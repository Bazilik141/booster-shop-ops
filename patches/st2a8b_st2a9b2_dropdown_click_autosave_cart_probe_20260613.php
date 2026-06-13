<?php
declare(strict_types=1);

/**
 * ST-2a.8b + ST-2a.9B2 combined patch.
 *
 * A) Fix NP dropdown mouse-click autosave:
 *    extension/PintaNovaPoshtaCod/catalog/view/template/shipping/js_checkout_shipping_address_form.twig
 *
 * B) Run cold-session add-to-cart timing probe:
 *    writes st2a8b_st2a9b2_dropdown_click_autosave_cart_probe_20260613-*.json/.md in site root.
 *
 * DB note: no schema/settings writes. The HTTP probe creates normal temporary storefront session/cart
 * state, the same as a visitor opening a product and adding it to cart.
 */

$patch = 'st2a8b_st2a9b2_dropdown_click_autosave_cart_probe_20260613';
$root = getcwd() ?: __DIR__;
$time = date('Ymd-His');
$backupDir = $root . '/_patch_backups/' . $patch . '-' . $time;
$skipProbe = in_array('--skip-probe', $argv ?? [], true);

function out(string $message): void {
    echo '[' . date('c') . '] ' . $message . PHP_EOL;
}

function fail(string $message): void {
    out('error=' . $message);
    out('done=failed');
    exit(1);
}

function qident(string $name): string {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        fail('unsafe identifier: ' . $name);
    }

    return '`' . $name . '`';
}

function db_rows(mysqli $db, string $sql): array {
    $result = $db->query($sql);

    if (!$result) {
        fail('db query failed: ' . $db->error);
    }

    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $result->free();

    return $rows;
}

function db_one(mysqli $db, string $sql): array {
    $rows = db_rows($db, $sql);

    return $rows[0] ?? [];
}

function redact_setting(string $key, string $value): string {
    foreach (['secret', 'token', 'password', 'pass', 'key', 'id'] as $needle) {
        if (stripos($key, $needle) !== false) {
            return $value === '' ? '' : '[redacted]';
        }
    }

    return $value;
}

function http_probe(string $label, string $url, string $cookieFile, string $method = 'GET', array $post = [], array $headers = []): array {
    if (!function_exists('curl_init')) {
        fail('PHP cURL extension is missing');
    }

    $ch = curl_init($url);

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_USERAGENT => 'BoosterShop-ST2a8b-ST2a9B2-Probe/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];

    if ($headers) {
        $options[CURLOPT_HTTPHEADER] = $headers;
    }

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($post);
    }

    curl_setopt_array($ch, $options);

    $start = microtime(true);
    $body = curl_exec($ch);
    $wallMs = round((microtime(true) - $start) * 1000, 2);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    $bodyString = is_string($body) ? $body : '';
    $json = json_decode($bodyString, true);

    $summary = [
        'label' => $label,
        'method' => $method,
        'http_code' => (int)($info['http_code'] ?? 0),
        'wall_ms' => $wallMs,
        'curl_total_ms' => isset($info['total_time']) ? round((float)$info['total_time'] * 1000, 2) : null,
        'namelookup_ms' => isset($info['namelookup_time']) ? round((float)$info['namelookup_time'] * 1000, 2) : null,
        'connect_ms' => isset($info['connect_time']) ? round((float)$info['connect_time'] * 1000, 2) : null,
        'starttransfer_ms' => isset($info['starttransfer_time']) ? round((float)$info['starttransfer_time'] * 1000, 2) : null,
        'bytes' => strlen($bodyString),
        'content_type' => $info['content_type'] ?? '',
        'curl_errno' => $errno,
        'curl_error' => $error,
    ];

    if (is_array($json)) {
        $summary['json_keys'] = array_keys($json);
        $summary['has_success'] = array_key_exists('success', $json);
        $summary['has_error'] = array_key_exists('error', $json);
        $summary['has_ps_add_to_cart'] = array_key_exists('ps_add_to_cart', $json);
    }

    return $summary;
}

