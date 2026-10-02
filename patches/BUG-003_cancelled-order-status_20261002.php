<?php
/**
 * BUG-003 WP2 — add the order status «Скасовано».
 *
 * Upload to ~/public_html and run with PHP CLI:  php BUG-003_cancelled-order-status_20261002.php
 * Run after WP1 (BUG-003_order-status-stock-fix_20261002.php).
 *
 * Owner approval: chat 2026-10-02 (phase 2 of BUG-003).
 *
 * Scope: one INSERT into `<prefix>order_status` for the single active language (expected
 * language_id 4, «Українська»). The new status is NOT added to config_processing_status or
 * config_complete_status, so an order moved into it returns its stock. No existing order, setting,
 * product or stock value is changed. The order-status file cache (DIR_CACHE/cache.order_status.*) is
 * cleared so the admin dropdown shows the new status at once — the same thing admin does on a status save.
 *
 * Pattern: patches/ORDER-STATUS-001_preorder_order_status_20260721.php (idempotent by name).
 *
 * Rollback: `_patch_backups/<this patch>-<timestamp>/rollback.sql` deletes only the new row by its
 * exact order_status_id, language_id and name. Do not run it once any order carries this status.
 */
declare(strict_types=1);

const BUG003_WP2_ID = 'BUG-003_cancelled-order-status_20261002';
const BUG003_WP2_NAME = 'Скасовано';
const BUG003_WP2_LANGUAGE_ID = 4;

function bug003_wp2_note(string $message): void {
    echo $message . PHP_EOL;
}

function bug003_wp2_fail(string $message): void {
    fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL);
    exit(1);
}

function bug003_wp2_write(string $path, string $contents): void {
    if (file_put_contents($path, $contents, LOCK_EX) !== strlen($contents)) {
        throw new RuntimeException('Cannot write backup artifact: ' . basename($path));
    }
}

function bug003_wp2_query(mysqli $db, string $sql, string $context) {
    $result = $db->query($sql);

    if ($result === false) {
        throw new RuntimeException('Database query failed at ' . $context . ' (errno ' . $db->errno . '). No credentials were printed.');
    }

    return $result;
}

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

mysqli_report(MYSQLI_REPORT_OFF);

$root = __DIR__;
$config = $root . '/config.php';

bug003_wp2_note('patch=' . BUG003_WP2_ID);
bug003_wp2_note('cwd=' . $root);
bug003_wp2_note('time=' . date('c'));

if (!is_file($config)) {
    bug003_wp2_fail('config.php missing; run only from public_html.');
}
if (!is_executable(PHP_BINARY)) {
    bug003_wp2_fail('PHP_BINARY is not executable; refusing database change.');
}

exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $lintOutput, $lintStatus);
if ($lintStatus !== 0) {
    bug003_wp2_fail('php -l gate failed before database change.');
}

require $config;

foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PORT', 'DB_PREFIX'] as $constant) {
    if (!defined($constant)) {
        bug003_wp2_fail($constant . ' missing from config.php.');
    }
}
if (!preg_match('/^[A-Za-z0-9_]+$/', (string)DB_PREFIX)) {
    bug003_wp2_fail('DB_PREFIX contains unexpected characters.');
}

$table = '`' . DB_PREFIX . 'order_status`';
$languageTable = '`' . DB_PREFIX . 'language`';
$settingTable = '`' . DB_PREFIX . 'setting`';

$db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
if ($db->connect_errno) {
    bug003_wp2_fail('Database connection failed. No credentials were printed.');
}
if (!$db->set_charset('utf8mb4')) {
    $db->close();
    bug003_wp2_fail('Cannot set UTF-8 database connection.');
}

$inserted = false;
$newStatusId = 0;
$languageId = 0;
$safeName = $db->real_escape_string(BUG003_WP2_NAME);

