<?php
declare(strict_types=1);

/**
 * UI-FIX post-deploy — homepage category tiles: 16:9 on desktop.
 *
 * Handoff: handoffs/handoff_UI-FIX_postdeploy-tile-size-desktop_20260904.md
 * Follows UI-FIX_home-category-tiles_20260903.php (patch 3 of UX-036), which is
 * already deployed and self-deleted. This is a fix on top, not a resend.
 *
 * WHAT THE HANDOFF ASKED VS WHAT THIS DOES
 * ----------------------------------------
 * The handoff offered (a) cap `.bs-cattiles` with a max-width at a wide
 * breakpoint, or (b) add columns above ~1200px, and said the tiles "grow
 * unbounded with the viewport". Measured on production 2026-09-04:
 *
 *     viewport 1920px → .container 1320px → tile 636x636
 *     viewport 2560px → .container 1320px → tile 636x636   (identical)
 *
 * They do not grow unbounded — Bootstrap's `.container` already caps the row at
 * 1320px, and every other block on the homepage (trust strip, hero, subtiles,
 * product grid, SEO panel) is the same 1296px wide. So (a) would either be a
 * no-op or make the tiles the one block narrower than everything around them,
 * and (b) makes no sense with two categories. The real problem is proportion,
 * not width: a 636px-tall square is simply too tall.
 *
 * The owner's decision of 2026-09-04 fixes exactly that — desktop switches from
 * 1:1 to 16:9, with new artwork the owner has already uploaded to
 * image/catalog/tiles/. At the same 636px column that is 636x358, which sits in
 * proportion with the hero above it. No max-width is added; adding one on top
 * of the aspect change would be a second, redundant constraint.
 *
 * Mobile and tablet are untouched: two columns, 1:1, the existing square
 * artwork, exactly as deployed. The switch uses the same 900px breakpoint patch
 * 3 already established for this component, so the component keeps one
 * breakpoint rather than gaining a second. Portrait tablets (768px) therefore
 * stay square; landscape (1024px) gets 16:9.
 *
 * SCOPE NOTE — this patch does touch the tile markup, which the handoff's
 * guardrails excluded. That guardrail was written before the owner's 16:9
 * decision and assumed a CSS-only cap. Serving different artwork above 900px
 * cannot be done from CSS alone with a <picture>; it needs a media-scoped
 * <source>. Nothing else in the markup changes: hrefs, order, alt, aria-label,
 * loading/fetchpriority and the existing square sources are all byte-unchanged,
 * and the square <img> fallback stays as the final fallback.
 *
 * SOURCE ARTWORK
 * --------------
 * The owner uploaded the 16:9 files directly to production under names this
 * patch cannot know in advance (the directory has no autoindex and the four
 * square files from patch 3 are untouched). So the patch lists
 * image/catalog/tiles/ on the server, prints everything it finds with
 * dimensions, and picks the one non-square file per category whose name
 * contains the category token and whose aspect ratio is between 1.60 and 1.90.
 * If that is not exactly one file per category it stops and prints the listing
 * — run with --dry-run first and read that listing before doing anything else.
 *
 * From whatever it finds it generates deterministic derivatives, so the markup
 * never depends on the owner's original filenames:
 *
 *     category-tile-pokemon-wide-1600.webp   1600x900
 *     category-tile-pokemon-wide-800.webp     800x450
 *     category-tile-onepiece-wide-1600.webp  1600x900
 *     category-tile-onepiece-wide-800.webp    800x450
 *
 * Sources are centre-cropped to exactly 16:9 before scaling, so a source that is
 * a few pixels off (1686x948 is 1.7785, not 1.7778) is never stretched. The
 * originals are read only, never modified or deleted. Budget is the same rule
 * patch 3 used: q80, dropping to q72 for any file over 160 KB.
 *
 * Files and images only; no DB writes.
 * Rollback: restore both files from
 * _patch_backups/UI-FIX_home-tiles-wide-desktop_20260904-<timestamp>/ and delete
 * image/catalog/tiles/category-tile-*-wide-*.webp. The square derivatives and
 * the owner's uploaded originals are not touched by a rollback.
 *
 * Usage (from ~/public_html):
 *   php UI-FIX_home-tiles-wide-desktop_20260904.php --dry-run
 *   php UI-FIX_home-tiles-wide-desktop_20260904.php
 */

