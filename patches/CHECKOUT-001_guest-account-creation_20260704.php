<?php
declare(strict_types=1);

/**
 * CHECKOUT-001 Phase 1 — stock-checkout guest account opt-in.
 *
 * Scope:
 * - adds an unchecked stock-checkout opt-in;
 * - persists the preference in session;
 * - creates a customer/address before the existing single confirm.confirm call;
 * - sends a one-time set-password link through the existing password-token flow;
 * - leaves SimpleCheckout, url.php, confirm.php, customer model, payment logic,
 *   Hutko, fiscalization, CRM, and database schema unchanged.
 *
 * Runtime database warning:
 * When a guest explicitly opts in and the email is new, normal OpenCart model
 * calls can add rows to:
 *   {DB_PREFIX}customer
 *   {DB_PREFIX}address
 *   {DB_PREFIX}customer_token
 *   {DB_PREFIX}customer_activity / {DB_PREFIX}customer_ip (core events/login)
 * No rows are written while this patch runner itself is executing.
 *
 * Manual rollback SQL for a confirmed TEST account only:
 *   START TRANSACTION;
 *   DELETE FROM `{DB_PREFIX}customer_token` WHERE `customer_id` = <TEST_CUSTOMER_ID>;
 *   DELETE FROM `{DB_PREFIX}customer_activity` WHERE `customer_id` = <TEST_CUSTOMER_ID>;
 *   DELETE FROM `{DB_PREFIX}customer_ip` WHERE `customer_id` = <TEST_CUSTOMER_ID>;
 *   DELETE FROM `{DB_PREFIX}address` WHERE `customer_id` = <TEST_CUSTOMER_ID>;
 *   DELETE FROM `{DB_PREFIX}customer` WHERE `customer_id` = <TEST_CUSTOMER_ID>;
 *   COMMIT;
 * Replace {DB_PREFIX} from config.php and verify the exact test customer_id first.
 * Never run this against a real customer or an account with production orders.
 */

const CHECKOUT001_PATCH_ID = 'CHECKOUT-001_guest-account-creation_20260704';
const CHECKOUT001_CHECKOUT_TWIG = 'catalog/view/template/checkout/checkout.twig';
const CHECKOUT001_PAYMENT_TWIG = 'catalog/view/template/checkout/payment_method.twig';
const CHECKOUT001_PAYMENT_CONTROLLER = 'catalog/controller/checkout/payment_method.php';
const CHECKOUT001_MAIL_CONTROLLER = 'catalog/controller/mail/forgotten.php';
const CHECKOUT001_MAIL_TEMPLATE = 'catalog/view/template/mail/account_created.twig';

const CHECKOUT001_MARKER_CHECKOUT = 'CHECKOUT-001: pre-confirm guest account step.';
const CHECKOUT001_MARKER_PAYMENT_TWIG = 'CHECKOUT-001: guest account opt-in UI.';
const CHECKOUT001_MARKER_PAYMENT_CONTROLLER = 'CHECKOUT-001: guest account opt-in endpoint.';
const CHECKOUT001_MARKER_MAIL_CONTROLLER = 'CHECKOUT-001: account-created set-password email.';
const CHECKOUT001_MARKER_MAIL_TEMPLATE = 'CHECKOUT-001 account-created set-password email';

const CHECKOUT001_EXPECTED_SHA256 = [
    CHECKOUT001_CHECKOUT_TWIG => '767f205f4bec6eb0d0e44e42c4d9ebd5f35e522dd9b86bb50ea18ec513260637',
    CHECKOUT001_PAYMENT_TWIG => 'ee517e2778c1ea91d5519fbd782713108ab286e3fcd2eb41660223f984c2d6dc',
    CHECKOUT001_PAYMENT_CONTROLLER => '2cfc357418b0b140b8f41051730cf8d27694339feb6b53848f77201a6d00beeb',
    CHECKOUT001_MAIL_CONTROLLER => '09f1d5e9ad50b43022fd06ed3213ec4b201bdbec676ed82c15630205022cf9e5',
];

function checkout001_out(string $key, string $value): void {
    echo $key . '=' . $value . PHP_EOL;
}

function checkout001_fail(string $message): void {
    throw new RuntimeException($message);
}

