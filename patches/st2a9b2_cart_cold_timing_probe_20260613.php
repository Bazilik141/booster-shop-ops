<?php
declare(strict_types=1);

/**
 * ST-2a.9 B2 - cold-session add-to-cart timing probe.
 *
 * Read-only diagnostic for code/DB schema:
 * - SELECT-only DB checks for session and ps_enhanced_measurement events/settings.
 * - HTTP probes against the live storefront using a temporary cookie jar.
 * - Writes a compact JSON/Markdown report in the site root.
 */

$patch = 'st2a9b2_cart_cold_timing_probe_20260613';
$root = getcwd() ?: __DIR__;
$time = date('Ymd-His');
$reportBase = $patch . '-' . $time;

function out(string $message): void {
    echo '[' . date('c') . '] ' . $message . PHP_EOL;
}

function fail_probe(string $message): void {
    out('error=' . $message);
    out('done=failed');
    exit(1);
}

function qident(string $name): string {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        fail_probe('unsafe identifier: ' . $name);
    }

    return '`' . $name . '`';
}

function db_rows(mysqli $db, string $sql): array {
    $result = $db->query($sql);

    if (!$result) {
        fail_probe('db query failed: ' . $db->error);
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
    $sensitive = ['secret', 'token', 'password', 'pass', 'key', 'id'];

    foreach ($sensitive as $needle) {
        if (stripos($key, $needle) !== false) {
            return $value === '' ? '' : '[redacted]';
        }
    }

    return $value;
}

function http_probe(string $label, string $url, string $cookieFile, string $method = 'GET', array $post = [], array $headers = []): array {
    if (!function_exists('curl_init')) {
        fail_probe('PHP cURL extension is missing');
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
        CURLOPT_USERAGENT => 'BoosterShop-ST2a9B2-Probe/1.0',
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
    $code = $probe['http_code'] ?? 0;
    $ms = $probe['wall_ms'] ?? 0;
    $bytes = $probe['bytes'] ?? 0;
    $ps = !empty($probe['has_ps_add_to_cart']) ? ', ps_add_to_cart=yes' : '';
    $err = !empty($probe['curl_error']) ? ', curl_error=' . $probe['curl_error'] : '';

    return '- ' . $label . ': HTTP ' . $code . ', ' . $ms . ' ms, bytes=' . $bytes . $ps . $err;
}

out('patch=' . $patch);
out('cwd=' . $root);
out('scope=read-only timing probe; no code changes, no DB writes except normal storefront temp session/cart requests');

$config = $root . '/config.php';

if (!is_file($config)) {
    fail_probe('missing config.php; upload/run from OpenCart public_html root');
}

require_once $config;

foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PORT', 'DB_PREFIX'] as $constant) {
    if (!defined($constant)) {
        fail_probe('missing constant ' . $constant);
    }
}

$prefix = (string)DB_PREFIX;
qident($prefix . 'setting');

$db = @new mysqli((string)DB_HOSTNAME, (string)DB_USERNAME, (string)DB_PASSWORD, (string)DB_DATABASE, (int)DB_PORT);

if ($db->connect_errno) {
    fail_probe('db connect failed: ' . $db->connect_error);
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

$product = db_one($db, "SELECT p.`product_id`, p.`quantity`, pd.`name` FROM {$productTable} p LEFT JOIN {$productDescriptionTable} pd ON (pd.`product_id` = p.`product_id` AND pd.`language_id` = 1) LEFT JOIN {$productOptionTable} po ON (po.`product_id` = p.`product_id` AND po.`required` = '1') LEFT JOIN {$productSubscriptionTable} ps ON (ps.`product_id` = p.`product_id`) WHERE p.`status` = '1' AND p.`date_available` <= NOW() AND p.`quantity` > 0 AND po.`product_option_id` IS NULL AND ps.`product_id` IS NULL ORDER BY p.`product_id` DESC LIMIT 1");

if (!$product) {
    $product = db_one($db, "SELECT p.`product_id`, p.`quantity`, pd.`name` FROM {$productTable} p LEFT JOIN {$productDescriptionTable} pd ON (pd.`product_id` = p.`product_id` AND pd.`language_id` = 1) LEFT JOIN {$productOptionTable} po ON (po.`product_id` = p.`product_id` AND po.`required` = '1') LEFT JOIN {$productSubscriptionTable} ps ON (ps.`product_id` = p.`product_id`) WHERE p.`status` = '1' AND p.`date_available` <= NOW() AND po.`product_option_id` IS NULL AND ps.`product_id` IS NULL ORDER BY p.`product_id` DESC LIMIT 1");
}

if (!$product || empty($product['product_id'])) {
    fail_probe('no enabled product without required options/subscriptions found for HTTP probe');
}

$base = '';

if (defined('HTTPS_SERVER') && HTTPS_SERVER) {
    $base = (string)HTTPS_SERVER;
} elseif (defined('HTTP_SERVER') && HTTP_SERVER) {
    $base = (string)HTTP_SERVER;
}

if ($base === '') {
    fail_probe('missing HTTP_SERVER/HTTPS_SERVER');
}

$base = rtrim($base, '/');
$language = 'uk-ua';
$productId = (int)$product['product_id'];
$productUrl = $base . '/index.php?route=product/product&language=' . rawurlencode($language) . '&product_id=' . $productId;
$cartAddUrl = $base . '/index.php?route=checkout/cart.add&language=' . rawurlencode($language);
$cartInfoUrl = $base . '/index.php?route=common/cart.info&language=' . rawurlencode($language);
$cookieConfirmUrl = $base . '/index.php?route=common/cookie.confirm&language=' . rawurlencode($language) . '&agree=1';

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
    fail_probe('cannot write JSON report');
}

$md = [];
$md[] = '# ST-2a.9 B2 Cart Cold Timing Probe';
$md[] = '';
$md[] = '- Time: ' . $report['time'];
$md[] = '- Product ID: ' . $productId;
$md[] = '- Scope: read-only diagnostic; no code changes; no DB schema/setting writes.';
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
    fail_probe('cannot write Markdown report');
}

out('report_json=' . basename($jsonPath));
out('report_md=' . basename($mdPath));

foreach ($probes as $probe) {
    out('timing ' . $probe['label'] . ' http=' . $probe['http_code'] . ' ms=' . $probe['wall_ms'] . (!empty($probe['has_ps_add_to_cart']) ? ' ps_add_to_cart=yes' : ''));
}

out('session_rows=' . ($sessionMetrics['rows_total'] ?? 'n/a'));
out('session_rows_with_ps_item_list_info=' . ($sessionMetrics['rows_with_ps_item_list_info'] ?? 'n/a'));
out('done=ok');

@unlink(__FILE__);
out('self_delete=' . (file_exists(__FILE__) ? 'failed' : 'ok'));
