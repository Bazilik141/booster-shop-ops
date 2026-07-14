<?php
/**
 * ACC-002 Phase 1 — NP address picker in account, versioned custom_field
 * metadata, and a narrow checkout hydrator for saved addresses.
 *
 * No DB schema change and no bulk data rewrite. Existing custom_field keys are
 * merged; invalid JSON blocks an edit so the raw database value is preserved.
 * Rollback: restore the runner-created backup directory printed on success.
 */
declare(strict_types=1);

$patch = basename(__FILE__, '.php');
$root = __DIR__;
$dryRun = in_array('--dry-run', $argv, true);
$targets = [
    'catalog/controller/account/address.php',
    'catalog/view/template/account/address_form.twig',
    'catalog/view/javascript/checkout-reskin.js',
];
$marker = 'ACC-002 Phase 1 NP metadata hydrator';
$files = [];
$backupDir = '';

function acc002p1_fail(string $message): void {
    throw new RuntimeException($message);
}

function acc002p1_eol(string $text, string $lineEnding): string {
    return str_replace("\n", $lineEnding, $text);
}

function acc002p1_replace_once(string $source, string $find, string $replace, string $relative): string {
    $lineEnding = str_contains($source, "\r\n") ? "\r\n" : "\n";
    $find = acc002p1_eol($find, $lineEnding);
    $replace = acc002p1_eol($replace, $lineEnding);

    if (substr_count($source, $find) !== 1) {
        acc002p1_fail('anchor_count_error path=' . $relative . ' expected=1');
    }

    return str_replace($find, $replace, $source);
}

function acc002p1_lint(string $path): void {
    if (!function_exists('exec')) {
        acc002p1_fail('php_l_unavailable exec_disabled=yes');
    }

    $output = [];
    $status = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $status);

    if ($status !== 0) {
        acc002p1_fail('php_l_failed path=' . $path . ' output=' . implode(' | ', $output));
    }
}

function acc002p1_restore(array $files, string $backupDir): void {
    if ($backupDir === '') {
        return;
    }

    foreach ($files as $file) {
        $backup = $backupDir . DIRECTORY_SEPARATOR . $file['relative'];

        if (is_file($backup)) {
            @copy($backup, $file['path']);
        }
    }
}

