<?php
declare(strict_types=1);

/**
 * UI-FIX — one-word typo in the homepage FAQ: "деку-бокси" → "дек-бокси".
 *
 * The FAQ was installed by patches/UI-FIX_cms-content_20260903.php (already run,
 * self-deleted). Its HTML lives entity-encoded inside the JSON in
 * `ocp5_module.setting` for module_id 9 ("Головна SEO"), in the answer to
 * "Чи продаєте аксесуари для зберігання карток?":
 *
 *   Так: протектори (sleeves), деку-бокси, плеймати, біндери та листи-кишені…
 *
 * "дек-бокс(и)" is the spelling used consistently across this repo's 3D-P
 * content rules (handoff_3D-P-CARDCONTENT_chatgpt-master_20260816.md and
 * others). "деку-бокси" exists only in the FAQ copy this batch shipped.
 * Confirmed on the live homepage 2026-09-04: exactly one occurrence rendered.
 *
 * Scope: this one word in this one module row. Nothing else — not the FAQ
 * structure, not the other three answers, not the intro paragraphs, not the
 * module's title, status or placement.
 *
 * The word contains no character that `htmlspecialchars()` or `json_encode()`
 * escapes, and the FAQ was stored with JSON_UNESCAPED_UNICODE, so it sits
 * literally in the stored blob. The patch therefore edits the raw `setting`
 * string rather than decoding and re-encoding the JSON: a re-encode could
 * perturb bytes far outside the fix. It still parses the JSON before and after
 * as a validity gate, and proves that the ONLY byte-level difference is the
 * intended word by replacing the new spelling back and comparing to the
 * original.
 *
 * DB WRITE — a single targeted UPDATE of one column in one row.
 * Owner-requested 2026-09-04 (AGENTS.md convention C6).
 *
 * ROLLBACK
 * --------
 * `restore.sql` is written into the backup directory BEFORE the write and holds
 * the complete previous value of the column:
 *
 *   UPDATE `ocp5_module` SET `setting` = '<previous>' WHERE `module_id` = 9;
 *
 * Usage (from ~/public_html):
 *   php UI-FIX_faq-typo-dek-boksy_20260904.php --dry-run
 *   php UI-FIX_faq-typo-dek-boksy_20260904.php
 */

const PATCH_ID = 'UI-FIX_faq-typo-dek-boksy_20260904';
const SEO_MODULE_ID = 9;
const WRONG = 'деку-бокси';
const RIGHT = 'дек-бокси';
/** Anything sharing this stem is reported before the write, not silently left. */
const STEM = 'деку-бокс';
const ANSWER_MARKER = 'Чи продаєте аксесуари для зберігання карток?';

function out(string $key, string $value) {
    echo $key . '=' . $value . PHP_EOL;
}

function fail(string $message) {
    throw new RuntimeException($message);
}

function need(bool $ok, string $message) {
    if (!$ok) {
        fail($message);
    }
}

function php_lint() {
    $output = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $output, $code);
    need($code === 0, 'php_lint_failed:' . implode(' | ', $output));
    out('php_lint', 'ok');
}

/**
 * @return array{0: mysqli, 1: string}
 */
function connect(): array {
    need(PHP_SAPI === 'cli', 'cli_only');
    $cwd = getcwd();
    need($cwd !== false, 'cwd_unavailable');
    need(is_file($cwd . DIRECTORY_SEPARATOR . 'config.php'), 'run_from_public_html_required');
    require $cwd . DIRECTORY_SEPARATOR . 'config.php';
    foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PORT', 'DB_PREFIX'] as $constant) {
        need(defined($constant), 'config_constant_missing:' . $constant);
    }
    need((string)DB_PREFIX === 'ocp5_', 'db_prefix_mismatch_expected_ocp5_');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli((string)DB_HOSTNAME, (string)DB_USERNAME, (string)DB_PASSWORD, (string)DB_DATABASE, (int)DB_PORT);
    $db->set_charset('utf8mb4');

    return [$db, (string)DB_PREFIX];
}

/**
 * @param array<int, string|int> $params
 * @return array<int, array<string, mixed>>
 */
