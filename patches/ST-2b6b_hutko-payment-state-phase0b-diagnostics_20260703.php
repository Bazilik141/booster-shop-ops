<?php
declare(strict_types=1);

/*
 * ST-2b.6 Phase 0b only: observe silent payment-method changes and
 * old/new checkout summary desynchronization.
 *
 * Behavior changes: none. Database changes: none.
 * Rollback: restore every printed backup, then clear OpenCart template/cache.
 */

const BS6B_ID = 'ST-2b6b_hutko-payment-state-phase0b-diagnostics_20260703';

$targets = [
    'new_checkout' => [
        'path' => 'catalog/view/template/checkout/checkout.twig',
        'sha256' => '0221400fe5daa72ee287dd2e948db1f3344c8a555b7e8ee672ba3098656c630a',
        'marker' => 'ST-2b.6 Phase 0b new-checkout state diagnostics',
    ],
    'new_payment' => [
        'path' => 'catalog/view/template/checkout/payment_method.twig',
        'sha256' => '10b6faf0f48d1c7d6fb0a0f4846bc1f443771e827396a7457fba03f7a82f4110',
        'marker' => 'ST-2b.6 Phase 0b payment-method diagnostics',
    ],
    'new_shipping' => [
        'path' => 'catalog/view/template/checkout/shipping_method.twig',
        'sha256' => 'cecc37ee98dda44b8c465700a3efd4da6b78c95c0491cb88ed692be465748fb0',
        'marker' => 'ST-2b.6 Phase 0b shipping reset diagnostics',
    ],
    'simple_checkout' => [
        'path' => 'extension/SimpleCheckout/catalog/view/template/module/checkout.twig',
        'sha256' => '612a8ba7253c25b408007fdef8915ad5223afbe0bf7c45a11356051c40513cb3',
        'marker' => 'ST-2b.6 Phase 0b SimpleCheckout diagnostics',
    ],
];

function bs6b_out(string $key, string $value = ''): void {
    echo $key . ($value !== '' ? '=' . $value : '') . PHP_EOL;
}

function bs6b_fail(string $message): void {
    throw new RuntimeException($message);
}

function bs6b_path(string $relative): string {
    return __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function bs6b_read(string $relative): string {
    $path = bs6b_path($relative);
    if (!is_file($path)) {
        bs6b_fail('missing_file:' . $relative);
    }
    $content = file_get_contents($path);
    if ($content === false) {
        bs6b_fail('read_failed:' . $relative);
    }
    return $content;
}

function bs6b_count(string $content, string $needle, int $expected, string $label): void {
    $actual = substr_count($content, $needle);
    if ($actual !== $expected) {
        bs6b_fail("anchor_count_mismatch:$label:expected=$expected:actual=$actual");
    }
}

function bs6b_replace(string $content, string $search, string $replace, string $label): string {
    bs6b_count($content, $search, 1, $label);
    return str_replace($search, $replace, $content);
}

function bs6b_lint_self(): void {
    if (!function_exists('exec')) {
        bs6b_fail('php_lint_unavailable:exec_disabled');
    }
    $output = [];
    $code = 1;
    $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
    exec(escapeshellarg($php) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        bs6b_fail('php_lint_failed:' . implode(' | ', $output));
    }
    bs6b_out('php_l_patch', 'ok');
}

function bs6b_backup(string $relative, string $root): string {
    $source = bs6b_path($relative);
    $backup = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0755, true) && !is_dir(dirname($backup))) {
        bs6b_fail('backup_directory_failed:' . dirname($backup));
    }
    if (!copy($source, $backup)) {
        bs6b_fail('backup_failed:' . $relative);
    }
    bs6b_out('backup', $backup);
    return $backup;
}

function bs6b_write(string $relative, string $content): void {
    $target = bs6b_path($relative);
    $temp = $target . '.st2b6b-tmp-' . getmypid();
    if (file_put_contents($temp, $content, LOCK_EX) !== strlen($content)) {
        @unlink($temp);
        bs6b_fail('temporary_write_failed:' . $relative);
    }
    if (!@rename($temp, $target)) {
        $written = file_put_contents($target, $content, LOCK_EX);
        @unlink($temp);
        if ($written !== strlen($content)) {
            bs6b_fail('target_write_failed:' . $relative);
        }
    }
}

