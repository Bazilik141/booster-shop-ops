<?php
declare(strict_types=1);

/**
 * CAT-002-5 + CAT-002-5b full rollback.
 *
 * Restores:
 * - catalog/view/template/common/home.twig
 * - catalog/view/template/common/header.twig
 * - catalog/view/stylesheet/boostershop-ds.css
 *
 * DB rollback is conditional:
 * - reads db-prechange.json from the original patch backup;
 * - deletes the Accessories rows only if the snapshot proves that the category
 *   did not exist before the original patch.
 *
 * Tables potentially deleted from:
 * - DB_PREFIX . category
 * - DB_PREFIX . category_description
 * - DB_PREFIX . category_to_store
 * - DB_PREFIX . seo_url
 */

const CATRB_PATCH_ID = 'cat002_5_5b_rollback_20260629';
const CATRB_SOURCE_PATCH = 'cat002_5_5b_tiles_burger_20260628';
const CATRB_HOME_MARKER = 'CAT-002-5 · Claude Design secondary tiles';
const CATRB_CSS_MARKER = 'CAT-002-5 · Claude Design secondary category tiles';
const CATRB_MENU_MARKER = 'CAT-002-5b · burger categories';

$args = $argv ?? [];
$dryRun = in_array('--dry-run', $args, true);
$root = rtrim((string)(getenv('BS_PATCH_ROOT') ?: (getcwd() ?: __DIR__)), "/\\");
$timestamp = date('Ymd-His');
$rollbackBackupDir = $root . '/_patch_backups/' . CATRB_PATCH_ID . '-' . $timestamp;
$files = [
    'home' => 'catalog/view/template/common/home.twig',
    'header' => 'catalog/view/template/common/header.twig',
    'css' => 'catalog/view/stylesheet/boostershop-ds.css',
];

function catrb_out(string $message): void
{
    echo $message . PHP_EOL;
}

function catrb_fail(string $message): never
{
    throw new RuntimeException($message);
}

function catrb_normalize(string $content): string
{
    return str_replace(["\r\n", "\r"], "\n", $content);
}

function catrb_read(string $path, string $label): string
{
    if (!is_file($path)) {
        catrb_fail("{$label}_not_found={$path}");
    }

    $content = file_get_contents($path);
    if (!is_string($content)) {
        catrb_fail("{$label}_read_failed={$path}");
    }

    return $content;
}

function catrb_query(mysqli $db, string $sql): mysqli_result
{
    $result = $db->query($sql);
    if (!$result instanceof mysqli_result) {
        catrb_fail('db_query_failed=' . $db->error);
    }

    return $result;
}

function catrb_rows(mysqli $db, string $sql): array
{
    $result = catrb_query($db, $sql);
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->free();

    return $rows;
}

function catrb_scalar(mysqli $db, string $sql): int
{
    $result = catrb_query($db, $sql);
    $row = $result->fetch_row();
    $result->free();

    return (int)($row[0] ?? 0);
}

function catrb_exec(mysqli $db, string $sql, string $label): int
{
    if (!$db->query($sql)) {
        catrb_fail("db_{$label}_failed=" . $db->error);
    }

    return $db->affected_rows;
}

function catrb_table_exists(mysqli $db, string $table): bool
{
    $escaped = $db->real_escape_string($table);

    return catrb_scalar(
        $db,
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$escaped}'"
    ) === 1;
}

function catrb_find_source_backup(string $root, array $files): string
{
    $pattern = $root . '/_patch_backups/' . CATRB_SOURCE_PATCH . '-*';
    $candidates = glob($pattern, GLOB_ONLYDIR) ?: [];
    usort(
        $candidates,
        static fn(string $a, string $b): int => (int)filemtime($b) <=> (int)filemtime($a)
    );

    foreach ($candidates as $candidate) {
        if (!is_file($candidate . '/db-prechange.json')) {
            continue;
        }

        $complete = true;
        foreach ($files as $relative) {
            if (!is_file($candidate . '/' . $relative)) {
                $complete = false;
                break;
            }
        }

        if ($complete) {
            return rtrim($candidate, "/\\");
        }
    }

    catrb_fail('original_backup_not_found_pattern=' . $pattern);
}

