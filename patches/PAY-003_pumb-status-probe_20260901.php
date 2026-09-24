<?php
/**
 * PAY-003 — one-shot, read-only PUMB TEST status probe.
 *
 * Usage from ~/public_html:
 *   php PAY-003_pumb-status-probe_20260901.php --status=<cap_id>
 *
 * Safety:
 * - TEST contour only (exact dts.fuib.com hosts, test_mode=1, public=0)
 * - OAuth plus one GET only; no POST/PATCH to the credit API
 * - no database writes and no raw bank response output
 * - self-deletes after a completed status request
 */
declare(strict_types=1);

const PAY003_PROBE_ID = 'PAY-003_pumb-status-probe_20260901';

function lineOut(string $key, string|int|bool|null $value): void {
    if (is_bool($value)) {
        $value = $value ? 'yes' : 'no';
    }
    if ($value === null) {
        $value = '';
    }
    echo $key . '=' . $value . PHP_EOL;
}

function stopProbe(string $message, int $code = 1): void {
    lineOut('error', $message);
    exit($code);
}

/** @return array<int,array{value:string,serialized:int}> */
function settingRows(mysqli $db, string $prefix, string $key): array {
    $sql = 'SELECT `value`,`serialized` FROM `' . $prefix . 'setting` WHERE `store_id`=0 AND `code`=? AND `key`=? ORDER BY `setting_id`';
    $statement = $db->prepare($sql);
    if ($statement === false) {
        stopProbe('settings_prepare_failed');
    }
    $code = 'payment_pumb_credit';
    $statement->bind_param('ss', $code, $key);
    if (!$statement->execute()) {
        stopProbe('settings_execute_failed');
    }
    $statement->bind_result($value, $serialized);
    $rows = [];
    while ($statement->fetch()) {
        $rows[] = ['value' => (string)$value, 'serialized' => (int)$serialized];
    }
    $statement->close();
    return $rows;
}

function requiredSetting(mysqli $db, string $prefix, string $key, bool $secret = false): string {
    $rows = settingRows($db, $prefix, $key);
    if (count($rows) !== 1) {
        stopProbe('expected_exactly_one_setting:' . $key);
    }
    $value = $rows[0]['value'];
    if ($value === '') {
        stopProbe(($secret ? 'credential_missing:' : 'setting_empty:') . $key);
    }
    return $value;
}

function exactHttpsHost(string $url, string $expected): bool {
    $parts = parse_url($url);
    return is_array($parts)
        && strtolower((string)($parts['scheme'] ?? '')) === 'https'
        && strtolower((string)($parts['host'] ?? '')) === $expected;
}

/** @return array{http:int,raw:string,body:mixed,error:string} */
function requestProbe(string $method, string $url, array $headers, ?string $body = null): array {
    $curl = curl_init($url);
    if ($curl === false) {
        return ['http' => 0, 'raw' => '', 'body' => null, 'error' => 'curl_init_failed'];
    }
    $options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
    ];
    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($curl, $options);
    $raw = curl_exec($curl);
    $http = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    $raw = is_string($raw) ? $raw : '';
    return [
        'http' => $http,
        'raw' => $raw,
        'body' => json_decode($raw, true),
        'error' => $error,
    ];
}

if (PHP_SAPI !== 'cli') {
    stopProbe('cli_only');
}
if (!function_exists('curl_init')) {
    stopProbe('curl_extension_required');
}
if (count($argv) !== 2 || !preg_match('/^--status=([0-9]{1,32})$/D', (string)$argv[1], $match)) {
    stopProbe('usage:php PAY-003_pumb-status-probe_20260901.php --status=<cap_id>', 2);
}
$capId = $match[1];
$root = getcwd() ?: '.';
if (!is_file($root . '/config.php') || !is_dir($root . '/catalog') || is_dir($root . '/.git')) {
    stopProbe('run_uploaded_copy_from_public_html');
}
require_once $root . '/config.php';
foreach (['DB_PREFIX','DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PORT'] as $constant) {
    if (!defined($constant)) {
        stopProbe('missing_database_constant:' . $constant);
    }
}
if (preg_match('/^[A-Za-z0-9_]+$/D', DB_PREFIX) !== 1) {
    stopProbe('unexpected_database_prefix');
}

mysqli_report(MYSQLI_REPORT_OFF);
$db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
if ($db->connect_errno) {
    stopProbe('database_connection_failed');
}
$db->set_charset('utf8mb4');

