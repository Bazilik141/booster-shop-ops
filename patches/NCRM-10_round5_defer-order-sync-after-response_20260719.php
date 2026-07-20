<?php
/**
 * NCRM-10 round 5: defer only the NCRM Edge Function sender until shutdown.
 *
 * Target: catalog/model/checkout/order.php
 * Database: none.
 * Rollback: restore order.php from the backup directory printed below.
 */

declare(strict_types=1);

const NCRM10R5_MARKER = 'NCRM-10_ROUND5_DEFER_AFTER_RESPONSE_20260719';

function ncrm10r5_fail(string $message): void {
    fwrite(STDERR, 'error=' . $message . PHP_EOL);
    exit(1);
}

function ncrm10r5_count(string $source, string $needle): int {
    return substr_count($source, $needle);
}

function ncrm10r5_lint_file(string $path): void {
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $exitCode);
    if ($exitCode !== 0) {
        ncrm10r5_fail('php_lint_failed target=' . $path . ' output=' . implode(' ', $output));
    }
}

function ncrm10r5_lint_text(string $source, string $label): void {
    $tmp = tempnam(sys_get_temp_dir(), 'ncrm10r5-lint-');
    if ($tmp === false) {
        ncrm10r5_fail('cannot_create_temporary_lint_file target=' . $label);
    }

    file_put_contents($tmp, $source);
    ncrm10r5_lint_file($tmp);
    @unlink($tmp);
}

$rootReal = realpath(__DIR__);
if ($rootReal === false) {
    ncrm10r5_fail('cannot_resolve_public_html');
}

$targetPath = $rootReal . DIRECTORY_SEPARATOR . 'catalog/model/checkout/order.php';
if (!is_file($targetPath)) {
    ncrm10r5_fail('required_file_missing path=' . $targetPath);
}

$original = file_get_contents($targetPath);
if ($original === false) {
    ncrm10r5_fail('cannot_read_target path=' . $targetPath);
}

if (strpos($original, NCRM10R5_MARKER) !== false) {
    echo 'already_applied=yes' . PHP_EOL;
    @unlink(__FILE__);
    exit(0);
}

$roundOneMarker = 'NCRM-10_ORDER_SYNC_HOOK_20260718';
$registrationOrderAnchor = '$this->boosterCrmSync($order_id, $booster_crm_event);' . PHP_EOL . '        $this->ncrmOrderSync($order_id, $booster_crm_event);';
$immediateMethod = <<<'METHOD'
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

$deferredMethod = <<<'METHOD'
    // NCRM-10_ORDER_SYNC_HOOK_20260718
    // NCRM-10_ROUND5_DEFER_AFTER_RESPONSE_20260719
    private function ncrmOrderSync(int $order_id, string $event): void {
        if ($event !== 'order_add') {
            return;
        }

        try {
            // boosterCrmSync() is registered first at this call site and calls
            // fastcgi_finish_request() when PHP-FPM provides it. Register this
            // remote NCRM delivery second so it cannot hold up the checkout
            // redirect while retaining the existing best-effort error handling.
            register_shutdown_function(function () use ($order_id, $event): void {
                try {
                    require_once(DIR_SYSTEM . 'library/booster_crm_sync.php');
                    $sender = new \Opencart\System\Library\NcrmOrderSync($this->registry);
                    $sender->syncOrder($order_id, $event);
                } catch (\Throwable $e) {
                    error_log('NCRM-10 order sync failed order_id=' . (int) $order_id);
                }
            });
        } catch (\Throwable $e) {
            error_log('NCRM-10 order sync failed order_id=' . (int) $order_id);
        }
    }

METHOD;

if (ncrm10r5_count($original, $roundOneMarker) !== 1) {
    ncrm10r5_fail('anchor_count_invalid anchor=round1_ncrm_marker expected=1 actual=' . ncrm10r5_count($original, $roundOneMarker));
}
if (ncrm10r5_count($original, $registrationOrderAnchor) !== 1) {
    ncrm10r5_fail('anchor_count_invalid anchor=apps_script_before_ncrm expected=1 actual=' . ncrm10r5_count($original, $registrationOrderAnchor));
}
if (ncrm10r5_count($original, $immediateMethod) !== 1) {
    ncrm10r5_fail('anchor_count_invalid anchor=immediate_ncrm_method expected=1 actual=' . ncrm10r5_count($original, $immediateMethod));
}

ncrm10r5_lint_file($targetPath);

$candidate = str_replace($immediateMethod, $deferredMethod, $original);
if (ncrm10r5_count($candidate, NCRM10R5_MARKER) !== 1
    || ncrm10r5_count($candidate, $immediateMethod) !== 0
    || ncrm10r5_count($candidate, 'register_shutdown_function(function () use ($order_id, $event): void {') < 2) {
    ncrm10r5_fail('post_replace_invariant_failed');
}

ncrm10r5_lint_text($candidate, 'catalog/model/checkout/order.php candidate');

$backupDir = $rootReal . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . basename(__FILE__, '.php') . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
if (!mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
    ncrm10r5_fail('cannot_create_backup_dir path=' . $backupDir);
}
if (!copy($targetPath, $backupDir . DIRECTORY_SEPARATOR . 'order.php')) {
    ncrm10r5_fail('backup_copy_failed path=' . $backupDir);
}

try {
    if (file_put_contents($targetPath, $candidate) === false) {
        throw new RuntimeException('target_write_failed');
    }
    ncrm10r5_lint_file($targetPath);

    $final = file_get_contents($targetPath);
    if ($final === false
        || ncrm10r5_count($final, NCRM10R5_MARKER) !== 1
        || ncrm10r5_count($final, $immediateMethod) !== 0
        || ncrm10r5_count($final, 'register_shutdown_function(function () use ($order_id, $event): void {') < 2) {
        throw new RuntimeException('post_write_invariant_failed');
    }
} catch (Throwable $e) {
    file_put_contents($targetPath, $original);
    ncrm10r5_fail('write_or_lint_failed rollback=restored reason=' . $e->getMessage());
}

echo 'cwd=' . $rootReal . PHP_EOL;
echo 'time_utc=' . gmdate('c') . PHP_EOL;
echo 'backup_dir=' . $backupDir . PHP_EOL;
echo 'changed_file=catalog/model/checkout/order.php' . PHP_EOL;
echo 'done=ok' . PHP_EOL;
@unlink(__FILE__);
