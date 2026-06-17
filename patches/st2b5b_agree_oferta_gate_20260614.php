<?php
/**
 * ST-2b.5B: stock checkout agree / oferta gate.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$patchId = 'st2b5b_agree_oferta_gate_20260614';
$root = getenv('BS_PATCH_ROOT') ?: getcwd();
$backupDir = rtrim($root, "/\\") . '/_patch_backups/' . $patchId . '-' . date('Ymd-His');
$changed = [];
$alreadyApplied = true;

function bs5b_fail(string $message): void {
    fwrite(STDERR, "error=$message" . PHP_EOL);
    exit(1);
}

function bs5b_path(string $root, string $relative): string {
    return rtrim($root, "/\\") . '/' . ltrim($relative, "/\\");
}

function bs5b_read(string $root, string $relative): string {
    $path = bs5b_path($root, $relative);
    if (!is_file($path)) {
        bs5b_fail("missing_file:$relative");
    }
    $content = file_get_contents($path);
    if ($content === false) {
        bs5b_fail("read_failed:$relative");
    }
    return $content;
}

function bs5b_backup(string $root, string $relative, string $backupDir): void {
    $src = bs5b_path($root, $relative);
    $dst = rtrim($backupDir, "/\\") . '/' . $relative;
    $dir = dirname($dst);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        bs5b_fail("backup_mkdir_failed:$dir");
    }
    if (!copy($src, $dst)) {
        bs5b_fail("backup_failed:$relative");
    }
}

function bs5b_write(string $root, string $relative, string $content, string $backupDir, array &$changed, bool &$alreadyApplied): void {
    $current = bs5b_read($root, $relative);
    if ($current === $content) {
        return;
    }
    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true)) {
        bs5b_fail("backup_root_mkdir_failed:$backupDir");
    }
    bs5b_backup($root, $relative, $backupDir);
    if (file_put_contents(bs5b_path($root, $relative), $content) === false) {
        bs5b_fail("write_failed:$relative");
    }
    $changed[] = $relative;
    $alreadyApplied = false;
}

function bs5b_replace_once(string $content, string $search, string $replace, string $label): string {
    $count = substr_count($content, $search);
    if ($count !== 1) {
        bs5b_fail("anchor_count_failed:$label:$count");
    }
    return str_replace($search, $replace, $content);
}

function bs5b_patch_checkout_twig(string $content): string {
    if (strpos($content, 'ST-2b.5B checkout agree gate') !== false) {
        return $content;
    }

    $old = <<<'JS'
  function bsCheckoutHasPaymentReady() {
    return !!$('#input-payment-code').val();
  }

  function bsCheckoutCanConfirm() {
    return bsCheckoutHasShippingReady() && bsCheckoutHasPaymentReady();
  }
JS;

    $new = <<<'JS'
  function bsCheckoutHasPaymentReady() {
    return !!$('#input-payment-code').val();
  }

  // ST-2b.5B checkout agree gate.
  function bsCheckoutHasAgreeReady() {
    var agree = $('#input-checkout-agree');
    return !agree.length || agree.prop('checked');
  }

  function bsCheckoutCanConfirm() {
    return bsCheckoutHasShippingReady() && bsCheckoutHasPaymentReady() && bsCheckoutHasAgreeReady();
  }
JS;

    $content = bs5b_replace_once($content, $old, $new, 'checkout_agree_ready_helper');

    $old = <<<'JS'
    var hasShipping = bsCheckoutHasShippingReady();
    var hasPayment = bsCheckoutHasPaymentReady();

    if (!hasShipping && !hasPayment) {
      return 'Заповніть доставку і оберіть спосіб оплати, щоб оформити замовлення.';
    }

    if (!hasShipping) {
      return 'Заповніть доставку, щоб оформити замовлення.';
    }

    if (!hasPayment) {
      return 'Оберіть спосіб оплати, щоб оформити замовлення.';
    }

    return 'Все готово. Натисніть кнопку, щоб оформити замовлення.';
JS;

    $new = <<<'JS'
    var hasShipping = bsCheckoutHasShippingReady();
    var hasPayment = bsCheckoutHasPaymentReady();
    var hasAgree = bsCheckoutHasAgreeReady();

    if (!hasShipping && !hasPayment) {
      return 'Заповніть доставку і оберіть спосіб оплати, щоб оформити замовлення.';
    }

    if (!hasShipping) {
      return 'Заповніть доставку, щоб оформити замовлення.';
    }

    if (!hasPayment) {
      return 'Оберіть спосіб оплати, щоб оформити замовлення.';
    }

    if (!hasAgree) {
      return 'Підтвердіть згоду з умовами оферти, щоб оформити замовлення.';
    }

    return 'Все готово. Натисніть кнопку, щоб оформити замовлення.';
JS;

    return bs5b_replace_once($content, $old, $new, 'checkout_agree_hint');
}

function bs5b_patch_payment_method_twig(string $content): string {
    if (strpos($content, 'ST-2b.5B checkout agree only') !== false) {
        return $content;
    }

    $old = <<<'JS'
  $(document).on('change', 'input[name="agree"]', function() {
    var $input = $(this);
JS;

    $new = <<<'JS'
  // ST-2b.5B checkout agree only; register account agree is separate.
  $(document).on('change', '#input-checkout-agree', function() {
    var $input = $(this);
JS;

    return bs5b_replace_once($content, $old, $new, 'payment_twig_agree_selector');
}

function bs5b_patch_payment_method_controller(string $content): string {
    if (strpos($content, 'ST-2b.5B: persist checkout agree') !== false) {
        return $content;
    }

    $old = <<<'PHP'
		// ST-2b.1: comment can be saved in session before deferred order exists.
		$this->session->data['comment'] = $comment;

		if ($order_id) {
PHP;

    $new = <<<'PHP'
		// ST-2b.1: comment can be saved in session before deferred order exists.
		$this->session->data['comment'] = $comment;

		// ST-2b.5B: persist checkout agree when the deferred checkout UI posts it with the comment.
		if (array_key_exists('agree', $this->request->post)) {
			if ($this->request->post['agree']) {
				$this->session->data['agree'] = $this->request->post['agree'];
			} else {
				unset($this->session->data['agree']);
			}
		}

		if ($order_id) {
PHP;

    return bs5b_replace_once($content, $old, $new, 'payment_controller_persist_agree');
}

foreach ([
    'catalog/view/template/checkout/checkout.twig',
    'catalog/view/template/checkout/payment_method.twig',
    'catalog/controller/checkout/payment_method.php',
] as $file) {
    bs5b_read($root, $file);
}

$checkout = bs5b_patch_checkout_twig(bs5b_read($root, 'catalog/view/template/checkout/checkout.twig'));
bs5b_write($root, 'catalog/view/template/checkout/checkout.twig', $checkout, $backupDir, $changed, $alreadyApplied);

$paymentTwig = bs5b_patch_payment_method_twig(bs5b_read($root, 'catalog/view/template/checkout/payment_method.twig'));
bs5b_write($root, 'catalog/view/template/checkout/payment_method.twig', $paymentTwig, $backupDir, $changed, $alreadyApplied);

$paymentController = bs5b_patch_payment_method_controller(bs5b_read($root, 'catalog/controller/checkout/payment_method.php'));
bs5b_write($root, 'catalog/controller/checkout/payment_method.php', $paymentController, $backupDir, $changed, $alreadyApplied);

exec('php -l ' . escapeshellarg(bs5b_path($root, 'catalog/controller/checkout/payment_method.php')), $lintOutput, $lintCode);
echo 'php_lint=catalog/controller/checkout/payment_method.php exit=' . $lintCode . PHP_EOL;
if ($lintOutput) {
    echo 'php_lint_output=' . implode(' | ', $lintOutput) . PHP_EOL;
}
if ($lintCode !== 0) {
    bs5b_fail('php_lint_failed:catalog/controller/checkout/payment_method.php');
}

$checkoutFinal = bs5b_read($root, 'catalog/view/template/checkout/checkout.twig');
if (strpos($checkoutFinal, 'bsCheckoutHasAgreeReady') === false || substr_count($checkoutFinal, 'checkout/confirm.confirm') !== 1) {
    bs5b_fail('postcheck_checkout_agree_gate_failed');
}

$paymentTwigFinal = bs5b_read($root, 'catalog/view/template/checkout/payment_method.twig');
if (strpos($paymentTwigFinal, '#input-checkout-agree') === false || strpos($paymentTwigFinal, 'ST-2b.5B checkout agree only') === false) {
    bs5b_fail('postcheck_payment_twig_agree_failed');
}

echo 'patch=' . $patchId . PHP_EOL;
echo 'cwd=' . getcwd() . PHP_EOL;
echo 'time=' . date('c') . PHP_EOL;
echo 'db_schema_changes=none' . PHP_EOL;
echo 'db_data_changes=none' . PHP_EOL;
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
