<?php
/** ACC-006 visual WP1: «Обране» names, heart toggle state, notices, «Обране» page in the RD-16 shell.
 * Owner CLI only; cwd=public_html; runner staged outside the web root. PHP 8.0 compatible. No DB writes:
 * runtime admission reads table columns, theme overrides, theme settings and enabled events only.
 * Backup: cwd/_patch_backups/ACC-006-visual-WP1_20261028-<UTC>-<nonce>/ (original-<i> + manifest.json).
 * Modes: --check | --apply --admission=<sha256> | --rollback-check=<backup> | --rollback=<backup>.
 * Rollback preflights every target and backup hash before any restore. Template cache cleanup is the
 * owner packet's step after apply/rollback. Header DS/JS tokens are located by path prefix (AGENTS.md
 * convention 8) and replaced wholesale; the owner grants the token change per package.
 */
const A6_ID = 'ACC-006-visual-WP1_20261028';
const A6_CACHE = '/home2/boosters/ocartdata/storage/cache/template/';
const A6_HEADER = 'catalog/view/template/common/header.twig';
const A6_CSS = 'catalog/view/stylesheet/boostershop-ds.css';
const A6_CSS_MARKER = '/* ACC006_VISUAL_WP1_20261028: «Обране» page, shell count, wishlist toggle and notice.';
const A6_TOKEN_PATTERNS = [
    '~(catalog/view/stylesheet/boostershop-ds\.css\?v=)([A-Za-z0-9._-]{1,128})(?=["\x27])~',
    '~(catalog/view/javascript/booster-wishlist\.js\?v=)([A-Za-z0-9._-]{1,128})(?=["\x27])~',
];
ini_set('display_errors', '0');
ini_set('log_errors', '0');
set_error_handler(static function () { throw new RuntimeException('io_or_runtime_warning'); });

function a6_fail(string $code): void { throw new RuntimeException($code); }
function a6_json(string $encoded): array {
    $value = json_decode(base64_decode($encoded, true), true);
    if (!is_array($value)) a6_fail('embedded_contract_invalid');
    return $value;
}
function a6_targets(): array { return a6_json('__TARGETS__'); }
function a6_dependencies(): array { return a6_json('__DEPENDENCIES__'); }
function a6_reviewed_hooks(): array { return a6_json('__HOOKS__'); }
function a6_state(string $path): string {
    if (is_link($path)) a6_fail('linked_file');
    if (!file_exists($path)) return 'absent';
    if (!is_file($path)) a6_fail('not_regular_file');
    $hash = hash_file('sha256', $path);
    if (!is_string($hash)) a6_fail('hash_failed');
    return $hash;
}
function a6_walk(string $root, string $relative): string {
    if (!preg_match('~^[A-Za-z0-9_./-]+$~D', $relative) || in_array('..', explode('/', $relative), true)) a6_fail('unsafe_relative_path');
    $path = $root;
    foreach (explode('/', $relative) as $part) {
        $path .= '/' . $part;
        if (is_link($path)) a6_fail('linked_path_component');
    }
    return $path;
}
/** Both asset tokens become placeholders; each must occur exactly once. */
function a6_header_normalize(string $body): string {
    foreach (A6_TOKEN_PATTERNS as $i => $pattern) {
        $body = preg_replace_callback($pattern, static function ($m) use ($i) { return $m[1] . 'A6_TOKEN_' . $i; }, $body, -1, $count);
        if ($count !== 1 || !is_string($body)) a6_fail('header_token_anchor');
    }
    return $body;
}
function a6_css_applied(string $body, string $block): bool { return substr_count($body, A6_CSS_MARKER) === 1 && substr_count($body, $block) === 1; }
function a6_lint(string $path): void {
    if (substr($path, -4) !== '.php') return;
    $lines = []; $status = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $lines, $status);
    if ($status !== 0) a6_fail('php_lint_failed');
}
function a6_write_new(string $path, string $body, int $mode = 0600): void {
    $stream = fopen($path, 'xb');
    if (!$stream) a6_fail('exclusive_create_failed');
    try {
        $offset = 0; $size = strlen($body);
        while ($offset < $size) {
            $written = fwrite($stream, substr($body, $offset));
            if (!$written) a6_fail('write_failed');
            $offset += $written;
        }
        if (!fflush($stream)) a6_fail('flush_failed');
    } finally { fclose($stream); }
    if (!chmod($path, $mode)) a6_fail('chmod_failed');
}
function a6_permissions(string $path, array $row): void {
    if (!chmod($path, $row['mode'])) a6_fail('restore_mode_failed');
    if (PHP_OS_FAMILY !== 'Windows') {
        if ($row['uid'] !== null && fileowner($path) !== $row['uid'] && !chown($path, $row['uid'])) a6_fail('restore_owner_failed');
        if ($row['gid'] !== null && filegroup($path) !== $row['gid'] && !chgrp($path, $row['gid'])) a6_fail('restore_group_failed');
    }
}

