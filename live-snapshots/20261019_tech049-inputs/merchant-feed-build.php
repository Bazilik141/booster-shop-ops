<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

const PREORDER_FALLBACK_DAYS = 30;
const DEFAULT_PREORDER_STOCK_STATUS_ID = 8;
const FEED_BACKUP_KEEP = 3;

$root = __DIR__ . '/public_html';
$target = $root . '/merchant-feed.tsv';
$stamp = date('Ymd-His');
$backup = $target . '.bak-' . $stamp;
$tmp = $target . '.tmp-' . $stamp;

function feed_fail(string $message, ?string $tmp = null): void {
    if ($tmp && is_file($tmp)) {
        @unlink($tmp);
    }
    echo 'error=' . $message . PHP_EOL;
    exit(1);
}

function prune_feed_backups(string $target, int $keep): array {
    $prefix = basename($target) . '.bak-';
    $candidates = glob($target . '.bak-*') ?: [];
    $backups = [];

    foreach ($candidates as $path) {
        if (!is_file($path)) {
            continue;
        }
        $name = basename($path);
        if (preg_match('/^' . preg_quote($prefix, '/') . '\d{8}-\d{6}$/', $name)) {
            $backups[] = $path;
        }
    }

    rsort($backups, SORT_STRING);
    $removed = 0;
    $failed = [];
    foreach (array_slice($backups, max(0, $keep)) as $path) {
        if (@unlink($path)) {
            $removed++;
        } else {
            $failed[] = basename($path);
        }
    }

    return [
        'kept' => min(count($backups), max(0, $keep)),
        'removed' => $removed,
        'failed' => $failed,
    ];
}
function clean_text($text, int $limit = 500): string {
    $text = html_entity_decode(strip_tags((string)$text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[\t\r\n]+/u', ' ', $text) ?? $text;
    $text = preg_replace('/\s+/u', ' ', trim($text)) ?? $text;

    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $limit, 'UTF-8');
    }

    return substr($text, 0, $limit);
}

function has_text($text, string $needle): bool {
    if (function_exists('mb_stripos')) {
        return mb_stripos((string)$text, $needle, 0, 'UTF-8') !== false;
    }

    return stripos((string)$text, $needle) !== false;
}

function money_uah($value): string {
    return number_format((float)$value, 2, '.', '') . ' UAH';
}

