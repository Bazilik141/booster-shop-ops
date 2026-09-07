<?php
/**
 * TECH-015 WP3a: emit the vendor-prepared add_payment_info payload.
 *
 * Scope: catalog/view/template/checkout/payment_method.twig
 * Shipping is intentionally not patched: its vendor anchor is present.
 * No database, payment provider, order, vendor extension, or shipping writes.
 */
declare(strict_types=1);

const TECH015_WP3A_ID = 'TECH-015_ga4-add-payment-info_wp3a_20260907';

function tech015Wp3aFailUnless(bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function tech015Wp3aWrite(string $path, string $content): void {
	$directory = dirname($path);

	if (!is_dir($directory)) {
		tech015Wp3aFailUnless(mkdir($directory, 0755, true), 'Cannot create directory: ' . $directory);
	}

	$result = file_put_contents($path, $content, LOCK_EX);
	tech015Wp3aFailUnless($result === strlen($content), 'Write failed: ' . $path);
	tech015Wp3aFailUnless(hash_file('sha256', $path) === hash('sha256', $content), 'Written hash mismatch: ' . $path);
}

try {
	tech015Wp3aFailUnless(PHP_SAPI === 'cli', 'CLI only');
	tech015Wp3aFailUnless(PHP_VERSION_ID >= 80000, 'PHP 8.0+ required');

	$root = realpath(getcwd());
	tech015Wp3aFailUnless(is_string($root), 'Cannot resolve cwd');
	$root = str_replace('\\', '/', $root);
	tech015Wp3aFailUnless(is_file($root . '/config.php') && is_dir($root . '/catalog'), 'Run from OpenCart public_html');
	tech015Wp3aFailUnless(!is_dir($root . '/.git'), 'Refusing to run from repository source tree');

	$template_file = 'catalog/view/template/checkout/payment_method.twig';
	$vendor_controller_file = 'extension/ps_enhanced_measurement/catalog/controller/analytics/ps_enhanced_measurement.php';
	$template_path = $root . '/' . $template_file;
	$vendor_controller_path = $root . '/' . $vendor_controller_file;

	foreach ([$template_file => $template_path, $vendor_controller_file => $vendor_controller_path] as $relative => $path) {
		tech015Wp3aFailUnless(is_file($path), 'Required file missing: ' . $relative);
		tech015Wp3aFailUnless(!is_link($path), 'Symlink file refused: ' . $relative);
	}

	$original = file_get_contents($template_path);
	$vendor_controller = file_get_contents($vendor_controller_path);
	tech015Wp3aFailUnless(is_string($original), 'Read failed: ' . $template_file);
	tech015Wp3aFailUnless(is_string($vendor_controller), 'Read failed: ' . $vendor_controller_file);

	$marker = '{# TECH-015-WP3A: emit the vendor-prepared payload without restoring its brittle success anchor. #}';
	$before_hash = 'efee1a60c2cecc7547787646690cc00fc01a428f9a2e9a64d9f08d464db6d396';
	$after_hash = '974d73f62b98b4a562db1478abcea040cd47b7270675e368173a83b3e43bf7c6';
	$current_hash = hash('sha256', $original);

	if (substr_count($original, $marker) === 1) {
		tech015Wp3aFailUnless($current_hash === $after_hash, 'Applied marker found but payment Twig drifted');
		echo "already_applied=yes\n";
		echo 'self_delete=' . (@unlink(__FILE__) ? 'ok' : 'failed remove_uploaded_patch_manually=yes') . "\n";
		exit(0);
	}

	tech015Wp3aFailUnless(substr_count($original, $marker) === 0, 'Duplicate TECH-015 WP3a marker refused');
	tech015Wp3aFailUnless($current_hash === $before_hash, 'Source SHA-256 mismatch: ' . $template_file);
	tech015Wp3aFailUnless(substr_count($original, "if (json['success']) {") === 0, 'Unexpected stock vendor success anchor present; re-audit before patching');
	tech015Wp3aFailUnless(substr_count($vendor_controller, "\$json_response['ps_add_payment_info']") === 1, 'Vendor response key missing or ambiguous');
	tech015Wp3aFailUnless(substr_count($vendor_controller, "\$args['ps_track_add_payment_info']") === 1, 'Vendor Twig toggle key missing or ambiguous');

	$anchor = <<<'TWIG'
        if (json && json.error) {
          $('#error-payment-method').addClass('d-block').text(json.error);
          status(json.error, true);
          refreshConfirmSummary();
          return;
        }

        $('#input-payment-method').val(label || code);
TWIG;
	$replacement = <<<'TWIG'
        if (json && json.error) {
          $('#error-payment-method').addClass('d-block').text(json.error);
          status(json.error, true);
          refreshConfirmSummary();
          return;
        }

        {# TECH-015-WP3A: emit the vendor-prepared payload without restoring its brittle success anchor. #}
        {% if ps_track_add_payment_info %}
        if (json['ps_add_payment_info'] && window.ps_dataLayer && typeof window.ps_dataLayer.pushEventData === 'function') {
          window.ps_dataLayer.pushEventData('add_payment_info', json['ps_add_payment_info']);
        }
        {% endif %}

        $('#input-payment-method').val(label || code);
TWIG;

	tech015Wp3aFailUnless(substr_count($original, $anchor) === 1, 'Payment success-handler anchor count mismatch');
	$candidate = str_replace($anchor, $replacement, $original);
	$candidate_hash = hash('sha256', $candidate);
	tech015Wp3aFailUnless($candidate_hash === $after_hash, 'Generated payment Twig hash mismatch: ' . $candidate_hash);
	tech015Wp3aFailUnless(substr_count($candidate, $marker) === 1, 'WP3a marker assertion failed');
	tech015Wp3aFailUnless(substr_count($candidate, "pushEventData('add_payment_info'") === 1, 'add_payment_info emitter count assertion failed');
	tech015Wp3aFailUnless(substr_count($candidate, "json['ps_add_payment_info']") === 2, 'Payment response-key guard assertion failed');

	$backup = $root . '/_patch_backups/' . TECH015_WP3A_ID . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
	tech015Wp3aFailUnless(!is_link($root . '/_patch_backups'), 'Backup root symlink refused');

	echo 'cwd=' . $root . "\n";
	echo 'time=' . date('c') . "\n";
	echo 'backup=' . $backup . "\n";

	tech015Wp3aWrite($backup . '/original/' . $template_file, $original);
	tech015Wp3aWrite($backup . '/generated/' . $template_file, $candidate);
	echo 'backup_file=' . $backup . '/original/' . $template_file . "\n";
	echo 'php_lint=not_applicable target=' . $template_file . "\n";

	try {
		tech015Wp3aFailUnless(hash_file('sha256', $template_path) === $before_hash, 'Source changed during apply: ' . $template_file);
		tech015Wp3aWrite($template_path, $candidate);
		echo 'changed_file=' . $template_file . "\n";
		echo 'after_sha256=' . hash_file('sha256', $template_path) . "\n";
	} catch (Throwable $write_error) {
		tech015Wp3aWrite($template_path, $original);
		throw new RuntimeException('Source restored after write failure: ' . $write_error->getMessage());
	}

	echo "database_touched=no\n";
	echo "done=ok\n";
	echo 'self_delete=' . (@unlink(__FILE__) ? 'ok' : 'failed remove_uploaded_patch_manually=yes') . "\n";
} catch (Throwable $error) {
	fwrite(STDERR, 'ERROR: ' . $error->getMessage() . "\n");
	exit(1);
}
