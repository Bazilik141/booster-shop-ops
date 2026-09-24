<?php
declare(strict_types=1);

/**
 * UI-CAT-GAP — restore the desktop gap between the category toolbar and product grid.
 *
 * Root cause: catalog/view/template/product/category.twig sets
 * `.bs-cat-header { margin-bottom: 0; }`. On mobile, the following
 * `.bs-action-row` supplies the visible gap; it is hidden from 992px up, leaving
 * the desktop toolbar directly against the first product row.
 *
 * Scope: one Twig inline-CSS rule only. No database, checkout, payment, price,
 * controller, JavaScript, or shared stylesheet changes.
 *
 * Rollback: restore catalog/view/template/product/category.twig from
 * _patch_backups/UI-CAT-GAP_category-toolbar-desktop-spacing_20260922-<timestamp>/.
 *
 * After success: clear OpenCart theme cache, then hard-refresh a category page.
 */

const PATCH_ID = 'UI-CAT-GAP_category-toolbar-desktop-spacing_20260922';
const TARGET = 'catalog/view/template/product/category.twig';
const MARKER = 'UI-CAT-GAP · desktop toolbar-to-grid spacing';

function out(string $message): void {
    echo $message . PHP_EOL;
}

function fail(string $message): void {
    throw new RuntimeException($message);
}

function normalize(string $value): string {
    return str_replace(array("\r\n", "\r"), "\n", $value);
}

function assert_count(string $content, string $needle, int $expected, string $label): void {
    $actual = substr_count($content, normalize($needle));
    if ($actual !== $expected) {
        fail('anchor_count_' . $label . '=' . $actual . ',expected=' . $expected);
    }
}

function write_file(string $path, string $content): void {
    $temporary = $path . '.ui-cat-gap.tmp.' . getmypid();
    if (file_put_contents($temporary, $content, LOCK_EX) === false) {
        fail('temporary_write_failed=' . TARGET);
    }
    if (!@rename($temporary, $path)) {
        if (!@copy($temporary, $path)) {
            @unlink($temporary);
            fail('file_replace_failed=' . TARGET);
        }
        @unlink($temporary);
    }
}

function assert_final(string $content): void {
    assert_count($content, MARKER, 1, 'marker_final');
    assert_count($content, "@media (min-width: 992px) {\n    #product-category .bs-cat-header { margin-bottom: 16px; }\n  }", 1, 'desktop_rule_final');
}

set_exception_handler(static function (Throwable $error): void {
    out('error=' . $error->getMessage());
    out('done=failed');
    exit(1);
});

$root = rtrim((string) (getenv('BS_PATCH_ROOT') ?: (getcwd() ?: __DIR__)), "/\\");
$target = $root . '/' . TARGET;
$backupDir = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');

out('patch=' . PATCH_ID);
out('cwd=' . $root);
out('time=' . date('c'));
out('db_changes=none');

if (!is_file($target)) {
    fail('target_not_found=' . TARGET);
}
if (!is_writable($target)) {
    fail('target_not_writable=' . TARGET);
}

$original = file_get_contents($target);
if (!is_string($original)) {
    fail('target_read_failed=' . TARGET);
}
$original = normalize($original);
out('file_preflight=ok:' . TARGET);

if (strpos($original, MARKER) !== false) {
    assert_final($original);
    out('already_applied=yes');
    out('cache_clear=required:theme');
    out('done=ok');
    @unlink(__FILE__);
    exit(0);
}

$old = '  .bs-cat-header { margin-bottom: 0; }';
$new = <<<'CSS'
  .bs-cat-header { margin-bottom: 0; }

  /* UI-CAT-GAP · desktop toolbar-to-grid spacing */
  @media (min-width: 992px) {
    #product-category .bs-cat-header { margin-bottom: 16px; }
  }
CSS;

assert_count($original, $old, 1, 'category_header_base_margin');
$updated = str_replace($old, $new, $original);
assert_final($updated);
out('assert=transformed_state:ok');

$writeStarted = false;
try {
    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
        fail('backup_root_create_failed=' . $backupDir);
    }

    $backup = $backupDir . '/' . TARGET;
    if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0775, true) && !is_dir(dirname($backup))) {
        fail('backup_dir_create_failed=' . dirname($backup));
    }
    if (!copy($target, $backup)) {
        fail('backup_copy_failed=' . TARGET);
    }
    out('backup=' . str_replace($root . '/', '', $backup));

    $writeStarted = true;
    write_file($target, $updated);
    out('changed=' . TARGET);

    $final = file_get_contents($target);
    if (!is_string($final)) {
        fail('post_write_read_failed=' . TARGET);
    }
    assert_final(normalize($final));
    out('assert=final_state:ok');
    out('cache_clear=required:theme');
    out('done=ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    if ($writeStarted && isset($backup) && is_file($backup)) {
        @copy($backup, $target);
        out('rollback=file_backup_restored');
    } else {
        out('rollback=no_write_started');
    }
    out('error=' . $error->getMessage());
    out('done=failed');
    exit(1);
}