function encode_path(string $path): string {
    $path = html_entity_decode(trim($path, '/'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return implode('/', array_map('rawurlencode', explode('/', $path)));
}

function image_url($path, string $base): string {
    return $base . 'image/' . encode_path((string)$path);
}

function product_link(array $row, string $base): string {
    return $base . 'index.php?route=product/product&product_id=' . (int)$row['product_id'];
}

function brand_for(array $row): string {
    $manufacturer = clean_text($row['manufacturer'] ?? '', 80);
    $manufacturer = str_replace(['The PokГ©mon Company', 'The Pokemon Company International'], 'The Pokemon Company', $manufacturer);

    if ($manufacturer !== '') {
        return $manufacturer;
    }

    $model = (string)($row['model'] ?? '');
    $haystack = $model . ' ' . ($row['name'] ?? '');

    if (preg_match('/^OP[-_]/i', $model) || has_text($haystack, 'One Piece')) {
        return 'Bandai';
    }

    if (preg_match('/^MTG[-_]/i', $model) || has_text($haystack, 'Magic')) {
        return 'Wizards of the Coast';
    }

    if (preg_match('/^YGO[-_]/i', $model) || has_text($haystack, 'Yu-Gi-Oh')) {
        return 'Konami';
    }

    return 'The Pokemon Company';
}

function product_family(array $row): string {
    $model = (string)($row['model'] ?? '');
    $haystack = $model . ' ' . ($row['name'] ?? '');

    if (preg_match('/^OP[-_]/i', $model) || has_text($haystack, 'One Piece')) {
        return 'One Piece Card Game';
    }

    if (preg_match('/^MTG[-_]/i', $model) || has_text($haystack, 'Magic')) {
        return 'Magic: The Gathering';
    }

    if (preg_match('/^YGO[-_]/i', $model) || has_text($haystack, 'Yu-Gi-Oh')) {
        return 'Yu-Gi-Oh! OCG';
    }

    return 'Pokemon TCG';
}

function product_type_for(array $row): string {
    $family = product_family($row);
    $model = (string)($row['model'] ?? '');
    $haystack = $model . ' ' . ($row['name'] ?? '');

    if (preg_match('/[-_](MBX|MIX)$/i', $model) || has_text($haystack, 'Mystery')) {
        return $family . ' > Mystery Box';
    }

    if (preg_match('/[-_](BLR|SET)$/i', $model)) {
        return $family . ' > Special Set';
    }

    if (preg_match('/[-_](BBX|BOX)$/i', $model) || has_text($haystack, 'box')) {
        return $family . ' > Booster Box';
    }

    if (preg_match('/[-_](PR|PROMO)$/i', $model) || has_text($haystack, 'Promotion Pack') || has_text($haystack, 'Promo')) {
        return $family . ' > Promo Pack';
    }

    if (has_text($haystack, 'low pull') || preg_match('/[-_]OUTL[-_]/i', $model)) {
        return $family . ' > Booster Pack > Outlet Mix';
    }

    return $family . ' > Booster Pack';
}

function description_for(array $row): string {
    $meta = clean_text($row['meta_description'] ?? '', 500);
    if ($meta !== '') {
        return $meta;
    }

    $description = clean_text($row['description'] ?? '', 500);
    if ($description !== '') {
        return $description;
    }

    $type = product_type_for($row);

    if (strpos($type, 'One Piece') === 0) {
        return 'Original sealed One Piece Card Game product by Bandai. Suitable for collecting, opening and sealed collection.';
    }

    if (strpos($type, 'Yu-Gi-Oh') === 0) {
        return 'Original sealed Yu-Gi-Oh! OCG product by Konami. Suitable for collecting, opening and sealed collection.';
    }

    if (strpos($type, 'Booster Box') !== false) {
        return 'Original sealed Pokemon TCG booster box. Suitable for box opening, collecting and sealed collection.';
    }

    return 'Original sealed Pokemon TCG booster pack. Suitable for collecting, opening and sealed collection.';
}

function is_preorder_status(array $row, array $preorder_ids): bool {
    return in_array((int)$row['stock_status_id'], $preorder_ids, true);
}

function availability_for(array $row, array $preorder_ids): string {
    if ((int)$row['quantity'] > 0) {
        return 'in_stock';
    }

    if (is_preorder_status($row, $preorder_ids)) {
        return 'preorder';
    }

    return 'out_of_stock';
}

function availability_date_for(array $row, array $preorder_ids, int &$fallbacks): string {
    if (availability_for($row, $preorder_ids) !== 'preorder') {
        return '';
    }

    $tz = new DateTimeZone('Europe/Kyiv');
    $today = new DateTimeImmutable('today', $tz);
    $date = null;
    $raw = trim((string)($row['date_available'] ?? ''));

    if ($raw !== '' && $raw !== '0000-00-00') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($raw, 0, 10), $tz) ?: null;
    }

    if (!$date || $date <= $today) {
        $date = $today->modify('+' . PREORDER_FALLBACK_DAYS . ' days');
        $fallbacks++;
    }

    return $date->format('Y-m-d\T00:00P');
}

function detect_preorder_ids(mysqli $db, string $prefix, int $lang_id): array {
    $ids = [];
    $sql = "SELECT stock_status_id, name FROM `{$prefix}stock_status` WHERE language_id = {$lang_id}";
    $res = $db->query($sql);

    while ($row = $res->fetch_assoc()) {
        $name = (string)($row['name'] ?? '');
        if (has_text($name, 'preorder') || has_text($name, 'pre-order') || has_text($name, 'перед')) {
            $ids[] = (int)$row['stock_status_id'];
        }
    }

    $check = $db->query("SELECT stock_status_id FROM `{$prefix}stock_status` WHERE stock_status_id = " . DEFAULT_PREORDER_STOCK_STATUS_ID . " AND language_id = {$lang_id} LIMIT 1");
    if ($check->fetch_assoc()) {
        $ids[] = DEFAULT_PREORDER_STOCK_STATUS_ID;
    }

    $ids = array_values(array_unique(array_filter($ids, static fn($id) => $id > 0)));
    if (!$ids) {
        feed_fail('preorder stock status not found for language_id ' . $lang_id, $GLOBALS['tmp'] ?? null);
    }

    return $ids;
}

if (!is_file($root . '/config.php')) {
    feed_fail('config.php not found; run from hosting home with public_html/config.php available', $tmp);
}

if (!is_file($target) || !is_readable($target) || !is_writable($target)) {
    feed_fail('merchant-feed.tsv is missing or not readable/writable', $tmp);
}

require $root . '/config.php';

foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PREFIX'] as $constant) {
    if (!defined($constant)) {
        feed_fail($constant . ' is not defined in config.php', $tmp);
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $port = defined('DB_PORT') ? (int)DB_PORT : 3306;
    $db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, $port);
    $db->set_charset('utf8mb4');
} catch (Throwable $e) {
    feed_fail('DB connection failed: ' . $e->getMessage(), $tmp);
}

$prefix = DB_PREFIX;
$base = defined('HTTPS_SERVER') ? HTTPS_SERVER : (defined('HTTP_SERVER') ? HTTP_SERVER : 'https://boostershop.website/');
$base = rtrim((string)$base, '/') . '/';
if (stripos($base, 'http://') !== 0 && stripos($base, 'https://') !== 0) {
    $base = 'https://boostershop.website/';
}

