<?php
/**
 * ACC-006 + RD-25: hide mobile-header wishlist; centre canonical card/gallery media.
 * Owner explicitly combined both changes on 2026-10-14; two source targets only.
 * Root causes: CSS bottom object-position and asymmetric cut inset geometry.
 * Edit source rules directly; contain/proportions/fit cache and original images unchanged.
 * Existing inclusive 768px header boundary; config DOM remains for wishlist JS.
 * Fresh 21-file source archive; dependency hashes and read-only active-theme gate.
 * C8: read/validate current DS asset token, replace wholesale; repeat preserves later token.
 * PHP 8.0 CLI runner; no production PHP targets, DB writes or image-cache deletion.
 * Usage: php ACC-006-RD-25_header-media-centering_20261014.php [--dry-run]
 * Rollback: re-upload; --rollback=_patch_backups/<exact printed directory>.
 * Restore-on-failure; source rollback only; later header token changes refuse rollback.
 * Owner refreshes compiled Twig cache after apply/rollback and reloads stylesheet.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

function hm14_log(string $key, string $value): void { echo $key . '=' . $value . PHP_EOL; }
class Hm14Failure extends RuntimeException {}
function hm14_fail(string $code): void { throw new Hm14Failure($code); }
function hm14_asset(string $bytes, ?string $replacement = null): string {
    $pattern = '~(catalog/view/stylesheet/boostershop-ds\.css\?v=)([^"\s<>]+)~';
    if (preg_match_all($pattern,$bytes,$matches) !== 1 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,127}$/D',$matches[2][0])) hm14_fail('asset_token_shape');
    if ($replacement === null) $replacement = '__ASSET_TOKEN__';
    return preg_replace_callback($pattern,static function(array $m)use($replacement):string{return $m[1].$replacement;},$bytes);
}
function hm14_norm(string $bytes, string $path): string {
    $bytes = str_replace("\r\n", "\n", $bytes);
    return $path === 'catalog/view/template/common/header.twig' ? hm14_asset($bytes) : $bytes;
}
function hm14_path(string $root, string $relative, bool $mustExist = true): string {
    if (!preg_match('~^(?:index\.php|(?:catalog/|system/|extension/[A-Za-z0-9_]+/)[A-Za-z0-9_./-]+)$~D', $relative) || strpos($relative, '..') !== false) hm14_fail('unsafe_target');
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $parent = realpath(dirname($path));
    if (!$parent || strpos($parent . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR) !== 0 || is_link($path)) hm14_fail('unsafe_target_parent');
    // Actual reads below are the readability gate; Windows sandbox ACL stat probes can disagree.
    if ($mustExist && !is_file($path)) hm14_fail('target_missing:' . $relative);
    return $path;
}
function hm14_read(string $path): string {
    $bytes = file_get_contents($path);
    if ($bytes === false) hm14_fail('file_read_failed');
    return $bytes;
}
function hm14_atomic(string $path, string $bytes): void {
    // Exclusive creation in the target directory; tempnam can silently fall back elsewhere.
    $temporary = dirname($path) . DIRECTORY_SEPARATOR . '.a7-' . bin2hex(random_bytes(4));
    $handle = @fopen($temporary,'xb');
    if ($handle === false) hm14_fail('temporary_write_failed');
    try {
        if (!flock($handle,LOCK_EX) || fwrite($handle,$bytes) !== strlen($bytes) || !fflush($handle)) hm14_fail('file_write_failed');
        fclose($handle); $handle = null;
        if (is_file($path)) @chmod($temporary, fileperms($path) & 0777);
        else @chmod($temporary, 0644);
        if (!rename($temporary, $path)) hm14_fail('atomic_replace_failed');
    } finally {
        if (is_resource($handle)) fclose($handle);
        if (is_file($temporary)) @unlink($temporary);
    }
    if (!hash_equals(hash('sha256', $bytes), hash_file('sha256', $path))) hm14_fail('written_hash_mismatch');
}
function hm14_lint(string $path): void {
    $output = []; $status = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $status);
    if ($status !== 0) hm14_fail('php_lint_failed:' . basename($path));
}
function hm14_edit(string $source, array $edits): string {
    $sourceEol = strpos($source,"\r\n") !== false ? "\r\n" : "\n";
    foreach ($edits as $edit) {
        $pattern = '~' . str_replace("\n", '\\r?\\n', preg_quote($edit['before'], '~')) . '~';
        if (preg_match_all($pattern, $source, $matches) !== $edit['count']) hm14_fail('anchor_count_mismatch');
        $source = preg_replace_callback($pattern, static function (array $match) use ($edit,$sourceEol): string {
            return str_replace("\n", strpos($match[0], "\r\n") !== false ? "\r\n" : $sourceEol, $edit['after']);
        }, $source);
        if (!is_string($source)) hm14_fail('anchor_replacement_failed');
    }
    return $source;
}
function hm14_rows($db, string $sql): array {
    $result = $db->query($sql);
    if (!$result) hm14_fail('db_preflight_query_failed');
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $result->free();
    return $rows;
}
function hm14_db(string $root, array $payload): void {
    if (!is_file($root.'/config.php') || is_link($root.'/config.php')) hm14_fail('config_missing_or_linked');
    require $root.'/config.php';
    foreach (['DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PREFIX','DB_PORT'] as $name) if (!defined($name)) hm14_fail('db_config_incomplete');
    if (!preg_match('/^[A-Za-z0-9_]+$/D',DB_PREFIX)) hm14_fail('db_prefix_shape');
    if (!class_exists('mysqli')) hm14_fail('mysqli_missing');
    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @new mysqli(DB_HOSTNAME,DB_USERNAME,DB_PASSWORD,DB_DATABASE,(int)DB_PORT);
    if ($db->connect_errno) hm14_fail('db_connection_failed');
    try {
        if (!$db->set_charset('utf8mb4')) hm14_fail('db_charset_failed');
        $sql = "SELECT `route` FROM `".DB_PREFIX."theme` WHERE `status` = 1 AND `route` IN ('common/header','product/product','product/thumb') LIMIT 1";
        if (hm14_rows($db,$sql)) hm14_fail('active_theme_override_requires_review');
        hm14_log('db_preflight','read_only_theme_ok');hm14_log('db_mutations','none');
    } finally { $db->close(); }
}

function hm14_rollback(string $root, string $argument, array $payload): void {
    if (!preg_match('~^_patch_backups/ACC-006-RD-25_header-media-centering_20261014-\d{8}T\d{6}Z-[a-f0-9]{8}$~D', $argument)) hm14_fail('rollback_path_shape');
    $backup = realpath($root . '/' . $argument);
    $backupRoot = realpath($root . '/_patch_backups');
    if (!$backup || !$backupRoot || dirname($backup) !== $backupRoot || is_link($root . '/' . $argument)) hm14_fail('rollback_path_outside_backup_root');
    $manifest = json_decode(hm14_read($backup . '/receipt.json'), true);
    if (!is_array($manifest) || ($manifest['task'] ?? '') !== 'ACC-006-RD-25' || count($manifest['files'] ?? []) !== count($payload['entries'])) hm14_fail('rollback_receipt_shape');
    $restore = [];
    foreach ($payload['entries'] as $entry) {
        $relative = $entry['path']; $meta = $manifest['files'][$relative] ?? null;
        if (!is_array($meta) || ($meta['new'] ?? null) !== $entry['new']) hm14_fail('rollback_receipt_entry');
        $target = hm14_path($root, $relative, false);
        $current = is_file($target) ? hm14_read($target) : null;
        $original = null;
        if (!$entry['new']) {
            $saved = $backup . '/original/' . $relative;
            if (is_link($saved) || !is_file($saved)) hm14_fail('rollback_original_missing');
            $original = hm14_read($saved);
            if (!hash_equals($meta['original_sha256'], hash('sha256',$original)) || !hash_equals($entry['before'],hash('sha256',hm14_norm($original,$relative)))) hm14_fail('rollback_backup_tampered');
        }
        if ($current === null && !$entry['new']) hm14_fail('rollback_target_missing');
        if ($relative === 'catalog/view/template/common/header.twig' && $current !== null && !hash_equals($meta['candidate_sha256'],hash('sha256',$current)) && !hash_equals($meta['original_sha256'],hash('sha256',$current))) hm14_fail('rollback_later_header_token_or_content');
        if ($current !== null) {
            $state = hash('sha256',hm14_norm($current,$relative));
            if (!hash_equals($entry['after'],$state) && !(!$entry['new'] && hash_equals($entry['before'],$state))) hm14_fail('rollback_later_file_change:' . $relative);
        }
        $restore[$relative] = ['target'=>$target,'current'=>$current,'original'=>$original];
    }
    foreach ($restore as $relative=>$item) {
        if ($item['original'] === null) {
            if ($item['current'] !== null && !unlink($item['target'])) hm14_fail('rollback_asset_delete_failed');
        } else {
            hm14_atomic($item['target'],$item['original']);
            if (substr($relative,-4)==='.php') hm14_lint($item['target']);
        }
        hm14_log('restored',$relative);
    }
    hm14_log('rollback','ok'); hm14_log('done','ok');
    @unlink(__FILE__);
}

$written = []; $plans = []; $backup = null;
try {
    if (PHP_VERSION_ID < 80000) hm14_fail('php_8_required');
    if (!function_exists('exec')) hm14_fail('php_lint_command_unavailable');
    $root = realpath(getcwd());
    if (!$root) hm14_fail('cwd_unavailable');
    hm14_log('cwd',$root); hm14_log('time',gmdate('c')); hm14_log('php',PHP_VERSION);
    $payload = json_decode(base64_decode('eyJ0YXNrIjoiQUNDLTAwNi1SRC0yNSIsIm1hcmtlciI6IkFDQy0wMDZfUkQtMjVfSEVBREVSX01FRElBX0NFTlRFUklOR18yMDI2MTAxNCIsImFzc2V0X3Rva2VuIjoiYWNjMDA2LXJkMjUtbWVkaWEtY2VudGVyLTIwMjYxMDE0IiwiYXJjaGl2ZV9zaGEyNTYiOiIzNjE5ZWU1ODU3YjliNDY4YWU1NWUwZjk3YmYwMmJmMmQ1NjdiNmFhMjEwMDhiNDJkMjVkMmM3ZmQ0NDc4N2QwIiwiZW50cmllcyI6W3sicGF0aCI6ImNhdGFsb2cvdmlldy9zdHlsZXNoZWV0L2Jvb3N0ZXJzaG9wLWRzLmNzcyIsIm5ldyI6ZmFsc2UsImJlZm9yZSI6ImUzOTY3OTNkZTIyZmRmYTU4ODI2Nzc1NWEyMmMxM2E1YzliMjYwYWQwODZiOTdhZjk0MmIzZWZlNmFmNmIzNTciLCJhZnRlciI6ImM1YWY0NGI3MmIzNjJhODA5MGIyZjU2OTlkNDFmMDZkMGUyZDE2NjNjYTliODIxNzNjNjZkOWQ2NzQxM2FmMGQiLCJlZGl0cyI6W3siYmVmb3JlIjoiLmJzLXBjYXJkX19tZWRpYS0tY3V0IGltZyB7XG4gIGluc2V0OiBhdXRvOyBsZWZ0OiA4JTsgdG9wOiA2JTsgd2lkdGg6IDg0JTsgaGVpZ2h0OiA5NCU7XG4gIG9iamVjdC1maXQ6IGNvbnRhaW47IG9iamVjdC1wb3NpdGlvbjogY2VudGVyIGJvdHRvbTtcbn0iLCJhZnRlciI6Ii8qIEFDQy0wMDZfUkQtMjVfSEVBREVSX01FRElBX0NFTlRFUklOR18yMDI2MTAxNDogbWlycm9yIHRoZSBleGlzdGluZyA2JSB0b3AgaW5zZXQgYXQgdGhlIGJvdHRvbS4gKi9cbi5icy1wY2FyZF9fbWVkaWEtLWN1dCBpbWcge1xuICBpbnNldDogYXV0bzsgbGVmdDogOCU7IHRvcDogNiU7IHdpZHRoOiA4NCU7IGhlaWdodDogODglO1xuICBvYmplY3QtZml0OiBjb250YWluOyBvYmplY3QtcG9zaXRpb246IGNlbnRlciBjZW50ZXI7XG59IiwiY291bnQiOjF9LHsiYmVmb3JlIjoiLyogUkQtMjUtV1AxLUZJWC1QSE9UTyAyMDI2MTAxMTogd2hvbGUgcGhvdG8sIGJvdHRvbSBhbGlnbmVkOyBlbXB0eSBzcGFjZSBhbGxvd2VkLiAqL1xuLmJzLXBjYXJkX19tZWRpYS0tcGhvdG8gaW1nIHtcbiAgaW5zZXQ6IDA7IHdpZHRoOiAxMDAlOyBoZWlnaHQ6IDEwMCU7IG9iamVjdC1maXQ6IGNvbnRhaW47XG4gIG9iamVjdC1wb3NpdGlvbjogY2VudGVyIGJvdHRvbTsiLCJhZnRlciI6Ii8qIFJELTI1IDIwMjYxMDE0OiB3aG9sZSBwaG90byBjZW50cmVkOyBzcGFyZSBzcGFjZSBzaGFyZWQgYWJvdmUvYmVsb3cuICovXG4uYnMtcGNhcmRfX21lZGlhLS1waG90byBpbWcge1xuICBpbnNldDogMDsgd2lkdGg6IDEwMCU7IGhlaWdodDogMTAwJTsgb2JqZWN0LWZpdDogY29udGFpbjtcbiAgb2JqZWN0LXBvc2l0aW9uOiBjZW50ZXIgY2VudGVyOyIsImNvdW50IjoxfSx7ImJlZm9yZSI6Ii8qIFJELTI1LVdQMi1GSVggMjAyNjEwMTI6IHByaW1hcnkgc2VydmVkIHJhdGlvIGNsYW1wZWQgdG8gMC44Li4xOyBwaG90byBjZW50cmVkLCBjdXQgYm90dG9tLiAqLyIsImFmdGVyIjoiLyogUkQtMjUgMjAyNjEwMTQ6IHN0YWJsZSBwcmltYXJ5IHJhdGlvOyBhbGwgZ2FsbGVyeSBpbWFnZXMgY2VudHJlZC4gKi8iLCJjb3VudCI6MX0seyJiZWZvcmUiOiJtb2JpbGUgcmFpbCA1Nng3MC4gQ3V0IGluc2V0IGlzIGxlZnQgNiUsIHRvcCA0JSwgd2lkdGggODglLCBoZWlnaHQgOTYlLiIsImFmdGVyIjoibW9iaWxlIHJhaWwgNTZ4NzAuIEN1dCBpbnNldCBpcyA2JSBhdCB0aGUgc2lkZXMgYW5kIDQlIGFib3ZlL2JlbG93LiIsImNvdW50IjoxfSx7ImJlZm9yZSI6Ii5icy1wcm9kdWN0X190aHVtYnMgPiBhW2RhdGEtYnMtbWVkaWEta2luZD1cImN1dFwiXSB7IGdyaWQtdGVtcGxhdGUtY29sdW1uczogNiUgODglIDYlOyBncmlkLXRlbXBsYXRlLXJvd3M6IDQlIDk2JTsgfSIsImFmdGVyIjoiLyogTWlycm9yIHRoZSBleGlzdGluZyA0JSB0b3AgaW5zZXQ7IHRoZSBtaWRkbGUgdHJhY2sgaXMgMTAwJSAtIDQlIC0gNCUuICovXG4uYnMtcHJvZHVjdF9fdGh1bWJzID4gYVtkYXRhLWJzLW1lZGlhLWtpbmQ9XCJjdXRcIl0geyBncmlkLXRlbXBsYXRlLWNvbHVtbnM6IDYlIDg4JSA2JTsgZ3JpZC10ZW1wbGF0ZS1yb3dzOiA0JSA5MiUgNCU7IH0iLCJjb3VudCI6MX0seyJiZWZvcmUiOiIuYnMtcHJvZHVjdF9fdGh1bWJzID4gYVtkYXRhLWJzLW1lZGlhLWtpbmQ9XCJjdXRcIl0gPiBpbWcgeyBncmlkLWNvbHVtbjogMjsgZ3JpZC1yb3c6IDI7IG9iamVjdC1wb3NpdGlvbjogY2VudGVyIGJvdHRvbTsgfSIsImFmdGVyIjoiLmJzLXByb2R1Y3RfX3RodW1icyA+IGFbZGF0YS1icy1tZWRpYS1raW5kPVwiY3V0XCJdID4gaW1nIHsgZ3JpZC1jb2x1bW46IDI7IGdyaWQtcm93OiAyOyBvYmplY3QtcG9zaXRpb246IGNlbnRlciBjZW50ZXI7IH0iLCJjb3VudCI6MX0seyJiZWZvcmUiOiIgIC5icy1oZWFkZXJfX2FjdGlvbnMge1xuICAgIGdhcDogNnB4O1xuICB9IiwiYWZ0ZXIiOiIgIC5icy1oZWFkZXJfX2FjdGlvbnMge1xuICAgIGdhcDogNnB4O1xuICB9XG4gIC8qIEV4aXN0aW5nIGhlYWRlciBtb2JpbGUgYm91bmRhcnkgKGluY2x1c2l2ZSA3NjhweCk7IGtlZXAgdGhlIGNvbmZpZyBub2RlIGluIERPTS4gKi9cbiAgLmJzLWhlYWRlcl9fYWN0aW9ucyA+IC5icy1oZWFkZXJfX3dpc2hsaXN0IHsgZGlzcGxheTogbm9uZTsgfSIsImNvdW50IjoxfV0sInJlZnJlc2hfYXNzZXQiOmZhbHNlfSx7InBhdGgiOiJjYXRhbG9nL3ZpZXcvdGVtcGxhdGUvY29tbW9uL2hlYWRlci50d2lnIiwibmV3IjpmYWxzZSwiYmVmb3JlIjoiMzlkMTkzNWNiM2EwNWNjMTBlMjM2NWJiY2ZlMmEyYTE0YTlmOWI2OGNiYTQwOWFjYjQzZmQ4N2UwM2MyYjg3YSIsImFmdGVyIjoiMGE0ODFlNzY0ZTk5ZWE2OWQ2NzA2N2NlNDE1NzBmYzFlNjA1NGM5MjgyNTBiNmNkMDY5NGFhYjgwN2ExNDliOSIsImVkaXRzIjpbeyJiZWZvcmUiOiIgICAgPGRpdiBjbGFzcz1cImJzLWhlYWRlcl9fYWN0aW9uc1wiPiIsImFmdGVyIjoiICAgIHsjIEFDQy0wMDZfUkQtMjVfSEVBREVSX01FRElBX0NFTlRFUklOR18yMDI2MTAxNDogbW9iaWxlIHZpc2liaWxpdHkgb25seTsgd2lzaGxpc3QgY29uZmlnIHJlbWFpbnMgbW91bnRlZC4gI31cbiAgICA8ZGl2IGNsYXNzPVwiYnMtaGVhZGVyX19hY3Rpb25zXCI+IiwiY291bnQiOjF9LHsiYmVmb3JlIjoiY2xhc3M9XCJicy1idG4gYnMtYnRuLXNlY29uZGFyeSBicy1idG4tc21cIiBkYXRhLWJzLXdpc2hsaXN0LWNvbmZpZyIsImFmdGVyIjoiY2xhc3M9XCJicy1idG4gYnMtYnRuLXNlY29uZGFyeSBicy1idG4tc20gYnMtaGVhZGVyX193aXNobGlzdFwiIGRhdGEtYnMtd2lzaGxpc3QtY29uZmlnIiwiY291bnQiOjF9XSwicmVmcmVzaF9hc3NldCI6dHJ1ZX1dLCJkZXBlbmRlbmNpZXMiOlt7InBhdGgiOiJjYXRhbG9nL3ZpZXcvc3R5bGVzaGVldC9zdHlsZXNoZWV0LmNzcyIsInNoYTI1NiI6IjAyMTM1NDMxZjcxNjQwM2NmYjRhOWJhZmIzN2JmMzY3YzI0MmQxNWEyNjdiMmRlODI2OGNiM2I5MDU0NmVhY2QifSx7InBhdGgiOiJjYXRhbG9nL3ZpZXcvc3R5bGVzaGVldC9ib290c3RyYXAuY3NzIiwic2hhMjU2IjoiM2U2Yjg1MjE1MDYxY2QzM2ZjMDI2NzY5NThlZTcwNmMwZTk2Yjg3Nzg5M2MyNDRiZmY0NzNjZTRmYjllY2M1NyJ9LHsicGF0aCI6ImNhdGFsb2cvY29udHJvbGxlci9jb21tb24vaGVhZGVyLnBocCIsInNoYTI1NiI6IjY0YTk2ZTJmZTUzNzg4N2EyZGU5MDYwNzViYWQ4ZGQ5ODYyZjA0YzliOTY1ZWViNzk3NDVjZmFjZGUwMWE2ZGQifSx7InBhdGgiOiJjYXRhbG9nL3ZpZXcvamF2YXNjcmlwdC9ib29zdGVyLXdpc2hsaXN0LmpzIiwic2hhMjU2IjoiNDM4NzZlODFmNGMxYjNmZDgxMjVmYWRmZWZlNTEwYzQxOWViZWQ1ODhkNTQyNzJlODE4ZTE1MjFiMTUzYWViNCJ9LHsicGF0aCI6ImNhdGFsb2cvY29udHJvbGxlci9wcm9kdWN0L3Byb2R1Y3QucGhwIiwic2hhMjU2IjoiN2QyNGY1YjdlYzk5MmNhZTZkNTRmZjU5YTk3NmQ3MTA0OGQ4YmVhZDM3OTkzODJiY2RlYmJiNTA3OWRiYTI2OCJ9LHsicGF0aCI6ImNhdGFsb2cvY29udHJvbGxlci9wcm9kdWN0L3RodW1iLnBocCIsInNoYTI1NiI6ImNmNGY4ZTM1NGIxOGE0MzE4YWYxNTljMzRiZjNjMDgzZDA5OTEwMDdiNDJkMDNlMmM1N2FhNDk2M2MxZWQzYjEifSx7InBhdGgiOiJjYXRhbG9nL3ZpZXcvdGVtcGxhdGUvcHJvZHVjdC9wcm9kdWN0LnR3aWciLCJzaGEyNTYiOiJkYjJkZTQ1YTZlY2FjOTJjZGUxMTliZjg0NTQ2ZDc3ODgzYmU3MzFmM2RlZTRjNzM3ZmY5YzEyY2U5YzU3NDhlIn0seyJwYXRoIjoiY2F0YWxvZy92aWV3L3RlbXBsYXRlL3Byb2R1Y3QvdGh1bWIudHdpZyIsInNoYTI1NiI6IjcyZGVjNmNlM2IwYmJkMjBiNjVlNmJjMTk0ZGZmODhjMzQ5M2YyYjliNmRjYmY1OGJmNmQ2OTA1MWRkOGZjZWQifSx7InBhdGgiOiJjYXRhbG9nL21vZGVsL3Rvb2wvaW1hZ2UucGhwIiwic2hhMjU2IjoiODVjZjk0ODkyNDRjNDhmZTkxNWJjMjVmMzcwY2RmZGIyYjM5YzljNmQ2YTQyMjE5Y2VjN2Y4NGE5ZTM3NWU3MSJ9LHsicGF0aCI6InN5c3RlbS9saWJyYXJ5L2ltYWdlLnBocCIsInNoYTI1NiI6IjdmODFkY2JiOGI1ZDJkMDMyMDA1YWUyOGNlMDFjZWE1YmE3NTQyZjczMzRmZDk5MDllNTM4ZjY5NTJhZGVmZjkifSx7InBhdGgiOiJleHRlbnNpb24vcHNfZW5oYW5jZWRfbWVhc3VyZW1lbnQvY2F0YWxvZy9jb250cm9sbGVyL2FuYWx5dGljcy9wc19lbmhhbmNlZF9tZWFzdXJlbWVudC5waHAiLCJzaGEyNTYiOiI3MjczYWMzNzMzYzMyMzNjYzUyYzg2ZjA3YWEyYTQ3MTRhZGI1NzVkNTcwZTkwMTgwOGM2MWQzNDk4ZWZmMDBlIn0seyJwYXRoIjoiZXh0ZW5zaW9uL3BzX2VuaGFuY2VkX21lYXN1cmVtZW50L2NhdGFsb2cvbW9kZWwvYW5hbHl0aWNzL3BzX2VuaGFuY2VkX21lYXN1cmVtZW50LnBocCIsInNoYTI1NiI6ImVlYTU4ZDFlMjQ3YTE2YzJjYzRiYzFlNjFiOGE1MDI4ZTQxZGM3ZWQ1NTdlOWM2NTJkNzQzODJmNzk4NGI0YjQifV19',true),true);
    if (!is_array($payload) || count($payload['entries'] ?? []) !== 2) hm14_fail('runner_payload_invalid');
    $dry = false; $rollback = null;
    foreach (array_slice($argv,1) as $arg) {
        if ($arg === '--dry-run') $dry = true;
        elseif (strpos($arg,'--rollback=') === 0) $rollback = substr($arg,11);
        else hm14_fail('unknown_argument');
    }
    if ($dry && $rollback !== null) hm14_fail('conflicting_arguments');
    if ($rollback !== null) { hm14_rollback($root,$rollback,$payload); exit(0); }
    $markers = 0;
    foreach ($payload['entries'] as $entry) {
        $relative = $entry['path']; $target = hm14_path($root,$relative,!$entry['new']);
        $before = is_file($target) ? hm14_read($target) : null;
        if ($before !== null && strpos($before,$payload['marker']) !== false) $markers++;
        $plans[$relative] = ['entry'=>$entry,'target'=>$target,'before'=>$before];
    }
    if ($markers > 0) {
        if ($markers !== count($plans)) hm14_fail('partial_application_requires_review');
        foreach ($plans as $relative=>$plan) if (!hash_equals($plan['entry']['after'],hash('sha256',hm14_norm($plan['before'],$relative)))) hm14_fail('installed_content_changed:' . $relative);
        hm14_log('already_applied','yes'); hm14_log('done','ok');
        if (!$dry) @unlink(__FILE__);
        exit(0);
    }
    foreach ($plans as $relative=>&$plan) {
        $entry = $plan['entry'];
        if ($entry['new']) {
            if ($plan['before'] !== null) hm14_fail('new_asset_already_exists');
            $after = $entry['content'];
        } else {
            if (!hash_equals($entry['before'],hash('sha256',hm14_norm($plan['before'],$relative)))) hm14_fail('source_hash_mismatch:' . $relative);
            $after = hm14_edit($plan['before'],$entry['edits']);
        }
        if (!empty($entry['refresh_asset'])) $after = hm14_asset($after,$payload['asset_token']);
        if (!hash_equals($entry['after'],hash('sha256',hm14_norm($after,$relative)))) hm14_fail('candidate_hash_mismatch:' . $relative);
        $plan['after'] = $after;
    }
    unset($plan);
    foreach ($payload['dependencies'] as $dependency) {
        $file = hm14_path($root,$dependency['path']);
        if (!hash_equals($dependency['sha256'],hash_file('sha256',$file))) hm14_fail('dependency_hash_mismatch:' . $dependency['path']);
    }
    hm14_log('source_gate','2_targets_ok');
    hm14_db($root,$payload);
    if ($dry) { hm14_log('dry_run','ok'); hm14_log('done','ok'); exit(0); }
    $backupRoot = $root . '/_patch_backups';
    if (is_link($backupRoot)) hm14_fail('backup_root_symlink');
    if (!is_dir($backupRoot) && !mkdir($backupRoot,0755)) hm14_fail('backup_root_create_failed');
    $backup = $backupRoot . '/ACC-006-RD-25_header-media-centering_20261014-' . gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(4));
    if (!mkdir($backup,0755)) hm14_fail('backup_create_failed');
    $receipt = ['task'=>'ACC-006-RD-25','date_utc'=>gmdate('c'),'files'=>[]];
    foreach ($plans as $relative=>$plan) {
        foreach (['original','candidate'] as $kind) {
            if ($kind === 'original' && $plan['before'] === null) continue;
            $file = $backup . '/' . $kind . '/' . $relative;
            if (!is_dir(dirname($file)) && !mkdir(dirname($file),0755,true)) hm14_fail('backup_directory_failed');
            hm14_atomic($file,$kind === 'original' ? $plan['before'] : $plan['after']);
        }
        $receipt['files'][$relative] = ['new'=>$plan['entry']['new'],'original_sha256'=>$plan['before'] === null ? null : hash('sha256',$plan['before']),'candidate_sha256'=>hash('sha256',$plan['after'])];
        if (substr($relative,-4)==='.php') hm14_lint($backup . '/candidate/' . $relative);
    }
    hm14_atomic($backup . '/receipt.json',json_encode($receipt,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    hm14_log('backup',str_replace(DIRECTORY_SEPARATOR,'/',substr($backup,strlen($root)+1)));
    foreach ($plans as $relative=>$plan) {
        $current = is_file($plan['target']) ? hm14_read($plan['target']) : null;
        if ($current !== $plan['before']) hm14_fail('source_changed_during_apply:' . $relative);
        $written[] = $relative;
        hm14_atomic($plan['target'],$plan['after']);
        hm14_log('changed',$relative);
    }
    foreach ($plans as $relative=>$plan) if (substr($relative,-4)==='.php') { hm14_lint($plan['target']); hm14_log('php_lint',$relative . ':ok'); }
    hm14_log('cache_refresh','required_owner_command'); hm14_log('done','ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    $restored = true;
    foreach (array_reverse($written) as $relative) {
        $plan = $plans[$relative];
        try {
            $current = is_file($plan['target']) ? hm14_read($plan['target']) : null;
            if ($current === $plan['before']) continue;
            if ($current !== $plan['after']) hm14_fail('later_change_during_restore');
            if ($plan['before'] === null) { if (!unlink($plan['target'])) hm14_fail('restore_asset_failed'); }
            else hm14_atomic($plan['target'],$plan['before']);
        } catch (Throwable $restoreError) { $restored = false; }
    }
    // Only our fixed failure codes are logged; external DB/OS exception messages stay private.
    $message = $error instanceof Hm14Failure ? $error->getMessage() : 'unexpected_error';
    if (!preg_match('~^[A-Za-z0-9_:/.-]+$~D',$message)) $message = 'unexpected_error';
    hm14_log('error',$message);
    if ($written) hm14_log('automatic_restore',$restored ? 'ok' : 'owner_review_required');
    hm14_log('done','error');
    exit(1);
}
