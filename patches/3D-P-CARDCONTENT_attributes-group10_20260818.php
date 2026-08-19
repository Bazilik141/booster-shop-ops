<?php
declare(strict_types=1);

/*
 * 3D-P-CARDCONTENT — work package 2 of 4: six attributes in attribute_group_id = 10.
 *
 * SOURCE OF TRUTH
 *   handoffs/handoff_3D-P-CARDCONTENT_five-pokemon-keychains_20260816.md §2.2
 *   handoffs/handoff_3D-P-CARDCONTENT_figures-wave_20260816.md            §2.2
 *   handoffs/handoff_3D-P-CARDCONTENT_stands-wave_20260816.md             §2.2
 *   Preflight evidence: diagnostics/3D-P-002_3D-P-CARDCONTENT_db-preflight_20260818.md
 *
 * WHAT THIS DOES
 *   Creates six attribute DEFINITIONS inside the existing group
 *   «Характеристики 3D-виробу» (attribute_group_id = 10), as the union of what
 *   the three product waves need:
 *
 *     Тип виробу          keychains + figures + stands
 *     Матеріал            keychains + figures + stands
 *     Колір               keychains + figures + stands
 *     Матеріал фурнітури  keychains only
 *     Призначення         figures only
 *     Сумісність          stands only
 *
 *   It writes ocp5_attribute and ocp5_attribute_description ONLY.
 *   It assigns nothing to any product: ocp5_product_attribute is never written
 *   and its row count is asserted identical before and after.
 *   It creates no attribute group — group 10 already exists.
 *
 * WHY NEW ROWS AND NOT REUSE — this reverses an earlier decision, deliberately
 *   patches/LEGAL-002b-3DP_3d-attribute-schema_20260806.php deliberately REUSED
 *   id=27 «Тип товару», id=29 «Матеріал» and id=33 «Сумісність з картками» from
 *   group 9 rather than duplicating them. All three waves now override that:
 *   an attribute belongs to exactly one group, so using a group-9 id drags the
 *   heading «Характеристики аксесуарів» onto a 3D product page next to
 *   «Характеристики 3D-виробу». Identical names across groups are normal in
 *   OpenCart. The group-9 attributes are read-only here and are asserted
 *   unchanged; nothing about them is modified.
 *
 *   Verified against backup-8.16.2026_08-03-55_boosters — none of the six
 *   exists in group 10, and «Колір» does not exist anywhere (id 47 is
 *   «Колір світла», a different attribute).
 *
 * ⚠ DISPLAY ORDER — READ THIS, IT IS A KNOWN AND ACCEPTED CONSEQUENCE
 *   Group 10 already uses sort_order 1..13 for ids 36..48. The new attributes
 *   are appended as 14..19, so the specification table on a product page will
 *   read:
 *       Країна виготовлення, Спосіб виготовлення, Розміри, Маса, Комплектація,
 *       Рухомі елементи, Вікове позиціонування, Типовий строк…, Mystery Box,
 *       Тип виробу, Матеріал, Колір, [Матеріал фурнітури|Призначення|Сумісність]
 *   The handoff §3 tables list a different, nicer order that starts with
 *   «Тип виробу». Producing that order requires RENUMBERING sort_order on the
 *   existing rows 36..48, which none of the three handoffs asked for, so this
 *   patch does not do it. It is safe to do later — group 10 is not yet used by
 *   any product — and the ready SQL is in the diagnostic report. Owner's call.
 *
 * ROLLBACK
 *   Actual ids are written to _patch_backups/<patch>-<ts>/db/created_ids.json.
 *     DELETE FROM ocp5_attribute_description WHERE attribute_id IN (<created_attribute_ids>);
 *     DELETE FROM ocp5_attribute             WHERE attribute_id IN (<created_attribute_ids>);
 *   Expected ids at backup state (NOT hardcoded, orientation only): 50..55.
 *   Safe to roll back only while no product uses them, i.e. before WP3/WP4.
 *
 * NOT TOUCHED
 *   ocp5_product_attribute, ocp5_attribute_group, ocp5_attribute_group_description,
 *   every existing attribute in groups 7, 9 and 10, any product, any category.
 */

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}
error_reporting(E_ALL);
ini_set('display_errors', '1');

