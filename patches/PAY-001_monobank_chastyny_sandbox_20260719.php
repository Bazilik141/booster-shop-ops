<?php
/**
 * PAY-001 — Monobank Покупка Частинами, Phase 1 sandbox extension.
 *
 * Upload to OpenCart public_html and run: php PAY-001_monobank_chastyny_sandbox_20260719.php
 *
 * DB changes (owner-approved): creates ocp5_mono_chast_transaction, six mono order statuses,
 * one payment extension row and payment_mono_chast settings. Default status is disabled.
 * Rollback: restore _patch_backups/PAY-001_monobank_chastyny_sandbox-<timestamp>/ or run its
 * rollback.sql after disabling the extension. Do not drop the transaction table after real use.
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-001_monobank_chastyny_sandbox_20260719';
const PATCH_MARKER = 'PAY-001 Phase 1 sandbox installed 2026-07-19';

function fail(string $message): never { fwrite(STDERR, "ERROR: {$message}\n"); exit(1); }
function q(mysqli $db, string $sql): mysqli_result|bool { $result = $db->query($sql); if ($result === false) fail('SQL failed: ' . $db->error); return $result; }
function writeFile(string $path, string $contents): void { $dir = dirname($path); if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) fail("Cannot create {$dir}"); if (file_put_contents($path, $contents) === false) fail("Cannot write {$path}"); }

$root = __DIR__;
$config = $root . '/config.php';
$hutkoAnchor = $root . '/extension/hutko/install.json';
$extensionRoot = $root . '/extension/mono_chast';

if (!is_file($config)) fail('config.php missing: run only from public_html.');
if (!is_file($hutkoAnchor)) fail('OC4 Hutko extension anchor missing; refusing an unverified install.');
if (is_file($extensionRoot . '/.pay001-marker')) { echo "already_applied=yes\n"; exit(0); }
if (file_exists($extensionRoot)) fail('extension/mono_chast already exists without PAY-001 marker; refusing overwrite.');

require $config;
foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PORT', 'DB_PREFIX'] as $constant) if (!defined($constant)) fail("{$constant} missing from config.php");
if (DB_PREFIX !== 'ocp5_') fail('Unexpected DB_PREFIX; expected ocp5_.');

$timestamp = date('Ymd-His');
$backup = $root . '/_patch_backups/' . PATCH_ID . '-' . $timestamp;
if (!mkdir($backup, 0755, true) && !is_dir($backup)) fail('Cannot create backup directory.');
copy($config, $backup . '/config.php.before') || fail('Cannot back up config.php.');

$rollback = <<<'SQL'
-- PAY-001 rollback: first disable payment_mono_chast_status in OpenCart admin.
DELETE FROM `ocp5_setting` WHERE `code` = 'payment_mono_chast';
DELETE FROM `ocp5_extension` WHERE `extension` = 'mono_chast' AND `type` = 'payment' AND `code` = 'mono_chast';
DELETE FROM `ocp5_order_status` WHERE `name` IN ('ПЧ mono — очікує клієнта','ПЧ mono — очікує видачу','ПЧ mono — активна','ПЧ mono — завершена','ПЧ mono — повернена','ПЧ mono — відхилена');
-- Keep ocp5_mono_chast_transaction for audit once any sandbox/real transaction exists.
SQL;
writeFile($backup . '/rollback.sql', $rollback . "\n");

$files = [];
$files['install.json'] = <<<'JSON'
{
  "name": "Monobank Покупка Частинами (sandbox)",
  "version": "1.0.0",
  "author": "Booster Shop",
  "link": "",
  "instruction": "PAY-001 Phase 1; disabled by default.",
  "code": "mono_chast"
}
JSON;

$files['catalog/language/uk-ua/payment/mono_chast.php'] = <<<'PHP'
<?php
$_['text_title'] = 'Покупка Частинами monobank';
$_['error_minimum'] = 'Покупка Частинами доступна для замовлень від 500 грн.';
$_['error_unavailable'] = 'Покупка Частинами тимчасово недоступна.';
$_['error_phone'] = 'Потрібен номер телефону у міжнародному форматі.';
PHP;

$files['catalog/model/payment/mono_chast.php'] = <<<'PHP'
<?php
namespace Opencart\Catalog\Model\Extension\MonoChast\Payment;

class MonoChast extends \Opencart\System\Engine\Model {
    public function getMethods(array $address = []): array {
        if (!$this->config->get('payment_mono_chast_status')) return [];
        if (strtoupper((string)($this->session->data['currency'] ?? 'UAH')) !== 'UAH') return [];
        if ((float)$this->cart->getTotal() < (float)$this->config->get('payment_mono_chast_min_total')) return [];
        $parts = json_decode((string)$this->config->get('payment_mono_chast_parts'), true);
        if (!is_array($parts)) $parts = [3, 4, 5];
        $option = [];
        foreach ($parts as $part) {
            $part = (int)$part;
            if (in_array($part, [3, 4, 5], true)) $option['mono_chast_' . $part] = ['code' => 'mono_chast.mono_chast_' . $part, 'name' => 'Покупка Частинами monobank — ' . $part . ' платежі'];
        }
        return $option ? ['code' => 'mono_chast', 'name' => 'Покупка Частинами monobank', 'option' => $option, 'sort_order' => (int)$this->config->get('payment_mono_chast_sort_order')] : [];
    }
}
PHP;

$files['catalog/controller/payment/mono_chast.php'] = <<<'PHP'
<?php
namespace Opencart\Catalog\Controller\Extension\MonoChast\Payment;

class MonoChast extends \Opencart\System\Engine\Controller {
    private const API_CREATE = '/api/order/create';
    private const API_STATE = '/api/order/state';

    public function index(): string { return ''; } // Phase 1 remains hidden; Phase 2 supplies UI.

    public function confirm(): void {
        $this->load->language('extension/mono_chast/payment/mono_chast');
        $json = [];
        $orderId = (int)($this->session->data['order_id'] ?? 0);
        if (!$this->config->get('payment_mono_chast_status') || !$orderId) { $json['error'] = $this->language->get('error_unavailable'); $this->reply($json); return; }
        $this->load->model('checkout/order');
        $order = $this->model_checkout_order->getOrder($orderId);
        $payment = json_decode((string)($order['payment_method'] ?? ''), true);
        if (!is_array($payment) || !preg_match('/^mono_chast\\.mono_chast_([345])$/', (string)($payment['code'] ?? ''), $match)) { $json['error'] = $this->language->get('error_unavailable'); $this->reply($json); return; }
        if ((float)$order['total'] < (float)$this->config->get('payment_mono_chast_min_total')) { $json['error'] = $this->language->get('error_minimum'); $this->reply($json); return; }
        if (!preg_match('/^\\+?[1-9][0-9]{8,14}$/', preg_replace('/[\\s()\\-]/', '', (string)$order['telephone']))) { $json['error'] = $this->language->get('error_phone'); $this->reply($json); return; }
        $existing = $this->transactionByStoreOrder('OC-' . $orderId);
        if ($existing) { $json = ['success' => true, 'order_id' => $existing['mono_order_id'], 'idempotent' => true]; $this->reply($json); return; }
        $payload = $this->createPayload($order, (int)$match[1]);
        $response = $this->api(self::API_CREATE, $payload);
        if (($response['http'] ?? 0) !== 201 || empty($response['body']['order_id'])) { $this->storeEvent($orderId, 'OC-' . $orderId, '', 'CREATE_FAILED', '', $response); $json['error'] = 'Monobank sandbox rejected the application.'; $this->reply($json); return; }
        $this->storeEvent($orderId, 'OC-' . $orderId, (string)$response['body']['order_id'], 'IN_PROCESS', 'WAITING_FOR_CLIENT', $response, (int)$match[1]);
        $json = ['success' => true, 'order_id' => $response['body']['order_id'], 'idempotent' => false];
        $this->reply($json);
    }

    public function callback(): void {
        $raw = (string)file_get_contents('php://input');
        if (!$this->validSignature($raw)) { $this->response->addHeader('HTTP/1.1 401 Unauthorized'); $this->response->setOutput('invalid signature'); return; }
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['order_id']) || empty($data['state'])) { $this->response->addHeader('HTTP/1.1 400 Bad Request'); $this->response->setOutput('invalid payload'); return; }
        $transaction = $this->transactionByMonoOrder((string)$data['order_id']);
        if (!$transaction) { $this->response->addHeader('HTTP/1.1 404 Not Found'); $this->response->setOutput('unknown order'); return; }
        $state = (string)$data['state']; $sub = (string)($data['order_sub_state'] ?? '');
        $this->storeEvent((int)$transaction['order_id'], (string)$transaction['store_order_id'], (string)$data['order_id'], $state, $sub, ['callback' => $data]);
        $this->applyOrderStatus((int)$transaction['order_id'], $state, $sub);
        $this->response->setOutput('OK');
    }

    public function poll(): void {
        $orderId = (int)($this->session->data['order_id'] ?? 0); $tx = $this->transactionByOrderId($orderId);
        if (!$orderId || !$tx) { $this->reply(['error' => 'order not found']); return; }
        $response = $this->api(self::API_STATE, ['order_id' => $tx['mono_order_id']]);
        if (($response['http'] ?? 0) === 200 && isset($response['body']['state'])) { $state = (string)$response['body']['state']; $sub = (string)($response['body']['order_sub_state'] ?? ''); $this->storeEvent($orderId, $tx['store_order_id'], $tx['mono_order_id'], $state, $sub, $response); $this->applyOrderStatus($orderId, $state, $sub); }
        $this->reply(['http' => $response['http'] ?? 0, 'state' => $response['body']['state'] ?? null, 'order_sub_state' => $response['body']['order_sub_state'] ?? null]);
    }

    private function createPayload(array $order, int $parts): array {
        $products = []; $q = $this->db->query("SELECT `name`,`quantity`,`total` FROM `" . DB_PREFIX . "order_product` WHERE `order_id`='" . (int)$order['order_id'] . "'");
        foreach ($q->rows as $row) $products[] = ['name' => (string)$row['name'], 'count' => (int)$row['quantity'], 'sum' => round((float)$row['total'], 2)];
        $base = rtrim((string)$this->config->get('config_url'), '/') . '/index.php?route=extension/mono_chast/payment/mono_chast|callback&language=' . rawurlencode((string)$this->config->get('config_language'));
        return ['store_order_id' => 'OC-' . (int)$order['order_id'], 'client_phone' => $this->phone((string)$order['telephone']), 'total_sum' => round((float)$order['total'], 2), 'invoice' => ['number' => 'OC-' . (int)$order['order_id'], 'date' => date('Y-m-d'), 'source' => 'INTERNET', 'point_id' => (string)$this->config->get('payment_mono_chast_point_id')], 'available_programs' => [['type' => 'payment_installments', 'available_parts_count' => [$parts]]], 'products' => $products, 'result_callback' => $base];
    }
    private function api(string $path, array $payload): array {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); if (!is_string($body)) return ['http' => 0, 'body' => []];
        $secret = (string)$this->config->get('payment_mono_chast_store_secret'); $store = (string)$this->config->get('payment_mono_chast_store_id');
        $url = rtrim((string)$this->config->get('payment_mono_chast_api_base'), '/') . $path;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $trace = ''; $curl = curl_init($url);
            curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'store-id: ' . $store, 'signature: ' . base64_encode(hash_hmac('sha256', $body, $secret, true))], CURLOPT_TIMEOUT => 20, CURLOPT_HEADERFUNCTION => static function($curl, string $header) use (&$trace): int { if (stripos($header, 'Trace-Id:') === 0) $trace = trim(substr($header, 9)); return strlen($header); }]);
            $raw = curl_exec($curl); $http = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl);
            if ($http < 500 || $attempt === 2) return ['http' => $http, 'body' => is_string($raw) ? (json_decode($raw, true) ?: []) : [], 'trace_id' => $trace];
            usleep((int)(250000 * (2 ** $attempt)));
        }
        return ['http' => 0, 'body' => []];
    }
    private function validSignature(string $raw): bool { $got = (string)($this->request->server['HTTP_SIGNATURE'] ?? ''); $want = base64_encode(hash_hmac('sha256', $raw, (string)$this->config->get('payment_mono_chast_store_secret'), true)); return $got !== '' && hash_equals($want, $got); }
    private function phone(string $phone): string { $digits = preg_replace('/\D+/', '', $phone); return '+' . (str_starts_with($digits, '380') ? $digits : '380' . substr($digits, -9)); }
    private function transactionByStoreOrder(string $store): array { $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mono_chast_transaction` WHERE `store_order_id`='" . $this->db->escape($store) . "' ORDER BY `mono_chast_transaction_id` DESC LIMIT 1"); return $q->row ?? []; }
    private function transactionByMonoOrder(string $mono): array { $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mono_chast_transaction` WHERE `mono_order_id`='" . $this->db->escape($mono) . "' ORDER BY `mono_chast_transaction_id` DESC LIMIT 1"); return $q->row ?? []; }
    private function transactionByOrderId(int $id): array { $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mono_chast_transaction` WHERE `order_id`='" . $id . "' ORDER BY `mono_chast_transaction_id` DESC LIMIT 1"); return $q->row ?? []; }
    private function storeEvent(int $order, string $store, string $mono, string $state, string $sub, array $payload, int $parts = 0): void { $trace = $this->db->escape((string)($payload['trace_id'] ?? '')); $this->db->query("INSERT INTO `" . DB_PREFIX . "mono_chast_transaction` SET `order_id`='" . $order . "', `store_order_id`='" . $this->db->escape($store) . "', `mono_order_id`=NULLIF('" . $this->db->escape($mono) . "',''), `parts_count`='" . $parts . "', `state`='" . $this->db->escape($state) . "', `order_sub_state`='" . $this->db->escape($sub) . "', `trace_id`='" . $trace . "', `payload`='" . $this->db->escape(json_encode($payload, JSON_UNESCAPED_UNICODE)) . "', `date_added`=NOW(), `date_modified`=NOW() ON DUPLICATE KEY UPDATE `state`=VALUES(`state`), `order_sub_state`=VALUES(`order_sub_state`), `trace_id`=VALUES(`trace_id`), `payload`=VALUES(`payload`), `date_modified`=NOW()"); }
    private function applyOrderStatus(int $orderId, string $state, string $sub): void { $key = $state === 'IN_PROCESS' && $sub === 'WAITING_FOR_CLIENT' ? 'waiting_client' : ($state === 'IN_PROCESS' && $sub === 'WAITING_FOR_STORE_CONFIRM' ? 'waiting_store' : ($state === 'SUCCESS' && $sub === 'ACTIVE' ? 'active' : ($state === 'SUCCESS' && $sub === 'DONE' ? 'done' : ($state === 'SUCCESS' && $sub === 'RETURNED' ? 'returned' : (str_starts_with($state, 'FAIL') ? 'failed' : ''))))); $status = (int)$this->config->get('payment_mono_chast_status_' . $key); if (!$status) return; $this->load->model('checkout/order'); $this->model_checkout_order->addHistory($orderId, $status, 'monobank ПЧ: ' . $state . ($sub ? '/' . $sub : ''), false); }
    private function reply(array $json): void { $this->response->addHeader('Content-Type: application/json'); $this->response->setOutput(json_encode($json)); }
}
PHP;

$files['admin/language/uk-ua/payment/mono_chast.php'] = <<<'PHP'
<?php
$_['heading_title'] = 'Покупка Частинами monobank (sandbox)';
$_['text_success'] = 'Налаштування збережено.';
$_['entry_status'] = 'Увімкнути метод';
$_['entry_api_base'] = 'API base URL';
$_['entry_store_id'] = 'Store ID';
$_['entry_store_secret'] = 'Store secret';
$_['entry_point_id'] = 'Point ID';
$_['entry_parts'] = 'Кількість платежів (JSON)';
$_['entry_min_total'] = 'Мінімальна сума';
$_['entry_sort_order'] = 'Порядок сортування';
$_['error_permission'] = 'Немає прав для зміни налаштувань.';
PHP;

$files['admin/controller/payment/mono_chast.php'] = <<<'PHP'
<?php
namespace Opencart\Admin\Controller\Extension\MonoChast\Payment;

class MonoChast extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $this->load->language('extension/mono_chast/payment/mono_chast');
        $this->load->model('setting/setting');
        if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_mono_chast', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/mono_chast/payment/mono_chast', 'user_token=' . $this->session->data['user_token'])); return;
        }
        $setting = $this->model_setting_setting->getSetting('payment_mono_chast');
        $keys = ['status','api_base','store_id','store_secret','point_id','parts','min_total','sort_order','status_waiting_client','status_waiting_store','status_active','status_done','status_returned','status_failed'];
        $data = ['heading_title' => $this->language->get('heading_title'), 'action' => $this->url->link('extension/mono_chast/payment/mono_chast', 'user_token=' . $this->session->data['user_token']), 'cancel' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')];
        foreach ($keys as $key) $data['payment_mono_chast_' . $key] = $this->request->post['payment_mono_chast_' . $key] ?? ($setting['payment_mono_chast_' . $key] ?? '');
        $data['header'] = $this->load->controller('common/header'); $data['column_left'] = $this->load->controller('common/column_left'); $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/mono_chast/payment/mono_chast', $data));
    }
    private function validate(): bool { if (!$this->user->hasPermission('modify', 'extension/mono_chast/payment/mono_chast')) { $this->error['warning'] = $this->language->get('error_permission'); } return !$this->error; }
    public function confirmApplication(): void { $this->applicationAction('/api/order/confirm'); }
    public function rejectApplication(): void { $this->applicationAction('/api/order/reject'); }
    private function applicationAction(string $path): void {
        $json = [];
        if (!$this->validate() || $this->request->server['REQUEST_METHOD'] !== 'POST') { $json['error'] = 'forbidden'; $this->json($json); return; }
        $orderId = trim((string)($this->request->post['mono_order_id'] ?? ''));
        if ($orderId === '') { $json['error'] = 'mono_order_id required'; $this->json($json); return; }
        $body = json_encode(['order_id' => $orderId]); $secret = (string)$this->config->get('payment_mono_chast_store_secret');
        $curl = curl_init(rtrim((string)$this->config->get('payment_mono_chast_api_base'), '/') . $path);
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'store-id: ' . (string)$this->config->get('payment_mono_chast_store_id'), 'signature: ' . base64_encode(hash_hmac('sha256', $body, $secret, true))], CURLOPT_TIMEOUT => 20]);
        $raw = curl_exec($curl); $http = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl); $response = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        if ($http === 200 && isset($response['state'])) { $state = $this->db->escape((string)$response['state']); $sub = $this->db->escape((string)($response['order_sub_state'] ?? '')); $this->db->query("UPDATE `" . DB_PREFIX . "mono_chast_transaction` SET `state`='{$state}', `order_sub_state`='{$sub}', `date_modified`=NOW() WHERE `mono_order_id`='" . $this->db->escape($orderId) . "'"); }
        $this->json(['http' => $http, 'response' => $response]);
    }
    private function json(array $json): void { $this->response->addHeader('Content-Type: application/json'); $this->response->setOutput(json_encode($json)); }
}
PHP;

$files['admin/view/template/payment/mono_chast.twig'] = <<<'TWIG'
{{ header }}{{ column_left }}
<div id="content" class="container-fluid"><div class="page-header"><div class="container-fluid"><h1>{{ heading_title }}</h1></div></div>
<div class="container-fluid"><div class="alert alert-warning">Phase 1 sandbox. Залиште метод вимкненим: публічний UI не входить у цей етап.</div><form id="form-payment" action="{{ action }}" method="post"><div class="card"><div class="card-body">
<div class="mb-3"><label class="form-label">API base URL</label><input class="form-control" name="payment_mono_chast_api_base" value="{{ payment_mono_chast_api_base }}"></div>
<div class="mb-3"><label class="form-label">Store ID</label><input class="form-control" name="payment_mono_chast_store_id" value="{{ payment_mono_chast_store_id }}"></div>
<div class="mb-3"><label class="form-label">Store secret</label><input type="password" class="form-control" name="payment_mono_chast_store_secret" value="{{ payment_mono_chast_store_secret }}" autocomplete="new-password"></div>
<div class="mb-3"><label class="form-label">Point ID</label><input class="form-control" name="payment_mono_chast_point_id" value="{{ payment_mono_chast_point_id }}"></div>
<div class="mb-3"><label class="form-label">Кількість платежів (JSON)</label><input class="form-control" name="payment_mono_chast_parts" value="{{ payment_mono_chast_parts }}"></div>
<div class="mb-3"><label class="form-label">Мінімальна сума</label><input type="number" step="0.01" class="form-control" name="payment_mono_chast_min_total" value="{{ payment_mono_chast_min_total }}"></div>
<div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="payment_mono_chast_status" value="1"{% if payment_mono_chast_status %} checked{% endif %}><label class="form-check-label">Увімкнути метод</label></div>
<input type="hidden" name="payment_mono_chast_sort_order" value="{{ payment_mono_chast_sort_order }}"><hr><button type="submit" class="btn btn-primary">Зберегти</button> <a href="{{ cancel }}" class="btn btn-light">Скасувати</a>
</div></div></form></div></div>{{ footer }}
TWIG;

foreach ($files as $relative => $contents) writeFile($extensionRoot . '/' . $relative, $contents . "\n");
writeFile($extensionRoot . '/.pay001-marker', PATCH_MARKER . "\n");

$php = $root . '/system/bin/php'; if (!is_executable($php)) $php = PHP_BINARY;
foreach (array_keys($files) as $relative) if (str_ends_with($relative, '.php')) { exec(escapeshellarg($php) . ' -l ' . escapeshellarg($extensionRoot . '/' . $relative), $out, $code); if ($code !== 0) { rename($extensionRoot, $backup . '/extension-mono_chast.syntax-failed'); fail('php -l failed for ' . $relative); } }

$db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
if ($db->connect_errno) { rename($extensionRoot, $backup . '/extension-mono_chast.db-failed'); fail('Database connection failed; files restored to backup.'); }
$db->set_charset('utf8mb4');
$prefix = DB_PREFIX;
q($db, "CREATE TABLE IF NOT EXISTS `{$prefix}mono_chast_transaction` (`mono_chast_transaction_id` int(11) NOT NULL AUTO_INCREMENT, `order_id` int(11) NOT NULL, `store_order_id` varchar(64) NOT NULL, `mono_order_id` varchar(64) DEFAULT NULL, `parts_count` tinyint(3) NOT NULL DEFAULT 0, `state` varchar(32) NOT NULL DEFAULT '', `order_sub_state` varchar(64) NOT NULL DEFAULT '', `trace_id` varchar(128) NOT NULL DEFAULT '', `payload` mediumtext, `date_added` datetime NOT NULL, `date_modified` datetime NOT NULL, PRIMARY KEY (`mono_chast_transaction_id`), UNIQUE KEY `store_order_id` (`store_order_id`), UNIQUE KEY `mono_order_id` (`mono_order_id`), KEY `order_id` (`order_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$statuses = ['waiting_client' => 'ПЧ mono — очікує клієнта','waiting_store' => 'ПЧ mono — очікує видачу','active' => 'ПЧ mono — активна','done' => 'ПЧ mono — завершена','returned' => 'ПЧ mono — повернена','failed' => 'ПЧ mono — відхилена'];
$languages = q($db, "SELECT DISTINCT `language_id` FROM `{$prefix}order_status`"); $languageIds = []; while ($row = $languages->fetch_assoc()) $languageIds[] = (int)$row['language_id']; if (!$languageIds) fail('No OC order-status language anchor found.');
$statusIds = [];
foreach ($statuses as $key => $name) { foreach ($languageIds as $languageId) { $safe = $db->real_escape_string($name); q($db, "INSERT INTO `{$prefix}order_status` (`language_id`,`name`) SELECT {$languageId}, '{$safe}' WHERE NOT EXISTS (SELECT 1 FROM `{$prefix}order_status` WHERE `language_id`={$languageId} AND `name`='{$safe}')"); } $safe = $db->real_escape_string($name); $row = q($db, "SELECT `order_status_id` FROM `{$prefix}order_status` WHERE `name`='{$safe}' ORDER BY `order_status_id` ASC LIMIT 1")->fetch_assoc(); $statusIds[$key] = (int)($row['order_status_id'] ?? 0); if (!$statusIds[$key]) fail("Status lookup failed for {$key}"); }
q($db, "INSERT INTO `{$prefix}extension` (`extension`,`type`,`code`) SELECT 'mono_chast','payment','mono_chast' WHERE NOT EXISTS (SELECT 1 FROM `{$prefix}extension` WHERE `extension`='mono_chast' AND `type`='payment' AND `code`='mono_chast')");
$route = 'extension/mono_chast/payment/mono_chast';
$group = q($db, "SELECT `permission` FROM `{$prefix}user_group` WHERE `user_group_id`=1 LIMIT 1")->fetch_assoc();
if (!$group) fail('Administrator user group anchor missing.');
writeFile($backup . '/user_group_1_permission.before.json', (string)$group['permission']);
$permissions = json_decode((string)$group['permission'], true);
if (!is_array($permissions)) fail('Administrator permissions JSON is invalid.');
foreach (['access', 'modify'] as $scope) { if (!isset($permissions[$scope]) || !is_array($permissions[$scope])) $permissions[$scope] = []; if (!in_array($route, $permissions[$scope], true)) $permissions[$scope][] = $route; }
$permissionJson = json_encode($permissions, JSON_UNESCAPED_SLASHES);
if (!is_string($permissionJson)) fail('Cannot encode administrator permissions.');
q($db, "UPDATE `{$prefix}user_group` SET `permission`='" . $db->real_escape_string($permissionJson) . "' WHERE `user_group_id`=1");
file_put_contents($backup . '/rollback.sql', "UPDATE `{$prefix}user_group` SET `permission`='" . addslashes((string)$group['permission']) . "' WHERE `user_group_id`=1;\n", FILE_APPEND);
q($db, "DELETE FROM `{$prefix}setting` WHERE `store_id`=0 AND `code`='payment_mono_chast'");
$settings = ['payment_mono_chast_status' => '0','payment_mono_chast_api_base' => 'https://u2-demo-ext.mono.st4g3.com','payment_mono_chast_store_id' => '','payment_mono_chast_store_secret' => '','payment_mono_chast_point_id' => '','payment_mono_chast_parts' => '[3,4,5]','payment_mono_chast_min_total' => '500','payment_mono_chast_sort_order' => '0'];
foreach ($statusIds as $key => $id) $settings['payment_mono_chast_status_' . $key] = (string)$id;
foreach ($settings as $key => $value) { $safeKey = $db->real_escape_string($key); $safeValue = $db->real_escape_string($value); q($db, "INSERT INTO `{$prefix}setting` (`store_id`,`code`,`key`,`value`,`serialized`) VALUES (0,'payment_mono_chast','{$safeKey}','{$safeValue}',0)"); }
$db->close();
echo "done=ok\nphase=sandbox_hidden\next=configure sandbox credentials in Admin > Extensions > Payments > Покупка Частинами monobank\n";
@unlink(__FILE__);
