<?php
/**
 * RD-13.1F — receiver grid + captcha visual alignment (CSS only)
 *
 * Owner correction after RD-13.1E:
 *   1. Direction of width parity was wrong in RD-13.1E: phone/e-mail must be
 *      brought TO the PIB field box (12px side inset), not PIB stretched to
 *      the flush variant. All receiver-grid children now get the PIB box.
 *      Row gap raised 4px -> 16px to restore the old vertical rhythm.
 *   2. CAPTCHA card: kill the dead 16px bottom margin inside the fieldset
 *      and center the widget horizontally on mobile (<=900px), where the
 *      compact widget always fits. Desktop stays left-aligned (a 304px
 *      widget in a ~300px card must not be centered under overflow:hidden).
 *
 * Requires RD-13.1E applied. No JS/twig logic change; cache-buster only.
 *
 * Usage (site root): php RD-13.1F_receiver_captcha_visual_align_20260712.php [--dry-run]
 */
declare(strict_types=1);

$id = 'RD-13.1F';
$dryRun = in_array('--dry-run', $argv ?? [], true);
$root = getcwd() ?: '.';
$targets = [
    'css' => 'catalog/view/stylesheet/boostershop-ds.css',
    'checkout' => 'catalog/view/template/checkout/checkout.twig',
];

function rd131f_log(string $message): void { global $id; echo '[' . $id . '] ' . $message . PHP_EOL; }
function rd131f_fail(string $message): void { rd131f_log('ERROR: ' . $message); exit(1); }
function rd131f_read(string $path): string {
    if (!is_file($path) || !is_readable($path)) { rd131f_fail('target missing or unreadable: ' . $path); }
    $data = file_get_contents($path);
    if ($data === false) { rd131f_fail('cannot read: ' . $path); }
    return str_replace("\r\n", "\n", $data);
}
function rd131f_replace_once(string $source, string $anchor, string $replacement, string $label): string {
    $count = substr_count($source, $anchor);
    if ($count !== 1) { rd131f_fail($label . ' anchor count is ' . $count . ', expected 1'); }
    return str_replace($anchor, $replacement, $source);
}
function rd131f_backup(string $root, string $backupRoot, string $relative): string {
    $source = $root . '/' . $relative;
    $destination = $backupRoot . '/' . $relative;
    $directory = dirname($destination);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) { rd131f_fail('cannot create backup directory: ' . $directory); }
    if (!copy($source, $destination)) { rd131f_fail('backup failed: ' . $relative); }
    return $destination;
}
function rd131f_write(string $path, string $data): void {
    if (file_put_contents($path, $data) === false) { rd131f_fail('write failed: ' . $path); }
}

rd131f_log('cwd=' . $root);
rd131f_log('time=' . date(DATE_ATOM));

$paths = [];
$files = [];
foreach ($targets as $key => $relative) {
    $paths[$key] = $root . '/' . $relative;
    $files[$key] = rd131f_read($paths[$key]);
}

/* Guards */
$cssApplied = strpos($files['css'], 'RD-13.1F') !== false;
$checkoutApplied = strpos($files['checkout'], 'v=rd13.1f-20260712') !== false;
if ($cssApplied && $checkoutApplied) { rd131f_log('already_applied=yes'); exit(0); }
if ($cssApplied || $checkoutApplied) {
    rd131f_fail('partial marker state detected; restore the newest RD-13.1F backup before retrying');
}
if (strpos($files['css'], 'RD-13.1E') === false) {
    rd131f_fail('RD-13.1E markers not found; RD-13.1F must be applied on top of RD-13.1E');
}

/* ---- 1 · Receiver grid: PIB box for every child + vertical rhythm ---- */
$files['css'] = rd131f_replace_once(
    $files['css'],
    <<<'CSS'
#checkout-checkout.bs-co #form-register > fieldset:first-of-type > .row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 4px 6px;
}
CSS,
    <<<'CSS'
#checkout-checkout.bs-co #form-register > fieldset:first-of-type > .row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 16px 6px; /* RD-13.1F: rhythm previously carried by PIB mb-3 */
}
CSS,
    'receiver grid gap'
);

