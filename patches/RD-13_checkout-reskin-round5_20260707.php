<?php
declare(strict_types=1);

/**
 * RD-13 — checkout reskin, round 5 (Claude). Polish pass on owner review of r4.
 *
 * Desktop: no bold "Авторизуйся"; compact autosizing comment; duplicate
 * confirm recap killed deterministically (cached wrapper tagged
 * bs-co-src-table in checkout.twig + CSS, on top of the JS sync()
 * exception-isolation fix that was the root cause on guest); receiver grid
 * ordered via inline styles (phone next to patronymic); stray "Captcha"
 * label removed; NP type radios reordered (відділення → поштомат → курʼєр);
 * free-shipping progress renders always; summary header total and
 * "Сума товарів" row hidden on desktop; promo button restyled; collapsible
 * Отримувач/Доставка now work on desktop too.
 * Mobile: SVG chevrons with correct orientation; collapsed-card typography
 * per mock; receiver recap shows name + address lines instead of the hint.
 *
 * No database, controller, payment/shipping endpoint, price calculation,
 * trusted-click gate, double-submit guard, Hutko, Checkbox, or order-status
 * behavior is changed. RD13-STUB free-shipping threshold (2000) preserved.
 *
 * Usage: php RD-13_checkout-reskin-round5_20260707.php [site_root] [--dry-run] [--keep-self]
 * Contract: idempotent (already_applied=yes), partial state fails loudly,
 * clears cache via config.php DIR_CACHE, self-deletes after done=ok.
 * Rollback: restore the three files from the printed _patch_backups dir.
 */

const RD13R5_PATCH_ID = 'RD-13_checkout-reskin-round5_20260707';
const RD13R5_CHECKOUT = 'catalog/view/template/checkout/checkout.twig';
const RD13R5_CSS = 'catalog/view/stylesheet/boostershop-ds.css';
const RD13R5_JS = 'catalog/view/javascript/checkout-reskin.js';

const RD13R5_CHECKOUT_SHA256 = 'ba9a9fbc3994d8160d47a32f376f04ef44fd160af3cfc93130214621ad010b07';
const RD13R5_CSS_SHA256 = 'bdf8e5a802d47dc21f62229ad4b94efc2d9871cc0da704bbffaa93f8c5fbdc58';
const RD13R5_JS_SHA256 = '0e4688f31abb6f09aefd16adda5301bea45932e4b6735257a2138940cd7ccaed';

const RD13R5_CSS_MARKER = 'RD-13 checkout reskin round 5';
const RD13R5_JS_MARKER = 'RD-13 checkout reskin round 5';
const RD13R5_VERSION_OLD = 'rd13r4-20260707';
const RD13R5_VERSION_NEW = 'rd13r5-20260707';

function rd13r5_out(string $key, string $value): void {
    echo $key . '=' . $value . PHP_EOL;
}

function rd13r5_fail(string $message): never {
    throw new RuntimeException($message);
}

function rd13r5_path(string $root, string $relative): string {
    return rtrim($root, "/\\") . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function rd13r5_read(string $path, string $relative): string {
    if (!is_file($path)) {
        rd13r5_fail('target_missing:' . $relative);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        rd13r5_fail('read_failed:' . $relative);
    }

    return $content;
}

function rd13r5_replace_once(string $content, string $search, string $replace, string $label): string {
    $count = substr_count($content, $search);

    if ($count !== 1) {
        rd13r5_fail('anchor_count_mismatch:' . $label . ':expected=1:actual=' . $count);
    }

    return str_replace($search, $replace, $content);
}

function rd13r5_hash_gate(string $content, string $expected, string $relative): void {
    $actual = hash('sha256', $content);
    rd13r5_out('preflight_sha256', $relative . ':' . $actual);

    if (!hash_equals($expected, $actual)) {
        rd13r5_fail('sha256_mismatch:' . $relative . ':expected=' . $expected . ':actual=' . $actual);
    }
}

function rd13r5_lint_self(): void {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1';
    $output = [];
    $exit = 1;
    exec($command, $output, $exit);
    rd13r5_out('php_lint_patch_self', 'exit=' . $exit . ';output=' . implode(' ', $output));

    if ($exit !== 0) {
        rd13r5_fail('php_lint_patch_self_failed');
    }
}

function rd13r5_atomic_write(string $path, string $content, string $relative): void {
    $written = file_put_contents($path, $content, LOCK_EX);

    if ($written === false || $written !== strlen($content)) {
        rd13r5_fail('write_failed_or_incomplete:' . $relative);
    }
}

function rd13r5_backup(string $root, string $backupRoot, string $relative): string {
    $source = rd13r5_path($root, $relative);
    $backup = rd13r5_path($backupRoot, $relative);
    $directory = dirname($backup);

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        rd13r5_fail('backup_mkdir_failed:' . $relative);
    }

    if (!copy($source, $backup)) {
        rd13r5_fail('backup_copy_failed:' . $relative);
    }

    rd13r5_out('backup', $relative . ' -> ' . $backup);
    return $backup;
}

function rd13r5_restore(array $backups, string $root): void {
    foreach ($backups as $relative => $backup) {
        $target = rd13r5_path($root, $relative);
        if (is_file($backup)) {
            @copy($backup, $target);
        }
    }
}

function rd13r5_self_delete(array $argv): void {
    if (in_array('--keep-self', $argv, true)) {
        rd13r5_out('self_delete', 'skipped_keep_self');
        return;
    }

    if (@unlink(__FILE__)) {
        rd13r5_out('self_delete', 'ok');
    } else {
        rd13r5_out('self_delete', 'FAILED_delete_manually');
    }
}

function rd13r5_clear_cache(string $root): void {
    $configPath = rd13r5_path($root, 'config.php');
    $cacheDir = '';

    if (is_file($configPath)) {
        $config = (string) file_get_contents($configPath);

        if (preg_match("~define\s*\(\s*'DIR_CACHE'\s*,\s*'([^']+)'\s*\)~", $config, $match)) {
            $cacheDir = rtrim($match[1], '/');
        }
    }

    if ($cacheDir === '' || !is_dir($cacheDir)) {
        rd13r5_out('cache_clear', 'skipped_dir_cache_not_found_clear_manually');
        return;
    }

    $removed = 0;

    foreach (glob($cacheDir . '/cache.*') ?: [] as $file) {
        if (is_file($file) && @unlink($file)) {
            $removed++;
        }
    }

    $templateCache = $cacheDir . '/template';

    if (is_dir($templateCache)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($templateCache, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            if ($entry->isFile() && @unlink($entry->getPathname())) {
                $removed++;
            }
        }
    }

    rd13r5_out('cache_clear', 'ok:removed=' . $removed . ':dir=' . $cacheDir);
}

