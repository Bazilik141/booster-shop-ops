<?php
declare(strict_types=1);
namespace Opencart\System\Engine {
    class Model { protected $r; public function __construct($r) {$this->r=$r;} public function __get($k) {return $this->r->$k;} }
    class Controller extends Model {}
}
namespace {
    if (extension_loaded('curl')) throw new \RuntimeException('Use php -n; no network allowed');
    define('DB_PREFIX','fixture_');
    $base=$argv[1] ?? dirname(__DIR__,2).'/work/pay003/candidate';
    $cache=dirname(__DIR__,2).'/work/pay003/test-cache-'.bin2hex(random_bytes(4)).'/'; mkdir($cache,0755,true); define('DIR_CACHE',$cache);
    require $base.'/catalog/model/checkout/credit.php';
    require $base.'/catalog/controller/checkout/credit.php';
    require $base.'/catalog/controller/checkout/success.php';
    require $base.'/extension/pumb_credit/catalog/controller/payment/pumb_credit.php';
    require $base.'/extension/mono_chast/catalog/controller/payment/mono_chast.php';
    $checks=0;
    function check($ok,string $name): void {global $checks;$checks++;if(!$ok)throw new \RuntimeException($name);}
    class Db {
        public array $order=[],$rows=[],$queries=[]; public int $affected=0; public bool $race=false;
        public function escape(string $s): string {return addslashes($s);}
        public function countAffected(): int {return $this->affected;}
        public function query(string $sql): object {
            $this->queries[]=$sql; $this->affected=0;
            if(str_starts_with($sql,'SELECT order_id, customer_id')) return (object)['row'=>$this->order];
            if(str_starts_with($sql,'SELECT * FROM `fixture_order`')) return (object)['row'=>$this->order];
            if(str_starts_with($sql,'SELECT * FROM `fixture_') && strpos($sql,'transaction`')!==false) return (object)['rows'=>$this->rows,'row'=>$this->rows[0]??[]];
            if(str_starts_with($sql,'UPDATE `fixture_')) {
                if($this->race)return (object)[];
                $this->affected=1;
                if(preg_match("/SET state='([^']*)'/",$sql,$m))$this->rows[0]['state']=$m[1];
                if(preg_match("/order_sub_state='([^']*)'/",$sql,$m))$this->rows[0]['order_sub_state']=$m[1];
                return (object)[];
            }
            if(str_starts_with($sql,'INSERT INTO `fixture_mono_chast_event`'))return (object)[];
            throw new \RuntimeException('Unexpected SQL: '.substr($sql,0,100));
        }
    }
    function setup(string $provider='pumb_credit'): object {
        $r=new \stdClass();
        $r->config=new class {public array $values=['config_store_id'=>0,'config_language'=>'uk-ua','config_currency'=>'UAH','payment_pumb_credit_status'=>1,'payment_pumb_credit_test_mode'=>1,'payment_pumb_credit_api_base'=>'https://bank.invalid','payment_mono_chast_api_base'=>'https://mono.invalid','payment_mono_chast_status'=>1];public function get($k){return $this->values[$k]??null;}};
        $r->customer=new class {public int $id=0;public function isLogged(){return $this->id>0;}public function getId(){return $this->id;}};
        $r->session=(object)['data'=>['order_id'=>123,'customer_token'=>'fixture']];
        $r->request=(object)['get'=>['order_id'=>'123'],'post'=>[],'cookie'=>[],'server'=>['REQUEST_METHOD'=>'GET']];
        $r->response=new class {public string $output='',$redirect='';public array $headers=[];public function addHeader($s){$this->headers[]=$s;}public function setOutput($s){$this->output=$s;}public function redirect($s){$this->redirect=$s;}};
        $r->url=new class {public function link($route,$args='',$secure=false){return 'https://shop.invalid/index.php?route='.$route.'&'.$args;}};
        $r->cart=new class {public int $cleared=0;public array $products=[['product_id'=>1,'quantity'=>1]];public function getProducts(){return $this->products;}public function clear(){$this->cleared++;$this->products=[];}};
        $r->db=new Db();$r->db->order=['order_id'=>123,'customer_id'=>0,'store_id'=>0,'payment_method'=>json_encode(['code'=>$provider.'.'.$provider.'_4'])];
        $r->db->rows=[[$provider.'_transaction_id'=>1,'order_id'=>123,'state'=>$provider==='pumb_credit'?'WAITING_CLIENT':'IN_PROCESS','order_sub_state'=>'WAITING_FOR_CLIENT','store_order_id'=>'OC-123','mono_order_id'=>'synthetic-mono-'.bin2hex(random_bytes(4)),'cap_id'=>'synthetic-cap-'.bin2hex(random_bytes(4)),'is_test'=>1,'payload'=>'{"create":{"request":{"term":4}}}','date_modified'=>'2026-08-31 20:04:46']];
        $r->model_checkout_credit=new \Opencart\Catalog\Model\Checkout\Credit($r);
        $r->model_checkout_order=new class($r) {private $r;public int $histories=0;public function __construct($r){$this->r=$r;}public function getOrder($id){return $this->r->db->order;}public function getProducts($id){return [];}public function getTotals($id){return [];}public function addHistory(...$args){$this->histories++;}};
        $r->language=new class {public function get($key){return $key;}};
        $r->document=new class {public function setTitle($s){}public function addStyle($s){}};
        $r->currency=new class {public function format(...$args){return 'synthetic';}};
        $r->load=new class($r) {
            public int $reads=0,$applies=0;public array $viewData=[];public array $bank=['http'=>200,'body'=>['state'=>'WAITING_STORE_CONFIRM']];private $r;
            public function __construct($r){$this->r=$r;}
            public function model($s){}public function language($s){}
            public function view($path,$data){$this->viewData=$data;return '<synthetic-view/>';}
            public function controller($path,...$args){if(str_ends_with($path,'.pay003Read')){$this->reads++;return $this->bank;}if(str_ends_with($path,'.pay003Apply')){$this->applies++;return null;}return '';}
        };
        return $r;
    }
    $r=setup();$m=$r->model_checkout_credit;
    check((bool)$m->context(123),'active guest');
    $m->remember(123);unset($r->session->data['order_id']);check((bool)$m->context(123),'guest recovery');
    $r->session->data['pay003_orders'][123]['expires']=time()-1;check(!$m->context(123),'expired guest denied');
    $r->customer->id=9;$r->db->order['customer_id']=9;check((bool)$m->context(123),'logged owner recovery');
    $r->customer->id=10;check(!$m->context(123),'foreign customer denied');
    $r->customer->id=9;$r->db->order['store_id']=1;check(!$m->context(123),'foreign store denied');
    $r->db->order['store_id']=0;$r->db->order['payment_method']='{"code":"pumb_credit.mono_chast_4"}';check(!$m->context(123),'mixed provider denied');
    $r=setup();$m=$r->model_checkout_credit;$c=$m->context(123);
    $r->db->rows[]=array_merge($r->db->rows[0],['is_test'=>0,'pumb_credit_transaction_id'=>2]);
    check(!$m->transaction($c),'ambiguous environments denied');$m->remember(123);$c=$m->context(123);check($m->transaction($c)['is_test']===1,'remembered environment');
    foreach(['WAITING_CLIENT','IN_PROGRESS']as$s)check($m->present('pumb_credit',['state'=>$s])['poll'],'pumb waiting '.$s);
    foreach(['FUNDED','WAITING_STORE_CONFIRM']as$s)check($m->present('pumb_credit',['state'=>$s])['confirmed'],'pumb confirmed '.$s);
    foreach(['CLIENT_NOT_FOUND','REJECTED','OVER_LIMIT','NO_LIMIT','IDENTIFICATION_FAILED','PUSH_TIMEOUT','FAIL_OTP','CONFIRM_TIME_EXPIRED','CANCELED_BY_CLIENT','CANCELED_BY_STORE','FAIL']as$s)check($m->present('pumb_credit',['state'=>$s])['kind']==='failed','pumb negative '.$s);
    foreach(['CREATE_FAILED','<script>','FOUNDED','']as$s)check($m->present('pumb_credit',['state'=>$s])['kind']==='review','pumb unknown '.$s);
    check($m->present('pumb_credit',['state'=>'CREATING'])['kind']==='creating','creating DB polling');
    check($m->present('pumb_credit',['state'=>'REFUND_FINISHED'])['kind']==='returned','pumb returned');
    foreach([['IN_PROCESS','WAITING_FOR_CLIENT','waiting'],['IN_PROCESS','WAITING_FOR_STORE_CONFIRM','confirmed'],['SUCCESS','ACTIVE','confirmed'],['SUCCESS','DONE','confirmed'],['SUCCESS','RETURNED','returned'],['SUCCESS','???','review'],['FAIL','REJECTED','failed']]as[$s,$sub,$kind])check($m->present('mono_chast',['state'=>$s,'order_sub_state'=>$sub])['kind']===$kind,'mono '.$s.$sub);
    $r=setup();$m=$r->model_checkout_credit;$m->remember(123);$c=$m->context(123);$tx=$m->transaction($c);
    check($m->refresh($c,$tx),'fallback succeeds');check($r->load->reads===1&&$r->load->applies===1,'fallback single read/apply');
    check(strpos(implode('\n',$r->db->queries),'MD5(COALESCE(payload')!==false,'callback CAS guard');
    $r->db->rows[0]=$tx;check(!$m->refresh($c,$tx),'throttle second request');check($r->load->reads===1,'throttle no API');
    $lock=glob(DIR_CACHE.'*.lock')[0];$stamp=file_get_contents($lock);check(!$m->refresh($c,$tx)&&file_get_contents($lock)===$stamp,'throttled read does not extend interval');
    $r->config->values['payment_pumb_credit_test_mode']=0;check(!$m->refresh($c,$tx),'mode drift no API');
    $r->config->values['payment_pumb_credit_test_mode']=1;$r->config->values['payment_pumb_credit_api_base']='https://production.invalid';check(!$m->refresh($c,$tx),'endpoint drift no API');
    $r=setup();$m=$r->model_checkout_credit;$m->remember(123);$c=$m->context(123);$r->db->race=true;check(!$m->refresh($c,$m->transaction($c))&&$r->load->applies===0,'callback wins CAS race');
    $r=setup();$m=$r->model_checkout_credit;$m->remember(123);$c=$m->context(123);$r->load->bank=['http'=>503,'body'=>[]];
    try{$m->refresh($c,$m->transaction($c));check(false,'failure should throw');}catch(\RuntimeException $e){check($e->getMessage()==='bank_state_unavailable','safe bank failure');}
    check(!$m->refresh($c,$m->transaction($c))&&$r->load->reads===1,'failure throttled');
    foreach([false,true]as$changed){$r=setup();$m=$r->model_checkout_credit;$m->remember(123);$c=$m->context(123);if($changed)$r->cart->products[]=['product_id'=>2,'quantity'=>1];$m->detach($c,$m->transaction($c));check($r->cart->cleared===($changed?0:1),'cart snapshot '.$changed);check(!isset($r->session->data['order_id'])&&(bool)$m->context(123),'detached guest recovers');}
    $r=setup();$controller=new \Opencart\Catalog\Controller\Checkout\Credit($r);$controller->index();check(isset($r->load->viewData['csrf']),'page CSRF issued');
    $r->request->server['REQUEST_METHOD']='POST';$controller->state();check(strpos($r->response->output,'access_denied')!==false&&$r->load->reads===0,'POST without CSRF refused');
    $r->request->server['REQUEST_METHOD']='GET';$controller->state();$json=json_decode($r->response->output,true);check($json['kind']==='waiting'&&$r->load->reads===0,'GET DB only');check(!isset($json['cap_id'])&&!isset($json['payload']),'JSON redacted');
    $controller->complete();check(strpos($r->response->redirect,'checkout/credit')!==false,'pending cannot complete');
    $r->db->rows[0]['state']='WAITING_STORE_CONFIRM';$controller->complete();check(strpos($r->response->redirect,'credit_order_id=123')!==false,'confirmed completes with original order');
    $r=setup();$r->model_checkout_credit->remember(123);$r->db->rows[0]['state']='CREATING';$r->db->rows[0]['cap_id']='PENDING-OC-123';$controller=new \Opencart\Catalog\Controller\Checkout\Credit($r);$controller->index();check(isset($r->session->data['order_id'])&&$r->cart->cleared===0,'creating keeps original checkout');
    $r->db->rows[0]['state']='WAITING_STORE_CONFIRM';$r->db->rows[0]['cap_id']='synthetic-ready';$controller->complete();check(!isset($r->session->data['order_id'])&&$r->cart->cleared===1,'creation race complete detaches once');
    $r=setup();$r->model_checkout_credit->remember(123);$controller=new \Opencart\Catalog\Controller\Checkout\Credit($r);$controller->index();$r->request->server['REQUEST_METHOD']='POST';$r->request->post['csrf']=$r->session->data['pay003_csrf'];$controller->state();check($r->load->reads===1&&json_decode($r->response->output,true)['confirmed'],'valid CSRF read and status refresh');
    $r=setup();$success=new \Opencart\Catalog\Controller\Checkout\Success($r);$success->index();check(strpos($r->response->redirect,'checkout/credit')!==false&&$r->cart->cleared===0,'direct success pending gated');
    $r=setup();$r->model_checkout_credit->remember(123);$r->session->data['order_id']=999;$r->session->data['coupon']='NEW';$r->session->data['checkout001_first15_offer_pending']=true;$r->db->rows[0]['state']='FUNDED';$r->request->get=['credit_order_id'=>'123'];
    $success=new \Opencart\Catalog\Controller\Checkout\Success($r);$success->index();check($r->load->viewData['order_data']['order_id']===123,'success original order');check($r->cart->cleared===0&&$r->session->data['order_id']===999&&$r->session->data['coupon']==='NEW'&&$r->session->data['checkout001_first15_offer_pending']===true,'historical recovery preserves new checkout');
    $r=setup();$r->request->get=['credit_order_id'=>'123'];unset($r->session->data['order_id']);$success=new \Opencart\Catalog\Controller\Checkout\Success($r);$success->index();check(in_array('HTTP/1.1 404 Not Found',$r->response->headers,true)&&$r->cart->cleared===0,'success unauthorized denied');
    foreach(['hutko.hutko','cod.cod','bank_transfer.bank_transfer']as$code){$r=setup();$r->db->order['payment_method']=json_encode(['code'=>$code]);$success=new \Opencart\Catalog\Controller\Checkout\Success($r);$success->index();check($r->cart->cleared===1&&$r->response->redirect==='','non-credit preserved '.$code);}
    foreach(['PumbCredit','MonoChast']as$class){$p=$class==='PumbCredit'?'pumb_credit':'mono_chast';$r=setup($p);$fq='Opencart\\Catalog\\Controller\\Extension\\'.$class.'\\Payment\\'.$class;$provider=new $fq($r);check($provider->pay003Read()===[],'public internal read denied '.$p);$provider->pay003Apply();check($r->model_checkout_order->histories===0,'public internal apply denied '.$p);}
    foreach(['WAITING_CLIENT','CREATING','CREATE_FAILED','REJECTED']as$state){
        $r=setup();$r->db->rows[0]['state']=$state;$r->db->order+=['total'=>850,'telephone'=>'+380000000000'];
        $r->config->values+=['payment_pumb_credit_public'=>0,'payment_pumb_credit_oauth_url'=>'https://auth.invalid','payment_pumb_credit_oauth_username'=>'synthetic','payment_pumb_credit_oauth_password'=>'synthetic','payment_pumb_credit_point_of_sale_code'=>'synthetic','payment_pumb_credit_partner_name'=>'synthetic','payment_pumb_credit_terms'=>'[3,4,5]'];
        $r->session->data['pay002_pumb_preview']=true;$r->request->post['term']='4';
        $p=new \Opencart\Catalog\Controller\Extension\PumbCredit\Payment\PumbCredit($r);$p->confirm();$json=json_decode($r->response->output,true);
        check(isset($json['redirect'])&&strpos($json['redirect'],'checkout/credit')!==false,'existing PUMB handoff '.$state);
    }
    foreach(['IN_PROCESS','CREATE_FAILED','FAIL']as$state){
        $r=setup('mono_chast');$r->db->rows[0]['state']=$state;$r->db->order+=['total'=>850,'telephone'=>'+380000000000'];
        $r->config->values+=['payment_mono_chast_store_id'=>'synthetic','payment_mono_chast_store_secret'=>'synthetic'];
        $p=new \Opencart\Catalog\Controller\Extension\MonoChast\Payment\MonoChast($r);$p->confirm();$json=json_decode($r->response->output,true);
        check(isset($json['redirect'])&&strpos($json['redirect'],'checkout/credit')!==false,'existing Mono handoff '.$state);
    }
    // Preserve the amount fix in the actual newly generated PUMB controller.
    $before=file_get_contents(dirname(__DIR__,2).'/work/pay003/source/extension/pumb_credit/catalog/controller/payment/pumb_credit.php');$after=file_get_contents($base.'/extension/pumb_credit/catalog/controller/payment/pumb_credit.php');
    $slice=static function($s){return substr($s,$start=strpos($s,'    private function createPayload('),strpos($s,'    private function oauthToken(')-$start);};
    check($slice($before)===$slice($after),'PAY-002 amount methods byte-identical');
    echo json_encode(['checks'=>$checks,'result'=>'ok','real_bank_calls'=>0,'real_db_calls'=>0,'cache'=>$cache],JSON_UNESCAPED_SLASHES)."\n";
}
