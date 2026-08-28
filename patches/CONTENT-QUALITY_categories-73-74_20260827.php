<?php
declare(strict_types=1);

/*
 * Generated from the owner-approved CONTENT-QUALITY release package v2.
 * DB runner: backup before write, transaction, restore.sql, syntax gate and
 * self-delete after a successful apply. Do not edit payload bytes by hand.
 *
 * ROLLBACK: run `_patch_backups/<run>/restore.sql`; it restores the original
 * category 73/74 language-4 names and category 73 meta_keyword verbatim.
 */

const PATCH_NAME = 'CONTENT-QUALITY_categories-73-74_20260827';
const LANGUAGE_ID = 4;
const STORE_ID = 0;
const PAYLOAD_B64 = 'eyJjYXRlZ29yaWVzIjpbeyJjYXRlZ29yeV9pZCI6NzMsIm5hbWUiOiLQpNGW0LPRg9GA0LrQuCDRgtCwINC00LXQutC+0YAgUG9rw6ltb24iLCJtZXRhX2tleXdvcmQiOiLQsdGA0LXQu9C+0LrQuCBQb2vDqW1vbiwg0YTRltCz0YPRgNC60LggUG9rw6ltb24sIDNELdC00YDRg9C6IFBva8OpbW9uLCDQtNC10LrQvtGAIFBva8OpbW9uLCBQb2vDqW1vbiAzRC3QtNGA0YPQuiDQo9C60YDQsNGX0L3QsCJ9LHsiY2F0ZWdvcnlfaWQiOjc0LCJuYW1lIjoi0KTRltCz0YPRgNC60Lgg0YLQsCDQtNC10LrQvtGAIE9uZSBQaWVjZSJ9XX0=';
const PAYLOAD_SHA256 = 'e2859cdfef21560b2a1d91331e7ee2d3ff0f6fc5ed604128526a749a92f8b64e';

