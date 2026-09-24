<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function fail_diag(string $message): void {
    fwrite(STDERR, json_encode(['done' => 'error', 'detail' => $message, 'database_writes' => false, 'bank_calls' => false], JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(1);
}

$config = getcwd() . DIRECTORY_SEPARATOR . 'config.php';
if (!is_file($config)) fail_diag('run_from_public_html_required');
require $config;

foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PREFIX'] as $constant) {
    if (!defined($constant)) fail_diag('missing_config_constant:' . $constant);
}
if (!preg_match('/^[A-Za-z0-9_]+$/D', (string)DB_PREFIX)) fail_diag('unsafe_db_prefix');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $db = new mysqli((string)DB_HOSTNAME, (string)DB_USERNAME, (string)DB_PASSWORD, (string)DB_DATABASE, defined('DB_PORT') ? (int)DB_PORT : 3306);
    $db->set_charset('utf8mb4');
    $prefix = (string)DB_PREFIX;
    $orderId = 341;

    $order = [];
    $stmt = $db->prepare("SELECT `order_id`,`order_status_id`,`payment_method`,`date_added`,`date_modified` FROM `{$prefix}order` WHERE `order_id`=? LIMIT 1");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $stmt->bind_result($oid, $statusId, $paymentMethod, $dateAdded, $dateModified);
    if ($stmt->fetch()) {
        $order = [
            'order_id' => (int)$oid,
            'order_status_id' => (int)$statusId,
            'payment_method_has_pumb' => stripos((string)$paymentMethod, 'pumb') !== false,
            'date_added' => (string)$dateAdded,
            'date_modified' => (string)$dateModified,
        ];
    }
    $stmt->close();

    $transactions = [];
    $stmt = $db->prepare("SELECT `pumb_credit_transaction_id`,`cap_id`,`state`,`is_test`,`requested_term`,`agreement_number`,`date_added`,`date_modified` FROM `{$prefix}pumb_credit_transaction` WHERE `order_id`=? ORDER BY `pumb_credit_transaction_id` DESC LIMIT 5");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $stmt->bind_result($txId, $capId, $state, $isTest, $term, $agreement, $txAdded, $txModified);
    while ($stmt->fetch()) {
        $transactions[] = [
            'transaction_id' => (int)$txId,
            'cap_id' => (string)$capId,
            'state' => (string)$state,
            'is_test' => (int)$isTest,
            'requested_term' => $term === null ? null : (int)$term,
            'agreement_number_present' => trim((string)$agreement) !== '',
            'date_added' => (string)$txAdded,
            'date_modified' => (string)$txModified,
        ];
    }
    $stmt->close();

    echo json_encode([
        'diagnostic' => 'PAY-003-order341-state-v1',
        'time_utc' => gmdate('c'),
        'order' => $order,
        'pumb_transactions' => $transactions,
        'bank_application_present' => count(array_filter($transactions, static function (array $row): bool {
            return $row['cap_id'] !== '' && $row['cap_id'] !== '0';
        })) > 0,
        'database_writes' => false,
        'bank_calls' => false,
        'done' => 'ok',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

    @unlink(__FILE__);
} catch (Throwable $exception) {
    fail_diag('query_failed:' . $exception->getMessage());
}