function checkout001_path(string $root, string $relative): string {
    return rtrim($root, "/\\") . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function checkout001_read(string $root, string $relative): string {
    $path = checkout001_path($root, $relative);

    if (!is_file($path)) {
        checkout001_fail('target_missing:' . $relative);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        checkout001_fail('read_failed:' . $relative);
    }

    return $content;
}

function checkout001_replace_once(string $content, string $search, string $replace, string $label): string {
    $count = substr_count($content, $search);

    if ($count !== 1) {
        checkout001_fail('anchor_count_mismatch:' . $label . ':expected=1:actual=' . $count);
    }

    return str_replace($search, $replace, $content);
}

function checkout001_write(string $path, string $content): void {
    $directory = dirname($path);

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        checkout001_fail('mkdir_failed:' . $directory);
    }

    $bytes = file_put_contents($path, $content, LOCK_EX);

    if ($bytes !== strlen($content)) {
        checkout001_fail('write_failed:' . $path);
    }
}

function checkout001_lint(string $path, string $label): void {
    if (!function_exists('exec')) {
        checkout001_fail('php_lint_unavailable:exec_disabled:' . $label);
    }

    $output = [];
    $code = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    checkout001_out('php_lint_' . $label, 'exit=' . $code . ';output=' . implode(' | ', $output));

    if ($code !== 0) {
        checkout001_fail('php_lint_failed:' . $label);
    }
}

function checkout001_backup(string $root, string $backupRoot, string $relative): string {
    $source = checkout001_path($root, $relative);
    $backup = checkout001_path($backupRoot, $relative);
    $directory = dirname($backup);

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        checkout001_fail('backup_mkdir_failed:' . $relative);
    }

    if (!copy($source, $backup)) {
        checkout001_fail('backup_copy_failed:' . $relative);
    }

    checkout001_out('backup', $relative . ' -> ' . $backup);

    return $backup;
}

$root = getenv('BS_PATCH_ROOT') ?: getcwd();
$backupRoot = null;
$backups = [];
$written = [];
$createdTemplate = false;

