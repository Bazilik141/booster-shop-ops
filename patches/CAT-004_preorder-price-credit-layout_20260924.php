<?php
declare(strict_types=1);

/**
 * CAT-004: show preorder quantity tiers, correct credit hint, place bank cards
 * side by side on the product page. Active specials already render through the
 * controller's special value and the Twig price block; no price math changes.
 * Source: BS_cart_product_ui_20260924.tar.gz (owner's current export).
 * Root cause: product.twig explicitly excludes preorder from the discount row;
 * two JS branches contain the old hint; the source CSS stacks provider rows.
 * Override history: PAY-001_phase2_credit_ui_20260721.php introduced the CSS;
 * PAY-002_pumb-product-page-card_20260831.php rewrote the provider Twig/JS.
 * This runner edits the source rules; the narrow mobile rule keeps both cards
 * on one row when the product column is small. No !important or JS timer added.
 * Rollback: restore the three files from the logged _patch_backups directory,
 * then clear the OpenCart template cache. Run in either order with the pending
 * CAT-004_variant-cosmetics and CAT-004_info-column-reflow patches.
 */

const PATCH_ID = 'CAT-004_preorder-price-credit-layout_20260924';
const TWIG_PATH = 'catalog/view/template/product/product.twig';
const CSS_PATH = 'catalog/view/stylesheet/boostershop-ds.css';
const HEADER_PATH = 'catalog/view/template/common/header.twig';
const TWIG_MARKER = 'CAT-004-PREORDER-PRICES-20260924';
const CSS_MARKER = 'CAT-004-CREDIT-CARDS-20260924';

function log_line(string $line): void { echo $line . PHP_EOL; }
function fail_patch(string $message): void { throw new RuntimeException($message); }
function normalize_text(string $text): string { return str_replace(array("\r\n", "\r"), "\n", $text); }
function expect_count(string $text, string $needle, int $expected, string $name): void {
    $count = substr_count($text, $needle);
    if ($count !== $expected) fail_patch('anchor_count_' . $name . '=' . $count . ',expected=' . $expected);
}
function replace_once(string $text, string $old, string $new, string $name): string {
    expect_count($text, $old, 1, $name);
    return str_replace($old, $new, $text);
}
function write_checked(string $path, string $content): void {
    $temp = $path . '.' . PATCH_ID . '.tmp.' . getmypid();
    if (file_put_contents($temp, $content, LOCK_EX) === false) fail_patch('temp_write_failed=' . $path);
    if (!@rename($temp, $path)) {
        if (!@copy($temp, $path)) {
            @unlink($temp);
            fail_patch('target_write_failed=' . $path);
        }
        @unlink($temp);
    }
    $actual = file_get_contents($path);
    if (!is_string($actual) || normalize_text($actual) !== $content) fail_patch('postwrite_verify_failed=' . $path);
}

set_exception_handler(static function (Throwable $error): void {
    log_line('error=' . $error->getMessage());
    log_line('done=failed');
    exit(1);
});

$root = rtrim((string)(getenv('BS_PATCH_ROOT') ?: (getcwd() ?: __DIR__)), '/\\');
$paths = array(TWIG_PATH, CSS_PATH, HEADER_PATH);
$original = array();
log_line('patch=' . PATCH_ID);
log_line('cwd=' . $root);
log_line('time=' . date('c'));
log_line('db_changes=none');
foreach ($paths as $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) fail_patch('target_not_found=' . $relative);
    if (!is_writable($path)) fail_patch('target_not_writable=' . $relative);
    $read = file_get_contents($path);
    if (!is_string($read)) fail_patch('target_read_failed=' . $relative);
    $original[$relative] = normalize_text($read);
    log_line('file_preflight=ok:' . $relative);
}

$twig = $original[TWIG_PATH];
$css = $original[CSS_PATH];
$header = $original[HEADER_PATH];
$twig_done = strpos($twig, TWIG_MARKER) !== false;
$css_done = strpos($css, CSS_MARKER) !== false;
if ($twig_done !== $css_done) fail_patch('partial_patch_marker_state');
if ($twig_done) {
    expect_count($twig, TWIG_MARKER, 1, 'twig_marker_final');
    expect_count($css, CSS_MARKER, 1, 'css_marker_final');
    expect_count($twig, 'Сплата частинами доступна лише для товарів у наявності.', 2, 'hint_final');
    expect_count($twig, '{% if discounts and not _is_preorder %}', 0, 'discount_guard_final');
    log_line('already_applied=yes');
    log_line('done=ok');
    @unlink(__FILE__);
    exit(0);
}

