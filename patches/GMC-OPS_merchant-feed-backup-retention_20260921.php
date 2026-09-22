<?php
declare(strict_types=1);

/*
 * GMC-OPS — retain only the newest three Merchant feed backups.
 *
 * Upload this file to ~/public_html and run: php GMC-OPS_merchant-feed-backup-retention_20260921.php
 * Target: ~/merchant-feed-build.php
 *
 * Rollback: restore the saved target from the backup path printed below.
 * This patch never changes merchant-feed.tsv or deletes backups itself. The
 * updated generator removes only its own merchant-feed.tsv.bak-YYYYmmdd-HHiiss
 * files after a future successful feed replacement.
 */

const PATCH_ID = 'GMC-OPS_merchant-feed-backup-retention_20260921';

function patch_fail(string $message): void {
    echo 'error=' . $message . PHP_EOL;
    exit(1);
}

function patch_write_file(string $path, string $content): void {
    $tmp = $path . '.tmp-' . getmypid();
    if (file_put_contents($tmp, $content, LOCK_EX) === false) {
        patch_fail('failed to write temporary file: ' . basename($path));
    }
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        patch_fail('failed to replace target: ' . basename($path));
    }
}

$homeRoot = dirname(__DIR__);
$target = $homeRoot . '/merchant-feed-build.php';
$backupRoot = $homeRoot . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');

echo 'cwd=' . getcwd() . PHP_EOL;
echo 'time=' . date('c') . PHP_EOL;
echo 'target=' . $target . PHP_EOL;

if (!is_file($target) || !is_readable($target) || !is_writable($target)) {
    patch_fail('target is missing or not readable/writable: ' . $target);
}

$source = file_get_contents($target);
if ($source === false) {
    patch_fail('failed to read target');
}

$marker = 'const FEED_BACKUP_KEEP = 3;';
if (substr_count($source, $marker) === 1) {
    echo 'already_applied=yes' . PHP_EOL;
    @unlink(__FILE__);
    exit(0);
}
if (substr_count($source, $marker) !== 0) {
    patch_fail('retention marker count is not 0 or 1');
}

$constantsAnchor = "const DEFAULT_PREORDER_STOCK_STATUS_ID = 8;\n";
$functionAnchor = "function clean_text(\$text, int \$limit = 500): string {\n";
$replaceAnchor = "if (!rename(\$tmp, \$target)) {\n    feed_fail('failed to replace merchant-feed.tsv', \$tmp);\n}\n";

foreach ([$constantsAnchor, $functionAnchor, $replaceAnchor] as $anchor) {
    if (substr_count($source, $anchor) !== 1) {
        patch_fail('expected anchor count != 1');
    }
}

$retentionFunction = <<<'PHP'
function prune_feed_backups(string $target, int $keep): array {
    $prefix = basename($target) . '.bak-';
    $candidates = glob($target . '.bak-*') ?: [];
    $backups = [];

    foreach ($candidates as $path) {
        if (!is_file($path)) {
            continue;
        }
        $name = basename($path);
        if (preg_match('/^' . preg_quote($prefix, '/') . '\d{8}-\d{6}$/', $name)) {
            $backups[] = $path;
        }
    }

    rsort($backups, SORT_STRING);
    $removed = 0;
    $failed = [];
    foreach (array_slice($backups, max(0, $keep)) as $path) {
        if (@unlink($path)) {
            $removed++;
        } else {
            $failed[] = basename($path);
        }
    }

    return [
        'kept' => min(count($backups), max(0, $keep)),
        'removed' => $removed,
        'failed' => $failed,
    ];
}

PHP;

$replacement = $constantsAnchor . "const FEED_BACKUP_KEEP = 3;\n";
$updated = str_replace($constantsAnchor, $replacement, $source);
$updated = str_replace($functionAnchor, $retentionFunction . $functionAnchor, $updated);
$updated = str_replace(
    $replaceAnchor,
    $replaceAnchor . "\n\$backup_prune = prune_feed_backups(\$target, FEED_BACKUP_KEEP);\necho 'backups_kept=' . \$backup_prune['kept'] . PHP_EOL;\necho 'backups_removed=' . \$backup_prune['removed'] . PHP_EOL;\nif (\$backup_prune['failed']) {\n    echo 'backup_prune_delete_failed=' . implode(',', \$backup_prune['failed']) . PHP_EOL;\n}\n",
    $updated
);

if (substr_count($updated, $marker) !== 1 || substr_count($updated, 'function prune_feed_backups') !== 1) {
    patch_fail('post-change marker validation failed');
}

if (!is_dir($backupRoot) && !mkdir($backupRoot, 0750, true)) {
    patch_fail('failed to create patch backup directory');
}

$backup = $backupRoot . '/merchant-feed-build.php';
if (!copy($target, $backup)) {
    patch_fail('failed to create target backup');
}
echo 'backup=' . $backup . PHP_EOL;

patch_write_file($target, $updated);
$lintOutput = [];
$lintCode = 0;
exec('php -l ' . escapeshellarg($target) . ' 2>&1', $lintOutput, $lintCode);
echo 'php_lint=' . implode("\n", $lintOutput) . PHP_EOL;
if ($lintCode !== 0) {
    if (!copy($backup, $target)) {
        patch_fail('lint failed and restore also failed; restore manually from: ' . $backup);
    }
    echo 'restored_from=' . $backup . PHP_EOL;
    patch_fail('lint failed; original generator restored');
}

echo 'changed_files=merchant-feed-build.php' . PHP_EOL;
echo 'done=ok' . PHP_EOL;
@unlink(__FILE__);
