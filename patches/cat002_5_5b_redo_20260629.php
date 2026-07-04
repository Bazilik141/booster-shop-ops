<?php
declare(strict_types=1);

/**
 * CAT-002-5 + CAT-002-5b REDO
 *
 * Scope:
 * - catalog/view/template/common/home.twig
 * - catalog/view/template/common/header.twig
 * - catalog/view/stylesheet/boostershop-ds.css
 * - DB_PREFIX . attribute_group
 * - DB_PREFIX . attribute_group_description
 * - DB_PREFIX . attribute
 * - DB_PREFIX . attribute_description
 * - DB_PREFIX . product_attribute
 * - DB_PREFIX . product_description
 * - DB_PREFIX . category_description
 *
 * DB warning:
 * The owner explicitly approved the content DB changes in the Claude handoff.
 * The category and products already exist. This patch does not create categories,
 * products, manufacturers, or manufacturer assignments.
 *
 * Rollback:
 * - restore the three file backups;
 * - execute rollback.sql generated from exact pre-change DB rows.
 * Both are written before the first DB/file mutation.
 */

const CAT002_PATCH_ID = 'cat002_5_5b_redo_20260629';
const CAT002_HOME_MARKER = 'CAT-002-5 · Claude Design secondary tiles';
const CAT002_CSS_MARKER = 'CAT-002-5 · Claude Design secondary category tiles';
const CAT002_MENU_MARKER = 'CAT-002-5b · burger categories';
const CAT002_CACHE_VERSION = 'cat002-redo-20260629';

$args = $argv ?? [];
$dryRun = in_array('--dry-run', $args, true);
$skipDb = in_array('--skip-db', $args, true);
$root = rtrim((string)(getenv('BS_PATCH_ROOT') ?: (getcwd() ?: __DIR__)), "/\\");
$timestamp = date('Ymd-His');
$backupDir = $root . '/_patch_backups/' . CAT002_PATCH_ID . '-' . $timestamp;

$files = [
    'home' => 'catalog/view/template/common/home.twig',
    'header' => 'catalog/view/template/common/header.twig',
    'css' => 'catalog/view/stylesheet/boostershop-ds.css',
];

function cat002_out(string $message): void
{
    echo $message . PHP_EOL;
}

function cat002_fail(string $message): never
{
    throw new RuntimeException($message);
}

function cat002_count(string $haystack, string $needle): int
{
    return substr_count($haystack, $needle);
}

function cat002_normalize(string $content): string
{
    return str_replace(["\r\n", "\r"], "\n", $content);
}

function cat002_assert_count(string $content, string $needle, int $expected, string $label): void
{
    $actual = cat002_count($content, $needle);

    if ($actual !== $expected) {
        cat002_fail("anchor_count_{$label}={$actual},expected={$expected}");
    }
}

function cat002_replace_once(string $content, string $needle, string $replacement, string $label): string
{
    $needle = cat002_normalize($needle);
    $replacement = cat002_normalize($replacement);
    cat002_assert_count($content, $needle, 1, $label);

    return str_replace($needle, $replacement, $content);
}

function cat002_sql_value(mysqli $db, mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    if (is_int($value) || is_float($value)) {
        return (string)$value;
    }

    return "'" . $db->real_escape_string((string)$value) . "'";
}

function cat002_query(mysqli $db, string $sql): mysqli_result
{
    $result = $db->query($sql);

    if (!$result instanceof mysqli_result) {
        cat002_fail('db_query_failed=' . $db->error);
    }

    return $result;
}

function cat002_rows(mysqli $db, string $sql): array
{
    $result = cat002_query($db, $sql);
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $result->free();

    return $rows;
}

function cat002_scalar(mysqli $db, string $sql): int
{
    $result = cat002_query($db, $sql);
    $row = $result->fetch_row();
    $result->free();

    return (int)($row[0] ?? 0);
}

function cat002_columns(mysqli $db, string $table): array
{
    $rows = cat002_rows($db, 'SHOW COLUMNS FROM `' . $table . '`');
    $columns = [];

    foreach ($rows as $row) {
        $columns[(string)$row['Field']] = true;
    }

    return $columns;
}

function cat002_insert(mysqli $db, string $table, array $data, array $availableColumns): void
{
    $filtered = [];

    foreach ($data as $column => $value) {
        if (isset($availableColumns[$column])) {
            $filtered[$column] = $value;
        }
    }

    if ($filtered === []) {
        cat002_fail('no_compatible_columns_for=' . $table);
    }

    $columns = array_map(
        static fn(string $column): string => '`' . $column . '`',
        array_keys($filtered)
    );
    $values = array_map(
        static fn(mixed $value): string => cat002_sql_value($db, $value),
        array_values($filtered)
    );
    $sql = 'INSERT INTO `' . $table . '` (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ')';

    if (!$db->query($sql)) {
        cat002_fail('db_insert_failed=' . $table . ':' . $db->error);
    }
}

function cat002_backup_file(string $root, string $backupDir, string $relative): void
{
    $source = $root . '/' . $relative;
    $target = $backupDir . '/' . $relative;

    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true) && !is_dir(dirname($target))) {
        cat002_fail('backup_dir_create_failed=' . dirname($target));
    }

    if (!copy($source, $target)) {
        cat002_fail('backup_copy_failed=' . $relative);
    }

    cat002_out('backup=' . str_replace($root . '/', '', $target));
}

function cat002_write_file(string $path, string $content): void
{
    $temporary = $path . '.cat002.tmp.' . getmypid();

    if (file_put_contents($temporary, $content, LOCK_EX) === false) {
        cat002_fail('temporary_write_failed=' . $path);
    }

    if (!rename($temporary, $path)) {
        @unlink($temporary);
        cat002_fail('atomic_replace_failed=' . $path);
    }
}

function cat002_restore_files(string $root, string $backupDir, array $files): void
{
    foreach ($files as $relative) {
        $backup = $backupDir . '/' . $relative;
        $target = $root . '/' . $relative;

        if (is_file($backup)) {
            @copy($backup, $target);
        }
    }
}

set_exception_handler(static function (Throwable $error): void {
    cat002_out('error=' . $error->getMessage());
    cat002_out('done=failed');
    exit(1);
});

