import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..');
const sourceRoot = path.join(root, 'work', 'pay003-admin-order', 'source');
const candidateRoot = path.join(root, 'work', 'pay003-admin-order', 'candidate');
const sourceController = fs.readFileSync(path.join(sourceRoot, 'extension', 'pumb_credit', 'admin', 'controller', 'payment', 'pumb_credit.php'));
const sourceTwig = fs.readFileSync(path.join(sourceRoot, 'adminEvhenii', 'view', 'template', 'sale', 'order_info.twig'));
const candidateController = fs.readFileSync(path.join(candidateRoot, 'extension', 'pumb_credit', 'admin', 'controller', 'payment', 'pumb_credit.php'));
const candidateTwig = fs.readFileSync(path.join(candidateRoot, 'admin-view', 'order_info.twig'));

const sha = (value) => crypto.createHash('sha256').update(value).digest('hex');
const b64 = (value) => value.toString('base64');

const runner = `<?php
/**
 * PAY-003: owner-facing PUMB operations panel on the OpenCart order page.
 *
 * Patch-time DB writes: none. Patch-time bank calls: none.
 * Runtime bank calls occur only after an authorised admin explicitly clicks
 * refresh, shipment confirmation, or full refund. Runtime refresh may update
 * the existing pumb_credit_transaction row with a validated bank state.
 *
 * Rollback: run rollback.php from the printed backup directory, then clear the
 * OpenCart template cache. Rollback removes the panel/code only; it cannot undo
 * bank actions or runtime transaction-state updates already requested by owner.
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-003_pumb-admin-order-panel_20260901';
const MARKER_REL = 'extension/pumb_credit/.pay003-admin-order-panel-marker';

function line(string $message): void { echo $message . PHP_EOL; }
function stop(string $message): void { fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL); exit(1); }
function sha_file_safe(string $file): string { $hash = is_file($file) ? hash_file('sha256', $file) : false; return is_string($hash) ? $hash : ''; }
function write_atomic(string $target, string $content): void {
    $temporary = $target . '.pay003-' . bin2hex(random_bytes(4)) . '.tmp';
    if (file_put_contents($temporary, $content, LOCK_EX) !== strlen($content)) { @unlink($temporary); throw new RuntimeException('temporary write failed: ' . basename($target)); }
    if (!@rename($temporary, $target)) { @unlink($temporary); throw new RuntimeException('atomic replace failed: ' . basename($target)); }
}
function lint_php(string $file): bool {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1';
    exec($command, $output, $code);
    return $code === 0;
}

$root = getcwd();
if (!is_string($root) || !is_file($root . '/config.php')) stop('run from the OpenCart public_html directory');

$adminMatches = [];
foreach (glob($root . '/*/view/template/sale/order_info.twig') ?: [] as $candidate) {
    $adminRoot = dirname($candidate, 4);
    if (is_file($adminRoot . '/controller/sale/order.php') && is_file($adminRoot . '/model/sale/order.php')) $adminMatches[] = $candidate;
}
if (count($adminMatches) !== 1) stop('expected exactly one admin order_info.twig, found ' . count($adminMatches));

$adminTwig = $adminMatches[0];
$adminRel = ltrim(str_replace('\\\\', '/', substr($adminTwig, strlen($root))), '/');
$controllerRel = 'extension/pumb_credit/admin/controller/payment/pumb_credit.php';
$marker = $root . '/' . MARKER_REL;

$controllerContent = base64_decode('${b64(candidateController)}', true);
$twigContent = base64_decode('${b64(candidateTwig)}', true);
if (!is_string($controllerContent) || !is_string($twigContent)) stop('embedded candidate decode failed');

$targets = [
    $controllerRel => [
        'path' => $root . '/' . $controllerRel,
        'before' => '${sha(sourceController)}',
        'after' => '${sha(candidateController)}',
        'content' => $controllerContent,
        'php' => true,
    ],
    $adminRel => [
        'path' => $adminTwig,
        'before' => '${sha(sourceTwig)}',
        'after' => '${sha(candidateTwig)}',
        'content' => $twigContent,
        'php' => false,
    ],
];

$allAfter = true;
foreach ($targets as $target) if (sha_file_safe($target['path']) !== $target['after']) $allAfter = false;
if ($allAfter && is_file($marker)) {
    line('already_applied=yes');
    line('database_touched=no');
    line('bank_calls=no');
    $deleted = @unlink(__FILE__);
    line('self_delete=' . ($deleted ? 'ok' : 'failed'));
    exit($deleted ? 0 : 1);
}

foreach ($targets as $rel => $target) {
    if (!is_file($target['path'])) stop('required target missing: ' . $rel);
    $actual = sha_file_safe($target['path']);
    if ($actual !== $target['before']) stop('source SHA256 mismatch for ' . $rel . ' expected=' . $target['before'] . ' actual=' . $actual);
}

if (substr_count($controllerContent, 'public function orderPanel(): void') !== 1
    || substr_count($controllerContent, 'public function refreshOrderStatus(): void') !== 1
    || substr_count($controllerContent, 'public function confirmOrderShipment(): void') !== 1
    || substr_count($controllerContent, 'public function refundOrder(): void') !== 1
    || substr_count($controllerContent, "state !== 'WAITING_STORE_CONFIRM'") !== 1
    || substr_count($controllerContent, "state !== 'FUNDED'") !== 1
    || substr_count($controllerContent, 'environmentMatches') < 4
    || substr_count($twigContent, 'id="pay003-admin-card"') !== 1
    || substr_count($twigContent, 'payment/pumb_credit.refreshOrderStatus') !== 1
    || substr_count($twigContent, 'payment/pumb_credit.confirmOrderShipment') !== 1
    || substr_count($twigContent, 'payment/pumb_credit.refundOrder') !== 1) {
    stop('candidate assertions failed');
}

$lintCandidate = tempnam(sys_get_temp_dir(), 'pay003-admin-');
if (!is_string($lintCandidate) || file_put_contents($lintCandidate, $controllerContent) !== strlen($controllerContent)) stop('unable to create lint candidate');
$candidateLintOk = lint_php($lintCandidate);
@unlink($lintCandidate);
if (!$candidateLintOk) stop('candidate PHP lint failed before writes');
line('candidate_sha256=' . $controllerRel . ':' . hash('sha256', $controllerContent));
line('candidate_sha256=' . $adminRel . ':' . hash('sha256', $twigContent));

$timestamp = date('Ymd-His');
$backupRel = '_patch_backups/' . PATCH_ID . '-' . $timestamp . '-' . bin2hex(random_bytes(3));
$backup = $root . '/' . $backupRel;
foreach (['original', 'generated'] as $folder) if (!@mkdir($backup . '/' . $folder, 0755, true) && !is_dir($backup . '/' . $folder)) stop('backup directory create failed');

$manifest = [];
foreach ($targets as $rel => $target) {
    foreach (['original' => null, 'generated' => $target['content']] as $kind => $content) {
        $destination = $backup . '/' . $kind . '/' . $rel;
        if (!@mkdir(dirname($destination), 0755, true) && !is_dir(dirname($destination))) stop('backup subdirectory create failed');
        $ok = $kind === 'original' ? @copy($target['path'], $destination) : file_put_contents($destination, $content, LOCK_EX) === strlen($content);
        if (!$ok) stop('backup write failed: ' . $kind . '/' . $rel);
    }
    $manifest[] = ['path' => $rel, 'before' => $target['before'], 'after' => $target['after'], 'php' => $target['php']];
}
if (file_put_contents($backup . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) stop('manifest write failed');

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
@unlink($root . '/extension/pumb_credit/.pay003-admin-order-panel-marker');
echo "rollback=ok\ncache_clear_required=yes\n";
ROLLBACK;
if (file_put_contents($backup . '/rollback.php', $rollback, LOCK_EX) === false || !lint_php($backup . '/rollback.php')) stop('rollback artifact failed');

line('cwd=' . $root);
line('time=' . date(DATE_ATOM));
line('backup=' . $backup);

$written = [];
try {
    foreach ($targets as $rel => $target) {
        write_atomic($target['path'], $target['content']);
        $written[] = $rel;
    }
    if (!lint_php($targets[$controllerRel]['path'])) throw new RuntimeException('live PHP lint failed');
    foreach ($targets as $rel => $target) {
        $actual = sha_file_safe($target['path']);
        if ($actual !== $target['after']) throw new RuntimeException('after SHA256 mismatch for ' . $rel);
    }
    if (file_put_contents($marker, PATCH_ID . PHP_EOL, LOCK_EX) === false) throw new RuntimeException('marker write failed');
} catch (Throwable $exception) {
    foreach ($written as $rel) @copy($backup . '/original/' . $rel, $targets[$rel]['path']);
    @unlink($marker);
    stop('source restored: ' . $exception->getMessage());
}

line('php_l=ok file=' . $controllerRel);
foreach ($targets as $rel => $target) line('after_sha256=' . $rel . ':' . sha_file_safe($target['path']));
line('changed=' . implode(',', array_keys($targets)));
line('assertions=ok');
line('database_touched=no');
line('bank_calls=no');
line('runtime_bank_calls=explicit_admin_actions_only');
line('done=ok');
$deleted = @unlink(__FILE__);
line('self_delete=' . ($deleted ? 'ok' : 'failed'));
exit($deleted ? 0 : 1);
`;

const output = path.join(root, 'patches', 'PAY-003_pumb-admin-order-panel_20260901.php');
fs.writeFileSync(output, runner, 'utf8');
console.log(`built=${output}`);
console.log(`sha256=${sha(Buffer.from(runner))}`);
