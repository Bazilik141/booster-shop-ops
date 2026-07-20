<?php
declare(strict_types=1);

/*
 * ACC-002A — saved Nova Poshta reference validation — 2026-07-14
 *
 * No DB changes. Replaces the browser's text-autocomplete recheck of a saved
 * NP point with authorised server-side validation of the stored reference.
 */

$patch = 'ACC-002A_saved-np-ref-validation_20260714';
$root = getcwd();
$dryRun = in_array('--dry-run', $argv ?? [], true);

function acc002a_fail(string $message): void {
	fwrite(STDERR, "error={$message}\n");
	exit(1);
}

function acc002a_lf(string $value): string {
	return str_replace(["\r\n", "\r"], "\n", $value);
}

function acc002a_eol(string $value): string {
	return str_contains($value, "\r\n") ? "\r\n" : "\n";
}

function acc002a_restore_eol(string $value, string $eol): string {
	return $eol === "\n" ? $value : str_replace("\n", $eol, $value);
}

function acc002a_replace_once(string $source, string $find, string $replace, string $relative): string {
	$count = substr_count($source, $find);

	if ($count !== 1) {
		acc002a_fail("anchor_count path={$relative} expected=1 actual={$count}");
	}

	return str_replace($find, $replace, $source);
}

function acc002a_php_l(string $path): bool {
	$output = [];
	$code = 0;
	exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
	return $code === 0;
}

$targets = [
	'catalog/controller/account/address.php' => 'ACC-002A saved NP ref validation',
	'catalog/view/javascript/checkout-reskin.js' => 'ACC-002A saved NP ref validation',
];

$files = [];
$states = [];

foreach ($targets as $relative => $marker) {
	$path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

	if (!is_file($path)) {
		acc002a_fail("target_missing path={$relative}");
	}

	$raw = file_get_contents($path);
	if ($raw === false) {
		acc002a_fail("read_failed path={$relative}");
	}

	$files[$relative] = [
		'path' => $path,
		'eol' => acc002a_eol($raw),
		'old' => acc002a_lf($raw),
	];
	$states[] = str_contains($raw, $marker);
}

if (count(array_filter($states)) === count($states)) {
	echo "already_applied=yes patch={$patch}\n";
	exit(0);
}

if (count(array_filter($states)) !== 0) {
	acc002a_fail('partial_state_detected restore_from_backup_required');
}

