<?php
declare(strict_types=1);

/*
 * TOC-001 — remove the unnecessary inner scroll from the desktop content TOC.
 * DB scope: none. Target: catalog/view/stylesheet/boostershop-ds.css only.
 * Rollback: restore the .bak file from _patch_backups/<patch>-<timestamp>/.
 */
if (PHP_SAPI !== 'cli' && !headers_sent()) header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors', '1');
const PATCH_NAME = 'TOC-001_desktop_toc_scroll_remove_20260724';
function log_line(string $key, string $value = ''): void { echo $key . ($value === '' ? '' : '=' . $value) . PHP_EOL; }
function fail_patch(string $message): void { throw new RuntimeException($message); }
function lint_self(): void {
    if (!function_exists('exec')) fail_patch('PHP exec() is unavailable; cannot pass mandatory php -l gate');
    $output = []; $code = 1; @exec(escapeshellarg(PHP_BINARY ?: 'php') . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $output, $code);
    if ($code !== 0) fail_patch('php -l gate failed: ' . implode(' ', $output));
    log_line('php_l', 'ok');
}
function toc_blocks(string $css): array {
    $pattern = '/\\.bs-cp-page\\s+\\.bs-cp-toc\\s*\\{([^{}]*)\\}/s';
    if (preg_match_all($pattern, $css, $matches, PREG_OFFSET_CAPTURE) === false) fail_patch('Cannot scan CSS selector blocks');
    $hits = [];
    foreach ($matches[0] as $index => $whole) {
        $body = $matches[1][$index][0];
        $hasMax = preg_match('/max-height\\s*:\\s*calc\\(\\s*100vh\\s*-\\s*80px\\s*\\)\\s*;/i', $body) === 1;
        $hasOverflow = preg_match('/overflow-y\\s*:\\s*auto\\s*;/i', $body) === 1;
        if ($hasMax && $hasOverflow) $hits[] = ['whole' => $whole[0], 'offset' => $whole[1], 'has_max' => $hasMax, 'has_overflow' => $hasOverflow];
    }
    return $hits;
}
function run(): void {
    $cwd = getcwd(); if (!is_string($cwd) || $cwd === '') fail_patch('Cannot determine cwd');
    log_line('patch', PATCH_NAME); log_line('cwd', $cwd); log_line('time', date('c')); lint_self();
    $relative = 'catalog/view/stylesheet/boostershop-ds.css'; $target = $cwd . '/' . $relative;
    if (!is_file($target)) fail_patch('Target CSS file not found: ' . $relative);
    $css = file_get_contents($target); if (!is_string($css)) fail_patch('Cannot read target CSS file');
    $hits = toc_blocks($css);
    if ($hits === []) {
        if (preg_match('/\\.bs-cp-page\\s+\\.bs-cp-toc\\s*\\{/s', $css) !== 1) fail_patch('Expected one desktop TOC selector is missing or ambiguous');
        log_line('already_applied', 'yes'); log_line('done', 'ok'); @unlink(__FILE__); log_line('self_delete', file_exists(__FILE__) ? 'failed' : 'ok'); return;
    }
    if (count($hits) !== 1 || !$hits[0]['has_max'] || !$hits[0]['has_overflow']) fail_patch('Expected exactly one desktop TOC block with both max-height and overflow-y:auto; found=' . count($hits));
    $old = $hits[0]['whole'];
    $new = preg_replace('/\\s*max-height\\s*:\\s*calc\\(\\s*100vh\\s*-\\s*80px\\s*\\)\\s*;\\s*/i', "\n", $old, 1, $maxCount);
    if (!is_string($new) || $maxCount !== 1) fail_patch('Cannot remove desktop TOC max-height anchor');
    $new = preg_replace('/\\s*overflow-y\\s*:\\s*auto\\s*;\\s*/i', "\n", $new, 1, $overflowCount);
    if (!is_string($new) || $overflowCount !== 1) fail_patch('Cannot remove desktop TOC overflow anchor');
    $updated = substr($css, 0, $hits[0]['offset']) . $new . substr($css, $hits[0]['offset'] + strlen($old));
    if (toc_blocks($updated) !== []) fail_patch('Post-replacement verification failed: inner-scroll declaration remains');
    $backupDir = $cwd . '/_patch_backups/' . PATCH_NAME . '-' . date('Ymd-His');
    $backup = $backupDir . '/' . $relative . '.bak';
    if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0755, true)) fail_patch('Cannot create backup directory');
    if (!copy($target, $backup)) fail_patch('Cannot create CSS backup');
    log_line('backup_file', $backup);
    if (file_put_contents($target, $updated, LOCK_EX) === false) fail_patch('Cannot write target CSS');
    $verify = file_get_contents($target); if (!is_string($verify) || toc_blocks($verify) !== []) { @copy($backup, $target); fail_patch('CSS verification failed; backup restored'); }
    log_line('changed_file', $relative); log_line('desktop_toc_inner_scroll', 'removed'); log_line('cache_clear', 'run separately after patch'); log_line('done', 'ok');
    @unlink(__FILE__); log_line('self_delete', file_exists(__FILE__) ? 'failed' : 'ok');
}
try { run(); } catch (Throwable $e) { log_line('error', $e->getMessage()); log_line('done', 'failed'); exit(1); }