try {
    // Anchor pre-check: stock OC4 order_status shape.
    $columns = bug003_wp2_query($db, 'SHOW COLUMNS FROM ' . $table, 'order_status_columns');
    $columnNames = [];
    while ($row = $columns->fetch_assoc()) {
        $columnNames[] = (string)($row['Field'] ?? '');
    }
    sort($columnNames);
    if ($columnNames !== ['language_id', 'name', 'order_status_id']) {
        bug003_wp2_fail('Unexpected order_status table shape; no changes made.');
    }

    $languages = bug003_wp2_query(
        $db,
        'SELECT `language_id` FROM ' . $languageTable . ' WHERE `status`=1 ORDER BY `language_id` ASC',
        'active_languages'
    );
    $activeLanguageIds = [];
    while ($row = $languages->fetch_assoc()) {
        $activeLanguageIds[] = (int)$row['language_id'];
    }
    if ($activeLanguageIds !== [BUG003_WP2_LANGUAGE_ID]) {
        bug003_wp2_fail('Expected exactly one active language with id ' . BUG003_WP2_LANGUAGE_ID . '; found [' . implode(',', $activeLanguageIds) . ']. No changes made.');
    }
    $languageId = BUG003_WP2_LANGUAGE_ID;

    $existing = bug003_wp2_query(
        $db,
        'SELECT `order_status_id` FROM ' . $table . " WHERE `language_id`={$languageId} AND `name`='{$safeName}' ORDER BY `order_status_id` ASC",
        'existing_cancelled_status'
    );
    $existingRows = [];
    while ($row = $existing->fetch_assoc()) {
        $existingRows[] = (int)$row['order_status_id'];
    }
    if (count($existingRows) === 1) {
        bug003_wp2_note('already_applied=yes');
        bug003_wp2_note('order_status_id=' . $existingRows[0]);
        $db->close();
        @unlink(__FILE__);
        exit(0);
    }
    if (count($existingRows) > 1) {
        bug003_wp2_fail('Duplicate «' . BUG003_WP2_NAME . '» status rows detected; no changes made.');
    }

    $backup = $root . '/_patch_backups/' . BUG003_WP2_ID . '-' . date('Ymd-His');
    if (!mkdir($backup, 0755, true) && !is_dir($backup)) {
        bug003_wp2_fail('Cannot create patch backup directory.');
    }

    $before = bug003_wp2_query(
        $db,
        'SELECT `order_status_id`,`language_id`,`name` FROM ' . $table . ' ORDER BY `order_status_id`,`language_id`',
        'order_status_backup'
    );
    $beforeRows = [];
    while ($row = $before->fetch_assoc()) {
        $beforeRows[] = $row;
    }
    $beforeJson = json_encode($beforeRows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (!is_string($beforeJson)) {
        bug003_wp2_fail('Cannot encode order-status backup.');
    }
    bug003_wp2_write($backup . '/order_status.before.json', $beforeJson . PHP_EOL);

    bug003_wp2_query(
        $db,
        'INSERT INTO ' . $table . " (`language_id`,`name`) VALUES ({$languageId},'{$safeName}')",
        'insert_cancelled_status'
    );
    $newStatusId = (int)$db->insert_id;
    if ($newStatusId <= 0) {
        throw new RuntimeException('Database did not return the new order-status ID.');
    }
    $inserted = true;

    $verify = bug003_wp2_query(
        $db,
        'SELECT COUNT(*) AS `total` FROM ' . $table . " WHERE `order_status_id`={$newStatusId} AND `language_id`={$languageId} AND `name`='{$safeName}'",
        'verify_cancelled_status'
    )->fetch_assoc();
    if ((int)($verify['total'] ?? 0) !== 1) {
        throw new RuntimeException('Post-insert verification failed.');
    }

    // The new id must not be stock-holding.
    $lists = bug003_wp2_query(
        $db,
        'SELECT `key`,`value` FROM ' . $settingTable . " WHERE `store_id`=0 AND `code`='config' AND `key` IN ('config_processing_status','config_complete_status')",
        'read_status_lists'
    );
    while ($row = $lists->fetch_assoc()) {
        $ids = json_decode((string)$row['value'], true);
        if (is_array($ids) && in_array((string)$newStatusId, array_map('strval', $ids), true)) {
            throw new RuntimeException('New status id ' . $newStatusId . ' already appears in ' . $row['key'] . '.');
        }
        bug003_wp2_note($row['key'] . '=' . $row['value']);
    }

    $rollback = "-- BUG-003 WP2 rollback: run only before any order uses this status.\n";
    $rollback .= 'DELETE FROM ' . $table . " WHERE `order_status_id`={$newStatusId} AND `language_id`={$languageId} AND `name`='{$safeName}';\n";
    bug003_wp2_write($backup . '/rollback.sql', $rollback);

    // Clear the order-status file cache (file engine: cache.order_status.<hash>.<expire>).
    $cleared = 0;
    if (defined('DIR_CACHE') && is_dir(DIR_CACHE)) {
        foreach ((array)glob(rtrim(DIR_CACHE, '/') . '/cache.order_status.*') as $cacheFile) {
            if (is_string($cacheFile) && is_file($cacheFile) && @unlink($cacheFile)) {
                $cleared++;
            }
        }
    }

    bug003_wp2_note('backup=' . $backup);
    bug003_wp2_note('changed_table=' . DB_PREFIX . 'order_status');
    bug003_wp2_note('changed_row=order_status_id:' . $newStatusId . ',language_id:' . $languageId . ',name:' . BUG003_WP2_NAME);
    bug003_wp2_note('order_status_cache_files_cleared=' . $cleared);
    bug003_wp2_note('php_lint=ok');
    bug003_wp2_note('done=ok');
    $db->close();
    @unlink(__FILE__);
} catch (Throwable $error) {
    if ($inserted && $newStatusId > 0 && $languageId > 0) {
        $db->query('DELETE FROM ' . $table . " WHERE `order_status_id`={$newStatusId} AND `language_id`={$languageId} AND `name`='{$safeName}'");
        bug003_wp2_note('rollback=inserted_status_removed');
    }
    $db->close();
    bug003_wp2_fail('Patch stopped safely: ' . $error->getMessage());
}
