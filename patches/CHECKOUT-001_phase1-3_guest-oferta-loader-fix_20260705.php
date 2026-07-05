<?php
declare(strict_types=1);

/**
 * CHECKOUT-001 Phase 1.3 — guest-only oferta + persistent checkout loader.
 *
 * Run after CHECKOUT-001_phase1-2_checkout-ux-loader_20260705.php.
 *
 * Database changes: none.
 */

const CHECKOUT00113_PATCH_ID = 'CHECKOUT-001_phase1-3_guest-oferta-loader-fix_20260705';
const CHECKOUT00113_CHECKOUT_TWIG = 'catalog/view/template/checkout/checkout.twig';
const CHECKOUT00113_PAYMENT_CONTROLLER = 'catalog/controller/checkout/payment_method.php';
const CHECKOUT00113_CONFIRM_CONTROLLER = 'catalog/controller/checkout/confirm.php';

const CHECKOUT00113_MARKER_CHECKOUT = 'CHECKOUT-001 Phase 1.3: absent oferta is valid for authorized checkout.';
const CHECKOUT00113_MARKER_LOADER = 'CHECKOUT-001 Phase 1.3: persistent submit overlay.';
const CHECKOUT00113_MARKER_PAYMENT = 'CHECKOUT-001 Phase 1.3: guest-only oferta state.';
const CHECKOUT00113_MARKER_CONFIRM = 'CHECKOUT-001 Phase 1.3: require oferta only for checkout that started as guest.';

const CHECKOUT00113_EXPECTED_SHA256 = [
    CHECKOUT00113_CHECKOUT_TWIG => 'ec580a506d546b4500fc97d105e52ab02335f99abc31cd1633e2e0e494eead61',
    CHECKOUT00113_PAYMENT_CONTROLLER => 'b7c08ee8940dedeb5e05eecbba6069afd2c203624c188fdc035755908d5ac95d',
    CHECKOUT00113_CONFIRM_CONTROLLER => '7d9f4bafb348983afc5b1794ce5d8933f8b1f12ebbd9fc68ed6d5b58b30390df',
];

function checkout00113_out(string $key, string $value): void {
    echo $key . '=' . $value . PHP_EOL;
}

function checkout00113_fail(string $message): void {
    throw new RuntimeException($message);
}

function checkout00113_path(string $root, string $relative): string {
    return rtrim($root, "/\\") . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function checkout00113_read(string $root, string $relative): string {
    $path = checkout00113_path($root, $relative);

    if (!is_file($path)) {
        checkout00113_fail('target_missing:' . $relative);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        checkout00113_fail('read_failed:' . $relative);
    }

    return $content;
}

function checkout00113_replace_once(string $content, string $search, string $replace, string $label): string {
    $count = substr_count($content, $search);

    if ($count !== 1) {
        checkout00113_fail('anchor_count_mismatch:' . $label . ':expected=1:actual=' . $count);
    }

    return str_replace($search, $replace, $content);
}

function checkout00113_lint(string $path, string $label): void {
    if (!function_exists('exec')) {
        checkout00113_fail('php_lint_unavailable:exec_disabled:' . $label);
    }

    $output = [];
    $code = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    checkout00113_out('php_lint_' . $label, 'exit=' . $code . ';output=' . implode(' | ', $output));

    if ($code !== 0) {
        checkout00113_fail('php_lint_failed:' . $label);
    }
}

function checkout00113_backup(string $root, string $backupRoot, string $relative): string {
    $source = checkout00113_path($root, $relative);
    $backup = checkout00113_path($backupRoot, $relative);
    $directory = dirname($backup);

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        checkout00113_fail('backup_mkdir_failed:' . $relative);
    }

    if (!copy($source, $backup)) {
        checkout00113_fail('backup_copy_failed:' . $relative);
    }

    checkout00113_out('backup', $relative . ' -> ' . $backup);

    return $backup;
}

$root = getenv('BS_PATCH_ROOT') ?: getcwd();
$files = array_keys(CHECKOUT00113_EXPECTED_SHA256);
$contents = [];
$backupRoot = null;
$backups = [];
$written = [];

