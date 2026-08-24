<?php
declare(strict_types=1);

/**
 * OC-FOP-0328 — one-time correction of OpenCart order #328.
 *
 * Authorized scope:
 * - OP-JP-V7PR-BST: 20 × 80.00 -> 12 × 80.00;
 * - line subtotal, order subtotal and grand total: 1945.00 -> 1305.00;
 * - restock exactly 8 units only when the current order status is configured
 *   by OpenCart to have already deducted stock.
 *
 * Preconditions:
 * - owner downloaded a fresh cPanel MySQL backup before execution;
 * - order #328 has the exact live values checked below.
 *
 * Rollback SQL (use only after restoring the MySQL backup if needed):
 * UPDATE `ocp5_order_product` SET `quantity` = 20, `total` = 1600.0000
 * WHERE `order_id` = 328 AND `model` = 'OP-JP-V7PR-BST';
 * UPDATE `ocp5_order_total` SET `value` = `value` + 640.0000
 * WHERE `order_id` = 328 AND `code` IN ('sub_total', 'total');
 * UPDATE `ocp5_order` SET `total` = `total` + 640.0000 WHERE `order_id` = 328;
 * If this runner reports stock_adjusted=yes, also subtract 8 from the matched
 * product's `quantity` after verifying the product id in its JSON backup.
 *
 * This runner does not create an order-history entry or send customer mail.
 */

const PATCH_ID = 'OC-FOP-0328_order-quantity-repair_20260824';
const ORDER_ID = 328;
const TARGET_MODEL = 'OP-JP-V7PR-BST';
const OLD_QTY = 20;
const NEW_QTY = 12;
const UNIT_PRICE = 80.00;
const OLD_LINE_TOTAL = 1600.00;
const OLD_ORDER_TOTAL = 1945.00;
const DELTA_TOTAL = 640.00;

function bs_log(string $key, string $value): void {
    echo $key . '=' . $value . PHP_EOL;
}

function bs_fail(string $message): void {
    fwrite(STDERR, 'error=' . $message . PHP_EOL);
    exit(1);
}

function bs_assert(bool $condition, string $message): void {
    if (!$condition) bs_fail($message);
}

function bs_close(mysqli_stmt $stmt): void {
    $stmt->close();
}

function bs_table(string $suffix): string {
    $table = DB_PREFIX . $suffix;
    bs_assert((bool) preg_match('/^[A-Za-z0-9_]+$/', $table), 'unsafe_table_name=' . $suffix);
    return $table;
}

function bs_table_exists(mysqli $db, string $table): bool {
    $quoted = "'" . $db->real_escape_string($table) . "'";
    $result = $db->query('SHOW TABLES LIKE ' . $quoted);
    if ($result === false) {
        throw new RuntimeException('Cannot inspect table ' . $table . ': ' . $db->error);
    }
    $exists = $result->num_rows === 1;
    $result->free();
    return $exists;
}

function bs_require_columns(mysqli $db, string $table, array $required): void {
    $result = $db->query('SHOW COLUMNS FROM `' . $table . '`');
    $columns = [];
    while ($row = $result->fetch_assoc()) $columns[(string) $row['Field']] = true;
    $result->free();
    foreach ($required as $column) bs_assert(isset($columns[$column]), 'missing_column=' . $table . '.' . $column);
}

