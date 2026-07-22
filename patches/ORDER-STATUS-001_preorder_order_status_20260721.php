<?php
/**
 * ORDER-STATUS-001 — add the Ukrainian OpenCart order status "Передзамовлення".
 *
 * Upload to ~/public_html and run this file with PHP CLI.
 *
 * Owner approval: supplied in chat on 2026-07-21.
 *
 * Scope: one INSERT into `${DB_PREFIX}order_status` for the single active
 * Ukrainian language. No existing order is changed. No checkout, stock,
 * payment, CRM, fiscalization, or product rule is changed.
 *
 * Rollback: use `_patch_backups/<this patch>-<timestamp>/rollback.sql`.
 * It deletes only the newly-created status row by its exact ID, language ID,
 * and name. Do not run rollback after orders have been assigned this status.
 */
declare(strict_types=1);

const ORDER_STATUS001_PATCH_ID = 'ORDER-STATUS-001_preorder_order_status_20260721';
const ORDER_STATUS001_NAME = 'Передзамовлення';

function order_status001_note(string $message): void {
    echo $message . PHP_EOL;
}

function order_status001_fail(string $message): never {
    fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL);
    exit(1);
}

function order_status001_write(string $path, string $contents): void {
    if (file_put_contents($path, $contents, LOCK_EX) === false) {
        throw new RuntimeException('Cannot write backup artifact: ' . basename($path));
    }
}

function order_status001_query(mysqli $db, string $sql, string $context): mysqli_result|bool {
    $result = $db->query($sql);

    if ($result === false) {
        throw new RuntimeException('Database query failed at ' . $context . '. No credentials were printed.');
    }

    return $result;
}

$root = __DIR__;
$config = $root . DIRECTORY_SEPARATOR . 'config.php';
$timestamp = date('Ymd-His');
$backup = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . ORDER_STATUS001_PATCH_ID . '-' . $timestamp;

order_status001_note('patch=' . ORDER_STATUS001_PATCH_ID);
order_status001_note('cwd=' . $root);
order_status001_note('time=' . date('c'));

if (!is_file($config)) {
    order_status001_fail('config.php missing; run only from public_html.');
}

if (!is_executable(PHP_BINARY)) {
    order_status001_fail('PHP_BINARY is not executable; refusing database change.');
}

exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__), $lintOutput, $lintStatus);
if ($lintStatus !== 0) {
    order_status001_fail('php -l gate failed before database change.');
}

require $config;

foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PORT', 'DB_PREFIX'] as $constant) {
    if (!defined($constant)) {
        order_status001_fail($constant . ' missing from config.php.');
    }
}

if (!preg_match('/^[A-Za-z0-9_]+$/', (string)DB_PREFIX)) {
    order_status001_fail('DB_PREFIX contains unexpected characters.');
}

$table = '`' . DB_PREFIX . 'order_status`';
$languageTable = '`' . DB_PREFIX . 'language`';

$db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
if ($db->connect_errno) {
    order_status001_fail('Database connection failed. No credentials were printed.');
}

if (!$db->set_charset('utf8mb4')) {
    $db->close();
    order_status001_fail('Cannot set UTF-8 database connection.');
}

$inserted = false;
$newStatusId = 0;
$languageId = 0;

