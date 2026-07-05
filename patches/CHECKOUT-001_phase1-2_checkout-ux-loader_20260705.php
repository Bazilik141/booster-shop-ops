<?php
declare(strict_types=1);

/**
 * CHECKOUT-001 Phase 1.2 — skip unused account pre-step + continuous loader.
 *
 * Applies on top of deployed CHECKOUT-001 Phase 1, Phase 1.1 and agree-session
 * hotfix state.
 *
 * Scope: catalog/view/template/checkout/checkout.twig only.
 * Database/server/payment-extension changes: none.
 */

const CHECKOUT001UX_PATCH_ID = 'CHECKOUT-001_phase1-2_checkout-ux-loader_20260705';
const CHECKOUT001UX_TARGET = 'catalog/view/template/checkout/checkout.twig';
const CHECKOUT001UX_EXPECTED_SHA256 = '5c8b49203c12588f5e0f3de715791ffe94d45e65934d9b2b4dde68d64dc951bc';
const CHECKOUT001UX_MARKER = 'CHECKOUT-001 Phase 1.2 UX: skip unused account pre-step.';

function checkout001ux_out(string $key, string $value): void {
    echo $key . '=' . $value . PHP_EOL;
}

function checkout001ux_fail(string $message): void {
    throw new RuntimeException($message);
}

