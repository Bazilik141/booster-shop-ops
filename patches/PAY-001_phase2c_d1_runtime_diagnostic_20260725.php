<?php
declare(strict_types=1);

/*
 * PAY-001 Phase 2c D1 read-only runtime diagnostic.
 *
 * Reads controller identity, non-secret configuration flags, cURL reachability,
 * recent Mono audit metadata, and relevant PHP/OpenCart log lines.
 * It does not call /api/order/create, change settings/DB/files, or retry an order.
 */

const PAY001_EXPECTED_CONTROLLER_SHA256 = '07ecff45afd965347b6d4f6588a09a05ccff57ba10ca93ef13a1e92f0c7ce677';

function pay001_line(string $key, string|int|float $value): void {
    echo $key . '=' . $value . PHP_EOL;
}

function pay001_redact(string $line): string {
    $line = preg_replace('/\b(?:\+?380|0)\d{9}\b/u', '[phone-redacted]', $line) ?? $line;
    $line = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', '[email-redacted]', $line) ?? $line;
    $line = preg_replace('/\b(signature|store-secret|authorization|token)\b\s*[:=]\s*[^\s,;]+/iu', '$1=[redacted]', $line) ?? $line;
    return $line;
}

function pay001_tail(string $file, int $maxBytes = 2097152): string {
    $size = filesize($file);
    if (!is_int($size)) {
        return '';
    }

    $handle = fopen($file, 'rb');
    if ($handle === false) {
        return '';
    }

    $offset = max(0, $size - $maxBytes);
    if ($offset > 0) {
        fseek($handle, $offset);
        fgets($handle);
    }
    $content = stream_get_contents($handle);
    fclose($handle);

    return is_string($content) ? $content : '';
}

function pay001_log_evidence(string $file): void {
    pay001_line('log_file', $file);
    if (!is_file($file) || !is_readable($file)) {
        pay001_line('log_readable', 'no');
        return;
    }

    pay001_line('log_readable', 'yes');
    $lines = preg_split('/\R/u', pay001_tail($file));
    if (!is_array($lines)) {
        pay001_line('log_matches', 0);
        return;
    }

    $selected = [];
    $pattern = '/mono_chast|monobank|u2-demo|fatal error|uncaught|sqlstate|mysqli|curl|json|typeerror|exception/iu';

    foreach ($lines as $index => $line) {
        if (!preg_match($pattern, $line)) {
            continue;
        }
        for ($cursor = max(0, $index - 5); $cursor <= min(count($lines) - 1, $index + 15); $cursor++) {
            $selected[$cursor] = true;
        }
    }

    ksort($selected);
    pay001_line('log_matches', count($selected));
    foreach (array_keys($selected) as $index) {
        echo 'log> ' . pay001_redact((string)$lines[$index]) . PHP_EOL;
    }
}

