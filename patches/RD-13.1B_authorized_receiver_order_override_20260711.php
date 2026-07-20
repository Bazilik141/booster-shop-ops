<?php
// RD-13.1B_authorized_receiver_order_override_20260711.php
//
// Scope: authorized stock checkout receiver editor only.
// - adds an order-only receiver endpoint backed by checkout session data;
// - writes edited first name, last name and phone to the current order and its
//   shipping recipient at confirm time, without touching the account or address book;
// - preserves optional patronymic in the order and shipping custom-field JSON
//   because the stock oc_order schema has no dedicated middlename column;
// - clears the override only after checkout success.
//
// DB: no schema or direct DB changes. The existing checkout order write persists
// the already-supported order_data fields. Rollback: restore printed backups and
// remove catalog/controller/checkout/receiver.php.

declare(strict_types=1);

$root = __DIR__;
$dryRun = in_array('--dry-run', $argv ?? [], true);
$stamp = date('Ymd_His');
$backupRoot = $root . '/_patch_backups/RD-13.1B_authorized_receiver_order_override_20260711_' . $stamp;

$paths = [
    'js'       => 'catalog/view/javascript/checkout-reskin.js',
    'twig'     => 'catalog/view/template/checkout/checkout.twig',
    'checkout' => 'catalog/controller/checkout/checkout.php',
    'confirm'  => 'catalog/controller/checkout/confirm.php',
    'success'  => 'catalog/controller/checkout/success.php',
    'receiver' => 'catalog/controller/checkout/receiver.php',
];

function rd13b_log(string $message): void {
    echo '[RD-13.1B] ' . $message . PHP_EOL;
}

function rd13b_fail(string $message): void {
    rd13b_log('error: ' . $message);
    exit(1);
}

function rd13b_read(string $path): string {
    if (!is_file($path)) {
        rd13b_fail('missing file: ' . $path);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        rd13b_fail('cannot read: ' . $path);
    }

    return $content;
}

function rd13b_write(string $path, string $content): void {
    if (file_put_contents($path, $content, LOCK_EX) === false) {
        rd13b_fail('cannot write: ' . $path);
    }
}

function rd13b_backup(string $root, string $backupRoot, string $relative): string {
    $source = $root . '/' . $relative;
    $target = $backupRoot . '/' . $relative;
    $targetDir = dirname($target);

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        rd13b_fail('cannot create backup directory: ' . $targetDir);
    }

    if (!copy($source, $target)) {
        rd13b_fail('cannot back up: ' . $relative);
    }

    return $target;
}

function rd13b_count(string $content, string $needle): int {
    return substr_count($content, $needle);
}

function rd13b_lint(string $path): void {
    $output = [];
    $exitCode = 0;
    exec(escapeshellarg(PHP_BINARY ?: 'php') . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $exitCode);
    rd13b_log('php -l ' . basename($path) . ': ' . implode(' ', $output));

    if ($exitCode !== 0) {
        rd13b_fail('php -l failed: ' . $path);
    }
}

function rd13b_restore(array $backups, string $newControllerPath): void {
    foreach ($backups as $path => $backup) {
        if (is_file($backup)) {
            @copy($backup, $path);
        }
    }

    if (is_file($newControllerPath)) {
        @unlink($newControllerPath);
    }
}

rd13b_log('cwd=' . $root);
rd13b_log('time=' . date(DATE_ATOM));
rd13b_lint(__FILE__);

foreach (['js', 'twig', 'checkout', 'confirm', 'success'] as $key) {
    if (!is_file($root . '/' . $paths[$key])) {
        rd13b_fail('missing file: ' . $paths[$key]);
    }
}

$receiverPath = $root . '/' . $paths['receiver'];
$receiverDir = dirname($receiverPath);

if (!is_dir($receiverDir)) {
    rd13b_fail('missing controller directory: ' . $receiverDir);
}

$js = rd13b_read($root . '/' . $paths['js']);
$twig = rd13b_read($root . '/' . $paths['twig']);
$checkout = rd13b_read($root . '/' . $paths['checkout']);
$confirm = rd13b_read($root . '/' . $paths['confirm']);
$success = rd13b_read($root . '/' . $paths['success']);
$receiverExisting = is_file($receiverPath) ? rd13b_read($receiverPath) : '';

