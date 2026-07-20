<?php
/**
 * RD-13.1G — captcha dead band + receiver ghost rows (verified causes)
 *
 * Verified against source + DB backup (boosters_ocart49.sql: v2_checkbox,
 * badge_size=normal, hide_badge=0, standard .row/.col-sm-10 layout):
 *
 *   1. The gray band under the CAPTCHA widget survived every round because
 *      Bootstrap's `.mb-3` is `margin-bottom: 1rem !important` — every prior
 *      `margin: 0` override silently lost the cascade. Fix: `!important`.
 *   2. Widget centering in RD-13.1F targeted only `[id^="g-recaptcha-"]`;
 *      the module's event-injected markup can also render the host as a
 *      classed `.g-recaptcha` div. Fix: center BOTH host variants via
 *      `width: fit-content; margin auto` (no flex, no media dependency).
 *   3. Receiver card bottom band: untagged stock blocks without a visible
 *      control sit in the grid as ghost tracks; each one costs a 16px gap.
 *      Fix: orderReceiverGrid() hides children that contain no visible
 *      input/select/textarea (tagged recipient fields are never touched).
 *
 * CSS + checkout-reskin.js + cache-busters. No logic/validation change.
 * Requires RD-13.1F applied.
 *
 * Usage (site root): php RD-13.1G_captcha_band_ghost_rows_20260712.php [--dry-run]
 */
declare(strict_types=1);

$id = 'RD-13.1G';
$dryRun = in_array('--dry-run', $argv ?? [], true);
$root = getcwd() ?: '.';
$targets = [
    'css' => 'catalog/view/stylesheet/boostershop-ds.css',
    'reskin' => 'catalog/view/javascript/checkout-reskin.js',
    'checkout' => 'catalog/view/template/checkout/checkout.twig',
];

function rd131g_log(string $message): void { global $id; echo '[' . $id . '] ' . $message . PHP_EOL; }
function rd131g_fail(string $message): void { rd131g_log('ERROR: ' . $message); exit(1); }
function rd131g_read(string $path): string {
    if (!is_file($path) || !is_readable($path)) { rd131g_fail('target missing or unreadable: ' . $path); }
    $data = file_get_contents($path);
    if ($data === false) { rd131g_fail('cannot read: ' . $path); }
    return str_replace("\r\n", "\n", $data);
}
function rd131g_replace_once(string $source, string $anchor, string $replacement, string $label): string {
    $count = substr_count($source, $anchor);
    if ($count !== 1) { rd131g_fail($label . ' anchor count is ' . $count . ', expected 1'); }
    return str_replace($anchor, $replacement, $source);
}
function rd131g_backup(string $root, string $backupRoot, string $relative): string {
    $source = $root . '/' . $relative;
    $destination = $backupRoot . '/' . $relative;
    $directory = dirname($destination);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) { rd131g_fail('cannot create backup directory: ' . $directory); }
    if (!copy($source, $destination)) { rd131g_fail('backup failed: ' . $relative); }
    return $destination;
}
function rd131g_write(string $path, string $data): void {
    if (file_put_contents($path, $data) === false) { rd131g_fail('write failed: ' . $path); }
}

rd131g_log('cwd=' . $root);
rd131g_log('time=' . date(DATE_ATOM));

$paths = [];
$files = [];
foreach ($targets as $key => $relative) {
    $paths[$key] = $root . '/' . $relative;
    $files[$key] = rd131g_read($paths[$key]);
}

/* Guards */
$cssApplied = strpos($files['css'], 'RD-13.1G') !== false;
$checkoutApplied = strpos($files['checkout'], 'v=rd13.1g-20260712') !== false;
$reskinApplied = strpos($files['reskin'], 'RD-13.1G') !== false;
if ($cssApplied && $checkoutApplied && $reskinApplied) { rd131g_log('already_applied=yes'); exit(0); }
if ($cssApplied || $checkoutApplied || $reskinApplied) {
    rd131g_fail('partial marker state detected; restore the newest RD-13.1G backup before retrying');
}
if (strpos($files['css'], 'RD-13.1F') === false) {
    rd131g_fail('RD-13.1F markers not found; RD-13.1G must be applied on top of RD-13.1F');
}

/* ---- 1 · CAPTCHA band: .mb-3 is !important in Bootstrap ---- */
$files['css'] = rd131g_replace_once(
    $files['css'],
    <<<'CSS'
#checkout-checkout.bs-co .bs-co-moved-captcha .row {
  --bs-gutter-x: 0;
  margin: 0; /* RD-13.1F: mb-3 bottom margin left a dead band in the card */
}
CSS,
    <<<'CSS'
#checkout-checkout.bs-co .bs-co-moved-captcha .row {
  --bs-gutter-x: 0;
  /* RD-13.1G: Bootstrap .mb-3 carries !important — a plain margin:0 loses. */
  margin: 0 !important;
}
CSS,
    'captcha row margin important'
);

/* ---- 2 · Center the widget: cover BOTH host variants, all widths ---- */
$files['css'] = rd131g_replace_once(
    $files['css'],
    <<<'CSS'
/* RD-13.1E: the CAPTCHA is never CSS-transformed; widget size is chosen at
   grecaptcha.render time (normal / compact). */
#checkout-checkout.bs-co .bs-co-moved-captcha [id^="g-recaptcha-"],
#checkout-checkout.bs-co .bs-co-moved-captcha .g-recaptcha {
  min-height: 78px;
}
CSS,
    <<<'CSS'
