<?php
declare(strict_types=1);

/**
 * CAT-002-5d — compact the two primary category cards on mobile home.
 *
 * Root cause: boostershop-ds.css keeps .bs-catcard at 132px on mobile,
 * while .bs-catcard__more uses margin-top:auto. With the mobile description
 * hidden, this creates the large empty gap between title and link.
 *
 * Changes only catalog/view/stylesheet/boostershop-ds.css and its cache-buster
 * in catalog/view/template/common/header.twig. No DB changes.
 *
 * Rollback: restore files from
 * _patch_backups/CAT-002-5d_mobile_primary_category_cards_20260718-<timestamp>/.
 * Existing !important on the mobile logo panel is retained, not added: it is
 * required by the pre-existing RD-01-02-03D media-panel rule.
 */

const PATCH_ID = 'CAT-002-5d_mobile_primary_category_cards_20260718';
const CSS_MARKER = 'CAT-002-5d · compact mobile primary category cards';
const CACHE_VERSION = 'cat002-5d-20260718';

$args = $argv ?? [];
$dryRun = in_array('--dry-run', $args, true);
$root = rtrim((string)(getenv('BS_PATCH_ROOT') ?: (getcwd() ?: __DIR__)), "/\\");
$backupDir = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
$files = [
    'css' => 'catalog/view/stylesheet/boostershop-ds.css',
    'header' => 'catalog/view/template/common/header.twig',
];

function out(string $message): void {
    echo $message . PHP_EOL;
}

function fail(string $message): never {
    throw new RuntimeException($message);
}

function normalize(string $value): string {
    return str_replace(["\r\n", "\r"], "\n", $value);
}

function assert_count(string $content, string $needle, int $expected, string $label): void {
    $actual = substr_count($content, normalize($needle));
    if ($actual !== $expected) {
        fail("anchor_count_{$label}={$actual},expected={$expected}");
    }
}

function replace_once(string $content, string $needle, string $replacement, string $label): string {
    $needle = normalize($needle);
    $replacement = normalize($replacement);
    assert_count($content, $needle, 1, $label);

    return str_replace($needle, $replacement, $content);
}

function write_file(string $path, string $content): void {
    $temporary = $path . '.cat0025d.tmp.' . getmypid();
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

function backup_file(string $root, string $backupDir, string $relative): void {
    $source = $root . '/' . $relative;
    $target = $backupDir . '/' . $relative;
    if (!is_dir(dirname($target))
        && !mkdir(dirname($target), 0775, true)
        && !is_dir(dirname($target))) {
        fail('backup_dir_create_failed=' . dirname($target));
    }
    if (!copy($source, $target)) {
        fail('backup_copy_failed=' . $relative);
    }
    out('backup=' . str_replace($root . '/', '', $target));
}

function php_lint(string $path): void {
    $output = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    if ($code !== 0 || !str_contains(implode("\n", $output), 'No syntax errors detected')) {
        fail('php_lint_failed=' . implode(' | ', $output));
    }
    out('php_lint=ok');
}

function assert_final(array $content): void {
    assert_count($content['css'], CSS_MARKER, 1, 'css_marker_final');
    assert_count($content['css'], '.bs-catcard { height: 110px; }', 1, 'mobile_card_height_final');
    assert_count($content['css'], 'height: 110px !important;', 1, 'mobile_media_height_final');
    assert_count(
        $content['css'],
        '.bs-catcard__more { margin-top: 8px; padding-top: 0; font-size: 13.5px; font-weight: 700; color: var(--bs-blue); }',
        1,
        'mobile_link_spacing_final'
    );
    assert_count($content['header'], 'boostershop-ds.css?v=' . CACHE_VERSION, 1, 'header_cache_final');
}

set_exception_handler(static function (Throwable $error): void {
    out('error=' . $error->getMessage());
    out('done=failed');
    exit(1);
});

out('patch=' . PATCH_ID);
out('cwd=' . $root);
out('time=' . date('c'));
out('dry_run=' . ($dryRun ? 'yes' : 'no'));
out('db_changes=none');
php_lint(__FILE__);

$original = [];
foreach ($files as $key => $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        fail('target_not_found=' . $relative);
    }
    if (!$dryRun && !is_writable($path)) {
        fail('target_not_writable=' . $relative);
    }
    $value = file_get_contents($path);
    if (!is_string($value)) {
        fail('target_read_failed=' . $relative);
    }
    $original[$key] = normalize($value);
    out('file_preflight=ok:' . $relative);
}

