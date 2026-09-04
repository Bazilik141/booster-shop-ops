<?php
declare(strict_types=1);

/**
 * UI-FIX — homepage tile CTA becomes a real pill with an arrow.
 *
 * Target: `.bs-cattile__cta` ("Дивитись усе") on the two homepage category
 * tiles. Owner request 2026-09-04: a white rounded background behind the label
 * plus a trailing arrow, in the position the label already occupies.
 *
 * CURRENT VALUES, RE-READ FROM THE DEPLOYED STYLESHEET 2026-09-04
 * (`?v=uifix-tiles-20260904`, so the earlier weight patch and the cache-bust
 * are both live — confirmed, not assumed):
 *
 *   base                     left:11px top:10px  12.5px / 800  color:#fff
 *   @media (min-width:900px) left:20px top:18px  15px
 *   no background, no padding, no border-radius, no arrow
 *
 * CHANGE
 *
 *   base      + background var(--bs-paper,#fff)
 *             + padding 5px 10px
 *             + border-radius var(--bs-r-pill,999px)
 *             color  #fff -> var(--bs-ink,#111827)
 *   >=900px   + padding 6px 12px
 *   new       .bs-cattile__cta::after { content:"→"; margin-left:5px }
 *
 * `left`/`top` are re-emitted byte-for-byte at both breakpoints, as in the last
 * two rounds, and the patch asserts their counts do not move. Font size, weight,
 * letter-spacing, the scrim and the tile geometry are untouched.
 *
 * THE COLOUR HAD TO CHANGE — the request listed background, radius and padding
 * only, but the label is currently `color:#fff`. White text on a white pill is
 * invisible, so the colour moves to the DS ink. Flagging rather than doing it
 * silently: if a dark pill with white text was wanted instead, that is the same
 * two values swapped.
 *
 * ARROW AS ::after, NOT MARKUP — the alternative was editing the two
 * `<span class="bs-cattile__cta">` in home.twig. The pseudo-element keeps this
 * to a single file and leaves the `<picture>` markup alone, which is the
 * smaller and safer diff. The link's accessible name is unaffected either way:
 * it comes from the `aria-label` on the `<a>` ("… — дивитись усе"), not from
 * this text.
 *
 * WHERE THE TEXT ENDS UP — worth knowing before review. Because `left`/`top`
 * stay fixed and padding is added, the pill's top-left corner lands exactly
 * where the bare text's top-left was, so the glyphs move inward by the padding:
 * 10px right / 5px down on mobile, 12px right / 6px down on desktop. The pill
 * itself has not moved. If the intent was to pin the *text* rather than the
 * box, subtract the padding from `left`/`top` — say so and it is two values.
 *
 * WIDTH BUDGET — measured on production, not estimated. The tile is 169px wide
 * at a 375px viewport and 636px at 1920px:
 *
 *   375px   before  86.9px wide, 70.6px clear of the tile edge
 *           after  122.8px wide, 34.7px clear      (pad 5px 10px + arrow)
 *   1920px  before 104.3px wide, 511.7px clear
 *           after  147.4px wide, 468.6px clear     (pad 6px 12px + arrow)
 *
 * Nothing wraps or reaches the edge at either end. Larger paddings were measured
 * too and also fit (6px 12px on mobile leaves 29.7px), so this is a comfortable
 * pick rather than the maximum. The pill also stays inside the scrim at both
 * breakpoints: 10+31 = 41px against a 61px scrim on mobile, 18+38 = 56px
 * against 122px on desktop.
 *
 * NOTE — this edits boostershop-ds.css again, so the `?v=` token bumped earlier
 * today (`uifix-tiles-20260904`) is now stale for anyone who has already cached
 * the file since that bump. Deliberately not touched here: this patch is scoped
 * to one element, and the token lives in header.twig. See the diagnostic.
 *
 * Files only; no DB writes; one file.
 * Rollback: restore the file from
 * _patch_backups/UI-FIX_tile-cta-pill_20260904-<timestamp>/.
 *
 * Usage (from ~/public_html):
 *   php UI-FIX_tile-cta-pill_20260904.php --dry-run
 *   php UI-FIX_tile-cta-pill_20260904.php
 */

