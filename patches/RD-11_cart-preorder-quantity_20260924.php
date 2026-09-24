<?php
declare(strict_types=1);

/**
 * RD-11 correction: show "передзамовлення" only when inventory < 1 AND
 * stock_status_id is 8. A positive-stock item with stale status 8 must not
 * receive the preorder label. Source: owner 2026-09-24 export plus the
 * RD-11_cart-preorder-label_20260924.php transformation.
 *
 * Root cause: the previous Twig branch checked stock_status_id alone.
 * Cart library's `stock` is the raw product inventory; checkout/cart.php
 * currently replaces that field with a display boolean. This runner adds one
 * display-only boolean before the replacement. No stock or checkout policy
 * changes. Run after RD-11_cart-preorder-label_20260924.php.
 * Rollback: restore both logged backups, then clear the template cache.
 */

const PATCH_ID = 'RD-11_cart-preorder-quantity_20260924';
const CONTROLLER = 'catalog/controller/checkout/cart.php';
const TWIG = 'catalog/view/template/checkout/cart_list.twig';
const CONTROLLER_MARKER = 'RD-11-PREORDER-QTY-20260924';
const TWIG_MARKER = 'RD-11-PREORDER-QTY-TWIG-20260924';

function line(string $s): void { echo $s . PHP_EOL; }
function bad(string $s): void { throw new RuntimeException($s); }
function norm(string $s): string { return str_replace(array("\r\n", "\r"), "\n", $s); }
function count_exact(string $s, string $needle, int $want, string $label): void {
    $got = substr_count($s, $needle);
    if ($got !== $want) bad('anchor_count_' . $label . '=' . $got . ',expected=' . $want);
}
function write_file(string $path, string $value): void {
    $tmp = $path . '.' . PATCH_ID . '.tmp.' . getmypid();
    if (file_put_contents($tmp, $value, LOCK_EX) === false) bad('temp_write_failed=' . $path);
    if (!@rename($tmp, $path)) {
        if (!@copy($tmp, $path)) { @unlink($tmp); bad('target_write_failed=' . $path); }
        @unlink($tmp);
    }
    $read = file_get_contents($path);
    if (!is_string($read) || norm($read) !== $value) bad('postwrite_verify_failed=' . $path);
}

set_exception_handler(static function (Throwable $e): void {
    line('error=' . $e->getMessage());
    line('done=failed');
    exit(1);
});

$root = rtrim((string)(getenv('BS_PATCH_ROOT') ?: (getcwd() ?: __DIR__)), '/\\');
$paths = array(CONTROLLER, TWIG);
$original = array();
line('patch=' . PATCH_ID);
line('cwd=' . $root);
line('time=' . date('c'));
line('db_changes=none');
foreach ($paths as $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) bad('target_not_found=' . $relative);
    if (!is_writable($path)) bad('target_not_writable=' . $relative);
    $value = file_get_contents($path);
    if (!is_string($value)) bad('target_read_failed=' . $relative);
    $original[$relative] = norm($value);
    line('file_preflight=ok:' . $relative);
}

$controller = $original[CONTROLLER];
$twig = $original[TWIG];
$controller_done = strpos($controller, CONTROLLER_MARKER) !== false;
$twig_done = strpos($twig, TWIG_MARKER) !== false;
if ($controller_done !== $twig_done) bad('partial_patch_marker_state');
if ($controller_done) {
    count_exact($controller, CONTROLLER_MARKER, 1, 'controller_marker_final');
    count_exact($twig, TWIG_MARKER, 1, 'twig_marker_final');
    count_exact($twig, 'product.bs_preorder_label', 1, 'twig_flag_final');
    line('already_applied=yes');
    line('done=ok');
    @unlink(__FILE__);
    exit(0);
}

$old_controller = "\t\t\t\t'stock'        => \$product['stock_status'] ? true : !(!\$this->config->get('config_stock_checkout') || \$this->config->get('config_stock_warning')),";
$new_controller = "\t\t\t\t// " . CONTROLLER_MARKER . ": display-only preorder requires zero inventory and explicit status.\n"
    . "\t\t\t\t'bs_preorder_label' => ((int)\$product['stock'] < 1 && (int)(\$product['stock_status_id'] ?? 0) === 8),\n"
    . $old_controller;
count_exact($controller, $old_controller, 1, 'controller_stock');
$controller = str_replace($old_controller, $new_controller, $controller);

$old_twig = '{# RD-11-PREORDER-LABEL-20260924 #}{% if product.stock_status_id == 8 %}<span> · передзамовлення</span>{% elseif not product.stock %}<span> · немає в наявності</span>{% endif %}';
$new_twig = '{# ' . TWIG_MARKER . ' #}{% if product.bs_preorder_label %}<span> · передзамовлення</span>{% elseif not product.stock %}<span> · немає в наявності</span>{% endif %}';
count_exact($twig, $old_twig, 1, 'old_preorder_branch');
$twig = str_replace($old_twig, $new_twig, $twig);

$updated = array(CONTROLLER => $controller, TWIG => $twig);
$backup_dir = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His') . '-' . getmypid();
if (!mkdir($backup_dir, 0755, true) && !is_dir($backup_dir)) bad('backup_dir_failed');
$backups = array();
foreach ($paths as $relative) {
    $backup = $backup_dir . '/' . basename($relative);
    if (file_put_contents($backup, $original[$relative], LOCK_EX) === false) bad('backup_write_failed=' . $relative);
    $backups[$relative] = $backup;
    line('backup=' . $backup);
}
try {
    foreach ($paths as $relative) {
        write_file($root . '/' . $relative, $updated[$relative]);
        line('changed=' . $relative);
    }
    $output = array(); $exit_code = -1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($root . '/' . CONTROLLER) . ' 2>&1', $output, $exit_code);
    if ($exit_code !== 0) bad('php_l_failed=' . implode(' | ', $output));
    line('php_lint=ok:' . CONTROLLER);
} catch (Throwable $e) {
    $restored = true;
    foreach ($paths as $relative) {
        if (!@copy($backups[$relative], $root . '/' . $relative)) $restored = false;
    }
    line('restore=' . ($restored ? 'ok' : 'failed'));
    throw $e;
}
line('done=ok');
@unlink(__FILE__);
