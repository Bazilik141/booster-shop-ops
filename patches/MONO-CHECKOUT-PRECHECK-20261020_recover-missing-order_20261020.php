<?php
/**
 * MONO-CHECKOUT-PRECHECK-20261020: recover a missing checkout order and restore
 * shared credit button presentation after a definite JSON create error.
 * Owner runs from public_html; this runner must be staged outside the webroot.
 * Modes: --preflight (default), --apply, --rollback-preflight=<exact backup dir>,
 * --rollback=<exact backup dir>. No database or cache mutation.
 * Apply backs up both files, metadata and this exact recovery runner before writes.
 * Rollback preflights every target and every needed backup before restoring any.
 * The owner stages the backup recovery_runner.php privately before rollback.
 * Cache cleanup is a separate reviewed owner-packet action after success.
 * PHP syntax remains compatible with production PHP 8.0.
 */
declare(strict_types=1);

const MF_ID = 'MONO-CHECKOUT-PRECHECK-20261020_recover-missing-order_20261020';
const MF_MARKER = 'MONO-CHECKOUT-PRECHECK-20261020';
const MF_SPECS = [
[
'path' => 'catalog/controller/checkout/confirm.php',
'kind' => 'php',
'before' => 'df3d6b75e261cc986646170864b7327e4eb56e7745ec3bf879cf86fe8cab869d',
'after' => '6365c2a36bb575848cf4f578252f3642454ab8e37626ce7c1c14eabf4f60ac17',
'replacements' => [
[
'CQlpZiAoJG9yZGVyX2lkICYmICEkb3JkZXJfaW5mbykgewoJCQl1bnNldCgkdGhpcy0+c2Vzc2lvbi0+ZGF0YVsnb3JkZXJfaWQnXSk7CgkJfQ==',
'CQlpZiAoJG9yZGVyX2lkICYmICEkb3JkZXJfaW5mbykgewoJCQl1bnNldCgkdGhpcy0+c2Vzc2lvbi0+ZGF0YVsnb3JkZXJfaWQnXSk7CgkJCS8vIE1PTk8tQ0hFQ0tPVVQtUFJFQ0hFQ0stMjAyNjEwMjA6IGxldCB0aGUgdmFsaWRhdGVkIHN1Ym1pdCBjcmVhdGUgYSByZXBsYWNlbWVudCBvcmRlci4KCQkJJG9yZGVyX2lkID0gMDsKCQl9'
]
]
],
[
'path' => 'catalog/view/template/checkout/credit_confirm.twig',
'kind' => 'twig',
'before' => '6c095eaee5bc8bd5b5d074d4e990c208e03b936be3d63e639b0b055e597624ad',
'after' => 'ae9062872ecc3acc51c0117eaf14c790e935a7b0c08130001dc5888071216b66',
'replacements' => [
[
'ICB2YXIgY29uZmlybVVybCA9IHt7IGNvbmZpcm1fanNvbnxyYXcgfX0sIHdhaXRpbmdVcmwgPSB7eyB3YWl0aW5nX2pzb258cmF3IH19LCBidXN5ID0gZmFsc2U7',
'ICB2YXIgY29uZmlybVVybCA9IHt7IGNvbmZpcm1fanNvbnxyYXcgfX0sIHdhaXRpbmdVcmwgPSB7eyB3YWl0aW5nX2pzb258cmF3IH19LCBidXN5ID0gZmFsc2U7CiAgLy8gTU9OTy1DSEVDS09VVC1QUkVDSEVDSy0yMDI2MTAyMDogY2FwdHVyZSBiZWZvcmUgdGhlIGNoZWNrb3V0IGNhbGxlciBjaGFuZ2VzIHRoZSBsYWJlbC4KICB2YXIgaWRsZUxhYmVsID0gcm9vdC5xdWVyeVNlbGVjdG9yKCdbZGF0YS1wYXkwMDMtY3JlYXRlXScpLnRleHRDb250ZW50Ow=='
],
[
'ICAgICAgYnVzeSA9IGZhbHNlOyBidXR0b24uZGlzYWJsZWQgPSBmYWxzZTs=',
'ICAgICAgYnVzeSA9IGZhbHNlOyBidXR0b24uZGlzYWJsZWQgPSBmYWxzZTsKICAgICAgYnV0dG9uLmNsYXNzTGlzdC5yZW1vdmUoJ2xvYWRpbmcnKTsKICAgICAgYnV0dG9uLnRleHRDb250ZW50ID0gaWRsZUxhYmVsOw=='
]
]
]
];

