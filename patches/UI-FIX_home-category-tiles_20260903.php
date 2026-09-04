<?php
declare(strict_types=1);

/**
 * UI-FIX Task 8 — homepage category tiles (Claude Design Component B).
 *
 * The two `.bs-catcards` entry tiles (Pokémon TCG / One Piece Card Game) become
 * full-bleed 1:1 square illustrations with a single white "Дивитись усе" CTA in
 * the top-left corner. The category name lives in the artwork and survives for
 * machines via alt + aria-label.
 *
 * `.bs-subtiles` ("Інші TCG", "Аксесуари") is NOT touched — owner decision.
 *
 * Images
 * ------
 * Sources are the owner's own uploads, already on production:
 *   image/catalog/Other/One Piece Card Game logo tiles catygory.png
 *   image/catalog/Other/Pokemon trading Card Game logo tiles catygory 2.png
 * (the "catygory" spelling is the real on-disk name — do not "fix" it.)
 * Both were confirmed on 2026-09-03 to exist and to be 1254x1254, i.e. already
 * square, so the derivatives below are a pure downscale — no crop, no recolor,
 * no baked-in overlay. The scrim is CSS.
 *
 * Derivatives written to image/catalog/tiles/ (new folder):
 *   category-tile-pokemon-1080.webp   1080x1080
 *   category-tile-pokemon-540.webp     540x540
 *   category-tile-onepiece-1080.webp  1080x1080
 *   category-tile-onepiece-540.webp    540x540
 *
 * Quality: q80, dropping to q72 for any file over 160 KB rather than shrinking
 * dimensions. Generated locally against the real production sources on
 * 2026-09-03, so the budget is measured, not assumed:
 *   onepiece 1080 q80 = 150198 B  -> kept at q80
 *   pokemon  1080 q80 = 183138 B  -> falls back to q72 = 142864 B
 *   onepiece  540 q80 =  52310 B
 *   pokemon   540 q80 =  66110 B
 *
 * The patch encodes server-side with GD. Production runs PHP 8.0.30 and already
 * writes .webp derivatives into image/cache/ through OpenCart's own resizer, so
 * GD with WebP encode is present; the patch still checks and fails loudly
 * rather than writing a broken file.
 *
 * Rollback: restore catalog/view/template/common/home.twig and
 * catalog/view/stylesheet/boostershop-ds.css from
 * _patch_backups/UI-FIX_home-category-tiles_20260903-<timestamp>/ and delete
 * the image/catalog/tiles/ folder. The source PNGs are never modified.
 *
 * Usage (from ~/public_html):
 *   php UI-FIX_home-category-tiles_20260903.php --dry-run
 *   php UI-FIX_home-category-tiles_20260903.php
 */

const PATCH_ID = 'UI-FIX_home-category-tiles_20260903';
const MARKER = 'UI-FIX-20260903-TILES';
const TILE_DIR = 'image/catalog/tiles';
const MAX_BYTES = 163840; // 160 KB

const SOURCES = [
    'pokemon' => 'image/catalog/Other/Pokemon trading Card Game logo tiles catygory 2.png',
    'onepiece' => 'image/catalog/Other/One Piece Card Game logo tiles catygory.png',
];

const FILES = [
    'home_twig' => 'catalog/view/template/common/home.twig',
    'ds_css' => 'catalog/view/stylesheet/boostershop-ds.css',
];

function out(string $message): void {
    echo $message . PHP_EOL;
}

function fail(string $message) {
    throw new RuntimeException($message);
}

function normalize(string $text): string {
    return str_replace(["\r\n", "\r"], "\n", $text);
}