function report_line(string $label, array $probe): string {
    $ps = !empty($probe['has_ps_add_to_cart']) ? ', ps_add_to_cart=yes' : '';
    $err = !empty($probe['curl_error']) ? ', curl_error=' . $probe['curl_error'] : '';

    return '- ' . $label . ': HTTP ' . ($probe['http_code'] ?? 0) . ', ' . ($probe['wall_ms'] ?? 0) . ' ms, bytes=' . ($probe['bytes'] ?? 0) . $ps . $err;
}

function apply_dropdown_click_fix(string $root, string $backupDir): void {
    $target = 'extension/PintaNovaPoshtaCod/catalog/view/template/shipping/js_checkout_shipping_address_form.twig';
    $targetPath = $root . '/' . $target;
    $checkout = 'catalog/view/template/checkout/checkout.twig';
    $checkoutPath = $root . '/' . $checkout;
    $marker = 'ST-2a.8b: mouse dropdown select must wake checkout autosave';

    out('target=' . $target);

    if (!is_file($targetPath)) {
        fail('missing ' . $target);
    }

    if (!is_file($checkoutPath)) {
        fail('missing ' . $checkout . '; cannot verify autosave flow');
    }

    $content = file_get_contents($targetPath);
    $checkoutContent = file_get_contents($checkoutPath);

    if ($content === false || $checkoutContent === false) {
        fail('cannot read target files');
    }

    $hasSavingReset = strpos($checkoutContent, "url.indexOf('checkout/register.save')") !== false
        && strpos($checkoutContent, "data('bsSaving', false)") !== false;
    $hasRegisterTimer = strpos($checkoutContent, 'bsRegisterTimer = window.setTimeout(triggerRegisterAutosave, 350);') !== false;
    $hasConfirmHook = strpos($checkoutContent, 'window.bsCheckoutConfirmNpDropdown') !== false
        && strpos($checkoutContent, 'window.bsCheckoutNpFieldChanged(input);') !== false;

    out('check_bsSaving_reset=' . ($hasSavingReset ? 'ok' : 'missing'));
    out('check_register_timer=' . ($hasRegisterTimer ? 'ok' : 'missing'));
    out('check_confirm_hook=' . ($hasConfirmHook ? 'ok' : 'missing'));

    if (!$hasSavingReset || !$hasRegisterTimer || !$hasConfirmHook) {
        fail('checkout autosave flow shape unexpected; refusing blind dropdown patch');
    }

    if (strpos($content, $marker) !== false) {
        out('dropdown_fix_already_applied=yes');
        out('changed=none');
        return;
    }

    $old = <<<'TWIG'
      input.val(label);
      hidePintaDropdown(input);

      if (window.bsCheckoutConfirmNpDropdown) {
        window.bsCheckoutConfirmNpDropdown(input[0], ref);
      }
TWIG;

    $new = <<<'TWIG'
      input.val(label);
      hidePintaDropdown(input);

      if (window.bsCheckoutConfirmNpDropdown) {
        window.bsCheckoutConfirmNpDropdown(input[0], ref);
      }

      // ST-2a.8b: mouse dropdown select must wake checkout autosave like keyboard changes do.
      input.trigger('change');
TWIG;

    $count = substr_count($content, $old);

    if ($count !== 1) {
        fail('pre-check failed for Pinta dropdown click handler: expected 1 match, got ' . $count);
    }

    $backupFile = $backupDir . '/' . $target;

    if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0755, true) && !is_dir(dirname($backupFile))) {
        fail('cannot create backup dir');
    }

    if (!copy($targetPath, $backupFile)) {
        fail('cannot backup ' . $target);
    }

    $patched = str_replace($old, $new, $content);

    if (file_put_contents($targetPath, $patched) === false) {
        fail('cannot write ' . $target);
    }

    out('backup=' . str_replace($root . '/', '', $backupFile));
    out('changed=' . $target);
    out('php_modified=none');
    out('rollback=restore ' . str_replace($root . '/', '', $backupFile));
}

