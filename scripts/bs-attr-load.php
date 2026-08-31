<?php
/**
 * bs-attr-load — reusable loader for canonical 3D-product attributes.
 *
 * Run this uploaded copy from ~/public_html, where config.php is present:
 *   php bs-attr-load.php --report
 *   php bs-attr-load.php --dry-run bs-attr-3dp-YYYYMMDD.csv
 *   php bs-attr-load.php --apply bs-attr-3dp-YYYYMMDD.csv --owner-approved
 *
 * PHP 8.0 compatible. This is a persistent repository tool, not a patch:
 * it never self-deletes and it never creates/renames/deletes definitions or
 * products. The only possible database writes are to DB_PREFIXproduct_attribute.
 *
 * --apply is deliberately a separate owner gate. It creates
 * _patch_backups/bs-attr-load-<UTC>/before.json and restore.sql before its
 * single transaction. restore.sql keys every row by
 * (product_id, attribute_id, language_id), never a surrogate id.
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
mysqli_report(MYSQLI_REPORT_OFF);

const BS_ATTR_LANGUAGE_ID = 4;
const BS_ATTR_TOOL_NAME = 'bs-attr-load';

/** @return never */
function bs_attr_fail(string $message): void {
    throw new RuntimeException($message);
}

function bs_attr_out(string $key, string $value): void {
    echo $key . '=' . $value . PHP_EOL;
}

function bs_attr_need(bool $condition, string $message): void {
    if (!$condition) {
        bs_attr_fail($message);
    }
}

function bs_attr_identifier(string $name): string {
    return '`' . str_replace('`', '``', $name) . '`';
}

function bs_attr_sql_string(mysqli $db, string $value): string {
    return "'" . $db->real_escape_string($value) . "'";
}

function bs_attr_value_display(string $value): string {
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json === false ? '[invalid UTF-8]' : $json;
}

/**
 * Bind without mysqlnd. All values are bound as strings; MySQL safely casts
 * the numeric identifiers in the fixed statements below.
 *
 * @param array<int, scalar> $params
 */
function bs_attr_bind(mysqli_stmt $statement, array $params): void {
    if ($params === []) {
        return;
    }

    $types = str_repeat('s', count($params));
    $arguments = [&$types];
    foreach ($params as $index => $value) {
        $params[$index] = (string) $value;
        $arguments[] = &$params[$index];
    }
    bs_attr_need((bool) call_user_func_array([$statement, 'bind_param'], $arguments), 'bind_failed:' . $statement->error);
}

/** @param array<int, scalar> $params */
function bs_attr_statement(mysqli $db, string $sql, array $params = []): mysqli_stmt {
    $statement = $db->prepare($sql);
    bs_attr_need($statement instanceof mysqli_stmt, 'prepare_failed:' . $db->error);
    bs_attr_bind($statement, $params);
    bs_attr_need($statement->execute(), 'execute_failed:' . $statement->error);
    return $statement;
}

/**
 * Fetch without mysqli_stmt::get_result(), because production can use libmysql
 * without mysqlnd.
 *
 * @param array<int, scalar> $params
 * @return array<int, array<string, mixed>>
 */
function bs_attr_rows(mysqli $db, string $sql, array $params = []): array {
    $statement = bs_attr_statement($db, $sql, $params);
    $metadata = $statement->result_metadata();
    bs_attr_need($metadata instanceof mysqli_result, 'result_metadata_failed');

    $fields = $metadata->fetch_fields();
    $values = [];
    $references = [];
    foreach ($fields as $index => $field) {
        $values[$index] = null;
        $references[] = &$values[$index];
    }
    bs_attr_need((bool) call_user_func_array([$statement, 'bind_result'], $references), 'result_bind_failed:' . $statement->error);

    $result = [];
    while ($statement->fetch()) {
        $row = [];
        foreach ($fields as $index => $field) {
            $row[$field->name] = $values[$index];
        }
        $result[] = $row;
    }
    $metadata->free();
    $statement->close();
    return $result;
}

/** @param array<int, scalar> $params */
function bs_attr_execute(mysqli $db, string $sql, array $params = []): int {
    $statement = bs_attr_statement($db, $sql, $params);
    $affected = $statement->affected_rows;
    $statement->close();
    return $affected;
}

