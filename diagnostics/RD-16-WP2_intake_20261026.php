<?php
// OWNER-ONLY RD-16 event diagnosis. Read-only SQL and thirty-three fixed WP2 owning sources.
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
    foreach (['view/account/account/before','view/account/account/after','view/account/shell/before','view/account/shell/after','view/account/edit/before','view/account/edit/after','view/common/header/before','view/common/header/after','controller/account/account/before','controller/account/account/after','controller/account/account.index/before','controller/account/account.index/after','controller/account/shell/before','controller/account/shell/after','controller/account/shell.index/before','controller/account/shell.index/after','controller/account/edit/before','controller/account/edit/after','controller/account/edit.index/before','controller/account/edit.index/after','controller/account/edit.save/before','controller/account/edit.save/after','controller/account/edit|index/before','controller/account/edit|index/after','controller/account/edit|save/before','controller/account/edit|save/after'] as $trigger) {
        if (fnmatch($pattern,$trigger) || fnmatch($pattern . '*',$trigger)) {
            $relevant=true;
            if (!in_array($action,['event/theme','event/theme.index'],true) || strpos($trigger,'view/')!==0 || substr($trigger,-7)!=='/before') $allowed=false;
        }
    }
    return ['allowed'=>$allowed,'relevant'=>$relevant];
}