try {
    // Anchor pre-check: this patch is intentionally limited to the stock OC4
    // order-status shape recorded in the current cPanel backup.
    $columns = order_status001_query($db, 'SHOW COLUMNS FROM ' . $table, 'order_status_columns');
    $columnNames = [];
    while ($row = $columns->fetch_assoc()) {
        $columnNames[] = (string)($row['Field'] ?? '');
    }
    sort($columnNames);
    if ($columnNames !== ['language_id', 'name', 'order_status_id']) {
        order_status001_fail('Unexpected order_status table shape; no changes made.');
    }

    $languages = order_status001_query(
        $db,
        'SELECT `language_id` FROM ' . $languageTable . ' WHERE `status`=1 ORDER BY `language_id` ASC',
        'active_languages'
    );
    $activeLanguageIds = [];
    while ($row = $languages->fetch_assoc()) {
        $activeLanguageIds[] = (int)$row['language_id'];
    }
    if (count($activeLanguageIds) !== 1) {
        order_status001_fail('Expected exactly one active storefront language; found ' . count($activeLanguageIds) . '. No changes made.');
    }
    $languageId = $activeLanguageIds[0];

    $safeName = $db->real_escape_string(ORDER_STATUS001_NAME);
    $existing = order_status001_query(
        $db,
        'SELECT `order_status_id` FROM ' . $table . " WHERE `language_id`={$languageId} AND `name`='{$safeName}' ORDER BY `order_status_id` ASC",
        'existing_preorder_status'
    );
    $existingRows = [];
    while ($row = $existing->fetch_assoc()) {
        $existingRows[] = (int)$row['order_status_id'];
    }
    if (count($existingRows) === 1) {
        order_status001_note('already_applied=yes');
        order_status001_note('order_status_id=' . $existingRows[0]);
        $db->close();
        @unlink(__FILE__);
        exit(0);
    }
    if (count($existingRows) > 1) {
        order_status001_fail('Duplicate Передзамовлення status rows detected; no changes made.');
    }

    if (!mkdir($backup, 0755, true) && !is_dir($backup)) {
        order_status001_fail('Cannot create patch backup directory.');
    }
    if (!copy($config, $backup . DIRECTORY_SEPARATOR . 'config.php.before')) {
        order_status001_fail('Cannot back up config.php before database change.');
    }

    $before = order_status001_query(
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
        order_status001_fail('Cannot encode order-status backup.');
    }
    order_status001_write($backup . DIRECTORY_SEPARATOR . 'order_status.before.json', $beforeJson . PHP_EOL);

    order_status001_query(
        $db,
        'INSERT INTO ' . $table . " (`language_id`,`name`) VALUES ({$languageId},'{$safeName}')",
        'insert_preorder_status'
    );
    $newStatusId = (int)$db->insert_id;
    if ($newStatusId <= 0) {
        throw new RuntimeException('Database did not return the new order-status ID.');
    }
    $inserted = true;

    $verify = order_status001_query(
        $db,
        'SELECT COUNT(*) AS `total` FROM ' . $table . " WHERE `order_status_id`={$newStatusId} AND `language_id`={$languageId} AND `name`='{$safeName}'",
        'verify_preorder_status'
    )->fetch_assoc();
    if ((int)($verify['total'] ?? 0) !== 1) {
        throw new RuntimeException('Post-insert verification failed.');
    }

    $rollback = "-- ORDER-STATUS-001 rollback: run only before any order uses this status.\n";
    $rollback .= 'DELETE FROM ' . $table . " WHERE `order_status_id`={$newStatusId} AND `language_id`={$languageId} AND `name`='{$safeName}';\n";
    order_status001_write($backup . DIRECTORY_SEPARATOR . 'rollback.sql', $rollback);

    order_status001_note('backup=' . $backup);
    order_status001_note('changed_table=' . DB_PREFIX . 'order_status');
    order_status001_note('changed_row=order_status_id:' . $newStatusId . ',language_id:' . $languageId . ',name:' . ORDER_STATUS001_NAME);
    order_status001_note('php_l=ok');
    order_status001_note('done=ok');
    $db->close();
    @unlink(__FILE__);
} catch (Throwable $error) {
    if ($inserted && $newStatusId > 0 && $languageId > 0) {
        $safeName = $db->real_escape_string(ORDER_STATUS001_NAME);
        $db->query('DELETE FROM ' . $table . " WHERE `order_status_id`={$newStatusId} AND `language_id`={$languageId} AND `name`='{$safeName}'");
        order_status001_note('rollback=inserted_status_removed');
    }
    $db->close();
    order_status001_fail('Patch stopped safely: ' . $error->getMessage());
}
