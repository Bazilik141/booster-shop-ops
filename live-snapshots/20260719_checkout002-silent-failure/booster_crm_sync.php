<?php
namespace Opencart\System\Library;

class BoosterCrmSync {
	private const WEB_APP_URL = 'https://script.google.com/macros/s/AKfycbz2WIFlW7A-ta7HtewK-0wsklekB-HDIgCx3CF1JGfmPaRvs2UgI0qktcfhvKzPYDbX-A/exec';
	private const SECRET_TOKEN = '[REDACTED-see live server]';
	private const TIMEOUT_SECONDS = 15;
	private const CONNECT_TIMEOUT_MS = 3000;
	private const TIMEOUT_MS = 15000;
	private const SKIP_ORDER_IDS = [107, 108, 112, 113, 114, 115, 116, 117, 118, 119, 120, 121, 122, 123];
	private const SKIP_ORDER_KEYS = ['OC-FOP-0107', 'OC-FOP-0108', 'OC-FOP-0112', 'OC-FOP-0113', 'OC-FOP-0114', 'OC-FOP-0115', 'OC-FOP-0116', 'OC-FOP-0117', 'OC-FOP-0118', 'OC-FOP-0119', 'OC-FOP-0120', 'OC-FOP-0121', 'OC-FOP-0122', 'OC-FOP-0123'];
	private const SKIP_TEST_PHONES = ['991119279', '991111111'];
	private const SKIP_TEST_EMAILS = ['evgenij.leusenko@gmail.com', '14bez23232323likiy14@gmail.com', '14bez232323ikiy14@gmail.com', '14bezli232323kiy14@gmail.com'];
	private const SKIP_TEST_CUSTOMER_NAMES = ['євгеній леусенко', 'евгений леусенко', 'evgenii leusenko', 'yevhenii leusenko', 'yevgeniy leusenko'];
	private const SKIP_TEST_PRODUCT_MARKERS = ['test sku', 'test-sku', 'testsku', 'тестова позиція'];
	private const SENT_DIR_NAME = 'booster_crm_sent';

	private $registry;
	private $db;
	private $log;

	public function __construct($registry) {
		$this->registry = $registry;
		$this->db = $registry->get('db');
		$this->log = $registry->get('log');
	}

	public function syncOrder(int $order_id, string $event): void {
		$order_key = $this->getOrderKey($order_id);

		if ($this->hasSentMarker($order_key)) {
			return;
		}

		$order = $this->getOrder($order_id);

		if (!$order) {
			return;
		}

		if ($this->shouldSkipOrder($order_id, $order)) {
			$this->removeQueuedPayloadsForOrder($order_id);
			return;
		}

		$payload = $this->buildPayload($order_id, $event, $order);
		$this->removeQueuedPayloadsForOrder($order_id);

		try {
			$this->queueAsyncPayload($payload, $order_key);
			return;
		} catch (\Throwable $e) {
			if ($this->log) {
				$this->log->write('Booster CRM sync queued ' . $order_key . ': ' . $e->getMessage());
			}
		}

		$this->queuePayload($payload);
	}

	private function isConfigured(): bool {
		return self::WEB_APP_URL !== ''
			&& self::SECRET_TOKEN !== ''
			&& self::WEB_APP_URL !== 'PASTE_GOOGLE_APPS_SCRIPT_WEB_APP_URL_HERE'
			&& self::SECRET_TOKEN !== 'CHANGE_ME';
	}

	private function shouldSkipOrder(int $order_id, array $order): bool {
		$order_key = $this->getOrderKey($order_id);

		if (in_array($order_id, self::SKIP_ORDER_IDS, true) || in_array($order_key, self::SKIP_ORDER_KEYS, true)) {
			return true;
		}

		$phone = $this->normalizePhone($order['telephone'] ?? '');

		if ($phone !== '' && in_array($phone, self::SKIP_TEST_PHONES, true)) {
			return true;
		}

		$email = $this->normalizeText($order['email'] ?? '');

		if ($email !== '' && in_array($email, self::SKIP_TEST_EMAILS, true)) {
			return true;
		}

		$name = $this->normalizeText(trim((string)($order['firstname'] ?? '') . ' ' . (string)($order['lastname'] ?? '')));

		if ($name !== '' && in_array($name, self::SKIP_TEST_CUSTOMER_NAMES, true)) {
			return true;
		}

		return $this->orderHasSkippedProduct($order_id);
	}

