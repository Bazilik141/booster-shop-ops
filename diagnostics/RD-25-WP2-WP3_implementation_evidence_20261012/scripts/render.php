<?php
namespace Opencart\System\Engine { class Model {public $config;} }
namespace {
error_reporting(E_ALL);$warnings=[];
set_error_handler(static function($level,$message)use(&$warnings){$warnings[]=['level'=>$level,'message'=>$message];return true;});
$w=str_replace('\\','/',__DIR__);$repo=dirname($w,2);$source=$repo.'/work/rd25-wp2-wp3-phase0-20261012/source';$fixture=$repo.'/work/rd25-wp2-wp3-phase0-20261012/fixture';
define('DIR_IMAGE',$fixture.'/image/');define('DIR_CACHE',$fixture.'/cache/');
if(!is_writable(DIR_CACHE))throw new \RuntimeException('Native diagnostic cache unavailable');
require $source.'/system/library/image.php';require $source.'/catalog/model/tool/image.php';require $repo.'/work/pay003/tooling/vendor/autoload.php';
function oc_substr($s,$start,$length=null){return mb_substr($s,$start,$length,'UTF-8');}
function oc_strrpos($s,$needle){return mb_strrpos($s,$needle,0,'UTF-8');}
$config=new class {function get($key){return ['config_url'=>'http://rd25.local/','config_image_popup_width'=>700,'config_image_popup_height'=>700,'config_image_thumb_width'=>500,'config_image_thumb_height'=>500,'config_image_additional_width'=>100,'config_image_additional_height'=>100,'config_image_cart_width'=>120,'config_image_cart_height'=>120][$key]??'';}};
$model=new \Opencart\Catalog\Model\Tool\Image;$model->config=$config;
$data=json_decode(file_get_contents($source.'/phase0.json'),true);$names=[];$products=[];$extras=[];
foreach($data['product_names'] as $p)$names[$p['product_id']]=$p['name'];
foreach($data['products'] as $p){$p['name']=$names[$p['product_id']]??'Product';$products[$p['product_id']]=$p;}
foreach($data['additional_images'] as $p)$extras[$p['product_id']][]=$p;
$context=new class($model,$config,$extras) {
 public $model_tool_image;public $load;public $config;public $model_catalog_product;
 function __construct($model,$config,$extras){$this->model_tool_image=$model;$this->config=$config;$this->load=new class{function model($x){}};$this->model_catalog_product=new class($extras){private $extras;function __construct($x){$this->extras=$x;}function getImages($id){return $this->extras[$id]??[];}};}
};
$selected=[78,80,77,131,144,96,201,73];$result=['products'=>[],'cart'=>[],'schema'=>[],'fallback'=>[]];
foreach(['before','wp2','wp3'] as $state){
 $base=$state==='before'?$source:$w.'/after-'.$state;
 $read=static function($path)use($base,$source){return file_get_contents(is_file($base.'/'.$path)?$base.'/'.$path:$source.'/'.$path);};
 $controller=$read('catalog/controller/product/product.php');
 preg_match('~\t\t\t// Image\R(.*?)\t\t\tif \(\$this->customer->isLogged~s',$controller,$block);
 if(!$block)throw new \RuntimeException('Real gallery controller block absent');
 $execute=(function($product_info,$product_id,$code){$data=[];eval($code);return $data;})->bindTo($context);
 $twig=new \Twig\Environment(new \Twig\Loader\ArrayLoader([]),['autoescape'=>false,'cache'=>false]);
 foreach($selected as $id){
  $payload=$execute($products[$id],$id,$block[1]);
  $values=$payload+['heading_title'=>htmlspecialchars($products[$id]['name'],ENT_QUOTES,'UTF-8'),'product_id'=>$id,'price'=>'100 ₴','stock'=>'Є в наявності','language'=>'uk-ua','breadcrumbs'=>[],'bs_variant_groups'=>[],'description'=>'Diagnostic fixture','minimum'=>1,'cart_add'=>'index.php?route=checkout/cart.add','text_loading'=>'Додавання','ps_track_add_to_cart'=>true,'ps_track_view_item'=>false,'ps_track_select_item'=>true,'ps_track_select_promotion'=>true,'ps_track_remove_from_cart'=>true];
  $raw=$read('catalog/view/template/product/product.twig');
  $full=$twig->createTemplate($raw)->render($values);
  preg_match('~<script type="application/ld\+json">(.*?)</script>~s',$full,$ld);$result['schema'][$id][$state]=$ld[1];
  preg_match('~({% if thumb or images %}.*?{# === INFO PANEL === #})~s',$raw,$gallery);
  $html=$twig->createTemplate($gallery[1])->render($values);
  preg_match('~\$\(document\)\.ready\(function\(\) \{\R.*?\R\}\);(?=\R//--></script>)~s',$full,$js);
  if(!$js)throw new \RuntimeException('Real gallery JS absent');
  $result['products'][$id][$state]=['html'=>$html,'js'=>$js[0],'payload'=>$payload,'name'=>$products[$id]['name']];
 }
 foreach(['common/cart.twig','checkout/cart_list.twig'] as $template){
  $controller=$read($template==='common/cart.twig'?'catalog/controller/common/cart.php':'catalog/controller/checkout/cart.php');$rows=[];
  foreach($selected as $id){$product=$products[$id];$bs_small=[];
   if($state==='wp3'){
    preg_match('~(\t\t\t// RD-25-WP3.*?)(?=\t\t\t\$data\[\'products\'\]\[\] = \[)~s',$controller,$small);
    $bs_small=(function($product,$code){eval($code);return $bs_small;})->bindTo($context)($product,$small[1]);
   }
   $rows[]=['thumb'=>$model->resize($product['image'],120,120),'name'=>htmlspecialchars($product['name'],ENT_QUOTES,'UTF-8'),'cart_id'=>$id,'product_id'=>$id,'quantity'=>1,'minimum'=>1,'minimum_value'=>1,'price'=>'100 ₴','total'=>'100 ₴','stock'=>true,'href'=>'#product-'.$id,'remove'=>'index.php?route=checkout/cart.remove&key='.$id,'option'=>[],'subscription'=>'','special'=>false]+$bs_small;
  }
  $values=['products'=>$rows,'heading_title'=>'Кошик','language'=>'uk-ua','totals'=>[['code'=>'sub_total','text'=>'800 ₴']],'text_items'=>'Мій кошик - 800 ₴','sub_total'=>800,'shipping_pinta_nova_poshta_free_from'=>2000,'cart'=>'index.php?route=checkout/cart','checkout'=>'index.php?route=checkout/checkout','edit'=>'index.php?route=checkout/cart.edit','remove'=>'index.php?route=common/cart.remove','continue'=>'#catalog','ps_track_select_item'=>true,'ps_track_select_promotion'=>true,'ps_track_add_to_cart'=>true,'ps_track_remove_from_cart'=>true];
  $raw=$read('catalog/view/template/'.$template);
  $injected=file_get_contents($w.'/ga4-injected-'.$state.'/catalog/view/template/'.$template);
  $result['cart'][$state][$template]=['html'=>$twig->createTemplate($raw)->render($values),'injected_html'=>$twig->createTemplate($injected)->render($values),'rows'=>$rows];
 }
}
foreach($result['schema'] as $id=>$states){if(count(array_unique($states))!==1)throw new \RuntimeException('JSONLD changed #'.$id);}
// Exercise LOCK_NB/budget refusal contract through the exact new controller closure.
$refuse=new class($model){private $base;function __construct($x){$this->base=$x;}function resize($f,$x,$y){return $this->base->resize($f,$x,$y);}function resizeFit($f,$x,$y){return '';}function mediaInfo($f){return [];}};
$context->model_tool_image=$refuse;
$controller=file_get_contents($w.'/after-wp2/catalog/controller/product/product.php');preg_match('~\t\t\t// Image\R(.*?)\t\t\tif \(\$this->customer->isLogged~s',$controller,$block);
$payload=$execute($products[201],201,$block[1]);
foreach($payload['bs_gallery'] as $index=>$row){$legacy=$index===0?$payload['thumb']:$payload['images'][$index-1]['thumb'];if($row['main']!==$legacy||$row['small']!==$legacy||$row['kind']!=='legacy')throw new \RuntimeException('Gallery fallback');}
$result['fallback']=['gallery_items'=>count($payload['bs_gallery']),'legacy_url_exact'=>true];
$result['warnings']=$warnings;
file_put_contents($w.'/rendered.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");
echo json_encode(['products'=>count($selected),'states'=>3,'schema_exact'=>count($result['schema']),'warnings'=>count($warnings)])."\n";
if($warnings)exit(1);
}
