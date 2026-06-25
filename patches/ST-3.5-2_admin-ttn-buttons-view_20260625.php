<?php
/**
 * ST-3.5-2 follow-up — render the Pinta NP order button component, not the
 * module settings page, inside admin sale/order_info.
 *
 * Scope:
 * - Fixes getOrdersShippingAddressHtmlSuffix() when an order has no TTN yet.
 * - No DB changes. No checkout/payment/totals/COD logic changes.
 */

declare(strict_types=1);

$patch = 'st3.5-2-admin-ttn-buttons-view-20260625';
$root = __DIR__;
$target = $root . '/extension/PintaNovaPoshtaCod/admin/controller/shipping/pinta_nova_poshta.php';
$buttonView = $root . '/extension/PintaNovaPoshtaCod/admin/view/template/shipping/pinta_nova_poshta/components/order_page_buttons.twig';
$badReturn = "return \$this->load->view('extension/PintaNovaPoshtaCod/shipping/pinta_nova_poshta/index', \$data);";

$oldBlock = <<<'PHP'
$settings = $this->model_setting_setting->getSetting('shipping_pinta_nova_poshta');
$internet_document = $this->model_extension_PintaNovaPoshtaCod_module_internet_document->getByOrderId($order_id);
$data['internet_document'] = $internet_document;

if (empty($internet_document) || empty($internet_document['int_doc_number'])) {
    $data['status'] = 'Інформація відсутня';
    return $this->load->view('extension/PintaNovaPoshtaCod/shipping/pinta_nova_poshta/index', $data);

}

$request_data = [
PHP;

$replacement = <<<'PHP'

            $settings = $this->model_setting_setting->getSetting('shipping_pinta_nova_poshta');
            $internet_document = $this->model_extension_PintaNovaPoshtaCod_module_internet_document->getByOrderId($order_id);
            $data['internet_document'] = $internet_document;

            if (empty($internet_document) || empty($internet_document['int_doc_number'])) {
                $data['status'] = 'Інформація відсутня';
                $data['internet_document'] = null;
                $data['pinta_link_create_internet_document'] = $this->url->link('extension/PintaNovaPoshtaCod/shipping/pinta_nova_poshta|createinternetdocument', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id, true);

                return $this->load->view('extension/PintaNovaPoshtaCod/shipping/pinta_nova_poshta/components/order_page_buttons', $data);
            }

            $request_data = [
PHP;

function st352_fail(string $message, int $code = 1): void
{
    fwrite(STDERR, "error={$message}\n");
    exit($code);
}

function st352_php_lint(string $file): array
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
    st352_fail('target_missing=' . $target);
}

if (!is_file($buttonView)) {
    st352_fail('button_view_missing=' . $buttonView);
}

$content = file_get_contents($target);
if ($content === false) {
    st352_fail('target_read_failed=' . $target);
}

$badReturnCount = substr_count($content, $badReturn);
$blockCount = substr_count($content, $oldBlock);

echo "bad_index_return_count={$badReturnCount}\n";
echo "replace_block_count={$blockCount}\n";

if ($badReturnCount === 0 && $blockCount === 0) {
    echo "already_applied=yes\n";
    echo "done=ok\n";
    @unlink(__FILE__);
    exit(0);
}

if ($badReturnCount !== 1 || $blockCount !== 1) {
    st352_fail('unexpected_suffix_view_state');
}

$backupDir = $root . '/_patch_backups/' . $patch . '-' . date('Ymd-His');
$backupFile = $backupDir . '/extension/PintaNovaPoshtaCod/admin/controller/shipping/pinta_nova_poshta.php';

if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0775, true)) {
    st352_fail('backup_dir_create_failed=' . dirname($backupFile));
}

if (!copy($target, $backupFile)) {
    st352_fail('backup_copy_failed=' . $backupFile);
}

echo "backup={$backupFile}\n";

$updated = str_replace($oldBlock, $replacement, $content, $replaceCount);
if ($replaceCount !== 1) {
    st352_fail('replace_failed=' . $replaceCount);
}

if (file_put_contents($target, $updated) === false) {
    copy($backupFile, $target);
    st352_fail('target_write_failed_restored_backup');
}

[$lintExit, $lintOutput] = st352_php_lint($target);
echo "php_lint=" . str_replace(["\r", "\n"], ' | ', trim($lintOutput)) . "\n";

if ($lintExit !== 0) {
    copy($backupFile, $target);
    st352_fail('php_lint_failed_restored_backup');
}

$post = file_get_contents($target);
if ($post === false) {
    copy($backupFile, $target);
    st352_fail('post_read_failed_restored_backup');
}

if (substr_count($post, $badReturn) !== 0) {
    copy($backupFile, $target);
    st352_fail('postcheck_bad_index_return_still_present');
}

echo "changed_file=extension/PintaNovaPoshtaCod/admin/controller/shipping/pinta_nova_poshta.php\n";
echo "already_applied=no\n";
echo "done=ok\n";
@unlink(__FILE__);
exit(0);
