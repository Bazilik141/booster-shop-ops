<?php
declare(strict_types=1);

/**
 * UI-FIX (Task 5) — remove invalid quantity=1 rows from `ocp5_product_discount`.
 *
 * WHAT IS BROKEN
 * --------------
 * Two products carry a row in `product_discount` with `quantity = 1` and
 * `special = 0` — i.e. a row entered on the admin "Discount" tab, where a
 * discount means "from N units" and N must be >= 2. Both rows have empty
 * date_start/date_end, which in this build's catalog SQL means "always active"
 * (`date_start = '0000-00-00' OR date_start < NOW()`), so they cannot be turned
 * off from the admin the way the owner tried.
 *
 * Effect measured on production 2026-09-03:
 *   - product page and cart charge the discount price;
 *   - category listings, search and merchant-feed.tsv keep showing `product.price`.
 * A full 99-product sweep (every row of merchant-feed.tsv against its own
 * product page) found exactly these two products and no others.
 *
 *   product_id 148  PKM-JP-INFX-BBX  product.price 6000.00  discount row 5700.00
 *   product_id 115  PKM-JP-MDEX-BBX  product.price 4900.00  discount row 4500.00
 *
 * Owner decision 2026-09-03: 6000.00 and 4900.00 are the correct prices; the
 * discount rows are wrong input and must go. `product.price` is NOT touched by
 * this patch — it is already correct. Rows on the "Special" tab (`special = 1`)
 * and any genuine quantity >= 2 discount row are out of scope and are never
 * matched by this patch.
 *
 * DB WRITE — owner-approved 2026-09-03 (AGENTS.md convention C6).
 *
 * ROLLBACK
 * --------
 * `restore.sql` is written into the backup directory BEFORE any write and holds
 * the exact INSERT for every deleted row, including its original
 * product_discount_id. Shape:
 *
 *   INSERT INTO `ocp5_product_discount`
 *     (`product_discount_id`,`product_id`,`customer_group_id`,`quantity`,
 *      `priority`,`price`,`type`,`special`,`date_start`,`date_end`)
 *   VALUES (<id>,<product_id>,<cgid>,1,<priority>,<price>,'<type>',0,
 *           '<date_start>','<date_end>');
 *
 * The row known from backup-8.28.2026_13-26-46 (product 115) restores as:
 *
 *   INSERT INTO `ocp5_product_discount`
 *     (`product_discount_id`,`product_id`,`customer_group_id`,`quantity`,
 *      `priority`,`price`,`type`,`special`,`date_start`,`date_end`)
 *   VALUES (1163,115,1,1,0,4500.0000,'F',0,'0000-00-00','0000-00-00');
 *
 * After running, clear the storefront cache if listings look stale:
 * the catalog listing query is cached under `product.*` in
 * ~/ocartdata/storage/cache/ and this patch does not touch it (the values it
 * caches are the correct ones — only the product page was wrong).
 *
 * Usage (from ~/public_html):
 *   php UI-FIX_price-discount-rows_20260903.php --dry-run
 *   php UI-FIX_price-discount-rows_20260903.php
 */

const PATCH_ID = 'UI-FIX_price-discount-rows_20260903';

/** product_id => [expected model, expected product.price] */
const TARGETS = [
    148 => ['PKM-JP-INFX-BBX', '6000.0000'],
    115 => ['PKM-JP-MDEX-BBX', '4900.0000'],
];

function out(string $key, string $value): void {
    echo $key . '=' . $value . PHP_EOL;
}

function fail(string $message) {
    throw new RuntimeException($message);
}

function need(bool $ok, string $message): void {
    if (!$ok) {
        fail($message);
    }
}

function same_price(string $left, string $right): bool {
    return abs((float)$left - (float)$right) < 0.005;
}

