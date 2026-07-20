<?php
declare(strict_types=1);

/**
 * RD-13 — checkout reskin corrective patch, round 2.
 *
 * Files changed:
 * - checkout.twig: removes only the temporary ST-2b.6 tracer;
 * - payment_method.twig / shipping_method.twig: removes tracer calls only;
 * - boostershop-ds.css: applies the approved round-2 visual corrections;
 * - checkout-reskin.js: moves real Nova Poshta/captcha/newsletter DOM blocks,
 *   relabels totals/payment methods, and adds the approved RD13-STUB UI.
 *
 * No database, controller, payment/shipping endpoint, price calculation,
 * trusted-click gate, double-submit guard, Hutko, Checkbox, or order-status
 * behavior is changed.
 *
 * Rollback:
 * Restore the five files from the printed _patch_backups directory and clear
 * system/storage/cache plus system/storage/modification.
 */

const RD13R2_PATCH_ID = 'RD-13_checkout-reskin-round2_20260706';
const RD13R2_CHECKOUT = 'catalog/view/template/checkout/checkout.twig';
const RD13R2_PAYMENT = 'catalog/view/template/checkout/payment_method.twig';
const RD13R2_SHIPPING = 'catalog/view/template/checkout/shipping_method.twig';
const RD13R2_CSS = 'catalog/view/stylesheet/boostershop-ds.css';
const RD13R2_JS = 'catalog/view/javascript/checkout-reskin.js';

const RD13R2_CHECKOUT_SHA256 = '2d7e44ae11325bb4ee320e01547290f29ab0a0dd151705060e5f84428fd0a8e6';
const RD13R2_PAYMENT_SHA256 = '3f22be91d64e2a6e797d3a2b64a148f388ec8e194af802a9f6c56ed671144c85';
const RD13R2_SHIPPING_SHA256 = '72335aec061790f42e782449fcf25ff7fada9823e9d9312948091d684216b4f2';
const RD13R2_CSS_SHA256 = '4571f298c7ab9f8edd0d2d05832c536ddb4debca0983ddcddcf53b56be6cbb63';
const RD13R2_JS_SHA256 = '7c0ed145eef38ce60a632794457fdd7d2799e49de438c3893cb44aafe5537a6f';

const RD13R2_CSS_MARKER = 'RD-13 checkout reskin round 2';
const RD13R2_JS_MARKER = 'RD-13 checkout reskin round 2';

function rd13r2_out(string $key, string $value): void {
    echo $key . '=' . $value . PHP_EOL;
}

function rd13r2_fail(string $message): never {
    throw new RuntimeException($message);
}

function rd13r2_path(string $root, string $relative): string {
    return rtrim($root, "/\\") . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function rd13r2_read(string $path, string $relative): string {
    if (!is_file($path)) {
        rd13r2_fail('target_missing:' . $relative);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        rd13r2_fail('read_failed:' . $relative);
    }

    return $content;
}

function rd13r2_replace_once(string $content, string $search, string $replace, string $label): string {
    $count = substr_count($content, $search);

    if ($count !== 1) {
        rd13r2_fail('anchor_count_mismatch:' . $label . ':expected=1:actual=' . $count);
    }

    return str_replace($search, $replace, $content);
}

function rd13r2_preg_once(string $content, string $pattern, string $replace, string $label): string {
    $result = preg_replace($pattern, $replace, $content, 1, $count);

    if ($result === null) {
        rd13r2_fail('regex_error:' . $label);
    }

    if ($count !== 1) {
        rd13r2_fail('anchor_count_mismatch:' . $label . ':expected=1:actual=' . $count);
    }

    return $result;
}

function rd13r2_hash_gate(string $content, string $expected, string $relative): void {
    $actual = hash('sha256', $content);
    rd13r2_out('preflight_sha256', $relative . ':' . $actual);

    if (!hash_equals($expected, $actual)) {
        rd13r2_fail('sha256_mismatch:' . $relative . ':expected=' . $expected . ':actual=' . $actual);
    }
}

function rd13r2_lint_self(): void {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1';
    $output = [];
    $exit = 1;
    exec($command, $output, $exit);
    rd13r2_out('php_lint_patch_self', 'exit=' . $exit . ';output=' . implode(' ', $output));

    if ($exit !== 0) {
        rd13r2_fail('php_lint_patch_self_failed');
    }
}

function rd13r2_atomic_write(string $path, string $content, string $relative): void {
    $written = file_put_contents($path, $content, LOCK_EX);

    if ($written === false || $written !== strlen($content)) {
        rd13r2_fail('write_failed_or_incomplete:' . $relative);
    }
}

function rd13r2_backup(string $root, string $backupRoot, string $relative): string {
    $source = rd13r2_path($root, $relative);
    $backup = rd13r2_path($backupRoot, $relative);
    $directory = dirname($backup);

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        rd13r2_fail('backup_mkdir_failed:' . $relative);
    }

    if (!copy($source, $backup)) {
        rd13r2_fail('backup_copy_failed:' . $relative);
    }

    rd13r2_out('backup', $relative . ' -> ' . $backup);
    return $backup;
}

