<?php
/**
 * PAY-002: accept both FUNDED and FOUNDED as the funded PUMB state.
 *
 * Scope: one controller expression only. No database, setting, checkout, or
 * credential change. Roll back by restoring the backed-up controller file.
 */

declare(strict_types=1);

const PAY002_PATCH = 'PAY-002_founded-state-defensive-fix_20260730';
const PAY002_TARGET = 'extension/pumb_credit/catalog/controller/payment/pumb_credit.php';
const PAY002_MARKER = 'extension/pumb_credit/.pay002-founded-state-marker';

function pay002_fail(string $message): void {
    fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL);
    exit(1);
}

function pay002_lint(string $target): void {
    $output = [];
    $exit = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($target) . ' 2>&1', $output, $exit);
    if ($exit !== 0) {
        throw new RuntimeException('php_l_failed: ' . implode(' | ', $output));
    }
}

$target = getcwd() . DIRECTORY_SEPARATOR . PAY002_TARGET;
$marker = getcwd() . DIRECTORY_SEPARATOR . PAY002_MARKER;
$backupDir = '';
$sourceWritten = false;
$old = "\$state === 'FUNDED' ? 'funded' :";
$new = "in_array(\$state, ['FUNDED', 'FOUNDED'], true) ? 'funded' :";

try {
    if (!is_file($target)) {
        throw new RuntimeException('Target file not found: ' . PAY002_TARGET);
    }

    $source = file_get_contents($target);
    if ($source === false) {
        throw new RuntimeException('Unable to read target file.');
    }

    if (is_file($marker)) {
        if (substr_count($source, $new) === 1 && substr_count($source, $old) === 0) {
            echo "already_applied=yes\n";
            exit(0);
        }
        throw new RuntimeException('Idempotency marker exists but controller state mapping is inconsistent; no write performed.');
    }

    if (substr_count($source, $old) !== 1) {
        throw new RuntimeException('Expected FUNDED status-mapping anchor exactly once.');
    }
    if (substr_count($source, $new) !== 0) {
        throw new RuntimeException('FOUNDED dual-state mapping already exists without this patch marker; no write performed.');
    }

    $replacement = str_replace($old, $new, $source);
    if (substr_count($replacement, $new) !== 1 || substr_count($replacement, $old) !== 0) {
        throw new RuntimeException('Post-replacement state-mapping validation failed before write.');
    }

    $timestamp = gmdate('Ymd-His');
    $backupDir = getcwd() . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . PAY002_PATCH . '-' . $timestamp;
    if (!mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
        throw new RuntimeException('Unable to create backup directory.');
    }
    if (!copy($target, $backupDir . DIRECTORY_SEPARATOR . 'pumb_credit.php')) {
        throw new RuntimeException('Unable to back up target controller.');
    }
    file_put_contents($backupDir . DIRECTORY_SEPARATOR . 'source.sha256', hash('sha256', $source) . "  " . PAY002_TARGET . "\n");

    if (file_put_contents($target, $replacement) === false) {
        throw new RuntimeException('Unable to write patched controller.');
    }
    $sourceWritten = true;
    pay002_lint($target);

    if (file_put_contents($marker, "PAY-002 FUNDED/FOUNDED dual-state guard\n") === false) {
        throw new RuntimeException('Unable to write idempotency marker.');
    }

    echo 'cwd=' . getcwd() . PHP_EOL;
    echo 'time=' . gmdate('c') . PHP_EOL;
    echo 'backup=' . $backupDir . PHP_EOL;
    echo 'changed_file=' . PAY002_TARGET . PHP_EOL;
    echo 'changed_file=' . PAY002_MARKER . PHP_EOL;
    echo "php_l=ok\n";
    echo "done=ok\n";
    @unlink(__FILE__);
} catch (Throwable $exception) {
    if ($sourceWritten && $backupDir !== '' && is_file($backupDir . DIRECTORY_SEPARATOR . 'pumb_credit.php')) {
        @copy($backupDir . DIRECTORY_SEPARATOR . 'pumb_credit.php', $target);
        @unlink($marker);
        fwrite(STDERR, "rollback=source_restored\n");
    }
    pay002_fail($exception->getMessage());
}
