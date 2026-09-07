<?php
/**
 * TECH-015 WP2 hotfix: restore the approved checkout H1 and keep GA4 injection.
 *
 * Scope:
 * - catalog/view/template/checkout/checkout.twig
 *
 * Fixes the production regression where {{ heading_title }} rendered the
 * late-loaded cart heading "Мій кошик". No database, controller, language,
 * payment, order-status, or vendor-extension writes.
 */
declare(strict_types=1);

const TECH015_WP2_HOTFIX_ID = 'TECH-015_ga4-begin-checkout_wp2-hotfix_20260907';

function tech015Wp2HotfixFailUnless(bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function tech015Wp2HotfixWrite(string $path, string $content): void {
	$directory = dirname($path);

	if (!is_dir($directory)) {
		tech015Wp2HotfixFailUnless(mkdir($directory, 0755, true), 'Cannot create directory: ' . $directory);
	}

	$result = file_put_contents($path, $content, LOCK_EX);
	tech015Wp2HotfixFailUnless($result === strlen($content), 'Write failed: ' . $path);
	tech015Wp2HotfixFailUnless(hash_file('sha256', $path) === hash('sha256', $content), 'Written hash mismatch: ' . $path);
}

try {
	tech015Wp2HotfixFailUnless(PHP_SAPI === 'cli', 'CLI only');
	tech015Wp2HotfixFailUnless(PHP_VERSION_ID >= 80000, 'PHP 8.0+ required');

	$root = realpath(getcwd());
	tech015Wp2HotfixFailUnless(is_string($root), 'Cannot resolve cwd');
	$root = str_replace('\\', '/', $root);
	tech015Wp2HotfixFailUnless(is_file($root . '/config.php') && is_dir($root . '/catalog'), 'Run from OpenCart public_html');
	tech015Wp2HotfixFailUnless(!is_dir($root . '/.git'), 'Refusing to run from repository source tree');

	$template_file = 'catalog/view/template/checkout/checkout.twig';
	$language_file = 'extension/ukrainian/catalog/language/uk-ua/checkout/checkout.php';
	$vendor_controller_file = 'extension/ps_enhanced_measurement/catalog/controller/analytics/ps_enhanced_measurement.php';
	$template_path = $root . '/' . $template_file;
	$language_path = $root . '/' . $language_file;
	$vendor_controller_path = $root . '/' . $vendor_controller_file;

	foreach ([$template_file => $template_path, $language_file => $language_path, $vendor_controller_file => $vendor_controller_path] as $relative => $path) {
		tech015Wp2HotfixFailUnless(is_file($path), 'Required file missing: ' . $relative);
		tech015Wp2HotfixFailUnless(!is_link($path), 'Symlink file refused: ' . $relative);
	}

	$original = file_get_contents($template_path);
	$language = file_get_contents($language_path);
	$vendor_controller = file_get_contents($vendor_controller_path);
	tech015Wp2HotfixFailUnless(is_string($original), 'Read failed: ' . $template_file);
	tech015Wp2HotfixFailUnless(is_string($language), 'Read failed: ' . $language_file);
	tech015Wp2HotfixFailUnless(is_string($vendor_controller), 'Read failed: ' . $vendor_controller_file);

	$old_marker = '{# TECH-015-WP2: restore the vendor begin_checkout event anchor. #}';
	$new_marker = '{# TECH-015-WP2-HOTFIX: keep the checkout heading independent from late-loaded cart language. #}';
	$before_hash = 'fb7d6a8a44d9b4d8a7898bb3c98a8c2df686a000916b5fb2366640e02449499e';
	$after_hash = '675e6cb14f082c53211113f307feea97e4bb4857d639c748a4b5e1294809cafb';
	$current_hash = hash('sha256', $original);

	if (substr_count($original, $new_marker) === 1) {
		tech015Wp2HotfixFailUnless($current_hash === $after_hash, 'Applied hotfix marker found but checkout Twig drifted');
		echo "already_applied=yes\n";
		echo 'self_delete=' . (@unlink(__FILE__) ? 'ok' : 'failed remove_uploaded_patch_manually=yes') . "\n";
		exit(0);
	}

	tech015Wp2HotfixFailUnless(substr_count($original, $new_marker) === 0, 'Duplicate TECH-015 WP2 hotfix marker refused');
	tech015Wp2HotfixFailUnless($current_hash === $before_hash, 'Source SHA-256 mismatch: ' . $template_file);

	$anchor = <<<'TWIG'
      {# TECH-015-WP2: restore the vendor begin_checkout event anchor. #}
      <h1>{{ heading_title }}</h1>
TWIG;
	$replacement = <<<'TWIG'
      {# TECH-015-WP2-HOTFIX: keep the checkout heading independent from late-loaded cart language. #}
      <h1>Оформлення замовлення</h1>
      {% if ps_track_begin_checkout and ps_begin_checkout %}<script>ps_dataLayer.pushEventData('begin_checkout', {{ ps_begin_checkout }});</script>{% endif %}
      {% if ps_track_qualify_lead and ps_qualify_lead %}<script>ps_dataLayer.pushEventData('qualify_lead', {{ ps_qualify_lead }});</script>{% endif %}
TWIG;

	tech015Wp2HotfixFailUnless(substr_count($original, $anchor) === 1, 'WP2 anchor count mismatch: ' . $template_file);
	tech015Wp2HotfixFailUnless(
		substr_count($language, "\$_['heading_title'] = 'Оформлення замовлення';") === 1,
		'Approved Ukrainian heading value missing or ambiguous'
	);
	foreach (["\$args['ps_begin_checkout']", "\$args['ps_track_begin_checkout']", "\$args['ps_qualify_lead']", "\$args['ps_track_qualify_lead']"] as $vendor_anchor) {
		tech015Wp2HotfixFailUnless(substr_count($vendor_controller, $vendor_anchor) >= 1, 'Vendor payload anchor missing: ' . $vendor_anchor);
	}

	$candidate = str_replace($anchor, $replacement, $original);
	$candidate_hash = hash('sha256', $candidate);
	tech015Wp2HotfixFailUnless($candidate_hash === $after_hash, 'Generated checkout Twig hash mismatch: ' . $candidate_hash);
	tech015Wp2HotfixFailUnless(substr_count($candidate, $new_marker) === 1, 'Hotfix marker assertion failed');
	tech015Wp2HotfixFailUnless(substr_count($candidate, '<h1>Оформлення замовлення</h1>') === 1, 'Approved H1 assertion failed');
	tech015Wp2HotfixFailUnless(substr_count($candidate, '<h1>{{ heading_title }}</h1>') === 0, 'Dynamic H1 remained after hotfix');
	tech015Wp2HotfixFailUnless(substr_count($candidate, "pushEventData('begin_checkout'") === 1, 'begin_checkout snippet count assertion failed');
	tech015Wp2HotfixFailUnless(substr_count($candidate, "pushEventData('qualify_lead'") === 1, 'qualify_lead compatibility snippet count assertion failed');

	$backup = $root . '/_patch_backups/' . TECH015_WP2_HOTFIX_ID . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
	tech015Wp2HotfixFailUnless(!is_link($root . '/_patch_backups'), 'Backup root symlink refused');

	echo 'cwd=' . $root . "\n";
	echo 'time=' . date('c') . "\n";
	echo 'backup=' . $backup . "\n";

	tech015Wp2HotfixWrite($backup . '/original/' . $template_file, $original);
	tech015Wp2HotfixWrite($backup . '/generated/' . $template_file, $candidate);
	echo 'backup_file=' . $backup . '/original/' . $template_file . "\n";
	echo 'php_lint=not_applicable target=' . $template_file . "\n";

	try {
		tech015Wp2HotfixFailUnless(hash_file('sha256', $template_path) === $before_hash, 'Source changed during apply: ' . $template_file);
		tech015Wp2HotfixWrite($template_path, $candidate);
		echo 'changed_file=' . $template_file . "\n";
		echo 'after_sha256=' . hash_file('sha256', $template_path) . "\n";
	} catch (Throwable $write_error) {
		tech015Wp2HotfixWrite($template_path, $original);
		throw new RuntimeException('Source restored after write failure: ' . $write_error->getMessage());
	}

	echo "database_touched=no\n";
	echo "done=ok\n";
	echo 'self_delete=' . (@unlink(__FILE__) ? 'ok' : 'failed remove_uploaded_patch_manually=yes') . "\n";
} catch (Throwable $error) {
	fwrite(STDERR, 'ERROR: ' . $error->getMessage() . "\n");
	exit(1);
}
