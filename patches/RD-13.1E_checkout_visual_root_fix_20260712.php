<?php
/**
 * RD-13.1E — checkout visual root fix (CSS + 2 twig, presentation only)
 *
 * Scope (owner request 2026-07-12):
 *   1. Receiver grid width parity: phone/e-mail inputs rendered 24px wider
 *      than the NP name fields (desktop AND mobile). Root cause: the grid
 *      normalization rule targeted `> .col` only, while the NP name blocks
 *      are adopted into the grid as `.mb-3` wrappers (no `.col`) and kept
 *      the Bootstrap `.row > *` gutter padding (0 12px). Fix: normalize ALL
 *      grid children (`> *`).
 *   2. Mobile guest CAPTCHA mess: round-6 `transform: none !important` on
 *      the widget host cancelled round-7's container scale, so only the
 *      IFRAME got scale(0.9) while the fixed 304px host box overflowed the
 *      card to the right. Root fix: remove every transform hack, zero the
 *      Bootstrap gutters inside the captcha fieldset, and render Google's
 *      supported compact widget on narrow checkout viewports.
 *
 * No DB, route, payment, order-creation or CAPTCHA-validation change.
 * Supersedes the visual part of RD-13.1D — do NOT deploy RD-13.1D.
 * Targets the state captured in booster-rd13-visual-current.tar.gz.
 *
 * Usage (site root): php RD-13.1E_checkout_visual_root_fix_20260712.php [--dry-run]
 */
declare(strict_types=1);

$id = 'RD-13.1E';
$dryRun = in_array('--dry-run', $argv ?? [], true);
$root = getcwd() ?: '.';
$targets = [
    'css' => 'catalog/view/stylesheet/boostershop-ds.css',
    'checkout' => 'catalog/view/template/checkout/checkout.twig',
    'captcha' => 'extension/ps_google_recaptcha/catalog/view/template/captcha/ps_google_recaptcha.twig',
];

function rd131e_log(string $message): void { global $id; echo '[' . $id . '] ' . $message . PHP_EOL; }
function rd131e_fail(string $message): void { rd131e_log('ERROR: ' . $message); exit(1); }
function rd131e_read(string $path): string {
    if (!is_file($path) || !is_readable($path)) { rd131e_fail('target missing or unreadable: ' . $path); }
    $data = file_get_contents($path);
    if ($data === false) { rd131e_fail('cannot read: ' . $path); }
    return str_replace("\r\n", "\n", $data);
}
function rd131e_replace_once(string $source, string $anchor, string $replacement, string $label): string {
    $count = substr_count($source, $anchor);
    if ($count !== 1) { rd131e_fail($label . ' anchor count is ' . $count . ', expected 1'); }
    return str_replace($anchor, $replacement, $source);
}
function rd131e_backup(string $root, string $backupRoot, string $relative): string {
    $source = $root . '/' . $relative;
    $destination = $backupRoot . '/' . $relative;
    $directory = dirname($destination);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) { rd131e_fail('cannot create backup directory: ' . $directory); }
    if (!copy($source, $destination)) { rd131e_fail('backup failed: ' . $relative); }
    return $destination;
}
function rd131e_write(string $path, string $data): void {
    if (file_put_contents($path, $data) === false) { rd131e_fail('write failed: ' . $path); }
}

rd131e_log('cwd=' . $root);
rd131e_log('time=' . date(DATE_ATOM));

$paths = [];
$files = [];
foreach ($targets as $key => $relative) {
    $paths[$key] = $root . '/' . $relative;
    $files[$key] = rd131e_read($paths[$key]);
}

