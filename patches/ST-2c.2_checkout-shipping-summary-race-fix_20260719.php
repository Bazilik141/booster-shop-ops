<?php
/**
 * ST-2c.2 — preserve one checkout summary refresh while another summary request is in flight.
 *
 * Changes only catalog/view/javascript/checkout-reskin.js.
 * No database changes. Rollback: restore the timestamped backup from _patch_backups/.
 */

declare(strict_types=1);

$patch = basename(__FILE__, '.php');
$root = __DIR__;
$targetRelative = 'catalog/view/javascript/checkout-reskin.js';
$target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $targetRelative);
$marker = 'ST-2c.2: keep one quiet summary refresh queued';

function st2c2_fail(string $message): void {
    fwrite(STDERR, 'error=' . $message . PHP_EOL);
    exit(1);
}

if (!is_file($target)) {
    st2c2_fail('target_missing:' . $targetRelative);
}

$source = file_get_contents($target);

if ($source === false) {
    st2c2_fail('target_read_failed:' . $targetRelative);
}

if (strpos($source, $marker) !== false) {
    echo "already_applied=yes" . PHP_EOL;
    exit(0);
}

$anchor = <<<'JS'
    var busy = false;

    function setStatus(message, isError) {
JS;

if (substr_count($source, $anchor) !== 1) {
    st2c2_fail('state_anchor_count=' . substr_count($source, $anchor) . '; expected=1');
}

$updated = str_replace($anchor, <<<'JS'
    var busy = false;
    // ST-2c.2: keep one quiet summary refresh queued while address autosave
    // and the subsequent shipping-method save overlap.
    var queuedQuietSummary = false;

    function setStatus(message, isError) {
JS, $source, $stateReplaceCount);

if ($stateReplaceCount !== 1) {
    st2c2_fail('state_replacement_failed');
}

$requestAnchor = <<<'JS'
    function request(action, data, options) {
      options = options || {};
      if (busy) {
        return;
      }
      busy = true;
      $.ajax({
        url: 'index.php?route=checkout/coupon.' + action,
        type: 'post',
        dataType: 'json',
        data: payload(data),
        success: function(json) {
          render(json, options);
        },
        error: function() {
          setStatus('Не вдалося оновити промокод. Спробуйте ще раз.', true);
        },
        complete: function() {
          busy = false;
        }
      });
    }
JS;

if (substr_count($updated, $requestAnchor) !== 1) {
    st2c2_fail('request_anchor_count=' . substr_count($updated, $requestAnchor) . '; expected=1');
}

$requestReplacement = <<<'JS'
    function request(action, data, options) {
      options = options || {};
      if (busy) {
        if (action === 'summary') {
          queuedQuietSummary = true;
        }
        return;
      }
      busy = true;
      $.ajax({
        url: 'index.php?route=checkout/coupon.' + action,
        type: 'post',
        dataType: 'json',
        data: payload(data),
        success: function(json) {
          render(json, options);
        },
        error: function() {
          setStatus('Не вдалося оновити промокод. Спробуйте ще раз.', true);
        },
        complete: function() {
          var refreshQueued = queuedQuietSummary;
          queuedQuietSummary = false;
          busy = false;

          if (refreshQueued) {
            window.setTimeout(function() {
              request('summary', {}, { quiet: true });
            }, 0);
          }
        }
      });
    }
JS;

$updated = str_replace($requestAnchor, $requestReplacement, $updated, $requestReplaceCount);

if ($requestReplaceCount !== 1 || $updated === $source) {
    st2c2_fail('request_replacement_failed');
}

$timestamp = gmdate('Ymd_His');
$backupDir = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . $patch . '_' . $timestamp;
$backup = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $targetRelative);

if (!mkdir(dirname($backup), 0775, true) && !is_dir(dirname($backup))) {
    st2c2_fail('backup_directory_create_failed');
}

if (!copy($target, $backup)) {
    st2c2_fail('backup_copy_failed');
}

if (file_put_contents($target, $updated) === false) {
    st2c2_fail('target_write_failed');
}

$lintOutput = [];
$lintCode = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $lintOutput, $lintCode);

if ($lintCode !== 0) {
    @copy($backup, $target);
    st2c2_fail('patch_php_lint_failed; restored=yes; detail=' . implode(' | ', $lintOutput));
}

echo 'cwd=' . $root . PHP_EOL;
echo 'backup=' . $backupDir . PHP_EOL;
echo 'changed=' . $targetRelative . PHP_EOL;
echo 'php_l=ok' . PHP_EOL;
echo 'done=ok' . PHP_EOL;

@unlink(__FILE__);