const PATCH_ID = 'UI-FIX_tile-cta-pill_20260904';
const MARKER = 'UI-FIX-20260904-PILL';
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
    $temporary = $path . '.uifixpill.tmp.' . getmypid();
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

// The previous round must be in place; this patch builds on its values.
assert_count($original, 'font-size:12.5px;font-weight:800', 1, 'weight_patch_present');
assert_count($original, 'left:20px;top:18px;font-size:15px', 1, 'desktop_size_present');

$content = $original;

/* --------------------------------------------------------- base rule ---- */

$content = replace_once(
    $content,
    ".bs-cattile__cta{position:absolute;left:11px;top:10px;font-size:12.5px;font-weight:800;\n"
    . "  color:#fff;letter-spacing:.01em}",
    "/* " . MARKER . ": the label becomes a real pill — white ground, dark ink,\n"
    . "   pill radius, and a trailing arrow via ::after. The colour had to flip\n"
    . "   from white or the text would be invisible on the new ground. The two\n"
    . "   position offsets are re-emitted unchanged; the padding grows the pill\n"
    . "   inward from that same corner. */\n"
    . ".bs-cattile__cta{position:absolute;left:11px;top:10px;font-size:12.5px;font-weight:800;\n"
    . "  color:var(--bs-ink,#111827);letter-spacing:.01em;\n"
    . "  background:var(--bs-paper,#fff);padding:5px 10px;border-radius:var(--bs-r-pill,999px)}\n"
    . ".bs-cattile__cta::after{content:\"\\2192\";margin-left:5px}",
    'cta_base'
);

/* ------------------------------------------------------ desktop rule ---- */

$content = replace_once(
    $content,
    "  .bs-cattile__cta{left:20px;top:18px;font-size:15px}",
    "  /* " . MARKER . ": slightly roomier pill at the larger type size; the\n"
    . "     position offsets are re-emitted unchanged. */\n"
    . "  .bs-cattile__cta{left:20px;top:18px;font-size:15px;padding:6px 12px}",
    'cta_desktop'
);

/* ------------------------------------------------------------ verify ---- */

if ($content === $original) {
    fail('no_change_produced=' . CSS_FILE);
}

assert_count($content, 'background:var(--bs-paper,#fff);padding:5px 10px;border-radius:var(--bs-r-pill,999px)', 1, 'pill_base');
assert_count($content, 'left:20px;top:18px;font-size:15px;padding:6px 12px', 1, 'pill_desktop');
assert_count($content, '.bs-cattile__cta::after{content:"\\2192";margin-left:5px}', 1, 'arrow_rule');
assert_count($content, 'color:var(--bs-ink,#111827)', 1, 'ink_colour');
assert_count($content, 'color:#fff;letter-spacing:.01em}', 0, 'white_text_gone');

// Position and type untouched — the point of the request, three rounds running.
assert_count($content, 'left:11px;top:10px', substr_count($original, 'left:11px;top:10px'), 'base_position_unchanged');
assert_count($content, 'left:20px;top:18px', substr_count($original, 'left:20px;top:18px'), 'desktop_position_unchanged');
assert_count($content, 'font-size:12.5px;font-weight:800', 1, 'base_type_unchanged');
assert_count($content, 'font-size:15px', 1, 'desktop_type_unchanged');

// Everything else about the component is untouched.
foreach ([
    'scrim_mobile' => '.bs-cattile__scrim{position:absolute;inset:0 0 auto 0;height:36%',
    'scrim_desktop' => '.bs-cattile__scrim{height:34%}',
    'wide_ratio' => 'aspect-ratio:16/9',
    'square_ratio' => 'aspect-ratio:1/1',
    'tiles_grid' => '.bs-cattiles{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:4px 0 12px}',
] as $label => $needle) {
    assert_count($content, $needle, substr_count($original, $needle), 'unchanged_' . $label);
}
out('verified=position_type_and_geometry_unchanged');

if ($dryRun) {
    out('plan=' . CSS_FILE . ' ' . strlen($original) . ' -> ' . strlen($content) . ' bytes');
    out('plan=base    + bg/padding 5px 10px/radius, colour #fff -> ink');
    out('plan=desktop + padding 6px 12px');
    out('plan=new     ::after arrow');
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
out('reminder=hard-reload (Ctrl+F5) before judging — the ?v= token is unchanged by this patch');
out('done=ok');
@unlink(__FILE__);
