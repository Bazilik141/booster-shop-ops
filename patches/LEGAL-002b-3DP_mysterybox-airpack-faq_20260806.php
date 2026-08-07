<?php
declare(strict_types=1);

/*
 * LEGAL-002b-3DP — work package 4 of 4.
 *
 * WHAT THIS DOES
 *   Adds the owner-approved AirPack disclosure block and one extra FAQ entry to
 *   the four live Mystery Box product descriptions
 *   (ocp5_product_description.description, language_id=4).
 *
 * LIVE DISCOVERY THAT SHAPED THIS PATCH (backup-8.5.2026_10-49-27_boosters)
 *   1. PRODUCT ROWS. The catalog contains exactly FOUR Mystery Box products, not
 *      the six the handoff allowed for:
 *        77  Містері бокс Pokémon TCG: Mystery Mix Standard      (капсула wording)
 *        85  Містері бокс One Piece Card Game: Mystery Mix Standard (скриня)
 *        110 Містері бокс Pokémon TCG: Mystery Mix XL            (капсула)
 *        111 Містері бокс One Piece Card Game: Mystery Mix XL     (скриня)
 *      No "Item" format product exists yet — consistent with there being no 3D
 *      SKUs for offer §6.12 to draw from. When the Item SKUs are created, they
 *      need their own round of this content.
 *   2. FAQ MECHANISM. There is no separate FAQ table or field. FAQ content lives
 *      inside the product description, and catalog/view/javascript/bs-faq.js
 *      normalizes it. Its first and highest-priority parse path reads an existing
 *      <section class="bs-faq-accordion"> and takes ONLY its .bs-faq-item
 *      children. All four rows already use that canonical structure with six
 *      items. A bare <h3>+<p> appended after the section — the shape of the draft
 *      file — would therefore have been silently ignored. Each new entry is built
 *      as a full seventh .bs-faq-item with the row's own data-bs-faq-id prefix
 *      and matching aria wiring (…-button-7 / …-panel-7).
 *   3. PLACEHOLDER CONTAINER. The handoff asked whether an existing PDP
 *      info/disclaimer class should be used instead of the draft's bare
 *      <p><strong>. boostershop-ds.css has no such class — the only "hint"
 *      classes (.bs-field-hint, .bs-co-field-hint, .bs-installment-hint) are
 *      checkout form-field helpers, not content blocks. The draft's bare
 *      <p><strong> therefore ships as written. This patch adds NO CSS, no new
 *      class, no !important and no override.
 *   4. ANCHOR. None of the rows has a «Комплектація» heading; the contents block
 *      is «<h2>Що входить у …</h2>» + <ul>, and the wording differs per row
 *      (id=85 says "Mystery box", the others "Mystery Mix"). The AirPack block
 *      is placed directly after that row's own contents </ul>.
 *   5. STORAGE FORM. These descriptions are stored HTML-entity-encoded, and
 *      catalog/controller/product/product.php html_entity_decode()s them. The
 *      replacement bodies keep the identical encoding, so the storage form does
 *      not change.
 *
 * STRUCTURED DATA — bs-merchant-schema-qa APPLIES TO THIS PACKAGE
 *   Two schema surfaces move here, and both need owner spot-checking:
 *     a) bs-faq.js emits Schema.org FAQPage microdata (itemtype FAQPage /
 *        Question / acceptedAnswer). A seventh Question is added per product.
 *     b) The Product JSON-LD in product.twig contains
 *        "description": {{ description|striptags … }}, so the AirPack sentence
 *        and the new FAQ text enter the Product description field.
 *   No schema template, feed file or markup was edited by this patch — only the
 *   content those templates render.
 *
 * HOW THE WRITE IS DONE
 *   No runtime string surgery. The complete replacement body for each product
 *   was generated offline from the live value and is embedded here whole. The
 *   patch verifies the live row still hashes to prev_sha256 before writing, then
 *   verifies the written row hashes to new_sha256. If a description has been
 *   edited since the 2026-08-05 backup the patch refuses to touch that product
 *   rather than overwrite the owner's edit — the other products still apply.
 *
 * NOT TOUCHED
 *   Price, quantity, stock, SKU, model, images, attributes, categories, any
 *   non-Mystery-Box product, ocp5_product (including date_modified), sitemap,
 *   robots, .htaccess, checkout, payment, Merchant feed files.
 *
 * ROLLBACK
 *   Every prior description is stored verbatim in
 *   _patch_backups/<patch>-<ts>/db/product_<id>_before.json — restore
 *   `description` for that product_id / language_id=4.
 */

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}
error_reporting(E_ALL);
ini_set('display_errors', '1');

