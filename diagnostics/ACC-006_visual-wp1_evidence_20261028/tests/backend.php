<?php
// ACC-006 WP1 backend checks against the actual candidate controllers with fake OpenCart services.
namespace Opencart\System\Engine {
  class Controller { protected $registry; public function __construct($r) { $this->registry = $r; } public function __get($k) { return $this->registry->$k ?? null; } }
}
namespace {
define('DB_PREFIX', 'oc_'); define('DIR_IMAGE', dirname(__DIR__) . '/fixtures/img/');
[$self, $wp] = $argv;
require "$wp/after/catalog/controller/account/wishlist.php";
require "$wp/after/catalog/controller/account/shell.php";
function lang(string $f): array { $_ = []; include $f; return $_; }
class R {}
class Lang { public $d = []; function get($k) { return $this->d[$k] ?? $k; } }
class Resp { public $out = ''; public $redirect = null; function addHeader($h) {} function setOutput($o) { $this->out = $o; } function redirect($u) { $this->redirect = $u; throw new RuntimeException('redirect'); } }
class Cust { public $logged = false; public $id = 0; function isLogged() { return $this->logged; } function getId() { return $this->id; } }
class Conf { public $d = ['config_language' => 'uk-ua', 'config_language_id' => 3, 'config_store_id' => 0, 'config_customer_price' => 0, 'config_tax' => 0, 'config_stock_display' => 0, 'config_stock_status_id' => 7, 'config_image_wishlist_width' => 47, 'config_image_wishlist_height' => 47]; function get($k) { return $this->d[$k] ?? null; } }
class Url { function link($r, $a = '', $js = false) { return "https://shop.test/index.php?route=$r&amp;" . str_replace('&', '&amp;', $a); } }
class Db { public $queries = []; public $visible = []; function query($sql) { $this->queries[] = $sql; $o = new stdClass;
  if (strpos($sql, 'COUNT(DISTINCT') !== false) { $o->row = ['total' => count($this->visible)]; $o->rows = [$o->row]; return $o; }
  if (strpos($sql, 'SELECT DISTINCT `cw`.`product_id`') !== false) { $o->rows = array_map(fn($i) => ['product_id' => (string)$i], $this->visible); $o->row = $o->rows[0] ?? []; return $o; }
  throw new RuntimeException('unexpected sql'); } }
class WishModel { public $rows = []; public $calls = []; function getWishlist($c) { return array_map(fn($i) => ['product_id' => $i], $this->rows); } function addWishlist($c, $p) { $this->calls[] = ['add', $p]; if (!in_array($p, $this->rows)) $this->rows[] = $p; } function deleteWishlist($c, $p) { $this->calls[] = ['delete', $p]; $this->rows = array_values(array_diff($this->rows, [$p])); } }
class ProdModel { public $p = []; function getProduct($id) { return $this->p[$id] ?? []; } }
class ImgModel { function resize($f, $w, $h) { return "https://shop.test/image/$f-{$w}x$h"; } function resizeFit($f, $w, $h) { return "https://shop.test/image/fit-$f"; } function mediaInfo($f) { return ['kind' => 'cut', 'crop_width' => 400, 'crop_height' => 800]; } }
class StockModel { function getStockStatus($id) { return ['name' => $id == 8 ? 'Передзамовлення' : 'Немає в наявності']; } }
class Cur { function format($v, $c) { return '₴' . number_format((float)$v, 2, '.', ''); } }
class Tax { function calculate($v, $c, $t) { return $v; } }
class Doc { function setTitle($t) {} }
class Loader { public $reg; public $views = []; public $controllers = []; function __construct($reg) { $this->reg = $reg; }
  function language($r) { $this->reg->language->d += lang($GLOBALS['LANGDIR'] . '/' . $r . '.php'); return []; }
  function model($r) { }
  function view($n, $d) { $this->views[] = [$n, $d]; return "VIEW:$n"; }
  function controller($r, ...$a) { $this->controllers[] = [$r, $a];
    if ($r === 'account/login.validate') return $this->reg->validate;
    if ($r === 'account/wishlist.total') { $c = new \Opencart\Catalog\Controller\Account\WishList($this->reg); return $c->total(); }
    if ($r === 'account/shell') return ['nav' => 'NAV', 'aside' => 'ASIDE'];
    return ''; } }
$GLOBALS['LANGDIR'] = "$wp/after/extension/ukrainian/catalog/language/uk-ua";
function fresh(bool $logged, string $token = 'tok') {
  $r = new R; $r->language = new Lang; $r->response = new Resp; $r->customer = new Cust; $r->customer->logged = $logged; $r->customer->id = $logged ? 42 : 0;
  $r->config = new Conf; $r->url = new Url; $r->db = new Db; $r->validate = true; $r->currency = new Cur; $r->tax = new Tax; $r->document = new Doc;
  $r->session = new stdClass; $r->session->data = ['acc006_wishlist_token' => 'tok', 'currency' => 'UAH'] + ($logged ? ['customer_token' => 'ct'] : []);
  $r->request = new stdClass; $r->request->server = ['REQUEST_METHOD' => 'POST']; $r->request->post = ['wishlist_token' => $token]; $r->request->cookie = [];
  $r->model_account_wishlist = new WishModel; $r->model_catalog_product = new ProdModel; $r->model_tool_image = new ImgModel; $r->model_localisation_stock_status = new StockModel;
  foreach ([5 => ['quantity' => 3, 'price' => 100, 'special' => 0], 6 => ['quantity' => 0, 'price' => 200, 'special' => 0, 'stock_status_id' => 8], 7 => ['quantity' => 2, 'price' => 100, 'special' => 80], 8 => ['quantity' => 0, 'price' => 50, 'special' => 0, 'stock_status_id' => 5]] as $id => $p)
    $r->model_catalog_product->p[$id] = $p + ['product_id' => $id, 'name' => "P$id", 'image' => "catalog/p$id.png", 'tax_class_id' => 0, 'minimum' => 1, 'stock_status_id' => 7, 'date_available' => '2026-01-01'];
  $r->load = new Loader($r);
  return $r;
}
function call($r, $m) { $c = new \Opencart\Catalog\Controller\Account\WishList($r); $c->$m(); return json_decode($r->response->out, true); }
$t = []; $chk = function ($n, $ok) use (&$t) { $t[$n] = (bool)$ok; };
// guest flow
$r = fresh(false); $r->request->post['product_id'] = '5';
$j = call($r, 'add'); $chk('guest add: added, pending, ids [5], count 1', $j['added'] === true && $r->session->data['wishlist'] === [5] && $j['ids'] === [5] && $j['count'] === 1 && $j['requires_login'] === true && $j['success'] === 'Збережено в цьому браузері до входу');
$j = call($r, 'add'); $chk('guest repeat add: added false', $j['added'] === false && $j['ids'] === [5]);
$j = call($r, 'remove'); $chk('guest remove: pending cleared, no DB/model', $r->session->data['wishlist'] === [] && $j['ids'] === [] && $j['count'] === 0 && $j['success'] === 'Прибрано з обраного' && !$r->db->queries && !$r->model_account_wishlist->calls);
$r->request->post['product_id'] = '0'; $j = call($r, 'remove'); $chk('guest remove id 0: error_product, no delete-all', $j['error'] === 'Товар не знайдено або недоступний.');
$r->request->post['product_id'] = ['5']; $j = call($r, 'remove'); $chk('guest remove array id: error_product', isset($j['error']));
$r2 = fresh(false, 'bad'); $r2->request->post['product_id'] = '5'; $j = call($r2, 'remove'); $chk('guest remove bad token: error_auth', $j['error'] === 'Оновіть сторінку та повторіть дію.');
$r3 = fresh(false); $r3->request->server['REQUEST_METHOD'] = 'GET'; $r3->request->post['product_id'] = '5'; $j = call($r3, 'remove'); $chk('guest remove GET: error_method', $j['error'] === 'Некоректний спосіб виконання дії.');
// logged flow
$r = fresh(true); $r->request->post['product_id'] = '7'; $r->db->visible = [7];
$j = call($r, 'add'); $chk('logged add: model add, ids from SQL', $r->model_account_wishlist->calls === [['add', 7]] && $j['ids'] === [7] && $j['added'] === true && $j['success'] === 'Додано в обране' && empty($j['requires_login']));
$r->db->visible = []; $j = call($r, 'remove'); $chk('logged remove: model delete', end($r->model_account_wishlist->calls) === ['delete', 7] && $j['ids'] === [] && $j['success'] === 'Прибрано з обраного');
$r4 = fresh(true); $r4->validate = false; $r4->request->post['product_id'] = '7'; $j = call($r4, 'remove'); $chk('logged remove invalid customer token: error_auth', $j['error'] === 'Оновіть сторінку та повторіть дію.' && !$r4->model_account_wishlist->calls);
// ids SQL equals total SQL apart from SELECT and ORDER BY
$r = fresh(true); $c = new \Opencart\Catalog\Controller\Account\WishList($r); $c->total(); $c->ids();
[$qt, $qi] = $r->db->queries; $norm = str_replace(['SELECT DISTINCT `cw`.`product_id`', ' ORDER BY `cw`.`product_id`'], ['SELECT COUNT(DISTINCT `cw`.`product_id`) AS `total`', ''], $qi);
$chk('ids SQL = total SQL filter', $norm === $qt);
$r = fresh(false); $r->session->data['wishlist'] = ['9', 3, 'x', -1, 9]; $c = new \Opencart\Catalog\Controller\Account\WishList($r); $chk('guest ids sanitized', $c->ids() === [9, 3]);
// index + list
$r = fresh(true); $r->model_account_wishlist->rows = [5, 6, 7, 8]; $r->db->visible = [5, 6, 7, 8];
if (!is_dir(DIR_IMAGE . 'catalog')) mkdir(DIR_IMAGE . 'catalog', 0700, true);
foreach ([5, 6, 7, 8] as $id) touch(DIR_IMAGE . "catalog/p$id.png");
$r->request->server['REQUEST_METHOD'] = 'GET'; $c = new \Opencart\Catalog\Controller\Account\WishList($r); $c->index();
$page = null; $list = null; foreach ($r->load->views as [$n, $d]) { if ($n === 'account/wishlist') $page = $d; if ($n === 'account/wishlist_list') $list = $d; }
$shellCall = null; foreach ($r->load->controllers as [$n, $a]) if ($n === 'account/shell') $shellCall = $a[0];
$chk('index: shell page/wishlist, column_left empty', $shellCall['mode'] === 'page' && $shellCall['active'] === 'wishlist' && $shellCall['labels']['wishlist'] === 'Обране' && $page['column_left'] === '' && strpos($shellCall['links']['order'], '&customer_token=ct') !== false && strpos($shellCall['links']['order'], '&amp;') === false);
$chk('index: heading Обране, label 4 товари', $r->language->get('heading_title') === 'Обране' && $page['bs_wishlist_count_label'] === '4 товари');
$byId = []; foreach ($list['products'] as $p) $byId[$p['product_id']] = $p;
$chk('list: fit thumb + kind', $byId[5]['bs_fit_thumb'] === 'https://shop.test/image/fit-catalog/p5.png' && $byId[5]['bs_media_kind'] === 'cut' && $byId[5]['bs_media_width'] === 400 && $byId[5]['bs_media_height'] === 800);
$chk('list: states', $byId[5]['bs_can_buy'] && !$byId[5]['bs_preorder'] && $byId[6]['bs_preorder'] && $byId[6]['bs_can_buy'] && !$byId[8]['bs_can_buy']);
$chk('list: discount 20 on sale, 0 otherwise', $byId[7]['bs_discount'] === 20 && $byId[5]['bs_discount'] === 0);
$chk('list: catalog url', strpos($list['bs_catalog_url'], 'route=common/home') !== false);
// plural
$m = new ReflectionMethod(\Opencart\Catalog\Controller\Account\WishList::class, 'countLabel'); $m->setAccessible(true);
$out = []; foreach ([0, 1, 2, 4, 5, 11, 12, 14, 21, 22, 25, 101, 111] as $n) $out[] = $m->invoke($c, $n);
$chk('plural forms', $out === ['0 товарів', '1 товар', '2 товари', '4 товари', '5 товарів', '11 товарів', '12 товарів', '14 товарів', '21 товар', '22 товари', '25 товарів', '101 товар', '111 товарів']);
// shell count
$r = fresh(true); $r->db->visible = [5, 7]; $s = new \Opencart\Catalog\Controller\Account\Shell($r);
$s->index(['mode' => 'page', 'active' => 'wishlist', 'labels' => array_fill_keys(['account','edit','address','order','wishlist','settings','logout'], 'x'), 'links' => array_fill_keys(['account','edit','address','order','wishlist','logout'], '/x')]);
$items = null; foreach ($r->load->views as [$n, $d]) if ($n === 'account/shell') $items = $d['rd16_items'];
$counts = array_column($items, 'count', 'key');
$chk('shell: wishlist count 2, others null', $counts['wishlist'] === 2 && $counts['order'] === null && count(array_filter($counts, fn($v) => $v !== null)) === 1);
$r = fresh(false); $s = new \Opencart\Catalog\Controller\Account\Shell($r); $chk('shell: guest returns empty', $s->index(['links' => [1], 'labels' => [1]]) === ['nav' => '', 'aside' => '']);
$bad = 0; foreach ($t as $n => $ok) { echo ($ok ? 'ok   ' : 'FAIL ') . $n . "\n"; $bad += !$ok; } echo 'backend_checks=' . count($t) . ' failed=' . $bad . "\n"; exit($bad ? 1 : 0);
}
