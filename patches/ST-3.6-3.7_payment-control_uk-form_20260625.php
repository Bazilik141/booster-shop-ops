<?php
/**
 * ST-3.6 + ST-3.7 — Nova Poshta payment control for business senders + Ukrainian admin TTN form.
 *
 * Scope:
 * - Uses AfterpaymentOnGoodsCost for COD when sender is not PrivatePerson.
 * - Keeps BackwardDeliveryData/Money for PrivatePerson senders.
 * - Fixes COD prefill inversion by order payment method.
 * - Adds Ukrainian labels overlay for create_internet_document.twig.
 * - No DB changes. No checkout/payment gateway/fiscalization changes.
 */

declare(strict_types=1);

$patch = 'st3.6-3.7-payment-control-uk-20260625';
$root = __DIR__;
$target = $root . '/extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php';
$library = $root . '/extension/PintaNovaPoshtaCod/system/library/pintanovaposhta/pintainternetdocument.php';

$oldLanguageLoad = <<<'PHP'
        $data = $this->load->language('extension/PintaNovaPoshtaCod/shipping/pinta_nova_poshta');
PHP;

$newLanguageLoad = <<<'PHP'
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

$oldSenderProperty = <<<'PHP'
    public $sender_contact_person_ref;
    public $sender_phone;
PHP;

$newSenderProperty = <<<'PHP'
    public $sender_contact_person_ref;
    public $sender_phone;
    public $sender_counterparty_type = '';
PHP;

$oldPrefill = <<<'PHP'
        if (!in_array($paymentCode, array('pinta_nova_poshta_cod', 'pinta_nova_poshta_cod.pinta_nova_poshta_cod'))) {
            $formdata['backward_delivery_cargo_type'] = 'Money';
            $formdata['backward_delivery_payer_type'] = 'Recipient';
            $formdata['backward_delivery_cargo_string'] = $cost;
        } else {
            $formdata['backward_delivery_cargo_type'] = 'Disabled';
            $formdata['backward_delivery_payer_type'] = 'Recipient';
            $formdata['backward_delivery_cargo_string'] = '';
        }
PHP;

$newPrefill = <<<'PHP'
        if (in_array($paymentCode, array('pinta_nova_poshta_cod', 'pinta_nova_poshta_cod.pinta_nova_poshta_cod'))) {
            $formdata['backward_delivery_cargo_type'] = 'Money';
            $formdata['backward_delivery_payer_type'] = 'Recipient';
            $formdata['backward_delivery_cargo_string'] = $cost;
        } else {
            $formdata['backward_delivery_cargo_type'] = 'Disabled';
            $formdata['backward_delivery_payer_type'] = 'Recipient';
            $formdata['backward_delivery_cargo_string'] = '';
        }
PHP;

$oldSenderTypeCapture = <<<'PHP'
        } else {
            $this->sender_ref = $counterparty['ref'];
        }
PHP;

$newSenderTypeCapture = <<<'PHP'
        } else {
            $this->sender_ref = $counterparty['ref'];
            $this->sender_counterparty_type = $counterparty['counterparty_type'];
        }
PHP;

$oldBackwardDelivery = <<<'PHP'
        if ($backward_delivery_cargo_type !== 'Disabled') {
            $internet_document_properties['BackwardDeliveryData'] = array(
                array(
                    'CargoType' => $backward_delivery_cargo_type,
                    'PayerType' => $backward_delivery_payer_type,
                    'RedeliveryString' => $backward_delivery_cargo_string,
                ),
            );
        }
PHP;

$newBackwardDelivery = <<<'PHP'
        if ($backward_delivery_cargo_type !== 'Disabled') {
            if ($backward_delivery_cargo_type === 'Money' && $this->sender_counterparty_type !== 'PrivatePerson') {
                $internet_document_properties['AfterpaymentOnGoodsCost'] = (string)$backward_delivery_cargo_string;
            } else {
                $internet_document_properties['BackwardDeliveryData'] = array(
                    array(
                        'CargoType' => $backward_delivery_cargo_type,
                        'PayerType' => $backward_delivery_payer_type,
                        'RedeliveryString' => $backward_delivery_cargo_string,
                    ),
                );
            }
        }
PHP;

function st36_37_fail(string $message, int $code = 1): void
{
    fwrite(STDERR, "error={$message}\n");
    exit($code);
}

function st36_37_count(string $haystack, string $needle): int
{
    return substr_count($haystack, $needle);
}

function st36_37_php_lint(string $file): array
{
    $cmd = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1';
    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);

    return [$exitCode, implode("\n", $output)];
}

function st36_37_apply(string $label, string $content, string $old, string $new, string $marker, array &$changes): string
{
    $oldCount = st36_37_count($content, $old);
    $markerCount = st36_37_count($content, $marker);

    echo "{$label}_old_count={$oldCount}\n";
    echo "{$label}_marker_count={$markerCount}\n";

    if ($markerCount > 0) {
        $changes[$label] = 'already_applied';
        return $content;
    }

    if ($oldCount !== 1 || $markerCount !== 0) {
        st36_37_fail("unexpected_{$label}_state");
    }

    $updated = str_replace($old, $new, $content, $replaceCount);
    if ($replaceCount !== 1) {
        st36_37_fail("replace_{$label}_count={$replaceCount}");
    }

    $changes[$label] = 'changed';
    return $updated;
}

