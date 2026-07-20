<?php
// CHECKOUT-002_ASYNC_ORDER_SIDE_EFFECTS_20260719
namespace Opencart\System\Library;

final class BoosterAsyncHttpQueue {
    private const DIRECTORY = 'booster_async_http_queue';
    private const MARKER = 'CHECKOUT-002_ASYNC_ORDER_SIDE_EFFECTS_20260719';

    public static function enqueue(array $job): void {
        $job = self::normalizeJob($job);
        $dir = self::queueDir();
        $target = $dir . DIRECTORY_SEPARATOR . $job['id'] . '.json';

        if (is_file($target)) {
            return;
        }

        $json = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Could not encode async queue job.');
        }

        $tmp = $target . '.tmp-' . bin2hex(random_bytes(4));
        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Could not write async queue job.');
        }
        @chmod($tmp, 0600);

        if (!@rename($tmp, $target)) {
            @unlink($tmp);
            if (!is_file($target)) {
                throw new \RuntimeException('Could not finalize async queue job.');
            }
        }
    }

    public static function run(int $limit = 25): array {
        $dir = self::queueDir();
        $lock = @fopen($dir . DIRECTORY_SEPARATOR . '.worker.lock', 'c');
        if ($lock === false) {
            throw new \RuntimeException('Could not open async queue worker lock.');
        }

        if (!@flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return ['status' => 'busy', 'processed' => 0, 'delivered' => 0, 'retry' => 0];
        }

        $stats = ['status' => 'ok', 'processed' => 0, 'delivered' => 0, 'retry' => 0];

        try {
            foreach (glob($dir . DIRECTORY_SEPARATOR . '*.work-*') ?: [] as $stale) {
                $ready = preg_replace('~\.work-[^/\\]+$~', '.json', $stale);
                if (is_string($ready) && !is_file($ready)) {
                    @rename($stale, $ready);
                }
            }

            foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $path) {
                if ($stats['processed'] >= max(1, $limit)) {
                    break;
                }

                $working = $path . '.work-' . getmypid();
                if (!@rename($path, $working)) {
                    continue;
                }

                $stats['processed']++;
                $raw = file_get_contents($working);
                $job = is_string($raw) ? json_decode($raw, true) : null;

                if (!is_array($job)) {
                    self::moveToDeadLetter($working, 'invalid_json');
                    $stats['retry']++;
                    continue;
                }

                try {
                    $job = self::normalizeJob($job);
                    $result = self::dispatch($job);
                } catch (\Throwable $e) {
                    $result = ['ok' => false, 'error' => substr($e->getMessage(), 0, 240)];
                }

                if (!empty($result['ok'])) {
                    if ($job['success_marker'] !== '') {
                        self::writeSuccessMarker($job['success_marker']);
                    }
                    @unlink($working);
                    $stats['delivered']++;
                    continue;
                }

                $job['attempts'] = (int)$job['attempts'] + 1;
                $job['last_error'] = substr((string)($result['error'] ?? 'request_failed'), 0, 240);
                $job['last_attempt_at'] = gmdate('c');
                $encoded = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                if ($encoded === false || file_put_contents($working, $encoded, LOCK_EX) === false || !@rename($working, $path)) {
                    self::moveToDeadLetter($working, 'retry_write_failed');
                }
                $stats['retry']++;
            }
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $stats;
    }

    private static function normalizeJob(array $job): array {
        $id = isset($job['id']) ? (string)$job['id'] : '';
        if (!preg_match('~^[A-Za-z0-9._-]{12,180}$~', $id)) {
            throw new \RuntimeException('Invalid async queue job id.');
        }

        $url = isset($job['url']) ? (string)$job['url'] : '';
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if (!in_array($host, ['script.google.com', 'api.telegram.org'], true) && !str_ends_with($host, '.supabase.co')) {
            throw new \RuntimeException('Async queue host is not allowed.');
        }

        $headers = isset($job['headers']) && is_array($job['headers']) ? array_values($job['headers']) : [];
        $body = isset($job['body']) ? (string)$job['body'] : '';
        $expect = isset($job['expect']) ? (string)$job['expect'] : 'http_2xx';
        if (!in_array($expect, ['http_2xx', 'json_ok'], true)) {
            throw new \RuntimeException('Invalid async queue response expectation.');
        }

        $timeout = max(1, min(30, (int)($job['timeout'] ?? 10)));
        $connectTimeout = max(1, min($timeout, (int)($job['connect_timeout'] ?? 3)));
        $marker = isset($job['success_marker']) ? (string)$job['success_marker'] : '';

        return [
            'id' => $id,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
            'expect' => $expect,
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'success_marker' => $marker,
            'attempts' => max(0, (int)($job['attempts'] ?? 0)),
            'created_at' => isset($job['created_at']) ? (string)$job['created_at'] : gmdate('c'),
            'last_error' => isset($job['last_error']) ? (string)$job['last_error'] : '',
            'last_attempt_at' => isset($job['last_attempt_at']) ? (string)$job['last_attempt_at'] : '',
        ];
    }

    private static function dispatch(array $job): array {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'curl_unavailable'];
        }

        $ch = curl_init($job['url']);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'curl_init_failed'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $job['body'],
            CURLOPT_HTTPHEADER => $job['headers'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $job['connect_timeout'],
            CURLOPT_TIMEOUT => $job['timeout'],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_NOSIGNAL => true,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $status < 200 || $status >= 300) {
            return ['ok' => false, 'error' => 'http_' . $status . ' ' . substr($error, 0, 160)];
        }

        if ($job['expect'] === 'json_ok') {
            $decoded = json_decode((string)$response, true);
            if (!is_array($decoded) || ($decoded['ok'] ?? null) !== true) {
                return ['ok' => false, 'error' => 'response_missing_ok'];
            }
        }

        return ['ok' => true];
    }

    private static function queueDir(): string {
        if (!defined('DIR_STORAGE') || !is_string(DIR_STORAGE) || DIR_STORAGE === '') {
            throw new \RuntimeException('DIR_STORAGE is unavailable for async queue.');
        }

        $dir = rtrim(DIR_STORAGE, '/\\') . DIRECTORY_SEPARATOR . self::DIRECTORY;
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create async queue directory.');
        }
        @chmod($dir, 0700);

        return $dir;
    }

    private static function writeSuccessMarker(string $marker): void {
        $storage = rtrim((string)DIR_STORAGE, '/\\') . DIRECTORY_SEPARATOR;
        if ($marker === '' || strpos($marker, $storage) !== 0 || !preg_match('~\.sent$~', $marker)) {
            throw new \RuntimeException('Invalid async queue success marker.');
        }

        $dir = dirname($marker);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create async marker directory.');
        }
        if (file_put_contents($marker, gmdate('c'), LOCK_EX) === false) {
            throw new \RuntimeException('Could not write async success marker.');
        }
        @chmod($marker, 0600);
    }

    private static function moveToDeadLetter(string $working, string $reason): void {
        $target = $working . '.failed-' . preg_replace('~[^A-Za-z0-9._-]+~', '_', $reason);
        @rename($working, $target);
    }
}