function assert_count(string $haystack, string $needle, int $expected, string $label): void {
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

function write_atomic(string $path, string $content, bool $crlf): void {
    if ($crlf) {
        $content = str_replace("\n", "\r\n", $content);
    }
    $temporary = $path . '.uifixtiles.tmp.' . getmypid();
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

function self_lint(): void {
    $output = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        fail('php_lint_failed=' . implode(' | ', $output));
    }
    out('php_lint=ok');
}

function require_gd(): void {
    foreach (['imagecreatefrompng', 'imagecreatetruecolor', 'imagecopyresampled', 'imagewebp'] as $function) {
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
 * @return array{0:int,1:int} written bytes and quality used
 */
function render_tile(GdImage $source, int $sourceWidth, int $sourceHeight, int $size, string $target): array {
    $canvas = imagecreatetruecolor($size, $size);
    if (!imagecopyresampled($canvas, $source, 0, 0, 0, 0, $size, $size, $sourceWidth, $sourceHeight)) {
        imagedestroy($canvas);
        fail('resample_failed=' . basename($target));
    }
    $quality = 80;
    if (!imagewebp($canvas, $target, $quality)) {
        imagedestroy($canvas);
        fail('webp_write_failed=' . basename($target));
    }
    clearstatcache(true, $target);
    $bytes = (int)filesize($target);
    if ($bytes > MAX_BYTES) {
        $quality = 72;
        if (!imagewebp($canvas, $target, $quality)) {
            imagedestroy($canvas);
            fail('webp_rewrite_failed=' . basename($target));
        }
        clearstatcache(true, $target);
        $bytes = (int)filesize($target);
    }
    imagedestroy($canvas);
    if ($bytes <= 0) {
        fail('webp_empty=' . basename($target));
    }

    return [$bytes, $quality];
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

foreach (SOURCES as $slug => $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        fail('missing_source=' . $relative);
    }
    $size = @getimagesize($path);
    if ($size === false || $size[2] !== IMAGETYPE_PNG) {
        fail('source_not_png=' . $relative);
    }
    if ($size[0] !== $size[1]) {
        fail('source_not_square=' . $relative . ':' . $size[0] . 'x' . $size[1]
            . ' — the spec forbids cropping; ask the owner for a square master');
    }
    out(sprintf('source=%s %dx%d ok', $slug, $size[0], $size[1]));
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
    $crlf[$key] = str_contains($raw, "\r\n");
    $original[$key] = normalize($raw);
}

$tileDirectory = $root . '/' . TILE_DIR;
$expectedTiles = [
    'category-tile-pokemon-1080.webp',
    'category-tile-pokemon-540.webp',
    'category-tile-onepiece-1080.webp',
    'category-tile-onepiece-540.webp',
];
$tilesPresent = true;
foreach ($expectedTiles as $file) {
    if (!is_file($tileDirectory . '/' . $file)) {
        $tilesPresent = false;
        break;
    }
}

$alreadyApplied = str_contains($original['home_twig'], MARKER)
    && str_contains($original['ds_css'], MARKER)
    && $tilesPresent;

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

require_gd();

/* --------------------------------------------------------------- markup --- */

$content = $original;

$content['home_twig'] = replace_once(
    $content['home_twig'],
    <<<'TWIG'
      <section class="bs-home-tiles bs-catcards" aria-label="Категорії товарів">
        <a class="bs-catcard" href="/catalog/Pokemon" style="--accent:#C68A00;">
          <span class="bs-catcard__media">
            <img src="{{ base }}image/catalog/Pokemon/PokemonC.png?v=tech013-wp2-20260806" alt="Pokémon TCG" loading="eager" width="168" height="168">
          </span>
          <span class="bs-catcard__body">
            <span class="bs-catcard__title">Pokémon TCG</span>
            <span class="bs-catcard__desc">Оригінальні бустери, бокси та набори Pokémon TCG. Японські, корейські й англійські видання — sealed, без зважування.</span>
            <span class="bs-catcard__more">Переглянути →</span>
          </span>
        </a>
        <a class="bs-catcard" href="/catalog/One-Piece" style="--accent:#1E40AF;">
          <span class="bs-catcard__media">
            <img src="{{ base }}image/catalog/One%20Piece/One%20Piece-Photoroom.png" alt="One Piece Card Game" loading="eager" width="168" height="168">
          </span>
          <span class="bs-catcard__body">
            <span class="bs-catcard__title">One Piece Card Game</span>
            <span class="bs-catcard__desc">Оригінальні бустери та бокси One Piece Card Game від Bandai. Sealed із боксів, без сортування.</span>
            <span class="bs-catcard__more">Переглянути →</span>
          </span>
        </a>
      </section>
TWIG,
    <<<'TWIG'
      {# UI-FIX-20260903-TILES · Component B. Square artwork tiles; the category
         name is in the illustration and reaches machines through alt and
         aria-label. hrefs and order are unchanged from the previous markup.
         The tile description copy that used to sit under each title moves to
         the homepage FAQ (UI-FIX_cms-content_20260903.php), so it stays
         indexable instead of being deleted. #}
      <section class="bs-cattiles" aria-label="Категорії товарів">
        <a class="bs-cattile" href="/catalog/Pokemon" aria-label="Pokémon TCG — дивитись усе">
          <picture>
            <source type="image/webp"
                    srcset="/image/catalog/tiles/category-tile-pokemon-540.webp 540w,
                            /image/catalog/tiles/category-tile-pokemon-1080.webp 1080w"
                    sizes="(min-width:900px) 530px, 50vw">
            <img src="/image/catalog/tiles/category-tile-pokemon-1080.webp"
                 alt="Pokémon Trading Card Game" width="1080" height="1080"
                 loading="eager" fetchpriority="high" decoding="async">
          </picture>
          <span class="bs-cattile__scrim" aria-hidden="true"></span>
          <span class="bs-cattile__cta">Дивитись усе</span>
        </a>
        <a class="bs-cattile" href="/catalog/One-Piece" aria-label="One Piece Card Game — дивитись усе">
          <picture>
            <source type="image/webp"
                    srcset="/image/catalog/tiles/category-tile-onepiece-540.webp 540w,
                            /image/catalog/tiles/category-tile-onepiece-1080.webp 1080w"
                    sizes="(min-width:900px) 530px, 50vw">
            <img src="/image/catalog/tiles/category-tile-onepiece-1080.webp"
                 alt="One Piece Card Game" width="1080" height="1080"
                 loading="lazy" decoding="async">
          </picture>
          <span class="bs-cattile__scrim" aria-hidden="true"></span>
          <span class="bs-cattile__cta">Дивитись усе</span>
        </a>
      </section>
TWIG,
    'tiles_markup'
);

$content['ds_css'] = rtrim($content['ds_css'], "\n") . "\n\n"
    . '/* ' . MARKER . " · Component B — homepage category tiles.\n"
    . "   Two columns at every breakpoint: the owner's explicit pick over a\n"
    . "   full-width square on mobile. The old .bs-catcard* rules above no longer\n"
    . "   match any markup on the homepage; they are left in place rather than\n"
    . "   widening this diff into the shared .bs-subtiles rules next to them. */\n"
    . ".bs-cattiles{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:4px 0 12px}\n"
    . ".bs-cattile{position:relative;display:block;aspect-ratio:1/1;overflow:hidden;\n"
    . "  border-radius:var(--bs-r-lg);text-decoration:none;background:var(--bs-ink)}\n"
    . ".bs-cattile img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;\n"
    . "  transition:transform .5s cubic-bezier(.2,.7,.2,1)}\n"
    . ".bs-cattile__scrim{position:absolute;inset:0 0 auto 0;height:36%;pointer-events:none;\n"
    . "  background:linear-gradient(to bottom,rgba(10,12,16,.66),rgba(10,12,16,0))}\n"
    . ".bs-cattile__cta{position:absolute;left:11px;top:10px;font-size:11.5px;font-weight:700;\n"
    . "  color:#fff;letter-spacing:.01em}\n"
    . ".bs-cattile:hover img{transform:scale(1.04)}\n"
    . ".bs-cattile:focus-visible{outline:2px solid var(--bs-ink);outline-offset:3px}\n"
    . "@media (prefers-reduced-motion:reduce){\n"
    . "  .bs-cattile img{transition:none}\n"
    . "  .bs-cattile:hover img{transform:none}\n"
    . "}\n"
    . "@media (min-width:900px){\n"
    . "  .bs-cattiles{gap:24px;margin:8px 0 16px}\n"
    . "  .bs-cattile__scrim{height:34%}\n"
    . "  .bs-cattile__cta{left:20px;top:18px;font-size:13.5px}\n"
    . "}\n"
    . '/* /' . MARKER . " */\n";

foreach (FILES as $key => $relative) {
    if ($content[$key] === $original[$key]) {
        fail('no_change_produced=' . $relative);
    }
}

assert_count($content['home_twig'], 'bs-catcard', 0, 'no_old_tile_classes');
assert_count($content['home_twig'], 'bs-subtile', 13, 'subtiles_untouched');
assert_count($content['home_twig'], '/catalog/Pokemon', 1, 'pokemon_href');
assert_count($content['home_twig'], '/catalog/One-Piece', 1, 'onepiece_href');
assert_count($content['home_twig'], '<h1 id="bs-home-title">', 1, 'h1_untouched');
assert_count($content['home_twig'], 'aria-label="Pokémon TCG — дивитись усе"', 1, 'aria_pokemon');
assert_count($content['home_twig'], 'aria-label="One Piece Card Game — дивитись усе"', 1, 'aria_onepiece');

if ($dryRun) {
    out('plan=' . FILES['home_twig'] . ' ' . strlen($original['home_twig']) . ' -> ' . strlen($content['home_twig']) . ' bytes');
    out('plan=' . FILES['ds_css'] . ' ' . strlen($original['ds_css']) . ' -> ' . strlen($content['ds_css']) . ' bytes');
    foreach ($expectedTiles as $file) {
        out('plan=' . TILE_DIR . '/' . $file . (is_file($tileDirectory . '/' . $file) ? ' overwrite' : ' new'));
    }
    out('dry_run=ok');
    exit(0);
}

/* ---------------------------------------------------------------- write --- */

$backupDir = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
if (file_exists($backupDir)) {
    fail('backup_path_exists');
}
if (!mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fail('backup_create_failed');
}

$writtenTiles = [];

try {
    foreach (FILES as $relative) {
        $target = $backupDir . '/' . $relative;
        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true) && !is_dir(dirname($target))) {
            fail('backup_dir_create_failed=' . dirname($target));
        }
        if (!copy($root . '/' . $relative, $target)) {
            fail('backup_copy_failed=' . $relative);
        }
        out('backup=' . $relative);
    }

    if (!is_dir($tileDirectory) && !mkdir($tileDirectory, 0755, true) && !is_dir($tileDirectory)) {
        fail('tile_dir_create_failed');
    }

    foreach (SOURCES as $slug => $relative) {
        $image = @imagecreatefrompng($root . '/' . $relative);
        if (!$image instanceof GdImage) {
            fail('source_decode_failed=' . $relative);
        }
        $width = imagesx($image);
        $height = imagesy($image);
        foreach ([1080, 540] as $size) {
            $file = sprintf('category-tile-%s-%d.webp', $slug, $size);
            $path = $tileDirectory . '/' . $file;
            [$bytes, $quality] = render_tile($image, $width, $height, $size, $path);
            @chmod($path, 0644);
            $writtenTiles[] = $path;
            out(sprintf('tile=%s %dx%d q%d %dB', $file, $size, $size, $quality, $bytes));
            if ($bytes > MAX_BYTES) {
                fail('tile_over_budget=' . $file . ':' . $bytes);
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
    foreach ($writtenTiles as $path) {
        @unlink($path);
    }
    fwrite(STDERR, 'ERROR=' . $error->getMessage() . ' (rolled back from ' . $backupDir . ')' . PHP_EOL);
    exit(1);
}

out('backup_dir=' . str_replace($root . '/', '', $backupDir));
out('done=ok');
@unlink(__FILE__);
