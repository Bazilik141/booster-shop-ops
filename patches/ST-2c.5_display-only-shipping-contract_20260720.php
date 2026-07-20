<?php
/**
 * ST-2c.5 — restore the display-only Nova Poshta shipping contract.
 *
 * The NP API tariff is informational: it must be visible on checkout/success,
 * but must never enter OpenCart order totals, Hutko amount, or fiscal products.
 * The quote therefore keeps payable cost=0 and carries the formatted tariff in
 * a dedicated booster_display_text field used only by the theme.
 *
 * DB changes: none.
 * Rollback: restore all eight files from
 * _patch_backups/ST-2c.5_display-only-shipping-contract_20260720-<ts>/.
 */

declare(strict_types=1);

const ST2C5_ID = 'ST-2c.5_display-only-shipping-contract_20260720';

$root = __DIR__;
$files = [
    'extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php' => [
        'hash' => 'BB345A3FE950BE302DB9FE39870E60B33EAD59E8EBD8E419B0A80DAD1C467594',
        'marker' => 'ST-2c.5: payable shipping cost is always zero; NP tariff is display-only.',
    ],
    'catalog/controller/checkout/shipping_method.php' => [
        'hash' => '0C64953D164D58D1FC82A2FD5E6876D661F46D775A4236320832572F4AAA9529',
        'marker' => 'ST-2c.5: return the informational NP tariff outside checkout totals.',
    ],
    'catalog/view/template/checkout/shipping_method.twig' => [
        'hash' => 'EBAF74BB0FDECA3691660E17A8A7951D26A6B15A1473B057DC0203FBE3D0B696',
        'marker' => 'input-shipping-display-text',
    ],
    'catalog/view/javascript/checkout-state.js' => [
        'hash' => 'AEFBB96FC94B1B2458826A05536F42F9CDE7ECD3B44332890CDDBCFDED1052BC',
        'marker' => 'ST-2c.5: invalidate the display-only tariff with its shipping state.',
    ],
    'catalog/view/javascript/checkout-reskin.js' => [
        'hash' => '0B4965808DD0061D5750F19C5AB3D251FE6D42A9E47852246A965AC7367A1C3A',
        'marker' => 'ST-2c.5: the NP tariff is display-only and never comes from totals.',
    ],
    'catalog/view/template/checkout/checkout.twig' => [
        'hash' => '40851DFD1610A810CBAA73027A91306F49DC02E89861889DE399F8154F5B43C4',
        'marker' => 'checkout-state.js?v=st2c5-20260720',
    ],
    'catalog/controller/checkout/success.php' => [
        'hash' => 'A1A407C6243F9BA516D5BDA36CD4C1DC45897070AF17349619E45A6F7AFB6EEC',
        'marker' => 'ST-2c.5: read the informational tariff saved inside shipping_method JSON.',
    ],
    'catalog/view/template/checkout/success.twig' => [
        'hash' => 'DE97C77D02B60D2508ED83E32EE4B7931C2813324D00A5F63510875D1BF1682D',
        'marker' => 'order_data.shipping_display_text',
    ],
];

function st2c5_out(string $line): void {
    echo $line . PHP_EOL;
}

function st2c5_fail(string $line): never {
    fwrite(STDERR, 'error=' . $line . PHP_EOL);
    exit(1);
}

function st2c5_eol(string $contents, string $value): string {
    return str_replace("\n", str_contains($contents, "\r\n") ? "\r\n" : "\n", $value);
}

function st2c5_replace_once(string $contents, string $anchor, string $replacement, string $label): string {
    $anchor = st2c5_eol($contents, $anchor);
    $replacement = st2c5_eol($contents, $replacement);
    $count = substr_count($contents, $anchor);

    if ($count !== 1) {
        st2c5_fail('anchor=' . $label . '; count=' . $count . '; expected=1');
    }

    return str_replace($anchor, $replacement, $contents);
}