function catrb_backup_current_file(string $root, string $backupDir, string $relative): void
{
    $source = $root . '/' . $relative;
    $target = $backupDir . '/' . $relative;

    if (!is_dir(dirname($target))
        && !mkdir(dirname($target), 0775, true)
        && !is_dir(dirname($target))) {
        catrb_fail('rollback_backup_dir_create_failed=' . dirname($target));
    }
    if (!copy($source, $target)) {
        catrb_fail('rollback_backup_copy_failed=' . $relative);
    }

    catrb_out('backup=' . str_replace($root . '/', '', $target));
}

function catrb_atomic_restore(string $source, string $target): void
{
    $content = catrb_read($source, 'source_backup');
    $temporary = $target . '.catrb.tmp.' . getmypid();

    if (file_put_contents($temporary, $content, LOCK_EX) === false) {
        catrb_fail('restore_temporary_write_failed=' . $target);
    }
    if (!rename($temporary, $target)) {
        @unlink($temporary);
        catrb_fail('restore_atomic_replace_failed=' . $target);
    }
}

function catrb_restore_rollback_backups(string $root, string $backupDir, array $files): void
{
    foreach ($files as $relative) {
        $source = $backupDir . '/' . $relative;
        $target = $root . '/' . $relative;

        if (is_file($source)) {
            @copy($source, $target);
        }
    }
}

function catrb_file_markers(array $content): array
{
    return [
        'home' => substr_count($content['home'], CATRB_HOME_MARKER),
        'header' => substr_count($content['header'], CATRB_MENU_MARKER),
        'css' => substr_count($content['css'], CATRB_CSS_MARKER),
    ];
}

set_exception_handler(static function (Throwable $error): void {
    catrb_out('error=' . $error->getMessage());
    catrb_out('done=failed');
    exit(1);
});

catrb_out('patch=' . CATRB_PATCH_ID);
catrb_out('cwd=' . $root);
catrb_out('time=' . date('c'));
catrb_out('dry_run=' . ($dryRun ? 'yes' : 'no'));
catrb_out('scope=full_CAT-002-5_CAT-002-5b_rollback');

$lintOutput = [];
$lintCode = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $lintOutput, $lintCode);
if ($lintCode !== 0 || !str_contains(implode("\n", $lintOutput), 'No syntax errors detected')) {
    catrb_fail('php_lint_failed=' . implode(' | ', $lintOutput));
}
catrb_out('php_lint=ok');

$sourceBackupDir = catrb_find_source_backup($root, $files);
catrb_out('source_backup=' . str_replace($root . '/', '', $sourceBackupDir));

$snapshotRaw = catrb_read($sourceBackupDir . '/db-prechange.json', 'db_prechange_snapshot');
$snapshot = json_decode($snapshotRaw, true, 512, JSON_THROW_ON_ERROR);
if (!is_array($snapshot)
    || !isset($snapshot['prefix'], $snapshot['accessories_pre_state'])
    || !is_array($snapshot['accessories_pre_state'])) {
    catrb_fail('db_prechange_snapshot_invalid');
}

$source = [];
$current = [];
foreach ($files as $key => $relative) {
    $source[$key] = catrb_normalize(
        catrb_read($sourceBackupDir . '/' . $relative, 'source_' . $key)
    );
    $current[$key] = catrb_normalize(
        catrb_read($root . '/' . $relative, 'current_' . $key)
    );

    if (!$dryRun && !is_writable($root . '/' . $relative)) {
        catrb_fail('current_file_not_writable=' . $relative);
    }
}

$sourceMarkers = catrb_file_markers($source);
if (array_sum($sourceMarkers) !== 0) {
    catrb_fail('source_backup_contains_CAT002_markers');
}
if (substr_count($source['home'], 'class="bs-catcard"') !== 2
    || str_contains($source['home'], 'class="bs-subtile"')) {
    catrb_fail('source_home_shape_invalid');
}
foreach (['59', '59_61', '59_62', '59_64', '60', '60_63'] as $pathValue) {
    if (!str_contains(
        $source['header'],
        'href="index.php?route=product/category&path=' . $pathValue . '"'
    )) {
        catrb_fail('source_header_path_missing=' . $pathValue);
    }
}
catrb_out('assert=source_backup_shape:ok');