$marker = 'RD-13.1B order-only receiver override';
$states = [
    strpos($js, $marker) !== false,
    strpos($twig, 'data-bs-receiver-firstname=') !== false,
    strpos($checkout, $marker) !== false,
    strpos($confirm, $marker) !== false,
    strpos($success, "rd13_receiver_override") !== false,
    strpos($receiverExisting, $marker) !== false,
];

if (!in_array(false, $states, true)) {
    rd13b_log('already_applied=yes');
    exit(0);
}

if (in_array(true, $states, true)) {
    rd13b_fail('partial RD-13.1B marker state detected; restore the newest backup before retrying');
}

if ($receiverExisting !== '') {
    rd13b_fail('receiver controller already exists without the RD-13.1B marker');
}

$jsPattern = '#  function ensureReceiverRecap\(\) \{.*?\n  \}\n\n  function cacheReceiverName\(\) \{#s';
$jsMatches = preg_match_all($jsPattern, $js, $unusedMatches);

if ($jsMatches !== 1) {
    rd13b_fail('ensureReceiverRecap anchor count is not 1; checkout-reskin.js shape changed');
}

$newJs = <<<'JS'
  // RD-13.1B order-only receiver override: authorized checkout only.
  function ensureReceiverRecap() {
    if (document.getElementById('form-register')) {
      return;
    }

    var select = savedAddressSelect();

    if (!select || !select.options.length) {
      return;
    }

    var recap = document.getElementById('bs-co-receiver-recap');
    var deliveryCard = root.querySelector('[data-co-card="delivery"]');

    if (!recap && deliveryCard) {
      recap = document.createElement('section');
      recap.id = 'bs-co-receiver-recap';
      recap.className = 'bs-card bs-co-card';
      recap.setAttribute('data-co-card', 'receiver');
      recap.setAttribute('data-co-collapsible', '');
      recap.innerHTML =
        '<button type="button" class="bs-co-card__head" data-co-card-toggle aria-expanded="true">' +
          '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 8a7 7 0 0 0-14 0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>' +
          '<span class="bs-co-card__title">Отримувач</span>' +
          '<span class="bs-co-card__summary" data-co-receiver-summary></span>' +
          '<span class="bs-co-chevron" aria-hidden="true">⌄</span>' +
        '</button>' +
        '<div class="bs-co-card__body">' +
          '<div class="row row-cols-1 row-cols-md-2" data-co-receiver-edit>' +
            '<div class="col mb-3 required bs-co-recipient-field--first">' +
              '<label class="form-label" for="bs-co-recv-firstname">Ім\'я отримувача</label>' +
              '<input type="text" id="bs-co-recv-firstname" class="form-control" placeholder="Ім\'я" autocomplete="given-name" maxlength="32">' +
            '</div>' +
            '<div class="col mb-3 required bs-co-recipient-field--last">' +
              '<label class="form-label" for="bs-co-recv-lastname">Прізвище отримувача</label>' +
              '<input type="text" id="bs-co-recv-lastname" class="form-control" placeholder="Прізвище" autocomplete="family-name" maxlength="32">' +
            '</div>' +
            '<div class="col mb-3 bs-co-recipient-field--middle">' +
              '<label class="form-label" for="bs-co-recv-middlename">По батькові</label>' +
              '<input type="text" id="bs-co-recv-middlename" class="form-control" placeholder="Необов\'язково" autocomplete="additional-name" maxlength="32">' +
            '</div>' +
            '<div class="col mb-3 required bs-co-recipient-field--phone">' +
              '<label class="form-label" for="bs-co-recv-telephone">Телефон</label>' +
              '<input type="tel" id="bs-co-recv-telephone" class="form-control" placeholder="Телефон" autocomplete="tel" inputmode="tel" maxlength="32">' +
            '</div>' +
          '</div>' +
          '<div class="bs-co-recap-sub" data-co-receiver-address></div>' +
          '<div class="small mt-1" data-co-receiver-status aria-live="polite"></div>' +
        '</div>';
      deliveryCard.parentNode.insertBefore(recap, deliveryCard);
    }

    if (!recap) {
      return;
    }

    var first = recap.querySelector('#bs-co-recv-firstname');
    var last = recap.querySelector('#bs-co-recv-lastname');
    var middle = recap.querySelector('#bs-co-recv-middlename');
    var phone = recap.querySelector('#bs-co-recv-telephone');

    if (recap.dataset.coRecvBuilt !== '1' && first && last && phone) {
      recap.dataset.coRecvBuilt = '1';

      var source = select.dataset.coNameCache || '';
      if (!source) {
        source = text((select.options[select.selectedIndex] || select.options[0]).textContent).split(',')[0];
      }
      var nameParts = text(source).split(' ').filter(Boolean);
      var savedFirst = text(root.getAttribute('data-bs-receiver-firstname'));
      var savedLast = text(root.getAttribute('data-bs-receiver-lastname'));
      var savedMiddle = text(root.getAttribute('data-bs-receiver-middlename'));
      var savedPhone = text(root.getAttribute('data-bs-receiver-telephone'));

      first.value = savedFirst || nameParts.shift() || '';
      last.value = savedLast || nameParts.join(' ');
      if (middle) {
        middle.value = savedMiddle;
      }
      phone.value = savedPhone || text(root.getAttribute('data-bs-customer-phone'));

      var pushEdit = function () {
        var f = text(first.value);
        var l = text(last.value);
        var m = text(middle ? middle.value : '');
        var p = text(phone.value);
        var errors = {};

        select.dataset.coNameCache = [f, m, l].filter(Boolean).join(' ');
        root.setAttribute('data-bs-customer-phone', p);
        root.setAttribute('data-bs-receiver-firstname', f);
        root.setAttribute('data-bs-receiver-lastname', l);
        root.setAttribute('data-bs-receiver-middlename', m);
        root.setAttribute('data-bs-receiver-telephone', p);
        updateCardSummaries();

        if (!f) {
          errors.firstname = 'Вкажіть ім\'я отримувача.';
        }
        if (!l) {
          errors.lastname = 'Вкажіть прізвище отримувача.';
        }
        if (!p) {
          errors.telephone = 'Вкажіть телефон отримувача.';
        }
        if (Object.keys(errors).length) {
          surfaceReceiverErrors(recap, errors);
          return;
        }

        window.bsCheckoutSaveReceiver({ firstname: f, lastname: l, middlename: m, telephone: p });
      };

      var debounce = null;
      [first, last, middle, phone].forEach(function (input) {
        if (!input) {
          return;
        }
        input.addEventListener('input', function () {
          window.clearTimeout(debounce);
          debounce = window.setTimeout(pushEdit, 400);
        });
        input.addEventListener('blur', pushEdit);
      });
    }

    var addressLine = recap.querySelector('[data-co-receiver-address]');
    var selectNow = savedAddressSelect();
    var fullOption = selectNow && selectNow.selectedOptions[0] ? selectNow.selectedOptions[0].dataset.coFullText : '';
    setText(addressLine, fullOption || selectedSavedAddressText());
  }

  function surfaceReceiverErrors(recap, errors) {
    var fields = {
      firstname: '#bs-co-recv-firstname',
      lastname: '#bs-co-recv-lastname',
      middlename: '#bs-co-recv-middlename',
      telephone: '#bs-co-recv-telephone'
    };
    var status = recap.querySelector('[data-co-receiver-status]');

    Object.keys(fields).forEach(function (key) {
      var input = recap.querySelector(fields[key]);
      if (!input) {
        return;
      }
      input.classList.remove('is-invalid');
      var feedback = input.parentNode.querySelector('[data-co-receiver-error="' + key + '"]');
      if (feedback) {
        feedback.remove();
      }
    });

    Object.keys(errors || {}).forEach(function (key) {
      var selector = fields[key];
      var input = selector ? recap.querySelector(selector) : null;
      if (!input || !errors[key]) {
        return;
      }
      input.classList.add('is-invalid');
      var feedback = document.createElement('div');
      feedback.className = 'invalid-feedback d-block';
      feedback.setAttribute('data-co-receiver-error', key);
      feedback.textContent = errors[key];
      input.parentNode.appendChild(feedback);
    });

    if (status) {
      status.textContent = Object.keys(errors || {}).length ? 'Перевірте дані отримувача.' : '';
    }
  }

  var receiverSaveRevision = Date.now();
  var receiverLastSavedSignature = '';
  var receiverSaveXhr = null;

  window.bsCheckoutSaveReceiver = function (data) {
    var recap = document.getElementById('bs-co-receiver-recap');
    var payload = {
      firstname: text(data && data.firstname),
      lastname: text(data && data.lastname),
      middlename: text(data && data.middlename),
      telephone: text(data && data.telephone)
    };
    var signature = JSON.stringify(payload);

    if (!recap || signature === receiverLastSavedSignature) {
      return;
    }

    receiverSaveRevision += 1;
    var revision = receiverSaveRevision;
    var status = recap.querySelector('[data-co-receiver-status]');

    if (receiverSaveXhr && receiverSaveXhr.readyState !== 4) {
      receiverSaveXhr.abort();
    }

    if (status) {
      status.textContent = 'Зберігаємо дані отримувача...';
    }

    receiverSaveXhr = $.ajax({
      url: 'index.php?route=checkout/receiver.save',
      type: 'post',
      dataType: 'json',
      data: $.extend({}, payload, { revision: revision }),
      success: function (json) {
        if (revision !== receiverSaveRevision) {
          return;
        }
        if (json && json.error) {
          surfaceReceiverErrors(recap, json.error);
          return;
        }
        receiverLastSavedSignature = signature;
        surfaceReceiverErrors(recap, {});
        if (status) {
          status.textContent = 'Дані отримувача збережено для цього замовлення.';
        }
      },
      error: function (xhr, textStatus) {
        if (revision !== receiverSaveRevision || textStatus === 'abort') {
          return;
        }
        surfaceReceiverErrors(recap, { warning: 'Не вдалося зберегти дані отримувача. Спробуйте ще раз.' });
      }
    });
  };

  function cacheReceiverName() {
JS;

$checkoutAnchor = <<<'PHP'
		// RD-13 r6: read-only customer phone for the receiver recap (presentation).
		$data['customer_telephone'] = $this->customer->isLogged() ? (string)$this->customer->getTelephone() : '';
PHP;

$checkoutInsert = <<<'PHP'
		// RD-13.1B order-only receiver override: render session values after reload
		// without changing the customer profile or the saved address book.
		$receiver_override = $this->session->data['rd13_receiver_override'] ?? [];
		$receiver_override = is_array($receiver_override) ? $receiver_override : [];
		$data['customer_telephone'] = $this->customer->isLogged() ? (string)$this->customer->getTelephone() : '';
		$data['receiver_override_firstname'] = (string)($receiver_override['firstname'] ?? '');
		$data['receiver_override_lastname'] = (string)($receiver_override['lastname'] ?? '');
		$data['receiver_override_middlename'] = (string)($receiver_override['middlename'] ?? '');
		$data['receiver_override_telephone'] = (string)($receiver_override['telephone'] ?? '');
PHP;

$twigAnchor = '<div id="checkout-checkout" class="container bs-co" data-rd13-checkout data-bs-customer-phone="{{ customer_telephone }}">';
$twigInsert = '<div id="checkout-checkout" class="container bs-co" data-rd13-checkout data-bs-customer-phone="{{ customer_telephone }}" data-bs-receiver-firstname="{{ receiver_override_firstname }}" data-bs-receiver-lastname="{{ receiver_override_lastname }}" data-bs-receiver-middlename="{{ receiver_override_middlename }}" data-bs-receiver-telephone="{{ receiver_override_telephone }}">';
$jsHrefOld = 'checkout-reskin.js?v=rd13r6-20260708';
$jsHrefNew = 'checkout-reskin.js?v=rd13.1b-20260711';

$confirmCoreAnchor = <<<'PHP'
			$order_data['custom_field'] = $this->session->data['customer']['custom_field'];

			// Payment Details
PHP;

$confirmCoreInsert = <<<'PHP'
			$order_data['custom_field'] = $this->session->data['customer']['custom_field'];

			// RD-13.1B order-only receiver override. This intentionally does not
			// mutate session customer data, the customer profile, or saved addresses.
			$receiver_override = $this->session->data['rd13_receiver_override'] ?? [];
			$receiver_override_active = is_array($receiver_override)
				&& (int)($receiver_override['customer_id'] ?? 0) === (int)$this->session->data['customer']['customer_id']
				&& oc_validate_length((string)($receiver_override['firstname'] ?? ''), 1, 32)
				&& oc_validate_length((string)($receiver_override['lastname'] ?? ''), 1, 32)
				&& oc_validate_length((string)($receiver_override['telephone'] ?? ''), 3, 32);

			if ($receiver_override_active) {
				$order_data['firstname'] = (string)$receiver_override['firstname'];
				$order_data['lastname'] = (string)$receiver_override['lastname'];
				$order_data['telephone'] = (string)$receiver_override['telephone'];

				if (!is_array($order_data['custom_field'])) {
					$order_data['custom_field'] = [];
				}

				$order_data['custom_field']['rd13_receiver_middlename'] = (string)($receiver_override['middlename'] ?? '');
			}

			// Payment Details
PHP;

$confirmShippingAnchor = "\t\t\tif (isset(\$this->session->data['comment'])) {";
$confirmShippingInsert = <<<'PHP'
			// RD-13.1B: make the order's delivery recipient match the editable
			// receiver card while keeping the stored address itself untouched.
			if ($receiver_override_active && $this->cart->hasShipping()) {
				$order_data['shipping_firstname'] = (string)$receiver_override['firstname'];
				$order_data['shipping_lastname'] = (string)$receiver_override['lastname'];

				if (!is_array($order_data['shipping_custom_field'])) {
					$order_data['shipping_custom_field'] = [];
				}

				$order_data['shipping_custom_field']['rd13_receiver_middlename'] = (string)($receiver_override['middlename'] ?? '');
			}

			if (isset($this->session->data['comment'])) {
PHP;

$successAnchor = "\t\t\tunset(\$this->session->data['agree']);";
$successInsert = <<<'PHP'
			unset($this->session->data['agree']);
			// RD-13.1B: the receiver override belongs to one checkout/order only.
			unset($this->session->data['rd13_receiver_override']);
PHP;

$receiverController = <<<'PHP'
<?php
namespace Opencart\Catalog\Controller\Checkout;

/**
 * RD-13.1B order-only receiver override endpoint.
 *
 * The data lives in checkout session state and is consumed by confirm.php.
 * It never calls account edit or address save, so account and address-book data
 * cannot be changed through this route.
 */
class Receiver extends \Opencart\System\Engine\Controller {
	public function save(): void {
		$json = [];

		if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'POST') {
			$json['error']['warning'] = 'Некоректний спосіб збереження даних отримувача.';
		}

		if (!$this->customer->isLogged() || !isset($this->session->data['customer'])) {
			$json['error']['warning'] = 'Увійдіть в акаунт, щоб зберегти дані отримувача.';
		}

		$receiver = [
			'firstname' => trim(str_replace("\0", '', (string)($this->request->post['firstname'] ?? ''))),
			'lastname' => trim(str_replace("\0", '', (string)($this->request->post['lastname'] ?? ''))),
			'middlename' => trim(str_replace("\0", '', (string)($this->request->post['middlename'] ?? ''))),
			'telephone' => trim(str_replace("\0", '', (string)($this->request->post['telephone'] ?? ''))),
		];
		$revisionRaw = (string)($this->request->post['revision'] ?? '');
		$revision = ctype_digit($revisionRaw) ? (int)$revisionRaw : 0;

		if (!$json) {
			if (!oc_validate_length($receiver['firstname'], 1, 32)) {
				$json['error']['firstname'] = 'Ім\'я отримувача має містити від 1 до 32 символів.';
			}

			if (!oc_validate_length($receiver['lastname'], 1, 32)) {
				$json['error']['lastname'] = 'Прізвище отримувача має містити від 1 до 32 символів.';
			}

			if ($receiver['middlename'] !== '' && !oc_validate_length($receiver['middlename'], 1, 32)) {
				$json['error']['middlename'] = 'По батькові має містити не більше 32 символів.';
			}

			if (!oc_validate_length($receiver['telephone'], 3, 32)) {
				$json['error']['telephone'] = 'Телефон має містити від 3 до 32 символів.';
			}

			if ($revision <= 0) {
				$json['error']['warning'] = 'Не вдалося узгодити версію даних отримувача. Оновіть сторінку.';
			}
		}

		if (!$json) {
			$current = $this->session->data['rd13_receiver_override'] ?? [];
			$currentRevision = is_array($current) ? (int)($current['revision'] ?? 0) : 0;

			// Ignore a delayed older request: it must not overwrite newer typing.
			if ($revision >= $currentRevision) {
				$receiver['customer_id'] = (int)$this->customer->getId();
				$receiver['revision'] = $revision;
				$this->session->data['rd13_receiver_override'] = $receiver;
			}

			$json['success'] = 1;
			$json['revision'] = max($revision, $currentRevision);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE));
	}
}
PHP;

