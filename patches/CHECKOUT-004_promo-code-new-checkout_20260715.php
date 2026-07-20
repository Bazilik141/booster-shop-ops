<?php
/**
 * CHECKOUT-004 — restore coupon/First15 endpoint and connect the RD-13 promo UI.
 *
 * DB: no schema or data changes. Runtime reads order/order_total only for First15 reuse guard.
 * Rollback: restore files from _patch_backups/CHECKOUT-004_promo-code-new-checkout_20260715-<timestamp>/.
 * Scope: promo endpoint + new-checkout promo UI only. Does not change ST-2c, shipping, payment,
 * free-shipping RD13-STUB, or the explicit checkout/confirm.confirm order-write gate.
 */
declare(strict_types=1);

const C004_ID = 'CHECKOUT-004_promo-code-new-checkout_20260715';

function c004_fail(string $message): void {
	throw new RuntimeException($message);
}

function c004_path(string $root, string $relative): string {
	return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function c004_read(string $root, string $relative): string {
	$path = c004_path($root, $relative);
	if (!is_file($path)) {
		c004_fail('missing_file=' . $relative);
	}
	$contents = file_get_contents($path);
	if ($contents === false) {
		c004_fail('read_failed=' . $relative);
	}
	return $contents;
}

function c004_eol(string $value): string {
	return str_contains($value, "\r\n") ? "\r\n" : "\n";
}

function c004_with_eol(string $value, string $eol): string {
	return $eol === "\n" ? $value : str_replace("\n", $eol, $value);
}

function c004_replace_once(string $source, string $find, string $replace, string $label): string {
	$eol = c004_eol($source);
	$find = c004_with_eol($find, $eol);
	$replace = c004_with_eol($replace, $eol);
	$count = substr_count($source, $find);
	if ($count !== 1) {
		c004_fail('anchor_count_' . $label . '=' . $count . '; expected=1');
	}
	return str_replace($find, $replace, $source);
}

function c004_backup(string $root, string $relative, string $backupDir): void {
	$source = c004_path($root, $relative);
	$destination = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
	if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0775, true) && !is_dir(dirname($destination))) {
		c004_fail('backup_dir_create_failed=' . $relative);
	}
	if (!copy($source, $destination)) {
		c004_fail('backup_failed=' . $relative);
	}
}

function c004_write(string $root, string $relative, string $contents, string $backupDir, array &$changed): void {
	$path = c004_path($root, $relative);
	$existing = is_file($path) ? file_get_contents($path) : null;
	if ($existing === $contents) {
		return;
	}
	if ($existing !== null) {
		c004_backup($root, $relative, $backupDir);
	} elseif (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) {
		c004_fail('target_dir_create_failed=' . $relative);
	}
	if (file_put_contents($path, $contents) === false) {
		c004_fail('write_failed=' . $relative);
	}
	$changed[] = $relative;
}

function c004_lint(string $root, string $relative): void {
	$path = c004_path($root, $relative);
	$output = [];
	$status = 0;
	exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $status);
	if ($status !== 0) {
		c004_fail('php_lint_failed=' . $relative . '; ' . implode(' | ', $output));
	}
	echo 'php_lint=ok ' . $relative . PHP_EOL;
}

function c004_restore(string $root, string $backupDir, array $changed): void {
	foreach ($changed as $relative) {
		$backup = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
		if (is_file($backup)) {
			@copy($backup, c004_path($root, $relative));
		} else {
			@unlink(c004_path($root, $relative));
		}
	}
}

$root = getcwd();
$required = [
	'catalog/controller/checkout/register.php',
	'catalog/controller/checkout/confirm.php',
	'catalog/view/template/checkout/checkout.twig',
	'catalog/view/javascript/checkout-reskin.js',
];
$changed = [];
$backupDir = '';

