<?php
/**
 * TECH-015 WP2: restore the checkout H1 anchor used by the existing GA4 module.
 *
 * Scope:
 * - catalog/view/template/checkout/checkout.twig
 *
 * PHP 8.0 compatible. No database, payment, order-status, controller, language,
 * or vendor-extension writes. Upload to ~/public_html and run there.
 * The uploaded runner self-deletes on success.
 */
declare(strict_types=1);

const TECH015_WP2_PATCH_ID = 'TECH-015_ga4-begin-checkout_wp2_20260907';

function tech015Wp2FailUnless(bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function tech015Wp2Write(string $path, string $content): void {
	$directory = dirname($path);

	if (!is_dir($directory)) {
		tech015Wp2FailUnless(mkdir($directory, 0755, true), 'Cannot create directory: ' . $directory);
	}

	$result = file_put_contents($path, $content, LOCK_EX);
	tech015Wp2FailUnless($result === strlen($content), 'Write failed: ' . $path);
	tech015Wp2FailUnless(hash_file('sha256', $path) === hash('sha256', $content), 'Written hash mismatch: ' . $path);
}

try {
	tech015Wp2FailUnless(PHP_SAPI === 'cli', 'CLI only');
	tech015Wp2FailUnless(PHP_VERSION_ID >= 80000, 'PHP 8.0+ required');

	$root = realpath(getcwd());
	tech015Wp2FailUnless(is_string($root), 'Cannot resolve cwd');
	$root = str_replace('\\', '/', $root);
	tech015Wp2FailUnless(is_file($root . '/config.php') && is_dir($root . '/catalog'), 'Run from OpenCart public_html');
	tech015Wp2FailUnless(!is_dir($root . '/.git'), 'Refusing to run from repository source tree');

	$template_file = 'catalog/view/template/checkout/checkout.twig';
	$language_file = 'extension/ukrainian/catalog/language/uk-ua/checkout/checkout.php';
	$vendor_file = 'extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php';
	$template_path = $root . '/' . $template_file;
	$language_path = $root . '/' . $language_file;
	$vendor_path = $root . '/' . $vendor_file;

	foreach ([$template_file => $template_path, $language_file => $language_path, $vendor_file => $vendor_path] as $relative => $path) {
		tech015Wp2FailUnless(is_file($path), 'Required file missing: ' . $relative);
		tech015Wp2FailUnless(!is_link($path), 'Symlink file refused: ' . $relative);
	}

	$original = file_get_contents($template_path);
	$language = file_get_contents($language_path);
	$vendor = file_get_contents($vendor_path);
	tech015Wp2FailUnless(is_string($original), 'Read failed: ' . $template_file);
	tech015Wp2FailUnless(is_string($language), 'Read failed: ' . $language_file);
	tech015Wp2FailUnless(is_string($vendor), 'Read failed: ' . $vendor_file);

	$marker = '{# TECH-015-WP2: restore the vendor begin_checkout event anchor. #}';
	$before_hash = 'd355ae2cfcd99bd1bba9c5d0f5825fc17de531a1b57fc8fb80343489fffb937f';
	$after_hash = 'fb7d6a8a44d9b4d8a7898bb3c98a8c2df686a000916b5fb2366640e02449499e';
	$current_hash = hash('sha256', $original);

	if (substr_count($original, $marker) === 1) {
		tech015Wp2FailUnless($current_hash === $after_hash, 'Applied marker found but checkout Twig drifted');
		echo "already_applied=yes\n";
		echo 'self_delete=' . (@unlink(__FILE__) ? 'ok' : 'failed remove_uploaded_patch_manually=yes') . "\n";
		exit(0);
	}

	tech015Wp2FailUnless(substr_count($original, $marker) === 0, 'Duplicate TECH-015 WP2 marker refused');
	tech015Wp2FailUnless($current_hash === $before_hash, 'Source SHA-256 mismatch: ' . $template_file);

	$anchor = '      <h1>Оформити замовлення</h1>';
	$replacement = "      " . $marker . "\n      <h1>{{ heading_title }}</h1>";
	tech015Wp2FailUnless(substr_count($original, $anchor) === 1, 'Anchor count mismatch: ' . $template_file);
	tech015Wp2FailUnless(substr_count($original, '<h1>{{ heading_title }}</h1>') === 0, 'Dynamic H1 already exists without TECH-015 WP2 marker');

	tech015Wp2FailUnless(
		substr_count($language, "\$_['heading_title'] = 'Оформлення замовлення';") === 1,
		'Approved Ukrainian heading value missing or ambiguous'
	);
	tech015Wp2FailUnless(
		substr_count($vendor, "'search' => '<h1>{{ heading_title }}</h1>'") === 1,
		'Vendor begin_checkout H1 search anchor missing or ambiguous'
	);
	tech015Wp2FailUnless(
		substr_count($vendor, "pushEventData(\\'begin_checkout\\'") === 1,
		'Vendor begin_checkout emitter missing or ambiguous'
	);

	$candidate = str_replace($anchor, $replacement, $original);
	tech015Wp2FailUnless(hash('sha256', $candidate) === $after_hash, 'Generated checkout Twig hash mismatch');
	tech015Wp2FailUnless(substr_count($candidate, $marker) === 1, 'Marker assertion failed');
	tech015Wp2FailUnless(substr_count($candidate, '<h1>{{ heading_title }}</h1>') === 1, 'Dynamic H1 assertion failed');
	tech015Wp2FailUnless(substr_count($candidate, '<h1>Оформити замовлення</h1>') === 0, 'Hardcoded H1 remained after generation');

	$backup = $root . '/_patch_backups/' . TECH015_WP2_PATCH_ID . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
	tech015Wp2FailUnless(!is_link($root . '/_patch_backups'), 'Backup root symlink refused');

	echo 'cwd=' . $root . "\n";
	echo 'time=' . date('c') . "\n";
	echo 'backup=' . $backup . "\n";

	tech015Wp2Write($backup . '/original/' . $template_file, $original);
	tech015Wp2Write($backup . '/generated/' . $template_file, $candidate);
	echo 'backup_file=' . $backup . '/original/' . $template_file . "\n";
	echo 'php_lint=not_applicable target=' . $template_file . "\n";

	try {
		tech015Wp2FailUnless(hash_file('sha256', $template_path) === $before_hash, 'Source changed during apply: ' . $template_file);
		tech015Wp2Write($template_path, $candidate);
		echo 'changed_file=' . $template_file . "\n";
		echo 'after_sha256=' . hash_file('sha256', $template_path) . "\n";
	} catch (Throwable $write_error) {
		tech015Wp2Write($template_path, $original);
		throw new RuntimeException('Source restored after write failure: ' . $write_error->getMessage());
	}

	echo "database_touched=no\n";
	echo "done=ok\n";
	echo 'self_delete=' . (@unlink(__FILE__) ? 'ok' : 'failed remove_uploaded_patch_manually=yes') . "\n";
} catch (Throwable $error) {
	fwrite(STDERR, 'ERROR: ' . $error->getMessage() . "\n");
	exit(1);
}