	private function orderHasSkippedProduct(int $order_id): bool {
		$query = $this->db->query("SELECT `name`, `model` FROM `" . DB_PREFIX . "order_product` WHERE `order_id` = '" . (int)$order_id . "'");

		foreach (($query->rows ?? []) as $row) {
			$text = $this->normalizeText((string)($row['model'] ?? '') . ' ' . (string)($row['name'] ?? ''));

			foreach (self::SKIP_TEST_PRODUCT_MARKERS as $marker) {
				$needle = $this->normalizeText($marker);

				if ($needle !== '' && strpos($text, $needle) !== false) {
					return true;
				}
			}
		}

		return false;
	}

	private function getOrderKey(int $order_id): string {
		return 'OC-FOP-' . str_pad((string)$order_id, 4, '0', STR_PAD_LEFT);
	}

	private function markSent(string $order_key): void {
		$file = $this->getSentMarkerFile($order_key);

		if ($file === '') {
			return;
		}

		$dir = dirname($file);

		if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
			return;
		}

		@file_put_contents($file, date('c'), LOCK_EX);
	}
	private function hasSentMarker(string $order_key): bool {
		$file = $this->getSentMarkerFile($order_key);

		return $file !== '' && is_file($file);
	}

	private function getSentMarkerFile(string $order_key): string {
		$safe_key = preg_replace('/[^a-z0-9_\-]/i', '_', $order_key);

		if ($safe_key === null || $safe_key === '') {
			return '';
		}

		return rtrim(DIR_STORAGE, '/\\') . DIRECTORY_SEPARATOR . self::SENT_DIR_NAME . DIRECTORY_SEPARATOR . $safe_key . '.sent';
	}

	private function normalizePhone($value): string {
		$digits = preg_replace('/\D+/', '', (string)$value);

		if ($digits === null) {
			$digits = '';
		}

		return strlen($digits) > 9 ? substr($digits, -9) : $digits;
	}

	private function normalizeText($value): string {
		$text = preg_replace('/\s+/', ' ', trim((string)$value));

		if ($text === null) {
			$text = '';
		}

		return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
	}

	private function buildPayload(int $order_id, string $event, array $order): array {
		$payment_method = $this->decodeJsonArray($order['payment_method'] ?? '');
		$shipping_method = $this->decodeJsonArray($order['shipping_method'] ?? '');

		return [
			'token' => self::SECRET_TOKEN,
			'event' => $event,
			'order_id' => $order_id,
			'order_key' => 'OC-FOP-' . str_pad((string)$order_id, 4, '0', STR_PAD_LEFT),
			'date_added' => $order['date_added'] ?? '',
			'date_modified' => $order['date_modified'] ?? '',
			'order_status_id' => (int)($order['order_status_id'] ?? 0),
			'order_status' => $order['order_status'] ?? '',
			'customer_name' => trim(($order['firstname'] ?? '') . ' ' . ($order['lastname'] ?? '')),
			'firstname' => $order['firstname'] ?? '',
			'lastname' => $order['lastname'] ?? '',
			'email' => $order['email'] ?? '',
			'telephone' => $order['telephone'] ?? '',
			'comment' => $order['comment'] ?? '',
			'total' => (float)($order['total'] ?? 0),
			'tracking' => $order['tracking'] ?? '',
			'payment_method_name' => $payment_method['name'] ?? '',
			'payment_method_code' => $payment_method['code'] ?? '',
			'shipping_method_name' => $shipping_method['name'] ?? '',
			'shipping_method_code' => $shipping_method['code'] ?? '',
			'products' => $this->getProducts($order_id),
			'totals' => $this->getTotals($order_id),
			'histories' => $this->getHistories($order_id),
		];
	}

	private function getOrder(int $order_id): array {
		$query = $this->db->query("SELECT *, (SELECT `os`.`name` FROM `" . DB_PREFIX . "order_status` `os` WHERE `os`.`order_status_id` = `o`.`order_status_id` AND `os`.`language_id` = `o`.`language_id`) AS `order_status` FROM `" . DB_PREFIX . "order` `o` WHERE `o`.`order_id` = '" . (int)$order_id . "'");

		return $query->row ?? [];
	}

	private function normalizeCrmSku(string $sku): string {
		$sku = trim($sku);
		$aliases = [
			'PKM-KR-HWA-BST' => 'PKM-KR-HWAK-BST',
			'PKM-KR-HWA-BBX' => 'PKM-KR-HWAK-BBX',
		];

		return $aliases[$sku] ?? $sku;
	}

	private function getProducts(int $order_id): array {
		$query = $this->db->query("SELECT `order_product_id`, `product_id`, `name`, `model`, `quantity`, `price`, `total`, `tax` FROM `" . DB_PREFIX . "order_product` WHERE `order_id` = '" . (int)$order_id . "' ORDER BY `order_product_id` ASC");
		$products = [];

		foreach (($query->rows ?? []) as $row) {
			$sku = $this->normalizeCrmSku((string)$row['model']);

			$products[] = [
				'order_product_id' => (int)$row['order_product_id'],
				'product_id' => (int)$row['product_id'],
				'name' => $row['name'],
				'sku' => $sku,
				'model' => $sku,
				'quantity' => (int)$row['quantity'],
				'price' => (float)$row['price'],
				'total' => (float)$row['total'],
				'tax' => (float)$row['tax'],
			];
		}

		return $products;
	}

	private function getTotals(int $order_id): array {
		$query = $this->db->query("SELECT `extension`, `code`, `title`, `value`, `sort_order` FROM `" . DB_PREFIX . "order_total` WHERE `order_id` = '" . (int)$order_id . "' ORDER BY `sort_order` ASC");
		$totals = [];

		foreach (($query->rows ?? []) as $row) {
			$totals[] = [
				'extension' => $row['extension'],
				'code' => $row['code'],
				'title' => $row['title'],
				'value' => (float)$row['value'],
				'sort_order' => (int)$row['sort_order'],
			];
		}

		return $totals;
	}

	private function getHistories(int $order_id): array {
		$query = $this->db->query("SELECT `oh`.`date_added`, `oh`.`order_status_id`, `oh`.`notify`, `oh`.`comment`, `os`.`name` AS `order_status` FROM `" . DB_PREFIX . "order_history` `oh` LEFT JOIN `" . DB_PREFIX . "order` `o` ON (`o`.`order_id` = `oh`.`order_id`) LEFT JOIN `" . DB_PREFIX . "order_status` `os` ON (`os`.`order_status_id` = `oh`.`order_status_id` AND `os`.`language_id` = `o`.`language_id`) WHERE `oh`.`order_id` = '" . (int)$order_id . "' ORDER BY `oh`.`date_added` DESC LIMIT 10");
		$histories = [];

		foreach (($query->rows ?? []) as $row) {
			$histories[] = [
				'date_added' => $row['date_added'],
				'order_status_id' => (int)$row['order_status_id'],
				'order_status' => $row['order_status'] ?? '',
				'notify' => (int)$row['notify'],
				'comment' => $row['comment'] ?? '',
			];
		}

		return $histories;
	}

	private function decodeJsonArray($value): array {
		if (is_array($value)) {
			return $value;
		}

		if (!is_string($value) || trim($value) === '') {
			return [];
		}

		$decoded = json_decode($value, true);

		return is_array($decoded) ? $decoded : [];
	}

	private function postJson(array $payload): void {
		$json = json_encode($payload, JSON_UNESCAPED_UNICODE);

		if ($json === false) {
			throw new \RuntimeException('Could not encode CRM payload.');
		}

		if (function_exists('curl_init')) {
			$this->postJsonCurl($json);
			return;
		}

		$this->postJsonStream($json);
	}

	// CHECKOUT-002_ASYNC_ORDER_SIDE_EFFECTS_20260719
	private function queueAsyncPayload(array $payload, string $order_key): void {
		$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			throw new \RuntimeException('Could not encode Booster CRM async payload.');
		}

		require_once(DIR_SYSTEM . 'library/booster_async_queue.php');
		$safe_key = preg_replace('/[^a-z0-9_\-]/i', '_', $order_key);
		if ($safe_key === null || $safe_key === '') {
			throw new \RuntimeException('Could not create Booster CRM async job id.');
		}

		\Opencart\System\Library\BoosterAsyncHttpQueue::enqueue([
			'id' => 'booster-crm-' . $safe_key,
			'url' => self::WEB_APP_URL,
			'headers' => ['Content-Type: application/json'],
			'body' => $json,
			'expect' => 'json_ok',
			'connect_timeout' => 3,
			'timeout' => self::TIMEOUT_SECONDS,
			'success_marker' => $this->getSentMarkerFile($order_key),
			'created_at' => gmdate('c'),
		]);
	}
	private function queuePayload(array $payload): void {
		$json = json_encode($payload, JSON_UNESCAPED_UNICODE);

		if ($json === false) {
			throw new \RuntimeException('Could not encode CRM payload.');
		}

		$queue_dir = rtrim(DIR_STORAGE, '/\\') . DIRECTORY_SEPARATOR . 'booster_crm_queue';

		if (!is_dir($queue_dir) && !mkdir($queue_dir, 0755, true) && !is_dir($queue_dir)) {
			throw new \RuntimeException('Could not create CRM queue directory.');
		}

		$suffix = function_exists('random_bytes') ? bin2hex(random_bytes(4)) : uniqid('', true);
		$base = date('YmdHis') . '_' . (int)($payload['order_id'] ?? 0) . '_' . preg_replace('/[^a-z0-9_\\-]/i', '_', (string)($payload['event'] ?? 'order')) . '_' . $suffix;
		$tmp = $queue_dir . DIRECTORY_SEPARATOR . $base . '.tmp';
		$file = $queue_dir . DIRECTORY_SEPARATOR . $base . '.json';

		if (file_put_contents($tmp, $json, LOCK_EX) === false) {
			throw new \RuntimeException('Could not write CRM queue file.');
		}

		if (!rename($tmp, $file)) {
			@unlink($tmp);
			throw new \RuntimeException('Could not finalize CRM queue file.');
		}
	}

	private function removeQueuedPayloadsForOrder(int $order_id): void {
		if ($order_id <= 0) {
			return;
		}

		$queue_dir = rtrim(DIR_STORAGE, '/\\') . DIRECTORY_SEPARATOR . 'booster_crm_queue';

		if (!is_dir($queue_dir)) {
			return;
		}

		foreach (glob($queue_dir . DIRECTORY_SEPARATOR . '*_' . $order_id . '_*.json') ?: [] as $file) {
			@unlink($file);
		}

		foreach (glob($queue_dir . DIRECTORY_SEPARATOR . '*_' . $order_id . '_*.tmp') ?: [] as $file) {
			@unlink($file);
		}
	}

	private function postJsonCurl(string $json): void {
		$ch = curl_init(self::WEB_APP_URL);

		$options = [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
			CURLOPT_POSTFIELDS => $json,
			CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
			CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
			CURLOPT_CONNECTTIMEOUT_MS => self::CONNECT_TIMEOUT_MS,
			CURLOPT_TIMEOUT_MS => self::TIMEOUT_MS,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_NOSIGNAL => true,
		];


		curl_setopt_array($ch, $options);

		$response = curl_exec($ch);
		$error = curl_error($ch);
		$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		if ($response === false || $status < 200 || $status >= 300) {
			throw new \RuntimeException('HTTP ' . $status . ' ' . $error . ' ' . substr((string)$response, 0, 300));
		}

		$this->assertSuccessfulCrmResponse((string)$response);
	}

	private function postJsonStream(string $json): void {
		$context = stream_context_create([
			'http' => [
				'method' => 'POST',
				'header' => "Content-Type: application/json\r\n",
				'content' => $json,
				'timeout' => self::TIMEOUT_SECONDS,
				'ignore_errors' => true,
				'follow_location' => 1,
				'max_redirects' => 5,
			],
		]);

		$response = @file_get_contents(self::WEB_APP_URL, false, $context);

		if ($response === false) {
			throw new \RuntimeException('HTTP request failed.');
		}

		$this->assertSuccessfulCrmResponse((string)$response);
	}

	private function assertSuccessfulCrmResponse(string $response): void {
		$trimmed = trim($response);

		if ($trimmed === '') {
			throw new \RuntimeException('CRM response is empty.');
		}

		$decoded = json_decode($trimmed, true);

		if (!is_array($decoded)) {
			throw new \RuntimeException('CRM response is not JSON: ' . substr($trimmed, 0, 300));
		}

		if (!array_key_exists('ok', $decoded)) {
			throw new \RuntimeException('CRM response missing ok flag.');
		}

		if ($decoded['ok'] !== true) {
			$error = isset($decoded['error']) ? (string)$decoded['error'] : 'unknown_error';
			throw new \RuntimeException('CRM response ok=false: ' . substr($error, 0, 300));
		}
	}
}