function cat002_assert_file_state(string $home, string $header, string $css): void
{
    cat002_assert_count($home, CAT002_HOME_MARKER, 1, 'home_marker_final');
    cat002_assert_count($home, 'class="bs-subtile"', 2, 'home_subtiles_final');
    cat002_assert_count($home, 'href="/catalog/more-tcg"', 1, 'home_more_tcg_url_final');
    cat002_assert_count($home, 'href="/catalog/acsesuary"', 1, 'home_accessories_url_final');
    cat002_assert_count($home, 'class="bs-subtile__glyph" aria-hidden="true">', 2, 'home_glyph_wrappers_final');
    cat002_assert_count($home, '<svg width="20" height="20" viewBox="0 0 24 24"', 2, 'home_glyph_dimensions_final');

    cat002_assert_count($css, CAT002_CSS_MARKER, 1, 'css_marker_final');
    foreach ([
        '--bs-other-tcg:   #065F46;',
        '--bs-accessories: #0D9488;',
        '--bs-yugioh:      #7C3AED;',
        '--bs-mtg:         #B45309;',
    ] as $token) {
        cat002_assert_count($css, $token, 1, 'css_' . substr(sha1($token), 0, 8));
    }
    cat002_assert_count($css, '.bs-subtile {', 2, 'css_subtile_component_final');

    cat002_assert_count($header, CAT002_MENU_MARKER, 1, 'menu_marker_final');
    foreach ([
        '/catalog/pokemon',
        '/catalog/one-piece',
        '/catalog/more-tcg',
        '/catalog/bustery-pokemon',
        '/catalog/Pokemon-booster-box',
        '/catalog/pokemon-tcg-nabory',
        '/catalog/One-Piece-Boosters',
        '/catalog/more-tcg/Yu-Gi-Oh',
        '/catalog/more-tcg/magic-the-gathering',
        '/catalog/acsesuary',
    ] as $url) {
        if (!str_contains($header, 'href="' . $url . '"')) {
            cat002_fail('menu_url_missing=' . $url);
        }
    }
    foreach (['59', '60', '59_61', '59_62', '59_64', '60_63'] as $pathValue) {
        cat002_assert_count($header, 'href="index.php?route=product/category&path=' . $pathValue . '"', 0, 'menu_path_removed_' . $pathValue);
    }
    cat002_assert_count($header, 'index.php?route=product/special', 1, 'menu_special_preserved');
    cat002_assert_count(
        $header,
        'boostershop-ds.css?v=' . CAT002_CACHE_VERSION,
        1,
        'header_css_cache_version_final'
    );
}

function cat002_db_state(mysqli $db, string $prefix, array $activeLanguages): array
{
    $categoryTable = $prefix . 'category';
    $descriptionTable = $prefix . 'category_description';
    $storeTable = $prefix . 'category_to_store';
    $seoTable = $prefix . 'seo_url';
    $name = $db->real_escape_string('Аксесуари');

    $categoryIds = cat002_rows(
        $db,
        "SELECT DISTINCT c.`category_id`
         FROM `{$categoryTable}` c
         INNER JOIN `{$descriptionTable}` cd ON cd.`category_id`=c.`category_id`
         WHERE cd.`name`='{$name}'"
    );

    if (count($categoryIds) > 1) {
        cat002_fail('db_duplicate_accessories_categories=' . count($categoryIds));
    }

    $categoryId = isset($categoryIds[0]['category_id']) ? (int)$categoryIds[0]['category_id'] : 0;
    $seoKeywordCount = cat002_scalar(
        $db,
        "SELECT COUNT(*) FROM `{$seoTable}` WHERE `keyword`='accessories'"
    );

    if ($categoryId === 0 && $seoKeywordCount > 0) {
        cat002_fail('db_accessories_keyword_already_used_without_category');
    }

    if ($categoryId === 0) {
        return [
            'complete' => false,
            'category_id' => 0,
            'description_count' => 0,
            'store_count' => 0,
            'seo_count' => 0,
        ];
    }

    $languageIds = implode(',', array_map('intval', $activeLanguages));
    $descriptionCount = cat002_scalar(
        $db,
        "SELECT COUNT(*) FROM `{$descriptionTable}`
         WHERE `category_id`={$categoryId} AND `language_id` IN ({$languageIds}) AND `name`='{$name}'"
    );
    $storeCount = cat002_scalar(
        $db,
        "SELECT COUNT(*) FROM `{$storeTable}` WHERE `category_id`={$categoryId} AND `store_id`=0"
    );
    $seoCount = cat002_scalar(
        $db,
        "SELECT COUNT(*) FROM `{$seoTable}`
         WHERE `store_id`=0 AND `language_id` IN ({$languageIds})
           AND `key`='path' AND `value`='{$categoryId}' AND `keyword`='accessories'"
    );
    $complete = $descriptionCount === count($activeLanguages)
        && $storeCount === 1
        && $seoCount === count($activeLanguages)
        && $seoKeywordCount === count($activeLanguages);

    if (!$complete) {
        cat002_fail(
            "db_partial_accessories_state=id:{$categoryId},descriptions:{$descriptionCount},store:{$storeCount},seo:{$seoCount}"
        );
    }

    return [
        'complete' => true,
        'category_id' => $categoryId,
        'description_count' => $descriptionCount,
        'store_count' => $storeCount,
        'seo_count' => $seoCount,
    ];
}

function cat002_assert_existing_categories(mysqli $db, string $prefix): array
{
    $categoryTable = $prefix . 'category';
    $descriptionTable = $prefix . 'category_description';
    $seoTable = $prefix . 'seo_url';
    $categoryNames = [
        'yugioh' => 'Yu-Gi-Oh!',
        'other_tcg' => 'Інші TCG',
        'mtg' => 'Magic: The Gathering',
    ];
    $categoryIds = [];

    foreach ($categoryNames as $key => $name) {
        $escaped = $db->real_escape_string($name);
        $rows = cat002_rows(
            $db,
            "SELECT DISTINCT `category_id` FROM `{$descriptionTable}` WHERE `name`='{$escaped}'"
        );
        if (count($rows) !== 1) {
            cat002_fail("db_category_name_not_unique={$name}:count=" . count($rows));
        }
        $categoryIds[$key] = (int)$rows[0]['category_id'];
    }

    $otherTcgId = $categoryIds['other_tcg'];
    foreach (['yugioh', 'mtg'] as $childKey) {
        $childId = $categoryIds[$childKey];
        $parentCount = cat002_scalar(
            $db,
            "SELECT COUNT(*) FROM `{$categoryTable}`
             WHERE `category_id`={$childId} AND `parent_id`={$otherTcgId} AND `status`=1"
        );
        if ($parentCount !== 1) {
            cat002_fail("db_category_parent_mismatch={$childKey}:id={$childId}:expected_parent={$otherTcgId}");
        }
    }

    $expectedSeo = [
        '59' => 'pokemon',
        '60' => 'one-piece',
        (string)$otherTcgId => 'more-tcg',
        $otherTcgId . '_' . $categoryIds['yugioh'] => 'more-tcg/yu-gi-oh',
        $otherTcgId . '_' . $categoryIds['mtg'] => 'more-tcg/magic-the-gathering',
    ];

    foreach ($expectedSeo as $value => $keyword) {
        $escapedValue = $db->real_escape_string((string)$value);
        $escapedKeyword = $db->real_escape_string((string)$keyword);
        $count = cat002_scalar(
            $db,
            "SELECT COUNT(*) FROM `{$seoTable}`
             WHERE `key`='path' AND `value`='{$escapedValue}'
               AND LOWER(`keyword`)=LOWER('{$escapedKeyword}')"
        );
        if ($count < 1) {
            cat002_fail("db_seo_anchor_missing={$value}:{$keyword}");
        }
    }

    return $categoryIds;
}