$account = $files['catalog/controller/account/address.php']['old'];
$account = acc002a_replace_once(
	$account,
	<<<'FIND'
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
FIND,
	<<<'REPLACE'
	// ACC-002A saved NP ref validation: validate the persisted entity by its
	// immutable reference. Autocomplete is a search aid, not an availability API.
	private function isBsNpMetadataAvailable(array $metadata): bool {
		$type = (string)($metadata['type'] ?? '');
		$labels = is_array($metadata['labels'] ?? null) ? $metadata['labels'] : [];

		$this->load->model('extension/PintaNovaPoshtaCod/module/warehouse');
		$this->load->model('extension/PintaNovaPoshtaCod/module/street');

		if ($type === 'warehouse' || $type === 'poshtomat') {
			$warehouse_ref = (string)($metadata['warehouse_ref'] ?? '');
			$warehouse = $warehouse_ref !== ''
				? $this->model_extension_PintaNovaPoshtaCod_module_warehouse->getByRef($warehouse_ref)
				: [];
			$poshtomat_type_ref = 'f9316480-5f2d-425d-bc2c-ac7cd29decf0';

			return (bool)$warehouse
				&& (string)($warehouse['ref'] ?? '') === $warehouse_ref
				&& (string)($warehouse['city_ref'] ?? '') === (string)($metadata['city_ref'] ?? '')
				&& ($type === 'poshtomat'
					? (string)($warehouse['type_of_warehouse'] ?? '') === $poshtomat_type_ref
					: (string)($warehouse['type_of_warehouse'] ?? '') !== $poshtomat_type_ref);
		}

		if ($type === 'courier') {
			$street_ref = (string)($metadata['street_ref'] ?? '');
			$street_label = (string)($labels['point'] ?? '');
			$city_ref = (string)($metadata['city_ref'] ?? '');
			$street = ($street_label !== '' && $city_ref !== '')
				? $this->model_extension_PintaNovaPoshtaCod_module_street->getByName($street_label, $city_ref)
				: [];

			return (bool)$street && (string)($street['ref'] ?? '') === $street_ref;
		}

		return false;
	}

	public function npMetadata(): void {
		$json = ['legacy' => true, 'stale' => false, 'metadata' => null];
		$address_id = isset($this->request->get['address_id']) ? (int)$this->request->get['address_id'] : 0;

		if ($this->customer->isLogged() && $address_id) {
			$this->load->model('account/address');
			$address_info = $this->model_account_address->getAddress($this->customer->getId(), $address_id);

			if ($address_info && is_array($address_info['custom_field'] ?? null)) {
				$metadata = $this->getBsNpMetadata($address_info['custom_field']);

				if ($metadata) {
					$json['legacy'] = false;

					if ($this->isBsNpMetadataAvailable($metadata)) {
						$json['metadata'] = $metadata;
					} else {
						$json['stale'] = true;
					}
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
REPLACE,
	'catalog/controller/account/address.php'
);
$files['catalog/controller/account/address.php']['new'] = $account;

$reskin = $files['catalog/view/javascript/checkout-reskin.js']['old'];
$reskin = acc002a_replace_once(
	$reskin,
	<<<'FIND'
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

FIND,
	<<<'REPLACE'
  // ACC-002A saved NP ref validation: the authorised endpoint checks the
  // stored ref. Do not turn an autocomplete index miss into a stale address.

REPLACE,
	'catalog/view/javascript/checkout-reskin.js'
);

$reskin = acc002a_replace_once(
	$reskin,
	<<<'FIND'
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
FIND,
	<<<'REPLACE'
        if (json && json.stale) {
          savedNpHydration.state = 'stale';
          savedNpPrompt('Збережена точка Нової пошти більше не доступна. Оберіть іншу адресу.');
          scheduleSync();
          return;
        }

        if (!json || json.legacy || !json.metadata) {
          savedNpHydration.state = 'legacy';
          savedNpPrompt('Для цієї збереженої адреси потрібно повторно обрати точку Нової пошти.');
          scheduleSync();
          return;
        }

        savedNpHydration.metadata = json.metadata;
        savedNpHydration.state = 'ready';
        applySavedNpMetadata(json.metadata);
        clearSavedNpPrompt();
        scheduleSync();
REPLACE,
	'catalog/view/javascript/checkout-reskin.js'
);
$files['catalog/view/javascript/checkout-reskin.js']['new'] = $reskin;

foreach ($files as $relative => $file) {
	if (!str_contains($file['new'], $targets[$relative])) {
		acc002a_fail("postcheck_marker_missing path={$relative}");
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
		acc002a_fail("backup_dir_failed path={$backupDir}");
	}

	if (!copy($file['path'], $backupPath)) {
		acc002a_fail("backup_failed path={$relative}");
	}
}

foreach ($files as $relative => $file) {
	if (file_put_contents($file['path'], acc002a_restore_eol($file['new'], $file['eol'])) === false) {
		foreach ($files as $restoreRelative => $restore) {
			@copy($backup . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $restoreRelative), $restore['path']);
		}
		acc002a_fail("write_failed path={$relative} rollback=attempted");
	}
}

if (!acc002a_php_l($files['catalog/controller/account/address.php']['path'])) {
	foreach ($files as $restoreRelative => $restore) {
		@copy($backup . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $restoreRelative), $restore['path']);
	}
	acc002a_fail("php_l_failed path=catalog/controller/account/address.php rollback=ok backup={$backup}");
}

foreach ($files as $relative => $file) {
	$written = file_get_contents($file['path']);
	if ($written === false || !str_contains($written, $targets[$relative])) {
		foreach ($files as $restoreRelative => $restore) {
			@copy($backup . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $restoreRelative), $restore['path']);
		}
		acc002a_fail("postwrite_check_failed path={$relative} rollback=ok backup={$backup}");
	}
}

echo "done=ok patch={$patch} files=" . count($files) . " php_l=ok backup={$backup}\n";
@unlink(__FILE__);
