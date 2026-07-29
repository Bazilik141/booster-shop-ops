<?php
declare(strict_types=1);

/**
 * CHECKOUT-009 Stage 1: typed coupon results, one address completion writer,
 * deferred First15 requote, and one immutable checkout asset key.
 *
 * Scope:
 * - add the truthful `mutated` boolean to coupon.summary only;
 * - classify summary/apply/remove on the client;
 * - preserve the current coupon requote/resave path for real mutations;
 * - queue a First15 requote behind an in-flight address/bootstrap commit;
 * - release a superseded address revision before any later promo requote;
 * - replace Pinta's 250 ms quote timer and the broad URL-substring listener
 *   with one explicit AddressCommitted callback;
 * - publish checkout-state.js and checkout-reskin.js under one new key.
 *
 * Third-party warning:
 * extension/PintaNovaPoshtaCod/.../js_checkout_shipping_address_form.twig is
 * vendor-owned. A Pinta update can overwrite this change and reintroduce the
 * duplicate shipping writer. Recheck CHECKOUT-009 after every Pinta update.
 *
 * Database: no changes.
 * Rollback: restore every file from the single backup directory printed by
 * this runner. Never roll back only the cache key or only the Pinta file.
 */

const PATCH_ID = 'CHECKOUT-009_stage1_coupon-classification-single-writer_20260729';
const PATCH_MARKER = 'CHECKOUT-009-STAGE1-20260729';

function fail_patch(string $message, int $code = 1): void {
    fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL);
    exit($code);
}

function log_line(string $key, string $value): void {
    echo $key . '=' . $value . PHP_EOL;
}

function replace_once(string $content, string $before, string $after, string $label): string {
    $count = substr_count($content, $before);

    if ($count !== 1) {
        fail_patch('anchor_count[' . $label . ']=' . $count . ' expected=1');
    }

    return str_replace($before, $after, $content);
}

function normalise_root(string $root): string {
    $resolved = realpath($root);

    if ($resolved === false || !is_dir($resolved)) {
        fail_patch('root_not_found=' . $root);
    }

    return rtrim($resolved, DIRECTORY_SEPARATOR);
}

function parse_root(array $arguments): string {
    foreach ($arguments as $argument) {
        if (strncmp($argument, '--root=', 7) === 0) {
            return substr($argument, 7);
        }
    }

    return getcwd() ?: '.';
}

function ensure_parent_directory(string $path): void {
    $parent = dirname($path);

    if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
        fail_patch('mkdir_failed=' . $parent);
    }
}

function write_atomic(string $path, string $content): void {
    $temporary = $path . '.checkout009.' . getmypid() . '.tmp';

    if (file_put_contents($temporary, $content, LOCK_EX) === false) {
        throw new RuntimeException('temporary_write_failed=' . $temporary);
    }

    if (!rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('atomic_replace_failed=' . $path);
    }
}

function restore_all(string $backupRoot, string $root, array $relativePaths): array {
    $failures = [];

    foreach ($relativePaths as $relativePath) {
        $backup = $backupRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (!is_file($backup)) {
            $failures[] = $relativePath . ':backup_missing';
            continue;
        }

        ensure_parent_directory($target);

        if (!copy($backup, $target)) {
            $failures[] = $relativePath . ':restore_copy_failed';
        }
    }

    return $failures;
}

function php_lint(string $path): array {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1';
    $output = [];
    $status = 1;
    exec($command, $output, $status);

    return [$status, trim(implode(PHP_EOL, $output))];
}

$arguments = array_slice($argv ?? [], 1);
$dryRun = in_array('--dry-run', $arguments, true);
$root = normalise_root(parse_root($arguments));
$startedAt = gmdate('Y-m-d\TH:i:s\Z');

log_line('patch', PATCH_ID);
log_line('cwd', $root);
log_line('time', $startedAt);
log_line('dry_run', $dryRun ? 'yes' : 'no');
log_line('db_changes', 'none');
log_line('third_party_pinta_warning', 'update_can_overwrite_single-writer_fix');

