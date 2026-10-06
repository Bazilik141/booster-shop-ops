<?php
/**
 * UX-003 — restore append behavior when page 2+ omits canonical.
 * Source: current public JS + owner category.twig export, 2026-10-06.
 * Run from ~/public_html: php UX-003_load-more-fix_20261006.php
 * Scope: one JS guard, one category-only script version. No SEO policy/DB/CSS change.
 * A missing canonical is allowed only for append; category identity remains mandatory.
 * Conflicting canonical, replacement requests, redirects, origin and count guards remain strict.
 * Backups: _patch_backups/UX-003_load-more-fix_<timestamp>-<random>/.
 * Rollback: restore the two relative paths from the logged backup, then clear caches.
 * Do not restore over later edits; reconcile those first. PHP 8.0 compatible.
 */
declare(strict_types=1);
const LM_MARKER = 'UX-003-LM-FIX 20261006';
const LM_TOKEN = 'ux003-lm-fix-20261006';
const LM_PREFIX = 'catalog/view/javascript/bs-category-results.js?v=';
function lm_log(string $key, string $value): void { echo $key . '=' . $value . PHP_EOL; }
function lm_fail(string $message): void { throw new RuntimeException($message); }
function lm_once(string $source, string $anchor, string $replacement, string $name): string {
    if (substr_count($source, $anchor) !== 1) lm_fail('anchor_count_invalid:' . $name);
    return str_replace($anchor, $replacement, $source);
}
$files = ['catalog/view/javascript/bs-category-results.js', 'catalog/view/template/product/category.twig'];
$expected = ['3b31c563aca5f20764c6b041655480494c711cc6230cd41d79fd5e5589e9c735', '2ede52f642757083241e1634ed77babc3786be5ccdfce963be3651f30da1dded'];
$backup = '';
$writing = false;
$originals = [];
$oldGuard = "if (!next || canonical(doc) !== canonical(document) || next.dataset.category !== region.dataset.category) throw new Error('Category response mismatch');";
$newGuard = "// " . LM_MARKER . ": paginated responses may omit canonical; append still requires the same category identity.\n      if (!next || !next.dataset.category || next.dataset.category !== region.dataset.category || ((!options.append || canonical(doc)) && canonical(doc) !== canonical(document))) throw new Error('Category response mismatch');";
try {
    lm_log('cwd', (string)getcwd());
    lm_log('time', gmdate('c'));
    if (PHP_SAPI !== 'cli') lm_fail('cli_required');
    foreach ($files as $file) {
        if (!is_file($file) || !is_readable($file) || !is_writable($file)) lm_fail('target_unavailable:' . $file);
        $source = file_get_contents($file);
        if ($source === false) lm_fail('read_failed:' . $file);
        $originals[$file] = $source;
    }
    $js = $originals[$files[0]];
    $twig = $originals[$files[1]];
    // The content marker is independent of the shared cache token.
    if (strpos($js, LM_MARKER) !== false) {
        if (substr_count($js, LM_MARKER) !== 1 || substr_count($js, $newGuard) !== 1 || strpos($js, $oldGuard) !== false) lm_fail('partial_marker_state');
        lm_log('already_applied', 'yes'); lm_log('done', 'ok');
        lm_log('self_delete', @unlink(__FILE__) ? 'ok' : 'failed'); exit(0);
    }
    foreach ($files as $index => $file) {
        if (hash('sha256', $originals[$file]) !== $expected[$index]) lm_fail('source_sha_mismatch:' . $file);
    }
    $js = lm_once($js, $oldGuard, $newGuard, 'category_response_guard');
    if (substr_count($twig, LM_PREFIX) !== 1) lm_fail('anchor_count_invalid:category_script');
    $offset = strpos($twig, LM_PREFIX) + strlen(LM_PREFIX);
    $token = substr($twig, $offset, strcspn($twig, "\"'?& \t\r\n<>", $offset));
    if (!preg_match('/^[A-Za-z0-9._-]{1,128}$/D', $token)) lm_fail('script_token_invalid');
    $twig = lm_once($twig, LM_PREFIX . $token, LM_PREFIX . LM_TOKEN, 'category_script_token');
    // Only a validated literal token changes; every Twig tag and other byte is preserved.
    if (str_replace(LM_PREFIX . LM_TOKEN, LM_PREFIX . $token, $twig) !== $originals[$files[1]]) lm_fail('twig_preservation_failed');
    lm_log('twig_preservation', 'ok');
    lm_log('script_cache_bust_from', $token); lm_log('script_cache_bust_to', LM_TOKEN);
    $updated = [$files[0] => $js, $files[1] => $twig];
    if (!is_callable('exec')) lm_fail('php_lint_unavailable');
    $output = []; $code = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $output, $code);
    if ($code !== 0) lm_fail('php_lint_failed');
    lm_log('php_lint', 'ok');
    $backup = getcwd() . '/_patch_backups/UX-003_load-more-fix_' . gmdate('Ymd_His') . '-' . bin2hex(random_bytes(3));
    foreach ($files as $file) {
        $saved = $backup . '/' . $file; $parent = dirname($saved);
        if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) lm_fail('backup_directory_failed:' . $file);
        if (!copy($file, $saved) || file_get_contents($saved) !== $originals[$file]) lm_fail('backup_verify_failed:' . $file);
    }
    lm_log('backup', $backup);
    foreach ($files as $file) if (file_get_contents($file) !== $originals[$file]) lm_fail('source_changed_during_preflight:' . $file);
    foreach ($files as $file) {
        $writing = true;
        if (file_put_contents($file, $updated[$file]) !== strlen($updated[$file]) || file_get_contents($file) !== $updated[$file]) lm_fail('write_verify_failed:' . $file);
        lm_log('changed', $file);
    }
    lm_log('done', 'ok'); lm_log('self_delete', @unlink(__FILE__) ? 'ok' : 'failed');
} catch (Throwable $error) {
    if ($writing && $backup !== '') foreach ($files as $file) {
        $ok = @copy($backup . '/' . $file, $file) && @file_get_contents($file) === $originals[$file];
        lm_log('restore:' . $file, $ok ? 'ok' : 'FAILED');
    }
    fwrite(STDERR, 'ERROR=' . $error->getMessage() . PHP_EOL); exit(1);
}
