<?php
/**
 * RD-01/02/03(e) — polish & fixes
 *
 *  1. header.twig — cache-bust boostershop-ds.css (breadcrumb fix now loads)
 *  2. home.twig   — One Piece image path fix
 *  3. home.twig   — catcard media: ширший контейнер, більший правий padding
 *  4. css         — mobile: ховати .bs-catcard__desc на плитках
 *  5. js          — openSearch(): re-focus input після position:fixed layout shift
 *
 * Upload to ~/public_html and run: php rd010203e_polish_20260601.php
 */

$patchId    = 'rd010203e_polish_20260601';
$root       = getcwd();
$stamp      = date('Ymd_His');
$backupRoot = $root . '/_patch_backups/' . $patchId . '_' . $stamp;

function out($m)   { echo $m . PHP_EOL; }
function fail($m)  { fwrite(STDERR, 'ERROR: ' . $m . PHP_EOL); exit(1); }
function pj($r,$f) { return rtrim($r,'/\\') . '/' . ltrim($f,'/\\'); }

function rreq($p, $l) {
    $d = @file_get_contents($p);
    if ($d === false) fail('cannot read ' . $l . ': ' . $p);
    return $d;
}
function wreq($p, $d) {
    $dir = dirname($p);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (@file_put_contents($p, $d, LOCK_EX) === false) fail('cannot write ' . $p);
}
function bkup($root, $rel, $bk) {
    $s = pj($root, $rel);
    if (!is_file($s)) { out('backup=skip:' . $rel); return; }
    $t = pj($bk, $rel);
    @mkdir(dirname($t), 0755, true);
    if (!@copy($s, $t)) fail('backup failed: ' . $rel);
    out('backup=' . $t);
}
function has($h, $n, $l)   { if (strpos($h, $n) === false) fail('pre-check: missing — ' . $l); }
function hasnt($h, $n, $l) { if (strpos($h, $n) !== false) fail('pre-check: unexpected — ' . $l); }

out('patch=' . $patchId);
out('cwd=' . $root);
out('time=' . date('Y-m-d H:i:s'));

$files = [
    'header' => 'catalog/view/template/common/header.twig',
    'home'   => 'catalog/view/template/common/home.twig',
    'css'    => 'catalog/view/stylesheet/boostershop-ds.css',
    'js'     => 'catalog/view/javascript/patch-mobile-search-menu-redesign.js',
];
foreach ($files as $k => $r) {
    if (!is_file(pj($root, $r))) fail('missing required file: ' . $r);
}

$header = rreq(pj($root, $files['header']), 'header.twig');
$home   = rreq(pj($root, $files['home']),   'home.twig');
$css    = rreq(pj($root, $files['css']),    'boostershop-ds.css');
$js     = rreq(pj($root, $files['js']),     'js');

$marker = 'RD-01-02-03E polish 20260601';
if (strpos($css, $marker) !== false) fail('already applied');

// Передумови
has($home,   'bs-catcard',          'bs-catcard in home');
has($home,   'One%20PieceC.png',    'old One Piece image path');
has($js,     'openSearch',          'openSearch in js');
has($header, 'boostershop-ds.css',  'css link in header');

/* ================================================================
   FIX 1 — Cache-bust boostershop-ds.css у header.twig
   Причина: breadcrumb CSS з rd010203d не підтягується браузером
   бо ?v= рядок не змінився. Замінюємо версійний тег.
   ================================================================ */
$header = preg_replace(
    '~boostershop-ds\.css\?v=[^"\']+~',
    'boostershop-ds.css?v=rd010203e-20260601',
    $header,
    -1,
    $count
);
if ($count < 1) fail('boostershop-ds.css versioned link not found in header.twig');
out('fix1=cache_bust_updated count=' . $count);

/* ================================================================
   FIX 2 — One Piece image path
   ================================================================ */
