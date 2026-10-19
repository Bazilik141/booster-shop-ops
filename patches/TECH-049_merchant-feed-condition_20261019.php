<?php
declare(strict_types=1);

/*
 * TECH-049 WP2 — Merchant feed condition and product_type for non-sealed Bulk cards.
 *
 * Upload this file to ~/public_html and run: php TECH-049_merchant-feed-condition_20261019.php
 * Target: ~/merchant-feed-build.php
 *
 * Change: a row whose model contains "-BULK-" (case-insensitive) gets condition
 * "used" and product_type "<family> > Bulk Set"; every other row keeps "new" and
 * its current product_type. Same rule as TECH-049 WP1 in product.twig. The SELECT,
 * headers, id, price, availability, link, image, brand and identifier_exists are
 * not changed. No DB write.
 *
 * This patch never runs the generator and never touches merchant-feed.tsv; the
 * owner re-runs the generator afterwards: php ~/merchant-feed-build.php
 *
 * Rollback: restore the saved target from the backup path printed below, then
 * re-run the generator (or restore the newest merchant-feed.tsv.bak-<stamp>).
 */

const PATCH_ID = 'TECH-049_merchant-feed-condition_20261019';

function patch_fail(string $message): void {
    echo 'error=' . $message . PHP_EOL;
    exit(1);
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
echo 'sha256_before=' . hash('sha256', $source) . PHP_EOL;

$marker = 'function feed_condition_for(array $row): string {';
$markerCount = substr_count($source, $marker);

$typeAnchor = <<<'PHP'
function product_type_for(array $row): string {
    $family = product_family($row);
    $model = (string)($row['model'] ?? '');
    $haystack = $model . ' ' . ($row['name'] ?? '');


PHP;
$conditionAnchor = "        money_uah(\$row['price']),\n        'new',\n        brand_for(\$row),\n";

if ($markerCount === 1 && substr_count($source, $conditionAnchor) === 0) {
    echo 'already_applied=yes' . PHP_EOL;
    @unlink(__FILE__);
    exit(0);
}
if ($markerCount !== 0) {
    patch_fail('partial or unexpected state: condition marker count=' . $markerCount . ' with the literal still present');
}

foreach (['product_type_for' => $typeAnchor, 'condition_literal' => $conditionAnchor] as $name => $anchor) {
    $count = substr_count($source, $anchor);
    if ($count !== 1) {
        patch_fail('expected anchor count != 1 name=' . $name . ' actual=' . $count);
    }
}

$helpers = <<<'PHP'
// TECH-049: non-sealed Bulk / Bulk Mix cards; the same SKU rule as product.twig JSON-LD.
function is_non_sealed_bulk(array $row): bool {
    return stripos((string)($row['model'] ?? ''), '-BULK-') !== false;
}

function feed_condition_for(array $row): string {
    return is_non_sealed_bulk($row) ? 'used' : 'new';
}


PHP;

$bulkBranch = <<<'PHP'
    if (is_non_sealed_bulk($row)) {
        return $family . ' > Bulk Set';
    }


PHP;

$updated = str_replace($typeAnchor, $helpers . $typeAnchor . $bulkBranch, $source);
$updated = str_replace(
    $conditionAnchor,
    "        money_uah(\$row['price']),\n        feed_condition_for(\$row),\n        brand_for(\$row),\n",
    $updated
);

if (substr_count($updated, $marker) !== 1
    || substr_count($updated, 'function is_non_sealed_bulk(array $row): bool {') !== 1
    || substr_count($updated, "return \$family . ' > Bulk Set';") !== 1
    || substr_count($updated, $conditionAnchor) !== 0) {
    patch_fail('post-change marker validation failed');
}

if (!is_dir($backupRoot) && !mkdir($backupRoot, 0750, true)) {
    patch_fail('failed to create patch backup directory');
}

$backup = $backupRoot . '/merchant-feed-build.php';
if (!copy($target, $backup) || hash_file('sha256', $backup) !== hash('sha256', $source)) {
    patch_fail('failed to create target backup');
}
echo 'backup=' . $backup . PHP_EOL;

if (file_put_contents($target, $updated, LOCK_EX) !== strlen($updated)) {
    if (!copy($backup, $target)) {
        patch_fail('write failed and restore also failed; restore manually from: ' . $backup);
    }
    patch_fail('write failed; original generator restored');
}

$lintOutput = [];
$lintCode = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($target) . ' 2>&1', $lintOutput, $lintCode);
echo 'php_lint=' . implode("\n", $lintOutput) . PHP_EOL;
if ($lintCode !== 0) {
    if (!copy($backup, $target)) {
        patch_fail('lint failed and restore also failed; restore manually from: ' . $backup);
    }
    echo 'restored_from=' . $backup . PHP_EOL;
    patch_fail('lint failed; original generator restored');
}

echo 'sha256_after=' . hash_file('sha256', $target) . PHP_EOL;
echo 'changed_files=merchant-feed-build.php' . PHP_EOL;
echo 'next_step=php ~/merchant-feed-build.php' . PHP_EOL;
echo 'done=ok' . PHP_EOL;
@unlink(__FILE__);