const PATCH_NAME         = '3D-P-CARDCONTENT_attributes-group10_20260818';
const LANGUAGE_ID        = 4;
const GROUP_ID           = 10;
const GROUP_NAME         = 'Характеристики 3D-виробу';
const FIRST_SORT_ORDER   = 14; // 1..13 are taken by ids 36..48

// Labels verbatim from the three wave handoffs, §2.2 of each.
const NEW_ATTRIBUTES = [
    'Тип виробу',
    'Матеріал',
    'Колір',
    'Матеріал фурнітури',
    'Призначення',
    'Сумісність',
];

/**
 * Group 10 as the backup shows it. Asserted present and unmodified — if any of
 * these has been renamed, the wave handoffs were written against a stale schema
 * and the patch must not guess.
 */
const EXPECTED_GROUP_10 = [
    36 => 'Країна виготовлення',
    37 => 'Спосіб виготовлення',
    38 => 'Розміри',
    39 => 'Маса',
    40 => 'Комплектація',
    41 => 'Рухомі елементи',
    42 => 'Вікове позиціонування',
    43 => 'Типовий строк виготовлення при відсутності на складі',
    44 => 'Може зустрічатися в Mystery Box Item',
    45 => 'Живлення',
    46 => 'Довжина кабелю',
    47 => 'Колір світла',
    48 => 'Умови використання',
];

/**
 * Group 7 / group 9 attributes that carry a confusingly similar name.
 * Asserted unchanged, never written. Their existence is exactly why the six
 * above are created fresh rather than reused.
 */
const DO_NOT_REUSE = [
    20 => ['name' => 'Виробник',              'group' => 7, 'why' => 'sealed goods; manufacturer is the native product field for 3D items'],
    27 => ['name' => 'Тип товару',            'group' => 9, 'why' => 'group 9 — would render the «Характеристики аксесуарів» heading'],
    29 => ['name' => 'Матеріал',              'group' => 9, 'why' => 'group 9 — same heading problem; a group-10 «Матеріал» is created instead'],
    33 => ['name' => 'Сумісність з картками', 'group' => 9, 'why' => 'group 9, and a narrower meaning than «Сумісність»'],
    47 => ['name' => 'Колір світла',          'group' => 10,'why' => 'lamp-only; «Колір» is a different attribute and did not exist'],
];

// --------------------------------------------------------------------------

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
function bs_bind_guard(string $sql, string $types, array $params): void {
    $marks = substr_count($sql, '?');
    if ($marks !== strlen($types) || $marks !== count($params)) {
        bs_fail('Bind mismatch: ' . $marks . ' placeholders, ' . strlen($types) . ' type chars, ' . count($params) . ' params in: ' . $sql);
    }
}
function bs_select(mysqli $db, string $sql, string $types, array $params): array {
    bs_bind_guard($sql, $types, $params);
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $refs = [$types];
        foreach ($params as $key => &$value) $refs[] = &$params[$key];
        if (!call_user_func_array([$stmt, 'bind_param'], $refs)) bs_fail('Cannot bind query parameters');
    }
    $stmt->execute(); $rows = bs_stmt_rows($stmt); $stmt->close(); return $rows;
}
function bs_exec(mysqli $db, string $sql, string $types, array $params): int {
    bs_bind_guard($sql, $types, $params);
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $refs = [$types];
        foreach ($params as $key => &$value) $refs[] = &$params[$key];
        if (!call_user_func_array([$stmt, 'bind_param'], $refs)) bs_fail('Cannot bind query parameters');
    }
    $stmt->execute(); $affected = $stmt->affected_rows; $stmt->close(); return (int) $affected;
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