/** @return array<string, mixed>|null */
function bs_attr_one(mysqli $db, string $sql, array $params = []): ?array {
    $rows = bs_attr_rows($db, $sql, $params);
    bs_attr_need(count($rows) <= 1, 'expected_at_most_one_row_got_' . count($rows));
    return $rows[0] ?? null;
}

function bs_attr_table(mysqli $db, string $prefix, string $suffix): string {
    $table = $prefix . $suffix;
    $probe = $db->query('SELECT 1 FROM ' . bs_attr_identifier($table) . ' LIMIT 0');
    bs_attr_need($probe instanceof mysqli_result || $probe === true, 'required_table_missing:' . $table . ':' . $db->error);
    if ($probe instanceof mysqli_result) {
        $probe->free();
    }
    return $table;
}

/** @return array{mode:string,csv:?string} */
function bs_attr_parse_command(): array {
    $args = array_slice($GLOBALS['argv'], 1);
    if ($args === [] || $args === ['--report']) {
        return ['mode' => 'report', 'csv' => null];
    }
    if (count($args) === 2 && $args[0] === '--dry-run') {
        return ['mode' => 'dry-run', 'csv' => $args[1]];
    }
    if (count($args) === 3 && $args[0] === '--apply' && $args[2] === '--owner-approved') {
        return ['mode' => 'apply', 'csv' => $args[1]];
    }
    if (count($args) === 2 && $args[0] === '--apply') {
        bs_attr_fail('owner_approval_required: --apply needs the explicit --owner-approved flag after the CSV filename');
    }
    bs_attr_fail('usage: php bs-attr-load.php [--report | --dry-run file.csv | --apply file.csv --owner-approved]');
}

/** @return array{0:mysqli,1:string,2:array<string,string>} */
function bs_attr_connect(): array {
    bs_attr_need(PHP_SAPI === 'cli', 'cli_only');
    $configPath = getcwd() . DIRECTORY_SEPARATOR . 'config.php';
    bs_attr_need(is_file($configPath), 'config.php_not_found: run from ~/public_html');
    require $configPath;

    foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PREFIX'] as $constant) {
        bs_attr_need(defined($constant), 'config_constant_missing:' . $constant);
    }

    $prefix = (string) DB_PREFIX;
    bs_attr_need((bool) preg_match('/^[A-Za-z0-9_]*$/', $prefix), 'unsafe_DB_PREFIX');
    $db = @new mysqli(
        (string) DB_HOSTNAME,
        (string) DB_USERNAME,
        (string) DB_PASSWORD,
        (string) DB_DATABASE,
        defined('DB_PORT') ? (int) DB_PORT : 3306
    );
    bs_attr_need($db->connect_errno === 0, 'database_connection_failed:' . $db->connect_error);
    bs_attr_need($db->set_charset('utf8mb4'), 'database_charset_failed:' . $db->error);

    $tables = [];
    foreach (['product', 'product_description', 'product_attribute', 'attribute_description'] as $suffix) {
        $tables[$suffix] = bs_attr_table($db, $prefix, $suffix);
    }
    return [$db, $prefix, $tables];
}

/** @param array<string,string> $tables */
function bs_attr_assert_language(mysqli $db, array $tables): void {
    $rows = bs_attr_rows(
        $db,
        'SELECT language_id, COUNT(*) AS n FROM ' . bs_attr_identifier($tables['product_description'])
        . ' GROUP BY language_id ORDER BY n DESC, language_id ASC'
    );
    bs_attr_need($rows !== [], 'product_description_is_empty');

    $topLanguage = (int) $rows[0]['language_id'];
    $topCount = (int) $rows[0]['n'];
    bs_attr_need($topLanguage === BS_ATTR_LANGUAGE_ID, 'language_id_4_is_not_the_live_majority:actual=' . $topLanguage);
    $nextCount = isset($rows[1]) ? (int) $rows[1]['n'] : 0;
    bs_attr_need($topCount > $nextCount, 'language_id_4_majority_is_tied');
}

