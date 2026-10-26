<?php
// OWNER-ONLY RD-16 event diagnosis. Read-only SQL and four fixed core sources.
ini_set('display_errors','0'); ini_set('log_errors','0');
set_error_handler(static function () { throw new RuntimeException('io_warning'); });
function rd16_event_check(string $pattern, string $action): array {
    // Both known storefront registration namespaces are treated conservatively,
    // including bare prefix rows which can register an empty/global pattern.
    do {
        $previous=$pattern;
        $pattern=preg_replace('~^(?:catalog|system)(?:/|$)~','',$pattern);
    } while ($pattern!==$previous);
    $action=str_replace('|','.',$action);
    $allowed=true; $relevant=false;
    foreach (['view/account/account/before','view/account/account/after','view/account/shell/before','view/account/shell/after','view/common/header/before','view/common/header/after','controller/account/account/before','controller/account/account/after','controller/account/shell/before','controller/account/shell/after','controller/account/account.index/before','controller/account/account.index/after','controller/account/shell.index/before','controller/account/shell.index/after'] as $trigger) {
        if (fnmatch($pattern,$trigger) || fnmatch($pattern . '*',$trigger)) {
            $relevant=true;
            if (!in_array($action,['event/theme','event/theme.index'],true) || strpos($trigger,'view/')!==0 || substr($trigger,-7)!=='/before') $allowed=false;
        }
    }
    return ['allowed'=>$allowed,'relevant'=>$relevant];
}


function rd16d_safe(string $s): array {
    if (strlen($s)<=512 && preg_match('~^[A-Za-z0-9_/*?.|\[\]!-]*$~D',$s)) return ['value'=>$s];
    return ['redacted_sha256'=>hash('sha256',$s),'bytes'=>strlen($s)];
}
function rd16d_collect(mysqli $db, string $root): array {
    if (!preg_match('/^[A-Za-z0-9_]+$/D',DB_PREFIX)) throw new RuntimeException('prefix');
    $q=$db->query('SHOW COLUMNS FROM `'.DB_PREFIX.'event`');
    if (!$q) throw new RuntimeException('schema');
    $cols=[]; while ($x=$q->fetch_assoc()) $cols[]=$x['Field']; $q->free();
    if (array_diff(['trigger','action','status'],$cols)) throw new RuntimeException('schema');
    if (!$db->query('START TRANSACTION READ ONLY')) throw new RuntimeException('readonly');
    try {
        $q=$db->query('SELECT `trigger`,`action` FROM `'.DB_PREFIX.'event` WHERE `status`=1 ORDER BY `trigger`,`action` LIMIT 1025');
        if (!$q) throw new RuntimeException('query');
        $total=0; $rows=[];
        while ($x=$q->fetch_assoc()) {
            if (++$total>1024) throw new RuntimeException('row_cap');
            if (strlen($x['trigger'])>4096 || strlen($x['action'])>4096) throw new RuntimeException('field_cap');
            $c=rd16_event_check((string)$x['trigger'],(string)$x['action']);
            if (!$c['relevant']) continue;
            if (count($rows)>=64) throw new RuntimeException('relevant_cap');
            $rows[]=['trigger'=>rd16d_safe($x['trigger']),'action'=>rd16d_safe($x['action']),'allowed'=>$c['allowed']];
        }
        $q->free();
    } finally { if (!$db->rollback()) throw new RuntimeException('transaction_close'); }
    $sources=[]; $bytes=0;
    foreach (['catalog/controller/startup/event.php','catalog/model/setting/event.php','system/engine/event.php','system/engine/action.php'] as $rel) {
        $p=$root;
        foreach (explode('/',$rel) as $part) { $p.='/'.$part; if (is_link($p)) throw new RuntimeException('linked_source'); }
        if (!is_file($p)) { $sources[$rel]=['state'=>'absent']; continue; }
        $size=filesize($p);
        if ($size>65536 || ($bytes+$size)>131072) throw new RuntimeException('source_cap');
        $s=file_get_contents($p); if (!is_string($s) || strlen($s)!==$size) throw new RuntimeException('source_read');
        $bytes+=strlen($s); $sources[$rel]=['bytes'=>strlen($s),'sha256'=>hash('sha256',$s),'base64'=>base64_encode($s)];
    }
    return ['task'=>'RD-16-WP1-event-inspect-r1','enabled_rows_scanned'=>$total,'relevant_rows'=>$rows,'sources'=>$sources,'source_bytes'=>$bytes,'writes'=>'none','customer_reads'=>'none'];
}
// Bootstrap is excluded from the explicitly fake-DB local harness.
if (!defined('RD16D_TEST')) {
    ob_start();
    try {
        if (PHP_SAPI!=='cli' || PHP_MAJOR_VERSION!==8 || PHP_MINOR_VERSION!==0) throw new RuntimeException('php');
        $root='/home2/boosters/public_html';
        if (getcwd()!==$root || realpath($root)!==$root || is_link($root) || is_link($root.'/config.php') || !is_file($root.'/config.php')) throw new RuntimeException('root');
        require $root.'/config.php';
        if (!defined('DIR_OPENCART') || rtrim(DIR_OPENCART,'/')!==$root || !defined('DIR_SYSTEM') || DIR_SYSTEM!==$root.'/system/' || !defined('DIR_APPLICATION') || DIR_APPLICATION!==$root.'/catalog/') throw new RuntimeException('mapping');
        mysqli_report(MYSQLI_REPORT_OFF);
        $db=new mysqli(DB_HOSTNAME,DB_USERNAME,DB_PASSWORD,DB_DATABASE,(int)DB_PORT);
        if ($db->connect_errno || !$db->set_charset('utf8mb4')) throw new RuntimeException('connect');
        $result=rd16d_collect($db,$root); $db->close();
        $result['captured_utc']=gmdate('Y-m-d\TH:i:s\Z'); $result['php']=PHP_VERSION;
        $json=json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        ob_end_clean(); echo $json,"\nRD16_EVENT_INSPECT_OK\n";
    } catch (Throwable $e) {
        while (ob_get_level()) ob_end_clean();
        $codes=['php','root','mapping','connect','schema','readonly','row_cap','field_cap','relevant_cap','linked_source','source_cap','source_read','io_warning','transaction_close','prefix','query'];
        $code=$e instanceof RuntimeException && in_array($e->getMessage(),$codes,true)?$e->getMessage():'internal';
        echo 'RD16_EVENT_INSPECT_STOP reason=',$code,"\n"; exit(1);
    }
}
