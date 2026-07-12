<?php
declare(strict_types=1);

/**
 * PRODUCT-CATALOG-20260712B — add/update 8 OpenCart product cards and apply
 * the narrow EN Mega Evolution manufacturer/attribute follow-up.
 *
 * Scope:
 * - product
 * - product_description (language_id 4 only)
 * - product_to_category
 * - product_to_store
 * - product_attribute (language_id 4 only)
 * - manufacturer_id only for product_id 103, 104, 105, 106
 *
 * Explicit owner inputs applied:
 * - all target quantities are forced to 0;
 * - stock status is resolved by the exact name "Закінчився" for language_id 4;
 * - existing images are preserved on updates;
 * - new products receive an empty main image so the owner can add images later;
 * - physical weight/dimensions are cloned from the source product declared by each
 *   technical_template; no invented measurements are hard-coded.
 *
 * SEO:
 * - seo_url is intentionally not changed because the payload has no approved
 *   SEO keywords. Existing URLs remain untouched; new products can use the
 *   regular product_id URL until a separate SEO decision.
 *
 * DB warning:
 * This is an insert/update transaction against the OpenCart catalog tables.
 * The patch creates a pre-change JSON export and a concrete rollback.sql before
 * any write. It never deletes products outside the 8 target models/source IDs.
 */

const BS_PATCH_ID = 'CATALOG-20260712_products-accessories-B';
const BS_EXPECTED_PRODUCT_COUNT = 8;
const BS_LANGUAGE_ID = 4;
const BS_STORE_ID = 0;
const BS_STOCK_STATUS_NAME = 'Закінчився';

function bs_out(string $message): void
{
    echo '[catalog] ' . $message . PHP_EOL;
}

function bs_fail(string $message): void
{
    bs_out('error=' . $message);
    exit(1);
}

function bs_qi(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        bs_fail('unsafe_identifier=' . $name);
    }

    return chr(96) . $name . chr(96);
}

function bs_sql_value(mysqli $db, mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (is_int($value) || is_float($value)) {
        return (string)$value;
    }

    return "'" . $db->real_escape_string((string)$value) . "'";
}

function bs_query(mysqli $db, string $sql): mysqli_result|bool
{
    $result = $db->query($sql);
    if ($result === false) {
        bs_fail('sql_failed=' . $db->error);
    }

    return $result;
}

function bs_rows(mysqli $db, string $sql): array
{
    $result = bs_query($db, $sql);
    if (!$result instanceof mysqli_result) {
        return [];
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->free();

    return $rows;
}

function bs_one(mysqli $db, string $sql): ?array
{
    $rows = bs_rows($db, $sql);

    return $rows[0] ?? null;
}

function bs_scalar(mysqli $db, string $sql): mixed
{
    $row = bs_one($db, $sql);

    return $row === null ? null : reset($row);
}

function bs_table_exists(mysqli $db, string $table): bool
{
    $pattern = $db->real_escape_string($table);
    $rows = bs_rows($db, "SHOW TABLES LIKE '" . $pattern . "'");

    return count($rows) === 1;
}

function bs_columns(mysqli $db, string $table): array
{
    $rows = bs_rows($db, 'SHOW COLUMNS FROM ' . bs_qi($table));
    $columns = [];

    foreach ($rows as $row) {
        $columns[] = (string)$row['Field'];
    }

    return $columns;
}

function bs_require_columns(string $table, array $columns, array $required): void
{
    foreach ($required as $column) {
        if (!in_array($column, $columns, true)) {
            bs_fail('column_missing=' . $table . '.' . $column);
        }
    }
}

function bs_insert(mysqli $db, string $table, array $values, array $columns): int
{
    $fields = [];
    $data = [];

    foreach ($values as $field => $value) {
        if (in_array($field, $columns, true)) {
            $fields[] = bs_qi($field);
            $data[] = bs_sql_value($db, $value);
        }
    }

    if ($fields === []) {
        bs_fail('insert_has_no_columns=' . $table);
    }

    bs_query(
        $db,
        'INSERT INTO ' . bs_qi($table)
        . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $data) . ')'
    );

    return (int)$db->insert_id;
}

function bs_update(mysqli $db, string $table, array $values, array $columns, string $where): void
{
    $sets = [];

    foreach ($values as $field => $value) {
        if (in_array($field, $columns, true)) {
            $sets[] = bs_qi($field) . '=' . bs_sql_value($db, $value);
        }
    }

    if ($sets === []) {
        bs_fail('update_has_no_columns=' . $table);
    }

    bs_query($db, 'UPDATE ' . bs_qi($table) . ' SET ' . implode(',', $sets) . ' WHERE ' . $where);
}

function bs_delete(mysqli $db, string $table, string $where): void
{
    bs_query($db, 'DELETE FROM ' . bs_qi($table) . ' WHERE ' . $where);
}

function bs_product_by_id(mysqli $db, string $table, int $productId): ?array
{
    return bs_one(
        $db,
        'SELECT * FROM ' . bs_qi($table) . ' WHERE product_id=' . $productId . ' LIMIT 1'
    );
}

function bs_product_by_model(mysqli $db, string $table, string $model): ?array
{
    return bs_one(
        $db,
        'SELECT * FROM ' . bs_qi($table)
        . ' WHERE model=' . bs_sql_value($db, $model)
        . ' LIMIT 1'
    );
}