function rd13r5_new_js(): string {
    return <<<'RD13R5JSEOT'
/* RD-13 checkout reskin round 5
 * Presentation helpers only. No endpoint, payload, or order-creation changes.
 * r5 on top of r4: sync() exception isolation (guest duplicate-table root
 * cause), deterministic receiver-grid ordering, captcha col capture + stray
 * label cleanup, always-on free-shipping progress, desktop collapse toggles,
 * SVG chevrons, comment autosize, receiver recap with real data lines,
 * NP type radio order (відділення → поштомат → кур'єр).
 */
(function ($) {
  'use strict';

  var root = document.querySelector('[data-rd13-checkout]');
  var observerTimer = 0;
  var syncing = false;
  var MOBILE_QUERY = '(max-width: 900px)';

  if (!root || !$) {
    return;
  }

  function isMobile() {
    return window.matchMedia(MOBILE_QUERY).matches;
  }

  function text(value) {
    return String(value || '').replace(/\s+/g, ' ').trim();
  }

  function setText(node, value) {
    if (node && node.textContent !== value) {
      node.textContent = value;
    }
  }

  function fieldBlock(input) {
    return input ? input.closest('.col, .mb-2, .mb-3, .form-group, .form-check, fieldset') : null;
  }

  function bindControlsToRegister(container) {
    if (!container) {
      return;
    }

    container.querySelectorAll('input, select, textarea, button').forEach(function (control) {
      control.setAttribute('form', 'form-register');
    });
  }

  function markRecipientField(input, className) {
    var block = fieldBlock(input);

    if (block) {
      block.classList.add(className);
    }

    return block;
  }

  // ---- Receiver card: field grid + NP panel relocation (guest) ----
  function moveDeliveryFields() {
    var deliveryBody = root.querySelector('[data-co-card="delivery"] .bs-co-card__body');
    var registerForm = document.getElementById('form-register');

    if (!deliveryBody) {
      return;
    }

    var shippingMethod = document.getElementById('checkout-shipping-method');
    if (shippingMethod && deliveryBody.firstElementChild !== shippingMethod) {
      deliveryBody.insertBefore(shippingMethod, deliveryBody.firstElementChild);
    }

    if (!registerForm) {
      return;
    }

    var receiverGrid = registerForm.querySelector('fieldset:first-of-type > .row');
    var npPanel = document.getElementById('shipping-novaposhta');

    if (receiverGrid && npPanel) {
      [
        ['#input-shipping-novaposhta-firstname', 'bs-co-recipient-field--first'],
        ['#input-shipping-novaposhta-lastname', 'bs-co-recipient-field--last'],
        ['#input-shipping-novaposhta-middlename', 'bs-co-recipient-field--middle']
      ].forEach(function (entry) {
        var block = markRecipientField(document.querySelector(entry[0]), entry[1]);
        if (block && block.parentNode !== receiverGrid) {
          receiverGrid.appendChild(block);
        }
      });

      var phoneBlock = markRecipientField(document.getElementById('input-telephone'), 'bs-co-recipient-field--phone');
      var emailBlock = markRecipientField(document.getElementById('input-email'), 'bs-co-recipient-field--email');

      if (phoneBlock && phoneBlock.parentNode === receiverGrid) {
        receiverGrid.appendChild(phoneBlock);
      }

      if (emailBlock && emailBlock.parentNode === receiverGrid) {
        receiverGrid.appendChild(emailBlock);
      }

      if (npPanel.parentNode !== deliveryBody) {
        deliveryBody.appendChild(npPanel);
      }
      bindControlsToRegister(npPanel);
    }

    var registerStatus = registerForm.querySelector('[data-bs-register-status]');
    if (registerStatus && registerStatus.parentNode !== deliveryBody) {
      deliveryBody.appendChild(registerStatus);
    }

    if (receiverGrid) {
      orderReceiverGrid(receiverGrid);
    }
  }

  // r5: explicit inline order — CSS class-based ordering proved fragile
  // against untagged stock blocks sharing the grid.
  function orderReceiverGrid(grid) {
    var orderMap = [
      ['input-shipping-novaposhta-firstname', 1],
      ['input-shipping-novaposhta-lastname', 2],
      ['input-shipping-novaposhta-middlename', 3],
      ['input-telephone', 4],
      ['input-email', 5]
    ];

    Array.prototype.forEach.call(grid.children, function (child) {
      if (!child.style.order) {
        child.style.order = '10';
      }
      child.style.minWidth = '0';
    });

    orderMap.forEach(function (entry) {
      var input = document.getElementById(entry[0]);
      var block = input ? input.closest('.col, .mb-3, .mb-2') : null;

      if (block && block.parentNode === grid) {
        block.style.order = String(entry[1]);
      }
    });
  }

  // ---- r4: responsive tail (desktop: inside summary card; mobile: bottom card) ----
  function tailBottom() {
    var bottom = document.getElementById('bs-co-tail-bottom');

    if (!bottom) {
      bottom = document.createElement('div');
      bottom.id = 'bs-co-tail-bottom';
      var body = root.querySelector('[data-co-summary-body]');
      if (!body) {
        return null;
      }
      body.appendChild(bottom);
    }

    return bottom;
  }

  function placeTail() {
    var bottom = tailBottom();

    if (!bottom) {
      return;
    }

    var grid = root.querySelector('.bs-co-grid');
    var body = root.querySelector('[data-co-summary-body]');

    if (isMobile()) {
      if (bottom.parentNode !== grid) {
        grid.appendChild(bottom);
        bottom.classList.add('bs-card', 'bs-co-card', 'bs-co-tailcard');
      }
    } else if (bottom.parentNode !== body) {
      body.appendChild(bottom);
      bottom.classList.remove('bs-card', 'bs-co-card', 'bs-co-tailcard');
    }

    // Adopt pieces in approved order: comment → agree → opt-in → confirm (hint+CTA) → captcha → newsletter.
    var pieces = [];
    var comment = document.getElementById('input-comment');
    var agree = document.getElementById('input-checkout-agree');
    var optIn = document.getElementById('input-create-account-opt-in');

    [comment, agree, optIn].forEach(function (input) {
      if (input) {
        pieces.push(input.closest('.mb-2, .form-check') || null);
      }
    });

    pieces.push(document.getElementById('checkout-confirm'));

    var registerForm = document.getElementById('form-register');
    if (registerForm) {
      var recaptcha = registerForm.querySelector('[id^="g-recaptcha-"], .g-recaptcha, iframe[src*="recaptcha"]') ||
        document.querySelector('[id^="g-recaptcha-"], .g-recaptcha, iframe[src*="recaptcha"]');
      var captchaBlock = recaptcha
        ? (recaptcha.closest('.col') || recaptcha.closest('fieldset, .mb-2, .mb-3, .form-group'))
        : null;
      if (captchaBlock) {
        captchaBlock.classList.add('bs-co-moved-captcha');
        bindControlsToRegister(captchaBlock);
        pieces.push(captchaBlock);
      }

      var newsletter = document.getElementById('input-newsletter');
      var newsletterBlock = newsletter ? newsletter.closest('.form-check, .mb-2, .mb-3, .form-group') : null;
      if (newsletterBlock) {
        newsletterBlock.classList.add('bs-co-moved-newsletter');
        bindControlsToRegister(newsletterBlock);
        pieces.push(newsletterBlock);
      }
    }

    pieces.forEach(function (piece) {
      if (piece && piece.parentNode !== bottom) {
        bottom.appendChild(piece);
      }
    });

    // r5: any leftover bare "Captcha" label in the register form is stock
    // noise once the widget block moved under the CTA.
    var register = document.getElementById('form-register');
    if (register) {
      register.querySelectorAll('label, .form-label').forEach(function (label) {
        if (/^Captcha$/i.test(text(label.textContent)) && !label.closest('.bs-co-moved-captcha')) {
          label.style.display = 'none';
        }
      });
    }

    // Keep DOM order inside the container aligned with the approved order.
    var ordered = pieces.filter(Boolean);
    ordered.forEach(function (piece, index) {
      if (bottom.children[index] !== piece) {
        bottom.appendChild(piece);
      }
    });
  }

  function ensurePromoStub() {
    var tail = document.getElementById('bs-co-summary-tail');

    if (!tail || tail.querySelector('[data-rd13-promo-stub]')) {
      return;
    }

    var promo = document.createElement('div');
    promo.className = 'bs-field';
    promo.setAttribute('data-rd13-promo-stub', '1');
    // RD13-STUB: replace with real coupon wiring once the endpoint ships.
    promo.innerHTML =
      '<label>Промокод</label>' +
      '<div class="bs-co-promo-input">' +
        '<input class="bs-input" name="rd13_stub_coupon" placeholder="Введіть промокод">' +
        '<button type="button" class="bs-btn bs-btn-secondary" data-co-promo-stub>Застосувати</button>' +
      '</div>' +
      '<div class="bs-co-field-hint" data-co-promo-stub-msg hidden>Промокоди зʼявляться незабаром</div>';
    tail.insertBefore(promo, tail.firstChild);

    var button = promo.querySelector('[data-co-promo-stub]');
    button.addEventListener('click', function () {
      var message = promo.querySelector('[data-co-promo-stub-msg]');
      if (message) {
        message.hidden = false;
      }
    });
  }

  function styleControls() {
    root.querySelectorAll('.bs-checkout-method-option, .bs-checkout-panel-choice .form-check, .bs-co-type-option').forEach(function (row) {
      var checked = row.querySelector('input[type="radio"]:checked, input[type="checkbox"]:checked');
      row.classList.toggle('is-active', !!checked);
    });
  }

  var PAYMENT_LABEL_MAP = [
    {
      match: /^(Оплата карткою через Hutko|Оплата карткою,\s*Google\s*\/\s*Apple Pay)$/i,
      title: 'Картка, Google Pay / Apple Pay',
      sub: 'Безпечно через еквайринг',
      rank: 1
    },
    {
      match: /^Оплата (при доставці|при отриманні)\s*\((накладений платіж|післяплата)\)$/i,
      title: 'Оплата при отриманні (накладений платіж)',
      sub: '',
      rank: 2
    },
    {
      match: /^(Банківський переказ|Оплата за реквізитами)$/i,
      title: 'За реквізитами на IBAN',
      sub: '',
      rank: 3
    }
  ];

  function relabelPaymentMethods() {
    var list = document.getElementById('bs-payment-methods');

    if (!list) {
      return;
    }

    var rows = Array.prototype.slice.call(list.querySelectorAll('.bs-checkout-method-option'));
    rows.forEach(function (row) {
      var label = row.querySelector(':scope > span, .form-check-label');
      if (!label || label.dataset.coRelabelled === '1') {
        return;
      }

      var current = text(label.textContent);
      var rule = PAYMENT_LABEL_MAP.find(function (candidate) {
        return candidate.match.test(current);
      });

      if (!rule) {
        row.dataset.coPaymentRank = '99';
        return;
      }

      label.dataset.coRelabelled = '1';
      row.dataset.coPaymentRank = String(rule.rank);
      setText(label, rule.title);

      if (rule.sub) {
        var subNode = document.createElement('span');
        subNode.className = 'bs-co-payment-sub';
        subNode.textContent = rule.sub;
        label.appendChild(subNode);
      }
    });

    var sorted = rows.slice().sort(function (a, b) {
      return Number(a.dataset.coPaymentRank || 99) - Number(b.dataset.coPaymentRank || 99);
    });

    sorted.forEach(function (row, index) {
      if (list.children[index] !== row) {
        list.appendChild(row);
      }
    });
  }

  // r4: keep the payment/shipping inline status visible when the method list
  // is empty — hiding it left the Оплата card blank with no explanation.
  function syncStatusVisibility() {
    [
      ['bs-payment-methods', '[data-bs-payment-status]'],
      ['bs-shipping-methods', '[data-bs-shipping-status]']
    ].forEach(function (pair) {
      var list = document.getElementById(pair[0]);
      var status = document.querySelector(pair[1]);

      if (status) {
        status.classList.toggle('bs-co-status-visible', !list || !list.children.length);
      }
    });
  }

  // ---- r4: NP delivery-type radios (presentational proxy over the module select) ----
  var TYPE_OPTIONS = [
    { value: 'warehouse', title: 'Нова пошта — у відділення', sub: 'За тарифами Нової пошти · ~2–3 дні' },
    { value: 'poshtoma', title: 'Нова пошта — поштомат', sub: '' },
    { value: 'doors', title: 'Нова пошта — курʼєром', sub: 'Адресна доставка' }
  ];

  function npTypeSelect() {
    return document.getElementById('input-shipping-novaposhta-type');
  }

  function ensureTypeRadios() {
    var deliveryBody = root.querySelector('[data-co-card="delivery"] .bs-co-card__body');
    var select = npTypeSelect();

    if (!deliveryBody || !select) {
      return;
    }

    var wrap = document.getElementById('bs-co-type-radios');

    if (!wrap) {
      wrap = document.createElement('div');
      wrap.id = 'bs-co-type-radios';

      var html = '';
      TYPE_OPTIONS.forEach(function (option, index) {
        html += '<label class="bs-co-type-option" for="bs-co-type-' + option.value + '">' +
          '<input type="radio" name="bs_co_type_proxy" id="bs-co-type-' + option.value + '" value="' + option.value + '"' + (index === 0 ? ' checked' : '') + '>' +
          '<span class="bs-co-type-option__text"><span class="bs-co-type-option__title">' + option.title + '</span>' +
          (option.sub ? '<span class="bs-co-method-sub">' + option.sub + '</span>' : '') + '</span>' +
          '</label>';
      });
      wrap.innerHTML = html;

      wrap.addEventListener('change', function (event) {
        var input = event.target;

        if (!input || input.name !== 'bs_co_type_proxy') {
          return;
        }

        var current = npTypeSelect();

        if (!current) {
          return;
        }

        // In saved-address mode a different type means "enter another address".
        var manualRadio = document.getElementById('input-shipping-novaposhta');
        if (manualRadio && !manualRadio.checked && savedAddressMode()) {
          $(manualRadio).prop('checked', true).trigger('change');
        }

        if (current.value !== input.value) {
          $(current).val(input.value).trigger('change');
        }

        styleControls();
      });

      deliveryBody.insertBefore(wrap, deliveryBody.firstElementChild);
    }

    syncTypeRadios();
  }

  function syncTypeRadios() {
    var select = npTypeSelect();
    var wrap = document.getElementById('bs-co-type-radios');

    if (!wrap) {
      return;
    }

    var value = null;

    if (savedAddressMode()) {
      var parsed = parseAddressText(selectedSavedAddressText());
      value = parsed ? parsed.type : null;
    }

    if (!value && select) {
      value = select.value;
    }

    if (!value) {
      return;
    }

    var input = wrap.querySelector('input[value="' + value + '"]');

    if (input && !input.checked) {
      input.checked = true;
    }
  }

  // ---- r4: saved/manual address presentation (links instead of radios) ----
  function savedAddressSelect() {
    return document.getElementById('input-shipping-address');
  }

  function savedAddressMode() {
    var existing = document.getElementById('input-shipping-existing');
    return !!(existing && existing.checked);
  }

  function selectedSavedAddressText() {
    var select = savedAddressSelect();
    return select && select.selectedOptions && select.selectedOptions[0]
      ? text(select.selectedOptions[0].textContent)
      : '';
  }

  function parseAddressText(value) {
    if (!value) {
      return null;
    }

    var type = null;

    if (/поштомат/i.test(value)) {
      type = 'poshtoma';
    } else if (/відділення/i.test(value)) {
      type = 'warehouse';
    } else if (/вул\.|просп\.|бульв\.|пров\./i.test(value)) {
      type = 'doors';
    }

    var numberMatch = value.match(/№\s*(\d+)/);
    var streetMatch = value.match(/(вул\.|просп\.|бульв\.|пров\.)\s*([^,]+)/i);

    return {
      type: type,
      number: numberMatch ? numberMatch[1] : '',
      street: streetMatch ? text(streetMatch[1] + ' ' + streetMatch[2]) : ''
    };
  }

  function ensureAddressLinks() {
    var addressWrap = document.getElementById('checkout-shipping-address');

    if (!addressWrap) {
      return;
    }

    var manualRadio = document.getElementById('input-shipping-novaposhta');
    var existingRadio = document.getElementById('input-shipping-existing');

    if (!manualRadio || !existingRadio) {
      return;
    }

    var links = document.getElementById('bs-co-address-links');

    if (!links) {
      links = document.createElement('div');
      links.id = 'bs-co-address-links';
      links.innerHTML =
        '<button type="button" class="bs-co-link" data-co-address-link="manual">+ Інша адреса</button>' +
        '<button type="button" class="bs-co-link" data-co-address-link="saved">← Використати збережену адресу</button>';
      addressWrap.appendChild(links);

      links.addEventListener('click', function (event) {
        var button = event.target.closest('[data-co-address-link]');

        if (!button) {
          return;
        }

        var target = button.getAttribute('data-co-address-link') === 'manual' ? manualRadio : existingRadio;
        $(target).prop('checked', true).trigger('change');
        syncAddressLinks();
      });
    }

    syncAddressLinks();
  }

  function syncAddressLinks() {
    var links = document.getElementById('bs-co-address-links');

    if (!links) {
      return;
    }

    var saved = savedAddressMode();
    var manualLink = links.querySelector('[data-co-address-link="manual"]');
    var savedLink = links.querySelector('[data-co-address-link="saved"]');

    if (manualLink) {
      manualLink.hidden = !saved;
    }

    if (savedLink) {
      savedLink.hidden = saved;
    }
  }

  function restyleSavedAddress() {
    var select = savedAddressSelect();

    if (select && select.dataset.coCleaned !== '1') {
      Array.prototype.forEach.call(select.options, function (option) {
        var cleaned = text(option.textContent).replace(/^[^,]+,\s*/, '').replace(/,?\s*Ukraine\s*$/i, '');

        if (cleaned) {
          option.textContent = cleaned;
        }
      });
      select.dataset.coCleaned = '1';
    }
  }

  // ---- r4: auth receiver recap (data already present on the page only) ----
  function ensureReceiverRecap() {
    if (document.getElementById('form-register') || root.querySelector('[data-co-card="receiver"]')) {
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
          '<div class="bs-co-recap-line" data-co-receiver-line></div>' +
          '<div class="bs-co-recap-sub" data-co-receiver-address></div>' +
        '</div>';
      deliveryCard.parentNode.insertBefore(recap, deliveryCard);
    }

    if (recap) {
      var source = select.dataset.coNameCache || '';

      if (!source) {
        // Name prefix was stripped from options by restyleSavedAddress —
        // cache it from the raw option text on first run.
        source = text((select.options[select.selectedIndex] || select.options[0]).textContent);
      }

      var line = recap.querySelector('[data-co-receiver-line]');
      setText(line, source ? source.split(',')[0] : '');

      var addressLine = recap.querySelector('[data-co-receiver-address]');
      setText(addressLine, selectedSavedAddressText());
    }
  }

  function cacheReceiverName() {
    var select = savedAddressSelect();

    if (select && !select.dataset.coNameCache && select.options.length) {
      var raw = text((select.options[select.selectedIndex] || select.options[0]).textContent);
      var name = raw.split(',')[0];

      if (name && !/поштомат|відділення|вул\./i.test(name)) {
        select.dataset.coNameCache = name;
      }
    }
  }

  function parseMoney(value) {
    var normalized = text(value).replace(/[^\d,.-]/g, '');
    var comma = normalized.lastIndexOf(',');
    var dot = normalized.lastIndexOf('.');

    if (comma > dot) {
      normalized = normalized.replace(/\./g, '').replace(',', '.');
    } else if (dot > comma) {
      normalized = normalized.replace(/,/g, '');
    } else {
      normalized = normalized.replace(',', '.');
    }

    var amount = Number(normalized);
    return Number.isFinite(amount) ? amount : 0;
  }

  function formatHryvnia(value) {
    return new Intl.NumberFormat('uk-UA', {
      maximumFractionDigits: 0
    }).format(Math.ceil(value));
  }

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function (character) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      }[character];
    });
  }

  // r4: product thumbs from the header mini-cart (already rendered by
  // common/cart.twig for this session — no extra requests).
  function findItemThumb(name) {
    var needle = text(name).toLowerCase();

    if (!needle) {
      return '';
    }

    var images = document.querySelectorAll('#cart .mini-cart-thumb img, #cart img.img-thumbnail');
    var found = '';

    Array.prototype.forEach.call(images, function (img) {
      if (found) {
        return;
      }

      var alt = text(img.alt || img.title).toLowerCase();

      if (alt && (alt.indexOf(needle) !== -1 || needle.indexOf(alt) !== -1)) {
        found = img.src;
      }
    });

    return found;
  }

  function buildSummaryView() {
    var body = root.querySelector('[data-co-summary-body]');

    if (!body) {
      return;
    }

    var table = root.querySelector('#checkout-confirm .bs-confirm-deferred-summary table') ||
      root.querySelector('#checkout-confirm > .table-responsive table');
    var view = body.querySelector('[data-rd13-view]');

    if (!table || !table.tBodies.length) {
      if (view) {
        view.remove();
      }
      return;
    }

    var wrapper = table.closest('.table-responsive');
    var signature = text(table.innerText || table.textContent);

    if (view && view.dataset.sig === signature) {
      if (wrapper) {
        wrapper.classList.add('bs-co-src-hidden');
      }
      return;
    }

    var items = [];
    Array.prototype.forEach.call(table.tBodies[0].rows, function (row) {
      if (row.cells.length < 2) {
        return;
      }

      var firstCell = row.cells[0];
      var link = firstCell.querySelector('a');
      var qtyMatch = text(firstCell.textContent).match(/^(\d+)\s*x/i);

      items.push({
        name: text(link ? link.textContent : firstCell.childNodes[0] && firstCell.childNodes[0].textContent),
        qty: qtyMatch ? qtyMatch[1] : '1',
        price: text(row.cells[row.cells.length - 1].textContent)
      });
    });

    var subtotal = null;
    var shipping = null;
    var grand = null;
    var extras = [];

    if (table.tFoot) {
      Array.prototype.forEach.call(table.tFoot.rows, function (row) {
        if (row.cells.length < 1) {
          return;
        }

        var label = text(row.cells[0].textContent);
        var value = text(row.cells[row.cells.length - 1].textContent);

        if (/^Сума( товарів)?$/i.test(label)) {
          subtotal = { label: 'Сума товарів', value: value };
        } else if (/^(Разом|До сплати)$/i.test(label)) {
          grand = { label: 'До сплати', value: value };
        } else if (/доставка|пошта/i.test(label)) {
          shipping = { label: label, value: value };
        } else if (label) {
          extras.push({
            label: label,
            value: value,
            discount: /зниж|купон|coupon/i.test(label)
          });
        }
      });
    }

    var html = '<div class="bs-co-items' + (items.length > 3 ? ' bs-co-items--scroll' : '') + '">';
    items.forEach(function (item) {
      var thumb = findItemThumb(item.name);

      html += '<div class="bs-co-item">' +
        (thumb ? '<img class="bs-co-item__img" src="' + escapeHtml(thumb) + '" alt="" loading="lazy">' : '') +
        '<div class="bs-co-item__main"><div class="bs-co-item__title">' + escapeHtml(item.name) + '</div>' +
        '<div class="bs-co-item__qty">× ' + escapeHtml(item.qty) + '</div></div>' +
        '<div class="bs-co-item__price">' + escapeHtml(item.price) + '</div>' +
        '</div>';
    });
    html += '</div>';

    // RD13-STUB: threshold is a temporary constant until the real
    // free-shipping config/backend ships. grep RD13-STUB to remove.
    // Progress is computed off the payable total (post-discount). The block
    // renders even before a shipping quote exists (approved mock shows it
    // always); the price cell then shows an em dash.
    var FREE_SHIP_THRESHOLD = 2000;
    var payableSource = grand || subtotal;
    var payableAmount = payableSource ? parseMoney(payableSource.value) : 0;
    var remaining = Math.max(0, FREE_SHIP_THRESHOLD - payableAmount);
    var percentage = Math.min(100, Math.round((payableAmount / FREE_SHIP_THRESHOLD) * 100));
    var free = remaining <= 0;
    var shippingPrice = free ? 'Безкоштовно' : (shipping ? escapeHtml(shipping.value) : '—');

    html += '<div class="bs-co-shipblock rd13-stub' + (free ? ' is-free' : '') + '">' +
      '<div class="bs-co-shipblock__row"><span>Доставка</span>' +
      '<span class="bs-co-shipblock__price">' + shippingPrice + '</span></div>' +
      '<div class="bs-co-shipblock__msg">' + (free
        ? 'Безкоштовна доставка застосована ✓'
        : 'До безкоштовної доставки лишилось ₴' + formatHryvnia(remaining)) + '</div>' +
      '<div class="bs-co-shipblock__track"><i style="width:' + percentage + '%"></i></div>' +
      '</div>';

    html += '<div class="bs-co-totals">';

    if (subtotal) {
      html += '<div class="bs-co-totals__subtotal"><span>' + escapeHtml(subtotal.label) + '</span><span>' + escapeHtml(subtotal.value) + '</span></div>';
    }

    extras.forEach(function (extra) {
      html += '<div' + (extra.discount ? ' class="bs-co-totals__discount"' : '') + '><span>' +
        escapeHtml(extra.label) + '</span><span>' + escapeHtml(extra.value) + '</span></div>';
    });

    if (grand) {
      html += '<div class="bs-co-totals__grand"><span>' + escapeHtml(grand.label) + '</span><span>' +
        escapeHtml(grand.value) + '</span></div>';
    }

    html += '</div>';

    if (!view) {
      view = document.createElement('div');
      view.setAttribute('data-rd13-view', '1');
      body.insertBefore(view, body.firstChild);
    }

    view.innerHTML = html;
    view.dataset.sig = signature;

    if (wrapper) {
      wrapper.classList.add('bs-co-src-hidden');
    }
  }

  function updateSummaryMeta() {
    var quantity = 0;
    var totalNode = document.querySelector('[data-co-summary-total]');
    var qtyNode = document.querySelector('[data-co-summary-qty]');
    var rows = root.querySelectorAll('#checkout-confirm tbody tr');
    var grand = root.querySelector('#checkout-confirm tfoot tr:last-child td:last-child');

    rows.forEach(function (row) {
      var cells = row.querySelectorAll('td');
      var candidate = cells.length ? text(cells[0].textContent) : '';
      var match = candidate.match(/^(\d+)\s*x/i);
      quantity += match ? parseInt(match[1], 10) : 1;
    });

    setText(qtyNode, quantity ? '· ' + quantity + (quantity === 1 ? ' товар' : ' товари') : '');
    setText(totalNode, grand ? text(grand.textContent) : '');
  }

  var SHIPPING_LABEL_MAP = [
    { match: /поштомат/i, title: 'Нова пошта — поштомат', typeLabel: 'поштомат' },
    { match: /відділен/i, title: 'Нова пошта — у відділення', typeLabel: 'відділення' },
    { match: /кур['ʼ`]?єр|адресна|doors/i, title: 'Нова пошта — курʼєром', typeLabel: 'адреса' }
  ];

  var TYPE_SUMMARY_LABEL = { warehouse: 'відділення', poshtoma: 'поштомат', doors: 'адреса' };

  function deliverySummaryText() {
    // Saved address: "Нова пошта · поштомат №49489" / "… · відділення №22" /
    // "… · адреса вул. …". Manual mode: from the currently filled NP fields.
    if (savedAddressMode()) {
      var parsed = parseAddressText(selectedSavedAddressText());

      if (parsed && parsed.type) {
        var typeLabel = TYPE_SUMMARY_LABEL[parsed.type] || '';
        var detail = parsed.type === 'doors' ? parsed.street : (parsed.number ? '№' + parsed.number : '');
        return text('Нова пошта · ' + typeLabel + (detail ? ' ' + detail : ''));
      }

      return selectedSavedAddressText() ? 'Нова пошта · збережена адреса' : '';
    }

    var select = npTypeSelect();

    if (select) {
      var typeName = TYPE_SUMMARY_LABEL[select.value] || '';
      var detailNode = select.value === 'doors'
        ? document.getElementById('input-shipping-novaposhta-doors-street')
        : document.getElementById('input-shipping-novaposhta-warehouse-address');
      var detailText = detailNode ? text(detailNode.value) : '';
      var numberMatch = detailText.match(/№\s*(\d+)/);

      if (detailText) {
        detailText = numberMatch ? '№' + numberMatch[1] : detailText;
        return text('Нова пошта · ' + typeName + ' ' + detailText);
      }

      return typeName ? 'Нова пошта · ' + typeName : '';
    }

    var hidden = document.getElementById('input-shipping-method');
    var label = hidden ? text(hidden.value) : '';
    var rule = SHIPPING_LABEL_MAP.find(function (candidate) {
      return candidate.match.test(label);
    });

    return rule ? rule.title : label;
  }

  function updateCardSummaries() {
    var receiver = document.querySelector('[data-co-receiver-summary]');
    var delivery = document.querySelector('[data-co-delivery-summary]');
    var first = document.getElementById('input-shipping-novaposhta-firstname') || document.getElementById('input-firstname');
    var last = document.getElementById('input-shipping-novaposhta-lastname') || document.getElementById('input-lastname');
    var phone = document.getElementById('input-telephone');
    var receiverText = [first && first.value, last && last.value].map(text).filter(Boolean).join(' ');
    var phoneText = phone ? text(phone.value) : '';
    var deliveryText = deliverySummaryText();

    if (!receiverText) {
      var select = savedAddressSelect();
      receiverText = select && select.dataset.coNameCache ? select.dataset.coNameCache : '';
    }

    if (receiver) {
      setText(receiver, [receiverText, phoneText].filter(Boolean).join(' · '));
    }

    setText(delivery, deliveryText);

    var receiverCard = receiver && receiver.closest('[data-co-card]');
    var deliveryCard = delivery && delivery.closest('[data-co-card]');

    if (receiverCard) {
      receiverCard.dataset.hasData = receiverText ? '1' : '0';
    }

    if (deliveryCard) {
      deliveryCard.dataset.hasData = deliveryText ? '1' : '0';
    }
  }

  // r4: the server-rendered fallback confirm button (confirm.twig, disabled
  // until the deferred flow replaces it) uses the stock language string.
  function relabelFallbackConfirm() {
    root.querySelectorAll('#checkout-confirm button').forEach(function (button) {
      if (button.dataset.coRelabelled === '1') {
        return;
      }

      if (/^Підтвердження замовлення$/i.test(text(button.textContent))) {
        button.dataset.coRelabelled = '1';
        setText(button, 'Підтвердити замовлення →');
      }
    });
  }

  // r5: replace the text chevron with an SVG so orientation/weight match the mock.
  function ensureChevrons() {
    root.querySelectorAll('.bs-co-chevron').forEach(function (node) {
      if (node.dataset.coSvg === '1') {
        return;
      }

      node.dataset.coSvg = '1';
      node.innerHTML = '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M3.5 6l4.5 4.5L12.5 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    });
  }

  // r5: comment textarea — compact by default, grows with content.
  function ensureCommentAutosize() {
    var comment = document.getElementById('input-comment');

    if (!comment || comment.dataset.coAutosize === '1') {
      return;
    }

    comment.dataset.coAutosize = '1';
    comment.rows = 2;

    var resize = function () {
      comment.style.height = 'auto';
      comment.style.height = Math.min(comment.scrollHeight + 2, 300) + 'px';
    };

    comment.addEventListener('input', resize);
    resize();
  }

  function initToggles() {
    root.querySelectorAll('[data-co-card-toggle]').forEach(function (button) {
      if (button.dataset.coBound === '1') {
        return;
      }

      button.dataset.coBound = '1';
      button.addEventListener('click', function () {
        var card = button.closest('[data-co-card]');
        var collapsed = !card.classList.contains('is-collapsed');
        card.classList.toggle('is-collapsed', collapsed);
        button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      });
    });

    var summaryToggle = root.querySelector('[data-co-summary-toggle]');

    if (summaryToggle && summaryToggle.dataset.coBound !== '1') {
      summaryToggle.dataset.coBound = '1';
      summaryToggle.addEventListener('click', function () {
        if (!isMobile()) {
          return;
        }

        var card = summaryToggle.closest('.bs-co-summary');
        var open = !card.classList.contains('is-open');
        card.classList.toggle('is-open', open);
        summaryToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }
  }

  function applyInitialMobileCollapse() {
    if (!isMobile()) {
      return;
    }

    root.querySelectorAll('[data-co-collapsible]').forEach(function (card) {
      if (card.dataset.coInitialised === '1') {
        return;
      }

      card.dataset.coInitialised = '1';

      if (card.dataset.hasData === '1') {
        card.classList.add('is-collapsed');
        var button = card.querySelector('[data-co-card-toggle]');
        if (button) {
          button.setAttribute('aria-expanded', 'false');
        }
      }
    });
  }

  // r5: one failing step must not kill the pipeline (a guest-only exception
  // previously left `syncing` stuck and the raw confirm table visible).
  function safeStep(fn) {
    try {
      fn();
    } catch (error) {
      if (window.console && !fn.coWarned) {
        fn.coWarned = true;
        console.warn('[RD-13] step failed:', fn.name, error);
      }
    }
  }

  function sync() {
    if (syncing) {
      return;
    }

    syncing = true;

    try {
      [
        cacheReceiverName,
        restyleSavedAddress,
        moveDeliveryFields,
        placeTail,
        ensurePromoStub,
        ensureTypeRadios,
        ensureAddressLinks,
        ensureReceiverRecap,
        styleControls,
        relabelPaymentMethods,
        syncStatusVisibility,
        buildSummaryView,
        updateSummaryMeta,
        updateCardSummaries,
        relabelFallbackConfirm,
        ensureChevrons,
        ensureCommentAutosize,
        initToggles,
        applyInitialMobileCollapse
      ].forEach(safeStep);
    } finally {
      syncing = false;
    }
  }

  function scheduleSync() {
    window.clearTimeout(observerTimer);
    observerTimer = window.setTimeout(sync, 40);
  }

  $(root).on('change input', 'input, select, textarea', scheduleSync);
  $(sync);

  var mediaQuery = window.matchMedia(MOBILE_QUERY);

  if (mediaQuery.addEventListener) {
    mediaQuery.addEventListener('change', scheduleSync);
  }

  if (window.MutationObserver) {
    new MutationObserver(scheduleSync).observe(root, {
      childList: true,
      subtree: true
    });

    var cart = document.getElementById('cart');

    if (cart) {
      new MutationObserver(function () {
        var view = root.querySelector('[data-rd13-view]');
        if (view) {
          delete view.dataset.sig;
        }
        scheduleSync();
      }).observe(cart, { childList: true, subtree: true });
    }
  }
})(window.jQuery);
RD13R5JSEOT;
}

function rd13r5_css_round5_block(): string {
    return <<<'RD13R5CSSEOT'

/* ==== RD-13 checkout reskin round 5 (presentation only) ==== */
/* Deterministic kill for the duplicate confirm recap: the cached table is a
   data source only — the custom view renders it. */
#checkout-checkout.bs-co #checkout-confirm .bs-co-src-table .table-responsive,
#checkout-checkout.bs-co #checkout-confirm .bs-confirm-deferred-summary .table-responsive {
  display: none !important;
}

/* Auth nudge: "Авторизуйся" is not a button — no bold */
#checkout-checkout.bs-co .bs-auth-nudge strong { font-weight: 400; }

/* Comment: compact by default (JS autosizes with content) */
#checkout-checkout.bs-co #input-comment {
  min-height: 44px;
  overflow: hidden;
  resize: none;
}

/* Order-summary header: title weight per mock; total only on mobile */
#checkout-checkout.bs-co .bs-co-summary__toggle strong {
  color: var(--bs-ink);
  font-size: 16px;
  font-weight: 800;
}

@media (min-width: 901px) {
  #checkout-checkout.bs-co .bs-co-summary__total { display: none; }
  #checkout-checkout.bs-co [data-rd13-view] .bs-co-totals__subtotal { display: none; }
}

/* Promo "Застосувати": blue outline button per mock */
#checkout-checkout.bs-co .bs-co-promo-input .bs-btn-secondary,
#checkout-checkout.bs-co .bs-co-promo-input button {
  background: #fff;
  border: 1px solid var(--bs-blue);
  border-radius: var(--bs-r-sm);
  color: var(--bs-blue);
  font-weight: 700;
}

