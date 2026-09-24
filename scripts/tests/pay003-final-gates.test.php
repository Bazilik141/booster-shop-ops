<?php
declare(strict_types=1);

namespace Opencart\System\Engine {
    class Controller {
        public mixed $config;
        public mixed $user;
    }
}

namespace {
final class FakeConfig {
    /** @param array<string,mixed> $values */
    public function __construct(private array $values) {}
    public function get(string $key): mixed { return $this->values[$key] ?? null; }
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
$controllerFile = $root . '/work/pay003-final-gates/candidate/extension/pumb_credit/admin/controller/payment/pumb_credit.php';
$catalogFile = $root . '/work/pay003-final-gates/candidate/extension/pumb_credit/catalog/controller/payment/pumb_credit.php';
$twigFile = $root . '/work/pay003-final-gates/candidate/admin-view/order_info.twig';
$orderModelFile = $root . '/work/pay003-final-gates/candidate/admin-model/order.php';
$creditTwigFile = $root . '/work/pay003-final-gates/candidate/catalog/view/template/checkout/credit.twig';
require $root . '/work/pay003/tooling/vendor/autoload.php';
require $controllerFile;

$checks = 0;
$ok = static function (bool $value, string $message) use (&$checks): void {
    $checks++;
    if (!$value) throw new \RuntimeException('FAIL ' . $message);
};
$invoke = static function (object $object, string $method, mixed ...$args): mixed {
    $reflection = new \ReflectionMethod($object, $method);
    $reflection->setAccessible(true);
    return $reflection->invoke($object, ...$args);
};

$controller = new \Opencart\Admin\Controller\Extension\PumbCredit\Payment\PumbCredit();
$controller->config = new FakeConfig([
    'payment_pumb_credit_api_base' => 'https://example.dts.fuib.com/api',
    'payment_pumb_credit_point_of_sale_code' => 'POS-TEST',
    'payment_pumb_credit_oauth_url' => 'https://example.dts.fuib.com/oauth/token',
    'payment_pumb_credit_test_mode' => 1,
]);
$controller->user = new FakeUser();
$endpoint = $invoke($controller, 'endpointIdentity');
$base = [
    'pumb_credit_transaction_id' => 1,
    'order_id' => 351,
    'cap_id' => '19040799',
    'state' => 'WAITING_CLIENT',
    'is_test' => 1,
    'requested_term' => 4,
    'agreement_number' => '',
    'payload' => json_encode(['pay003' => ['endpoint' => $endpoint], 'create' => ['request' => ['credit_request' => ['amount' => 720]]]]),
    'date_modified' => '2026-09-01 14:31:48',
];

foreach (['IN_PROGRESS', 'WAITING_CLIENT', 'WAITING_STORE_CONFIRM'] as $state) {
    $tx = $base; $tx['state'] = $state;
    $ok($invoke($controller, 'project', $tx)['can_cancel'] === true, 'cancel enabled for ' . $state);
}
foreach (['FUNDED', 'CANCELED_BY_CLIENT', 'CANCELED_BY_STORE', 'REJECTED', 'FAIL', 'REFUND_FINISHED'] as $state) {
    $tx = $base; $tx['state'] = $state;
    $ok($invoke($controller, 'project', $tx)['can_cancel'] === false, 'cancel disabled for ' . $state);
}

$admin = (string)file_get_contents($controllerFile);
$catalog = (string)file_get_contents($catalogFile);
$twig = (string)file_get_contents($twigFile);
$orderModel = (string)file_get_contents($orderModelFile);
$creditTwig = (string)file_get_contents($creditTwigFile);
$clientStart = strpos($twig, '(function($) {', strpos($twig, 'id="pay003-admin-card"'));
$clientEnd = $clientStart === false ? false : strpos($twig, '}(jQuery));', $clientStart);
$clientBlock = $clientStart === false || $clientEnd === false ? '' : substr($twig, $clientStart, $clientEnd - $clientStart);
$ok(substr_count($admin, "'cancel_reason' => 'CancelLead50'") >= 2, 'store cancel uses established bank contract');
$ok(substr_count($admin, "'flow_id' => \$flowId") === 3, 'safe flow id is returned by API helper');
$ok(strpos($admin, "'response_fields' => \$responseFields") !== false, 'safe response shape is available');
$ok(strpos($catalog, "applyOrderStatus(\$orderId, 'CREATE_FAILED', false)") !== false, 'create failure becomes visible');
$ok(strpos($catalog, "['CREATE_FAILED','CANCELED_BY_CLIENT'") !== false, 'create failure maps to configured failed status');
$ok(substr_count($orderModel, 'pumb_credit_transaction') === 2, 'default list and count use narrow PUMB lookup');
$ok(substr_count($orderModel, "'CREATE_FAILED'") === 2, 'only CREATE_FAILED drafts are surfaced');
$ok(substr_count($orderModel, "order_status_id` > '0' OR EXISTS") === 2, 'ordinary status-zero drafts remain hidden');
$ok(substr_count($twig, 'id="pay003-admin-cancel"') === 1, 'one admin cancel button');
$ok(strpos($twig, 'window.confirm') !== false, 'destructive confirmation remains');
$ok($clientBlock !== '' && strpos($clientBlock, '.html(') === false, 'bank response is not inserted as HTML');
$ok(strpos($creditTwig, 'Сплата частинами') !== false, 'requested customer label');
$ok(strpos($creditTwig, 'id="pay003-refresh"') === false, 'customer refresh button removed');

$loader = new \Twig\Loader\ArrayLoader(['order_info.twig' => $twig, 'credit.twig' => $creditTwig]);
$environment = new \Twig\Environment($loader);
foreach (['order_info.twig' => $twig, 'credit.twig' => $creditTwig] as $name => $source) {
    $environment->parse($environment->tokenize(new \Twig\Source($source, $name)));
}
$ok(true, 'both Twig candidates parse');

echo 'checks=' . $checks . " result=ok bank_calls=0 database_writes=0\n";
}
