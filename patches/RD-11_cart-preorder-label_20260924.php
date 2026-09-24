<?php
declare(strict_types=1);

/**
 * RD-11: distinguish preorder items from other unavailable items in cart UI.
 * Source: BS_cart_product_ui_20260924.tar.gz (owner's current export).
 * Root cause: cart_list.twig tests only product.stock, although the cart row
 * already carries stock_status_id. Stock status 8 is the explicit preorder
 * state used by system/library/cart/cart.php::hasStock().
 * Override history: RD-11_cart-page_20260921.php introduced this line;
 * RD-11_cart-hide-recommendations_20260922.php also edited cart_list.twig.
 * Rollback: restore cart_list.twig from the logged _patch_backups directory,
 * then clear the OpenCart template cache. No DB or checkout changes.
 */

const PATCH_ID = 'RD-11_cart-preorder-label_20260924';
const TARGET = 'catalog/view/template/checkout/cart_list.twig';
const MARKER = 'RD-11-PREORDER-LABEL-20260924';

function log_line(string $line): void { echo $line . PHP_EOL; }
function fail_patch(string $message): void { throw new RuntimeException($message); }
function normalize_text(string $text): string { return str_replace(array("\r\n", "\r"), "\n", $text); }
function expect_once(string $text, string $needle, string $name): void {
    $count = substr_count($text, $needle);
    if ($count !== 1) fail_patch('anchor_count_' . $name . '=' . $count . ',expected=1');
}

set_exception_handler(static function (Throwable $error): void {
    log_line('error=' . $error->getMessage());
    log_line('done=failed');
    exit(1);
});

$root = rtrim((string)(getenv('BS_PATCH_ROOT') ?: (getcwd() ?: __DIR__)), '/\\');
$path = $root . '/' . TARGET;
log_line('patch=' . PATCH_ID);
log_line('cwd=' . $root);
log_line('time=' . date('c'));
log_line('db_changes=none');
if (!is_file($path)) fail_patch('target_not_found=' . TARGET);
if (!is_writable($path)) fail_patch('target_not_writable=' . TARGET);
$original = file_get_contents($path);
if (!is_string($original)) fail_patch('target_read_failed=' . TARGET);
$original = normalize_text($original);
log_line('file_preflight=ok:' . TARGET);

if (strpos($original, MARKER) !== false) {
    expect_once($original, MARKER, 'marker_final');
    expect_once($original, '<span> · передзамовлення</span>', 'preorder_final');
    log_line('already_applied=yes');
    log_line('done=ok');
    @unlink(__FILE__);
    exit(0);
}

$old = '{% if not product.stock %}<span> · немає в наявності</span>{% endif %}';
$new = '{# ' . MARKER . ' #}{% if product.stock_status_id == 8 %}<span> · передзамовлення</span>{% elseif not product.stock %}<span> · немає в наявності</span>{% endif %}';
expect_once($original, $old, 'cart_stock_label');
$updated = str_replace($old, $new, $original);
expect_once($updated, MARKER, 'marker_new');

$backup_dir = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His') . '-' . getmypid();
if (!mkdir($backup_dir, 0755, true) && !is_dir($backup_dir)) fail_patch('backup_dir_failed');
$backup = $backup_dir . '/cart_list.twig';
if (file_put_contents($backup, $original, LOCK_EX) === false) fail_patch('backup_write_failed');
log_line('backup=' . $backup);

$temp = $path . '.' . PATCH_ID . '.tmp.' . getmypid();
if (file_put_contents($temp, $updated, LOCK_EX) === false) fail_patch('temp_write_failed');
if (!@rename($temp, $path)) {
    if (!@copy($temp, $path)) {
        @unlink($temp);
        fail_patch('target_write_failed');
    }
    @unlink($temp);
}
$written = file_get_contents($path);
if (!is_string($written) || normalize_text($written) !== $updated) {
    if (!@copy($backup, $path)) fail_patch('postwrite_failed_restore_failed');
    fail_patch('postwrite_failed_restored');
}
log_line('changed=' . TARGET);
log_line('php_lint=not_applicable(no PHP targets)');
log_line('done=ok');
@unlink(__FILE__);
