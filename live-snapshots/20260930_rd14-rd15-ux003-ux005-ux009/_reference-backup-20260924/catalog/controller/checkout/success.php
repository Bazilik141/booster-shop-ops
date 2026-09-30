<?php
namespace Opencart\Catalog\Controller\Checkout;
/**
 * Class Success
 *
 * @package Opencart\Catalog\Controller\Checkout
 */
class Success extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
        // PAY-003: validate credit recovery before success can clear any session/cart.
        $pay003_recovery = false;
        $pay003_requested = $this->request->get['credit_order_id'] ?? null;
        $pay003_id = $pay003_requested === null ? (int)($this->session->data['order_id'] ?? 0) : (is_scalar($pay003_requested) && preg_match('/^[1-9][0-9]{0,9}$/D', (string)$pay003_requested) ? (int)$pay003_requested : 0);
        $this->load->model('checkout/credit');
        $pay003_context = $this->model_checkout_credit->context($pay003_id);
        if ($pay003_requested !== null && !$pay003_context) {
            $this->response->addHeader('HTTP/1.1 404 Not Found');
            $this->response->addHeader('Cache-Control: no-store, private');
            $this->response->setOutput('Посилання недоступне.');
            return;
        }
        if ($pay003_context) {
            $pay003_tx = $this->model_checkout_credit->transaction($pay003_context);
            $pay003_view = $this->model_checkout_credit->present($pay003_context['provider'], $pay003_tx);
            if (!$pay003_view['confirmed']) {
                $this->response->redirect($this->model_checkout_credit->url($pay003_id));
                return;
            }
            $pay003_recovery = $pay003_requested !== null;
            $this->response->addHeader('Cache-Control: no-store, private');
        }
		$this->load->language('checkout/success');

		// ST-2b.2: keep success order id available for safe re-render after cookie/F5 reload.
		$order_id = $pay003_recovery ? $pay003_id : (int)($this->session->data['order_id'] ?? 0);
		$session_success_order_id = $this->getSessionSuccessOrderId();
		$hutko_return_context = $this->getHutkoReturnContext();

		// CHECKOUT-006: consume the courtesy message flag once.
		// It is intentionally not part of the Hutko reload-resilience contract.
		$show_first15_offer = !$pay003_recovery && !empty($this->session->data['checkout001_first15_offer_pending']);
		if (!$pay003_recovery) unset($this->session->data['checkout001_first15_offer_pending']);

		// CHECKOUT-007A: recover the shared success message from the durable customer flag.
		// The normal flag is one-time. Some non-Hutko success routes render after it
		// has already been consumed, so only the just-created shortcut account may
		// use this read-only fallback.
		$checkout007_shortcut_created = (string)($this->session->data['checkout001_account_processed'] ?? '') === 'created';
		$checkout007_shortcut_customer_id = (int)($this->session->data['checkout001_account_customer_id'] ?? ($this->session->data['customer']['customer_id'] ?? 0));

		$candidate_order_ids = [];

		if ($order_id > 0) {
			$candidate_order_ids[] = $order_id;
		} else {
			if ($session_success_order_id > 0) {
				$candidate_order_ids[] = $session_success_order_id;
			}

			if (!empty($hutko_return_context['order_id'])) {
				$candidate_order_ids[] = (int)$hutko_return_context['order_id'];
			}
		}

		$candidate_order_ids = array_values(array_unique(array_filter($candidate_order_ids)));

		if (!$pay003_recovery && isset($this->session->data['order_id'])) {
			$this->cart->clear();

			if ($order_id > 0) {
				$this->session->data['bs_success_order_id'] = $order_id;
				$this->session->data['bs_success_order_at'] = time();
			}

			unset($this->session->data['order_id']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['comment']);
			unset($this->session->data['agree']);
			// RD-13.1B: the receiver override belongs to one checkout/order only.
			unset($this->session->data['rd13_receiver_override']);
			unset($this->session->data['coupon']);
			unset($this->session->data['reward']);
		unset($this->session->data['welcome_coupon_pending']);
		unset($this->session->data['welcome_coupon_applied']);
		unset($this->session->data['welcome_coupon_error']);
		}

		// PAY-003: historical recovery must not clear discounts for a new checkout.
		if (!$pay003_recovery) {
			unset($this->session->data['coupon']);
			unset($this->session->data['reward']);
		}

		// R-11: load read-only order data for the success template.
		$order_data = [];
		$order_items = [];
		$order_totals = [];
		$ga4_purchase_payload = null;

		$get_method_name = static function ($method): string {
			if (is_array($method)) {
				return trim((string)($method['name'] ?? $method['title'] ?? $method['code'] ?? ''));
			}

			if (is_string($method) && $method !== '') {
				$decoded = json_decode($method, true);

				if (is_array($decoded)) {
					return trim((string)($decoded['name'] ?? $decoded['title'] ?? $decoded['code'] ?? ''));
				}

				return trim($method);
			}

			return '';
		};

		// ST-2c.5: read the informational tariff saved inside shipping_method JSON.
		$get_shipping_display_text = static function ($method): string {
			if (is_string($method) && $method !== '') {
				$decoded = json_decode($method, true);
				$method = is_array($decoded) ? $decoded : [];
			}

			return is_array($method) ? trim((string)($method['booster_display_text'] ?? '')) : '';
		};

		$get_method_code = static function ($method): string {
			if (is_array($method)) {
				return strtolower(trim((string)($method['code'] ?? '')));
			}

			if (is_string($method) && $method !== '') {
				$decoded = json_decode($method, true);

				if (is_array($decoded)) {
					return strtolower(trim((string)($decoded['code'] ?? '')));
				}

				return strtolower(trim($method));
			}

			return '';
		};

		if ($candidate_order_ids) {
			$this->load->model('checkout/order');
			$order_info = [];

			foreach ($candidate_order_ids as $candidate_order_id) {
				$candidate_order_info = $this->model_checkout_order->getOrder((int)$candidate_order_id);

				if ($candidate_order_info && $this->canShowSuccessOrder($candidate_order_info, (int)$candidate_order_id === $order_id, $hutko_return_context)) {
					$order_id = (int)$candidate_order_id;
					$order_info = $candidate_order_info;
					$this->session->data['bs_success_order_id'] = $order_id;
					$this->session->data['bs_success_order_at'] = time();
					break;
				}
			}

			if ($order_info) {
				$currency_code = $order_info['currency_code'] ?? ($this->session->data['currency'] ?? $this->config->get('config_currency'));
				$currency_value = (float)($order_info['currency_value'] ?? 1);
				$payment_code = $get_method_code($order_info['payment_method'] ?? '');
				$payment_name = $get_method_name($order_info['payment_method'] ?? '');
				$is_hutko = $payment_code === 'hutko' || strpos($payment_code, 'hutko.') === 0;
				$is_cod = $payment_code === 'cod' || strpos($payment_code, 'cod.') === 0 || strpos($payment_code, 'pinta_nova_poshta_cod') !== false;
				// CHECKOUT-008: exact payment-code gate for IBAN requisites on success.
				$is_iban_bank_transfer = $payment_code === 'bank_transfer.bank_transfer';

				if (
					!$show_first15_offer &&
					$checkout007_shortcut_created &&
					$checkout007_shortcut_customer_id > 0 &&
					(int)($order_info['customer_id'] ?? 0) === $checkout007_shortcut_customer_id
				) {
					$this->load->model('account/customer');
					$checkout007_customer = $this->model_account_customer->getCustomer($checkout007_shortcut_customer_id);
					$checkout007_custom_field = is_array($checkout007_customer['custom_field'] ?? null) ? $checkout007_customer['custom_field'] : [];
					$show_first15_offer = !empty($checkout007_custom_field['bs_first15_pending']);
				}

				$order_data = [
					'order_id'        => (int)$order_id,
					'shipping_method' => $get_method_name($order_info['shipping_method'] ?? ''),
					'shipping_display_text' => $get_shipping_display_text($order_info['shipping_method'] ?? ''),
					'payment_method'  => $payment_name,
					'payment_code'    => $payment_code,
					'is_hutko'        => $is_hutko,
					'is_cod'          => $is_cod,
					'is_iban_bank_transfer' => $is_iban_bank_transfer,
					'show_first15_offer' => $show_first15_offer,
				];

				// TECH-015-WP1: build GA4 purchase from the authorized order, never from the cleared cart.
				$ga4_purchase_items = [];

				foreach ($this->model_checkout_order->getProducts((int)$order_id) as $product) {
					$price = (float)$product['price'] + ($this->config->get('config_tax') ? (float)$product['tax'] : 0);
					$total = (float)$product['total'] + ($this->config->get('config_tax') ? ((float)$product['tax'] * (int)$product['quantity']) : 0);

					$order_items[] = [
						'name'     => $product['name'] ?? '',
						'model'    => $product['model'] ?? '',
						'quantity' => (int)($product['quantity'] ?? 0),
						'price'    => $this->currency->format($price, $currency_code, $currency_value),
						'total'    => $this->currency->format($total, $currency_code, $currency_value),
					];

					$ga4_purchase_items[] = [
						'item_id'   => (int)($product['product_id'] ?? 0),
						'item_name' => html_entity_decode((string)($product['name'] ?? ''), ENT_QUOTES, 'UTF-8'),
						'price'     => (float)$this->currency->format((float)($product['price'] ?? 0), $currency_code, $currency_value, false),
						'quantity'  => (int)($product['quantity'] ?? 0),
					];
				}

				$ga4_total = null;
				$ga4_tax = 0.0;
				$ga4_shipping = 0.0;
				$ga4_coupon = null;

				foreach ($this->model_checkout_order->getTotals((int)$order_id) as $total) {
					$_code  = (string)($total['code'] ?? '');
					$_val   = (float)($total['value'] ?? 0);

					if ($_code === 'total') {
						$ga4_total = $_val;
					} elseif ($_code === 'tax') {
						$ga4_tax += $_val;
					} elseif (in_array($_code, ['shipping', 'pinta_nova_poshta'], true)) {
						$ga4_shipping += $_val;
					} elseif ($_code === 'coupon') {
						$coupon_title = trim((string)($total['title'] ?? ''));
						if (preg_match('/\(([^()]*)\)\s*$/u', $coupon_title, $coupon_match) === 1) {
							$ga4_coupon = trim((string)$coupon_match[1]);
						}
					}

					// R-11-FIX: skip zero-value shipping (НП placeholder before module config)
					if ($_val == 0.0 && in_array($_code, ['shipping', 'pinta_nova_poshta'], true)) {
						continue;
					}
					$order_totals[] = [
						'code'  => $_code,
						'title' => $total['title'] ?? '',
						'value' => $_val,
						'text'  => $this->currency->format($_val, $currency_code, $currency_value),
					];
				}

				if (
					!$pay003_recovery &&
					(int)($this->session->data['bs_ga4_purchase_emitted_order_id'] ?? 0) !== (int)$order_id
				) {
					$ga4_total = $ga4_total ?? (float)($order_info['total'] ?? 0);
					$ga4_ecommerce = [
						'transaction_id' => (int)$order_id,
						'currency'       => (string)$currency_code,
						// Match the vendor module: revenue excludes tax and shipping.
						'value'          => (float)$this->currency->format($ga4_total - $ga4_tax - $ga4_shipping, $currency_code, $currency_value, false),
						'tax'            => (float)$this->currency->format($ga4_tax, $currency_code, $currency_value, false),
						'shipping'       => (float)$this->currency->format($ga4_shipping, $currency_code, $currency_value, false),
						'items'          => $ga4_purchase_items,
					];

					if (is_string($ga4_coupon) && $ga4_coupon !== '') {
						$ga4_ecommerce['coupon'] = $ga4_coupon;
					}

					$ga4_json = json_encode(
						['ecommerce' => $ga4_ecommerce],
						JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PRESERVE_ZERO_FRACTION
					);

					if (is_string($ga4_json)) {
						$ga4_purchase_payload = $ga4_json;
						$this->session->data['bs_ga4_purchase_emitted_order_id'] = (int)$order_id;
					}
				}
			}
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$data['heading_title'] = $this->language->get('heading_title');
		$data['order_data'] = $order_data;
		$data['order_items'] = $order_items;
		$data['order_totals'] = $order_totals;
		$data['ga4_purchase_payload'] = $ga4_purchase_payload;

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_basket'),
			'href' => $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'))
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_checkout'),
			'href' => $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'))
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_success'),
			'href' => $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'))
		];