function bs6b_patch_new_checkout(string $content): string {
    $content = bs6b_replace(
        $content,
        "  window.bsCheckoutResetMethodState = function(reason) {\n" .
        "    \$('#input-shipping-code, #input-shipping-method, #input-payment-code, #input-payment-method').val('');\n",
        "  window.bsCheckoutResetMethodState = function(reason) {\n" .
        "    var bsSt2b6bOldPaymentCode = \$('#input-payment-code').val() || '';\n" .
        "    if (window.bsSt2b6Log) {\n" .
        "      window.bsSt2b6Log('phase0b:new:reset-method-state:before', null, null, { reason: reason || '', oldPaymentCode: bsSt2b6bOldPaymentCode, newPaymentCode: '' });\n" .
        "    }\n" .
        "    \$('#input-shipping-code, #input-shipping-method, #input-payment-code, #input-payment-method').val('');\n" .
        "    if (window.bsSt2b6Log && bsSt2b6bOldPaymentCode !== '') {\n" .
        "      window.bsSt2b6Log('phase0b:new:payment-code-changed', null, null, { changeSource: 'bsCheckoutResetMethodState', oldPaymentCode: bsSt2b6bOldPaymentCode, newPaymentCode: '', reason: reason || '' });\n" .
        "    }\n",
        'new_checkout_reset'
    );

    $helper = <<<'JS'
  // ST-2b.6 Phase 0b new-checkout state diagnostics.
  function bsSt2b6bCheckedState(selector) {
    var input = $(selector).filter(':checked').first();
    return {
      code: input.length ? String(input.val() || '') : '',
      label: input.length ? String(input.data('label') || input.attr('title') || input.closest('label').text() || '').replace(/\s+/g, ' ').trim() : ''
    };
  }

  function bsSt2b6bConfirmComparison(displayShippingLabel, displayPaymentLabel) {
    var payment = bsSt2b6bCheckedState('#bs-payment-methods input[name="payment_method"]');
    var paymentPreview = bsSt2b6bCheckedState('#bs-payment-methods input[name="payment_method_preview"]');
    var shipping = bsSt2b6bCheckedState('#checkout-shipping-method input[name="shipping_method"]');
    var hiddenPaymentCode = String($('#input-payment-code').val() || '');
    var hiddenShippingCode = String($('#input-shipping-code').val() || '');

    return {
      displayPaymentLabel: String(displayPaymentLabel || ''),
      displayShippingLabel: String(displayShippingLabel || ''),
      hiddenPaymentCode: hiddenPaymentCode,
      hiddenPaymentLabel: String($('#input-payment-method').val() || ''),
      hiddenShippingCode: hiddenShippingCode,
      hiddenShippingLabel: String($('#input-shipping-method').val() || ''),
      livePaymentRadioCode: payment.code,
      livePaymentRadioLabel: payment.label,
      previewPaymentRadioCode: paymentPreview.code,
      previewPaymentRadioLabel: paymentPreview.label,
      liveShippingRadioCode: shipping.code,
      liveShippingRadioLabel: shipping.label,
      paymentCodeDiverged: !!payment.code && payment.code !== hiddenPaymentCode,
      shippingCodeDiverged: !!shipping.code && shipping.code !== hiddenShippingCode
    };
  }

JS;

    $content = bs6b_replace(
        $content,
        "  function bsCheckoutDeferredConfirmHtml() {\n",
        $helper . "  function bsCheckoutDeferredConfirmHtml() {\n",
        'new_checkout_comparison_helper'
    );

    $content = bs6b_replace(
        $content,
        "    var ready = bsCheckoutCanConfirm();\n\n" .
        "    if (shippingLabel || paymentLabel) {\n",
        "    var ready = bsCheckoutCanConfirm();\n\n" .
        "    if (window.bsSt2b6Log) {\n" .
        "      window.bsSt2b6Log('phase0b:new:confirm-summary:before-render', null, null, bsSt2b6bConfirmComparison(shippingLabel, paymentLabel));\n" .
        "    }\n\n" .
        "    if (shippingLabel || paymentLabel) {\n",
        'new_checkout_before_render'
    );

    $content = bs6b_replace(
        $content,
        "  window.bsCheckoutRefreshConfirmIfPaymentReady = function() {\n" .
        "    \$('#checkout-confirm').html(bsCheckoutDeferredConfirmHtml());\n" .
        "    return bsCheckoutCanConfirm();\n" .
        "  };\n",
        "  window.bsCheckoutRefreshConfirmIfPaymentReady = function() {\n" .
        "    \$('#checkout-confirm').html(bsCheckoutDeferredConfirmHtml());\n" .
        "    if (window.bsSt2b6Log) {\n" .
        "      window.bsSt2b6Log('phase0b:new:confirm-summary:after-render', null, null, {\n" .
        "        renderedMethodText: String(\$('#checkout-confirm .bs-confirm-method-summary').text() || '').replace(/\\s+/g, ' ').trim()\n" .
        "      });\n" .
        "    }\n" .
        "    return bsCheckoutCanConfirm();\n" .
        "  };\n",
        'new_checkout_after_render'
    );

    return $content;
}