const PATCH_ID = 'UI-FIX_home-tiles-wide-desktop_20260904';
const MARKER = 'UI-FIX-20260904-WIDE';
const TILE_DIR = 'image/catalog/tiles';
const MAX_BYTES = 163840; // 160 KB, inherited from patch 3 — owner's number to change, not this patch's
/** Tried in order; each lower step only if the previous is over MAX_BYTES. */
const QUALITY_LADDER = [80, 72, 62];
// 1280 is the 2x-DPR tier for this tile: it is never wider than 636 CSS px
// (1320px Bootstrap container, two columns, 24px gap), so 2x needs 1272. The
// first version of this patch used 1600, which was over-provisioned by 25%
// linear / 56% area for no visible gain and pushed the One Piece derivative
// over the size ceiling. Owner-approved 2026-09-04.
const WIDE_W = 1280;
const WIDE_H = 720;
// The 1x tier stays 800x450: already exactly 16:9, and it still covers the
// 636px tile at DPR 1 with headroom. Scaling it down with the wide tier would
// leave none.
const NARROW_W = 800;
const NARROW_H = 450;

/** slug => filename tokens that identify that category's artwork */
const CATEGORIES = [
    'pokemon' => ['pokemon', 'pokémon', 'pkm'],
    'onepiece' => ['onepiece', 'one-piece', 'one piece', 'op-', 'op_'],
];

/** Square derivatives from patch 3 — never candidates for the 16:9 source. */
const RESERVED = [
    'category-tile-pokemon-1080.webp',
    'category-tile-pokemon-540.webp',
    'category-tile-onepiece-1080.webp',
    'category-tile-onepiece-540.webp',
];

const FILES = [
    'home_twig' => 'catalog/view/template/common/home.twig',
    'ds_css' => 'catalog/view/stylesheet/boostershop-ds.css',
];

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
    $temporary = $path . '.uifixwide.tmp.' . getmypid();
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

function require_gd() {
    foreach (['imagecreatetruecolor', 'imagecopyresampled', 'imagewebp'] as $function) {
        if (!function_exists($function)) {
            fail('gd_missing=' . $function . ' — ask the owner for pre-converted .webp files instead');
        }
    }
    $info = function_exists('gd_info') ? gd_info() : [];
    if (($info['WebP Support'] ?? false) !== true) {
        fail('gd_webp_unsupported — ask the owner for pre-converted .webp files instead');
    }
    out('gd=ok');
}

/**
 * @return array<int, array{name:string,width:int,height:int,ratio:float,type:int,bytes:int}>
 */
function list_tiles(string $directory): array {
    $entries = @scandir($directory);
    if ($entries === false) {
        fail('tile_dir_unreadable=' . $directory);
    }
    $found = [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $directory . '/' . $entry;
        if (!is_file($path)) {
            continue;
        }
        $size = @getimagesize($path);
        if ($size === false || (int)$size[1] === 0) {
            continue;
        }
        $found[] = [
            'name' => $entry,
            'width' => (int)$size[0],
            'height' => (int)$size[1],
            'ratio' => (int)$size[0] / (int)$size[1],
            'type' => (int)$size[2],
            'bytes' => (int)filesize($path),
        ];
    }

    return $found;
}

/**
 * @param array<int, array{name:string,width:int,height:int,ratio:float,type:int,bytes:int}> $files
 * @param array<int, string> $tokens
 * @return array{name:string,width:int,height:int,ratio:float,type:int,bytes:int}
 */
function pick_wide_source(array $files, string $slug, array $tokens): array {
    $matches = [];
    foreach ($files as $file) {
        $lower = mb_strtolower($file['name'], 'UTF-8');
        if (in_array($file['name'], RESERVED, true) || strpos($lower, '-wide-') !== false) {
            continue;
        }
        if ($file['ratio'] < 1.60 || $file['ratio'] > 1.90) {
            continue;
        }
        foreach ($tokens as $token) {
            if (strpos($lower, $token) !== false) {
                $matches[] = $file;
                break;
            }
        }
    }
    if (count($matches) !== 1) {
        fail('wide_source_ambiguous:' . $slug . ':matches=' . count($matches)
            . ' — expected exactly one 16:9 file whose name contains one of ['
            . implode(', ', $tokens) . ']. See the listing above and tell the executor the exact filename.');
    }

    return $matches[0];
}

function load_image(string $path, int $type) {
    if ($type === IMAGETYPE_PNG && function_exists('imagecreatefrompng')) {
        return @imagecreatefrompng($path);
    }
    if ($type === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) {
        return @imagecreatefromjpeg($path);
    }
    if ($type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
        return @imagecreatefromwebp($path);
    }
    fail('unsupported_source_type=' . $path . ':type=' . $type);
}

