<?php
declare(strict_types=1);
/*
 * LEGAL-003 WP1: publish the owner-approved 06.10.2026 offer, archive 07.08.
 * Owner decision 2026-10-06: WP2 rejected; NO product/attribute writes.
 * Run from ~/public_html after a separate owner-held OpenCart database dump.
 * Writes: DB_PREFIX information (one NEW row), information_description
 * (NEW archive language 4 + description ONLY for id=3/language=4),
 * information_to_store (NEW archive/store=0), seo_url (one NEW route).
 * Does not edit files, title/meta fields, old archives, layouts, SEO policy,
 * sitemap, robots, templates, checkout, payment or business data.
 * No mysqlnd required: prepared reads use bind_result(). PHP 8.0 compatible.
 * Strict all-or-nothing application: InnoDB required; no partial repair.
 * Backups: _patch_backups/<patch>-<ts>/db/live_offer_before.json,
 * created_ids.json (written BEFORE commit), rollback.sql.
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
const PATCH_NAME = 'LEGAL-003_offer-rare-pack-and-archive_20261006';
const LANGUAGE_ID = 4;
const OFFER_ID = 3;
const OFFER_PREV_SHA256 = '08695acfe3c7e1a1b6f5d09360879d2af3e004384494c41742e1197e420e2cae';
const OFFER_NEW_SHA256 = '9e22347c80bd773292832614818ecba68d66b053af37a0e369f54d43e07efb71';
const ARCHIVE_TITLE = 'Публічна оферта — архів 07.08.2026';
const ARCHIVE_SLUG = 'publichna-oferta-arhiv-2026-08-07';
const ARCHIVE_BANNER = '<blockquote><p><strong>Це архівна редакція Публічної оферти від 7 серпня 2026 року.</strong> Вона зберігається для довідки й не застосовується до нових замовлень. Актуальна редакція: <a href="https://boostershop.website/information/publichna-oferta">boostershop.website/information/publichna-oferta</a></p></blockquote>';
const ARCHIVE_SHA256 = '8df9d9bae07699976433cd7945f5c8624bf8fb4149fc851780617da7bfc0624c';
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
function bs_blob(string $b64, string $sha, string $label): string {
    if (!function_exists('gzdecode')) bs_fail('zlib/gzdecode is unavailable; cannot decode embedded ' . $label);
    $compressed = base64_decode(preg_replace('/\s+/', '', $b64) ?? '', true);
    $html = is_string($compressed) ? @gzdecode($compressed) : false;
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
mPxdAQA=
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
    bs_expect($html, 'Редакція від: <strong>06.10.2026</strong>', 1, 'offer');
    bs_expect($html, '2.17. <strong>Rare Pack</strong>', 1, 'offer');
    foreach (['2026-08-07','2026-07-24','2026-05-26'] as $date) {
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
            if ($columns !== $expected) bs_fail('Schema differs from verified 24.09 backup: ' . $table);
        }
        // Lock the source before its hash gate; prevent concurrent applications
        // from archiving a revision different from the one actually replaced.
        $db->begin_transaction(); $inTransaction = true;
        $offer = bs_one(bs_rows($db, $desc, 'information_id=? AND language_id=?', 'ii', [OFFER_ID,LANGUAGE_ID]), 'live offer');
        if ($offer['title'] !== 'Публічна оферта') bs_fail('Unexpected live offer title');
        $sha = hash('sha256', (string) $offer['description']); bs_log('live_offer_sha256', $sha);
        $mirrorInfo = null;
        foreach ([6,7] as $mirrorId) {
            $ref = bs_one(bs_rows($db, $info, 'information_id=?', 'i', [$mirrorId]), 'mirror information');
            if ((int) $ref['sort_order'] !== 0 || (int) $ref['status'] !== 1) bs_fail('Unexpected archive reference settings');
            $refDesc = bs_one(bs_rows($db, $desc, 'information_id=? AND language_id=?', 'ii', [$mirrorId,LANGUAGE_ID]), 'mirror description');
            $date = $mirrorId === 6 ? '26.05.2026' : '24.07.2026';
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
            if ($archiveId <= 7) bs_fail('Archive points at a protected information ID');
            bs_archive_verify($db, $tables, $archiveId, $mirrorInfo);
            $db->rollback(); $inTransaction = false;
            bs_log('already_applied', 'yes'); bs_log('archive_information_id', (string) $archiveId);
            bs_log('done', 'ok'); bs_self_delete(); return;
        }
        if ($sha !== OFFER_PREV_SHA256) bs_fail('Live offer hash differs from expected 07.08.2026 edition; no writes permitted');
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
        if ($archiveId <= 7) bs_fail('Invalid/protected archive ID assigned');
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
        $rollback = "-- LEGAL-003: owner-only rollback; use only after done=ok and before later offer edits.\nSET NAMES utf8mb4;\nSTART TRANSACTION;\n"
            . 'UPDATE `' . $desc . '` SET description=CONVERT(0x' . bin2hex((string)$offer['description']) . " USING utf8mb4) WHERE information_id=3 AND language_id=4;\n"
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
