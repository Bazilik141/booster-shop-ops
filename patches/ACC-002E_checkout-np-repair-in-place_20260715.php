<?php
declare(strict_types=1);

/*
 * ACC-002E — repair a selected saved NP address in place — 2026-07-15
 *
 * Scope (no patch-time DB migration/cleanup):
 * - carries the explicitly selected legacy/stale address ID through the NP
 *   re-pick form;
 * - updates that customer-owned row with validated bs_np_v1 data instead of
 *   adding another address-book row;
 * - clears the repair target after success and rehydrates the updated address.
 *
 * Existing duplicates are intentionally not deleted or bulk-rewritten.
 */

$patch = 'ACC-002E_checkout-np-repair-in-place_20260715';
$root = getcwd();
$dryRun = in_array('--dry-run', $argv ?? [], true);

function acc002e_fail(string $message): void {
	fwrite(STDERR, "error={$message}\n");
	exit(1);
}

function acc002e_lf(string $value): string {
	return str_replace(["\r\n", "\r"], "\n", $value);
}

function acc002e_eol(string $value): string {
	return str_contains($value, "\r\n") ? "\r\n" : "\n";
}

function acc002e_restore_eol(string $value, string $eol): string {
	return $eol === "\n" ? $value : str_replace("\n", $eol, $value);
}

function acc002e_replace_once(string $source, string $find, string $replace, string $relative): string {
	$count = substr_count($source, $find);

	if ($count !== 1) {
		acc002e_fail("anchor_count path={$relative} expected=1 actual={$count}");
	}

	return str_replace($find, $replace, $source);
}

function acc002e_php_l(string $path): bool {
	$output = [];
	$code = 0;
	exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
	return $code === 0;
}

$targets = [
	'catalog/controller/checkout/shipping_address.php' => 'ACC-002E explicit NP saved-address repair',
	'catalog/view/javascript/checkout-reskin.js' => 'ACC-002E explicit saved-address repair target',
];

$files = [];
$states = [];

foreach ($targets as $relative => $marker) {
	$path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

	if (!is_file($path)) {
		acc002e_fail("target_missing path={$relative}");
	}

	$raw = file_get_contents($path);
	if ($raw === false) {
		acc002e_fail("read_failed path={$relative}");
	}

	$files[$relative] = [
		'path' => $path,
		'eol' => acc002e_eol($raw),
		'old' => acc002e_lf($raw),
	];
	$states[] = str_contains($raw, $marker);
}

if (count(array_filter($states)) === count($states)) {
	echo "already_applied=yes patch={$patch}\n";
	exit(0);
}

if (count(array_filter($states)) !== 0) {
	acc002e_fail('partial_state_detected restore_from_backup_required');
}

$controller = $files['catalog/controller/checkout/shipping_address.php']['old'];
$controller = acc002e_replace_once(
	$controller,
	<<<'FIND'
		$post_info = $this->request->post + $required;

		// Validate cart has products and has stock.
FIND,
	<<<'REPLACE'
		$post_info = $this->request->post + $required;

		// ACC-002E explicit NP saved-address repair. This ID is accepted only
		// from the validated NP form and is still ownership-checked below.
		$replace_np_address_id = max(0, (int)($post_info['bs_np_replace_address_id'] ?? 0));
		unset($post_info['bs_np_replace_address_id']);

		// Validate cart has products and has stock.
REPLACE,
	'catalog/controller/checkout/shipping_address.php'
);

