<?php
/** ACC-008 owner-only before/after check. PHP8.0, CLI, SELECT-only DB access.
 * Upload above public_html. Run from public_html. Email is supplied on stdin
 * by a hidden shell read; never put it in arguments or send it to agents.
 * Private checkpoint contains only IDs/counts/keyed digests, never JSON values.
 * --before creates one private checkpoint; --after compares and cleans up on
 * success; --cleanup cancels by deleting only this task's checkpoint/script.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
if (PHP_VERSION_ID < 80000) { fwrite(STDERR,"error=php8_required\n"); exit(1); }
const A08Q_SOURCES='eyJyMTEiOnsic3lzdGVtL2xpYnJhcnkvYm9vc3Rlcl9jdXN0b21fZmllbGRfZ3VhcmQucGhwIjoiOWYxNWFlZGJkNzRkNDhlYzU4ZGNlZjExZWVhYmE0MDE1ZDllOGY4YjM1ZmY0M2I3ZDcwNWZjODRhZDUyYTY2ZiIsImNhdGFsb2cvY29udHJvbGxlci9hY2NvdW50L2VkaXQucGhwIjoiODNjODlmMDVmOTJhMGQwNDhjNWJkMmJkMzM1OTQwMGM4NjUyYzhjMjcwNGRiMTg2ZjI1ZDZiMzkwYmVjMzg5MCIsImNhdGFsb2cvY29udHJvbGxlci9hY2NvdW50L2FkZHJlc3MucGhwIjoiNTRlMjczYTI1YTQyMjI4YTk2NTJiODI1MTRkNDM1MWVkZDI2NDQzODQ2NTdhNzhmNjdiZWI5ZmFjM2NjYWQ1ZCIsImFkbWluRXZoZW5paS9jb250cm9sbGVyL2N1c3RvbWVyL2N1c3RvbWVyLnBocCI6IjdiMDJhYjk2NjZlNjMzY2ZiYTBlYjFkOWYyYzZiNjE1Nzg1ZjI4ZTAwMGVmM2JmM2E1MGIyNTVkZDZiYmI5NGUiLCJhZG1pbkV2aGVuaWkvY29udHJvbGxlci9jdXN0b21lci9hZGRyZXNzLnBocCI6ImI0ZGE4YTBmNjg0NWJhYTJlNWJjYWY3ODczZmY2Y2VlMjFkMDhhYTA1M2Y3NzZhYjU3NWM5NjA5NzVkMGZiOTUiLCJjYXRhbG9nL3ZpZXcvdGVtcGxhdGUvYWNjb3VudC9lZGl0LnR3aWciOiJlNTQxMTRiOTZlNDdlYWFiNWI1OWZhMDA5OTUxYjNhZWE1ZjYyZTE0YjU2Y2JiMGM3NTIwZmVlYzE0NTI3ZmZlIiwiY2F0YWxvZy92aWV3L3RlbXBsYXRlL2FjY291bnQvYWRkcmVzc19mb3JtLnR3aWciOiJhMDhjYWIxZGU4OTVjZDBiZGI4YjRlZGIwM2JjMGQ1ZGNlMWI1NjQ3NzI3ZGQwMTI4OTE1MmU1Y2Q3NWJkNWFhIiwiYWRtaW5FdmhlbmlpL3ZpZXcvdGVtcGxhdGUvY3VzdG9tZXIvY3VzdG9tZXJfZm9ybS50d2lnIjoiMjlkN2MzZGE2YjJjMTIxMTZkZTRhYTk3N2U1NWNmMTI2MTY0MTZkM2NjMjU3YTc5Mzc3MTRkNWZkZmUxNjkxYiIsImFkbWluRXZoZW5paS92aWV3L3RlbXBsYXRlL2N1c3RvbWVyL2FkZHJlc3NfZm9ybS50d2lnIjoiOTllMzUxZmUxMWY3MmEyN2I4NjJlYjc1ZTQwYmMxNzU4MjcwNWI4YzhkMGJhMjZlODQ3ZmQ1NGYxMjAwZjI3NyJ9LCJwcm9maWxlX3IxMyI6eyJzeXN0ZW0vbGlicmFyeS9ib29zdGVyX2N1c3RvbV9maWVsZF9ndWFyZC5waHAiOiI5ZjE1YWVkYmQ3NGQ0OGVjNThkY2VmMTFlZWFiYTQwMTVkOWU4ZjhiMzVmZjQzYjdkNzA1ZmM4NGFkNTJhNjZmIiwiY2F0YWxvZy9jb250cm9sbGVyL2FjY291bnQvZWRpdC5waHAiOiJlOWMxYjg0NTcwNDdhM2U4NTI4YmJhODRkNmQ4NGY0OWNiYTgyNWU2YWYxYmJhMjM1Y2Y4MzI1NmQxNjAyZjJkIiwiY2F0YWxvZy9jb250cm9sbGVyL2FjY291bnQvYWRkcmVzcy5waHAiOiI1NGUyNzNhMjVhNDIyMjhhOTY1MmI4MjUxNGQ0MzUxZWRkMjY0NDM4NDY1N2E3OGY2N2JlYjlmYWMzY2NhZDVkIiwiYWRtaW5FdmhlbmlpL2NvbnRyb2xsZXIvY3VzdG9tZXIvY3VzdG9tZXIucGhwIjoiN2IwMmFiOTY2NmU2MzNjZmJhMGViMWQ5ZjJjNmI2MTU3ODVmMjhlMDAwZWYzYmYzYTUwYjI1NWRkNmJiYjk0ZSIsImFkbWluRXZoZW5paS9jb250cm9sbGVyL2N1c3RvbWVyL2FkZHJlc3MucGhwIjoiYjRkYThhMGY2ODQ1YmFhMmU1YmNhZjc4NzNmZjZjZWUyMWQwOGFhMDUzZjc3NmFiNTc1Yzk2MDk3NWQwZmI5NSIsImNhdGFsb2cvdmlldy90ZW1wbGF0ZS9hY2NvdW50L2VkaXQudHdpZyI6IjcwN2MzNzg4ZTEyODg4MzI0MTYxZGMzMTFjMDU5MWFlM2NlMjhjNzIxOTRkNzc0M2I0YjgyZjNmMDcwZWVmZjkiLCJjYXRhbG9nL3ZpZXcvdGVtcGxhdGUvYWNjb3VudC9hZGRyZXNzX2Zvcm0udHdpZyI6ImEwOGNhYjFkZTg5NWNkMGJkYjhiNGVkYjAzYmMwZDVkY2UxYjU2NDc3MjdkZDAxMjg5MTUyZTVjZDc1YmQ1YWEiLCJhZG1pbkV2aGVuaWkvdmlldy90ZW1wbGF0ZS9jdXN0b21lci9jdXN0b21lcl9mb3JtLnR3aWciOiIyOWQ3YzNkYTZiMmMxMjExNmRlNGFhOTc3ZTU1Y2YxMjYxNjQxNmQzY2MyNTdhNzkzNzcxNGQ1ZmRmZTE2OTFiIiwiYWRtaW5FdmhlbmlpL3ZpZXcvdGVtcGxhdGUvY3VzdG9tZXIvYWRkcmVzc19mb3JtLnR3aWciOiI5OWUzNTFmZTExZjcyYTI3Yjg2MmViNzVlNDBiYzE3NTgyNzA1YjhjOGQwYmEyNmU4NDdmZDU0ZjEyMDBmMjc3In19';
const A08Q_SERVICES=['bs_first15_pending','bs_np_v1','rd13_receiver_middlename'];
final class Acc008QaFailure extends RuntimeException {}
function acc008_log(string $key,string $value): void { echo $key.'='.$value."\n"; }
function acc008_fail(string $code): void { throw new Acc008QaFailure($code); }
function acc008_path(string $root,string $relative): string {
    if (!preg_match('~^[A-Za-z0-9_./-]+$~D',$relative)||strpos($relative,'..')!==false||$relative[0]==='/') acc008_fail('unsafe_path');
    $path=$root;
    foreach(explode('/',$relative) as $part) { $path.='/'.$part; if(is_link($path))acc008_fail('linked_path'); }
    return $path;
}
/** ACC-008: narrow literal configuration grammar; never evaluate config code. */
function acc008_config_literals(string $code): array {
    if (!function_exists('token_get_all') || strlen($code) > 524288) acc008_fail('config_parser_unavailable_or_size_cap');
    $tokens = array_values(array_filter(token_get_all($code), static function($t) {
        return !is_array($t) || !in_array($t[0], [T_WHITESPACE,T_COMMENT,T_DOC_COMMENT], true);
    }));
    if (!isset($tokens[0]) || !is_array($tokens[0]) || $tokens[0][0] !== T_OPEN_TAG) acc008_fail('config_literal_grammar_required');
    $values = []; $count = count($tokens); $storedBytes=0;
    for ($i = 1; $i < $count;) {
        $t = $tokens[$i];
        if (is_array($t) && $t[0] === T_CLOSE_TAG && $i === $count - 1) break;
        if (!is_array($t) || !in_array(strtolower($t[1]), ['define','\\define'], true)
            || !in_array($t[0], [T_STRING,T_NAME_FULLY_QUALIFIED], true)
            || ($tokens[$i+1] ?? null) !== '(' || !is_array($tokens[$i+2] ?? null)
            || $tokens[$i+2][0] !== T_CONSTANT_ENCAPSED_STRING || ($tokens[$i+3] ?? null) !== ',') acc008_fail('config_literal_grammar_required');
        $name = acc008_config_string($tokens[$i+2][1]);
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/D', $name) || array_key_exists($name, $values)) acc008_fail('config_duplicate_or_indirect_definition');
        $j=$i+4; $value='';
        while (true) {
            $operand=$tokens[$j]??null;
            if (!is_array($operand)) acc008_fail('config_literal_grammar_required');
            if ($operand[0]===T_CONSTANT_ENCAPSED_STRING) $piece=acc008_config_string($operand[1]);
            elseif ($operand[0]===T_LNUMBER && preg_match('/^(?:0|[1-9][0-9]*)$/D',$operand[1])) $piece=$operand[1];
            elseif ($operand[0]===T_STRING && array_key_exists($operand[1],$values)) $piece=$values[$operand[1]];
            else acc008_fail('config_unknown_or_unsafe_operand');
            if (strlen($value)+strlen($piece)>524288) acc008_fail('config_evaluated_value_size_cap');
            $value.=$piece; $j++;
            if (($tokens[$j]??null)==='.') { $j++; continue; }
            if (($tokens[$j]??null)!==')'||($tokens[$j+1]??null)!==';') acc008_fail('config_literal_grammar_required');
            break;
        }
        $storedBytes+=strlen($value);
        if ($storedBytes>2097152) acc008_fail('config_aggregate_value_size_cap');
        $values[$name]=$value; $i=$j+2;
    }
    return $values;
}
function acc008_config_string(string $literal): string {
    if (strlen($literal)<2 || !in_array($literal[0],["'",'"'],true) || substr($literal,-1)!==$literal[0]) acc008_fail('config_binary_or_invalid_string_literal');
    $value = substr($literal, 1, -1);
    if ($literal[0] === "'") return str_replace(["\\'",'\\\\'], ["'",'\\'], $value);
    // Double-quoted strings with escapes/interpolation require explicit review.
    if (strpos($value, '\\') !== false || strpos($value, '$') !== false) acc008_fail('config_escaped_double_quote_requires_review');
    return $value;
}
function acc008_config_targets(string $root): array {
    $configs = [];
    foreach (['catalog'=>'config.php','admin'=>'adminEvhenii/config.php'] as $app=>$relative) {
        $path = acc008_path($root, $relative);
        if (!is_file($path) || filesize($path)>524288) acc008_fail('hosting_config_missing_or_size_cap');
        $body = file_get_contents($path); if (!is_string($body)) acc008_fail('hosting_config_read_failed');
        $v = acc008_config_literals($body); unset($body);
        foreach (['DB_HOSTNAME','DB_PORT','DB_DATABASE','DB_PREFIX','DB_DRIVER','DB_USERNAME','DB_PASSWORD','DIR_OPENCART','DIR_APPLICATION','DIR_SYSTEM','DIR_EXTENSION','DIR_CONFIG','DIR_TEMPLATE','DIR_CACHE','APPLICATION'] as $key) {
            if (!array_key_exists($key,$v)) acc008_fail('hosting_config_incomplete:'.$app);
        }
        if ($v['APPLICATION']!==($app==='admin'?'Admin':'Catalog')) acc008_fail('application_identity_changed:'.$app);
        if ($v['DB_DRIVER']!=='mysqli' || stripos($v['DB_HOSTNAME'],'p:')===0
            || !preg_match('/^[1-9][0-9]{0,4}$/D',$v['DB_PORT']) || (int)$v['DB_PORT']>65535
            || !preg_match('/^[A-Za-z0-9_]+$/D',$v['DB_PREFIX'])) acc008_fail('unsupported_or_persistent_db_target:'.$app);
        foreach (['DIR_OPENCART'=>'','DIR_APPLICATION'=>($app==='admin'?'adminEvhenii':'catalog'),'DIR_SYSTEM'=>'system','DIR_EXTENSION'=>'extension','DIR_CONFIG'=>'system/config','DIR_TEMPLATE'=>($app==='admin'?'adminEvhenii':'catalog').'/view/template'] as $key=>$expected) {
            $actual = realpath($v[$key]); $wanted = $expected===''?realpath($root):realpath(acc008_path($root,$expected));
            if ($actual===false || $wanted===false || $actual!==$wanted) acc008_fail('runtime_path_mapping_changed:'.$app.'/'.$key);
        }
        $configs[$app]=$v;
    }
    foreach(['catalog'=>'cache_dir_config_relative','admin'=>'admin_cache_dir_config_relative']as$app=>$label){
        $cache=realpath($configs[$app]['DIR_CACHE']);
        if ($cache===false||!is_dir($cache)) acc008_fail('configured_cache_directory_missing:'.$app);
        $from=explode('/',trim(str_replace('\\','/',$root),'/'));$to=explode('/',trim(str_replace('\\','/',$cache),'/'));$common=0;
        while(isset($from[$common],$to[$common])&&$from[$common]===$to[$common])$common++;
        acc008_log($label,str_repeat('../',count($from)-$common).implode('/',array_slice($to,$common)));
    }
    foreach (['DB_HOSTNAME','DB_PORT','DB_DATABASE','DB_PREFIX','DB_DRIVER'] as $key) {
        if ($configs['catalog'][$key]!==$configs['admin'][$key]) acc008_fail('catalog_admin_db_target_mismatch');
    }
    acc008_log('config_admission','literal_nonpersistent_same_target_and_paths');
    return $configs['catalog'];
}