function bs_normalize_categories(mixed $categories): array
{
    if (!is_array($categories)) {
        return [];
    }

    if (array_key_exists('category_id', $categories)) {
        return [$categories];
    }

    return array_values($categories);
}

function bs_normalize_payload(array $payload, mysqli $db, string $categoryDescriptionTable, string $manufacturerTable, string $attributeDescriptionTable, array $products): array
{
    if (count($products) !== BS_EXPECTED_PRODUCT_COUNT) {
        bs_fail('payload_product_count=' . count($products) . ',expected=' . BS_EXPECTED_PRODUCT_COUNT);
    }

    $seenModels = [];
    $prepared = [];

    foreach ($products as $index => $product) {
        if (!is_array($product)) {
            bs_fail('payload_product_invalid=' . $index);
        }

        foreach (['operation', 'model', 'name', 'price', 'description_html', 'tag', 'meta_title', 'meta_description', 'meta_keyword', 'categories', 'attributes', 'manufacturer_id', 'manufacturer'] as $field) {
            if (!array_key_exists($field, $product)) {
                bs_fail('payload_field_missing=' . $index . '.' . $field);
            }
        }

        $model = trim((string)$product['model']);
        if ($model === '') {
            bs_fail('payload_model_empty=' . $index);
        }
        if (isset($seenModels[$model])) {
            bs_fail('payload_model_duplicate=' . $model);
        }
        $seenModels[$model] = true;

        if (!in_array((string)$product['operation'], ['create', 'update'], true)) {
            bs_fail('payload_operation_invalid=' . $model);
        }
        if (!is_numeric((string)$product['price'])) {
            bs_fail('payload_price_invalid=' . $model);
        }

        $description = (string)$product['description_html'];
        if ($description === '' || str_starts_with($description, '&lt;') || preg_match('/<script\b|<iframe\b|\son[a-z]+\s*=/i', $description)) {
            bs_fail('payload_description_invalid=' . $model);
        }

        $categories = bs_normalize_categories($product['categories']);
        if ($categories === []) {
            bs_fail('payload_categories_empty=' . $model);
        }

        foreach ($categories as $category) {
            $categoryId = (int)($category['category_id'] ?? 0);
            $categoryName = (string)($category['name'] ?? '');
            if ($categoryId < 1 || $categoryName === '') {
                bs_fail('payload_category_invalid=' . $model);
            }

            $categoryRow = bs_one(
                $db,
                'SELECT name FROM ' . bs_qi($categoryDescriptionTable)
                . ' WHERE category_id=' . $categoryId
                . ' AND language_id=' . BS_LANGUAGE_ID
                . ' LIMIT 2'
            );
            if ($categoryRow === null || (string)$categoryRow['name'] !== $categoryName) {
                bs_fail('category_mismatch=' . $model . ':' . $categoryId . ':' . $categoryName);
            }
        }

        $manufacturerId = (int)$product['manufacturer_id'];
        $manufacturerName = (string)$product['manufacturer'];
        $manufacturerRow = bs_one(
            $db,
            'SELECT name FROM ' . bs_qi($manufacturerTable)
            . ' WHERE manufacturer_id=' . $manufacturerId . ' LIMIT 1'
        );
        if ($manufacturerRow === null || (string)$manufacturerRow['name'] !== $manufacturerName) {
            bs_fail('manufacturer_mismatch=' . $model . ':' . $manufacturerId . ':' . $manufacturerName);
        }

        $attributes = $product['attributes'];
        if (!is_array($attributes) || $attributes === []) {
            bs_fail('payload_attributes_empty=' . $model);
        }

        foreach ($attributes as $attribute) {
            $attributeId = (int)($attribute['attribute_id'] ?? 0);
            $attributeName = (string)($attribute['name'] ?? '');
            $attributeText = (string)($attribute['text'] ?? '');
            if ($attributeId < 1 || $attributeName === '' || $attributeText === '') {
                bs_fail('payload_attribute_invalid=' . $model);
            }

            $attributeRow = bs_one(
                $db,
                'SELECT name FROM ' . bs_qi($attributeDescriptionTable)
                . ' WHERE attribute_id=' . $attributeId
                . ' AND language_id=' . BS_LANGUAGE_ID
                . ' LIMIT 2'
            );
            if ($attributeRow === null || (string)$attributeRow['name'] !== $attributeName) {
                bs_fail('attribute_mismatch=' . $model . ':' . $attributeId . ':' . $attributeName);
            }
        }

        $cloneId = 0;
        $template = $product['technical_template'] ?? [];
        if (is_array($template) && isset($template['clone_from_product_id'])) {
            $cloneId = (int)$template['clone_from_product_id'];
        }
        if ((string)$product['operation'] === 'update') {
            $cloneId = (int)($product['source_product_id'] ?? 0);
            if ($cloneId < 1 || (string)($product['old_model'] ?? '') === '') {
                bs_fail('update_source_anchor_missing=' . $model);
            }
        }
        if ($cloneId < 1) {
            bs_fail('clone_source_missing=' . $model);
        }

        $source = bs_product_by_id($db, DB_PREFIX . 'product', $cloneId);
        if ($source === null) {
            bs_fail('clone_source_not_found=' . $model . ':' . $cloneId);
        }

        $expectedSourceModel = '';
        if ((string)$product['operation'] === 'update') {
            $expectedSourceModel = (string)$product['old_model'];
        } elseif (is_array($template)) {
            $expectedSourceModel = (string)($template['clone_from_model'] ?? '');
        }
        if ($expectedSourceModel !== '' && (string)$source['model'] !== $expectedSourceModel && (string)$source['model'] !== $model) {
            bs_fail('clone_source_model_mismatch=' . $model . ':' . $source['model'] . ',expected=' . $expectedSourceModel);
        }

        $product['_categories'] = $categories;
        $product['_clone_id'] = $cloneId;
        $product['_quantity'] = 0;
        $product['_stock_status_id'] = 0;
        $product['_status'] = 1;
        $product['_image'] = '';
        $prepared[] = $product;
    }

    return $prepared;
}

