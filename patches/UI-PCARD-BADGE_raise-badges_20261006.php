<?php
/**
 * UI-PCARD-BADGE — move both listing badge corners from 18px to 4px.
 * Owner-supplied live sources: boostershop-ds.css and header.twig, 2026-10-06.
 * Root cause: boostershop-ds.css:270-271, canonical top:18px rules.
 * History: CAT-004-SD-7 adds a flex column only; UI-PCARD-D preserves offsets.
 * Edit those source rules directly; no new override or !important.
 * Scope: CSS plus header CSS cache token only. No DB/PHP/template markup edits.
 * Run: php UI-PCARD-BADGE_raise-badges_20261006.php in ~/public_html.
 * Rollback: restore both relative paths from the logged backup, clear caches.
 * PHP 8.0 compatible. Lint the runner before any write; fail closed if unavailable.
 */
declare(strict_types=1);

const PCB_MARKER = 'UI-PCARD-BADGE 20261006';
const PCB_TOKEN = 'ui-pcard-badge-20261006';
const PCB_PREFIX = 'catalog/view/stylesheet/boostershop-ds.css?v=';

function pcb_log(string $key, string $value): void { echo $key . '=' . $value . PHP_EOL; }
function pcb_fail(string $message): void { throw new RuntimeException($message); }
function pcb_replace(string $source, string $anchor, string $replacement, string $label): string {
    if (substr_count($source, $anchor) !== 1) pcb_fail('anchor_count_invalid:' . $label);
    return str_replace($anchor, $replacement, $source);
}

$files = ['catalog/view/stylesheet/boostershop-ds.css', 'catalog/view/template/common/header.twig'];
$backup = '';
$writing = false;
$originals = [];
try {
    pcb_log('cwd', (string)getcwd());
    pcb_log('time', gmdate('c'));
    if (PHP_SAPI !== 'cli') pcb_fail('cli_required');
    foreach ($files as $file) {
        if (!is_file($file) || !is_readable($file) || !is_writable($file)) pcb_fail('target_unavailable:' . $file);
        $source = file_get_contents($file);
        if ($source === false) pcb_fail('target_read_failed:' . $file);
        $originals[$file] = $source;
    }
    $css = $originals[$files[0]];
    // The 4px inset follows the design system's spacing grid and the owner's sketch.
    $newLeft = '.bs-pcard__badge-tl { position: absolute; top: 4px; left: 18px; }';
    $newRight = '.bs-pcard__badge-tr { position: absolute; top: 4px; right: 18px; }';
    if (strpos($css, PCB_MARKER) !== false) {
        if (substr_count($css, PCB_MARKER) !== 1 || substr_count($css, $newLeft) !== 1 || substr_count($css, $newRight) !== 1) pcb_fail('partial_marker_state');
        pcb_log('already_applied', 'yes');
        pcb_log('done', 'ok');
        pcb_log('self_delete', @unlink(__FILE__) ? 'ok' : 'failed');
        exit(0);
    }
    $css = pcb_replace($css, '.bs-pcard__badge-tl { position: absolute; top: 18px; left: 18px; }',
        '/* ' . PCB_MARKER . ': 4px top inset on the DS spacing grid. */' . "\n" . $newLeft, 'badge_left');
    $css = pcb_replace($css, '.bs-pcard__badge-tr { position: absolute; top: 18px; right: 18px; }', $newRight, 'badge_right');
    $header = $originals[$files[1]];
    if (substr_count($header, PCB_PREFIX) !== 1) pcb_fail('anchor_count_invalid:header_css_reference');
    $offset = strpos($header, PCB_PREFIX) + strlen(PCB_PREFIX);
    $token = substr($header, $offset, strcspn($header, "\"'?& \t\r\n<>", $offset));
    if (!preg_match('/^[A-Za-z0-9._-]{1,128}$/D', $token)) pcb_fail('header_token_invalid');
    $header = pcb_replace($header, PCB_PREFIX . $token, PCB_PREFIX . PCB_TOKEN, 'header_css_token');
    pcb_log('header_cache_bust_from', $token);
    pcb_log('header_cache_bust_to', PCB_TOKEN);
    $updated = [$files[0] => $css, $files[1] => $header];

    if (!is_callable('exec')) pcb_fail('php_lint_unavailable');
    $lintOutput = [];
    $lintCode = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $lintOutput, $lintCode);
    if ($lintCode !== 0) pcb_fail('php_lint_failed');
    pcb_log('php_lint', 'ok');

    $backup = getcwd() . '/_patch_backups/UI-PCARD-BADGE_raise-badges_' . gmdate('Ymd_His') . '-' . bin2hex(random_bytes(3));
    foreach ($files as $file) {
        $destination = $backup . '/' . $file;
        $parent = dirname($destination);
        if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) pcb_fail('backup_directory_failed:' . $file);
        if (!copy($file, $destination) || file_get_contents($destination) !== $originals[$file]) pcb_fail('backup_verify_failed:' . $file);
    }
    pcb_log('backup', $backup);
    foreach ($files as $file) {
        if (file_get_contents($file) !== $originals[$file]) pcb_fail('source_changed_during_preflight:' . $file);
    }
    foreach ($files as $file) {
        // Mark before writing: even a short/failed write must trigger restoration.
        $writing = true;
        if (file_put_contents($file, $updated[$file]) !== strlen($updated[$file]) || file_get_contents($file) !== $updated[$file]) pcb_fail('write_verify_failed:' . $file);
        pcb_log('changed', $file);
    }
    pcb_log('done', 'ok');
    pcb_log('self_delete', @unlink(__FILE__) ? 'ok' : 'failed');
} catch (Throwable $error) {
    if ($writing && $backup !== '') {
        foreach ($files as $file) {
            $saved = $backup . '/' . $file;
            $restored = @copy($saved, $file) && @file_get_contents($file) === $originals[$file];
            pcb_log('restore:' . $file, $restored ? 'ok' : 'FAILED');
        }
    }
    fwrite(STDERR, 'ERROR=' . $error->getMessage() . PHP_EOL);
    exit(1);
}
