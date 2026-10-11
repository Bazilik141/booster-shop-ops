<?php
namespace Opencart\System\Engine {class Model{public $config;}class Controller{public $config,$load,$url,$db,$model_tool_image,$model_catalog_product;}}
namespace {
error_reporting(E_ALL);$warnings=[];set_error_handler(function($n,$m)use(&$warnings){$warnings[]=[$n,$m];echo 'WARNING:'.$m;return true;});
$w=str_replace('\\','/',__DIR__);$r=dirname($w,2);$root=str_replace('\\','/',$argv[1]);$dataset=json_decode(file_get_contents($argv[2]),true);$mode=$argv[3];
define('DIR_IMAGE',$root.'/image/');define('DIR_CACHE',$root.'/cache/');define('DB_PREFIX','fixture_');
require $w.'/expected/system/library/image.php';require $w.'/expected/catalog/model/tool/image.php';require $w.'/expected/catalog/controller/product/thumb.php';require $r.'/work/pay003/tooling/vendor/autoload.php';
function oc_substr($s,$start,$length=null){return mb_substr($s,$start,$length,'UTF-8');}function oc_strrpos($s,$needle){return mb_strrpos($s,$needle,0,'UTF-8');}
$model=new \Opencart\Catalog\Model\Tool\Image;$config=new class{function get($k){return ['config_url'=>'http://fixture/','config_language'=>'uk-ua','config_language_id'=>1][$k]??'';}};$model->config=$config;
if($mode==='legacy-warm'){
 foreach($dataset['products'] as $p){if($p['image']!=='')$model->resize($p['image'],240,240);}
 echo "legacy_warm=ok\n";exit;
}
$twig=new \Twig\Environment(new \Twig\Loader\FilesystemLoader([$r.'/work/rd25-wp1-fix-phase0-20261011/source/catalog/view/template',$r.'/work/rd25-wp1-20261009/expected/catalog/view/template']),['autoescape'=>false,'cache'=>false]);
$load=new class{public $twig,$last;function model($s){}function language($s){}function view($s,$data){$this->last=$data;return $this->twig->render($s.'.twig',$data);}};$load->twig=$twig;
$thumb=new \Opencart\Catalog\Controller\Product\Thumb;$thumb->model_tool_image=$model;$thumb->config=$config;$thumb->load=$load;$thumb->db=new class{function query($q){return (object)['num_rows'=>0,'row'=>[]];}};$thumb->url=new class{function link($a,$b){return '#fixture';}};
$cards=[];$trace=[];$start=hrtime(true);
foreach($dataset['products'] as $p){
 $t=hrtime(true);$legacy=$p['image']===''?'':$model->resize($p['image'],240,240);
 $html=$thumb->index(['product_id'=>$p['product_id'],'image'=>$p['image'],'thumb'=>$legacy,'name'=>'Fixture long product name '.$p['product_id'],'href'=>'#fixture','quantity'=>1,'stock'=>'available','minimum'=>1,'price'=>'100 ₴','button_cart'=>'До кошика']);
 $data=$load->last;$fit=$data['bs_fit_thumb']!=='';if($fit){$relative=substr($data['bs_fit_thumb'],strlen('http://fixture/image/'));$dims=getimagesize(DIR_IMAGE.$relative);if([$dims[0],$dims[1]]!==[$data['bs_media_width'],$data['bs_media_height']])throw new \RuntimeException('fit dimensions');}
 elseif($data['thumb']!==$legacy||strpos($html,$legacy)===false)throw new \RuntimeException('legacy fallback missing');
 $cards[]=$html;$trace[]=['product_id'=>$p['product_id'],'image'=>$p['image'],'fit'=>$fit,'seconds'=>round((hrtime(true)-$t)/1e9,6)];
}
$category=file_get_contents($r.'/work/rd25-wp1-20261009/expected/catalog/view/template/product/category.twig');preg_match('~(<div id="product-list"[^>]*>\s*{% for product in products %}.*?{% endfor %}\s*</div>)~s',$category,$match);$html=$twig->createTemplate($match[1])->render(['products'=>$cards]);
$elapsed=(hrtime(true)-$start)/1e9;$out=['php'=>PHP_VERSION,'model_sha256'=>hash_file('sha256',$w.'/expected/catalog/model/tool/image.php'),'dataset_scope'=>$dataset['scope'],'mode'=>$mode,'products'=>count($cards),'fit_cards'=>count(array_filter($trace,fn($t)=>$t['fit'])),'legacy_fallback_cards'=>count(array_filter($trace,fn($t)=>!$t['fit'])),'wall_seconds'=>round($elapsed,6),'warning_count'=>count($warnings),'warnings'=>$warnings,'peak_memory_bytes'=>memory_get_peak_usage(true),'trace'=>$trace];
file_put_contents($root.'/'.$mode.'.html','<!doctype html><meta charset="utf-8">'.$html);file_put_contents($root.'/'.$mode.'.json',json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));echo json_encode($out,JSON_UNESCAPED_SLASHES)."\n";
}
