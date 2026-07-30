<?php
/**
 * PAY-002 PUMB confirm idempotency guard
 *
 * Scope: make PUMB credit creation durable and idempotent per order + is_test.
 * DB schema is not changed. The existing unique store_order_is_test index is
 * preflighted and used as the durable reservation primitive.
 *
 * Rollback: restore the backed-up controller from _patch_backups/<patch>-<ts>/.
 * No SQL rollback is needed because this runner does not mutate schema or data.
 */

declare(strict_types=1);

const PAY002_PATCH = 'PAY-002_confirm-idempotency-guard_20260729';
const PAY002_TARGET = 'extension/pumb_credit/catalog/controller/payment/pumb_credit.php';
const PAY002_MARKER = 'extension/pumb_credit/.pay002-confirm-idempotency-marker';

function pay002_fail(string $message): void {
    fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL);
    exit(1);
}

function pay002_count(string $source, string $needle): int {
    return substr_count($source, $needle);
}

/** @return array{start:int,end:int,body:string} */
function pay002_method(string $source, string $signature): array {
    if (pay002_count($source, $signature) !== 1) {
        throw new RuntimeException('Expected method anchor exactly once: ' . $signature);
    }

    $start = strpos($source, $signature);
    $brace = strpos($source, '{', $start);
    if ($start === false || $brace === false) {
        throw new RuntimeException('Unable to locate method body: ' . $signature);
    }

    $depth = 0;
    $length = strlen($source);
    for ($i = $brace; $i < $length; $i++) {
        if ($source[$i] === '{') {
            $depth++;
        } elseif ($source[$i] === '}') {
            $depth--;
            if ($depth === 0) {
                return ['start' => $start, 'end' => $i + 1, 'body' => substr($source, $start, $i - $start + 1)];
            }
        }
    }

    throw new RuntimeException('Unbalanced method body: ' . $signature);
}

function pay002_replace(string $source, array $method, string $replacement): string {
    return substr($source, 0, $method['start']) . $replacement . substr($source, $method['end']);
}

function pay002_lint(string $target): void {
    $output = [];
    $exit = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($target) . ' 2>&1', $output, $exit);
    if ($exit !== 0) {
        throw new RuntimeException('php_l_failed: ' . implode(' | ', $output));
    }
}

function pay002_stmt_string(mysqli_stmt $statement): string {
    $statement->execute();
    $value = '';
    $statement->bind_result($value);
    $statement->fetch();
    $statement->close();
    return (string)$value;
}

function pay002_db_preflight(): void {
    if (!is_file('config.php')) {
        throw new RuntimeException('config.php was not found; run from OpenCart public_html.');
    }

    require_once 'config.php';
    foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PORT', 'DB_PREFIX'] as $constant) {
        if (!defined($constant)) {
            throw new RuntimeException('Missing database constant: ' . $constant);
        }
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', (string)DB_PREFIX)) {
        throw new RuntimeException('DB_PREFIX has unexpected characters.');
    }

    $mysqli = mysqli_init();
    if (!$mysqli || !mysqli_real_connect($mysqli, (string)DB_HOSTNAME, (string)DB_USERNAME, (string)DB_PASSWORD, (string)DB_DATABASE, (int)DB_PORT)) {
        throw new RuntimeException('Database preflight connection failed.');
    }
    mysqli_set_charset($mysqli, 'utf8mb4');

    $table = (string)DB_PREFIX . 'pumb_credit_transaction';
    $statement = $mysqli->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    if (!$statement) {
        throw new RuntimeException('Unable to prepare table preflight.');
    }
    $statement->bind_param('s', $table);
    if (pay002_stmt_string($statement) !== '1') {
        throw new RuntimeException('Missing required table: ' . $table);
    }

    $requiredColumns = ['pumb_credit_transaction_id', 'order_id', 'store_order_id', 'cap_id', 'state', 'is_test', 'payload', 'date_added', 'date_modified'];
    foreach ($requiredColumns as $column) {
        $statement = $mysqli->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        if (!$statement) {
            throw new RuntimeException('Unable to prepare column preflight.');
        }
        $statement->bind_param('ss', $table, $column);
        if (pay002_stmt_string($statement) !== '1') {
            throw new RuntimeException('Missing required column: ' . $column);
        }
    }

    $index = 'store_order_is_test';
    $statement = $mysqli->prepare("SELECT COALESCE(GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ','), '') FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?");
    if (!$statement) {
        throw new RuntimeException('Unable to prepare unique-index preflight.');
    }
    $statement->bind_param('ss', $table, $index);
    if (pay002_stmt_string($statement) !== 'store_order_id,is_test') {
        throw new RuntimeException('Expected unique store_order_is_test(store_order_id, is_test) index is missing or malformed.');
    }

    $statement = $mysqli->prepare('SELECT non_unique FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1');
    if (!$statement) {
        throw new RuntimeException('Unable to prepare index-uniqueness preflight.');
    }
    $statement->bind_param('ss', $table, $index);
    if (pay002_stmt_string($statement) !== '0') {
        throw new RuntimeException('store_order_is_test must be unique.');
    }

    $mysqli->close();
    echo "db_preflight=ok\n";
}

