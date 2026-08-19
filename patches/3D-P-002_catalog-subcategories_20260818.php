<?php
declare(strict_types=1);

/*
 * 3D-P-002 — work package 1 of 4: catalog subcategories + move of the 9 accessories.
 *
 * SOURCE OF TRUTH
 *   handoffs/handoff_3D-P-002_subcategories-and-content_20260816.md (ред. 2, FINAL)
 *   Preflight evidence: diagnostics/3D-P-002_3D-P-CARDCONTENT_db-preflight_20260818.md
 *
 * WHAT THIS DOES
 *   Creates four catalog subcategories and re-parents the nine existing
 *   «Аксесуари» products into two of them. Writes only:
 *     ocp5_category, ocp5_category_description, ocp5_category_path,
 *     ocp5_category_to_store, ocp5_seo_url  — new rows only
 *     ocp5_product_to_category               — UPDATE of exactly 9 existing rows
 *   ocp5_category_to_layout is NOT written: it is empty for every existing
 *   category on this install (verified against the backup).
 *
 *     Протектори та зберігання   parent 70 (Аксесуари)            status 1
 *     Підставки та декор         parent 70 (Аксесуари)            status 1
 *     Фігурки та декор           parent 59 (Pokémon)              status 0
 *     Фігурки та декор           parent 60 (One Piece Card Game)  status 0
 *
 *   The two categories named «Фігурки та декор» under different parents are
 *   deliberate (handoff §2), not a mistake. Their SEO slugs differ, which is
 *   required — see the SEO URL note below.
 *
 * TWO STAGES — this is the handoff §4.1 gate, not an optional extra
 *   The handoff requires moving ONE product first and confirming its
 *   /product/<seo-name> URL did not change, BEFORE the other eight move.
 *   A patch cannot fetch a live URL, so the gate is the owner's:
 *
 *     php 3D-P-002_catalog-subcategories_20260818.php
 *         -> creates the 4 categories, moves ONLY product 99
 *            (ACC-005, «Акрилова підставка для карток»), then STOPS and does
 *            NOT self-delete. Prints the URL for the owner to check.
 *
 *     php 3D-P-002_catalog-subcategories_20260818.php --move-remaining
 *         -> after the owner confirms, moves the other eight and self-deletes.
 *
 *   Both stages are idempotent and report already_applied=yes on repeat.
 *
 * SEO URL — DELIBERATE DIVERGENCE FROM THE LETTER OF THE HANDOFF
 *   Handoff §5 lists the SEO URL of each subcategory as a bare slug, e.g.
 *   `protektory-ta-zberihannia`. On this install that would be wrong.
 *   catalog/model/design/seo_url.php resolves a keyword with
 *       WHERE keyword = '<part>' OR keyword LIKE '%/<part>'
 *   and catalog/controller/startup/seo_url.php splits the route on '/', so a
 *   subcategory stores its FULL PATH in `keyword`. Every live subcategory
 *   does exactly that: 'Pokemon/Pokemon-booster-box', 'more-tcg/Yu-Gi-Oh',
 *   'One-Piece/One-Piece-Boosters'. Storing a bare slug would produce
 *   /catalog/protektory-ta-zberihannia with no parent segment — inconsistent
 *   with every other subcategory on the site.
 *   Therefore the patch stores '<parent keyword>/<slug from the handoff>'.
 *   The parent keyword is READ from the database at runtime and asserted
 *   against the expected value; it is not hardcoded blind.
 *
 * PRODUCT URLS ARE CATEGORY-INDEPENDENT — asserted, not assumed
 *   ocp5_seo_url rows for products use key='product_id' and carry no category
 *   reference. The patch snapshots the seo_url row of every moved product
 *   before and after the move and fails the transaction if any byte differs.
 *   That is the DB-level proof; the owner's live check is the second gate.
 *
 * ROLLBACK
 *   Actual auto-increment IDs are written to
 *   _patch_backups/<patch>-<ts>/db/created_ids.json, and the full prior state
 *   of the nine product_to_category rows to .../db/subcategories_before.json.
 *
 *   Stage 2 (undo the move of all nine — always safe, restores the root):
 *     UPDATE ocp5_product_to_category SET category_id = 70
 *      WHERE product_id IN (95,96,97,98,99,100,112,113,114) AND category_id IN (<created_category_ids>);
 *
 *   Stage 1 (delete the four categories — only after the move is undone):
 *     DELETE FROM ocp5_seo_url               WHERE `key` = 'path' AND `value` IN (<'70_71' style values from created_ids.json>);
 *     DELETE FROM ocp5_category_to_store     WHERE category_id IN (<created_category_ids>);
 *     DELETE FROM ocp5_category_path         WHERE category_id IN (<created_category_ids>) OR path_id IN (<created_category_ids>);
 *     DELETE FROM ocp5_category_description  WHERE category_id IN (<created_category_ids>);
 *     DELETE FROM ocp5_category              WHERE category_id IN (<created_category_ids>);
 *
 *   Expected IDs at backup state (NOT hardcoded, orientation only): 71,72,73,74.
 *
 * NOT TOUCHED
 *   Every existing category row, every product row, product_to_category rows
 *   for products outside the nine, ocp5_category_to_layout, sitemap, robots,
 *   .htaccess, checkout, payment, any attribute or product table.
 */

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}
error_reporting(E_ALL);
ini_set('display_errors', '1');

