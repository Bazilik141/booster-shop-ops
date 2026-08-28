<?php
declare(strict_types=1);

/**
 * NCRM-TEST-UNBLOCK: remove owner/test-order filters from both PHP sync paths
 * and enqueue only OC-FOP-0332 for the normal legacy CRM + NCRM deliveries.
 *
 * Scope: system/library/booster_crm_sync.php only. No database writes.
 * Rollback: restore the timestamped file in _patch_backups created below.
 * Prerequisite: deploy the matching Supabase order-sync function first.
 */

const PATCH_ID = 'NCRM-TEST-UNBLOCK_remove-test-filters-replay-0332_20260824';
const REPLAY_ORDER_ID = 332;

$log = [];
$target = __DIR__ . '/system/library/booster_crm_sync.php';
$backupRoot = __DIR__ . '/_patch_backups';

function patchLog(array &$log, string $message): void {
    $line = '[' . gmdate('c') . '] ' . $message;
    $log[] = $line;
    echo $message . PHP_EOL;
}

function patchFail(array &$log, string $message): void {
    patchLog($log, 'error=' . $message);
    exit(1);
}

function countLiteral(string $source, string $needle): int {
    return substr_count($source, $needle);
}

function replaceOnce(string $source, string $before, string $after, array &$log, string $label): string {
    if (countLiteral($source, $before) !== 1) {
        patchFail($log, 'anchor_count_' . $label . '!=' . '1');
    }

    return str_replace($before, $after, $source);
}

function removeSpan(string $source, string $start, string $end, array &$log, string $label): string {
    if (countLiteral($source, $start) !== 1 || countLiteral($source, $end) !== 1) {
        patchFail($log, 'anchor_count_' . $label . '!=1');
    }

    $offset = strpos($source, $start);
    $endOffset = strpos($source, $end, $offset);
    if ($offset === false || $endOffset === false || $endOffset < $offset) {
        patchFail($log, 'anchor_order_invalid_' . $label);
    }

    return substr($source, 0, $offset) . substr($source, $endOffset + strlen($end));
}

function removeBeforeAnchor(string $source, string $start, string $nextAnchor, array &$log, string $label): string {
    if (countLiteral($source, $start) !== 1 || countLiteral($source, $nextAnchor) !== 1) {
        patchFail($log, 'anchor_count_' . $label . '!=1');
    }

    $offset = strpos($source, $start);
    $nextOffset = strpos($source, $nextAnchor, $offset);
    if ($offset === false || $nextOffset === false || $nextOffset < $offset) {
        patchFail($log, 'anchor_order_invalid_' . $label);
    }

    return substr($source, 0, $offset) . substr($source, $nextOffset);
}

function requireFile(string $path, array &$log): void {
    if (!is_file($path)) {
        patchFail($log, 'required_file_missing=' . $path);
    }
    require_once $path;
}

