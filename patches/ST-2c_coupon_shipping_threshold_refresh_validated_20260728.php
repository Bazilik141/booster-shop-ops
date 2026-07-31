<?php
declare(strict_types=1);

/** Run from ~/public_html. No DB, payment, order-write or SimpleCheckout changes. */
$id = 'ST-2c_coupon_shipping_threshold_refresh_validated_20260728';
$mark = 'ST-2C-COUPON-SHIPPING-20260728';
$root = getcwd() ?: __DIR__;
$dry = in_array('--dry-run', $argv ?? [], true);
$backup = $root . '/_patch_backups/' . $id . '-' . date('Ymd-His');
$files = [
  'catalog/controller/checkout/coupon.php' => '592BA0B0C99A7218CE252DB76994894D080A17A83B3A1AA5C0E6529BF030CE86',
  'catalog/view/javascript/checkout-state.js' => '23378071030674F5CCDD34B039CDE51CD2D5C40ACC07ED2012BD2BB67A49D025',
  'catalog/view/template/checkout/shipping_method.twig' => '5264DD70EF2E9F190207F768C884322CA444018F9595C8DA1FDDE558DABD0845',
  'extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php' => '766C70E4963541AB10585C783CD5DBC52E7070CD95FED973BEC36D7194625D34',
];
function st2cv_out(string $v): void { echo '[' . date('c') . '] ' . $v . PHP_EOL; }
function st2cv_fail(string $v): void { st2cv_out('error=' . $v); st2cv_out('done=failed'); exit(1); }
function st2cv_norm(string $v): string { return str_replace(["\r\n", "\r"], "\n", $v); }
function st2cv_sub(string $v, string $pattern, string $replacement, string $name): string {
  $count = 0; $out = preg_replace_callback($pattern, static fn(): string => $replacement, $v, 1, $count);
  if ($out === null || $count !== 1) st2cv_fail('anchor_' . $name . '_count=' . $count);
  return $out;
}
function st2cv_lint(string $path): bool { exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path), $x, $code); st2cv_out('php_lint=' . ($code ? 'failed' : 'ok') . ' file=' . $path); return $code === 0; }
st2cv_out('patch=' . $id); st2cv_out('cwd=' . $root); st2cv_out('time=' . date('c'));
$src = []; $eol = []; $all = true;
foreach ($files as $file => $hash) {
  $path = $root . '/' . $file;
  if (!is_file($path)) st2cv_fail('missing_file=' . $file);
  $raw = file_get_contents($path); if ($raw === false) st2cv_fail('cannot_read=' . $file);
  $all = $all && str_contains($raw, $mark); $eol[$file] = str_contains($raw, "\r\n") ? "\r\n" : "\n";
  if (!str_contains($raw, $mark) && strtoupper(hash('sha256', $raw)) !== $hash) st2cv_fail('sha256_mismatch=' . $file);
  $src[$file] = st2cv_norm($raw);
}
if ($all) { st2cv_out('already_applied=yes'); st2cv_out('done=ok'); @unlink(__FILE__); exit(0); }
foreach ($src as $file => $raw) if (str_contains($raw, $mark)) st2cv_fail('partial_marker_state=' . $file);
$next = $src;

$coupon = 'catalog/controller/checkout/coupon.php';
$next[$coupon] = st2cv_sub($next[$coupon], '~^\t\t\t\'free_shipping_subtotal\'.*$~m', <<<'PHP'
			'free_shipping_subtotal' => $this->totalToUah((float)($summary['total_value'] ?? 0.0)),
PHP, 'coupon_response');
$next[$coupon] = st2cv_sub($next[$coupon], '~^\t// ST-2c: expose the authoritative shipping threshold.*$~m', "\t// ST-2C-COUPON-SHIPPING-20260728: eligibility follows the post-discount payable amount.", 'coupon_comment');
$next[$coupon] = st2cv_sub($next[$coupon], '~\tprivate function cartSubtotalUah\(\): float \{.*?^\t\}~ms', <<<'PHP'
	private function totalToUah(float $total): float {
		$currency = (string)$this->config->get('config_currency');
		if ($currency !== '' && $currency !== 'UAH' && $this->currency->has($currency) && $this->currency->has('UAH')) {
			return (float)$this->currency->convert($total, $currency, 'UAH');
		}
		return $total;
	}
PHP, 'coupon_total_basis');
$next[$coupon] = st2cv_sub($next[$coupon], '~^\t\treturn \[\'html\'.*$~m', <<<'PHP'
		return ['html' => $html, 'totals' => $rows, 'total_text' => $rows ? (string)end($rows)['text'] : '', 'total_value' => $total];
PHP, 'coupon_total_value');