function bs_attribute_name(mysqli $db, string $attrDesc, int $attributeId): ?string {
    $rows = bs_select($db, 'SELECT name FROM `' . $attrDesc . '` WHERE attribute_id = ? AND language_id = ?', 'ii', [$attributeId, LANGUAGE_ID]);
    return $rows === [] ? null : (string) $rows[0]['name'];
}
function bs_attribute_group(mysqli $db, string $attr, int $attributeId): ?int {
    $rows = bs_select($db, 'SELECT attribute_group_id FROM `' . $attr . '` WHERE attribute_id = ?', 'i', [$attributeId]);
    return $rows === [] ? null : (int) $rows[0]['attribute_group_id'];
}
function bs_attributes_in_group(mysqli $db, string $attr, string $attrDesc, int $groupId): array {
    $rows = bs_select($db,
        'SELECT a.attribute_id AS attribute_id, a.sort_order AS sort_order, d.name AS name FROM `' . $attr . '` a'
        . ' JOIN `' . $attrDesc . '` d ON d.attribute_id = a.attribute_id AND d.language_id = ?'
        . ' WHERE a.attribute_group_id = ? ORDER BY a.sort_order, a.attribute_id', 'ii', [LANGUAGE_ID, $groupId]);
    $map = [];
    foreach ($rows as $row) $map[(string) $row['name']] = ['id' => (int) $row['attribute_id'], 'sort_order' => (int) $row['sort_order']];
    return $map;
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

    // attribute_description.name is varchar(64) on this install.
    foreach (NEW_ATTRIBUTES as $label) {
        $length = mb_strlen($label, 'UTF-8');
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
        bs_require_columns(bs_columns($db, $attr), ['attribute_id', 'attribute_group_id', 'sort_order'], $attr);
        bs_require_columns(bs_columns($db, $attrDesc), ['attribute_id', 'language_id', 'name'], $attrDesc);

        // --- the group must exist and still be the 3D group ---
        $groupRows = bs_select($db, 'SELECT name FROM `' . $groupDesc . '` WHERE attribute_group_id = ? AND language_id = ?', 'ii', [GROUP_ID, LANGUAGE_ID]);
        if ($groupRows === []) bs_fail('attribute_group_id ' . GROUP_ID . ' does not exist — run LEGAL-002b-3DP first');
        if ((string) $groupRows[0]['name'] !== GROUP_NAME) {
            bs_fail('attribute_group_id ' . GROUP_ID . ' is named «' . $groupRows[0]['name'] . '», expected «' . GROUP_NAME . '» — stopping');
        }
        bs_log('group_verified', GROUP_ID . ' «' . GROUP_NAME . '»');

        // --- the 13 existing group-10 attributes must be intact ---
        foreach (EXPECTED_GROUP_10 as $id => $expectedName) {
            $actualName  = bs_attribute_name($db, $attrDesc, (int) $id);
            $actualGroup = bs_attribute_group($db, $attr, (int) $id);
            if ($actualName === null) bs_fail('Expected group-10 attribute id=' . $id . ' («' . $expectedName . '») no longer exists — stopping');
            if ($actualName !== $expectedName) bs_fail('Attribute id=' . $id . ' is named «' . $actualName . '», expected «' . $expectedName . '» — stopping');
            if ($actualGroup !== GROUP_ID) bs_fail('Attribute id=' . $id . ' moved to group ' . (string) $actualGroup . ', expected ' . GROUP_ID . ' — stopping');
        }
        bs_log('existing_group_10_verified', (string) count(EXPECTED_GROUP_10) . ' attributes intact');

        // --- the look-alikes must be where we think, so the no-reuse decision still holds ---
        foreach (DO_NOT_REUSE as $id => $meta) {
            $actualName  = bs_attribute_name($db, $attrDesc, (int) $id);
            $actualGroup = bs_attribute_group($db, $attr, (int) $id);
            if ($actualName !== $meta['name'] || $actualGroup !== $meta['group']) {
                bs_fail('Look-alike attribute id=' . $id . ' is «' . (string) $actualName . '» in group ' . (string) $actualGroup
                    . ', expected «' . $meta['name'] . '» in group ' . $meta['group'] . ' — the no-reuse decision was made against that; stopping.');
            }
            bs_log('not_reused', 'id=' . $id . ' «' . $meta['name'] . '» (group ' . $meta['group'] . ') — ' . $meta['why']);
        }

        $prodAttrBefore = bs_product_attribute_count($db, $prodAttr);
        bs_log('product_attribute_rows_before', (string) $prodAttrBefore);

        $existing = bs_attributes_in_group($db, $attr, $attrDesc, GROUP_ID);
        $missing  = [];
        foreach (NEW_ATTRIBUTES as $label) if (!isset($existing[$label])) $missing[] = $label;

        if ($missing === []) {
            bs_log('already_applied', 'yes');
            foreach (NEW_ATTRIBUTES as $label) bs_log('attribute_exists', $label . ' (id=' . $existing[$label]['id'] . ')');
            bs_log('product_attribute_rows_after', (string) bs_product_attribute_count($db, $prodAttr));
            bs_log('done', 'ok');
            bs_self_delete();
            return;
        }

        // Guard the sort_order band we are about to occupy.
        $maxSort = 0;
        foreach ($existing as $meta) $maxSort = max($maxSort, $meta['sort_order']);
        if ($maxSort >= FIRST_SORT_ORDER) {
            bs_fail('Group ' . GROUP_ID . ' already uses sort_order ' . $maxSort . ', which collides with the band starting at '
                . FIRST_SORT_ORDER . ' — resolve before running');
        }

        bs_json_backup($backupDir, 'attributes_before', [
            'attribute_table'             => $attr,
            'attribute_description_table' => $attrDesc,
            'group_id'                    => GROUP_ID,
            'group_name'                  => GROUP_NAME,
            'existing_attributes_in_group' => $existing,
            'missing_labels'              => $missing,
            'do_not_reuse_verified'       => DO_NOT_REUSE,
            'product_attribute_row_count' => $prodAttrBefore,
        ]);

        $createdAttributeIds = [];

        $db->begin_transaction();
        try {
            $sortOrder = FIRST_SORT_ORDER - 1;
            foreach (NEW_ATTRIBUTES as $label) {
                $sortOrder++;
                if (isset($existing[$label])) {
                    bs_log('attribute_exists', $label . ' (id=' . $existing[$label]['id'] . ')');
                    continue;
                }

                bs_exec($db, 'INSERT INTO `' . $attr . '` (`attribute_group_id`, `sort_order`) VALUES (?, ?)', 'ii', [GROUP_ID, $sortOrder]);
                $attributeId = (int) $db->insert_id;
                if ($attributeId < 1) bs_fail('attribute insert returned no id for ' . $label);

                bs_exec($db, 'INSERT INTO `' . $attrDesc . '` (`attribute_id`, `language_id`, `name`) VALUES (?, ?, ?)',
                    'iis', [$attributeId, LANGUAGE_ID, $label]);

                $createdAttributeIds[$label] = $attributeId;
                bs_log('created_attribute', $label . ' (id=' . $attributeId . ', sort_order=' . $sortOrder . ')');
            }

            // --- verify inside the transaction ---
            $verify = bs_attributes_in_group($db, $attr, $attrDesc, GROUP_ID);
            foreach (NEW_ATTRIBUTES as $label) {
                if (!isset($verify[$label])) bs_fail('Verification failed: «' . $label . '» is missing from group ' . GROUP_ID . ' after write');
            }
            if (count($verify) !== count(EXPECTED_GROUP_10) + count(NEW_ATTRIBUTES)) {
                bs_fail('Group ' . GROUP_ID . ' now holds ' . count($verify) . ' attributes, expected '
                    . (count(EXPECTED_GROUP_10) + count(NEW_ATTRIBUTES)) . ' — rolling back');
            }
            foreach (EXPECTED_GROUP_10 as $id => $expectedName) {
                if (bs_attribute_name($db, $attrDesc, (int) $id) !== $expectedName) bs_fail('Existing attribute id=' . $id . ' was modified — rolling back');
            }
            foreach (DO_NOT_REUSE as $id => $meta) {
                if (bs_attribute_name($db, $attrDesc, (int) $id) !== $meta['name'] || bs_attribute_group($db, $attr, (int) $id) !== $meta['group']) {
                    bs_fail('Look-alike attribute id=' . $id . ' was modified — rolling back');
                }
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
            'note'                   => 'Rollback: delete exactly these rows. attribute_description first, then attribute.',
            'attribute_group_id'     => GROUP_ID,
            'created_attribute_ids'  => $createdAttributeIds,
            'sort_order_band'        => FIRST_SORT_ORDER . '..' . (FIRST_SORT_ORDER + count(NEW_ATTRIBUTES) - 1),
        ]);

        bs_log('created_attributes', (string) count($createdAttributeIds));
        bs_log('product_attribute_rows_after', (string) bs_product_attribute_count($db, $prodAttr));
        bs_log('product_assignment', 'none — ocp5_product_attribute untouched by design; WP3/WP4 assign values');
        bs_log('display_order_note', 'new rows sort after the existing 13 — see the patch header');
        bs_log('next', 'clear OpenCart cache, confirm Admin -> Catalog -> Attributes shows 19 rows in «' . GROUP_NAME . '»');
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
