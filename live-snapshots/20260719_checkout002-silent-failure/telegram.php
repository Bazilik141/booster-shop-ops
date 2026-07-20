<?php
namespace Opencart\Catalog\Controller\Extension\TelegramNotify\Event;

class Telegram extends \Opencart\System\Engine\Controller
{
	public function sendNotification(string &$route, array &$args, mixed &$output): void
	{
		// CHECKOUT-002_ASYNC_ORDER_SIDE_EFFECTS_20260719
		// order.php calls sendOrderNotification directly for every addHistory event.
		// Keep this registered after-event handler inert to avoid a duplicate message.
		return;
	}

	public function sendOrderNotification(int $order_id, int $new_status_id): void
	{
		if (!$this->config->get('module_telegram_notify_status')) {
			return;
		}

		if (!$order_id || $new_status_id <= 0) {
			return;
		}

		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "order_history WHERE order_id = '" . (int)$order_id . "'");

		if ((int)$query->row['total'] !== 1) {
			return;
		}

		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($order_id);

		if (!$order_info) {
			return;
		}

		$template = (string)$this->config->get('module_telegram_notify_template');

		$products_text = '';
		$products = $this->model_checkout_order->getProducts($order_id);

		foreach ($products as $product) {
			$products_text .= '• ' . $product['name'] . ' (x' . $product['quantity'] . ')' . "\n";
		}

		$replace = [
			'{order_id}'        => $order_id,
			'{firstname}'       => $order_info['firstname'] ?? '',
			'{lastname}'        => $order_info['lastname'] ?? '',
			'{email}'           => $order_info['email'] ?? '',
			'{telephone}'       => $order_info['telephone'] ?? '',
			'{payment_method}'  => is_array($order_info['payment_method'] ?? null) ? (($order_info['payment_method']['name'] ?? $order_info['payment_method']['title'] ?? '')) : ($order_info['payment_method'] ?? ''),
'{shipping_method}' => is_array($order_info['shipping_method'] ?? null) ? (($order_info['shipping_method']['name'] ?? $order_info['shipping_method']['title'] ?? '')) : ($order_info['shipping_method'] ?? ''),

			'{total}'           => $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value']),
			'{products}'        => trim($products_text),
			'{comment}'         => !empty($order_info['comment']) ? $order_info['comment'] : '—'
		];

		$message = str_replace(array_keys($replace), array_values($replace), $template);

		$this->sendMessage($message);
	}

		// CHECKOUT-002_ASYNC_ORDER_SIDE_EFFECTS_20260719
	private function sendMessage(string $message): void
	{
		$status = $this->config->get('module_telegram_notify_status');
		$token = $this->config->get('module_telegram_notify_token');
		$chat_id = $this->config->get('module_telegram_notify_chat_id');

		if (!$status || !$token || !$chat_id) {
			return;
		}

		try {
			require_once(DIR_SYSTEM . 'library/booster_async_queue.php');
			\Opencart\System\Library\BoosterAsyncHttpQueue::enqueue([
				'id' => 'telegram-' . (string)(int)microtime(true) . '-' . bin2hex(random_bytes(5)),
				'url' => "https://api.telegram.org/bot{$token}/sendMessage",
				'headers' => ['Content-Type: application/x-www-form-urlencoded'],
				'body' => http_build_query(['chat_id' => $chat_id, 'text' => $message], '', '&', PHP_QUERY_RFC3986),
				'expect' => 'json_ok',
				'connect_timeout' => 3,
				'timeout' => 10,
				'success_marker' => '',
				'created_at' => gmdate('c'),
			]);
		} catch (\Throwable $e) {
			$this->log->write('Telegram notification queue failed: ' . $e->getMessage());
		}
	}
}
