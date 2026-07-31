<?php
declare(strict_types=1);

/**
 * ST-2c — Recalculate Nova Poshta free-shipping eligibility after coupon changes.
 *
 * Verified live source (2026-07-28): both the coupon endpoint and Pinta quote
 * used cart->getSubTotal(), so a coupon could lower the payable amount below
 * the threshold while the UI and selected session quote stayed free.
 *
 * Scope: coupon response, Pinta display-only quote basis, and stock checkout
 * state re-quote. No DB, payment provider, order-write or SimpleCheckout changes.
 *
 * Run from ~/public_html:
 *   php ST-2c_coupon_shipping_threshold_refresh_20260728.php
 * Optional non-writing validation:
 *   php ST-2c_coupon_shipping_threshold_refresh_20260728.php --dry-run
 */

$patchId = 'ST-2c_coupon_shipping_threshold_refresh_20260728';
$marker = 'ST-2C-COUPON-SHIPPING-20260728';
$root = getcwd() ?: __DIR__;
$dryRun = in_array('--dry-run', $argv ?? [], true);
$backupDir = $root . '/_patch_backups/' . $patchId . '-' . date('Ymd-His');

$files = [
    'catalog/controller/checkout/coupon.php' => '592BA0B0C99A7218CE252DB76994894D080A17A83B3A1AA5C0E6529BF030CE86',
    'catalog/view/javascript/checkout-state.js' => '23378071030674F5CCDD34B039CDE51CD2D5C40ACC07ED2012BD2BB67A49D025',
    'catalog/view/template/checkout/shipping_method.twig' => '5264DD70EF2E9F190207F768C884322CA444018F9595C8DA1FDDE558DABD0845',
    'extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php' => '766C70E4963541AB10585C783CD5DBC52E7070CD95FED973BEC36D7194625D34',
];

function st2c_coupon_out(string $message): void {
    echo '[' . date('c') . '] ' . $message . PHP_EOL;
}

function st2c_coupon_fail(string $message): void {
    st2c_coupon_out('error=' . $message);
    st2c_coupon_out('done=failed');
    exit(1);
}

function st2c_coupon_normalize(string $content): string {
    return str_replace(["\r\n", "\r"], "\n", $content);
}

function st2c_coupon_restore_eol(string $content, string $eol): string {
    return $eol === "\r\n" ? str_replace("\n", "\r\n", $content) : $content;
}

function st2c_coupon_replace_once(string $content, string $search, string $replace, string $label): string {
    $count = substr_count($content, $search);

    if ($count !== 1) {
        st2c_coupon_fail('anchor_' . $label . '_count=' . $count);
    }

    return str_replace($search, $replace, $content);
}

function st2c_coupon_php_lint(string $path): void {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path);
    exec($command, $output, $code);
    st2c_coupon_out('php_lint=' . ($code === 0 ? 'ok' : 'failed') . ' file=' . $path);

    if ($code !== 0) {
        st2c_coupon_fail('php_lint_failed');
    }
}

st2c_coupon_out('patch=' . $patchId);
st2c_coupon_out('cwd=' . $root);
st2c_coupon_out('time=' . date('c'));

$original = [];
$eols = [];
$alreadyApplied = true;

foreach ($files as $relative => $expectedHash) {
    $path = $root . '/' . $relative;

    if (!is_file($path)) {
        st2c_coupon_fail('missing_file=' . $relative);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        st2c_coupon_fail('cannot_read=' . $relative);
    }

    $original[$relative] = $content;
    $eols[$relative] = str_contains($content, "\r\n") ? "\r\n" : "\n";
    $alreadyApplied = $alreadyApplied && str_contains($content, $marker);
}

if ($alreadyApplied) {
    st2c_coupon_out('already_applied=yes');
    st2c_coupon_out('done=ok');
    exit(0);
}

foreach ($original as $relative => $content) {
    if (str_contains($content, $marker)) {
        st2c_coupon_fail('partial_marker_state=' . $relative);
    }

    $actualHash = strtoupper(hash('sha256', $content));

    if ($actualHash !== $files[$relative]) {
        st2c_coupon_fail('sha256_mismatch=' . $relative . ' expected=' . $files[$relative] . ' actual=' . $actualHash);
    }
}

$next = [];
foreach ($original as $relative => $content) {
    $next[$relative] = st2c_coupon_normalize($content);
}

