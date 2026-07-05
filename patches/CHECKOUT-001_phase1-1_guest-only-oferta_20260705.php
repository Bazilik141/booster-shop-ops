<?php
declare(strict_types=1);

/**
 * CHECKOUT-001 Phase 1.1 — guest-only stock checkout + mandatory oferta.
 *
 * Applies on top of the deployed CHECKOUT-001 Phase 1 patch.
 *
 * Scope:
 * - removes the native account/guest selector from stock checkout;
 * - forces checkout/register.save to remain guest-only server-side;
 * - keeps CHECKOUT-001 as the only checkout account-creation path;
 * - renders the owner-approved oferta text and URL unconditionally;
 * - requires oferta agreement in the client gate and confirm controller.
 *
 * Database changes: none.
 * Runtime customer/order rows: unchanged from the deployed Phase 1 behavior.
 */

const CHECKOUT00111_PATCH_ID = 'CHECKOUT-001_phase1-1_guest-only-oferta_20260705';
const CHECKOUT00111_CHECKOUT_TWIG = 'catalog/view/template/checkout/checkout.twig';
const CHECKOUT00111_PAYMENT_CONTROLLER = 'catalog/controller/checkout/payment_method.php';
const CHECKOUT00111_REGISTER_TWIG = 'catalog/view/template/checkout/register.twig';
const CHECKOUT00111_REGISTER_CONTROLLER = 'catalog/controller/checkout/register.php';
const CHECKOUT00111_CONFIRM_CONTROLLER = 'catalog/controller/checkout/confirm.php';

const CHECKOUT00111_MARKER_CHECKOUT = 'CHECKOUT-001 Phase 1.1: mandatory oferta client gate.';
const CHECKOUT00111_MARKER_PAYMENT = 'CHECKOUT-001 Phase 1.1: fixed mandatory oferta copy.';
const CHECKOUT00111_MARKER_REGISTER_TWIG = 'CHECKOUT-001 Phase 1.1: guest-only stock checkout.';
const CHECKOUT00111_MARKER_REGISTER_CONTROLLER = 'CHECKOUT-001 Phase 1.1: guest-only server mode.';
const CHECKOUT00111_MARKER_CONFIRM = 'CHECKOUT-001 Phase 1.1: mandatory oferta server gate.';

const CHECKOUT00111_EXPECTED_SHA256 = [
    CHECKOUT00111_CHECKOUT_TWIG => '33040ad395787496ebc0f5975498f800434280a952a13c48a2e07b1fc0511023',
    CHECKOUT00111_PAYMENT_CONTROLLER => '2be773b8a787a819a794954abc1fc9cd8085675ca0218d08fdcf504b99d26bf5',
    CHECKOUT00111_REGISTER_TWIG => '8eb532f28241a4fb990249a42614a79fceed0056360fca0f5916767306bd7f67',
    CHECKOUT00111_REGISTER_CONTROLLER => '4af74e10cc688aad28037f94d3051b40a2c649d7156a2c0dfe2fc1c6d84b4f16',
    CHECKOUT00111_CONFIRM_CONTROLLER => 'a6ea2f9e33fea9117aff2845229431f078527d1325ea29507221c00c668e7c47',
];

function checkout00111_out(string $key, string $value): void {
    echo $key . '=' . $value . PHP_EOL;
}

function checkout00111_fail(string $message): void {
    throw new RuntimeException($message);
}

function checkout00111_path(string $root, string $relative): string {
    return rtrim($root, "/\\") . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function checkout00111_read(string $root, string $relative): string {
    $path = checkout00111_path($root, $relative);

    if (!is_file($path)) {
        checkout00111_fail('target_missing:' . $relative);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        checkout00111_fail('read_failed:' . $relative);
    }

    return $content;
}

function checkout00111_replace_once(string $content, string $search, string $replace, string $label): string {
    $count = substr_count($content, $search);

    if ($count !== 1) {
        checkout00111_fail('anchor_count_mismatch:' . $label . ':expected=1:actual=' . $count);
    }

    return str_replace($search, $replace, $content);
}

function checkout00111_write(string $path, string $content): void {
    $bytes = file_put_contents($path, $content, LOCK_EX);

    if ($bytes !== strlen($content)) {
        checkout00111_fail('write_failed:' . $path);
    }
}

function checkout00111_lint(string $path, string $label): void {
    if (!function_exists('exec')) {
        checkout00111_fail('php_lint_unavailable:exec_disabled:' . $label);
    }

    $output = [];
    $code = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    checkout00111_out('php_lint_' . $label, 'exit=' . $code . ';output=' . implode(' | ', $output));

    if ($code !== 0) {
        checkout00111_fail('php_lint_failed:' . $label);
    }
}

function checkout00111_backup(string $root, string $backupRoot, string $relative): string {
    $source = checkout00111_path($root, $relative);
    $backup = checkout00111_path($backupRoot, $relative);
    $directory = dirname($backup);

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        checkout00111_fail('backup_mkdir_failed:' . $relative);
    }

    if (!copy($source, $backup)) {
        checkout00111_fail('backup_copy_failed:' . $relative);
    }

    checkout00111_out('backup', $relative . ' -> ' . $backup);

    return $backup;
}

