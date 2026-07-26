<?php
/**
 * ST-2c — cut over normal navigation to the stock OpenCart checkout.
 *
 * Removes only the URL-library redirect which sends checkout/checkout to
 * SimpleCheckout. SimpleCheckout remains installed as a rollback fallback.
 *
 * DB changes: none.
 * Mono credit: unchanged; this patch neither reads nor writes payment_mono_chast_status.
 * Rollback: restore system/library/url.php from
 * _patch_backups/ST-2c_checkout-cutover_20260725-<timestamp>/.
 */

declare(strict_types=1);

$patch = basename(__FILE__, '.php');
$root = __DIR__;
$relative = 'system/library/url.php';
$target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
$expectedHash = '941B44ECA33AA207B874972941EC29B188DD066E309ED689ED7D875F6F374969';
$marker = '// ST-2c cutover: stock checkout is default; SimpleCheckout remains installed.';

function st2c_cutover_fail(string $message): void {
    fwrite(STDERR, 'error=' . $message . PHP_EOL);
    exit(1);
}

if (!is_file($target)) {
    st2c_cutover_fail('target_missing:' . $relative);
}

$source = file_get_contents($target);

if ($source === false) {
    st2c_cutover_fail('target_read_failed:' . $relative);
}

if (strpos($source, $marker) !== false) {
    echo 'already_applied=yes' . PHP_EOL;
    exit(0);
}

$actualHash = strtoupper((string)hash_file('sha256', $target));

if ($actualHash !== $expectedHash) {
    st2c_cutover_fail('sha256_mismatch:' . $relative . '; actual=' . $actualHash);
}

$old = <<<'OLD'
		// Simple Checkout module
		if ($route == 'checkout/checkout') {
			$route = 'extension/SimpleCheckout/module/pinta_simple_checkout';
		}
		// Simple Checkout module

OLD;

$new = "\t\t// ST-2c cutover: stock checkout is default; SimpleCheckout remains installed.\n";
$anchorCount = substr_count($source, $old);

if ($anchorCount !== 1) {
    st2c_cutover_fail('anchor=simplecheckout_checkout_redirect; count=' . $anchorCount . '; expected=1');
}

$updated = str_replace($old, $new, $source);

if (strpos($updated, $marker) === false) {
    st2c_cutover_fail('postcheck_missing:cutover_marker');
}

if (strpos($updated, "\$route = 'extension/SimpleCheckout/module/pinta_simple_checkout';") !== false) {
    st2c_cutover_fail('postcheck_old_redirect_present');
}

$timestamp = gmdate('Ymd_His');
$backupDir = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . $patch . '-' . $timestamp;
$backup = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0775, true) && !is_dir(dirname($backup))) {
    st2c_cutover_fail('backup_directory_create_failed');
}

if (!copy($target, $backup)) {
    st2c_cutover_fail('backup_copy_failed:' . $relative);
}

if (file_put_contents($target, $updated) === false) {
    @copy($backup, $target);
    st2c_cutover_fail('target_write_failed:' . $relative . '; restored=yes');
}

$lintOutput = [];
$lintCode = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($target) . ' 2>&1', $lintOutput, $lintCode);

if ($lintCode !== 0) {
    @copy($backup, $target);
    st2c_cutover_fail('php_l_failed; restored=yes; detail=' . implode(' | ', $lintOutput));
}

echo 'cwd=' . $root . PHP_EOL;
echo 'time=' . gmdate('c') . PHP_EOL;
echo 'backup=' . $backupDir . PHP_EOL;
echo 'changed=' . $relative . PHP_EOL;
echo 'php_l=ok' . PHP_EOL;
echo 'done=ok' . PHP_EOL;

@unlink(__FILE__);
