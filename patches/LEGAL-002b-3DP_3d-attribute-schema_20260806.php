<?php
declare(strict_types=1);

/*
 * LEGAL-002b-3DP — work package 3 of 4.
 *
 * WHAT THIS DOES
 *   Creates 3D-product attribute DEFINITIONS only, so they appear in
 *   Admin → Catalog → Attributes. It creates:
 *     - one attribute group: «Характеристики 3D-виробу» (language_id=4)
 *     - 13 attributes inside that group
 *   It assigns NOTHING to any product: ocp5_product_attribute is never written,
 *   and the patch asserts its row count is identical before and after.
 *
 * OWNER DECISION IMPLEMENTED (2026-08-07)
 *   Reuse existing attributes wherever the meaning is identical; create a
 *   separate 3D group only for genuinely new attributes; do not duplicate an
 *   existing attribute merely to keep all 3D fields under one heading.
 *   REUSED AS-IS (verified, never modified by this patch):
 *     id=20 Виробник               (group 7)
 *     id=27 Тип товару             (group 9)  — covers the schema's "Тип виробу"
 *     id=29 Матеріал               (group 9)
 *     id=33 Сумісність з картками  (group 9)  — covers card-format compatibility
 *   NOT REUSED — id=30 «Розмір / Формат»:
 *     The owner's rule was to reuse it only if it can carry physical dimensions
 *     without changing its existing semantics. Its live values are
 *     '63×89 мм', '63.5×88 мм', 'Кишенька 68×94 мм', '35PT',
 *     '35PT (~66×92 мм внутрішній)' — i.e. the CARD/POCKET FORMAT an accessory
 *     fits, sometimes as a non-metric grade, not the product's own outer size.
 *     A 3D item needs Д×Ш×В of the object itself plus tolerance, so reusing it
 *     would change that attribute's established meaning. A dedicated
 *     «Розміри» is therefore created.
 *
 * LAMP LABELS (owner decision: collapse to the general labels)
 *   «Матеріал корпусу», «Розміри світильника» and a second «Тип товару» from
 *   Частина V are deliberately NOT created. Lamps use the shared Матеріал /
 *   Розміри / Тип товару attributes with lamp-specific VALUES. Only the
 *   genuinely lamp-only fields get their own attributes: Живлення, Довжина
 *   кабелю, Колір світла, Умови використання.
 *
 * NOT CREATED AS ATTRIBUTES
 *   «Назва товару» and «SKU (модель)» are native OpenCart product fields
 *   (ocp5_product_description.name, ocp5_product.model), not attributes.
 *
 * SCHEMA / MERCHANT-FEED GATE — CHECKED, NOT TRIGGERED
 *   catalog/view/template/product/product.twig renders attribute_groups only as
 *   a visible HTML table in the #tab-specification tab (no itemprop). None of
 *   the five application/ld+json blocks in that template reference attributes,
 *   and no additionalProperty/PropertyValue markup exists anywhere in the theme
 *   templates. Verified against backup-8.5.2026_10-49-27_boosters. New attribute
 *   labels therefore cannot reach Product JSON-LD or the Merchant feed, so
 *   bs-merchant-schema-qa is not required for this package.
 *
 * NOT TOUCHED
 *   ocp5_product_attribute, the existing rows of groups 7 and 9, every existing
 *   attribute (including id=30), sitemap/robots/.htaccess, checkout, payment.
 *
 * ROLLBACK
 *   The auto-increment IDs actually assigned are written to
 *   _patch_backups/<patch>-<ts>/db/created_ids.json. Delete exactly those:
 *     DELETE FROM ocp5_attribute_description       WHERE attribute_id IN (<created_attribute_ids>);
 *     DELETE FROM ocp5_attribute                   WHERE attribute_id IN (<created_attribute_ids>);
 *     DELETE FROM ocp5_attribute_group_description WHERE attribute_group_id = <created_attribute_group_id>;
 *     DELETE FROM ocp5_attribute_group             WHERE attribute_group_id = <created_attribute_group_id>;
 *   No ID is hardcoded here on purpose.
 */

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}
error_reporting(E_ALL);
ini_set('display_errors', '1');

