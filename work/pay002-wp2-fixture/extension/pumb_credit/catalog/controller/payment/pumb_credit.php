<?php
namespace Opencart\Catalog\Controller\Extension\PumbCredit\Payment;
class PumbCredit extends \Opencart\System\Engine\Controller {
    private const API_CREATE = '/sf-credits';
    public function index(): string { return ''; }
    public function confirm(): void {
        $this->load->language('extension/pumb_credit/payment/pumb_credit');
        $orderId = (int)($this->session->data['order_id'] ?? 0);
        if (!$this->config->get('payment_pumb_credit_status') || !$orderId) { $this->reply(['error' => $this->language->get('error_unavailable')]); return; }
        $order = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE `order_id`='" . $orderId . "' LIMIT 1")->row ?? [];
        $minimum = (float)($this->config->get('payment_pumb_credit_min_total') ?: 500);
        $maximum = (float)$this->config->get('payment_pumb_credit_max_total');
        if (!$order || (float)$order['total'] < $minimum || ($maximum > 0 && (float)$order['total'] > $maximum)) { $this->reply(['error' => $this->language->get('error_minimum')]); return; }
        $term = $this->requestedTerm();
        if ($term === null) { $this->reply(['error' => $this->language->get('error_term')]); return; }
        $isTest = $this->isTestMode() ? 1 : 0;

        $existing = $this->transactionByOrder($orderId, $isTest);
        if ($existing) {
            $this->replyExistingCreate($existing);
            return;
        }

        try {
            $reservation = $this->reserveCreate($orderId, $isTest);
        } catch (\Throwable $exception) {
            $this->reply(['error' => 'PUMB application reservation failed.']);
            return;
        }
        if (!$reservation['owner']) {
            $this->replyExistingCreate($reservation['transaction']);
            return;
        }

        $payload = $this->createPayload($order, $term);
        $response = $this->api('POST', self::API_CREATE, $payload);
        $capId = (string)($response['body']['id'] ?? '');
        if (($response['http'] ?? 0) !== 201 || $capId === '') {
            $this->upsertTransaction(
                $orderId,
                'OC-' . $orderId,
                'PENDING-OC-' . $orderId,
                'CREATE_FAILED',
                $isTest,
                ['create' => ['request' => $payload, 'response' => $response]],
                null,
                $term
            );
            $this->reply(['error' => 'PUMB application was not created. Manual review is required.', 'http' => $response['http'] ?? 0]);
            return;
        }

        $this->upsertTransaction($orderId, 'OC-' . $orderId, $capId, 'WAITING_CLIENT', $isTest, ['create' => ['request' => $payload, 'response' => $response]], null, $term);
        $this->applyOrderStatus($orderId, 'WAITING_CLIENT', false);
        $this->reply(['cap_id' => $capId, 'state' => 'WAITING_CLIENT']);
    }
    public function callback(): void { $this->handleCallback(false); }
    public function callbackTest(): void { $this->handleCallback(true); }
    public function poll(): void {
        $orderId = (int)($this->session->data['order_id'] ?? 0);
        $isTest = $this->isTestMode() ? 1 : 0;
        $tx = $this->transactionByOrder($orderId, $isTest);
        if (!$orderId || !$tx) { $this->reply(['error' => 'transaction not found']); return; }
        $response = $this->api('GET', self::API_CREATE . '/' . rawurlencode((string)$tx['cap_id']));
        if (($response['http'] ?? 0) === 200 && isset($response['body']['state'])) {
            $letter = isset($response['body']['guarantee_letter']) ? $response['body']['guarantee_letter'] : null;
            $this->upsertTransaction($orderId, (string)$tx['store_order_id'], (string)$tx['cap_id'], (string)$response['body']['state'], $isTest, ['poll' => $response], $letter);
            if (!$isTest) $this->applyOrderStatus($orderId, (string)$response['body']['state'], false);
        }
        $this->reply(['http' => $response['http'] ?? 0, 'state' => $response['body']['state'] ?? null]);
    }
    /** @param array<string,mixed> $transaction */
    private function replyExistingCreate(array $transaction): void {
        $state = (string)($transaction['state'] ?? '');
        $capId = (string)($transaction['cap_id'] ?? '');

        if ($state === 'CREATING') {
            $this->reply(['success' => true, 'idempotent' => true, 'pending' => true, 'state' => 'CREATING']);
            return;
        }
        if ($state === 'CREATE_FAILED') {
            $this->reply(['error' => 'PUMB application creation previously failed. Manual review is required.', 'idempotent' => true, 'state' => 'CREATE_FAILED']);
            return;
        }
        if (strpos($capId, 'PENDING-OC-') === 0) {
            $this->reply(['error' => 'PUMB application is pending manual review.', 'idempotent' => true, 'state' => $state]);
            return;
        }

        $this->reply(['success' => true, 'idempotent' => true, 'cap_id' => $capId, 'state' => $state]);
    }

