<?php
declare(strict_types=1);

/*
 * LEGAL-002b-3DP — work package 2 of 4.
 *
 * WHAT THIS DOES
 *   Replaces the body of the live Обмін і повернення page
 *   (ocp5_information_description id=2, language_id=4) with the owner-approved
 *   06.08.2026 draft. Nothing else on the row changes: title, meta_title,
 *   meta_description and meta_keyword are all left exactly as they are.
 *
 * STORAGE-FORM NOTE — READ BEFORE DEPLOY
 *   The current id=2 body is stored HTML-ENTITY-ENCODED (&lt;p&gt;…), unlike
 *   id=3 and id=6 which store raw HTML. This patch writes RAW HTML, matching the
 *   offer/archive pages. Both forms render identically, because
 *   catalog/controller/information/information.php runs the value through
 *   html_entity_decode(..., ENT_QUOTES, 'UTF-8') before the template prints it,
 *   and OpenCart's Twig runs with autoescape => false. Verified against
 *   backup-8.5.2026_10-49-27_boosters. The full previous row is backed up as
 *   JSON before the write, so this is reversible.
 *
 * PRECONDITION HANDLING
 *   The 2026-08-05 backup value hashes to PREV_SHA256_SNAPSHOT. The patch does
 *   NOT hard-fail if the live row has drifted from that snapshot (the owner may
 *   have edited the page since) — it records the actual pre-change hash in the
 *   backup and in the run log. The hard gate is the post-write SHA-256 check.
 *
 * NOT TOUCHED
 *   Every other column on id=2, every other information_id, seo_url, layouts,
 *   sitemap.xml, robots.txt, .htaccess, checkout, payment, Merchant feed.
 *
 * ROLLBACK
 *   Restore `description` for information_id=2 / language_id=4 from
 *   _patch_backups/<patch>-<ts>/db/return_page_before.json
 */

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}
error_reporting(E_ALL);
ini_set('display_errors', '1');

const PATCH_NAME            = 'LEGAL-002b-3DP_return-page-revision_20260806';
const LANGUAGE_ID           = 4;
const RETURN_ID             = 2;
const RETURN_TITLE          = 'Обмін і повернення';
const RETURN_NEW_SHA256     = '9c3304c4d7f8ecc1665bce589976e04ddbea91dee992def6810fdf12304e00b8';
const PREV_SHA256_SNAPSHOT  = 'd9e8b4868a5d30024ebc82a5d22afe184ad338d81e1031e2c3cf9c3b7193d868';
const TELEGRAM_URL          = 'https://telegram.me/BoosterShop_Support_bot';

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
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($json) || file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) bs_fail('Cannot write DB backup: ' . $name);
    bs_log('backup_db', $path);
}
function bs_self_delete(): void {
    @unlink(__FILE__);
    bs_log('self_delete', file_exists(__FILE__) ? 'failed' : 'ok');
}

function bs_return_html(): string { return bs_blob(<<<'B64'
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
ccakuNi3eOqlgID1FNt77Ke/AWITjhmeGQAA
B64, RETURN_NEW_SHA256, 'return-page HTML'); }

function bs_desc_row(mysqli $db, string $table, int $id): ?array {
    $rows = bs_select($db,
        'SELECT information_id, language_id, title, description, meta_title, meta_description, meta_keyword'
        . ' FROM `' . $table . '` WHERE information_id = ? AND language_id = ?', 'ii', [$id, LANGUAGE_ID]);
    return $rows[0] ?? null;
}

