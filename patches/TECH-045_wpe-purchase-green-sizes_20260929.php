<?php
declare(strict_types=1);

/**
 * TECH-045 — WP-E: purchase green, checkout-confirm and card-button sizes, success-page label
 * =============================================================================
 * Task      : TECH-045 Stage 2, round 2, work package E (single patch)
 * Handoff   : handoffs/handoff_TECH-045_render-blocking-fonts-icons_20260925.md, section "Round 2"
 * Decisions : owner 2026-09-29 — purchase buttons #12883E, hover #15803D; checkout «Підтвердити
 *             замовлення» 17px in the new green; card «Купити» 16px, bolder, one line on mobile;
 *             «Переглянути замовлення» white text, fix the overriding rule
 * Author    : Claude Code · 2026-09-29
 * Risk      : LOW–MEDIUM — CSS values only, but one rule is on the checkout confirm button
 *             (risky zone: colour and font size only, no selector, markup or JS change)
 * DB changes: NONE
 * RUN FROM  : ~/public_html    ->    php TECH-045_wpe-purchase-green-sizes_20260929.php
 * Live base : tech045-live3-20260929.tar.gz (round 1 deployed); every anchor counted there
 *
 * 1. PURCHASE GREEN — two tokens in :root, used instead of repeated hex values
 *      --bs-buy        #12883E  white 4.55:1 (AA 4.5) — lightest same-hue green that passes
 *      --bs-buy-hover  #15803D  white 5.02:1 — hover / focus / open state
 *    --bs-green is NOT changed (it also colours text, borders and badges). --bs-green-dd (WP-D)
 *    is removed: after this patch nothing references it (the runner checks).
 *    Applied to every purchase/cart control, each at its existing source rule:
 *      boostershop-ds.css   .bs-btn-primary (+ :hover, now also :focus-visible)
 *                           header mini-cart trigger base / :hover :focus .show   (values only —
 *                             these two rules already carry !important; none is added)
 *                           #cart .mini-cart-checkout-btn base / :hover :focus    (values only, same)
 *                           #checkout-checkout.bs-co #checkout-confirm .btn-primary /
 *                             [data-bs-deferred-confirm] (was --bs-green, 3.3:1) + :hover, now also
 *                             :focus-visible
 *      booster-typography.css  #product-info #button-cart base / :hover :focus
 *    Not changed: legacy `.product-thumb .button .btn-buy-full` (ds.css ~663, stylesheet.css
 *    #28a745) — not rendered by the current thumb.twig; preorder colour; mobile toast.
 *
 * 2. CHECKOUT CONFIRM  font-size 15px → 17px in the same rule (weight stays 800). Label
 *    «Підтвердити замовлення →» measures ≈ 232 px at 17px/800 Manrope; the full-width button at
 *    390 px leaves ≈ 294 px of content width, so it stays on one line.
 *
 * 3. CARD «Купити»  `.bs-pcard__buy-btn` (ds.css:500) 14.5px/600 → 16px/700 on every breakpoint.
 *    The handoff expected a 2-column mobile grid with a 13px mobile rule; neither exists on live
 *    (2026-09-29): cards are one column below 768 px and no media rule sets this button's size
 *    (font-size comes from `.bs-btn` 14.5px). Narrowest card button measured on live: 291 px
 *    (product-page related row at 360 px); others 330 px (360/390/768 category, home) and
 *    ≥ 300 px at 1440. Longest label «Передзамовити» (preorder cards share the class) is 127 px
 *    at 16px/700 + 32 px padding = 159 px, so one size fits all breakpoints — no mobile rule is
 *    added. Product-page «У кошик» is a different element and is unchanged.
 *
 * 4. SUCCESS PAGE «Переглянути замовлення» (checkout/success.twig:135, an <a>)
 *    Root cause: ds.css:96 `.bs a { color: var(--bs-blue) }` — specificity (0,1,1) — outranks
 *    `.bs-btn-primary { color: #fff }` (0,1,0) on links, so the label computes #1E3A8A (reproduced
 *    on live with a test link inside body.bs). Fix at the button rule: `a.bs-btn-primary` joins
 *    the base selector — also (0,1,1), and later in the file than :96, so it wins by order —
 *    plus `a.bs-btn-primary:hover { text-decoration: none }` against `.bs a:hover`'s underline.
 *    Deliberately NOT `.bs a.bs-btn-primary` (0,2,1): that would beat the toast's contextual
 *    `.bs-toast__actions .bs-btn-primary` (0,2,0, white/ink on desktop, outline on mobile) and
 *    turn those buttons green. `.bs a` itself is not edited: excluding buttons there would
 *    raise its specificity or need :where(), and would recolour every other .bs-btn link.
 *    Side effect, intended: the two empty-state «continue» links (checkout/cart_list.twig:43,
 *    product/category.twig:171) also get white text instead of navy on green.
 *
 * UI/CSS DISCIPLINE
 *   Override history: TECH-045 WP-D (same rules, 2026-09-28), RD-12 toast/mini-cart patches
 *   (contextual .bs-btn-primary rules, untouched), CAT-004_RD-11_trust-cart-finish (same `.bs a`
 *   root cause on the cart page, fixed there with #checkout-cart scope). No !important added,
 *   no position/setTimeout, no new magic pixel values beyond the two sizes the owner set.
 *
 * SAFETY / ROLLBACK
 *   Anchors counted before any write; originals to _patch_backups/<patch>-<ts>/; CSS brace and
 *   comment balance gate; Twig parse gate on header.twig (control parse first); any failure
 *   restores all three files. Line endings preserved.
 *   Idempotent: marker TECH-045-WPE in both CSS files → already_applied=yes.
 *   ROLLBACK: copy the three files back from the backup folder, refresh the theme cache, Ctrl+F5.
 * =============================================================================
 */

