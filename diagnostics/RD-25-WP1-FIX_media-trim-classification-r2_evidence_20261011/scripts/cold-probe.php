<?php
namespace Opencart\System\Engine { class Model { public $config; } }
namespace Opencart\Catalog\Model\Tool {
    // Fixture-only race injection: remove the cache after the production guard.
    function file_get_contents($path) {
        if (($GLOBALS['rd25_race_path'] ?? '') === $path) {
            $GLOBALS['rd25_race_path']='';\unlink($path);\clearstatcache(true,$path);
        }
        if (($GLOBALS['rd25_throw_path'] ?? '') === $path) { throw new \RuntimeException('fixture-read-exception'); }
        return \file_get_contents($path);
    }
    function is_readable($path) {
        return ($GLOBALS['rd25_unreadable_path'] ?? '') === $path ? false : \is_readable($path);
    }
}
namespace {
    $root=str_replace('\\','/',$argv[1]);$mode=$argv[2]??'printing';
    define('DIR_IMAGE',$root.'/image/');define('DIR_CACHE',$root.'/storage/cache/');
    @mkdir(DIR_IMAGE.'fixture',0755,true);@mkdir(DIR_CACHE,0755,true);
    $library=$root.'/system/library/image.php';$original=file_get_contents($library);
    file_put_contents($library,str_replace('public function __construct(string $file) {','public function __construct(string $file) { $GLOBALS["rd25_decodes"]=($GLOBALS["rd25_decodes"]??0)+1;', $original));
    require $library;require $root.'/catalog/model/tool/image.php';
    $model=new \Opencart\Catalog\Model\Tool\Image();$model->config=new class {public function get($key){return 'http://fixture/';}};
    $files=[];
    foreach([['png',64,128,127,[255,255,255]],['jpg',320,400,0,[30,30,30]],['webp',256,384,0,[255,255,255]]] as [$fmt,$w,$h,$alpha,$rgb]) {
        $im=imagecreatetruecolor($w,$h);imagealphablending($im,false);imagesavealpha($im,true);
        imagefill($im,0,0,imagecolorallocatealpha($im,$rgb[0],$rgb[1],$rgb[2],$alpha));
        imagefilledrectangle($im,30,40,$w-30,$h-40,imagecolorallocatealpha($im,40,90,160,0));
        $f='fixture/cold-'.$fmt.'.'.$fmt;
        if($fmt==='png')imagepng($im,DIR_IMAGE.$f);elseif($fmt==='jpg')imagejpeg($im,DIR_IMAGE.$f);else imagewebp($im,DIR_IMAGE.$f);
        imagedestroy($im);touch(DIR_IMAGE.$f,1700000000);$files[]=$f;
    }
    $warnings=[];
    $handler=static function($level,$message,$file,$line)use(&$warnings,$mode){
        $warnings[]=['level'=>$level,'message'=>$message,'file'=>$file,'line'=>$line,'reported_mask_contains_level'=>(bool)(error_reporting()&$level)];
        if($mode==='throwing')throw new \ErrorException($message,0,$level,$file,$line);
        echo '<b>Warning</b>: '.$message.' in '.$file.' on line '.$line;
        return true;
    };
    $mask=error_reporting();set_error_handler($handler);
    function operation($callback) {
        global $warnings;
        $warnings=[];ob_start();$value=null;$exception='';
        try{$value=$callback();}catch(\Throwable $e){$exception=get_class($e).':'.$e->getMessage();}
        $output=ob_get_clean();return ['value'=>$value,'output'=>$output,'warnings'=>$warnings,'exception'=>$exception];
    }
    function row($model,$f) {
        $info=$model->mediaInfo($f);$url=$model->resizeFit($f,700,875);
        $out=DIR_IMAGE.substr($url,strpos($url,'/image/')+7);
        return ['metadata'=>$info,'fit_sha256'=>hash_file('sha256',$out),'fit_dimensions'=>array_slice(getimagesize($out),0,3),'fit_cache_relative'=>substr($out,strlen(DIR_IMAGE))];
    }
    try {
        $cold=operation(fn()=>array_map(fn($f)=>row($model,$f),$files));
        $before=$GLOBALS['rd25_decodes']??0;
        $warm=operation(fn()=>array_map(fn($f)=>row($model,$f),$files));
        $warmDecodes=($GLOBALS['rd25_decodes']??0)-$before;
        $extra=[];
        if($mode==='printing' || $cold['exception']==='') {
            $f=$files[0];$info=$model->mediaInfo($f);$meta=DIR_CACHE.'rd25-media-v4-'.hash('sha256',$f).'.json';
            $source=['relative'=>$f,'mtime'=>$info['mtime'],'size'=>$info['size']];
            $read=new \ReflectionMethod($model,'rd25ReadMedia');$read->setAccessible(true);
            $valid=file_get_contents($meta);
            $GLOBALS['rd25_race_path']=$meta;
            $extra['disappears_after_guard']=operation(fn()=>$read->invoke($model,$meta,$source));
            file_put_contents($meta,$valid);
            $GLOBALS['rd25_unreadable_path']=$meta;
            $extra['unreadable_guard']=operation(fn()=>$read->invoke($model,$meta,$source));$GLOBALS['rd25_unreadable_path']='';
            $GLOBALS['rd25_throw_path']=$meta;
            $extra['exception_restores_handler']=operation(fn()=>$read->invoke($model,$meta,$source));$GLOBALS['rd25_throw_path']='';
            file_put_contents($meta,'corrupt JSON');
            $extra['corrupt_metadata']=operation(fn()=>$model->mediaInfo($f));
            $stale=$model->mediaInfo($f);$stale['version']=2;file_put_contents($meta,json_encode($stale));
            $extra['stale_v2_metadata']=operation(fn()=>$model->mediaInfo($f));
            unlink($meta);mkdir($meta);clearstatcache(true,$meta);
            $extra['directory_not_metadata']=operation(fn()=>$read->invoke($model,$meta,$source));rmdir($meta);
            file_put_contents($meta,$valid);
            foreach($files as $f)unlink(DIR_CACHE.'rd25-media-v4-'.hash('sha256',$f).'.json');clearstatcache();
            $extra['deleted_metadata_regenerates']=operation(fn()=>array_map(fn($f)=>row($model,$f),$files));
            $f=$files[1];touch(DIR_IMAGE.$f,1700000005);clearstatcache();
            $extra['source_stat_invalidates']=operation(fn()=>$model->mediaInfo($f));
        }
        $previous=set_error_handler(static fn()=>false);$restored=$previous===$handler;restore_error_handler();
        $other=operation(static function(){trigger_error('fixture-unrelated-warning',E_USER_WARNING);return 'continued';});
        $result=['php'=>PHP_VERSION,'mode'=>$mode,'cold'=>$cold,'warm'=>$warm,'warm_GD_decodes'=>$warmDecodes,'extra'=>$extra,'site_handler_restored'=>$restored,'error_reporting_mask_unchanged'=>error_reporting()===$mask,'unrelated_warning'=>$other];
    } finally {restore_error_handler();file_put_contents($library,$original);}
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
}
