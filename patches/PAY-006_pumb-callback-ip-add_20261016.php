<?php
/**
 * PAY-006 — append PUMB callback source IP 3.123.129.140 to the TEST and PROD
 * callback IP allowlists. Existing entries (194.44.66.16-31) are kept byte-for-byte.
 *
 * Run from ~/public_html (CLI only): php PAY-006_pumb-callback-ip-add_20261016.php
 *
 * DB change (owner approval = the owner running this file): UPDATE of `value` in at
 * most two rows of <DB_PREFIX>setting, store_id=0, code='payment_pumb_credit':
 *   payment_pumb_credit_callback_ips        old value + ',3.123.129.140'
 *   payment_pumb_credit_test_callback_ips   old value + ',3.123.129.140'
 * No file of the site is changed. No other setting, table or row is touched.
 *
 * Refuses before any write: non-CLI run, wrong directory, DB prefix other than
 * 'ocp5_', missing setting table/columns, a target row missing, duplicated, outside
 * store_id=0/code=payment_pumb_credit, or serialized != 0. An EMPTY list (allow-all)
 * is never written; it is reported and the other list is handled independently.
 * Idempotency marker: the exact entry 3.123.129.140 inside each list.
 *
 * Backup: _patch_backups/PAY-006_pumb-callback-ip-add_20261016-<ts>/
 *   before.json (both rows, exact old values + SHA-256), rollback.sql (exact old
 *   values, hex literals), rollback.php (compare-and-swap restore, php -l checked).
 *
 * Rollback, preferred (from ~/public_html):
 *   php _patch_backups/PAY-006_pumb-callback-ip-add_20261016-<ts>/rollback.php
 * Rollback, SQL fallback (phpMyAdmin) — exact inverse of the append; it only touches
 * a list that still ends with the appended entry:
 *   UPDATE `ocp5_setting`
 *      SET `value` = LEFT(`value`, CHAR_LENGTH(`value`) - CHAR_LENGTH(',3.123.129.140'))
 *    WHERE `store_id` = 0 AND `code` = 'payment_pumb_credit'
 *      AND `key` IN ('payment_pumb_credit_callback_ips','payment_pumb_credit_test_callback_ips')
 *      AND `serialized` = 0
 *      AND RIGHT(`value`, CHAR_LENGTH(',3.123.129.140')) = ',3.123.129.140';
 * Settings cache: before any write the patch scans the live OpenCart settings files
 * (read-only) and prints settings_cache_scan[...] plus settings_cache_verdict. The
 * owner must read them; REVIEW_BEFORE_TRUSTING means a "cache" hit exists and must be
 * checked before trusting that the new value is live without a cache clear.
 *
 * PHP 8.0 compatible; mysqli without mysqlnd (no get_result(), no fetch_all()).
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-006_pumb-callback-ip-add_20261016';
const NEW_IP = '3.123.129.140';
const EXPECTED_PREFIX = 'ocp5_';
const SETTING_CODE = 'payment_pumb_credit';
const TARGET_KEYS = ['payment_pumb_credit_callback_ips', 'payment_pumb_credit_test_callback_ips'];
const CONTROLLER_REL = 'extension/pumb_credit/catalog/controller/payment/pumb_credit.php';
const CONTROLLER_SHA256 = '951dcf43a4f4c2712b1f1a124fbac08ed80b57879a5f9bc91ab5cd9bf80a41dd';
const SETTINGS_CACHE_SCAN = ['catalog/controller/startup/setting.php', 'catalog/model/setting/setting.php', 'admin/model/setting/setting.php'];

function out(string $line): void { echo $line . PHP_EOL; }
function fail(string $message): void { throw new RuntimeException($message); }
function need(bool $ok, string $message): void { if (!$ok) fail($message); }
function lint_php(string $file): bool {
    $lines = [];
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
    return $code === 0;
}
function list_entries(string $value): array { return array_map('trim', explode(',', $value)); }

/** All rows with this key anywhere in the table (any store, any code). */
function key_rows(mysqli $db, string $table, string $key): array {
    $stmt = $db->prepare('SELECT `setting_id`,`store_id`,`code`,`key`,`value`,`serialized` FROM `' . $table . '` WHERE `key`=? ORDER BY `setting_id`');
    need($stmt !== false, 'cannot prepare row read for ' . $key);
    $stmt->bind_param('s', $key);
    need($stmt->execute(), 'cannot read rows for ' . $key);
    $id = $store = $serialized = 0; $code = $rowKey = $value = '';
    $stmt->bind_result($id, $store, $code, $rowKey, $value, $serialized);
    $rows = [];
    while ($stmt->fetch()) {
        $rows[] = ['setting_id' => (int)$id, 'store_id' => (int)$store, 'code' => (string)$code, 'key' => (string)$rowKey, 'value' => (string)$value, 'serialized' => (int)$serialized];
    }
    $stmt->close();
    return $rows;
}