$pinta = 'extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php';
$next[$pinta] = st2cv_sub($next[$pinta], '~    private function getBoosterCartTotalUah\(\) \{.*?^    \}~ms', <<<'PHP'
    // ST-2C-COUPON-SHIPPING-20260728: Pinta remains display-only, while the
    // threshold uses the cart payable after any active coupon/discount.
    private function getBoosterCartTotalUah() {
        $this->load->model('checkout/booster_coupon');
        $this->model_checkout_booster_coupon->prepareCouponTotal();
        $this->load->model('checkout/cart');
        $totals = [];
        $taxes = $this->cart->getTaxes();
        $total = 0;
        $this->model_checkout_cart->getTotals($totals, $taxes, $total);
        $currency = (string)$this->config->get('config_currency');
        if ($currency && $currency !== 'UAH' && $this->currency->has($currency) && $this->currency->has('UAH')) {
            return (float)$this->currency->convert((float)$total, $currency, 'UAH');
        }
        return (float)$total;
    }
PHP, 'pinta_total_basis');

$state = 'catalog/view/javascript/checkout-state.js';
$next[$state] = st2cv_sub($next[$state], '~  function totalsChanged\(source, summaryHtml\) \{.*?\n  \}\n\n  function bootstrap\(\) \{~s', <<<'JS'
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

  function bootstrap() {
JS, 'state_coupon_requote');

$twig = 'catalog/view/template/checkout/shipping_method.twig';
$next[$twig] = st2cv_sub($next[$twig], '~    \} else if \(current\) \{\n      status\(\'Доставку обрано\.\'\);~u', <<<'TWIG'
    } else if (current) {
      // ST-2C-COUPON-SHIPPING-20260728: persist the fresh quote for the same selected method.
      if (options && options.resaveCurrent && currentQuote) {
        saveShipping(currentQuote.code, currentQuote.label, options.stateRevision);
        return;
      }
      status('Доставку обрано.');
TWIG, 'shipping_resave');
foreach ($next as $file => $raw) if (!str_contains($raw, $mark)) st2cv_fail('postcheck_marker_missing=' . $file);
if ($dry) { st2cv_out('dry_run=ok'); st2cv_out('done=ok'); exit(0); }
if (!is_dir($backup) && !mkdir($backup, 0755, true) && !is_dir($backup)) st2cv_fail('cannot_create_backup_dir');
foreach ($files as $file => $_) { $to = $backup . '/' . $file; if (!is_dir(dirname($to)) && !mkdir(dirname($to), 0755, true) && !is_dir(dirname($to))) st2cv_fail('cannot_create_backup_parent=' . $file); if (!copy($root . '/' . $file, $to)) st2cv_fail('cannot_backup=' . $file); }
st2cv_out('backup=' . str_replace($root . '/', '', $backup)); $written = [];
foreach ($next as $file => $raw) { $out = $eol[$file] === "\r\n" ? str_replace("\n", "\r\n", $raw) : $raw; if (file_put_contents($root . '/' . $file, $out) === false) { foreach ($written as $r) copy($backup . '/' . $r, $root . '/' . $r); st2cv_fail('cannot_write=' . $file); } $written[] = $file; st2cv_out('changed=' . $file); }
if (!st2cv_lint($root . '/catalog/controller/checkout/coupon.php') || !st2cv_lint($root . '/extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php')) { foreach ($written as $r) copy($backup . '/' . $r, $root . '/' . $r); st2cv_fail('rollback_after_php_lint'); }
st2cv_out('done=ok'); @unlink(__FILE__);
