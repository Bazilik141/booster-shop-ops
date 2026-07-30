<?php
/**
 * PAY-002 — PUMB "Сплачуйте частинами" disabled extension skeleton.
 *
 * Upload to OpenCart public_html and run: php PAY-002_pumb-credit-skeleton_20260728.php
 *
 * Owner-approved DB changes (handoff 2026-07-27): creates the PUMB transaction
 * table and OC4 registry, installs disabled settings, and consolidates the six
 * mono-specific order statuses to five shared labels. This runner backs up all
 * touched source and database evidence before mutating anything.
 *
 * Rollback: first set payment_pumb_credit_status=0. Run the generated
 * _patch_backups/<PATCH_ID>-<timestamp>/rollback.sql. It restores the exact
 * pre-run status labels, extension/settings rows, and status-20 order/history
 * references captured at deployment time; do not delete the PUMB transaction
 * table after it has recorded a real application.
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-002_pumb-credit-skeleton_20260728';

function out(string $line): void { echo $line . PHP_EOL; }
function fail(string $message): never { fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL); exit(1); }
function ensure(bool $condition, string $message): void { if (!$condition) fail($message); }
function save(string $path, string $contents): void {
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) fail('Cannot create directory: ' . $dir);
    if (file_put_contents($path, $contents) === false) fail('Cannot write: ' . $path);
}
function query(mysqli $db, string $sql): mysqli_result|bool {
    $result = $db->query($sql);
    if ($result === false) fail('SQL failed: ' . $db->error);
    return $result;
}
function preparedCount(mysqli $db, string $sql, string $value): int {
    $stmt = $db->prepare($sql);
    if (!$stmt) fail('SQL preflight prepare failed: ' . $db->error);
    $stmt->bind_param('s', $value);
    if (!$stmt->execute()) fail('SQL preflight execute failed: ' . $stmt->error);
    $stmt->bind_result($count);
    if (!$stmt->fetch()) { $stmt->close(); fail('SQL preflight returned no count.'); }
    $stmt->close();
    return (int)$count;
}
function rawCount(mysqli $db, string $sql): int {
    $result = query($db, $sql);
    ensure($result instanceof mysqli_result, 'Count query did not return a result set.');
    $row = $result->fetch_row();
    return (int)($row[0] ?? 0);
}
function literal(mysqli $db, ?string $value): string { return $value === null ? 'NULL' : "'" . $db->real_escape_string($value) . "'"; }
function sqlRows(mysqli $db, string $sql): array {
    $result = query($db, $sql);
    $rows = [];
    if ($result instanceof mysqli_result) while ($row = $result->fetch_assoc()) $rows[] = $row;
    return $rows;
}
function sqlInsert(string $table, array $row, mysqli $db): string {
    $columns = []; $values = [];
    foreach ($row as $column => $value) { $columns[] = '`' . str_replace('`', '``', $column) . '`'; $values[] = literal($db, $value === null ? null : (string)$value); }
    return 'INSERT INTO `' . $table . '` (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n";
}

$root = getcwd() ?: '.';
$config = $root . '/config.php';
ensure(is_file($config), 'Run this file from OpenCart public_html (config.php not found).');
require_once $config;
ensure(defined('DB_PREFIX') && defined('DB_HOSTNAME') && defined('DB_USERNAME') && defined('DB_PASSWORD') && defined('DB_DATABASE') && defined('DB_PORT'), 'OpenCart database constants are unavailable.');

$monoController = $root . '/extension/mono_chast/catalog/controller/payment/mono_chast.php';
$extensionRoot = $root . '/extension/pumb_credit';
$marker = $extensionRoot . '/.pay002-marker';
ensure(is_file($monoController), 'Required live mono controller is missing: extension/mono_chast/catalog/controller/payment/mono_chast.php');
ensure(function_exists('curl_init'), 'PHP cURL extension is required for the PUMB OAuth/API client.');

mysqli_report(MYSQLI_REPORT_OFF);
$db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
ensure(!$db->connect_errno, 'Database connection failed before changes.');
$db->set_charset('utf8mb4');
$prefix = DB_PREFIX;

foreach ([$prefix . 'extension', $prefix . 'extension_install', $prefix . 'extension_path', $prefix . 'order_status', $prefix . 'order', $prefix . 'order_history', $prefix . 'setting', $prefix . 'user_group'] as $table) {
    ensure(preparedCount($db, 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', $table) === 1, 'Required table missing: ' . $table);
}

$oldStatuses = [
    'ПЧ mono — очікує клієнта' => 'Розстрочка — очікує клієнта',
    'ПЧ mono — очікує видачу' => 'Розстрочка — очікує видачі',
    'ПЧ mono — активна' => 'Розстрочка — оформлено',
    'ПЧ mono — завершена' => 'Розстрочка — оформлено',
    'ПЧ mono — повернена' => 'Розстрочка — повернено',
    'ПЧ mono — відхилена' => 'Розстрочка — відхилено',
];
foreach (array_keys($oldStatuses) as $name) ensure(preparedCount($db, "SELECT COUNT(*) FROM `{$prefix}order_status` WHERE `name` = ?", $name) === 1, 'Expected exactly one current mono status row: ' . $name);
foreach (array_unique(array_values($oldStatuses)) as $name) ensure(preparedCount($db, "SELECT COUNT(*) FROM `{$prefix}order_status` WHERE `name` = ?", $name) === 0, 'Shared status already exists; refusing an ambiguous merge: ' . $name);

if (is_file($marker)) {
    $required = ['install.json', 'catalog/model/payment/pumb_credit.php', 'catalog/controller/payment/pumb_credit.php', 'admin/controller/payment/pumb_credit.php'];
    foreach ($required as $relative) ensure(is_file($extensionRoot . '/' . $relative), 'PAY-002 marker exists but file is missing: ' . $relative);
    ensure(rawCount($db, "SELECT COUNT(*) FROM `{$prefix}extension` WHERE `extension`='pumb_credit' AND `type`='payment' AND `code`='pumb_credit'") === 1, 'PAY-002 marker exists but the payment registry row is missing.');
    $db->close();
    out('already_applied=yes');
    exit(0);
}
ensure(!is_dir($extensionRoot), 'extension/pumb_credit already exists without PAY-002 marker; refusing to overwrite it.');

$monoSource = file_get_contents($monoController);
ensure(is_string($monoSource), 'Cannot read live mono controller.');
$monoAnchor = "(\$state === 'SUCCESS' && \$sub === 'DONE' ? 'done' :";
ensure(substr_count($monoSource, $monoAnchor) === 1, 'Expected mono status-mapping anchor exactly once.');
$monoReplacement = "(\$state === 'SUCCESS' && \$sub === 'DONE' ? 'active' :";
$monoPatched = str_replace($monoAnchor, $monoReplacement, $monoSource);

$files = [];
$files['install.json'] = <<<'JSON'
{
  "name": "ПУМБ Сплачуйте частинами (disabled skeleton)",
  "version": "0.1.0",
  "author": "Booster Shop",
  "link": "",
  "instruction": "PAY-002 skeleton. Disabled by default; enter bank credentials only after owner approval.",
  "code": "pumb_credit"
}
JSON;
$files['catalog/model/payment/pumb_credit.php'] = <<<'PHP'
<?php
namespace Opencart\Catalog\Model\Extension\PumbCredit\Payment;
class PumbCredit extends \Opencart\System\Engine\Model {
    public function getMethods(array $address = []): array {
        // PAY-002: PUMB is intentionally not exposed through legacy Simple Checkout.
        // PAY-003 owns the shared credit-provider UI and will call the dedicated flow.
        return [];
    }
}
PHP;
$files['catalog/language/uk-ua/payment/pumb_credit.php'] = <<<'PHP'
<?php
$_['text_title'] = 'Сплачуйте частинами ПУМБ';
$_['error_unavailable'] = 'Сплачуйте частинами ПУМБ тимчасово недоступна.';
$_['error_minimum'] = 'Сплачуйте частинами ПУМБ доступна для замовлень від 500 грн.';
$_['error_phone'] = 'Потрібен коректний номер телефону у форматі +380XXXXXXXXX.';
PHP;
$files['catalog/controller/payment/pumb_credit.php'] = <<<'PHP'
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
        $response = $this->api('POST', self::API_CREATE, $this->createPayload($order));
        $capId = (string)($response['body']['id'] ?? '');
        if (($response['http'] ?? 0) !== 201 || $capId === '') { $this->reply(['error' => 'PUMB application was not created.', 'http' => $response['http'] ?? 0]); return; }
        $isTest = $this->isTestMode() ? 1 : 0;
        $this->upsertTransaction($orderId, 'OC-' . $orderId, $capId, 'WAITING_CLIENT', $isTest, ['create' => $response], null);
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
    private function createPayload(array $order): array {
        $goods = []; $total = 0.0;
        $products = $this->db->query("SELECT `name`,`quantity`,`price` FROM `" . DB_PREFIX . "order_product` WHERE `order_id`='" . (int)$order['order_id'] . "'")->rows;
        foreach ($products as $product) { $amount = round((float)$product['price'], 2); $count = (int)$product['quantity']; $goods[] = ['name' => (string)$product['name'], 'count' => $count, 'amount' => $amount]; $total += $amount * $count; }
        $total = round($total, 2);
        return ['store_order_id' => 'OC-' . (int)$order['order_id'], 'point_of_sale_code' => (string)$this->config->get('payment_pumb_credit_point_of_sale_code'), 'partner_name' => (string)$this->config->get('payment_pumb_credit_partner_name'), 'channel_type' => 'INTERNET', 'flow' => ['type' => 'DIGITAL_SF'], 'customer' => ['phone' => $this->phone((string)$order['telephone'])], 'invoices' => [['date' => date('Y-m-d'), 'invoice_number' => 'OC-' . (int)$order['order_id'], 'goods' => $goods, 'total_amount' => $total]], 'credit_request' => ['term' => (int)$this->config->get('payment_pumb_credit_term'), 'amount' => $total]];
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
    private function upsertTransaction(int $orderId, string $storeOrder, string $capId, string $state, int $isTest, array $payload, mixed $letter): void {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE); if (!is_string($payloadJson)) $payloadJson = '{}';
        $letterJson = $letter === null ? null : json_encode($letter, JSON_UNESCAPED_UNICODE); if ($letter !== null && !is_string($letterJson)) $letterJson = null;
        $agreement = is_array($letter) ? (string)($letter['content']['customer_agreement']['number'] ?? '') : '';
        $this->db->query("INSERT INTO `" . DB_PREFIX . "pumb_credit_transaction` SET `order_id`='" . $orderId . "',`store_order_id`='" . $this->db->escape($storeOrder) . "',`cap_id`='" . $this->db->escape($capId) . "',`state`='" . $this->db->escape($state) . "',`is_test`='" . $isTest . "',`guarantee_letter`=" . ($letterJson === null ? 'NULL' : "'" . $this->db->escape($letterJson) . "'") . ",`agreement_number`=NULLIF('" . $this->db->escape($agreement) . "',''),`payload`='" . $this->db->escape($payloadJson) . "',`date_added`=NOW(),`date_modified`=NOW() ON DUPLICATE KEY UPDATE `state`=VALUES(`state`),`guarantee_letter`=COALESCE(VALUES(`guarantee_letter`),`guarantee_letter`),`agreement_number`=NULLIF(VALUES(`agreement_number`),''),`payload`=VALUES(`payload`),`date_modified`=NOW()");
    }
    private function applyOrderStatus(int $orderId, string $state, bool $force): void {
        $key = $state === 'WAITING_CLIENT' ? 'waiting_client' : ($state === 'WAITING_STORE_CONFIRM' ? 'waiting_store' : ($state === 'FUNDED' ? 'funded' : ($state === 'REFUND_FINISHED' ? 'returned' : (in_array($state, ['CANCELED_BY_CLIENT','CANCELED_BY_STORE','REJECTED','NO_LIMIT','OVER_LIMIT','CLIENT_NOT_FOUND','FAIL','PUSH_TIMEOUT','CONFIRM_TIME_EXPIRED','FAIL_OTP','IDENTIFICATION_FAILED'], true) ? 'failed' : ''))));
        $status = (int)$this->config->get('payment_pumb_credit_status_' . $key); if (!$status || (!$force && !$this->config->get('payment_pumb_credit_status'))) return;
        $this->load->model('checkout/order'); $this->model_checkout_order->addHistory($orderId, $status, 'ПУМБ Сплачуйте частинами: ' . $state, false);
    }
    private function reply(array $json): void { $this->response->addHeader('Content-Type: application/json'); $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE)); }
}
PHP;
$files['admin/language/uk-ua/payment/pumb_credit.php'] = <<<'PHP'
<?php
$_['heading_title'] = 'ПУМБ «Сплачуйте частинами» (skeleton)';
$_['text_success'] = 'Налаштування збережено.';
$_['error_permission'] = 'Немає прав для зміни налаштувань.';
PHP;
$files['admin/controller/payment/pumb_credit.php'] = <<<'PHP'
<?php
namespace Opencart\Admin\Controller\Extension\PumbCredit\Payment;
class PumbCredit extends \Opencart\System\Engine\Controller {
    protected array $error = [];
    public function index(): void {
        $this->load->language('extension/pumb_credit/payment/pumb_credit'); $this->load->model('setting/setting');
        if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validate()) { $this->model_setting_setting->editSetting('payment_pumb_credit', $this->request->post); $this->session->data['success'] = $this->language->get('text_success'); $this->response->redirect($this->url->link('extension/pumb_credit/payment/pumb_credit', 'user_token=' . $this->session->data['user_token'])); return; }
        $setting = $this->model_setting_setting->getSetting('payment_pumb_credit');
        $keys = ['status','test_mode','oauth_url','api_base','oauth_username','oauth_password','point_of_sale_code','partner_name','callback_user','callback_password','callback_ips','test_callback_user','test_callback_password','test_callback_ips','min_total','max_total','term','sort_order','status_waiting_client','status_waiting_store','status_funded','status_returned','status_failed'];
        $data = ['heading_title' => $this->language->get('heading_title'), 'action' => $this->url->link('extension/pumb_credit/payment/pumb_credit', 'user_token=' . $this->session->data['user_token']), 'cancel' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')];
        foreach ($keys as $key) $data['payment_pumb_credit_' . $key] = $this->request->post['payment_pumb_credit_' . $key] ?? ($setting['payment_pumb_credit_' . $key] ?? '');
        foreach (['shipmentConfirm' => 'shipment_confirm_url', 'cancelApplication' => 'cancel_url', 'refund' => 'refund_url'] as $method => $key) $data[$key] = $this->url->link('extension/pumb_credit/payment/pumb_credit.' . $method, 'user_token=' . $this->session->data['user_token']);
        $data['header'] = $this->load->controller('common/header'); $data['column_left'] = $this->load->controller('common/column_left'); $data['footer'] = $this->load->controller('common/footer'); $this->response->setOutput($this->load->view('extension/pumb_credit/payment/pumb_credit', $data));
    }
    public function shipmentConfirm(): void { $this->manual('shipment'); }
    public function cancelApplication(): void { $this->manual('cancel'); }
    public function refund(): void { $this->manual('refund'); }
    private function manual(string $action): void {
        if (!$this->validate() || $this->request->server['REQUEST_METHOD'] !== 'POST') { $this->reply(['error' => 'forbidden']); return; }
        $capId = trim((string)($this->request->post['cap_id'] ?? '')); if ($capId === '') { $this->reply(['error' => 'cap_id required']); return; }
        $tx = $this->db->query("SELECT * FROM `" . DB_PREFIX . "pumb_credit_transaction` WHERE `cap_id`='" . $this->db->escape($capId) . "' AND `is_test`='" . ($this->config->get('payment_pumb_credit_test_mode') ? 1 : 0) . "' LIMIT 1")->row ?? []; if (!$tx) { $this->reply(['error' => 'transaction not found']); return; }
        $path = '/sf-credits/' . rawurlencode($capId); $method = 'PATCH'; $payload = ['flow' => ['type' => 'DIGITAL_SF']];
        if ($action === 'shipment') $payload += ['method' => 'UPDATE', 'goods_shipped' => true];
        if ($action === 'cancel') $payload += ['method' => 'CLOSE', 'cancel_reason' => 'CancelLead50'];
        if ($action === 'refund') { $agreement = trim((string)$tx['agreement_number']); if ($agreement === '') { $this->reply(['error' => 'guarantee_letter agreement_number is required before refund']); return; } $path = '/sf-credits'; $method = 'POST'; $payload = ['id' => $capId, 'agreement_number' => $agreement, 'refund' => true, 'amount' => (float)($this->request->post['amount'] ?? 0), 'point_of_sale_code' => (string)$this->config->get('payment_pumb_credit_point_of_sale_code'), 'partner_name' => (string)$this->config->get('payment_pumb_credit_partner_name'), 'flow' => ['type' => 'DIGITAL_SF'], 'external_id' => 'OC-' . (int)$tx['order_id']]; }
        $this->reply($this->api($method, $path, $payload));
    }
    private function api(string $method, string $path, array $payload): array { $token = $this->oauthToken(); if ($token === '') return ['http' => 0, 'error' => 'oauth_failed']; $curl = curl_init(rtrim((string)$this->config->get('payment_pumb_credit_api_base'), '/') . $path); $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'X-Flow-Id: ' . bin2hex(random_bytes(16))], CURLOPT_TIMEOUT => 20]); $raw = curl_exec($curl); $http = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl); return ['http' => $http, 'response' => is_string($raw) ? (json_decode($raw, true) ?: []) : []]; }
    private function oauthToken(): string { $cache = rtrim(defined('DIR_CACHE') ? DIR_CACHE : sys_get_temp_dir(), '/\\') . '/pumb_credit_token_' . ($this->config->get('payment_pumb_credit_test_mode') ? 'test' : 'prod') . '.json'; $cached = is_file($cache) ? json_decode((string)file_get_contents($cache), true) : null; if (is_array($cached) && (int)($cached['expires_at'] ?? 0) > time() + 15 && !empty($cached['access_token'])) return (string)$cached['access_token']; $body = http_build_query(['client_id' => 'EXT_OIC', 'username' => (string)$this->config->get('payment_pumb_credit_oauth_username'), 'password' => (string)$this->config->get('payment_pumb_credit_oauth_password'), 'grant_type' => 'password']); $curl = curl_init((string)$this->config->get('payment_pumb_credit_oauth_url')); curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'], CURLOPT_TIMEOUT => 20]); $raw = curl_exec($curl); $http = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl); $json = is_string($raw) ? (json_decode($raw, true) ?: []) : []; if ($http !== 200 || empty($json['access_token'])) return ''; $record = ['access_token' => (string)$json['access_token'], 'expires_at' => time() + max(1, (int)($json['expires_in'] ?? 300) - 15)]; @file_put_contents($cache, json_encode($record)); return $record['access_token']; }
    private function validate(): bool { if (!$this->user->hasPermission('modify', 'extension/pumb_credit/payment/pumb_credit')) $this->error['warning'] = $this->language->get('error_permission'); return !$this->error; }
    private function reply(array $json): void { $this->response->addHeader('Content-Type: application/json'); $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE)); }
}
PHP;
$files['admin/view/template/payment/pumb_credit.twig'] = <<<'TWIG'
{{ header }}{{ column_left }}
<div id="content" class="container-fluid"><div class="page-header"><div class="container-fluid"><h1>{{ heading_title }}</h1></div></div><div class="container-fluid">
<div class="alert alert-warning">PAY-002 skeleton. Не вмикайте метод і не вводьте production credentials до owner QA та дозволу банку.</div>
<form id="form-payment" action="{{ action }}" method="post"><div class="card"><div class="card-body">
<div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="payment_pumb_credit_status" value="1"{% if payment_pumb_credit_status %} checked{% endif %}><label class="form-check-label">Увімкнути метод (залишити вимкненим)</label></div>
<div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="payment_pumb_credit_test_mode" value="1"{% if payment_pumb_credit_test_mode %} checked{% endif %}><label class="form-check-label">Test contour</label></div>
{% for field, label in {'oauth_url':'OAuth URL','api_base':'API base URL','oauth_username':'OAuth username','oauth_password':'OAuth password','point_of_sale_code':'Point of sale code','partner_name':'Partner name','callback_user':'Production callback Basic user','callback_password':'Production callback Basic password','callback_ips':'Production callback IP allowlist (CSV)','test_callback_user':'Test callback Basic user','test_callback_password':'Test callback Basic password','test_callback_ips':'Test callback IP allowlist (CSV)','min_total':'Minimum amount','max_total':'Maximum amount (empty until bank confirms)','term':'Term'} %}
<div class="mb-3"><label class="form-label">{{ label }}</label><input {% if field in ['oauth_password','callback_password','test_callback_password'] %}type="password"{% else %}type="text"{% endif %} class="form-control" name="payment_pumb_credit_{{ field }}" value="{{ attribute(_context, 'payment_pumb_credit_' ~ field) }}"{% if field in ['oauth_password','callback_password','test_callback_password'] %} autocomplete="new-password"{% endif %}></div>{% endfor %}
<input type="hidden" name="payment_pumb_credit_sort_order" value="{{ payment_pumb_credit_sort_order }}"><button type="submit" class="btn btn-primary">Зберегти</button> <a href="{{ cancel }}" class="btn btn-light">Скасувати</a>
</div></div></form>
<div class="card mt-3"><div class="card-body"><h5>Manual lifecycle actions</h5><p class="text-muted">Лише після банківського тесту; ці дії не вмикають checkout method.</p>{% for url,label in {shipment_confirm_url:'Підтвердити видачу',cancel_url:'Скасувати заявку',refund_url:'Повернення'} %}<form class="d-inline-block me-2" method="post" action="{{ attribute(_context, url) }}"><input name="cap_id" placeholder="cap_id" required>{% if label == 'Повернення' %}<input name="amount" placeholder="amount" required>{% endif %}<button class="btn btn-outline-secondary btn-sm">{{ label }}</button></form>{% endfor %}</div></div>
</div></div>{{ footer }}
TWIG;

$backup = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
ensure(mkdir($backup . '/files', 0755, true) || is_dir($backup . '/files'), 'Cannot create backup directory.');
save($backup . '/files/extension/mono_chast/catalog/controller/payment/mono_chast.php', $monoSource);

$statusRows = sqlRows($db, "SELECT * FROM `{$prefix}order_status` WHERE `name` LIKE 'ПЧ mono — %' ORDER BY `order_status_id`");
$order20 = sqlRows($db, "SELECT `order_id`,`order_status_id` FROM `{$prefix}order` WHERE `order_status_id`=20 ORDER BY `order_id`");
$history20 = sqlRows($db, "SELECT `order_history_id`,`order_status_id` FROM `{$prefix}order_history` WHERE `order_status_id`=20 ORDER BY `order_history_id`");
$extensionRows = sqlRows($db, "SELECT * FROM `{$prefix}extension` WHERE `extension`='pumb_credit'");
$installRows = sqlRows($db, "SELECT * FROM `{$prefix}extension_install` WHERE `code`='pumb_credit'");
$settingRows = sqlRows($db, "SELECT * FROM `{$prefix}setting` WHERE `code` IN ('payment_pumb_credit','payment_mono_chast') AND (`code`='payment_pumb_credit' OR `key` LIKE 'payment_mono_chast_status_%') ORDER BY `setting_id`");
$groupRows = sqlRows($db, "SELECT * FROM `{$prefix}user_group` WHERE `user_group_id`=1 LIMIT 1");
save($backup . '/db-before.json', json_encode(['order_status' => $statusRows, 'order_status_20_orders' => $order20, 'order_status_20_history' => $history20, 'extension' => $extensionRows, 'extension_install' => $installRows, 'settings' => $settingRows, 'administrator_group' => $groupRows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);

$rollback = "-- PAY-002 rollback generated " . date('c') . ". Disable payment_pumb_credit first.\n";
$rollback .= "DELETE FROM `{$prefix}extension_path` WHERE `extension_install_id` IN (SELECT `extension_install_id` FROM `{$prefix}extension_install` WHERE `code`='pumb_credit');\nDELETE FROM `{$prefix}extension_install` WHERE `code`='pumb_credit';\nDELETE FROM `{$prefix}extension` WHERE `extension`='pumb_credit' AND `type`='payment' AND `code`='pumb_credit';\nDELETE FROM `{$prefix}setting` WHERE `code`='payment_pumb_credit';\nDELETE FROM `{$prefix}setting` WHERE `code`='payment_mono_chast' AND `key` LIKE 'payment_mono_chast_status_%';\n";
foreach ($settingRows as $row) $rollback .= sqlInsert($prefix . 'setting', $row, $db);
foreach ($statusRows as $row) {
    if ((int)$row['order_status_id'] === 20) $rollback .= sqlInsert($prefix . 'order_status', $row, $db);
    else $rollback .= "UPDATE `{$prefix}order_status` SET `name`=" . literal($db, (string)$row['name']) . " WHERE `order_status_id`=" . (int)$row['order_status_id'] . ";\n";
}
foreach ($order20 as $row) $rollback .= "UPDATE `{$prefix}order` SET `order_status_id`=20 WHERE `order_id`=" . (int)$row['order_id'] . ";\n";
foreach ($history20 as $row) $rollback .= "UPDATE `{$prefix}order_history` SET `order_status_id`=20 WHERE `order_history_id`=" . (int)$row['order_history_id'] . ";\n";
foreach ($groupRows as $row) $rollback .= "UPDATE `{$prefix}user_group` SET `permission`=" . literal($db, (string)$row['permission']) . " WHERE `user_group_id`=1;\n";
save($backup . '/rollback.sql', $rollback);

$written = [];
try {
    foreach ($files as $relative => $contents) { save($extensionRoot . '/' . $relative, $contents . "\n"); $written[] = 'extension/pumb_credit/' . $relative; }
    save($marker, "PAY-002 pumb_credit skeleton installed " . date('c') . "\n"); $written[] = 'extension/pumb_credit/.pay002-marker';
    save($monoController, $monoPatched); $written[] = 'extension/mono_chast/catalog/controller/payment/mono_chast.php';
    foreach (array_merge([$monoController], array_map(static fn(string $path): string => $extensionRoot . '/' . $path, array_keys($files))) as $path) { exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path), $lint, $exit); if ($exit !== 0) throw new RuntimeException('php -l failed: ' . $path); }

    query($db, "CREATE TABLE IF NOT EXISTS `{$prefix}pumb_credit_transaction` (`pumb_credit_transaction_id` int(11) NOT NULL AUTO_INCREMENT,`order_id` int(11) NOT NULL DEFAULT 0,`store_order_id` varchar(64) NOT NULL,`cap_id` varchar(64) NOT NULL,`state` varchar(64) NOT NULL DEFAULT '',`is_test` tinyint(1) NOT NULL DEFAULT 0,`guarantee_letter` mediumtext NULL,`agreement_number` varchar(128) DEFAULT NULL,`payload` mediumtext NULL,`date_added` datetime NOT NULL,`date_modified` datetime NOT NULL,PRIMARY KEY (`pumb_credit_transaction_id`),UNIQUE KEY `cap_id_is_test` (`cap_id`,`is_test`),UNIQUE KEY `store_order_is_test` (`store_order_id`,`is_test`),KEY `order_id` (`order_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $statusIds = [];
    foreach ($oldStatuses as $old => $new) { $row = sqlRows($db, "SELECT `order_status_id` FROM `{$prefix}order_status` WHERE `name`=" . literal($db, $old) . ' LIMIT 1'); $statusIds[$old] = (int)$row[0]['order_status_id']; }
    query($db, "UPDATE `{$prefix}order` SET `order_status_id`=" . $statusIds['ПЧ mono — активна'] . " WHERE `order_status_id`=" . $statusIds['ПЧ mono — завершена']);
    query($db, "UPDATE `{$prefix}order_history` SET `order_status_id`=" . $statusIds['ПЧ mono — активна'] . " WHERE `order_status_id`=" . $statusIds['ПЧ mono — завершена']);
    foreach ($oldStatuses as $old => $new) { if ($old === 'ПЧ mono — завершена') continue; query($db, "UPDATE `{$prefix}order_status` SET `name`=" . literal($db, $new) . " WHERE `order_status_id`=" . $statusIds[$old]); }
    query($db, "DELETE FROM `{$prefix}order_status` WHERE `order_status_id`=" . $statusIds['ПЧ mono — завершена']);
    query($db, "INSERT INTO `{$prefix}extension` (`extension`,`type`,`code`) SELECT 'pumb_credit','payment','pumb_credit' WHERE NOT EXISTS (SELECT 1 FROM `{$prefix}extension` WHERE `extension`='pumb_credit' AND `type`='payment' AND `code`='pumb_credit')");
    $install = sqlRows($db, "SELECT `extension_install_id` FROM `{$prefix}extension_install` WHERE `code`='pumb_credit' LIMIT 1");
    if ($install) $installId = (int)$install[0]['extension_install_id']; else { query($db, "INSERT INTO `{$prefix}extension_install` SET `extension_id`=0,`extension_download_id`=0,`name`='ПУМБ Сплачуйте частинами (disabled skeleton)',`description`='PAY-002 disabled PUMB skeleton',`code`='pumb_credit',`version`='0.1.0',`author`='Booster Shop',`link`='',`status`=1,`date_added`=NOW()"); $installId = (int)$db->insert_id; }
    $paths = ['pumb_credit']; $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extensionRoot, FilesystemIterator::SKIP_DOTS)); foreach ($iterator as $item) $paths[] = 'pumb_credit/' . str_replace(DIRECTORY_SEPARATOR, '/', substr($item->getPathname(), strlen($extensionRoot) + 1));
    foreach (array_unique($paths) as $path) { $count = preparedCount($db, "SELECT COUNT(*) FROM `{$prefix}extension_path` WHERE `extension_install_id`='" . $installId . "' AND `path`=?", $path); if ($count === 0) query($db, "INSERT INTO `{$prefix}extension_path` SET `extension_install_id`='{$installId}',`path`=" . literal($db, $path)); }
    $settings = ['status' => '0','test_mode' => '1','oauth_url' => 'https://auth.dts.fuib.com/auth/realms/pumb_ext/protocol/openid-connect/token','api_base' => 'https://api.dts.fuib.com/ext-oic/galadriel/v1','oauth_username' => '','oauth_password' => '','point_of_sale_code' => '1700001669IN020147','partner_name' => 'Boostershop Digital SF Internet','callback_user' => '','callback_password' => '','callback_ips' => '','test_callback_user' => '','test_callback_password' => '','test_callback_ips' => '','min_total' => '500','max_total' => '','term' => '3','sort_order' => '0','status_waiting_client' => (string)$statusIds['ПЧ mono — очікує клієнта'],'status_waiting_store' => (string)$statusIds['ПЧ mono — очікує видачу'],'status_funded' => (string)$statusIds['ПЧ mono — активна'],'status_returned' => (string)$statusIds['ПЧ mono — повернена'],'status_failed' => (string)$statusIds['ПЧ mono — відхилена']];
    query($db, "DELETE FROM `{$prefix}setting` WHERE `code`='payment_pumb_credit'"); foreach ($settings as $key => $value) query($db, "INSERT INTO `{$prefix}setting` SET `store_id`=0,`code`='payment_pumb_credit',`key`=" . literal($db, 'payment_pumb_credit_' . $key) . ',`value`=' . literal($db, $value) . ',`serialized`=0');
    $monoMap = ['waiting_client' => $statusIds['ПЧ mono — очікує клієнта'], 'waiting_store' => $statusIds['ПЧ mono — очікує видачу'], 'active' => $statusIds['ПЧ mono — активна'], 'done' => $statusIds['ПЧ mono — активна'], 'returned' => $statusIds['ПЧ mono — повернена'], 'failed' => $statusIds['ПЧ mono — відхилена']];
    query($db, "DELETE FROM `{$prefix}setting` WHERE `code`='payment_mono_chast' AND `key` LIKE 'payment_mono_chast_status_%'"); foreach ($monoMap as $key => $id) query($db, "INSERT INTO `{$prefix}setting` SET `store_id`=0,`code`='payment_mono_chast',`key`=" . literal($db, 'payment_mono_chast_status_' . $key) . ',`value`=' . literal($db, (string)$id) . ',`serialized`=0');
    $group = $groupRows[0] ?? []; $permissions = json_decode((string)($group['permission'] ?? ''), true); if (!is_array($permissions)) throw new RuntimeException('Administrator permission JSON cannot be decoded.'); foreach (['access','modify'] as $kind) { $permissions[$kind] = array_values(array_unique(array_merge((array)($permissions[$kind] ?? []), ['extension/pumb_credit/payment/pumb_credit']))); } query($db, "UPDATE `{$prefix}user_group` SET `permission`=" . literal($db, json_encode($permissions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . " WHERE `user_group_id`=1");
} catch (Throwable $error) {
    foreach ($written as $relative) { $path = $root . '/' . $relative; if ($relative === 'extension/mono_chast/catalog/controller/payment/mono_chast.php') copy($backup . '/files/' . $relative, $path); else @unlink($path); }
    if (is_dir($extensionRoot)) { $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extensionRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($items as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); } @rmdir($extensionRoot); }
    $db->close();
    fail($error->getMessage() . '; source files restored. If database mutation began, run generated rollback.sql.');
}
$db->close();
out('cwd=' . $root); out('time=' . date('c')); out('backup=' . $backup); foreach ($written as $file) out('changed_file=' . $file); out('php_l=ok'); out('done=ok');
@unlink(__FILE__);
