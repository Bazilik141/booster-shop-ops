<?php
declare(strict_types=1);

/**
 * ST-2c — Recalculate Nova Poshta free-shipping eligibility after coupon changes.
 * Scope: coupon response, Pinta display-only quote and stock checkout state only.
 * No DB, payment, order-write or SimpleCheckout changes.
 * Run from ~/public_html: php ST-2c_coupon_shipping_threshold_refresh_final_20260728.php
 * Optional validation: php ST-2c_coupon_shipping_threshold_refresh_final_20260728.php --dry-run
 */

$id = 'ST-2c_coupon_shipping_threshold_refresh_final_20260728';
$marker = 'ST-2C-COUPON-SHIPPING-20260728';
$root = getcwd() ?: __DIR__;
$dry = in_array('--dry-run', $argv ?? [], true);
$backup = $root . '/_patch_backups/' . $id . '-' . date('Ymd-His');
$files = [
    'catalog/controller/checkout/coupon.php' => '592BA0B0C99A7218CE252DB76994894D080A17A83B3A1AA5C0E6529BF030CE86',
    'catalog/view/javascript/checkout-state.js' => '23378071030674F5CCDD34B039CDE51CD2D5C40ACC07ED2012BD2BB67A49D025',
    'catalog/view/template/checkout/shipping_method.twig' => '5264DD70EF2E9F190207F768C884322CA444018F9595C8DA1FDDE558DABD0845',
    'extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php' => '766C70E4963541AB10585C783CD5DBC52E7070CD95FED973BEC36D7194625D34',
];

function st2cf_out(string $message): void { echo '[' . date('c') . '] ' . $message . PHP_EOL; }
function st2cf_fail(string $message): void { st2cf_out('error=' . $message); st2cf_out('done=failed'); exit(1); }
function st2cf_normalize(string $content): string { return str_replace(["\r\n", "\r"], "\n", $content); }
function st2cf_eol(string $content, string $eol): string { return $eol === "\r\n" ? str_replace("\n", "\r\n", $content) : $content; }
function st2cf_replace(string $content, string $pattern, string $replacement, string $name): string {
    $count = 0;
    $result = preg_replace_callback($pattern, static fn(): string => $replacement, $content, 1, $count);
    if ($result === null || $count !== 1) st2cf_fail('anchor_' . $name . '_count=' . $count);
    return $result;
}
function st2cf_lint(string $path): bool {
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path), $ignored, $code);
    st2cf_out('php_lint=' . ($code === 0 ? 'ok' : 'failed') . ' file=' . $path);
    return $code === 0;
}

st2cf_out('patch=' . $id); st2cf_out('cwd=' . $root); st2cf_out('time=' . date('c'));
$source = []; $eol = []; $allMarked = true;
foreach ($files as $file => $hash) {
    $path = $root . '/' . $file;
    if (!is_file($path)) st2cf_fail('missing_file=' . $file);
    $content = file_get_contents($path);
    if ($content === false) st2cf_fail('cannot_read=' . $file);
    $source[$file] = $content;
    $eol[$file] = str_contains($content, "\r\n") ? "\r\n" : "\n";
    $allMarked = $allMarked && str_contains($content, $marker);
}
if ($allMarked) { st2cf_out('already_applied=yes'); st2cf_out('done=ok'); exit(0); }
foreach ($source as $file => $content) {
    if (str_contains($content, $marker)) st2cf_fail('partial_marker_state=' . $file);
    $actual = strtoupper(hash('sha256', $content));
    if ($actual !== $files[$file]) st2cf_fail('sha256_mismatch=' . $file . ' expected=' . $files[$file] . ' actual=' . $actual);
    $source[$file] = st2cf_normalize($content);
}

$next = $source;
$coupon = 'catalog/controller/checkout/coupon.php';
$next[$coupon] = st2cf_replace($next[$coupon], "~^\\t\\t\\t'free_shipping_subtotal' => \\$this->cartSubtotalUah\(\),$~m", "\\t\\t\\t'free_shipping_subtotal' => \\$this->totalToUah((float)(\\$summary['total_value'] ?? 0.0)),", 'coupon_response');
$next[$coupon] = st2cf_replace($next[$coupon], "~^\\t// ST-2c: expose the authoritative shipping threshold and pre-discount subtotal\\.\\n\\t// This response-only endpoint is the existing safe totals-refresh path\\.$~m", "\\t// ST-2C-COUPON-SHIPPING-20260728: free-shipping eligibility uses the post-discount payable amount.", 'coupon_comment');
$next[$coupon] = st2cf_replace($next[$coupon], "~\\tprivate function cartSubtotalUah\(\): float \{\\n\\t\\t\\$subtotal = \(float\)\\$this->cart->getSubTotal\(\);\\n\\t\\t\\$currency = \(string\)\\$this->config->get\('config_currency'\);\\n\\t\\tif \(\\$currency !== '' && \\$currency !== 'UAH' && \\$this->currency->has\(\\$currency\) && \\$this->currency->has\('UAH'\)\) \{\\n\\t\\t\\treturn \(float\)\\$this->currency->convert\(\\$subtotal, \\$currency, 'UAH'\);\\n\\t\\t\}\\n\\t\\treturn \\$subtotal;\\n\\t\}~", <<<'PHP'
	private function totalToUah(float $total): float {
		$currency = (string)$this->config->get('config_currency');
		if ($currency !== '' && $currency !== 'UAH' && $this->currency->has($currency) && $this->currency->has('UAH')) {
			return (float)$this->currency->convert($total, $currency, 'UAH');
		}
		return $total;
	}
PHP, 'coupon_total_basis');
$next[$coupon] = st2cf_replace($next[$coupon], "~^\\t\\treturn \['html' => \\$html, 'totals' => \\$rows, 'total_text' => \\$rows \? \(string\)end\(\\$rows\)\['text'\] : ''\];$~m", "\\t\\treturn ['html' => \\$html, 'totals' => \\$rows, 'total_text' => \\$rows ? (string)end(\\$rows)['text'] : '', 'total_value' => \\$total];", 'coupon_total_value');