function pay001_db_rows(mysqli $db, string $sql, string $label): void {
    $result = $db->query($sql);
    if (!$result instanceof mysqli_result) {
        pay001_line($label . '_query', 'failed:' . $db->errno);
        return;
    }

    pay001_line($label . '_rows', $result->num_rows);
    while ($row = $result->fetch_assoc()) {
        echo $label . '> ' . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
    $result->free();
}

$root = getcwd();
pay001_line('cwd', $root);
pay001_line('time', date(DATE_ATOM));

if (!is_file($root . DIRECTORY_SEPARATOR . 'config.php')) {
    pay001_line('error', 'run_from_opencart_root_config_missing');
    exit(1);
}

require $root . DIRECTORY_SEPARATOR . 'config.php';

$relative = 'extension/mono_chast/catalog/controller/payment/mono_chast.php';
$controller = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
if (!is_file($controller)) {
    pay001_line('controller_missing', $relative);
    exit(1);
}

$controllerHash = hash_file('sha256', $controller);
pay001_line('controller_sha256', $controllerHash);
pay001_line('controller_matches_known_source', hash_equals(PAY001_EXPECTED_CONTROLLER_SHA256, $controllerHash) ? 'yes' : 'no');
pay001_line('php_version', PHP_VERSION);
pay001_line('curl_loaded', extension_loaded('curl') ? 'yes' : 'no');
pay001_line('openssl_loaded', extension_loaded('openssl') ? 'yes' : 'no');

$apiBase = '';

// Load OpenCart's saved extension settings without bootstrapping the storefront.
mysqli_report(MYSQLI_REPORT_OFF);
$db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
if ($db->connect_errno) {
    pay001_line('db_connect', 'failed:' . $db->connect_errno);
} else {
    $db->set_charset('utf8mb4');
    $prefix = DB_PREFIX;

    $settings = [];
    $settingResult = $db->query(
        "SELECT `key`, `value` FROM `{$prefix}setting` " .
        "WHERE `store_id` = 0 AND `code` = 'payment_mono_chast' AND `key` IN (" .
        "'payment_mono_chast_status','payment_mono_chast_api_base','payment_mono_chast_store_id'," .
        "'payment_mono_chast_store_secret','payment_mono_chast_point_id')"
    );

    if ($settingResult instanceof mysqli_result) {
        while ($row = $settingResult->fetch_assoc()) {
            $settings[(string)$row['key']] = (string)$row['value'];
        }
        $settingResult->free();
    }

    $apiBase = trim((string)($settings['payment_mono_chast_api_base'] ?? ''));
    pay001_line('mono_status', (string)($settings['payment_mono_chast_status'] ?? 'missing'));
    pay001_line('api_host', (string)(parse_url($apiBase, PHP_URL_HOST) ?: 'missing'));
    pay001_line('store_id_present', trim((string)($settings['payment_mono_chast_store_id'] ?? '')) !== '' ? 'yes' : 'no');
    pay001_line('store_secret_present', trim((string)($settings['payment_mono_chast_store_secret'] ?? '')) !== '' ? 'yes' : 'no');
    pay001_line('point_id_present', trim((string)($settings['payment_mono_chast_point_id'] ?? '')) !== '' ? 'yes' : 'no');

    pay001_db_rows(
        $db,
        "SELECT `mono_chast_transaction_id`,`order_id`,`store_order_id`,`parts_count`,`state`,`order_sub_state`,`trace_id`,`date_added`,`date_modified` " .
        "FROM `{$prefix}mono_chast_transaction` ORDER BY `mono_chast_transaction_id` DESC LIMIT 10",
        'transaction'
    );
    pay001_db_rows(
        $db,
        "SELECT `mono_chast_event_id`,`mono_chast_transaction_id`,`order_id`,`event_source`,`state`,`order_sub_state`,`trace_id`,`http_status`,`date_added` " .
        "FROM `{$prefix}mono_chast_event` ORDER BY `mono_chast_event_id` DESC LIMIT 20",
        'event'
    );
    $db->close();
}

if ($apiBase !== '' && extension_loaded('curl')) {
    $curl = curl_init(rtrim($apiBase, '/') . '/');
    curl_setopt_array($curl, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 12,
    ]);
    curl_exec($curl);
    pay001_line('reachability_http', (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE));
    pay001_line('reachability_curl_errno', curl_errno($curl));
    pay001_line('reachability_curl_error', pay001_redact(curl_error($curl)));
    pay001_line('reachability_primary_ip', (string)curl_getinfo($curl, CURLINFO_PRIMARY_IP));
    pay001_line('reachability_ssl_verify', (int)curl_getinfo($curl, CURLINFO_SSL_VERIFYRESULT));
    curl_close($curl);
}

$logs = [];
if (defined('DIR_LOGS')) {
    $logs[] = rtrim((string)DIR_LOGS, '/\\') . DIRECTORY_SEPARATOR . 'error.log';
}
$logs[] = $root . DIRECTORY_SEPARATOR . 'error_log';

foreach (array_values(array_unique($logs)) as $log) {
    pay001_log_evidence($log);
}

pay001_line('done', 'ok');
@unlink(__FILE__);
