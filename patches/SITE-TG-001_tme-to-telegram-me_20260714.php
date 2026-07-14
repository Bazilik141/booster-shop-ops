<?php
declare(strict_types=1);

/**
 * SITE-TG-001 — replace //t.me/ links with //telegram.me/ (t.me serverHold outage 2026-07-14).
 *
 * КОНТЕКСТ: 2026-07-14 реєстр .me поставив t.me на serverHold — усі t.me-посилання не резолвляться.
 * Це НЕ офіційна зміна домену Telegram. telegram.me — робочий запасний домен Telegram (та сама зона .me).
 * Заміна свідомо зроблена ОБОРОТНОЮ: бекапи файлів + rollback SQL. Якщо t.me повернеться —
 * зворотна заміна telegram.me → t.me тим самим раннером (поміняти константи місцями).
 *
 * ЩО РОБИТЬ:
 *  - Режим за замовчуванням (php SITE-TG-001_...php) — DRY-RUN: сканує catalog/ (twig/php/js/css/html/tpl)
 *    і БД (information_description, product_description, category_description, setting, modification)
 *    на входження '//t.me/'. Нічого не змінює.
 *  - --apply        : замінює '//t.me/' → '//telegram.me/' у файлах catalog/ з бекапом
 *                     у _patch_backups/SITE-TG-001-<ts>/, php -l гейт для .php (restore-on-fail),
 *                     чистить data-cache і twig-cache. Self-delete після успіху.
 *  - --apply --with-db : додатково REPLACE() у таблицях БД (потрібен явний дозвіл owner;
 *                     rollback SQL друкується перед виконанням).
 *
 * ЗАПУСК: з ~/public_html:  php SITE-TG-001_tme-to-telegram-me_20260714.php [--apply] [--with-db]
 * ROLLBACK файлів: скопіювати вміст _patch_backups/SITE-TG-001-<ts>/ назад у catalog/.
 * ROLLBACK БД: SQL друкується в лог при --with-db (REPLACE '//telegram.me/' → '//t.me/').
 */

const STG1_SEARCH  = '//t.me/';
const STG1_REPLACE = '//telegram.me/';
const STG1_ID      = 'SITE-TG-001';

function stg1_log(string $m): void { echo '[' . gmdate('c') . '] ' . $m . PHP_EOL; }
function stg1_fail(string $m): void { stg1_log('error=' . $m); stg1_log('done=failed'); exit(1); }

$apply  = in_array('--apply', $argv, true);
$withDb = in_array('--with-db', $argv, true);

if ($withDb && !$apply) stg1_fail('flag_with-db_requires_apply');

$root = __DIR__;
$catalog = $root . DIRECTORY_SEPARATOR . 'catalog';
if (!is_dir($catalog)) stg1_fail('missing_dir:catalog (run from ~/public_html)');

stg1_log('mode=' . ($apply ? ($withDb ? 'APPLY+DB' : 'APPLY') : 'DRY-RUN'));

/* ---------- 1. File scan ---------- */

$exts = ['twig', 'php', 'js', 'css', 'html', 'tpl'];
$hits = []; // relative path => count

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($catalog, FilesystemIterator::SKIP_DOTS)
);

foreach ($it as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) continue;
    $path = $file->getPathname();
    if (strpos($path, '_patch_backups') !== false) continue;
    if (substr($path, -4) === '.bak' || strpos($path, '.bak.') !== false) continue;
    $ext = strtolower($file->getExtension());
    if (!in_array($ext, $exts, true)) continue;
    if ($file->getSize() > 2 * 1024 * 1024) continue;

    $content = file_get_contents($path);
    if ($content === false) { stg1_log('warn=read_failed:' . $path); continue; }

    $count = substr_count($content, STG1_SEARCH);
    if ($count > 0) {
        $rel = ltrim(str_replace($root, '', $path), '/\\');
        $hits[$rel] = $count;
        stg1_log('file_hit=' . $rel . ' count=' . $count);
    }
}

$totalFile = array_sum($hits);
stg1_log('files_with_hits=' . count($hits) . ' total_occurrences=' . $totalFile);

/* ---------- 2. DB scan (read-only unless --with-db) ---------- */

$dbTargets = [
    // table (without prefix) => [text columns to check]
    'information_description' => ['description', 'meta_description'],
    'product_description'     => ['description', 'meta_description'],
    'category_description'    => ['description', 'meta_description'],
    'setting'                 => ['value'],
    'modification'            => ['xml'],
];

$db = null;
$dbHits = []; // "table.column" => row count
$configPath = $root . DIRECTORY_SEPARATOR . 'config.php';

if (is_file($configPath) && extension_loaded('mysqli')) {
    require_once $configPath;
    if (defined('DB_HOSTNAME')) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)(defined('DB_PORT') ? DB_PORT : 3306));
        if ($db->connect_errno) { stg1_log('warn=db_connect_failed:' . $db->connect_error); $db = null; }
    }
} else {
    stg1_log('warn=db_scan_skipped (no config.php or mysqli)');
}