/** Compare-and-swap: write $new only while the stored value is still byte-identical to $old. */
function cas_update(mysqli $db, string $table, int $settingId, string $key, string $old, string $new): int {
    $stmt = $db->prepare('UPDATE `' . $table . '` SET `value`=? WHERE `setting_id`=? AND `store_id`=0 AND `code`=? AND `key`=? AND `serialized`=0 AND BINARY `value`=BINARY ?');
    need($stmt !== false, 'cannot prepare update for ' . $key);
    $code = SETTING_CODE;
    $stmt->bind_param('sisss', $new, $settingId, $code, $key, $old);
    need($stmt->execute(), 'update failed for ' . $key);
    $affected = (int)$stmt->affected_rows;
    $stmt->close();
    return $affected;
}

function rollback_php(string $table, array $plan): string {
    $items = [];
    foreach ($plan as $key => $p) {
        $items[] = "    ['key' => '" . $key . "', 'before' => base64_decode('" . base64_encode($p['before']) . "', true), 'after' => base64_decode('" . base64_encode($p['after']) . "', true)],";
    }
    $itemsCode = implode("\n", $items);
    return <<<PHP
<?php
// PAY-006 rollback: restores the exact pre-patch values. Run from ~/public_html.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
\$root = getcwd();
if (!is_string(\$root) || !is_file(\$root . '/config.php')) { fwrite(STDERR, "ERROR: run from public_html\\n"); exit(1); }
require_once \$root . '/config.php';
if (!defined('DB_PREFIX') || DB_PREFIX . 'setting' !== '{$table}') { fwrite(STDERR, "ERROR: table mismatch\\n"); exit(1); }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
\$items = [
{$itemsCode}
];
\$exit = 0;
try {
    \$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
    \$db->set_charset('utf8mb4');
    foreach (\$items as \$item) {
        \$stmt = \$db->prepare("SELECT `value` FROM `{$table}` WHERE `store_id`=0 AND `code`='payment_pumb_credit' AND `key`=? AND `serialized`=0");
        \$stmt->bind_param('s', \$item['key']); \$stmt->execute(); \$value = ''; \$stmt->bind_result(\$value);
        \$values = []; while (\$stmt->fetch()) \$values[] = (string)\$value; \$stmt->close();
        if (count(\$values) !== 1) { echo 'rollback[' . \$item['key'] . ']=refused_row_count_' . count(\$values) . PHP_EOL; \$exit = 1; continue; }
        if (\$values[0] === \$item['before']) { echo 'rollback[' . \$item['key'] . ']=already_original' . PHP_EOL; continue; }
        if (\$values[0] !== \$item['after']) { echo 'rollback[' . \$item['key'] . ']=refused_value_changed_since_patch' . PHP_EOL; \$exit = 1; continue; }
        \$stmt = \$db->prepare("UPDATE `{$table}` SET `value`=? WHERE `store_id`=0 AND `code`='payment_pumb_credit' AND `key`=? AND `serialized`=0 AND BINARY `value`=BINARY ?");
        \$stmt->bind_param('sss', \$item['before'], \$item['key'], \$item['after']); \$stmt->execute(); \$affected = \$stmt->affected_rows; \$stmt->close();
        echo 'rollback[' . \$item['key'] . ']=' . (\$affected === 1 ? 'restored' : 'failed') . PHP_EOL;
        echo 'value[' . \$item['key'] . ']=' . \$item['before'] . PHP_EOL;
        if (\$affected !== 1) \$exit = 1;
    }
} catch (Throwable \$e) { fwrite(STDERR, 'ERROR: ' . \$e->getMessage() . PHP_EOL); exit(1); }
echo 'rollback=' . (\$exit === 0 ? 'ok' : 'incomplete') . PHP_EOL;
exit(\$exit);
PHP;
}

