<?php
/**
 * CHECKOUT-005 — guest account creation: persist Nova Poshta metadata and apply First15.
 *
 * Upload to OpenCart public_html and run once. No DB schema/data migration.
 * Rollback: restore files from _patch_backups/CHECKOUT-005_guest-account-np-first15_20260715-<timestamp>/.
 */
declare(strict_types=1);

const PATCH_ID = 'CHECKOUT-005_guest-account-np-first15_20260715';

function out(string $line): void { echo $line . PHP_EOL; }
function fail(string $line): void { out('error=' . $line); exit(1); }
function countAnchor(string $contents, string $anchor, string $file): void {
	$count = substr_count($contents, $anchor);
	if ($count !== 1) {
		fail('anchor_count=' . $count . ' expected=1 file=' . $file);
	}
}
function replaceOnce(string $contents, string $anchor, string $replacement, string $file): string {
	$eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
	$anchor = str_replace("\n", $eol, $anchor);
	$replacement = str_replace("\n", $eol, $replacement);
	countAnchor($contents, $anchor, $file);
	return str_replace($anchor, $replacement, $contents);
}
function writeChecked(string $path, string $contents, string $backup_dir): void {
	$backup = $backup_dir . DIRECTORY_SEPARATOR . $path;
	$backup_parent = dirname($backup);
	if (!is_dir($backup_parent) && !mkdir($backup_parent, 0775, true) && !is_dir($backup_parent)) {
		fail('backup_dir_create_failed path=' . $backup_parent);
	}
	if (!copy($path, $backup)) {
		fail('backup_copy_failed file=' . $path);
	}
	if (file_put_contents($path, $contents) === false) {
		@copy($backup, $path);
		fail('write_failed file=' . $path);
	}
}

$files = [
	'catalog/controller/checkout/payment_method.php',
	'catalog/view/template/checkout/checkout.twig',
	'catalog/model/checkout/booster_coupon.php',
];

foreach ($files as $file) {
	if (!is_file($file)) {
		fail('missing_file=' . $file);
	}
}

$payment_file = $files[0];
$twig_file = $files[1];
$coupon_file = $files[2];
$payment = file_get_contents($payment_file);
$twig = file_get_contents($twig_file);
$coupon = file_get_contents($coupon_file);

if ($payment === false || $twig === false || $coupon === false) {
	fail('read_failed');
}

$payment_marker = 'CHECKOUT-005 structured Nova Poshta handoff for guest account creation.';
$twig_marker = 'CHECKOUT-005: include validated NP refs in the account-creation handoff.';
$has_payment = strpos($payment, $payment_marker) !== false;
$has_twig = strpos($twig, $twig_marker) !== false;

if ($has_payment || $has_twig) {
	if ($has_payment && $has_twig) {
		out('already_applied=yes');
		exit(0);
	}
	fail('partial_marker_detected');
}

if (strpos($coupon, 'public function applyPendingWelcomeCoupon') === false) {
	fail('coupon_contract_missing=applyPendingWelcomeCoupon');
}

$inject_before_customer_model = <<<'PHP'
		// CHECKOUT-005 structured Nova Poshta handoff for guest account creation.
		// register.save already carries the selected NP point in its session address.
		// The pre-confirm account call must receive and validate the same refs before
		// addAddress(), otherwise it creates an unrecoverable legacy address-book row.
		$checkout005_np_address = [];

		if (array_key_exists('shipping_novaposhta_type', $this->request->post)) {
			$checkout005_np_address = $this->checkout005PrepareNpAddress($this->request->post, $json);

			if (!empty($json['error'])) {
				$this->checkout001Json($json);
				return;
			}

			$this->checkout005ApplyNpAddressToCheckoutSession($checkout005_np_address);
		}

		$this->load->model('account/customer');
PHP;

$payment = replaceOnce(
	$payment,
	"\t\t\$this->load->model('account/customer');",
	$inject_before_customer_model,
	$payment_file
);

$inject_first15 = <<<'PHP'
			$this->session->data['checkout001_account_customer_id'] = $created_customer_id;

			// The account is now authenticated and no order exists yet. Applying the
			// pending welcome coupon here makes the subsequent confirm/Hutko request use
			// the discounted total without issuing a coupon-triggered order-write route.
			if (empty($this->session->data['coupon'])) {
				$this->session->data['welcome_coupon_pending'] = 'First15';
				$this->load->model('checkout/booster_coupon');
				$checkout005_coupon_result = $this->model_checkout_booster_coupon->applyPendingWelcomeCoupon($email);
				$json['first15_applied'] = !empty($checkout005_coupon_result['success']);
			}
PHP;

$payment = replaceOnce(
	$payment,
	"\t\t\t\$this->session->data['checkout001_account_customer_id'] = \$created_customer_id;",
	$inject_first15,
	$payment_file
);