$couponFile = 'catalog/controller/checkout/coupon.php';
$next[$couponFile] = st2c_coupon_replace_once(
    $next[$couponFile],
    "\t\t\t'free_shipping_subtotal' => \$this->cartSubtotalUah(),",
    "\t\t\t'free_shipping_subtotal' => \$this->totalToUah((float)(\$summary['total_value'] ?? 0.0)),",
    'coupon_response_payable_total'
);
$next[$couponFile] = st2c_coupon_replace_once(
    $next[$couponFile],
    <<<'PHP'
	// ST-2c: expose the authoritative shipping threshold and pre-discount subtotal.
	// This response-only endpoint is the existing safe totals-refresh path.
	private function freeShippingThresholdUah(): float {
PHP,
    <<<'PHP'
	// ST-2C-COUPON-SHIPPING-20260728: expose the post-discount payable amount.
	// The shipping model uses the same total basis; neither path creates an order.
	private function freeShippingThresholdUah(): float {
PHP,
    'coupon_threshold_comment'
);
$next[$couponFile] = st2c_coupon_replace_once(
    $next[$couponFile],
    <<<'PHP'
	private function cartSubtotalUah(): float {
		$subtotal = (float)$this->cart->getSubTotal();
		$currency = (string)$this->config->get('config_currency');
		if ($currency !== '' && $currency !== 'UAH' && $this->currency->has($currency) && $this->currency->has('UAH')) {
			return (float)$this->currency->convert($subtotal, $currency, 'UAH');
		}
		return $subtotal;
	}
PHP,
    <<<'PHP'
	private function totalToUah(float $total): float {
		$currency = (string)$this->config->get('config_currency');
		if ($currency !== '' && $currency !== 'UAH' && $this->currency->has($currency) && $this->currency->has('UAH')) {
			return (float)$this->currency->convert($total, $currency, 'UAH');
		}
		return $total;
	}
PHP,
    'coupon_total_to_uah'
);
$next[$couponFile] = st2c_coupon_replace_once(
    $next[$couponFile],
    "\t\treturn ['html' => \$html, 'totals' => \$rows, 'total_text' => \$rows ? (string)end(\$rows)['text'] : ''];",
    "\t\treturn ['html' => \$html, 'totals' => \$rows, 'total_text' => \$rows ? (string)end(\$rows)['text'] : '', 'total_value' => \$total];",
    'coupon_summary_total_value'
);

$pintaFile = 'extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php';
$next[$pintaFile] = st2c_coupon_replace_once(
    $next[$pintaFile],
    <<<'PHP'
    private function getBoosterCartTotalUah() {
        $total = (float)$this->cart->getSubTotal();
        $currency = (string)$this->config->get('config_currency');

        if ($currency && $currency !== 'UAH' && $this->currency->has($currency) && $this->currency->has('UAH')) {
            return (float)$this->currency->convert($total, $currency, 'UAH');
        }

        return $total;
    }
PHP,
    <<<'PHP'
    // ST-2C-COUPON-SHIPPING-20260728: free shipping follows the payable cart
    // after an active coupon/discount. Pinta remains display-only (cost = 0).
    private function getBoosterCartTotalUah() {
        $this->load->model('checkout/booster_coupon');
        $this->model_checkout_booster_coupon->prepareCouponTotal();
        $this->load->model('checkout/cart');

        $totals = [];
        $taxes = $this->cart->getTaxes();
        $total = 0;
        ($this->model_checkout_cart->getTotals)($totals, $taxes, $total);

        $currency = (string)$this->config->get('config_currency');

        if ($currency && $currency !== 'UAH' && $this->currency->has($currency) && $this->currency->has('UAH')) {
            return (float)$this->currency->convert((float)$total, $currency, 'UAH');
        }

        return (float)$total;
    }
PHP,
    'pinta_payable_total'
);

$stateFile = 'catalog/view/javascript/checkout-state.js';
$next[$stateFile] = st2c_coupon_replace_once(
    $next[$stateFile],
    <<<'JS'
  function totalsChanged(source, summaryHtml) {
    // PAY-001-PHASE2C-D2-COUPON-TOTALS-20260725:
    // checkout/coupon already returns the authoritative rendered summary.
    // Consume it directly and keep the payment gate refresh, without issuing
    // a duplicate checkout/confirm GET for the same coupon transition.
    totalsDirty = !cacheSummary(summaryHtml || '');
    if ($('#input-shipping-code').val()) {
      if (totalsDirty) {
        refreshTotals(revision);
      }
      // PAY-001 Phase 2c: coupon/cart changes must re-evaluate the server-owned
      // payable threshold and preorder gate, not only repaint the totals.
      if (typeof window.bsCheckoutLoadPaymentMethods === 'function') {
        window.bsCheckoutLoadPaymentMethods({ stateRevision: revision });
      }
    }
  }
JS,
    <<<'JS'
  // ST-2C-COUPON-SHIPPING-20260728: retain the selected delivery code, but
  // request a fresh quote and save it back to the session after any coupon
  // transition. This is a quote/save sequence only; no order-write route runs.
  function couponChanged() {
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

  function totalsChanged(source, summaryHtml) {
    if (source === 'coupon') {
      return couponChanged();
    }

    totalsDirty = !cacheSummary(summaryHtml || '');
    if ($('#input-shipping-code').val() && totalsDirty) {
      refreshTotals(revision);
    }
  }
JS,
    'state_coupon_requote'
);

$shippingTwig = 'catalog/view/template/checkout/shipping_method.twig';
$next[$shippingTwig] = st2c_coupon_replace_once(
    $next[$shippingTwig],
    <<<'TWIG'
    } else if (current) {
      status('Доставку обрано.');

      if (window.bsCheckoutState) {
        window.bsCheckoutState.shippingSaved(current, currentQuote ? currentQuote.label : $('#input-shipping-method').val(), options.stateRevision);
      } else if (window.bsCheckoutLoadPaymentMethods) {
        window.bsCheckoutLoadPaymentMethods();
      }
TWIG,
    <<<'TWIG'
    } else if (current) {
      // ST-2C-COUPON-SHIPPING-20260728: quote() refreshed the selected method.
      // Re-save it so the session and its display-only tariff cannot remain stale.
      if (options && options.resaveCurrent && currentQuote) {
        saveShipping(currentQuote.code, currentQuote.label, options.stateRevision);
        return;
      }

      status('Доставку обрано.');

      if (window.bsCheckoutState) {
        window.bsCheckoutState.shippingSaved(current, currentQuote ? currentQuote.label : $('#input-shipping-method').val(), options.stateRevision);
      } else if (window.bsCheckoutLoadPaymentMethods) {
        window.bsCheckoutLoadPaymentMethods();
      }
TWIG,
    'shipping_template_resave_current'
);

foreach ($next as $relative => $content) {
    if (!str_contains($content, $marker)) {
        st2c_coupon_fail('postcheck_marker_missing=' . $relative);
    }
}

if ($dryRun) {
    st2c_coupon_out('dry_run=ok');
    st2c_coupon_out('done=ok');
    exit(0);
}

if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
    st2c_coupon_fail('cannot_create_backup_dir');
}

foreach ($original as $relative => $content) {
    $backupPath = $backupDir . '/' . $relative;
    $backupParent = dirname($backupPath);

    if (!is_dir($backupParent) && !mkdir($backupParent, 0755, true) && !is_dir($backupParent)) {
        st2c_coupon_fail('cannot_create_backup_parent=' . $relative);
    }

    if (!copy($root . '/' . $relative, $backupPath)) {
        st2c_coupon_fail('cannot_backup=' . $relative);
    }
}
st2c_coupon_out('backup=' . str_replace($root . '/', '', $backupDir));

$written = [];
foreach ($next as $relative => $content) {
    $path = $root . '/' . $relative;
    $output = st2c_coupon_restore_eol($content, $eols[$relative]);

    if (file_put_contents($path, $output) === false) {
        foreach ($written as $restoreRelative) {
            copy($backupDir . '/' . $restoreRelative, $root . '/' . $restoreRelative);
        }
        st2c_coupon_fail('cannot_write=' . $relative);
    }

    $written[] = $relative;
    st2c_coupon_out('changed=' . $relative);
}

try {
    st2c_coupon_php_lint($root . '/catalog/controller/checkout/coupon.php');
    st2c_coupon_php_lint($root . '/extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php');
} catch (Throwable $exception) {
    foreach ($written as $restoreRelative) {
        copy($backupDir . '/' . $restoreRelative, $root . '/' . $restoreRelative);
    }
    st2c_coupon_fail('rollback_after_validation=' . $exception->getMessage());
}

st2c_coupon_out('done=ok');
@unlink(__FILE__);
