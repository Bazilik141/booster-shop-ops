<?php
// ACC-006 WP2: PS anchors (real model, str_replace) + native Twig render of thumb/header, before vs after.
namespace Opencart\System\Engine { class Model { public function __get($k) { return null; } } }
namespace {
define('VERSION', '4.1.0.3');
[$self, $live, $wp, $twigenv] = $argv;
require $twigenv . '/vendor/autoload.php';
require $live . '/extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php';
$m = new \Opencart\Catalog\Model\Extension\PsEnhancedMeasurement\Analytics\PsEnhancedMeasurement();
$fail = 0; $ok = function ($n, $c) use (&$fail) { echo ($c ? 'ok   ' : 'FAIL ') . $n . "\n"; $fail += !$c; };
$render = [];
foreach (['product/thumb' => 'replaceCatalogViewProductThumbBefore', 'common/header' => 'replaceCatalogViewCommonHeaderBefore'] as $route => $fn) {
  $views = $m->$fn([]); $counts = [];
  foreach (['before', 'after'] as $side) {
    $src = file_get_contents("$wp/$side/catalog/view/template/$route.twig");
    $counts[$side] = array_map(fn($v) => substr_count($src, $v['search']), $views);
    foreach ($views as $v) $src = str_replace($v['search'], $v['replace'], $src);
    $render[$route][$side] = new \Twig\Environment(new \Twig\Loader\ArrayLoader(['t' => $src]), ['autoescape' => false]);
  }
  $ok("$route anchors " . json_encode($counts['before']) . ' = ' . json_encode($counts['after']), $counts['before'] === $counts['after']);
}
$base = ['product_id' => 61, 'name' => 'Бустер &quot;X&quot; &amp; Y', 'href' => '/p61', 'thumb' => '/i.webp', 'bs_fit_thumb' => '/f.webp', 'bs_media_kind' => 'cut', 'price' => '₴100', 'special' => false, 'cart' => '/c', 'cart_add' => '/a', 'button_cart' => 'Купити', 'minimum' => 1, 'ps_track_add_to_cart' => true, 'ps_track_select_item' => true];
$cases = ['in stock' => [], 'sale' => ['special' => '₴80', 'price_value' => 100, 'special_value' => 80], 'preorder+rare' => ['bs_state' => 'preorder', 'bs_is_rare' => true, 'bs_eta' => 'Орієнтовно: грудень 2026'], 'out' => ['bs_state' => 'out'], 'no image' => ['bs_fit_thumb' => '', 'thumb' => '/image/placeholder.png']];
foreach ($cases as $name => $extra) {
  $b = $render['product/thumb']['before']->render('t', $extra + $base); $a = $render['product/thumb']['after']->render('t', $extra + $base);
  $heart = '<button type="button" class="bs-pcard__heart" data-bs-wishlist-add="61" data-bs-wishlist-name="Бустер &quot;X&quot; &amp; Y" aria-pressed="false" aria-label="Додати в обране: Бустер &quot;X&quot; &amp; Y">';
  $ok("$name: one heart, outside <a> and <form>", substr_count($a, 'class="bs-pcard__heart"') === 1 && strpos($a, $heart) !== false && strpos($a, '</a>' . "\n  " . $heart) !== false && strpos($a, $heart) < strpos($a, '<div class="bs-pcard__body">'));
  $ok("$name: text button gone", strpos($a, 'data-bs-wishlist-text') === false && strpos($a, 'bs-btn-sm w-100 mt-2') === false);
  // The media link (RD-25 slot, badges, CAT-004 column-0 whitespace) must be byte-identical.
  $media = fn($html) => substr($html, strpos($html, '<a class="bs-pcard__media'), strpos($html, '</a>', strpos($html, '<a class="bs-pcard__media')) + 4 - strpos($html, '<a class="bs-pcard__media'));
  $ok("$name: media link byte-identical (slot, badges, CAT-004 whitespace)", $media($a) === $media($b));
  // Strip exactly the WP2 additions/removal; the rest is identical up to inter-tag whitespace.
  $aa = preg_replace('~<div class="bs-pcard__media-wrap">~', '', $a, -1, $c1);
  $aa = preg_replace('~<button type="button" class="bs-pcard__heart".*?</button>\s*</div>~s', '', $aa, -1, $c2);
  $bb = preg_replace('~<button type="button" class="bs-btn bs-btn-secondary bs-btn-sm w-100 mt-2".*?</button>~s', '', $b, -1, $c3);
  $ws = fn($s) => preg_replace('~\s+~', ' ', $s);
  $ok("$name: rest of tile identical (cart form, TECH-015 script), whitespace aside", $c1 === 1 && $c2 === 1 && $c3 === 1 && $ws($aa) === $ws($bb));
}
$hb = $render['common/header']['before']->render('t', []); $ha = $render['common/header']['after']->render('t', []);
$ok('header: only the two token values differ', preg_replace('~\?v=[A-Za-z0-9._-]+"~', '?v=X"', $hb) === preg_replace('~\?v=[A-Za-z0-9._-]+"~', '?v=X"', $ha) && $hb !== $ha);
echo $fail ? "twig_fail=$fail\n" : "twig=ok\n"; exit($fail ? 1 : 0);
}