$files['css'] = rd131f_replace_once(
    $files['css'],
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
    <<<'CSS'
/* RD-13.1F (owner direction): every receiver-grid child gets the PIB box —
   12px side inset — so phone/e-mail match the name fields, not vice versa. */
#checkout-checkout.bs-co #form-register > fieldset:first-of-type > .row > * {
  width: auto;
  margin: 0 !important;
  padding: 0 12px;
}
CSS,
    'receiver grid child box'
);

/* ---- 2 · Captcha card: no dead space, centered widget on mobile ---- */
$files['css'] = rd131f_replace_once(
    $files['css'],
    <<<'CSS'
#checkout-checkout.bs-co .bs-co-moved-captcha .row {
  --bs-gutter-x: 0;
  margin-left: 0;
  margin-right: 0;
}
CSS,
    <<<'CSS'
#checkout-checkout.bs-co .bs-co-moved-captcha .row {
  --bs-gutter-x: 0;
  margin: 0; /* RD-13.1F: mb-3 bottom margin left a dead band in the card */
}
CSS,
    'captcha row margin'
);

$files['css'] = rd131f_replace_once(
    $files['css'],
    <<<'CSS'
  /* 8 · RD-13.1E: no transform — narrow screens get Google's compact widget
     at render time; the overflow guard stays as a safety net only. */
  #checkout-checkout.bs-co .bs-co-moved-captcha { overflow: hidden; }
CSS,
    <<<'CSS'
  /* 8 · RD-13.1F: compact widget centered in the card; overflow guard stays. */
  #checkout-checkout.bs-co .bs-co-moved-captcha { overflow: hidden; }
  #checkout-checkout.bs-co .bs-co-moved-captcha [id^="g-recaptcha-"] {
    display: flex;
    justify-content: center;
  }
CSS,
    'mobile captcha centering'
);

/* ---- 3 · Cache-buster ---- */
$files['checkout'] = rd131f_replace_once(
    $files['checkout'],
    '<link rel="stylesheet" href="catalog/view/stylesheet/boostershop-ds.css?v=rd13.1e-20260712">',
    '<link rel="stylesheet" href="catalog/view/stylesheet/boostershop-ds.css?v=rd13.1f-20260712">',
    'CSS cache-buster'
);

/* ---- Post-replace verification ---- */
$checks = [
    'grid gap 16px' => strpos($files['css'], 'gap: 16px 6px;') !== false,
    'child inset 12px' => strpos($files['css'], "  margin: 0 !important;\n  padding: 0 12px;\n}") !== false,
    'no flush child rule' => strpos($files['css'], "> .row > * {\n  width: auto;\n  margin: 0 !important;\n  padding: 0;\n}") === false,
    'captcha row margin 0' => strpos($files['css'], '--bs-gutter-x: 0;
  margin: 0;') !== false,
    'captcha centering' => strpos($files['css'], 'justify-content: center') !== false,
    'css marker' => strpos($files['css'], 'RD-13.1F') !== false,
    'checkout cache-buster' => strpos($files['checkout'], 'v=rd13.1f-20260712') !== false,
    'old cache-buster gone' => strpos($files['checkout'], 'v=rd13.1e-20260712') === false,
];
foreach ($checks as $label => $ok) {
    if (!$ok) { rd131f_fail('post-replace check failed: ' . $label); }
}

if ($dryRun) {
    rd131f_log('dry_run=ok');
    rd131f_log('would_change=' . implode(',', $targets));
    exit(0);
}

$backupRoot = $root . '/_patch_backups/RD-13.1F_receiver_captcha_visual_align_20260712_' . date('Ymd_His');
foreach ($targets as $key => $relative) { rd131f_log('backup=' . rd131f_backup($root, $backupRoot, $relative)); }
foreach ($targets as $key => $relative) { rd131f_write($paths[$key], $files[$key]); }

rd131f_log('changed=' . implode(',', $targets));
rd131f_log('done=ok');
rd131f_log('qa=guest checkout: all 5 receiver inputs share the PIB width/inset (desktop 2-col, mobile 1-col); 16px row rhythm; mobile captcha compact, centered, no dead band');
if (@unlink(__FILE__)) { rd131f_log('self_delete=ok'); } else { rd131f_log('self_delete=skipped'); }
