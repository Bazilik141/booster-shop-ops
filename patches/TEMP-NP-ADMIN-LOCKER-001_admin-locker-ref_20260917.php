<?php
/**
 * TEMP-NP-ADMIN-LOCKER-001 — reliable admin branch/parcel-locker selection.
 *
 * Run from the OpenCart web root:
 *   php TEMP-NP-ADMIN-LOCKER-001_admin-locker-ref_20260917.php
 *
 * Scope:
 * - admin waybill recipient search returns branches and parcel lockers together;
 * - sender search also permits Nova Poshta parcel lockers;
 * - selected point UUIDs are preserved and used for TTN creation;
 * - existing orders use checkout custom_field.bs_np_v1.warehouse_ref when present;
 * - admin labels say "Відділення / поштомат".
 *
 * No database writes are performed by this runner. Saving module settings later
 * stores the selected sender UUID through OpenCart's existing setting workflow.
 *
 * Rollback: restore the five files from the backup directory printed by the run,
 * then clear OpenCart caches and compiled templates.
 */

const TEMP_NP_PATCH = 'TEMP-NP-ADMIN-LOCKER-001_admin-locker-ref_20260917';
const TEMP_NP_MARKER = 'TEMP-NP-ADMIN-LOCKER-001';

function temp_np_out($key, $value)
{
    echo $key . '=' . $value . PHP_EOL;
}

function temp_np_fail($message)
{
    throw new RuntimeException($message);
}

function temp_np_with_eol($text, $content)
{
    $eol = strpos($content, "\r\n") !== false ? "\r\n" : "\n";
    return str_replace("\n", $eol, $text);
}

function temp_np_replace_once($content, $old, $new, $label)
{
    $old = temp_np_with_eol($old, $content);
    $new = temp_np_with_eol($new, $content);
    $count = substr_count($content, $old);

    if ($count !== 1) {
        temp_np_fail('anchor_count[' . $label . ']=' . $count . ';expected=1');
    }

    return str_replace($old, $new, $content);
}

function temp_np_write($path, $content)
{
    $written = file_put_contents($path, $content, LOCK_EX);
    if ($written === false || $written !== strlen($content)) {
        temp_np_fail('write_failed=' . $path);
    }
}

function temp_np_lint($path)
{
    $output = array();
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);

    if ($code !== 0) {
        temp_np_fail('php_lint_failed=' . $path . ';output=' . implode(' | ', $output));
    }

    temp_np_out('php_lint[' . $path . ']', 'ok');
}

$root = getcwd();
$relative_files = array(
    'extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php',
    'extension/PintaNovaPoshtaCod/admin/controller/shipping/pinta_nova_poshta.php',
    'extension/PintaNovaPoshtaCod/catalog/controller/shipping/pinta_nova_poshta.php',
    'extension/PintaNovaPoshtaCod/admin/view/template/shipping/pinta_nova_poshta/create_internet_document.twig',
    'extension/PintaNovaPoshtaCod/admin/view/template/shipping/pinta_nova_poshta/index.twig',
);
$php_files = array_slice($relative_files, 0, 3);
$original = array();
$updated = array();
$marker_files = 0;
$backup_dir = '';
$written_files = array();

