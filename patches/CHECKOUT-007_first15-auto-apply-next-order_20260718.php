<?php
/**
 * CHECKOUT-007 — automatically apply First15 on the true next checkout.
 *
 * Runtime DB effect (owner-approved in the CHECKOUT-007 handoff): only the
 * JSON `customer.custom_field` of shortcut-created customers gets the
 * `bs_first15_pending` key, then that key is removed after First15 applies or
 * is found already used. No schema migration or bulk update is performed.
 *
 * Rollback source files from _patch_backups/CHECKOUT-007_first15-auto-apply-next-order_20260718-<timestamp>/.
 */
declare(strict_types=1);

const PATCH_ID = 'CHECKOUT-007_first15-auto-apply-next-order_20260718';

function out(string $line): void { echo $line . PHP_EOL; }
function fail(string $line): void { out('error=' . $line); exit(1); }
function replaceOnce(string $contents, string $anchor, string $replacement, string $file): string {
	$eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
	$anchor = str_replace("\n", $eol, $anchor);
	$replacement = str_replace("\n", $eol, $replacement);
	$count = substr_count($contents, $anchor);
	if ($count !== 1) {
		fail('anchor_count=' . $count . ' expected=1 file=' . $file);
	}
	return str_replace($anchor, $replacement, $contents);
}
function backupAndWrite(string $file, string $contents, string $backup_dir): void {
	$backup = $backup_dir . DIRECTORY_SEPARATOR . $file;
	$parent = dirname($backup);
	if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
		fail('backup_dir_create_failed path=' . $parent);
	}
	if (!copy($file, $backup)) {
		fail('backup_copy_failed file=' . $file);
	}
	if (file_put_contents($file, $contents) === false) {
		@copy($backup, $file);
		fail('write_failed file=' . $file);
	}
}
function restore(string $backup_dir, array $files): void {
	foreach ($files as $file) {
		$backup = $backup_dir . DIRECTORY_SEPARATOR . $file;
		if (is_file($backup)) {
			@copy($backup, $file);
		}
	}
}

$payment_file = 'catalog/controller/checkout/payment_method.php';
$coupon_controller_file = 'catalog/controller/checkout/coupon.php';
$coupon_model_file = 'catalog/model/checkout/booster_coupon.php';
$success_twig_file = 'catalog/view/template/checkout/success.twig';
$files = [$payment_file, $coupon_controller_file, $coupon_model_file, $success_twig_file];

out('cwd=' . getcwd());
out('time=' . date('c'));

foreach ($files as $file) {
	if (!is_file($file)) {
		fail('missing_file=' . $file);
	}
}

$payment = file_get_contents($payment_file);
$coupon_controller = file_get_contents($coupon_controller_file);
$coupon_model = file_get_contents($coupon_model_file);
$success_twig = file_get_contents($success_twig_file);
if ($payment === false || $coupon_controller === false || $coupon_model === false || $success_twig === false) {
	fail('read_failed');
}

$payment_marker = 'CHECKOUT-007: persist the durable First15 eligibility flag.';
$controller_marker = 'CHECKOUT-007: silently resolve the durable next-order First15 flag.';
$model_marker = 'CHECKOUT-007: auto-apply First15 only after the registration order is complete.';
$twig_marker = 'CHECKOUT-007: First15 will be applied automatically on the next order.';
$markers = [
	strpos($payment, $payment_marker) !== false,
	strpos($coupon_controller, $controller_marker) !== false,
	strpos($coupon_model, $model_marker) !== false,
	strpos($success_twig, $twig_marker) !== false,
];
if (in_array(true, $markers, true)) {
	if ($markers === [true, true, true, true]) {
		out('already_applied=yes');
		exit(0);
	}
	fail('partial_marker_detected');
}

if (strpos($payment, 'CHECKOUT-006 next-order First15 offer flag.') === false) {
	fail('checkout006_current_order_guard_missing');
}
if (strpos($coupon_model, 'public function applyPendingWelcomeCoupon') === false || strpos($coupon_model, 'public function hasCouponOrderUsage') === false) {
	fail('coupon_model_contract_missing');
}

