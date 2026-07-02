<?php
declare(strict_types=1);

/**
 * TECH-012 — redirect legacy product URLs to their verified current canonicals.
 *
 * Scope: root .htaccess only. No DB changes.
 * Source verification: 2026-07-02 cPanel backup + live HTTP checks.
 * Rollback: restore .htaccess from
 * _patch_backups/TECH-012_legacy404-301_20260702-<timestamp>/.htaccess
 */

const PATCH_ID = 'TECH-012_legacy404-301_20260702';
const TARGET_FILE = '.htaccess';
const BEGIN_MARKER = '# BEGIN legacy-404-301 20260702';
const END_MARKER = '# END legacy-404-301 20260702';

$args = $argv ?? [];
$dryRun = in_array('--dry-run', $args, true);
$root = rtrim((string)(getenv('BS_PATCH_ROOT') ?: (getcwd() ?: __DIR__)), "/\\");
$target = $root . '/' . TARGET_FILE;
$backupDir = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
$backupFile = $backupDir . '/' . TARGET_FILE;

function out(string $message): void {
    echo $message . PHP_EOL;
}

function fail(string $message): never {
    throw new RuntimeException($message);
}

function normalize(string $value): string {
    return str_replace(["\r\n", "\r"], "\n", $value);
}

function assert_count(string $content, string $needle, int $expected, string $label): void {
    $actual = substr_count($content, $needle);
    if ($actual !== $expected) {
        fail("anchor_count_{$label}={$actual},expected={$expected}");
    }
}

function php_lint(string $path): void {
    $output = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    if ($code !== 0 || !str_contains(implode("\n", $output), 'No syntax errors detected')) {
        fail('php_lint_failed=' . implode(' | ', $output));
    }
    out('php_lint=ok');
}

function write_file(string $path, string $content): void {
    $temporary = $path . '.tech012.tmp.' . getmypid();
    if (file_put_contents($temporary, $content, LOCK_EX) === false) {
        fail('temporary_write_failed=' . TARGET_FILE);
    }
    if (!@rename($temporary, $path)) {
        if (!copy($temporary, $path)) {
            @unlink($temporary);
            fail('file_replace_failed=' . TARGET_FILE);
        }
        @unlink($temporary);
    }
}

function redirect_block(): string {
    return <<<'HTACCESS'
# BEGIN legacy-404-301 20260702
RewriteRule ^product/YGO-JP-QCAC-BST$ /product/Yu-Gi-Oh-boosters-Quarter-Century-Art-Collection [R=301,L]
RewriteRule ^product/OP-JP-OP08-BST$ /product/One-Piece-Boosters-OP-08-Two-Legends [R=301,L]
RewriteRule ^product/OP-JP-MIX-MBX$ /product/One-Piece-Mystery-Box [R=301,L]
RewriteRule ^product/PKM-JP-INFX-BST$ /product/Pokemon-boosters-Inferno-X [R=301,L]
RewriteRule ^product/PKM-JP-MZERO-BLR$ /product/Pokemon-Mega-Gallade-EX-Special-Set [R=301,L]
RewriteRule ^product/PKM-JP-SVEX-BLR$ /product/Pokemon-Scarlet-Violet-ex-Special-Set [R=301,L]
RewriteRule ^product/YGO-JP-WPP5-BST$ /product/Yu-Gi-Oh-boosters-World-Premiere-Pack-2024 [R=301,L]
RewriteRule ^product/MTG-JP-AFRS-BST$ /product/Magic-the-Gathering-Adventures-in-the-Forgotten-Realms [R=301,L]
RewriteRule ^product/PKM-KR-HWA-BST$ /product/Pokemon-boosters-Hot-Wind-Arena [R=301,L]
RewriteRule ^product/Pokemon-boosters-mix-lowpull$ /product/Pokemon-Japanese-outlet-booster [R=301,L]
# END legacy-404-301 20260702
HTACCESS;
}

