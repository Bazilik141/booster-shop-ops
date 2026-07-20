<?php
/**
 * CHECKOUT-002: remove duplicate Telegram send and queue outbound order side effects.
 *
 * Targets:
 * - catalog/model/checkout/order.php
 * - system/library/booster_crm_sync.php
 * - extension/telegram_notify/catalog/controller/event/telegram.php
 * Creates:
 * - system/library/booster_async_queue.php
 * - system/library/booster_async_queue_worker.php
 *
 * Database: none.
 * Rollback: restore the three backed-up files and remove the two created files.
 */

declare(strict_types=1);

const CHECKOUT002_MARKER = 'CHECKOUT-002_ASYNC_ORDER_SIDE_EFFECTS_20260719';

function checkout002_fail(string $message): void {
    fwrite(STDERR, 'error=' . $message . PHP_EOL);
    exit(1);
}

function checkout002_count(string $source, string $needle): int {
    return substr_count($source, $needle);
}

function checkout002_regex_count(string $source, string $pattern): int {
    $count = preg_match_all($pattern, $source, $matches);
    if ($count === false) {
        checkout002_fail('invalid_internal_pattern');
    }

    return $count;
}

function checkout002_lint_file(string $path): void {
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $exitCode);
    if ($exitCode !== 0) {
        checkout002_fail('php_lint_failed target=' . $path . ' output=' . implode(' ', $output));
    }
}

function checkout002_lint_text(string $source, string $label): void {
    $tmp = tempnam(sys_get_temp_dir(), 'checkout002-lint-');
    if ($tmp === false) {
        checkout002_fail('cannot_create_temporary_lint_file target=' . $label);
    }

    file_put_contents($tmp, $source);
    checkout002_lint_file($tmp);
    @unlink($tmp);
}

$root = realpath(__DIR__);
if ($root === false) {
    checkout002_fail('cannot_resolve_public_html');
}

$orderPath = $root . '/catalog/model/checkout/order.php';
$senderPath = $root . '/system/library/booster_crm_sync.php';
$telegramPath = $root . '/extension/telegram_notify/catalog/controller/event/telegram.php';
$queuePath = $root . '/system/library/booster_async_queue.php';
$workerPath = $root . '/system/library/booster_async_queue_worker.php';

foreach ([$orderPath, $senderPath, $telegramPath] as $path) {
    if (!is_file($path)) {
        checkout002_fail('required_file_missing path=' . $path);
    }
}

$order = file_get_contents($orderPath);
$sender = file_get_contents($senderPath);
$telegram = file_get_contents($telegramPath);
if ($order === false || $sender === false || $telegram === false) {
    checkout002_fail('cannot_read_required_source');
}

$sourceMarkerCount = checkout002_count($order, CHECKOUT002_MARKER)
    + checkout002_count($sender, CHECKOUT002_MARKER)
    + checkout002_count($telegram, CHECKOUT002_MARKER);

if ($sourceMarkerCount > 0) {
    $queueSource = is_file($queuePath) ? file_get_contents($queuePath) : false;
    $workerSource = is_file($workerPath) ? file_get_contents($workerPath) : false;

    if (checkout002_count($order, CHECKOUT002_MARKER) === 1
        && checkout002_count($sender, CHECKOUT002_MARKER) === 2
        && checkout002_count($telegram, CHECKOUT002_MARKER) === 2
        && is_string($queueSource) && checkout002_count($queueSource, CHECKOUT002_MARKER) === 2
        && is_string($workerSource) && checkout002_count($workerSource, CHECKOUT002_MARKER) === 1) {
        echo 'already_applied=yes' . PHP_EOL;
        @unlink(__FILE__);
        exit(0);
    }

    checkout002_fail('partial_or_previous_application_detected restore_from_backup_before_retry');
}

if (is_file($queuePath) || is_file($workerPath)) {
    checkout002_fail('new_runtime_file_already_exists path=' . (is_file($queuePath) ? $queuePath : $workerPath));
}

$manualTelegramLine = "\t\t\t\$this->load->controller('extension/telegram_notify/event/telegram.sendOrderNotification', (int)\$order_id, (int)\$order_status_id);";
if (checkout002_count($order, $manualTelegramLine) !== 1) {
    checkout002_fail('anchor_count_invalid anchor=manual_telegram_send expected=1 actual=' . checkout002_count($order, $manualTelegramLine));
}

$boosterDirectPattern = '~\$this->postJson\(\$payload\);\s+\$this->markSent\(\$order_key\);\s+return;~';
if (checkout002_regex_count($sender, $boosterDirectPattern) !== 1) {
    checkout002_fail('anchor_count_invalid anchor=booster_direct_post expected=1 actual=' . checkout002_regex_count($sender, $boosterDirectPattern));
}