try {
	foreach ($required as $relative) {
		c004_read($root, $relative);
	}

	$register = c004_read($root, 'catalog/controller/checkout/register.php');
	$confirm = c004_read($root, 'catalog/controller/checkout/confirm.php');
	$twig = c004_read($root, 'catalog/view/template/checkout/checkout.twig');
	$reskin = c004_read($root, 'catalog/view/javascript/checkout-reskin.js');
	$modelPath = c004_path($root, 'catalog/model/checkout/booster_coupon.php');
	$controllerPath = c004_path($root, 'catalog/controller/checkout/coupon.php');
	$model = is_file($modelPath) ? (string)file_get_contents($modelPath) : '';
	$controller = is_file($controllerPath) ? (string)file_get_contents($controllerPath) : '';

	$markers = [
		str_contains($register, 'CHECKOUT-004: queue First15 after new-account checkout registration'),
		str_contains($confirm, 'CHECKOUT-004: make coupon total available to read-only confirm previews'),
		str_contains($twig, 'CHECKOUT-004: response-only promo summary bridge'),
		str_contains($twig, 'CHECKOUT-004: refresh First15 after register.save'),
		str_contains($reskin, 'function ensurePromoCoupon()'),
		str_contains($model, 'CHECKOUT-004: runtime coupon and First15 helper'),
		str_contains($controller, 'CHECKOUT-004: response-only checkout promo endpoint'),
	];
	if (count(array_filter($markers)) === count($markers)) {
		echo 'patch=' . C004_ID . PHP_EOL;
		echo 'already_applied=yes' . PHP_EOL;
		echo 'changed_files=0' . PHP_EOL;
		echo 'done=ok' . PHP_EOL;
		@unlink(__FILE__);
		exit(0);
	}
	if (count(array_filter($markers)) > 0) {
		c004_fail('partial_previous_application_detected');
	}
	if ($model !== '' || $controller !== '') {
		c004_fail('unexpected_existing_coupon_endpoint_or_model; manual_review_required');
	}

	$registerNew = c004_replace_once($register, <<<'PHP'
					// Create customer token
					$this->session->data['customer_token'] = oc_token(26);

					$json['success'] = $this->language->get('text_success_add');
PHP, <<<'PHP'
					// Create customer token
					$this->session->data['customer_token'] = oc_token(26);

					// CHECKOUT-004: queue First15 after new-account checkout registration.
					// The promo endpoint consumes it without checkout/confirm.confirm.
					if (empty($this->session->data['coupon'])) {
						$this->session->data['welcome_coupon_pending'] = 'First15';
					}

					$json['success'] = $this->language->get('text_success_add');
PHP, 'register_first15_queue');

	$confirmNew = c004_replace_once($confirm, <<<'PHP'
		$this->load->language('checkout/confirm');

		// Order Totals
PHP, <<<'PHP'
		$this->load->language('checkout/confirm');

		// CHECKOUT-004: make coupon total available to read-only confirm previews.
		$this->load->model('checkout/booster_coupon');
		$this->model_checkout_booster_coupon->prepareCouponTotal();

		// Order Totals
PHP, 'confirm_prepare_coupon_total');

	$twigNew = c004_replace_once($twig, <<<'JS'
  function bsCheckoutCachedSummaryHtml() {
    bsCheckoutCaptureInitialSummary();

    if (!bsCheckoutInitialSummaryHtml) {
      return '';
    }

    return '<div class="bs-confirm-deferred-summary bs-co-src-table">' + bsCheckoutInitialSummaryHtml + '</div>';
  }
JS, <<<'JS'
  function bsCheckoutCachedSummaryHtml() {
    bsCheckoutCaptureInitialSummary();

    if (!bsCheckoutInitialSummaryHtml) {
      return '';
    }

    return '<div class="bs-confirm-deferred-summary bs-co-src-table">' + bsCheckoutInitialSummaryHtml + '</div>';
  }

  // CHECKOUT-004: response-only promo summary bridge. This never calls confirm.confirm.
  window.bsCheckoutUpdateCachedSummaryHtml = function(summaryHtml) {
    if (!summaryHtml) {
      return false;
    }

    bsCheckoutInitialSummaryHtml = summaryHtml;
    bsCheckoutInitialSummaryCaptured = true;

    if (window.bsCheckoutRefreshConfirmIfPaymentReady) {
      window.bsCheckoutRefreshConfirmIfPaymentReady();
    }

    return true;
  };
JS, 'twig_summary_bridge');

	$twigNew = c004_replace_once($twigNew, <<<'JS'
      if (typeof window.bsCheckoutLoadShippingMethods === 'function') {
        window.bsCheckoutResetMethodState('address');
        window.setTimeout(function() {
          window.bsCheckoutLoadShippingMethods({ autoSelect: true });
        }, 250);
      }
JS, <<<'JS'
      // CHECKOUT-004: refresh First15 after register.save without an order-write request.
      if (typeof window.bsCheckoutRefreshPromoCouponSummary === 'function') {
        window.bsCheckoutRefreshPromoCouponSummary({ quiet: true });
      }

      if (typeof window.bsCheckoutLoadShippingMethods === 'function') {
        window.bsCheckoutResetMethodState('address');
        window.setTimeout(function() {
          window.bsCheckoutLoadShippingMethods({ autoSelect: true });
        }, 250);
      }
JS, 'twig_register_coupon_refresh');

	$twigNew = c004_replace_once($twigNew, '<script src="catalog/view/javascript/checkout-reskin.js?v=rd13.1i-20260712"></script>', '<script src="catalog/view/javascript/checkout-reskin.js?v=checkout004-20260715"></script>', 'reskin_cache_version');

	$oldPromo = <<<'JS'
  function ensurePromoStub() {
    var tail = document.getElementById('bs-co-summary-tail');

    if (!tail || tail.querySelector('[data-rd13-promo-stub]')) {
      return;
    }

    var promo = document.createElement('div');
    promo.className = 'bs-field';
    promo.setAttribute('data-rd13-promo-stub', '1');
    // RD13-STUB: replace with real coupon wiring once the endpoint ships.
    promo.innerHTML =
      '<label>Промокод</label>' +
      '<div class="bs-co-promo-input">' +
        '<input class="bs-input" name="rd13_stub_coupon" placeholder="Введіть промокод">' +
        '<button type="button" class="bs-btn bs-btn-secondary" data-co-promo-stub>Застосувати</button>' +
      '</div>' +
      '<div class="bs-co-field-hint" data-co-promo-stub-msg hidden>Промокоди зʼявляться незабаром</div>';
    tail.insertBefore(promo, tail.firstChild);

    var button = promo.querySelector('[data-co-promo-stub]');
    button.addEventListener('click', function () {
      var message = promo.querySelector('[data-co-promo-stub-msg]');
      if (message) {
        message.hidden = false;
      }
    });
  }
JS;
	$newPromo = <<<'JS'
  function ensurePromoCoupon() {
    var tail = document.getElementById('bs-co-summary-tail');

    if (!tail) {
      return;
    }

    var promo = tail.querySelector('[data-checkout004-promo]');
    if (!promo) {
      promo = document.createElement('div');
      promo.className = 'bs-field';
      promo.setAttribute('data-checkout004-promo', '1');
      promo.innerHTML =
        '<label>Промокод</label>' +
        '<div data-co-promo-empty>' +
          '<div class="bs-co-promo-input">' +
            '<input class="bs-input" name="checkout004_coupon" placeholder="Введіть промокод" autocomplete="off">' +
            '<button type="button" class="bs-btn bs-btn-secondary" data-co-promo-apply>Застосувати</button>' +
          '</div>' +
        '</div>' +
        '<div data-co-promo-applied hidden>' +
          '<span data-co-promo-code></span> ' +
          '<button type="button" class="bs-btn bs-btn-secondary" data-co-promo-remove>Прибрати</button>' +
        '</div>' +
        '<div class="bs-co-field-hint" data-co-promo-status role="status" aria-live="polite"></div>';
      tail.insertBefore(promo, tail.firstChild);
    }

    if (promo.dataset.checkout004Bound === '1') {
      return;
    }
    promo.dataset.checkout004Bound = '1';

    var input = promo.querySelector('[name="checkout004_coupon"]');
    var empty = promo.querySelector('[data-co-promo-empty]');
    var applied = promo.querySelector('[data-co-promo-applied]');
    var code = promo.querySelector('[data-co-promo-code]');
    var status = promo.querySelector('[data-co-promo-status]');
    var busy = false;

    function setStatus(message, isError) {
      if (!status) {
        return;
      }
      status.textContent = message || '';
      status.classList.toggle('bs-is-error', !!isError);
      status.classList.toggle('bs-is-success', !!message && !isError);
    }

    function payload(extra) {
      var data = extra || {};
      var email = document.getElementById('input-email');
      if (email && email.value) {
        data.email = email.value;
      }
      return data;
    }

    function render(json, options) {
      json = json || {};
      options = options || {};
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

      if (json.summary_html && window.bsCheckoutUpdateCachedSummaryHtml) {
        window.bsCheckoutUpdateCachedSummaryHtml(json.summary_html);
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
    }

    function request(action, data, options) {
      options = options || {};
      if (busy) {
        return;
      }
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
    }

    window.bsCheckoutRefreshPromoCouponSummary = function(options) {
      request('summary', {}, options || { quiet: true });
    };

    promo.querySelector('[data-co-promo-apply]').addEventListener('click', function() {
      setStatus('Застосовуємо промокод...', false);
      request('apply', { coupon: input ? input.value : '' }, {});
    });
    promo.querySelector('[data-co-promo-remove]').addEventListener('click', function() {
      setStatus('Прибираємо промокод...', false);
      request('remove', {}, {});
    });
    input.addEventListener('keydown', function(event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        promo.querySelector('[data-co-promo-apply]').click();
      }
    });

    window.bsCheckoutRefreshPromoCouponSummary({ quiet: true });
  }
JS;
	$reskinNew = c004_replace_once($reskin, $oldPromo, $newPromo, 'reskin_promo_stub');
	$reskinNew = c004_replace_once($reskinNew, '        ensurePromoStub,', '        ensurePromoCoupon,', 'reskin_promo_runner');

	$modelNew = <<<'PHP'
<?php
namespace Opencart\Catalog\Model\Checkout;

/** CHECKOUT-004: runtime coupon and First15 helper. */
class BoosterCoupon extends \Opencart\System\Engine\Model {
	public function prepareCouponTotal(): void {
		$this->config->set('total_coupon_status', 1);
		$this->config->set('coupon_status', 1);
		if ($this->config->get('total_coupon_sort_order') === null || $this->config->get('total_coupon_sort_order') === '') {
			$this->config->set('total_coupon_sort_order', 4);
		}
	}

	public function applyPendingWelcomeCoupon(string $email = ''): array {
		if (empty($this->session->data['welcome_coupon_pending']) || !$this->cart->hasProducts()) {
			return [];
		}
		$coupon = trim((string)$this->session->data['welcome_coupon_pending']);
		unset($this->session->data['welcome_coupon_pending']);
		if ($coupon === '' || !empty($this->session->data['coupon'])) {
			return [];
		}
		$result = $this->applyCouponCode($coupon, $email);
		if (!empty($result['success'])) {
			$this->session->data['welcome_coupon_applied'] = $coupon;
		} elseif (!empty($result['error'])) {
			$this->session->data['welcome_coupon_error'] = $result['error'];
		}
		return $result;
	}

	public function applyCouponCode(string $coupon, string $email = ''): array {
		$this->prepareCouponTotal();
		$coupon = trim($coupon);
		if ($coupon === '') {
			unset($this->session->data['coupon']);
			return ['error' => 'Введіть промокод.'];
		}
		$this->load->model('marketing/coupon');
		if (!$this->model_marketing_coupon->getCoupon($coupon)) {
			unset($this->session->data['coupon']);
			return ['error' => 'Промокод недійсний або вже був використаний.'];
		}
		if (strcasecmp($coupon, 'First15') === 0 && $this->hasCouponOrderUsage($coupon, $email)) {
			unset($this->session->data['coupon']);
			return ['error' => 'Промокод First15 вже був використаний для цього акаунта.'];
		}
		$this->session->data['coupon'] = $coupon;
		return ['success' => 'Промокод застосовано.'];
	}

	public function hasCouponOrderUsage(string $coupon, string $email = ''): bool {
		$conditions = [];
		if ($this->customer->isLogged()) {
			$conditions[] = "`o`.`customer_id` = '" . (int)$this->customer->getId() . "'";
		}
		if ($email === '' && $this->customer->isLogged() && method_exists($this->customer, 'getEmail')) {
			$email = trim((string)$this->customer->getEmail());
		}
		if ($email !== '') {
			$conditions[] = "LOWER(`o`.`email`) = '" . $this->db->escape(strtolower($email)) . "'";
		}
		if (!$conditions) {
			return false;
		}
		$like = $this->db->escape('%(' . trim($coupon) . ')%');
		$query = $this->db->query(
			"SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "order_total` `ot` " .
			"LEFT JOIN `" . DB_PREFIX . "order` `o` ON (`ot`.`order_id` = `o`.`order_id`) " .
			"WHERE `ot`.`code` = 'coupon' AND `ot`.`title` LIKE '" . $like . "' " .
			"AND `o`.`order_status_id` > '0' AND (" . implode(' OR ', $conditions) . ")"
		);
		return (int)($query->row['total'] ?? 0) > 0;
	}
}
PHP;

	$controllerNew = <<<'PHP'
<?php
namespace Opencart\Catalog\Controller\Checkout;

/** CHECKOUT-004: response-only checkout promo endpoint. */
class Coupon extends \Opencart\System\Engine\Controller {
	public function summary(): void {
		$this->load->model('checkout/booster_coupon');
		$this->output($this->model_checkout_booster_coupon->applyPendingWelcomeCoupon($this->postedEmail()));
	}

	public function apply(): void {
		$this->load->model('checkout/booster_coupon');
		$coupon = isset($this->request->post['coupon']) ? trim((string)$this->request->post['coupon']) : '';
		$this->output($this->model_checkout_booster_coupon->applyCouponCode($coupon, $this->postedEmail()));
	}

	public function remove(): void {
		unset($this->session->data['coupon']);
		$this->output(['success' => 'Промокод прибрано.']);
	}

	private function postedEmail(): string {
		return isset($this->request->post['email']) ? trim((string)$this->request->post['email']) : '';
	}

	private function output(array $result): void {
		$this->load->model('checkout/booster_coupon');
		$this->model_checkout_booster_coupon->prepareCouponTotal();
		$summary = $this->summaryData();
		$result += [
			'coupon' => $this->session->data['coupon'] ?? '',
			'coupon_discount_text' => $this->discountText(),
			'welcome_coupon_applied' => $this->session->data['welcome_coupon_applied'] ?? '',
			'welcome_coupon_error' => $this->session->data['welcome_coupon_error'] ?? '',
			'summary_html' => $summary['html'],
			'totals' => $summary['totals'],
			'total_text' => $summary['total_text'],
		];
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($result));
	}

	private function discountText(): string {
		$coupon = trim((string)($this->session->data['coupon'] ?? ''));
		if ($coupon === '') {
			return '';
		}
		$this->load->model('marketing/coupon');
		$info = $this->model_marketing_coupon->getCoupon($coupon);
		if (!$info || ($info['type'] ?? '') !== 'P') {
			return '';
		}
		$discount = rtrim(rtrim(number_format((float)($info['discount'] ?? 0), 2, '.', ''), '0'), '.');
		return $discount === '' ? '' : '−' . $discount . '%';
	}

	private function summaryData(): array {
		$this->load->language('checkout/confirm');
		$this->load->model('checkout/cart');
		$totals = [];
		$taxes = $this->cart->getTaxes();
		$total = 0;
		($this->model_checkout_cart->getTotals)($totals, $taxes, $total);
		$rows = [];
		foreach ($totals as $row) {
			$rows[] = [
				'code' => (string)($row['code'] ?? ''),
				'title' => (string)($row['title'] ?? ''),
				'value' => (float)($row['value'] ?? 0),
				'text' => $this->currency->format((float)($row['value'] ?? 0), $this->session->data['currency']),
			];
		}
		$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
		$html = '<div class="table-responsive"><table class="table table-bordered table-hover"><thead><tr><th>' . $e($this->language->get('column_product')) . '</th><th class="text-end">' . $e($this->language->get('column_total')) . '</th></tr></thead><tbody>';
		$priceStatus = $this->customer->isLogged() || !$this->config->get('config_customer_price');
		foreach ($this->model_checkout_cart->getProducts() as $product) {
			$html .= '<tr><td>' . (int)($product['quantity'] ?? 0) . 'x ' . $e($product['name'] ?? '') . '</td><td class="text-end">' . $e($priceStatus ? ($product['total_text'] ?? '') : '') . '</td></tr>';
		}
		$html .= '</tbody><tfoot>';
		foreach ($rows as $row) {
			$html .= '<tr><td class="text-end"><strong>' . $e($row['title']) . '</strong></td><td class="text-end">' . $e($row['text']) . '</td></tr>';
		}
		$html .= '</tfoot></table></div>';
		return ['html' => $html, 'totals' => $rows, 'total_text' => $rows ? (string)end($rows)['text'] : ''];
	}
}
PHP;

	if (str_contains($reskinNew, 'rd13_stub_coupon') || str_contains($reskinNew, 'data-co-promo-stub')) {
		c004_fail('postcompose_promo_stub_still_present');
	}
	if (substr_count($reskinNew, 'RD13-STUB') < 2) {
		c004_fail('postcompose_free_shipping_stub_missing');
	}
	if (str_contains($reskinNew, 'checkout/confirm.confirm') || str_contains($controllerNew, 'confirm.confirm')) {
		c004_fail('postcompose_confirm_write_call_detected');
	}

	$backupDir = c004_path($root, '_patch_backups/' . C004_ID . '-' . date('Ymd-His'));
	if (!mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
		c004_fail('backup_root_create_failed');
	}

	c004_write($root, 'catalog/controller/checkout/register.php', $registerNew, $backupDir, $changed);
	c004_write($root, 'catalog/controller/checkout/confirm.php', $confirmNew, $backupDir, $changed);
	c004_write($root, 'catalog/view/template/checkout/checkout.twig', $twigNew, $backupDir, $changed);
	c004_write($root, 'catalog/view/javascript/checkout-reskin.js', $reskinNew, $backupDir, $changed);
	c004_write($root, 'catalog/model/checkout/booster_coupon.php', $modelNew, $backupDir, $changed);
	c004_write($root, 'catalog/controller/checkout/coupon.php', $controllerNew, $backupDir, $changed);

	foreach ([
		'catalog/controller/checkout/register.php',
		'catalog/controller/checkout/confirm.php',
		'catalog/model/checkout/booster_coupon.php',
		'catalog/controller/checkout/coupon.php',
	] as $relative) {
		c004_lint($root, $relative);
	}

	if (str_contains(c004_read($root, 'catalog/view/javascript/checkout-reskin.js'), 'rd13_stub_coupon')) {
		c004_fail('postcheck_promo_stub_present');
	}
	if (!str_contains(c004_read($root, 'catalog/view/javascript/checkout-reskin.js'), 'FREE_SHIP_THRESHOLD')) {
		c004_fail('postcheck_free_shipping_stub_missing');
	}

	echo 'patch=' . C004_ID . PHP_EOL;
	echo 'cwd=' . $root . PHP_EOL;
	echo 'time=' . date('c') . PHP_EOL;
	echo 'db_schema_changes=none' . PHP_EOL;
	echo 'db_data_changes=none_by_patch; runtime read of order/order_total for First15 guard only' . PHP_EOL;
	echo 'already_applied=no' . PHP_EOL;
	echo 'backup_dir=' . $backupDir . PHP_EOL;
	echo 'changed_files=' . count($changed) . PHP_EOL;
	foreach ($changed as $relative) {
		echo 'changed=' . $relative . PHP_EOL;
	}
	echo 'done=ok' . PHP_EOL;
	@unlink(__FILE__);
} catch (Throwable $e) {
	if ($backupDir !== '' && $changed) {
		c004_restore($root, $backupDir, $changed);
		echo 'rollback=attempted' . PHP_EOL;
	}
	fwrite(STDERR, 'error=' . $e->getMessage() . PHP_EOL);
	exit(1);
}
