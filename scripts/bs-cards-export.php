<?php
/**
 * bs-cards-export — READ-ONLY export of every product and category text.
 * Run from ~/public_html. Writes nothing to the site. SELECT statements only.
 */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
// Our own checks handle every failure; without this PHP 8.1+ throws instead.
mysqli_report(MYSQLI_REPORT_OFF);

function fail(string $m): void { fwrite(STDERR, "\nЗУПИНКА: $m\n"); exit(1); }

$cfg = getcwd() . '/config.php';
if (!is_file($cfg)) fail('config.php не знайдено. Запускай командою: cd ~/public_html && php ~/bs-cards-export.php');
require $cfg;
foreach (['DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PREFIX'] as $c) {
    if (!defined($c)) fail("config.php не містить $c — це не схоже на робочий config OpenCart");
}

$db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, defined('DB_PORT') ? (int) DB_PORT : 3306);
if ($db->connect_errno) fail('не вдалося підключитись до бази: ' . $db->connect_error);
$db->set_charset('utf8mb4');

$p = (string) DB_PREFIX;
if (!preg_match('/^[A-Za-z0-9_]*$/', $p)) fail('DB_PREFIX має недопустимі символи — зупиняюсь');
$T = function (string $s) use ($p): string { return '`' . $p . $s . '`'; };

function q(mysqli $db, string $sql): array {
    $r = $db->query($sql);
    if ($r === false) fail('помилка SQL: ' . $db->error . ' | ' . $sql);
    $out = [];
    while ($row = $r->fetch_assoc()) $out[] = $row;
    $r->free();
    return $out;
}

// Dominant language — this install has one, but detect instead of assuming.
$lang = q($db, 'SELECT language_id, COUNT(*) n FROM ' . $T('product_description') . ' GROUP BY language_id ORDER BY n DESC LIMIT 1');
if ($lang === []) fail('таблиця product_description порожня — перевір, чи це та база');
$L = (int) $lang[0]['language_id'];

// ---------------------------------------------------------------- text tools
function plain(string $html): string {
    $s = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', ' ', $html) ?? $html;
    $s = preg_replace('~<[^>]+>~', ' ', $s) ?? $s;
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim((string) preg_replace('~\s+~u', ' ', $s));
}
/**
 * OpenCart stores product/category descriptions HTML-entity-encoded
 * (&lt;h2&gt;, class=&quot;...&quot;). Every structural check below works on real
 * tags, so the raw column must be decoded first. Conditional, so a row that is
 * already stored as plain HTML is not double-decoded.
 * Added 2026-08-28: without it h2/h3/strong/ul/a and faq_items were always 0 and
 * NO_HEADING / NO_EMPHASIS / NO_FAQ fired on every product.
 */
