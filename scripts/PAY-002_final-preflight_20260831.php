<?php
/**
 * PAY-002 read-only preflight, PHP 8.0+. CLI only; no bank calls or writes.
 * Upload a copy to public_html and run from there. Persistent diagnostic,
 * not a patch: does not self-delete. Never reads orders or transaction data.
 * config.php is tokenized, NOT executed. Errors never echo exception messages.
 */
declare(strict_types=1);

function pay002Config(string $source): array {
    $tokens = array_values(array_filter(token_get_all($source), static function ($t): bool {
        return !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }));
    $wanted = ['DB_DRIVER','DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PORT','DB_PREFIX'];
    $decode = static function (string $s): string {
        $body = substr($s, 1, -1);
        if ($s[0] === "'") return preg_replace_callback('/\\\\([\'\\\\])/', static fn(array $m): string => $m[1], $body);
        // Do not guess PHP escape semantics in an unusual generated config.
        if (strpos($body, '\\u{') !== false) throw new RuntimeException('config_unicode_escape_unsupported');
        return preg_replace_callback('/\\\\(x[0-9A-Fa-f]{1,2}|[0-7]{1,3}|[nrtvef\\\\$"])/', static fn(array $m): string => stripcslashes($m[0]), $body);
    };
    $out = [];
    for ($i = 0; $i + 5 < count($tokens); $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING || strtolower($tokens[$i][1]) !== 'define') continue;
        if ($tokens[$i + 1] !== '(' || !is_array($tokens[$i + 2]) || $tokens[$i + 2][0] !== T_CONSTANT_ENCAPSED_STRING) continue;
        $name = $decode($tokens[$i + 2][1]);
        if (!in_array($name, $wanted, true)) continue;
        if (isset($out[$name]) || $tokens[$i + 3] !== ',' || $tokens[$i + 5] !== ')' || !is_array($tokens[$i + 4])) throw new RuntimeException('config_shape');
        $value = $tokens[$i + 4];
        if ($value[0] === T_CONSTANT_ENCAPSED_STRING) $out[$name] = $decode($value[1]);
        elseif ($value[0] === T_LNUMBER) $out[$name] = (string)(int)$value[1];
        else throw new RuntimeException('config_literal_required');
    }
    foreach (['DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PREFIX'] as $key) if (!array_key_exists($key, $out)) throw new RuntimeException('config_missing');
    if (!preg_match('/^[A-Za-z0-9_]+$/D', $out['DB_PREFIX'])) throw new RuntimeException('prefix_invalid');
    return $out;
}

function pay002Endpoint(string $key, string $value): string {
    $known = [
        'oauth_url' => ['test' => 'https://auth.dts.fuib.com/auth/realms/pumb_ext/protocol/openid-connect/token', 'production' => 'https://authsrv.pumb.ua/auth/realms/pumb_ext/protocol/openid-connect/token'],
        'api_base' => ['test' => 'https://api.dts.fuib.com/ext-oic/galadriel/v1', 'production' => 'https://apiext.pumb.ua/ext-oic/galadriel/v1'],
    ];
    if ($value === '') return 'empty';
    foreach ($known[$key] as $environment => $url) if (rtrim(trim($value), '/') === $url) return $environment . '_exact';
    return 'unrecognized_redacted';
}

