<?php
// RD-13.1C_guest_captcha_final_gate_20260712.php
//
// Guest checkout CAPTCHA enforcement at the final trusted place-order click.
// It deliberately does NOT restore CAPTCHA validation to checkout/register.save:
// that route runs on address autosave before a guest can solve reCAPTCHA.
// No DB, customer-profile, address-book, payment, Hutko or fiscal changes.
// Rollback: restore the printed backups.

declare(strict_types=1);

$root = __DIR__;
$dryRun = in_array('--dry-run', $argv ?? [], true);
$stamp = date('Ymd_His');
$backupRoot = $root . '/_patch_backups/RD-13.1C_guest_captcha_final_gate_20260712_' . $stamp;
$targets = [
    'twig' => 'catalog/view/template/checkout/checkout.twig',
    'confirm' => 'catalog/controller/checkout/confirm.php',
];

function rd131c_log(string $message): void { echo '[RD-13.1C] ' . $message . PHP_EOL; }
function rd131c_fail(string $message): void { rd131c_log('error: ' . $message); exit(1); }
function rd131c_read(string $path): string {
    if (!is_file($path)) { rd131c_fail('missing file: ' . $path); }
    $content = file_get_contents($path);
    if ($content === false) { rd131c_fail('cannot read: ' . $path); }
    return $content;
}
function rd131c_write(string $path, string $content): void {
    if (file_put_contents($path, $content, LOCK_EX) === false) { rd131c_fail('cannot write: ' . $path); }
}
function rd131c_backup(string $root, string $backupRoot, string $relative): string {
    $target = $backupRoot . '/' . $relative;
    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true) && !is_dir(dirname($target))) {
        rd131c_fail('cannot create backup directory');
    }
    if (!copy($root . '/' . $relative, $target)) { rd131c_fail('cannot back up: ' . $relative); }
    return $target;
}
function rd131c_lint(string $path): void {
    $output = []; $code = 0;
    exec(escapeshellarg(PHP_BINARY ?: 'php') . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    rd131c_log('php -l ' . basename($path) . ': ' . implode(' ', $output));
    if ($code !== 0) { rd131c_fail('php -l failed: ' . $path); }
}

rd131c_log('cwd=' . $root);
rd131c_log('time=' . date(DATE_ATOM));
rd131c_lint(__FILE__);

$twigPath = $root . '/' . $targets['twig'];
$confirmPath = $root . '/' . $targets['confirm'];
$twig = rd131c_read($twigPath);
$confirm = rd131c_read($confirmPath);
$marker = 'RD-13.1C final guest CAPTCHA gate';

if (strpos($twig, $marker) !== false && strpos($confirm, $marker) !== false) {
    rd131c_log('already_applied=yes');
    exit(0);
}
if (strpos($twig, $marker) !== false || strpos($confirm, $marker) !== false) {
    rd131c_fail('partial marker state detected; restore the newest patch backup before retrying');
}

$twigAnchor = <<<'JS'
      $('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}', function(response, status) {

        $('#checkout-confirm').removeClass('bs-confirm-loading');

        if (status === 'error') {
          checkout001Fail('Не вдалося створити замовлення. Оновіть сторінку і спробуйте ще раз.');
          return;
        }

        hideModelColumns();

        var realButton = $('#checkout-confirm #button-confirm').first();

        if (!realButton.length) {
          checkout001Fail('Не знайдено кнопку підтвердження оплати після створення замовлення.');
          return;
        }

        realButton.addClass('loading').text('Завершуємо замовлення...');
        bsCheckoutUpdateSubmitLoader('Завершуємо оформлення...');

        window.setTimeout(function() {
          realButton.trigger('click');
          bsCheckoutConfirmSubmitting = false;
        }, 30);
      });
JS;

$twigInsert = <<<'JS'
      // RD-13.1C final guest CAPTCHA gate. register.save stays CAPTCHA-free
      // because it is the background autosave path, never the place-order action.
      var registerForm = $('#form-register');
      // The CAPTCHA textarea can sit outside the form but still use form="form-register".
      // Collect it explicitly so both the client check and POST match the server validation.
      var captchaField = $('[name="g-recaptcha-response"]').first();
      var captchaResponse = registerForm.length ? $.trim(captchaField.val() || '') : '';
      var confirmPayload = registerForm.length ? registerForm.serializeArray() : [];

      if (captchaField.length && !confirmPayload.some(function(field) { return field.name === 'g-recaptcha-response'; })) {
        confirmPayload.push({ name: 'g-recaptcha-response', value: captchaResponse });
      }

      if (registerForm.length && captchaField.length && !captchaResponse) {
        checkout001Fail('Підтвердьте, що ви не робот, перед оформленням замовлення.');
        return;
      }

      $.ajax({
        url: 'index.php?route=checkout/confirm.confirm&language={{ language }}',
        type: 'post',
        data: confirmPayload,
        success: function(response) {
          $('#checkout-confirm').removeClass('bs-confirm-loading').html(response);
          hideModelColumns();

          var realButton = $('#checkout-confirm #button-confirm').first();

          if (!realButton.length) {
            checkout001Fail('Підтвердьте CAPTCHA і спробуйте ще раз.');
            return;
          }

          realButton.addClass('loading').text('Завершуємо замовлення...');
          bsCheckoutUpdateSubmitLoader('Завершуємо оформлення...');

          window.setTimeout(function() {
            realButton.trigger('click');
            bsCheckoutConfirmSubmitting = false;
          }, 30);
        },
        error: function(xhr) {
          var response = xhr && xhr.responseText ? xhr.responseText : '';
          if (response.indexOf('data-bs-captcha-error') !== -1) {
            checkout001Fail('Підтвердьте, що ви не робот, перед оформленням замовлення.');
          } else {
            checkout001Fail('Не вдалося створити замовлення. Оновіть сторінку і спробуйте ще раз.');
          }
        }
      });
JS;

$confirmAnchor = <<<'PHP'
	public function confirm(): void {
		$this->response->setOutput($this->index());
	}
PHP;

$confirmInsert = <<<'PHP'
	// RD-13.1C final guest CAPTCHA gate. This route is reached only after the
	// trusted place-order click; register.save remains an autosave endpoint.
	public function confirm(): void {
		if (!$this->customer->isLogged()) {
			$this->load->model('setting/extension');

			$extension_info = $this->model_setting_extension->getExtensionByCode('captcha', $this->config->get('config_captcha'));

			if ($extension_info && $this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('register', (array)$this->config->get('config_captcha_page'))) {
				$captcha = $this->load->controller('extension/' . $extension_info['extension'] . '/captcha/' . $extension_info['code'] . '.validate');

				if ($captcha) {
					$this->response->addHeader('HTTP/1.1 422 Unprocessable Entity');
					$this->response->setOutput('<div class="alert alert-danger" data-bs-captcha-error="1">' . htmlspecialchars((string)$captcha, ENT_QUOTES, 'UTF-8') . '</div>');
					return;
				}
			}
		}

		$this->response->setOutput($this->index());
	}
PHP;

if (substr_count($twig, $twigAnchor) !== 1) { rd131c_fail('checkout.twig final-confirm anchor count is not 1'); }
if (substr_count($confirm, $confirmAnchor) !== 1) { rd131c_fail('confirm.php anchor count is not 1'); }

$newTwig = str_replace($twigAnchor, $twigInsert, $twig);
$newConfirm = str_replace($confirmAnchor, $confirmInsert, $confirm);
if (strpos($newTwig, $marker) === false || strpos($newConfirm, $marker) === false) { rd131c_fail('in-memory post-replace verification failed'); }

if ($dryRun) {
    rd131c_log('dry_run=ok');
    rd131c_log('would_change=' . implode(',', $targets));
    exit(0);
}

$twigBackup = rd131c_backup($root, $backupRoot, $targets['twig']);
$confirmBackup = rd131c_backup($root, $backupRoot, $targets['confirm']);
rd131c_log('backup=' . $twigBackup);
rd131c_log('backup=' . $confirmBackup);
rd131c_write($twigPath, $newTwig);
rd131c_write($confirmPath, $newConfirm);

$lintOutput = []; $lintCode = 0;
exec(escapeshellarg(PHP_BINARY ?: 'php') . ' -l ' . escapeshellarg($confirmPath) . ' 2>&1', $lintOutput, $lintCode);
rd131c_log('php -l confirm.php: ' . implode(' ', $lintOutput));
if ($lintCode !== 0 || strpos(rd131c_read($twigPath), $marker) === false || strpos(rd131c_read($confirmPath), $marker) === false) {
    @copy($twigBackup, $twigPath);
    @copy($confirmBackup, $confirmPath);
    rd131c_fail('post-write verification failed; rollback=ok');
}

rd131c_log('changed=' . implode(',', $targets));
rd131c_log('done=ok');
rd131c_log('smoke_test=guest without CAPTCHA: no confirm.confirm order creation; guest after solved CAPTCHA: one confirm.confirm and one payment click; authorized checkout unchanged');
if (@unlink(__FILE__)) { rd131c_log('self_delete=ok'); } else { rd131c_log('self_delete=skipped'); }
