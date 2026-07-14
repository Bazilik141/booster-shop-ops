<?php
/**
 * CHECKOUT-003 — prevent checkout/register.save from being called while the
 * Nova Poshta UI is merely initialising.
 *
 * No DB changes. Targets only the current checkout Twig and the current
 * Pinta Nova Poshta checkout JS template. Rollback: restore the backup path
 * printed by this runner.
 */
declare(strict_types=1);

$patch = basename(__FILE__, '.php');
$root = __DIR__;
$dryRun = in_array('--dry-run', $argv, true);
$targets = [
    'catalog/view/template/checkout/checkout.twig',
    'extension/PintaNovaPoshtaCod/catalog/view/template/shipping/js_checkout_shipping_address_form.twig',
];
$marker = 'CHECKOUT-003: no register autosave during NP initialisation';
$files = [];
$backupDir = '';

function fail(string $message): void {
    throw new RuntimeException($message);
}

function eol(string $text, string $lineEnding): string {
    return str_replace("\n", $lineEnding, $text);
}

function replaceOnce(string $source, string $find, string $replace, string $path): string {
    $lineEnding = str_contains($source, "\r\n") ? "\r\n" : "\n";
    $find = eol($find, $lineEnding);
    $replace = eol($replace, $lineEnding);

    if (substr_count($source, $find) !== 1) {
        fail('anchor_count_error path=' . $path . ' expected=1');
    }

    return str_replace($find, $replace, $source);
}

function restoreFiles(array $files, string $backupDir): void {
    if ($backupDir === '') {
        return;
    }

    foreach ($files as $relative => $source) {
        $backup = $backupDir . DIRECTORY_SEPARATOR . $relative;

        if (is_file($backup)) {
            @copy($backup, $source['path']);
        }
    }
}

