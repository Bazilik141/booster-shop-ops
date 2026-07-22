<?php
/**
 * PAY-001 Phase 2 follow-up — stock checkout entry, direct credit drawer, availability guard.
 *
 * Scope: keep product-modal navigation on stock checkout, reveal Mono options on a direct
 * stock-checkout selection, place the product info block under the button, and hide all
 * credit UI for quantity < 1. No DB/settings/url.php/SimpleCheckout changes.
 *
 * Rollback: restore every file from
 * _patch_backups/PAY-001_phase2_stock-entry-and-availability_20260722-<timestamp>/files/.
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-001_phase2_stock-entry-and-availability_20260722';
const PATCH_MARKER = 'PAY-001-PHASE2-STOCK-ENTRY-AND-AVAILABILITY-20260722';

function say(string $message): void { echo $message . PHP_EOL; }
function fail(string $message): never { fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL); exit(1); }
function normalized(string $value): string { return str_replace("\r\n", "\n", $value); }
function replace_once(string $contents, string $needle, string $replacement, string $label): string {
	$count = substr_count($contents, $needle);
	if ($count !== 1) fail($label . ' anchor count=' . $count . ', expected=1. No files changed.');
	return str_replace($needle, $replacement, $contents);
}
function backup_file(string $root, string $backup, string $relative): void {
	$source = $root . '/' . $relative;
	$destination = $backup . '/files/' . $relative;
	$directory = dirname($destination);
	if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) fail('Cannot create backup directory for ' . $relative);
	if (!copy($source, $destination)) fail('Cannot back up ' . $relative);
}
function write_target(string $root, string $relative, string $contents): void {
	if (file_put_contents($root . '/' . $relative, $contents, LOCK_EX) === false) fail('Cannot write ' . $relative);
}

$root = __DIR__;
$config = $root . '/config.php';
if (!is_file($config)) fail('config.php missing; run only from public_html.');
say('cwd=' . $root);
say('time=' . date('c'));

$targets = [
	'catalog/controller/product/product.php',
	'catalog/view/template/product/product.twig',
	'catalog/view/template/checkout/payment_method.twig',
];
foreach ($targets as $relative) {
	if (!is_file($root . '/' . $relative)) fail('Target missing: ' . $relative);
}

$productCurrent = file_get_contents($root . '/catalog/controller/product/product.php');
if ($productCurrent === false) fail('Cannot read product.php.');
if (str_contains($productCurrent, PATCH_MARKER)) {
	say('already_applied=yes');
	@unlink(__FILE__);
	exit(0);
}
if (!str_contains($productCurrent, 'PAY-001-PHASE2-CREDIT-UI-20260721')) {
	fail('Phase 2 credit UI marker missing; refusing an unverified follow-up.');
}

$source = [];
foreach ($targets as $relative) {
	$contents = file_get_contents($root . '/' . $relative);
	if ($contents === false) fail('Cannot read: ' . $relative);
	$source[$relative] = normalized($contents);
}

$productAnchor = <<<'PHP'
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
$productReplacement = <<<'PHP'
			// PAY-001-PHASE2-STOCK-ENTRY-AND-AVAILABILITY-20260722: credit is only
			// offered for a sellable item. A raw stock-checkout URL deliberately bypasses
			// the current URL::link() SimpleCheckout redirect; the controller revalidates parts.
			$pay001_mono_price = (float)$product_info['special'] ?: (float)$product_info['price'];
			$pay001_mono_currency = strtoupper((string)($this->session->data['currency'] ?? $this->config->get('config_currency')));
			$data['pay001_mono_chast_visible'] =
				(bool)$this->config->get('payment_mono_chast_status') &&
				(int)$product_info['quantity'] > 0 &&
				$pay001_mono_currency === 'UAH' &&
				$pay001_mono_price >= max(500.0, (float)$this->config->get('payment_mono_chast_min_total')) &&
				trim((string)$this->config->get('payment_mono_chast_api_base')) !== '' &&
				trim((string)$this->config->get('payment_mono_chast_store_id')) !== '' &&
				trim((string)$this->config->get('payment_mono_chast_store_secret')) !== '';
			$data['pay001_mono_chast_price'] = round($pay001_mono_price, 2);
			$pay001_mono_base_url = rtrim((string)$this->config->get('config_url'), '/');
			$data['pay001_mono_chast_checkout'] = $pay001_mono_base_url . '/index.php?route=checkout/checkout&language=' . rawurlencode((string)$this->config->get('config_language'));
PHP;
$source['catalog/controller/product/product.php'] = replace_once($source['catalog/controller/product/product.php'], $productAnchor, $productReplacement, 'product.php Phase 2 gate');

$infoAnchor = <<<'TWIG'
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
$source['catalog/view/template/product/product.twig'] = replace_once($source['catalog/view/template/product/product.twig'], $infoAnchor, '', 'product.twig top credit info');

$buttonAnchor = <<<'TWIG'
                {% if pay001_mono_chast_visible %}
                <button type="button" class="bs-btn bs-btn-secondary pay001-product-credit__open pay001-product-credit__open--after-cart" data-pay001-credit-open>Оплатити частинами</button>
                {% endif %}
                <input type="hidden" name="product_id" value="{{ product_id }}" id="input-product-id"/>
TWIG;
$buttonReplacement = <<<'TWIG'
                {% if pay001_mono_chast_visible %}
                <button type="button" class="bs-btn bs-btn-secondary pay001-product-credit__open pay001-product-credit__open--after-cart" data-pay001-credit-open>Оплатити частинами</button>
                <!-- PAY-001-PHASE2-STOCK-ENTRY-AND-AVAILABILITY-20260722 -->
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
                <input type="hidden" name="product_id" value="{{ product_id }}" id="input-product-id"/>
TWIG;
$source['catalog/view/template/product/product.twig'] = replace_once($source['catalog/view/template/product/product.twig'], $buttonAnchor, $buttonReplacement, 'product.twig credit info below button');

$drawerAnchor = <<<'TWIG'
      html += '<label class="bs-checkout-method-option' + (option.pay001Credit ? ' pay001-checkout-method' : '') + '" for="' + inputId + '">' +
        '<input type="radio" name="payment_method" id="' + inputId + '" value="' + escapeHtml(renderedCode) + '" data-label="' + escapeHtml(option.label) + '"' + checked + ' />' +
        '<span>' + (option.pay001Credit ? '<strong>Оплатити частинами</strong><small>Розстрочка від підключених банків</small>' : escapeHtml(option.label)) + '</span></label>';
      if (option.pay001Credit && checked) html += pay001Drawer(option, selected.code).prop('outerHTML');
TWIG;
$drawerReplacement = <<<'TWIG'
      var $pay001Drawer = null;
      if (option.pay001Credit) {
        $pay001Drawer = pay001Drawer(option, selected && selected.pay001Credit ? selected.code : renderedCode);
        if (!checked) $pay001Drawer.attr('hidden', 'hidden');
      }
      html += '<label class="bs-checkout-method-option' + (option.pay001Credit ? ' pay001-checkout-method' : '') + '" for="' + inputId + '">' +
        '<input type="radio" name="payment_method" id="' + inputId + '" value="' + escapeHtml(renderedCode) + '" data-label="' + escapeHtml(option.label) + '"' + (option.pay001Credit ? ' data-pay001-credit="1"' : '') + checked + ' />' +
        '<span>' + (option.pay001Credit ? '<strong>Оплатити частинами</strong><small>Розстрочка від підключених банків</small>' : escapeHtml(option.label)) + '</span></label>';
      if ($pay001Drawer) html += $pay001Drawer.prop('outerHTML');
TWIG;
$source['catalog/view/template/checkout/payment_method.twig'] = replace_once($source['catalog/view/template/checkout/payment_method.twig'], $drawerAnchor, $drawerReplacement, 'payment_method.twig direct credit drawer');

$eventAnchor = <<<'TWIG'
  $(document).on('change', '#bs-payment-methods input[name="payment_method"]', function(event) {
    var $input = $(this);
    savePayment($input.val(), $input.data('label'), false, event, window.bsCheckoutState ? window.bsCheckoutState.currentRevision() : undefined);
  });
TWIG;
$eventReplacement = <<<'TWIG'
  $(document).on('change', '#bs-payment-methods input[name="payment_method"]', function(event) {
    var $input = $(this);
    if ($input.data('pay001Credit')) {
      $input.closest('label').next('[data-pay001-drawer]').prop('hidden', false);
    }
    savePayment($input.val(), $input.data('label'), false, event, window.bsCheckoutState ? window.bsCheckoutState.currentRevision() : undefined);
  });
TWIG;
$source['catalog/view/template/checkout/payment_method.twig'] = replace_once($source['catalog/view/template/checkout/payment_method.twig'], $eventAnchor, $eventReplacement, 'payment_method.twig direct credit selection');

$timestamp = date('Ymd-His');
$backup = $root . '/_patch_backups/' . PATCH_ID . '-' . $timestamp;
foreach ($targets as $relative) backup_file($root, $backup, $relative);
say('backup=' . $backup);

foreach ($targets as $relative) {
	write_target($root, $relative, $source[$relative]);
	say('changed_file=' . $relative);
}

$lint = [];
$exitCode = 0;
exec(PHP_BINARY . ' -l ' . escapeshellarg($root . '/catalog/controller/product/product.php') . ' 2>&1', $lint, $exitCode);
if ($exitCode !== 0) {
	foreach ($targets as $relative) {
		$backupFile = $backup . '/files/' . $relative;
		if (is_file($backupFile)) @copy($backupFile, $root . '/' . $relative);
	}
	fail('php_l=failed; restored files. ' . implode(' | ', $lint));
}
say('php_l=ok');
say('done=ok');
@unlink(__FILE__);
