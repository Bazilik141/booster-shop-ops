<?php
declare(strict_types=1);

/**
 * CAT-004 follow-up: center the preorder trust item on mobile and use
 * "Сплата частинами" in both in-stock threshold hints.
 * Source: owner 2026-09-24 export transformed by the pending CAT-004
 * info-column-reflow and preorder-price-credit-layout runners.
 *
 * Root cause: the product-only mobile trust rule aligns every icon to the
 * first line, leaving the two-line preorder item visually off-center. The
 * credit threshold JS kept the earlier "Оплата частинами" prefix.
 * Shared-selector history: cat002_5c_mobile_visual_breadcrumb_20260630.php
 * and common/home.twig use a different trust strip. All new selectors stay
 * under .bs-product__info and affect only the truck/preorder item.
 * Run after CAT-004_info-column-reflow_20260923.php and
 * CAT-004_preorder-price-credit-layout_20260924.php.
 * Rollback: restore the three logged backups and clear the template cache.
 */

const PATCH_ID = 'CAT-004_trust-credit-copy_20260924';
const TWIG = 'catalog/view/template/product/product.twig';
const CSS = 'catalog/view/stylesheet/boostershop-ds.css';
const HEADER = 'catalog/view/template/common/header.twig';
const TWIG_MARKER = 'CAT-004-TRUST-COPY-20260924';
const CSS_MARKER = 'CAT-004-TRUST-CENTER-20260924';

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
$paths = array(TWIG, CSS, HEADER);
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

$twig = $original[TWIG];
$css = $original[CSS];
$header = $original[HEADER];
$twig_done = strpos($twig, TWIG_MARKER) !== false;
$css_done = strpos($css, CSS_MARKER) !== false;
if ($twig_done !== $css_done) bad('partial_patch_marker_state');
if ($twig_done) {
    count_exact($twig, TWIG_MARKER, 1, 'twig_marker_final');
    count_exact($css, CSS_MARKER, 1, 'css_marker_final');
    count_exact($twig, "hint.textContent = 'Сплата частинами доступна від '", 2, 'threshold_hint_final');
    line('already_applied=yes');
    line('done=ok');
    @unlink(__FILE__);
    exit(0);
}

$old_item = '<div class="bs-trust-strip__item" role="listitem">';
$new_item = '{# ' . TWIG_MARKER . ' #}<div class="bs-trust-strip__item{% if item.icon == \'truck\' %} bs-trust-strip__item--preorder{% endif %}" role="listitem">';
count_exact($twig, $old_item, 1, 'trust_item');
$twig = str_replace($old_item, $new_item, $twig);
$old_hint = "hint.textContent = 'Оплата частинами доступна від '";
count_exact($twig, $old_hint, 2, 'threshold_hint');
$twig = str_replace($old_hint, "hint.textContent = 'Сплата частинами доступна від '", $twig);

$old_css = '  .bs-product__info .bs-trust-strip__item { align-items: flex-start; }';
$new_css = $old_css . "\n" . '  /* ' . CSS_MARKER . ': center the two-line preorder item only. */' . "\n"
    . '  .bs-product__info .bs-trust-strip__item--preorder { align-items: center; text-align: center; }';
count_exact($css, $old_css, 1, 'mobile_trust_alignment');
$css = str_replace($old_css, $new_css, $css);

$pattern = '~(catalog/view/stylesheet/boostershop-ds\.css\?v=)([A-Za-z0-9._-]+)~';
$token_count = preg_match_all($pattern, $header);
if ($token_count !== 1) bad('anchor_count_header_css_token=' . (string)$token_count . ',expected=1');
$header = preg_replace_callback($pattern, static function (array $m): string {
    return $m[1] . 'cat004-trust-copy-20260924';
}, $header, 1);
if (!is_string($header)) bad('header_css_token_replace_failed');

$updated = array(TWIG => $twig, CSS => $css, HEADER => $header);
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
} catch (Throwable $e) {
    $restored = true;
    foreach ($paths as $relative) {
        if (!@copy($backups[$relative], $root . '/' . $relative)) $restored = false;
    }
    line('restore=' . ($restored ? 'ok' : 'failed'));
    throw $e;
}
line('php_lint=not_applicable(no PHP targets)');
line('done=ok');
@unlink(__FILE__);
