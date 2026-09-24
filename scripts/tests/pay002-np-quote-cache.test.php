<?php
declare(strict_types=1);
namespace Opencart\System\Engine {
    class Model {
        public array $services = [];
        public function __get($name) { return $this->services[$name]; }
    }
}
namespace {
    use Opencart\System\Library\Pintanovaposhta\PintaNovaPoshtaApi as Api;
    define('DIR_EXTENSION', __DIR__ . '/fixtures/pay002-np/');
    define('VERSION', '4.0.2.3');
    require ($argv[1] ?? '') . '/extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php';
    class Stub {
        private array $f;
        public function __construct(array $f) { $this->f=$f; }
        public function __call($name,$args) { return ($this->f[$name])(...$args); }
    }
    class QuoteModel extends \Opencart\Catalog\Model\Extension\PintaNovaPoshtaCod\Shipping\PintaNovaPoshta {
        public bool $doors=false;
        public function isCorrectWarehouseAddress($address) { return !$this->doors; }
        public function isCorrectDoorsAddress($address) { return $this->doors; }
    }
    function check($ok,$label) { if (!$ok) throw new \RuntimeException($label); }
    function pass($label) { echo 'PASS ' . $label . PHP_EOL; }
    $cfg=['shipping_pinta_nova_poshta_use_api_price'=>true,'shipping_pinta_nova_poshta_api_key'=>'fixture-key-a',
        'shipping_pinta_nova_poshta_sender_city'=>'Sender','shipping_pinta_nova_poshta_sender_service_from'=>'Warehouse',
        'config_currency'=>'UAH','config_store_id'=>0,'shipping_pinta_nova_poshta_free_from'=>2000];
    $product=['shipping'=>true,'weight'=>1,'quantity'=>1,'weight_class_id'=>1,'length'=>10,'width'=>8,'height'=>3,'length_class_id'=>1];
    $payable=1000; $declared=1000; $uahRate=1; $headers=[];
    $session=(object)['data'=>['currency'=>'UAH']];
    $model=new QuoteModel();
    $model->services=[
        'config'=>new Stub(['get'=>function($key)use(&$cfg){return $cfg[$key]??null;}]),
        'session'=>$session,
        'load'=>new Stub(['model'=>fn($r)=>null,'language'=>fn($r)=>null]),
        'response'=>new Stub(['addHeader'=>function($s)use(&$headers){$headers[]=$s;}]),
        'cart'=>new Stub(['getTotal'=>function()use(&$declared){return $declared;},'getProducts'=>function()use(&$product){return [$product];},'getTaxes'=>fn()=>[]]),
        'model_localisation_currency'=>new Stub(['getCurrencyByCode'=>function($c)use(&$uahRate){return ['value'=>$uahRate];}]),
        'model_extension_PintaNovaPoshtaCod_module_city'=>new Stub(['getByName'=>fn($s)=>['ref'=>'ref-'.$s]]),
        'model_extension_PintaNovaPoshtaCod_module_area'=>new Stub(['getByName'=>fn($s)=>['ref'=>'area'],'getZoneIdByRef'=>fn($s)=>1]),
        'model_checkout_booster_coupon'=>new Stub(['prepareCouponTotal'=>fn()=>null]),
        'model_checkout_cart'=>(object)['getTotals'=>function(&$totals,&$taxes,&$total)use(&$payable){$total=$payable;}],
        'weight'=>new Stub(['convert'=>fn($v,$from,$to)=>$v]),
        'length'=>new Stub(['convert'=>fn($v,$from,$to)=>$v]),
        'currency'=>new Stub(['has'=>fn($v)=>true,'convert'=>fn($v,$from,$to)=>$v,'format'=>fn($v,$c)=>(string)$v.' '.$c])
    ];
    $addr=['city'=>'Recipient','address_1'=>'Відділення 1','zone_id'=>'1'];
    $quote=$model->getQuote($addr);
    check(Api::$calls===1 && $quote['quote']['warehouse']['booster_display_text']==='95 UAH','cold paid quote');
    check($quote['quote']['warehouse']['cost']===0.0 && $quote['quote']['warehouse']['tax_class_id']===0,'display-only cost preserved');
    $again=$model->getQuote($addr);
    check($again===$quote && Api::$calls===1 && Api::$constructed===1,'identical quote cache hit');
    pass('cold paid quote unchanged; repeat identical without API/client construction');
    $payable=2000;
    $free=$model->getQuote($addr);
    check(Api::$calls===1 && $free['quote']['warehouse']['booster_display_text']==='За наш кошт','free shipping must skip API');
    $model->doors=true;
    $free=$model->getQuote($addr);
    check(Api::$calls===1 && $free['quote']['doors']['booster_display_text']==='За наш кошт','courier free');
    $payable=1999;
    $paid=$model->getQuote($addr);
    check(Api::$calls===2 && $paid['quote']['doors']['booster_display_text']==='95 UAH','discount below free threshold must request tariff');
    pass('warehouse/courier free skip; coupon-adjusted threshold remains authoritative');
    $model->doors=false;
    foreach (['city','weight','length','quantity','cost','key','store','currency'] as $change) {
        $before=Api::$calls;
        if($change==='city')$addr['city']='Other';
        if($change==='weight')$product['weight']=2;
        if($change==='length')$product['length']=15;
        if($change==='quantity')$product['quantity']=2;
        if($change==='cost')$declared=1200;
        if($change==='key')$cfg['shipping_pinta_nova_poshta_api_key']='fixture-key-b';
        if($change==='store')$cfg['config_store_id']=1;
        if($change==='currency')$uahRate=2;
        $model->getQuote($addr);
        check(Api::$calls===$before+1,'cache invalidation '.$change);
    }
    check(count($session->data['pay002_np_price_cache'])<=8,'bounded cache');
    check(strpos(serialize($session->data['pay002_np_price_cache']),'fixture-key')===false,'no key stored');
    check(strpos(serialize($session->data['pay002_np_price_cache']),'Recipient')===false,'no address stored');
    pass('payload/account/store/currency changes invalidate; bounded opaque cache');
    foreach($session->data['pay002_np_price_cache'] as &$item)$item['expires']=time()-1;
    unset($item);
    $before=Api::$calls;$model->getQuote($addr);check(Api::$calls===$before+1,'expiry');
    $session->data['pay002_np_price_cache']='corrupt';
    $before=Api::$calls;$model->getQuote($addr);check(Api::$calls===$before+1,'malformed cache');
    pass('expired and malformed cache are ignored');
    $cfg['shipping_pinta_nova_poshta_use_api_price']=false;
    $before=Api::$calls;$unknown=$model->getQuote($addr);
    check(Api::$calls===$before && $unknown['quote']['warehouse']['booster_display_text']==='За тарифами Нової пошти','API-disabled policy');
    pass('API disabled does not reuse an API tariff');
    $cfg['shipping_pinta_nova_poshta_use_api_price']=true;
    $session->data['pay002_np_price_cache']=[];
    $success=Api::$result;
    foreach(['curl','http','api','invalid','zero'] as $failure){
        Api::$result=$success;
        if($failure==='curl')Api::$result['curl_error']='fixture timeout';
        if($failure==='http')Api::$result['http_code']=503;
        if($failure==='api')Api::$result['api_response']['success']=false;
        if($failure==='invalid')Api::$result['api_response']['data'][0]['Cost']='invalid';
        if($failure==='zero')Api::$result['api_response']['data'][0]['Cost']=0;
        $before=Api::$calls;$model->getQuote($addr);$model->getQuote($addr);
        check(Api::$calls===$before+2 && !$session->data['pay002_np_price_cache'],'failure must not cache '.$failure);
    }
    pass('curl/HTTP/API/invalid/zero results are not cached');
    $cfg['shipping_pinta_nova_poshta_use_fixed_rates']=true;
    $cfg['shipping_pinta_nova_poshta']=['fixed_rates'=>['weight'=>[10],'area_warehouse'=>[75]]];
    $fallback=$model->getQuote($addr);
    check($fallback['quote']['warehouse']['booster_display_text']==='75 UAH' && !$session->data['pay002_np_price_cache'],'fixed fallback preserved');
    pass('existing fixed-rate fallback preserved');
    check((bool)preg_grep('/desc="hit"/',$headers) && (bool)preg_grep('/desc="miss"/',$headers) && (bool)preg_grep('/desc="free"/',$headers),'timing states');
    check(strpos(implode('\n',$headers),'fixture-key')===false && strpos(implode('\n',$headers),'Recipient')===false,'timing redaction');
    pass('timing headers expose only durations and hit/miss/free');
}