/**
 * Centre-crop to exactly 16:9, then scale. A source a few pixels off 16:9 is
 * cropped, never stretched.
 *
 * Quality ladder: q80 first, then each lower step only if the previous one is
 * over budget. Every attempt is reported, and if even the last step is over the
 * patch fails with the whole ladder printed — the ceiling is the owner's call,
 * never silently raised here.
 *
 * @return array{0:int,1:int,2:array<int,string>} bytes, quality, ladder log
 */
function render_wide($source, int $sourceWidth, int $sourceHeight, int $width, int $height, string $target): array {
    $targetRatio = WIDE_W / WIDE_H;
    $cropWidth = $sourceWidth;
    $cropHeight = (int)round($sourceWidth / $targetRatio);
    if ($cropHeight > $sourceHeight) {
        $cropHeight = $sourceHeight;
        $cropWidth = (int)round($sourceHeight * $targetRatio);
    }
    $cropX = (int)floor(($sourceWidth - $cropWidth) / 2);
    $cropY = (int)floor(($sourceHeight - $cropHeight) / 2);

    $canvas = imagecreatetruecolor($width, $height);
    if (!imagecopyresampled($canvas, $source, 0, 0, $cropX, $cropY, $width, $height, $cropWidth, $cropHeight)) {
        imagedestroy($canvas);
        fail('resample_failed=' . basename($target));
    }
    $ladder = [];
    $bytes = 0;
    $quality = 0;
    foreach (QUALITY_LADDER as $step) {
        $quality = $step;
        if (!imagewebp($canvas, $target, $quality)) {
            imagedestroy($canvas);
            fail('webp_write_failed=' . basename($target) . ':q' . $quality);
        }
        clearstatcache(true, $target);
        $bytes = (int)filesize($target);
        $ladder[] = sprintf('q%d=%dB', $quality, $bytes);
        if ($bytes > 0 && $bytes <= MAX_BYTES) {
            break;
        }
    }
    imagedestroy($canvas);
    if ($bytes <= 0) {
        fail('webp_empty=' . basename($target));
    }

    return [$bytes, $quality, $ladder];
}

function desktop_source_block(string $slug): string {
    return "            {# " . MARKER . ": desktop 16:9 artwork. Matches the same 900px\n"
        . "               breakpoint the CSS switches proportion on; below that the\n"
        . "               square sources below are used, unchanged. #}\n"
        . "            <source media=\"(min-width:900px)\" type=\"image/webp\"\n"
        . "                    srcset=\"/" . TILE_DIR . "/category-tile-" . $slug . "-wide-" . NARROW_W . ".webp " . NARROW_W . "w,\n"
        . "                            /" . TILE_DIR . "/category-tile-" . $slug . "-wide-" . WIDE_W . ".webp " . WIDE_W . "w\"\n"
        . "                    sizes=\"(min-width:900px) 636px, 50vw\">\n";
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

$original = [];
$crlf = [];
foreach (FILES as $key => $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        fail('missing_file=' . $relative);
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        fail('read_failed=' . $relative);
    }
    $crlf[$key] = strpos($raw, "\r\n") !== false;
    $original[$key] = normalize($raw);
}

$tileDirectory = $root . '/' . TILE_DIR;
if (!is_dir($tileDirectory)) {
    fail('missing_tile_dir=' . TILE_DIR . ' — patch 3 should have created it');
}

$expectedDerivatives = [];
foreach (array_keys(CATEGORIES) as $slug) {
    $expectedDerivatives[] = 'category-tile-' . $slug . '-wide-' . WIDE_W . '.webp';
    $expectedDerivatives[] = 'category-tile-' . $slug . '-wide-' . NARROW_W . '.webp';
}
$derivativesPresent = true;
foreach ($expectedDerivatives as $file) {
    if (!is_file($tileDirectory . '/' . $file)) {
        $derivativesPresent = false;
        break;
    }
}

$alreadyApplied = strpos($original['home_twig'], MARKER) !== false
    && strpos($original['ds_css'], MARKER) !== false
    && $derivativesPresent;

if ($alreadyApplied && !$dryRun) {
    out('already_applied=yes');
    out('done=ok');
    @unlink(__FILE__);
    exit(0);
}
if ($alreadyApplied) {
    out('already_applied=yes');
    out('dry_run=ok');
    exit(0);
}

/* ------------------------------------------------- find the 16:9 sources --- */