const PATCH_ID = 'TECH-045_wpe-purchase-green-sizes_20260929';
const MARKER   = 'TECH-045-WPE';
const HEADER   = 'catalog/view/template/common/header.twig';
const DSCSS    = 'catalog/view/stylesheet/boostershop-ds.css';
const TYPOCSS  = 'catalog/view/stylesheet/booster-typography.css';
const TOKEN    = 'tech045-wpe-20260929';

function out(string $line): void { echo $line . PHP_EOL; }
function fail(string $reason): void { throw new RuntimeException($reason); }

/** Returns the file as LF text plus its original line ending. Mixed endings abort:
 *  re-encoding them would rewrite lines this patch does not own. */
function load_text(string $root, string $rel): array
{
    $path = $root . '/' . $rel;
    if (!is_file($path)) fail('target_not_found=' . $rel);
    if (!is_readable($path) || !is_writable($path)) fail('target_not_writable=' . $rel);
    $raw = file_get_contents($path);
    if (!is_string($raw)) fail('target_read_failed=' . $rel);
    $crlf = substr_count($raw, "\r\n");
    if ($crlf !== 0 && $crlf !== substr_count($raw, "\n")) fail('mixed_line_endings=' . $rel);
    if ($crlf === 0 && strpos($raw, "\r") !== false) fail('bare_cr_line_endings=' . $rel);
    return array('raw' => $raw, 'eol' => $crlf !== 0 ? "\r\n" : "\n", 'text' => str_replace("\r\n", "\n", $raw));
}

function encode_text(array $file, string $text): string
{
    return $file['eol'] === "\n" ? $text : str_replace("\n", "\r\n", $text);
}

function count_exact(string $haystack, string $needle, int $expected, string $label): void
{
    $actual = substr_count($haystack, $needle);
    if ($actual !== $expected) fail('anchor_count_' . $label . '=' . $actual . ',expected=' . $expected);
}

function replace_one(string $haystack, string $old, string $new, string $label): string
{
    count_exact($haystack, $old, 1, $label);
    return str_replace($old, $new, $haystack);
}

/** Convention 8: find the reference by its path, accept only a well-formed token (or
 *  none), and replace it wholesale. A malformed token leaves zero matches and aborts. */
function bust_token(string $header, string $assetPath, string $token, string $label): string
{
    $pattern = '~(' . preg_quote($assetPath, '~') . ')(\?v=[A-Za-z0-9._-]+)?(?=["\'])~';
    $found = preg_match_all($pattern, $header);
    if ($found !== 1) fail('anchor_count_token_' . $label . '=' . (string)$found . ',expected=1');
    $result = preg_replace($pattern, '$1?v=' . $token, $header, 1);
    if (!is_string($result)) fail('token_replace_failed_' . $label);
    return $result;
}

function css_balance_gate(string $before, string $after, string $label, int $bracesAdded): void
{
    foreach (array('{', '}') as $brace) {
        if (substr_count($after, $brace) !== substr_count($before, $brace) + $bracesAdded) fail('css_brace_balance_changed=' . $label);
    }
    if (substr_count($after, '/*') !== substr_count($after, '*/')) fail('css_comment_unbalanced=' . $label);
}

/** Parses templates with the site's own Twig source (not the Composer autoloader, whose
 *  platform check targets PHP 8.1). The unmodified file is parsed first as a control. */
