<?php
/**
 * CHECKOUT-007A — display the First15 next-order message across shared success flows.
 *
 * No database write or schema change. The controller only reads the existing
 * customer custom_field fallback added by CHECKOUT-007.
 * Rollback: restore catalog/controller/checkout/success.php from
 * _patch_backups/CHECKOUT-007A_first15-success-message-fallback_20260718-<timestamp>/.
 */
declare(strict_types=1);

const PATCH_ID = 'CHECKOUT-007A_first15-success-message-fallback_20260718';

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

$success_file = 'catalog/controller/checkout/success.php';
$customer_model_file = 'catalog/model/account/customer.php';
$success_twig_file = 'catalog/view/template/checkout/success.twig';

out('cwd=' . getcwd());
out('time=' . date('c'));

foreach ([$success_file, $customer_model_file, $success_twig_file] as $file) {
	if (!is_file($file)) {
		fail('missing_file=' . $file);
	}
}

$success = file_get_contents($success_file);
$customer_model = file_get_contents($customer_model_file);
$success_twig = file_get_contents($success_twig_file);
if ($success === false || $customer_model === false || $success_twig === false) {
	fail('read_failed');
}

$marker = 'CHECKOUT-007A: recover the shared success message from the durable customer flag.';
if (strpos($success, $marker) !== false) {
	out('already_applied=yes');
	exit(0);
}
if (strpos($success, 'CHECKOUT-006: consume the courtesy message flag once.') === false) {
	fail('checkout006_success_contract_missing');
}
if (strpos($success_twig, 'CHECKOUT-007: First15 will be applied automatically on the next order.') === false) {
	fail('checkout007_success_copy_missing');
}
if (strpos($customer_model, 'public function getCustomer(int $customer_id): array') === false) {
	fail('customer_model_contract_missing=getCustomer');
}

$capture_old = <<<'PHP'
		$show_first15_offer = !empty($this->session->data['checkout001_first15_offer_pending']);
		unset($this->session->data['checkout001_first15_offer_pending']);

		$candidate_order_ids = [];
PHP;
$capture_new = <<<'PHP'
		$show_first15_offer = !empty($this->session->data['checkout001_first15_offer_pending']);
		unset($this->session->data['checkout001_first15_offer_pending']);

		// CHECKOUT-007A: recover the shared success message from the durable customer flag.
		// The normal flag is one-time. Some non-Hutko success routes render after it
		// has already been consumed, so only the just-created shortcut account may
		// use this read-only fallback.
		$checkout007_shortcut_created = (string)($this->session->data['checkout001_account_processed'] ?? '') === 'created';
		$checkout007_shortcut_customer_id = (int)($this->session->data['checkout001_account_customer_id'] ?? ($this->session->data['customer']['customer_id'] ?? 0));

		$candidate_order_ids = [];
PHP;
$success = replaceOnce($success, $capture_old, $capture_new, $success_file);

$order_data_old = <<<'PHP'
				$order_data = [
					'order_id'        => (int)$order_id,
					'shipping_method' => $get_method_name($order_info['shipping_method'] ?? ''),
					'payment_method'  => $payment_name,
					'payment_code'    => $payment_code,
					'is_hutko'        => $is_hutko,
					'is_cod'          => $is_cod,
					'show_first15_offer' => $show_first15_offer,
				];
PHP;
$order_data_new = <<<'PHP'
				if (
					!$show_first15_offer &&
					$checkout007_shortcut_created &&
					$checkout007_shortcut_customer_id > 0 &&
					(int)($order_info['customer_id'] ?? 0) === $checkout007_shortcut_customer_id
				) {
					$this->load->model('account/customer');
					$checkout007_customer = $this->model_account_customer->getCustomer($checkout007_shortcut_customer_id);
					$checkout007_custom_field = is_array($checkout007_customer['custom_field'] ?? null) ? $checkout007_customer['custom_field'] : [];
					$show_first15_offer = !empty($checkout007_custom_field['bs_first15_pending']);
				}

				$order_data = [
					'order_id'        => (int)$order_id,
					'shipping_method' => $get_method_name($order_info['shipping_method'] ?? ''),
					'payment_method'  => $payment_name,
					'payment_code'    => $payment_code,
					'is_hutko'        => $is_hutko,
					'is_cod'          => $is_cod,
					'show_first15_offer' => $show_first15_offer,
				];
PHP;
$success = replaceOnce($success, $order_data_old, $order_data_new, $success_file);

$backup_dir = '_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
backupAndWrite($success_file, $success, $backup_dir);

$lint_output = [];
$lint_code = 0;
exec('php -l ' . escapeshellarg($success_file) . ' 2>&1', $lint_output, $lint_code);
if ($lint_code !== 0) {
	@copy($backup_dir . DIRECTORY_SEPARATOR . $success_file, $success_file);
	fail('php_lint_failed output=' . implode(' ', $lint_output));
}

out('php_lint=ok file=' . $success_file);
out('backup=' . $backup_dir);
out('changed_files=1');
out('db_runtime=read_only customer.custom_field.bs_first15_pending');
out('cache_clear=required');
out('done=ok');
@unlink(__FILE__);
