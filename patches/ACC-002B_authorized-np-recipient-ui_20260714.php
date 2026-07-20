<?php
declare(strict_types=1);

/*
 * ACC-002B — authorised Nova Poshta recipient UI — 2026-07-14
 *
 * No DB changes. The customer profile provides the first/last-name fallback;
 * the order-only receiver override remains authoritative. The NP re-pick form
 * keeps synchronized hidden values but does not duplicate receiver fields.
 */

$patch = 'ACC-002B_authorized-np-recipient-ui_20260714';
$root = getcwd();
$dryRun = in_array('--dry-run', $argv ?? [], true);

function acc002b_fail(string $message): void {
	fwrite(STDERR, "error={$message}\n");
	exit(1);
}

function acc002b_lf(string $value): string {
	return str_replace(["\r\n", "\r"], "\n", $value);
}

function acc002b_eol(string $value): string {
	return str_contains($value, "\r\n") ? "\r\n" : "\n";
}

function acc002b_restore_eol(string $value, string $eol): string {
	return $eol === "\n" ? $value : str_replace("\n", $eol, $value);
}

function acc002b_replace_once(string $source, string $find, string $replace, string $relative): string {
	$count = substr_count($source, $find);

	if ($count !== 1) {
		acc002b_fail("anchor_count path={$relative} expected=1 actual={$count}");
	}

	return str_replace($find, $replace, $source);
}

function acc002b_php_l(string $path): bool {
	$output = [];
	$code = 0;
	exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
	return $code === 0;
}

$targets = [
	'catalog/controller/checkout/checkout.php' => 'ACC-002B authorised receiver profile fallback',
	'catalog/view/javascript/checkout-reskin.js' => 'ACC-002B authorised NP recipient UI',
];

$files = [];
$states = [];

foreach ($targets as $relative => $marker) {
	$path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

	if (!is_file($path)) {
		acc002b_fail("target_missing path={$relative}");
	}

	$raw = file_get_contents($path);
	if ($raw === false) {
		acc002b_fail("read_failed path={$relative}");
	}

	$files[$relative] = [
		'path' => $path,
		'eol' => acc002b_eol($raw),
		'old' => acc002b_lf($raw),
	];
	$states[] = str_contains($raw, $marker);
}

if (count(array_filter($states)) === count($states)) {
	echo "already_applied=yes patch={$patch}\n";
	exit(0);
}

if (count(array_filter($states)) !== 0) {
	acc002b_fail('partial_state_detected restore_from_backup_required');
}

$checkout = $files['catalog/controller/checkout/checkout.php']['old'];
$checkout = acc002b_replace_once(
	$checkout,
	<<<'FIND'
		// RD-13.1B order-only receiver override: render session values after reload
		// without changing the customer profile or the saved address book.
		$receiver_override = $this->session->data['rd13_receiver_override'] ?? [];
		$receiver_override = is_array($receiver_override) ? $receiver_override : [];
		$data['customer_telephone'] = $this->customer->isLogged() ? (string)$this->customer->getTelephone() : '';
		$data['receiver_override_firstname'] = (string)($receiver_override['firstname'] ?? '');
		$data['receiver_override_lastname'] = (string)($receiver_override['lastname'] ?? '');
		$data['receiver_override_middlename'] = (string)($receiver_override['middlename'] ?? '');
		$data['receiver_override_telephone'] = (string)($receiver_override['telephone'] ?? '');
FIND,
	<<<'REPLACE'
		// ACC-002B authorised receiver profile fallback: a non-empty order-only
		// override wins; otherwise start from the logged-in customer profile.
		$receiver_override = $this->session->data['rd13_receiver_override'] ?? [];
		$receiver_override = is_array($receiver_override) ? $receiver_override : [];
		$profile_firstname = $this->customer->isLogged() && method_exists($this->customer, 'getFirstName')
			? (string)$this->customer->getFirstName()
			: '';
		$profile_lastname = $this->customer->isLogged() && method_exists($this->customer, 'getLastName')
			? (string)$this->customer->getLastName()
			: '';
		$override_firstname = trim((string)($receiver_override['firstname'] ?? ''));
		$override_lastname = trim((string)($receiver_override['lastname'] ?? ''));
		$data['customer_telephone'] = $this->customer->isLogged() ? (string)$this->customer->getTelephone() : '';
		$data['receiver_override_firstname'] = $override_firstname !== '' ? $override_firstname : $profile_firstname;
		$data['receiver_override_lastname'] = $override_lastname !== '' ? $override_lastname : $profile_lastname;
		$data['receiver_override_middlename'] = (string)($receiver_override['middlename'] ?? '');
		$data['receiver_override_telephone'] = (string)($receiver_override['telephone'] ?? '');
REPLACE,
	'catalog/controller/checkout/checkout.php'
);
$files['catalog/controller/checkout/checkout.php']['new'] = $checkout;

$reskin = $files['catalog/view/javascript/checkout-reskin.js']['old'];
$reskin = acc002b_replace_once(
	$reskin,
	<<<'FIND'
      var source = select.dataset.coNameCache || '';
      if (!source) {
        source = text((select.options[select.selectedIndex] || select.options[0]).textContent).split(',')[0];
      }
FIND,
	<<<'REPLACE'
      var source = select.value ? (select.dataset.coNameCache || '') : '';
      if (!source && select.value) {
        source = text((select.options[select.selectedIndex] || select.options[0]).textContent).split(',')[0];
      }
REPLACE,
	'catalog/view/javascript/checkout-reskin.js'
);

