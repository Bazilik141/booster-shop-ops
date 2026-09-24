<?php
declare(strict_types=1);

/**
 * CAT-004 / RD-11 owner follow-up, 2026-09-24.
 * Product mobile: vertically center each trust icon against its wrapped label,
 * left-align preorder text, and stack Mono/PUMB provider cards. Desktop cards
 * stay in two columns. Cart: remove both top stock-only alerts while retaining
 * the redesigned in-cart stock notice and all checkout/stock predicates.
 *
 * Source: BS_cart_product_ui_20260924.tar.gz transformed by the seven previous
 * CAT-004/RD-11/RD-PP-META runners; owner screenshots show that later UI state.
 * Root causes: product-only mobile trust align-items:flex-start and preorder
 * text-align:center; CAT-004 credit grid stays two columns at mobile widths;
 * PAY-002 top stock hint duplicates the RD-11 notice below the cart heading.
 * Override history: CAT-004_info-column-reflow and CAT-004_trust-credit-copy
 * own the product trust rule; CAT-004_preorder-price-credit-layout owns provider
 * rules; PAY-002_cart-stock-hint-correction owns the top cart stock hint.
 * The trust selector is scoped to .bs-product__info; homepage trust is separate.
 * This edits those source rules, with no !important or new global override.
 *
 * Run after all seven prior runners. Rollback: restore the three logged backups
 * from _patch_backups and clear the OpenCart template cache.
 */

const PATCH_ID = 'CAT-004_RD-11_mobile-trust-cart-alert_20260924';
const CSS = 'catalog/view/stylesheet/boostershop-ds.css';
const CART = 'catalog/view/template/checkout/cart_list.twig';
const HEADER = 'catalog/view/template/common/header.twig';
const CSS_MARKER = 'CAT-004-MOBILE-TRUST-CREDIT-20260924';
const CART_MARKER = 'RD-11-TOP-STOCK-ALERT-REMOVED-20260924';

function log_line(string $value): void { echo $value . PHP_EOL; }
function fail_patch(string $value): void { throw new RuntimeException($value); }
function normalize(string $value): string { return str_replace(array("\r\n", "\r"), "\n", $value); }
function count_exact(string $haystack, string $needle, int $expected, string $label): void {
    $actual = substr_count($haystack, $needle);
    if ($actual !== $expected) fail_patch('anchor_count_' . $label . '=' . $actual . ',expected=' . $expected);
}
function replace_exact(string $haystack, string $old, string $new, string $label): string {
    count_exact($haystack, $old, 1, $label);
    return str_replace($old, $new, $haystack);
}
function write_checked(string $path, string $content): void {
    $temp = $path . '.' . PATCH_ID . '.tmp.' . getmypid();
    if (file_put_contents($temp, $content, LOCK_EX) === false) fail_patch('temp_write_failed=' . $path);
    if (!@rename($temp, $path)) {
        if (!@copy($temp, $path)) { @unlink($temp); fail_patch('target_write_failed=' . $path); }
        @unlink($temp);
    }
    $actual = file_get_contents($path);
    if (!is_string($actual) || normalize($actual) !== $content) fail_patch('postwrite_verify_failed=' . $path);
}

set_exception_handler(static function (Throwable $error): void {
    log_line('error=' . $error->getMessage());
    log_line('done=failed');
    exit(1);
});

$root = rtrim((string)(getenv('BS_PATCH_ROOT') ?: (getcwd() ?: __DIR__)), '/\\');
$paths = array(CSS, CART, HEADER);
$original = array();
log_line('patch=' . PATCH_ID);
log_line('cwd=' . $root);
log_line('time=' . date('c'));
log_line('db_changes=none');
foreach ($paths as $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) fail_patch('target_not_found=' . $relative);
    if (!is_writable($path)) fail_patch('target_not_writable=' . $relative);
    $content = file_get_contents($path);
    if (!is_string($content)) fail_patch('target_read_failed=' . $relative);
    $original[$relative] = normalize($content);
    log_line('file_preflight=ok:' . $relative);
}

$css = $original[CSS];
$cart = $original[CART];
$header = $original[HEADER];
$css_done = strpos($css, CSS_MARKER) !== false;
$cart_done = strpos($cart, CART_MARKER) !== false;
if ($css_done !== $cart_done) fail_patch('partial_patch_marker_state');
if ($css_done) {
    count_exact($css, CSS_MARKER, 1, 'css_marker_final');
    count_exact($cart, CART_MARKER, 1, 'cart_marker_final');
    count_exact($cart, 'data-pay002-cart-stock-hint', 0, 'top_stock_hint_final');
    count_exact($cart, 'class="bs-cart-stock-notice"', 1, 'lower_stock_notice_final');
    log_line('already_applied=yes');
    log_line('done=ok');
    @unlink(__FILE__);
    exit(0);
}

$old_trust = <<<'CSS'
  /* Keep the icon beside the first line when product trust text wraps. */
  .bs-product__info .bs-trust-strip__item { align-items: flex-start; }
  /* CAT-004-TRUST-CENTER-20260924: center the two-line preorder item only. */
  .bs-product__info .bs-trust-strip__item--preorder { align-items: center; text-align: center; }