function rows(mysqli $db, string $sql, array $params = []): array {
    $statement = $db->prepare($sql);
    need($statement instanceof mysqli_stmt, 'prepare_failed:' . $db->error);
    if ($params !== []) {
        $types = str_repeat('s', count($params));
        $bind = [$types];
        foreach ($params as $index => $value) {
            $params[$index] = (string)$value;
            $bind[] = &$params[$index];
        }
        need((bool)call_user_func_array([$statement, 'bind_param'], $bind), 'bind_failed:' . $statement->error);
    }
    need($statement->execute(), 'execute_failed:' . $statement->error);
    // This host's mysqli is built WITHOUT mysqlnd, so the mysqlnd-only
    // prepared-statement result helpers are unavailable and fatal here — see
    // diagnostics/LEGAL-002_offer_mono_pumb_archive_v3_report_20260724.md.
    $metadata = $statement->result_metadata();
    need($metadata instanceof mysqli_result, 'result_failed');
    $row = [];
    $refs = [];
    foreach ($metadata->fetch_fields() as $field) {
        $row[$field->name] = null;
        $refs[] = &$row[$field->name];
    }
    need((bool)call_user_func_array([$statement, 'bind_result'], $refs), 'result_bind_failed:' . $statement->error);
    $out = [];
    while ($statement->fetch()) {
        // bind_result binds by reference — copy per iteration.
        $copy = [];
        foreach ($row as $key => $value) {
            $copy[$key] = $value;
        }
        $out[] = $copy;
    }
    $metadata->free();
    $statement->close();

    return $out;
}

/**
 * @param array<int, string|int> $params
 */
function execute(mysqli $db, string $sql, array $params = []): int {
    $statement = $db->prepare($sql);
    need($statement instanceof mysqli_stmt, 'prepare_failed:' . $db->error);
    if ($params !== []) {
        $types = str_repeat('s', count($params));
        $bind = [$types];
        foreach ($params as $index => $value) {
            $params[$index] = (string)$value;
            $bind[] = &$params[$index];
        }
        need((bool)call_user_func_array([$statement, 'bind_param'], $bind), 'bind_failed:' . $statement->error);
    }
    need($statement->execute(), 'execute_failed:' . $statement->error);
    $affected = $statement->affected_rows;
    $statement->close();

    return $affected;
}

function quote(mysqli $db, string $value): string {
    return "'" . $db->real_escape_string($value) . "'";
}

$arguments = array_slice($argv ?? [], 1);
need($arguments === [] || $arguments === ['--dry-run'], 'usage:php ' . basename(__FILE__) . ' [--dry-run]');
$dryRun = $arguments === ['--dry-run'];

php_lint();

$transaction = false;
$db = null;

