<?php
/**
 * ST-2c.3 — unified checkout state coordinator.
 *
 * Scope:
 * - one address -> shipping -> payment -> totals coordinator;
 * - revision-gated async responses;
 * - customer-only register saves no longer invalidate delivery/payment;
 * - totals use the existing read-only checkout/confirm index route;
 * - payment methods are reduced server-side to Hutko / preferred COD / bank;
 * - removes ST-2c.1/ST-2c.2 totals refresh layering.
 *
 * No database changes.
 * Rollback: restore every file from the printed _patch_backups directory and
 * remove catalog/view/javascript/checkout-state.js.
 */

declare(strict_types=1);

$patch = basename(__FILE__, '.php');
$root = __DIR__;
$marker = 'ST-2c.3 — unified checkout state coordinator';
$stateRelative = 'catalog/view/javascript/checkout-state.js';
$expectedHashes = [
    'catalog/controller/checkout/register.php' => 'A2BC3C6DE11C5A5F392F2FAE69C8D3DD2CDA0F6342E381C94E75C7337FDE6FF6',
    'catalog/controller/checkout/payment_method.php' => 'F752FF0CE1A82677DAB6C06C418BF3C6473CF32EF5E1F30157FF5CAC890A93ED',
    'catalog/view/template/checkout/checkout.twig' => '38978E7BF8497BE6357BFD53DC7D07402B8E11BD17E514943A7F5A5950C6EE24',
    'catalog/view/template/checkout/shipping_method.twig' => 'CECC37EE98DDA44B8C465700A3EFD4DA6B78C95C0491CB88ED692BE465748FB0',
    'catalog/view/template/checkout/payment_method.twig' => '72197C41B1EF8A397DDCE9B139FBBCCBC30D15D19B21ECDFB067D878D928F9AB',
    'catalog/view/javascript/checkout-reskin.js' => '5FAD01A50037B1F1687577C9FBF3910BC09B916CEDE3336F5277A19E1516E46A',
];

function st2c3_fail(string $message): void {
    fwrite(STDERR, 'error=' . $message . PHP_EOL);
    exit(1);
}

