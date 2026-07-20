<?php
/**
 * RD-13.1I — captcha polish: drop the title, unfreeze the fit-scaler
 *
 *   1. "Перевірка безпеки" (CSS ::before) removed per owner.
 *   2. RD-13.1H scaler measured the card once and stopped (clearInterval on
 *      first success) — if that happened before the final layout settled,
 *      the widget stayed undersized. v2: cheap interval that re-applies
 *      only when the computed scale actually changes; upscale cap 1.45.
 *
 * CSS + checkout-reskin.js + version bumps (header.twig CSS, checkout.twig JS).
 * Requires RD-13.1H applied. No logic/validation change.
 *
 * Usage (site root): php RD-13.1I_captcha_title_scale_polish_20260712.php [--dry-run]
 */
declare(strict_types=1);

$id = 'RD-13.1I';
$dryRun = in_array('--dry-run', $argv ?? [], true);
$root = getcwd() ?: '.';
$targets = [
    'css' => 'catalog/view/stylesheet/boostershop-ds.css',
    'reskin' => 'catalog/view/javascript/checkout-reskin.js',
    'checkout' => 'catalog/view/template/checkout/checkout.twig',
    'header' => 'catalog/view/template/common/header.twig',
];

function rd131i_log(string $message): void { global $id; echo '[' . $id . '] ' . $message . PHP_EOL; }
function rd131i_fail(string $message): void { rd131i_log('ERROR: ' . $message); exit(1); }
function rd131i_read(string $path): string {
    if (!is_file($path) || !is_readable($path)) { rd131i_fail('target missing or unreadable: ' . $path); }
    $data = file_get_contents($path);
    if ($data === false) { rd131i_fail('cannot read: ' . $path); }
    return str_replace("\r\n", "\n", $data);
}
function rd131i_replace_once(string $source, string $anchor, string $replacement, string $label): string {
    $count = substr_count($source, $anchor);
    if ($count !== 1) { rd131i_fail($label . ' anchor count is ' . $count . ', expected 1'); }
    return str_replace($anchor, $replacement, $source);
}
function rd131i_backup(string $root, string $backupRoot, string $relative): string {
    $source = $root . '/' . $relative;
    $destination = $backupRoot . '/' . $relative;
    $directory = dirname($destination);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) { rd131i_fail('cannot create backup directory: ' . $directory); }
    if (!copy($source, $destination)) { rd131i_fail('backup failed: ' . $relative); }
    return $destination;
}
function rd131i_write(string $path, string $data): void {
    if (file_put_contents($path, $data) === false) { rd131i_fail('write failed: ' . $path); }
}

rd131i_log('cwd=' . $root);
rd131i_log('time=' . date(DATE_ATOM));

$paths = [];
$files = [];
foreach ($targets as $key => $relative) {
    $paths[$key] = $root . '/' . $relative;
    $files[$key] = rd131i_read($paths[$key]);
}

/* Guards */
$applied = strpos($files['css'], 'RD-13.1I') !== false && strpos($files['reskin'], 'RD-13.1I') !== false;
if ($applied) { rd131i_log('already_applied=yes'); exit(0); }
if (strpos($files['css'], 'RD-13.1I') !== false || strpos($files['reskin'], 'RD-13.1I') !== false) {
    rd131i_fail('partial marker state detected; restore the newest RD-13.1I backup before retrying');
}
if (strpos($files['reskin'], 'RD-13.1H') === false || strpos($files['header'], 'boostershop-ds.css?v=rd13.1h-20260712') === false) {
    rd131i_fail('RD-13.1H markers not found; RD-13.1I must be applied on top of RD-13.1H');
}

/* ---- 1 · CSS: remove the "Перевірка безпеки" title ---- */
$files['css'] = rd131i_replace_once(
    $files['css'],
    <<<'CSS'
#checkout-checkout.bs-co .bs-co-moved-captcha::before {
  color: var(--bs-ink-2);
  content: 'Перевірка безпеки';
  display: block;
  font-size: 12.5px;
  font-weight: 600;
  margin-bottom: 8px;
}
CSS,
    <<<'CSS'
/* RD-13.1I: "Перевірка безпеки" title removed per owner — the widget itself
   is the content of this card. */
CSS,
    'captcha title removal'
);

