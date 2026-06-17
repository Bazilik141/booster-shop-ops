<?php
declare(strict_types=1);

/**
 * Read-only diagnostic for Booster CRM sync order #180.
 * Does not modify DB or site files except writing a diagnostic report/zip.
 */

$orderId = 180;
$orderKey = 'OC-FOP-0180';
$startedAt = date('Y-m-d H:i:s');
$root = getcwd();
$configPath = $root . DIRECTORY_SEPARATOR . 'config.php';

function diag_fail(string $message): void {
    fwrite(STDERR, "error=" . $message . PHP_EOL);
    exit(1);
}

function ensure_dir(string $path): void {
    if (!is_dir($path) && !mkdir($path, 0755, true)) {
        diag_fail('cannot_create_dir:' . $path);
    }
}

function redact_text(string $text): string {
    $text = preg_replace("~(SECRET_TOKEN\s*=\s*')[^']+(')~", '$1[REDACTED]$2', $text) ?? $text;
    $text = preg_replace('~("token"\s*:\s*")[^"]+(" )~', '$1[REDACTED]$2', $text) ?? $text;
    $text = preg_replace('~("token"\s*:\s*")[^"]+(")~', '$1[REDACTED]$2', $text) ?? $text;
    $text = preg_replace('~(token=)[^&\s]+~i', '$1[REDACTED]', $text) ?? $text;
    $text = preg_replace('~(key=)[^&\s]+~i', '$1[REDACTED]', $text) ?? $text;
    return $text;
}

function json_write(string $path, array $data): void {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($path, $json . PHP_EOL) === false) {
        diag_fail('cannot_write_json:' . $path);
    }
}

function db_all(mysqli $db, string $sql): array {
    $result = $db->query($sql);
    if (!$result) {
        return ['__error' => $db->error, '__sql' => $sql];
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->free();
    return $rows;
}

function table_exists(mysqli $db, string $table): bool {
    $safe = $db->real_escape_string($table);
    $result = $db->query("SHOW TABLES LIKE '{$safe}'");
    if (!$result) {
        return false;
    }
    $exists = $result->num_rows > 0;
    $result->free();
    return $exists;
}

function filtered_log_lines(string $path, array $needles, int $maxLines = 300): array {
    if (!is_file($path) || !is_readable($path)) {
        return ['__missing_or_unreadable' => $path];
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return ['__cannot_read' => $path];
    }
    $matches = [];
    foreach ($lines as $line) {
        foreach ($needles as $needle) {
            if (stripos($line, $needle) !== false) {
                if (stripos($line, 'Hutko Payment') !== false) {
                    continue;
                }
                $matches[] = redact_text($line);
                break;
            }
        }
    }
    return array_slice($matches, -$maxLines);
}

if (!is_file($configPath)) {
    diag_fail('run_from_public_html_config_missing');
}

require_once $configPath;

foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PORT', 'DB_PREFIX', 'DIR_STORAGE', 'DIR_LOGS'] as $constant) {
    if (!defined($constant)) {
        diag_fail('missing_constant:' . $constant);
    }
}

$diagDir = $root . DIRECTORY_SEPARATOR . 'booster-order180-diagnostic-' . date('Ymd-His');
ensure_dir($diagDir);
ensure_dir($diagDir . DIRECTORY_SEPARATOR . 'queue_payloads');

$db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
if ($db->connect_errno) {
    diag_fail('db_connect_failed:' . $db->connect_error);
}
$db->set_charset('utf8mb4');

$prefix = DB_PREFIX;
$tables = [
    'order' => $prefix . 'order',
    'order_product' => $prefix . 'order_product',
    'order_total' => $prefix . 'order_total',
    'order_history' => $prefix . 'order_history',
];

$missingTables = [];
foreach ($tables as $name => $table) {
    if (!table_exists($db, $table)) {
        $missingTables[] = $table;
    }
}
if ($missingTables) {
    diag_fail('missing_tables:' . implode(',', $missingTables));
}

$orderIdSql = (int)$orderId;
$orderRows = db_all($db, "SELECT order_id, order_status_id, invoice_prefix, store_name, language_code, currency_code, firstname, lastname, email, telephone, total, payment_method, shipping_method, comment, date_added, date_modified FROM `{$tables['order']}` WHERE order_id = {$orderIdSql}");
$productRows = db_all($db, "SELECT order_product_id, product_id, name, model, quantity, price, total FROM `{$tables['order_product']}` WHERE order_id = {$orderIdSql} ORDER BY order_product_id");
$totalRows = db_all($db, "SELECT title, value, sort_order, code FROM `{$tables['order_total']}` WHERE order_id = {$orderIdSql} ORDER BY sort_order, order_total_id");
$historyRows = db_all($db, "SELECT order_history_id, order_status_id, notify, comment, date_added FROM `{$tables['order_history']}` WHERE order_id = {$orderIdSql} ORDER BY order_history_id");

