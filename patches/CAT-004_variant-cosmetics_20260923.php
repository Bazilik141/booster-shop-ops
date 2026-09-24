<?php
declare(strict_types=1);

/**
 * CAT-004 variant cosmetics: remove the duplicate current-value label and
 * the selector's extra top margin. Product page only; no DB changes.
 *
 * Live source: owner export CAT-004_ui_sources_20260923.tar.gz.
 * Root cause: .bs-product__info supplies a 13px row gap, and .bs-variant
 * adds a 20px top margin inside #product. The active chip already displays
 * the selected value; the label's separate current-value span repeats it.
 * Override history: CAT-004_variant-selector_20260916.php and
 * CAT-004_variant-family_20260919.php added the selector CSS and Twig.
 * This runner edits that source rule directly; it adds no override.
 *
 * Rollback: restore the three files from
 * _patch_backups/CAT-004_variant-cosmetics_20260923-<timestamp>/, then clear
 * the OpenCart theme cache. Run this patch before or after the companion
 * CAT-004_info-column-reflow_20260923.php.
 */

const PATCH_ID = 'CAT-004_variant-cosmetics_20260923';
const CSS_PATH = 'catalog/view/stylesheet/boostershop-ds.css';
const TWIG_PATH = 'catalog/view/template/product/product.twig';
const HEADER_PATH = 'catalog/view/template/common/header.twig';
const CSS_MARKER = 'CAT-004-VC selector top margin';
const TWIG_MARKER = 'CAT-004-VC active chip is the current-value indicator';

function out(string $value): void { echo $value . PHP_EOL; }
function fail(string $value): void { throw new RuntimeException($value); }
function norm(string $value): string { return str_replace(array("\r\n", "\r"), "\n", $value); }
function expect_count(string $haystack, string $needle, int $count, string $name): void {
    $actual = substr_count($haystack, $needle);
    if ($actual !== $count) fail('anchor_count_' . $name . '=' . $actual . ',expected=' . $count);
}
function put(string $path, string $content): void {
    $temp = $path . '.cat004vc.tmp.' . getmypid();
    if (file_put_contents($temp, $content, LOCK_EX) === false) fail('temp_write_failed=' . $path);
    if (!@rename($temp, $path)) {
        if (!@copy($temp, $path)) { @unlink($temp); fail('replace_failed=' . $path); }
        @unlink($temp);
    }
}
function css_token_replace(string $header, string $token): string {
    $pattern = '~(catalog/view/stylesheet/boostershop-ds\.css\?v=)([A-Za-z0-9._-]+)~';
    $count = preg_match_all($pattern, $header);
    if ($count !== 1) fail('anchor_count_header_css_token=' . (string) $count . ',expected=1');
    $result = preg_replace($pattern, '${1}' . $token, $header, 1);
    if (!is_string($result)) fail('header_css_token_replace_failed');
    return $result;
}

set_exception_handler(static function (Throwable $error): void {
    out('error=' . $error->getMessage());
    out('done=failed');
    exit(1);
});

$root = rtrim((string) (getenv('BS_PATCH_ROOT') ?: (getcwd() ?: __DIR__)), "/\\");
$paths = array(CSS_PATH, TWIG_PATH, HEADER_PATH);
$original = array();
out('patch=' . PATCH_ID);
out('cwd=' . $root);
out('time=' . date('c'));
out('db_changes=none');
foreach ($paths as $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) fail('target_not_found=' . $relative);
    if (!is_writable($path)) fail('target_not_writable=' . $relative);
    $data = file_get_contents($path);
    if (!is_string($data)) fail('target_read_failed=' . $relative);
    $original[$relative] = norm($data);
    out('file_preflight=ok:' . $relative);
}

$css = $original[CSS_PATH];
$twig = $original[TWIG_PATH];
$header = $original[HEADER_PATH];
$cssDone = strpos($css, CSS_MARKER) !== false;
$twigDone = strpos($twig, TWIG_MARKER) !== false;
if ($cssDone !== $twigDone) fail('partial_patch_marker_state');
if ($cssDone) {
    expect_count($css, CSS_MARKER, 1, 'css_marker_final');
    expect_count($twig, TWIG_MARKER, 1, 'twig_marker_final');
    expect_count($css, '.bs-variant { margin: 0 0 20px; }', 1, 'selector_margin_final');
    expect_count($twig, 'bs-variant__current', 0, 'duplicate_value_final');
    out('already_applied=yes');
    out('done=ok');
    @unlink(__FILE__);
    exit(0);
}

$oldCss = '.bs-variant { margin: 20px 0; }';
$newCss = "/* " . CSS_MARKER . " — keep the 13px info-column row gap. */\n"
    . '.bs-variant { margin: 0 0 20px; }';
$oldTwig = <<<'TWIG'
                  <div class="bs-variant__label">
                    {{ group.name }}
                    {% for value in group.values %}
                      {% if value.is_current %}<span class="bs-variant__current">{{ value.label }}</span>{% endif %}
                    {% endfor %}
                  </div>
TWIG;
$newTwig = <<<'TWIG'
                  <div class="bs-variant__label">
                    {{ group.name }}
                    {# CAT-004-VC active chip is the current-value indicator #}
                  </div>
TWIG;
expect_count($css, $oldCss, 1, 'selector_margin');
expect_count($twig, $oldTwig, 1, 'selector_label');
$updated = array(
    CSS_PATH => str_replace($oldCss, $newCss, $css),
    TWIG_PATH => str_replace($oldTwig, $newTwig, $twig),
    HEADER_PATH => css_token_replace($header, 'cat004-variant-cosmetics-20260923'),
);
expect_count($updated[CSS_PATH], CSS_MARKER, 1, 'css_marker_final');
expect_count($updated[TWIG_PATH], TWIG_MARKER, 1, 'twig_marker_final');
expect_count($updated[TWIG_PATH], 'bs-variant__current', 0, 'duplicate_value_final');
out('assert=transformed_state:ok');

$backupDir = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
$written = array();
try {
    foreach ($paths as $relative) {
        $backup = $backupDir . '/' . $relative;
        if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0775, true) && !is_dir(dirname($backup))) {
            fail('backup_dir_create_failed=' . $relative);
        }
        if (!copy($root . '/' . $relative, $backup)) fail('backup_copy_failed=' . $relative);
        out('backup=' . str_replace($root . '/', '', $backup));
    }
    foreach ($paths as $relative) {
        put($root . '/' . $relative, $updated[$relative]);
        $written[] = $relative;
        out('changed=' . $relative);
    }
    $actualCss = file_get_contents($root . '/' . CSS_PATH);
    $actualTwig = file_get_contents($root . '/' . TWIG_PATH);
    if (!is_string($actualCss) || !is_string($actualTwig)) fail('post_write_read_failed');
    expect_count(norm($actualCss), CSS_MARKER, 1, 'css_marker_written');
    expect_count(norm($actualTwig), TWIG_MARKER, 1, 'twig_marker_written');
    out('assert=final_state:ok');
    out('php_lint=not_applicable:no_php_target_changed');
    out('cache_clear=required:theme');
    out('done=ok');
    out(@unlink(__FILE__) ? 'self_delete=ok' : 'self_delete=failed');
} catch (Throwable $error) {
    foreach ($written as $relative) {
        if (@copy($backupDir . '/' . $relative, $root . '/' . $relative)) out('rollback=restored:' . $relative);
        else out('rollback=failed:' . $relative);
    }
    out('error=' . $error->getMessage());
    out('done=failed');
    exit(1);
}