/* ---- 2 · JS: scaler v2 — never freezes, re-applies on real change ---- */
$files['reskin'] = rd131i_replace_once(
    $files['reskin'],
    <<<'JS'
/* RD-13.1H: fit the CAPTCHA widget to its card (owner: "по контуру").
   The Google widget is a fixed 304x78 iframe; the only clean way to make it
   span the card is a uniform transform on the host with layout-height
   compensation. Upscale is capped at 1.3 to stay crisp. */
(function () {
  'use strict';

  var NATURAL_W = 304;
  var NATURAL_H = 78;

  function fitCaptcha() {
    var host = document.querySelector('.bs-co-moved-captcha [id^="g-recaptcha-"]');
    if (!host || !host.querySelector('iframe')) { return false; }

    var box = host.parentElement;
    if (!box || !box.clientWidth) { return false; }

    var scale = Math.min(box.clientWidth / NATURAL_W, 1.3);
    host.style.width = NATURAL_W + 'px';
    host.style.height = NATURAL_H + 'px';
    host.style.transform = 'scale(' + scale.toFixed(4) + ')';
    host.style.transformOrigin = '0 0';
    host.style.margin = '0 0 0 ' + Math.max(0, Math.round((box.clientWidth - NATURAL_W * scale) / 2)) + 'px';
    box.style.height = Math.round(NATURAL_H * scale) + 'px';
    box.style.overflow = 'visible';
    return true;
  }

  var tries = 0;
  var timer = setInterval(function () {
    tries += 1;
    if (fitCaptcha() || tries > 60) { clearInterval(timer); }
  }, 500);

  var resizeTimer = null;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(fitCaptcha, 150);
  });
})();
JS,
    <<<'JS'
/* RD-13.1I: fit the CAPTCHA widget to its card (owner: "по контуру").
   v2 of the RD-13.1H scaler: the H version measured once and stopped, so an
   early (pre-layout) measurement froze the widget undersized. This version
   keeps a cheap watcher running and re-applies only when the computed scale
   actually changes. Upscale cap raised to 1.45. */
(function () {
  'use strict';

  var NATURAL_W = 304;
  var NATURAL_H = 78;
  var MAX_SCALE = 1.45;
  var lastScale = 0;

  function fitCaptcha() {
    var host = document.querySelector('.bs-co-moved-captcha [id^="g-recaptcha-"]');
    if (!host || !host.querySelector('iframe')) { return; }

    var box = host.parentElement;
    if (!box || !box.clientWidth) { return; }

    var scale = Math.min(box.clientWidth / NATURAL_W, MAX_SCALE);
    if (Math.abs(scale - lastScale) < 0.01) { return; }
    lastScale = scale;

    host.style.width = NATURAL_W + 'px';
    host.style.height = NATURAL_H + 'px';
    host.style.transform = 'scale(' + scale.toFixed(4) + ')';
    host.style.transformOrigin = '0 0';
    host.style.margin = '0 0 0 ' + Math.max(0, Math.round((box.clientWidth - NATURAL_W * scale) / 2)) + 'px';
    box.style.height = Math.round(NATURAL_H * scale) + 'px';
    box.style.overflow = 'visible';
  }

  setInterval(fitCaptcha, 600);

  var resizeTimer = null;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    lastScale = 0;
    resizeTimer = setTimeout(fitCaptcha, 150);
  });
})();
JS,
    'scaler v2'
);

/* ---- 3 · Version bumps ---- */
$files['header'] = rd131i_replace_once(
    $files['header'],
    'boostershop-ds.css?v=rd13.1h-20260712',
    'boostershop-ds.css?v=rd13.1i-20260712',
    'header CSS version'
);
$files['checkout'] = rd131i_replace_once(
    $files['checkout'],
    '<script src="catalog/view/javascript/checkout-reskin.js?v=rd13.1h-20260712"></script>',
    '<script src="catalog/view/javascript/checkout-reskin.js?v=rd13.1i-20260712"></script>',
    'reskin cache-buster'
);

/* ---- Post-replace verification ---- */
$checks = [
    'title gone' => strpos($files['css'], "content: 'Перевірка безпеки'") === false,
    'css marker' => strpos($files['css'], 'RD-13.1I') !== false,
    'scaler v2' => strpos($files['reskin'], 'MAX_SCALE = 1.45') !== false,
    'old scaler gone' => strpos($files['reskin'], 'tries > 60') === false,
    'header buster' => strpos($files['header'], 'v=rd13.1i-20260712') !== false && strpos($files['header'], 'v=rd13.1h-20260712') === false,
    'js buster' => strpos($files['checkout'], 'checkout-reskin.js?v=rd13.1i-20260712') !== false,
];
foreach ($checks as $label => $ok) {
    if (!$ok) { rd131i_fail('post-replace check failed: ' . $label); }
}

if ($dryRun) {
    rd131i_log('dry_run=ok');
    rd131i_log('would_change=' . implode(',', $targets));
    exit(0);
}

$backupRoot = $root . '/_patch_backups/RD-13.1I_captcha_title_scale_polish_20260712_' . date('Ymd_His');
foreach ($targets as $key => $relative) { rd131i_log('backup=' . rd131i_backup($root, $backupRoot, $relative)); }
foreach ($targets as $key => $relative) { rd131i_write($paths[$key], $files[$key]); }

rd131i_log('changed=' . implode(',', $targets));
rd131i_log('done=ok');
rd131i_log('qa=hard refresh checkout: no title in the captcha card; widget spans the card width on mobile; checkbox clickable; order flow passes');
if (@unlink(__FILE__)) { rd131i_log('self_delete=ok'); } else { rd131i_log('self_delete=skipped'); }
