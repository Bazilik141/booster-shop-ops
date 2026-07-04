<?php
declare(strict_types=1);

/*
 * ST-2b.6d: trusted activation gate for the deferred confirm button.
 * DB changes: none. Order/payment implementation is not modified.
 */

const BS6D_ID = 'ST-2b6d_deferred-confirm-trusted-click-gate_20260703';
const BS6D_TARGET = 'catalog/view/template/checkout/checkout.twig';
const BS6D_SHA256 = '44b52ede066c25a8c6bfd7668988367015a88f524545d118f31a7884923ea8fa';
const BS6D_MARKER = 'ST-2b.6d: trusted deferred-confirm activation gate.';

function bs6d_out(string $key, string $value = ''): void {
    echo $key . ($value !== '' ? '=' . $value : '') . PHP_EOL;
}
function bs6d_fail(string $message): void {
    throw new RuntimeException($message);
}
function bs6d_path(string $relative): string {
    return __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}
function bs6d_read(): string {
    $path = bs6d_path(BS6D_TARGET);
    if (!is_file($path)) bs6d_fail('missing_file:' . BS6D_TARGET);
    $content = file_get_contents($path);
    if ($content === false) bs6d_fail('read_failed:' . BS6D_TARGET);
    return $content;
}
function bs6d_lint_self(): void {
    if (!function_exists('exec')) bs6d_fail('php_lint_unavailable:exec_disabled');
    $out = [];
    $code = 1;
    exec(escapeshellarg(PHP_BINARY ?: 'php') . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $out, $code);
    if ($code !== 0) bs6d_fail('php_lint_failed:' . implode(' | ', $out));
    bs6d_out('php_l_patch', 'ok');
}

$backup = null;