function bs_run(): void {
    $cwd = getcwd();
    if (!is_string($cwd) || $cwd === '') bs_fail('Cannot determine cwd');
    bs_log('patch', PATCH_NAME); bs_log('cwd', $cwd); bs_log('time', date('c'));

    $config = bs_path($cwd, 'config.php');
    if (!is_file($config)) bs_fail('config.php not found. Run this patch from ~/public_html.');

    bs_lint_self();
    require_once $config;

    // --- content gates, before any DB work -------------------------------
    $html = bs_return_html();
    bs_expect($html, '<h2>', 6, 'return page');
    bs_expect($html, '<h2>Повернення бустерів та Mystery Box</h2>', 1, 'return page');
    bs_expect($html, '<h2>3D-товари</h2>', 1, 'return page');
    bs_expect($html, '14 календарних днів', 1, 'return page');
    bs_expect($html, TELEGRAM_URL, 1, 'return page');
    bs_expect($html, 'Написати в Telegram', 1, 'return page');
    bs_log('return_h2', '6');
    bs_log('telegram_button', 'present and unchanged');

    $backupDir = bs_path($cwd, '_patch_backups/' . PATCH_NAME . '-' . date('Ymd-His'));
    if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) bs_fail('Cannot create backup directory');
    bs_log('backup_dir', $backupDir);

    $db = bs_connect();
    try {
        $prefix = (string) DB_PREFIX;
        $desc   = bs_table($prefix, 'information_description');
        if (!bs_table_exists($db, $desc)) bs_fail('Required table not found: ' . $desc);
        bs_require_columns(bs_columns($db, $desc),
            ['information_id','language_id','title','description','meta_title','meta_description','meta_keyword'], $desc);

        $row = bs_desc_row($db, $desc, RETURN_ID);
        if ($row === null) bs_fail('Row information_id=2 / language_id=4 not found');
        if ((string) $row['title'] !== RETURN_TITLE) {
            bs_fail('information_id=2 title is "' . $row['title'] . '", expected "' . RETURN_TITLE . '" — refusing to write');
        }

        $beforeSha = hash('sha256', (string) $row['description']);
        bs_log('live_sha256_before', $beforeSha);

        if ($beforeSha === RETURN_NEW_SHA256) {
            bs_log('already_applied', 'yes');
            bs_log('done', 'ok');
            bs_self_delete();
            return;
        }

        bs_log('matches_20260805_snapshot', $beforeSha === PREV_SHA256_SNAPSHOT ? 'yes' : 'NO — page was edited since the backup; previous body is preserved in the JSON backup');
        bs_log('previous_storage_form', strpos((string) $row['description'], '&lt;') !== false ? 'entity-encoded' : 'raw HTML');

        // Telegram button must have been there before, and must survive.
        $decodedBefore = html_entity_decode((string) $row['description'], ENT_QUOTES, 'UTF-8');
        bs_log('telegram_url_before', strpos($decodedBefore, TELEGRAM_URL) !== false ? 'present' : 'ABSENT');

        bs_json_backup($backupDir, 'return_page_before', [
            'table' => $desc, 'row' => $row, 'description_sha256' => $beforeSha,
        ]);

        $db->begin_transaction();
        try {
            $stmt = $db->prepare('UPDATE `' . $desc . '` SET description = ? WHERE information_id = ? AND language_id = ?');
            $id = RETURN_ID; $lang = LANGUAGE_ID;
            $stmt->bind_param('sii', $html, $id, $lang);
            $stmt->execute(); $stmt->close();

            $verify = bs_desc_row($db, $desc, RETURN_ID);
            if ($verify === null || hash('sha256', (string) $verify['description']) !== RETURN_NEW_SHA256) {
                bs_fail('Return-page SHA-256 verification failed after write');
            }
            if ((string) $verify['title'] !== (string) $row['title']) bs_fail('title changed unexpectedly');
            if ((string) $verify['meta_title'] !== (string) $row['meta_title']) bs_fail('meta_title changed unexpectedly');
            if ((string) $verify['meta_description'] !== (string) $row['meta_description']) bs_fail('meta_description changed unexpectedly');
            if ((string) $verify['meta_keyword'] !== (string) $row['meta_keyword']) bs_fail('meta_keyword changed unexpectedly');

            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }

        bs_log('return_sha256', RETURN_NEW_SHA256);
        bs_log('meta_fields', 'unchanged');
        bs_log('done', 'ok');
        bs_self_delete();
    } finally {
        $db->close();
    }
}

try {
    bs_run();
} catch (Throwable $e) {
    bs_log('error', $e->getMessage());
    bs_log('done', 'failed');
    exit(1);
}