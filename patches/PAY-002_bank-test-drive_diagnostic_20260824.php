<?php
/**
 * PAY-002 — PUMB test-contour bank-drive diagnostic.
 *
 * Run only from the production host terminal:
 *   php PAY-002_bank-test-drive_diagnostic_20260824.php --order=<id> --term=4 --units=hryvnia
 *
 * Read one test-contour application status without creating or persisting:
 *   php PAY-002_bank-test-drive_diagnostic_20260824.php --status=<cap_id>
 *
 * Add --live only after reviewing the dry-run payload. This diagnostic never
 * changes files, settings, orders, order history, or extension code. A live
 * HTTP 201 can insert one PUMB TEST transaction row for the returned cap_id.
 * Keep this file while testing; delete it manually from ~/public_html when done.
 */
declare(strict_types=1);

const PAY002_DIAGNOSTIC_ID = 'PAY-002_bank-test-drive_diagnostic_20260824';
const PAY002_DEFAULT_PHONE = '+380695060051';
const PAY002_DEFAULT_STORE_USER_LOGIN = 'boostershop-oc';

function out(string $key, string|int|float|bool|null $value): void {
    if (is_bool($value)) $value = $value ? 'yes' : 'no';
    if ($value === null) $value = '';
    echo $key . '=' . $value . PHP_EOL;
}
function fail(string $message, int $code = 1): void { out('error', $message); exit($code); }
function settingRows(mysqli $db, string $prefix, string $key): array {
    $sql = 'SELECT `value`,`serialized` FROM `' . $prefix . 'setting` WHERE `store_id`=0 AND `code`=? AND `key`=? ORDER BY `setting_id`';
    $stmt = $db->prepare($sql);
    if ($stmt === false) fail('settings_preflight_prepare_failed');
    $code = 'payment_pumb_credit';
    $stmt->bind_param('ss', $code, $key);
    if (!$stmt->execute()) fail('settings_preflight_execute_failed');
    $stmt->bind_result($value, $serialized);
    $rows = [];
    while ($stmt->fetch()) $rows[] = ['value' => (string)$value, 'serialized' => (int)$serialized];
    $stmt->close();
    return $rows;
}
function requiredSetting(mysqli $db, string $prefix, string $key): string {
    $rows = settingRows($db, $prefix, $key);
    if (count($rows) !== 1) fail('expected_exactly_one_setting:' . $key);
    return $rows[0]['value'];
}
function requiredCredential(mysqli $db, string $prefix, string $key): string {
    $rows = settingRows($db, $prefix, $key);
    if (count($rows) !== 1 || $rows[0]['value'] === '') {
        out('missing_setting', $key);
        fail('oauth_credentials_incomplete');
    }
    return $rows[0]['value'];
}
function tableExists(mysqli $db, string $table): bool {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    if ($stmt === false) fail('table_preflight_prepare_failed');
    $stmt->bind_param('s', $table);
    if (!$stmt->execute()) fail('table_preflight_execute_failed');
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int)$count === 1;
}
function exactTestHost(string $url, string $expectedHost): bool {
    $parts = parse_url($url);
    return is_array($parts) && strtolower((string)($parts['scheme'] ?? '')) === 'https' && strtolower((string)($parts['host'] ?? '')) === $expectedHost;
}
function parseArguments(array $argv): array {
    $parsed = ['live' => false, 'status_mode' => false];
    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--live') {
            if ($parsed['live']) fail('duplicate_argument:--live', 2);
            $parsed['live'] = true;
            continue;
        }
        if (!str_starts_with($argument, '--') || !str_contains($argument, '=')) fail('invalid_argument:' . $argument, 2);
        [$key, $value] = explode('=', substr($argument, 2), 2);
        if (!in_array($key, ['order', 'term', 'units', 'phone', 'store-user', 'status'], true) || array_key_exists($key, $parsed)) fail('invalid_or_duplicate_argument:--' . $key, 2);
        $parsed[$key] = $value;
    }
    if (array_key_exists('status', $parsed)) {
        if ($parsed['live']) fail('status_mode_refuses_--live', 2);
        if (!preg_match('/^[0-9]{1,32}$/', (string)$parsed['status'])) fail('invalid_status_cap_id', 2);
        $parsed['status_mode'] = true;
        return $parsed;
    }
    foreach (['order', 'term', 'units'] as $required) if (!array_key_exists($required, $parsed)) fail('missing_argument:--' . $required, 2);
    if (!preg_match('/^[1-9][0-9]*$/', (string)$parsed['order'])) fail('invalid_order', 2);
    if (!in_array((string)$parsed['term'], ['3', '4', '5'], true)) fail('invalid_term', 2);
    if (!in_array((string)$parsed['units'], ['hryvnia', 'kopiyka'], true)) fail('invalid_units', 2);
    $parsed['phone_explicit'] = array_key_exists('phone', $parsed);
    $parsed['phone'] = $parsed['phone'] ?? PAY002_DEFAULT_PHONE;
    if (!preg_match('/^\+380[0-9]{9}$/', (string)$parsed['phone'])) fail('invalid_phone', 2);
    $parsed['store_user_login'] = $parsed['store-user'] ?? PAY002_DEFAULT_STORE_USER_LOGIN;
    if (!preg_match('/^[A-Za-z0-9._-]{1,64}$/', (string)$parsed['store_user_login'])) fail('invalid_store_user', 2);
    $parsed['order'] = (int)$parsed['order'];
    $parsed['term'] = (int)$parsed['term'];
    return $parsed;
}
/** @return array{http:int,raw:string,body:array<string,mixed>|null,error:string} */
function httpRequest(string $method, string $url, array $headers = [], ?string $body = null): array {
    $curl = curl_init($url);
    if ($curl === false) return ['http' => 0, 'raw' => '', 'body' => null, 'error' => 'curl_init_failed'];
    $options = [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 25];
    if ($body !== null) $options[CURLOPT_POSTFIELDS] = $body;
    curl_setopt_array($curl, $options);
    $raw = curl_exec($curl);
    $http = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    $raw = is_string($raw) ? $raw : '';
    $decoded = json_decode($raw, true);
    return ['http' => $http, 'raw' => $raw, 'body' => is_array($decoded) ? $decoded : null, 'error' => $error];
}
function money(float $value, string $units): float|int { return $units === 'kopiyka' ? (int)round($value * 100) : round($value, 2); }
function numericText(float|int $value, string $units): string { return $units === 'kopiyka' ? (string)(int)$value : number_format((float)$value, 2, '.', ''); }
function testStoreOrderId(int $orderId, string $capId): string {
    // The bank permits duplicate OC order ids in test, but the local table has
    // a unique (store_order_id,is_test) key. Keep every diagnostic row distinct.
    return 'PUMB-TEST-' . $orderId . '-' . substr(hash('sha256', $capId), 0, 32);
}

