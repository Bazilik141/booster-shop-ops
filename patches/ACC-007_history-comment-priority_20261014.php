<?php
/**
 * ACC-007 follow-up: notified nonempty comment replaces the standard status message.
 * Owner request 2026-10-14; current Twig SHA confirmed by owner sha256sum.
 * Exactly one existing Twig target; backend/notify policy, mail, status and DB data unchanged.
 * Root cause: history.comment was printed unconditionally before the comment branch.
 * Move that print into else; preserve explicit HTML escape then nl2br and existing classes.
 * Usage: php ACC-007_history-comment-priority_20261014.php [--dry-run]
 * Rollback: re-upload; --rollback=_patch_backups/<exact folder printed on apply>.
 * Restores the notified-comments template, not any earlier backend or DB state.
 * Local ArrayLoader compile/render verifies Twig syntax. No production PHP file is edited.
 * PHP 8.0 compatible runner; read-only metadata/dependency preflight; C1-C7.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

function acc007p_log(string $key, string $value): void { echo $key . '=' . $value . PHP_EOL; }
class Acc007pFailure extends RuntimeException {}
function acc007p_fail(string $code): void { throw new Acc007pFailure($code); }
function acc007p_norm(string $bytes, string $path): string {
    return str_replace("\r\n", "\n", $bytes);
}
function acc007p_path(string $root, string $relative, bool $mustExist = true): string {
    if (!preg_match('~^(?:index\.php|(?:catalog/|system/|extension/[A-Za-z0-9_]+/)[A-Za-z0-9_./-]+)$~D', $relative) || strpos($relative, '..') !== false) acc007p_fail('unsafe_target');
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $parent = realpath(dirname($path));
    if (!$parent || strpos($parent . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR) !== 0 || is_link($path)) acc007p_fail('unsafe_target_parent');
    // Actual reads below are the readability gate; Windows sandbox ACL stat probes can disagree.
    if ($mustExist && !is_file($path)) acc007p_fail('target_missing:' . $relative);
    return $path;
}
function acc007p_read(string $path): string {
    $bytes = file_get_contents($path);
    if ($bytes === false) acc007p_fail('file_read_failed');
    return $bytes;
}
function acc007p_atomic(string $path, string $bytes): void {
    // Exclusive creation in the target directory; tempnam can silently fall back elsewhere.
    $temporary = dirname($path) . DIRECTORY_SEPARATOR . '.a7-' . bin2hex(random_bytes(4));
    $handle = @fopen($temporary,'xb');
    if ($handle === false) acc007p_fail('temporary_write_failed');
    try {
        if (!flock($handle,LOCK_EX) || fwrite($handle,$bytes) !== strlen($bytes) || !fflush($handle)) acc007p_fail('file_write_failed');
        fclose($handle); $handle = null;
        if (is_file($path)) @chmod($temporary, fileperms($path) & 0777);
        else @chmod($temporary, 0644);
        if (!rename($temporary, $path)) acc007p_fail('atomic_replace_failed');
    } finally {
        if (is_resource($handle)) fclose($handle);
        if (is_file($temporary)) @unlink($temporary);
    }
    if (!hash_equals(hash('sha256', $bytes), hash_file('sha256', $path))) acc007p_fail('written_hash_mismatch');
}
function acc007p_lint(string $path): void {
    $output = []; $status = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $status);
    if ($status !== 0) acc007p_fail('php_lint_failed:' . basename($path));
}
function acc007p_edit(string $source, array $edits): string {
    $sourceEol = strpos($source,"\r\n") !== false ? "\r\n" : "\n";
    foreach ($edits as $edit) {
        $pattern = '~' . str_replace("\n", '\\r?\\n', preg_quote($edit['before'], '~')) . '~';
        if (preg_match_all($pattern, $source, $matches) !== $edit['count']) acc007p_fail('anchor_count_mismatch');
        $source = preg_replace_callback($pattern, static function (array $match) use ($edit,$sourceEol): string {
            return str_replace("\n", strpos($match[0], "\r\n") !== false ? "\r\n" : $sourceEol, $edit['after']);
        }, $source);
        if (!is_string($source)) acc007p_fail('anchor_replacement_failed');
    }
    return $source;
}
function acc007p_rows($db, string $sql): array {
    $result = $db->query($sql);
    if (!$result) acc007p_fail('db_preflight_query_failed');
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $result->free();
    return $rows;
}
function acc007p_db(string $root, array $payload): void {
    if (!is_file($root . '/config.php') || is_link($root . '/config.php')) acc007p_fail('config_missing_or_linked');
    require $root . '/config.php';
    foreach (['DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PREFIX','DB_PORT'] as $constant) {
        if (!defined($constant)) acc007p_fail('db_config_incomplete');
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/D', DB_PREFIX)) acc007p_fail('db_prefix_shape');
    if (!class_exists('mysqli')) acc007p_fail('mysqli_missing');
    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
    if ($db->connect_errno) acc007p_fail('db_connection_failed');
    if (!$db->set_charset('utf8mb4')) acc007p_fail('db_charset_failed');
    try {
        $prefix = DB_PREFIX;
        $required = [
            'order' => ['order_id'=>'int','customer_id'=>'int','store_id'=>'int','order_status_id'=>'int','date_added'=>'datetime','date_modified'=>'datetime'],
            'order_history' => ['order_history_id'=>'int','order_id'=>'int','order_status_id'=>'int','date_added'=>'datetime','notify'=>'integer','comment'=>'string'],
            'order_status' => ['order_status_id'=>'int','language_id'=>'int','name'=>'varchar(32)']
        ];
        foreach ($required as $table=>$fields) {
            $map = [];
            foreach (acc007p_rows($db,'SHOW COLUMNS FROM `' . $prefix . $table . '`') as $row) $map[$row['Field']] = strtolower($row['Type']);
            foreach ($fields as $field=>$type) {
                if (!isset($map[$field]) || ($type === 'int' ? !preg_match('/^int(?:\(\d+\))?$/D',$map[$field]) : ($type === 'integer' ? !preg_match('/^(?:tinyint|smallint|mediumint|int|bigint)(?:\(\d+\))?(?: unsigned)?$/D',$map[$field]) : ($type === 'string' ? !preg_match('/^(?:tinytext|text|mediumtext|longtext|varchar\(\d+\))$/D',$map[$field]) : $map[$field] !== $type)))) acc007p_fail('schema_mismatch:' . $table . '/' . $field);
            }
        }
        foreach (['order'=>['order_id'],'order_history'=>['order_history_id'],'order_status'=>['order_status_id','language_id']] as $table=>$keys) {
            $indexes = acc007p_rows($db,'SHOW INDEX FROM `' . $prefix . $table . '` WHERE Key_name = \'PRIMARY\'');
            usort($indexes,static function(array $a,array $b):int{return (int)$a['Seq_in_index'] <=> (int)$b['Seq_in_index'];});
            if (array_column($indexes,'Column_name') !== $keys) acc007p_fail('primary_key_mismatch:' . $table);
        }
        $languages = acc007p_rows($db,'SELECT `language_id`,`code`,`status` FROM `' . $prefix . 'language` WHERE `status` = 1');
        if (count($languages) !== 1 || (string)$languages[0]['language_id'] !== '4' || $languages[0]['code'] !== 'uk-ua') acc007p_fail('language_inventory_mismatch');
        $statuses = acc007p_rows($db,'SELECT `order_status_id`,`language_id`,`name` FROM `' . $prefix . 'order_status` ORDER BY `language_id`,`order_status_id`');
        $registry = array_map(static function(array $row):array{return ['order_status_id'=>(string)$row['order_status_id'],'language_id'=>(string)$row['language_id'],'name'=>$row['name']];},$statuses);
        if ($registry !== $payload['status_registry']) acc007p_fail('status_registry_requires_review');
        $timezone = acc007p_rows($db,'SELECT `value` FROM `' . $prefix . 'setting` WHERE `store_id` = 0 AND `key` = \'config_timezone\'');
        if (count($timezone) !== 1 || $timezone[0]['value'] !== 'Europe/Kyiv') acc007p_fail('shop_timezone_requires_review');
        // Verified startup/setting.php applies this setting to BOTH PHP and the DB session.
        // No SET is needed here: the runner only reads metadata and never writes a date.
        if ((new DateTimeZone('Europe/Kyiv'))->getName() !== 'Europe/Kyiv') acc007p_fail('kyiv_timezone_unavailable');
        $routes = ['account/order_history'];
        $quoted = array_map(static function(string $v)use($db):string{return "'" . $db->real_escape_string($v) . "'";},$routes);
        if (acc007p_rows($db,'SELECT `route` FROM `' . $prefix . 'theme` WHERE `route` IN (' . implode(',',$quoted) . ') AND `status` = 1 LIMIT 1')) acc007p_fail('active_theme_override_requires_review');
        $events = acc007p_rows($db,'SELECT `code`,`trigger`,`action` FROM `' . $prefix . 'event` WHERE `status` = 1 AND (`trigger` LIKE \'%account/order%\' OR `trigger` LIKE \'%*%\')');
        $allowed = array_map(static function(array $row):string{return implode("\n",[$row['code'],$row['trigger'],$row['action']]);},$payload['allowed_events']);
        foreach ($events as $row) if (!in_array(implode("\n",[$row['code'],$row['trigger'],$row['action']]),$allowed,true)) acc007p_fail('unreviewed_active_event');
        acc007p_log('db_preflight','read_only_ok');
        acc007p_log('time_basis','verified_shop_Kyiv_wall_time');
        acc007p_log('db_mutations','none');
    } finally { $db->close(); }
}

function acc007p_rollback(string $root, string $argument, array $payload): void {
    if (!preg_match('~^_patch_backups/ACC-007_history-comment-priority_20261014-\d{8}T\d{6}Z-[a-f0-9]{8}$~D', $argument)) acc007p_fail('rollback_path_shape');
    $backup = realpath($root . '/' . $argument);
    $backupRoot = realpath($root . '/_patch_backups');
    if (!$backup || !$backupRoot || dirname($backup) !== $backupRoot || is_link($root . '/' . $argument)) acc007p_fail('rollback_path_outside_backup_root');
    $manifest = json_decode(acc007p_read($backup . '/receipt.json'), true);
    if (!is_array($manifest) || ($manifest['task'] ?? '') !== 'ACC-007' || count($manifest['files'] ?? []) !== count($payload['entries'])) acc007p_fail('rollback_receipt_shape');
    $restore = [];
    foreach ($payload['entries'] as $entry) {
        $relative = $entry['path']; $meta = $manifest['files'][$relative] ?? null;
        if (!is_array($meta) || ($meta['new'] ?? null) !== $entry['new']) acc007p_fail('rollback_receipt_entry');
        $target = acc007p_path($root, $relative, false);
        $current = is_file($target) ? acc007p_read($target) : null;
        $original = null;
        if (!$entry['new']) {
            $saved = $backup . '/original/' . $relative;
            if (is_link($saved) || !is_file($saved)) acc007p_fail('rollback_original_missing');
            $original = acc007p_read($saved);
            if (!hash_equals($meta['original_sha256'], hash('sha256',$original)) || !hash_equals($entry['before'],hash('sha256',acc007p_norm($original,$relative)))) acc007p_fail('rollback_backup_tampered');
        }
        if ($current === null && !$entry['new']) acc007p_fail('rollback_target_missing');
        if ($current !== null) {
            $state = hash('sha256',acc007p_norm($current,$relative));
            if (!hash_equals($entry['after'],$state) && !(!$entry['new'] && hash_equals($entry['before'],$state))) acc007p_fail('rollback_later_file_change:' . $relative);
        }
        $restore[$relative] = ['target'=>$target,'current'=>$current,'original'=>$original];
    }
    foreach ($restore as $relative=>$item) {
        if ($item['original'] === null) {
            if ($item['current'] !== null && !unlink($item['target'])) acc007p_fail('rollback_asset_delete_failed');
        } else {
            acc007p_atomic($item['target'],$item['original']);
            if (substr($relative,-4)==='.php') acc007p_lint($item['target']);
        }
        acc007p_log('restored',$relative);
    }
    acc007p_log('rollback','ok'); acc007p_log('done','ok');
    @unlink(__FILE__);
}

$written = []; $plans = []; $backup = null;
try {
    if (PHP_VERSION_ID < 80000) acc007p_fail('php_8_required');
    if (!function_exists('exec')) acc007p_fail('php_lint_command_unavailable');
    $root = realpath(getcwd());
    if (!$root) acc007p_fail('cwd_unavailable');
    acc007p_log('cwd',$root); acc007p_log('time',gmdate('c')); acc007p_log('php',PHP_VERSION);
    $payload = json_decode(base64_decode('eyJ0YXNrIjoiQUNDLTAwNyIsIm1hcmtlciI6IkFDQy0wMDdfSElTVE9SWV9DT01NRU5UX1BSSU9SSVRZXzIwMjYxMDE0IiwiZW50cmllcyI6W3sicGF0aCI6ImNhdGFsb2cvdmlldy90ZW1wbGF0ZS9hY2NvdW50L29yZGVyX2hpc3RvcnkudHdpZyIsIm5ldyI6ZmFsc2UsImJlZm9yZSI6IjFjNDJhNGUzZGUzODNlM2U5NGEyZmRhMTQ4Mzg1MzQ5OTk0NzBmNmFjM2VhNGRlOGQ3YzBlMTRlZGQwNTJiOGQiLCJhZnRlciI6IjMyMmQ0NThiOTBlNzBjOTg3MDgyNDMxMDc0ZTkyMTVhZmQ5NGI4YmRiZTYyOTg0OTFiNzdmNDcyYjU4YWJkNjIiLCJlZGl0cyI6W3siYmVmb3JlIjoieyMgQUNDLTAwN19OT1RJRklFRF9ISVNUT1JZX0NPTU1FTlRTXzIwMjYxMDE0ICN9IiwiYWZ0ZXIiOiJ7IyBBQ0MtMDA3X05PVElGSUVEX0hJU1RPUllfQ09NTUVOVFNfMjAyNjEwMTQgI31cbnsjIEFDQy0wMDdfSElTVE9SWV9DT01NRU5UX1BSSU9SSVRZXzIwMjYxMDE0ICN9IiwiY291bnQiOjF9LHsiYmVmb3JlIjoiICAgICAgICAgICAgICB7eyBoaXN0b3J5LmNvbW1lbnQgfX1cbiAgICAgICAgICAgICAgeyUgaWYgaGlzdG9yeS5ic19jdXN0b21lcl9jb21tZW50IGlzIG5vdCBlbXB0eSAlfVxuICAgICAgICAgICAgICAgIDxkaXYgY2xhc3M9XCJtdC0yIHRleHQtYnJlYWtcIj48c3Ryb25nPnt7IGNvbHVtbl9jb21tZW50IH19Ojwvc3Ryb25nPjxicj57eyBoaXN0b3J5LmJzX2N1c3RvbWVyX2NvbW1lbnR8ZXNjYXBlKCdodG1sJyl8bmwyYnIgfX08L2Rpdj5cbiAgICAgICAgICAgICAgeyUgZW5kaWYgJX0iLCJhZnRlciI6IiAgICAgICAgICAgICAgeyUgaWYgaGlzdG9yeS5ic19jdXN0b21lcl9jb21tZW50IGlzIG5vdCBlbXB0eSAlfVxuICAgICAgICAgICAgICAgIDxkaXYgY2xhc3M9XCJtdC0yIHRleHQtYnJlYWtcIj48c3Ryb25nPnt7IGNvbHVtbl9jb21tZW50IH19Ojwvc3Ryb25nPjxicj57eyBoaXN0b3J5LmJzX2N1c3RvbWVyX2NvbW1lbnR8ZXNjYXBlKCdodG1sJyl8bmwyYnIgfX08L2Rpdj5cbiAgICAgICAgICAgICAgeyUgZWxzZSAlfVxuICAgICAgICAgICAgICAgIHt7IGhpc3RvcnkuY29tbWVudCB9fVxuICAgICAgICAgICAgICB7JSBlbmRpZiAlfSIsImNvdW50IjoxfV19XSwiZGVwZW5kZW5jaWVzIjpbeyJwYXRoIjoiY2F0YWxvZy9tb2RlbC9hY2NvdW50L29yZGVyLnBocCIsInNoYTI1NiI6IjI4NjJmZDlkMmE1Zjc1NmZkNWM1OGQyZWNmYzcxZGY3MDU3OTlhM2JmM2YyY2VmMjRhNjlhZjg2N2MxMjc5MzAifSx7InBhdGgiOiJjYXRhbG9nL2NvbnRyb2xsZXIvYWNjb3VudC9vcmRlci5waHAiLCJzaGEyNTYiOiIwNWQ3ZGU0ZWY2OTU4NWNiNTQyMmFmOWEwYmFkYzRmMGZhNWJlZjRjZDBiMzNjYmMyNzJhMzliZDAwMDc4MzUyIn0seyJwYXRoIjoic3lzdGVtL2xpYnJhcnkvYWNjMDA3X29yZGVyX3N0YXR1cy5waHAiLCJzaGEyNTYiOiI5MDg2ZWZmOTE3ZWEyMDAzYmRhZDc4NmQwZGRhYTY4MGYwNmNiNzAyYjlhZjE1NjdjMzBjMzkxNmU2Y2E2ODg1In0seyJwYXRoIjoiZXh0ZW5zaW9uL3VrcmFpbmlhbi9jYXRhbG9nL2xhbmd1YWdlL3VrLXVhL2FjY291bnQvb3JkZXIucGhwIiwic2hhMjU2IjoiYmQyNWFiZmUwMjQwODM0N2M5ZWYxNzcwZTYxMzRkZjExMTAxYThjOThmY2M2ZTRkMGRlZGZiZGVlODQxZGNlYSJ9XSwic3RhdHVzX3JlZ2lzdHJ5IjpbeyJvcmRlcl9zdGF0dXNfaWQiOiIxIiwibGFuZ3VhZ2VfaWQiOiI0IiwibmFtZSI6ItCSINC+0LHRgNC+0LHRhtGWIn0seyJvcmRlcl9zdGF0dXNfaWQiOiIzIiwibGFuZ3VhZ2VfaWQiOiI0IiwibmFtZSI6ItCU0L7RgdGC0LDQstC70Y/RlNGC0YzRgdGPIn0seyJvcmRlcl9zdGF0dXNfaWQiOiI1IiwibGFuZ3VhZ2VfaWQiOiI0IiwibmFtZSI6ItCe0YLRgNC40LzQsNC90L4ifSx7Im9yZGVyX3N0YXR1c19pZCI6IjciLCJsYW5ndWFnZV9pZCI6IjQiLCJuYW1lIjoi0JTQvtGB0YLQsNCy0LvQtdC90L4gLSDRh9C10LrQsNGUINC+0YLRgNC40LzRg9Cy0LDRh9CwIn0seyJvcmRlcl9zdGF0dXNfaWQiOiIxMCIsImxhbmd1YWdlX2lkIjoiNCIsIm5hbWUiOiLQn9C+0LzQuNC70LrQsCJ9LHsib3JkZXJfc3RhdHVzX2lkIjoiMTIiLCJsYW5ndWFnZV9pZCI6IjQiLCJuYW1lIjoi0J/QvtCy0LXRgNC90LXQvdC+In0seyJvcmRlcl9zdGF0dXNfaWQiOiIxMyIsImxhbmd1YWdlX2lkIjoiNCIsIm5hbWUiOiLQn9C+0LLQtdGA0L3QtdC90L3RjyDQutC+0YjRgtGW0LIifSx7Im9yZGVyX3N0YXR1c19pZCI6IjE0IiwibGFuZ3VhZ2VfaWQiOiI0IiwibmFtZSI6ItCf0YDQvtGC0LXRgNC80ZbQvdC+0LLQsNC90L4ifSx7Im9yZGVyX3N0YXR1c19pZCI6IjE1IiwibGFuZ3VhZ2VfaWQiOiI0IiwibmFtZSI6ItCn0LXRgNC90LXRgtC60LAgKNGB0LjRgdGC0LXQvNC90LjQuSkifSx7Im9yZGVyX3N0YXR1c19pZCI6IjE3IiwibGFuZ3VhZ2VfaWQiOiI0IiwibmFtZSI6ItCg0L7Qt9GB0YLRgNC+0YfQutCwIOKAlCDQvtGH0ZbQutGD0ZQg0LrQu9GW0ZTQvdGC0LAifSx7Im9yZGVyX3N0YXR1c19pZCI6IjE4IiwibGFuZ3VhZ2VfaWQiOiI0IiwibmFtZSI6ItCg0L7Qt9GB0YLRgNC+0YfQutCwIOKAlCDQvtGH0ZbQutGD0ZQg0LLQuNC00LDRh9GWIn0seyJvcmRlcl9zdGF0dXNfaWQiOiIxOSIsImxhbmd1YWdlX2lkIjoiNCIsIm5hbWUiOiLQoNC+0LfRgdGC0YDQvtGH0LrQsCDigJQg0L7RhNC+0YDQvNC70LXQvdC+In0seyJvcmRlcl9zdGF0dXNfaWQiOiIyMSIsImxhbmd1YWdlX2lkIjoiNCIsIm5hbWUiOiLQoNC+0LfRgdGC0YDQvtGH0LrQsCDigJQg0L/QvtCy0LXRgNC90LXQvdC+In0seyJvcmRlcl9zdGF0dXNfaWQiOiIyMiIsImxhbmd1YWdlX2lkIjoiNCIsIm5hbWUiOiLQoNC+0LfRgdGC0YDQvtGH0LrQsCDigJQg0LLRltC00YXQuNC70LXQvdC+In0seyJvcmRlcl9zdGF0dXNfaWQiOiIyMyIsImxhbmd1YWdlX2lkIjoiNCIsIm5hbWUiOiLQn9C10YDQtdC00LfQsNC80L7QstC70LXQvdC90Y8ifSx7Im9yZGVyX3N0YXR1c19pZCI6IjI0IiwibGFuZ3VhZ2VfaWQiOiI0IiwibmFtZSI6ItCh0LrQsNGB0L7QstCw0L3QviJ9XSwiYWxsb3dlZF9ldmVudHMiOlt7ImNvZGUiOiJhbmFseXRpY3NfcHNfZW5oYW5jZWRfbWVhc3VyZW1lbnQiLCJ0cmlnZ2VyIjoiY2F0YWxvZy92aWV3L2FjY291bnQvb3JkZXJfaW5mby9iZWZvcmUiLCJhY3Rpb24iOiJleHRlbnNpb24vcHNfZW5oYW5jZWRfbWVhc3VyZW1lbnQvYW5hbHl0aWNzL3BzX2VuaGFuY2VkX21lYXN1cmVtZW50LmV2ZW50Q2F0YWxvZ1ZpZXdBY2NvdW50T3JkZXJJbmZvQmVmb3JlIn1dfQ==',true),true);
    if (!is_array($payload) || count($payload['entries'] ?? []) !== 1) acc007p_fail('runner_payload_invalid');
    $dry = false; $rollback = null;
    foreach (array_slice($argv,1) as $arg) {
        if ($arg === '--dry-run') $dry = true;
        elseif (strpos($arg,'--rollback=') === 0) $rollback = substr($arg,11);
        else acc007p_fail('unknown_argument');
    }
    if ($dry && $rollback !== null) acc007p_fail('conflicting_arguments');
    if ($rollback !== null) { acc007p_rollback($root,$rollback,$payload); exit(0); }
    $markers = 0;
    foreach ($payload['entries'] as $entry) {
        $relative = $entry['path']; $target = acc007p_path($root,$relative,!$entry['new']);
        $before = is_file($target) ? acc007p_read($target) : null;
        if ($before !== null && strpos($before,$payload['marker']) !== false) $markers++;
        $plans[$relative] = ['entry'=>$entry,'target'=>$target,'before'=>$before];
    }
    if ($markers > 0) {
        if ($markers !== count($plans)) acc007p_fail('partial_application_requires_review');
        foreach ($plans as $relative=>$plan) if (!hash_equals($plan['entry']['after'],hash('sha256',acc007p_norm($plan['before'],$relative)))) acc007p_fail('installed_content_changed:' . $relative);
        acc007p_log('already_applied','yes'); acc007p_log('done','ok');
        if (!$dry) @unlink(__FILE__);
        exit(0);
    }
    foreach ($plans as $relative=>&$plan) {
        $entry = $plan['entry'];
        if ($entry['new']) {
            if ($plan['before'] !== null) acc007p_fail('new_asset_already_exists');
            $after = $entry['content'];
        } else {
            if (!hash_equals($entry['before'],hash('sha256',acc007p_norm($plan['before'],$relative)))) acc007p_fail('source_hash_mismatch:' . $relative);
            $after = acc007p_edit($plan['before'],$entry['edits']);
        }
        if (!hash_equals($entry['after'],hash('sha256',acc007p_norm($after,$relative)))) acc007p_fail('candidate_hash_mismatch:' . $relative);
        $plan['after'] = $after;
    }
    unset($plan);
    foreach ($payload['dependencies'] as $dependency) {
        $file = acc007p_path($root,$dependency['path']);
        if (!hash_equals($dependency['sha256'],hash_file('sha256',$file))) acc007p_fail('dependency_hash_mismatch:' . $dependency['path']);
    }
    acc007p_log('source_gate','1_target_ok');
    acc007p_db($root,$payload);
    if ($dry) { acc007p_log('dry_run','ok'); acc007p_log('done','ok'); exit(0); }
    $backupRoot = $root . '/_patch_backups';
    if (is_link($backupRoot)) acc007p_fail('backup_root_symlink');
    if (!is_dir($backupRoot) && !mkdir($backupRoot,0755)) acc007p_fail('backup_root_create_failed');
    $backup = $backupRoot . '/ACC-007_history-comment-priority_20261014-' . gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(4));
    if (!mkdir($backup,0755)) acc007p_fail('backup_create_failed');
    $receipt = ['task'=>'ACC-007','date_utc'=>gmdate('c'),'files'=>[]];
    foreach ($plans as $relative=>$plan) {
        foreach (['original','candidate'] as $kind) {
            if ($kind === 'original' && $plan['before'] === null) continue;
            $file = $backup . '/' . $kind . '/' . $relative;
            if (!is_dir(dirname($file)) && !mkdir(dirname($file),0755,true)) acc007p_fail('backup_directory_failed');
            acc007p_atomic($file,$kind === 'original' ? $plan['before'] : $plan['after']);
        }
        $receipt['files'][$relative] = ['new'=>$plan['entry']['new'],'original_sha256'=>$plan['before'] === null ? null : hash('sha256',$plan['before']),'candidate_sha256'=>hash('sha256',$plan['after'])];
        if (substr($relative,-4)==='.php') acc007p_lint($backup . '/candidate/' . $relative);
    }
    acc007p_atomic($backup . '/receipt.json',json_encode($receipt,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    acc007p_log('backup',str_replace(DIRECTORY_SEPARATOR,'/',substr($backup,strlen($root)+1)));
    foreach ($plans as $relative=>$plan) {
        $current = is_file($plan['target']) ? acc007p_read($plan['target']) : null;
        if ($current !== $plan['before']) acc007p_fail('source_changed_during_apply:' . $relative);
        $written[] = $relative;
        acc007p_atomic($plan['target'],$plan['after']);
        acc007p_log('changed',$relative);
    }
    foreach ($plans as $relative=>$plan) if (substr($relative,-4)==='.php') { acc007p_lint($plan['target']); acc007p_log('php_lint',$relative . ':ok'); }
    acc007p_log('cache_refresh','required_owner_command'); acc007p_log('done','ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    $restored = true;
    foreach (array_reverse($written) as $relative) {
        $plan = $plans[$relative];
        try {
            $current = is_file($plan['target']) ? acc007p_read($plan['target']) : null;
            if ($current === $plan['before']) continue;
            if ($current !== $plan['after']) acc007p_fail('later_change_during_restore');
            if ($plan['before'] === null) { if (!unlink($plan['target'])) acc007p_fail('restore_asset_failed'); }
            else acc007p_atomic($plan['target'],$plan['before']);
        } catch (Throwable $restoreError) { $restored = false; }
    }
    // Only our fixed failure codes are logged; external DB/OS exception messages stay private.
    $message = $error instanceof Acc007pFailure ? $error->getMessage() : 'unexpected_error';
    if (!preg_match('~^[A-Za-z0-9_:/.-]+$~D',$message)) $message = 'unexpected_error';
    acc007p_log('error',$message);
    if ($written) acc007p_log('automatic_restore',$restored ? 'ok' : 'owner_review_required');
    acc007p_log('done','error');
    exit(1);
}
