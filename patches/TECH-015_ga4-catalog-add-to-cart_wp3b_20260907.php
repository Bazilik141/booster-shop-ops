<?php
/**
 * TECH-015 WP3b: emit catalogue tile ecommerce events on form submit.
 *
 * Scope: catalog/view/template/product/thumb.twig
 * Does not prevent, redispatch, delay, or alter the cart form submission.
 * No database, cart controller, vendor extension, CSS, or payment writes.
 */
declare(strict_types=1);

const TECH015_WP3B_ID = 'TECH-015_ga4-catalog-add-to-cart_wp3b_20260907';

function tech015Wp3bFailUnless(bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function tech015Wp3bWrite(string $path, string $content): void {
	$directory = dirname($path);

	if (!is_dir($directory)) {
		tech015Wp3bFailUnless(mkdir($directory, 0755, true), 'Cannot create directory: ' . $directory);
	}

	$result = file_put_contents($path, $content, LOCK_EX);
	tech015Wp3bFailUnless($result === strlen($content), 'Write failed: ' . $path);
	tech015Wp3bFailUnless(hash_file('sha256', $path) === hash('sha256', $content), 'Written hash mismatch: ' . $path);
}

try {
	tech015Wp3bFailUnless(PHP_SAPI === 'cli', 'CLI only');
	tech015Wp3bFailUnless(PHP_VERSION_ID >= 80000, 'PHP 8.0+ required');

	$root = realpath(getcwd());
	tech015Wp3bFailUnless(is_string($root), 'Cannot resolve cwd');
	$root = str_replace('\\', '/', $root);
	tech015Wp3bFailUnless(is_file($root . '/config.php') && is_dir($root . '/catalog'), 'Run from OpenCart public_html');
	tech015Wp3bFailUnless(!is_dir($root . '/.git'), 'Refusing to run from repository source tree');

	$template_file = 'catalog/view/template/product/thumb.twig';
	$vendor_controller_file = 'extension/ps_enhanced_measurement/catalog/controller/analytics/ps_enhanced_measurement.php';
	$common_js_file = 'catalog/view/javascript/common.js';
	$template_path = $root . '/' . $template_file;
	$vendor_controller_path = $root . '/' . $vendor_controller_file;
	$common_js_path = $root . '/' . $common_js_file;

	foreach ([$template_file => $template_path, $vendor_controller_file => $vendor_controller_path, $common_js_file => $common_js_path] as $relative => $path) {
		tech015Wp3bFailUnless(is_file($path), 'Required file missing: ' . $relative);
		tech015Wp3bFailUnless(!is_link($path), 'Symlink file refused: ' . $relative);
	}

	$original = file_get_contents($template_path);
	$vendor_controller = file_get_contents($vendor_controller_path);
	$common_js = file_get_contents($common_js_path);
	tech015Wp3bFailUnless(is_string($original), 'Read failed: ' . $template_file);
	tech015Wp3bFailUnless(is_string($vendor_controller), 'Read failed: ' . $vendor_controller_file);
	tech015Wp3bFailUnless(is_string($common_js), 'Read failed: ' . $common_js_file);

	$marker = '// TECH-015-WP3B: observe tile submits without preventing or redispatching the cart action.';
	$before_hash = '0375e269381f19e85573e3702d81dd118bf0b8b4e562ea60f9c9a30d3ccea807';
	$after_hash = 'ee483c07b40bdd58a2b23e75793b9850aef61ed4efd9df8bf0944f7b538f9c5a';
	$current_hash = hash('sha256', $original);

	if (substr_count($original, $marker) === 1) {
		tech015Wp3bFailUnless($current_hash === $after_hash, 'Applied marker found but product thumb Twig drifted');
		echo "already_applied=yes\n";
		echo 'self_delete=' . (@unlink(__FILE__) ? 'ok' : 'failed remove_uploaded_patch_manually=yes') . "\n";
		exit(0);
	}

	tech015Wp3bFailUnless(substr_count($original, $marker) === 0, 'Duplicate TECH-015 WP3b marker refused');
	tech015Wp3bFailUnless($current_hash === $before_hash, 'Source SHA-256 mismatch: ' . $template_file);
	tech015Wp3bFailUnless(substr_count($original, 'data-ps-track-event') === 0, 'Vendor click interceptor already present in product thumb');
	foreach (["\$args['ps_has_options']", "\$args['ps_track_add_to_cart']", "\$args['ps_track_select_item']", "\$args['ps_track_select_promotion']", "'add_to_cart_' . \$product_id"] as $vendor_anchor) {
		tech015Wp3bFailUnless(substr_count($vendor_controller, $vendor_anchor) >= 1, 'Vendor dataset contract missing: ' . $vendor_anchor);
	}
	tech015Wp3bFailUnless(substr_count($common_js, "\$(document).on('submit', 'form'") === 1, 'OpenCart delegated AJAX form handler missing or ambiguous');

	$form_anchor = <<<'TWIG'
        <form method="post"
              data-oc-toggle="ajax"
TWIG;
	$form_replacement = <<<'TWIG'
        <form method="post"
              {% if ps_has_options %}
                {% if ps_track_select_promotion and special %}
                  data-tech015-ga4-submit="select_promotion"
                {% elseif ps_track_select_item and not special %}
                  data-tech015-ga4-submit="select_item"
                {% endif %}
              {% elseif ps_track_add_to_cart %}
                data-tech015-ga4-submit="add_to_cart"
              {% endif %}
              data-oc-toggle="ajax"
TWIG;
	$end_anchor = <<<'TWIG'
  </div>
</article>
TWIG;
	$end_replacement = <<<'TWIG'
  </div>
</article>
<script>
// TECH-015-WP3B: observe tile submits without preventing or redispatching the cart action.
(function() {
  if (window.bsTech015CatalogGa4Bound || !window.jQuery) {
    return;
  }

  window.bsTech015CatalogGa4Bound = true;

  window.jQuery(document).on('submit.tech015CatalogGa4', '.bs-pcard__form[data-tech015-ga4-submit]', function() {
    var form = window.jQuery(this);
    var eventName = String(form.attr('data-tech015-ga4-submit') || '');
    var productId = String(form.find('input[name="product_id"]').val() || '');
    var dataId = eventName + '_' + productId;

    if (!eventName || !productId || !window.ps_dataLayer ||
        typeof window.ps_dataLayer.onClick !== 'function' ||
        !window.ps_dataLayer.ga4_data ||
        !Object.prototype.hasOwnProperty.call(window.ps_dataLayer.ga4_data, dataId)) {
      return;
    }

    window.ps_dataLayer.onClick(eventName, productId);
  });
})();
</script>
TWIG;

	tech015Wp3bFailUnless(substr_count($original, $form_anchor) === 1, 'Catalogue form anchor count mismatch');
	tech015Wp3bFailUnless(substr_count($original, $end_anchor) === 1, 'Product thumb end anchor count mismatch');
	$candidate = str_replace($form_anchor, $form_replacement, $original);
	$candidate = str_replace($end_anchor, $end_replacement, $candidate);
	$candidate_hash = hash('sha256', $candidate);
	tech015Wp3bFailUnless($candidate_hash === $after_hash, 'Generated product thumb Twig hash mismatch: ' . $candidate_hash);
	tech015Wp3bFailUnless(substr_count($candidate, $marker) === 1, 'WP3b marker assertion failed');
	tech015Wp3bFailUnless(substr_count($candidate, "on('submit.tech015CatalogGa4'") === 1, 'Delegated listener count assertion failed');
	tech015Wp3bFailUnless(substr_count($candidate, "onClick(eventName, productId)") === 1, 'Vendor lookup call count assertion failed');
	tech015Wp3bFailUnless(substr_count($candidate, 'data-ps-track-event') === 0, 'Forbidden vendor click-event attribute introduced');
	tech015Wp3bFailUnless(substr_count($candidate, 'data-ps-track-id') === 0, 'Forbidden vendor click-id attribute introduced');
	tech015Wp3bFailUnless(substr_count($candidate, 'preventDefault') === 0, 'Submit interception introduced');
	tech015Wp3bFailUnless(substr_count($candidate, 'setTimeout') === 0, 'Submit delay introduced');

	$backup = $root . '/_patch_backups/' . TECH015_WP3B_ID . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
	tech015Wp3bFailUnless(!is_link($root . '/_patch_backups'), 'Backup root symlink refused');

	echo 'cwd=' . $root . "\n";
	echo 'time=' . date('c') . "\n";
	echo 'backup=' . $backup . "\n";

	tech015Wp3bWrite($backup . '/original/' . $template_file, $original);
	tech015Wp3bWrite($backup . '/generated/' . $template_file, $candidate);
	echo 'backup_file=' . $backup . '/original/' . $template_file . "\n";
	echo 'php_lint=not_applicable target=' . $template_file . "\n";

	try {
		tech015Wp3bFailUnless(hash_file('sha256', $template_path) === $before_hash, 'Source changed during apply: ' . $template_file);
		tech015Wp3bWrite($template_path, $candidate);
		echo 'changed_file=' . $template_file . "\n";
		echo 'after_sha256=' . hash_file('sha256', $template_path) . "\n";
	} catch (Throwable $write_error) {
		tech015Wp3bWrite($template_path, $original);
		throw new RuntimeException('Source restored after write failure: ' . $write_error->getMessage());
	}

	echo "database_touched=no\n";
	echo "done=ok\n";
	echo 'self_delete=' . (@unlink(__FILE__) ? 'ok' : 'failed remove_uploaded_patch_manually=yes') . "\n";
} catch (Throwable $error) {
	fwrite(STDERR, 'ERROR: ' . $error->getMessage() . "\n");
	exit(1);
}
