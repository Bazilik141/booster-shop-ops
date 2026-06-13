<?php
declare(strict_types=1);

$patch = 'st2a8c_autosave_bsSaving_guard_20260613';
$root = getcwd() ?: __DIR__;
$time = gmdate('Ymd-His');
$backupRoot = '_patch_backups/' . $patch . '-' . $time;

$checkoutRel = 'catalog/view/template/checkout/checkout.twig';
$pintaRel = 'extension/PintaNovaPoshtaCod/catalog/view/template/shipping/js_checkout_shipping_address_form.twig';

function st2a8c_log(string $message): void
{
    echo '[' . gmdate('c') . '] ' . $message . PHP_EOL;
}

function st2a8c_fail(string $message, int $code = 1): void
{
    st2a8c_log('error=' . $message);
    st2a8c_log('done=failed');
    exit($code);
}

function st2a8c_join(string $base, string $rel): string
{
    return rtrim($base, "\\/") . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
}

function st2a8c_read(string $path, string $rel): string
{
    if (!is_file($path)) {
        st2a8c_fail('target file not found: ' . $rel);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        st2a8c_fail('cannot read target file: ' . $rel);
    }

    return $content;
}

function st2a8c_replace_once(string $content, string $search, string $replace, string $label): string
{
    $count = substr_count($content, $search);

    if ($count !== 1) {
        st2a8c_fail('precheck failed: expected exactly 1 match for ' . $label . ', got ' . $count);
    }

    return str_replace($search, $replace, $content);
}

function st2a8c_backup_and_write(string $root, string $backupRoot, string $rel, string $path, string $content): void
{
    $backup = st2a8c_join($root, $backupRoot . '/' . $rel);
    $backupDir = dirname($backup);

    if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) {
        st2a8c_fail('cannot create backup dir: ' . $backupDir);
    }

    if (!copy($path, $backup)) {
        st2a8c_fail('cannot create backup: ' . $rel);
    }

    st2a8c_log('backup: ' . $rel . ' -> ' . str_replace($root . DIRECTORY_SEPARATOR, '', $backup));

    if (file_put_contents($path, $content, LOCK_EX) === false) {
        @copy($backup, $path);
        st2a8c_fail('cannot write target; backup restored: ' . $rel);
    }

    st2a8c_log('changed=' . $rel);
}