$target = getcwd() . DIRECTORY_SEPARATOR . PAY002_TARGET;
$marker = getcwd() . DIRECTORY_SEPARATOR . PAY002_MARKER;
$backupDir = '';
$sourceWritten = false;

try {
    if (!is_file($target)) {
        throw new RuntimeException('Target file not found: ' . PAY002_TARGET);
    }

    $source = file_get_contents($target);
    if ($source === false) {
        throw new RuntimeException('Unable to read target file.');
    }

    if (is_file($marker)) {
        if (pay002_count($source, 'private function reserveCreate(int $orderId, int $isTest): array') === 1
            && pay002_count($source, "'PENDING-OC-' . $orderId") >= 2
            && pay002_count($source, '`cap_id`=VALUES(`cap_id`)') === 1) {
            echo "already_applied=yes\n";
            exit(0);
        }
        throw new RuntimeException('Idempotency marker exists but controller shape is inconsistent; no write performed.');
    }

    if (pay002_count($source, "private const API_CREATE = '/sf-credits';") !== 1
        || pay002_count($source, 'private function transactionByOrder(int $orderId, int $isTest): array') !== 1
        || pay002_count($source, 'private function upsertTransaction(') !== 1) {
        throw new RuntimeException('Unexpected PUMB controller anchors; no write performed.');
    }

    $confirm = pay002_method($source, 'public function confirm(): void');
    if (strpos($confirm['body'], 'self::API_CREATE') === false || strpos($confirm['body'], '$this->upsertTransaction(') === false) {
        throw new RuntimeException('confirm() does not match the expected pre-guard shape.');
    }
    if (pay002_count($source, 'private function handleCallback(bool $isTest): void') !== 1) {
        throw new RuntimeException('Expected callback insertion anchor exactly once.');
    }

    $upsertUpdateAnchor = '`state`=VALUES(`state`),`guarantee_letter`=COALESCE(VALUES(`guarantee_letter`),`guarantee_letter`),`agreement_number`=NULLIF(VALUES(`agreement_number`),\'\'),`payload`=VALUES(`payload`),`date_modified`=NOW()';
    if (pay002_count($source, $upsertUpdateAnchor) !== 1) {
        throw new RuntimeException('Expected unguarded upsert cap_id anchor exactly once.');
    }

    pay002_db_preflight();

    $newConfirm = <<<'PHP'
public function confirm(): void {
        $this->load->language('extension/pumb_credit/payment/pumb_credit');
        $orderId = (int)($this->session->data['order_id'] ?? 0);
        if (!$this->config->get('payment_pumb_credit_status') || !$orderId) { $this->reply(['error' => $this->language->get('error_unavailable')]); return; }
        $order = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE `order_id`='" . $orderId . "' LIMIT 1")->row ?? [];
        $minimum = (float)($this->config->get('payment_pumb_credit_min_total') ?: 500);
        $maximum = (float)$this->config->get('payment_pumb_credit_max_total');
        if (!$order || (float)$order['total'] < $minimum || ($maximum > 0 && (float)$order['total'] > $maximum)) { $this->reply(['error' => $this->language->get('error_minimum')]); return; }
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

        $response = $this->api('POST', self::API_CREATE, $this->createPayload($order));
        $capId = (string)($response['body']['id'] ?? '');
        if (($response['http'] ?? 0) !== 201 || $capId === '') {
            $this->upsertTransaction(
                $orderId,
                'OC-' . $orderId,
                'PENDING-OC-' . $orderId,
                'CREATE_FAILED',
                $isTest,
                ['create' => $response],
                null
            );
            $this->reply(['error' => 'PUMB application was not created. Manual review is required.', 'http' => $response['http'] ?? 0]);
            return;
        }

        $this->upsertTransaction($orderId, 'OC-' . $orderId, $capId, 'WAITING_CLIENT', $isTest, ['create' => $response], null);
        $this->applyOrderStatus($orderId, 'WAITING_CLIENT', false);
        $this->reply(['cap_id' => $capId, 'state' => 'WAITING_CLIENT']);
    }
PHP;

    $source = pay002_replace($source, $confirm, $newConfirm);
    $callbackAnchor = '    private function handleCallback(bool $isTest): void {';
    $helpers = <<<'PHP'
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

PHP;
    if (pay002_count($source, $callbackAnchor) !== 1) {
        throw new RuntimeException('Callback insertion anchor changed during patch assembly.');
    }
    $source = str_replace($callbackAnchor, $helpers . $callbackAnchor, $source);
    $source = str_replace($upsertUpdateAnchor, '`cap_id`=VALUES(`cap_id`),' . $upsertUpdateAnchor, $source);

    if (pay002_count($source, 'private function reserveCreate(int $orderId, int $isTest): array') !== 1
        || pay002_count($source, "'CREATE_FAILED'") < 2
        || pay002_count($source, "'PENDING-OC-' . $orderId") < 2
        || pay002_count($source, '`cap_id`=VALUES(`cap_id`)') !== 1) {
        throw new RuntimeException('Post-patch controller shape validation failed before write.');
    }

    $timestamp = gmdate('Ymd-His');
    $backupDir = getcwd() . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . PAY002_PATCH . '-' . $timestamp;
    if (!mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
        throw new RuntimeException('Unable to create backup directory.');
    }
    if (!copy($target, $backupDir . DIRECTORY_SEPARATOR . 'pumb_credit.php')) {
        throw new RuntimeException('Unable to back up target controller.');
    }
    file_put_contents($backupDir . DIRECTORY_SEPARATOR . 'source.sha256', hash('sha256', file_get_contents($target)) . "  " . PAY002_TARGET . "\n");

    if (file_put_contents($target, $source) === false) {
        throw new RuntimeException('Unable to write patched controller.');
    }
    $sourceWritten = true;
    pay002_lint($target);

    if (file_put_contents($marker, "PAY-002 confirm idempotency guard\n") === false) {
        throw new RuntimeException('Unable to write idempotency marker.');
    }

    echo 'cwd=' . getcwd() . PHP_EOL;
    echo 'time=' . gmdate('c') . PHP_EOL;
    echo 'backup=' . $backupDir . PHP_EOL;
    echo 'changed_file=' . PAY002_TARGET . PHP_EOL;
    echo 'changed_file=' . PAY002_MARKER . PHP_EOL;
    echo "php_l=ok\n";
    echo "done=ok\n";
    @unlink(__FILE__);
} catch (Throwable $exception) {
    if ($sourceWritten && $backupDir !== '' && is_file($backupDir . DIRECTORY_SEPARATOR . 'pumb_credit.php')) {
        @copy($backupDir . DIRECTORY_SEPARATOR . 'pumb_credit.php', $target);
        @unlink($marker);
        fwrite(STDERR, "rollback=source_restored\n");
    }
    pay002_fail($exception->getMessage());
}