function cat002_redo_payload(): array
{
    $encoded = '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';
    $compressed = base64_decode($encoded, true);
    if (!is_string($compressed)) {
        cat002_fail('embedded_payload_base64_invalid');
    }
    $json = gzdecode($compressed);
    if (!is_string($json)
        || hash('sha256', $json) !== 'e9e4da77d9cdeca44e34eeb7a3d8b76dde28ca92662f77453c6518f426fff60c') {
        cat002_fail('embedded_payload_integrity_failed');
    }
    $payload = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($payload) || count($payload['products'] ?? []) !== 7 || !isset($payload['category'])) {
        cat002_fail('embedded_payload_shape_invalid');
    }

    return $payload;
}

function cat002_redo_attributes(): array
{
    return [
        'Тип товару' => 1,
        'Кількість в упаковці' => 2,
        'Матеріал' => 3,
        'Розмір / Формат' => 4,
        'Товщина матеріалу' => 5,
        'Без ПВХ' => 6,
        'Сумісність з картками' => 7,
        'Кількість кишеньок' => 8,
        'Отворів для кріплення' => 9,
    ];
}

function cat002_redo_assignments(): array
{
    return [
        95 => [
            'Тип товару' => 'Протектор',
            'Кількість в упаковці' => '100 шт',
            'Матеріал' => 'PP (поліпропілен)',
            'Розмір / Формат' => '63×89 мм',
            'Товщина матеріалу' => '60–80 мкм',
            'Без ПВХ' => 'Так',
            'Сумісність з картками' => 'Pokemon, MTG, Lorcana, One Piece TCG та інші Standard-size TCG',
        ],
        96 => [
            'Тип товару' => 'Протектор',
            'Кількість в упаковці' => '50 шт',
            'Матеріал' => 'PP (поліпропілен)',
            'Розмір / Формат' => '63.5×88 мм',
            'Товщина матеріалу' => '110 мкм',
            'Без ПВХ' => 'Так',
            'Сумісність з картками' => 'Pokemon, MTG, Lorcana та інші Standard-size TCG',
        ],
        97 => [
            'Тип товару' => 'Топлоадер',
            'Кількість в упаковці' => '25 шт',
            'Матеріал' => 'ПЕТ (жорсткий прозорий пластик)',
            'Розмір / Формат' => '35PT (~66×92 мм внутрішній)',
            'Сумісність з картками' => 'Pokemon, MTG, Lorcana, Yu-Gi-Oh! та інші Standard-size TCG',
        ],
        98 => [
            'Тип товару' => 'Магнітний кейс',
            'Кількість в упаковці' => '1 шт',
            'Матеріал' => 'Акриловий пластик',
            'Розмір / Формат' => '35PT',
            'Сумісність з картками' => 'Standard TCG-картки (~66×92 мм)',
        ],
        99 => [
            'Тип товару' => 'Підставка для кейсу',
            'Кількість в упаковці' => '1 шт',
            'Матеріал' => 'Акриловий пластик',
            'Сумісність з картками' => 'Стандартні магнітні кейси 35PT–100PT',
        ],
        100 => [
            'Тип товару' => 'Аркуш-файл',
            'Кількість в упаковці' => '1 шт',
            'Матеріал' => 'PP 100 мкм',
            'Розмір / Формат' => 'Кишенька 68×94 мм',
            'Товщина матеріалу' => '100 мкм',
            'Без ПВХ' => 'Так',
            'Сумісність з картками' => 'Pokemon, MTG, Lorcana та інші Standard-size TCG',
            'Кількість кишеньок' => '9 (3×3)',
            'Отворів для кріплення' => '11',
        ],
    ];
}

function cat002_redo_assert_entities(mysqli $db, string $prefix): void
{
    $models = [
        95 => 'ACC-001',
        96 => 'ACC-002',
        97 => 'ACC-003',
        98 => 'ACC-004',
        99 => 'ACC-005',
        100 => 'ACC-006',
        101 => 'YGO-JP-BODE-BST',
    ];
    foreach ($models as $productId => $model) {
        $escaped = $db->real_escape_string($model);
        $count = cat002_scalar(
            $db,
            "SELECT COUNT(*) FROM `{$prefix}product`
             WHERE `product_id`={$productId} AND BINARY `model`=BINARY '{$escaped}'"
        );
        if ($count !== 1) {
            cat002_fail("db_product_identity_mismatch={$productId}:{$model}");
        }
    }

    if (cat002_scalar(
        $db,
        "SELECT COUNT(*) FROM `{$prefix}category_description`
         WHERE `category_id`=70 AND BINARY `name`=BINARY 'Аксесуари'"
    ) < 1) {
        cat002_fail('db_category_70_accessories_missing');
    }

    $seo = [
        ['path', '59', 'Pokemon'],
        ['path', '60', 'One-Piece'],
        ['path', '59_61', 'Pokemon/bustery-pokemon'],
        ['path', '59_62', 'Pokemon/Pokemon-booster-box'],
        ['path', '59_64', 'Pokemon/pokemon-tcg-nabory'],
        ['path', '60_63', 'One-Piece/One-Piece-Boosters'],
        ['path', '66', 'more-tcg'],
        ['path', '66_65', 'more-tcg/Yu-Gi-Oh'],
        ['path', '66_67', 'more-tcg/magic-the-gathering'],
        ['path', '70', 'acsesuary'],
    ];
    foreach ($seo as [$key, $value, $keyword]) {
        $k = $db->real_escape_string($key);
        $v = $db->real_escape_string($value);
        $w = $db->real_escape_string($keyword);
        if (cat002_scalar(
            $db,
            "SELECT COUNT(*) FROM `{$prefix}seo_url`
             WHERE BINARY `key`=BINARY '{$k}' AND BINARY `value`=BINARY '{$v}'
               AND BINARY `keyword`=BINARY '{$w}'"
        ) < 1) {
            cat002_fail("db_seo_anchor_missing={$value}:{$keyword}");
        }
    }
}

function cat002_redo_find_group(mysqli $db, string $prefix): int
{
    $rows = cat002_rows(
        $db,
        "SELECT DISTINCT `attribute_group_id`
         FROM `{$prefix}attribute_group_description`
         WHERE BINARY `name`=BINARY 'Характеристики аксесуарів'"
    );
    if (count($rows) > 1) {
        cat002_fail('db_duplicate_accessory_attribute_groups=' . count($rows));
    }

    return isset($rows[0]['attribute_group_id']) ? (int)$rows[0]['attribute_group_id'] : 0;
}

