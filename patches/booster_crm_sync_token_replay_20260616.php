<?php
declare(strict_types=1);

/**
 * Update Booster CRM sync token from a temporary local file/env and replay queue.
 * Token sources, in order:
 * - BOOSTER_CRM_TOKEN environment variable
 * - .booster_crm_token file in public_html
 * The token value is never printed and .booster_crm_token is deleted after read.
 */

$target = __DIR__ . '/system/library/booster_crm_sync.php';
$tokenFile = __DIR__ . '/.booster_crm_token';
$backupDir = __DIR__ . '/_boostershop_patch_backups/booster_crm_sync_token_20260616';
$retentionDays = 21;
$log = [];

function patch_log(array &$log, string $message): void {
    $log[] = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    echo $message . PHP_EOL;
}

function patch_fail(array $log, string $message): void {
    $log[] = '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $message;
    echo 'error=' . $message . PHP_EOL;
    if (defined('DIR_STORAGE')) {
        $logDir = rtrim((string)DIR_STORAGE, '/\\') . DIRECTORY_SEPARATOR . 'logs';
        if (is_dir($logDir) || @mkdir($logDir, 0755, true)) {
            @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'booster_crm_sync_token_patch_20260616.log', implode(PHP_EOL, $log) . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
    exit(1);
}

function normalize_text($value): string {
    $text = preg_replace('/\s+/', ' ', trim((string)$value));
    if ($text === null) {
        $text = '';
    }
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}

function normalize_phone($value): string {
    $digits = preg_replace('/\D+/', '', (string)$value);
    if ($digits === null) {
        $digits = '';
    }
    return strlen($digits) > 9 ? substr($digits, -9) : $digits;
}

function payload_order_key(array $payload): string {
    $key = isset($payload['order_key']) ? (string)$payload['order_key'] : '';
    if ($key !== '') {
        return preg_replace('/[^a-z0-9_\-]/i', '_', $key) ?: '';
    }
    $orderId = (int)($payload['order_id'] ?? 0);
    return $orderId > 0 ? 'OC-FOP-' . str_pad((string)$orderId, 4, '0', STR_PAD_LEFT) : '';
}

function payload_is_test_order(array $payload): bool {
    $phone = normalize_phone($payload['telephone'] ?? '');
    if ($phone !== '' && in_array($phone, ['991119279', '991111111'], true)) {
        return true;
    }
    $name = normalize_text(trim((string)($payload['firstname'] ?? '') . ' ' . (string)($payload['lastname'] ?? '')));
    if ($name !== '' && in_array($name, ['С”РІРіРµРЅС–Р№ Р»РµСѓСЃРµРЅРєРѕ', 'РµРІРіРµРЅРёР№ Р»РµСѓСЃРµРЅРєРѕ', 'evgenii leusenko', 'yevhenii leusenko', 'yevgeniy leusenko'], true)) {
        return true;
    }
    foreach (($payload['products'] ?? []) as $product) {
        if (!is_array($product)) {
            continue;
        }
        $text = normalize_text(($product['sku'] ?? '') . ' ' . ($product['model'] ?? '') . ' ' . ($product['name'] ?? ''));
        foreach (['test sku', 'test-sku', 'testsku', 'С‚РµСЃС‚РѕРІР° РїРѕР·РёС†С–СЏ'] as $marker) {
            if (strpos($text, normalize_text($marker)) !== false) {
                return true;
            }
        }
    }
    return false;
}

function payload_timestamp(array $payload, string $file): int {
    foreach (['date_modified', 'date_added'] as $field) {
        if (!empty($payload[$field])) {
            $ts = strtotime((string)$payload[$field]);
            if ($ts !== false) {
                return $ts;
            }
        }
    }
    return filemtime($file) ?: time();
}

function crm_post_json(string $url, string $json): string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT_MS => 3000,
            CURLOPT_TIMEOUT_MS => 15000,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_NOSIGNAL => true,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('HTTP ' . $status . ' ' . $error . ' ' . substr((string)$response, 0, 200));
        }
        return (string)$response;
    }
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $json,
            'timeout' => 15,
            'ignore_errors' => true,
            'follow_location' => 1,
            'max_redirects' => 5,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        throw new RuntimeException('HTTP request failed.');
    }
    return (string)$response;
}

