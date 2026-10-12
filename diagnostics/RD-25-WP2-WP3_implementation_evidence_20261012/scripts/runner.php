<?php
/**
 * @TASK@: @DESCRIPTION@
 * Approved Phase 0 20261012; review C1-C3 and owner go WP2-WP3.
 * Source: RD-25-WP2-WP3-check-20261012-094705-34448.tar.gz + fresh GA4 model.
 * Root causes: gallery square/grey and RD-10 repeated height caps; cart cover squares.
 * Edit original gallery / RD-12 mini-cart / RD-11 cart CSS, no override stack.
 * Reuses unchanged WP1 v4 mediaInfo/fit-v2 resizeFit, admission budget and LOCK_NB.
 * All GA4 anchors/counts pinned; commerce fields, controls, JSON-LD, popup/og unchanged.
 * No DB writes, new classifier, media-cache deletion, vendor or checkout-summary rewrite.
 * CLI PHP >=8.0. All targets/gates preflight; backups precede writes; atomic readback;
 * PHP candidate+target lint; failure restores originals. Repeat preserves later asset token.
 * Usage: php @FILENAME@ [--dry-run]
 * Rollback: re-upload SAME runner; php @FILENAME@ --rollback=_patch_backups/<printed-directory>
 * Rollback order WP3, then WP2. Each receipt refuses changed targets or mixed states.
 * Apply/repeat/rollback self-delete; dry-run/failure retain runner. Clear compiled Twig
 * cache after both applies or rollback (owner command in report); keep rd25 fit/metadata.
 * Owner QA: gallery at 1440/1024/390/360; bs-checkout-smoke steps 1-4, no order submit.
 */
declare(strict_types=1);
const RD25_TASK = '@TASK@';
const RD25_MARKER = '@MARKER@';
const RD25_TOKEN = '@TOKEN@';
const RD25_PREFIX = 'catalog/view/stylesheet/boostershop-ds.css?v=';
const RD25_HEADER = 'catalog/view/template/common/header.twig';
const RD25_CSS = 'catalog/view/stylesheet/boostershop-ds.css';