CSS;
$new_trust = <<<'CSS'
  /* CAT-004-MOBILE-TRUST-CREDIT-20260924: vertically center icons beside wrapped product trust text. */
  .bs-product__info .bs-trust-strip__item { align-items: center; }
  /* CAT-004-TRUST-CENTER-20260924: keep the preorder truck centered beside left-aligned text. */
  .bs-product__info .bs-trust-strip__item--preorder { align-items: center; text-align: left; }
CSS;
$css = replace_exact($css, $old_trust, $new_trust, 'mobile_trust_rules');

$old_credit_scoped = <<<'CSS'
/* At 480px and below, 24px logos and compact gaps leave text room in half-width cards. */
@media (max-width: 480px) {
  .pay001-provider-row { gap: 6px; padding: 9px; }
  .pay001-product-credit__info .pay001-provider-row__mono, .pay001-product-credit__info .pay001-provider-row__pumb { width: 24px; height: 24px; }
  .pay001-product-credit__info .pay001-provider-row strong { overflow-wrap: anywhere; font-size: 12px; }
}
CSS;
$old_credit_fixture = <<<'CSS'
/* Keep two columns on narrow screens while letting long bank names wrap. */
@media (max-width: 480px) {
  .pay001-provider-row { gap: 6px; padding: 9px; }
  .pay001-provider-row__mono, .pay001-provider-row__pumb { width: 24px; height: 24px; }
  .pay001-provider-row strong { overflow-wrap: anywhere; font-size: 12px; }
}
CSS;
$new_credit = <<<'CSS'
/* Product mobile breakpoint: each provider gets the full row; desktop stays two columns. */
@media (max-width: 767.98px) {
  .pay001-product-credit__info { grid-template-columns: minmax(0, 1fr); }
  .pay001-provider-row + .pay001-provider-row { border-left: 0; border-top: 1px solid var(--bs-line, #d8dee8); }
  .pay001-product-credit__info .pay001-provider-row strong { overflow-wrap: anywhere; }
}
CSS;
$scoped_count = substr_count($css, $old_credit_scoped);
$fixture_count = substr_count($css, $old_credit_fixture);
if ($scoped_count + $fixture_count !== 1) fail_patch('anchor_count_mobile_credit_rules=' . ($scoped_count + $fixture_count) . ',expected=1');
$css = str_replace($scoped_count === 1 ? $old_credit_scoped : $old_credit_fixture, $new_credit, $css);

$old_stock_alert = <<<'TWIG'
  {% if error_stock and not pay002_cart_stock_warning %}
    <div class="alert alert-danger alert-dismissible"><i class="fa-solid fa-circle-exclamation"></i> {{ error_stock }} <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  {% endif %}
TWIG;
$cart = replace_exact($cart, $old_stock_alert, '  {# ' . CART_MARKER . ': stock warning is rendered once below the cart heading. #}', 'legacy_top_stock_alert');
$old_pay002_alert = <<<'TWIG'
  {% if pay002_cart_stock_warning %}
    <div class="alert alert-warning" role="alert" data-pay002-cart-stock-hint>
      <i class="fa-solid fa-circle-info"></i> <strong>Перевірте кількість товарів у кошику.</strong>
      Одного або кількох товарів немає в наявності в обраній кількості — зменште кількість або приберіть позицію.
    </div>
  {% endif %}
TWIG;
$cart = replace_exact($cart, $old_pay002_alert, '', 'pay002_top_stock_alert');
count_exact($cart, 'class="bs-cart-stock-notice"', 1, 'lower_stock_notice');
count_exact($cart, 'pay002_cart_stock_checkout_blocked or pay002_cart_minimum_checkout_blocked', 2, 'checkout_guards');

$token_pattern = '~(catalog/view/stylesheet/boostershop-ds\.css\?v=)([A-Za-z0-9._-]+)~';
$token_count = preg_match_all($token_pattern, $header);
if ($token_count !== 1) fail_patch('anchor_count_header_css_token=' . (string)$token_count . ',expected=1');
$header = preg_replace_callback($token_pattern, static function (array $match): string {
    return $match[1] . 'cat004-rd11-mobile-trust-20260924';
}, $header, 1);
if (!is_string($header)) fail_patch('header_css_token_replace_failed');

$updated = array(CSS => $css, CART => $cart, HEADER => $header);
$backup_dir = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His') . '-' . getmypid();
if (!mkdir($backup_dir, 0755, true) && !is_dir($backup_dir)) fail_patch('backup_dir_failed');
$backups = array();
foreach ($paths as $relative) {
    $backup = $backup_dir . '/' . basename($relative);
    if (file_put_contents($backup, $original[$relative], LOCK_EX) === false) fail_patch('backup_write_failed=' . $relative);
    $backups[$relative] = $backup;
    log_line('backup=' . $backup);
}
try {
    foreach ($paths as $relative) {
        write_checked($root . '/' . $relative, $updated[$relative]);
        log_line('changed=' . $relative);
    }
} catch (Throwable $error) {
    $restored = true;
    foreach ($paths as $relative) {
        if (!@copy($backups[$relative], $root . '/' . $relative)) $restored = false;
    }
    log_line('restore=' . ($restored ? 'ok' : 'failed'));
    throw $error;
}
log_line('php_lint=not_applicable(no PHP targets)');
log_line('done=ok');
@unlink(__FILE__);
