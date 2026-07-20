<?php
/**
 * RD-13.1D — checkout visual root fixes (CSS/JS/Twig only)
 *
 * No DB, checkout route, payment, order-creation or CAPTCHA-validation change.
 * Targets the current owner-supplied files from booster-rd13-visual-current.tar.gz.
 */
declare(strict_types=1);

$id = 'RD-13.1D';
$dryRun = in_array('--dry-run', $argv ?? [], true);
$root = getcwd() ?: '.';
$targets = [
    'css' => 'catalog/view/stylesheet/boostershop-ds.css',
    'reskin' => 'catalog/view/javascript/checkout-reskin.js',
    'checkout' => 'catalog/view/template/checkout/checkout.twig',
    'captcha' => 'extension/ps_google_recaptcha/catalog/view/template/captcha/ps_google_recaptcha.twig',
    'np_form' => 'extension/PintaNovaPoshtaCod/catalog/view/template/shipping/checkout_shipping_address_form.twig',
    'np_register' => 'extension/PintaNovaPoshtaCod/catalog/view/template/shipping/checkout_shipping_address_form_register.twig',
];

function rd131d_log(string $message): void { global $id; echo '[' . $id . '] ' . $message . PHP_EOL; }
function rd131d_fail(string $message): void { rd131d_log('ERROR: ' . $message); exit(1); }
function rd131d_read(string $path): string {
    if (!is_file($path) || !is_readable($path)) { rd131d_fail('target missing or unreadable: ' . $path); }
    $data = file_get_contents($path);
    if ($data === false) { rd131d_fail('cannot read: ' . $path); }
    return str_replace("\r\n", "\n", $data);
}
function rd131d_replace_once(string $source, string $anchor, string $replacement, string $label): string {
    $count = substr_count($source, $anchor);
    if ($count !== 1) { rd131d_fail($label . ' anchor count is ' . $count . ', expected 1'); }
    return str_replace($anchor, $replacement, $source);
}
function rd131d_backup(string $root, string $backupRoot, string $relative): string {
    $source = $root . '/' . $relative;
    $destination = $backupRoot . '/' . $relative;
    $directory = dirname($destination);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) { rd131d_fail('cannot create backup directory: ' . $directory); }
    if (!copy($source, $destination)) { rd131d_fail('backup failed: ' . $relative); }
    return $destination;
}
function rd131d_write(string $path, string $data): void {
    if (file_put_contents($path, $data) === false) { rd131d_fail('write failed: ' . $path); }
}

rd131d_log('cwd=' . $root);
rd131d_log('time=' . date(DATE_ATOM));
exec(escapeshellarg(PHP_BINARY ?: 'php') . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $lintOutput, $lintCode);
rd131d_log('php -l patch: ' . implode(' ', $lintOutput));
if ($lintCode !== 0) { rd131d_fail('patch syntax check failed'); }

$paths = [];
$files = [];
foreach ($targets as $key => $relative) {
    $paths[$key] = $root . '/' . $relative;
    $files[$key] = rd131d_read($paths[$key]);
}

$marker = 'RD-13.1D visual root fixes';
$already = strpos($files['css'], $marker) !== false
    && strpos($files['reskin'], $marker) !== false
    && strpos($files['checkout'], $marker) !== false
    && strpos($files['captcha'], $marker) !== false
    && strpos($files['np_register'], 'bs-register-autosave-status') === false
    && strpos($files['np_form'], 'bs-np-autosave-status') === false;
if ($already) { rd131d_log('already_applied=yes'); exit(0); }
if (strpos($files['css'], $marker) !== false || strpos($files['reskin'], $marker) !== false || strpos($files['checkout'], $marker) !== false || strpos($files['captcha'], $marker) !== false) {
    rd131d_fail('partial marker state detected; restore the newest RD-13.1D backup before retrying');
}

/* Verify that the earlier root removal is present; do not resurrect it. */
if (strpos($files['np_form'], 'bs-np-autosave-status') !== false) { rd131d_fail('NP form still contains bs-np-autosave-status; current source differs from supplied state'); }

$files['css'] = rd131d_replace_once(
    $files['css'],
    <<<'CSS'
#checkout-checkout.bs-co .bs-co-recipient-field--phone { order: 4; }
#checkout-checkout.bs-co .bs-co-recipient-field--email {
  order: 5;
  grid-column: 1 / 2;
}
CSS,
    <<<'CSS'
