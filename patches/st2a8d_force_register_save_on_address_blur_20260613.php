<?php
declare(strict_types=1);

$patch = 'st2a8d_force_register_save_on_address_blur_20260613';
$root = getcwd() ?: __DIR__;
$time = gmdate('Ymd-His');
$backupRoot = '_patch_backups/' . $patch . '-' . $time;

$checkoutRel = 'catalog/view/template/checkout/checkout.twig';
$pintaRel = 'extension/PintaNovaPoshtaCod/catalog/view/template/shipping/js_checkout_shipping_address_form.twig';

function st2a8d_log(string $message): void
{
    echo '[' . gmdate('c') . '] ' . $message . PHP_EOL;
}

function st2a8d_fail(string $message, int $code = 1): void
{
    st2a8d_log('error=' . $message);
    st2a8d_log('done=failed');
    exit($code);
}

function st2a8d_join(string $base, string $rel): string
{
    return rtrim($base, "\\/") . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
}

function st2a8d_read(string $path, string $rel): string
{
    if (!is_file($path)) {
        st2a8d_fail('target file not found: ' . $rel);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        st2a8d_fail('cannot read target file: ' . $rel);
    }

    return $content;
}

function st2a8d_replace_once(string $content, string $search, string $replace, string $label): string
{
    $count = substr_count($content, $search);

    if ($count !== 1) {
        st2a8d_fail('precheck failed: expected exactly 1 match for ' . $label . ', got ' . $count);
    }

    return str_replace($search, $replace, $content);
}

function st2a8d_backup_and_write(string $root, string $backupRoot, string $rel, string $path, string $content): void
{
    $backup = st2a8d_join($root, $backupRoot . '/' . $rel);
    $backupDir = dirname($backup);

    if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) {
        st2a8d_fail('cannot create backup dir: ' . $backupDir);
    }

    if (!copy($path, $backup)) {
        st2a8d_fail('cannot create backup: ' . $rel);
    }

    st2a8d_log('backup: ' . $rel . ' -> ' . str_replace($root . DIRECTORY_SEPARATOR, '', $backup));

    if (file_put_contents($path, $content, LOCK_EX) === false) {
        @copy($backup, $path);
        st2a8d_fail('cannot write target; backup restored: ' . $rel);
    }

    st2a8d_log('changed=' . $rel);
}