function fail(string $message): never { throw new RuntimeException($message); }
function out(string $key, string $value): void { echo $key . '=' . $value . PHP_EOL; }
function need(bool $ok, string $message): void { if (!$ok) fail($message); }
function qi(string $name): string { return chr(96) . str_replace(chr(96), chr(96) . chr(96), $name) . chr(96); }
function quote_sql(mysqli $db, mixed $value): string { return $value === null ? 'NULL' : chr(39) . $db->real_escape_string((string)$value) . chr(39); }
function bind(mysqli_stmt $statement, array $params): void {
    if ($params === []) return;
    $types = str_repeat('s', count($params)); $args = [&$types];
    foreach ($params as $i => $value) { $params[$i] = (string)$value; $args[] = &$params[$i]; }
    need(call_user_func_array([$statement, 'bind_param'], $args), 'bind_failed:' . $statement->error);
}
function statement(mysqli $db, string $sql, array $params = []): mysqli_stmt {
    $s = $db->prepare($sql); need($s instanceof mysqli_stmt, 'prepare_failed:' . $db->error); bind($s, $params); need($s->execute(), 'execute_failed:' . $s->error); return $s;
}
function rows(mysqli $db, string $sql, array $params = []): array {
    $s = statement($db, $sql, $params); $m = $s->result_metadata(); need($m instanceof mysqli_result, 'result_metadata_failed');
    $fields = $m->fetch_fields(); $values = []; $refs = [];
    foreach ($fields as $i => $field) { $values[$i] = null; $refs[] = &$values[$i]; }
    need(call_user_func_array([$s, 'bind_result'], $refs), 'result_bind_failed'); $result = [];
    while ($s->fetch()) { $copy = []; foreach ($fields as $i => $field) $copy[$field->name] = $values[$i]; $result[] = $copy; }
    $s->close(); return $result;
}
function one(mysqli $db, string $sql, array $params = []): ?array { $all = rows($db, $sql, $params); need(count($all) <= 1, 'expected_one_row_got_' . count($all)); return $all[0] ?? null; }
function exec_sql(mysqli $db, string $sql, array $params = []): int { $s = statement($db, $sql, $params); $n = $s->affected_rows; $s->close(); return $n; }
function columns(mysqli $db, string $table): array { return array_column(rows($db, 'SHOW COLUMNS FROM ' . qi($table)), 'Field'); }
function require_columns(mysqli $db, string $table, array $required): void { $have = array_flip(columns($db, $table)); foreach ($required as $name) need(isset($have[$name]), 'schema_column_missing:' . $table . '.' . $name); }
function html_encode(string $html): string { return htmlspecialchars($html, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8'); }
function check_html(string $sku, string $html): void {
    need(substr_count($html, '<section class="bs-faq-accordion"') === 1, 'faq_section_invalid:' . $sku);
    need(!str_contains($html, 'class="bs-faq"') && !str_contains($html, '<div class="bs-faq-accordion"'), 'legacy_faq_markup:' . $sku);
    need(substr_count($html, 'data-bs-faq-accordion=""') === 1 && substr_count($html, 'data-bs-faq-id="') === 1, 'faq_contract_invalid:' . $sku);
    need(substr_count($html, '<h2') >= 1, 'heading_missing:' . $sku);
    preg_match_all('/href="([^"]+)"/', $html, $links);
    foreach ($links[1] as $href) need(str_starts_with($href, '/product/'), 'internal_link_prefix_invalid:' . $sku . ':' . $href);
    preg_match_all('/ id="([^"]+)"/', $html, $ids); need(count($ids[1]) === count(array_unique($ids[1])), 'duplicate_html_id:' . $sku);
}
function backup_dir(): string {
    $path = getcwd() . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . PATCH_NAME . '-' . gmdate('Ymd-His');
    need(!file_exists($path), 'backup_path_exists:' . $path); need(mkdir($path, 0750, true), 'backup_create_failed:' . $path); return $path;
}
function write_backup(string $dir, string $name, array $data): string {
    $path = $dir . DIRECTORY_SEPARATOR . $name; $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    need(file_put_contents($path, $json, LOCK_EX) !== false, 'backup_write_failed:' . $name); return $path;
}
function write_restore(string $dir, string $sql): string { $path = $dir . DIRECTORY_SEPARATOR . 'restore.sql'; need(file_put_contents($path, $sql, LOCK_EX) !== false, 'restore_write_failed'); return $path; }
function appender(string $path, string $sql): void { need(file_put_contents($path, $sql, FILE_APPEND | LOCK_EX) !== false, 'restore_append_failed'); }
function table(mysqli $db, string $prefix, string $name): string { $full = $prefix . $name; statement($db, 'SELECT 1 FROM ' . qi($full) . ' LIMIT 0')->close(); return $full; }
function restore_update(mysqli $db, string $table, array $row, array $sets, string $where): string {
    $parts = []; foreach ($sets as $column) $parts[] = qi($column) . '=' . quote_sql($db, $row[$column]); return 'UPDATE ' . qi($table) . ' SET ' . implode(', ', $parts) . ' WHERE ' . $where . ";\n";
}
function load_payload(): array { $json = base64_decode(PAYLOAD_B64, true); need($json !== false && hash('sha256', $json) === PAYLOAD_SHA256, 'payload_integrity_failed'); $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR); need(is_array($payload), 'payload_invalid'); return $payload; }
function connect(): array {
    need(PHP_SAPI === 'cli', 'cli_only'); need(is_file(getcwd() . DIRECTORY_SEPARATOR . 'config.php'), 'run_from_public_html_required'); require getcwd() . DIRECTORY_SEPARATOR . 'config.php';
    foreach (['DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PORT','DB_PREFIX'] as $c) need(defined($c), 'config_constant_missing:' . $c);
    need((string)DB_PREFIX === 'ocp5_', 'db_prefix_mismatch_expected_ocp5_'); mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli((string)DB_HOSTNAME, (string)DB_USERNAME, (string)DB_PASSWORD, (string)DB_DATABASE, (int)DB_PORT); $db->set_charset('utf8mb4'); return [$db, (string)DB_PREFIX];
}
function lint_self(): void { $status = 1; $output = []; @exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__), $output, $status); need($status === 0, 'php_lint_failed:' . implode(' ', $output)); out('php_lint', 'ok'); }
function parse_mode(): bool { $args = array_slice($GLOBALS['argv'], 1); need($args === [] || $args === ['--dry-run'], 'usage:php ' . basename(__FILE__) . ' [--dry-run]'); return $args === ['--dry-run']; }

