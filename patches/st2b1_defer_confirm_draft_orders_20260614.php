<?php
declare(strict_types=1);

$patch = 'st2b1_defer_confirm_draft_orders_20260614';
$root = getcwd() ?: __DIR__;
$time = gmdate('Ymd-His');
$backupRoot = '_patch_backups/' . $patch . '-' . $time;

$checkoutRel = 'catalog/view/template/checkout/checkout.twig';
$paymentMethodRel = 'catalog/controller/checkout/payment_method.php';

function st2b1_log(string $message): void
{
    echo '[' . gmdate('c') . '] ' . $message . PHP_EOL;
}

function st2b1_fail(string $message, int $code = 1): void
{
    st2b1_log('error=' . $message);
    st2b1_log('done=failed');
    exit($code);
}

function st2b1_join(string $base, string $rel): string
{
    return rtrim($base, "\\/") . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
}

function st2b1_read(string $path, string $rel): string
{
    if (!is_file($path)) {
        st2b1_fail('target file not found: ' . $rel);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        st2b1_fail('cannot read target file: ' . $rel);
    }

    return $content;
}

function st2b1_replace_once(string $content, string $search, string $replace, string $label): string
{
    $count = substr_count($content, $search);

    if ($count !== 1) {
        st2b1_fail('precheck failed: expected exactly 1 match for ' . $label . ', got ' . $count);
    }

    return str_replace($search, $replace, $content);
}

function st2b1_backup_and_write(string $root, string $backupRoot, string $rel, string $path, string $content): string
{
    $backup = st2b1_join($root, $backupRoot . '/' . $rel);
    $backupDir = dirname($backup);

    if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) {
        st2b1_fail('cannot create backup dir: ' . $backupDir);
    }

    if (!copy($path, $backup)) {
        st2b1_fail('cannot create backup: ' . $rel);
    }

    st2b1_log('backup: ' . $rel . ' -> ' . str_replace($root . DIRECTORY_SEPARATOR, '', $backup));

    if (file_put_contents($path, $content, LOCK_EX) === false) {
        @copy($backup, $path);
        st2b1_fail('cannot write target; backup restored: ' . $rel);
    }

    st2b1_log('changed=' . $rel);

    return $backup;
}

function st2b1_php_lint(string $root, string $rel, string $path, string $backup): void
{
    $cmd = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path);
    $output = [];
    $code = 0;
    exec($cmd, $output, $code);

    st2b1_log('php_lint=' . $rel . ' exit=' . $code);

    if ($output) {
        foreach ($output as $line) {
            st2b1_log('php_lint_output=' . $line);
        }
    }

    if ($code !== 0) {
        @copy($backup, $path);
        st2b1_fail('php lint failed; backup restored for ' . $rel);
    }
}