function pay002SafeSetting(string $key, string $value): mixed {
    if (in_array($key, ['oauth_url','api_base'], true)) return pay002Endpoint($key, $value);
    if ($key === 'terms') {
        $x = json_decode($value, true);
        if (!is_array($x) || !$x || count($x) > 12) return 'invalid_redacted';
        foreach ($x as $v) if (!is_int($v) || !in_array($v, [3,4,5], true)) return 'invalid_redacted';
        return array_values($x);
    }
    if (in_array($key, ['callback_ips','test_callback_ips'], true)) {
        if (trim($value) === '') return 'empty_no_ip_filter';
        $ips = array_map('trim', explode(',', $value));
        $invalid = 0; $cidr = 0;
        foreach ($ips as $ip) { if (strpos($ip, '/') !== false) $cidr++; elseif (!filter_var($ip, FILTER_VALIDATE_IP)) $invalid++; }
        return ['entries' => count($ips), 'cidr_unsupported_by_live_code' => $cidr, 'invalid_entries' => $invalid,
            'observed_bank_sender_allowed' => in_array('194.44.66.21', $ips, true)];
    }
    if ($key === 'point_of_sale_code') return $value === '1700001669IN020147' ? 'bank_match' : 'mismatch_redacted';
    if ($key === 'partner_name') return $value === 'Boostershop Digital SF Internet' ? 'bank_match' : 'mismatch_redacted';
    if (preg_match('/^(status|public|test_mode)$/D', $key)) return in_array($value, ['0','1'], true) ? (int)$value : 'invalid_redacted';
    if ($key === 'min_total' || $key === 'max_total') return preg_match('/^[0-9]{1,9}(\.[0-9]{1,4})?$/D', $value) ? (float)$value : 'invalid_redacted';
    if (preg_match('/^status_(waiting_client|waiting_store|funded|returned|failed)$/D', $key)) return ctype_digit($value) ? (int)$value : 'invalid_redacted';
    return in_array($value, ['present','empty'], true) ? $value : 'redacted';
}

