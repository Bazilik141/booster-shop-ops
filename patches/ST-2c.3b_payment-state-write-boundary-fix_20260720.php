<?php
/**
 * ST-2c.3b — payment state write-boundary fix.
 *
 * Makes the three payment choices visible immediately, without address-dependent
 * helper copy. Payment save now resolves the allowed canonical option from the
 * current checkout context instead of trusting a transient session map populated
 * by a separate AJAX request.
 *
 * DB changes: none.
 * Rollback: restore all four files from
 * _patch_backups/ST-2c.3b_payment-state-write-boundary-fix_20260720-<ts>/.
 */

declare(strict_types=1);

$patch = basename(__FILE__, '.php');
$root = __DIR__;

$files = [
    'catalog/controller/checkout/payment_method.php' => [
        'hash' => '6A96DC5C5CEDEB1074B278BD1A46B3C561907EF93E635E4855A338ECC43728FD',
        'marker' => 'ST-2c.3b: resolve the canonical option from current server state.',
    ],
    'catalog/view/javascript/checkout-state.js' => [
        'hash' => '6840D6BD9AB19FC7D70329E35007871A8616D61490FF892C419B2676020C092E',
        'marker' => 'ST-2c.3b: payment choices are part of the initial UI state',
    ],
    'catalog/view/template/checkout/payment_method.twig' => [
        'hash' => '2DC69B60ECFB22B0AA466E168DEF15927BEE0097923117FE9B8E3868DBDA0430',
        'marker' => 'ST-2c.3b: the three choices are self-explanatory before address entry.',
    ],
    'catalog/view/template/checkout/checkout.twig' => [
        'hash' => '628C4D71272B3BB75F89B0625E8D96938BE0DC012E40770D6F132441F782F7CA',
        'marker' => 'checkout-state.js?v=st2c3b-20260720',
    ],
];

function st2c3b_fail(string $message): void {
    fwrite(STDERR, 'error=' . $message . PHP_EOL);
    exit(1);
}

function st2c3b_replace_once(string $source, string $old, string $new, string $label): string {
    $count = substr_count($source, $old);

    if ($count !== 1) {
        st2c3b_fail('anchor=' . $label . '; count=' . $count . '; expected=1');
    }

    return str_replace($old, $new, $source);
}

function st2c3b_restore(array $targets, array $backups): void {
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
        st2c3b_fail('target_missing:' . $relative);
    }

    $source = file_get_contents($target);

    if ($source === false) {
        st2c3b_fail('target_read_failed:' . $relative);
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
    st2c3b_fail('partial_apply_detected:' . implode(',', array_keys(array_filter($applied))));
}

foreach ($files as $relative => $spec) {
    $actualHash = strtoupper((string)hash_file('sha256', $targets[$relative]));

    if ($actualHash !== $spec['hash']) {
        st2c3b_fail('sha256_mismatch:' . $relative . '; actual=' . $actualHash);
    }
}

$updated = $sources;
$controller = 'catalog/controller/checkout/payment_method.php';
$stateJs = 'catalog/view/javascript/checkout-state.js';
$paymentTwig = 'catalog/view/template/checkout/payment_method.twig';
$checkoutTwig = 'catalog/view/template/checkout/checkout.twig';