const PATCH_NAME  = 'LEGAL-002b-3DP_mysterybox-airpack-faq_20260806';
const LANGUAGE_ID = 4;

const PRODUCTS = [
    77 => ['name' => 'Містері бокс Pokémon TCG: Mystery Mix Standard (Японське видання)', 'prev_sha256' => '5cce9e37f43d46dc23411d8038b9d2130e27cabc9c03c11dfa56bf67e44f9f0b', 'new_sha256' => '294b3bdfaec751225f4d3cdac9b10079a004a93484fef439da3d0a1848a7ad3b', 'loader' => 'bs_desc_77'],
    85 => ['name' => 'Містері бокс One Piece Card Game: Mystery Mix Standard (Японське видання)', 'prev_sha256' => 'b1627fbe4651f3e544a38921db991830b33caa0d0f11aaf94c4ffad2c65994cf', 'new_sha256' => 'd929c861f3ce5cce09978799b17dd607e79e4ab8bf4d48d59195cd9025c58195', 'loader' => 'bs_desc_85'],
    110 => ['name' => 'Містері бокс Pokémon TCG: Mystery Mix XL (Японське видання)', 'prev_sha256' => 'bdd04de1707c2db7f2fa284ee05adb8e025b03f21df309bb1d234e7d8dec429e', 'new_sha256' => '4ca10aade01d3dfa2b46fcc03096eadba8104646e4dca34d90ae62ec1d2bd1fc', 'loader' => 'bs_desc_110'],
    111 => ['name' => 'Містері бокс One Piece Card Game: Mystery Mix XL (Японське видання)', 'prev_sha256' => '2731c7a2b67edb8d05caa4841031b2e6526f20b8bec19b099c8f2a0df8489ea5', 'new_sha256' => '1b7d35e96b2aa9c4c94decb72a0b18db2ccaf90e74cc5a82980d471997c76c09', 'loader' => 'bs_desc_111'],
];

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

function bs_desc_77(): string { return bs_blob(<<<'B64'
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
6Q4mAAA=
B64, PRODUCTS[77]['new_sha256'], 'product 77 description'); }

function bs_desc_85(): string { return bs_blob(<<<'B64'
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
XJmhDLWNzZGQB1AgrxnI1gNO/AfjEZ2e6yUAAA==
B64, PRODUCTS[85]['new_sha256'], 'product 85 description'); }

function bs_desc_110(): string { return bs_blob(<<<'B64'
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
phF/KQAA
B64, PRODUCTS[110]['new_sha256'], 'product 110 description'); }

function bs_desc_111(): string { return bs_blob(<<<'B64'
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
B64, PRODUCTS[111]['new_sha256'], 'product 111 description'); }

