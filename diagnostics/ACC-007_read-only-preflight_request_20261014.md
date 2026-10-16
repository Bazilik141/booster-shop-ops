# ACC-007 — WP1 read-only preflight request

Date: 2026-10-14 (Europe/Kyiv)
Authority: owner `го` after G0 source intake. WP1 only. No ACC-008/ACC-009 work, implementation patch, commit/push, deploy, Notion or dashboard write.

## Why fresh runtime evidence is needed

The owner drop contains all requested source files. `system/config/default.php:9` configures UTC; `system/framework.php:29` sets PHP's timezone from that configuration before DB startup; `system/library/db/mysqli.php:84` sets its own connection's timezone to PHP's numeric offset. A raw new mysqli connection can therefore report a different initial timezone from the shop's effective connection.

The command below reproduces only these configuration/timezone steps in an isolated CLI process and temporary connection; it does not bootstrap sessions, carts, framework events or application controllers. Its effective-connection field is explicitly emulated, not a claim that a full production web request was traced. Metadata and current bootstrap fingerprints must be reconciled before implementing a conversion of recorded timestamps.

## Boundaries

- Only SELECT/SHOW queries, plus SET SESSION time_zone on this temporary connection. No global setting, persistent configuration or business-row mutation.
- Root credential configuration is read by the owner-held process but no credentials/hostname/database name are returned. Driver exceptions are redacted.
- Status/language definitions, schema, timezone settings, relevant event/theme metadata, file hashes and capped aggregate history counts only. No customer/order identifiers, contacts or raw history comments.
- No custom-field reads or API calls: later packages remain unopened.
- PHP 8.0-compatible; plain mysqli query/fetch_assoc, integer DB_PORT; no get_result/fetch_all.
- Each result has a hard cap; failure returns only a fixed step/error code. No website source/cache files are written.
- Missing or drifted bootstrap fingerprints require a narrow fresh-source follow-up; no current/historical timezone is assumed from the October 9 snapshot alone.

## Owner command

Run the block in cPanel Terminal. On success, return its JSON. On failure, return the bounded error JSON; do not alter the DB or rerun with expanded dumps.

