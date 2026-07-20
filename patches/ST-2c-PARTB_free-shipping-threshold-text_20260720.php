<?php
/**
 * ST-2c Part B — free-shipping threshold text 1500 грн -> 2000 грн.
 *
 * Owner-approved DB scope from handoffs/handoff_ST-2c-PARTB_free-shipping-threshold-text-2000_20260720.md:
 *   - DB_PREFIX . product_description
 *   - DB_PREFIX . information_description (information_id=4 only)
 *
 * Explicitly excluded: all order tables, especially DB_PREFIX . order_history;
 * checkout, payment, Hutko, Checkbox, JSON-LD, sitemap and schema changes.
 * No ALTERs, inserts or deletes are made.
 *
 * Rollback: this runner writes the original target values to both
 * _patch_backups/ST-2c-PARTB_free-shipping-threshold-text_20260720-<timestamp>/db-before.json
 * and rollback.sql before its transaction starts. Restore with the generated
 * rollback.sql, then clear cache using the command supplied with this patch.
 *
 * Run from ~/public_html:
 *   php ST-2c-PARTB_free-shipping-threshold-text_20260720.php
 * Optional non-mutating preflight:
 *   php ST-2c-PARTB_free-shipping-threshold-text_20260720.php --dry-run
 */
declare(strict_types=1);

const ST2CPB_ID = 'ST-2c-PARTB_free-shipping-threshold-text_20260720';
const ST2CPB_SEARCH = '1500 грн';
const ST2CPB_REPLACE = '2000 грн';
const ST2CPB_INFORMATION_ID = 4;

function st2cpb_log(string $message): void {
    echo ST2CPB_ID . ': ' . $message . PHP_EOL;
}

function st2cpb_fail(string $message): never {
    st2cpb_log('ERROR: ' . $message);
    st2cpb_log('done=failed');
    exit(1);
}

function st2cpb_require_identifier(string $value, string $label): string {
    if ($value === '' || !preg_match('/^[A-Za-z0-9_]+$/', $value)) {
        st2cpb_fail('unsafe_identifier=' . $label);
    }
    return $value;
}

function st2cpb_query(mysqli $db, string $sql, string $label): mysqli_result|bool {
    $result = $db->query($sql);
    if ($result === false) {
        throw new RuntimeException($label . ': ' . $db->error);
    }
    return $result;
}

function st2cpb_table_exists(mysqli $db, string $table): bool {
    $escaped = $db->real_escape_string($table);
    $result = st2cpb_query($db, "SHOW TABLES LIKE '{$escaped}'", 'table_exists_query');
    return $result instanceof mysqli_result && $result->num_rows === 1;
}

/** @return list<string> */
function st2cpb_columns(mysqli $db, string $table): array {
    $result = st2cpb_query($db, "SHOW COLUMNS FROM `{$table}`", 'show_columns=' . $table);
    if (!$result instanceof mysqli_result) {
        throw new RuntimeException('show_columns_no_result=' . $table);
    }

    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = (string)$row['Field'];
    }
    return $columns;
}

/** @param list<string> $columns */
function st2cpb_require_columns(string $table, array $columns, array $required): void {
    $missing = array_values(array_diff($required, $columns));
    if ($missing !== []) {
        st2cpb_fail('required_columns_missing=' . $table . ':' . implode(',', $missing));
    }
}

/** @param list<string> $columns */
function st2cpb_where_for_phrase(mysqli $db, array $columns): string {
    $search = $db->real_escape_string(ST2CPB_SEARCH);
    return '(' . implode(' OR ', array_map(
        static fn(string $column): string => "`{$column}` LIKE '%{$search}%'",
        $columns
    )) . ')';
}

/** @param list<string> $columns */
function st2cpb_count_rows(mysqli $db, string $table, array $columns, string $extraWhere = ''): int {
    $where = st2cpb_where_for_phrase($db, $columns);
    if ($extraWhere !== '') {
        $where = '(' . $extraWhere . ') AND ' . $where;
    }
    $result = st2cpb_query($db, "SELECT COUNT(*) AS `count` FROM `{$table}` WHERE {$where}", 'count_rows=' . $table);
    if (!$result instanceof mysqli_result) {
        throw new RuntimeException('count_rows_no_result=' . $table);
    }
    return (int)$result->fetch_assoc()['count'];
}