/** @return array<int,string> */
function bs_attr_required_names(string $sku): array {
    $mandatory = [
        'Країна виготовлення',
        'Спосіб виготовлення',
        'Розміри',
        'Маса',
        'Комплектація',
        'Рухомі елементи',
        'Вікове позиціонування',
        'Типовий строк виготовлення при відсутності на складі',
        'Може трапитись у Mystery Box',
        'Тип виробу',
        'Матеріал',
        'Колір',
    ];

    if (strpos($sku, 'BR-') === 0) {
        $mandatory[] = 'Матеріал фурнітури';
        return $mandatory;
    }
    if (strpos($sku, 'FIG-') === 0) {
        $mandatory[] = 'Призначення';
        return $mandatory;
    }
    if (strpos($sku, 'ACC-3D-') === 0) {
        if (!preg_match('/^ACC-3D-.+-([0-9])[0-9][0-9]$/', $sku, $matches)) {
            bs_attr_fail('cannot_determine_ACC_3D_tail_from_sku:' . $sku);
        }
        $category = $matches[1];
        if (in_array($category, ['1', '2', '3', '7'], true)) {
            $mandatory[] = 'Сумісність';
            return $mandatory;
        }
        if (in_array($category, ['4', '5', '6', '8'], true)) {
            $mandatory[] = 'Призначення';
            return $mandatory;
        }
        bs_attr_fail('unsupported_ACC_3D_category_digit:' . $sku);
    }
    bs_attr_fail('sku_is_not_a_3D_attribute_loader_type:' . $sku);
}

/**
 * Canonical OpenCart product code: explicit sku wins, otherwise use model.
 * The alias is fixed by this script, but validate it before interpolation.
 */
function bs_attr_product_code_expression(string $alias): string {
    bs_attr_need((bool) preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $alias), 'unsafe_sql_alias');
    return "COALESCE(NULLIF(TRIM(" . $alias . ".sku), ''), TRIM(" . $alias . '.model))';
}

/**
 * Read-only report. It joins by attribute_id and never creates a name-to-id
 * map, so the known duplicate definition named Матеріал cannot be silently
 * collapsed or make the first audit unusable.
 *
 * @param array<string,string> $tables
 */
