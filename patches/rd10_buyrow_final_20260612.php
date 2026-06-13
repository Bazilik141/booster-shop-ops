<?php
declare(strict_types=1);

/**
 * RD-10D3 — buy row final fix (CSS-only, file-based; theme override_rows=0).
 *
 * Fixes:
 *  - Desktop: "У кошик" did not fill its column because an extension injects
 *    <input type="hidden" name="category_id"> inside .bs-buy-row, stealing a grid/flex slot.
 *    -> neutralize stray hidden inputs in the row + authoritative flex layout (CTA grows to trust-strip right edge).
 *  - Mobile (361-480px, incl. 362): qty input overlapped the "+" because the middle
 *    stepper column was fixed-width. -> middle column 1fr + box-sizing so the input never overflows.
 *
 * Appends one delimited RD-10D3 block to boostershop-ds.css (idempotent) and bumps its cache-bust.
 * Does NOT touch product.twig, controller, JSON-LD, R-04, checkout/payment. No DB (CSS is a static asset).
 */

const PATCH_ID = 'rd10_buyrow_final_20260612';
const DS_CACHE_BUST = 'rd10-buyrow-final-20260612';

function out(string $m): void { echo $m . PHP_EOL; }
function fail(string $m): never { fwrite(STDERR, 'ERROR: ' . $m . PHP_EOL); exit(1); }
function norm(string $s): string { return str_replace(["\r\n", "\r"], "\n", $s); }

function read_file_or_fail(string $p): string {
    if (!is_file($p)) { fail('missing file: ' . $p); }
    $c = file_get_contents($p);
    if ($c === false) { fail('cannot read: ' . $p); }
    return $c;
}

function write_with_backup(string $path, string $new, string $backupDir): bool {
    $old = read_file_or_fail($path);
    if ($old === $new) { out('unchanged: ' . $path); return false; }
    $bak = $backupDir . '/' . str_replace(['/', '\\', ':'], '_', $path) . '.bak';
    if (!copy($path, $bak)) { fail('cannot backup: ' . $path); }
    if (file_put_contents($path, $new) === false) { fail('cannot write: ' . $path); }
    out('changed: ' . $path);
    out('backup : ' . $bak);
    return true;
}

function rd10d3_block(): string {
    return <<<'CSS'

/* ==== RD-10D3: buy row final — CTA fills to trust width, qty no-overlap, kill injected hidden inputs 20260612 ==== */

/* An extension injects <input type="hidden" name="category_id"> inside .bs-buy-row.
   Force every hidden input out of the row's flex/grid flow so it can never steal a slot. */
.bs-buy-row > input[type="hidden"] { display: none !important; }

/* Authoritative single-row layout: label | qty | CTA(grows to trust-strip right edge). */
.bs-product__info .bs-buy-row {
  display: flex !important;
  flex-wrap: nowrap !important;
  align-items: stretch !important;
  gap: 10px !important;
  width: 100% !important;
}
.bs-product__info .bs-buy-row__label {
  display: inline-flex !important;
  align-items: center !important;
  flex: 0 0 auto !important;
  min-height: 44px !important;
  white-space: nowrap !important;
}
.bs-product__info .bs-main-qty {
  flex: 0 0 118px !important;
  width: 118px !important;
  display: grid !important;
  grid-template-columns: 38px 1fr 38px !important;
  min-height: 44px !important;
  box-sizing: border-box !important;
}
.bs-product__info .bs-main-qty__input {
  min-width: 0 !important;
  width: 100% !important;
  box-sizing: border-box !important;
  text-align: center !important;
  -moz-appearance: textfield;
}
.bs-product__info .bs-main-qty__input::-webkit-outer-spin-button,
.bs-product__info .bs-main-qty__input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.bs-product__info .bs-buy-row__cta {
  flex: 1 1 auto !important;
  width: auto !important;
  min-width: 0 !important;
  min-height: 44px !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 8px !important;
}

/* Tablet/mobile: keep one row, comfortable qty, CTA still fills; input can't overflow onto "+". */
@media (max-width: 768px) {
  .bs-product__info .bs-buy-row { gap: 8px !important; }
  .bs-product__info .bs-buy-row__label-full { display: none !important; }
  .bs-product__info .bs-buy-row__label-short { display: inline !important; }
  .bs-product__info .bs-main-qty {
    flex: 0 0 110px !important;
    width: 110px !important;
    grid-template-columns: 34px 1fr 34px !important;
  }
}
@media (max-width: 360px) {
  .bs-product__info .bs-main-qty {
    flex-basis: 104px !important;
    width: 104px !important;
    grid-template-columns: 32px 1fr 32px !important;
  }
  .bs-product__info .bs-buy-row__cta { padding-left: 8px !important; padding-right: 8px !important; }
}

/* ==== /RD-10D3 ==== */

CSS;
}

function transform_ds_css(string $content): string {
    $src = norm($content);
    // idempotent: drop any prior RD-10D3 block before re-adding
    $src = preg_replace('~\n?/\* ==== RD-10D3:.*?/\* ==== /RD-10D3 ==== \*/\n?~s', "\n", $src);
    if ($src === null) { fail('regex failure removing prior RD-10D3 block'); }
    return rtrim($src) . "\n" . rd10d3_block() . "\n";
}

function transform_header(string $content): string {
    $src = norm($content);
    if (strpos($src, 'boostershop-ds.css?v=') === false) {
        fail('header.twig: boostershop-ds.css link not found');
    }
    $src = preg_replace(
        '~(catalog/view/stylesheet/boostershop-ds\.css\?v=)[^"\']+~',
        '${1}' . DS_CACHE_BUST,
        $src,
        1,
        $n
    );
    if ($src === null || $n !== 1) { fail('header.twig: cache-bust replace failed'); }
    return $src;
}

// ---- run ----
$root = getcwd();
out('patch=' . PATCH_ID);
out('cwd=' . $root);
out('time=' . date('c'));

$cssPath = $root . '/catalog/view/stylesheet/boostershop-ds.css';
$hdrPath = $root . '/catalog/view/template/common/header.twig';
foreach ([$cssPath, $hdrPath] as $p) { if (!is_file($p)) { fail('required file missing: ' . $p); } }

$backupDir = $root . '/system/storage/backup/' . PATCH_ID . '_' . date('Ymd_His');
if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) { fail('cannot create backup dir: ' . $backupDir); }
out('backup_dir=' . $backupDir);

$changed = 0;
$changed += (int) write_with_backup($cssPath, transform_ds_css(read_file_or_fail($cssPath)), $backupDir);
$changed += (int) write_with_backup($hdrPath, transform_header(read_file_or_fail($hdrPath)), $backupDir);

out('changed_files=' . $changed);
out('ds_cache_bust=' . DS_CACHE_BUST);
out('done=ok');
@unlink(__FILE__);