/* Idempotency + base-state guards */
$cssApplied = strpos($files['css'], 'RD-13.1E') !== false;
$checkoutApplied = strpos($files['checkout'], 'v=rd13.1e-20260712') !== false;
$captchaApplied = strpos($files['captcha'], 'RD-13.1E') !== false;
if ($cssApplied && $checkoutApplied && $captchaApplied) { rd131e_log('already_applied=yes'); exit(0); }
if ($cssApplied || $checkoutApplied || $captchaApplied) {
    rd131e_fail('partial marker state detected; restore the newest RD-13.1E backup before retrying');
}
if (strpos($files['css'], 'RD-13.1D visual root fixes') !== false) {
    rd131e_fail('RD-13.1D is applied on this tree; RD-13.1E targets the pre-13.1D state — restore first');
}

/* ---- 1 · CSS: receiver grid width parity ---- */
$files['css'] = rd131e_replace_once(
    $files['css'],
    <<<'CSS'
#checkout-checkout.bs-co #form-register > fieldset:first-of-type > .row > .col {
  width: auto;
  margin: 0 !important;
  padding: 0;
}
CSS,
    <<<'CSS'
/* RD-13.1E: normalize ALL receiver-grid children, not only `.col` — the NP
   name blocks are adopted `.mb-3` wrappers that kept Bootstrap gutters and
   rendered their inputs 24px narrower than phone/e-mail. */
#checkout-checkout.bs-co #form-register > fieldset:first-of-type > .row > * {
  width: auto;
  margin: 0 !important;
  padding: 0;
}
CSS,
    'receiver grid normalization'
);

$files['css'] = rd131e_replace_once(
    $files['css'],
    '#checkout-checkout.bs-co #form-register > fieldset:first-of-type > .row > .col:not([class*="bs-co-recipient-field"]) { order: 10; }',
    '#checkout-checkout.bs-co #form-register > fieldset:first-of-type > .row > *:not([class*="bs-co-recipient-field"]) { order: 10; }',
    'receiver grid untagged order'
);

/* ---- 2 · CSS: captcha — remove transform hacks, fix box math ---- */
$files['css'] = rd131e_replace_once(
    $files['css'],
    <<<'CSS'
  #checkout-checkout.bs-co #bs-co-tail-bottom .table-responsive,
  #checkout-checkout.bs-co #bs-co-tail-bottom img,
  #checkout-checkout.bs-co #bs-co-tail-bottom iframe {
    max-width: 100%;
  }
CSS,
    <<<'CSS'
  /* RD-13.1E: iframe dropped from this list — max-width squashes the
     fixed-size reCAPTCHA frame instead of resizing it. */
  #checkout-checkout.bs-co #bs-co-tail-bottom .table-responsive,
  #checkout-checkout.bs-co #bs-co-tail-bottom img {
    max-width: 100%;
  }
CSS,
    'tail-bottom iframe max-width'
);

$files['css'] = rd131e_replace_once(
    $files['css'],
    <<<'CSS'
/* Captcha: keep the widget at its native size, centered in its card */
#checkout-checkout.bs-co .bs-co-moved-captcha [id^="g-recaptcha-"],
#checkout-checkout.bs-co .bs-co-moved-captcha .g-recaptcha {
  min-height: 78px;
  transform: none !important;
}
CSS,
    <<<'CSS'
/* RD-13.1E: the CAPTCHA is never CSS-transformed; widget size is chosen at
   grecaptcha.render time (normal / compact). */
#checkout-checkout.bs-co .bs-co-moved-captcha [id^="g-recaptcha-"],
#checkout-checkout.bs-co .bs-co-moved-captcha .g-recaptcha {
  min-height: 78px;
}
CSS,
    'captcha transform-none rule'
);

$files['css'] = rd131e_replace_once(
    $files['css'],
    '#checkout-checkout.bs-co .bs-co-moved-captcha .col-sm-10 { width: 100%; }',
    <<<'CSS'
#checkout-checkout.bs-co .bs-co-moved-captcha .row {
  --bs-gutter-x: 0;
  margin-left: 0;
  margin-right: 0;
}
#checkout-checkout.bs-co .bs-co-moved-captcha .col-sm-10 {
  width: 100%;
  padding-left: 0;
  padding-right: 0;
}
CSS,
    'captcha gutter zeroing'
);