const PATCH_NAME       = 'LEGAL-002b-3DP_3d-attribute-schema_20260806';
const LANGUAGE_ID      = 4;
const GROUP_NAME       = 'Характеристики 3D-виробу';
const GROUP_SORT_ORDER = 20;

// Labels taken verbatim from handoffs/handoff_LEGAL-002b-3DP_3d-attribute-schema_20260806.md
const NEW_ATTRIBUTES = [
    'Країна виготовлення',
    'Спосіб виготовлення',
    'Розміри',
    'Маса',
    'Комплектація',
    'Рухомі елементи',
    'Вікове позиціонування',
    'Типовий строк виготовлення при відсутності на складі',
    'Може зустрічатися в Mystery Box Item',
    'Живлення',
    'Довжина кабелю',
    'Колір світла',
    'Умови використання',
];

// Existing attributes this schema reuses. Verified, never modified.
const REUSED_ATTRIBUTES = [
    20 => 'Виробник',
    27 => 'Тип товару',
    29 => 'Матеріал',
    33 => 'Сумісність з картками',
];

// Deliberately not reused — see the header note.
const NOT_REUSED = [30 => 'Розмір / Формат'];

function bs_log(string $key, string $value = ''): void {
    echo $key . ($value === '' ? '' : '=' . $value) . PHP_EOL;
}
function bs_fail(string $message): void { throw new RuntimeException($message); }
function bs_path(string $base, string $part): string {
    return rtrim($base, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $part);
}
function bs_table(string $prefix, string $suffix): string {
    $table = $prefix . $suffix;
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) bs_fail('Unsafe DB table name from DB_PREFIX');
    return $table;
}
function bs_quote(mysqli $db, string $value): string { return "'" . $db->real_escape_string($value) . "'"; }
function bs_table_exists(mysqli $db, string $table): bool {
    $r = $db->query('SHOW TABLES LIKE ' . bs_quote($db, $table));
    $ok = $r->num_rows === 1; $r->free(); return $ok;
}
function bs_columns(mysqli $db, string $table): array {
    $r = $db->query('SHOW COLUMNS FROM `' . $table . '`'); $columns = [];
    while ($row = $r->fetch_assoc()) $columns[(string) $row['Field']] = true;
    $r->free(); return $columns;
}
function bs_require_columns(array $columns, array $needed, string $table): void {
    foreach ($needed as $column) if (!isset($columns[$column])) bs_fail('Unexpected schema: ' . $table . '.' . $column . ' is missing');
}
function bs_lint_self(): void {
    if (!function_exists('exec')) bs_fail('PHP exec() is unavailable; cannot pass mandatory php -l gate');
    $php = PHP_BINARY !== '' ? PHP_BINARY : 'php'; $output = []; $code = 1;
    @exec(escapeshellarg($php) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $output, $code);
    if ($code !== 0) bs_fail('php -l gate failed: ' . implode(' ', $output));
    bs_log('php_l', 'ok');
}
function bs_blob(string $b64, string $sha, string $label): string {
    if (!function_exists('gzdecode')) bs_fail('zlib/gzdecode is unavailable; cannot decode embedded ' . $label);
    $compressed = base64_decode(preg_replace('/\s+/', '', $b64) ?? '', true);
    $html = is_string($compressed) ? @gzdecode($compressed) : false;
    if (!is_string($html) || $html === '') bs_fail('Cannot decode embedded ' . $label);
    if (hash('sha256', $html) !== $sha) bs_fail($label . ' SHA-256 mismatch inside this patch file');
    return $html;
}
function bs_expect(string $html, string $needle, int $count, string $label): void {
    $found = substr_count($html, $needle);
    if ($found !== $count) bs_fail($label . ': expected ' . $count . ' x "' . $needle . '", found ' . $found);
}
function bs_connect(): mysqli {
    if (!extension_loaded('mysqli')) bs_fail('mysqli extension is not loaded');
    foreach (['DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PREFIX'] as $constant) {
        if (!defined($constant)) bs_fail('Missing config constant: ' . $constant);
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, defined('DB_PORT') ? (int) DB_PORT : 3306);
    $db->set_charset('utf8mb4'); bs_log('db_connect', 'ok'); return $db;
}
function bs_stmt_rows(mysqli_stmt $stmt): array {
    $metadata = $stmt->result_metadata();
    if ($metadata === false) bs_fail('Cannot read SQL result metadata');
    $row = []; $refs = [];
    foreach ($metadata->fetch_fields() as $field) { $row[$field->name] = null; $refs[] = &$row[$field->name]; }
    if (!call_user_func_array([$stmt, 'bind_result'], $refs)) bs_fail('Cannot bind SQL result columns');
    $rows = [];
    while ($stmt->fetch()) { $copy = []; foreach ($row as $k => $v) $copy[$k] = $v; $rows[] = $copy; }
    $metadata->free();
    return $rows;
}
function bs_select(mysqli $db, string $sql, string $types, array $params): array {
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $refs = [$types];
        foreach ($params as $key => &$value) $refs[] = &$params[$key];
        if (!call_user_func_array([$stmt, 'bind_param'], $refs)) bs_fail('Cannot bind query parameters');
    }
    $stmt->execute(); $rows = bs_stmt_rows($stmt); $stmt->close(); return $rows;
}
function bs_json_backup(string $dir, string $name, array $payload): void {
    $path = bs_path($dir, 'db/' . $name . '.json'); $parent = dirname($path);
    if (!is_dir($parent) && !mkdir($parent, 0755, true)) bs_fail('Cannot create DB backup directory');
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($json) || file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) bs_fail('Cannot write DB backup: ' . $name);
    bs_log('backup_db', $path);
}
function bs_self_delete(): void {
    @unlink(__FILE__);
    bs_log('self_delete', file_exists(__FILE__) ? 'failed' : 'ok');
}