$payment_old = <<<'PHP'
		$password = bin2hex(random_bytes(24)) . 'Aa1!';
		$created_customer_id = 0;
		$login_complete = false;

		try {
PHP;
$payment_new = <<<'PHP'
		$password = bin2hex(random_bytes(24)) . 'Aa1!';
		$created_customer_id = 0;
		$login_complete = false;

		// CHECKOUT-007: persist the durable First15 eligibility flag.
		// Preserve every existing customer custom field; this marker belongs only
		// to the guest-during-checkout account creation path.
		$checkout007_customer_custom_field = is_array($customer_data['custom_field'] ?? null) ? $customer_data['custom_field'] : [];
		$checkout007_customer_custom_field['bs_first15_pending'] = 1;

		try {
PHP;
$payment = replaceOnce($payment, $payment_old, $payment_new, $payment_file);
$payment = replaceOnce(
	$payment,
	"\t\t\t\t'custom_field'      => is_array(\$customer_data['custom_field'] ?? null) ? \$customer_data['custom_field'] : [],",
	"\t\t\t\t'custom_field'      => \$checkout007_customer_custom_field,",
	$payment_file
);

$controller_old = <<<'PHP'
	public function summary(): void {
		$this->load->model('checkout/booster_coupon');
		$this->output($this->model_checkout_booster_coupon->applyPendingWelcomeCoupon($this->postedEmail()));
	}
PHP;
$controller_new = <<<'PHP'
	public function summary(): void {
		$this->load->model('checkout/booster_coupon');
		$result = $this->model_checkout_booster_coupon->applyPendingWelcomeCoupon($this->postedEmail());

		// CHECKOUT-007: silently resolve the durable next-order First15 flag.
		// This endpoint is loaded with every real checkout page; the model keeps
		// the registration order protected until its success page consumes the guard.
		if (empty($result['success']) && empty($this->session->data['coupon']) && $this->customer->isLogged()) {
			$automatic = $this->model_checkout_booster_coupon->applyPendingFirst15ForCustomer((int)$this->customer->getId());

			if (!empty($automatic['success'])) {
				unset($result['error']);
				$result = array_merge($result, $automatic);
			}
		}

		$this->output($result);
	}
PHP;
$coupon_controller = replaceOnce($coupon_controller, $controller_old, $controller_new, $coupon_controller_file);

$model_anchor = "\n\tpublic function hasCouponOrderUsage(string \$coupon, string \$email = ''): bool {";
$model_methods = <<<'PHP'

	/**
	 * CHECKOUT-007: auto-apply First15 only after the registration order is complete.
	 * The CHECKOUT-006 session flag exists only through that order's success render.
	 */
	public function applyPendingFirst15ForCustomer(int $customer_id): array {
		if (
			$customer_id <= 0 ||
			!$this->customer->isLogged() ||
			(int)$this->customer->getId() !== $customer_id ||
			!$this->cart->hasProducts() ||
			!empty($this->session->data['coupon']) ||
			!empty($this->session->data['checkout001_first15_offer_pending'])
		) {
			return [];
		}

		$this->load->model('account/customer');
		$customer_info = $this->model_account_customer->getCustomer($customer_id);
		$custom_field = is_array($customer_info['custom_field'] ?? null) ? $customer_info['custom_field'] : [];

		if (!$customer_info || empty($custom_field['bs_first15_pending'])) {
			return [];
		}

		$email = trim((string)($customer_info['email'] ?? ''));

		if ($this->hasCouponOrderUsage('First15', $email)) {
			$this->clearPendingFirst15ForCustomer($customer_id, $customer_info);
			return [];
		}

		$result = $this->applyCouponCode('First15', $email);

		if (!empty($result['success'])) {
			$this->clearPendingFirst15ForCustomer($customer_id, $customer_info);
			return $result;
		}

		// Coupon availability failures are deliberately silent and retryable.
		return [];
	}

	private function clearPendingFirst15ForCustomer(int $customer_id, array $customer_info): void {
		$custom_field = is_array($customer_info['custom_field'] ?? null) ? $customer_info['custom_field'] : [];
		unset($custom_field['bs_first15_pending']);

		try {
			$this->model_account_customer->editCustomer($customer_id, [
				'firstname' => (string)($customer_info['firstname'] ?? ''),
				'lastname' => (string)($customer_info['lastname'] ?? ''),
				'email' => (string)($customer_info['email'] ?? ''),
				'telephone' => (string)($customer_info['telephone'] ?? ''),
				'custom_field' => $custom_field,
			]);
		} catch (\Throwable $error) {
			// Do not block checkout if this optional cleanup is temporarily unavailable.
		}
	}
PHP;
$coupon_model = replaceOnce($coupon_model, $model_anchor, $model_methods . $model_anchor, $coupon_model_file);

$twig_old = <<<'TWIG'
        {# CHECKOUT-006: First15 is reserved for the next order. #}
        <section class="bs-success-meta bs-card" role="status">
          <strong>Дякуємо за реєстрацію!</strong>
          <p>Даруємо вам знижку 15% на наступне замовлення — промокод First15.</p>
        </section>
TWIG;
$twig_new = <<<'TWIG'
        {# CHECKOUT-007: First15 will be applied automatically on the next order. #}
        <section class="bs-success-meta bs-card" role="status">
          <strong>Дякуємо за реєстрацію!</strong>
          <p>На ваше наступне замовлення ми автоматично застосуємо знижку 15%.</p>
        </section>
TWIG;
$success_twig = replaceOnce($success_twig, $twig_old, $twig_new, $success_twig_file);

$backup_dir = '_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
backupAndWrite($payment_file, $payment, $backup_dir);
backupAndWrite($coupon_controller_file, $coupon_controller, $backup_dir);
backupAndWrite($coupon_model_file, $coupon_model, $backup_dir);
backupAndWrite($success_twig_file, $success_twig, $backup_dir);

foreach ([$payment_file, $coupon_controller_file, $coupon_model_file] as $file) {
	$lint_output = [];
	$lint_code = 0;
	exec('php -l ' . escapeshellarg($file) . ' 2>&1', $lint_output, $lint_code);
	if ($lint_code !== 0) {
		restore($backup_dir, $files);
		fail('php_lint_failed file=' . $file . ' output=' . implode(' ', $lint_output));
	}
	out('php_lint=ok file=' . $file);
}

out('backup=' . $backup_dir);
out('changed_files=4');
out('db_runtime=customer.custom_field.bs_first15_pending');
out('cache_clear=required');
out('done=ok');
@unlink(__FILE__);
