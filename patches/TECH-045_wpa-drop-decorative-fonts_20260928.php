<?php
declare(strict_types=1);

/**
 * TECH-045 — WP-A: drop JetBrains Mono and IBM Plex Sans Condensed
 * =============================================================================
 * Task      : TECH-045 Stage 2, round 1, work package A of A → B → C0 → C → D
 * Handoff   : handoffs/handoff_TECH-045_render-blocking-fonts-icons_20260925.md §4 WP-A
 * Decisions : owner 2026-09-25 (both fonts dropped, their text renders in Manrope);
 *             owner 2026-09-28 decision 1(a): payment requisites use the system
 *             monospace stack instead
 * Author    : Claude Code · 2026-09-28
 * Risk      : LOW — two <link> tags removed from the global head; three font-family
 *             values edited at their source rules. No logic, no DB.
 * DB changes: NONE
 * RUN FROM  : ~/public_html    ->    php TECH-045_wpa-drop-decorative-fonts_20260928.php
 *
 * ROOT CAUSE (live files, tech045-live-20260928.tar.gz)
 *   header.twig:62 and :63 load two cross-origin, render-blocking Google Fonts
 *   stylesheets on every route. Their only consumers:
 *     JetBrains Mono  boostershop-ds.css:2678  .bs-menu__label (10.5 px, inside the closed burger menu)
 *                     content-pages.css:466    .bs-kv--copy .bs-kv__value (copyable payment requisites)
 *     IBM Plex Cond.  boostershop-ds.css:3225  .bs-h1-badge__eyebrow (10 px, home badge)
 *                     boostershop-ds.css:3240  .bs-h1-badge__sub
 *   No other reference exists in the two live archives (header/footer/home/cart/cookie,
 *   product, category, information, checkout and SimpleCheckout templates, all CSS/JS).
 *
 * CHANGES
 *   header.twig          remove the JetBrains Mono and IBM Plex <link> lines
 *   boostershop-ds.css   .bs-menu__label, .bs-h1-badge__eyebrow, .bs-h1-badge__sub →
 *                        'Manrope', system-ui, sans-serif (the DS stack)
 *   content-pages.css    .bs-kv--copy .bs-kv__value →
 *                        ui-monospace, SFMono-Regular, Menlo, Consolas, monospace
 *   header.twig          cache-bust boostershop-ds.css and content-pages.css (convention 8)
 *
 * UI/CSS DISCIPLINE
 *   Source rules edited in place. No new selector, no !important, no override stacking.
 *   Override history: patches/patch-mobile-search-menu-redesign.css (the original
 *   .bs-menu__label rule, now merged into ds.css) and TECH-013 WP1, which kept the
 *   JetBrains link after measuring a 7.2 % narrower label without it — the owner has
 *   since accepted that change. Measured on live 2026-09-28 at 1440 px: the badge keeps
 *   its 98 px box; each 10 px line grows from 13 px to 14 px line height.
 *
 * SAFETY / ROLLBACK
 *   Anchors are counted before any write; a mismatch aborts with nothing written.
 *   Originals are copied to _patch_backups/<patch>-<ts>/ with their paths.
 *   Gates: header.twig must parse with the site's Twig library (a control parse of the
 *   unmodified file runs first); CSS brace and comment balance must be unchanged.
 *   Any write failure restores every file. Line endings are preserved.
 *   Idempotent: marker TECH-045-WPA in all three files → already_applied=yes.
 *   ROLLBACK: copy the three files back from the backup folder, refresh the OpenCart
 *   theme cache, Ctrl+F5.
 * =============================================================================
 */

const PATCH_ID = 'TECH-045_wpa-drop-decorative-fonts_20260928';
const MARKER   = 'TECH-045-WPA';
const HEADER   = 'catalog/view/template/common/header.twig';
const DSCSS    = 'catalog/view/stylesheet/boostershop-ds.css';
const PAGESCSS = 'catalog/view/stylesheet/content-pages.css';
const TOKEN    = 'tech045-wpa-20260928';

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

