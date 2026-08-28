<?php
namespace Opencart\Catalog\Controller\Checkout;
/**
 * Class PaymentMethod
 *
 * @package Opencart\Catalog\Controller\Checkout
 */
class PaymentMethod extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('checkout/payment_method');

		if (isset($this->session->data['payment_method'])) {
			$data['payment_method'] = $this->session->data['payment_method']['name'];
			$data['code'] = $this->session->data['payment_method']['code'];
		} else {
			$data['payment_method'] = '';
			$data['code'] = '';
		}

		if (isset($this->session->data['comment'])) {
			$data['comment'] = $this->session->data['comment'];
		} else {
			$data['comment'] = '';
		}

		if (isset($this->session->data['agree'])) {
			$data['agree'] = $this->session->data['agree'];
		} else {
			$data['agree'] = '';
		}

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

		$data['language'] = $this->config->get('config_language');

		return $this->load->view('checkout/payment_method', $data);
	}

	/**
	 * Get Methods
	 *
	 * @return void
	 */
	public function getMethods(): void {
		$this->load->language('checkout/payment_method');

		$json = [];

		// Validate cart has products and has stock.
		if (!$this->cart->hasProducts() || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) || !$this->cart->hasMinimum()) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
			// Validate if customer session data is set
			if (!isset($this->session->data['customer'])) {
				$json['error'] = $this->language->get('error_customer');
			}

			if ($this->config->get('config_checkout_payment_address') && !isset($this->session->data['payment_address'])) {
				$json['error'] = $this->language->get('error_payment_address');
			}

			// Validate shipping
			if ($this->cart->hasShipping()) {
				// Validate shipping address
				if (!isset($this->session->data['shipping_address']['address_id'])) {
					$json['error'] = $this->language->get('error_shipping_address');
				}

				// Validate shipping method
				if (!isset($this->session->data['shipping_method'])) {
					$json['error'] = $this->language->get('error_shipping_method');
				}
			}
		}

		if (!$json) {
			$pay001_gate = $this->pay001MonoChastGate();
			$current_code = (string)($this->session->data['payment_method']['code'] ?? '');
			$pay001_selected = str_starts_with($current_code, 'mono_chast.');
			$payment_methods = $this->getBoosterCheckoutPaymentMethods($pay001_gate);

			if (isset($this->session->data['payment_method']['code'])) {
				if (!$this->boosterPaymentByCode($payment_methods, $current_code)) {
					unset($this->session->data['payment_method']);
				}
			}

			$this->session->data['payment_methods'] = $payment_methods;

			if ($payment_methods) {
				$json['payment_methods'] = $payment_methods;
			} else {
				$json['error'] = sprintf($this->language->get('error_no_payment'), $this->url->link('information/contact', 'language=' . $this->config->get('config_language')));
			}

			if (!empty($pay001_gate['configured'])) {
				$json['pay001_credit_gate'] = $pay001_gate + [
					'selected' => $pay001_selected,
					'selected_code' => $current_code
				];
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/** @return array<string, mixed> */
	private function getBoosterCheckoutPaymentMethods(?array $pay001_gate = null): array {
		$payment_address = [];

		if ($this->config->get('config_checkout_payment_address') && isset($this->session->data['payment_address'])) {
			$payment_address = $this->session->data['payment_address'];
		} elseif ($this->config->get('config_checkout_shipping_address') && isset($this->session->data['shipping_address']['address_id'])) {
			$payment_address = $this->session->data['shipping_address'];
		}

		$this->load->model('checkout/payment_method');

		$payment_methods = $this->filterBoosterCheckoutPaymentMethods(
			$this->model_checkout_payment_method->getMethods($payment_address)
		);

		// Dedicated stock-checkout entry. Do not call the Mono model here:
		// deployed SimpleCheckout isolation deliberately makes its getMethods() return [].
		$pay001_gate ??= $this->pay001MonoChastGate();
		$pay001_mono_method = $this->pay001MonoChastMethod($pay001_gate);
		if ($pay001_mono_method) {
			$payment_methods['mono_chast'] = $pay001_mono_method;
		}

		return $payment_methods;
	}

	/**
	 * Booster checkout exposes one canonical option for each supported payment
	 * category. Unknown extensions and duplicate stock COD never reach the UI or
	 * the session validation map.
	 *
	 * @param array<string, mixed> $payment_methods
	 * @return array<string, mixed>
	 */
	private function filterBoosterCheckoutPaymentMethods(array $payment_methods): array {
		$candidates = [];
		$sequence = 0;

		foreach ($payment_methods as $group_key => $group) {
			$options = is_array($group['option'] ?? null) ? $group['option'] : [];

			foreach ($options as $option_key => $option) {
				if (!is_array($option)) {
					continue;
				}

				$category = $this->boosterPaymentCategory($option);

				if ($category === '') {
					continue;
				}

				$candidates[$category][] = [
					'group_key' => $group_key,
					'option_key' => $option_key,
					'option' => $option,
					'score' => $this->boosterPaymentPreferenceScore($category, $option),
					'sequence' => $sequence++,
				];
			}
		}

		$filtered = [];

		foreach (['hutko', 'cod', 'bank'] as $category) {
			if (empty($candidates[$category])) {
				continue;
			}

			usort($candidates[$category], static function(array $left, array $right): int {
				return [$left['score'], $left['sequence']] <=> [$right['score'], $right['sequence']];
			});

			$selected = $candidates[$category][0];
			$group_key = $selected['group_key'];

			if (!isset($filtered[$group_key])) {
				$filtered[$group_key] = $payment_methods[$group_key];
				$filtered[$group_key]['option'] = [];
			}

			$selected_option = $selected['option'];
			$selected_option['booster_category'] = $category;
			$filtered[$group_key]['option'][$selected['option_key']] = $selected_option;
		}

		return $filtered;
	}

	/** @param array<string, mixed> $option */
	private function boosterPaymentCategory(array $option): string {
		$code = oc_strtolower(trim((string)($option['code'] ?? '')));
		$name = oc_strtolower(trim((string)($option['name'] ?? $option['title'] ?? '')));
		$value = $code . ' ' . $name;

		if (str_contains($value, 'hutko') || str_contains($value, 'mono') || str_contains($value, 'card') || str_contains($value, 'картк')) {
			return 'hutko';
		}

		if (str_contains($value, 'cod') || str_contains($value, 'cash') || str_contains($value, 'after') || str_contains($value, 'післяплат') || str_contains($value, 'накладен')) {
			return 'cod';
		}

		if (str_contains($value, 'bank') || str_contains($value, 'transfer') || str_contains($value, 'rekv') || str_contains($value, 'iban') || str_contains($value, 'реквізит')) {
			return 'bank';
		}

		return '';
	}

	/** @param array<string, mixed> $option */
	private function boosterPaymentPreferenceScore(string $category, array $option): int {
		$code = oc_strtolower(trim((string)($option['code'] ?? '')));
		$name = oc_strtolower(trim((string)($option['name'] ?? $option['title'] ?? '')));
		$score = 20;

		if ($category === 'hutko' && str_contains($code, 'hutko')) {
			$score = 0;
		}

		if ($category === 'bank' && (str_contains($code, 'bank') || str_contains($code, 'transfer'))) {
			$score = 0;
		}

		if ($category === 'cod') {
			if (str_contains($name, 'накладений платіж') || str_contains($name, 'післяплата')) {
				$score = 0;
			} elseif (str_contains($code, 'pinta') || str_contains($code, 'nova') || str_contains($code, 'after')) {
				$score = 5;
			}

			// The generic OpenCart COD option is a fallback only. If the configured
			// Nova Poshta/post-payment option exists, it wins deterministically.
			if ($code === 'cod.cod') {
				$score += 100;
			}
		}

		return $score;
	}

	/** @param array<string, mixed> $payment_methods */
	private function boosterPaymentByCode(array $payment_methods, string $code): array {
		foreach ($payment_methods as $group) {
			foreach ((array)($group['option'] ?? []) as $option) {
				if (is_array($option) && (string)($option['code'] ?? '') === $code) {
					return $option;
				}
			}
		}

		return [];
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('checkout/payment_method');

		$json = [];
		$selected_payment = [];

		// Validate cart has products and has stock.
		if (!$this->cart->hasProducts() || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) || !$this->cart->hasMinimum()) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
			// Validate has payment address if required
			if ($this->config->get('config_checkout_payment_address') && !isset($this->session->data['payment_address'])) {
				$json['error'] = $this->language->get('error_payment_address');
			}

			// Validate shipping
			if ($this->cart->hasShipping()) {
				// Validate shipping address
				if (!isset($this->session->data['shipping_address']['address_id'])) {
					$json['error'] = $this->language->get('error_shipping_address');
				}

				// Validate shipping method
				if (!isset($this->session->data['shipping_method'])) {
					$json['error'] = $this->language->get('error_shipping_method');
				}
			}

			// Validate payment methods at the write boundary from the current checkout
			// context. Do not trust a transient session map written by another AJAX request.
			// ST-2c.3b: resolve the canonical option from current server state.
			if (isset($this->request->post['payment_method'])) {
				$payment_methods = $this->getBoosterCheckoutPaymentMethods($this->pay001MonoChastGate());
				$this->session->data['payment_methods'] = $payment_methods;
				$selected_payment = $this->boosterPaymentByCode(
					$payment_methods,
					(string)$this->request->post['payment_method']
				);
			}

			if (!$selected_payment) {
				$json['error'] = $this->language->get('error_payment_method');
			}
		}

		if (!$json) {
			$pay001_boundary_error = $this->pay001PreparePaymentChange(
				(string)($selected_payment['code'] ?? '')
			);

			if ($pay001_boundary_error !== '') {
				$json['error'] = $pay001_boundary_error;
			}
		}

		if (!$json) {
			$this->session->data['payment_method'] = $selected_payment;

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function pay001MonoChastConfigured(): bool {
		if (!$this->config->get('payment_mono_chast_status')) {
			return false;
		}
		$currency = strtoupper((string)($this->session->data['currency'] ?? $this->config->get('config_currency')));
		if ($currency !== 'UAH') {
			return false;
		}
		// UI-QA is allowed before Monobank issues the merchant point_id.
		// The payment controller keeps point_id as a hard server-side preflight,
		// so no bank application can be sent from an incomplete onboarding.
		foreach (['payment_mono_chast_api_base', 'payment_mono_chast_store_id', 'payment_mono_chast_store_secret'] as $key) {
			if (trim((string)$this->config->get($key)) === '') {
				return false;
			}
		}
		return true;
	}

	/**
	 * Authoritative Phase 2c gate. Preorder wins over the payable threshold.
	 *
	 * @return array<string, mixed>
	 */
	private function pay001MonoChastGate(): array {
		$threshold = max(500.0, (float)$this->config->get('payment_mono_chast_min_total'));
		$gate = [
			'configured' => $this->pay001MonoChastConfigured(),
			'available' => false,
			'reason' => 'config',
			'threshold' => round($threshold, 2),
			'payable' => 0.0,
			'remaining' => round($threshold, 2)
		];

		if (!$gate['configured']) {
			return $gate;
		}

		foreach ($this->cart->getProducts() as $product) {
			if ((int)($product['stock'] ?? 0) < 1) {
				$gate['reason'] = 'preorder';
				return $gate;
			}
		}

		$this->load->model('checkout/booster_coupon');
		$this->model_checkout_booster_coupon->prepareCouponTotal();

		$totals = [];
		$taxes = $this->cart->getTaxes();
		$total = 0;
		$this->load->model('checkout/cart');
		($this->model_checkout_cart->getTotals)($totals, $taxes, $total);

		$payable = max(0.0, (float)$total);
		$gate['payable'] = round($payable, 2);
		$gate['remaining'] = round(max(0.0, $threshold - $payable), 2);

		if ($payable < $threshold) {
			$gate['reason'] = 'threshold';
			return $gate;
		}

		$gate['available'] = true;
		$gate['reason'] = '';
		$gate['remaining'] = 0.0;
		return $gate;
	}

	private function pay001MonoChastMethod(array $gate): array {
		if (empty($gate['available'])) {
			return [];
		}
		$configured_parts = json_decode((string)$this->config->get('payment_mono_chast_parts'), true);
		$configured_parts = is_array($configured_parts) ? array_values(array_unique(array_map('intval', $configured_parts))) : [];
		$parts = array_values(array_filter([3, 4, 5], static fn(int $value): bool => in_array($value, $configured_parts, true)));
		if (!$parts) {
			$parts = [3, 4, 5];
		}
		$preferred = (int)($this->session->data['pay001_mono_chast_parts'] ?? 3);
		if (!in_array($preferred, $parts, true)) {
			$preferred = $parts[0];
		}
		$options = [];
		foreach ($parts as $part) {
			$options['mono_chast_' . $part] = [
				'code' => 'mono_chast.mono_chast_' . $part,
				'name' => 'Оплатити частинами'
			];
		}
		return [
			'code' => 'mono_chast',
			'name' => 'Оплатити частинами',
			'option' => $options,
			'pay001_credit' => true,
			'pay001_preferred' => $preferred,
			'pay001_from_modal' => !empty($this->session->data['pay001_mono_chast_from_modal']),
			'pay001_total' => (float)$gate['payable'],
			'sort_order' => (int)$this->config->get('payment_mono_chast_sort_order')
		];
	}

	/**
	 * A failed/unsubmitted Mono draft cannot be completed by another payment
	 * controller. Release its session order_id so the next explicit checkout
	 * confirmation creates a fresh order with the newly selected method.
	 *
	 * A real Mono application is fail-closed: switching methods from the same
	 * browser session could create a duplicate purchase.
	 */
	private function pay001PreparePaymentChange(string $next_code): string {
		$order_id = (int)($this->session->data['order_id'] ?? 0);

		if ($order_id < 1 || str_starts_with($next_code, 'mono_chast.')) {
			return '';
		}

		$this->load->model('checkout/order');
		$order = $this->model_checkout_order->getOrder($order_id);

		if (!$order) {
			unset($this->session->data['order_id']);
			return '';
		}

		$payment = $order['payment_method'] ?? [];

		if (is_string($payment)) {
			$decoded = json_decode($payment, true);
			$payment = is_array($decoded) ? $decoded : [];
		}

		$current_code = is_array($payment) ? (string)($payment['code'] ?? '') : '';

		if (!str_starts_with($current_code, 'mono_chast.')) {
			return '';
		}

		try {
			$query = $this->db->query(
				"SELECT `mono_order_id`,`state` FROM `" . DB_PREFIX . "mono_chast_transaction` " .
				"WHERE `order_id`='" . $order_id . "' ORDER BY `mono_chast_transaction_id` DESC LIMIT 1"
			);
			$transaction = $query->row ?? [];
		} catch (\Throwable $error) {
			$this->log->write('PAY-001 payment-change guard failed order_id=' . $order_id . ' error=' . get_class($error));
			return 'Не вдалося перевірити стан заявки monobank. Оновіть сторінку та спробуйте ще раз.';
		}

		$mono_order_id = trim((string)($transaction['mono_order_id'] ?? ''));
		$state = strtoupper(trim((string)($transaction['state'] ?? '')));
		$failed = $state === 'CREATE_FAILED' || str_starts_with($state, 'FAIL');

		if ($mono_order_id !== '' && !$failed) {
			return 'Заявку monobank уже створено. Завершіть її у застосунку або зверніться до підтримки.';
		}

		unset(
			$this->session->data['order_id'],
			$this->session->data['pay001_mono_chast_from_modal'],
			$this->session->data['pay001_mono_chast_parts']
		);

		return '';
	}
	/**
	 * Comment
	 *
	 * @return void
	 */
	public function comment(): void {
		$this->load->language('checkout/payment_method');
		$this->load->model('checkout/order');

		$json = [];

		if (isset($this->session->data['order_id'])) {
			$order_id = (int)$this->session->data['order_id'];
		} else {
			$order_id = 0;
		}

		if (isset($this->request->post['comment'])) {
			$comment = (string)$this->request->post['comment'];
		} else {
			$comment = '';
		}

		// ST-2b.1: comment can be saved in session before deferred order exists.
		$this->session->data['comment'] = $comment;

		// CHECKOUT-001 Phase 1.2: persist mandatory oferta agreement.
		if (array_key_exists('agree', $this->request->post)) {
			if ((string)$this->request->post['agree'] === '1') {
				$this->session->data['agree'] = 1;
			} else {
				unset($this->session->data['agree']);
			}
		}

		if (array_key_exists('create_account_opt_in', $this->request->post)) {
			$this->checkout001SetOptIn((string)$this->request->post['create_account_opt_in'] === '1');
		}

		if ($order_id) {
			$order_info = $this->model_checkout_order->getOrder($order_id);
		} else {
			$order_info = [];
		}

		if ($order_id && !$order_info) {
			$json['error'] = $this->language->get('error_order');
		}

		if (!$json) {
			if ($order_info) {
				$this->model_checkout_order->editComment($order_id, $comment);
			}

			$json['success'] = $this->language->get('text_comment');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

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
			$this->log->write('CHECKOUT-001 account creation skipped: invalid guest session data');
			$this->checkout001SetOptIn(false);
			$this->checkout001Json($json);
			return;
		}

		// CHECKOUT-005 structured Nova Poshta handoff for guest account creation.
		// register.save already carries the selected NP point in its session address.
		// The pre-confirm account call must receive and validate the same refs before
		// addAddress(), otherwise it creates an unrecoverable legacy address-book row.
		$checkout005_np_address = [];

		if (array_key_exists('shipping_novaposhta_type', $this->request->post)) {
			$checkout005_np_address = $this->checkout005PrepareNpAddress($this->request->post, $json);

			if (!empty($json['error'])) {
				$this->checkout001Json($json);
				return;
			}

			$this->checkout005ApplyNpAddressToCheckoutSession($checkout005_np_address);
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

		// CHECKOUT-007: persist the durable First15 eligibility flag.
		// Preserve every existing customer custom field; this marker belongs only
		// to the guest-during-checkout account creation path.
		$checkout007_customer_custom_field = is_array($customer_data['custom_field'] ?? null) ? $customer_data['custom_field'] : [];
		$checkout007_customer_custom_field['bs_first15_pending'] = 1;

		try {
			$this->db->query('START TRANSACTION');

			$created_customer_id = $this->model_account_customer->addCustomer([
				'customer_group_id' => (int)($customer_data['customer_group_id'] ?? $this->config->get('config_customer_group_id')),
				'firstname'         => (string)$customer_data['firstname'],
				'lastname'          => (string)$customer_data['lastname'],
				'email'             => $email,
				'telephone'         => (string)$customer_data['telephone'],
				'custom_field'      => $checkout007_customer_custom_field,
				'password'                                => $password,
				'newsletter'                              => 0,
				'checkout001_skip_standard_register_mail' => true
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

			// CHECKOUT-006 next-order First15 offer flag.
			// Do not alter coupon state here: the current order must retain the total
			// shown in the confirm panel. The success page consumes this one-time flag.
			$this->session->data['checkout001_first15_offer_pending'] = 1;
		} catch (\Throwable $error) {
			unset($this->session->data['checkout001_account_created_mail']);

			try {
				$this->db->query('ROLLBACK');
			} catch (\Throwable $rollback_error) {
				$this->log->write('CHECKOUT-001 rollback failed: ' . get_class($rollback_error));
			}

			if ($login_complete && method_exists($this->customer, 'logout')) {
				$this->customer->logout();
			}

			$this->log->write('CHECKOUT-001 account creation failed: ' . get_class($error));
			$this->checkout001SetOptIn(false);
			$this->checkout001Json($json);
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

	/**
	 * CHECKOUT-005: mirror the validated NP address contract used by
	 * checkout/shipping_address.save before CHECKOUT-001 persists an address.
	 */
	private function checkout005PrepareNpAddress(array $post, array &$json): array {
		$module_type = trim((string)($post['shipping_novaposhta_type'] ?? ''));
		$type_map = ['warehouse' => 'warehouse', 'poshtoma' => 'poshtomat', 'doors' => 'courier'];
		$type = $type_map[$module_type] ?? '';
		$area_label = trim((string)($post['shipping_novaposhta_area'] ?? ''));
		$city_label = trim((string)($post['shipping_novaposhta_city'] ?? ''));
		$area_ref = trim((string)($post['shipping_novaposhta_area_ref'] ?? ''));
		$city_ref = trim((string)($post['shipping_novaposhta_city_ref'] ?? ''));
		$point_label = $type === 'courier'
			? trim((string)($post['shipping_novaposhta_doors_street'] ?? ''))
			: trim((string)($post['shipping_novaposhta_warehouse_address'] ?? ''));
		$point_ref = $type === 'courier'
			? trim((string)($post['shipping_novaposhta_street_ref'] ?? ''))
			: trim((string)($post['shipping_novaposhta_warehouse_ref'] ?? ''));
		$house = trim((string)($post['shipping_novaposhta_doors_house'] ?? ''));
		$flat = trim((string)($post['shipping_novaposhta_doors_flat'] ?? ''));

		if ($type === '') {
			$json['error']['novaposhta_type'] = 'Оберіть тип доставки Нової пошти.';
		}
		if (!oc_validate_length($area_label, 1, 128) || $area_ref === '') {
			$json['error']['novaposhta_area'] = 'Оберіть область із довідника Нової пошти.';
		}
		if (!oc_validate_length($city_label, 2, 128) || $city_ref === '') {
			$json['error']['novaposhta_city'] = 'Оберіть місто із довідника Нової пошти.';
		}
		if (!oc_validate_length($point_label, 1, 128) || $point_ref === '') {
			$json['error'][$type === 'courier' ? 'novaposhta_doors_street' : 'novaposhta_warehouse_address'] = 'Оберіть точку Нової пошти із довідника.';
		}
		if ($type === 'courier' && !oc_validate_length($house, 1, 32)) {
			$json['error']['novaposhta_doors_house'] = 'Вкажіть номер будинку (до 32 символів).';
		}
		if (strlen($flat) > 32) {
			$json['error']['novaposhta_doors_flat'] = 'Номер квартири має містити до 32 символів.';
		}
		if (!empty($json['error'])) {
			return [];
		}

		$this->load->model('extension/PintaNovaPoshtaCod/module/area');
		$this->load->model('extension/PintaNovaPoshtaCod/module/city');
		$this->load->model('extension/PintaNovaPoshtaCod/module/warehouse');
		$this->load->model('extension/PintaNovaPoshtaCod/module/street');
		$area = $this->model_extension_PintaNovaPoshtaCod_module_area->getByName($area_label);
		$city = $this->model_extension_PintaNovaPoshtaCod_module_city->getByName($city_label);

		if (!$area || (string)($area['ref'] ?? '') !== $area_ref) {
			$json['error']['novaposhta_area'] = 'Область Нової пошти більше не доступна. Оберіть її повторно.';
			return [];
		}
		if (!$city || (string)($city['ref'] ?? '') !== $city_ref || (string)($city['area'] ?? '') !== (string)$area['ref']) {
			$json['error']['novaposhta_city'] = 'Місто Нової пошти більше не доступне для цієї області. Оберіть його повторно.';
			return [];
		}
		$zone_id = (int)$this->model_extension_PintaNovaPoshtaCod_module_area->getZoneIdByRef($area['ref']);
		if (!$zone_id) {
			$json['error']['novaposhta_area'] = 'Не вдалося визначити область доставки. Оберіть область повторно.';
			return [];
		}

		$metadata = [
			'version' => 1,
			'type' => $type,
			'area_ref' => (string)$area['ref'],
			'city_ref' => (string)$city['ref'],
			'warehouse_ref' => '',
			'street_ref' => '',
			'labels' => [
				'area' => (string)($area['description'] ?? $area_label),
				'city' => (string)($city['description'] ?? $city_label),
				'point' => '',
			],
			'house' => '',
			'flat' => '',
		];

		if ($type === 'warehouse' || $type === 'poshtomat') {
			$warehouse = $this->model_extension_PintaNovaPoshtaCod_module_warehouse->getByRef($point_ref);
			$poshtomat_type_ref = 'f9316480-5f2d-425d-bc2c-ac7cd29decf0';
			if (!$warehouse || (string)($warehouse['city_ref'] ?? '') !== (string)$city['ref'] || ($type === 'poshtomat' && (string)($warehouse['type_of_warehouse'] ?? '') !== $poshtomat_type_ref) || ($type === 'warehouse' && (string)($warehouse['type_of_warehouse'] ?? '') === $poshtomat_type_ref)) {
				$json['error']['novaposhta_warehouse_address'] = 'Обрана точка Нової пошти не відповідає типу доставки. Оберіть її повторно.';
				return [];
			}
			$point = (string)($warehouse['description'] ?? $point_label);
			$metadata['warehouse_ref'] = (string)$warehouse['ref'];
			$metadata['labels']['point'] = $point;
			return ['metadata' => $metadata, 'city' => (string)($city['description'] ?? $city_label), 'address_1' => $point, 'address_2' => '', 'country_id' => 220, 'zone_id' => $zone_id];
		}

		$street = $this->model_extension_PintaNovaPoshtaCod_module_street->getByName($point_label, (string)$city['ref']);
		if (!$street || (string)($street['ref'] ?? '') !== $point_ref) {
			$json['error']['novaposhta_doors_street'] = 'Вулиця Нової пошти недоступна. Оберіть її повторно.';
			return [];
		}
		$street_label = trim((string)($street['street_type'] ?? '') . ' ' . (string)($street['description'] ?? $point_label));
		$metadata['street_ref'] = (string)$street['ref'];
		$metadata['labels']['point'] = $street_label;
		$metadata['house'] = $house;
		$metadata['flat'] = $flat;
		return ['metadata' => $metadata, 'city' => (string)($city['description'] ?? $city_label), 'address_1' => 'Адресна доставка Нової пошти', 'address_2' => $street_label . ', ' . $house . ($flat !== '' ? ', кв. ' . $flat : ''), 'country_id' => 220, 'zone_id' => $zone_id];
	}

	private function checkout005ApplyNpAddressToCheckoutSession(array $np_address): void {
		foreach (['payment_address', 'shipping_address'] as $key) {
			$address = $this->session->data[$key] ?? [];
			if (!is_array($address)) {
				continue;
			}
			$address['city'] = $np_address['city'];
			$address['address_1'] = $np_address['address_1'];
			$address['address_2'] = $np_address['address_2'];
			$address['country_id'] = $np_address['country_id'];
			$address['zone_id'] = $np_address['zone_id'];
			$address['custom_field'] = is_array($address['custom_field'] ?? null) ? $address['custom_field'] : [];
			$address['custom_field']['bs_np_v1'] = $np_address['metadata'];
			$this->session->data[$key] = $address;
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
		$this->load->language('checkout/payment_method');

		$json = [];

		if (isset($this->request->post['agree'])) {
			$this->session->data['agree'] = $this->request->post['agree'];
		} else {
			unset($this->session->data['agree']);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
