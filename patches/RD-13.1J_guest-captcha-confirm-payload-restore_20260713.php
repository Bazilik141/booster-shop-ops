<?php
/**
 * RD-13.1J — restore the guest CAPTCHA payload for checkout confirm.
 *
 * Changes only catalog/view/template/checkout/checkout.twig.
 * No DB changes. No changes to confirm.php or payment code.
 *
 * Usage (from OpenCart public_html):
 *   php RD-13.1J_guest-captcha-confirm-payload-restore_20260713.php [--dry-run]
 */
declare(strict_types=1);

$id = 'RD-13.1J';
$patchName = 'RD-13.1J_guest-captcha-confirm-payload-restore_20260713';
$root = getcwd() ?: '.';
$dryRun = in_array('--dry-run', $argv, true);
$relative = 'catalog/view/template/checkout/checkout.twig';
$target = $root . '/' . $relative;
$marker = 'RD-13.1J guest CAPTCHA confirm payload restore';

function rd131j_log(string $message): void {
    global $id;
    echo '[' . $id . '] ' . $message . PHP_EOL;
}

function rd131j_fail(string $message): void {
    rd131j_log('error=' . $message);
    exit(1);
}

function rd131j_read(string $path): string {
    if (!is_file($path) || !is_readable($path)) {
        rd131j_fail('target missing or unreadable: ' . $path);
    }

    $data = file_get_contents($path);

    if ($data === false) {
        rd131j_fail('cannot read target: ' . $path);
    }

    return str_replace("\r\n", "\n", $data);
}

function rd131j_self_delete(): void {
    rd131j_log(@unlink(__FILE__) ? 'self_delete=ok' : 'self_delete=skipped');
}

if (!is_file($target)) {
    rd131j_fail('target not found: ' . $relative);
}

$lintOutput = [];
$lintCode = 0;
@exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $lintOutput, $lintCode);

if ($lintCode !== 0) {
    rd131j_fail('php_lint_failed: ' . implode(' | ', $lintOutput));
}

rd131j_log('php_lint=ok');

$twig = rd131j_read($target);

$legacyRoute = "$('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}'";
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
      // RD-13.1J guest CAPTCHA confirm payload restore. register.save remains
      // CAPTCHA-free because it is the background autosave path, not place order.
      var registerForm = $('#form-register');
      // The reskin can move the CAPTCHA fieldset outside the form. Include its
      // response explicitly, even when serializeArray() cannot see it.
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

      function resetGuestCaptchaForRetry() {
        if (!captchaField.length) {
          return;
        }

        captchaField.val('');

        // reCAPTCHA's supported reset returns an expired/rejected widget to a
        // solvable state without replacing the checkout page.
        if (window.grecaptcha && typeof window.grecaptcha.reset === 'function') {
          try {
            window.grecaptcha.reset();
          } catch (error) {
            // The field is already cleared; leave the visible widget usable.
          }
        }
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
            resetGuestCaptchaForRetry();
            checkout001Fail('Підтвердьте, що ви не робот, перед оформленням замовлення.');
          } else {
            checkout001Fail('Не вдалося створити замовлення. Оновіть сторінку і спробуйте ще раз.');
          }
        }
      });
JS;

$alreadyChecks = [
    'single marker' => substr_count($twig, $marker) === 1,
    'legacy GET loader absent' => strpos($twig, $legacyRoute) === false,
    'POST confirm route present' => strpos($twig, "type: 'post',\n        data: confirmPayload") !== false,
    'explicit CAPTCHA payload present' => strpos($twig, "g-recaptcha-response") !== false && strpos($twig, 'confirmPayload.push') !== false,
    'client CAPTCHA precheck present' => strpos($twig, "Підтвердьте, що ви не робот, перед оформленням замовлення.") !== false,
    '422 CAPTCHA response handler present' => strpos($twig, 'data-bs-captcha-error') !== false,
    'CAPTCHA retry reset present' => strpos($twig, 'window.grecaptcha.reset()') !== false,
];

if (strpos($twig, $marker) !== false) {
    foreach ($alreadyChecks as $label => $ok) {
        if (!$ok) {
            rd131j_fail('partial marker state: ' . $label . ' failed; restore the newest RD-13.1J backup before retrying');
        }
    }

    rd131j_log('already_applied=yes');
    rd131j_self_delete();
    exit(0);
}