$root = getenv('BS_PATCH_ROOT') ?: getcwd();
$backupRoot = null;
$backups = [];
$written = [];

try {
    checkout00111_out('patch', CHECKOUT00111_PATCH_ID);
    checkout00111_out('cwd', getcwd());
    checkout00111_out('root', $root);
    checkout00111_out('time', date('c'));
    checkout00111_out('scope', 'guest-only stock checkout and mandatory oferta agreement');
    checkout00111_out('db_schema_changes', 'none');
    checkout00111_out('simplecheckout_changes', 'none');
    checkout00111_out('standalone_account_register_changes', 'none');
    checkout00111_out('oferta_url', 'https://boostershop.website/information/publichna-oferta');

    $files = array_keys(CHECKOUT00111_EXPECTED_SHA256);
    $contents = [];

    foreach ($files as $relative) {
        $contents[$relative] = checkout00111_read($root, $relative);
    }

    $markerStates = [
        CHECKOUT00111_CHECKOUT_TWIG => strpos($contents[CHECKOUT00111_CHECKOUT_TWIG], CHECKOUT00111_MARKER_CHECKOUT) !== false,
        CHECKOUT00111_PAYMENT_CONTROLLER => strpos($contents[CHECKOUT00111_PAYMENT_CONTROLLER], CHECKOUT00111_MARKER_PAYMENT) !== false,
        CHECKOUT00111_REGISTER_TWIG => strpos($contents[CHECKOUT00111_REGISTER_TWIG], CHECKOUT00111_MARKER_REGISTER_TWIG) !== false,
        CHECKOUT00111_REGISTER_CONTROLLER => strpos($contents[CHECKOUT00111_REGISTER_CONTROLLER], CHECKOUT00111_MARKER_REGISTER_CONTROLLER) !== false,
        CHECKOUT00111_CONFIRM_CONTROLLER => strpos($contents[CHECKOUT00111_CONFIRM_CONTROLLER], CHECKOUT00111_MARKER_CONFIRM) !== false,
    ];

    $appliedCount = count(array_filter($markerStates));

    if ($appliedCount === count($markerStates)) {
        checkout00111_lint(__FILE__, 'patch_self');
        checkout00111_lint(checkout00111_path($root, CHECKOUT00111_PAYMENT_CONTROLLER), 'payment_controller');
        checkout00111_lint(checkout00111_path($root, CHECKOUT00111_REGISTER_CONTROLLER), 'register_controller');
        checkout00111_lint(checkout00111_path($root, CHECKOUT00111_CONFIRM_CONTROLLER), 'confirm_controller');
        checkout00111_out('already_applied', 'yes');
        checkout00111_out('changed_files', '0');
        checkout00111_out('done', 'ok');
        @unlink(__FILE__);
        exit(0);
    }

    if ($appliedCount !== 0) {
        checkout00111_fail('partial_state_detected:markers=' . $appliedCount . '/' . count($markerStates));
    }

    foreach (CHECKOUT00111_EXPECTED_SHA256 as $relative => $expectedHash) {
        $actualHash = hash('sha256', $contents[$relative]);

        if (!hash_equals($expectedHash, $actualHash)) {
            checkout00111_fail(
                'live_sha256_mismatch:' . $relative .
                ':expected=' . $expectedHash .
                ':actual=' . $actualHash
            );
        }

        checkout00111_out('source_sha256', $relative . ':' . $actualHash);
    }

    checkout00111_lint(__FILE__, 'patch_self');

    $checkoutGateOld = <<<'JS'
  function bsCheckoutHasPaymentReady() {
    return !!$('#input-payment-code').val();
  }

  function bsCheckoutCanConfirm() {
    return bsCheckoutHasShippingReady() && bsCheckoutHasPaymentReady();
  }
JS;

    $checkoutGateNew = <<<'JS'
  function bsCheckoutHasPaymentReady() {
    return !!$('#input-payment-code').val();
  }

  // CHECKOUT-001 Phase 1.1: mandatory oferta client gate.
  function bsCheckoutHasAgreeReady() {
    var agree = $('#input-checkout-agree');
    return agree.length === 1 && agree.prop('checked');
  }

  function bsCheckoutCanConfirm() {
    return bsCheckoutHasShippingReady() && bsCheckoutHasPaymentReady() && bsCheckoutHasAgreeReady();
  }
JS;

    $checkoutPatched = checkout00111_replace_once(
        $contents[CHECKOUT00111_CHECKOUT_TWIG],
        $checkoutGateOld,
        $checkoutGateNew,
        'checkout_mandatory_oferta_gate'
    );

    $checkoutHintOld = <<<'JS'
  function bsCheckoutConfirmHint() {
    var hasShipping = bsCheckoutHasShippingReady();
    var hasPayment = bsCheckoutHasPaymentReady();

    if (!hasShipping && !hasPayment) {
      return 'Заповніть доставку і оберіть спосіб оплати, щоб оформити замовлення.';
    }

    if (!hasShipping) {
      return 'Заповніть доставку, щоб оформити замовлення.';
    }

    if (!hasPayment) {
      return 'Оберіть спосіб оплати, щоб оформити замовлення.';
    }

    return 'Все готово. Натисніть кнопку, щоб оформити замовлення.';
  }
JS;

    $checkoutHintNew = <<<'JS'
  function bsCheckoutConfirmHint() {
    var hasShipping = bsCheckoutHasShippingReady();
    var hasPayment = bsCheckoutHasPaymentReady();
    var hasAgree = bsCheckoutHasAgreeReady();

    if (!hasShipping && !hasPayment) {
      return 'Заповніть доставку і оберіть спосіб оплати, щоб оформити замовлення.';
    }

    if (!hasShipping) {
      return 'Заповніть доставку, щоб оформити замовлення.';
    }

    if (!hasPayment) {
      return 'Оберіть спосіб оплати, щоб оформити замовлення.';
    }

    if (!hasAgree) {
      return 'Погодьтеся з умовами Публічної оферти, щоб оформити замовлення.';
    }

    return 'Все готово. Натисніть кнопку, щоб оформити замовлення.';
  }
JS;

    $checkoutPatched = checkout00111_replace_once(
        $checkoutPatched,
        $checkoutHintOld,
        $checkoutHintNew,
        'checkout_oferta_hint'
    );

    $paymentInfoOld = <<<'PHP'
		// Information
		$this->load->model('catalog/information');

		$information_info = $this->model_catalog_information->getInformation((int)$this->config->get('config_checkout_id'));

		if ($information_info) {
			$information_url = $this->url->link('information/information.info', 'language=' . $this->config->get('config_language') . '&information_id=' . $this->config->get('config_checkout_id'));
			$data['text_agree'] = sprintf($this->language->get('text_agree'), $information_url, $information_info['title']);
			$data['account_privacy'] = $information_url;
		} else {
			$data['text_agree'] = '';
		}
PHP;

    $paymentInfoNew = <<<'PHP'
		// CHECKOUT-001 Phase 1.1: fixed mandatory oferta copy.
		$information_url = 'https://boostershop.website/information/publichna-oferta';
		$data['text_agree'] = 'Я погоджуюся з умовами <a href="' . $information_url . '" target="_blank" rel="noopener noreferrer">Публічної оферти</a>, включно з положеннями про обробку персональних даних.';
		$data['account_privacy'] = $information_url;
PHP;

    $paymentPatched = checkout00111_replace_once(
        $contents[CHECKOUT00111_PAYMENT_CONTROLLER],
        $paymentInfoOld,
        $paymentInfoNew,
        'payment_fixed_oferta_copy'
    );

    $registerChoiceOld = <<<'TWIG'
      {% if config_checkout_guest %}
        <div class="col mb-3 required">
          <div class="form-check form-check-inline">
            <input type="radio" name="account" value="1" id="input-register" class="form-check-input"{% if account %} checked{% endif %}/> <label for="input-register" class="form-check-label">{{ text_register }}</label>
          </div>
          <div class="form-check form-check-inline">
            <input type="radio" name="account" value="0" id="input-guest" class="form-check-input"{% if not account %} checked{% endif %}/> <label for="input-guest" class="form-check-label">{{ text_guest }}</label>
          </div>
        </div>
      {% endif %}
TWIG;

    $registerChoiceNew = <<<'TWIG'
      {# CHECKOUT-001 Phase 1.1: guest-only stock checkout. #}
      <input type="hidden" name="account" value="0"/>
TWIG;

    $registerTwigPatched = checkout00111_replace_once(
        $contents[CHECKOUT00111_REGISTER_TWIG],
        $registerChoiceOld,
        $registerChoiceNew,
        'register_guest_only_choice'
    );

    $registerTwigPatched = checkout00111_replace_once(
        $registerTwigPatched,
        '<div id="password" class="col mb-3 required">',
        '<div id="password" class="col mb-3 required d-none">',
        'register_hide_password'
    );

    $registerTwigPatched = checkout00111_replace_once(
        $registerTwigPatched,
        '<div id="register-agree" class="form-check form-switch form-switch-lg">',
        '<div id="register-agree" class="form-check form-switch form-switch-lg d-none">',
        'register_hide_native_account_agree'
    );

    $registerScriptOld = <<<'JS'
// Account
$('input[name=\'account\']').on('click', function() {
    if ($(this).val() == 1) {
        $('#password').removeClass('d-none');
    } else {
        // If guest hide password field
        $('#password').addClass('d-none');
    }

    if ($(this).val() == 1) {
        $('#register-agree').removeClass('d-none');
    } else {
        // If guest hide register agree field
        $('#register-agree').addClass('d-none');
    }
});

$('input[name=\'account\']:checked').trigger('click');
JS;

    $registerScriptNew = <<<'JS'
// CHECKOUT-001 Phase 1.1: native registration is disabled in stock checkout.
$('#password, #register-agree').addClass('d-none');
JS;

    $registerTwigPatched = checkout00111_replace_once(
        $registerTwigPatched,
        $registerScriptOld,
        $registerScriptNew,
        'register_remove_native_account_toggle'
    );

    $registerIndexOld = <<<'PHP'
		if (isset($this->session->data['customer']['customer_id'])) {
			$data['account'] = $this->session->data['customer']['customer_id'];
		} else {
			$data['account'] = 1;
		}
PHP;

    $registerIndexNew = <<<'PHP'
		// CHECKOUT-001 Phase 1.1: guest-only server mode.
		$data['account'] = 0;
PHP;

    $registerControllerPatched = checkout00111_replace_once(
        $contents[CHECKOUT00111_REGISTER_CONTROLLER],
        $registerIndexOld,
        $registerIndexNew,
        'register_controller_guest_default'
    );

    $registerForceOld = <<<'PHP'
		// Force account requires subscript or is a downloadable product.
		if ($this->cart->hasDownload() || $this->cart->hasSubscription() || !$this->config->get('config_checkout_guest')) {
			$post_info['account'] = 1;
		}
PHP;

    $registerForceNew = <<<'PHP'
		// CHECKOUT-001 Phase 1.1: reject native account mode even on a crafted POST.
		if ($this->cart->hasDownload() || $this->cart->hasSubscription() || !$this->config->get('config_checkout_guest')) {
			$json['error']['warning'] = $this->language->get('error_guest');
		}

		$post_info['account'] = 0;
PHP;

    $registerControllerPatched = checkout00111_replace_once(
        $registerControllerPatched,
        $registerForceOld,
        $registerForceNew,
        'register_controller_force_guest_post'
    );

    $confirmAgreeOld = <<<'PHP'
		// Validate checkout terms
		if ($this->config->get('config_checkout_id') && empty($this->session->data['agree'])) {
			$status = false;
		}
PHP;

    $confirmAgreeNew = <<<'PHP'
		// CHECKOUT-001 Phase 1.1: mandatory oferta server gate.
		if (empty($this->session->data['agree'])) {
			$status = false;
		}
PHP;

    $confirmPatched = checkout00111_replace_once(
        $contents[CHECKOUT00111_CONFIRM_CONTROLLER],
        $confirmAgreeOld,
        $confirmAgreeNew,
        'confirm_mandatory_oferta'
    );

    if (substr_count($checkoutPatched, 'function bsCheckoutHasAgreeReady()') !== 1) {
        checkout00111_fail('postbuild_checkout_agree_gate_count_changed');
    }

    if (substr_count($checkoutPatched, 'checkout/confirm.confirm') !== 3) {
        checkout00111_fail('postbuild_confirm_route_count_changed');
    }

    if (substr_count($paymentPatched, 'https://boostershop.website/information/publichna-oferta') !== 1) {
        checkout00111_fail('postbuild_oferta_url_count_changed');
    }

    if (substr_count($registerTwigPatched, 'name="account" value="1"') !== 0) {
        checkout00111_fail('postbuild_native_register_choice_still_present');
    }

    if (substr_count($registerTwigPatched, 'name="account" value="0"') !== 1) {
        checkout00111_fail('postbuild_guest_account_field_count_changed');
    }

    if (substr_count($registerControllerPatched, '$post_info[\'account\'] = 0;') !== 1) {
        checkout00111_fail('postbuild_server_guest_force_count_changed');
    }

    $backupRoot = checkout00111_path(
        $root,
        '_patch_backups/' . CHECKOUT00111_PATCH_ID . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4))
    );

    foreach ($files as $relative) {
        $backups[$relative] = checkout00111_backup($root, $backupRoot, $relative);
    }

    $patchedFiles = [
        CHECKOUT00111_CHECKOUT_TWIG => $checkoutPatched,
        CHECKOUT00111_PAYMENT_CONTROLLER => $paymentPatched,
        CHECKOUT00111_REGISTER_TWIG => $registerTwigPatched,
        CHECKOUT00111_REGISTER_CONTROLLER => $registerControllerPatched,
        CHECKOUT00111_CONFIRM_CONTROLLER => $confirmPatched,
    ];

    foreach ($patchedFiles as $relative => $content) {
        checkout00111_write(checkout00111_path($root, $relative), $content);
        $written[] = $relative;
    }

    checkout00111_lint(checkout00111_path($root, CHECKOUT00111_PAYMENT_CONTROLLER), 'payment_controller');
    checkout00111_lint(checkout00111_path($root, CHECKOUT00111_REGISTER_CONTROLLER), 'register_controller');
    checkout00111_lint(checkout00111_path($root, CHECKOUT00111_CONFIRM_CONTROLLER), 'confirm_controller');

    $finalMarkers = [
        CHECKOUT00111_CHECKOUT_TWIG => CHECKOUT00111_MARKER_CHECKOUT,
        CHECKOUT00111_PAYMENT_CONTROLLER => CHECKOUT00111_MARKER_PAYMENT,
        CHECKOUT00111_REGISTER_TWIG => CHECKOUT00111_MARKER_REGISTER_TWIG,
        CHECKOUT00111_REGISTER_CONTROLLER => CHECKOUT00111_MARKER_REGISTER_CONTROLLER,
        CHECKOUT00111_CONFIRM_CONTROLLER => CHECKOUT00111_MARKER_CONFIRM,
    ];

    foreach ($finalMarkers as $relative => $marker) {
        $final = checkout00111_read($root, $relative);

        if (substr_count($final, $marker) !== 1) {
            checkout00111_fail('postwrite_marker_failed:' . $relative);
        }
    }

    checkout00111_out('already_applied', 'no');
    checkout00111_out('changed_files', (string)count($written));
    foreach ($written as $relative) {
        checkout00111_out('changed_file', $relative);
    }
    checkout00111_out('native_checkout_account_mode', 'disabled_and_server_forced_guest');
    checkout00111_out('checkout001_account_creation', 'unchanged_pre_confirm_only');
    checkout00111_out('oferta_client_gate', 'required');
    checkout00111_out('oferta_server_gate', 'required');
    checkout00111_out('rollback_code', 'restore the five files from ' . $backupRoot . ';then clear template cache');
    checkout00111_out('done', 'ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    checkout00111_out('error', $error->getMessage());

    if ($backups) {
        $restoreOk = true;

        foreach ($backups as $relative => $backup) {
            $target = checkout00111_path($root, $relative);
            $restored = is_file($backup) && copy($backup, $target);
            checkout00111_out('restore', $relative . ':' . ($restored ? 'ok' : 'failed'));
            $restoreOk = $restoreOk && $restored;
        }

        checkout00111_out('restore_on_fail', $restoreOk ? 'ok' : 'failed');
    } else {
        checkout00111_out('restore_on_fail', 'not_needed');
    }

    checkout00111_out('done', 'failed');
    exit(1);
}
