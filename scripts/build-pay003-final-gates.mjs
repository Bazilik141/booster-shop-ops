import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..');
const candidateRoot = path.join(root, 'work', 'pay003-final-gates', 'candidate');
const specs = [
  {
    rel: 'extension/pumb_credit/catalog/controller/payment/pumb_credit.php',
    source: path.join(root, 'work', 'pay003-button-hotfix-fixture', 'extension', 'pumb_credit', 'catalog', 'controller', 'payment', 'pumb_credit.php'),
    candidate: path.join(candidateRoot, 'extension', 'pumb_credit', 'catalog', 'controller', 'payment', 'pumb_credit.php'),
    php: true,
  },
  {
    rel: 'extension/pumb_credit/admin/controller/payment/pumb_credit.php',
    source: path.join(root, 'work', 'pay003-admin-order', 'candidate', 'extension', 'pumb_credit', 'admin', 'controller', 'payment', 'pumb_credit.php'),
    candidate: path.join(candidateRoot, 'extension', 'pumb_credit', 'admin', 'controller', 'payment', 'pumb_credit.php'),
    php: true,
  },
  {
    rel: '__ADMIN_TWIG__',
    source: path.join(root, 'work', 'pay003-admin-order', 'candidate', 'admin-view', 'order_info.twig'),
    candidate: path.join(candidateRoot, 'admin-view', 'order_info.twig'),
    php: false,
  },
  {
    rel: '__ADMIN_MODEL__',
    source: path.join(root, 'work', 'pay003-admin-order', 'source', 'adminEvhenii', 'model', 'sale', 'order.php'),
    candidate: path.join(candidateRoot, 'admin-model', 'order.php'),
    php: true,
  },
  {
    rel: 'catalog/view/template/checkout/credit.twig',
    source: path.join(root, 'work', 'pay003', 'candidate', 'catalog', 'view', 'template', 'checkout', 'credit.twig'),
    candidate: path.join(candidateRoot, 'catalog', 'view', 'template', 'checkout', 'credit.twig'),
    php: false,
  },
  {
    rel: 'catalog/view/javascript/pay003-credit.js',
    source: path.join(root, 'work', 'pay003', 'candidate', 'catalog', 'view', 'javascript', 'pay003-credit.js'),
    candidate: path.join(candidateRoot, 'catalog', 'view', 'javascript', 'pay003-credit.js'),
    php: false,
  },
].map((entry) => ({
  ...entry,
  before: crypto.createHash('sha256').update(fs.readFileSync(entry.source)).digest('hex'),
  after: crypto.createHash('sha256').update(fs.readFileSync(entry.candidate)).digest('hex'),
  content: fs.readFileSync(entry.candidate).toString('base64'),
}));

