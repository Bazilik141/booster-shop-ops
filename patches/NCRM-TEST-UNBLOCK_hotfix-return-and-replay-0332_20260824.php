<?php
declare(strict_types=1);

/**
 * Emergency follow-up for NCRM-TEST-UNBLOCK.
 * Restores the missing false return in BoosterCrmSync::shouldSkipOrder(), then
 * enqueues only OC-FOP-0332 through the normal legacy CRM and NCRM paths.
 * No database writes; rollback is the backup created before the PHP source write.
 */

const PATCH_ID = 'NCRM-TEST-UNBLOCK_hotfix-return-and-replay-0332_20260824';
const REPLAY_ORDER_ID = 332;

$log = [];
$target = __DIR__ . '/system/library/booster_crm_sync.php';

function out(array &$log, string $message): void {
    $log[] = '[' . gmdate('c') . '] ' . $message;
    echo $message . PHP_EOL;
}

function fail(array &$log, string $message): void {
    out($log, 'error=' . $message);
    exit(1);
}

function required(string $path, array &$log): void {
    if (!is_file($path)) fail($log, 'required_file_missing=' . $path);
    require_once $path;
}

function newRegistry(array &$log): object {
    foreach (['DB_DRIVER', 'DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PORT', 'DIR_SYSTEM'] as $constant) {
        if (!defined($constant)) fail($log, 'missing_config_constant=' . $constant);
    }

    required(DIR_SYSTEM . 'engine/registry.php', $log);
    required(DIR_SYSTEM . 'library/db.php', $log);
    required(DIR_SYSTEM . 'library/db/' . DB_DRIVER . '.php', $log);

    $registryClass = '\\Opencart\\System\\Engine\\Registry';
    $dbClass = '\\Opencart\\System\\Library\\DB';
    if (!class_exists($registryClass) || !class_exists($dbClass)) fail($log, 'OpenCart_registry_or_db_class_unavailable');

    $registry = new $registryClass();
    $db = new $dbClass(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (string) DB_PORT);
    $registry->set('db', $db);
    $registry->set('log', new class { public function write(string $message): void {} });

    $order = $db->query("SELECT `order_id` FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . REPLAY_ORDER_ID . "' LIMIT 1");
    if (empty($order->row['order_id']) || (int) $order->row['order_id'] !== REPLAY_ORDER_ID) {
        fail($log, 'order_not_found=OC-FOP-0332');
    }

    return $registry;
}

out($log, 'cwd=' . (getcwd() ?: 'unknown'));
out($log, 'time=' . gmdate('c'));
if (!is_file(__DIR__ . '/config.php')) fail($log, 'config.php_not_found_run_from_public_html');
require_once __DIR__ . '/config.php';
if (!is_file($target)) fail($log, 'target_missing=system/library/booster_crm_sync.php');
$source = file_get_contents($target);
if (!is_string($source)) fail($log, 'target_read_failed');

foreach (['SKIP_TEST_', 'orderHasSkippedProduct', 'isTestOrder('] as $forbidden) {
    if (strpos($source, $forbidden) !== false) fail($log, 'unexpected_test_filter_marker=' . $forbidden);
}

$pattern = '~(\n\tprivate function shouldSkipOrder\(int \$order_id, array \$order\): bool \{\n.*?)(\n\t\})(\n\n\tprivate function getOrderKey\(int \$order_id\): string \{)~s';
if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER) !== 1) {
    fail($log, 'shouldSkipOrder_shape_not_unique');
}
$method = $matches[0][1];
if (substr_count($method, 'return true;') !== 1) fail($log, 'shouldSkipOrder_return_true_count_invalid');

if (strpos($method, 'return false;') === false) {
    $updated = preg_replace($pattern, '$1' . "\n\t\treturn false;" . '$2$3', $source, 1, $replaced);
    if (!is_string($updated) || $replaced !== 1) fail($log, 'return_false_insert_failed');

    $backupDir = __DIR__ . '/_patch_backups/' . PATCH_ID . '-' . gmdate('Ymd-His');
    if (!mkdir($backupDir, 0700, true) && !is_dir($backupDir)) fail($log, 'backup_directory_create_failed');
    $backup = $backupDir . '/booster_crm_sync.php';
    if (!copy($target, $backup)) fail($log, 'backup_create_failed');
    out($log, 'backup=' . $backup);
    if (file_put_contents($target, $updated, LOCK_EX) === false) fail($log, 'target_write_failed');
    $lint = [];
    $lintCode = 0;
    exec('php -l ' . escapeshellarg($target) . ' 2>&1', $lint, $lintCode);
    out($log, 'php_lint=' . implode(' | ', $lint));
    if ($lintCode !== 0) {
        @copy($backup, $target);
        fail($log, 'php_lint_failed_backup_restored');
    }
    out($log, 'changed=system/library/booster_crm_sync.php');
} else {
    out($log, 'already_applied=yes');
}

try {
    $queueDir = rtrim((string) DIR_STORAGE, '/\\') . '/booster_async_http_queue';
    $legacyJob = $queueDir . '/booster-crm-OC-FOP-0332.json';
    $ncrmJob = $queueDir . '/ncrm-OC-FOP-0332.json';
    $legacySent = rtrim((string) DIR_STORAGE, '/\\') . '/booster_crm_sent/OC-FOP-0332.sent';
    if (is_file($legacySent)) fail($log, 'legacy_sent_marker_exists_refusing_replay');

    required($target, $log);
    $registry = newRegistry($log);
    $legacyClass = '\\Opencart\\System\\Library\\BoosterCrmSync';
    $ncrmClass = '\\Opencart\\System\\Library\\NcrmOrderSync';
    if (!class_exists($legacyClass) || !class_exists($ncrmClass)) fail($log, 'sync_class_unavailable_after_hotfix');

    (new $legacyClass($registry))->syncOrder(REPLAY_ORDER_ID, 'order_add');
    (new $ncrmClass($registry))->syncOrder(REPLAY_ORDER_ID, 'order_add');
    if (!is_file($legacyJob) || !is_file($ncrmJob)) fail($log, 'replay_queue_not_created_for_OC-FOP-0332');

    out($log, 'legacy_job_enqueued=booster-crm-OC-FOP-0332.json');
    out($log, 'ncrm_job_enqueued=ncrm-OC-FOP-0332.json');
} catch (Throwable $error) {
    fail($log, 'replay_failed=' . substr($error->getMessage(), 0, 220));
}

out($log, 'done=ok');
@unlink(__FILE__);