$files['css'] = rd131e_replace_once(
    $files['css'],
    <<<'CSS'
  /* 8 · the real reCAPTCHA widget is a fixed ~304px — scale it to fit the card */
  #checkout-checkout.bs-co .bs-co-moved-captcha { overflow: hidden; }
  #checkout-checkout.bs-co .bs-co-moved-captcha .g-recaptcha,
  #checkout-checkout.bs-co .bs-co-moved-captcha iframe[src*="recaptcha"] {
    transform: scale(0.9);
    transform-origin: left top;
  }
CSS,
    <<<'CSS'
  /* 8 · RD-13.1E: no transform — narrow screens get Google's compact widget
     at render time; the overflow guard stays as a safety net only. */
  #checkout-checkout.bs-co .bs-co-moved-captcha { overflow: hidden; }
CSS,
    'mobile captcha scale hack'
);

/* ---- 3 · reCAPTCHA twig: compact widget on narrow checkout viewports ---- */
$files['captcha'] = rd131e_replace_once(
    $files['captcha'],
    <<<'TWIG'
				var onloadCallback{{ widget_counter }} = function () {
					recaptcha_widget{{ widget_counter }} = grecaptcha.render('g-recaptcha-{{ widget_counter }}', {
						'sitekey': '{{ site_key }}',
						'theme': '{{ badge_theme }}',
						'size': '{{ badge_size }}'
					});
				};
TWIG,
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
    'captcha compact render'
);

/* ---- 4 · Cache-buster (CSS only; checkout-reskin.js is untouched) ---- */
$files['checkout'] = rd131e_replace_once(
    $files['checkout'],
    '<link rel="stylesheet" href="catalog/view/stylesheet/boostershop-ds.css?v=rd13r7-manual-20260711c">',
    '<link rel="stylesheet" href="catalog/view/stylesheet/boostershop-ds.css?v=rd13.1e-20260712">',
    'CSS cache-buster'
);

/* ---- In-memory post-replace verification ---- */
$checks = [
    'grid > * rules x3 (2 new + pre-existing min-width rule)' => substr_count($files['css'], '> .row > *') === 3,
    'no scale hack' => strpos($files['css'], 'transform: scale(0.9)') === false,
    'transform-none only outside captcha' => substr_count($files['css'], 'transform: none !important') === 1,
    'no iframe max-width' => strpos($files['css'], '#bs-co-tail-bottom iframe') === false,
    'gutter zero present' => strpos($files['css'], '--bs-gutter-x: 0') !== false,
    'css marker' => strpos($files['css'], 'RD-13.1E') !== false,
    'checkout cache-buster' => strpos($files['checkout'], 'v=rd13.1e-20260712') !== false,
    'old cache-buster gone' => strpos($files['checkout'], 'rd13r7-manual-20260711c') === false,
    'captcha compact render' => strpos($files['captcha'], 'recaptchaSize{{ widget_counter }}') !== false,
];
foreach ($checks as $label => $ok) {
    if (!$ok) { rd131e_fail('post-replace check failed: ' . $label); }
}

if ($dryRun) {
    rd131e_log('dry_run=ok');
    rd131e_log('would_change=' . implode(',', $targets));
    exit(0);
}

$backupRoot = $root . '/_patch_backups/RD-13.1E_checkout_visual_root_fix_20260712_' . date('Ymd_His');
foreach ($targets as $key => $relative) { rd131e_log('backup=' . rd131e_backup($root, $backupRoot, $relative)); }
foreach ($targets as $key => $relative) { rd131e_write($paths[$key], $files[$key]); }

rd131e_log('changed=' . implode(',', $targets));
rd131e_log('done=ok');
rd131e_log('qa=guest checkout: PIB vs phone/email equal input width (desktop+mobile); mobile <=420px compact CAPTCHA, no right overflow; >420px normal CAPTCHA fits card');
if (@unlink(__FILE__)) { rd131e_log('self_delete=ok'); } else {