const encodedSpecs = Buffer.from(JSON.stringify(specs)).toString('base64');
const runner = `<?php
/**
 * PAY-003 final operational gates for PUMB installment payments.
 *
 * Patch-time bank calls: none. Patch-time database writes: none.
 * Runtime bank calls occur only after an authorised admin explicitly clicks
 * refresh, cancellation, shipment confirmation, or full refund.
 *
 * Rollback: run rollback.php from the printed backup directory, then clear
 * OpenCart caches. Rollback cannot undo bank actions already requested.
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-003_pumb-final-gates_20260901';
const MARKER_REL = 'extension/pumb_credit/.pay003-final-gates-marker';

function out(string $message): void { echo $message . PHP_EOL; }
function fail(string $message): void { fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL); exit(1); }
function file_hash(string $file): string { $hash = is_file($file) ? hash_file('sha256', $file) : false; return is_string($hash) ? $hash : ''; }
function atomic_write(string $target, string $content): void {
    $temporary = $target . '.pay003-' . bin2hex(random_bytes(4)) . '.tmp';
    if (file_put_contents($temporary, $content, LOCK_EX) !== strlen($content)) { @unlink($temporary); throw new RuntimeException('temporary write failed: ' . basename($target)); }
    if (!@rename($temporary, $target)) { @unlink($temporary); throw new RuntimeException('atomic replace failed: ' . basename($target)); }
}
function lint_php(string $file): bool {
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    return $code === 0;
}

if (PHP_SAPI !== 'cli' || PHP_VERSION_ID < 80000) fail('CLI PHP 8.0+ required');
$root = getcwd();
if (!is_string($root) || !is_file($root . '/config.php') || !is_dir($root . '/catalog') || is_dir($root . '/.git')) fail('run uploaded copy from public_html only');

$adminMatches = [];
foreach (glob($root . '/*/view/template/sale/order_info.twig') ?: [] as $candidate) {
    $adminRoot = dirname($candidate, 4);
    if (is_file($adminRoot . '/controller/sale/order.php') && is_file($adminRoot . '/model/sale/order.php')) $adminMatches[] = $candidate;
}
if (count($adminMatches) !== 1) fail('expected exactly one admin order_info.twig, found ' . count($adminMatches));
$adminTwig = $adminMatches[0];
$adminRoot = dirname($adminTwig, 4);
$adminModel = $adminRoot . '/model/sale/order.php';
$adminRel = ltrim(str_replace('\\\\', '/', substr($adminTwig, strlen($root))), '/');
$adminModelRel = ltrim(str_replace('\\\\', '/', substr($adminModel, strlen($root))), '/');

$decoded = base64_decode('${encodedSpecs}', true);
$specs = is_string($decoded) ? json_decode($decoded, true) : null;
if (!is_array($specs)) fail('embedded specification decode failed');
$targets = [];
foreach ($specs as $spec) {
    $rel = $spec['rel'] === '__ADMIN_TWIG__' ? $adminRel : ($spec['rel'] === '__ADMIN_MODEL__' ? $adminModelRel : (string)$spec['rel']);
    if (!preg_match('~^(?:extension|catalog|[A-Za-z0-9_-]+/(?:view|model))/[A-Za-z0-9_./-]+$~D', $rel) || strpos($rel, '..') !== false) fail('unsafe target path');
    $content = base64_decode((string)$spec['content'], true);
    if (!is_string($content)) fail('candidate decode failed: ' . $rel);
    $targets[$rel] = ['path' => $root . '/' . $rel, 'before' => (string)$spec['before'], 'after' => (string)$spec['after'], 'content' => $content, 'php' => !empty($spec['php'])];
}

$allAfter = true;
foreach ($targets as $target) if (file_hash($target['path']) !== $target['after']) $allAfter = false;
if ($allAfter && is_file($root . '/' . MARKER_REL)) {
    out('already_applied=yes');
    out('final_preflight=code_contract_ok');
    out('database_touched=no');
    out('bank_calls=no');
    $deleted = @unlink(__FILE__);
    out('self_delete=' . ($deleted ? 'ok' : 'failed'));
    exit($deleted ? 0 : 1);
}

foreach ($targets as $rel => $target) {
    if (!is_file($target['path'])) fail('required target missing: ' . $rel);
    $actual = file_hash($target['path']);
    if ($actual !== $target['before']) fail('source SHA256 mismatch for ' . $rel . ' expected=' . $target['before'] . ' actual=' . $actual);
}

$catalog = $targets['extension/pumb_credit/catalog/controller/payment/pumb_credit.php']['content'];
$admin = $targets['extension/pumb_credit/admin/controller/payment/pumb_credit.php']['content'];
$twig = $targets[$adminRel]['content'];
$orderModel = $targets[$adminModelRel]['content'];
$creditTwig = $targets['catalog/view/template/checkout/credit.twig']['content'];
$creditJs = $targets['catalog/view/javascript/pay003-credit.js']['content'];
if (substr_count($catalog, base64_decode('${Buffer.from("applyOrderStatus($orderId, 'CREATE_FAILED', false)").toString('base64')}', true)) !== 1
    || substr_count($catalog, "['CREATE_FAILED','CANCELED_BY_CLIENT'") !== 1
    || substr_count($admin, 'public function cancelOrderApplication(): void') !== 1
    || substr_count($admin, "['IN_PROGRESS', 'WAITING_CLIENT', 'WAITING_STORE_CONFIRM']") !== 2
    || substr_count($admin, "'cancel_reason' => 'CancelLead50'") < 2
    || substr_count($admin, base64_decode('${Buffer.from("'flow_id' => $flowId").toString('base64')}', true)) !== 3
    || substr_count($twig, 'data-cancel-url=') !== 1
    || substr_count($twig, 'id="pay003-admin-cancel"') !== 1
    || substr_count($twig, base64_decode('${Buffer.from("$cancel.on('click'").toString('base64')}', true)) !== 1
    || substr_count($orderModel, "'CREATE_FAILED'" ) !== 2
    || substr_count($orderModel, 'pumb_credit_transaction') !== 2
    || substr_count($creditTwig, 'Сплата частинами') !== 1
    || strpos($creditTwig, 'id="pay003-refresh"') !== false
    || strpos($creditJs, "getElementById('pay003-refresh')") !== false
    || strpos($creditJs, 'schedule(5000)') === false
    || strpos($creditJs, 'visibilitychange') === false) fail('candidate assertions failed');

foreach ($targets as $rel => $target) if ($target['php']) {
    $temporary = tempnam(sys_get_temp_dir(), 'pay003-final-');
    if (!is_string($temporary) || file_put_contents($temporary, $target['content']) !== strlen($target['content'])) fail('unable to create PHP lint candidate');
    $ok = lint_php($temporary); @unlink($temporary);
    if (!$ok) fail('candidate PHP lint failed: ' . $rel);
}
foreach ($targets as $rel => $target) out('candidate_sha256=' . $rel . ':' . $target['after']);

$timestamp = date('Ymd-His');
$backupRel = '_patch_backups/' . PATCH_ID . '-' . $timestamp . '-' . bin2hex(random_bytes(3));
$backup = $root . '/' . $backupRel;
foreach (['original', 'generated'] as $folder) if (!@mkdir($backup . '/' . $folder, 0755, true) && !is_dir($backup . '/' . $folder)) fail('backup directory create failed');

$manifest = [];
foreach ($targets as $rel => $target) {
    foreach (['original' => null, 'generated' => $target['content']] as $kind => $content) {
        $destination = $backup . '/' . $kind . '/' . $rel;
        if (!@mkdir(dirname($destination), 0755, true) && !is_dir(dirname($destination))) fail('backup subdirectory create failed');
        $ok = $kind === 'original' ? @copy($target['path'], $destination) : file_put_contents($destination, $content, LOCK_EX) === strlen($content);
        if (!$ok) fail('backup write failed: ' . $kind . '/' . $rel);
    }
    $manifest[] = ['path' => $rel, 'before' => $target['before'], 'after' => $target['after'], 'php' => $target['php']];
}
if (file_put_contents($backup . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) fail('manifest write failed');

$rollback = <<<'ROLLBACK'
<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
$manifest = json_decode((string)file_get_contents(__DIR__ . '/manifest.json'), true);
if (!is_array($manifest)) { fwrite(STDERR, "ERROR: invalid manifest\n"); exit(1); }
foreach ($manifest as $entry) {
    $target = $root . '/' . $entry['path'];
    $actual = is_file($target) ? hash_file('sha256', $target) : false;
    if (!is_string($actual) || !hash_equals((string)$entry['after'], $actual)) { fwrite(STDERR, "ERROR: rollback target drift: " . $entry['path'] . "\n"); exit(1); }
    $original = __DIR__ . '/original/' . $entry['path'];
    if (!is_file($original) || !hash_equals((string)$entry['before'], (string)hash_file('sha256', $original))) { fwrite(STDERR, "ERROR: invalid original backup: " . $entry['path'] . "\n"); exit(1); }
    if (!empty($entry['php'])) { exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($original) . ' 2>&1', $output, $code); if ($code !== 0) { fwrite(STDERR, "ERROR: backup PHP lint failed\n"); exit(1); } }
}
foreach ($manifest as $entry) {
    $target = $root . '/' . $entry['path'];
    $temporary = $target . '.rollback-' . bin2hex(random_bytes(3)) . '.tmp';
    if (!copy(__DIR__ . '/original/' . $entry['path'], $temporary) || !rename($temporary, $target)) { @unlink($temporary); fwrite(STDERR, "ERROR: rollback write failed: " . $entry['path'] . "\n"); exit(1); }
}
@unlink($root . '/extension/pumb_credit/.pay003-final-gates-marker');
echo "rollback=ok\ncache_clear_required=yes\n";
ROLLBACK;
if (file_put_contents($backup . '/rollback.php', $rollback, LOCK_EX) === false || !lint_php($backup . '/rollback.php')) fail('rollback artifact failed');

out('cwd=' . $root);
out('time=' . date(DATE_ATOM));
out('backup=' . $backup);
$written = [];
try {
    foreach ($targets as $rel => $target) {
        atomic_write($target['path'], $target['content']);
        $written[] = $rel;
        if ($target['php'] && !lint_php($target['path'])) throw new RuntimeException('live PHP lint failed: ' . $rel);
    }
    foreach ($targets as $rel => $target) if (file_hash($target['path']) !== $target['after']) throw new RuntimeException('after SHA256 mismatch: ' . $rel);
    if (file_put_contents($root . '/' . MARKER_REL, PATCH_ID . PHP_EOL, LOCK_EX) === false) throw new RuntimeException('marker write failed');
} catch (Throwable $exception) {
    foreach ($written as $rel) @copy($backup . '/original/' . $rel, $targets[$rel]['path']);
    @unlink($root . '/' . MARKER_REL);
    fail('source restored: ' . $exception->getMessage());
}

foreach ($targets as $rel => $target) {
    if ($target['php']) out('php_l=ok file=' . $rel);
    out('after_sha256=' . $rel . ':' . file_hash($target['path']));
}
out('changed=' . implode(',', array_keys($targets)));
out('assertions=ok');
out('final_preflight=code_contract_ok_owner_runtime_qa_required');
out('database_touched=no');
out('bank_calls=no');
out('runtime_bank_calls=explicit_admin_actions_and_existing_background_poll_only');
out('done=ok');
$deleted = @unlink(__FILE__);
out('self_delete=' . ($deleted ? 'ok' : 'failed'));
exit($deleted ? 0 : 1);
`;

const output = path.join(root, 'patches', 'PAY-003_pumb-final-gates_20260901.php');
fs.writeFileSync(output, runner, 'utf8');
console.log(`built=${output}`);
console.log(`sha256=${crypto.createHash('sha256').update(runner).digest('hex')}`);