function st2cpb_count_all_rows(mysqli $db, string $table): int {
    $result = st2cpb_query($db, "SELECT COUNT(*) AS `count` FROM `{$table}`", 'count_all_rows=' . $table);
    if (!$result instanceof mysqli_result) {
        throw new RuntimeException('count_all_rows_no_result=' . $table);
    }
    return (int)$result->fetch_assoc()['count'];
}

/** @return list<array<string,mixed>> */
function st2cpb_snapshot_rows(mysqli $db, string $table, array $keyColumns, array $textColumns, string $extraWhere = ''): array {
    $where = st2cpb_where_for_phrase($db, $textColumns);
    if ($extraWhere !== '') {
        $where = '(' . $extraWhere . ') AND ' . $where;
    }
    $columns = array_unique(array_merge($keyColumns, $textColumns));
    $select = implode(',', array_map(static fn(string $column): string => "`{$column}`", $columns));
    $order = implode(',', array_map(static fn(string $column): string => "`{$column}`", $keyColumns));
    $result = st2cpb_query($db, "SELECT {$select} FROM `{$table}` WHERE {$where} ORDER BY {$order}", 'snapshot_rows=' . $table);
    if (!$result instanceof mysqli_result) {
        throw new RuntimeException('snapshot_rows_no_result=' . $table);
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

/** @param list<string> $textColumns */
function st2cpb_update_phrase(mysqli $db, string $table, array $textColumns, string $extraWhere = ''): int {
    $search = $db->real_escape_string(ST2CPB_SEARCH);
    $replace = $db->real_escape_string(ST2CPB_REPLACE);
    $set = implode(',', array_map(
        static fn(string $column): string => "`{$column}`=REPLACE(`{$column}`,'{$search}','{$replace}')",
        $textColumns
    ));
    $where = st2cpb_where_for_phrase($db, $textColumns);
    if ($extraWhere !== '') {
        $where = '(' . $extraWhere . ') AND ' . $where;
    }
    st2cpb_query($db, "UPDATE `{$table}` SET {$set} WHERE {$where}", 'update_rows=' . $table);
    return $db->affected_rows;
}

/** @param list<array<string,mixed>> $rows */
function st2cpb_rollback_sql(mysqli $db, string $table, array $keyColumns, array $textColumns, array $rows): string {
    $sql = "-- Generated by " . ST2CPB_ID . " at " . gmdate('c') . "\n";
    $sql .= "START TRANSACTION;\n";
    foreach ($rows as $row) {
        $set = [];
        foreach ($textColumns as $column) {
            $value = $db->real_escape_string((string)$row[$column]);
            $set[] = "`{$column}`='{$value}'";
        }
        $where = [];
        foreach ($keyColumns as $column) {
            $where[] = "`{$column}`=" . (int)$row[$column];
        }
        $sql .= "UPDATE `{$table}` SET " . implode(',', $set) . ' WHERE ' . implode(' AND ', $where) . ";\n";
    }
    $sql .= "COMMIT;\n";
    return $sql;
}

function st2cpb_file_put(string $path, string $contents, string $label): void {
    if (file_put_contents($path, $contents, LOCK_EX) === false) {
        st2cpb_fail('backup_write_failed=' . $label);
    }
}

$root = getcwd();
$self = __FILE__;
$dryRun = in_array('--dry-run', $argv, true);

if (!is_file($root . DIRECTORY_SEPARATOR . 'config.php')) {
    st2cpb_fail('run_from_public_html_required=config.php_missing');
}
if (!extension_loaded('mysqli')) {
    st2cpb_fail('mysqli_extension_missing');
}

$lintOutput = [];
$lintCode = 1;
@exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($self) . ' 2>&1', $lintOutput, $lintCode);
if ($lintCode !== 0) {
    st2cpb_fail('php_l_failed=' . implode(' | ', $lintOutput));
}

require_once $root . DIRECTORY_SEPARATOR . 'config.php';
foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PREFIX'] as $constant) {
    if (!defined($constant)) {
        st2cpb_fail('config_constant_missing=' . $constant);
    }
}