function assert_ok_response(string $response): void {
    $trimmed = trim($response);
    if ($trimmed === '') {
        throw new RuntimeException('CRM response is empty.');
    }
    $decoded = json_decode($trimmed, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('CRM response is not JSON: ' . substr($trimmed, 0, 160));
    }
    if (($decoded['ok'] ?? null) !== true) {
        $error = isset($decoded['error']) ? (string)$decoded['error'] : 'unknown_error';
        throw new RuntimeException('CRM response ok=false: ' . substr($error, 0, 200));
    }
}

function read_new_token(string $tokenFile, array $log): string {
    $raw = getenv('BOOSTER_CRM_TOKEN');
    if (!is_string($raw) || trim($raw) === '') {
        if (!is_file($tokenFile)) {
            patch_fail($log, 'missing token source: create .booster_crm_token or set BOOSTER_CRM_TOKEN');
        }
        $raw = (string)file_get_contents($tokenFile);
        @unlink($tokenFile);
    }

    $candidate = trim($raw);
    if (preg_match('/^[A-Z0-9_]+\s*=\s*(.+)$/s', $candidate, $match)) {
        $candidate = $match[1];
    }
    $candidate = trim($candidate);
    if (strlen($candidate) >= 2) {
        $first = $candidate[0];
        $last = $candidate[strlen($candidate) - 1];
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $candidate = substr($candidate, 1, -1);
        }
    }

    $token = preg_replace('/\s+/', '', $candidate);
    if ($token === null) {
        patch_fail($log, 'token cleanup failed');
    }
    if ($token === '') {
        patch_fail($log, 'token is empty after cleanup');
    }
    if ($token !== trim($raw)) {
        echo 'token_whitespace_removed=yes' . PHP_EOL;
    }
    return $token;
}

function replay_queue(string $url, string $secretToken, int $retentionDays, array &$log): array {
    $queueDir = rtrim((string)DIR_STORAGE, '/\\') . DIRECTORY_SEPARATOR . 'booster_crm_queue';
    $sentDir = rtrim((string)DIR_STORAGE, '/\\') . DIRECTORY_SEPARATOR . 'booster_crm_sent';
    $stats = ['attempted' => 0, 'sent' => 0, 'failed' => 0, 'already_sent' => 0, 'test_skipped' => 0, 'expired_deleted' => 0, 'resigned' => 0];
    if (!is_dir($queueDir)) {
        return $stats;
    }
    if (!is_dir($sentDir) && !mkdir($sentDir, 0755, true) && !is_dir($sentDir)) {
        throw new RuntimeException('Could not create sent marker directory.');
    }

    $groups = [];
    foreach (glob($queueDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
        $raw = @file_get_contents($file);
        $payload = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($payload)) {
            continue;
        }
        $orderKey = payload_order_key($payload);
        if ($orderKey === '') {
            continue;
        }
        $ts = payload_timestamp($payload, $file);
        if (!isset($groups[$orderKey]) || $ts > $groups[$orderKey]['ts']) {
            $files = isset($groups[$orderKey]) ? $groups[$orderKey]['files'] : [];
            $files[] = $file;
            $groups[$orderKey] = ['latest' => $file, 'payload' => $payload, 'ts' => $ts, 'files' => $files];
        } else {
            $groups[$orderKey]['files'][] = $file;
        }
    }

    $cutoff = time() - ($retentionDays * 86400);
    ksort($groups);
    foreach ($groups as $orderKey => $group) {
        if ($group['ts'] < $cutoff) {
            foreach ($group['files'] as $file) {
                @unlink($file);
            }
            $stats['expired_deleted']++;
            patch_log($log, 'queue_delete_expired=' . $orderKey);
            continue;
        }
        if (payload_is_test_order($group['payload'])) {
            foreach ($group['files'] as $file) {
                @unlink($file);
            }
            $stats['test_skipped']++;
            patch_log($log, 'queue_skip_test_order=' . $orderKey);
            continue;
        }
        $sentMarker = $sentDir . DIRECTORY_SEPARATOR . $orderKey . '.sent';
        if (is_file($sentMarker)) {
            foreach ($group['files'] as $file) {
                @unlink($file);
            }
            $stats['already_sent']++;
            patch_log($log, 'queue_skip_already_sent=' . $orderKey);
            continue;
        }

        $payload = $group['payload'];
        if (($payload['token'] ?? '') !== $secretToken) {
            $payload['token'] = $secretToken;
            $stats['resigned']++;
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $stats['failed']++;
            patch_log($log, 'queue_failed_json_encode=' . $orderKey);
            continue;
        }

        $stats['attempted']++;
        try {
            $response = crm_post_json($url, $json);
            assert_ok_response($response);
            @file_put_contents($sentMarker, date('c'), LOCK_EX);
            foreach ($group['files'] as $file) {
                @unlink($file);
            }
            $stats['sent']++;
            patch_log($log, 'queue_sent=' . $orderKey);
        } catch (Throwable $e) {
            $stats['failed']++;
            patch_log($log, 'queue_failed=' . $orderKey . ' message=' . substr($e->getMessage(), 0, 220));
        }
    }
    return $stats;
}

