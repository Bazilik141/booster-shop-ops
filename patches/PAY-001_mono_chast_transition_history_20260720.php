<?php
/**
 * PAY-001 follow-up: append-only transition history for monobank ПЧ.
 * Owner approved creating one new table: ocp5_mono_chast_event (2026-07-20).
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-001_mono_chast_transition_history_20260720';
function note(string $s): void { echo $s . PHP_EOL; }
function fail(string $s): never { fwrite(STDERR, 'ERROR: ' . $s . PHP_EOL); exit(1); }
function sql(mysqli $db, string $query): mysqli_result|bool { $r = $db->query($query); if ($r === false) fail('SQL: ' . $db->error); return $r; }
function replaceOnce(string $body, string $pattern, string $replacement, string $label): string { $count = preg_match_all($pattern, $body); if ($count !== 1) fail($label . ' anchor count=' . $count . ', expected=1'); return (string)preg_replace($pattern, $replacement, $body, 1); }
function save(string $path, string $contents): void { if (file_put_contents($path, $contents) === false) fail('Cannot write ' . $path); }

$root = __DIR__;
$config = $root . '/config.php';
$catalogFile = $root . '/extension/mono_chast/catalog/controller/payment/mono_chast.php';
$adminFile = $root . '/extension/mono_chast/admin/controller/payment/mono_chast.php';
$marker = $root . '/extension/mono_chast/.pay001-history-marker';
note('cwd=' . $root); note('time=' . date('c'));
if (!is_file($config)) fail('config.php is missing; run only from public_html.');
if (!is_file($catalogFile) || !is_file($adminFile)) fail('PAY-001 extension files are missing.');
if (is_file($marker)) { note('already_applied=yes'); exit(0); }
require $config;
foreach (['DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PORT','DB_PREFIX'] as $key) if (!defined($key)) fail($key . ' missing from config.php.');
if (DB_PREFIX !== 'ocp5_') fail('Unexpected DB_PREFIX; expected ocp5_.');

$catalog = (string)file_get_contents($catalogFile);
$admin = (string)file_get_contents($adminFile);
$catalog = replaceOnce($catalog, '~        \$raw = \(string\)file_get_contents\(\'php://input\'\);\n~', "        \$raw = (string)file_get_contents('php://input');\n        \$traceId = (string)(\$this->request->server['HTTP_TRACE_ID'] ?? '');\n", 'callback trace');
$catalog = replaceOnce($catalog, '~\$this->storeEvent\(\$orderId, \'OC-\' \. \$orderId, \'\', \'CREATE_FAILED\', \'\', \$response\);~', "\$this->storeEvent(\$orderId, 'OC-' . \$orderId, '', 'CREATE_FAILED', '', \$response, 0, 'create', (int)(\$response['http'] ?? 0));", 'create failure');
$catalog = replaceOnce($catalog, '~\$this->storeEvent\(\$orderId, \'OC-\' \. \$orderId, \(string\)\$response\[\'body\'\]\[\'order_id\'\], \'IN_PROCESS\', \'WAITING_FOR_CLIENT\', \$response, \(int\)\$match\[1\]\);~', "\$this->storeEvent(\$orderId, 'OC-' . \$orderId, (string)\$response['body']['order_id'], 'IN_PROCESS', 'WAITING_FOR_CLIENT', \$response, (int)\$match[1], 'create', (int)(\$response['http'] ?? 0));", 'create success');
$catalog = replaceOnce($catalog, '~        \$this->storeEvent\(\(int\)\$transaction\[\'order_id\'\], \(string\)\$transaction\[\'store_order_id\'\], \(string\)\$data\[\'order_id\'\], \$state, \$sub, \[\'callback\' => \$data\]\);\n~', "        \$this->storeEvent((int)\$transaction['order_id'], (string)\$transaction['store_order_id'], (string)\$data['order_id'], \$state, \$sub, ['callback' => \$data], 0, 'callback', 200, \$traceId);\n", 'callback event');
$catalog = replaceOnce($catalog, '~        if \(\(\$response\[\'http\'\] \?\? 0\) === 200 && isset\(\$response\[\'body\'\]\[\'state\'\]\)\) \{ \$state = \(string\)\$response\[\'body\'\]\[\'state\'\]; \$sub = \(string\)\(\$response\[\'body\'\]\[\'order_sub_state\'\] \?\? \'\'\); \$this->storeEvent\(\$orderId, \$tx\[\'store_order_id\'\], \$tx\[\'mono_order_id\'\], \$state, \$sub, \$response\); \$this->applyOrderStatus\(\$orderId, \$state, \$sub\); \}\n~', "        if ((\$response['http'] ?? 0) === 200 && isset(\$response['body']['state'])) { \$state = (string)\$response['body']['state']; \$sub = (string)(\$response['body']['order_sub_state'] ?? ''); \$this->storeEvent(\$orderId, \$tx['store_order_id'], \$tx['mono_order_id'], \$state, \$sub, \$response, 0, 'poll', (int)(\$response['http'] ?? 0)); \$this->applyOrderStatus(\$orderId, \$state, \$sub); }\n", 'poll event');
$newStoreEvent = <<<'PHP'
    private function storeEvent(int $order, string $store, string $mono, string $state, string $sub, array $payload, int $parts = 0, string $source = 'unknown', int $httpStatus = 0, string $traceId = ''): void {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (!is_string($payloadJson)) $payloadJson = '{}';
        $traceId = $traceId !== '' ? $traceId : (string)($payload['trace_id'] ?? '');
        $this->db->query("INSERT INTO " . DB_PREFIX . "mono_chast_transaction SET order_id='" . $order . "', store_order_id='" . $this->db->escape($store) . "', mono_order_id=NULLIF('" . $this->db->escape($mono) . "',''), parts_count='" . $parts . "', state='" . $this->db->escape($state) . "', order_sub_state='" . $this->db->escape($sub) . "', trace_id='" . $this->db->escape($traceId) . "', payload='" . $this->db->escape($payloadJson) . "', date_added=NOW(), date_modified=NOW() ON DUPLICATE KEY UPDATE state=VALUES(state), order_sub_state=VALUES(order_sub_state), trace_id=IF(VALUES(trace_id) <> '', VALUES(trace_id), trace_id), payload=VALUES(payload), date_modified=NOW()");
        $transaction = $this->transactionByStoreOrder($store);
        if (!$transaction && $mono !== '') $transaction = $this->transactionByMonoOrder($mono);
        if (!$transaction) return;
        $this->db->query("INSERT INTO " . DB_PREFIX . "mono_chast_event SET mono_chast_transaction_id='" . (int)$transaction['mono_chast_transaction_id'] . "', order_id='" . $order . "', store_order_id='" . $this->db->escape($store) . "', mono_order_id=NULLIF('" . $this->db->escape($mono) . "',''), event_source='" . $this->db->escape($source) . "', state='" . $this->db->escape($state) . "', order_sub_state='" . $this->db->escape($sub) . "', trace_id='" . $this->db->escape($traceId) . "', http_status='" . $httpStatus . "', payload='" . $this->db->escape($payloadJson) . "', date_added=NOW()");
    }
PHP;
$catalog = replaceOnce($catalog, '~    private function storeEvent\(int \$order,.*?\n(?=    private function applyOrderStatus)~s', $newStoreEvent . "\n", 'storeEvent method');

$newAdminBlock = <<<'PHP'
        $traceId = '';
        $curl = curl_init(rtrim((string)$this->config->get('payment_mono_chast_api_base'), '/') . $path);
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'store-id: ' . (string)$this->config->get('payment_mono_chast_store_id'), 'signature: ' . base64_encode(hash_hmac('sha256', $body, $secret, true))], CURLOPT_TIMEOUT => 20, CURLOPT_HEADERFUNCTION => static function($curl, string $header) use (&$traceId): int { if (stripos($header, 'Trace-Id:') === 0) $traceId = trim(substr($header, 9)); return strlen($header); }]);
        $raw = curl_exec($curl); $http = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl); $response = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        if ($http === 200 && isset($response['state'])) {
            $state = $this->db->escape((string)$response['state']); $sub = $this->db->escape((string)($response['order_sub_state'] ?? '')); $trace = $this->db->escape($traceId);
            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "mono_chast_transaction WHERE mono_order_id='" . $this->db->escape($orderId) . "' LIMIT 1"); $transaction = $query->row ?? [];
            $payload = json_encode(['response' => $response, 'trace_id' => $traceId], JSON_UNESCAPED_UNICODE); if (!is_string($payload)) $payload = '{}';
            $this->db->query("UPDATE " . DB_PREFIX . "mono_chast_transaction SET state='{$state}', order_sub_state='{$sub}', trace_id=IF('{$trace}' <> '', '{$trace}', trace_id), payload='" . $this->db->escape($payload) . "', date_modified=NOW() WHERE mono_order_id='" . $this->db->escape($orderId) . "'");
            if ($transaction) {
                $source = $path === '/api/order/confirm' ? 'confirm' : 'reject';
                $targetKey = $source === 'confirm' ? 'active' : 'failed'; $statusId = (int)$this->config->get('payment_mono_chast_status_' . $targetKey);
                $this->db->query("INSERT INTO " . DB_PREFIX . "mono_chast_event SET mono_chast_transaction_id='" . (int)$transaction['mono_chast_transaction_id'] . "', order_id='" . (int)$transaction['order_id'] . "', store_order_id='" . $this->db->escape((string)$transaction['store_order_id']) . "', mono_order_id='" . $this->db->escape($orderId) . "', event_source='" . $source . "', state='{$state}', order_sub_state='{$sub}', trace_id='{$trace}', http_status='{$http}', payload='" . $this->db->escape($payload) . "', date_added=NOW()");
                if ($statusId > 0) { $comment = $this->db->escape('monobank ПЧ: ' . (string)$response['state'] . '/' . (string)($response['order_sub_state'] ?? '')); $this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id='" . (int)$transaction['order_id'] . "', order_status_id='{$statusId}', notify='0', comment='{$comment}', date_added=NOW()"); $this->db->query("UPDATE " . DB_PREFIX . "order SET order_status_id='{$statusId}', date_modified=NOW() WHERE order_id='" . (int)$transaction['order_id'] . "'"); }
            }
        }
        $this->json(['http' => $http, 'response' => $response, 'trace_id' => $traceId]);
PHP;
$admin = replaceOnce($admin, '~        \$curl = curl_init\(.*?\n        \$this->json\(\[\'http\' => \$http, \'response\' => \$response\]\);\n~s', $newAdminBlock . "\n", 'admin confirm/reject');

$stamp = date('Ymd-His');
$backup = $root . '/_patch_backups/' . PATCH_ID . '-' . $stamp;
if (!mkdir($backup, 0755, true) && !is_dir($backup)) fail('Cannot create backup directory.');
if (!copy($catalogFile, $backup . '/catalog-controller.before.php') || !copy($adminFile, $backup . '/admin-controller.before.php')) fail('Cannot back up controllers.');
save($backup . '/rollback.sql', "-- First disable the extension. Restore both controller files from this backup.\n-- Drop this table only when its audit history is no longer needed:\nDROP TABLE IF EXISTS ocp5_mono_chast_event;\n");
note('backup=' . $backup);

save($catalogFile, $catalog); save($adminFile, $admin);
$php = is_executable($root . '/system/bin/php') ? $root . '/system/bin/php' : PHP_BINARY;
foreach ([$catalogFile, $adminFile] as $file) { exec(escapeshellarg($php) . ' -l ' . escapeshellarg($file), $lint, $code); if ($code !== 0) { copy($backup . '/catalog-controller.before.php', $catalogFile); copy($backup . '/admin-controller.before.php', $adminFile); fail('php -l failed; controller files restored.'); } }

mysqli_report(MYSQLI_REPORT_OFF);
$db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
if ($db->connect_errno) { copy($backup . '/catalog-controller.before.php', $catalogFile); copy($backup . '/admin-controller.before.php', $adminFile); fail('Database connection failed; controller files restored.'); }
$db->set_charset('utf8mb4'); $prefix = DB_PREFIX; $eventTable = $prefix . 'mono_chast_event';
$exists = sql($db, "SHOW TABLES LIKE '" . $db->real_escape_string($eventTable) . "'");
if ($exists->num_rows) { $db->close(); copy($backup . '/catalog-controller.before.php', $catalogFile); copy($backup . '/admin-controller.before.php', $adminFile); fail('mono_chast_event already exists without marker; refusing overwrite.'); }
$before = sql($db, "SHOW CREATE TABLE " . $prefix . "mono_chast_transaction")->fetch_assoc();
save($backup . '/mono_chast_transaction.schema.before.sql', (string)($before['Create Table'] ?? '') . ";\n");
sql($db, "CREATE TABLE " . $eventTable . " (mono_chast_event_id int(11) NOT NULL AUTO_INCREMENT, mono_chast_transaction_id int(11) NOT NULL, order_id int(11) NOT NULL, store_order_id varchar(64) NOT NULL, mono_order_id varchar(64) DEFAULT NULL, event_source varchar(24) NOT NULL, state varchar(32) NOT NULL DEFAULT '', order_sub_state varchar(64) NOT NULL DEFAULT '', trace_id varchar(128) NOT NULL DEFAULT '', http_status smallint(6) NOT NULL DEFAULT 0, payload mediumtext, date_added datetime NOT NULL, PRIMARY KEY (mono_chast_event_id), KEY mono_chast_transaction_id (mono_chast_transaction_id), KEY order_id (order_id), KEY mono_order_id (mono_order_id), KEY date_added (date_added)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->close(); save($marker, "PAY-001 transition history installed 2026-07-20\n");
note('changed_files=2'); note('db_table=' . $eventTable); note('php_l=ok'); note('done=ok');
@unlink(__FILE__);
