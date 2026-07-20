<?php
/**
 * ST-2c Phase 0 live diagnostics (read-only).
 *
 * Purpose: collect the current evidence needed before a checkout / payment patch.
 * It does not write OpenCart files or database rows. It prints no credentials.
 * On a successful completed run it removes itself.
 */
declare(strict_types=1);

const ST2C_PREFIX = 'ST-2c Phase0';

function st2c_log(string $message): void {
    echo ST2C_PREFIX . ': ' . $message . PHP_EOL;
}

function st2c_fail(string $message): never {
    st2c_log('ERROR: ' . $message);
    exit(1);
}

function st2c_excerpt(string $value, int $offset, int $radius = 180): string {
    $start = max(0, $offset - $radius);
    $part = substr($value, $start, $radius * 2 + 180);
    $part = preg_replace('/\s+/u', ' ', $part) ?? $part;
    return trim($part);
}

function st2c_scan_source(string $root, string $relative, array $patterns, bool $required = true): void {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (!is_file($path)) {
        if ($required) {
            st2c_fail('required_file_missing=' . $relative);
        }
        st2c_log('optional_file_missing=' . $relative);
        return;
    }

    $source = file_get_contents($path);
    if ($source === false) {
        st2c_fail('file_read_failed=' . $relative);
    }

    st2c_log('file=' . $relative . ' bytes=' . strlen($source));
    foreach ($patterns as $label => $pattern) {
        $matched = preg_match_all($pattern, $source, $hits, PREG_OFFSET_CAPTURE);
        if ($matched === false) {
            st2c_fail('invalid_pattern=' . $label);
        }
        st2c_log('anchor=' . $label . ' count=' . $matched);
        foreach (array_slice($hits[0], 0, 5) as $hit) {
            $before = substr($source, 0, (int)$hit[1]);
            $line = substr_count($before, "\n") + 1;
            st2c_log('anchor_context=' . $label . ' line=' . $line . ' text=' . st2c_excerpt($source, (int)$hit[1]));
        }
    }
}