patch_log($log, 'cwd=' . getcwd());
patch_log($log, 'time=' . date('c'));
$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    patch_fail($log, 'config.php not found; run from public_html');
}
require_once $configPath;
if (!is_file($target)) {
    patch_fail($log, 'target missing: system/library/booster_crm_sync.php');
}
$source = file_get_contents($target);
if ($source === false) {
    patch_fail($log, 'cannot read target');
}
if (!preg_match("/private const WEB_APP_URL = '([^']+)'/", $source, $urlMatch)) {
    patch_fail($log, 'cannot read WEB_APP_URL');
}
if (!preg_match("/private const SECRET_TOKEN = '([^']*)'/", $source, $oldTokenMatch)) {
    patch_fail($log, 'cannot read current SECRET_TOKEN');
}
$url = $urlMatch[1];
$newToken = read_new_token($tokenFile, $log);

if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
    patch_fail($log, 'cannot create backup dir');
}
$backup = $backupDir . '/booster_crm_sync.php.' . date('Ymd_His') . '.bak';
if (!copy($target, $backup)) {
    patch_fail($log, 'cannot create backup');
}
patch_log($log, 'backup=' . $backup);

$updated = preg_replace("/private const SECRET_TOKEN = '[^']*';/", "private const SECRET_TOKEN = '" . addcslashes($newToken, "\\'") . "';", $source, 1, $count);
if (!is_string($updated) || $count !== 1) {
    patch_fail($log, 'SECRET_TOKEN replace failed');
}
$updated = str_replace("\tprivate const TIMEOUT_SECONDS = 2;\n\tprivate const CONNECT_TIMEOUT_MS = 700;\n\tprivate const TIMEOUT_MS = 1800;", "\tprivate const TIMEOUT_SECONDS = 15;\n\tprivate const CONNECT_TIMEOUT_MS = 3000;\n\tprivate const TIMEOUT_MS = 15000;", $updated);
$updated = str_replace("\n\t\tif (defined('CURL_REDIR_POST_ALL')) {\n\t\t\t\$options[CURLOPT_POSTREDIR] = CURL_REDIR_POST_ALL;\n\t\t}\n", "\n", $updated);

if (file_put_contents($target, $updated, LOCK_EX) === false) {
    patch_fail($log, 'cannot write target');
}
patch_log($log, 'changed=system/library/booster_crm_sync.php');
$lintOutput = [];
$lintCode = 0;
exec('php -l ' . escapeshellarg($target) . ' 2>&1', $lintOutput, $lintCode);
patch_log($log, 'php_lint=' . implode(' | ', $lintOutput));
if ($lintCode !== 0) {
    @copy($backup, $target);
    patch_fail($log, 'php lint failed; restored backup');
}

try {
    $stats = replay_queue($url, $newToken, $retentionDays, $log);
    patch_log($log, 'retention_days=' . $retentionDays);
    patch_log($log, 'replay_attempted=' . $stats['attempted']);
    patch_log($log, 'replay_resigned=' . $stats['resigned']);
    patch_log($log, 'replay_sent=' . $stats['sent']);
    patch_log($log, 'replay_failed=' . $stats['failed']);
    patch_log($log, 'replay_already_sent=' . $stats['already_sent']);
    patch_log($log, 'replay_test_skipped=' . $stats['test_skipped']);
    patch_log($log, 'replay_expired_deleted=' . $stats['expired_deleted']);
    if ($stats['failed'] > 0) {
        patch_fail($log, 'queue replay has failures; patch file kept for rerun');
    }
} catch (Throwable $e) {
    patch_fail($log, 'queue replay exception: ' . substr($e->getMessage(), 0, 300));
}

if (defined('DIR_STORAGE')) {
    $logDir = rtrim((string)DIR_STORAGE, '/\\') . DIRECTORY_SEPARATOR . 'logs';
    if (is_dir($logDir) || @mkdir($logDir, 0755, true)) {
        @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'booster_crm_sync_token_patch_20260616.log', implode(PHP_EOL, $log) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
patch_log($log, 'done=ok');
@unlink(__FILE__);