function st2c5_replace_count(string $contents, string $anchor, string $replacement, int $expected, string $label): string {
    $anchor = st2c5_eol($contents, $anchor);
    $replacement = st2c5_eol($contents, $replacement);
    $count = substr_count($contents, $anchor);

    if ($count !== $expected) {
        st2c5_fail('anchor=' . $label . '; count=' . $count . '; expected=' . $expected);
    }

    return str_replace($anchor, $replacement, $contents);
}

function st2c5_restore(array $targets, array $backups): void {
    foreach ($targets as $relative => $target) {
        if (isset($backups[$relative]) && is_file($backups[$relative])) {
            @copy($backups[$relative], $target);
        }
    }
}

$targets = [];
$sources = [];
$applied = [];

st2c5_out('patch=' . ST2C5_ID);
st2c5_out('cwd=' . $root);
st2c5_out('db_schema_changes=none');
st2c5_out('db_data_changes=none');

foreach ($files as $relative => $spec) {
    $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (!is_file($target)) {
        st2c5_fail('target_missing:' . $relative);
    }

    $source = file_get_contents($target);

    if ($source === false) {
        st2c5_fail('target_read_failed:' . $relative);
    }

    $targets[$relative] = $target;
    $sources[$relative] = $source;
    $applied[$relative] = str_contains($source, $spec['marker']);
}

$appliedCount = count(array_filter($applied));

if ($appliedCount === count($files)) {
    st2c5_out('already_applied=yes');
    exit(0);
}

if ($appliedCount !== 0) {
    st2c5_fail('partial_apply_detected:' . implode(',', array_keys(array_filter($applied))));
}

foreach ($files as $relative => $spec) {
    $actualHash = strtoupper((string)hash_file('sha256', $targets[$relative]));

    if ($actualHash !== $spec['hash']) {
        st2c5_fail('sha256_mismatch:' . $relative . '; actual=' . $actualHash);
    }
}

$updated = $sources;
$model = 'extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php';
$shippingController = 'catalog/controller/checkout/shipping_method.php';
$shippingTwig = 'catalog/view/template/checkout/shipping_method.twig';
$stateJs = 'catalog/view/javascript/checkout-state.js';
$reskinJs = 'catalog/view/javascript/checkout-reskin.js';
$checkoutTwig = 'catalog/view/template/checkout/checkout.twig';
$successController = 'catalog/controller/checkout/success.php';
$successTwig = 'catalog/view/template/checkout/success.twig';

$updated[$model] = st2c5_replace_count(
    $updated[$model],
    <<<'OLD'
                    'cost'         => $this->getBoosterShippingCost($shipping_cost_default),
'tax_class_id' => 0,
'text'         => $this->getBoosterShippingText($shipping_cost_uah),
OLD
,
    <<<'NEW'
                    'cost'         => 0.0,
'tax_class_id' => 0,
'text'         => $this->getBoosterShippingText($shipping_cost_uah),
'booster_display_text' => $this->getBoosterShippingDisplayText($shipping_cost_session_currency),
NEW
,
    2,
    'np_zero_payable_cost_and_display_field'
);