$targets = [
    'catalog/controller/checkout/coupon.php' => [
        'sha256' => '114923340eb7da20ce3da2f0668b2ed1a0ba3fdc67ad7bd0b68bdfac5aa5840e',
        'transform' => static function (string $content): string {
            $before = <<<'BEFORE_COUPON_SUMMARY'
	public function summary(): void {
		$this->load->model('checkout/booster_coupon');
		$result = $this->model_checkout_booster_coupon->applyPendingWelcomeCoupon($this->postedEmail());

		// CHECKOUT-007: silently resolve the durable next-order First15 flag.
		// This endpoint is loaded with every real checkout page; the model keeps
		// the registration order protected until its success page consumes the guard.
		if (empty($result['success']) && empty($this->session->data['coupon']) && $this->customer->isLogged()) {
			$automatic = $this->model_checkout_booster_coupon->applyPendingFirst15ForCustomer((int)$this->customer->getId());

			if (!empty($automatic['success'])) {
				unset($result['error']);
				$result = array_merge($result, $automatic);
			}
		}

		$this->output($result);
	}
BEFORE_COUPON_SUMMARY;
            $after = <<<'AFTER_COUPON_SUMMARY'
	public function summary(): void {
		$coupon_before = (string)($this->session->data['coupon'] ?? '');
		$this->load->model('checkout/booster_coupon');
		$result = $this->model_checkout_booster_coupon->applyPendingWelcomeCoupon($this->postedEmail());

		// CHECKOUT-007: silently resolve the durable next-order First15 flag.
		// This endpoint is loaded with every real checkout page; the model keeps
		// the registration order protected until its success page consumes the guard.
		if (empty($result['success']) && empty($this->session->data['coupon']) && $this->customer->isLogged()) {
			$automatic = $this->model_checkout_booster_coupon->applyPendingFirst15ForCustomer((int)$this->customer->getId());

			if (!empty($automatic['success'])) {
				unset($result['error']);
				$result = array_merge($result, $automatic);
			}
		}

		// CHECKOUT-009-STAGE1-20260729: report only a real coupon state change.
		// A repeated summary after First15 auto-apply therefore returns false.
		$result['mutated'] = $coupon_before !== (string)($this->session->data['coupon'] ?? '');
		$this->output($result);
	}
AFTER_COUPON_SUMMARY;

            return replace_once($content, $before, $after, 'coupon.summary_mutated');
        },
    ],
    'catalog/view/javascript/checkout-state.js' => [
        'sha256' => 'ef67a9cd578844f9f217d1eb1015fc67ea9d8a28acc291c55cbf7f2d292c8927',
        'transform' => static function (string $content): string {
            $content = replace_once(
                $content,
                <<<'BEFORE_STATE_VARS'
  var revision = 0;
  var totalsRequest = null;
  var totalsDirty = true;
  var bootstrapped = false;
BEFORE_STATE_VARS,
                <<<'AFTER_STATE_VARS'
  var revision = 0;
  var totalsRequest = null;
  var totalsDirty = true;
  var bootstrapped = false;
  // CHECKOUT-009-STAGE1-20260729: a promo mutation may wait for one active
  // address/bootstrap delivery commit, but it never advances that revision.
  var activeAddressRevision = null;
  var deferredDeliveryRequote = null;
AFTER_STATE_VARS,
                'state.transaction_vars'
            );

            $content = replace_once(
                $content,
                <<<'BEFORE_ADDRESS_SAVED'
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
BEFORE_ADDRESS_SAVED,
                <<<'AFTER_ADDRESS_SAVED'
  function AddressCommitted(result) {
    result = result || {};
    // A newer address commit quotes the current server coupon/cart state, so
    // it subsumes a requote deferred behind an older address transaction.
    deferredDeliveryRequote = null;
    var token = beginAddressTransition();
    activeAddressRevision = token;

    if (typeof window.bsCheckoutLoadShippingMethods === 'function') {
      return window.bsCheckoutLoadShippingMethods({
        autoSelect: true,
        quietAddressError: !!result.quietAddressError,
        stateRevision: token
      });
    }

    return null;
  }

  // Compatibility alias for callers outside the Stage 1 target set.
  function addressSaved(options) {
    return AddressCommitted(options);
  }
AFTER_ADDRESS_SAVED,
                'state.AddressCommitted'
            );

            $content = replace_once(
                $content,
                <<<'BEFORE_SHIPPING_SAVED'
  function shippingSaved(code, label, candidateRevision, summaryHtml) {
    var token = Number(candidateRevision);
    if (!isCurrent(token)) {
      return false;
    }

    $('#input-shipping-code').val(code || '');
    $('#input-shipping-method').val(label || code || '');

    // ST-2c.4: the write response owns the first shipping summary. Keep the
    // legacy read-only refresh only as a compatibility fallback.
    totalsDirty = !cacheSummary(summaryHtml);
    if (totalsDirty) {
      refreshTotals(token);
    }

    if (typeof window.bsCheckoutLoadPaymentMethods === 'function') {
      window.bsCheckoutLoadPaymentMethods({ stateRevision: token });
    }

    renderConfirmState();
    return true;
  }
BEFORE_SHIPPING_SAVED,
                <<<'AFTER_SHIPPING_SAVED'
  function finishAddressCommit(token) {
    if (activeAddressRevision !== token) {
      return false;
    }

    activeAddressRevision = null;
    var pending = deferredDeliveryRequote;
    deferredDeliveryRequote = null;

    if (!pending) {
      return false;
    }

    requoteDeliverySelection(pending.reason, pending.selectionPolicy);
    return true;
  }

  function shippingSaved(code, label, candidateRevision, summaryHtml) {
    var token = Number(candidateRevision);
    if (!isCurrent(token)) {
      return false;
    }

    $('#input-shipping-code').val(code || '');
    $('#input-shipping-method').val(label || code || '');

    // ST-2c.4: the write response owns the first shipping summary. Keep the
    // legacy read-only refresh only as a compatibility fallback.
    totalsDirty = !cacheSummary(summaryHtml);
    var requoteScheduled = finishAddressCommit(token);

    if (totalsDirty && !requoteScheduled) {
      refreshTotals(token);
    }

    // A deferred First15 requote now owns the next payment availability load.
    if (!requoteScheduled && typeof window.bsCheckoutLoadPaymentMethods === 'function') {
      window.bsCheckoutLoadPaymentMethods({ stateRevision: token });
    }

    renderConfirmState();
    return true;
  }
AFTER_SHIPPING_SAVED,
                'state.finish_address_before_requote'
            );

            $content = replace_once(
                $content,
                <<<'BEFORE_COUPON_STATE'
  // ST-2C-COUPON-SHIPPING-20260728: preserve the choice, re-quote and re-save
  // it after every coupon transition. This never calls an order-write route.
  function couponChanged() {
    revision += 1;
    var token = revision;
    totalsDirty = true;
    abortTotals();
    $('#input-shipping-display-text').val('');
    clearPaymentState();
    if (typeof window.bsCheckoutLoadShippingMethods === 'function') {
      return window.bsCheckoutLoadShippingMethods({autoSelect: false, resaveCurrent: true, quietAddressError: true, stateRevision: token});
    }
    return null;
  }
  function totalsChanged(source, summaryHtml) {
    if (source === 'coupon') return couponChanged();
    totalsDirty = !cacheSummary(summaryHtml || '');
    if ($('#input-shipping-code').val() && totalsDirty) refreshTotals(revision);
  }
BEFORE_COUPON_STATE,
                <<<'AFTER_COUPON_STATE'
  function hasCommittedDelivery() {
    return !!String($('#input-shipping-code').val() || '');
  }

  // ST-2C-COUPON-SHIPPING-20260728: preserve the choice, re-quote and re-save
  // it after every real coupon transition. This never calls an order-write route.
  function requoteDeliverySelection(reason, selectionPolicy) {
    // CHECKOUT-009-STAGE1-20260729: a stale address revision must never gate
    // a later promo mutation after cartChanged() or a failed quote supersedes it.
    if (activeAddressRevision !== null && !isCurrent(activeAddressRevision)) {
      activeAddressRevision = null;
      deferredDeliveryRequote = null;
    }

    if (activeAddressRevision !== null) {
      deferredDeliveryRequote = {
        reason: reason || 'unknown',
        selectionPolicy: selectionPolicy || 'preserve-current'
      };
      return null;
    }

    if (!hasCommittedDelivery()) {
      return null;
    }

    revision += 1;
    var token = revision;
    totalsDirty = true;
    abortTotals();
    $('#input-shipping-display-text').val('');
    clearPaymentState();

    if (typeof window.bsCheckoutLoadShippingMethods === 'function') {
      return window.bsCheckoutLoadShippingMethods({
        autoSelect: selectionPolicy === 'auto-select',
        resaveCurrent: selectionPolicy !== 'auto-select',
        quietAddressError: true,
        stateRevision: token
      });
    }

    return null;
  }

  var DeliverySelection = {
    requote: requoteDeliverySelection
  };

  function couponChanged() {
    return DeliverySelection.requote('coupon', 'preserve-current');
  }

  function promoResult(result, summaryHtml) {
    result = result || { kind: 'summary', mutated: false };
    totalsDirty = !cacheSummary(summaryHtml || '');

    if (!result.mutated) {
      if (hasCommittedDelivery() && totalsDirty) {
        refreshTotals(revision);
      }
      return null;
    }

    return DeliverySelection.requote('coupon:' + result.kind, 'preserve-current');
  }

  // Compatibility query adapter. Typed coupon callers use promoResult().
  function totalsChanged(source, summaryHtml) {
    totalsDirty = !cacheSummary(summaryHtml || '');
    if (hasCommittedDelivery() && totalsDirty) refreshTotals(revision);
  }
AFTER_COUPON_STATE,
                'state.typed_promo_result'
            );

            $content = replace_once(
                $content,
                <<<'BEFORE_BOOTSTRAP_QUOTE'
    if (typeof window.bsCheckoutLoadShippingMethods === 'function') {
      window.bsCheckoutLoadShippingMethods({
        autoSelect: true,
        quietAddressError: true,
        stateRevision: revision
      });
      return;
    }
BEFORE_BOOTSTRAP_QUOTE,
                <<<'AFTER_BOOTSTRAP_QUOTE'
    if (typeof window.bsCheckoutLoadShippingMethods === 'function') {
      // Track bootstrap quote/save as an address commit so an auto-applied
      // First15 mutation waits for its shipping save before requoting.
      activeAddressRevision = revision;
      deferredDeliveryRequote = null;
      window.bsCheckoutLoadShippingMethods({
        autoSelect: true,
        quietAddressError: true,
        stateRevision: revision
      });
      return;
    }
AFTER_BOOTSTRAP_QUOTE,
                'state.bootstrap_tracking'
            );

            $content = replace_once(
                $content,
                <<<'BEFORE_STATE_EXPORT'
  window.bsCheckoutState = {
    currentRevision: currentRevision,
    isCurrent: isCurrent,
    addressSaved: addressSaved,
    cartChanged: cartChanged,
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
BEFORE_STATE_EXPORT,
                <<<'AFTER_STATE_EXPORT'
  window.bsCheckoutState = {
    currentRevision: currentRevision,
    isCurrent: isCurrent,
    AddressCommitted: AddressCommitted,
    addressSaved: addressSaved,
    DeliverySelection: DeliverySelection,
    promoResult: promoResult,
    cartChanged: cartChanged,
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
AFTER_STATE_EXPORT,
                'state.public_contract'
            );

            return $content;
        },
    ],
    'catalog/view/javascript/checkout-reskin.js' => [
        'sha256' => 'abe369a24671784826f8858555c53d1957363ffa3323655bac0a0ea7fe60b4e8',
        'transform' => static function (string $content): string {
            $content = replace_once(
                $content,
                <<<'BEFORE_PROMO_RENDER'
    function render(json, options) {
      json = json || {};
      options = options || {};

      // ST-2c: server-owned free-shipping rule; never invoke the order-write endpoint.
      var freeShippingThreshold = Number(json.free_shipping_threshold);
      var freeShippingSubtotal = Number(json.free_shipping_subtotal);
      if (isFinite(freeShippingThreshold) && freeShippingThreshold > 0 && isFinite(freeShippingSubtotal) && freeShippingSubtotal >= 0) {
        window.bsCheckoutFreeShippingRule = {
          threshold: freeShippingThreshold,
          subtotal: freeShippingSubtotal
        };
      }

      var coupon = text(json.coupon || '');
      if (input) {
        input.value = coupon;
      }
      if (empty) {
        empty.hidden = !!coupon;
      }
      if (applied) {
        applied.hidden = !coupon;
      }
      if (code) {
        code.textContent = coupon ? coupon + (json.coupon_discount_text ? ' · ' + json.coupon_discount_text : '') + ' · Промокод застосовано' : '';
      }

      if (json.error) {
        setStatus(json.error, true);
      } else if (json.welcome_coupon_error) {
        setStatus(json.welcome_coupon_error, true);
      } else if (!options.quiet && json.success) {
        setStatus(json.success, false);
      } else if (!options.quiet && json.welcome_coupon_applied) {
        setStatus('Промокод ' + json.welcome_coupon_applied + ' застосовано.', false);
      }

      if (window.bsCheckoutState) {
        // PAY-001-PHASE2C-D2-COUPON-TOTALS-20260725
        window.bsCheckoutState.totalsChanged('coupon', json.summary_html || '');
      }
    }
BEFORE_PROMO_RENDER,
                <<<'AFTER_PROMO_RENDER'
    function classifyPromoResult(action, json) {
      // PAY-001-PHASE2C-D2-COUPON-TOTALS-20260725
      // CHECKOUT-009-STAGE1-20260729: the endpoint name is not the mutation.
      // summary trusts the server flag; apply/remove mutate only on success.
      return {
        kind: action,
        mutated: action === 'summary' ? json.mutated === true : !!json.success
      };
    }

    function render(json, options, result) {
      json = json || {};
      options = options || {};
      result = result || { kind: 'summary', mutated: false };

      // ST-2c: server-owned free-shipping rule; never invoke the order-write endpoint.
      var freeShippingThreshold = Number(json.free_shipping_threshold);
      var freeShippingSubtotal = Number(json.free_shipping_subtotal);
      if (isFinite(freeShippingThreshold) && freeShippingThreshold > 0 && isFinite(freeShippingSubtotal) && freeShippingSubtotal >= 0) {
        window.bsCheckoutFreeShippingRule = {
          threshold: freeShippingThreshold,
          subtotal: freeShippingSubtotal
        };
      }

      var coupon = text(json.coupon || '');
      if (input) {
        input.value = coupon;
      }
      if (empty) {
        empty.hidden = !!coupon;
      }
      if (applied) {
        applied.hidden = !coupon;
      }
      if (code) {
        code.textContent = coupon ? coupon + (json.coupon_discount_text ? ' · ' + json.coupon_discount_text : '') + ' · Промокод застосовано' : '';
      }

      if (json.error) {
        setStatus(json.error, true);
      } else if (json.welcome_coupon_error) {
        setStatus(json.welcome_coupon_error, true);
      } else if (!options.quiet && json.success) {
        setStatus(json.success, false);
      } else if (!options.quiet && json.welcome_coupon_applied) {
        setStatus('Промокод ' + json.welcome_coupon_applied + ' застосовано.', false);
      }

      if (window.bsCheckoutState && typeof window.bsCheckoutState.promoResult === 'function') {
        window.bsCheckoutState.promoResult(result, json.summary_html || '');
      }
    }
AFTER_PROMO_RENDER,
                'reskin.typed_promo_render'
            );

            $content = replace_once(
                $content,
                <<<'BEFORE_PROMO_SUCCESS'
          success: function(json) {
            render(json, options);
          },
BEFORE_PROMO_SUCCESS,
                <<<'AFTER_PROMO_SUCCESS'
          success: function(json) {
            render(json, options, classifyPromoResult(action, json || {}));
          },
AFTER_PROMO_SUCCESS,
                'reskin.action_to_renderer'
            );

            $content = replace_once(
                $content,
                <<<'BEFORE_PROMO_REFRESH'
    window.bsCheckoutRefreshPromoCouponSummary = function(options) {
      request('summary', {}, options || { quiet: true });
    };
BEFORE_PROMO_REFRESH,
                <<<'AFTER_PROMO_REFRESH'
    window.bsCheckoutRefreshPromoCouponSummary = function(options) {
      return request('summary', {}, options || { quiet: true });
    };
AFTER_PROMO_REFRESH,
                'reskin.summary_return'
            );

            return $content;
        },
    ],
    'catalog/view/template/checkout/checkout.twig' => [
        'sha256' => '563697ba196c97f14984a9fa2f82fb9cc2ab5c4847cdb4262d0432ead03709c9',
        'transform' => static function (string $content): string {
            $content = replace_once(
                $content,
                "        return window.bsCheckoutState.addressSaved();",
                "        return window.bsCheckoutState.AddressCommitted({ source: 'legacy-reset' });",
                'checkout.legacy_reset_adapter'
            );

            $content = replace_once(
                $content,
                <<<'BEFORE_CHECKOUT_AJAX'
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

  $(document).ajaxSuccess(function(event, xhr, settings) {
    var url = settings && settings.url ? settings.url : '';
    var json = xhr && xhr.responseJSON ? xhr.responseJSON : null;

    if (json && json['error'] && url.indexOf('checkout/register.save') !== -1) {
      surfaceRegisterErrors(json);
    }

    if (json && json['success'] && url.indexOf('checkout/register.save') !== -1) {
      var form = $('#form-register');
      form.data('bsLastSaved', form.data('bsPendingSignature') || form.serialize());
      $('[data-bs-register-status]').removeClass('bs-is-error').text('Дані збережено.');

      // CHECKOUT-004: refresh First15 after register.save without an order-write request.
      if (typeof window.bsCheckoutRefreshPromoCouponSummary === 'function') {
        window.bsCheckoutRefreshPromoCouponSummary({ quiet: true });
      }

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
  });
BEFORE_CHECKOUT_AJAX,
                <<<'AFTER_CHECKOUT_AJAX'
  function bsCheckoutRequestRoute(settings) {
    var url = settings && settings.url ? String(settings.url) : '';
    var match = url.match(/[?&]route=([^&]+)/);
    return match ? decodeURIComponent(match[1]).replace(/\|/g, '.') : '';
  }

  // CHECKOUT-009-STAGE1-20260729: the only address-save completion callback.
  window.bsCheckoutAddressSaveCompleted = function(result) {
    if (window.bsCheckoutState && typeof window.bsCheckoutState.AddressCommitted === 'function') {
      return window.bsCheckoutState.AddressCommitted(result || {});
    }
    return null;
  };

  $(document).ajaxSend(function(event, xhr, settings) {
    if (bsCheckoutRequestRoute(settings) === 'checkout/register.save') {
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
    if (bsCheckoutRequestRoute(settings) === 'checkout/register.save') {
      clearRegisterAutosaveLock($('#form-register'));
    }
  });

  $(document).ajaxSuccess(function(event, xhr, settings) {
    var route = bsCheckoutRequestRoute(settings);
    var json = xhr && xhr.responseJSON ? xhr.responseJSON : null;

    if (json && json['error'] && route === 'checkout/register.save') {
      surfaceRegisterErrors(json);
    }

    if (json && json['success'] && route === 'checkout/register.save') {
      var form = $('#form-register');
      form.data('bsLastSaved', form.data('bsPendingSignature') || form.serialize());
      $('[data-bs-register-status]').removeClass('bs-is-error').text('Дані збережено.');

      if (window.bsCheckoutState) {
        var checkoutState = json['checkout_state'] || null;

        if (!checkoutState || checkoutState['shipping_changed']) {
          window.bsCheckoutAddressSaveCompleted({ source: 'register', quietAddressError: true });
        } else if (checkoutState['payment_changed']) {
          window.bsCheckoutState.paymentContextSaved();
        } else {
          window.bsCheckoutState.customerSaved();
        }
      }

      // CHECKOUT-004: query First15 after the named address transaction starts.
      // If it reports mutated:true, the coordinator defers one requote until
      // the address/bootstrap shipping save commits.
      if (typeof window.bsCheckoutRefreshPromoCouponSummary === 'function') {
        window.bsCheckoutRefreshPromoCouponSummary({ quiet: true });
      }
    }

    var stockAddressRoute = route === 'checkout/shipping_address.save' || route === 'checkout/shipping_address.address';
    var handledByPinta = settings && settings.bsCheckoutAddressSource === 'pinta';

    if (json && json['success'] && stockAddressRoute && !handledByPinta) {
      window.bsCheckoutAddressSaveCompleted({ source: 'stock-address', quietAddressError: false });
    }
  });
AFTER_CHECKOUT_AJAX,
                'checkout.explicit_address_callback'
            );

            $content = replace_once(
                $content,
                <<<'BEFORE_ASSET_KEY'
{# R-13.5: bust checkout state/summary assets after mini-cart and shipping fallback fixes. #}
<script src="catalog/view/javascript/checkout-state.js?v=r135-cart-refresh-20260725"></script>
<script src="catalog/view/javascript/checkout-reskin.js?v=r135-cart-refresh-20260725"></script>
BEFORE_ASSET_KEY,
                <<<'AFTER_ASSET_KEY'
{# R-13.5 + CHECKOUT-009-STAGE1-20260729: state + reskin are one immutable deployment unit. #}
<script src="catalog/view/javascript/checkout-state.js?v=checkout009-stage1-20260729"></script>
<script src="catalog/view/javascript/checkout-reskin.js?v=checkout009-stage1-20260729"></script>
AFTER_ASSET_KEY,
                'checkout.immutable_asset_key'
            );

            return $content;
        },
    ],
    'extension/PintaNovaPoshtaCod/catalog/view/template/shipping/js_checkout_shipping_address_form.twig' => [
        'sha256' => 'e45da5bb303fcde91600bcfed71fddbbca915beb3318577ebfc034fc4961960e',
        'transform' => static function (string $content): string {
            $content = replace_once(
                $content,
                <<<'BEFORE_PINTA_AJAX_SOURCE'
          dataType: 'json',
          contentType: 'application/x-www-form-urlencoded',
          complete: function() {
BEFORE_PINTA_AJAX_SOURCE,
                <<<'AFTER_PINTA_AJAX_SOURCE'
          dataType: 'json',
          contentType: 'application/x-www-form-urlencoded',
          bsCheckoutAddressSource: 'pinta',
          complete: function() {
AFTER_PINTA_AJAX_SOURCE,
                'pinta.request_source'
            );

            $content = replace_once(
                $content,
                <<<'BEFORE_PINTA_TIMER'
              if (window.bsCheckoutLoadShippingMethods) {
                window.setTimeout(function() {
                  window.bsCheckoutLoadShippingMethods({ autoSelect: true });
                }, 250);
              }
BEFORE_PINTA_TIMER,
                <<<'AFTER_PINTA_TIMER'
              // CHECKOUT-009-STAGE1-20260729: this third-party template must
              // retain the single AddressCommitted callback after Pinta updates.
              if (typeof window.bsCheckoutAddressSaveCompleted === 'function') {
                window.bsCheckoutAddressSaveCompleted({ source: 'pinta', quietAddressError: false });
              } else if (window.bsCheckoutState && typeof window.bsCheckoutState.AddressCommitted === 'function') {
                window.bsCheckoutState.AddressCommitted({ source: 'pinta', quietAddressError: false });
              }
AFTER_PINTA_TIMER,
                'pinta.single_address_callback'
            );

            return $content;
        },
    ],
];

$relativePaths = array_keys($targets);
$source = [];
$patched = [];
$alreadyApplied = 0;

foreach ($targets as $relativePath => $target) {
    $absolutePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (!is_file($absolutePath)) {
        fail_patch('target_missing=' . $relativePath);
    }

    $content = file_get_contents($absolutePath);

    if ($content === false) {
        fail_patch('target_read_failed=' . $relativePath);
    }

    $source[$relativePath] = $content;

    if (strpos($content, PATCH_MARKER) !== false) {
        $alreadyApplied++;
    }
}

if ($alreadyApplied === count($targets)) {
    log_line('already_applied', 'yes');
    log_line('done', 'ok');

    if (!$dryRun) {
        @unlink(__FILE__);
        log_line('self_delete', is_file(__FILE__) ? 'failed' : 'ok');
    }

    exit(0);
}

if ($alreadyApplied !== 0) {
    fail_patch('partial_marker_state=' . $alreadyApplied . '/' . count($targets));
}

foreach ($targets as $relativePath => $target) {
    $actualHash = hash('sha256', $source[$relativePath]);

    if (!hash_equals($target['sha256'], $actualHash)) {
        fail_patch('sha256_mismatch[' . $relativePath . ']=' . $actualHash);
    }

    $patched[$relativePath] = $target['transform']($source[$relativePath]);

    if (strpos($patched[$relativePath], PATCH_MARKER) === false) {
        fail_patch('post_marker_missing=' . $relativePath);
    }

    log_line('preflight', $relativePath);
    log_line('before_sha256', $actualHash);
    log_line('after_sha256', hash('sha256', $patched[$relativePath]));
}

if ($dryRun) {
    log_line('changed_files', (string)count($patched));
    log_line('write_performed', 'no');
    log_line('done', 'ok');
    exit(0);
}

$backupRoot = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . PATCH_ID . '-' . gmdate('Ymd-His');

foreach ($relativePaths as $relativePath) {
    $backup = $backupRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    ensure_parent_directory($backup);

    if (!copy($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath), $backup)) {
        fail_patch('backup_failed=' . $relativePath);
    }

    log_line('backup', $backup);
}

try {
    foreach ($patched as $relativePath => $content) {
        $absolutePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        write_atomic($absolutePath, $content);
        log_line('changed', $relativePath);
    }

    $couponPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, 'catalog/controller/checkout/coupon.php');
    [$lintStatus, $lintOutput] = php_lint($couponPath);
    log_line('php_lint', $lintOutput);

    if ($lintStatus !== 0) {
        throw new RuntimeException('php_lint_failed=catalog/controller/checkout/coupon.php');
    }

    foreach ($patched as $relativePath => $content) {
        $absolutePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $actual = file_get_contents($absolutePath);

        if ($actual === false || !hash_equals(hash('sha256', $content), hash('sha256', $actual))) {
            throw new RuntimeException('post_write_hash_failed=' . $relativePath);
        }
    }
} catch (Throwable $error) {
    $restoreFailures = restore_all($backupRoot, $root, $relativePaths);

    if ($restoreFailures) {
        fail_patch($error->getMessage() . '; rollback_failed=' . implode(',', $restoreFailures), 3);
    }

    fail_patch($error->getMessage() . '; rollback=restored', 2);
}

log_line('backup_root', $backupRoot);
log_line('changed_files', (string)count($patched));
log_line('done', 'ok');
@unlink(__FILE__);
log_line('self_delete', is_file(__FILE__) ? 'failed' : 'ok');

