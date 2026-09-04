<?php
declare(strict_types=1);

/**
 * UI-FIX — bump the cache-busting token on boostershop-ds.css.
 *
 * `catalog/view/template/common/header.twig:58` has linked the design-system
 * stylesheet as `?v=tech013-wp2-20260806` since 2026-08-06, while patches 2, 3,
 * the 16:9 tiles patch and the pending CTA patch have all edited that file.
 * Browsers holding a cached copy keep serving the August CSS.
 *
 * Measured on production 2026-09-04, from inside the page:
 *
 *   fetch('...boostershop-ds.css?v=tech013-wp2-20260806')
 *     -> 162243 bytes, no `aspect-ratio:16/9`
 *   fetch('...boostershop-ds.css?bust=<now>', {cache:'reload'})
 *     -> 162656 bytes, has `aspect-ratio:16/9`
 *
 * Visible consequence: the 16:9 tile markup is live and the wide derivatives are
 * being served, but `.bs-cattile` still computes `aspect-ratio: 1 / 1` for a
 * cached browser, so the wide artwork is cropped inside a square box.
 *
 * CHANGE — one token on one line:
 *
 *   ?v=tech013-wp2-20260806  ->  ?v=uifix-tiles-20260904
 *
 * Same `<build-id>-<date>` scheme already in use on the neighbouring links
 * (`rd05b-faq-20260604`, `pay001-info-20260726`,
 * `rd10-breadcrumb-mockup-20260611e`). The href path, `type` and `rel` are
 * re-emitted byte-for-byte; the whole line is the anchor, so nothing else on it
 * can drift.
 *
 * WHAT THIS DELIBERATELY DOES NOT TOUCH
 * -------------------------------------
 * The same token string also appears at header.twig:262, on the **logo image**:
 * `<img src="{{ logo }}?v=tech013-wp2-20260806" ...>`. That is a different asset
 * with its own cache concern — the logo has not changed, and bumping its token
 * would force every visitor to re-download it for nothing. This patch anchors on
 * the full stylesheet `<link>` line, so the logo cannot be caught by accident,
 * and asserts afterwards that the logo line still carries the old token.
 *
 * The TECH-013 WP2 comment at header.twig:258-261 was read as instructed. It
 * documents the **logo** re-export (270x84) and says the img attributes and the
 * aspect-ratio rule in boostershop-ds.css must stay in step with that file — it
 * is not about the stylesheet's cache token, and it hardcodes no token string
 * (it only says "The ?v= is required because images are served with
 * max-age=604800"). Nothing in it goes stale from this change, so it is left
 * untouched; the patch asserts it is byte-identical afterwards.
 *
 * Files only; no DB writes; one file, one line.
 * Rollback: restore the file from
 * _patch_backups/UI-FIX_ds-css-cache-bust_20260904-<timestamp>/.
 *
 * Usage (from ~/public_html):
 *   php UI-FIX_ds-css-cache-bust_20260904.php --dry-run
 *   php UI-FIX_ds-css-cache-bust_20260904.php
 */

const PATCH_ID = 'UI-FIX_ds-css-cache-bust_20260904';
const HEADER_FILE = 'catalog/view/template/common/header.twig';
const OLD_TOKEN = 'tech013-wp2-20260806';
const NEW_TOKEN = 'uifix-tiles-20260904';

const OLD_LINK = '  <link href="catalog/view/stylesheet/boostershop-ds.css?v=' . OLD_TOKEN . '" type="text/css" rel="stylesheet"/>';
const NEW_LINK = '  <link href="catalog/view/stylesheet/boostershop-ds.css?v=' . NEW_TOKEN . '" type="text/css" rel="stylesheet"/>';
const LOGO_LINE = '<img src="{{ logo }}?v=' . OLD_TOKEN . '"';
const COMMENT_ANCHOR = 'the aspect-ratio in boostershop-ds.css must stay in step with the file.';

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

