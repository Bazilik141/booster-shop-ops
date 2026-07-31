<?php
declare(strict_types=1);

/**
 * Run from ~/public_html.
 * Re-quotes and re-saves the currently selected delivery after a successful
 * mini-cart quantity/remove update. No DB, payment, order-write or Hutko changes.
 */
$id = 'ST-2c_minicart_shipping_requote_20260728';
$mark = 'ST-2C-MINICART-SHIPPING-20260728';
$root = getcwd() ?: __DIR__;
$dry = in_array('--dry-run', $argv ?? [], true);
$backup = $root . '/_patch_backups/' . $id . '-' . date('Ymd-His');
$file = 'catalog/view/javascript/checkout-state.js';
$expectedHash = 'C291F81EE26E354CE51C34BA7C8694FA4F0CACCE46263B79509A989DA7EDFA6A';

function st2cm_out(string $value): void { echo '[' . date('c') . '] ' . $value . PHP_EOL; }
function st2cm_fail(string $value): void { st2cm_out('error=' . $value); st2cm_out('done=failed'); exit(1); }
function st2cm_normalize(string $value): string { return str_replace(["\r\n", "\r"], "\n", $value); }

st2cm_out('patch=' . $id);
st2cm_out('cwd=' . $root);
st2cm_out('time=' . date('c'));
$path = $root . '/' . $file;
if (!is_file($path)) st2cm_fail('missing_file=' . $file);
$source = file_get_contents($path);
if ($source === false) st2cm_fail('cannot_read=' . $file);

if (str_contains($source, $mark)) {
    st2cm_out('already_applied=yes');
    st2cm_out('done=ok');
    @unlink(__FILE__);
    exit(0);
}
if (strtoupper(hash('sha256', $source)) !== $expectedHash) st2cm_fail('sha256_mismatch=' . $file);

$eol = str_contains($source, "\r\n") ? "\r\n" : "\n";
$normalized = st2cm_normalize($source);
$old = <<<'JS'
  function cartChanged() {
    return addressSaved({ quietAddressError: true });
  }
JS;
$new = <<<'JS'
  // ST-2C-MINICART-SHIPPING-20260728: mini-cart changes preserve the selected
  // method, but must persist its freshly quoted display text and summary.
  function cartChanged() {
    revision += 1;
    var token = revision;
    totalsDirty = true;
    abortTotals();
    $('#input-shipping-display-text').val('');
    clearPaymentState();

    if (typeof window.bsCheckoutLoadShippingMethods === 'function') {
      return window.bsCheckoutLoadShippingMethods({
        autoSelect: false,
        resaveCurrent: true,
        quietAddressError: true,
        stateRevision: token
      });
    }

    return null;
  }
JS;
$count = substr_count($normalized, $old);
if ($count !== 1) st2cm_fail('anchor_cart_changed_count=' . $count);
$updated = str_replace($old, $new, $normalized);
if (!str_contains($updated, $mark)) st2cm_fail('postcheck_marker_missing');
if ($dry) { st2cm_out('dry_run=ok'); st2cm_out('done=ok'); exit(0); }

$backupFile = $backup . '/' . $file;
if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0755, true) && !is_dir(dirname($backupFile))) st2cm_fail('cannot_create_backup_dir');
if (!copy($path, $backupFile)) st2cm_fail('cannot_backup=' . $file);
st2cm_out('backup=' . str_replace($root . '/', '', $backup));

$output = $eol === "\r\n" ? str_replace("\n", "\r\n", $updated) : $updated;
if (file_put_contents($path, $output) === false) st2cm_fail('cannot_write=' . $file);
st2cm_out('changed=' . $file);
st2cm_out('js_syntax=owner_run_required');
st2cm_out('done=ok');
@unlink(__FILE__);