function bs_compare_product(mysqli $db, string $productTable, string $descriptionTable, string $categoryTable, string $storeTable, string $attributeTable, array $product, int $productId, int $stockStatusId): bool
{
    $row = bs_product_by_id($db, $productTable, $productId);
    if ($row === null) {
        return false;
    }

    if ((string)$row['model'] !== (string)$product['model']
        || abs((float)$row['price'] - (float)$product['price']) > 0.0001
        || (int)$row['quantity'] !== 0
        || (int)$row['stock_status_id'] !== $stockStatusId
        || (int)$row['status'] !== 1
        || (int)$row['manufacturer_id'] !== (int)$product['manufacturer_id']) {
        return false;
    }

    $descriptionRows = bs_rows(
        $db,
        'SELECT name,description,tag,meta_title,meta_description,meta_keyword FROM '
        . bs_qi($descriptionTable)
        . ' WHERE product_id=' . $productId . ' AND language_id=' . BS_LANGUAGE_ID
    );
    if (count($descriptionRows) !== 1) {
        return false;
    }
    $description = $descriptionRows[0];
    foreach (['name', 'description', 'tag', 'meta_title', 'meta_description', 'meta_keyword'] as $field) {
        if ((string)$description[$field] !== (string)($product[$field === 'description' ? 'description_html' : $field] ?? '')) {
            return false;
        }
    }

    $wantedCategories = [];
    foreach ($product['_categories'] as $category) {
        $wantedCategories[] = (int)$category['category_id'];
    }
    sort($wantedCategories);

    $currentCategories = [];
    foreach (bs_rows($db, 'SELECT category_id FROM ' . bs_qi($categoryTable) . ' WHERE product_id=' . $productId) as $category) {
        $currentCategories[] = (int)$category['category_id'];
    }
    sort($currentCategories);
    if ($wantedCategories !== $currentCategories) {
        return false;
    }

    $storeRows = bs_rows(
        $db,
        'SELECT store_id FROM ' . bs_qi($storeTable)
        . ' WHERE product_id=' . $productId . ' AND store_id=' . BS_STORE_ID
    );
    if (count($storeRows) !== 1) {
        return false;
    }

    $wantedAttributes = [];
    foreach ($product['attributes'] as $attribute) {
        $wantedAttributes[(int)$attribute['attribute_id']] = (string)$attribute['text'];
    }
    ksort($wantedAttributes);

    $currentAttributes = [];
    foreach (bs_rows($db, 'SELECT attribute_id,text FROM ' . bs_qi($attributeTable) . ' WHERE product_id=' . $productId . ' AND language_id=' . BS_LANGUAGE_ID) as $attribute) {
        $currentAttributes[(int)$attribute['attribute_id']] = (string)$attribute['text'];
    }
    ksort($currentAttributes);

    return $wantedAttributes === $currentAttributes;
}

function bs_attribute_map(array $rows): array
{
    $map = [];
    foreach ($rows as $row) {
        $attributeId = (int)($row['attribute_id'] ?? 0);
        if ($attributeId < 1 || isset($map[$attributeId])) {
            return ['__invalid__' => '1'];
        }
        $map[$attributeId] = (string)($row['text'] ?? '');
    }
    ksort($map);

    return $map;
}

