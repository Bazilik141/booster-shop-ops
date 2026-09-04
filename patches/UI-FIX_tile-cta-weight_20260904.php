<?php
declare(strict_types=1);

/**
 * UI-FIX — homepage category tile CTA: heavier and slightly larger.
 *
 * Target: the "Дивитись усе" label, `.bs-cattile__cta`, on the two homepage
 * category tiles. Owner request 2026-09-04: visually stronger, same position.
 *
 * CURRENT VALUES, READ FROM THE DEPLOYED STYLESHEET (not from patch history):
 *
 *   base                    font-size 11.5px   font-weight 700
 *   @media (min-width:900px) font-size 13.5px   (weight inherited: 700)
 *
 * CHANGE — weight and size only:
 *
 *   base                    11.5px -> 12.5px    700 -> 800
 *   @media (min-width:900px) 13.5px -> 15px     (inherits 800)
 *
 * Position is untouched at every breakpoint: base keeps `left:11px;top:10px`,
 * desktop keeps `left:20px;top:18px`. So are colour, letter-spacing, the scrim
 * and the tile geometry. Both replacements re-emit the position declarations
 * byte-for-byte, and the patch asserts afterwards that all four values survive.
 *
 * ON THE WEIGHT — the request said "600-700 range", but the deployed weight is
 * already 700, the top of that range. There is no bolder step inside it, so the
 * only real increase is 800. Manrope is loaded with 800 on this page
 * (`family=Manrope:wght@400;500;600;700;800`), confirmed in the browser with
 * `document.fonts.check('800 12px Manrope') === true`, so this is a real face,
 * not a synthesised faux-bold.
 *
 * ON THE SIZE — measured on the live page rather than estimated. The element is
 * plain absolutely-positioned text: no pill, no badge, no arrow, no background,
 * border, padding or border-radius (computed style on production, 2026-09-04).
 * The only overflow risk is the text running past the tile edge, and the
 * headroom is large at both extremes:
 *
 *   375px viewport, tile 169px wide:
 *     11.5px/700 (now)  text 78.8px, 78.8px clear to the tile edge, 1 line
 *     12.5px/800 (new)  text 86.9px, 70.6px clear to the tile edge, 1 line
 *
 *   1920px viewport, tile 636px wide:
 *     13.5px/700 (now)  text 92.5px, 523.5px clear, 1 line
 *     15px/800   (new)  text 104.3px, 511.7px clear, 1 line
 *
 * Nothing wraps or overflows anywhere between those widths, and the label stays
 * inside the scrim (36% of the tile on mobile, 34% on desktop).
 *
 * NOTE FOR THE OWNER, NOT FIXED HERE: `header.twig:58` links this stylesheet as
 * `boostershop-ds.css?v=tech013-wp2-20260806`. That token has not changed since
 * 2026-08-06, so browsers holding a cached copy do not pick up edits to this
 * file. Verified on production 2026-09-04: the cached copy under that URL is
 * 162243 bytes with no `aspect-ratio:16/9`, while a cache-busted fetch of the
 * same file is 162656 bytes and has it. Until that token is bumped this patch —
 * like the 16:9 one before it — will not reach returning visitors. See the
 * diagnostic; it is a one-line change and deliberately left out of this patch,
 * which was scoped to weight and size only.
 *
 * Files only; no DB writes.
 * Rollback: restore the file from
 * _patch_backups/UI-FIX_tile-cta-weight_20260904-<timestamp>/.
 *
 * Usage (from ~/public_html):
 *   php UI-FIX_tile-cta-weight_20260904.php --dry-run
 *   php UI-FIX_tile-cta-weight_20260904.php
 */

const PATCH_ID = 'UI-FIX_tile-cta-weight_20260904';
const MARKER = 'UI-FIX-20260904-CTA';
const CSS_FILE = 'catalog/view/stylesheet/boostershop-ds.css';

function out(string $message) {
    echo $message . PHP_EOL;
}

function fail(string $message) {
    throw new RuntimeException($message);
}

function normalize(string $text): string {
    return str_replace(["\r\n", "\r"], "\n", $text);
}

function assert_count(string $haystack, string $needle, int $expected, string $label) {
    $actual = substr_count($haystack, normalize($needle));
    if ($actual !== $expected) {
        fail("anchor_count_{$label}={$actual},expected={$expected}");
    }
}

function replace_once(string $content, string $needle, string $replacement, string $label): string {
    $needle = normalize($needle);
    assert_count($content, $needle, 1, $label);

    return str_replace($needle, normalize($replacement), $content);
}

function write_atomic(string $path, string $content, bool $crlf) {
    if ($crlf) {
        $content = str_replace("\n", "\r\n", $content);
    }
    $temporary = $path . '.uifixcta.tmp.' . getmypid();
    if (file_put_contents($temporary, $content, LOCK_EX) === false) {
        fail('temporary_write_failed=' . $path);
    }
    if (!@rename($temporary, $path)) {
        if (!copy($temporary, $path)) {
            @unlink($temporary);
            fail('file_replace_failed=' . $path);
        }
        @unlink($temporary);
    }
}

function self_lint() {
    $output = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        fail('php_lint_failed=' . implode(' | ', $output));
    }
    out('php_lint=ok');
}

$arguments = array_slice($argv ?? [], 1);
if ($arguments !== [] && $arguments !== ['--dry-run']) {
    fail('usage:php ' . basename(__FILE__) . ' [--dry-run]');
}
$dryRun = $arguments === ['--dry-run'];