$testMode = requiredSetting($db, DB_PREFIX, 'payment_pumb_credit_test_mode');
$public = requiredSetting($db, DB_PREFIX, 'payment_pumb_credit_public');
$oauthUrl = requiredSetting($db, DB_PREFIX, 'payment_pumb_credit_oauth_url');
$apiBase = requiredSetting($db, DB_PREFIX, 'payment_pumb_credit_api_base');
$username = requiredSetting($db, DB_PREFIX, 'payment_pumb_credit_oauth_username', true);
$password = requiredSetting($db, DB_PREFIX, 'payment_pumb_credit_oauth_password', true);

if ($testMode !== '1') {
    stopProbe('test_mode_must_be_1');
}
if ($public !== '0') {
    stopProbe('public_mode_must_be_0');
}
if (!exactHttpsHost($oauthUrl, 'auth.dts.fuib.com') || !exactHttpsHost($apiBase, 'api.dts.fuib.com')) {
    stopProbe('exact_test_hosts_required');
}

lineOut('diagnostic', PAY003_PROBE_ID);
lineOut('time_utc', gmdate('c'));
lineOut('mode', 'status_get_only');
lineOut('test_contour', 'verified');
lineOut('database_writes', 'no');
lineOut('credit_api_mutations', 'no');

$oauthBody = http_build_query([
    'client_id' => 'EXT_OIC',
    'username' => $username,
    'password' => $password,
    'grant_type' => 'password',
]);
$oauth = requestProbe('POST', $oauthUrl, ['Content-Type: application/x-www-form-urlencoded'], $oauthBody);
lineOut('oauth_http', $oauth['http']);
$token = is_array($oauth['body']) ? (string)($oauth['body']['access_token'] ?? '') : '';
if ($oauth['http'] !== 200 || $token === '') {
    lineOut('oauth_token', 'fail');
    lineOut('oauth_body_bytes', strlen($oauth['raw']));
    lineOut('oauth_body_sha256', hash('sha256', $oauth['raw']));
    if ($oauth['error'] !== '') {
        lineOut('oauth_transport_error', 'present');
    }
    $db->close();
    stopProbe('oauth_failed');
}
lineOut('oauth_token', 'ok');

$flowId = bin2hex(random_bytes(16));
$status = requestProbe(
    'GET',
    rtrim($apiBase, '/') . '/sf-credits/' . rawurlencode($capId),
    [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
        'Content-Type: application/json',
        'X-Flow-Id: ' . $flowId,
    ]
);
$db->close();

lineOut('status_http', $status['http']);
lineOut('status_flow_id', $flowId);
lineOut('status_body_bytes', strlen($status['raw']));
lineOut('status_body_sha256', hash('sha256', $status['raw']));
if ($status['error'] !== '') {
    lineOut('status_transport_error', 'present');
}

$body = $status['body'];
if (is_array($body)) {
    $keys = array_map('strval', array_keys($body));
    sort($keys, SORT_STRING);
    lineOut('status_body_type', 'json_object');
    lineOut('status_body_keys', implode(',', $keys));
} elseif ($status['raw'] === '') {
    lineOut('status_body_type', 'empty');
    lineOut('status_body_keys', '');
} else {
    lineOut('status_body_type', 'non_json');
    lineOut('status_body_keys', '');
}

$allowedStates = [
    'IN_PROGRESS','WAITING_CLIENT','WAITING_STORE_CONFIRM','FUNDED',
    'CANCELED_BY_CLIENT','CANCELED_BY_STORE','REJECTED','NO_LIMIT','OVER_LIMIT',
    'CLIENT_NOT_FOUND','FAIL','PUSH_TIMEOUT','CONFIRM_TIME_EXPIRED','FAIL_OTP',
    'REFUND_FINISHED','IDENTIFICATION_FAILED',
];
$state = is_array($body) && is_string($body['state'] ?? null) ? trim($body['state']) : '';
if ($state === '') {
    lineOut('status_state', 'missing');
} elseif (in_array($state, $allowedStates, true)) {
    lineOut('status_state', $state);
} else {
    lineOut('status_state', 'unrecognized');
    lineOut('status_state_sha256', hash('sha256', $state));
}

lineOut('done', 'ok');
lineOut('self_delete', @unlink(__FILE__) ? 'ok' : 'failed');