try {
    checkout00113_out('patch', CHECKOUT00113_PATCH_ID);
    checkout00113_out('cwd', getcwd());
    checkout00113_out('root', $root);
    checkout00113_out('time', date('c'));
    checkout00113_out('scope', 'guest-only oferta visibility and enforcement; persistent checkout submit overlay');
    checkout00113_out('requires_loader_patch', 'CHECKOUT-001_phase1-2_checkout-ux-loader_20260705.php');
    checkout00113_out('db_schema_changes', 'none');
    checkout00113_out('account_creation_changes', 'none');
    checkout00113_out('payment_extension_changes', 'none');

    foreach ($files as $relative) {
        $contents[$relative] = checkout00113_read($root, $relative);
    }

    $markerStates = [
        CHECKOUT00113_CHECKOUT_TWIG => strpos($contents[CHECKOUT00113_CHECKOUT_TWIG], CHECKOUT00113_MARKER_CHECKOUT) !== false,
        CHECKOUT00113_PAYMENT_CONTROLLER => strpos($contents[CHECKOUT00113_PAYMENT_CONTROLLER], CHECKOUT00113_MARKER_PAYMENT) !== false,
        CHECKOUT00113_CONFIRM_CONTROLLER => strpos($contents[CHECKOUT00113_CONFIRM_CONTROLLER], CHECKOUT00113_MARKER_CONFIRM) !== false,
    ];

    $appliedCount = count(array_filter($markerStates));

    if ($appliedCount === count($markerStates)) {
        checkout00113_lint(__FILE__, 'patch_self');
        checkout00113_lint(checkout00113_path($root, CHECKOUT00113_PAYMENT_CONTROLLER), 'payment_controller');
        checkout00113_lint(checkout00113_path($root, CHECKOUT00113_CONFIRM_CONTROLLER), 'confirm_controller');
        checkout00113_out('already_applied', 'yes');
        checkout00113_out('changed_files', '0');
        checkout00113_out('done', 'ok');
        @unlink(__FILE__);
        exit(0);
    }

    if ($appliedCount !== 0) {
        checkout00113_fail('partial_state_detected:markers=' . $appliedCount . '/' . count($markerStates));
    }

    foreach (CHECKOUT00113_EXPECTED_SHA256 as $relative => $expectedHash) {
        $actualHash = hash('sha256', $contents[$relative]);

        if (!hash_equals($expectedHash, $actualHash)) {
            checkout00113_fail(
                'live_sha256_mismatch:' . $relative .
                ':expected=' . $expectedHash .
                ':actual=' . $actualHash
            );
        }

        checkout00113_out('source_sha256', $relative . ':' . $actualHash);
    }

    checkout00113_lint(__FILE__, 'patch_self');

    $checkoutOld = <<<'JS'
  // CHECKOUT-001 Phase 1.1: mandatory oferta client gate.
  function bsCheckoutHasAgreeReady() {
    var agree = $('#input-checkout-agree');
    return agree.length === 1 && agree.prop('checked');
  }
JS;

    $checkoutNew = <<<'JS'
  // CHECKOUT-001 Phase 1.1: mandatory oferta client gate.
  // CHECKOUT-001 Phase 1.3: absent oferta is valid for authorized checkout.
  function bsCheckoutHasAgreeReady() {
    var agree = $('#input-checkout-agree');
    return !agree.length || agree.prop('checked');
  }
JS;

    $checkoutPatched = checkout00113_replace_once(
        $contents[CHECKOUT00113_CHECKOUT_TWIG],
        $checkoutOld,
        $checkoutNew,
        'authorized_checkout_agree_client_bypass'
    );

    $loaderCssOld = <<<'CSS'
#checkout-checkout #checkout-confirm .btn-primary:active,
#checkout-checkout #checkout-confirm button[type="submit"]:active,
#checkout-checkout #checkout-confirm input[type="submit"]:active {
  box-shadow: 0 3px 8px rgba(25, 164, 71, 0.22);
  transform: translateY(0);
}

@media (max-width: 767.98px) {
CSS;

    $loaderCssNew = <<<'CSS'
#checkout-checkout #checkout-confirm .btn-primary:active,
#checkout-checkout #checkout-confirm button[type="submit"]:active,
#checkout-checkout #checkout-confirm input[type="submit"]:active {
  box-shadow: 0 3px 8px rgba(25, 164, 71, 0.22);
  transform: translateY(0);
}

/* CHECKOUT-001 Phase 1.3: persistent submit overlay. */
#bs-checkout-submit-loader {
  align-items: center;
  background: rgba(15, 23, 42, .58);
  display: none;
  inset: 0;
  justify-content: center;
  padding: 20px;
  position: fixed;
  z-index: 2147483000;
}

#bs-checkout-submit-loader.bs-is-visible {
  display: flex;
}

#bs-checkout-submit-loader .bs-checkout-submit-loader-card {
  align-items: center;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 18px 50px rgba(15, 23, 42, .24);
  color: #243142;
  display: flex;
  gap: 14px;
  max-width: 420px;
  padding: 18px 20px;
  width: 100%;
}

