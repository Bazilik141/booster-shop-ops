<?php
declare(strict_types=1);

/*
 * Read-only diagnosis for CONTENT-QUALITY_create-br-charm-200_20260828.php.
 * It reports bounded row counts and identifiers only; it never writes the DB.
 * The runner self-deletes after a successful report.
 */

const PATCH_NAME = 'CONTENT-QUALITY_wp3-preflight-diagnostic_20260828';
const LANGUAGE_ID = 4;
const SKU = 'BR-CHARM-200';
const KEYWORD = 'brelok-kliker-charmander-pokemon-3d-druk';

function fail(string $message): void { throw new RuntimeException($message); }
function out(string $key, string $value): void { echo $key . '=' . $value . PHP_EOL; }
function need(bool $ok, string $message): void { if (!$ok) fail($message); }
function qi(string $name): string { return chr(96) . str_replace(chr(96), chr(96) . chr(96), $name) . chr(96); }
function bind(mysqli_stmt $statement, array $params): void {
    if ($params === []) return;
    $types = str_repeat('s', count($params)); $args = [&$types];
    foreach ($params as $i => $value) { $params[$i] = (string)$value; $args[] = &$params[$i]; }
    need(call_user_func_array([$statement, 'bind_param'], $args), 'bind_failed:' . $statement->error);
}
function rows(mysqli $db, string $sql, array $params = []): array {
    $statement = $db->prepare($sql); need($statement instanceof mysqli_stmt, 'prepare_failed:' . $db->error);
    bind($statement, $params); need($statement->execute(), 'execute_failed:' . $statement->error);
    $meta = $statement->result_metadata(); need($meta instanceof mysqli_result, 'result_metadata_failed');
    $fields = $meta->fetch_fields(); $values = []; $refs = [];
    foreach ($fields as $i => $field) { $values[$i] = null; $refs[] = &$values[$i]; }
    need(call_user_func_array([$statement, 'bind_result'], $refs), 'result_bind_failed');
    $result = [];
    while ($statement->fetch()) { $row = []; foreach ($fields as $i => $field) $row[$field->name] = $values[$i]; $result[] = $row; }
    $statement->close(); return $result;
}
function table(mysqli $db, string $prefix, string $name): string {
    $full = $prefix . $name; $statement = $db->prepare('SELECT 1 FROM ' . qi($full) . ' LIMIT 0');
    need($statement instanceof mysqli_stmt, 'table_missing:' . $full); $statement->execute(); $statement->close(); return $full;
}
function emit_rows(string $label, array $items): void {
    out($label . '_count', (string)count($items));
    foreach ($items as $index => $item) out($label . '_' . $index, json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}
function lint_self(): void {
    $status = 1; $output = []; @exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__), $output, $status);
    need($status === 0, 'php_lint_failed:' . implode(' ', $output)); out('php_lint', 'ok');
}

lint_self();
try {
    need(PHP_SAPI === 'cli', 'cli_only'); need(is_file(getcwd() . DIRECTORY_SEPARATOR . 'config.php'), 'run_from_public_html_required');
    require getcwd() . DIRECTORY_SEPARATOR . 'config.php';
    foreach (['DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PORT','DB_PREFIX'] as $constant) need(defined($constant), 'config_constant_missing:' . $constant);
    need((string)DB_PREFIX === 'ocp5_', 'db_prefix_mismatch_expected_ocp5_');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli((string)DB_HOSTNAME, (string)DB_USERNAME, (string)DB_PASSWORD, (string)DB_DATABASE, (int)DB_PORT); $db->set_charset('utf8mb4');
    $product = table($db, (string)DB_PREFIX, 'product'); $seo = table($db, (string)DB_PREFIX, 'seo_url');
    $attributeDescription = table($db, (string)DB_PREFIX, 'attribute_description');
    $category = table($db, (string)DB_PREFIX, 'category');
    out('cwd', getcwd()); out('time_utc', gmdate('c')); out('mode', 'read_only');
    emit_rows('product_sku', rows($db, 'SELECT product_id,model,sku,status,quantity,price FROM ' . qi($product) . ' WHERE model=? OR sku=? ORDER BY product_id', [SKU, SKU]));
    emit_rows('seo_keyword', rows($db, 'SELECT seo_url_id,store_id,language_id,' . qi('key') . ',value,keyword FROM ' . qi($seo) . ' WHERE keyword=? ORDER BY seo_url_id', [KEYWORD]));
    emit_rows('template_126', rows($db, 'SELECT product_id,model,sku,manufacturer_id,shipping,tax_class_id,weight,weight_class_id,length,width,height,length_class_id,sort_order FROM ' . qi($product) . ' WHERE product_id=126 AND model=\'BR-CHARM-100\''));
    emit_rows('categories', rows($db, 'SELECT category_id,status FROM ' . qi($category) . ' WHERE category_id IN (59,73) ORDER BY category_id'));
    $names = ['Тип виробу','Країна виготовлення','Спосіб виготовлення','Матеріал','Колір','Матеріал фурнітури','Розміри','Маса','Комплектація','Рухомі елементи','Вікове позиціонування','Типовий строк виготовлення при відсутності на складі','Може трапитись у Mystery Box'];
    foreach ($names as $name) { $label = 'attribute_' . hash('crc32b', $name); out($label . '_name', $name); emit_rows($label, rows($db, 'SELECT attribute_id,name FROM ' . qi($attributeDescription) . ' WHERE language_id=? AND name=? ORDER BY attribute_id', [LANGUAGE_ID, $name])); }
    emit_rows('attribute_43', rows($db, 'SELECT attribute_id,name FROM ' . qi($attributeDescription) . ' WHERE attribute_id=43 AND language_id=?', [LANGUAGE_ID]));
    emit_rows('attribute_44', rows($db, 'SELECT attribute_id,name FROM ' . qi($attributeDescription) . ' WHERE attribute_id=44 AND language_id=?', [LANGUAGE_ID]));
    out('done', 'ok'); @unlink(__FILE__);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR=' . $e->getMessage() . PHP_EOL); exit(1);
}
