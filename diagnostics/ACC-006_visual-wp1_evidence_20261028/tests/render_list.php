<?php
namespace Opencart\System\Engine { class Model { public function __get($k) { return null; } } }
namespace {
define('VERSION', '4.1.0.3');
[$self, $live, $wp, $twigenv, $outdir] = $argv;
require $twigenv . '/vendor/autoload.php';
require $live . '/extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php';
$m = new \Opencart\Catalog\Model\Extension\PsEnhancedMeasurement\Analytics\PsEnhancedMeasurement();
function lang(string $f): array { $_ = []; include $f; return $_; }
$L = lang("$wp/after/extension/ukrainian/catalog/language/uk-ua/account/wishlist.php") + ['button_cart' => 'Купити', 'button_remove' => 'Видалити', 'button_continue' => 'Продовжити'];
function tpl($wp, $route, $views) { $src = file_get_contents("$wp/after/catalog/view/template/$route.twig"); foreach ($views as $v) $src = str_replace($v['search'], $v['replace'], $src); return $src; }
$twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader([
  'list' => tpl($wp, 'account/wishlist_list', $m->replaceCatalogViewAccountWishlistListBefore([])),
  'page' => tpl($wp, 'account/wishlist', $m->replaceCatalogViewAccountWishlistBefore([])),
  'shell' => tpl($wp, 'account/shell', []),
]), ['autoescape' => false]);
$img = 'https://boostershop.website/image/cache/rd25-fit/x.webp';
$p = function ($id, $name, $extra) use ($img) { return $extra + ['product_id' => $id, 'name' => $name, 'href' => "https://example.test/p$id", 'thumb' => $img, 'bs_fit_thumb' => $img, 'bs_media_kind' => 'cut', 'bs_media_width' => 350, 'bs_media_height' => 700, 'price' => '₴4800.00', 'special' => false, 'minimum' => 1, 'bs_preorder' => false, 'bs_can_buy' => true, 'bs_discount' => 0, 'ps_has_options' => false, 'special_value' => 0]; };
$products = [
  $p(1, 'Бустер бокс Pokémon TCG: Abyss Eye (Японське видання)', []),
  $p(2, 'Бустер бокс Hot Wind Arena &quot;Special&quot; &amp; Co', ['special' => '₴2900.00', 'price' => '₴3100.00', 'bs_discount' => 6]),
  $p(3, 'Бустер бокс Pokémon TCG: Mega Symphonia (Японське видання)', ['bs_preorder' => true]),
  $p(4, 'Бустер бокс Pokémon TCG: Inferno X (Японське видання)', ['bs_can_buy' => false]),
  $p(5, 'Бустер One Piece Card Game OP-16: The Time of Battle — дуже довга назва товару для перевірки переносу рядків у картці', []),
];
$common = $L + ['cart' => '/cart', 'cart_add' => '/cart-add', 'bs_wishlist_list_url' => '/list', 'bs_catalog_url' => '/', 'ps_track_add_to_cart' => true, 'ps_track_select_item' => true, 'ps_track_select_promotion' => true];
$list = $twig->render('list', $common + ['products' => $products, 'bs_wishlist_count' => 4]);
$empty = $twig->render('list', $common + ['products' => [], 'bs_wishlist_count' => 0]);
$shellData = ['rd16_mode' => 'page', 'rd16' => ['nav' => 'Розділи кабінету', 'soon' => 'Скоро', 'help' => 'Допомога', 'help_title' => 'Потрібна допомога?', 'help_body' => 'Напишіть у Telegram-бот підтримки.', 'theme' => 'Тема сайту', 'light' => 'Світла', 'dark' => 'Темна'], 'rd16_items' => []];
foreach (['account' => 'Кабінет', 'edit' => 'Особисті дані', 'address' => 'Адреси доставки', 'order' => 'Мої замовлення', 'wishlist' => 'Обране', 'settings' => 'Налаштування', 'logout' => 'Вийти'] as $k => $label) $shellData['rd16_items'][] = ['key' => $k, 'label' => $label, 'href' => $k === 'settings' ? '' : "/$k", 'active' => $k === 'wishlist', 'count' => $k === 'wishlist' ? 5 : null];
$nav = $twig->render('shell', $shellData + ['rd16_part' => 'nav']); $aside = $twig->render('shell', $shellData + ['rd16_part' => 'aside']);
foreach (['full' => [$list, 5, '5 товарів'], 'empty' => [$empty, 0, '0 товарів']] as $k => [$l, $c, $lbl]) {
  $page = $twig->render('page', $L + ['header' => '', 'footer' => '', 'breadcrumbs' => [['href' => '/', 'text' => 'Головна']], 'rd16_shell' => ['nav' => $nav, 'aside' => $aside], 'list' => $l, 'bs_wishlist_count_label' => $lbl, 'content_top' => '', 'content_bottom' => '']);
  file_put_contents("$outdir/favorites-$k.html", $page);
}
// assertions
$checks = [
  'preorder button text' => substr_count($list, '>Передзамовити</button>') === 1,
  'buy button text (incl. card after preorder)' => substr_count($list, '>Купити</button>') === 3 && strrpos($list, '>Купити</button>') > strpos($list, '>Передзамовити</button>'),
  'ga4 cart attrs on 4 buyable' => substr_count($list, 'data-ps-track-event="add_to_cart"') === 4,
  'ga4 select_item links' => substr_count($list, 'data-ps-track-event="select_item"') + substr_count($list, 'data-ps-track-event="select_promotion"') === 10,
  'disabled out' => substr_count($list, ' disabled>Немає в наявності</button>') === 1,
  'forms 0..3' => strpos($list, 'id="form-product-3"') !== false && strpos($list, 'form="form-product-2"') !== false,
  'remove hearts' => substr_count($list, 'data-bs-wishlist-remove=') === 5,
  'encoded name kept' => strpos($list, 'Прибрати з обраного: Бустер бокс Hot Wind Arena &quot;Special&quot; &amp; Co') !== false,
  'discount badge' => strpos($list, '−6%') !== false,
  'empty state' => strpos($empty, 'До каталогу') !== false && strpos($empty, 'Тут збиратимуться ваші товари') !== false,
  'nav count' => strpos($nav, 'bs-account-nav__count" data-bs-wishlist-count>5<') !== false && substr_count($nav, 'bs-account-nav__count') === 1,
];
$bad = 0; foreach ($checks as $n => $ok) { echo ($ok ? 'ok   ' : 'FAIL ') . $n . "\n"; $bad += !$ok; }
exit($bad ? 1 : 0);
}
