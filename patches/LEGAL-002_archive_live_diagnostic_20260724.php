<?php
declare(strict_types=1);

/*
 * LEGAL-002 read-only live diagnostic.
 * It never INSERTs, UPDATEs, DELETEs, ALTERs, or clears cache. It only reads
 * information, information_description, information_to_store, and SEO rows.
 */
if (PHP_SAPI !== 'cli' && !headers_sent()) header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors', '1');
const PATCH_NAME = 'LEGAL-002_archive_live_diagnostic_20260724';
const LANG = 4;
const OFFER_TITLE = 'Публічна оферта';
const ARCHIVE_TITLE = 'Публічна оферта — архів 26.05.2026';
const ARCHIVE_SLUG = 'publichna-oferta-arhiv-2026-05-26';
function out(string $key, string $value = ''): void { echo $key . ($value === '' ? '' : '=' . $value) . PHP_EOL; }
function fail(string $message): void { throw new RuntimeException($message); }
function table(string $prefix, string $name): string { $value = $prefix . $name; if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) fail('Unsafe DB table name'); return $value; }
function exists(mysqli $db, string $table): bool { $sql = "SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'"; $r = $db->query($sql); $yes = $r->num_rows === 1; $r->free(); return $yes; }
function json_line(string $key, array $row): void { out($key, (string)json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)); }
function columns(mysqli $db, string $table): array { $r = $db->query('SHOW COLUMNS FROM `' . $table . '`'); $fields = []; while ($row = $r->fetch_assoc()) $fields[] = (string)$row['Field']; $r->free(); return $fields; }
function title_rows(mysqli $db, string $desc, string $title): array { $sql = 'SELECT information_id, language_id, title, CHAR_LENGTH(description) AS description_length, SHA2(description,256) AS description_sha256 FROM `' . $desc . '` WHERE language_id=' . LANG . " AND title='" . $db->real_escape_string($title) . "'"; $r = $db->query($sql); $rows = $r->fetch_all(MYSQLI_ASSOC); $r->free(); return $rows; }
function run(): void {
    $cwd = getcwd(); if (!is_string($cwd) || $cwd === '') fail('Cannot determine cwd');
    out('patch', PATCH_NAME); out('mode', 'read_only'); out('cwd', $cwd); out('time', date('c'));
    if (!is_file($cwd . '/config.php')) fail('config.php not found. Run from public_html.');
    require_once $cwd . '/config.php';
    foreach (['DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PREFIX'] as $constant) if (!defined($constant)) fail('Missing config constant: ' . $constant);
    if (!extension_loaded('mysqli')) fail('mysqli extension is not loaded');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, defined('DB_PORT') ? (int)DB_PORT : 3306); $db->set_charset('utf8mb4');
    try {
        $prefix = (string)DB_PREFIX; $info = table($prefix, 'information'); $desc = table($prefix, 'information_description'); $store = table($prefix, 'information_to_store'); $seo = table($prefix, 'seo_url'); $alias = table($prefix, 'url_alias');
        foreach ([$info, $desc, $store, $seo, $alias] as $candidate) out('table_' . $candidate, exists($db, $candidate) ? 'yes' : 'no');
        foreach ([$info, $desc] as $required) if (!exists($db, $required)) fail('Required table missing: ' . $required);
        out('columns_' . $info, implode(',', columns($db, $info))); out('columns_' . $desc, implode(',', columns($db, $desc)));
        $offerRows = title_rows($db, $desc, OFFER_TITLE); $archiveRows = title_rows($db, $desc, ARCHIVE_TITLE);
        out('live_offer_rows', (string)count($offerRows)); foreach ($offerRows as $row) json_line('live_offer', $row);
        out('archive_description_rows', (string)count($archiveRows)); foreach ($archiveRows as $row) json_line('archive_description', $row);
        foreach ($archiveRows as $row) {
            $id = (int)$row['information_id'];
            $r = $db->query('SELECT * FROM `' . $info . '` WHERE information_id=' . $id); $infoRows = $r->fetch_all(MYSQLI_ASSOC); $r->free(); out('archive_information_rows', (string)count($infoRows)); foreach ($infoRows as $infoRow) json_line('archive_information', $infoRow);
            if (exists($db, $store)) { $r = $db->query('SELECT * FROM `' . $store . '` WHERE information_id=' . $id); $storeRows = $r->fetch_all(MYSQLI_ASSOC); $r->free(); out('archive_store_rows', (string)count($storeRows)); foreach ($storeRows as $storeRow) json_line('archive_store', $storeRow); }
        }
        if (exists($db, $seo)) { out('columns_' . $seo, implode(',', columns($db, $seo))); $r = $db->query("SELECT * FROM `" . $seo . "` WHERE keyword='" . $db->real_escape_string(ARCHIVE_SLUG) . "'"); $rows = $r->fetch_all(MYSQLI_ASSOC); $r->free(); out('archive_seo_rows', (string)count($rows)); foreach ($rows as $row) json_line('archive_seo', $row); }
        if (exists($db, $alias)) { $r = $db->query("SELECT * FROM `" . $alias . "` WHERE keyword='" . $db->real_escape_string(ARCHIVE_SLUG) . "'"); $rows = $r->fetch_all(MYSQLI_ASSOC); $r->free(); out('archive_alias_rows', (string)count($rows)); foreach ($rows as $row) json_line('archive_alias', $row); }
        out('done', 'ok'); @unlink(__FILE__); out('self_delete', file_exists(__FILE__) ? 'failed' : 'ok');
    } finally { $db->close(); }
}
try { run(); } catch (Throwable $e) { out('error', $e->getMessage()); out('done', 'failed'); exit(1); }