try {
    bs6d_out('patch', BS6D_ID);
    bs6d_out('cwd', __DIR__);
    bs6d_out('time', gmdate('c'));
    bs6d_out('scope', 'trusted deferred-confirm activation gate only');
    bs6d_out('db_schema_changes', 'none');
    bs6d_out('db_data_changes', 'none');

    $original = bs6d_read();
    if (strpos($original, BS6D_MARKER) !== false) {
        if (substr_count($original, BS6D_MARKER) !== 1) bs6d_fail('marker_count_invalid');
        bs6d_out('already_applied', 'yes');
        bs6d_out('changed_files', 'none');
        bs6d_out('done', 'ok');
        @unlink(__FILE__);
        exit(0);
    }

    $actual = hash('sha256', $original);
    if (!hash_equals(BS6D_SHA256, $actual)) {
        bs6d_fail('live_sha256_mismatch:expected=' . BS6D_SHA256 . ':actual=' . $actual);
    }
    if (substr_count($original, 'route=checkout/confirm.confirm&language={{ language }}') !== 1) {
        bs6d_fail('confirm_request_count_changed');
    }

    $search =
        "  \$(document).on('click.bsSt2b1DeferredConfirm', '#checkout-confirm [data-bs-deferred-confirm]', function(event) {\n" .
        "    if (window.bsSt2b6Log) {\n" .
        "      window.bsSt2b6Log('deferred-confirm:click', event, this);\n" .
        "    }\n\n" .
        "    event.preventDefault();\n" .
        "    event.stopImmediatePropagation();\n\n" .
        "    if (window.bsCheckoutLoadConfirmAndSubmit) {\n" .
        "      window.bsCheckoutLoadConfirmAndSubmit(this, event);\n" .
        "    }\n" .
        "  });\n";

    $replace =
        "  \$(document).on('click.bsSt2b1DeferredConfirm', '#checkout-confirm [data-bs-deferred-confirm]', function(event) {\n" .
        "    if (window.bsSt2b6Log) {\n" .
        "      window.bsSt2b6Log('deferred-confirm:click', event, this);\n" .
        "    }\n\n" .
        "    // " . BS6D_MARKER . "\n" .
        "    var nativeEvent = event && event.originalEvent ? event.originalEvent : event;\n" .
        "    var trustedActivation = !!(nativeEvent && nativeEvent.isTrusted === true);\n" .
        "    var triggerIsButton = !!(this && \$(this).is('[data-bs-deferred-confirm]'));\n" .
        "    var targetIsButton = !!(event && event.target === this);\n" .
        "    var currentTargetIsButton = !!(event && event.currentTarget === this);\n\n" .
        "    event.preventDefault();\n" .
        "    event.stopImmediatePropagation();\n\n" .
        "    if (!trustedActivation || !triggerIsButton || !targetIsButton || !currentTargetIsButton) {\n" .
        "      if (window.bsSt2b6Log) {\n" .
        "        window.bsSt2b6Log('phase1:deferred-confirm:rejected', event, this, {\n" .
        "          trustedActivation: trustedActivation,\n" .
        "          triggerIsButton: triggerIsButton,\n" .
        "          targetIsButton: targetIsButton,\n" .
        "          currentTargetIsButton: currentTargetIsButton\n" .
        "        });\n" .
        "      }\n" .
        "      return false;\n" .
        "    }\n\n" .
        "    if (window.bsSt2b6Log) {\n" .
        "      window.bsSt2b6Log('phase1:deferred-confirm:accepted', event, this, { trustedActivation: true });\n" .
        "    }\n\n" .
        "    if (window.bsCheckoutLoadConfirmAndSubmit) {\n" .
        "      window.bsCheckoutLoadConfirmAndSubmit(this, event);\n" .
        "    }\n" .
        "  });\n";

    if (substr_count($original, $search) !== 1) {
        bs6d_fail('anchor_count_mismatch:deferred_handler');
    }
    bs6d_lint_self();
    $patched = str_replace($search, $replace, $original);

    if (substr_count($patched, BS6D_MARKER) !== 1) bs6d_fail('marker_check_failed');
    if (substr_count($patched, 'nativeEvent.isTrusted === true') !== 1) bs6d_fail('trusted_check_failed');
    if (substr_count($patched, 'event.target === this') !== 1) bs6d_fail('target_check_failed');
    if (substr_count($patched, 'event.currentTarget === this') !== 1) bs6d_fail('current_target_check_failed');
    if (substr_count($patched, 'window.bsCheckoutLoadConfirmAndSubmit(this, event);') !== 1) bs6d_fail('submit_call_changed');

    $backupRoot = __DIR__ . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . BS6D_ID . '-' . date('Ymd-His');
    $backup = $backupRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, BS6D_TARGET);
    if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0755, true) && !is_dir(dirname($backup))) {
        bs6d_fail('backup_directory_failed');
    }
    if (!copy(bs6d_path(BS6D_TARGET), $backup)) bs6d_fail('backup_failed');
    bs6d_out('backup', $backup);

    $target = bs6d_path(BS6D_TARGET);
    if (file_put_contents($target, $patched, LOCK_EX) !== strlen($patched)) bs6d_fail('write_failed');
    $written = bs6d_read();
    if (substr_count($written, BS6D_MARKER) !== 1) bs6d_fail('written_marker_failed');

    bs6d_out('changed_files', BS6D_TARGET);
    bs6d_out('target_php_lint', 'not_applicable:twig_only');
    bs6d_out('keyboard_activation', 'preserved:trusted native click on focused button');
    bs6d_out('diagnostics', 'preserved');
    bs6d_out('rollback', 'restore=' . $backup . ';then_clear_template_cache');
    bs6d_out('done', 'ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    bs6d_out('error', $error->getMessage());
    if ($backup !== null && is_file($backup)) {
        bs6d_out('restore_on_fail', copy($backup, bs6d_path(BS6D_TARGET)) ? 'ok' : 'failed');
    } else {
        bs6d_out('restore_on_fail', 'not_needed');
    }
    bs6d_out('done', 'failed');
    exit(1);
}
