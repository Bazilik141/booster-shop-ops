<?php
/**
 * HOTFIX-001 — Rename the customer-facing pay-in-parts action label.
 *
 * Root cause (verified against backup-8.16.2026_08-03-55_boosters.tar.gz):
 * - catalog/view/template/product/product.twig has one literal action label.
 * - catalog/view/template/checkout/payment_method.twig has five literal labels.
 *
 * No database changes. Run from ~/public_html.
 */

$patchId = 'HOTFIX-001_pay-in-parts-label_20260816';
$oldText = 'Оплатити частинами';
$newText = 'Сплатити частинами';
$root = realpath(getcwd());

function hotfix001_emit(string $message): void {
    global $logPath;

    echo $message . PHP_EOL;

    if (isset($logPath) && is_string($logPath)) {
        @file_put_contents($logPath, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

function hotfix001_fail(string $message): void {
    hotfix001_emit('error=' . $message);
    exit(1);
}

if ($root === false || !is_file($root . '/config.php') || !is_dir($root . '/catalog')) {
    hotfix001_fail('run_this_patch_from_public_html');
}

$backupDir = $root . '/_patch_backups/' . $patchId . '-' . date('Ymd-His');
if (!@mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
    hotfix001_fail('cannot_create_backup_directory');
}

$logPath = $backupDir . '/patch.log';
hotfix001_emit('patch=' . $patchId);
hotfix001_emit('cwd=' . $root);
hotfix001_emit('time=' . date(DATE_ATOM));
hotfix001_emit('backup_dir=' . $backupDir);

$targets = [
    'catalog/view/template/product/product.twig' => 1,
    'catalog/view/template/checkout/payment_method.twig' => 5,
];
$prepared = [];

foreach ($targets as $relativePath => $expectedOldCount) {
    $targetPath = $root . '/' . $relativePath;

    if (!is_file($targetPath)) {
        hotfix001_fail('target_missing=' . $relativePath);
    }

    $source = @file_get_contents($targetPath);
    if ($source === false) {
        hotfix001_fail('target_unreadable=' . $relativePath);
    }

    $oldCount = substr_count($source, $oldText);
    $newCount = substr_count($source, $newText);

    if ($oldCount === 0 && $newCount === $expectedOldCount) {
        hotfix001_emit('already_applied_target=' . $relativePath);
        continue;
    }

    if ($oldCount !== $expectedOldCount || $newCount !== 0) {
        hotfix001_fail(
            'anchor_count_mismatch=' . $relativePath .
            '; expected_old=' . $expectedOldCount .
            '; found_old=' . $oldCount .
            '; found_new=' . $newCount
        );
    }

    $prepared[$relativePath] = str_replace($oldText, $newText, $source);
    hotfix001_emit('anchor_ok=' . $relativePath . '; count=' . $oldCount);
}

if ($prepared === []) {
    hotfix001_emit('already_applied=yes');
    hotfix001_emit('php_l=not_applicable; twig_only_patch=yes');
    hotfix001_emit('done=ok');
    hotfix001_emit('self_deleted=' . (@unlink(__FILE__) ? 'yes' : 'no'));
    exit(0);
}

$backupFiles = [];
foreach (array_keys($prepared) as $relativePath) {
    $targetPath = $root . '/' . $relativePath;
    $backupPath = $backupDir . '/files/' . $relativePath;
    $backupParent = dirname($backupPath);

    if (!@mkdir($backupParent, 0755, true) && !is_dir($backupParent)) {
        hotfix001_fail('cannot_create_backup_parent=' . $relativePath);
    }

    if (!@copy($targetPath, $backupPath)) {
        hotfix001_fail('backup_failed=' . $relativePath);
    }

    $backupFiles[$relativePath] = $backupPath;
    hotfix001_emit('backup_path=' . $backupPath);
}

$written = [];

try {
    foreach ($prepared as $relativePath => $replacement) {
        $targetPath = $root . '/' . $relativePath;
        $tempPath = $targetPath . '.' . $patchId . '.tmp';

        if (@file_put_contents($tempPath, $replacement, LOCK_EX) === false) {
            throw new RuntimeException('write_failed=' . $relativePath);
        }

        if (!@rename($tempPath, $targetPath)) {
            @unlink($tempPath);
            throw new RuntimeException('replace_failed=' . $relativePath);
        }

        $written[] = $relativePath;
        $verified = @file_get_contents($targetPath);

        if ($verified === false || substr_count($verified, $oldText) !== 0 || substr_count($verified, $newText) !== $targets[$relativePath]) {
            throw new RuntimeException('post_write_verification_failed=' . $relativePath);
        }

        hotfix001_emit('changed=' . $relativePath);
    }
} catch (Throwable $exception) {
    foreach (array_reverse($written) as $relativePath) {
        if (isset($backupFiles[$relativePath]) && @copy($backupFiles[$relativePath], $root . '/' . $relativePath)) {
            hotfix001_emit('restored=' . $relativePath);
        } else {
            hotfix001_emit('restore_failed=' . $relativePath);
        }
    }

    hotfix001_fail($exception->getMessage());
}

hotfix001_emit('php_l=not_applicable; twig_only_patch=yes');
hotfix001_emit('cache_cleanup=required');
hotfix001_emit('done=ok');
hotfix001_emit('self_deleted=' . (@unlink(__FILE__) ? 'yes' : 'no'));