function st2b1_patch_checkout(string $content): string
{
    $marker = 'ST-2b.1: defer real confirm.confirm until explicit place-order click.';

    if (strpos($content, $marker) !== false) {
        return $content;
    }

    $content = st2b1_replace_once(
        $content,
        "  var bsAutoShippingStarted = false;\n",
        "  var bsAutoShippingStarted = false;\n  var bsCheckoutConfirmSubmitting = false;\n",
        'checkout submit guard variable'
    );

    $oldRefresh = <<<'TWIG'
  window.bsCheckoutRefreshConfirmIfPaymentReady = function() {
    if ($('#input-payment-code').val()) {
      $('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}');
      return true;
    }

    $('#checkout-confirm').html('');
    return false;
  };
TWIG;

    $newRefresh = <<<'TWIG'
  function bsCheckoutEscapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function(chr) {
      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[chr];
    });
  }

  function bsCheckoutDeferredConfirmHtml() {
    var shippingLabel = normalizeText($('#input-shipping-method').val());
    var paymentLabel = normalizeText($('#input-payment-method').val());
    var summary = '';

    if (shippingLabel) {
      summary += '<div class="small text-muted mb-1">Доставка: <strong>' + bsCheckoutEscapeHtml(shippingLabel) + '</strong></div>';
    }

    if (paymentLabel) {
      summary += '<div class="small text-muted mb-3">Оплата: <strong>' + bsCheckoutEscapeHtml(paymentLabel) + '</strong></div>';
    }

    return '<div class="bs-confirm-deferred" data-bs-confirm-deferred="1">' +
      '<h2 class="h5 mb-2">Підтвердження замовлення</h2>' +
      '<p class="text-muted mb-3">Замовлення ще не створено. Після кліку перевіримо кошик, створимо замовлення і перейдемо до оплати.</p>' +
      summary +
      '<div class="text-end"><button type="button" id="bs-button-confirm-deferred" class="btn btn-primary" data-bs-deferred-confirm="1">Оформити замовлення</button></div>' +
      '</div>';
  }

  // ST-2b.1: defer real confirm.confirm until explicit place-order click.
  window.bsCheckoutRefreshConfirmIfPaymentReady = function() {
    if ($('#input-payment-code').val()) {
      $('#checkout-confirm').html(bsCheckoutDeferredConfirmHtml());
      return true;
    }

    $('#checkout-confirm').html('');
    return false;
  };

  window.bsCheckoutLoadConfirmAndSubmit = function(trigger) {
    if (!$('#input-payment-code').val()) {
      $('#checkout-confirm').html('<div class="alert alert-warning mb-0">Оберіть спосіб оплати перед оформленням.</div>');
      return false;
    }

    if (bsCheckoutConfirmSubmitting) {
      return false;
    }

    bsCheckoutConfirmSubmitting = true;

    var button = $(trigger);
    button.prop('disabled', true).addClass('loading').text('Створюємо замовлення...');
    $('#checkout-confirm').addClass('bs-confirm-loading');

    $('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}', function(response, status) {
      $('#checkout-confirm').removeClass('bs-confirm-loading');

      if (status === 'error') {
        bsCheckoutConfirmSubmitting = false;
        $('#checkout-confirm').html('<div class="alert alert-danger mb-0">Не вдалося створити замовлення. Оновіть сторінку і спробуйте ще раз.</div>');
        return;
      }

      hideModelColumns();

      var realButton = $('#checkout-confirm #button-confirm').first();

      if (!realButton.length) {
        bsCheckoutConfirmSubmitting = false;
        $('#checkout-confirm').append('<div class="alert alert-danger mt-3 mb-0">Не знайдено кнопку підтвердження оплати після створення замовлення.</div>');
        return;
      }

      window.setTimeout(function() {
        realButton.trigger('click');
        bsCheckoutConfirmSubmitting = false;
      }, 30);
    });

    return false;
  };
TWIG;

    $content = st2b1_replace_once($content, $oldRefresh, $newRefresh, 'checkout confirm refresh function');

    $oldHandlerAnchor = <<<'TWIG'
  $(document).on('change', '#checkout-shipping-method input[type="radio"], #checkout-payment-method input[type="radio"]', markSelectedChoices);

  $(document).on('change', '#input-bs-recipient-other', updateRecipientMode);
TWIG;

    $newHandlerAnchor = <<<'TWIG'
  $(document).on('change', '#checkout-shipping-method input[type="radio"], #checkout-payment-method input[type="radio"]', markSelectedChoices);

  $(document).on('click.bsSt2b1DeferredConfirm', '#checkout-confirm [data-bs-deferred-confirm]', function(event) {
    event.preventDefault();
    event.stopImmediatePropagation();

    if (window.bsCheckoutLoadConfirmAndSubmit) {
      window.bsCheckoutLoadConfirmAndSubmit(this);
    }
  });

  $(document).on('change', '#input-bs-recipient-other', updateRecipientMode);
TWIG;

    $content = st2b1_replace_once($content, $oldHandlerAnchor, $newHandlerAnchor, 'checkout deferred confirm click handler');

    if (strpos($content, $marker) === false || strpos($content, 'click.bsSt2b1DeferredConfirm') === false || strpos($content, "checkout/confirm.confirm&language={{ language }}") === false) {
        st2b1_fail('postcheck failed: checkout deferred confirm guard not installed');
    }

    if (substr_count($content, "$('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}'") !== 1) {
        st2b1_fail('postcheck failed: checkout.twig must contain exactly one real confirm.confirm load after patch');
    }

    return $content;
}