#checkout-checkout.bs-co .bs-co-recipient-field--phone { order: 4; }
#checkout-checkout.bs-co .bs-co-recipient-field--email { order: 5; }
CSS,
    'recipient base grid'
);

$files['css'] = rd131d_replace_once(
    $files['css'],
    <<<'CSS'
  #checkout-checkout.bs-co .bs-co-recipient-field--email {
    grid-column: auto;
  }
CSS,
    <<<'CSS'
  #checkout-checkout.bs-co .bs-co-recipient-field--phone,
  #checkout-checkout.bs-co .bs-co-recipient-field--email {
    grid-column: auto;
  }
CSS,
    'recipient mobile grid'
);

$files['css'] = rd131d_replace_once(
    $files['css'],
    <<<'CSS'
#checkout-checkout.bs-co .bs-co-recipient-field--phone { order: 4 !important; }
#checkout-checkout.bs-co .bs-co-recipient-field--email { order: 5 !important; grid-column: 1 / 2; }
CSS,
    <<<'CSS'
#checkout-checkout.bs-co .bs-co-recipient-field--phone { order: 4 !important; grid-column: 2 / 3; }
#checkout-checkout.bs-co .bs-co-recipient-field--email { order: 5 !important; grid-column: 1 / 2; }
CSS,
    'recipient canonical grid'
);

$files['css'] = rd131d_replace_once(
    $files['css'],
    <<<'CSS'
  #checkout-checkout.bs-co #bs-co-tail-bottom .table-responsive,
  #checkout-checkout.bs-co #bs-co-tail-bottom img,
  #checkout-checkout.bs-co #bs-co-tail-bottom iframe {
    max-width: 100%;
  }
CSS,
    <<<'CSS'
  #checkout-checkout.bs-co #bs-co-tail-bottom .table-responsive,
  #checkout-checkout.bs-co #bs-co-tail-bottom img {
    max-width: 100%;
  }
CSS,
    'CAPTCHA iframe scaling rule'
);

$files['css'] = rd131d_replace_once(
    $files['css'],
    <<<'CSS'
#checkout-checkout.bs-co .bs-co-moved-captcha .col-sm-10 { width: 100%; }
CSS,
    <<<'CSS'
/* RD-13.1D visual root fixes: the native CAPTCHA is not CSS-scaled. */
#checkout-checkout.bs-co .bs-co-moved-captcha > .row {
  --bs-gutter-x: 0;
  margin-left: 0;
  margin-right: 0;
}
#checkout-checkout.bs-co .bs-co-moved-captcha .col-sm-10 {
  width: 100%;
  padding-left: 0;
  padding-right: 0;
}
CSS,
    'CAPTCHA Bootstrap gutter rule'
);

$files['css'] = rd131d_replace_once(
    $files['css'],
    <<<'CSS'
/* 3 · Remove the stray empty "Перевірка безпеки" block under the newsletter
   toggle (the api.js script-only column). Keep the block that actually holds
   the reCAPTCHA widget (iframe / .g-recaptcha). */
#checkout-checkout.bs-co .bs-co-moved-captcha:not(:has(iframe)):not(:has(.g-recaptcha)) {
  display: none !important;
}

CSS,
    '',
    'stray CAPTCHA CSS hiding rule'
);

$files['css'] = rd131d_replace_once(
    $files['css'],
    <<<'CSS'
  /* 8 · the real reCAPTCHA widget is a fixed ~304px — scale it to fit the card */
  #checkout-checkout.bs-co .bs-co-moved-captcha { overflow: hidden; }
  #checkout-checkout.bs-co .bs-co-moved-captcha .g-recaptcha,
  #checkout-checkout.bs-co .bs-co-moved-captcha iframe[src*="recaptcha"] {
    transform: scale(0.9);
    transform-origin: left top;
  }
CSS,
    '',
    'mobile CAPTCHA transform rule'
);

$files['css'] = rd131d_replace_once(
    $files['css'],
    <<<'CSS'
/* Порожній статус Nova Poshta не займає місце */
#checkout-checkout.bs-co .bs-np-autosave-status:empty {
  display: none !important;
}

