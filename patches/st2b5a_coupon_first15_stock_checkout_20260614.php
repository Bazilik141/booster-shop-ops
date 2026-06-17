<?php
/**
 * ST-2b.5A: stock checkout coupon / First15 parity.
 *
 * Upload to public_html and run:
 *   php st2b5a_coupon_first15_stock_checkout_20260614.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$patchId = 'st2b5a_coupon_first15_stock_checkout_20260614';
$root = getenv('BS_PATCH_ROOT') ?: getcwd();
$backupDir = rtrim($root, "/\\") . '/_patch_backups/' . $patchId . '-' . date('Ymd-His');
$changed = [];
$alreadyApplied = true;

function bs5a_fail(string $message): void {
    fwrite(STDERR, "error=$message" . PHP_EOL);
    exit(1);
}

function bs5a_path(string $root, string $relative): string {
    return rtrim($root, "/\\") . '/' . ltrim($relative, "/\\");
}

function bs5a_read(string $root, string $relative): string {
    $path = bs5a_path($root, $relative);

    if (!is_file($path)) {
        bs5a_fail("missing_file:$relative");
    }

    $content = file_get_contents($path);

    if ($content === false) {
        bs5a_fail("read_failed:$relative");
    }

    return $content;
}

function bs5a_backup(string $root, string $relative, string $backupDir): void {
    $src = bs5a_path($root, $relative);
    $dst = rtrim($backupDir, "/\\") . '/' . $relative;
    $dir = dirname($dst);

    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        bs5a_fail("backup_mkdir_failed:$dir");
    }

    if (!copy($src, $dst)) {
        bs5a_fail("backup_failed:$relative");
    }
}

function bs5a_write(string $root, string $relative, string $content, string $backupDir, array &$changed, bool &$alreadyApplied): void {
    $path = bs5a_path($root, $relative);
    $exists = is_file($path);
    $current = $exists ? file_get_contents($path) : '';

    if ($exists && $current === false) {
        bs5a_fail("read_failed:$relative");
    }

    if ($exists && $current === $content) {
        return;
    }

    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true)) {
        bs5a_fail("backup_root_mkdir_failed:$backupDir");
    }

    if ($exists) {
        bs5a_backup($root, $relative, $backupDir);
    }

    $dir = dirname($path);

    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        bs5a_fail("target_mkdir_failed:$dir");
    }

    if (file_put_contents($path, $content) === false) {
        bs5a_fail("write_failed:$relative");
    }

    $changed[] = $relative;
    $alreadyApplied = false;
}

function bs5a_replace_once(string $content, string $search, string $replace, string $label): string {
    $count = substr_count($content, $search);

    if ($count !== 1) {
        bs5a_fail("anchor_count_failed:$label:$count");
    }

    return str_replace($search, $replace, $content);
}

function bs5a_add_file_once(string $root, string $relative, string $content, string $marker, string $backupDir, array &$changed, bool &$alreadyApplied): void {
    $path = bs5a_path($root, $relative);

    if (is_file($path)) {
        $current = bs5a_read($root, $relative);

        if (strpos($current, $marker) !== false) {
            bs5a_write($root, $relative, $content, $backupDir, $changed, $alreadyApplied);
            return;
        }

        bs5a_fail("target_exists_without_marker:$relative");
    }

    bs5a_write($root, $relative, $content, $backupDir, $changed, $alreadyApplied);
}

function bs5a_php_lint(string $root, string $relative): void {
    $path = bs5a_path($root, $relative);
    $cmd = 'php -l ' . escapeshellarg($path);
    exec($cmd, $output, $code);

    echo 'php_lint=' . $relative . ' exit=' . $code . PHP_EOL;

    if ($output) {
        echo 'php_lint_output=' . implode(' | ', $output) . PHP_EOL;
    }

    if ($code !== 0) {
        bs5a_fail("php_lint_failed:$relative");
    }
}

function bs5a_patch_checkout_controller(string $content): string {
    if (strpos($content, 'ST-2b.5A: prepare coupon total and pending First15') !== false) {
        return $content;
    }

    $old = <<<'PHP'
		$this->load->language('checkout/checkout');

		$this->document->setTitle($this->language->get('heading_title'));
PHP;

    $new = <<<'PHP'
		// ST-2b.5A: prepare coupon total and pending First15 before checkout render/GA4 view hooks.
		$this->load->model('checkout/booster_coupon');
		$this->model_checkout_booster_coupon->prepareCouponTotal();
		$this->model_checkout_booster_coupon->applyPendingWelcomeCoupon();

		$this->load->language('checkout/checkout');

		$this->document->setTitle($this->language->get('heading_title'));
PHP;

    $content = bs5a_replace_once($content, $old, $new, 'checkout_controller_prepare_coupon');

    $old = <<<'PHP'
		if ($this->cart->hasShipping()) {
			$data['shipping_method'] = $this->load->controller('checkout/shipping_method');
		} else {
			$data['shipping_method'] = '';
		}

		$data['payment_method'] = $this->load->controller('checkout/payment_method');
		$data['confirm'] = $this->load->controller('checkout/confirm');
PHP;

    $new = <<<'PHP'
		if ($this->cart->hasShipping()) {
			$data['shipping_method'] = $this->load->controller('checkout/shipping_method');
		} else {
			$data['shipping_method'] = '';
		}

		$data['payment_method'] = $this->load->controller('checkout/payment_method');
		$data['coupon'] = $this->load->controller('checkout/coupon');
		$data['confirm'] = $this->load->controller('checkout/confirm');
PHP;

    return bs5a_replace_once($content, $old, $new, 'checkout_controller_coupon_child');
}

function bs5a_patch_confirm_controller(string $content): string {
    if (strpos($content, 'ST-2b.5A: enable coupon total for deferred stock confirm') !== false) {
        return $content;
    }

    $old = <<<'PHP'
		$this->load->language('checkout/confirm');

		// Order Totals
PHP;

    $new = <<<'PHP'
		$this->load->language('checkout/confirm');

		// ST-2b.5A: enable coupon total for deferred stock confirm without changing DB settings.
		$this->load->model('checkout/booster_coupon');
		$this->model_checkout_booster_coupon->prepareCouponTotal();

		// Order Totals
PHP;

    return bs5a_replace_once($content, $old, $new, 'confirm_prepare_coupon_total');
}

function bs5a_patch_register_controller(string $content): string {
    if (strpos($content, 'ST-2b.5A: queue First15 for new checkout account') !== false) {
        return $content;
    }

    $old = <<<'PHP'
					// Create customer token
					$this->session->data['customer_token'] = oc_token(26);

					$json['success'] = $this->language->get('text_success_add');
PHP;

    $new = <<<'PHP'
					// Create customer token
					$this->session->data['customer_token'] = oc_token(26);

					// ST-2b.5A: queue First15 for new checkout account; checkout/coupon applies it without confirm.confirm.
					if (empty($this->session->data['coupon'])) {
						$this->session->data['welcome_coupon_pending'] = 'First15';
					}

					$json['success'] = $this->language->get('text_success_add');
PHP;

    return bs5a_replace_once($content, $old, $new, 'register_queue_first15');
}

function bs5a_patch_checkout_twig(string $content): string {
    if (strpos($content, 'ST-2b.5A coupon panel') !== false) {
        return $content;
    }

    $old = <<<'TWIG'
          <div id="checkout-payment-method" class="bs-checkout-panel bs-checkout-panel-choice mb-4">{{ payment_method }}</div>
          <div id="checkout-confirm" class="bs-checkout-panel bs-checkout-panel-summary">{{ confirm }}</div>
TWIG;

    $new = <<<'TWIG'
          <div id="checkout-payment-method" class="bs-checkout-panel bs-checkout-panel-choice mb-4">{{ payment_method }}</div>
          {% if coupon %}
            <div id="checkout-coupon" class="bs-checkout-panel bs-checkout-panel-choice mb-4">{{ coupon }}</div>
          {% endif %}
          <div id="checkout-confirm" class="bs-checkout-panel bs-checkout-panel-summary">{{ confirm }}</div>
TWIG;

    $content = bs5a_replace_once($content, $old, $new, 'checkout_twig_coupon_panel');

    $old = <<<'CSS'
#checkout-checkout #checkout-confirm [data-bs-deferred-confirm][disabled] {
  opacity: .55;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}
CSS;

    $new = <<<'CSS'
#checkout-checkout #checkout-confirm [data-bs-deferred-confirm][disabled] {
  opacity: .55;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}

/* ST-2b.5A coupon panel */
#checkout-checkout .bs-coupon-actions {
  display: grid;
  gap: 8px;
  grid-template-columns: minmax(0, 1fr) auto auto;
}