function st2b1_patch_payment_method(string $content): string
{
    $marker = 'ST-2b.1: comment can be saved in session before deferred order exists.';

    if (strpos($content, $marker) !== false) {
        return $content;
    }

    $old = <<<'PHP'
		if (isset($this->session->data['order_id'])) {
			$order_id = (int)$this->session->data['order_id'];
		} else {
			$order_id = 0;
		}

		if (isset($this->request->post['comment'])) {
			$comment = (string)$this->request->post['comment'];
		} else {
			$comment = '';
		}

		$order_info = $this->model_checkout_order->getOrder($order_id);

		if (!$order_info) {
			$json['error'] = $this->language->get('error_order');
		}

		if (!$json) {
			$this->session->data['comment'] = $comment;

			$this->model_checkout_order->editComment($order_id, $comment);

			$json['success'] = $this->language->get('text_comment');
		}
PHP;

    $new = <<<'PHP'
		if (isset($this->session->data['order_id'])) {
			$order_id = (int)$this->session->data['order_id'];
		} else {
			$order_id = 0;
		}

		if (isset($this->request->post['comment'])) {
			$comment = (string)$this->request->post['comment'];
		} else {
			$comment = '';
		}

		// ST-2b.1: comment can be saved in session before deferred order exists.
		$this->session->data['comment'] = $comment;

		if ($order_id) {
			$order_info = $this->model_checkout_order->getOrder($order_id);
		} else {
			$order_info = [];
		}

		if ($order_id && !$order_info) {
			$json['error'] = $this->language->get('error_order');
		}

		if (!$json) {
			if ($order_info) {
				$this->model_checkout_order->editComment($order_id, $comment);
			}

			$json['success'] = $this->language->get('text_comment');
		}
PHP;

    $content = st2b1_replace_once($content, $old, $new, 'payment_method comment deferred-order guard');

    if (strpos($content, $marker) === false || substr_count($content, '$this->session->data[\'comment\'] = $comment;') !== 1) {
        st2b1_fail('postcheck failed: payment_method comment session guard not installed');
    }

    return $content;
}

st2b1_log('patch=' . $patch);
st2b1_log('cwd=' . $root);
st2b1_log('time=' . gmdate('c'));
st2b1_log('scope=ST-2b.1 defer checkout/confirm.confirm order creation until explicit place-order click; preserve comments before deferred order');
st2b1_log('db_schema_changes=none');
st2b1_log('db_data_changes=none_by_patch; runtime order creation remains in existing checkout/confirm.confirm after user click');

$checkoutPath = st2b1_join($root, $checkoutRel);
$paymentMethodPath = st2b1_join($root, $paymentMethodRel);

$checkoutContent = st2b1_read($checkoutPath, $checkoutRel);
$paymentMethodContent = st2b1_read($paymentMethodPath, $paymentMethodRel);

$newCheckout = st2b1_patch_checkout($checkoutContent);
$newPaymentMethod = st2b1_patch_payment_method($paymentMethodContent);

$writes = [];

if ($newCheckout !== $checkoutContent) {
    $writes[$checkoutRel] = [$checkoutPath, $newCheckout, false];
} else {
    st2b1_log('already_applied=yes file=' . $checkoutRel);
}

if ($newPaymentMethod !== $paymentMethodContent) {
    $writes[$paymentMethodRel] = [$paymentMethodPath, $newPaymentMethod, true];
} else {
    st2b1_log('already_applied=yes file=' . $paymentMethodRel);
}

if (!$writes) {
    st2b1_log('done=ok already_applied=yes');
    @unlink(__FILE__);
    exit(0);
}

$backups = [];

foreach ($writes as $rel => [$path, $content, $needsLint]) {
    $backups[$rel] = st2b1_backup_and_write($root, $backupRoot, $rel, $path, $content);
}

foreach ($writes as $rel => [$path, $content, $needsLint]) {
    if ($needsLint) {
        st2b1_php_lint($root, $rel, $path, $backups[$rel]);
    }
}

st2b1_log('changed_files=' . implode(',', array_keys($writes)));
st2b1_log('rollback=restore files from ' . $backupRoot . ' and clear OpenCart template/cache');
st2b1_log('done=ok');

@unlink(__FILE__);
