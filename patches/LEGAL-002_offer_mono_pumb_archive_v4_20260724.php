<?php
declare(strict_types=1);

/*
 * LEGAL-002 — publish the owner-provided 24.07.2026 public offer and create
 * its 26.05.2026 archive page.
 *
 * DB scope (approved by owner): one information_description row is updated;
 * one information row, one information_description row, and one SEO route
 * are created only when the archive does not already exist. No checkout,
 * payment, sitemap, robots, canonical, or menu data is changed.
 * Rollback: use the JSON snapshots in _patch_backups/<patch>-<timestamp>/db/
 * to restore the former offer row; delete the archive information row and its
 * SEO route only if they were created by this patch.
 */

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}
error_reporting(E_ALL);
ini_set('display_errors', '1');

const PATCH_NAME = 'LEGAL-002_offer_mono_pumb_archive_v4_20260724';
const LANGUAGE_ID = 4;
const OFFER_TITLE = 'Публічна оферта';
const ARCHIVE_TITLE = 'Публічна оферта — архів 26.05.2026';
const ARCHIVE_SLUG = 'publichna-oferta-arhiv-2026-05-26';
const OFFER_SHA256 = '4324d3f4854da660ba2ec31f4ba447944fc202d3b88f6b31d4b4a4f2e97e044e';
const ARCHIVE_SHA256 = 'f19699e5f67713348604f9180decd6fcc64ea1858dbf193afb7c75aa9726efc1';