function rd13r2_restore(array $backups, string $root): void {
    foreach ($backups as $relative => $backup) {
        $target = rd13r2_path($root, $relative);
        if (is_file($backup)) {
            @copy($backup, $target);
        }
    }
}

function rd13r2_remove_checkout_tracer(string $content): string {
    $content = rd13r2_replace_once(
        $content,
        <<<'OLD'
  window.bsCheckoutResetMethodState = function(reason) {
    var bsSt2b6bOldPaymentCode = $('#input-payment-code').val() || '';
    if (window.bsSt2b6Log) {
      window.bsSt2b6Log('phase0b:new:reset-method-state:before', null, null, { reason: reason || '', oldPaymentCode: bsSt2b6bOldPaymentCode, newPaymentCode: '' });
    }
    $('#input-shipping-code, #input-shipping-method, #input-payment-code, #input-payment-method').val('');
    if (window.bsSt2b6Log && bsSt2b6bOldPaymentCode !== '') {
      window.bsSt2b6Log('phase0b:new:payment-code-changed', null, null, { changeSource: 'bsCheckoutResetMethodState', oldPaymentCode: bsSt2b6bOldPaymentCode, newPaymentCode: '', reason: reason || '' });
    }
OLD,
        <<<'NEW'
  window.bsCheckoutResetMethodState = function(reason) {
    $('#input-shipping-code, #input-shipping-method, #input-payment-code, #input-payment-method').val('');
NEW,
        'checkout_reset_tracer'
    );

    $content = rd13r2_preg_once(
        $content,
        '~\n  // ST-2b\.6 Phase 0 tab-restore diagnostics\..*?\n  window\.bsSt2b6Log\(\'checkout:init\', null, null\);\n~s',
        "\n",
        'checkout_main_tracer'
    );

    $content = rd13r2_preg_once(
        $content,
        '~\n  // ST-2b\.6 Phase 0b new-checkout state diagnostics\..*?\n  function bsCheckoutDeferredConfirmHtml\(\) \{~s',
        "\n  function bsCheckoutDeferredConfirmHtml() {",
        'checkout_comparison_tracer'
    );

    $replacements = [
        'checkout_before_render_tracer' => [
            <<<'OLD'

    if (window.bsSt2b6Log) {
      window.bsSt2b6Log('phase0b:new:confirm-summary:before-render', null, null, bsSt2b6bConfirmComparison(shippingLabel, paymentLabel));
    }
OLD,
            ''
        ],
        'checkout_after_render_tracer' => [
            <<<'OLD'
    if (window.bsSt2b6Log) {
      window.bsSt2b6Log('phase0b:new:confirm-summary:after-render', null, null, {
        renderedMethodText: String($('#checkout-confirm .bs-confirm-method-summary').text() || '').replace(/\s+/g, ' ').trim()
      });
    }
OLD,
            ''
        ],
        'checkout_submit_entry_tracer' => [
            <<<'OLD'
    if (window.bsSt2b6Log) {
      window.bsSt2b6Log('bsCheckoutLoadConfirmAndSubmit:entry', event || null, trigger || null);
    }
OLD,
            ''
        ],
        'checkout_before_load_tracer' => [
            <<<'OLD'

      if (window.bsSt2b6Log) {
        window.bsSt2b6Log('checkout.twig:confirm.confirm:before-load', event || null, trigger || null);
      }
OLD,
            ''
        ],
        'checkout_complete_tracer' => [
            <<<'OLD'
        if (window.bsSt2b6Log) {
          window.bsSt2b6Log('checkout.twig:confirm.confirm:complete', event || null, trigger || null, { requestStatus: status || '' });
        }
OLD,
            ''
        ],
        'checkout_fail_open_tracer' => [
            <<<'OLD'

      if (window.bsSt2b6Log) {
        window.bsSt2b6Log('CHECKOUT-001:account-prestep:fail-open', event || null, trigger || null, {
          reason: reason || 'unknown'
        });
      }
OLD,
            ''
        ],
        'checkout_click_tracer' => [
            <<<'OLD'
    if (window.bsSt2b6Log) {
      window.bsSt2b6Log('deferred-confirm:click', event, this);
    }

OLD,
            ''
        ],
        'checkout_rejected_tracer' => [
            <<<'OLD'
      if (window.bsSt2b6Log) {
        window.bsSt2b6Log('phase1:deferred-confirm:rejected', event, this, {
          trustedActivation: trustedActivation,
          triggerIsButton: triggerIsButton,
          targetIsButton: targetIsButton,
          currentTargetIsButton: currentTargetIsButton
        });
      }
OLD,
            ''
        ],
        'checkout_accepted_tracer' => [
            <<<'OLD'

    if (window.bsSt2b6Log) {
      window.bsSt2b6Log('phase1:deferred-confirm:accepted', event, this, { trustedActivation: true });
    }
OLD,
            ''
        ],
    ];

    foreach ($replacements as $label => [$old, $new]) {
        $content = rd13r2_replace_once($content, $old, $new, $label);
    }

    return $content;
}

function rd13r2_remove_payment_tracer(string $content): string {
    $replacements = [
        'payment_tracer_function' => [
            <<<'OLD'
  // ST-2b.6 Phase 0b payment-method diagnostics.
  function bsSt2b6bPaymentLog(source, event, extra) {
    if (window.bsSt2b6Log) {
      window.bsSt2b6Log(source, event || null, event && event.currentTarget ? event.currentTarget : null, extra || {});
    }
  }
OLD,
            ''
        ],
        'payment_waiting_tracer' => [
            <<<'OLD'

    if (!selected && window.bsSt2b6Log) {
      window.bsSt2b6Log('phase1:new:payment-awaiting-explicit-click', null, null, { currentPaymentCode: current || '', optionCodes: $.map(options, function(option) { return option.code || ''; }) });
    }
OLD,
            ''
        ],
        'payment_save_entry_tracer' => [
            <<<'OLD'

    bsSt2b6bPaymentLog('phase0b:new:save-payment:entry', event || null, { oldPaymentCode: $('#input-payment-code').val() || '', newPaymentCode: code || '', isAuto: !!isAuto });
OLD,
            ''
        ],
        'payment_save_success_tracer' => [
            <<<'OLD'
        var bsSt2b6bOldCode = $('#input-payment-code').val() || '';
        $('#input-payment-method').val(label || code);
        $('#input-payment-code').val(code);
        bsSt2b6bPaymentLog('phase0b:new:payment-code-changed', event || null, { changeSource: 'savePayment.success', oldPaymentCode: bsSt2b6bOldCode, newPaymentCode: code || '', isAuto: !!isAuto });
OLD,
            <<<'NEW'
        $('#input-payment-method').val(label || code);
        $('#input-payment-code').val(code);
NEW
        ],
        'payment_preview_change_tracer' => [
            "    bsSt2b6bPaymentLog('phase0b:new:preview-radio-change', event, { oldPendingChoice: bsPaymentPendingChoice || '', newPendingChoice: normalizeChoice(this.value) });\n",
            ''
        ],
        'payment_radio_change_tracer' => [
            "    bsSt2b6bPaymentLog('phase0b:new:payment-radio-change', event, { oldPaymentCode: $('#input-payment-code').val() || '', newPaymentCode: \$input.val() || '' });\n",
            ''
        ],
        'payment_ready_tracer' => [
            "    bsSt2b6bPaymentLog('phase0b:new:payment-module-ready', null, { paymentCode: $('#input-payment-code').val() || '', pendingChoice: bsPaymentPendingChoice || '', shippingCode: $('#input-shipping-code').val() || '' });\n",
            ''
        ],
    ];

    foreach ($replacements as $label => [$old, $new]) {
        $content = rd13r2_replace_once($content, $old, $new, $label);
    }

    return $content;
}

function rd13r2_remove_shipping_tracer(string $content): string {
    return rd13r2_replace_once(
        $content,
        <<<'OLD'
            var bsSt2b6bOldPaymentCode = $('#input-payment-code').val() || '';
            $('#input-shipping-code').val(code);
            $('#input-shipping-method').val(label || code);
            $('#input-payment-method').val('');
            $('#input-payment-code').val('');
            // ST-2b.6 Phase 0b shipping reset diagnostics.
            if (window.bsSt2b6Log) {
              window.bsSt2b6Log('phase0b:new:payment-code-changed', null, null, { changeSource: 'shipping_method.save.success', oldPaymentCode: bsSt2b6bOldPaymentCode, newPaymentCode: '', shippingCode: code || '' });
            }
OLD,
        <<<'NEW'
            $('#input-shipping-code').val(code);
            $('#input-shipping-method').val(label || code);
            $('#input-payment-method').val('');
            $('#input-payment-code').val('');
NEW,
        'shipping_reset_tracer'
    );
}

$cssAppend = <<<'CSS'

/* RD-13 checkout reskin round 2 */
#checkout-checkout.bs-co #checkout-confirm .bs-confirm-deferred-summary,
#checkout-checkout.bs-co #checkout-confirm .bs-confirm-method-summary {
  display: none !important;
}

#checkout-checkout.bs-co #checkout-confirm tbody {
  display: block;
  max-height: 268px;
  overflow-y: auto;
}