function twig_gate(string $root, array $templates): void
{
    $config = @file_get_contents($root . '/config.php');
    if (!is_string($config) || !preg_match("/define\\(\\s*['\"]DIR_STORAGE['\"]\\s*,\\s*['\"]([^'\"]+)['\"]\\s*\\)/", $config, $match)) {
        fail('twig_gate_dir_storage_not_found');
    }
    $src = rtrim($match[1], '/\\') . '/vendor/twig/twig/src/';
    if (!is_file($src . 'Environment.php')) fail('twig_gate_twig_source_not_found');
    spl_autoload_register(static function (string $class) use ($src): void {
        if (strncmp($class, 'Twig\\', 5) !== 0) return;
        $file = $src . str_replace('\\', '/', substr($class, 5)) . '.php';
        if (is_file($file)) require_once $file;
    });
    foreach (array('core.php', 'debug.php', 'escaper.php', 'string_loader.php') as $resource) {
        if (is_file($src . 'Resources/' . $resource)) require_once $src . 'Resources/' . $resource;
    }
    $twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader(array()), array('cache' => false, 'autoescape' => false));
    foreach ($templates as $rel => $pair) {
        try {
            $twig->parse($twig->tokenize(new \Twig\Source($pair[0], $rel . '#unmodified')));
        } catch (Throwable $error) {
            fail('twig_gate_control_parse_failed=' . $rel . ':' . $error->getMessage());
        }
        try {
            $twig->parse($twig->tokenize(new \Twig\Source($pair[1], $rel)));
        } catch (Throwable $error) {
            fail('twig_gate_failed=' . $rel . ':' . $error->getMessage());
        }
        out('twig_gate=passed:' . $rel);
    }
}

function write_checked(string $path, string $bytes): void
{
    $temp = $path . '.' . PATCH_ID . '.tmp';
    if (file_put_contents($temp, $bytes, LOCK_EX) !== strlen($bytes)) { @unlink($temp); fail('temp_write_failed=' . $path); }
    if (!@rename($temp, $path)) {
        $copied = @copy($temp, $path);
        @unlink($temp);
        if (!$copied) fail('target_write_failed=' . $path);
    }
    if (file_get_contents($path) !== $bytes) fail('postwrite_verify_failed=' . $path);
}

set_exception_handler(static function (Throwable $error): void {
    out('error=' . $error->getMessage());
    out('done=failed');
    exit(1);
});

$root = rtrim((string)(getcwd() ?: __DIR__), '/\\');
out('patch=' . PATCH_ID);
out('cwd=' . $root);
out('time=' . date('c'));
out('db_changes=none');
if (!is_file($root . '/index.php') || !is_dir($root . '/catalog/view/template/common')) {
    fail('not_opencart_webroot — upload to ~/public_html and run from there');
}

$files = array();
foreach (array(DSCSS, TYPOCSS, HEADER) as $rel) {
    $files[$rel] = load_text($root, $rel);
    out('file_preflight=ok:' . $rel . ' eol=' . ($files[$rel]['eol'] === "\r\n" ? 'crlf' : 'lf'));
}

$done = array(strpos($files[DSCSS]['text'], MARKER) !== false, strpos($files[TYPOCSS]['text'], MARKER) !== false);
if ($done[0] !== $done[1]) fail('partial_marker_state — restore the files from the backup of the earlier run before retrying');
if ($done[0]) {
    count_exact($files[DSCSS]['text'], '--bs-green-dd', 0, 'final_old_token');
    out('already_applied=yes');
    out('done=ok');
    @unlink(__FILE__);
    exit(0);
}

/* ---- boostershop-ds.css ------------------------------------------------- */
$ds = $files[DSCSS]['text'];
count_exact($ds, '--bs-buy', 0, 'ds_token_name_free');
count_exact($ds, 'var(--bs-green-dd)', 3, 'ds_old_token_uses');

