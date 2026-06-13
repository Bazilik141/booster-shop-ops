<?php
/**
 * RD-01/02/03(c) — Post-deploy fixes for Booster Shop
 *
 * Fixes 7 issues found after rd010203b:
 *   1. Language warning — remove &language={{ lang }} from burger menu links
 *   2. Category tiles — update home.twig to use bs-catcard (full-bleed logo)
 *   3. H1 home — improve visual hierarchy
 *   4. Breadcrumb separators — CSS fix
 *   5. Акції seo-block — move below products, above FAQ
 *   6. Footer tagline text — update copy
 *   7. Mobile search width — CSS flex fix
 *
 * Upload to ~/public_html and run: php rd010203c_fixes_20260531.php
 */

$patchId  = 'rd010203c_fixes_20260531';
$root     = getcwd();
$stamp    = date('Ymd_His');
$backupRoot = $root . '/_patch_backups/' . $patchId . '_' . $stamp;

function out($msg)    { echo $msg . PHP_EOL; }
function fail($msg)   { fwrite(STDERR, 'ERROR: ' . $msg . PHP_EOL); exit(1); }
function pjoin($r,$f) { return rtrim($r,'/\\').'/'.ltrim($f,'/\\'); }

function read_req($path, $label) {
    $d = @file_get_contents($path);
    if ($d === false) fail('cannot read '.$label.': '.$path);
    return $d;
}
function write_req($path, $data, $label) {
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (@file_put_contents($path, $data, LOCK_EX) === false) fail('cannot write '.$label.': '.$path);
}
function backup($root, $rel, $bkRoot) {
    $src = pjoin($root, $rel);
    if (!is_file($src)) { out('backup=skip:'.$rel); return; }
    $dst = pjoin($bkRoot, $rel);
    $dir = dirname($dst);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (!@copy($src,$dst)) fail('cannot backup '.$rel);
    out('backup='.$dst);
}
function assert_contains($content, $needle, $label) {
    if (strpos($content, $needle) === false) fail('pre-check failed — expected string not found: '.$label);
}
function assert_not_contains($content, $needle, $label) {
    if (strpos($content, $needle) !== false) fail('pre-check failed — unexpected string present: '.$label);
}

out('patch='.$patchId);
out('cwd='.$root);
out('time='.date('Y-m-d H:i:s'));

$files = [
    'header'  => 'catalog/view/template/common/header.twig',
    'home'    => 'catalog/view/template/common/home.twig',
    'special' => 'catalog/view/template/product/special.twig',
    'footer'  => 'catalog/view/template/common/footer.twig',
    'css'     => 'catalog/view/stylesheet/boostershop-ds.css',
];

foreach ($files as $key => $rel) {
    if (!is_file(pjoin($root, $rel))) fail('missing required file: '.$rel);
}

$header  = read_req(pjoin($root,$files['header']),  'header.twig');
$home    = read_req(pjoin($root,$files['home']),    'home.twig');
$special = read_req(pjoin($root,$files['special']), 'special.twig');
$footer  = read_req(pjoin($root,$files['footer']),  'footer.twig');
$css     = read_req(pjoin($root,$files['css']),     'boostershop-ds.css');

$cssMarker = 'RD-01-02-03C fixes 20260531';
if (strpos($css, $cssMarker) !== false) fail('CSS marker already present — patch already applied');

/* ---- pre-checks ---- */
assert_contains($header, 'id="bs-menu"',            'bs-menu present (rd010203b required)');
assert_contains($header, '&language={{ lang }}',     'language param present in menu links');
assert_contains($home,   'bs-home-tile',             'bs-home-tile in home.twig');
assert_contains($home,   'bs-home-h1',               'bs-home-h1 in home.twig');
assert_contains($special,'bs-special-seo',           'bs-special-seo in special.twig');
assert_contains($footer, 'Оригінальні бустери, бокси та набори', 'old footer text present');

/* ================================================================
   FIX 1 — Language warning: strip &language={{ lang }} from
   burger menu hrefs (OpenCart uses session lang without it).
   The search form hidden input is kept intentionally — it does
   NOT cause the warning (form GET goes through a different path).
   ================================================================ */
// Only strip from href= attributes inside .bs-menu__subs and .bs-menu__cat-row and .bs-menu__info
// Safe: replace &language={{ lang }} only in href= strings
$header = str_replace(
    '&language={{ lang }}&',
    '&',
    $header
);
// trailing case (last param): &language={{ lang }}"
$header = preg_replace('/&language=\{\{\s*lang\s*\}\}"/', '"', $header);
assert_not_contains($header, '&language={{ lang }}', 'language param removed from menu links');
out('fix1=language_warning_removed');

/* ================================================================
   FIX 2 — Category tiles: replace bs-home-tile markup in home.twig
   with bs-catcard (full-bleed <img> panel, matching design file).
   ================================================================ */