function rawHtml(string $s): string {
    return strpos($s, '&lt;') === false ? $s : html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
/** Body = description with the FAQ accordion removed. */
function bodyOnly(string $html): string {
    return (string) preg_replace('~<section\b[^>]*bs-faq-accordion.*?</section>~is', ' ', $html);
}
function faqOnly(string $html): string {
    return preg_match('~<section\b[^>]*bs-faq-accordion.*?</section>~is', $html, $m) ? $m[0] : '';
}
function countTag(string $html, string $tag): int {
    return preg_match_all('~<' . $tag . '\b~i', $html);
}
/** Head phrase of a meta title: text before an em dash or the brand pipe. */
function headPhrase(string $title): string {
    $t = preg_split('~\s+[|—–]\s+~u', $title)[0] ?? $title;
    return trim($t);
}
/** Are the meaningful words of $head present in $text (prefix match, case-folded)? */
function headInText(string $head, string $text): bool {
    preg_match_all('~[\p{L}\p{N}]{4,}~u', mb_strtolower($head, 'UTF-8'), $m);
    $words = array_slice($m[0], 0, 3);
    if ($words === []) return true;
    $t = mb_strtolower($text, 'UTF-8');
    foreach ($words as $w) {
        if (mb_strpos($t, mb_substr($w, 0, 6, 'UTF-8'), 0, 'UTF-8') === false) return false;
    }
    return true;
}
function reCount(string $re, string $text): int { return (int) preg_match_all($re, $text); }

// ---------------------------------------------------------------- collectors
$RE_WE      = '~\b(друкуємо|виготовляємо|робимо|друкуєм)\b~ui';
$RE_PASSIVE = '~\b(друкується|виготовляється|комплектується|друкуються|ставиться)\b~ui';
$RE_TY      = '~\b(задаєш|встигаєш|можеш|обираєш|твій|тобі|тебе)\b~ui';
$RE_VY      = '~\b(ви|вам|вас|ваш\w*|сідайте|грайте|звірте|перевірте)\b~ui';
$RE_INTERNAL= '~(\bпарті[ії]\b|\bп.ятір\w+|\bSKU\b|\bартикул\w*|картка товару|цієї хвилі)~ui';
$RE_SUPER   = '~(\bнай\p{L}+|\bєдин\w+)~ui';
// A superlative scoped to the current assortment — decays as soon as the range grows.
$RE_BATCH   = '~((\bнай\p{L}+|\bєдин\w+)[^.!?]{0,90}(сері[ії]|парті[ії]|п.ятір\w+|з трьох|моделей|виробів)'
            . '|(сері[ії]|парті[ії]|п.ятір\w+)[^.!?]{0,90}(\bнай\p{L}+|\bєдин\w+))~ui';
$RE_PLACE   = '~(уточнюємо|уточнюється|TBD|\{\{|LOREM|XXX)~ui';

$products = q($db,
    'SELECT p.product_id, p.model, p.sku, p.status, p.quantity, p.price, p.image, p.sort_order,'
  . ' pd.name, pd.description, pd.meta_title, pd.meta_description, pd.meta_keyword'
  . ' FROM ' . $T('product') . ' p'
  . ' JOIN ' . $T('product_description') . ' pd ON pd.product_id = p.product_id AND pd.language_id = ' . $L
  . ' ORDER BY p.product_id');

$seo = [];
foreach (q($db, 'SELECT `value`, keyword FROM ' . $T('seo_url') . ' WHERE `key` = \'product_id\'') as $r) {
    $seo[(int) $r['value']] = (string) $r['keyword'];
}
$cats = [];
foreach (q($db, 'SELECT product_id, category_id FROM ' . $T('product_to_category')) as $r) {
    $cats[(int) $r['product_id']][] = (int) $r['category_id'];
}
$codes = [];
$hasCode = q($db, 'SHOW TABLES LIKE \'' . $p . 'product_code\'');
if ($hasCode !== []) {
    foreach (q($db, 'SELECT product_id, `code`, `value` FROM ' . $T('product_code')) as $r) {
        $codes[(int) $r['product_id']][(string) $r['code']] = (string) $r['value'];
    }
}
$attrs = [];
foreach (q($db,
    'SELECT pa.product_id, ad.name, pa.text FROM ' . $T('product_attribute') . ' pa'
  . ' JOIN ' . $T('attribute_description') . ' ad ON ad.attribute_id = pa.attribute_id AND ad.language_id = ' . $L
  . ' WHERE pa.language_id = ' . $L) as $r) {
    $attrs[(int) $r['product_id']][(string) $r['name']] = (string) $r['text'];
}
$categories = q($db,
    'SELECT c.category_id, c.parent_id, c.status, c.sort_order,'
  . ' cd.name, cd.description, cd.meta_title, cd.meta_description, cd.meta_keyword'
  . ' FROM ' . $T('category') . ' c'
  . ' JOIN ' . $T('category_description') . ' cd ON cd.category_id = c.category_id AND cd.language_id = ' . $L
  . ' ORDER BY c.parent_id, c.sort_order, c.category_id');
$catSeo = [];
foreach (q($db, 'SELECT `value`, keyword FROM ' . $T('seo_url') . ' WHERE `key` = \'path\'') as $r) {
    $parts = explode('_', (string) $r['value']);
    $catSeo[(int) end($parts)] = (string) $r['keyword'];
}
$catName = [];
foreach ($categories as $c) $catName[(int) $c['category_id']] = (string) $c['name'];

// ---------------------------------------------------------------- analysis
$names = [];
foreach ($products as $r) $names[] = mb_strtolower((string) $r['name'], 'UTF-8');
$live = [];
foreach ($products as $r) if ((int) $r['status'] === 1) $live[] = mb_strtolower((string) $r['name'], 'UTF-8');

$rows = [];
foreach ($products as $r) {
    $id   = (int) $r['product_id'];
    $sku  = $codes[$id]['SKU'] ?? (string) ($r['sku'] ?: $r['model']);
    $desc = rawHtml((string) $r['description']);
    $body = bodyOnly($desc);
    $faq  = faqOnly($desc);
    $bt   = plain($body);
    $ft   = plain($faq);
    $all  = $bt . ' ' . $ft;
    $is3d = (bool) preg_match('~^(BR-|FIG-|ACC-3D-)~', $sku);

    $f = [];
    if ($bt === '')                                        $f[] = 'EMPTY_BODY';
    if (!headInText(headPhrase((string) $r['meta_title']), $bt)) $f[] = 'KEY_NOT_IN_BODY';
    if (!headInText(headPhrase((string) $r['meta_title']), (string) $r['name'])) $f[] = 'NAME_VS_TITLE';
    if (countTag($body, 'h2') + countTag($body, 'h3') === 0) $f[] = 'NO_HEADING';
    if (countTag($body, 'strong') + countTag($body, 'b') === 0) $f[] = 'NO_EMPHASIS';
    if (mb_strlen((string) $r['meta_description'], 'UTF-8') > 155) $f[] = 'MD_TOO_LONG';
    if (mb_strlen((string) $r['meta_description'], 'UTF-8') < 80)  $f[] = 'MD_TOO_SHORT';
    if (trim((string) $r['meta_title']) === '')            $f[] = 'NO_META_TITLE';
    if (mb_strlen((string) $r['meta_title'], 'UTF-8') > 60) $f[] = 'MT_TOO_LONG';
    if (mb_strlen($bt, 'UTF-8') < 400)                     $f[] = 'THIN_BODY';
    if ($faq === '')                                       $f[] = 'NO_FAQ';
    if (!isset($seo[$id]) || $seo[$id] === '')             $f[] = 'NO_SEO_URL';
    if (reCount($RE_PLACE, $all) > 0)                      $f[] = 'PLACEHOLDER';
    if (reCount($RE_INTERNAL, $all) > 0)                   $f[] = 'INTERNAL_VOCAB';
    if (reCount($RE_SUPER, $all) > 0)                      $f[] = 'SUPERLATIVE';
    if (reCount($RE_BATCH, $all) > 0)                      $f[] = 'BATCH_SCOPED';
    if (reCount($RE_TY, $all) > 0)                         $f[] = 'ADDRESSES_TY';
    $we = reCount($RE_WE, $all); $pv = reCount($RE_PASSIVE, $all);
    if ($we > 0 && $pv > 0)                                $f[] = 'VOICE_MIXED';

    // A product named in this text whose own page is not live.
    $dangling = [];
    foreach ($names as $n) {
        if (mb_strlen($n, 'UTF-8') < 12) continue;
        if (mb_strpos(mb_strtolower($all, 'UTF-8'), $n, 0, 'UTF-8') !== false && !in_array($n, $live, true)) {
            $dangling[] = $n;
        }
    }
    if ($dangling !== []) $f[] = 'REFS_OFFLINE_PRODUCT';

    $notes = [];
    if (trim((string) $r['image']) === '') $notes[] = 'NO_IMAGE';
    if ((int) $r['status'] !== 1)          $notes[] = 'HIDDEN';

    $rows[] = [
        'id' => $id, 'sku' => $sku, 'is3d' => $is3d, 'status' => (int) $r['status'],
        'name' => (string) $r['name'], 'slug' => $seo[$id] ?? '',
        'categories' => $cats[$id] ?? [],
        'category_names' => array_values(array_map(function ($c) use ($catName) { return $catName[$c] ?? ('#' . $c); }, $cats[$id] ?? [])),
        'meta_title' => (string) $r['meta_title'], 'mt_len' => mb_strlen((string) $r['meta_title'], 'UTF-8'),
        'meta_description' => (string) $r['meta_description'], 'md_len' => mb_strlen((string) $r['meta_description'], 'UTF-8'),
        'meta_keyword' => (string) $r['meta_keyword'],
        'body_html' => $body, 'body_text' => $bt, 'body_chars' => mb_strlen($bt, 'UTF-8'),
        'faq_html' => $faq, 'faq_text' => $ft, 'faq_items' => substr_count($faq, 'bs-faq-item'),
        'h2' => countTag($body, 'h2'), 'h3' => countTag($body, 'h3'),
        'strong' => countTag($body, 'strong'), 'ul' => countTag($body, 'ul'), 'a' => countTag($body, 'a'),
        'we' => $we, 'passive' => $pv, 'ty' => reCount($RE_TY, $all), 'vy' => reCount($RE_VY, $all),
        'attributes' => $attrs[$id] ?? [],
        'dangling' => $dangling,
        'flags' => $f, 'flag_count' => count($f), 'notes' => $notes,
    ];
}

// 3D first, then by flag count, then by id.
usort($rows, function ($a, $b) {
    if ($a['is3d'] !== $b['is3d']) return $a['is3d'] ? -1 : 1;
    if ($a['flag_count'] !== $b['flag_count']) return $b['flag_count'] <=> $a['flag_count'];
    return $a['id'] <=> $b['id'];
});

$catRows = [];
foreach ($categories as $c) {
    $cid  = (int) $c['category_id'];
    $desc = rawHtml((string) $c['description']);
    $body = bodyOnly($desc); $faq = faqOnly($desc); $bt = plain($body);
    $f = [];
    if ($bt === '')                                        $f[] = 'EMPTY_BODY';
    if (mb_strlen($bt, 'UTF-8') < 400)                     $f[] = 'THIN_BODY';
    if (trim((string) $c['meta_title']) === '')            $f[] = 'NO_META_TITLE';
    if (mb_strlen((string) $c['meta_description'], 'UTF-8') > 155) $f[] = 'MD_TOO_LONG';
    if (countTag($body, 'h2') + countTag($body, 'h3') === 0) $f[] = 'NO_HEADING';
    if ($faq === '')                                       $f[] = 'NO_FAQ';
    if (reCount($RE_PLACE, $bt . ' ' . plain($faq)) > 0)   $f[] = 'PLACEHOLDER';
    $catRows[] = [
        'id' => $cid, 'parent_id' => (int) $c['parent_id'], 'status' => (int) $c['status'],
        'name' => (string) $c['name'], 'slug' => $catSeo[$cid] ?? '',
        'meta_title' => (string) $c['meta_title'], 'meta_description' => (string) $c['meta_description'],
        'meta_keyword' => (string) $c['meta_keyword'],
        'body_html' => $body, 'body_text' => $bt, 'body_chars' => mb_strlen($bt, 'UTF-8'),
        'faq_html' => $faq, 'flags' => $f, 'flag_count' => count($f),
    ];
}

// Duplicate names / titles / slugs across the catalogue.
$dupe = ['name' => [], 'meta_title' => [], 'slug' => []];
foreach (['name', 'meta_title', 'slug'] as $k) {
    $seen = [];
    foreach (array_merge($rows, $catRows) as $r) {
        $v = trim((string) ($r[$k] ?? ''));
        if ($v === '') continue;
        $seen[$v][] = ($r['sku'] ?? ('cat' . $r['id']));
    }
    foreach ($seen as $v => $who) if (count($who) > 1) $dupe[$k][$v] = $who;
}

// ---------------------------------------------------------------- write out
$stamp = date('Ymd-Hi');
$home  = getenv('HOME') ?: dirname(getcwd());
$dir   = $home . '/bs-cards-export-' . $stamp;
if (!@mkdir($dir . '/products', 0700, true) && !is_dir($dir . '/products')) fail('не можу створити теку ' . $dir);
if (!@mkdir($dir . '/categories', 0700, true) && !is_dir($dir . '/categories')) fail('не можу створити теку categories');

$slugify = function (string $s): string {
    $s = preg_replace('~[^A-Za-z0-9_-]+~', '-', $s) ?? $s;
    return trim((string) $s, '-') ?: 'item';
};

$n = 0;
foreach ($rows as $r) {
    $n++;
    $file = sprintf('%s/products/%03d_%s_%d.md', $dir, $n, $slugify($r['sku']), $r['id']);
    $out  = "# {$r['name']}\n\n";
    $out .= "- product_id: {$r['id']}\n- SKU: {$r['sku']}\n- status: " . ($r['status'] ? 'visible' : 'HIDDEN') . "\n";
    $out .= "- slug: {$r['slug']}\n- categories: " . implode(', ', $r['category_names']) . "\n";
    $out .= "- flags: " . ($r['flags'] ? implode(' ', $r['flags']) : '—') . "\n";
    $out .= "- notes: " . ($r['notes'] ? implode(' ', $r['notes']) : '—') . "\n\n";
    $out .= "## Meta\n\nTitle ({$r['mt_len']}): {$r['meta_title']}\nDescription ({$r['md_len']}): {$r['meta_description']}\nKeywords: {$r['meta_keyword']}\n\n";
    $out .= "## Body (HTML)\n\n```html\n{$r['body_html']}\n```\n\n";
    $out .= "## Body (plain)\n\n{$r['body_text']}\n\n";
    $out .= "## FAQ (plain)\n\n{$r['faq_text']}\n\n";
    $out .= "## Attributes\n\n";
    foreach ($r['attributes'] as $k => $v) $out .= "- {$k}: {$v}\n";
    if ($r['dangling']) $out .= "\n## Names mentioned whose page is not live\n\n- " . implode("\n- ", $r['dangling']) . "\n";
    file_put_contents($file, $out);
}
foreach ($catRows as $c) {
    $file = sprintf('%s/categories/%03d_%s.md', $dir, $c['id'], $slugify($c['slug'] ?: $c['name']));
    $out  = "# {$c['name']} (category {$c['id']}, parent {$c['parent_id']}, " . ($c['status'] ? 'enabled' : 'DISABLED') . ")\n\n";
    $out .= "- slug: {$c['slug']}\n- flags: " . ($c['flags'] ? implode(' ', $c['flags']) : '—') . "\n\n";
    $out .= "## Meta\n\nTitle: {$c['meta_title']}\nDescription: {$c['meta_description']}\nKeywords: {$c['meta_keyword']}\n\n";
    $out .= "## Body (HTML)\n\n```html\n{$c['body_html']}\n```\n\n## Body (plain)\n\n{$c['body_text']}\n";
    file_put_contents($file, $out);
}

$tsv = "n\ttype\tid\tsku\t3d\tstatus\tflag_count\tflags\tnotes\tname\tslug\tmt_len\tmd_len\tbody_chars\th2\th3\tstrong\tul\ta\twe\tpassive\tty\n";
$n = 0;
foreach ($rows as $r) {
    $n++;
    $tsv .= implode("\t", [$n, 'product', $r['id'], $r['sku'], $r['is3d'] ? '3D' : '-', $r['status'] ? 'visible' : 'hidden',
        $r['flag_count'], implode(' ', $r['flags']), implode(' ', $r['notes']), $r['name'], $r['slug'], $r['mt_len'], $r['md_len'], $r['body_chars'],
        $r['h2'], $r['h3'], $r['strong'], $r['ul'], $r['a'], $r['we'], $r['passive'], $r['ty']]) . "\n";
}
foreach ($catRows as $c) {
    $tsv .= implode("\t", ['', 'category', $c['id'], '', '-', $c['status'] ? 'enabled' : 'disabled',
        $c['flag_count'], implode(' ', $c['flags']), '', $c['name'], $c['slug'], mb_strlen($c['meta_title'], 'UTF-8'),
        mb_strlen($c['meta_description'], 'UTF-8'), $c['body_chars'], '', '', '', '', '', '', '', '']) . "\n";
}
file_put_contents($dir . '/index.tsv', $tsv);

$problem = array_values(array_filter($rows, function ($r) { return $r['flag_count'] > 0; }));
$plist = "Cards with at least one flag — " . count($problem) . " of " . count($rows) . "\n\n";
foreach ($problem as $r) {
    $plist .= sprintf("%-18s %-3s %-8s %2d  %s\n", $r['sku'], $r['is3d'] ? '3D' : '', $r['status'] ? 'visible' : 'hidden', $r['flag_count'], implode(' ', $r['flags']));
}
$plist .= "\nCategories with at least one flag\n\n";
foreach ($catRows as $c) if ($c['flag_count'] > 0) $plist .= sprintf("cat %-4d %-28s %2d  %s\n", $c['id'], $c['name'], $c['flag_count'], implode(' ', $c['flags']));
$plist .= "\nVoice profile — the split between cards is the finding, not the mix inside one\n\n";
$vp = ['we-only' => [], 'passive-only' => [], 'both' => [], 'neither' => []];
foreach ($rows as $r) {
    $k = $r['we'] > 0 && $r['passive'] > 0 ? 'both' : ($r['we'] > 0 ? 'we-only' : ($r['passive'] > 0 ? 'passive-only' : 'neither'));
    $vp[$k][] = $r['sku'];
}
foreach ($vp as $k => $set) $plist .= sprintf("%-13s %2d  %s\n", $k, count($set), implode(' ', $set));

$plist .= "\nDuplicate values across the catalogue\n\n";
foreach ($dupe as $k => $set) foreach ($set as $v => $who) $plist .= $k . ": «" . $v . "» → " . implode(', ', $who) . "\n";
file_put_contents($dir . '/problem-cards.txt', $plist);

file_put_contents($dir . '/raw.json', json_encode([
    'generated'  => date('c'),
    'language_id'=> $L,
    'db_prefix'  => $p,
    'products'   => $rows,
    'categories' => $catRows,
    'duplicates' => $dupe,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

file_put_contents($dir . '/README.txt',
    "bs-cards-export — read-only snapshot of Booster Shop card and category text\n"
  . "Generated: " . date('c') . "\n"
  . "Products: " . count($rows) . " (flagged: " . count($problem) . ")\n"
  . "Categories: " . count($catRows) . "\n"
  . "Language id: {$L}\n\n"
  . "products/   one file per card, 3D first, then by flag count\n"
  . "categories/ one file per category\n"
  . "index.tsv   one row per item, opens in Excel\n"
  . "problem-cards.txt  short list of everything that tripped a flag\n"
  . "raw.json    full machine-readable dump\n\n"
  . "Flags — mechanical pointers, not verdicts. A human decides.\n"
  . "  KEY_NOT_IN_BODY      head phrase of the Meta Title is absent from the visible description\n"
  . "  NAME_VS_TITLE        product name and Meta Title carry different head phrases\n"
  . "  NO_HEADING           description has no h2/h3 of its own\n"
  . "  NO_EMPHASIS          description has no strong/b\n"
  . "  THIN_BODY            visible description under 400 characters\n"
  . "  NO_FAQ               no FAQ accordion in the description\n"
  . "  MD_TOO_LONG          Meta Description over 155 characters\n"
  . "  MD_TOO_SHORT         Meta Description under 80 characters\n"
  . "  MT_TOO_LONG          Meta Title over 60 characters\n"
  . "  NO_META_TITLE        Meta Title empty\n"
  . "  NO_SEO_URL           no seo_url row for this product\n"
  . "  PLACEHOLDER          text still says «уточнюємо» / TBD / {{\n"
  . "  INTERNAL_VOCAB       production words in customer copy (партія, п'ятірка, SKU, артикул)\n"
  . "  SUPERLATIVE          най-/єдиний — check what the claim is scoped to\n"
  . "  BATCH_SCOPED         superlative tied to the current range; false as soon as the range grows\n"
  . "  ADDRESSES_TY         addresses the reader as «ти» while the shop uses «ви» or impersonal\n"
  . "  VOICE_MIXED          «ми друкуємо» and «друкується» in the same card\n"
  . "  REFS_OFFLINE_PRODUCT names a product whose own page is not visible\n"
  . "  EMPTY_BODY           no description at all\n\n"
  . "Notes are not text problems: NO_IMAGE, HIDDEN.\n\n"
  . "Nothing on the site was written. Every statement is a SELECT.\n");

echo "products: " . count($rows) . " (flagged " . count($problem) . ")\n";
echo "categories: " . count($catRows) . "\n";
echo "dir: " . $dir . "\n";
