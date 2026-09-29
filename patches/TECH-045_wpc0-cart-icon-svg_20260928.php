<?php
declare(strict_types=1);

/**
 * TECH-045 — WP-C0: product add-to-cart icon → inline SVG
 * =============================================================================
 * Task      : TECH-045 Stage 2, round 1, work package C0 of A → B → C0 → C → D
 * Handoff   : handoffs/handoff_TECH-045_render-blocking-fonts-icons_20260925.md §4 WP-C0
 *             (scope extension; owner decision 2026-09-28, 2a)
 * Author    : Claude Code · 2026-09-28
 * Risk      : LOW — one string literal in a product-page script, one additive CSS rule,
 *             two cache tokens. No logic change, no DB.
 * DB changes: NONE
 * MUST RUN  : before TECH-045_wpc-fontawesome-nonblocking_20260928.php (WP-C checks for it)
 * RUN FROM  : ~/public_html    ->    php TECH-045_wpc0-cart-icon-svg_20260928.php
 *
 * ROOT CAUSE
 *   catalog/view/javascript/booster-product-polish.js:48 rewrites #button-cart to
 *   <i class="fa-solid fa-cart-shopping"> + "У кошик". Measured live 2026-09-28 on
 *   /product/Pokemon-boosters-Mega-Symphonia: at 1440×900 the icon sits at y 453–469, above
 *   the fold (390×844: y 900, just below). With FontAwesome loaded non-blocking (WP-C) the
 *   <i> would paint empty and zero-width first, then push the label sideways when the
 *   stylesheet arrives. It also ties the buy button to the 155 KiB fa-solid-900.woff2.
 *
 * CHANGES
 *   booster-product-polish.js   the <i> becomes an inline 18×18 SVG cart glyph
 *                               (fill="currentColor", aria-hidden, focusable="false"). The
 *                               glyph is drawn for this patch, not copied from FontAwesome.
 *                               The rest of the line — the "У кошик" label — is unchanged.
 *   booster-typography.css      new rule `#product-info #button-cart svg` (fixed 18×18 box,
 *                               flex: 0 0 auto) placed directly after the existing
 *                               `#product-info #button-cart i` rule, which stays (harmless).
 *   header.twig                 booster-product-polish.js gets ?v= (it had none) and the
 *                               booster-typography.css token is replaced (convention 8).
 *   Measured by DOM substitution on live 2026-09-28, 1440 px: button 435×44 → 435×44,
 *   label offset 197.2 px → 197.2 px, icon box 18×16 → 18×18.
 *
 * NOT FIXED — owner decision needed (handoff §4 WP-C0 ⚠)
 *   The script overwrites #button-cart unconditionally. product.twig:371 renders preorder
 *   products as "Передзамовити" (class bs-btn-preorder); live
 *   /product/Duel-Masters-Boosters-DM24-RP3 shows "У кошик" after the script runs. This
 *   patch keeps that behaviour byte-for-byte outside the icon markup.
 *
 * UI/CSS DISCIPLINE
 *   Additive rule next to its sibling, no !important, no override of an existing
 *   declaration. Override history for #button-cart styling: booster-typography.css:179–212
 *   only. Markup: product.twig:896 (ST-2a.9) saves the button's HTML before add-to-cart and
 *   restores it afterwards, so the SVG survives the loading state unchanged.
 *
 * SAFETY / ROLLBACK
 *   Anchors counted before any write; originals to _patch_backups/<patch>-<ts>/; Twig parse
 *   gate on header.twig; CSS brace/comment balance gate; any failure restores all three.
 *   Idempotent: marker TECH-045-WPC0 in the JS and CSS → already_applied=yes.
 *   ROLLBACK: copy the three files back from the backup folder, refresh the theme cache, Ctrl+F5.
 * =============================================================================
 */

const PATCH_ID = 'TECH-045_wpc0-cart-icon-svg_20260928';
const MARKER   = 'TECH-045-WPC0';
const HEADER   = 'catalog/view/template/common/header.twig';
const POLISHJS = 'catalog/view/javascript/booster-product-polish.js';
const TYPOCSS  = 'catalog/view/stylesheet/booster-typography.css';
const TOKEN    = 'tech045-wpc0-20260928';

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

