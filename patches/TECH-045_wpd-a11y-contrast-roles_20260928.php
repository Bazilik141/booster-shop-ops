<?php
declare(strict_types=1);

/**
 * TECH-045 — WP-D: contrast and ARIA-role defects from the home accessibility audit
 * =============================================================================
 * Task      : TECH-045 Stage 2, round 1, work package D of A → B → C0 → C → D
 * Handoff   : handoffs/handoff_TECH-045_render-blocking-fonts-icons_20260925.md §4 WP-D
 * Decisions : owner 2026-09-28 (3a): «До каталогу» in the empty mini-cart becomes
 *             bs-btn-secondary, then the purchase green is darkened
 * Author    : Claude Code · 2026-09-28
 * Risk      : LOW — colour values edited at their source rules, one class swap, one tag swap.
 *             cart.twig and cookie.twig render on checkout too (cosmetic only).
 * DB changes: NONE
 * RUN FROM  : ~/public_html    ->    php TECH-045_wpd-a11y-contrast-roles_20260928.php
 *
 * EVIDENCE — axe-core 4.10 on live https://boostershop.website/ (2026-09-28), matching the
 * PSI report 1q7sc3ulq3 list. Ratio before → after (after = measured with the new values
 * injected into the live page; axe then reported zero contrast / role violations):
 *   header mini-cart trigger «Мій кошик»   #fff on #16A34A 3.29 → on #15803D 5.02
 *   .bs-pcard__buy-btn «Купити» ×4          #fff on #16A34A 3.29 → on #15803D 5.02
 *   mini-cart empty «До каталогу»          #fff on #16A34A 3.29 → bs-btn-secondary (ink on white)
 *   footer © line + «Лінія підтримки»      #6B7280 on #0F1115 3.90 → #9CA3AF 7.44
 *   cookie «Так, я згоден!»                #fff on #229AC8 (×.95 bar opacity) 3.02 → on #176688 5.69
 *   cookie «Ні, дякую!»                    #fff on #F8F9FA 1.05 → #1F2937 on #F8F9FA 11.96
 *       (text inherited white from `#cookie div` stylesheet.css:481 via `#cookie a {color:inherit}`
 *        cookie.twig:29 — the button label is effectively invisible today)
 *   aria-allowed-role: <aside class="bs-mini-cart__panel" role="dialog"> → <div>
 *   Product page (not on home, same defect): #product-info #button-cart hardcodes #16a34a in
 *   booster-typography.css:186-187 → #15803d (hover #15803d → #166534).
 *
 * ROOT CAUSES AND CHANGES
 *   boostershop-ds.css
 *     :53   new token --bs-green-dd #166534 (7.13:1 with white) — hover for buttons whose base
 *           is now --bs-green-d. --bs-green and --bs-green-hover are NOT changed: they also
 *           colour text, borders and badges that are not part of this audit.
 *     :108  .bs-btn-primary        background --bs-green → --bs-green-d; hover --bs-green-d → --bs-green-dd
 *     :2204 mini-cart trigger     background/border --bs-green → --bs-green-d; hover → --bs-green-dd.
 *           These two rules already carry !important (they must beat the legacy #cart rules);
 *           only their values change — no !important is added.
 *     :811, :2264  .bs-footer__bottom color #6B7280 → #9CA3AF (the footer's own text colour,
 *           footer.bs-footer and .bs-footer__col a). Both rules are edited so they stay in step.
 *   booster-typography.css:179-199  #button-cart colours as above.
 *   cart.twig   aside → div (role="dialog" is not allowed on <aside>); «До каталогу» class swap.
 *               No CSS or JS selects aside.bs-mini-cart* — every rule and the RD-12 swipe
 *               handlers use the .bs-mini-cart__panel class (checked in both live archives).
 *   cookie.twig «Так, я згоден!»: bootstrap `bg-primary` (an !important utility) replaced by a
 *               local class bs-cookie-agree styled in the template's own <style> — this avoids
 *               stacking an !important override on a utility. «Ні, дякую!»: explicit colour
 *               for .btn-light inside #cookie. Hover/focus/active keep the same colours
 *               (a bare .btn has a transparent hover background).
 *   header.twig cache-bust boostershop-ds.css and booster-typography.css (convention 8).
 *
 * PURCHASE-COLOUR RULE — every remaining .bs-btn-primary consumer is a purchase/cart action:
 *   header + cart.twig mini-cart trigger, «Оформити замовлення», product «У кошик» and sticky
 *   «Купити», thumb.twig «Купити», common.js toast cart/checkout links.
 *   Recorded, NOT changed (owner decision 3a; checkout/category scope):
 *     checkout/cart_list.twig:43  empty cart {{ button_continue }}
 *     product/category.twig:171   empty category {{ button_continue }}
 *     checkout/success.twig:135   «Переглянути замовлення» (logged-in only)
 *   These three non-purchase buttons stay green, now the darker #15803D.
 *
 * SKIPPED BY THE HANDOFF RULE (styles target elements, not classes)
 *   Heading order: «Рекомендовані товари» <h3> — layout keyed on
 *   `#content > .row.mb-3 + h3 + .row` (boostershop-ds.css:1299-1364) and `.bs h3` (:89/:94);
 *   footer <h4> — `.bs-footer__col h4` (:792, :2246). Changing the tag would break them.
 *
 * NOT IN THIS AUDIT, recorded only
 *   Mobile toast `.bs-toast` white on --bs-green (ds.css:5856) and `.bs-footer__legal` #6B7280
 *   (ds.css:788) — neither renders on the audited page. header.twig:331 <aside class="bs-menu__panel"
 *   role="dialog"> has the same role defect but is `hidden` until the burger opens.
 *
 * SAFETY / ROLLBACK
 *   Anchors counted before any write; originals to _patch_backups/<patch>-<ts>/; Twig parse
 *   gate on the three templates (control parse of the unmodified files first); CSS
 *   brace/comment balance gate; any failure restores all five files. Line endings preserved.
 *   Idempotent: marker TECH-045-WPD in ds.css, typography CSS, cart.twig, cookie.twig.
 *   ROLLBACK: copy the five files back from the backup folder, refresh the theme cache, Ctrl+F5.
 * =============================================================================
 */