function bs_attribute_updates_payload(): array
{
    $common = [
        ['attribute_id' => 12, 'name' => 'Мова', 'text' => 'Англійська (English)'],
        ['attribute_id' => 14, 'name' => 'Рік випуску', 'text' => '2026'],
        ['attribute_id' => 15, 'name' => 'Кількість карток у бустері', 'text' => '10'],
        ['attribute_id' => 17, 'name' => 'Стан', 'text' => 'Новий, нерозпакований (Sealed)'],
        ['attribute_id' => 18, 'name' => 'Походження товару', 'text' => 'Sealed-партія, без розсипу'],
        ['attribute_id' => 19, 'name' => 'Зважування', 'text' => 'Без зважування, сортування чи ручного перевідбору'],
        ['attribute_id' => 20, 'name' => 'Виробник', 'text' => 'The Pokémon Company'],
    ];

    return [
        [
            'product_id' => 103,
            'model' => 'PKM-EN-PORD-BBN',
            'manufacturer_id' => 11,
            'manufacturer' => 'The Pokémon Company',
            'attributes' => array_merge($common, [
                ['attribute_id' => 13, 'name' => 'Назва сету', 'text' => 'Perfect Order'],
                ['attribute_id' => 16, 'name' => 'Кількість бустерів у боксі', 'text' => '6'],
                ['attribute_id' => 21, 'name' => 'Тип пакування', 'text' => 'Sealed Booster Bundle'],
                ['attribute_id' => 24, 'name' => 'Додатковий вміст', 'text' => 'У кожному з 6 бустерів: 1 Basic Energy + 1 Pokémon TCG Live code card'],
            ]),
        ],
        [
            'product_id' => 104,
            'model' => 'PKM-EN-PORD-BST',
            'manufacturer_id' => 11,
            'manufacturer' => 'The Pokémon Company',
            'attributes' => array_merge($common, [
                ['attribute_id' => 13, 'name' => 'Назва сету', 'text' => 'Perfect Order'],
                ['attribute_id' => 21, 'name' => 'Тип пакування', 'text' => 'Sealed Booster Pack'],
                ['attribute_id' => 24, 'name' => 'Додатковий вміст', 'text' => '1 Basic Energy + 1 Pokémon TCG Live code card'],
            ]),
        ],
        [
            'product_id' => 105,
            'model' => 'PKM-EN-CHRS-BBN',
            'manufacturer_id' => 11,
            'manufacturer' => 'The Pokémon Company',
            'attributes' => array_merge($common, [
                ['attribute_id' => 13, 'name' => 'Назва сету', 'text' => 'Chaos Rising'],
                ['attribute_id' => 16, 'name' => 'Кількість бустерів у боксі', 'text' => '6'],
                ['attribute_id' => 21, 'name' => 'Тип пакування', 'text' => 'Sealed Booster Bundle'],
                ['attribute_id' => 24, 'name' => 'Додатковий вміст', 'text' => 'У кожному з 6 бустерів: 1 Basic Energy + 1 Pokémon TCG Live code card'],
            ]),
        ],
        [
            'product_id' => 106,
            'model' => 'PKM-EN-CHRS-BST',
            'manufacturer_id' => 11,
            'manufacturer' => 'The Pokémon Company',
            'attributes' => array_merge($common, [
                ['attribute_id' => 13, 'name' => 'Назва сету', 'text' => 'Chaos Rising'],
                ['attribute_id' => 21, 'name' => 'Тип пакування', 'text' => 'Sealed Booster Pack'],
                ['attribute_id' => 24, 'name' => 'Додатковий вміст', 'text' => '1 Basic Energy + 1 Pokémon TCG Live code card'],
            ]),
        ],
    ];
}

function bs_prepare_attribute_updates(mysqli $db, string $productTable, string $manufacturerTable, string $attributeDescriptionTable, string $attributeTable): array
{
    $updates = bs_attribute_updates_payload();
    if (count($updates) !== 4) {
        bs_fail('attribute_update_count_invalid=' . count($updates));
    }

    $manufacturerRows = bs_rows(
        $db,
        'SELECT name FROM ' . bs_qi($manufacturerTable) . ' WHERE manufacturer_id=11 LIMIT 2'
    );
    if (count($manufacturerRows) !== 1 || (string)$manufacturerRows[0]['name'] !== 'The Pokémon Company') {
        bs_fail('attribute_update_manufacturer_mismatch');
    }

    $seenIds = [];
    foreach ($updates as $update) {
        $productId = (int)$update['product_id'];
        if (isset($seenIds[$productId])) {
            bs_fail('attribute_update_product_duplicate=' . $productId);
        }
        $seenIds[$productId] = true;

        $product = bs_product_by_id($db, $productTable, $productId);
        if ($product === null || (string)$product['model'] !== (string)$update['model']) {
            bs_fail('attribute_update_product_anchor_mismatch=' . $productId);
        }

        $seenAttributes = [];
        foreach ($update['attributes'] as $attribute) {
            $attributeId = (int)$attribute['attribute_id'];
            if (isset($seenAttributes[$attributeId])) {
                bs_fail('attribute_update_attribute_duplicate=' . $update['model'] . ':' . $attributeId);
            }
            $seenAttributes[$attributeId] = true;

            $attributeRows = bs_rows(
                $db,
                'SELECT name FROM ' . bs_qi($attributeDescriptionTable)
                . ' WHERE attribute_id=' . $attributeId
                . ' AND language_id=' . BS_LANGUAGE_ID
                . ' LIMIT 2'
            );
            if (count($attributeRows) !== 1 || (string)$attributeRows[0]['name'] !== (string)$attribute['name']) {
                bs_fail('attribute_update_attribute_name_mismatch=' . $update['model'] . ':' . $attributeId);
            }
        }

        $currentAttributes = bs_attribute_map(
            bs_rows(
                $db,
                'SELECT attribute_id,text FROM ' . bs_qi($attributeTable)
                . ' WHERE product_id=' . $productId . ' AND language_id=' . BS_LANGUAGE_ID
            )
        );
        if (isset($currentAttributes['__invalid__'])) {
            bs_fail('attribute_update_current_rows_invalid=' . $update['model']);
        }
        $wantedAttributes = bs_attribute_map($update['attributes']);
        if ($currentAttributes !== [] && $currentAttributes !== $wantedAttributes) {
            bs_fail('attribute_update_existing_rows_unexpected=' . $update['model']);
        }
    }

    return $updates;
}

