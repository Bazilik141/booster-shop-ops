<?php
/**
 * PAY-004 — PUMB: forward the customer-selected instalment term safely.
 *
 * Upload this file to ~/public_html and run:
 *   php PAY-004_pumb-customer-selected-term_20260824.php
 *
 * Scope: PUMB extension only. This patch preserves payment_pumb_credit_status=0,
 * changes the setting from one legacy term to a JSON allowed-terms list, adds
 * pumb_credit_transaction.requested_term, and requires an explicit validated
 * term for PUMB confirm(). It does not expose PUMB in the checkout UI.
 *
 * DB change: adds nullable `requested_term` to {DB_PREFIX}pumb_credit_transaction
 * and replaces payment_pumb_credit_term=3 with
 * payment_pumb_credit_terms=[3,4,5]. The added column is intentionally
 * additive and is not dropped during rollback.
 *
 * Rollback: restore the backed-up extension files and use settings-before.json
 * in the backup directory to restore the previous term setting. Keep the new
 * nullable column in place.
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-004_pumb-customer-selected-term_20260824';

function out(string $line): void { echo $line . PHP_EOL; }
function fail(string $message): void { fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL); exit(1); }
function ensure(bool $condition, string $message): void { if (!$condition) fail($message); }
function save(string $path, string $contents): void {
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) fail('Cannot create directory: ' . $dir);
    if (file_put_contents($path, $contents) === false) fail('Cannot write: ' . $path);
}
function writeOrThrow(string $path, string $contents): void {
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Cannot create directory: ' . $dir);
    if (file_put_contents($path, $contents) === false) throw new RuntimeException('Cannot write: ' . $path);
}
function copyToBackup(string $source, string $backup): void {
    $target = $backup . '/' . $source;
    $dir = dirname($target);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) fail('Cannot create backup directory: ' . $dir);
    if (!copy($source, $target)) fail('Cannot back up: ' . $source);
}
function replaceOnce(string $source, string $needle, string $replacement, string $label): string {
    ensure(substr_count($source, $needle) === 1, 'Expected exactly one anchor for ' . $label . '.');
    return str_replace($needle, $replacement, $source);
}
function replaceCount(string $source, string $needle, string $replacement, int $count, string $label): string {
    ensure(substr_count($source, $needle) === $count, 'Expected ' . $count . ' anchors for ' . $label . '.');
    return str_replace($needle, $replacement, $source);
}
function syntaxCheck(string $contents, string $label): void {
    $temp = tempnam(sys_get_temp_dir(), 'pay004-');
    ensure($temp !== false, 'Cannot create syntax-check temporary file for ' . $label . '.');
    file_put_contents($temp, $contents);
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($temp) . ' 2>&1';
    exec($command, $lines, $status);
    @unlink($temp);
    ensure($status === 0, 'php -l failed for generated ' . $label . ': ' . implode(' ', $lines));
    out('php_l_generated=ok file=' . $label);
}
function tableCount(mysqli $db, string $table): int {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    ensure($stmt !== false, 'Cannot prepare table preflight: ' . $db->error);
    $stmt->bind_param('s', $table);
    ensure($stmt->execute(), 'Cannot execute table preflight: ' . $stmt->error);
    $stmt->bind_result($count);
    ensure($stmt->fetch(), 'Table preflight returned no result.');
    $stmt->close();
    return (int)$count;
}
function columnCount(mysqli $db, string $table, string $column): int {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    ensure($stmt !== false, 'Cannot prepare column preflight: ' . $db->error);
    $stmt->bind_param('ss', $table, $column);
    ensure($stmt->execute(), 'Cannot execute column preflight: ' . $stmt->error);
    $stmt->bind_result($count);
    ensure($stmt->fetch(), 'Column preflight returned no result.');
    $stmt->close();
    return (int)$count;
}
/** @return list<array{setting_id:int,value:string,serialized:int}> */
function settingRows(mysqli $db, string $prefix, string $key): array {
    $sql = 'SELECT `setting_id`,`value`,`serialized` FROM `' . $prefix . 'setting` WHERE `store_id`=0 AND `code`=? AND `key`=? ORDER BY `setting_id`';
    $stmt = $db->prepare($sql);
    ensure($stmt !== false, 'Cannot prepare settings preflight: ' . $db->error);
    $code = 'payment_pumb_credit';
    $stmt->bind_param('ss', $code, $key);
    ensure($stmt->execute(), 'Cannot execute settings preflight: ' . $stmt->error);
    $stmt->bind_result($id, $value, $serialized);
    $rows = [];
    while ($stmt->fetch()) $rows[] = ['setting_id' => (int)$id, 'value' => (string)$value, 'serialized' => (int)$serialized];
    $stmt->close();
    return $rows;
}
function settingValue(mysqli $db, string $prefix, string $key): string {
    $rows = settingRows($db, $prefix, $key);
    ensure(count($rows) === 1, 'Expected exactly one setting row for ' . $key . '.');
    return $rows[0]['value'];
}
function pumbStatusIsDisabled(mysqli $db, string $prefix): bool {
    $rows = settingRows($db, $prefix, 'payment_pumb_credit_status');
    ensure(count($rows) <= 1, 'Expected at most one setting row for payment_pumb_credit_status.');
    return !$rows || $rows[0]['value'] === '0';
}
function runSql(mysqli $db, string $sql, string $label): void {
    if ($db->query($sql) === false) throw new RuntimeException($label . ': ' . $db->error);
}