final class Acc008QaJson {
    private function fail(string $message=''): void { acc008_fail('custom_field_json_requires_review'); }
    private function decode($raw): array {
        if ($raw === '' || $raw === null) {
            return [];
        }
        if (!is_string($raw)) {
            $this->fail();
        }
        try {
            $decoded = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->fail('Збережені додаткові дані пошкоджені. Зміни не внесено; зверніться до адміністратора.');
        }
        if (!is_array($decoded) && !($decoded instanceof \stdClass)) {
            $this->fail('Збережені додаткові дані мають непідтримуваний формат. Зміни не внесено.');
        }
        $this->checkRawKeys($raw);
        $values = is_array($decoded) ? $decoded : get_object_vars($decoded);
        // json_encode in the existing writers must not silently change a large
        // unknown number or an unsupported value. Fail before any model write.
        $this->checkNumbers($values);
        $this->checkRawNumbers($raw);
        return $values;
    }

    private function checkRawKeys(string $raw): void {
        $stack = [];
        $length = strlen($raw);
        for ($i = 0; $i < $length; $i++) {
            $char = $raw[$i];
            if ($char === '{') { $stack[] = []; }
            elseif ($char === '[') { $stack[] = null; }
            elseif ($char === '}' || $char === ']') { array_pop($stack); }
            elseif ($char === '"') {
                $start = $i++;
                for (; $i < $length; $i++) {
                    if ($raw[$i] === '\\') { $i++; }
                    elseif ($raw[$i] === '"') { break; }
                }
                $next = $i + 1;
                while ($next < $length && strpos(" \t\r\n", $raw[$next]) !== false) { $next++; }
                if ($next < $length && $raw[$next] === ':') {
                    $key = json_decode(substr($raw, $start, $i - $start + 1), true, 512, JSON_THROW_ON_ERROR);
                    $level = count($stack) - 1;
                    if (!is_string($key) || $level < 0 || !is_array($stack[$level]) || strpos($key, "\0") !== false) { $this->fail(); }
                    $identity = 'key:' . $key;
                    if (isset($stack[$level][$identity])) {
                        $this->fail('Збережені додаткові дані містять повторювані ключі. Зміни не внесено; зверніться до адміністратора.');
                    }
                    $stack[$level][$identity] = true;
                }
            }
        }
    }