function bs_attribute_update_is_applied(mysqli $db, string $productTable, string $attributeTable, array $update): bool
{
    $product = bs_product_by_id($db, $productTable, (int)$update['product_id']);
    if ($product === null
        || (string)$product['model'] !== (string)$update['model']
        || (int)$product['manufacturer_id'] !== (int)$update['manufacturer_id']) {
        return false;
    }

    $wanted = bs_attribute_map($update['attributes']);
    $current = bs_attribute_map(
        bs_rows(
            $db,
            'SELECT attribute_id,text FROM ' . bs_qi($attributeTable)
            . ' WHERE product_id=' . (int)$update['product_id'] . ' AND language_id=' . BS_LANGUAGE_ID
        )
    );

    return $wanted === $current;
}

function bs_apply_attribute_update(mysqli $db, string $productTable, string $attributeTable, array $productColumns, array $attributeColumns, array $update): void
{
    $productId = (int)$update['product_id'];
    bs_update(
        $db,
        $productTable,
        ['manufacturer_id' => (int)$update['manufacturer_id']],
        $productColumns,
        'product_id=' . $productId
    );

    bs_delete($db, $attributeTable, 'product_id=' . $productId . ' AND language_id=' . BS_LANGUAGE_ID);
    foreach ($update['attributes'] as $attribute) {
        bs_insert(
            $db,
            $attributeTable,
            [
                'product_id' => $productId,
                'attribute_id' => (int)$attribute['attribute_id'],
                'language_id' => BS_LANGUAGE_ID,
                'text' => (string)$attribute['text'],
            ],
            $attributeColumns
        );
    }

    bs_out('attribute_update_applied=' . $update['model'] . ':' . $productId);
}

function bs_resolve_target_id(mysqli $db, string $productTable, array $product): ?int
{
    $target = bs_product_by_model($db, $productTable, (string)$product['model']);
    if ($target !== null) {
        if ((string)$product['operation'] === 'update' && (int)$target['product_id'] !== (int)$product['source_product_id']) {
            bs_fail('update_target_id_mismatch=' . $product['model']);
        }

        return (int)$target['product_id'];
    }

    if ((string)$product['operation'] === 'update') {
        $source = bs_product_by_id($db, $productTable, (int)$product['source_product_id']);
        if ($source === null || (string)$source['model'] !== (string)$product['old_model']) {
            bs_fail('update_old_model_not_found=' . $product['model']);
        }

        return (int)$source['product_id'];
    }

    return null;
}

function bs_snapshot(mysqli $db, string $table, array $ids): array
{
    if ($ids === []) {
        return [];
    }

    return bs_rows(
        $db,
        'SELECT * FROM ' . bs_qi($table)
        . ' WHERE product_id IN (' . implode(',', array_map('intval', $ids)) . ')'
    );
}

function bs_sql_insert_row(mysqli $db, string $table, array $row): string
{
    if ($row === []) {
        return '';
    }

    $fields = [];
    $values = [];
    foreach ($row as $field => $value) {
        $fields[] = bs_qi((string)$field);
        $values[] = bs_sql_value($db, $value);
    }

    return 'INSERT INTO ' . bs_qi($table)
        . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ');';
}

function bs_build_rollback(mysqli $db, array $snapshot, string $prefix, array $targetModels): string
{
    $sql = "-- Generated by " . BS_PATCH_ID . PHP_EOL
        . "-- Restore from the pre-change snapshot captured by the patch." . PHP_EOL
        . "START TRANSACTION;" . PHP_EOL;

    $snapshotIds = [];
    foreach ($snapshot['product'] ?? [] as $row) {
        $snapshotIds[] = (int)$row['product_id'];
    }
    $idList = $snapshotIds === [] ? '0' : implode(',', $snapshotIds);
    $modelValues = [];
    foreach ($targetModels as $model) {
        $modelValues[] = bs_sql_value($db, $model);
    }
    $modelList = $modelValues === [] ? "''" : implode(',', $modelValues);

    foreach (['product_description', 'product_to_category', 'product_to_store', 'product_attribute'] as $suffix) {
        $table = $prefix . $suffix;
        $sql .= 'DELETE FROM ' . bs_qi($table) . ' WHERE product_id IN (' . $idList . ');' . PHP_EOL;
    }
    $sql .= 'DELETE FROM ' . bs_qi($prefix . 'product') . ' WHERE product_id IN (' . $idList . ');' . PHP_EOL;

    foreach (['product_to_category', 'product_to_store', 'product_attribute', 'product_description'] as $suffix) {
        $table = $prefix . $suffix;
        $sql .= 'DELETE t FROM ' . bs_qi($table) . ' t JOIN ' . bs_qi($prefix . 'product') . ' p ON p.product_id=t.product_id WHERE p.model IN (' . $modelList . ') AND p.product_id NOT IN (' . $idList . ');' . PHP_EOL;
    }
    $sql .= 'DELETE FROM ' . bs_qi($prefix . 'product') . ' WHERE model IN (' . $modelList . ') AND product_id NOT IN (' . $idList . ');' . PHP_EOL;

    foreach (['product', 'product_description', 'product_to_category', 'product_to_store', 'product_attribute'] as $suffix) {
        foreach ($snapshot[$suffix] ?? [] as $row) {
            $sql .= bs_sql_insert_row($db, $prefix . $suffix, $row) . PHP_EOL;
        }
    }

    $sql .= "COMMIT;" . PHP_EOL;

    return $sql;
}