register_shutdown_function(static function (): void { out('closing_reminder', 'delete this diagnostic from ~/public_html after bank testing is finished'); });

$startedAtLocal = date('c');
$startedAtUtc = gmdate('c');
if (PHP_SAPI !== 'cli') fail('cli_only_refused');
if (!function_exists('curl_init')) fail('curl_extension_required');
$arguments = parseArguments($argv);
$root = getcwd() ?: '.';
$config = $root . '/config.php';
if (!is_file($config)) fail('run_from_public_html_config_php_missing');
require_once $config;
foreach (['DB_PREFIX', 'DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PORT'] as $constant) if (!defined($constant)) fail('missing_database_constant:' . $constant);
if (preg_match('/^[A-Za-z0-9_]+$/', DB_PREFIX) !== 1) fail('unexpected_database_prefix');

mysqli_report(MYSQLI_REPORT_OFF);
$db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
if ($db->connect_errno) fail('database_connection_failed');
$db->set_charset('utf8mb4');
$prefix = DB_PREFIX;
$transactionTable = $prefix . 'pumb_credit_transaction';
if (!tableExists($db, $prefix . 'setting')) fail('settings_table_missing');
if (!$arguments['status_mode']) {
    if (!tableExists($db, $prefix . 'order')) fail('order_table_missing');
    if (!tableExists($db, $prefix . 'order_product')) fail('order_product_table_missing');
    if (!tableExists($db, $transactionTable)) fail('pumb_credit_transaction_table_missing');
}

