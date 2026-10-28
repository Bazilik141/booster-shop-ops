<?php
// OWNER-ONLY RD-18 event diagnosis. Read-only event metadata and4 core source hashes.
// Exact main predicate is copied verbatim; it invokes no event action.
ini_set('display_errors','0'); ini_set('log_errors','0');
set_error_handler(static function () { throw new RuntimeException('io_warning'); });
function rd16_event_check(string $pattern, string $action): array {
    // Exact captured hook pairs only. Their implementation and assets are pinned
    // by all twelve added dependency hashes; no extension-wide or glob allowance.
    $reviewedHooks=[['catalog/view/account/account/before', 'extension/ps_enhanced_measurement/analytics/ps_enhanced_measurement.eventCatalogViewAccountAccountBefore'],
        ['catalog/view/common/header/before', 'extension/ps_enhanced_measurement/analytics/ps_enhanced_measurement.eventCatalogViewCommonHeaderBefore'],
        ['catalog/view/common/header/before', 'extension/ps_google_recaptcha/captcha/ps_google_recaptcha.eventCatalogViewCommonHeaderBefore'],
        ['catalog/view/common/header/before', 'extension/ps_live_search/module/ps_live_search.eventCatalogViewCommonHeaderBefore']];
    if (in_array([$pattern,$action],$reviewedHooks,true)) return ['allowed'=>true,'relevant'=>true];
    // Both known storefront registration namespaces are treated conservatively,
    // including bare prefix rows which can register an empty/global pattern.
    do {
        $previous=$pattern;
        $pattern=preg_replace('~^(?:catalog|system)(?:/|$)~','',$pattern);
    } while ($pattern!==$previous);
    $action=str_replace('|','.',$action);
    $allowed=true; $relevant=false;
    foreach (['view/account/account/before', 'view/account/account/after', 'view/account/order_list/before', 'view/account/order_list/after', 'view/account/order_info/before', 'view/account/order_info/after', 'view/account/order_history/before', 'view/account/order_history/after', 'view/account/shell/before', 'view/account/shell/after', 'view/common/header/before', 'view/common/header/after', 'view/common/pagination/before', 'view/common/pagination/after', 'controller/account/account/before', 'controller/account/account/after', 'controller/account/account.index/before', 'controller/account/account.index/after', 'controller/account/account|index/before', 'controller/account/account|index/after', 'controller/account/order/before', 'controller/account/order/after', 'controller/account/order.index/before', 'controller/account/order.index/after', 'controller/account/order|index/before', 'controller/account/order|index/after', 'controller/account/order.info/before', 'controller/account/order.info/after', 'controller/account/order|info/before', 'controller/account/order|info/after', 'controller/account/order.history/before', 'controller/account/order.history/after', 'controller/account/order|history/before', 'controller/account/order|history/after', 'controller/account/shell/before', 'controller/account/shell/after', 'controller/account/shell.index/before', 'controller/account/shell.index/after', 'controller/account/shell|index/before', 'controller/account/shell|index/after', 'controller/common/pagination/before', 'controller/common/pagination/after', 'controller/common/pagination.index/before', 'controller/common/pagination.index/after', 'controller/common/pagination|index/before', 'controller/common/pagination|index/after', 'model/account/order/getOrder/before', 'model/account/order/getOrder/after', 'model/account/order/getOrders/before', 'model/account/order/getOrders/after', 'model/account/order/getTotalOrders/before', 'model/account/order/getTotalOrders/after', 'model/account/order/getProducts/before', 'model/account/order/getProducts/after', 'model/account/order/getTotalProductsByOrderId/before', 'model/account/order/getTotalProductsByOrderId/after', 'model/account/order/getOptions/before', 'model/account/order/getOptions/after', 'model/account/order/getSubscription/before', 'model/account/order/getSubscription/after', 'model/account/order/getTotals/before', 'model/account/order/getTotals/after', 'model/account/order/getHistories/before', 'model/account/order/getHistories/after', 'model/account/order/getTotalHistories/before', 'model/account/order/getTotalHistories/after'] as $trigger) {
        if (fnmatch($pattern,$trigger) || fnmatch($pattern . '*',$trigger)) {
            $relevant=true;
            if (!in_array($action,['event/theme','event/theme.index'],true) || strpos($trigger,'view/')!==0 || substr($trigger,-7)!=='/before') $allowed=false;
        }
    }
    return ['allowed'=>$allowed,'relevant'=>$relevant];
}


function rd18d_safe(string $s): array {
    if (strlen($s)<=512 && preg_match('~^[A-Za-z0-9_/*?.|\[\]!-]*$~D',$s)) return ['value'=>$s];
    return ['redacted_sha256'=>hash('sha256',$s),'bytes'=>strlen($s)];
}
function rd18d_collect(mysqli $db, string $root): array {
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
            $rows[]=['trigger'=>rd18d_safe($x['trigger']),'action'=>rd18d_safe($x['action']),'allowed'=>$c['allowed']];
        }
        $q->free();
    } finally { if (!$db->rollback()) throw new RuntimeException('transaction_close'); }
    $sources=[]; $bytes=0;
    foreach (['catalog/controller/startup/event.php','catalog/model/setting/event.php','system/engine/event.php','system/engine/action.php'] as $rel) {
        $p=$root;
        foreach (explode('/',$rel) as $part) { $p.='/'.$part; if (is_link($p)) throw new RuntimeException('linked_source'); }
        if (!is_file($p)) { $sources[$rel]=['state'=>'absent']; continue; }
        $size=filesize($p);
        if ($size<=0 || $size>65536 || ($bytes+$size)>131072) throw new RuntimeException('source_cap');
        $hash=hash_file('sha256',$p); if (!is_string($hash)) throw new RuntimeException('source_read');
        $bytes+=$size; $sources[$rel]=['bytes'=>$size,'sha256'=>$hash];
    }
    $rejected=count(array_filter($rows,static function($row){return !$row['allowed'];}));
    return ['task'=>'RD-18-WP1-event-inspect-r1','enabled_rows_scanned'=>$total,'relevant_rows'=>$rows,
        'unadmitted_count'=>$rejected,'core_sources'=>$sources,'source_bytes_hashed'=>$bytes,
        'classifier_sha256'=>'48f8b701b3034c40256a2a79e8da08e93e8015edf912ac51a9c8fc4cf6c78d6b','main_runner_sha256'=>'f2bd1d4312923ba7c1d4cdc46cf83ccf44a631f50a027e87f9e3a6bd9a428221','main_packet_sha256'=>'2b2713c0ff86a824dacf224b8105ed779d198893943f1d138e8bccb5a3724b91',
        'writes'=>'none','customer_reads'=>'none','admission'=>'none'];
}
// Bootstrap is excluded from the explicitly fake-DB local harness.
if (!defined('RD18D_TEST')) {
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
        $result=rd18d_collect($db,$root); $db->close();
        $result['captured_utc']=gmdate('Y-m-d\TH:i:s\Z'); $result['php']=PHP_VERSION;
        $json=json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        ob_end_clean(); echo $json,"\nRD18_EVENT_INSPECT_OK\n";
    } catch (Throwable $e) {
        while (ob_get_level()) ob_end_clean();
        $codes=['php','root','mapping','connect','schema','readonly','row_cap','field_cap','relevant_cap','linked_source','source_cap','source_read','io_warning','transaction_close','prefix','query'];
        $code=$e instanceof RuntimeException && in_array($e->getMessage(),$codes,true)?$e->getMessage():'internal';
        echo 'RD18_EVENT_INSPECT_STOP reason=',$code,"\n"; exit(1);
    }
}