const PATCH_NAME  = '3D-P-002_catalog-subcategories_20260818';
const LANGUAGE_ID = 4;
const STORE_ID    = 0;

// Parents, verified against backup-8.16.2026_08-03-55_boosters.
const PARENTS = [
    59 => ['name' => 'Pokémon',             'keyword' => 'Pokemon'],
    60 => ['name' => 'One Piece Card Game', 'keyword' => 'One-Piece'],
    70 => ['name' => 'Аксесуари',           'keyword' => 'acsesuary'],
];

// The nine products currently sitting in the root of «Аксесуари» (70).
// Names are asserted at runtime so a mis-assignment cannot pass silently.
const MOVE_FIRST = 99; // ACC-005 — the sole product going to «Підставки та декор»

const PRODUCT_MOVES = [
    // product_id => [expected model, target category slug]
    95  => ['ACC-001',     'protektory-ta-zberihannia'],
    96  => ['ACC-002',     'protektory-ta-zberihannia'],
    97  => ['ACC-003',     'protektory-ta-zberihannia'],
    98  => ['ACC-004',     'protektory-ta-zberihannia'],
    99  => ['ACC-005',     'pidstavky-ta-dekor'],
    100 => ['ACC-006',     'protektory-ta-zberihannia'],
    112 => ['ACC-007-360', 'protektory-ta-zberihannia'],
    113 => ['ACC-008',     'protektory-ta-zberihannia'],
    114 => ['ACC-009',     'protektory-ta-zberihannia'],
];

/**
 * Category content. Text is verbatim from handoff §5 — FINAL, not to be edited.
 * 'body' paragraphs are raw HTML; 'faq' is [question, answer].
 */