$reskin = acc002b_replace_once(
	$reskin,
	<<<'FIND'
  function cacheReceiverName() {
    var select = savedAddressSelect();

    if (select && !select.dataset.coNameCache && select.options.length) {
      var raw = text((select.options[select.selectedIndex] || select.options[0]).textContent);
      var name = raw.split(',')[0];

      if (name && !/поштомат|відділення|вул\./i.test(name)) {
        select.dataset.coNameCache = name;
      }
    }
  }
FIND,
	<<<'REPLACE'
  // ACC-002B authorised NP recipient UI: NP keeps its submitted values from
  // the receiver card, while the duplicate module fields stay out of the UI.
  function syncAuthorizedNpRecipient() {
    if (document.getElementById('form-register')) {
      return;
    }

    var recap = document.getElementById('bs-co-receiver-recap');
    var values = {
      firstname: text(recap && recap.querySelector('#bs-co-recv-firstname') ? recap.querySelector('#bs-co-recv-firstname').value : root.getAttribute('data-bs-receiver-firstname')),
      lastname: text(recap && recap.querySelector('#bs-co-recv-lastname') ? recap.querySelector('#bs-co-recv-lastname').value : root.getAttribute('data-bs-receiver-lastname')),
      middlename: text(recap && recap.querySelector('#bs-co-recv-middlename') ? recap.querySelector('#bs-co-recv-middlename').value : root.getAttribute('data-bs-receiver-middlename'))
    };

    Object.keys(values).forEach(function(key) {
      var input = document.getElementById('input-shipping-novaposhta-' + key);

      if (input) {
        input.value = values[key];
        $(input).closest('.mb-3').hide().addClass('bs-np-authorized-recipient-hidden');
      }
    });

    $('[data-bs-np-form="shipping"] input[name="firstname"]').val(values.firstname);
    $('[data-bs-np-form="shipping"] input[name="lastname"]').val(values.lastname);
  }

  $(document).on('input.acc002bNpRecipient change.acc002bNpRecipient', '#bs-co-recv-firstname, #bs-co-recv-lastname, #bs-co-recv-middlename', syncAuthorizedNpRecipient);
  $(document).on('click.acc002bNpRecipient', '[data-bs-np-repick], [data-co-address-link]', function() {
    window.setTimeout(syncAuthorizedNpRecipient, 0);
  });

  function cacheReceiverName() {
    var select = savedAddressSelect();

    if (!select || !select.value || !savedAddressMode() || select.dataset.coNameCache || !select.options.length) {
      return;
    }

    var raw = text((select.options[select.selectedIndex] || select.options[0]).textContent);
    var name = raw.split(',')[0];

    if (name && !/виберіть/i.test(name) && !/^[-\s]+$/.test(name) && !/поштомат|відділення|вул\./i.test(name)) {
      select.dataset.coNameCache = name;
    }
  }
REPLACE,
	'catalog/view/javascript/checkout-reskin.js'
);

$reskin = acc002b_replace_once(
	$reskin,
	<<<'FIND'
  $(function() {
    hydrateSavedNpAddress();
  });
FIND,
	<<<'REPLACE'
  $(function() {
    syncAuthorizedNpRecipient();
    hydrateSavedNpAddress();
  });
REPLACE,
	'catalog/view/javascript/checkout-reskin.js'
);
$files['catalog/view/javascript/checkout-reskin.js']['new'] = $reskin;

foreach ($files as $relative => $file) {
	if (!str_contains($file['new'], $targets[$relative])) {
		acc002b_fail("postcheck_marker_missing path={$relative}");
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
		acc002b_fail("backup_dir_failed path={$backupDir}");
	}

	if (!copy($file['path'], $backupPath)) {
		acc002b_fail("backup_failed path={$relative}");
	}
}

foreach ($files as $relative => $file) {
	if (file_put_contents($file['path'], acc002b_restore_eol($file['new'], $file['eol'])) === false) {
		foreach ($files as $restoreRelative => $restore) {
			@copy($backup . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $restoreRelative), $restore['path']);
		}
		acc002b_fail("write_failed path={$relative} rollback=attempted");
	}
}

if (!acc002b_php_l($files['catalog/controller/checkout/checkout.php']['path'])) {
	foreach ($files as $restoreRelative => $restore) {
		@copy($backup . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $restoreRelative), $restore['path']);
	}
	acc002b_fail("php_l_failed path=catalog/controller/checkout/checkout.php rollback=ok backup={$backup}");
}

foreach ($files as $relative => $file) {
	$written = file_get_contents($file['path']);
	if ($written === false || !str_contains($written, $targets[$relative])) {
		foreach ($files as $restoreRelative => $restore) {
			@copy($backup . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $restoreRelative), $restore['path']);
		}
		acc002b_fail("postwrite_check_failed path={$relative} rollback=ok backup={$backup}");
	}
}

echo "done=ok patch={$patch} files=" . count($files) . " php_l=ok backup={$backup}\n";
@unlink(__FILE__);
