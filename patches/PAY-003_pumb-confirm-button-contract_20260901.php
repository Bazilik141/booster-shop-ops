<?php
declare(strict_types=1);

const PATCH_ID = 'PAY-003_pumb-confirm-button-contract_20260901';
const TARGET = 'extension/pumb_credit/catalog/controller/payment/pumb_credit.php';
const BEFORE_SHA256 = 'a91288f5c7a1c0eaa4fc9c52f19aed832abfc2472e5e86ca04a3f82533b53b6b';
const AFTER_SHA256 = '8d3da024b269f0fe9579e03c0b9f701ed495b0d070008e7777fa7f2c1936e708';
const OLD_BUTTON_DATA = "'button_id'=>'pay002-pumb-confirm-button'";
const NEW_BUTTON_DATA = "'button_id'=>'button-confirm'";

function out(string $key, string $value): void {
    echo $key . '=' . $value . PHP_EOL;
}

function fail_patch(string $message): void {
    fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL);
    exit(1);
}

function count_exact(string $haystack, string $needle): int {
    return substr_count($haystack, $needle);
}

function lint_php(string $path): array {
    $output = [];
    $status = 1;
    @exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $status);
    return [$status === 0, implode(' ', $output)];
}

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = getcwd();
$target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, TARGET);

out('cwd', $root);
out('time', gmdate('c'));

if (!is_file($target)) fail_patch('target_not_found:' . TARGET);

$source = file_get_contents($target);
if (!is_string($source)) fail_patch('target_read_failed:' . TARGET);

$currentHash = hash('sha256', $source);
if ($currentHash === AFTER_SHA256 && count_exact($source, NEW_BUTTON_DATA) === 1) {
    out('already_applied', 'yes');
    out('database_touched', 'no');
    out('done', 'ok');
    @unlink(__FILE__);
    exit(0);
}

if ($currentHash !== BEFORE_SHA256) fail_patch('source_sha256_mismatch expected=' . BEFORE_SHA256 . ' actual=' . $currentHash);
if (count_exact($source, OLD_BUTTON_DATA) !== 1) fail_patch('old_button_data_anchor_count=' . count_exact($source, OLD_BUTTON_DATA));
if (count_exact($source, NEW_BUTTON_DATA) !== 0) fail_patch('new_button_data_preexists');

$candidate = str_replace(OLD_BUTTON_DATA, NEW_BUTTON_DATA, $source, $replacements);
if ($replacements !== 1) fail_patch('replacement_count=' . $replacements);
if (hash('sha256', $candidate) !== AFTER_SHA256) fail_patch('candidate_sha256_mismatch');

$temp = $target . '.pay003-candidate-' . bin2hex(random_bytes(4));
if (file_put_contents($temp, $candidate, LOCK_EX) !== strlen($candidate)) {
    @unlink($temp);
    fail_patch('candidate_write_failed');
}

[$candidateOk, $candidateLint] = lint_php($temp);
if (!$candidateOk) {
    @unlink($temp);
    fail_patch('candidate_php_l_failed:' . $candidateLint);
}
out('candidate_sha256', hash_file('sha256', $temp));

$backupDir = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . PATCH_ID . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
$backupTarget = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, TARGET);
if (!is_dir(dirname($backupTarget)) && !mkdir(dirname($backupTarget), 0755, true)) {
    @unlink($temp);
    fail_patch('backup_directory_create_failed');
}
if (!copy($target, $backupTarget)) {
    @unlink($temp);
    fail_patch('backup_copy_failed');
}
if (hash_file('sha256', $backupTarget) !== BEFORE_SHA256) {
    @unlink($temp);
    fail_patch('backup_sha256_mismatch');
}
out('backup', $backupDir);

$written = false;
try {
    if (!rename($temp, $target)) fail_patch('target_replace_failed');
    $written = true;

    [$targetOk, $targetLint] = lint_php($target);
    if (!$targetOk) throw new RuntimeException('target_php_l_failed:' . $targetLint);
    if (hash_file('sha256', $target) !== AFTER_SHA256) throw new RuntimeException('after_sha256_mismatch');

    $after = file_get_contents($target);
    if (!is_string($after) || count_exact($after, NEW_BUTTON_DATA) !== 1 || count_exact($after, OLD_BUTTON_DATA) !== 0) {
        throw new RuntimeException('after_assertion_failed');
    }

    out('php_l', 'ok');
    out('after_sha256', TARGET . ':' . AFTER_SHA256);
    out('changed', TARGET);
    out('assertions', 'ok');
    out('database_touched', 'no');
    out('done', 'ok');
    out('self_delete', @unlink(__FILE__) ? 'ok' : 'failed');
} catch (Throwable $exception) {
    @unlink($temp);
    if ($written && is_file($backupTarget)) {
        @copy($backupTarget, $target);
    }
    fail_patch('source_restored:' . $exception->getMessage());
}