function cat002_redo_attribute_ids(mysqli $db, string $prefix, int $groupId): array
{
    if ($groupId < 1) {
        return [];
    }
    $ids = [];
    foreach (cat002_redo_attributes() as $name => $sortOrder) {
        $escaped = $db->real_escape_string($name);
        $rows = cat002_rows(
            $db,
            "SELECT DISTINCT a.`attribute_id`
             FROM `{$prefix}attribute` a
             INNER JOIN `{$prefix}attribute_description` ad
               ON ad.`attribute_id`=a.`attribute_id`
             WHERE a.`attribute_group_id`={$groupId}
               AND BINARY ad.`name`=BINARY '{$escaped}'"
        );
        if (count($rows) > 1) {
            cat002_fail('db_duplicate_attribute=' . $name);
        }
        if (isset($rows[0]['attribute_id'])) {
            $ids[$name] = (int)$rows[0]['attribute_id'];
        }
    }

    return $ids;
}

function cat002_redo_db_state(
    mysqli $db,
    string $prefix,
    array $languages,
    array $payload
): array {
    $groupId = cat002_redo_find_group($db, $prefix);
    $attributeIds = cat002_redo_attribute_ids($db, $prefix, $groupId);
    $complete = $groupId > 0 && count($attributeIds) === count(cat002_redo_attributes());

    if ($complete) {
        foreach ($languages as $languageId) {
            if (cat002_scalar(
                $db,
                "SELECT COUNT(*) FROM `{$prefix}attribute_group_description`
                 WHERE `attribute_group_id`={$groupId} AND `language_id`={$languageId}
                   AND BINARY `name`=BINARY 'Характеристики аксесуарів'"
            ) !== 1) {
                $complete = false;
            }
            foreach ($attributeIds as $name => $attributeId) {
                $escaped = $db->real_escape_string($name);
                if (cat002_scalar(
                    $db,
                    "SELECT COUNT(*) FROM `{$prefix}attribute_description`
                     WHERE `attribute_id`={$attributeId} AND `language_id`={$languageId}
                       AND BINARY `name`=BINARY '{$escaped}'"
                ) !== 1) {
                    $complete = false;
                }
            }
        }
    }

    if ($complete) {
        foreach (cat002_redo_assignments() as $productId => $values) {
            foreach ($values as $name => $text) {
                $attributeId = $attributeIds[$name] ?? 0;
                $escaped = $db->real_escape_string($text);
                foreach ($languages as $languageId) {
                    if (cat002_scalar(
                        $db,
                        "SELECT COUNT(*) FROM `{$prefix}product_attribute`
                         WHERE `product_id`={$productId} AND `attribute_id`={$attributeId}
                           AND `language_id`={$languageId}
                           AND BINARY COALESCE(`text`,'')=BINARY '{$escaped}'"
                    ) !== 1) {
                        $complete = false;
                    }
                }
            }
        }
    }

    foreach ($payload['products'] as $productId => $description) {
        $escaped = $db->real_escape_string((string)$description);
        foreach ($languages as $languageId) {
            if (cat002_scalar(
                $db,
                "SELECT COUNT(*) FROM `{$prefix}product_description`
                 WHERE `product_id`=" . (int)$productId . " AND `language_id`={$languageId}
                   AND BINARY COALESCE(`description`,'')=BINARY '{$escaped}'"
            ) !== 1) {
                $complete = false;
            }
        }
    }
    $categoryDescription = $db->real_escape_string((string)$payload['category']);
    foreach ($languages as $languageId) {
        if (cat002_scalar(
            $db,
            "SELECT COUNT(*) FROM `{$prefix}category_description`
             WHERE `category_id`=70 AND `language_id`={$languageId}
               AND BINARY COALESCE(`description`,'')=BINARY '{$categoryDescription}'"
        ) !== 1) {
            $complete = false;
        }
    }

    return [
        'complete' => $complete,
        'group_id' => $groupId,
        'attribute_ids' => $attributeIds,
    ];
}

cat002_out('patch=' . CAT002_PATCH_ID);
cat002_out('cwd=' . $root);
cat002_out('time=' . date('c'));
cat002_out('dry_run=' . ($dryRun ? 'yes' : 'no'));
cat002_out('skip_db=' . ($skipDb ? 'yes' : 'no'));
cat002_out('warning_db_changes=owner_approved');
cat002_out('db_tables=attribute_group,attribute_group_description,attribute,attribute_description,product_attribute,product_description,category_description');

$lintOutput = [];
$lintCode = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $lintOutput, $lintCode);

if ($lintCode !== 0 || !str_contains(implode("\n", $lintOutput), 'No syntax errors detected')) {
    cat002_fail('php_lint_failed=' . implode(' | ', $lintOutput));
}
cat002_out('php_lint=ok');

$original = [];
foreach ($files as $key => $relative) {
    $path = $root . '/' . $relative;

    if (!is_file($path)) {
        cat002_fail('target_not_found=' . $relative);
    }
    if (!$dryRun && !is_writable($path)) {
        cat002_fail('target_not_writable=' . $relative);
    }

    $content = file_get_contents($path);
    if (!is_string($content)) {
        cat002_fail('target_read_failed=' . $relative);
    }

    $original[$key] = cat002_normalize($content);
    cat002_out('file_preflight=ok:' . $relative);
}

$homeApplied = cat002_count($original['home'], CAT002_HOME_MARKER) === 1;
$cssApplied = cat002_count($original['css'], CAT002_CSS_MARKER) === 1;
$menuApplied = cat002_count($original['header'], CAT002_MENU_MARKER) === 1;

if (cat002_count($original['home'], CAT002_HOME_MARKER) > 1
    || cat002_count($original['css'], CAT002_CSS_MARKER) > 1
    || cat002_count($original['header'], CAT002_MENU_MARKER) > 1) {
    cat002_fail('duplicate_patch_markers_detected');
}

if (!$homeApplied && (
    str_contains($original['home'], 'class="bs-subtile"')
    || str_contains($original['home'], 'href="/catalog/acsesuary"')
)) {
    cat002_fail('partial_home_state_without_marker');
}
if (!$cssApplied && (
    str_contains($original['css'], '--bs-other-tcg:')
    || str_contains($original['css'], '.bs-subtile {')
)) {
    cat002_fail('partial_css_state_without_marker');
}
if (!$menuApplied && (
    str_contains($original['header'], 'class="bs-menu__sub bs-menu__sub--accent"')
    || str_contains($original['header'], 'href="/catalog/acsesuary"')
)) {
    cat002_fail('partial_menu_state_without_marker');
}

$updated = $original;