function bs_upsert_description(mysqli $db, string $table, array $columns, int $productId, array $product): void
{
    $values = [
        'product_id' => $productId,
        'language_id' => BS_LANGUAGE_ID,
        'name' => (string)$product['name'],
        'description' => (string)$product['description_html'],
        'tag' => (string)$product['tag'],
        'meta_title' => (string)$product['meta_title'],
        'meta_description' => (string)$product['meta_description'],
        'meta_keyword' => (string)$product['meta_keyword'],
    ];
    $count = (int)bs_scalar(
        $db,
        'SELECT COUNT(*) FROM ' . bs_qi($table)
        . ' WHERE product_id=' . $productId . ' AND language_id=' . BS_LANGUAGE_ID
    );
    if ($count > 1) {
        bs_fail('duplicate_description_rows=' . $product['model']);
    }
    if ($count === 1) {
        bs_update($db, $table, $values, $columns, 'product_id=' . $productId . ' AND language_id=' . BS_LANGUAGE_ID);
    } else {
        bs_insert($db, $table, $values, $columns);
    }
}

function bs_replace_relations(mysqli $db, string $categoryTable, string $storeTable, string $attributeTable, array $columns, int $productId, array $product): void
{
    bs_delete($db, $categoryTable, 'product_id=' . $productId);
    foreach ($product['_categories'] as $category) {
        bs_insert(
            $db,
            $categoryTable,
            ['product_id' => $productId, 'category_id' => (int)$category['category_id']],
            $columns['category']
        );
    }

    $storeCount = (int)bs_scalar(
        $db,
        'SELECT COUNT(*) FROM ' . bs_qi($storeTable)
        . ' WHERE product_id=' . $productId . ' AND store_id=' . BS_STORE_ID
    );
    if ($storeCount > 1) {
        bs_fail('duplicate_store_relation=' . $product['model']);
    }
    if ($storeCount === 0) {
        bs_insert($db, $storeTable, ['product_id' => $productId, 'store_id' => BS_STORE_ID], $columns['store']);
    }

    bs_delete($db, $attributeTable, 'product_id=' . $productId . ' AND language_id=' . BS_LANGUAGE_ID);
    foreach ($product['attributes'] as $attribute) {
        bs_insert(
            $db,
            $attributeTable,
            [
                'product_id' => $productId,
                'attribute_id' => (int)$attribute['attribute_id'],
                'language_id' => BS_LANGUAGE_ID,
                'text' => (string)$attribute['text'],
            ],
            $columns['attribute']
        );
    }
}

function bs_lint_self(): void
{
    if (!function_exists('exec')) {
        bs_out('php_l=runtime_parse_ok_exec_unavailable');
        return;
    }

    $output = [];
    $code = 0;
    @exec(PHP_BINARY . ' -l ' . escapeshellarg(__FILE__), $output, $code);
    if ($code !== 0) {
        bs_fail('php_l_failed');
    }

    bs_out('php_l=ok');
}

$dryRun = in_array('--dry-run', $argv ?? [], true);
$root = getcwd();
bs_out('patch=' . BS_PATCH_ID);
bs_out('cwd=' . $root);
bs_out('time=' . date('c'));
bs_out('mode=' . ($dryRun ? 'dry-run' : 'apply'));

$config = $root . DIRECTORY_SEPARATOR . 'config.php';
if (!is_file($config)) {
    bs_fail('file_missing=config.php');
}
bs_out('assert=file_exists_config:ok');

$payloadB64 = <<<'PAYLOAD'
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
PAYLOAD;

$decoded = base64_decode(preg_replace('/\s+/', '', $payloadB64) ?: '', true);
if (!is_string($decoded)) {
    bs_fail('payload_base64_decode_failed');
}
$rawPayload = gzdecode($decoded);
if (!is_string($rawPayload)) {
    bs_fail('payload_gzip_decode_failed');
}
$payload = json_decode($rawPayload, true);
if (!is_array($payload)) {
    bs_fail('payload_json_decode_failed=' . json_last_error_msg());
}

require_once $config;
foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PREFIX'] as $constant) {
    if (!defined($constant)) {
        bs_fail('config_constant_missing=' . $constant);
    }
}

mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli(
    (string)DB_HOSTNAME,
    (string)DB_USERNAME,
    (string)DB_PASSWORD,
    (string)DB_DATABASE,
    defined('DB_PORT') ? (int)DB_PORT : 3306
);
if ($db->connect_error) {
    bs_fail('db_connect_failed=' . $db->connect_error);
}
if (!$db->set_charset('utf8mb4')) {
    bs_fail('db_charset_failed=' . $db->error);
}

$prefix = (string)DB_PREFIX;
if (!preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
    bs_fail('unsafe_db_prefix');
}

$tables = [
    'product' => $prefix . 'product',
    'description' => $prefix . 'product_description',
    'category' => $prefix . 'product_to_category',
    'store' => $prefix . 'product_to_store',
    'attribute' => $prefix . 'product_attribute',
    'category_description' => $prefix . 'category_description',
    'manufacturer' => $prefix . 'manufacturer',
    'attribute_description' => $prefix . 'attribute_description',
    'stock_status' => $prefix . 'stock_status',
    'language' => $prefix . 'language',
];

foreach ($tables as $key => $table) {
    if (!bs_table_exists($db, $table)) {
        bs_fail('db_table_missing=' . $table);
    }
    bs_out('assert=table_exists:' . $table);
}

$columns = [
    'product' => bs_columns($db, $tables['product']),
    'description' => bs_columns($db, $tables['description']),
    'category' => bs_columns($db, $tables['category']),
    'store' => bs_columns($db, $tables['store']),
    'attribute' => bs_columns($db, $tables['attribute']),
];

