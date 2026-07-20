<?php
/**
 * ST-2c.1 — refresh checkout sidebar totals after a shipping-method save.
 *
 * Changes only catalog/view/javascript/checkout-reskin.js.
 * No database changes. A timestamped source backup is created before writing.
 * Rollback: restore the backed-up checkout-reskin.js from _patch_backups/.
 */

declare(strict_types=1);

$patch = basename(__FILE__, '.php');
$root = __DIR__;
$targetRelative = 'catalog/view/javascript/checkout-reskin.js';
$target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $targetRelative);
$marker = 'ajaxSuccess.st2c1ShippingSummary';

function fail_st2c1(string $message): void {
    fwrite(STDERR, "error=" . $message . PHP_EOL);
    exit(1);
}

function count_st2c1(string $haystack, string $needle): int {
    return substr_count($haystack, $needle);
}

if (!is_file($target)) {
    fail_st2c1('target_missing:' . $targetRelative);
}

$source = file_get_contents($target);

if ($source === false) {
    fail_st2c1('target_read_failed:' . $targetRelative);
}

if (strpos($source, $marker) !== false) {
    echo "already_applied=yes" . PHP_EOL;
    exit(0);
}

$anchor = <<<'JS'
  $(document).on('ajaxSuccess.acc002eNpRepair', function(event, xhr, settings) {
    var url = settings && settings.url ? settings.url : '';
    var json = xhr && xhr.responseJSON ? xhr.responseJSON : null;

    if (!json || !json.address_updated || url.indexOf('checkout/shipping_address') === -1) {
      return;
    }

    window.bsCheckoutClearNpRepairTarget();
    savedNpHydration = { addressId: '', metadata: null, state: '' };
    clearSavedNpPrompt();
    window.setTimeout(hydrateSavedNpAddress, 0);
  });
JS;

$anchorCount = count_st2c1($source, $anchor);

if ($anchorCount !== 1) {
    fail_st2c1('anchor_count=' . $anchorCount . '; expected=1');
}

$insertion = <<<'JS'


  // ST-2c.1: shipping_method.save has stored the live quote; refresh only the
  // cached sidebar/preview summary. It never calls checkout/confirm.confirm.
  var st2c1ShippingSummaryRefreshTimer = null;
  $(document).on('ajaxSuccess.st2c1ShippingSummary', function(event, xhr, settings) {
    var url = settings && settings.url ? settings.url : '';
    var json = xhr && xhr.responseJSON ? xhr.responseJSON : null;

    if (!json || !json.success || url.indexOf('checkout/shipping_method.save') === -1) {
      return;
    }

    window.clearTimeout(st2c1ShippingSummaryRefreshTimer);
    st2c1ShippingSummaryRefreshTimer = window.setTimeout(function() {
      if (typeof window.bsCheckoutRefreshPromoCouponSummary === 'function') {
        window.bsCheckoutRefreshPromoCouponSummary({ quiet: true });
      }
    }, 0);
  });
JS;

$updated = str_replace($anchor, $anchor . $insertion, $source, $replaceCount);

if ($replaceCount !== 1 || $updated === $source) {
    fail_st2c1('replacement_failed');
}

$timestamp = gmdate('Ymd_His');
$backupDir = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . $patch . '_' . $timestamp;
$backup = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $targetRelative);

if (!mkdir($backupDir . DIRECTORY_SEPARATOR . 'catalog' . DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR . 'javascript', 0775, true) && !is_dir(dirname($backup))) {
    fail_st2c1('backup_directory_create_failed');
}

if (!copy($target, $backup)) {
    fail_st2c1('backup_copy_failed');
}

if (file_put_contents($target, $updated) === false) {
    fail_st2c1('target_write_failed');
}

$lintOutput = [];
$lintCode = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $lintOutput, $lintCode);

if ($lintCode !== 0) {
    @copy($backup, $target);
    fail_st2c1('patch_php_lint_failed; restored=yes; detail=' . implode(' | ', $lintOutput));
}

echo 'cwd=' . $root . PHP_EOL;
echo 'backup=' . $backupDir . PHP_EOL;
echo 'changed=' . $targetRelative . PHP_EOL;
echo 'php_l=ok' . PHP_EOL;
echo 'done=ok' . PHP_EOL;

@unlink(__FILE__);
