<?php
/**
 * RD-13.1H — kill the duplicate stylesheet link + CAPTCHA sized to the card
 *
 * DOM-verified on live guest checkout (Chrome, 2026-07-12):
 *
 *   ROOT CAUSE of the whole RD-13 visual saga: boostershop-ds.css is loaded
 *   TWICE on checkout — header link with ?v=cat002-5c1-20260630 (cache serves
 *   a stale 30.06 copy: 806 rules, old `.row > .col {padding:0}`) plus the
 *   checkout.twig link with the current buster (810 rules). The stale copy's
 *   higher-specificity rules override every new fix. Every "partial" fix
 *   since round 7 is explained by this duplication.
 *
 *   Fix: checkout.twig link removed; the single site-wide link gets a new
 *   version (?v=rd13.1h-20260712) so every page pulls the fresh file.
 *
 *   CAPTCHA (owner: widget must fill the card, "по контуру"):
 *   - live wrapper is `col mb-3` (alternative layout), not `.row` — its
 *     !important bottom margin is now killed too;
 *   - compact-render branch reverted (tiny widget in a big card was wrong);
 *   - checkout-reskin.js gets a fit-scaler: normal widget, scaled to the
 *     card inner width (upscale capped at 1.3), centered, height-compensated.
 *
 * Requires RD-13.1G applied. No logic/validation change.
 *
 * Usage (site root): php RD-13.1H_single_css_link_captcha_fit_20260712.php [--dry-run]
 */
declare(strict_types=1);

$id = 'RD-13.1H';
$dryRun = in_array('--dry-run', $argv ?? [], true);
$root = getcwd() ?: '.';
$targets = [
    'css' => 'catalog/view/stylesheet/boostershop-ds.css',
    'reskin' => 'catalog/view/javascript/checkout-reskin.js',
    'checkout' => 'catalog/view/template/checkout/checkout.twig',
    'captcha' => 'extension/ps_google_recaptcha/catalog/view/template/captcha/ps_google_recaptcha.twig',
];

function rd131h_log(string $message): void { global $id; echo '[' . $id . '] ' . $message . PHP_EOL; }
function rd131h_fail(string $message): void { rd131h_log('ERROR: ' . $message); exit(1); }
function rd131h_read(string $path): string {
    if (!is_file($path) || !is_readable($path)) { rd131h_fail('target missing or unreadable: ' . $path); }
    $data = file_get_contents($path);
    if ($data === false) { rd131h_fail('cannot read: ' . $path); }
    return str_replace("\r\n", "\n", $data);
}
function rd131h_replace_once(string $source, string $anchor, string $replacement, string $label): string {
    $count = substr_count($source, $anchor);
    if ($count !== 1) { rd131h_fail($label . ' anchor count is ' . $count . ', expected 1'); }
    return str_replace($anchor, $replacement, $source);
}
function rd131h_backup(string $root, string $backupRoot, string $relative): string {
    $source = $root . '/' . $relative;
    $destination = $backupRoot . '/' . $relative;
    $directory = dirname($destination);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) { rd131h_fail('cannot create backup directory: ' . $directory); }
    if (!copy($source, $destination)) { rd131h_fail('backup failed: ' . $relative); }
    return $destination;
}
function rd131h_write(string $path, string $data): void {
    if (file_put_contents($path, $data) === false) { rd131h_fail('write failed: ' . $path); }
}

rd131h_log('cwd=' . $root);
rd131h_log('time=' . date(DATE_ATOM));

/* Locate the file that carries the site-wide cat002 stylesheet link */
$oldBuster = 'boostershop-ds.css?v=cat002-5c1-20260630';
$newBuster = 'boostershop-ds.css?v=rd13.1h-20260712';
$headerHits = [];
$scanDirs = [$root . '/catalog/view/template', $root . '/extension'];
foreach ($scanDirs as $dir) {
    if (!is_dir($dir)) { continue; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) { continue; }
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['twig', 'php'], true)) { continue; }
        $content = @file_get_contents($file->getPathname());
        if ($content !== false && strpos($content, $oldBuster) !== false) {
            $headerHits[] = $file->getPathname();
        }
    }
}
if (count($headerHits) !== 1) {
    rd131h_fail('expected exactly 1 file carrying "' . $oldBuster . '", found ' . count($headerHits) . ': ' . implode(', ', $headerHits));
}
$headerPath = $headerHits[0];
rd131h_log('site_css_link_in=' . $headerPath);
$headerData = rd131h_read($headerPath);
if (substr_count($headerData, $oldBuster) !== 1) { rd131h_fail('old buster count in header file is not 1'); }

$paths = [];
$files = [];
foreach ($targets as $key => $relative) {
    $paths[$key] = $root . '/' . $relative;
    $files[$key] = rd131h_read($paths[$key]);
}

/* Guards */
$applied = strpos($files['css'], 'RD-13.1H') !== false
    && strpos($files['reskin'], 'RD-13.1H') !== false
    && strpos($files['checkout'], 'boostershop-ds.css') === false;
if ($applied) { rd131h_log('already_applied=yes'); exit(0); }
if (strpos($files['css'], 'RD-13.1H') !== false || strpos($files['reskin'], 'RD-13.1H') !== false) {
    rd131h_fail('partial marker state detected; restore the newest RD-13.1H backup before retrying');
}
if (strpos($files['css'], 'RD-13.1G') === false) {
    rd131h_fail('RD-13.1G markers not found; RD-13.1H must be applied on top of RD-13.1G');
}

/* ---- 1 · Single stylesheet link ---- */
$headerData = str_replace($oldBuster, $newBuster, $headerData);