foreach ([
    'checkout.php receiver context' => [$checkout, $checkoutAnchor],
    'checkout.twig receiver context' => [$twig, $twigAnchor],
    'checkout.twig JS cache-buster' => [$twig, $jsHrefOld],
    'confirm.php customer override' => [$confirm, $confirmCoreAnchor],
    'confirm.php shipping override' => [$confirm, $confirmShippingAnchor],
    'success.php override cleanup' => [$success, $successAnchor],
] as $label => [$content, $anchor]) {
    if (rd13b_count($content, $anchor) !== 1) {
        rd13b_fail($label . ' anchor count is not 1; live shape changed');
    }
}

$updatedJs = preg_replace($jsPattern, $newJs, $js, 1, $replaceCount);

if ($updatedJs === null || $replaceCount !== 1) {
    rd13b_fail('cannot replace ensureReceiverRecap safely');
}

$updatedTwig = str_replace($twigAnchor, $twigInsert, $twig);
$updatedTwig = str_replace($jsHrefOld, $jsHrefNew, $updatedTwig);
$updatedCheckout = str_replace($checkoutAnchor, $checkoutInsert, $checkout);
$updatedConfirm = str_replace($confirmCoreAnchor, $confirmCoreInsert, $confirm);
$updatedConfirm = str_replace($confirmShippingAnchor, $confirmShippingInsert, $updatedConfirm);
$updatedSuccess = str_replace($successAnchor, $successInsert, $success);