/** Enabled events that can reach the changed views or controllers, matched conservatively:
 * full wildcard match OR prefix wildcard match, after stripping catalog/system namespaces.
 * Every relevant pair must be one of the reviewed hooks, and the relevant multiset must equal them. */
function a6_event_relevant(string $pattern): bool {
    do {
        $previous = $pattern;
        $pattern = preg_replace('~^(?:catalog|system)(?:/|$)~', '', $pattern);
    } while ($pattern !== $previous);
    foreach (__TRIGGERS__ as $trigger) {
        if (fnmatch($pattern, $trigger) || fnmatch($pattern . '*', $trigger)) return true;
    }
    return false;
}

function a6_hook_templates(string $root, array $rows, ?string $modification = null): void {
    $root = str_replace('\\', '/', $root);
    if (count($rows) > 1024) a6_fail('hook_theme_settings_cap');
    $stores = [0 => []];
    foreach ($rows as $row) {
        if (count($row) !== 4 || (!is_string($row[0]) && !is_int($row[0])) || (string)(int)$row[0] !== (string)$row[0] || (int)$row[0] < 0 || (int)$row[0] > 2147483647) a6_fail('hook_theme_store_invalid');
        [$store, $key, $value, $serialized] = $row; $store = (int)$store;
        if ((string)$serialized !== '0') a6_fail('serialized_hook_theme_setting');
        if (!in_array($key, ['config_theme', 'theme_default_directory'], true) || (!is_string($value) && $value !== null) || strlen((string)$value) > 256) a6_fail('hook_theme_setting_invalid');
        if (isset($stores[$store]) && array_key_exists($key, $stores[$store])) a6_fail('duplicate_hook_theme_setting');
        $stores[$store][$key] = (string)$value;
    }
    foreach ($stores as $values) {
        $effective = array_replace($stores[0], $values);
        $theme = ($effective['config_theme'] ?? '') === 'default' ? ($effective['theme_default_directory'] ?? '') : ($effective['config_theme'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_-]{0,64}$/D', $theme)) a6_fail('unsafe_hook_theme_segment');
        // Mirror vendor lookup, with store-N values overlaid on store 0: no alternate copy may shadow a target.
        $base = 'catalog/view/template/';
        foreach (array_unique([$base . ($theme === '' ? '' : $theme . '/') . 'template/', $base . 'default/template/', $base]) as $prefix) {
            foreach (__VIEWS__ as $view) {
                $path = a6_walk($root, $prefix . $view . '.twig');
                if ($modification !== null && strpos($path, str_replace('\\', '/', $modification)) === 0) a6_fail('hook_modification_redirect');
                if ($prefix !== $base && a6_state($path) !== 'absent') a6_fail('alternate_hook_template');
            }
        }
    }
}

function a6_runtime(string $root, bool $admission = true): array {
    ob_start();
    try {
        if (is_link($root . '/config.php') || !is_file($root . '/config.php')) a6_fail('config_unavailable');
        require_once $root . '/config.php';
        foreach (['DIR_OPENCART', 'DIR_APPLICATION', 'DIR_SYSTEM', 'DIR_TEMPLATE', 'DIR_EXTENSION', 'DIR_CACHE', 'DIR_IMAGE'] as $key) if (!defined($key)) a6_fail('runtime_constant_missing');
        if (rtrim(str_replace('\\', '/', DIR_OPENCART), '/') !== $root || rtrim(str_replace('\\', '/', DIR_APPLICATION), '/') !== $root . '/catalog' || rtrim(str_replace('\\', '/', DIR_EXTENSION), '/') !== $root . '/extension') a6_fail('runtime_root_mapping_changed');
        if (DIR_CACHE . 'template/' !== A6_CACHE || !is_dir(A6_CACHE) || is_link(rtrim(A6_CACHE, '/')) || realpath(A6_CACHE) !== rtrim(A6_CACHE, '/')) a6_fail('template_cache_mapping_changed');
        if (rtrim(str_replace('\\', '/', DIR_SYSTEM), '/') !== $root . '/system' || str_replace('\\', '/', DIR_TEMPLATE) !== $root . '/catalog/view/template/' || defined('DIR_CATALOG')) a6_fail('hook_runtime_mapping_changed');
        if (str_replace('\\', '/', DIR_IMAGE) !== $root . '/image/' || is_link(rtrim(DIR_IMAGE, '/')) || realpath(DIR_IMAGE) !== $root . '/image') a6_fail('image_runtime_mapping_changed');
        $facts = ['root' => $root, 'php' => PHP_VERSION, 'cache' => A6_CACHE];
        if ($admission) {
            if (!preg_match('/^[A-Za-z0-9_]+$/D', DB_PREFIX)) a6_fail('db_prefix_invalid');
            mysqli_report(MYSQLI_REPORT_OFF);
            $db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int) DB_PORT);
            if ($db->connect_errno || !$db->set_charset('utf8mb4')) a6_fail('metadata_unavailable');
            foreach (['theme' => ['route', 'status'], 'event' => ['trigger', 'action', 'status'], 'setting' => ['store_id', 'key', 'value', 'serialized'], 'customer_wishlist' => ['customer_id', 'product_id', 'store_id']] as $table => $required) {
                $q = $db->query('SHOW COLUMNS FROM `' . DB_PREFIX . $table . '`');
                if (!$q) a6_fail('metadata_schema_unavailable');
                $columns = [];
                while ($row = $q->fetch_assoc()) $columns[] = $row['Field'];
                $q->free();
                if (array_diff($required, $columns)) a6_fail('metadata_schema_changed');
            }
            if (!$db->query('START TRANSACTION READ ONLY')) a6_fail('readonly_transaction_unavailable');
            $q = $db->query("SELECT `store_id`,`key`,LEFT(`value`,257) AS `value`,`serialized` FROM `" . DB_PREFIX . "setting` WHERE `key` IN ('config_theme','theme_default_directory') ORDER BY `store_id`,`key` LIMIT 1025");
            if (!$q) a6_fail('hook_theme_settings_unavailable');
            $hookSettings = [];
            while ($setting = $q->fetch_assoc()) {
                if (count($hookSettings) >= 1024) a6_fail('hook_theme_settings_cap');
                $hookSettings[] = [(string)$setting['store_id'], (string)$setting['key'], $setting['value'], (string)$setting['serialized']];
            }
            $q->free();
            a6_hook_templates($root, $hookSettings, defined('DIR_MODIFICATION') ? (string)DIR_MODIFICATION : null);
            $facts['hook_theme_settings_sha256'] = hash('sha256', json_encode($hookSettings));
            $statement = $db->prepare('SELECT COUNT(*) FROM `' . DB_PREFIX . 'theme` WHERE `route` = ?');
            $route = '';
            if (!$statement || !$statement->bind_param('s', $route)) a6_fail('theme_check_unavailable');
            foreach (__VIEWS__ as $route) {
                if (!$statement->execute() || !$statement->bind_result($count) || !$statement->fetch()) a6_fail('theme_check_unavailable');
                if ((int)$count !== 0) a6_fail('active_target_theme_override');
                $statement->free_result();
            }
            $statement->close();
            $q = $db->query('SELECT `trigger`,`action` FROM `' . DB_PREFIX . 'event` WHERE `status` = 1 ORDER BY `trigger`,`action` LIMIT 1025');
            if (!$q) a6_fail('event_check_unavailable');
            $hooks = []; $total = 0;
            while ($event = $q->fetch_assoc()) {
                if (++$total > 1024) a6_fail('event_metadata_cap');
                if (a6_event_relevant((string)$event['trigger'])) $hooks[] = (string)$event['trigger'] . ':' . str_replace('|', '.', (string)$event['action']);
            }
            $q->free();
            if (!$db->rollback()) a6_fail('readonly_transaction_close_failed');
            $db->close();
            $expected = a6_reviewed_hooks();
            sort($hooks, SORT_STRING); sort($expected, SORT_STRING);
            if ($hooks !== $expected) {
                // Non-secret metadata only: the relevant trigger/action pairs, so the review can see the difference.
                ob_end_clean();
                foreach ($hooks as $hook) echo 'relevant_hook=' . preg_replace('~[^A-Za-z0-9_./:*|-]~', '?', $hook) . "\n";
                ob_start();
                a6_fail('reviewed_hook_set_changed');
            }
            $facts['admitted_hook_sha256'] = hash('sha256', json_encode($hooks));
            $facts['target_theme_overrides'] = 0;
        }
        ob_end_clean();
        return $facts;
    } catch (Throwable $error) {
        while (ob_get_level()) ob_end_clean();
        if ($error instanceof RuntimeException && preg_match('/^[a-z_]+$/D', $error->getMessage())) throw $error;
        a6_fail('runtime_admission_failed');
    }
}