try {
    temp_np_out('patch', TEMP_NP_PATCH);
    temp_np_out('cwd', $root);
    temp_np_out('time_utc', gmdate('c'));

    foreach ($relative_files as $relative) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_file($path)) {
            temp_np_fail('missing_file=' . $relative);
        }

        $content = file_get_contents($path);
        if ($content === false) {
            temp_np_fail('read_failed=' . $relative);
        }

        $original[$relative] = $content;
        $marker_count = substr_count($content, TEMP_NP_MARKER);
        if ($marker_count > 1) {
            temp_np_fail('marker_count[' . $relative . ']=' . $marker_count . ';expected=0_or_1');
        }
        if ($marker_count === 1) {
            $marker_files++;
        }
    }

    if ($marker_files === count($relative_files)) {
        temp_np_out('already_applied', 'yes');
        temp_np_out('done', 'ok');
        @unlink(__FILE__);
        exit(0);
    }

    if ($marker_files !== 0) {
        temp_np_fail('partial_state_marker_files=' . $marker_files . ';expected=0_or_' . count($relative_files));
    }

    $relative = 'extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php';
    $content = $original[$relative];
    $content = temp_np_replace_once(
        $content,
        "            'entry_recipient_address_warehouse' => 'Відділення',",
        "            'entry_recipient_address_warehouse' => 'Відділення / поштомат',",
        'admin_recipient_label'
    );
    $content = temp_np_replace_once(
        $content,
        "            'entry_sender_address_warehouse' => 'Відділення',",
        "            'entry_sender_address_warehouse' => 'Відділення / поштомат',",
        'admin_sender_label'
    );
    $content = temp_np_replace_once(
        $content,
        "            'text_warehouse' => 'Відділення',",
        "            'text_warehouse' => 'Відділення / поштомат',",
        'admin_delivery_type_label'
    );
    $content = temp_np_replace_once(
        $content,
        <<<'OLD'
                    $formdata['sender_city'],
                    $formdata['sender_address_warehouse'],
                    $formdata['sender_address_doors_street'],
OLD,
        <<<'NEW'
                    $formdata['sender_city'],
                    $formdata['sender_address_warehouse'],
                    $formdata['sender_address_warehouse_ref'] ?? '',
                    $formdata['sender_address_doors_street'],
NEW,
        'sender_ref_argument'
    );
    $content = temp_np_replace_once(
        $content,
        <<<'OLD'
                    $formdata['recipient_city'],
                    $formdata['recipient_address_warehouse'],
                    $formdata['recipient_address_doors_street'],
OLD,
        <<<'NEW'
                    $formdata['recipient_city'],
                    $formdata['recipient_address_warehouse'],
                    $formdata['recipient_address_warehouse_ref'] ?? '',
                    $formdata['recipient_address_doors_street'],
NEW,
        'recipient_ref_argument'
    );
    $content = temp_np_replace_once(
        $content,
        "            'sender_address_warehouse' => \$settings['shipping_pinta_nova_poshta_sender_address_warehouse'],",
        "            'sender_address_warehouse' => \$settings['shipping_pinta_nova_poshta_sender_address_warehouse'],\n            'sender_address_warehouse_ref' => \$settings['shipping_pinta_nova_poshta_sender_address_warehouse_ref'] ?? '',",
        'sender_ref_default'
    );
    $content = temp_np_replace_once(
        $content,
        "            'recipient_address_warehouse' => '',\n            'recipient_address_doors_street' => '',",
        "            'recipient_address_warehouse' => '',\n            'recipient_address_warehouse_ref' => '',\n            'recipient_address_doors_street' => '',",
        'recipient_ref_default'
    );
    $content = temp_np_replace_once(
        $content,
        <<<'OLD'
        if ($shippingCode === 'pinta_nova_poshta.warehouse') {
            $formdata['recipient_service_to'] = 'Warehouse';
            $formdata['recipient_address_warehouse'] = $recipient_address;
        } else {
OLD,
        <<<'NEW'
        if ($shippingCode === 'pinta_nova_poshta.warehouse') {
            $formdata['recipient_service_to'] = 'Warehouse';
            $formdata['recipient_address_warehouse'] = $recipient_address;
            $formdata['recipient_address_warehouse_ref'] = $this->getOrderWarehouseRef($order);
        } else {
NEW,
        'order_ref_prefill'
    );
    $content = temp_np_replace_once(
        $content,
        <<<'OLD'
        return $formdata;
    }

    public function prepareInternetDocumentProperties(
OLD,
        <<<'NEW'
        return $formdata;
    }

    // TEMP-NP-ADMIN-LOCKER-001: use the canonical checkout point ref instead of a mutable label.
    private function getOrderWarehouseRef($order)
    {
        $custom_field = $order['shipping_custom_field'] ?? array();

        if (is_string($custom_field)) {
            $decoded = json_decode($custom_field, true);
            $custom_field = is_array($decoded) ? $decoded : array();
        }

        $metadata = isset($custom_field['bs_np_v1']) && is_array($custom_field['bs_np_v1'])
            ? $custom_field['bs_np_v1']
            : array();
        $type = isset($metadata['type']) ? (string)$metadata['type'] : '';

        if (
            (int)($metadata['version'] ?? 0) !== 1
            || !in_array($type, array('warehouse', 'poshtomat'), true)
        ) {
            return '';
        }

        return $this->normaliseWarehouseRef($metadata['warehouse_ref'] ?? '');
    }

    private function normaliseWarehouseRef($warehouse_ref)
    {
        $warehouse_ref = trim((string)$warehouse_ref);

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $warehouse_ref)) {
            return '';
        }

        return $warehouse_ref;
    }

    public function prepareInternetDocumentProperties(
NEW,
        'order_ref_helper'
    );
    $content = temp_np_replace_once(
        $content,
        <<<'OLD'
    public function prepareSenderAddress(
        $counterparty_ref,
        $service_type,
        $city_name,
        $warehouse_name = "",
        $street_name = "",
OLD,
        <<<'NEW'
    public function prepareSenderAddress(
        $counterparty_ref,
        $service_type,
        $city_name,
        $warehouse_name = "",
        $warehouse_ref = "",
        $street_name = "",
NEW,
        'sender_ref_signature'
    );
    $content = temp_np_replace_once(
        $content,
        <<<'OLD'
            if ($service_type === 'WarehouseWarehouse' || $service_type === 'WarehouseDoors') {
                $warehouse = $this->model_extension_PintaNovaPoshtaCod_module_warehouse->getByName($warehouse_name);

                if (!$warehouse) {
                    $this->errors[] = $this->language->get('error_sender_warehouse_not_found');
                    $has_error = true;
                } else {
                    $this->sender_address_ref = $warehouse['ref'];
                }
            } else if ($service_type === 'DoorsWarehouse' || $service_type === 'DoorsDoors') {
OLD,
        <<<'NEW'
            if ($service_type === 'WarehouseWarehouse' || $service_type === 'WarehouseDoors') {
                $warehouse_ref = $this->normaliseWarehouseRef($warehouse_ref);

                if ($warehouse_ref !== '') {
                    $this->sender_address_ref = $warehouse_ref;
                } else {
                    $warehouse = $this->model_extension_PintaNovaPoshtaCod_module_warehouse->getByName($warehouse_name);

                    if (!$warehouse) {
                        $this->errors[] = $this->language->get('error_sender_warehouse_not_found');
                        $has_error = true;
                    } else {
                        $this->sender_address_ref = $warehouse['ref'];
                    }
                }
            } else if ($service_type === 'DoorsWarehouse' || $service_type === 'DoorsDoors') {
NEW,
        'sender_ref_resolution'
    );
    $content = temp_np_replace_once(
        $content,
        <<<'OLD'
    public function prepareRecipientAddress(
        $counterparty_ref,
        $service_type,
        $city_name,
        $warehouse_name = "",
        $street_name = "",
OLD,
        <<<'NEW'
    public function prepareRecipientAddress(
        $counterparty_ref,
        $service_type,
        $city_name,
        $warehouse_name = "",
        $warehouse_ref = "",
        $street_name = "",
NEW,
        'recipient_ref_signature'
    );
    $content = temp_np_replace_once(
        $content,
        <<<'OLD'
            if ($service_type === 'WarehouseWarehouse' || $service_type === 'DoorsWarehouse') {
                $warehouse = $this->model_extension_PintaNovaPoshtaCod_module_warehouse->getByName($warehouse_name);

                if (!$warehouse) {
                    $this->errors[] = $this->language->get('error_recipient_warehouse_not_found');
                    $has_error = true;
                } else {
                    $this->recipient_address_ref = $warehouse['ref'];
                }
            } else if ($service_type === 'WarehouseDoors' || $service_type === 'DoorsDoors') {
OLD,
        <<<'NEW'
            if ($service_type === 'WarehouseWarehouse' || $service_type === 'DoorsWarehouse') {
                $warehouse_ref = $this->normaliseWarehouseRef($warehouse_ref);

                if ($warehouse_ref !== '') {
                    $this->recipient_address_ref = $warehouse_ref;
                } else {
                    $warehouse = $this->model_extension_PintaNovaPoshtaCod_module_warehouse->getByName($warehouse_name);

                    if (!$warehouse) {
                        $this->errors[] = $this->language->get('error_recipient_warehouse_not_found');
                        $has_error = true;
                    } else {
                        $this->recipient_address_ref = $warehouse['ref'];
                    }
                }
            } else if ($service_type === 'WarehouseDoors' || $service_type === 'DoorsDoors') {
NEW,
        'recipient_ref_resolution'
    );
    $updated[$relative] = $content;

    $relative = 'extension/PintaNovaPoshtaCod/admin/controller/shipping/pinta_nova_poshta.php';
    $content = temp_np_replace_once(
        $original[$relative],
        <<<'OLD'
        $data = $this->load->language('extension/PintaNovaPoshtaCod/shipping/pinta_nova_poshta');
        $data['text_database_names'] = $this->getTextDatabaseNames();
OLD,
        <<<'NEW'
        $data = $this->load->language('extension/PintaNovaPoshtaCod/shipping/pinta_nova_poshta');
        // TEMP-NP-ADMIN-LOCKER-001: the Warehouse API service covers branches and parcel lockers.
        $data['text_warehouse'] = 'Відділення / поштомат';
        $data['entry_sender_address_warehouse'] = 'Відділення / поштомат';
        $data['text_database_names'] = $this->getTextDatabaseNames();
NEW,
        'settings_combined_label'
    );
    $updated[$relative] = $content;

    $relative = 'extension/PintaNovaPoshtaCod/catalog/controller/shipping/pinta_nova_poshta.php';
    $content = $original[$relative];
    $content = temp_np_replace_once(
        $content,
        <<<'OLD'
                        foreach ($warehouses as $warehouse) {
                            if ($type === 'sender') {
								if (
									$warehouse['type_of_warehouse'] !== '841339c7-591a-42e2-8233-7a0a00f0ed6f'
									&& $warehouse['type_of_warehouse'] !== '9a68df70-0267-42a8-bb5c-37f427e36ee4'
								) {
									continue; // Пропуск всего, что не "Почтовое отделение" или "Грузовое отделение"
								}
							}

                            if ($type == 'poshtoma' && $warehouse['type_of_warehouse'] == 'f9316480-5f2d-425d-bc2c-ac7cd29decf0') {
OLD,
        <<<'NEW'
                        // TEMP-NP-ADMIN-LOCKER-001: admin uses combined branch/parcel-locker search.
                        $parcel_locker_type = 'f9316480-5f2d-425d-bc2c-ac7cd29decf0';
                        $sender_types = array(
                            '841339c7-591a-42e2-8233-7a0a00f0ed6f',
                            '9a68df70-0267-42a8-bb5c-37f427e36ee4',
                            $parcel_locker_type,
                        );

                        foreach ($warehouses as $warehouse) {
                            if ($type === 'sender') {
                                if (!in_array($warehouse['type_of_warehouse'], $sender_types, true)) {
                                    continue;
                                }
                            }

                            if ($type == 'poshtoma' && $warehouse['type_of_warehouse'] == $parcel_locker_type) {
NEW,
        'warehouse_sender_filter'
    );
    $content = temp_np_replace_once(
        $content,
        "                            if ((\$type == 'warehouse' || \$type == 'sender') && \$warehouse['type_of_warehouse'] != 'f9316480-5f2d-425d-bc2c-ac7cd29decf0') {",
        <<<'NEW'
                            if (
                                $type === 'recipient'
                                || $type === 'sender'
                                || ($type === 'warehouse' && $warehouse['type_of_warehouse'] != $parcel_locker_type)
                            ) {
NEW,
        'warehouse_recipient_filter'
    );
    $updated[$relative] = $content;

    $relative = 'extension/PintaNovaPoshtaCod/admin/view/template/shipping/pinta_nova_poshta/create_internet_document.twig';
    $content = $original[$relative];
    $content = temp_np_replace_once(
        $content,
        <<<'OLD'
                                                    autocomplete="off"
                                            >
                                            <ul class="pinta-dropdown" data-for-id="sender-warehouse-address"
OLD,
        <<<'NEW'
                                                    autocomplete="off"
                                            >
                                            {# TEMP-NP-ADMIN-LOCKER-001: preserve the selected point UUID. #}
                                            <input type="hidden" name="sender_address_warehouse_ref"
                                                   id="sender-warehouse-address-ref"
                                                   value="{{ formdata["sender_address_warehouse_ref"] }}">
                                            <ul class="pinta-dropdown" data-for-id="sender-warehouse-address"
NEW,
        'waybill_sender_hidden_ref'
    );
    $content = temp_np_replace_once(
        $content,
        <<<'OLD'
                                                    autocomplete="off"
                                            >
                                            <ul class="pinta-dropdown" data-for-id="recipient-warehouse-address"
OLD,
        <<<'NEW'
                                                    autocomplete="off"
                                            >
                                            <input type="hidden" name="recipient_address_warehouse_ref"
                                                   id="recipient-warehouse-address-ref"
                                                   value="{{ formdata["recipient_address_warehouse_ref"] }}">
                                            <ul class="pinta-dropdown" data-for-id="recipient-warehouse-address"
NEW,
        'waybill_recipient_hidden_ref'
    );
    $content = temp_np_replace_once(
        $content,
        "                type: (element.attr('name') === 'sender_address_warehouse') ? 'sender' : '',",
        "                type: (element.attr('name') === 'sender_address_warehouse') ? 'sender' : 'recipient',",
        'waybill_recipient_search_type'
    );
    $content = temp_np_replace_once(
        $content,
        <<<'OLD'
        $("#" + inputId).val(label);
        $("#" + inputId).blur();
        hidePintaDropdown($("#" + inputId));
    });

    $("body").on("mousedown", ".pinta-dropdown li", function (event) {
        // Prevent input blur trigger
OLD,
        <<<'NEW'
        $("#" + inputId).val(label);
        $("#" + inputId + "-ref").val(ref);
        $("#" + inputId).blur();
        hidePintaDropdown($("#" + inputId));
    });

    $("body").on("input", "#sender-warehouse-address, #recipient-warehouse-address", function () {
        $("#" + this.id + "-ref").val("");
    });

    $("body").on("input", "#sender-city", function () {
        $("#sender-warehouse-address-ref").val("");
    });

    $("body").on("input", "#recipient-city", function () {
        $("#recipient-warehouse-address-ref").val("");
    });

    $("body").on("mousedown", ".pinta-dropdown li", function (event) {
        // Prevent input blur trigger
NEW,
        'waybill_selection_ref'
    );
    $updated[$relative] = $content;

    $relative = 'extension/PintaNovaPoshtaCod/admin/view/template/shipping/pinta_nova_poshta/index.twig';
    $content = $original[$relative];
    $content = temp_np_replace_once(
        $content,
        <<<'OLD'
                                                autocomplete="off"
                                        >
                                        <ul class="pinta-dropdown" data-for-id="sender-warehouse-address"
OLD,
        <<<'NEW'
                                                autocomplete="off"
                                        >
                                        {# TEMP-NP-ADMIN-LOCKER-001: preserve the selected sender point UUID. #}
                                        <input type="hidden"
                                               name="shipping_pinta_nova_poshta_sender_address_warehouse_ref"
                                               id="sender-warehouse-address-ref"
                                               value="{{ shipping_pinta_nova_poshta_sender_address_warehouse_ref|default('') }}">
                                        <ul class="pinta-dropdown" data-for-id="sender-warehouse-address"
NEW,
        'settings_sender_hidden_ref'
    );
    $content = temp_np_replace_once(
        $content,
        <<<'OLD'
        $("#" + inputId).val(label);
        $("#" + inputId).blur();
        hidePintaDropdown($("#" + inputId));
    });

    $("body").on("mousedown", ".pinta-dropdown li", function (event) {
        event.preventDefault();
OLD,
        <<<'NEW'
        $("#" + inputId).val(label);
        $("#" + inputId + "-ref").val(ref);
        $("#" + inputId).blur();
        hidePintaDropdown($("#" + inputId));
    });

    $("body").on("input", "#sender-warehouse-address, #sender-city", function () {
        $("#sender-warehouse-address-ref").val("");
    });

    $("body").on("mousedown", ".pinta-dropdown li", function (event) {
        event.preventDefault();
NEW,
        'settings_selection_ref'
    );
    $updated[$relative] = $content;

    foreach ($updated as $relative => $content) {
        if (substr_count($content, TEMP_NP_MARKER) !== 1) {
            temp_np_fail('post_transform_marker_count[' . $relative . ']=' . substr_count($content, TEMP_NP_MARKER));
        }
    }

    $backup_dir = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . TEMP_NP_PATCH . '-' . gmdate('Ymd-His');
    foreach ($relative_files as $relative) {
        $source = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $backup = $backup_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $backup_parent = dirname($backup);

        if (!is_dir($backup_parent) && !mkdir($backup_parent, 0775, true) && !is_dir($backup_parent)) {
            temp_np_fail('backup_dir_create_failed=' . $backup_parent);
        }
        if (!copy($source, $backup)) {
            temp_np_fail('backup_copy_failed=' . $relative);
        }
    }
    temp_np_out('backup', $backup_dir);

    foreach ($relative_files as $relative) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $written_files[] = $relative;
        temp_np_write($path, $updated[$relative]);
    }

    foreach ($php_files as $relative) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        temp_np_lint($path);
    }

    foreach ($relative_files as $relative) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $final = file_get_contents($path);
        if ($final === false || substr_count($final, TEMP_NP_MARKER) !== 1) {
            temp_np_fail('postcheck_failed=' . $relative);
        }
        temp_np_out('changed_file', $relative);
    }

    temp_np_out('changed_files', count($relative_files));
    temp_np_out('database_changes', 'none');
    temp_np_out('cache_clear', 'required');
    temp_np_out('already_applied', 'no');
    temp_np_out('done', 'ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    if ($backup_dir !== '' && $written_files) {
        foreach ($written_files as $relative) {
            $backup = $backup_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (is_file($backup)) {
                @copy($backup, $target);
            }
        }
        temp_np_out('rollback', 'attempted');
    }

    temp_np_out('error', $error->getMessage());
    temp_np_out('done', 'error');
    exit(1);
}
