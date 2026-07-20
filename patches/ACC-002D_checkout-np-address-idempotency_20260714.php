<?php
declare(strict_types=1);

/*
 * ACC-002D — checkout NP address idempotency — 2026-07-14
 *
 * Scope (no patch-time DB migration/cleanup):
 * - resets stale NP point/ref data before a checkout delivery-type switch;
 * - validates checkout NP refs server-side and persists bs_np_v1 metadata;
 * - reuses an existing structured address for the same NP identity instead of
 *   calling addAddress() again on every autosave.
 *
 * Existing duplicate/legacy rows are intentionally not modified or deleted.
 */

$patch = 'ACC-002D_checkout-np-address-idempotency_20260714';
$root = getcwd();
$dryRun = in_array('--dry-run', $argv ?? [], true);

function acc002d_fail(string $message): void {
	fwrite(STDERR, "error={$message}\n");
	exit(1);
}

function acc002d_lf(string $value): string {
	return str_replace(["\r\n", "\r"], "\n", $value);
}

function acc002d_eol(string $value): string {
	return str_contains($value, "\r\n") ? "\r\n" : "\n";
}

function acc002d_restore_eol(string $value, string $eol): string {
	return $eol === "\n" ? $value : str_replace("\n", $eol, $value);
}

function acc002d_replace_once(string $source, string $find, string $replace, string $relative): string {
	$count = substr_count($source, $find);

	if ($count !== 1) {
		acc002d_fail("anchor_count path={$relative} expected=1 actual={$count}");
	}

	return str_replace($find, $replace, $source);
}

function acc002d_php_l(string $path): bool {
	$output = [];
	$code = 0;
	exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
	return $code === 0;
}

$targets = [
	'catalog/controller/checkout/shipping_address.php' => 'ACC-002D structured NP checkout address',
	'catalog/view/javascript/checkout-reskin.js' => 'ACC-002D saved-to-new NP type gate',
];

$files = [];
$states = [];

foreach ($targets as $relative => $marker) {
	$path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

	if (!is_file($path)) {
		acc002d_fail("target_missing path={$relative}");
	}

	$raw = file_get_contents($path);
	if ($raw === false) {
		acc002d_fail("read_failed path={$relative}");
	}

	$files[$relative] = [
		'path' => $path,
		'eol' => acc002d_eol($raw),
		'old' => acc002d_lf($raw),
	];
	$states[] = str_contains($raw, $marker);
}

if (count(array_filter($states)) === count($states)) {
	echo "already_applied=yes patch={$patch}\n";
	exit(0);
}

if (count(array_filter($states)) !== 0) {
	acc002d_fail('partial_state_detected restore_from_backup_required');
}

