<?php
declare(strict_types=1);

/**
 * ST-2a.5 — guest checkout blocker fix (one line).
 *
 * Root cause (verified on backup-6.12.2026_21-54-05):
 * checkout/register.php save() builds $shipping_address_data WITHOUT 'address_id'
 * for guests; stock 4.1 shipping_method.quote requires
 * isset(session['shipping_address']['address_id']) -> guest always gets
 * "Потрібна адреса доставки!". Fix = initialize 'address_id' => 0 in the array
 * (account branch overwrites it with the real id, same as stock line ~680 does
 * in the payment-address branch).
 *
 * Scope: ONE file, ONE insertion. No DB. No SimpleCheckout/url.php/Hutko.
 */

$patch = 'st2a5_guest_address_id_fix_20260612';
$root = getcwd() ?: __DIR__;
$backupDir = $root . '/_patch_backups/' . $patch . '-' . date('Ymd-His');
$file = 'catalog/controller/checkout/register.php';
$path = $root . '/' . $file;

function out(string $m): void { echo '[' . date('c') . '] ' . $m . PHP_EOL; }
function fail(string $m): void { out('error=' . $m); out('done=failed'); exit(1); }

out('patch=' . $patch);
out('cwd=' . $root);
out('scope=guest shipping_address address_id=0 init; single file');

if (!is_file($path)) fail('missing ' . $file);
$content = file_get_contents($path);
if ($content === false) fail('cannot read ' . $file);

$needle = "\t\t\t\t\t\t'custom_field'   => \$post_info['shipping_custom_field'] ?? []\n\t\t\t\t\t];";
$replacement = "\t\t\t\t\t\t'custom_field'   => \$post_info['shipping_custom_field'] ?? [],\n\t\t\t\t\t\t'address_id'     => 0\n\t\t\t\t\t];";

if (strpos($content, "'address_id'     => 0") !== false && substr_count($content, $needle) === 0) {
    out('already_applied=yes');
    out('done=ok');
    @unlink(__FILE__);
    exit(0);
}

$count = substr_count($content, $needle);
if ($count !== 1) fail('pre-check: expected 1 anchor, got ' . $count);

if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) fail('cannot create backup dir');
if (!copy($path, $backupDir . '/register.php')) fail('cannot backup');
out('backup=' . str_replace($root . '/', '', $backupDir) . '/register.php');

if (file_put_contents($path, str_replace($needle, $replacement, $content)) === false) fail('cannot write');
out('changed=' . $file);

$lint = shell_exec(escapeshellarg(PHP_BINARY ?: 'php') . ' -l ' . escapeshellarg($path) . ' 2>&1');
if (!is_string($lint) || stripos($lint, 'No syntax errors detected') === false) {
    copy($backupDir . '/register.php', $path);
    fail('php -l failed, restored: ' . trim((string)$lint));
}
out('php_lint_ok=' . $file);
out('rollback=restore ' . str_replace($root . '/', '', $backupDir) . '/register.php');
out('done=ok');

@unlink(__FILE__);
out('self_delete=' . (file_exists(__FILE__) ? 'failed' : 'ok'));