$dryRun = parse_mode(); lint_self(); $payload = load_payload(); [$db, $prefix] = connect(); $tx=false;
try {
    $categoryDescription = table($db,$prefix,'category_description'); $category = table($db,$prefix,'category'); $attributeDescription = table($db,$prefix,'attribute_description');
    $a43=one($db,'SELECT name FROM '.qi($attributeDescription).' WHERE attribute_id=43 AND language_id=?',[LANGUAGE_ID]); $a44=one($db,'SELECT name FROM '.qi($attributeDescription).' WHERE attribute_id=44 AND language_id=?',[LANGUAGE_ID]); need($a43 !== null && $a43['name']==='Типовий строк виготовлення при відсутності на складі','attribute_43_unexpected'); need($a44 !== null && $a44['name']==='Може трапитись у Mystery Box','attribute_44_unexpected');
    $before=[]; foreach ($payload['categories'] as $change) { $row=one($db,'SELECT category_id,language_id,name,meta_keyword FROM '.qi($categoryDescription).' WHERE category_id=? AND language_id=?',[$change['category_id'],LANGUAGE_ID]); need($row!==null,'category_description_missing:'.$change['category_id']); $state=one($db,'SELECT status FROM '.qi($category).' WHERE category_id=?',[$change['category_id']]); need($state!==null && (int)$state['status']===0,'category_not_disabled:'.$change['category_id']); $before[]=$row; }
    if($dryRun){out('dry_run','ok');out('categories','73,74');exit(0);} $dir=backup_dir(); write_backup($dir,'before.json',['categories'=>$before]); $restore="-- ".PATCH_NAME." rollback\nSTART TRANSACTION;\n"; foreach($before as $row) $restore.=restore_update($db,$categoryDescription,$row,['name','meta_keyword'],'category_id='.(int)$row['category_id'].' AND language_id='.LANGUAGE_ID); $restore.="COMMIT;\n"; write_restore($dir,$restore); $db->begin_transaction();$tx=true;
    foreach($payload['categories'] as $change){$sql='UPDATE '.qi($categoryDescription).' SET name=?'.(array_key_exists('meta_keyword',$change)?',meta_keyword=?':'').' WHERE category_id=? AND language_id=?';$params=[$change['name']];if(array_key_exists('meta_keyword',$change))$params[]=$change['meta_keyword'];$params[]= $change['category_id'];$params[]=LANGUAGE_ID;need(exec_sql($db,$sql,$params)===1,'category_update_failed:'.$change['category_id']);}
    foreach($payload['categories'] as $change){$after=one($db,'SELECT name,meta_keyword FROM '.qi($categoryDescription).' WHERE category_id=? AND language_id=?',[$change['category_id'],LANGUAGE_ID]);need($after['name']===$change['name'],'category_name_verify_failed:'.$change['category_id']);$state=one($db,'SELECT status FROM '.qi($category).' WHERE category_id=?',[$change['category_id']]);need((int)$state['status']===0,'category_status_changed:'.$change['category_id']);}
    $db->commit();$tx=false;out('backup',$dir);out('attribute_43_44','verified_preexisting');out('updated_categories','2');out('done','ok');@unlink(__FILE__);
} catch(Throwable $e){if($tx)$db->rollback();fwrite(STDERR,'ERROR='.$e->getMessage().PHP_EOL);exit(1);}