    private function numberSignature(string $number): string {
        if (strlen($number) > 256 || !preg_match('/^(-?)([0-9]+)(?:\.([0-9]+))?(?:[eE]([+-]?[0-9]+))?$/D', $number, $parts)) {
            $this->fail('Збережені числові дані потребують перевірки точності. Зміни не внесено.');
        }
        $fraction = $parts[3] ?? '';
        $exponentText = $parts[4] ?? '0';
        if (strlen(ltrim($exponentText, '+-')) > 4) { $this->fail(); }
        $exponent = (int)$exponentText - strlen($fraction);
        $digits = ltrim($parts[2] . $fraction, '0');
        if ($digits === '') { return '0'; }
        while (substr($digits, -1) === '0') { $digits = substr($digits, 0, -1); $exponent++; }
        return $parts[1] . $digits . 'e' . $exponent;
    }

    private function checkRawNumbers(string $raw): void {
        // JSON was validated first. Skip complete quoted strings and compare
        // unquoted numbers with the existing writer's current serialization.
        $matched = preg_match_all('/"(?:[^"\\\\]++|\\\\.)*+"|(-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?)/s', $raw, $matches, PREG_SET_ORDER);
        if ($matched === false) {
            $this->fail('Збережені числові дані неможливо безпечно перевірити. Зміни не внесено.');
        }
        foreach ($matches as $match) {
            if (!isset($match[1]) || $match[1] === '') { continue; }
            $encoded = json_encode(json_decode($match[1]));
            if (!is_string($encoded) || $this->numberSignature($match[1]) !== $this->numberSignature($encoded)) {
                $this->fail('Збережені числові дані неможливо зберегти без втрати точності. Зміни не внесено.');
            }
        }
    }

