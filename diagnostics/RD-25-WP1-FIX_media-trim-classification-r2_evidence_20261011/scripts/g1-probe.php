<?php
namespace Opencart\System\Engine {class Model{public $config;}}
namespace Opencart\Catalog\Model\Tool {
 function hrtime($numbers=false){if(isset($GLOBALS['fixture_time_ns'])){$t=$GLOBALS['fixture_time_ns'];$GLOBALS['fixture_time_ns']+=1300000000;return $t;}return \hrtime($numbers);}
 function imagecolorat($image,$x,$y){$GLOBALS['pixel_reads']=($GLOBALS['pixel_reads']??0)+1;return \imagecolorat($image,$x,$y);}
}
namespace {
error_reporting(E_ALL);$warnings=[];set_error_handler(function($n,$m)use(&$warnings){$warnings[]=[$n,$m];echo 'WARNING:'.$m;return true;});
$w=str_replace('\\','/',__DIR__);$r=dirname($w,2);$f=$w.'/g1-runtime2';$e=$r.'/diagnostics/RD-25-WP1-FIX_media-trim-classification-r2_evidence_20261011';
foreach([$f.'/image/catalog',$f.'/cache'] as $dir){if(!is_dir($dir))mkdir($dir,0755,true);}
define('DIR_IMAGE',$f.'/image/');define('DIR_CACHE',$f.'/cache/');
require $w.'/expected/system/library/image.php';require $w.'/expected/catalog/model/tool/image.php';
$prior=file_get_contents($r.'/work/rd25-wp1-fix-phase1-20261011/expected/catalog/model/tool/image.php');$prior=str_replace('class Image extends','class ImageR1 extends',$prior);file_put_contents($f.'/r1.php',$prior);require $f.'/r1.php';
$m=new \Opencart\Catalog\Model\Tool\Image;$m->config=new class{function get($x){return 'http://fixture/';}};
$r1=new \Opencart\Catalog\Model\Tool\ImageR1;
$method=new \ReflectionMethod($m,'rd25Measure');$method->setAccessible(true);$baseline=new \ReflectionMethod($r1,'rd25Measure');$baseline->setAccessible(true);
$checks=[];function check($name,$ok,$detail=null){global $checks;$checks[]=['name'=>$name,'pass'=>(bool)$ok,'detail'=>$detail];if(!$ok)throw new \RuntimeException($name);}
foreach(['palette','truecolor'] as $mode){
 $g=$mode==='palette'?imagecreate(32,32):imagecreatetruecolor(32,32);imagealphablending($g,false);imagesavealpha($g,true);$bg=imagecolorallocatealpha($g,255,255,255,127);imagefill($g,0,0,$bg);$solid=imagecolorallocatealpha($g,200,50,100,0);$semi=imagecolorallocatealpha($g,120,40,60,63);imagefilledrectangle($g,6,7,22,24,$solid);imagesetpixel($g,5,8,$semi);
 $expected=$baseline->invoke($r1,$g,32,32);$actual=$method->invoke($m,$g,32,32);
 check($mode.' all measurement fields equal r1',$actual==$expected);check($mode.' converted truecolor',imageistruecolor($g));
 check($mode.' raw semi-alpha preserved',imagecolorsforindex($g,\imagecolorat($g,5,8))['alpha']===63);imagedestroy($g);
}
// Fast path ignores interior content for classification, including a transparent hole.
$g=imagecreatetruecolor(32,32);imagealphablending($g,false);imagesavealpha($g,true);imagefill($g,0,0,imagecolorallocate($g,30,40,50));imagefilledrectangle($g,5,5,20,20,imagecolorallocatealpha($g,220,80,90,127));
$expected=$baseline->invoke($r1,$g,32,32);$GLOBALS['pixel_reads']=0;$actual=$method->invoke($m,$g,32,32);
check('inside-band photo exactly four corner reads',$GLOBALS['pixel_reads']===4,$GLOBALS['pixel_reads']);
foreach(['alpha_bbox_16','background_bbox_12','cover_check'] as $key){check('fast nullable diagnostic '.$key,$actual[$key]===null);unset($actual[$key],$expected[$key]);}
check('fast all operational fields exact with interior alpha',$actual==$expected);imagedestroy($g);
foreach(['one','two','three','locked'] as $name){$g=imagecreatetruecolor(64,128);imagefill($g,0,0,imagecolorallocate($g,255,255,255));imagefilledrectangle($g,10,8,54,120,imagecolorallocate($g,40,80,120));imagepng($g,DIR_IMAGE.'catalog/'.$name.'.png');imagedestroy($g);}
$locked_source='catalog/locked.png';$locked_path=DIR_CACHE.'rd25-media-v4-'.hash('sha256',$locked_source).'.json';$lock=fopen($locked_path.'.lock','c');flock($lock,LOCK_EX);$start=microtime(true);$blocked=$m->mediaInfo($locked_source);$elapsed=microtime(true)-$start;flock($lock,LOCK_UN);fclose($lock);
check('metadata LOCK_NB no wait or metadata write',$blocked===[]&&!is_file($locked_path)&&$elapsed<.1,['seconds'=>$elapsed]);
$GLOBALS['fixture_time_ns']=10000000000;$first=$m->mediaInfo('catalog/one.png');unset($GLOBALS['fixture_time_ns']);
check('one admitted source completes and warms',!empty($first)&&is_file(DIR_CACHE.'rd25-media-v4-'.hash('sha256','catalog/one.png').'.json'));
$GLOBALS['pixel_reads']=0;$next=$m->mediaInfo('catalog/two.png');$next2=$m->mediaInfo('catalog/three.png');
check('request cumulative budget returns misses without scan or writes',$next===[]&&$next2===[]&&$GLOBALS['pixel_reads']===0&&!is_file(DIR_CACHE.'rd25-media-v4-'.hash('sha256','catalog/two.png').'.json'));
check('resizeFit returns empty for budget fallback',$m->resizeFit('catalog/two.png',700,875)==='');
check('warm metadata remains available after budget exhausted',$m->mediaInfo('catalog/one.png')==$first);
// Cached diagnostics are deliberately not inputs to any decision path.
$cache=DIR_CACHE.'rd25-media-v4-'.hash('sha256','catalog/one.png').'.json';$nullable=$first;
foreach(['alpha_bbox_16','background_bbox_12','cover_check'] as $key)$nullable[$key]=null;file_put_contents($cache,json_encode($nullable));
check('rd25ReadMedia accepts null diagnostics for cut',$m->mediaInfo('catalog/one.png')==$nullable);
// Same empty URL flows through unchanged Thumb fallback; fits lock is also non-blocking.
$fit=$m->resizeFit('catalog/one.png',700,875);$relative=substr($fit,strlen('http://fixture/image/'));$fitpath=DIR_IMAGE.$relative;
unlink($fitpath);$lock=fopen(DIR_CACHE.'rd25-fit-'.hash('sha256',$relative).'.lock','c');flock($lock,LOCK_EX);$start=microtime(true);$fit=$m->resizeFit('catalog/one.png',700,875);$elapsed=microtime(true)-$start;flock($lock,LOCK_UN);fclose($lock);
check('fit LOCK_NB returns empty without waiting',$fit===''&&$elapsed<.1,$elapsed);
check('printing site handler emitted zero warnings',$warnings===[],$warnings);
file_put_contents($e.'/g1-checks.json',json_encode(['checks'=>$checks,'passed'=>count($checks),'total'=>count($checks)],JSON_PRETTY_PRINT));echo 'G1 checks='.count($checks)."\n";
}
