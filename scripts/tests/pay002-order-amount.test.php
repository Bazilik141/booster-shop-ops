<?php
declare(strict_types=1);
namespace Opencart\System\Engine {
    class Controller { public $db; public $config; public $request; public $session; public $response; public $load; public $language; public $model_checkout_order; }
}
namespace Opencart\Catalog\Controller\Extension\PumbCredit\Payment {
    // No real cURL: exercise the original private api() via namespaced doubles.
    function curl_init(string $url): object { return (object)['url'=>$url,'options'=>[]]; }
    function curl_setopt_array(object $c, array $options): void { $c->options += $options; }
    function curl_setopt(object $c, int $key, $value): void { $c->options[$key] = $value; }
    function curl_exec(object $c): string {
        $GLOBALS['api_calls'][]=$c->url;
        if (strpos($c->url,'auth.invalid') !== false) return '{"access_token":"synthetic","expires_in":300}';
        $GLOBALS['bank_payload']=json_decode($c->options[CURLOPT_POSTFIELDS],true);
        return '{"id":"synthetic-cap"}';
    }
    function curl_getinfo(object $c, int $key): int { return strpos($c->url,'auth.invalid') !== false ? 200 : 201; }
    function curl_close(object $c): void {}
    function is_file(string $path): bool { return false; }
    function file_put_contents(string $path, string $body): int { return strlen($body); }
}
namespace {
    if (extension_loaded('curl')) { fwrite(STDERR,"Run php -n to guarantee no cURL\n");exit(1); }
    foreach (['CURLOPT_POST','CURLOPT_POSTFIELDS','CURLOPT_RETURNTRANSFER','CURLOPT_HTTPHEADER','CURLOPT_TIMEOUT','CURLOPT_CUSTOMREQUEST','CURLINFO_RESPONSE_CODE'] as $i=>$name) define($name,$i+1);
    define('DB_PREFIX','fixture_');
    $source = $argv[1] ?? dirname(__DIR__,2).'/work/pay002-order-amount/candidate/extension/pumb_credit/catalog/controller/payment/pumb_credit.php';
    require $source;
    function check(bool $ok, string $name): void { if (!$ok) throw new \RuntimeException($name); }
    class AmountDb {
        public array $products = []; public array $order = []; public array $tx = []; public int $writes = 0; public int $productReads = 0;
        public function escape(string $s): string { return addslashes($s); }
        public function query(string $sql): object {
            if (strpos($sql,'SELECT `name`,`quantity`,`price`')===0) { $this->productReads++; return (object)['rows'=>$this->products]; }
            if (strpos($sql,'SELECT * FROM `fixture_order`')===0) return (object)['row'=>$this->order];
            if (strpos($sql,'SELECT * FROM `fixture_pumb_credit_transaction`')===0) return (object)['row'=>$this->tx];
            if (strpos($sql,'INSERT INTO `fixture_pumb_credit_transaction`')===0) {
                $this->writes++;
                if (strpos($sql,"state = 'CREATING'")!==false) {
                    preg_match("/payload = '(.*?)', date_added/s",$sql,$m);
                    $this->tx=['payload'=>stripslashes($m[1]),'state'=>'CREATING','cap_id'=>'PENDING-OC-1'];
                } else $this->tx=['payload'=>'{}','state'=>'WAITING_CLIENT','cap_id'=>'synthetic-cap'];
                return (object)[];
            }
            throw new \RuntimeException('unexpected_sql');
        }
    }
    function controller(array $products, $total=850): object {
        $c=new \Opencart\Catalog\Controller\Extension\PumbCredit\Payment\PumbCredit();
        $c->db=new AmountDb();$c->db->products=$products;$c->db->order=['order_id'=>1,'total'=>$total,'telephone'=>'+380739991740'];
        $c->config=new class {
            public array $values=['payment_pumb_credit_status'=>1,'payment_pumb_credit_public'=>0,'payment_pumb_credit_api_base'=>'https://bank.invalid','payment_pumb_credit_oauth_url'=>'https://auth.invalid','payment_pumb_credit_oauth_username'=>'synthetic','payment_pumb_credit_oauth_password'=>'synthetic','payment_pumb_credit_point_of_sale_code'=>'synthetic','payment_pumb_credit_partner_name'=>'synthetic','payment_pumb_credit_min_total'=>500,'payment_pumb_credit_max_total'=>500000,'payment_pumb_credit_terms'=>'[3,4,5]','payment_pumb_credit_test_mode'=>1,'payment_pumb_credit_status_waiting_client'=>17];
            public function get(string $k) {return $this->values[$k]??null;}
        };
        $c->session=(object)['data'=>['order_id'=>1,'pay002_pumb_preview'=>true]];
        $c->request=(object)['post'=>['term'=>'4'],'get'=>[]];
        $c->response=new class {public string $body=''; public function addHeader(string $s): void {}public function setOutput(string $s): void {$this->body=$s;}};
        $c->load=new class {public function language(string $s): void {}public function model(string $s): void {}};
        $c->language=new class {public function get(string $s): string{return $s;}};
        $c->model_checkout_order=new class {public function addHistory(...$args): void {}};
        return $c;
    }
    function product(string $name,$price,$quantity=1): array{return compact('name','price','quantity');}
    function payload(object $c,int $term=4): array { $m=new \ReflectionMethod($c,'createPayload');$m->setAccessible(true);return $m->invoke($c,$c->db->order,$term); }
    function verify(array $products,$total,int $cents,int $term=4): array {
        $c=controller($products,$total);$p=payload($c,$term);
        check((int)round($p['credit_request']['amount']*100)===$cents,'canonical_amount');
        check((int)round($p['invoices'][0]['total_amount']*100)===$cents,'invoice_total');
        check($p['credit_request']['term']===$term,'term');
        $counts=[];$sum=0;
        foreach($p['invoices'][0]['goods'] as $g){
            check(is_int($g['count'])&&$g['count']>0&&$g['amount']>=0,'nonnegative_goods');
            $unit=(int)round($g['amount']*100);check(abs($g['amount']*100-$unit)<0.00001,'cent_precision');
            $sum+=$unit*$g['count'];$counts[$g['name']]=($counts[$g['name']]??0)+$g['count'];
        }
        check($sum===$cents,'goods_sum');
        $original=[];foreach($products as $g)$original[$g['name']]=($original[$g['name']]??0)+(int)$g['quantity'];
        check($original===$counts,'names_and_quantities');
        check(count($p['invoices'][0]['goods'])<=2*count($products),'bounded_splits');
        check($p===payload($c,$term),'deterministic');
        return $p;
    }
    foreach([3,4,5] as $term)verify([product('A','1000.0000')],'850.0000',85000,$term);
    verify([product('A',1000)],1000,100000);
    verify([product('A',1000)],1050,105000);
    verify([product('A',1000)],'850.0050',85001);
    verify([product('A','333.3333',3)],'999.9999',100000);
    $split=verify([product('A',400,3)],1000,100000);
    check($split['invoices'][0]['goods']===[['name'=>'A','count'=>2,'amount'=>333.33],['name'=>'A','count'=>1,'amount'=>333.34]],'unit_split');
    $multi=verify([product('A',1000),product('B',500,2)],'1700.00',170000);
    check($multi['invoices'][0]['goods'][0]['amount']==850,'proportional_discount');
    verify([product('A',200),product('A',300)],'500.01',50001);
    verify([product('A',500000)],500000,50000000);
    $gift=verify([product('A',1000),product('Gift',0,2)],850,85000);
    check($gift['invoices'][0]['goods'][1]['amount']==0,'gift_stays_free');
    mt_srand(20260831);
    for($n=0;$n<600;$n++){
        $goods=[];for($i=0,$len=mt_rand(1,12);$i<$len;$i++)$goods[]=product('P'.$i,(string)mt_rand(10,2000),mt_rand(1,8));
        $target=mt_rand(50000,50000000);verify($goods,number_format($target/100,2,'.',''),$target);
    }
    $bad=[ [[],850], [[product('A',0)],850], [[product('A',-1)],850], [[product('A',10,0)],850], [[product('A',10,'1.5')],850], [[product('A',10)],NAN], [[product('A',10)],INF], [[product('A',10)],'1e3'], [[product('A',10)],'1.00001'], [[product('',10)],850], [[product('A',1,100000)],500], [[product('A','999999999.9999',999999999)],500000] ];
    foreach($bad as [$goods,$total]){ $rejected=false;try{payload(controller($goods,$total));}catch(\Throwable $e){$rejected=true;}check($rejected,'invalid_payload_rejected'); }
    // Confirm lifecycle: malformed input cannot create a reservation or call API.
    $GLOBALS['api_calls']=[];$c=controller([product('A',0)]);$c->confirm();
    check($c->db->writes===0&&count($GLOBALS['api_calls'])===0&&isset(json_decode($c->response->body,true)['error']),'fail_before_reservation');
    // Valid discounted order preserves reservation/create/upsert flow.
    $c=controller([product('A',1000)],850);$c->confirm();
    check($c->db->writes===2&&count($GLOBALS['api_calls'])===2,'create_flow');
    check($GLOBALS['bank_payload']['credit_request']['amount']==850&&$GLOBALS['bank_payload']['credit_request']['term']===4,'bank_payload');
    $reads=$c->db->productReads;$writes=$c->db->writes;$calls=count($GLOBALS['api_calls']);$c->confirm();
    check($reads===$c->db->productReads&&$writes===$c->db->writes&&$calls===count($GLOBALS['api_calls']),'repeat_no_reprice_no_bank');
    foreach(['CREATING','CREATE_FAILED','FUNDED'] as $state){$c=controller([]);$c->db->tx=['state'=>$state,'cap_id'=>'synthetic-cap'];$c->confirm();check($c->db->productReads===0&&$c->db->writes===0&&count($GLOBALS['api_calls'])===$calls,'existing_unchanged');}
    foreach([499.99,500000.01] as $total){$c=controller([product('A',1000)],$total);$c->confirm();check($c->db->writes===0&&$c->db->productReads===0,'bounds_unchanged');}
    $c=controller([product('A',1000)]);$c->request->post['term']='6';$c->confirm();check($c->db->writes===0&&$c->db->productReads===0,'invalid_term');
    $c=controller([product('A',1000)]);$c->session->data['pay002_pumb_preview']=false;$c->confirm();check($c->db->productReads===0&&$c->db->writes===0,'preview_gate');
    echo "amount_tests=ok randomized=600 invalid=12 confirm_flow=ok real_bank_calls=0\n";
}