#checkout-checkout .bs-coupon-status {
  color: #5b6b7a;
  font-size: .88rem;
  min-height: 1.2em;
}

#checkout-checkout .bs-coupon-status.bs-is-error {
  color: #b42318;
}

#checkout-checkout .bs-coupon-status.bs-is-success {
  color: #087443;
}

@media (max-width: 480px) {
  #checkout-checkout .bs-coupon-actions {
    grid-template-columns: minmax(0, 1fr);
  }
}
CSS;

    $content = bs5a_replace_once($content, $old, $new, 'checkout_twig_coupon_css');

    $old = <<<'JS'
  function bsCheckoutCachedSummaryHtml() {
    bsCheckoutCaptureInitialSummary();

    if (!bsCheckoutInitialSummaryHtml) {
      return '';
    }

    return '<div class="bs-confirm-deferred-summary">' + bsCheckoutInitialSummaryHtml + '</div>';
  }
JS;

    $new = <<<'JS'
  function bsCheckoutCachedSummaryHtml() {
    bsCheckoutCaptureInitialSummary();

    if (!bsCheckoutInitialSummaryHtml) {
      return '';
    }

    return '<div class="bs-confirm-deferred-summary">' + bsCheckoutInitialSummaryHtml + '</div>';
  }

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
JS;

    $content = bs5a_replace_once($content, $old, $new, 'checkout_twig_summary_setter');

    $old = <<<'JS'
      if (typeof window.bsCheckoutLoadShippingMethods === 'function') {
        window.bsCheckoutResetMethodState('address');
        window.setTimeout(function() {
          window.bsCheckoutLoadShippingMethods({ autoSelect: true });
        }, 250);
      }