$updated[$model] = st2c5_replace_once(
    $updated[$model],
    <<<'OLD'
    // ST-2c: use the calculated NP tariff unless the cart subtotal qualifies.
    private function getBoosterShippingCost($shipping_cost_default) {
        if ($this->getBoosterCartTotalUah() >= $this->getBoosterFreeShippingFromUah()) {
            return 0.0;
        }

        return is_numeric($shipping_cost_default) && (float)$shipping_cost_default > 0 ? (float)$shipping_cost_default : 0.0;
    }

    private function getBoosterShippingText($shipping_cost_uah) {
OLD
,
    <<<'NEW'
    // ST-2c.5: payable shipping cost is always zero; NP tariff is display-only.
    private function getBoosterShippingDisplayText($shipping_cost_session_currency): string {
        if ($this->getBoosterCartTotalUah() >= $this->getBoosterFreeShippingFromUah()) {
            return 'За наш кошт';
        }

        if (!is_numeric($shipping_cost_session_currency) || (float)$shipping_cost_session_currency <= 0) {
            return '';
        }

        $currency = (string)($this->session->data['currency'] ?? $this->config->get('config_currency'));

        return $currency !== ''
            ? $this->currency->format((float)$shipping_cost_session_currency, $currency)
            : number_format((float)$shipping_cost_session_currency, 2, '.', ' ');
    }

    private function getBoosterShippingText($shipping_cost_uah) {
NEW
,
    'replace_payable_cost_helper'
);

$updated[$shippingController] = st2c5_replace_once(
    $updated[$shippingController],
    <<<'OLD'
		$this->load->language('checkout/shipping_method');

		if (isset($this->session->data['shipping_method'])) {
OLD
,
    <<<'NEW'
		$this->load->language('checkout/shipping_method');

		if (isset($this->session->data['shipping_method']) && is_array($this->session->data['shipping_method'])) {
			$this->session->data['shipping_method'] = $this->normalizeBoosterDisplayOnlyShipping($this->session->data['shipping_method']);
		}

		if (isset($this->session->data['shipping_method'])) {
NEW
,
    'normalize_existing_checkout_session'
);

$updated[$shippingController] = st2c5_replace_once(
    $updated[$shippingController],
    <<<'OLD'
		$data['language'] = $this->config->get('config_language');
OLD
,
    <<<'NEW'
		$data['shipping_display_text'] = isset($this->session->data['shipping_method']['booster_display_text'])
			? (string)$this->session->data['shipping_method']['booster_display_text']
			: '';

		$data['language'] = $this->config->get('config_language');
NEW
,
    'shipping_display_text_on_index'
);

$updated[$shippingController] = st2c5_replace_once(
    $updated[$shippingController],
    <<<'OLD'
			$this->session->data['shipping_method'] = $this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]];

			$json['success'] = $this->language->get('text_success');
OLD
,
    <<<'NEW'
			$this->session->data['shipping_method'] = $this->normalizeBoosterDisplayOnlyShipping(
				$this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]]
			);

			// ST-2c.5: return the informational NP tariff outside checkout totals.
			$json['shipping_display_text'] = (string)($this->session->data['shipping_method']['booster_display_text'] ?? '');
			$json['success'] = $this->language->get('text_success');
NEW
,
    'shipping_display_text_on_save'
);

$updated[$shippingController] = st2c5_replace_once(
    $updated[$shippingController],
    <<<'OLD'
		$this->response->setOutput(json_encode($json));
	}
}
OLD
,
    <<<'NEW'
		$this->response->setOutput(json_encode($json));
	}

	private function normalizeBoosterDisplayOnlyShipping(array $shippingMethod): array {
		$code = (string)($shippingMethod['code'] ?? '');

		if (strpos($code, 'pinta_nova_poshta.') !== 0) {
			return $shippingMethod;
		}

		if (empty($shippingMethod['booster_display_text']) && is_numeric($shippingMethod['cost'] ?? null) && (float)$shippingMethod['cost'] > 0) {
			$displayCost = (float)$shippingMethod['cost'];
			$defaultCurrency = (string)$this->config->get('config_currency');
			$sessionCurrency = (string)($this->session->data['currency'] ?? $defaultCurrency);

			if ($defaultCurrency !== '' && $sessionCurrency !== '' && $defaultCurrency !== $sessionCurrency && $this->currency->has($defaultCurrency) && $this->currency->has($sessionCurrency)) {
				$displayCost = (float)$this->currency->convert($displayCost, $defaultCurrency, $sessionCurrency);
			}

			$shippingMethod['booster_display_text'] = $sessionCurrency !== ''
				? $this->currency->format($displayCost, $sessionCurrency)
				: number_format($displayCost, 2, '.', ' ');
		}

		$shippingMethod['cost'] = 0.0;
		$shippingMethod['tax_class_id'] = 0;

		return $shippingMethod;
	}
}
NEW
,
    'normalize_pinta_session_write_boundary'
);