#checkout-checkout.bs-co .bs-co-promo-input button:hover {
  background: var(--bs-blue-soft);
}

/* Collapsible cards: працюють і на десктопі */
#checkout-checkout.bs-co .bs-co-card__head { cursor: pointer; }
#checkout-checkout.bs-co .bs-co-card__head--static { cursor: default; }

@media (min-width: 901px) {
  #checkout-checkout.bs-co .bs-co-card__head .bs-co-chevron { display: inline-flex; }
}

/* SVG chevron orientation: expanded = up, collapsed = down (mock) */
#checkout-checkout.bs-co .bs-co-chevron {
  align-items: center;
  color: var(--bs-ink-3);
  display: inline-flex;
  transition: transform .18s ease;
  transform: rotate(180deg);
}

#checkout-checkout.bs-co .bs-co-card.is-collapsed .bs-co-chevron { transform: rotate(0deg); }
#checkout-checkout.bs-co .bs-co-summary .bs-co-chevron { transform: rotate(0deg); }
#checkout-checkout.bs-co .bs-co-summary.is-open .bs-co-chevron { transform: rotate(180deg); }

/* Collapsed card summaries: weights/colors per mock */
#checkout-checkout.bs-co .bs-co-card__title {
  color: var(--bs-ink);
  font-size: 15px;
  font-weight: 700;
}