if (!$homeApplied) {
    $homeAnchor = <<<'TWIG'
      </section>
      
      {{ content_top }}
TWIG;
    $homeReplacement = <<<'TWIG'
      </section>

      {# CAT-002-5 · Claude Design secondary tiles #}
      <section class="bs-home-tiles bs-subtiles" aria-label="Інші категорії">
        <a class="bs-subtile" href="/catalog/more-tcg" style="--accent: var(--bs-other-tcg);">
          <span class="bs-subtile__glyph" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="4" y="3" width="13" height="18" rx="2"/>
              <path d="M20 7v14a2 2 0 0 1-2 2H8"/>
            </svg>
          </span>
          <span class="bs-subtile__body">
            <span class="bs-subtile__title">Інші TCG</span>
            <span class="bs-subtile__hint">Yu-Gi-Oh! · Magic: The Gathering</span>
          </span>
          <svg class="bs-subtile__chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="9 6 15 12 9 18"/>
          </svg>
        </a>

        <a class="bs-subtile" href="/catalog/acsesuary" style="--accent: var(--bs-accessories);">
          <span class="bs-subtile__glyph" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="5" y="4" width="12" height="16" rx="1.5"/>
              <path d="M19 5.5l.7 1.6 1.8.3-1.4 1.2.4 1.8-1.5-.9-1.5.9.4-1.8L16.5 7.4l1.8-.3z" fill="currentColor" fill-opacity=".15"/>
            </svg>
          </span>
          <span class="bs-subtile__body">
            <span class="bs-subtile__title">Аксесуари</span>
            <span class="bs-subtile__hint">Sleeves, deck boxes, playmats, binders</span>
          </span>
          <svg class="bs-subtile__chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="9 6 15 12 9 18"/>
          </svg>
        </a>
      </section>
      
      {{ content_top }}
TWIG;
    $updated['home'] = cat002_replace_once(
        $updated['home'],
        $homeAnchor,
        $homeReplacement,
        'home_after_catcards'
    );
}

if (!$cssApplied) {
    foreach (['yugioh', 'mtg'] as $gameToken) {
        $pattern = '/^[ \t]*--bs-' . preg_quote($gameToken, '/') . ':\s*#[0-9A-Fa-f]{6};[ \t]*\n/m';
        $matchCount = preg_match_all($pattern, $updated['css']);
        if ($matchCount === false || $matchCount > 1) {
            cat002_fail("css_existing_{$gameToken}_token_count=" . ($matchCount === false ? 'regex_error' : $matchCount));
        }
        if ($matchCount === 1) {
            $cleanedCss = preg_replace($pattern, '', $updated['css'], 1, $replaceCount);
            if (!is_string($cleanedCss) || $replaceCount !== 1) {
                cat002_fail("css_existing_{$gameToken}_token_remove_failed");
            }
            $updated['css'] = $cleanedCss;
        }
    }

    $tokenAnchor = <<<'CSS'
  --bs-pokemon:    #C68A00;
  --bs-onepiece:   #1E40AF;
CSS;
    $tokenReplacement = <<<'CSS'
  --bs-pokemon:     #C68A00;
  --bs-onepiece:    #1E40AF;
  --bs-other-tcg:   #065F46;
  --bs-accessories: #0D9488;
  --bs-yugioh:      #7C3AED;
  --bs-mtg:         #B45309;
CSS;
    $updated['css'] = cat002_replace_once(
        $updated['css'],
        $tokenAnchor,
        $tokenReplacement,
        'css_category_tokens'
    );

    $cssEndAnchor = '/* ==== /RD-10D3 ==== */';
    $cssComponent = <<<'CSS'
/* ==== /RD-10D3 ==== */

/* ==== CAT-002-5 · Claude Design secondary category tiles ==== */
.bs-subtiles {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
  margin-top: 18px;
}
.bs-subtile {
  position: relative;
  display: flex;
  align-items: center;
  gap: 14px;
  height: 84px;
  padding: 0 18px 0 20px;
  background: #fff;
  border: 1px solid var(--bs-line);
  border-radius: var(--bs-r);
  color: var(--bs-ink);
  overflow: hidden;
  text-decoration: none;
  transition: border-color .15s, box-shadow .15s;
}
.bs-subtile:hover {
  border-color: color-mix(in oklab, var(--accent) 35%, var(--bs-line));
  box-shadow: var(--bs-sh-sm);
  text-decoration: none;
}
.bs-subtile::before {
  content: "";
  position: absolute;
  left: 0;
  top: 14px;
  bottom: 14px;
  width: 3px;
  background: var(--accent);
  border-radius: 0 3px 3px 0;
}
.bs-subtile__glyph {
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  border-radius: 8px;
  display: grid;
  place-items: center;
  background: color-mix(in oklab, var(--accent) 12%, white);
  color: var(--accent);
}
.bs-subtile__glyph svg { width: 20px; height: 20px; display: block; }
.bs-subtile__body { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.bs-subtile__title {
  font-size: 15px;
  font-weight: 700;
  letter-spacing: -0.005em;
  color: var(--bs-ink);
}
.bs-subtile__hint {
  font-size: 12.5px;
  color: var(--bs-ink-3);
  margin-top: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.bs-subtile__chev { color: var(--bs-ink-4); flex: 0 0 auto; }

.bs-menu__sub--accent {
  display: flex;
  align-items: center;
  gap: 9px;
}
.bs-menu__sub--accent .bs-menu__dot {
  width: 6px;
  height: 6px;
  flex: 0 0 6px;
}

@media (max-width: 768px) {
  .bs-subtiles { grid-template-columns: 1fr; gap: 10px; margin-top: 10px; }
  .bs-subtile { height: 72px; padding: 0 14px 0 18px; gap: 12px; }
  .bs-subtile__glyph { width: 34px; height: 34px; flex-basis: 34px; border-radius: 7px; }
  .bs-subtile__glyph svg { width: 18px; height: 18px; }
  .bs-subtile__title { font-size: 14px; }
  .bs-subtile__hint { font-size: 12px; }
}

@supports not (background: color-mix(in oklab, red, white)) {
  .bs-subtile__glyph { background: rgba(0,0,0,0.04); }
  .bs-subtile:hover { border-color: var(--bs-line); }
}
/* ==== /CAT-002-5 ==== */
CSS;
    $updated['css'] = cat002_replace_once(
        $updated['css'],
        $cssEndAnchor,
        $cssComponent,
        'css_eof_component'
    );
}

if (!$menuApplied) {
    $menuUrlReplacements = [
        'href="index.php?route=product/category&path=59"' => 'href="/catalog/pokemon"',
        'href="index.php?route=product/category&path=60"' => 'href="/catalog/one-piece"',
        'href="index.php?route=product/category&path=59_61"' => 'href="/catalog/bustery-pokemon"',
        'href="index.php?route=product/category&path=59_62"' => 'href="/catalog/Pokemon-booster-box"',
        'href="index.php?route=product/category&path=59_64"' => 'href="/catalog/pokemon-tcg-nabory"',
        'href="index.php?route=product/category&path=60_63"' => 'href="/catalog/One-Piece-Boosters"',
    ];

    foreach ($menuUrlReplacements as $old => $new) {
        $updated['header'] = cat002_replace_once(
            $updated['header'],
            $old,
            $new,
            'menu_' . substr(sha1($old), 0, 8)
        );
    }

    $saleAnchor = <<<'TWIG'
      <div class="bs-menu__cat">
        <a href="index.php?route=product/special" class="bs-menu__cat-row is-sale">
TWIG;
    $newMenu = <<<'TWIG'
      {# CAT-002-5b · burger categories #}
      <div class="bs-menu__cat">
        <button type="button" class="bs-menu__cat-row" data-bs-accordion>
          <span class="bs-menu__dot" style="background:var(--bs-other-tcg)"></span>
          <span class="bs-menu__cat-name">Інші TCG</span>
          <span class="bs-menu__chev bs-menu__chev--acc">
            <svg width="13" height="13" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <path d="M4 2.5L9 7l-5 4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </button>
        <div class="bs-menu__subs" hidden>
          <a href="/catalog/more-tcg" class="bs-menu__sub">Усі Інші TCG</a>
          <a href="/catalog/more-tcg/Yu-Gi-Oh" class="bs-menu__sub bs-menu__sub--accent">
            <span class="bs-menu__dot" style="background:var(--bs-yugioh)"></span>
            <span>Yu-Gi-Oh! OCG</span>
          </a>
          <a href="/catalog/more-tcg/magic-the-gathering" class="bs-menu__sub bs-menu__sub--accent">
            <span class="bs-menu__dot" style="background:var(--bs-mtg)"></span>
            <span>Magic: The Gathering</span>
          </a>
        </div>
      </div>

      <div class="bs-menu__cat">
        <a href="/catalog/acsesuary" class="bs-menu__cat-row">
          <span class="bs-menu__dot" style="background:var(--bs-accessories)"></span>
          <span class="bs-menu__cat-name">Аксесуари</span>
          <span class="bs-menu__chev">
            <svg width="13" height="13" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <path d="M4 2.5L9 7l-5 4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </a>
      </div>

      <div class="bs-menu__cat">
        <a href="index.php?route=product/special" class="bs-menu__cat-row is-sale">
TWIG;
    $updated['header'] = cat002_replace_once(
        $updated['header'],
        $saleAnchor,
        $newMenu,
        'menu_before_special'
    );

    $updated['header'] = cat002_replace_once(
        $updated['header'],
        'boostershop-ds.css?v=rd10-buyrow-final-20260612',
        'boostershop-ds.css?v=' . CAT002_CACHE_VERSION,
        'header_css_cache_bust'
    );
}

cat002_assert_file_state($updated['home'], $updated['header'], $updated['css']);
cat002_out('assert=files_transformed:ok');

/*
 * REDO execution path. The legacy code below this block is intentionally
 * unreachable; keeping it does not affect runtime and avoids a risky wholesale
 * rewrite of the reviewed file-transform helpers above.
 */
$payload = cat002_redo_payload();
$db = null;
$dbTransaction = false;
$dbCommitted = false;
$prefix = '';
$activeLanguages = [];
$preState = [];

if ($dryRun && $skipDb) {
    cat002_out('db_preflight=skipped_local_file_validation');
    cat002_out('would_change_home=' . ($homeApplied ? 'no' : 'yes'));
    cat002_out('would_change_css=' . ($cssApplied ? 'no' : 'yes'));
    cat002_out('would_change_header=' . ($menuApplied ? 'no' : 'yes'));
    cat002_out('assert=dry_run_files:ok');
    cat002_out('done=ok');
    exit(0);
}

try {
    $configPath = $root . '/config.php';
    if (!is_file($configPath)) {
        cat002_fail('config_not_found=' . $configPath);
    }
    require_once $configPath;
    foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PREFIX'] as $constant) {
        if (!defined($constant)) {
            cat002_fail('db_constant_missing=' . $constant);
        }
    }

    $db = @new mysqli(
        (string)DB_HOSTNAME,
        (string)DB_USERNAME,
        (string)DB_PASSWORD,
        (string)DB_DATABASE,
        defined('DB_PORT') ? (int)DB_PORT : 3306
    );
    if ($db->connect_errno) {
        cat002_fail('db_connect_failed=' . $db->connect_error);
    }
    if (!$db->set_charset('utf8mb4')) {
        cat002_fail('db_charset_failed=' . $db->error);
    }

    $prefix = (string)DB_PREFIX;
    if (!preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
        cat002_fail('unsafe_db_prefix');
    }
    $requiredTables = [
        'language',
        'theme',
        'product',
        'product_description',
        'category_description',
        'seo_url',
        'attribute_group',
        'attribute_group_description',
        'attribute',
        'attribute_description',
        'product_attribute',
    ];
    foreach ($requiredTables as $suffix) {
        $table = $prefix . $suffix;
        $escapedTable = $db->real_escape_string($table);
        if (cat002_scalar(
            $db,
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$escapedTable}'"
        ) !== 1) {
            cat002_fail('db_table_missing=' . $table);
        }
    }
    cat002_out('assert=db_tables:ok');

    if (cat002_scalar(
        $db,
        "SELECT COUNT(*) FROM `{$prefix}theme`
         WHERE `store_id`=0 AND `route` IN ('common/home','common/header')"
    ) !== 0) {
        cat002_fail('db_theme_override_masks_twig');
    }
    cat002_out('assert=db_theme_overrides_clear:ok');

    $languageRows = cat002_rows(
        $db,
        "SELECT `language_id` FROM `{$prefix}language`
         WHERE `status`=1 ORDER BY `language_id`"
    );
    $activeLanguages = array_map(
        static fn(array $row): int => (int)$row['language_id'],
        $languageRows
    );
    if ($activeLanguages === []) {
        cat002_fail('db_no_active_languages');
    }
    cat002_out('assert=db_active_languages:ok');

    cat002_redo_assert_entities($db, $prefix);
    cat002_out('assert=db_ids_models_and_seo:ok');

    $preState = cat002_redo_db_state($db, $prefix, $activeLanguages, $payload);
    cat002_out('db_preflight=ok');

    $allFilesApplied = $homeApplied && $cssApplied && $menuApplied;
    if (!$dryRun && !$skipDb && $allFilesApplied && (bool)$preState['complete']) {
        cat002_out('already_applied=yes');
        cat002_out('attribute_group_id=' . (int)$preState['group_id']);
        cat002_out('run_url=https://boostershop.website/');
        cat002_out('qa_url_accessories=https://boostershop.website/catalog/acsesuary');
        cat002_out('qa_url_more_tcg=https://boostershop.website/catalog/more-tcg');
        cat002_out('done=ok');
        $db->close();
        @unlink(__FILE__);
        exit(0);
    }

    if ($dryRun) {
        cat002_out('would_change_home=' . ($homeApplied ? 'no' : 'yes'));
        cat002_out('would_change_css=' . ($cssApplied ? 'no' : 'yes'));
        cat002_out('would_change_header=' . ($menuApplied ? 'no' : 'yes'));
        cat002_out('would_change_db=' . ((bool)$preState['complete'] ? 'no' : 'yes'));
        cat002_out('assert=dry_run:ok');
        cat002_out('done=ok');
        $db->close();
        exit(0);
    }
    if ($skipDb) {
        cat002_fail('skip_db_is_only_allowed_with_dry_run');
    }

    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
        cat002_fail('backup_root_create_failed=' . $backupDir);
    }
    foreach ($files as $relative) {
        cat002_backup_file($root, $backupDir, $relative);
    }

    $groupIdBefore = (int)$preState['group_id'];
    $attributeIdsBefore = array_values(array_map('intval', $preState['attribute_ids']));
    $productIdsSql = '95,96,97,98,99,100';
    $oldProductDescriptions = cat002_rows(
        $db,
        "SELECT `product_id`,`language_id`,`description`
         FROM `{$prefix}product_description`
         WHERE `product_id` IN (95,96,97,98,99,100,101)
           AND `language_id` IN (" . implode(',', $activeLanguages) . ")
         ORDER BY `product_id`,`language_id`"
    );
    $oldCategoryDescriptions = cat002_rows(
        $db,
        "SELECT `category_id`,`language_id`,`description`
         FROM `{$prefix}category_description`
         WHERE `category_id`=70
           AND `language_id` IN (" . implode(',', $activeLanguages) . ")
         ORDER BY `language_id`"
    );
    $oldProductAttributes = [];
    if ($attributeIdsBefore !== []) {
        $oldProductAttributes = cat002_rows(
            $db,
            "SELECT `product_id`,`attribute_id`,`language_id`,`text`
             FROM `{$prefix}product_attribute`
             WHERE `product_id` IN ({$productIdsSql})
               AND `attribute_id` IN (" . implode(',', $attributeIdsBefore) . ")
             ORDER BY `product_id`,`attribute_id`,`language_id`"
        );
    }
    $snapshot = [
        'captured_at' => date('c'),
        'prefix' => $prefix,
        'active_language_ids' => $activeLanguages,
        'group_id_before' => $groupIdBefore,
        'attribute_ids_before' => $preState['attribute_ids'],
        'product_attribute_rows_before' => $oldProductAttributes,
        'product_descriptions_before' => $oldProductDescriptions,
        'category_descriptions_before' => $oldCategoryDescriptions,
    ];
    $snapshotJson = json_encode(
        $snapshot,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    if (file_put_contents($backupDir . '/db-prechange.json', $snapshotJson . PHP_EOL, LOCK_EX) === false) {
        cat002_fail('db_snapshot_write_failed');
    }

    $rollback = "-- CAT-002-5/CAT-002-5b REDO rollback\nSTART TRANSACTION;\n";
    foreach ($oldProductDescriptions as $row) {
        $rollback .= "UPDATE `{$prefix}product_description` SET `description`="
            . cat002_sql_value($db, $row['description'])
            . " WHERE `product_id`=" . (int)$row['product_id']
            . " AND `language_id`=" . (int)$row['language_id'] . ";\n";
    }
    foreach ($oldCategoryDescriptions as $row) {
        $rollback .= "UPDATE `{$prefix}category_description` SET `description`="
            . cat002_sql_value($db, $row['description'])
            . " WHERE `category_id`=70 AND `language_id`=" . (int)$row['language_id'] . ";\n";
    }
    $rollback .= "DELETE pa FROM `{$prefix}product_attribute` pa "
        . "INNER JOIN `{$prefix}attribute` a ON a.`attribute_id`=pa.`attribute_id` "
        . "INNER JOIN `{$prefix}attribute_group_description` agd "
        . "ON agd.`attribute_group_id`=a.`attribute_group_id` "
        . "WHERE pa.`product_id` IN ({$productIdsSql}) "
        . "AND BINARY agd.`name`=BINARY 'Характеристики аксесуарів';\n";
    foreach ($oldProductAttributes as $row) {
        $rollback .= "INSERT INTO `{$prefix}product_attribute` "
            . "(`product_id`,`attribute_id`,`language_id`,`text`) VALUES ("
            . (int)$row['product_id'] . ',' . (int)$row['attribute_id'] . ','
            . (int)$row['language_id'] . ',' . cat002_sql_value($db, $row['text'])
            . ") ON DUPLICATE KEY UPDATE `text`=VALUES(`text`);\n";
    }
    $missingNames = array_diff(array_keys(cat002_redo_attributes()), array_keys($preState['attribute_ids']));
    foreach ($missingNames as $name) {
        $escapedName = $db->real_escape_string($name);
        $rollback .= "DELETE ad FROM `{$prefix}attribute_description` ad "
            . "INNER JOIN `{$prefix}attribute` a ON a.`attribute_id`=ad.`attribute_id` "
            . "INNER JOIN `{$prefix}attribute_group_description` agd "
            . "ON agd.`attribute_group_id`=a.`attribute_group_id` "
            . "WHERE BINARY agd.`name`=BINARY 'Характеристики аксесуарів' "
            . "AND a.`attribute_id` IN (SELECT x.`attribute_id` FROM "
            . "(SELECT a2.`attribute_id` FROM `{$prefix}attribute` a2 "
            . "INNER JOIN `{$prefix}attribute_description` ad2 "
            . "ON ad2.`attribute_id`=a2.`attribute_id` "
            . "INNER JOIN `{$prefix}attribute_group_description` agd2 "
            . "ON agd2.`attribute_group_id`=a2.`attribute_group_id` "
            . "WHERE BINARY agd2.`name`=BINARY 'Характеристики аксесуарів' "
            . "AND BINARY ad2.`name`=BINARY '{$escapedName}') x);\n";
        $rollback .= "DELETE a FROM `{$prefix}attribute` a "
            . "INNER JOIN `{$prefix}attribute_group_description` agd "
            . "ON agd.`attribute_group_id`=a.`attribute_group_id` "
            . "WHERE BINARY agd.`name`=BINARY 'Характеристики аксесуарів' "
            . "AND NOT EXISTS (SELECT 1 FROM `{$prefix}attribute_description` ad "
            . "WHERE ad.`attribute_id`=a.`attribute_id`);\n";
    }
    if ($groupIdBefore === 0) {
        $rollback .= "DELETE agd FROM `{$prefix}attribute_group_description` agd "
            . "WHERE BINARY agd.`name`=BINARY 'Характеристики аксесуарів';\n"
            . "DELETE ag FROM `{$prefix}attribute_group` ag "
            . "WHERE NOT EXISTS (SELECT 1 FROM `{$prefix}attribute_group_description` agd "
            . "WHERE agd.`attribute_group_id`=ag.`attribute_group_id`) "
            . "AND NOT EXISTS (SELECT 1 FROM `{$prefix}attribute` a "
            . "WHERE a.`attribute_group_id`=ag.`attribute_group_id`);\n";
    }
    $rollback .= "COMMIT;\n";
    if (file_put_contents($backupDir . '/rollback.sql', $rollback, LOCK_EX) === false) {
        cat002_fail('rollback_sql_write_failed');
    }
    cat002_out('assert=backup_and_rollback_before_changes:ok');

    if (!$db->begin_transaction()) {
        cat002_fail('db_transaction_begin_failed=' . $db->error);
    }
    $dbTransaction = true;

    $groupId = $groupIdBefore;
    if ($groupId === 0) {
        if (!$db->query("INSERT INTO `{$prefix}attribute_group` (`sort_order`) VALUES (10)")) {
            cat002_fail('db_attribute_group_insert_failed=' . $db->error);
        }
        $groupId = (int)$db->insert_id;
    }
    foreach ($activeLanguages as $languageId) {
        if (!$db->query(
            "INSERT INTO `{$prefix}attribute_group_description`
             (`attribute_group_id`,`language_id`,`name`)
             VALUES ({$groupId},{$languageId},'Характеристики аксесуарів')
             ON DUPLICATE KEY UPDATE `name`=VALUES(`name`)"
        )) {
            cat002_fail('db_attribute_group_description_failed=' . $db->error);
        }
    }
    cat002_out('assert=db_attribute_group:ok:id=' . $groupId);

    $attributeIds = cat002_redo_attribute_ids($db, $prefix, $groupId);
    foreach (cat002_redo_attributes() as $name => $sortOrder) {
        $attributeId = (int)($attributeIds[$name] ?? 0);
        if ($attributeId === 0) {
            if (!$db->query(
                "INSERT INTO `{$prefix}attribute` (`attribute_group_id`,`sort_order`)
                 VALUES ({$groupId},{$sortOrder})"
            )) {
                cat002_fail('db_attribute_insert_failed=' . $name . ':' . $db->error);
            }
            $attributeId = (int)$db->insert_id;
        }
        $escapedName = $db->real_escape_string($name);
        foreach ($activeLanguages as $languageId) {
            if (!$db->query(
                "INSERT INTO `{$prefix}attribute_description`
                 (`attribute_id`,`language_id`,`name`)
                 VALUES ({$attributeId},{$languageId},'{$escapedName}')
                 ON DUPLICATE KEY UPDATE `name`=VALUES(`name`)"
            )) {
                cat002_fail('db_attribute_description_failed=' . $name . ':' . $db->error);
            }
        }
        $attributeIds[$name] = $attributeId;
    }
    cat002_out('assert=db_attributes_9:ok');

    foreach (cat002_redo_assignments() as $productId => $values) {
        foreach ($values as $name => $text) {
            $attributeId = (int)$attributeIds[$name];
            $escapedText = $db->real_escape_string($text);
            foreach ($activeLanguages as $languageId) {
                if (!$db->query(
                    "INSERT INTO `{$prefix}product_attribute`
                     (`product_id`,`attribute_id`,`language_id`,`text`)
                     VALUES ({$productId},{$attributeId},{$languageId},'{$escapedText}')
                     ON DUPLICATE KEY UPDATE `text`=VALUES(`text`)"
                )) {
                    cat002_fail("db_product_attribute_failed={$productId}:{$name}:" . $db->error);
                }
            }
        }
    }
    cat002_out('assert=db_product_attributes_95_100:ok');

    foreach ($payload['products'] as $productId => $description) {
        $escapedDescription = $db->real_escape_string((string)$description);
        foreach ($activeLanguages as $languageId) {
            if (!$db->query(
                "UPDATE `{$prefix}product_description`
                 SET `description`='{$escapedDescription}'
                 WHERE `product_id`=" . (int)$productId . " AND `language_id`={$languageId}"
            )) {
                cat002_fail('db_product_description_failed=' . $productId . ':' . $db->error);
            }
            if ($db->affected_rows > 1) {
                cat002_fail('db_product_description_affected_too_many=' . $productId);
            }
        }
    }
    cat002_out('assert=db_product_descriptions_95_101:ok');

    $escapedCategoryDescription = $db->real_escape_string((string)$payload['category']);
    foreach ($activeLanguages as $languageId) {
        if (!$db->query(
            "UPDATE `{$prefix}category_description`
             SET `description`='{$escapedCategoryDescription}'
             WHERE `category_id`=70 AND `language_id`={$languageId}"
        )) {
            cat002_fail('db_category_description_failed=' . $db->error);
        }
        if ($db->affected_rows > 1) {
            cat002_fail('db_category_description_affected_too_many');
        }
    }
    cat002_out('assert=db_category_description_70:ok');

    $postDbState = cat002_redo_db_state($db, $prefix, $activeLanguages, $payload);
    if (!(bool)$postDbState['complete']) {
        cat002_fail('db_post_change_assert_failed');
    }
    cat002_out('assert=db_all_steps:ok');

    foreach (['home', 'css', 'header'] as $key) {
        if ($updated[$key] === $original[$key]) {
            cat002_out('changed=none:' . $files[$key]);
            continue;
        }
        cat002_write_file($root . '/' . $files[$key], $updated[$key]);
        $written = file_get_contents($root . '/' . $files[$key]);
        if (!is_string($written) || cat002_normalize($written) !== $updated[$key]) {
            cat002_fail('file_post_write_assert_failed=' . $files[$key]);
        }
        cat002_out('changed=' . $files[$key]);
        cat002_out('assert=file_write_' . $key . ':ok');
    }

    cat002_assert_file_state(
        cat002_normalize((string)file_get_contents($root . '/' . $files['home'])),
        cat002_normalize((string)file_get_contents($root . '/' . $files['header'])),
        cat002_normalize((string)file_get_contents($root . '/' . $files['css']))
    );
    cat002_out('assert=all_file_steps:ok');

    if (!$db->commit()) {
        cat002_fail('db_commit_failed=' . $db->error);
    }
    $dbTransaction = false;
    $dbCommitted = true;
    cat002_out('assert=db_commit:ok');

    $finalState = cat002_redo_db_state($db, $prefix, $activeLanguages, $payload);
    if (!(bool)$finalState['complete']) {
        cat002_fail('final_state_assert_failed');
    }
    cat002_out('attribute_group_id=' . (int)$finalState['group_id']);
    cat002_out('assert=final_state:ok');
    cat002_out('run_url=https://boostershop.website/');
    cat002_out('qa_url_accessories=https://boostershop.website/catalog/acsesuary');
    cat002_out('qa_url_more_tcg=https://boostershop.website/catalog/more-tcg');
    cat002_out('qa_url_pokemon=https://boostershop.website/catalog/pokemon');
    cat002_out('qa_url_one_piece=https://boostershop.website/catalog/one-piece');
    cat002_out('done=ok');
    $db->close();
    @unlink(__FILE__);
    exit(0);
} catch (Throwable $error) {
    if ($db instanceof mysqli && $dbTransaction) {
        @$db->rollback();
    }
    if (!$dbCommitted && is_dir($backupDir)) {
        cat002_restore_files($root, $backupDir, array_values($files));
    }
    cat002_out('rollback=' . ($dbCommitted ? 'not_attempted_after_commit' : 'db_transaction_and_file_backups'));
    cat002_out('error=' . $error->getMessage());
    cat002_out('done=failed');
    $db?->close();
    exit(1);
}