$lang_id = 4;
$lang_res = $db->query("SELECT language_id FROM `{$prefix}language` WHERE code = 'uk-ua' AND status = 1 LIMIT 1");
if ($lang_row = $lang_res->fetch_assoc()) {
    $lang_id = (int)$lang_row['language_id'];
}

$preorder_ids = detect_preorder_ids($db, $prefix, $lang_id);
$preorder_sql = implode(',', array_map('intval', $preorder_ids));

$sql = "
SELECT
  p.product_id,
  p.model,
  p.image,
  p.quantity,
  p.stock_status_id,
  p.price,
  p.date_available,
  pd.name,
  pd.description,
  pd.meta_description,
  COALESCE(m.name, '') AS manufacturer
FROM `{$prefix}product` p
JOIN `{$prefix}product_description` pd
  ON pd.product_id = p.product_id AND pd.language_id = {$lang_id}
JOIN `{$prefix}product_to_store` p2s
  ON p2s.product_id = p.product_id AND p2s.store_id = 0
LEFT JOIN `{$prefix}manufacturer` m
  ON m.manufacturer_id = p.manufacturer_id
WHERE p.status = 1
  AND p.price > 0
  AND p.image <> ''
  AND (p.quantity > 0 OR p.stock_status_id IN ({$preorder_sql}))
ORDER BY p.product_id DESC
";

$headers = [
    'id',
    'title',
    'description',
    'link',
    'image_link',
    'availability',
    'availability_date',
    'price',
    'condition',
    'brand',
    'identifier_exists',
    'adult',
    'product_type'
];

$out = fopen($tmp, 'wb');
if (!$out) {
    feed_fail('failed to open temp feed for writing', $tmp);
}

fputcsv($out, $headers, "\t");

$rows = 0;
$in_stock = 0;
$preorder = 0;
$fallbacks = 0;
$seen = [];
$duplicates = [];
$result = $db->query($sql);

while ($row = $result->fetch_assoc()) {
    $id = clean_text($row['model'] !== '' ? $row['model'] : (string)$row['product_id'], 80);
    if ($id === '') {
        feed_fail('empty id for product_id ' . (int)$row['product_id'], $tmp);
    }

    if (isset($seen[$id])) {
        $duplicates[] = $id;
        continue;
    }
    $seen[$id] = true;

    $title = clean_text($row['name'], 150);
    $description = description_for($row);
    $link = product_link($row, $base);
    $image = image_url($row['image'], $base);
    $availability = availability_for($row, $preorder_ids);
    $availability_date = availability_date_for($row, $preorder_ids, $fallbacks);

    if ($title === '' || $description === '' || $link === '' || $image === '') {
        feed_fail('required field is empty for product_id ' . (int)$row['product_id'], $tmp);
    }

    if ($availability === 'in_stock') {
        $in_stock++;
    } elseif ($availability === 'preorder') {
        $preorder++;
    } else {
        feed_fail('unexpected out_of_stock row selected for product_id ' . (int)$row['product_id'], $tmp);
    }

    fputcsv($out, [
        $id,
        $title,
        $description,
        $link,
        $image,
        $availability,
        $availability_date,
        money_uah($row['price']),
        'new',
        brand_for($row),
        'no',
        'no',
        product_type_for($row),
    ], "\t");

    $rows++;
}

fclose($out);

if ($duplicates) {
    feed_fail('duplicate product ids in feed source: ' . implode(', ', array_slice($duplicates, 0, 10)), $tmp);
}

if ($rows < 1) {
    feed_fail('no eligible products selected; refusing to replace feed', $tmp);
}

if (!copy($target, $backup)) {
    feed_fail('failed to back up merchant-feed.tsv before replace', $tmp);
}

if (!rename($tmp, $target)) {
    feed_fail('failed to replace merchant-feed.tsv', $tmp);
}

$backup_prune = prune_feed_backups($target, FEED_BACKUP_KEEP);
echo 'backups_kept=' . $backup_prune['kept'] . PHP_EOL;
echo 'backups_removed=' . $backup_prune['removed'] . PHP_EOL;
if ($backup_prune['failed']) {
    echo 'backup_prune_delete_failed=' . implode(',', $backup_prune['failed']) . PHP_EOL;
}

echo 'rows_written=' . $rows . PHP_EOL;
echo 'in_stock=' . $in_stock . PHP_EOL;
echo 'preorder=' . $preorder . PHP_EOL;
echo 'preorder_date_fallbacks=' . $fallbacks . PHP_EOL;
echo 'preorder_stock_status_ids=' . implode(',', $preorder_ids) . PHP_EOL;
echo 'link_mode=route_by_product_id' . PHP_EOL;
echo 'done=ok' . PHP_EOL;