$testMode = requiredSetting($db, $prefix, 'payment_pumb_credit_test_mode');
if ($testMode !== '1') { out('test_mode_refused', 'yes'); fail('payment_pumb_credit_test_mode_must_be_1'); }
$oauthUrl = requiredSetting($db, $prefix, 'payment_pumb_credit_oauth_url');
$apiBase = requiredSetting($db, $prefix, 'payment_pumb_credit_api_base');
if (!exactTestHost($oauthUrl, 'auth.dts.fuib.com') || !exactTestHost($apiBase, 'api.dts.fuib.com')) { out('production_contour_refused', 'yes'); fail('configured_hosts_are_not_the_explicit_pumb_test_hosts'); }
$statusRows = settingRows($db, $prefix, 'payment_pumb_credit_status');
if (count($statusRows) > 1 || (count($statusRows) === 1 && $statusRows[0]['value'] !== '0')) { out('method_live_refused', 'yes'); fail('payment_pumb_credit_status_must_be_absent_or_0'); }
$username = requiredCredential($db, $prefix, 'payment_pumb_credit_oauth_username');
$password = requiredCredential($db, $prefix, 'payment_pumb_credit_oauth_password');

if ($arguments['status_mode']) {
    out('scope', PAY002_DIAGNOSTIC_ID);
    out('mode', 'status');
    out('started_at_local', $startedAtLocal);
    out('started_at_utc', $startedAtUtc);
    $oauthForm = http_build_query(['client_id' => 'EXT_OIC', 'username' => $username, 'password' => $password, 'grant_type' => 'password']);
    $oauth = httpRequest('POST', $oauthUrl, ['Content-Type: application/x-www-form-urlencoded'], $oauthForm);
    out('oauth_http', $oauth['http']);
    $token = is_array($oauth['body']) ? (string)($oauth['body']['access_token'] ?? '') : '';
    if ($oauth['http'] !== 200 || $token === '') { out('oauth_token', 'fail'); out('oauth_response_body', $oauth['raw']); if ($oauth['error'] !== '') out('oauth_transport_error', $oauth['error']); $db->close(); exit(1); }
    out('oauth_token', 'ok');
    out('oauth_expires_in', (int)($oauth['body']['expires_in'] ?? 0));
    $statusFlowId = bin2hex(random_bytes(16));
    $status = httpRequest('GET', rtrim($apiBase, '/') . '/sf-credits/' . $arguments['status'], ['Authorization: Bearer ' . $token, 'Accept: application/json', 'Content-Type: application/json', 'X-Flow-Id: ' . $statusFlowId]);
    out('status_http', $status['http']);
    out('status_flow_id', $statusFlowId);
    out('status_response_body', $status['raw']);
    $state = is_array($status['body']) && is_string($status['body']['state'] ?? null) ? $status['body']['state'] : '';
    out('status_state', $state);
    $db->close();
    out('done', 'ok');
    exit(0);
}

$orderStmt = $db->prepare('SELECT `order_id` FROM `' . $prefix . 'order` WHERE `order_id`=? LIMIT 1');
if ($orderStmt === false) fail('order_preflight_prepare_failed');
$orderStmt->bind_param('i', $arguments['order']);
if (!$orderStmt->execute()) fail('order_preflight_execute_failed');
$orderStmt->bind_result($foundOrderId);
$orderExists = $orderStmt->fetch();
$orderStmt->close();
if (!$orderExists) fail('order_not_found');