$updated[$controller] = st2c3b_replace_once(
    $updated[$controller],
    <<<'OLD'
		if (!$json) {
			$payment_address = [];

			if ($this->config->get('config_checkout_payment_address') && isset($this->session->data['payment_address'])) {
				$payment_address = $this->session->data['payment_address'];
			} elseif ($this->config->get('config_checkout_shipping_address') && isset($this->session->data['shipping_address']['address_id'])) {
				$payment_address = $this->session->data['shipping_address'];
			}

			// Payment method
			$this->load->model('checkout/payment_method');

			$payment_methods = $this->model_checkout_payment_method->getMethods($payment_address);
			$payment_methods = $this->filterBoosterCheckoutPaymentMethods($payment_methods);
OLD
,
    <<<'NEW'
		if (!$json) {
			$payment_methods = $this->getBoosterCheckoutPaymentMethods();
NEW
,
    'shared_payment_method_source'
);

$updated[$controller] = st2c3b_replace_once(
    $updated[$controller],
    <<<'OLD'
			if ($payment_methods) {
				$json['payment_methods'] = $this->session->data['payment_methods'] = $payment_methods;
OLD
,
    <<<'NEW'
			$this->session->data['payment_methods'] = $payment_methods;

			if ($payment_methods) {
				$json['payment_methods'] = $payment_methods;
NEW
,
    'payment_methods_session_snapshot'
);

$updated[$controller] = st2c3b_replace_once(
    $updated[$controller],
    <<<'OLD'
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Booster checkout exposes one canonical option for each supported payment
OLD
,
    <<<'NEW'
		$this->response->setOutput(json_encode($json));
	}

	/** @return array<string, mixed> */
	private function getBoosterCheckoutPaymentMethods(): array {
		$payment_address = [];

		if ($this->config->get('config_checkout_payment_address') && isset($this->session->data['payment_address'])) {
			$payment_address = $this->session->data['payment_address'];
		} elseif ($this->config->get('config_checkout_shipping_address') && isset($this->session->data['shipping_address']['address_id'])) {
			$payment_address = $this->session->data['shipping_address'];
		}

		$this->load->model('checkout/payment_method');

		return $this->filterBoosterCheckoutPaymentMethods(
			$this->model_checkout_payment_method->getMethods($payment_address)
		);
	}

	/**
	 * Booster checkout exposes one canonical option for each supported payment
NEW
,
    'shared_payment_method_helper'
);

$updated[$controller] = st2c3b_replace_once(
    $updated[$controller],
    <<<'OLD'
			// Validate payment methods
			// ST-2c.3a: validate the rendered canonical option by its own code, not array key shape.
			if (isset($this->request->post['payment_method']) && isset($this->session->data['payment_methods'])) {
				$selected_payment = $this->boosterPaymentByCode(
					$this->session->data['payment_methods'],
					(string)$this->request->post['payment_method']
				);
			}
OLD
,
    <<<'NEW'
			// Validate payment methods at the write boundary from the current checkout
			// context. Do not trust a transient session map written by another AJAX request.
			// ST-2c.3b: resolve the canonical option from current server state.
			if (isset($this->request->post['payment_method'])) {
				$payment_methods = $this->getBoosterCheckoutPaymentMethods();
				$this->session->data['payment_methods'] = $payment_methods;
				$selected_payment = $this->boosterPaymentByCode(
					$payment_methods,
					(string)$this->request->post['payment_method']
				);
			}
NEW
,
    'payment_save_write_boundary_validation'
);

$updated[$stateJs] = st2c3b_replace_once(
    $updated[$stateJs],
    <<<'OLD'
    } else {
      $('#bs-payment-methods').empty();
      $('[data-bs-payment-status]').text('Спосіб оплати можна обрати після завантаження блоку.');
    }
OLD
,
    <<<'NEW'
    } else {
      $('#bs-payment-methods').empty();
      $('[data-bs-payment-status]').text('');
    }
NEW
,
    'payment_preview_fallback_status'
);

$updated[$stateJs] = st2c3b_replace_once(
    $updated[$stateJs],
    <<<'OLD'
    bootstrapped = true;

    if ($('#input-shipping-code').val()) {
OLD
,
    <<<'NEW'
    bootstrapped = true;

    // ST-2c.3b: payment choices are part of the initial UI state, independent
    // of the address -> shipping method bootstrap request.
    renderPaymentPreview();
    renderConfirmState();

    if ($('#input-shipping-code').val()) {
NEW
,
    'initial_payment_preview'
);

$updated[$stateJs] = st2c3b_replace_once(
    $updated[$stateJs],
    <<<'OLD'
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
OLD
,
    <<<'NEW'
    if (typeof window.bsCheckoutLoadShippingMethods === 'function') {
      window.bsCheckoutLoadShippingMethods({
        autoSelect: true,
        quietAddressError: true,
        stateRevision: revision
      });
      return;
    }
NEW
,
    'remove_late_payment_preview'
);

$updated[$paymentTwig] = st2c3b_replace_once(
    $updated[$paymentTwig],
    <<<'OLD'
    $('#bs-payment-methods').html(html);
    status('Спосіб оплати можна обрати зараз. Застосуємо після заповнення доставки.');
    refreshConfirmSummary();
OLD
,
    <<<'NEW'
    $('#bs-payment-methods').html(html);
    // ST-2c.3b: the three choices are self-explanatory before address entry.
    status('');
    refreshConfirmSummary();
NEW
,
    'quiet_payment_preview'
);

$updated[$checkoutTwig] = st2c3b_replace_once(
    $updated[$checkoutTwig],
    <<<'OLD'
    if (window.bsCheckoutRenderPaymentPreview) {
      window.bsCheckoutRenderPaymentPreview();
    } else {
      $('#bs-payment-methods').html('');
      $('[data-bs-payment-status]').text('Спосіб оплати можна обрати після завантаження блоку.');
    }
OLD
,
    <<<'NEW'
    if (window.bsCheckoutRenderPaymentPreview) {
      window.bsCheckoutRenderPaymentPreview();
    } else {
      $('#bs-payment-methods').html('');
      $('[data-bs-payment-status]').text('');
    }
NEW
,
    'checkout_payment_preview_fallback_status'
);

$updated[$checkoutTwig] = st2c3b_replace_once(
    $updated[$checkoutTwig],
    '<script src="catalog/view/javascript/checkout-state.js?v=st2c3-20260719"></script>',
    '<script src="catalog/view/javascript/checkout-state.js?v=st2c3b-20260720"></script>',
    'checkout_state_cache_buster'
);

foreach ($files as $relative => $spec) {
    if (strpos($updated[$relative], $spec['marker']) === false) {
        st2c3b_fail('postcheck_missing:' . $relative . ':' . $spec['marker']);
    }
}

foreach ([
    "isset(\$this->session->data['payment_methods'])",
    'Спосіб оплати можна обрати після завантаження блоку.',
    'Спосіб оплати можна обрати зараз. Застосуємо після заповнення доставки.',
] as $removed) {
    foreach ($updated as $relative => $content) {
        if (strpos($content, $removed) !== false) {
            st2c3b_fail('postcheck_old_logic_present:' . $relative . ':' . $removed);
        }
    }
}

$timestamp = gmdate('Ymd_His');
$backupDir = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . $patch . '-' . $timestamp;
$backups = [];

foreach ($targets as $relative => $target) {
    $backup = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0775, true) && !is_dir(dirname($backup))) {
        st2c3b_fail('backup_directory_create_failed:' . $relative);
    }

    if (!copy($target, $backup)) {
        st2c3b_fail('backup_copy_failed:' . $relative);
    }

    $backups[$relative] = $backup;
}

foreach ($targets as $relative => $target) {
    if (file_put_contents($target, $updated[$relative]) === false) {
        st2c3b_restore($targets, $backups);
        st2c3b_fail('target_write_failed:' . $relative . '; restored=yes');
    }
}

$lintOutput = [];
$lintCode = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($targets[$controller]) . ' 2>&1', $lintOutput, $lintCode);

if ($lintCode !== 0) {
    st2c3b_restore($targets, $backups);
    st2c3b_fail('php_l_failed; restored=yes; detail=' . implode(' | ', $lintOutput));
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