function st2a8c_patch_checkout(string $content): string
{
    $marker = 'ST-2a.8c: register autosave must not get stuck before AJAX starts.';

    if (strpos($content, $marker) !== false) {
        return $content;
    }

    $content = st2a8c_replace_once(
        $content,
        "  var bsRegisterTimer = null;\n  var bsAutoShippingStarted = false;",
        "  var bsRegisterTimer = null;\n  var bsRegisterAutosaveWatchdog = null;\n  var bsAutoShippingStarted = false;",
        'checkout autosave watchdog variable'
    );

    $content = st2a8c_replace_once(
        $content,
        "  function triggerRegisterAutosave() {\n",
        <<<'TWIG'
  // ST-2a.8c: register autosave must not get stuck before AJAX starts.
  function clearRegisterAutosaveLock(form) {
    window.clearTimeout(bsRegisterAutosaveWatchdog);
    bsRegisterAutosaveWatchdog = null;
    form.data('bsSaving', false).data('bsSubmitPending', false).data('bsPendingSignature', '');
  }

  function clearRegisterAutosaveLockIfChanged(form, signature) {
    if ((form.data('bsSaving') || form.data('bsSubmitPending')) && form.data('bsPendingSignature') !== signature) {
      clearRegisterAutosaveLock(form);
    }
  }

  function scheduleRegisterAutosaveWatchdog(form, signature) {
    window.clearTimeout(bsRegisterAutosaveWatchdog);
    bsRegisterAutosaveWatchdog = window.setTimeout(function() {
      if (form.data('bsSubmitPending') && form.data('bsPendingSignature') === signature) {
        clearRegisterAutosaveLock(form);
      }
    }, 3000);
  }

  function sendRegisterAutosave(form, payload) {
    var send = function() {
      return $.ajax({
        url: 'index.php?route=checkout/register.save&language={{ language }}',
        type: 'post',
        dataType: 'json',
        data: payload,
        contentType: 'application/x-www-form-urlencoded',
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

  function triggerRegisterAutosave() {

TWIG,
        'checkout autosave helper insertion'
    );

    $oldTrigger = <<<'TWIG'
    var signature = form.serialize();

    if (form.data('bsSaving') || form.data('bsLastSaved') === signature) {
      return;
    }

    form.data('bsSaving', true).data('bsPendingSignature', signature);
    $('[data-bs-register-status]').removeClass('bs-is-error').text('Зберігаємо дані для замовлення...');
    form.trigger('submit');
TWIG;

    $newTrigger = <<<'TWIG'
    var signature = form.serialize();

    if (form.data('bsLastSaved') === signature) {
      return;
    }

    clearRegisterAutosaveLockIfChanged(form, signature);

    if (form.data('bsSaving') || form.data('bsSubmitPending')) {
      return;
    }

    form.data('bsSubmitPending', true).data('bsPendingSignature', signature);
    $('[data-bs-register-status]').removeClass('bs-is-error').text('Зберігаємо дані для замовлення...');
    scheduleRegisterAutosaveWatchdog(form, signature);
    sendRegisterAutosave(form, signature);
TWIG;

    $content = st2a8c_replace_once($content, $oldTrigger, $newTrigger, 'checkout triggerRegisterAutosave body');

    $oldFieldChanged = <<<'TWIG'
  window.bsCheckoutNpFieldChanged = function(source) {
    window.bsCheckoutSyncNpFields();

    window.clearTimeout(bsRegisterTimer);
TWIG;

    $newFieldChanged = <<<'TWIG'
  window.bsCheckoutNpFieldChanged = function(source) {
    window.bsCheckoutSyncNpFields();

    var registerForm = $('#form-register');

    if (registerForm.length) {
      clearRegisterAutosaveLockIfChanged(registerForm, registerForm.serialize());
    }

    window.clearTimeout(bsRegisterTimer);
TWIG;

    $content = st2a8c_replace_once($content, $oldFieldChanged, $newFieldChanged, 'checkout NP field changed stale-lock reset');

    $oldAjaxComplete = <<<'TWIG'
  $(document).ajaxComplete(function(event, xhr, settings) {
    var url = settings && settings.url ? settings.url : '';

    if (url.indexOf('checkout/register.save') !== -1) {
      $('#form-register').data('bsSaving', false);
    }
  });
TWIG;

    $newAjaxHandlers = <<<'TWIG'
  $(document).ajaxSend(function(event, xhr, settings) {
    var url = settings && settings.url ? settings.url : '';

    if (url.indexOf('checkout/register.save') !== -1) {
      var form = $('#form-register');
      window.clearTimeout(bsRegisterAutosaveWatchdog);
      bsRegisterAutosaveWatchdog = null;

      if (!form.data('bsPendingSignature')) {
        form.data('bsPendingSignature', form.serialize());
      }

      form.data('bsSaving', true).data('bsSubmitPending', false);
    }
  });

  $(document).ajaxComplete(function(event, xhr, settings) {
    var url = settings && settings.url ? settings.url : '';

    if (url.indexOf('checkout/register.save') !== -1) {
      clearRegisterAutosaveLock($('#form-register'));
    }
  });
TWIG;

    $content = st2a8c_replace_once($content, $oldAjaxComplete, $newAjaxHandlers, 'checkout register.save ajax lifecycle');

    if (strpos($content, $marker) === false || strpos($content, "form.trigger('submit');") !== false) {
        st2a8c_fail('postcheck failed: checkout autosave guard not installed cleanly');
    }

    return $content;
}

function st2a8c_patch_pinta(string $content, bool &$changed): string
{
    $old = <<<'TWIG'
      if (window.bsCheckoutConfirmNpDropdown) {
        window.bsCheckoutConfirmNpDropdown(input[0], ref);
      }

      // ST-2a.8b: mouse dropdown select must wake checkout autosave like keyboard changes do.
      input.trigger('change');
TWIG;

    $new = <<<'TWIG'
      if (window.bsCheckoutConfirmNpDropdown) {
        window.bsCheckoutConfirmNpDropdown(input[0], ref);
      }
TWIG;

    $count = substr_count($content, $old);

    if ($count === 0) {
        $changed = false;
        return $content;
    }

    if ($count !== 1) {
        st2a8c_fail('precheck failed: expected at most 1 ST-2a.8b Pinta trigger block, got ' . $count);
    }

    $changed = true;
    return str_replace($old, $new, $content);
}

st2a8c_log('patch=' . $patch);
st2a8c_log('cwd=' . $root);
st2a8c_log('time=' . gmdate('c'));
st2a8c_log('scope=ST-2a.8c guest checkout register autosave bsSaving guard; Twig/client JS only');
st2a8c_log('db_schema_changes=none');

$checkoutPath = st2a8c_join($root, $checkoutRel);
$pintaPath = st2a8c_join($root, $pintaRel);

$checkoutContent = st2a8c_read($checkoutPath, $checkoutRel);
$pintaContent = st2a8c_read($pintaPath, $pintaRel);

$newCheckout = st2a8c_patch_checkout($checkoutContent);
$pintaChanged = false;
$newPinta = st2a8c_patch_pinta($pintaContent, $pintaChanged);

$writes = [];

if ($newCheckout !== $checkoutContent) {
    $writes[$checkoutRel] = [$checkoutPath, $newCheckout];
} else {
    st2a8c_log('already_applied=' . $checkoutRel);
}

if ($newPinta !== $pintaContent) {
    $writes[$pintaRel] = [$pintaPath, $newPinta];
} else {
    st2a8c_log('unchanged=' . $pintaRel . ' (ST-2a.8b trigger block not present)');
}

if (!$writes) {
    st2a8c_log('done=ok');
    @unlink(__FILE__);
    exit(0);
}

foreach ($writes as $rel => [$path, $content]) {
    st2a8c_backup_and_write($root, $backupRoot, $rel, $path, $content);
}

st2a8c_log('php_modified=none');
st2a8c_log('rollback=restore files from ' . $backupRoot . ' and clear OpenCart template cache');
st2a8c_log('done=ok');
@unlink(__FILE__);