try {
    checkout001_out('patch', CHECKOUT001_PATCH_ID);
    checkout001_out('cwd', getcwd());
    checkout001_out('root', $root);
    checkout001_out('time', date('c'));
    checkout001_out('scope', 'stock checkout guest account opt-in before existing single confirm.confirm');
    checkout001_out('db_schema_changes', 'none');
    checkout001_out('runtime_db_rows', 'explicit opt-in only: customer,address,customer_token,core activity/ip');
    checkout001_out('simplecheckout_changes', 'none');
    checkout001_out('url_php_changes', 'none');
    checkout001_out('confirm_php_changes', 'none');
    checkout001_out('customer_model_changes', 'none');
    checkout001_out('consent_copy', 'draft_requires_owner_signoff_before_deploy');

    $existingFiles = [
        CHECKOUT001_CHECKOUT_TWIG,
        CHECKOUT001_PAYMENT_TWIG,
        CHECKOUT001_PAYMENT_CONTROLLER,
        CHECKOUT001_MAIL_CONTROLLER,
    ];

    $contents = [];
    foreach ($existingFiles as $relative) {
        $contents[$relative] = checkout001_read($root, $relative);
    }

    $mailTemplatePath = checkout001_path($root, CHECKOUT001_MAIL_TEMPLATE);
    $mailTemplateExists = is_file($mailTemplatePath);
    $mailTemplateContent = $mailTemplateExists ? checkout001_read($root, CHECKOUT001_MAIL_TEMPLATE) : '';

    $markerStates = [
        CHECKOUT001_CHECKOUT_TWIG => strpos($contents[CHECKOUT001_CHECKOUT_TWIG], CHECKOUT001_MARKER_CHECKOUT) !== false,
        CHECKOUT001_PAYMENT_TWIG => strpos($contents[CHECKOUT001_PAYMENT_TWIG], CHECKOUT001_MARKER_PAYMENT_TWIG) !== false,
        CHECKOUT001_PAYMENT_CONTROLLER => strpos($contents[CHECKOUT001_PAYMENT_CONTROLLER], CHECKOUT001_MARKER_PAYMENT_CONTROLLER) !== false,
        CHECKOUT001_MAIL_CONTROLLER => strpos($contents[CHECKOUT001_MAIL_CONTROLLER], CHECKOUT001_MARKER_MAIL_CONTROLLER) !== false,
        CHECKOUT001_MAIL_TEMPLATE => $mailTemplateExists && strpos($mailTemplateContent, CHECKOUT001_MARKER_MAIL_TEMPLATE) !== false,
    ];

    $appliedCount = count(array_filter($markerStates));

    if ($appliedCount === count($markerStates)) {
        checkout001_lint(__FILE__, 'patch_self');
        checkout001_lint(checkout001_path($root, CHECKOUT001_PAYMENT_CONTROLLER), 'payment_controller');
        checkout001_lint(checkout001_path($root, CHECKOUT001_MAIL_CONTROLLER), 'mail_controller');
        checkout001_out('already_applied', 'yes');
        checkout001_out('changed_files', '0');
        checkout001_out('done', 'ok');
        @unlink(__FILE__);
        exit(0);
    }

    if ($appliedCount !== 0) {
        checkout001_fail('partial_state_detected:markers=' . $appliedCount . '/' . count($markerStates));
    }

    if ($mailTemplateExists) {
        checkout001_fail('unexpected_existing_file:' . CHECKOUT001_MAIL_TEMPLATE);
    }

    foreach (CHECKOUT001_EXPECTED_SHA256 as $relative => $expectedHash) {
        $actualHash = hash('sha256', $contents[$relative]);

        if (!hash_equals($expectedHash, $actualHash)) {
            checkout001_fail('live_sha256_mismatch:' . $relative . ':expected=' . $expectedHash . ':actual=' . $actualHash);
        }

        checkout001_out('source_sha256', $relative . ':' . $actualHash);
    }

    checkout001_lint(__FILE__, 'patch_self');

    $paymentTwigOld = <<<'TWIG'
{% if text_agree %}
  <div class="form-check form-switch form-switch-lg form-check-reverse mt-3">
    <label for="input-checkout-agree" class="form-check-label">{{ text_agree }}</label> <input type="checkbox" name="agree" value="1" id="input-checkout-agree" class="form-check-input"{% if agree %} checked{% endif %}/>
  </div>
{% endif %}
<script type="text/javascript"><!--
TWIG;

    $paymentTwigNew = <<<'TWIG'
{% if text_agree %}
  <div class="form-check form-switch form-switch-lg form-check-reverse mt-3">
    <label for="input-checkout-agree" class="form-check-label">{{ text_agree }}</label> <input type="checkbox" name="agree" value="1" id="input-checkout-agree" class="form-check-input"{% if agree %} checked{% endif %}/>
  </div>
{% endif %}
{% if show_create_account_opt_in %}
  <!-- CHECKOUT-001 START -->
  <div class="form-check form-switch form-switch-lg form-check-reverse mt-3" data-checkout001-account-opt-in>
    <label for="input-create-account-opt-in" class="form-check-label">
      <strong>Зберегти дані для наступного разу</strong>
      <span class="d-block small text-muted">Створимо обліковий запис і надішлемо одноразове посилання для встановлення пароля.{% if account_privacy %} Деталі — в <a href="{{ account_privacy }}" target="_blank" rel="noopener">умовах обробки персональних даних</a>.{% endif %}</span>
    </label>
    <input type="checkbox" name="create_account_opt_in" value="1" id="input-create-account-opt-in" class="form-check-input"{% if create_account_opt_in %} checked{% endif %}/>
  </div>
  <!-- CHECKOUT-001 END -->
{% endif %}
{# CHECKOUT-001: guest account opt-in UI. Draft microcopy requires owner sign-off before deploy. #}
<script type="text/javascript"><!--
TWIG;

    $paymentTwigPatched = checkout001_replace_once(
        $contents[CHECKOUT001_PAYMENT_TWIG],
        $paymentTwigOld,
        $paymentTwigNew,
        'payment_twig_markup'
    );

    $paymentTwigAgreeOld = <<<'JS'
      data: {
        comment: $('textarea[name="comment"]').val(),
        agree: $input.is(':checked') ? '1' : ''
      },
JS;

    $paymentTwigAgreeNew = <<<'JS'
      data: {
        comment: $('textarea[name="comment"]').val(),
        agree: $input.is(':checked') ? '1' : '',
        create_account_opt_in: $('#input-create-account-opt-in').is(':checked') ? '1' : ''
      },
JS;

    $paymentTwigPatched = checkout001_replace_once(
        $paymentTwigPatched,
        $paymentTwigAgreeOld,
        $paymentTwigAgreeNew,
        'payment_twig_agree_payload'
    );

    $paymentTwigReadyOld = <<<'JS'
  $(function() {
    bsSt2b6bPaymentLog('phase0b:new:payment-module-ready', null, { paymentCode: $('#input-payment-code').val() || '', pendingChoice: bsPaymentPendingChoice || '', shippingCode: $('#input-shipping-code').val() || '' });
JS;

    $paymentTwigReadyNew = <<<'JS'
  $(document).on('change', '#input-create-account-opt-in', function() {
    $.ajax({
      url: 'index.php?route=checkout/payment_method.comment&language={{ language }}',
      type: 'post',
      data: {
        comment: $('textarea[name="comment"]').val(),
        create_account_opt_in: this.checked ? '1' : ''
      },
      dataType: 'json',
      error: function() {
        $('#input-create-account-opt-in').prop('checked', false);
      }
    });
  });

  $(function() {
    bsSt2b6bPaymentLog('phase0b:new:payment-module-ready', null, { paymentCode: $('#input-payment-code').val() || '', pendingChoice: bsPaymentPendingChoice || '', shippingCode: $('#input-shipping-code').val() || '' });
JS;

    $paymentTwigPatched = checkout001_replace_once(
        $paymentTwigPatched,
        $paymentTwigReadyOld,
        $paymentTwigReadyNew,
        'payment_twig_optin_handler'
    );

    $paymentControllerDataOld = <<<'PHP'
		if (isset($this->session->data['agree'])) {
			$data['agree'] = $this->session->data['agree'];
		} else {
			$data['agree'] = '';
		}

		// Information
PHP;

    $paymentControllerDataNew = <<<'PHP'
		if (isset($this->session->data['agree'])) {
			$data['agree'] = $this->session->data['agree'];
		} else {
			$data['agree'] = '';
		}

		// CHECKOUT-001: guest account opt-in endpoint.
		$data['show_create_account_opt_in'] = !$this->customer->isLogged() && empty($this->session->data['customer']['customer_id']);
		$data['create_account_opt_in'] = !empty($this->session->data['checkout001_create_account_opt_in']);
		$data['account_privacy'] = '';

		// Information
PHP;

    $paymentControllerPatched = checkout001_replace_once(
        $contents[CHECKOUT001_PAYMENT_CONTROLLER],
        $paymentControllerDataOld,
        $paymentControllerDataNew,
        'payment_controller_view_data'
    );

    $paymentControllerInfoOld = <<<'PHP'
		if ($information_info) {
			$data['text_agree'] = sprintf($this->language->get('text_agree'), $this->url->link('information/information.info', 'language=' . $this->config->get('config_language') . '&information_id=' . $this->config->get('config_checkout_id')), $information_info['title']);
		} else {
			$data['text_agree'] = '';
		}
PHP;

    $paymentControllerInfoNew = <<<'PHP'
		if ($information_info) {
			$information_url = $this->url->link('information/information.info', 'language=' . $this->config->get('config_language') . '&information_id=' . $this->config->get('config_checkout_id'));
			$data['text_agree'] = sprintf($this->language->get('text_agree'), $information_url, $information_info['title']);
			$data['account_privacy'] = $information_url;
		} else {
			$data['text_agree'] = '';
		}
PHP;

    $paymentControllerPatched = checkout001_replace_once(
        $paymentControllerPatched,
        $paymentControllerInfoOld,
        $paymentControllerInfoNew,
        'payment_controller_privacy_link'
    );

    $paymentControllerCommentOld = <<<'PHP'
		// ST-2b.1: comment can be saved in session before deferred order exists.
		$this->session->data['comment'] = $comment;

		if ($order_id) {
PHP;

    $paymentControllerCommentNew = <<<'PHP'
		// ST-2b.1: comment can be saved in session before deferred order exists.
		$this->session->data['comment'] = $comment;

		if (array_key_exists('create_account_opt_in', $this->request->post)) {
			$this->checkout001SetOptIn((string)$this->request->post['create_account_opt_in'] === '1');
		}

		if ($order_id) {
PHP;

    $paymentControllerPatched = checkout001_replace_once(
        $paymentControllerPatched,
        $paymentControllerCommentOld,
        $paymentControllerCommentNew,
        'payment_controller_comment_persistence'
    );

    $paymentControllerMethodAnchor = <<<'PHP'
	/**
	 * Agree
	 *
	 * @return void
	 */
	public function agree(): void {
PHP;

    $paymentControllerMethods = <<<'PHP'
	/**
	 * CHECKOUT-001 pre-confirm account creation.
	 *
	 * Returns the same success response for unchecked, existing-email, and
	 * newly-created cases so the UI does not disclose account existence.
	 *
	 * @return void
	 */
	public function createAccount(): void {
		$json = ['success' => true];
		$requested = isset($this->request->post['create_account_opt_in']) && (string)$this->request->post['create_account_opt_in'] === '1';
		$this->checkout001SetOptIn($requested);

		if (!$requested || $this->customer->isLogged()) {
			$this->checkout001Json($json);
			return;
		}

		$customer_data = $this->session->data['customer'] ?? [];
		$email = trim((string)($customer_data['email'] ?? ''));
		$normalized_email = oc_strtolower($email);

		if (
			!empty($this->session->data['checkout001_account_processed']) &&
			(string)($this->session->data['checkout001_account_processed_email'] ?? '') === $normalized_email
		) {
			$this->checkout001Json($json);
			return;
		}

		if ((int)($customer_data['customer_id'] ?? 0) > 0) {
			$this->session->data['checkout001_account_processed'] = 'created';
			$this->session->data['checkout001_account_processed_email'] = $normalized_email;
			$this->checkout001Json($json);
			return;
		}

		if (
			!oc_validate_email($email) ||
			!oc_validate_length((string)($customer_data['firstname'] ?? ''), 1, 32) ||
			!oc_validate_length((string)($customer_data['lastname'] ?? ''), 1, 32) ||
			!oc_validate_length((string)($customer_data['telephone'] ?? ''), 3, 32)
		) {
			$this->checkout001Json(['error' => 'Не вдалося підготувати обліковий запис. Перевірте контактні дані або вимкніть опцію збереження даних.']);
			return;
		}

		$this->load->model('account/customer');

		if ($this->model_account_customer->getTotalCustomersByEmail($email)) {
			$this->session->data['checkout001_account_processed'] = 'existing';
			$this->session->data['checkout001_account_processed_email'] = $normalized_email;
			$this->checkout001Json($json);
			return;
		}

		$password = bin2hex(random_bytes(24)) . 'Aa1!';
		$created_customer_id = 0;
		$login_complete = false;

		try {
			$this->db->query('START TRANSACTION');

			$created_customer_id = $this->model_account_customer->addCustomer([
				'customer_group_id' => (int)($customer_data['customer_group_id'] ?? $this->config->get('config_customer_group_id')),
				'firstname'         => (string)$customer_data['firstname'],
				'lastname'          => (string)$customer_data['lastname'],
				'email'             => $email,
				'telephone'         => (string)$customer_data['telephone'],
				'custom_field'      => is_array($customer_data['custom_field'] ?? null) ? $customer_data['custom_field'] : [],
				'password'          => $password,
				'newsletter'        => 0
			]);

			if ($created_customer_id <= 0) {
				throw new \RuntimeException('customer_create_failed');
			}

			$this->load->model('account/address');
			$address_ids = $this->checkout001CreateAddresses($created_customer_id);

			if (!$address_ids) {
				throw new \RuntimeException('address_create_failed');
			}

			$code = oc_token(40);
			$this->session->data['checkout001_account_created_mail'] = $created_customer_id;
			$this->model_account_customer->addToken($created_customer_id, 'password', $code);
			unset($this->session->data['checkout001_account_created_mail']);

			$login_complete = $this->customer->login($email, $password);

			if (!$login_complete) {
				throw new \RuntimeException('customer_login_failed');
			}

			$this->db->query('COMMIT');

			$customer_data['customer_id'] = $created_customer_id;
			$this->session->data['customer'] = $customer_data;
			$this->session->data['customer_token'] = oc_token(26);
			$this->checkout001ApplyAddressIds($address_ids);
			$this->session->data['checkout001_account_processed'] = 'created';
			$this->session->data['checkout001_account_processed_email'] = $normalized_email;
			$this->session->data['checkout001_account_customer_id'] = $created_customer_id;
		} catch (\Throwable $error) {
			unset($this->session->data['checkout001_account_created_mail']);
			$this->db->query('ROLLBACK');

			if ($login_complete && method_exists($this->customer, 'logout')) {
				$this->customer->logout();
			}

			$this->log->write('CHECKOUT-001 account creation failed: ' . get_class($error));
			$this->checkout001Json(['error' => 'Не вдалося створити обліковий запис. Спробуйте ще раз або вимкніть опцію збереження даних.']);
			return;
		}

		$this->checkout001Json($json);
	}

	private function checkout001SetOptIn(bool $enabled): void {
		$previous = !empty($this->session->data['checkout001_create_account_opt_in']);

		if ($enabled) {
			$this->session->data['checkout001_create_account_opt_in'] = 1;
		} else {
			unset($this->session->data['checkout001_create_account_opt_in']);
		}

		if ($previous !== $enabled) {
			unset($this->session->data['checkout001_account_processed']);
			unset($this->session->data['checkout001_account_processed_email']);
			unset($this->session->data['checkout001_account_customer_id']);
		}
	}

	private function checkout001CreateAddresses(int $customer_id): array {
		$result = [];
		$payment = $this->session->data['payment_address'] ?? [];
		$shipping = $this->session->data['shipping_address'] ?? [];
		$payment_fingerprint = '';

		if ($this->config->get('config_checkout_payment_address') && $this->checkout001AddressIsValid($payment)) {
			$payment['default'] = 1;
			$result['payment'] = $this->model_account_address->addAddress($customer_id, $payment);
			$payment_fingerprint = $this->checkout001AddressFingerprint($payment);
		}

		if ($this->cart->hasShipping() && $this->checkout001AddressIsValid($shipping)) {
			$shipping_fingerprint = $this->checkout001AddressFingerprint($shipping);

			if (!empty($result['payment']) && $payment_fingerprint === $shipping_fingerprint) {
				$result['shipping'] = $result['payment'];
			} else {
				$shipping['default'] = empty($result);
				$result['shipping'] = $this->model_account_address->addAddress($customer_id, $shipping);
			}
		}

		return array_filter($result);
	}

	private function checkout001AddressIsValid(array $address): bool {
		return
			!empty($address['firstname']) &&
			!empty($address['lastname']) &&
			!empty($address['address_1']) &&
			!empty($address['city']) &&
			!empty($address['country_id']) &&
			!empty($address['zone_id']);
	}

	private function checkout001AddressFingerprint(array $address): string {
		$fields = [
			'firstname',
			'lastname',
			'company',
			'address_1',
			'address_2',
			'city',
			'postcode',
			'zone_id',
			'country_id',
			'custom_field'
		];
		$values = [];

		foreach ($fields as $field) {
			$values[$field] = $address[$field] ?? '';
		}

		return hash('sha256', json_encode($values));
	}

	private function checkout001ApplyAddressIds(array $address_ids): void {
		if (!empty($address_ids['payment']) && isset($this->session->data['payment_address'])) {
			$this->session->data['payment_address']['address_id'] = (int)$address_ids['payment'];
		}

		if (!empty($address_ids['shipping']) && isset($this->session->data['shipping_address'])) {
			$this->session->data['shipping_address']['address_id'] = (int)$address_ids['shipping'];
		}
	}

	private function checkout001Json(array $json): void {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Agree
	 *
	 * @return void
	 */
	public function agree(): void {
PHP;

    $paymentControllerPatched = checkout001_replace_once(
        $paymentControllerPatched,
        $paymentControllerMethodAnchor,
        $paymentControllerMethods,
        'payment_controller_create_account_methods'
    );

    $checkoutFunctionOld = <<<'JS'
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
    button.prop('disabled', true).addClass('loading').text('Створюємо замовлення...');
    $('#checkout-confirm').addClass('bs-confirm-loading');

    if (window.bsSt2b6Log) {
      window.bsSt2b6Log('checkout.twig:confirm.confirm:before-load', event || null, trigger || null);
    }

    $('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}', function(response, status) {
      if (window.bsSt2b6Log) {
        window.bsSt2b6Log('checkout.twig:confirm.confirm:complete', event || null, trigger || null, { requestStatus: status || '' });
      }
      $('#checkout-confirm').removeClass('bs-confirm-loading');

      if (status === 'error') {
        bsCheckoutConfirmSubmitting = false;
        $('#checkout-confirm').html('<div class="alert alert-danger mb-0">Не вдалося створити замовлення. Оновіть сторінку і спробуйте ще раз.</div>');
        return;
      }

      hideModelColumns();

      var realButton = $('#checkout-confirm #button-confirm').first();

      if (!realButton.length) {
        bsCheckoutConfirmSubmitting = false;
        $('#checkout-confirm').append('<div class="alert alert-danger mt-3 mb-0">Не знайдено кнопку підтвердження оплати після створення замовлення.</div>');
        return;
      }

      window.setTimeout(function() {
        realButton.trigger('click');
        bsCheckoutConfirmSubmitting = false;
      }, 30);
    });

    return false;
  };
JS;

    $checkoutFunctionNew = <<<'JS'
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

    $.ajax({
      url: 'index.php?route=checkout/payment_method.createAccount&language={{ language }}',
      type: 'post',
      data: {
        create_account_opt_in: $('#input-create-account-opt-in').is(':checked') ? '1' : ''
      },
      dataType: 'json',
      success: function(json) {
        if (json && json.error) {
          checkout001Fail(json.error);
          return;
        }

        loadConfirmAndSubmit();
      },
      error: function() {
        checkout001Fail('Не вдалося перевірити дані облікового запису. Спробуйте ще раз або вимкніть опцію збереження даних.');
      }
    });

    return false;
  };
JS;

    $checkoutTwigPatched = checkout001_replace_once(
        $contents[CHECKOUT001_CHECKOUT_TWIG],
        $checkoutFunctionOld,
        $checkoutFunctionNew,
        'checkout_preconfirm_function'
    );

    $mailControllerOld = <<<'PHP'
		if ($type == 'password' && $customer_info) {
			$this->load->language('mail/forgotten');

			$store_name = html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8');

			$subject = sprintf($this->language->get('text_subject'), $store_name);

			$data['text_greeting'] = sprintf($this->language->get('text_greeting'), $store_name);

			$data['reset'] = $this->url->link('account/forgotten.reset', 'language=' . $this->config->get('config_language') . '&email=' . urlencode($customer_info['email']) . '&code=' . $code, true);
			$data['ip'] = oc_get_ip();

			$data['store'] = $store_name;
			$data['store_url'] = $this->config->get('config_url');

			if ($this->config->get('config_mail_engine')) {
				$mail_option = [
					'parameter'     => $this->config->get('config_mail_parameter'),
					'smtp_hostname' => $this->config->get('config_mail_smtp_hostname'),
					'smtp_username' => $this->config->get('config_mail_smtp_username'),
					'smtp_password' => html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8'),
					'smtp_port'     => $this->config->get('config_mail_smtp_port'),
					'smtp_timeout'  => $this->config->get('config_mail_smtp_timeout')
				];

				$mail = new \Opencart\System\Library\Mail($this->config->get('config_mail_engine'), $mail_option);
				$mail->setTo($customer_info['email']);
				$mail->setFrom($this->config->get('config_email'));
				$mail->setSender($store_name);
				$mail->setSubject($subject);
				$mail->setHtml($this->load->view('mail/forgotten', $data));
				$mail->send();
			}
		}
PHP;

    $mailControllerNew = <<<'PHP'
		if ($type == 'password' && $customer_info) {
			$this->load->language('mail/forgotten');

			$store_name = html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8');
			$is_checkout001_account = !empty($this->session->data['checkout001_account_created_mail']) && (int)$this->session->data['checkout001_account_created_mail'] === $customer_id;

			// CHECKOUT-001: account-created set-password email.
			if ($is_checkout001_account) {
				$subject = 'Створіть пароль для облікового запису ' . $store_name;
				$data['firstname'] = (string)($customer_info['firstname'] ?? '');
				$template = 'mail/account_created';
			} else {
				$subject = sprintf($this->language->get('text_subject'), $store_name);
				$data['text_greeting'] = sprintf($this->language->get('text_greeting'), $store_name);
				$template = 'mail/forgotten';
			}

			$data['reset'] = $this->url->link('account/forgotten.reset', 'language=' . $this->config->get('config_language') . '&email=' . urlencode($customer_info['email']) . '&code=' . $code, true);
			$data['ip'] = oc_get_ip();

			$data['store'] = $store_name;
			$data['store_url'] = $this->config->get('config_url');

			if ($this->config->get('config_mail_engine')) {
				$mail_option = [
					'parameter'     => $this->config->get('config_mail_parameter'),
					'smtp_hostname' => $this->config->get('config_mail_smtp_hostname'),
					'smtp_username' => $this->config->get('config_mail_smtp_username'),
					'smtp_password' => html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8'),
					'smtp_port'     => $this->config->get('config_mail_smtp_port'),
					'smtp_timeout'  => $this->config->get('config_mail_smtp_timeout')
				];

				$mail = new \Opencart\System\Library\Mail($this->config->get('config_mail_engine'), $mail_option);
				$mail->setTo($customer_info['email']);
				$mail->setFrom($this->config->get('config_email'));
				$mail->setSender($store_name);
				$mail->setSubject($subject);
				$mail->setHtml($this->load->view($template, $data));
				$mail->send();
			}
		}
PHP;

    $mailControllerPatched = checkout001_replace_once(
        $contents[CHECKOUT001_MAIL_CONTROLLER],
        $mailControllerOld,
        $mailControllerNew,
        'mail_controller_account_template'
    );

    $mailTemplate = <<<'TWIG'
{# CHECKOUT-001 account-created set-password email #}
Вітаємо{% if firstname %}, {{ firstname }}{% endif %}!<br/>
<br/>
Для вашого замовлення створено обліковий запис у {{ store }}.<br/>
Щоб завершити налаштування, встановіть пароль за одноразовим посиланням:<br/>
<br/>
<a href="{{ reset }}">Створити пароль</a><br/>
<br/>
Посилання дійсне 10 хвилин і спрацює лише один раз.<br/>
Якщо ви не обирали збереження даних під час оформлення замовлення, зверніться до магазину.<br/>
<br/>
{{ store }}<br/>
{{ store_url }}
TWIG;

    if (substr_count($checkoutTwigPatched, 'checkout/confirm.confirm') !== 3) {
        checkout001_fail('postbuild_confirm_route_count_changed:' . substr_count($checkoutTwigPatched, 'checkout/confirm.confirm'));
    }

    if (substr_count($checkoutTwigPatched, "nativeEvent.isTrusted === true") !== 1) {
        checkout001_fail('postbuild_trusted_gate_changed');
    }

    if (substr_count($checkoutTwigPatched, "'#form-register input, #form-register select'") !== 1) {
        checkout001_fail('postbuild_autosave_selector_changed');
    }

    if (substr_count($paymentTwigPatched, 'name="create_account_opt_in"') !== 1) {
        checkout001_fail('postbuild_optin_field_count_changed');
    }

    if (substr_count($paymentControllerPatched, 'public function createAccount(): void') !== 1) {
        checkout001_fail('postbuild_create_account_method_count_changed');
    }

    $backupRoot = checkout001_path(
        $root,
        '_patch_backups/' . CHECKOUT001_PATCH_ID . '-' . date('Ymd-His')
    );

    foreach ($existingFiles as $relative) {
        $backups[$relative] = checkout001_backup($root, $backupRoot, $relative);
    }

    $patchedFiles = [
        CHECKOUT001_CHECKOUT_TWIG => $checkoutTwigPatched,
        CHECKOUT001_PAYMENT_TWIG => $paymentTwigPatched,
        CHECKOUT001_PAYMENT_CONTROLLER => $paymentControllerPatched,
        CHECKOUT001_MAIL_CONTROLLER => $mailControllerPatched,
    ];

    foreach ($patchedFiles as $relative => $content) {
        checkout001_write(checkout001_path($root, $relative), $content);
        $written[] = $relative;
    }

    checkout001_write($mailTemplatePath, $mailTemplate);
    $createdTemplate = true;
    $written[] = CHECKOUT001_MAIL_TEMPLATE;

    checkout001_lint(checkout001_path($root, CHECKOUT001_PAYMENT_CONTROLLER), 'payment_controller');
    checkout001_lint(checkout001_path($root, CHECKOUT001_MAIL_CONTROLLER), 'mail_controller');

    $finalChecks = [
        CHECKOUT001_CHECKOUT_TWIG => CHECKOUT001_MARKER_CHECKOUT,
        CHECKOUT001_PAYMENT_TWIG => CHECKOUT001_MARKER_PAYMENT_TWIG,
        CHECKOUT001_PAYMENT_CONTROLLER => CHECKOUT001_MARKER_PAYMENT_CONTROLLER,
        CHECKOUT001_MAIL_CONTROLLER => CHECKOUT001_MARKER_MAIL_CONTROLLER,
        CHECKOUT001_MAIL_TEMPLATE => CHECKOUT001_MARKER_MAIL_TEMPLATE,
    ];

    foreach ($finalChecks as $relative => $marker) {
        $final = checkout001_read($root, $relative);

        if (substr_count($final, $marker) !== 1) {
            checkout001_fail('postwrite_marker_failed:' . $relative);
        }
    }

    $finalCheckout = checkout001_read($root, CHECKOUT001_CHECKOUT_TWIG);

    if (substr_count($finalCheckout, 'checkout/confirm.confirm') !== 3) {
        checkout001_fail('postwrite_confirm_route_count_changed');
    }

    checkout001_out('already_applied', 'no');
    checkout001_out('changed_files', (string)count($written));
    foreach ($written as $relative) {
        checkout001_out('changed_file', $relative);
    }
    checkout001_out('rollback_code', 'restore files from ' . $backupRoot . ' and remove ' . CHECKOUT001_MAIL_TEMPLATE . ';then clear template cache');
    checkout001_out('rollback_runtime_test_data', 'use header SQL only for a confirmed test customer_id');
    checkout001_out('done', 'ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    checkout001_out('error', $error->getMessage());

    if ($backups) {
        $restoreOk = true;

        foreach ($backups as $relative => $backup) {
            $target = checkout001_path($root, $relative);
            $restored = is_file($backup) && copy($backup, $target);
            checkout001_out('restore', $relative . ':' . ($restored ? 'ok' : 'failed'));
            $restoreOk = $restoreOk && $restored;
        }

        if ($createdTemplate && is_file(checkout001_path($root, CHECKOUT001_MAIL_TEMPLATE))) {
            $removed = unlink(checkout001_path($root, CHECKOUT001_MAIL_TEMPLATE));
            checkout001_out('restore_remove_new_template', $removed ? 'ok' : 'failed');
            $restoreOk = $restoreOk && $removed;
        }

        checkout001_out('restore_on_fail', $restoreOk ? 'ok' : 'failed');
    } else {
        checkout001_out('restore_on_fail', 'not_needed');
    }

    checkout001_out('done', 'failed');
    exit(1);
}
