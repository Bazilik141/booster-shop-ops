<?php
/**
 * ST-3.7 — localize the Pinta Nova Poshta admin consignment form to Ukrainian.
 *
 * Scope:
 * - Adds a Ukrainian text overlay for keys used by create_internet_document.twig.
 * - No DB changes. No checkout/payment/TTN API logic changes.
 */

declare(strict_types=1);

$patch = 'st3.7-np-consignment-form-uk-20260625';
$root = __DIR__;
$target = $root . '/extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php';
$twig = $root . '/extension/PintaNovaPoshtaCod/admin/view/template/shipping/pinta_nova_poshta/create_internet_document.twig';

$old = <<<'PHP'
        $data = $this->load->language('extension/PintaNovaPoshtaCod/shipping/pinta_nova_poshta');
PHP;

$new = <<<'PHP'
        $data = $this->load->language('extension/PintaNovaPoshtaCod/shipping/pinta_nova_poshta');
        $data = array_merge($data, [
            'backward_delivery_cargo_disabled' => 'Вимкнено',
            'backward_delivery_cargo_document' => 'Документи',
            'backward_delivery_cargo_money' => 'Гроші',
            'backward_delivery_cargo_other' => 'Інше',
            'backward_delivery_cargo_trays' => 'Палети',
            'backward_delivery_payer_type_recipient' => 'Одержувач',
            'backward_delivery_payer_type_sender' => 'Відправник',
            'button_cancel' => 'Скасувати',
            'button_save' => 'Зберегти',
            'entry_additional_information' => 'Додаткова інформація',
            'entry_backward_delivery_cargo_string' => 'Сума зворотної доставки',
            'entry_backward_delivery_cargo_type' => 'Зворотна доставка',
            'entry_backward_delivery_payer_type' => 'Платник зворотної доставки',
            'entry_cargo_type' => 'Тип вантажу',
            'entry_cargo_type_cargo' => 'Вантаж',
            'entry_cargo_type_documents' => 'Документи',
            'entry_cargo_type_pallet' => 'Палети',
            'entry_cargo_type_parcel' => 'Посилка',
            'entry_cargo_type_tireswheels' => 'Шини та диски',
            'entry_cost' => 'Оголошена вартість',
            'entry_description' => 'Опис',
            'entry_info_reg_client_barcodes' => 'Внутрішній № замовлення',
            'entry_payment_form' => 'Форма оплати',
            'entry_payment_form_cash' => 'Готівка',
            'entry_payment_form_noncash' => 'Безготівкова',
            'entry_recipient' => 'Отримувач',
            'entry_recipient_address_doors_flat' => 'Квартира',
            'entry_recipient_address_doors_house' => 'Будинок',
            'entry_recipient_address_doors_street' => 'Вулиця',
            'entry_recipient_address_warehouse' => 'Відділення',
            'entry_recipient_area' => 'Область',
            'entry_recipient_city' => 'Місто',
            'entry_recipient_firstname' => 'Імʼя',
            'entry_recipient_lastname' => 'Прізвище',
            'entry_recipient_middlename' => 'По батькові',
            'entry_recipient_opencart_address' => 'Адреса з OpenCart',
            'entry_recipient_phone' => 'Телефон',
            'entry_recipient_shipping_to' => 'Тип доставки',
            'entry_seats_amount' => 'Кількість місць',
            'entry_sender' => 'Контрагент-відправник',
            'entry_sender_address_doors_flat' => 'Квартира',
            'entry_sender_address_doors_house' => 'Будинок',
            'entry_sender_address_doors_street' => 'Вулиця',
            'entry_sender_address_warehouse' => 'Відділення',
            'entry_sender_area' => 'Область',
            'entry_sender_city' => 'Місто',
            'entry_sender_firstname' => 'Імʼя',
            'entry_sender_lastname' => 'Прізвище',
            'entry_sender_middlename' => 'По батькові',
            'entry_sender_phone' => 'Телефон',
            'entry_sender_shipping_from' => 'Тип відправлення',
            'entry_service_type' => 'Тип сервісу',
            'entry_service_type_doors_doors' => 'Адреса-Адреса',
            'entry_service_type_doors_warehouse' => 'Адреса-Відділення',
            'entry_service_type_warehouse_doors' => 'Відділення-Адреса',
            'entry_service_type_warehouse_warehouse' => 'Відділення-Відділення',
            'entry_shipping_date' => 'Дата відправлення',
            'entry_type_of_payer' => 'Платник за доставку',
            'entry_type_of_payer_receiver' => 'Одержувач',
            'entry_type_of_payer_sender' => 'Відправник',
            'entry_weight' => 'Вага',
            'text_action' => 'Дія',
            'text_amount' => 'Кількість',
            'text_cargo' => 'Вантаж',
            'text_doors' => 'Адреса',
            'text_general' => 'Загальне',
            'text_height' => 'Висота',
            'text_help_special_cargo' => 'Доставка без коробки',
            'text_internet_document_form' => 'Форма накладної',
            'text_length' => 'Довжина',
            'text_option_please_select' => '--- Виберіть ---',
            'text_pack' => 'Упаковка',
            'text_recipient' => 'Отримувач',
            'text_seats' => 'Місця',
            'text_sender' => 'Відправник',
            'text_special_cargo' => 'Спеціальний вантаж',
            'text_tires_and_wheels' => 'Шини та диски',
            'text_tires_and_wheels_description' => 'Опис',
            'text_warehouse' => 'Відділення',
            'text_weight' => 'Вага',
            'text_width' => 'Ширина',
        ]);
