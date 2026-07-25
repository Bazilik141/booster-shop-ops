<?php
declare(strict_types=1);

/*
 * PAY-001 Phase 2d read-only order lifecycle diagnostic.
 *
 * Scope:
 * - inspects OpenCart order #276, its history, and Mono audit metadata;
 * - reports only phone format shape, never the phone number;
 * - reports hashes/presence of the checkout/payment files involved;
 * - lists checkout-related OpenCart events that may redirect stock checkout;
 * - does NOT call Monobank, change DB/settings/order/session/files, or retry payment.
 *
 * Run from the OpenCart root. The script self-deletes after a successful read.
 */

const PAY001_ORDER_ID = 276;

function pay001_line(string $key, string|int|float $value): void {
    echo $key . '=' . $value . PHP_EOL;
}

function pay001_redact(string $value): string {
    $value = preg_replace('/\b(?:\+?380|0)\d{9}\b/u', '[phone-redacted]', $value) ?? $value;
    $value = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', '[email-redacted]', $value) ?? $value;
    $value = preg_replace('/\b(signature|store[_ -]?secret|authorization|token)\b\s*[:=]\s*[^\s,;]+/iu', '$1=[redacted]', $value) ?? $value;

    return $value;
}

function pay001_phone_shape(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    if (strlen($digits) === 12 && strncmp($digits, '380', 3) === 0) {
        return 'ua_international_380_12_digits';
    }

    if (strlen($digits) === 10 && strncmp($digits, '0', 1) === 0) {
        return 'ua_local_0_10_digits';
    }

    return $digits === '' ? 'empty' : 'other_' . strlen($digits) . '_digits';
}

function pay001_json(array $row): string {
    $encoded = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return is_string($encoded) ? pay001_redact($encoded) : '{}';
}

function pay001_table_exists(mysqli $db, string $table): bool {
    $escaped = $db->real_escape_string($table);
    $result = $db->query("SHOW TABLES LIKE '{$escaped}'");

    if (!$result instanceof mysqli_result) {
        return false;
    }

    $exists = $result->num_rows > 0;
    $result->free();

    return $exists;
}

function pay001_columns(mysqli $db, string $table): array {
    $columns = [];
    $result = $db->query("SHOW COLUMNS FROM `{$table}`");

    if (!$result instanceof mysqli_result) {
        return $columns;
    }

    while ($row = $result->fetch_assoc()) {
        $columns[] = (string)$row['Field'];
    }
    $result->free();

    return $columns;
}

function pay001_rows(mysqli $db, string $sql, string $label, callable $sanitize = null): void {
    $result = $db->query($sql);

    if (!$result instanceof mysqli_result) {
        pay001_line($label . '_query', 'failed:' . $db->errno);
        return;
    }

    pay001_line($label . '_rows', $result->num_rows);

    while ($row = $result->fetch_assoc()) {
        if ($sanitize !== null) {
            $row = $sanitize($row);
        }
        echo $label . '> ' . pay001_json($row) . PHP_EOL;
    }
    $result->free();
}

function pay001_payment_summary(mixed $payment): array {
    if (is_string($payment)) {
        $decoded = json_decode($payment, true);
        $payment = is_array($decoded) ? $decoded : [];
    }

    if (!is_array($payment)) {
        $payment = [];
    }

    return [
        'code' => (string)($payment['code'] ?? ''),
        'name' => (string)($payment['name'] ?? ''),
    ];
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

$criticalFiles = [
    'catalog/controller/checkout/checkout.php',
    'catalog/controller/checkout/confirm.php',
    'catalog/controller/checkout/payment_method.php',
    'catalog/controller/checkout/success.php',
    'catalog/view/template/checkout/checkout.twig',
    'catalog/view/template/checkout/payment_method.twig',
    'catalog/view/javascript/checkout-state.js',
    'catalog/view/javascript/checkout-reskin.js',
    'catalog/view/template/common/header.twig',
    'extension/mono_chast/catalog/controller/payment/mono_chast.php',
    'extension/opencart/catalog/controller/payment/cod.php',
    'system/library/url.php',
];

foreach ($criticalFiles as $relative) {
    $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (!is_file($absolute)) {
        echo 'file> ' . pay001_json(['path' => $relative, 'exists' => 'no']) . PHP_EOL;
        continue;
    }

    echo 'file> ' . pay001_json([
        'path' => $relative,
        'exists' => 'yes',
        'sha256' => hash_file('sha256', $absolute),
    ]) . PHP_EOL;
}

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
    "SELECT o.`order_id`,o.`order_status_id`,o.`language_id`,o.`telephone`,o.`payment_method`," .
    "o.`total`,o.`currency_code`,o.`date_added`,o.`date_modified`,os.`name` AS `order_status` " .
    "FROM `{$prefix}order` o " .
    "LEFT JOIN `{$prefix}order_status` os ON (os.`order_status_id`=o.`order_status_id` AND os.`language_id`=o.`language_id`) " .
    "WHERE o.`order_id`={$orderId} LIMIT 1"
);

