<?php
/** OWNER-RUN transport observation only. CLI stdin, no staged runner.
 * Pins only 3 existing files by SHA256. No app/config/DB/keys/customer reads.
 * 3 unauthenticated HEAD samples from CLI and 3 from one sealed temporary
 * web endpoint; no JSON API payload, contacts, addresses or shipment mutation.
 * Sample limits: connect5s, total10s. SSL verification remains ON.
 * Source/code/deployment state is not changed; only the random TTL web probe
 * is created then deleted by exact owned bytes. Diagnostics are not a fix.
 */
const NPD_HELPERS_B64 = 'ZnVuY3Rpb24gbnBkX2Vycm9yX2NsYXNzKGludCAkZXJybm8pOiBzdHJpbmcgewogICAgJG1hcD1bMD0+J25vbmUnLDU9Pidwcm94eV9yZXNvbHZlJyw2PT4naG9zdF9yZXNvbHZlJyw3PT4nY29ubmVjdCcsMjg9Pid0aW1lb3V0JywzNT0+J1RMU19jb25uZWN0Jyw1MT0+J2NlcnRpZmljYXRlJyw1OD0+J2xvY2FsX2NlcnRpZmljYXRlJyw2MD0+J2NlcnRpZmljYXRlX3ZlcmlmeScsNzc9PidDQV9maWxlJyw4Mz0+J2lzc3Vlcl9jaGVjayddOwogICAgcmV0dXJuICRtYXBbJGVycm5vXT8/J290aGVyX3RyYW5zcG9ydCc7Cn0KZnVuY3Rpb24gbnBkX3NhbXBsZShzdHJpbmcgJGxhYmVsLGJvb2wgJGlwdjQpOiBhcnJheSB7CiAgICAkY3VybD1jdXJsX2luaXQoKTtpZigkY3VybD09PWZhbHNlKXRocm93IG5ldyBSdW50aW1lRXhjZXB0aW9uKCdjdXJsX2luaXRfZmFpbGVkJyk7CiAgICB0cnkgewogICAgICAgICRvcHRzPVtDVVJMT1BUX1VSTD0+J2h0dHBzOi8vYXBpLm5vdmFwb3NodGEudWEvdjIuMC9qc29uL0NvdW50ZXJwYXJ0eS9nZXRDb3VudGVycGFydGllcycsCiAgICAgICAgICAgIENVUkxPUFRfTk9CT0RZPT50cnVlLENVUkxPUFRfUkVUVVJOVFJBTlNGRVI9PmZhbHNlLENVUkxPUFRfRk9MTE9XTE9DQVRJT049PmZhbHNlLAogICAgICAgICAgICBDVVJMT1BUX0hUVFBfVkVSU0lPTj0+Q1VSTF9IVFRQX1ZFUlNJT05fMV8xLENVUkxPUFRfQ09OTkVDVFRJTUVPVVQ9PjUsQ1VSTE9QVF9USU1FT1VUPT4xMCwKICAgICAgICAgICAgQ1VSTE9QVF9TU0xfVkVSSUZZUEVFUj0+dHJ1ZSxDVVJMT1BUX1NTTF9WRVJJRllIT1NUPT4yLAogICAgICAgICAgICBDVVJMT1BUX1VTRVJBR0VOVD0+J0Jvb3N0ZXJTaG9wLUFDQzAwOS1OUC1UcmFuc3BvcnQvMS4wJywKICAgICAgICAgICAgQ1VSTE9QVF9IRUFERVI9PmZhbHNlLENVUkxPUFRfV1JJVEVGVU5DVElPTj0+c3RhdGljIGZ1bmN0aW9uKCRoLCRjaHVuayl7cmV0dXJuIHN0cmxlbigkY2h1bmspO30sCiAgICAgICAgICAgIENVUkxPUFRfSEVBREVSRlVOQ1RJT049PnN0YXRpYyBmdW5jdGlvbigkaCwkY2h1bmspe3JldHVybiBzdHJsZW4oJGNodW5rKTt9XTsKICAgICAgICAvLyBNYXRjaCB0aGUgbW9kdWxlJ3MgVENQIGtlZXBhbGl2ZSBzZXR0aW5nczsgbm8ga2V5LCBKU09OLCBQT1NUIG9yIGNvb2tpZXMuCiAgICAgICAgaWYoZGVmaW5lZCgnQ1VSTE9QVF9UQ1BfS0VFUEFMSVZFJykpeyRvcHRzW0NVUkxPUFRfVENQX0tFRVBBTElWRV09MTskb3B0c1tDVVJMT1BUX1RDUF9LRUVQSURMRV09MTskb3B0c1tDVVJMT1BUX1RDUF9LRUVQSU5UVkxdPTE7fQogICAgICAgIGlmKCRpcHY0KSRvcHRzW0NVUkxPUFRfSVBSRVNPTFZFXT1DVVJMX0lQUkVTT0xWRV9WNDsKICAgICAgICBpZighY3VybF9zZXRvcHRfYXJyYXkoJGN1cmwsJG9wdHMpKXRocm93IG5ldyBSdW50aW1lRXhjZXB0aW9uKCdjdXJsX29wdGlvbnNfZmFpbGVkJyk7CiAgICAgICAgJG9rPWN1cmxfZXhlYygkY3VybCk7JGVycm5vPWN1cmxfZXJybm8oJGN1cmwpOyRpbmZvPWN1cmxfZ2V0aW5mbygkY3VybCk7CiAgICAgICAgJGFwcD1kZWZpbmVkKCdDVVJMSU5GT19BUFBDT05ORUNUX1RJTUUnKT9jdXJsX2dldGluZm8oJGN1cmwsQ1VSTElORk9fQVBQQ09OTkVDVF9USU1FKTpudWxsOwogICAgICAgICRwZWVyPShzdHJpbmcpKCRpbmZvWydwcmltYXJ5X2lwJ10/PycnKTsKICAgICAgICBpZigkcGVlciE9PScnICYmIGZpbHRlcl92YXIoJHBlZXIsRklMVEVSX1ZBTElEQVRFX0lQKT09PWZhbHNlKSRwZWVyPSdpbnZhbGlkJzsKICAgICAgICAkdGltaW5ncz1bXTtmb3JlYWNoKFsnbmFtZWxvb2t1cF90aW1lJywnY29ubmVjdF90aW1lJywncHJldHJhbnNmZXJfdGltZScsJ3N0YXJ0dHJhbnNmZXJfdGltZScsJ3RvdGFsX3RpbWUnXSBhcyAka2V5KXskdGltaW5nc1ska2V5LidfbXMnXT1yb3VuZCgoZmxvYXQpKCRpbmZvWyRrZXldPz8wKSoxMDAwLDMpO30KICAgICAgICAkdGltaW5nc1snYXBwY29ubmVjdF90aW1lX21zJ109aXNfbnVtZXJpYygkYXBwKT9yb3VuZCgoZmxvYXQpJGFwcCoxMDAwLDMpOm51bGw7CiAgICAgICAgcmV0dXJuIFsnc2FtcGxlJz0+JGxhYmVsLCdtZXRob2QnPT4nSEVBRCcsJ2lwX21vZGUnPT4kaXB2ND8nSVB2NCc6J2RlZmF1bHQnLCdjdXJsX3N1Y2Nlc3MnPT4kb2shPT1mYWxzZSwKICAgICAgICAgICAgJ2N1cmxfZXJybm8nPT4kZXJybm8sJ2Vycm9yX2NsYXNzJz0+bnBkX2Vycm9yX2NsYXNzKCRlcnJubyksJ2h0dHBfY29kZSc9PihpbnQpKCRpbmZvWydodHRwX2NvZGUnXT8/MCksCiAgICAgICAgICAgICdzc2xfdmVyaWZ5X3Jlc3VsdCc9PihpbnQpKCRpbmZvWydzc2xfdmVyaWZ5X3Jlc3VsdCddPz8tMSksJ3BlZXJfaXAnPT4kcGVlciwndGltaW5ncyc9PiR0aW1pbmdzXTsKICAgIH0gZmluYWxseSB7Y3VybF9jbG9zZSgkY3VybCk7fQp9CmZ1bmN0aW9uIG5wZF9jb2xsZWN0KCk6IGFycmF5IHsKICAgIGlmKCFmdW5jdGlvbl9leGlzdHMoJ2N1cmxfdmVyc2lvbicpKXRocm93IG5ldyBSdW50aW1lRXhjZXB0aW9uKCdjdXJsX2V4dGVuc2lvbl91bmF2YWlsYWJsZScpOwogICAgJHY9Y3VybF92ZXJzaW9uKCk7JHZlcnNpb249KHN0cmluZykoJHZbJ3ZlcnNpb24nXT8/J3Vua25vd24nKTskc3NsPShzdHJpbmcpKCR2Wydzc2xfdmVyc2lvbiddPz8ndW5rbm93bicpOwogICAgZm9yZWFjaChbJHZlcnNpb24sJHNzbF0gYXMgJHZhbHVlKWlmKHN0cmxlbigkdmFsdWUpPjE2MCB8fCBwcmVnX21hdGNoKCcvW1x4MDAtXHgxRlx4N0ZdLycsJHZhbHVlKSl0aHJvdyBuZXcgUnVudGltZUV4Y2VwdGlvbigndW5leHBlY3RlZF92ZXJzaW9uX3NoYXBlJyk7CiAgICAkc2FtcGxlcz1bXTtmb3JlYWNoKFtbJ2RlZmF1bHRfMScsZmFsc2VdLFsnZGVmYXVsdF8yJyxmYWxzZV0sWydJUHY0Jyx0cnVlXV0gYXMgJHNwZWMpJHNhbXBsZXNbXT1ucGRfc2FtcGxlKCRzcGVjWzBdLCRzcGVjWzFdKTsKICAgIHJldHVybiBbJ3BocCc9PlBIUF9WRVJTSU9OLCdzYXBpJz0+UEhQX1NBUEksJ2xpYmN1cmwnPT4kdmVyc2lvbiwnVExTX2JhY2tlbmQnPT4kc3NsLAogICAgICAgICdpcHY2X3N1cHBvcnRlZCc9PmRlZmluZWQoJ0NVUkxfVkVSU0lPTl9JUFY2Jyk/KCgoaW50KSgkdlsnZmVhdHVyZXMnXT8/MCkmQ1VSTF9WRVJTSU9OX0lQVjYpIT09MCk6bnVsbCwKICAgICAgICAnc2FtcGxlcyc9PiRzYW1wbGVzXTsKfQpmdW5jdGlvbiBucGRfYXNzZXJ0X3J1bnRpbWUoYXJyYXkgJHIsYm9vbCAkd2ViKTogdm9pZCB7CiAgICBpZighaXNfc3RyaW5nKCRyWydwaHAnXT8/bnVsbCl8fCFwcmVnX21hdGNoKCcvXjhcLjBcLlswLTldKyQvRCcsJHJbJ3BocCddKXx8KCRyWydzYXBpJ10/P251bGwpIT09KCR3ZWI/J2xpdGVzcGVlZCc6J2NsaScpKXRocm93IG5ldyBSdW50aW1lRXhjZXB0aW9uKCd1bmV4cGVjdGVkX3J1bnRpbWUnKTsKICAgIGlmKCFpc19zdHJpbmcoJHJbJ2xpYmN1cmwnXT8/bnVsbCl8fCFpc19zdHJpbmcoJHJbJ1RMU19iYWNrZW5kJ10/P251bGwpfHwhaXNfYXJyYXkoJHJbJ3NhbXBsZXMnXT8/bnVsbCl8fGNvdW50KCRyWydzYW1wbGVzJ10pIT09Myl0aHJvdyBuZXcgUnVudGltZUV4Y2VwdGlvbigncnVudGltZV9wYXlsb2FkX3NoYXBlJyk7CiAgICBmb3JlYWNoKCRyWydzYW1wbGVzJ10gYXMgJGk9PiRzYW1wbGUpewogICAgICAgIGlmKCgkc2FtcGxlWydzYW1wbGUnXT8/bnVsbCkhPT1bJ2RlZmF1bHRfMScsJ2RlZmF1bHRfMicsJ0lQdjQnXVskaV18fCgkc2FtcGxlWydtZXRob2QnXT8/bnVsbCkhPT0nSEVBRCcKICAgICAgICAgICAgfHwhaXNfaW50KCRzYW1wbGVbJ2N1cmxfZXJybm8nXT8/bnVsbCl8fCgkc2FtcGxlWydlcnJvcl9jbGFzcyddPz9udWxsKSE9PW5wZF9lcnJvcl9jbGFzcygkc2FtcGxlWydjdXJsX2Vycm5vJ10pCiAgICAgICAgICAgIHx8IWlzX2ludCgkc2FtcGxlWydodHRwX2NvZGUnXT8/bnVsbCl8fCFpc19hcnJheSgkc2FtcGxlWyd0aW1pbmdzJ10/P251bGwpKXRocm93IG5ldyBSdW50aW1lRXhjZXB0aW9uKCdzYW1wbGVfcGF5bG9hZF9zaGFwZScpOwogICAgfQp9Cg==';
const NPD_HELPERS_SHA = 'ecd6a39c6d3d8e79792bc4571806cc607c63355ba39d0f9ee5accb1eaf72feab';
function npd_error_class(int $errno): string {
    $map=[0=>'none',5=>'proxy_resolve',6=>'host_resolve',7=>'connect',28=>'timeout',35=>'TLS_connect',51=>'certificate',58=>'local_certificate',60=>'certificate_verify',77=>'CA_file',83=>'issuer_check'];
    return $map[$errno]??'other_transport';
}
function npd_sample(string $label,bool $ipv4): array {
    $curl=curl_init();if($curl===false)throw new RuntimeException('curl_init_failed');
    try {
        $opts=[CURLOPT_URL=>'https://api.novaposhta.ua/v2.0/json/Counterparty/getCounterparties',
            CURLOPT_NOBODY=>true,CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_USERAGENT=>'BoosterShop-ACC009-NP-Transport/1.0',
            CURLOPT_HEADER=>false,CURLOPT_WRITEFUNCTION=>static function($h,$chunk){return strlen($chunk);},
            CURLOPT_HEADERFUNCTION=>static function($h,$chunk){return strlen($chunk);}];
        // Match the module's TCP keepalive settings; no key, JSON, POST or cookies.
        if(defined('CURLOPT_TCP_KEEPALIVE')){$opts[CURLOPT_TCP_KEEPALIVE]=1;$opts[CURLOPT_TCP_KEEPIDLE]=1;$opts[CURLOPT_TCP_KEEPINTVL]=1;}
        if($ipv4)$opts[CURLOPT_IPRESOLVE]=CURL_IPRESOLVE_V4;
        if(!curl_setopt_array($curl,$opts))throw new RuntimeException('curl_options_failed');
        $ok=curl_exec($curl);$errno=curl_errno($curl);$info=curl_getinfo($curl);
        $app=defined('CURLINFO_APPCONNECT_TIME')?curl_getinfo($curl,CURLINFO_APPCONNECT_TIME):null;
        $peer=(string)($info['primary_ip']??'');
        if($peer!=='' && filter_var($peer,FILTER_VALIDATE_IP)===false)$peer='invalid';
        $timings=[];foreach(['namelookup_time','connect_time','pretransfer_time','starttransfer_time','total_time'] as $key){$timings[$key.'_ms']=round((float)($info[$key]??0)*1000,3);}
        $timings['appconnect_time_ms']=is_numeric($app)?round((float)$app*1000,3):null;
        return ['sample'=>$label,'method'=>'HEAD','ip_mode'=>$ipv4?'IPv4':'default','curl_success'=>$ok!==false,
            'curl_errno'=>$errno,'error_class'=>npd_error_class($errno),'http_code'=>(int)($info['http_code']??0),
            'ssl_verify_result'=>(int)($info['ssl_verify_result']??-1),'peer_ip'=>$peer,'timings'=>$timings];
    } finally {curl_close($curl);}
}
function npd_collect(): array {
    if(!function_exists('curl_version'))throw new RuntimeException('curl_extension_unavailable');
    $v=curl_version();$version=(string)($v['version']??'unknown');$ssl=(string)($v['ssl_version']??'unknown');
    foreach([$version,$ssl] as $value)if(strlen($value)>160 || preg_match('/[\x00-\x1F\x7F]/',$value))throw new RuntimeException('unexpected_version_shape');
    $samples=[];foreach([['default_1',false],['default_2',false],['IPv4',true]] as $spec)$samples[]=npd_sample($spec[0],$spec[1]);
    return ['php'=>PHP_VERSION,'sapi'=>PHP_SAPI,'libcurl'=>$version,'TLS_backend'=>$ssl,
        'ipv6_supported'=>defined('CURL_VERSION_IPV6')?(((int)($v['features']??0)&CURL_VERSION_IPV6)!==0):null,
        'samples'=>$samples];
}
function npd_assert_runtime(array $r,bool $web): void {
    if(!is_string($r['php']??null)||!preg_match('/^8\.0\.[0-9]+$/D',$r['php'])||($r['sapi']??null)!==($web?'litespeed':'cli'))throw new RuntimeException('unexpected_runtime');
    if(!is_string($r['libcurl']??null)||!is_string($r['TLS_backend']??null)||!is_array($r['samples']??null)||count($r['samples'])!==3)throw new RuntimeException('runtime_payload_shape');
    foreach($r['samples'] as $i=>$sample){
        if(($sample['sample']??null)!==['default_1','default_2','IPv4'][$i]||($sample['method']??null)!=='HEAD'
            ||!is_int($sample['curl_errno']??null)||($sample['error_class']??null)!==npd_error_class($sample['curl_errno'])
            ||!is_int($sample['http_code']??null)||!is_array($sample['timings']??null))throw new RuntimeException('sample_payload_shape');
    }
}