$updated[$shippingTwig] = st2c5_replace_once(
    $updated[$shippingTwig],
    <<<'OLD'
  <input type="hidden" name="code" value="{{ code }}" id="input-shipping-code"/>
OLD
,
    <<<'NEW'
  <input type="hidden" name="code" value="{{ code }}" id="input-shipping-code"/>
  <input type="hidden" value="{{ shipping_display_text }}" id="input-shipping-display-text"/>
NEW
,
    'shipping_display_hidden_field'
);

$updated[$shippingTwig] = st2c5_replace_once(
    $updated[$shippingTwig],
    <<<'OLD'
            $('#input-shipping-method').val(label || code);
            $('#input-payment-method').val('');
OLD
,
    <<<'NEW'
            $('#input-shipping-method').val(label || code);
            $('#input-shipping-display-text').val(json['shipping_display_text'] || '');
            $('#input-payment-method').val('');
NEW
,
    'apply_shipping_display_text_before_summary'
);

$updated[$stateJs] = st2c5_replace_once(
    $updated[$stateJs],
    <<<'OLD'
    $('#input-shipping-code, #input-shipping-method').val('');
    clearPaymentState();
OLD
,
    <<<'NEW'
    // ST-2c.5: invalidate the display-only tariff with its shipping state.
    $('#input-shipping-code, #input-shipping-method, #input-shipping-display-text').val('');
    clearPaymentState();
NEW
,
    'clear_display_tariff_on_address_change'
);

$updated[$stateJs] = st2c5_replace_once(
    $updated[$stateJs],
    <<<'OLD'
    abortTotals();
    clearPaymentState();
    return revision;
  }

  function extractSummary(html) {
OLD
,
    <<<'NEW'
    abortTotals();
    $('#input-shipping-display-text').val('');
    clearPaymentState();
    return revision;
  }

  function extractSummary(html) {
NEW
,
    'clear_display_tariff_on_shipping_change'
);

$updated[$reskinJs] = st2c5_replace_once(
    $updated[$reskinJs],
    <<<'OLD'
    var wrapper = table.closest('.table-responsive');
    var signature = text(table.innerText || table.textContent);
OLD
,
    <<<'NEW'
    var wrapper = table.closest('.table-responsive');
    var shippingDisplayField = document.getElementById('input-shipping-display-text');
    var shippingDisplayText = text(shippingDisplayField ? shippingDisplayField.value : '');
    var signature = text(table.innerText || table.textContent) + '|shipping-display:' + shippingDisplayText;
NEW
,
    'display_tariff_in_summary_signature'
);

$updated[$reskinJs] = st2c5_replace_once(
    $updated[$reskinJs],
    <<<'OLD'
    var shippingPrice = free ? 'За наш кошт' : (shipping ? escapeHtml(shipping.value) : '—');
OLD
,
    <<<'NEW'
    // ST-2c.5: the NP tariff is display-only and never comes from totals.
    // A positive shipping total would change the order/payment amount.
    var shippingPrice = free
      ? 'За наш кошт'
      : (shippingDisplayText ? escapeHtml(shippingDisplayText) : '—');
NEW
,
    'sidebar_uses_display_only_tariff'
);

$updated[$checkoutTwig] = st2c5_replace_once(
    $updated[$checkoutTwig],
    '<script src="catalog/view/javascript/checkout-state.js?v=st2c4-20260720"></script>',
    '<script src="catalog/view/javascript/checkout-state.js?v=st2c5-20260720"></script>',
    'checkout_state_cache_buster'
);

$updated[$checkoutTwig] = st2c5_replace_once(
    $updated[$checkoutTwig],
    '<script src="catalog/view/javascript/checkout-reskin.js?v=st2c4-20260720"></script>',
    '<script src="catalog/view/javascript/checkout-reskin.js?v=st2c5-20260720"></script>',
    'checkout_reskin_cache_buster'
);

