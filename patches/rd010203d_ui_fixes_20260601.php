<?php
/**
 * RD-01/02/03(d) — UI polish fixes
 *
 *  1. Category tiles: object-fit contain + wider media panel
 *  2. H1 home: Опція A з хендофу (TCG gold accent + subtitle)
 *  3. Mobile search: remove max-width cap (no logo changes)
 *  4. Breadcrumb: explicit font-size + smaller divider (no !important needed)
 *
 * Upload to ~/public_html and run: php rd010203d_ui_fixes_20260601.php
 */

$patchId    = 'rd010203d_ui_fixes_20260601';
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
    'home' => 'catalog/view/template/common/home.twig',
    'css'  => 'catalog/view/stylesheet/boostershop-ds.css',
];
foreach ($files as $k => $r) {
    if (!is_file(pj($root, $r))) fail('missing required file: ' . $r);
}

$home = rreq(pj($root, $files['home']), 'home.twig');
$css  = rreq(pj($root, $files['css']),  'boostershop-ds.css');

$marker = 'RD-01-02-03D ui-fixes 20260601';
if (strpos($css, $marker) !== false) fail('already applied');

// Передумови
has($home, 'bs-catcard',          'bs-catcard in home (rd010203c required)');
has($css,  'bs-catcard__media',   'bs-catcard__media CSS (rd010203b required)');
has($home, 'bs-home-h1',          'bs-home-h1 in home.twig');
has($css,  'RD-01-02-03C',        'rd010203c marker (apply c before d)');

/* ================================================================
   FIX 1 — Category tile logo: full logo visible, no crop
   Діагноз: flex: 0 0 168px + object-fit:cover обрізає горизонтальні
   логотипи Pokémon та One Piece.
   Рішення: object-fit:contain + padding + ширша панель (220px).
   ================================================================ */
$cssAppend = <<<'CSS'

/* ==== RD-01-02-03D ui-fixes 20260601 ==== */

/* Fix 1 — Category tile logo: full logo, no crop */
.bs-catcard__media {
  flex: 0 0 220px !important;
  width: 220px !important;
  height: 168px !important;
  background: var(--bs-bg) !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  padding: 16px !important;
}
.bs-catcard__media img {
  width: 100% !important;
  height: 100% !important;
  object-fit: contain !important;
  object-position: 50% 50% !important;
}
@media (max-width: 767.98px) {
  .bs-catcard__media {
    flex: 0 0 140px !important;
    width: 140px !important;
    height: 132px !important;
    padding: 12px !important;
  }
}

/* Fix 3 — Mobile search: fill header row, DO NOT touch logo size */
@media (max-width: 768px) {
  .bs-msearch {
    flex: 1 1 0px !important;
    min-width: 0 !important;
    max-width: none !important;
  }
  .bs-msearch__field,
  form.bs-search.bs-msearch__field {
    flex: 1 1 0px !important;
    width: 100% !important;
    min-width: 0 !important;
  }
  #cart.bs-header__cart,
  .bs-header__cart { flex: 0 0 auto !important; }
}

/* Fix 4 — Breadcrumb separator: smaller and subtler
   Причина: --bs-breadcrumb-font-size не визначено в Bootstrap → успадковує від батька.
   Роздільник "/" за замовчуванням виглядає крупним на деяких шрифтах.
   Рішення: явно задаємо font-size та менший символ — без !important,
   бо інших overrides немає (перевірено в boostershop-ds.css і bootstrap.css). */
.breadcrumb {
  font-size: 0.8125rem;
  --bs-breadcrumb-divider: "·";
  --bs-breadcrumb-item-padding-x: 0.4rem;
}
.breadcrumb-item + .breadcrumb-item::before {
  opacity: 0.45;
}

/* Fix 2 — H1 home: Опція A (додається CSS, markup нижче) */
.bs-cat-heading { margin: 0 0 24px; }
.bs-cat-heading__title {
  margin: 0;
  font-family: 'Manrope', system-ui, sans-serif;
  font-size: 32px;
  font-weight: 800;
  letter-spacing: -0.025em;
  line-height: 1.1;
  color: var(--bs-ink);
  text-wrap: balance;
}
.bs-cat-heading__accent { color: var(--bs-pokemon); }
.bs-cat-heading__sub {
  margin: 8px 0 0;
  font-size: 17px;
  font-weight: 600;
  letter-spacing: -0.005em;
  line-height: 1.3;
  color: var(--bs-ink-3);
}
.bs-cat-heading__sep {
  color: var(--bs-ink-4);
  font-weight: 500;
  margin: 0 0.35em;
}
@media (max-width: 640px) {
  .bs-cat-heading        { margin-bottom: 18px; }
  .bs-cat-heading__title { font-size: 24px; letter-spacing: -0.02em; }
  .bs-cat-heading__sub   { font-size: 15px; }
}
CSS;

$css = rtrim($css) . "\n" . $cssAppend . "\n";
has($css, $marker, 'CSS marker present after append');
has($css, 'object-fit: contain', 'contain rule');
has($css, 'max-width: none', 'max-width none rule');
has($css, 'bs-cat-heading', 'heading CSS');
has($css, '--bs-breadcrumb-divider', 'breadcrumb divider rule');

/* ================================================================
   FIX 2 — H1 home: replace with Опція A markup
   ================================================================ */
$h1Old = '<h1 class="bs-home-h1" data-seo-crit-001="home-h1">Оригінальні бустери та бокси Pokemon, One Piece та інших TCG</h1>';
$h1New = <<<'TWIG'
<header class="bs-cat-heading">
  <h1 class="bs-cat-heading__title">
    Оригінальні бустери та бокси <span class="bs-cat-heading__accent">TCG</span>
  </h1>
  <p class="bs-cat-heading__sub">
    Pokémon<span class="bs-cat-heading__sep">·</span>One&nbsp;Piece<span class="bs-cat-heading__sep">·</span>та інші ігри
  </p>
</header>
TWIG;

if (strpos($home, $h1Old) === false) fail('H1 old markup not found verbatim — check whitespace or previous patch');
$home = str_replace($h1Old, $h1New, $home);

// Прибираємо застарілий inline стиль .bs-home-h1 з home.twig (більше не потрібен)
$home = preg_replace('~\s*\.bs-home-h1\s*\{[^}]+\}~', '', $home);
$home = preg_replace('~\s*@media[^{]+\{[^{}]*\.bs-home-h1\s*\{[^}]+\}\s*\}~', '', $home);

has($home, 'bs-cat-heading__accent', 'new H1 markup present');
hasnt($home, '<h1 class="bs-home-h1"', 'old H1 gone');
out('fix2=h1_redesigned');

/* ---- backup + write ---- */
if (!is_dir($backupRoot)) @mkdir($backupRoot, 0755, true);
bkup($root, $files['home'], $backupRoot);
bkup($root, $files['css'],  $backupRoot);

wreq(pj($root, $files['home']), $home);
wreq(pj($root, $files['css']),  $css);

/* ---- post-write verify ---- */
$hv = rreq(pj($root, $files['home']), 'home post');
$cv = rreq(pj($root, $files['css']),  'css post');

has($hv, 'bs-cat-heading__accent', 'H1 accent post');
has($cv, $marker,                   'marker post');
has($cv, 'object-fit: contain',     'contain post');
has($cv, 'max-width: none',         'search width post');
has($cv, '--bs-breadcrumb-divider', 'breadcrumb post');

foreach ($files as $rel) out('changed=' . $rel);
out('done=ok');
@unlink(__FILE__);
exit(0);
