<?php
/**
 * PAY-001 Phase 2 — dedicated stock-checkout credit UI.
 *
 * Scope: product credit chooser, stock checkout virtual mono_chast method, and UI assets.
 * No DB/schema changes. The deployed SimpleCheckout isolation remains untouched.
 * Rollback: restore every file from _patch_backups/PAY-001_phase2_credit_ui_20260721-<timestamp>/files/.
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-001_phase2_credit_ui_20260721';
const PATCH_MARKER = 'PAY-001-PHASE2-CREDIT-UI-20260721';

function say(string $message): void { echo $message . PHP_EOL; }
function fail(string $message): never { fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL); exit(1); }
function normalized(string $value): string { return str_replace("\r\n", "\n", $value); }
function replace_once(string $contents, string $needle, string $replacement, string $label): string {
    $count = substr_count($contents, $needle);
    if ($count !== 1) fail($label . ' anchor count=' . $count . ', expected=1. No files changed.');
    return str_replace($needle, $replacement, $contents, $written);
}
function backup_file(string $root, string $backup, string $relative): void {
    $source = $root . '/' . $relative;
    $destination = $backup . '/files/' . $relative;
    $dir = dirname($destination);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) fail('Cannot create backup directory for ' . $relative);
    if (is_file($source) && !copy($source, $destination)) fail('Cannot back up ' . $relative);
}
function write_target(string $root, string $relative, string $contents): void {
    $path = $root . '/' . $relative;
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) fail('Cannot create directory for ' . $relative);
    if (file_put_contents($path, $contents, LOCK_EX) === false) fail('Cannot write ' . $relative);
}

$root = __DIR__;
$config = $root . '/config.php';
if (!is_file($config)) fail('config.php missing; run only from public_html.');
say('cwd=' . $root);
say('time=' . date('c'));

$targets = [
    'catalog/controller/product/product.php',
    'catalog/view/template/product/product.twig',
    'catalog/controller/checkout/checkout.php',
    'catalog/controller/checkout/payment_method.php',
    'catalog/view/template/checkout/payment_method.twig',
    'catalog/view/stylesheet/boostershop-ds.css',
    'catalog/view/template/common/header.twig',
];
foreach ($targets as $relative) {
    if (!is_file($root . '/' . $relative)) fail('Target missing: ' . $relative);
}
$monoModel = $root . '/extension/mono_chast/catalog/model/payment/mono_chast.php';
$monoController = $root . '/extension/mono_chast/catalog/controller/payment/mono_chast.php';
if (!is_file($monoModel) || !is_file($monoController)) fail('Deployed Mono extension is missing; Phase 1 must be present first.');
$monoModelContents = file_get_contents($monoModel);
$monoControllerContents = file_get_contents($monoController);
if ($monoModelContents === false || $monoControllerContents === false) fail('Cannot read deployed Mono extension guards.');
if (!str_contains($monoModelContents, 'PAY-001-SIMPLE-CHECKOUT-ISOLATION')) fail('SimpleCheckout isolation marker missing; refusing to expose Mono.');
if (!str_contains($monoControllerContents, 'public function confirm(): void')) fail('Mono confirm() anchor missing; refusing unverified checkout wiring.');

$cssCurrent = file_get_contents($root . '/catalog/view/stylesheet/boostershop-ds.css');
if ($cssCurrent === false) fail('Cannot read CSS target.');
if (str_contains($cssCurrent, PATCH_MARKER)) {
    say('already_applied=yes');
    @unlink(__FILE__);
    exit(0);
}

$source = [];
foreach ($targets as $relative) {
    $contents = file_get_contents($root . '/' . $relative);
    if ($contents === false) fail('Cannot read: ' . $relative);
    $source[$relative] = normalized($contents);
}

$productPriceAnchor = <<<'PHP'
			if ((float)$product_info['special']) {
				$data['special'] = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			} else {
				$data['special'] = false;
			}
PHP;
$productPriceReplacement = $productPriceAnchor . <<<'PHP'

			// PAY-001-PHASE2-CREDIT-UI-20260721: product-page entry is gated by
			// the same sandbox setting as stock checkout. No client value is trusted later.
			$pay001_mono_price = (float)$product_info['special'] ?: (float)$product_info['price'];
			$pay001_mono_currency = strtoupper((string)($this->session->data['currency'] ?? $this->config->get('config_currency')));
			$data['pay001_mono_chast_visible'] =
				(bool)$this->config->get('payment_mono_chast_status') &&
				$pay001_mono_currency === 'UAH' &&
				$pay001_mono_price >= max(500.0, (float)$this->config->get('payment_mono_chast_min_total')) &&
				trim((string)$this->config->get('payment_mono_chast_api_base')) !== '' &&
				trim((string)$this->config->get('payment_mono_chast_store_id')) !== '' &&
				trim((string)$this->config->get('payment_mono_chast_store_secret')) !== '';
			$data['pay001_mono_chast_price'] = round($pay001_mono_price, 2);
			$data['pay001_mono_chast_checkout'] = $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'), true);
PHP;
$source['catalog/controller/product/product.php'] = replace_once($source['catalog/controller/product/product.php'], $productPriceAnchor, $productPriceReplacement, 'product.php price');

$oldHint = <<<'TWIG'
          <div class="bs-installment-hint">
            <span class="bs-installment-hint__label">Оплата частинами ПУМБ</span>
            <span class="bs-installment-hint__sub">Деталі при оформленні замовлення</span>
          </div>
TWIG;
$newHint = <<<'TWIG'
          {% if pay001_mono_chast_visible %}
          <section class="pay001-product-credit" aria-label="Оплата частинами">
            <div class="pay001-product-credit__info">
              <div class="pay001-provider-row">
                <img src="catalog/view/image/payment/pay001-mono-label.png" alt="monobank" class="pay001-provider-row__mono">
                <span><strong>Покупка частинами monobank</strong><small>До 5 платежів</small></span>
              </div>
              <div class="pay001-provider-row pay001-provider-row--soon">
                <img src="catalog/view/image/payment/pay001-pumb.svg" alt="ПУМБ" class="pay001-provider-row__pumb">
                <span><strong>Сплачуйте частинами ПУМБ</strong><small>До 5 платежів</small></span>
                <em>СКОРО БУДЕ</em>
              </div>
            </div>
          </section>
          {% endif %}
TWIG;
$source['catalog/view/template/product/product.twig'] = replace_once($source['catalog/view/template/product/product.twig'], $oldHint, $newHint, 'product.twig legacy credit hint');

$buyAnchor = <<<'TWIG'
                </div>
                <input type="hidden" name="product_id" value="{{ product_id }}" id="input-product-id"/>
TWIG;
$buyReplacement = <<<'TWIG'
                </div>
                {% if pay001_mono_chast_visible %}
                <button type="button" class="bs-btn bs-btn-secondary pay001-product-credit__open pay001-product-credit__open--after-cart" data-pay001-credit-open>Оплатити частинами</button>
                {% endif %}
                <input type="hidden" name="product_id" value="{{ product_id }}" id="input-product-id"/>
TWIG;
$source['catalog/view/template/product/product.twig'] = replace_once($source['catalog/view/template/product/product.twig'], $buyAnchor, $buyReplacement, 'product.twig buy row');

$productModal = <<<'TWIG'
{% if pay001_mono_chast_visible %}
<div class="pay001-modal" data-pay001-credit-modal hidden>
  <div class="pay001-modal__backdrop" data-pay001-credit-close></div>
  <section class="pay001-modal__sheet" role="dialog" aria-modal="true" aria-labelledby="pay001-credit-title">
    <button type="button" class="pay001-modal__close" aria-label="Закрити" data-pay001-credit-close>&times;</button>
    <h2 id="pay001-credit-title">Виберіть кредитну пропозицію</h2>
    <article class="pay001-modal-provider">
      <div class="pay001-modal-provider__head"><img src="catalog/view/image/payment/pay001-mono-label.png" alt="monobank"><span><strong>Покупка частинами monobank</strong><small>Без комісії для вас</small></span></div>
      <div class="pay001-parts" data-pay001-parts>
        <button type="button" data-pay001-part="3">3 платежі</button><button type="button" data-pay001-part="4">4 платежі</button><button type="button" data-pay001-part="5">5 платежів</button>
      </div>
      <div class="pay001-summary"><span>Щомісячний платіж<strong data-pay001-monthly></strong></span><span>Платежів<strong data-pay001-count></strong></span><span>Вартість товару<strong data-pay001-total></strong></span></div>
      <button type="button" class="bs-btn pay001-modal-provider__choose" data-pay001-credit-choose>Обрати</button>
    </article>
    <article class="pay001-modal-provider pay001-modal-provider--soon"><div class="pay001-modal-provider__head"><img src="catalog/view/image/payment/pay001-pumb.svg" alt="ПУМБ"><span><strong>Сплачуйте частинами ПУМБ</strong><small>До 5 платежів</small></span><em>СКОРО БУДЕ</em></div></article>
    <p class="pay001-modal__disclaimer">Без комісії для вас — умови кредитування визначає банк.</p>
  </section>
</div>
<script>
(function () {
  var modal = document.querySelector('[data-pay001-credit-modal]');
  if (!modal) return;
  var price = Number({{ pay001_mono_chast_price|json_encode|raw }}) || 0;
  var checkoutUrl = {{ pay001_mono_chast_checkout|replace({'&amp;': '&'})|json_encode|raw }};
  var parts = 3;
  function money(value) { return new Intl.NumberFormat('uk-UA', {maximumFractionDigits: 0}).format(Math.ceil(value)) + ' ₴'; }
  function render() {
    modal.querySelectorAll('[data-pay001-part]').forEach(function (button) { button.classList.toggle('is-active', Number(button.dataset.pay001Part) === parts); });
    modal.querySelector('[data-pay001-monthly]').textContent = money(price / parts);
    modal.querySelector('[data-pay001-count]').textContent = parts;
    modal.querySelector('[data-pay001-total]').textContent = money(price);
  }
  document.querySelectorAll('[data-pay001-credit-open]').forEach(function (button) { button.addEventListener('click', function () { modal.hidden = false; render(); }); });
  modal.querySelectorAll('[data-pay001-credit-close]').forEach(function (button) { button.addEventListener('click', function () { modal.hidden = true; }); });
  modal.querySelectorAll('[data-pay001-part]').forEach(function (button) { button.addEventListener('click', function () { parts = Number(button.dataset.pay001Part); render(); }); });
  modal.querySelector('[data-pay001-credit-choose]').addEventListener('click', function () { window.location.assign(checkoutUrl + '&mono_chast_parts=' + encodeURIComponent(parts)); });
  render();
}());
</script>
{% endif %}
TWIG;
$source['catalog/view/template/product/product.twig'] = replace_once($source['catalog/view/template/product/product.twig'], "\n{{ footer }}", "\n" . $productModal . "\n{{ footer }}", 'product.twig footer');

$checkoutAnchor = "\t\t\$this->load->language('checkout/checkout');\n";
$checkoutReplacement = <<<'PHP'
		$this->load->language('checkout/checkout');

		// PAY-001-PHASE2-CREDIT-UI-20260721: product UI can suggest only 3/4/5.
		// The stock payment controller validates the final virtual option again.
		if (isset($this->request->get['mono_chast_parts'])) {
			$pay001_parts = (int)$this->request->get['mono_chast_parts'];
			if (in_array($pay001_parts, [3, 4, 5], true)) {
				$this->session->data['pay001_mono_chast_parts'] = $pay001_parts;
			}
		}
PHP;
$source['catalog/controller/checkout/checkout.php'] = replace_once($source['catalog/controller/checkout/checkout.php'], $checkoutAnchor, $checkoutReplacement, 'checkout.php language');

$paymentIndexAnchor = "\t\t\$data['language'] = \$this->config->get('config_language');\n";
$paymentIndexReplacement = <<<'PHP'
		$data['language'] = $this->config->get('config_language');
		$data['pay001_mono_chast_enabled'] = $this->pay001MonoChastEnabled();
PHP;
$source['catalog/controller/checkout/payment_method.php'] = replace_once($source['catalog/controller/checkout/payment_method.php'], $paymentIndexAnchor, $paymentIndexReplacement, 'payment_method.php index');

$methodsAnchor = <<<'PHP'
			$payment_methods = $this->model_checkout_payment_method->getMethods($payment_address);

			if ($payment_methods) {
PHP;
$methodsReplacement = <<<'PHP'
			$payment_methods = $this->model_checkout_payment_method->getMethods($payment_address);

			// Dedicated stock-checkout entry. Do not call the Mono model here:
			// deployed SimpleCheckout isolation deliberately makes its getMethods() return [].
			$pay001_mono_method = $this->pay001MonoChastMethod();
			if ($pay001_mono_method) {
				$payment_methods['mono_chast'] = $pay001_mono_method;
			}

			if ($payment_methods) {
PHP;
$source['catalog/controller/checkout/payment_method.php'] = replace_once($source['catalog/controller/checkout/payment_method.php'], $methodsAnchor, $methodsReplacement, 'payment_method.php methods');

$commentAnchor = "\t/**\n\t * Comment\n";
$helpers = <<<'PHP'
	private function pay001MonoChastEnabled(): bool {
		if (!$this->config->get('payment_mono_chast_status')) {
			return false;
		}
		$currency = strtoupper((string)($this->session->data['currency'] ?? $this->config->get('config_currency')));
		if ($currency !== 'UAH') {
			return false;
		}
		foreach (['payment_mono_chast_api_base', 'payment_mono_chast_store_id', 'payment_mono_chast_store_secret'] as $key) {
			if (trim((string)$this->config->get($key)) === '') {
				return false;
			}
		}
		return (float)$this->cart->getTotal() >= max(500.0, (float)$this->config->get('payment_mono_chast_min_total'));
	}

	private function pay001MonoChastMethod(): array {
		if (!$this->pay001MonoChastEnabled()) {
			return [];
		}
		$parts = json_decode((string)$this->config->get('payment_mono_chast_parts'), true);
		$parts = is_array($parts) ? array_values(array_unique(array_filter(array_map('intval', $parts), static fn(int $value): bool => in_array($value, [3, 4, 5], true)))) : [];
		if (!$parts) {
			$parts = [3, 4, 5];
		}
		$preferred = (int)($this->session->data['pay001_mono_chast_parts'] ?? 3);
		if (!in_array($preferred, $parts, true)) {
			$preferred = $parts[0];
		}
		usort($parts, static fn(int $left, int $right): int => $left === $preferred ? -1 : ($right === $preferred ? 1 : $left <=> $right));
		$options = [];
		foreach ($parts as $part) {
			$options['mono_chast_' . $part] = [
				'code' => 'mono_chast.mono_chast_' . $part,
				'name' => 'Оплатити частинами'
			];
		}
		return [
			'code' => 'mono_chast',
			'name' => 'Оплатити частинами',
			'option' => $options,
			'pay001_credit' => true,
			'pay001_preferred' => $preferred,
			'pay001_total' => round((float)$this->cart->getTotal(), 2),
			'sort_order' => (int)$this->config->get('payment_mono_chast_sort_order')
		];
	}

PHP;
$source['catalog/controller/checkout/payment_method.php'] = replace_once($source['catalog/controller/checkout/payment_method.php'], $commentAnchor, $helpers . $commentAnchor, 'payment_method.php helpers');

$twigNormalizeAnchor = <<<'TWIG'
  function normalizeChoice(choice) {
    choice = String(choice || '').toLowerCase();

    if (choice.indexOf('hutko') !== -1 || choice.indexOf('mono') !== -1 || choice.indexOf('card') !== -1) {
      return 'hutko';
    }
TWIG;
$twigNormalizeReplacement = <<<'TWIG'
  function normalizeChoice(choice) {
    choice = String(choice || '').toLowerCase();

    if (choice.indexOf('mono_chast') !== -1 || choice.indexOf('оплатити частинами') !== -1) {
      return 'mono_chast';
    }

    if (choice.indexOf('hutko') !== -1 || choice.indexOf('mono') !== -1 || choice.indexOf('card') !== -1) {
      return 'hutko';
    }
TWIG;
$source['catalog/view/template/checkout/payment_method.twig'] = replace_once($source['catalog/view/template/checkout/payment_method.twig'], $twigNormalizeAnchor, $twigNormalizeReplacement, 'payment_method.twig normalize');

$twigMatchesAnchor = <<<'TWIG'
    if (choice === 'hutko') {
      return code.indexOf('hutko') !== -1 || code.indexOf('mono') !== -1 || code.indexOf('card') !== -1;
    }
TWIG;
$twigMatchesReplacement = <<<'TWIG'
    if (choice === 'mono_chast') {
      return code.indexOf('mono_chast.') === 0;
    }

    if (choice === 'hutko') {
      return code.indexOf('hutko') !== -1 || (code.indexOf('mono') !== -1 && code.indexOf('mono_chast') === -1) || code.indexOf('card') !== -1;
    }
TWIG;
$source['catalog/view/template/checkout/payment_method.twig'] = replace_once($source['catalog/view/template/checkout/payment_method.twig'], $twigMatchesAnchor, $twigMatchesReplacement, 'payment_method.twig match');

$flattenAnchor = <<<'TWIG'
  function flattenPaymentMethods(json) {
    var options = [];

    if (json && json.payment_methods) {
      $.each(json.payment_methods, function(groupKey, group) {
        if (group && group.option) {
          $.each(group.option, function(optionKey, option) {
            if (option && option.code) {
              options.push({
                code: option.code,
                label: option.name || option.title || group.name || groupKey,
                id: optionKey
              });
            }
          });
        } else if (group && group.code) {
          options.push({
            code: group.code,
            label: group.name || group.title || groupKey,
            id: groupKey
          });
        }
      });
    }

    return options;
  }
TWIG;
$flattenReplacement = <<<'TWIG'
  function flattenPaymentMethods(json) {
    var options = [];

    if (json && json.payment_methods) {
      $.each(json.payment_methods, function(groupKey, group) {
        if (group && group.pay001_credit && group.option) {
          var monoOptions = [];
          $.each(group.option, function(optionKey, option) {
            if (option && option.code) monoOptions.push({ code: option.code, count: Number(String(option.code).match(/mono_chast_(\d)/)[1]) });
          });
          if (monoOptions.length) {
            options.push({
              code: monoOptions[0].code,
              label: 'Оплатити частинами',
              id: 'mono_chast',
              pay001Credit: true,
              preferred: Number(group.pay001_preferred) || monoOptions[0].count,
              total: Number(group.pay001_total) || 0,
              monoOptions: monoOptions
            });
          }
        } else if (group && group.option) {
          $.each(group.option, function(optionKey, option) {
            if (option && option.code) options.push({ code: option.code, label: option.name || option.title || group.name || groupKey, id: optionKey });
          });
        } else if (group && group.code) {
          options.push({ code: group.code, label: group.name || group.title || groupKey, id: groupKey });
        }
      });
    }
    return options;
  }
TWIG;
$source['catalog/view/template/checkout/payment_method.twig'] = replace_once($source['catalog/view/template/checkout/payment_method.twig'], $flattenAnchor, $flattenReplacement, 'payment_method.twig flatten');

$renderAnchor = <<<'TWIG'
  function renderPaymentMethods(json) {
    if (json && json.error) {
      status(json.error, true);
      return;
    }

    var options = flattenPaymentMethods(json);
    var current = $('#input-payment-code').val();
    var selected = current ? findPaymentOption(options, current) : null;


    if (!options.length) {
      $('#bs-payment-methods').html('');
      status('Немає доступних способів оплати для цієї доставки.', true);
      refreshConfirmSummary();
      return;
    }

    var html = '';
    $.each(options, function(index, option) {
      var inputId = 'input-payment-method-' + index;
      var checked = selected && selected.code === option.code ? ' checked' : '';

      html += '<label class="bs-checkout-method-option" for="' + inputId + '">' +
        '<input type="radio" name="payment_method" id="' + inputId + '" value="' + escapeHtml(option.code) + '" data-label="' + escapeHtml(option.label) + '"' + checked + ' />' +
        '<span>' + escapeHtml(option.label) + '</span>' +
        '</label>';
    });

    $('#bs-payment-methods').html(html);

    status(current ? 'Оплату обрано.' : 'Оберіть спосіб оплати.');
    refreshConfirmSummary();
  }
TWIG;
$renderReplacement = <<<'TWIG'
  function pay001Money(value) {
    return new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 0 }).format(Math.ceil(Number(value) || 0)) + ' ₴';
  }
  function pay001Drawer(option, selectedCode) {
    var selected = selectedCode || option.code;
    var match = String(selected).match(/mono_chast_(\d)/);
    var count = match ? Number(match[1]) : option.preferred;
    var phone = $('input[name="telephone"]').first().val() || 'вказаний у формі';
    var html = '<div class="pay001-checkout-drawer" data-pay001-drawer>' +
      '<article class="pay001-checkout-provider"><div class="pay001-checkout-provider__head"><img src="catalog/view/image/payment/pay001-mono-label.png" alt="monobank"><strong>Покупка частинами monobank</strong></div>' +
      '<div class="pay001-parts">';
    $.each(option.monoOptions, function(_, item) { html += '<button type="button" data-pay001-checkout-part="' + item.count + '" data-pay001-code="' + escapeHtml(item.code) + '"' + (item.count === count ? ' class="is-active"' : '') + '>' + item.count + ' платежі</button>'; });
    html += '</div><div class="pay001-summary"><span>Сума в кредит<strong>' + pay001Money(option.total) + '</strong></span><span>Щомісячний платіж<strong data-pay001-monthly>' + pay001Money(option.total / count) + '</strong></span><span>Платежів до завершення<strong data-pay001-left>' + Math.max(count - 1, 0) + '</strong></span></div><p>Кредит буде оформлено на номер телефону: <strong data-pay001-phone></strong></p></article>' +
      '<article class="pay001-checkout-provider pay001-checkout-provider--soon"><div class="pay001-checkout-provider__head"><img src="catalog/view/image/payment/pay001-pumb.svg" alt="ПУМБ"><strong>Сплачуйте частинами ПУМБ</strong><em>СКОРО БУДЕ</em></div><small>До 5 платежів</small></article></div>';
    var $drawer = $(html);
    $drawer.find('[data-pay001-phone]').text(phone);
    return $drawer;
  }
  function renderPaymentMethods(json) {
    if (json && json.error) { status(json.error, true); return; }
    var options = flattenPaymentMethods(json);
    var current = $('#input-payment-code').val();
    var selected = current ? findPaymentOption(options, current) : null;
    if (!selected && !current) {
      $.each(options, function(_, option) { if (!selected && option.pay001Credit) selected = option; });
    }
    if (!options.length) {
      $('#bs-payment-methods').html('');
      status('Немає доступних способів оплати для цієї доставки.', true);
      refreshConfirmSummary();
      return;
    }
    var html = '';
    $.each(options, function(index, option) {
      var inputId = 'input-payment-method-' + index;
      var checked = selected && selected.code === option.code ? ' checked' : '';
      html += '<label class="bs-checkout-method-option' + (option.pay001Credit ? ' pay001-checkout-method' : '') + '" for="' + inputId + '">' +
        '<input type="radio" name="payment_method" id="' + inputId + '" value="' + escapeHtml(option.code) + '" data-label="' + escapeHtml(option.label) + '"' + checked + ' />' +
        '<span>' + (option.pay001Credit ? '<strong>Оплатити частинами</strong><small>Розстрочка від підключених банків</small>' : escapeHtml(option.label)) + '</span></label>';
      if (option.pay001Credit && checked) html += pay001Drawer(option, option.code).prop('outerHTML');
    });
    $('#bs-payment-methods').html(html);
    if (!current && selected && selected.pay001Credit) setTimeout(function () { savePayment(selected.code, selected.label, true); }, 0);
    status(current || selected ? 'Оплату обрано.' : 'Оберіть спосіб оплати.');
    refreshConfirmSummary();
  }
TWIG;
$source['catalog/view/template/checkout/payment_method.twig'] = replace_once($source['catalog/view/template/checkout/payment_method.twig'], $renderAnchor, $renderReplacement, 'payment_method.twig renderer');

$eventAnchor = <<<'TWIG'
  $(document).on('change', '#bs-payment-methods input[name="payment_method"]', function(event) {
    var $input = $(this);
    savePayment($input.val(), $input.data('label'), false, event);
  });
TWIG;
$eventReplacement = <<<'TWIG'
  $(document).on('change', '#bs-payment-methods input[name="payment_method"]', function(event) {
    var $input = $(this);
    savePayment($input.val(), $input.data('label'), false, event);
  });
  $(document).on('click', '[data-pay001-checkout-part]', function() {
    var $button = $(this);
    var count = Number($button.data('pay001-checkout-part'));
    var code = String($button.data('pay001-code'));
    var $drawer = $button.closest('[data-pay001-drawer]');
    var total = Number(($drawer.find('.pay001-summary strong').first().text() || '0').replace(/[^\d]/g, '')) || 0;
    $drawer.find('[data-pay001-checkout-part]').removeClass('is-active');
    $button.addClass('is-active');
    $drawer.find('[data-pay001-monthly]').text(pay001Money(total / count));
    $drawer.find('[data-pay001-left]').text(Math.max(count - 1, 0));
    savePayment(code, 'Оплатити частинами', false);
  });
TWIG;
$source['catalog/view/template/checkout/payment_method.twig'] = replace_once($source['catalog/view/template/checkout/payment_method.twig'], $eventAnchor, $eventReplacement, 'payment_method.twig events');

$headerAnchor = 'boostershop-ds.css?v=cat002-5e-20260718';
$source['catalog/view/template/common/header.twig'] = replace_once($source['catalog/view/template/common/header.twig'], $headerAnchor, 'boostershop-ds.css?v=pay001-ui-20260721', 'header CSS version');

$cssAppend = <<<'CSS'

/* PAY-001-PHASE2-CREDIT-UI-20260721 */
.pay001-product-credit { margin: 12px 0 0; }
.pay001-product-credit__open { width: 100%; border: 1.5px solid #111 !important; color: #111 !important; background: #fff !important; }
.pay001-product-credit__open--after-cart { margin-top: 10px; }
.pay001-product-credit__info { margin-top: 10px; border: 1px solid var(--bs-line, #d8dee8); border-radius: 10px; overflow: hidden; background: #fff; }
.pay001-provider-row { min-height: 56px; display: flex; align-items: center; gap: 10px; padding: 9px 12px; }
.pay001-provider-row + .pay001-provider-row { border-top: 1px solid var(--bs-line, #d8dee8); }
.pay001-provider-row img { object-fit: contain; flex: 0 0 auto; }
.pay001-provider-row__mono { width: 32px; height: 32px; border-radius: 50%; }
.pay001-provider-row__pumb { width: 30px; height: 30px; }
.pay001-provider-row span, .pay001-modal-provider__head span { display: grid; gap: 2px; }
.pay001-provider-row strong, .pay001-modal-provider strong, .pay001-checkout-provider strong { color: #111; font-size: 13px; }
.pay001-provider-row small, .pay001-modal-provider small { color: #6b7280; font-size: 12px; }
.pay001-provider-row em, .pay001-modal-provider em, .pay001-checkout-provider em { margin-left: auto; font-style: normal; font-size: 9px; letter-spacing: .05em; font-weight: 800; padding: 4px 6px; border-radius: 999px; color: #6b7280; background: #f3f4f6; }
.pay001-modal[hidden] { display: none; }
.pay001-modal { position: fixed; z-index: 1070; inset: 0; display: grid; place-items: center; padding: 16px; }
.pay001-modal__backdrop { position: absolute; inset: 0; background: rgba(17,24,39,.58); }
.pay001-modal__sheet { position: relative; width: min(540px, 100%); max-height: calc(100vh - 32px); overflow: auto; background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 24px 64px rgba(0,0,0,.28); }
.pay001-modal__sheet h2 { margin: 0 34px 18px 0; font-size: 20px; }
.pay001-modal__close { position: absolute; right: 14px; top: 12px; border: 0; background: transparent; font-size: 30px; line-height: 1; cursor: pointer; }
.pay001-modal-provider, .pay001-checkout-provider { border: 1px solid #d8dee8; border-radius: 12px; padding: 14px; background: #fff; }
.pay001-modal-provider + .pay001-modal-provider { margin-top: 10px; }
.pay001-modal-provider--soon, .pay001-checkout-provider--soon { opacity: .7; border-style: dashed; }
.pay001-modal-provider__head, .pay001-checkout-provider__head { display: flex; align-items: center; gap: 10px; }
.pay001-modal-provider__head img, .pay001-checkout-provider__head img { width: 38px; height: 38px; border-radius: 50%; object-fit: contain; }
.pay001-parts { display: flex; flex-wrap: wrap; gap: 8px; margin: 14px 0; }
.pay001-parts button { border: 1px solid #cbd5e1; background: #fff; color: #111; padding: 8px 10px; border-radius: 999px; font-weight: 700; cursor: pointer; }
.pay001-parts button.is-active { background: #111; border-color: #111; color: #fff; }
.pay001-summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.pay001-summary span { display: grid; gap: 4px; color: #6b7280; font-size: 11px; }
.pay001-summary strong { color: #111; font-size: 14px; }
.pay001-modal-provider__choose { width: 100%; margin-top: 14px; background: #111 !important; border-color: #111 !important; color: #fff !important; }
.pay001-modal__disclaimer { margin: 14px 0 0; color: #6b7280; font-size: 12px; }
.pay001-checkout-method span { display: grid; gap: 2px; }
.pay001-checkout-method small { color: #64748b; font-size: 12px; font-weight: 500; }
.pay001-checkout-drawer { margin: -1px 0 10px; padding: 12px; border: 1px solid var(--bs-blue, #3b82f6); border-top: 0; border-radius: 0 0 10px 10px; background: #eff6ff; }
.pay001-checkout-provider + .pay001-checkout-provider { margin-top: 10px; }
.pay001-checkout-provider p { margin: 12px 0 0; color: #475569; font-size: 12px; }
@media (max-width: 480px) {
  .pay001-modal { align-items: end; padding: 0; }
  .pay001-modal__sheet { width: 100%; max-height: 88vh; border-radius: 16px 16px 0 0; padding: 20px 16px; }
  .pay001-summary { gap: 6px; }
  .pay001-summary strong { font-size: 12px; }
}
/* /PAY-001-PHASE2-CREDIT-UI-20260721 */
CSS;
$source['catalog/view/stylesheet/boostershop-ds.css'] .= $cssAppend . "\n";

$assets = [
    'catalog/view/image/payment/pay001-mono-label.png' => base64_decode('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', true),
    'catalog/view/image/payment/pay001-pumb.svg' => base64_decode('PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4KPCEtLSBHZW5lcmF0b3I6IEFkb2JlIElsbHVzdHJhdG9yIDI4LjUuMCwgU1ZHIEV4cG9ydCBQbHVnLUluIC4gU1ZHIFZlcnNpb246IDkuMDMgQnVpbGQgNTQ3MjcpICAtLT4KPHN2ZyB2ZXJzaW9uPSIxLjAiIGlkPSLQqNCw0YBfMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgeD0iMHB4IiB5PSIwcHgiCgkgdmlld0JveD0iMCAwIDQ3MiA0NzIiIHN0eWxlPSJlbmFibGUtYmFja2dyb3VuZDpuZXcgMCAwIDQ3MiA0NzI7IiB4bWw6c3BhY2U9InByZXNlcnZlIj4KPHN0eWxlIHR5cGU9InRleHQvY3NzIj4KCS5zdDB7ZmlsbDojRTYwQzJBO30KCS5zdDF7ZmlsbDojRkZGMEVBO30KPC9zdHlsZT4KPHBhdGggY2xhc3M9InN0MCIgZD0iTTIzNi4xLDIyLjlDMjM2LjEsMjIuOSwyMzYsMjIuOSwyMzYuMSwyMi45QzExOC41LDIyLjksMjIuOSwxMTguNSwyMi45LDIzNmMwLDExNy41LDk1LjYsMjEzLjEsMjEzLjEsMjEzLjEKCWMxMTcuNSwwLDIxMy4xLTk1LjYsMjEzLjEtMjEzLjFjMCwwLDAtMC4xLDAtMC4xaC0yMTNWMjIuOXoiLz4KPGc+Cgk8cGF0aCBjbGFzcz0ic3QxIiBkPSJNMzY0LjQsMjcyLjRjLTYuNCwxNy41LTMyLjMsMTQuMy0zOC4zLDI4LjljMTYuMi0xMy45LDQzLjQtMS41LDQyLjYsMjEuMmMtMC42LDQwLjUtNjQuNiwzOS42LTY0LjYtNC43CgkJYzAtMjAuMiw3LjEtMzMuMSwyOC43LTQyLjdjNi42LTMuMyw4LjItMy43LDExLjktNi45YzEuNi0xLjQsMy40LTMuNyw0LjItNS4zTDM2NC40LDI3Mi40TDM2NC40LDI3Mi40eiBNMzUwLjEsMzIyLjUKCQljMC4zLTExLjYtMTQuOC0xNi4zLTIyLjgtOS4yYy01LjMsNC4zLTUsMTMuNSwwLjEsMTcuOEMzMzUuMiwzMzguNSwzNTAuNCwzMzQuMSwzNTAuMSwzMjIuNXoiLz4KCTxwYXRoIGNsYXNzPSJzdDEiIGQ9Ik0xMDMuMywyODcuMWg1OC4yVjM0OWgtMTguNnYtNDQuNmgtMjFWMzQ5aC0xOC42VjI4Ny4xeiIvPgoJPHBhdGggY2xhc3M9InN0MSIgZD0iTTI4Mi40LDI4Ny4xaDE3LjhWMzQ5aC0xNy44di0zMS45bC0xNC44LDIxLjVoLTAuNGwtMTQuOC0yMS41VjM0OWgtMTcuOHYtNjEuOWgxNy44bDE1LDIzLjRMMjgyLjQsMjg3LjEKCQlMMjgyLjQsMjg3LjF6Ii8+Cgk8cGF0aCBjbGFzcz0ic3QxIiBkPSJNMjExLjEsMjg3LjFsLTkuNywzMS4ybC0wLjIsMC42bC0xNi0zMS44aC0xOS44bDI2LjksNTMuOWMtMS4zLDQuNy00LjgsOC43LTE1LjQsOC43djE3LjkKCQljMTguNCwwLDI4LjMtOC42LDM0LjgtMjcuMmwxOS4yLTUzLjJIMjExLjF6Ii8+CjwvZz4KPC9zdmc+Cg==', true),
];
foreach ($assets as $relative => $contents) {
    if ($contents === false) fail('Embedded asset decode failed: ' . $relative);
    if (is_file($root . '/' . $relative)) fail('Asset already exists without patch marker: ' . $relative);
}

$backup = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
if (!mkdir($backup . '/files', 0755, true) && !is_dir($backup . '/files')) fail('Cannot create backup directory.');
foreach (array_merge($targets, array_keys($assets)) as $relative) backup_file($root, $backup, $relative);

$written = [];
try {
    foreach ($source as $relative => $contents) { write_target($root, $relative, $contents); $written[] = $relative; }
    foreach ($assets as $relative => $contents) { write_target($root, $relative, $contents); $written[] = $relative; }
    $php = PHP_BINARY;
    foreach (['catalog/controller/product/product.php', 'catalog/controller/checkout/checkout.php', 'catalog/controller/checkout/payment_method.php'] as $relative) {
        exec(escapeshellarg($php) . ' -l ' . escapeshellarg($root . '/' . $relative), $lint, $status);
        if ($status !== 0) throw new RuntimeException('php -l failed: ' . $relative);
    }
} catch (Throwable $error) {
    foreach ($written as $relative) {
        $saved = $backup . '/files/' . $relative;
        if (is_file($saved)) { copy($saved, $root . '/' . $relative); } else { @unlink($root . '/' . $relative); }
    }
    fail($error->getMessage() . '; changed files restored.');
}

say('backup=' . $backup);
foreach ($written as $relative) say('changed_file=' . $relative);
say('php_l=ok');
say('done=ok');
@unlink(__FILE__);
