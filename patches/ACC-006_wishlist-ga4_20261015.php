<?php
/**
 * ACC-006 — confirmed new wishlist membership to native PS Enhanced Measurement GA4.
 * PHP 8.0.30 compatible; four source files only. No DB writes, settings changes or event registration.
 * Respects native module/event flags, cookie policy, gtag/GTM and hidden-price policy.
 * Actual independent design review accepted; CODE review and owner deploy/QA are separate gates.
 * Native PS controller update may be overwritten by vendor upgrades: revalidate this integration then.
 * Uses fresh October15 archive and exact dependency gates; source drift fails before any write.
 * Usage: php ACC-006_wishlist-ga4_20261015.php --dry-run
 * Apply: php ACC-006_wishlist-ga4_20261015.php
 * Rollback: re-upload runner, then --rollback=_patch_backups/ACC-006_wishlist-ga4_20261015-<ts>-<suffix>
 * Backup stores exact original/candidate bytes and receipt. Cache cleanup is owner-run after apply/rollback.
 * No DB dump/SQL rollback needed: this runner performs read-only metadata preflight only.
 * Owner later enables add_to_wishlist in PS admin and verifies actual DebugView/Realtime delivery.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

function acc006_log(string $key, string $value): void { echo $key . '=' . $value . PHP_EOL; }
class Acc006Failure extends RuntimeException {}
function acc006_fail(string $code): void { throw new Acc006Failure($code); }
function acc006_norm(string $bytes, string $path): string {
    $bytes = str_replace("\r\n", "\n", $bytes);
    if ($path === 'catalog/view/template/common/header.twig') {
        foreach (['catalog/view/stylesheet/boostershop-ds.css'=>'{DS_TOKEN}', 'catalog/view/javascript/booster-wishlist.js'=>'{JS_TOKEN}'] as $prefix=>$token) {
            $pattern = '~' . preg_quote($prefix,'~') . '\?v=([A-Za-z0-9._-]{1,128})(?=["\x27])~';
            if (preg_match_all($pattern,$bytes,$m) !== 1) acc006_fail('header_asset_token_shape');
            $bytes = str_replace($m[0][0],$prefix . '?v=' . $token,$bytes);
        }
    }
    return $bytes;
}
function acc006_header_bust(string $bytes): string {
    $pattern = '~catalog/view/javascript/booster-wishlist\.js\?v=([A-Za-z0-9._-]{1,128})(?=["\x27])~';
    if (preg_match_all($pattern,$bytes,$m) !== 1) acc006_fail('wishlist_asset_token_shape');
    return str_replace($m[0][0],'catalog/view/javascript/booster-wishlist.js?v=acc006-ga4-20261015',$bytes);
}
function acc006_path(string $root, string $relative, bool $mustExist = true): string {
    if (!preg_match('~^(catalog/|system/|extension/[A-Za-z0-9_]+/)[A-Za-z0-9_./-]+$~D', $relative) || strpos($relative, '..') !== false) acc006_fail('unsafe_target');
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $parent = realpath(dirname($path));
    if (!$parent || strpos($parent . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR) !== 0 || is_link($path)) acc006_fail('unsafe_target_parent');
    // Actual reads below are the readability gate; Windows sandbox ACL stat probes can disagree.
    if ($mustExist && !is_file($path)) acc006_fail('target_missing:' . $relative);
    return $path;
}
function acc006_read(string $path): string {
    $bytes = file_get_contents($path);
    if ($bytes === false) acc006_fail('file_read_failed');
    return $bytes;
}
function acc006_atomic(string $path, string $bytes): void {
    // Exclusive creation in the target directory; tempnam can silently fall back elsewhere.
    $temporary = dirname($path) . DIRECTORY_SEPARATOR . '.a6-' . bin2hex(random_bytes(4));
    $handle = @fopen($temporary,'xb');
    if ($handle === false) acc006_fail('temporary_write_failed');
    try {
        if (!flock($handle,LOCK_EX) || fwrite($handle,$bytes) !== strlen($bytes) || !fflush($handle)) acc006_fail('file_write_failed');
        fclose($handle); $handle = null;
        if (is_file($path)) @chmod($temporary, fileperms($path) & 0777);
        else @chmod($temporary, 0644);
        if (!rename($temporary, $path)) acc006_fail('atomic_replace_failed');
    } finally {
        if (is_resource($handle)) fclose($handle);
        if (is_file($temporary)) @unlink($temporary);
    }
    if (!hash_equals(hash('sha256', $bytes), hash_file('sha256', $path))) acc006_fail('written_hash_mismatch');
}
function acc006_lint(string $path): void {
    $output = []; $status = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $status);
    if ($status !== 0) acc006_fail('php_lint_failed:' . basename($path));
}
function acc006_edit(string $source, array $edits): string {
    foreach ($edits as $edit) {
        $pattern = '~' . str_replace("\n", '\\r?\\n', preg_quote($edit['before'], '~')) . '~';
        if (preg_match_all($pattern, $source, $matches) !== $edit['count']) acc006_fail('anchor_count_mismatch');
        $source = preg_replace_callback($pattern, static function (array $match) use ($edit): string {
            return str_replace("\n", strpos($match[0], "\r\n") !== false ? "\r\n" : "\n", $edit['after']);
        }, $source);
        if (!is_string($source)) acc006_fail('anchor_replacement_failed');
    }
    return $source;
}
function acc006_rows($db, string $sql): array {
    $result = $db->query($sql);
    if (!$result) acc006_fail('db_preflight_query_failed');
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $result->free();
    return $rows;
}
function acc006_db(string $root, array $payload): void {
    if (!is_file($root . '/config.php')) acc006_fail('config_missing');
    require $root . '/config.php';
    foreach (['DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PREFIX','DB_PORT'] as $constant) {
        if (!defined($constant)) acc006_fail('db_config_incomplete');
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/D', DB_PREFIX)) acc006_fail('db_prefix_shape');
    if (!class_exists('mysqli')) acc006_fail('mysqli_missing');
    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
    if ($db->connect_errno) acc006_fail('db_connection_failed');
    if (!$db->set_charset('utf8mb4')) acc006_fail('db_charset_failed');
    try {
        $prefix = DB_PREFIX;
        $columns = acc006_rows($db, 'SHOW COLUMNS FROM `' . $prefix . 'customer_wishlist`');
        $map = [];
        foreach ($columns as $row) $map[$row['Field']] = strtolower($row['Type']);
        if (array_keys($map) !== ['customer_id','store_id','product_id','date_added']) acc006_fail('wishlist_schema_mismatch');
        foreach (['customer_id','store_id','product_id'] as $key) if (!preg_match('/^int(?:\(\d+\))?$/D', $map[$key])) acc006_fail('wishlist_id_type_mismatch');
        if ($map['date_added'] !== 'datetime') acc006_fail('wishlist_date_type_mismatch');
        $indexes = acc006_rows($db, 'SHOW INDEX FROM `' . $prefix . 'customer_wishlist` WHERE Key_name = \'PRIMARY\'');
        usort($indexes, static function (array $a, array $b): int { return (int)$a['Seq_in_index'] <=> (int)$b['Seq_in_index']; });
        if (array_column($indexes, 'Column_name') !== ['customer_id','store_id','product_id']) acc006_fail('wishlist_primary_key_mismatch');
        $languages = acc006_rows($db, 'SELECT `language_id`,`code`,`status` FROM `' . $prefix . 'language` WHERE `status` = 1');
        if (count($languages) !== 1 || (string)$languages[0]['language_id'] !== '4' || $languages[0]['code'] !== 'uk-ua') acc006_fail('language_inventory_mismatch');
        $routes = ['account/wishlist','account/wishlist_list','product/thumb','product/product','common/header'];
        $quoted = array_map(static function (string $v) use ($db): string { return "'" . $db->real_escape_string($v) . "'"; }, $routes);
        $themes = acc006_rows($db, 'SELECT `route` FROM `' . $prefix . 'theme` WHERE `route` IN (' . implode(',', $quoted) . ') AND `status` = 1 LIMIT 1');
        if ($themes) acc006_fail('active_theme_override_requires_review');
        $events = acc006_rows($db, 'SELECT `code`,`trigger`,`action` FROM `' . $prefix . 'event` WHERE `status` = 1 AND (`trigger` LIKE \'%account/wishlist%\' OR `trigger` LIKE \'%product/thumb%\' OR `trigger` LIKE \'%product/product%\' OR `trigger` LIKE \'%common/header%\' OR `trigger` LIKE \'%*%\')');
        $allowed = array_map(static function (array $row): string { return implode("\n", $row); }, $payload['allowed_events']);
        foreach ($events as $row) {
            if (!in_array(implode("\n", [$row['code'],$row['trigger'],$row['action']]), $allowed, true)) acc006_fail('unreviewed_active_event');
        }
        acc006_log('db_preflight', 'read_only_ok');
        acc006_log('db_mutations', 'none');
    } finally { $db->close(); }
}
function acc006_preserve_token(string $original, string $current, string $relative): string {
    if ($relative !== 'catalog/view/template/common/header.twig') return $original;
    acc006_norm($original, $relative); acc006_norm($current, $relative);
    preg_match('~catalog/view/stylesheet/boostershop-ds\.css\?v=([A-Za-z0-9._-]{1,128})(?=["\x27])~', $current, $m);
    return preg_replace('~(catalog/view/stylesheet/boostershop-ds\.css\?v=)[A-Za-z0-9._-]{1,128}(?=["\x27])~', '${1}' . $m[1], $original, 1);
}
function acc006_rollback(string $root, string $argument, array $payload): void {
    if (!preg_match('~^_patch_backups/ACC-006_wishlist-ga4_20261015-\d{8}T\d{6}Z-[a-f0-9]{8}$~D', $argument)) acc006_fail('rollback_path_shape');
    $backup = realpath($root . '/' . $argument);
    $backupRoot = realpath($root . '/_patch_backups');
    if (!$backup || !$backupRoot || dirname($backup) !== $backupRoot || is_link($root . '/' . $argument)) acc006_fail('rollback_path_outside_backup_root');
    $manifest = json_decode(acc006_read($backup . '/receipt.json'), true);
    if (!is_array($manifest) || ($manifest['task'] ?? '') !== 'ACC-006-GA4' || count($manifest['files'] ?? []) !== count($payload['entries'])) acc006_fail('rollback_receipt_shape');
    $restore = [];
    foreach ($payload['entries'] as $entry) {
        $relative = $entry['path']; $meta = $manifest['files'][$relative] ?? null;
        if (!is_array($meta) || ($meta['new'] ?? null) !== $entry['new']) acc006_fail('rollback_receipt_entry');
        $target = acc006_path($root, $relative, false);
        $current = is_file($target) ? acc006_read($target) : null;
        $original = null;
        if (!$entry['new']) {
            $saved = $backup . '/original/' . $relative;
            if (is_link($saved) || !is_file($saved)) acc006_fail('rollback_original_missing');
            $original = acc006_read($saved);
            if (!hash_equals($meta['original_sha256'], hash('sha256',$original)) || !hash_equals($entry['before'],hash('sha256',acc006_norm($original,$relative)))) acc006_fail('rollback_backup_tampered');
        }
        if ($current === null && !$entry['new']) acc006_fail('rollback_target_missing');
        if ($current !== null) {
            $state = hash('sha256',acc006_norm($current,$relative));
            if (!hash_equals($entry['after'],$state) && !(!$entry['new'] && hash_equals($entry['before'],$state))) acc006_fail('rollback_later_file_change:' . $relative);
        }
        $restore[$relative] = ['target'=>$target,'current'=>$current,'original'=>$original];
    }
    foreach ($restore as $relative=>$item) {
        if ($item['original'] === null) {
            if ($item['current'] !== null && !unlink($item['target'])) acc006_fail('rollback_asset_delete_failed');
        } else {
            acc006_atomic($item['target'],acc006_preserve_token($item['original'],$item['current'],$relative));
            if (substr($relative,-4)==='.php') acc006_lint($item['target']);
        }
        acc006_log('restored',$relative);
    }
    acc006_log('rollback','ok'); acc006_log('done','ok');
    @unlink(__FILE__);
}

$written = []; $plans = []; $backup = null;
try {
    if (PHP_VERSION_ID < 80000) acc006_fail('php_8_required');
    if (!function_exists('exec')) acc006_fail('php_lint_command_unavailable');
    $root = realpath(getcwd());
    if (!$root) acc006_fail('cwd_unavailable');
    acc006_log('cwd',$root); acc006_log('time',gmdate('c')); acc006_log('php',PHP_VERSION);
    $payload = json_decode(base64_decode('eyJ0YXNrIjoiQUNDLTAwNi1HQTQiLCJtYXJrZXIiOiJBQ0MwMDZfR0E0X1dJU0hMSVNUXzIwMjYxMDE1IiwiYXJjaGl2ZV9zaGEyNTYiOiJhNjRiMjIyMzg5YmYxMTgxZGE0YmIxNjQzNmZmOWI0Njk2Mzc0NmFjMTc2ZjMzNDQ4MzU2MDRlYTY3YmFkZGMwIiwiZW50cmllcyI6W3sicGF0aCI6ImNhdGFsb2cvdmlldy9qYXZhc2NyaXB0L2Jvb3N0ZXItd2lzaGxpc3QuanMiLCJuZXciOmZhbHNlLCJiZWZvcmUiOiI0Mzg3NmU4MWY0YzFiM2ZkODEyNWZhZGZlZmU1MTBjNDE5ZWJlZDU4OGQ1NDI3MmU4MThlMTUyMWIxNTNhZWI0IiwiYWZ0ZXIiOiJmOGVmNmMyNDRiZTg4NmZiMjlmMDQxNGQ1N2NlNDc0ZTUzMzFiNTEzZmUxZWJiNmVmN2MzYjYzMGJjODAzNDBjIiwiZWRpdHMiOlt7ImJlZm9yZSI6IiAgZnVuY3Rpb24gYnVzeShidXR0b24sIHZhbHVlKSB7IiwiYWZ0ZXIiOiIgIC8vIEFDQzAwNl9HQTRfV0lTSExJU1RfMjAyNjEwMTU6IG9wdGlvbmFsIGFuYWx5dGljcyBtdXN0IG5ldmVyIGZhaWwgYSBjb21wbGV0ZWQgc2F2ZS5cbiAgZnVuY3Rpb24gdHJhY2tBZGRpdGlvbihqc29uLCBpZCkge1xuICAgIHRyeSB7XG4gICAgICBpZiAoanNvbi5hZGRlZCAhPT0gdHJ1ZSB8fCBTdHJpbmcoanNvbi5wcm9kdWN0X2lkKSAhPT0gaWQgfHwgdHlwZW9mIGpzb24uc3VjY2VzcyAhPT0gJ3N0cmluZycgfHwgIWpzb24uc3VjY2VzcykgcmV0dXJuO1xuICAgICAgdmFyIGRhdGEgPSBqc29uLnBzX2FkZF90b193aXNobGlzdDtcbiAgICAgIHZhciBlYyA9IGRhdGEgJiYgZGF0YS5lY29tbWVyY2U7XG4gICAgICBpZiAoIWVjIHx8ICFBcnJheS5pc0FycmF5KGVjLml0ZW1zKSB8fCBlYy5pdGVtcy5sZW5ndGggIT09IDEpIHJldHVybjtcbiAgICAgIHZhciBpdGVtID0gZWMuaXRlbXNbMF07XG4gICAgICBpZiAoIWl0ZW0gfHwgaXRlbS5xdWFudGl0eSAhPT0gMSB8fCB0eXBlb2YgaXRlbS5pdGVtX25hbWUgIT09ICdzdHJpbmcnIHx8ICFpdGVtLml0ZW1fbmFtZSkgcmV0dXJuO1xuICAgICAgdmFyIHByaWNlZCA9IE9iamVjdC5wcm90b3R5cGUuaGFzT3duUHJvcGVydHkuY2FsbChpdGVtLCAncHJpY2UnKTtcbiAgICAgIGlmIChwcmljZWQpIHtcbiAgICAgICAgaWYgKHR5cGVvZiBpdGVtLnByaWNlICE9PSAnbnVtYmVyJyB8fCAhTnVtYmVyLmlzRmluaXRlKGl0ZW0ucHJpY2UpIHx8IGl0ZW0ucHJpY2UgPCAwIHx8IGVjLnZhbHVlICE9PSBpdGVtLnByaWNlIHx8ICEvXltBLVpdezN9JC8udGVzdChlYy5jdXJyZW5jeSB8fCAnJykpIHJldHVybjtcbiAgICAgIH0gZWxzZSBpZiAoT2JqZWN0LnByb3RvdHlwZS5oYXNPd25Qcm9wZXJ0eS5jYWxsKGVjLCAndmFsdWUnKSB8fCBPYmplY3QucHJvdG90eXBlLmhhc093blByb3BlcnR5LmNhbGwoZWMsICdjdXJyZW5jeScpKSByZXR1cm47XG4gICAgICBpZiAod2luZG93LnBzX2RhdGFMYXllciAmJiB0eXBlb2Ygd2luZG93LnBzX2RhdGFMYXllci5wdXNoRXZlbnREYXRhID09PSAnZnVuY3Rpb24nKSB7XG4gICAgICAgIHdpbmRvdy5wc19kYXRhTGF5ZXIucHVzaEV2ZW50RGF0YSgnYWRkX3RvX3dpc2hsaXN0JywgZGF0YSk7XG4gICAgICB9XG4gICAgfSBjYXRjaCAoZXJyb3IpIHsgLyogQmVzdC1lZmZvcnQgdGVsZW1ldHJ5OiB0aGUgd2lzaGxpc3Qgc2F2ZSBhbHJlYWR5IHN1Y2NlZWRlZC4gKi8gfVxuICB9XG5cbiAgZnVuY3Rpb24gYnVzeShidXR0b24sIHZhbHVlKSB7IiwiY291bnQiOjF9LHsiYmVmb3JlIjoiICAgICAgZWxzZSBidXR0b24udGV4dENvbnRlbnQgPSBqc29uLnJlcXVpcmVzX2xvZ2luID8gJ9CX0LHQtdGA0LXQttC10L3QvicgOiAn0JTQvtC00LDQvdC+JzsiLCJhZnRlciI6IiAgICAgIGVsc2Uge1xuICAgICAgICBidXR0b24udGV4dENvbnRlbnQgPSBqc29uLnJlcXVpcmVzX2xvZ2luID8gJ9CX0LHQtdGA0LXQttC10L3QvicgOiAn0JTQvtC00LDQvdC+JztcbiAgICAgICAgdHJhY2tBZGRpdGlvbihqc29uLCBpZCk7XG4gICAgICB9IiwiY291bnQiOjF9XX0seyJwYXRoIjoiZXh0ZW5zaW9uL3BzX2VuaGFuY2VkX21lYXN1cmVtZW50L2NhdGFsb2cvY29udHJvbGxlci9hbmFseXRpY3MvcHNfZW5oYW5jZWRfbWVhc3VyZW1lbnQucGhwIiwibmV3IjpmYWxzZSwiYmVmb3JlIjoiNzI3M2FjMzczM2MzMjMzY2M1MmM4NmYwN2FhMmE0NzE0YWRiNTc1ZDU3MGU5MDE4MDhjNjFkMzQ5OGVmZjAwZSIsImFmdGVyIjoiY2Q4OTEwNTI0OTMxYjMzMjQ0YWUyNGRiNGQwODJjNDhhYmE5YWU2ZTYzNWRlNTNhNmMxNmNkMmY2MTRmNDNhMSIsImVkaXRzIjpbeyJiZWZvcmUiOiIgICAgcHJvdGVjdGVkIGZ1bmN0aW9uIGdldENhdGVnb3J5VHlwZTEoaW50ICRwcm9kdWN0X2lkKTogYXJyYXkiLCJhZnRlciI6IiAgICAvLyBBQ0MwMDZfR0E0X1dJU0hMSVNUXzIwMjYxMDE1OiBpbnRlcm5hbCBzdWNjZXNzLXJlc3BvbnNlIHBheWxvYWQsIG5vIGV2ZW50L0RCIHdyaXRlcy5cbiAgICBwdWJsaWMgZnVuY3Rpb24gd2lzaGxpc3REYXRhKGFycmF5ICRwcm9kdWN0ID0gW10pOiBhcnJheVxuICAgIHtcbiAgICAgICAgaWYgKCEkcHJvZHVjdCB8fCBlbXB0eSgkcHJvZHVjdFsncHJvZHVjdF9pZCddKSB8fCBlbXB0eSgkcHJvZHVjdFsnbmFtZSddKVxuICAgICAgICAgICAgfHwgISR0aGlzLT5jb25maWctPmdldCgnYW5hbHl0aWNzX3BzX2VuaGFuY2VkX21lYXN1cmVtZW50X3N0YXR1cycpXG4gICAgICAgICAgICB8fCAhJHRoaXMtPmNvbmZpZy0+Z2V0KCdhbmFseXRpY3NfcHNfZW5oYW5jZWRfbWVhc3VyZW1lbnRfdHJhY2tfYWRkX3RvX3dpc2hsaXN0JylcbiAgICAgICAgICAgIHx8ICEoISR0aGlzLT5jb25maWctPmdldCgnY29uZmlnX2Nvb2tpZV9pZCcpIHx8IChpc3NldCgkdGhpcy0+cmVxdWVzdC0+Y29va2llWydwb2xpY3knXSkgJiYgJHRoaXMtPnJlcXVlc3QtPmNvb2tpZVsncG9saWN5J10pKSkge1xuICAgICAgICAgICAgcmV0dXJuIFtdO1xuICAgICAgICB9XG5cbiAgICAgICAgJGZpZWxkID0gKHN0cmluZykkdGhpcy0+Y29uZmlnLT5nZXQoJ2FuYWx5dGljc19wc19lbmhhbmNlZF9tZWFzdXJlbWVudF9pdGVtX2lkJyk7XG4gICAgICAgICRpZCA9IGlzc2V0KCRwcm9kdWN0WyRmaWVsZF0pICYmICFlbXB0eSgkcHJvZHVjdFskZmllbGRdKSA/ICR0aGlzLT5mb3JtYXRMaXN0SWQoKHN0cmluZykkcHJvZHVjdFskZmllbGRdKSA6ICRwcm9kdWN0Wydwcm9kdWN0X2lkJ107XG4gICAgICAgIC8vIE1hdGNoIGFjdHVhbCBuYXRpdmUgUFMgaXRlbSBJRHMsIGluY2x1ZGluZyBpdHMgbnVtZXJpYy1zdHJpbmcgY29udmVudGlvbiwgb25seSBmb3IgSUQuXG4gICAgICAgICRpZCA9IGpzb25fZGVjb2RlKGpzb25fZW5jb2RlKCRpZCwgSlNPTl9OVU1FUklDX0NIRUNLKSwgdHJ1ZSk7XG4gICAgICAgICRpdGVtID0gWydpdGVtX2lkJyA9PiAkaWQsICdpdGVtX25hbWUnID0+IGh0bWxfZW50aXR5X2RlY29kZSgoc3RyaW5nKSRwcm9kdWN0WyduYW1lJ10sIEVOVF9RVU9URVMsICdVVEYtOCcpLCAncXVhbnRpdHknID0+IDFdO1xuICAgICAgICBpZiAoJGl0ZW1bJ2l0ZW1fbmFtZSddID09PSAnJykgcmV0dXJuIFtdO1xuICAgICAgICAkYWZmaWxpYXRpb24gPSAkdGhpcy0+Y29uZmlnLT5nZXQoJ2FuYWx5dGljc19wc19lbmhhbmNlZF9tZWFzdXJlbWVudF9hZmZpbGlhdGlvbicpO1xuICAgICAgICBpZiAoJGFmZmlsaWF0aW9uKSAkaXRlbVsnYWZmaWxpYXRpb24nXSA9ICRhZmZpbGlhdGlvbjtcbiAgICAgICAgJGxvY2F0aW9uID0gJHRoaXMtPmNvbmZpZy0+Z2V0KCdhbmFseXRpY3NfcHNfZW5oYW5jZWRfbWVhc3VyZW1lbnRfbG9jYXRpb25faWQnKTtcbiAgICAgICAgaWYgKCRsb2NhdGlvbikgJGl0ZW1bJ2xvY2F0aW9uX2lkJ10gPSAkbG9jYXRpb247XG5cbiAgICAgICAgJHRoaXMtPmxvYWQtPm1vZGVsKCdjYXRhbG9nL21hbnVmYWN0dXJlcicpO1xuICAgICAgICAkYnJhbmQgPSAkdGhpcy0+bW9kZWxfY2F0YWxvZ19tYW51ZmFjdHVyZXItPmdldE1hbnVmYWN0dXJlcigoaW50KSgkcHJvZHVjdFsnbWFudWZhY3R1cmVyX2lkJ10gPz8gMCkpO1xuICAgICAgICBpZiAoJGJyYW5kKSAkaXRlbVsnaXRlbV9icmFuZCddID0gaHRtbF9lbnRpdHlfZGVjb2RlKCRicmFuZFsnbmFtZSddLCBFTlRfUVVPVEVTLCAnVVRGLTgnKTtcbiAgICAgICAgJG1vZGUgPSAoaW50KSR0aGlzLT5jb25maWctPmdldCgnYW5hbHl0aWNzX3BzX2VuaGFuY2VkX21lYXN1cmVtZW50X2l0ZW1fY2F0ZWdvcnlfb3B0aW9uJyk7XG4gICAgICAgICRjYXRlZ29yaWVzID0gW107XG4gICAgICAgIGlmICgkbW9kZSA9PT0gMCB8fCAkbW9kZSA9PT0gMSkge1xuICAgICAgICAgICAgJHRoaXMtPmxvYWQtPm1vZGVsKCdleHRlbnNpb24vcHNfZW5oYW5jZWRfbWVhc3VyZW1lbnQvYW5hbHl0aWNzL3BzX2VuaGFuY2VkX21lYXN1cmVtZW50Jyk7XG4gICAgICAgICAgICAkY2F0ZWdvcmllcyA9ICRtb2RlID09PSAwID8gJHRoaXMtPmdldENhdGVnb3J5VHlwZTEoKGludCkkcHJvZHVjdFsncHJvZHVjdF9pZCddKSA6ICR0aGlzLT5nZXRDYXRlZ29yeVR5cGUyKChpbnQpJHByb2R1Y3RbJ3Byb2R1Y3RfaWQnXSk7XG4gICAgICAgIH1cbiAgICAgICAgLy8gUGFnZS1jb250ZXh0IG1vZGVzIDIvMyBoYXZlIG5vIHZlcmlmaWVkIGNhdGVnb3J5IGluIHRoaXMgUE9TVDsgb21pdCBvcHRpb25hbCBjYXRlZ29yaWVzLlxuICAgICAgICBmb3JlYWNoIChhcnJheV9zbGljZSgkY2F0ZWdvcmllcywgMCwgNSkgYXMgJGluZGV4ID0+ICRjYXRlZ29yeSkge1xuICAgICAgICAgICAgJGl0ZW1bJGluZGV4ID09PSAwID8gJ2l0ZW1fY2F0ZWdvcnknIDogJ2l0ZW1fY2F0ZWdvcnknIC4gKCRpbmRleCArIDEpXSA9ICRjYXRlZ29yeTtcbiAgICAgICAgfVxuXG4gICAgICAgICRlY29tbWVyY2UgPSBbJ2l0ZW1zJyA9PiBbJGl0ZW1dXTtcbiAgICAgICAgaWYgKCR0aGlzLT5jdXN0b21lci0+aXNMb2dnZWQoKSB8fCAhJHRoaXMtPmNvbmZpZy0+Z2V0KCdjb25maWdfY3VzdG9tZXJfcHJpY2UnKSkge1xuICAgICAgICAgICAgJGN1cnJlbmN5ID0gKHN0cmluZykoJHRoaXMtPmNvbmZpZy0+Z2V0KCdhbmFseXRpY3NfcHNfZW5oYW5jZWRfbWVhc3VyZW1lbnRfY3VycmVuY3knKSA/OiAoJHRoaXMtPnNlc3Npb24tPmRhdGFbJ2N1cnJlbmN5J10gPz8gJHRoaXMtPmNvbmZpZy0+Z2V0KCdjb25maWdfY3VycmVuY3knKSkpO1xuICAgICAgICAgICAgaWYgKHByZWdfbWF0Y2goJy9eW0EtWl17M30kL0QnLCAkY3VycmVuY3kpKSB7XG4gICAgICAgICAgICAgICAgLy8gTWF0Y2ggdGhlIGVmZmVjdGl2ZSBxdWFudGl0eS1vbmUgc3RvcmVmcm9udCBwcmljZSwgbm90IHN0b2NrIHF1YW50aXR5L2NhcnQgb3B0aW9ucy5cbiAgICAgICAgICAgICAgICAkYmFzZSA9IChmbG9hdCkoJHByb2R1Y3RbJ3NwZWNpYWwnXSA/PyAwKSA/OiAoZmxvYXQpJHByb2R1Y3RbJ3ByaWNlJ107XG4gICAgICAgICAgICAgICAgJHByaWNlID0gJHRoaXMtPmN1cnJlbmN5LT5mb3JtYXQoJHRoaXMtPnRheC0+Y2FsY3VsYXRlKCRiYXNlLCAoaW50KSRwcm9kdWN0Wyd0YXhfY2xhc3NfaWQnXSwgKGJvb2wpJHRoaXMtPmNvbmZpZy0+Z2V0KCdhbmFseXRpY3NfcHNfZW5oYW5jZWRfbWVhc3VyZW1lbnRfaXRlbV9wcmljZV90YXgnKSksICRjdXJyZW5jeSwgMCwgZmFsc2UpO1xuICAgICAgICAgICAgICAgIGlmIChpc19udW1lcmljKCRwcmljZSkgJiYgaXNfZmluaXRlKChmbG9hdCkkcHJpY2UpICYmIChmbG9hdCkkcHJpY2UgPj0gMCkge1xuICAgICAgICAgICAgICAgICAgICAkZWNvbW1lcmNlWydpdGVtcyddWzBdWydwcmljZSddID0gKGZsb2F0KSRwcmljZTtcbiAgICAgICAgICAgICAgICAgICAgJGVjb21tZXJjZVsnY3VycmVuY3knXSA9ICRjdXJyZW5jeTtcbiAgICAgICAgICAgICAgICAgICAgJGVjb21tZXJjZVsndmFsdWUnXSA9IChmbG9hdCkkcHJpY2U7XG4gICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgfVxuICAgICAgICB9XG4gICAgICAgIHJldHVybiBbJ2Vjb21tZXJjZScgPT4gJGVjb21tZXJjZV07XG4gICAgfVxuXG4gICAgcHJvdGVjdGVkIGZ1bmN0aW9uIGdldENhdGVnb3J5VHlwZTEoaW50ICRwcm9kdWN0X2lkKTogYXJyYXkiLCJjb3VudCI6MX1dfSx7InBhdGgiOiJjYXRhbG9nL2NvbnRyb2xsZXIvYWNjb3VudC93aXNobGlzdC5waHAiLCJuZXciOmZhbHNlLCJiZWZvcmUiOiJhMTk0ZWRmYTdiYzZmMWYxYjg1MjUxZDJjZWNhNjZjZjMxOGViYWZlYmRiOWY0NTU5NzdlMDFmZWJjMTVkODUwIiwiYWZ0ZXIiOiI4ZjVlZWE0ZDIyZjI3MWVlMmNkNTAyY2JjZGFkZDY1MGYxYWJiNjU3ZDNlYzFhOGZhMTAyMTQ1OTVjM2UyNGMxIiwiZWRpdHMiOlt7ImJlZm9yZSI6Ilx0XHRpZiAoJHRoaXMtPmN1c3RvbWVyLT5pc0xvZ2dlZCgpKSB7XG5cdFx0XHQkdGhpcy0+bG9hZC0+bW9kZWwoJ2FjY291bnQvd2lzaGxpc3QnKTtcblx0XHRcdCR0aGlzLT5tb2RlbF9hY2NvdW50X3dpc2hsaXN0LT5hZGRXaXNobGlzdCIsImFmdGVyIjoiXHRcdC8vIEFDQzAwNl9HQTRfV0lTSExJU1RfMjAyNjEwMTU6IGV4aXN0aW5nIHBlbmRpbmcgbWVtYmVyc2hpcCBhbHNvIHByZXZlbnRzIGEgc2Vjb25kIGd1ZXN0LXRvLWxvZ2luIGV2ZW50LlxuXHRcdCRwZW5kaW5nID0gJHRoaXMtPnBlbmRpbmdJZHMoKTtcblx0XHQkYWRkZWQgPSAhaXNzZXQoJHBlbmRpbmdbJHByb2R1Y3RfaWRdKTtcblx0XHRpZiAoJHRoaXMtPmN1c3RvbWVyLT5pc0xvZ2dlZCgpKSB7XG5cdFx0XHQkdGhpcy0+bG9hZC0+bW9kZWwoJ2FjY291bnQvd2lzaGxpc3QnKTtcblx0XHRcdGZvcmVhY2ggKCR0aGlzLT5tb2RlbF9hY2NvdW50X3dpc2hsaXN0LT5nZXRXaXNobGlzdCgkdGhpcy0+Y3VzdG9tZXItPmdldElkKCkpIGFzICRyb3cpIHtcblx0XHRcdFx0aWYgKChpbnQpJHJvd1sncHJvZHVjdF9pZCddID09PSAkcHJvZHVjdF9pZCkgeyAkYWRkZWQgPSBmYWxzZTsgYnJlYWs7IH1cblx0XHRcdH1cblx0XHRcdCR0aGlzLT5tb2RlbF9hY2NvdW50X3dpc2hsaXN0LT5hZGRXaXNobGlzdCIsImNvdW50IjoxfSx7ImJlZm9yZSI6Ilx0XHRcdCRwZW5kaW5nID0gJHRoaXMtPnBlbmRpbmdJZHMoKTtcblx0XHRcdCRwZW5kaW5nWyRwcm9kdWN0X2lkXSA9ICRwcm9kdWN0X2lkOyIsImFmdGVyIjoiXHRcdFx0JHBlbmRpbmdbJHByb2R1Y3RfaWRdID0gJHByb2R1Y3RfaWQ7IiwiY291bnQiOjF9LHsiYmVmb3JlIjoiXHRcdCR0aGlzLT5yZXBseSgkanNvbiArICR0aGlzLT5zdGF0ZURhdGEoKSArIFsncHJvZHVjdF9pZCcgPT4gJHByb2R1Y3RfaWRdKTsiLCJhZnRlciI6Ilx0XHQkanNvbiArPSAkdGhpcy0+c3RhdGVEYXRhKCkgKyBbJ3Byb2R1Y3RfaWQnID0+ICRwcm9kdWN0X2lkLCAnYWRkZWQnID0+ICRhZGRlZF07XG5cdFx0aWYgKCRhZGRlZCAmJiAkdGhpcy0+Y29uZmlnLT5nZXQoJ2FuYWx5dGljc19wc19lbmhhbmNlZF9tZWFzdXJlbWVudF9zdGF0dXMnKVxuXHRcdFx0JiYgJHRoaXMtPmNvbmZpZy0+Z2V0KCdhbmFseXRpY3NfcHNfZW5oYW5jZWRfbWVhc3VyZW1lbnRfdHJhY2tfYWRkX3RvX3dpc2hsaXN0Jylcblx0XHRcdCYmICghJHRoaXMtPmNvbmZpZy0+Z2V0KCdjb25maWdfY29va2llX2lkJykgfHwgKGlzc2V0KCR0aGlzLT5yZXF1ZXN0LT5jb29raWVbJ3BvbGljeSddKSAmJiAkdGhpcy0+cmVxdWVzdC0+Y29va2llWydwb2xpY3knXSkpKSB7XG5cdFx0XHR0cnkge1xuXHRcdFx0XHQkdHJhY2tpbmcgPSAkdGhpcy0+bG9hZC0+Y29udHJvbGxlcignZXh0ZW5zaW9uL3BzX2VuaGFuY2VkX21lYXN1cmVtZW50L2FuYWx5dGljcy9wc19lbmhhbmNlZF9tZWFzdXJlbWVudC53aXNobGlzdERhdGEnLCAkcHJvZHVjdCk7XG5cdFx0XHRcdGlmIChpc19hcnJheSgkdHJhY2tpbmcpICYmICR0cmFja2luZykgJGpzb25bJ3BzX2FkZF90b193aXNobGlzdCddID0gJHRyYWNraW5nO1xuXHRcdFx0fSBjYXRjaCAoXFxUaHJvd2FibGUgJGVycm9yKSB7IC8qIE9wdGlvbmFsIGFuYWx5dGljcyBjYW5ub3QgdW5kbyBhIHN1Y2Nlc3NmdWwgd2lzaGxpc3Qgc2F2ZS4gKi8gfVxuXHRcdH1cblx0XHQkdGhpcy0+cmVwbHkoJGpzb24pOyIsImNvdW50IjoxfV19LHsicGF0aCI6ImNhdGFsb2cvdmlldy90ZW1wbGF0ZS9jb21tb24vaGVhZGVyLnR3aWciLCJuZXciOmZhbHNlLCJiZWZvcmUiOiI3M2VkMWM5ODE5MDZiNmIwNzkxY2M4YzMzYzJiM2VhYjU2MzQ3ZWE0NDUzZmI0MDg4NGQ3ZTQ3ZjBmZWQzNGE2IiwiYWZ0ZXIiOiIzMTQ0NDUxYjUyYjQ3NzhlYzdkNGU4ZGU3NWFiYjY1ZmNjYzVjNGNhMTA4NThlMTQ3ZWJhZGIzYmEyZDRmYmI3IiwiZWRpdHMiOlt7ImJlZm9yZSI6IiAgPHNjcmlwdCBzcmM9XCJjYXRhbG9nL3ZpZXcvamF2YXNjcmlwdC9ib29zdGVyLXdpc2hsaXN0LmpzP3Y9IiwiYWZ0ZXIiOiIgIHsjIEFDQzAwNl9HQTRfV0lTSExJU1RfMjAyNjEwMTUgI31cbiAgPHNjcmlwdCBzcmM9XCJjYXRhbG9nL3ZpZXcvamF2YXNjcmlwdC9ib29zdGVyLXdpc2hsaXN0LmpzP3Y9IiwiY291bnQiOjF9XX1dLCJkZXBlbmRlbmNpZXMiOlt7InBhdGgiOiJjYXRhbG9nL21vZGVsL2FjY291bnQvd2lzaGxpc3QucGhwIiwic2hhMjU2IjoiYmI0ZGUxZjJkZjY3YmYwYWI5NmVhMDM0MDg0MTUyOTc1MTUwNjc1YWRmZWJiMjA3NTA2OGVhYmQxYmRkYjk5MiJ9LHsicGF0aCI6ImNhdGFsb2cvY29udHJvbGxlci9jb21tb24vaGVhZGVyLnBocCIsInNoYTI1NiI6IjY0YTk2ZTJmZTUzNzg4N2EyZGU5MDYwNzViYWQ4ZGQ5ODYyZjA0YzliOTY1ZWViNzk3NDVjZmFjZGUwMWE2ZGQifSx7InBhdGgiOiJjYXRhbG9nL3ZpZXcvdGVtcGxhdGUvcHJvZHVjdC90aHVtYi50d2lnIiwic2hhMjU2IjoiNzJkZWM2Y2UzYjBiYmQyMGI2NWU2YmMxOTRkZmY4OGMzNDkzZjJiOWI2ZGNiZjU4YmY2ZDY5MDUxZGQ4ZmNlZCJ9LHsicGF0aCI6ImNhdGFsb2cvdmlldy90ZW1wbGF0ZS9wcm9kdWN0L3Byb2R1Y3QudHdpZyIsInNoYTI1NiI6ImRiMmRlNDVhNmVjYWM5MmNkZTExOWJmODQ1NDZkNzc4ODNiZTczMWYzZGVlNGM3MzdmZjljMTJjZTljNTc0OGUifSx7InBhdGgiOiJleHRlbnNpb24vcHNfZW5oYW5jZWRfbWVhc3VyZW1lbnQvY2F0YWxvZy9tb2RlbC9hbmFseXRpY3MvcHNfZW5oYW5jZWRfbWVhc3VyZW1lbnQucGhwIiwic2hhMjU2IjoiZWVhNThkMWUyNDdhMTZjMmNjNGJjMWU2MWI4YTUwMjhlNDFkYzdlZDU1N2U5YzY1MmQ3NDM4MmY3OTg0YjRiNCJ9LHsicGF0aCI6ImV4dGVuc2lvbi9wc19lbmhhbmNlZF9tZWFzdXJlbWVudC9jYXRhbG9nL3ZpZXcvamF2YXNjcmlwdC9wcy1lbmhhbmNlZC1tZWFzdXJlbWVudC5qcyIsInNoYTI1NiI6ImZhMTAzZTEwMTQwMTE5NmU2NDdkYTRlODMzNDM2YzE5NTgwYmY2ZDIwYjIwYWZkZGExZWI1NDE3NDg1Y2RiMTMifSx7InBhdGgiOiJzeXN0ZW0vZW5naW5lL2xvYWRlci5waHAiLCJzaGEyNTYiOiJlYTIwOTg0NTBkZGM1Njc3NDdlMmQ4OTAwMWQ5OTdjZTkyMWU5NjZhMzEzY2FmYjY4MGEzMDhlZTM2OWE0NzIzIn0seyJwYXRoIjoic3lzdGVtL2VuZ2luZS9hY3Rpb24ucGhwIiwic2hhMjU2IjoiOTFhNGFmMzc3NWU5YzFjNTk5ZmFmNTVmMmIxZjllYWM4OWI4OWQxNzg2NTdjNmJkOGVjN2I2MTA1NDM3M2U3OSJ9LHsicGF0aCI6InN5c3RlbS9saWJyYXJ5L2NhcnQvY3VycmVuY3kucGhwIiwic2hhMjU2IjoiYTA4MGNlZTAyMTcyZmMxMzIxZGZmMWNkMTE3ZDRiZjM3NDgyNDE0NTlhZGVkOTQ2MTQ2YjhlM2VjNGNlMTgyOSJ9LHsicGF0aCI6InN5c3RlbS9saWJyYXJ5L2NhcnQvdGF4LnBocCIsInNoYTI1NiI6ImQ0MDE1NzEyMDg0ZWE3OGUwNzcyN2YwNDNmMDU2ZDM2YzIwNzg1MzRhNzc4NDhkYTM2YTM1N2FlZDI0MzgyZmUifSx7InBhdGgiOiJjYXRhbG9nL21vZGVsL2NhdGFsb2cvcHJvZHVjdC5waHAiLCJzaGEyNTYiOiJlY2Y5NmIyMTRiNDdmMWVmZDVlMzgyNmNhZWRlYWU2Y2MyNWQ4ZDg0NjUwYWU4YTM5MTUzMzBmNmY1NTYxZGM3In0seyJwYXRoIjoiY2F0YWxvZy9tb2RlbC9jYXRhbG9nL21hbnVmYWN0dXJlci5waHAiLCJzaGEyNTYiOiIxMzBjNmI5NmIyZjk4MWQzOWYwYTViZjEyZDZiZmNiNDEyNjAwY2JmOWNlMGM3ZjY2YzkyNjMxNWMzNjg5MjJiIn1dLCJhbGxvd2VkX2V2ZW50cyI6W1siYW5hbHl0aWNzX3BzX2VuaGFuY2VkX21lYXN1cmVtZW50IiwiY2F0YWxvZy92aWV3L2NvbW1vbi9oZWFkZXIvYmVmb3JlIiwiZXh0ZW5zaW9uL3BzX2VuaGFuY2VkX21lYXN1cmVtZW50L2FuYWx5dGljcy9wc19lbmhhbmNlZF9tZWFzdXJlbWVudC5ldmVudENhdGFsb2dWaWV3Q29tbW9uSGVhZGVyQmVmb3JlIl0sWyJhbmFseXRpY3NfcHNfZW5oYW5jZWRfbWVhc3VyZW1lbnQiLCJjYXRhbG9nL3ZpZXcvcHJvZHVjdC90aHVtYi9iZWZvcmUiLCJleHRlbnNpb24vcHNfZW5oYW5jZWRfbWVhc3VyZW1lbnQvYW5hbHl0aWNzL3BzX2VuaGFuY2VkX21lYXN1cmVtZW50LmV2ZW50Q2F0YWxvZ1ZpZXdQcm9kdWN0VGh1bWJCZWZvcmUiXSxbImFuYWx5dGljc19wc19lbmhhbmNlZF9tZWFzdXJlbWVudCIsImNhdGFsb2cvdmlldy9wcm9kdWN0L3Byb2R1Y3QvYmVmb3JlIiwiZXh0ZW5zaW9uL3BzX2VuaGFuY2VkX21lYXN1cmVtZW50L2FuYWx5dGljcy9wc19lbmhhbmNlZF9tZWFzdXJlbWVudC5ldmVudENhdGFsb2dWaWV3UHJvZHVjdFByb2R1Y3RCZWZvcmUiXSxbImFuYWx5dGljc19wc19lbmhhbmNlZF9tZWFzdXJlbWVudCIsImNhdGFsb2cvdmlldy9hY2NvdW50L3dpc2hsaXN0L2JlZm9yZSIsImV4dGVuc2lvbi9wc19lbmhhbmNlZF9tZWFzdXJlbWVudC9hbmFseXRpY3MvcHNfZW5oYW5jZWRfbWVhc3VyZW1lbnQuZXZlbnRDYXRhbG9nVmlld0FjY291bnRXaXNobGlzdEJlZm9yZSJdLFsiYW5hbHl0aWNzX3BzX2VuaGFuY2VkX21lYXN1cmVtZW50IiwiY2F0YWxvZy92aWV3L2FjY291bnQvd2lzaGxpc3RfbGlzdC9iZWZvcmUiLCJleHRlbnNpb24vcHNfZW5oYW5jZWRfbWVhc3VyZW1lbnQvYW5hbHl0aWNzL3BzX2VuaGFuY2VkX21lYXN1cmVtZW50LmV2ZW50Q2F0YWxvZ1ZpZXdBY2NvdW50V2lzaGxpc3RMaXN0QmVmb3JlIl0sWyJjYXB0Y2hhX3BzX2dvb2dsZV9yZWNhcHRjaGEiLCJjYXRhbG9nL3ZpZXcvY29tbW9uL2hlYWRlci9iZWZvcmUiLCJleHRlbnNpb24vcHNfZ29vZ2xlX3JlY2FwdGNoYS9jYXB0Y2hhL3BzX2dvb2dsZV9yZWNhcHRjaGEuZXZlbnRDYXRhbG9nVmlld0NvbW1vbkhlYWRlckJlZm9yZSJdLFsiY2FwdGNoYV9wc19nb29nbGVfcmVjYXB0Y2hhIiwiYWRtaW4vdmlldy9jb21tb24vaGVhZGVyL2JlZm9yZSIsImV4dGVuc2lvbi9wc19nb29nbGVfcmVjYXB0Y2hhL2NhcHRjaGEvcHNfZ29vZ2xlX3JlY2FwdGNoYS5ldmVudEFkbWluVmlld0NvbW1vbkhlYWRlckJlZm9yZSJdLFsibW9kdWxlX3BzX2xpdmVfc2VhcmNoIiwiY2F0YWxvZy92aWV3L2NvbW1vbi9oZWFkZXIvYmVmb3JlIiwiZXh0ZW5zaW9uL3BzX2xpdmVfc2VhcmNoL21vZHVsZS9wc19saXZlX3NlYXJjaC5ldmVudENhdGFsb2dWaWV3Q29tbW9uSGVhZGVyQmVmb3JlIl1dfQ==',true),true);
    if (!is_array($payload) || count($payload['entries'] ?? []) !== 4) acc006_fail('runner_payload_invalid');
    $dry = false; $rollback = null;
    foreach (array_slice($argv,1) as $arg) {
        if ($arg === '--dry-run') $dry = true;
        elseif (strpos($arg,'--rollback=') === 0) $rollback = substr($arg,11);
        else acc006_fail('unknown_argument');
    }
    if ($dry && $rollback !== null) acc006_fail('conflicting_arguments');
    if ($rollback !== null) { acc006_rollback($root,$rollback,$payload); exit(0); }
    $markers = 0;
    foreach ($payload['entries'] as $entry) {
        $relative = $entry['path']; $target = acc006_path($root,$relative,!$entry['new']);
        $before = is_file($target) ? acc006_read($target) : null;
        if ($before !== null && strpos($before,$payload['marker']) !== false) $markers++;
        $plans[$relative] = ['entry'=>$entry,'target'=>$target,'before'=>$before];
    }
    if ($markers > 0) {
        if ($markers !== count($plans)) acc006_fail('partial_application_requires_review');
        foreach ($plans as $relative=>$plan) if (!hash_equals($plan['entry']['after'],hash('sha256',acc006_norm($plan['before'],$relative)))) acc006_fail('installed_content_changed:' . $relative);
        acc006_log('already_applied','yes'); acc006_log('done','ok');
        if (!$dry) @unlink(__FILE__);
        exit(0);
    }
    foreach ($plans as $relative=>&$plan) {
        $entry = $plan['entry'];
        if ($entry['new']) {
            if ($plan['before'] !== null) acc006_fail('new_asset_already_exists');
            $after = $entry['content'];
        } else {
            if (!hash_equals($entry['before'],hash('sha256',acc006_norm($plan['before'],$relative)))) acc006_fail('source_hash_mismatch:' . $relative);
            $after = acc006_edit($plan['before'],$entry['edits']);
            if ($relative === 'catalog/view/template/common/header.twig') $after = acc006_header_bust($after);
        }
        if (!hash_equals($entry['after'],hash('sha256',acc006_norm($after,$relative)))) acc006_fail('candidate_hash_mismatch:' . $relative);
        $plan['after'] = $after;
    }
    unset($plan);
    foreach ($payload['dependencies'] as $dependency) {
        $file = acc006_path($root,$dependency['path']);
        if (!hash_equals($dependency['sha256'],hash_file('sha256',$file))) acc006_fail('dependency_hash_mismatch:' . $dependency['path']);
    }
    acc006_log('source_gate','4_targets_ok');
    acc006_db($root,$payload);
    if ($dry) { acc006_log('dry_run','ok'); acc006_log('done','ok'); exit(0); }
    $backupRoot = $root . '/_patch_backups';
    if (is_link($backupRoot)) acc006_fail('backup_root_symlink');
    if (!is_dir($backupRoot) && !mkdir($backupRoot,0755)) acc006_fail('backup_root_create_failed');
    $backup = $backupRoot . '/ACC-006_wishlist-ga4_20261015-' . gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(4));
    if (!mkdir($backup,0755)) acc006_fail('backup_create_failed');
    $receipt = ['task'=>'ACC-006-GA4','date_utc'=>gmdate('c'),'files'=>[]];
    foreach ($plans as $relative=>$plan) {
        foreach (['original','candidate'] as $kind) {
            if ($kind === 'original' && $plan['before'] === null) continue;
            $file = $backup . '/' . $kind . '/' . $relative;
            if (!is_dir(dirname($file)) && !mkdir(dirname($file),0755,true)) acc006_fail('backup_directory_failed');
            acc006_atomic($file,$kind === 'original' ? $plan['before'] : $plan['after']);
        }
        $receipt['files'][$relative] = ['new'=>$plan['entry']['new'],'original_sha256'=>$plan['before'] === null ? null : hash('sha256',$plan['before']),'candidate_sha256'=>hash('sha256',$plan['after'])];
        if (substr($relative,-4)==='.php') acc006_lint($backup . '/candidate/' . $relative);
    }
    acc006_atomic($backup . '/receipt.json',json_encode($receipt,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    acc006_log('backup',str_replace(DIRECTORY_SEPARATOR,'/',substr($backup,strlen($root)+1)));
    foreach ($plans as $relative=>$plan) {
        $current = is_file($plan['target']) ? acc006_read($plan['target']) : null;
        if ($current !== $plan['before']) acc006_fail('source_changed_during_apply:' . $relative);
        $written[] = $relative;
        acc006_atomic($plan['target'],$plan['after']);
        acc006_log('changed',$relative);
    }
    foreach ($plans as $relative=>$plan) if (substr($relative,-4)==='.php') { acc006_lint($plan['target']); acc006_log('php_lint',$relative . ':ok'); }
    acc006_log('cache_refresh','required_owner_command'); acc006_log('done','ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    $restored = true;
    foreach (array_reverse($written) as $relative) {
        $plan = $plans[$relative];
        try {
            $current = is_file($plan['target']) ? acc006_read($plan['target']) : null;
            if ($current === $plan['before']) continue;
            if ($current !== $plan['after']) acc006_fail('later_change_during_restore');
            if ($plan['before'] === null) { if (!unlink($plan['target'])) acc006_fail('restore_asset_failed'); }
            else acc006_atomic($plan['target'],$plan['before']);
        } catch (Throwable $restoreError) { $restored = false; }
    }
    // Only our fixed failure codes are logged; external DB/OS exception messages stay private.
    $message = $error instanceof Acc006Failure ? $error->getMessage() : 'unexpected_error';
    if (!preg_match('~^[A-Za-z0-9_:/.-]+$~D',$message)) $message = 'unexpected_error';
    acc006_log('error',$message);
    if ($written) acc006_log('automatic_restore',$restored ? 'ok' : 'owner_review_required');
    acc006_log('done','error');
    exit(1);
}
