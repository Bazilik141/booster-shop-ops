<?php
declare(strict_types=1);

namespace Opencart\System\Engine {
    class Controller {
        public mixed $config;
        public mixed $request;
    }
}

namespace {
final class FakeConfig {
    /** @param array<string,mixed> $values */
    public function __construct(private array $values) {}
    public function get(string $key): mixed { return $this->values[$key] ?? null; }
}

$root = dirname(__DIR__, 2);
require $root . '/work/pay003-callback-cidr/candidate/extension/pumb_credit/catalog/controller/payment/pumb_credit.php';
$controller = new \Opencart\Catalog\Controller\Extension\PumbCredit\Payment\PumbCredit();
$controller->request = (object)['server' => []];
$invoke = static function (object $object, string $method, mixed ...$args): mixed {
    $reflection = new \ReflectionMethod($object, $method);
    $reflection->setAccessible(true);
    return $reflection->invoke($object, ...$args);
};
$checks = 0;
$check = static function (bool $actual, bool $expected, string $name) use (&$checks): void {
    $checks++;
    if ($actual !== $expected) throw new \RuntimeException('FAIL ' . $name);
};

$cases = [
    ['194.44.66.20', '194.44.66.20', true, 'exact IPv4'],
    ['194.44.66.21', '194.44.66.20', false, 'different exact IPv4'],
    ['194.44.66.16', '194.44.66.16/28', true, 'IPv4 CIDR lower boundary'],
    ['194.44.66.31', '194.44.66.16/28', true, 'IPv4 CIDR upper boundary'],
    ['194.44.66.15', '194.44.66.16/28', false, 'IPv4 below CIDR'],
    ['194.44.66.32', '194.44.66.16/28', false, 'IPv4 above CIDR'],
    ['194.44.66.20', '194.44.66.19/28', true, 'CIDR network bits are masked'],
    ['194.44.66.20', '194.44.66.16/33', false, 'IPv4 prefix overflow'],
    ['194.44.66.20', '194.44.66.16/-1', false, 'negative prefix refused'],
    ['194.44.66.20', 'not-an-ip/28', false, 'invalid network refused'],
    ['2001:db8:1::1', '2001:db8::/32', true, 'IPv6 CIDR'],
    ['2001:db9::1', '2001:db8::/32', false, 'IPv6 outside CIDR'],
    ['2001:0db8:0:0:0:0:0:1', '2001:db8::1', true, 'equivalent exact IPv6'],
    ['194.44.66.20', '2001:db8::/32', false, 'address-family mismatch'],
    ['', '194.44.66.16/28', false, 'missing remote refused'],
];
foreach ($cases as [$remote, $allowed, $expected, $name]) {
    $check($invoke($controller, 'ipMatches', $remote, $allowed), $expected, $name);
}

$controller->config = new FakeConfig(['payment_pumb_credit_callback_ips' => '']);
$controller->request->server = ['REMOTE_ADDR' => '203.0.113.5'];
$check($invoke($controller, 'allowedIp', false), true, 'empty allowlist preserves existing allow-all behavior');

$controller->config = new FakeConfig(['payment_pumb_credit_callback_ips' => '203.0.113.7, 194.44.66.16/28']);
$controller->request->server = ['REMOTE_ADDR' => '194.44.66.21'];
$check($invoke($controller, 'allowedIp', false), true, 'comma-separated CIDR match');
$controller->request->server = ['REMOTE_ADDR' => '203.0.113.7'];
$check($invoke($controller, 'allowedIp', false), true, 'comma-separated exact match');
$controller->request->server = ['REMOTE_ADDR' => '203.0.113.8'];
$check($invoke($controller, 'allowedIp', false), false, 'outside allowlist refused');

$controller->config = new FakeConfig(['payment_pumb_credit_callback_ips' => 'invalid-entry']);
$controller->request->server = ['REMOTE_ADDR' => '194.44.66.21'];
$check($invoke($controller, 'allowedIp', false), false, 'non-empty invalid allowlist fails closed');

$controller->config = new FakeConfig(['payment_pumb_credit_callback_ips' => '194.44.66.16/28']);
$controller->request->server = ['REMOTE_ADDR' => '203.0.113.8', 'HTTP_X_FORWARDED_FOR' => '194.44.66.21'];
$check($invoke($controller, 'allowedIp', false), false, 'forwarded header is not trusted');

$controller->config = new FakeConfig(['payment_pumb_credit_test_callback_ips' => '194.44.66.16/28']);
$controller->request->server = ['REMOTE_ADDR' => '194.44.66.30'];
$check($invoke($controller, 'allowedIp', true), true, 'test callback setting uses same CIDR matcher');

echo 'checks=' . $checks . " result=ok network=0 database_writes=0\n";
}