function npd_fail(string $reason): void {throw new RuntimeException($reason);}
final class NpdProbeCleanupFailure extends RuntimeException {
    public string $probeName;public string $cleanupStage;public ?string $primaryError;
    public function __construct(string $name,string $stage,?Throwable $primary) {
        parent::__construct('probe_cleanup_requires_assessment');$this->probeName=$name;$this->cleanupStage=$stage;
        $this->primaryError=$primary===null?null:($primary instanceof JsonException?'web_payload_invalid':$primary->getMessage());
    }
}
function npd_probe_hash(string $path) {return hash_file('sha256',$path);}
function npd_probe_unlink(string $path): bool {return unlink($path);}
function npd_probe_cleanup(string $path,string $name,string $owned,?Throwable $primary): void {
    $stage='stat';
    try {
        clearstatcache(true,$path);$stat=lstat($path);
        if(!is_array($stat)||is_link($path)||!is_file($path))npd_fail('probe_unknown_type');
        $stage='hash';if(npd_probe_hash($path)!==hash('sha256',$owned))npd_fail('probe_changed');
        $stage='unlink';if(!npd_probe_unlink($path))npd_fail('probe_unlink_failed');
        $stage='absence';clearstatcache(true,$path);
        if(file_exists($path)||is_link($path))npd_fail('probe_absence_not_confirmed');
    } catch(Throwable $cleanupError) {throw new NpdProbeCleanupFailure($name,$stage,$primary);}
}
function npd_abs(string $path): void {
    if($path===''||$path[0]!=='/'||strpos($path,'..')!==false)npd_fail('unsafe_path');
    $walk='';foreach(explode('/',ltrim($path,'/')) as $part){$walk.='/'.$part;if($part===''||is_link($walk))npd_fail('linked_path');}
    if(realpath($path)!==$path)npd_fail('unresolved_path');
}
function npd_sources(string $root): array {
    $pins=[
        'extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php'=>'2138e15643ce4bced1b49698955c78f443617e4b59877325113261042d1c7b93',
        'extension/PintaNovaPoshtaCod/system/library/pintanovaposhta/pintacounterparty.php'=>'44d138edca35b83eb7e124161cf8fc0fc19d084db8a635f32b901cc00b773bb7',
        'extension/PintaNovaPoshtaCod/system/library/pintanovaposhta/pintanovaposhtaapi.php'=>'729c3c6cee6b04f925967a9e42277b9702e51876b37d6df119ba40258b20b19c'];
    $rows=[];foreach($pins as $rel=>$expected){$path=$root.'/'.$rel;npd_abs($path);if(!is_file($path)||filesize($path)>4000000)npd_fail('source_type_or_size');$actual=hash_file('sha256',$path);$rows[]=['path'=>$rel,'sha256'=>$actual,'matches'=>$actual===$expected];if($actual!==$expected)npd_fail('source_identity_changed:'.$rel);}
    return $rows;
}
function npd_web(string $root): array {
    $nonce=bin2hex(random_bytes(16));$name='acc009-np-transport-'.bin2hex(random_bytes(16)).'.php';$path=$root.'/'.$name;
    $helpers=base64_decode(NPD_HELPERS_B64,true);if(!is_string($helpers)||hash('sha256',$helpers)!==NPD_HELPERS_SHA)npd_fail('helper_identity');
    $body='<?php if(($_SERVER["REQUEST_METHOD"]??"")!=="GET"||time()>'.(time()+120).'||!is_string($_GET["nonce"]??null)||!hash_equals("'.$nonce.'",$_GET["nonce"])){http_response_code(404);exit;}header("Content-Type: application/json");header("Cache-Control: no-store");ini_set("display_errors","0");set_error_handler(static function(){throw new RuntimeException("probe_runtime_warning");});' . $helpers . '$r=npd_collect();echo json_encode(["schema"=>"acc009.np_transport.web.v1","nonce"=>"'.$nonce.'","runtime"=>$r],JSON_THROW_ON_ERROR);';
    $handle=fopen($path,'x');if($handle===false)npd_fail('probe_create');$owned='';$primary=null;
    try {
        $count=fwrite($handle,$body);$owned=substr($body,0,$count===false?0:$count);$closed=fclose($handle);$handle=null;
        if($count!==strlen($body)||!$closed||!chmod($path,0604)||hash_file('sha256',$path)!==hash('sha256',$body))npd_fail('probe_write');
        $ctx=stream_context_create(['http'=>['method'=>'GET','timeout'=>65,'ignore_errors'=>true,'follow_location'=>0,'header'=>"User-Agent: BoosterShop-ACC009-Runtime/1.0\r\nConnection: close\r\n"],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
        $http_response_header=[];$data=@file_get_contents('https://boostershop.website/'.$name.'?nonce='.$nonce,false,$ctx,0,65537);
        if(!is_string($data)||strlen($data)>65536||!preg_match('~^HTTP/\S+ 200(?: |$)~D',$http_response_header[0]??''))npd_fail('web_collection_transport');
        $j=json_decode($data,true,32,JSON_THROW_ON_ERROR);
        if(!is_array($j)||($j['schema']??null)!=='acc009.np_transport.web.v1'||($j['nonce']??null)!==$nonce||!is_array($j['runtime']??null))npd_fail('web_collection_identity');
        npd_assert_runtime($j['runtime'],true);return $j['runtime'];
    } catch(Throwable $e) {$primary=$e;throw $e;
    } finally {
        $closeError=null;
        if(is_resource($handle)){try{if(!fclose($handle))npd_fail('probe_close_failed');}catch(Throwable $e){$closeError=$e;}}
        npd_probe_cleanup($path,$name,$owned,$primary??$closeError);
        if($closeError!==null)throw new NpdProbeCleanupFailure($name,'close',$primary??$closeError);
    }
}
// NPD_ENTRY - owner-only main, never invoke as an agent.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
set_error_handler(static function($n){if(error_reporting()&$n)throw new RuntimeException('runtime_warning_details_withheld');return true;});
$result=['schema'=>'acc009.np_transport.v1','task'=>'ACC-009-NP-TRANSPORT','utc'=>gmdate('c'),'status'=>'incomplete','db_access'=>'NONE','business_API_calls'=>'NONE','source_mutations'=>0,'probe_cleanup'=>'not_created'];
try {
    if(PHP_VERSION_ID<80000||PHP_VERSION_ID>=80100)npd_fail('PHP8_0_required');
    $root=realpath(getcwd());if($root!=='/home2/boosters/public_html')npd_fail('verified_webroot_required');npd_abs($root);
    $result['sources']=npd_sources($root);
    $result['cli']=npd_collect();npd_assert_runtime($result['cli'],false);
    try{$result['web']=npd_web($root);$result['probe_cleanup']='ok';$result['status']='complete';}
    catch(Throwable $e){
        $result['status']='partial';
        if($e instanceof NpdProbeCleanupFailure){$result['error']='probe_cleanup_requires_assessment';$result['probe_cleanup']='STOP_manual_assessment';$result['probe_basename']=$e->probeName;$result['cleanup_stage']=$e->cleanupStage;$result['primary_error']=$e->primaryError;}
        else{$result['error']=$e instanceof JsonException?'web_payload_invalid':$e->getMessage();$result['probe_cleanup']='completed_or_not_created';}
    }
    echo 'NPDIAG_JSON='.json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    echo 'done=observed' . "\n";
    exit($result['status']==='complete'?0:2);
} catch(Throwable $e){$result['error']=$e instanceof JsonException?'payload_invalid':$e->getMessage();echo 'NPDIAG_JSON='.json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";fwrite(STDERR,"error=diagnostic_incomplete\n");exit(1);}