#checkout-checkout.bs-co #checkout-confirm tbody tr {
  display: table;
  width: 100%;
  table-layout: fixed;
}

#checkout-checkout.bs-co #checkout-confirm tbody a {
  color: var(--bs-ink);
  font-weight: 600;
  text-decoration: none;
}

#checkout-checkout.bs-co #checkout-confirm tbody a:hover {
  color: var(--bs-blue);
  text-decoration: underline;
}

#checkout-checkout.bs-co .bs-recipient-toggle {
  background: var(--bs-bg);
  border: 1px solid var(--bs-line);
  border-radius: var(--bs-r-sm);
  padding: 13px 14px;
  margin: 4px 0 2px;
}

#checkout-checkout.bs-co .bs-recipient-toggle small,
#checkout-checkout.bs-co .bs-recipient-toggle p {
  color: var(--bs-ink-3);
  font-size: 12px;
  margin: 4px 0 0;
}

#checkout-checkout.bs-co .bs-co-payment-sub {
  display: block;
  font-size: 12px;
  font-weight: 400;
  color: var(--bs-ink-3);
  margin-top: 2px;
}

#checkout-checkout.bs-co #form-register > fieldset:first-of-type > .row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 14px 16px;
}

#checkout-checkout.bs-co #form-register > fieldset:first-of-type > .row > .col {
  width: auto;
  margin: 0 !important;
  padding: 0;
}