$storage = rtrim((string)DIR_STORAGE, '/\\') . DIRECTORY_SEPARATOR;
$queueDir = $storage . 'booster_crm_queue';
$sentDir = $storage . 'booster_crm_sent';
$sentMarker = $sentDir . DIRECTORY_SEPARATOR . $orderKey . '.sent';
$queuePatterns = [
    $queueDir . DIRECTORY_SEPARATOR . '*' . $orderId . '*.json',
    $queueDir . DIRECTORY_SEPARATOR . '*' . $orderKey . '*.json',
];
$queueFiles = [];
foreach ($queuePatterns as $pattern) {
    foreach (glob($pattern) ?: [] as $file) {
        $queueFiles[$file] = true;
    }
}
$queueFiles = array_keys($queueFiles);
sort($queueFiles);

$queueSummaries = [];
foreach ($queueFiles as $file) {
    $raw = is_readable($file) ? (string)file_get_contents($file) : '';
    $redactedRaw = redact_text($raw);
    $target = $diagDir . DIRECTORY_SEPARATOR . 'queue_payloads' . DIRECTORY_SEPARATOR . basename($file);
    file_put_contents($target, $redactedRaw);
    $decoded = json_decode($raw, true);
    if (is_array($decoded) && array_key_exists('token', $decoded)) {
        $decoded['token'] = '[REDACTED]';
    }
    $queueSummaries[] = [
        'file' => $file,
        'size' => is_file($file) ? filesize($file) : null,
        'mtime' => is_file($file) ? date('Y-m-d H:i:s', filemtime($file)) : null,
        'decoded' => is_array($decoded) ? $decoded : null,
        'copied_to' => $target,
    ];
}

$syncPath = $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'booster_crm_sync.php';
$syncInfo = ['path' => $syncPath, 'exists' => is_file($syncPath)];
if (is_file($syncPath)) {
    $syncInfo['size'] = filesize($syncPath);
    $syncInfo['mtime'] = date('Y-m-d H:i:s', filemtime($syncPath));
    $syncInfo['sha256'] = hash_file('sha256', $syncPath);
    $syncText = redact_text((string)file_get_contents($syncPath));
    file_put_contents($diagDir . DIRECTORY_SEPARATOR . 'booster_crm_sync_redacted.php.txt', $syncText);
}

$logPaths = array_values(array_unique(array_filter([
    defined('DIR_LOGS') ? rtrim((string)DIR_LOGS, '/\\') . DIRECTORY_SEPARATOR . 'error.log' : null,
    $storage . 'logs' . DIRECTORY_SEPARATOR . 'error.log',
])));
$logs = [];
foreach ($logPaths as $path) {
    $logs[$path] = filtered_log_lines($path, ['Booster CRM', $orderKey, 'order ' . $orderId, 'order_id ' . $orderId, 'order_id=' . $orderId, '#' . $orderId]);
}

$queueAll = glob($queueDir . DIRECTORY_SEPARATOR . '*.json') ?: [];
usort($queueAll, static function (string $a, string $b): int {
    return (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0);
});
$latestQueue = [];
foreach (array_slice($queueAll, 0, 20) as $file) {
    $latestQueue[] = [
        'file' => $file,
        'size' => filesize($file),
        'mtime' => date('Y-m-d H:i:s', filemtime($file)),
    ];
}

$report = [
    'meta' => [
        'script' => basename(__FILE__),
        'started_at' => $startedAt,
        'order_id' => $orderId,
        'order_key' => $orderKey,
        'root' => $root,
        'dir_storage' => defined('DIR_STORAGE') ? (string)DIR_STORAGE : null,
        'dir_logs' => defined('DIR_LOGS') ? (string)DIR_LOGS : null,
    ],
    'crm_sync_files' => [
        'sent_marker' => [
            'path' => $sentMarker,
            'exists' => is_file($sentMarker),
            'mtime' => is_file($sentMarker) ? date('Y-m-d H:i:s', filemtime($sentMarker)) : null,
        ],
        'queue_dir' => [
            'path' => $queueDir,
            'exists' => is_dir($queueDir),
            'total_json_count' => count($queueAll),
            'order_180_matches' => $queueSummaries,
            'latest_json_files' => $latestQueue,
        ],
        'sync_library' => $syncInfo,
    ],
    'database' => [
        'order' => $orderRows,
        'order_product' => $productRows,
        'order_total' => $totalRows,
        'order_history' => $historyRows,
    ],
    'logs' => $logs,
];

$reportPath = $diagDir . DIRECTORY_SEPARATOR . 'report.json';
json_write($reportPath, $report);

$zipPath = null;
if (class_exists('ZipArchive')) {
    $zipPath = $diagDir . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $baseLen = strlen($diagDir) + 1;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($diagDir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile()) {
                $zip->addFile($fileInfo->getPathname(), substr($fileInfo->getPathname(), $baseLen));
            }
        }
        $zip->close();
    } else {
        $zipPath = null;
    }
}

@unlink(__FILE__);

echo 'done=ok' . PHP_EOL;
echo 'order_found=' . (!empty($orderRows) && !isset($orderRows['__error']) ? 'yes' : 'no') . PHP_EOL;
echo 'sent_marker_exists=' . (is_file($sentMarker) ? 'yes' : 'no') . PHP_EOL;
echo 'queue_matches=' . count($queueFiles) . PHP_EOL;
echo 'report=' . $reportPath . PHP_EOL;
if ($zipPath && is_file($zipPath)) {
    echo 'archive=' . $zipPath . PHP_EOL;
} else {
    echo 'archive=not_created_ziparchive_unavailable' . PHP_EOL;
}
