<?php
/**
 * BUG-003 WP3 — stock inflation estimate (READ-ONLY).
 *
 * Upload to ~/public_html and run with PHP CLI:  php BUG-003_stock-inflation-report_20261002.php
 * Can run before or after WP1/WP2; it reads the live status lists only to print them.
 *
 * Writes nothing to the database and nothing inside public_html. Its only output is a CSV in
 * ../bs-reports/ (outside the web root; path printed to stdout) plus a stdout summary.
 * Self-deletes after success. Re-running produces a new timestamped CSV; there is nothing to roll back.
 *
 * Method: for every order, replay <prefix>order_history in (date_added, order_history_id) order,
 * starting from status 0, applying addHistory() stock semantics per transition:
 *   not-holding → holding  : deduct the line quantity
 *   holding → not-holding  : restock the line quantity
 * applied to order_product.product_id and, when non-zero, order_product.master_id — as the code does.
 *   buggy rule   : holding = config_processing_status (what production ran: `+` key union)
 *   correct rule : holding = config_processing_status ∪ ["5","12"] (post-WP1 intent)
 *   inflation    = buggy net delta − correct net delta (units the bug put back on the shelf)
 *
 * This is an ESTIMATE to compare with the physical count, never a value to write back:
 * manual stock edits by the owner are invisible to the replay; deleted orders (history deleted with
 * them) are invisible; the current `subtract` flag and current status lists are assumed for all history.
 * No customer names, emails, phones or addresses are read or written.
 */
declare(strict_types=1);

const BUG003_WP3_ID = 'BUG-003_stock-inflation-report_20261002';
const BUG003_WP3_CORRECT_EXTRA = [5, 12];
const BUG003_WP3_HUTKO_LINK_COMMENT = 'Payment Link Created (Admin)';

function bug003_wp3_note(string $message): void {
    echo $message . PHP_EOL;
}

function bug003_wp3_fail(string $message): void {
    fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL);
    exit(1);
}

function bug003_wp3_query(mysqli $db, string $sql, string $context) {
    $result = $db->query($sql);

    if ($result === false) {
        throw new RuntimeException('Database query failed at ' . $context . ' (errno ' . $db->errno . '). No credentials were printed.');
    }

    return $result;
}

function bug003_wp3_ids(?string $json): array {
    $decoded = json_decode((string)$json, true);

    return is_array($decoded) ? array_values(array_unique(array_map('intval', $decoded))) : [];
}

function bug003_wp3_pairs(array $map): string {
    $out = [];
    foreach ($map as $orderId => $units) {
        $out[] = $orderId . ':' . $units;
    }

    return implode(' ', $out);
}

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

mysqli_report(MYSQLI_REPORT_OFF);

$root = __DIR__;
$config = $root . '/config.php';

bug003_wp3_note('patch=' . BUG003_WP3_ID);
bug003_wp3_note('mode=read-only');
bug003_wp3_note('time=' . date('c'));

if (!is_file($config)) {
    bug003_wp3_fail('config.php missing; run only from public_html.');
}

require $config;

foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PORT', 'DB_PREFIX'] as $constant) {
    if (!defined($constant)) {
        bug003_wp3_fail($constant . ' missing from config.php.');
    }
}
if (!preg_match('/^[A-Za-z0-9_]+$/', (string)DB_PREFIX)) {
    bug003_wp3_fail('DB_PREFIX contains unexpected characters.');
}

// Output directory: sibling of public_html, never inside it.
$reportDir = dirname($root) . '/bs-reports';
if (!is_dir($reportDir) && !mkdir($reportDir, 0700, true) && !is_dir($reportDir)) {
    bug003_wp3_fail('Cannot create ' . $reportDir . '.');
}
$realRoot = realpath($root);
$realReport = realpath($reportDir);
if ($realRoot === false || $realReport === false || strpos($realReport . '/', rtrim($realRoot, '/') . '/') === 0) {
    bug003_wp3_fail('Report directory resolves inside public_html; refusing to write.');
}
$csvPath = $realReport . '/BUG-003_stock-inflation_' . date('Ymd-His') . '.csv';

$db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
if ($db->connect_errno) {
    bug003_wp3_fail('Database connection failed. No credentials were printed.');
}
if (!$db->set_charset('utf8mb4')) {
    $db->close();
    bug003_wp3_fail('Cannot set UTF-8 database connection.');
}

$p = DB_PREFIX;