self_lint();

$root = rtrim((string)(getenv('BS_PATCH_ROOT') ?: (getcwd() ?: __DIR__)), "/\\");
if (!is_file($root . '/config.php')) {
    fail('run_from_public_html_required');
}

$path = $root . '/' . CSS_FILE;
if (!is_file($path)) {
    fail('missing_file=' . CSS_FILE);
}
$raw = file_get_contents($path);
if ($raw === false) {
    fail('read_failed=' . CSS_FILE);
}
$crlf = strpos($raw, "\r\n") !== false;
$original = normalize($raw);

if (strpos($original, MARKER) !== false) {
    out('already_applied=yes');
    if ($dryRun) {
        out('dry_run=ok');
        exit(0);
    }
    out('done=ok');
    @unlink(__FILE__);
    exit(0);
}

$content = $original;

/* --------------------------------------------------------- base rule ---- */

$content = replace_once(
    $content,
    ".bs-cattile__cta{position:absolute;left:11px;top:10px;font-size:11.5px;font-weight:700;\n"
    . "  color:#fff;letter-spacing:.01em}",
    "/* " . MARKER . ": stronger label — weight 700 -> 800, size 11.5 -> 12.5px.\n"
    . "   The two position offsets below are re-emitted unchanged. 800 is the only\n"
    . "   step up that exists here: 700 was already the top of the requested\n"
    . "   600-700 range, and Manrope is loaded with a real 800 face on this page. */\n"
    . ".bs-cattile__cta{position:absolute;left:11px;top:10px;font-size:12.5px;font-weight:800;\n"
    . "  color:#fff;letter-spacing:.01em}",
    'cta_base'
);

/* ----------------------------------------------------- desktop rule ----- */

$content = replace_once(
    $content,
    "  .bs-cattile__cta{left:20px;top:18px;font-size:13.5px}",
    "  /* " . MARKER . ": size 13.5 -> 15px; weight inherits 800 from the base\n"
    . "     rule; the position offsets are re-emitted unchanged. */\n"
    . "  .bs-cattile__cta{left:20px;top:18px;font-size:15px}",
    'cta_desktop'
);

/* ------------------------------------------------------------ verify ---- */

if ($content === $original) {
    fail('no_change_produced=' . CSS_FILE);
}

// New values present exactly once each.
assert_count($content, 'font-size:12.5px;font-weight:800', 1, 'base_values');
assert_count($content, 'left:20px;top:18px;font-size:15px', 1, 'desktop_values');

// Old values gone.
assert_count($content, 'font-size:11.5px;font-weight:700', 0, 'old_base_gone');
assert_count($content, 'left:20px;top:18px;font-size:13.5px', 0, 'old_desktop_gone');

// Position untouched — the whole point of the request.
assert_count($content, 'left:11px;top:10px', substr_count($original, 'left:11px;top:10px'), 'base_position_unchanged');
assert_count($content, 'left:20px;top:18px', substr_count($original, 'left:20px;top:18px'), 'desktop_position_unchanged');

// Everything else about the component is untouched.
foreach ([
    'scrim_mobile' => '.bs-cattile__scrim{position:absolute;inset:0 0 auto 0;height:36%',
    'scrim_desktop' => '.bs-cattile__scrim{height:34%}',
    'wide_ratio' => 'aspect-ratio:16/9',
    'square_ratio' => 'aspect-ratio:1/1',
    'cta_colour' => 'color:#fff;letter-spacing:.01em}',
] as $label => $needle) {
    assert_count($content, $needle, substr_count($original, $needle), 'unchanged_' . $label);
}
assert_count($content, 'bs-cattile__cta', 2, 'still_two_cta_rules');
out('verified=position_and_geometry_unchanged');

if ($dryRun) {
    out('plan=' . CSS_FILE . ' ' . strlen($original) . ' -> ' . strlen($content) . ' bytes');
    out('plan=base 11.5px/700 -> 12.5px/800');
    out('plan=desktop 13.5px -> 15px (weight inherits 800)');
    out('dry_run=ok');
    exit(0);
}

/* ------------------------------------------------------------- write ---- */

$backupDir = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
if (file_exists($backupDir)) {
    fail('backup_path_exists');
}
if (!mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fail('backup_create_failed');
}

try {
    $target = $backupDir . '/' . CSS_FILE;
    $directory = dirname($target);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        fail('backup_dir_create_failed=' . $directory);
    }
    if (!copy($path, $target)) {
        fail('backup_copy_failed=' . CSS_FILE);
    }
    out('backup=' . CSS_FILE);

    write_atomic($path, $content, $crlf);
    out('written=' . CSS_FILE);

    $readback = file_get_contents($path);
    if ($readback === false || normalize($readback) !== $content) {
        fail('readback_mismatch=' . CSS_FILE);
    }
    out('readback=ok');
} catch (Throwable $error) {
    $backup = $backupDir . '/' . CSS_FILE;
    if (is_file($backup)) {
        @copy($backup, $path);
    }
    fwrite(STDERR, 'ERROR=' . $error->getMessage() . ' (file restored from ' . $backupDir . ')' . PHP_EOL);
    exit(1);
}

out('backup_dir=' . str_replace($root . '/', '', $backupDir));
out('reminder=bump the ?v= token in header.twig or cached browsers will not see this');
out('done=ok');
@unlink(__FILE__);