$pinta = 'extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php';
$next[$pinta] = st2cf_replace($next[$pinta], "~    private function getBoosterCartTotalUah\(\) \{\\n        \\$total = \(float\)\\$this->cart->getSubTotal\(\);\\n        \\$currency = \(string\)\\$this->config->get\('config_currency'\);\\n\\n        if \(\\$currency && \\$currency !== 'UAH' && \\$this->currency->has\(\\$currency\) && \\$this->currency->has\('UAH'\)\) \{\\n            return \(float\)\\$this->currency->convert\(\\$total, \\$currency, 'UAH'\);\\n        \}\\n\\n        return \\$total;\\n    \}~", <<<'PHP'
    // ST-2C-COUPON-SHIPPING-20260728: keep Pinta display-only, but evaluate
    // the free-shipping threshold after the active coupon/discount.
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
PHP, 'pinta_total_basis');

$state = 'catalog/view/javascript/checkout-state.js';
$next[$state] = st2cf_replace($next[$state], "~  function totalsChanged\(source, summaryHtml\) \{.*?\\n  \}\\n\\n  function bootstrap\(\) \{~s", <<<'JS'
  // ST-2C-COUPON-SHIPPING-20260728: keep the selected code, re-quote it and
  // save the fresh display-only tariff back to the session. No order is created.
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
    if (source === 'coupon') return couponChanged();
    totalsDirty = !cacheSummary(summaryHtml || '');
    if ($('#input-shipping-code').val() && totalsDirty) refreshTotals(revision);
  }

  function bootstrap() {
JS, 'state_coupon_requote');

$twig = 'catalog/view/template/checkout/shipping_method.twig';
$next[$twig] = st2cf_replace($next[$twig], "~    \} else if \(current\) \{\\n      status\('Доставку обрано\\.'\);~u", <<<'TWIG'
    } else if (current) {
      // ST-2C-COUPON-SHIPPING-20260728: quote() refreshed the same method;
      // persist it so session tariff and summary cannot stay stale.
      if (options && options.resaveCurrent && currentQuote) {
        saveShipping(currentQuote.code, currentQuote.label, options.stateRevision);
        return;
      }

      status('Доставку обрано.');
TWIG, 'shipping_resave_current');

foreach ($next as $file => $content) if (!str_contains($content, $marker)) st2cf_fail('postcheck_marker_missing=' . $file);
if ($dry) { st2cf_out('dry_run=ok'); st2cf_out('done=ok'); exit(0); }
if (!is_dir($backup) && !mkdir($backup, 0755, true) && !is_dir($backup)) st2cf_fail('cannot_create_backup_dir');
foreach ($source as $file => $content) {
    $to = $backup . '/' . $file;
    if (!is_dir(dirname($to)) && !mkdir(dirname($to), 0755, true) && !is_dir(dirname($to))) st2cf_fail('cannot_create_backup_parent=' . $file);
    if (!copy($root . '/' . $file, $to)) st2cf_fail('cannot_backup=' . $file);
}
st2cf_out('backup=' . str_replace($root . '/', '', $backup));
$written = [];
foreach ($next as $file => $content) {
    if (file_put_contents($root . '/' . $file, st2cf_eol($content, $eol[$file])) === false) {
        foreach ($written as $restore) copy($backup . '/' . $restore, $root . '/' . $restore);
        st2cf_fail('cannot_write=' . $file);
    }
    $written[] = $file; st2cf_out('changed=' . $file);
}
if (!st2cf_lint($root . '/catalog/controller/checkout/coupon.php') || !st2cf_lint($root . '/extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php')) {
    foreach ($written as $restore) copy($backup . '/' . $restore, $root . '/' . $restore);
    st2cf_fail('rollback_after_php_lint');
}
st2cf_out('done=ok');
@unlink(__FILE__);