$controller = acc002e_replace_once(
	$controller,
	<<<'FIND'
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
FIND,
	<<<'REPLACE'
		if (!$json) {
			$default_address_id = $this->customer->getAddressId();

			$this->load->model('account/address');

			$replace_np_address = $replace_np_address_id
				? $this->model_account_address->getAddress($this->customer->getId(), $replace_np_address_id)
				: [];

			if ($replace_np_address_id && (!$np_address || !$replace_np_address)) {
				$json['error']['warning'] = 'Не вдалося оновити вибрану адресу. Оберіть її повторно.';
			}

			if (!$json) {
				$addresses = $this->model_account_address->getAddresses($this->customer->getId());
				$existing_np_address_id = $np_address
					? $this->findBsNpCheckoutAddressId($addresses, $np_address['metadata'])
					: 0;

				if ($replace_np_address) {
					$post_info['default'] = $default_address_id
						? (int)($replace_np_address['default'] ?? 0)
						: 1;
					$this->model_account_address->editAddress($this->customer->getId(), $replace_np_address_id, $post_info);
					$json['address_id'] = $replace_np_address_id;
					$json['address_updated'] = true;
					$json['address_reused'] = false;
				} elseif ($existing_np_address_id) {
					$json['address_id'] = $existing_np_address_id;
					$json['address_updated'] = false;
					$json['address_reused'] = true;
				} else {
					if (!$default_address_id) {
						$post_info['default'] = 1;
					}

					$json['address_id'] = $this->model_account_address->addAddress($this->customer->getId(), $post_info);
					$json['address_updated'] = false;
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
		}
REPLACE,
	'catalog/controller/checkout/shipping_address.php'
);
$files['catalog/controller/checkout/shipping_address.php']['new'] = $controller;

$reskin = $files['catalog/view/javascript/checkout-reskin.js']['old'];
$reskin = acc002e_replace_once(
	$reskin,
	<<<'FIND'
      links.addEventListener('click', function (event) {
        var button = event.target.closest('[data-co-address-link]');

        if (!button) {
          return;
        }

        var target = button.getAttribute('data-co-address-link') === 'manual' ? manualRadio : existingRadio;
        $(target).prop('checked', true).trigger('change');
        syncAddressLinks();
      });
FIND,
	<<<'REPLACE'
      links.addEventListener('click', function (event) {
        var button = event.target.closest('[data-co-address-link]');

        if (!button) {
          return;
        }

        var manualRequested = button.getAttribute('data-co-address-link') === 'manual';
        var target = manualRequested ? manualRadio : existingRadio;

        if (manualRequested && window.bsCheckoutClearNpRepairTarget) {
          window.bsCheckoutClearNpRepairTarget();
        }

        $(target).prop('checked', true).trigger('change');
        syncAddressLinks();
      });
REPLACE,
	'catalog/view/javascript/checkout-reskin.js'
);

$reskin = acc002e_replace_once(
	$reskin,
	<<<'FIND'
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
FIND,
	<<<'REPLACE'
  // ACC-002E explicit saved-address repair target. Only the re-pick action may
  // set it; ordinary "+ Інша адреса" and saved-address changes clear it.
  function npRepairForm() {
    return $('#form-shipping-address_nova_poshta');
  }

  window.bsCheckoutClearNpRepairTarget = function() {
    npRepairForm().find('input[name="bs_np_replace_address_id"]').remove();
  };

  function setNpRepairTarget(addressId) {
    window.bsCheckoutClearNpRepairTarget();

    if (addressId) {
      $('<input>', {
        type: 'hidden',
        name: 'bs_np_replace_address_id',
        value: addressId
      }).appendTo(npRepairForm());
    }
  }

  $(document).on('click.acc002NpRepick', '[data-bs-np-repick]', function() {
    var manual = $('#input-shipping-novaposhta');
    var select = savedAddressSelect();
    var repairAddressId = select && String(select.value || '') === savedNpHydration.addressId && (savedNpHydration.state === 'legacy' || savedNpHydration.state === 'stale')
      ? String(select.value || '')
      : '';
    var previousFlag = !!window.bsCheckoutNpInitialising;

    window.bsCheckoutNpInitialising = true;
    try {
      manual.prop('checked', true).trigger('change');
      setNpRepairTarget(repairAddressId);
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
    window.bsCheckoutClearNpRepairTarget();
    window.setTimeout(hydrateSavedNpAddress, 0);
  });

  $(document).on('ajaxSuccess.acc002eNpRepair', function(event, xhr, settings) {
    var url = settings && settings.url ? settings.url : '';
    var json = xhr && xhr.responseJSON ? xhr.responseJSON : null;

    if (!json || !json.address_updated || url.indexOf('checkout/shipping_address') === -1) {
      return;
    }

    window.bsCheckoutClearNpRepairTarget();
    savedNpHydration = { addressId: '', metadata: null, state: '' };
    clearSavedNpPrompt();
    window.setTimeout(hydrateSavedNpAddress, 0);
  });
REPLACE,
	'catalog/view/javascript/checkout-reskin.js'
);
$files['catalog/view/javascript/checkout-reskin.js']['new'] = $reskin;

foreach ($files as $relative => $file) {
	if (!str_contains($file['new'], $targets[$relative])) {
		acc002e_fail("postcheck_marker_missing path={$relative}");
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
		acc002e_fail("backup_dir_failed path={$backupDir}");
	}

	if (!copy($file['path'], $backupPath)) {
		acc002e_fail("backup_failed path={$relative}");
	}
}

foreach ($files as $relative => $file) {
	if (file_put_contents($file['path'], acc002e_restore_eol($file['new'], $file['eol'])) === false) {
		foreach ($files as $restoreRelative => $restore) {
			@copy($backup . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $restoreRelative), $restore['path']);
		}
		acc002e_fail("write_failed path={$relative} rollback=attempted");
	}
}

if (!acc002e_php_l($files['catalog/controller/checkout/shipping_address.php']['path'])) {
	foreach ($files as $restoreRelative => $restore) {
		@copy($backup . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $restoreRelative), $restore['path']);
	}
	acc002e_fail("php_l_failed path=catalog/controller/checkout/shipping_address.php rollback=ok backup={$backup}");
}

foreach ($files as $relative => $file) {
	$written = file_get_contents($file['path']);
	if ($written === false || !str_contains($written, $targets[$relative])) {
		foreach ($files as $restoreRelative => $restore) {
			@copy($backup . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $restoreRelative), $restore['path']);
		}
		acc002e_fail("postwrite_check_failed path={$relative} rollback=ok backup={$backup}");
	}
}

echo "done=ok patch={$patch} files=" . count($files) . " php_l=ok backup={$backup}\n";
@unlink(__FILE__);