$helper = <<<'PHP'

	/**
	 * CHECKOUT-005: mirror the validated NP address contract used by
	 * checkout/shipping_address.save before CHECKOUT-001 persists an address.
	 */
	private function checkout005PrepareNpAddress(array $post, array &$json): array {
		$module_type = trim((string)($post['shipping_novaposhta_type'] ?? ''));
		$type_map = ['warehouse' => 'warehouse', 'poshtoma' => 'poshtomat', 'doors' => 'courier'];
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
			$json['error'][$type === 'courier' ? 'novaposhta_doors_street' : 'novaposhta_warehouse_address'] = 'Оберіть точку Нової пошти із довідника.';
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
		$city = $this->model_extension_PintaNovaPoshtaCod_module_city->getByName($city_label);

		if (!$area || (string)($area['ref'] ?? '') !== $area_ref) {
			$json['error']['novaposhta_area'] = 'Область Нової пошти більше не доступна. Оберіть її повторно.';
			return [];
		}
		if (!$city || (string)($city['ref'] ?? '') !== $city_ref || (string)($city['area'] ?? '') !== (string)$area['ref']) {
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
			if (!$warehouse || (string)($warehouse['city_ref'] ?? '') !== (string)$city['ref'] || ($type === 'poshtomat' && (string)($warehouse['type_of_warehouse'] ?? '') !== $poshtomat_type_ref) || ($type === 'warehouse' && (string)($warehouse['type_of_warehouse'] ?? '') === $poshtomat_type_ref)) {
				$json['error']['novaposhta_warehouse_address'] = 'Обрана точка Нової пошти не відповідає типу доставки. Оберіть її повторно.';
				return [];
			}
			$point = (string)($warehouse['description'] ?? $point_label);
			$metadata['warehouse_ref'] = (string)$warehouse['ref'];
			$metadata['labels']['point'] = $point;
			return ['metadata' => $metadata, 'city' => (string)($city['description'] ?? $city_label), 'address_1' => $point, 'address_2' => '', 'country_id' => 220, 'zone_id' => $zone_id];
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
		return ['metadata' => $metadata, 'city' => (string)($city['description'] ?? $city_label), 'address_1' => 'Адресна доставка Нової пошти', 'address_2' => $street_label . ', ' . $house . ($flat !== '' ? ', кв. ' . $flat : ''), 'country_id' => 220, 'zone_id' => $zone_id];
	}

	private function checkout005ApplyNpAddressToCheckoutSession(array $np_address): void {
		foreach (['payment_address', 'shipping_address'] as $key) {
			$address = $this->session->data[$key] ?? [];
			if (!is_array($address)) {
				continue;
			}
			$address['city'] = $np_address['city'];
			$address['address_1'] = $np_address['address_1'];
			$address['address_2'] = $np_address['address_2'];
			$address['country_id'] = $np_address['country_id'];
			$address['zone_id'] = $np_address['zone_id'];
			$address['custom_field'] = is_array($address['custom_field'] ?? null) ? $address['custom_field'] : [];
			$address['custom_field']['bs_np_v1'] = $np_address['metadata'];
			$this->session->data[$key] = $address;
		}
	}
PHP;

$payment = replaceOnce(
	$payment,
	"\n\tprivate function checkout001CreateAddresses(int \$customer_id): array {",
	$helper . "\n\tprivate function checkout001CreateAddresses(int \$customer_id): array {",
	$payment_file
);

$twig_old = <<<'TWIG'
      $.ajax({
        url: 'index.php?route=checkout/payment_method.createAccount&language={{ language }}',
        type: 'post',
        data: {
          create_account_opt_in: '1'
        },
        dataType: 'json',
TWIG;

$twig_new = <<<'TWIG'
      // CHECKOUT-005: include validated NP refs in the account-creation handoff.
      // The server rebuilds the exact structured address before adding it to the new account.
      window.bsCheckoutSyncNpFields();
      var checkout005AccountPayload = $('#form-register').serializeArray();
      $('#form-shipping-address_nova_poshta').serializeArray().forEach(function(field) {
        if (field.name.indexOf('shipping_novaposhta_') === 0) {
          checkout005AccountPayload.push(field);
        }
      });
      checkout005AccountPayload.push({ name: 'create_account_opt_in', value: '1' });

      $.ajax({
        url: 'index.php?route=checkout/payment_method.createAccount&language={{ language }}',
        type: 'post',
        data: checkout005AccountPayload,
        dataType: 'json',
TWIG;

$twig = replaceOnce($twig, $twig_old, $twig_new, $twig_file);

$backup_dir = '_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
writeChecked($payment_file, $payment, $backup_dir);
writeChecked($twig_file, $twig, $backup_dir);

$lint_output = [];
$lint_code = 0;
exec('php -l ' . escapeshellarg($payment_file) . ' 2>&1', $lint_output, $lint_code);
if ($lint_code !== 0) {
	@copy($backup_dir . DIRECTORY_SEPARATOR . $payment_file, $payment_file);
	@copy($backup_dir . DIRECTORY_SEPARATOR . $twig_file, $twig_file);
	fail('php_lint_failed ' . implode(' ', $lint_output));
}

out('changed_files=2');
out('php_lint=ok');
out('backup=' . $backup_dir);
out('cache_clear=required');
out('done=ok');
@unlink(__FILE__);