// Printed unconditionally: this listing is the only view anyone here has of
// what the owner actually uploaded.
$listing = list_tiles($tileDirectory);
out('tile_dir_listing=' . count($listing) . ' image file(s) in ' . TILE_DIR);
foreach ($listing as $file) {
    out(sprintf(
        '  %-52s %5dx%-5d ratio=%.3f %7dB%s',
        $file['name'],
        $file['width'],
        $file['height'],
        $file['ratio'],
        $file['bytes'],
        in_array($file['name'], RESERVED, true) ? '  (square, from patch 3)' : ''
    ));
}

$sources = [];
foreach (CATEGORIES as $slug => $tokens) {
    $picked = pick_wide_source($listing, $slug, $tokens);
    $sources[$slug] = $picked;
    out(sprintf('wide_source[%s]=%s %dx%d ratio=%.3f', $slug, $picked['name'], $picked['width'], $picked['height'], $picked['ratio']));
}

/* ------------------------------------------------------------- markup ---- */

$content = $original;

foreach (CATEGORIES as $slug => $tokens) {
    $squareSource = "            <source type=\"image/webp\"\n"
        . "                    srcset=\"/" . TILE_DIR . "/category-tile-" . $slug . "-540.webp 540w,\n"
        . "                            /" . TILE_DIR . "/category-tile-" . $slug . "-1080.webp 1080w\"\n"
        . "                    sizes=\"(min-width:900px) 530px, 50vw\">\n";

    $content['home_twig'] = replace_once(
        $content['home_twig'],
        $squareSource,
        desktop_source_block($slug) . $squareSource,
        'twig_source_' . $slug
    );
}

/* ---------------------------------------------------------------- css ---- */

$content['ds_css'] = replace_once(
    $content['ds_css'],
    "@media (min-width:900px){\n"
    . "  .bs-cattiles{gap:24px;margin:8px 0 16px}\n"
    . "  .bs-cattile__scrim{height:34%}\n"
    . "  .bs-cattile__cta{left:20px;top:18px;font-size:13.5px}\n"
    . "}",
    "@media (min-width:900px){\n"
    . "  /* " . MARKER . ": desktop is 16:9, not 1:1. At the 1320px Bootstrap\n"
    . "     container each tile is 636x358 instead of 636x636 — the square was\n"
    . "     simply too tall, and the row was never growing unbounded (measured\n"
    . "     2026-09-04: identical 1320px container at 1920px and 2560px), so no\n"
    . "     extra width cap is added on top. Mobile and tablet keep 1:1 below\n"
    . "     900px. */\n"
    . "  .bs-cattile{aspect-ratio:16/9}\n"
    . "  .bs-cattiles{gap:24px;margin:8px 0 16px}\n"
    . "  .bs-cattile__scrim{height:34%}\n"
    . "  .bs-cattile__cta{left:20px;top:18px;font-size:13.5px}\n"
    . "}",
    'css_desktop_block'
);

/* ------------------------------------------------------------- verify ---- */

foreach (FILES as $key => $relative) {
    if ($content[$key] === $original[$key]) {
        fail('no_change_produced=' . $relative);
    }
}

assert_count($content['home_twig'], 'media="(min-width:900px)"', 2, 'twig_two_desktop_sources');
assert_count($content['home_twig'], 'aspect-ratio', 0, 'twig_has_no_inline_aspect');
assert_count($content['ds_css'], 'aspect-ratio:16/9', 1, 'css_wide_ratio_once');
assert_count($content['ds_css'], 'aspect-ratio:1/1', 1, 'css_square_ratio_kept');
assert_count($content['ds_css'], 'max-width', substr_count($original['ds_css'], 'max-width'), 'css_no_new_max_width');

// Everything patch 3 established stays exactly as deployed.
assert_count($content['home_twig'], 'href="/catalog/Pokemon"', 1, 'twig_pokemon_href');
assert_count($content['home_twig'], 'href="/catalog/One-Piece"', 1, 'twig_onepiece_href');
assert_count($content['home_twig'], 'category-tile-pokemon-1080.webp', 2, 'twig_pokemon_square_kept');
assert_count($content['home_twig'], 'category-tile-onepiece-1080.webp', 2, 'twig_onepiece_square_kept');
assert_count($content['home_twig'], 'loading="eager" fetchpriority="high"', 1, 'twig_lcp_hint_kept');
assert_count($content['home_twig'], 'bs-subtile', 13, 'twig_subtiles_untouched');
assert_count($content['home_twig'], '<h1 id="bs-home-title">', 1, 'twig_h1_untouched');

