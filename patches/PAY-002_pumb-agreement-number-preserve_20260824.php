<?php
/**
 * PAY-002 — preserve PUMB agreement_number when a later callback has no letter.
 *
 * Upload to ~/public_html and run:
 *   php PAY-002_pumb-agreement-number-preserve_20260824.php
 *
 * Scope: one controller expression only. No database schema or data changes.
 * Rollback: restore
 * _patch_backups/PAY-002_pumb-agreement-number-preserve_20260824-<timestamp>/
 * extension/pumb_credit/catalog/controller/payment/pumb_credit.php.
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-002_pumb-agreement-number-preserve_20260824';

function out(string $line): void { echo $line . PHP_EOL; }
function fail(string $message): void { fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL); exit(1); }
function ensure(bool $condition, string $message): void { if (!$condition) fail($message); }
function writeFile(string $path, string $contents): void {
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) fail('Cannot create directory: ' . $directory);
    if (file_put_contents($path, $contents) === false) fail('Cannot write: ' . $path);
}

$root = getcwd() ?: '.';
$config = $root . '/config.php';
ensure(is_file($config), 'Run this patch from OpenCart public_html (config.php not found).');
$relative = 'extension/pumb_credit/catalog/controller/payment/pumb_credit.php';
$target = $root . '/' . $relative;
$marker = $root . '/extension/pumb_credit/.pay002-agreement-number-preserve-marker';
ensure(is_file($target), 'Required live controller is missing: ' . $relative);

$source = file_get_contents($target);
ensure(is_string($source), 'Cannot read live controller: ' . $relative);
$old = "`agreement_number`=NULLIF(VALUES(`agreement_number`),''),`payload`=VALUES(`payload`)";
$new = "`agreement_number`=COALESCE(NULLIF(VALUES(`agreement_number`),''),`agreement_number`),`payload`=VALUES(`payload`)";

if (is_file($marker)) {
    ensure(substr_count($source, $new) === 1, 'Marker exists but the protected agreement_number expression is missing.');
    ensure(substr_count($source, $old) === 0, 'Marker exists but the unsafe agreement_number expression remains.');
    out('already_applied=yes');
    exit(0);
}

ensure(substr_count($source, $old) === 1, 'Expected unsafe agreement_number anchor exactly once.');
ensure(substr_count($source, $new) === 0, 'Protected agreement_number expression already exists without marker; refusing ambiguous state.');
$patched = str_replace($old, $new, $source);

$timestamp = date('Ymd-His');
$backup = $root . '/_patch_backups/' . PATCH_ID . '-' . $timestamp;
$backupFile = $backup . '/' . $relative;
$backupDirectory = dirname($backupFile);
if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0755, true) && !is_dir($backupDirectory)) fail('Cannot create backup directory: ' . $backupDirectory);
ensure(copy($target, $backupFile), 'Cannot create controller backup.');
out('cwd=' . $root);
out('time=' . date('c'));
out('backup=' . $backup);

writeFile($target, $patched);
$lint = [];
$status = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($target) . ' 2>&1', $lint, $status);
if ($status !== 0) {
    ensure(copy($backupFile, $target), 'php -l failed and controller restore failed.');
    fail('php -l failed; controller restored from ' . $backupFile . ': ' . implode(' ', $lint));
}
ensure(file_get_contents($target) === $patched, 'Written controller verification failed.');
ensure(substr_count($patched, $new) === 1 && substr_count($patched, $old) === 0, 'Post-write agreement_number verification failed.');
writeFile($marker, PATCH_ID . ' applied ' . date('c') . PHP_EOL);
out('changed=' . $relative);
out('php_l=ok file=' . $relative);
out('agreement_number=preserve_existing_when_incoming_empty');
if (!@unlink(__FILE__)) out('self_delete=failed remove_uploaded_patch_manually=yes');
else out('self_delete=ok');
out('done=ok');