function mf_out(string $key, string $value): void { echo $key . '=' . $value . PHP_EOL; }
function mf_bad(string $reason): void { throw new RuntimeException($reason); }
function mf_normal(string $path): string { return str_replace('\\', '/', $path); }
function mf_inside(string $path, string $root): bool {
    $path = mf_normal($path); $root = rtrim(mf_normal($root), '/') . '/';
    if (DIRECTORY_SEPARATOR === '\\') { $path = strtolower($path); $root = strtolower($root); }
    return strpos($path . '/', $root) === 0;
}
function mf_path(string $root, string $relative): string {
    if (!preg_match('~^[A-Za-z0-9_][A-Za-z0-9_./-]*$~D', $relative) || strpos($relative, '..') !== false) mf_bad('relative_path_invalid');
    $path = $root;
    foreach (explode('/', $relative) as $part) {
        $path .= DIRECTORY_SEPARATOR . $part;
        if (is_link($path)) mf_bad('symlink_refused:' . $relative);
    }
    return $path;
}
function mf_read(string $path, int $maximum = 1048576): string {
    if (!is_file($path) || is_link($path)) mf_bad('regular_file_required');
    $size = filesize($path);
    if (!is_int($size) || $size < 1 || $size > $maximum) mf_bad('file_size_invalid');
    $bytes = file_get_contents($path);
    if (!is_string($bytes) || strlen($bytes) !== $size) mf_bad('file_read_failed');
    return $bytes;
}
function mf_meta(string $path): array {
    $s = stat($path);
    if (!is_array($s) || (($s['mode'] & 07000) !== 0)) mf_bad('target_metadata_invalid');
    return ['mode' => $s['mode'] & 0777, 'uid' => $s['uid'], 'gid' => $s['gid']];
}
function mf_metadata(string $path, array $meta): void {
    clearstatcache(true, $path);
    if (fileowner($path) !== $meta['uid'] && !chown($path, $meta['uid'])) mf_bad('owner_restore_failed');
    if (filegroup($path) !== $meta['gid'] && !chgrp($path, $meta['gid'])) mf_bad('group_restore_failed');
    if (!chmod($path, $meta['mode'])) mf_bad('mode_restore_failed');
    clearstatcache(true, $path);
    if (mf_meta($path) !== $meta) mf_bad('metadata_verification_failed');
}
function mf_new(string $path, string $bytes, int $mode = 0600): void {
    $handle = fopen($path, 'x+b');
    if ($handle === false) mf_bad('exclusive_create_failed');
    try {
        if (!chmod($path, $mode)) mf_bad('private_mode_failed');
        $offset = 0; $length = strlen($bytes);
        while ($offset < $length) {
            $n = fwrite($handle, substr($bytes, $offset));
            if (!is_int($n) || $n < 1) mf_bad('write_failed');
            $offset += $n;
        }
        if (!fflush($handle)) mf_bad('flush_failed');
    } finally { fclose($handle); }
    if (hash_file('sha256', $path) !== hash('sha256', $bytes)) mf_bad('write_hash_failed');
}
function mf_lint(string $bytes, string $kind): string {
    if ($kind !== 'php') return 'not_php_js_only';
    $pipes = [];
    $process = proc_open([PHP_BINARY, '-l'], [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
    if (!is_resource($process)) mf_bad('php_lint_start_failed');
    $offset = 0; $length = strlen($bytes);
    while ($offset < $length) {
        $n = fwrite($pipes[0], substr($bytes, $offset));
        if (!is_int($n) || $n < 1) mf_bad('php_lint_input_failed');
        $offset += $n;
    }
    fclose($pipes[0]);
    stream_get_contents($pipes[1]); fclose($pipes[1]);
    stream_get_contents($pipes[2]); fclose($pipes[2]);
    if (proc_close($process) !== 0) mf_bad('php_lint_failed');
    return 'php_l_ok';
}
function mf_classify(string $bytes, array $spec): string {
    $sha = hash('sha256', $bytes);
    if ($sha === $spec['before'] && substr_count($bytes, MF_MARKER) === 0) return 'before';
    if ($sha === $spec['after'] && substr_count($bytes, MF_MARKER) === 1) return 'after';
    mf_bad('target_drift:' . $spec['path']);
}
function mf_candidate(string $bytes, array $spec): string {
    foreach ($spec['replacements'] as $change) {
        $old = base64_decode($change[0], true); $new = base64_decode($change[1], true);
        if (!is_string($old) || !is_string($new) || substr_count($bytes, $old) !== 1) mf_bad('anchor_count:' . $spec['path']);
        $bytes = str_replace($old, $new, $bytes, $count);
        if ($count !== 1) mf_bad('replacement_count:' . $spec['path']);
    }
    if (hash('sha256', $bytes) !== $spec['after'] || substr_count($bytes, MF_MARKER) !== 1) mf_bad('candidate_hash:' . $spec['path']);
    mf_lint($bytes, $spec['kind']);
    return $bytes;
}
function mf_mkdir(string $path): void {
    if (is_link($path)) mf_bad('backup_directory_symlink');
    if (!is_dir($path) && !mkdir($path, 0700)) mf_bad('backup_directory_create_failed');
}
function mf_install(string $path, string $bytes, array $meta, string $kind): void {
    $tmp = $path . '.mono-fix-' . bin2hex(random_bytes(8));
    try {
        mf_new($tmp, $bytes);
        mf_metadata($tmp, $meta);
        mf_lint(mf_read($tmp), $kind);
        if (!rename($tmp, $path)) mf_bad('atomic_replace_failed');
        clearstatcache(true, $path);
        if (hash_file('sha256', $path) !== hash('sha256', $bytes)) mf_bad('installed_hash_failed');
        mf_metadata($path, $meta);
        mf_lint(mf_read($path), $kind);
    } finally {
        if (is_file($tmp) && !is_link($tmp)) unlink($tmp);
    }
}
function mf_backup(string $root, array $sources, array $metadata): array {
    $base = mf_path($root, '_patch_backups'); mf_mkdir($base);
    $dir = $base . DIRECTORY_SEPARATOR . MF_ID . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
    if (file_exists($dir) || is_link($dir) || !mkdir($dir, 0700)) mf_bad('unique_backup_create_failed');
    $manifest = ['patch' => MF_ID, 'root' => $root, 'created_utc' => gmdate('c'), 'runner_sha256' => hash_file('sha256', __FILE__), 'targets' => []];
    foreach (MF_SPECS as $spec) {
        $path = $dir;
        foreach (explode('/', dirname($spec['path'])) as $part) { $path .= DIRECTORY_SEPARATOR . $part; mf_mkdir($path); }
        $backup = mf_path($dir, $spec['path']);
        mf_new($backup, $sources[$spec['path']]);
        if (hash_file('sha256', $backup) !== $spec['before']) mf_bad('backup_hash_failed');
        $manifest['targets'][$spec['path']] = ['before' => $spec['before'], 'after' => $spec['after'], 'metadata' => $metadata[$spec['path']]];
    }
    mf_new($dir . DIRECTORY_SEPARATOR . 'recovery_runner.php', mf_read(__FILE__));
    $manifestBytes = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    mf_new($dir . DIRECTORY_SEPARATOR . 'manifest.json', $manifestBytes);
    return [$dir, hash('sha256', $manifestBytes), $manifest['runner_sha256']];
}
function mf_restore(string $root, string $directory, bool $dry): int {
    $dir = realpath($directory);
    $base = realpath(mf_path($root, '_patch_backups'));
    if (!is_string($dir) || !is_string($base) || dirname($dir) !== $base || is_link($directory) || strpos(basename($dir), MF_ID . '-') !== 0) mf_bad('exact_backup_directory_required');
    mf_path($base, basename($dir));
    $manifest = json_decode(mf_read(mf_path($dir, 'manifest.json'), 65536), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($manifest) || ($manifest['patch'] ?? null) !== MF_ID || ($manifest['root'] ?? null) !== $root || ($manifest['runner_sha256'] ?? null) !== hash_file('sha256', __FILE__)) mf_bad('backup_manifest_identity');
    if (!is_array($manifest['targets'] ?? null) || count($manifest['targets']) !== count(MF_SPECS)) mf_bad('backup_manifest_targets');
    $plans = [];
    foreach (MF_SPECS as $spec) {
        $entry = $manifest['targets'][$spec['path']] ?? [];
        if (($entry['before'] ?? '') !== $spec['before'] || ($entry['after'] ?? '') !== $spec['after']) mf_bad('backup_manifest_pins');
        $meta = $entry['metadata'] ?? [];
        if (array_keys($meta) !== ['mode','uid','gid']) mf_bad('backup_metadata_keys');
        foreach ($meta as $value) if (!is_int($value) || $value < 0) mf_bad('backup_metadata_type');
        if ($meta['mode'] > 0777) mf_bad('backup_metadata_mode');
        $target = mf_path($root, $spec['path']);
        $state = mf_classify(mf_read($target), $spec);
        $backup = mf_path($dir, $spec['path']);
        $bytes = '';
        if ($state === 'after') {
            $bytes = mf_read($backup);
            if (hash('sha256', $bytes) !== $spec['before']) mf_bad('rollback_backup_hash:' . $spec['path']);
            mf_lint($bytes, $spec['kind']);
        }
        $plans[] = [$spec, $target, $state, $backup, $bytes, $meta];
    }
    // All target states and every required backup are admitted before any restore.
    $restored = 0;
    foreach ($plans as [$spec, $target, $state, $backup, $bytes, $meta]) {
        if ($state === 'after' && !$dry) {
            if (hash_file('sha256', $target) !== $spec['after']) mf_bad('rollback_concurrent_drift');
            mf_install($target, $bytes, $meta, $spec['kind']);
            $restored++;
        }
        mf_out('target', $spec['path'] . ' current_state=' . $state . ' sha256_before=' . $spec['before'] . ' sha256_after=' . $spec['after'] . ' backup=' . ($state === 'after' ? $backup : 'none'));
    }
    mf_out('backup_dir', $dir);
    mf_out('restored_files', (string)$restored);
    return $restored;
}

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
set_error_handler(static function (): bool { throw new RuntimeException('runtime_or_filesystem_error'); });
try {
    if (PHP_VERSION_ID < 80000) mf_bad('php_8_required');
    if ($argc > 2) mf_bad('one_mode_argument_required');
    $argument = $argv[1] ?? '--preflight';
    $mode = ''; $backupArgument = '';
    if ($argument === '--preflight' || $argument === '--apply') $mode = substr($argument, 2);
    elseif (strpos($argument, '--rollback-preflight=') === 0) { $mode = 'rollback-preflight'; $backupArgument = substr($argument, 21); }
    elseif (strpos($argument, '--rollback=') === 0) { $mode = 'rollback'; $backupArgument = substr($argument, 11); }
    else mf_bad('mode_invalid');
    $root = realpath((string)getcwd());
    if (!is_string($root) || !is_file(mf_path($root, 'index.php')) || !is_file(mf_path($root, 'config.php'))) mf_bad('run_from_catalog_root');
    $self = realpath(__FILE__);
    if (!is_string($self) || mf_inside($self, $root)) mf_bad('runner_must_be_outside_webroot');
    mf_out('patch', MF_ID); mf_out('mode', $mode);
    if ($mode === 'rollback' || $mode === 'rollback-preflight') {
        $restored = mf_restore($root, $backupArgument, $mode === 'rollback-preflight');
        mf_out('database_touched', 'no'); mf_out('cache_cleared_by_runner', 'no');
        mf_out('done', $mode === 'rollback' ? 'rollback_ok' : 'rollback_preflight_ok');
        exit(0);
    }
    $sources = []; $metadata = []; $states = []; $candidates = [];
    foreach (MF_SPECS as $spec) {
        $path = mf_path($root, $spec['path']);
        $bytes = mf_read($path); $state = mf_classify($bytes, $spec);
        $states[] = $state; $sources[$spec['path']] = $bytes; $metadata[$spec['path']] = mf_meta($path);
        mf_lint($bytes, $spec['kind']);
        if ($state === 'before') $candidates[$spec['path']] = mf_candidate($bytes, $spec);
    }
    if (count(array_unique($states)) !== 1) mf_bad('mixed_target_states_require_rollback_or_review');
    $state = $states[0];
    if ($mode === 'preflight') {
        mf_out('admission', $state);
        foreach (MF_SPECS as $spec) mf_out('target', $spec['path'] . ' sha256_before=' . $spec['before'] . ' sha256_after=' . $spec['after'] . ' backup=none syntax_gate=' . mf_lint($sources[$spec['path']], $spec['kind']));
        mf_out('database_touched', 'no'); mf_out('cache_cleared_by_runner', 'no'); mf_out('done', 'preflight_ok'); exit(0);
    }
    if ($state === 'after') {
        mf_out('already_applied', 'yes'); mf_out('changed_files', '0');
        mf_out('database_touched', 'no'); mf_out('cache_cleared_by_runner', 'no');
        if (!unlink(__FILE__)) mf_bad('self_delete_failed_already_applied');
        mf_out('self_delete', 'ok'); mf_out('done', 'ok'); exit(0);
    }
    [$backupDir, $manifestSha, $runnerSha] = mf_backup($root, $sources, $metadata);
    mf_out('backup_dir', $backupDir); mf_out('backup_manifest_sha256', $manifestSha); mf_out('recovery_runner_sha256', $runnerSha);
    try {
        foreach (MF_SPECS as $spec) if (hash_file('sha256', mf_path($root, $spec['path'])) !== $spec['before']) mf_bad('concurrent_target_drift_before_apply');
        foreach (MF_SPECS as $spec) {
            $path = mf_path($root, $spec['path']);
            if (hash_file('sha256', $path) !== $spec['before']) mf_bad('concurrent_target_drift_during_apply');
            mf_install($path, $candidates[$spec['path']], $metadata[$spec['path']], $spec['kind']);
        }
    } catch (Throwable $applyError) {
        try { mf_restore($root, $backupDir, false); mf_out('source_restored', 'yes'); }
        catch (Throwable $restoreError) { mf_out('source_restored', 'no_review_required'); }
        throw $applyError;
    }
    foreach (MF_SPECS as $spec) mf_out('target', $spec['path'] . ' sha256_before=' . $spec['before'] . ' backup=' . mf_path($backupDir, $spec['path']) . ' syntax_gate=' . mf_lint(mf_read(mf_path($root, $spec['path'])), $spec['kind']) . ' sha256_after=' . $spec['after']);
    mf_out('changed_files', (string)count(MF_SPECS)); mf_out('database_touched', 'no'); mf_out('cache_cleared_by_runner', 'no');
    if (!unlink(__FILE__)) mf_bad('self_delete_failed_applied_backup_available');
    mf_out('self_delete', 'ok'); mf_out('done', 'ok');
} catch (Throwable $error) {
    $reason = preg_replace('/[^A-Za-z0-9_:\/. -]/', '_', $error->getMessage()) ?? 'unknown';
    mf_out('error', substr($reason, 0, 220)); exit(1);
}