try {
    list($db, $prefix) = connect();

    $moduleTable = $prefix . 'module';
    rows($db, 'SELECT 1 FROM `' . $moduleTable . '` LIMIT 0');

    $moduleRows = rows(
        $db,
        'SELECT `module_id`, `name`, `code`, `setting` FROM `' . $moduleTable . '` WHERE `module_id` = ?',
        [SEO_MODULE_ID]
    );
    need(count($moduleRows) === 1, 'seo_module_missing');
    $before = (string)$moduleRows[0]['setting'];
    need((string)$moduleRows[0]['code'] === 'opencart.html', 'seo_module_unexpected_code:' . (string)$moduleRows[0]['code']);

    // Validity gate on the way in — never write back something that was already
    // not parseable.
    $decodedBefore = json_decode($before, true);
    need(is_array($decodedBefore) && isset($decodedBefore['module_description']), 'seo_module_setting_shape');

    // Survey the whole stored blob for the stem, as requested, before deciding.
    $stemCount = substr_count($before, STEM);
    $wrongCount = substr_count($before, WRONG);
    $rightCount = substr_count($before, RIGHT);
    out('survey_stem_' . STEM, (string)$stemCount);
    out('survey_' . WRONG, (string)$wrongCount);
    out('survey_' . RIGHT, (string)$rightCount);

    if ($wrongCount === 0 && $stemCount === 0) {
        if ($dryRun) {
            out('already_applied', 'yes');
            out('dry_run', 'ok');
            exit(0);
        }
        out('already_applied', 'yes');
        out('done', 'ok');
        @unlink(__FILE__);
        exit(0);
    }

    // Every stem hit must be the exact word this patch knows how to fix. If the
    // blob carries another declension ("деку-боксів" and so on) the safe move is
    // to stop and let a human decide, not to half-fix the row.
    need(
        $stemCount === $wrongCount,
        'unexpected_stem_variant:stem=' . $stemCount . ',exact=' . $wrongCount
            . ' - the stored HTML contains a form of "' . STEM . '" this patch does not handle; refusing to guess'
    );
    need($wrongCount === 1, 'unexpected_occurrence_count:' . $wrongCount . ' - expected exactly 1');

    // The occurrence must be the one in the accessories answer, not somewhere
    // else the owner edited later.
    $answerAt = strpos($before, ANSWER_MARKER);
    $wrongAt = strpos($before, WRONG);
    need($answerAt !== false, 'accessories_answer_not_found');
    need($wrongAt !== false && $wrongAt > $answerAt, 'occurrence_outside_accessories_answer');
    out('occurrence_in_accessories_answer', 'yes');

    $after = str_replace(WRONG, RIGHT, $before);
    need($after !== $before, 'no_change_produced');

    // The only byte-level difference must be the intended word.
    need(str_replace(RIGHT, WRONG, $after) === $before, 'unexpected_collateral_change');

    $decodedAfter = json_decode($after, true);
    need(is_array($decodedAfter), 'result_not_valid_json');
    need(
        array_keys($decodedAfter) === array_keys($decodedBefore),
        'json_structure_changed'
    );
    out('json_valid_after', 'yes');

    out('plan', WRONG . ' -> ' . RIGHT . ' in ' . $moduleTable . '.setting module_id=' . SEO_MODULE_ID);

    if ($dryRun) {
        out('dry_run', 'ok');
        exit(0);
    }

    $root = rtrim((string)(getcwd() ?: __DIR__), "/\\");
    $backupDir = $root . '/_patch_backups/' . PATCH_ID . '-' . gmdate('Ymd-His');
    need(!file_exists($backupDir), 'backup_path_exists');
    need(mkdir($backupDir, 0750, true), 'backup_create_failed');

    need(
        file_put_contents(
            $backupDir . '/before.json',
            json_encode($moduleRows[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            LOCK_EX
        ) !== false,
        'backup_write_failed'
    );

    $restore = '-- ' . PATCH_ID . " rollback\nSTART TRANSACTION;\n"
        . 'UPDATE `' . $moduleTable . '` SET `setting` = ' . quote($db, $before)
        . ' WHERE `module_id` = ' . SEO_MODULE_ID . ";\nCOMMIT;\n";
    need(file_put_contents($backupDir . '/restore.sql', $restore, LOCK_EX) !== false, 'restore_write_failed');
    out('backup', str_replace($root . '/', '', $backupDir));

    $db->begin_transaction();
    $transaction = true;

    $affected = execute(
        $db,
        'UPDATE `' . $moduleTable . '` SET `setting` = ? WHERE `module_id` = ?',
        [$after, SEO_MODULE_ID]
    );
    need($affected === 1, 'update_failed:affected=' . $affected);

    $check = rows($db, 'SELECT `setting` FROM `' . $moduleTable . '` WHERE `module_id` = ?', [SEO_MODULE_ID]);
    need(count($check) === 1, 'verify_row_missing');
    $stored = (string)$check[0]['setting'];
    need($stored === $after, 'verify_value_mismatch');
    need(substr_count($stored, WRONG) === 0, 'verify_typo_remains');
    need(substr_count($stored, STEM) === 0, 'verify_stem_remains');
    need(substr_count($stored, RIGHT) === $rightCount + 1, 'verify_replacement_count');
    need(is_array(json_decode($stored, true)), 'verify_json_invalid');

    $db->commit();
    $transaction = false;

    out('replacements', '1');
    out('done', 'ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    if ($transaction && $db instanceof mysqli) {
        $db->rollback();
    }
    fwrite(STDERR, 'ERROR=' . $error->getMessage() . PHP_EOL);
    exit(1);
}