const CATEGORIES = [
    [
        'slug'        => 'protektory-ta-zberihannia',
        'name'        => 'Протектори та зберігання',
        'parent_id'   => 70,
        'sort_order'  => 1,
        'status'      => 1,
        'faq_id'      => 'cat-protektory',
        'faq_title'   => 'FAQ — Протектори та зберігання',
        'meta_title'  => 'Протектори, топлоадери та альбоми для карток | Booster Shop',
        'meta_desc'   => 'Протектори, топлоадери, кейси та альбоми для колекційних карток Pokémon, One Piece, MTG та інших TCG. Купити в Україні — Booster Shop.',
        'meta_kw'     => 'протектори для карток, sleeves купити, топлоадери 35PT, кейс для карток, альбом для колекційних карток',
        'h2'          => 'Протектори та зберігання карток',
        'body'        => [
            'Тут зібрано все, що допомагає захистити та зберігати колекційні картки: <strong>протектори</strong> стандартного формату, <strong>топлоадери</strong>, жорсткі акрилові кейси, аркуші з кишеньками та альбоми. Аксесуари підходять для Pokémon TCG, One Piece Card Game, Magic: The Gathering, Lorcana та інших TCG відповідного формату.',
            'Різні способи зберігання вирішують різні задачі. М\'який протектор захищає поверхню картки під час гри та щоденного використання, топлоадер додає жорсткості, а акриловий кейс підходить для окремого зберігання чи демонстрації картки. Альбоми та аркуші з кишеньками зручні, коли потрібно організувати вже цілу частину колекції.',
            'У картці кожного товару вказані його точні параметри: розміри, кількість в упаковці, матеріал, товщина та сумісний формат. Перед покупкою варто звірити їх зі способом, у який ви зберігаєте картку — наприклад, картка в додатковому протекторі потребує більше внутрішнього простору, ніж картка без нього.',
        ],
        'faq'         => [
            ['Який розмір протекторів потрібен для Pokémon, One Piece та MTG?', 'Для більшості стандартних TCG використовуються протектори приблизно формату 63×89 мм, але точний розмір залежить від конкретного товару та способу використання. Перед покупкою звірте параметри у характеристиках протектора.'],
            ['Чим топлоадер відрізняється від звичайного протектора?', 'Протектор — гнучкий чохол, який насамперед захищає поверхню картки від подряпин і забруднень. Топлоадер — жорсткий тримач, який додатково допомагає захистити картку від згинання під час зберігання або транспортування.'],
            ['Чи можна зберігати картку в кейсі або топлоадері разом із протектором?', 'Залежить від внутрішнього розміру конкретного кейса або топлоадера та товщини протектора. Сумісність потрібно перевіряти в характеристиках конкретного товару.'],
        ],
    ],
    [
        'slug'        => 'pidstavky-ta-dekor',
        'name'        => 'Підставки та декор',
        'parent_id'   => 70,
        'sort_order'  => 2,
        'status'      => 1,
        'faq_id'      => 'cat-pidstavky',
        'faq_title'   => 'FAQ — Підставки та декор',
        'meta_title'  => 'Підставки для карток та декор колекції | Booster Shop',
        'meta_desc'   => 'Підставки для колекційних карток, тримачі під слаби та декор для оформлення колекції. Купити в Україні з доставкою — Booster Shop.',
        'meta_kw'     => 'підставка для карток, підставка під слаб PSA, тримач для картки, декор для колекції, підставка 3D друк',
        'h2'          => 'Підставки та декор для колекції',
        'body'        => [
            'Категорія про те, як показати картку, а не сховати її. Тут зібрані <strong>підставки для колекційних карток</strong>, тримачі під грейджені слаби та декоративні вироби для оформлення полиці чи вітрини.',
            'Моделі можуть відрізнятися матеріалом і форматом: від прозорих акрилових підставок до 3D-друкованих тримачів під конкретні типи карток або слабів. У картці кожного товару вказано, з чого він виготовлений і для якого формату призначений.',
            'Для 3D-друкованих моделей природною особливістю технології є тонка видима структура шарів; відтінок пластику між партіями також може незначно відрізнятися. Для акрилових виробів ці особливості не застосовуються.',
        ],
        'faq'         => [
            ['Під який формат підходить підставка?', 'Залежить від моделі. Формат — під звичайну картку, картку в протекторі чи грейджений слаб конкретного бренду — вказаний у характеристиках кожного товару.'],
            ['Чим відрізняються акрилові та 3D-друковані підставки?', 'Акрилові моделі прозорі та виготовляються як готові вироби. 3D-друковані моделі Booster Shop виготовляє окремо під конкретні формати; для них характерна тонка видима структура шарів, а колір фіксується для кожної моделі.'],
            ['Чи входять картки або слаби в комплект?', 'Ні, якщо в картці конкретного товару прямо не зазначено інше. Картки, слаби та інший декор на фото використовуються для демонстрації формату й масштабу.'],
        ],
    ],
    [
        'slug'        => 'figurky-ta-dekor-pokemon',
        'name'        => 'Фігурки та декор',
        'parent_id'   => 59,
        'sort_order'  => 4,
        'status'      => 0,
        'faq_id'      => 'cat-figurky-pokemon',
        'faq_title'   => 'FAQ — Фігурки та декор Pokémon',
        'meta_title'  => 'Фігурки та декор Pokémon — 3D-друк | Booster Shop',
        'meta_desc'   => 'Брелоки та фігурки Pokémon власного 3D-друку: Пікачу, Чармандер, Сквіртл, Бульбазавр, Онікс. Виготовляємо в Україні — купити в Booster Shop.',
        'meta_kw'     => 'брелок покемон, фігурка покемон, брелок Пікачу, 3D друк покемон, брелоки Pokemon купити Україна',
        'h2'          => 'Фігурки та декор Pokémon',
        'body'        => [
            'Тут зібрані <strong>брелоки та фігурки Pokémon</strong>, які ми друкуємо самі, в Україні, на власному обладнанні Booster Shop. Це не перепродаж готового мерчу: кожен виріб друкується пошарово, окремим екземпляром, і саме тому асортимент росте поступово.',
            'Формати різні. Пласкі брелоки з мінімалістичним силуетом персонажа — легкі та компактні, для ключів, сумки чи рюкзака. Фігурки з рухомими сегментами й моделі з fidget-механікою — коли хочеться не лише носити, а й крутити в руках. Розміри, маса й колір кожного виробу вказані в його картці.',
            'Пошаровий 3D-друк лишає на поверхні характерну тонку фактуру шарів, а відтінок пластику між партіями може незначно відрізнятися. Це природні особливості технології, а не дефект — ми про них пишемо прямо, замість того щоб ховати.',
            'Усі вироби в цій категорії — колекційні та декоративні, з віковим позиціонуванням 14+. Це не дитячі іграшки.',
        ],
        'faq'         => [
            ['Це офіційна продукція Pokémon?', 'Ні. Це вироби Booster Shop у тематиці Pokémon. Booster Shop не є ліцензіатом, партнером чи афілійованою особою правовласника.'],
            ['З чого виготовлені фігурки та брелоки?', 'З пластику PLA, методом пошарового 3D-друку. Точний матеріал, розміри й маса вказані в картці кожного товару.'],
            ['Чому вироби виглядають не як заводські?', 'Тому що вони й не заводські. Видима структура шарів і невелика різниця відтінку між партіями — природні риси 3D-друку.'],
        ],
    ],
    [
        'slug'        => 'figurky-ta-dekor-one-piece',
        'name'        => 'Фігурки та декор',
        'parent_id'   => 60,
        'sort_order'  => 2,
        'status'      => 0,
        'faq_id'      => 'cat-figurky-one-piece',
        'faq_title'   => 'FAQ — Фігурки та декор One Piece',
        'meta_title'  => 'Фігурки та декор One Piece — 3D-друк | Booster Shop',
        'meta_desc'   => 'Брелоки, фігурки та декор One Piece власного 3D-друку: солом\'яний капелюх, диявольський фрукт, череп, Луффі. Виготовляємо в Україні — Booster Shop.',
        'meta_kw'     => 'брелок One Piece, фігурка Луффі, брелок солом\'яний капелюх, 3D друк One Piece, аніме брелок купити Україна',
        'h2'          => 'Фігурки та декор One Piece',
        'body'        => [
            'Вироби у тематиці One Piece, які ми друкуємо самі, в Україні, на обладнанні Booster Shop. У цій категорії — <strong>брелоки, фігурки та декоративні аксесуари</strong> за мотивами всесвіту серії: впізнавані символи команди й самі персонажі.',
            'Частина моделей побудована навколо предметів, а не героїв: солом\'яний капелюх, диявольський фрукт, піратський череп. Це працює як відсилка «для своїх» — помітна тому, хто знає, і не схожа на випадковий аніме-мерч. Формати можуть бути різними: від пласких брелоків і закладок до об\'ємних фігурок, світильників та іншого декору.',
            'Пошаровий 3D-друк лишає на поверхні характерну тонку фактуру шарів, а відтінок пластику між партіями може незначно відрізнятися. Це природні особливості технології, про які ми пишемо прямо.',
            'Усі вироби — колекційні та декоративні, вікове позиціонування 14+. Це не дитячі іграшки.',
        ],
        'faq'         => [
            ['Це офіційна продукція One Piece?', 'Ні. Це вироби Booster Shop у тематиці One Piece. Booster Shop не є ліцензіатом, партнером чи афілійованою особою правовласника.'],
            ['З чого виготовлені вироби?', 'З пластику PLA, методом пошарового 3D-друку. Точні розміри, маса й колір вказані в картці кожного товару.'],
            ['Чи є вироби з рухомими елементами?', 'Так, у частини моделей є рухомі сегменти або fidget-механіка. Це завжди вказано в характеристиках товару.'],
        ],
    ],
];