function bs_log(string $key, string $value = ''): void {
    echo $key . ($value === '' ? '' : '=' . $value) . PHP_EOL;
}
function bs_fail(string $message): void { throw new RuntimeException($message); }
function bs_path(string $base, string $part): string { return rtrim($base, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $part); }
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
    while ($row = $r->fetch_assoc()) $columns[(string)$row['Field']] = true;
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
function bs_html(string $b64, string $sha, int $h2, string $mustContain, string $label): string {
    if (!function_exists('gzdecode')) bs_fail('zlib/gzdecode is unavailable; cannot decode embedded ' . $label . ' HTML');
    $compressed = base64_decode(preg_replace('/\s+/', '', $b64) ?? '', true);
    $html = is_string($compressed) ? @gzdecode($compressed) : false;
    if (!is_string($html) || $html === '') bs_fail('Cannot decode embedded ' . $label . ' HTML');
    if (hash('sha256', $html) !== $sha) bs_fail($label . ' SHA-256 mismatch');
    if (substr_count($html, '<h2>') !== $h2) bs_fail($label . ' heading count mismatch');
    if (strpos($html, $mustContain) === false) bs_fail($label . ' required anchor is missing');
    return $html;
}
function bs_offer_html(): string { return bs_html(<<<'B64'
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
B64, OFFER_SHA256, 19, 'publichna-oferta-arhiv-2026-05-26', 'offer'); }
function bs_archive_html(): string { return bs_html(<<<'B64'
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
/wFGyPaNDFIAAA==
B64, ARCHIVE_SHA256, 18, '26 травня 2026 року', 'archive'); }
function bs_connect(): mysqli {
    if (!extension_loaded('mysqli')) bs_fail('mysqli extension is not loaded');
    foreach (['DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PREFIX'] as $constant) if (!defined($constant)) bs_fail('Missing config constant: ' . $constant);
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
    while ($stmt->fetch()) { $copy = []; foreach ($row as $key => $value) $copy[$key] = $value; $rows[] = $copy; }
    $metadata->free();
    return $rows;
}
function bs_one_by_title(mysqli $db, string $table, string $title): ?array {
    $stmt = $db->prepare('SELECT information_id, language_id, title, description FROM `' . $table . '` WHERE language_id = ? AND title = ?');
    $lang = LANGUAGE_ID; $stmt->bind_param('is', $lang, $title); $stmt->execute();
    $rows = bs_stmt_rows($stmt); $stmt->close();
    if (count($rows) > 1) bs_fail('Expected exactly one or zero rows for title: ' . $title);
    return $rows[0] ?? null;
}
function bs_information_row(mysqli $db, string $table, int $id): ?array {
    $stmt = $db->prepare('SELECT information_id, sort_order, status FROM `' . $table . '` WHERE information_id = ?');
    $stmt->bind_param('i', $id); $stmt->execute(); $rows = bs_stmt_rows($stmt); $stmt->close();
    if (count($rows) > 1) bs_fail('Archive information row is ambiguous');
    return $rows[0] ?? null;
}
function bs_store_rows(mysqli $db, string $table, int $id): array {
    $stmt = $db->prepare('SELECT information_id, store_id FROM `' . $table . '` WHERE information_id = ?');
    $stmt->bind_param('i', $id); $stmt->execute(); $rows = bs_stmt_rows($stmt); $stmt->close(); return $rows;
}
function bs_has_default_store(array $rows): bool { foreach ($rows as $row) if ((int)$row['store_id'] === 0) return true; return false; }
function bs_insert_default_store(mysqli $db, string $table, int $id): void {
    $stmt = $db->prepare('INSERT INTO `' . $table . '` (information_id, store_id) VALUES (?, 0)');
    $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); bs_log('archive_store_mapping', 'created');
}
function bs_json_backup(string $dir, string $name, array $payload): void {
    $path = bs_path($dir, 'db/' . $name . '.json'); $parent = dirname($path);
    if (!is_dir($parent) && !mkdir($parent, 0755, true)) bs_fail('Cannot create DB backup directory');
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($json) || file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) bs_fail('Cannot write DB backup: ' . $name);
    bs_log('backup_db', $path);
}
function bs_route_mode(mysqli $db, string $prefix): array {
    $seo = bs_table($prefix, 'seo_url'); $alias = bs_table($prefix, 'url_alias');
    if (bs_table_exists($db, $seo)) {
        bs_require_columns(bs_columns($db, $seo), ['store_id','language_id','key','value','keyword'], $seo);
        return ['mode' => 'seo_url', 'table' => $seo];
    }
    if (bs_table_exists($db, $alias)) {
        bs_require_columns(bs_columns($db, $alias), ['query','keyword'], $alias);
        return ['mode' => 'url_alias', 'table' => $alias];
    }
    bs_fail('Neither ' . $seo . ' nor ' . $alias . ' exists; SEO route table cannot be confirmed');
}
function bs_route(mysqli $db, array $route): ?array {
    if ($route['mode'] === 'seo_url') {
        $stmt = $db->prepare('SELECT store_id, language_id, `key`, value, keyword FROM `' . $route['table'] . '` WHERE keyword = ?');
    } else {
        $stmt = $db->prepare('SELECT `query`, keyword FROM `' . $route['table'] . '` WHERE keyword = ?');
    }
    $slug = ARCHIVE_SLUG; $stmt->bind_param('s', $slug); $stmt->execute(); $rows = bs_stmt_rows($stmt); $stmt->close();
    if (count($rows) > 1) bs_fail('Archive SEO slug is ambiguous in ' . $route['table']);
    return $rows[0] ?? null;
}
function bs_route_is_correct(?array $row, array $route, int $id): bool {
    if ($row === null) return false;
    if ($route['mode'] === 'seo_url') return (int)$row['store_id'] === 0 && (int)$row['language_id'] === LANGUAGE_ID && $row['key'] === 'information_id' && (string)$row['value'] === (string)$id;
    return $row['query'] === 'information_id=' . $id;
}
function bs_insert_information(mysqli $db, string $table, array $columns): int {
    $fields = []; $values = [];
    foreach (['bottom' => 0, 'sort_order' => 0, 'status' => 1] as $field => $value) {
        if (isset($columns[$field])) { $fields[] = '`' . $field . '`'; $values[] = (string)$value; }
    }
    $sql = $fields === [] ? 'INSERT INTO `' . $table . '` () VALUES ()' : 'INSERT INTO `' . $table . '` (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')';
    $db->query($sql); $id = (int)$db->insert_id;
    if ($id < 1) bs_fail('Archive information insert did not return an information_id');
    bs_log('information_insert_columns', $fields === [] ? 'defaults_only' : implode(',', $fields));
    return $id;
}
function bs_insert_archive_description(mysqli $db, string $table, array $columns, int $id, string $html): void {
    $fields = ['information_id', 'language_id', 'title', 'description'];
    $values = [$id, LANGUAGE_ID, ARCHIVE_TITLE, $html]; $types = 'iiss';
    foreach (['meta_title' => ARCHIVE_TITLE, 'meta_description' => '', 'meta_keyword' => ''] as $field => $value) {
        if (isset($columns[$field])) { $fields[] = $field; $values[] = $value; $types .= 's'; }
    }
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $fields) . '`) VALUES (' . implode(', ', array_fill(0, count($fields), '?')) . ')';
    $stmt = $db->prepare($sql); $refs = [$types]; foreach ($values as $key => &$value) $refs[] = &$value;
    if (!call_user_func_array([$stmt, 'bind_param'], $refs)) bs_fail('Cannot bind archive description values');
    $stmt->execute(); $stmt->close();
}
function bs_insert_route(mysqli $db, array $route, int $id): void {
    if ($route['mode'] === 'seo_url') {
        $columns = bs_columns($db, $route['table']); $sort = isset($columns['sort_order']);
        $sql = $sort ? 'INSERT INTO `' . $route['table'] . '` (store_id, language_id, `key`, value, keyword, sort_order) VALUES (0, ?, \'information_id\', ?, ?, 0)' : 'INSERT INTO `' . $route['table'] . '` (store_id, language_id, `key`, value, keyword) VALUES (0, ?, \'information_id\', ?, ?)';
        $stmt = $db->prepare($sql); $lang = LANGUAGE_ID; $value = (string)$id; $slug = ARCHIVE_SLUG; $stmt->bind_param('iss', $lang, $value, $slug);
    } else {
        $stmt = $db->prepare('INSERT INTO `' . $route['table'] . '` (`query`, keyword) VALUES (?, ?)'); $query = 'information_id=' . $id; $slug = ARCHIVE_SLUG; $stmt->bind_param('ss', $query, $slug);
    }
    $stmt->execute(); $stmt->close();
}
function bs_run(): void {
    $cwd = getcwd(); if (!is_string($cwd) || $cwd === '') bs_fail('Cannot determine cwd');
    bs_log('patch', PATCH_NAME); bs_log('cwd', $cwd); bs_log('time', date('c'));
    $config = bs_path($cwd, 'config.php'); if (!is_file($config)) bs_fail('config.php not found. Run from OpenCart public_html.');
    bs_lint_self(); require_once $config;
    $offerHtml = bs_offer_html(); $archiveHtml = bs_archive_html();
    $backupDir = bs_path($cwd, '_patch_backups/' . PATCH_NAME . '-' . date('Ymd-His'));
    if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) bs_fail('Cannot create backup directory');
    bs_log('backup_dir', $backupDir);
    $db = bs_connect();
    try {
        $prefix = (string) DB_PREFIX; $info = bs_table($prefix, 'information'); $desc = bs_table($prefix, 'information_description'); $infoStore = bs_table($prefix, 'information_to_store');
        foreach ([$info, $desc, $infoStore] as $table) if (!bs_table_exists($db, $table)) bs_fail('Required table not found: ' . $table);
        $infoColumns = bs_columns($db, $info); $descColumns = bs_columns($db, $desc); $storeColumns = bs_columns($db, $infoStore);
        bs_require_columns($infoColumns, ['information_id'], $info);
        bs_require_columns($descColumns, ['information_id','language_id','title','description'], $desc);
        bs_require_columns($storeColumns, ['information_id','store_id'], $infoStore);
        $route = bs_route_mode($db, $prefix); bs_log('seo_route_table', $route['table']);
        $offer = bs_one_by_title($db, $desc, OFFER_TITLE); if ($offer === null) bs_fail('Live public offer row not found for language_id=4');
        $archive = bs_one_by_title($db, $desc, ARCHIVE_TITLE); $routeRow = bs_route($db, $route);
        $archiveInformation = $archive === null ? null : bs_information_row($db, $info, (int)$archive['information_id']);
        $archiveStores = $archive === null ? [] : bs_store_rows($db, $infoStore, (int)$archive['information_id']);
        $offerOk = hash('sha256', (string)$offer['description']) === OFFER_SHA256;
        $archiveOk = $archive !== null && $archiveInformation !== null && hash('sha256', (string)$archive['description']) === ARCHIVE_SHA256;
        $routeOk = $archive !== null && bs_route_is_correct($routeRow, $route, (int)$archive['information_id']);
        $storeOk = $archive !== null && bs_has_default_store($archiveStores) && $archiveInformation !== null && (int)$archiveInformation['status'] === 1;
        if ($offerOk && $archiveOk && $routeOk && $storeOk) { bs_log('already_applied', 'yes'); bs_log('done', 'ok'); @unlink(__FILE__); bs_log('self_delete', file_exists(__FILE__) ? 'failed' : 'ok'); return; }
        if ($archive !== null && !$archiveOk) bs_fail('Archive title exists but its description does not match the embedded approved archive');
        if ($routeRow !== null && ($archive === null || !bs_route_is_correct($routeRow, $route, (int)$archive['information_id']))) bs_fail('Archive SEO slug is already assigned to a different route');
        bs_json_backup($backupDir, 'live_offer_before', ['table' => $desc, 'row' => $offer, 'description_sha256' => hash('sha256', (string)$offer['description'])]);
        bs_json_backup($backupDir, 'archive_before', ['information_table' => $info, 'information_to_store_table' => $infoStore, 'description_table' => $desc, 'archive_row' => $archive, 'information_row' => $archiveInformation, 'store_rows' => $archiveStores, 'seo_table' => $route['table'], 'seo_route' => $routeRow]);
        $db->begin_transaction();
        try {
            if (!$offerOk) { $stmt = $db->prepare('UPDATE `' . $desc . '` SET description = ? WHERE information_id = ? AND language_id = ?'); $id = (int)$offer['information_id']; $lang = LANGUAGE_ID; $stmt->bind_param('sii', $offerHtml, $id, $lang); $stmt->execute(); $stmt->close(); bs_log('updated_offer', 'yes'); }
            if ($archive === null) { $archiveId = bs_insert_information($db, $info, $infoColumns); bs_insert_archive_description($db, $desc, $descColumns, $archiveId, $archiveHtml); bs_insert_default_store($db, $infoStore, $archiveId); bs_insert_route($db, $route, $archiveId); bs_log('created_archive_information_id', (string)$archiveId); }
            else { $archiveId = (int)$archive['information_id']; if ($archiveInformation === null) bs_fail('Archive description exists without parent information row'); if ((int)$archiveInformation['status'] !== 1) { $db->query('UPDATE `' . $info . '` SET status = 1 WHERE information_id = ' . $archiveId); bs_log('archive_status', 'enabled'); } if (!bs_has_default_store($archiveStores)) bs_insert_default_store($db, $infoStore, $archiveId); }
            $verifyOffer = bs_one_by_title($db, $desc, OFFER_TITLE); $verifyArchive = bs_one_by_title($db, $desc, ARCHIVE_TITLE); $verifyRoute = bs_route($db, $route);
            if ($verifyOffer === null || hash('sha256', (string)$verifyOffer['description']) !== OFFER_SHA256) bs_fail('Live offer SHA-256 verification failed');
            if ($verifyArchive === null || hash('sha256', (string)$verifyArchive['description']) !== ARCHIVE_SHA256) bs_fail('Archive SHA-256 verification failed');
            if (!bs_route_is_correct($verifyRoute, $route, (int)$verifyArchive['information_id'])) bs_fail('Archive SEO route verification failed');
            $verifyInformation = bs_information_row($db, $info, (int)$verifyArchive['information_id']); $verifyStores = bs_store_rows($db, $infoStore, (int)$verifyArchive['information_id']);
            if ($verifyInformation === null || (int)$verifyInformation['status'] !== 1 || !bs_has_default_store($verifyStores)) bs_fail('Archive store publication verification failed');
            $db->commit();
        } catch (Throwable $e) { $db->rollback(); throw $e; }
        bs_log('offer_html_sha256', OFFER_SHA256); bs_log('archive_html_sha256', ARCHIVE_SHA256); bs_log('done', 'ok'); @unlink(__FILE__); bs_log('self_delete', file_exists(__FILE__) ? 'failed' : 'ok');
    } finally { $db->close(); }
}
try { bs_run(); } catch (Throwable $e) { bs_log('error', $e->getMessage()); bs_log('done', 'failed'); exit(1); }
