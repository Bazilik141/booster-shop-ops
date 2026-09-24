<?php
declare(strict_types=1);
namespace Opencart\System\Engine {
    class Controller { public $db; public $config; }
}
namespace {
    define('DB_PREFIX', 'fixture_');
    $root = dirname(__DIR__, 2) . '/work/pay002-final-audit/source';
    require $root . '/extension/pumb_credit/catalog/controller/payment/pumb_credit.php';
    $controller = new \Opencart\Catalog\Controller\Extension\PumbCredit\Payment\PumbCredit();
    $controller->db = new class {
        public function query(string $sql): object {
            if (strpos($sql, 'SELECT `name`,`quantity`,`price` FROM') !== 0) throw new \RuntimeException('unexpected_query');
            return (object)['rows' => [['name'=>'Synthetic product','quantity'=>1,'price'=>1000.0]]];
        }
    };
    $controller->config = new class { public function get(string $key): string { return 'synthetic'; } };
    $method = new \ReflectionMethod($controller, 'createPayload');
    $method->setAccessible(true);
    foreach ([1000.0, 850.0, 1050.0] as $total) {
        $payload = $method->invoke($controller, ['order_id'=>1,'telephone'=>'+380739991740','total'=>$total], 4);
        if ($payload['credit_request']['term'] !== 4) throw new \RuntimeException('term_changed');
        echo json_encode(['order_total'=>$total,'bank_amount'=>$payload['credit_request']['amount'],'matches_order'=>$total === (float)$payload['credit_request']['amount'],'term'=>4]) . PHP_EOL;
    }
    echo "source_reproduction=complete; bank_calls=0; db_fixture_only=yes\n";
}