function pay002Main(array $args): int {
    $phase = 'arguments';
    $dbOperation = 'not_started';
    try {
        if (count($args) !== 1) throw new RuntimeException('no_arguments');
        $root = getcwd();
        if (!is_string($root) || !is_file($root . '/config.php') || !is_dir($root . '/catalog')) throw new RuntimeException('wrong_directory');
        $phase = 'config_parse';
        $config = pay002Config((string)file_get_contents($root . '/config.php'));
        if (($config['DB_DRIVER'] ?? 'mysqli') !== 'mysqli') throw new RuntimeException('driver_unsupported');
        $phase = 'db_connect';
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db = mysqli_init();
        $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        $db->real_connect($config['DB_HOSTNAME'], $config['DB_USERNAME'], $config['DB_PASSWORD'], $config['DB_DATABASE'], (int)($config['DB_PORT'] ?? 3306));
        $db->set_charset('utf8mb4');
        $prefix = $config['DB_PREFIX'];
        unset($config);
        $query = static function (string $sql) use ($db, &$dbOperation): array {
            if (!preg_match('/^(SELECT|SHOW)\s/i', $sql)) throw new RuntimeException('readonly_guard');
            $dbOperation = 'query';
            $result = $db->query($sql);
            $dbOperation = 'fetch';
            // PHP 8.0 + libmysqlclient may not provide fetch_all().
            $rows = [];
            try {
                while (($row = $result->fetch_assoc()) !== null) {
                    if (!is_array($row)) throw new RuntimeException('fetch_failed');
                    $rows[] = $row;
                }
            } finally {
                $result->free();
            }
            $dbOperation = 'complete';
            return $rows;
        };
        $plain = ['status','public','test_mode','oauth_url','api_base','terms','min_total','max_total','point_of_sale_code','partner_name','callback_ips','test_callback_ips','status_waiting_client','status_waiting_store','status_funded','status_returned','status_failed'];
        $secret = ['oauth_username','oauth_password','preview_token','callback_user','callback_password','test_callback_user','test_callback_password'];
        $quoted = static fn(array $keys): string => implode(',', array_map(static fn(string $k): string => "'payment_pumb_credit_" . $k . "'", $keys));
        $phase = 'settings';
        $rows = $query("SELECT store_id, `key`, CASE WHEN `key` IN (" . $quoted($secret) . ") THEN CASE WHEN LENGTH(TRIM(`value`)) > 0 THEN 'present' ELSE 'empty' END ELSE `value` END AS safe_value FROM `{$prefix}setting` WHERE `code`='payment_pumb_credit' AND `key` IN (" . $quoted(array_merge($plain, $secret)) . ") ORDER BY store_id, `key` LIMIT 201");
        if (count($rows) > 200) throw new RuntimeException('settings_limit');
        $settings = []; $duplicates = [];
        foreach ($rows as $row) {
            $store = (int)$row['store_id']; $key = substr($row['key'], strlen('payment_pumb_credit_'));
            if (isset($settings[$store]) && array_key_exists($key, $settings[$store])) $duplicates[] = ['store' => $store, 'key' => $key];
            $settings[$store][$key] = pay002SafeSetting($key, (string)$row['safe_value']);
        }
        $missing = array_values(array_diff(array_merge($plain, $secret), array_keys($settings[0] ?? [])));
        $phase = 'schema';
        $columns = $query("SHOW COLUMNS FROM `{$prefix}pumb_credit_transaction`");
        $indexes = $query("SHOW INDEX FROM `{$prefix}pumb_credit_transaction`");
        $groups = [];
        foreach ($indexes as $row) if ((int)$row['Non_unique'] === 0) $groups[$row['Key_name']][(int)$row['Seq_in_index']] = (string)$row['Column_name'];
        $unique = [];
        foreach ($groups as $group) { ksort($group); $unique[] = array_values($group); }
        $phase = 'status_mapping';
        $statusIds = [];
        foreach ($settings as $storeSettings) foreach ($storeSettings as $key => $value) if (strpos($key, 'status_') === 0 && is_int($value) && $value > 0) $statusIds[] = $value;
        $statusRows = $statusIds ? $query("SELECT order_status_id, language_id FROM `{$prefix}order_status` WHERE order_status_id IN (" . implode(',', array_unique($statusIds)) . ") LIMIT 100") : [];
        $phase = 'file_hashes';
        $paths = ['extension/pumb_credit/catalog/controller/payment/pumb_credit.php','extension/pumb_credit/admin/controller/payment/pumb_credit.php','catalog/controller/checkout/payment_method.php','catalog/view/template/checkout/payment_method.twig','catalog/controller/checkout/checkout.php','catalog/controller/checkout/cart.php','catalog/view/template/checkout/cart_list.twig'];
        $hashes = [];
        foreach ($paths as $path) $hashes[$path] = is_file($root . '/' . $path) ? hash_file('sha256', $root . '/' . $path) : 'missing';
        $db->close();
        echo json_encode(['diagnostic' => 'PAY-002-final-preflight-v2', 'time_utc' => gmdate('c'), 'php' => PHP_VERSION,
            'database_writes' => false, 'bank_calls' => false, 'files_modified' => false,
            'settings_by_store' => $settings, 'missing_default_store_keys' => $missing, 'duplicate_setting_rows' => $duplicates,
            'transaction_columns' => array_column($columns, 'Field'), 'unique_indexes_columns' => $unique,
            'mapped_status_ids_found' => $statusRows, 'sha256' => $hashes,
            'readiness' => 'requires_review_not_a_launch_approval', 'done' => 'ok'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        return 0;
    } catch (Throwable $e) {
        // Report only classifications/codes, never SQL, values, or exception text.
        $kind = $e instanceof Error ? 'php_error' : 'exception';
        $code = (int)$e->getCode();
        if ($e instanceof mysqli_sql_exception) {
            $kind = [1054 => 'unknown_column', 1146 => 'missing_table', 1142 => 'read_permission_denied', 1064 => 'sql_syntax', 2006 => 'connection_lost', 2013 => 'connection_lost'][$code] ?? 'database_error';
        } elseif ($e instanceof Error && strpos($e->getMessage(), 'Call to undefined ') === 0) {
            $kind = 'missing_runtime_function';
        }
        echo json_encode(['diagnostic' => 'PAY-002-final-preflight-v2', 'done' => 'error', 'phase' => $phase,
            'db_operation' => $dbOperation, 'error_kind' => $kind, 'error_code' => $code,
            'php' => PHP_VERSION, 'mysqlnd_loaded' => extension_loaded('mysqlnd'),
            'mysqli_fetch_all_available' => function_exists('mysqli_fetch_all'),
            'detail' => 'redacted; report this output to Codex', 'bank_calls' => false, 'database_writes' => false]) . PHP_EOL;
        return 1;
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    ini_set('display_errors', '0');
    if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
    exit(pay002Main($argv));
}
