<?php
/**
 * NCRM-10 round 3: increase only the OpenCart to Edge Function HTTP timeout.
 *
 * Target: system/library/booster_crm_sync.php
 * Database: none.
 * Rollback: restore booster_crm_sync.php from the backup directory printed below.
 */

declare(strict_types=1);

const NCRM10R3_MARKER = 'NCRM-10_ROUND3_SYNC_TIMEOUT_20260719';

function ncrm10r3_fail(string $message): void {
    fwrite(STDERR, 'error=' . $message . PHP_EOL);
    exit(1);
}

function ncrm10r3_count(string $source, string $needle): int {
    return substr_count($source, $needle);
}

function ncrm10r3_lint_file(string $path): void {
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $exitCode);
    if ($exitCode !== 0) {
        ncrm10r3_fail('php_lint_failed target=' . $path . ' output=' . implode(' ', $output));
    }
}

function ncrm10r3_lint_text(string $source, string $label): void {
    $tmp = tempnam(sys_get_temp_dir(), 'ncrm10r3-lint-');
    if ($tmp === false) {
        ncrm10r3_fail('cannot_create_temporary_lint_file target=' . $label);
    }

    file_put_contents($tmp, $source);
    ncrm10r3_lint_file($tmp);
    @unlink($tmp);
}

$rootReal = realpath(__DIR__);
if ($rootReal === false) {
    ncrm10r3_fail('cannot_resolve_public_html');
}

$targetPath = $rootReal . DIRECTORY_SEPARATOR . 'system/library/booster_crm_sync.php';
if (!is_file($targetPath)) {
    ncrm10r3_fail('required_file_missing path=' . $targetPath);
}

$maxExecutionRaw = trim((string) ini_get('max_execution_time'));
if ($maxExecutionRaw === '' || !preg_match('/^[0-9]+$/', $maxExecutionRaw)) {
    ncrm10r3_fail('cannot_verify_max_execution_time value=' . $maxExecutionRaw);
}

$maxExecution = (int) $maxExecutionRaw;
echo 'cwd=' . $rootReal . PHP_EOL;
echo 'time_utc=' . gmdate('c') . PHP_EOL;
echo 'max_execution_time=' . $maxExecution . PHP_EOL;

// 0 means no PHP execution-time limit. A finite limit needs seven seconds of
// headroom above the eight-second HTTP total timeout.
if ($maxExecution > 0 && $maxExecution < 15) {
    ncrm10r3_fail('max_execution_time_too_low required=15 actual=' . $maxExecution);
}

$original = file_get_contents($targetPath);
if ($original === false) {
    ncrm10r3_fail('cannot_read_target path=' . $targetPath);
}

if (strpos($original, NCRM10R3_MARKER) !== false) {
    echo 'already_applied=yes' . PHP_EOL;
    @unlink(__FILE__);
    exit(0);
}

$roundOneMarker = 'NCRM-10_ORDER_SYNC_HOOK_20260718';
$connectAnchor = 'CURLOPT_CONNECTTIMEOUT => 1,';
$timeoutAnchor = 'CURLOPT_TIMEOUT => 2,';
$streamAnchor = "'timeout' => 2,";

if (ncrm10r3_count($original, $roundOneMarker) !== 1) {
    ncrm10r3_fail('anchor_count_invalid anchor=round1_marker expected=1 actual=' . ncrm10r3_count($original, $roundOneMarker));
}
if (ncrm10r3_count($original, $connectAnchor) !== 1) {
    ncrm10r3_fail('anchor_count_invalid anchor=curl_connect_timeout_1 expected=1 actual=' . ncrm10r3_count($original, $connectAnchor));
}
if (ncrm10r3_count($original, $timeoutAnchor) !== 1) {
    ncrm10r3_fail('anchor_count_invalid anchor=curl_timeout_2 expected=1 actual=' . ncrm10r3_count($original, $timeoutAnchor));
}
if (ncrm10r3_count($original, $streamAnchor) !== 1) {
    ncrm10r3_fail('anchor_count_invalid anchor=stream_timeout_2 expected=1 actual=' . ncrm10r3_count($original, $streamAnchor));
}

ncrm10r3_lint_file($targetPath);

$candidate = str_replace($connectAnchor, 'CURLOPT_CONNECTTIMEOUT => 3,', $original);
$candidate = str_replace($timeoutAnchor, 'CURLOPT_TIMEOUT => 8,', $candidate);
$candidate = str_replace(
    $streamAnchor,
    "'timeout' => 8," . PHP_EOL . "                // " . NCRM10R3_MARKER,
    $candidate
);

if (ncrm10r3_count($candidate, 'CURLOPT_CONNECTTIMEOUT => 3,') !== 1
    || ncrm10r3_count($candidate, 'CURLOPT_TIMEOUT => 8,') !== 1
    || ncrm10r3_count($candidate, "'timeout' => 8,") !== 1
    || ncrm10r3_count($candidate, NCRM10R3_MARKER) !== 1) {
    ncrm10r3_fail('post_replace_invariant_failed');
}

ncrm10r3_lint_text($candidate, 'system/library/booster_crm_sync.php candidate');

$backupDir = $rootReal . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . basename(__FILE__, '.php') . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
if (!mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
    ncrm10r3_fail('cannot_create_backup_dir path=' . $backupDir);
}
if (!copy($targetPath, $backupDir . DIRECTORY_SEPARATOR . 'booster_crm_sync.php')) {
    ncrm10r3_fail('backup_copy_failed path=' . $backupDir);
}

try {
    if (file_put_contents($targetPath, $candidate) === false) {
        throw new RuntimeException('target_write_failed');
    }
    ncrm10r3_lint_file($targetPath);

    $final = file_get_contents($targetPath);
    if ($final === false
        || ncrm10r3_count($final, 'CURLOPT_CONNECTTIMEOUT => 3,') !== 1
        || ncrm10r3_count($final, 'CURLOPT_TIMEOUT => 8,') !== 1
        || ncrm10r3_count($final, "'timeout' => 8,") !== 1
        || ncrm10r3_count($final, NCRM10R3_MARKER) !== 1) {
        throw new RuntimeException('post_write_invariant_failed');
    }
} catch (Throwable $e) {
    file_put_contents($targetPath, $original);
    ncrm10r3_fail('write_or_lint_failed rollback=restored reason=' . $e->getMessage());
}

echo 'backup_dir=' . $backupDir . PHP_EOL;
echo 'changed_file=system/library/booster_crm_sync.php' . PHP_EOL;
echo 'done=ok' . PHP_EOL;
@unlink(__FILE__);
