<?php
declare(strict_types=1);

/**
 * TECH-045 — WP-C: FontAwesome stylesheet off the critical path (non-checkout routes)
 * =============================================================================
 * Task      : TECH-045 Stage 2, round 1, work package C of A → B → C0 → C → D
 * Handoff   : handoffs/handoff_TECH-045_render-blocking-fonts-icons_20260925.md §4 WP-C
 * Author    : Claude Code · 2026-09-28
 * Risk      : MEDIUM — global head template; renders on every route incl. checkout
 * DB changes: NONE
 * REQUIRES  : WP-C0 deployed first (the runner checks booster-product-polish.js and aborts otherwise)
 * RUN FROM  : ~/public_html    ->    php TECH-045_wpc-fontawesome-nonblocking_20260928.php
 *
 * ROOT CAUSE
 *   header.twig:54 `<link href="{{ icons }}" rel="stylesheet">` loads FontAwesome all.min.css
 *   (26 KiB, render-blocking, PSI 1,780 ms) on every route. First-viewport usage measured
 *   live 2026-09-28, scroll 0:
 *     /                    390×844 and 1440×900: no visible icon (#back-to-top fa-chevron-up is
 *                          visibility:hidden; opacity:0 until the visitor scrolls)
 *     /catalog/Pokemon     390×844: fa-home and fa-filter present but zero width; nothing visible
 *     product benchmark    1440×900: fa-cart-shopping in #button-cart at y 453–469 — removed by
 *                          WP-C0 (inline SVG); nothing else
 *   So no first-viewport icon on the three benchmark URLs depends on the stylesheet once WP-C0
 *   is live.
 *
 * CHANGE (header.twig, one tag)
 *   Routes where bs_wp1_defer is ' defer' (TECH-013 WP1 flag, header.twig:50-51 — everything
 *   except checkout/* and extension/SimpleCheckout*):
 *       <link rel="stylesheet" href="{{ icons }}" media="print" onload="this.media='all'">
 *       <noscript><link rel="stylesheet" href="{{ icons }}"></noscript>
 *   Checkout routes: today's blocking <link>, byte-identical.
 *   Why print-media rather than rel=preload: the print swap fetches at low priority, so the
 *   icon stylesheet no longer competes with boostershop-ds.css and the LCP image for the slow-4G
 *   pipe; nothing on these routes needs it for first paint. No CSP header is sent (checked
 *   2026-09-28), so the inline onload handler runs.
 *   Not changed: catalog/controller/common/header.php:45 and the FontAwesome files.
 *
 * VISIBLE EFFECT OUTSIDE THE BENCHMARK URLS
 *   On non-checkout pages that show FA icons in the first viewport (account pages, stock
 *   OpenCart templates) the icons can appear a moment after the text on a cold load.
 *   font-display is already swap (TECH-013 WP4), so this adds no text delay.
 *
 * SAFETY / ROLLBACK
 *   Anchor counted before any write; header.twig backed up to _patch_backups/<patch>-<ts>/;
 *   Twig parse gate (control parse of the unmodified file first); failure restores the file.
 *   Idempotent: marker TECH-045-WPC in header.twig → already_applied=yes.
 *   ROLLBACK: copy header.twig back from the backup folder, refresh the theme cache, Ctrl+F5.
 * =============================================================================
 */

const PATCH_ID = 'TECH-045_wpc-fontawesome-nonblocking_20260928';
const MARKER   = 'TECH-045-WPC';
const HEADER   = 'catalog/view/template/common/header.twig';
const POLISHJS = 'catalog/view/javascript/booster-product-polish.js';

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

$file = load_text($root, HEADER);
out('file_preflight=ok:' . HEADER . ' eol=' . ($file['eol'] === "\r\n" ? 'crlf' : 'lf'));
$header = $file['text'];

if (strpos($header, MARKER . ' ') !== false) {
    count_exact($header, 'media="print" onload="this.media=\'all\'"', 1, 'final_nonblocking_link');
    out('already_applied=yes');
    out('done=ok');
    @unlink(__FILE__);
    exit(0);
}

$polish = load_text($root, POLISHJS);
if (strpos($polish['text'], 'fa-cart-shopping') !== false || strpos($polish['text'], 'TECH-045-WPC0') === false) {
    fail('wp_c0_not_applied — run TECH-045_wpc0-cart-icon-svg_20260928.php first (the buy-button icon must not depend on FontAwesome)');
}
out('precondition=wp_c0_applied');

/* The flag must be defined before the tag it now controls. */
$flagAt = strpos($header, "{% set bs_wp1_defer = ");
$iconsAt = strpos($header, "  <link href=\"{{ icons }}\" rel=\"stylesheet\" type=\"text/css\"/>\n");
if ($flagAt === false || $iconsAt === false || $flagAt > $iconsAt) fail('defer_flag_not_defined_before_icons_link');

$header = replace_one(
    $header,
    "  <link href=\"{{ icons }}\" rel=\"stylesheet\" type=\"text/css\"/>\n",
    "  {# " . MARKER . " (2026-09-28): FontAwesome is non-blocking where bs_wp1_defer is set (all routes except\n"
    . "     checkout/* and SimpleCheckout). print-media swap = low-priority fetch; nothing in the first viewport of\n"
    . "     home, category or product needs it (product buy-button icon is inline SVG since TECH-045-WPC0).\n"
    . "     Checkout keeps the blocking tag unchanged. #}\n"
    . "  {% if bs_wp1_defer %}\n"
    . "  <link href=\"{{ icons }}\" rel=\"stylesheet\" type=\"text/css\" media=\"print\" onload=\"this.media='all'\"/>\n"
    . "  <noscript><link href=\"{{ icons }}\" rel=\"stylesheet\" type=\"text/css\"/></noscript>\n"
    . "  {% else %}\n"
    . "  <link href=\"{{ icons }}\" rel=\"stylesheet\" type=\"text/css\"/>\n"
    . "  {% endif %}\n",
    'header_icons_link'
);
count_exact($header, '{{ icons }}', 3, 'icons_references');

twig_gate($root, array(HEADER => array($file['text'], $header)));
out('php_lint=not_applicable(no PHP targets)');

$backupDir = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
$dest = $backupDir . '/' . HEADER;
if (!is_dir(dirname($dest)) && !mkdir(dirname($dest), 0755, true) && !is_dir(dirname($dest))) fail('backup_dir_failed=' . dirname($dest));
if (file_put_contents($dest, $file['raw'], LOCK_EX) !== strlen($file['raw'])) fail('backup_write_failed=' . HEADER);
out('backup=' . substr($dest, strlen($root) + 1));
try {
    write_checked($root . '/' . HEADER, encode_text($file, $header));
    out('changed=' . HEADER);
} catch (Throwable $error) {
    $restored = file_put_contents($root . '/' . HEADER, $file['raw'], LOCK_EX) === strlen($file['raw']);
    out('restore=' . ($restored ? 'ok' : 'FAILED — copy header.twig back from ' . $backupDir));
    throw $error;
}

out('already_applied=no');
out('done=ok');
out('next=refresh the OpenCart theme cache, Ctrl+F5, then run the WP-C checks in the TECH-045 report');
@unlink(__FILE__);
