<?php
/**
 * TECH-015 WP3c: emit the vendor-prepared mini-cart remove_from_cart payload.
 *
 * Scope: catalog/view/template/common/cart.twig
 * The event is emitted only after the remove request succeeds.
 * No database, cart controller, vendor extension, CSS, or payment writes.
 */
declare(strict_types=1);

const TECH015_WP3C_ID = 'TECH-015_ga4-mini-cart-remove_wp3c_20260907';

function tech015Wp3cFailUnless(bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function tech015Wp3cWrite(string $path, string $content): void {
	$directory = dirname($path);

	if (!is_dir($directory)) {
		tech015Wp3cFailUnless(mkdir($directory, 0755, true), 'Cannot create directory: ' . $directory);
	}

	$result = file_put_contents($path, $content, LOCK_EX);
	tech015Wp3cFailUnless($result === strlen($content), 'Write failed: ' . $path);
	tech015Wp3cFailUnless(hash_file('sha256', $path) === hash('sha256', $content), 'Written hash mismatch: ' . $path);
}

try {
	tech015Wp3cFailUnless(PHP_SAPI === 'cli', 'CLI only');
	tech015Wp3cFailUnless(PHP_VERSION_ID >= 80000, 'PHP 8.0+ required');

	$root = realpath(getcwd());
	tech015Wp3cFailUnless(is_string($root), 'Cannot resolve cwd');
	$root = str_replace('\\', '/', $root);
	tech015Wp3cFailUnless(is_file($root . '/config.php') && is_dir($root . '/catalog'), 'Run from OpenCart public_html');
	tech015Wp3cFailUnless(!is_dir($root . '/.git'), 'Refusing to run from repository source tree');

	$template_file = 'catalog/view/template/common/cart.twig';
	$vendor_controller_file = 'extension/ps_enhanced_measurement/catalog/controller/analytics/ps_enhanced_measurement.php';
	$template_path = $root . '/' . $template_file;
	$vendor_controller_path = $root . '/' . $vendor_controller_file;

	foreach ([$template_file => $template_path, $vendor_controller_file => $vendor_controller_path] as $relative => $path) {
		tech015Wp3cFailUnless(is_file($path), 'Required file missing: ' . $relative);
		tech015Wp3cFailUnless(!is_link($path), 'Symlink file refused: ' . $relative);
	}

	$original = file_get_contents($template_path);
	$vendor_controller = file_get_contents($vendor_controller_path);
	tech015Wp3cFailUnless(is_string($original), 'Read failed: ' . $template_file);
	tech015Wp3cFailUnless(is_string($vendor_controller), 'Read failed: ' . $vendor_controller_file);

	$marker = '// TECH-015-WP3C: emit only after the server accepted the removal.';
	$before_hash = '9b2a8c326604c64ddfe29b8e289712690474cfc2dea59f1de931947d78aa1fa1';
	$after_hash = '020b8f2a9dfee24d8c55b386415ef31731d16586a70191cbd8266b54dfd27fec';
	$current_hash = hash('sha256', $original);

	if (substr_count($original, $marker) === 1) {
		tech015Wp3cFailUnless($current_hash === $after_hash, 'Applied marker found but mini-cart Twig drifted');
		echo "already_applied=yes\n";
		echo 'self_delete=' . (@unlink(__FILE__) ? 'ok' : 'failed remove_uploaded_patch_manually=yes') . "\n";
		exit(0);
	}

	tech015Wp3cFailUnless(substr_count($original, $marker) === 0, 'Duplicate TECH-015 WP3c marker refused');
	tech015Wp3cFailUnless($current_hash === $before_hash, 'Source SHA-256 mismatch: ' . $template_file);
	tech015Wp3cFailUnless(substr_count($original, "off('click.miniCartRemove'") === 1, 'Mini-cart remove handler missing or ambiguous');
	tech015Wp3cFailUnless(substr_count($original, 'data-ps-track-event') === 0, 'Vendor click interceptor already present in mini-cart');
	tech015Wp3cFailUnless(substr_count($vendor_controller, "'remove_from_cart_' . \$cart_id") >= 1, 'Vendor mini-cart dataset key missing');
	tech015Wp3cFailUnless(substr_count($vendor_controller, "\$args['ps_merge_items']") >= 1, 'Vendor mini-cart dataset export missing');

	$anchor = <<<'TWIG'
    $.ajax({
        url: button.data('remove-url'),
        type: 'post',
        data: {
            key: button.data('key')
        },
        dataType: 'json',
        beforeSend: function() {
            button.prop('disabled', true).addClass('loading');
        },
        complete: function() {
            button.prop('disabled', false).removeClass('loading');
        },
        success: function(json) {
            console.log(json);

            if (json['redirect']) {
TWIG;
	$replacement = <<<'TWIG'
    $.ajax({
        url: button.data('remove-url'),
        type: 'post',
        data: {
            key: button.data('key')
        },
        dataType: 'json',
        beforeSend: function() {
            button.prop('disabled', true).addClass('loading');
        },
        complete: function() {
            button.prop('disabled', false).removeClass('loading');
        },
        success: function(json) {
            console.log(json);

            // TECH-015-WP3C: emit only after the server accepted the removal.
            var trackId = String(button.data('key') || '');
            var dataId = 'remove_from_cart_' + trackId;

            if (trackId && window.ps_dataLayer &&
                typeof window.ps_dataLayer.onClick === 'function' &&
                window.ps_dataLayer.ga4_data &&
                Object.prototype.hasOwnProperty.call(window.ps_dataLayer.ga4_data, dataId)) {
                window.ps_dataLayer.onClick('remove_from_cart', trackId);
            }

            if (json['redirect']) {
TWIG;

	tech015Wp3cFailUnless(substr_count($original, $anchor) === 1, 'Mini-cart remove success anchor count mismatch');
	$candidate = str_replace($anchor, $replacement, $original);
	$candidate_hash = hash('sha256', $candidate);
	tech015Wp3cFailUnless($candidate_hash === $after_hash, 'Generated mini-cart Twig hash mismatch: ' . $candidate_hash);
	tech015Wp3cFailUnless(substr_count($candidate, $marker) === 1, 'WP3c marker assertion failed');
	tech015Wp3cFailUnless(substr_count($candidate, "onClick('remove_from_cart', trackId)") === 1, 'remove_from_cart emitter count assertion failed');
	tech015Wp3cFailUnless(substr_count($candidate, 'data-ps-track-event') === 0, 'Forbidden vendor click-event attribute introduced');
	tech015Wp3cFailUnless(substr_count($candidate, 'preventDefault') === substr_count($original, 'preventDefault'), 'Click interception changed unexpectedly');

	$backup = $root . '/_patch_backups/' . TECH015_WP3C_ID . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
	tech015Wp3cFailUnless(!is_link($root . '/_patch_backups'), 'Backup root symlink refused');

	echo 'cwd=' . $root . "\n";
	echo 'time=' . date('c') . "\n";
	echo 'backup=' . $backup . "\n";

	tech015Wp3cWrite($backup . '/original/' . $template_file, $original);
	tech015Wp3cWrite($backup . '/generated/' . $template_file, $candidate);
	echo 'backup_file=' . $backup . '/original/' . $template_file . "\n";
	echo 'php_lint=not_applicable target=' . $template_file . "\n";

	try {
		tech015Wp3cFailUnless(hash_file('sha256', $template_path) === $before_hash, 'Source changed during apply: ' . $template_file);
		tech015Wp3cWrite($template_path, $candidate);
		echo 'changed_file=' . $template_file . "\n";
		echo 'after_sha256=' . hash_file('sha256', $template_path) . "\n";
	} catch (Throwable $write_error) {
		tech015Wp3cWrite($template_path, $original);
		throw new RuntimeException('Source restored after write failure: ' . $write_error->getMessage());
	}

	echo "database_touched=no\n";
	echo "done=ok\n";
	echo 'self_delete=' . (@unlink(__FILE__) ? 'ok' : 'failed remove_uploaded_patch_manually=yes') . "\n";
} catch (Throwable $error) {
	fwrite(STDERR, 'ERROR: ' . $error->getMessage() . "\n");
	exit(1);
}