$ncrmDirectPattern = '~if \(!\$this->postJson\(\$config\[\'url\'\], \$config\[\'secret\'\], \$payload\)\) \{\s+error_log\(\'NCRM-10 order sync failed order_id=\' \. \(int\) \$orderId\);\s+\}~';
if (checkout002_regex_count($sender, $ncrmDirectPattern) !== 1) {
    checkout002_fail('anchor_count_invalid anchor=ncrm_direct_post expected=1 actual=' . checkout002_regex_count($sender, $ncrmDirectPattern));
}

$boosterQueueAnchor = "\tprivate function queuePayload(array \$payload): void {";
if (checkout002_count($sender, $boosterQueueAnchor) !== 1) {
    checkout002_fail('anchor_count_invalid anchor=booster_legacy_queue_method expected=1 actual=' . checkout002_count($sender, $boosterQueueAnchor));
}

$ncrmPostAnchor = "    private function postJson(string \$url, string \$secret, array \$payload): bool {";
if (checkout002_count($sender, $ncrmPostAnchor) !== 1) {
    checkout002_fail('anchor_count_invalid anchor=ncrm_post_method expected=1 actual=' . checkout002_count($sender, $ncrmPostAnchor));
}

$telegramMethodAnchor = 'private function sendMessage(string $message): void';
if (checkout002_count($telegram, $telegramMethodAnchor) !== 1) {
    checkout002_fail('anchor_count_invalid anchor=telegram_send_method expected=1 actual=' . checkout002_count($telegram, $telegramMethodAnchor));
}
$telegramMethodStart = strpos($telegram, $telegramMethodAnchor);
$telegramClassEnd = strrpos($telegram, "\n}");
if ($telegramMethodStart === false || $telegramClassEnd === false || $telegramClassEnd <= $telegramMethodStart) {
    checkout002_fail('anchor_boundary_invalid anchor=telegram_send_method');
}
$telegramEventAnchor = 'public function sendNotification(string &$route, array &$args, mixed &$output): void';
$telegramOrderMethodAnchor = 'public function sendOrderNotification(int $order_id, int $new_status_id): void';
if (checkout002_count($telegram, $telegramEventAnchor) !== 1
    || checkout002_count($telegram, $telegramOrderMethodAnchor) !== 1) {
    checkout002_fail('anchor_count_invalid anchor=telegram_event_handler');
}
$telegramEventStart = strpos($telegram, $telegramEventAnchor);
$telegramOrderMethodStart = strpos($telegram, $telegramOrderMethodAnchor);
if ($telegramEventStart === false || $telegramOrderMethodStart === false || $telegramOrderMethodStart <= $telegramEventStart) {
    checkout002_fail('anchor_boundary_invalid anchor=telegram_event_handler');
}

checkout002_lint_file($orderPath);
checkout002_lint_file($senderPath);
checkout002_lint_file($telegramPath);

$queueLibrary = <<<'PHP'
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
PHP;

$queueWorker = <<<'PHP'
<?php
// CHECKOUT-002_ASYNC_ORDER_SIDE_EFFECTS_20260719
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "cli_only\n");
    exit(1);
}

$root = dirname(__DIR__, 2);
$config = $root . '/config.php';
$queue = __DIR__ . '/booster_async_queue.php';

if (!is_file($config) || !is_file($queue)) {
    fwrite(STDERR, "required_file_missing\n");
    exit(1);
}

require_once($config);
require_once($queue);

try {
    $stats = \Opencart\System\Library\BoosterAsyncHttpQueue::run(25);
    echo 'status=' . $stats['status'] . PHP_EOL;
    echo 'processed=' . (int)$stats['processed'] . PHP_EOL;
    echo 'delivered=' . (int)$stats['delivered'] . PHP_EOL;
    echo 'retry=' . (int)$stats['retry'] . PHP_EOL;
} catch (\Throwable $e) {
    fwrite(STDERR, 'error=' . $e->getMessage() . PHP_EOL);
    exit(1);
}
PHP;

$boosterQueueMethod = <<<'PHP'
	// CHECKOUT-002_ASYNC_ORDER_SIDE_EFFECTS_20260719
	private function queueAsyncPayload(array $payload, string $order_key): void {
		$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			throw new \RuntimeException('Could not encode Booster CRM async payload.');
		}

		require_once(DIR_SYSTEM . 'library/booster_async_queue.php');
		$safe_key = preg_replace('/[^a-z0-9_\-]/i', '_', $order_key);
		if ($safe_key === null || $safe_key === '') {
			throw new \RuntimeException('Could not create Booster CRM async job id.');
		}

		\Opencart\System\Library\BoosterAsyncHttpQueue::enqueue([
			'id' => 'booster-crm-' . $safe_key,
			'url' => self::WEB_APP_URL,
			'headers' => ['Content-Type: application/json'],
			'body' => $json,
			'expect' => 'json_ok',
			'connect_timeout' => 3,
			'timeout' => self::TIMEOUT_SECONDS,
			'success_marker' => $this->getSentMarkerFile($order_key),
			'created_at' => gmdate('c'),
		]);
	}

