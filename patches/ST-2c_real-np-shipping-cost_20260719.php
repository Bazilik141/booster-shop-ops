<?php
/**
 * ST-2c Part A — real Nova Poshta cost in totals and server-sourced free-shipping UI.
 *
 * DB schema/data: none. The existing admin field remains the persistent setting;
 * this patch changes its missing/invalid default from 1500 to 2000 UAH.
 * Hutko is intentionally read-only: live payment_hutko_shipping_include=0 already
 * charges order total minus a non-zero shipping line.
 */
declare(strict_types=1);

const ST2C_ID = 'ST-2c_real-np-shipping-cost_20260719';

function st2c_out(string $line): void { echo $line . PHP_EOL; }
function st2c_fail(string $line): never { st2c_out('error=' . $line); exit(1); }

function st2c_eol(string $contents, string $value): string {
    return str_replace("\n", str_contains($contents, "\r\n") ? "\r\n" : "\n", $value);
}

function st2c_replace_once(string $contents, string $anchor, string $replacement, string $label): string {
    $anchor = st2c_eol($contents, $anchor);
    $replacement = st2c_eol($contents, $replacement);
    $count = substr_count($contents, $anchor);
    if ($count !== 1) {
        st2c_fail('anchor_count=' . $count . ' expected=1 label=' . $label);
    }
    return str_replace($anchor, $replacement, $contents);
}

function st2c_replace_count(string $contents, string $anchor, string $replacement, int $expected, string $label): string {
    $anchor = st2c_eol($contents, $anchor);
    $replacement = st2c_eol($contents, $replacement);
    $count = substr_count($contents, $anchor);
    if ($count !== $expected) {
        st2c_fail('anchor_count=' . $count . ' expected=' . $expected . ' label=' . $label);
    }
    return str_replace($anchor, $replacement, $contents);
}

function st2c_replace_regex_once(string $contents, string $pattern, string $replacement, string $label): string {
    $count = 0;
    $replacement = st2c_eol($contents, $replacement);
    $result = preg_replace($pattern, $replacement, $contents, 1, $count);
    if (!is_string($result) || $count !== 1) {
        st2c_fail('regex_anchor_count=' . $count . ' expected=1 label=' . $label);
    }
    return $result;
}

function st2c_backup_write(string $file, string $contents, string $backupDir): void {
    $backup = $backupDir . DIRECTORY_SEPARATOR . $file;
    $parent = dirname($backup);
    if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
        st2c_fail('backup_dir_create_failed=' . $parent);
    }
    if (!copy($file, $backup)) {
        st2c_fail('backup_copy_failed=' . $file);
    }
    if (file_put_contents($file, $contents) === false) {
        @copy($backup, $file);
        st2c_fail('write_failed=' . $file);
    }
}

function st2c_restore(string $backupDir, array $files): void {
    foreach ($files as $file) {
        $backup = $backupDir . DIRECTORY_SEPARATOR . $file;
        if (is_file($backup)) {
            @copy($backup, $file);
        }
    }
}

$files = [
    'extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php',
    'extension/PintaNovaPoshtaCod/admin/controller/shipping/pinta_nova_poshta.php',
    'catalog/controller/checkout/coupon.php',
    'catalog/view/javascript/checkout-reskin.js',
];

st2c_out('patch=' . ST2C_ID);
st2c_out('cwd=' . getcwd());
st2c_out('time=' . date('c'));
st2c_out('db_schema_changes=none');
st2c_out('db_data_changes=none');

foreach ($files as $file) {
    if (!is_file($file)) {
        st2c_fail('missing_file=' . $file);
    }
}

$model = file_get_contents($files[0]);
$admin = file_get_contents($files[1]);
$coupon = file_get_contents($files[2]);
$reskin = file_get_contents($files[3]);
if (!is_string($model) || !is_string($admin) || !is_string($coupon) || !is_string($reskin)) {
    st2c_fail('read_failed');
}

$markers = [
    str_contains($model, 'ST-2c: use the calculated NP tariff unless the cart subtotal qualifies.'),
    str_contains($admin, "shipping_pinta_nova_poshta_free_from'] = '2000';"),
    str_contains($coupon, 'ST-2c: expose the authoritative shipping threshold and pre-discount subtotal.'),
    str_contains($reskin, 'ST-2c: server-owned free-shipping rule; never invoke the order-write endpoint.'),
];
if (in_array(true, $markers, true)) {
    if ($markers === [true, true, true, true]) {
        st2c_out('already_applied=yes');
        exit(0);
    }
    st2c_fail('partial_marker_detected');
}

