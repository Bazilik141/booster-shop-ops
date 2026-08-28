<?php
declare(strict_types=1);

/* Read-only attribute-name diagnosis for WP1 products 142 and 143. */
const LANGUAGE_ID = 4;
function fail(string $message): void { throw new RuntimeException($message); }
function out(string $key, string $value): void { echo $key . '=' . $value . PHP_EOL; }
function need(bool $ok, string $message): void { if (!$ok) fail($message); }
function qi(string $name): string { return chr(96) . str_replace(chr(96), chr(96) . chr(96), $name) . chr(96); }
function bind(mysqli_stmt $statement, array $params): void { if ($params === []) return; $types = str_repeat('s', count($params)); $args = [&$types]; foreach ($params as $i => $value) { $params[$i] = (string)$value; $args[] = &$params[$i]; } need(call_user_func_array([$statement, 'bind_param'], $args), 'bind_failed:' . $statement->error); }
function rows(mysqli $db, string $sql, array $params = []): array {
    $statement = $db->prepare($sql); need($statement instanceof mysqli_stmt, 'prepare_failed:' . $db->error); bind($statement, $params); need($statement->execute(), 'execute_failed:' . $statement->error);
    $meta = $statement->result_metadata(); need($meta instanceof mysqli_result, 'result_metadata_failed'); $fields = $meta->fetch_fields(); $values = []; $refs = [];
    foreach ($fields as $i => $field) { $values[$i] = null; $refs[] = &$values[$i]; } need(call_user_func_array([$statement, 'bind_result'], $refs), 'result_bind_failed');
    $result = []; while ($statement->fetch()) { $row = []; foreach ($fields as $i => $field) $row[$field->name] = $values[$i]; $result[] = $row; } $statement->close(); return $result;
}
function table(mysqli $db, string $prefix, string $name): string { $full = $prefix . $name; $statement = $db->prepare('SELECT 1 FROM ' . qi($full) . ' LIMIT 0'); need($statement instanceof mysqli_stmt, 'table_missing:' . $full); $statement->execute(); $statement->close(); return $full; }
function emit_rows(string $label, array $items): void { out($label . '_count', (string)count($items)); foreach ($items as $index => $item) out($label . '_' . $index, json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)); }
function lint_self(): void { $status = 1; $output = []; @exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__), $output, $status); need($status === 0, 'php_lint_failed:' . implode(' ', $output)); out('php_lint', 'ok'); }

lint_self();
try {
    need(PHP_SAPI === 'cli', 'cli_only'); need(is_file(getcwd() . DIRECTORY_SEPARATOR . 'config.php'), 'run_from_public_html_required'); require getcwd() . DIRECTORY_SEPARATOR . 'config.php';
    foreach (['DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PORT','DB_PREFIX'] as $constant) need(defined($constant), 'config_constant_missing:' . $constant); need((string)DB_PREFIX === 'ocp5_', 'db_prefix_mismatch_expected_ocp5_');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); $db = new mysqli((string)DB_HOSTNAME, (string)DB_USERNAME, (string)DB_PASSWORD, (string)DB_DATABASE, (int)DB_PORT); $db->set_charset('utf8mb4');
    $product = table($db, (string)DB_PREFIX, 'product'); $attribute = table($db, (string)DB_PREFIX, 'product_attribute'); $description = table($db, (string)DB_PREFIX, 'attribute_description');
    out('cwd', getcwd()); out('time_utc', gmdate('c')); out('mode', 'read_only');
    emit_rows('products', rows($db, 'SELECT product_id,model,status FROM ' . qi($product) . ' WHERE product_id IN (142,143) ORDER BY product_id'));
    emit_rows('product_attributes', rows($db, 'SELECT pa.product_id,pa.attribute_id,ad.name,pa.text FROM ' . qi($attribute) . ' pa JOIN ' . qi($description) . ' ad ON ad.attribute_id=pa.attribute_id AND ad.language_id=pa.language_id WHERE pa.product_id IN (142,143) AND pa.language_id=? ORDER BY pa.product_id,pa.attribute_id', [LANGUAGE_ID]));
    foreach (['Місткість дисплея','Місткість дисплею','Внутрішнє зберігання','Сумісність'] as $name) { out('exact_name', $name); emit_rows('exact_' . hash('crc32b', $name), rows($db, 'SELECT attribute_id,name FROM ' . qi($description) . ' WHERE language_id=? AND name=? ORDER BY attribute_id', [LANGUAGE_ID, $name])); }
    emit_rows('semantic_candidates', rows($db, 'SELECT attribute_id,name FROM ' . qi($description) . ' WHERE language_id=? AND (name LIKE ? OR name LIKE ? OR name LIKE ?) ORDER BY attribute_id LIMIT 20', [LANGUAGE_ID, '%Містк%', '%зберіган%', '%Сумісн%']));
    out('done', 'ok'); @unlink(__FILE__);
} catch (Throwable $e) { fwrite(STDERR, 'ERROR=' . $e->getMessage() . PHP_EOL); exit(1); }