bs_require_columns($tables['product'], $columns['product'], ['product_id', 'model', 'price', 'quantity', 'stock_status_id', 'status', 'manufacturer_id', 'image']);
bs_require_columns($tables['description'], $columns['description'], ['product_id', 'language_id', 'name', 'description', 'tag', 'meta_title', 'meta_description', 'meta_keyword']);
bs_require_columns($tables['category'], $columns['category'], ['product_id', 'category_id']);
bs_require_columns($tables['store'], $columns['store'], ['product_id', 'store_id']);
bs_require_columns($tables['attribute'], $columns['attribute'], ['product_id', 'attribute_id', 'language_id', 'text']);
bs_require_columns($tables['language'], bs_columns($db, $tables['language']), ['language_id', 'name']);

$languageName = bs_scalar(
    $db,
    'SELECT name FROM ' . bs_qi($tables['language']) . ' WHERE language_id=' . BS_LANGUAGE_ID . ' LIMIT 1'
);
if ($languageName === null) {
    bs_fail('language_missing=' . BS_LANGUAGE_ID);
}
bs_out('language_id=' . BS_LANGUAGE_ID . ' name=' . $languageName);

$stockRows = bs_rows(
    $db,
    'SELECT stock_status_id FROM ' . bs_qi($tables['stock_status'])
    . ' WHERE language_id=' . BS_LANGUAGE_ID
    . ' AND name=' . bs_sql_value($db, BS_STOCK_STATUS_NAME)
);
if (count($stockRows) !== 1) {
    bs_fail('stock_status_resolution_failed=' . BS_STOCK_STATUS_NAME . ',count=' . count($stockRows));
}
$stockStatusId = (int)$stockRows[0]['stock_status_id'];
bs_out('stock_status=' . BS_STOCK_STATUS_NAME . ':' . $stockStatusId);

$products = bs_normalize_payload(
    $payload,
    $db,
    $tables['category_description'],
    $tables['manufacturer'],
    $tables['attribute_description'],
    $payload['products'] ?? []
);
$attributeUpdates = bs_prepare_attribute_updates(
    $db,
    $tables['product'],
    $tables['manufacturer'],
    $tables['attribute_description'],
    $tables['attribute']
);
bs_out('attribute_update_count=' . count($attributeUpdates));

foreach ($products as &$product) {
    $product['_stock_status_id'] = $stockStatusId;
}
unset($product);