function a6_rollback_plan(string $root, string $backup, array $targets): array {
    $prefix = $root . '/_patch_backups/' . A6_ID . '-';
    if (strpos($backup, $prefix) !== 0 || !preg_match('/^[0-9]{8}T[0-9]{6}Z-[a-f0-9]{12}$/D', substr($backup, strlen($prefix))) || str_replace('\\', '/', realpath($backup) ?: '') !== $backup) a6_fail('unsafe_backup_path');
    a6_walk($root, substr($backup, strlen($root) + 1));
    $manifest = $backup . '/manifest.json';
    if (a6_state($manifest) === 'absent') a6_fail('backup_manifest_missing');
    $data = json_decode(file_get_contents($manifest), true);
    if (!is_array($data) || ($data['task'] ?? '') !== A6_ID || ($data['root'] ?? '') !== $root || count($data['targets'] ?? []) !== count($targets)) a6_fail('backup_manifest_invalid');
    $plan = [];
    foreach ($targets as $i => $contract) {
        $row = $data['targets'][$i];
        if (($row['path'] ?? '') !== $contract['path'] || ($row['after'] ?? '') !== $contract['after']) a6_fail('backup_contract_mismatch');
        if ($contract['path'] !== A6_HEADER && ($row['before'] ?? '') !== $contract['before']) a6_fail('backup_before_contract_mismatch');
        $path = a6_walk($root, $row['path']); $current = a6_state($path);
        if ($current !== $row['before'] && $current !== $row['after']) a6_fail('rollback_target_drift');
        $copy = $backup . '/original-' . $i;
        if (a6_state($copy) !== $row['before']) a6_fail('backup_hash_mismatch');
        if ($row['path'] === A6_HEADER && hash('sha256', a6_header_normalize(file_get_contents($copy))) !== $contract['normalized_before']) a6_fail('header_backup_contract_mismatch');
        $row['current'] = $current; $plan[] = $row;
    }
    return $plan;
}