$controller = $files['catalog/controller/checkout/shipping_address.php']['old'];
$controller = acc002d_replace_once(
	$controller,
	<<<'FIND'
	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
FIND,
	<<<'REPLACE'
	// ACC-002D structured NP checkout address: validate module refs before a
	// checkout autosave may create or reuse an address-book row.
	private function prepareBsNpCheckoutAddress(array $post, array &$json): array {
		$module_type = trim((string)($post['shipping_novaposhta_type'] ?? ''));
		$type_map = [
			'warehouse' => 'warehouse',
			'poshtoma' => 'poshtomat',
			'doors' => 'courier',
		];
		$type = $type_map[$module_type] ?? '';
		$area_label = trim((string)($post['shipping_novaposhta_area'] ?? ''));
		$city_label = trim((string)($post['shipping_novaposhta_city'] ?? ''));
		$area_ref = trim((string)($post['shipping_novaposhta_area_ref'] ?? ''));
		$city_ref = trim((string)($post['shipping_novaposhta_city_ref'] ?? ''));
		$point_label = $type === 'courier'
			? trim((string)($post['shipping_novaposhta_doors_street'] ?? ''))
			: trim((string)($post['shipping_novaposhta_warehouse_address'] ?? ''));
		$point_ref = $type === 'courier'
			? trim((string)($post['shipping_novaposhta_street_ref'] ?? ''))
			: trim((string)($post['shipping_novaposhta_warehouse_ref'] ?? ''));
		$house = trim((string)($post['shipping_novaposhta_doors_house'] ?? ''));
		$flat = trim((string)($post['shipping_novaposhta_doors_flat'] ?? ''));

		if ($type === '') {
			$json['error']['novaposhta_type'] = 'Оберіть тип доставки Нової пошти.';
		}

		if (!oc_validate_length($area_label, 1, 128) || $area_ref === '') {
			$json['error']['novaposhta_area'] = 'Оберіть область із довідника Нової пошти.';
		}

		if (!oc_validate_length($city_label, 2, 128) || $city_ref === '') {
			$json['error']['novaposhta_city'] = 'Оберіть місто із довідника Нової пошти.';
		}

		if (!oc_validate_length($point_label, 1, 128) || $point_ref === '') {
			$json['error'][$type === 'courier' ? 'novaposhta_doors_street' : 'novaposhta_warehouse_address'] = $type === 'courier'
				? 'Оберіть вулицю із довідника Нової пошти.'
				: 'Оберіть відділення або поштомат із довідника Нової пошти.';
		}

		if ($type === 'courier' && !oc_validate_length($house, 1, 32)) {
			$json['error']['novaposhta_doors_house'] = 'Вкажіть номер будинку (до 32 символів).';
		}

		if (strlen($flat) > 32) {
			$json['error']['novaposhta_doors_flat'] = 'Номер квартири має містити до 32 символів.';
		}

		if (!empty($json['error'])) {
			return [];
		}

		$this->load->model('extension/PintaNovaPoshtaCod/module/area');
		$this->load->model('extension/PintaNovaPoshtaCod/module/city');
		$this->load->model('extension/PintaNovaPoshtaCod/module/warehouse');
		$this->load->model('extension/PintaNovaPoshtaCod/module/street');

		$area = $this->model_extension_PintaNovaPoshtaCod_module_area->getByName($area_label);

		if (!$area || (string)($area['ref'] ?? '') !== $area_ref) {
			$json['error']['novaposhta_area'] = 'Область Нової пошти більше не доступна. Оберіть її повторно.';
			return [];
		}

		$city = $this->model_extension_PintaNovaPoshtaCod_module_city->getByName($city_label);

		if (
			!$city ||
			(string)($city['ref'] ?? '') !== $city_ref ||
			(string)($city['area'] ?? '') !== (string)$area['ref']
		) {
			$json['error']['novaposhta_city'] = 'Місто Нової пошти більше не доступне для цієї області. Оберіть його повторно.';
			return [];
		}

		$zone_id = (int)$this->model_extension_PintaNovaPoshtaCod_module_area->getZoneIdByRef($area['ref']);

		if (!$zone_id) {
			$json['error']['novaposhta_area'] = 'Не вдалося визначити область доставки. Оберіть область повторно.';
			return [];
		}

		$metadata = [
			'version' => 1,
			'type' => $type,
			'area_ref' => (string)$area['ref'],
			'city_ref' => (string)$city['ref'],
			'warehouse_ref' => '',
			'street_ref' => '',
			'labels' => [
				'area' => (string)($area['description'] ?? $area_label),
				'city' => (string)($city['description'] ?? $city_label),
				'point' => '',
			],
			'house' => '',
			'flat' => '',
		];

		if ($type === 'warehouse' || $type === 'poshtomat') {
			$warehouse = $this->model_extension_PintaNovaPoshtaCod_module_warehouse->getByRef($point_ref);
			$poshtomat_type_ref = 'f9316480-5f2d-425d-bc2c-ac7cd29decf0';

			if (
				!$warehouse ||
				(string)($warehouse['city_ref'] ?? '') !== (string)$city['ref'] ||
				($type === 'poshtomat' && (string)($warehouse['type_of_warehouse'] ?? '') !== $poshtomat_type_ref) ||
				($type === 'warehouse' && (string)($warehouse['type_of_warehouse'] ?? '') === $poshtomat_type_ref)
			) {
				$json['error']['novaposhta_warehouse_address'] = 'Обрана точка Нової пошти не відповідає типу доставки. Оберіть її повторно.';
				return [];
			}

			$point = (string)($warehouse['description'] ?? $point_label);
			$metadata['warehouse_ref'] = (string)$warehouse['ref'];
			$metadata['labels']['point'] = $point;

			return [
				'metadata' => $metadata,
				'city' => (string)($city['description'] ?? $city_label),
				'address_1' => $point,
				'address_2' => '',
				'country_id' => 220,
				'zone_id' => $zone_id,
			];
		}

		$street = $this->model_extension_PintaNovaPoshtaCod_module_street->getByName($point_label, (string)$city['ref']);

		if (!$street || (string)($street['ref'] ?? '') !== $point_ref) {
			$json['error']['novaposhta_doors_street'] = 'Вулиця Нової пошти недоступна. Оберіть її повторно.';
			return [];
		}

		$street_label = trim((string)($street['street_type'] ?? '') . ' ' . (string)($street['description'] ?? $point_label));
		$metadata['street_ref'] = (string)$street['ref'];
		$metadata['labels']['point'] = $street_label;
		$metadata['house'] = $house;
		$metadata['flat'] = $flat;

		return [
			'metadata' => $metadata,
			'city' => (string)($city['description'] ?? $city_label),
			'address_1' => 'Адресна доставка Нової пошти',
			'address_2' => $street_label . ', ' . $house . ($flat !== '' ? ', кв. ' . $flat : ''),
			'country_id' => 220,
			'zone_id' => $zone_id,
		];
	}

	private function findBsNpCheckoutAddressId(array $addresses, array $metadata): int {
		foreach ($addresses as $address) {
			$custom_field = is_array($address['custom_field'] ?? null) ? $address['custom_field'] : [];
			$saved = is_array($custom_field['bs_np_v1'] ?? null) ? $custom_field['bs_np_v1'] : [];

			if (
				(int)($saved['version'] ?? 0) !== 1 ||
				(string)($saved['type'] ?? '') !== (string)$metadata['type'] ||
				(string)($saved['area_ref'] ?? '') !== (string)$metadata['area_ref'] ||
				(string)($saved['city_ref'] ?? '') !== (string)$metadata['city_ref']
			) {
				continue;
			}

			if (
				in_array((string)$metadata['type'], ['warehouse', 'poshtomat'], true) &&
				(string)($saved['warehouse_ref'] ?? '') === (string)$metadata['warehouse_ref']
			) {
				return (int)$address['address_id'];
			}

			if (
				(string)$metadata['type'] === 'courier' &&
				(string)($saved['street_ref'] ?? '') === (string)$metadata['street_ref'] &&
				trim((string)($saved['house'] ?? '')) === trim((string)$metadata['house']) &&
				trim((string)($saved['flat'] ?? '')) === trim((string)$metadata['flat'])
			) {
				return (int)$address['address_id'];
			}
		}

		return 0;
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
REPLACE,
	'catalog/controller/checkout/shipping_address.php'
);

$controller = acc002d_replace_once(
	$controller,
	<<<'FIND'
		// Validate if shipping not required
		if (!$this->cart->hasShipping()) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
FIND,
	<<<'REPLACE'
		// Validate if shipping not required
		if (!$this->cart->hasShipping()) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		$np_address = [];

		if (!$json && array_key_exists('shipping_novaposhta_type', $this->request->post)) {
			$np_address = $this->prepareBsNpCheckoutAddress($post_info, $json);

			if ($np_address) {
				$post_info['firstname'] = trim((string)$post_info['firstname']);
				$post_info['lastname'] = trim((string)$post_info['lastname']);
				$post_info['city'] = $np_address['city'];
				$post_info['address_1'] = $np_address['address_1'];
				$post_info['address_2'] = $np_address['address_2'];
				$post_info['country_id'] = $np_address['country_id'];
				$post_info['zone_id'] = $np_address['zone_id'];
				$post_info['custom_field'] = is_array($post_info['custom_field'] ?? null) ? $post_info['custom_field'] : [];
				$post_info['custom_field']['bs_np_v1'] = $np_address['metadata'];
			}
		}

		if (!$json) {
REPLACE,
	'catalog/controller/checkout/shipping_address.php'
);

$controller = acc002d_replace_once(
	$controller,
	<<<'FIND'
		if (!$json) {
			// If no default address has been found, add it
			$address_id = $this->customer->getAddressId();

			if (!$address_id) {
				$post_info['default'] = 1;
			}

			$this->load->model('account/address');

			$json['address_id'] = $this->model_account_address->addAddress($this->customer->getId(), $post_info);

			$json['addresses'] = $this->model_account_address->getAddresses($this->customer->getId());

			$this->session->data['shipping_address'] = $this->model_account_address->getAddress($this->customer->getId(), $json['address_id']);

			$json['success'] = $this->language->get('text_success');

			// Clear payment and shipping methods
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
		}
FIND,
	<<<'REPLACE'
		if (!$json) {
			// If no default address has been found, add it
			$address_id = $this->customer->getAddressId();

			if (!$address_id) {
				$post_info['default'] = 1;
			}

			$this->load->model('account/address');

			$addresses = $this->model_account_address->getAddresses($this->customer->getId());
			$existing_np_address_id = $np_address
				? $this->findBsNpCheckoutAddressId($addresses, $np_address['metadata'])
				: 0;

			if ($existing_np_address_id) {
				$json['address_id'] = $existing_np_address_id;
				$json['address_reused'] = true;
			} else {
				$json['address_id'] = $this->model_account_address->addAddress($this->customer->getId(), $post_info);
				$json['address_reused'] = false;
			}

			$json['addresses'] = $this->model_account_address->getAddresses($this->customer->getId());

			$this->session->data['shipping_address'] = $this->model_account_address->getAddress($this->customer->getId(), $json['address_id']);

			$json['success'] = $this->language->get('text_success');

			// Clear payment and shipping methods
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
		}
REPLACE,
	'catalog/controller/checkout/shipping_address.php'
);
$files['catalog/controller/checkout/shipping_address.php']['new'] = $controller;

$reskin = $files['catalog/view/javascript/checkout-reskin.js']['old'];
$reskin = acc002d_replace_once(
	$reskin,
	<<<'FIND'
        // In saved-address mode a different type means "enter another address".
        var manualRadio = document.getElementById('input-shipping-novaposhta');
        if (manualRadio && !manualRadio.checked && savedAddressMode()) {
          $(manualRadio).prop('checked', true).trigger('change');
        }

        if (current.value !== input.value) {
          $(current).val(input.value).trigger('change');
        }

        styleControls();
FIND,
	<<<'REPLACE'
        // ACC-002D saved-to-new NP type gate: a saved point/ref must never be
        // submitted under a newly selected delivery type. Reset the point
        // while autosave is gated; the next real point selection may save.
        var manualRadio = document.getElementById('input-shipping-novaposhta');
        var switchingFromSaved = !!(manualRadio && !manualRadio.checked && savedAddressMode());
        var typeChanged = current.value !== input.value;
        var resetPoint = switchingFromSaved || typeChanged;
        var previousInitialising = !!window.bsCheckoutNpInitialising;

        if (resetPoint) {
          window.bsCheckoutNpInitialising = true;
        }

        try {
          if (switchingFromSaved) {
            $(manualRadio).prop('checked', true).trigger('change');
          }

          if (resetPoint) {
            $('#input-shipping-novaposhta-warehouse-address, #input-shipping-novaposhta-doors-street, #input-shipping-novaposhta-doors-house, #input-shipping-novaposhta-doors-flat')
              .val('').attr('data-ref', '').removeAttr('data-manual-ok');
            $('input[name="shipping_novaposhta_warehouse_ref"], input[name="shipping_novaposhta_street_ref"], [data-bs-np-form="shipping"] input[name="address_1"], [data-bs-np-form="shipping"] input[name="address_2"]').val('');
          }

          if (typeChanged) {
            $(current).val(input.value).trigger('change');
          }
        } finally {
          window.bsCheckoutNpInitialising = previousInitialising;
        }

        styleControls();
REPLACE,
	'catalog/view/javascript/checkout-reskin.js'
);
$files['catalog/view/javascript/checkout-reskin.js']['new'] = $reskin;

foreach ($files as $relative => $file) {
	if (!str_contains($file['new'], $targets[$relative])) {
		acc002d_fail("postcheck_marker_missing path={$relative}");
	}
}

echo 'cwd=' . $root . ' time=' . date('c') . "\n";

if ($dryRun) {
	echo "dry_run=ok patch={$patch} files=" . count($files) . " php_l=deferred\n";
	exit(0);
}

$backup = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . $patch . '-' . date('Ymd_His');

foreach ($files as $relative => $file) {
	$backupPath = $backup . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
	$backupDir = dirname($backupPath);

	if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
		acc002d_fail("backup_dir_failed path={$backupDir}");
	}

	if (!copy($file['path'], $backupPath)) {
		acc002d_fail("backup_failed path={$relative}");
	}
}

foreach ($files as $relative => $file) {
	if (file_put_contents($file['path'], acc002d_restore_eol($file['new'], $file['eol'])) === false) {
		foreach ($files as $restoreRelative => $restore) {
			@copy($backup . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $restoreRelative), $restore['path']);
		}
		acc002d_fail("write_failed path={$relative} rollback=attempted");
	}
}

if (!acc002d_php_l($files['catalog/controller/checkout/shipping_address.php']['path'])) {
	foreach ($files as $restoreRelative => $restore) {
		@copy($backup . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $restoreRelative), $restore['path']);
	}
	acc002d_fail("php_l_failed path=catalog/controller/checkout/shipping_address.php rollback=ok backup={$backup}");
}

foreach ($files as $relative => $file) {
	$written = file_get_contents($file['path']);
	if ($written === false || !str_contains($written, $targets[$relative])) {
		foreach ($files as $restoreRelative => $restore) {
			@copy($backup . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $restoreRelative), $restore['path']);
		}
		acc002d_fail("postwrite_check_failed path={$relative} rollback=ok backup={$backup}");
	}
}

echo "done=ok patch={$patch} files=" . count($files) . " php_l=ok backup={$backup}\n";
@unlink(__FILE__);