PHP;

function st37_fail(string $message, int $code = 1): void
{
    fwrite(STDERR, "error={$message}\n");
    exit($code);
}

function st37_php_lint(string $file): array
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
    st37_fail('target_missing=' . $target);
}

if (!is_file($twig)) {
    st37_fail('twig_missing=' . $twig);
}

$content = file_get_contents($target);
if ($content === false) {
    st37_fail('target_read_failed=' . $target);
}

$oldCount = substr_count($content, $old);
$newMarker = "'entry_backward_delivery_cargo_string' => 'Сума зворотної доставки'";
$newCount = substr_count($content, $newMarker);

echo "old_language_load_count={$oldCount}\n";
echo "uk_overlay_marker_count={$newCount}\n";

if ($oldCount === 1 && $newCount === 1) {
    echo "already_applied=yes\n";
    echo "done=ok\n";
    @unlink(__FILE__);
    exit(0);
}

if ($oldCount !== 1 || $newCount !== 0) {
    st37_fail('unexpected_language_overlay_state');
}

$backupDir = $root . '/_patch_backups/' . $patch . '-' . date('Ymd-His');
$backupFile = $backupDir . '/extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php';

if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0775, true)) {
    st37_fail('backup_dir_create_failed=' . dirname($backupFile));
}

if (!copy($target, $backupFile)) {
    st37_fail('backup_copy_failed=' . $backupFile);
}

echo "backup={$backupFile}\n";

$updated = str_replace($old, $new, $content, $replaceCount);
if ($replaceCount !== 1) {
    copy($backupFile, $target);
    st37_fail('replace_count=' . $replaceCount);
}

if (file_put_contents($target, $updated) === false) {
    copy($backupFile, $target);
    st37_fail('target_write_failed_restored_backup');
}

[$lintExit, $lintOutput] = st37_php_lint($target);
echo "php_lint=" . str_replace(["\r", "\n"], ' | ', trim($lintOutput)) . "\n";

if ($lintExit !== 0) {
    copy($backupFile, $target);
    st37_fail('php_lint_failed_restored_backup');
}

$post = file_get_contents($target);
if ($post === false || substr_count($post, $newMarker) !== 1) {
    copy($backupFile, $target);
    st37_fail('postcheck_failed_restored_backup');
}

echo "changed_file=extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php\n";
echo "already_applied=no\n";
echo "done=ok\n";
@unlink(__FILE__);
exit(0);