// --------------------------------------------------------------------------
// Shared helpers (house style, carried over from LEGAL-002b-3DP patches)
// --------------------------------------------------------------------------

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
function bs_bind_guard(string $sql, string $types, array $params): void {
    $marks = substr_count($sql, '?');
    if ($marks !== strlen($types) || $marks !== count($params)) {
        bs_fail('Bind mismatch: ' . $marks . ' placeholders, ' . strlen($types) . ' type chars, ' . count($params) . ' params in: ' . $sql);
    }
}
function bs_select(mysqli $db, string $sql, string $types, array $params): array {
    bs_bind_guard($sql, $types, $params);
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $refs = [$types];
        foreach ($params as $key => &$value) $refs[] = &$params[$key];
        if (!call_user_func_array([$stmt, 'bind_param'], $refs)) bs_fail('Cannot bind query parameters');
    }
    $stmt->execute(); $rows = bs_stmt_rows($stmt); $stmt->close(); return $rows;
}
function bs_exec(mysqli $db, string $sql, string $types, array $params): int {
    bs_bind_guard($sql, $types, $params);
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $refs = [$types];
        foreach ($params as $key => &$value) $refs[] = &$params[$key];
        if (!call_user_func_array([$stmt, 'bind_param'], $refs)) bs_fail('Cannot bind query parameters');
    }
    $stmt->execute(); $affected = $stmt->affected_rows; $stmt->close(); return (int) $affected;
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

/**
 * Encode a raw HTML string the way this install stores descriptions.
 * Live rows carry &lt; &gt; &quot; and leave apostrophes and typographic
 * characters raw — there is not a single &#039; anywhere in the dump. So:
 * ENT_COMPAT, never ENT_QUOTES.
 */