$productsStmt = $db->prepare('SELECT `name`,`quantity`,`price` FROM `' . $prefix . 'order_product` WHERE `order_id`=? ORDER BY `order_product_id`');
if ($productsStmt === false) fail('product_preflight_prepare_failed');
$productsStmt->bind_param('i', $arguments['order']);
if (!$productsStmt->execute()) fail('product_preflight_execute_failed');
$productsStmt->bind_result($name, $quantity, $price);
$goods = [];
$sum = 0.0;
while ($productsStmt->fetch()) {
    $amount = money((float)$price, $arguments['units']);
    $count = (int)$quantity;
    if ($count < 1) { $productsStmt->close(); fail('invalid_order_product_quantity'); }
    $goods[] = ['name' => (string)$name, 'count' => $count, 'amount' => $amount];
    $sum += (float)$amount * $count;
}
$productsStmt->close();
if (!$goods) fail('order_has_no_products');
$total = $arguments['units'] === 'kopiyka' ? (int)round($sum) : round($sum, 2);
if (abs($sum - (float)$total) > ($arguments['units'] === 'kopiyka' ? 0.0 : 0.00001)) fail('local_amount_reconciliation_failed');

$storeOrderId = 'OC-' . $arguments['order'];
$payload = [
    'store_order_id' => $storeOrderId,
    'point_of_sale_code' => requiredSetting($db, $prefix, 'payment_pumb_credit_point_of_sale_code'),
    'partner_name' => requiredSetting($db, $prefix, 'payment_pumb_credit_partner_name'),
    'channel_type' => 'INTERNET',
    'store_user_login' => $arguments['store_user_login'],
    'flow' => ['type' => 'DIGITAL_SF'],
    'customer' => ['phone' => $arguments['phone']],
    'invoices' => [['date' => date('Y-m-d'), 'invoice_number' => $storeOrderId, 'goods' => $goods, 'total_amount' => $total]],
    'credit_request' => ['term' => $arguments['term'], 'amount' => $total],
];
$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
if (!is_string($payloadJson)) fail('payload_json_encoding_failed');
out('scope', PAY002_DIAGNOSTIC_ID);
out('mode', $arguments['live'] ? 'live' : 'dry_run');
out('started_at_local', $startedAtLocal);
out('started_at_utc', $startedAtUtc);
if ($arguments['live'] && !$arguments['phone_explicit']) out('live_phone_warning', 'default test phone from the 2026-08-12 letter is in use; confirm it with the bank before relying on this run');
out('units', $arguments['units']);
out('store_user_login', $arguments['store_user_login']);
out('amount_goods_sum', numericText($sum, $arguments['units']));
out('amount_invoice_total', numericText($total, $arguments['units']));
out('amount_credit_request', numericText($total, $arguments['units']));
out('payload_json', $payloadJson);

$oauthForm = http_build_query(['client_id' => 'EXT_OIC', 'username' => $username, 'password' => $password, 'grant_type' => 'password']);
$oauth = httpRequest('POST', $oauthUrl, ['Content-Type: application/x-www-form-urlencoded'], $oauthForm);
out('oauth_http', $oauth['http']);
$token = is_array($oauth['body']) ? (string)($oauth['body']['access_token'] ?? '') : '';
if ($oauth['http'] !== 200 || $token === '') { out('oauth_token', 'fail'); out('oauth_response_body', $oauth['raw']); if ($oauth['error'] !== '') out('oauth_transport_error', $oauth['error']); $db->close(); exit(1); }
out('oauth_token', 'ok');
out('oauth_expires_in', (int)($oauth['body']['expires_in'] ?? 0));