$homeTilesOld = <<<'TWIG'
      <section class="bs-home-tiles" aria-label="Категорії товарів">
        <a class="bs-home-tile" href="/catalog/Pokemon">
          <div class="bs-home-tile__strip"></div>
          <div class="bs-home-tile__body">
            <div class="bs-home-tile__logo" style="background-image:url('{{ base }}image/catalog/Pokemon/PokemonC.png');"></div>
            <div class="bs-home-tile__info">
              <h3>Pokemon TCG</h3>
              <p>Оригінальні бустери, бокси та набори Pokémon TCG. Японські, корейські й англійські видання - sealed, без зважування.</p>
              <div class="bs-home-tile__cta">
                <span class="bs-home-tile__arrow">Переглянути &rarr;</span>
              </div>
            </div>
          </div>
        </a>

        <a class="bs-home-tile bs-home-tile--onepiece" href="/catalog/One-Piece">
          <div class="bs-home-tile__strip"></div>
          <div class="bs-home-tile__body">
            <div class="bs-home-tile__logo" style="background-image:url('{{ base }}image/catalog/One%20Piece/One%20PieceC.png');"></div>
            <div class="bs-home-tile__info">
              <h3>One Piece Card Game</h3>
              <p>Оригінальні бустери та бокси One Piece Card Game від Bandai. Sealed із боксів, без сортування.</p>
              <div class="bs-home-tile__cta">
                <span class="bs-home-tile__arrow">Переглянути &rarr;</span>
              </div>
            </div>
          </div>
        </a>
      </section>
TWIG;

$homeTilesNew = <<<'TWIG'
      <section class="bs-home-tiles bs-catcards" aria-label="Категорії товарів">
        <a class="bs-catcard" href="/catalog/Pokemon" style="--accent:#C68A00;">
          <span class="bs-catcard__media">
            <img src="{{ base }}image/catalog/Pokemon/PokemonC.png" alt="Pokémon TCG" loading="eager" width="168" height="168">
          </span>
          <span class="bs-catcard__body">
            <span class="bs-catcard__title">Pokémon TCG</span>
            <span class="bs-catcard__desc">Оригінальні бустери, бокси та набори Pokémon TCG. Японські, корейські й англійські видання — sealed, без зважування.</span>
            <span class="bs-catcard__more">Переглянути →</span>
          </span>
        </a>
        <a class="bs-catcard" href="/catalog/One-Piece" style="--accent:#1E40AF;">
          <span class="bs-catcard__media">
            <img src="{{ base }}image/catalog/One%20Piece/One%20PieceC.png" alt="One Piece Card Game" loading="eager" width="168" height="168">
          </span>
          <span class="bs-catcard__body">
            <span class="bs-catcard__title">One Piece Card Game</span>
            <span class="bs-catcard__desc">Оригінальні бустери та бокси One Piece Card Game від Bandai. Sealed із боксів, без сортування.</span>
            <span class="bs-catcard__more">Переглянути →</span>
          </span>
        </a>
      </section>
TWIG;

if (strpos($home, $homeTilesOld) === false) fail('bs-home-tile block not found verbatim in home.twig — check whitespace');
$home = str_replace($homeTilesOld, $homeTilesNew, $home);
assert_contains($home, 'bs-catcard', 'bs-catcard in updated home.twig');
out('fix2=category_tiles_updated');

/* ================================================================
   FIX 3 — H1 home: improve visual hierarchy via CSS (no text change).
   The h1 text stays; we only update the style block.
   ================================================================ */
$h1StyleOld = <<<'CSS'
  .bs-home-h1 {
    margin: 8px 0 18px;
    color: #111827;
    font-size: 2rem;
    font-weight: 800;
    line-height: 1.18;
  }
  @media (max-width: 767.98px) {
    .bs-home-h1 {
      margin: 6px 0 14px;
      font-size: 1.45rem;
    }
  }
CSS;

$h1StyleNew = <<<'CSS'
  .bs-home-h1 {
    margin: 10px 0 20px;
    color: #111827;
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.25;
    letter-spacing: -0.01em;
    padding-bottom: 12px;
    border-bottom: 1px solid #E5E7EB;
  }
  @media (max-width: 767.98px) {
    .bs-home-h1 {
      margin: 6px 0 14px;
      font-size: 1.15rem;
      font-weight: 700;
    }
  }
CSS;

if (strpos($home, $h1StyleOld) === false) fail('bs-home-h1 style block not found verbatim in home.twig');
$home = str_replace($h1StyleOld, $h1StyleNew, $home);
out('fix3=h1_style_updated');

/* ================================================================
   FIX 4 — Breadcrumb: CSS fix (in boostershop-ds.css append)
   ================================================================ */
// Handled in CSS append block below
out('fix4=breadcrumb_css_will_append');

/* ================================================================
   FIX 5 — Акції: move bs-special-seo block BELOW products, ABOVE FAQ
   ================================================================ */

// Current order in special.twig:
//   {{ content_top }}
//   <h1>...</h1>
//   <div class="bs-special-seo">...</div>   ← must move
//   {% if products %} ... {% endif %}
//   <hr ...>
//   <section class="bs-faq-accordion"> ...   ← seo block goes HERE

