<?php
declare(strict_types=1);

/**
 * CAT-002-5c1 — keep the mobile Contacts footer block in the right column.
 *
 * Files only; no DB writes.
 * Rollback: restore the two files from
 * _patch_backups/cat002_5c1_footer_contacts_columns_20260630-<timestamp>/.
 */

const PATCH_ID = 'cat002_5c1_footer_contacts_columns_20260630';
const CSS_MARKER = 'CAT-002-5c1 · mobile footer contacts columns';
const CACHE_VERSION = 'cat002-5c1-20260630';

$args = $argv ?? [];
$dryRun = in_array('--dry-run', $args, true);
$root = rtrim((string)(getenv('BS_PATCH_ROOT') ?: (getcwd() ?: __DIR__)), "/\\");
$backupDir = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
$files = [
    'header' => 'catalog/view/template/common/header.twig',
    'css' => 'catalog/view/stylesheet/boostershop-ds.css',
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
    $actual = substr_count($content, $needle);
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
    $temporary = $path . '.cat0025c1.tmp.' . getmypid();
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
    assert_count(
        $content['css'],
        'grid-template-columns: minmax(0, 0.78fr) minmax(0, 1.22fr) !important;',
        1,
        'mobile_footer_columns_final'
    );
    assert_count(
        $content['css'],
        "body.bs .bs-footer__inner > .bs-footer__col--contacts {\n    grid-column: auto !important;",
        1,
        'contacts_column_final'
    );
    assert_count($content['css'], 'font-size: 11.5px !important;', 1, 'contacts_font_final');
    assert_count($content['header'], 'boostershop-ds.css?v=' . CACHE_VERSION, 1, 'cache_final');
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

$oldCss = <<<'CSS'
@media (max-width: 480px) {
  body.bs .bs-footer__inner > .bs-footer__col--contacts {
    grid-column: 1 / -1 !important;
    min-width: 0;
  }
  body.bs .bs-footer__col--contacts .bs-footer__contact-link {
    display: grid !important;
    grid-template-columns: 16px minmax(0, 1fr);
    align-items: center;
    gap: 8px;
    white-space: nowrap;
  }
}
/* ==== /CAT-002-5c ==== */
CSS;

$newCss = <<<'CSS'
@media (max-width: 480px) {
  /* CAT-002-5c1 · mobile footer contacts columns */
  body.bs .bs-footer__inner,
  body.bs footer.bs-footer .bs-footer__inner {
    grid-template-columns: minmax(0, 0.78fr) minmax(0, 1.22fr) !important;
    column-gap: 12px !important;
  }
  body.bs .bs-footer__inner > .bs-footer__col--contacts {
    grid-column: auto !important;
    min-width: 0;
  }
  body.bs .bs-footer__col--contacts .bs-footer__contact-link {
    display: grid !important;
    grid-template-columns: 16px minmax(0, 1fr);
    align-items: center;
    gap: 6px;
    font-size: 11.5px !important;
    letter-spacing: -0.01em;
    white-space: nowrap;
  }
}
/* ==== /CAT-002-5c ==== */
CSS;

$updated = $original;
$updated['css'] = replace_once($updated['css'], $oldCss, $newCss, 'mobile_footer_block');
$updated['header'] = replace_once(
    $updated['header'],
    'boostershop-ds.css?v=cat002-5c-20260630',
    'boostershop-ds.css?v=' . CACHE_VERSION,
    'header_cache_bust'
);
assert_final($updated);
out('assert=transformed_state:ok');

if ($dryRun) {
    out('would_change=yes:' . $files['css']);
    out('would_change=yes:' . $files['header']);
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