$updated[$successController] = st2c5_replace_once(
    $updated[$successController],
    <<<'OLD'
		$get_method_code = static function ($method): string {
OLD
,
    <<<'NEW'
		// ST-2c.5: read the informational tariff saved inside shipping_method JSON.
		$get_shipping_display_text = static function ($method): string {
			if (is_string($method) && $method !== '') {
				$decoded = json_decode($method, true);
				$method = is_array($decoded) ? $decoded : [];
			}

			return is_array($method) ? trim((string)($method['booster_display_text'] ?? '')) : '';
		};

		$get_method_code = static function ($method): string {
NEW
,
    'success_shipping_display_reader'
);

$updated[$successController] = st2c5_replace_once(
    $updated[$successController],
    <<<'OLD'
					'shipping_method' => $get_method_name($order_info['shipping_method'] ?? ''),
					'payment_method'  => $payment_name,
OLD
,
    <<<'NEW'
					'shipping_method' => $get_method_name($order_info['shipping_method'] ?? ''),
					'shipping_display_text' => $get_shipping_display_text($order_info['shipping_method'] ?? ''),
					'payment_method'  => $payment_name,
NEW
,
    'success_order_data_display_tariff'
);

$updated[$successTwig] = st2c5_replace_once(
    $updated[$successTwig],
    <<<'OLD'
                <span>{{ order_data.shipping_method }}</span>
OLD
,
    <<<'NEW'
                <span>{{ order_data.shipping_method }}{% if order_data.shipping_display_text %} · {{ order_data.shipping_display_text }}{% endif %}</span>
NEW
,
    'success_delivery_meta_display_tariff'
);

foreach ($files as $relative => $spec) {
    if (!str_contains($updated[$relative], $spec['marker'])) {
        st2c5_fail('postcheck_missing:' . $relative . ':' . $spec['marker']);
    }
}

if (substr_count($updated[$model], "'cost'         => 0.0,") !== 2) {
    st2c5_fail('postcheck_np_payable_cost_not_zero');
}

if (str_contains($updated[$model], 'getBoosterShippingCost(')) {
    st2c5_fail('postcheck_old_payable_cost_helper_present');
}

if (substr_count($updated[$model], "'booster_display_text' =>") !== 2) {
    st2c5_fail('postcheck_display_field_count');
}

if (str_contains($updated[$reskinJs], "(shipping ? escapeHtml(shipping.value) : '—')")) {
    st2c5_fail('postcheck_sidebar_still_reads_shipping_total');
}

$timestamp = gmdate('Ymd_His');
$backupDir = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . ST2C5_ID . '-' . $timestamp;
$backups = [];

foreach ($targets as $relative => $target) {
    $backup = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0775, true) && !is_dir(dirname($backup))) {
        st2c5_fail('backup_directory_create_failed:' . $relative);
    }

    if (!copy($target, $backup)) {
        st2c5_fail('backup_copy_failed:' . $relative);
    }

    $backups[$relative] = $backup;
}

foreach ($targets as $relative => $target) {
    if (file_put_contents($target, $updated[$relative]) === false) {
        st2c5_restore($targets, $backups);
        st2c5_fail('target_write_failed:' . $relative . '; restored=yes');
    }
}

foreach ([$model, $shippingController, $successController] as $relative) {
    $lintOutput = [];
    $lintCode = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($targets[$relative]) . ' 2>&1', $lintOutput, $lintCode);

    if ($lintCode !== 0) {
        st2c5_restore($targets, $backups);
        st2c5_fail('php_l_failed:' . $relative . '; restored=yes; detail=' . implode(' | ', $lintOutput));
    }

    st2c5_out('php_l=ok file=' . $relative);
}

st2c5_out('time=' . gmdate('c'));
st2c5_out('backup=' . $backupDir);

foreach (array_keys($targets) as $relative) {
    st2c5_out('changed=' . $relative);
}

st2c5_out('payable_shipping_cost=0');
st2c5_out('display_field=booster_display_text');
st2c5_out('cache_clear=required');
st2c5_out('done=ok');

@unlink(__FILE__);
