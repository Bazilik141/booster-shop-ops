<?php
declare(strict_types=1);

/**
 * CAT-004 product info reflow: stock/manufacturer two columns on desktop,
 * pre-order ETA below stock, and product trust icons beside their text.
 * No DB, price, checkout, canonical, or schema changes.
 *
 * Live source: owner export CAT-004_ui_sources_20260923.tar.gz.
 * Root cause: .bs-pp-meta rows are full-width flex rows; the product-scoped
 * .bs-trust-strip__item rule uses flex-direction: column. Both add height.
 * Override history: cat002_5c_mobile_visual_breadcrumb_20260630.php touches
 * #content > .bs-trust-strip. The live common/home.twig has a second strip.
 * This runner edits only .bs-product__info-scoped trust rules and adds
 * product-scoped meta placement; it never changes the homepage component.
 *
 * Rollback: restore the three files from
 * _patch_backups/CAT-004_info-column-reflow_20260923-<timestamp>/, then clear
 * the OpenCart theme cache. Independent of CAT-004_variant-cosmetics_20260923.php.
 */

const PATCH_ID = 'CAT-004_info-column-reflow_20260923';
const CSS_PATH = 'catalog/view/stylesheet/boostershop-ds.css';
const TWIG_PATH = 'catalog/view/template/product/product.twig';
const HEADER_PATH = 'catalog/view/template/common/header.twig';
const CSS_MARKER = 'CAT-004-ICR product info reflow';
const TWIG_MARKER = 'bs-pp-meta__row--manufacturer';

function out(string $value): void { echo $value . PHP_EOL; }
function fail(string $value): void { throw new RuntimeException($value); }
function norm(string $value): string { return str_replace(array("\r\n", "\r"), "\n", $value); }
function expect_count(string $haystack, string $needle, int $count, string $name): void {
    $actual = substr_count($haystack, $needle);
    if ($actual !== $count) fail('anchor_count_' . $name . '=' . $actual . ',expected=' . $count);
}
function put(string $path, string $content): void {
    $temp = $path . '.cat004icr.tmp.' . getmypid();
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
    foreach (array('manufacturer', 'stock', 'eta') as $name) {
        expect_count($twig, 'bs-pp-meta__row--' . $name, 1, $name . '_class_final');
    }
    out('already_applied=yes');
    out('done=ok');
    @unlink(__FILE__);
    exit(0);
}

$oldMetaCss = '.bs-pp-meta__link:hover { text-decoration: underline; }';
$newMetaCss = <<<'CSS'
.bs-pp-meta__link:hover { text-decoration: underline; }

/* Let long product values wrap within a meta cell at every width. */
.bs-product__info .bs-pp-meta__row > :last-child {
  min-width: 0;
  overflow-wrap: anywhere;
}

/* CAT-004-ICR product info reflow: explicit placement; mobile stays stacked. */
@media (min-width: 1024px) {
  .bs-product__info .bs-pp-meta {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  }
  .bs-product__info .bs-pp-meta__row { min-width: 0; min-height: 44px; }
  .bs-product__info .bs-pp-meta__row .bs-pp-meta__label { width: auto; }
  .bs-product__info .bs-pp-meta__row--stock {
    grid-column: 1; grid-row: 1;
    background: #fff;
    border-right: 1px solid var(--bs-line-2, #E5E7EB);
    border-bottom: 0;
  }
  .bs-product__info .bs-pp-meta__row--manufacturer {
    grid-column: 2; grid-row: 1;
    background: #fff;
    border-bottom: 0;
  }
  .bs-product__info .bs-pp-meta__row--eta {
    grid-column: 1; grid-row: 2;
    background: var(--bs-bg, #F9FAFB);
    border-top: 1px solid var(--bs-line-2, #E5E7EB);
  }
}
CSS;

$oldTrustCss = <<<'CSS'
.bs-product__info .bs-trust-strip__item {
  position: static;
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 5px;
  text-align: center;
  color: var(--bs-gold, #C68A00);
}
CSS;
$newTrustCss = <<<'CSS'
.bs-product__info .bs-trust-strip__item {
  position: static;
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
  gap: 6px;
  text-align: left;
  color: var(--bs-gold, #C68A00);
}
.bs-product__info .bs-trust-strip__item svg { flex: 0 0 auto; }
CSS;
$oldMobileCss = <<<'CSS'
  .bs-product__info .bs-trust-strip__item svg {
    width: 14px;
    height: 14px;
  }
CSS;
$newMobileCss = <<<'CSS'
  /* Keep the icon beside the first line when product trust text wraps. */
  .bs-product__info .bs-trust-strip__item { align-items: flex-start; }
  .bs-product__info .bs-trust-strip__item svg {
    width: 14px;
    height: 14px;
  }
CSS;
$oldManufacturer = <<<'TWIG'
          <div class="bs-pp-meta">
            <div class="bs-pp-meta__row">
              <span class="bs-pp-meta__label">Виробник</span>
TWIG;
$newManufacturer = <<<'TWIG'
          <div class="bs-pp-meta">
            <div class="bs-pp-meta__row bs-pp-meta__row--manufacturer">
              <span class="bs-pp-meta__label">Виробник</span>
TWIG;
$oldStock = <<<'TWIG'
            <div class="bs-pp-meta__row">
              {% if _is_preorder %}
TWIG;
$newStock = <<<'TWIG'
            <div class="bs-pp-meta__row bs-pp-meta__row--stock">
              {% if _is_preorder %}
TWIG;
$oldEta = <<<'TWIG'
            {% if _is_preorder %}
              <div class="bs-pp-meta__row">
                <span class="bs-pp-meta__label">Доставка</span>
TWIG;
$newEta = <<<'TWIG'
            {% if _is_preorder %}
              <div class="bs-pp-meta__row bs-pp-meta__row--eta">
                <span class="bs-pp-meta__label">Доставка</span>
TWIG;

foreach (array(
    'meta_css' => $oldMetaCss,
    'trust_css' => $oldTrustCss,
    'mobile_css' => $oldMobileCss,
) as $name => $anchor) expect_count($css, $anchor, 1, $name);
foreach (array(
    'manufacturer' => $oldManufacturer,
    'stock' => $oldStock,
    'eta' => $oldEta,
) as $name => $anchor) expect_count($twig, $anchor, 1, $name . '_twig');

$updatedCss = str_replace($oldMetaCss, $newMetaCss, $css);
$updatedCss = str_replace($oldTrustCss, $newTrustCss, $updatedCss);
$updatedCss = str_replace($oldMobileCss, $newMobileCss, $updatedCss);
$updatedTwig = str_replace($oldManufacturer, $newManufacturer, $twig);
$updatedTwig = str_replace($oldStock, $newStock, $updatedTwig);
$updatedTwig = str_replace($oldEta, $newEta, $updatedTwig);
$updated = array(
    CSS_PATH => $updatedCss,
    TWIG_PATH => $updatedTwig,
    HEADER_PATH => css_token_replace($header, 'cat004-info-reflow-20260923'),
);
expect_count($updated[CSS_PATH], CSS_MARKER, 1, 'css_marker_final');
foreach (array('manufacturer', 'stock', 'eta') as $name) {
    expect_count($updated[TWIG_PATH], 'bs-pp-meta__row--' . $name, 1, $name . '_class_final');
}
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
    foreach (array('manufacturer', 'stock', 'eta') as $name) {
        expect_count(norm($actualTwig), 'bs-pp-meta__row--' . $name, 1, $name . '_class_written');
    }
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