function css_balance_gate(string $before, string $after, string $label): void
{
    foreach (array('{', '}') as $brace) {
        if (substr_count($before, $brace) !== substr_count($after, $brace)) fail('css_brace_balance_changed=' . $label);
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

$files = array(HEADER => load_text($root, HEADER), DSCSS => load_text($root, DSCSS), PAGESCSS => load_text($root, PAGESCSS));
foreach ($files as $rel => $file) out('file_preflight=ok:' . $rel . ' eol=' . ($file['eol'] === "\r\n" ? 'crlf' : 'lf'));

$state = array();
foreach ($files as $rel => $file) $state[$rel] = strpos($file['text'], MARKER) !== false;
if (count(array_unique($state)) !== 1) fail('partial_marker_state — restore the three files from the backup of the earlier run before retrying');
if ($state[HEADER]) {
    count_exact($files[HEADER]['text'], 'family=JetBrains+Mono', 0, 'final_jetbrains_link');
    count_exact($files[HEADER]['text'], 'family=IBM+Plex', 0, 'final_ibmplex_link');
    out('already_applied=yes');
    out('done=ok');
    @unlink(__FILE__);
    exit(0);
}

/* ---- header.twig -------------------------------------------------------- */
$header = $files[HEADER]['text'];
$header = replace_one(
    $header,
    "<link href=\"https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&display=swap\" rel=\"stylesheet\">\n"
    . "<link href=\"https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Condensed:wght@400;500;600;700&display=swap\" rel=\"stylesheet\">\n",
    "{# " . MARKER . " (2026-09-28): JetBrains Mono and IBM Plex Sans Condensed links removed — owner decision\n"
    . "   2026-09-25. Their text now renders in Manrope / system monospace. Do not re-add decorative web fonts here. #}\n",
    'header_decorative_font_links'
);
$header = bust_token($header, 'catalog/view/stylesheet/boostershop-ds.css', TOKEN, 'ds_css');
$header = bust_token($header, 'catalog/view/stylesheet/content-pages.css', TOKEN, 'content_pages_css');
count_exact($header, 'fonts.googleapis.com/css2?family=', 1, 'remaining_google_font_links_manrope_only');

/* ---- boostershop-ds.css ------------------------------------------------- */
$ds = $files[DSCSS]['text'];
$ds = replace_one(
    $ds,
    ".bs-menu__label {\n  font-family: 'JetBrains Mono', ui-monospace, monospace;\n",
    "/* " . MARKER . ": was 'JetBrains Mono' (dropped 2026-09-25, owner decision). */\n"
    . ".bs-menu__label {\n  font-family: 'Manrope', system-ui, sans-serif;\n",
    'ds_menu_label'
);
count_exact($ds, "  font-family: 'IBM Plex Sans Condensed', 'Manrope', system-ui, sans-serif;\n", 2, 'ds_badge_family');
$ds = replace_one(
    $ds,
    ".bs-h1-badge__eyebrow {\n  font-family: 'IBM Plex Sans Condensed', 'Manrope', system-ui, sans-serif;\n",
    "/* " . MARKER . ": eyebrow and sub were 'IBM Plex Sans Condensed' (dropped 2026-09-25, owner decision). */\n"
    . ".bs-h1-badge__eyebrow {\n  font-family: 'Manrope', system-ui, sans-serif;\n",
    'ds_badge_eyebrow'
);
$ds = replace_one(
    $ds,
    ".bs-h1-badge__sub {\n  font-family: 'IBM Plex Sans Condensed', 'Manrope', system-ui, sans-serif;\n",
    ".bs-h1-badge__sub {\n  font-family: 'Manrope', system-ui, sans-serif;\n",
    'ds_badge_sub'
);
count_exact($ds, "font-family: 'JetBrains", 0, 'ds_jetbrains_left');
count_exact($ds, "font-family: 'IBM Plex", 0, 'ds_ibmplex_left');
css_balance_gate($files[DSCSS]['text'], $ds, DSCSS);

/* ---- content-pages.css -------------------------------------------------- */
$pages = $files[PAGESCSS]['text'];
$pages = replace_one(
    $pages,
    ".bs-kv--copy .bs-kv__value{display:flex;align-items:center;gap:8px;font-family:'JetBrains Mono',ui-monospace,monospace;",
    "/* " . MARKER . ": requisites were 'JetBrains Mono'; system monospace per owner decision 2026-09-28 (1a). */\n"
    . ".bs-kv--copy .bs-kv__value{display:flex;align-items:center;gap:8px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;",
    'pages_kv_copy'
);
count_exact($pages, "font-family:'JetBrains", 0, 'pages_jetbrains_left');
css_balance_gate($files[PAGESCSS]['text'], $pages, PAGESCSS);

/* ---- gates -------------------------------------------------------------- */
twig_gate($root, array(HEADER => array($files[HEADER]['text'], $header)));
out('php_lint=not_applicable(no PHP targets)');

/* ---- backup, write, verify ---------------------------------------------- */
$updated = array(HEADER => encode_text($files[HEADER], $header), DSCSS => encode_text($files[DSCSS], $ds), PAGESCSS => encode_text($files[PAGESCSS], $pages));
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
out('next=refresh the OpenCart theme cache, Ctrl+F5, then run the WP-A checks in the TECH-045 report');
@unlink(__FILE__);