function st2c3_path(string $root, string $relative): string {
    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function st2c3_replace_once(string $source, string $old, string $new, string $label): string {
    $count = substr_count($source, $old);

    if ($count !== 1) {
        st2c3_fail('anchor=' . $label . '; count=' . $count . '; expected=1');
    }

    return str_replace($old, $new, $source, $replaced);
}

$statePath = st2c3_path($root, $stateRelative);

if (is_file($statePath) && strpos((string)file_get_contents($statePath), $marker) !== false) {
    $installedChecks = [
        'catalog/controller/checkout/register.php' => 'private function boosterCheckoutAddressFingerprint',
        'catalog/controller/checkout/payment_method.php' => "['booster_category'] = \$category",
        'catalog/view/template/checkout/checkout.twig' => 'checkout-state.js?v=st2c3-20260719',
        'catalog/view/template/checkout/shipping_method.twig' => 'window.bsCheckoutState.beginShippingSelection',
        'catalog/view/template/checkout/payment_method.twig' => 'option.booster_category',
        'catalog/view/javascript/checkout-reskin.js' => "window.bsCheckoutState.totalsChanged('coupon')",
    ];

    foreach ($installedChecks as $relative => $needle) {
        $installedPath = st2c3_path($root, $relative);
        $installedContent = is_file($installedPath) ? file_get_contents($installedPath) : false;

        if ($installedContent === false || strpos($installedContent, $needle) === false) {
            st2c3_fail('partial_apply_detected:' . $relative);
        }
    }

    echo 'already_applied=yes' . PHP_EOL;
    exit(0);
}

$files = [];

foreach ($expectedHashes as $relative => $expectedHash) {
    $path = st2c3_path($root, $relative);

    if (!is_file($path)) {
        st2c3_fail('target_missing:' . $relative);
    }

    $actualHash = strtoupper((string)hash_file('sha256', $path));

    if ($actualHash !== $expectedHash) {
        st2c3_fail('sha256_mismatch:' . $relative . '; actual=' . $actualHash);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        st2c3_fail('target_read_failed:' . $relative);
    }

    $files[$relative] = $content;
}

if (is_file($statePath)) {
    st2c3_fail('new_target_already_exists:' . $stateRelative);
}

$stateJs = <<<'JS'
/* ST-2c.3 — unified checkout state coordinator.
 * Owns the address -> shipping -> payment -> totals sequence and ignores stale
 * async responses by revision. Totals come only from read-only checkout/confirm.
 */
(function ($) {
  'use strict';

  var root = document.getElementById('checkout-checkout');
  if (!root || window.bsCheckoutState) {
    return;
  }

  var revision = 0;
  var totalsRequest = null;
  var totalsDirty = true;
  var bootstrapped = false;

  function currentRevision() {
    return revision;
  }

  function isCurrent(candidate) {
    return Number(candidate) === revision;
  }

  function abortTotals() {
    if (totalsRequest && typeof totalsRequest.abort === 'function') {
      totalsRequest.abort();
    }
    totalsRequest = null;
  }

  function renderConfirmState() {
    if (typeof window.bsCheckoutRefreshConfirmIfPaymentReady === 'function') {
      window.bsCheckoutRefreshConfirmIfPaymentReady();
    }
  }

  function renderPaymentPreview() {
    if (typeof window.bsCheckoutRenderPaymentPreview === 'function') {
      window.bsCheckoutRenderPaymentPreview();
    } else {
      $('#bs-payment-methods').empty();
      $('[data-bs-payment-status]').text('Спосіб оплати можна обрати після завантаження блоку.');
    }
  }

  function clearPaymentState() {
    $('#input-payment-code, #input-payment-method').val('');
    renderPaymentPreview();
    renderConfirmState();
  }

  function beginAddressTransition() {
    revision += 1;
    totalsDirty = true;
    abortTotals();
    $('#input-shipping-code, #input-shipping-method').val('');
    clearPaymentState();
    return revision;
  }

  function beginShippingSelection() {
    revision += 1;
    totalsDirty = true;
    abortTotals();
    clearPaymentState();
    return revision;
  }

  function extractSummary(html) {
    var shell = $('<div>').html(html || '');
    var summary = shell.children('.table-responsive').first();

    if (!summary.length) {
      summary = shell.find('.table-responsive').first();
    }

    return summary.length ? $('<div>').append(summary.clone()).html() : '';
  }

  function refreshTotals(candidateRevision) {
    var requestRevision = candidateRevision === undefined ? revision : Number(candidateRevision);
    var shippingCode = String($('#input-shipping-code').val() || '');

    if (!isCurrent(requestRevision) || !shippingCode) {
      totalsDirty = true;
      return null;
    }

    abortTotals();
    totalsDirty = false;

    totalsRequest = $.ajax({
      url: 'index.php?route=checkout/confirm&language=' + encodeURIComponent(root.getAttribute('data-bs-language') || 'uk-ua'),
      type: 'get',
      dataType: 'html',
      cache: false,
      success: function (html) {
        if (!isCurrent(requestRevision) || shippingCode !== String($('#input-shipping-code').val() || '')) {
          return;
        }

        var summaryHtml = extractSummary(html);
        if (summaryHtml && typeof window.bsCheckoutUpdateCachedSummaryHtml === 'function') {
          window.bsCheckoutUpdateCachedSummaryHtml(summaryHtml);
        }
      },
      error: function (xhr, status) {
        if (status !== 'abort' && isCurrent(requestRevision)) {
          totalsDirty = true;
          $('[data-bs-shipping-status]').text('Не вдалося оновити підсумок. Повторіть вибір доставки.');
        }
      },
      complete: function () {
        if (isCurrent(requestRevision)) {
          totalsRequest = null;
        }
      }
    });

    return totalsRequest;
  }

  function addressSaved(options) {
    options = options || {};
    var token = beginAddressTransition();

    if (typeof window.bsCheckoutLoadShippingMethods === 'function') {
      return window.bsCheckoutLoadShippingMethods({
        autoSelect: true,
        quietAddressError: !!options.quietAddressError,
        stateRevision: token
      });
    }

    return null;
  }

  function shippingSaved(code, label, candidateRevision) {
    var token = Number(candidateRevision);
    if (!isCurrent(token)) {
      return false;
    }

    $('#input-shipping-code').val(code || '');
    $('#input-shipping-method').val(label || code || '');
    totalsDirty = true;
    refreshTotals(token);

    if (typeof window.bsCheckoutLoadPaymentMethods === 'function') {
      window.bsCheckoutLoadPaymentMethods({ stateRevision: token });
    }

    renderConfirmState();
    return true;
  }

  function paymentContextSaved() {
    revision += 1;
    var token = revision;
    totalsDirty = true;
    abortTotals();
    clearPaymentState();

    if ($('#input-shipping-code').val()) {
      refreshTotals(token);
      if (typeof window.bsCheckoutLoadPaymentMethods === 'function') {
        window.bsCheckoutLoadPaymentMethods({ stateRevision: token });
      }
    }

    return token;
  }

  function customerSaved() {
    renderConfirmState();
  }

  function paymentSaved(code, label, candidateRevision) {
    if (!isCurrent(candidateRevision)) {
      return false;
    }

    $('#input-payment-code').val(code || '');
    $('#input-payment-method').val(label || code || '');
    renderConfirmState();
    return true;
  }

  function paymentMethodsRendered(candidateRevision) {
    if (!isCurrent(candidateRevision)) {
      return false;
    }

    renderConfirmState();
    return true;
  }

  function totalsChanged() {
    totalsDirty = true;
    if ($('#input-shipping-code').val()) {
      refreshTotals(revision);
    }
  }

  function bootstrap() {
    if (bootstrapped) {
      return;
    }
    bootstrapped = true;

    if ($('#input-shipping-code').val()) {
      refreshTotals(revision);
      if (typeof window.bsCheckoutLoadPaymentMethods === 'function') {
        window.bsCheckoutLoadPaymentMethods({ stateRevision: revision });
      }
      return;
    }

    if (typeof window.bsCheckoutLoadShippingMethods === 'function') {
      window.bsCheckoutLoadShippingMethods({
        autoSelect: true,
        quietAddressError: true,
        stateRevision: revision
      });
      return;
    }

    renderPaymentPreview();
    renderConfirmState();
  }

  window.bsCheckoutState = {
    currentRevision: currentRevision,
    isCurrent: isCurrent,
    addressSaved: addressSaved,
    beginShippingSelection: beginShippingSelection,
    shippingSaved: shippingSaved,
    paymentContextSaved: paymentContextSaved,
    customerSaved: customerSaved,
    paymentSaved: paymentSaved,
    paymentMethodsRendered: paymentMethodsRendered,
    totalsChanged: totalsChanged,
    refreshTotals: refreshTotals,
    renderConfirm: renderConfirmState,
    bootstrap: bootstrap
  };

  $(bootstrap);
})(jQuery);
JS;
$stateJs .= "\n";

$files['catalog/controller/checkout/register.php'] = st2c3_replace_once(
    $files['catalog/controller/checkout/register.php'],
<<<'OLD'
		$this->load->language('checkout/register');

		$json = [];
OLD
,
<<<'NEW'
		$this->load->language('checkout/register');

		$shipping_state_before = $this->boosterCheckoutAddressFingerprint('shipping_address');
		$payment_state_before = $this->boosterCheckoutAddressFingerprint('payment_address');
		$json = [];
NEW
,
    'register_state_before'
);

$files['catalog/controller/checkout/register.php'] = st2c3_replace_once(
    $files['catalog/controller/checkout/register.php'],
<<<'OLD'
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
OLD
,
<<<'NEW'
			$shipping_changed = $shipping_state_before !== $this->boosterCheckoutAddressFingerprint('shipping_address');
			$payment_changed = $payment_state_before !== $this->boosterCheckoutAddressFingerprint('payment_address');

			if ($shipping_changed) {
				unset($this->session->data['shipping_method']);
				unset($this->session->data['shipping_methods']);
				unset($this->session->data['payment_method']);
				unset($this->session->data['payment_methods']);
			} elseif ($payment_changed) {
				unset($this->session->data['payment_method']);
				unset($this->session->data['payment_methods']);
			}

			$json['checkout_state'] = [
				'shipping_changed' => $shipping_changed,
				'payment_changed' => $payment_changed,
			];
NEW
,
    'register_selective_invalidation'
);

$files['catalog/controller/checkout/register.php'] = st2c3_replace_once(
    $files['catalog/controller/checkout/register.php'],
<<<'OLD'
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
OLD
,
<<<'NEW'
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function boosterCheckoutAddressFingerprint(string $session_key): string {
		$address = $this->session->data[$session_key] ?? [];

		if (!is_array($address) || !$address) {
			return '';
		}

		$relevant = [];

		foreach (['company', 'address_1', 'address_2', 'city', 'postcode', 'country_id', 'zone_id', 'custom_field'] as $key) {
			$relevant[$key] = $address[$key] ?? null;
		}

		$normalize = static function($value) use (&$normalize) {
			if (!is_array($value)) {
				return is_string($value) ? trim($value) : $value;
			}

			foreach ($value as $key => $item) {
				$value[$key] = $normalize($item);
			}

			$is_list = $value === [] || array_keys($value) === range(0, count($value) - 1);

			if (!$is_list) {
				ksort($value);
			}

			return $value;
		};

		return hash('sha256', json_encode($normalize($relevant), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}
}
NEW
,
    'register_fingerprint_helper'
);

$files['catalog/controller/checkout/payment_method.php'] = st2c3_replace_once(
    $files['catalog/controller/checkout/payment_method.php'],
<<<'OLD'
			$payment_methods = $this->model_checkout_payment_method->getMethods($payment_address);

			if ($payment_methods) {
OLD
,
<<<'NEW'
			$payment_methods = $this->model_checkout_payment_method->getMethods($payment_address);
			$payment_methods = $this->filterBoosterCheckoutPaymentMethods($payment_methods);

			if (isset($this->session->data['payment_method']['code'])) {
				$current_code = (string)$this->session->data['payment_method']['code'];

				if (!$this->boosterPaymentCodeExists($payment_methods, $current_code)) {
					unset($this->session->data['payment_method']);
				}
			}

			if ($payment_methods) {
NEW
,
    'payment_server_filter_call'
);

$paymentHelpers = <<<'PHP'
	/**
	 * Booster checkout exposes one canonical option for each supported payment
	 * category. Unknown extensions and duplicate stock COD never reach the UI or
	 * the session validation map.
	 *
	 * @param array<string, mixed> $payment_methods
	 * @return array<string, mixed>
	 */
	private function filterBoosterCheckoutPaymentMethods(array $payment_methods): array {
		$candidates = [];
		$sequence = 0;

		foreach ($payment_methods as $group_key => $group) {
			$options = is_array($group['option'] ?? null) ? $group['option'] : [];

			foreach ($options as $option_key => $option) {
				if (!is_array($option)) {
					continue;
				}

				$category = $this->boosterPaymentCategory($option);

				if ($category === '') {
					continue;
				}

				$candidates[$category][] = [
					'group_key' => $group_key,
					'option_key' => $option_key,
					'option' => $option,
					'score' => $this->boosterPaymentPreferenceScore($category, $option),
					'sequence' => $sequence++,
				];
			}
		}

		$filtered = [];

		foreach (['hutko', 'cod', 'bank'] as $category) {
			if (empty($candidates[$category])) {
				continue;
			}

			usort($candidates[$category], static function(array $left, array $right): int {
				return [$left['score'], $left['sequence']] <=> [$right['score'], $right['sequence']];
			});

			$selected = $candidates[$category][0];
			$group_key = $selected['group_key'];

			if (!isset($filtered[$group_key])) {
				$filtered[$group_key] = $payment_methods[$group_key];
				$filtered[$group_key]['option'] = [];
			}

			$selected_option = $selected['option'];
			$selected_option['booster_category'] = $category;
			$filtered[$group_key]['option'][$selected['option_key']] = $selected_option;
		}

		return $filtered;
	}

	/** @param array<string, mixed> $option */
	private function boosterPaymentCategory(array $option): string {
		$code = oc_strtolower(trim((string)($option['code'] ?? '')));
		$name = oc_strtolower(trim((string)($option['name'] ?? $option['title'] ?? '')));
		$value = $code . ' ' . $name;

		if (str_contains($value, 'hutko') || str_contains($value, 'mono') || str_contains($value, 'card') || str_contains($value, 'картк')) {
			return 'hutko';
		}

		if (str_contains($value, 'cod') || str_contains($value, 'cash') || str_contains($value, 'after') || str_contains($value, 'післяплат') || str_contains($value, 'накладен')) {
			return 'cod';
		}

		if (str_contains($value, 'bank') || str_contains($value, 'transfer') || str_contains($value, 'rekv') || str_contains($value, 'iban') || str_contains($value, 'реквізит')) {
			return 'bank';
		}

		return '';
	}

	/** @param array<string, mixed> $option */
	private function boosterPaymentPreferenceScore(string $category, array $option): int {
		$code = oc_strtolower(trim((string)($option['code'] ?? '')));
		$name = oc_strtolower(trim((string)($option['name'] ?? $option['title'] ?? '')));
		$score = 20;

		if ($category === 'hutko' && str_contains($code, 'hutko')) {
			$score = 0;
		}

		if ($category === 'bank' && (str_contains($code, 'bank') || str_contains($code, 'transfer'))) {
			$score = 0;
		}

		if ($category === 'cod') {
			if (str_contains($name, 'накладений платіж') || str_contains($name, 'післяплата')) {
				$score = 0;
			} elseif (str_contains($code, 'pinta') || str_contains($code, 'nova') || str_contains($code, 'after')) {
				$score = 5;
			}

			// The generic OpenCart COD option is a fallback only. If the configured
			// Nova Poshta/post-payment option exists, it wins deterministically.
			if ($code === 'cod.cod') {
				$score += 100;
			}
		}

		return $score;
	}

	/** @param array<string, mixed> $payment_methods */
	private function boosterPaymentCodeExists(array $payment_methods, string $code): bool {
		foreach ($payment_methods as $group) {
			foreach ((array)($group['option'] ?? []) as $option) {
				if ((string)($option['code'] ?? '') === $code) {
					return true;
				}
			}
		}

		return false;
	}
PHP;

$files['catalog/controller/checkout/payment_method.php'] = st2c3_replace_once(
    $files['catalog/controller/checkout/payment_method.php'],
<<<'OLD'
	/**
	 * Save
OLD
,
    $paymentHelpers . <<<'NEW'


	/**
	 * Save
NEW
,
    'payment_filter_helpers'
);

$files['catalog/view/template/checkout/checkout.twig'] = st2c3_replace_once(
    $files['catalog/view/template/checkout/checkout.twig'],
<<<'OLD'
<div id="checkout-checkout" class="container bs-co" data-rd13-checkout data-bs-customer-phone="{{ customer_telephone }}"
OLD
,
<<<'NEW'
<div id="checkout-checkout" class="container bs-co" data-rd13-checkout data-bs-language="{{ language }}" data-bs-customer-phone="{{ customer_telephone }}"
NEW
,
    'checkout_language_attribute'
);

$files['catalog/view/template/checkout/checkout.twig'] = st2c3_replace_once(
    $files['catalog/view/template/checkout/checkout.twig'],
<<<'OLD'
  var bsRegisterAutosaveWatchdog = null;
  var bsAutoShippingStarted = false;
  var bsCheckoutConfirmSubmitting = false;
OLD
,
<<<'NEW'
  var bsRegisterAutosaveWatchdog = null;
  var bsCheckoutConfirmSubmitting = false;
NEW
,
    'checkout_remove_auto_shipping_owner'
);

$files['catalog/view/template/checkout/checkout.twig'] = st2c3_replace_once(
    $files['catalog/view/template/checkout/checkout.twig'],
<<<'OLD'
  window.bsCheckoutResetMethodState = function(reason) {
    $('#input-shipping-code, #input-shipping-method, #input-payment-code, #input-payment-method').val('');
OLD
,
<<<'NEW'
  window.bsCheckoutResetMethodState = function(reason) {
    if (window.bsCheckoutState) {
      if (reason === 'address') {
        return window.bsCheckoutState.addressSaved();
      }

      return window.bsCheckoutState.beginShippingSelection();
    }

    $('#input-shipping-code, #input-shipping-method, #input-payment-code, #input-payment-method').val('');
NEW
,
    'checkout_reset_delegates_to_state'
);

$files['catalog/view/template/checkout/checkout.twig'] = st2c3_replace_once(
    $files['catalog/view/template/checkout/checkout.twig'],
<<<'OLD'
    if (form.data('bsLastSaved') === signature) {
      if ($('#input-shipping-address').val() && typeof window.bsCheckoutLoadShippingMethods === 'function') {
        window.bsCheckoutResetMethodState('address');
        window.setTimeout(function() {
          window.bsCheckoutLoadShippingMethods({ autoSelect: true });
        }, 80);
      }

      return true;
    }
OLD
,
<<<'NEW'
    if (form.data('bsLastSaved') === signature) {
      return true;
    }
NEW
,
    'checkout_no_reload_for_same_signature'
);

$files['catalog/view/template/checkout/checkout.twig'] = st2c3_replace_once(
    $files['catalog/view/template/checkout/checkout.twig'],
<<<'OLD'
    // CHECKOUT-003: no register autosave during NP initialisation.
    // Hidden refs can survive while visible NP fields are blank; only a real
    // customer interaction may schedule checkout/register.save.

    if (!bsAutoShippingStarted && $('#input-shipping-address').val() && !$('#input-shipping-code').val() && typeof window.bsCheckoutLoadShippingMethods === 'function') {
      bsAutoShippingStarted = true;
      window.setTimeout(function() {
        window.bsCheckoutLoadShippingMethods({ autoSelect: true, quietAddressError: true });
      }, 350);
    }
OLD
,
<<<'NEW'
    // CHECKOUT-003: no register autosave during NP initialisation.
    // Initial shipping/payment orchestration belongs to checkout-state.js.
NEW
,
    'checkout_remove_initial_loader'
);

$files['catalog/view/template/checkout/checkout.twig'] = st2c3_replace_once(
    $files['catalog/view/template/checkout/checkout.twig'],
<<<'OLD'
      if (typeof window.bsCheckoutLoadShippingMethods === 'function') {
        window.bsCheckoutResetMethodState('address');
        window.setTimeout(function() {
          window.bsCheckoutLoadShippingMethods({ autoSelect: true });
        }, 250);
      }
    }

    if (json && json['success'] && url.indexOf('checkout/shipping_address') !== -1 && typeof window.bsCheckoutLoadShippingMethods === 'function') {
      window.bsCheckoutResetMethodState('address');
      window.setTimeout(function() {
        window.bsCheckoutLoadShippingMethods({ autoSelect: true });
      }, 250);
    }
OLD
,
<<<'NEW'
      if (window.bsCheckoutState) {
        var checkoutState = json['checkout_state'] || null;

        if (!checkoutState || checkoutState['shipping_changed']) {
          window.bsCheckoutState.addressSaved();
        } else if (checkoutState['payment_changed']) {
          window.bsCheckoutState.paymentContextSaved();
        } else {
          window.bsCheckoutState.customerSaved();
        }
      }
    }

    if (json && json['success'] && url.indexOf('checkout/shipping_address') !== -1 && window.bsCheckoutState) {
      window.bsCheckoutState.addressSaved();
    }
NEW
,
    'checkout_single_success_dispatch'
);

$files['catalog/view/template/checkout/checkout.twig'] = st2c3_replace_once(
    $files['catalog/view/template/checkout/checkout.twig'],
<<<'OLD'
<script src="catalog/view/javascript/checkout-reskin.js?v=checkout004-20260715"></script>
OLD
,
<<<'NEW'
<script src="catalog/view/javascript/checkout-state.js?v=st2c3-20260719"></script>
<script src="catalog/view/javascript/checkout-reskin.js?v=st2c3-20260719"></script>
NEW
,
    'checkout_state_asset'
);

$shippingFile = 'catalog/view/template/checkout/shipping_method.twig';
$files[$shippingFile] = st2c3_replace_once($files[$shippingFile],
<<<'OLD'
  function saveShipping(code, label) {
    if (!code) {
      return;
    }

    status('Зберігаємо спосіб доставки...');
OLD
,
<<<'NEW'
  function saveShipping(code, label, stateRevision) {
    if (!code) {
      return;
    }

    if (window.bsCheckoutState && stateRevision === undefined) {
      stateRevision = window.bsCheckoutState.currentRevision();
    }

    status('Зберігаємо спосіб доставки...');
NEW
, 'shipping_save_revision');

$files[$shippingFile] = st2c3_replace_once($files[$shippingFile],
<<<'OLD'
        url: 'index.php?route=checkout/shipping_method.save&language={{ language }}',
        type: 'post',
        data: { shipping_method: code },
        dataType: 'json',
        success: function(json) {
          if (json['redirect']) {
OLD
,
<<<'NEW'
        url: 'index.php?route=checkout/shipping_method.save&language={{ language }}',
        type: 'post',
        data: { shipping_method: code },
        dataType: 'json',
        success: function(json) {
          if (window.bsCheckoutState && !window.bsCheckoutState.isCurrent(stateRevision)) {
            return;
          }

          if (json['redirect']) {
NEW
, 'shipping_save_stale_gate');

$files[$shippingFile] = st2c3_replace_once($files[$shippingFile],
<<<'OLD'
            if (window.bsCheckoutLoadPaymentMethods) {
              window.setTimeout(function() {
                window.bsCheckoutLoadPaymentMethods();
              }, 200);
            }
OLD
,
<<<'NEW'
            if (window.bsCheckoutState) {
              window.bsCheckoutState.shippingSaved(code, label || code, stateRevision);
            } else if (window.bsCheckoutLoadPaymentMethods) {
              window.bsCheckoutLoadPaymentMethods();
            }
NEW
, 'shipping_save_dispatch');

$files[$shippingFile] = st2c3_replace_once($files[$shippingFile],
<<<'OLD'
    var html = '';
    var firstQuote = null;
    var current = $('#input-shipping-code').val();
OLD
,
<<<'NEW'
    var html = '';
    var firstQuote = null;
    var currentQuote = null;
    var current = $('#input-shipping-code').val();
NEW
, 'shipping_current_quote_state');

$files[$shippingFile] = st2c3_replace_once($files[$shippingFile],
<<<'OLD'
          if (!firstQuote) {
            firstQuote = { code: code, label: label };
          }

          html += '<div class="form-check">';
OLD
,
<<<'NEW'
          if (!firstQuote) {
            firstQuote = { code: code, label: label };
          }

          if (checked) {
            currentQuote = { code: code, label: label };
          }

          html += '<div class="form-check">';
NEW
, 'shipping_capture_current_quote');

$files[$shippingFile] = st2c3_replace_once($files[$shippingFile],
<<<'OLD'
      saveShipping(firstQuote.code, firstQuote.label);
    } else if (current) {
      status('Доставку обрано.');

      if (window.bsCheckoutLoadPaymentMethods) {
        window.setTimeout(function() {
          window.bsCheckoutLoadPaymentMethods();
        }, 150);
      }
OLD
,
<<<'NEW'
      saveShipping(firstQuote.code, firstQuote.label, options.stateRevision);
    } else if (current) {
      status('Доставку обрано.');

      if (window.bsCheckoutState) {
        window.bsCheckoutState.shippingSaved(current, currentQuote ? currentQuote.label : $('#input-shipping-method').val(), options.stateRevision);
      } else if (window.bsCheckoutLoadPaymentMethods) {
        window.bsCheckoutLoadPaymentMethods();
      }
NEW
, 'shipping_render_dispatch');

$files[$shippingFile] = st2c3_replace_once($files[$shippingFile],
<<<'OLD'
  window.bsCheckoutLoadShippingMethods = function(options) {
    options = options || {};
    status('Завантажуємо доставку...');
OLD
,
<<<'NEW'
  window.bsCheckoutLoadShippingMethods = function(options) {
    options = options || {};
    if (window.bsCheckoutState && options.stateRevision === undefined) {
      options.stateRevision = window.bsCheckoutState.currentRevision();
    }
    status('Завантажуємо доставку...');
NEW
, 'shipping_load_revision');

$files[$shippingFile] = st2c3_replace_once($files[$shippingFile],
<<<'OLD'
        url: 'index.php?route=checkout/shipping_method.quote&language={{ language }}',
        dataType: 'json',
        success: function(json) {
          if (json['redirect']) {
OLD
,
<<<'NEW'
        url: 'index.php?route=checkout/shipping_method.quote&language={{ language }}',
        dataType: 'json',
        success: function(json) {
          if (window.bsCheckoutState && !window.bsCheckoutState.isCurrent(options.stateRevision)) {
            return;
          }

          if (json['redirect']) {
NEW
, 'shipping_quote_stale_gate');

$files[$shippingFile] = st2c3_replace_once($files[$shippingFile],
<<<'OLD'
  window.bsCheckoutAutoShipping = function() {
    return window.bsCheckoutLoadShippingMethods({ autoSelect: true });
  };

  $(document).off('change.bsInlineShipping', '#bs-shipping-methods input[name="shipping_method"]').on('change.bsInlineShipping', '#bs-shipping-methods input[name="shipping_method"]', function() {
    saveShipping($(this).val(), $(this).attr('data-label') || $(this).closest('.form-check').find('label').text());
  });

  $(function() {
    window.setTimeout(function() {
      window.bsCheckoutLoadShippingMethods({ autoSelect: true, quietAddressError: true });
    }, 250);
  });
OLD
,
<<<'NEW'
  window.bsCheckoutAutoShipping = function() {
    return window.bsCheckoutLoadShippingMethods({
      autoSelect: true,
      stateRevision: window.bsCheckoutState ? window.bsCheckoutState.currentRevision() : undefined
    });
  };

  $(document).off('change.bsInlineShipping', '#bs-shipping-methods input[name="shipping_method"]').on('change.bsInlineShipping', '#bs-shipping-methods input[name="shipping_method"]', function() {
    var stateRevision = window.bsCheckoutState ? window.bsCheckoutState.beginShippingSelection() : undefined;
    saveShipping($(this).val(), $(this).attr('data-label') || $(this).closest('.form-check').find('label').text(), stateRevision);
  });
NEW
, 'shipping_single_owner');

$paymentTwig = 'catalog/view/template/checkout/payment_method.twig';
$files[$paymentTwig] = st2c3_replace_once($files[$paymentTwig],
<<<'OLD'
  function refreshConfirmSummary() {
    if (window.bsCheckoutRefreshConfirmIfPaymentReady) {
      window.bsCheckoutRefreshConfirmIfPaymentReady();
    }
  }
OLD
,
<<<'NEW'
  function refreshConfirmSummary() {
    if (window.bsCheckoutState) {
      window.bsCheckoutState.renderConfirm();
    } else if (window.bsCheckoutRefreshConfirmIfPaymentReady) {
      window.bsCheckoutRefreshConfirmIfPaymentReady();
    }
  }
NEW
, 'payment_confirm_delegate');

$files[$paymentTwig] = st2c3_replace_once($files[$paymentTwig],
<<<'OLD'
  function renderPaymentMethods(json) {
    if (json && json.error) {
OLD
,
<<<'NEW'
  function renderPaymentMethods(json, stateOptions) {
    stateOptions = stateOptions || {};

    if (window.bsCheckoutState && !window.bsCheckoutState.isCurrent(stateOptions.stateRevision)) {
      return;
    }

    if (json && json.error) {
NEW
, 'payment_render_revision');

$files[$paymentTwig] = st2c3_replace_once($files[$paymentTwig],
<<<'OLD'
                id: optionKey
OLD
,
<<<'NEW'
                id: optionKey,
                category: option.booster_category || ''
NEW
, 'payment_canonical_category_transport');

$files[$paymentTwig] = st2c3_replace_once($files[$paymentTwig],
<<<'OLD'
  function findPaymentOption(options, choice) {
    var found = null;

    $.each(options, function(index, option) {
      if (!found && paymentMatchesChoice(option.code, choice)) {
OLD
,
<<<'NEW'
  function findPaymentOption(options, choice) {
    var found = null;
    choice = normalizeChoice(choice);

    $.each(options, function(index, option) {
      if (!found && (normalizeChoice(option.category) === choice || paymentMatchesChoice(option.code, choice))) {
NEW
, 'payment_canonical_category_match');

$files[$paymentTwig] = st2c3_replace_once($files[$paymentTwig],
<<<'OLD'
    var options = flattenPaymentMethods(json);
    var current = $('#input-payment-code').val();
    var selected = current ? findPaymentOption(options, current) : null;


    if (!options.length) {
OLD
,
<<<'NEW'
    var options = flattenPaymentMethods(json);
    var current = $('#input-payment-code').val();
    var selected = current ? findPaymentOption(options, current) : null;
    var desiredChoice = normalizeChoice(bsPaymentPendingChoice || current);

    if (current && !selected) {
      current = '';
      $('#input-payment-code, #input-payment-method').val('');
    }

    if (!selected && desiredChoice) {
      selected = findPaymentOption(options, desiredChoice);
    }


    if (!options.length) {
NEW
, 'payment_reconcile_selection');

$files[$paymentTwig] = st2c3_replace_once($files[$paymentTwig],
<<<'OLD'
    $('#bs-payment-methods').html(html);

    status(current ? 'Оплату обрано.' : 'Оберіть спосіб оплати.');
    refreshConfirmSummary();
  }

  function savePayment(code, label, isAuto, event) {
    if (!code) {
      return;
    }


OLD
,
<<<'NEW'
    $('#bs-payment-methods').html(html);

    if (selected && selected.code !== current) {
      savePayment(selected.code, selected.label, true, null, stateOptions.stateRevision);
      return;
    }

    status(current ? 'Оплату обрано.' : 'Оберіть спосіб оплати.');
    if (window.bsCheckoutState) {
      window.bsCheckoutState.paymentMethodsRendered(stateOptions.stateRevision);
    } else {
      refreshConfirmSummary();
    }
  }

  function savePayment(code, label, isAuto, event, stateRevision) {
    if (!code) {
      return;
    }

    if (window.bsCheckoutState && stateRevision === undefined) {
      stateRevision = window.bsCheckoutState.currentRevision();
    }


NEW
, 'payment_save_revision');

$files[$paymentTwig] = st2c3_replace_once($files[$paymentTwig],
<<<'OLD'
      data: { payment_method: code },
      dataType: 'json',
      success: function(json) {
        $('#error-payment-method').removeClass('d-block').text('');
OLD
,
<<<'NEW'
      data: { payment_method: code },
      dataType: 'json',
      success: function(json) {
        if (window.bsCheckoutState && !window.bsCheckoutState.isCurrent(stateRevision)) {
          return;
        }

        $('#error-payment-method').removeClass('d-block').text('');
NEW
, 'payment_save_stale_gate');

$files[$paymentTwig] = st2c3_replace_once($files[$paymentTwig],
<<<'OLD'
        $('#input-payment-code').val(code);
        status('Оплату обрано.');
        refreshConfirmSummary();
OLD
,
<<<'NEW'
        $('#input-payment-code').val(code);
        status('Оплату обрано.');
        if (window.bsCheckoutState) {
          window.bsCheckoutState.paymentSaved(code, label || code, stateRevision);
        } else {
          refreshConfirmSummary();
        }
NEW
, 'payment_save_dispatch');

$files[$paymentTwig] = st2c3_replace_once($files[$paymentTwig],
<<<'OLD'
  window.bsCheckoutLoadPaymentMethods = function() {
    if (!hasShippingReady()) {
OLD
,
<<<'NEW'
  window.bsCheckoutLoadPaymentMethods = function(options) {
    options = options || {};
    if (window.bsCheckoutState && options.stateRevision === undefined) {
      options.stateRevision = window.bsCheckoutState.currentRevision();
    }

    if (!hasShippingReady()) {
NEW
, 'payment_load_revision');

$files[$paymentTwig] = st2c3_replace_once($files[$paymentTwig],
<<<'OLD'
      url: 'index.php?route=checkout/payment_method.getMethods&language={{ language }}',
      dataType: 'json',
      success: renderPaymentMethods,
OLD
,
<<<'NEW'
      url: 'index.php?route=checkout/payment_method.getMethods&language={{ language }}',
      dataType: 'json',
      success: function(json) {
        renderPaymentMethods(json, options);
      },
NEW
, 'payment_load_dispatch');

$files[$paymentTwig] = st2c3_replace_once($files[$paymentTwig],
<<<'OLD'
  $(document).on('change', '#bs-payment-methods input[name="payment_method"]', function(event) {
    var $input = $(this);
    savePayment($input.val(), $input.data('label'), false, event);
  });
OLD
,
<<<'NEW'
  $(document).on('change', '#bs-payment-methods input[name="payment_method"]', function(event) {
    var $input = $(this);
    savePayment($input.val(), $input.data('label'), false, event, window.bsCheckoutState ? window.bsCheckoutState.currentRevision() : undefined);
  });
NEW
, 'payment_manual_revision');

$files[$paymentTwig] = st2c3_replace_once($files[$paymentTwig],
<<<'OLD'
  $(function() {
    bsPaymentPendingChoice = normalizeChoice($('#input-payment-code').val() || bsPaymentPendingChoice);

    if (hasShippingReady()) {
      setTimeout(window.bsCheckoutLoadPaymentMethods, 300);
    } else {
      renderPaymentPreview();
    }
  });
OLD
,
<<<'NEW'
  bsPaymentPendingChoice = normalizeChoice($('#input-payment-code').val() || bsPaymentPendingChoice);
NEW
, 'payment_remove_ready_owner');

$reskinFile = 'catalog/view/javascript/checkout-reskin.js';
$files[$reskinFile] = st2c3_replace_once($files[$reskinFile],
<<<'OLD'
    var status = promo.querySelector('[data-co-promo-status]');
    var busy = false;
    // ST-2c.2: keep one quiet summary refresh queued while address autosave
    // and the subsequent shipping-method save overlap.
    var queuedQuietSummary = false;
OLD
,
<<<'NEW'
    var status = promo.querySelector('[data-co-promo-status]');
    var busy = false;
NEW
, 'reskin_remove_st2c2_state');

$files[$reskinFile] = st2c3_replace_once($files[$reskinFile],
<<<'OLD'
      if (json.summary_html && window.bsCheckoutUpdateCachedSummaryHtml) {
        window.bsCheckoutUpdateCachedSummaryHtml(json.summary_html);
      }

      if (json.error) {
OLD
,
<<<'NEW'
      if (json.error) {
NEW
, 'reskin_coupon_not_totals_bus');

$files[$reskinFile] = st2c3_replace_once($files[$reskinFile],
<<<'OLD'
      } else if (!options.quiet && json.welcome_coupon_applied) {
        setStatus('Промокод ' + json.welcome_coupon_applied + ' застосовано.', false);
      }
    }

    function request(action, data, options) {
      options = options || {};
      if (busy) {
        if (action === 'summary') {
          queuedQuietSummary = true;
        }
        return;
      }
OLD
,
<<<'NEW'
      } else if (!options.quiet && json.welcome_coupon_applied) {
        setStatus('Промокод ' + json.welcome_coupon_applied + ' застосовано.', false);
      }

      if (window.bsCheckoutState) {
        window.bsCheckoutState.totalsChanged('coupon');
      }
    }

    function request(action, data, options) {
      options = options || {};
      if (busy) {
        return;
      }
NEW
, 'reskin_coupon_signals_state');

$files[$reskinFile] = st2c3_replace_once($files[$reskinFile],
<<<'OLD'
        complete: function() {
          var refreshQueued = queuedQuietSummary;
          queuedQuietSummary = false;
          busy = false;

          if (refreshQueued) {
            window.setTimeout(function() {
              request('summary', {}, { quiet: true });
            }, 0);
          }
        }
OLD
,
<<<'NEW'
        complete: function() {
          busy = false;
        }
NEW
, 'reskin_remove_summary_queue');

$files[$reskinFile] = st2c3_replace_once($files[$reskinFile],
<<<'OLD'

  // ST-2c.1: shipping_method.save has stored the live quote; refresh only the
  // cached sidebar/preview summary. It never calls checkout/confirm.confirm.
  var st2c1ShippingSummaryRefreshTimer = null;
  $(document).on('ajaxSuccess.st2c1ShippingSummary', function(event, xhr, settings) {
    var url = settings && settings.url ? settings.url : '';
    var json = xhr && xhr.responseJSON ? xhr.responseJSON : null;

    if (!json || !json.success || url.indexOf('checkout/shipping_method.save') === -1) {
      return;
    }

    window.clearTimeout(st2c1ShippingSummaryRefreshTimer);
    st2c1ShippingSummaryRefreshTimer = window.setTimeout(function() {
      if (typeof window.bsCheckoutRefreshPromoCouponSummary === 'function') {
        window.bsCheckoutRefreshPromoCouponSummary({ quiet: true });
      }
    }, 0);
  });

OLD
, ''
, 'reskin_remove_st2c1_hook');

$postChecks = [
    'catalog/controller/checkout/register.php' => ['private function boosterCheckoutAddressFingerprint', "'checkout_state'"],
    'catalog/controller/checkout/payment_method.php' => ['private function filterBoosterCheckoutPaymentMethods', "if (\$code === 'cod.cod')", "['booster_category'] = \$category"],
    'catalog/view/template/checkout/checkout.twig' => ['checkout-state.js?v=st2c3-20260719', 'window.bsCheckoutState.customerSaved()'],
    'catalog/view/template/checkout/shipping_method.twig' => ['window.bsCheckoutState.shippingSaved(code, label || code, stateRevision);', 'window.bsCheckoutState.beginShippingSelection'],
    'catalog/view/template/checkout/payment_method.twig' => ['window.bsCheckoutState.paymentSaved', 'function renderPaymentMethods(json, stateOptions)', 'option.booster_category'],
    'catalog/view/javascript/checkout-reskin.js' => ["window.bsCheckoutState.totalsChanged('coupon')"],
];

foreach ($postChecks as $relative => $needles) {
    foreach ($needles as $needle) {
        if (substr_count($files[$relative], $needle) !== 1) {
            st2c3_fail('postcheck_failed:' . $relative . ':' . $needle);
        }
    }
}

foreach (['ST-2c.1:', 'ST-2c.2:', 'st2c1ShippingSummary', 'queuedQuietSummary'] as $removedNeedle) {
    if (strpos($files['catalog/view/javascript/checkout-reskin.js'], $removedNeedle) !== false) {
        st2c3_fail('removed_hook_still_present:' . $removedNeedle);
    }
}

$timestamp = gmdate('Ymd_His');
$backupDir = st2c3_path($root, '_patch_backups/' . $patch . '_' . $timestamp);
$written = [];

foreach (array_keys($expectedHashes) as $relative) {
    $source = st2c3_path($root, $relative);
    $backup = st2c3_path($backupDir, $relative);

    if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0775, true) && !is_dir(dirname($backup))) {
        st2c3_fail('backup_directory_create_failed:' . $relative);
    }

    if (!copy($source, $backup)) {
        st2c3_fail('backup_copy_failed:' . $relative);
    }
}

foreach ($files as $relative => $content) {
    $path = st2c3_path($root, $relative);

    if (file_put_contents($path, $content) === false) {
        foreach ($written as $restoreRelative) {
            @copy(st2c3_path($backupDir, $restoreRelative), st2c3_path($root, $restoreRelative));
        }
        st2c3_fail('target_write_failed:' . $relative . '; restored=yes');
    }

    $written[] = $relative;
}

if (!is_dir(dirname($statePath)) && !mkdir(dirname($statePath), 0775, true) && !is_dir(dirname($statePath))) {
    st2c3_fail('new_target_directory_create_failed');
}

if (file_put_contents($statePath, $stateJs) === false) {
    foreach ($written as $restoreRelative) {
        @copy(st2c3_path($backupDir, $restoreRelative), st2c3_path($root, $restoreRelative));
    }
    st2c3_fail('new_target_write_failed; restored=yes');
}

$lintTargets = [
    st2c3_path($root, 'catalog/controller/checkout/register.php'),
    st2c3_path($root, 'catalog/controller/checkout/payment_method.php'),
    __FILE__,
];

foreach ($lintTargets as $lintTarget) {
    $lintOutput = [];
    $lintCode = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($lintTarget) . ' 2>&1', $lintOutput, $lintCode);

    if ($lintCode !== 0) {
        foreach (array_keys($expectedHashes) as $restoreRelative) {
            @copy(st2c3_path($backupDir, $restoreRelative), st2c3_path($root, $restoreRelative));
        }
        @unlink($statePath);
        st2c3_fail('php_l_failed:' . basename($lintTarget) . '; restored=yes; detail=' . implode(' | ', $lintOutput));
    }
}

echo 'cwd=' . $root . PHP_EOL;
echo 'time=' . gmdate('c') . PHP_EOL;
echo 'backup=' . $backupDir . PHP_EOL;

foreach (array_keys($expectedHashes) as $relative) {
    echo 'changed=' . $relative . PHP_EOL;
}

echo 'created=' . $stateRelative . PHP_EOL;
echo 'php_l=ok' . PHP_EOL;
echo 'done=ok' . PHP_EOL;

@unlink(__FILE__);
