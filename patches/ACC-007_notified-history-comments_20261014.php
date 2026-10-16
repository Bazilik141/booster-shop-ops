<?php
/**
 * ACC-007 follow-up: show only notify=1 history comments as escaped plain text.
 * Owner QA request, 2026-10-14, overrides the earlier no-history-comments policy.
 * Requires the independently reviewed and deployed ACC-007 status presentation.
 * Three existing account files; read-only DB metadata; no mail/status/payment writes.
 * Root cause: the original getHistories SELECT omitted comments by accepted policy.
 * One entity decode reverses Request::clean; Twig escapes before newline formatting.
 * notify=1 records notification intent, not successful email delivery.
 * Usage: php ACC-007_notified-history-comments_20261014.php [--dry-run]
 * Rollback: re-upload; --rollback=_patch_backups/<exact folder printed on apply>.
 * Rollback restores the already-installed ACC-007 files, never order/history data.
 * Owner clears Twig cache after apply/rollback. PHP 8.0 compatible; C1-C7.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

function acc007c_log(string $key, string $value): void { echo $key . '=' . $value . PHP_EOL; }
class Acc007cFailure extends RuntimeException {}
function acc007c_fail(string $code): void { throw new Acc007cFailure($code); }
function acc007c_norm(string $bytes, string $path): string {
    return str_replace("\r\n", "\n", $bytes);
}
function acc007c_path(string $root, string $relative, bool $mustExist = true): string {
    if (!preg_match('~^(?:index\.php|(?:catalog/|system/|extension/[A-Za-z0-9_]+/)[A-Za-z0-9_./-]+)$~D', $relative) || strpos($relative, '..') !== false) acc007c_fail('unsafe_target');
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $parent = realpath(dirname($path));
    if (!$parent || strpos($parent . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR) !== 0 || is_link($path)) acc007c_fail('unsafe_target_parent');
    // Actual reads below are the readability gate; Windows sandbox ACL stat probes can disagree.
    if ($mustExist && !is_file($path)) acc007c_fail('target_missing:' . $relative);
    return $path;
}
function acc007c_read(string $path): string {
    $bytes = file_get_contents($path);
    if ($bytes === false) acc007c_fail('file_read_failed');
    return $bytes;
}
function acc007c_atomic(string $path, string $bytes): void {
    // Exclusive creation in the target directory; tempnam can silently fall back elsewhere.
    $temporary = dirname($path) . DIRECTORY_SEPARATOR . '.a7-' . bin2hex(random_bytes(4));
    $handle = @fopen($temporary,'xb');
    if ($handle === false) acc007c_fail('temporary_write_failed');
    try {
        if (!flock($handle,LOCK_EX) || fwrite($handle,$bytes) !== strlen($bytes) || !fflush($handle)) acc007c_fail('file_write_failed');
        fclose($handle); $handle = null;
        if (is_file($path)) @chmod($temporary, fileperms($path) & 0777);
        else @chmod($temporary, 0644);
        if (!rename($temporary, $path)) acc007c_fail('atomic_replace_failed');
    } finally {
        if (is_resource($handle)) fclose($handle);
        if (is_file($temporary)) @unlink($temporary);
    }
    if (!hash_equals(hash('sha256', $bytes), hash_file('sha256', $path))) acc007c_fail('written_hash_mismatch');
}
function acc007c_lint(string $path): void {
    $output = []; $status = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $status);
    if ($status !== 0) acc007c_fail('php_lint_failed:' . basename($path));
}
function acc007c_edit(string $source, array $edits): string {
    $sourceEol = strpos($source,"\r\n") !== false ? "\r\n" : "\n";
    foreach ($edits as $edit) {
        $pattern = '~' . str_replace("\n", '\\r?\\n', preg_quote($edit['before'], '~')) . '~';
        if (preg_match_all($pattern, $source, $matches) !== $edit['count']) acc007c_fail('anchor_count_mismatch');
        $source = preg_replace_callback($pattern, static function (array $match) use ($edit,$sourceEol): string {
            return str_replace("\n", strpos($match[0], "\r\n") !== false ? "\r\n" : $sourceEol, $edit['after']);
        }, $source);
        if (!is_string($source)) acc007c_fail('anchor_replacement_failed');
    }
    return $source;
}
function acc007c_rows($db, string $sql): array {
    $result = $db->query($sql);
    if (!$result) acc007c_fail('db_preflight_query_failed');
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $result->free();
    return $rows;
}
function acc007c_db(string $root, array $payload): void {
    if (!is_file($root . '/config.php') || is_link($root . '/config.php')) acc007c_fail('config_missing_or_linked');
    require $root . '/config.php';
    foreach (['DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PREFIX','DB_PORT'] as $constant) {
        if (!defined($constant)) acc007c_fail('db_config_incomplete');
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/D', DB_PREFIX)) acc007c_fail('db_prefix_shape');
    if (!class_exists('mysqli')) acc007c_fail('mysqli_missing');
    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
    if ($db->connect_errno) acc007c_fail('db_connection_failed');
    if (!$db->set_charset('utf8mb4')) acc007c_fail('db_charset_failed');
    try {
        $prefix = DB_PREFIX;
        $required = [
            'order' => ['order_id'=>'int','customer_id'=>'int','store_id'=>'int','order_status_id'=>'int','date_added'=>'datetime','date_modified'=>'datetime'],
            'order_history' => ['order_history_id'=>'int','order_id'=>'int','order_status_id'=>'int','date_added'=>'datetime','notify'=>'integer','comment'=>'string'],
            'order_status' => ['order_status_id'=>'int','language_id'=>'int','name'=>'varchar(32)']
        ];
        foreach ($required as $table=>$fields) {
            $map = [];
            foreach (acc007c_rows($db,'SHOW COLUMNS FROM `' . $prefix . $table . '`') as $row) $map[$row['Field']] = strtolower($row['Type']);
            foreach ($fields as $field=>$type) {
                if (!isset($map[$field]) || ($type === 'int' ? !preg_match('/^int(?:\(\d+\))?$/D',$map[$field]) : ($type === 'integer' ? !preg_match('/^(?:tinyint|smallint|mediumint|int|bigint)(?:\(\d+\))?(?: unsigned)?$/D',$map[$field]) : ($type === 'string' ? !preg_match('/^(?:tinytext|text|mediumtext|longtext|varchar\(\d+\))$/D',$map[$field]) : $map[$field] !== $type)))) acc007c_fail('schema_mismatch:' . $table . '/' . $field);
            }
        }
        foreach (['order'=>['order_id'],'order_history'=>['order_history_id'],'order_status'=>['order_status_id','language_id']] as $table=>$keys) {
            $indexes = acc007c_rows($db,'SHOW INDEX FROM `' . $prefix . $table . '` WHERE Key_name = \'PRIMARY\'');
            usort($indexes,static function(array $a,array $b):int{return (int)$a['Seq_in_index'] <=> (int)$b['Seq_in_index'];});
            if (array_column($indexes,'Column_name') !== $keys) acc007c_fail('primary_key_mismatch:' . $table);
        }
        $languages = acc007c_rows($db,'SELECT `language_id`,`code`,`status` FROM `' . $prefix . 'language` WHERE `status` = 1');
        if (count($languages) !== 1 || (string)$languages[0]['language_id'] !== '4' || $languages[0]['code'] !== 'uk-ua') acc007c_fail('language_inventory_mismatch');
        $statuses = acc007c_rows($db,'SELECT `order_status_id`,`language_id`,`name` FROM `' . $prefix . 'order_status` ORDER BY `language_id`,`order_status_id`');
        $registry = array_map(static function(array $row):array{return ['order_status_id'=>(string)$row['order_status_id'],'language_id'=>(string)$row['language_id'],'name'=>$row['name']];},$statuses);
        if ($registry !== $payload['status_registry']) acc007c_fail('status_registry_requires_review');
        $timezone = acc007c_rows($db,'SELECT `value` FROM `' . $prefix . 'setting` WHERE `store_id` = 0 AND `key` = \'config_timezone\'');
        if (count($timezone) !== 1 || $timezone[0]['value'] !== 'Europe/Kyiv') acc007c_fail('shop_timezone_requires_review');
        // Verified startup/setting.php applies this setting to BOTH PHP and the DB session.
        // No SET is needed here: the runner only reads metadata and never writes a date.
        if ((new DateTimeZone('Europe/Kyiv'))->getName() !== 'Europe/Kyiv') acc007c_fail('kyiv_timezone_unavailable');
        $routes = ['account/order_list','account/order_info','account/order_history'];
        $quoted = array_map(static function(string $v)use($db):string{return "'" . $db->real_escape_string($v) . "'";},$routes);
        if (acc007c_rows($db,'SELECT `route` FROM `' . $prefix . 'theme` WHERE `route` IN (' . implode(',',$quoted) . ') AND `status` = 1 LIMIT 1')) acc007c_fail('active_theme_override_requires_review');
        $events = acc007c_rows($db,'SELECT `code`,`trigger`,`action` FROM `' . $prefix . 'event` WHERE `status` = 1 AND (`trigger` LIKE \'%account/order%\' OR `trigger` LIKE \'%*%\')');
        $allowed = array_map(static function(array $row):string{return implode("\n",[$row['code'],$row['trigger'],$row['action']]);},$payload['allowed_events']);
        foreach ($events as $row) if (!in_array(implode("\n",[$row['code'],$row['trigger'],$row['action']]),$allowed,true)) acc007c_fail('unreviewed_active_event');
        acc007c_log('db_preflight','read_only_ok');
        acc007c_log('time_basis','verified_shop_Kyiv_wall_time');
        acc007c_log('db_mutations','none');
    } finally { $db->close(); }
}

function acc007c_rollback(string $root, string $argument, array $payload): void {
    if (!preg_match('~^_patch_backups/ACC-007_notified-history-comments_20261014-\d{8}T\d{6}Z-[a-f0-9]{8}$~D', $argument)) acc007c_fail('rollback_path_shape');
    $backup = realpath($root . '/' . $argument);
    $backupRoot = realpath($root . '/_patch_backups');
    if (!$backup || !$backupRoot || dirname($backup) !== $backupRoot || is_link($root . '/' . $argument)) acc007c_fail('rollback_path_outside_backup_root');
    $manifest = json_decode(acc007c_read($backup . '/receipt.json'), true);
    if (!is_array($manifest) || ($manifest['task'] ?? '') !== 'ACC-007' || count($manifest['files'] ?? []) !== count($payload['entries'])) acc007c_fail('rollback_receipt_shape');
    $restore = [];
    foreach ($payload['entries'] as $entry) {
        $relative = $entry['path']; $meta = $manifest['files'][$relative] ?? null;
        if (!is_array($meta) || ($meta['new'] ?? null) !== $entry['new']) acc007c_fail('rollback_receipt_entry');
        $target = acc007c_path($root, $relative, false);
        $current = is_file($target) ? acc007c_read($target) : null;
        $original = null;
        if (!$entry['new']) {
            $saved = $backup . '/original/' . $relative;
            if (is_link($saved) || !is_file($saved)) acc007c_fail('rollback_original_missing');
            $original = acc007c_read($saved);
            if (!hash_equals($meta['original_sha256'], hash('sha256',$original)) || !hash_equals($entry['before'],hash('sha256',acc007c_norm($original,$relative)))) acc007c_fail('rollback_backup_tampered');
        }
        if ($current === null && !$entry['new']) acc007c_fail('rollback_target_missing');
        if ($current !== null) {
            $state = hash('sha256',acc007c_norm($current,$relative));
            if (!hash_equals($entry['after'],$state) && !(!$entry['new'] && hash_equals($entry['before'],$state))) acc007c_fail('rollback_later_file_change:' . $relative);
        }
        $restore[$relative] = ['target'=>$target,'current'=>$current,'original'=>$original];
    }
    foreach ($restore as $relative=>$item) {
        if ($item['original'] === null) {
            if ($item['current'] !== null && !unlink($item['target'])) acc007c_fail('rollback_asset_delete_failed');
        } else {
            acc007c_atomic($item['target'],$item['original']);
            if (substr($relative,-4)==='.php') acc007c_lint($item['target']);
        }
        acc007c_log('restored',$relative);
    }
    acc007c_log('rollback','ok'); acc007c_log('done','ok');
    @unlink(__FILE__);
}

$written = []; $plans = []; $backup = null;
try {
    if (PHP_VERSION_ID < 80000) acc007c_fail('php_8_required');
    if (!function_exists('exec')) acc007c_fail('php_lint_command_unavailable');
    $root = realpath(getcwd());
    if (!$root) acc007c_fail('cwd_unavailable');
    acc007c_log('cwd',$root); acc007c_log('time',gmdate('c')); acc007c_log('php',PHP_VERSION);
    $payload = json_decode(base64_decode('eyJ0YXNrIjoiQUNDLTAwNyIsIm1hcmtlciI6IkFDQy0wMDdfTk9USUZJRURfSElTVE9SWV9DT01NRU5UU18yMDI2MTAxNCIsImFyY2hpdmVfc2hhMjU2IjoiNWJhMGU0Yjg5NWFmY2Q2ODdhN2ZmYTQwYTIxZTU0MTQyZGZlZWNkMDhmMTQyOTVkN2YwZjdkNDAwNWIxMDQ0YSIsImVudHJpZXMiOlt7InBhdGgiOiJjYXRhbG9nL21vZGVsL2FjY291bnQvb3JkZXIucGhwIiwibmV3IjpmYWxzZSwiYmVmb3JlIjoiODEyNjM1OTJmOTA0NDJmMzkyMTM0MzRmYjFkODMxMzExYzU4YTI1MDZhZWZjYmNiNmFmNjEwY2ZiNGU3NTlkNyIsImFmdGVyIjoiMjg2MmZkOWQyYTVmNzU2ZmQ1YzU4ZDJlY2ZjNzFkZjcwNTc5OWEzYmYzZjJjZWYyNGE2OWFmODY3YzEyNzkzMCIsImVkaXRzIjpbeyJiZWZvcmUiOiJcdFx0JHF1ZXJ5ID0gJHRoaXMtPmRiLT5xdWVyeShcIlNFTEVDVCBgb2hgLmBvcmRlcl9oaXN0b3J5X2lkYCxgb2hgLmBvcmRlcl9zdGF0dXNfaWRgLGBvaGAuYGRhdGVfYWRkZWRgIEZST00gYFwiIC4gREJfUFJFRklYIC4gXCJvcmRlcl9oaXN0b3J5YCBgb2hgIElOTkVSIEpPSU4gYFwiIC4gREJfUFJFRklYIC4gXCJvcmRlcmAgYG9gIE9OIChgb2AuYG9yZGVyX2lkYCA9IGBvaGAuYG9yZGVyX2lkYCkgV0hFUkUgYG9oYC5gb3JkZXJfaWRgID0gJ1wiIC4gKGludCkkb3JkZXJfaWQgLiBcIicgQU5EIFwiIC4gJHRoaXMtPnB1YmxpY09yZGVyU2NvcGUoKSAuIFwiIEFORCBcIiAuIEFjYzAwN09yZGVyU3RhdHVzOjp2aXNpYmxlU3FsKCdgb2hgLmBvcmRlcl9zdGF0dXNfaWRgJykgLiBcIiBPUkRFUiBCWSBgb2hgLmBkYXRlX2FkZGVkYCBBU0MsIGBvaGAuYG9yZGVyX2hpc3RvcnlfaWRgIEFTQyBMSU1JVCBcIiAuICRzdGFydCAuIFwiLFwiIC4gJGxpbWl0KTsiLCJhZnRlciI6Ilx0XHQvLyBBQ0MtMDA3X05PVElGSUVEX0hJU1RPUllfQ09NTUVOVFNfMjAyNjEwMTQ6IG5ldmVyIHNlbGVjdCB1bm5vdGlmaWVkIGhpc3RvcnkgY29tbWVudHMuXG5cdFx0JHF1ZXJ5ID0gJHRoaXMtPmRiLT5xdWVyeShcIlNFTEVDVCBgb2hgLmBvcmRlcl9oaXN0b3J5X2lkYCxgb2hgLmBvcmRlcl9zdGF0dXNfaWRgLGBvaGAuYGRhdGVfYWRkZWRgLENBU0UgV0hFTiBgb2hgLmBub3RpZnlgID0gMSBUSEVOIGBvaGAuYGNvbW1lbnRgIEVMU0UgJycgRU5EIEFTIGBic19jdXN0b21lcl9jb21tZW50YCBGUk9NIGBcIiAuIERCX1BSRUZJWCAuIFwib3JkZXJfaGlzdG9yeWAgYG9oYCBJTk5FUiBKT0lOIGBcIiAuIERCX1BSRUZJWCAuIFwib3JkZXJgIGBvYCBPTiAoYG9gLmBvcmRlcl9pZGAgPSBgb2hgLmBvcmRlcl9pZGApIFdIRVJFIGBvaGAuYG9yZGVyX2lkYCA9ICdcIiAuIChpbnQpJG9yZGVyX2lkIC4gXCInIEFORCBcIiAuICR0aGlzLT5wdWJsaWNPcmRlclNjb3BlKCkgLiBcIiBBTkQgXCIgLiBBY2MwMDdPcmRlclN0YXR1czo6dmlzaWJsZVNxbCgnYG9oYC5gb3JkZXJfc3RhdHVzX2lkYCcpIC4gXCIgT1JERVIgQlkgYG9oYC5gZGF0ZV9hZGRlZGAgQVNDLCBgb2hgLmBvcmRlcl9oaXN0b3J5X2lkYCBBU0MgTElNSVQgXCIgLiAkc3RhcnQgLiBcIixcIiAuICRsaW1pdCk7IiwiY291bnQiOjF9XX0seyJwYXRoIjoiY2F0YWxvZy9jb250cm9sbGVyL2FjY291bnQvb3JkZXIucGhwIiwibmV3IjpmYWxzZSwiYmVmb3JlIjoiNzIyNDAwZDc4YTJhZWJlNjcyNmYyNzM5MmM2ZTJkMWExODExNmFmNzY0ZWNlNTk0NWI4MTAwMzUwOWM4NGY1OSIsImFmdGVyIjoiMDVkN2RlNGVmNjk1ODVjYjU0MjJhZjlhMGJhZGM0ZjBmYTViZWY0Y2QwYjMzY2JjMjcyYTM5YmQwMDA3ODM1MiIsImVkaXRzIjpbeyJiZWZvcmUiOiJcdFx0XHRcdCdjb21tZW50JyA9PiAkYnNfc3RhdHVzWydtZXNzYWdlJ10sIiwiYWZ0ZXIiOiJcdFx0XHRcdCdjb21tZW50JyA9PiAkYnNfc3RhdHVzWydtZXNzYWdlJ10sXG5cdFx0XHRcdC8vIEFDQy0wMDdfTk9USUZJRURfSElTVE9SWV9DT01NRU5UU18yMDI2MTAxNDogUmVxdWVzdDo6Y2xlYW4gZW5jb2RlcyBhZG1pbiB0ZXh0OyBkZWNvZGUgb25jZSwgZXNjYXBlIGluIFR3aWcuXG5cdFx0XHRcdCdic19jdXN0b21lcl9jb21tZW50JyA9PiB0cmltKGh0bWxfZW50aXR5X2RlY29kZSgoc3RyaW5nKSgkcmVzdWx0Wydic19jdXN0b21lcl9jb21tZW50J10gPz8gJycpLCBFTlRfUVVPVEVTIHwgRU5UX0hUTUw1LCAnVVRGLTgnKSksIiwiY291bnQiOjF9XX0seyJwYXRoIjoiY2F0YWxvZy92aWV3L3RlbXBsYXRlL2FjY291bnQvb3JkZXJfaGlzdG9yeS50d2lnIiwibmV3IjpmYWxzZSwiYmVmb3JlIjoiMmMxYmFmYmVmODk3MDU5Y2QzNGQ4ZWVkYTQ2ZGVhZjY1ZTI5YTNmZjY5MzQ1NjM0OTZkM2Y3MWJjYWNhZDJmZSIsImFmdGVyIjoiMWM0MmE0ZTNkZTM4M2UzZTk0YTJmZGExNDgzODUzNDk5OTQ3MGY2YWMzZWE0ZGU4ZDdjMGUxNGVkZDA1MmI4ZCIsImVkaXRzIjpbeyJiZWZvcmUiOiJ7IyBBQ0MtMDA3X09SREVSX1NUQVRVU19QUkVTRU5UQVRJT05fMjAyNjEwMTQgI30iLCJhZnRlciI6InsjIEFDQy0wMDdfT1JERVJfU1RBVFVTX1BSRVNFTlRBVElPTl8yMDI2MTAxNCAjfVxueyMgQUNDLTAwN19OT1RJRklFRF9ISVNUT1JZX0NPTU1FTlRTXzIwMjYxMDE0ICN9IiwiY291bnQiOjF9LHsiYmVmb3JlIjoiICAgICAgICAgICAgPHRkPnt7IGhpc3RvcnkuY29tbWVudCB9fTwvdGQ+IiwiYWZ0ZXIiOiIgICAgICAgICAgICA8dGQ+XG4gICAgICAgICAgICAgIHt7IGhpc3RvcnkuY29tbWVudCB9fVxuICAgICAgICAgICAgICB7JSBpZiBoaXN0b3J5LmJzX2N1c3RvbWVyX2NvbW1lbnQgaXMgbm90IGVtcHR5ICV9XG4gICAgICAgICAgICAgICAgPGRpdiBjbGFzcz1cIm10LTIgdGV4dC1icmVha1wiPjxzdHJvbmc+e3sgY29sdW1uX2NvbW1lbnQgfX06PC9zdHJvbmc+PGJyPnt7IGhpc3RvcnkuYnNfY3VzdG9tZXJfY29tbWVudHxlc2NhcGUoJ2h0bWwnKXxubDJiciB9fTwvZGl2PlxuICAgICAgICAgICAgICB7JSBlbmRpZiAlfVxuICAgICAgICAgICAgPC90ZD4iLCJjb3VudCI6MX1dfV0sImRlcGVuZGVuY2llcyI6W3sicGF0aCI6InN5c3RlbS9saWJyYXJ5L2FjYzAwN19vcmRlcl9zdGF0dXMucGhwIiwic2hhMjU2IjoiOTA4NmVmZjkxN2VhMjAwM2JkYWQ3ODZkMGRkYWE2ODBmMDZjYjcwMmI5YWYxNTY3YzMwYzM5MTZlNmNhNjg4NSJ9LHsicGF0aCI6ImV4dGVuc2lvbi91a3JhaW5pYW4vY2F0YWxvZy9sYW5ndWFnZS91ay11YS9hY2NvdW50L29yZGVyLnBocCIsInNoYTI1NiI6ImJkMjVhYmZlMDI0MDgzNDdjOWVmMTc3MGU2MTM0ZGYxMTEwMWE4Yzk4ZmNjNmU0ZDBkZWRmYmRlZTg0MWRjZWEifSx7InBhdGgiOiJzeXN0ZW0vbGlicmFyeS9yZXF1ZXN0LnBocCIsInNoYTI1NiI6IjQ4M2MxYTQ0ZWMwYzdjOWQ2OTY5NTcwZDc2NmMxY2Q4YzQwZTc2NDZkMTlkYTBmN2ExMWQwZGRhNzk3OWY1MmMifSx7InBhdGgiOiJjYXRhbG9nL21vZGVsL2NoZWNrb3V0L29yZGVyLnBocCIsInNoYTI1NiI6IjAzMjRjMjIxY2I3NWNiMWFhYTA0ZDhiMjhiZTU4NjViMjk1YWZmM2I3MjcxMzk3OTFlMDM0MTMzNDJjM2E4MDAifSx7InBhdGgiOiJjYXRhbG9nL2NvbnRyb2xsZXIvbWFpbC9vcmRlci5waHAiLCJzaGEyNTYiOiJhMDE0ODlmYzljYTA3ZTM2NTc5ZTFhZWU3YTNkODM4Mzg3ZmVkM2E0ZmE3MDMzNWJhZTJhYzdmYWY1OGExNThmIn1dLCJzdGF0dXNfcmVnaXN0cnkiOlt7Im9yZGVyX3N0YXR1c19pZCI6IjEiLCJsYW5ndWFnZV9pZCI6IjQiLCJuYW1lIjoi0JIg0L7QsdGA0L7QsdGG0ZYifSx7Im9yZGVyX3N0YXR1c19pZCI6IjMiLCJsYW5ndWFnZV9pZCI6IjQiLCJuYW1lIjoi0JTQvtGB0YLQsNCy0LvRj9GU0YLRjNGB0Y8ifSx7Im9yZGVyX3N0YXR1c19pZCI6IjUiLCJsYW5ndWFnZV9pZCI6IjQiLCJuYW1lIjoi0J7RgtGA0LjQvNCw0L3QviJ9LHsib3JkZXJfc3RhdHVzX2lkIjoiNyIsImxhbmd1YWdlX2lkIjoiNCIsIm5hbWUiOiLQlNC+0YHRgtCw0LLQu9C10L3QviAtINGH0LXQutCw0ZQg0L7RgtGA0LjQvNGD0LLQsNGH0LAifSx7Im9yZGVyX3N0YXR1c19pZCI6IjEwIiwibGFuZ3VhZ2VfaWQiOiI0IiwibmFtZSI6ItCf0L7QvNC40LvQutCwIn0seyJvcmRlcl9zdGF0dXNfaWQiOiIxMiIsImxhbmd1YWdlX2lkIjoiNCIsIm5hbWUiOiLQn9C+0LLQtdGA0L3QtdC90L4ifSx7Im9yZGVyX3N0YXR1c19pZCI6IjEzIiwibGFuZ3VhZ2VfaWQiOiI0IiwibmFtZSI6ItCf0L7QstC10YDQvdC10L3QvdGPINC60L7RiNGC0ZbQsiJ9LHsib3JkZXJfc3RhdHVzX2lkIjoiMTQiLCJsYW5ndWFnZV9pZCI6IjQiLCJuYW1lIjoi0J/RgNC+0YLQtdGA0LzRltC90L7QstCw0L3QviJ9LHsib3JkZXJfc3RhdHVzX2lkIjoiMTUiLCJsYW5ndWFnZV9pZCI6IjQiLCJuYW1lIjoi0KfQtdGA0L3QtdGC0LrQsCAo0YHQuNGB0YLQtdC80L3QuNC5KSJ9LHsib3JkZXJfc3RhdHVzX2lkIjoiMTciLCJsYW5ndWFnZV9pZCI6IjQiLCJuYW1lIjoi0KDQvtC30YHRgtGA0L7Rh9C60LAg4oCUINC+0YfRltC60YPRlCDQutC70ZbRlNC90YLQsCJ9LHsib3JkZXJfc3RhdHVzX2lkIjoiMTgiLCJsYW5ndWFnZV9pZCI6IjQiLCJuYW1lIjoi0KDQvtC30YHRgtGA0L7Rh9C60LAg4oCUINC+0YfRltC60YPRlCDQstC40LTQsNGH0ZYifSx7Im9yZGVyX3N0YXR1c19pZCI6IjE5IiwibGFuZ3VhZ2VfaWQiOiI0IiwibmFtZSI6ItCg0L7Qt9GB0YLRgNC+0YfQutCwIOKAlCDQvtGE0L7RgNC80LvQtdC90L4ifSx7Im9yZGVyX3N0YXR1c19pZCI6IjIxIiwibGFuZ3VhZ2VfaWQiOiI0IiwibmFtZSI6ItCg0L7Qt9GB0YLRgNC+0YfQutCwIOKAlCDQv9C+0LLQtdGA0L3QtdC90L4ifSx7Im9yZGVyX3N0YXR1c19pZCI6IjIyIiwibGFuZ3VhZ2VfaWQiOiI0IiwibmFtZSI6ItCg0L7Qt9GB0YLRgNC+0YfQutCwIOKAlCDQstGW0LTRhdC40LvQtdC90L4ifSx7Im9yZGVyX3N0YXR1c19pZCI6IjIzIiwibGFuZ3VhZ2VfaWQiOiI0IiwibmFtZSI6ItCf0LXRgNC10LTQt9Cw0LzQvtCy0LvQtdC90L3RjyJ9LHsib3JkZXJfc3RhdHVzX2lkIjoiMjQiLCJsYW5ndWFnZV9pZCI6IjQiLCJuYW1lIjoi0KHQutCw0YHQvtCy0LDQvdC+In1dLCJhbGxvd2VkX2V2ZW50cyI6W3siY29kZSI6ImFuYWx5dGljc19wc19lbmhhbmNlZF9tZWFzdXJlbWVudCIsInRyaWdnZXIiOiJjYXRhbG9nL3ZpZXcvYWNjb3VudC9vcmRlcl9pbmZvL2JlZm9yZSIsImFjdGlvbiI6ImV4dGVuc2lvbi9wc19lbmhhbmNlZF9tZWFzdXJlbWVudC9hbmFseXRpY3MvcHNfZW5oYW5jZWRfbWVhc3VyZW1lbnQuZXZlbnRDYXRhbG9nVmlld0FjY291bnRPcmRlckluZm9CZWZvcmUifV19',true),true);
    if (!is_array($payload) || count($payload['entries'] ?? []) !== 3) acc007c_fail('runner_payload_invalid');
    $dry = false; $rollback = null;
    foreach (array_slice($argv,1) as $arg) {
        if ($arg === '--dry-run') $dry = true;
        elseif (strpos($arg,'--rollback=') === 0) $rollback = substr($arg,11);
        else acc007c_fail('unknown_argument');
    }
    if ($dry && $rollback !== null) acc007c_fail('conflicting_arguments');
    if ($rollback !== null) { acc007c_rollback($root,$rollback,$payload); exit(0); }
    $markers = 0;
    foreach ($payload['entries'] as $entry) {
        $relative = $entry['path']; $target = acc007c_path($root,$relative,!$entry['new']);
        $before = is_file($target) ? acc007c_read($target) : null;
        if ($before !== null && strpos($before,$payload['marker']) !== false) $markers++;
        $plans[$relative] = ['entry'=>$entry,'target'=>$target,'before'=>$before];
    }
    if ($markers > 0) {
        if ($markers !== count($plans)) acc007c_fail('partial_application_requires_review');
        foreach ($plans as $relative=>$plan) if (!hash_equals($plan['entry']['after'],hash('sha256',acc007c_norm($plan['before'],$relative)))) acc007c_fail('installed_content_changed:' . $relative);
        acc007c_log('already_applied','yes'); acc007c_log('done','ok');
        if (!$dry) @unlink(__FILE__);
        exit(0);
    }
    foreach ($plans as $relative=>&$plan) {
        $entry = $plan['entry'];
        if ($entry['new']) {
            if ($plan['before'] !== null) acc007c_fail('new_asset_already_exists');
            $after = $entry['content'];
        } else {
            if (!hash_equals($entry['before'],hash('sha256',acc007c_norm($plan['before'],$relative)))) acc007c_fail('source_hash_mismatch:' . $relative);
            $after = acc007c_edit($plan['before'],$entry['edits']);
        }
        if (!hash_equals($entry['after'],hash('sha256',acc007c_norm($after,$relative)))) acc007c_fail('candidate_hash_mismatch:' . $relative);
        $plan['after'] = $after;
    }
    unset($plan);
    foreach ($payload['dependencies'] as $dependency) {
        $file = acc007c_path($root,$dependency['path']);
        if (!hash_equals($dependency['sha256'],hash_file('sha256',$file))) acc007c_fail('dependency_hash_mismatch:' . $dependency['path']);
    }
    acc007c_log('source_gate','3_targets_ok');
    acc007c_db($root,$payload);
    if ($dry) { acc007c_log('dry_run','ok'); acc007c_log('done','ok'); exit(0); }
    $backupRoot = $root . '/_patch_backups';
    if (is_link($backupRoot)) acc007c_fail('backup_root_symlink');
    if (!is_dir($backupRoot) && !mkdir($backupRoot,0755)) acc007c_fail('backup_root_create_failed');
    $backup = $backupRoot . '/ACC-007_notified-history-comments_20261014-' . gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(4));
    if (!mkdir($backup,0755)) acc007c_fail('backup_create_failed');
    $receipt = ['task'=>'ACC-007','date_utc'=>gmdate('c'),'files'=>[]];
    foreach ($plans as $relative=>$plan) {
        foreach (['original','candidate'] as $kind) {
            if ($kind === 'original' && $plan['before'] === null) continue;
            $file = $backup . '/' . $kind . '/' . $relative;
            if (!is_dir(dirname($file)) && !mkdir(dirname($file),0755,true)) acc007c_fail('backup_directory_failed');
            acc007c_atomic($file,$kind === 'original' ? $plan['before'] : $plan['after']);
        }
        $receipt['files'][$relative] = ['new'=>$plan['entry']['new'],'original_sha256'=>$plan['before'] === null ? null : hash('sha256',$plan['before']),'candidate_sha256'=>hash('sha256',$plan['after'])];
        if (substr($relative,-4)==='.php') acc007c_lint($backup . '/candidate/' . $relative);
    }
    acc007c_atomic($backup . '/receipt.json',json_encode($receipt,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    acc007c_log('backup',str_replace(DIRECTORY_SEPARATOR,'/',substr($backup,strlen($root)+1)));
    foreach ($plans as $relative=>$plan) {
        $current = is_file($plan['target']) ? acc007c_read($plan['target']) : null;
        if ($current !== $plan['before']) acc007c_fail('source_changed_during_apply:' . $relative);
        $written[] = $relative;
        acc007c_atomic($plan['target'],$plan['after']);
        acc007c_log('changed',$relative);
    }
    foreach ($plans as $relative=>$plan) if (substr($relative,-4)==='.php') { acc007c_lint($plan['target']); acc007c_log('php_lint',$relative . ':ok'); }
    acc007c_log('cache_refresh','required_owner_command'); acc007c_log('done','ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    $restored = true;
    foreach (array_reverse($written) as $relative) {
        $plan = $plans[$relative];
        try {
            $current = is_file($plan['target']) ? acc007c_read($plan['target']) : null;
            if ($current === $plan['before']) continue;
            if ($current !== $plan['after']) acc007c_fail('later_change_during_restore');
            if ($plan['before'] === null) { if (!unlink($plan['target'])) acc007c_fail('restore_asset_failed'); }
            else acc007c_atomic($plan['target'],$plan['before']);
        } catch (Throwable $restoreError) { $restored = false; }
    }
    // Only our fixed failure codes are logged; external DB/OS exception messages stay private.
    $message = $error instanceof Acc007cFailure ? $error->getMessage() : 'unexpected_error';
    if (!preg_match('~^[A-Za-z0-9_:/.-]+$~D',$message)) $message = 'unexpected_error';
    acc007c_log('error',$message);
    if ($written) acc007c_log('automatic_restore',$restored ? 'ok' : 'owner_review_required');
    acc007c_log('done','error');
    exit(1);
}