#checkout-checkout.bs-co .bs-co-recipient-field--first { order: 1; }
#checkout-checkout.bs-co .bs-co-recipient-field--last { order: 2; }
#checkout-checkout.bs-co .bs-co-recipient-field--middle { order: 3; }
#checkout-checkout.bs-co .bs-co-recipient-field--phone { order: 4; }
#checkout-checkout.bs-co .bs-co-recipient-field--email {
  order: 5;
  grid-column: 1 / -1;
}

#checkout-checkout.bs-co [data-co-card="delivery"] #shipping-novaposhta,
#checkout-checkout.bs-co [data-co-card="delivery"] .bs-np-address-panel {
  margin-top: 14px;
}

#checkout-checkout.bs-co [data-co-card="delivery"] .bs-register-autosave-status {
  margin: 8px 0 0;
}

#checkout-checkout.bs-co [data-co-card="delivery"] .bs-co-moved-captcha,
#checkout-checkout.bs-co [data-co-card="delivery"] .bs-co-moved-newsletter {
  margin-top: 14px;
}

#checkout-checkout.bs-co .bs-co-promo-input {
  display: flex;
  gap: 8px;
}

#checkout-checkout.bs-co .bs-co-promo-input .bs-input {
  min-width: 0;
  flex: 1 1 auto;
}

#checkout-checkout.bs-co .bs-co-field-hint {
  margin-top: 6px;
  font-size: 12px;
  color: var(--bs-ink-3);
}

/* RD13-STUB — remove with renderFreeShippingStub when backend config ships. */
#checkout-checkout.bs-co .bs-co-shipblock {
  padding: 12px 14px;
  border-radius: var(--bs-r-sm);
  background: var(--bs-blue-soft);
  margin: 6px 0;
}

#checkout-checkout.bs-co .bs-co-shipblock.is-free { background: #eaf7ee; }

#checkout-checkout.bs-co .bs-co-shipblock__row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 9px;
}

#checkout-checkout.bs-co .bs-co-shipblock__row span:first-child {
  font-size: 13px;
  font-weight: 600;
  color: var(--bs-ink-2);
}

#checkout-checkout.bs-co .bs-co-shipblock__price {
  font-size: 14.5px;
  font-weight: 800;
  color: var(--bs-ink);
}

#checkout-checkout.bs-co .bs-co-shipblock.is-free .bs-co-shipblock__price { color: var(--bs-green); }

#checkout-checkout.bs-co .bs-co-shipblock__msg {
  font-size: 12px;
  font-weight: 600;
  color: var(--bs-blue);
  margin-bottom: 8px;
}

#checkout-checkout.bs-co .bs-co-shipblock.is-free .bs-co-shipblock__msg { color: var(--bs-green); }

#checkout-checkout.bs-co .bs-co-shipblock__track {
  height: 5px;
  background: #fff;
  border-radius: 999px;
  overflow: hidden;
}

#checkout-checkout.bs-co .bs-co-shipblock__track i {
  display: block;
  height: 100%;
  background: var(--bs-blue);
  border-radius: 999px;
}

#checkout-checkout.bs-co .bs-co-shipblock.is-free .bs-co-shipblock__track i { background: var(--bs-green); }

@media (max-width: 900px) {
  #checkout-checkout.bs-co .bs-co-grid { grid-template-columns: 1fr; }
  #checkout-checkout.bs-co .bs-co-col { order: 2; }
  #checkout-checkout.bs-co .bs-co-aside {
    order: 1;
    position: static;
  }

  #checkout-checkout.bs-co #form-register > fieldset:first-of-type > .row {
    grid-template-columns: 1fr;
  }

  #checkout-checkout.bs-co .bs-co-recipient-field--email {
    grid-column: auto;
  }
}
CSS;

$newJs = <<<'JS'
/* RD-13 checkout reskin round 2
 * Presentation helpers only. No endpoint, payload, or order-creation changes.
 */