function bs_group_id_by_name(mysqli $db, string $table, string $groupName): int {
    $rows = bs_select($db, 'SELECT attribute_group_id FROM `' . $table . '` WHERE language_id = ? AND name = ?',
        'is', [LANGUAGE_ID, $groupName]);
    if (count($rows) > 1) bs_fail('Ambiguous attribute groups named "' . $groupName . '"');
    return $rows === [] ? 0 : (int) $rows[0]['attribute_group_id'];
}
function bs_attribute_names_in_group(mysqli $db, string $attr, string $attrDesc, int $groupId): array {
    $rows = bs_select($db,
        'SELECT a.attribute_id AS attribute_id, d.name AS name FROM `' . $attr . '` a'
        . ' JOIN `' . $attrDesc . '` d ON d.attribute_id = a.attribute_id AND d.language_id = ?'
        . ' WHERE a.attribute_group_id = ?', 'ii', [LANGUAGE_ID, $groupId]);
    $map = [];
    foreach ($rows as $row) $map[(string) $row['name']] = (int) $row['attribute_id'];
    return $map;
}
function bs_attribute_name(mysqli $db, string $attrDesc, int $attributeId): ?string {
    $rows = bs_select($db, 'SELECT name FROM `' . $attrDesc . '` WHERE attribute_id = ? AND language_id = ?',
        'ii', [$attributeId, LANGUAGE_ID]);
    return $rows === [] ? null : (string) $rows[0]['name'];
}
function bs_product_attribute_count(mysqli $db, string $table): int {
    $r = $db->query('SELECT COUNT(*) AS c FROM `' . $table . '`');
    $row = $r->fetch_assoc(); $r->free();
    return (int) $row['c'];
}

