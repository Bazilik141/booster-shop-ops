#!/usr/bin/env bash
# Owner-run only. Read fixed public source files and technical metadata.
# No source/DB/cache writes. Creates only two task-unique private pull artifacts.
set +x
set -euo pipefail
export LC_ALL=C
umask 077
stop() { printf 'STOP=%s\n' "$1" >&2; exit 1; }
cd "$HOME/public_html" || stop webroot_missing
[[ ! -L "$HOME/public_html" ]] || stop linked_webroot
[[ $(pwd -P) == "$HOME/public_html" ]] || stop unexpected_webroot
for rd18_dir in "$HOME/private" "$HOME/private/booster-pulls"; do
  [[ ! -L "$rd18_dir" ]] || stop linked_private_directory
  if [[ ! -e "$rd18_dir" ]]; then mkdir -m 700 -- "$rd18_dir"; fi
  [[ -d "$rd18_dir" ]] || stop private_directory_not_directory
done
rd18_archive="$HOME/private/booster-pulls/RD-18-display-source-20261028-r1.tar.gz"
rd18_manifest="$HOME/private/booster-pulls/RD-18-display-source-20261028-r1.manifest.txt"
[[ ! -e "$rd18_archive" && ! -L "$rd18_archive" && ! -e "$rd18_manifest" && ! -L "$rd18_manifest" ]] || stop artifact_exists_no_replay
set -C
# Only this numeric owner argument is substituted by the reviewed packet.
rd18_order_id=__RD18_ORDER_ID__
[[ "$rd18_order_id" =~ ^[1-9][0-9]{0,9}$ && "$rd18_order_id" -le 2147483647 ]] || stop invalid_order_argument
rd18_files=(system/library/language.php catalog/controller/startup/language.php catalog/controller/startup/extension.php)
[[ ! -L catalog/controller/event/language.php ]] || stop linked_language_event_source
if [[ -f catalog/controller/event/language.php ]]; then rd18_files+=(catalog/controller/event/language.php); fi
[[ ! -L system/config/catalog.php ]] || stop linked_catalog_preaction_source
if [[ -f system/config/catalog.php ]]; then rd18_files+=(system/config/catalog.php); fi
rd18_language_count=0
# Bounded filename discovery only; no arbitrary recursive source export.
for rd18_language_root in extension catalog/language; do
  [[ ! -L "$rd18_language_root" ]] || stop linked_language_root
  [[ -d "$rd18_language_root" ]] || continue
  rd18_candidates=$(find "$rd18_language_root" -maxdepth 12 \( -type f -o -type l \) -name bank_transfer.php \( -path '*/catalog/language/uk-ua/*' -o -path 'catalog/language/uk-ua/*' \) -print | LC_ALL=C sort) || stop language_discovery_failed
  (( ${#rd18_candidates} <= 4096 )) || stop language_discovery_cap
  while IFS= read -r rd18_candidate; do
    [[ -n "$rd18_candidate" ]] || continue
    [[ "$rd18_candidate" =~ ^[A-Za-z0-9_./-]+$ && ( "$rd18_candidate" == */catalog/language/uk-ua/* || "$rd18_candidate" == catalog/language/uk-ua/* ) && "$rd18_candidate" == */bank_transfer.php ]] || stop unsafe_language_path
    rd18_language_count=$((rd18_language_count + 1))
    (( rd18_language_count <= 8 )) || stop bank_language_count_cap
    rd18_files+=("$rd18_candidate")
  done <<< "$rd18_candidates"
done
(( rd18_language_count > 0 )) || stop bank_language_source_missing

rd18_total=0
for rd18_file in "${rd18_files[@]}"; do
  rd18_walk=.
  IFS=/ read -r -a rd18_parts <<< "$rd18_file"
  for rd18_part in "${rd18_parts[@]}"; do
    rd18_walk="$rd18_walk/$rd18_part"
    [[ ! -L "$rd18_walk" ]] || stop linked_source_component
  done
  [[ -f "$rd18_file" ]] || stop required_source_missing
  rd18_size=$(stat -c %s -- "$rd18_file")
  [[ "$rd18_size" =~ ^[0-9]+$ ]] || stop bad_source_size
  (( rd18_size > 0 && rd18_size <= 2097152 )) || stop source_size_cap
  rd18_total=$((rd18_total + rd18_size))
  (( rd18_total <= 8388608 )) || stop total_source_cap
  # This is an obvious-secret screen, not a guarantee that source has no secrets.
  if grep -Eiq -- '-----BEGIN (RSA |OPENSSH |EC )?PRIVATE KEY-----|sk-ant-(api|oat)-[A-Za-z0-9_-]{20,}|sk-proj-[A-Za-z0-9_-]{20,}|AKIA[0-9A-Z]{16}' "$rd18_file"; then
    stop obvious_secret_signature
  else
    rd18_grep=$?
    [[ "$rd18_grep" == 1 ]] || stop secret_screen_failed
  fi
done
# Screen literal Nova Poshta API keys as well as the generic signatures above.
# This is conservative, not proof that arbitrary source contains no secrets.
if grep -Eiq -- "[\"'](apiKey|api_key|shipping_pinta_nova_poshta_api_key)[\"'][[:space:]]*[:=][=>[:space:]]*[\"'][[:xdigit:]]{32}[\"']" "${rd18_files[@]}"; then
  stop literal_api_key_signature
else
  rd18_grep=$?; [[ "$rd18_grep" == 1 ]] || stop secret_screen_failed
fi
if grep -Eiq -- 'UA[0-9]{2}([[:space:]]?[0-9]){25}' "${rd18_files[@]}"; then stop financial_literal_source_not_exported; else rd18_grep=$?; [[ "$rd18_grep" == 1 ]] || stop financial_screen_failed; fi
rd18_before=$(sha256sum -- "${rd18_files[@]}")
rd18_started=$(date -u +%Y-%m-%dT%H:%M:%SZ)
rd18_php=$(command -v php) || stop php_missing
[[ "$rd18_php" == /* && -f "$rd18_php" && -x "$rd18_php" ]] || stop php_path_invalid
rd18_version=$("$rd18_php" -r 'echo PHP_MAJOR_VERSION,".",PHP_MINOR_VERSION;')
[[ "$rd18_version" == 8.0 ]] || stop php_not_8_0
# Require config only within owner-run PHP; never export it or print credentials.
# Schema is inspected before fixed prepared SELECTs. Only address shape is exported.
rd18_metadata=$(RD18_OWNER_ORDER_ID="$rd18_order_id" "$rd18_php" -d display_errors=0 -d log_errors=0 <<'RD18_PHP'
<?php
ini_set('display_errors','0'); ini_set('log_errors','0');
set_error_handler(static function () { throw new RuntimeException('metadata_warning'); });
function rd16_event_check(string $pattern, string $action): array {
    // Exact captured hook pairs only. Their implementation and assets are pinned
    // by their complete dependency hashes; no extension-wide or glob allowance.
    $reviewedHooks=[['catalog/view/account/account/before', 'extension/ps_enhanced_measurement/analytics/ps_enhanced_measurement.eventCatalogViewAccountAccountBefore'], ['catalog/view/account/order_info/before', 'extension/ps_enhanced_measurement/analytics/ps_enhanced_measurement.eventCatalogViewAccountOrderInfoBefore'], ['catalog/view/common/header/before', 'extension/ps_enhanced_measurement/analytics/ps_enhanced_measurement.eventCatalogViewCommonHeaderBefore'], ['catalog/view/common/header/before', 'extension/ps_google_recaptcha/captcha/ps_google_recaptcha.eventCatalogViewCommonHeaderBefore'], ['catalog/view/common/header/before', 'extension/ps_live_search/module/ps_live_search.eventCatalogViewCommonHeaderBefore']];
    if (in_array([$pattern,$action],$reviewedHooks,true)) return ['allowed'=>true,'relevant'=>true];
    // Both known storefront registration namespaces are treated conservatively,
    // including bare prefix rows which can register an empty/global pattern.
    do {
        $previous=$pattern;
        $pattern=preg_replace('~^(?:catalog|system)(?:/|$)~','',$pattern);
    } while ($pattern!==$previous);
    $action=str_replace('|','.',$action);
    $allowed=true; $relevant=false;
    foreach (['view/account/account/before', 'view/account/account/after', 'view/account/order_list/before', 'view/account/order_list/after', 'view/account/order_info/before', 'view/account/order_info/after', 'view/account/order_history/before', 'view/account/order_history/after', 'view/account/shell/before', 'view/account/shell/after', 'view/common/header/before', 'view/common/header/after', 'view/common/pagination/before', 'view/common/pagination/after', 'controller/account/account/before', 'controller/account/account/after', 'controller/account/account.index/before', 'controller/account/account.index/after', 'controller/account/account|index/before', 'controller/account/account|index/after', 'controller/account/order/before', 'controller/account/order/after', 'controller/account/order.index/before', 'controller/account/order.index/after', 'controller/account/order|index/before', 'controller/account/order|index/after', 'controller/account/order.info/before', 'controller/account/order.info/after', 'controller/account/order|info/before', 'controller/account/order|info/after', 'controller/account/order.history/before', 'controller/account/order.history/after', 'controller/account/order|history/before', 'controller/account/order|history/after', 'controller/account/shell/before', 'controller/account/shell/after', 'controller/account/shell.index/before', 'controller/account/shell.index/after', 'controller/account/shell|index/before', 'controller/account/shell|index/after', 'controller/common/pagination/before', 'controller/common/pagination/after', 'controller/common/pagination.index/before', 'controller/common/pagination.index/after', 'controller/common/pagination|index/before', 'controller/common/pagination|index/after', 'model/account/order/getOrder/before', 'model/account/order/getOrder/after', 'model/account/order/getOrders/before', 'model/account/order/getOrders/after', 'model/account/order/getTotalOrders/before', 'model/account/order/getTotalOrders/after', 'model/account/order/getProducts/before', 'model/account/order/getProducts/after', 'model/account/order/getTotalProductsByOrderId/before', 'model/account/order/getTotalProductsByOrderId/after', 'model/account/order/getOptions/before', 'model/account/order/getOptions/after', 'model/account/order/getSubscription/before', 'model/account/order/getSubscription/after', 'model/account/order/getTotals/before', 'model/account/order/getTotals/after', 'model/account/order/getHistories/before', 'model/account/order/getHistories/after', 'model/account/order/getTotalHistories/before', 'model/account/order/getTotalHistories/after', 'model/account/order.getOrder/before', 'model/account/order.getOrder/after', 'model/account/order.getOrders/before', 'model/account/order.getOrders/after', 'model/account/order.getTotalOrders/before', 'model/account/order.getTotalOrders/after', 'model/account/order.getProducts/before', 'model/account/order.getProducts/after', 'model/account/order.getTotalProductsByOrderId/before', 'model/account/order.getTotalProductsByOrderId/after', 'model/account/order.getOptions/before', 'model/account/order.getOptions/after', 'model/account/order.getSubscription/before', 'model/account/order.getSubscription/after', 'model/account/order.getTotals/before', 'model/account/order.getTotals/after', 'model/account/order.getHistories/before', 'model/account/order.getHistories/after', 'model/account/order.getTotalHistories/before', 'model/account/order.getTotalHistories/after', 'model/catalog/product.getProduct/before', 'model/catalog/product.getProduct/after', 'model/tool/image.resize/before', 'model/tool/image.resize/after', 'model/catalog/product/getProduct/before', 'model/catalog/product/getProduct/after', 'model/tool/image/resize/before', 'model/tool/image/resize/after', 'language/extension/opencart/payment/bank_transfer/before', 'language/extension/opencart/payment/bank_transfer/after'] as $trigger) {
        if (fnmatch($pattern,$trigger) || fnmatch($pattern . '*',$trigger)) {
            $relevant=true;
            if (!in_array($action,['event/theme','event/theme.index'],true) || strpos($trigger,'view/')!==0 || substr($trigger,-7)!=='/before') $allowed=false;
        }
    }
    return ['allowed'=>$allowed,'relevant'=>$relevant];
}


function rd18v_safe(string $s): array {
    if (strlen($s)<=512 && preg_match('~^[A-Za-z0-9_/*?.|\[\]!-]*$~D',$s)) return ['value'=>$s];
    return ['redacted_sha256'=>hash('sha256',$s),'bytes'=>strlen($s)];
}
function rd18v_order_shape(mysqli $db,int $id): array {
    // ONE owner-selected order, only address field presence/shape is emitted.
    // Never select names, phone, email, totals, comments or payment identifiers.
    $q=$db->query('SHOW COLUMNS FROM `'.DB_PREFIX.'order`');if(!$q)throw new RuntimeException('order_schema');
    $cols=[];while($r=$q->fetch_assoc())$cols[]=$r['Field'];$q->free();
    if(array_diff(['order_id','shipping_address_1','shipping_address_2','shipping_address_format','shipping_custom_field'],$cols))throw new RuntimeException('order_schema');
    $st=$db->prepare('SELECT `shipping_address_1`,`shipping_address_2`,`shipping_address_format`,`shipping_custom_field` FROM `'.DB_PREFIX.'order` WHERE `order_id`=? LIMIT 1');
    if(!$st)throw new RuntimeException('shape_query');
    try {
        if(!$st->bind_param('i',$id)||!$st->execute()||!$st->bind_result($a1,$a2,$format,$raw))throw new RuntimeException('shape_query');
        $found=$st->fetch();if($st->errno||($found!==true&&$found!==null))throw new RuntimeException('shape_fetch');if($found===null)return['selected_order_found'=>false];
        $present=static function($v):bool{return is_string($v)&&trim($v)!=='';};
        $fields=is_string($raw)&&strlen($raw)<=65536?json_decode($raw,true,20):null;
        $np=is_array($fields)?($fields['bs_np_v1']??null):null;
        if(is_string($np)&&strlen($np)<=16384)$np=json_decode($np,true,10);
        $labels=is_array($np)&&is_array($np['labels']??null)?$np['labels']:[];
        return['selected_order_found'=>true,'stored_address1_nonempty'=>$present($a1),'stored_address2_nonempty'=>$present($a2),
          'format_nonempty'=>$present($format),'format_has_city'=>is_string($format)&&strpos($format,'{city}')!==false,
          'format_has_address1'=>is_string($format)&&strpos($format,'{address_1}')!==false,'format_has_address2'=>is_string($format)&&strpos($format,'{address_2}')!==false,
          'custom_json_is_map'=>is_array($fields),'np_is_map'=>is_array($np),'np_version1'=>is_array($np)&&($np['version']??null)===1,
          'np_type'=>is_array($np)&&in_array($np['type']??null,['warehouse','poshtomat','courier'],true)?$np['type']:'unknown',
          'np_city_label_nonempty'=>$present($labels['city']??null),'np_point_label_nonempty'=>$present($labels['point']??null),
          'np_house_nonempty'=>is_array($np)&&$present($np['house']??null),'np_flat_nonempty'=>is_array($np)&&$present($np['flat']??null)];
    } finally {$st->close();}
}
function rd18v_collect(mysqli $db, string $root, int $orderId): array {
    if (!preg_match('/^[A-Za-z0-9_]+$/D',DB_PREFIX)) throw new RuntimeException('prefix');
    $q=$db->query('SHOW COLUMNS FROM `'.DB_PREFIX.'event`'); if (!$q) throw new RuntimeException('schema');
    $cols=[]; while ($x=$q->fetch_assoc()) $cols[]=$x['Field']; $q->free();
    if (array_diff(['trigger','action','status'],$cols)) throw new RuntimeException('schema');
    if (!$db->query('START TRANSACTION READ ONLY')) throw new RuntimeException('readonly');
    try {
        $q=$db->query('SELECT `trigger`,`action` FROM `'.DB_PREFIX.'event` WHERE `status`=1 ORDER BY `trigger`,`action` LIMIT 1025');
        if (!$q) throw new RuntimeException('query'); $total=0; $rows=[];
        while ($x=$q->fetch_assoc()) {
            if (++$total>1024 || !isset($x['trigger'],$x['action']) || !is_string($x['trigger']) || !is_string($x['action']) || strlen($x['trigger'])>4096 || strlen($x['action'])>4096) throw new RuntimeException('row_cap_or_type');
            $c=rd16_event_check($x['trigger'],$x['action']); if (!$c['relevant']) continue;
            if (count($rows)>=64) throw new RuntimeException('relevant_cap');
            $rows[]=['trigger'=>rd18v_safe($x['trigger']),'action'=>rd18v_safe($x['action']),'already_reviewed'=>$c['allowed']];
        }
        $q->free();
        $shape=rd18v_order_shape($db,$orderId);
    } finally { if (!$db->rollback()) throw new RuntimeException('transaction_close'); }
    $pins=[];
    foreach (['catalog/controller/startup/event.php','catalog/model/setting/event.php','system/engine/event.php','system/engine/action.php','system/engine/loader.php'] as $rel) {
        $p=$root; foreach (explode('/',$rel) as $part) { $p.='/'.$part; if (is_link($p)) throw new RuntimeException('linked_core'); }
        if (!is_file($p) || filesize($p)<=0 || filesize($p)>65536) throw new RuntimeException('core_missing_or_cap');
        $h=hash_file('sha256',$p); if (!is_string($h)) throw new RuntimeException('core_read'); $pins[$rel]=$h;
    }
    $current=[];
    foreach (['catalog/controller/account/order.php','catalog/view/template/account/order_info.twig','catalog/view/template/account/order_history.twig','catalog/view/stylesheet/boostershop-ds.css','catalog/view/template/common/header.twig','catalog/model/account/order.php','system/library/acc007_order_status.php','extension/ukrainian/catalog/language/uk-ua/account/order.php','system/library/booster_order_tracking.php','catalog/view/javascript/booster-order-tracking.js','extension/opencart/catalog/controller/payment/bank_transfer.php'] as $rel) {
        $p=$root; foreach(explode('/',$rel) as $part) {$p.='/'.$part;if(is_link($p))throw new RuntimeException('linked_current');}
        if(!is_file($p)||filesize($p)<=0||filesize($p)>2097152)throw new RuntimeException('current_missing_or_cap');
        $h=hash_file('sha256',$p);if(!is_string($h))throw new RuntimeException('current_read');$current[$rel]=$h;
    }
    return ['enabled_event_rows'=>$total,'relevant_rows'=>$rows,'routing_core_sha256'=>$pins,'current_order_files_sha256'=>$current,'selected_order_address_shape'=>$shape];
}
ob_start();
try {
    if (PHP_SAPI!=='cli' || PHP_MAJOR_VERSION!==8 || PHP_MINOR_VERSION!==0 || is_link('config.php') || !is_file('config.php')) throw new RuntimeException('runtime');
    require './config.php';
    if (!defined('DIR_OPENCART') || rtrim(str_replace('\\','/',DIR_OPENCART),'/')!==getcwd()) throw new RuntimeException('root');
    mysqli_report(MYSQLI_REPORT_OFF);
    $db=new mysqli(DB_HOSTNAME,DB_USERNAME,DB_PASSWORD,DB_DATABASE,(int)DB_PORT);
    if ($db->connect_errno || !$db->set_charset('utf8mb4')) throw new RuntimeException('connect');
    $id=getenv('RD18_OWNER_ORDER_ID');if(!is_string($id)||!preg_match('/^[1-9][0-9]{0,9}$/D',$id)||(float)$id>2147483647)throw new RuntimeException('order_argument');
    $result=rd18v_collect($db,getcwd(),(int)$id); $db->close();
    $result['php']=PHP_VERSION;
    $result['image_root_is_expected']=defined('DIR_IMAGE') && str_replace('\\','/',DIR_IMAGE)===getcwd().'/image/' && !is_link(rtrim(DIR_IMAGE,'/')) && realpath(DIR_IMAGE)===getcwd().'/image';
    $result['writes']='none'; $result['customer_or_catalog_rows']='no_values_exported_one_address_shape_only'; $result['admission']='none';
    $json=json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); ob_end_clean(); echo $json;
} catch (Throwable $e) {
    while (ob_get_level()) ob_end_clean(); file_put_contents('php://stderr',"STOP=display_source_metadata_failed_no_details\n"); exit(1);
}
RD18_PHP
) || stop metadata_failed
tar -czf - -- "${rd18_files[@]}" > "$rd18_archive"
rd18_after=$(sha256sum -- "${rd18_files[@]}")
[[ "$rd18_before" == "$rd18_after" ]] || stop source_drift
# Independently bind archive bytes to the source manifest without extraction.
rd18_index=0
while IFS= read -r rd18_line; do
  rd18_expected=${rd18_line:0:64}
  rd18_file=${rd18_files[$rd18_index]}
  [[ "$rd18_expected" =~ ^[0-9a-f]{64}$ && "${rd18_line:66}" == "$rd18_file" ]] || stop hash_format
  [[ "${rd18_line:64:2}" == '  ' || "${rd18_line:64:2}" == ' *' ]] || stop hash_format
  rd18_archived=$(tar -xzOf "$rd18_archive" -- "$rd18_file" | sha256sum)
  [[ "${rd18_archived:0:64}" == "$rd18_expected" ]] || stop archived_source_mismatch
  rd18_index=$((rd18_index + 1))
done <<< "$rd18_before"
[[ "$rd18_index" == "${#rd18_files[@]}" ]] || stop source_count_mismatch
rd18_archive_hash=$(sha256sum -- "$rd18_archive")
{
  printf 'task=RD-18-WP4-display-source\ncollection_started_utc=%s\ncollection_finished_utc=%s\nsource_files=%s\nsource_bytes=%s\n' "$rd18_started" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "${#rd18_files[@]}" "$rd18_total"
  printf 'archive_sha256=%s\n' "${rd18_archive_hash:0:64}"
  printf 'metadata_json=%s\n' "$rd18_metadata"
  printf 'source_hashes_begin\n%s\nsource_hashes_end\n' "$rd18_before"
  printf 'source_pre_post=ok\narchived_source_hashes=ok\nproduction_writes=0\ndone=ok\n'
} > "$rd18_manifest"
printf 'task=RD-18-WP4-display-source\nsource_files=%s\narchive_sha256=%s\nmanifest_sha256=' "${#rd18_files[@]}" "${rd18_archive_hash:0:64}"
rd18_manifest_hash=$(sha256sum -- "$rd18_manifest")
printf '%s\ndone=ok\n' "${rd18_manifest_hash:0:64}"