$exitCode = 1;
$selfDelete = false;
try {
    need(PHP_SAPI === 'cli', 'CLI only');
    need(PHP_VERSION_ID >= 80000, 'PHP 8.0+ required');
    need(filter_var(NEW_IP, FILTER_VALIDATE_IP) !== false, 'NEW_IP is not a valid IP address');

    $root = getcwd();
    need(is_string($root) && is_file($root . '/config.php') && is_dir($root . '/catalog') && !is_dir($root . '/.git'), 'run the uploaded copy from public_html only (config.php/catalog missing)');
    require_once $root . '/config.php';
    foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PORT', 'DB_PREFIX'] as $constant) need(defined($constant), 'config.php does not define ' . $constant);
    need(DB_PREFIX === EXPECTED_PREFIX, 'DB_PREFIX is not the expected ' . EXPECTED_PREFIX);
    $table = DB_PREFIX . 'setting';

    $controller = $root . '/' . CONTROLLER_REL;
    need(is_file($controller), 'PUMB callback controller missing: ' . CONTROLLER_REL);
    $controllerSha = (string)hash_file('sha256', $controller);

    out('patch=' . PATCH_ID);
    out('cwd=' . $root);
    out('time=' . date(DATE_ATOM));
    out('php=' . PHP_VERSION);
    out('db_prefix=' . DB_PREFIX);
    out('new_ip=' . NEW_IP . ' valid=yes');
    out('controller_sha256=' . $controllerSha . ' expected_match=' . ($controllerSha === CONTROLLER_SHA256 ? 'yes' : 'no'));
    if ($controllerSha !== CONTROLLER_SHA256) {
        // Unknown controller version: the callback IP gate must still be recognisable.
        $controllerSource = (string)file_get_contents($controller);
        $anchorIpMatches = substr_count($controllerSource, 'function ipMatches');
        $anchorCallbackIps = substr_count($controllerSource, 'callback_ips');
        out('controller_anchor[function ipMatches]=' . $anchorIpMatches . ' controller_anchor[callback_ips]=' . $anchorCallbackIps);
        if ($anchorIpMatches !== 1 || $anchorCallbackIps < 1) {
            out('refused=controller_anchor_missing');
            out('settings_changed=no');
            out('done=refused');
            out('self_delete=skipped');
            exit(2);
        }
        out('WARNING controller_sha256_mismatch=yes');
    }

    // Read-only settings-cache scan of the live core files; informational, never changes done=/exit.
    $cacheHit = false;
    foreach (SETTINGS_CACHE_SCAN as $scanRel) {
        $scanFile = $root . '/' . $scanRel;
        $scanLines = null;
        if (is_file($scanFile)) $scanLines = @file($scanFile);
        if (!is_array($scanLines)) { out('settings_cache_scan[' . $scanRel . ']=missing'); continue; }
        $hits = [];
        foreach ($scanLines as $number => $line) if (stripos($line, 'cache') !== false) $hits[] = $number + 1;
        if ($hits) $cacheHit = true;
        out('settings_cache_scan[' . $scanRel . ']=' . ($hits ? 'found:' . implode(',', $hits) : 'none_found'));
    }
    out('settings_cache_verdict=' . ($cacheHit ? 'REVIEW_BEFORE_TRUSTING' : 'none_found'));

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
    $db->set_charset('utf8mb4');

    // Table and row layout are verified, not assumed.
    $tables = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
    need($tables instanceof mysqli_result && $tables->num_rows === 1, 'table missing: ' . $table);
    $tables->free();
    $columns = [];
    $result = $db->query('SHOW COLUMNS FROM `' . $table . '`');
    while ($row = $result->fetch_assoc()) $columns[(string)$row['Field']] = strtolower((string)$row['Type']);
    $result->free();
    foreach (['setting_id', 'store_id', 'code', 'key', 'value', 'serialized'] as $column) need(isset($columns[$column]), 'column missing: ' . $table . '.' . $column);
    $valueLimit = null;
    if (preg_match('/^varchar\((\d+)\)/', $columns['value'], $m)) $valueLimit = (int)$m[1];
    else need((bool)preg_match('/^(tiny|medium|long)?text\b/', $columns['value']), 'unexpected type of ' . $table . '.value: ' . $columns['value']);
    $engine = '';
    $stmt = $db->prepare('SELECT `ENGINE` FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA`=DATABASE() AND `TABLE_NAME`=?');
    $stmt->bind_param('s', $table); $stmt->execute(); $stmt->bind_result($engine); $stmt->fetch(); $stmt->close();
    out('table=' . $table . ' engine=' . (string)$engine . ' value_type=' . $columns['value']);

    // Preflight both rows completely before any write.
    $plan = [];
    foreach (TARGET_KEYS as $key) {
        $rows = key_rows($db, $table, $key);
        need(count($rows) > 0, 'row missing: ' . $key);
        need(count($rows) === 1, 'duplicate rows for ' . $key . ' (count=' . count($rows) . ')');
        $row = $rows[0];
        need($row['store_id'] === 0 && $row['code'] === SETTING_CODE, 'row ' . $key . ' is outside store_id=0/code=' . SETTING_CODE);
        need($row['serialized'] === 0, 'row ' . $key . ' is serialized=' . $row['serialized']);
        $before = $row['value'];
        $entries = list_entries($before);
        $invalid = 0;
        foreach ($entries as $entry) {
            if ($entry === '') continue;
            $address = strpos($entry, '/') === false ? $entry : substr($entry, 0, (int)strpos($entry, '/'));
            if (filter_var($address, FILTER_VALIDATE_IP) === false) $invalid++;
        }
        $item = ['setting_id' => $row['setting_id'], 'before' => $before, 'after' => $before, 'action' => '', 'entries' => count(array_filter($entries, 'strlen')), 'invalid' => $invalid];
        if (trim($before) === '') {
            $item['action'] = 'refused_empty_list';
        } elseif (in_array(NEW_IP, $entries, true)) {
            $item['action'] = 'already_present';
        } else {
            $item['action'] = 'append';
            $item['after'] = $before . ',' . NEW_IP;
            need($valueLimit === null || strlen($item['after']) <= $valueLimit, 'new value of ' . $key . ' exceeds ' . $table . '.value length');
        }
        $plan[$key] = $item;
        out('preflight[' . $key . ']=ok setting_id=' . $item['setting_id'] . ' entries=' . $item['entries'] . ' invalid_entries=' . $invalid . ' action=' . $item['action']);
    }

    $toWrite = array_filter($plan, static function (array $p): bool { return $p['action'] === 'append'; });
    $refused = array_keys(array_filter($plan, static function (array $p): bool { return $p['action'] === 'refused_empty_list'; }));

    if (!$toWrite) {
        foreach ($plan as $key => $p) out('value[' . $key . ']=' . $p['before']);
        if ($refused) {
            out('refused_lists=' . implode(',', $refused) . ' reason=empty_list_is_allow_all');
            out('settings_changed=no');
            out(count($refused) === count($plan) ? 'done=refused' : 'done=partial');
            out('self_delete=skipped');
            $exitCode = 2;
        } else {
            out('already_applied=yes');
            out('settings_changed=no');
            out('done=ok');
            $selfDelete = true;
            $exitCode = 0;
        }
    } else {
        // Backup before any write.
        $backup = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
        need(@mkdir($backup, 0750, true) || is_dir($backup), 'cannot create backup directory');
        $snapshot = [];
        $sql = "-- PAY-006 rollback: exact pre-patch values\n";
        foreach ($plan as $key => $p) {
            $snapshot[] = ['table' => $table, 'setting_id' => $p['setting_id'], 'store_id' => 0, 'code' => SETTING_CODE, 'key' => $key, 'serialized' => 0, 'value' => $p['before'], 'value_sha256' => hash('sha256', $p['before']), 'planned_action' => $p['action']];
            if ($p['action'] === 'append') {
                $sql .= 'UPDATE `' . $table . '` SET `value` = ' . ($p['before'] === '' ? "''" : '0x' . bin2hex($p['before']))
                    . " WHERE `store_id` = 0 AND `code` = '" . SETTING_CODE . "' AND `key` = '" . $key . "' AND `serialized` = 0 AND BINARY `value` = 0x" . bin2hex($p['after']) . ";\n";
            }
        }
        $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        need(is_string($json) && file_put_contents($backup . '/before.json', $json . "\n", LOCK_EX) !== false, 'cannot write before.json');
        need(file_put_contents($backup . '/rollback.sql', $sql, LOCK_EX) !== false, 'cannot write rollback.sql');
        need(file_put_contents($backup . '/rollback.php', rollback_php($table, $toWrite), LOCK_EX) !== false, 'cannot write rollback.php');
        need(lint_php($backup . '/rollback.php'), 'php -l failed for generated rollback.php; nothing written');
        out('php_l=ok file=' . substr($backup, strlen($root) + 1) . '/rollback.php');
        out('backup=' . substr($backup, strlen($root) + 1));

        // Write: compare-and-swap per list, verify by re-read, all-or-nothing.
        $written = [];
        $db->begin_transaction();
        try {
            foreach ($toWrite as $key => $p) {
                need(cas_update($db, $table, $p['setting_id'], $key, $p['before'], $p['after']) === 1, 'row ' . $key . ' changed after preflight; nothing kept');
                $written[$key] = $p;
            }
            foreach ($toWrite as $key => $p) {
                $rows = key_rows($db, $table, $key);
                need(count($rows) === 1 && $rows[0]['value'] === $p['after'], 'post-write verification failed for ' . $key);
            }
            $db->commit();
        } catch (Throwable $writeError) {
            try { $db->rollback(); } catch (Throwable $ignored) {}
            // Non-transactional engine safety net: undo anything that still holds the new value.
            foreach ($written as $key => $p) {
                $rows = key_rows($db, $table, $key);
                if (count($rows) === 1 && $rows[0]['value'] === $p['after']) cas_update($db, $table, $p['setting_id'], $key, $p['after'], $p['before']);
            }
            foreach ($toWrite as $key => $p) {
                $rows = key_rows($db, $table, $key);
                out('restored_check[' . $key . ']=' . (count($rows) === 1 && $rows[0]['value'] === $p['before'] ? 'original' : 'NOT_ORIGINAL'));
            }
            throw $writeError;
        }

        foreach ($plan as $key => $p) {
            out('before[' . $key . ']=' . $p['before']);
            out('after[' . $key . ']=' . $p['after']);
            out('result[' . $key . ']=' . ($p['action'] === 'append' ? 'appended old_part_identical=' . (strpos($p['after'], $p['before']) === 0 ? 'yes' : 'no') : $p['action']));
        }
        out('settings_changed=yes');
        if ($refused) {
            out('refused_lists=' . implode(',', $refused) . ' reason=empty_list_is_allow_all');
            out('done=partial');
            out('self_delete=skipped');
            $exitCode = 2;
        } else {
            out('done=ok');
            $selfDelete = true;
            $exitCode = 0;
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    out('done=failed');
    out('self_delete=skipped');
    exit(1);
}

if ($selfDelete) {
    $deleted = @unlink(__FILE__);
    out('self_delete=' . ($deleted ? 'ok' : 'failed'));
    if (!$deleted) $exitCode = 1;
}
exit($exitCode);
