<?php
/**
 * PAY-002 WP5 — PUMB product-page card and modal, behind the existing gate.
 *
 * File-only patch. Run from ~/public_html. No database writes.
 * Rollback: restore the three files from the printed backup directory and clear
 * the template cache. A disabled payment_pumb_credit_status is the immediate
 * visibility kill switch for PUMB on both the product page and checkout.
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-002_pumb-product-page-card_20260831';
const BEFORE_SHA256 = [
    'catalog/controller/product/product.php' => '4c33564176c8363dc1156861a1bfd9eb3647ffd62103c20c7314b420fda232c0',
    'catalog/view/template/product/product.twig' => '4b2744fc1117db1bd97cb922eaf19fe2c036396b5a871b73e1fc6b493913f3d6',
    'catalog/controller/checkout/checkout.php' => 'fd5943d20bbe437e66af7a3b9893c2134ede8d212564eb318024c947c29e5b60'
];
const AFTER_SHA256 = [
    'catalog/controller/product/product.php' => 'cd9624e3c7d1d0698dff0723935599e1cc7342d701610f324a7319fce0b33d82',
    'catalog/view/template/product/product.twig' => '1f705580684ba8ffcb70fb279d0ac6a288dfeae14d051ac4cf2263764c5b6ec4',
    'catalog/controller/checkout/checkout.php' => '39bb445651806fde1451bd8d47531c9040fc7d4f926fda123e24e55ee5027c10'
];

function out(string $message): void { echo $message . PHP_EOL; }
function fail(string $message): void { throw new RuntimeException('ERROR: ' . $message); }
function need(bool $condition, string $message): void { if (!$condition) fail($message); }
function readFileChecked(string $path): string { $value = file_get_contents($path); need(is_string($value), 'cannot read ' . $path); return $value; }
function writeFileChecked(string $path, string $content): void { $written = file_put_contents($path, $content); need($written !== false && $written === strlen($content), 'write failed: ' . $path); }
function replaceExactOnce(string $source, string $anchor, string $replacement, string $label): string {
    $count = substr_count($source, $anchor);
    need($count === 1, 'anchor count for ' . $label . ' is ' . $count . ', expected 1');
    return str_replace($anchor, $replacement, $source);
}
function replaceLastExact(string $source, string $anchor, string $replacement, string $label): string {
    $position = strrpos($source, $anchor);
    need($position !== false, 'last anchor missing for ' . $label);
    return substr_replace($source, $replacement, $position, strlen($anchor));
}
function replaceRegexOnce(string $source, string $pattern, string $replacement, string $label): string {
    $count = preg_match_all($pattern, $source, $matches);
    need($count === 1, 'anchor count for ' . $label . ' is ' . $count . ', expected 1');
    $changed = 0;
    $result = preg_replace($pattern, $replacement, $source, 1, $changed);
    need(is_string($result) && $changed === 1, 'replacement failed for ' . $label);
    return $result;
}
function backupFile(string $root, string $backup, string $rel): void {
    $to = $backup . '/' . $rel;
    if (!is_dir(dirname($to)) && !mkdir(dirname($to), 0755, true) && !is_dir(dirname($to))) fail('cannot create backup directory');
    need(copy($root . '/' . $rel, $to), 'backup failed: ' . $rel);
}
function restoreVerified(string $root, string $backup, array $files): void {
    foreach ($files as $rel) {
        $backupPath = $backup . '/' . $rel;
        need(is_file($backupPath) && copy($backupPath, $root . '/' . $rel), 'restore copy failed: ' . $rel);
        need(hash_equals(BEFORE_SHA256[$rel], hash_file('sha256', $root . '/' . $rel)), 'restore SHA256 mismatch: ' . $rel);
    }
}
function lint(string $path): void {
    $lines = [];
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $lines, $status);
    need($status === 0, 'php -l failed for ' . $path . ': ' . implode(' ', $lines));
    out('php_l=ok file=' . $path);
}

try {
    $root = getcwd() ?: '.';
    need(is_file($root . '/config.php'), 'Run from OpenCart public_html (config.php missing).');
    $files = array_keys(BEFORE_SHA256);
    foreach ($files as $rel) need(is_file($root . '/' . $rel), 'missing live file: ' . $rel);

    $marker = $root . '/extension/pumb_credit/.pay002-product-page-card-marker';
    if (is_file($marker)) { out('already_applied=yes'); exit(0); }
    need(is_dir(dirname($marker)), 'missing deployed PUMB extension directory for marker');

    $source = [];
    foreach ($files as $rel) {
        $source[$rel] = readFileChecked($root . '/' . $rel);
        $actual = hash('sha256', $source[$rel]);
        need(hash_equals(BEFORE_SHA256[$rel], $actual), 'live source SHA256 mismatch for ' . $rel . ' expected=' . BEFORE_SHA256[$rel] . ' actual=' . $actual . '; write skipped');
    }

    $productAnchor = <<<'PHP'
			// PAY-001-PHASE2C-CART-CONTRACT-GATES-20260725: keep the credit teaser
			// visible when Mono is configured. Quantity and threshold are interactive
			// client-side gates; checkout repeats both rules against authoritative data.
			$pay001_mono_price = (float)$product_info['special'] ?: (float)$product_info['price'];
			$pay001_mono_currency = strtoupper((string)($this->session->data['currency'] ?? $this->config->get('config_currency')));
			$data['pay001_mono_chast_visible'] =
				(bool)$this->config->get('payment_mono_chast_status') &&
				$pay001_mono_currency === 'UAH' &&
				trim((string)$this->config->get('payment_mono_chast_api_base')) !== '' &&
				trim((string)$this->config->get('payment_mono_chast_store_id')) !== '' &&
				trim((string)$this->config->get('payment_mono_chast_store_secret')) !== '';
			$data['pay001_mono_chast_price'] = round($pay001_mono_price, 2);
			$data['pay001_mono_chast_min_total'] = max(500.0, (float)$this->config->get('payment_mono_chast_min_total'));
			$data['pay001_mono_chast_cart_total'] = round((float)$this->cart->getSubTotal(), 2);
			$data['pay001_mono_chast_in_stock'] = (int)$product_info['quantity'] > 0;
			$pay001_mono_base_url = rtrim((string)$this->config->get('config_url'), '/');
			$data['pay001_mono_chast_checkout'] = $pay001_mono_base_url . '/index.php?route=checkout/checkout&language=' . rawurlencode((string)$this->config->get('config_language'));
PHP;
    $productReplacement = <<<'PHP'
			// PAY-001-PHASE2C-CART-CONTRACT-GATES-20260725: keep the credit teaser
			// visible when Mono is configured. Quantity and threshold are interactive
			// client-side gates; checkout repeats both rules against authoritative data.
			$pay001_mono_price = (float)$product_info['special'] ?: (float)$product_info['price'];
			$pay001_mono_currency = strtoupper((string)($this->session->data['currency'] ?? $this->config->get('config_currency')));
			$data['pay001_mono_chast_visible'] =
				(bool)$this->config->get('payment_mono_chast_status') &&
				$pay001_mono_currency === 'UAH' &&
				trim((string)$this->config->get('payment_mono_chast_api_base')) !== '' &&
				trim((string)$this->config->get('payment_mono_chast_store_id')) !== '' &&
				trim((string)$this->config->get('payment_mono_chast_store_secret')) !== '';
			$data['pay001_mono_chast_price'] = round($pay001_mono_price, 2);
			$data['pay001_mono_chast_min_total'] = max(500.0, (float)$this->config->get('payment_mono_chast_min_total'));
			$data['pay001_mono_chast_cart_total'] = round((float)$this->cart->getSubTotal(), 2);
			$data['pay001_mono_chast_in_stock'] = (int)$product_info['quantity'] > 0;
			$pay001_mono_base_url = rtrim((string)$this->config->get('config_url'), '/');
			$data['pay001_mono_chast_checkout'] = $pay001_mono_base_url . '/index.php?route=checkout/checkout&language=' . rawurlencode((string)$this->config->get('config_language'));

			// PAY-002: keep this predicate aligned with extension/pumb_credit and
			// checkout/payment_method.php. Those two files intentionally stay out of WP5.
			$pay002_pumb_configured = (bool)$this->config->get('payment_pumb_credit_status');
			foreach (['payment_pumb_credit_api_base', 'payment_pumb_credit_oauth_url', 'payment_pumb_credit_oauth_username', 'payment_pumb_credit_oauth_password', 'payment_pumb_credit_point_of_sale_code', 'payment_pumb_credit_partner_name'] as $pay002_pumb_key) {
				if (trim((string)$this->config->get($pay002_pumb_key)) === '') {
					$pay002_pumb_configured = false;
					break;
				}
			}
			$data['pay002_pumb_visible'] =
				$pay002_pumb_configured &&
				$pay001_mono_currency === 'UAH' &&
				((bool)$this->config->get('payment_pumb_credit_public') || !empty($this->session->data['pay002_pumb_preview']));
			$pay002_pumb_raw_terms = json_decode((string)$this->config->get('payment_pumb_credit_terms'), true);
			$pay002_pumb_terms = is_array($pay002_pumb_raw_terms) ? array_values(array_unique(array_map('intval', $pay002_pumb_raw_terms))) : [3, 4, 5];
			$pay002_pumb_terms = array_values(array_filter([3, 4, 5], static fn(int $term): bool => in_array($term, $pay002_pumb_terms, true)));
			if (!$pay002_pumb_terms) $pay002_pumb_terms = [3, 4, 5];
			$data['pay002_pumb_terms'] = $pay002_pumb_terms;
			$data['pay002_pumb_price'] = round($pay001_mono_price, 2);
			$data['pay002_pumb_min_total'] = max(500.0, (float)$this->config->get('payment_pumb_credit_min_total'));
			$data['pay002_pumb_max_total'] = max(0.0, (float)$this->config->get('payment_pumb_credit_max_total'));
			$data['pay002_pumb_cart_total'] = round((float)$this->cart->getSubTotal(), 2);
			$data['pay002_pumb_in_stock'] = (int)$product_info['quantity'] > 0;
			$data['pay002_pumb_checkout'] = $pay001_mono_base_url . '/index.php?route=checkout/checkout&language=' . rawurlencode((string)$this->config->get('config_language'));
PHP;
    $product = replaceExactOnce($source['catalog/controller/product/product.php'], $productAnchor, $productReplacement, 'product credit data');

    $teaserPattern = '~                \{% if pay001_mono_chast_visible %\}\R                <!-- PAY-001-PHASE2C-CART-CONTRACT-GATES-20260725 -->.*?\R                \{% endif %\}~s';
    $teaserReplacement = <<<'TWIG'
                {% if pay001_mono_chast_visible or pay002_pumb_visible %}
                <!-- PAY-001-PHASE2C-CART-CONTRACT-GATES-20260725 -->
                <div data-pay001-product-credit{% if pay001_mono_chast_visible %} data-pay001-price="{{ pay001_mono_chast_price }}" data-pay001-threshold="{{ pay001_mono_chast_min_total }}" data-pay001-cart-total="{{ pay001_mono_chast_cart_total }}" data-pay001-stock="{{ pay001_mono_chast_in_stock ? '1' : '0' }}"{% endif %}{% if pay002_pumb_visible %} data-pay002-price="{{ pay002_pumb_price }}" data-pay002-threshold="{{ pay002_pumb_min_total }}" data-pay002-max-total="{{ pay002_pumb_max_total }}" data-pay002-cart-total="{{ pay002_pumb_cart_total }}" data-pay002-stock="{{ pay002_pumb_in_stock ? '1' : '0' }}"{% endif %}>
                  <button type="button" class="bs-btn bs-btn-secondary pay001-product-credit__open pay001-product-credit__open--after-cart" data-pay001-credit-open disabled>Сплатити частинами</button>
                  <section class="pay001-product-credit is-muted" aria-label="Оплата частинами">
                    <div class="pay001-product-credit__info">
                    {% if pay001_mono_chast_visible %}
                    <div class="pay001-provider-row">
                      <img src="catalog/view/image/payment/pay001-mono-label.png" alt="monobank" class="pay001-provider-row__mono">
                      <span><strong>Покупка частинами monobank</strong><small>До 5 платежів</small></span>
                    </div>
                    {% endif %}
                    {% if pay002_pumb_visible %}
                    <div class="pay001-provider-row">
                      <img src="catalog/view/image/payment/pay001-pumb.svg" alt="ПУМБ" class="pay001-provider-row__pumb">
                      <span><strong>Сплачуйте частинами ПУМБ</strong><small>До {{ pay002_pumb_terms|last }} платежів</small></span>
                    </div>
                    {% endif %}
                    </div>
                    <p class="pay001-product-credit__hint" data-pay001-credit-hint aria-live="polite"></p>
                  </section>
                  </div>
                {% endif %}
TWIG;
    $teaserMatches = [];
    need(preg_match($teaserPattern, $source['catalog/view/template/product/product.twig'], $teaserMatches) === 1, 'product credit teaser capture failed');
    $legacyTeaser = replaceExactOnce($teaserMatches[0], '                {% if pay001_mono_chast_visible %}', '                {% if not pay002_pumb_visible %}{% if pay001_mono_chast_visible %}', 'legacy teaser opening branch');
    $legacyTeaser = replaceLastExact($legacyTeaser, '                {% endif %}', '                {% endif %}{% else %}' . "\n" . $teaserReplacement . "\n" . '                {% endif %}', 'legacy teaser closing branch');
    $twig = replaceExactOnce($source['catalog/view/template/product/product.twig'], $teaserMatches[0], $legacyTeaser, 'product credit teaser');

    $modalPattern = '~\{% if pay001_mono_chast_visible %\}\R<div class="pay001-modal" data-pay001-credit-modal hidden>.*?</script>\R\{% endif %\}~s';
    $modalReplacement = <<<'TWIG'
{% if pay001_mono_chast_visible or pay002_pumb_visible %}
<div class="pay001-modal" data-pay001-credit-modal hidden>
  <div class="pay001-modal__backdrop" data-pay001-credit-close></div>
  <section class="pay001-modal__sheet" role="dialog" aria-modal="true" aria-labelledby="pay001-credit-title">
    <button type="button" class="pay001-modal__close" aria-label="Закрити" data-pay001-credit-close>&times;</button>
    <h2 id="pay001-credit-title">Виберіть кредитну пропозицію</h2>
    {% if pay001_mono_chast_visible %}
    <article class="pay001-modal-provider" data-pay001-modal-provider="mono_chast">
      <div class="pay001-modal-provider__head"><img src="catalog/view/image/payment/pay001-mono-label.png" alt="monobank"><span><strong>Покупка частинами monobank</strong><small>Без комісії для вас</small></span></div>
      <div class="pay001-parts" data-pay001-parts>
        <button type="button" data-pay001-part="3" data-pay001-provider="mono_chast">3 платежі</button><button type="button" data-pay001-part="4" data-pay001-provider="mono_chast">4 платежі</button><button type="button" data-pay001-part="5" data-pay001-provider="mono_chast">5 платежів</button>
      </div>
      <div class="pay001-summary"><span>Щомісячний платіж<strong data-pay001-monthly></strong></span><span>Платежів<strong data-pay001-count></strong></span><span>Вартість товару<strong data-pay001-total></strong></span></div>
      <div class="pay001-modal__actions">
        <button type="button" class="bs-btn pay001-modal-provider__choose" data-pay001-credit-action="checkout" data-pay001-provider="mono_chast">Додати й оформити</button>
        <button type="button" class="bs-btn bs-btn-secondary pay001-modal-provider__continue" data-pay001-credit-action="continue" data-pay001-provider="mono_chast">Продовжити покупки</button>
      </div>
    </article>
    {% endif %}
    {% if pay002_pumb_visible %}
    <article class="pay001-modal-provider" data-pay001-modal-provider="pumb_credit">
      <div class="pay001-modal-provider__head"><img src="catalog/view/image/payment/pay001-pumb.svg" alt="ПУМБ"><span><strong>Сплачуйте частинами ПУМБ</strong><small>До {{ pay002_pumb_terms|last }} платежів</small></span></div>
      <div class="pay001-parts" data-pay001-parts>
        {% for term in pay002_pumb_terms %}<button type="button" data-pay001-part="{{ term }}" data-pay001-provider="pumb_credit">{{ term }} {{ term == 5 ? 'платежів' : 'платежі' }}</button>{% endfor %}
      </div>
      <div class="pay001-summary"><span>Щомісячний платіж<strong data-pay001-monthly></strong></span><span>Платежів<strong data-pay001-count></strong></span><span>Вартість товару<strong data-pay001-total></strong></span></div>
      <div class="pay001-modal__actions">
        <button type="button" class="bs-btn pay001-modal-provider__choose" data-pay001-credit-action="checkout" data-pay001-provider="pumb_credit">Додати й оформити</button>
        <button type="button" class="bs-btn bs-btn-secondary pay001-modal-provider__continue" data-pay001-credit-action="continue" data-pay001-provider="pumb_credit">Продовжити покупки</button>
      </div>
    </article>
    {% endif %}
    {% if pay001_mono_chast_visible %}<p class="pay001-modal__disclaimer">Без комісії для вас — умови кредитування визначає банк.</p>{% endif %}
  </section>
</div>
<script>
(function () {
  var modal = document.querySelector('[data-pay001-credit-modal]');
  var creditRoot = document.querySelector('[data-pay001-product-credit]');
  var quantityInput = document.getElementById('input-quantity');
  if (!modal || !creditRoot || !quantityInput) return;
  var hasMono = creditRoot.dataset.pay001Price !== undefined;
  var hasPumb = creditRoot.dataset.pay002Price !== undefined;
  var price = Number(hasMono ? creditRoot.dataset.pay001Price : creditRoot.dataset.pay002Price) || 0;
  var cartTotal = Number(hasMono ? creditRoot.dataset.pay001CartTotal : creditRoot.dataset.pay002CartTotal) || 0;
  var monoThreshold = Number(creditRoot.dataset.pay001Threshold) || 500;
  var pumbThreshold = Number(creditRoot.dataset.pay002Threshold) || 500;
  var pumbMaxTotal = Number(creditRoot.dataset.pay002MaxTotal) || 0;
  var monoInStock = creditRoot.dataset.pay001Stock === '1';
  var pumbInStock = creditRoot.dataset.pay002Stock === '1';
  var monoCheckoutUrl = hasMono ? {{ pay001_mono_chast_checkout|replace({'&amp;': '&'})|json_encode|raw }} : '';
  var pumbCheckoutUrl = hasPumb ? {{ pay002_pumb_checkout|replace({'&amp;': '&'})|json_encode|raw }} : '';
  var parts = {mono_chast: 3, pumb_credit: 3};
  var adding = false;
  function money(value) { return new Intl.NumberFormat('uk-UA', {maximumFractionDigits: 0}).format(Math.ceil(value)) + ' ₴'; }
  window.pay001PaymentsWord = window.pay001PaymentsWord || function (count) {
    count = Number(count) || 0;
    return count >= 2 && count <= 4 ? 'платежі' : 'платежів';
  };
  function quantity() { return Math.max(1, parseInt(quantityInput.value, 10) || 1); }
  function total() { return price * quantity(); }
  function eligibilityTotal() { return cartTotal + total(); }
  function providerAvailable(provider) {
    if (provider === 'mono_chast') return hasMono && monoInStock && eligibilityTotal() >= monoThreshold;
    return hasPumb && pumbInStock && eligibilityTotal() >= pumbThreshold && (!pumbMaxTotal || eligibilityTotal() <= pumbMaxTotal);
  }
  function renderProvider(provider) {
    var card = modal.querySelector('[data-pay001-modal-provider="' + provider + '"]');
    if (!card) return;
    var available = providerAvailable(provider);
    var count = parts[provider];
    card.querySelectorAll('[data-pay001-part]').forEach(function (button) {
      button.classList.toggle('is-active', Number(button.dataset.pay001Part) === count);
      button.disabled = !available;
    });
    card.querySelectorAll('[data-pay001-credit-action]').forEach(function (button) { button.disabled = !available; });
    card.querySelector('[data-pay001-monthly]').textContent = money(total() / count);
    card.querySelector('[data-pay001-count]').textContent = count;
    card.querySelector('[data-pay001-total]').textContent = money(total());
  }
  function renderProductState() {
    var available = providerAvailable('mono_chast') || providerAvailable('pumb_credit');
    var button = creditRoot.querySelector('[data-pay001-credit-open]');
    var panel = creditRoot.querySelector('.pay001-product-credit');
    var hint = creditRoot.querySelector('[data-pay001-credit-hint]');
    button.disabled = !available;
    panel.classList.toggle('is-muted', !available);
    panel.classList.toggle('is-preorder', !(monoInStock || pumbInStock));
    if (!(monoInStock || pumbInStock)) {
      hint.textContent = 'Оплата частинами доступна лише для товарів у наявності.';
    } else if (!available) {
      var threshold = hasPumb && !hasMono ? pumbThreshold : monoThreshold;
      hint.textContent = 'Оплата частинами доступна від ' + money(threshold) + ' — додайте ще ' + money(Math.max(0, threshold - eligibilityTotal())) + '.';
    } else {
      hint.textContent = '';
    }
  }
  function render() { renderProvider('mono_chast'); renderProvider('pumb_credit'); }
  function showCartRetry(message) {
    $('#alert .bs-cart-add-timeout').remove();
    $('#alert').prepend('<div class="alert alert-warning alert-dismissible bs-cart-add-timeout"><i class="fa-solid fa-circle-exclamation"></i> ' + message + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
  }
  function setAdding(button, state) {
    adding = state;
    modal.querySelectorAll('[data-pay001-credit-action]').forEach(function (item) { item.disabled = state; });
    if (state) {
      button.dataset.pay001OriginalText = button.textContent;
      button.textContent = 'Додаємо…';
      button.classList.add('is-loading');
    } else {
      render();
      modal.querySelectorAll('[data-pay001-credit-action]').forEach(function (item) {
        if (item.dataset.pay001OriginalText) {
          item.textContent = item.dataset.pay001OriginalText;
          delete item.dataset.pay001OriginalText;
        }
        item.classList.remove('is-loading');
      });
    }
  }
  function addProduct(action, button) {
    var provider = String(button.dataset.pay001Provider || '');
    if (adding || !providerAvailable(provider)) return;
    var form = $('#form-product');
    if (form.data('bsCartAddPending')) return;
    setAdding(button, true);
    $.ajax({
      url: 'index.php?route=checkout/cart.add&language={{ language }}',
      type: 'post',
      timeout: 12000,
      data: form.serialize(),
      dataType: 'json',
      contentType: 'application/x-www-form-urlencoded',
      cache: false,
      processData: false,
      complete: function () { setAdding(button, false); },
      success: function (json) {
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('.invalid-feedback').removeClass('d-block');
        if (json.error) {
          Object.keys(json.error).forEach(function (key) {
            $('#input-' + key.replaceAll('_', '-')).addClass('is-invalid').find('.form-control, .form-select, .form-check-input, .form-check-label').addClass('is-invalid');
            $('#error-' + key.replaceAll('_', '-')).html(json.error[key]).addClass('d-block');
          });
          return;
        }
        if (!json.success) return;
        $('#alert .bs-cart-add-timeout').remove();
        $('#alert').prepend('<div class="alert alert-success alert-dismissible"><i class="fa-solid fa-circle-check"></i> ' + json.success + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
        $('#cart').load('index.php?route=common/cart.info&language={{ language }}');
        if (action === 'checkout') {
          var checkoutUrl = provider === 'pumb_credit' ? pumbCheckoutUrl : monoCheckoutUrl;
          var parameter = provider === 'pumb_credit' ? 'pumb_credit_term' : 'mono_chast_parts';
          window.location.assign(checkoutUrl + '&' + parameter + '=' + encodeURIComponent(parts[provider]));
        } else {
          modal.hidden = true;
        }
      },
      error: function (xhr, ajaxOptions, thrownError) {
        showCartRetry(ajaxOptions === 'timeout' ? 'Додавання до кошика зайняло забагато часу. Перевірте кошик або спробуйте ще раз.' : 'Не вдалося додати товар до кошика. Спробуйте ще раз.');
        console.log(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
      }
    });
  }
  document.querySelectorAll('[data-pay001-credit-open]').forEach(function (button) { button.addEventListener('click', function () { if (!button.disabled) { modal.hidden = false; render(); } }); });
  modal.querySelectorAll('[data-pay001-credit-close]').forEach(function (button) { button.addEventListener('click', function () { if (!adding) modal.hidden = true; }); });
  modal.querySelectorAll('[data-pay001-part]').forEach(function (button) { button.addEventListener('click', function () { var provider = button.dataset.pay001Provider; parts[provider] = Number(button.dataset.pay001Part); renderProvider(provider); }); });
  modal.querySelectorAll('[data-pay001-credit-action]').forEach(function (button) { button.addEventListener('click', function () { addProduct(button.dataset.pay001CreditAction, button); }); });
  quantityInput.addEventListener('input', function () { renderProductState(); render(); });
  quantityInput.addEventListener('change', function () { renderProductState(); render(); });
  renderProductState();
  render();
}());
</script>
{% endif %}
TWIG;
    $modalMatches = [];
    need(preg_match($modalPattern, $source['catalog/view/template/product/product.twig'], $modalMatches) === 1, 'product credit modal capture failed');
    $legacyModal = replaceExactOnce($modalMatches[0], '{% if pay001_mono_chast_visible %}', '{% if not pay002_pumb_visible %}{% if pay001_mono_chast_visible %}', 'legacy modal opening branch');
    $legacyModal = replaceLastExact($legacyModal, '{% endif %}', '{% endif %}{% else %}' . "\n" . $modalReplacement . "\n" . '{% endif %}', 'legacy modal closing branch');
    $twig = replaceExactOnce($twig, $modalMatches[0], $legacyModal, 'product credit modal and script');

    $checkoutAnchor = <<<'PHP'
		// PAY-001-PHASE2-CREDIT-UI-20260721: product UI can suggest only 3/4/5.
		// The stock payment controller validates the final virtual option again.
		$pay001_parts = isset($this->request->get['mono_chast_parts']) ? (int)$this->request->get['mono_chast_parts'] : 0;
		if (in_array($pay001_parts, [3, 4, 5], true)) {
			// PAY-001-PHASE2C-D3-CREDIT-TERM-20260725:
			// a fresh modal redirect is authoritative over a credit method saved
			// by an earlier product. Clear only that stale credit selection so
			// payment_method.twig can apply and save the newly requested term.
			$current_pay001_code = (string)($this->session->data['payment_method']['code'] ?? '');
			if (str_starts_with($current_pay001_code, 'mono_chast.')) {
				unset($this->session->data['payment_method']);
			}
			$this->session->data['pay001_mono_chast_parts'] = $pay001_parts;
			$this->session->data['pay001_mono_chast_from_modal'] = 1;
		} else {
			// Direct or malformed checkout URLs must never inherit a credit-modal choice.
			unset($this->session->data['pay001_mono_chast_parts'], $this->session->data['pay001_mono_chast_from_modal']);
		}
PHP;
    $checkoutReplacement = <<<'PHP'
		// PAY-001-PHASE2-CREDIT-UI-20260721: product UI can suggest only 3/4/5.
		// The stock payment controller validates the final virtual option again.
		$pay001_parts = isset($this->request->get['mono_chast_parts']) ? (int)$this->request->get['mono_chast_parts'] : 0;
		$pay002_pumb_raw_terms = json_decode((string)$this->config->get('payment_pumb_credit_terms'), true);
		$pay002_pumb_terms = is_array($pay002_pumb_raw_terms) ? array_values(array_unique(array_map('intval', $pay002_pumb_raw_terms))) : [3, 4, 5];
		$pay002_pumb_terms = array_values(array_filter([3, 4, 5], static fn(int $term): bool => in_array($term, $pay002_pumb_terms, true)));
		if (!$pay002_pumb_terms) $pay002_pumb_terms = [3, 4, 5];
		$pay002_pumb_term = isset($this->request->get['pumb_credit_term']) ? (int)$this->request->get['pumb_credit_term'] : 0;
		$pay001_valid = in_array($pay001_parts, [3, 4, 5], true);
		$pay002_valid = in_array($pay002_pumb_term, $pay002_pumb_terms, true);
		$current_credit_code = (string)($this->session->data['payment_method']['code'] ?? '');

		if ($pay001_valid && !$pay002_valid) {
			// PAY-001-PHASE2C-D3-CREDIT-TERM-20260725:
			// a fresh modal redirect is authoritative over a credit method saved
			// by an earlier product. Clear either credit provider so the requested
			// Mono term is selected again by payment_method.twig.
			if (str_starts_with($current_credit_code, 'mono_chast.') || str_starts_with($current_credit_code, 'pumb_credit.')) {
				unset($this->session->data['payment_method']);
			}
			$this->session->data['pay001_mono_chast_parts'] = $pay001_parts;
			$this->session->data['pay001_mono_chast_from_modal'] = 1;
			unset($this->session->data['pay002_pumb_credit_term']);
		} elseif ($pay002_valid && !$pay001_valid) {
			// PAY-002: the PUMB product modal uses its own URL parameter and must
			// clear any earlier Mono modal preference before checkout renders.
			if (str_starts_with($current_credit_code, 'mono_chast.') || str_starts_with($current_credit_code, 'pumb_credit.')) {
				unset($this->session->data['payment_method']);
			}
			$this->session->data['pay002_pumb_credit_term'] = $pay002_pumb_term;
			unset($this->session->data['pay001_mono_chast_parts'], $this->session->data['pay001_mono_chast_from_modal']);
		} else {
			// Direct, malformed, or ambiguous URLs must not inherit either modal choice.
			unset($this->session->data['pay001_mono_chast_parts'], $this->session->data['pay001_mono_chast_from_modal'], $this->session->data['pay002_pumb_credit_term']);
		}
PHP;
    $checkout = replaceExactOnce($source['catalog/controller/checkout/checkout.php'], $checkoutAnchor, $checkoutReplacement, 'product modal term hand-off');

    $changed = [
        'catalog/controller/product/product.php' => $product,
        'catalog/view/template/product/product.twig' => $twig,
        'catalog/controller/checkout/checkout.php' => $checkout
    ];
    $stamp = date('Ymd-His');
    $backup = $root . '/_patch_backups/' . PATCH_ID . '-' . $stamp;
    foreach ($files as $rel) backupFile($root, $backup, $rel);

    try {
        foreach ($changed as $rel => $content) writeFileChecked($root . '/' . $rel, $content);
        foreach (['catalog/controller/product/product.php', 'catalog/controller/checkout/checkout.php'] as $rel) lint($root . '/' . $rel);
        foreach ($files as $rel) {
            $actual = hash_file('sha256', $root . '/' . $rel);
            need(is_string($actual), 'cannot hash generated file: ' . $rel);
            if (AFTER_SHA256[$rel] !== '') need(hash_equals(AFTER_SHA256[$rel], $actual), 'generated SHA256 mismatch for ' . $rel . ' expected=' . AFTER_SHA256[$rel] . ' actual=' . $actual);
            out('after_sha256=' . $rel . ':' . $actual);
        }
        $writtenTwig = readFileChecked($root . '/catalog/view/template/product/product.twig');
        need(substr_count($writtenTwig, 'pumb_credit_term') === 1, 'generated PUMB URL parameter assertion failed');
        need(substr_count($writtenTwig, 'СКОРО БУДЕ') === 2, 'generated closed-gate placeholder assertion failed');
        need(strpos($writtenTwig, 'Без комісії для вас') !== false, 'existing Mono commission copy missing');
        need(strpos($writtenTwig, 'ПУМБ</strong><small>Без комісії') === false, 'unsupported PUMB commission claim detected');
        need(substr_count($writtenTwig, 'data-pay001-modal-provider="pumb_credit"') === 1, 'generated PUMB modal assertion failed');
        need(substr_count($writtenTwig, 'data-pay001-modal-provider="mono_chast"') === 1, 'generated Mono modal assertion failed');
        need(substr_count($checkout, 'pay002_pumb_credit_term') === 3, 'generated PUMB session assertion failed');
        need(strpos($product, "'payment_pumb_credit_partner_name'") !== false, 'generated PUMB predicate assertion failed');
        out('assertions=ok');
        writeFileChecked($marker, PATCH_ID . ' applied ' . date('c') . PHP_EOL);
        out('cwd=' . $root);
        out('time=' . date('c'));
        out('backup=' . $backup);
        out('changed=' . implode(',', $files));
        out('database_touched=no');
        out('done=ok');
    } catch (Throwable $exception) {
        $reason = $exception->getMessage();
        restoreVerified($root, $backup, $files);
        fail('source restored: ' . $reason);
    }

    if (!@unlink(__FILE__)) out('self_delete=failed remove_uploaded_patch_manually=yes');
    else out('self_delete=ok');
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
