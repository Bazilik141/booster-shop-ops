<?php
declare(strict_types=1);
/*
 * CAT-005 WP2: create the 3 Bulk / Bulk Mix product cards DISABLED (status=0)
 * plus two new attributes in group 7. Deploy AFTER
 * CAT-005_offer-bulk-and-archive_20261018.php.
 * Run from ~/public_html after a separate owner-held OpenCart database dump.
 * Owner decisions 2026-10-19: subtract=1 (D1); new attributes sort_order 4/5
 * (D2); schema/feed untouched, cards stay status=0 (D3).
 * Writes (INSERT only, no UPDATE/DELETE of existing rows): DB_PREFIX attribute,
 * attribute_description (2 new attributes), product, product_description,
 * product_to_store, product_to_category, product_attribute, seo_url
 * (3 new products). No images, weights, dimensions, stock or layout rows.
 * Does not edit files, existing products/attributes (12-24, 49 included),
 * categories, manufacturers, templates, schema, feed, sitemap or CRM.
 * Deferred SKUs PKM-JP-BULK-100 / OP-JP-BULK-100 are not created.
 * No mysqlnd required: prepared reads use bind_result(). PHP 8.0 compatible.
 * Strict all-or-nothing application: InnoDB required; no partial repair.
 * Backups: _patch_backups/<patch>-<ts>/db/preflight_snapshot.json,
 * created_ids.json and rollback.sql (all written BEFORE commit).
 * ROLLBACK (owner only, review saved IDs and take a fresh DB dump first):
 * execute the generated db/rollback.sql with a DB client. It contains, for the
 * saved product IDs P and attribute IDs A only:
 * START TRANSACTION;
 * DELETE FROM ocp5_seo_url WHERE `key`='product_id' AND `value` IN (P);
 * DELETE FROM <every existing ocp5_product_* child table> WHERE product_id IN (P);
 *   (product_related also WHERE related_id IN (P): images, discounts etc. the
 *   owner added after deploy are removed with the card)
 * DELETE FROM ocp5_product WHERE product_id IN (P);
 * DELETE FROM ocp5_attribute_description WHERE attribute_id IN (A) AND no
 *   product_attribute row still uses it; same guard for ocp5_attribute;
 * COMMIT;
 * Actual table prefix and assigned IDs are used in the generated SQL.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL);
ini_set('display_errors', '0');
const PATCH_NAME = 'CAT-005_bulk-cards_20261018';
const LANGUAGE_ID = 4;
const STORE_ID = 0;
const ATTRIBUTE_GROUP_ID = 7;
const META_TITLE_MAX = 63;
const META_DESCRIPTION_MAX = 155;
// Existing attributes used by every card (id => exact name, verified 15.10 backup).
const EXISTING_ATTRIBUTES = [
    12 => 'Мова', 13 => 'Назва сету', 17 => 'Стан', 18 => 'Походження товару',
    20 => 'Виробник', 21 => 'Тип пакування', 24 => 'Додатковий вміст',
];
// New attributes: key => [name, sort_order] (owner decision D2: 4 / 5).
const NEW_ATTRIBUTES = [
    'count' => ['Кількість карток у наборі', 4],
    'rarity' => ['Склад за рідкістю', 5],
];
const MANUFACTURERS = [11 => 'The Pokémon Company', 12 => 'Bandai'];
// category_id => [parent_id, exact name]
const CATEGORIES = [
    59 => [0, 'Pokémon'], 64 => [59, 'Набори'],
    60 => [0, 'One Piece Card Game'], 68 => [60, 'Набори та бокси One Piece'],
];
// Technical fields mirrored from Mystery Mix 77/85, subtract per owner decision D1.
const STOCK_STATUS_ID = 5;
const WEIGHT_CLASS_ID = 2;
const LENGTH_CLASS_ID = 1;
const SUBTRACT = 1;

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
function bs_bind(mysqli_stmt $stmt, string $types, array $params): void {
    if ($types === '') return;
    $refs = [$types];
    foreach ($params as $key => &$value) $refs[] = &$params[$key];
    if (!call_user_func_array([$stmt, 'bind_param'], $refs)) bs_fail('Cannot bind query parameters');
}
function bs_select(mysqli $db, string $sql, string $types, array $params): array {
    $stmt = $db->prepare($sql); bs_bind($stmt, $types, $params);
    $stmt->execute(); $rows = bs_stmt_rows($stmt); $stmt->close(); return $rows;
}
function bs_insert(mysqli $db, string $sql, string $types, array $params): int {
    $stmt = $db->prepare($sql); bs_bind($stmt, $types, $params);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) bs_fail('Insert did not affect exactly one row');
    $id = (int) $stmt->insert_id; $stmt->close(); return $id;
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
// OpenCart admin stores product text fields through Request::clean(), i.e.
// htmlspecialchars(ENT_COMPAT); live descriptions are stored entity-escaped.
function bs_oc(string $value): string { return htmlspecialchars($value, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8'); }
// Rows read through bind_result carry driver types; compare as strings.
function bs_norm(array $row): array {
    $out = [];
    foreach ($row as $k => $v) $out[$k] = $v === null ? null : (string) $v;
    ksort($out);
    return $out;
}
function bs_norm_all(array $rows): array { return array_map('bs_norm', $rows); }
function bs_one(array $rows, string $label): array {
    if (count($rows) !== 1) bs_fail($label . ': expected exactly one row, found ' . count($rows));
    return $rows[0];
}
// Both sides are normalised (string values, sorted keys) so live column order cannot matter.
function bs_same(array $actual, array $expected, string $label): void {
    $isList = $expected === [] || array_keys($expected) === range(0, count($expected) - 1);
    if ($isList) { $actual = bs_norm_all($actual); $expected = bs_norm_all($expected); }
    else { $actual = bs_norm($actual); $expected = bs_norm($expected); }
    if ($actual !== $expected) bs_fail($label . ': verification failed');
}

// Card copy from handoffs/CAT-005_bulk-card-copy_FINAL_20261018.md, verbatim.
function bs_cards(): array {
    $cards = [];
    $cards['PKM-JP-BULK-155'] = [
        'sku' => 'PKM-JP-BULK-155',
        'name' => 'Набір Pokémon TCG: Bulk 155 — 150 C/U + 5 R (Японське видання)',
        'keyword' => 'Pokemon-Bulk-155-JP',
        'meta_title' => 'Pokémon TCG Bulk 155 JP — 150 C/U + 5 R | Booster Shop',
        'meta_description' => 'Bulk-набір Pokémon TCG: 155 японських карток — 150 C/U та 5 R. Оригінальні картки з відкритих бустерів для колекції й обміну.',
        'tag' => 'Pokémon TCG, Bulk 155, японські картки, набір карток, Common, Uncommon, Rare',
        'manufacturer_id' => 11,
        'price' => '250.0000',
        'categories' => [59, 64],
        'attributes' => [12 => 'Японська (Japanese)', 13 => 'Bulk / різні японські сети Pokémon TCG', 17 => 'Оригінальні картки з розкритих бустерів; ступінь стану не заявляється', 18 => 'Картки з оригінальних бустерів, розкритих магазином', 20 => 'The Pokémon Company', 21 => 'Сформований набір карток; паковання підбирається при комплектації замовлення', 24 => 'Немає; спеціальні рідкості не гарантуються'],
        'new_attributes' => ['count' => '155', 'rarity' => '150 C/U + 5 R'],
        'description_sha256' => '90362cba821a3ab06ec4ef12ed79eab300b5683274fde16348e67e61e65b380e',
        'stored_description_sha256' => 'd87fd0165a8f78773fd261c0e12942c17141c03b377f98ff0716b90b2d2ca149',
        'description' => <<<'HTML'
<p>Коли хочеться поповнити альбом японськими картками Покемон, відкривати бустери один за одним необов’язково. Формат Bulk дає одразу багато карток для колекції, обміну або експериментів із власною колодою.</p>
<h2>Що входить до набору</h2>
<p><strong>155 оригінальних карток Pokémon TCG японською мовою: 150 Common/Uncommon (C/U) та 5 Rare (R).</strong></p>
<p>Картки можуть походити з різних японських сетів, тому склад наборів відрізнятиметься. Це спосіб поповнити свою колекцію, а не полювання на конкретного покемона чи дорогу картку.</p>
<h2>Як ми формуємо набір</h2>
<p>Ми збираємо його з оригінальних карток, які раніше дістали з бустерів під час власного відкриття паків. Це не запечатаний набір виробника. Паковання підбираємо під час комплектації замовлення з урахуванням обсягу набору й захисту карток.</p>
<p>На фото показуємо приклад наповнення — у вашому наборі можуть бути інші картки.</p>
<section class="bs-faq-accordion" data-bs-faq-accordion data-bs-faq-id="prod-pkm-jp-bulk-155">
<h2 class="bs-faq-title">FAQ</h2>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-pkm-jp-bulk-155-panel-1" aria-expanded="false" id="bs-faq-prod-pkm-jp-bulk-155-button-1"><span>Що означають C/U та R у наборі Pokémon Bulk?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-pkm-jp-bulk-155-button-1" id="bs-faq-prod-pkm-jp-bulk-155-panel-1"><p>Це позначення рідкісності карток у Pokémon TCG: C (Common) — звичайні, U (Uncommon) — незвичайні, R (Rare) — рідкісні. Вони вказують на категорію картки, а не на її ринкову вартість.</p></div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-pkm-jp-bulk-155-panel-2" aria-expanded="false" id="bs-faq-prod-pkm-jp-bulk-155-button-2"><span>Чи можуть у Bulk-наборі трапитися повтори?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-pkm-jp-bulk-155-button-2" id="bs-faq-prod-pkm-jp-bulk-155-panel-2"><p>Ми намагаємося складати набори без повторів, але це не гарантія: випадковий дублікат можливий. Якщо повтор усе ж трапиться, це не вважається браком набору.</p></div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-pkm-jp-bulk-155-panel-3" aria-expanded="false" id="bs-faq-prod-pkm-jp-bulk-155-button-3"><span>Чи можна з карток Bulk 155 скласти колоду для гри?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-pkm-jp-bulk-155-button-3" id="bs-faq-prod-pkm-jp-bulk-155-panel-3"><p>Картки можна використовувати для власних ігрових задумів, але це не готова колода. Потрібні для вашої стратегії картки та їхню відповідність правилам обраного формату доведеться перевірити окремо.</p></div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-pkm-jp-bulk-155-panel-4" aria-expanded="false" id="bs-faq-prod-pkm-jp-bulk-155-button-4"><span>Це запечатаний набір від виробника?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-pkm-jp-bulk-155-button-4" id="bs-faq-prod-pkm-jp-bulk-155-panel-4"><p>Ні. Набір формуємо самі з оригінальних карток Pokémon TCG, які раніше дістали з фабричних бустерів. Він не запечатаний виробником, тому не варто чекати заводського пакування.</p></div>
</div>
</section>
HTML,
    ];
    $cards['OP-JP-BULK-155'] = [
        'sku' => 'OP-JP-BULK-155',
        'name' => 'Набір One Piece Card Game: Bulk 155 — 150 C/UC + 5 R (Японське видання)',
        'keyword' => 'One-Piece-Bulk-155-JP',
        'meta_title' => 'One Piece Bulk 155 JP — 150 C/UC + 5 R | Booster Shop',
        'meta_description' => 'Bulk 155 для One Piece Card Game: 155 японських карток, із них 150 C/UC та 5 R. Для альбому, обмінів і власних ігрових ідей.',
        'tag' => 'One Piece Card Game, Bulk 155, японські картки, колекція, обмін, Common, Uncommon, Rare',
        'manufacturer_id' => 12,
        'price' => '250.0000',
        'categories' => [60, 68],
        'attributes' => [12 => 'Японська (Japanese)', 13 => 'Bulk / різні японські сети One Piece Card Game', 17 => 'Оригінальні картки з розкритих бустерів; ступінь стану не заявляється', 18 => 'Картки з оригінальних бустерів, розкритих магазином', 20 => 'Bandai', 21 => 'Сформований набір карток; паковання підбирається при комплектації замовлення', 24 => 'Немає; спеціальні рідкості не гарантуються'],
        'new_attributes' => ['count' => '155', 'rarity' => '150 C/UC + 5 R'],
        'description_sha256' => '348df0820594e43f59031fc4315838b95aba43a52684fe21da35c3edeafeebbc',
        'stored_description_sha256' => '458c6a16790559bc39c33eb54720309f9e6650d091655c97c8e343a1ff2b8c3c',
        'description' => <<<'HTML'
<p>У колекції Ван Піс є місце не лише для рідкісних карток. Якщо хочеться більше персонажів та ілюстрацій для альбому, обміну чи власних ігрових задумів, формат Bulk дозволяє поповнити запас без відкриття нових бустерів.</p>
<h2>Які картки всередині</h2>
<p><strong>155 оригінальних карток One Piece Card Game японською мовою: 150 Common/Uncommon (C/UC) та 5 Rare (R).</strong></p>
<p>Картки можуть походити з різних японських сетів, тому наперед визначеного переліку персонажів та ілюстрацій немає. Це набір для поповнення колекції, а не полювання на конкретну дорогу картку чи особливу рідкісність.</p>
<h2>Як картки потрапляють до набору</h2>
<p>Для таких наборів ми використовуємо оригінальні картки з бустерів, які раніше відкрили самі. Це не запечатаний продукт виробника. Паковання обираємо під час комплектації замовлення, орієнтуючись на кількість карток та необхідний захист.</p>
<p>Фотографії допомагають уявити наповнення, але саме у вашому наборі картки можуть бути іншими.</p>
<section class="bs-faq-accordion" data-bs-faq-accordion data-bs-faq-id="prod-op-jp-bulk-155">
<h2 class="bs-faq-title">FAQ</h2>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-op-jp-bulk-155-panel-1" aria-expanded="false" id="bs-faq-prod-op-jp-bulk-155-button-1"><span>Що означають C, UC та R у One Piece Card Game?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-op-jp-bulk-155-button-1" id="bs-faq-prod-op-jp-bulk-155-panel-1"><p>Це позначення рідкісності: C (Common) — звичайні картки, UC (Uncommon) — незвичайні, R (Rare) — рідкісні. Рідкісність за класифікацією гри не дорівнює ціні картки на ринку.</p></div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-op-jp-bulk-155-panel-2" aria-expanded="false" id="bs-faq-prod-op-jp-bulk-155-button-2"><span>Чи можуть бути однакові картки в наборі?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-op-jp-bulk-155-button-2" id="bs-faq-prod-op-jp-bulk-155-panel-2"><p>Під час комплектування ми стараємося уникати повторів, але гарантії немає. Якщо випадково трапиться дублікат, це не означає, що набір бракований.</p></div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-op-jp-bulk-155-panel-3" aria-expanded="false" id="bs-faq-prod-op-jp-bulk-155-button-3"><span>Чи мають картки професійну оцінку стану?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-op-jp-bulk-155-button-3" id="bs-faq-prod-op-jp-bulk-155-panel-3"><p>Ні. Ми не заявляємо оцінки за шкалами PSA, BGS або CGC і не вказуємо ступінь стану. Це оригінальні картки з раніше відкритих бустерів.</p></div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-op-jp-bulk-155-panel-4" aria-expanded="false" id="bs-faq-prod-op-jp-bulk-155-button-4"><span>One Piece Bulk 155 — це фабричний запечатаний набір?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-op-jp-bulk-155-button-4" id="bs-faq-prod-op-jp-bulk-155-panel-4"><p>Ні. Ми самостійно складаємо набори з карток, які дістали з бустерів під час своїх відкриттів. Це не запечатаний товар від Bandai.</p></div>
</div>
</section>
HTML,
    ];
    $cards['PKM-MIX-BULK-100'] = [
        'sku' => 'PKM-MIX-BULK-100',
        'name' => 'Набір Pokémon TCG: Bulk Mix 100 — C/U (Мікс видань: JP / EN / KR)',
        'keyword' => 'Pokemon-Bulk-Mix-100',
        'meta_title' => 'Pokémon Bulk Mix 100 — JP / EN / KR | Booster Shop',
        'meta_description' => 'Pokémon TCG Bulk Mix: 100 оригінальних карток C/U. Можливі японська, англійська й корейська мови без фіксованого співвідношення.',
        'tag' => 'Pokémon TCG, Bulk Mix, JP EN KR, різні видання, колекційні картки, Common, Uncommon',
        'manufacturer_id' => 11,
        'price' => '150.0000',
        'categories' => [59, 64],
        'attributes' => [12 => 'Японська, англійська, корейська (JP / EN / KR)', 13 => 'Bulk Mix / різні сети Pokémon TCG (JP / EN / KR)', 17 => 'Оригінальні картки з розкритих бустерів; ступінь стану не заявляється', 18 => 'Картки з оригінальних бустерів, розкритих магазином', 20 => 'The Pokémon Company', 21 => 'Сформований набір карток; паковання підбирається при комплектації замовлення', 24 => 'Немає; спеціальні рідкості не гарантуються'],
        'new_attributes' => ['count' => '100', 'rarity' => '100 C/U'],
        'description_sha256' => 'f5259abb91d26514e825bdb4e95cbf0d5f9165b66c93009b6968ce57ce10e171',
        'stored_description_sha256' => 'c584f60391aab57b86c952d3c8fa1e18bc699deb2dbc81775b77ecde12f870cb',
        'description' => <<<'HTML'
<p>Для альбому з картками Покемон зовсім не обов’язково обирати лише одну мову видання. Bulk Mix — варіант для тих, кому цікаві картки різних локалізацій і хто хоче поповнити колекцію або підготувати більше карток для обміну.</p>
<h2>Склад Bulk Mix 100</h2>
<p><strong>100 оригінальних карток Pokémon TCG рідкісностей Common/Uncommon (C/U).</strong></p>
<p>У наборі можуть бути японські, англійські й корейські картки (JP / EN / KR), але їхнє співвідношення щоразу може змінюватися. Так само не закріплено конкретні сети, персонажів або ілюстрації: це добірка для колекції, а не обіцянка окремої особливої чи дорогої картки.</p>
<h2>Як з’являється така добірка</h2>
<p>Ми складаємо Bulk Mix із карток, які самі раніше дістали з оригінальних бустерів. Це не продукція, запечатана виробником. Паковання підбираємо під час комплектування замовлення, щоб урахувати обсяг і захист карток.</p>
<p>На знімках — приклади можливих карток, а не точний склад набору, який отримаєте ви.</p>
<section class="bs-faq-accordion" data-bs-faq-accordion data-bs-faq-id="prod-pkm-mix-bulk-100">
<h2 class="bs-faq-title">FAQ</h2>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-pkm-mix-bulk-100-panel-1" aria-expanded="false" id="bs-faq-prod-pkm-mix-bulk-100-button-1"><span>Чи будуть у Bulk Mix картки всіма трьома мовами?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-pkm-mix-bulk-100-button-1" id="bs-faq-prod-pkm-mix-bulk-100-panel-1"><p>Не обов’язково. Можуть трапитися картки японською, англійською або корейською, але ми не фіксуємо частку кожної мови й не обіцяємо присутність усіх трьох в одному наборі.</p></div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-pkm-mix-bulk-100-panel-2" aria-expanded="false" id="bs-faq-prod-pkm-mix-bulk-100-button-2"><span>Що означає C/U у Pokémon TCG?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-pkm-mix-bulk-100-button-2" id="bs-faq-prod-pkm-mix-bulk-100-panel-2"><p>C (Common) — звичайна рідкісність, U (Uncommon) — незвичайна. Це категорії карток у грі, а не оцінка їхньої колекційної чи ринкової вартості.</p></div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-pkm-mix-bulk-100-panel-3" aria-expanded="false" id="bs-faq-prod-pkm-mix-bulk-100-button-3"><span>Чи трапляються в Pokémon Bulk Mix повтори?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-pkm-mix-bulk-100-button-3" id="bs-faq-prod-pkm-mix-bulk-100-panel-3"><p>Під час складання ми прагнемо уникати однакових карток, проте обіцяти цього не можемо. Випадковий дублікат сам по собі не є браком.</p></div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-pkm-mix-bulk-100-panel-4" aria-expanded="false" id="bs-faq-prod-pkm-mix-bulk-100-button-4"><span>Це новий запечатаний набір Pokémon від виробника?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-pkm-mix-bulk-100-button-4" id="bs-faq-prod-pkm-mix-bulk-100-panel-4"><p>Ні. Bulk Mix ми формуємо власноруч з оригінальних карток, які раніше вийняли з відкритих бустерів. Це не запечатаний виробником набір.</p></div>
</div>
</section>
HTML,
    ];
    foreach ($cards as $sku => $card) {
        if (hash('sha256', $card['description']) !== $card['description_sha256']) bs_fail('Embedded description SHA-256 mismatch: ' . $sku);
        if (mb_strlen($card['meta_title'], 'UTF-8') > META_TITLE_MAX) bs_fail('meta_title too long: ' . $sku);
        if (mb_strlen($card['meta_description'], 'UTF-8') > META_DESCRIPTION_MAX) bs_fail('meta_description too long: ' . $sku);
        if (count($card['attributes']) !== 7 || count($card['new_attributes']) !== 2) bs_fail('Attribute set incomplete: ' . $sku);
        foreach (array_keys($card['attributes']) as $id) if (!isset(EXISTING_ATTRIBUTES[$id])) bs_fail('Unexpected attribute id: ' . $id);
        foreach (array_keys(NEW_ATTRIBUTES) as $key) if (!isset($card['new_attributes'][$key])) bs_fail('Missing new attribute value: ' . $sku);
    }
    return $cards;
}

function bs_expected_product(array $card, int $id, string $now): array {
    return [
        'product_id' => (string) $id, 'master_id' => '0', 'model' => $card['sku'], 'sku' => $card['sku'],
        'upc' => null, 'ean' => null, 'jan' => null, 'isbn' => null, 'mpn' => null,
        'location' => '', 'variant' => '', 'override' => '', 'quantity' => '0',
        'stock_status_id' => (string) STOCK_STATUS_ID, 'image' => '', 'manufacturer_id' => (string) $card['manufacturer_id'],
        'shipping' => '1', 'price' => $card['price'], 'points' => '0', 'tax_class_id' => '0',
        'date_available' => substr($now, 0, 10), 'weight' => '0.00000000', 'weight_class_id' => (string) WEIGHT_CLASS_ID,
        'length' => '0.00000000', 'width' => '0.00000000', 'height' => '0.00000000', 'length_class_id' => (string) LENGTH_CLASS_ID,
        'subtract' => (string) SUBTRACT, 'minimum' => '1', 'rating' => '0', 'sort_order' => '0', 'status' => '0',
        'date_added' => $now, 'date_modified' => $now,
    ];
}
function bs_verify_product(mysqli $db, array $t, array $card, int $id, array $attrIds, string $now): void {
    $sku = $card['sku'];
    bs_same(bs_norm(bs_one(bs_select($db, 'SELECT * FROM `' . $t['product'] . '` WHERE product_id=?', 'i', [$id]), $sku . ' product')),
        bs_expected_product($card, $id, $now), $sku . ' product');
    bs_same(bs_norm_all(bs_select($db, 'SELECT * FROM `' . $t['product_description'] . '` WHERE product_id=?', 'i', [$id])), [[
        'product_id' => (string) $id, 'language_id' => (string) LANGUAGE_ID, 'name' => bs_oc($card['name']),
        'description' => bs_oc($card['description']), 'tag' => bs_oc($card['tag']), 'meta_title' => bs_oc($card['meta_title']),
        'meta_description' => bs_oc($card['meta_description']), 'meta_keyword' => '',
    ]], $sku . ' description');
    $desc = bs_one(bs_select($db, 'SELECT description FROM `' . $t['product_description'] . '` WHERE product_id=?', 'i', [$id]), $sku . ' description');
    if (hash('sha256', (string) $desc['description']) !== $card['stored_description_sha256']) bs_fail($sku . ' stored description hash mismatch');
    bs_same(bs_norm_all(bs_select($db, 'SELECT * FROM `' . $t['product_to_store'] . '` WHERE product_id=?', 'i', [$id])),
        [['product_id' => (string) $id, 'store_id' => (string) STORE_ID]], $sku . ' store');
    $categories = [];
    foreach ($card['categories'] as $categoryId) $categories[] = ['product_id' => (string) $id, 'category_id' => (string) $categoryId];
    bs_same(bs_norm_all(bs_select($db, 'SELECT * FROM `' . $t['product_to_category'] . '` WHERE product_id=? ORDER BY category_id', 'i', [$id])), $categories, $sku . ' categories');
    $attributes = [];
    foreach ($card['attributes'] as $attributeId => $text) $attributes[(int) $attributeId] = $text;
    foreach ($card['new_attributes'] as $key => $text) $attributes[$attrIds[$key]] = $text;
    ksort($attributes); $expectedAttributes = [];
    foreach ($attributes as $attributeId => $text) {
        $expectedAttributes[] = ['product_id' => (string) $id, 'attribute_id' => (string) $attributeId, 'language_id' => (string) LANGUAGE_ID, 'text' => bs_oc($text)];
    }
    bs_same(bs_norm_all(bs_select($db, 'SELECT * FROM `' . $t['product_attribute'] . '` WHERE product_id=? ORDER BY attribute_id', 'i', [$id])), $expectedAttributes, $sku . ' attributes');
    $seo = bs_norm(bs_one(bs_select($db, 'SELECT * FROM `' . $t['seo_url'] . '` WHERE `key`=\'product_id\' AND `value`=?', 's', [(string) $id]), $sku . ' SEO URL'));
    unset($seo['seo_url_id']);
    bs_same($seo, ['store_id' => (string) STORE_ID, 'language_id' => (string) LANGUAGE_ID, 'key' => 'product_id', 'value' => (string) $id, 'keyword' => $card['keyword'], 'sort_order' => '0'], $sku . ' SEO URL');
    if (bs_select($db, 'SELECT product_id FROM `' . $t['product_to_layout'] . '` WHERE product_id=?', 'i', [$id]) !== []) bs_fail($sku . ' unexpected layout row');
}
function bs_verify_attribute(mysqli $db, array $t, int $id, string $name, int $sortOrder): void {
    bs_same(bs_norm_all(bs_select($db, 'SELECT * FROM `' . $t['attribute'] . '` WHERE attribute_id=?', 'i', [$id])),
        [['attribute_id' => (string) $id, 'attribute_group_id' => (string) ATTRIBUTE_GROUP_ID, 'sort_order' => (string) $sortOrder]], 'attribute ' . $name);
    bs_same(bs_norm_all(bs_select($db, 'SELECT * FROM `' . $t['attribute_description'] . '` WHERE attribute_id=?', 'i', [$id])),
        [['attribute_id' => (string) $id, 'language_id' => (string) LANGUAGE_ID, 'name' => bs_oc($name)]], 'attribute description ' . $name);
}

function bs_run(): void {
    $cwd = getcwd();
    if (!is_string($cwd) || $cwd === '') bs_fail('Cannot determine cwd');
    bs_log('patch', PATCH_NAME); bs_log('cwd', $cwd); bs_log('time', date('c'));
    $config = bs_path($cwd, 'config.php');
    if (!is_file($config)) bs_fail('config.php not found; run from ~/public_html');
    bs_lint_self();
    if (!function_exists('mb_strlen')) bs_fail('mbstring is unavailable');
    $cards = bs_cards();
    require_once $config;
    $db = bs_connect(); $inTransaction = false;
    try {
        $prefix = (string) DB_PREFIX;
        $schemas = [
            'product' => ['product_id','master_id','model','sku','upc','ean','jan','isbn','mpn','location','variant','override','quantity','stock_status_id','image','manufacturer_id','shipping','price','points','tax_class_id','date_available','weight','weight_class_id','length','width','height','length_class_id','subtract','minimum','rating','sort_order','status','date_added','date_modified'],
            'product_description' => ['product_id','language_id','name','description','tag','meta_title','meta_description','meta_keyword'],
            'product_to_store' => ['product_id','store_id'],
            'product_to_category' => ['product_id','category_id'],
            'product_attribute' => ['product_id','attribute_id','language_id','text'],
            'product_to_layout' => ['product_id','store_id','layout_id'],
            'seo_url' => ['seo_url_id','store_id','language_id','key','value','keyword','sort_order'],
            'attribute' => ['attribute_id','attribute_group_id','sort_order'],
            'attribute_description' => ['attribute_id','language_id','name'],
        ];
        $written = ['product','product_description','product_to_store','product_to_category','product_attribute','seo_url','attribute','attribute_description'];
        $t = [];
        foreach ($schemas as $suffix => $expected) {
            $table = bs_table($prefix, $suffix); $t[$suffix] = $table;
            if (!bs_table_exists($db, $table)) bs_fail('Required table missing: ' . $table);
            $columns = array_keys(bs_columns($db, $table)); sort($columns); sort($expected);
            if ($columns !== $expected) bs_fail('Schema differs from verified 15.10 backup: ' . $table);
            if (!in_array($suffix, $written, true)) continue;
            $engine = bs_select($db, 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', 's', [$table]);
            if (count($engine) !== 1 || strtoupper((string) $engine[0]['ENGINE']) !== 'INNODB') bs_fail('InnoDB required: ' . $table);
            // Triggers can add writes outside the approved tables.
            $triggers = bs_select($db, 'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE=?', 's', [$table]);
            if ($triggers !== []) bs_fail('Unexpected trigger on scoped table: ' . $table);
        }
        foreach (['category','category_description','manufacturer','attribute_group','stock_status','language'] as $suffix) {
            $t[$suffix] = bs_table($prefix, $suffix);
            if (!bs_table_exists($db, $t[$suffix])) bs_fail('Required table missing: ' . $t[$suffix]);
        }

        $db->begin_transaction(); $inTransaction = true;
        // Read-only reference preconditions.
        bs_one(bs_select($db, 'SELECT language_id FROM `' . $t['language'] . '` WHERE language_id=? AND status=1', 'i', [LANGUAGE_ID]), 'language');
        bs_one(bs_select($db, 'SELECT attribute_group_id FROM `' . $t['attribute_group'] . '` WHERE attribute_group_id=?', 'i', [ATTRIBUTE_GROUP_ID]), 'attribute group');
        bs_one(bs_select($db, 'SELECT stock_status_id FROM `' . $t['stock_status'] . '` WHERE stock_status_id=? AND language_id=?', 'ii', [STOCK_STATUS_ID, LANGUAGE_ID]), 'stock status');
        foreach (MANUFACTURERS as $id => $name) {
            $row = bs_one(bs_select($db, 'SELECT name FROM `' . $t['manufacturer'] . '` WHERE manufacturer_id=?', 'i', [$id]), 'manufacturer ' . $id);
            if ($row['name'] !== $name) bs_fail('Unexpected manufacturer name: ' . $id);
        }
        foreach (CATEGORIES as $id => [$parent, $name]) {
            $row = bs_one(bs_select($db, 'SELECT c.parent_id, cd.name FROM `' . $t['category'] . '` c JOIN `' . $t['category_description'] . '` cd ON cd.category_id=c.category_id AND cd.language_id=? WHERE c.category_id=?', 'ii', [LANGUAGE_ID, $id]), 'category ' . $id);
            if ((int) $row['parent_id'] !== $parent || $row['name'] !== bs_oc($name)) bs_fail('Unexpected category parent/name: ' . $id);
        }
        foreach (EXISTING_ATTRIBUTES as $id => $name) {
            $row = bs_one(bs_select($db, 'SELECT a.attribute_group_id, ad.name FROM `' . $t['attribute'] . '` a JOIN `' . $t['attribute_description'] . '` ad ON ad.attribute_id=a.attribute_id AND ad.language_id=? WHERE a.attribute_id=?', 'ii', [LANGUAGE_ID, $id]), 'attribute ' . $id);
            if ((int) $row['attribute_group_id'] !== ATTRIBUTE_GROUP_ID || $row['name'] !== bs_oc($name)) bs_fail('Unexpected attribute group/name: ' . $id);
        }

        // Existing state of everything this patch would create; lock for the transaction.
        $skus = array_keys($cards);
        $products = bs_select($db, 'SELECT product_id, model, sku FROM `' . $t['product'] . '` WHERE model IN (?,?,?) OR sku IN (?,?,?) FOR UPDATE', 'ssssss', array_merge($skus, $skus));
        $keywords = array_column($cards, 'keyword');
        $routes = bs_select($db, 'SELECT seo_url_id, `key`, `value`, keyword FROM `' . $t['seo_url'] . '` WHERE keyword IN (?,?,?) FOR UPDATE', 'sss', $keywords);
        $newNames = [];
        foreach (NEW_ATTRIBUTES as [$name, $sortOrder]) $newNames[] = bs_oc($name);
        $attrRows = bs_select($db, 'SELECT ad.attribute_id, ad.language_id, ad.name, a.attribute_group_id FROM `' . $t['attribute_description'] . '` ad LEFT JOIN `' . $t['attribute'] . '` a ON a.attribute_id=ad.attribute_id WHERE ad.name IN (?,?) FOR UPDATE', 'ss', $newNames);

        if ($products === [] && $routes === [] && $attrRows === []) {
            bs_log('preconditions', 'ok');
        } elseif (count($products) === 3 && count($routes) === 3 && count($attrRows) === 2) {
            // Already applied: confirm identity only. Owner-editable fields
            // (status, stock, price, images, copy) are expected to change after deploy.
            $byModel = [];
            foreach ($products as $p) {
                if ($p['model'] !== $p['sku'] || !isset($cards[$p['model']])) bs_fail('Conflicting product row for a CAT-005 SKU; no repair permitted');
                $byModel[$p['model']] = (int) $p['product_id'];
            }
            if (count($byModel) !== 3) bs_fail('Duplicate CAT-005 SKU rows; no repair permitted');
            foreach ($routes as $r) {
                $sku = array_search($r['keyword'], $keywords, true);
                if ($r['key'] !== 'product_id' || $sku === false || (string) $r['value'] !== (string) $byModel[$skus[$sku]]) bs_fail('SEO keyword points elsewhere; no repair permitted');
            }
            foreach ($attrRows as $a) {
                if ((int) $a['attribute_group_id'] !== ATTRIBUTE_GROUP_ID || (int) $a['language_id'] !== LANGUAGE_ID) bs_fail('New attribute name exists outside group 7; no repair permitted');
            }
            $db->rollback(); $inTransaction = false;
            bs_log('already_applied', 'yes');
            foreach ($byModel as $sku => $id) bs_log('product_' . $sku, (string) $id);
            bs_log('done', 'ok'); bs_self_delete(); return;
        } else {
            bs_fail('Partial or conflicting CAT-005 state (products=' . count($products) . ', seo=' . count($routes) . ', attributes=' . count($attrRows) . '); no writes permitted');
        }

        $backupDir = bs_path($cwd, '_patch_backups/' . PATCH_NAME . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)));
        if (!mkdir($backupDir, 0700, true)) bs_fail('Cannot create backup directory');
        bs_log('backup_dir', $backupDir);
        bs_json_backup($backupDir, 'preflight_snapshot', [
            'note' => 'INSERT-only patch; nothing existing is modified. Snapshot of the absent-state checks.',
            'products_with_sku' => $products, 'seo_keywords' => $routes, 'new_attribute_names' => $attrRows,
        ]);

        $now = date('Y-m-d H:i:s');
        $lang = LANGUAGE_ID;
        $attrIds = [];
        foreach (NEW_ATTRIBUTES as $key => [$name, $sortOrder]) {
            $id = bs_insert($db, 'INSERT INTO `' . $t['attribute'] . '` (attribute_group_id,sort_order) VALUES (?,?)', 'ii', [ATTRIBUTE_GROUP_ID, $sortOrder]);
            if ($id <= 55) bs_fail('Invalid/protected attribute ID assigned');
            bs_insert($db, 'INSERT INTO `' . $t['attribute_description'] . '` (attribute_id,language_id,name) VALUES (?,?,?)', 'iis', [$id, $lang, bs_oc($name)]);
            $attrIds[$key] = $id;
        }
        $created = [];
        foreach ($cards as $sku => $card) {
            $id = bs_insert($db, 'INSERT INTO `' . $t['product'] . '` (master_id,model,sku,upc,ean,jan,isbn,mpn,location,variant,override,quantity,stock_status_id,image,manufacturer_id,shipping,price,points,tax_class_id,date_available,weight,weight_class_id,length,width,height,length_class_id,subtract,minimum,rating,sort_order,status,date_added,date_modified)'
                . ' VALUES (0,?,?,NULL,NULL,NULL,NULL,NULL,\'\',\'\',\'\',0,?,\'\',?,1,?,0,0,?,0,?,0,0,0,?,?,1,0,0,0,?,?)',
                'ssiissiiiss', [$sku, $sku, STOCK_STATUS_ID, $card['manufacturer_id'], $card['price'], substr($now, 0, 10), WEIGHT_CLASS_ID, LENGTH_CLASS_ID, SUBTRACT, $now, $now]);
            if ($id <= 213) bs_fail('Invalid/protected product ID assigned');
            $created[$sku] = $id;
            bs_insert($db, 'INSERT INTO `' . $t['product_description'] . '` (product_id,language_id,name,description,tag,meta_title,meta_description,meta_keyword) VALUES (?,?,?,?,?,?,?,\'\')',
                'iisssss', [$id, $lang, bs_oc($card['name']), bs_oc($card['description']), bs_oc($card['tag']), bs_oc($card['meta_title']), bs_oc($card['meta_description'])]);
            bs_insert($db, 'INSERT INTO `' . $t['product_to_store'] . '` (product_id,store_id) VALUES (?,?)', 'ii', [$id, STORE_ID]);
            foreach ($card['categories'] as $categoryId) {
                bs_insert($db, 'INSERT INTO `' . $t['product_to_category'] . '` (product_id,category_id) VALUES (?,?)', 'ii', [$id, $categoryId]);
            }
            $values = [];
            foreach ($card['attributes'] as $attributeId => $text) $values[(int) $attributeId] = $text;
            foreach ($card['new_attributes'] as $key => $text) $values[$attrIds[$key]] = $text;
            foreach ($values as $attributeId => $text) {
                bs_insert($db, 'INSERT INTO `' . $t['product_attribute'] . '` (product_id,attribute_id,language_id,text) VALUES (?,?,?,?)', 'iiis', [$id, $attributeId, $lang, bs_oc($text)]);
            }
            bs_insert($db, 'INSERT INTO `' . $t['seo_url'] . '` (store_id,language_id,`key`,`value`,keyword,sort_order) VALUES (?,?,\'product_id\',?,?,0)', 'iiss', [STORE_ID, $lang, (string) $id, $card['keyword']]);
        }

        // In-transaction verification of every created row.
        foreach (NEW_ATTRIBUTES as $key => [$name, $sortOrder]) bs_verify_attribute($db, $t, $attrIds[$key], $name, $sortOrder);
        foreach ($cards as $sku => $card) bs_verify_product($db, $t, $card, $created[$sku], $attrIds, $now);
        $uses = bs_select($db, 'SELECT COUNT(*) AS n FROM `' . $t['product_attribute'] . '` WHERE attribute_id IN (?,?)', 'ii', array_values($attrIds));
        if ((int) $uses[0]['n'] !== 3 * count(NEW_ATTRIBUTES)) bs_fail('New attributes are used outside the created products');
        bs_log('verification', 'ok');

        $productIds = implode(',', array_values($created));
        $attributeIds = array_values($attrIds);
        bs_json_backup($backupDir, 'created_ids', [
            'created_product_ids' => $created, 'created_attribute_ids' => $attrIds, 'date_added' => $now,
            'note' => 'Prepared before commit. On failed apply the DB transaction is rolled back; use only after done=ok.',
        ]);
        // Child tables that may hold rows for these products by rollback time
        // (the owner adds images, stock and discounts after deploy).
        $children = [];
        foreach (['product_attribute','product_description','product_discount','product_filter','product_image','product_option','product_option_value','product_related','product_reward','product_special','product_subscription','product_to_category','product_to_download','product_to_layout','product_to_store','product_code','product_report','product_viewed','review'] as $suffix) {
            $table = bs_table($prefix, $suffix);
            if (bs_table_exists($db, $table) && isset(bs_columns($db, $table)['product_id'])) $children[] = $table;
        }
        $rollback = "-- CAT-005 WP2: owner-only rollback; removes exactly the products and attributes created by this patch.\nSET NAMES utf8mb4;\nSTART TRANSACTION;\n"
            . 'DELETE FROM `' . $t['seo_url'] . "` WHERE `key`='product_id' AND `value` IN ('" . implode("','", array_values($created)) . "');\n";
        foreach ($children as $table) {
            $rollback .= 'DELETE FROM `' . $table . '` WHERE product_id IN (' . $productIds . ')'
                . ($table === bs_table($prefix, 'product_related') ? ' OR related_id IN (' . $productIds . ')' : '') . ";\n";
        }
        $rollback .= 'DELETE FROM `' . $t['product'] . '` WHERE product_id IN (' . $productIds . ");\n";
        foreach ($attributeIds as $attributeId) {
            $unused = 'NOT EXISTS (SELECT 1 FROM `' . $t['product_attribute'] . '` pa WHERE pa.attribute_id=' . $attributeId . ')';
            $rollback .= 'DELETE FROM `' . $t['attribute_description'] . '` WHERE attribute_id=' . $attributeId . ' AND ' . $unused . ";\n"
                . 'DELETE FROM `' . $t['attribute'] . '` WHERE attribute_id=' . $attributeId . ' AND ' . $unused . ";\n";
        }
        $rollback .= "COMMIT;\n";
        if (file_put_contents(bs_path($backupDir, 'db/rollback.sql'), $rollback, LOCK_EX) !== strlen($rollback)) bs_fail('Cannot write complete rollback SQL');
        bs_log('backup_db', bs_path($backupDir, 'db/rollback.sql'));
        $db->commit(); $inTransaction = false;
        foreach ($attrIds as $key => $id) bs_log('attribute_' . $key, (string) $id);
        foreach ($created as $sku => $id) bs_log('product_' . $sku, (string) $id);
        bs_log('status', 'disabled (0) for all created products');
        bs_log('transaction', 'committed');
        bs_log('done', 'ok'); bs_self_delete();
    } catch (Throwable $e) {
        if ($inTransaction) { $db->rollback(); bs_log('transaction', 'rolled_back'); }
        throw $e;
    } finally { $db->close(); }
}
try { bs_run(); } catch (Throwable $e) {
    // Driver messages may include credentials/server details. Do not print them.
    bs_log('error', $e instanceof mysqli_sql_exception ? 'Database operation failed (code ' . $e->getCode() . '); no credentials logged' : $e->getMessage());
    bs_log('done', 'failed'); exit(1);
}