function checkout001ux_path(string $root, string $relative): string {
    return rtrim($root, "/\\") . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function checkout001ux_lint(string $path, string $label): void {
    if (!function_exists('exec')) {
        checkout001ux_fail('php_lint_unavailable:exec_disabled:' . $label);
    }

    $output = [];
    $code = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    checkout001ux_out('php_lint_' . $label, 'exit=' . $code . ';output=' . implode(' | ', $output));

    if ($code !== 0) {
        checkout001ux_fail('php_lint_failed:' . $label);
    }
}

$root = getenv('BS_PATCH_ROOT') ?: getcwd();
$target = checkout001ux_path($root, CHECKOUT001UX_TARGET);
$backup = null;
$written = false;

try {
    checkout001ux_out('patch', CHECKOUT001UX_PATCH_ID);
    checkout001ux_out('cwd', getcwd());
    checkout001ux_out('root', $root);
    checkout001ux_out('time', date('c'));
    checkout001ux_out('scope', 'client-only checkout progress and unchecked pre-step skip');
    checkout001ux_out('db_schema_changes', 'none');
    checkout001ux_out('server_controller_changes', 'none');
    checkout001ux_out('payment_extension_changes', 'none');

    if (!is_file($target)) {
        checkout001ux_fail('target_missing:' . CHECKOUT001UX_TARGET);
    }

    $content = file_get_contents($target);

    if ($content === false) {
        checkout001ux_fail('read_failed:' . CHECKOUT001UX_TARGET);
    }

    if (strpos($content, CHECKOUT001UX_MARKER) !== false) {
        checkout001ux_lint(__FILE__, 'patch_self');
        checkout001ux_out('already_applied', 'yes');
        checkout001ux_out('changed_files', '0');
        checkout001ux_out('done', 'ok');
        @unlink(__FILE__);
        exit(0);
    }

    $actualHash = hash('sha256', $content);
    checkout001ux_out('source_sha256', CHECKOUT001UX_TARGET . ':' . $actualHash);

    if (!hash_equals(CHECKOUT001UX_EXPECTED_SHA256, $actualHash)) {
        checkout001ux_fail(
            'live_sha256_mismatch:' . CHECKOUT001UX_TARGET .
            ':expected=' . CHECKOUT001UX_EXPECTED_SHA256 .
            ':actual=' . $actualHash
        );
    }

    checkout001ux_lint(__FILE__, 'patch_self');

    $old = <<<'JS'
  // CHECKOUT-001: pre-confirm guest account step.
  window.bsCheckoutLoadConfirmAndSubmit = function(trigger, event) {
    if (window.bsSt2b6Log) {
      window.bsSt2b6Log('bsCheckoutLoadConfirmAndSubmit:entry', event || null, trigger || null);
    }
    if (!bsCheckoutCanConfirm()) {
      $('#checkout-confirm').html(bsCheckoutDeferredConfirmHtml());
      return false;
    }

    if (bsCheckoutConfirmSubmitting) {
      return false;
    }

    bsCheckoutConfirmSubmitting = true;

    var button = $(trigger);
    button.prop('disabled', true).addClass('loading').text('Перевіряємо дані...');
    $('#checkout-confirm').addClass('bs-confirm-loading');

    function checkout001Fail(message) {
      bsCheckoutConfirmSubmitting = false;
      $('#checkout-confirm').removeClass('bs-confirm-loading');
      $('#checkout-confirm').html('<div class="alert alert-danger mb-3">' + bsCheckoutEscapeHtml(message) + '</div>' + bsCheckoutDeferredConfirmHtml());
    }

    function loadConfirmAndSubmit() {
      button.text('Створюємо замовлення...');

      if (window.bsSt2b6Log) {
        window.bsSt2b6Log('checkout.twig:confirm.confirm:before-load', event || null, trigger || null);
      }

      $('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}', function(response, status) {
        if (window.bsSt2b6Log) {
          window.bsSt2b6Log('checkout.twig:confirm.confirm:complete', event || null, trigger || null, { requestStatus: status || '' });
        }
        $('#checkout-confirm').removeClass('bs-confirm-loading');

        if (status === 'error') {
          checkout001Fail('Не вдалося створити замовлення. Оновіть сторінку і спробуйте ще раз.');
          return;
        }

        hideModelColumns();

        var realButton = $('#checkout-confirm #button-confirm').first();

        if (!realButton.length) {
          checkout001Fail('Не знайдено кнопку підтвердження оплати після створення замовлення.');
          return;
        }

        window.setTimeout(function() {
          realButton.trigger('click');
          bsCheckoutConfirmSubmitting = false;
        }, 30);
      });
    }

    function checkout001FailOpen(reason) {
      $('#input-create-account-opt-in').prop('checked', false);

      if (window.bsSt2b6Log) {
        window.bsSt2b6Log('CHECKOUT-001:account-prestep:fail-open', event || null, trigger || null, {
          reason: reason || 'unknown'
        });
      }

      loadConfirmAndSubmit();
    }

    $.ajax({
      url: 'index.php?route=checkout/payment_method.createAccount&language={{ language }}',
      type: 'post',
      data: {
        create_account_opt_in: $('#input-create-account-opt-in').is(':checked') ? '1' : ''
      },
      dataType: 'json',
      success: function(json) {
        if (json && json.error) {
          checkout001FailOpen('server');
          return;
        }

        loadConfirmAndSubmit();
      },
      error: function() {
        checkout001FailOpen('network');
      }
    });

    return false;
  };
JS;

    $new = <<<'JS'
  // CHECKOUT-001: pre-confirm guest account step.
  // CHECKOUT-001 Phase 1.2 UX: skip unused account pre-step.
  window.bsCheckoutLoadConfirmAndSubmit = function(trigger, event) {
    if (window.bsSt2b6Log) {
      window.bsSt2b6Log('bsCheckoutLoadConfirmAndSubmit:entry', event || null, trigger || null);
    }
    if (!bsCheckoutCanConfirm()) {
      $('#checkout-confirm').html(bsCheckoutDeferredConfirmHtml());
      return false;
    }

    if (bsCheckoutConfirmSubmitting) {
      return false;
    }

    bsCheckoutConfirmSubmitting = true;

    var button = $(trigger);
    var accountOptIn = $('#input-create-account-opt-in');
    var shouldCreateAccount = accountOptIn.length === 1 && accountOptIn.is(':checked');
    button.prop('disabled', true).addClass('loading').text(shouldCreateAccount ? 'Перевіряємо дані...' : 'Створюємо замовлення...');
    $('#checkout-confirm').addClass('bs-confirm-loading');

    function checkout001Fail(message) {
      bsCheckoutConfirmSubmitting = false;
      $('#checkout-confirm').removeClass('bs-confirm-loading');
      $('#checkout-confirm').html('<div class="alert alert-danger mb-3">' + bsCheckoutEscapeHtml(message) + '</div>' + bsCheckoutDeferredConfirmHtml());
    }

    function loadConfirmAndSubmit() {
      button.text('Створюємо замовлення...');

      if (window.bsSt2b6Log) {
        window.bsSt2b6Log('checkout.twig:confirm.confirm:before-load', event || null, trigger || null);
      }

      $('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}', function(response, status) {
        if (window.bsSt2b6Log) {
          window.bsSt2b6Log('checkout.twig:confirm.confirm:complete', event || null, trigger || null, { requestStatus: status || '' });
        }
        $('#checkout-confirm').removeClass('bs-confirm-loading');

        if (status === 'error') {
          checkout001Fail('Не вдалося створити замовлення. Оновіть сторінку і спробуйте ще раз.');
          return;
        }

        hideModelColumns();

        var realButton = $('#checkout-confirm #button-confirm').first();

        if (!realButton.length) {
          checkout001Fail('Не знайдено кнопку підтвердження оплати після створення замовлення.');
          return;
        }

        realButton.addClass('loading').text('Завершуємо замовлення...');

        window.setTimeout(function() {
          realButton.trigger('click');
          bsCheckoutConfirmSubmitting = false;
        }, 30);
      });
    }

    function checkout001FailOpen(reason) {
      accountOptIn.prop('checked', false);

      if (window.bsSt2b6Log) {
        window.bsSt2b6Log('CHECKOUT-001:account-prestep:fail-open', event || null, trigger || null, {
          reason: reason || 'unknown'
        });
      }

      loadConfirmAndSubmit();
    }

    if (!shouldCreateAccount) {
      loadConfirmAndSubmit();
      return false;
    }

    $.ajax({
      url: 'index.php?route=checkout/payment_method.createAccount&language={{ language }}',
      type: 'post',
      data: {
        create_account_opt_in: '1'
      },
      dataType: 'json',
      success: function(json) {
        if (json && json.error) {
          checkout001FailOpen('server');
          return;
        }

        loadConfirmAndSubmit();
      },
      error: function() {
        checkout001FailOpen('network');
      }
    });

    return false;
  };
JS;

    $anchorCount = substr_count($content, $old);

    if ($anchorCount !== 1) {
        checkout001ux_fail('anchor_count_mismatch:checkout_loader_flow:expected=1:actual=' . $anchorCount);
    }

    $patched = str_replace($old, $new, $content);

    $checks = [
        'marker' => substr_count($patched, CHECKOUT001UX_MARKER) === 1,
        'trusted_gate' => substr_count($patched, 'nativeEvent.isTrusted === true') === 1,
        'autosave_selector' => substr_count($patched, "'#form-register input, #form-register select'") === 1,
        'confirm_route_count' => substr_count($patched, 'checkout/confirm.confirm') === 3,
        'create_account_route_count' => substr_count($patched, 'checkout/payment_method.createAccount') === 1,
        'prestep_skip_count' => substr_count($patched, 'if (!shouldCreateAccount)') === 1,
        'real_payment_trigger_count' => substr_count($patched, "realButton.trigger('click')") === 1,
        'final_status_count' => substr_count($patched, "text('Завершуємо замовлення...')") === 1,
    ];

    foreach ($checks as $label => $ok) {
        if (!$ok) {
            checkout001ux_fail('postbuild_check_failed:' . $label);
        }
    }

    $backupRoot = checkout001ux_path(
        $root,
        '_patch_backups/' . CHECKOUT001UX_PATCH_ID . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4))
    );
    $backup = checkout001ux_path($backupRoot, CHECKOUT001UX_TARGET);
    $backupDirectory = dirname($backup);

    if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0755, true) && !is_dir($backupDirectory)) {
        checkout001ux_fail('backup_mkdir_failed:' . CHECKOUT001UX_TARGET);
    }

    if (!copy($target, $backup)) {
        checkout001ux_fail('backup_copy_failed:' . CHECKOUT001UX_TARGET);
    }

    checkout001ux_out('backup', CHECKOUT001UX_TARGET . ' -> ' . $backup);

    $bytes = file_put_contents($target, $patched, LOCK_EX);

    if ($bytes !== strlen($patched)) {
        checkout001ux_fail('write_failed:' . CHECKOUT001UX_TARGET);
    }

    $written = true;
    $final = file_get_contents($target);

    if ($final === false || substr_count($final, CHECKOUT001UX_MARKER) !== 1) {
        checkout001ux_fail('postwrite_marker_failed:' . CHECKOUT001UX_TARGET);
    }

    checkout001ux_out('already_applied', 'no');
    checkout001ux_out('changed_files', '1');
    checkout001ux_out('changed_file', CHECKOUT001UX_TARGET);
    checkout001ux_out('unchecked_or_missing_optin', 'skip_createAccount');
    checkout001ux_out('checked_optin', 'preserve_createAccount_then_confirm');
    checkout001ux_out('status_sequence_checked', 'Перевіряємо дані -> Створюємо замовлення -> Завершуємо замовлення');
    checkout001ux_out('status_sequence_unchecked', 'Створюємо замовлення -> Завершуємо замовлення');
    checkout001ux_out('rollback_code', 'restore ' . CHECKOUT001UX_TARGET . ' from ' . $backup . ';then clear template cache');
    checkout001ux_out('done', 'ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    checkout001ux_out('error', $error->getMessage());

    if ($written && $backup && is_file($backup)) {
        $restored = copy($backup, $target);
        checkout001ux_out('restore_on_fail', $restored ? 'ok' : 'failed');
    } else {
        checkout001ux_out('restore_on_fail', 'not_needed');
    }

    checkout001ux_out('done', 'failed');
    exit(1);
}
