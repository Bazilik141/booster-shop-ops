<?php
namespace Opencart\System\Engine {class Model{}}
namespace {
$w=str_replace('\\','/',__DIR__);$r=dirname($w,2);$e=$r.'/diagnostics/RD-25-WP1-FIX_media-trim-classification-r2_evidence_20261011';
require $w.'/expected/system/library/image.php';require $w.'/expected/catalog/model/tool/image.php';
$rows=json_decode(file_get_contents($r.'/diagnostics/RD-25-WP1-FIX_media-trim-classification_evidence_20261011/gd-assets.json'),true);
usort($rows,fn($a,$b)=>($b['metadata']['width']*$b['metadata']['height'])<=>($a['metadata']['width']*$a['metadata']['height']));
$m=new \Opencart\Catalog\Model\Tool\Image;$measure=new \ReflectionMethod($m,'rd25Measure');$measure->setAccessible(true);$out=[];
foreach(array_merge(array_slice($rows,0,5),array_values(array_filter($rows,fn($a)=>in_array($a['id'],[97,131,143,186],true)))) as $a){
 $started=microtime(true);$im=new \Opencart\System\Library\Image($r.'/work/rd25-wp1-fix-phase0-20261011/images/'.$a['source']);$decoded=microtime(true);$info=$measure->invoke($m,$im->getImage(),$im->getWidth(),$im->getHeight());$end=microtime(true);
 $out[]=['id'=>$a['id'],'source'=>$a['source'],'pixels'=>$im->getWidth()*$im->getHeight(),'decode_seconds'=>round($decoded-$started,5),'measurement_seconds'=>round($end-$decoded,5),'total_seconds'=>round($end-$started,5),'path'=>$info['cover_check']===null?'four_corners_no_scan':'bit_scan','kind'=>$info['kind']];
}
file_put_contents($e.'/source-performance.json',json_encode(['php'=>PHP_VERSION,'scope'=>'Five largest originals by area plus four prior timing references; native GD, no instrumentation or cache','assets'=>$out],JSON_PRETTY_PRINT));echo json_encode($out)."\n";
}