function run_cart_timing_probe(string $root, string $patch, string $time): void {
    $config = $root . '/config.php';

    if (!is_file($config)) {
        fail('missing config.php; cannot run timing probe');
    }

    require_once $config;

    foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PORT', 'DB_PREFIX'] as $constant) {
        if (!defined($constant)) {
            fail('missing constant ' . $constant);
        }
    }

    $prefix = (string)DB_PREFIX;
    qident($prefix . 'setting');

    $db = @new mysqli((string)DB_HOSTNAME, (string)DB_USERNAME, (string)DB_PASSWORD, (string)DB_DATABASE, (int)DB_PORT);

    if ($db->connect_errno) {
        fail('db connect failed: ' . $db->connect_error);
    }

    $db->set_charset('utf8mb4');

    $settingTable = qident($prefix . 'setting');
    $eventTable = qident($prefix . 'event');
    $sessionTable = qident($prefix . 'session');
    $productTable = qident($prefix . 'product');
    $productDescriptionTable = qident($prefix . 'product_description');
    $productOptionTable = qident($prefix . 'product_option');
    $productSubscriptionTable = qident($prefix . 'product_subscription');

    $settingsWanted = [
        'analytics_ps_enhanced_measurement_status',
        'analytics_ps_enhanced_measurement_tracking_delay',
        'analytics_ps_enhanced_measurement_track_add_to_cart',
        'analytics_ps_enhanced_measurement_track_remove_from_cart',
        'analytics_ps_enhanced_measurement_track_select_item',
        'analytics_ps_enhanced_measurement_track_view_cart',
        'analytics_ps_enhanced_measurement_track_view_item',
        'analytics_ps_enhanced_measurement_track_view_item_list',
        'analytics_ps_enhanced_measurement_track_begin_checkout',
        'analytics_ps_enhanced_measurement_adwords_add_to_cart_label',
    ];

    $settingSqlKeys = "'" . implode("','", array_map([$db, 'real_escape_string'], $settingsWanted)) . "'";
    $settingsRows = db_rows($db, "SELECT `key`, `value` FROM {$settingTable} WHERE `key` IN ({$settingSqlKeys}) ORDER BY `key`");
    $settings = [];

    foreach ($settingsRows as $row) {
        $settings[$row['key']] = redact_setting($row['key'], (string)$row['value']);
    }

    $eventTriggers = [
        'catalog/controller/checkout/cart.add/after',
        'catalog/view/common/cart/before',
        'catalog/view/checkout/cart_info/before',
        'catalog/view/product/product/before',
    ];

    $triggerSql = "'" . implode("','", array_map([$db, 'real_escape_string'], $eventTriggers)) . "'";
    $events = db_rows($db, "SELECT `event_id`, `code`, `trigger`, `action`, `status`, `sort_order` FROM {$eventTable} WHERE `trigger` IN ({$triggerSql}) ORDER BY `trigger`, `sort_order`, `event_id`");

    $sessionMetrics = db_one($db, "SELECT COUNT(*) AS rows_total, SUM(`expire` < UTC_TIMESTAMP()) AS rows_expired, ROUND(AVG(CHAR_LENGTH(`data`)), 1) AS avg_data_bytes, MAX(CHAR_LENGTH(`data`)) AS max_data_bytes, SUM(`data` LIKE '%ps_item_list_info%') AS rows_with_ps_item_list_info FROM {$sessionTable}");
    $sessionIndexes = db_rows($db, "SHOW INDEX FROM {$sessionTable}");

    $product = db_one($db, "SELECT p.`product_id`, p.`quantity`, pd.`name` FROM {$productTable} p LEFT JOIN {$productDescriptionTable} pd ON (pd.`product_id` = p.`product_id` AND pd.`language_id` = 1) LEFT JOIN {$productOptionTable} po ON (po.`product_id` = p.`product_id` AND po.`required` = '1') LEFT JOIN {$productSubscriptionTable} ps ON (ps.`product_id` = p.`product_id`) WHERE p.`status` = '1' AND p.`date_available` <= NOW() AND p.`quantity` > 0 AND po.`product_option_id` IS NULL AND ps.`product_id` IS NULL ORDER BY p.`viewed` DESC, p.`product_id` DESC LIMIT 1");

    if (!$product) {
        $product = db_one($db, "SELECT p.`product_id`, p.`quantity`, pd.`name` FROM {$productTable} p LEFT JOIN {$productDescriptionTable} pd ON (pd.`product_id` = p.`product_id` AND pd.`language_id` = 1) LEFT JOIN {$productOptionTable} po ON (po.`product_id` = p.`product_id` AND po.`required` = '1') LEFT JOIN {$productSubscriptionTable} ps ON (ps.`product_id` = p.`product_id`) WHERE p.`status` = '1' AND p.`date_available` <= NOW() AND po.`product_option_id` IS NULL AND ps.`product_id` IS NULL ORDER BY p.`viewed` DESC, p.`product_id` DESC LIMIT 1");
    }

    if (!$product || empty($product['product_id'])) {
        fail('no enabled product without required options/subscriptions found for HTTP probe');
    }

    $base = '';

    if (defined('HTTPS_SERVER') && HTTPS_SERVER) {
        $base = (string)HTTPS_SERVER;
    } elseif (defined('HTTP_SERVER') && HTTP_SERVER) {
        $base = (string)HTTP_SERVER;
    }

    if ($base === '') {
        fail('missing HTTP_SERVER/HTTPS_SERVER');
    }

    $base = rtrim($base, '/');
    $language = 'uk-ua';
    $productId = (int)$product['product_id'];
    $productUrl = $base . '/index.php?route=product/product&language=' . rawurlencode($language) . '&product_id=' . $productId;
    $cartAddUrl = $base . '/index.php?route=checkout/cart.add&language=' . rawurlencode($language);
    $cartInfoUrl = $base . '/index.php?route=common/cart.info&language=' . rawurlencode($language);
    $cookieConfirmUrl = $base . '/index.php?route=common/cookie.confirm&language=' . rawurlencode($language) . '&agree=1';
    $reportBase = $patch . '-' . $time;
    $cookieA = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $reportBase . '-cold.cookie';
    $cookieB = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $reportBase . '-cookie-warm.cookie';

    @unlink($cookieA);
    @unlink($cookieB);

    $probes = [];
    $probes[] = http_probe('cold_product_page', $productUrl, $cookieA);
    $probes[] = http_probe('cold_cart_add', $cartAddUrl, $cookieA, 'POST', ['product_id' => $productId, 'quantity' => 1]);
    $probes[] = http_probe('cold_cart_info', $cartInfoUrl, $cookieA);
    $probes[] = http_probe('warm_cart_add_same_session', $cartAddUrl, $cookieA, 'POST', ['product_id' => $productId, 'quantity' => 1]);
    $probes[] = http_probe('warm_cart_info_same_session', $cartInfoUrl, $cookieA);
    $probes[] = http_probe('cookiewarm_product_page', $productUrl, $cookieB);
    $probes[] = http_probe('cookiewarm_confirm', $cookieConfirmUrl, $cookieB, 'GET', [], ['X-Requested-With: XMLHttpRequest']);
    $probes[] = http_probe('cookiewarm_cart_add', $cartAddUrl, $cookieB, 'POST', ['product_id' => $productId, 'quantity' => 1]);
    $probes[] = http_probe('cookiewarm_cart_info', $cartInfoUrl, $cookieB);

    @unlink($cookieA);
    @unlink($cookieB);

    $report = [
        'patch' => $patch,
        'time' => date('c'),
        'base' => $base,
        'product_id' => $productId,
        'product_quantity' => isset($product['quantity']) ? (int)$product['quantity'] : null,
        'db' => [
            'prefix' => $prefix,
            'session_metrics' => $sessionMetrics,
            'session_indexes' => $sessionIndexes,
            'ps_settings' => $settings,
            'relevant_events' => $events,
        ],
        'http_probes' => $probes,
    ];

    $jsonPath = $root . '/' . $reportBase . '.json';
    $mdPath = $root . '/' . $reportBase . '.md';
    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false || file_put_contents($jsonPath, $json . PHP_EOL) === false) {
        fail('cannot write JSON report');
    }

    $md = [];
    $md[] = '# ST-2a.8b + ST-2a.9B2 Combined Report';
    $md[] = '';
    $md[] = '- Time: ' . $report['time'];
    $md[] = '- Product ID: ' . $productId;
    $md[] = '- Code fix: Pinta dropdown click dispatches checkout change event after bsCheckoutConfirmNpDropdown.';
    $md[] = '- DB: no schema/settings writes; HTTP probe creates temporary storefront session/cart state.';
    $md[] = '';
    $md[] = '## Session DB';
    $md[] = '- rows_total: ' . ($sessionMetrics['rows_total'] ?? 'n/a');
    $md[] = '- rows_expired: ' . ($sessionMetrics['rows_expired'] ?? 'n/a');
    $md[] = '- avg_data_bytes: ' . ($sessionMetrics['avg_data_bytes'] ?? 'n/a');
    $md[] = '- max_data_bytes: ' . ($sessionMetrics['max_data_bytes'] ?? 'n/a');
    $md[] = '- rows_with_ps_item_list_info: ' . ($sessionMetrics['rows_with_ps_item_list_info'] ?? 'n/a');
    $md[] = '';
    $md[] = '## HTTP Timings';

    foreach ($probes as $probe) {
        $md[] = report_line((string)$probe['label'], $probe);
    }

    $md[] = '';
    $md[] = '## Relevant Events';

    foreach ($events as $event) {
        $md[] = '- #' . $event['event_id'] . ' status=' . $event['status'] . ' trigger=' . $event['trigger'] . ' action=' . $event['action'];
    }

    $md[] = '';
    $md[] = '## Notes';
    $md[] = '- If cold_cart_add is slow and warm_cart_add_same_session is fast, focus on checkout/cart.add server path and after-events.';
    $md[] = '- If cold_cart_info is slow, focus on common/cart.info and cart_info/common_cart before-events.';
    $md[] = '- If cookiewarm_cart_add is materially faster than cold_cart_add, cookie-confirm/session-warm behavior is confirmed.';

    if (file_put_contents($mdPath, implode(PHP_EOL, $md) . PHP_EOL) === false) {
        fail('cannot write Markdown report');
    }

    out('report_json=' . basename($jsonPath));
    out('report_md=' . basename($mdPath));

    foreach ($probes as $probe) {
        out('timing ' . $probe['label'] . ' http=' . $probe['http_code'] . ' ms=' . $probe['wall_ms'] . (!empty($probe['has_ps_add_to_cart']) ? ' ps_add_to_cart=yes' : ''));
    }

    out('session_rows=' . ($sessionMetrics['rows_total'] ?? 'n/a'));
    out('session_rows_with_ps_item_list_info=' . ($sessionMetrics['rows_with_ps_item_list_info'] ?? 'n/a'));
}

out('patch=' . $patch);
out('cwd=' . $root);
out('scope=ST-2a.8b Pinta dropdown click autosave fix + ST-2a.9B2 cart cold timing probe');
out('db_schema_changes=none');
out('db_runtime_note=HTTP probe may create temporary storefront session/cart rows');

apply_dropdown_click_fix($root, $backupDir);

if ($skipProbe) {
    out('probe=skipped_by_arg');
} else {
    run_cart_timing_probe($root, $patch, $time);
}

out('done=ok');

@unlink(__FILE__);
out('self_delete=' . (file_exists(__FILE__) ? 'failed' : 'ok'));