function bs_run(): void {
    $cwd = getcwd();
    if (!is_string($cwd) || $cwd === '') bs_fail('Cannot determine cwd');
    bs_log('patch', PATCH_NAME); bs_log('cwd', $cwd); bs_log('time', date('c'));

    $config = bs_path($cwd, 'config.php');
    if (!is_file($config)) bs_fail('config.php not found. Run this patch from ~/public_html.');

    bs_lint_self();
    require_once $config;

    // attribute_description.name is varchar(64) — refuse anything that would truncate.
    foreach (NEW_ATTRIBUTES as $label) {
        $length = function_exists('mb_strlen') ? mb_strlen($label, 'UTF-8') : strlen($label);
        if ($length > 64) bs_fail('Label exceeds the varchar(64) column: ' . $label);
    }
    if (count(array_unique(NEW_ATTRIBUTES)) !== count(NEW_ATTRIBUTES)) bs_fail('Duplicate label in NEW_ATTRIBUTES');
    bs_log('new_attribute_count', (string) count(NEW_ATTRIBUTES));

    $backupDir = bs_path($cwd, '_patch_backups/' . PATCH_NAME . '-' . date('Ymd-His'));
    if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) bs_fail('Cannot create backup directory');
    bs_log('backup_dir', $backupDir);

    $db = bs_connect();
    try {
        $prefix    = (string) DB_PREFIX;
        $group     = bs_table($prefix, 'attribute_group');
        $groupDesc = bs_table($prefix, 'attribute_group_description');
        $attr      = bs_table($prefix, 'attribute');
        $attrDesc  = bs_table($prefix, 'attribute_description');
        $prodAttr  = bs_table($prefix, 'product_attribute');
        foreach ([$group, $groupDesc, $attr, $attrDesc, $prodAttr] as $table) {
            if (!bs_table_exists($db, $table)) bs_fail('Required table not found: ' . $table);
        }
        bs_require_columns(bs_columns($db, $group), ['attribute_group_id', 'sort_order'], $group);
        bs_require_columns(bs_columns($db, $groupDesc), ['attribute_group_id', 'language_id', 'name'], $groupDesc);
        bs_require_columns(bs_columns($db, $attr), ['attribute_id', 'attribute_group_id', 'sort_order'], $attr);
        bs_require_columns(bs_columns($db, $attrDesc), ['attribute_id', 'language_id', 'name'], $attrDesc);

        // --- reuse contract: the attributes we rely on must still be what we think ---
        foreach (REUSED_ATTRIBUTES as $id => $expected) {
            $actual = bs_attribute_name($db, $attrDesc, (int) $id);
            if ($actual === null) bs_fail('Reused attribute id=' . $id . ' («' . $expected . '») no longer exists — resolve before running');
            if ($actual !== $expected) {
                bs_fail('Reused attribute id=' . $id . ' is now named «' . $actual . '», expected «' . $expected
                    . '». The reuse decision was made against that label — stopping.');
            }
            bs_log('reuse_verified', 'id=' . $id . ' «' . $expected . '»');
        }
        foreach (NOT_REUSED as $id => $label) {
            bs_log('not_reused', 'id=' . $id . ' «' . $label . '» — dedicated «Розміри» created instead (see header)');
        }

        $prodAttrBefore = bs_product_attribute_count($db, $prodAttr);
        bs_log('product_attribute_rows_before', (string) $prodAttrBefore);

        $groupId  = bs_group_id_by_name($db, $groupDesc, GROUP_NAME);
        $existing = $groupId > 0 ? bs_attribute_names_in_group($db, $attr, $attrDesc, $groupId) : [];
        $missing  = [];
        foreach (NEW_ATTRIBUTES as $label) if (!isset($existing[$label])) $missing[] = $label;

        if ($groupId > 0 && $missing === []) {
            bs_log('already_applied', 'yes');
            bs_log('attribute_group_id', (string) $groupId);
            bs_log('product_attribute_rows_after', (string) bs_product_attribute_count($db, $prodAttr));
            bs_log('done', 'ok');
            bs_self_delete();
            return;
        }

        bs_json_backup($backupDir, 'attribute_schema_before', [
            'attribute_group_table'             => $group,
            'attribute_group_description_table' => $groupDesc,
            'attribute_table'                   => $attr,
            'attribute_description_table'       => $attrDesc,
            'existing_group_id_for_name'        => $groupId,
            'existing_attributes_in_that_group' => $existing,
            'reused_attributes_verified'        => REUSED_ATTRIBUTES,
            'not_reused'                        => NOT_REUSED,
            'product_attribute_row_count'       => $prodAttrBefore,
        ]);

        $createdGroupId      = 0;
        $createdAttributeIds = [];

        $db->begin_transaction();
        try {
            if ($groupId === 0) {
                $db->query('INSERT INTO `' . $group . '` (`sort_order`) VALUES (' . GROUP_SORT_ORDER . ')');
                $groupId = (int) $db->insert_id;
                if ($groupId < 1) bs_fail('attribute_group insert returned no id');
                $createdGroupId = $groupId;

                $stmt = $db->prepare('INSERT INTO `' . $groupDesc . '` (`attribute_group_id`, `language_id`, `name`) VALUES (?, ?, ?)');
                $lang = LANGUAGE_ID; $gname = GROUP_NAME;
                $stmt->bind_param('iis', $groupId, $lang, $gname);
                $stmt->execute(); $stmt->close();
                bs_log('created_attribute_group_id', (string) $groupId);
            } else {
                bs_log('reusing_existing_group_id', (string) $groupId);
            }

            $sortOrder = 0;
            foreach (NEW_ATTRIBUTES as $label) {
                $sortOrder++;
                if (isset($existing[$label])) { bs_log('attribute_exists', $label . ' (id=' . $existing[$label] . ')'); continue; }

                $stmt = $db->prepare('INSERT INTO `' . $attr . '` (`attribute_group_id`, `sort_order`) VALUES (?, ?)');
                $stmt->bind_param('ii', $groupId, $sortOrder);
                $stmt->execute(); $stmt->close();
                $attributeId = (int) $db->insert_id;
                if ($attributeId < 1) bs_fail('attribute insert returned no id for ' . $label);

                $stmt = $db->prepare('INSERT INTO `' . $attrDesc . '` (`attribute_id`, `language_id`, `name`) VALUES (?, ?, ?)');
                $lang = LANGUAGE_ID; $aname = $label;
                $stmt->bind_param('iis', $attributeId, $lang, $aname);
                $stmt->execute(); $stmt->close();

                $createdAttributeIds[$label] = $attributeId;
                bs_log('created_attribute', $label . ' (id=' . $attributeId . ', sort_order=' . $sortOrder . ')');
            }

            // --- verify inside the transaction -------------------------------
            $verify = bs_attribute_names_in_group($db, $attr, $attrDesc, $groupId);
            foreach (NEW_ATTRIBUTES as $label) {
                if (!isset($verify[$label])) bs_fail('Verification failed: «' . $label . '» is missing from the group after write');
            }
            foreach (REUSED_ATTRIBUTES as $id => $expected) {
                if (bs_attribute_name($db, $attrDesc, (int) $id) !== $expected) bs_fail('A reused attribute was modified — rolling back');
            }
            $notReusedId = array_key_first(NOT_REUSED);
            if (bs_attribute_name($db, $attrDesc, (int) $notReusedId) !== NOT_REUSED[$notReusedId]) {
                bs_fail('Attribute id=' . $notReusedId . ' was modified — rolling back');
            }
            $prodAttrAfter = bs_product_attribute_count($db, $prodAttr);
            if ($prodAttrAfter !== $prodAttrBefore) {
                bs_fail('product_attribute row count changed (' . $prodAttrBefore . ' -> ' . $prodAttrAfter . ') — rolling back');
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }

        bs_json_backup($backupDir, 'created_ids', [
            'note'                       => 'Rollback: delete exactly these rows. See the patch header for the DELETE order.',
            'created_attribute_group_id' => $createdGroupId,
            'attribute_group_id_used'    => $groupId,
            'created_attribute_ids'      => $createdAttributeIds,
        ]);

        bs_log('attribute_group', GROUP_NAME . ' (id=' . $groupId . ')');
        bs_log('created_attributes', (string) count($createdAttributeIds));
        bs_log('product_attribute_rows_after', (string) bs_product_attribute_count($db, $prodAttr));
        bs_log('product_assignment', 'none — ocp5_product_attribute untouched by design');
        bs_log('done', 'ok');
        bs_self_delete();
    } finally {
        $db->close();
    }
}

try {
    bs_run();
} catch (Throwable $e) {
    bs_log('error', $e->getMessage());
    bs_log('done', 'failed');
    exit(1);
}