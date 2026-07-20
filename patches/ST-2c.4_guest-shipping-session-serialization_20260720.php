<?php
/**
 * ST-2c.4 — guest shipping session serialization and atomic summary.
 *
 * Root cause: guest register/coupon requests were not all serialized with the
 * shipping write, while the summary was fetched by a separate request. A late
 * guest session write could therefore remove the saved shipping method before
 * checkout/confirm rendered totals. This patch queues coupon requests with the
 * existing checkout chain and returns the read-only confirm summary from the
 * same shipping_method.save request that stores the selected quote.
 *
 * Also removes the duplicated delivery-address line from the Receiver card.
 *
 * DB changes: none.
 * Rollback: restore all five files from
 * _patch_backups/ST-2c.4_guest-shipping-session-serialization_20260720-<ts>/.
 */

declare(strict_types=1);

$patch = basename(__FILE__, '.php');
$root = __DIR__;

$files = [
    'catalog/controller/checkout/shipping_method.php' => [
        'hash' => '9B52FE4A1DC3E69EF4070F944A4F0E0EA47829C62F7AAF4C123625DCA5AEE709',
        'marker' => 'ST-2c.4: render totals inside the shipping write request.',
    ],
    'catalog/view/javascript/checkout-state.js' => [
        'hash' => 'B189A924756ACBA9E037D23514821C83E1E03213664FD637A7C353A6E4C7FD3C',
        'marker' => 'ST-2c.4: the write response owns the first shipping summary.',
    ],
    'catalog/view/javascript/checkout-reskin.js' => [
        'hash' => '743068A97F0A0D8DF34A6A53F29BA930391EB77E644598E960B340DC403B0CED',
        'marker' => 'ST-2c.4: serialize coupon session writes with checkout state writes.',
    ],
    'catalog/view/template/checkout/shipping_method.twig' => [
        'hash' => '7360A7F5865D4A93AA8CC37D6F478DE0E64B96358C9997C3FF9A4E8091B85091',
        'marker' => "json['summary_html'] || ''",
    ],
    'catalog/view/template/checkout/checkout.twig' => [
        'hash' => 'B8CF36BB28C2EF913777ABD9188DF1048EB704EA16B19E9B6063B0F1F4580BDE',
        'marker' => 'checkout-state.js?v=st2c4-20260720',
    ],
];

function st2c4_fail(string $message): void {
    fwrite(STDERR, 'error=' . $message . PHP_EOL);
    exit(1);
}

function st2c4_replace_once(string $source, string $old, string $new, string $label): string {
    $count = substr_count($source, $old);

    if ($count !== 1) {
        st2c4_fail('anchor=' . $label . '; count=' . $count . '; expected=1');
    }

    return str_replace($old, $new, $source);
}

function st2c4_restore(array $targets, array $backups): void {
    foreach ($targets as $relative => $target) {
        if (isset($backups[$relative]) && is_file($backups[$relative])) {
            @copy($backups[$relative], $target);
        }
    }
}

$targets = [];
$sources = [];
$applied = [];

foreach ($files as $relative => $spec) {
    $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (!is_file($target)) {
        st2c4_fail('target_missing:' . $relative);
    }

    $source = file_get_contents($target);

    if ($source === false) {
        st2c4_fail('target_read_failed:' . $relative);
    }

    $targets[$relative] = $target;
    $sources[$relative] = $source;
    $applied[$relative] = strpos($source, $spec['marker']) !== false;
}

$appliedCount = count(array_filter($applied));

if ($appliedCount === count($files)) {
    echo 'already_applied=yes' . PHP_EOL;
    exit(0);
}

if ($appliedCount !== 0) {
    st2c4_fail('partial_apply_detected:' . implode(',', array_keys(array_filter($applied))));
}

foreach ($files as $relative => $spec) {
    $actualHash = strtoupper((string)hash_file('sha256', $targets[$relative]));

    if ($actualHash !== $spec['hash']) {
        st2c4_fail('sha256_mismatch:' . $relative . '; actual=' . $actualHash);
    }
}