$prefix = st2cpb_require_identifier((string)DB_PREFIX, 'DB_PREFIX');
$productTable = st2cpb_require_identifier($prefix . 'product_description', 'product_table');
$informationTable = st2cpb_require_identifier($prefix . 'information_description', 'information_table');
$historyTable = st2cpb_require_identifier($prefix . 'order_history', 'history_table');
$productKeys = ['product_id', 'language_id'];
$informationKeys = ['information_id', 'language_id'];
$productTargetColumns = ['name', 'description', 'meta_title', 'meta_description', 'meta_keyword'];
$informationTargetColumns = ['title', 'description', 'meta_title', 'meta_description', 'meta_keyword'];

mysqli_report(MYSQLI_REPORT_OFF);
$db = @new mysqli(
    (string)DB_HOSTNAME,
    (string)DB_USERNAME,
    (string)DB_PASSWORD,
    (string)DB_DATABASE,
    defined('DB_PORT') ? (int)DB_PORT : 3306
);
if ($db->connect_errno) {
    st2cpb_fail('db_connect_failed');
}
if (!$db->set_charset('utf8mb4')) {
    st2cpb_fail('db_charset_failed');
}

try {
    foreach ([$productTable, $informationTable, $historyTable] as $table) {
        if (!st2cpb_table_exists($db, $table)) {
            st2cpb_fail('required_table_missing=' . $table);
        }
    }

    $productColumns = st2cpb_columns($db, $productTable);
    $informationColumns = st2cpb_columns($db, $informationTable);
    $historyColumns = st2cpb_columns($db, $historyTable);
    st2cpb_require_columns($productTable, $productColumns, array_merge($productKeys, $productTargetColumns));
    st2cpb_require_columns($informationTable, $informationColumns, array_merge($informationKeys, $informationTargetColumns));
    st2cpb_require_columns($historyTable, $historyColumns, ['comment']);

    $rowCountProductBefore = st2cpb_count_all_rows($db, $productTable);
    $rowCountInformationBefore = st2cpb_count_all_rows($db, $informationTable);
    $historySearch = $db->real_escape_string('1500');
    $historyBeforeResult = st2cpb_query($db, "SELECT COUNT(*) AS `count` FROM `{$historyTable}` WHERE `comment` LIKE '%{$historySearch}%'", 'history_pre_count');
    $historyBefore = (int)($historyBeforeResult instanceof mysqli_result ? $historyBeforeResult->fetch_assoc()['count'] : 0);

    $productHitsBefore = st2cpb_count_rows($db, $productTable, $productTargetColumns);
    $informationHitsBefore = st2cpb_count_rows(
        $db,
        $informationTable,
        $informationTargetColumns,
        '`information_id`=' . ST2CPB_INFORMATION_ID
    );
    $totalHitsBefore = $productHitsBefore + $informationHitsBefore;

    st2cpb_log('php_l=ok');
    st2cpb_log('cwd=' . $root);
    st2cpb_log('mode=' . ($dryRun ? 'dry_run' : 'apply'));
    st2cpb_log('db_prefix=' . $prefix);
    st2cpb_log('pre_rows_product_description=' . $rowCountProductBefore);
    st2cpb_log('pre_rows_information_description=' . $rowCountInformationBefore);
    st2cpb_log('pre_hits_product_description=' . $productHitsBefore);
    st2cpb_log('pre_hits_information_description_id_4=' . $informationHitsBefore);
    st2cpb_log('pre_order_history_comment_1500=' . $historyBefore);

    if ($totalHitsBefore === 0) {
        st2cpb_log('already_applied=yes');
        st2cpb_log('done=ok');
        $db->close();
        @unlink($self);
        exit(0);
    }

    $productSnapshot = st2cpb_snapshot_rows($db, $productTable, $productKeys, $productTargetColumns);
    $informationSnapshot = st2cpb_snapshot_rows(
        $db,
        $informationTable,
        $informationKeys,
        $informationTargetColumns,
        '`information_id`=' . ST2CPB_INFORMATION_ID
    );
    if (count($productSnapshot) !== $productHitsBefore || count($informationSnapshot) !== $informationHitsBefore) {
        st2cpb_fail('anchor_count_changed_during_preflight');
    }

    if ($dryRun) {
        st2cpb_log('dry_run=yes no_db_write');
        st2cpb_log('done=ok');
        $db->close();
        exit(0);
    }

    $timestamp = gmdate('Ymd-His');
    $backupDir = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . ST2CPB_ID . '-' . $timestamp;
    if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
        st2cpb_fail('backup_directory_create_failed');
    }
    $snapshot = [
        'task' => ST2CPB_ID,
        'captured_at' => gmdate('c'),
        'search' => ST2CPB_SEARCH,
        'replace' => ST2CPB_REPLACE,
        'product_table' => $productTable,
        'information_table' => $informationTable,
        'information_id' => ST2CPB_INFORMATION_ID,
        'product_rows' => $productSnapshot,
        'information_rows' => $informationSnapshot,
    ];
    $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($snapshotJson)) {
        st2cpb_fail('snapshot_json_encode_failed');
    }
    st2cpb_file_put($backupDir . DIRECTORY_SEPARATOR . 'db-before.json', $snapshotJson . PHP_EOL, 'db-before.json');
    $rollbackSql = st2cpb_rollback_sql($db, $productTable, $productKeys, $productTargetColumns, $productSnapshot)
        . "\n"
        . st2cpb_rollback_sql($db, $informationTable, $informationKeys, $informationTargetColumns, $informationSnapshot);
    st2cpb_file_put($backupDir . DIRECTORY_SEPARATOR . 'rollback.sql', $rollbackSql, 'rollback.sql');
    st2cpb_log('backup_dir=' . $backupDir);

    if (!$db->begin_transaction()) {
        st2cpb_fail('transaction_begin_failed');
    }
    $inTransaction = true;
    try {
        $productAffected = st2cpb_update_phrase($db, $productTable, $productTargetColumns);
        $informationAffected = st2cpb_update_phrase(
            $db,
            $informationTable,
            $informationTargetColumns,
            '`information_id`=' . ST2CPB_INFORMATION_ID
        );

        $productHitsAfter = st2cpb_count_rows($db, $productTable, $productTargetColumns);
        $informationHitsAfter = st2cpb_count_rows(
            $db,
            $informationTable,
            $informationTargetColumns,
            '`information_id`=' . ST2CPB_INFORMATION_ID
        );
        $historyAfterResult = st2cpb_query($db, "SELECT COUNT(*) AS `count` FROM `{$historyTable}` WHERE `comment` LIKE '%{$historySearch}%'", 'history_post_count');
        $rowCountProductAfter = st2cpb_count_all_rows($db, $productTable);
        $rowCountInformationAfter = st2cpb_count_all_rows($db, $informationTable);
        $historyAfter = (int)($historyAfterResult instanceof mysqli_result ? $historyAfterResult->fetch_assoc()['count'] : -1);

        if ($productHitsAfter !== 0 || $informationHitsAfter !== 0) {
            throw new RuntimeException('postcheck_old_phrase_remaining');
        }
        if ($rowCountProductAfter !== $rowCountProductBefore || $rowCountInformationAfter !== $rowCountInformationBefore) {
            throw new RuntimeException('postcheck_row_count_changed');
        }
        if ($historyAfter !== $historyBefore) {
            throw new RuntimeException('postcheck_order_history_changed');
        }
        if (!$db->commit()) {
            throw new RuntimeException('transaction_commit_failed');
        }
        $inTransaction = false;

        st2cpb_log('updated_rows_product_description=' . $productAffected);
        st2cpb_log('updated_rows_information_description_id_4=' . $informationAffected);
        st2cpb_log('post_hits_product_description=0');
        st2cpb_log('post_hits_information_description_id_4=0');
        st2cpb_log('post_order_history_comment_1500=' . $historyAfter);
        st2cpb_log('post_row_counts=unchanged');
        st2cpb_log('rollback_sql=' . $backupDir . DIRECTORY_SEPARATOR . 'rollback.sql');
    } catch (Throwable $exception) {
        if ($inTransaction) {
            $db->rollback();
        }
        throw $exception;
    }

    $db->close();
    st2cpb_log('self_delete=' . (@unlink($self) ? 'yes' : 'failed_delete_manually'));
    st2cpb_log('done=ok');
} catch (Throwable $exception) {
    $db->close();
    st2cpb_fail('database_operation_failed=' . $exception->getMessage());
}