if ($db) {
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'oc_';
    $like = '%' . $db->real_escape_string(STG1_SEARCH) . '%';

    foreach ($dbTargets as $table => $cols) {
        $full = $prefix . $table;
        $check = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($full) . "'");
        if (!$check || $check->num_rows === 0) { stg1_log('db_table_absent=' . $full); continue; }

        foreach ($cols as $col) {
            $res = $db->query("SELECT COUNT(*) c FROM `$full` WHERE `$col` LIKE '$like'");
            if (!$res) { stg1_log('warn=db_query_failed:' . $full . '.' . $col); continue; }
            $c = (int)$res->fetch_assoc()['c'];
            if ($c > 0) {
                $dbHits["$full.$col"] = $c;
                stg1_log('db_hit=' . $full . '.' . $col . ' rows=' . $c);
            }
        }
    }
    stg1_log('db_columns_with_hits=' . count($dbHits));
}

/* ---------- 3. Idempotency / dry-run exit ---------- */

if ($totalFile === 0 && count($dbHits) === 0) {
    stg1_log('already_applied=yes (no //t.me/ occurrences found)');
    stg1_log('done=ok');
    exit(0);
}

if (!$apply) {
    stg1_log('dry_run=complete. Re-run with --apply to patch files' . ($db ? ' (add --with-db for DB after owner approval)' : ''));
    stg1_log('done=ok');
    exit(0);
}

/* ---------- 4. Apply: files ---------- */

$ts = date('Ymd-His');
$backupDir = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . STG1_ID . '-' . $ts;

foreach ($hits as $rel => $count) {
    $path = $root . DIRECTORY_SEPARATOR . $rel;
    $backupPath = $backupDir . DIRECTORY_SEPARATOR . $rel;

    if (!is_dir(dirname($backupPath)) && !mkdir(dirname($backupPath), 0755, true)) {
        stg1_fail('backup_mkdir_failed:' . $rel);
    }
    if (!copy($path, $backupPath)) stg1_fail('backup_failed:' . $rel);

    $content = file_get_contents($path);
    $patched = str_replace(STG1_SEARCH, STG1_REPLACE, $content);
    if (file_put_contents($path, $patched) === false) stg1_fail('write_failed:' . $rel);

    if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'php') {
        $out = [];
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $rc);
        if ($rc !== 0) {
            copy($backupPath, $path);
            stg1_fail('php_lint_failed_restored:' . $rel . ' :: ' . implode(' | ', $out));
        }
    }
    stg1_log('patched=' . $rel . ' replacements=' . $count);
}

if (count($hits) > 0) stg1_log('backup_dir=' . $backupDir);

/* ---------- 5. Apply: DB (only with --with-db) ---------- */

if ($withDb && $db && count($dbHits) > 0) {
    stg1_log('DB ROLLBACK SQL (save before proceeding):');
    foreach ($dbHits as $key => $c) {
        [$full, $col] = explode('.', $key, 2);
        stg1_log("  UPDATE `$full` SET `$col` = REPLACE(`$col`, '" . STG1_REPLACE . "', '" . STG1_SEARCH . "') WHERE `$col` LIKE '%" . STG1_REPLACE . "%';");
    }
    foreach ($dbHits as $key => $c) {
        [$full, $col] = explode('.', $key, 2);
        $sql = "UPDATE `$full` SET `$col` = REPLACE(`$col`, '" . STG1_SEARCH . "', '" . STG1_REPLACE . "') WHERE `$col` LIKE '%" . $db->real_escape_string(STG1_SEARCH) . "%'";
        if (!$db->query($sql)) stg1_fail('db_update_failed:' . $key . ' :: ' . $db->error);
        stg1_log('db_updated=' . $key . ' affected_rows=' . $db->affected_rows);
    }
} elseif (count($dbHits) > 0) {
    stg1_log('db_hits_present_but_not_applied (re-run with --apply --with-db after owner approval)');
}

/* ---------- 6. Cache clear ---------- */

$cacheDir = $root . '/system/storage/cache';
$cleared = 0;
foreach ((glob($cacheDir . '/cache.*') ?: []) as $f) { if (is_file($f) && @unlink($f)) $cleared++; }
foreach ((glob($cacheDir . '/template/*') ?: []) as $f) { if (is_file($f) && @unlink($f)) $cleared++; }
stg1_log('cache_files_cleared=' . $cleared . ' (якщо twig-кеш не оновився — Admin → Dashboard → шестерня → Refresh Theme)');

/* ---------- 7. Self-delete ---------- */

if (@unlink(__FILE__)) stg1_log('self_delete=yes');
else stg1_log('warn=self_delete_failed (delete manually)');

stg1_log('done=ok');
