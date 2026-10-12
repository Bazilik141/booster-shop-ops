<?php
/**
 * RD-25 WP2+WP3 Phase 0 intake. PHP 8.0 compatible, CLI only.
 * Run from public_html; argument 1 is an owner-created empty export directory
 * outside the web root. SELECT-only DB access; no site/cache/image/DB writes.
 * Writes only phase0.json and files.txt in that export directory.
 * No config.php, credentials, orders, customers or full DB dump are exported.
 * Intake revision 3 (2026-10-12): allow '..' inside filenames, while rejecting
 * parent-directory segments. Resolve DB image HTML entities exactly like the
 * existing resizer; retain raw DB paths and explicit resolution evidence.
 * Errors expose only safe stage/reason tags.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function rd25Rows(mysqli $db, string $sql, array $params = []): array {
    $stmt = $db->prepare($sql);
    if ($params) { $types = str_repeat('s', count($params)); $stmt->bind_param($types, ...$params); }
    $stmt->execute(); $meta = $stmt->result_metadata();
    if (!$meta) { throw new RuntimeException('Expected SELECT result'); }
    $row = []; $bindings = [];
    foreach ($meta->fetch_fields() as $field) { $row[$field->name] = null; $bindings[] = &$row[$field->name]; }
    $stmt->bind_result(...$bindings); $rows = [];
    while ($stmt->fetch()) { $rows[] = array_map(static function ($value) { return $value; }, $row); }
    $stmt->close(); return $rows;
}
function rd25Columns(mysqli $db, string $suffix): array {
    return array_column(rd25Rows($db, 'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [DB_DATABASE, DB_PREFIX . $suffix]), 'COLUMN_NAME');
}
function rd25Dimensions($value, string $path = ''): array {
    $out = [];
    if (is_array($value)) {
        foreach ($value as $key => $child) { $out += rd25Dimensions($child, $path === '' ? (string)$key : $path . '.' . $key); }
    } elseif (preg_match('/(?:image|img|thumb|thumbnail).*(?:width|height)|(?:width|height).*(?:image|img|thumb|thumbnail)|live.*search.*(?:width|height)/i', $path)
        && is_scalar($value) && preg_match('/\A[0-9]{1,5}\z/', (string)$value)) { $out[$path] = (int)$value; }
    return $out;
}

$rd25Stage = 'export_directory'; $rd25Relative = null;
try {
    $root = realpath(getcwd()); $out = realpath($argv[1] ?? '');
    if (!$root || !$out || $out === $root || strpos($out . '/', $root . '/') === 0 || !is_dir($out) || !is_writable($out)) {
        throw new RuntimeException('Export directory must be outside public_html');
    }
    foreach (['phase0.json', 'files.txt'] as $name) { if (file_exists($out . '/' . $name)) { throw new RuntimeException('Export output already exists'); } }
    $rd25Stage = 'load_config';
    require $root . '/config.php';
    if (!preg_match('/\A[A-Za-z0-9_]+\z/', DB_PREFIX)) { throw new RuntimeException('Invalid prefix'); }
    $rd25Stage = 'db_connect';
    $db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
    $db->set_charset('utf8mb4'); $db->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
    $rd25Stage = 'db_read';
    $p = DB_PREFIX;
    $products = rd25Rows($db, 'SELECT p.product_id, p.model, p.image, p.date_available FROM `' . $p . 'product` p INNER JOIN `' . $p . 'product_to_store` s ON s.product_id = p.product_id WHERE s.store_id = 0 AND p.status = 1 ORDER BY p.product_id');
    $extra = rd25Rows($db, 'SELECT i.product_id, i.image, i.sort_order FROM `' . $p . 'product_image` i WHERE EXISTS (SELECT 1 FROM `' . $p . 'product` p INNER JOIN `' . $p . 'product_to_store` s ON s.product_id = p.product_id WHERE p.product_id = i.product_id AND s.store_id = 0 AND p.status = 1) ORDER BY i.product_id, i.sort_order, i.image');
    $names = rd25Rows($db, 'SELECT d.product_id, d.language_id, d.name FROM `' . $p . 'product_description` d WHERE EXISTS (SELECT 1 FROM `' . $p . 'product` p INNER JOIN `' . $p . 'product_to_store` s ON s.product_id = p.product_id WHERE p.product_id = d.product_id AND s.store_id = 0 AND p.status = 1) ORDER BY d.product_id, d.language_id');
    $themeCols = rd25Columns($db, 'theme'); $overrides = []; $themeStatus = 'not_available';
    if (in_array('route', $themeCols, true) && in_array('code', $themeCols, true)) {
        $selected = array_values(array_intersect(['theme_id', 'store_id', 'route', 'code', 'date_added'], $themeCols));
        $projection = implode(', ', array_map(static function ($x) { return '`' . $x . '`'; }, $selected));
        $overrides = rd25Rows($db, 'SELECT ' . $projection . ' FROM `' . $p . 'theme` WHERE route IN (\'product/product\', \'product/thumb\', \'common/cart\', \'checkout/cart\', \'checkout/cart_list\', \'checkout/checkout\', \'checkout/confirm\') OR route LIKE \'checkout/%\'');
        $themeStatus = 'checked';
    }
    $settings = rd25Rows($db, 'SELECT store_id, code, `key`, value FROM `' . $p . 'setting` WHERE store_id = 0 AND (`key` IN (\'config_theme\', \'config_language_id\') OR (`key` REGEXP \'(image|thumb).*(width|height)|(width|height).*(image|thumb)\') OR code LIKE \'%live%search%\' OR `key` LIKE \'%live%search%\') ORDER BY code, `key`');
    $safeSettings = [];
    foreach ($settings as $item) {
        $safe = ['store_id' => (int)$item['store_id'], 'code' => $item['code'], 'key' => $item['key']];
        if ($item['key'] === 'config_language_id' && ctype_digit((string)$item['value'])) { $safe['value'] = (int)$item['value']; }
        elseif ($item['key'] === 'config_theme' && preg_match('/\A[A-Za-z0-9_\/-]{1,100}\z/', (string)$item['value'])) { $safe['value'] = $item['value']; }
        else {
            $decoded = json_decode((string)$item['value'], true);
            $safe['dimensions'] = rd25Dimensions(is_array($decoded) ? $decoded : $item['value'], $item['code'] . '.' . $item['key']);
            $safe['non_dimension_values_omitted'] = true;
        }
        $safeSettings[] = $safe;
    }
    $modules = []; $moduleCols = rd25Columns($db, 'module');
    if (!array_diff(['module_id', 'code', 'setting'], $moduleCols)) {
        foreach (rd25Rows($db, 'SELECT module_id, code, setting FROM `' . $p . 'module` WHERE code LIKE \'%live%search%\' ORDER BY module_id') as $m) {
            $modules[] = ['module_id' => (int)$m['module_id'], 'code' => $m['code'], 'dimensions' => rd25Dimensions(json_decode((string)$m['setting'], true), $m['code']), 'non_dimension_values_omitted' => true];
        }
    }
    $db->rollback(); $db->close();

    $rd25Stage = 'collect_source_files';
    $files = []; $missing = []; $hashes = [];
    $add = static function (string $rel, bool $required = true) use ($root, &$files, &$missing, &$hashes, &$rd25Relative): void {
        $rd25Relative = substr($rel, 0, 300);
        if (preg_match('/[\x00\r\n]/', $rel) || $rel === '' || $rel[0] === '/' || preg_match('~(?:\A|/)\.\.(?:/|\z)~', str_replace('\\', '/', $rel))) { throw new RuntimeException('Unsafe export path'); }
        $full = realpath($root . '/' . $rel);
        if (!$full || !is_file($full)) { if ($required) { $missing[] = $rel; } return; }
        if (strpos($full, $root . '/') !== 0) { throw new RuntimeException('Export path escapes public_html'); }
        if (!isset($files[$rel])) { $files[$rel] = true; $hashes[$rel] = ['bytes' => filesize($full), 'sha256' => hash_file('sha256', $full)]; }
    };
    foreach (['catalog/controller/product/product.php', 'catalog/view/template/product/product.twig', 'catalog/model/tool/image.php', 'system/library/image.php', 'catalog/controller/product/thumb.php', 'catalog/view/template/product/thumb.twig', 'catalog/view/stylesheet/boostershop-ds.css', 'catalog/view/template/common/header.twig', 'catalog/controller/common/cart.php', 'catalog/view/template/common/cart.twig', 'catalog/controller/checkout/cart.php', 'catalog/view/template/checkout/cart.twig'] as $rel) { $add($rel); }
    foreach (['catalog/controller/common/header.php', 'catalog/controller/common/footer.php', 'catalog/model/catalog/product.php', 'system/library/cart/cart.php'] as $rel) { $add($rel, false); }
    foreach (['catalog/controller/checkout', 'catalog/view/template/checkout', 'catalog/view/template/product', 'catalog/view/template/common', 'catalog/view/javascript', 'catalog/view/stylesheet', 'extension/ps_live_search'] as $dir) {
        if (!is_dir($root . '/' . $dir)) { continue; }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && preg_match('/\.(?:php|twig|js|css)\z/i', $file->getFilename())) { $add(substr($file->getPathname(), strlen($root) + 1), false); }
        }
    }
    $rd25Stage = 'collect_product_images';
    $imagePaths = [];
    foreach (array_merge($products, $extra) as $item) { if ((string)$item['image'] !== '') { $imagePaths[(string)$item['image']] = true; } }
    $imageResolution = [];
    foreach (array_keys($imagePaths) as $image) {
        $resolved = html_entity_decode($image, ENT_QUOTES, 'UTF-8');
        if ($resolved !== $image) { $imageResolution[] = ['db_image' => $image, 'source_image' => $resolved, 'method' => 'html_entity_decode_ENT_QUOTES_UTF8']; }
        $add('image/' . $resolved);
    }
    ksort($files); ksort($hashes);
    $rd25Stage = 'write_export'; $rd25Relative = null;
    $data = ['task' => 'RD-25-WP2-WP3', 'export_revision' => 3, 'utc' => gmdate('c'), 'php_version' => PHP_VERSION, 'db_writes' => 0, 'site_writes' => 0,
        'scope' => 'enabled store-0 products, including future date_available; primary + additional images; no customer/order data',
        'product_count' => count($products), 'products' => $products, 'additional_images' => $extra, 'product_names' => $names,
        'theme_override_check' => ['status' => $themeStatus, 'columns' => $themeCols, 'rows' => $overrides],
        'store0_image_and_live_search_settings' => $safeSettings, 'live_search_modules' => $modules,
        'image_path_resolution' => $imageResolution, 'file_hashes' => $hashes, 'missing_files' => $missing, 'done' => $missing ? 'incomplete' : 'ok'];
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if (file_put_contents($out . '/phase0.json', $encoded) === false || file_put_contents($out . '/files.txt', implode("\n", array_keys($files)) . "\n") === false) { throw new RuntimeException('Export write failed'); }
    echo json_encode(['task' => 'RD-25-WP2-WP3', 'export_revision' => 3, 'products' => count($products), 'files' => count($files), 'missing_files' => $missing, 'db_writes' => 0, 'site_writes' => 0, 'done' => $data['done']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    exit($missing ? 2 : 0);
} catch (Throwable $error) {
    $safeReasons = ['Export directory must be outside public_html', 'Export output already exists', 'Invalid prefix', 'Expected SELECT result', 'Unsafe export path', 'Export path escapes public_html', 'Export write failed'];
    $reason = in_array($error->getMessage(), $safeReasons, true) ? $error->getMessage() : 'unexpected_failure';
    echo json_encode(['task' => 'RD-25-WP2-WP3', 'export_revision' => 3, 'done' => 'error', 'stage' => $rd25Stage, 'reason' => $reason, 'relative_file' => $rd25Relative, 'error_kind' => get_class($error), 'error_code' => (int)$error->getCode(), 'db_writes' => 0, 'site_writes' => 0], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
    exit(1);
}