function write_atomic(string $path, string $content, bool $crlf) {
    if ($crlf) {
        $content = str_replace("\n", "\r\n", $content);
    }
    $temporary = $path . '.uifixbust.tmp.' . getmypid();
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

$path = $root . '/' . HEADER_FILE;
if (!is_file($path)) {
    fail('missing_file=' . HEADER_FILE);
}
$raw = file_get_contents($path);
if ($raw === false) {
    fail('read_failed=' . HEADER_FILE);
}
$crlf = strpos($raw, "\r\n") !== false;
$original = normalize($raw);

// The new token is its own idempotency marker.
if (strpos($original, NEW_TOKEN) !== false) {
    out('already_applied=yes');
    if ($dryRun) {
        out('dry_run=ok');
        exit(0);
    }
    out('done=ok');
    @unlink(__FILE__);
    exit(0);
}

/* ------------------------------------------------------------ pre-check --- */

assert_count($original, OLD_LINK, 1, 'stylesheet_link_line');
assert_count($original, LOGO_LINE, 1, 'logo_line_present');
assert_count($original, OLD_TOKEN, 2, 'old_token_twice');
assert_count($original, COMMENT_ANCHOR, 1, 'tech013_comment_present');

$content = str_replace(OLD_LINK, NEW_LINK, $original);

/* --------------------------------------------------------------- verify --- */

if ($content === $original) {
    fail('no_change_produced=' . HEADER_FILE);
}

assert_count($content, NEW_LINK, 1, 'new_link_line');
assert_count($content, OLD_LINK, 0, 'old_link_line_gone');
assert_count($content, NEW_TOKEN, 1, 'new_token_once');

// The logo keeps its own token, and the TECH-013 comment is untouched.
assert_count($content, LOGO_LINE, 1, 'logo_line_unchanged');
assert_count($content, OLD_TOKEN, 1, 'old_token_only_on_logo');
assert_count($content, COMMENT_ANCHOR, 1, 'tech013_comment_unchanged');

// Exactly one line differs, and only in the token.
$before = explode("\n", $original);
$after = explode("\n", $content);
if (count($before) !== count($after)) {
    fail('line_count_changed:' . count($before) . '->' . count($after));
}
$changed = [];
foreach ($before as $index => $line) {
    if ($line !== $after[$index]) {
        $changed[] = $index + 1;
    }
}
if (count($changed) !== 1) {
    fail('unexpected_changed_line_count=' . count($changed) . ':' . implode(',', $changed));
}
$lineNumber = $changed[0];
if (str_replace(NEW_TOKEN, OLD_TOKEN, $after[$lineNumber - 1]) !== $before[$lineNumber - 1]) {
    fail('changed_line_differs_beyond_token:' . $lineNumber);
}
out('verified=single_line_' . $lineNumber . '_token_only');
out('verified=logo_and_tech013_comment_untouched');

if ($dryRun) {
    out('plan=' . HEADER_FILE . ' line ' . $lineNumber . ': ?v=' . OLD_TOKEN . ' -> ?v=' . NEW_TOKEN);
    out('plan=' . strlen($original) . ' -> ' . strlen($content) . ' bytes');
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

try {
    $target = $backupDir . '/' . HEADER_FILE;
    $directory = dirname($target);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        fail('backup_dir_create_failed=' . $directory);
    }
    if (!copy($path, $target)) {
        fail('backup_copy_failed=' . HEADER_FILE);
    }
    out('backup=' . HEADER_FILE);

    write_atomic($path, $content, $crlf);
    out('written=' . HEADER_FILE);

    $readback = file_get_contents($path);
    if ($readback === false || normalize($readback) !== $content) {
        fail('readback_mismatch=' . HEADER_FILE);
    }
    out('readback=ok');
} catch (Throwable $error) {
    $backup = $backupDir . '/' . HEADER_FILE;
    if (is_file($backup)) {
        @copy($backup, $path);
    }
    fwrite(STDERR, 'ERROR=' . $error->getMessage() . ' (file restored from ' . $backupDir . ')' . PHP_EOL);
    exit(1);
}

out('backup_dir=' . str_replace($root . '/', '', $backupDir));
out('done=ok');
@unlink(__FILE__);