function bs_attr_report(mysqli $db, array $tables): void {
    $productCode = bs_attr_product_code_expression('p');
    $canonicalDefinitionNames = [
        'Країна виготовлення',
        'Спосіб виготовлення',
        'Розміри',
        'Маса',
        'Комплектація',
        'Рухомі елементи',
        'Вікове позиціонування',
        'Типовий строк виготовлення при відсутності на складі',
        'Може трапитись у Mystery Box',
        'Тип виробу',
        'Матеріал',
        'Колір',
        'Матеріал фурнітури',
        'Сумісність',
        'Призначення',
    ];
    $definitionRows = bs_attr_rows(
        $db,
        'SELECT ad.name, ad.attribute_id, COUNT(DISTINCT pa.product_id) AS products_using'
        . ' FROM ' . bs_attr_identifier($tables['attribute_description']) . ' ad'
        . ' LEFT JOIN ' . bs_attr_identifier($tables['product_attribute']) . ' pa'
        . ' ON pa.attribute_id = ad.attribute_id AND pa.language_id = ad.language_id'
        . ' WHERE ad.language_id = ? AND ad.name IN (' . implode(',', array_fill(0, count($canonicalDefinitionNames), '?')) . ')'
        . ' GROUP BY ad.name, ad.attribute_id ORDER BY ad.name, ad.attribute_id',
        array_merge([BS_ATTR_LANGUAGE_ID], $canonicalDefinitionNames)
    );
    $definitionsByName = [];
    foreach ($definitionRows as $definition) {
        $definitionsByName[(string) $definition['name']][] = $definition;
    }

    echo 'DEFINITION_DUPLICATE_CHECK language_id=' . BS_ATTR_LANGUAGE_ID . ' canonical_names=' . count($canonicalDefinitionNames) . PHP_EOL;
    $duplicateDefinitionCount = 0;
    foreach ($canonicalDefinitionNames as $definitionName) {
        $definitions = $definitionsByName[$definitionName] ?? [];
        if (count($definitions) <= 1) {
            continue;
        }
        $ids = [];
        $usage = [];
        foreach ($definitions as $definition) {
            $attributeId = (int) $definition['attribute_id'];
            $ids[] = (string) $attributeId;
            $usage[] = $attributeId . ':' . (int) $definition['products_using'];
        }
        echo 'DEFINITION_DUPLICATE name=' . $definitionName
            . ' attribute_ids=' . implode(',', $ids)
            . ' products_using=' . implode(',', $usage) . PHP_EOL;
        $duplicateDefinitionCount++;
    }
    bs_attr_out('definition_duplicates', (string) $duplicateDefinitionCount);
    echo PHP_EOL;

    $productRows = bs_attr_rows(
        $db,
        'SELECT p.product_id, p.sku, p.model, ' . $productCode . ' AS product_code,'
        . " CASE WHEN NULLIF(TRIM(p.sku), '') IS NULL THEN 'model' ELSE 'sku' END AS code_source, pd.name"
        . ' FROM ' . bs_attr_identifier($tables['product']) . ' p'
        . ' LEFT JOIN ' . bs_attr_identifier($tables['product_description']) . ' pd'
        . ' ON pd.product_id = p.product_id AND pd.language_id = ' . BS_ATTR_LANGUAGE_ID
        . " WHERE " . $productCode . " REGEXP '^(BR-|FIG-|ACC-3D-)'"
        . ' ORDER BY product_code, p.product_id'
    );
    $attributeRows = bs_attr_rows(
        $db,
        'SELECT pa.product_id, pa.attribute_id, pa.text, ad.name'
        . ' FROM ' . bs_attr_identifier($tables['product_attribute']) . ' pa'
        . ' JOIN ' . bs_attr_identifier($tables['product']) . ' p ON p.product_id = pa.product_id'
        . ' LEFT JOIN ' . bs_attr_identifier($tables['attribute_description']) . ' ad'
        . ' ON ad.attribute_id = pa.attribute_id AND ad.language_id = ' . BS_ATTR_LANGUAGE_ID
        . ' WHERE pa.language_id = ' . BS_ATTR_LANGUAGE_ID
        . " AND " . $productCode . " REGEXP '^(BR-|FIG-|ACC-3D-)'"
        . ' ORDER BY pa.product_id, pa.attribute_id'
    );

    $productIdsByCode = [];
    foreach ($productRows as $product) {
        $code = (string) $product['product_code'];
        $productIdsByCode[$code][] = (int) $product['product_id'];
    }
    foreach ($productIdsByCode as $code => $productIds) {
        if (count($productIds) > 1) {
            bs_attr_fail('ambiguous_product_code:' . $code . ':product_ids=' . implode(',', $productIds));
        }
    }

    $byProduct = [];
    foreach ($attributeRows as $row) {
        $byProduct[(int) $row['product_id']][] = $row;
    }

    $tailNames = ['Матеріал фурнітури', 'Сумісність', 'Призначення'];
    $productsAtThirteen = 0;
    $productsWithGaps = 0;
    $codeFieldConflicts = 0;
    $distinctGaps = [];

    foreach ($productRows as $product) {
        $productId = (int) $product['product_id'];
        $code = (string) $product['product_code'];
        $codeSource = (string) $product['code_source'];
        $skuField = trim((string) $product['sku']);
        $modelField = trim((string) $product['model']);
        $name = $product['name'] === null || trim((string) $product['name']) === '' ? '[missing language 4 name]' : (string) $product['name'];
        $attributes = $byProduct[$productId] ?? [];
        if (count($attributes) === 13) {
            $productsAtThirteen++;
        }

        if ($skuField !== '' && $modelField !== '' && $skuField !== $modelField) {
            echo 'CODE_FIELD_CONFLICT product_id=' . $productId . ' sku=' . $skuField . ' model=' . $modelField . PHP_EOL;
            $codeFieldConflicts++;
        }
        echo 'PRODUCT code=' . $code . ' source=' . $codeSource . ' product_id=' . $productId . ' name=' . bs_attr_value_display($name) . PHP_EOL;
        echo 'ATTRIBUTE_COUNT=' . count($attributes) . PHP_EOL;

        $issues = [];
        try {
            $required = bs_attr_required_names($code);
        } catch (Throwable $error) {
            $issues[] = 'SCHEMA_ERROR ' . $error->getMessage();
            $distinctGaps['SCHEMA_ERROR ' . $error->getMessage()] = true;
            $required = [];
        }

        $byName = [];
        foreach ($attributes as $attribute) {
            $attributeName = $attribute['name'] === null ? '[undefined attribute_id ' . (int) $attribute['attribute_id'] . ']' : (string) $attribute['name'];
            $byName[$attributeName][] = $attribute;
        }

        foreach ($required as $attributeName) {
            $entries = $byName[$attributeName] ?? [];
            if ($entries === []) {
                $issues[] = 'MISSING attribute=' . bs_attr_value_display($attributeName);
                $distinctGaps['MISSING attribute=' . $attributeName] = true;
                continue;
            }
            if (count($entries) > 1) {
                $ids = [];
                foreach ($entries as $entry) {
                    $ids[] = (string) (int) $entry['attribute_id'];
                }
                $issues[] = 'DUPLICATE_VALUE attribute=' . bs_attr_value_display($attributeName) . ' attribute_ids=' . implode(',', $ids);
            }
            foreach ($entries as $entry) {
                if (trim((string) $entry['text']) === '') {
                    $issues[] = 'EMPTY attribute=' . bs_attr_value_display($attributeName) . ' attribute_id=' . (int) $entry['attribute_id'];
                    $distinctGaps['EMPTY attribute=' . $attributeName] = true;
                }
            }
        }

        if ($required !== []) {
            $expectedTail = $required[12];
            foreach ($tailNames as $tailName) {
                if ($tailName !== $expectedTail && isset($byName[$tailName])) {
                    $issues[] = 'WRONG_TAIL expected=' . bs_attr_value_display($expectedTail) . ' found=' . bs_attr_value_display($tailName);
                }
            }
            foreach ($byName as $attributeName => $entries) {
                if (in_array($attributeName, $required, true) || in_array($attributeName, $tailNames, true)) {
                    continue;
                }
                foreach ($entries as $entry) {
                    $issues[] = 'EXTRA attribute=' . bs_attr_value_display($attributeName) . ' attribute_id=' . (int) $entry['attribute_id'];
                }
            }
        }

        if (count($attributes) !== 13) {
            $issues[] = 'COUNT_MISMATCH expected=13 actual=' . count($attributes);
        }
        if ($issues === []) {
            echo 'OK=canonical_13' . PHP_EOL;
        } else {
            foreach (array_values(array_unique($issues)) as $issue) {
                echo $issue . PHP_EOL;
            }
        }
        echo PHP_EOL;

        $hasGap = false;
        foreach ($issues as $issue) {
            if (strpos($issue, 'MISSING ') === 0 || strpos($issue, 'EMPTY ') === 0 || strpos($issue, 'SCHEMA_ERROR ') === 0) {
                $hasGap = true;
                break;
            }
        }
        if ($hasGap) {
            $productsWithGaps++;
        }
    }

    bs_attr_out('products_seen', (string) count($productRows));
    bs_attr_out('products_exactly_13_attributes', (string) $productsAtThirteen);
    bs_attr_out('products_with_value_gaps', (string) $productsWithGaps);
    bs_attr_out('code_field_conflicts', (string) $codeFieldConflicts);
    bs_attr_out('distinct_value_gaps', (string) count($distinctGaps));
    foreach (array_keys($distinctGaps) as $gap) {
        echo 'GAP ' . $gap . PHP_EOL;
    }
    bs_attr_out('write_mode', 'none');
    bs_attr_out('done', 'ok');
}

