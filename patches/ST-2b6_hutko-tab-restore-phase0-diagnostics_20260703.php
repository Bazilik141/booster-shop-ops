<?php
declare(strict_types=1);

/*
 * ST-2b.6 Phase 0 only: instrument checkout tab/browser restore behavior.
 *
 * Behavior changes: none. This patch only records browser lifecycle and
 * checkout/confirm.confirm trigger evidence in the browser console/localStorage.
 * Database changes: none.
 *
 * Rollback:
 * 1. Restore catalog/view/template/checkout/checkout.twig from the backup path
 *    printed by this patch.
 * 2. Clear OpenCart cache.* and template cache using DIR_CACHE from config.php.
 */

const BS_ST2B6_PATCH_ID = 'ST-2b6_hutko-tab-restore-phase0-diagnostics_20260703';
const BS_ST2B6_TARGET = 'catalog/view/template/checkout/checkout.twig';
const BS_ST2B6_LIVE_SHA256 = 'd1f83b562f7ff39e7ad04a52d15e492f666ebaa40b13080035ef21d2c8c83bd8';
const BS_ST2B6_MARKER = 'ST-2b.6 Phase 0 tab-restore diagnostics';

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

function bs_st2b6_out(string $key, string $value = ''): void
{
    echo $key . ($value !== '' ? '=' . $value : '') . PHP_EOL;
}

function bs_st2b6_fail(string $message): void
{
    throw new RuntimeException($message);
}

function bs_st2b6_path(string $relative): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function bs_st2b6_read(string $relative): string
{
    $path = bs_st2b6_path($relative);

    if (!is_file($path)) {
        bs_st2b6_fail('missing_file:' . $relative);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        bs_st2b6_fail('read_failed:' . $relative);
    }

    return $content;
}

function bs_st2b6_assert_count(string $content, string $needle, int $expected, string $label): void
{
    $actual = substr_count($content, $needle);

    if ($actual !== $expected) {
        bs_st2b6_fail(
            'anchor_count_mismatch:' . $label .
            ':expected=' . $expected .
            ':actual=' . $actual
        );
    }
}

function bs_st2b6_replace_once(string $content, string $search, string $replace, string $label): string
{
    bs_st2b6_assert_count($content, $search, 1, $label);

    return str_replace($search, $replace, $content);
}

function bs_st2b6_php_lint(string $file): void
{
    if (!function_exists('exec')) {
        bs_st2b6_fail('php_lint_unavailable:exec_disabled');
    }

    $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
    $output = [];
    $code = 1;
    exec(escapeshellarg($php) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);

    if ($code !== 0) {
        bs_st2b6_fail('php_lint_failed:' . implode(' | ', $output));
    }

    bs_st2b6_out('php_l_patch', 'ok');
}

function bs_st2b6_backup(string $relative, string $backupRoot): string
{
    $source = bs_st2b6_path($relative);
    $backup = $backupRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $backupDir = dirname($backup);

    if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
        bs_st2b6_fail('backup_directory_create_failed:' . $backupDir);
    }

    if (!copy($source, $backup)) {
        bs_st2b6_fail('backup_copy_failed:' . $relative);
    }

    bs_st2b6_out('backup', $backup);

    return $backup;
}

function bs_st2b6_write_atomic(string $relative, string $content): void
{
    $target = bs_st2b6_path($relative);
    $temp = $target . '.st2b6-tmp-' . getmypid();

    if (file_put_contents($temp, $content, LOCK_EX) !== strlen($content)) {
        @unlink($temp);
        bs_st2b6_fail('temporary_write_failed:' . $relative);
    }

    if (!@rename($temp, $target)) {
        $written = file_put_contents($target, $content, LOCK_EX);
        @unlink($temp);

        if ($written !== strlen($content)) {
            bs_st2b6_fail('target_write_failed:' . $relative);
        }
    }
}

$backup = null;
$targetPath = bs_st2b6_path(BS_ST2B6_TARGET);

