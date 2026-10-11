<?php
namespace Opencart\System\Engine {class Model{}}
namespace {
error_reporting(E_ALL);set_error_handler(function($n,$m){throw new \ErrorException($m,0,$n);});
$w=str_replace('\\','/',__DIR__);$r=dirname($w,2);$e=$r.'/diagnostics/RD-25-WP1-FIX_media-trim-classification-r2_evidence_20261011';
require $w.'/expected/system/library/image.php';require $w.'/expected/catalog/model/tool/image.php';
$rows=json_decode(file_get_contents($r.'/diagnostics/RD-25-WP1-FIX_media-trim-classification_evidence_20261011/gd-assets.json'),true);
$model=new \Opencart\Catalog\Model\Tool\Image;$method=new \ReflectionMethod($model,'rd25Measure');$method->setAccessible(true);
$out=[];$mismatch=[];$scanned=0;$fast=0;$diagnostic_changes=[];
foreach($rows as $n=>$row){
 $im=new \Opencart\System\Library\Image($r.'/work/rd25-wp1-fix-phase0-20261011/images/'.$row['source']);
 $start=microtime(true);$full=$method->invoke($model,$im->getImage(),$im->getWidth(),$im->getHeight(),true);$elapsed=microtime(true)-$start;
 $expected=array_intersect_key($row['metadata'],$full);if($expected!=$full)$mismatch[]=['id'=>$row['id'],'scope'=>'full bit scan','actual'=>$full,'expected'=>$expected];
 $production_im=new \Opencart\System\Library\Image($r.'/work/rd25-wp1-fix-phase0-20261011/images/'.$row['source']);
 $production=$method->invoke($model,$production_im->getImage(),$production_im->getWidth(),$production_im->getHeight());
 $is_fast=$production['cover_check']===null;
 if($is_fast){
  $fast++;$diff=[];foreach($expected as $k=>$v){if($production[$k]!=$v)$diff[$k]=['before'=>$v,'after'=>$production[$k]];}
  foreach($diff as $k=>$v){if(!in_array($k,['alpha_bbox_16','background_bbox_12','cover_check'],true)||$production[$k]!==null)$mismatch[]=['id'=>$row['id'],'scope'=>'fast operational field','key'=>$k];}
  $diagnostic_changes[]=['id'=>$row['id'],'source'=>$row['source'],'changes'=>$diff];
 }else{$scanned++;if($production!=$expected)$mismatch[]=['id'=>$row['id'],'scope'=>'production scanned metadata'];}
 $out[]=['id'=>$row['id'],'source'=>$row['source'],'source_pixels'=>$im->getWidth()*$im->getHeight(),'path'=>$is_fast?'corner_only':'scan','full_diagnostic_scan_seconds'=>round($elapsed,6),'metadata'=>$production];
 file_put_contents($w.'/parity-progress.txt',($n+1).'/286');
}
file_put_contents($e.'/parity.json',json_encode(['php'=>PHP_VERSION,'assets'=>count($out),'full_bit_scan_all_fields_match'=>count($rows)-count(array_filter($mismatch,fn($m)=>$m['scope']==='full bit scan')),'production_scanned'=>$scanned,'production_fast'=>$fast,'mismatches'=>$mismatch,'diagnostic_field_changes'=>$diagnostic_changes,'results'=>$out],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
if($mismatch)throw new \RuntimeException('Parity mismatch');echo "parity=286/286 scanned=$scanned fast=$fast\n";
}