/** @return array<int,array{line:int,sku:string,attribute:string,value:string}> */
function bs_attr_parse_csv(string $path): array {
    bs_attr_need(is_file($path) && is_readable($path), 'csv_not_readable:' . $path);
    $handle = fopen($path, 'rb');
    bs_attr_need($handle !== false, 'csv_open_failed:' . $path);

    $header = fgetcsv($handle);
    bs_attr_need(is_array($header), 'csv_header_missing');
    if (isset($header[0])) {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
    }
    bs_attr_need($header === ['sku', 'attribute', 'value'], 'csv_header_must_be:sku,attribute,value');

    $rows = [];
    $seenPairs = [];
    $line = 1;
    while (($fields = fgetcsv($handle)) !== false) {
        $line++;
        if ($fields === [null] || $fields === ['']) {
            continue;
        }
        bs_attr_need(count($fields) === 3, 'csv_line_' . $line . '_must_have_exactly_3_columns');
        $sku = trim((string) $fields[0]);
        $attribute = trim((string) $fields[1]);
        $value = (string) $fields[2];
        bs_attr_need($sku !== '', 'csv_line_' . $line . '_sku_is_blank');
        bs_attr_need($attribute !== '', 'csv_line_' . $line . '_attribute_is_blank');
        bs_attr_need((bool) preg_match('//u', $sku) && (bool) preg_match('//u', $attribute) && (bool) preg_match('//u', $value), 'csv_line_' . $line . '_is_not_valid_UTF_8');
        $pair = $sku . "\0" . $attribute;
        bs_attr_need(!isset($seenPairs[$pair]), 'duplicate_csv_pair:line_' . $line . ':' . $sku . ':' . $attribute);
        $seenPairs[$pair] = true;
        $rows[] = ['line' => $line, 'sku' => $sku, 'attribute' => $attribute, 'value' => $value];
    }
    fclose($handle);
    bs_attr_need($rows !== [], 'csv_has_no_data_rows');
    return $rows;
}