#bs-checkout-submit-loader .bs-checkout-submit-spinner {
  animation: bsCheckoutSubmitSpin .75s linear infinite;
  border: 4px solid #d9e6dc;
  border-radius: 50%;
  border-top-color: #19a447;
  flex: 0 0 34px;
  height: 34px;
  width: 34px;
}

#bs-checkout-submit-loader .bs-checkout-submit-title {
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.35;
}

#bs-checkout-submit-loader .bs-checkout-submit-stage {
  color: #667085;
  font-size: .88rem;
  line-height: 1.35;
  margin-top: 2px;
}

@keyframes bsCheckoutSubmitSpin {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  #bs-checkout-submit-loader .bs-checkout-submit-spinner {
    animation-duration: 1.5s;
  }
}

@media (max-width: 767.98px) {
CSS;

    $checkoutPatched = checkout00113_replace_once(
        $checkoutPatched,
        $loaderCssOld,
        $loaderCssNew,
        'persistent_loader_css'
    );

    $loaderFunctionOld = <<<'JS'
  // CHECKOUT-001: pre-confirm guest account step.
  // CHECKOUT-001 Phase 1.2 UX: skip unused account pre-step.
JS;

    $loaderFunctionNew = <<<'JS'
  function bsCheckoutShowSubmitLoader(stage) {
    var loader = $('#bs-checkout-submit-loader');

    if (!loader.length) {
      loader = $('<div id="bs-checkout-submit-loader" role="status" aria-live="polite" aria-hidden="true">' +
        '<div class="bs-checkout-submit-loader-card">' +
          '<span class="bs-checkout-submit-spinner" aria-hidden="true"></span>' +
          '<div><div class="bs-checkout-submit-title">Оформлюємо замовлення...</div>' +
          '<div class="bs-checkout-submit-stage"></div></div>' +
        '</div></div>').appendTo('body');
    }

    loader.find('.bs-checkout-submit-stage').text(stage || '');
    loader.addClass('bs-is-visible').attr('aria-hidden', 'false');
    $('body').attr('aria-busy', 'true');
  }

  function bsCheckoutUpdateSubmitLoader(stage) {
    $('#bs-checkout-submit-loader .bs-checkout-submit-stage').text(stage || '');
  }

  function bsCheckoutHideSubmitLoader() {
    $('#bs-checkout-submit-loader').removeClass('bs-is-visible').attr('aria-hidden', 'true');
    $('body').removeAttr('aria-busy');
  }

  // CHECKOUT-001: pre-confirm guest account step.
  // CHECKOUT-001 Phase 1.2 UX: skip unused account pre-step.
JS;

    $checkoutPatched = checkout00113_replace_once(
        $checkoutPatched,
        $loaderFunctionOld,
        $loaderFunctionNew,
        'persistent_loader_functions'
    );

    $checkoutPatched = checkout00113_replace_once(
        $checkoutPatched,
        <<<'JS'
    button.prop('disabled', true).addClass('loading').text(shouldCreateAccount ? 'Перевіряємо дані...' : 'Створюємо замовлення...');
    $('#checkout-confirm').addClass('bs-confirm-loading');
JS,
        <<<'JS'
    button.prop('disabled', true).addClass('loading').text(shouldCreateAccount ? 'Перевіряємо дані...' : 'Створюємо замовлення...');
    $('#checkout-confirm').addClass('bs-confirm-loading');
    bsCheckoutShowSubmitLoader(shouldCreateAccount ? 'Створюємо обліковий запис...' : 'Перевіряємо дані замовлення...');
JS,
        'show_loader_before_request'
    );

    $checkoutPatched = checkout00113_replace_once(
        $checkoutPatched,
        <<<'JS'
    function checkout001Fail(message) {
      bsCheckoutConfirmSubmitting = false;
      $('#checkout-confirm').removeClass('bs-confirm-loading');
JS,
        <<<'JS'
    function checkout001Fail(message) {
      bsCheckoutConfirmSubmitting = false;
      bsCheckoutHideSubmitLoader();
      $('#checkout-confirm').removeClass('bs-confirm-loading');
JS,
        'hide_loader_on_failure'
    );

    $checkoutPatched = checkout00113_replace_once(
        $checkoutPatched,
        <<<'JS'
    function loadConfirmAndSubmit() {
      button.text('Створюємо замовлення...');

      if (window.bsSt2b6Log) {
JS,
        <<<'JS'
    function loadConfirmAndSubmit() {
      button.text('Створюємо замовлення...');
      bsCheckoutUpdateSubmitLoader('Перевіряємо дані замовлення...');

      if (window.bsSt2b6Log) {
JS,
        'update_loader_confirm_stage'
    );

    $checkoutPatched = checkout00113_replace_once(
        $checkoutPatched,
        <<<'JS'
        realButton.addClass('loading').text('Завершуємо замовлення...');

        window.setTimeout(function() {
JS,
        <<<'JS'
        realButton.addClass('loading').text('Завершуємо замовлення...');
        bsCheckoutUpdateSubmitLoader('Завершуємо оформлення...');

        window.setTimeout(function() {
JS,
        'update_loader_final_stage'
    );

    $checkoutFlowOld = <<<'JS'
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
JS;

    $checkoutFlowNew = <<<'JS'
    function checkout001StartSubmitFlow() {
      if (!shouldCreateAccount) {
        loadConfirmAndSubmit();
        return;
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
    }

    // Give the browser one paint before starting the sequential AJAX flow.
    if (window.requestAnimationFrame) {
      window.requestAnimationFrame(checkout001StartSubmitFlow);
    } else {
      window.setTimeout(checkout001StartSubmitFlow, 0);
    }

    return false;
JS;

    $checkoutPatched = checkout00113_replace_once(
        $checkoutPatched,
        $checkoutFlowOld,
        $checkoutFlowNew,
        'paint_loader_before_submit_flow'
    );

    $paymentOld = <<<'PHP'
		// CHECKOUT-001: guest account opt-in endpoint.
		$data['show_create_account_opt_in'] = !$this->customer->isLogged() && empty($this->session->data['customer']['customer_id']);
		$data['create_account_opt_in'] = !empty($this->session->data['checkout001_create_account_opt_in']);
		$data['account_privacy'] = '';

		// CHECKOUT-001 Phase 1.1: fixed mandatory oferta copy.
		$information_url = 'https://boostershop.website/information/publichna-oferta';
		$data['text_agree'] = 'Я погоджуюся з умовами <a href="' . $information_url . '" target="_blank" rel="noopener noreferrer">Публічної оферти</a>, включно з положеннями про обробку персональних даних.';
		$data['account_privacy'] = $information_url;
PHP;

    $paymentNew = <<<'PHP'
		// CHECKOUT-001: guest account opt-in endpoint.
		$is_guest_checkout = !$this->customer->isLogged() && empty($this->session->data['customer']['customer_id']);
		$data['show_create_account_opt_in'] = $is_guest_checkout;
		$data['create_account_opt_in'] = !empty($this->session->data['checkout001_create_account_opt_in']);
		$data['account_privacy'] = '';

		// CHECKOUT-001 Phase 1.3: guest-only oferta state.
		if ($is_guest_checkout) {
			$this->session->data['checkout001_guest_agree_required'] = 1;
			$information_url = 'https://boostershop.website/information/publichna-oferta';
			$data['text_agree'] = 'Я погоджуюся з умовами <a href="' . $information_url . '" target="_blank" rel="noopener noreferrer">Публічної оферти</a>, включно з положеннями про обробку персональних даних.';
			$data['account_privacy'] = $information_url;
		} else {
			unset($this->session->data['checkout001_guest_agree_required']);
			unset($this->session->data['agree']);
			$data['agree'] = '';
			$data['text_agree'] = '';
		}
PHP;

    $paymentPatched = checkout00113_replace_once(
        $contents[CHECKOUT00113_PAYMENT_CONTROLLER],
        $paymentOld,
        $paymentNew,
        'payment_guest_only_oferta'
    );

    $confirmOld = <<<'PHP'
		// CHECKOUT-001 Phase 1.1: mandatory oferta server gate.
		if (empty($this->session->data['agree'])) {
			$status = false;
		}
PHP;

    $confirmNew = <<<'PHP'
		// CHECKOUT-001 Phase 1.3: require oferta only for checkout that started as guest.
		if (!empty($this->session->data['checkout001_guest_agree_required']) && empty($this->session->data['agree'])) {
			$status = false;
		}
PHP;

    $confirmPatched = checkout00113_replace_once(
        $contents[CHECKOUT00113_CONFIRM_CONTROLLER],
        $confirmOld,
        $confirmNew,
        'confirm_guest_only_oferta'
    );

    $checks = [
        'checkout_marker' => substr_count($checkoutPatched, CHECKOUT00113_MARKER_CHECKOUT) === 1,
        'loader_marker' => substr_count($checkoutPatched, CHECKOUT00113_MARKER_LOADER) === 1,
        'payment_marker' => substr_count($paymentPatched, CHECKOUT00113_MARKER_PAYMENT) === 1,
        'confirm_marker' => substr_count($confirmPatched, CHECKOUT00113_MARKER_CONFIRM) === 1,
        'guest_flag_set' => substr_count($paymentPatched, "\$this->session->data['checkout001_guest_agree_required'] = 1;") === 1,
        'guest_flag_gate' => substr_count($confirmPatched, "checkout001_guest_agree_required") === 1,
        'oferta_url' => substr_count($paymentPatched, 'https://boostershop.website/information/publichna-oferta') === 1,
        'confirm_route_count' => substr_count($checkoutPatched, 'checkout/confirm.confirm') === 3,
        'loader_marker_preserved' => substr_count($checkoutPatched, 'CHECKOUT-001 Phase 1.2 UX: skip unused account pre-step.') === 1,
        'trusted_gate_preserved' => substr_count($checkoutPatched, 'nativeEvent.isTrusted === true') === 1,
        'persistent_loader_singleton' => substr_count($checkoutPatched, 'id="bs-checkout-submit-loader"') === 1,
        'loader_title' => substr_count($checkoutPatched, 'Оформлюємо замовлення...') === 1,
        'paint_before_ajax' => substr_count($checkoutPatched, 'window.requestAnimationFrame(checkout001StartSubmitFlow)') === 1,
    ];

    foreach ($checks as $label => $ok) {
        if (!$ok) {
            checkout00113_fail('postbuild_check_failed:' . $label);
        }
    }

    $backupRoot = checkout00113_path(
        $root,
        '_patch_backups/' . CHECKOUT00113_PATCH_ID . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4))
    );

    foreach ($files as $relative) {
        $backups[$relative] = checkout00113_backup($root, $backupRoot, $relative);
    }

    $patchedFiles = [
        CHECKOUT00113_CHECKOUT_TWIG => $checkoutPatched,
        CHECKOUT00113_PAYMENT_CONTROLLER => $paymentPatched,
        CHECKOUT00113_CONFIRM_CONTROLLER => $confirmPatched,
    ];

    foreach ($patchedFiles as $relative => $content) {
        $target = checkout00113_path($root, $relative);
        $bytes = file_put_contents($target, $content, LOCK_EX);

        if ($bytes !== strlen($content)) {
            checkout00113_fail('write_failed:' . $relative);
        }

        $written[] = $relative;
    }

    checkout00113_lint(checkout00113_path($root, CHECKOUT00113_PAYMENT_CONTROLLER), 'payment_controller');
    checkout00113_lint(checkout00113_path($root, CHECKOUT00113_CONFIRM_CONTROLLER), 'confirm_controller');

    foreach ($markerStates as $relative => $_) {
        $final = checkout00113_read($root, $relative);
        $marker = match ($relative) {
            CHECKOUT00113_CHECKOUT_TWIG => CHECKOUT00113_MARKER_CHECKOUT,
            CHECKOUT00113_PAYMENT_CONTROLLER => CHECKOUT00113_MARKER_PAYMENT,
            CHECKOUT00113_CONFIRM_CONTROLLER => CHECKOUT00113_MARKER_CONFIRM,
        };

        if (substr_count($final, $marker) !== 1) {
            checkout00113_fail('postwrite_marker_failed:' . $relative);
        }
    }

    checkout00113_out('already_applied', 'no');
    checkout00113_out('changed_files', (string)count($written));
    foreach ($written as $relative) {
        checkout00113_out('changed_file', $relative);
    }
    checkout00113_out('guest_checkout_oferta', 'visible_and_required');
    checkout00113_out('authorized_checkout_oferta', 'hidden_and_not_required');
    checkout00113_out('guest_preconfirm_login', 'agreement_requirement_preserved_by_session_flag');
    checkout00113_out('checkout_loader', 'persistent_overlay_visible_before_first_request');
    checkout00113_out('rollback_code', 'restore the three files from ' . $backupRoot . ';then clear template cache');
    checkout00113_out('done', 'ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    checkout00113_out('error', $error->getMessage());

    if ($backups) {
        $restoreOk = true;

        foreach ($backups as $relative => $backup) {
            $target = checkout00113_path($root, $relative);
            $restored = is_file($backup) && copy($backup, $target);
            checkout00113_out('restore', $relative . ':' . ($restored ? 'ok' : 'failed'));
            $restoreOk = $restoreOk && $restored;
        }

        checkout00113_out('restore_on_fail', $restoreOk ? 'ok' : 'failed');
    } else {
        checkout00113_out('restore_on_fail', 'not_needed');
    }

    checkout00113_out('done', 'failed');
    exit(1);
}