foreach ([
    'checkout-reskin.js' => [$updatedJs, $marker],
    'checkout.twig' => [$updatedTwig, 'data-bs-receiver-firstname='],
    'checkout.php' => [$updatedCheckout, $marker],
    'confirm.php' => [$updatedConfirm, $marker],
    'success.php' => [$updatedSuccess, 'rd13_receiver_override'],
] as $label => [$content, $expected]) {
    if (strpos($content, $expected) === false) {
        rd13b_fail('in-memory post-replace verification failed: ' . $label);
    }
}

if ($dryRun) {
    rd13b_log('dry_run=ok');
    rd13b_log('would_change=' . implode(',', array_values($paths)));
    exit(0);
}

$backups = [];
foreach (['js', 'twig', 'checkout', 'confirm', 'success'] as $key) {
    $path = $root . '/' . $paths[$key];
    $backups[$path] = rd13b_backup($root, $backupRoot, $paths[$key]);
    rd13b_log('backup=' . $backups[$path]);
}

rd13b_write($root . '/' . $paths['js'], $updatedJs);
rd13b_write($root . '/' . $paths['twig'], $updatedTwig);
rd13b_write($root . '/' . $paths['checkout'], $updatedCheckout);
rd13b_write($root . '/' . $paths['confirm'], $updatedConfirm);
rd13b_write($root . '/' . $paths['success'], $updatedSuccess);
rd13b_write($receiverPath, $receiverController);