function rd16d_safe(string $s): array {
    if (strlen($s)<=512 && preg_match('~^[A-Za-z0-9_/*?%.|\[\]!-]*$~D',$s)) return ['value'=>$s];
    return ['redacted_sha256'=>hash('sha256',$s),'bytes'=>strlen($s)];
}
function rd16d_collect(mysqli $db, string $root): array {
    if (!preg_match('/^[A-Za-z0-9_]+$/D',DB_PREFIX)) throw new RuntimeException('prefix');
    $q=$db->query('SHOW COLUMNS FROM `'.DB_PREFIX.'event`');
    if (!$q) throw new RuntimeException('schema');
    $cols=[]; while ($x=$q->fetch_assoc()) $cols[]=$x['Field']; $q->free();
    if (array_diff(['trigger','action','status'],$cols)) throw new RuntimeException('schema');
    foreach (['theme'=>['route','status'],'layout_route'=>['store_id','layout_id','route'],'layout_module'=>['layout_id','code','position','sort_order']] as $table=>$required) {
        $q=$db->query('SHOW COLUMNS FROM `'.DB_PREFIX.$table.'`');
        if (!$q) throw new RuntimeException('schema');
        $cols=[];while($x=$q->fetch_assoc())$cols[]=$x['Field'];$q->free();
        if(array_diff($required,$cols))throw new RuntimeException('schema');
    }
    if (!$db->query('START TRANSACTION READ ONLY')) throw new RuntimeException('readonly');
    try {
        $theme=[];
        $q=$db->query("SELECT `route`,COUNT(*) AS `total` FROM `".DB_PREFIX."theme` WHERE `status`=1 AND `route` IN ('account/edit','account/shell','common/header') GROUP BY `route` LIMIT 4");
        if(!$q)throw new RuntimeException('metadata_query');
        while($x=$q->fetch_assoc()){$theme[]=['route'=>rd16d_safe($x['route']),'enabled_rows'=>(int)$x['total']];if(count($theme)>3)throw new RuntimeException('metadata_cap');}$q->free();
        $q=$db->query("SELECT `store_id`,`layout_id`,`route` FROM `".DB_PREFIX."layout_route` WHERE 'account/edit' LIKE `route` OR 'account/account' LIKE `route` ORDER BY `store_id`,`route`,`layout_id` LIMIT 17");
        if(!$q)throw new RuntimeException('metadata_query');$layouts=[];
        while($x=$q->fetch_assoc()){$layouts[]=[(int)$x['store_id'],(int)$x['layout_id'],rd16d_safe($x['route'])];if(count($layouts)>16)throw new RuntimeException('metadata_cap');}$q->free();
        $q=$db->query('SELECT `code`,`position`,`sort_order` FROM `'.DB_PREFIX.'layout_module` WHERE `layout_id`=6 ORDER BY `position`,`sort_order`,`code` LIMIT 17');
        if(!$q)throw new RuntimeException('metadata_query');$modules=[];
        while($x=$q->fetch_assoc()){$modules[]=[rd16d_safe($x['code']),rd16d_safe($x['position']),(int)$x['sort_order']];if(count($modules)>16)throw new RuntimeException('metadata_cap');}$q->free();
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
    foreach (['catalog/controller/startup/event.php','catalog/model/setting/event.php','system/engine/event.php','system/engine/action.php','extension/ps_enhanced_measurement/catalog/controller/analytics/ps_enhanced_measurement.php','extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php','extension/ps_google_recaptcha/catalog/controller/captcha/ps_google_recaptcha.php','extension/ps_google_recaptcha/catalog/model/captcha/ps_google_recaptcha.php','extension/ps_live_search/catalog/controller/module/ps_live_search.php','extension/ps_enhanced_measurement/catalog/view/javascript/ps-enhanced-measurement.js','extension/ps_live_search/catalog/view/javascript/ps_live_search.js','extension/ps_live_search/catalog/view/stylesheet/ps_live_search.css','catalog/controller/account/edit.php','catalog/view/template/account/edit.twig','extension/ukrainian/catalog/language/uk-ua/account/edit.php','catalog/view/javascript/booster-account-profile.js','system/library/booster_account_profile.php','system/library/booster_custom_field_guard.php','catalog/controller/account/shell.php','catalog/view/template/account/shell.twig','catalog/view/stylesheet/boostershop-ds.css','catalog/view/template/common/header.twig','extension/ukrainian/catalog/language/uk-ua/account/account.php','catalog/controller/startup/setting.php','catalog/model/setting/setting.php','system/engine/factory.php','catalog/model/account/customer.php','catalog/model/account/custom_field.php','catalog/model/account/address.php','catalog/view/javascript/common.js','catalog/view/javascript/jquery/jquery-3.7.1.min.js','catalog/view/stylesheet/stylesheet.css','catalog/view/stylesheet/bootstrap.css'] as $rel) {
        $p=$root;
        foreach (explode('/',$rel) as $part) { $p.='/'.$part; if (is_link($p)) throw new RuntimeException('linked_source'); }
        if (!is_file($p)) { $sources[$rel]=['state'=>'absent']; continue; }
        $size=filesize($p);
        if ($size>524288 || ($bytes+$size)>2097152) throw new RuntimeException('source_cap');
        $s=file_get_contents($p); if (!is_string($s) || strlen($s)!==$size) throw new RuntimeException('source_read');
        $bytes+=strlen($s); $sources[$rel]=['bytes'=>strlen($s),'sha256'=>hash('sha256',$s),'base64'=>base64_encode($s)];
    }
    return ['task'=>'RD-16-WP2-intake-r1','enabled_rows_scanned'=>$total,'relevant_rows'=>$rows,'sources'=>$sources,'source_bytes'=>$bytes,'theme_enabled'=>$theme,'layout_candidates'=>$layouts,'layout6_modules'=>$modules,'writes'=>'none','customer_reads'=>'none'];
}
// Bootstrap is excluded from the explicitly fake-DB local harness.
if (!defined('RD16D_TEST')) {
    ob_start();
    try {
        if (PHP_SAPI!=='cli' || PHP_MAJOR_VERSION!==8 || PHP_MINOR_VERSION!==0) throw new RuntimeException('php');
        $root='/home2/boosters/public_html';
        if (getcwd()!==$root || realpath($root)!==$root || is_link($root) || is_link($root.'/config.php') || !is_file($root.'/config.php')) throw new RuntimeException('root');
        require $root.'/config.php';
        if (!defined('DIR_OPENCART') || rtrim(DIR_OPENCART,'/')!==$root || !defined('DIR_SYSTEM') || DIR_SYSTEM!==$root.'/system/' || !defined('DIR_APPLICATION') || DIR_APPLICATION!==$root.'/catalog/' || !defined('DIR_EXTENSION') || DIR_EXTENSION!==$root.'/extension/' || !defined('DIR_TEMPLATE') || DIR_TEMPLATE!==$root.'/catalog/view/template/') throw new RuntimeException('mapping');
        mysqli_report(MYSQLI_REPORT_OFF);
        $db=new mysqli(DB_HOSTNAME,DB_USERNAME,DB_PASSWORD,DB_DATABASE,(int)DB_PORT);
        if ($db->connect_errno || !$db->set_charset('utf8mb4')) throw new RuntimeException('connect');
        $result=rd16d_collect($db,$root); $db->close();
        $result['captured_utc']=gmdate('Y-m-d\TH:i:s\Z'); $result['php']=PHP_VERSION;
        $json=json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        ob_end_clean(); echo $json,"\nRD16_WP2_INTAKE_OK\n";
    } catch (Throwable $e) {
        while (ob_get_level()) ob_end_clean();
        $codes=['php','root','mapping','connect','schema','readonly','row_cap','field_cap','relevant_cap','linked_source','source_cap','source_read','io_warning','transaction_close','prefix','query','metadata_query','metadata_cap'];
        $code=$e instanceof RuntimeException && in_array($e->getMessage(),$codes,true)?$e->getMessage():'internal';
        echo 'RD16_WP2_INTAKE_STOP reason=',$code,"\n"; exit(1);
    }
}