if (!$orderResult instanceof mysqli_result) {
    pay001_line('order_query', 'failed:' . $db->errno);
} elseif ($orderResult->num_rows === 0) {
    pay001_line('order_found', 'no');
    $orderResult->free();
} else {
    $order = $orderResult->fetch_assoc();
    $orderResult->free();
    $payment = pay001_payment_summary($order['payment_method'] ?? []);

    echo 'order> ' . pay001_json([
        'order_id' => (int)$order['order_id'],
        'order_status_id' => (int)$order['order_status_id'],
        'order_status' => (string)($order['order_status'] ?? ''),
        'payment_code' => $payment['code'],
        'payment_name' => $payment['name'],
        'phone_shape' => pay001_phone_shape((string)$order['telephone']),
        'total' => (float)$order['total'],
        'currency_code' => (string)$order['currency_code'],
        'date_added' => (string)$order['date_added'],
        'date_modified' => (string)$order['date_modified'],
    ]) . PHP_EOL;
}

pay001_rows(
    $db,
    "SELECT COUNT(*) AS `product_lines`,COALESCE(SUM(`quantity`),0) AS `product_quantity` " .
    "FROM `{$prefix}order_product` WHERE `order_id`={$orderId}",
    'order_products'
);

pay001_rows(
    $db,
    "SELECT oh.`order_history_id`,oh.`order_status_id`,os.`name` AS `order_status`," .
    "oh.`notify`,oh.`comment`,oh.`date_added` " .
    "FROM `{$prefix}order_history` oh " .
    "LEFT JOIN `{$prefix}order` o ON (o.`order_id`=oh.`order_id`) " .
    "LEFT JOIN `{$prefix}order_status` os ON (os.`order_status_id`=oh.`order_status_id` AND os.`language_id`=o.`language_id`) " .
    "WHERE oh.`order_id`={$orderId} ORDER BY oh.`order_history_id` ASC",
    'order_history',
    static function (array $row): array {
        $row['comment'] = pay001_redact((string)($row['comment'] ?? ''));
        return $row;
    }
);

$transactionTable = $prefix . 'mono_chast_transaction';

if (pay001_table_exists($db, $transactionTable)) {
    $transactionColumns = pay001_columns($db, $transactionTable);
    $wanted = [
        'mono_chast_transaction_id',
        'order_id',
        'store_order_id',
        'parts_count',
        'state',
        'order_sub_state',
        'trace_id',
        'date_added',
        'date_modified',
    ];
    $selected = array_values(array_intersect($wanted, $transactionColumns));

    if ($selected !== []) {
        $select = implode(',', array_map(static fn(string $column): string => "`{$column}`", $selected));
        pay001_rows(
            $db,
            "SELECT {$select} FROM `{$transactionTable}` WHERE `order_id`={$orderId} " .
            "ORDER BY `mono_chast_transaction_id` ASC",
            'mono_transaction'
        );
    }
} else {
    pay001_line('mono_transaction_table', 'missing');
}

$eventTable = $prefix . 'mono_chast_event';

if (pay001_table_exists($db, $eventTable)) {
    $eventColumns = pay001_columns($db, $eventTable);
    $wanted = [
        'mono_chast_event_id',
        'mono_chast_transaction_id',
        'order_id',
        'event_source',
        'state',
        'order_sub_state',
        'trace_id',
        'http_status',
        'date_added',
    ];
    $selected = array_values(array_intersect($wanted, $eventColumns));

    if ($selected !== []) {
        $select = implode(',', array_map(static fn(string $column): string => "`{$column}`", $selected));
        pay001_rows(
            $db,
            "SELECT {$select} FROM `{$eventTable}` WHERE `order_id`={$orderId} " .
            "ORDER BY `mono_chast_event_id` ASC",
            'mono_event'
        );
    }
} else {
    pay001_line('mono_event_table', 'missing');
}

$registryTable = $prefix . 'extension';

if (pay001_table_exists($db, $registryTable)) {
    pay001_rows(
        $db,
        "SELECT `extension_id`,`extension`,`type`,`code`,`status` FROM `{$registryTable}` " .
        "WHERE `type`='payment' AND (`code` LIKE '%cod%' OR `code` LIKE '%cash%' OR `code` LIKE '%mono%') " .
        "ORDER BY `extension`,`code`",
        'payment_registry'
    );
}

$opencartEventTable = $prefix . 'event';

if (pay001_table_exists($db, $opencartEventTable)) {
    $eventColumns = pay001_columns($db, $opencartEventTable);
    $wanted = ['event_id', 'code', 'description', 'trigger', 'action', 'status', 'sort_order'];
    $selected = array_values(array_intersect($wanted, $eventColumns));

    if ($selected !== [] && in_array('trigger', $eventColumns, true) && in_array('action', $eventColumns, true)) {
        $select = implode(',', array_map(static fn(string $column): string => "`{$column}`", $selected));
        pay001_rows(
            $db,
            "SELECT {$select} FROM `{$opencartEventTable}` WHERE " .
            "`code` LIKE '%simple%' OR `trigger` LIKE '%checkout/checkout%' OR " .
            "`trigger` LIKE '%checkout/cart%' OR `action` LIKE '%checkout/checkout%' OR " .
            "`action` LIKE '%checkout/cart%' ORDER BY `sort_order`,`event_id`",
            'checkout_event'
        );
    }
}

$db->close();
pay001_line('done', 'ok');
@unlink(__FILE__);