$customer_token = $this->session->data['customer_token'] ?? '';

$data['is_logged'] = $this->customer->isLogged();
$data['history_url'] = $data['is_logged']
	? $this->url->link('account/order', 'language=' . $this->config->get('config_language') . ($customer_token ? '&customer_token=' . $customer_token : ''))
	: '';

if ($this->customer->isLogged()) {
	$data['text_message'] = sprintf(
		$this->language->get('text_customer'),
		$this->url->link('account/account', 'language=' . $this->config->get('config_language') . ($customer_token ? '&customer_token=' . $customer_token : '')),
		$this->url->link('account/order', 'language=' . $this->config->get('config_language') . ($customer_token ? '&customer_token=' . $customer_token : '')),
		$this->url->link('account/download', 'language=' . $this->config->get('config_language') . ($customer_token ? '&customer_token=' . $customer_token : '')),
		$this->url->link('information/contact', 'language=' . $this->config->get('config_language'))
	);
} else {
	$data['text_message'] = sprintf(
		$this->language->get('text_guest'),
		$this->url->link('information/contact', 'language=' . $this->config->get('config_language'))
	);

}

		

		$data['continue'] = $this->url->link('common/home', 'language=' . $this->config->get('config_language'));

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('checkout/success', $data));
	}

	private function getSessionSuccessOrderId(): int {
		$order_id = (int)($this->session->data['bs_success_order_id'] ?? 0);
		$created_at = (int)($this->session->data['bs_success_order_at'] ?? 0);

		if ($order_id <= 0 || $created_at <= 0) {
			return 0;
		}

		$age = time() - $created_at;

		if ($age < 0 || $age > 1800) {
			return 0;
		}

		return $order_id;
	}

	private function getHutkoReturnContext(): array {
		if (empty($this->request->cookie['bs_hutko_return'])) {
			return [];
		}

		$parts = explode('.', (string)$this->request->cookie['bs_hutko_return'], 2);

		if (count($parts) !== 2) {
			return [];
		}

		[$encoded, $signature] = $parts;
		$expected = hash_hmac('sha256', $encoded, $this->getHutkoReturnCookieSecret());

		if (!hash_equals($expected, $signature)) {
			return [];
		}

		$decoded = base64_decode($encoded, true);

		if ($decoded === false) {
			return [];
		}

		$data = json_decode($decoded, true);

		if (!is_array($data) || empty($data['order_id']) || empty($data['expires']) || (int)$data['expires'] < time()) {
			return [];
		}

		return $data;
	}

	private function canShowSuccessOrder(array $order_info, bool $from_order_session, array $hutko_return_context): bool {
		$order_id = (int)($order_info['order_id'] ?? 0);

		if ($order_id <= 0) {
			return false;
		}

		if ($from_order_session) {
			return true;
		}

		if ($this->customer->isLogged()) {
			return (int)($order_info['customer_id'] ?? 0) === (int)$this->customer->getId();
		}

		$session_success_order_id = $this->getSessionSuccessOrderId();

		if ($session_success_order_id === $order_id && (int)($order_info['customer_id'] ?? 0) === 0) {
			return true;
		}

		if (!empty($hutko_return_context['order_id']) && (int)$hutko_return_context['order_id'] === $order_id) {
			$context_customer_id = (int)($hutko_return_context['customer_id'] ?? 0);
			$order_customer_id = (int)($order_info['customer_id'] ?? 0);

			return $context_customer_id === $order_customer_id || ($context_customer_id === 0 && $order_customer_id === 0);
		}

		return false;
	}

	private function getHutkoReturnCookieSecret(): string {
		return (string)($this->config->get('payment_hutko_secret_key') ?: $this->config->get('config_encryption') ?: $this->config->get('config_hash') ?: DIR_STORAGE);
	}
}
