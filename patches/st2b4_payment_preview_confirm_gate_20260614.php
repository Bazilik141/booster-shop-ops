<?php
/**
 * ST-2b.4 Phase 1: payment preview before address + guarded confirm button.
 *
 * Upload to public_html and run:
 *   php st2b4_payment_preview_confirm_gate_20260614.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = __DIR__;
$dryRunRoot = getenv('BS_PATCH_ROOT');
if ($dryRunRoot !== false && $dryRunRoot !== '') {
    $root = rtrim($dryRunRoot, "/\\");
}

$patchId = 'st2b4_payment_preview_confirm_gate_20260614';
$backupDir = $root . '/_bs_patch_backups/' . $patchId . '_' . date('Ymd_His');
$changed = [];
$alreadyApplied = true;

function bs_fail($message)
{
    fwrite(STDERR, "error=" . $message . PHP_EOL);
    exit(1);
}

function bs_path($root, $relative)
{
    return rtrim($root, "/\\") . '/' . ltrim($relative, "/\\");
}

function bs_read($root, $relative)
{
    $path = bs_path($root, $relative);
    if (!is_file($path)) {
        bs_fail("missing_file:$relative");
    }

    $content = file_get_contents($path);
    if ($content === false) {
        bs_fail("read_failed:$relative");
    }

    return $content;
}

function bs_backup($root, $relative, $backupDir)
{
    $src = bs_path($root, $relative);
    $dst = rtrim($backupDir, "/\\") . '/' . $relative;
    $dir = dirname($dst);

    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        bs_fail("backup_mkdir_failed:$dir");
    }

    if (!copy($src, $dst)) {
        bs_fail("backup_failed:$relative");
    }
}

function bs_write($root, $relative, $content, $backupDir, &$changed, &$alreadyApplied)
{
    $path = bs_path($root, $relative);
    $current = bs_read($root, $relative);

    if ($current === $content) {
        return;
    }

    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true)) {
        bs_fail("backup_root_mkdir_failed:$backupDir");
    }

    bs_backup($root, $relative, $backupDir);

    if (file_put_contents($path, $content) === false) {
        bs_fail("write_failed:$relative");
    }

    $changed[] = $relative;
    $alreadyApplied = false;
}

function bs_replace_once($content, $search, $replace, $label)
{
    $count = substr_count($content, $search);
    if ($count !== 1) {
        bs_fail("anchor_count_failed:$label:$count");
    }

    return str_replace($search, $replace, $content);
}

function bs_regex_replace_once($content, $pattern, $replace, $label)
{
    $count = preg_match_all($pattern, $content, $matches);
    if ($count !== 1) {
        bs_fail("regex_anchor_count_failed:$label:$count");
    }

    $result = preg_replace($pattern, $replace, $content, 1);
    if ($result === null) {
        bs_fail("regex_replace_failed:$label");
    }

    return $result;
}

function bs_regex_replace_optional($content, $pattern, $replace, $label)
{
    $count = preg_match_all($pattern, $content, $matches);
    if ($count > 1) {
        bs_fail("regex_optional_anchor_count_failed:$label:$count");
    }

    if ($count === 0) {
        return $content;
    }

    $result = preg_replace($pattern, $replace, $content, 1);
    if ($result === null) {
        bs_fail("regex_optional_replace_failed:$label");
    }

    return $result;
}

function bs_clean_legacy_confirm_loads($content, $label)
{
    $safe = <<<'JS'
if (window.bsCheckoutRefreshConfirmIfPaymentReady) {
  window.bsCheckoutRefreshConfirmIfPaymentReady();
} else {
  $('#checkout-confirm').html('');
}
JS;

    $pattern = <<<'REGEX'
~([ \t]*)if \(window\.bsSt2b4ConfirmLoad\) \{\s*window\.bsSt2b4ConfirmLoad\('[^']+', null\);\s*\} else \{\s*\$\('#checkout-confirm'\)\.load\('index\.php\?route=checkout/confirm\.confirm&language=\{\{ language \}\}'\);\s*\}~
REGEX;
    $content = preg_replace_callback($pattern, static function ($m) use ($safe) {
        return preg_replace('/^/m', $m[1], $safe);
    }, $content);
    if ($content === null) {
        bs_fail("legacy_confirm_wrapper_replace_failed:$label");
    }

    $pattern = <<<'REGEX'
~(?m)^([ \t]*)\$\('#checkout-confirm'\)\.load\('index\.php\?route=checkout/confirm\.confirm&language=\{\{ language \}\}'\);~
REGEX;
    $content = preg_replace_callback($pattern, static function ($m) use ($safe) {
        return preg_replace('/^/m', $m[1], $safe);
    }, $content);
    if ($content === null) {
        bs_fail("legacy_confirm_direct_replace_failed:$label");
    }

    return $content;
}

function bs_patch_checkout($content)
{
    if (strpos($content, 'ST-2b.4 Phase 1 payment preview confirm gate') !== false) {
        return $content;
    }

    $content = bs_regex_replace_optional(
        $content,
        "~\R  function bsSt2b4DescribeNode\(node\) \{.*?\R  \}\);\R\R  function bsCheckoutDeferredConfirmHtml\(\) \{~s",
        "\n  function bsCheckoutDeferredConfirmHtml() {",
        'checkout_remove_phase0_diagnostics'
    );

    $oldReset = <<<'JS'
  window.bsCheckoutResetMethodState = function(reason) {
    $('#input-shipping-code, #input-shipping-method, #input-payment-code, #input-payment-method').val('');
    $('#bs-payment-methods').html('');
    $('[data-bs-payment-status]').text('Після доставки покажемо способи оплати.');

    if (reason === 'address') {
      $('#checkout-confirm').html('');
    }
  };
JS;

    $newReset = <<<'JS'
  window.bsCheckoutResetMethodState = function(reason) {
    $('#input-shipping-code, #input-shipping-method, #input-payment-code, #input-payment-method').val('');

    if (window.bsCheckoutRenderPaymentPreview) {
      window.bsCheckoutRenderPaymentPreview();
    } else {
      $('#bs-payment-methods').html('');
      $('[data-bs-payment-status]').text('Спосіб оплати можна обрати після завантаження блоку.');
    }

    if (window.bsCheckoutRefreshConfirmIfPaymentReady) {
      window.bsCheckoutRefreshConfirmIfPaymentReady();
    } else if (reason === 'address') {
      $('#checkout-confirm').html('');
    }
  };
JS;

    $content = bs_replace_once($content, $oldReset, $newReset, 'checkout_reset_method_state');

    $cssNeedle = <<<'CSS'
#checkout-checkout .bs-confirm-deferred-summary table {
  margin-bottom: 0;
}
CSS;

    $cssPatch = <<<'CSS'
#checkout-checkout .bs-confirm-deferred-summary table {
  margin-bottom: 0;
}

#checkout-checkout #checkout-confirm [data-bs-deferred-confirm][disabled] {
  opacity: .55;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}
CSS;

    $content = bs_replace_once($content, $cssNeedle, $cssPatch, 'checkout_disabled_button_css');

    $helpers = <<<'JS'
  // ST-2b.4 Phase 1 payment preview confirm gate.
  function bsCheckoutHasShippingReady() {
    return !!$('#input-shipping-code').val();
  }

  function bsCheckoutHasPaymentReady() {
    return !!$('#input-payment-code').val();
  }

  function bsCheckoutCanConfirm() {
    return bsCheckoutHasShippingReady() && bsCheckoutHasPaymentReady();
  }

  function bsCheckoutConfirmHint() {
    var hasShipping = bsCheckoutHasShippingReady();
    var hasPayment = bsCheckoutHasPaymentReady();

    if (!hasShipping && !hasPayment) {
      return 'Заповніть доставку і оберіть спосіб оплати, щоб оформити замовлення.';
    }

    if (!hasShipping) {
      return 'Заповніть доставку, щоб оформити замовлення.';
    }

    if (!hasPayment) {
      return 'Оберіть спосіб оплати, щоб оформити замовлення.';
    }

    return 'Все готово. Натисніть кнопку, щоб оформити замовлення.';
  }

JS;

    $content = bs_replace_once(
        $content,
        "  function bsCheckoutDeferredConfirmHtml() {\n",
        $helpers . "  function bsCheckoutDeferredConfirmHtml() {\n",
        'checkout_insert_readiness_helpers'
    );

    $newDeferred = <<<'JS'
  function bsCheckoutDeferredConfirmHtml() {
    var methodSummary = '';
    var shippingLabel = $('#input-shipping-method').val();
    var paymentLabel = $('#input-payment-method').val();
    var ready = bsCheckoutCanConfirm();

    if (shippingLabel || paymentLabel) {
      methodSummary = '<div class="bs-confirm-method-summary">';
      if (shippingLabel) {
        methodSummary += '<div><strong>Доставка:</strong> ' + bsCheckoutEscapeHtml(shippingLabel) + '</div>';
      }
      if (paymentLabel) {
        methodSummary += '<div><strong>Оплата:</strong> ' + bsCheckoutEscapeHtml(paymentLabel) + '</div>';
      }
      methodSummary += '</div>';
    }

    return '<div class="bs-confirm-deferred" data-bs-confirm-deferred="1">' +
      '<h2 class="mb-3">Підтвердження замовлення</h2>' +
      bsCheckoutCachedSummaryHtml() +
      '<p class="text-muted mb-3">' + bsCheckoutEscapeHtml(bsCheckoutConfirmHint()) + '</p>' +
      methodSummary +
      '<div class="text-end"><button type="button" id="bs-button-confirm-deferred" class="btn btn-primary' + (ready ? '' : ' disabled') + '" data-bs-deferred-confirm="1"' + (ready ? '' : ' disabled aria-disabled="true"') + '>Оформити замовлення</button></div>' +
      '</div>';
  }
JS;

    $content = bs_regex_replace_once(
        $content,
        "~  function bsCheckoutDeferredConfirmHtml\(\) \{.*?\R  \}\R\R  // ST-2b\.1: defer real confirm\.confirm until explicit place-order click\.~s",
        $newDeferred . "\n\n  // ST-2b.1: defer real confirm.confirm until explicit place-order click.",
        'checkout_deferred_confirm_html'
    );

    $newRefresh = <<<'JS'
  window.bsCheckoutRefreshConfirmIfPaymentReady = function() {
    $('#checkout-confirm').html(bsCheckoutDeferredConfirmHtml());
    return bsCheckoutCanConfirm();
  };
JS;

    $content = bs_regex_replace_once(
        $content,
        "~  window\.bsCheckoutRefreshConfirmIfPaymentReady = function\(\) \{.*?\R  \};~s",
        $newRefresh,
        'checkout_refresh_confirm'
    );

    $newSubmit = <<<'JS'
  window.bsCheckoutLoadConfirmAndSubmit = function(trigger) {
    if (!bsCheckoutCanConfirm()) {
      $('#checkout-confirm').html(bsCheckoutDeferredConfirmHtml());
      return false;
    }

    if (bsCheckoutConfirmSubmitting) {
      return false;
    }

    bsCheckoutConfirmSubmitting = true;

    var button = $(trigger);
    button.prop('disabled', true).addClass('loading').text('Створюємо замовлення...');
    $('#checkout-confirm').addClass('bs-confirm-loading');

    $('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}', function(response, status) {
      $('#checkout-confirm').removeClass('bs-confirm-loading');

      if (status === 'error') {
        bsCheckoutConfirmSubmitting = false;
        $('#checkout-confirm').html('<div class="alert alert-danger mb-0">Не вдалося створити замовлення. Оновіть сторінку і спробуйте ще раз.</div>');
        return;
      }

      hideModelColumns();

      var realButton = $('#checkout-confirm #button-confirm').first();

      if (!realButton.length) {
        bsCheckoutConfirmSubmitting = false;
        $('#checkout-confirm').append('<div class="alert alert-danger mt-3 mb-0">Не знайдено кнопку підтвердження оплати після створення замовлення.</div>');
        return;
      }

      window.setTimeout(function() {
        realButton.trigger('click');
        bsCheckoutConfirmSubmitting = false;
      }, 30);
    });

    return false;
  };
JS;

    $content = bs_regex_replace_once(
        $content,
        "~  window\.bsCheckoutLoadConfirmAndSubmit = function\(trigger(?:, event)?\) \{.*?\R  \};\R~s",
        $newSubmit . "\n",
        'checkout_load_confirm_submit'
    );

    $content = str_replace(
        "window.bsCheckoutLoadConfirmAndSubmit(this, event);",
        "window.bsCheckoutLoadConfirmAndSubmit(this);",
        $content
    );

    if (strpos($content, 'bsSt2b4') !== false) {
        bs_fail('checkout_phase0_diagnostics_still_present');
    }

    return $content;
}

function bs_patch_payment_method($content)
{
    if (strpos($content, 'ST-2b.4 Phase 1 payment preview') !== false) {
        return $content;
    }

    $script = <<<'TWIG'
<script type="text/javascript"><!--
(function() {
  // ST-2b.4 Phase 1 payment preview: show payment choices before address, save only after delivery is ready.
  var bsPaymentPreviewMethods = [
    { key: 'hutko', label: 'Оплата карткою, Google / Apple Pay' },
    { key: 'cod', label: 'Оплата при доставці (післяплата)' },
    { key: 'bank', label: 'Оплата за реквізитами' }
  ];
  var bsPaymentPendingChoice = 'hutko';

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function(chr) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      }[chr];
    });
  }

  function status(message, isError) {
    var $status = $('[data-bs-payment-status]');
    $status.toggleClass('text-danger', !!isError);
    $status.text(message || '');
  }

  function hasShippingReady() {
    return !!$('#input-shipping-code').val();
  }

  function normalizeChoice(choice) {
    choice = String(choice || '').toLowerCase();

    if (choice.indexOf('hutko') !== -1 || choice.indexOf('mono') !== -1 || choice.indexOf('card') !== -1) {
      return 'hutko';
    }

    if (choice.indexOf('cod') !== -1 || choice.indexOf('cash') !== -1 || choice.indexOf('after') !== -1) {
      return 'cod';
    }

    if (choice.indexOf('bank') !== -1 || choice.indexOf('transfer') !== -1 || choice.indexOf('rekv') !== -1) {
      return 'bank';
    }

    return choice || 'hutko';
  }

  function paymentMatchesChoice(code, choice) {
    code = String(code || '').toLowerCase();
    choice = normalizeChoice(choice);

    if (!code) {
      return false;
    }

    if (choice === 'hutko') {
      return code.indexOf('hutko') !== -1 || code.indexOf('mono') !== -1 || code.indexOf('card') !== -1;
    }

    if (choice === 'cod') {
      return code.indexOf('cod') !== -1 || code.indexOf('cash') !== -1 || code.indexOf('after') !== -1;
    }

    if (choice === 'bank') {
      return code.indexOf('bank') !== -1 || code.indexOf('transfer') !== -1 || code.indexOf('rekv') !== -1;
    }

    return code === choice || code.indexOf(choice + '.') === 0 || code.indexOf(choice) === 0;
  }

  function flattenPaymentMethods(json) {
    var options = [];

    if (json && json.payment_methods) {
      $.each(json.payment_methods, function(groupKey, group) {
        if (group && group.option) {
          $.each(group.option, function(optionKey, option) {
            if (option && option.code) {
              options.push({
                code: option.code,
                label: option.name || option.title || group.name || groupKey,
                id: optionKey
              });
            }
          });
        } else if (group && group.code) {
          options.push({
            code: group.code,
            label: group.name || group.title || groupKey,
            id: groupKey
          });
        }
      });
    }

    return options;
  }

  function findPaymentOption(options, choice) {
    var found = null;

    $.each(options, function(index, option) {
      if (!found && paymentMatchesChoice(option.code, choice)) {
        found = option;
      }
    });

    return found;
  }

  function refreshConfirmSummary() {
    if (window.bsCheckoutRefreshConfirmIfPaymentReady) {
      window.bsCheckoutRefreshConfirmIfPaymentReady();
    }
  }

  function renderPaymentPreview() {
    var current = normalizeChoice(bsPaymentPendingChoice || $('#input-payment-code').val());
    var html = '';

    $.each(bsPaymentPreviewMethods, function(index, method) {
      var inputId = 'bs-payment-preview-' + method.key;
      var checked = current === method.key ? ' checked' : '';

      html += '<label class="bs-checkout-method-option" for="' + inputId + '">' +
        '<input type="radio" name="payment_method_preview" id="' + inputId + '" value="' + escapeHtml(method.key) + '"' + checked + ' />' +
        '<span>' + escapeHtml(method.label) + '</span>' +
        '</label>';
    });

    $('#bs-payment-methods').html(html);
    status('Спосіб оплати можна обрати зараз. Застосуємо після заповнення доставки.');
    refreshConfirmSummary();
  }

  window.bsCheckoutRenderPaymentPreview = renderPaymentPreview;

  function renderPaymentMethods(json) {
    if (json && json.error) {
      status(json.error, true);
      return;
    }

    var options = flattenPaymentMethods(json);
    var current = $('#input-payment-code').val();
    var selected = current ? findPaymentOption(options, current) : null;

    if (!selected) {
      selected = findPaymentOption(options, bsPaymentPendingChoice) || findPaymentOption(options, 'hutko') || options[0] || null;
    }

    if (!options.length) {
      $('#bs-payment-methods').html('');
      status('Немає доступних способів оплати для цієї доставки.', true);
      refreshConfirmSummary();
      return;
    }

    var html = '';
    $.each(options, function(index, option) {
      var inputId = 'input-payment-method-' + index;
      var checked = selected && selected.code === option.code ? ' checked' : '';

      html += '<label class="bs-checkout-method-option" for="' + inputId + '">' +
        '<input type="radio" name="payment_method" id="' + inputId + '" value="' + escapeHtml(option.code) + '" data-label="' + escapeHtml(option.label) + '"' + checked + ' />' +
        '<span>' + escapeHtml(option.label) + '</span>' +
        '</label>';
    });

    $('#bs-payment-methods').html(html);

    if (selected && !current) {
      savePayment(selected.code, selected.label, true);
      return;
    }

    status(current ? 'Оплату обрано.' : 'Оберіть спосіб оплати.');
    refreshConfirmSummary();
  }

  function savePayment(code, label, isAuto) {
    if (!code) {
      return;
    }

    bsPaymentPendingChoice = normalizeChoice(code);

    if (!hasShippingReady()) {
      renderPaymentPreview();
      return;
    }

    status(isAuto ? 'Автоматично застосовуємо спосіб оплати.' : 'Зберігаємо спосіб оплати...');

    $.ajax({
      url: 'index.php?route=checkout/payment_method.save&language={{ language }}',
      type: 'post',
      data: { payment_method: code },
      dataType: 'json',
      success: function(json) {
        $('#error-payment-method').removeClass('d-block').text('');

        if (json && json.error) {
          $('#error-payment-method').addClass('d-block').text(json.error);
          status(json.error, true);
          refreshConfirmSummary();
          return;
        }

        $('#input-payment-method').val(label || code);
        $('#input-payment-code').val(code);
        status('Оплату обрано.');
        refreshConfirmSummary();
      },
      error: function(xhr, ajaxOptions, thrownError) {
        status(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText, true);
        refreshConfirmSummary();
      }
    });
  }

  window.bsCheckoutLoadPaymentMethods = function() {
    if (!hasShippingReady()) {
      renderPaymentPreview();
      return;
    }

    status('Завантажуємо способи оплати...');

    $.ajax({
      url: 'index.php?route=checkout/payment_method.getMethods&language={{ language }}',
      dataType: 'json',
      success: renderPaymentMethods,
      error: function(xhr, ajaxOptions, thrownError) {
        status(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText, true);
        refreshConfirmSummary();
      }
    });
  };

  $(document).on('change', '#bs-payment-methods input[name="payment_method_preview"]', function() {
    bsPaymentPendingChoice = normalizeChoice(this.value);
    renderPaymentPreview();
  });

  $(document).on('change', '#bs-payment-methods input[name="payment_method"]', function() {
    var $input = $(this);
    savePayment($input.val(), $input.data('label'), false);
  });

  $(document).on('change', 'input[name="agree"]', function() {
    var $input = $(this);

    $.ajax({
      url: 'index.php?route=checkout/payment_method.comment&language={{ language }}',
      type: 'post',
      data: {
        comment: $('textarea[name="comment"]').val(),
        agree: $input.is(':checked') ? '1' : ''
      },
      dataType: 'json',
      success: function(json) {
        if (json && json.error) {
          alert(json.error);
          return;
        }

        refreshConfirmSummary();
      }
    });
  });

  $(function() {
    bsPaymentPendingChoice = normalizeChoice($('#input-payment-code').val() || bsPaymentPendingChoice);

    if (hasShippingReady()) {
      setTimeout(window.bsCheckoutLoadPaymentMethods, 300);
    } else {
      renderPaymentPreview();
    }
  });
})();
//--></script>
TWIG;

    $content = bs_regex_replace_once(
        $content,
        "~<script type=\"text/javascript\"><!--.*?//--></script>~s",
        $script,
        'payment_method_script'
    );

    return $content;
}

function bs_assert_postconditions($root)
{
    $checkout = bs_read($root, 'catalog/view/template/checkout/checkout.twig');
    if (strpos($checkout, 'ST-2b.4 Phase 1 payment preview confirm gate') === false) {
        bs_fail('postcheck_checkout_marker_missing');
    }
    if (strpos($checkout, 'bsSt2b4') !== false) {
        bs_fail('postcheck_checkout_phase0_leftover');
    }
    if (substr_count($checkout, 'checkout/confirm.confirm') !== 1) {
        bs_fail('postcheck_checkout_confirm_route_count:' . substr_count($checkout, 'checkout/confirm.confirm'));
    }

    $payment = bs_read($root, 'catalog/view/template/checkout/payment_method.twig');
    if (strpos($payment, 'ST-2b.4 Phase 1 payment preview') === false) {
        bs_fail('postcheck_payment_marker_missing');
    }
    if (strpos($payment, 'checkout/confirm.confirm') !== false || strpos($payment, 'bsSt2b4') !== false) {
        bs_fail('postcheck_payment_unsafe_confirm_leftover');
    }

    $partials = [
        'catalog/view/template/checkout/shipping_address.twig',
        'catalog/view/template/checkout/payment_address.twig',
        'catalog/view/template/checkout/register.twig',
    ];

    foreach ($partials as $partial) {
        $content = bs_read($root, $partial);
        if (strpos($content, 'checkout/confirm.confirm') !== false || strpos($content, 'bsSt2b4') !== false) {
            bs_fail('postcheck_partial_unsafe_confirm_leftover:' . $partial);
        }
    }
}

$files = [
    'catalog/view/template/checkout/checkout.twig',
    'catalog/view/template/checkout/payment_method.twig',
    'catalog/view/template/checkout/shipping_address.twig',
    'catalog/view/template/checkout/payment_address.twig',
    'catalog/view/template/checkout/register.twig',
];

foreach ($files as $file) {
    bs_read($root, $file);
}

$checkoutFile = 'catalog/view/template/checkout/checkout.twig';
$paymentFile = 'catalog/view/template/checkout/payment_method.twig';

$checkout = bs_patch_checkout(bs_read($root, $checkoutFile));
bs_write($root, $checkoutFile, $checkout, $backupDir, $changed, $alreadyApplied);

$payment = bs_patch_payment_method(bs_read($root, $paymentFile));
bs_write($root, $paymentFile, $payment, $backupDir, $changed, $alreadyApplied);

foreach ([
    'catalog/view/template/checkout/shipping_address.twig',
    'catalog/view/template/checkout/payment_address.twig',
    'catalog/view/template/checkout/register.twig',
] as $file) {
    $content = bs_read($root, $file);
    $updated = bs_clean_legacy_confirm_loads($content, $file);
    bs_write($root, $file, $updated, $backupDir, $changed, $alreadyApplied);
}

bs_assert_postconditions($root);

echo 'patch=' . $patchId . PHP_EOL;
echo 'already_applied=' . ($alreadyApplied ? 'yes' : 'no') . PHP_EOL;
echo 'changed_files=' . count($changed) . PHP_EOL;
foreach ($changed as $file) {
    echo 'changed=' . $file . PHP_EOL;
}

if (!$alreadyApplied) {
    echo 'backup_dir=' . $backupDir . PHP_EOL;
}

echo 'done=ok' . PHP_EOL;

@unlink(__FILE__);