// NCRM-10_ORDER_SYNC_HOOK_20260718
// Separate from BoosterCrmSync: it never changes the Apps Script delivery path.
class NcrmOrderSync {
    private $db;

    public function __construct($registry) {
        $this->db = $registry->get('db');
    }

    public function syncOrder(int $orderId, string $event): void {
        if ($event !== 'order_add') {
            return;
        }

        $config = $this->loadConfig();
        if ($config === []) {
            return;
        }

        try {
            $order = $this->loadOrder($orderId);
            if ($order === [] || $this->isTestOrder($order, [])) {
                return;
            }

            $products = $this->loadProducts($orderId);
            if ($products === [] || $this->isTestOrder($order, $products)) {
                return;
            }

            $payload = $this->buildPayload($order, $products, $event);
            $this->queueAsyncPayload($config['url'], $config['secret'], $payload, $orderId);
        } catch (\Throwable $e) {
            error_log('NCRM-10 order sync failed order_id=' . (int) $orderId);
        }
    }

    private function loadConfig(): array {
        if (!defined('DIR_STORAGE')) {
            return [];
        }

        $path = rtrim((string) DIR_STORAGE, '/\\') . '/config/ncrm_order_sync.php';
        if (!is_file($path)) {
            return [];
        }

        $config = require $path;
        if (!is_array($config)) {
            return [];
        }

        $url = trim((string) ($config['url'] ?? ''));
        $secret = trim((string) ($config['secret'] ?? ''));
        if ($url === '' || $secret === '' || strpos($url, 'https://') !== 0) {
            return [];
        }

        return ['url' => $url, 'secret' => $secret];
    }