function bs_desc_row(mysqli $db, string $table, int $productId): ?array {
    $rows = bs_select($db,
        'SELECT product_id, language_id, name, description FROM `' . $table . '`'
        . ' WHERE product_id = ? AND language_id = ?', 'ii', [$productId, LANGUAGE_ID]);
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
    $bodies = [];
    foreach (PRODUCTS as $productId => $spec) {
        $html = call_user_func($spec['loader']);
        bs_expect($html, 'class=&quot;bs-faq-item&quot;', 7, 'product ' . $productId);
        bs_expect($html, 'AirPack', 2, 'product ' . $productId);
        bs_expect($html, '-panel-7&quot;', 2, 'product ' . $productId);
        bs_expect($html, '-button-7&quot;', 2, 'product ' . $productId);
        bs_expect($html, '&lt;/section&gt;', 1, 'product ' . $productId);
        bs_expect($html, 'Про паковання:', 1, 'product ' . $productId);
        $bodies[$productId] = $html;
    }
    bs_log('bodies_verified', (string) count($bodies));

    $backupDir = bs_path($cwd, '_patch_backups/' . PATCH_NAME . '-' . date('Ymd-His'));
    if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) bs_fail('Cannot create backup directory');
    bs_log('backup_dir', $backupDir);

    $db = bs_connect();
    try {
        $prefix = (string) DB_PREFIX;
        $desc   = bs_table($prefix, 'product_description');
        if (!bs_table_exists($db, $desc)) bs_fail('Required table not found: ' . $desc);
        bs_require_columns(bs_columns($db, $desc), ['product_id', 'language_id', 'name', 'description'], $desc);

        $applied = 0; $skipped = 0; $already = 0; $planned = [];

        // --- pass 1: decide per product, write nothing yet -------------------
        foreach (PRODUCTS as $productId => $spec) {
            $row = bs_desc_row($db, $desc, (int) $productId);
            if ($row === null) { bs_log('skip_' . $productId, 'row not found for language_id=4'); $skipped++; continue; }
            if ((string) $row['name'] !== $spec['name']) {
                bs_log('skip_' . $productId, 'name is "' . $row['name'] . '", expected "' . $spec['name'] . '" — not touching it');
                $skipped++; continue;
            }
            $sha = hash('sha256', (string) $row['description']);
            if ($sha === $spec['new_sha256']) { bs_log('already_applied_' . $productId, 'yes'); $already++; continue; }
            if ($sha !== $spec['prev_sha256']) {
                bs_log('skip_' . $productId, 'description changed since the 2026-08-05 backup (live sha=' . $sha
                    . ') — refusing to overwrite; regenerate this patch for that product');
                $skipped++; continue;
            }
            $planned[$productId] = $row;
        }
        bs_log('planned', (string) count($planned));

        if ($planned !== []) {
            foreach ($planned as $productId => $row) {
                bs_json_backup($backupDir, 'product_' . $productId . '_before', [
                    'table' => $desc, 'row' => $row,
                    'description_sha256' => hash('sha256', (string) $row['description']),
                ]);
            }

            $db->begin_transaction();
            try {
                foreach ($planned as $productId => $row) {
                    $html = $bodies[$productId];
                    $stmt = $db->prepare('UPDATE `' . $desc . '` SET description = ? WHERE product_id = ? AND language_id = ?');
                    $id = (int) $productId; $lang = LANGUAGE_ID;
                    $stmt->bind_param('sii', $html, $id, $lang);
                    $stmt->execute(); $stmt->close();

                    $verify = bs_desc_row($db, $desc, (int) $productId);
                    if ($verify === null || hash('sha256', (string) $verify['description']) !== PRODUCTS[$productId]['new_sha256']) {
                        bs_fail('SHA-256 verification failed for product ' . $productId . ' — rolling back the whole patch');
                    }
                    if ((string) $verify['name'] !== (string) $row['name']) {
                        bs_fail('product ' . $productId . ' name changed unexpectedly — rolling back');
                    }
                    bs_log('updated_' . $productId, PRODUCTS[$productId]['new_sha256']);
                    $applied++;
                }
                $db->commit();
            } catch (Throwable $e) {
                $db->rollback();
                throw $e;
            }
        }

        bs_log('applied', (string) $applied);
        bs_log('already_applied', (string) $already);
        bs_log('skipped', (string) $skipped);
        bs_log('schema_note', 'FAQPage microdata + Product JSON-LD description change — run bs-merchant-schema-qa spot-check');

        if ($skipped > 0) {
            bs_log('done', 'partial — see skip_* lines above; this file was NOT deleted so it can be re-run after regeneration');
            return;
        }
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