    /** @return array{owner:bool,transaction:array<string,mixed>} */
    private function reserveCreate(int $orderId, int $isTest): array {
        $token = bin2hex(random_bytes(16));
        $payload = json_encode([
            'create_reservation' => [
                'token' => $token,
                'created_at' => gmdate('c'),
            ],
        ], JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            throw new \RuntimeException('Unable to encode PUMB creation reservation.');
        }

        $storeOrderId = 'OC-' . $orderId;
        $pendingCapId = 'PENDING-OC-' . $orderId;
        $this->db->query("INSERT INTO `" . DB_PREFIX . "pumb_credit_transaction` SET order_id = '" . (int)$orderId . "', store_order_id = '" . $this->db->escape($storeOrderId) . "', cap_id = '" . $this->db->escape($pendingCapId) . "', state = 'CREATING', is_test = '" . (int)$isTest . "', payload = '" . $this->db->escape($payload) . "', date_added = NOW(), date_modified = NOW() ON DUPLICATE KEY UPDATE pumb_credit_transaction_id = pumb_credit_transaction_id");

        $transaction = $this->transactionByOrder($orderId, $isTest);
        if (!$transaction) {
            throw new \RuntimeException('PUMB creation reservation was not persisted.');
        }
        $storedPayload = json_decode((string)($transaction['payload'] ?? ''), true);
        $storedToken = is_array($storedPayload) ? (string)($storedPayload['create_reservation']['token'] ?? '') : '';

        return [
            'owner' => $storedToken !== '' && hash_equals($token, $storedToken),
            'transaction' => $transaction,
        ];
    }
    private function handleCallback(bool $isTest): void {
        if (!$this->validBasicAuth($isTest)) { $this->response->addHeader('HTTP/1.1 401 Unauthorized'); $this->reply(['success' => false, 'error' => 'unauthorized']); return; }
        if (!$this->allowedIp($isTest)) { $this->response->addHeader('HTTP/1.1 403 Forbidden'); $this->reply(['success' => false, 'error' => 'ip_not_allowed']); return; }
        $raw = (string)file_get_contents('php://input');
        $data = json_decode($raw, true);
        $capId = is_array($data) ? trim((string)($data['cap_id'] ?? '')) : '';
        $state = is_array($data) ? trim((string)($data['state'] ?? '')) : '';
        if ($capId === '' || $state === '') { $this->response->addHeader('HTTP/1.1 400 Bad Request'); $this->reply(['success' => false, 'error' => 'invalid_callback']); return; }
        $tx = $this->transactionByCap($capId, $isTest ? 1 : 0);
        $orderId = (int)($tx['order_id'] ?? 0);
        $storeOrder = (string)($tx['store_order_id'] ?? ($isTest ? 'PUMB-TEST-' . $capId : 'PUMB-CALLBACK-' . $capId));
        $letter = $data['guarantee_letter'] ?? null;
        $this->upsertTransaction($orderId, $storeOrder, $capId, $state, $isTest ? 1 : 0, ['callback' => $data], $letter);
        if (!$isTest && $orderId > 0) $this->applyOrderStatus($orderId, $state, false);
        $this->reply(['success' => true, 'error' => null]);
    }
    private function validBasicAuth(bool $isTest): bool {
        $suffix = $isTest ? 'test_' : '';
        $expectedUser = (string)$this->config->get('payment_pumb_credit_' . $suffix . 'callback_user');
        $expectedPass = (string)$this->config->get('payment_pumb_credit_' . $suffix . 'callback_password');
        if ($expectedUser === '' || $expectedPass === '') return false;
        $header = (string)($this->request->server['HTTP_AUTHORIZATION'] ?? $this->request->server['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (stripos($header, 'Basic ') !== 0) return false;
        $decoded = base64_decode(substr($header, 6), true);
        return is_string($decoded) && hash_equals($expectedUser . ':' . $expectedPass, $decoded);
    }
    private function allowedIp(bool $isTest): bool {
        $suffix = $isTest ? 'test_' : '';
        $allowed = array_filter(array_map('trim', explode(',', (string)$this->config->get('payment_pumb_credit_' . $suffix . 'callback_ips'))));
        return !$allowed || in_array((string)($this->request->server['REMOTE_ADDR'] ?? ''), $allowed, true);
    }
    private function isTestMode(): bool { return (bool)$this->config->get('payment_pumb_credit_test_mode'); }
    private function requestedTerm(): ?int {
        $raw = $this->request->post['term'] ?? $this->request->get['term'] ?? null;
        if (!is_scalar($raw) || !preg_match('/^[0-9]{1,2}$/', (string)$raw)) return null;
        $term = (int)$raw;
        return in_array($term, $this->allowedTerms(), true) ? $term : null;
    }
    private function allowedTerms(): array {
        $configured = $this->config->get('payment_pumb_credit_terms');
        $decoded = is_array($configured) ? $configured : json_decode((string)$configured, true);
        if (!is_array($decoded)) return [];
        $terms = [];
        foreach ($decoded as $value) {
            if (is_int($value) || (is_string($value) && preg_match('/^[0-9]{1,2}$/', $value))) $terms[] = (int)$value;
        }
        return array_values(array_unique($terms));
    }
    private function createPayload(array $order, int $term): array {
        $goods = []; $total = 0.0;
        $products = $this->db->query("SELECT `name`,`quantity`,`price` FROM `" . DB_PREFIX . "order_product` WHERE `order_id`='" . (int)$order['order_id'] . "'")->rows;
        foreach ($products as $product) { $amount = round((float)$product['price'], 2); $count = (int)$product['quantity']; $goods[] = ['name' => (string)$product['name'], 'count' => $count, 'amount' => $amount]; $total += $amount * $count; }
        $total = round($total, 2);
        return ['store_order_id' => 'OC-' . (int)$order['order_id'], 'point_of_sale_code' => (string)$this->config->get('payment_pumb_credit_point_of_sale_code'), 'partner_name' => (string)$this->config->get('payment_pumb_credit_partner_name'), 'channel_type' => 'INTERNET', 'flow' => ['type' => 'DIGITAL_SF'], 'customer' => ['phone' => $this->phone((string)$order['telephone'])], 'invoices' => [['date' => date('Y-m-d'), 'invoice_number' => 'OC-' . (int)$order['order_id'], 'goods' => $goods, 'total_amount' => $total]], 'credit_request' => ['term' => $term, 'amount' => $total]];
    }
    private function oauthToken(): string {
        $cache = rtrim(defined('DIR_CACHE') ? DIR_CACHE : sys_get_temp_dir(), '/\\') . '/pumb_credit_token_' . ($this->isTestMode() ? 'test' : 'prod') . '.json';
        $cached = is_file($cache) ? json_decode((string)file_get_contents($cache), true) : null;
        if (self::tokenCacheIsFresh($cached, time())) return (string)$cached['access_token'];
        $payload = http_build_query(['client_id' => 'EXT_OIC', 'username' => (string)$this->config->get('payment_pumb_credit_oauth_username'), 'password' => (string)$this->config->get('payment_pumb_credit_oauth_password'), 'grant_type' => 'password']);
        $curl = curl_init((string)$this->config->get('payment_pumb_credit_oauth_url'));
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'], CURLOPT_TIMEOUT => 20]);
        $raw = curl_exec($curl); $http = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl);
        $body = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        if ($http !== 200) return '';
        $record = self::tokenCacheRecord($body, time()); if ($record === null) return '';
        @file_put_contents($cache, json_encode($record));
        return $record['access_token'];
    }
    public static function tokenCacheRecord(array $body, int $now): ?array {
        $token = trim((string)($body['access_token'] ?? ''));
        if ($token === '') return null;
        return ['access_token' => $token, 'expires_at' => $now + max(1, (int)($body['expires_in'] ?? 300) - 15)];
    }
    public static function tokenCacheIsFresh(mixed $record, int $now): bool {
        return is_array($record) && !empty($record['access_token']) && (int)($record['expires_at'] ?? 0) > $now + 15;
    }
    private function api(string $method, string $path, array $payload = []): array {
        $token = $this->oauthToken(); if ($token === '') return ['http' => 0, 'body' => []];
        $url = rtrim((string)$this->config->get('payment_pumb_credit_api_base'), '/') . $path;
        $curl = curl_init($url); $headers = ['Authorization: Bearer ' . $token, 'X-Flow-Id: ' . $this->flowId(), 'Accept: application/json'];
        if ($method !== 'GET') { $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); if (!is_string($body)) return ['http' => 0, 'body' => []]; $headers[] = 'Content-Type: application/json'; curl_setopt($curl, CURLOPT_POSTFIELDS, $body); }
        curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 20]);
        $raw = curl_exec($curl); $http = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl);
        return ['http' => $http, 'body' => is_string($raw) ? (json_decode($raw, true) ?: []) : []];
    }
    private function flowId(): string { return bin2hex(random_bytes(16)); }
    private function phone(string $phone): string { $digits = preg_replace('/\D+/', '', $phone); return '+' . (str_starts_with($digits, '380') ? $digits : '380' . substr($digits, -9)); }
    private function transactionByOrder(int $orderId, int $isTest): array { $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "pumb_credit_transaction` WHERE `order_id`='" . $orderId . "' AND `is_test`='" . $isTest . "' ORDER BY `pumb_credit_transaction_id` DESC LIMIT 1"); return $q->row ?? []; }
    private function transactionByCap(string $capId, int $isTest): array { $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "pumb_credit_transaction` WHERE `cap_id`='" . $this->db->escape($capId) . "' AND `is_test`='" . $isTest . "' LIMIT 1"); return $q->row ?? []; }
    private function upsertTransaction(int $orderId, string $storeOrder, string $capId, string $state, int $isTest, array $payload, mixed $letter, ?int $term = null): void {
        $existing = $this->transactionByCap($capId, $isTest);
        $previous = $existing ? json_decode((string)($existing['payload'] ?? ''), true) : null;
        if (is_array($previous) && isset($previous['create']['request']) && !isset($payload['create']['request'])) $payload['create'] = $previous['create'];
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE); if (!is_string($payloadJson)) $payloadJson = '{}';
        $letterJson = $letter === null ? null : json_encode($letter, JSON_UNESCAPED_UNICODE); if ($letter !== null && !is_string($letterJson)) $letterJson = null;
        $agreement = is_array($letter) ? (string)($letter['content']['customer_agreement']['number'] ?? '') : '';
        $this->db->query("INSERT INTO `" . DB_PREFIX . "pumb_credit_transaction` SET `order_id`='" . $orderId . "',`store_order_id`='" . $this->db->escape($storeOrder) . "',`cap_id`='" . $this->db->escape($capId) . "',`state`='" . $this->db->escape($state) . "',`is_test`='" . $isTest . "',`requested_term`=" . ($term === null ? 'NULL' : "'" . (int)$term . "'") . ",`guarantee_letter`=" . ($letterJson === null ? 'NULL' : "'" . $this->db->escape($letterJson) . "'") . ",`agreement_number`=NULLIF('" . $this->db->escape($agreement) . "',''),`payload`='" . $this->db->escape($payloadJson) . "',`date_added`=NOW(),`date_modified`=NOW() ON DUPLICATE KEY UPDATE `cap_id`=VALUES(`cap_id`),`state`=VALUES(`state`),`requested_term`=COALESCE(VALUES(`requested_term`),`requested_term`),`guarantee_letter`=COALESCE(VALUES(`guarantee_letter`),`guarantee_letter`),`agreement_number`=COALESCE(NULLIF(VALUES(`agreement_number`),''),`agreement_number`),`payload`=VALUES(`payload`),`date_modified`=NOW()");
    }
    private function applyOrderStatus(int $orderId, string $state, bool $force): void {
        $key = $state === 'WAITING_CLIENT' ? 'waiting_client' : ($state === 'WAITING_STORE_CONFIRM' ? 'waiting_store' : ($state === 'FUNDED' ? 'funded' : ($state === 'REFUND_FINISHED' ? 'returned' : (in_array($state, ['CANCELED_BY_CLIENT','CANCELED_BY_STORE','REJECTED','NO_LIMIT','OVER_LIMIT','CLIENT_NOT_FOUND','FAIL','PUSH_TIMEOUT','CONFIRM_TIME_EXPIRED','FAIL_OTP','IDENTIFICATION_FAILED'], true) ? 'failed' : ''))));
        $status = (int)$this->config->get('payment_pumb_credit_status_' . $key); if (!$status || (!$force && !$this->config->get('payment_pumb_credit_status'))) return;
        $this->load->model('checkout/order'); $this->model_checkout_order->addHistory($orderId, $status, 'ПУМБ Сплачуйте частинами: ' . $state, false);
    }
    private function reply(array $json): void { $this->response->addHeader('Content-Type: application/json'); $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE)); }
}
