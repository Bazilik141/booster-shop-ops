<?php
namespace Opencart\System\Engine { class Model { public $config; } }
namespace {
error_reporting(E_ALL); $warnings = [];
set_error_handler(static function($n,$m)use(&$warnings){ $warnings[]=['level'=>$n,'message'=>$m]; return true; });
$w=str_replace('\\','/',__DIR__); $repo=dirname($w,2);
$source=$w.'/source'; $e=$repo.'/diagnostics/RD-25-WP2-WP3_phase0_evidence_20261012';
$mode=$argv[1]; $root=str_replace('\\','/',$argv[2]??$w.'/fixture');
define('DIR_IMAGE',$root.'/image/'); define('DIR_CACHE',$root.'/cache/');
if(!is_writable(DIR_CACHE)){throw new \RuntimeException('Diagnostic cache unavailable in this local runtime; do not accept timing or render evidence');}
require $source.'/system/library/image.php'; require $source.'/catalog/model/tool/image.php';
require $repo.'/work/pay003/tooling/vendor/autoload.php';
function oc_substr($s,$start,$length=null){return mb_substr($s,$start,$length,'UTF-8');}
function oc_strrpos($s,$needle){return mb_strrpos($s,$needle,0,'UTF-8');}
$model=new \Opencart\Catalog\Model\Tool\Image;
$model->config=new class {function get($k){ return $k==='config_url'?'http://rd25.local/':''; }};
$data=json_decode(file_get_contents($source.'/phase0.json'),true);
$names=[]; foreach($data['product_names'] as $n){$names[(int)$n['product_id']]=$n['name'];}
$productById=[]; $images=[];
foreach($data['products'] as $p){$id=(int)$p['product_id']; $p['name']=$names[$id]??('Product '.$id); $productById[$id]=$p; $images[$id]=[$p['image']];}
foreach($data['additional_images'] as $i){if($i['image']!=='')$images[(int)$i['product_id']][]=$i['image'];}
$twig=new \Twig\Environment(new \Twig\Loader\FilesystemLoader([$source.'/catalog/view/template']),['autoescape'=>false,'cache'=>false]);

if($mode==='debug'){
 $raw=$images[78][0];$method=new \ReflectionMethod($model,'rd25Source');$method->setAccessible(true);$s=$method->invoke($model,$raw);$cache=DIR_CACHE.'rd25-media-v4-'.hash('sha256',$s['relative']??'').'.json';
 echo json_encode(['source'=>$s,'cache'=>$cache,'cache_dir_exists'=>is_dir(DIR_CACHE),'cache_dir_writable'=>is_writable(DIR_CACHE),'cache_exists'=>is_file($cache),'info'=>$model->mediaInfo($raw),'fit'=>$model->resizeFit($raw,600,750),'warnings'=>$warnings],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";exit;
}
if($mode==='validate-inventory'){
 $measured=json_decode(file_get_contents($e.'/measured-inventory.json'),true);$count=0;
 foreach($measured['assets'] as $filename=>$record){$actual=$model->mediaInfo($filename);if($actual!==$record['metadata'])throw new \RuntimeException('Cached metadata parity '.$filename);$count++;}
 file_put_contents($e.'/metadata-validation.json',json_encode(['assets'=>$count,'cached_mediaInfo_exact_parity'=>true,'warnings'=>$warnings],JSON_PRETTY_PRINT));echo 'metadata_parity='.$count."\n";exit;
}

if($mode==='measure'){
 $measure=new \ReflectionMethod($model,'rd25Measure');$measure->setAccessible(true);$resolve=new \ReflectionMethod($model,'rd25Source');$resolve->setAccessible(true);
 $unique=[];foreach($images as $refs)foreach($refs as $raw){if($raw!=='')$unique[html_entity_decode($raw,ENT_QUOTES,'UTF-8')]=$raw;}
 $assets=[];$start=hrtime(true);
 foreach($unique as $filename=>$raw){
  $s=$resolve->invoke($model,$raw);if(!$s)throw new \RuntimeException('Unresolved public image '.$filename);
  $t=hrtime(true);$im=new \Opencart\System\Library\Image($s['path']);
  $metadata=array_merge(['version'=>4,'source'=>$s['relative'],'mtime'=>$s['mtime'],'size'=>$s['size'],'width'=>$im->getWidth(),'height'=>$im->getHeight(),'type'=>getimagesize($s['path'])[2]],$measure->invoke($model,$im->getImage(),$im->getWidth(),$im->getHeight()));
  imagedestroy($im->getImage());
  $assets[$filename]=['metadata'=>$metadata,'seconds'=>round((hrtime(true)-$t)/1e9,6),'sha256'=>hash_file('sha256',$s['path'])];
  file_put_contents(DIR_CACHE.'rd25-media-v4-'.hash('sha256',$s['relative']).'.json',json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
 }
 $inventory=[];foreach($productById as $id=>$p){$refs=[];foreach($images[$id] as $n=>$raw){if($raw==='')continue;$filename=html_entity_decode($raw,ENT_QUOTES,'UTF-8');$a=$assets[$filename];$refs[]=['role'=>$n===0?'primary':'additional','raw_db_image'=>$raw,'source'=>$filename,'kind'=>$a['metadata']['kind'],'class_reason'=>$a['metadata']['class_reason'],'width'=>$a['metadata']['width'],'height'=>$a['metadata']['height']];}
  $inventory[]=['product_id'=>$id,'name'=>$p['name'],'date_available'=>$p['date_available'],'future_date_at_export'=>substr($p['date_available'],0,10)>substr($data['utc'],0,10),'primary_image_blank'=>$p['image']==='','image_count'=>count($refs),'unique_image_count'=>count(array_unique(array_column($refs,'source'))),'images'=>$refs];}
 $result=['php'=>PHP_VERSION,'scope'=>$data['scope'],'unique_assets'=>count($assets),'products'=>count($inventory),'wall_seconds'=>round((hrtime(true)-$start)/1e9,6),'warnings'=>$warnings,'assets'=>$assets,'inventory'=>$inventory];
 file_put_contents($e.'/measured-inventory.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
 echo json_encode(['measured_assets'=>count($assets),'products'=>count($inventory),'seconds'=>$result['wall_seconds'],'warnings'=>count($warnings)])."\n"; exit;
}

// Fixture-only payload simulation. Uses the unmodified live model and Twig;
// does not edit controllers/templates, introduce a patch, or change live caches.
$selected=[78,80,77,131,144,96,201];
$plan=json_decode(file_get_contents($e.'/plan.json'),true);
$gallery=file_get_contents($source.'/catalog/view/template/product/product.twig');
preg_match('~({% if thumb or images %}.*?{# === INFO PANEL === #})~s',$gallery,$g);
if(!$g)throw new \RuntimeException('Gallery extraction');
$galleryTemplate=$twig->createTemplate($g[1]);
$buildGallery=function(int $id,bool $after)use($model,$images,$productById,$galleryTemplate){
 $refs=$images[$id];$name=htmlspecialchars($productById[$id]['name'],ENT_QUOTES,'UTF-8');$items=[];
 foreach($refs as $i=>$raw){$popup=$model->resize($raw,700,700);$legacy=$model->resize($raw,$i===0?500:100,$i===0?500:100);$m=[];$main='';$small='';
  if($after){$m=$model->mediaInfo($raw);$main=$model->resizeFit($raw,600,750);$small=$model->resizeFit($raw,128,160);}
  $items[]=['popup'=>$popup,'thumb'=>$legacy,'main'=>$main?:$legacy,'small'=>$small?:$legacy,'kind'=>$m['kind']??'legacy','fit'=>$main!==''&&$small!==''];
 }
 if(!$after){$html=$galleryTemplate->render(['heading_title'=>$name,'popup'=>$items[0]['popup'],'thumb'=>$items[0]['thumb'],'images'=>array_slice($items,1)]);}
 else{
  $html='<div class="bs-product__gallery magnific-popup prototype-gallery"><div class="bs-product__main-img"><a data-gallery-main href="'.$items[0]['popup'].'" data-kind="'.$items[0]['kind'].'"><img src="'.$items[0]['main'].'" alt="'.$name.'"></a></div>';
  if(count($items)>1){$html.='<div class="bs-product__thumbs" aria-label="Фотографії товару">';foreach($items as $i=>$x){$html.='<a href="'.$x['popup'].'" data-gallery-thumb data-main="'.$x['main'].'" data-kind="'.$x['kind'].'" aria-label="Фото '.($i+1).'"'.($i===0?' aria-current="true"':'').'><img src="'.$x['small'].'" alt="'.$name.'"></a>';}$html.='</div>';}$html.='</div>';
 }
 return ['html'=>$html,'items'=>$items];
};
$buildCart=function(array $ids,bool $after,string $template)use($model,$productById,$twig){$products=[];$trace=[];
 foreach($ids as $id){$p=$productById[$id];$legacy=$model->resize($p['image'],120,120);$fit='';$m=[];if($after){$m=$model->mediaInfo($p['image']);$fit=$model->resizeFit($p['image'],104,130);}
  $products[]=['product_id'=>$id,'cart_id'=>$id,'name'=>htmlspecialchars($p['name'],ENT_QUOTES,'UTF-8'),'href'=>'#product-'.$id,'thumb'=>$fit?:$legacy,'quantity'=>1,'minimum'=>1,'price'=>'100 ₴','total'=>'100 ₴','stock'=>true,'option'=>[],'subscription'=>'','kind'=>$m['kind']??'legacy'];
  $trace[]=['product_id'=>$id,'fit'=>$fit!=='','url'=>$fit?:$legacy];}
 $values=['products'=>$products,'totals'=>[['code'=>'sub_total','text'=>(count($products)*100).' ₴']],'sub_total'=>count($products)*100,'shipping_pinta_nova_poshta_free_from'=>5000,'text_items'=>'Мій кошик - '.(count($products)*100).' ₴','language'=>'uk-ua','cart'=>'#cart','checkout'=>'#checkout','continue'=>'#catalog'];
 return ['html'=>$twig->render($template,$values),'trace'=>$trace];
};

if($mode==='legacy-warm'){
 foreach($selected as $id){$buildGallery($id,false);$buildCart([$id],false,'checkout/cart_list.twig');}
 foreach($plan['cold_product_ids'] as $id)$buildGallery($id,false);
 foreach($plan['cold_cart_ids'] as $id)$buildCart([$id],false,'checkout/cart_list.twig');
 echo "legacy_cache_warm=yes\n";exit;
}
if($mode==='schema'){
 $checks=[];
 foreach($selected as $id){$bodies=[];$parsed=[];
  foreach([false,true] as $after){$b=$buildGallery($id,$after);$context=['product_id'=>$id,'heading_title'=>htmlspecialchars($productById[$id]['name'],ENT_QUOTES,'UTF-8'),'popup'=>$b['items'][0]['popup'],'thumb'=>$after?$b['items'][0]['main']:$b['items'][0]['thumb'],'images'=>array_slice($b['items'],1),'price'=>'100 ₴','language'=>'uk-ua','breadcrumbs'=>[],'share'=>'http://rd25.local/product/'.$id];
   $body=$twig->render('product/product.twig',$context);preg_match('~<script type="application/ld\+json">(.*?)</script>~s',$body,$schema);if(!$schema)throw new \RuntimeException('JSON-LD absent');
   $bodies[]=$schema[1];$parsed[]=json_decode($schema[1],true,512,JSON_THROW_ON_ERROR);}
  if($bodies[0]!==$bodies[1])throw new \RuntimeException('Fixture JSON-LD bytes changed');
  $checks[]=['product_id'=>$id,'image_count'=>count($parsed[0]['image']),'jsonld_bytes_identical'=>true,'image_array_identical'=>$parsed[0]['image']===$parsed[1]['image'],'primary_thumbnail_not_appended_to_legacy_images'=>true,'image_urls'=>$parsed[0]['image']];
 }
 file_put_contents($e.'/schema-preservation.json',json_encode(['scope'=>'Full unmodified product Twig, legacy popup/image fields retained, diagnostic fit thumb substitution only; local fixture proof, not a deployed patch','checks'=>$checks,'warnings'=>$warnings],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));echo 'schema_checks='.count($checks)."\n";exit;
}
if($mode==='render'){
 $output=[];
 foreach($selected as $id){foreach([false,true] as $after){$b=$buildGallery($id,$after);$output[$id][$after?'after':'before']=$b;}}
 $carts=[];foreach([false,true] as $after){foreach(['common/cart.twig','checkout/cart_list.twig'] as $tpl){$b=$buildCart($selected,$after,$tpl);$carts[$after?'after':'before'][$tpl]=$b;}}
 file_put_contents($e.'/rendered-fragments.json',json_encode(['product'=>$output,'cart'=>$carts,'warnings'=>$warnings],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));echo json_encode(['rendered_products'=>count($output),'warnings'=>count($warnings)])."\n";exit;
}
if($mode==='cost'){
 $scenario=$argv[3];$start=hrtime(true);$parts=[];$trace=[];
 if($scenario==='product'){
  foreach(isset($argv[4])?[(int)$argv[4]]:$plan['cold_product_ids'] as $id){$t=hrtime(true);$b=$buildGallery($id,true);
   $page=$twig->createTemplate(str_replace($g[1],'{{ rd25_gallery|raw }}',$gallery));
   $parts[]=$page->render(['rd25_gallery'=>$b['html'],'product_id'=>$id,'heading_title'=>htmlspecialchars($productById[$id]['name'],ENT_QUOTES,'UTF-8'),'popup'=>$b['items'][0]['popup'],'thumb'=>$b['items'][0]['main'],'images'=>array_slice($b['items'],1),'price'=>'100 ₴','language'=>'uk-ua','breadcrumbs'=>[],'bs_variant_groups'=>[],'description'=>'Diagnostic fixture, not a live product response.']);
   $trace[]=['product_id'=>$id,'images'=>count($b['items']),'fit_images'=>count(array_filter($b['items'],static function($i){return $i['fit'];})),'seconds'=>round((hrtime(true)-$t)/1e9,6)];}
 }else{$b=$buildCart($plan['cold_cart_ids'],true,'checkout/cart_list.twig');$parts[]=$twig->render('checkout/cart.twig',['list'=>$b['html'],'heading_title'=>'Кошик','language'=>'uk-ua','breadcrumbs'=>[]]);$trace=$b['trace'];}
 $result=['scenario'=>$scenario,'php'=>PHP_VERSION,'wall_seconds'=>round((hrtime(true)-$start)/1e9,6),'trace'=>$trace,'warnings'=>$warnings,'peak_memory_bytes'=>memory_get_peak_usage(true),'rendered_bytes'=>strlen(implode('',$parts))];
 echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";exit;
}
throw new \RuntimeException('Unknown diagnostic mode');
}