$targetModels = array_map(static fn(array $product): string => (string)$product['model'], $products);
$resolvedIds = [];
foreach ($products as $product) {
    $id = bs_resolve_target_id($db, $tables['product'], $product);
    if ($id !== null) {
        $resolvedIds[(string)$product['model']] = $id;
    }
}
bs_out('target_models=' . implode(',', $targetModels));
bs_out('target_ids=' . ($resolvedIds === [] ? 'none' : json_encode($resolvedIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));

$allApplied = count($resolvedIds) === count($products);
if ($allApplied) {
    foreach ($products as $product) {
        if (!bs_compare_product($db, $tables['product'], $tables['description'], $tables['category'], $tables['store'], $tables['attribute'], $product, $resolvedIds[(string)$product['model']], $stockStatusId)) {
            $allApplied = false;
            break;
        }
    }
}

$attributesApplied = true;
foreach ($attributeUpdates as $attributeUpdate) {
    if (!bs_attribute_update_is_applied($db, $tables['product'], $tables['attribute'], $attributeUpdate)) {
        $attributesApplied = false;
        break;
    }
}

if ($allApplied && $attributesApplied) {
    bs_out('already_applied=yes');
    bs_out('done=ok');
    if (!$dryRun) {
        @unlink(__FILE__);
    }
    exit(0);
}

bs_lint_self();

if ($dryRun) {
    foreach ($products as $product) {
        $id = bs_resolve_target_id($db, $tables['product'], $product);
        if ($id === null || !bs_compare_product($db, $tables['product'], $tables['description'], $tables['category'], $tables['store'], $tables['attribute'], $product, $id, $stockStatusId)) {
            bs_out('would_' . ($id === null ? 'create' : 'update') . '=' . $product['model'] . ($id === null ? '' : ':' . $id));
        }
    }
    foreach ($attributeUpdates as $attributeUpdate) {
        if (!bs_attribute_update_is_applied($db, $tables['product'], $tables['attribute'], $attributeUpdate)) {
            bs_out('would_attribute_update=' . $attributeUpdate['model'] . ':' . $attributeUpdate['product_id']);
        }
    }
    bs_out('seo_url=preserved_or_skipped');
    bs_out('done=ok');
    exit(0);
}

$timestamp = date('Ymd-His');
$backupDir = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . BS_PATCH_ID . '-' . $timestamp;
if (!is_dir($backupDir) && !mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
    bs_fail('backup_dir_create_failed=' . $backupDir);
}

$affectedIds = [];
foreach ($products as $product) {
    $id = bs_resolve_target_id($db, $tables['product'], $product);
    if ($id !== null) {
        $affectedIds[] = $id;
    }
}
$attributeProductIds = array_map(
    static fn(array $update): int => (int)$update['product_id'],
    $attributeUpdates
);
$affectedIds = array_merge($affectedIds, $attributeProductIds);
$affectedIds = array_values(array_unique(array_map('intval', $affectedIds)));

$snapshot = [
    'captured_at' => date('c'),
    'product_ids' => $affectedIds,
    'product' => bs_snapshot($db, $tables['product'], $affectedIds),
    'product_description' => bs_snapshot($db, $tables['description'], $affectedIds),
    'product_to_category' => bs_snapshot($db, $tables['category'], $affectedIds),
    'product_to_store' => bs_snapshot($db, $tables['store'], $affectedIds),
    'product_attribute' => bs_snapshot($db, $tables['attribute'], $affectedIds),
];

if (file_put_contents($backupDir . DIRECTORY_SEPARATOR . 'db-prechange.json', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) === false) {
    bs_fail('backup_write_failed=db-prechange.json');
}
$backupPayload = json_encode(
    ['products' => $payload['products'] ?? [], 'attribute_updates' => $attributeUpdates],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
);
if (!is_string($backupPayload) || file_put_contents($backupDir . DIRECTORY_SEPARATOR . 'payload.json', $backupPayload) === false) {
    bs_fail('backup_write_failed=payload.json');
}
$rollbackSql = bs_build_rollback($db, $snapshot, $prefix, $targetModels);
if (file_put_contents($backupDir . DIRECTORY_SEPARATOR . 'rollback.sql', $rollbackSql) === false) {
    bs_fail('backup_write_failed=rollback.sql');
}
bs_out('backup_path=' . $backupDir);

if (!$db->begin_transaction()) {
    bs_fail('transaction_begin_failed');
}

try {
    foreach ($products as $product) {
        $model = (string)$product['model'];
        $target = bs_product_by_model($db, $tables['product'], $model);
        $isUpdate = (string)$product['operation'] === 'update';

        if ($target !== null) {
            $productId = (int)$target['product_id'];
            if ($isUpdate && $productId !== (int)$product['source_product_id']) {
                bs_fail('write_update_target_id_mismatch=' . $model);
            }
            bs_update(
                $db,
                $tables['product'],
                [
                    'model' => $model,
                    'price' => (string)$product['price'],
                    'quantity' => 0,
                    'stock_status_id' => $stockStatusId,
                    'status' => 1,
                    'manufacturer_id' => (int)$product['manufacturer_id'],
                ],
                $columns['product'],
                'product_id=' . $productId
            );
            bs_out('product_updated=' . $model . ':' . $productId);
        } elseif ($isUpdate) {
            $source = bs_product_by_id($db, $tables['product'], (int)$product['source_product_id']);
            if ($source === null || (string)$source['model'] !== (string)$product['old_model']) {
                bs_fail('write_update_source_missing=' . $model);
            }
            $productId = (int)$source['product_id'];
            bs_update(
                $db,
                $tables['product'],
                [
                    'model' => $model,
                    'price' => (string)$product['price'],
                    'quantity' => 0,
                    'stock_status_id' => $stockStatusId,
                    'status' => 1,
                    'manufacturer_id' => (int)$product['manufacturer_id'],
                ],
                $columns['product'],
                'product_id=' . $productId
            );
            bs_out('product_renamed_and_updated=' . $model . ':' . $productId);
        } else {
            $source = bs_product_by_id($db, $tables['product'], (int)$product['_clone_id']);
            if ($source === null) {
                bs_fail('write_clone_source_missing=' . $model);
            }
            $clone = $source;
            unset($clone['product_id']);
            $clone['model'] = $model;
            $clone['price'] = (string)$product['price'];
            $clone['quantity'] = 0;
            $clone['stock_status_id'] = $stockStatusId;
            $clone['status'] = 1;
            $clone['manufacturer_id'] = (int)$product['manufacturer_id'];
            $clone['image'] = '';
            if (array_key_exists('sku', $clone)) {
                $clone['sku'] = null;
            }
            if (array_key_exists('date_added', $clone)) {
                $clone['date_added'] = date('Y-m-d H:i:s');
            }
            if (array_key_exists('date_modified', $clone)) {
                $clone['date_modified'] = date('Y-m-d H:i:s');
            }
            $productId = bs_insert($db, $tables['product'], $clone, $columns['product']);
            if ($productId < 1) {
                bs_fail('product_insert_id_invalid=' . $model);
            }
            bs_out('product_created=' . $model . ':' . $productId . ':image_pending_owner');
        }

        bs_upsert_description($db, $tables['description'], $columns['description'], $productId, $product);
        bs_replace_relations(
            $db,
            $tables['category'],
            $tables['store'],
            $tables['attribute'],
            [
                'category' => $columns['category'],
                'store' => $columns['store'],
                'attribute' => $columns['attribute'],
            ],
            $productId,
            $product
        );
    }

    foreach ($attributeUpdates as $attributeUpdate) {
        bs_apply_attribute_update(
            $db,
            $tables['product'],
            $tables['attribute'],
            $columns['product'],
            $columns['attribute'],
            $attributeUpdate
        );
    }

    if (!$db->commit()) {
        bs_fail('transaction_commit_failed');
    }
} catch (Throwable $exception) {
    $db->rollback();
    bs_fail('transaction_rolled_back=' . $exception->getMessage());
}

bs_out('changed_tables=product,product_description,product_to_category,product_to_store,product_attribute,manufacturer_id');
bs_out('seo_url=preserved_or_skipped');
bs_out('done=ok');
$db->close();
@unlink(__FILE__);