if (substr_count($model, "'cost'         => 0,") !== 2) {
    st2c_fail('unexpected_np_zero_cost_contract');
}
if (substr_count($model, 'return 1500.0;') !== 1) {
    st2c_fail('unexpected_np_threshold_default_contract');
}
if (substr_count($admin, "shipping_pinta_nova_poshta_free_from'] = '1500';") !== 1 || substr_count($admin, "'shipping_pinta_nova_poshta_free_from' => '1500',") !== 1) {
    st2c_fail('unexpected_admin_threshold_default_contract');
}
if (substr_count($reskin, 'RD13-STUB') !== 2 || substr_count($reskin, 'var FREE_SHIP_THRESHOLD = 2000;') !== 1) {
    st2c_fail('unexpected_checkout_free_shipping_stub_contract');
}
if (str_contains($reskin, 'checkout/confirm.confirm') || str_contains($coupon, 'checkout/confirm.confirm')) {
    st2c_fail('forbidden_confirm_write_call_present_before_patch');
}

$model = st2c_replace_count(
    $model,
    "'cost'         => 0,",
    "'cost'         => \$this->getBoosterShippingCost(\$shipping_cost_default),",
    2,
    'np_quote_cost'
);
$model = st2c_replace_once(
    $model,
    <<<'PHP'
    private function getBoosterShippingText($shipping_cost_uah) {
PHP,
    <<<'PHP'
    // ST-2c: use the calculated NP tariff unless the cart subtotal qualifies.
    private function getBoosterShippingCost($shipping_cost_default) {
        if ($this->getBoosterCartTotalUah() >= $this->getBoosterFreeShippingFromUah()) {
            return 0.0;
        }

        return is_numeric($shipping_cost_default) && (float)$shipping_cost_default > 0 ? (float)$shipping_cost_default : 0.0;
    }

    private function getBoosterShippingText($shipping_cost_uah) {
PHP,
    'np_shipping_cost_helper'
);
$model = st2c_replace_once(
    $model,
    "return 'За тарифами Нової Пошти (≈ ₴' . number_format((float)\$shipping_cost_uah, 0, '.', ' ') . ')';",
    "return 'За тарифами Нової Пошти';",
    'np_remove_approximate_text'
);
$model = st2c_replace_once($model, 'return 1500.0;', 'return 2000.0;', 'np_threshold_fallback');
$model = st2c_replace_once(
    $model,
    '$total = (float)$this->cart->getTotal();',
    '$total = (float)$this->cart->getSubTotal();',
    'np_pre_discount_subtotal'
);

$admin = st2c_replace_once(
    $admin,
    '$settings[\'shipping_pinta_nova_poshta_free_from\'] = \'1500\';',
    '$settings[\'shipping_pinta_nova_poshta_free_from\'] = \'2000\';',
    'admin_threshold_fallback'
);
$admin = st2c_replace_once(
    $admin,
    "'shipping_pinta_nova_poshta_free_from' => '1500',",
    "'shipping_pinta_nova_poshta_free_from' => '2000',",
    'admin_threshold_install_default'
);

$coupon = st2c_replace_once(
    $coupon,
    <<<'PHP'
			'summary_html' => $summary['html'],
			'totals' => $summary['totals'],
			'total_text' => $summary['total_text'],
PHP,
    <<<'PHP'
			'summary_html' => $summary['html'],
			'totals' => $summary['totals'],
			'total_text' => $summary['total_text'],
			'free_shipping_threshold' => $this->freeShippingThresholdUah(),
			'free_shipping_subtotal' => $this->cartSubtotalUah(),
PHP,
    'coupon_summary_shipping_rule_fields'
);
$coupon = st2c_replace_once(
    $coupon,
    <<<'PHP'
	private function discountText(): string {
PHP,
    <<<'PHP'
	// ST-2c: expose the authoritative shipping threshold and pre-discount subtotal.
	// This response-only endpoint is the existing safe totals-refresh path.
	private function freeShippingThresholdUah(): float {
		$value = $this->config->get('shipping_pinta_nova_poshta_free_from');
		return is_numeric($value) && (float)$value > 0 ? (float)$value : 2000.0;
	}

	private function cartSubtotalUah(): float {
		$subtotal = (float)$this->cart->getSubTotal();
		$currency = (string)$this->config->get('config_currency');
		if ($currency !== '' && $currency !== 'UAH' && $this->currency->has($currency) && $this->currency->has('UAH')) {
			return (float)$this->currency->convert($subtotal, $currency, 'UAH');
		}
		return $subtotal;
	}

	private function discountText(): string {
PHP,
    'coupon_shipping_rule_helpers'
);

$reskin = st2c_replace_once(
    $reskin,
    <<<'JS'
      json = json || {};
      options = options || {};
      var coupon = text(json.coupon || '');
JS,
    <<<'JS'
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
JS,
    'reskin_shipping_rule_response'
);
$reskin = st2c_replace_regex_once(
    $reskin,
    '~\s*// RD13-STUB: threshold is a temporary constant until the real.*?\n\s*html \+= \'<div class="bs-co-totals">\';~s',
    <<<'JS'

    // ST-2c: server-owned rule arrives through checkout/coupon.summary.
    // Before that safe response returns, show the actual shipping row without
    // claiming eligibility from a browser-side fallback threshold.
    var shippingRule = window.bsCheckoutFreeShippingRule || null;
    var freeShippingThreshold = Number(shippingRule && shippingRule.threshold);
    var serverSubtotal = Number(shippingRule && shippingRule.subtotal);
    var subtotalAmount = isFinite(serverSubtotal) && serverSubtotal >= 0
      ? serverSubtotal
      : (subtotal ? parseMoney(subtotal.value) : 0);
    var ruleReady = isFinite(freeShippingThreshold) && freeShippingThreshold > 0;
    var remaining = ruleReady ? Math.max(0, freeShippingThreshold - subtotalAmount) : 0;
    var percentage = ruleReady ? Math.min(100, Math.round((subtotalAmount / freeShippingThreshold) * 100)) : 0;
    var free = ruleReady && remaining <= 0;
    var shippingPrice = free ? 'За наш кошт' : (shipping ? escapeHtml(shipping.value) : '—');
    var shippingMessage = !ruleReady
      ? 'Вартість доставки уточнюється'
      : (free ? 'Безкоштовна доставка застосована ✓' : 'До безкоштовної доставки лишилось ₴' + formatHryvnia(remaining));

    html += '<div class="bs-co-shipblock st2c-free-shipping' + (free ? ' is-free' : '') + '">' +
      '<div class="bs-co-shipblock__row"><span>Доставка</span>' +
      '<span class="bs-co-shipblock__price">' + shippingPrice + '</span></div>' +
      '<div class="bs-co-shipblock__msg">' + shippingMessage + '</div>' +
      '<div class="bs-co-shipblock__track"><i style="width:' + percentage + '%"></i></div>' +
      '</div>';

    html += '<div class="bs-co-totals">';
JS,
    'reskin_remove_rd13_shipping_stub'
);

if (str_contains($model, "'cost'         => 0,") || str_contains($model, 'return 1500.0;') || str_contains($model, '≈ ₴')) {
    st2c_fail('model_postcheck_failed');
}
if (str_contains($admin, "shipping_pinta_nova_poshta_free_from'] = '1500';") || str_contains($admin, "'shipping_pinta_nova_poshta_free_from' => '1500',")) {
    st2c_fail('admin_postcheck_failed');
}
if (str_contains($reskin, 'RD13-STUB') || str_contains($reskin, 'var FREE_SHIP_THRESHOLD = 2000;') || str_contains($reskin, 'grand || subtotal') || str_contains($reskin, 'checkout/confirm.confirm')) {
    st2c_fail('reskin_postcheck_failed');
}
if (!str_contains($coupon, "'free_shipping_threshold' => \$this->freeShippingThresholdUah()") || !str_contains($coupon, 'ST-2c: expose the authoritative shipping threshold')) {
    st2c_fail('coupon_postcheck_failed');
}

$backupDir = '_patch_backups/' . ST2C_ID . '-' . date('Ymd-His');
$changed = [];
try {
    st2c_backup_write($files[0], $model, $backupDir);
    $changed[] = $files[0];
    st2c_backup_write($files[1], $admin, $backupDir);
    $changed[] = $files[1];
    st2c_backup_write($files[2], $coupon, $backupDir);
    $changed[] = $files[2];
    st2c_backup_write($files[3], $reskin, $backupDir);
    $changed[] = $files[3];

    foreach ([$files[0], $files[1], $files[2]] as $file) {
        $output = [];
        $code = 1;
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
        if ($code !== 0) {
            st2c_restore($backupDir, $changed);
            st2c_fail('php_l_failed file=' . $file . ' output=' . implode(' ', $output));
        }
        st2c_out('php_l=ok file=' . $file);
    }
} catch (Throwable $error) {
    st2c_restore($backupDir, $changed);
    st2c_fail('exception=' . $error->getMessage());
}

st2c_out('backup=' . $backupDir);
st2c_out('changed_files=' . count($changed));
foreach ($changed as $file) {
    st2c_out('changed=' . $file);
}
st2c_out('hutko=read_only payment_hutko_shipping_include=0; charged_amount=order_total-shipping');
st2c_out('cache_clear=required');
st2c_out('done=ok');
@unlink(__FILE__);
