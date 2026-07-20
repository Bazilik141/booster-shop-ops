<?php
/**
 * CHECKOUT-006 — make First15 a next-order offer for guest account creation.
 *
 * No database schema/data change. Rollback: restore files from
 * _patch_backups/CHECKOUT-006_first15-next-order-message_20260717-<timestamp>/.
 */
declare(strict_types=1);

const PATCH_ID = 'CHECKOUT-006_first15-next-order-message_20260717';

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
$success_file = 'catalog/controller/checkout/success.php';
$twig_file = 'catalog/view/template/checkout/success.twig';
$files = [$payment_file, $success_file, $twig_file];

out('cwd=' . getcwd());
out('time=' . date('c'));

foreach ($files as $file) {
	if (!is_file($file)) {
		fail('missing_file=' . $file);
	}
}

$payment = file_get_contents($payment_file);
$success = file_get_contents($success_file);
$twig = file_get_contents($twig_file);
if ($payment === false || $success === false || $twig === false) {
	fail('read_failed');
}

$markers = [
	$payment_file => 'CHECKOUT-006 next-order First15 offer flag.',
	$success_file => 'CHECKOUT-006: consume the courtesy message flag once.',
	$twig_file => 'CHECKOUT-006: First15 is reserved for the next order.',
];
// Check each target explicitly to keep the partial-install guard unambiguous.
$has_payment_marker = strpos($payment, $markers[$payment_file]) !== false;
$has_success_marker = strpos($success, $markers[$success_file]) !== false;
$has_twig_marker = strpos($twig, $markers[$twig_file]) !== false;
if ($has_payment_marker || $has_success_marker || $has_twig_marker) {
	if ($has_payment_marker && $has_success_marker && $has_twig_marker) {
		out('already_applied=yes');
		exit(0);
	}
	fail('partial_marker_detected');
}

if (strpos($payment, 'CHECKOUT-005 structured Nova Poshta handoff for guest account creation.') === false) {
	fail('checkout005_np_contract_missing');
}

$payment_old = <<<'PHP'
			// The account is now authenticated and no order exists yet. Applying the
			// pending welcome coupon here makes the subsequent confirm/Hutko request use
			// the discounted total without issuing a coupon-triggered order-write route.
			if (empty($this->session->data['coupon'])) {
				$this->session->data['welcome_coupon_pending'] = 'First15';
				$this->load->model('checkout/booster_coupon');
				$checkout005_coupon_result = $this->model_checkout_booster_coupon->applyPendingWelcomeCoupon($email);
				$json['first15_applied'] = !empty($checkout005_coupon_result['success']);
			}
PHP;
$payment_new = <<<'PHP'
			// CHECKOUT-006 next-order First15 offer flag.
			// Do not alter coupon state here: the current order must retain the total
			// shown in the confirm panel. The success page consumes this one-time flag.
			$this->session->data['checkout001_first15_offer_pending'] = 1;
PHP;
$payment = replaceOnce($payment, $payment_old, $payment_new, $payment_file);

$success_capture_old = <<<'PHP'
		$hutko_return_context = $this->getHutkoReturnContext();
		$candidate_order_ids = [];
PHP;
$success_capture_new = <<<'PHP'
		$hutko_return_context = $this->getHutkoReturnContext();

		// CHECKOUT-006: consume the courtesy message flag once.
		// It is intentionally not part of the Hutko reload-resilience contract.
		$show_first15_offer = !empty($this->session->data['checkout001_first15_offer_pending']);
		unset($this->session->data['checkout001_first15_offer_pending']);

		$candidate_order_ids = [];
PHP;
$success = replaceOnce($success, $success_capture_old, $success_capture_new, $success_file);

$success_order_data_old = <<<'PHP'
					'is_hutko'        => $is_hutko,
					'is_cod'          => $is_cod,
				];
PHP;
$success_order_data_new = <<<'PHP'
					'is_hutko'        => $is_hutko,
					'is_cod'          => $is_cod,
					'show_first15_offer' => $show_first15_offer,
				];
PHP;
$success = replaceOnce($success, $success_order_data_old, $success_order_data_new, $success_file);

$twig_old = <<<'TWIG'
      </section>

      {% if order_data.order_id is defined and order_data.order_id %}
TWIG;
$twig_new = <<<'TWIG'
      </section>

      {% if order_data.show_first15_offer|default(false) %}
        {# CHECKOUT-006: First15 is reserved for the next order. #}
        <section class="bs-success-meta bs-card" role="status">
          <strong>Дякуємо за реєстрацію!</strong>
          <p>Даруємо вам знижку 15% на наступне замовлення — промокод First15.</p>
        </section>
      {% endif %}

      {% if order_data.order_id is defined and order_data.order_id %}
TWIG;
$twig = replaceOnce($twig, $twig_old, $twig_new, $twig_file);

$backup_dir = '_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
backupAndWrite($payment_file, $payment, $backup_dir);
backupAndWrite($success_file, $success, $backup_dir);
backupAndWrite($twig_file, $twig, $backup_dir);

$lint_files = [$payment_file, $success_file];
foreach ($lint_files as $file) {
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
out('changed_files=3');
out('cache_clear=required');
out('done=ok');
@unlink(__FILE__);