$twig = replace_once($twig, '{% if discounts and not _is_preorder %}',
    '{# ' . TWIG_MARKER . ': quantity tiers also apply to preorder products. #}' . "\n" .
    '          {% if discounts %}', 'discount_guard');
expect_count($twig, 'Оплата частинами доступна лише для товарів у наявності.', 2, 'old_hint');
$twig = str_replace('Оплата частинами доступна лише для товарів у наявності.',
    'Сплата частинами доступна лише для товарів у наявності.', $twig);

$old_css = <<<'CSS'
.pay001-product-credit__info { margin-top: 10px; border: 1px solid var(--bs-line, #d8dee8); border-radius: 10px; overflow: hidden; background: #fff; }
.pay001-product-credit__hint { margin: 7px 2px 0; color: #64748b; font-size: 12px; line-height: 1.4; }
.pay001-provider-row { min-height: 56px; display: flex; align-items: center; gap: 10px; padding: 9px 12px; }
.pay001-provider-row + .pay001-provider-row { border-top: 1px solid var(--bs-line, #d8dee8); }
CSS;
$new_css = <<<'CSS'
/* CAT-004-CREDIT-CARDS-20260924: two equal product-page provider cards; one visible card spans the row. */
.pay001-product-credit__info { margin-top: 10px; border: 1px solid var(--bs-line, #d8dee8); border-radius: 10px; overflow: hidden; background: #fff; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
.pay001-product-credit__hint { margin: 7px 2px 0; color: #64748b; font-size: 12px; line-height: 1.4; }
.pay001-provider-row { min-width: 0; min-height: 56px; display: flex; align-items: center; gap: 10px; padding: 9px 12px; }
.pay001-provider-row:only-child { grid-column: 1 / -1; }
.pay001-provider-row + .pay001-provider-row { border-left: 1px solid var(--bs-line, #d8dee8); }
/* At 480px and below, 24px logos and compact gaps leave text room in half-width cards. */
@media (max-width: 480px) {
  .pay001-provider-row { gap: 6px; padding: 9px; }
  .pay001-product-credit__info .pay001-provider-row__mono, .pay001-product-credit__info .pay001-provider-row__pumb { width: 24px; height: 24px; }
  .pay001-product-credit__info .pay001-provider-row strong { overflow-wrap: anywhere; font-size: 12px; }
}
CSS;
$css = replace_once($css, $old_css, $new_css, 'provider_rules');

$token_pattern = '~(catalog/view/stylesheet/boostershop-ds\.css\?v=)([A-Za-z0-9._-]+)~';
$token_count = preg_match_all($token_pattern, $header);
if ($token_count !== 1) fail_patch('anchor_count_header_css_token=' . (string)$token_count . ',expected=1');
$header = preg_replace_callback($token_pattern, static function (array $match): string {
    return $match[1] . 'cat004-price-credit-20260924';
}, $header, 1);
if (!is_string($header)) fail_patch('header_css_token_replace_failed');

$updated = array(TWIG_PATH => $twig, CSS_PATH => $css, HEADER_PATH => $header);
$backup_dir = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His') . '-' . getmypid();
if (!mkdir($backup_dir, 0755, true) && !is_dir($backup_dir)) fail_patch('backup_dir_failed');
$backups = array();
foreach ($paths as $relative) {
    $backup = $backup_dir . '/' . basename($relative);
    if (file_put_contents($backup, $original[$relative], LOCK_EX) === false) fail_patch('backup_write_failed=' . $relative);
    $backups[$relative] = $backup;
    log_line('backup=' . $backup);
}

$changed = array();
try {
    foreach ($paths as $relative) {
        write_checked($root . '/' . $relative, $updated[$relative]);
        $changed[] = $relative;
        log_line('changed=' . $relative);
    }
} catch (Throwable $error) {
    $rollback_ok = true;
    foreach ($paths as $relative) {
        if (!@copy($backups[$relative], $root . '/' . $relative)) $rollback_ok = false;
    }
    log_line('restore=' . ($rollback_ok ? 'ok' : 'failed'));
    throw $error;
}
log_line('php_lint=not_applicable(no PHP targets)');
log_line('done=ok');
@unlink(__FILE__);
