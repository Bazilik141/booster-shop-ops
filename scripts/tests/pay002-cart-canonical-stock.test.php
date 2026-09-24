<?php
/** Run against an extracted fixture AFTER the runner; uses the actual controllers and cart gates. */
declare(strict_types=1);
namespace Opencart\System\Engine {
    class Controller {
        public array $services = [];
        public function __get(string $name) { return $this->services[$name]; }
    }
}
namespace {
    $fixture = $argv[1] ?? '';
    if (!is_dir($fixture)) throw new \RuntimeException('Pass extracted fixture root');
    require $fixture . '/system/library/cart/cart.php';
    require $fixture . '/catalog/controller/checkout/cart.php';
    require $fixture . '/catalog/controller/checkout/checkout.php';
    class TestCart extends \Opencart\System\Library\Cart\Cart {
        public array $rows = [];
        public function __construct() {}
        public function getProducts(): array { return $this->rows; }
        public function getTaxes(): array { return []; }
    }
    class Stub {
        public array $handlers;
        public function __construct(array $handlers) { $this->handlers = $handlers; }
        public function __call(string $name, array $args) { return ($this->handlers[$name])(...$args); }
    }
    class GateResult extends \RuntimeException {}
    function check(bool $condition, string $message): void { if (!$condition) throw new \RuntimeException($message); }
    $cart = new TestCart();
    $configValues = ['config_customer_price' => true, 'config_stock_checkout' => false, 'config_stock_warning' => false];
    $rendered = [];
    $services = [
        'cart' => $cart,
        'session' => (object)['data' => []],
        'config' => new Stub(['get' => function($key) use (&$configValues) { return $configValues[$key] ?? null; }]),
        'customer' => new Stub(['isLogged' => fn() => false]),
        'url' => new Stub(['link' => fn($route, ...$rest) => $route]),
        'language' => new Stub(['get' => fn($key) => $key === 'error_minimum' ? 'Minimum: %s %s' : '%s']),
        'load' => new Stub([
            'model' => fn($route) => null,
            'view' => function($route, $data) use (&$rendered) { $rendered = $data; return 'rendered'; }
        ]),
        'model_checkout_cart' => new Stub(['getProducts' => fn() => $cart->rows]),
        'model_tool_image' => new Stub(['resize' => fn(...$args) => 'fixture.png']),
        'model_setting_extension' => new Stub(['getExtensionsByType' => fn($type) => []]),
        'response' => new Stub(['redirect' => function($url) { throw new GateResult('blocked:' . $url); }])
    ];
    $controller = new \Opencart\Catalog\Controller\Checkout\Cart();
    $controller->services = $services;
    $checkout = new \Opencart\Catalog\Controller\Checkout\Checkout();
    $checkout->services = $services;
    $checkout->services['load'] = new Stub(['language' => function($route) { throw new GateResult('allowed'); }]);
    $base = ['stock' => 5, 'stock_status' => true, 'minimum_status' => true, 'stock_status_id' => 5,
        'quantity' => 5, 'minimum' => 1, 'option' => [], 'subscription' => [], 'image' => '', 'product_id' => 1, 'cart_id' => '1'];
    $cases = [
        ['exceeds-positive-stock', ['quantity' => 8, 'stock_status' => false], false, true, false, true],
        ['corrected-to-available', [], false, false, false, false],
        ['zero-stock', ['stock' => 0, 'stock_status' => false], false, true, false, true],
        ['option-shortage', ['stock' => 100, 'quantity' => 2, 'stock_status' => false], false, true, false, true],
        ['stock-checkout-allowed', ['stock_status' => false], true, true, false, false],
        ['preorder-preserved', ['stock' => 0, 'stock_status' => false, 'stock_status_id' => 8], false, false, false, false],
        ['genuine-minimum', ['quantity' => 1, 'minimum' => 3, 'minimum_status' => false], false, false, true, true],
        ['stock-and-minimum', ['stock_status' => false, 'minimum_status' => false], false, true, true, true]
    ];
    foreach ($cases as [$name, $overrides, $allowStock, $warnStock, $warnMin, $blocked]) {
        $cart->rows = [array_replace($base, $overrides)];
        $configValues['config_stock_checkout'] = $allowStock;
        $controller->getList();
        check($rendered['pay002_cart_stock_warning'] === $warnStock, $name . ': stock warning');
        check($rendered['pay002_cart_minimum_checkout_blocked'] === $warnMin, $name . ': minimum warning');
        check(($rendered['pay002_cart_stock_checkout_blocked'] || $rendered['pay002_cart_minimum_checkout_blocked']) === $blocked, $name . ': CTA state');
        check(($rendered['products'][0]['minimum_error'] !== '') === $warnMin, $name . ': row minimum text');
        try { $checkout->index(); throw new \RuntimeException('entry gate did not stop fixture'); }
        catch (GateResult $result) { check(($result->getMessage() !== 'allowed') === $blocked, $name . ': checkout/cart disagreement'); }
        echo 'PASS ' . $name . PHP_EOL;
    }
    $cart->rows = [$base, array_replace($base, ['stock_status' => false])];
    $controller->getList();
    check($rendered['pay002_cart_stock_warning'] === true, 'mixed cart');
    echo 'PASS mixed cart' . PHP_EOL;
    // The same controller instance must clear a previously computed warning on the next fragment render.
    $cart->rows = [$base];
    $controller->getList();
    check(!$rendered['pay002_cart_stock_warning'] && !$rendered['pay002_cart_minimum_checkout_blocked'], 'fragment recovery');
    echo 'PASS fragment recovery' . PHP_EOL;
}
