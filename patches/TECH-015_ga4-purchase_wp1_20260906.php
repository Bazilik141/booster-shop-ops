<?php
/**
 * TECH-015 WP1: emit one GA4 purchase event from the Booster-owned success page.
 *
 * Scope:
 * - catalog/controller/checkout/success.php
 * - catalog/view/template/checkout/success.twig
 *
 * PHP 8.0 compatible. No database, payment, order-status, or vendor-extension writes.
 * Upload to ~/public_html and run there. The uploaded runner self-deletes on success.
 */
declare(strict_types=1);

const TECH015_PATCH_ID = 'TECH-015_ga4-purchase_wp1_20260906';

function tech015FailUnless(bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function tech015Write(string $path, string $content): void {
	$directory = dirname($path);

	if (!is_dir($directory)) {
		tech015FailUnless(mkdir($directory, 0755, true), 'Cannot create directory: ' . $directory);
	}

	$result = file_put_contents($path, $content, LOCK_EX);
	tech015FailUnless($result === strlen($content), 'Write failed: ' . $path);
	tech015FailUnless(hash_file('sha256', $path) === hash('sha256', $content), 'Written hash mismatch: ' . $path);
}

function tech015Lint(string $path): void {
	if (substr($path, -4) !== '.php') {
		return;
	}

	$output = [];
	$exit_code = 1;
	exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $exit_code);
	tech015FailUnless($exit_code === 0, 'PHP lint failed: ' . $path . ' :: ' . implode(' | ', $output));
	echo 'php_lint=ok file=' . $path . "\n";
}