$root = getcwd() ?: '.';
$config = $root . '/config.php';
ensure(is_file($config), 'Run this patch from OpenCart public_html (config.php not found).');
require_once $config;
ensure(defined('DB_PREFIX') && defined('DB_HOSTNAME') && defined('DB_USERNAME') && defined('DB_PASSWORD') && defined('DB_DATABASE') && defined('DB_PORT'), 'OpenCart database constants are unavailable.');
ensure(preg_match('/^[A-Za-z0-9_]+$/', DB_PREFIX) === 1, 'Unexpected database table prefix.');

$extensionRoot = $root . '/extension/pumb_credit';
$marker = $extensionRoot . '/.pay004-marker';
$files = [
    'extension/pumb_credit/catalog/controller/payment/pumb_credit.php',
    'extension/pumb_credit/admin/controller/payment/pumb_credit.php',
    'extension/pumb_credit/admin/view/template/payment/pumb_credit.twig',
    'extension/pumb_credit/catalog/language/uk-ua/payment/pumb_credit.php',
    'extension/pumb_credit/admin/language/uk-ua/payment/pumb_credit.php',
];
foreach ($files as $relative) ensure(is_file($root . '/' . $relative), 'Required live file is missing: ' . $relative);

mysqli_report(MYSQLI_REPORT_OFF);
$db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
ensure(!$db->connect_errno, 'Database connection failed before changes.');
$db->set_charset('utf8mb4');
$prefix = DB_PREFIX;
$transactionTable = $prefix . 'pumb_credit_transaction';
ensure(tableCount($db, $prefix . 'setting') === 1, 'Required settings table is missing.');
ensure(tableCount($db, $transactionTable) === 1, 'Required PUMB transaction table is missing.');
ensure(pumbStatusIsDisabled($db, $prefix), 'PUMB must remain disabled; payment_pumb_credit_status is present and non-zero.');

if (is_file($marker)) {
    ensure(columnCount($db, $transactionTable, 'requested_term') === 1, 'PAY-004 marker exists but requested_term is missing.');
    $termsRows = settingRows($db, $prefix, 'payment_pumb_credit_terms');
    ensure(count($termsRows) === 1 && $termsRows[0]['value'] === '[3,4,5]', 'PAY-004 marker exists but allowed terms setting is not [3,4,5].');
    $db->close();
    out('already_applied=yes');
    exit(0);
}

$catalogPath = $root . '/' . $files[0];
$adminPath = $root . '/' . $files[1];
$twigPath = $root . '/' . $files[2];
$catalogLanguagePath = $root . '/' . $files[3];
$adminLanguagePath = $root . '/' . $files[4];
$catalog = file_get_contents($catalogPath);
$admin = file_get_contents($adminPath);
$twig = file_get_contents($twigPath);
$catalogLanguage = file_get_contents($catalogLanguagePath);
$adminLanguage = file_get_contents($adminLanguagePath);
ensure(is_string($catalog) && is_string($admin) && is_string($twig) && is_string($catalogLanguage) && is_string($adminLanguage), 'Cannot read one or more PUMB extension files.');