function bs_bind(mysqli_stmt $stmt, string $types, array $params): void {
    if ($types === '') return;
    $refs = [$types];
    foreach ($params as $key => &$value) $refs[] = &$params[$key];
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function bs_rows(mysqli $db, string $sql, string $types, array $params): array {
    bs_assert(substr_count($sql, '?') === strlen($types) && strlen($types) === count($params), 'statement_bind_mismatch');
    $stmt = $db->prepare($sql);
    bs_bind($stmt, $types, $params);
    $stmt->execute();
    $metadata = $stmt->result_metadata();
    bs_assert($metadata !== false, 'statement_result_metadata_missing');

    $row = [];
    $refs = [];
    foreach ($metadata->fetch_fields() as $field) {
        $row[$field->name] = null;
        $refs[] = &$row[$field->name];
    }
    call_user_func_array([$stmt, 'bind_result'], $refs);

    $rows = [];
    while ($stmt->fetch()) {
        $copy = [];
        foreach ($row as $name => $value) $copy[$name] = $value;
        $rows[] = $copy;
    }
    $metadata->free();
    bs_close($stmt);
    return $rows;
}

function bs_exec(mysqli $db, string $sql, string $types, array $params): int {
    bs_assert(substr_count($sql, '?') === strlen($types) && strlen($types) === count($params), 'statement_bind_mismatch');
    $stmt = $db->prepare($sql);
    bs_bind($stmt, $types, $params);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    bs_close($stmt);
    return $affected;
}

function bs_close_enough(float $actual, float $expected): bool {
    return abs($actual - $expected) < 0.005;
}

function bs_status_ids(string $raw): array {
    preg_match_all('/\d+/', $raw, $matches);
    return array_values(array_unique(array_map('intval', $matches[0] ?? [])));
}

function bs_read_setting(mysqli $db, string $table, int $storeId, string $key): string {
    $rows = bs_rows(
        $db,
        'SELECT `value` FROM `' . $table . '` WHERE `key` = ? AND `store_id` IN (0, ?) ORDER BY `store_id` ASC',
        'si',
        [$key, $storeId]
    );
    if ($rows === []) return '';
    $last = end($rows);
    return (string) $last['value'];
}

function bs_backup_dir(): string {
    $stamp = gmdate('Ymd-His');
    $dir = __DIR__ . '/_patch_backups/' . PATCH_ID . '-' . $stamp;
    bs_assert(@mkdir($dir, 0775, true) || is_dir($dir), 'cannot_create_backup_dir');
    return $dir;
}

function bs_write_backup(string $dir, array $snapshot): void {
    $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    bs_assert($json !== false, 'cannot_encode_backup_json');
    bs_assert(file_put_contents($dir . '/db-before.json', $json . PHP_EOL, LOCK_EX) !== false, 'cannot_write_backup_json');
}

function bs_connect(): mysqli {
    bs_assert(extension_loaded('mysqli'), 'mysqli_extension_missing');
    foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PREFIX'] as $constant) {
        bs_assert(defined($constant), 'missing_config_constant=' . $constant);
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, defined('DB_PORT') ? (int) DB_PORT : 3306);
    $db->set_charset('utf8mb4');
    return $db;
}