$home = str_replace(
    'image/catalog/One%20Piece/One%20PieceC.png',
    'image/catalog/One%20Piece/One%20Piece-Photoroom.png',
    $home
);
has($home, 'One%20Piece-Photoroom.png', 'new One Piece path after replace');
out('fix2=one_piece_image_path_updated');

/* ================================================================
   FIX 3+4 — Catcard: ширший media, більший правий padding,
             ховати опис на мобілі
   ================================================================ */
$cssAppend = <<<'CSS'

/* ==== RD-01-02-03E polish 20260601 ==== */

/* Fix 3 — catcard media: ширший контейнер, правий gap */
.bs-catcard__media {
  flex: 0 0 240px !important;
  width: 240px !important;
  padding: 14px 20px 14px 14px !important; /* +6px right = більше повітря зліва від тексту */
}

/* Fix 4 — mobile: ховаємо опис на плитках категорій */
@media (max-width: 767.98px) {
  .bs-catcard__desc { display: none !important; }
  .bs-catcard__media {
    flex: 0 0 130px !important;
    width: 130px !important;
    padding: 10px 14px 10px 10px !important;
  }
}
CSS;

$css = rtrim($css) . "\n" . $cssAppend . "\n";
has($css, $marker, 'CSS marker present');
out('fix3_4=catcard_media_and_mobile_desc');

/* ================================================================
   FIX 5 — JS: re-focus input після position:fixed layout shift
   Причина: коли .is-open додається → wrapper йде в position:fixed
   → браузер тригерить blur на input → input втрачає фокус.
   Рішення: setTimeout re-focus після layout change.
   ================================================================ */
$jsOld = <<<'JS'
  function openSearch() {
    wrap.classList.add('is-open');
    if (backBtn) backBtn.hidden = false;
    if (bodyScrim) bodyScrim.hidden = false;
    // Фіксуємо висоту шапки для позиціонування dropdown
    document.documentElement.style.setProperty('--bs-header-h', getHeaderH() + 'px');
  }
JS;

$jsNew = <<<'JS'
  function openSearch() {
    wrap.classList.add('is-open');
    if (backBtn) backBtn.hidden = false;
    if (bodyScrim) bodyScrim.hidden = false;
    // Фіксуємо висоту шапки для позиціонування dropdown
    document.documentElement.style.setProperty('--bs-header-h', getHeaderH() + 'px');
    // Re-focus після position:fixed layout shift (браузер може blur тригернути)
    setTimeout(function () { if (psInput) psInput.focus(); }, 30);
  }
JS;

if (strpos($js, $jsOld) === false) fail('openSearch() function body not found verbatim in js — check whitespace');
$js = str_replace($jsOld, $jsNew, $js);
has($js, 'Re-focus після position:fixed', 'refocus comment present');
out('fix5=search_refocus_added');

/* ---- backup + write ---- */
if (!is_dir($backupRoot)) @mkdir($backupRoot, 0755, true);
bkup($root, $files['header'], $backupRoot);
bkup($root, $files['home'],   $backupRoot);
bkup($root, $files['css'],    $backupRoot);
bkup($root, $files['js'],     $backupRoot);

wreq(pj($root, $files['header']), $header);
wreq(pj($root, $files['home']),   $home);
wreq(pj($root, $files['css']),    $css);
wreq(pj($root, $files['js']),     $js);

/* ---- post-write verify ---- */
$hv = rreq(pj($root, $files['header']), 'header post');
$ov = rreq(pj($root, $files['home']),   'home post');
$cv = rreq(pj($root, $files['css']),    'css post');
$jv = rreq(pj($root, $files['js']),     'js post');

has($hv, 'rd010203e-20260601',           'cache bust header post');
has($ov, 'One%20Piece-Photoroom.png',    'image path post');
has($cv, $marker,                         'CSS marker post');
has($jv, 'Re-focus після position:fixed', 'refocus js post');

foreach ($files as $rel) out('changed=' . $rel);
out('done=ok');
@unlink(__FILE__);
exit(0);