#checkout-checkout.bs-co .bs-co-card__summary {
  color: var(--bs-ink-3);
  font-size: 12.5px;
  font-weight: 400;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Receiver recap lines */
#checkout-checkout.bs-co .bs-co-recap-sub {
  color: var(--bs-ink-3);
  font-size: 12.5px;
  line-height: 1.5;
  margin-top: 4px;
}

/* Receiver grid children: prevent overflow pushing phone out of its column */
#checkout-checkout.bs-co #form-register > fieldset:first-of-type > .row > * { min-width: 0; }
#checkout-checkout.bs-co #form-register > fieldset:first-of-type > .row input { max-width: 100%; }
/* ==== /RD-13 checkout reskin round 5 ==== */

RD13R5CSSEOT;
}

function rd13r5_main(array $argv): void {
    $root = $argv[1] ?? '.';

    if (str_starts_with($root, '--')) {
        $root = '.';
    }

    $root = rtrim($root, "/\\");

    rd13r5_out('patch_id', RD13R5_PATCH_ID);
    rd13r5_out('site_root', $root);
    rd13r5_lint_self();

    $checkoutPath = rd13r5_path($root, RD13R5_CHECKOUT);
    $cssPath = rd13r5_path($root, RD13R5_CSS);
    $jsPath = rd13r5_path($root, RD13R5_JS);

    $checkout = rd13r5_read($checkoutPath, RD13R5_CHECKOUT);
    $css = rd13r5_read($cssPath, RD13R5_CSS);
    $js = rd13r5_read($jsPath, RD13R5_JS);

    $applied = [
        'twig' => strpos($checkout, RD13R5_VERSION_NEW) !== false,
        'css' => strpos($css, RD13R5_CSS_MARKER) !== false,
        'js' => strpos($js, RD13R5_JS_MARKER) !== false,
    ];

    if ($applied['twig'] && $applied['css'] && $applied['js']) {
        rd13r5_out('already_applied', 'yes');
        rd13r5_out('done', 'ok');

        if (!in_array('--dry-run', $argv, true)) {
            rd13r5_self_delete($argv);
        }

        return;
    }

    if ($applied['twig'] || $applied['css'] || $applied['js']) {
        rd13r5_fail(
            'partially_applied:twig=' . ($applied['twig'] ? '1' : '0')
            . ':css=' . ($applied['css'] ? '1' : '0')
            . ':js=' . ($applied['js'] ? '1' : '0')
            . ':restore_all_three_from_backups_then_rerun'
        );
    }

    rd13r5_out('already_applied', 'no');

    rd13r5_hash_gate($checkout, RD13R5_CHECKOUT_SHA256, RD13R5_CHECKOUT);
    rd13r5_hash_gate($css, RD13R5_CSS_SHA256, RD13R5_CSS);
    rd13r5_hash_gate($js, RD13R5_JS_SHA256, RD13R5_JS);

    $prechecks = [
        'trusted_click_delegation_present' => strpos($checkout, "click.bsSt2b1DeferredConfirm") !== false,
        'double_submit_guard_present' => strpos($checkout, 'bsCheckoutConfirmSubmitting') !== false,
        'deferred_summary_cache_present' => strpos($checkout, 'bs-confirm-deferred-summary') !== false,
        'js_is_round4' => strpos($js, 'RD-13 checkout reskin round 4') !== false,
        'css_has_round4' => strpos($css, 'RD-13 checkout reskin round 4') !== false,
    ];

    foreach ($prechecks as $label => $ok) {
        rd13r5_out('precheck_' . $label, $ok ? 'ok' : 'FAIL');
        if (!$ok) {
            rd13r5_fail('precheck_failed:' . $label);
        }
    }

    $checkoutPatched = rd13r5_replace_once(
        $checkout,
        'catalog/view/stylesheet/boostershop-ds.css?v=' . RD13R5_VERSION_OLD,
        'catalog/view/stylesheet/boostershop-ds.css?v=' . RD13R5_VERSION_NEW,
        'twig_css_version'
    );

    $checkoutPatched = rd13r5_replace_once(
        $checkoutPatched,
        'catalog/view/javascript/checkout-reskin.js?v=' . RD13R5_VERSION_OLD,
        'catalog/view/javascript/checkout-reskin.js?v=' . RD13R5_VERSION_NEW,
        'twig_js_version'
    );

    // r5: tag the cached confirm-summary wrapper so the raw table can never
    // become visible again, regardless of JS timing (guest duplicate fix).
    $checkoutPatched = rd13r5_replace_once(
        $checkoutPatched,
        "return '<div class=\"bs-confirm-deferred-summary\">' + bsCheckoutInitialSummaryHtml + '</div>';",
        "return '<div class=\"bs-confirm-deferred-summary bs-co-src-table\">' + bsCheckoutInitialSummaryHtml + '</div>';",
        'twig_cached_summary_class'
    );

    $cssPatched = rtrim($css, "\n") . "\n" . rd13r5_css_round5_block();
    $jsPatched = rd13r5_new_js();

    $postchecks = [
        'twig_new_version_count_2' => substr_count($checkoutPatched, RD13R5_VERSION_NEW) === 2,
        'twig_old_version_gone' => strpos($checkoutPatched, '?v=' . RD13R5_VERSION_OLD) === false,
        'twig_trusted_click_preserved' => strpos($checkoutPatched, "click.bsSt2b1DeferredConfirm") !== false,
        'twig_src_table_class_added' => strpos($checkoutPatched, 'bs-confirm-deferred-summary bs-co-src-table') !== false,
        'css_round5_marker' => strpos($cssPatched, RD13R5_CSS_MARKER) !== false,
        'css_round4_kept' => strpos($cssPatched, 'RD-13 checkout reskin round 4') !== false,
        'js_round5_marker' => strpos($jsPatched, RD13R5_JS_MARKER) !== false,
        'js_tail_placement_present' => strpos($jsPatched, 'function placeTail') !== false,
        'js_type_radios_present' => strpos($jsPatched, 'function ensureTypeRadios') !== false,
        'js_view_builder_present' => strpos($jsPatched, 'function buildSummaryView') !== false,
        'js_sync_isolation_present' => strpos($jsPatched, 'function safeStep') !== false,
        'js_grid_order_present' => strpos($jsPatched, 'function orderReceiverGrid') !== false,
        'js_stub_tag_kept' => strpos($jsPatched, 'RD13-STUB') !== false,
        'js_no_endpoint_added' => strpos($jsPatched, '$.ajax') === false && strpos($jsPatched, 'fetch(') === false,
        'js_cta_not_moved_out_of_confirm' => strpos($jsPatched, 'bs-button-confirm-deferred') === false,
    ];

    foreach ($postchecks as $label => $ok) {
        rd13r5_out('postcheck_' . $label, $ok ? 'ok' : 'FAIL');
        if (!$ok) {
            rd13r5_fail('postcheck_failed:' . $label);
        }
    }

    if (in_array('--dry-run', $argv, true)) {
        rd13r5_out('write', 'skipped_dry_run');
        rd13r5_out('done', 'dry-run');
        return;
    }

    $backupRoot = rd13r5_path($root, '_patch_backups/' . RD13R5_PATCH_ID . '-' . date('Ymd-His'));
    $backups = [];

    foreach ([RD13R5_CHECKOUT, RD13R5_CSS, RD13R5_JS] as $relative) {
        $backups[$relative] = rd13r5_backup($root, $backupRoot, $relative);
    }

    try {
        rd13r5_atomic_write($checkoutPath, $checkoutPatched, RD13R5_CHECKOUT);
        rd13r5_atomic_write($cssPath, $cssPatched, RD13R5_CSS);
        rd13r5_atomic_write($jsPath, $jsPatched, RD13R5_JS);
    } catch (Throwable $error) {
        rd13r5_restore($backups, $root);
        rd13r5_out('rollback', 'restored_from_backups');
        throw $error;
    }

    foreach ([RD13R5_CHECKOUT => $checkoutPatched, RD13R5_CSS => $cssPatched, RD13R5_JS => $jsPatched] as $relative => $expected) {
        $actual = file_get_contents(rd13r5_path($root, $relative));

        if ($actual !== $expected) {
            rd13r5_restore($backups, $root);
            rd13r5_out('rollback', 'restored_from_backups');
            rd13r5_fail('readback_mismatch:' . $relative);
        }

        rd13r5_out('written_sha256', $relative . ':' . hash('sha256', $expected));
    }

    rd13r5_clear_cache($root);
    rd13r5_out('changed_files', '3');
    rd13r5_out('done', 'ok');
    rd13r5_self_delete($argv);
}

rd13r5_main($argv);