function main(): void {
    bs_log('patch', PATCH_ID);
    bs_log('cwd', getcwd() ?: 'unknown');
    bs_log('time_utc', gmdate('c'));
    bs_assert(is_file(__DIR__ . '/config.php'), 'run_from_public_html_required');
    require_once __DIR__ . '/config.php';

    $db = bs_connect();
    $orderTable = bs_table('order');
    $orderProductTable = bs_table('order_product');
    $orderTotalTable = bs_table('order_total');
    $productTable = bs_table('product');
    $settingTable = bs_table('setting');
    $orderOptionTable = bs_table('order_option');

    foreach ([$orderTable, $orderProductTable, $orderTotalTable, $productTable, $settingTable, $orderOptionTable] as $table) {
        bs_assert(bs_table_exists($db, $table), 'missing_table=' . $table);
    }
    bs_require_columns($db, $orderTable, ['order_id', 'store_id', 'order_status_id', 'total', 'date_modified']);
    bs_require_columns($db, $orderProductTable, ['order_product_id', 'order_id', 'product_id', 'model', 'quantity', 'price', 'total']);
    bs_require_columns($db, $orderTotalTable, ['order_id', 'code', 'value', 'sort_order']);
    bs_require_columns($db, $productTable, ['product_id', 'quantity', 'subtract']);
    bs_require_columns($db, $settingTable, ['store_id', 'key', 'value']);
    bs_require_columns($db, $orderOptionTable, ['order_product_id']);
    bs_log('schema_preflight', 'ok');

    $orderRows = bs_rows($db, 'SELECT `order_id`, `store_id`, `order_status_id`, `total` FROM `' . $orderTable . '` WHERE `order_id` = ?', 'i', [ORDER_ID]);
    bs_assert(count($orderRows) === 1, 'expected_exactly_one_order');
    $order = $orderRows[0];
    bs_assert(bs_close_enough((float) $order['total'], OLD_ORDER_TOTAL), 'unexpected_order_total=' . $order['total']);

    $targetRows = bs_rows(
        $db,
        'SELECT `order_product_id`, `product_id`, `model`, `quantity`, `price`, `total` FROM `' . $orderProductTable . '` WHERE `order_id` = ? AND `model` = ?',
        'is',
        [ORDER_ID, TARGET_MODEL]
    );
    bs_assert(count($targetRows) === 1, 'expected_exactly_one_target_order_product');
    $target = $targetRows[0];
    bs_assert((int) $target['quantity'] === OLD_QTY, 'unexpected_target_quantity=' . $target['quantity']);
    bs_assert(bs_close_enough((float) $target['price'], UNIT_PRICE), 'unexpected_target_price=' . $target['price']);
    bs_assert(bs_close_enough((float) $target['total'], OLD_LINE_TOTAL), 'unexpected_target_total=' . $target['total']);

    $optionRows = bs_rows($db, 'SELECT `order_product_id` FROM `' . $orderOptionTable . '` WHERE `order_product_id` = ?', 'i', [(int) $target['order_product_id']]);
    bs_assert($optionRows === [], 'target_has_order_options_manual_review_required');

    $totalRows = bs_rows($db, 'SELECT `code`, `value` FROM `' . $orderTotalTable . '` WHERE `order_id` = ? ORDER BY `sort_order`, `code`', 'i', [ORDER_ID]);
    $totalByCode = [];
    foreach ($totalRows as $row) {
        $code = (string) $row['code'];
        bs_assert(!isset($totalByCode[$code]), 'duplicate_order_total_code=' . $code);
        $totalByCode[$code] = (float) $row['value'];
    }
    bs_assert(isset($totalByCode['sub_total'], $totalByCode['total']), 'required_order_total_rows_missing');
    bs_assert(bs_close_enough($totalByCode['sub_total'], OLD_ORDER_TOTAL), 'unexpected_sub_total=' . $totalByCode['sub_total']);
    bs_assert(bs_close_enough($totalByCode['total'], OLD_ORDER_TOTAL), 'unexpected_grand_total=' . $totalByCode['total']);
    foreach ($totalByCode as $code => $value) {
        if ($code === 'sub_total' || $code === 'total') continue;
        bs_assert(bs_close_enough($value, 0.00), 'nonzero_extra_order_total=' . $code . ':' . $value);
    }

    $processingRaw = bs_read_setting($db, $settingTable, (int) $order['store_id'], 'config_processing_status');
    $completeRaw = bs_read_setting($db, $settingTable, (int) $order['store_id'], 'config_complete_status');
    $stockStatuses = array_values(array_unique(array_merge(bs_status_ids($processingRaw), bs_status_ids($completeRaw))));
    bs_assert($stockStatuses !== [], 'cannot_resolve_stock_deduct_statuses');
    $stockDeducted = in_array((int) $order['order_status_id'], $stockStatuses, true);

    $productRows = bs_rows($db, 'SELECT `product_id`, `quantity`, `subtract` FROM `' . $productTable . '` WHERE `product_id` = ?', 'i', [(int) $target['product_id']]);
    bs_assert(count($productRows) === 1, 'target_catalog_product_missing');
    $catalogProduct = $productRows[0];
    $shouldRestock = $stockDeducted && (int) $catalogProduct['subtract'] === 1;

    $backupDir = bs_backup_dir();
    bs_write_backup($backupDir, [
        'patch' => PATCH_ID,
        'time_utc' => gmdate('c'),
        'order' => $order,
        'target_order_product' => $target,
        'order_totals' => $totalRows,
        'catalog_product' => $catalogProduct,
        'stock_status_settings' => ['processing' => $processingRaw, 'complete' => $completeRaw, 'resolved_ids' => $stockStatuses],
        'will_restock_units' => $shouldRestock ? OLD_QTY - NEW_QTY : 0
    ]);
    bs_log('backup_dir', $backupDir);

    $newLineTotal = round(UNIT_PRICE * NEW_QTY, 4);
    $newOrderTotal = round(OLD_ORDER_TOTAL - DELTA_TOTAL, 4);
    $restockQty = OLD_QTY - NEW_QTY;
    $db->begin_transaction();
    try {
        bs_assert(bs_exec($db, 'UPDATE `' . $orderProductTable . '` SET `quantity` = ?, `total` = ? WHERE `order_product_id` = ? AND `quantity` = ? AND `total` = ?', 'idiid', [NEW_QTY, $newLineTotal, (int) $target['order_product_id'], OLD_QTY, OLD_LINE_TOTAL]) === 1, 'target_order_product_write_mismatch');
        bs_assert(bs_exec($db, 'UPDATE `' . $orderTotalTable . '` SET `value` = `value` - ? WHERE `order_id` = ? AND `code` IN (\'sub_total\', \'total\')', 'di', [DELTA_TOTAL, ORDER_ID]) === 2, 'order_total_write_mismatch');
        bs_assert(bs_exec($db, 'UPDATE `' . $orderTable . '` SET `total` = `total` - ?, `date_modified` = NOW() WHERE `order_id` = ? AND `total` = ?', 'did', [DELTA_TOTAL, ORDER_ID, OLD_ORDER_TOTAL]) === 1, 'order_total_header_write_mismatch');
        if ($shouldRestock) {
            bs_assert(bs_exec($db, 'UPDATE `' . $productTable . '` SET `quantity` = `quantity` + ? WHERE `product_id` = ? AND `subtract` = 1', 'ii', [$restockQty, (int) $target['product_id']]) === 1, 'catalog_stock_write_mismatch');
        }

        $afterTarget = bs_rows($db, 'SELECT `quantity`, `total` FROM `' . $orderProductTable . '` WHERE `order_product_id` = ?', 'i', [(int) $target['order_product_id']]);
        $afterOrder = bs_rows($db, 'SELECT `total` FROM `' . $orderTable . '` WHERE `order_id` = ?', 'i', [ORDER_ID]);
        $afterTotals = bs_rows($db, 'SELECT `code`, `value` FROM `' . $orderTotalTable . '` WHERE `order_id` = ? AND `code` IN (\'sub_total\', \'total\') ORDER BY `code`', 'i', [ORDER_ID]);
        bs_assert(count($afterTarget) === 1 && (int) $afterTarget[0]['quantity'] === NEW_QTY && bs_close_enough((float) $afterTarget[0]['total'], $newLineTotal), 'target_order_product_readback_failed');
        bs_assert(count($afterOrder) === 1 && bs_close_enough((float) $afterOrder[0]['total'], $newOrderTotal), 'order_header_readback_failed');
        bs_assert(count($afterTotals) === 2 && bs_close_enough((float) $afterTotals[0]['value'], $newOrderTotal) && bs_close_enough((float) $afterTotals[1]['value'], $newOrderTotal), 'order_totals_readback_failed');
        $db->commit();
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    } finally {
        $db->close();
    }

    bs_log('order_id', (string) ORDER_ID);
    bs_log('model', TARGET_MODEL);
    bs_log('quantity', OLD_QTY . '->' . NEW_QTY);
    bs_log('order_total', number_format(OLD_ORDER_TOTAL, 2, '.', '') . '->' . number_format($newOrderTotal, 2, '.', ''));
    bs_log('stock_adjusted', $shouldRestock ? 'yes:+' . $restockQty : 'no');
    bs_log('done', 'ok');
    @unlink(__FILE__);
}

try {
    main();
} catch (Throwable $error) {
    bs_fail($error->getMessage());
}