$anchorCount = substr_count($twig, $twigAnchor);

if ($anchorCount !== 1) {
    rd131j_fail('checkout.twig confirm-loader anchor count is ' . $anchorCount . ', expected 1');
}

if (substr_count($twig, $legacyRoute) !== 1) {
    rd131j_fail('legacy confirm route count is not 1');
}

$newTwig = str_replace($twigAnchor, $twigInsert, $twig);

foreach ($alreadyChecks as $label => $ok) {
    if ($label === 'single marker') {
        $ok = substr_count($newTwig, $marker) === 1;
    } elseif ($label === 'legacy GET loader absent') {
        $ok = strpos($newTwig, $legacyRoute) === false;
    } elseif ($label === 'POST confirm route present') {
        $ok = strpos($newTwig, "type: 'post',\n        data: confirmPayload") !== false;
    } elseif ($label === 'explicit CAPTCHA payload present') {
        $ok = strpos($newTwig, 'g-recaptcha-response') !== false && strpos($newTwig, 'confirmPayload.push') !== false;
    } elseif ($label === 'client CAPTCHA precheck present') {
        $ok = strpos($newTwig, "Підтвердьте, що ви не робот, перед оформленням замовлення.") !== false;
    } elseif ($label === '422 CAPTCHA response handler present') {
        $ok = strpos($newTwig, 'data-bs-captcha-error') !== false;
    } elseif ($label === 'CAPTCHA retry reset present') {
        $ok = strpos($newTwig, 'window.grecaptcha.reset()') !== false;
    }

    if (!$ok) {
        rd131j_fail('in-memory post-replace check failed: ' . $label);
    }
}

if ($dryRun) {
    rd131j_log('target=' . $relative);
    rd131j_log('dry_run=ok');
    exit(0);
}

$backupRoot = $root . '/_patch_backups/' . $patchName . '_' . date('Ymd_His');
$backupTarget = $backupRoot . '/' . $relative;

if (!is_dir(dirname($backupTarget)) && !mkdir(dirname($backupTarget), 0775, true) && !is_dir(dirname($backupTarget))) {
    rd131j_fail('cannot create backup directory: ' . dirname($backupTarget));
}

if (!copy($target, $backupTarget)) {
    rd131j_fail('backup failed: ' . $backupTarget);
}

rd131j_log('backup=' . $backupTarget);

if (file_put_contents($target, $newTwig, LOCK_EX) === false) {
    @copy($backupTarget, $target);
    rd131j_fail('write failed; rollback=ok');
}

$writtenRaw = @file_get_contents($target);

if ($writtenRaw === false) {
    @copy($backupTarget, $target);
    rd131j_fail('post-write readback failed; rollback=ok');
}

$written = str_replace("\r\n", "\n", $writtenRaw);

foreach ($alreadyChecks as $label => $ok) {
    if ($label === 'single marker') {
        $ok = substr_count($written, $marker) === 1;
    } elseif ($label === 'legacy GET loader absent') {
        $ok = strpos($written, $legacyRoute) === false;
    } elseif ($label === 'POST confirm route present') {
        $ok = strpos($written, "type: 'post',\n        data: confirmPayload") !== false;
    } elseif ($label === 'explicit CAPTCHA payload present') {
        $ok = strpos($written, 'g-recaptcha-response') !== false && strpos($written, 'confirmPayload.push') !== false;
    } elseif ($label === 'client CAPTCHA precheck present') {
        $ok = strpos($written, "Підтвердьте, що ви не робот, перед оформленням замовлення.") !== false;
    } elseif ($label === '422 CAPTCHA response handler present') {
        $ok = strpos($written, 'data-bs-captcha-error') !== false;
    } elseif ($label === 'CAPTCHA retry reset present') {
        $ok = strpos($written, 'window.grecaptcha.reset()') !== false;
    }

    if (!$ok) {
        @copy($backupTarget, $target);
        rd131j_fail('post-write verification failed: ' . $label . '; rollback=ok');
    }
}

rd131j_log('changed=' . $relative);
rd131j_log('done=ok');
rd131j_self_delete();