$cssApplied = str_contains($original['css'], CSS_MARKER);
$headerApplied = str_contains($original['header'], 'boostershop-ds.css?v=' . CACHE_VERSION);
if ($cssApplied xor $headerApplied) {
    fail('partial_patch_state_detected');
}
if ($cssApplied && $headerApplied) {
    assert_final($original);
    out('already_applied=yes');
    out('run_url=https://boostershop.website/');
    out('done=ok');
    if (!$dryRun) {
        @unlink(__FILE__);
    }
    exit(0);
}

$primaryOld = <<<'CSS'
.bs-catcard__more { margin-top: auto; padding-top: 10px; font-size: 13.5px; font-weight: 700; color: var(--bs-blue); }

@media (max-width: 768px) {
  .bs-catcard { height: 132px; }
  .bs-catcard__media { flex-basis: 124px; width: 124px; height: 132px; }
  .bs-catcard__desc { -webkit-line-clamp: 2; }
}
CSS;

$primaryNew = <<<'CSS'
.bs-catcard__more { margin-top: auto; padding-top: 10px; font-size: 13.5px; font-weight: 700; color: var(--bs-blue); }

@media (max-width: 768px) {
  /* CAT-002-5d · compact mobile primary category cards */
  .bs-catcard { height: 110px; }
  .bs-catcard__body { padding-top: 12px; padding-bottom: 12px; justify-content: center; }
  .bs-catcard__more { margin-top: 8px; padding-top: 0; font-size: 13.5px; font-weight: 700; color: var(--bs-blue); }
  .bs-catcard__media { flex-basis: 124px; width: 124px; height: 110px; }
  .bs-catcard__desc { -webkit-line-clamp: 2; }
}
CSS;

$mediaOld = <<<'CSS'
@media (max-width: 767.98px) {
  .bs-catcard__media {
    flex: 0 0 140px !important;
    width: 140px !important;
    height: 132px !important;
    padding: 12px !important;
  }
}
CSS;

$mediaNew = <<<'CSS'
@media (max-width: 767.98px) {
  .bs-catcard__media {
    flex: 0 0 140px !important;
    width: 140px !important;
    height: 110px !important;
    padding: 12px !important;
  }
}
CSS;

$updated = $original;
$updated['css'] = replace_once($updated['css'], $primaryOld, $primaryNew, 'primary_mobile_card_rules');
$updated['css'] = replace_once($updated['css'], $mediaOld, $mediaNew, 'active_mobile_media_rule');
$updated['header'] = replace_once(
    $updated['header'],
    'boostershop-ds.css?v=rd13.1i-20260712',
    'boostershop-ds.css?v=' . CACHE_VERSION,
    'header_cache_bust'
);
assert_final($updated);
out('assert=transformed_state:ok');

if ($dryRun) {
    foreach ($files as $relative) {
        out('would_change=yes:' . $relative);
    }
    out('done=ok');
    exit(0);
}

$writeStarted = false;
try {
    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
        fail('backup_root_create_failed=' . $backupDir);
    }
    foreach ($files as $relative) {
        backup_file($root, $backupDir, $relative);
    }
    out('assert=backup_before_write:ok');

    $writeStarted = true;
    foreach ($files as $key => $relative) {
        write_file($root . '/' . $relative, $updated[$key]);
        out('changed=' . $relative);
    }

    $final = [];
    foreach ($files as $key => $relative) {
        $value = file_get_contents($root . '/' . $relative);
        if (!is_string($value)) {
            fail('post_write_read_failed=' . $relative);
        }
        $final[$key] = normalize($value);
    }
    assert_final($final);
    out('assert=final_state:ok');
    out('run_url=https://boostershop.website/');
    out('done=ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    if ($writeStarted) {
        foreach ($files as $relative) {
            $backup = $backupDir . '/' . $relative;
            if (is_file($backup)) {
                @copy($backup, $root . '/' . $relative);
            }
        }
    }
    out('rollback=' . ($writeStarted ? 'file_backups_restored' : 'no_write_started'));
    out('error=' . $error->getMessage());
    out('done=failed');
    exit(1);
}
