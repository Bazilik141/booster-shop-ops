<?php
namespace Opencart\System\Engine {class Model{}}
namespace {
 $w=str_replace('\\','/',__DIR__);$r=dirname($w,2);$e=$r.'/diagnostics/RD-25-WP1-FIX_media-trim-classification_evidence_20261011';
 require $w.'/expected/system/library/image.php';require $w.'/expected/catalog/model/tool/image.php';
 $rows=json_decode(file_get_contents($e.'/gd-assets.json'),true);$m=new \Opencart\Catalog\Model\Tool\Image;$method=new \ReflectionMethod($m,'rd25Measure');$method->setAccessible(true);$out=[];
 foreach([97,131,143,186] as $id){$a=array_values(array_filter($rows,fn($a)=>$a['id']===$id))[0];$start=microtime(true);$im=new \Opencart\System\Library\Image($r.'/work/rd25-wp1-fix-phase0-20261011/images/'.$a['source']);$measured=$method->invoke($m,$im->getImage(),$im->getWidth(),$im->getHeight());$out[]=['id'=>$id,'source'=>$a['source'],'pixels'=>$im->getWidth()*$im->getHeight(),'decode_and_measure_seconds'=>round(microtime(true)-$start,4),'kind'=>$measured['kind']];}
 file_put_contents($e.'/cold-performance.json',json_encode(['php'=>PHP_VERSION,'scope'=>'Local source decode + uncached pixel scan, excludes encoding/network/host load','assets'=>$out],JSON_PRETTY_PRINT));echo json_encode($out)."\n";
}
