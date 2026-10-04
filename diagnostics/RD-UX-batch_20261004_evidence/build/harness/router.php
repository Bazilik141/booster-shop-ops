<?php
declare(strict_types=1);
/* Local fixture renderer. Serves static files from the working root and renders the
   site's own templates with fixture data using the site's own Twig (3.18) source.
   Fixture-only additions (Manrope from Google Fonts, the page picker) never reach a runner. */

$W = rtrim((string)(getenv('BS_WORK') ?: dirname(__DIR__) . '/work'), '/\\');
$uri = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($uri !== '/' && $uri !== '/index.php' && is_file($W . $uri)) return false;

$route = (string)($_GET['route'] ?? '');
if (strpos($route, 'ps_live_search.autocomplete') !== false) { require __DIR__ . '/livesearch.php'; exit; }

$twigSrc = $W . '/storage/vendor/twig/twig/src/';
spl_autoload_register(static function (string $c) use ($twigSrc): void {
    if (strncmp($c, 'Twig\\', 5) !== 0) return;
    $f = $twigSrc . str_replace('\\', '/', substr($c, 5)) . '.php';
    if (is_file($f)) require_once $f;
});
foreach (['core.php', 'debug.php', 'escaper.php', 'string_loader.php'] as $r) if (is_file($twigSrc . 'Resources/' . $r)) require_once $twigSrc . 'Resources/' . $r;

$twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader('./', $W), ['charset' => 'utf-8', 'autoescape' => false, 'debug' => true, 'cache' => false]);
$twig->addExtension(new \Twig\Extension\DebugExtension());
function tpl(string $name, array $data): string { global $twig; return $twig->render('catalog/view/template/' . $name . '.twig', $data); }
function ext_tpl(string $path, array $data): string { global $twig; return $twig->render($path, $data); }

$base = 'http://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1') . '/';
$page = (string)($_GET['page'] ?? ($route === 'product/search' ? 'search' : ($route === 'product/category' ? 'category' : 'index')));
$logged = !empty($_GET['logged']);

function header_html(array $o): string {
    global $base, $logged;
    $cart = tpl('common/cart', [
        'text_items' => $o['cart_text'] ?? '0 товар(ів) - 0.00₴', 'products' => $o['cart_products'] ?? [], 'vouchers' => [], 'totals' => $o['cart_totals'] ?? [],
        'bs_cart_qty' => $o['cart_qty'] ?? 0, 'sub_total' => 0, 'shipping_pinta_nova_poshta_free_from' => 2000, 'checkout' => 'index.php?route=checkout/checkout', 'cart' => 'index.php?route=checkout/cart',
        'remove' => '#', 'language' => 'uk-ua', 'text_no_results' => 'Ваш кошик порожній!',
    ]);
    return tpl('common/header', [
        'direction' => 'ltr', 'lang' => 'uk', 'title' => $o['title'] ?? 'Fixture', 'base' => $base, 'description' => '', 'keywords' => '',
        'robots' => $o['robots'] ?? '', 'route' => $o['route'] ?? 'common/home', 'meta_url' => $base, 'meta_image' => '',
        'jquery' => 'catalog/view/javascript/jquery/jquery-3.7.1.min.js', 'bootstrap' => 'catalog/view/stylesheet/bootstrap.css',
        'icons' => 'catalog/view/stylesheet/fonts/fontawesome/css/all.min.css', 'stylesheet' => 'catalog/view/stylesheet/stylesheet.css',
        'styles' => [['href' => 'extension/ps_live_search/catalog/view/stylesheet/ps_live_search.css', 'rel' => 'stylesheet', 'media' => 'screen']],
        'scripts' => [['href' => 'extension/ps_live_search/catalog/view/javascript/ps_live_search.js']],
        'links' => $o['links'] ?? [], 'analytics' => [], 'icon' => '', 'logo' => 'image/catalog/fixture-logo.png', 'name' => 'Booster Shop',
        'home' => $base, 'logged' => $logged, 'account' => '#account', 'login' => '#login', 'order' => '#orders', 'text_account' => 'Акаунт',
        'text_checkout' => 'Оформлення замовлення', 'cart' => $cart, 'menu' => '',
    ]);
}
function footer_html(): string {
    return tpl('common/footer', ['bootstrap' => 'catalog/view/javascript/bootstrap/js/bootstrap.bundle.min.js', 'scripts' => [],
        'about_us' => '#', 'payment_delivery' => '#', 'original_guarantee' => '#', 'exchange_returns' => '#', 'public_offer' => '#', 'special' => '#', 'cookie' => '']);
}
function crumbs(array $items): array { $o = [['text' => '<i class="fa-solid fa-house"></i>', 'href' => '/']]; foreach ($items as $t) $o[] = ['text' => $t, 'href' => '#' . md5($t)]; return $o; }

require __DIR__ . '/fixtures.php';

$html = render_page($page);
// Fixture-only: never send local renders to the owner's Clarity project.
$html = str_replace('"https://www.clarity.ms/tag/"+i', '"data:text/javascript,//"+i', $html);
$inject = '<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">'
    . '<style>/* fixture-only */ .fx-pick{position:fixed;right:4px;bottom:4px;z-index:9999;font:11px monospace;background:#ff0;padding:2px 4px;opacity:.7;pointer-events:none}</style>';
$html = preg_replace('~</head>~', $inject . '</head>', $html, 1);
$html = preg_replace('~</body>~', '<div class="fx-pick">' . htmlspecialchars($page . ' ' . ($_SERVER['QUERY_STRING'] ?? '')) . '</div></body>', $html, 1);
header('Content-Type: text/html; charset=utf-8');
echo $html;
