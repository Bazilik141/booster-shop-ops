<?php
declare(strict_types=1);

const PATCH_ID = 'PAY-003_pumb-canonical-term_20260901';
const TARGET = 'extension/pumb_credit/catalog/controller/payment/pumb_credit.php';
const BEFORE_SHA256 = '8d3da024b269f0fe9579e03c0b9f701ed495b0d070008e7777fa7f2c1936e708';
const AFTER_SHA256 = '77c0cd2d37854b8822173401983160894f809cea29e31cf80e508e5bfc58b6ab';

const OLD_INDEX = <<<'PHP'
        $id = (int)($this->session->data['order_id'] ?? 0);
        $arguments = 'language=' . rawurlencode((string)$this->config->get('config_language'));
        $arguments .= '&term=' . (int)($this->session->data['pay002_pumb_credit_term'] ?? 3);
PHP;

const NEW_INDEX = <<<'PHP'
        $id = (int)($this->session->data['order_id'] ?? 0);
        $context = $this->model_checkout_credit->context($id);
        $term = $this->requestedTerm((array)($context['order'] ?? []));
        if ($term === null) return '';
        $arguments = 'language=' . rawurlencode((string)$this->config->get('config_language'));
        $arguments .= '&term=' . $term;
PHP;

const OLD_CALL = '        $term = $this->requestedTerm();';
const NEW_CALL = '        $term = $this->requestedTerm($order);';

const OLD_METHOD = <<<'PHP'
    private function requestedTerm(): ?int {
        $raw = $this->request->post['term'] ?? $this->request->get['term'] ?? null;
        if (!is_scalar($raw) || !preg_match('/^[0-9]{1,2}$/', (string)$raw)) return null;
        $term = (int)$raw;
        return in_array($term, $this->allowedTerms(), true) ? $term : null;
    }
PHP;

const NEW_METHOD = <<<'PHP'
    private function requestedTerm(array $order): ?int {
        $payment = $order['payment_method'] ?? [];
        if (is_string($payment)) $payment = json_decode($payment, true);
        $code = is_array($payment) ? (string)($payment['code'] ?? '') : '';
        if (!preg_match('/^pumb_credit\.pumb_credit_([0-9]{1,2})$/D', $code, $match)) return null;
        $term = (int)$match[1];
        return in_array($term, $this->allowedTerms(), true) ? $term : null;
    }
PHP;

function out(string $key, string $value): void { echo $key . '=' . $value . PHP_EOL; }
function fail_patch(string $message): void { fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL); exit(1); }
function exact_count(string $source, string $needle): int { return substr_count($source, $needle); }
function lint_php(string $path): array {
    $output = [];
    $status = 1;
    @exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $status);
    return [$status === 0, implode(' ', $output)];
}

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = getcwd();
$target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, TARGET);
out('cwd', $root);
out('time', gmdate('c'));

if (!is_file($target)) fail_patch('target_not_found:' . TARGET);
$source = file_get_contents($target);
if (!is_string($source)) fail_patch('target_read_failed');
$currentHash = hash('sha256', $source);

if ($currentHash === AFTER_SHA256 && exact_count($source, NEW_INDEX) === 1 && exact_count($source, NEW_CALL) === 1 && exact_count($source, NEW_METHOD) === 1) {
    out('already_applied', 'yes');
    out('database_touched', 'no');
    out('done', 'ok');
    @unlink(__FILE__);
    exit(0);
}

if ($currentHash !== BEFORE_SHA256) fail_patch('source_sha256_mismatch expected=' . BEFORE_SHA256 . ' actual=' . $currentHash);
foreach ([OLD_INDEX => 'index', OLD_CALL => 'call', OLD_METHOD => 'method'] as $anchor => $name) {
    if (exact_count($source, $anchor) !== 1) fail_patch($name . '_anchor_count=' . exact_count($source, $anchor));
}
foreach ([NEW_INDEX => 'new_index', NEW_CALL => 'new_call', NEW_METHOD => 'new_method'] as $anchor => $name) {
    if (exact_count($source, $anchor) !== 0) fail_patch($name . '_preexists');
}

$candidate = str_replace([OLD_INDEX, OLD_CALL, OLD_METHOD], [NEW_INDEX, NEW_CALL, NEW_METHOD], $source, $replacements);
if ($replacements !== 3) fail_patch('replacement_count=' . $replacements);
if (hash('sha256', $candidate) !== AFTER_SHA256) fail_patch('candidate_sha256_mismatch');

$temp = $target . '.pay003-term-candidate-' . bin2hex(random_bytes(4));
if (file_put_contents($temp, $candidate, LOCK_EX) !== strlen($candidate)) { @unlink($temp); fail_patch('candidate_write_failed'); }
[$candidateOk, $candidateLint] = lint_php($temp);
if (!$candidateOk) { @unlink($temp); fail_patch('candidate_php_l_failed:' . $candidateLint); }
out('candidate_sha256', hash_file('sha256', $temp));

$backupDir = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . PATCH_ID . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
$backupTarget = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, TARGET);
if (!is_dir(dirname($backupTarget)) && !mkdir(dirname($backupTarget), 0755, true)) { @unlink($temp); fail_patch('backup_directory_create_failed'); }
if (!copy($target, $backupTarget) || hash_file('sha256', $backupTarget) !== BEFORE_SHA256) { @unlink($temp); fail_patch('backup_failed'); }
out('backup', $backupDir);

$written = false;
try {
    if (!rename($temp, $target)) fail_patch('target_replace_failed');
    $written = true;
    [$targetOk, $targetLint] = lint_php($target);
    if (!$targetOk) throw new RuntimeException('target_php_l_failed:' . $targetLint);
    if (hash_file('sha256', $target) !== AFTER_SHA256) throw new RuntimeException('after_sha256_mismatch');
    $after = file_get_contents($target);
    if (!is_string($after) || exact_count($after, NEW_INDEX) !== 1 || exact_count($after, NEW_CALL) !== 1 || exact_count($after, NEW_METHOD) !== 1) throw new RuntimeException('after_assertion_failed');

    out('php_l', 'ok');
    out('after_sha256', TARGET . ':' . AFTER_SHA256);
    out('changed', TARGET);
    out('assertions', 'ok');
    out('database_touched', 'no');
    out('done', 'ok');
    out('self_delete', @unlink(__FILE__) ? 'ok' : 'failed');
} catch (Throwable $exception) {
    @unlink($temp);
    if ($written && is_file($backupTarget)) @copy($backupTarget, $target);
    fail_patch('source_restored:' . $exception->getMessage());
}