(function ($) {
  'use strict';

  var root = document.querySelector('[data-rd13-checkout]');
  var observerTimer = 0;
  var syncing = false;

  if (!root || !$) {
    return;
  }

  function text(value) {
    return String(value || '').replace(/\s+/g, ' ').trim();
  }

  function setText(node, value) {
    if (node && node.textContent !== value) {
      node.textContent = value;
    }
  }

  function fieldBlock(input) {
    return input ? input.closest('.col, .mb-2, .mb-3, .form-group, .form-check, fieldset') : null;
  }

  function bindControlsToRegister(container) {
    if (!container) {
      return;
    }

    container.querySelectorAll('input, select, textarea, button').forEach(function (control) {
      control.setAttribute('form', 'form-register');
    });
  }

  function markRecipientField(input, className) {
    var block = fieldBlock(input);

    if (block) {
      block.classList.add(className);
    }

    return block;
  }

  function moveDeliveryFields() {
    var deliveryBody = root.querySelector('[data-co-card="delivery"] .bs-co-card__body');
    var registerForm = document.getElementById('form-register');

    if (!deliveryBody) {
      return;
    }

    var shippingMethod = document.getElementById('checkout-shipping-method');
    if (shippingMethod && deliveryBody.firstElementChild !== shippingMethod) {
      deliveryBody.insertBefore(shippingMethod, deliveryBody.firstElementChild);
    }

    if (!registerForm) {
      return;
    }

    var receiverGrid = registerForm.querySelector('fieldset:first-of-type > .row');
    var npPanel = document.getElementById('shipping-novaposhta');

    if (receiverGrid && npPanel) {
      [
        ['#input-shipping-novaposhta-firstname', 'bs-co-recipient-field--first'],
        ['#input-shipping-novaposhta-lastname', 'bs-co-recipient-field--last'],
        ['#input-shipping-novaposhta-middlename', 'bs-co-recipient-field--middle']
      ].forEach(function (entry) {
        var block = markRecipientField(document.querySelector(entry[0]), entry[1]);
        if (block && block.parentNode !== receiverGrid) {
          receiverGrid.appendChild(block);
        }
      });

      var phoneBlock = markRecipientField(document.getElementById('input-telephone'), 'bs-co-recipient-field--phone');
      var emailBlock = markRecipientField(document.getElementById('input-email'), 'bs-co-recipient-field--email');

      if (phoneBlock && phoneBlock.parentNode === receiverGrid) {
        receiverGrid.appendChild(phoneBlock);
      }

      if (emailBlock && emailBlock.parentNode === receiverGrid) {
        receiverGrid.appendChild(emailBlock);
      }

      if (npPanel.parentNode !== deliveryBody) {
        deliveryBody.appendChild(npPanel);
      }
      bindControlsToRegister(npPanel);
    }

    var registerStatus = registerForm.querySelector('[data-bs-register-status]');
    if (registerStatus && registerStatus.parentNode !== deliveryBody) {
      deliveryBody.appendChild(registerStatus);
    }

    var recaptcha = registerForm.querySelector('[id^="g-recaptcha-"], .g-recaptcha');
    var captchaBlock = recaptcha ? recaptcha.closest('fieldset, .mb-2, .mb-3, .form-group') : null;
    if (captchaBlock && captchaBlock.parentNode !== deliveryBody) {
      captchaBlock.classList.add('bs-co-moved-captcha');
      deliveryBody.appendChild(captchaBlock);
      bindControlsToRegister(captchaBlock);
    }

    var newsletter = document.getElementById('input-newsletter');
    var newsletterBlock = newsletter ? newsletter.closest('.form-check, .mb-2, .mb-3, .form-group') : null;
    if (newsletterBlock && newsletterBlock.parentNode !== deliveryBody) {
      newsletterBlock.classList.add('bs-co-moved-newsletter');
      deliveryBody.appendChild(newsletterBlock);
      bindControlsToRegister(newsletterBlock);
    }
  }

  function ensurePromoStub() {
    var tail = document.getElementById('bs-co-summary-tail');

    if (!tail || tail.querySelector('[data-rd13-promo-stub]')) {
      return;
    }

    var promo = document.createElement('div');
    promo.className = 'bs-field';
    promo.setAttribute('data-rd13-promo-stub', '1');
    // RD13-STUB: replace with real coupon wiring once the endpoint ships.
    promo.innerHTML =
      '<label>Промокод</label>' +
      '<div class="bs-co-promo-input">' +
        '<input class="bs-input" name="rd13_stub_coupon" placeholder="Введіть промокод">' +
        '<button type="button" class="bs-btn bs-btn-secondary" data-co-promo-stub>Застосувати</button>' +
      '</div>' +
      '<div class="bs-co-field-hint" data-co-promo-stub-msg hidden>Промокоди зʼявляться незабаром</div>';
    tail.insertBefore(promo, tail.firstChild);

    var button = promo.querySelector('[data-co-promo-stub]');
    button.addEventListener('click', function () {
      var message = promo.querySelector('[data-co-promo-stub-msg]');
      if (message) {
        message.hidden = false;
      }
    });
  }

  function moveSummaryFields() {
    var tail = document.getElementById('bs-co-summary-tail');

    if (!tail) {
      return;
    }

    [
      document.getElementById('input-comment'),
      document.getElementById('input-checkout-agree'),
      document.getElementById('input-create-account-opt-in')
    ].forEach(function (input) {
      if (!input) {
        return;
      }

      var block = input.closest('.mb-2, .form-check');

      if (block && block.parentNode !== tail) {
        tail.appendChild(block);
      }
    });
  }

  function styleControls() {
    root.querySelectorAll('.bs-checkout-method-option, .bs-checkout-panel-choice .form-check').forEach(function (row) {
      var checked = row.querySelector('input[type="radio"]:checked, input[type="checkbox"]:checked');
      row.classList.toggle('is-active', !!checked);
    });
  }

  function relabelTotals() {
    root.querySelectorAll('#checkout-confirm tfoot tr').forEach(function (row) {
      var label = row.querySelector('td:first-child');
      if (!label) {
        return;
      }

      var current = text(label.textContent);
      if (/^Сума$/i.test(current)) {
        setText(label, 'Сума товарів');
      }
      if (/^Разом$/i.test(current)) {
        setText(label, 'До сплати');
      }
    });
  }

  var PAYMENT_LABEL_MAP = [
    {
      match: /^(Оплата карткою через Hutko|Оплата карткою,\s*Google\s*\/\s*Apple Pay)$/i,
      title: 'Картка, Google Pay / Apple Pay',
      sub: 'Безпечно через еквайринг',
      rank: 1
    },
    {
      match: /^Оплата (при доставці|при отриманні)\s*\((накладений платіж|післяплата)\)$/i,
      title: 'Оплата при отриманні (накладений платіж)',
      sub: '',
      rank: 2
    },
    {
      match: /^(Банківський переказ|Оплата за реквізитами)$/i,
      title: 'За реквізитами на IBAN',
      sub: '',
      rank: 3
    }
  ];

  function relabelPaymentMethods() {
    var list = document.getElementById('bs-payment-methods');

    if (!list) {
      return;
    }

    var rows = Array.prototype.slice.call(list.querySelectorAll('.bs-checkout-method-option'));
    rows.forEach(function (row) {
      var label = row.querySelector(':scope > span, .form-check-label');
      if (!label || label.dataset.coRelabelled === '1') {
        return;
      }

      var current = text(label.textContent);
      var rule = PAYMENT_LABEL_MAP.find(function (candidate) {
        return candidate.match.test(current);
      });

      if (!rule) {
        row.dataset.coPaymentRank = '99';
        return;
      }

      label.dataset.coRelabelled = '1';
      row.dataset.coPaymentRank = String(rule.rank);
      setText(label, rule.title);

      if (rule.sub) {
        var subNode = document.createElement('span');
        subNode.className = 'bs-co-payment-sub';
        subNode.textContent = rule.sub;
        label.appendChild(subNode);
      }
    });

    var sorted = rows.slice().sort(function (a, b) {
      return Number(a.dataset.coPaymentRank || 99) - Number(b.dataset.coPaymentRank || 99);
    });

    sorted.forEach(function (row, index) {
      if (list.children[index] !== row) {
        list.appendChild(row);
      }
    });
  }

  function parseMoney(value) {
    var normalized = text(value).replace(/[^\d,.-]/g, '');
    var comma = normalized.lastIndexOf(',');
    var dot = normalized.lastIndexOf('.');

    if (comma > dot) {
      normalized = normalized.replace(/\./g, '').replace(',', '.');
    } else if (dot > comma) {
      normalized = normalized.replace(/,/g, '');
    } else {
      normalized = normalized.replace(',', '.');
    }

    var amount = Number(normalized);
    return Number.isFinite(amount) ? amount : 0;
  }

  function formatHryvnia(value) {
    return new Intl.NumberFormat('uk-UA', {
      maximumFractionDigits: 0
    }).format(Math.ceil(value));
  }

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function (character) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      }[character];
    });
  }

  function renderFreeShippingStub() {
    var FREE_SHIP_THRESHOLD = 2000; // RD13-STUB: replace with real config.
    var rows = root.querySelectorAll('#checkout-confirm tfoot tr');

    if (rows.length !== 3) {
      return;
    }

    var subtotalRow = rows[0];
    var shippingRow = rows[1];

    if (shippingRow.dataset.rd13Stub === '1') {
      return;
    }

    var subtotalCells = subtotalRow.querySelectorAll('td');
    var shipCells = shippingRow.querySelectorAll('td');

    if (!subtotalCells.length || !shipCells.length) {
      return;
    }

    var subtotal = parseMoney(subtotalCells[subtotalCells.length - 1].textContent);
    var remaining = Math.max(0, FREE_SHIP_THRESHOLD - subtotal);
    var percentage = Math.min(100, Math.round((subtotal / FREE_SHIP_THRESHOLD) * 100));
    var done = remaining <= 0;
    var shippingLabel = text(shipCells[0].textContent);
    var shippingValue = text(shipCells[shipCells.length - 1].textContent);
    var cell = document.createElement('td');
    var wrap = document.createElement('div');

    wrap.className = 'bs-co-shipblock rd13-stub' + (done ? ' is-free' : '');
    wrap.innerHTML =
      '<div class="bs-co-shipblock__row"><span>' + escapeHtml(shippingLabel) + '</span>' +
      '<span class="bs-co-shipblock__price">' + escapeHtml(done ? 'Безкоштовно' : shippingValue) + '</span></div>' +
      '<div class="bs-co-shipblock__msg">' + (done
        ? 'Безкоштовна доставка застосована ✓'
        : 'До безкоштовної доставки лишилось ₴' + formatHryvnia(remaining)) + '</div>' +
      '<div class="bs-co-shipblock__track"><i style="width:' + percentage + '%"></i></div>';

    cell.colSpan = shipCells.length;
    shippingRow.innerHTML = '';
    shippingRow.appendChild(cell);
    cell.appendChild(wrap);
    shippingRow.dataset.rd13Stub = '1';
  }

  function updateSummaryMeta() {
    var quantity = 0;
    var totalNode = document.querySelector('[data-co-summary-total]');
    var qtyNode = document.querySelector('[data-co-summary-qty]');
    var rows = root.querySelectorAll('#checkout-confirm tbody tr');
    var grand = root.querySelector('#checkout-confirm tfoot tr:last-child td:last-child');

    rows.forEach(function (row) {
      var cells = row.querySelectorAll('td');
      var candidate = cells.length > 2 ? text(cells[cells.length - 2].textContent) : '';
      var match = candidate.match(/\d+/);
      quantity += match ? parseInt(match[0], 10) : 1;
    });

    setText(qtyNode, quantity ? '· ' + quantity + (quantity === 1 ? ' товар' : ' товари') : '');
    setText(totalNode, grand ? text(grand.textContent) : '');
  }

  function updateCardSummaries() {
    var receiver = document.querySelector('[data-co-receiver-summary]');
    var delivery = document.querySelector('[data-co-delivery-summary]');
    var first = document.getElementById('input-shipping-novaposhta-firstname') || document.getElementById('input-firstname');
    var last = document.getElementById('input-shipping-novaposhta-lastname') || document.getElementById('input-lastname');
    var phone = document.getElementById('input-telephone');
    var shipping = document.getElementById('input-shipping-method');
    var receiverText = [first && first.value, last && last.value].map(text).filter(Boolean).join(' ');
    var phoneText = phone ? text(phone.value) : '';
    var deliveryText = shipping ? text(shipping.value) : '';

    setText(receiver, [receiverText, phoneText].filter(Boolean).join(' · '));
    setText(delivery, deliveryText);

    var receiverCard = receiver && receiver.closest('[data-co-card]');
    var deliveryCard = delivery && delivery.closest('[data-co-card]');

    if (receiverCard) {
      receiverCard.dataset.hasData = receiverText && phoneText ? '1' : '0';
    }

    if (deliveryCard) {
      deliveryCard.dataset.hasData = deliveryText ? '1' : '0';
    }
  }

  function initToggles() {
    root.querySelectorAll('[data-co-card-toggle]').forEach(function (button) {
      if (button.dataset.coBound === '1') {
        return;
      }

      button.dataset.coBound = '1';
      button.addEventListener('click', function () {
        if (!window.matchMedia('(max-width: 900px)').matches) {
          return;
        }

        var card = button.closest('[data-co-card]');
        var collapsed = !card.classList.contains('is-collapsed');
        card.classList.toggle('is-collapsed', collapsed);
        button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      });
    });

    var summaryToggle = root.querySelector('[data-co-summary-toggle]');

    if (summaryToggle && summaryToggle.dataset.coBound !== '1') {
      summaryToggle.dataset.coBound = '1';
      summaryToggle.addEventListener('click', function () {
        if (!window.matchMedia('(max-width: 900px)').matches) {
          return;
        }

        var card = summaryToggle.closest('.bs-co-summary');
        var open = !card.classList.contains('is-open');
        card.classList.toggle('is-open', open);
        summaryToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }
  }

  function applyInitialMobileCollapse() {
    if (!window.matchMedia('(max-width: 900px)').matches) {
      return;
    }

    root.querySelectorAll('[data-co-collapsible]').forEach(function (card) {
      if (card.dataset.coInitialised === '1') {
        return;
      }

      card.dataset.coInitialised = '1';

      if (card.dataset.hasData === '1') {
        card.classList.add('is-collapsed');
        var button = card.querySelector('[data-co-card-toggle]');
        if (button) {
          button.setAttribute('aria-expanded', 'false');
        }
      }
    });
  }

  function sync() {
    if (syncing) {
      return;
    }

    syncing = true;
    moveDeliveryFields();
    ensurePromoStub();
    moveSummaryFields();
    styleControls();
    relabelPaymentMethods();
    relabelTotals();
    renderFreeShippingStub();
    updateSummaryMeta();
    updateCardSummaries();
    initToggles();
    applyInitialMobileCollapse();
    syncing = false;
  }

  function scheduleSync() {
    window.clearTimeout(observerTimer);
    observerTimer = window.setTimeout(sync, 40);
  }

  $(root).on('change input', 'input, select, textarea', scheduleSync);
  $(sync);

  if (window.MutationObserver) {
    new MutationObserver(scheduleSync).observe(root, {
      childList: true,
      subtree: true
    });
  }
})(window.jQuery);
JS;