function st2c_connect_database(): ?mysqli {
    if (!defined('DB_HOSTNAME') || !defined('DB_USERNAME') || !defined('DB_PASSWORD') || !defined('DB_DATABASE')) {
        st2c_log('db=unavailable_missing_config_constants');
        return null;
    }
    if (!extension_loaded('mysqli')) {
        st2c_log('db=unavailable_mysqli_extension_missing');
        return null;
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @new mysqli((string)DB_HOSTNAME, (string)DB_USERNAME, (string)DB_PASSWORD, (string)DB_DATABASE, defined('DB_PORT') ? (int)DB_PORT : 3306);
    if ($db->connect_errno) {
        st2c_log('db=unavailable_connect_failed');
        return null;
    }
    $db->set_charset('utf8mb4');
    return $db;
}

function st2c_prefix(): string {
    $prefix = defined('DB_PREFIX') ? (string)DB_PREFIX : '';
    if ($prefix === '' || !preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
        st2c_fail('db_prefix_invalid_or_missing');
    }
    return $prefix;
}

function st2c_table_exists(mysqli $db, string $table): bool {
    $quoted = $db->real_escape_string($table);
    $result = $db->query("SHOW TABLES LIKE '{$quoted}'");
    return $result instanceof mysqli_result && $result->num_rows === 1;
}

function st2c_scan_settings(mysqli $db, string $prefix): void {
    $table = $prefix . 'setting';
    if (!st2c_table_exists($db, $table)) {
        st2c_log('settings=unavailable_table_missing');
        return;
    }

    $keys = [
        'shipping_pinta_nova_poshta_free_from',
        'payment_hutko_shipping_include',
    ];
    $quotedKeys = implode(',', array_map(static fn(string $key): string => "'" . $db->real_escape_string($key) . "'", $keys));
    $result = $db->query("SELECT store_id, `code`, `key`, `value` FROM `{$table}` WHERE `key` IN ({$quotedKeys}) ORDER BY store_id, `key`");
    if (!$result instanceof mysqli_result) {
        st2c_log('settings=query_failed');
        return;
    }
    if ($result->num_rows === 0) {
        st2c_log('settings=keys_not_persisted');
        return;
    }
    while ($row = $result->fetch_assoc()) {
        st2c_log('setting store_id=' . $row['store_id'] . ' code=' . $row['code'] . ' key=' . $row['key'] . ' value=' . $row['value']);
    }
}

function st2c_scan_database_content(mysqli $db, string $prefix): void {
    $tables = [
        ['name' => 'information_description', 'id' => 'information_id', 'title' => 'title'],
        ['name' => 'category_description', 'id' => 'category_id', 'title' => 'name'],
        ['name' => 'product_description', 'id' => 'product_id', 'title' => 'name'],
    ];
    $where = "(`description` LIKE '%безкоштовн%доставк%' OR `description` LIKE '%безкоштовна доставка%' OR `description` LIKE '%free shipping%')";

    foreach ($tables as $definition) {
        $table = $prefix . $definition['name'];
        if (!st2c_table_exists($db, $table)) {
            st2c_log('content_table_missing=' . $table);
            continue;
        }
        $id = $definition['id'];
        $title = $definition['title'];
        $result = $db->query("SELECT `{$id}` AS entity_id, language_id, `{$title}` AS title, `description` FROM `{$table}` WHERE {$where} ORDER BY entity_id, language_id LIMIT 100");
        if (!$result instanceof mysqli_result) {
            st2c_log('content_query_failed=' . $table);
            continue;
        }
        st2c_log('content_table=' . $table . ' matches=' . $result->num_rows);
        while ($row = $result->fetch_assoc()) {
            $description = (string)$row['description'];
            preg_match('/безкоштовн.{0,100}доставк/ui', $description, $match, PREG_OFFSET_CAPTURE);
            $offset = isset($match[0][1]) ? (int)$match[0][1] : 0;
            st2c_log('content_match table=' . $table . ' id=' . $row['entity_id'] . ' language_id=' . $row['language_id'] . ' title=' . preg_replace('/\s+/u', ' ', (string)$row['title']) . ' text=' . st2c_excerpt($description, $offset));
        }
    }
}

function st2c_scan_template_tree(string $root): void {
    $roots = ['catalog', 'extension'];
    $allowed = ['php', 'twig', 'js', 'html', 'htm', 'xml'];
    $pattern = '/безкоштовн.{0,100}доставк/ui';
    $matches = 0;

    foreach ($roots as $folder) {
        $path = $root . DIRECTORY_SEPARATOR . $folder;
        if (!is_dir($path)) {
            st2c_log('content_tree_missing=' . $folder);
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getSize() > 2 * 1024 * 1024) {
                continue;
            }
            if (!in_array(strtolower($file->getExtension()), $allowed, true)) {
                continue;
            }
            $content = @file_get_contents($file->getPathname());
            if (!is_string($content) || !preg_match_all($pattern, $content, $hits, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
            foreach (array_slice($hits[0], 0, 5) as $hit) {
                $matches++;
                st2c_log('file_content_match=' . $relative . ' text=' . st2c_excerpt($content, (int)$hit[1]));
            }
        }
    }
    st2c_log('file_content_matches_total=' . $matches);
}

$self = __FILE__;
$root = getcwd();
if (!is_file($root . DIRECTORY_SEPARATOR . 'config.php')) {
    st2c_fail('run_from_public_html_required=config.php_missing');
}

$lintOutput = [];
$lintCode = 1;
@exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($self), $lintOutput, $lintCode);
if ($lintCode !== 0) {
    st2c_fail('php_l_failed');
}
st2c_log('php_l=ok');
st2c_log('cwd=' . $root);
st2c_log('time=' . gmdate('c'));
st2c_log('backup=not_applicable_readonly');

st2c_scan_source($root, 'extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php', [
    'get_quote' => '/function\s+getQuote\s*\(/',
    'hardcoded_cost_zero' => "/['\"]cost['\"]\s*=>\s*0\b/",
    'document_price' => '/getDocumentPrice\s*\(/',
    'free_threshold_key' => '/shipping_pinta_nova_poshta_free_from/',
]);
st2c_scan_source($root, 'catalog/view/javascript/checkout-reskin.js', [
    'rd13_stub' => '/RD13-STUB/',
    'hardcoded_free_threshold' => '/FREE_SHIP_THRESHOLD\s*=\s*\d+/',
    'payable_total_basis' => '/payableSource\s*=\s*grand\s*\|\|\s*subtotal/',
    'coupon_summary_endpoint' => "/checkout\\/coupon\.['\"]?\s*\+?\s*action|checkout\\/coupon\\.summary/",
]);
st2c_scan_source($root, 'extension/hutko/catalog/controller/payment/hutko.php', [
    'shipping_include_config' => '/payment_hutko_shipping_include/',
    'order_total_shipping_query' => "/code\s*=\s*['\"]shipping['\"]/",
]);
st2c_scan_source($root, 'extension/PintaNovaPoshtaCod/admin/controller/shipping/pinta_nova_poshta.php', [
    'threshold_setting' => '/shipping_pinta_nova_poshta_free_from/',
    'default_1500_or_2000' => "/shipping_pinta_nova_poshta_free_from['\"]?\s*=>\s*['\"]?(?:1500|2000)/",
]);
st2c_scan_source($root, 'extension/PintaNovaPoshtaCod/admin/view/template/shipping/pinta_nova_poshta/index.twig', [
    'threshold_input' => '/shipping_pinta_nova_poshta_free_from/',
]);
st2c_scan_source($root, 'catalog/controller/checkout/coupon.php', [
    'summary_method' => '/function\s+summary\s*\(/',
    'summary_totals' => "/['\"]totals['\"]\s*=>/",
], false);
st2c_scan_source($root, 'catalog/model/total/shipping.php', [
    'shipping_total' => '/function\s+getTotal\s*\(/',
], false);

require_once $root . DIRECTORY_SEPARATOR . 'config.php';
$db = st2c_connect_database();
if ($db instanceof mysqli) {
    $prefix = st2c_prefix();
    st2c_scan_settings($db, $prefix);
    st2c_scan_database_content($db, $prefix);
    $db->close();
}
st2c_scan_template_tree($root);
st2c_log('crm_order_sync_payload=not_checked_no_crm_endpoint_or_token_in_runner');
st2c_log('done=ok');
@unlink($self);
