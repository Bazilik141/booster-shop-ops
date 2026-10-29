<?php
// ACC-006 WP3: PS anchors + native Twig render of product.twig (and header) before vs after.
namespace Opencart\System\Engine { class Model { public function __get($k) { return null; } } }
namespace {
define('VERSION', '4.1.0.3');
[$self, $live, $wp, $twigenv] = $argv;
require $twigenv . '/vendor/autoload.php';
require $live . '/extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php';
$m = new \Opencart\Catalog\Model\Extension\PsEnhancedMeasurement\Analytics\PsEnhancedMeasurement();
$fail = 0; $ok = function ($n, $c) use (&$fail) { echo ($c ? 'ok   ' : 'FAIL ') . $n . "\n"; $fail += !$c; };
$env = [];
foreach (['product/product' => 'replaceCatalogViewProductProductBefore', 'common/header' => 'replaceCatalogViewCommonHeaderBefore'] as $route => $fn) {
  $views = $m->$fn([]); $counts = [];
  foreach (['before', 'after'] as $side) {
    $src = file_get_contents("$wp/$side/catalog/view/template/$route.twig");
    $counts[$side] = array_map(fn($v) => substr_count($src, $v['search']), $views);
    foreach ($views as $v) $src = str_replace($v['search'], $v['replace'], $src);
    $env[$route][$side] = new \Twig\Environment(new \Twig\Loader\ArrayLoader(['t' => $src]), ['autoescape' => false]);
  }
  $ok("$route anchors " . json_encode($counts['before']) . ' = ' . json_encode($counts['after']), $counts['before'] === $counts['after']);
}
$img = ['main' => '/m.webp', 'popup' => '/p.webp', 'small' => '/s.webp', 'kind' => 'cut', 'main_width' => 400, 'main_height' => 800, 'small_width' => 50, 'small_height' => 100];
$base = ['product_id' => 70, 'heading_title' => 'Бустер &quot;OP-11&quot; &amp; Co', 'thumb' => '/t.webp', 'images' => [], 'bs_gallery' => [$img, $img], 'bs_gallery_ratio' => '4 / 5', 'price' => '₴180', 'minimum' => 1, 'stock' => 'В наявності', 'breadcrumbs' => [['href' => '/', 'text' => 'Головна'], ['href' => '/p', 'text' => 'X']], 'ps_track_add_to_wishlist' => true, 'ps_track_add_to_cart' => true, 'model' => 'OP-JP-OP11-BST', 'pay001_mono_chast_visible' => true, 'pay001_mono_chast_price' => '180', 'variant_groups' => [['label' => 'Сет', 'show_price' => false, 'values' => [['label' => 'OP-11', 'href' => '/a', 'state' => 'in', 'is_current' => true]]]]];
$cases = ['in stock, gallery' => [], 'preorder' => ['stock' => 'Передзамовлення', 'quantity' => 0], 'no gallery' => ['thumb' => '', 'images' => [], 'bs_gallery' => []]];
$heart = 'data-bs-wishlist-add="70" data-bs-wishlist-name="Бустер &quot;OP-11&quot; &amp; Co" aria-pressed="false" aria-label="Додати в обране: Бустер &quot;OP-11&quot; &amp; Co"';
$cut = function (string $html, string $start, string $end) { $a = strpos($html, $start); if ($a === false) return null; $b = strpos($html, $end, $a); return substr($html, $a, $b - $a + strlen($end)); };
foreach ($cases as $name => $extra) {
  $b = $env['product/product']['before']->render('t', $extra + $base); $a = $env['product/product']['after']->render('t', $extra + $base);
  $gallery = $name !== 'no gallery';
  $ok("$name: row heart in .bs-buy-row after #button-cart, type=button" . ($gallery ? '' : ', --solo'), substr_count($a, 'class="bs-product-heart bs-product-heart--row' . ($gallery ? '"' : ' bs-product-heart--solo"') . ' ' . $heart) === 1 && strrpos($a, 'id="button-cart"') < strpos($a, 'bs-product-heart--row') && strpos($a, 'bs-product-heart--row') < strpos($a, '</form>'));
  $ok("$name: gallery heart " . ($gallery ? 'once, sibling after the Magnific <a>' : 'absent'), $gallery ? (substr_count($a, 'class="bs-product-heart bs-product-heart--gallery" ' . $heart) === 1 && preg_match('~data-bs-gallery-main[^>]*>.*?</a>\s*<button type="button" class="bs-product-heart bs-product-heart--gallery"~s', $a)) : strpos($a, 'bs-product-heart--gallery') === false);
  $ok("$name: WP1 text button gone, exactly two/one hearts", strpos($a, 'data-bs-wishlist-text') === false && substr_count($a, 'data-bs-wishlist-add=') === ($gallery ? 2 : 1));
  foreach (['sticky ATC bar' => ['<div class="bs-sticky-atc"', '</script>'], 'credit/installment block' => ['<!-- PAY-001-PHASE2C-CART-CONTRACT-GATES-20260725 -->', '</section>'], 'variant selector' => ['<div class="bs-variant', '</div>'], 'gallery main link' => ['<a href="/p.webp" title=', '</a>'], 'JSON-LD' => ['<script type="application/ld+json">', '</script>'], 'cart button + GA4 hidden input' => ['<input type="hidden" name="category_id"', 'id="button-cart"']] as $label => [$s, $e]) {
    $x = $cut($b, $s, $e); $y = $cut($a, $s, $e);
    if ($x === null && $label === 'gallery main link' && !$gallery) continue;
    $ok("$name: $label byte-identical", $x !== null && $x === $y);
  }
  // Everything else identical apart from the WP3 additions/removal and inter-tag whitespace.
  $aa = preg_replace(['~<button type="button" class="bs-product-heart[^"]*"[^>]*>.*?</button>~s'], '', $a, -1, $c1);
  $bb = preg_replace('~<button type="button" class="bs-btn bs-btn-secondary mt-2" data-bs-wishlist-add.*?</button>~s', '', $b, -1, $c2);
  $ws = fn($s) => preg_replace('~\s+~', ' ', $s);
  $ok("$name: rest of page identical (whitespace aside)", $c1 === ($gallery ? 2 : 1) && $c2 === 1 && $ws($aa) === $ws($bb));
}
$hb = $env['common/header']['before']->render('t', []); $ha = $env['common/header']['after']->render('t', []);
$ok('header: only the two token values differ', preg_replace('~\?v=[A-Za-z0-9._-]+"~', '?v=X"', $hb) === preg_replace('~\?v=[A-Za-z0-9._-]+"~', '?v=X"', $ha) && $hb !== $ha);
echo $fail ? "twig_fail=$fail\n" : "twig=ok\n"; exit($fail ? 1 : 0);
}