$root = __DIR__;
$dryRun = in_array('--dry-run', $argv ?? [], true);
$targets = [
    RD13R2_CHECKOUT => RD13R2_CHECKOUT_SHA256,
    RD13R2_PAYMENT => RD13R2_PAYMENT_SHA256,
    RD13R2_SHIPPING => RD13R2_SHIPPING_SHA256,
    RD13R2_CSS => RD13R2_CSS_SHA256,
    RD13R2_JS => RD13R2_JS_SHA256,
];
$contents = [];
$backups = [];

try {
    rd13r2_lint_self();

    foreach ($targets as $relative => $expectedHash) {
        $contents[$relative] = rd13r2_read(rd13r2_path($root, $relative), $relative);
    }

    $alreadyApplied =
        str_contains($contents[RD13R2_CSS], RD13R2_CSS_MARKER) &&
        str_contains($contents[RD13R2_JS], RD13R2_JS_MARKER) &&
        !str_contains($contents[RD13R2_CHECKOUT], 'bsSt2b6Log') &&
        !str_contains($contents[RD13R2_PAYMENT], 'bsSt2b6Log') &&
        !str_contains($contents[RD13R2_SHIPPING], 'bsSt2b6Log');

    if ($alreadyApplied) {
        rd13r2_out('already_applied', 'yes');
        rd13r2_out('done', 'ok');
        if (!$dryRun) {
            @unlink(__FILE__);
        }
        exit(0);
    }

    foreach ($targets as $relative => $expectedHash) {
        rd13r2_hash_gate($contents[$relative], $expectedHash, $relative);
    }

    $patched = $contents;
    $patched[RD13R2_CHECKOUT] = rd13r2_remove_checkout_tracer($contents[RD13R2_CHECKOUT]);
    $patched[RD13R2_PAYMENT] = rd13r2_remove_payment_tracer($contents[RD13R2_PAYMENT]);
    $patched[RD13R2_SHIPPING] = rd13r2_remove_shipping_tracer($contents[RD13R2_SHIPPING]);

    if (str_contains($contents[RD13R2_CSS], RD13R2_CSS_MARKER)) {
        rd13r2_fail('marker_unexpected_before_patch:' . RD13R2_CSS);
    }
    $patched[RD13R2_CSS] = rtrim($contents[RD13R2_CSS]) . "\n" . $cssAppend . "\n";
    $patched[RD13R2_JS] = $newJs . "\n";

    foreach ([RD13R2_CHECKOUT, RD13R2_PAYMENT, RD13R2_SHIPPING] as $relative) {
        foreach (['bsSt2b6Log', '[ST-2b.6 diagnostic]', 'bsSt2b6b'] as $forbidden) {
            if (str_contains($patched[$relative], $forbidden)) {
                rd13r2_fail('postcheck_tracer_remaining:' . $relative . ':' . $forbidden);
            }
        }
    }

    $checks = [
        'trusted_click_gate_preserved' => str_contains($patched[RD13R2_CHECKOUT], 'ST-2b.6d: trusted deferred-confirm activation gate.'),
        'double_submit_guard_preserved' => str_contains($patched[RD13R2_CHECKOUT], 'if (bsCheckoutConfirmSubmitting)'),
        'duplicate_recap_hidden' => str_contains($patched[RD13R2_CSS], '#checkout-checkout.bs-co #checkout-confirm .bs-confirm-deferred-summary'),
        'mobile_summary_order' => str_contains($patched[RD13R2_CSS], '#checkout-checkout.bs-co .bs-co-aside'),
        'product_link_restyled' => str_contains($patched[RD13R2_CSS], '#checkout-checkout.bs-co #checkout-confirm tbody a'),
        'item_scroll_cap' => str_contains($patched[RD13R2_CSS], 'max-height: 268px'),
        'real_np_selectors' => str_contains($patched[RD13R2_JS], '#input-shipping-novaposhta-firstname'),
        'totals_relabel' => str_contains($patched[RD13R2_JS], "setText(label, 'До сплати')"),
        'payment_copy' => str_contains($patched[RD13R2_JS], 'Картка, Google Pay / Apple Pay'),
        'free_shipping_stub_tagged' => str_contains($patched[RD13R2_JS], 'FREE_SHIP_THRESHOLD = 2000; // RD13-STUB'),
        'promo_stub_no_endpoint' => str_contains($patched[RD13R2_JS], 'name="rd13_stub_coupon"') &&
            !str_contains($patched[RD13R2_JS], 'coupon.coupon'),
    ];

    foreach ($checks as $name => $passed) {
        rd13r2_out('check_' . $name, $passed ? 'ok' : 'failed');
        if (!$passed) {
            rd13r2_fail('postcheck_failed:' . $name);
        }
    }

    if ($dryRun) {
        rd13r2_out('already_applied', 'no');
        rd13r2_out('write', 'skipped_dry_run');
        rd13r2_out('done', 'dry-run');
        exit(0);
    }

    $timestamp = date('Ymd-His');
    $backupRoot = rd13r2_path($root, '_patch_backups/' . RD13R2_PATCH_ID . '-' . $timestamp);
    foreach (array_keys($targets) as $relative) {
        $backups[$relative] = rd13r2_backup($root, $backupRoot, $relative);
    }

    foreach ($patched as $relative => $content) {
        rd13r2_atomic_write(rd13r2_path($root, $relative), $content, $relative);
        rd13r2_out('write', $relative . ':ok');
    }

    foreach ($patched as $relative => $expectedContent) {
        $actualContent = rd13r2_read(rd13r2_path($root, $relative), $relative);
        if (!hash_equals(hash('sha256', $expectedContent), hash('sha256', $actualContent))) {
            rd13r2_fail('post_write_hash_mismatch:' . $relative);
        }
    }

    rd13r2_out('php_lint_targets', 'not_applicable;twig_css_js_only');
    rd13r2_out('backup_root', $backupRoot);
    rd13r2_out('already_applied', 'no');
    rd13r2_out('done', 'ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    if ($backups) {
        rd13r2_restore($backups, $root);
        rd13r2_out('rollback', 'restored');
    }

    rd13r2_out('error', $error->getMessage());
    rd13r2_out('done', 'failed');
    exit(1);
}