$files['checkout'] = rd131h_replace_once(
    $files['checkout'],
    "<link rel=\"stylesheet\" href=\"catalog/view/stylesheet/boostershop-ds.css?v=rd13.1g-20260712\">\n",
    '',
    'duplicate checkout stylesheet link'
);

/* ---- 2 · CSS: kill the col.mb-3 margin (live wrapper is col, not row) ---- */
$files['css'] = rd131h_replace_once(
    $files['css'],
    <<<'CSS'
#checkout-checkout.bs-co .bs-co-moved-captcha .row {
  --bs-gutter-x: 0;
  /* RD-13.1G: Bootstrap .mb-3 carries !important — a plain margin:0 loses. */
  margin: 0 !important;
}
CSS,
    <<<'CSS'
#checkout-checkout.bs-co .bs-co-moved-captcha .row {
  --bs-gutter-x: 0;
  /* RD-13.1G: Bootstrap .mb-3 carries !important — a plain margin:0 loses. */
  margin: 0 !important;
}

/* RD-13.1H: DOM-verified — the live wrapper is col.mb-3 (alternative
   layout), so the .row-scoped kill above never matched it. */
#checkout-checkout.bs-co .bs-co-moved-captcha .mb-3 {
  margin-bottom: 0 !important;
}
#checkout-checkout.bs-co .bs-co-moved-captcha .col {
  padding: 0;
}
CSS,
    'captcha col margin kill'
);

/* ---- 3 · CAPTCHA twig: revert the compact branch (widget fills the card) ---- */
$files['captcha'] = rd131h_replace_once(
    $files['captcha'],
    <<<'TWIG'
				var onloadCallback{{ widget_counter }} = function () {
					// RD-13.1E: compact widget for the narrow mobile checkout —
					// Google's supported sizing instead of CSS transform hacks.
					var recaptchaSize{{ widget_counter }} = document.getElementById('checkout-checkout') &&
						window.matchMedia('(max-width: 420px)').matches ? 'compact' : '{{ badge_size }}';
					recaptcha_widget{{ widget_counter }} = grecaptcha.render('g-recaptcha-{{ widget_counter }}', {
						'sitekey': '{{ site_key }}',
						'theme': '{{ badge_theme }}',
						'size': recaptchaSize{{ widget_counter }}
					});
				};
TWIG,
    <<<'TWIG'
				var onloadCallback{{ widget_counter }} = function () {
					// RD-13.1H: always the normal widget; the checkout reskin
					// scales it to the card width (no compact, no CSS hacks here).
					recaptcha_widget{{ widget_counter }} = grecaptcha.render('g-recaptcha-{{ widget_counter }}', {
						'sitekey': '{{ site_key }}',
						'theme': '{{ badge_theme }}',
						'size': '{{ badge_size }}'
					});
				};
TWIG,
    'captcha compact revert'
);

/* ---- 4 · reskin.js: fit-scaler appended as a standalone IIFE ---- */
$scaler = <<<'JS'


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
JS;
$files['reskin'] .= $scaler . "\n";

/* ---- 5 · reskin cache-buster ---- */
$files['checkout'] = rd131h_replace_once(
    $files['checkout'],
    '<script src="catalog/view/javascript/checkout-reskin.js?v=rd13.1g-20260712"></script>',
    '<script src="catalog/view/javascript/checkout-reskin.js?v=rd13.1h-20260712"></script>',
    'reskin cache-buster'
);

/* ---- Post-replace verification ---- */
$checks = [
    'header new buster' => strpos($headerData, $newBuster) !== false && strpos($headerData, $oldBuster) === false,
    'checkout has no ds link' => strpos($files['checkout'], 'boostershop-ds.css') === false,
    'css mb-3 kill' => strpos($files['css'], '.bs-co-moved-captcha .mb-3') !== false,
    'css marker' => strpos($files['css'], 'RD-13.1H') !== false,
    'captcha compact gone' => strpos($files['captcha'], 'recaptchaSize') === false,
    'captcha normal render' => strpos($files['captcha'], "'size': '{{ badge_size }}'") !== false,
    'reskin scaler' => strpos($files['reskin'], 'fitCaptcha') !== false,
    'reskin buster' => strpos($files['checkout'], 'checkout-reskin.js?v=rd13.1h-20260712') !== false,
];
foreach ($checks as $label => $ok) {
    if (!$ok) { rd131h_fail('post-replace check failed: ' . $label); }
}

if ($dryRun) {
    rd131h_log('dry_run=ok');
    rd131h_log('would_change=' . implode(',', $targets) . ',' . $headerPath);
    exit(0);
}

$backupRoot = $root . '/_patch_backups/RD-13.1H_single_css_link_captcha_fit_20260712_' . date('Ymd_His');
foreach ($targets as $key => $relative) { rd131h_log('backup=' . rd131h_backup($root, $backupRoot, $relative)); }
$headerRelative = ltrim(str_replace($root, '', $headerPath), '/');
rd131h_log('backup=' . rd131h_backup($root, $backupRoot, $headerRelative));

foreach ($targets as $key => $relative) { rd131h_write($paths[$key], $files[$key]); }
rd131h_write($headerPath, $headerData);

rd131h_log('changed=' . implode(',', $targets) . ',' . $headerRelative);
rd131h_log('done=ok');
rd131h_log('qa=hard refresh; view-source: exactly ONE boostershop-ds.css link (v=rd13.1h) on home+checkout; captcha spans the card width, no gray band; phone/email inputs equal PIB width');
if (@unlink(__FILE__)) { rd131h_log('self_delete=ok'); } else { rd131h_log('self_delete=skipped'); }