$catalog = replaceOnce(
    $catalog,
    "        \$isTest = \$this->isTestMode() ? 1 : 0;\n\n        \$existing = \$this->transactionByOrder(\$orderId, \$isTest);",
    "        \$term = \$this->requestedTerm();\n        if (\$term === null) { \$this->reply(['error' => \$this->language->get('error_term')]); return; }\n        \$isTest = \$this->isTestMode() ? 1 : 0;\n\n        \$existing = \$this->transactionByOrder(\$orderId, \$isTest);",
    'catalog explicit-term validation'
);
$catalog = replaceOnce(
    $catalog,
    "        \$response = \$this->api('POST', self::API_CREATE, \$this->createPayload(\$order));",
    "        \$payload = \$this->createPayload(\$order, \$term);\n        \$response = \$this->api('POST', self::API_CREATE, \$payload);",
    'catalog payload call'
);
$catalog = replaceCount(
    $catalog,
    "['create' => \$response]",
    "['create' => ['request' => \$payload, 'response' => \$response]]",
    2,
    'catalog create payload recording'
);
$catalog = replaceOnce(
    $catalog,
    "                ['create' => ['request' => \$payload, 'response' => \$response]],\n                null\n            );",
    "                ['create' => ['request' => \$payload, 'response' => \$response]],\n                null,\n                \$term\n            );",
    'catalog failed create requested-term persistence'
);
$catalog = replaceOnce(
    $catalog,
    "        \$this->upsertTransaction(\$orderId, 'OC-' . \$orderId, \$capId, 'WAITING_CLIENT', \$isTest, ['create' => ['request' => \$payload, 'response' => \$response]], null);",
    "        \$this->upsertTransaction(\$orderId, 'OC-' . \$orderId, \$capId, 'WAITING_CLIENT', \$isTest, ['create' => ['request' => \$payload, 'response' => \$response]], null, \$term);",
    'catalog successful create requested-term persistence'
);
$catalog = replaceOnce(
    $catalog,
    "    private function createPayload(array \$order): array {",
    <<<'PHP'
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
PHP,
    'catalog createPayload signature'
);
$catalog = replaceOnce(
    $catalog,
    "'credit_request' => ['term' => (int)\$this->config->get('payment_pumb_credit_term'), 'amount' => \$total]",
    "'credit_request' => ['term' => \$term, 'amount' => \$total]",
    'catalog config-term removal'
);
$catalog = replaceOnce(
    $catalog,
    "    private function upsertTransaction(int \$orderId, string \$storeOrder, string \$capId, string \$state, int \$isTest, array \$payload, mixed \$letter): void {\n        \$payloadJson = json_encode(\$payload, JSON_UNESCAPED_UNICODE);",
    <<<'PHP'
    private function upsertTransaction(int $orderId, string $storeOrder, string $capId, string $state, int $isTest, array $payload, mixed $letter, ?int $term = null): void {
        $existing = $this->transactionByCap($capId, $isTest);
        $previous = $existing ? json_decode((string)($existing['payload'] ?? ''), true) : null;
        if (is_array($previous) && isset($previous['create']['request']) && !isset($payload['create']['request'])) $payload['create'] = $previous['create'];
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
PHP,
    'catalog transaction signature and payload preservation'
);
$catalog = replaceOnce(
    $catalog,
    <<<'PHP'
`is_test`='" . $isTest . "',`guarantee_letter`=
PHP,
    <<<'PHP'
`is_test`='" . $isTest . "',`requested_term`=" . ($term === null ? 'NULL' : "'" . (int)$term . "'") . ",`guarantee_letter`=
PHP,
    'catalog requested-term insert'
);
$catalog = replaceOnce(
    $catalog,
    "`state`=VALUES(`state`),`guarantee_letter`=COALESCE(VALUES(`guarantee_letter`),`guarantee_letter`),",
    "`state`=VALUES(`state`),`requested_term`=COALESCE(VALUES(`requested_term`),`requested_term`),`guarantee_letter`=COALESCE(VALUES(`guarantee_letter`),`guarantee_letter`),",
    'catalog requested-term update'
);

$admin = replaceOnce(
    $admin,
    "if (\$this->request->server['REQUEST_METHOD'] === 'POST' && \$this->validate())",
    "if (\$this->request->server['REQUEST_METHOD'] === 'POST' && \$this->validate() && \$this->validateTerms())",
    'admin allowed-terms validation gate'
);
$admin = replaceOnce($admin, "'max_total','term','sort_order'", "'max_total','terms','sort_order'", 'admin allowed-terms key');
$admin = replaceOnce(
    $admin,
    "    private function validate(): bool {",
    <<<'PHP'
    private function validateTerms(): bool {
        $raw = (string)($this->request->post['payment_pumb_credit_terms'] ?? '');
        $terms = json_decode($raw, true);
        $shopTerms = [3, 4, 5];
        if (!is_array($terms) || !$terms) { $this->error['warning'] = $this->language->get('error_terms'); return false; }
        $normalized = array_values(array_unique(array_map('intval', $terms)));
        sort($normalized);
        foreach ($normalized as $term) if (!in_array($term, $shopTerms, true)) { $this->error['warning'] = $this->language->get('error_terms'); return false; }
        $this->request->post['payment_pumb_credit_terms'] = json_encode($normalized);
        return true;
    }
    private function validate(): bool {
PHP,
    'admin allowed-terms validator'
);
$twig = replaceOnce($twig, "'max_total':'Maximum amount (empty until bank confirms)','term':'Term'", "'max_total':'Maximum amount (empty until bank confirms)','terms':'Allowed terms (JSON: [3,4,5])'", 'admin template allowed-terms label');
$catalogLanguage = replaceOnce($catalogLanguage, <<<'PHP'
$_['error_phone'] = 'Потрібен коректний номер телефону у форматі +380XXXXXXXXX.';
PHP, <<<'PHP'
$_['error_phone'] = 'Потрібен коректний номер телефону у форматі +380XXXXXXXXX.';
$_['error_term'] = 'Оберіть доступну кількість платежів ПУМБ.';
PHP, 'catalog invalid-term language');
$adminLanguage = replaceOnce($adminLanguage, <<<'PHP'
$_['error_permission'] = 'Немає прав для зміни налаштувань.';
PHP, <<<'PHP'
$_['error_permission'] = 'Немає прав для зміни налаштувань.';
$_['error_terms'] = 'Вкажіть JSON-масив дозволених термінів лише з 3, 4 або 5.';
PHP, 'admin allowed-terms language');

syntaxCheck($catalog, $files[0]);
syntaxCheck($admin, $files[1]);
syntaxCheck($catalogLanguage, $files[3]);
syntaxCheck($adminLanguage, $files[4]);

$legacyRows = settingRows($db, $prefix, 'payment_pumb_credit_term');
$termsRows = settingRows($db, $prefix, 'payment_pumb_credit_terms');
ensure(count($legacyRows) === 1, 'Expected exactly one legacy payment_pumb_credit_term setting row.');
ensure(count($termsRows) === 0, 'payment_pumb_credit_terms already exists without a PAY-004 marker; refusing an ambiguous migration.');
ensure(in_array($legacyRows[0]['value'], ['3', '4', '5'], true), 'Legacy PUMB term must be 3, 4, or 5.');
ensure(columnCount($db, $transactionTable, 'requested_term') === 0, 'requested_term already exists without a PAY-004 marker; refusing an ambiguous migration.');

$stamp = date('Ymd-His');
$backup = $root . '/_patch_backups/' . PATCH_ID . '-' . $stamp;
foreach ($files as $relative) copyToBackup($relative, $backup);
save($backup . '/settings-before.json', json_encode(['payment_pumb_credit_term' => $legacyRows, 'payment_pumb_credit_terms' => $termsRows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
out('cwd=' . $root);
out('time=' . date('c'));
out('backup=' . $backup);

try {
    writeOrThrow($catalogPath, $catalog);
    writeOrThrow($adminPath, $admin);
    writeOrThrow($twigPath, $twig);
    writeOrThrow($catalogLanguagePath, $catalogLanguage);
    writeOrThrow($adminLanguagePath, $adminLanguage);
    foreach ([$catalogPath, $adminPath, $catalogLanguagePath, $adminLanguagePath] as $path) {
        $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1';
        $lines = [];
        exec($command, $lines, $status);
        if ($status !== 0) throw new RuntimeException('php -l failed after writing ' . $path . ': ' . implode(' ', $lines));
        out('php_l_written=ok file=' . substr($path, strlen($root) + 1));
    }

    runSql($db, 'ALTER TABLE `' . $transactionTable . '` ADD COLUMN `requested_term` tinyint unsigned NULL DEFAULT NULL AFTER `is_test`', 'Cannot add requested_term column');
    if (!$db->begin_transaction()) throw new RuntimeException('Cannot begin allowed-terms migration: ' . $db->error);
    $delete = $db->prepare('DELETE FROM `' . $prefix . 'setting` WHERE `store_id`=0 AND `code`=? AND `key`=?');
    if ($delete === false) throw new RuntimeException('Cannot prepare legacy setting deletion: ' . $db->error);
    $code = 'payment_pumb_credit';
    $legacyKey = 'payment_pumb_credit_term';
    $delete->bind_param('ss', $code, $legacyKey);
    if (!$delete->execute()) throw new RuntimeException('Cannot delete legacy term setting: ' . $delete->error);
    $delete->close();
    $insert = $db->prepare('INSERT INTO `' . $prefix . 'setting` (`store_id`,`code`,`key`,`value`,`serialized`) VALUES (0,?,?,?,0)');
    if ($insert === false) throw new RuntimeException('Cannot prepare allowed-terms setting insertion: ' . $db->error);
    $termsKey = 'payment_pumb_credit_terms';
    $termsValue = '[3,4,5]';
    $insert->bind_param('sss', $code, $termsKey, $termsValue);
    if (!$insert->execute()) throw new RuntimeException('Cannot insert allowed-terms setting: ' . $insert->error);
    $insert->close();
    if (!$db->commit()) throw new RuntimeException('Cannot commit allowed-terms migration: ' . $db->error);
    if (columnCount($db, $transactionTable, 'requested_term') !== 1) throw new RuntimeException('requested_term column verification failed.');
    if (settingValue($db, $prefix, 'payment_pumb_credit_terms') !== '[3,4,5]') throw new RuntimeException('Allowed-terms setting verification failed.');
    if (count(settingRows($db, $prefix, 'payment_pumb_credit_term')) !== 0) throw new RuntimeException('Legacy term setting was not removed.');
    if (!pumbStatusIsDisabled($db, $prefix)) throw new RuntimeException('PUMB status changed unexpectedly.');
    writeOrThrow($marker, 'PAY-004 applied ' . date('c') . PHP_EOL);
} catch (Throwable $exception) {
    foreach ($files as $relative) {
        $saved = $backup . '/' . $relative;
        if (is_file($saved)) @copy($saved, $root . '/' . $relative);
    }
    $db->rollback();
    $db->close();
    fail('Patch stopped and source files were restored. Additive requested_term may remain if ALTER TABLE completed: ' . $exception->getMessage());
}
$db->close();

out('changed=extension/pumb_credit/catalog/controller/payment/pumb_credit.php');
out('changed=extension/pumb_credit/admin/controller/payment/pumb_credit.php');
out('changed=extension/pumb_credit/admin/view/template/payment/pumb_credit.twig');
out('changed=extension/pumb_credit/catalog/language/uk-ua/payment/pumb_credit.php');
out('changed=extension/pumb_credit/admin/language/uk-ua/payment/pumb_credit.php');
out('db_column=requested_term');
out('setting=payment_pumb_credit_terms:[3,4,5]');
out('payment_pumb_credit_status=0');
if (!@unlink(__FILE__)) out('self_delete=failed remove_uploaded_patch_manually=yes');
else out('self_delete=ok');
out('done=ok');
