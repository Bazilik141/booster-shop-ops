<?php
declare(strict_types=1);
/*
 * CAT-005 WP1: publish the owner-approved 18.10.2026 offer, archive 06.10.2026.
 * Deploy BEFORE CAT-005_bulk-cards_20261018.php. No product/attribute writes.
 * Run from ~/public_html after a separate owner-held OpenCart database dump.
 * Writes: DB_PREFIX information (one NEW row), information_description
 * (NEW archive language 4 + description ONLY for id=3/language=4),
 * information_to_store (NEW archive/store=0), seo_url (one NEW route).
 * Does not edit files, title/meta fields, older archives (ids 6, 7, 8), other
 * information pages, layouts, SEO policy, sitemap, robots, templates,
 * checkout, payment or business data.
 * No mysqlnd required: prepared reads use bind_result(). PHP 8.0 compatible.
 * Strict all-or-nothing application: InnoDB required; no partial repair.
 * Backups: _patch_backups/<patch>-<ts>/db/live_offer_before.json,
 * created_ids.json and rollback.sql (all written BEFORE commit).
 * ROLLBACK (owner only, review saved IDs and take a fresh DB dump first):
 * execute the generated db/rollback.sql with a DB client. It contains:
 * START TRANSACTION;
 * UPDATE ocp5_information_description SET description=<saved UTF-8 hex>
 *   WHERE information_id=3 AND language_id=4;
 * DELETE FROM ocp5_seo_url WHERE seo_url_id=<saved created_seo_url_id>;
 * DELETE FROM ocp5_information_to_store WHERE information_id=<saved created_information_id>;
 * DELETE FROM ocp5_information_description WHERE information_id=<saved created_information_id>;
 * DELETE FROM ocp5_information WHERE information_id=<saved created_information_id>;
 * COMMIT;
 * Actual table prefix and assigned IDs are used in the generated SQL.
 * Rollback restores this edition only; do not overwrite later offer revisions.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL);
ini_set('display_errors', '0');
const PATCH_NAME = 'CAT-005_offer-bulk-and-archive_20261018';
const LANGUAGE_ID = 4;
const OFFER_ID = 3;
const OFFER_PREV_SHA256 = '9e22347c80bd773292832614818ecba68d66b053af37a0e369f54d43e07efb71';
const OFFER_NEW_SHA256 = '6ec16036b7bfeedc57f20882975ae5928ac8fa9d32c1a7e6393c82c252146dea';
const ARCHIVE_TITLE = 'Публічна оферта — архів 06.10.2026';
const ARCHIVE_SLUG = 'publichna-oferta-arhiv-2026-10-06';
const ARCHIVE_BANNER = '<blockquote><p><strong>Це архівна редакція Публічної оферти від 6 жовтня 2026 року.</strong> Вона зберігається для довідки й не застосовується до нових замовлень. Актуальна редакція: <a href="https://boostershop.website/information/publichna-oferta">boostershop.website/information/publichna-oferta</a></p></blockquote>';
const ARCHIVE_SHA256 = '7c699b0e8536c7526fe14a909cc7888ce627c0ee35cd4ee9553effeb57046898';
// Existing archives used as the settings reference; all are protected.
const MIRROR_ARCHIVES = [6 => '26.05.2026', 7 => '24.07.2026', 8 => '07.08.2026'];
const MAX_PROTECTED_INFORMATION_ID = 8;
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
    $html = null;
    if (is_string($compressed)) $html = @gzdecode($compressed);
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
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (!is_string($json) || file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) bs_fail('Cannot write DB backup: ' . $name);
    bs_log('backup_db', $path);
}
function bs_self_delete(): void {
    @unlink(__FILE__);
    bs_log('self_delete', file_exists(__FILE__) ? 'failed' : 'ok');
}

function bs_offer_html(): string { return bs_blob(<<<'B64'
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
/D9j9L50bIoBAA==
B64, OFFER_NEW_SHA256, 'offer HTML'); }

function bs_rows(mysqli $db, string $table, string $where, string $types = '', array $params = []): array {
    return bs_select($db, 'SELECT * FROM `' . $table . '` WHERE ' . $where . ' FOR UPDATE', $types, $params);
}
function bs_one(array $rows, string $label): array {
    if (count($rows) !== 1) bs_fail($label . ': expected exactly one row');
    return $rows[0];
}
function bs_same(array $actual, array $expected, string $label): void {
    if ($actual !== $expected) bs_fail($label . ': verification failed');
}
function bs_archive_verify(mysqli $db, array $tables, int $id, array $mirror): void {
    [$info, $desc, $store, $seo, $layout] = $tables;
    $expectedInfo = $mirror; $expectedInfo['information_id'] = $id;
    bs_same(bs_one(bs_rows($db, $info, 'information_id=?', 'i', [$id]), 'archive information'), $expectedInfo, 'archive information');
    $rows = bs_rows($db, $desc, 'information_id=?', 'i', [$id]);
    $row = bs_one($rows, 'archive description');
    if ((int) $row['language_id'] !== LANGUAGE_ID || $row['title'] !== ARCHIVE_TITLE
        || $row['meta_title'] !== ARCHIVE_TITLE || $row['meta_description'] !== '' || $row['meta_keyword'] !== ''
        || hash('sha256', (string) $row['description']) !== ARCHIVE_SHA256) bs_fail('Archive content/meta verification failed');
    bs_same(bs_rows($db, $store, 'information_id=?', 'i', [$id]), [['information_id'=>$id, 'store_id'=>0]], 'archive store');
    if (bs_rows($db, $layout, 'information_id=?', 'i', [$id]) !== []) bs_fail('Archive unexpectedly has a layout mapping');
    $route = bs_one(bs_rows($db, $seo, 'keyword=?', 's', [ARCHIVE_SLUG]), 'archive SEO route');
    if ((int) $route['store_id'] !== 0 || (int) $route['language_id'] !== LANGUAGE_ID
        || $route['key'] !== 'information_id' || (string) $route['value'] !== (string) $id
        || (int) $route['sort_order'] !== 0) bs_fail('Archive SEO route verification failed');
}
function bs_run(): void {
    $cwd = getcwd();
    if (!is_string($cwd) || $cwd === '') bs_fail('Cannot determine cwd');
    bs_log('patch', PATCH_NAME); bs_log('cwd', $cwd); bs_log('time', date('c'));
    $config = bs_path($cwd, 'config.php');
    if (!is_file($config)) bs_fail('config.php not found; run from ~/public_html');
    bs_lint_self();
    $html = bs_offer_html();
    bs_expect($html, '<h2>', 21, 'offer');
    bs_expect($html, '<h3>Особливості товарів Bulk / Bulk Mix</h3>', 1, 'offer');
    bs_expect($html, 'Редакція від: <strong>18.10.2026</strong>', 1, 'offer');
    foreach (['<p>2.20. <strong>Bulk / Bulk Mix</strong>', '<p>5.6. ', '<p>6.19. ', '<p>6.25. ', '<p>13.13. ', '<p>13.14. '] as $clause) {
        bs_expect($html, $clause, 1, 'offer clause');
    }
    foreach (['2026-10-06','2026-08-07','2026-07-24','2026-05-26'] as $date) {
        bs_expect($html, 'href="https://boostershop.website/information/publichna-oferta-arhiv-' . $date . '"', 1, 'archive link');
    }
    require_once $config;
    $db = bs_connect(); $inTransaction = false;
    try {
        $prefix = (string) DB_PREFIX;
        $tables = [];
        foreach (['information','information_description','information_to_store','seo_url','information_to_layout'] as $suffix) {
            $table = bs_table($prefix, $suffix); $tables[] = $table;
            if (!bs_table_exists($db, $table)) bs_fail('Required table missing: ' . $table);
            $engine = bs_select($db, 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', 's', [$table]);
            if (count($engine) !== 1 || strtoupper((string) $engine[0]['ENGINE']) !== 'INNODB') bs_fail('InnoDB required: ' . $table);
            // Triggers can add writes outside the approved four tables.
            $triggers = bs_select($db, 'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE=?', 's', [$table]);
            if ($triggers !== []) bs_fail('Unexpected trigger on scoped table: ' . $table);
        }
        [$info, $desc, $store, $seo, $layout] = $tables;
        $schemas = [
            ['information_id','sort_order','status'],
            ['information_id','language_id','title','description','meta_title','meta_description','meta_keyword'],
            ['information_id','store_id'],
            ['seo_url_id','store_id','language_id','key','value','keyword','sort_order'],
            ['information_id','store_id','layout_id'],
        ];
        foreach ($tables as $n => $table) {
            $columns = array_keys(bs_columns($db, $table)); sort($columns);
            $expected = $schemas[$n]; sort($expected);
            if ($columns !== $expected) bs_fail('Schema differs from verified 15.10 backup: ' . $table);
        }
        // Lock the source before its hash gate; prevent concurrent applications
        // from archiving a revision different from the one actually replaced.
        $db->begin_transaction(); $inTransaction = true;
        $offer = bs_one(bs_rows($db, $desc, 'information_id=? AND language_id=?', 'ii', [OFFER_ID,LANGUAGE_ID]), 'live offer');
        if ($offer['title'] !== 'Публічна оферта') bs_fail('Unexpected live offer title');
        $sha = hash('sha256', (string) $offer['description']); bs_log('live_offer_sha256', $sha);
        $mirrorInfo = null;
        foreach (MIRROR_ARCHIVES as $mirrorId => $date) {
            $ref = bs_one(bs_rows($db, $info, 'information_id=?', 'i', [$mirrorId]), 'mirror information');
            if ((int) $ref['sort_order'] !== 0 || (int) $ref['status'] !== 1) bs_fail('Unexpected archive reference settings');
            $refDesc = bs_one(bs_rows($db, $desc, 'information_id=? AND language_id=?', 'ii', [$mirrorId,LANGUAGE_ID]), 'mirror description');
            $title = 'Публічна оферта — архів ' . $date;
            if ($refDesc['title'] !== $title || $refDesc['meta_title'] !== $title
                || $refDesc['meta_description'] !== '' || $refDesc['meta_keyword'] !== ''
                || strpos((string) $refDesc['description'], '<blockquote><p><strong>Це архівна редакція Публічної оферти від ') !== 0) bs_fail('Unexpected archive reference title/meta/banner');
            bs_same(bs_rows($db, $store, 'information_id=?', 'i', [$mirrorId]), [['information_id'=>$mirrorId,'store_id'=>0]], 'mirror store');
            if (bs_rows($db, $layout, 'information_id=?', 'i', [$mirrorId]) !== []) bs_fail('Archive reference has a layout mapping');
            $mirrorInfo = $ref;
            bs_log('mirror_id' . $mirrorId, 'settings+meta+banner+store+no_layout=ok');
        }
        $routes = bs_rows($db, $seo, 'keyword=?', 's', [ARCHIVE_SLUG]);
        $archives = bs_rows($db, $desc, 'title=?', 's', [ARCHIVE_TITLE]);
        if ($sha === OFFER_NEW_SHA256) {
            $archive = bs_one($archives, 'already-applied archive');
            $archiveId = (int) $archive['information_id'];
            if ($archiveId <= MAX_PROTECTED_INFORMATION_ID) bs_fail('Archive points at a protected information ID');
            bs_archive_verify($db, $tables, $archiveId, $mirrorInfo);
            $db->rollback(); $inTransaction = false;
            bs_log('already_applied', 'yes'); bs_log('archive_information_id', (string) $archiveId);
            bs_log('done', 'ok'); bs_self_delete(); return;
        }
        if ($sha !== OFFER_PREV_SHA256) bs_fail('Live offer hash differs from expected 06.10.2026 edition; no writes permitted');
        if ($routes !== []) bs_fail('Archive SEO keyword already exists; no writes permitted');
        if ($archives !== []) bs_fail('Archive title already exists; no partial repair permitted');
        $archiveHtml = ARCHIVE_BANNER . (string) $offer['description'];
        if (hash('sha256', $archiveHtml) !== ARCHIVE_SHA256) bs_fail('Runtime archive hash verification failed');
        $backupDir = bs_path($cwd, '_patch_backups/' . PATCH_NAME . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)));
        if (!mkdir($backupDir, 0700, true)) bs_fail('Cannot create backup directory');
        bs_log('backup_dir', $backupDir);
        bs_json_backup($backupDir, 'live_offer_before', ['table'=>$desc,'row'=>$offer,'description_sha256'=>$sha]);
        // Preserve source settings exactly; never derive an ID from MAX(id).
        $db->query('INSERT INTO `' . $info . '` (sort_order,status) VALUES (0,1)');
        $archiveId = (int) $db->insert_id;
        if ($archiveId <= MAX_PROTECTED_INFORMATION_ID) bs_fail('Invalid/protected archive ID assigned');
        $stmt = $db->prepare('INSERT INTO `' . $desc . '` (information_id,language_id,title,description,meta_title,meta_description,meta_keyword) VALUES (?,?,?,?,?,?,?)');
        $lang=LANGUAGE_ID; $title=ARCHIVE_TITLE; $empty='';
        $stmt->bind_param('iisssss',$archiveId,$lang,$title,$archiveHtml,$title,$empty,$empty);
        $stmt->execute(); $stmt->close();
        $db->query('INSERT INTO `' . $store . '` (information_id,store_id) VALUES (' . $archiveId . ',0)');
        $stmt = $db->prepare('INSERT INTO `' . $seo . '` (store_id,language_id,`key`,`value`,keyword,sort_order) VALUES (0,?,\'information_id\',?,?,0)');
        $value=(string)$archiveId; $slug=ARCHIVE_SLUG;
        $stmt->bind_param('iss',$lang,$value,$slug); $stmt->execute(); $stmt->close();
        $seoId=(int)$db->insert_id;
        if ($seoId < 1) bs_fail('Invalid SEO ID assigned');
        $stmt = $db->prepare('UPDATE `' . $desc . '` SET description=? WHERE information_id=? AND language_id=?');
        $id=OFFER_ID; $stmt->bind_param('sii',$html,$id,$lang); $stmt->execute();
        if ($stmt->affected_rows !== 1) bs_fail('Offer update did not affect exactly one row');
        $stmt->close();
        $expectedOffer = $offer; $expectedOffer['description']=$html;
        bs_same(bs_one(bs_rows($db,$desc,'information_id=? AND language_id=?','ii',[$id,$lang]),'updated offer'),$expectedOffer,'offer description and preserved metadata');
        bs_archive_verify($db,$tables,$archiveId,$mirrorInfo);
        bs_json_backup($backupDir,'created_ids',[
            'created_information_id'=>$archiveId,'created_seo_url_id'=>$seoId,
            'archive_slug'=>ARCHIVE_SLUG,'offer_new_sha256'=>OFFER_NEW_SHA256,
            'note'=>'Prepared before commit. On failed apply the DB transaction is rolled back; use only after done=ok.',
        ]);
        $rollback = "-- CAT-005 WP1: owner-only rollback; use only after done=ok and before later offer edits.\nSET NAMES utf8mb4;\nSTART TRANSACTION;\n"
            . 'UPDATE `' . $desc . '` SET description=CONVERT(0x' . bin2hex((string)$offer['description']) . ' USING utf8mb4) WHERE information_id=' . OFFER_ID . ' AND language_id=' . LANGUAGE_ID . ";\n"
            . 'DELETE FROM `' . $seo . '` WHERE seo_url_id=' . $seoId . ";\n"
            . 'DELETE FROM `' . $store . '` WHERE information_id=' . $archiveId . ";\n"
            . 'DELETE FROM `' . $desc . '` WHERE information_id=' . $archiveId . ";\n"
            . 'DELETE FROM `' . $info . '` WHERE information_id=' . $archiveId . ";\nCOMMIT;\n";
        if (file_put_contents(bs_path($backupDir,'db/rollback.sql'),$rollback,LOCK_EX) !== strlen($rollback)) bs_fail('Cannot write complete rollback SQL');
        $db->commit(); $inTransaction=false;
        bs_log('updated_offer','description_only'); bs_log('offer_sha256',OFFER_NEW_SHA256);
        bs_log('archive_sha256',ARCHIVE_SHA256); bs_log('archive_information_id',(string)$archiveId);
        bs_log('archive_seo_url_id',(string)$seoId); bs_log('transaction','committed');
        bs_log('done','ok'); bs_self_delete();
    } catch (Throwable $e) {
        if ($inTransaction) { $db->rollback(); bs_log('transaction','rolled_back'); }
        throw $e;
    } finally { $db->close(); }
}
try { bs_run(); } catch (Throwable $e) {
    // Driver messages may include credentials/server details. Do not print them.
    bs_log('error', $e instanceof mysqli_sql_exception ? 'Database operation failed (code ' . $e->getCode() . '); no credentials logged' : $e->getMessage());
    bs_log('done','failed'); exit(1);
}