$currentMarkers = catrb_file_markers($current);
foreach ($currentMarkers as $key => $count) {
    if ($count > 1) {
        catrb_fail("current_{$key}_marker_count={$count}");
    }
}
$allPatched = array_sum($currentMarkers) === 3;
$noMarkers = array_sum($currentMarkers) === 0;
if (!$allPatched && !$noMarkers) {
    catrb_fail('current_files_partial_CAT002_state=' . json_encode($currentMarkers));
}

$configPath = $root . '/config.php';
if (!is_file($configPath)) {
    catrb_fail('config_not_found=' . $configPath);
}
require_once $configPath;
foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PREFIX'] as $constant) {
    if (!defined($constant)) {
        catrb_fail('db_constant_missing=' . $constant);
    }
}

$dbPort = defined('DB_PORT') ? (int)DB_PORT : 3306;
$db = @new mysqli(
    (string)DB_HOSTNAME,
    (string)DB_USERNAME,
    (string)DB_PASSWORD,
    (string)DB_DATABASE,
    $dbPort
);
if ($db->connect_errno) {
    catrb_fail('db_connect_failed=' . $db->connect_error);
}
if (!$db->set_charset('utf8mb4')) {
    catrb_fail('db_charset_failed=' . $db->error);
}

$prefix = (string)DB_PREFIX;
if (!preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
    catrb_fail('unsafe_db_prefix');
}
if ((string)$snapshot['prefix'] !== $prefix) {
    catrb_fail('db_prefix_mismatch_snapshot=' . (string)$snapshot['prefix'] . ',live=' . $prefix);
}

foreach (['category', 'category_description', 'category_to_store', 'seo_url'] as $suffix) {
    if (!catrb_table_exists($db, $prefix . $suffix)) {
        catrb_fail('db_table_missing=' . $prefix . $suffix);
    }
}

$preState = $snapshot['accessories_pre_state'];
$categoryExistedBefore = (bool)($preState['complete'] ?? false);
$categoryTable = $prefix . 'category';
$descriptionTable = $prefix . 'category_description';
$storeTable = $prefix . 'category_to_store';
$seoTable = $prefix . 'seo_url';

$accessoriesRows = catrb_rows(
    $db,
    "SELECT DISTINCT c.`category_id`
     FROM `{$categoryTable}` c
     INNER JOIN `{$descriptionTable}` cd ON cd.`category_id`=c.`category_id`
     INNER JOIN `{$seoTable}` su
       ON su.`key`='path' AND CAST(su.`value` AS UNSIGNED)=c.`category_id`
     WHERE cd.`name`='Аксесуари' AND su.`keyword`='accessories'"
);
if (count($accessoriesRows) > 1) {
    catrb_fail('db_multiple_accessories_categories=' . count($accessoriesRows));
}
$accessoriesId = isset($accessoriesRows[0]['category_id'])
    ? (int)$accessoriesRows[0]['category_id']
    : 0;

$deleteAccessories = !$categoryExistedBefore && $accessoriesId > 0;
if ($categoryExistedBefore && $accessoriesId === 0) {
    catrb_fail('db_preexisting_accessories_missing_refuse_rollback');
}