function out(string $key, string $value): void { echo $key . '=' . $value . PHP_EOL; }
function fail(string $message): void { throw new RuntimeException($message); }
function readFileSafe(string $path): string {
    $content = @file_get_contents($path);
    if ($content === false) { fail('file_read_failed:' . $path); }
    return $content;
}
function normalize(string $path, string $content): string {
    if ($path !== RD25_HEADER) { return $content; }
    $pattern = '~(' . preg_quote(RD25_PREFIX, '~') . ')([A-Za-z0-9._-]{1,64})(?=["\'])~';
    if (preg_match_all($pattern, $content) !== 1 || substr_count($content, RD25_PREFIX) !== 1) { fail('header_token_anchor_invalid'); }
    return preg_replace($pattern, '${1}__RD25_TOKEN__', $content, 1);
}
function mkdirSafe(string $path): void {
    if (!is_dir($path) && !@mkdir($path, 0755, true) && !is_dir($path)) { fail('mkdir_failed:' . $path); }
}
function writeAtomic(string $path, string $content): void {
    $mode = is_file($path) ? (fileperms($path) & 0777) : 0644;
    $temp = @tempnam(dirname($path), 'rd25-write-');
    if (!$temp) { fail('temp_failed:' . $path); }
    if (@file_put_contents($temp, $content, LOCK_EX) !== strlen($content) || !@chmod($temp, $mode) || !@rename($temp, $path)) {
        @unlink($temp); fail('file_write_failed:' . $path);
    }
}
function lint(string $path): void {
    if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') { return; }
    $output = []; $exit = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $exit);
    if ($exit !== 0) { fail('php_lint_failed:' . $path); }
    out('php_lint', 'ok:' . $path);
}
function finish(bool $delete): void {
    out('done', 'ok');
    if ($delete) { out('self_delete', @unlink(__FILE__) ? 'ok' : 'failed'); }
}
function checkAnchors(string $root, array $anchors, array $overrides = []): void {
    foreach ($anchors as $anchor) {
        $code = $overrides[$anchor['template']] ?? readFileSafe($root . '/' . $anchor['template']);
        if (substr_count($code, $anchor['search']) !== $anchor['count']) { fail('ga4_anchor_count:' . $anchor['template'] . ':' . $anchor['index']); }
    }
}
// Readable JSON stores exact replacement bytes and pinned SHA256 values; no external dependency.
$spec = json_decode(<<<'RD25_SPEC'
@SPEC@
RD25_SPEC, true, 512, JSON_THROW_ON_ERROR);
$targets = $spec['targets'];
$originals = []; $written = []; $backup = ''; $lock = null;
try {
    if (PHP_SAPI !== 'cli' || PHP_VERSION_ID < 80000 || !function_exists('exec')) { fail('requires_cli_php80_and_exec'); }
    lint(__FILE__);
    $root = str_replace('\\', '/', (string)realpath(getcwd()));
    out('task', RD25_TASK); out('cwd', $root); out('time', gmdate('c'));
    if (!is_file($root . '/catalog/model/tool/image.php')) { fail('run_from_public_html'); }
    $dry = false; $rollback = '';
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--dry-run') { $dry = true; }
        elseif (strpos($arg, '--rollback=') === 0) { $rollback = substr($arg, 11); }
        else { fail('unknown_argument'); }
    }
    if ($dry && $rollback !== '') { fail('rollback_dry_run_not_supported'); }
    $lock = @fopen(__FILE__, 'r');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { fail('another_runner_is_active'); }
    foreach ($targets as $relative => $target) {
        $path = $root . '/' . $relative;
        if (!is_file($path) || !is_writable($path) || !is_writable(dirname($path)) || str_replace('\\', '/', (string)realpath($path)) !== $path) { fail('target_missing_unwritable_or_symlink:' . $relative); }
        $originals[$relative] = readFileSafe($path);
    }
    if ($rollback !== '') {
        if (!preg_match('~\A_patch_backups/' . preg_quote(RD25_TASK, '~') . '-[0-9]{8}_[0-9]{6}-[a-f0-9]{6}\z~', $rollback)) { fail('rollback_path_invalid'); }
        $base = realpath($root . '/_patch_backups'); $folder = realpath($root . '/' . $rollback);
        if (!$base || !$folder || str_replace('\\', '/', $folder) !== $root . '/' . $rollback || is_link($folder)) { fail('rollback_outside_task_backup'); }
        $receipt = json_decode(readFileSafe($folder . '/manifest.json'), true);
        if (!is_array($receipt) || ($receipt['task'] ?? '') !== RD25_TASK || ($receipt['state'] ?? '') !== 'APPLIED' || array_keys($receipt['files'] ?? []) !== array_keys($targets)) { fail('rollback_manifest_invalid'); }
        $restore = [];
        foreach ($targets as $p => $target) {
            $restore[$p] = readFileSafe($folder . '/original/' . $p); $entry = $receipt['files'][$p];
            if (hash('sha256', $originals[$p]) !== ($entry['after_sha256'] ?? '') || hash('sha256', $restore[$p]) !== ($entry['before_sha256'] ?? '') || hash('sha256', normalize($p, $restore[$p])) !== $target['before_sha256']) { fail('rollback_source_changed:' . $p); }
            lint($folder . '/original/' . $p);
        }
        foreach ($restore as $p => $content) {
            $written[] = $p; writeAtomic($root . '/' . $p, $content); lint($root . '/' . $p);
            if (readFileSafe($root . '/' . $p) !== $content) { fail('rollback_readback_failed:' . $p); }
            out('restored_file', $p);
        }
        $receipt['state'] = 'ROLLED_BACK'; writeAtomic($folder . '/manifest.json', json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        flock($lock, LOCK_UN); fclose($lock); $lock = null; out('rollback', 'ok'); finish(true); exit(0);
    }
    foreach ($spec['context'] as $p => $hash) {
        if (!is_file($root . '/' . $p) || hash('sha256', readFileSafe($root . '/' . $p)) !== $hash) { fail('context_sha256_mismatch:' . $p); }
        out('context_file', $p);
    }
    checkAnchors($root, $spec['ga4_anchors']);
    $marked = [];
    foreach ($targets as $p => $target) {
        if ($p === RD25_HEADER) { continue; }
        $count = substr_count($originals[$p], RD25_MARKER);
        if ($count > 1) { fail('duplicate_marker:' . $p); }
        $marked[] = $count;
    }
    if (array_sum($marked) !== 0 && array_sum($marked) !== count($marked)) { fail('partial_or_mixed_markers'); }
    if (array_sum($marked) === count($marked)) {
        foreach ($targets as $p => $target) {
            if ($p === RD25_HEADER) {
                if (hash('sha256', normalize($p, $originals[$p])) !== $target['after_sha256']) { fail('applied_header_changed'); }
                continue;
            }
            if (!in_array(hash('sha256', $originals[$p]), $spec['repeat_hashes'][$p], true)) { fail('applied_content_changed:' . $p); }
        }
        // WP2 repeat after WP3 also verifies the other WP3 files, rejecting a partial later stage.
        if (isset($spec['later_css_hash']) && hash('sha256', $originals[RD25_CSS]) === $spec['later_css_hash']) {
            foreach ($spec['later_files'] as $p => $hash) {
                if (hash('sha256', readFileSafe($root . '/' . $p)) !== $hash) { fail('later_stage_partial:' . $p); }
            }
        }
        flock($lock, LOCK_UN); fclose($lock); $lock = null; out('already_applied', 'yes'); finish(!$dry); exit(0);
    }
    $candidates = [];
    foreach ($targets as $p => $target) {
        $code = normalize($p, $originals[$p]);
        if (hash('sha256', $code) !== $target['before_sha256']) { fail('source_sha256_mismatch:' . $p); }
        foreach ($target['replacements'] as $index => $replace) {
            if (substr_count($code, $replace['before']) !== 1) { fail('anchor_count_invalid:' . $p . ':' . $index); }
            $code = str_replace($replace['before'], $replace['after'], $code);
        }
        if (hash('sha256', $code) !== $target['after_sha256']) { fail('candidate_sha256_mismatch:' . $p); }
        $candidates[$p] = $p === RD25_HEADER ? str_replace('__RD25_TOKEN__', RD25_TOKEN, $code) : $code;
        out('preflight_file', $p);
    }
    checkAnchors($root, $spec['ga4_anchors'], $candidates);
    if ($dry) { flock($lock, LOCK_UN); fclose($lock); $lock = null; out('dry_run', 'yes'); out('target_writes', '0'); finish(false); exit(0); }
    mkdirSafe($root . '/_patch_backups');
    if (str_replace('\\', '/', (string)realpath($root . '/_patch_backups')) !== $root . '/_patch_backups') { fail('unsafe_backup_root'); }
    $backup = $root . '/_patch_backups/' . RD25_TASK . '-' . gmdate('Ymd_His') . '-' . bin2hex(random_bytes(3));
    $receipt = ['task' => RD25_TASK, 'state' => 'PREPARED', 'created_at' => gmdate('c'), 'files' => []];
    foreach ($targets as $p => $target) {
        mkdirSafe(dirname($backup . '/original/' . $p)); writeAtomic($backup . '/original/' . $p, $originals[$p]);
        if (readFileSafe($backup . '/original/' . $p) !== $originals[$p]) { fail('backup_verify_failed:' . $p); }
        mkdirSafe(dirname($backup . '/candidate/' . $p)); writeAtomic($backup . '/candidate/' . $p, $candidates[$p]); lint($backup . '/candidate/' . $p);
        $receipt['files'][$p] = ['before_sha256' => hash('sha256', $originals[$p]), 'after_sha256' => hash('sha256', $candidates[$p])];
    }
    writeAtomic($backup . '/manifest.json', json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"); out('backup', $backup);
    foreach ($originals as $p => $content) { if (readFileSafe($root . '/' . $p) !== $content) { fail('source_changed_after_preflight:' . $p); } }
    foreach ($candidates as $p => $content) {
        $written[] = $p; writeAtomic($root . '/' . $p, $content); lint($root . '/' . $p);
        if (readFileSafe($root . '/' . $p) !== $content) { fail('write_readback_failed:' . $p); }
        out('changed_file', $p);
    }
    $receipt['state'] = 'APPLIED'; writeAtomic($backup . '/manifest.json', json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    flock($lock, LOCK_UN); fclose($lock); $lock = null; out('changed_files', (string)count($targets)); finish(true);
} catch (Throwable $error) {
    $failed = false;
    foreach (array_reverse(array_unique($written)) as $p) {
        try { writeAtomic(getcwd() . '/' . $p, $originals[$p]); if (readFileSafe(getcwd() . '/' . $p) !== $originals[$p]) { fail('restore_readback'); } lint(getcwd() . '/' . $p); out('restored_after_failure', $p); }
        catch (Throwable $restore_error) { $failed = true; out('restore_error', $p); }
    }
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    out('error', $error->getMessage()); if ($backup !== '') { out('backup', $backup); }
    out('restore', $failed ? 'FAILED_OWNER_ACTION_REQUIRED' : ($written ? 'ok' : 'not_needed')); exit(1);
}
