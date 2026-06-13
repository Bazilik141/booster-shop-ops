<?php
declare(strict_types=1);

/**
 * patch-content-rebrand-lowpull_20260612
 *
 * Прибирає всі згадки "Low pull" та "баластних/актуального stock"
 * з описів товарів і категорій в OpenCart DB.
 *
 * Запуск: php patch-content-rebrand-lowpull_20260612.php
 * Dry-run: php patch-content-rebrand-lowpull_20260612.php --dry-run
 *
 * Завантажити в ~/public_html і запустити звідти.
 */

const PATCH_ID = 'patch-content-rebrand-lowpull_20260612';

$dryRun = in_array('--dry-run', $argv ?? [], true);

function out(string $m): void  { echo $m . PHP_EOL; }
function fail(string $m): never { fwrite(STDERR, 'ERROR: ' . $m . PHP_EOL); exit(1); }

out('patch=' . PATCH_ID);
out('dry_run=' . ($dryRun ? 'yes' : 'no'));
out('time=' . date('c'));
out('');

// --- DB connect via OpenCart config ---
$config = __DIR__ . '/config.php';
if (!is_file($config)) {
    fail('config.php not found — run from OpenCart public_html');
}
require_once $config;

if (!defined('DB_HOSTNAME') || !defined('DB_USERNAME') || !defined('DB_PASSWORD') || !defined('DB_DATABASE')) {
    fail('DB constants not defined in config.php');
}

$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'oc_';

// --- mysqli connect ---
$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
if ($db->connect_errno) {
    fail('DB connect failed: ' . $db->connect_error);
}
$db->set_charset('utf8mb4');

out('db_connected=yes');
out('prefix=' . $prefix);
out('');

// ---------------------------------------------------------------------------
// Список замін: [old_string, new_string, label]
// ---------------------------------------------------------------------------
$replacements = [

    // 1. Pokémon category description — перелік форматів
    [
        'sealed-паки, unweighed бустери та low pull формати.',
        'sealed-паки, unweighed та Outlet Mix бустери.',
        'pkm_cat_desc_format_list',
    ],

    // 2. Pokémon category description — пояснення Low Pull
    [
        'Якщо товар має формат <strong>Low Pull</strong> — це прямо зазначено в назві та описі.',
        'Якщо товар є форматом <strong>Outlet Mix</strong> — це прямо зазначено в назві та описі.',
        'pkm_cat_desc_format_note',
    ],

    // 3. Pokémon FAQ — питання
    [
        '<button class="bs-faq__question" aria-expanded="false">Що таке Low Pull?</button>',
        '<button class="bs-faq__question" aria-expanded="false">Що таке Outlet Mix?</button>',
        'pkm_faq_question',
    ],

    // 4. Pokémon FAQ — відповідь
    [
        '<p>Low Pull — уцінений формат бустерів із дуже низькою ймовірністю отримати holo, SR або інші chase-карти. Такі бустери купують для недорогого відкриття, збору commons/uncommons або знайомства з сетом.</p>',
        '<p><a href="https://boostershop.website/product/Pokemon-Japanese-outlet-booster">Outlet Mix</a> — японські sealed-бустери Pokémon TCG зі змішаних сетів за зниженою ціною. Підходить для недорогого відкриття, збору commons/uncommons або знайомства з різними релізами.</p>',
        'pkm_faq_answer',
    ],

    // 5. One Piece Sets — опис категорії (inline)
    [
        'Склад змінюється залежно від актуального stock, але завжди з актуальних релізів без Low Pull і застарілих залишків.',
        'Склад змінюється, але завжди містить бустери з актуальних релізів.',
        'op_sets_cat_desc_stock',
    ],

    // 6. One Piece Sets — FAQ / Mystery Mix guarantee
    [
        'Гарантуємо актуальні релізи без Low Pull.',
        'Гарантуємо бустери з актуальних релізів.',
        'op_sets_faq_guarantee',
    ],

    // 7. Mystery Mix product — абзац про склад
    [
        "релізах, не Low Pull і не застарілих залишках.",
        "релізах.",
        'op_mix_product_desc_lowpull',
    ],

    // 8. Mystery Mix product — пункт ul «баластних»
    [
        "<li>склад із актуального stock — без «баластних» залишків</li>",
        "<li>мінімум 2 різні японські One Piece сети в одному боксі</li>",
        'op_mix_product_ul_ballast',
    ],

    // 9. Mystery Mix product — FAQ/гарантія
    [
        'Гарантуємо: мінімум 2 різні японські One Piece сети, актуальні релізи без Low Pull і застарілих залишків.',
        'Гарантуємо: мінімум 2 різні японські One Piece сети з актуальних релізів.',
        'op_mix_faq_guarantee',
    ],

    // 10. Запасний варіант — "без Low Pull" у будь-якому контексті
    [
        'Гарантуємо актуальні релізи без Low Pull.</p>',
        'Гарантуємо бустери з актуальних релізів.</p>',
        'generic_guarantee_lowpull',
    ],
];

// ---------------------------------------------------------------------------
// Таблиці для оновлення: [table, id_col, text_col]
// ---------------------------------------------------------------------------
$tables = [
    [$prefix . 'category_description', 'category_id', 'description'],
    [$prefix . 'product_description',  'product_id',  'description'],
];

$totalFound   = 0;
$totalChanged = 0;

foreach ($tables as [$table, $idCol, $textCol]) {
    out('--- scanning: ' . $table . ' ---');

    // Шукаємо рядки з хоча б однією проблемною фразою
    $whereParts = [];
    foreach (array_column($replacements, 0) as $needle) {
        $escaped = $db->real_escape_string($needle);
        $whereParts[] = "`{$textCol}` LIKE '%" . $escaped . "%'";
    }
    $whereClause = implode(' OR ', $whereParts);

    $result = $db->query("SELECT `{$idCol}`, `{$textCol}` FROM `{$table}` WHERE {$whereClause}");
    if (!$result) {
        fail('Query failed on ' . $table . ': ' . $db->error);
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    out('rows_matched=' . count($rows));

    foreach ($rows as $row) {
        $id   = $row[$idCol];
        $orig = $row[$textCol];
        $text = $orig;

        $appliedLabels = [];
        foreach ($replacements as [$old, $new, $label]) {
            if (str_contains($text, $old)) {
                $text = str_replace($old, $new, $text);
                $appliedLabels[] = $label;
                $totalFound++;
            }
        }

        if ($text === $orig) {
            continue;
        }

        out(sprintf('  id=%s changes=[%s]', $id, implode(',', $appliedLabels)));
        $totalChanged++;

        if (!$dryRun) {
            $escaped = $db->real_escape_string($text);
            $idEsc   = (int) $id;
            if (!$db->query("UPDATE `{$table}` SET `{$textCol}` = '{$escaped}' WHERE `{$idCol}` = {$idEsc}")) {
                fail('Update failed: ' . $db->error);
            }
            out('  written=yes');
        } else {
            out('  written=no (dry-run)');
        }
    }

    out('');
}

$db->close();

out('--- summary ---');
out('replacements_found=' . $totalFound);
out('rows_changed='       . $totalChanged);
out('dry_run='            . ($dryRun ? 'yes — no DB writes' : 'no — DB updated'));

if ($totalFound === 0) {
    out('status=ALREADY_CLEAN — nothing to do');
} elseif ($dryRun) {
    out('status=DRY_RUN_DONE — re-run without --dry-run to apply');
} else {
    out('status=DONE');
}