if ($deleteAccessories) {
    $categoryCount = catrb_scalar(
        $db,
        "SELECT COUNT(*) FROM `{$categoryTable}` WHERE `category_id`={$accessoriesId}"
    );
    $descriptionCount = catrb_scalar(
        $db,
        "SELECT COUNT(*) FROM `{$descriptionTable}`
         WHERE `category_id`={$accessoriesId} AND `name`='Аксесуари'"
    );
    $storeCount = catrb_scalar(
        $db,
        "SELECT COUNT(*) FROM `{$storeTable}`
         WHERE `category_id`={$accessoriesId} AND `store_id`=0"
    );
    $seoCount = catrb_scalar(
        $db,
        "SELECT COUNT(*) FROM `{$seoTable}`
         WHERE `key`='path' AND `value`='{$accessoriesId}' AND `keyword`='accessories'"
    );
    if ($categoryCount !== 1 || $descriptionCount < 1 || $storeCount !== 1 || $seoCount < 1) {
        catrb_fail(
            "db_accessories_shape_invalid=id:{$accessoriesId},category:{$categoryCount},"
            . "description:{$descriptionCount},store:{$storeCount},seo:{$seoCount}"
        );
    }

    foreach (['product_to_category', 'category_path', 'category_filter', 'category_to_layout'] as $suffix) {
        $table = $prefix . $suffix;
        if (!catrb_table_exists($db, $table)) {
            continue;
        }

        $column = $suffix === 'category_path' ? 'category_id' : 'category_id';
        $dependentCount = catrb_scalar(
            $db,
            "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}`={$accessoriesId}"
        );
        if ($dependentCount !== 0) {
            catrb_fail("db_dependent_rows_refuse_delete={$table}:count={$dependentCount}");
        }
    }
}

catrb_out('accessories_category_id=' . ($accessoriesId > 0 ? $accessoriesId : 'none'));
catrb_out('db_delete_accessories=' . ($deleteAccessories ? 'yes' : 'no'));
catrb_out('assert=db_rollback_preflight:ok');

$filesAlreadyRestored = $noMarkers
    && hash('sha256', $current['home']) === hash('sha256', $source['home'])
    && hash('sha256', $current['header']) === hash('sha256', $source['header'])
    && hash('sha256', $current['css']) === hash('sha256', $source['css']);
$dbAlreadyRestored = $categoryExistedBefore || $accessoriesId === 0;

if ($filesAlreadyRestored && $dbAlreadyRestored) {
    catrb_out('already_rolled_back=yes');
    catrb_out('done=ok');
    $db->close();
    @unlink(__FILE__);
    exit(0);
}

if (!$allPatched && !$filesAlreadyRestored) {
    catrb_fail('current_files_do_not_match_patched_or_original_state');
}

if ($dryRun) {
    catrb_out('would_restore_files=' . ($allPatched ? 'yes' : 'no'));
    catrb_out('would_delete_accessories=' . ($deleteAccessories ? 'yes' : 'no'));
    catrb_out('assert=dry_run:ok');
    catrb_out('done=ok');
    $db->close();
    exit(0);
}

$transaction = false;
$committed = false;