$files = array(HEADER => load_text($root, HEADER), POLISHJS => load_text($root, POLISHJS), TYPOCSS => load_text($root, TYPOCSS));
foreach ($files as $rel => $file) out('file_preflight=ok:' . $rel . ' eol=' . ($file['eol'] === "\r\n" ? 'crlf' : 'lf'));

$done = array(strpos($files[POLISHJS]['text'], MARKER) !== false, strpos($files[TYPOCSS]['text'], MARKER) !== false);
if ($done[0] !== $done[1]) fail('partial_marker_state — restore the files from the backup of the earlier run before retrying');
if ($done[0]) {
    count_exact($files[POLISHJS]['text'], 'fa-cart-shopping', 0, 'final_fa_icon');
    out('already_applied=yes');
    out('done=ok');
    @unlink(__FILE__);
    exit(0);
}

/* ---- booster-product-polish.js ----------------------------------------- */
$svg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">'
    . '<path d="M2 3a1 1 0 0 1 1-1h1.6a1.5 1.5 0 0 1 1.46 1.16L6.3 4H21a1 1 0 0 1 .97 1.24l-1.8 7.2A2 2 0 0 1 18.23 14H8.1l.3 1.5H18a1 1 0 1 1 0 2H7.6a1 1 0 0 1-.98-.8L4.2 4H3a1 1 0 0 1-1-1z"/>'
    . '<circle cx="8.5" cy="20.5" r="1.5"/><circle cx="17" cy="20.5" r="1.5"/></svg>';
$js = $files[POLISHJS]['text'];
$js = replace_one(
    $js,
    "      cartButton.innerHTML = '<i class=\"fa-solid fa-cart-shopping\" aria-hidden=\"true\"></i><span>У кошик</span>';\n",
    "      // " . MARKER . " (2026-09-28): inline SVG instead of the FontAwesome <i>, so the buy button never\n"
    . "      // waits for all.min.css (non-blocking since TECH-045 WP-C). Fixed 18x18 box in booster-typography.css.\n"
    . "      cartButton.innerHTML = '" . $svg . "<span>У кошик</span>';\n",
    'js_cart_icon'
);
count_exact($js, 'fa-', 0, 'js_fontawesome_left');

/* ---- booster-typography.css -------------------------------------------- */
$css = $files[TYPOCSS]['text'];
$css = replace_one(
    $css,
    "#product-info #button-cart i {\n  font-size: 16px;\n}\n",
    "#product-info #button-cart i {\n  font-size: 16px;\n}\n\n"
    . "/* " . MARKER . ": inline SVG cart glyph written by booster-product-polish.js; fixed box so the label never reflows. */\n"
    . "#product-info #button-cart svg {\n  flex: 0 0 auto;\n  width: 18px;\n  height: 18px;\n}\n",
    'css_button_icon_rule'
);
css_balance_gate($files[TYPOCSS]['text'], $css, TYPOCSS, 1);

/* ---- header.twig: cache tokens ------------------------------------------ */
$header = $files[HEADER]['text'];
$header = bust_token($header, 'catalog/view/javascript/booster-product-polish.js', TOKEN, 'polish_js');
$header = bust_token($header, 'catalog/view/stylesheet/booster-typography.css', TOKEN, 'typography_css');

twig_gate($root, array(HEADER => array($files[HEADER]['text'], $header)));
out('php_lint=not_applicable(no PHP targets)');

/* ---- backup, write, verify ---------------------------------------------- */
$updated = array(POLISHJS => encode_text($files[POLISHJS], $js), TYPOCSS => encode_text($files[TYPOCSS], $css), HEADER => encode_text($files[HEADER], $header));
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
out('next=refresh the OpenCart theme cache, Ctrl+F5, then run the WP-C0 checks in the TECH-045 report');
@unlink(__FILE__);