$updated = $sources;
$controller = 'catalog/controller/checkout/shipping_method.php';
$stateJs = 'catalog/view/javascript/checkout-state.js';
$reskinJs = 'catalog/view/javascript/checkout-reskin.js';
$shippingTwig = 'catalog/view/template/checkout/shipping_method.twig';
$checkoutTwig = 'catalog/view/template/checkout/checkout.twig';

$updated[$controller] = st2c4_replace_once(
    $updated[$controller],
    <<<'OLD'
			// Clear payment methods
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
OLD
,
    <<<'NEW'
			// Clear payment methods
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);

			// ST-2c.4: render totals inside the shipping write request. This keeps
			// the selected quote and its summary on one server-side session boundary.
			$json['summary_html'] = $this->load->controller('checkout/confirm');
NEW
,
    'atomic_shipping_summary'
);

$updated[$stateJs] = st2c4_replace_once(
    $updated[$stateJs],
    <<<'OLD'
  function refreshTotals(candidateRevision) {
OLD
,
    <<<'NEW'
  function cacheSummary(html) {
    var summaryHtml = extractSummary(html);

    if (!summaryHtml || typeof window.bsCheckoutUpdateCachedSummaryHtml !== 'function') {
      return false;
    }

    window.bsCheckoutUpdateCachedSummaryHtml(summaryHtml);
    return true;
  }

  function refreshTotals(candidateRevision) {
NEW
,
    'shared_summary_cache_helper'
);

$updated[$stateJs] = st2c4_replace_once(
    $updated[$stateJs],
    <<<'OLD'
        var summaryHtml = extractSummary(html);
        if (summaryHtml && typeof window.bsCheckoutUpdateCachedSummaryHtml === 'function') {
          window.bsCheckoutUpdateCachedSummaryHtml(summaryHtml);
        }
OLD
,
    <<<'NEW'
        cacheSummary(html);
NEW
,
    'reuse_summary_cache_helper'
);

$updated[$stateJs] = st2c4_replace_once(
    $updated[$stateJs],
    <<<'OLD'
  function shippingSaved(code, label, candidateRevision) {
    var token = Number(candidateRevision);
    if (!isCurrent(token)) {
      return false;
    }

    $('#input-shipping-code').val(code || '');
    $('#input-shipping-method').val(label || code || '');
    totalsDirty = true;
    refreshTotals(token);
OLD
,
    <<<'NEW'
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
NEW
,
    'consume_atomic_shipping_summary'
);

$updated[$shippingTwig] = st2c4_replace_once(
    $updated[$shippingTwig],
    'window.bsCheckoutState.shippingSaved(code, label || code, stateRevision);',
    "window.bsCheckoutState.shippingSaved(code, label || code, stateRevision, json['summary_html'] || '');",
    'pass_shipping_summary_to_coordinator'
);

$updated[$reskinJs] = st2c4_replace_once(
    $updated[$reskinJs],
    <<<'OLD'
      busy = true;
      $.ajax({
        url: 'index.php?route=checkout/coupon.' + action,
        type: 'post',
        dataType: 'json',
        data: payload(data),
        success: function(json) {
          render(json, options);
        },
        error: function() {
          setStatus('Не вдалося оновити промокод. Спробуйте ще раз.', true);
        },
        complete: function() {
          busy = false;
        }
      });
OLD
,
    <<<'NEW'
      busy = true;

      var send = function() {
        // OpenCart's global Chain advances only through jqxhr.done(). Return an
        // always-resolved wrapper so a failed coupon request cannot freeze the
        // following shipping/payment steps.
        var queueStep = $.Deferred();

        $.ajax({
          url: 'index.php?route=checkout/coupon.' + action,
          type: 'post',
          dataType: 'json',
          data: payload(data),
          success: function(json) {
            render(json, options);
          },
          error: function() {
            setStatus('Не вдалося оновити промокод. Спробуйте ще раз.', true);
          },
          complete: function() {
            busy = false;
            queueStep.resolve();
          }
        });

        return queueStep.promise();
      };

      // ST-2c.4: serialize coupon session writes with checkout state writes.
      if (window.chain && typeof chain.attach === 'function') {
        return chain.attach(send);
      }

      return send();
NEW
,
    'serialize_coupon_requests'
);

$updated[$reskinJs] = st2c4_replace_once(
    $updated[$reskinJs],
    <<<'OLD'
          '<div class="bs-co-recap-sub" data-co-receiver-address></div>' +
OLD
,
    '',
    'remove_receiver_address_recap_markup'
);

$updated[$reskinJs] = st2c4_replace_once(
    $updated[$reskinJs],
    <<<'OLD'
    var addressLine = recap.querySelector('[data-co-receiver-address]');
    var selectNow = savedAddressSelect();
    var fullOption = selectNow && selectNow.selectedOptions[0] ? selectNow.selectedOptions[0].dataset.coFullText : '';
    setText(addressLine, fullOption || selectedSavedAddressText());
OLD
,
    '',
    'remove_receiver_address_recap_writer'
);

$updated[$checkoutTwig] = st2c4_replace_once(
    $updated[$checkoutTwig],
    '<script src="catalog/view/javascript/checkout-state.js?v=st2c3b-20260720"></script>',
    '<script src="catalog/view/javascript/checkout-state.js?v=st2c4-20260720"></script>',
    'checkout_state_cache_buster'
);

$updated[$checkoutTwig] = st2c4_replace_once(
    $updated[$checkoutTwig],
    '<script src="catalog/view/javascript/checkout-reskin.js?v=st2c3-20260719"></script>',
    '<script src="catalog/view/javascript/checkout-reskin.js?v=st2c4-20260720"></script>',
    'checkout_reskin_cache_buster'
);

foreach ($files as $relative => $spec) {
    if (strpos($updated[$relative], $spec['marker']) === false) {
        st2c4_fail('postcheck_missing:' . $relative . ':' . $spec['marker']);
    }
}

if (strpos($updated[$reskinJs], 'data-co-receiver-address') !== false) {
    st2c4_fail('postcheck_old_receiver_address_recap_present');
}

if (strpos($updated[$reskinJs], 'queueStep.resolve();') === false) {
    st2c4_fail('postcheck_coupon_queue_settlement_missing');
}

$timestamp = gmdate('Ymd_His');
$backupDir = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . $patch . '-' . $timestamp;
$backups = [];

foreach ($targets as $relative => $target) {
    $backup = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0775, true) && !is_dir(dirname($backup))) {
        st2c4_fail('backup_directory_create_failed:' . $relative);
    }

    if (!copy($target, $backup)) {
        st2c4_fail('backup_copy_failed:' . $relative);
    }

    $backups[$relative] = $backup;
}

foreach ($targets as $relative => $target) {
    if (file_put_contents($target, $updated[$relative]) === false) {
        st2c4_restore($targets, $backups);
        st2c4_fail('target_write_failed:' . $relative . '; restored=yes');
    }
}

$lintOutput = [];
$lintCode = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($targets[$controller]) . ' 2>&1', $lintOutput, $lintCode);

if ($lintCode !== 0) {
    st2c4_restore($targets, $backups);
    st2c4_fail('php_l_failed; restored=yes; detail=' . implode(' | ', $lintOutput));
}

echo 'cwd=' . $root . PHP_EOL;
echo 'time=' . gmdate('c') . PHP_EOL;
echo 'backup=' . $backupDir . PHP_EOL;

foreach (array_keys($targets) as $relative) {
    echo 'changed=' . $relative . PHP_EOL;
}

echo 'php_l=ok' . PHP_EOL;
echo 'done=ok' . PHP_EOL;

@unlink(__FILE__);