try {
    if (!is_dir($rollbackBackupDir)
        && !mkdir($rollbackBackupDir, 0775, true)
        && !is_dir($rollbackBackupDir)) {
        catrb_fail('rollback_backup_root_create_failed=' . $rollbackBackupDir);
    }

    foreach ($files as $relative) {
        catrb_backup_current_file($root, $rollbackBackupDir, $relative);
    }

    $dbBackup = [
        'captured_at' => date('c'),
        'source_backup' => str_replace($root . '/', '', $sourceBackupDir),
        'category_existed_before_original_patch' => $categoryExistedBefore,
        'accessories_category_id' => $accessoriesId,
        'category' => $accessoriesId > 0
            ? catrb_rows($db, "SELECT * FROM `{$categoryTable}` WHERE `category_id`={$accessoriesId}")
            : [],
        'category_description' => $accessoriesId > 0
            ? catrb_rows($db, "SELECT * FROM `{$descriptionTable}` WHERE `category_id`={$accessoriesId}")
            : [],
        'category_to_store' => $accessoriesId > 0
            ? catrb_rows($db, "SELECT * FROM `{$storeTable}` WHERE `category_id`={$accessoriesId}")
            : [],
        'seo_url' => $accessoriesId > 0
            ? catrb_rows(
                $db,
                "SELECT * FROM `{$seoTable}`
                 WHERE `key`='path' AND `value`='{$accessoriesId}' AND `keyword`='accessories'"
            )
            : [],
    ];
    $dbBackupJson = json_encode(
        $dbBackup,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if (!is_string($dbBackupJson)
        || file_put_contents(
            $rollbackBackupDir . '/db-pre-rollback.json',
            $dbBackupJson . PHP_EOL,
            LOCK_EX
        ) === false) {
        catrb_fail('db_pre_rollback_backup_write_failed');
    }
    catrb_out('backup=' . str_replace($root . '/', '', $rollbackBackupDir . '/db-pre-rollback.json'));
    catrb_out('assert=rollback_backup_before_changes:ok');

    if (!$db->begin_transaction()) {
        catrb_fail('db_transaction_begin_failed=' . $db->error);
    }
    $transaction = true;

    if ($deleteAccessories) {
        $seoDeleted = catrb_exec(
            $db,
            "DELETE FROM `{$seoTable}`
             WHERE `key`='path' AND `value`='{$accessoriesId}' AND `keyword`='accessories'",
            'delete_seo_url'
        );
        $storeDeleted = catrb_exec(
            $db,
            "DELETE FROM `{$storeTable}` WHERE `category_id`={$accessoriesId} AND `store_id`=0",
            'delete_category_to_store'
        );
        $descriptionDeleted = catrb_exec(
            $db,
            "DELETE FROM `{$descriptionTable}` WHERE `category_id`={$accessoriesId}",
            'delete_category_description'
        );
        $categoryDeleted = catrb_exec(
            $db,
            "DELETE FROM `{$categoryTable}` WHERE `category_id`={$accessoriesId}",
            'delete_category'
        );

        if ($seoDeleted < 1 || $storeDeleted !== 1 || $descriptionDeleted < 1 || $categoryDeleted !== 1) {
            catrb_fail(
                "db_delete_assert_failed=seo:{$seoDeleted},store:{$storeDeleted},"
                . "description:{$descriptionDeleted},category:{$categoryDeleted}"
            );
        }
        catrb_out('assert=db_accessories_deleted:ok');
    } else {
        catrb_out('changed=db_none');
    }

    if ($allPatched) {
        foreach ($files as $relative) {
            catrb_atomic_restore(
                $sourceBackupDir . '/' . $relative,
                $root . '/' . $relative
            );

            $restoredHash = hash_file('sha256', $root . '/' . $relative);
            $sourceHash = hash_file('sha256', $sourceBackupDir . '/' . $relative);
            if (!is_string($restoredHash)
                || !is_string($sourceHash)
                || !hash_equals($sourceHash, $restoredHash)) {
                catrb_fail('restored_file_hash_mismatch=' . $relative);
            }
            catrb_out('restored=' . $relative);
        }
        catrb_out('assert=files_restored_from_original_backup:ok');
    }

    $postContent = [];
    foreach ($files as $key => $relative) {
        $postContent[$key] = catrb_normalize(
            catrb_read($root . '/' . $relative, 'post_restore_' . $key)
        );
    }
    if (array_sum(catrb_file_markers($postContent)) !== 0) {
        catrb_fail('post_restore_CAT002_markers_remain');
    }

    if (!$db->commit()) {
        catrb_fail('db_commit_failed=' . $db->error);
    }
    $transaction = false;
    $committed = true;
    catrb_out('assert=db_commit:ok');

    if (!$categoryExistedBefore) {
        $remaining = catrb_scalar(
            $db,
            "SELECT COUNT(*) FROM `{$categoryTable}` WHERE `category_id`={$accessoriesId}"
        );
        if ($remaining !== 0) {
            catrb_fail('db_category_remains_after_commit=' . $accessoriesId);
        }
    }

    catrb_out('assert=rollback_final_state:ok');
    catrb_out('done=ok');
    $db->close();
    @unlink(__FILE__);
} catch (Throwable $error) {
    if ($transaction) {
        @$db->rollback();
    }
    if (!$committed && is_dir($rollbackBackupDir)) {
        catrb_restore_rollback_backups($root, $rollbackBackupDir, array_values($files));
    }

    catrb_out('rollback=' . ($committed ? 'not_attempted_after_commit' : 'db_transaction_and_current_file_backups'));
    catrb_out('error=' . $error->getMessage());
    catrb_out('done=failed');
    $db->close();
    exit(1);
}