foreach (['checkout', 'confirm', 'success', 'receiver'] as $key) {
    $path = $root . '/' . $paths[$key];
    $output = [];
    $exitCode = 0;
    exec(escapeshellarg(PHP_BINARY ?: 'php') . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $exitCode);
    rd13b_log('php -l ' . $paths[$key] . ': ' . implode(' ', $output));

    if ($exitCode !== 0) {
        rd13b_restore($backups, $receiverPath);
        rd13b_fail('php-l rollback=ok after failure in ' . $paths[$key]);
    }
}

foreach ([
    $root . '/' . $paths['js'] => $marker,
    $root . '/' . $paths['twig'] => 'data-bs-receiver-firstname=',
    $root . '/' . $paths['checkout'] => $marker,
    $root . '/' . $paths['confirm'] => $marker,
    $root . '/' . $paths['success'] => 'rd13_receiver_override',
    $receiverPath => $marker,
] as $path => $expected) {
    if (strpos(rd13b_read($path), $expected) === false) {
        rd13b_restore($backups, $receiverPath);
        rd13b_fail('post-write verification failed for ' . $path . '; rollback=ok');
    }
}

rd13b_log('changed=' . implode(',', array_values($paths)));
rd13b_log('done=ok');
rd13b_log('smoke_test=authorized checkout: edit first name, last name, patronymic and phone; reload once; place one test order; verify account telephone and saved address unchanged while order plus shipping recipient use the override');

if (@unlink(__FILE__)) {
    rd13b_log('self_delete=ok');
} else {
    rd13b_log('self_delete=skipped');
}
