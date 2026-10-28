<?php
// ACC-006 WP1 local check: PS GA4 anchors (native model replacements, str_replace semantics)
// and native Twig compile/render of every changed template, before and after.
namespace Opencart\System\Engine { class Model { public function __get($k) { return null; } } }
namespace {
define('VERSION', '4.1.0.3');
[$self, $live, $wp, $twigenv] = $argv;
require $twigenv . '/vendor/autoload.php';
require $live . '/extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php';
$m = new \Opencart\Catalog\Model\Extension\PsEnhancedMeasurement\Analytics\PsEnhancedMeasurement();
$map = [
  'product/thumb' => 'replaceCatalogViewProductThumbBefore',
  'product/product' => 'replaceCatalogViewProductProductBefore',
  'account/wishlist' => 'replaceCatalogViewAccountWishlistBefore',
  'account/wishlist_list' => 'replaceCatalogViewAccountWishlistListBefore',
  'common/header' => 'replaceCatalogViewCommonHeaderBefore',
  'account/shell' => null,
];
$fail = 0;
foreach ($map as $route => $fn) {
  $views = $fn ? $m->$fn([]) : [];
  $out = [];
  foreach (['before', 'after'] as $side) {
    $src = file_get_contents("$wp/$side/catalog/view/template/$route.twig");
    $counts = [];
    foreach ($views as $v) $counts[] = substr_count($src, $v['search']);
    foreach ($views as $v) $src = str_replace($v['search'], $v['replace'], $src);
    $twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader(['t' => $src]), ['autoescape' => false, 'strict_variables' => false]);
    try { $twig->render('t', []); $render = 'ok'; } catch (\Throwable $e) { $render = 'ERR ' . $e->getMessage(); $fail++; }
    $out[$side] = [$counts, $render];
  }
  $same = $out['before'][0] === $out['after'][0];
  if (!$same) $fail++;
  printf("%-22s anchors before=%s after=%s %s | render before=%s after=%s\n", $route, json_encode($out['before'][0]), json_encode($out['after'][0]), $same ? 'SAME' : 'CHANGED', $out['before'][1], $out['after'][1]);
}
echo $fail ? "anchors_fail=$fail\n" : "anchors=ok\n";
exit($fail ? 1 : 0);
}
