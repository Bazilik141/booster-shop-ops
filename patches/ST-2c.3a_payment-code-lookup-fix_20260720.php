<?php
/**
 * ST-2c.3a — payment code lookup fix.
 *
 * Fixes the post-ST-2c.3 regression where a payment option rendered from the
 * canonical filtered list was rejected by payment_method.save because the
 * legacy validator assumed array keys always equal explode('.', option.code).
 *
 * DB changes: none.
 * Rollback: restore catalog/controller/checkout/payment_method.php from the
 * generated _patch_backups/ST-2c.3a_payment-code-lookup-fix_20260720_<ts>/ tree.
 */

declare(strict_types=1);

$patch = basename(__FILE__, '.php');
$root = __DIR__;
$relative = 'catalog/controller/checkout/payment_method.php';
$target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
$expectedHash = 'D54EAFB41FEA111E3D39EB1BEB85612E671BB6FC854A6501C0517745677BC0CC';
$marker = 'ST-2c.3a: validate the rendered canonical option by its own code';

function st2c3a_fail(string $message): void {
    fwrite(STDERR, 'error=' . $message . PHP_EOL);
    exit(1);
}

function st2c3a_replace_once(string $source, string $old, string $new, string $label): string {
    $count = substr_count($source, $old);

    if ($count !== 1) {
        st2c3a_fail('anchor=' . $label . '; count=' . $count . '; expected=1');
    }

    return str_replace($old, $new, $source);
}

if (!is_file($target)) {
    st2c3a_fail('target_missing:' . $relative);
}

$source = file_get_contents($target);

if ($source === false) {
    st2c3a_fail('target_read_failed:' . $relative);
}

if (strpos($source, $marker) !== false) {
    if (
        strpos($source, 'private function boosterPaymentByCode') === false ||
        strpos($source, "explode('.', \$this->request->post['payment_method'])") !== false
    ) {
        st2c3a_fail('partial_apply_detected:' . $relative);
    }

    echo 'already_applied=yes' . PHP_EOL;
    exit(0);
}

$actualHash = strtoupper((string)hash_file('sha256', $target));

if ($actualHash !== $expectedHash) {
    st2c3a_fail('sha256_mismatch:' . $relative . '; actual=' . $actualHash);
}

$updated = st2c3a_replace_once(
    $source,
    <<<'OLD'
				if (!$this->boosterPaymentCodeExists($payment_methods, $current_code)) {
OLD
,
    <<<'NEW'
				if (!$this->boosterPaymentByCode($payment_methods, $current_code)) {
NEW
,
    'current_payment_lookup'
);

$updated = st2c3a_replace_once(
    $updated,
    <<<'OLD'
	/** @param array<string, mixed> $payment_methods */
	private function boosterPaymentCodeExists(array $payment_methods, string $code): bool {
		foreach ($payment_methods as $group) {
			foreach ((array)($group['option'] ?? []) as $option) {
				if ((string)($option['code'] ?? '') === $code) {
					return true;
				}
			}
		}

		return false;
	}
OLD
,
    <<<'NEW'
	/** @param array<string, mixed> $payment_methods */
	private function boosterPaymentByCode(array $payment_methods, string $code): array {
		foreach ($payment_methods as $group) {
			foreach ((array)($group['option'] ?? []) as $option) {
				if (is_array($option) && (string)($option['code'] ?? '') === $code) {
					return $option;
				}
			}
		}

		return [];
	}
NEW
,
    'canonical_payment_lookup_helper'
);

$updated = st2c3a_replace_once(
    $updated,
    <<<'OLD'
	public function save(): void {
		$this->load->language('checkout/payment_method');

		$json = [];

		// Validate cart has products and has stock.
OLD
,
    <<<'NEW'
	public function save(): void {
		$this->load->language('checkout/payment_method');

		$json = [];
		$selected_payment = [];

		// Validate cart has products and has stock.
NEW
,
    'selected_payment_initial_state'
);

$updated = st2c3a_replace_once(
    $updated,
    <<<'OLD'
			// Validate payment methods
			if (isset($this->request->post['payment_method']) && isset($this->session->data['payment_methods'])) {
				$payment = explode('.', $this->request->post['payment_method']);

				if (!isset($payment[0]) || !isset($payment[1]) || !isset($this->session->data['payment_methods'][$payment[0]]['option'][$payment[1]])) {
					$json['error'] = $this->language->get('error_payment_method');
				}
			} else {
				$json['error'] = $this->language->get('error_payment_method');
			}
OLD
,
    <<<'NEW'
			// Validate payment methods
			// ST-2c.3a: validate the rendered canonical option by its own code, not array key shape.
			if (isset($this->request->post['payment_method']) && isset($this->session->data['payment_methods'])) {
				$selected_payment = $this->boosterPaymentByCode(
					$this->session->data['payment_methods'],
					(string)$this->request->post['payment_method']
				);
			}

			if (!$selected_payment) {
				$json['error'] = $this->language->get('error_payment_method');
			}
NEW
,
    'payment_save_validation'
);

$updated = st2c3a_replace_once(
    $updated,
    <<<'OLD'
			$this->session->data['payment_method'] = $this->session->data['payment_methods'][$payment[0]]['option'][$payment[1]];
OLD
,
    <<<'NEW'
			$this->session->data['payment_method'] = $selected_payment;
NEW
,
    'payment_session_assignment'
);

foreach ([$marker, 'private function boosterPaymentByCode', '$this->session->data[\'payment_method\'] = $selected_payment;'] as $needle) {
    if (strpos($updated, $needle) === false) {
        st2c3a_fail('postcheck_missing:' . $needle);
    }
}

foreach (['boosterPaymentCodeExists', "explode('.', \$this->request->post['payment_method'])"] as $removed) {
    if (strpos($updated, $removed) !== false) {
        st2c3a_fail('postcheck_old_logic_present:' . $removed);
    }
}

$timestamp = gmdate('Ymd_His');
$backupDir = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . $patch . '_' . $timestamp;
$backup = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0775, true) && !is_dir(dirname($backup))) {
    st2c3a_fail('backup_directory_create_failed');
}

if (!copy($target, $backup)) {
    st2c3a_fail('backup_copy_failed:' . $relative);
}

if (file_put_contents($target, $updated) === false) {
    @copy($backup, $target);
    st2c3a_fail('target_write_failed; restored=yes');
}

$lintOutput = [];
$lintCode = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($target) . ' 2>&1', $lintOutput, $lintCode);

if ($lintCode !== 0) {
    @copy($backup, $target);
    st2c3a_fail('php_l_failed; restored=yes; detail=' . implode(' | ', $lintOutput));
}

echo 'cwd=' . $root . PHP_EOL;
echo 'time=' . gmdate('c') . PHP_EOL;
echo 'backup=' . $backupDir . PHP_EOL;
echo 'changed=' . $relative . PHP_EOL;
echo 'php_l=ok' . PHP_EOL;
echo 'done=ok' . PHP_EOL;

@unlink(__FILE__);
