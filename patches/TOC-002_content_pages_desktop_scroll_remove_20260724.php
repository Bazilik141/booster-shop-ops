<?php
declare(strict_types=1);

/*
 * TOC-002 — remove the live desktop TOC inner-scroll from content-pages.css.
 * Verified source: catalog/view/stylesheet/content-pages.css.
 * DB scope: none. Rollback: restore the .bak file from _patch_backups.
 */
if (PHP_SAPI !== 'cli' && !headers_sent()) header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors', '1');
const PATCH_NAME = 'TOC-002_content_pages_desktop_scroll_remove_20260724';
function out(string $key, string $value = ''): void { echo $key . ($value === '' ? '' : '=' . $value) . PHP_EOL; }
function fail(string $message): void { throw new RuntimeException($message); }
function lint_self(): void {
    if (!function_exists('exec')) fail('PHP exec() is unavailable; cannot pass mandatory php -l gate');
    $output = []; $status = 1; @exec(escapeshellarg(PHP_BINARY ?: 'php') . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $output, $status);
    if ($status !== 0) fail('php -l gate failed: ' . implode(' ', $output));
    out('php_l', 'ok');
}
function toc_scroll_blocks(string $css): array {
    $selector = '/^\\s*\\.bs-cp-toc\\s*\\{([^{}]*)\\}/m';
    if (preg_match_all($selector, $css, $matches, PREG_OFFSET_CAPTURE) === false) fail('Cannot scan .bs-cp-toc blocks');
    $hits = [];
    $max = '/max-height\\s*:\\s*calc\\s*\\(\\s*(?:100vh\\s*-\\s*80px|-\\s*80px\\s*\\+\\s*100vh)\\s*\\)\\s*;/i';
    $overflow = '/overflow-y\\s*:\\s*auto\\s*;/i';
    foreach ($matches[0] as $i => $whole) {
        $body = $matches[1][$i][0];
        if (preg_match($max, $body) === 1 && preg_match($overflow, $body) === 1) $hits[] = ['whole' => $whole[0], 'offset' => $whole[1]];
    }
    return $hits;
}
function run(): void {
    $cwd = getcwd(); if (!is_string($cwd) || $cwd === '') fail('Cannot determine cwd');
    out('patch', PATCH_NAME); out('cwd', $cwd); out('time', date('c')); lint_self();
    $relative = 'catalog/view/stylesheet/content-pages.css'; $target = $cwd . '/' . $relative;
    if (!is_file($target)) fail('Target CSS file not found: ' . $relative);
    $css = file_get_contents($target); if (!is_string($css)) fail('Cannot read target CSS file');
    $hits = toc_scroll_blocks($css);
    if ($hits === []) {
        if (preg_match('/^\\s*\\.bs-cp-toc\\s*\\{/m', $css) !== 1) fail('Expected .bs-cp-toc selector is missing');
        out('already_applied', 'yes'); out('done', 'ok'); @unlink(__FILE__); out('self_delete', file_exists(__FILE__) ? 'failed' : 'ok'); return;
    }
    if (count($hits) !== 1) fail('Expected one desktop .bs-cp-toc inner-scroll block; found=' . count($hits));
    $old = $hits[0]['whole'];
    $max = '/\\s*max-height\\s*:\\s*calc\\s*\\(\\s*(?:100vh\\s*-\\s*80px|-\\s*80px\\s*\\+\\s*100vh)\\s*\\)\\s*;\\s*/i';
    $new = preg_replace($max, "\n", $old, 1, $maxCount);
    if (!is_string($new) || $maxCount !== 1) fail('Cannot remove max-height anchor');
    $new = preg_replace('/\\s*overflow-y\\s*:\\s*auto\\s*;\\s*/i', "\n", $new, 1, $overflowCount);
    if (!is_string($new) || $overflowCount !== 1) fail('Cannot remove overflow-y anchor');
    $updated = substr($css, 0, $hits[0]['offset']) . $new . substr($css, $hits[0]['offset'] + strlen($old));
    if (toc_scroll_blocks($updated) !== []) fail('Post-replacement verification failed: desktop TOC inner-scroll remains');
    $backupDir = $cwd . '/_patch_backups/' . PATCH_NAME . '-' . date('Ymd-His');
    $backup = $backupDir . '/' . $relative . '.bak';
    if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0755, true)) fail('Cannot create backup directory');
    if (!copy($target, $backup)) fail('Cannot create CSS backup');
    out('backup_file', $backup);
    if (file_put_contents($target, $updated, LOCK_EX) === false) fail('Cannot write target CSS');
    $verify = file_get_contents($target); if (!is_string($verify) || toc_scroll_blocks($verify) !== []) { @copy($backup, $target); fail('Verification failed; CSS backup restored'); }
    out('changed_file', $relative); out('desktop_toc_inner_scroll', 'removed'); out('done', 'ok');
    @unlink(__FILE__); out('self_delete', file_exists(__FILE__) ? 'failed' : 'ok');
}
try { run(); } catch (Throwable $e) { out('error', $e->getMessage()); out('done', 'failed'); exit(1); }
