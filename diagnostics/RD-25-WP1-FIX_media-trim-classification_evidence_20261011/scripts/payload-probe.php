<?php
namespace Opencart\System\Engine {
    class Controller { public $config,$load,$url,$db,$model_tool_image,$model_catalog_product,$customer,$currency,$tax,$session; }
}
namespace {
    error_reporting(E_ALL);
    define('DB_PREFIX','f6_fixture_');
    function oc_substr($s,$start,$length){return mb_substr($s,$start,$length,'UTF-8');}
    require $argv[1];
    require __DIR__.'/expected/catalog/controller/product/thumb.php';
    $case=$argv[2];
    class Config {public $hidden=false;public function get($key){return ['config_language'=>'uk-ua','config_language_id'=>1,'config_customer_price'=>$this->hidden?1:0,'config_tax'=>1,'config_product_description_length'=>60,'config_review_status'=>1][$key]??'';}}
    class ProductRead {
        public $case,$phase='featured',$reads=[];
        public function getProduct($id){
            $this->reads[]=['phase'=>$this->phase,'id'=>$id];
            if($id==99)return false;
            $image=($this->case==='empty' || $this->case==='mixed' && $id==1)?'':($this->case==='missing'?'catalog/missing.webp':'catalog/F6/O&amp;P-'.$id.'.webp');
            return ['product_id'=>$id,'image'=>$image,'name'=>'Fixture '.$id,'description'=>'<p>Synthetic description</p>','price'=>100+$id,'special'=>$id==1?80:0,'tax_class_id'=>1,'quantity'=>$id==2?0:5,'stock_status'=>$id==2?'Передзамовлення':'В наявності','stock_status_id'=>1,'date_available'=>$id==2?'2030-01-01':'0000-00-00','minimum'=>1,'rating'=>4];
        }
    }
    class Images {
        public $legacy=[],$fits=[];
        public function resize($file,$w,$h){$this->legacy[]=[$file,$w,$h];return '/legacy/'.rawurlencode($file);}
        public function resizeFit($file,$w,$h){$this->fits[]=[$file,$w,$h];return strpos($file,'missing')!==false?'':'/fit/'.rawurlencode(html_entity_decode($file,ENT_QUOTES,'UTF-8'));}
        public function mediaInfo($file){return ['width'=>500,'height'=>1000,'crop_width'=>500,'crop_height'=>1000,'kind'=>'cut'];}
    }
    class DB {public $queries=[];public function query($sql){if(stripos($sql,'SELECT ')!==0)throw new \RuntimeException('write forbidden');$this->queries[]=$sql;return (object)['num_rows'=>0,'row'=>[]];}}
    class Load {
        public $thumb,$products,$payloads=[],$thumbViews=[],$featuredViews=[];
        public function language($x){}
        public function model($x){}
        public function controller($route,$row){
            if($route!=='product/thumb')throw new \RuntimeException('Unexpected route');
            $this->payloads[]=$row;$this->products->phase='thumb';
            try{return $this->thumb->index($row);}finally{$this->products->phase='featured';}
        }
        public function view($route,$data){
            if($route==='product/thumb')$this->thumbViews[]=$data;else $this->featuredViews[]=$data;
            return json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
    }
    $featured=new \Opencart\Catalog\Controller\Extension\Opencart\Module\Featured();
    $thumb=new \Opencart\Catalog\Controller\Product\Thumb();
    $config=new Config();$config->hidden=$case==='hidden';$products=new ProductRead();$products->case=$case;
    $images=new Images();$db=new DB();$load=new Load();$load->thumb=$thumb;$load->products=$products;
    foreach([$featured,$thumb] as $c){
        $c->config=$config;$c->model_catalog_product=$products;$c->model_tool_image=$images;$c->db=$db;$c->load=$load;
        $c->url=new class {public function link($r,$q){return '/'.$r.'?'.$q;}};
        $c->customer=new class {public function isLogged(){return false;}};
        $c->currency=new class {public function format($v,$currency){return $currency.':'.number_format($v,2,'.','');}};
        $c->tax=new class {public function calculate($v,$id,$enabled){return $v;}};
        $c->session=(object)['data'=>['currency'=>'UAH']];
    }
    $ids=$case==='duplicates'?[1,1,2]:($case==='invalid'?[1,99,2]:($case==='no-products'?[]:range(1,24)));
    $setting=['axis'=>$case==='vertical'?'vertical':'horizontal','product'=>$ids,'width'=>500,'height'=>500];
    $result=$featured->index($setting);
    $firstReads=$products->reads;
    $featured->index($setting);
    echo json_encode(['php'=>PHP_VERSION,'case'=>$case,'first_reads'=>$firstReads,'all_reads'=>$products->reads,'first_count'=>count($firstReads),'first_featured_reads'=>count(array_filter($firstReads,fn($r)=>$r['phase']==='featured')),'first_thumb_reads'=>count(array_filter($firstReads,fn($r)=>$r['phase']==='thumb')),'payloads'=>$load->payloads,'thumb_views'=>$load->thumbViews,'featured_views'=>$load->featuredViews,'legacy_resize_calls'=>$images->legacy,'fit_calls'=>$images->fits,'db_queries'=>$db->queries,'empty_result'=>$result===''],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
}
