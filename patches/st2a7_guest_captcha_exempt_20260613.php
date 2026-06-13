<?php
declare(strict_types=1);

/**
 * ST-2a.7 - guest checkout captcha exemption.
 *
 * Scope: one file, one block in catalog/controller/checkout/register.php::save().
 * No DB changes. No SimpleCheckout/url.php/Hutko/Checkbox/account/register changes.
 *
 * Root cause: reCAPTCHA v2-checkbox is validated server-side on the stock
 * checkout/register.save autosave flow, but that flow submits an empty
 * g-recaptcha-response. The save branch never reaches shipping_address session
 * persistence, so guest shipping methods never load.
 */

$patch = 'st2a7_guest_captcha_exempt_20260613';
$root = getcwd() ?: __DIR__;
$backupDir = $root . '/_patch_backups/' . $patch . '-' . date('Ymd-His');
$file = 'catalog/controller/checkout/register.php';
$path = $root . '/' . $file;
$marker = 'ST-2a.7: captcha intentionally NOT validated on checkout/register';

function out(string $message): void {
    echo '[' . date('c') . '] ' . $message . PHP_EOL;
}

function fail(string $message): void {
    out('error=' . $message);
    out('done=failed');
    exit(1);
}

out('patch=' . $patch);
out('cwd=' . $root);
out('scope=checkout/register.save guest captcha exemption; one file; no DB');
out('target=' . $file);

if (!is_file($path)) {
    fail('missing ' . $file . '; upload patch to OpenCart public_html root');
}

$content = file_get_contents($path);
if ($content === false) {
    fail('cannot read ' . $file);
}

if (strpos($content, $marker) !== false) {
    out('already_applied=yes');
    out('changed=none');
    out('done=ok');
    @unlink(__FILE__);
    exit(0);
}

$old = <<<'PHP'
			// Captcha
			$this->load->model('setting/extension');

			if (!$this->customer->isLogged()) {
				$extension_info = $this->model_setting_extension->getExtensionByCode('captcha', $this->config->get('config_captcha'));

				if ($extension_info && $this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('register', (array)$this->config->get('config_captcha_page'))) {
					$captcha = $this->load->controller('extension/' . $extension_info['extension'] . '/captcha/' . $extension_info['code'] . '.validate');

					if ($captcha) {
						$json['error']['captcha'] = $captcha;
					}
				}
			}
PHP;

$new = <<<'PHP'
			// ST-2a.7: captcha intentionally NOT validated on checkout/register.
			// Reason: reCAPTCHA v2-checkbox cannot be solved in the autosave-on-blur
			// guest flow (empty g-recaptcha-response -> guaranteed fail -> silent block).
			// Standalone /account/register, login and forgotten_password keep captcha.
			// Rollback: restore the backup created by this patch.
			// --- original checkout/register captcha block disabled below ---
			// $this->load->model('setting/extension');
			//
			// if (!$this->customer->isLogged()) {
			// 	$extension_info = $this->model_setting_extension->getExtensionByCode('captcha', $this->config->get('config_captcha'));
			//
			// 	if ($extension_info && $this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('register', (array)$this->config->get('config_captcha_page'))) {
			// 		$captcha = $this->load->controller('extension/' . $extension_info['extension'] . '/captcha/' . $extension_info['code'] . '.validate');
			//
			// 		if ($captcha) {
			// 			$json['error']['captcha'] = $captcha;
			// 		}
			// 	}
			// }
PHP;

$count = substr_count($content, $old);
if ($count !== 1) {
    fail('pre-check failed: expected 1 checkout/register captcha block, got ' . $count);
}

$backupFile = $backupDir . '/' . $file;
if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0755, true) && !is_dir(dirname($backupFile))) {
    fail('cannot create backup dir');
}
if (!copy($path, $backupFile)) {
    fail('cannot backup ' . $file);
}
out('backup=' . str_replace($root . '/', '', $backupFile));

$patched = str_replace($old, $new, $content);
if (file_put_contents($path, $patched) === false) {
    fail('cannot write ' . $file);
}
out('changed=' . $file);

$lint = shell_exec(escapeshellarg(PHP_BINARY ?: 'php') . ' -l ' . escapeshellarg($path) . ' 2>&1');
if (!is_string($lint) || stripos($lint, 'No syntax errors detected') === false) {
    @copy($backupFile, $path);
    fail('php -l failed, restored backup: ' . trim((string)$lint));
}
out('php_lint_ok=' . $file);
out('rollback=restore ' . str_replace($root . '/', '', $backupFile));
out('done=ok');

@unlink(__FILE__);
out('self_delete=' . (file_exists(__FILE__) ? 'failed' : 'ok'));