function php_lint(): void {
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
    // prepared-statement result helpers are unavailable and fatal here. See
    // diagnostics/LEGAL-002_offer_mono_pumb_archive_v3_report_20260724.md and
    // ..._v4_report_20260724.md: v2 of that patch died on exactly that call.
    // Read through result_metadata() + bind_result() instead — the same proven
    // pattern as bs_stmt_rows() in LEGAL-002 ..._v4_20260724.php, which ran to
    // completion on this host. Return shape is unchanged: a list of associative
    // arrays keyed by column name.
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
        // bind_result binds by reference — without a per-iteration copy every
        // element would point at the same final row.
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

function quote(mysqli $db, ?string $value): string {
    return $value === null ? 'NULL' : "'" . $db->real_escape_string($value) . "'";
}

$arguments = array_slice($argv ?? [], 1);
need($arguments === [] || $arguments === ['--dry-run'], 'usage:php ' . basename(__FILE__) . ' [--dry-run]');
$dryRun = $arguments === ['--dry-run'];

php_lint();

$transaction = false;
$db = null;

try {
    [$db, $prefix] = connect();

    $productTable = $prefix . 'product';
    $discountTable = $prefix . 'product_discount';
    rows($db, 'SELECT 1 FROM `' . $productTable . '` LIMIT 0');
    rows($db, 'SELECT 1 FROM `' . $discountTable . '` LIMIT 0');

    // Full-table survey first: anything else shaped like the defect is reported,
    // never deleted. A new one appearing later is a data-entry problem, not this
    // patch's business.
    $survey = rows(
        $db,
        'SELECT `pd`.`product_discount_id`, `pd`.`product_id`, `p`.`model`, `pd`.`price`
           FROM `' . $discountTable . '` `pd`
           LEFT JOIN `' . $productTable . '` `p` ON (`p`.`product_id` = `pd`.`product_id`)
          WHERE `pd`.`quantity` = 1 AND `pd`.`special` = 0
          ORDER BY `pd`.`product_id`'
    );
    foreach ($survey as $row) {
        $known = isset(TARGETS[(int)$row['product_id']]) ? 'in_scope' : 'OUT_OF_SCOPE_REPORT_ONLY';
        out('survey', sprintf(
            'discount_id=%d product_id=%d model=%s price=%s %s',
            (int)$row['product_discount_id'],
            (int)$row['product_id'],
            (string)($row['model'] ?? '?'),
            (string)$row['price'],
            $known
        ));
    }

    $plan = [];
    foreach (TARGETS as $productId => [$expectedModel, $expectedPrice]) {
        $product = rows(
            $db,
            'SELECT `product_id`, `model`, `price`, `status`, `quantity`, `stock_status_id`, `date_modified`
               FROM `' . $productTable . '` WHERE `product_id` = ?',
            [$productId]
        );
        need(count($product) === 1, 'product_missing:' . $productId);
        $product = $product[0];
        need(
            (string)$product['model'] === $expectedModel,
            'model_mismatch:' . $productId . ':' . (string)$product['model'] . ':expected=' . $expectedModel
        );
        need(
            same_price((string)$product['price'], $expectedPrice),
            'base_price_drift:' . $expectedModel . ':' . (string)$product['price'] . ':expected=' . $expectedPrice
        );

        $candidates = rows(
            $db,
            'SELECT `product_discount_id`, `product_id`, `customer_group_id`, `quantity`, `priority`,
                    `price`, `type`, `special`, `date_start`, `date_end`
               FROM `' . $discountTable . '`
              WHERE `product_id` = ? AND `quantity` = 1 AND `special` = 0',
            [$productId]
        );
        foreach ($candidates as $candidate) {
            $plan[] = ['product' => $product, 'row' => $candidate];
            out('plan', sprintf(
                'delete discount_id=%d %s qty=1 special=0 price=%s (base stays %s)',
                (int)$candidate['product_discount_id'],
                $expectedModel,
                (string)$candidate['price'],
                (string)$product['price']
            ));
        }
    }

    need(count($plan) <= 4, 'delete_limit_exceeded:' . count($plan));

    if ($plan === [] && !$dryRun) {
        out('already_applied', 'yes');
        out('done', 'ok');
        @unlink(__FILE__);
        exit(0);
    }

    if ($dryRun) {
        out('dry_run', 'ok');
        out('planned_deletes', (string)count($plan));
        exit(0);
    }

    $root = rtrim((string)(getcwd() ?: __DIR__), "/\\");
    $backupDir = $root . '/_patch_backups/' . PATCH_ID . '-' . gmdate('Ymd-His');
    need(!file_exists($backupDir), 'backup_path_exists');
    need(mkdir($backupDir, 0750, true), 'backup_create_failed');

    $before = json_encode(
        ['plan' => $plan, 'survey' => $survey],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    need(file_put_contents($backupDir . '/before.json', $before . PHP_EOL, LOCK_EX) !== false, 'backup_write_failed');

    $restore = "-- " . PATCH_ID . " rollback\n-- Re-creates every deleted quantity=1 discount row verbatim.\nSTART TRANSACTION;\n";
    foreach ($plan as $item) {
        $row = $item['row'];
        $restore .= 'INSERT INTO `' . $discountTable . '` '
            . '(`product_discount_id`,`product_id`,`customer_group_id`,`quantity`,`priority`,`price`,`type`,`special`,`date_start`,`date_end`) VALUES ('
            . (int)$row['product_discount_id'] . ','
            . (int)$row['product_id'] . ','
            . (int)$row['customer_group_id'] . ','
            . (int)$row['quantity'] . ','
            . (int)$row['priority'] . ','
            . quote($db, (string)$row['price']) . ','
            . quote($db, (string)$row['type']) . ','
            . (int)$row['special'] . ','
            . quote($db, (string)$row['date_start']) . ','
            . quote($db, (string)$row['date_end']) . ");\n";
    }
    $restore .= "COMMIT;\n";
    need(file_put_contents($backupDir . '/restore.sql', $restore, LOCK_EX) !== false, 'restore_write_failed');
    out('backup', str_replace($root . '/', '', $backupDir));

    $db->begin_transaction();
    $transaction = true;

    $deleted = 0;
    foreach ($plan as $item) {
        $row = $item['row'];
        $affected = execute(
            $db,
            'DELETE FROM `' . $discountTable . '`
              WHERE `product_discount_id` = ? AND `product_id` = ? AND `quantity` = 1 AND `special` = 0 AND `price` = ?',
            [$row['product_discount_id'], $row['product_id'], $row['price']]
        );
        need($affected === 1, 'delete_failed:' . (int)$row['product_discount_id']);
        $deleted++;
    }

    // Nothing but those rows may have moved.
    foreach (TARGETS as $productId => [$expectedModel, $expectedPrice]) {
        $after = rows(
            $db,
            'SELECT `model`, `price`, `status`, `quantity`, `stock_status_id`, `date_modified`
               FROM `' . $productTable . '` WHERE `product_id` = ?',
            [$productId]
        );
        need(count($after) === 1, 'verify_product_missing:' . $productId);
        need(same_price((string)$after[0]['price'], $expectedPrice), 'verify_price_changed:' . $expectedModel);

        $left = rows(
            $db,
            'SELECT COUNT(*) AS `total` FROM `' . $discountTable . '`
              WHERE `product_id` = ? AND `quantity` = 1 AND `special` = 0',
            [$productId]
        );
        need((int)$left[0]['total'] === 0, 'verify_rows_remain:' . $expectedModel);
    }

    $db->commit();
    $transaction = false;

    out('deleted_rows', (string)$deleted);
    out('base_prices_untouched', 'yes');
    out('done', 'ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    if ($transaction && $db instanceof mysqli) {
        $db->rollback();
    }
    fwrite(STDERR, 'ERROR=' . $error->getMessage() . PHP_EOL);
    exit(1);
}