function bs_encode_html(string $html): string {
    return htmlspecialchars($html, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build the category FAQ accordion, byte-shaped like the live categories. */
function bs_faq_html(string $faqId, string $title, array $items): string {
    $nl  = "\r\n";
    $out = '<section class="bs-faq-accordion" data-bs-faq-accordion data-bs-faq-id="' . $faqId . '">' . $nl;
    $out .= '  <h2 class="bs-faq-title">' . $title . '</h2>' . $nl;
    $i = 0;
    foreach ($items as $item) {
        $i++;
        $btn   = 'bs-faq-' . $faqId . '-button-' . $i;
        $panel = 'bs-faq-' . $faqId . '-panel-' . $i;
        $out .= $nl;
        $out .= '  <div class="bs-faq-item">' . $nl;
        $out .= '    <h3 class="bs-faq-question">' . $nl;
        $out .= '      <button type="button" id="' . $btn . '" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="' . $panel . '">' . $nl;
        $out .= '        <span>' . $item[0] . '</span><span class="bs-faq-icon" aria-hidden="true"></span>' . $nl;
        $out .= '      </button>' . $nl;
        $out .= '    </h3>' . $nl;
        $out .= '    <div id="' . $panel . '" class="bs-faq-panel" role="region" aria-labelledby="' . $btn . '" hidden>' . $nl;
        $out .= '      <p>' . $item[1] . '</p>' . $nl;
        $out .= '    </div>' . $nl;
        $out .= '  </div>' . $nl;
    }
    $out .= '</section>';
    return $out;
}

/** Full category description: H2 + paragraphs + FAQ, then entity-encoded. */
function bs_category_description(array $category): string {
    $nl   = "\r\n";
    $html = '<h2>' . $category['h2'] . '</h2>' . $nl;
    foreach ($category['body'] as $paragraph) {
        $html .= $nl . '<p>' . $paragraph . '</p>' . $nl;
    }
    $html .= $nl . bs_faq_html($category['faq_id'], $category['faq_title'], $category['faq']);
    return bs_encode_html($html);
}

// --------------------------------------------------------------------------
// Domain helpers
// --------------------------------------------------------------------------

function bs_category_id_by_seo(mysqli $db, string $seoUrl, string $keyword): int {
    $rows = bs_select($db,
        'SELECT `value` FROM `' . $seoUrl . '` WHERE `key` = \'path\' AND `keyword` = ? AND store_id = ?',
        'si', [$keyword, STORE_ID]);
    if ($rows === []) return 0;
    $value = (string) $rows[0]['value'];
    $parts = explode('_', $value);
    return (int) end($parts);
}

function bs_parent_keyword(mysqli $db, string $seoUrl, int $parentId): string {
    $rows = bs_select($db,
        'SELECT `keyword` FROM `' . $seoUrl . '` WHERE `key` = \'path\' AND `value` = ? AND store_id = ?',
        'si', [(string) $parentId, STORE_ID]);
    if ($rows === []) bs_fail('Parent category ' . $parentId . ' has no seo_url path row — cannot build a subcategory keyword');
    if (count($rows) > 1) bs_fail('Parent category ' . $parentId . ' has ' . count($rows) . ' seo_url path rows — ambiguous, resolve before running');
    return (string) $rows[0]['keyword'];
}

function bs_product_seo_row(mysqli $db, string $seoUrl, int $productId): array {
    $rows = bs_select($db,
        'SELECT seo_url_id, store_id, language_id, `key`, `value`, `keyword`, sort_order'
        . ' FROM `' . $seoUrl . '` WHERE `key` = \'product_id\' AND `value` = ?',
        's', [(string) $productId]);
    return $rows;
}

function bs_product_categories(mysqli $db, string $p2c, int $productId): array {
    $rows = bs_select($db, 'SELECT category_id FROM `' . $p2c . '` WHERE product_id = ? ORDER BY category_id', 'i', [$productId]);
    $out = [];
    foreach ($rows as $row) $out[] = (int) $row['category_id'];
    return $out;
}

function bs_product_model(mysqli $db, string $product, int $productId): ?string {
    $rows = bs_select($db, 'SELECT model FROM `' . $product . '` WHERE product_id = ?', 'i', [$productId]);
    return $rows === [] ? null : (string) $rows[0]['model'];
}

/** Move one product; assert its seo_url row is untouched by the move. */
function bs_move_product(mysqli $db, array $t, int $productId, int $targetCategoryId): void {
    $before = bs_product_seo_row($db, $t['seo_url'], $productId);

    $affected = bs_exec($db,
        'UPDATE `' . $t['product_to_category'] . '` SET category_id = ? WHERE product_id = ? AND category_id = 70',
        'ii', [$targetCategoryId, $productId]);
    if ($affected !== 1) {
        bs_fail('Move of product ' . $productId . ' updated ' . $affected . ' rows, expected exactly 1 — rolling back');
    }

    $after = bs_product_seo_row($db, $t['seo_url'], $productId);
    if ($before !== $after) {
        bs_fail('seo_url row of product ' . $productId . ' changed during the category move — rolling back');
    }

    $categories = bs_product_categories($db, $t['product_to_category'], $productId);
    if ($categories !== [$targetCategoryId]) {
        bs_fail('Product ' . $productId . ' is now in categories [' . implode(',', $categories)
            . '], expected exactly [' . $targetCategoryId . '] — rolling back');
    }

    $keyword = $before === [] ? '(no seo_url row)' : (string) $before[0]['keyword'];
    bs_log('moved_product', $productId . ' -> category ' . $targetCategoryId . ' | /product/' . $keyword . ' unchanged');
}

// --------------------------------------------------------------------------

function bs_run(array $argv): void {
    $stageTwo = in_array('--move-remaining', $argv, true);

    $cwd = getcwd();
    if (!is_string($cwd) || $cwd === '') bs_fail('Cannot determine cwd');
    bs_log('patch', PATCH_NAME);
    bs_log('stage', $stageTwo ? '2 (move remaining 8)' : '1 (create categories + move product ' . MOVE_FIRST . ')');
    bs_log('cwd', $cwd);
    bs_log('time', date('c'));

    $config = bs_path($cwd, 'config.php');
    if (!is_file($config)) bs_fail('config.php not found. Run this patch from ~/public_html.');

    bs_lint_self();
    require_once $config;

    // Column-width guards — every one of these columns is varchar(255).
    foreach (CATEGORIES as $category) {
        foreach (['name' => 255, 'meta_title' => 255, 'meta_desc' => 255, 'meta_kw' => 255] as $field => $limit) {
            $length = mb_strlen($category[$field], 'UTF-8');
            if ($length > $limit) bs_fail('Field ' . $field . ' of «' . $category['slug'] . '» is ' . $length . ' chars, limit ' . $limit);
        }
    }
    $slugs = array_column(CATEGORIES, 'slug');
    if (count(array_unique($slugs)) !== count($slugs)) bs_fail('Duplicate category slug in CATEGORIES');

    $backupDir = bs_path($cwd, '_patch_backups/' . PATCH_NAME . '-' . date('Ymd-His'));
    if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) bs_fail('Cannot create backup directory');
    bs_log('backup_dir', $backupDir);

    $db = bs_connect();
    try {
        $prefix = (string) DB_PREFIX;
        $t = [
            'category'            => bs_table($prefix, 'category'),
            'category_description'=> bs_table($prefix, 'category_description'),
            'category_path'       => bs_table($prefix, 'category_path'),
            'category_to_store'   => bs_table($prefix, 'category_to_store'),
            'category_to_layout'  => bs_table($prefix, 'category_to_layout'),
            'seo_url'             => bs_table($prefix, 'seo_url'),
            'product'             => bs_table($prefix, 'product'),
            'product_to_category' => bs_table($prefix, 'product_to_category'),
        ];
        foreach ($t as $table) if (!bs_table_exists($db, $table)) bs_fail('Required table not found: ' . $table);

        bs_require_columns(bs_columns($db, $t['category']), ['category_id','image','parent_id','sort_order','status'], $t['category']);
        bs_require_columns(bs_columns($db, $t['category_description']), ['category_id','language_id','name','description','meta_title','meta_description','meta_keyword'], $t['category_description']);
        bs_require_columns(bs_columns($db, $t['category_path']), ['category_id','path_id','level'], $t['category_path']);
        bs_require_columns(bs_columns($db, $t['seo_url']), ['seo_url_id','store_id','language_id','key','value','keyword','sort_order'], $t['seo_url']);
        bs_require_columns(bs_columns($db, $t['product_to_category']), ['product_id','category_id'], $t['product_to_category']);

        // --- contract: the three parents must still be what the handoff assumed ---
        foreach (PARENTS as $parentId => $expected) {
            $rows = bs_select($db,
                'SELECT d.name AS name, c.parent_id AS parent_id FROM `' . $t['category'] . '` c'
                . ' JOIN `' . $t['category_description'] . '` d ON d.category_id = c.category_id AND d.language_id = ?'
                . ' WHERE c.category_id = ?', 'ii', [LANGUAGE_ID, $parentId]);
            if ($rows === []) bs_fail('Parent category ' . $parentId . ' («' . $expected['name'] . '») does not exist — stopping');
            if ((string) $rows[0]['name'] !== $expected['name']) {
                bs_fail('Parent ' . $parentId . ' is now named «' . $rows[0]['name'] . '», expected «' . $expected['name'] . '» — stopping');
            }
            $keyword = bs_parent_keyword($db, $t['seo_url'], (int) $parentId);
            if ($keyword !== $expected['keyword']) {
                bs_fail('Parent ' . $parentId . ' seo keyword is «' . $keyword . '», expected «' . $expected['keyword']
                    . '». Subcategory URLs are built on it — stopping.');
            }
            bs_log('parent_verified', $parentId . ' «' . $expected['name'] . '» keyword=' . $keyword);
        }

        // --- which of the four already exist? ---
        $existing = [];
        foreach (CATEGORIES as $category) {
            $parentKeyword = bs_parent_keyword($db, $t['seo_url'], (int) $category['parent_id']);
            $fullKeyword   = $parentKeyword . '/' . $category['slug'];
            $id = bs_category_id_by_seo($db, $t['seo_url'], $fullKeyword);
            if ($id > 0) $existing[$category['slug']] = $id;
        }
        bs_log('existing_subcategories', $existing === [] ? 'none' : json_encode($existing, JSON_UNESCAPED_UNICODE));

        // ------------------------------------------------------------------
        // STAGE 2 — move the remaining eight, then finish.
        // ------------------------------------------------------------------
        if ($stageTwo) {
            if (count($existing) !== count(CATEGORIES)) {
                bs_fail('Stage 2 requires all four subcategories to exist; found ' . count($existing) . '. Run stage 1 first.');
            }
            $firstCategories = bs_product_categories($db, $t['product_to_category'], MOVE_FIRST);
            $firstTargetSlug = PRODUCT_MOVES[MOVE_FIRST][1];
            if ($firstCategories !== [$existing[$firstTargetSlug]]) {
                bs_fail('Stage 2 requires product ' . MOVE_FIRST . ' to have been moved by stage 1; it is in ['
                    . implode(',', $firstCategories) . ']. Run stage 1 first.');
            }

            $pending = [];
            foreach (PRODUCT_MOVES as $productId => $move) {
                if ($productId === MOVE_FIRST) continue;
                if (bs_product_categories($db, $t['product_to_category'], (int) $productId) === [70]) {
                    $pending[(int) $productId] = $move;
                }
            }
            if ($pending === []) {
                bs_log('already_applied', 'yes');
                bs_log('remaining_to_move', '0');
                bs_log('done', 'ok');
                bs_self_delete();
                return;
            }

            $snapshot = [];
            foreach ($pending as $productId => $move) {
                $snapshot[$productId] = [
                    'model'      => bs_product_model($db, $t['product'], (int) $productId),
                    'categories' => bs_product_categories($db, $t['product_to_category'], (int) $productId),
                    'seo_url'    => bs_product_seo_row($db, $t['seo_url'], (int) $productId),
                ];
            }
            bs_json_backup($backupDir, 'stage2_products_before', [
                'note'     => 'Rollback: UPDATE ' . $t['product_to_category'] . ' SET category_id = 70 WHERE product_id IN (' . implode(',', array_keys($pending)) . ');',
                'products' => $snapshot,
            ]);

            $db->begin_transaction();
            try {
                foreach ($pending as $productId => $move) {
                    $model = bs_product_model($db, $t['product'], (int) $productId);
                    if ($model !== $move[0]) {
                        bs_fail('Product ' . $productId . ' has model «' . (string) $model . '», expected «' . $move[0] . '» — rolling back');
                    }
                    bs_move_product($db, $t, (int) $productId, $existing[$move[1]]);
                }

                $leftInRoot = bs_select($db, 'SELECT product_id FROM `' . $t['product_to_category'] . '` WHERE category_id = 70', '', []);
                if ($leftInRoot !== []) {
                    bs_fail('Category 70 still holds ' . count($leftInRoot) . ' product(s) after the move — rolling back');
                }
                $db->commit();
            } catch (Throwable $e) {
                $db->rollback();
                throw $e;
            }

            foreach ($existing as $slug => $id) {
                $count = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['product_to_category'] . '` WHERE category_id = ?', 'i', [$id]);
                bs_log('category_product_count', $slug . ' (id=' . $id . ') = ' . $count[0]['c']);
            }
            bs_log('moved', (string) count($pending));
            bs_log('accessories_root_remaining', '0');
            bs_log('next', 'clear OpenCart cache + compiled templates, then run the handoff §9 Owner QA');
            bs_log('done', 'ok');
            bs_self_delete();
            return;
        }

        // ------------------------------------------------------------------
        // STAGE 1 — create the four categories + move product 99 only.
        // ------------------------------------------------------------------
        $firstTargetSlug   = PRODUCT_MOVES[MOVE_FIRST][1];
        $firstAlreadyMoved = isset($existing[$firstTargetSlug])
            && bs_product_categories($db, $t['product_to_category'], MOVE_FIRST) === [$existing[$firstTargetSlug]];

        if (count($existing) === count(CATEGORIES) && $firstAlreadyMoved) {
            bs_log('already_applied', 'yes');
            bs_log('created_category_ids', json_encode($existing, JSON_UNESCAPED_UNICODE));
            bs_log('gate', 'Stage 1 is done. Verify the URL below, then run: php ' . basename(__FILE__) . ' --move-remaining');
            bs_log('verify_url', 'https://boostershop.website/product/akrylova-pidstavka-dlya-kart');
            bs_log('done', 'ok');
            return; // deliberately NOT self-deleting: stage 2 still needs this file
        }

        // Snapshot everything stage 1 can touch.
        bs_json_backup($backupDir, 'subcategories_before', [
            'note'                  => 'State before stage 1. See the patch header for the rollback order.',
            'categories'            => bs_select($db, 'SELECT category_id, image, parent_id, sort_order, status FROM `' . $t['category'] . '` ORDER BY category_id', '', []),
            'category_path'         => bs_select($db, 'SELECT category_id, path_id, level FROM `' . $t['category_path'] . '` ORDER BY category_id, path_id', '', []),
            'category_to_store'     => bs_select($db, 'SELECT category_id, store_id FROM `' . $t['category_to_store'] . '` ORDER BY category_id', '', []),
            'category_to_layout'    => bs_select($db, 'SELECT category_id, store_id, layout_id FROM `' . $t['category_to_layout'] . '`', '', []),
            'seo_url_path_rows'     => bs_select($db, 'SELECT seo_url_id, store_id, language_id, `key`, `value`, `keyword`, sort_order FROM `' . $t['seo_url'] . '` WHERE `key` = \'path\' ORDER BY seo_url_id', '', []),
            'product_to_category_70'=> bs_select($db, 'SELECT product_id, category_id FROM `' . $t['product_to_category'] . '` WHERE category_id = 70 ORDER BY product_id', '', []),
            'moved_first_product'   => [
                'product_id' => MOVE_FIRST,
                'model'      => bs_product_model($db, $t['product'], MOVE_FIRST),
                'seo_url'    => bs_product_seo_row($db, $t['seo_url'], MOVE_FIRST),
            ],
        ]);

        // Refuse to start if the nine are not where the handoff says they are.
        $rootRows = bs_select($db, 'SELECT product_id FROM `' . $t['product_to_category'] . '` WHERE category_id = 70 ORDER BY product_id', '', []);
        $rootIds  = [];
        foreach ($rootRows as $row) $rootIds[] = (int) $row['product_id'];
        $expectedIds = array_map('intval', array_keys(PRODUCT_MOVES));
        sort($expectedIds);
        if ($rootIds !== $expectedIds && !$firstAlreadyMoved) {
            bs_fail('Category 70 holds [' . implode(',', $rootIds) . '], expected [' . implode(',', $expectedIds)
                . ']. The handoff was written against the second list — stopping.');
        }

        $created = [];
        $createdSeoValues = [];

        $db->begin_transaction();
        try {
            foreach (CATEGORIES as $category) {
                $slug = $category['slug'];
                if (isset($existing[$slug])) {
                    bs_log('category_exists', $slug . ' (id=' . $existing[$slug] . ')');
                    continue;
                }

                $parentId      = (int) $category['parent_id'];
                $parentKeyword = bs_parent_keyword($db, $t['seo_url'], $parentId);
                $fullKeyword   = $parentKeyword . '/' . $slug;

                // Last-moment collision guard, exactly the way the storefront looks a keyword up.
                $clash = bs_select($db,
                    'SELECT seo_url_id, `keyword` FROM `' . $t['seo_url'] . '` WHERE (`keyword` = ? OR `keyword` LIKE ?) AND store_id = ?',
                    'ssi', [$fullKeyword, '%/' . $slug, STORE_ID]);
                if ($clash !== []) {
                    bs_fail('SEO keyword collision for «' . $slug . '»: seo_url_id=' . $clash[0]['seo_url_id']
                        . ' already answers to it («' . $clash[0]['keyword'] . '») — rolling back');
                }

                bs_exec($db, 'INSERT INTO `' . $t['category'] . '` (`image`, `parent_id`, `sort_order`, `status`) VALUES (\'\', ?, ?, ?)',
                    'iii', [$parentId, (int) $category['sort_order'], (int) $category['status']]);
                $categoryId = (int) $db->insert_id;
                if ($categoryId < 1) bs_fail('category insert returned no id for ' . $slug);

                $description = bs_category_description($category);
                bs_exec($db,
                    'INSERT INTO `' . $t['category_description'] . '` (`category_id`, `language_id`, `name`, `description`, `meta_title`, `meta_description`, `meta_keyword`)'
                    . ' VALUES (?, ?, ?, ?, ?, ?, ?)',
                    'iisssss',
                    [$categoryId, LANGUAGE_ID, $category['name'], $description, $category['meta_title'], $category['meta_desc'], $category['meta_kw']]);

                // Path: (child, parent, 0) then (child, child, 1) — matches every live subcategory.
                bs_exec($db, 'INSERT INTO `' . $t['category_path'] . '` (`category_id`, `path_id`, `level`) VALUES (?, ?, 0)', 'ii', [$categoryId, $parentId]);
                bs_exec($db, 'INSERT INTO `' . $t['category_path'] . '` (`category_id`, `path_id`, `level`) VALUES (?, ?, 1)', 'ii', [$categoryId, $categoryId]);

                bs_exec($db, 'INSERT INTO `' . $t['category_to_store'] . '` (`category_id`, `store_id`) VALUES (?, ?)', 'ii', [$categoryId, STORE_ID]);

                $seoValue = $parentId . '_' . $categoryId;
                bs_exec($db,
                    'INSERT INTO `' . $t['seo_url'] . '` (`store_id`, `language_id`, `key`, `value`, `keyword`, `sort_order`) VALUES (?, ?, \'path\', ?, ?, 0)',
                    'iiss', [STORE_ID, LANGUAGE_ID, $seoValue, $fullKeyword]);

                $created[$slug]          = $categoryId;
                $createdSeoValues[$slug] = $seoValue;
                $existing[$slug]         = $categoryId;

                bs_log('created_category', $slug . ' (id=' . $categoryId . ', parent=' . $parentId
                    . ', status=' . $category['status'] . ', url=/catalog/' . $fullKeyword . ')');
            }

            // Move exactly one product — the handoff §4.1 gate.
            if (!$firstAlreadyMoved) {
                $model = bs_product_model($db, $t['product'], MOVE_FIRST);
                if ($model !== PRODUCT_MOVES[MOVE_FIRST][0]) {
                    bs_fail('Product ' . MOVE_FIRST . ' has model «' . (string) $model . '», expected «'
                        . PRODUCT_MOVES[MOVE_FIRST][0] . '» — rolling back');
                }
                bs_move_product($db, $t, MOVE_FIRST, $existing[PRODUCT_MOVES[MOVE_FIRST][1]]);
            }

            // The other eight must still be untouched at the end of stage 1.
            $stillRoot = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['product_to_category'] . '` WHERE category_id = 70', '', []);
            if ((int) $stillRoot[0]['c'] !== 8) {
                bs_fail('Expected 8 products still in category 70 after stage 1, found ' . $stillRoot[0]['c'] . ' — rolling back');
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }

        bs_json_backup($backupDir, 'created_ids', [
            'note'                  => 'Rollback: see the patch header. Undo the product move BEFORE deleting categories.',
            'created_category_ids'  => $created,
            'created_seo_values'    => $createdSeoValues,
            'all_subcategory_ids'   => $existing,
            'moved_in_stage_1'      => [MOVE_FIRST],
            'still_in_category_70'  => array_values(array_diff(array_map('intval', array_keys(PRODUCT_MOVES)), [MOVE_FIRST])),
        ]);

        bs_log('created_categories', (string) count($created));
        bs_log('stage_1', 'complete');
        bs_log('');
        bs_log('GATE', 'handoff 3D-P-002 §4.1 — verify BEFORE moving the remaining eight');
        bs_log('verify_url', 'https://boostershop.website/product/akrylova-pidstavka-dlya-kart');
        bs_log('verify_expect', '200 OK, same URL as before; breadcrumbs now Аксесуари -> Підставки та декор');
        bs_log('then_run', 'php ' . basename(__FILE__) . ' --move-remaining');
        bs_log('note', 'This file is intentionally NOT deleted yet — stage 2 needs it.');
        bs_log('done', 'ok');
    } finally {
        $db->close();
    }
}

try {
    bs_run($argv ?? []);
} catch (Throwable $e) {
    bs_log('error', $e->getMessage());
    bs_log('done', 'failed');
    exit(1);
}
