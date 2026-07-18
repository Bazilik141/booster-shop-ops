<?php
/**
 * NCRM-10 OpenCart to Supabase order-sync hook.
 *
 * Scope:
 * - preserves the existing Apps Script bridge unchanged;
 * - adds a separate, best-effort sender for the existing order_add event only;
 * - creates a blank storage-side endpoint/secret configuration template.
 *
 * No database changes. Rollback: restore the two backed-up source files and
 * remove the created DIR_STORAGE/config/ncrm_order_sync.php file if required.
 */

declare(strict_types=1);

const NCRM10_PATCH_MARKER = 'NCRM-10_ORDER_SYNC_HOOK_20260718';

function ncrm10_fail(string $message): void {
    fwrite(STDERR, 'error=' . $message . PHP_EOL);
    exit(1);
}

function ncrm10_count(string $source, string $needle): int {
    return substr_count($source, $needle);
}

function ncrm10_lint_text(string $source, string $label): void {
    $tmp = tempnam(sys_get_temp_dir(), 'ncrm10-lint-');
    if ($tmp === false) {
        ncrm10_fail('cannot create temporary lint file for ' . $label);
    }

    file_put_contents($tmp, $source);
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $output, $exitCode);
    @unlink($tmp);

    if ($exitCode !== 0) {
        ncrm10_fail('php_lint_failed target=' . $label . ' output=' . implode(' ', $output));
    }
}

function ncrm10_lint_file(string $path): void {
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $exitCode);
    if ($exitCode !== 0) {
        ncrm10_fail('php_lint_failed target=' . $path . ' output=' . implode(' ', $output));
    }
}

function ncrm10_restore(string $modelPath, string $modelOriginal, string $bridgePath, string $bridgeOriginal, string $storageConfigPath, bool $storageConfigCreated): void {
    file_put_contents($modelPath, $modelOriginal);
    file_put_contents($bridgePath, $bridgeOriginal);
    if ($storageConfigCreated && is_file($storageConfigPath)) {
        @unlink($storageConfigPath);
    }
}

$root = __DIR__;
$rootReal = realpath($root);
if ($rootReal === false) {
    ncrm10_fail('cannot resolve OpenCart root');
}

$modelPath = $rootReal . DIRECTORY_SEPARATOR . 'catalog/model/checkout/order.php';
$bridgePath = $rootReal . DIRECTORY_SEPARATOR . 'system/library/booster_crm_sync.php';
$publicConfigPath = $rootReal . DIRECTORY_SEPARATOR . 'config.php';

foreach ([$modelPath, $bridgePath, $publicConfigPath] as $requiredPath) {
    if (!is_file($requiredPath)) {
        ncrm10_fail('required_file_missing path=' . $requiredPath);
    }
}

$modelOriginal = file_get_contents($modelPath);
$bridgeOriginal = file_get_contents($bridgePath);
if ($modelOriginal === false || $bridgeOriginal === false) {
    ncrm10_fail('cannot_read_target');
}

if (strpos($modelOriginal, NCRM10_PATCH_MARKER) !== false && strpos($bridgeOriginal, NCRM10_PATCH_MARKER) !== false) {
    echo 'already_applied=yes' . PHP_EOL;
    @unlink(__FILE__);
    exit(0);
}

if (strpos($modelOriginal, NCRM10_PATCH_MARKER) !== false || strpos($bridgeOriginal, NCRM10_PATCH_MARKER) !== false) {
    ncrm10_fail('partial_marker_state_manual_review_required');
}

$modelAnchor = '$this->boosterCrmSync($order_id, $booster_crm_event);';
$modelMethodAnchor = 'private function boosterCrmSync(int $order_id, string $event): void {';
$bridgeAnchor = 'class BoosterCrmSync';

if (ncrm10_count($modelOriginal, $modelAnchor) !== 1) {
    ncrm10_fail('anchor_count_invalid anchor=existing_apps_script_call expected=1 actual=' . ncrm10_count($modelOriginal, $modelAnchor));
}
if (ncrm10_count($modelOriginal, $modelMethodAnchor) !== 1) {
    ncrm10_fail('anchor_count_invalid anchor=boosterCrmSync_method expected=1 actual=' . ncrm10_count($modelOriginal, $modelMethodAnchor));
}
if (ncrm10_count($bridgeOriginal, $bridgeAnchor) !== 1) {
    ncrm10_fail('anchor_count_invalid anchor=BoosterCrmSync_class expected=1 actual=' . ncrm10_count($bridgeOriginal, $bridgeAnchor));
}

