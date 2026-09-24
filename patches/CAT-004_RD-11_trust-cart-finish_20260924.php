<?php
declare(strict_types=1);

/**
 * CAT-004 / RD-11 final visual correction from owner screenshots, 2026-09-24.
 * Center the text lines inside each product trust item at every breakpoint.
 * Keep cart checkout text white, reduce the gap between its two summary
 * buttons from 30px (14px grid gap + 16px margin) to 14px, and label both
 * desktop and sticky-mobile checkout links "Оформити замовлення".
 *
 * Evidence: the live EB-03 page has left-aligned trust spans. The live cart
 * computes checkout text as rgb(30,58,138) despite the white source rule,
 * because boostershop-ds.css `.bs a` outranks `.bs-cart-checkout`.
 * The cart shell source is RD-11_cart-page_20260921.php; the live page matches
 * its button rules. The preceding CAT-004_RD-11_mobile-trust-cart-alert runner
 * must have succeeded before this patch.
 *
 * Override history: CAT-004_info-column-reflow, CAT-004_trust-credit-copy,
 * CAT-004_RD-11_mobile-trust-cart-alert affect the product trust selector.
 * The shared homepage strip is distinct and remains untouched. This runner
 * edits the product span source rule and RD-11 cart shell source rules. It
 * introduces no !important, JS, or global trust override.
 * Rollback: restore the four logged backups, then clear OpenCart cache.
 */

const PATCH_ID = 'CAT-004_RD-11_trust-cart-finish_20260924';
const CSS = 'catalog/view/stylesheet/boostershop-ds.css';
const CART_SHELL = 'catalog/view/template/checkout/cart.twig';
const CART_LIST = 'catalog/view/template/checkout/cart_list.twig';
const HEADER = 'catalog/view/template/common/header.twig';
const CSS_MARKER = 'CAT-004-TRUST-TEXT-CENTER-20260924';
const SHELL_MARKER = 'RD-11-CART-BUTTONS-20260924';
const LIST_MARKER = 'RD-11-CHECKOUT-LABEL-20260924';

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
$paths = array(CSS, CART_SHELL, CART_LIST, HEADER);
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
$shell = $original[CART_SHELL];
$list = $original[CART_LIST];
$header = $original[HEADER];
$done = array(strpos($css, CSS_MARKER) !== false, strpos($shell, SHELL_MARKER) !== false, strpos($list, LIST_MARKER) !== false);
if (count(array_unique($done)) !== 1) fail_patch('partial_patch_marker_state');
if ($done[0]) {
    count_exact($css, CSS_MARKER, 1, 'css_marker_final');
    count_exact($shell, SHELL_MARKER, 1, 'shell_marker_final');
    count_exact($list, LIST_MARKER, 1, 'list_marker_final');
    count_exact($list, '>Оформити замовлення</a>', 2, 'checkout_labels_final');
    log_line('already_applied=yes');
    log_line('done=ok');
    @unlink(__FILE__);
    exit(0);
}

$old_trust = <<<'CSS'
.bs-product__info .bs-trust-strip__item span {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--bs-ink-2, #374151);
  line-height: 1.3;
  text-wrap: balance;
}
CSS;
$new_trust = <<<'CSS'
/* CAT-004-TRUST-TEXT-CENTER-20260924: center wrapped label lines beside their icons. */
.bs-product__info .bs-trust-strip__item span {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--bs-ink-2, #374151);
  line-height: 1.3;
  text-wrap: balance;
  text-align: center;
}
CSS;
$css = replace_exact($css, $old_trust, $new_trust, 'product_trust_text');
count_exact($css, 'CAT-004-MOBILE-TRUST-CREDIT-20260924', 1, 'prior_mobile_patch');

$old_continue = '.bs-cart-continue{min-height:44px;margin-top:16px;border:1px solid var(--bs-blue);border-radius:var(--bs-r-sm);background:#fff;color:var(--bs-blue);font-weight:700}';
$new_continue = '/* ' . SHELL_MARKER . ': 14px grid gap between summary buttons; keep the line-list button margin. */' . "\n"
    . '.bs-cart-continue{min-height:44px;margin-top:0;border:1px solid var(--bs-blue);border-radius:var(--bs-r-sm);background:#fff;color:var(--bs-blue);font-weight:700}'
    . '.bs-cart-lines > .bs-cart-continue{margin-top:16px}';
$shell = replace_exact($shell, $old_continue, $new_continue, 'continue_spacing');

$old_checkout = '.bs-cart-checkout{display:inline-flex;min-height:48px;align-items:center;justify-content:center;border:1px solid var(--bs-green);border-radius:var(--bs-r-sm);background:var(--bs-green);color:#fff;font-weight:700;text-decoration:none}';
$new_checkout = '#checkout-cart .bs-cart-checkout{display:inline-flex;min-height:48px;align-items:center;justify-content:center;border:1px solid var(--bs-green);border-radius:var(--bs-r-sm);background:var(--bs-green);color:#fff;font-weight:700;text-decoration:none}';
$shell = replace_exact($shell, $old_checkout, $new_checkout, 'checkout_color_specificity');
$shell = replace_exact($shell,
    '.bs-cart-checkout:hover{background:var(--bs-green-d);border-color:var(--bs-green-d);color:#fff}',
    '#checkout-cart .bs-cart-checkout:hover{background:var(--bs-green-d);border-color:var(--bs-green-d);color:#fff}',
    'checkout_hover');
$shell = replace_exact($shell,
    '.bs-cart-checkout[aria-disabled=true]{border-color:var(--bs-line);background:var(--bs-line-2);color:var(--bs-ink-2);cursor:not-allowed;pointer-events:none}',
    '#checkout-cart .bs-cart-checkout[aria-disabled=true]{border-color:var(--bs-line);background:var(--bs-line-2);color:var(--bs-ink-2);cursor:not-allowed;pointer-events:none}',
    'checkout_disabled');

count_exact($list, '>Оформити</a>', 2, 'checkout_labels_old');
$list = replace_exact($list, '  {% set bs_free = ', '  {# ' . LIST_MARKER . ' #}' . "\n" . '  {% set bs_free = ', 'checkout_label_marker');
$list = str_replace('>Оформити</a>', '>Оформити замовлення</a>', $list);
count_exact($list, '>Оформити замовлення</a>', 2, 'checkout_labels_new');
count_exact($list, 'pay002_cart_stock_checkout_blocked or pay002_cart_minimum_checkout_blocked', 2, 'checkout_guards');

$token_pattern = '~(catalog/view/stylesheet/boostershop-ds\.css\?v=)([A-Za-z0-9._-]+)~';
$token_count = preg_match_all($token_pattern, $header);
if ($token_count !== 1) fail_patch('anchor_count_header_css_token=' . (string)$token_count . ',expected=1');
$header = preg_replace_callback($token_pattern, static function (array $match): string {
    return $match[1] . 'cat004-rd11-trust-cart-finish-20260924';
}, $header, 1);
if (!is_string($header)) fail_patch('header_css_token_replace_failed');

$updated = array(CSS => $css, CART_SHELL => $shell, CART_LIST => $list, HEADER => $header);
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