function bs6b_patch_new_payment(string $content): string {
    $helper = <<<'JS'
  // ST-2b.6 Phase 0b payment-method diagnostics.
  function bsSt2b6bPaymentLog(source, event, extra) {
    if (window.bsSt2b6Log) {
      window.bsSt2b6Log(source, event || null, event && event.currentTarget ? event.currentTarget : null, extra || {});
    }
  }

JS;
    $content = bs6b_replace(
        $content,
        "<script type=\"text/javascript\"><!--\n(function() {\n",
        "<script type=\"text/javascript\"><!--\n(function() {\n" . $helper,
        'new_payment_helper'
    );

    $content = bs6b_replace(
        $content,
        "    if (!selected) {\n" .
        "      selected = findPaymentOption(options, bsPaymentPendingChoice) || findPaymentOption(options, 'hutko') || options[0] || null;\n" .
        "    }\n",
        "    if (!selected) {\n" .
        "      selected = findPaymentOption(options, bsPaymentPendingChoice) || findPaymentOption(options, 'hutko') || options[0] || null;\n" .
        "      bsSt2b6bPaymentLog('phase0b:new:default-payment-candidate', null, {\n" .
        "        currentPaymentCode: current || '', pendingChoice: bsPaymentPendingChoice || '', selectedCode: selected ? selected.code : '', optionCodes: $.map(options, function(option) { return option.code || ''; })\n" .
        "      });\n" .
        "    }\n",
        'new_payment_default'
    );

    $content = bs6b_replace(
        $content,
        "    if (selected && !current) {\n" .
        "      savePayment(selected.code, selected.label, true);\n",
        "    if (selected && !current) {\n" .
        "      bsSt2b6bPaymentLog('phase0b:new:auto-save-payment', null, { oldPaymentCode: '', newPaymentCode: selected.code || '', pendingChoice: bsPaymentPendingChoice || '' });\n" .
        "      savePayment(selected.code, selected.label, true, null);\n",
        'new_payment_auto_save'
    );

    $content = bs6b_replace(
        $content,
        "  function savePayment(code, label, isAuto) {\n" .
        "    if (!code) {\n" .
        "      return;\n" .
        "    }\n",
        "  function savePayment(code, label, isAuto, event) {\n" .
        "    if (!code) {\n" .
        "      return;\n" .
        "    }\n\n" .
        "    bsSt2b6bPaymentLog('phase0b:new:save-payment:entry', event || null, { oldPaymentCode: \$('#input-payment-code').val() || '', newPaymentCode: code || '', isAuto: !!isAuto });\n",
        'new_payment_save_entry'
    );

    $content = bs6b_replace(
        $content,
        "        \$('#input-payment-method').val(label || code);\n" .
        "        \$('#input-payment-code').val(code);\n" .
        "        status('Оплату обрано.');\n",
        "        var bsSt2b6bOldCode = \$('#input-payment-code').val() || '';\n" .
        "        \$('#input-payment-method').val(label || code);\n" .
        "        \$('#input-payment-code').val(code);\n" .
        "        bsSt2b6bPaymentLog('phase0b:new:payment-code-changed', event || null, { changeSource: 'savePayment.success', oldPaymentCode: bsSt2b6bOldCode, newPaymentCode: code || '', isAuto: !!isAuto });\n" .
        "        status('Оплату обрано.');\n",
        'new_payment_write'
    );

    $content = bs6b_replace(
        $content,
        "  \$(document).on('change', '#bs-payment-methods input[name=\"payment_method_preview\"]', function() {\n" .
        "    bsPaymentPendingChoice = normalizeChoice(this.value);\n",
        "  \$(document).on('change', '#bs-payment-methods input[name=\"payment_method_preview\"]', function(event) {\n" .
        "    bsSt2b6bPaymentLog('phase0b:new:preview-radio-change', event, { oldPendingChoice: bsPaymentPendingChoice || '', newPendingChoice: normalizeChoice(this.value) });\n" .
        "    bsPaymentPendingChoice = normalizeChoice(this.value);\n",
        'new_payment_preview_change'
    );

    $content = bs6b_replace(
        $content,
        "  \$(document).on('change', '#bs-payment-methods input[name=\"payment_method\"]', function() {\n" .
        "    var \$input = \$(this);\n" .
        "    savePayment(\$input.val(), \$input.data('label'), false);\n",
        "  \$(document).on('change', '#bs-payment-methods input[name=\"payment_method\"]', function(event) {\n" .
        "    var \$input = \$(this);\n" .
        "    bsSt2b6bPaymentLog('phase0b:new:payment-radio-change', event, { oldPaymentCode: \$('#input-payment-code').val() || '', newPaymentCode: \$input.val() || '' });\n" .
        "    savePayment(\$input.val(), \$input.data('label'), false, event);\n",
        'new_payment_radio_change'
    );

    $content = bs6b_replace(
        $content,
        "  \$(function() {\n" .
        "    bsPaymentPendingChoice = normalizeChoice(\$('#input-payment-code').val() || bsPaymentPendingChoice);\n",
        "  \$(function() {\n" .
        "    bsSt2b6bPaymentLog('phase0b:new:payment-module-ready', null, { paymentCode: \$('#input-payment-code').val() || '', pendingChoice: bsPaymentPendingChoice || '', shippingCode: \$('#input-shipping-code').val() || '' });\n" .
        "    bsPaymentPendingChoice = normalizeChoice(\$('#input-payment-code').val() || bsPaymentPendingChoice);\n",
        'new_payment_ready'
    );

    return $content;
}

