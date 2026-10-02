<?php
/**
 * BUG-003 WP1 — order-status stock fix (code + one setting row, atomic).
 *
 * Upload to ~/public_html and run with PHP CLI:  php BUG-003_order-status-stock-fix_20261002.php
 * Take a MySQL backup first (cPanel → Backup → Download a MySQL Database Backup).
 *
 * Owner approval for the DB change: chat 2026-10-02.
 *
 * Changes, and nothing else:
 *   1. catalog/model/checkout/order.php — the 5 occurrences of
 *        (array)$this->config->get('config_processing_status') + (array)$this->config->get('config_complete_status')
 *      become
 *        array_merge((array)$this->config->get('config_processing_status'), (array)$this->config->get('config_complete_status'))
 *      PHP `+` is a key union: with 5 processing and 4 complete statuses the complete list was
 *      discarded, so «Отримано» (5) left the stock-holding set and restocked every delivered order.
 *   2. <prefix>setting, store_id 0, code 'config', key 'config_complete_status':
 *        ["5","12","10","14"]  →  ["5","12"]
 *      Without this, fix 1 would turn «Помилка» (10) / «Протерміновано» (14) into stock-holding
 *      statuses. Both halves are applied together or not at all.
 *
 * Pre-checks (abort before any write): exactly 5 old anchors and 0 new ones in order.php; the setting
 * row exists once and equals exactly ["5","12","10","14"] with serialized=1.
 * Repeat run: 0 old anchors + 5 new + setting ["5","12"] → already_applied=yes, nothing written.
 * Any other combination is a mixed state → abort, nothing written.
 *
 * Rollback (both together, never one without the other):
 *   cp _patch_backups/BUG-003_order-status-stock-fix_20261002-<ts>/catalog/model/checkout/order.php catalog/model/checkout/order.php
 *   UPDATE `ocp5_setting` SET `value`='["5","12","10","14"]' WHERE `store_id`=0 AND `code`='config' AND `key`='config_complete_status';
 * The prefix above is the live one (config.php, 2026-09-24); the backup folder's rollback.sql uses the
 * prefix read at run time and the exact setting_id.
 */
declare(strict_types=1);

const BUG003_WP1_ID = 'BUG-003_order-status-stock-fix_20261002';
const BUG003_WP1_TARGET = 'catalog/model/checkout/order.php';
const BUG003_WP1_EXPECTED = 5;
const BUG003_WP1_OLD = "(array)\$this->config->get('config_processing_status') + (array)\$this->config->get('config_complete_status')";
const BUG003_WP1_NEW = "array_merge((array)\$this->config->get('config_processing_status'), (array)\$this->config->get('config_complete_status'))";
const BUG003_WP1_SETTING_OLD = '["5","12","10","14"]';
const BUG003_WP1_SETTING_NEW = '["5","12"]';

function bug003_note(string $message): void {
    echo $message . PHP_EOL;
}

function bug003_fail(string $message): void {
    fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL);
    exit(1);
}

function bug003_query(mysqli $db, string $sql, string $context) {
    $result = $db->query($sql);

    if ($result === false) {
        throw new RuntimeException('Database query failed at ' . $context . ' (errno ' . $db->errno . '). No credentials were printed.');
    }

    return $result;
}

function bug003_lint(string $file): bool {
    $output = [];
    $status = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $status);

    return $status === 0;
}

function bug003_write(string $path, string $contents): void {
    if (file_put_contents($path, $contents, LOCK_EX) !== strlen($contents)) {
        throw new RuntimeException('Cannot write ' . basename($path));
    }
}

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

mysqli_report(MYSQLI_REPORT_OFF);

$root = __DIR__;
$config = $root . '/config.php';
$target = $root . '/' . BUG003_WP1_TARGET;

bug003_note('patch=' . BUG003_WP1_ID);
bug003_note('cwd=' . $root);
bug003_note('time=' . date('c'));

if (!is_file($config)) {
    bug003_fail('config.php missing; run only from public_html.');
}
if (!is_file($target)) {
    bug003_fail(BUG003_WP1_TARGET . ' not found; no changes made.');
}
if (!is_executable(PHP_BINARY)) {
    bug003_fail('PHP_BINARY is not executable; the php -l gate cannot run. No changes made.');
}
if (!bug003_lint(__FILE__)) {
    bug003_fail('php -l failed on this patch file; no changes made.');
}

$original = file_get_contents($target);
if (!is_string($original)) {
    bug003_fail('Cannot read ' . BUG003_WP1_TARGET . '; no changes made.');
}