    private function loadOrder(int $orderId): array {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order WHERE order_id = '" . (int) $orderId . "' LIMIT 1");
        return !empty($query->row) && is_array($query->row) ? $query->row : [];
    }

    private function loadProducts(int $orderId): array {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int) $orderId . "' ORDER BY order_product_id ASC");
        return !empty($query->rows) && is_array($query->rows) ? $query->rows : [];
    }

    private function loadTotals(int $orderId): array {
        $query = $this->db->query("SELECT code, value FROM " . DB_PREFIX . "order_total WHERE order_id = '" . (int) $orderId . "'");
        $totals = [];
        foreach (($query->rows ?? []) as $row) {
            $totals[(string) $row['code']] = (float) $row['value'];
        }
        return $totals;
    }

    private function buildPayload(array $order, array $products, string $event): array {
        $items = [];
        foreach ($products as $product) {
            $sku = trim((string) ($product['sku'] ?? ''));
            if ($sku === '') {
                $sku = trim((string) ($product['model'] ?? ''));
            }
            if ($sku === 'PKM-KR-HWA-BST') {
                $sku = 'PKM-KR-HWAK-BST';
            }
            if ($sku === 'PKM-KR-HWA-BBX') {
                $sku = 'PKM-KR-HWAK-BBX';
            }
            if ($sku === '') {
                continue;
            }
            $items[] = [
                'sku' => $sku,
                'model' => (string) ($product['model'] ?? ''),
                'name' => (string) ($product['name'] ?? ''),
                'quantity' => (int) ($product['quantity'] ?? 0),
                'unit_price' => (float) ($product['price'] ?? 0),
                'line_total' => (float) ($product['total'] ?? 0),
            ];
        }

        $totals = $this->loadTotals((int) $order['order_id']);
        $discount = 0.0;
        foreach ($totals as $code => $value) {
            if ($code === 'coupon' || $code === 'voucher' || $code === 'reward' || $code === 'discount') {
                $discount += abs((float) $value);
            }
        }

        return [
            'event' => $event,
            'order_id' => (int) $order['order_id'],
            'order_no' => 'OC-FOP-' . str_pad((string) $order['order_id'], 4, '0', STR_PAD_LEFT),
            'order_key' => 'OC-FOP-' . str_pad((string) $order['order_id'], 4, '0', STR_PAD_LEFT),
            'date_added' => (string) ($order['date_added'] ?? ''),
            'customer_name' => trim((string) ($order['firstname'] ?? '') . ' ' . (string) ($order['lastname'] ?? '')),
            'firstname' => (string) ($order['firstname'] ?? ''),
            'lastname' => (string) ($order['lastname'] ?? ''),
            'telephone' => (string) ($order['telephone'] ?? ''),
            'email' => (string) ($order['email'] ?? ''),
            'comment' => (string) ($order['comment'] ?? ''),
            'payment_method_name' => (string) ($order['payment_method'] ?? ''),
            'payment_method_code' => (string) ($order['payment_code'] ?? ''),
            'shipping_method_name' => (string) ($order['shipping_method'] ?? ''),
            'shipping_method_code' => (string) ($order['shipping_code'] ?? ''),
            'subtotal' => (float) ($totals['sub_total'] ?? 0),
            'discount_total' => $discount,
            'delivery_total' => (float) ($totals['shipping'] ?? 0),
            'total' => (float) ($order['total'] ?? 0),
            'products' => $items,
        ];
    }

    private function isTestOrder(array $order, array $products): bool {
        $phone = preg_replace('/\D+/', '', (string) ($order['telephone'] ?? ''));
        if (substr($phone, -10) === '0991119279') {
            return true;
        }

        $haystack = strtolower(
            (string) ($order['lastname'] ?? '') . ' ' .
            (string) ($order['firstname'] ?? '') . ' ' .
            (string) ($order['comment'] ?? '')
        );
        if (strpos($haystack, 'leusenko') !== false || strpos($haystack, 'тест') !== false || strpos($haystack, 'test') !== false) {
            return true;
        }

        foreach ($products as $product) {
            $productText = strtolower(
                (string) ($product['name'] ?? '') . ' ' .
                (string) ($product['model'] ?? '') . ' ' .
                (string) ($product['sku'] ?? '')
            );
            if (strpos($productText, 'тест') !== false || strpos($productText, 'test') !== false) {
                return true;
            }
        }

        return false;
    }

    // CHECKOUT-002_ASYNC_ORDER_SIDE_EFFECTS_20260719
    private function queueAsyncPayload(string $url, string $secret, array $payload, int $orderId): void {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Could not encode NCRM async payload.');
        }

        $orderKey = trim((string)($payload['order_key'] ?? ''));
        $safeKey = preg_replace('/[^a-z0-9_\-]/i', '_', $orderKey !== '' ? $orderKey : ('order-' . $orderId));
        if ($safeKey === null || $safeKey === '') {
            throw new \RuntimeException('Could not create NCRM async job id.');
        }

        require_once(DIR_SYSTEM . 'library/booster_async_queue.php');
        \Opencart\System\Library\BoosterAsyncHttpQueue::enqueue([
            'id' => 'ncrm-' . $safeKey,
            'url' => $url,
            'headers' => [
                'Content-Type: application/json',
                'X-Order-Sync-Secret: ' . $secret,
            ],
            'body' => $json,
            'expect' => 'http_2xx',
            'connect_timeout' => 3,
            'timeout' => 8,
            'success_marker' => '',
            'created_at' => gmdate('c'),
        ]);
    }
    private function postJson(string $url, string $secret, array $payload): bool {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-Order-Sync-Secret: ' . $secret,
                ],
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_RETURNTRANSFER => true,
            ]);
            curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $status >= 200 && $status < 300;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nX-Order-Sync-Secret: " . $secret . "\r\n",
                'content' => $json,
                'timeout' => 8,
                // NCRM-10_ROUND3_SYNC_TIMEOUT_20260719
                'ignore_errors' => true,
            ],
        ]);
        $result = @file_get_contents($url, false, $context);
        if ($result === false || empty($http_response_header[0])) {
            return false;
        }
        return (bool) preg_match('/\s2\d\d\s/', (string) $http_response_header[0]);
    }
}