/** @param array<string,string> $tables @return array{product_id:int} */
function bs_attr_resolve_product(mysqli $db, array $tables, string $sku): array {
    $productCode = bs_attr_product_code_expression('p');
    $rows = bs_attr_rows(
        $db,
        'SELECT p.product_id FROM ' . bs_attr_identifier($tables['product']) . ' p WHERE ' . $productCode . ' = ?',
        [$sku]
    );
    if ($rows === []) {
        bs_attr_fail('unknown_product_code:' . $sku);
    }
    if (count($rows) !== 1) {
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (string) (int) $row['product_id'];
        }
        bs_attr_fail('ambiguous_product_code:' . $sku . ':product_ids=' . implode(',', $ids));
    }
    return ['product_id' => (int) $rows[0]['product_id']];
}

/**
 * Resolve an input name only at execution-plan time. Crucially, this does not
 * build a lossy name=>id dictionary: every duplicate is fatal and lists ids.
 *
 * @param array<string,string> $tables
 */
function bs_attr_resolve_attribute(mysqli $db, array $tables, string $name): int {
    $rows = bs_attr_rows(
        $db,
        'SELECT attribute_id FROM ' . bs_attr_identifier($tables['attribute_description'])
        . ' WHERE language_id = ? AND name = ? ORDER BY attribute_id',
        [BS_ATTR_LANGUAGE_ID, $name]
    );
    if ($rows === []) {
        bs_attr_fail('attribute_definition_missing:' . $name);
    }
    if (count($rows) !== 1) {
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (string) (int) $row['attribute_id'];
        }
        bs_attr_fail('attribute_name_ambiguous:' . $name . ':attribute_ids=' . implode(',', $ids));
    }
    return (int) $rows[0]['attribute_id'];
}

/**
 * @param array<string,string> $tables
 * @param array<int,array{line:int,sku:string,attribute:string,value:string}> $csvRows
 * @return array<int,array<string,mixed>>
 */
function bs_attr_build_plan(mysqli $db, array $tables, array $csvRows): array {
    $products = [];
    $definitions = [];
    $plan = [];

    foreach ($csvRows as $csvRow) {
        $sku = $csvRow['sku'];
        if (!isset($products[$sku])) {
            $products[$sku] = bs_attr_resolve_product($db, $tables, $sku);
        }
        $required = bs_attr_required_names($sku);
        bs_attr_need(in_array($csvRow['attribute'], $required, true), 'attribute_not_in_canonical_schema:' . $sku . ':' . $csvRow['attribute']);

        $attributeName = $csvRow['attribute'];
        if (!isset($definitions[$attributeName])) {
            $definitions[$attributeName] = bs_attr_resolve_attribute($db, $tables, $attributeName);
        }
        $productId = (int) $products[$sku]['product_id'];
        $attributeId = (int) $definitions[$attributeName];
        $existing = bs_attr_rows(
            $db,
            'SELECT text FROM ' . bs_attr_identifier($tables['product_attribute'])
            . ' WHERE product_id = ? AND attribute_id = ? AND language_id = ?',
            [$productId, $attributeId, BS_ATTR_LANGUAGE_ID]
        );
        bs_attr_need(count($existing) <= 1, 'duplicate_live_attribute_rows:' . $sku . ':' . $attributeName . ':key=' . $productId . ',' . $attributeId . ',' . BS_ATTR_LANGUAGE_ID);

        $action = 'skip';
        $before = null;
        if (trim($csvRow['value']) === '') {
            $action = 'skip';
        } elseif ($existing === []) {
            $action = 'insert';
        } else {
            $before = (string) $existing[0]['text'];
            $action = $before === $csvRow['value'] ? 'unchanged' : 'update';
        }
        $plan[] = [
            'line' => $csvRow['line'],
            'sku' => $sku,
            'product_id' => $productId,
            'attribute' => $attributeName,
            'attribute_id' => $attributeId,
            'value' => $csvRow['value'],
            'before' => $before,
            'action' => $action,
        ];
    }
    return $plan;
}