$ds = replace_one(
    $ds,
    "  --bs-green-dd: #166534; /* TECH-045-WPD: hover for buttons whose base is --bs-green-d (white 7.1:1). */\n",
    "  --bs-buy: #12883E;       /* " . MARKER . ": purchase/cart controls, white 4.55:1 (owner 2026-09-29). */\n"
    . "  --bs-buy-hover: #15803D; /* " . MARKER . ": their hover/focus/open state, white 5.02:1. */\n",
    'ds_tokens'
);
$ds = replace_one(
    $ds,
    "/* TECH-045-WPD: white on --bs-green was 3.3:1 (AA needs 4.5); --bs-green-d is 5.0:1. Purchase actions only. */\n"
    . ".bs-btn-primary { background: var(--bs-green-d); color: #fff; }\n"
    . ".bs-btn-primary:hover { background: var(--bs-green-dd); }\n",
    "/* " . MARKER . ": purchase green (owner 2026-09-29). a.bs-btn-primary is in the base rule because `.bs a` above\n"
    . "   (0,1,1) outranks .bs-btn-primary (0,1,0) on links — it painted «Переглянути замовлення» navy. Same\n"
    . "   specificity, later in the file, so it wins by order and still loses to contextual toast rules (0,2,0). */\n"
    . ".bs-btn-primary,\na.bs-btn-primary { background: var(--bs-buy); color: #fff; }\n"
    . ".bs-btn-primary:hover,\n.bs-btn-primary:focus-visible { background: var(--bs-buy-hover); }\n"
    . "a.bs-btn-primary:hover { text-decoration: none; }\n",
    'ds_btn_primary'
);
$ds = replace_one(
    $ds,
    "/* TECH-045-WPD: values only (was --bs-green, 3.3:1); the !important predates this change. */\n"
    . "body.bs #cart.bs-header__cart .mini-cart-trigger {\n  background: var(--bs-green-d) !important;\n  border-color: var(--bs-green-d) !important;\n",
    "/* " . MARKER . ": values only (purchase green tokens); the !important predates TECH-045. */\n"
    . "body.bs #cart.bs-header__cart .mini-cart-trigger {\n  background: var(--bs-buy) !important;\n  border-color: var(--bs-buy) !important;\n",
    'ds_trigger'
);
$ds = replace_one(
    $ds,
    "body.bs #cart.bs-header__cart .mini-cart-trigger.show {\n  background: var(--bs-green-dd) !important;\n  border-color: var(--bs-green-dd) !important;\n",
    "body.bs #cart.bs-header__cart .mini-cart-trigger.show {\n  background: var(--bs-buy-hover) !important;\n  border-color: var(--bs-buy-hover) !important;\n",
    'ds_trigger_hover'
);
$ds = replace_one(
    $ds,
    "body.bs #cart .mini-cart-checkout-btn {\n  background: var(--bs-green) !important;\n  border-color: var(--bs-green-d) !important;\n",
    "body.bs #cart .mini-cart-checkout-btn { /* " . MARKER . ": values only */\n  background: var(--bs-buy) !important;\n  border-color: var(--bs-buy) !important;\n",
    'ds_minicart_checkout'
);
$ds = replace_one(
    $ds,
    "body.bs #cart .mini-cart-checkout-btn:focus {\n  background: var(--bs-green-d) !important;\n  border-color: var(--bs-green-d) !important;\n",
    "body.bs #cart .mini-cart-checkout-btn:focus {\n  background: var(--bs-buy-hover) !important;\n  border-color: var(--bs-buy-hover) !important;\n",
    'ds_minicart_checkout_hover'
);
$ds = replace_one(
    $ds,
    "#checkout-checkout.bs-co #checkout-confirm .btn-primary,\n#checkout-checkout.bs-co #checkout-confirm [data-bs-deferred-confirm] {\n"
    . "  background: var(--bs-green);\n  border: 1px solid var(--bs-green);\n  border-radius: var(--bs-r-sm);\n  box-shadow: none;\n  font-size: 15px;\n",
    "/* " . MARKER . ": purchase green (was --bs-green, white 3.3:1) and 17px label — owner 2026-09-29. */\n"
    . "#checkout-checkout.bs-co #checkout-confirm .btn-primary,\n#checkout-checkout.bs-co #checkout-confirm [data-bs-deferred-confirm] {\n"
    . "  background: var(--bs-buy);\n  border: 1px solid var(--bs-buy);\n  border-radius: var(--bs-r-sm);\n  box-shadow: none;\n  font-size: 17px;\n",
    'ds_checkout_confirm'
);
$ds = replace_one(
    $ds,
    "#checkout-checkout.bs-co #checkout-confirm .btn-primary:hover {\n  background: var(--bs-green-d);\n  border-color: var(--bs-green-d);\n",
    "#checkout-checkout.bs-co #checkout-confirm .btn-primary:hover,\n#checkout-checkout.bs-co #checkout-confirm .btn-primary:focus-visible {\n"
    . "  background: var(--bs-buy-hover);\n  border-color: var(--bs-buy-hover);\n",
    'ds_checkout_confirm_hover'
);
$ds = replace_one(
    $ds,
    ".bs-pcard__buy-btn { width: 100%; font-weight: 600; text-transform: none; }\n",
    "/* " . MARKER . ": 16px/700 at every breakpoint — narrowest live card button is 291px, the longest label\n"
    . "   («Передзамовити») needs 159px incl. padding, so one size stays on one line everywhere. */\n"
    . ".bs-pcard__buy-btn { width: 100%; font-size: 16px; font-weight: 700; text-transform: none; }\n",
    'ds_card_buy'
);
count_exact($ds, '--bs-green-dd', 0, 'ds_old_token_left');
count_exact($ds, '.bs a  { color: var(--bs-blue); text-decoration: none; }', 1, 'ds_bs_a_unchanged');
if (strpos($ds, '.bs a  { color: var(--bs-blue)') > strpos($ds, "a.bs-btn-primary { background: var(--bs-buy)")) {
    fail('ds_order_link_rule_after_button_rule');
}
css_balance_gate($files[DSCSS]['text'], $ds, DSCSS, 1); // +1: a.bs-btn-primary:hover