echo "patch={$patch}\n";
echo "cwd=" . getcwd() . "\n";
echo "time=" . date('c') . "\n";
echo "db_changes=no\n";

if (!is_file($target)) {
    st36_37_fail('target_missing=' . $target);
}

if (!is_file($library)) {
    st36_37_fail('library_missing=' . $library);
}

$libraryContent = file_get_contents($library);
if ($libraryContent === false || st36_37_count($libraryContent, "\$api->callApi('InternetDocument', 'save', \$method_properties)") !== 1) {
    st36_37_fail('unexpected_internet_document_library_shape');
}

$content = file_get_contents($target);
if ($content === false) {
    st36_37_fail('target_read_failed=' . $target);
}

$backupDir = $root . '/_patch_backups/' . $patch . '-' . date('Ymd-His');
$backupFile = $backupDir . '/extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php';

$changes = [];
$updated = $content;
$updated = st36_37_apply(
    'uk_overlay',
    $updated,
    $oldLanguageLoad,
    $newLanguageLoad,
    "'entry_backward_delivery_cargo_string' => 'Сума зворотної доставки'",
    $changes
);
$updated = st36_37_apply(
    'sender_type_property',
    $updated,
    $oldSenderProperty,
    $newSenderProperty,
    'public $sender_counterparty_type',
    $changes
);
$updated = st36_37_apply(
    'cod_prefill',
    $updated,
    $oldPrefill,
    $newPrefill,
    "if (in_array(\$paymentCode, array('pinta_nova_poshta_cod', 'pinta_nova_poshta_cod.pinta_nova_poshta_cod')))",
    $changes
);
$updated = st36_37_apply(
    'sender_type_capture',
    $updated,
    $oldSenderTypeCapture,
    $newSenderTypeCapture,
    "\$this->sender_counterparty_type = \$counterparty['counterparty_type'];",
    $changes
);
$updated = st36_37_apply(
    'afterpayment_payload',
    $updated,
    $oldBackwardDelivery,
    $newBackwardDelivery,
    "AfterpaymentOnGoodsCost",
    $changes
);

if ($updated === $content) {
    echo "already_applied=yes\n";
    echo "done=ok\n";
    @unlink(__FILE__);
    exit(0);
}

if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0775, true)) {
    st36_37_fail('backup_dir_create_failed=' . dirname($backupFile));
}

if (!copy($target, $backupFile)) {
    st36_37_fail('backup_copy_failed=' . $backupFile);
}

echo "backup={$backupFile}\n";

if (file_put_contents($target, $updated) === false) {
    copy($backupFile, $target);
    st36_37_fail('target_write_failed_restored_backup');
}

[$lintExit, $lintOutput] = st36_37_php_lint($target);
echo "php_lint=" . str_replace(["\r", "\n"], ' | ', trim($lintOutput)) . "\n";

if ($lintExit !== 0) {
    copy($backupFile, $target);
    st36_37_fail('php_lint_failed_restored_backup');
}

$post = file_get_contents($target);
if ($post === false) {
    copy($backupFile, $target);
    st36_37_fail('post_read_failed_restored_backup');
}

$postChecks = [
    'uk_overlay' => st36_37_count($post, "'entry_backward_delivery_cargo_string' => 'Сума зворотної доставки'") === 1,
    'sender_type_property' => st36_37_count($post, 'public $sender_counterparty_type') === 1,
    'sender_type_capture' => st36_37_count($post, "\$this->sender_counterparty_type = \$counterparty['counterparty_type'];") === 1,
    'afterpayment_payload' => st36_37_count($post, "AfterpaymentOnGoodsCost") === 1,
    'cod_prefill_fixed' => st36_37_count($post, "if (in_array(\$paymentCode, array('pinta_nova_poshta_cod', 'pinta_nova_poshta_cod.pinta_nova_poshta_cod')))") === 1,
    'cod_prefill_old_removed' => st36_37_count($post, "if (!in_array(\$paymentCode, array('pinta_nova_poshta_cod', 'pinta_nova_poshta_cod.pinta_nova_poshta_cod')))") === 0,
];

foreach ($postChecks as $name => $ok) {
    echo "postcheck_{$name}=" . ($ok ? 'ok' : 'fail') . "\n";
}

if (in_array(false, $postChecks, true)) {
    copy($backupFile, $target);
    st36_37_fail('postcheck_failed_restored_backup');
}

foreach ($changes as $label => $state) {
    echo "change_{$label}={$state}\n";
}

echo "changed_file=extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php\n";
echo "already_applied=no\n";
echo "done=ok\n";
@unlink(__FILE__);
exit(0);
