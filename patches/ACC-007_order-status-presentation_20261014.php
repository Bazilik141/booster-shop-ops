<?php
/**
 * ACC-007 WP1: customer-only order status presentation, source-only package.
 * Authority: owner go after G0; handoff_ACC-007-ACC-008-ACC-009_account-data-foundation_20261013.md.
 * PHP 8.0 compatible; no DB migration, business-data write, status lifecycle or mail change.
 * Root causes: raw administrative status/comment output, lost/missing history rows,
 * creation/date-only output and unbounded history despite apparent pagination.
 * Read-only preflight verifies schema, status registry, language, timezone, theme/event metadata.
 * Verified shop writers use Kyiv local DATETIME; preserve wall time, never convert it as UTC.
 * Current time is unknown unless the latest appended history attests the current status.
 * Existing CSS/tokens, GA4 hook, auth, checkout, payment, NP, mail and wishlist are untouched.
 * Usage: php ACC-007_order-status-presentation_20261014.php [--dry-run]
 * Rollback: re-upload runner; --rollback=_patch_backups/<exact directory printed on apply>.
 * Restore source only; all order/history data stays unchanged. Owner clears template cache.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

function acc007_log(string $key, string $value): void { echo $key . '=' . $value . PHP_EOL; }
class Acc007Failure extends RuntimeException {}
function acc007_fail(string $code): void { throw new Acc007Failure($code); }
function acc007_norm(string $bytes, string $path): string {
    return str_replace("\r\n", "\n", $bytes);
}
function acc007_path(string $root, string $relative, bool $mustExist = true): string {
    if (!preg_match('~^(?:index\.php|(?:catalog/|system/|extension/[A-Za-z0-9_]+/)[A-Za-z0-9_./-]+)$~D', $relative) || strpos($relative, '..') !== false) acc007_fail('unsafe_target');
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $parent = realpath(dirname($path));
    if (!$parent || strpos($parent . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR) !== 0 || is_link($path)) acc007_fail('unsafe_target_parent');
    // Actual reads below are the readability gate; Windows sandbox ACL stat probes can disagree.
    if ($mustExist && !is_file($path)) acc007_fail('target_missing:' . $relative);
    return $path;
}
function acc007_read(string $path): string {
    $bytes = file_get_contents($path);
    if ($bytes === false) acc007_fail('file_read_failed');
    return $bytes;
}
function acc007_atomic(string $path, string $bytes): void {
    // Exclusive creation in the target directory; tempnam can silently fall back elsewhere.
    $temporary = dirname($path) . DIRECTORY_SEPARATOR . '.a7-' . bin2hex(random_bytes(4));
    $handle = @fopen($temporary,'xb');
    if ($handle === false) acc007_fail('temporary_write_failed');
    try {
        if (!flock($handle,LOCK_EX) || fwrite($handle,$bytes) !== strlen($bytes) || !fflush($handle)) acc007_fail('file_write_failed');
        fclose($handle); $handle = null;
        if (is_file($path)) @chmod($temporary, fileperms($path) & 0777);
        else @chmod($temporary, 0644);
        if (!rename($temporary, $path)) acc007_fail('atomic_replace_failed');
    } finally {
        if (is_resource($handle)) fclose($handle);
        if (is_file($temporary)) @unlink($temporary);
    }
    if (!hash_equals(hash('sha256', $bytes), hash_file('sha256', $path))) acc007_fail('written_hash_mismatch');
}
function acc007_lint(string $path): void {
    $output = []; $status = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $status);
    if ($status !== 0) acc007_fail('php_lint_failed:' . basename($path));
}
function acc007_edit(string $source, array $edits): string {
    $sourceEol = strpos($source,"\r\n") !== false ? "\r\n" : "\n";
    foreach ($edits as $edit) {
        $pattern = '~' . str_replace("\n", '\\r?\\n', preg_quote($edit['before'], '~')) . '~';
        if (preg_match_all($pattern, $source, $matches) !== $edit['count']) acc007_fail('anchor_count_mismatch');
        $source = preg_replace_callback($pattern, static function (array $match) use ($edit,$sourceEol): string {
            return str_replace("\n", strpos($match[0], "\r\n") !== false ? "\r\n" : $sourceEol, $edit['after']);
        }, $source);
        if (!is_string($source)) acc007_fail('anchor_replacement_failed');
    }
    return $source;
}
function acc007_rows($db, string $sql): array {
    $result = $db->query($sql);
    if (!$result) acc007_fail('db_preflight_query_failed');
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $result->free();
    return $rows;
}
function acc007_db(string $root, array $payload): void {
    if (!is_file($root . '/config.php') || is_link($root . '/config.php')) acc007_fail('config_missing_or_linked');
    require $root . '/config.php';
    foreach (['DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PREFIX','DB_PORT'] as $constant) {
        if (!defined($constant)) acc007_fail('db_config_incomplete');
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/D', DB_PREFIX)) acc007_fail('db_prefix_shape');
    if (!class_exists('mysqli')) acc007_fail('mysqli_missing');
    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
    if ($db->connect_errno) acc007_fail('db_connection_failed');
    if (!$db->set_charset('utf8mb4')) acc007_fail('db_charset_failed');
    try {
        $prefix = DB_PREFIX;
        $required = [
            'order' => ['order_id'=>'int','customer_id'=>'int','store_id'=>'int','order_status_id'=>'int','date_added'=>'datetime','date_modified'=>'datetime'],
            'order_history' => ['order_history_id'=>'int','order_id'=>'int','order_status_id'=>'int','date_added'=>'datetime'],
            'order_status' => ['order_status_id'=>'int','language_id'=>'int','name'=>'varchar(32)']
        ];
        foreach ($required as $table=>$fields) {
            $map = [];
            foreach (acc007_rows($db,'SHOW COLUMNS FROM `' . $prefix . $table . '`') as $row) $map[$row['Field']] = strtolower($row['Type']);
            foreach ($fields as $field=>$type) {
                if (!isset($map[$field]) || ($type === 'int' ? !preg_match('/^int(?:\(\d+\))?$/D',$map[$field]) : $map[$field] !== $type)) acc007_fail('schema_mismatch:' . $table . '/' . $field);
            }
        }
        foreach (['order'=>['order_id'],'order_history'=>['order_history_id'],'order_status'=>['order_status_id','language_id']] as $table=>$keys) {
            $indexes = acc007_rows($db,'SHOW INDEX FROM `' . $prefix . $table . '` WHERE Key_name = \'PRIMARY\'');
            usort($indexes,static function(array $a,array $b):int{return (int)$a['Seq_in_index'] <=> (int)$b['Seq_in_index'];});
            if (array_column($indexes,'Column_name') !== $keys) acc007_fail('primary_key_mismatch:' . $table);
        }
        $languages = acc007_rows($db,'SELECT `language_id`,`code`,`status` FROM `' . $prefix . 'language` WHERE `status` = 1');
        if (count($languages) !== 1 || (string)$languages[0]['language_id'] !== '4' || $languages[0]['code'] !== 'uk-ua') acc007_fail('language_inventory_mismatch');
        $statuses = acc007_rows($db,'SELECT `order_status_id`,`language_id`,`name` FROM `' . $prefix . 'order_status` ORDER BY `language_id`,`order_status_id`');
        $registry = array_map(static function(array $row):array{return ['order_status_id'=>(string)$row['order_status_id'],'language_id'=>(string)$row['language_id'],'name'=>$row['name']];},$statuses);
        if ($registry !== $payload['status_registry']) acc007_fail('status_registry_requires_review');
        $timezone = acc007_rows($db,'SELECT `value` FROM `' . $prefix . 'setting` WHERE `store_id` = 0 AND `key` = \'config_timezone\'');
        if (count($timezone) !== 1 || $timezone[0]['value'] !== 'Europe/Kyiv') acc007_fail('shop_timezone_requires_review');
        // Verified startup/setting.php applies this setting to BOTH PHP and the DB session.
        // No SET is needed here: the runner only reads metadata and never writes a date.
        if ((new DateTimeZone('Europe/Kyiv'))->getName() !== 'Europe/Kyiv') acc007_fail('kyiv_timezone_unavailable');
        $routes = ['account/order_list','account/order_info','account/order_history'];
        $quoted = array_map(static function(string $v)use($db):string{return "'" . $db->real_escape_string($v) . "'";},$routes);
        if (acc007_rows($db,'SELECT `route` FROM `' . $prefix . 'theme` WHERE `route` IN (' . implode(',',$quoted) . ') AND `status` = 1 LIMIT 1')) acc007_fail('active_theme_override_requires_review');
        $events = acc007_rows($db,'SELECT `code`,`trigger`,`action` FROM `' . $prefix . 'event` WHERE `status` = 1 AND (`trigger` LIKE \'%account/order%\' OR `trigger` LIKE \'%*%\')');
        $allowed = array_map(static function(array $row):string{return implode("\n",[$row['code'],$row['trigger'],$row['action']]);},$payload['allowed_events']);
        foreach ($events as $row) if (!in_array(implode("\n",[$row['code'],$row['trigger'],$row['action']]),$allowed,true)) acc007_fail('unreviewed_active_event');
        acc007_log('db_preflight','read_only_ok');
        acc007_log('time_basis','verified_shop_Kyiv_wall_time');
        acc007_log('db_mutations','none');
    } finally { $db->close(); }
}

function acc007_rollback(string $root, string $argument, array $payload): void {
    if (!preg_match('~^_patch_backups/ACC-007_order-status-presentation_20261014-\d{8}T\d{6}Z-[a-f0-9]{8}$~D', $argument)) acc007_fail('rollback_path_shape');
    $backup = realpath($root . '/' . $argument);
    $backupRoot = realpath($root . '/_patch_backups');
    if (!$backup || !$backupRoot || dirname($backup) !== $backupRoot || is_link($root . '/' . $argument)) acc007_fail('rollback_path_outside_backup_root');
    $manifest = json_decode(acc007_read($backup . '/receipt.json'), true);
    if (!is_array($manifest) || ($manifest['task'] ?? '') !== 'ACC-007' || count($manifest['files'] ?? []) !== count($payload['entries'])) acc007_fail('rollback_receipt_shape');
    $restore = [];
    foreach ($payload['entries'] as $entry) {
        $relative = $entry['path']; $meta = $manifest['files'][$relative] ?? null;
        if (!is_array($meta) || ($meta['new'] ?? null) !== $entry['new']) acc007_fail('rollback_receipt_entry');
        $target = acc007_path($root, $relative, false);
        $current = is_file($target) ? acc007_read($target) : null;
        $original = null;
        if (!$entry['new']) {
            $saved = $backup . '/original/' . $relative;
            if (is_link($saved) || !is_file($saved)) acc007_fail('rollback_original_missing');
            $original = acc007_read($saved);
            if (!hash_equals($meta['original_sha256'], hash('sha256',$original)) || !hash_equals($entry['before'],hash('sha256',acc007_norm($original,$relative)))) acc007_fail('rollback_backup_tampered');
        }
        if ($current === null && !$entry['new']) acc007_fail('rollback_target_missing');
        if ($current !== null) {
            $state = hash('sha256',acc007_norm($current,$relative));
            if (!hash_equals($entry['after'],$state) && !(!$entry['new'] && hash_equals($entry['before'],$state))) acc007_fail('rollback_later_file_change:' . $relative);
        }
        $restore[$relative] = ['target'=>$target,'current'=>$current,'original'=>$original];
    }
    foreach ($restore as $relative=>$item) {
        if ($item['original'] === null) {
            if ($item['current'] !== null && !unlink($item['target'])) acc007_fail('rollback_asset_delete_failed');
        } else {
            acc007_atomic($item['target'],$item['original']);
            if (substr($relative,-4)==='.php') acc007_lint($item['target']);
        }
        acc007_log('restored',$relative);
    }
    acc007_log('rollback','ok'); acc007_log('done','ok');
    @unlink(__FILE__);
}

$written = []; $plans = []; $backup = null;
try {
    if (PHP_VERSION_ID < 80000) acc007_fail('php_8_required');
    if (!function_exists('exec')) acc007_fail('php_lint_command_unavailable');
    $root = realpath(getcwd());
    if (!$root) acc007_fail('cwd_unavailable');
    acc007_log('cwd',$root); acc007_log('time',gmdate('c')); acc007_log('php',PHP_VERSION);
    $payload = json_decode(base64_decode('eyJ0YXNrIjoiQUNDLTAwNyIsIm1hcmtlciI6IkFDQy0wMDdfT1JERVJfU1RBVFVTX1BSRVNFTlRBVElPTl8yMDI2MTAxNCIsImFyY2hpdmVfc2hhMjU2IjoiNTgyZGNiYTVkZWJkOTk4OTIxMmIxMzE1ZWE4OWMxMTJkMmVjMTNmYmFmNTMyNjk2ZGY5ZjViZDlkNDQ5ZTY4MiIsImVudHJpZXMiOlt7InBhdGgiOiJjYXRhbG9nL21vZGVsL2FjY291bnQvb3JkZXIucGhwIiwibmV3IjpmYWxzZSwiYmVmb3JlIjoiNzYzMjZlMjE4ODJhZTQ0YWJiOTI2ODdhNjM3NjkyY2MwMTY4ZTFjODQ2OGY3NDYxYmYzZjE2MjhkNGVmNWY3NCIsImFmdGVyIjoiODEyNjM1OTJmOTA0NDJmMzkyMTM0MzRmYjFkODMxMzExYzU4YTI1MDZhZWZjYmNiNmFmNjEwY2ZiNGU3NTlkNyIsImVkaXRzIjpbeyJiZWZvcmUiOiJuYW1lc3BhY2UgT3BlbmNhcnRcXENhdGFsb2dcXE1vZGVsXFxBY2NvdW50O1xuIiwiYWZ0ZXIiOiJuYW1lc3BhY2UgT3BlbmNhcnRcXENhdGFsb2dcXE1vZGVsXFxBY2NvdW50O1xuLy8gQUNDLTAwN19PUkRFUl9TVEFUVVNfUFJFU0VOVEFUSU9OXzIwMjYxMDE0XG51c2UgT3BlbmNhcnRcXFN5c3RlbVxcTGlicmFyeVxcQWNjMDA3T3JkZXJTdGF0dXM7XG5yZXF1aXJlX29uY2UgRElSX1NZU1RFTSAuICdsaWJyYXJ5L2FjYzAwN19vcmRlcl9zdGF0dXMucGhwJztcbiIsImNvdW50IjoxfSx7ImJlZm9yZSI6Ilx0XHQkb3JkZXJfcXVlcnkgPSAkdGhpcy0+ZGItPnF1ZXJ5KFwiU0VMRUNUICogRlJPTSBgXCIgLiBEQl9QUkVGSVggLiBcIm9yZGVyYCBXSEVSRSBgb3JkZXJfaWRgID0gJ1wiIC4gKGludCkkb3JkZXJfaWQgLiBcIicgQU5EIGBjdXN0b21lcl9pZGAgPSAnXCIgLiAoaW50KSR0aGlzLT5jdXN0b21lci0+Z2V0SWQoKSAuIFwiJyBBTkQgYGN1c3RvbWVyX2lkYCAhPSAnMCcgQU5EIGBvcmRlcl9zdGF0dXNfaWRgID4gJzAnXCIpOyIsImFmdGVyIjoiXHRcdCRvcmRlcl9xdWVyeSA9ICR0aGlzLT5kYi0+cXVlcnkoJHRoaXMtPnB1YmxpY09yZGVyU2VsZWN0KCkgLiBcIiBXSEVSRSBgb2AuYG9yZGVyX2lkYCA9ICdcIiAuIChpbnQpJG9yZGVyX2lkIC4gXCInIEFORCBcIiAuICR0aGlzLT5wdWJsaWNPcmRlclNjb3BlKCkpOyIsImNvdW50IjoxfSx7ImJlZm9yZSI6Ilx0XHQkcXVlcnkgPSAkdGhpcy0+ZGItPnF1ZXJ5KFwiU0VMRUNUICogRlJPTSBgXCIgLiBEQl9QUkVGSVggLiBcIm9yZGVyYCBXSEVSRSBgY3VzdG9tZXJfaWRgID0gJ1wiIC4gKGludCkkdGhpcy0+Y3VzdG9tZXItPmdldElkKCkgLiBcIicgQU5EIGBvcmRlcl9zdGF0dXNfaWRgID4gJzAnIEFORCBgc3RvcmVfaWRgID0gJ1wiIC4gKGludCkkdGhpcy0+Y29uZmlnLT5nZXQoJ2NvbmZpZ19zdG9yZV9pZCcpIC4gXCInIE9SREVSIEJZIGBvcmRlcl9pZGAgREVTQyBMSU1JVCBcIiAuIChpbnQpJHN0YXJ0IC4gXCIsXCIgLiAoaW50KSRsaW1pdCk7IiwiYWZ0ZXIiOiJcdFx0JGxpbWl0ID0gbWluKCRsaW1pdCwgMTAwKTtcblx0XHQkcXVlcnkgPSAkdGhpcy0+ZGItPnF1ZXJ5KCR0aGlzLT5wdWJsaWNPcmRlclNlbGVjdCgpIC4gXCIgV0hFUkUgXCIgLiAkdGhpcy0+cHVibGljT3JkZXJTY29wZSgpIC4gXCIgT1JERVIgQlkgYG9gLmBvcmRlcl9pZGAgREVTQyBMSU1JVCBcIiAuIChpbnQpJHN0YXJ0IC4gXCIsXCIgLiAoaW50KSRsaW1pdCk7IiwiY291bnQiOjF9LHsiYmVmb3JlIjoiXHRwdWJsaWMgZnVuY3Rpb24gZ2V0SGlzdG9yaWVzKGludCAkb3JkZXJfaWQpOiBhcnJheSB7XG5cdFx0JHF1ZXJ5ID0gJHRoaXMtPmRiLT5xdWVyeShcIlNFTEVDVCBgZGF0ZV9hZGRlZGAsIGBvc2AuYG5hbWVgIEFTIGBzdGF0dXNgLCBgb2hgLmBjb21tZW50YCwgYG9oYC5gbm90aWZ5YCBGUk9NIGBcIiAuIERCX1BSRUZJWCAuIFwib3JkZXJfaGlzdG9yeWAgYG9oYCBMRUZUIEpPSU4gYFwiIC4gREJfUFJFRklYIC4gXCJvcmRlcl9zdGF0dXNgIGBvc2AgT04gYG9oYC5gb3JkZXJfc3RhdHVzX2lkYCA9IGBvc2AuYG9yZGVyX3N0YXR1c19pZGAgV0hFUkUgYG9oYC5gb3JkZXJfaWRgID0gJ1wiIC4gKGludCkkb3JkZXJfaWQgLiBcIicgQU5EIGBvc2AuYGxhbmd1YWdlX2lkYCA9ICdcIiAuIChpbnQpJHRoaXMtPmNvbmZpZy0+Z2V0KCdjb25maWdfbGFuZ3VhZ2VfaWQnKSAuIFwiJyBPUkRFUiBCWSBgb2hgLmBkYXRlX2FkZGVkYFwiKTtcblxuXHRcdHJldHVybiAkcXVlcnktPnJvd3M7XG5cdH1cbiIsImFmdGVyIjoiXHRwdWJsaWMgZnVuY3Rpb24gZ2V0SGlzdG9yaWVzKGludCAkb3JkZXJfaWQsIGludCAkc3RhcnQgPSAwLCBpbnQgJGxpbWl0ID0gMTApOiBhcnJheSB7XG5cdFx0JHN0YXJ0ID0gbWF4KDAsICRzdGFydCk7XG5cdFx0JGxpbWl0ID0gbWF4KDEsIG1pbigxMDAsICRsaW1pdCkpO1xuXHRcdCRxdWVyeSA9ICR0aGlzLT5kYi0+cXVlcnkoXCJTRUxFQ1QgYG9oYC5gb3JkZXJfaGlzdG9yeV9pZGAsYG9oYC5gb3JkZXJfc3RhdHVzX2lkYCxgb2hgLmBkYXRlX2FkZGVkYCBGUk9NIGBcIiAuIERCX1BSRUZJWCAuIFwib3JkZXJfaGlzdG9yeWAgYG9oYCBJTk5FUiBKT0lOIGBcIiAuIERCX1BSRUZJWCAuIFwib3JkZXJgIGBvYCBPTiAoYG9gLmBvcmRlcl9pZGAgPSBgb2hgLmBvcmRlcl9pZGApIFdIRVJFIGBvaGAuYG9yZGVyX2lkYCA9ICdcIiAuIChpbnQpJG9yZGVyX2lkIC4gXCInIEFORCBcIiAuICR0aGlzLT5wdWJsaWNPcmRlclNjb3BlKCkgLiBcIiBBTkQgXCIgLiBBY2MwMDdPcmRlclN0YXR1czo6dmlzaWJsZVNxbCgnYG9oYC5gb3JkZXJfc3RhdHVzX2lkYCcpIC4gXCIgT1JERVIgQlkgYG9oYC5gZGF0ZV9hZGRlZGAgQVNDLCBgb2hgLmBvcmRlcl9oaXN0b3J5X2lkYCBBU0MgTElNSVQgXCIgLiAkc3RhcnQgLiBcIixcIiAuICRsaW1pdCk7XG5cdFx0cmV0dXJuICRxdWVyeS0+cm93cztcblx0fVxuIiwiY291bnQiOjF9LHsiYmVmb3JlIjoiXHRwdWJsaWMgZnVuY3Rpb24gZ2V0VG90YWxIaXN0b3JpZXMoaW50ICRvcmRlcl9pZCk6IGludCB7XG5cdFx0JHF1ZXJ5ID0gJHRoaXMtPmRiLT5xdWVyeShcIlNFTEVDVCBDT1VOVCgqKSBBUyBgdG90YWxgIEZST00gYFwiIC4gREJfUFJFRklYIC4gXCJvcmRlcl9oaXN0b3J5YCBXSEVSRSBgb3JkZXJfaWRgID0gJ1wiIC4gKGludCkkb3JkZXJfaWQgLiBcIidcIik7XG5cblx0XHRpZiAoJHF1ZXJ5LT5udW1fcm93cykge1xuXHRcdFx0cmV0dXJuIChpbnQpJHF1ZXJ5LT5yb3dbJ3RvdGFsJ107XG5cdFx0fSBlbHNlIHtcblx0XHRcdHJldHVybiAwO1xuXHRcdH1cblx0fVxuIiwiYWZ0ZXIiOiJcdHB1YmxpYyBmdW5jdGlvbiBnZXRUb3RhbEhpc3RvcmllcyhpbnQgJG9yZGVyX2lkKTogaW50IHtcblx0XHQkcXVlcnkgPSAkdGhpcy0+ZGItPnF1ZXJ5KFwiU0VMRUNUIENPVU5UKCopIEFTIGB0b3RhbGAgRlJPTSBgXCIgLiBEQl9QUkVGSVggLiBcIm9yZGVyX2hpc3RvcnlgIGBvaGAgSU5ORVIgSk9JTiBgXCIgLiBEQl9QUkVGSVggLiBcIm9yZGVyYCBgb2AgT04gKGBvYC5gb3JkZXJfaWRgID0gYG9oYC5gb3JkZXJfaWRgKSBXSEVSRSBgb2hgLmBvcmRlcl9pZGAgPSAnXCIgLiAoaW50KSRvcmRlcl9pZCAuIFwiJyBBTkQgXCIgLiAkdGhpcy0+cHVibGljT3JkZXJTY29wZSgpIC4gXCIgQU5EIFwiIC4gQWNjMDA3T3JkZXJTdGF0dXM6OnZpc2libGVTcWwoJ2BvaGAuYG9yZGVyX3N0YXR1c19pZGAnKSk7XG5cblx0XHRpZiAoJHF1ZXJ5LT5udW1fcm93cykge1xuXHRcdFx0cmV0dXJuIChpbnQpJHF1ZXJ5LT5yb3dbJ3RvdGFsJ107XG5cdFx0fSBlbHNlIHtcblx0XHRcdHJldHVybiAwO1xuXHRcdH1cblx0fVxuIiwiY291bnQiOjF9LHsiYmVmb3JlIjoiXHRcdCRxdWVyeSA9ICR0aGlzLT5kYi0+cXVlcnkoXCJTRUxFQ1QgQ09VTlQoKikgQVMgYHRvdGFsYCBGUk9NIGBcIiAuIERCX1BSRUZJWCAuIFwib3JkZXJgIGBvYCBXSEVSRSBgY3VzdG9tZXJfaWRgID0gJ1wiIC4gKGludCkkdGhpcy0+Y3VzdG9tZXItPmdldElkKCkgLiBcIicgQU5EIGBvYC5gb3JkZXJfc3RhdHVzX2lkYCA+ICcwJyBBTkQgYG9gLmBzdG9yZV9pZGAgPSAnXCIgLiAoaW50KSR0aGlzLT5jb25maWctPmdldCgnY29uZmlnX3N0b3JlX2lkJykgLiBcIidcIik7IiwiYWZ0ZXIiOiJcdFx0JHF1ZXJ5ID0gJHRoaXMtPmRiLT5xdWVyeShcIlNFTEVDVCBDT1VOVCgqKSBBUyBgdG90YWxgIEZST00gYFwiIC4gREJfUFJFRklYIC4gXCJvcmRlcmAgYG9gIFdIRVJFIFwiIC4gJHRoaXMtPnB1YmxpY09yZGVyU2NvcGUoKSk7IiwiY291bnQiOjF9LHsiYmVmb3JlIjoiXG59XG4iLCJhZnRlciI6IlxuXHRwcml2YXRlIGZ1bmN0aW9uIHB1YmxpY09yZGVyU2NvcGUoKTogc3RyaW5nIHtcblx0XHRyZXR1cm4gXCJgb2AuYGN1c3RvbWVyX2lkYCA9ICdcIiAuIChpbnQpJHRoaXMtPmN1c3RvbWVyLT5nZXRJZCgpIC4gXCInIEFORCBgb2AuYGN1c3RvbWVyX2lkYCAhPSAnMCcgQU5EIGBvYC5gc3RvcmVfaWRgID0gJ1wiIC4gKGludCkkdGhpcy0+Y29uZmlnLT5nZXQoJ2NvbmZpZ19zdG9yZV9pZCcpIC4gXCInIEFORCBgb2AuYG9yZGVyX3N0YXR1c19pZGAgPiAnMCcgQU5EIFwiIC4gQWNjMDA3T3JkZXJTdGF0dXM6OnZpc2libGVTcWwoJ2BvYC5gb3JkZXJfc3RhdHVzX2lkYCcpO1xuXHR9XG5cblx0cHJpdmF0ZSBmdW5jdGlvbiBwdWJsaWNPcmRlclNlbGVjdCgpOiBzdHJpbmcge1xuXHRcdC8vIE9ubHkgdGhlIGxhdGVzdCBhcHBlbmRlZCBoaXN0b3J5IGNhbiBhdHRlc3QgdGhlIENVUlJFTlQgc3RhdGUuIEFuIG9sZCBvY2N1cnJlbmNlIG9mXG5cdFx0Ly8gYSByZWN1cnJpbmcgc3RhdHVzLCBhbiB1bmRhdGVkIHJvdyBvciBhbiBhZG1pbiBzYXZlIHdpdGhvdXQgaGlzdG9yeSBnaXZlcyB1bmtub3duIHRpbWUuXG5cdFx0cmV0dXJuIFwiU0VMRUNUIGBvYC4qLCAoU0VMRUNUIENBU0UgV0hFTiBgbGFzdGAuYG9yZGVyX3N0YXR1c19pZGAgPSBgb2AuYG9yZGVyX3N0YXR1c19pZGAgVEhFTiBgbGFzdGAuYGRhdGVfYWRkZWRgIEVMU0UgTlVMTCBFTkQgRlJPTSBgXCIgLiBEQl9QUkVGSVggLiBcIm9yZGVyX2hpc3RvcnlgIGBsYXN0YCBXSEVSRSBgbGFzdGAuYG9yZGVyX2lkYCA9IGBvYC5gb3JkZXJfaWRgIE9SREVSIEJZIGBsYXN0YC5gb3JkZXJfaGlzdG9yeV9pZGAgREVTQyBMSU1JVCAxKSBBUyBgYnNfc3RhdHVzX2RhdGVfYWRkZWRgIEZST00gYFwiIC4gREJfUFJFRklYIC4gXCJvcmRlcmAgYG9gXCI7XG5cdH1cbn1cbiIsImNvdW50IjoxfV19LHsicGF0aCI6ImNhdGFsb2cvY29udHJvbGxlci9hY2NvdW50L29yZGVyLnBocCIsIm5ldyI6ZmFsc2UsImJlZm9yZSI6IjYwODlkZDBmMzllNjdiOGNjMTQ3Mzk3NThjYjk3MDk4NTFlZTcyNjA3OTBhNTM4N2E4NWUwNTMyMWY2OTk0MGYiLCJhZnRlciI6IjcyMjQwMGQ3OGEyYWViZTY3MjZmMjczOTJjNmUyZDFhMTgxMTZhZjc2NGVjZTU5NDViODEwMDM1MDljODRmNTkiLCJlZGl0cyI6W3siYmVmb3JlIjoibmFtZXNwYWNlIE9wZW5jYXJ0XFxDYXRhbG9nXFxDb250cm9sbGVyXFxBY2NvdW50O1xuIiwiYWZ0ZXIiOiJuYW1lc3BhY2UgT3BlbmNhcnRcXENhdGFsb2dcXENvbnRyb2xsZXJcXEFjY291bnQ7XG4vLyBBQ0MtMDA3X09SREVSX1NUQVRVU19QUkVTRU5UQVRJT05fMjAyNjEwMTRcbnVzZSBPcGVuY2FydFxcU3lzdGVtXFxMaWJyYXJ5XFxBY2MwMDdPcmRlclN0YXR1cztcbnJlcXVpcmVfb25jZSBESVJfU1lTVEVNIC4gJ2xpYnJhcnkvYWNjMDA3X29yZGVyX3N0YXR1cy5waHAnO1xuIiwiY291bnQiOjF9LHsiYmVmb3JlIjoiXHRcdFx0JG9yZGVyX3N0YXR1c19pbmZvID0gJHRoaXMtPm1vZGVsX2xvY2FsaXNhdGlvbl9vcmRlcl9zdGF0dXMtPmdldE9yZGVyU3RhdHVzKCRyZXN1bHRbJ29yZGVyX3N0YXR1c19pZCddKTtcblxuXHRcdFx0aWYgKCRvcmRlcl9zdGF0dXNfaW5mbykge1xuXHRcdFx0XHQkb3JkZXJfc3RhdHVzID0gJG9yZGVyX3N0YXR1c19pbmZvWyduYW1lJ107XG5cdFx0XHR9IGVsc2Uge1xuXHRcdFx0XHQkb3JkZXJfc3RhdHVzID0gJyc7XG5cdFx0XHR9IiwiYWZ0ZXIiOiJcdFx0XHQkYnNfc3RhdHVzID0gQWNjMDA3T3JkZXJTdGF0dXM6OnByZXNlbnRhdGlvbigoaW50KSR0aGlzLT5jb25maWctPmdldCgnY29uZmlnX2xhbmd1YWdlX2lkJyksIChpbnQpJHJlc3VsdFsnb3JkZXJfc3RhdHVzX2lkJ10pOyIsImNvdW50IjoxfSx7ImJlZm9yZSI6Ilx0XHRcdFx0J3N0YXR1cycgICAgICAgID0+ICRvcmRlcl9zdGF0dXMsIiwiYWZ0ZXIiOiJcdFx0XHRcdCdzdGF0dXMnICAgICAgICA9PiAkYnNfc3RhdHVzWydsYWJlbCddLFxuXHRcdFx0XHQnYnNfc3RhdHVzX3N0eWxlJyA9PiAkYnNfc3RhdHVzWydzdHlsZSddLFxuXHRcdFx0XHQnYnNfc3RhdHVzX2RhdGUnID0+IEFjYzAwN09yZGVyU3RhdHVzOjpkYXRlKCRyZXN1bHRbJ2JzX3N0YXR1c19kYXRlX2FkZGVkJ10gPz8gbnVsbCwgJHRoaXMtPmNvbmZpZy0+Z2V0KCdjb25maWdfdGltZXpvbmUnKSksIiwiY291bnQiOjF9LHsiYmVmb3JlIjoiXHRcdFx0JG9yZGVyX3N0YXR1c19pbmZvID0gJHRoaXMtPm1vZGVsX2xvY2FsaXNhdGlvbl9vcmRlcl9zdGF0dXMtPmdldE9yZGVyU3RhdHVzKCRvcmRlcl9pbmZvWydvcmRlcl9zdGF0dXNfaWQnXSk7XG5cblx0XHRcdGlmICgkb3JkZXJfc3RhdHVzX2luZm8pIHtcblx0XHRcdFx0JGRhdGFbJ29yZGVyX3N0YXR1cyddID0gJG9yZGVyX3N0YXR1c19pbmZvWyduYW1lJ107XG5cdFx0XHR9IGVsc2Uge1xuXHRcdFx0XHQkZGF0YVsnb3JkZXJfc3RhdHVzJ10gPSAnJztcblx0XHRcdH0iLCJhZnRlciI6Ilx0XHRcdCRic19zdGF0dXMgPSBBY2MwMDdPcmRlclN0YXR1czo6cHJlc2VudGF0aW9uKChpbnQpJHRoaXMtPmNvbmZpZy0+Z2V0KCdjb25maWdfbGFuZ3VhZ2VfaWQnKSwgKGludCkkb3JkZXJfaW5mb1snb3JkZXJfc3RhdHVzX2lkJ10pO1xuXHRcdFx0JGRhdGFbJ29yZGVyX3N0YXR1cyddID0gJGJzX3N0YXR1c1snbGFiZWwnXTtcblx0XHRcdCRkYXRhWydic19zdGF0dXNfc3R5bGUnXSA9ICRic19zdGF0dXNbJ3N0eWxlJ107XG5cdFx0XHQkZGF0YVsnYnNfc3RhdHVzX21lc3NhZ2UnXSA9ICRic19zdGF0dXNbJ21lc3NhZ2UnXTtcblx0XHRcdCRkYXRhWydic19zdGF0dXNfZGF0ZSddID0gQWNjMDA3T3JkZXJTdGF0dXM6OmRhdGUoJG9yZGVyX2luZm9bJ2JzX3N0YXR1c19kYXRlX2FkZGVkJ10gPz8gbnVsbCwgJHRoaXMtPmNvbmZpZy0+Z2V0KCdjb25maWdfdGltZXpvbmUnKSk7IiwiY291bnQiOjF9LHsiYmVmb3JlIjoiXHRcdCRyZXN1bHRzID0gJHRoaXMtPm1vZGVsX2FjY291bnRfb3JkZXItPmdldEhpc3Rvcmllcygkb3JkZXJfaWQpOyIsImFmdGVyIjoiXHRcdCRwYWdlID0gbWF4KDEsIG1pbigkcGFnZSwgaW50ZGl2KFBIUF9JTlRfTUFYLCAkbGltaXQpKSk7XG5cdFx0JHJlc3VsdHMgPSAkdGhpcy0+bW9kZWxfYWNjb3VudF9vcmRlci0+Z2V0SGlzdG9yaWVzKCRvcmRlcl9pZCwgKCRwYWdlIC0gMSkgKiAkbGltaXQsICRsaW1pdCk7IiwiY291bnQiOjF9LHsiYmVmb3JlIjoiXHRcdGZvcmVhY2ggKCRyZXN1bHRzIGFzICRyZXN1bHQpIHtcblx0XHRcdCRkYXRhWydoaXN0b3JpZXMnXVtdID0gW1xuXHRcdFx0XHQnY29tbWVudCcgICAgPT4gbmwyYnIoJHJlc3VsdFsnY29tbWVudCddKSxcblx0XHRcdFx0J2RhdGVfYWRkZWQnID0+IGRhdGUoJHRoaXMtPmxhbmd1YWdlLT5nZXQoJ2RhdGVfZm9ybWF0X3Nob3J0JyksIHN0cnRvdGltZSgkcmVzdWx0WydkYXRlX2FkZGVkJ10pKVxuXHRcdFx0XSArICRyZXN1bHQ7XG5cdFx0fSIsImFmdGVyIjoiXHRcdGZvcmVhY2ggKCRyZXN1bHRzIGFzICRyZXN1bHQpIHtcblx0XHRcdCRic19zdGF0dXMgPSBBY2MwMDdPcmRlclN0YXR1czo6cHJlc2VudGF0aW9uKChpbnQpJHRoaXMtPmNvbmZpZy0+Z2V0KCdjb25maWdfbGFuZ3VhZ2VfaWQnKSwgKGludCkkcmVzdWx0WydvcmRlcl9zdGF0dXNfaWQnXSk7XG5cdFx0XHQkZGF0YVsnaGlzdG9yaWVzJ11bXSA9IFtcblx0XHRcdFx0J2NvbW1lbnQnID0+ICRic19zdGF0dXNbJ21lc3NhZ2UnXSxcblx0XHRcdFx0J3N0YXR1cycgPT4gJGJzX3N0YXR1c1snbGFiZWwnXSxcblx0XHRcdFx0J2JzX3N0YXR1c19zdHlsZScgPT4gJGJzX3N0YXR1c1snc3R5bGUnXSxcblx0XHRcdFx0J2RhdGVfYWRkZWQnID0+IEFjYzAwN09yZGVyU3RhdHVzOjpkYXRlKCRyZXN1bHRbJ2RhdGVfYWRkZWQnXSwgJHRoaXMtPmNvbmZpZy0+Z2V0KCdjb25maWdfdGltZXpvbmUnKSlcblx0XHRcdF07XG5cdFx0fSIsImNvdW50IjoxfSx7ImJlZm9yZSI6Ilx0XHQkbGltaXQgPSAxMDtcblxuXHRcdCRkYXRhWydvcmRlcnMnXSA9IFtdOyIsImFmdGVyIjoiXHRcdCRsaW1pdCA9IDEwO1xuXHRcdCRwYWdlID0gbWF4KDEsIG1pbigkcGFnZSwgaW50ZGl2KFBIUF9JTlRfTUFYLCAkbGltaXQpKSk7XG5cblx0XHQkZGF0YVsnb3JkZXJzJ10gPSBbXTsiLCJjb3VudCI6MX1dfSx7InBhdGgiOiJjYXRhbG9nL3ZpZXcvdGVtcGxhdGUvYWNjb3VudC9vcmRlcl9saXN0LnR3aWciLCJuZXciOmZhbHNlLCJiZWZvcmUiOiI5Mjk5NDlhMWRiZGQwMzcxNmM5N2Y2ZGUxOTVmMWE0YzZkYmQxOTFmMTAxZWQyNTQwNzBmZjQ5NjE3Njc5YzZlIiwiYWZ0ZXIiOiJmNTI2ZGI5Y2NlYzQzYzkyNTc3YmU5ZmQxMjA5MTBlOGJlNDAwMGVmYTYxMWI5YzZhZDdjOGIwMmM0YTljMjQxIiwiZWRpdHMiOlt7ImJlZm9yZSI6Int7IGhlYWRlciB9fSIsImFmdGVyIjoieyMgQUNDLTAwN19PUkRFUl9TVEFUVVNfUFJFU0VOVEFUSU9OXzIwMjYxMDE0ICN9XG57eyBoZWFkZXIgfX0iLCJjb3VudCI6MX0seyJiZWZvcmUiOiI8dGg+e3sgY29sdW1uX2RhdGVfYWRkZWQgfX08L3RoPiIsImFmdGVyIjoiPHRoPnt7IGNvbHVtbl9zdGF0dXNfY2hhbmdlZCB9fTwvdGg+IiwiY291bnQiOjF9LHsiYmVmb3JlIjoiPHRkPnt7IG9yZGVyLnN0YXR1cyB9fTwvdGQ+IiwiYWZ0ZXIiOiI8dGQ+PHNwYW4gY2xhc3M9XCJicy1iYWRnZVwiIHN0eWxlPVwie3sgb3JkZXIuYnNfc3RhdHVzX3N0eWxlIH19XCI+e3sgb3JkZXIuc3RhdHVzIH19PC9zcGFuPjwvdGQ+IiwiY291bnQiOjF9LHsiYmVmb3JlIjoiPHRkPnt7IG9yZGVyLmRhdGVfYWRkZWQgfX08L3RkPiIsImFmdGVyIjoiPHRkPnt7IG9yZGVyLmJzX3N0YXR1c19kYXRlIH19PC90ZD4iLCJjb3VudCI6MX1dfSx7InBhdGgiOiJjYXRhbG9nL3ZpZXcvdGVtcGxhdGUvYWNjb3VudC9vcmRlcl9pbmZvLnR3aWciLCJuZXciOmZhbHNlLCJiZWZvcmUiOiI5ZDNhZTc1YTQ4Yzk2ZDZkMDczMTA1MDYzMWJjZTNmY2Y2ZmUxOGNkOWZhYmU4ZGMzNGM3YmFlYTYxMjg4ZjczIiwiYWZ0ZXIiOiI3MzA2N2EwMGRhNmJlOTQ5ZjgwYjcyNWZiZmI2MDhjZTYyZTQxY2Q3Y2VmYmNhOTM2YjgzNDAwYWYwYjkxNzkzIiwiZWRpdHMiOlt7ImJlZm9yZSI6Int7IGhlYWRlciB9fSIsImFmdGVyIjoieyMgQUNDLTAwN19PUkRFUl9TVEFUVVNfUFJFU0VOVEFUSU9OXzIwMjYxMDE0ICN9XG57eyBoZWFkZXIgfX0iLCJjb3VudCI6MX0seyJiZWZvcmUiOiIgICAgICB7eyBjb250ZW50X3RvcCB9fSIsImFmdGVyIjoiICAgICAge3sgY29udGVudF90b3AgfX1cbiAgICAgIDxkaXYgY2xhc3M9XCJtYi0zXCI+XG4gICAgICAgIDxwPnt7IHRleHRfY3VycmVudF9zdGF0dXMgfX06IDxzcGFuIGNsYXNzPVwiYnMtYmFkZ2VcIiBzdHlsZT1cInt7IGJzX3N0YXR1c19zdHlsZSB9fVwiPnt7IG9yZGVyX3N0YXR1cyB9fTwvc3Bhbj48L3A+XG4gICAgICAgIDxwPnt7IHRleHRfc3RhdHVzX2RhdGUgfX06IHt7IGJzX3N0YXR1c19kYXRlIH19PC9wPlxuICAgICAgICA8cD57eyBic19zdGF0dXNfbWVzc2FnZSB9fTwvcD5cbiAgICAgIDwvZGl2PiIsImNvdW50IjoxfV19LHsicGF0aCI6ImNhdGFsb2cvdmlldy90ZW1wbGF0ZS9hY2NvdW50L29yZGVyX2hpc3RvcnkudHdpZyIsIm5ldyI6ZmFsc2UsImJlZm9yZSI6ImJkZjJmMzE3MGM3ZTBmY2E3NmUwMDEyNWFhY2M0YmIxOTU3ZTM0MmNjMDFmMGE4OGY1YzZkZTQ5ODE0NjQwZWQiLCJhZnRlciI6IjJjMWJhZmJlZjg5NzA1OWNkMzRkOGVlZGE0NmRlYWY2NWUyOWEzZmY2OTM0NTYzNDk2ZDNmNzFiY2FjYWQyZmUiLCJlZGl0cyI6W3siYmVmb3JlIjoiPGRpdiBjbGFzcz1cInRhYmxlLXJlc3BvbnNpdmVcIj4iLCJhZnRlciI6InsjIEFDQy0wMDdfT1JERVJfU1RBVFVTX1BSRVNFTlRBVElPTl8yMDI2MTAxNCAjfVxuPGRpdiBjbGFzcz1cInRhYmxlLXJlc3BvbnNpdmVcIj4iLCJjb3VudCI6MX0seyJiZWZvcmUiOiI8dGg+e3sgY29sdW1uX2RhdGVfYWRkZWQgfX08L3RoPiIsImFmdGVyIjoiPHRoPnt7IGNvbHVtbl9zdGF0dXNfY2hhbmdlZCB9fTwvdGg+IiwiY291bnQiOjF9LHsiYmVmb3JlIjoiPHRoPnt7IGNvbHVtbl9jb21tZW50IH19PC90aD4iLCJhZnRlciI6Ijx0aD57eyBjb2x1bW5fcHVibGljX21lc3NhZ2UgfX08L3RoPiIsImNvdW50IjoxfSx7ImJlZm9yZSI6Ijx0ZD57eyBoaXN0b3J5LnN0YXR1cyB9fTwvdGQ+IiwiYWZ0ZXIiOiI8dGQ+PHNwYW4gY2xhc3M9XCJicy1iYWRnZVwiIHN0eWxlPVwie3sgaGlzdG9yeS5ic19zdGF0dXNfc3R5bGUgfX1cIj57eyBoaXN0b3J5LnN0YXR1cyB9fTwvc3Bhbj48L3RkPiIsImNvdW50IjoxfV19LHsicGF0aCI6ImV4dGVuc2lvbi91a3JhaW5pYW4vY2F0YWxvZy9sYW5ndWFnZS91ay11YS9hY2NvdW50L29yZGVyLnBocCIsIm5ldyI6ZmFsc2UsImJlZm9yZSI6IjliYzc0MDRjMzE2ODc2MTcwYWU4ODJhMjZlMWNlNmZhZjY0MjhkODU3MTEzMTU1NTkxNWZhOTBlZmMxNTE3ZjIiLCJhZnRlciI6ImJkMjVhYmZlMDI0MDgzNDdjOWVmMTc3MGU2MTM0ZGYxMTEwMWE4Yzk4ZmNjNmU0ZDBkZWRmYmRlZTg0MWRjZWEiLCJlZGl0cyI6W3siYmVmb3JlIjoiPD9waHBcbiIsImFmdGVyIjoiPD9waHBcbi8vIEFDQy0wMDdfT1JERVJfU1RBVFVTX1BSRVNFTlRBVElPTl8yMDI2MTAxNFxuJF9bJ2NvbHVtbl9zdGF0dXNfY2hhbmdlZCddID0gJ9CU0LDRgtCwINC30LzRltC90Lgg0YHRgtCw0YLRg9GB0YMgKNCa0LjRl9CyKSc7XG4kX1snY29sdW1uX3B1YmxpY19tZXNzYWdlJ10gPSAn0J/QvtCy0ZbQtNC+0LzQu9C10L3QvdGPJztcbiRfWyd0ZXh0X2N1cnJlbnRfc3RhdHVzJ10gPSAn0J/QvtGC0L7Rh9C90LjQuSDRgdGC0LDRgtGD0YEnO1xuJF9bJ3RleHRfc3RhdHVzX2RhdGUnXSA9ICfQlNCw0YLQsCDQt9C80ZbQvdC4ICjQmtC40ZfQsiknO1xuIiwiY291bnQiOjF9XX0seyJwYXRoIjoic3lzdGVtL2xpYnJhcnkvYWNjMDA3X29yZGVyX3N0YXR1cy5waHAiLCJuZXciOnRydWUsImJlZm9yZSI6bnVsbCwiYWZ0ZXIiOiI5MDg2ZWZmOTE3ZWEyMDAzYmRhZDc4NmQwZGRhYTY4MGYwNmNiNzAyYjlhZjE1NjdjMzBjMzkxNmU2Y2E2ODg1IiwiY29udGVudCI6Ijw/cGhwXG5uYW1lc3BhY2UgT3BlbmNhcnRcXFN5c3RlbVxcTGlicmFyeTtcbi8vIEFDQy0wMDdfT1JERVJfU1RBVFVTX1BSRVNFTlRBVElPTl8yMDI2MTAxNFxuZmluYWwgY2xhc3MgQWNjMDA3T3JkZXJTdGF0dXMge1xuICAgIC8qKiBDdXN0b21lciBwcmVzZW50YXRpb24gaXMgaW5kZXBlbmRlbnQgb2YgYWRtaW5pc3RyYXRpdmUgbmFtZXMvbm90aWZ5IGJpdHMuICovXG4gICAgcHVibGljIHN0YXRpYyBmdW5jdGlvbiBwcmVzZW50YXRpb24oaW50ICRsYW5ndWFnZV9pZCwgaW50ICRzdGF0dXNfaWQpOiBhcnJheSB7XG4gICAgICAgICRtYXAgPSBbNCA9PiBbXG4gICAgICAgICAgICAxID0+IFsn0JIg0L7QsdGA0L7QsdGG0ZYnLCAnaW5fcHJvZ3Jlc3MnLCAnYmx1ZScsICfQnNC4INC+0LHRgNC+0LHQu9GP0ZTQvNC+INCy0LDRiNC1INC30LDQvNC+0LLQu9C10L3QvdGPLiddLFxuICAgICAgICAgICAgMyA9PiBbJ9Cf0LXRgNC10LTQsNC90L4g0LIg0LTQvtGB0YLQsNCy0LrRgycsICdpbl90cmFuc2l0JywgJ2JsdWUnLCAn0JfQsNC80L7QstC70LXQvdC90Y8g0L/QtdGA0LXQtNCw0L3QviDQsiDQtNC+0YHRgtCw0LLQutGDLiddLFxuICAgICAgICAgICAgNSA9PiBbJ9Ce0YLRgNC40LzQsNC90L4nLCAnY29tcGxldGVkJywgJ2dyZWVuJywgJ9CX0LDQvNC+0LLQu9C10L3QvdGPINC+0YLRgNC40LzQsNC90L4uJ10sXG4gICAgICAgICAgICA3ID0+IFsn0J7Rh9GW0LrRg9GUINC+0YLRgNC40LzQsNC90L3RjycsICdpbl90cmFuc2l0JywgJ2JsdWUnLCAn0JfQsNC80L7QstC70LXQvdC90Y8g0L7Rh9GW0LrRg9GUINC+0YLRgNC40LzQsNC90L3Rjy4nXSxcbiAgICAgICAgICAgIDEwID0+IFsn0J/QvtGC0YDRltCx0L3QsCDRg9Cy0LDQs9CwJywgJ2NhbmNlbGxlZF9wcm9ibGVtJywgJ2FtYmVyJywgJ9Cf0L7RgtGA0ZbQsdC90LAg0LTQvtC00LDRgtC60L7QstCwINC/0LXRgNC10LLRltGA0LrQsCDQt9Cw0LzQvtCy0LvQtdC90L3Rjy4nXSxcbiAgICAgICAgICAgIDEyID0+IFsn0J/QvtCy0LXRgNC90LXQvdC+JywgJ2NhbmNlbGxlZF9wcm9ibGVtJywgJ2FtYmVyJywgJ9CU0LvRjyDQt9Cw0LzQvtCy0LvQtdC90L3RjyDQt9Cw0YTRltC60YHQvtCy0LDQvdC+INC/0L7QstC10YDQvdC10L3QvdGPLiddLFxuICAgICAgICAgICAgMTMgPT4gWyfQn9C+0LLQtdGA0L3QtdC90L3RjyDQutC+0YjRgtGW0LInLCAnY2FuY2VsbGVkX3Byb2JsZW0nLCAnYW1iZXInLCAn0JTQu9GPINC30LDQvNC+0LLQu9C10L3QvdGPINC30LDRhNGW0LrRgdC+0LLQsNC90L4g0YHRgtCw0YLRg9GBINC/0L7QstC10YDQvdC10L3QvdGPINC60L7RiNGC0ZbQsi4nXSxcbiAgICAgICAgICAgIDE0ID0+IFsn0KLQtdGA0LzRltC9INC+0L/Qu9Cw0YLQuCDQvNC40L3Rg9CyJywgJ2NhbmNlbGxlZF9wcm9ibGVtJywgJ2FtYmVyJywgJ9Ci0LXRgNC80ZbQvSDQvtC/0LvQsNGC0Lgg0LfQsNC80L7QstC70LXQvdC90Y8g0LzQuNC90YPQsi4nXSxcbiAgICAgICAgICAgIDE1ID0+IFsnJywgJ2hpZGRlbicsICduZXV0cmFsJywgJyddLFxuICAgICAgICAgICAgMTcgPT4gWyfQn9GW0LTRgtCy0LXRgNC00YzRgtC1INC+0L/Qu9Cw0YLRgyDRh9Cw0YHRgtC40L3QsNC80LgnLCAnaW5fcHJvZ3Jlc3MnLCAnYW1iZXInLCAn0J/QvtGC0YDRltCx0L3QtSDQstCw0YjQtSDQv9GW0LTRgtCy0LXRgNC00LbQtdC90L3RjyDQvtC/0LvQsNGC0Lgg0YfQsNGB0YLQuNC90LDQvNC4LiddLFxuICAgICAgICAgICAgMTggPT4gWyfQntC/0LvQsNGC0LAg0YfQsNGB0YLQuNC90LDQvNC4IOKAlCDQv9C10YDQtdCy0ZbRgNC60LAg0LzQsNCz0LDQt9C40L3RgycsICdpbl9wcm9ncmVzcycsICdibHVlJywgJ9Cc0LDQs9Cw0LfQuNC9INC/0LXRgNC10LLRltGA0Y/RlCDQvtC/0LvQsNGC0YMg0YfQsNGB0YLQuNC90LDQvNC4LiddLFxuICAgICAgICAgICAgMTkgPT4gWyfQntC/0LvQsNGC0YMg0YfQsNGB0YLQuNC90LDQvNC4INC/0ZbQtNGC0LLQtdGA0LTQttC10L3QvicsICdpbl9wcm9ncmVzcycsICdibHVlJywgJ9Ce0L/Qu9Cw0YLRgyDRh9Cw0YHRgtC40L3QsNC80Lgg0L/RltC00YLQstC10YDQtNC20LXQvdC+LiDQptC1INC90LUg0L7Qt9C90LDRh9Cw0ZQg0L7RgtGA0LjQvNCw0L3QvdGPINC30LDQvNC+0LLQu9C10L3QvdGPLiddLFxuICAgICAgICAgICAgMjEgPT4gWyfQntC/0LvQsNGC0YMg0YfQsNGB0YLQuNC90LDQvNC4INC/0L7QstC10YDQvdC10L3QvicsICdjYW5jZWxsZWRfcHJvYmxlbScsICdhbWJlcicsICfQlNC70Y8g0L7Qv9C70LDRgtC4INGH0LDRgdGC0LjQvdCw0LzQuCDQt9Cw0YTRltC60YHQvtCy0LDQvdC+INC/0L7QstC10YDQvdC10L3QvdGPLiddLFxuICAgICAgICAgICAgMjIgPT4gWyfQntC/0LvQsNGC0YMg0YfQsNGB0YLQuNC90LDQvNC4INC90LUg0L/RltC00YLQstC10YDQtNC20LXQvdC+JywgJ2NhbmNlbGxlZF9wcm9ibGVtJywgJ2FtYmVyJywgJ9Ce0L/Qu9Cw0YLRgyDRh9Cw0YHRgtC40L3QsNC80Lgg0L3QtSDQv9GW0LTRgtCy0LXRgNC00LbQtdC90L4uJ10sXG4gICAgICAgICAgICAyMyA9PiBbJ9Cf0LXRgNC10LTQt9Cw0LzQvtCy0LvQtdC90L3RjycsICdpbl9wcm9ncmVzcycsICdibHVlJywgJ9CX0LDQvNC+0LLQu9C10L3QvdGPINC+0YTQvtGA0LzQu9C10L3QviDRj9C6INC/0LXRgNC10LTQt9Cw0LzQvtCy0LvQtdC90L3Rjy4nXSxcbiAgICAgICAgICAgIDI0ID0+IFsn0KHQutCw0YHQvtCy0LDQvdC+JywgJ2NhbmNlbGxlZF9wcm9ibGVtJywgJ3JlZCcsICfQl9Cw0LzQvtCy0LvQtdC90L3RjyDRgdC60LDRgdC+0LLQsNC90L4uJ11cbiAgICAgICAgXV07XG4gICAgICAgICR2YWx1ZSA9ICRtYXBbJGxhbmd1YWdlX2lkXVskc3RhdHVzX2lkXSA/PyBbJ9Ch0YLQsNGC0YPRgSDRg9GC0L7Rh9C90Y7RlNGC0YzRgdGPJywgJ25ldXRyYWwnLCAnbmV1dHJhbCcsICfQhtC90YTQvtGA0LzQsNGG0ZbRjyDQv9GA0L4g0YbQtdC5INGB0YLQsNC9INC30LDQvNC+0LLQu9C10L3QvdGPINGD0YLQvtGH0L3RjtGU0YLRjNGB0Y8uJ107XG4gICAgICAgICRzdHlsZXMgPSBbXG4gICAgICAgICAgICAnYmx1ZScgPT4gJ2JhY2tncm91bmQ6dmFyKC0tYnMtYmx1ZS1zb2Z0KTtjb2xvcjp2YXIoLS1icy1ibHVlKScsXG4gICAgICAgICAgICAnZ3JlZW4nID0+ICdiYWNrZ3JvdW5kOnZhcigtLWJzLWdyZWVuLXNvZnQpO2NvbG9yOnZhcigtLWJzLWdyZWVuLWQpJyxcbiAgICAgICAgICAgICdhbWJlcicgPT4gJ2JhY2tncm91bmQ6dmFyKC0tYnMtd2FybmluZy1iZyk7Y29sb3I6dmFyKC0tYnMtd2FybmluZy1mZyknLFxuICAgICAgICAgICAgJ3JlZCcgPT4gJ2JhY2tncm91bmQ6dmFyKC0tYnMtZGFuZ2VyKTtjb2xvcjp2YXIoLS1icy1wYXBlciknLFxuICAgICAgICAgICAgJ25ldXRyYWwnID0+ICdiYWNrZ3JvdW5kOnZhcigtLWJzLWxpbmUtMik7Y29sb3I6dmFyKC0tYnMtaW5rLTIpJ1xuICAgICAgICBdO1xuICAgICAgICByZXR1cm4gWydsYWJlbCc9PiR2YWx1ZVswXSwgJ2dyb3VwJz0+JHZhbHVlWzFdLCAnY29sb3VyJz0+JHZhbHVlWzJdLCAnbWVzc2FnZSc9PiR2YWx1ZVszXSwgJ3Zpc2libGUnPT4kc3RhdHVzX2lkICE9PSAxNSwgJ3N0eWxlJz0+JHN0eWxlc1skdmFsdWVbMl1dXTtcbiAgICB9XG5cbiAgICAvKiogT25lIGhpZGRlbi1zdGF0dXMgcHJlZGljYXRlIGZvciBhbGwgcHVibGljIHF1ZXJ5L2NvdW50IHBhdGhzOyBOVUxMLzAgcmVtYWluIG5ldXRyYWwgaW4gaGlzdG9yeS4gKi9cbiAgICBwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIHZpc2libGVTcWwoc3RyaW5nICRjb2x1bW4pOiBzdHJpbmcge1xuICAgICAgICByZXR1cm4gJ0NPQUxFU0NFKCcgLiAkY29sdW1uIC4gJywwKSA8PiAxNSc7XG4gICAgfVxuXG4gICAgLyoqIFZlcmlmaWVkIHNob3Agd3JpdGVyIHVzZXMgS3lpdiBsb2NhbCBEQVRFVElNRS4gUHJlc2VydmUgaXRzIHdhbGwgdGltZTsgbmV2ZXIgc2hpZnQgaXQgYXMgVVRDLiAqL1xuICAgIHB1YmxpYyBzdGF0aWMgZnVuY3Rpb24gZGF0ZSgkdmFsdWUsICRjb25maWd1cmVkX3RpbWV6b25lKTogc3RyaW5nIHtcbiAgICAgICAgJHVua25vd24gPSAn0JTQsNGC0YMg0LfQvNGW0L3QuCDQvdC1INC30LDRhNGW0LrRgdC+0LLQsNC90L4nO1xuICAgICAgICBpZiAoJGNvbmZpZ3VyZWRfdGltZXpvbmUgIT09ICdFdXJvcGUvS3lpdicgfHwgIWlzX3N0cmluZygkdmFsdWUpXG4gICAgICAgICAgICB8fCAhcHJlZ19tYXRjaCgnL15bMC05XXs0fS1bMC05XXsyfS1bMC05XXsyfSBbMC05XXsyfTpbMC05XXsyfTpbMC05XXsyfSQvRCcsICR2YWx1ZSlcbiAgICAgICAgICAgIHx8IChpbnQpc3Vic3RyKCR2YWx1ZSwgMCwgNCkgPCAxMDAwKSByZXR1cm4gJHVua25vd247XG4gICAgICAgICRkYXRlID0gXFxEYXRlVGltZUltbXV0YWJsZTo6Y3JlYXRlRnJvbUZvcm1hdCgnIVktbS1kIEg6aTpzJywgJHZhbHVlLCBuZXcgXFxEYXRlVGltZVpvbmUoJ0V1cm9wZS9LeWl2JykpO1xuICAgICAgICAkZXJyb3JzID0gXFxEYXRlVGltZUltbXV0YWJsZTo6Z2V0TGFzdEVycm9ycygpO1xuICAgICAgICBpZiAoISRkYXRlIHx8ICgkZXJyb3JzICE9PSBmYWxzZSAmJiAoJGVycm9yc1snd2FybmluZ19jb3VudCddIHx8ICRlcnJvcnNbJ2Vycm9yX2NvdW50J10pKVxuICAgICAgICAgICAgfHwgJGRhdGUtPmZvcm1hdCgnWS1tLWQgSDppOnMnKSAhPT0gJHZhbHVlKSByZXR1cm4gJHVua25vd247XG4gICAgICAgIHJldHVybiAkZGF0ZS0+Zm9ybWF0KCdkLm0uWSBIOmk6cycpO1xuICAgIH1cbn1cbiJ9XSwiZGVwZW5kZW5jaWVzIjpbeyJwYXRoIjoiaW5kZXgucGhwIiwic2hhMjU2IjoiNDc3MzQzMDdlMmJjM2RkN2M3YWMyYzAyZWU4N2EwMWFjNzMzNGFmNjI5ZTdlNWVjYjRkMDA1NDhiYzFkMTM3OSJ9LHsicGF0aCI6InN5c3RlbS9zdGFydHVwLnBocCIsInNoYTI1NiI6ImM1ZWU4NmU0NTI3MWMxNDVlZTA4YjRjNzdiMGExYzcwZDVjMTA0ZDgyYWU5ZDI5NGY4MmM2MzZhMzZhZjY5NWUifSx7InBhdGgiOiJjYXRhbG9nL2NvbnRyb2xsZXIvc3RhcnR1cC9zZXR0aW5nLnBocCIsInNoYTI1NiI6IjQ3NDI1NzU5NjAxNzc5Mzk3MWUzYzJiOTkxYjNkZGQ1MjAyZjZkYjNkOTU4YzEzYmM0Mjk3N2MxMmJmOGVjZTkifSx7InBhdGgiOiJzeXN0ZW0vY29uZmlnL2RlZmF1bHQucGhwIiwic2hhMjU2IjoiYzU2ZDQxZDA4MzM2YWFjNmQ2NTJhM2I1ZWNkODdhMTFiZTA1MzM0YTIyYjRmZjkwMTdmYTAzZGJlYWY3MDg3MCJ9LHsicGF0aCI6InN5c3RlbS9jb25maWcvY2F0YWxvZy5waHAiLCJzaGEyNTYiOiJmZGM1NzEzZmE4ZTlkNzMyYTQxODM0ODU5MzBiZjRmMjkxMDRlYThjMWQ0ZjA1OTQ0N2EzMDBjMDMzYzgwNDQzIn0seyJwYXRoIjoic3lzdGVtL2ZyYW1ld29yay5waHAiLCJzaGEyNTYiOiI3YmQyMWY5YjJkOTE2N2IzZjcwYzE4OTk2ODdjMTg3ZDc2OTAyMjQ0M2QyMDkxYTNkZTkzMGE0MWMxNGI1MDg4In0seyJwYXRoIjoic3lzdGVtL2xpYnJhcnkvZGIvbXlzcWxpLnBocCIsInNoYTI1NiI6ImY0N2UxOGM5ZmZhMmIzNDBmZGQ5ZGY4YzVkNWMwNGViNGY4OTlhNmQ0NjEwYTQzMmU1Y2ZkMmViNjVmOGU3MmYifSx7InBhdGgiOiJzeXN0ZW0vbGlicmFyeS90ZW1wbGF0ZS90d2lnLnBocCIsInNoYTI1NiI6IjkyYTZhYzBhZjZmZjg4MDUzZWNhZmUwYWNkODZlMjQ5YmRkYWFiNjY0MDgwYTU0OWMwYTUwMjE3OWY1MzdhNjkifSx7InBhdGgiOiJleHRlbnNpb24vcHNfZW5oYW5jZWRfbWVhc3VyZW1lbnQvY2F0YWxvZy9jb250cm9sbGVyL2FuYWx5dGljcy9wc19lbmhhbmNlZF9tZWFzdXJlbWVudC5waHAiLCJzaGEyNTYiOiI3MjczYWMzNzMzYzMyMzNjYzUyYzg2ZjA3YWEyYTQ3MTRhZGI1NzVkNTcwZTkwMTgwOGM2MWQzNDk4ZWZmMDBlIn0seyJwYXRoIjoiZXh0ZW5zaW9uL3BzX2VuaGFuY2VkX21lYXN1cmVtZW50L2NhdGFsb2cvbW9kZWwvYW5hbHl0aWNzL3BzX2VuaGFuY2VkX21lYXN1cmVtZW50LnBocCIsInNoYTI1NiI6ImVlYTU4ZDFlMjQ3YTE2YzJjYzRiYzFlNjFiOGE1MDI4ZTQxZGM3ZWQ1NTdlOWM2NTJkNzQzODJmNzk4NGI0YjQifV0sInN0YXR1c19yZWdpc3RyeSI6W3sib3JkZXJfc3RhdHVzX2lkIjoiMSIsImxhbmd1YWdlX2lkIjoiNCIsIm5hbWUiOiLQkiDQvtCx0YDQvtCx0YbRliJ9LHsib3JkZXJfc3RhdHVzX2lkIjoiMyIsImxhbmd1YWdlX2lkIjoiNCIsIm5hbWUiOiLQlNC+0YHRgtCw0LLQu9GP0ZTRgtGM0YHRjyJ9LHsib3JkZXJfc3RhdHVzX2lkIjoiNSIsImxhbmd1YWdlX2lkIjoiNCIsIm5hbWUiOiLQntGC0YDQuNC80LDQvdC+In0seyJvcmRlcl9zdGF0dXNfaWQiOiI3IiwibGFuZ3VhZ2VfaWQiOiI0IiwibmFtZSI6ItCU0L7RgdGC0LDQstC70LXQvdC+IC0g0YfQtdC60LDRlCDQvtGC0YDQuNC80YPQstCw0YfQsCJ9LHsib3JkZXJfc3RhdHVzX2lkIjoiMTAiLCJsYW5ndWFnZV9pZCI6IjQiLCJuYW1lIjoi0J/QvtC80LjQu9C60LAifSx7Im9yZGVyX3N0YXR1c19pZCI6IjEyIiwibGFuZ3VhZ2VfaWQiOiI0IiwibmFtZSI6ItCf0L7QstC10YDQvdC10L3QviJ9LHsib3JkZXJfc3RhdHVzX2lkIjoiMTMiLCJsYW5ndWFnZV9pZCI6IjQiLCJuYW1lIjoi0J/QvtCy0LXRgNC90LXQvdC90Y8g0LrQvtGI0YLRltCyIn0seyJvcmRlcl9zdGF0dXNfaWQiOiIxNCIsImxhbmd1YWdlX2lkIjoiNCIsIm5hbWUiOiLQn9GA0L7RgtC10YDQvNGW0L3QvtCy0LDQvdC+In0seyJvcmRlcl9zdGF0dXNfaWQiOiIxNSIsImxhbmd1YWdlX2lkIjoiNCIsIm5hbWUiOiLQp9C10YDQvdC10YLQutCwICjRgdC40YHRgtC10LzQvdC40LkpIn0seyJvcmRlcl9zdGF0dXNfaWQiOiIxNyIsImxhbmd1YWdlX2lkIjoiNCIsIm5hbWUiOiLQoNC+0LfRgdGC0YDQvtGH0LrQsCDigJQg0L7Rh9GW0LrRg9GUINC60LvRltGU0L3RgtCwIn0seyJvcmRlcl9zdGF0dXNfaWQiOiIxOCIsImxhbmd1YWdlX2lkIjoiNCIsIm5hbWUiOiLQoNC+0LfRgdGC0YDQvtGH0LrQsCDigJQg0L7Rh9GW0LrRg9GUINCy0LjQtNCw0YfRliJ9LHsib3JkZXJfc3RhdHVzX2lkIjoiMTkiLCJsYW5ndWFnZV9pZCI6IjQiLCJuYW1lIjoi0KDQvtC30YHRgtGA0L7Rh9C60LAg4oCUINC+0YTQvtGA0LzQu9C10L3QviJ9LHsib3JkZXJfc3RhdHVzX2lkIjoiMjEiLCJsYW5ndWFnZV9pZCI6IjQiLCJuYW1lIjoi0KDQvtC30YHRgtGA0L7Rh9C60LAg4oCUINC/0L7QstC10YDQvdC10L3QviJ9LHsib3JkZXJfc3RhdHVzX2lkIjoiMjIiLCJsYW5ndWFnZV9pZCI6IjQiLCJuYW1lIjoi0KDQvtC30YHRgtGA0L7Rh9C60LAg4oCUINCy0ZbQtNGF0LjQu9C10L3QviJ9LHsib3JkZXJfc3RhdHVzX2lkIjoiMjMiLCJsYW5ndWFnZV9pZCI6IjQiLCJuYW1lIjoi0J/QtdGA0LXQtNC30LDQvNC+0LLQu9C10L3QvdGPIn0seyJvcmRlcl9zdGF0dXNfaWQiOiIyNCIsImxhbmd1YWdlX2lkIjoiNCIsIm5hbWUiOiLQodC60LDRgdC+0LLQsNC90L4ifV0sImFsbG93ZWRfZXZlbnRzIjpbeyJjb2RlIjoiYW5hbHl0aWNzX3BzX2VuaGFuY2VkX21lYXN1cmVtZW50IiwidHJpZ2dlciI6ImNhdGFsb2cvdmlldy9hY2NvdW50L29yZGVyX2luZm8vYmVmb3JlIiwiYWN0aW9uIjoiZXh0ZW5zaW9uL3BzX2VuaGFuY2VkX21lYXN1cmVtZW50L2FuYWx5dGljcy9wc19lbmhhbmNlZF9tZWFzdXJlbWVudC5ldmVudENhdGFsb2dWaWV3QWNjb3VudE9yZGVySW5mb0JlZm9yZSJ9XX0=',true),true);
    if (!is_array($payload) || count($payload['entries'] ?? []) !== 7) acc007_fail('runner_payload_invalid');
    $dry = false; $rollback = null;
    foreach (array_slice($argv,1) as $arg) {
        if ($arg === '--dry-run') $dry = true;
        elseif (strpos($arg,'--rollback=') === 0) $rollback = substr($arg,11);
        else acc007_fail('unknown_argument');
    }
    if ($dry && $rollback !== null) acc007_fail('conflicting_arguments');
    if ($rollback !== null) { acc007_rollback($root,$rollback,$payload); exit(0); }
    $markers = 0;
    foreach ($payload['entries'] as $entry) {
        $relative = $entry['path']; $target = acc007_path($root,$relative,!$entry['new']);
        $before = is_file($target) ? acc007_read($target) : null;
        if ($before !== null && strpos($before,$payload['marker']) !== false) $markers++;
        $plans[$relative] = ['entry'=>$entry,'target'=>$target,'before'=>$before];
    }
    if ($markers > 0) {
        if ($markers !== count($plans)) acc007_fail('partial_application_requires_review');
        foreach ($plans as $relative=>$plan) if (!hash_equals($plan['entry']['after'],hash('sha256',acc007_norm($plan['before'],$relative)))) acc007_fail('installed_content_changed:' . $relative);
        acc007_log('already_applied','yes'); acc007_log('done','ok');
        if (!$dry) @unlink(__FILE__);
        exit(0);
    }
    foreach ($plans as $relative=>&$plan) {
        $entry = $plan['entry'];
        if ($entry['new']) {
            if ($plan['before'] !== null) acc007_fail('new_asset_already_exists');
            $after = $entry['content'];
        } else {
            if (!hash_equals($entry['before'],hash('sha256',acc007_norm($plan['before'],$relative)))) acc007_fail('source_hash_mismatch:' . $relative);
            $after = acc007_edit($plan['before'],$entry['edits']);
        }
        if (!hash_equals($entry['after'],hash('sha256',acc007_norm($after,$relative)))) acc007_fail('candidate_hash_mismatch:' . $relative);
        $plan['after'] = $after;
    }
    unset($plan);
    foreach ($payload['dependencies'] as $dependency) {
        $file = acc007_path($root,$dependency['path']);
        if (!hash_equals($dependency['sha256'],hash_file('sha256',$file))) acc007_fail('dependency_hash_mismatch:' . $dependency['path']);
    }
    acc007_log('source_gate','7_targets_ok');
    acc007_db($root,$payload);
    if ($dry) { acc007_log('dry_run','ok'); acc007_log('done','ok'); exit(0); }
    $backupRoot = $root . '/_patch_backups';
    if (is_link($backupRoot)) acc007_fail('backup_root_symlink');
    if (!is_dir($backupRoot) && !mkdir($backupRoot,0755)) acc007_fail('backup_root_create_failed');
    $backup = $backupRoot . '/ACC-007_order-status-presentation_20261014-' . gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(4));
    if (!mkdir($backup,0755)) acc007_fail('backup_create_failed');
    $receipt = ['task'=>'ACC-007','date_utc'=>gmdate('c'),'files'=>[]];
    foreach ($plans as $relative=>$plan) {
        foreach (['original','candidate'] as $kind) {
            if ($kind === 'original' && $plan['before'] === null) continue;
            $file = $backup . '/' . $kind . '/' . $relative;
            if (!is_dir(dirname($file)) && !mkdir(dirname($file),0755,true)) acc007_fail('backup_directory_failed');
            acc007_atomic($file,$kind === 'original' ? $plan['before'] : $plan['after']);
        }
        $receipt['files'][$relative] = ['new'=>$plan['entry']['new'],'original_sha256'=>$plan['before'] === null ? null : hash('sha256',$plan['before']),'candidate_sha256'=>hash('sha256',$plan['after'])];
        if (substr($relative,-4)==='.php') acc007_lint($backup . '/candidate/' . $relative);
    }
    acc007_atomic($backup . '/receipt.json',json_encode($receipt,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    acc007_log('backup',str_replace(DIRECTORY_SEPARATOR,'/',substr($backup,strlen($root)+1)));
    foreach ($plans as $relative=>$plan) {
        $current = is_file($plan['target']) ? acc007_read($plan['target']) : null;
        if ($current !== $plan['before']) acc007_fail('source_changed_during_apply:' . $relative);
        $written[] = $relative;
        acc007_atomic($plan['target'],$plan['after']);
        acc007_log('changed',$relative);
    }
    foreach ($plans as $relative=>$plan) if (substr($relative,-4)==='.php') { acc007_lint($plan['target']); acc007_log('php_lint',$relative . ':ok'); }
    acc007_log('cache_refresh','required_owner_command'); acc007_log('done','ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    $restored = true;
    foreach (array_reverse($written) as $relative) {
        $plan = $plans[$relative];
        try {
            $current = is_file($plan['target']) ? acc007_read($plan['target']) : null;
            if ($current === $plan['before']) continue;
            if ($current !== $plan['after']) acc007_fail('later_change_during_restore');
            if ($plan['before'] === null) { if (!unlink($plan['target'])) acc007_fail('restore_asset_failed'); }
            else acc007_atomic($plan['target'],$plan['before']);
        } catch (Throwable $restoreError) { $restored = false; }
    }
    // Only our fixed failure codes are logged; external DB/OS exception messages stay private.
    $message = $error instanceof Acc007Failure ? $error->getMessage() : 'unexpected_error';
    if (!preg_match('~^[A-Za-z0-9_:/.-]+$~D',$message)) $message = 'unexpected_error';
    acc007_log('error',$message);
    if ($written) acc007_log('automatic_restore',$restored ? 'ok' : 'owner_review_required');
    acc007_log('done','error');
    exit(1);
}