$a6_lock = null; $a6_lockOwned = false; $a6_stage = [];
try {
    if (PHP_SAPI !== 'cli' || PHP_MAJOR_VERSION !== 8) a6_fail('php_cli_8_required');
    $root = realpath(getcwd());
    if (!is_string($root) || !is_dir($root . '/catalog')) a6_fail('cwd_not_opencart');
    $root = str_replace('\\', '/', $root);
    $stageRoot = str_replace('\\', '/', realpath(__DIR__));
    if ($stageRoot === $root || strpos($stageRoot . '/', $root . '/') === 0) a6_fail('runner_must_be_private');
    if (stat($stageRoot)['dev'] !== stat($root)['dev']) a6_fail('stage_filesystem_differs');
    $mode = $argv[1] ?? '';
    if (!in_array($mode, ['--check', '--apply'], true) && strpos($mode, '--rollback=') !== 0 && strpos($mode, '--rollback-check=') !== 0) a6_fail('explicit_mode_required');
    $targets = a6_targets();
    foreach ($targets as $target) a6_walk($root, $target['path']);
    // Complete no-op before admission: a later task may change a dependency or a token.
    $applied = true;
    foreach ($targets as $target) {
        $path = $root . '/' . $target['path'];
        $state = a6_state($path);
        if ($state === 'absent') { $applied = false; continue; }
        if ($target['path'] === A6_HEADER) {
            if (hash('sha256', a6_header_normalize(file_get_contents($path))) !== $target['normalized_after']) $applied = false;
        } elseif ($target['path'] === A6_CSS) {
            if (!a6_css_applied(file_get_contents($path), base64_decode($target['block'], true))) $applied = false;
        } elseif ($state !== $target['after']) $applied = false;
    }
    if ($applied && in_array($mode, ['--check', '--apply'], true)) { echo "already_applied=yes\n"; exit(0); }
    $a6_lock = $stageRoot . '/' . A6_ID . '.lock';
    a6_write_new($a6_lock, (string)getmypid());
    $a6_lockOwned = true;
    $runtime = a6_runtime($root, strpos($mode, '--rollback') !== 0);
    if (strpos($mode, '--rollback') === 0) {
        $backup = substr($mode, strpos($mode, '=') + 1);
        $plan = a6_rollback_plan($root, $backup, $targets);
        echo 'backup=' . $backup . "\nrollback_preflight=ok\n";
        if (strpos($mode, '--rollback-check=') === 0) { echo "done=ok\n"; }
        else {
            // Prepare and lint every restore candidate before any target is modified.
            foreach ($plan as $i => $row) {
                if ($row['current'] === $row['before']) continue;
                $candidate = $stageRoot . '/A6-restore-' . bin2hex(random_bytes(6)) . (substr($row['path'], -4) === '.php' ? '.php' : '.tmp');
                a6_write_new($candidate, file_get_contents($backup . '/original-' . $i));
                a6_permissions($candidate, $row); a6_lint($candidate); $a6_stage[$i] = $candidate;
            }
            // Recheck all live and backup states immediately before the first rollback write.
            $secondPlan = a6_rollback_plan($root, $backup, $targets);
            foreach ($secondPlan as $i => $row) {
                if ($row['current'] !== $plan[$i]['current']) a6_fail('rollback_state_changed_during_preflight');
                if (isset($a6_stage[$i]) && a6_state($a6_stage[$i]) !== $row['before']) a6_fail('restore_candidate_hash_changed');
            }
            $plan = $secondPlan;
            // Reverse of the apply order: the header (asset tokens) first, languages last.
            foreach (array_reverse(__ORDER__) as $i) {
                $row = $plan[$i];
                if ($row['current'] === $row['before']) continue;
                $path = $root . '/' . $row['path'];
                if (!rename($a6_stage[$i], $path)) a6_fail('restore_rename_failed');
                unset($a6_stage[$i]);
                if (a6_state($path) !== $row['before']) a6_fail('restore_hash_failed');
            }
            echo "rollback=ok\nchanged_files=" . count(array_filter($plan, static function ($row) { return $row['current'] !== $row['before']; })) . "\n";
            if (!unlink(__FILE__)) a6_fail('self_delete_failed');
            echo "self_deleted=yes\ndone=ok\n";
        }
    } else {
        $rows = []; $payloads = [];
        foreach ($targets as $i => $target) {
            $path = $root . '/' . $target['path']; $actual = a6_state($path);
            if ($actual === 'absent') a6_fail('target_missing');
            if ($target['path'] === A6_HEADER) {
                if (hash('sha256', a6_header_normalize(file_get_contents($path))) !== $target['normalized_before']) a6_fail('header_non_token_drift');
            } elseif ($actual !== $target['before']) a6_fail('target_before_mismatch');
            $new = base64_decode($target['payload'], true);
            if (!is_string($new) || hash('sha256', $new) !== $target['after']) a6_fail('payload_hash_mismatch');
            $rows[] = ['path' => $target['path'], 'before' => $actual, 'after' => $target['after'], 'mode' => fileperms($path) & 0777, 'uid' => fileowner($path), 'gid' => filegroup($path)];
            $payloads[$i] = $new;
        }
        foreach (a6_dependencies() as $relative => $expected) if (a6_state(a6_walk($root, $relative)) !== $expected) a6_fail('dependency_source_drift');
        $admission = hash('sha256', json_encode(['runtime' => $runtime, 'targets' => $rows, 'dependencies' => a6_dependencies()]));
        foreach ($rows as $i => $row) {
            $candidate = $stageRoot . '/A6-candidate-' . bin2hex(random_bytes(6)) . (substr($row['path'], -4) === '.php' ? '.php' : '.tmp');
            a6_write_new($candidate, $payloads[$i]); $a6_stage[$i] = $candidate; a6_lint($candidate);
        }
        echo 'admission_sha256=' . $admission . "\nsyntax_gate=ok\n";
        if ($mode === '--check') { echo "production_writes=0\nbackup=none\ndone=ok\n"; }
        else {
            if (($argv[2] ?? '') !== '--admission=' . $admission) a6_fail('admission_changed_or_missing');
            $backupBase = a6_walk($root, '_patch_backups');
            if (!file_exists($backupBase) && !mkdir($backupBase, 0700)) a6_fail('backup_root_create_failed');
            if (!is_dir($backupBase)) a6_fail('backup_root_invalid');
            if (stat($backupBase)['dev'] !== stat($root)['dev']) a6_fail('backup_filesystem_differs');
            $backup = $backupBase . '/' . A6_ID . '-' . gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(6));
            if (!mkdir($backup, 0700)) a6_fail('backup_create_failed');
            foreach ($rows as $i => $row) {
                $body = file_get_contents($root . '/' . $row['path']);
                if (hash('sha256', $body) !== $row['before']) a6_fail('source_changed_before_backup');
                a6_write_new($backup . '/original-' . $i, $body);
                if (a6_state($backup . '/original-' . $i) !== $row['before']) a6_fail('backup_hash_failed');
            }
            a6_write_new($backup . '/manifest.json', json_encode(['task' => A6_ID, 'root' => $root, 'targets' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            echo 'backup=' . $backup . "\n";
            foreach ($rows as $i => $row) if (a6_state($root . '/' . $row['path']) !== $row['before']) a6_fail('source_changed_before_write');
            foreach (a6_dependencies() as $relative => $expected) if (a6_state(a6_walk($root, $relative)) !== $expected) a6_fail('dependency_changed_before_write');
            foreach ($rows as $i => $row) a6_permissions($a6_stage[$i], $row);
            $changed = [];
            try {
                // Languages and controllers first, then CSS/JS assets and views; the header publishes the tokens last.
                foreach (__ORDER__ as $i) {
                    $row = $rows[$i];
                    $path = $root . '/' . $row['path'];
                    if (!rename($a6_stage[$i], $path)) a6_fail('target_atomic_rename_failed');
                    unset($a6_stage[$i]);
                    $changed[] = $i;
                    if (a6_state($path) !== $row['after']) a6_fail('target_post_hash_failed');
                    a6_lint($path);
                }
            } catch (Throwable $writeError) {
                // Known partial writes only; preflight every target and backup before repair.
                $restore = a6_rollback_plan($root, $backup, $targets);
                foreach (array_reverse($changed) as $i) {
                    $row = $restore[$i]; $path = $root . '/' . $row['path'];
                    $candidate = $stageRoot . '/A6-auto-restore-' . $i . '-' . bin2hex(random_bytes(6));
                    a6_write_new($candidate, file_get_contents($backup . '/original-' . $i));
                    a6_permissions($candidate, $row);
                    if (!rename($candidate, $path) || a6_state($path) !== $row['before']) a6_fail('automatic_restore_failed');
                }
                echo "automatic_restore=ok\n";
                throw $writeError;
            }
            foreach ($rows as $i => $row) echo 'target=' . $row['path'] . ' sha256_before=' . $row['before'] . ' sha256_after=' . $row['after'] . ' backup=' . $backup . '/original-' . $i . "\n";
            echo "changed_files=" . count($rows) . "\n";
            if (!unlink(__FILE__)) a6_fail('self_delete_failed');
            echo "self_deleted=yes\ndone=ok\n";
        }
    }
} catch (Throwable $error) {
    echo 'error=' . (($error instanceof RuntimeException && preg_match('/^[a-z_]+$/D', $error->getMessage())) ? $error->getMessage() : 'operation_failed') . "\n";
    $a6_error = true;
} finally {
    foreach ($a6_stage as $candidate) if (is_file($candidate) && !is_link($candidate)) unlink($candidate);
    if ($a6_lockOwned && $a6_lock && is_file($a6_lock) && !is_link($a6_lock)) unlink($a6_lock);
}
if (!empty($a6_error)) exit(1);