PHP;

$ncrmQueueMethod = <<<'PHP'
    // CHECKOUT-002_ASYNC_ORDER_SIDE_EFFECTS_20260719
    private function queueAsyncPayload(string $url, string $secret, array $payload, int $orderId): void {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Could not encode NCRM async payload.');
        }

        $orderKey = trim((string)($payload['order_key'] ?? ''));
        $safeKey = preg_replace('/[^a-z0-9_\-]/i', '_', $orderKey !== '' ? $orderKey : ('order-' . $orderId));
        if ($safeKey === null || $safeKey === '') {
            throw new \RuntimeException('Could not create NCRM async job id.');
        }

        require_once(DIR_SYSTEM . 'library/booster_async_queue.php');
        \Opencart\System\Library\BoosterAsyncHttpQueue::enqueue([
            'id' => 'ncrm-' . $safeKey,
            'url' => $url,
            'headers' => [
                'Content-Type: application/json',
                'X-Order-Sync-Secret: ' . $secret,
            ],
            'body' => $json,
            'expect' => 'http_2xx',
            'connect_timeout' => 3,
            'timeout' => 8,
            'success_marker' => '',
            'created_at' => gmdate('c'),
        ]);
    }

PHP;

$telegramReplacement = <<<'PHP'
	// CHECKOUT-002_ASYNC_ORDER_SIDE_EFFECTS_20260719
	private function sendMessage(string $message): void
	{
		$status = $this->config->get('module_telegram_notify_status');
		$token = $this->config->get('module_telegram_notify_token');
		$chat_id = $this->config->get('module_telegram_notify_chat_id');

		if (!$status || !$token || !$chat_id) {
			return;
		}

		try {
			require_once(DIR_SYSTEM . 'library/booster_async_queue.php');
			\Opencart\System\Library\BoosterAsyncHttpQueue::enqueue([
				'id' => 'telegram-' . (string)(int)microtime(true) . '-' . bin2hex(random_bytes(5)),
				'url' => "https://api.telegram.org/bot{$token}/sendMessage",
				'headers' => ['Content-Type: application/x-www-form-urlencoded'],
				'body' => http_build_query(['chat_id' => $chat_id, 'text' => $message], '', '&', PHP_QUERY_RFC3986),
				'expect' => 'json_ok',
				'connect_timeout' => 3,
				'timeout' => 10,
				'success_marker' => '',
				'created_at' => gmdate('c'),
			]);
		} catch (\Throwable $e) {
			$this->log->write('Telegram notification queue failed: ' . $e->getMessage());
		}
	}
PHP;

$telegramEventReplacement = <<<'PHP'
public function sendNotification(string &$route, array &$args, mixed &$output): void
	{
		// CHECKOUT-002_ASYNC_ORDER_SIDE_EFFECTS_20260719
		// order.php calls sendOrderNotification directly for every addHistory event.
		// Keep this registered after-event handler inert to avoid a duplicate message.
		return;
	}

	
PHP;

$orderCandidate = str_replace(
    $manualTelegramLine,
    $manualTelegramLine . "\n\t\t\t// " . CHECKOUT002_MARKER . ': direct Telegram call is the single queued delivery path.',
    $order
);

$senderCandidate = preg_replace(
    $boosterDirectPattern,
    "\$this->queueAsyncPayload(\$payload, \$order_key);\n\t\t\treturn;",
    $sender,
    1,
    $boosterDirectReplacements
);
if ($senderCandidate === null || $boosterDirectReplacements !== 1) {
    checkout002_fail('booster_direct_post_replacement_failed');
}

$senderCandidate = preg_replace(
    $ncrmDirectPattern,
    "\$this->queueAsyncPayload(\$config['url'], \$config['secret'], \$payload, \$orderId);",
    $senderCandidate,
    1,
    $ncrmDirectReplacements
);
if ($senderCandidate === null || $ncrmDirectReplacements !== 1) {
    checkout002_fail('ncrm_direct_post_replacement_failed');
}

$senderCandidate = str_replace($boosterQueueAnchor, $boosterQueueMethod . $boosterQueueAnchor, $senderCandidate, $boosterQueueInsertions);
if ($boosterQueueInsertions !== 1) {
    checkout002_fail('booster_queue_method_insertion_failed');
}

$senderCandidate = str_replace($ncrmPostAnchor, $ncrmQueueMethod . $ncrmPostAnchor, $senderCandidate, $ncrmQueueInsertions);
if ($ncrmQueueInsertions !== 1) {
    checkout002_fail('ncrm_queue_method_insertion_failed');
}

