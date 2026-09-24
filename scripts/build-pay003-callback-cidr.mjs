import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..');
const rel = 'extension/pumb_credit/catalog/controller/payment/pumb_credit.php';
const source = fs.readFileSync(path.join(root, 'work', 'pay003-final-gates', 'candidate', rel));
const candidate = fs.readFileSync(path.join(root, 'work', 'pay003-callback-cidr', 'candidate', rel));
const sha = (value) => crypto.createHash('sha256').update(value).digest('hex');
const sourceHash = sha(source);
const candidateHash = sha(candidate);
const content = candidate.toString('base64');

const runner = `<?php
/**
 * PAY-003: add exact-IP and IPv4/IPv6 CIDR matching for PUMB callbacks.
 *
 * Patch-time database writes: none. Patch-time bank calls: none.
 * Settings are not changed; PROD cutover remains a separate owner action.
 * Rollback: run rollback.php from the printed backup directory and clear cache.
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-003_pumb-callback-cidr_20260901';
const TARGET_REL = '${rel}';
const MARKER_REL = 'extension/pumb_credit/.pay003-callback-cidr-marker';
const BEFORE_SHA256 = '${sourceHash}';
const AFTER_SHA256 = '${candidateHash}';

function out(string $message): void { echo $message . PHP_EOL; }
function fail(string $message): void { fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL); exit(1); }
function file_hash(string $file): string { $hash = is_file($file) ? hash_file('sha256', $file) : false; return is_string($hash) ? $hash : ''; }
function lint_php(string $file): bool {
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    return $code === 0;
}
function atomic_write(string $target, string $content): void {
    $temporary = $target . '.pay003-' . bin2hex(random_bytes(4)) . '.tmp';
    if (file_put_contents($temporary, $content, LOCK_EX) !== strlen($content)) { @unlink($temporary); throw new RuntimeException('temporary write failed'); }
    if (!@rename($temporary, $target)) { @unlink($temporary); throw new RuntimeException('atomic replace failed'); }
}

if (PHP_SAPI !== 'cli' || PHP_VERSION_ID < 80000) fail('CLI PHP 8.0+ required');
$root = getcwd();
if (!is_string($root) || !is_file($root . '/config.php') || !is_dir($root . '/catalog') || is_dir($root . '/.git')) fail('run uploaded copy from public_html only');
$target = $root . '/' . TARGET_REL;
$marker = $root . '/' . MARKER_REL;
$candidate = base64_decode('${content}', true);
if (!is_string($candidate)) fail('embedded candidate decode failed');

$actual = file_hash($target);
if ($actual === AFTER_SHA256 && is_file($marker)) {
    out('already_applied=yes');
    out('database_touched=no');
    out('bank_calls=no');
    $deleted = @unlink(__FILE__);
    out('self_delete=' . ($deleted ? 'ok' : 'failed'));
    exit($deleted ? 0 : 1);
}
if (!is_file($target)) fail('required target missing: ' . TARGET_REL);
if ($actual !== BEFORE_SHA256) fail('source SHA256 mismatch expected=' . BEFORE_SHA256 . ' actual=' . $actual);
if (hash('sha256', $candidate) !== AFTER_SHA256
    || substr_count($candidate, 'private function ipMatches(string $remote, string $allowed): bool') !== 1
    || substr_count($candidate, 'inet_pton') !== 3
    || substr_count($candidate, 'hash_equals') < 4
    || substr_count($candidate, "['REMOTE_ADDR']") !== 1
    || strpos($candidate, 'HTTP_X_FORWARDED_FOR') !== false
    || substr_count($candidate, '$prefix > $maximum') !== 1
    || substr_count($candidate, '$remainingBits === 0') !== 1) fail('candidate assertions failed');

$temporary = tempnam(sys_get_temp_dir(), 'pay003-cidr-');
if (!is_string($temporary) || file_put_contents($temporary, $candidate) !== strlen($candidate)) fail('unable to create lint candidate');
$lintOk = lint_php($temporary); @unlink($temporary);
if (!$lintOk) fail('candidate PHP lint failed before writes');
out('candidate_sha256=' . TARGET_REL . ':' . AFTER_SHA256);

$timestamp = date('Ymd-His');
$backupRel = '_patch_backups/' . PATCH_ID . '-' . $timestamp . '-' . bin2hex(random_bytes(3));
$backup = $root . '/' . $backupRel;
foreach (['original/' . dirname(TARGET_REL), 'generated/' . dirname(TARGET_REL)] as $folder) if (!@mkdir($backup . '/' . $folder, 0755, true) && !is_dir($backup . '/' . $folder)) fail('backup directory create failed');
if (!@copy($target, $backup . '/original/' . TARGET_REL)) fail('original backup failed');
if (file_put_contents($backup . '/generated/' . TARGET_REL, $candidate, LOCK_EX) !== strlen($candidate)) fail('generated backup failed');

$manifest = [['path' => TARGET_REL, 'before' => BEFORE_SHA256, 'after' => AFTER_SHA256, 'php' => true]];
if (file_put_contents($backup . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) fail('manifest write failed');
$rollback = <<<'ROLLBACK'
<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
$manifest = json_decode((string)file_get_contents(__DIR__ . '/manifest.json'), true);
$entry = is_array($manifest) ? ($manifest[0] ?? null) : null;
if (!is_array($entry)) { fwrite(STDERR, "ERROR: invalid manifest\n"); exit(1); }
$target = $root . '/' . $entry['path'];
$original = __DIR__ . '/original/' . $entry['path'];
if (!is_file($target) || !hash_equals((string)$entry['after'], (string)hash_file('sha256', $target))) { fwrite(STDERR, "ERROR: rollback target drift\n"); exit(1); }
if (!is_file($original) || !hash_equals((string)$entry['before'], (string)hash_file('sha256', $original))) { fwrite(STDERR, "ERROR: invalid original backup\n"); exit(1); }
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($original) . ' 2>&1', $output, $code);
if ($code !== 0) { fwrite(STDERR, "ERROR: backup PHP lint failed\n"); exit(1); }
$temporary = $target . '.rollback-' . bin2hex(random_bytes(3)) . '.tmp';
if (!copy($original, $temporary) || !rename($temporary, $target)) { @unlink($temporary); fwrite(STDERR, "ERROR: rollback write failed\n"); exit(1); }
@unlink($root . '/extension/pumb_credit/.pay003-callback-cidr-marker');
echo "rollback=ok\ncache_clear_required=yes\n";
ROLLBACK;
if (file_put_contents($backup . '/rollback.php', $rollback, LOCK_EX) === false || !lint_php($backup . '/rollback.php')) fail('rollback artifact failed');

out('cwd=' . $root);
out('time=' . date(DATE_ATOM));
out('backup=' . $backup);
try {
    atomic_write($target, $candidate);
    if (!lint_php($target)) throw new RuntimeException('live PHP lint failed');
    if (file_hash($target) !== AFTER_SHA256) throw new RuntimeException('after SHA256 mismatch');
    if (file_put_contents($marker, PATCH_ID . PHP_EOL, LOCK_EX) === false) throw new RuntimeException('marker write failed');
} catch (Throwable $exception) {
    @copy($backup . '/original/' . TARGET_REL, $target);
    @unlink($marker);
    fail('source restored: ' . $exception->getMessage());
}

out('php_l=ok file=' . TARGET_REL);
out('after_sha256=' . TARGET_REL . ':' . file_hash($target));
out('changed=' . TARGET_REL);
out('assertions=ok');
out('database_touched=no');
out('bank_calls=no');
out('settings_changed=no');
out('runtime_callback_ip_policy=exact_ip_or_ipv4_ipv6_cidr');
out('done=ok');
$deleted = @unlink(__FILE__);
out('self_delete=' . ($deleted ? 'ok' : 'failed'));
exit($deleted ? 0 : 1);
`;

const output = path.join(root, 'patches', 'PAY-003_pumb-callback-cidr_20260901.php');
fs.writeFileSync(output, runner, 'utf8');
console.log(`built=${output}`);
console.log(`sha256=${sha(Buffer.from(runner))}`);
