<?php
/**
 * ST-3.5 — restore Pinta Nova Poshta TTN block on OC 4.1 admin order page.
 *
 * Scope:
 * - Updates the admin event injection anchor from the old OC4 id
 *   shipping-address-value to the current output-shipping-address id.
 * - No DB changes. No checkout/payment/totals changes.
 */

declare(strict_types=1);

$patch = 'st3.5-admin-ttn-20260624';
$root = __DIR__;
$target = $root . '/extension/PintaNovaPoshtaCod/admin/controller/payment/pinta_nova_poshta_cod.php';
$old = '$search = \'<div id="shipping-address-value">\';';
$new = '$search = \'<div id="output-shipping-address">\';';
$requiredTemplateAnchor = '<div id="output-shipping-address">';

function st35_fail(string $message, int $code = 1): void
{
    fwrite(STDERR, "error={$message}\n");
    exit($code);
}

function st35_count(string $haystack, string $needle): int
{
    return substr_count($haystack, $needle);
}

function st35_find_admin_order_template(string $root): string
{
    $candidates = [
        $root . '/admin/view/template/sale/order_info.twig',
        $root . '/adminEvhenii/view/template/sale/order_info.twig',
    ];

    foreach (glob($root . '/*/view/template/sale/order_info.twig') ?: [] as $candidate) {
        $candidates[] = $candidate;
    }

    $found = [];
    foreach (array_unique($candidates) as $candidate) {
        if (is_file($candidate)) {
            $found[] = $candidate;
        }
    }

    if (count($found) !== 1) {
        st35_fail('admin_order_info_template_count=' . count($found));
    }

    return $found[0];
}

function st35_php_lint(string $file): array
{
    $cmd = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1';
    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);

    return [$exitCode, implode("\n", $output)];
}

echo "patch={$patch}\n";
echo "cwd=" . getcwd() . "\n";
echo "time=" . date('c') . "\n";
echo "db_changes=no\n";

if (!is_file($target)) {
    st35_fail('target_missing=' . $target);
}

$template = st35_find_admin_order_template($root);
$templateContent = file_get_contents($template);

if ($templateContent === false) {
    st35_fail('template_read_failed=' . $template);
}

$templateAnchorCount = st35_count($templateContent, $requiredTemplateAnchor);
echo "admin_template={$template}\n";
echo "admin_template_anchor_count={$templateAnchorCount}\n";

if ($templateAnchorCount !== 1) {
    st35_fail('expected_one_output_shipping_address_anchor');
}

$content = file_get_contents($target);
if ($content === false) {
    st35_fail('target_read_failed=' . $target);
}

$oldCount = st35_count($content, $old);
$newCount = st35_count($content, $new);
echo "old_anchor_count={$oldCount}\n";
echo "new_anchor_count={$newCount}\n";

if ($oldCount === 0 && $newCount === 1) {
    echo "already_applied=yes\n";
    echo "done=ok\n";
    @unlink(__FILE__);
    exit(0);
}

if ($oldCount !== 1 || $newCount !== 0) {
    st35_fail('unexpected_anchor_state');
}

$backupDir = $root . '/_patch_backups/' . $patch . '-' . date('Ymd-His');
$backupFile = $backupDir . '/extension/PintaNovaPoshtaCod/admin/controller/payment/pinta_nova_poshta_cod.php';

if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0775, true)) {
    st35_fail('backup_dir_create_failed=' . dirname($backupFile));
}

if (!copy($target, $backupFile)) {
    st35_fail('backup_copy_failed=' . $backupFile);
}

echo "backup={$backupFile}\n";

$updated = str_replace($old, $new, $content, $replaceCount);
if ($replaceCount !== 1) {
    st35_fail('replace_count=' . $replaceCount);
}

if (file_put_contents($target, $updated) === false) {
    copy($backupFile, $target);
    st35_fail('target_write_failed_restored_backup');
}

[$lintExit, $lintOutput] = st35_php_lint($target);
echo "php_lint=" . str_replace(["\r", "\n"], ' | ', trim($lintOutput)) . "\n";

if ($lintExit !== 0) {
    copy($backupFile, $target);
    st35_fail('php_lint_failed_restored_backup');
}

$post = file_get_contents($target);
if ($post === false) {
    copy($backupFile, $target);
    st35_fail('post_read_failed_restored_backup');
}

if (st35_count($post, $old) !== 0 || st35_count($post, $new) !== 1) {
    copy($backupFile, $target);
    st35_fail('postcheck_failed_restored_backup');
}

echo "changed_file=extension/PintaNovaPoshtaCod/admin/controller/payment/pinta_nova_poshta_cod.php\n";
echo "already_applied=no\n";
echo "done=ok\n";
@unlink(__FILE__);
exit(0);