/* ---- booster-typography.css -------------------------------------------- */
$typo = $files[TYPOCSS]['text'];
$typo = replace_one(
    $typo,
    "/* TECH-045-WPD: product buy button is styled here, not by .bs-btn-primary. #16a34a gave 3.3:1 with white. */\n"
    . "#product-info #button-cart {\n  display: inline-flex;\n  flex: 0 0 auto;\n  align-items: center;\n  justify-content: center;\n"
    . "  gap: 8px;\n  min-width: 152px;\n  border-color: #15803d;\n  background: #15803d;\n",
    "/* " . MARKER . ": product buy button is styled here, not by .bs-btn-primary — purchase green tokens (boostershop-ds.css). */\n"
    . "#product-info #button-cart {\n  display: inline-flex;\n  flex: 0 0 auto;\n  align-items: center;\n  justify-content: center;\n"
    . "  gap: 8px;\n  min-width: 152px;\n  border-color: var(--bs-buy, #12883E);\n  background: var(--bs-buy, #12883E);\n",
    'typo_button_cart'
);
$typo = replace_one(
    $typo,
    "#product-info #button-cart:focus {\n  border-color: #166534;\n  background: #166534;\n",
    "#product-info #button-cart:focus {\n  border-color: var(--bs-buy-hover, #15803D);\n  background: var(--bs-buy-hover, #15803D);\n",
    'typo_button_cart_hover'
);
css_balance_gate($files[TYPOCSS]['text'], $typo, TYPOCSS, 0);

/* ---- header.twig: cache tokens ------------------------------------------ */
$header = $files[HEADER]['text'];
$header = bust_token($header, 'catalog/view/stylesheet/boostershop-ds.css', TOKEN, 'ds_css');
$header = bust_token($header, 'catalog/view/stylesheet/booster-typography.css', TOKEN, 'typography_css');

twig_gate($root, array(HEADER => array($files[HEADER]['text'], $header)));
out('php_lint=not_applicable(no PHP targets)');

/* ---- backup, write, verify ---------------------------------------------- */
$updated = array(
    DSCSS   => encode_text($files[DSCSS], $ds),
    TYPOCSS => encode_text($files[TYPOCSS], $typo),
    HEADER  => encode_text($files[HEADER], $header),
);
$backupDir = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
foreach ($updated as $rel => $bytes) {
    $dest = $backupDir . '/' . $rel;
    if (!is_dir(dirname($dest)) && !mkdir(dirname($dest), 0755, true) && !is_dir(dirname($dest))) fail('backup_dir_failed=' . dirname($dest));
    if (file_put_contents($dest, $files[$rel]['raw'], LOCK_EX) !== strlen($files[$rel]['raw'])) fail('backup_write_failed=' . $rel);
    out('backup=' . substr($dest, strlen($root) + 1));
}
try {
    foreach ($updated as $rel => $bytes) {
        write_checked($root . '/' . $rel, $bytes);
        out('changed=' . $rel);
    }
} catch (Throwable $error) {
    $restored = true;
    foreach ($updated as $rel => $bytes) {
        if (file_put_contents($root . '/' . $rel, $files[$rel]['raw'], LOCK_EX) !== strlen($files[$rel]['raw'])) $restored = false;
    }
    out('restore=' . ($restored ? 'ok' : 'FAILED — copy the files back from ' . $backupDir));
    throw $error;
}

out('already_applied=no');
out('done=ok');
out('next=refresh the OpenCart theme cache, Ctrl+F5, run the WP-E checks, then bs-checkout-smoke');
@unlink(__FILE__);