JS;

    $new = <<<'JS'
      if (typeof window.bsCheckoutRefreshCouponSummary === 'function') {
        window.bsCheckoutRefreshCouponSummary({ quiet: true });
      }

      if (typeof window.bsCheckoutLoadShippingMethods === 'function') {
        window.bsCheckoutResetMethodState('address');
        window.setTimeout(function() {
          window.bsCheckoutLoadShippingMethods({ autoSelect: true });
        }, 250);
      }
JS;

    $content = bs5a_replace_once($content, $old, $new, 'checkout_twig_register_success_coupon_refresh');

    return $content;
}

$model = <<<'PHP'
<?php
namespace Opencart\Catalog\Model\Checkout;

/**
 * ST-2b.5A helper: stock checkout coupon / First15 runtime parity.
 */
class BoosterCoupon extends \Opencart\System\Engine\Model {
	public function prepareCouponTotal(): void {
		$this->config->set('total_coupon_status', 1);
		$this->config->set('coupon_status', 1);

		if ($this->config->get('total_coupon_sort_order') === null || $this->config->get('total_coupon_sort_order') === '') {
			$this->config->set('total_coupon_sort_order', 4);
		}
	}

	public function applyPendingWelcomeCoupon(string $email = ''): array {
		if (empty($this->session->data['welcome_coupon_pending'])) {
			return [];
		}

		if (!$this->cart->hasProducts()) {
			return [];
		}

		$coupon = trim((string)$this->session->data['welcome_coupon_pending']);

		if ($coupon === '') {
			unset($this->session->data['welcome_coupon_pending']);
			return [];
		}

		if (!empty($this->session->data['coupon'])) {
			unset($this->session->data['welcome_coupon_pending']);
			return [];
		}

		$result = $this->applyCouponCode($coupon, $email);

		if (!empty($result['success'])) {
			$this->session->data['welcome_coupon_applied'] = $coupon;
		} elseif (!empty($result['error'])) {
			$this->session->data['welcome_coupon_error'] = $result['error'];
		}

		unset($this->session->data['welcome_coupon_pending']);

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

		$coupon_info = $this->model_marketing_coupon->getCoupon($coupon);

		if (!$coupon_info) {
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
		$coupon = trim($coupon);

		if ($coupon === '') {
			return false;
		}

		$conditions = [];
		$customer_id = $this->customer->isLogged() ? (int)$this->customer->getId() : 0;

		if ($customer_id > 0) {
			$conditions[] = "`o`.`customer_id` = '" . (int)$customer_id . "'";
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

		$coupon_like = $this->db->escape('%(' . $coupon . ')%');

		$query = $this->db->query(
			"SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "order_total` `ot` " .
			"LEFT JOIN `" . DB_PREFIX . "order` `o` ON (`ot`.`order_id` = `o`.`order_id`) " .
			"WHERE `ot`.`code` = 'coupon' " .
			"AND `ot`.`title` LIKE '" . $coupon_like . "' " .
			"AND `o`.`order_status_id` > '0' " .
			"AND (" . implode(' OR ', $conditions) . ")"
		);

		return (int)($query->row['total'] ?? 0) > 0;
	}
}
PHP;

$controller = <<<'PHP'
<?php
namespace Opencart\Catalog\Controller\Checkout;

/**
 * ST-2b.5A stock checkout coupon endpoint.
 */
class Coupon extends \Opencart\System\Engine\Controller {
	public function index(): string {
		$data['coupon'] = $this->session->data['coupon'] ?? '';
		$data['language'] = $this->config->get('config_language');

		return $this->load->view('checkout/coupon', $data);
	}

	public function summary(): void {
		$this->load->model('checkout/booster_coupon');

		$result = $this->model_checkout_booster_coupon->applyPendingWelcomeCoupon($this->getPostedEmail());
		$json = $this->buildResponse($result);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function apply(): void {
		$this->load->model('checkout/booster_coupon');

		$coupon = isset($this->request->post['coupon']) ? trim((string)$this->request->post['coupon']) : '';
		$result = $this->model_checkout_booster_coupon->applyCouponCode($coupon, $this->getPostedEmail());
		$json = $this->buildResponse($result);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function remove(): void {
		unset($this->session->data['coupon']);

		$json = $this->buildResponse(['success' => 'Промокод прибрано.']);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function getPostedEmail(): string {
		return isset($this->request->post['email']) ? trim((string)$this->request->post['email']) : '';
	}

	private function buildResponse(array $result = []): array {
		$this->load->model('checkout/booster_coupon');
		$this->model_checkout_booster_coupon->prepareCouponTotal();

		$summary = $this->buildSummary();

		return $result + [
			'coupon' => $this->session->data['coupon'] ?? '',
			'welcome_coupon_applied' => $this->session->data['welcome_coupon_applied'] ?? '',
			'welcome_coupon_error' => $this->session->data['welcome_coupon_error'] ?? '',
			'summary_html' => $summary['html'],
			'totals' => $summary['totals'],
			'total_text' => $summary['total_text'],
		];
	}

	private function buildSummary(): array {
		$this->load->language('checkout/confirm');
		$this->load->model('checkout/cart');

		$totals = [];
		$taxes = $this->cart->getTaxes();
		$total = 0;

		($this->model_checkout_cart->getTotals)($totals, $taxes, $total);

		$price_status = ($this->customer->isLogged() || !$this->config->get('config_customer_price'));
		$products = [];

		foreach ($this->model_checkout_cart->getProducts() as $product) {
			if (!empty($product['option'])) {
				foreach ($product['option'] as $key => $option) {
					$product['option'][$key]['value'] = (oc_strlen($option['value']) > 20 ? oc_substr($option['value'], 0, 20) . '..' : $option['value']);
				}
			}

			$products[] = [
				'name' => $product['name'] ?? '',
				'model' => $product['model'] ?? '',
				'quantity' => (int)($product['quantity'] ?? 0),
				'option' => $product['option'] ?? [],
				'total' => $price_status ? ($product['total_text'] ?? '') : '',
				'href' => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . (int)$product['product_id'])
			];
		}

		$total_rows = [];

		foreach ($totals as $row) {
			$total_rows[] = [
				'code' => $row['code'] ?? '',
				'title' => $row['title'] ?? '',
				'value' => (float)($row['value'] ?? 0),
				'text' => $this->currency->format((float)($row['value'] ?? 0), $this->session->data['currency'])
			];
		}

		return [
			'html' => $this->renderSummaryHtml($products, $total_rows),
			'totals' => $total_rows,
			'total_text' => $total_rows ? (string)end($total_rows)['text'] : '',
		];
	}

	private function renderSummaryHtml(array $products, array $totals): string {
		$e = static function ($value): string {
			return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
		};

		$html = '<div class="table-responsive"><table class="table table-bordered table-hover"><thead><tr>';
		$html .= '<th>' . $e($this->language->get('column_product')) . '</th>';
		$html .= '<th class="text-end">' . $e($this->language->get('column_total')) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ($products as $product) {
			$html .= '<tr><td>' . (int)$product['quantity'] . 'x <a href="' . $e($product['href']) . '">' . $e($product['name']) . '</a>';
			$html .= '<ul class="list-unstyled mb-0"><li><small> - ' . $e($this->language->get('text_model')) . ': ' . $e($product['model']) . '</small></li>';

			foreach ($product['option'] as $option) {
				$html .= '<li><small> - ' . $e($option['name'] ?? '') . ': ' . $e($option['value'] ?? '') . '</small></li>';
			}

			$html .= '</ul></td><td class="text-end">' . $e($product['total']) . '</td></tr>';
		}

		$html .= '</tbody><tfoot>';

		foreach ($totals as $total) {
			$html .= '<tr><td class="text-end"><strong>' . $e($total['title']) . '</strong></td><td class="text-end">' . $e($total['text']) . '</td></tr>';
		}

		$html .= '</tfoot></table></div>';

		return $html;
	}
}
PHP;

$couponTwig = <<<'TWIG'
<fieldset>
  <legend>Промокод</legend>
  <div class="bs-coupon-actions">
    <input type="text" name="coupon" value="{{ coupon }}" placeholder="Введіть промокод" id="input-checkout-coupon" class="form-control" autocomplete="off"/>
    <button type="button" id="button-checkout-coupon" class="btn btn-primary">Застосувати</button>
    <button type="button" id="button-checkout-coupon-remove" class="btn btn-outline-secondary"{% if not coupon %} hidden{% endif %}>Прибрати</button>
  </div>
  <div class="bs-coupon-status mt-2" data-bs-coupon-status></div>
</fieldset>
<script type="text/javascript"><!--
(function() {
  function couponStatus(message, type) {
    $('[data-bs-coupon-status]')
      .removeClass('bs-is-error bs-is-success')
      .addClass(type === 'error' ? 'bs-is-error' : (type === 'success' ? 'bs-is-success' : ''))
      .text(message || '');
  }

  function couponPayload(extra) {
    var payload = extra || {};
    var email = $('#input-email').val();

    if (email) {
      payload.email = email;
    }

    return payload;
  }

  function applyCouponResponse(json, options) {
    options = options || {};

    if (!json) {
      couponStatus('Не вдалося оновити промокод.', 'error');
      return;
    }

    if (json.coupon !== undefined) {
      $('#input-checkout-coupon').val(json.coupon || '');
      $('#button-checkout-coupon-remove').prop('hidden', !json.coupon);
    }

    if (json.summary_html && window.bsCheckoutUpdateCachedSummaryHtml) {
      window.bsCheckoutUpdateCachedSummaryHtml(json.summary_html);
    } else if (window.bsCheckoutRefreshConfirmIfPaymentReady) {
      window.bsCheckoutRefreshConfirmIfPaymentReady();
    }

    if (json.error) {
      couponStatus(json.error, 'error');
      return;
    }

    if (!options.quiet && json.success) {
      couponStatus(json.success, 'success');
    } else if (!options.quiet && json.welcome_coupon_applied) {
      couponStatus('Промокод ' + json.welcome_coupon_applied + ' застосовано.', 'success');
    } else if (!options.quiet && json.welcome_coupon_error) {
      couponStatus(json.welcome_coupon_error, 'error');
    }
  }

  window.bsCheckoutRefreshCouponSummary = function(options) {
    return $.ajax({
      url: 'index.php?route=checkout/coupon.summary&language={{ language }}',
      type: 'post',
      dataType: 'json',
      data: couponPayload({}),
      success: function(json) {
        applyCouponResponse(json, options || { quiet: true });
      },
      error: function(xhr, ajaxOptions, thrownError) {
        if (!(options && options.quiet)) {
          couponStatus(thrownError + "\r\n" + xhr.statusText, 'error');
        }
      }
    });
  };

  $(document).on('click', '#button-checkout-coupon', function() {
    couponStatus('Застосовуємо промокод...');

    $.ajax({
      url: 'index.php?route=checkout/coupon.apply&language={{ language }}',
      type: 'post',
      dataType: 'json',
      data: couponPayload({ coupon: $('#input-checkout-coupon').val() }),
      success: function(json) {
        applyCouponResponse(json);
      },
      error: function(xhr, ajaxOptions, thrownError) {
        couponStatus(thrownError + "\r\n" + xhr.statusText, 'error');
      }
    });
  });

  $(document).on('click', '#button-checkout-coupon-remove', function() {
    couponStatus('Прибираємо промокод...');

    $.ajax({
      url: 'index.php?route=checkout/coupon.remove&language={{ language }}',
      type: 'post',
      dataType: 'json',
      data: couponPayload({}),
      success: function(json) {
        applyCouponResponse(json);
      },
      error: function(xhr, ajaxOptions, thrownError) {
        couponStatus(thrownError + "\r\n" + xhr.statusText, 'error');
      }
    });
  });

  $(document).on('keydown', '#input-checkout-coupon', function(event) {
    if (event.key === 'Enter') {
      event.preventDefault();
      $('#button-checkout-coupon').trigger('click');
    }
  });

  $(function() {
    window.bsCheckoutRefreshCouponSummary({ quiet: true });
  });
})();
//--></script>
TWIG;

foreach ([
    'catalog/controller/checkout/checkout.php',
    'catalog/controller/checkout/confirm.php',
    'catalog/controller/checkout/register.php',
    'catalog/view/template/checkout/checkout.twig',
] as $file) {
    bs5a_read($root, $file);
}

$checkoutController = bs5a_patch_checkout_controller(bs5a_read($root, 'catalog/controller/checkout/checkout.php'));
bs5a_write($root, 'catalog/controller/checkout/checkout.php', $checkoutController, $backupDir, $changed, $alreadyApplied);

$confirmController = bs5a_patch_confirm_controller(bs5a_read($root, 'catalog/controller/checkout/confirm.php'));
bs5a_write($root, 'catalog/controller/checkout/confirm.php', $confirmController, $backupDir, $changed, $alreadyApplied);

$registerController = bs5a_patch_register_controller(bs5a_read($root, 'catalog/controller/checkout/register.php'));
bs5a_write($root, 'catalog/controller/checkout/register.php', $registerController, $backupDir, $changed, $alreadyApplied);

$checkoutTwig = bs5a_patch_checkout_twig(bs5a_read($root, 'catalog/view/template/checkout/checkout.twig'));
bs5a_write($root, 'catalog/view/template/checkout/checkout.twig', $checkoutTwig, $backupDir, $changed, $alreadyApplied);

bs5a_add_file_once($root, 'catalog/model/checkout/booster_coupon.php', $model, 'ST-2b.5A helper', $backupDir, $changed, $alreadyApplied);
bs5a_add_file_once($root, 'catalog/controller/checkout/coupon.php', $controller, 'ST-2b.5A stock checkout coupon endpoint', $backupDir, $changed, $alreadyApplied);
bs5a_add_file_once($root, 'catalog/view/template/checkout/coupon.twig', $couponTwig, 'bs-coupon-actions', $backupDir, $changed, $alreadyApplied);

foreach ([
    'catalog/controller/checkout/checkout.php',
    'catalog/controller/checkout/confirm.php',
    'catalog/controller/checkout/register.php',
    'catalog/controller/checkout/coupon.php',
    'catalog/model/checkout/booster_coupon.php',
] as $phpFile) {
    bs5a_php_lint($root, $phpFile);
}

$checkoutTwigFinal = bs5a_read($root, 'catalog/view/template/checkout/checkout.twig');
if (substr_count($checkoutTwigFinal, 'checkout/confirm.confirm') !== 1) {
    bs5a_fail('postcheck_confirm_confirm_count:' . substr_count($checkoutTwigFinal, 'checkout/confirm.confirm'));
}

if (strpos($checkoutTwigFinal, 'bsCheckoutUpdateCachedSummaryHtml') === false) {
    bs5a_fail('postcheck_coupon_js_missing');
}

$couponTwigFinal = bs5a_read($root, 'catalog/view/template/checkout/coupon.twig');
if (strpos($couponTwigFinal, 'checkout/coupon.apply') === false || strpos($couponTwigFinal, 'checkout/coupon.summary') === false) {
    bs5a_fail('postcheck_coupon_twig_js_missing');
}

echo 'patch=' . $patchId . PHP_EOL;
echo 'cwd=' . getcwd() . PHP_EOL;
echo 'time=' . date('c') . PHP_EOL;
echo 'db_schema_changes=none' . PHP_EOL;
echo 'db_data_changes=none_by_patch; runtime reads order/order_total for First15 guard only' . PHP_EOL;
echo 'already_applied=' . ($alreadyApplied ? 'yes' : 'no') . PHP_EOL;
echo 'changed_files=' . count($changed) . PHP_EOL;
foreach ($changed as $file) {
    echo 'changed=' . $file . PHP_EOL;
}
if (!$alreadyApplied) {
    echo 'backup_dir=' . $backupDir . PHP_EOL;
}
echo 'done=ok' . PHP_EOL;

@unlink(__FILE__);