try {
    // Status lists.
    $lists = ['config_processing_status' => null, 'config_complete_status' => null];
    $result = bug003_wp3_query($db, "SELECT `key`,`value` FROM `{$p}setting` WHERE `store_id`=0 AND `code`='config' AND `key` IN ('config_processing_status','config_complete_status')", 'status_lists');
    while ($row = $result->fetch_assoc()) {
        $lists[$row['key']] = (string)$row['value'];
    }
    if ($lists['config_processing_status'] === null) {
        throw new RuntimeException('config_processing_status not found.');
    }
    $processing = bug003_wp3_ids($lists['config_processing_status']);
    $buggyHold = array_fill_keys($processing, true);
    $correctHold = array_fill_keys(array_merge($processing, BUG003_WP3_CORRECT_EXTRA), true);

    bug003_wp3_note('config_processing_status=' . $lists['config_processing_status']);
    bug003_wp3_note('config_complete_status=' . (string)$lists['config_complete_status']);
    bug003_wp3_note('buggy_holding=' . implode(',', array_keys($buggyHold)));
    bug003_wp3_note('correct_holding=' . implode(',', array_keys($correctHold)));

    // Products (no personal data in this table).
    $products = [];
    $result = bug003_wp3_query($db, "SELECT `product_id`,`model`,`sku`,`quantity`,`subtract` FROM `{$p}product`", 'products');
    while ($row = $result->fetch_assoc()) {
        $products[(int)$row['product_id']] = [
            'model'    => (string)$row['model'],
            'sku'      => (string)$row['sku'],
            'quantity' => (int)$row['quantity'],
            'subtract' => (int)$row['subtract'],
        ];
    }

    // Order lines: ids and quantities only.
    $lines = [];
    $result = bug003_wp3_query($db, "SELECT `order_id`,`product_id`,`master_id`,`quantity` FROM `{$p}order_product`", 'order_products');
    while ($row = $result->fetch_assoc()) {
        $lines[(int)$row['order_id']][] = [
            'product_id' => (int)$row['product_id'],
            'master_id'  => (int)$row['master_id'],
            'quantity'   => (int)$row['quantity'],
        ];
    }

    // History: status ids and order only; the comment is reduced to one boolean in SQL.
    $histories = [];
    $hutkoLink = $db->real_escape_string(BUG003_WP3_HUTKO_LINK_COMMENT);
    $result = bug003_wp3_query(
        $db,
        "SELECT `order_id`,`order_status_id`,(`comment`='{$hutkoLink}') AS `bypass` FROM `{$p}order_history` ORDER BY `order_id`,`date_added`,`order_history_id`",
        'order_history'
    );
    while ($row = $result->fetch_assoc()) {
        $histories[(int)$row['order_id']][] = [(int)$row['order_status_id'], (int)$row['bypass'] === 1];
    }

    // Current order status, to spot status writes that bypassed order_history.
    $orderStatus = [];
    $result = bug003_wp3_query($db, "SELECT `order_id`,`order_status_id` FROM `{$p}order`", 'orders');
    while ($row = $result->fetch_assoc()) {
        $orderStatus[(int)$row['order_id']] = (int)$row['order_status_id'];
    }

    // Replay.
    $buggy = [];
    $correct = [];
    $buggyRestocks = [];
    $inflatingOrders = [];
    $bypassSkipped = [];
    $statusMismatch = [];

    foreach ($histories as $orderId => $rows) {
        $prev = 0;
        $buggyOrder = 0;   // per-unit-of-line sign: −1 deducted, +1 restocked, summed over transitions
        $correctOrder = 0;
        $buggyRestockCount = 0;

        foreach ($rows as [$status, $bypass]) {
            if ($bypass) {
                // Hutko admin "payment link" writes history directly, without addHistory() stock logic.
                $bypassSkipped[$orderId] = true;
                $prev = $status;
                continue;
            }

            $bWas = isset($buggyHold[$prev]);
            $bNow = isset($buggyHold[$status]);
            if (!$bWas && $bNow) {
                $buggyOrder--;
            } elseif ($bWas && !$bNow) {
                $buggyOrder++;
                $buggyRestockCount++;
            }

            $cWas = isset($correctHold[$prev]);
            $cNow = isset($correctHold[$status]);
            if (!$cWas && $cNow) {
                $correctOrder--;
            } elseif ($cWas && !$cNow) {
                $correctOrder++;
            }

            $prev = $status;
        }

        if (isset($orderStatus[$orderId]) && $orderStatus[$orderId] !== $prev) {
            $statusMismatch[] = $orderId . '(history ' . $prev . ', order ' . $orderStatus[$orderId] . ')';
        }

        foreach ($lines[$orderId] ?? [] as $line) {
            $targets = [$line['product_id']];
            if ($line['master_id']) {
                $targets[] = $line['master_id'];
            }

            foreach ($targets as $productId) {
                $buggy[$productId] = ($buggy[$productId] ?? 0) + $buggyOrder * $line['quantity'];
                $correct[$productId] = ($correct[$productId] ?? 0) + $correctOrder * $line['quantity'];

                if ($buggyRestockCount > 0) {
                    $buggyRestocks[$productId][$orderId] = ($buggyRestocks[$productId][$orderId] ?? 0) + $buggyRestockCount * $line['quantity'];
                }

                $delta = ($buggyOrder - $correctOrder) * $line['quantity'];
                if ($delta !== 0) {
                    $inflatingOrders[$productId][$orderId] = ($inflatingOrders[$productId][$orderId] ?? 0) + $delta;
                }
            }
        }
    }

    // Deleted-order gaps.
    $gaps = [];
    if ($orderStatus) {
        $ids = array_keys($orderStatus);
        $maxId = max($ids);
        for ($id = min($ids); $id <= $maxId; $id++) {
            if (!isset($orderStatus[$id])) {
                $gaps[] = $id;
            }
        }
    }

    // Build rows: products with subtract = 1.
    $report = [];
    foreach ($products as $productId => $product) {
        if ($product['subtract'] !== 1) {
            continue;
        }
        $b = $buggy[$productId] ?? 0;
        $c = $correct[$productId] ?? 0;
        $report[] = [
            'product_id'            => $productId,
            'model'                 => $product['model'],
            'sku'                   => $product['sku'],
            'current_quantity'      => $product['quantity'],
            'buggy_net_delta'       => $b,
            'correct_net_delta'     => $c,
            'inflation'             => $b - $c,
            'buggy_restock_orders'  => bug003_wp3_pairs($buggyRestocks[$productId] ?? []),
            'inflating_orders'      => bug003_wp3_pairs($inflatingOrders[$productId] ?? []),
        ];
    }
    usort($report, static function (array $a, array $b): int {
        return [$b['inflation'], $a['product_id']] <=> [$a['inflation'], $b['product_id']];
    });

    // CSV.
    $fh = fopen($csvPath, 'xb');
    if ($fh === false) {
        throw new RuntimeException('Cannot create ' . $csvPath);
    }
    $header = [
        '# BUG-003 stock inflation ESTIMATE — generated ' . date('c') . ' — read-only replay of order history.',
        '# Compare with the physical count. NEVER write these numbers back: manual stock edits by the owner and deleted orders are invisible to the replay.',
        '# inflation = buggy_net_delta - correct_net_delta = units the status bug returned to stock. Order lists are order_id:units.',
        '# buggy holding statuses: ' . implode(',', array_keys($buggyHold)) . ' | correct holding statuses: ' . implode(',', array_keys($correctHold)),
        '# order_id gaps (deleted or never created, not replayed): ' . ($gaps ? implode(' ', $gaps) : 'none'),
        '# orders whose current status differs from their last history status (status written outside addHistory): ' . ($statusMismatch ? implode(' ', $statusMismatch) : 'none'),
        '# orders with a Hutko admin payment-link history row (skipped as no-stock transitions): ' . ($bypassSkipped ? implode(' ', array_keys($bypassSkipped)) : 'none'),
    ];
    foreach ($header as $line) {
        fwrite($fh, $line . "\n");
    }
    fputcsv($fh, array_keys($report[0] ?? ['product_id' => 0, 'model' => '', 'sku' => '', 'current_quantity' => 0, 'buggy_net_delta' => 0, 'correct_net_delta' => 0, 'inflation' => 0, 'buggy_restock_orders' => '', 'inflating_orders' => '']));
    foreach ($report as $row) {
        fputcsv($fh, array_values($row));
    }
    fclose($fh);
    @chmod($csvPath, 0600);

    // Stdout summary.
    $inflated = array_values(array_filter($report, static function (array $row): bool {
        return $row['inflation'] > 0;
    }));
    $negative = count(array_filter($report, static function (array $row): bool {
        return $row['inflation'] < 0;
    }));
    $totalUnits = array_sum(array_column($inflated, 'inflation'));

    bug003_wp3_note('orders_replayed=' . count($histories));
    bug003_wp3_note('products_subtract_1=' . count($report));
    bug003_wp3_note('products_inflation_gt_0=' . count($inflated));
    bug003_wp3_note('products_inflation_lt_0=' . $negative);
    bug003_wp3_note('inflation_units_total=' . $totalUnits);
    bug003_wp3_note('order_id_gaps=' . ($gaps ? implode(' ', $gaps) : 'none'));
    bug003_wp3_note('status_written_outside_history=' . ($statusMismatch ? implode(' ', $statusMismatch) : 'none'));
    bug003_wp3_note('top20 (product_id | model | sku | current_qty | inflation | inflating orders):');
    foreach (array_slice($inflated, 0, 20) as $row) {
        bug003_wp3_note('  ' . $row['product_id'] . ' | ' . $row['model'] . ' | ' . $row['sku'] . ' | ' . $row['current_quantity'] . ' | ' . $row['inflation'] . ' | ' . $row['inflating_orders']);
    }
    bug003_wp3_note('csv=' . $csvPath);
    bug003_wp3_note('ESTIMATE ONLY — compare with the physical count; never write back.');
    bug003_wp3_note('done=ok');

    $db->close();
    @unlink(__FILE__);
} catch (Throwable $error) {
    $db->close();
    bug003_wp3_fail('Report stopped: ' . $error->getMessage() . ' No data was changed.');
}