/* Приховуємо «Дані збережено», але залишаємо помилки автозбереження */
#checkout-checkout.bs-co .bs-register-autosave-status:not(.bs-is-error) {
  display: none !important;
}
CSS,
    '',
    'temporary autosave CSS hiding rules'
);

$reskinAnchor = <<<'JS'
  var registerForm = document.getElementById('form-register');

if (registerForm) {
  // Переміщуємо лише штатний fieldset реального Google reCAPTCHA.
  var captchaWidget = document.querySelector('#form-register [id^="g-recaptcha-"]') ||
    document.querySelector('[id^="g-recaptcha-"]');

  var captchaBlock = captchaWidget ? captchaWidget.closest('fieldset') : null;

  if (captchaBlock) {
    captchaBlock.classList.add('bs-co-moved-captcha');
    bindControlsToRegister(captchaBlock);
    pieces.push(captchaBlock);
  }

JS;
$reskinInsert = <<<'JS'
    var registerForm = document.getElementById('form-register');

    if (registerForm) {
      // RD-13.1D visual root fixes: move only the native widget's own fieldset.
      // The former iframe/.col fallback could move an unrelated empty container.
      var captchaWidget = document.querySelector('#form-register [id^="g-recaptcha-"]') ||
        document.querySelector('[id^="g-recaptcha-"]');
      var captchaBlock = captchaWidget ? captchaWidget.closest('fieldset') : null;

      if (captchaBlock) {
        captchaBlock.classList.add('bs-co-moved-captcha');
        bindControlsToRegister(captchaBlock);
        pieces.push(captchaBlock);
      }

JS;
$files['reskin'] = rd131d_replace_once($files['reskin'], $reskinAnchor, $reskinInsert, 'native CAPTCHA move');

$files['captcha'] = rd131d_replace_once(
    $files['captcha'],
    <<<'TWIG'
				var onloadCallback{{ widget_counter }} = function () {
					recaptcha_widget{{ widget_counter }} = grecaptcha.render('g-recaptcha-{{ widget_counter }}', {
						'sitekey': '{{ site_key }}',
						'theme': '{{ badge_theme }}',
						'size': '{{ badge_size }}'
					});
				};
TWIG,
    <<<'TWIG'
				var onloadCallback{{ widget_counter }} = function () {
					// RD-13.1D visual root fixes: use Google's supported compact widget
					// only for the narrow mobile checkout; no CSS transform is involved.
					var recaptchaSize{{ widget_counter }} = document.getElementById('checkout-checkout') &&
						window.matchMedia('(max-width: 420px)').matches ? 'compact' : '{{ badge_size }}';
					recaptcha_widget{{ widget_counter }} = grecaptcha.render('g-recaptcha-{{ widget_counter }}', {
						'sitekey': '{{ site_key }}',
						'theme': '{{ badge_theme }}',
						'size': recaptchaSize{{ widget_counter }}
					});
				};
TWIG,
    'reCAPTCHA native size'
);

$files['np_register'] = rd131d_replace_once(
    $files['np_register'],
    "<div class=\"bs-register-autosave-status\" data-bs-register-status></div>\n<br>",
    '',
    'static register autosave status'
);

$files['checkout'] = rd131d_replace_once(
    $files['checkout'],
    "  function surfaceRegisterErrors(json) {\n",
    <<<'JS'
  // RD-13.1D visual root fixes: create a status line only for a real error.
  // Normal autosave has no visible block and no reserved vertical space.
  function bsCheckoutShowRegisterStatusError(message) {
    var status = $('[data-bs-register-status]');

    if (!status.length) {
      var form = $('#form-register');
      var anchor = form.find('.bs-np-register').first();
      var markup = '<div class="bs-register-autosave-status bs-is-error" data-bs-register-status></div>';

      if (anchor.length) {
        anchor.after(markup);
      } else {
        form.append(markup);
      }

      status = $('[data-bs-register-status]');
    }

    status.addClass('bs-is-error').text(message);
  }

  function surfaceRegisterErrors(json) {
JS,
    'register status helper'
);

$files['checkout'] = rd131d_replace_once(
    $files['checkout'],
    <<<'JS'
    $('[data-bs-register-status]')
      .addClass('bs-is-error')
      .text('Не вдалося зберегти: ' + messages.join(' '));
JS,
    "    bsCheckoutShowRegisterStatusError('Не вдалося зберегти: ' + messages.join(' '));\n",
    'register validation status'
);

$errorStatus = "          $('[data-bs-register-status]').addClass('bs-is-error').text('Не вдалося зберегти дані для замовлення.');\n";
if (substr_count($files['checkout'], $errorStatus) !== 2) { rd131d_fail('register transport-error anchor count is not 2'); }
$files['checkout'] = str_replace($errorStatus, "          bsCheckoutShowRegisterStatusError('Не вдалося зберегти дані для замовлення.');\n", $files['checkout']);

$savingStatus = "    $('[data-bs-register-status]').removeClass('bs-is-error').text('Зберігаємо дані для замовлення...');\n";
if (substr_count($files['checkout'], $savingStatus) !== 2) { rd131d_fail('register saving-status anchor count is not 2'); }
$files['checkout'] = str_replace($savingStatus, "    $('[data-bs-register-status]').remove();\n", $files['checkout']);

$files['checkout'] = rd131d_replace_once(
    $files['checkout'],
    <<<'JS'
    if (!$('[data-bs-register-status]').length) {
      form.find('.bs-np-register').after('<div class="bs-register-autosave-status" data-bs-register-status></div>');
    }

JS,
    '',
    'default register status creation'
);

$files['checkout'] = rd131d_replace_once(
    $files['checkout'],
    "      $('[data-bs-register-status]').removeClass('bs-is-error').text('Дані збережено.');\n",
    "      $('[data-bs-register-status]').remove();\n",
    'register success status'
);

$files['checkout'] = rd131d_replace_once(
    $files['checkout'],
    '<link rel="stylesheet" href="catalog/view/stylesheet/boostershop-ds.css?v=rd13r7-manual-20260711c">',
    '<link rel="stylesheet" href="catalog/view/stylesheet/boostershop-ds.css?v=rd13.1d-20260712">',
    'CSS cache-buster'
);
$files['checkout'] = rd131d_replace_once(
    $files['checkout'],
    '<script src="catalog/view/javascript/checkout-reskin.js?v=rd13.1b-20260712a"></script>',
    '<script src="catalog/view/javascript/checkout-reskin.js?v=rd13.1d-20260712"></script>',
    'reskin cache-buster'
);

$checks = [
    strpos($files['css'], $marker) !== false,
    strpos($files['css'], '#checkout-checkout.bs-co #bs-co-tail-bottom iframe {') === false,
    strpos($files['css'], 'transform: scale(0.9);') === false,
    strpos($files['css'], 'bs-co-moved-captcha:not(:has') === false,
    strpos($files['css'], 'bs-co-recipient-field--phone { order: 4 !important; grid-column: 2 / 3; }') !== false,
    strpos($files['reskin'], $marker) !== false,
    strpos($files['captcha'], $marker) !== false,
    strpos($files['checkout'], $marker) !== false,
    strpos($files['checkout'], 'Дані збережено.') === false,
    strpos($files['checkout'], "form.find('.bs-np-register').after('<div class=\"bs-register-autosave-status\"") === false,
    strpos($files['np_register'], 'bs-register-autosave-status') === false,
];
if (in_array(false, $checks, true)) { rd131d_fail('in-memory post-replace verification failed'); }

if ($dryRun) {
    rd131d_log('dry_run=ok');
    rd131d_log('would_change=' . implode(',', $targets));
    exit(0);
}

$backupRoot = $root . '/_patch_backups/RD-13.1D_checkout_visual_root_fixes_20260712_' . date('Ymd_His');
foreach ($targets as $key => $relative) { rd131d_log('backup=' . rd131d_backup($root, $backupRoot, $relative)); }
foreach ($targets as $key => $relative) { rd131d_write($paths[$key], $files[$key]); }

exec(escapeshellarg(PHP_BINARY ?: 'php') . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $postLintOutput, $postLintCode);
rd131d_log('php -l patch: ' . implode(' ', $postLintOutput));
if ($postLintCode !== 0) { rd131d_fail('post-write patch syntax check failed'); }

rd131d_log('changed=' . implode(',', $targets));
rd131d_log('done=ok');
rd131d_log('qa=guest desktop/mobile: CAPTCHA normal/compact; phone+email equal receiver-grid width; error-only autosave feedback');
if (@unlink(__FILE__)) { rd131d_log('self_delete=ok'); } else { rd131d_log('self_delete=skipped'); }