function bs6b_patch_new_shipping(string $content): string {
    return bs6b_replace(
        $content,
        "            \$('#input-shipping-code').val(code);\n" .
        "            \$('#input-shipping-method').val(label || code);\n" .
        "            \$('#input-payment-method').val('');\n" .
        "            \$('#input-payment-code').val('');\n",
        "            var bsSt2b6bOldPaymentCode = \$('#input-payment-code').val() || '';\n" .
        "            \$('#input-shipping-code').val(code);\n" .
        "            \$('#input-shipping-method').val(label || code);\n" .
        "            \$('#input-payment-method').val('');\n" .
        "            \$('#input-payment-code').val('');\n" .
        "            // ST-2b.6 Phase 0b shipping reset diagnostics.\n" .
        "            if (window.bsSt2b6Log) {\n" .
        "              window.bsSt2b6Log('phase0b:new:payment-code-changed', null, null, { changeSource: 'shipping_method.save.success', oldPaymentCode: bsSt2b6bOldPaymentCode, newPaymentCode: '', shippingCode: code || '' });\n" .
        "            }\n",
        'new_shipping_reset'
    );
}

function bs6b_patch_simple_checkout(string $content): string {
    $logger = <<<'JS'
    // ST-2b.6 Phase 0b SimpleCheckout diagnostics.
    var bsSt2b6bStorageKey = 'bs.st2b6.diag.v1';
    var bsSt2b6bLastSimplePaymentCode = null;

    function bsSt2b6bSimpleRead() {
        try {
            var stored = window.localStorage.getItem(bsSt2b6bStorageKey);
            var parsed = stored ? JSON.parse(stored) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function bsSt2b6bSimpleNode(node) {
        if (!node) return '';
        return String(node.tagName || '').toLowerCase() +
            (node.id ? '#' + node.id : '') +
            (node.name ? '[name="' + node.name + '"]' : '');
    }

    window.__bsSt2b6Diag = bsSt2b6bSimpleRead();
    window.bsSt2b6ReadDiagnostics = function() { return bsSt2b6bSimpleRead(); };
    window.bsSt2b6ClearDiagnostics = function() {
        try { window.localStorage.removeItem(bsSt2b6bStorageKey); } catch (error) {}
        window.__bsSt2b6Diag = [];
        bsSt2b6bLastSimplePaymentCode = null;
        return true;
    };

    window.bsSt2b6Log = function(source, event, trigger, extra) {
        var nativeEvent = event && event.originalEvent ? event.originalEvent : event;
        var payment = $('.payment-method input[name="payment_method"]:checked').first();
        var shipping = $('.shipping-method input[name="shipping_method"]:checked').first();
        var entry = {
            sequence: window.__bsSt2b6Diag.length + 1,
            time: new Date().toISOString(),
            page: 'simple-checkout',
            source: source || '',
            eventType: nativeEvent && nativeEvent.type ? nativeEvent.type : (event && event.type ? event.type : ''),
            isTrusted: nativeEvent && typeof nativeEvent.isTrusted !== 'undefined' ? nativeEvent.isTrusted : null,
            persisted: nativeEvent && typeof nativeEvent.persisted !== 'undefined' ? nativeEvent.persisted : null,
            activeElement: bsSt2b6bSimpleNode(document.activeElement),
            target: bsSt2b6bSimpleNode(event && event.target ? event.target : null),
            trigger: bsSt2b6bSimpleNode(trigger),
            visibilityState: document.visibilityState || '',
            paymentCode: payment.length ? String(payment.val() || '') : '',
            paymentLabel: payment.length ? String(payment.prop('title') || payment.closest('label').text() || '').replace(/\s+/g, ' ').trim() : '',
            shippingCode: shipping.length ? String(shipping.val() || '') : '',
            shippingLabel: shipping.length ? String(shipping.prop('title') || shipping.closest('label').text() || '').replace(/\s+/g, ' ').trim() : '',
            stack: (new Error('ST-2b.6 Phase 0b SimpleCheckout stack')).stack || ''
        };
        if (extra) {
            Object.keys(extra).forEach(function(key) { entry[key] = extra[key]; });
        }
        window.__bsSt2b6Diag.push(entry);
        if (window.__bsSt2b6Diag.length > 400) {
            window.__bsSt2b6Diag = window.__bsSt2b6Diag.slice(-400);
        }
        try {
            window.localStorage.setItem(bsSt2b6bStorageKey, JSON.stringify(window.__bsSt2b6Diag));
        } catch (error) {
            entry.storageError = String(error && error.message ? error.message : error);
        }
        if (window.console && console.warn) console.warn('[ST-2b.6 Phase 0b]', entry);
        return entry;
    };

    function bsSt2b6bSampleSimplePayment(source, event) {
        var payment = $('.payment-method input[name="payment_method"]:checked').first();
        var code = payment.length ? String(payment.val() || '') : '';
        if (bsSt2b6bLastSimplePaymentCode === null || code !== bsSt2b6bLastSimplePaymentCode) {
            window.bsSt2b6Log(source, event || null, payment.length ? payment[0] : null, {
                oldPaymentCode: bsSt2b6bLastSimplePaymentCode === null ? '' : bsSt2b6bLastSimplePaymentCode,
                newPaymentCode: code
            });
            bsSt2b6bLastSimplePaymentCode = code;
        }
    }

    window.addEventListener('pageshow', function(event) { window.bsSt2b6Log('phase0b:simple:pageshow', event, null); });
    window.addEventListener('pagehide', function(event) { window.bsSt2b6Log('phase0b:simple:pagehide', event, null); });
    document.addEventListener('visibilitychange', function(event) { window.bsSt2b6Log('phase0b:simple:visibilitychange', event, null); });
    $(document).on('click.bsSt2b6b change.bsSt2b6b', '.payment-method input[name="payment_method"]', function(event) {
        window.bsSt2b6Log('phase0b:simple:payment-radio-event', event, this, { newPaymentCode: String($(this).val() || '') });
        bsSt2b6bSampleSimplePayment('phase0b:simple:payment-radio-state', event);
    });
    $(document).ajaxComplete(function(event) { bsSt2b6bSampleSimplePayment('phase0b:simple:ajax-complete-state', event); });
    window.setInterval(function() { bsSt2b6bSampleSimplePayment('phase0b:simple:state-observer', null); }, 250);
    $(function() { bsSt2b6bSampleSimplePayment('phase0b:simple:ready-state', null); });
JS;

    $content = bs6b_replace(
        $content,
        "    var error = true;\r\n",
        "    var error = true;\r\n" . $logger . "\n",
        'simple_logger'
    );

    $content = bs6b_replace(
        $content,
        "    if (!\$methods.length || \$methods.filter(':checked').length) {\n" .
        "        return;\n" .
        "    }\n\n" .
        "    var \$preferred = \$methods.filter('[value^=\"hutko\"]');\n",
        "    if (!\$methods.length || \$methods.filter(':checked').length) {\n" .
        "        return;\n" .
        "    }\n\n" .
        "    var \$preferred = \$methods.filter('[value^=\"hutko\"]');\n" .
        "    window.bsSt2b6Log('phase0b:simple:select-preferred-payment', null, null, { oldPaymentCode: '', preferredPrefix: 'hutko', candidateCode: \$preferred.length ? String(\$preferred.first().val() || '') : '' });\n",
        'simple_preferred'
    );

    $content = bs6b_replace(
        $content,
        "    \$methods.not(\$checked).prop('checked', false).removeAttr('checked');\n" .
        "    \$checked.prop('checked', true).attr('checked', 'checked');\n\n" .
        "    return \$checked;\n",
        "    var bsSt2b6bBeforeCode = \$methods.filter(':checked').first().val() || '';\n" .
        "    \$methods.not(\$checked).prop('checked', false).removeAttr('checked');\n" .
        "    \$checked.prop('checked', true).attr('checked', 'checked');\n" .
        "    if (name === 'payment_method') {\n" .
        "        window.bsSt2b6Log('phase0b:simple:ensure-method-selection', null, \$checked[0] || null, { oldPaymentCode: String(bsSt2b6bBeforeCode || ''), newPaymentCode: String(\$checked.val() || ''), preferredPrefix: preferredPrefix || '' });\n" .
        "        bsSt2b6bSampleSimplePayment('phase0b:simple:ensure-method-state', null);\n" .
        "    }\n\n" .
        "    return \$checked;\n",
        'simple_ensure'
    );

    $content = bs6b_replace(
        $content,
        "function buildCheckoutRequestData() {\n" .
        "    syncGuestShippingFields();\n" .
        "    ensureCheckoutMethodSelection('shipping_method', '');\n" .
        "    ensureCheckoutMethodSelection('payment_method', 'hutko');\n",
        "function buildCheckoutRequestData() {\n" .
        "    window.bsSt2b6Log('phase0b:simple:build-request:before', null, null, { selectedPaymentBefore: String(\$('input[name=\"payment_method\"]:checked').first().val() || '') });\n" .
        "    syncGuestShippingFields();\n" .
        "    ensureCheckoutMethodSelection('shipping_method', '');\n" .
        "    ensureCheckoutMethodSelection('payment_method', 'hutko');\n" .
        "    window.bsSt2b6Log('phase0b:simple:build-request:after', null, null, { selectedPaymentAfter: String(\$('input[name=\"payment_method\"]:checked').first().val() || '') });\n",
        'simple_build_request'
    );

    return $content;
}

$original = [];
$patched = [];
$backups = [];

try {
    bs6b_out('patch', BS6B_ID);
    bs6b_out('cwd', __DIR__);
    bs6b_out('time', gmdate('c'));
    bs6b_out('scope', 'Phase 0b diagnostics only');
    bs6b_out('db_schema_changes', 'none');
    bs6b_out('db_data_changes', 'none');

    $markerCount = 0;
    foreach ($targets as $key => $meta) {
        $original[$key] = bs6b_read($meta['path']);
        if (strpos($original[$key], $meta['marker']) !== false) {
            $markerCount++;
        }
    }

    if ($markerCount === count($targets)) {
        bs6b_out('already_applied', 'yes');
        bs6b_out('changed_files', 'none');
        bs6b_out('done', 'ok');
        @unlink(__FILE__);
        exit(0);
    }
    if ($markerCount !== 0) {
        bs6b_fail('partial_phase0b_markers_found:' . $markerCount);
    }

    foreach ($targets as $key => $meta) {
        $actual = hash('sha256', $original[$key]);
        if (!hash_equals($meta['sha256'], $actual)) {
            bs6b_fail("live_sha256_mismatch:$key:expected={$meta['sha256']}:actual=$actual");
        }
    }

    bs6b_count($original['new_checkout'], 'ST-2b.6 Phase 0 tab-restore diagnostics', 1, 'phase0a_required');
    bs6b_count($original['new_payment'], "var bsPaymentPendingChoice = 'hutko';", 1, 'stock_hutko_default');
    bs6b_count($original['new_payment'], "findPaymentOption(options, 'hutko')", 1, 'stock_hutko_fallback');
    bs6b_count($original['simple_checkout'], "ensureCheckoutMethodSelection('payment_method', 'hutko');", 1, 'simple_hutko_default');
    bs6b_count($original['simple_checkout'], "\$methods.filter('[value^=\"hutko\"]')", 1, 'simple_hutko_preferred');
    bs6b_lint_self();

    $patched['new_checkout'] = bs6b_patch_new_checkout($original['new_checkout']);
    $patched['new_payment'] = bs6b_patch_new_payment($original['new_payment']);
    $patched['new_shipping'] = bs6b_patch_new_shipping($original['new_shipping']);
    $patched['simple_checkout'] = bs6b_patch_simple_checkout($original['simple_checkout']);

    foreach ($targets as $key => $meta) {
        bs6b_count($patched[$key], $meta['marker'], 1, $key . '_marker_after');
    }
    bs6b_count($patched['new_payment'], "data: { payment_method: code }", 1, 'payment_save_unchanged');
    bs6b_count($patched['simple_checkout'], "ensureCheckoutMethodSelection('payment_method', 'hutko');", 1, 'simple_default_unchanged');
    bs6b_count($patched['new_checkout'], 'route=checkout/confirm.confirm&language={{ language }}', 1, 'confirm_request_unchanged');

    $backupRoot = __DIR__ . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . BS6B_ID . '-' . date('Ymd-His');
    foreach ($targets as $key => $meta) {
        $backups[$key] = bs6b_backup($meta['path'], $backupRoot);
    }
    foreach ($targets as $key => $meta) {
        bs6b_write($meta['path'], $patched[$key]);
    }
    foreach ($targets as $key => $meta) {
        $written = bs6b_read($meta['path']);
        bs6b_count($written, $meta['marker'], 1, $key . '_written_marker');
    }

    bs6b_out('changed_files', implode(',', array_column($targets, 'path')));
    bs6b_out('target_php_lint', 'not_applicable:twig_only');
    bs6b_out('simplecheckout_instrumentation', 'added');
    bs6b_out('diagnostic_readout', 'window.bsSt2b6ReadDiagnostics()');
    bs6b_out('rollback', 'restore_printed_backups_then_clear_template_cache');
    bs6b_out('done', 'ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    bs6b_out('error', $error->getMessage());
    if ($backups) {
        $restoreOk = true;
        foreach ($backups as $key => $backup) {
            if (!copy($backup, bs6b_path($targets[$key]['path']))) {
                $restoreOk = false;
            }
        }
        bs6b_out('restore_on_fail', $restoreOk ? 'ok' : 'failed');
    } else {
        bs6b_out('restore_on_fail', 'not_needed');
    }
    bs6b_out('done', 'failed');
    exit(1);
}