/** @param array<int,array<string,mixed>> $plan */
function bs_attr_print_plan(array $plan, string $mode): array {
    $counts = ['inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0];
    foreach ($plan as $item) {
        $prefix = strtoupper((string) $item['action']);
        echo $prefix . ' line=' . $item['line']
            . ' sku=' . $item['sku']
            . ' product_id=' . $item['product_id']
            . ' attribute=' . bs_attr_value_display((string) $item['attribute'])
            . ' attribute_id=' . $item['attribute_id'];
        if ($item['action'] === 'update') {
            echo ' before=' . bs_attr_value_display((string) $item['before']) . ' after=' . bs_attr_value_display((string) $item['value']);
            $counts['updated']++;
        } elseif ($item['action'] === 'insert') {
            echo ' value=' . bs_attr_value_display((string) $item['value']);
            $counts['inserted']++;
        } elseif ($item['action'] === 'unchanged') {
            echo ' value=' . bs_attr_value_display((string) $item['value']);
            $counts['unchanged']++;
        } else {
            echo ' reason=blank_csv_value_not_written';
            $counts['skipped']++;
        }
        echo PHP_EOL;
    }
    bs_attr_out('mode', $mode);
    return $counts;
}

/** @param array<int,array<string,mixed>> $changed */
function bs_attr_create_backup(mysqli $db, string $productAttributeTable, array $changed): string {
    $directory = getcwd() . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR
        . BS_ATTR_TOOL_NAME . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
    bs_attr_need(!file_exists($directory), 'backup_path_already_exists:' . $directory);
    bs_attr_need(@mkdir($directory, 0750, true) || is_dir($directory), 'backup_directory_create_failed:' . $directory);

    $before = [
        'tool' => BS_ATTR_TOOL_NAME,
        'created_utc' => gmdate('c'),
        'language_id' => BS_ATTR_LANGUAGE_ID,
        'table' => $productAttributeTable,
        'affected_rows' => [],
    ];
    $restore = '-- ' . BS_ATTR_TOOL_NAME . ' rollback; key = (product_id, attribute_id, language_id)' . PHP_EOL
        . 'START TRANSACTION;' . PHP_EOL;
    foreach ($changed as $item) {
        $before['affected_rows'][] = [
            'sku' => $item['sku'],
            'product_id' => $item['product_id'],
            'attribute' => $item['attribute'],
            'attribute_id' => $item['attribute_id'],
            'language_id' => BS_ATTR_LANGUAGE_ID,
            'previous_text' => $item['before'],
            'action_to_undo' => $item['action'],
        ];
        $where = 'product_id = ' . (int) $item['product_id']
            . ' AND attribute_id = ' . (int) $item['attribute_id']
            . ' AND language_id = ' . BS_ATTR_LANGUAGE_ID;
        if ($item['action'] === 'insert') {
            $restore .= 'DELETE FROM ' . bs_attr_identifier($productAttributeTable) . ' WHERE ' . $where . ';' . PHP_EOL;
            continue;
        }
        // Do not rely on a unique surrogate or a composite UNIQUE constraint:
        // restoring by the three stable business columns is portable across OC
        // schema revisions and removes exactly the row this tool changed.
        $restore .= 'DELETE FROM ' . bs_attr_identifier($productAttributeTable) . ' WHERE ' . $where . ';' . PHP_EOL;
        $restore .= 'INSERT INTO ' . bs_attr_identifier($productAttributeTable)
            . ' (product_id, attribute_id, language_id, text) VALUES ('
            . (int) $item['product_id'] . ', ' . (int) $item['attribute_id'] . ', ' . BS_ATTR_LANGUAGE_ID . ', '
            . bs_attr_sql_string($db, (string) $item['before']) . ');' . PHP_EOL;
    }
    $restore .= 'COMMIT;' . PHP_EOL;

    $json = json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    bs_attr_need($json !== false, 'backup_json_encode_failed');
    bs_attr_need(file_put_contents($directory . DIRECTORY_SEPARATOR . 'before.json', $json . PHP_EOL, LOCK_EX) !== false, 'backup_before_json_write_failed');
    bs_attr_need(file_put_contents($directory . DIRECTORY_SEPARATOR . 'restore.sql', $restore, LOCK_EX) !== false, 'backup_restore_sql_write_failed');
    return $directory;
}

/** @param array<int,array<string,mixed>> $changed @param array<string,string> $tables */
function bs_attr_apply(mysqli $db, array $tables, array $changed): string {
    $backup = bs_attr_create_backup($db, $tables['product_attribute'], $changed);
    $inTransaction = false;
    try {
        bs_attr_need($db->begin_transaction(), 'transaction_begin_failed:' . $db->error);
        $inTransaction = true;
        foreach ($changed as $item) {
            if ($item['action'] === 'insert') {
                $affected = bs_attr_execute(
                    $db,
                    'INSERT INTO ' . bs_attr_identifier($tables['product_attribute']) . ' (product_id, attribute_id, language_id, text) VALUES (?, ?, ?, ?)',
                    [$item['product_id'], $item['attribute_id'], BS_ATTR_LANGUAGE_ID, $item['value']]
                );
            } else {
                $affected = bs_attr_execute(
                    $db,
                    'UPDATE ' . bs_attr_identifier($tables['product_attribute']) . ' SET text = ? WHERE product_id = ? AND attribute_id = ? AND language_id = ?',
                    [$item['value'], $item['product_id'], $item['attribute_id'], BS_ATTR_LANGUAGE_ID]
                );
            }
            bs_attr_need($affected === 1, 'attribute_write_affected_rows_not_1:' . $item['sku'] . ':' . $item['attribute'] . ':' . $affected);

            $after = bs_attr_rows(
                $db,
                'SELECT text FROM ' . bs_attr_identifier($tables['product_attribute']) . ' WHERE product_id = ? AND attribute_id = ? AND language_id = ?',
                [$item['product_id'], $item['attribute_id'], BS_ATTR_LANGUAGE_ID]
            );
            bs_attr_need(count($after) === 1 && (string) $after[0]['text'] === (string) $item['value'], 'attribute_write_readback_failed:' . $item['sku'] . ':' . $item['attribute']);
        }

        // All SQL in this function is explicitly confined to product_attribute.
        $definitionsCreated = 0;
        $productsCreated = 0;
        $nonAttributeTablesWritten = 0;
        bs_attr_need($definitionsCreated === 0 && $productsCreated === 0 && $nonAttributeTablesWritten === 0, 'write_scope_assertion_failed');
        bs_attr_need($db->commit(), 'transaction_commit_failed:' . $db->error);
        $inTransaction = false;
        return $backup;
    } catch (Throwable $error) {
        if ($inTransaction) {
            $db->rollback();
        }
        throw $error;
    }
}

try {
    $command = bs_attr_parse_command();
    [$db, $prefix, $tables] = bs_attr_connect();
    bs_attr_assert_language($db, $tables);
    bs_attr_out('language_id', (string) BS_ATTR_LANGUAGE_ID);
    bs_attr_out('db_prefix', $prefix);

    if ($command['mode'] === 'report') {
        bs_attr_report($db, $tables);
        exit(0);
    }

    $csvRows = bs_attr_parse_csv((string) $command['csv']);
    $plan = bs_attr_build_plan($db, $tables, $csvRows);
    $counts = bs_attr_print_plan($plan, $command['mode']);
    $changed = array_values(array_filter($plan, function (array $item): bool {
        return $item['action'] === 'insert' || $item['action'] === 'update';
    }));

    if ($command['mode'] === 'apply' && $changed !== []) {
        $backup = bs_attr_apply($db, $tables, $changed);
        bs_attr_out('backup', $backup);
    }

    bs_attr_out('products_seen', (string) count(array_unique(array_map(function (array $item): string {
        return (string) $item['product_id'];
    }, $plan))));
    bs_attr_out('attributes_inserted', (string) $counts['inserted']);
    bs_attr_out('attributes_updated', (string) $counts['updated']);
    bs_attr_out('attributes_unchanged', (string) $counts['unchanged']);
    bs_attr_out('attributes_skipped_blank', (string) $counts['skipped']);
    if ($counts['inserted'] === 0 && $counts['updated'] === 0) {
        bs_attr_out('already_applied', 'yes');
    }
    bs_attr_out('definitions_created', '0');
    bs_attr_out('products_created', '0');
    bs_attr_out('non_attribute_tables_written', '0');
    bs_attr_out('done', 'ok');
    $db->close();
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR=' . $error->getMessage() . PHP_EOL);
    exit(1);
}
