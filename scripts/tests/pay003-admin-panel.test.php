<?php
declare(strict_types=1);

namespace Opencart\System\Engine {
    class Controller {
        public mixed $config;
        public mixed $user;
        public mixed $request;
        public mixed $response;
        public mixed $db;
        public mixed $session;
        public mixed $load;
        public mixed $url;
    }
}

namespace {
final class FakeConfig {
    /** @param array<string,mixed> $values */
    public function __construct(private array $values) {}
    public function get(string $key): mixed { return $this->values[$key] ?? null; }
    public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
}

final class FakeUser {
    public function hasPermission(string $kind, string $route): bool {
        return in_array($kind . ':' . $route, [
            'access:sale/order',
            'modify:sale/order',
            'modify:extension/pumb_credit/payment/pumb_credit',
        ], true);
    }
}

$root = dirname(__DIR__, 2);
$controllerFile = $root . '/work/pay003-admin-order/candidate/extension/pumb_credit/admin/controller/payment/pumb_credit.php';
$twigFile = $root . '/work/pay003-admin-order/candidate/admin-view/order_info.twig';
require $root . '/work/pay003/tooling/vendor/autoload.php';
require $controllerFile;

$checks = 0;
$ok = static function (bool $value, string $message) use (&$checks): void {
    $checks++;
    if (!$value) throw new \RuntimeException('FAIL ' . $message);
};

$controller = new \Opencart\Admin\Controller\Extension\PumbCredit\Payment\PumbCredit();
$controller->config = new FakeConfig([
    'payment_pumb_credit_api_base' => 'https://example.dts.fuib.com/api',
    'payment_pumb_credit_point_of_sale_code' => 'POS-TEST',
    'payment_pumb_credit_oauth_url' => 'https://example.dts.fuib.com/oauth/token',
    'payment_pumb_credit_test_mode' => 1,
]);
$controller->user = new FakeUser();

$invoke = static function (object $object, string $method, mixed ...$args): mixed {
    $reflection = new \ReflectionMethod($object, $method);
    $reflection->setAccessible(true);
    return $reflection->invoke($object, ...$args);
};

$endpoint = $invoke($controller, 'endpointIdentity');
$payload = [
    'pay003' => ['endpoint' => $endpoint, 'mode' => 1],
    'create' => ['request' => ['credit_request' => ['amount' => 720]]],
];
$tx = [
    'pumb_credit_transaction_id' => 10,
    'order_id' => 341,
    'cap_id' => '19040764',
    'state' => 'WAITING_STORE_CONFIRM',
    'is_test' => 1,
    'requested_term' => 4,
    'agreement_number' => 'SECRET-AGREEMENT',
    'payload' => json_encode($payload),
    'date_modified' => '2026-09-01 10:30:55',
];

$project = $invoke($controller, 'project', $tx);
$ok($project['found'] === true, 'panel finds transaction');
$ok($project['is_test'] === true, 'TEST badge');
$ok($project['term'] === 4, 'requested term');
$ok($project['credit_amount'] === '720.00', 'canonical refund amount');
$ok($project['can_refresh'] === true, 'refresh enabled');
$ok($project['can_ship'] === true, 'shipment enabled only at waiting store');
$ok($project['can_refund'] === false, 'refund disabled before funded');
$ok(!array_key_exists('agreement_number', $project), 'agreement number not exposed');
$ok(!array_key_exists('payload', $project), 'payload not exposed');

$funded = $tx;
$funded['state'] = 'FUNDED';
$fundedProject = $invoke($controller, 'project', $funded);
$ok($fundedProject['can_ship'] === false, 'shipment disabled after funded');
$ok($fundedProject['can_refund'] === true, 'refund enabled at funded');

$wrongMode = $tx;
$wrongMode['is_test'] = 0;
$wrongModeProject = $invoke($controller, 'project', $wrongMode);
$ok($wrongModeProject['environment_matches'] === false, 'environment mismatch detected');
$ok($wrongModeProject['can_refresh'] === false && $wrongModeProject['can_ship'] === false, 'bank actions blocked across environments');

$noEndpoint = $tx;
$noEndpoint['payload'] = json_encode(['create' => $payload['create']]);
$ok($invoke($controller, 'project', $noEndpoint)['can_refresh'] === false, 'historical row without endpoint stays read-only');

$badAmount = $tx;
$badAmount['payload'] = json_encode(['pay003' => $payload['pay003'], 'create' => ['request' => ['credit_request' => ['amount' => 12.345]]]]);
$ok($invoke($controller, 'project', $badAmount)['credit_amount'] === null, 'non-cent amount rejected');

$states = $invoke($controller, 'allowedStates');
$ok(in_array('REFUND_FINISHED', $states, true) && !in_array('SURPRISE_SUCCESS', $states, true), 'state allowlist');
$ok($invoke($controller, 'stateLabel', 'SURPRISE_SUCCESS') === 'Невідомий стан — потрібна перевірка', 'unknown state is not success');

$twig = file_get_contents($twigFile);
$ok(is_string($twig) && substr_count($twig, 'id="pay003-admin-card"') === 1, 'single panel marker');
$ok(substr_count($twig, 'payment/pumb_credit.refreshOrderStatus') === 1, 'single refresh endpoint');
$ok(substr_count($twig, 'payment/pumb_credit.confirmOrderShipment') === 1, 'single shipment endpoint');
$ok(substr_count($twig, 'payment/pumb_credit.refundOrder') === 1, 'single refund endpoint');
$ok(strpos($twig, 'setTimeout') === false && strpos($twig, '!important') === false, 'no timer or override stacking');

$loader = new \Twig\Loader\ArrayLoader(['order_info.twig' => $twig]);
$environment = new \Twig\Environment($loader);
$environment->parse($environment->tokenize(new \Twig\Source($twig, 'order_info.twig')));
$ok(true, 'Twig parses');

echo 'checks=' . $checks . " result=ok bank_calls=0 database_writes=0\n";
}