try {
    foreach ($targets as $relative) {
        $path = $root . DIRECTORY_SEPARATOR . $relative;

        if (!is_file($path)) {
            fail('target_missing path=' . $relative);
        }

        $source = file_get_contents($path);

        if ($source === false) {
            fail('read_failed path=' . $relative);
        }

        $files[$relative] = ['path' => $path, 'source' => $source, 'new' => $source];
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
        fail('partial_marker_state=yes; restore both target files from the same backup before retrying');
    }

    $checkout = $files['catalog/view/template/checkout/checkout.twig']['new'];

    $checkout = replaceOnce(
        $checkout,
        <<<'OLD'
  window.bsCheckoutNpFieldChanged = function(source) {
    window.bsCheckoutSyncNpFields();
OLD,
        <<<'REPLACE'
  window.bsCheckoutNpFieldChanged = function(source) {
    if (window.bsCheckoutNpInitialising) {
      return;
    }

    window.bsCheckoutSyncNpFields();
REPLACE,
        'catalog/view/template/checkout/checkout.twig'
    );

    $checkout = replaceOnce(
        $checkout,
        <<<'OLD'
  function updateRecipientMode() {
    var other = isOtherRecipient();
    $('#form-register #input-firstname, #form-register #input-lastname').closest('.col').toggleClass('bs-customer-name-fields-hidden', !other);
    window.bsCheckoutSyncNpFields();
    window.clearTimeout(bsRegisterTimer);
    bsRegisterTimer = window.setTimeout(triggerRegisterAutosave, 250);
  }
OLD,
        <<<'OLD'
  function updateRecipientMode(shouldAutosave) {
    var other = isOtherRecipient();
    $('#form-register #input-firstname, #form-register #input-lastname').closest('.col').toggleClass('bs-customer-name-fields-hidden', !other);
    window.bsCheckoutSyncNpFields();

    if (!shouldAutosave) {
      return;
    }

    window.clearTimeout(bsRegisterTimer);
    bsRegisterTimer = window.setTimeout(triggerRegisterAutosave, 250);
  }
OLD,
        'catalog/view/template/checkout/checkout.twig'
    );

    $checkout = replaceOnce(
        $checkout,
        <<<'OLD'
    updateRecipientMode();
  }
OLD,
        <<<'OLD'
    updateRecipientMode(false);
  }
OLD,
        'catalog/view/template/checkout/checkout.twig'
    );

    $checkout = replaceOnce(
        $checkout,
        <<<'OLD'
    prepareRegisterFlow();
    var hasHydratedNpRefs = hydrateNpRefsFromHidden();
    window.bsCheckoutUpdateNpCascade();
    window.bsCheckoutSyncNpFields();

    if (hasHydratedNpRefs && window.bsCheckoutNpIsComplete()) {
      window.clearTimeout(bsRegisterTimer);
      bsRegisterTimer = window.setTimeout(triggerRegisterAutosave, 250);
    }
OLD,
        <<<'OLD'
    prepareRegisterFlow();
    hydrateNpRefsFromHidden();
    window.bsCheckoutUpdateNpCascade();
    window.bsCheckoutSyncNpFields();

    // CHECKOUT-003: no register autosave during NP initialisation.
    // Hidden refs can survive while visible NP fields are blank; only a real
    // customer interaction may schedule checkout/register.save.
OLD,
        'catalog/view/template/checkout/checkout.twig'
    );

    $checkout = replaceOnce(
        $checkout,
        <<<'OLD'
  $(document).on('change', '#input-bs-recipient-other', updateRecipientMode);

  $(document).on('change keyup blur input', '#form-register input, #form-register select', function() {
    window.bsCheckoutSyncNpFields();
    window.clearTimeout(bsRegisterTimer);
    bsRegisterTimer = window.setTimeout(triggerRegisterAutosave, 350);
  });
OLD,
        <<<'OLD'
  $(document).on('change', '#input-bs-recipient-other', function() {
    updateRecipientMode(true);
  });

  $(document).on('change keyup blur input', '#form-register input, #form-register select', function() {
    if (window.bsCheckoutNpInitialising) {
      return;
    }

    window.bsCheckoutSyncNpFields();
    window.clearTimeout(bsRegisterTimer);
    bsRegisterTimer = window.setTimeout(triggerRegisterAutosave, 350);
  });
OLD,
        'catalog/view/template/checkout/checkout.twig'
    );

    $files['catalog/view/template/checkout/checkout.twig']['new'] = $checkout;

    $np = $files['extension/PintaNovaPoshtaCod/catalog/view/template/shipping/js_checkout_shipping_address_form.twig']['new'];
    $np = replaceOnce(
        $np,
        <<<'OLD'
  toggleNovaPoshtaAddress($('input[name="shipping_existing"]:checked').val());
  $('#input-shipping-novaposhta-type').trigger('change');

  if (window.bsCheckoutUpdateNpCascade) {
    window.bsCheckoutUpdateNpCascade();
  }

  if (window.bsCheckoutSyncNpFields) {
    window.bsCheckoutSyncNpFields();
  }
OLD,
        <<<'OLD'
  // CHECKOUT-003: no register autosave during NP initialisation; the initial
  // type change is programmatic, not customer input.
  // The checkout reskin consumes this flag and must not create an address/order
  // autosave request while the NP form is only being rendered.
  window.bsCheckoutNpInitialising = true;
  toggleNovaPoshtaAddress($('input[name="shipping_existing"]:checked').val());
  $('#input-shipping-novaposhta-type').trigger('change');

  if (window.bsCheckoutUpdateNpCascade) {
    window.bsCheckoutUpdateNpCascade();
  }

  if (window.bsCheckoutSyncNpFields) {
    window.bsCheckoutSyncNpFields();
  }

  window.bsCheckoutNpInitialising = false;
OLD,
        'extension/PintaNovaPoshtaCod/catalog/view/template/shipping/js_checkout_shipping_address_form.twig'
    );
    $files['extension/PintaNovaPoshtaCod/catalog/view/template/shipping/js_checkout_shipping_address_form.twig']['new'] = $np;

    foreach ($files as $relative => $file) {
        if (!str_contains($file['new'], $marker)) {
            fail('postcheck_marker_missing path=' . $relative);
        }
    }

    if ($dryRun) {
        echo "dry_run=ok patch={$patch} files=" . count($files) . "\n";
        exit(0);
    }

    $backupDir = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . $patch . '-' . date('Ymd_His');
    foreach ($files as $relative => $file) {
        $backup = $backupDir . DIRECTORY_SEPARATOR . $relative;
        $dir = dirname($backup);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            fail('backup_dir_create_failed path=' . $dir);
        }

        if (!copy($file['path'], $backup)) {
            fail('backup_failed path=' . $relative);
        }
    }

    foreach ($files as $relative => $file) {
        if (file_put_contents($file['path'], $file['new']) === false) {
            fail('write_failed path=' . $relative);
        }
    }

    foreach ($files as $relative => $file) {
        $written = file_get_contents($file['path']);
        if ($written === false || !str_contains($written, $marker)) {
            fail('postwrite_verify_failed path=' . $relative);
        }
    }

    echo "done=ok patch={$patch} files=" . count($files) . " backup={$backupDir}\n";
    @unlink(__FILE__);
} catch (Throwable $e) {
    restoreFiles($files, $backupDir);
    echo 'done=error patch=' . $patch . ' message=' . $e->getMessage() . "\n";
    exit(1);
}