const PATCH_ID = 'TECH-045_wpd-a11y-contrast-roles_20260928';
const MARKER   = 'TECH-045-WPD';
const HEADER   = 'catalog/view/template/common/header.twig';
const CARTTWIG = 'catalog/view/template/common/cart.twig';
const COOKIE   = 'catalog/view/template/common/cookie.twig';
const DSCSS    = 'catalog/view/stylesheet/boostershop-ds.css';
const TYPOCSS  = 'catalog/view/stylesheet/booster-typography.css';
const TOKEN    = 'tech045-wpd-20260928';

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
foreach (array(DSCSS, TYPOCSS, CARTTWIG, COOKIE, HEADER) as $rel) {
    $files[$rel] = load_text($root, $rel);
    out('file_preflight=ok:' . $rel . ' eol=' . ($files[$rel]['eol'] === "\r\n" ? 'crlf' : 'lf'));
}

$state = array();
foreach (array(DSCSS, TYPOCSS, CARTTWIG, COOKIE) as $rel) $state[$rel] = strpos($files[$rel]['text'], MARKER) !== false;
if (count(array_unique($state)) !== 1) fail('partial_marker_state — restore the files from the backup of the earlier run before retrying');
if ($state[DSCSS]) {
    count_exact($files[CARTTWIG]['text'], '<aside', 0, 'final_aside');
    out('already_applied=yes');
    out('done=ok');
    @unlink(__FILE__);
    exit(0);
}