// Step 1: extract the seo block
if (!preg_match('~(<div class="bs-special-seo"[^>]*>.*?</div>\s*)~s', $special, $seoMatch)) {
    fail('bs-special-seo block not found in special.twig');
}
$seoBlock = $seoMatch[1];

// Step 2: remove it from current position (after h1)
$specialWithoutSeo = str_replace($seoBlock, '', $special);

// Step 3: insert before <hr class="bs-divider"
$hrTarget = '<hr class="bs-divider"';
if (strpos($specialWithoutSeo, $hrTarget) === false) fail('bs-divider hr not found in special.twig');

$specialFixed = str_replace(
    $hrTarget,
    $seoBlock . "\n      " . $hrTarget,
    $specialWithoutSeo
);

assert_not_contains($specialFixed, "<h1>{{ heading_title }}</h1>\n      <div class=\"bs-special-seo\"", 'seo block no longer after h1');
assert_contains($specialFixed, $seoBlock, 'seo block still present after move');
$special = $specialFixed;
out('fix5=special_seo_block_moved_below_products');

/* ================================================================
   FIX 6 — Footer tagline text
   ================================================================ */
$footer = str_replace(
    'Оригінальні бустери, бокси та набори Pokémon TCG та One Piece Card Game. Японські, корейські та англомовні видання. Без зважування й сортування.',
    'Оригінальні бустери, дисплеї Pokémon TCG, One Piece Card Game та інших TCG.',
    $footer
);
assert_contains($footer, 'Оригінальні бустери, дисплеї', 'new footer text present');
out('fix6=footer_tagline_updated');

/* ================================================================
   FIX 7 — Mobile search + breadcrumb CSS appended to boostershop-ds.css
   ================================================================ */
$cssAppend = <<<'CSS'

/* RD-01-02-03C fixes 20260531 */

/* Fix 7a — Mobile search: fill available space, no cart push */
@media (max-width: 768px) {
  .bs-msearch {
    flex: 1 1 auto !important;
    min-width: 0 !important;
    max-width: calc(100% - 100px); /* leave room for burger + cart */
  }
  .bs-header__cart,
  #cart.bs-header__cart {
    flex: 0 0 auto !important;
  }
}

/* Fix 7b — Breadcrumb: smaller separator, tighter size */
.breadcrumb {
  font-size: 0.875rem;
  --bs-breadcrumb-divider: "›";
  --bs-breadcrumb-item-padding-x: 0.4rem;
  margin-bottom: 12px;
}
.breadcrumb-item + .breadcrumb-item::before {
  font-size: 0.85em;
  opacity: 0.55;
}

/* Fix 2 — bs-catcards grid on home page */
.bs-catcards {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
  margin-bottom: 28px;
}
@media (max-width: 767.98px) {
  .bs-catcards {
    grid-template-columns: 1fr;
    gap: 12px;
    margin-bottom: 20px;
  }
}
CSS;

$css = rtrim($css) . "\n" . $cssAppend . "\n";
assert_contains($css, $cssMarker, 'CSS marker in updated css');
assert_contains($css, 'bs-catcards', 'bs-catcards CSS present');
out('fix7=mobile_search_and_breadcrumb_css_appended');

/* ---- backup + write ---- */
if (!is_dir($backupRoot)) @mkdir($backupRoot, 0755, true);
backup($root, $files['header'],  $backupRoot);
backup($root, $files['home'],    $backupRoot);
backup($root, $files['special'], $backupRoot);
backup($root, $files['footer'],  $backupRoot);
backup($root, $files['css'],     $backupRoot);

write_req(pjoin($root,$files['header']),  $header,  'header.twig');
write_req(pjoin($root,$files['home']),    $home,     'home.twig');
write_req(pjoin($root,$files['special']), $special,  'special.twig');
write_req(pjoin($root,$files['footer']),  $footer,   'footer.twig');
write_req(pjoin($root,$files['css']),     $css,      'boostershop-ds.css');

/* ---- post-write verification ---- */
$hv = read_req(pjoin($root,$files['header']),  'header post');
$ov = read_req(pjoin($root,$files['home']),    'home post');
$sv = read_req(pjoin($root,$files['special']), 'special post');
$fv = read_req(pjoin($root,$files['footer']),  'footer post');
$cv = read_req(pjoin($root,$files['css']),     'css post');

assert_not_contains($hv, '&language={{ lang }}&', 'language param gone from header');
assert_contains($ov,     'bs-catcard',             'bs-catcard in home post');
assert_not_contains($sv, "<h1>{{ heading_title }}</h1>\n      <div class=\"bs-special-seo\"", 'seo block not after h1 post');
assert_contains($fv,     'Оригінальні бустери, дисплеї', 'footer text post');
assert_contains($cv,     $cssMarker,               'CSS marker post');

foreach ([
    $files['header'], $files['home'], $files['special'], $files['footer'], $files['css']
] as $rel) {
    out('changed='.$rel);
}

out('done=ok');
@unlink(__FILE__);
exit(0);