$telegramWithEvent = substr($telegram, 0, $telegramEventStart) . $telegramEventReplacement . substr($telegram, $telegramOrderMethodStart);
$telegramCandidateMethodStart = strpos($telegramWithEvent, $telegramMethodAnchor);
$telegramCandidateClassEnd = strrpos($telegramWithEvent, "\n}");
if ($telegramCandidateMethodStart === false || $telegramCandidateClassEnd === false || $telegramCandidateClassEnd <= $telegramCandidateMethodStart) {
    checkout002_fail('candidate_boundary_invalid anchor=telegram_send_method');
}
$telegramCandidate = substr($telegramWithEvent, 0, $telegramCandidateMethodStart) . $telegramReplacement . substr($telegramWithEvent, $telegramCandidateClassEnd);

if (checkout002_count($orderCandidate, CHECKOUT002_MARKER) !== 1
    || checkout002_count($senderCandidate, CHECKOUT002_MARKER) !== 2
    || checkout002_count($telegramCandidate, CHECKOUT002_MARKER) !== 2
    || checkout002_count($orderCandidate, $manualTelegramLine) !== 1
    || checkout002_regex_count($senderCandidate, $boosterDirectPattern) !== 0
    || checkout002_regex_count($senderCandidate, $ncrmDirectPattern) !== 0
    || checkout002_count($telegramCandidate, $telegramMethodAnchor) !== 1
    || checkout002_count($queueLibrary, CHECKOUT002_MARKER) !== 2
    || checkout002_count($queueWorker, CHECKOUT002_MARKER) !== 1) {
    checkout002_fail('candidate_invariant_failed');
}

checkout002_lint_text($orderCandidate, 'catalog/model/checkout/order.php candidate');
checkout002_lint_text($senderCandidate, 'system/library/booster_crm_sync.php candidate');
checkout002_lint_text($telegramCandidate, 'extension/telegram_notify/catalog/controller/event/telegram.php candidate');
checkout002_lint_text($queueLibrary, 'system/library/booster_async_queue.php candidate');
checkout002_lint_text($queueWorker, 'system/library/booster_async_queue_worker.php candidate');

$backupDir = $root . '/_patch_backups/' . basename(__FILE__, '.php') . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
if (!mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
    checkout002_fail('cannot_create_backup_dir path=' . $backupDir);
}

$backups = [
    $orderPath => $backupDir . '/order.php',
    $senderPath => $backupDir . '/booster_crm_sync.php',
    $telegramPath => $backupDir . '/telegram.php',
];
foreach ($backups as $source => $backup) {
    if (!copy($source, $backup)) {
        checkout002_fail('backup_copy_failed path=' . $source);
    }
}

$writes = [
    $orderPath => $orderCandidate,
    $senderPath => $senderCandidate,
    $telegramPath => $telegramCandidate,
    $queuePath => $queueLibrary,
    $workerPath => $queueWorker,
];

try {
    foreach ($writes as $path => $contents) {
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('target_write_failed path=' . $path);
        }
    }
    @chmod($queuePath, 0640);
    @chmod($workerPath, 0700);

    foreach (array_keys($writes) as $path) {
        checkout002_lint_file($path);
    }

    $finalOrder = file_get_contents($orderPath);
    $finalSender = file_get_contents($senderPath);
    $finalTelegram = file_get_contents($telegramPath);
    if ($finalOrder === false || $finalSender === false || $finalTelegram === false
        || checkout002_count($finalOrder, CHECKOUT002_MARKER) !== 1
        || checkout002_count($finalSender, CHECKOUT002_MARKER) !== 2
        || checkout002_count($finalTelegram, CHECKOUT002_MARKER) !== 2
        || !is_file($queuePath) || !is_file($workerPath)) {
        throw new RuntimeException('post_write_invariant_failed');
    }
} catch (Throwable $e) {
    foreach ($backups as $target => $backup) {
        if (is_file($backup)) {
            @copy($backup, $target);
        }
    }
    @unlink($queuePath);
    @unlink($workerPath);
    checkout002_fail('write_or_lint_failed rollback=restored reason=' . $e->getMessage());
}

echo 'cwd=' . $root . PHP_EOL;
echo 'time_utc=' . gmdate('c') . PHP_EOL;
echo 'backup_dir=' . $backupDir . PHP_EOL;
echo 'changed_file=catalog/model/checkout/order.php' . PHP_EOL;
echo 'changed_file=system/library/booster_crm_sync.php' . PHP_EOL;
echo 'changed_file=extension/telegram_notify/catalog/controller/event/telegram.php' . PHP_EOL;
echo 'created_file=system/library/booster_async_queue.php' . PHP_EOL;
echo 'created_file=system/library/booster_async_queue_worker.php' . PHP_EOL;
echo 'done=ok' . PHP_EOL;
@unlink(__FILE__);