/* ---- boostershop-ds.css ------------------------------------------------- */
$ds = $files[DSCSS]['text'];
count_exact($ds, '--bs-green-dd', 0, 'ds_token_free');
$ds = replace_one(
    $ds,
    "  --bs-green-d: #15803D;\n",
    "  --bs-green-d: #15803D;\n"
    . "  --bs-green-dd: #166534; /* " . MARKER . ": hover for buttons whose base is --bs-green-d (white 7.1:1). */\n",
    'ds_green_token'
);
$ds = replace_one(
    $ds,
    ".bs-btn-primary { background: var(--bs-green); color: #fff; }\n.bs-btn-primary:hover { background: var(--bs-green-d); }\n",
    "/* " . MARKER . ": white on --bs-green was 3.3:1 (AA needs 4.5); --bs-green-d is 5.0:1. Purchase actions only. */\n"
    . ".bs-btn-primary { background: var(--bs-green-d); color: #fff; }\n.bs-btn-primary:hover { background: var(--bs-green-dd); }\n",
    'ds_btn_primary'
);
$ds = replace_one(
    $ds,
    "body.bs #cart.bs-header__cart .mini-cart-trigger {\n  background: var(--bs-green) !important;\n  border-color: var(--bs-green) !important;\n",
    "/* " . MARKER . ": values only (was --bs-green, 3.3:1); the !important predates this change. */\n"
    . "body.bs #cart.bs-header__cart .mini-cart-trigger {\n  background: var(--bs-green-d) !important;\n  border-color: var(--bs-green-d) !important;\n",
    'ds_trigger'
);
$ds = replace_one(
    $ds,
    "body.bs #cart.bs-header__cart .mini-cart-trigger.show {\n  background: var(--bs-green-d) !important;\n  border-color: var(--bs-green-d) !important;\n",
    "body.bs #cart.bs-header__cart .mini-cart-trigger.show {\n  background: var(--bs-green-dd) !important;\n  border-color: var(--bs-green-dd) !important;\n",
    'ds_trigger_hover'
);
$ds = replace_one(
    $ds,
    ".bs-footer__bottom {\n  max-width: 1240px;\n  margin: 0 auto;\n  border-top: 1px solid #1F2937;\n  padding-top: 18px;\n"
    . "  display: flex;\n  justify-content: space-between;\n  font-size: 12px;\n  color: #6B7280;\n",
    "/* " . MARKER . ": #6B7280 on #0F1115 was 3.9:1; #9CA3AF (the footer's own text colour) is 7.4:1. */\n"
    . ".bs-footer__bottom {\n  max-width: 1240px;\n  margin: 0 auto;\n  border-top: 1px solid #1F2937;\n  padding-top: 18px;\n"
    . "  display: flex;\n  justify-content: space-between;\n  font-size: 12px;\n  color: #9CA3AF;\n",
    'ds_footer_bottom'
);
$ds = replace_one(
    $ds,
    "body.bs .bs-footer__bottom {\n  border-top: 1px solid #1F2937;\n  color: #6B7280;\n",
    "body.bs .bs-footer__bottom {\n  border-top: 1px solid #1F2937;\n  color: #9CA3AF; /* " . MARKER . " */\n",
    'ds_footer_bottom_bs'
);
css_balance_gate($files[DSCSS]['text'], $ds, DSCSS, 0);

/* ---- booster-typography.css -------------------------------------------- */
$typo = $files[TYPOCSS]['text'];
$typo = replace_one(
    $typo,
    "#product-info #button-cart {\n  display: inline-flex;\n  flex: 0 0 auto;\n  align-items: center;\n  justify-content: center;\n"
    . "  gap: 8px;\n  min-width: 152px;\n  border-color: #16a34a;\n  background: #16a34a;\n",
    "/* " . MARKER . ": product buy button is styled here, not by .bs-btn-primary. #16a34a gave 3.3:1 with white. */\n"
    . "#product-info #button-cart {\n  display: inline-flex;\n  flex: 0 0 auto;\n  align-items: center;\n  justify-content: center;\n"
    . "  gap: 8px;\n  min-width: 152px;\n  border-color: #15803d;\n  background: #15803d;\n",
    'typo_button_cart'
);
$typo = replace_one(
    $typo,
    "#product-info #button-cart:focus {\n  border-color: #15803d;\n  background: #15803d;\n",
    "#product-info #button-cart:focus {\n  border-color: #166534;\n  background: #166534;\n",
    'typo_button_cart_hover'
);
css_balance_gate($files[TYPOCSS]['text'], $typo, TYPOCSS, 0);