require $publicConfigPath;
if (!defined('DIR_STORAGE') || trim((string) DIR_STORAGE) === '') {
    ncrm10_fail('DIR_STORAGE_is_not_defined_in_config_php');
}

$storageConfigPath = rtrim((string) DIR_STORAGE, '/\\') . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'ncrm_order_sync.php';
$storageConfigCreated = false;

$ncrmOrderMethod = <<<'METHOD'

    // NCRM-10_ORDER_SYNC_HOOK_20260718
    private function ncrmOrderSync(int $order_id, string $event): void {
        if ($event !== 'order_add') {
            return;
        }

        try {
            require_once(DIR_SYSTEM . 'library/booster_crm_sync.php');
            $sender = new \Opencart\System\Library\NcrmOrderSync($this->registry);
            $sender->syncOrder($order_id, $event);
        } catch (\Throwable $e) {
            error_log('NCRM-10 order sync failed order_id=' . (int) $order_id);
        }
    }

METHOD;

$bridgeClass = <<<'BRIDGE'


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
            if (!$this->postJson($config['url'], $config['secret'], $payload)) {
                error_log('NCRM-10 order sync failed order_id=' . (int) $orderId);
            }
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
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_TIMEOUT => 2,
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
                'timeout' => 2,
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
BRIDGE;

$modelCandidate = str_replace(
    $modelAnchor,
    $modelAnchor . PHP_EOL . '        $this->ncrmOrderSync($order_id, $booster_crm_event);',
    $modelOriginal
);
$modelCandidate = str_replace(
    $modelMethodAnchor,
    $ncrmOrderMethod . $modelMethodAnchor,
    $modelCandidate
);

$bridgeCandidate = preg_replace('/\s*\?>\s*$/', PHP_EOL, $bridgeOriginal);
$bridgeCandidate .= $bridgeClass . PHP_EOL;

if (ncrm10_count($modelCandidate, NCRM10_PATCH_MARKER) !== 1 || ncrm10_count($bridgeCandidate, NCRM10_PATCH_MARKER) !== 1) {
    ncrm10_fail('post_replace_marker_invariant_failed');
}
ncrm10_lint_text($modelCandidate, 'catalog/model/checkout/order.php');
ncrm10_lint_text($bridgeCandidate, 'system/library/booster_crm_sync.php');

$backupDir = $rootReal . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . basename(__FILE__, '.php') . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
if (!mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
    ncrm10_fail('cannot_create_backup_dir path=' . $backupDir);
}
if (!copy($modelPath, $backupDir . DIRECTORY_SEPARATOR . 'order.php') || !copy($bridgePath, $backupDir . DIRECTORY_SEPARATOR . 'booster_crm_sync.php')) {
    ncrm10_fail('backup_copy_failed path=' . $backupDir);
}

$configTemplate = <<<'CONFIG'
<?php
// Fill both values only after the Supabase function is deployed.
return [
    'url' => '',
    'secret' => '',
];
CONFIG;

try {
    file_put_contents($modelPath, $modelCandidate);
    file_put_contents($bridgePath, $bridgeCandidate);

    if (!is_file($storageConfigPath)) {
        $storageConfigDir = dirname($storageConfigPath);
        if (!is_dir($storageConfigDir) && !mkdir($storageConfigDir, 0750, true) && !is_dir($storageConfigDir)) {
            throw new RuntimeException('cannot_create_storage_config_dir');
        }
        if (file_put_contents($storageConfigPath, $configTemplate . PHP_EOL) === false) {
            throw new RuntimeException('cannot_write_storage_config');
        }
        $storageConfigCreated = true;
    }

    ncrm10_lint_file($modelPath);
    ncrm10_lint_file($bridgePath);
    ncrm10_lint_file($storageConfigPath);

    $modelFinal = file_get_contents($modelPath);
    $bridgeFinal = file_get_contents($bridgePath);
    if ($modelFinal === false || $bridgeFinal === false || ncrm10_count($modelFinal, NCRM10_PATCH_MARKER) !== 1 || ncrm10_count($bridgeFinal, NCRM10_PATCH_MARKER) !== 1) {
        throw new RuntimeException('post_write_invariant_failed');
    }
} catch (Throwable $e) {
    ncrm10_restore($modelPath, $modelOriginal, $bridgePath, $bridgeOriginal, $storageConfigPath, $storageConfigCreated);
    ncrm10_fail('write_or_lint_failed rollback=restored reason=' . $e->getMessage());
}

echo 'backup_dir=' . $backupDir . PHP_EOL;
echo 'storage_config=' . $storageConfigPath . PHP_EOL;
echo 'done=ok' . PHP_EOL;
@unlink(__FILE__);