```bash
cd ~/public_html || exit
php -d display_errors=0 -d log_errors=0 <<'PHP'
<?php
// ACC-007 owner-run read-only metadata preflight. No business-row values are returned.
ini_set('display_errors', '0');
ini_set('log_errors', '0');
$step = 'config';
try {
    if (PHP_SAPI !== 'cli' || !is_file('config.php')) throw new RuntimeException('cli_root');
    require 'config.php';
    foreach (['DB_DRIVER','DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PORT','DB_PREFIX'] as $key) {
        if (!defined($key)) throw new RuntimeException('config_incomplete');
    }
    if (DB_DRIVER !== 'mysqli' || !preg_match('/^[A-Za-z0-9_]+$/D', DB_PREFIX)) throw new RuntimeException('driver_prefix');
    $cliTimezone = date_default_timezone_get();
    $_ = [];
    require 'system/config/default.php';
    require 'system/config/catalog.php';
    $appTimezone = $_['date_timezone'] ?? null;
    if (!is_string($appTimezone)) throw new RuntimeException('timezone_missing');
    date_default_timezone_set($appTimezone);
    $kyiv = new DateTimeZone('Europe/Kyiv');
    $offset = date('P');
    $step = 'connection';
    if (!class_exists('mysqli')) throw new RuntimeException('mysqli_missing');
    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
    if ($db->connect_errno || !$db->set_charset('utf8mb4')) throw new RuntimeException('connection_failed');
    $rows = static function (string $sql, int $cap = 80) use ($db): array {
        if (!preg_match('/^(SELECT|SHOW)\b/i', $sql)) throw new RuntimeException('read_only_query');
        $result = $db->query($sql);
        if (!$result || $result->num_rows > $cap) throw new RuntimeException('query_or_cap');
        $data = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;
        $result->free();
        return $data;
    };
    $clockSql = "SELECT @@session.time_zone AS session_timezone, @@global.time_zone AS global_timezone, @@system_time_zone AS system_timezone, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) AS utc_offset_seconds";
    $step = 'timezones';
    $initialClock = $rows($clockSql, 1)[0];
    // Change only this temporary connection, exactly as the verified OpenCart mysqli constructor does.
    if (!preg_match('/^[+-][0-9]{2}:[0-9]{2}$/D', $offset) || !$db->query("SET SESSION time_zone = '" . $offset . "'")) throw new RuntimeException('session_timezone');
    $appClock = $rows($clockSql, 1)[0];
    $out = ['task'=>'ACC-007','mode'=>'read_only_preflight','php_version'=>PHP_VERSION,'db_prefix'=>DB_PREFIX,'adminEvhenii_exists'=>is_dir('adminEvhenii'),'cli_timezone_before_bootstrap'=>$cliTimezone,'timezone_from_framework_config'=>$appTimezone,'openCart_sync_offset'=>$offset,'initial_db_connection'=>$initialClock,'emulated_openCart_connection'=>$appClock,'kyiv_timezone_supported'=>$kyiv->getName()];
    $p = DB_PREFIX;
    $step = 'languages';
    $out['languages'] = $rows("SELECT language_id, code, status FROM `" . $p . "language` ORDER BY language_id", 20);
    $step = 'status_definitions';
    $out['order_status'] = $rows("SELECT order_status_id, language_id, name FROM `" . $p . "order_status` ORDER BY language_id, order_status_id", 64);
    $step = 'configuration_metadata';
    $out['timezone_language_settings'] = $rows("SELECT store_id, `key`, value FROM `" . $p . "setting` WHERE `key` IN ('config_language','config_timezone','date_timezone') ORDER BY store_id, `key`", 30);
    $step = 'history_aggregates';
    $out['history_aggregate'] = $rows("SELECT COUNT(*) AS total, SUM(order_status_id=0) AS status_zero, SUM(order_status_id=2) AS status_two, SUM(order_status_id=15) AS hidden_draft, SUM(date_added IS NULL OR CAST(date_added AS CHAR) IN ('','0000-00-00','0000-00-00 00:00:00')) AS missing_timestamp, SUM(order_id IS NULL) AS missing_order_reference FROM `" . $p . "order_history`", 1)[0];
    $out['history_status_counts'] = $rows("SELECT order_status_id, COUNT(*) AS total FROM `" . $p . "order_history` GROUP BY order_status_id ORDER BY order_status_id", 64);
    $step = 'schema_metadata';
    $out['schema'] = [];
    foreach (['order'=>['order_id','customer_id','store_id','order_status_id','date_added','date_modified'],'order_history'=>['order_history_id','order_id','order_status_id','date_added'],'order_status'=>['order_status_id','language_id','name']] as $table=>$fields) {
        $columns = $rows("SHOW COLUMNS FROM `" . $p . $table . "`", 120);
        $out['schema'][$table] = array_values(array_filter($columns, static function (array $row) use ($fields): bool { return in_array($row['Field'], $fields, true); }));
    }
    $step = 'event_metadata';
    $out['events'] = $rows("SELECT code, `trigger`, action, status FROM `" . $p . "event` WHERE status=1 AND (`trigger` LIKE '%account/order%' OR `trigger` LIKE '%*%') ORDER BY event_id", 80);
    $step = 'theme_metadata';
    $out['themes'] = $rows("SELECT theme_id, store_id, route, status, CHAR_LENGTH(code) AS code_chars, SHA2(code,256) AS code_sha256 FROM `" . $p . "theme` WHERE status=1 AND route IN ('account/order_list','account/order_info','account/order_history') ORDER BY theme_id", 30);
    $step = 'source_fingerprints';
    $out['source_sha256'] = [];
    foreach (['index.php','system/startup.php','catalog/controller/startup/setting.php','system/config/default.php','system/config/catalog.php','system/framework.php','system/library/db/mysqli.php','catalog/controller/account/order.php','catalog/model/account/order.php','catalog/view/template/account/order_list.twig','catalog/view/template/account/order_info.twig','catalog/view/template/account/order_history.twig','extension/ukrainian/catalog/language/uk-ua/account/order.php'] as $file) {
        $out['source_sha256'][$file] = is_file($file) ? hash_file('sha256', $file) : null;
    }
    $db->close();
    $out['done'] = 'ok';
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    // Never expose driver messages, credentials, stack traces or raw data.
    echo json_encode(['task'=>'ACC-007','done'=>'error','step'=>$step,'error'=>'read_only_preflight_failed'], JSON_UNESCAPED_SLASHES) . "\n";
    exit(1);
}
PHP
```

## Current stop

WP1 is active but metadata/timezone evidence is pending. No application patch has been authored. This is an evidence request within WP1, not delivery at G1. Owner decisions in handoff section 2 remain accepted and are not reopened.

## Local validation

PHP syntax passed with local PHP 8.3.30. One synthetic smoke pass covered four cases: successful metadata collection with distinct initial/effective DB offsets, result-cap refusal, driver-query exception, and connection failure. All four passed; deliberately injected credential-like text was absent from stdout/stderr, and unrelated schema defaults were excluded. Evidence: `diagnostics/ACC-007_read-only-preflight_validation_20261014.json`.

These checks used a mysqli double, not MySQL or production PHP 8.0.30. Live metadata and source fingerprint reconciliation remain pending; no runtime outcome is inferred from the local checks.