/* ---- cart.twig ---------------------------------------------------------- */
$cart = $files[CARTTWIG]['text'];
count_exact($cart, '<aside', 1, 'cart_aside_open_total');
count_exact($cart, '</aside>', 1, 'cart_aside_close_total');
$cart = replace_one(
    $cart,
    "{# RD-12 toast layout and swipe fix 2026-09-22. #}\n",
    "{# RD-12 toast layout and swipe fix 2026-09-22. #}\n"
    . "{# " . MARKER . " (2026-09-28): the panel element is div, not aside — role=dialog is not allowed on aside; «До каталогу» is\n"
    . "   navigation, so it uses bs-btn-secondary (green is for purchase actions). #}\n",
    'cart_marker_anchor'
);
$cart = replace_one(
    $cart,
    '<aside class="bs-mini-cart__panel" role="dialog" aria-modal="true" aria-label="Кошик">',
    '<div class="bs-mini-cart__panel" role="dialog" aria-modal="true" aria-label="Кошик">',
    'cart_panel_open'
);
$cart = replace_one($cart, '</aside>', '</div>', 'cart_panel_close');
$cart = replace_one(
    $cart,
    '<button type="button" class="bs-btn bs-btn-primary" data-bs-mini-cart-close>До каталогу</button>',
    '<button type="button" class="bs-btn bs-btn-secondary" data-bs-mini-cart-close>До каталогу</button>',
    'cart_empty_cta'
);
if (substr_count($cart, '<div') - substr_count($cart, '</div>') !== substr_count($files[CARTTWIG]['text'], '<div') - substr_count($files[CARTTWIG]['text'], '</div>')) {
    fail('cart_div_balance_changed');
}

/* ---- cookie.twig -------------------------------------------------------- */
$cookie = $files[COOKIE]['text'];
$cookie = replace_one(
    $cookie,
    'class="btn bg-primary btn-block text-white" data-cookie-action="1"',
    'class="btn bs-cookie-agree btn-block text-white" data-cookie-action="1"',
    'cookie_agree_class'
);
$cookie = replace_one(
    $cookie,
    "#cookie .cookie-actions .btn {\n  margin: 0;\n  min-width: 132px;\n  width: auto;\n}\n",
    "#cookie .cookie-actions .btn {\n  margin: 0;\n  min-width: 132px;\n  width: auto;\n}\n"
    . "/* " . MARKER . " (2026-09-28): AA contrast. Agree was white on bootstrap bg-primary #229AC8 (3.0:1 under the\n"
    . "   bar's 0.95 opacity); decline inherited white from `#cookie div` onto btn-light (1.05:1). A bare .btn has a\n"
    . "   transparent hover background, so hover/focus/active are set too. */\n"
    . "#cookie .cookie-actions .bs-cookie-agree,\n"
    . "#cookie .cookie-actions .bs-cookie-agree:hover,\n"
    . "#cookie .cookie-actions .bs-cookie-agree:focus,\n"
    . "#cookie .cookie-actions .bs-cookie-agree:active {\n  background: #176688;\n  border-color: #176688;\n}\n"
    . "#cookie .cookie-actions .btn-light {\n  color: #1F2937;\n}\n",
    'cookie_style'
);
count_exact($cookie, 'class="btn bg-primary', 0, 'cookie_bg_primary_class_left');
count_exact($cookie, 'data-cookie-action="1"', 1, 'cookie_agree_hook');
count_exact($cookie, 'data-cookie-action="0"', 1, 'cookie_decline_hook');

/* ---- header.twig: cache tokens ------------------------------------------ */
$header = $files[HEADER]['text'];
$header = bust_token($header, 'catalog/view/stylesheet/boostershop-ds.css', TOKEN, 'ds_css');
$header = bust_token($header, 'catalog/view/stylesheet/booster-typography.css', TOKEN, 'typography_css');

twig_gate($root, array(
    CARTTWIG => array($files[CARTTWIG]['text'], $cart),
    COOKIE   => array($files[COOKIE]['text'], $cookie),
    HEADER   => array($files[HEADER]['text'], $header),
));
out('php_lint=not_applicable(no PHP targets)');

/* ---- backup, write, verify ---------------------------------------------- */
$updated = array(
    DSCSS    => encode_text($files[DSCSS], $ds),
    TYPOCSS  => encode_text($files[TYPOCSS], $typo),
    CARTTWIG => encode_text($files[CARTTWIG], $cart),
    COOKIE   => encode_text($files[COOKIE], $cookie),
    HEADER   => encode_text($files[HEADER], $header),
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
out('next=refresh the OpenCart theme cache, Ctrl+F5, then run the WP-D checks and bs-checkout-smoke (TECH-045 report)');
@unlink(__FILE__);