$oldCount = substr_count($original, BUG003_WP1_OLD);
$newCount = substr_count($original, BUG003_WP1_NEW);
$hashBefore = hash('sha256', $original);

bug003_note('target=' . BUG003_WP1_TARGET);
bug003_note('target_sha256_before=' . $hashBefore);
bug003_note('target_bytes_before=' . strlen($original));
bug003_note('anchor_old_count=' . $oldCount);
bug003_note('anchor_new_count=' . $newCount);

$codeApplied = ($oldCount === 0 && $newCount === BUG003_WP1_EXPECTED);
$codePending = ($oldCount === BUG003_WP1_EXPECTED && $newCount === 0);

if (!$codeApplied && !$codePending) {
    bug003_fail('Unexpected anchor counts (expected old=' . BUG003_WP1_EXPECTED . ',new=0 or old=0,new=' . BUG003_WP1_EXPECTED . '). No changes made.');
}

require $config;

foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PORT', 'DB_PREFIX'] as $constant) {
    if (!defined($constant)) {
        bug003_fail($constant . ' missing from config.php; no changes made.');
    }
}
if (!preg_match('/^[A-Za-z0-9_]+$/', (string)DB_PREFIX)) {
    bug003_fail('DB_PREFIX contains unexpected characters; no changes made.');
}

$settingTable = '`' . DB_PREFIX . 'setting`';
bug003_note('db_prefix=' . DB_PREFIX);

$db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
if ($db->connect_errno) {
    bug003_fail('Database connection failed. No credentials were printed. No changes made.');
}
if (!$db->set_charset('utf8mb4')) {
    $db->close();
    bug003_fail('Cannot set UTF-8 database connection; no changes made.');
}

$settingWritten = false;
$fileWritten = false;
$settingId = 0;
$backup = '';