$apiBase = rtrim($apiBase, '/');
foreach ([1, 13] as $fixtureId) {
    $fixtureFlowId = bin2hex(random_bytes(16));
    $fixture = httpRequest('GET', $apiBase . '/sf-credits/' . $fixtureId, ['Authorization: Bearer ' . $token, 'Accept: application/json', 'Content-Type: application/json', 'X-Flow-Id: ' . $fixtureFlowId]);
    out('fixture_' . $fixtureId . '_http', $fixture['http']);
    out('fixture_' . $fixtureId . '_flow_id', $fixtureFlowId);
    out('fixture_' . $fixtureId . '_response_body', $fixture['raw']);
    $state = is_array($fixture['body']) && is_string($fixture['body']['state'] ?? null) ? $fixture['body']['state'] : '';
    out('fixture_' . $fixtureId . '_state', $state);
}

$existingStmt = $db->prepare('SELECT COUNT(*) FROM `' . $transactionTable . '` WHERE `order_id`=? AND `is_test`=1');
if ($existingStmt === false) fail('transaction_preflight_prepare_failed');
$existingStmt->bind_param('i', $arguments['order']);
if (!$existingStmt->execute()) fail('transaction_preflight_execute_failed');
$existingStmt->bind_result($existingCount);
$existingStmt->fetch();
$existingStmt->close();
out('existing_test_transactions_for_order', (int)$existingCount);
if ((int)$existingCount > 0) out('live_create_warning', 'bank permits another application for this order; this diagnostic will create one only when --live is present');
if (!$arguments['live']) { out('dry_run', 'complete_no_create_post_no_transaction_write'); $db->close(); exit(0); }

$createFlowId = bin2hex(random_bytes(16));
$create = httpRequest('POST', $apiBase . '/sf-credits', ['Authorization: Bearer ' . $token, 'Accept: application/json', 'Content-Type: application/json', 'X-Flow-Id: ' . $createFlowId], $payloadJson);
out('create_http', $create['http']);
out('create_flow_id', $createFlowId);
out('create_response_body', $create['raw']);
if ($create['http'] !== 201) { out('persisted_transaction', 'no'); $db->close(); exit(0); }
$capId = is_array($create['body']) ? (string)($create['body']['id'] ?? $create['body']['cap_id'] ?? '') : '';
$state = is_array($create['body']) && is_string($create['body']['state'] ?? null) ? $create['body']['state'] : '';
if ($capId === '') { out('persisted_transaction', 'no'); fail('create_201_without_cap_id'); }
$diagnosticPayload = json_encode(['diagnostic' => ['id' => PAY002_DIAGNOSTIC_ID, 'units' => $arguments['units'], 'created_at' => gmdate('c')], 'create' => ['request' => $payload, 'response' => $create['body']]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
if (!is_string($diagnosticPayload)) fail('transaction_payload_encoding_failed');
$testStoreOrderId = testStoreOrderId($arguments['order'], $capId);
$insert = $db->prepare('INSERT INTO `' . $transactionTable . '` (`order_id`,`store_order_id`,`cap_id`,`state`,`is_test`,`payload`,`date_added`,`date_modified`) VALUES (?,?,?,?,1,?,NOW(),NOW())');
if ($insert === false) fail('transaction_insert_prepare_failed');
$insert->bind_param('issss', $arguments['order'], $testStoreOrderId, $capId, $state, $diagnosticPayload);
if (!$insert->execute()) { $insert->close(); fail('transaction_insert_failed:' . $db->error); }
$insert->close();
$deleteSql = 'DELETE FROM `' . $transactionTable . '` WHERE `cap_id`=\'' . $db->real_escape_string($capId) . '\' AND `is_test`=1;';
out('cap_id', $capId);
out('transaction_store_order_id', $testStoreOrderId);
out('persisted_transaction', 'yes');
out('rollback_delete_sql', $deleteSql);
$db->close();
out('done', 'ok');