function st2a8d_patch_checkout(string $content): string
{
    $marker = 'ST-2a.8d: force the working register.save refresh path on address blur.';

    if (strpos($content, $marker) !== false) {
        return $content;
    }

    $forceBlock = <<<'TWIG'
  // ST-2a.8d: force the working register.save refresh path on address blur.
  function bsCheckoutResetRegisterAutosaveLocks(form) {
    window.clearTimeout(bsRegisterTimer);

    if (typeof bsRegisterAutosaveWatchdog !== 'undefined') {
      window.clearTimeout(bsRegisterAutosaveWatchdog);
      bsRegisterAutosaveWatchdog = null;
    }

    if (typeof clearRegisterAutosaveLock === 'function') {
      clearRegisterAutosaveLock(form);
    } else {
      form.data('bsSaving', false).data('bsSubmitPending', false).data('bsPendingSignature', '');
    }
  }

  function bsCheckoutRunForcedRegisterSave(form, signature) {
    var send = function() {
      return $.ajax({
        url: 'index.php?route=checkout/register.save&language={{ language }}',
        type: 'post',
        dataType: 'json',
        data: signature,
        contentType: 'application/x-www-form-urlencoded',
        complete: function() {
          form.data('bsForceSaving', false);
        },
        error: function(xhr, ajaxOptions, thrownError) {
          $('[data-bs-register-status]').addClass('bs-is-error').text('Не вдалося зберегти дані для замовлення.');
          console.log(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
        }
      });
    };

    if (window.chain && typeof chain.attach === 'function') {
      chain.attach(send);
    } else {
      send();
    }
  }

  window.bsCheckoutForceRegisterAutosave = function(reason) {
    var form = $('#form-register');

    if (!form.length || form.data('bsForceSaving')) {
      return false;
    }

    window.bsCheckoutSyncNpFields();

    if (!registerIsComplete()) {
      return false;
    }

    var signature = form.serialize();

    if (form.data('bsLastSaved') === signature) {
      if ($('#input-shipping-address').val() && typeof window.bsCheckoutLoadShippingMethods === 'function') {
        window.bsCheckoutResetMethodState('address');
        window.setTimeout(function() {
          window.bsCheckoutLoadShippingMethods({ autoSelect: true });
        }, 80);
      }

      return true;
    }

    bsCheckoutResetRegisterAutosaveLocks(form);
    form.data('bsForceSaving', true).data('bsPendingSignature', signature);
    $('[data-bs-register-status]').removeClass('bs-is-error').text('Зберігаємо дані для замовлення...');
    bsCheckoutRunForcedRegisterSave(form, signature);

    return true;
  };

TWIG;

    $content = st2a8d_replace_once(
        $content,
        "  window.bsCheckoutNpFieldChanged = function(source) {\n",
        $forceBlock . "  window.bsCheckoutNpFieldChanged = function(source) {\n",
        'checkout force save insertion before bsCheckoutNpFieldChanged'
    );

    $oldHandler = <<<'TWIG'
  $(document).on('change blur keyup input', '#shipping-novaposhta input, #shipping-novaposhta select', function() {
    if (window.bsCheckoutNpFieldChanged) {
      window.bsCheckoutNpFieldChanged(this);
    }
  });
TWIG;

    $newHandler = <<<'TWIG'
  $(document).on('change blur keyup input', '#shipping-novaposhta input, #shipping-novaposhta select', function() {
    if (window.bsCheckoutNpFieldChanged) {
      window.bsCheckoutNpFieldChanged(this);
    }
  });

  $(document).on('focusout.bsSt2a8dForceSave', '#shipping-novaposhta input, #shipping-novaposhta select, #form-register #shipping-address input, #form-register #shipping-address select', function() {
    var field = this;

    window.setTimeout(function() {
      if (!normalizeText($(field).val()) && !window.bsCheckoutNpIsComplete()) {
        return;
      }

      if (window.bsCheckoutForceRegisterAutosave) {
        window.bsCheckoutForceRegisterAutosave('address-focusout');
      }
    }, 80);
  });
TWIG;

    $content = st2a8d_replace_once($content, $oldHandler, $newHandler, 'checkout address focusout force save handler');

    if (strpos($content, $marker) === false || strpos($content, 'focusout.bsSt2a8dForceSave') === false) {
        st2a8d_fail('postcheck failed: checkout force blur handler not installed');
    }

    return $content;
}

function st2a8d_patch_pinta(string $content): string
{
    $marker = 'ST-2a.8d: dropdown click also runs the forced register.save path.';

    if (strpos($content, $marker) !== false) {
        return $content;
    }

    $old8b = <<<'TWIG'

      // ST-2a.8b: mouse dropdown select must wake checkout autosave like keyboard changes do.
      input.trigger('change');
TWIG;

    $content = str_replace($old8b, '', $content);

    $oldClick = <<<'TWIG'
      if (window.bsCheckoutConfirmNpDropdown) {
        window.bsCheckoutConfirmNpDropdown(input[0], ref);
      }
TWIG;

    $newClick = <<<'TWIG'
      if (window.bsCheckoutConfirmNpDropdown) {
        window.bsCheckoutConfirmNpDropdown(input[0], ref);
      }

      // ST-2a.8d: dropdown click also runs the forced register.save path.
      if (window.bsCheckoutForceRegisterAutosave) {
        window.setTimeout(function() {
          window.bsCheckoutForceRegisterAutosave('pinta-dropdown-click');
        }, 80);
      }
TWIG;

    $content = st2a8d_replace_once($content, $oldClick, $newClick, 'Pinta dropdown click force save hook');

    if (strpos($content, $marker) === false) {
        st2a8d_fail('postcheck failed: Pinta force click hook not installed');
    }

    return $content;
}

st2a8d_log('patch=' . $patch);
st2a8d_log('cwd=' . $root);
st2a8d_log('time=' . gmdate('c'));
st2a8d_log('scope=ST-2a.8d force checkout/register.save micro-refresh on NP address blur/dropdown click; Twig/client JS only');
st2a8d_log('db_schema_changes=none');

$checkoutPath = st2a8d_join($root, $checkoutRel);
$pintaPath = st2a8d_join($root, $pintaRel);

$checkoutContent = st2a8d_read($checkoutPath, $checkoutRel);
$pintaContent = st2a8d_read($pintaPath, $pintaRel);

$newCheckout = st2a8d_patch_checkout($checkoutContent);
$newPinta = st2a8d_patch_pinta($pintaContent);

$writes = [];

if ($newCheckout !== $checkoutContent) {
    $writes[$checkoutRel] = [$checkoutPath, $newCheckout];
} else {
    st2a8d_log('already_applied=' . $checkoutRel);
}

if ($newPinta !== $pintaContent) {
    $writes[$pintaRel] = [$pintaPath, $newPinta];
} else {
    st2a8d_log('already_applied=' . $pintaRel);
}

if (!$writes) {
    st2a8d_log('done=ok');
    @unlink(__FILE__);
    exit(0);
}

foreach ($writes as $rel => [$path, $content]) {
    st2a8d_backup_and_write($root, $backupRoot, $rel, $path, $content);
}

st2a8d_log('php_modified=none');
st2a8d_log('rollback=restore files from ' . $backupRoot . ' and clear OpenCart template cache');
st2a8d_log('done=ok');
@unlink(__FILE__);