try {
    $rows = [];
    $result = bug003_query(
        $db,
        'SELECT `setting_id`,`value`,`serialized` FROM ' . $settingTable . " WHERE `store_id`=0 AND `code`='config' AND `key`='config_complete_status'",
        'read_complete_status'
    );
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    if (count($rows) !== 1) {
        throw new RuntimeException('Expected exactly one config_complete_status row for store 0; found ' . count($rows) . '. No changes made.');
    }

    $settingId = (int)$rows[0]['setting_id'];
    $settingValue = (string)$rows[0]['value'];
    $settingSerialized = (int)$rows[0]['serialized'];

    $processing = bug003_query(
        $db,
        'SELECT `value` FROM ' . $settingTable . " WHERE `store_id`=0 AND `code`='config' AND `key`='config_processing_status'",
        'read_processing_status'
    )->fetch_assoc();

    bug003_note('config_processing_status=' . (string)($processing['value'] ?? '(missing)') . ' (not changed)');
    bug003_note('config_complete_status_current=' . $settingValue);

    if ($settingSerialized !== 1) {
        throw new RuntimeException('config_complete_status is not stored as serialized JSON; no changes made.');
    }

    if ($codeApplied) {
        if ($settingValue === BUG003_WP1_SETTING_NEW) {
            bug003_note('already_applied=yes');
            $db->close();
            @unlink(__FILE__);
            exit(0);
        }

        throw new RuntimeException('Mixed state: order.php is already fixed but config_complete_status is ' . $settingValue . '. No changes made; ask Claude before continuing.');
    }

    if ($settingValue !== BUG003_WP1_SETTING_OLD) {
        throw new RuntimeException('config_complete_status is ' . $settingValue . ', expected exactly ' . BUG003_WP1_SETTING_OLD . '. No changes made.');
    }

    $patched = str_replace(BUG003_WP1_OLD, BUG003_WP1_NEW, $original, $replaced);
    if ($replaced !== BUG003_WP1_EXPECTED || substr_count($patched, BUG003_WP1_OLD) !== 0 || substr_count($patched, BUG003_WP1_NEW) !== BUG003_WP1_EXPECTED) {
        throw new RuntimeException('In-memory replacement count mismatch; no changes made.');
    }

    // Backup: file + old setting + rollback SQL, before any write.
    $backup = $root . '/_patch_backups/' . BUG003_WP1_ID . '-' . date('Ymd-His');
    $backupTarget = $backup . '/' . BUG003_WP1_TARGET;
    if (!mkdir(dirname($backupTarget), 0755, true) && !is_dir(dirname($backupTarget))) {
        throw new RuntimeException('Cannot create backup directory; no changes made.');
    }
    if (!copy($target, $backupTarget) || hash_file('sha256', $backupTarget) !== $hashBefore) {
        throw new RuntimeException('Backup copy of order.php failed verification; no changes made.');
    }

    $settingBefore = json_encode([
        'setting_id' => $settingId,
        'store_id'   => 0,
        'code'       => 'config',
        'key'        => 'config_complete_status',
        'value'      => $settingValue,
        'serialized' => $settingSerialized,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    bug003_write($backup . '/setting.before.json', $settingBefore . PHP_EOL);

    $rollback = "-- BUG-003 WP1 rollback. Restore order.php from this folder AND run this SQL. Never one without the other.\n";
    $rollback .= "-- cp " . $backup . '/' . BUG003_WP1_TARGET . ' ' . $target . "\n";
    $rollback .= 'UPDATE ' . $settingTable . " SET `value`='" . BUG003_WP1_SETTING_OLD . "' WHERE `setting_id`=" . $settingId . " AND `store_id`=0 AND `code`='config' AND `key`='config_complete_status';\n";
    bug003_write($backup . '/rollback.sql', $rollback);
    bug003_write($backup . '/order.php.sha256-before', $hashBefore . PHP_EOL);

    // Lint the candidate before it touches the live file.
    $candidate = $backup . '/order.php.candidate';
    bug003_write($candidate, $patched);
    if (!bug003_lint($candidate)) {
        throw new RuntimeException('php -l failed on the patched candidate; no changes made.');
    }
    @unlink($candidate);

    // 1. Setting.
    $oldEsc = $db->real_escape_string(BUG003_WP1_SETTING_OLD);
    $newEsc = $db->real_escape_string(BUG003_WP1_SETTING_NEW);
    bug003_query(
        $db,
        'UPDATE ' . $settingTable . " SET `value`='" . $newEsc . "' WHERE `setting_id`=" . $settingId . " AND `store_id`=0 AND `code`='config' AND `key`='config_complete_status' AND `value`='" . $oldEsc . "'",
        'write_complete_status'
    );
    if ($db->affected_rows !== 1) {
        throw new RuntimeException('Setting UPDATE affected ' . $db->affected_rows . ' rows, expected 1.');
    }
    $settingWritten = true;

    $check = bug003_query($db, 'SELECT `value` FROM ' . $settingTable . ' WHERE `setting_id`=' . $settingId, 'verify_complete_status')->fetch_assoc();
    if ((string)($check['value'] ?? '') !== BUG003_WP1_SETTING_NEW) {
        throw new RuntimeException('Setting read-back mismatch after UPDATE.');
    }

    // 2. PHP file.
    $fileWritten = true;
    bug003_write($target, $patched);
    clearstatcache(true, $target);

    if (!bug003_lint($target)) {
        throw new RuntimeException('php -l failed on the live file after write.');
    }

    $after = file_get_contents($target);
    if (!is_string($after) || $after !== $patched) {
        throw new RuntimeException('Live file read-back mismatch after write.');
    }
    if (substr_count($after, BUG003_WP1_OLD) !== 0 || substr_count($after, BUG003_WP1_NEW) !== BUG003_WP1_EXPECTED) {
        throw new RuntimeException('Live file anchor counts wrong after write.');
    }

    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($target, true);
    }

    bug003_note('backup=' . $backup);
    bug003_note('occurrences_replaced=' . $replaced);
    bug003_note('target_sha256_after=' . hash('sha256', $after));
    bug003_note('target_bytes_after=' . strlen($after));
    bug003_note('config_complete_status_old=' . BUG003_WP1_SETTING_OLD);
    bug003_note('config_complete_status_new=' . BUG003_WP1_SETTING_NEW);
    bug003_note('php_lint=ok');
    bug003_note('done=ok');

    $db->close();
    @unlink(__FILE__);
} catch (Throwable $error) {
    if ($fileWritten && $backup !== '') {
        $restored = copy($backup . '/' . BUG003_WP1_TARGET, $target) && hash_file('sha256', $target) === $hashBefore;
        bug003_note('rollback_file=' . ($restored ? 'restored' : 'FAILED — restore manually from ' . $backup));
    }
    if ($settingWritten && $settingId > 0) {
        $ok = $db->query(
            'UPDATE ' . $settingTable . " SET `value`='" . $db->real_escape_string(BUG003_WP1_SETTING_OLD) . "' WHERE `setting_id`=" . $settingId . " AND `key`='config_complete_status'"
        );
        bug003_note('rollback_setting=' . ($ok !== false && $db->affected_rows === 1 ? 'restored' : 'FAILED — run rollback.sql from ' . $backup));
    }
    $db->close();
    bug003_fail('Patch stopped: ' . $error->getMessage());
}