/* RD-13.1G: no CSS transform ever; widget size comes from grecaptcha.render
   (normal / compact). Host is centered via fit-content — works for both the
   id-based host and the classed .g-recaptcha variant, desktop and mobile. */
#checkout-checkout.bs-co .bs-co-moved-captcha [id^="g-recaptcha-"],
#checkout-checkout.bs-co .bs-co-moved-captcha .g-recaptcha {
  min-height: 78px;
  width: fit-content;
  max-width: 100%;
  margin-left: auto;
  margin-right: auto;
}
CSS,
    'captcha host centering'
);

/* Drop the F-round flex rule (superseded by fit-content centering) */
$files['css'] = rd131g_replace_once(
    $files['css'],
    <<<'CSS'
  /* 8 · RD-13.1F: compact widget centered in the card; overflow guard stays. */
  #checkout-checkout.bs-co .bs-co-moved-captcha { overflow: hidden; }
  #checkout-checkout.bs-co .bs-co-moved-captcha [id^="g-recaptcha-"] {
    display: flex;
    justify-content: center;
  }
CSS,
    <<<'CSS'
  /* 8 · RD-13.1G: centering moved to the global fit-content rule. */
  #checkout-checkout.bs-co .bs-co-moved-captcha { overflow: hidden; }
CSS,
    'mobile flex centering removal'
);

/* ---- 3 · Receiver grid: hide ghost tracks (no visible control) ---- */
$files['reskin'] = rd131g_replace_once(
    $files['reskin'],
    <<<'JS'
    Array.prototype.forEach.call(grid.children, function (child) {
      if (!child.style.order) {
        child.style.order = '10';
      }
      child.style.minWidth = '0';
    });
JS,
    <<<'JS'
    Array.prototype.forEach.call(grid.children, function (child) {
      if (!child.style.order) {
        child.style.order = '10';
      }
      child.style.minWidth = '0';

      // RD-13.1G: untagged children without a visible form control are ghost
      // grid tracks — each one costs a full row gap of empty space.
      if (!/bs-co-recipient-field/.test(child.className)) {
        var control = child.querySelector('input:not([type="hidden"]), select, textarea');
        if (!control) {
          child.style.display = 'none';
        }
      }
    });
JS,
    'ghost track hiding'
);

/* ---- 4 · Cache-busters ---- */
$files['checkout'] = rd131g_replace_once(
    $files['checkout'],
    '<link rel="stylesheet" href="catalog/view/stylesheet/boostershop-ds.css?v=rd13.1f-20260712">',
    '<link rel="stylesheet" href="catalog/view/stylesheet/boostershop-ds.css?v=rd13.1g-20260712">',
    'CSS cache-buster'
);
$files['checkout'] = rd131g_replace_once(
    $files['checkout'],
    '<script src="catalog/view/javascript/checkout-reskin.js?v=rd13.1b-20260712a"></script>',
    '<script src="catalog/view/javascript/checkout-reskin.js?v=rd13.1g-20260712"></script>',
    'reskin cache-buster'
);

/* ---- Post-replace verification ---- */
$checks = [
    'mb-3 override important' => strpos($files['css'], "margin: 0 !important;\n}\n") !== false && strpos($files['css'], 'Bootstrap .mb-3 carries !important') !== false,
    'fit-content centering' => strpos($files['css'], 'width: fit-content;') !== false,
    'no captcha flex centering left' => strpos($files['css'], "moved-captcha [id^=\"g-recaptcha-\"] {\n    display: flex;") === false,
    'css marker' => strpos($files['css'], 'RD-13.1G') !== false,
    'reskin ghost hiding' => strpos($files['reskin'], 'RD-13.1G') !== false,
    'checkout css buster' => strpos($files['checkout'], 'v=rd13.1g-20260712') !== false,
    'old css buster gone' => strpos($files['checkout'], 'v=rd13.1f-20260712') === false,
    'old js buster gone' => strpos($files['checkout'], 'v=rd13.1b-20260712a') === false,
];
foreach ($checks as $label => $ok) {
    if (!$ok) { rd131g_fail('post-replace check failed: ' . $label); }
}

if ($dryRun) {
    rd131g_log('dry_run=ok');
    rd131g_log('would_change=' . implode(',', $targets));
    exit(0);
}

$backupRoot = $root . '/_patch_backups/RD-13.1G_captcha_band_ghost_rows_20260712_' . date('Ymd_His');
foreach ($targets as $key => $relative) { rd131g_log('backup=' . rd131g_backup($root, $backupRoot, $relative)); }
foreach ($targets as $key => $relative) { rd131g_write($paths[$key], $files[$key]); }

rd131g_log('changed=' . implode(',', $targets));
rd131g_log('done=ok');
rd131g_log('qa=guest checkout: captcha card = title + centered widget, no gray band below; receiver card has no empty band at the bottom; both mobile and desktop');
if (@unlink(__FILE__)) { rd131g_log('self_delete=ok'); } else { rd131g_log('self_delete=skipped'); }