function newOpenCartRegistry(array &$log): object {
    foreach (['DB_DRIVER', 'DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PORT', 'DIR_SYSTEM'] as $constant) {
        if (!defined($constant)) {
            patchFail($log, 'missing_config_constant=' . $constant);
        }
    }

    requireFile(DIR_SYSTEM . 'engine/registry.php', $log);
    requireFile(DIR_SYSTEM . 'library/db.php', $log);
    requireFile(DIR_SYSTEM . 'library/db/' . DB_DRIVER . '.php', $log);

    $registryClass = '\\Opencart\\System\\Engine\\Registry';
    $dbClass = '\\Opencart\\System\\Library\\DB';
    if (!class_exists($registryClass) || !class_exists($dbClass)) {
        patchFail($log, 'OpenCart_registry_or_db_class_unavailable');
    }

    $registry = new $registryClass();
    $db = new $dbClass(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (string) DB_PORT);
    $registry->set('db', $db);
    $registry->set('log', new class {
        public function write(string $message): void {}
    });

    $order = $db->query("SELECT `order_id` FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . REPLAY_ORDER_ID . "' LIMIT 1");
    if (empty($order->row['order_id']) || (int) $order->row['order_id'] !== REPLAY_ORDER_ID) {
        patchFail($log, 'order_not_found=OC-FOP-0332');
    }

    return $registry;
}

patchLog($log, 'cwd=' . (getcwd() ?: 'unknown'));
patchLog($log, 'time=' . gmdate('c'));

if (!is_file(__DIR__ . '/config.php')) {
    patchFail($log, 'config.php_not_found_run_from_public_html');
}
require_once __DIR__ . '/config.php';

if (!is_file($target)) {
    patchFail($log, 'target_missing=system/library/booster_crm_sync.php');
}
$source = file_get_contents($target);
if (!is_string($source)) {
    patchFail($log, 'target_read_failed');
}

$alreadyApplied = strpos($source, 'SKIP_TEST_') === false
    && strpos($source, 'orderHasSkippedProduct') === false
    && strpos($source, 'isTestOrder(') === false;

$updated = $source;
if (!$alreadyApplied) {
    foreach ([
        'class BoosterCrmSync {' => 1,
        'class NcrmOrderSync {' => 1,
        'private const SKIP_TEST_PHONES' => 1,
        'private const SKIP_TEST_EMAILS' => 1,
        'private const SKIP_TEST_CUSTOMER_NAMES' => 1,
        'private const SKIP_TEST_PRODUCT_MARKERS' => 1,
        'private function shouldSkipOrder(int $order_id, array $order): bool {' => 1,
        'private function orderHasSkippedProduct(int $order_id): bool {' => 1,
        'private function isTestOrder(array $order, array $products): bool {' => 1,
        'if ($order === [] || $this->isTestOrder($order, [])) {' => 1,
        'if ($products === [] || $this->isTestOrder($order, $products)) {' => 1,
    ] as $anchor => $expected) {
        if (countLiteral($source, $anchor) !== $expected) {
            patchFail($log, 'preflight_anchor_count_invalid');
        }
    }

    foreach ([
        "\tprivate const SKIP_TEST_PHONES = ['991119279', '991111111'];\n",
        "\tprivate const SKIP_TEST_EMAILS = ['evgenij.leusenko@gmail.com', '14bez23232323likiy14@gmail.com', '14bez232323ikiy14@gmail.com', '14bezli232323kiy14@gmail.com'];\n",
        "\tprivate const SKIP_TEST_CUSTOMER_NAMES = ['євгеній леусенко', 'евгений леусенко', 'evgenii leusenko', 'yevhenii leusenko', 'yevgeniy leusenko'];\n",
        "\tprivate const SKIP_TEST_PRODUCT_MARKERS = ['test sku', 'test-sku', 'testsku', 'тестова позиція'];\n",
    ] as $constantLine) {
        $updated = replaceOnce($updated, $constantLine, '', $log, 'test_constant');
    }

    $legacyTestStart = "\n\t\t\$phone = \$this->normalizePhone(\$order['telephone'] ?? '');\n";
    $legacyTestEnd = "\n\t\treturn \$this->orderHasSkippedProduct(\$order_id);\n";
    $updated = removeSpan($updated, $legacyTestStart, $legacyTestEnd, $log, 'legacy_test_rules');
    $updated = removeBeforeAnchor(
        $updated,
        "\n\tprivate function orderHasSkippedProduct(int \$order_id): bool {\n",
        "\n\tprivate function getOrderKey(int \$order_id): string {\n",
        $log,
        'legacy_test_product_method'
    );
    $updated = replaceOnce(
        $updated,
        "if (\$order === [] || \$this->isTestOrder(\$order, [])) {",
        "if (\$order === []) {",
        $log,
        'ncrm_order_filter'
    );
    $updated = replaceOnce(
        $updated,
        "if (\$products === [] || \$this->isTestOrder(\$order, \$products)) {",
        "if (\$products === []) {",
        $log,
        'ncrm_product_filter'
    );
    $updated = removeBeforeAnchor(
        $updated,
        "\n    private function isTestOrder(array \$order, array \$products): bool {\n",
        "\n    // CHECKOUT-002_ASYNC_ORDER_SIDE_EFFECTS_20260719\n",
        $log,
        'ncrm_test_method'
    );
}

foreach (['SKIP_TEST_', 'orderHasSkippedProduct', 'isTestOrder('] as $forbidden) {
    if (strpos($updated, $forbidden) !== false) {
        patchFail($log, 'postchange_forbidden_marker_present=' . $forbidden);
    }
}

if (in_array('--dry-run', $argv ?? [], true)) {
    patchLog($log, 'dry_run=ok');
    exit(0);
}

$changed = $updated !== $source;
if ($changed) {
    $backupDir = $backupRoot . '/' . PATCH_ID . '-' . gmdate('Ymd-His');
    if (!mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
        patchFail($log, 'backup_directory_create_failed');
    }
    $backup = $backupDir . '/booster_crm_sync.php';
    if (!copy($target, $backup)) {
        patchFail($log, 'backup_create_failed');
    }
    patchLog($log, 'backup=' . $backup);

    if (file_put_contents($target, $updated, LOCK_EX) === false) {
        patchFail($log, 'target_write_failed');
    }
    $lint = [];
    $lintCode = 0;
    exec('php -l ' . escapeshellarg($target) . ' 2>&1', $lint, $lintCode);
    patchLog($log, 'php_lint=' . implode(' | ', $lint));
    if ($lintCode !== 0) {
        @copy($backup, $target);
        patchFail($log, 'php_lint_failed_backup_restored');
    }
    patchLog($log, 'changed=system/library/booster_crm_sync.php');
} else {
    patchLog($log, 'already_applied=yes');
}

try {
    $queueDir = rtrim((string) DIR_STORAGE, '/\\') . '/booster_async_http_queue';
    $legacyJob = $queueDir . '/booster-crm-OC-FOP-0332.json';
    $ncrmJob = $queueDir . '/ncrm-OC-FOP-0332.json';
    $legacySent = rtrim((string) DIR_STORAGE, '/\\') . '/booster_crm_sent/OC-FOP-0332.sent';
    if (is_file($legacySent)) {
        patchFail($log, 'legacy_sent_marker_exists_refusing_replay');
    }

    requireFile($target, $log);
    $registry = newOpenCartRegistry($log);
    $legacyClass = '\\Opencart\\System\\Library\\BoosterCrmSync';
    $ncrmClass = '\\Opencart\\System\\Library\\NcrmOrderSync';
    if (!class_exists($legacyClass) || !class_exists($ncrmClass)) {
        patchFail($log, 'sync_class_unavailable_after_patch');
    }

    (new $legacyClass($registry))->syncOrder(REPLAY_ORDER_ID, 'order_add');
    (new $ncrmClass($registry))->syncOrder(REPLAY_ORDER_ID, 'order_add');

    if (!is_file($legacyJob) || !is_file($ncrmJob)) {
        patchFail($log, 'replay_queue_not_created_for_OC-FOP-0332');
    }
    patchLog($log, 'legacy_job_enqueued=booster-crm-OC-FOP-0332.json');
    patchLog($log, 'ncrm_job_enqueued=ncrm-OC-FOP-0332.json');
} catch (Throwable $error) {
    patchFail($log, 'replay_failed=' . substr($error->getMessage(), 0, 220));
}

patchLog($log, 'done=ok');
@unlink(__FILE__);
