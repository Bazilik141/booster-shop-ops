<?php
namespace Opencart\System\Engine { class Model {} }
namespace {
define('VERSION', $argv[1]);
$root=__DIR__;
$file=$root.'/ga4/extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php';
require $file;
$model=new \Opencart\Catalog\Model\Extension\PsEnhancedMeasurement\Analytics\PsEnhancedMeasurement;
$mapping=[
 'catalog/view/template/common/header.twig'=>'replaceCatalogViewCommonHeaderBefore',
 'catalog/view/template/product/product.twig'=>'replaceCatalogViewProductProductBefore',
 'catalog/view/template/common/cart.twig'=>'replaceCatalogViewCheckoutCartInfoBefore',
 'catalog/view/template/checkout/cart.twig'=>'replaceCatalogViewCheckoutCartBefore',
 'catalog/view/template/checkout/cart_list.twig'=>'replaceCatalogViewCheckoutCartListBefore'
];
$rows=[];$outputs=[];
foreach($mapping as $template=>$method){
 $views=$model->$method([]);
 foreach(['before','wp2','wp3'] as $state){
  $base=$state==='before'?dirname($root).'/rd25-wp2-wp3-phase0-20261012/source':$root.'/after-'.$state;
  $path=$base.'/'.$template;
  if(!is_file($path))$path=dirname($root).'/rd25-wp2-wp3-phase0-20261012/source/'.$template;
  $code=file_get_contents($path);
  foreach($views as $index=>$view){
   $key=$template.':'.$index;
   if(!isset($rows[$key]))$rows[$key]=['template'=>$template,'method'=>$method,'index'=>$index,'search'=>$view['search'],'replace'=>$view['replace']];
   $rows[$key][$state]=substr_count($code,$view['search']);
  }
  foreach($views as $view)$code=str_replace($view['search'],$view['replace'],$code);
  $outputs[$state][$template]=$code;
 }
}
foreach($rows as $row){if($row['before']!==$row['wp2']||$row['before']!==$row['wp3'])throw new \RuntimeException('GA4 anchor count changed: '.$row['template'].' #'.$row['index']);}
$checks=[];
foreach($mapping as $template=>$method){
 $baseline=null;
 foreach($outputs as $state=>$templates){
  $code=$templates[$template];
  preg_match_all('~data-ps-track-[a-z-]+\s*=\s*"[^"]*"~',$code,$attributes);
  preg_match_all('~<(?:input|button)\b[^>]*>|<form\b.*?</form>|<a\b[^>]*(?:bs-cart-remove|mini-cart-remove-btn)[^>]*>.*?</a>~s',$code,$controls);
  $controls=array_values(array_filter($controls[0],static function($x){return strpos($x,'data-bs-gallery')===false;}));
  $now=['tracking_attributes'=>$attributes[0],'controls'=>$controls];
  if($baseline===null)$baseline=$now;
  elseif($baseline!==$now)throw new \RuntimeException('Tracking/control bytes changed: '.$template.' '.$state);
 }
 $checks[]=['template'=>$template,'injected_tracking_attributes'=>count($baseline['tracking_attributes']),'controls'=>count($baseline['controls']),'all_states_byte_identical'=>true];
}
$result=['module_sha256'=>hash_file('sha256',$file),'version_branch'=>VERSION,'anchors'=>array_values($rows),'preservation'=>$checks];
file_put_contents($root.'/ga4-'.VERSION.'.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");
foreach($outputs as $state=>$templates)foreach($templates as $p=>$code){$dest=$root.'/ga4-injected-'.$state.'/'.$p;if(!is_dir(dirname($dest)))mkdir(dirname($dest),0777,true);file_put_contents($dest,$code);}
echo json_encode(['version'=>VERSION,'anchors'=>count($rows),'control_checks'=>count($checks)])."\n";
}
