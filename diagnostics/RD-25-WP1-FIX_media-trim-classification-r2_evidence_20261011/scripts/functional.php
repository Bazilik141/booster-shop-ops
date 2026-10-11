<?php
namespace Opencart\System\Engine {
 class Model { public $config; }
 class Controller { public $config,$load,$url,$db,$model_tool_image,$model_catalog_product; }
}
namespace {
error_reporting(E_ALL); $warnings=[];
set_error_handler(function($n,$m)use(&$warnings){$warnings[]=[$n,$m]; echo "WARNING:$m\n"; return true;});
$w=str_replace('\\','/',__DIR__); $repo=dirname(dirname($w));
$e=$repo.'/diagnostics/RD-25-WP1-FIX_media-trim-classification-r2_evidence_20261011';
define('DIR_IMAGE',$repo.'/work/rd25-wp1-fix-phase0-20261011/images/');
define('DIR_CACHE',$w.'/runtime/cache/'); define('DB_PREFIX','readonly_fixture_');
if (!is_dir(DIR_CACHE)) { mkdir(DIR_CACHE,0755,true); }
require $w.'/expected/system/library/image.php';
require $w.'/expected/catalog/model/tool/image.php';
require $w.'/expected/catalog/controller/product/thumb.php';
$config=new class{function get($k){return ['config_url'=>'https://fixture.invalid/','config_language'=>'uk-ua','config_language_id'=>1][$k]??'';}};
$model=new \Opencart\Catalog\Model\Tool\Image; $model->config=$config;
$thumb=new \Opencart\Catalog\Controller\Product\Thumb; $thumb->config=$config; $thumb->model_tool_image=$model;
$thumb->url=new class{function link($r,$q){return '/'.$r.'?'.$q;}};
$thumb->load=new class{public $last; function model($s){} function language($s){} function view($r,$data){$this->last=$data; return '<img width="'.$data['bs_media_width'].'" height="'.$data['bs_media_height'].'">';}};
function ensure($yes,$name){if(!$yes)throw new \RuntimeException($name);}
$assets=json_decode(file_get_contents($repo.'/diagnostics/RD-25-WP1-FIX_phase0_evidence_20261011/proposed-assets.json'),true);
$out=[]; $start=microtime(true);
foreach($assets as $i=>$a){
 $m=$model->mediaInfo($a['source']); ensure($m,'metadata '.$i);
 $url=$model->resizeFit($a['source'],700,875); ensure($url,'fit '.$i);
 $path=DIR_IMAGE.substr($url,strlen('https://fixture.invalid/image/')); $dims=getimagesize($path);
 $html=$thumb->index(['image'=>$a['source'],'product_id'=>0,'quantity'=>1,'stock'=>'available']); $data=$thumb->load->last;
 ensure([$dims[0],$dims[1]]===[$data['bs_media_width'],$data['bs_media_height']],'img dimensions '.$i);
 ensure($model->mediaInfo($a['source'])==$m && $model->resizeFit($a['source'],700,875)===$url,'warm '.$i);
 $out[]=['id'=>$a['id'],'source'=>$a['source'],'metadata'=>$m,'served_dimensions'=>[$dims[0],$dims[1]],'emitted_img'=>$html,'fit_relative'=>substr($path,strlen(DIR_IMAGE))];
 if($i%25===0){file_put_contents($w.'/progress.txt',"$i/286\n");}
}
file_put_contents($e.'/gd-assets.json',json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
$extra=[];
foreach(['png','webp'] as $ext){
 $p=$w.'/runtime/alpha.'.$ext; $g=imagecreatetruecolor(8,8); imagealphablending($g,false); imagesavealpha($g,true);
 imagefill($g,0,0,imagecolorallocatealpha($g,255,255,255,127));
 imagesetpixel($g,2,2,imagecolorallocatealpha($g,230,40,50,63)); imagesetpixel($g,3,3,imagecolorallocatealpha($g,30,210,60,0));
 if($ext==='png')imagepng($g,$p);else imagewebp($g,$p,IMG_WEBP_LOSSLESS); imagedestroy($g);
 $im=new \Opencart\System\Library\Image($p); $before=imagecolorsforindex($im->getImage(),imagecolorat($im->getImage(),2,2));
 $im->cropFit(1,1,6,6); $edge=imagecolorsforindex($im->getImage(),imagecolorat($im->getImage(),1,1));
 $empty=imagecolorsforindex($im->getImage(),imagecolorat($im->getImage(),0,0));
 ensure($edge===$before && $edge['alpha']===63 && $empty['alpha']===127,'crop alpha '.$ext);
 $im->save($w.'/runtime/cropped.'.$ext); $round=new \Opencart\System\Library\Image($w.'/runtime/cropped.'.$ext);
 $saved=imagecolorsforindex($round->getImage(),imagecolorat($round->getImage(),1,1)); ensure($saved['alpha']===63,'saved alpha '.$ext);
 $extra['alpha_'.$ext]=['source_edge'=>$before,'crop_edge'=>$edge,'transparent_pixel'=>$empty,'saved_edge'=>$saved,'dimensions'=>[$im->getWidth(),$im->getHeight()]];
 try{$im->cropFit(-1,0,2,2);ensure(false,'invalid bounds');}catch(\InvalidArgumentException $ok){}
}
$a=$assets[131]; $cache=DIR_CACHE.'rd25-media-v4-'.hash('sha256',$a['source']).'.json'; $expected=$model->mediaInfo($a['source']);
foreach(['invalid_json','v3','outside_crop','float_crop','inconsistent_dimensions'] as $case){
 $bad=$expected; if($case==='v3')$bad['version']=3; if($case==='outside_crop')$bad['crop_box_exclusive'][2]=$bad['width']+1;
 if($case==='float_crop')$bad['crop_box_exclusive'][0]=0.5; if($case==='inconsistent_dimensions')$bad['crop_width']=1;
 file_put_contents($cache,$case==='invalid_json'?'broken':json_encode($bad)); ensure($model->mediaInfo($a['source'])==$expected,'reject '.$case); $extra[$case]='regenerated';
}
unlink($cache); ensure($model->mediaInfo($a['source'])==$expected,'deleted metadata');
ensure(count($warnings)===0,'zero printing-handler warnings');
file_put_contents($e.'/functional.json',json_encode(['php'=>PHP_VERSION,'assets'=>count($out),'printing_handler_warnings'=>$warnings,'cold_warm_dimensions_checked'=>count($out),'extra'=>$extra,'elapsed_seconds'=>round(microtime(true)-$start,2)],JSON_PRETTY_PRINT));
echo "done=ok assets=".count($out)." warnings=".count($warnings)."\n";
}

