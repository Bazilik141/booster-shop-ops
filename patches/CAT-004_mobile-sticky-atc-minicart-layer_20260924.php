<?php
declare(strict_types=1);

/**
 * CAT-004 mobile product buy bar / RD-12 mini-cart stacking fix, 2026-09-24.
 *
 * Root cause: product.twig sets .bs-sticky-atc to z-index:1050 while the
 * mini-cart overlay and panel use --bs-z-modal (400). The fixed buy bar then
 * covers the bottom of the open mini-cart and its checkout control.
 *
 * Override history: RD-10 introduced this inline product rule. RD-12 uses
 * --bs-z-modal in boostershop-ds.css. No later patch in patches/ changes
 * .bs-sticky-atc. Edit its source rule, not the shared stylesheet or a new
 * overriding selector. Keep it above page content at --bs-z-sticky (200).
 *
 * Rollback: restore logged product.twig backup, then clear OpenCart cache.
 */

const PATCH_ID = 'CAT-004_mobile-sticky-atc-minicart-layer_20260924';
const TARGET = 'catalog/view/template/product/product.twig';
const MARKER = 'CAT-004-STICKY-ATC-BELOW-MINICART-20260924';

function log_line(string $value): void { echo $value . PHP_EOL; }
function fail_patch(string $value): void { throw new RuntimeException($value); }
function normalize(string $value): string { return str_replace(array("\r\n", "\r"), "\n", $value); }
function count_exact(string $text, string $needle, int $expected, string $label): void {
    $actual = substr_count($text, $needle);
    if ($actual !== $expected) fail_patch('anchor_count_' . $label . '=' . $actual . ',expected=' . $expected);
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
$read = file_get_contents($path);
if (!is_string($read)) fail_patch('target_read_failed=' . TARGET);
$original = normalize($read);
log_line('file_preflight=ok:' . TARGET);

$old = <<<'CSS'
          .bs-sticky-atc {
            position: fixed;
            right: 0;
            bottom: 0;
            left: 0;
            z-index: 1050;
            padding: 10px 12px calc(10px + env(safe-area-inset-bottom));
CSS;
$new = <<<'CSS'
          .bs-sticky-atc {
            position: fixed;
            right: 0;
            bottom: 0;
            left: 0;
            /* CAT-004-STICKY-ATC-BELOW-MINICART-20260924: modal layer stays above the buy bar. */
            z-index: var(--bs-z-sticky, 200);
            padding: 10px 12px calc(10px + env(safe-area-inset-bottom));
CSS;

if (strpos($original, MARKER) !== false) {
    count_exact($original, MARKER, 1, 'applied_marker');
    count_exact($original, $new, 1, 'applied_rule');
    log_line('already_applied=yes');
    log_line('done=ok');
    @unlink(__FILE__);
    exit(0);
}
count_exact($original, $old, 1, 'product_sticky_rule');
count_exact($original, 'bs-sticky-atc__cta', 2, 'sticky_cta_class');
$updated = str_replace($old, $new, $original);
count_exact($updated, MARKER, 1, 'new_marker');

$backup_dir = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His') . '-' . getmypid();
if (!mkdir($backup_dir, 0755, true) && !is_dir($backup_dir)) fail_patch('backup_dir_failed');
$backup = $backup_dir . '/product.twig';
if (file_put_contents($backup, $original, LOCK_EX) === false) fail_patch('backup_write_failed=' . TARGET);
log_line('backup=' . $backup);

$temp = $path . '.' . PATCH_ID . '.tmp.' . getmypid();
try {
    if (file_put_contents($temp, $updated, LOCK_EX) === false) fail_patch('temp_write_failed=' . TARGET);
    if (!@rename($temp, $path)) {
        if (!@copy($temp, $path)) fail_patch('target_write_failed=' . TARGET);
        @unlink($temp);
    }
    $after = file_get_contents($path);
    if (!is_string($after) || normalize($after) !== $updated) fail_patch('postwrite_verify_failed=' . TARGET);
    log_line('changed=' . TARGET);
} catch (Throwable $error) {
    @unlink($temp);
    log_line('restore=' . (@copy($backup, $path) ? 'ok' : 'failed'));
    throw $error;
}
log_line('php_lint=not_applicable(no PHP targets)');
log_line('done=ok');
@unlink(__FILE__);