try {
    acc002p1_lint(__FILE__);

    // Phase-1 dependency: never install the hydrator on a checkout that lacks
    // CHECKOUT-003's programmatic-autosave gate.
    $checkoutGuard = $root . DIRECTORY_SEPARATOR . 'catalog/view/template/checkout/checkout.twig';
    if (!is_file($checkoutGuard)) {
        acc002p1_fail('dependency_missing path=catalog/view/template/checkout/checkout.twig');
    }
    $checkoutGuardSource = file_get_contents($checkoutGuard);
    if ($checkoutGuardSource === false || substr_count($checkoutGuardSource, 'CHECKOUT-003: no register autosave during NP initialisation') !== 1 || !str_contains($checkoutGuardSource, 'window.bsCheckoutNpInitialising')) {
        acc002p1_fail('dependency_missing CHECKOUT-003 autosave gate not found exactly once');
    }

    foreach ($targets as $relative) {
        $path = $root . DIRECTORY_SEPARATOR . $relative;

        if (!is_file($path)) {
            acc002p1_fail('target_missing path=' . $relative);
        }

        $source = file_get_contents($path);

        if ($source === false) {
            acc002p1_fail('read_failed path=' . $relative);
        }

        $files[$relative] = [
            'relative' => $relative,
            'path' => $path,
            'source' => $source,
            'new' => $source,
        ];
    }

    $markers = 0;
    foreach ($files as $file) {
        if (str_contains($file['source'], $marker)) {
            $markers++;
        }
    }

    if ($markers === count($files)) {
        echo "already_applied=yes patch={$patch}\n";
        @unlink(__FILE__);
        exit(0);
    }

    if ($markers !== 0) {
        acc002p1_fail('partial_marker_state=yes; restore all ACC-002 Phase 1 targets from one backup before retrying');
    }

    $controller = $files['catalog/controller/account/address.php']['new'];
    $controller = acc002p1_replace_once(
        $controller,
        <<<'FIND'
	/**
	 * Form
	 *
	 * @return void
	 */
	public function form(): void {
FIND,
        <<<'REPLACE'
	// ACC-002 Phase 1 NP metadata hydrator: the versioned payload lives in the
	// existing custom_field JSON and therefore requires no schema migration.
	private function getBsNpMetadata(array $custom_field): array {
		if (!isset($custom_field['bs_np_v1']) || !is_array($custom_field['bs_np_v1'])) {
			return [];
		}

		$metadata = $custom_field['bs_np_v1'];
		$type = isset($metadata['type']) ? (string)$metadata['type'] : '';
		$labels = isset($metadata['labels']) && is_array($metadata['labels']) ? $metadata['labels'] : [];

		if (
			(int)($metadata['version'] ?? 0) !== 1 ||
			!in_array($type, ['warehouse', 'poshtomat', 'courier'], true) ||
			empty($metadata['area_ref']) ||
			empty($metadata['city_ref']) ||
			empty($labels['area']) ||
			empty($labels['city']) ||
			empty($labels['point'])
		) {
			return [];
		}

		if (($type === 'warehouse' || $type === 'poshtomat') && empty($metadata['warehouse_ref'])) {
			return [];
		}

		if ($type === 'courier' && (empty($metadata['street_ref']) || empty($metadata['house']))) {
			return [];
		}

		return $metadata;
	}

	private function validateBsNpPayload(array $post, array &$json): array {
		$type = trim((string)($post['bs_np_type'] ?? ''));
		$area_label = trim((string)($post['bs_np_area_label'] ?? ''));
		$city_label = trim((string)($post['bs_np_city_label'] ?? ''));
		$point_label = trim((string)($post['bs_np_point_label'] ?? ''));
		$area_ref = trim((string)($post['bs_np_area_ref'] ?? ''));
		$city_ref = trim((string)($post['bs_np_city_ref'] ?? ''));
		$point_ref = trim((string)($post['bs_np_point_ref'] ?? ''));
		$house = trim((string)($post['bs_np_house'] ?? ''));
		$flat = trim((string)($post['bs_np_flat'] ?? ''));

		if (!in_array($type, ['warehouse', 'poshtomat', 'courier'], true)) {
			$json['error']['bs_np_type'] = 'Оберіть тип доставки Нової пошти.';
		}

		if (!oc_validate_length($area_label, 1, 128) || $area_ref === '') {
			$json['error']['bs_np_area'] = 'Оберіть область із довідника Нової пошти.';
		}

		if (!oc_validate_length($city_label, 2, 128) || $city_ref === '') {
			$json['error']['bs_np_city'] = 'Оберіть місто із довідника Нової пошти.';
		}

		if (!oc_validate_length($point_label, 1, 128) || $point_ref === '') {
			$json['error']['bs_np_point'] = $type === 'courier'
				? 'Оберіть вулицю із довідника Нової пошти.'
				: 'Оберіть відділення або поштомат із довідника Нової пошти.';
		}

		if ($type === 'courier' && !oc_validate_length($house, 1, 32)) {
			$json['error']['bs_np_house'] = 'Вкажіть номер будинку (до 32 символів).';
		}

		if (strlen($flat) > 32) {
			$json['error']['bs_np_flat'] = 'Номер квартири має містити до 32 символів.';
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
			$json['error']['bs_np_area'] = 'Область Нової пошти більше не доступна. Оберіть її повторно.';
			return [];
		}

		$city = $this->model_extension_PintaNovaPoshtaCod_module_city->getByName($city_label);

		if (
			!$city ||
			(string)($city['ref'] ?? '') !== $city_ref ||
			(string)($city['area'] ?? '') !== (string)$area['ref']
		) {
			$json['error']['bs_np_city'] = 'Місто Нової пошти більше не доступне для цієї області. Оберіть його повторно.';
			return [];
		}

		$zone_id = (int)$this->model_extension_PintaNovaPoshtaCod_module_area->getZoneIdByRef($area['ref']);

		if (!$zone_id) {
			$json['error']['bs_np_area'] = 'Не вдалося визначити область доставки. Оберіть область повторно.';
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
				$json['error']['bs_np_point'] = 'Обрана точка Нової пошти недоступна. Оберіть її повторно.';
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
			$json['error']['bs_np_point'] = 'Вулиця Нової пошти недоступна. Оберіть її повторно.';
			return [];
		}

		$street_label = trim((string)($street['street_type'] ?? '') . ' ' . (string)($street['description'] ?? $point_label));
		$address = $street_label . ', ' . $house . ($flat !== '' ? ', кв. ' . $flat : '');
		$metadata['street_ref'] = (string)$street['ref'];
		$metadata['labels']['point'] = $street_label;
		$metadata['house'] = $house;
		$metadata['flat'] = $flat;

		return [
			'metadata' => $metadata,
			'city' => (string)($city['description'] ?? $city_label),
			'address_1' => 'Адресна доставка Нової пошти',
			'address_2' => $address,
			'country_id' => 220,
			'zone_id' => $zone_id,
		];
	}

	public function npMetadata(): void {
		$json = ['legacy' => true, 'metadata' => null];
		$address_id = isset($this->request->get['address_id']) ? (int)$this->request->get['address_id'] : 0;

		if ($this->customer->isLogged() && $address_id) {
			$this->load->model('account/address');
			$address_info = $this->model_account_address->getAddress($this->customer->getId(), $address_id);

			if ($address_info && is_array($address_info['custom_field'] ?? null)) {
				$metadata = $this->getBsNpMetadata($address_info['custom_field']);

				if ($metadata) {
					$json['legacy'] = false;
					$json['metadata'] = $metadata;
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Form
	 *
	 * @return void
	 */
	public function form(): void {
REPLACE,
        'catalog/controller/account/address.php'
    );

    $controller = acc002p1_replace_once(
        $controller,
        <<<'FIND'
		if (!empty($address_info)) {
			$data['address_custom_field'] = $address_info['custom_field'];
		} else {
			$data['address_custom_field'] = [];
		}

		if (isset($this->request->get['address_id'])) {
FIND,
        <<<'REPLACE'
		if (!empty($address_info)) {
			$data['address_custom_field'] = is_array($address_info['custom_field']) ? $address_info['custom_field'] : [];
		} else {
			$data['address_custom_field'] = [];
		}

		$data['bs_np_metadata'] = !empty($address_info) && is_array($address_info['custom_field'] ?? null)
			? $this->getBsNpMetadata($address_info['custom_field'])
			: [];
		$data['bs_np_legacy'] = !empty($address_info) && empty($data['bs_np_metadata']);
		$data['bs_np_corrupt'] = !empty($address_info) && !is_array($address_info['custom_field'] ?? null);

		if (isset($this->request->get['address_id'])) {
REPLACE,
        'catalog/controller/account/address.php'
    );

    $controller = acc002p1_replace_once(
        $controller,
        <<<'FIND'
		if (!$json) {
			if (!oc_validate_length((string)$post_info['firstname'], 1, 32)) {
FIND,
        <<<'REPLACE'
		if (!$json) {
			// ACC-002 Phase 1 NP metadata hydrator: preserve every existing real
			// custom-field key, then replace only the reserved bs_np_v1 key.
			$post_info['custom_field'] = is_array($post_info['custom_field'] ?? null) ? $post_info['custom_field'] : [];
			$existing_custom_field = [];

			if (isset($this->request->get['address_id'])) {
				$this->load->model('account/address');
				$existing_address = $this->model_account_address->getAddress($this->customer->getId(), (int)$this->request->get['address_id']);

				if (!$existing_address) {
					$json['error']['warning'] = 'Адресу не знайдено.';
				} elseif (!is_array($existing_address['custom_field'] ?? null)) {
					// Do not turn a corrupt raw JSON value into an empty object on save.
					$json['error']['warning'] = 'Цю адресу неможливо безпечно оновити. Створіть нову адресу Нової пошти.';
				} else {
					$existing_custom_field = $existing_address['custom_field'];
				}
			}

			$post_info['custom_field'] = array_replace($existing_custom_field, $post_info['custom_field']);
			$bs_np_address = $this->validateBsNpPayload($this->request->post, $json);

			if (!$json) {
				$post_info['city'] = $bs_np_address['city'];
				$post_info['address_1'] = $bs_np_address['address_1'];
				$post_info['address_2'] = $bs_np_address['address_2'];
				$post_info['country_id'] = $bs_np_address['country_id'];
				$post_info['zone_id'] = $bs_np_address['zone_id'];
				$post_info['custom_field']['bs_np_v1'] = $bs_np_address['metadata'];
			}

			if (!oc_validate_length((string)$post_info['firstname'], 1, 32)) {
REPLACE,
        'catalog/controller/account/address.php'
    );
    $files['catalog/controller/account/address.php']['new'] = $controller;

    $twig = $files['catalog/view/template/account/address_form.twig']['new'];
    $twig = acc002p1_replace_once(
        $twig,
        <<<'FIND'
            <div class="bs-address-field bs-address-field-full required">
              <label for="input-city" class="bs-address-label">Місто<span class="bs-required-star">*</span></label>
              <input type="text" name="city" value="{{ city }}" placeholder="Місто" id="input-city" class="form-control"/>
              <div id="error-city" class="invalid-feedback"></div>
            </div>

            <div class="bs-address-field bs-address-field-full required">
              <label for="input-address-1" class="bs-address-label">Відділення або поштомат Нової пошти<span class="bs-required-star">*</span></label>
              <input type="text" name="address_1" value="{{ address_1 }}" placeholder="Наприклад: Київ, відділення №12 або поштомат №1234" id="input-address-1" class="form-control"/>
              <div id="error-address-1" class="invalid-feedback"></div>
            </div>

            <div class="bs-address-field bs-address-field-full">
              <label for="input-address-2" class="bs-address-label">Адресна доставка Нової пошти</label>
              <input type="text" name="address_2" value="{{ address_2 }}" placeholder="Наприклад: вул. Хрещатик, 1, кв. 10" id="input-address-2" class="form-control"/>
              <div class="bs-address-helper">Заповнюйте лише якщо потрібна доставка курʼєром.</div>
            </div>

            <div class="bs-address-field bs-address-field-full required">
              <label for="input-country" class="bs-address-label">{{ entry_country }}<span class="bs-required-star">*</span></label>
              <select name="country_id" id="input-country" class="form-select">
                <option value="0">{{ text_select }}</option>
                {% for country in countries %}
                  <option value="{{ country.country_id }}"{% if country.country_id == country_id %} selected{% endif %}>{{ country.name }}</option>
                {% endfor %}
              </select>
              <div id="error-country" class="invalid-feedback"></div>
            </div>

            <div class="bs-address-field bs-address-field-full required">
              <label for="input-zone" class="bs-address-label">{{ entry_zone }}<span class="bs-required-star">*</span></label>
              <select name="zone_id" id="input-zone" class="form-select">
                <option value="">{{ text_select }}</option>
                {% for zone in zones %}
                  <option value="{{ zone.zone_id }}"{% if zone.zone_id == zone_id %} selected{% endif %}>{{ zone.name }}</option>
                {% endfor %}
              </select>
              <div id="error-zone" class="invalid-feedback"></div>
            </div>
FIND,
        <<<'REPLACE'
            {# ACC-002 Phase 1 NP metadata hydrator: labels remain in stock
               fields, refs/types are written only by account/address.save. #}
            <input type="hidden" name="city" value="{{ city }}" id="input-city"/>
            <input type="hidden" name="address_1" value="{{ address_1 }}" id="input-address-1"/>
            <input type="hidden" name="address_2" value="{{ address_2 }}" id="input-address-2"/>
            <input type="hidden" name="country_id" value="220" id="input-country"/>
            <input type="hidden" name="zone_id" value="{{ zone_id }}" id="input-zone"/>

            {% if bs_np_legacy %}
              <div class="bs-address-field bs-address-field-full">
                <div class="alert alert-warning bs-np-legacy-note" role="alert">
                  {% if bs_np_corrupt %}
                    Дані цієї адреси пошкоджені. Створіть нову адресу Нової пошти, щоб не втратити старі дані.
                  {% else %}
                    Переоберіть точку Нової пошти перед збереженням. Старе значення: {{ city }}, {{ address_1 }}{% if address_2 %}, {{ address_2 }}{% endif %}.
                  {% endif %}
                </div>
              </div>
            {% endif %}

            <section id="bs-np-account-picker" class="bs-address-field bs-address-field-full bs-np-account-picker"
              data-bs-np-type="{{ bs_np_metadata.type|default('') }}"
              data-bs-np-area-ref="{{ bs_np_metadata.area_ref|default('') }}"
              data-bs-np-city-ref="{{ bs_np_metadata.city_ref|default('') }}"
              data-bs-np-warehouse-ref="{{ bs_np_metadata.warehouse_ref|default('') }}"
              data-bs-np-street-ref="{{ bs_np_metadata.street_ref|default('') }}"
              data-bs-np-area="{{ bs_np_metadata.labels.area|default('') }}"
              data-bs-np-city="{{ bs_np_metadata.labels.city|default('') }}"
              data-bs-np-point="{{ bs_np_metadata.labels.point|default('') }}"
              data-bs-np-house="{{ bs_np_metadata.house|default('') }}"
              data-bs-np-flat="{{ bs_np_metadata.flat|default('') }}">
              <div class="bs-np-account-heading">Доставка Новою поштою</div>
              <p class="bs-address-helper">Оберіть область, місто та точку тільки з довідника Нової пошти.</p>

              <input type="hidden" name="bs_np_area_ref" id="input-bs-np-area-ref" value="{{ bs_np_metadata.area_ref|default('') }}"/>
              <input type="hidden" name="bs_np_city_ref" id="input-bs-np-city-ref" value="{{ bs_np_metadata.city_ref|default('') }}"/>
              <input type="hidden" name="bs_np_point_ref" id="input-bs-np-point-ref" value="{% if bs_np_metadata.type == 'courier' %}{{ bs_np_metadata.street_ref|default('') }}{% else %}{{ bs_np_metadata.warehouse_ref|default('') }}{% endif %}"/>

              <div class="bs-np-account-grid">
                <div class="bs-np-account-control required">
                  <label for="input-bs-np-area" class="bs-address-label">Область<span class="bs-required-star">*</span></label>
                  <input type="text" name="bs_np_area_label" value="{{ bs_np_metadata.labels.area|default('') }}" id="input-bs-np-area" class="form-control" autocomplete="off" placeholder="Почніть вводити область"/>
                  <div class="bs-np-directory-dropdown" data-for="input-bs-np-area" hidden></div>
                  <div id="error-bs-np-area" class="invalid-feedback"></div>
                </div>

                <div class="bs-np-account-control required">
                  <label for="input-bs-np-city" class="bs-address-label">Місто<span class="bs-required-star">*</span></label>
                  <input type="text" name="bs_np_city_label" value="{{ bs_np_metadata.labels.city|default('') }}" id="input-bs-np-city" class="form-control" autocomplete="off" placeholder="Спочатку оберіть область"/>
                  <div class="bs-np-directory-dropdown" data-for="input-bs-np-city" hidden></div>
                  <div id="error-bs-np-city" class="invalid-feedback"></div>
                </div>

                <div class="bs-np-account-control bs-np-account-full required">
                  <label for="input-bs-np-type" class="bs-address-label">Тип доставки<span class="bs-required-star">*</span></label>
                  <select name="bs_np_type" id="input-bs-np-type" class="form-select">
                    <option value="warehouse"{% if bs_np_metadata.type == 'warehouse' %} selected{% endif %}>Відділення Нової пошти</option>
                    <option value="poshtomat"{% if bs_np_metadata.type == 'poshtomat' %} selected{% endif %}>Поштомат Нової пошти</option>
                    <option value="courier"{% if bs_np_metadata.type == 'courier' %} selected{% endif %}>Курʼєрська доставка</option>
                  </select>
                  <div id="error-bs-np-type" class="invalid-feedback"></div>
                </div>

                <div class="bs-np-account-control bs-np-account-full required">
                  <label for="input-bs-np-point" id="label-bs-np-point" class="bs-address-label">Відділення або поштомат<span class="bs-required-star">*</span></label>
                  <input type="text" name="bs_np_point_label" value="{{ bs_np_metadata.labels.point|default('') }}" id="input-bs-np-point" class="form-control" autocomplete="off" placeholder="Спочатку оберіть місто"/>
                  <div class="bs-np-directory-dropdown" data-for="input-bs-np-point" hidden></div>
                  <div id="error-bs-np-point" class="invalid-feedback"></div>
                </div>

                <div id="bs-np-courier-extra" class="bs-np-account-full bs-np-account-grid">
                  <div class="bs-np-account-control required">
                    <label for="input-bs-np-house" class="bs-address-label">Номер будинку<span class="bs-required-star">*</span></label>
                    <input type="text" name="bs_np_house" value="{{ bs_np_metadata.house|default('') }}" id="input-bs-np-house" class="form-control" autocomplete="address-line1"/>
                    <div id="error-bs-np-house" class="invalid-feedback"></div>
                  </div>
                  <div class="bs-np-account-control">
                    <label for="input-bs-np-flat" class="bs-address-label">Квартира</label>
                    <input type="text" name="bs_np_flat" value="{{ bs_np_metadata.flat|default('') }}" id="input-bs-np-flat" class="form-control" autocomplete="address-line2"/>
                    <div id="error-bs-np-flat" class="invalid-feedback"></div>
                  </div>
                </div>
              </div>
            </section>
REPLACE,
        'catalog/view/template/account/address_form.twig'
    );

    $twig = acc002p1_replace_once(
        $twig,
        <<<'FIND'
    #account-address .bs-address-helper {
      color: #6b7280;
      font-size: 0.86rem;
      line-height: 1.35;
      margin-top: 6px;
    }
FIND,
        <<<'REPLACE'
    #account-address .bs-address-helper {
      color: #6b7280;
      font-size: 0.86rem;
      line-height: 1.35;
      margin-top: 6px;
    }

    #account-address .bs-np-account-picker {
      background: #fbfdff;
      border: 1px solid #dbe7f0;
      border-radius: 10px;
      padding: 18px;
    }

    #account-address .bs-np-account-heading {
      color: #172554;
      font-size: 1.05rem;
      font-weight: 700;
    }

    #account-address .bs-np-account-grid {
      display: grid;
      gap: 16px;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      margin-top: 16px;
    }

    #account-address .bs-np-account-full {
      grid-column: 1 / -1;
    }

    #account-address .bs-np-account-control {
      min-width: 0;
      position: relative;
    }

    #account-address .bs-np-directory-dropdown {
      background: #fff;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      box-shadow: 0 10px 24px rgba(15, 23, 42, .14);
      display: grid;
      gap: 2px;
      left: 0;
      max-height: 232px;
      overflow: auto;
      padding: 4px;
      position: absolute;
      right: 0;
      top: calc(100% + 4px);
      z-index: 25;
    }

    #account-address .bs-np-directory-dropdown[hidden] {
      display: none;
    }

    #account-address .bs-np-directory-option {
      background: transparent;
      border: 0;
      border-radius: 6px;
      color: #1e293b;
      cursor: pointer;
      padding: 9px 10px;
      text-align: left;
      width: 100%;
    }

    #account-address .bs-np-directory-option:hover,
    #account-address .bs-np-directory-option:focus {
      background: #eaf6ff;
      outline: 0;
    }

    #account-address .bs-np-legacy-note {
      margin: 0;
    }

    @media (max-width: 767.98px) {
      #account-address .bs-np-account-grid {
        grid-template-columns: 1fr;
      }
    }
REPLACE,
        'catalog/view/template/account/address_form.twig'
    );

    $twig = acc002p1_replace_once(
        $twig,
        <<<'FIND'
//--></script>
{{ footer }}
FIND,
        <<<'REPLACE'
//--></script>
<script type="text/javascript"><!--
// ACC-002 Phase 1 NP metadata hydrator: account-side picker reuses the live
// Pinta directory endpoints and sends only selected labels plus their refs.
(function($) {
  var $form = $('#form-address');
  var $picker = $('#bs-np-account-picker');

  if (!$form.length || !$picker.length) {
    return;
  }

  var endpoint = (window.pinta_catalog || '') + 'index.php?route=extension/PintaNovaPoshtaCod/shipping/pinta_nova_poshta|';
  var lookupTimer = 0;

  function clean(value) {
    return $.trim(String(value || '').replace(/\s+/g, ' '));
  }

  function currentType() {
    return $('#input-bs-np-type').val();
  }

  function moduleType() {
    return currentType() === 'poshtomat' ? 'poshtoma' : (currentType() === 'courier' ? 'doors' : 'warehouse');
  }

  function ref(name) {
    return clean($('#input-bs-np-' + name + '-ref').val());
  }

  function setRef(name, value) {
    $('#input-bs-np-' + name + '-ref').val(value || '');
  }

  function field(name) {
    return $('#input-bs-np-' + name);
  }

  function dropdown($input) {
    return $input.siblings('.bs-np-directory-dropdown');
  }

  function hideDropdown($input) {
    dropdown($input).empty().prop('hidden', true);
  }

  function clearErrors() {
    $picker.find('.is-invalid').removeClass('is-invalid');
    $picker.find('.invalid-feedback').removeClass('d-block').text('');
  }

  function showError(name, message) {
    var $input = field(name);
    $input.addClass('is-invalid');
    $('#error-bs-np-' + name).text(message).addClass('d-block');
  }

  function clearAfterArea() {
    field('city').val('');
    field('point').val('');
    setRef('city', '');
    setRef('point', '');
    field('house').val('');
    field('flat').val('');
  }

  function clearAfterCity() {
    field('point').val('');
    setRef('point', '');
    field('house').val('');
    field('flat').val('');
  }

  function updateMode() {
    var courier = currentType() === 'courier';
    $('#bs-np-courier-extra').toggle(courier);
    $('#label-bs-np-point').html(courier
      ? 'Вулиця<span class="bs-required-star">*</span>'
      : 'Відділення або поштомат<span class="bs-required-star">*</span>');
    field('point').attr('placeholder', ref('city') ? (courier ? 'Почніть вводити вулицю' : 'Почніть вводити номер або адресу') : 'Спочатку оберіть місто');
  }

  function updateAvailability() {
    var areaReady = !!ref('area');
    var cityReady = !!ref('city');
    field('city').prop('disabled', !areaReady).attr('placeholder', areaReady ? 'Мінімум 2 символи' : 'Спочатку оберіть область');
    field('point').prop('disabled', !cityReady);
    updateMode();
  }

  function configFor($input) {
    var id = $input.attr('id');

    if (id === 'input-bs-np-area') {
      return { method: 'searchArea', min: 1, data: { string: $input.val() } };
    }

    if (id === 'input-bs-np-city' && ref('area')) {
      return { method: 'searchCity', min: 2, data: { string: $input.val(), area: field('area').val() } };
    }

    if (id === 'input-bs-np-point' && ref('city')) {
      return currentType() === 'courier'
        ? { method: 'searchStreet', min: 1, data: { string: $input.val(), city: field('city').val() } }
        : { method: 'searchWarehouse', min: 1, data: { string: $input.val(), city: field('city').val(), type: moduleType() } };
    }

    return null;
  }

  function renderOptions($input, options) {
    var $dropdown = dropdown($input).empty();

    (options || []).forEach(function(option) {
      var $button = $('<button type="button" class="bs-np-directory-option"></button>');
      $button.attr('data-ref', option.value || '').text(clean(option.label || option.value || ''));
      $dropdown.append($button);
    });

    $dropdown.prop('hidden', !$dropdown.children().length);
  }

  function search($input) {
    var config = configFor($input);
    var value = clean($input.val());

    if (!config || value.length < config.min || $input.prop('disabled')) {
      hideDropdown($input);
      return;
    }

    $.ajax({
      url: endpoint + config.method,
      type: 'post',
      dataType: 'json',
      data: config.data,
      success: function(json) {
        if (document.activeElement === $input[0]) {
          renderOptions($input, json && $.isArray(json.options) ? json.options : []);
        }
      },
      error: function() {
        hideDropdown($input);
      }
    });
  }

  function scheduleSearch($input) {
    window.clearTimeout(lookupTimer);
    lookupTimer = window.setTimeout(function() {
      search($input);
    }, 220);
  }

  function choose($input, label, selectedRef) {
    $input.val(label);
    hideDropdown($input);

    if ($input.attr('id') === 'input-bs-np-area') {
      setRef('area', selectedRef);
      clearAfterArea();
    } else if ($input.attr('id') === 'input-bs-np-city') {
      setRef('city', selectedRef);
      clearAfterCity();
    } else {
      setRef('point', selectedRef);
    }

    updateAvailability();
  }

  $(document).on('input.acc002NpPicker', '#input-bs-np-area, #input-bs-np-city, #input-bs-np-point', function() {
    var $input = $(this);

    if (this.id === 'input-bs-np-area') {
      setRef('area', '');
      clearAfterArea();
    } else if (this.id === 'input-bs-np-city') {
      setRef('city', '');
      clearAfterCity();
    } else {
      setRef('point', '');
    }

    updateAvailability();
    scheduleSearch($input);
  });

  $(document).on('focus.acc002NpPicker', '#input-bs-np-area, #input-bs-np-city, #input-bs-np-point', function() {
    search($(this));
  });

  $(document).on('blur.acc002NpPicker', '#input-bs-np-area, #input-bs-np-city, #input-bs-np-point', function() {
    var $input = $(this);
    window.setTimeout(function() {
      hideDropdown($input);
    }, 160);
  });

  $(document).on('mousedown.acc002NpPicker', '.bs-np-directory-option', function(event) {
    event.preventDefault();
  });

  $(document).on('click.acc002NpPicker', '.bs-np-directory-option', function() {
    var $button = $(this);
    var inputId = $button.closest('.bs-np-directory-dropdown').attr('data-for');
    choose($('#' + inputId), clean($button.text()), clean($button.attr('data-ref')));
  });

  $('#input-bs-np-type').on('change.acc002NpPicker', function() {
    clearAfterCity();
    updateAvailability();
  });

  $form.on('submit.acc002NpPicker', function(event) {
    clearErrors();
    var valid = true;

    if (!ref('area')) {
      showError('area', 'Оберіть область із довідника Нової пошти.');
      valid = false;
    }

    if (!ref('city')) {
      showError('city', 'Оберіть місто із довідника Нової пошти.');
      valid = false;
    }

    if (!ref('point')) {
      showError('point', currentType() === 'courier' ? 'Оберіть вулицю із довідника Нової пошти.' : 'Оберіть точку Нової пошти із довідника.');
      valid = false;
    }

    if (currentType() === 'courier' && !clean(field('house').val())) {
      showError('house', 'Вкажіть номер будинку.');
      valid = false;
    }

    if (!valid) {
      event.preventDefault();
      return false;
    }
  });

  updateAvailability();
})(jQuery);
//--></script>
{{ footer }}
REPLACE,
        'catalog/view/template/account/address_form.twig'
    );
    $files['catalog/view/template/account/address_form.twig']['new'] = $twig;

    $reskin = $files['catalog/view/javascript/checkout-reskin.js']['new'];
    $reskin = acc002p1_replace_once(
        $reskin,
        <<<'FIND'
    if (savedAddressMode()) {
      var parsed = parseAddressText(selectedSavedAddressText());
      value = parsed ? parsed.type : null;
    }
FIND,
        <<<'REPLACE'
    if (savedAddressMode()) {
      var metadata = savedNpMetadataForSelectedAddress();
      var parsed = metadata ? null : parseAddressText(selectedSavedAddressText());
      value = metadata ? moduleTypeFromSavedMetadata(metadata) : (parsed ? parsed.type : null);
    }
REPLACE,
        'catalog/view/javascript/checkout-reskin.js'
    );

    $reskin = acc002p1_replace_once(
        $reskin,
        <<<'FIND'
    if (savedAddressMode()) {
      var parsed = parseAddressText(selectedSavedAddressText());

      if (parsed && parsed.type) {
        var typeLabel = TYPE_SUMMARY_LABEL[parsed.type] || '';
        var detail = parsed.type === 'doors' ? parsed.street : (parsed.number ? '№' + parsed.number : '');
        return text('Нова пошта · ' + typeLabel + (detail ? ' ' + detail : ''));
      }

      return selectedSavedAddressText() ? 'Нова пошта · збережена адреса' : '';
    }
FIND,
        <<<'REPLACE'
    if (savedAddressMode()) {
      var metadata = savedNpMetadataForSelectedAddress();

      if (metadata) {
        var metadataType = moduleTypeFromSavedMetadata(metadata);
        var metadataDetail = metadata.labels && metadata.labels.point ? metadata.labels.point : '';
        return text('Нова пошта · ' + (TYPE_SUMMARY_LABEL[metadataType] || 'збережена адреса') + (metadataDetail ? ' ' + metadataDetail : ''));
      }

      // Legacy addresses retain text parsing for presentation only. Hydration
      // is never decided from this fallback.
      var parsed = parseAddressText(selectedSavedAddressText());

      if (parsed && parsed.type) {
        var typeLabel = TYPE_SUMMARY_LABEL[parsed.type] || '';
        var detail = parsed.type === 'doors' ? parsed.street : (parsed.number ? '№' + parsed.number : '');
        return text('Нова пошта · ' + typeLabel + (detail ? ' ' + detail : ''));
      }

      return selectedSavedAddressText() ? 'Нова пошта · збережена адреса' : '';
    }
REPLACE,
        'catalog/view/javascript/checkout-reskin.js'
    );

    $reskin = acc002p1_replace_once(
        $reskin,
        <<<'FIND'
  $(root).on('change input', 'input, select, textarea', scheduleSync);
FIND,
        <<<'REPLACE'
  // ACC-002 Phase 1 NP metadata hydrator. Saved address text is never used
  // to hydrate refs; only the account-owned bs_np_v1 contract is accepted.
  var savedNpHydration = { addressId: '', metadata: null, state: '' };
  var savedNpRequest = 0;

  function moduleTypeFromSavedMetadata(metadata) {
    if (!metadata) {
      return '';
    }

    return metadata.type === 'poshtomat' ? 'poshtoma' : (metadata.type === 'courier' ? 'doors' : 'warehouse');
  }

  function savedNpMetadataForSelectedAddress() {
    var select = savedAddressSelect();

    if (!savedAddressMode() || !select || String(select.value || '') !== savedNpHydration.addressId || savedNpHydration.state !== 'ready') {
      return null;
    }

    return savedNpHydration.metadata;
  }

  function savedNpPrompt(message) {
    var host = document.getElementById('checkout-shipping-address');

    if (!host) {
      return;
    }

    var prompt = document.getElementById('bs-np-saved-address-prompt');

    if (!prompt) {
      prompt = document.createElement('div');
      prompt.id = 'bs-np-saved-address-prompt';
      prompt.className = 'alert alert-warning bs-np-saved-address-prompt';
      host.insertBefore(prompt, host.firstChild);
    }

    prompt.innerHTML = '<span></span><button type="button" class="btn btn-sm btn-outline-primary" data-bs-np-repick>Переобрати адресу НП</button>';
    prompt.querySelector('span').textContent = message;
    prompt.hidden = false;
  }

  function clearSavedNpPrompt() {
    var prompt = document.getElementById('bs-np-saved-address-prompt');

    if (prompt) {
      prompt.hidden = true;
    }
  }

  function npDirectoryOption(method, data, expectedRef, done) {
    $.ajax({
      url: (window.pinta_catalog || '') + 'index.php?route=extension/PintaNovaPoshtaCod/shipping/pinta_nova_poshta|' + method,
      type: 'post',
      dataType: 'json',
      data: data,
      success: function(json) {
        var options = json && Array.isArray(json.options) ? json.options : [];
        var match = options.some(function(option) {
          return String(option && option.value || '') === String(expectedRef || '');
        });
        done(match);
      },
      error: function() {
        done(false);
      }
    });
  }

  function validateSavedNpMetadata(metadata, done) {
    var labels = metadata && metadata.labels ? metadata.labels : {};
    var moduleType = moduleTypeFromSavedMetadata(metadata);

    if (!labels.area || !labels.city || !labels.point || !metadata.area_ref || !metadata.city_ref || !moduleType) {
      done(false);
      return;
    }

    npDirectoryOption('searchArea', { string: labels.area }, metadata.area_ref, function(areaOk) {
      if (!areaOk) {
        done(false);
        return;
      }

      npDirectoryOption('searchCity', { string: labels.city, area: labels.area }, metadata.city_ref, function(cityOk) {
        if (!cityOk) {
          done(false);
          return;
        }

        if (moduleType === 'doors') {
          npDirectoryOption('searchStreet', { string: labels.point, city: labels.city }, metadata.street_ref, done);
        } else {
          npDirectoryOption('searchWarehouse', { string: labels.point, city: labels.city, type: moduleType }, metadata.warehouse_ref, done);
        }
      });
    });
  }

  function setSavedNpField(id, label, ref, hiddenName) {
    var input = $('#' + id);
    input.val(label || '').attr('data-ref', ref || '');
    $('input[name="' + hiddenName + '"]').val(ref || '');
  }

  function applySavedNpMetadata(metadata) {
    var labels = metadata.labels || {};
    var previousFlag = !!window.bsCheckoutNpInitialising;
    var moduleType = moduleTypeFromSavedMetadata(metadata);

    window.bsCheckoutNpInitialising = true;

    try {
      $('#input-shipping-novaposhta-type').val(moduleType).trigger('change');
      setSavedNpField('input-shipping-novaposhta-area', labels.area, metadata.area_ref, 'shipping_novaposhta_area_ref');
      setSavedNpField('input-shipping-novaposhta-city', labels.city, metadata.city_ref, 'shipping_novaposhta_city_ref');

      if (moduleType === 'doors') {
        setSavedNpField('input-shipping-novaposhta-doors-street', labels.point, metadata.street_ref, 'shipping_novaposhta_street_ref');
        $('#input-shipping-novaposhta-doors-house').val(metadata.house || '');
        $('#input-shipping-novaposhta-doors-flat').val(metadata.flat || '');
      } else {
        setSavedNpField('input-shipping-novaposhta-warehouse-address', labels.point, metadata.warehouse_ref, 'shipping_novaposhta_warehouse_ref');
      }

      if (window.bsCheckoutUpdateNpCascade) {
        window.bsCheckoutUpdateNpCascade();
      }

      if (window.bsCheckoutSyncNpFields) {
        window.bsCheckoutSyncNpFields();
      }
    } finally {
      window.bsCheckoutNpInitialising = previousFlag;
    }
  }

  function hydrateSavedNpAddress() {
    var select = savedAddressSelect();

    if (!savedAddressMode() || !select || !select.value) {
      savedNpHydration = { addressId: '', metadata: null, state: '' };
      clearSavedNpPrompt();
      return;
    }

    var addressId = String(select.value);

    if (savedNpHydration.addressId === addressId && (savedNpHydration.state === 'loading' || savedNpHydration.state === 'ready' || savedNpHydration.state === 'legacy' || savedNpHydration.state === 'stale')) {
      return;
    }

    var requestId = ++savedNpRequest;
    savedNpHydration = { addressId: addressId, metadata: null, state: 'loading' };

    $.ajax({
      url: 'index.php?route=account/address.npMetadata&address_id=' + encodeURIComponent(addressId),
      dataType: 'json',
      success: function(json) {
        if (requestId !== savedNpRequest) {
          return;
        }

        if (!json || json.legacy || !json.metadata) {
          savedNpHydration.state = 'legacy';
          savedNpPrompt('Для цієї збереженої адреси потрібно повторно обрати точку Нової пошти.');
          scheduleSync();
          return;
        }

        validateSavedNpMetadata(json.metadata, function(valid) {
          if (requestId !== savedNpRequest) {
            return;
          }

          if (!valid) {
            savedNpHydration.state = 'stale';
            savedNpPrompt('Збережена точка Нової пошти більше не доступна. Оберіть іншу адресу.');
            scheduleSync();
            return;
          }

          savedNpHydration.metadata = json.metadata;
          savedNpHydration.state = 'ready';
          applySavedNpMetadata(json.metadata);
          clearSavedNpPrompt();
          scheduleSync();
        });
      },
      error: function() {
        if (requestId !== savedNpRequest) {
          return;
        }

        savedNpHydration.state = 'stale';
        savedNpPrompt('Не вдалося перевірити збережену адресу Нової пошти. Оберіть точку повторно.');
      }
    });
  }

  $(document).on('click.acc002NpRepick', '[data-bs-np-repick]', function() {
    var manual = $('#input-shipping-novaposhta');
    var previousFlag = !!window.bsCheckoutNpInitialising;

    window.bsCheckoutNpInitialising = true;
    try {
      manual.prop('checked', true).trigger('change');
      ['input-shipping-novaposhta-area', 'input-shipping-novaposhta-city', 'input-shipping-novaposhta-warehouse-address', 'input-shipping-novaposhta-doors-street'].forEach(function(id) {
        var input = $('#' + id);
        input.val('').attr('data-ref', '');
      });
      $('input[name="shipping_novaposhta_area_ref"], input[name="shipping_novaposhta_city_ref"], input[name="shipping_novaposhta_warehouse_ref"], input[name="shipping_novaposhta_street_ref"]').val('');
      if (window.bsCheckoutUpdateNpCascade) {
        window.bsCheckoutUpdateNpCascade();
      }
    } finally {
      window.bsCheckoutNpInitialising = previousFlag;
    }
    clearSavedNpPrompt();
  });

  $(document).on('change.acc002NpHydrator', '#input-shipping-address, #input-shipping-existing, #input-shipping-novaposhta', function() {
    window.setTimeout(hydrateSavedNpAddress, 0);
  });

  $(function() {
    hydrateSavedNpAddress();
  });

  $(root).on('change input', 'input, select, textarea', scheduleSync);
REPLACE,
        'catalog/view/javascript/checkout-reskin.js'
    );
    $files['catalog/view/javascript/checkout-reskin.js']['new'] = $reskin;

    foreach ($files as $relative => $file) {
        if (!str_contains($file['new'], $marker)) {
            acc002p1_fail('postcheck_marker_missing path=' . $relative);
        }
    }

    if (!str_contains($controller, 'public function npMetadata(): void') || !str_contains($controller, "['bs_np_v1']")) {
        acc002p1_fail('postcheck_controller_contract_missing');
    }

    if (!str_contains($twig, 'id="bs-np-account-picker"') || !str_contains($twig, "searchArea")) {
        acc002p1_fail('postcheck_account_picker_missing');
    }

    if (!str_contains($reskin, 'function hydrateSavedNpAddress()') || !str_contains($reskin, 'bsCheckoutNpInitialising = true')) {
        acc002p1_fail('postcheck_checkout_hydrator_missing');
    }

    if ($dryRun) {
        echo "dry_run=ok patch={$patch} files=" . count($files) . " php_l=ok\n";
        exit(0);
    }

    $backupDir = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . $patch . '-' . date('Ymd_His');
    foreach ($files as $file) {
        $backup = $backupDir . DIRECTORY_SEPARATOR . $file['relative'];
        $dir = dirname($backup);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            acc002p1_fail('backup_dir_create_failed path=' . $dir);
        }

        if (!copy($file['path'], $backup)) {
            acc002p1_fail('backup_failed path=' . $file['relative']);
        }
    }

    foreach ($files as $file) {
        if (file_put_contents($file['path'], $file['new']) === false) {
            acc002p1_fail('write_failed path=' . $file['relative']);
        }
    }

    acc002p1_lint($files['catalog/controller/account/address.php']['path']);

    foreach ($files as $file) {
        $written = file_get_contents($file['path']);
        if ($written === false || !str_contains($written, $marker)) {
            acc002p1_fail('postwrite_verify_failed path=' . $file['relative']);
        }
    }

    echo "done=ok patch={$patch} files=" . count($files) . " php_l=ok backup={$backupDir}\n";
    @unlink(__FILE__);
} catch (Throwable $e) {
    acc002p1_restore($files, $backupDir);
    echo 'done=error patch=' . $patch . ' message=' . $e->getMessage() . "\n";
    exit(1);
}