function assert_final(string $content): void {
    $content = normalize($content);
    $block = redirect_block();

    assert_count($content, BEGIN_MARKER, 1, 'begin_marker');
    assert_count($content, END_MARKER, 1, 'end_marker');
    assert_count($content, $block, 1, 'redirect_block');
    assert_count(
        $content,
        'RewriteRule ^(.*)$ https://boostershop.website/$1 [R=301,L]',
        1,
        'canonical_redirect'
    );
    assert_count(
        $content,
        'RewriteRule ^([^?]*) index.php?_route_=$1 [L,QSA]',
        1,
        'opencart_catchall'
    );

    $canonicalPosition = strpos(
        $content,
        'RewriteRule ^(.*)$ https://boostershop.website/$1 [R=301,L]'
    );
    $blockPosition = strpos($content, BEGIN_MARKER);
    $catchallPosition = strpos($content, 'RewriteRule ^([^?]*) index.php?_route_=$1 [L,QSA]');
    if ($canonicalPosition === false || $blockPosition === false || $catchallPosition === false) {
        fail('final_order_check_missing_anchor');
    }
    if (!($canonicalPosition < $blockPosition && $blockPosition < $catchallPosition)) {
        fail('final_order_check_failed');
    }
}

set_exception_handler(static function (Throwable $error): void {
    out('error=' . $error->getMessage());
    out('done=failed');
    exit(1);
});

out('patch=' . PATCH_ID);
out('cwd=' . $root);
out('time=' . date('c'));
out('dry_run=' . ($dryRun ? 'yes' : 'no'));
out('db_changes=none');
out('target=' . TARGET_FILE);
php_lint(__FILE__);

if (!is_file($target)) {
    fail('target_not_found=' . TARGET_FILE);
}
if (!$dryRun && !is_writable($target)) {
    fail('target_not_writable=' . TARGET_FILE);
}

$originalRaw = file_get_contents($target);
if (!is_string($originalRaw)) {
    fail('target_read_failed=' . TARGET_FILE);
}
$eol = str_contains($originalRaw, "\r\n") ? "\r\n" : "\n";
$original = normalize($originalRaw);
out('file_preflight=ok:' . TARGET_FILE);

$hasBegin = str_contains($original, BEGIN_MARKER);
$hasEnd = str_contains($original, END_MARKER);
if ($hasBegin xor $hasEnd) {
    fail('partial_patch_state_detected');
}
if ($hasBegin && $hasEnd) {
    assert_final($original);
    out('already_applied=yes');
    out('changed=none');
    out('done=ok');
    if (!$dryRun) {
        @unlink(__FILE__);
    }
    exit(0);
}

assert_count($original, BEGIN_MARKER, 0, 'begin_marker_preflight');
assert_count($original, END_MARKER, 0, 'end_marker_preflight');
assert_count(
    $original,
    'RewriteRule ^(.*)$ https://boostershop.website/$1 [R=301,L]',
    1,
    'canonical_redirect_preflight'
);
assert_count(
    $original,
    'RewriteRule ^uk-ua/?$ index.php?route=common/home&language=uk-ua [L,QSA]',
    1,
    'insert_anchor'
);
assert_count(
    $original,
    'RewriteRule ^([^?]*) index.php?_route_=$1 [L,QSA]',
    1,
    'opencart_catchall_preflight'
);

$insertAnchor = 'RewriteRule ^uk-ua/?$ index.php?route=common/home&language=uk-ua [L,QSA]';
$updated = str_replace(
    $insertAnchor,
    redirect_block() . "\n\n" . $insertAnchor,
    $original
);
assert_final($updated);
out('assert=transformed_state:ok');

if ($dryRun) {
    out('would_change=yes:' . TARGET_FILE);
    out('done=ok');
    exit(0);
}

$writeStarted = false;
try {
    if (!is_dir($backupDir)
        && !mkdir($backupDir, 0775, true)
        && !is_dir($backupDir)) {
        fail('backup_dir_create_failed=' . $backupDir);
    }
    if (!copy($target, $backupFile)) {
        fail('backup_copy_failed=' . TARGET_FILE);
    }
    out('backup=' . str_replace($root . '/', '', $backupFile));
    out('assert=backup_before_write:ok');

    $writeStarted = true;
    $writeContent = $eol === "\n" ? $updated : str_replace("\n", $eol, $updated);
    write_file($target, $writeContent);
    out('changed=' . TARGET_FILE);

    $finalRaw = file_get_contents($target);
    if (!is_string($finalRaw)) {
        fail('post_write_read_failed=' . TARGET_FILE);
    }
    assert_final($finalRaw);
    out('assert=final_state:ok');
    out('done=ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    if ($writeStarted && is_file($backupFile)) {
        @copy($backupFile, $target);
    }
    out('rollback=' . ($writeStarted ? 'file_backup_restored' : 'no_write_started'));
    out('error=' . $error->getMessage());
    out('done=failed');
    exit(1);
}