    private function checkNumbers($value): void {
        if (is_float($value) && (!is_finite($value) || abs($value) > 9007199254740991)) {
            $this->fail('Збережені додаткові дані потребують перевірки точності. Зміни не внесено.');
        }
        if (is_array($value) || $value instanceof \stdClass) {
            foreach ($value as $nested) {
                $this->checkNumbers($nested);
            }
        }
    }

    private function canonical($value) {
        if($value instanceof stdClass) return ['object',$this->pairs(get_object_vars($value))];
        if(is_array($value)) return ['array',array_map([$this,'canonical'],$value)];
        if(is_int($value)||is_float($value)) return ['number',($value==0?'0':json_encode($value,JSON_THROW_ON_ERROR))];
        return [gettype($value),$value];
    }
    private function pairs(array $values): array {
        uksort($values,static function($a,$b){return strcmp((string)$a,(string)$b);});
        $pairs=[];foreach($values as $key=>$value)$pairs[]=[(string)$key,$this->canonical($value)];return $pairs;
    }
    public function fingerprint($raw,string $secret): array {
        if($raw!==null && (!is_string($raw)||strlen($raw)>65535))acc008_fail('custom_field_size_or_type');
        $values=$this->decode($raw);$tracked=[];
        foreach(A08Q_SERVICES as $name) $tracked[$name]=array_key_exists($name,$values)?hash_hmac('sha256',json_encode($this->canonical($values[$name]),JSON_THROW_ON_ERROR),$secret):null;
        return ['count'=>count($values),'digest'=>hash_hmac('sha256',json_encode($this->pairs($values),JSON_THROW_ON_ERROR),$secret),'tracked'=>$tracked];
    }
}
function a08q_source_variant(string $root): array {
    $variants=json_decode(base64_decode(A08Q_SOURCES,true),true,512,JSON_THROW_ON_ERROR);
    $actual=[];
    foreach($variants['r11'] as $relative=>$unused) {
        $path=acc008_path($root,$relative);
        if(!is_file($path))acc008_fail('installed_source_missing');
        $actual[$relative]=hash_file('sha256',$path);
    }
    foreach($variants as $name=>$expected)if($actual===$expected)return ['variant'=>$name,'hashes'=>$actual];
    acc008_fail('installed_source_requires_review');
}
function a08q_private(string $path,bool $directory): void {
    clearstatcache(true,$path);
    if(is_link($path)||($directory?!is_dir($path):!is_file($path)))acc008_fail('private_checkpoint_path_invalid');
    if((fileperms($path)&0777)!==($directory?0700:0600))acc008_fail('private_checkpoint_permissions');
    if(function_exists('posix_geteuid')&&fileowner($path)!==posix_geteuid())acc008_fail('private_checkpoint_owner');
    $stat=stat($path);if(!$stat||(!$directory&&$stat['nlink']!==1))acc008_fail('private_checkpoint_links');
}
function a08q_db(array $config): mysqli {
    if(!class_exists('mysqli'))acc008_fail('mysqli_required');mysqli_report(MYSQLI_REPORT_OFF);
    $db=@new mysqli($config['DB_HOSTNAME'],$config['DB_USERNAME'],$config['DB_PASSWORD'],$config['DB_DATABASE'],(int)$config['DB_PORT']);
    if($db->connect_errno||!$db->set_charset('utf8mb4'))acc008_fail('db_connection_failed');return $db;
}
function a08q_name_digest($first,$last,string $secret): string {
    foreach([$first,$last] as $name)if($name!==null&&(!is_string($name)||strlen($name)>4096))acc008_fail('name_witness_type_or_size');
    return hash_hmac('sha256',json_encode([$first,$last],JSON_THROW_ON_ERROR),$secret);
}
function a08q_values_only(array $rows): array {
    foreach($rows as &$row)unset($row['name']);unset($row);return $rows;
}
function a08q_snapshot(mysqli $db,string $prefix,$target,bool $byEmail,string $secret): array {
    // One bounded SELECT supplies the account and all its addresses in one read.
    // The derived LIMIT2 detects ambiguous emails; LIMIT33 detects >32 rows.
    $where=$byEmail?'LCASE(`email`) = LCASE(?)':'`customer_id` = ?';
    $sql='SELECT c.customer_id,c.custom_field,a.address_id,a.custom_field,c.firstname,c.lastname,a.firstname,a.lastname FROM (SELECT customer_id,custom_field,firstname,lastname FROM `'.$prefix.'customer` WHERE '.$where.' LIMIT 2) c LEFT JOIN `'.$prefix.'address` a ON a.customer_id=c.customer_id ORDER BY c.customer_id,a.address_id LIMIT 33';
    $st=$db->prepare($sql);if(!$st)acc008_fail('qa_select_prepare_failed');
    try {
        $type=$byEmail?'s':'i';
        if(!$st->bind_param($type,$target)||!$st->execute()||!$st->bind_result($id,$customerRaw,$addressId,$addressRaw,$customerFirst,$customerLast,$addressFirst,$addressLast))acc008_fail('qa_select_failed');
        $rows=[];$customerId=null;$seen=0;$parser=new Acc008QaJson();
        while(($fetch=$st->fetch())===true) {
            if(!filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]))acc008_fail('invalid_customer_identity');
            if($customerId!==null&&$customerId!==(int)$id)acc008_fail('email_matches_multiple_accounts');
            $customerId=(int)$id;
            if(++$seen>32)acc008_fail('address_row_cap_requires_review');
            $customer=$parser->fingerprint($customerRaw,$secret);
            $customer['name']=a08q_name_digest($customerFirst,$customerLast,$secret);
            if(isset($rows['customer'])&&$rows['customer']!==$customer)acc008_fail('inconsistent_account_read');
            $rows['customer']=$customer;
            if($addressId!==null) {
                if(!filter_var($addressId,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]))acc008_fail('invalid_address_identity');
                $key='address_'.(int)$addressId;if(isset($rows[$key]))acc008_fail('duplicate_address_identity');
                $rows[$key]=$parser->fingerprint($addressRaw,$secret);
                $rows[$key]['name']=a08q_name_digest($addressFirst,$addressLast,$secret);
            }
        }
        if($fetch!==null||$st->errno)acc008_fail('qa_select_fetch_failed');
        if($customerId===null)acc008_fail('account_not_found');
        ksort($rows,SORT_STRING);return ['customer_id'=>$customerId,'rows'=>$rows];
    } finally { $st->close(); }
}
function a08q_checkpoint_read(string $path): array {
    a08q_private($path,false);
    if(filesize($path)>131072)acc008_fail('checkpoint_size_cap');
    $raw=file_get_contents($path);$s=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    if(!is_array($s)||($s['schema']??null)!==2||!preg_match('/^[a-f0-9]{64}$/D',$s['secret']??'')||!is_int($s['created']??null)||!is_int($s['customer_id']??null)||$s['customer_id']<1||!is_array($s['rows']??null)||count($s['rows'])<1||count($s['rows'])>33||!isset($s['rows']['customer'])||!is_array($s['sources']??null)||!preg_match('/^[a-f0-9]{64}$/D',$s['target']??''))acc008_fail('checkpoint_schema_invalid');
    foreach($s['rows'] as $key=>$row) {
        if(!preg_match('/^(?:customer|address_[1-9][0-9]*)$/D',$key)||!is_array($row)||!is_int($row['count']??null)||$row['count']<0||!preg_match('/^[a-f0-9]{64}$/D',$row['digest']??'')||!preg_match('/^[a-f0-9]{64}$/D',$row['name']??'')||array_keys($row['tracked']??[])!==A08Q_SERVICES)acc008_fail('checkpoint_row_invalid');
        foreach($row['tracked'] as $digest)if($digest!==null&&(!is_string($digest)||!preg_match('/^[a-f0-9]{64}$/D',$digest)))acc008_fail('checkpoint_digest_invalid');
    }
    return [$s,hash('sha256',$raw)];
}
function a08q_remove_checkpoint(string $file,string $expected): void {
    a08q_private($file,false);
    if(!hash_equals($expected,hash_file('sha256',$file)))acc008_fail('checkpoint_changed_during_check');
    if(!unlink($file))acc008_fail('checkpoint_cleanup_failed');
}
function a08q_delete_self(): void { if(!unlink(__FILE__))acc008_fail('script_cleanup_failed'); }
function a08q_main(array $args): int {
    if(PHP_OS_FAMILY!=='Linux')acc008_fail('linux_host_required');
    if(count($args)!==2||!in_array($args[1],['--before','--after','--cleanup'],true))acc008_fail('use_before_after_or_cleanup');
    $mode=substr($args[1],2);$root=realpath(getcwd());
    if(!$root||basename($root)!=='public_html'||realpath(dirname(__FILE__))!==realpath(dirname($root)))acc008_fail('upload_above_public_html_required');
    acc008_log('task','ACC-008-key-check');acc008_log('mode',$mode);acc008_log('time',gmdate('c'));acc008_log('php_version',PHP_VERSION);
    $parent=realpath(dirname($root));$dir=acc008_path($parent,'_patch_backups/ACC-008_key-preservation_check');$file=$dir.'/checkpoint.json';
    if(is_link($file))acc008_fail('linked_checkpoint');
    if($mode==='cleanup') {
        if(file_exists($dir)){a08q_private($dir,true);if(file_exists($file)){a08q_private($file,false);if(filesize($file)>131072)acc008_fail('checkpoint_size_cap');a08q_remove_checkpoint($file,hash_file('sha256',$file));}}
        a08q_delete_self();acc008_log('cleanup','ok');acc008_log('done','ok');return 0;
    }
    if($mode==='before'&&file_exists($file))acc008_fail('checkpoint_exists_do_not_overwrite');
    $installed=a08q_source_variant($root);acc008_log('source_variant',$installed['variant']);
    $config=acc008_config_targets($root);
    if($mode==='before') {
        if(stream_isatty(STDIN))acc008_fail('hidden_stdin_pipe_required');
        $input=stream_get_contents(STDIN,257);if(strlen($input)>=257)acc008_fail('email_input_too_long');$email=trim($input);unset($input);
        if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))acc008_fail('valid_email_required_on_hidden_stdin');
        $state=['schema'=>2,'created'=>time(),'secret'=>bin2hex(random_bytes(32)),'sources'=>$installed['hashes']];$target=$email;$byEmail=true;
    } else {
        a08q_private($dir,true);[$state,$checkpointDigest]=a08q_checkpoint_read($file);acc008_log('private_checkpoint_modes','checked_0700_0600');
        if($state['created']>time()+60||time()-$state['created']>86400)acc008_fail('checkpoint_expired');
        if($state['sources']!==$installed['hashes'])acc008_fail('code_changed_since_before');
        $target=$state['customer_id'];$byEmail=false;
    }
    $dbTarget=hash_hmac('sha256',json_encode(array_intersect_key($config,array_flip(['DB_HOSTNAME','DB_PORT','DB_DATABASE','DB_PREFIX','DB_DRIVER'])),JSON_THROW_ON_ERROR),$state['secret']);
    if($mode==='after'&&!hash_equals($state['target'],$dbTarget))acc008_fail('db_target_changed_since_before');
    $db=a08q_db($config);$prefix=$config['DB_PREFIX'];unset($config);
    try{$snapshot=a08q_snapshot($db,$prefix,$target,$byEmail,$state['secret']);}finally{$db->close();}
    unset($target,$email);acc008_log('db_access','SELECT_ONLY');
    if($mode==='before') {
        if(!is_dir($dir)&&!mkdir($dir,0700,true))acc008_fail('private_checkpoint_directory_failed');a08q_private($dir,true);
        $state+=$snapshot;$state['target']=$dbTarget;$bytes=json_encode($state,JSON_THROW_ON_ERROR);if(strlen($bytes)>131072)acc008_fail('checkpoint_size_cap');
        $old=umask(0077);try{$handle=@fopen($file,'xb');}finally{umask($old);}
        if(!$handle)acc008_fail('checkpoint_exclusive_create_failed');
        try{if(fwrite($handle,$bytes)!==strlen($bytes)||!fflush($handle))acc008_fail('checkpoint_write_failed');}finally{fclose($handle);}
        a08q_private($file,false);acc008_log('private_checkpoint_modes','checked_0700_0600');acc008_log('private_checkpoint','saved_outside_public_html');acc008_log('address_rows',(string)(count($state['rows'])-1));acc008_log('before','ok');acc008_log('done','ok');return 0;
    }
    $same=$state['customer_id']===$snapshot['customer_id']&&a08q_values_only($state['rows'])===a08q_values_only($snapshot['rows']);$keyCount=0;
    $customerNameChanged=!hash_equals($state['rows']['customer']['name'],$snapshot['rows']['customer']['name']);$addressNameChanged=0;
    foreach($state['rows'] as $rowKey=>$row)if($rowKey!=='customer'&&isset($snapshot['rows'][$rowKey])&&!hash_equals($row['name'],$snapshot['rows'][$rowKey]['name']))$addressNameChanged++;
    foreach($state['rows'] as $row)$keyCount+=$row['count'];
    acc008_log('customer_keys_before',(string)$state['rows']['customer']['count']);acc008_log('customer_keys_after',(string)$snapshot['rows']['customer']['count']);
    acc008_log('address_rows_before',(string)(count($state['rows'])-1));acc008_log('address_rows_after',(string)(count($snapshot['rows'])-1));
    foreach(A08Q_SERVICES as $name) {
        $present=0;$preserved=0;
        foreach($state['rows'] as $key=>$row)if($row['tracked'][$name]!==null){$present++;if(isset($snapshot['rows'][$key])&&$snapshot['rows'][$key]['tracked'][$name]!==null&&hash_equals($row['tracked'][$name],$snapshot['rows'][$key]['tracked'][$name]))$preserved++;}
        acc008_log('service_key',$name.':present_rows='.$present.':preserved_rows='.$preserved);
    }
    acc008_log('existing_keys',(string)$keyCount);
    acc008_log('customer_name_changed',$customerNameChanged?'yes':'no');acc008_log('address_rows_name_changed',(string)$addressNameChanged);
    if(!$same){acc008_log('result','CHANGED');acc008_log('checkpoint','retained');return 1;}
    if($keyCount===0){acc008_log('result','INCONCLUSIVE_NO_EXISTING_KEYS');acc008_log('checkpoint','retained');return 2;}
    if(!$customerNameChanged||$addressNameChanged<1){acc008_log('result','INCONCLUSIVE_SAVE_NOT_DETECTED');acc008_log('checkpoint','retained');return 2;}
    acc008_log('result','UNCHANGED');a08q_remove_checkpoint($file,$checkpointDigest);a08q_delete_self();acc008_log('cleanup','ok');acc008_log('done','ok');return 0;
}
try {
    set_error_handler(static function(){throw new Acc008QaFailure('runtime_warning');});
    exit(a08q_main($argv));
} catch(Throwable $e) {
    $code=$e instanceof Acc008QaFailure&&preg_match('/^[A-Za-z_:\/]+$/D',$e->getMessage())?$e->getMessage():'qa_check_failed';
    fwrite(STDERR,'error='.$code."\n");exit(1);
}
