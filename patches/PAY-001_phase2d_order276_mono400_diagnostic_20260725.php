<?php
declare(strict_types=1);

/*
 * PAY-001 Phase 2d read-only Mono HTTP 400 diagnostic for order #276.
 *
 * Reads only the already-saved Mono response and monetary order rows.
 * It does not call Monobank or change DB/settings/order/session/files.
 */

const PAY001_ORDER_ID = 276;
const PAY001_EXPECTED_MONO_SHA256 = 'd68a2d9cd8345ba180518f3b740989c15901e860838656c1572788b2d59f2186';

function pay001_line(string $key, string|int|float $value): void {
    echo $key . '=' . $value . PHP_EOL;
}

function pay001_redact(string $value): string {
    $value = preg_replace('/\b(?:\+?380|0)\d{9}\b/u', '[phone-redacted]', $value) ?? $value;
    $value = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', '[email-redacted]', $value) ?? $value;
    $value = preg_replace('/\b(signature|store[_ -]?secret|authorization|token)\b\s*[:=]\s*[^\s,;"\]}]+/iu', '$1=[redacted]', $value) ?? $value;

    return $value;
}

function pay001_safe_response(mixed $value, string $key = ''): mixed {
    if (preg_match('/phone|email|secret|signature|authorization|token|payload|request/iu', $key)) {
        return '[redacted]';
    }

    if (is_array($value)) {
        $safe = [];
        foreach ($value as $childKey => $childValue) {
            $safe[(string)$childKey] = pay001_safe_response($childValue, (string)$childKey);
        }
        return $safe;
    }

    if (is_string($value)) {
        return pay001_redact(mb_substr($value, 0, 1000));
    }

    return $value;
}

function pay001_json(array $row): string {
    $encoded = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return is_string($encoded) ? pay001_redact($encoded) : '{}';
}

$root = getcwd();
pay001_line('cwd', $root);
pay001_line('time', date(DATE_ATOM));
pay001_line('mode', 'read_only_no_api_calls');
pay001_line('order_id', PAY001_ORDER_ID);

$config = $root . DIRECTORY_SEPARATOR . 'config.php';

if (!is_file($config)) {
    pay001_line('error', 'run_from_opencart_root_config_missing');
    exit(1);
}

require $config;

$relative = 'extension/mono_chast/catalog/controller/payment/mono_chast.php';
$controller = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

if (!is_file($controller)) {
    pay001_line('error', 'mono_controller_missing');
    exit(1);
}

$controllerHash = hash_file('sha256', $controller);
pay001_line('mono_controller_sha256', $controllerHash);
pay001_line(
    'mono_controller_matches_expected_live',
    hash_equals(PAY001_EXPECTED_MONO_SHA256, $controllerHash) ? 'yes' : 'no'
);

mysqli_report(MYSQLI_REPORT_OFF);
$db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);

if ($db->connect_errno) {
    pay001_line('db_connect', 'failed:' . $db->connect_errno);
    exit(1);
}

if (!$db->set_charset('utf8mb4')) {
    pay001_line('db_charset', 'failed:' . $db->errno);
    $db->close();
    exit(1);
}

pay001_line('db_connect', 'ok');
$prefix = DB_PREFIX;
$orderId = PAY001_ORDER_ID;

$orderResult = $db->query(
    "SELECT `order_id`,`total`,`currency_code`,`payment_method` FROM `{$prefix}order` " .
    "WHERE `order_id`={$orderId} LIMIT 1"
);

if ($orderResult instanceof mysqli_result && $orderResult->num_rows === 1) {
    $order = $orderResult->fetch_assoc();
    $payment = json_decode((string)($order['payment_method'] ?? ''), true);
    $payment = is_array($payment) ? $payment : [];

    echo 'order> ' . pay001_json([
        'order_id' => (int)$order['order_id'],
        'total' => (float)$order['total'],
        'currency_code' => (string)$order['currency_code'],
        'payment_code' => (string)($payment['code'] ?? ''),
    ]) . PHP_EOL;
    $orderResult->free();
} else {
    pay001_line('order_query', 'missing_or_failed:' . $db->errno);
}

$productResult = $db->query(
    "SELECT `order_product_id`,`product_id`,`quantity`,`price`,`total`,`tax` " .
    "FROM `{$prefix}order_product` WHERE `order_id`={$orderId} ORDER BY `order_product_id`"
);

if ($productResult instanceof mysqli_result) {
    pay001_line('order_product_rows', $productResult->num_rows);
    while ($row = $productResult->fetch_assoc()) {
        echo 'order_product> ' . pay001_json($row) . PHP_EOL;
    }
    $productResult->free();
} else {
    pay001_line('order_product_query', 'failed:' . $db->errno);
}

$totalResult = $db->query(
    "SELECT `code`,`title`,`value`,`sort_order` FROM `{$prefix}order_total` " .
    "WHERE `order_id`={$orderId} ORDER BY `sort_order`,`order_total_id`"
);

if ($totalResult instanceof mysqli_result) {
    pay001_line('order_total_rows', $totalResult->num_rows);
    while ($row = $totalResult->fetch_assoc()) {
        echo 'order_total> ' . pay001_json($row) . PHP_EOL;
    }
    $totalResult->free();
} else {
    pay001_line('order_total_query', 'failed:' . $db->errno);
}

$eventResult = $db->query(
    "SELECT `mono_chast_event_id`,`event_source`,`state`,`order_sub_state`,`http_status`,`trace_id`,`payload`,`date_added` " .
    "FROM `{$prefix}mono_chast_event` WHERE `order_id`={$orderId} " .
    "ORDER BY `mono_chast_event_id` ASC"
);

if ($eventResult instanceof mysqli_result) {
    pay001_line('mono_event_rows', $eventResult->num_rows);

    while ($row = $eventResult->fetch_assoc()) {
        $payload = json_decode((string)($row['payload'] ?? ''), true);
        $payload = is_array($payload) ? pay001_safe_response($payload) : ['decode' => 'failed'];

        echo 'mono_event> ' . pay001_json([
            'mono_chast_event_id' => (int)$row['mono_chast_event_id'],
            'event_source' => (string)$row['event_source'],
            'state' => (string)$row['state'],
            'order_sub_state' => (string)$row['order_sub_state'],
            'http_status' => (int)$row['http_status'],
            'trace_id' => (string)$row['trace_id'],
            'response' => $payload,
            'date_added' => (string)$row['date_added'],
        ]) . PHP_EOL;
    }
    $eventResult->free();
} else {
    pay001_line('mono_event_query', 'failed:' . $db->errno);
}

$db->close();
pay001_line('done', 'ok');
@unlink(__FILE__);