// The desktop <source> must precede the square one inside each <picture>, or
// the browser would never reach it.
foreach (array_keys(CATEGORIES) as $slug) {
    $wideAt = strpos($content['home_twig'], 'category-tile-' . $slug . '-wide-' . NARROW_W . '.webp');
    $squareAt = strpos($content['home_twig'], 'category-tile-' . $slug . '-540.webp');
    if ($wideAt === false || $squareAt === false || $wideAt > $squareAt) {
        fail('source_order_wrong:' . $slug);
    }
}
out('verified=desktop_source_precedes_square');

if ($dryRun) {
    foreach (FILES as $key => $relative) {
        out(sprintf('plan=%s %d -> %d bytes', $relative, strlen($original[$key]), strlen($content[$key])));
    }
    foreach ($expectedDerivatives as $file) {
        out('plan=' . TILE_DIR . '/' . $file . (is_file($tileDirectory . '/' . $file) ? ' overwrite' : ' new'));
    }
    out('dry_run=ok');
    exit(0);
}

require_gd();

/* -------------------------------------------------------------- write ---- */

$backupDir = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
if (file_exists($backupDir)) {
    fail('backup_path_exists');
}
if (!mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fail('backup_create_failed');
}

$writtenDerivatives = [];

try {
    foreach (FILES as $relative) {
        $target = $backupDir . '/' . $relative;
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            fail('backup_dir_create_failed=' . $directory);
        }
        if (!copy($root . '/' . $relative, $target)) {
            fail('backup_copy_failed=' . $relative);
        }
        out('backup=' . $relative);
    }

    foreach (CATEGORIES as $slug => $tokens) {
        $source = $sources[$slug];
        $image = load_image($tileDirectory . '/' . $source['name'], $source['type']);
        if ($image === false) {
            fail('source_decode_failed=' . $source['name']);
        }
        foreach ([[WIDE_W, WIDE_H], [NARROW_W, NARROW_H]] as $pair) {
            $file = sprintf('category-tile-%s-wide-%d.webp', $slug, $pair[0]);
            $path = $tileDirectory . '/' . $file;
            list($bytes, $quality, $ladder) = render_wide($image, $source['width'], $source['height'], $pair[0], $pair[1], $path);
            @chmod($path, 0644);
            $writtenDerivatives[] = $path;
            out(sprintf('tile=%s %dx%d q%d %dB  [%s]', $file, $pair[0], $pair[1], $quality, $bytes, implode(' ', $ladder)));
            if ($bytes > MAX_BYTES) {
                fail(
                    'tile_over_budget=' . $file . PHP_EOL
                    . '  source: ' . $source['name'] . ' ' . $source['width'] . 'x' . $source['height'] . PHP_EOL
                    . '  target: ' . $pair[0] . 'x' . $pair[1] . PHP_EOL
                    . '  ladder: ' . implode('  ', $ladder) . PHP_EOL
                    . '  ceiling: ' . MAX_BYTES . 'B (' . round(MAX_BYTES / 1024) . ' KB)' . PHP_EOL
                    . '  over by: ' . ($bytes - MAX_BYTES) . 'B at the lowest step (q' . $quality . ')' . PHP_EOL
                    . '  This patch will not raise the ceiling or drop quality further on its own —'
                    . ' send these numbers to the owner and let them decide (smaller 1600 variant,'
                    . ' or a higher ceiling for the wide tiles).'
                );
            }
        }
        imagedestroy($image);
    }

    foreach (FILES as $key => $relative) {
        write_atomic($root . '/' . $relative, $content[$key], $crlf[$key]);
        out('written=' . $relative);
    }

    foreach (FILES as $key => $relative) {
        $readback = file_get_contents($root . '/' . $relative);
        if ($readback === false || normalize($readback) !== $content[$key]) {
            fail('readback_mismatch=' . $relative);
        }
    }
    out('readback=ok');
} catch (Throwable $error) {
    foreach (FILES as $relative) {
        $backup = $backupDir . '/' . $relative;
        if (is_file($backup)) {
            @copy($backup, $root . '/' . $relative);
        }
    }
    foreach ($writtenDerivatives as $path) {
        @unlink($path);
    }
    fwrite(STDERR, 'ERROR=' . $error->getMessage() . ' (rolled back from ' . $backupDir . ')' . PHP_EOL);
    exit(1);
}

out('backup_dir=' . str_replace($root . '/', '', $backupDir));
out('done=ok');
@unlink(__FILE__);