try {
	tech015FailUnless(PHP_SAPI === 'cli', 'CLI only');
	tech015FailUnless(PHP_VERSION_ID >= 80000, 'PHP 8.0+ required');

	$root = realpath(getcwd());
	tech015FailUnless(is_string($root), 'Cannot resolve cwd');
	$root = str_replace('\\', '/', $root);
	tech015FailUnless(is_file($root . '/config.php') && is_dir($root . '/catalog'), 'Run from OpenCart public_html');
	tech015FailUnless(!is_dir($root . '/.git'), 'Refusing to run from repository source tree');

	$controller_file = 'catalog/controller/checkout/success.php';
	$template_file = 'catalog/view/template/checkout/success.twig';
	$paths = [
		$controller_file => $root . '/' . $controller_file,
		$template_file => $root . '/' . $template_file,
	];

	foreach ($paths as $relative => $path) {
		tech015FailUnless(is_file($path), 'Target missing: ' . $relative);
		tech015FailUnless(!is_link($path), 'Symlink target refused: ' . $relative);
	}

	$original = [
		$controller_file => file_get_contents($paths[$controller_file]),
		$template_file => file_get_contents($paths[$template_file]),
	];

	foreach ($original as $relative => $content) {
		tech015FailUnless(is_string($content), 'Read failed: ' . $relative);
	}

	$controller_marker = '// TECH-015-WP1: build GA4 purchase from the authorized order, never from the cleared cart.';
	$template_marker = '{# TECH-015-WP1: server-side session dedup permits this purchase payload only once per order. #}';
	$controller_marker_count = substr_count($original[$controller_file], $controller_marker);
	$template_marker_count = substr_count($original[$template_file], $template_marker);
	$applied_hashes = [
		$controller_file => '90bea312550bdd42b37129273e7a5aa8a3b80fc4ccc11a70e8ed6d053077737b',
		$template_file => '4b61912437fcb79520425d8520453deb1ff1663bd86443ef045aef7971833252',
	];

	if ($controller_marker_count === 1 && $template_marker_count === 1) {
		foreach ($applied_hashes as $relative => $hash) {
			tech015FailUnless(hash('sha256', $original[$relative]) === $hash, 'Applied marker found but file drifted: ' . $relative);
		}
		echo "already_applied=yes\n";
		echo 'self_delete=' . (@unlink(__FILE__) ? 'ok' : 'failed remove_uploaded_patch_manually=yes') . "\n";
		exit(0);
	}

	tech015FailUnless($controller_marker_count === 0 && $template_marker_count === 0, 'Partial TECH-015 WP1 state refused');

	$expected_hashes = [
		$controller_file => 'de242821206a69f1f26c4e8f64848cf5bdceeaa21f4ce1d9a009048971306ee9',
		$template_file => '7f8b516d65877f3ce6f8f986307cf112c34c3175733aab3a2cc1b72b1cb85392',
	];

	foreach ($expected_hashes as $relative => $hash) {
		tech015FailUnless(hash('sha256', $original[$relative]) === $hash, 'Source SHA-256 mismatch: ' . $relative);
	}

	$controller_anchor_1 = <<<'PHP'
		$order_data = [];
		$order_items = [];
		$order_totals = [];
PHP;
	$controller_replacement_1 = <<<'PHP'
		$order_data = [];
		$order_items = [];
		$order_totals = [];
		$ga4_purchase_payload = null;
PHP;

	$controller_anchor_2 = <<<'PHP'
				foreach ($this->model_checkout_order->getProducts((int)$order_id) as $product) {
					$price = (float)$product['price'] + ($this->config->get('config_tax') ? (float)$product['tax'] : 0);
					$total = (float)$product['total'] + ($this->config->get('config_tax') ? ((float)$product['tax'] * (int)$product['quantity']) : 0);

					$order_items[] = [
						'name'     => $product['name'] ?? '',
						'model'    => $product['model'] ?? '',
						'quantity' => (int)($product['quantity'] ?? 0),
						'price'    => $this->currency->format($price, $currency_code, $currency_value),
						'total'    => $this->currency->format($total, $currency_code, $currency_value),
					];
				}

				foreach ($this->model_checkout_order->getTotals((int)$order_id) as $total) {
					$_code  = $total['code'] ?? '';
					$_val   = (float)($total['value'] ?? 0);
					// R-11-FIX: skip zero-value shipping (НП placeholder before module config)
					if ($_val == 0.0 && in_array($_code, ['shipping', 'pinta_nova_poshta'], true)) {
						continue;
					}
					$order_totals[] = [
						'code'  => $_code,
						'title' => $total['title'] ?? '',
						'value' => $_val,
						'text'  => $this->currency->format($_val, $currency_code, $currency_value),
					];
				}
PHP;
	$controller_replacement_2 = <<<'PHP'
				// TECH-015-WP1: build GA4 purchase from the authorized order, never from the cleared cart.
				$ga4_purchase_items = [];

				foreach ($this->model_checkout_order->getProducts((int)$order_id) as $product) {
					$price = (float)$product['price'] + ($this->config->get('config_tax') ? (float)$product['tax'] : 0);
					$total = (float)$product['total'] + ($this->config->get('config_tax') ? ((float)$product['tax'] * (int)$product['quantity']) : 0);

					$order_items[] = [
						'name'     => $product['name'] ?? '',
						'model'    => $product['model'] ?? '',
						'quantity' => (int)($product['quantity'] ?? 0),
						'price'    => $this->currency->format($price, $currency_code, $currency_value),
						'total'    => $this->currency->format($total, $currency_code, $currency_value),
					];

					$ga4_purchase_items[] = [
						'item_id'   => (int)($product['product_id'] ?? 0),
						'item_name' => html_entity_decode((string)($product['name'] ?? ''), ENT_QUOTES, 'UTF-8'),
						'price'     => (float)$this->currency->format((float)($product['price'] ?? 0), $currency_code, $currency_value, false),
						'quantity'  => (int)($product['quantity'] ?? 0),
					];
				}

				$ga4_total = null;
				$ga4_tax = 0.0;
				$ga4_shipping = 0.0;
				$ga4_coupon = null;

				foreach ($this->model_checkout_order->getTotals((int)$order_id) as $total) {
					$_code  = (string)($total['code'] ?? '');
					$_val   = (float)($total['value'] ?? 0);

					if ($_code === 'total') {
						$ga4_total = $_val;
					} elseif ($_code === 'tax') {
						$ga4_tax += $_val;
					} elseif (in_array($_code, ['shipping', 'pinta_nova_poshta'], true)) {
						$ga4_shipping += $_val;
					} elseif ($_code === 'coupon') {
						$coupon_title = trim((string)($total['title'] ?? ''));
						if (preg_match('/\(([^()]*)\)\s*$/u', $coupon_title, $coupon_match) === 1) {
							$ga4_coupon = trim((string)$coupon_match[1]);
						}
					}

					// R-11-FIX: skip zero-value shipping (НП placeholder before module config)
					if ($_val == 0.0 && in_array($_code, ['shipping', 'pinta_nova_poshta'], true)) {
						continue;
					}
					$order_totals[] = [
						'code'  => $_code,
						'title' => $total['title'] ?? '',
						'value' => $_val,
						'text'  => $this->currency->format($_val, $currency_code, $currency_value),
					];
				}

				if (
					!$pay003_recovery &&
					(int)($this->session->data['bs_ga4_purchase_emitted_order_id'] ?? 0) !== (int)$order_id
				) {
					$ga4_total = $ga4_total ?? (float)($order_info['total'] ?? 0);
					$ga4_ecommerce = [
						'transaction_id' => (int)$order_id,
						'currency'       => (string)$currency_code,
						// Match the vendor module: revenue excludes tax and shipping.
						'value'          => (float)$this->currency->format($ga4_total - $ga4_tax - $ga4_shipping, $currency_code, $currency_value, false),
						'tax'            => (float)$this->currency->format($ga4_tax, $currency_code, $currency_value, false),
						'shipping'       => (float)$this->currency->format($ga4_shipping, $currency_code, $currency_value, false),
						'items'          => $ga4_purchase_items,
					];

					if (is_string($ga4_coupon) && $ga4_coupon !== '') {
						$ga4_ecommerce['coupon'] = $ga4_coupon;
					}

					$ga4_json = json_encode(
						['ecommerce' => $ga4_ecommerce],
						JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PRESERVE_ZERO_FRACTION
					);

					if (is_string($ga4_json)) {
						$ga4_purchase_payload = $ga4_json;
						$this->session->data['bs_ga4_purchase_emitted_order_id'] = (int)$order_id;
					}
				}
PHP;

	$controller_anchor_3 = <<<'PHP'
		$data['order_data'] = $order_data;
		$data['order_items'] = $order_items;
		$data['order_totals'] = $order_totals;
PHP;
	$controller_replacement_3 = <<<'PHP'
		$data['order_data'] = $order_data;
		$data['order_items'] = $order_items;
		$data['order_totals'] = $order_totals;
		$data['ga4_purchase_payload'] = $ga4_purchase_payload;
PHP;

	$template_anchor = <<<'TWIG'
      {% endif %}

      {{ content_bottom }}</main>
TWIG;
	$template_replacement = <<<'TWIG'
      {% endif %}

      {% if ga4_purchase_payload %}
        {# TECH-015-WP1: server-side session dedup permits this purchase payload only once per order. #}
        <script>ps_dataLayer.pushEventData('purchase', {{ ga4_purchase_payload|raw }});</script>
      {% endif %}

      {{ content_bottom }}</main>
TWIG;

	$edits = [
		$controller_file => [
			[$controller_anchor_1, $controller_replacement_1],
			[$controller_anchor_2, $controller_replacement_2],
			[$controller_anchor_3, $controller_replacement_3],
		],
		$template_file => [
			[$template_anchor, $template_replacement],
		],
	];

	$candidates = $original;
	foreach ($edits as $relative => $file_edits) {
		foreach ($file_edits as [$before, $after]) {
			tech015FailUnless(substr_count($candidates[$relative], $before) === 1, 'Anchor count mismatch: ' . $relative);
			$candidates[$relative] = str_replace($before, $after, $candidates[$relative]);
		}
	}

	tech015FailUnless(substr_count($candidates[$controller_file], $controller_marker) === 1, 'Controller marker assertion failed');
	tech015FailUnless(substr_count($candidates[$template_file], $template_marker) === 1, 'Template marker assertion failed');
	tech015FailUnless(substr_count($candidates[$template_file], "pushEventData('purchase'") === 1, 'Purchase snippet count assertion failed');
	tech015FailUnless(
		substr_count(
			$candidates[$controller_file],
			"!\$pay003_recovery &&\n\t\t\t\t\t(int)(\$this->session->data['bs_ga4_purchase_emitted_order_id'] ?? 0) !== (int)\$order_id"
		) === 1,
		'PAY-003 and dedup guard assertion failed'
	);

	$backup = $root . '/_patch_backups/' . TECH015_PATCH_ID . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
	tech015FailUnless(!is_link($root . '/_patch_backups'), 'Backup root symlink refused');

	echo 'cwd=' . $root . "\n";
	echo 'time=' . date('c') . "\n";
	echo 'backup=' . $backup . "\n";

	foreach ($paths as $relative => $path) {
		tech015Write($backup . '/original/' . $relative, $original[$relative]);
		tech015Write($backup . '/generated/' . $relative, $candidates[$relative]);
		tech015Lint($backup . '/generated/' . $relative);
		echo 'backup_file=' . $backup . '/original/' . $relative . "\n";
	}

	$written = [];
	try {
		foreach ($paths as $relative => $path) {
			tech015FailUnless(hash_file('sha256', $path) === $expected_hashes[$relative], 'Source changed during apply: ' . $relative);
			tech015Write($path, $candidates[$relative]);
			$written[] = $relative;
			tech015Lint($path);
			echo 'changed_file=' . $relative . "\n";
			echo 'after_sha256=' . hash_file('sha256', $path) . "\n";
		}
	} catch (Throwable $write_error) {
		foreach (array_reverse($written) as $relative) {
			tech015Write($paths[$relative], $original[$relative]);
		}
		throw new RuntimeException('Source restored after write failure: ' . $write_error->getMessage());
	}

	echo "database_touched=no\n";
	echo "done=ok\n";
	echo 'self_delete=' . (@unlink(__FILE__) ? 'ok' : 'failed remove_uploaded_patch_manually=yes') . "\n";
} catch (Throwable $error) {
	fwrite(STDERR, 'ERROR: ' . $error->getMessage() . "\n");
	exit(1);
}