try {
    bs_st2b6_out('patch', BS_ST2B6_PATCH_ID);
    bs_st2b6_out('cwd', __DIR__);
    bs_st2b6_out('time', gmdate('c'));
    bs_st2b6_out('scope', 'Phase 0 browser diagnostics only');
    bs_st2b6_out('db_schema_changes', 'none');
    bs_st2b6_out('db_data_changes', 'none');

    $original = bs_st2b6_read(BS_ST2B6_TARGET);
    $normalized = str_replace("\r\n", "\n", $original);
    $hadCrLf = strpos($original, "\r\n") !== false;

    if (strpos($normalized, BS_ST2B6_MARKER) !== false) {
        bs_st2b6_assert_count($normalized, BS_ST2B6_MARKER, 1, 'marker');
        bs_st2b6_assert_count($normalized, 'window.bsSt2b6ReadDiagnostics', 1, 'read_helper');
        bs_st2b6_assert_count($normalized, 'window.bsSt2b6ClearDiagnostics', 1, 'clear_helper');
        bs_st2b6_assert_count(
            $normalized,
            'route=checkout/confirm.confirm&language={{ language }}',
            1,
            'confirm_request'
        );
        bs_st2b6_out('already_applied', 'yes');
        bs_st2b6_out('changed_files', 'none');
        bs_st2b6_out('done', 'ok');
        @unlink(__FILE__);
        exit(0);
    }

    if (
        strpos($normalized, 'bsSt2b6Log') !== false ||
        strpos($normalized, 'bs.st2b6.diag.v1') !== false
    ) {
        bs_st2b6_fail('partial_diagnostic_markers_found');
    }

    $actualHash = hash('sha256', $original);

    if (!hash_equals(BS_ST2B6_LIVE_SHA256, $actualHash)) {
        bs_st2b6_fail(
            'live_sha256_mismatch:expected=' . BS_ST2B6_LIVE_SHA256 .
            ':actual=' . $actualHash
        );
    }

    bs_st2b6_assert_count($normalized, 'checkout/confirm.confirm', 1, 'confirm_route_before');
    bs_st2b6_assert_count(
        $normalized,
        'window.bsCheckoutLoadConfirmAndSubmit = function(trigger) {',
        1,
        'submit_function_before'
    );
    bs_st2b6_assert_count(
        $normalized,
        "    \$('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}', function(response, status) {\n",
        1,
        'confirm_load_before'
    );
    bs_st2b6_assert_count(
        $normalized,
        "      window.bsCheckoutLoadConfirmAndSubmit(this);\n",
        1,
        'deferred_handler_call_before'
    );
    bs_st2b6_assert_count(
        $normalized,
        "  function bsCheckoutEscapeHtml(value) {\n" .
        "    return String(value || '').replace(/[&<>\"']/g, function(chr) {\n" .
        "      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;', \"'\": '&#039;'}[chr];\n" .
        "    });\n" .
        "  }\n",
        1,
        'escape_helper_before'
    );

    bs_st2b6_php_lint(__FILE__);

    $diagnostics = <<<'TWIG'

  // ST-2b.6 Phase 0 tab-restore diagnostics.
  // Temporary observability only. Remove after evidence is captured.
  var bsSt2b6StorageKey = 'bs.st2b6.diag.v1';
  var bsSt2b6MaxEntries = 200;

  function bsSt2b6DescribeNode(node) {
    if (!node) {
      return '';
    }

    var parts = [];

    if (node.tagName) {
      parts.push(String(node.tagName).toLowerCase());
    }

    if (node.id) {
      parts.push('#' + node.id);
    }

    if (node.name) {
      parts.push('[name="' + node.name + '"]');
    }

    if (node.type) {
      parts.push('[type="' + node.type + '"]');
    }

    return parts.join('');
  }

  function bsSt2b6NativeEvent(event) {
    return event && event.originalEvent ? event.originalEvent : event;
  }

  function bsSt2b6ReadStored() {
    try {
      var stored = window.localStorage.getItem(bsSt2b6StorageKey);
      var parsed = stored ? JSON.parse(stored) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
      return [];
    }
  }

  function bsSt2b6NavigationType() {
    try {
      var entries = window.performance && window.performance.getEntriesByType
        ? window.performance.getEntriesByType('navigation')
        : [];
      return entries.length && entries[0].type ? entries[0].type : '';
    } catch (error) {
      return '';
    }
  }

  function bsSt2b6SelectionState() {
    return {
      paymentCode: $('#input-payment-code').val() || '',
      paymentMethod: $('#input-payment-method').val() || '',
      shippingCode: $('#input-shipping-code').val() || '',
      shippingMethod: $('#input-shipping-method').val() || ''
    };
  }

  window.__bsSt2b6Diag = bsSt2b6ReadStored();

  window.bsSt2b6ReadDiagnostics = function() {
    return bsSt2b6ReadStored();
  };

  window.bsSt2b6ClearDiagnostics = function() {
    try {
      window.localStorage.removeItem(bsSt2b6StorageKey);
    } catch (error) {
      // Keep the in-memory buffer available even if storage is blocked.
    }

    window.__bsSt2b6Diag = [];
    return true;
  };

  window.bsSt2b6Log = function(source, event, trigger, extra) {
    var nativeEvent = bsSt2b6NativeEvent(event);
    var selection = bsSt2b6SelectionState();
    var entry = {
      sequence: window.__bsSt2b6Diag.length + 1,
      time: new Date().toISOString(),
      source: source || '',
      eventType: nativeEvent && nativeEvent.type
        ? nativeEvent.type
        : (event && event.type ? event.type : ''),
      isTrusted: nativeEvent && typeof nativeEvent.isTrusted !== 'undefined'
        ? nativeEvent.isTrusted
        : null,
      persisted: nativeEvent && typeof nativeEvent.persisted !== 'undefined'
        ? nativeEvent.persisted
        : null,
      activeElement: bsSt2b6DescribeNode(document.activeElement),
      target: bsSt2b6DescribeNode(event && event.target ? event.target : (nativeEvent && nativeEvent.target ? nativeEvent.target : null)),
      currentTarget: bsSt2b6DescribeNode(event && event.currentTarget ? event.currentTarget : (nativeEvent && nativeEvent.currentTarget ? nativeEvent.currentTarget : null)),
      trigger: bsSt2b6DescribeNode(trigger),
      visibilityState: document.visibilityState || '',
      documentHidden: !!document.hidden,
      documentHasFocus: typeof document.hasFocus === 'function' ? document.hasFocus() : null,
      wasDiscarded: typeof document.wasDiscarded !== 'undefined' ? document.wasDiscarded : null,
      navigationType: bsSt2b6NavigationType(),
      paymentCode: selection.paymentCode,
      paymentMethod: selection.paymentMethod,
      shippingCode: selection.shippingCode,
      shippingMethod: selection.shippingMethod,
      confirmSubmitting: !!bsCheckoutConfirmSubmitting,
      stack: (new Error('ST-2b.6 diagnostic stack')).stack || ''
    };

    if (extra) {
      Object.keys(extra).forEach(function(key) {
        entry[key] = extra[key];
      });
    }

    window.__bsSt2b6Diag.push(entry);

    if (window.__bsSt2b6Diag.length > bsSt2b6MaxEntries) {
      window.__bsSt2b6Diag = window.__bsSt2b6Diag.slice(-bsSt2b6MaxEntries);
    }

    try {
      window.localStorage.setItem(bsSt2b6StorageKey, JSON.stringify(window.__bsSt2b6Diag));
    } catch (error) {
      entry.storageError = String(error && error.message ? error.message : error);
    }

    if (window.console && console.warn) {
      console.warn('[ST-2b.6 diagnostic]', entry);
    }

    return entry;
  };

  window.addEventListener('pageshow', function(event) {
    window.bsSt2b6Log('window:pageshow', event, null);
  });

  window.addEventListener('pagehide', function(event) {
    window.bsSt2b6Log('window:pagehide', event, null);
  });

  document.addEventListener('visibilitychange', function(event) {
    window.bsSt2b6Log('document:visibilitychange', event, null);
  });

  $(document).on('ajaxSend.bsSt2b6Diag', function(event, xhr, settings) {
    var url = settings && settings.url ? String(settings.url) : '';

    if (url.indexOf('checkout/confirm.confirm') !== -1) {
      window.bsSt2b6Log('ajaxSend:checkout/confirm.confirm', event, null, {
        requestMethod: settings && settings.type ? String(settings.type) : ''
      });
    }
  });

  window.bsSt2b6Log('checkout:init', null, null);
TWIG;

    $escapeHelper = "  function bsCheckoutEscapeHtml(value) {\n" .
        "    return String(value || '').replace(/[&<>\"']/g, function(chr) {\n" .
        "      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;', \"'\": '&#039;'}[chr];\n" .
        "    });\n" .
        "  }\n";

    $patched = bs_st2b6_replace_once(
        $normalized,
        $escapeHelper,
        $escapeHelper . $diagnostics . "\n",
        'insert_diagnostics'
    );

    $patched = bs_st2b6_replace_once(
        $patched,
        "  window.bsCheckoutLoadConfirmAndSubmit = function(trigger) {\n",
        "  window.bsCheckoutLoadConfirmAndSubmit = function(trigger, event) {\n" .
        "    if (window.bsSt2b6Log) {\n" .
        "      window.bsSt2b6Log('bsCheckoutLoadConfirmAndSubmit:entry', event || null, trigger || null);\n" .
        "    }\n",
        'instrument_submit_entry'
    );

    $patched = bs_st2b6_replace_once(
        $patched,
        "    \$('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}', function(response, status) {\n",
        "    if (window.bsSt2b6Log) {\n" .
        "      window.bsSt2b6Log('checkout.twig:confirm.confirm:before-load', event || null, trigger || null);\n" .
        "    }\n\n" .
        "    \$('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}', function(response, status) {\n" .
        "      if (window.bsSt2b6Log) {\n" .
        "        window.bsSt2b6Log('checkout.twig:confirm.confirm:complete', event || null, trigger || null, { requestStatus: status || '' });\n" .
        "      }\n",
        'instrument_confirm_load'
    );

    $patched = bs_st2b6_replace_once(
        $patched,
        "  \$(document).on('click.bsSt2b1DeferredConfirm', '#checkout-confirm [data-bs-deferred-confirm]', function(event) {\n" .
        "    event.preventDefault();\n",
        "  \$(document).on('click.bsSt2b1DeferredConfirm', '#checkout-confirm [data-bs-deferred-confirm]', function(event) {\n" .
        "    if (window.bsSt2b6Log) {\n" .
        "      window.bsSt2b6Log('deferred-confirm:click', event, this);\n" .
        "    }\n\n" .
        "    event.preventDefault();\n",
        'instrument_deferred_click'
    );

    $patched = bs_st2b6_replace_once(
        $patched,
        "      window.bsCheckoutLoadConfirmAndSubmit(this);\n",
        "      window.bsCheckoutLoadConfirmAndSubmit(this, event);\n",
        'pass_click_event'
    );

    bs_st2b6_assert_count($patched, BS_ST2B6_MARKER, 1, 'marker_after');
    bs_st2b6_assert_count($patched, 'checkout/confirm.confirm', 3, 'confirm_route_text_after');
    bs_st2b6_assert_count($patched, "route=checkout/confirm.confirm&language={{ language }}", 1, 'confirm_request_after');
    bs_st2b6_assert_count($patched, 'window.bsSt2b6ReadDiagnostics', 1, 'read_helper_after');
    bs_st2b6_assert_count($patched, 'window.bsSt2b6ClearDiagnostics', 1, 'clear_helper_after');
    bs_st2b6_assert_count($patched, 'window.bsCheckoutLoadConfirmAndSubmit(this, event);', 1, 'event_pass_after');
    bs_st2b6_assert_count($patched, "window.addEventListener('pageshow'", 1, 'pageshow_after');
    bs_st2b6_assert_count($patched, "window.addEventListener('pagehide'", 1, 'pagehide_after');
    bs_st2b6_assert_count($patched, "document.addEventListener('visibilitychange'", 1, 'visibility_after');

    $final = $hadCrLf ? str_replace("\n", "\r\n", $patched) : $patched;
    $backupRoot = __DIR__ . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR .
        BS_ST2B6_PATCH_ID . '-' . date('Ymd-His');
    $backup = bs_st2b6_backup(BS_ST2B6_TARGET, $backupRoot);

    bs_st2b6_write_atomic(BS_ST2B6_TARGET, $final);

    $written = bs_st2b6_read(BS_ST2B6_TARGET);
    $writtenNormalized = str_replace("\r\n", "\n", $written);

    bs_st2b6_assert_count($writtenNormalized, BS_ST2B6_MARKER, 1, 'written_marker');
    bs_st2b6_assert_count($writtenNormalized, "route=checkout/confirm.confirm&language={{ language }}", 1, 'written_confirm_request');
    bs_st2b6_assert_count($writtenNormalized, 'window.bsSt2b6ReadDiagnostics', 1, 'written_read_helper');
    bs_st2b6_assert_count($writtenNormalized, 'window.bsSt2b6ClearDiagnostics', 1, 'written_clear_helper');

    bs_st2b6_out('changed', BS_ST2B6_TARGET);
    bs_st2b6_out('changed_files', BS_ST2B6_TARGET);
    bs_st2b6_out('target_php_lint', 'not_applicable:twig_only');
    bs_st2b6_out('diagnostic_storage', 'localStorage:bs.st2b6.diag.v1');
    bs_st2b6_out('diagnostic_readout', 'window.bsSt2b6ReadDiagnostics()');
    bs_st2b6_out('rollback', 'restore=' . $backup . ';then_clear_template_cache');
    bs_st2b6_out('done', 'ok');

    @unlink(__FILE__);
} catch (Throwable $error) {
    bs_st2b6_out('error', $error->getMessage());

    if ($backup !== null && is_file($backup)) {
        if (copy($backup, $targetPath)) {
            bs_st2b6_out('restore_on_fail', 'ok');
        } else {
            bs_st2b6_out('restore_on_fail', 'failed');
        }
    } else {
        bs_st2b6_out('restore_on_fail', 'not_needed');
    }

    bs_st2b6_out('done', 'failed');
    exit(1);
}
