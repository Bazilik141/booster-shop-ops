<?php
declare(strict_types=1);

/**
 * RD-13 — checkout reskin, round 3 (Claude).
 *
 * Root causes addressed:
 * 1. Rounds 1-2 shipped CSS/JS under the SAME cache-buster (?v=rd13-20260706),
 *    so browsers/hosting cache kept serving round-1 CSS. r3 bumps both to
 *    ?v=rd13r3-20260707.
 * 2. Round-2 P0-1 rule hid .bs-confirm-deferred-summary entirely — that node
 *    CONTAINS the items/totals table, so the order summary lost its content.
 *    r3 narrows the rule to .bs-confirm-method-summary only.
 * 3. New presentation layer in checkout-reskin.js (full file replace):
 *    - buildSummaryView(): renders items / shipping+free-shipping progress /
 *      totals from the stock confirm table (table stays in DOM as data
 *      source, hidden). Summary order now: items → shipping → totals →
 *      promo → comment → agree/opt-in → hint + CTA (flex order, the CTA is
 *      NOT moved out of #checkout-confirm — its delegated click handler
 *      requires it to stay inside).
 *    - relabelShippingMethods()/restyleSavedAddress(): display-only relabel
 *      of NP quote labels, address-mode radios and saved-address options.
 *      Radio values, hidden inputs, data-label and endpoints untouched.
 *
 * No database, controller, payment/shipping endpoint, price calculation,
 * trusted-click gate, double-submit guard, Hutko, Checkbox, or order-status
 * behavior is changed. RD13-STUB free-shipping threshold (2000) preserved.
 *
 * Usage: php RD-13_checkout-reskin-round3_20260707.php [site_root]
 * Rollback: restore the three files from the printed _patch_backups dir and
 * clear system/storage/cache.
 */

const RD13R3_PATCH_ID = 'RD-13_checkout-reskin-round3_20260707';
const RD13R3_CHECKOUT = 'catalog/view/template/checkout/checkout.twig';
const RD13R3_CSS = 'catalog/view/stylesheet/boostershop-ds.css';
const RD13R3_JS = 'catalog/view/javascript/checkout-reskin.js';

const RD13R3_CHECKOUT_SHA256 = '7027bed25956038e8a7c4d98b5a3e0753907ebf3e5c299edd46f451f68f85f59';
const RD13R3_CSS_SHA256 = '0921ec138947ab76d135032969037533907b27335f333dab39ec11ed8e308211';
const RD13R3_JS_SHA256 = '77c6b049923d3bf3a7d2296e1acc3417e2f8b55957ade84c316ba89d83e02148';

const RD13R3_CSS_MARKER = 'RD-13 checkout reskin round 3';
const RD13R3_JS_MARKER = 'RD-13 checkout reskin round 3';
const RD13R3_VERSION_OLD = 'rd13-20260706';
const RD13R3_VERSION_NEW = 'rd13r3-20260707';

function rd13r3_out(string $key, string $value): void {
    echo $key . '=' . $value . PHP_EOL;
}

function rd13r3_fail(string $message): never {
    throw new RuntimeException($message);
}

function rd13r3_path(string $root, string $relative): string {
    return rtrim($root, "/\\") . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function rd13r3_read(string $path, string $relative): string {
    if (!is_file($path)) {
        rd13r3_fail('target_missing:' . $relative);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        rd13r3_fail('read_failed:' . $relative);
    }

    return $content;
}

function rd13r3_replace_once(string $content, string $search, string $replace, string $label): string {
    $count = substr_count($content, $search);

    if ($count !== 1) {
        rd13r3_fail('anchor_count_mismatch:' . $label . ':expected=1:actual=' . $count);
    }

    return str_replace($search, $replace, $content);
}

function rd13r3_hash_gate(string $content, string $expected, string $relative): void {
    $actual = hash('sha256', $content);
    rd13r3_out('preflight_sha256', $relative . ':' . $actual);

    if (!hash_equals($expected, $actual)) {
        rd13r3_fail('sha256_mismatch:' . $relative . ':expected=' . $expected . ':actual=' . $actual);
    }
}

function rd13r3_lint_self(): void {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1';
    $output = [];
    $exit = 1;
    exec($command, $output, $exit);
    rd13r3_out('php_lint_patch_self', 'exit=' . $exit . ';output=' . implode(' ', $output));

    if ($exit !== 0) {
        rd13r3_fail('php_lint_patch_self_failed');
    }
}

function rd13r3_atomic_write(string $path, string $content, string $relative): void {
    $written = file_put_contents($path, $content, LOCK_EX);

    if ($written === false || $written !== strlen($content)) {
        rd13r3_fail('write_failed_or_incomplete:' . $relative);
    }
}

function rd13r3_backup(string $root, string $backupRoot, string $relative): string {
    $source = rd13r3_path($root, $relative);
    $backup = rd13r3_path($backupRoot, $relative);
    $directory = dirname($backup);

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        rd13r3_fail('backup_mkdir_failed:' . $relative);
    }

    if (!copy($source, $backup)) {
        rd13r3_fail('backup_copy_failed:' . $relative);
    }

    rd13r3_out('backup', $relative . ' -> ' . $backup);
    return $backup;
}

function rd13r3_restore(array $backups, string $root): void {
    foreach ($backups as $relative => $backup) {
        $target = rd13r3_path($root, $relative);
        if (is_file($backup)) {
            @copy($backup, $target);
        }
    }
}

function rd13r3_new_js(): string {
    return <<<'RD13R3JSEOT'
/* RD-13 checkout reskin round 3
 * Presentation helpers only. No endpoint, payload, or order-creation changes.
 * r3: custom order-summary view (items/shipping-progress/totals) built from the
 * stock confirm table, clean shipping-method labels, saved-address cleanup.
 */
(function ($) {
  'use strict';

  var root = document.querySelector('[data-rd13-checkout]');
  var observerTimer = 0;
  var syncing = false;

  if (!root || !$) {
    return;
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

    var recaptcha = registerForm.querySelector('[id^="g-recaptcha-"], .g-recaptcha');
    var captchaBlock = recaptcha ? recaptcha.closest('fieldset, .mb-2, .mb-3, .form-group') : null;
    if (captchaBlock && captchaBlock.parentNode !== deliveryBody) {
      captchaBlock.classList.add('bs-co-moved-captcha');
      deliveryBody.appendChild(captchaBlock);
      bindControlsToRegister(captchaBlock);
    }

    var newsletter = document.getElementById('input-newsletter');
    var newsletterBlock = newsletter ? newsletter.closest('.form-check, .mb-2, .mb-3, .form-group') : null;
    if (newsletterBlock && newsletterBlock.parentNode !== deliveryBody) {
      newsletterBlock.classList.add('bs-co-moved-newsletter');
      deliveryBody.appendChild(newsletterBlock);
      bindControlsToRegister(newsletterBlock);
    }
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

  function moveSummaryFields() {
    var tail = document.getElementById('bs-co-summary-tail');

    if (!tail) {
      return;
    }

    [
      document.getElementById('input-comment'),
      document.getElementById('input-checkout-agree'),
      document.getElementById('input-create-account-opt-in')
    ].forEach(function (input) {
      if (!input) {
        return;
      }

      var block = input.closest('.mb-2, .form-check');

      if (block && block.parentNode !== tail) {
        tail.appendChild(block);
      }
    });
  }

  function styleControls() {
    root.querySelectorAll('.bs-checkout-method-option, .bs-checkout-panel-choice .form-check').forEach(function (row) {
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

  // r3: clean shipping-method quote labels (display only — value/data-label untouched).
  var SHIPPING_LABEL_MAP = [
    { match: /поштомат/i, title: 'Нова пошта — поштомат', sub: 'За тарифами Нової пошти' },
    { match: /відділен/i, title: 'Нова пошта — у відділення', sub: 'За тарифами Нової пошти · ~2–3 дні' },
    { match: /кур['ʼ`]?єр|адресна/i, title: 'Нова пошта — курʼєром', sub: 'Адресна доставка' }
  ];

  function cleanShippingLabel(label) {
    var current = text(label);

    if (!current) {
      return null;
    }

    var rule = SHIPPING_LABEL_MAP.find(function (candidate) {
      return candidate.match.test(current);
    });

    return rule || null;
  }

  function relabelShippingMethods() {
    root.querySelectorAll('#bs-shipping-methods .form-check-label').forEach(function (label) {
      if (label.dataset.coRelabelled === '1') {
        return;
      }

      var rule = cleanShippingLabel(label.textContent);

      if (!rule) {
        return;
      }

      label.dataset.coRelabelled = '1';
      setText(label, rule.title);

      var subNode = document.createElement('span');
      subNode.className = 'bs-co-method-sub';
      subNode.textContent = rule.sub;
      label.appendChild(subNode);
    });
  }

  // r3: saved-address presentation cleanup (labels + option text only).
  var ADDRESS_LABEL_MAP = [
    { match: /^Додати нову адресу Нової пошти$/i, title: '+ Інша адреса' },
    { match: /^Я хочу використовувати існуючу адресу$/i, title: 'Збережена адреса' }
  ];

  function restyleSavedAddress() {
    var addressWrap = document.getElementById('checkout-shipping-address');

    if (!addressWrap) {
      return;
    }

    addressWrap.querySelectorAll('.form-check-label').forEach(function (label) {
      if (label.dataset.coRelabelled === '1') {
        return;
      }

      var current = text(label.textContent);
      var rule = ADDRESS_LABEL_MAP.find(function (candidate) {
        return candidate.match.test(current);
      });

      if (!rule) {
        return;
      }

      label.dataset.coRelabelled = '1';
      setText(label, rule.title);
    });

    var select = document.getElementById('input-shipping-address');

    if (select && select.dataset.coCleaned !== '1') {
      Array.prototype.forEach.call(select.options, function (option) {
        var cleaned = text(option.textContent).replace(/^[^,]+,\s*/, '');

        if (cleaned) {
          option.textContent = cleaned;
        }
      });
      select.dataset.coCleaned = '1';
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

  // r3: custom summary view built from the stock confirm table. The table
  // stays in the DOM (hidden) as the data source; only presentation is new.
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
      html += '<div class="bs-co-item">' +
        '<div class="bs-co-item__main"><div class="bs-co-item__title">' + escapeHtml(item.name) + '</div>' +
        '<div class="bs-co-item__qty">× ' + escapeHtml(item.qty) + '</div></div>' +
        '<div class="bs-co-item__price">' + escapeHtml(item.price) + '</div>' +
        '</div>';
    });
    html += '</div>';

    if (shipping) {
      // RD13-STUB: threshold is a temporary constant until the real
      // free-shipping config/backend ships. grep RD13-STUB to remove.
      // Progress is computed off the payable total (post-discount), per the
      // approved V2 spec — falls back to the items subtotal if absent.
      var FREE_SHIP_THRESHOLD = 2000;
      var payableSource = grand || subtotal;
      var payableAmount = payableSource ? parseMoney(payableSource.value) : 0;
      var remaining = Math.max(0, FREE_SHIP_THRESHOLD - payableAmount);
      var percentage = Math.min(100, Math.round((payableAmount / FREE_SHIP_THRESHOLD) * 100));
      var free = remaining <= 0;

      html += '<div class="bs-co-shipblock rd13-stub' + (free ? ' is-free' : '') + '">' +
        '<div class="bs-co-shipblock__row"><span>Доставка</span>' +
        '<span class="bs-co-shipblock__price">' + (free ? 'Безкоштовно' : escapeHtml(shipping.value)) + '</span></div>' +
        '<div class="bs-co-shipblock__msg">' + (free
          ? 'Безкоштовна доставка застосована ✓'
          : 'До безкоштовної доставки лишилось ₴' + formatHryvnia(remaining)) + '</div>' +
        '<div class="bs-co-shipblock__track"><i style="width:' + percentage + '%"></i></div>' +
        '</div>';
    }

    html += '<div class="bs-co-totals">';

    if (subtotal) {
      html += '<div><span>' + escapeHtml(subtotal.label) + '</span><span>' + escapeHtml(subtotal.value) + '</span></div>';
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

  function updateCardSummaries() {
    var receiver = document.querySelector('[data-co-receiver-summary]');
    var delivery = document.querySelector('[data-co-delivery-summary]');
    var first = document.getElementById('input-shipping-novaposhta-firstname') || document.getElementById('input-firstname');
    var last = document.getElementById('input-shipping-novaposhta-lastname') || document.getElementById('input-lastname');
    var phone = document.getElementById('input-telephone');
    var shipping = document.getElementById('input-shipping-method');
    var receiverText = [first && first.value, last && last.value].map(text).filter(Boolean).join(' ');
    var phoneText = phone ? text(phone.value) : '';
    var deliveryText = shipping ? text(shipping.value) : '';
    var deliveryRule = cleanShippingLabel(deliveryText);

    if (deliveryRule) {
      deliveryText = deliveryRule.title;
    }

    setText(receiver, [receiverText, phoneText].filter(Boolean).join(' · '));
    setText(delivery, deliveryText);

    var receiverCard = receiver && receiver.closest('[data-co-card]');
    var deliveryCard = delivery && delivery.closest('[data-co-card]');

    if (receiverCard) {
      receiverCard.dataset.hasData = receiverText && phoneText ? '1' : '0';
    }

    if (deliveryCard) {
      deliveryCard.dataset.hasData = deliveryText ? '1' : '0';
    }
  }

  function initToggles() {
    root.querySelectorAll('[data-co-card-toggle]').forEach(function (button) {
      if (button.dataset.coBound === '1') {
        return;
      }

      button.dataset.coBound = '1';
      button.addEventListener('click', function () {
        if (!window.matchMedia('(max-width: 900px)').matches) {
          return;
        }

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
        if (!window.matchMedia('(max-width: 900px)').matches) {
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
    if (!window.matchMedia('(max-width: 900px)').matches) {
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

  function sync() {
    if (syncing) {
      return;
    }

    syncing = true;
    moveDeliveryFields();
    ensurePromoStub();
    moveSummaryFields();
    styleControls();
    relabelPaymentMethods();
    relabelShippingMethods();
    restyleSavedAddress();
    buildSummaryView();
    updateSummaryMeta();
    updateCardSummaries();
    initToggles();
    applyInitialMobileCollapse();
    syncing = false;
  }

  function scheduleSync() {
    window.clearTimeout(observerTimer);
    observerTimer = window.setTimeout(sync, 40);
  }

  $(root).on('change input', 'input, select, textarea', scheduleSync);
  $(sync);

  if (window.MutationObserver) {
    new MutationObserver(scheduleSync).observe(root, {
      childList: true,
      subtree: true
    });
  }
})(window.jQuery);
RD13R3JSEOT;
}

function rd13r3_css_round3_block(): string {
    return <<<'RD13R3CSSEOT'

/* ==== RD-13 checkout reskin round 3 (presentation only) ==== */
/* Summary body ordering: custom view (items/shipping/totals) → promo/comment/agree → hint + CTA.
   Interactive confirm markup stays inside #checkout-confirm untouched. */
#checkout-checkout.bs-co .bs-co-summary__body {
  display: flex;
  flex-direction: column;
}

#checkout-checkout.bs-co .bs-co-summary__body > [data-rd13-view] { order: 1; }
#checkout-checkout.bs-co .bs-co-summary__body > #bs-co-summary-tail { order: 2; }
#checkout-checkout.bs-co .bs-co-summary__body > #checkout-confirm { order: 3; }

#checkout-checkout.bs-co #checkout-confirm:empty { display: none; }

/* Raw confirm table stays in the DOM as the data source for the view. */
#checkout-checkout.bs-co #checkout-confirm .bs-co-src-hidden { display: none !important; }

/* Items list */
#checkout-checkout.bs-co [data-rd13-view] .bs-co-items {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

#checkout-checkout.bs-co [data-rd13-view] .bs-co-items--scroll {
  max-height: 268px;
  overflow-y: auto;
  padding-right: 4px;
}

#checkout-checkout.bs-co [data-rd13-view] .bs-co-item {
  align-items: center;
  display: flex;
  gap: 12px;
  justify-content: space-between;
}

#checkout-checkout.bs-co [data-rd13-view] .bs-co-item__title {
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  color: var(--bs-ink);
  display: -webkit-box;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.35;
  overflow: hidden;
}

#checkout-checkout.bs-co [data-rd13-view] .bs-co-item__qty {
  color: var(--bs-ink-3);
  font-size: 11px;
  margin-top: 2px;
}

#checkout-checkout.bs-co [data-rd13-view] .bs-co-item__price {
  flex: 0 0 auto;
  font-size: 13.5px;
  font-weight: 700;
}

/* Totals */
#checkout-checkout.bs-co [data-rd13-view] .bs-co-totals {
  margin-top: 12px;
}

#checkout-checkout.bs-co [data-rd13-view] .bs-co-totals > div {
  color: var(--bs-ink-2);
  display: flex;
  font-size: 13.5px;
  justify-content: space-between;
  padding: 3px 0;
}

#checkout-checkout.bs-co [data-rd13-view] .bs-co-totals__discount {
  color: var(--bs-green) !important;
  font-weight: 600;
}

#checkout-checkout.bs-co [data-rd13-view] .bs-co-totals__grand {
  border-top: 1px solid var(--bs-line);
  color: var(--bs-ink);
  font-size: 18px !important;
  font-weight: 800;
  margin-top: 6px;
  padding-top: 10px !important;
}

#checkout-checkout.bs-co [data-rd13-view] .bs-co-shipblock { margin-top: 12px; }

/* Deferred-confirm area: keep only status hint, alerts and the CTA visible. */
#checkout-checkout.bs-co #checkout-confirm .bs-confirm-deferred > p.text-muted {
  color: var(--bs-ink-3) !important;
  font-size: 12.5px;
  margin: 0 0 10px;
}

#checkout-checkout.bs-co #checkout-confirm .bs-confirm-deferred .text-end { text-align: initial !important; }

/* Shipping-method / address presentation cleanup */
#checkout-checkout.bs-co .bs-checkout-inline-status { display: none; }
#checkout-checkout.bs-co #checkout-shipping-address legend { display: none; }
#checkout-checkout.bs-co #checkout-shipping-address br { display: none; }

#checkout-checkout.bs-co .bs-co-method-sub {
  color: var(--bs-ink-3);
  display: block;
  font-size: 12px;
  font-weight: 400;
  margin-top: 2px;
}

#checkout-checkout.bs-co #checkout-shipping-address .bs-np-address-choice {
  background: var(--bs-bg);
  border: 1px solid var(--bs-line);
  border-radius: var(--bs-r-sm);
  margin: 0 0 10px;
  padding: 11px 12px 11px 40px;
}

#checkout-checkout.bs-co #checkout-shipping-address .form-check-label {
  font-size: 13.5px;
  font-weight: 600;
}

#checkout-checkout.bs-co #shipping-existing .form-select {
  border-color: var(--bs-line);
  border-radius: var(--bs-r-sm);
  font-size: 13.5px;
  min-height: 44px;
}

/* Collapse chevrons are a mobile affordance only. */
@media (min-width: 901px) {
  #checkout-checkout.bs-co .bs-co-card__head .bs-co-chevron { display: none; }
}

@media (max-width: 900px) {
  .bs-co-summary:not(.is-open) [data-rd13-view] { display: none; }
}
/* ==== /RD-13 checkout reskin round 3 ==== */

RD13R3CSSEOT;
}

function rd13r3_main(array $argv): void {
    $root = $argv[1] ?? '.';
    $root = rtrim($root, "/\\");

    rd13r3_out('patch_id', RD13R3_PATCH_ID);
    rd13r3_out('site_root', $root);
    rd13r3_lint_self();

    $checkoutPath = rd13r3_path($root, RD13R3_CHECKOUT);
    $cssPath = rd13r3_path($root, RD13R3_CSS);
    $jsPath = rd13r3_path($root, RD13R3_JS);

    $checkout = rd13r3_read($checkoutPath, RD13R3_CHECKOUT);
    $css = rd13r3_read($cssPath, RD13R3_CSS);
    $js = rd13r3_read($jsPath, RD13R3_JS);

    rd13r3_hash_gate($checkout, RD13R3_CHECKOUT_SHA256, RD13R3_CHECKOUT);
    rd13r3_hash_gate($css, RD13R3_CSS_SHA256, RD13R3_CSS);
    rd13r3_hash_gate($js, RD13R3_JS_SHA256, RD13R3_JS);

    // --- Prechecks: guarded behaviors must exist in the source state. ---
    $prechecks = [
        'trusted_click_delegation_present' => strpos($checkout, "click.bsSt2b1DeferredConfirm") !== false,
        'double_submit_guard_present' => strpos($checkout, 'bsCheckoutConfirmSubmitting') !== false,
        'deferred_summary_cache_present' => strpos($checkout, 'bs-confirm-deferred-summary') !== false,
        'guest_oferta_field_present' => strpos($js, 'input-checkout-agree') !== false,
        'account_opt_in_present' => strpos($js, 'input-create-account-opt-in') !== false,
        'js_is_round2' => strpos($js, 'RD-13 checkout reskin round 2') !== false,
        'css_round3_not_applied' => strpos($css, RD13R3_CSS_MARKER) === false,
        'twig_round3_not_applied' => strpos($checkout, RD13R3_VERSION_NEW) === false,
    ];

    foreach ($prechecks as $label => $ok) {
        rd13r3_out('precheck_' . $label, $ok ? 'ok' : 'FAIL');
        if (!$ok) {
            rd13r3_fail('precheck_failed:' . $label);
        }
    }

    // --- 1. checkout.twig: bump cache-busters (fixes stale round-1 CSS). ---
    $checkoutPatched = rd13r3_replace_once(
        $checkout,
        'catalog/view/stylesheet/boostershop-ds.css?v=' . RD13R3_VERSION_OLD,
        'catalog/view/stylesheet/boostershop-ds.css?v=' . RD13R3_VERSION_NEW,
        'twig_css_version'
    );

    $checkoutPatched = rd13r3_replace_once(
        $checkoutPatched,
        'catalog/view/javascript/checkout-reskin.js?v=' . RD13R3_VERSION_OLD,
        'catalog/view/javascript/checkout-reskin.js?v=' . RD13R3_VERSION_NEW,
        'twig_js_version'
    );

    // --- 2. CSS: narrow the round-2 P0-1 rule (regression fix) + append r3 block. ---
    $cssPatched = rd13r3_replace_once(
        $css,
        "/* RD-13 checkout reskin round 2 */\n"
        . "#checkout-checkout.bs-co #checkout-confirm .bs-confirm-deferred-summary,\n"
        . "#checkout-checkout.bs-co #checkout-confirm .bs-confirm-method-summary {\n"
        . "  display: none !important;\n"
        . "}",
        "/* RD-13 checkout reskin round 2 (r3: narrowed — deferred summary holds the items/totals table) */\n"
        . "#checkout-checkout.bs-co #checkout-confirm .bs-confirm-method-summary {\n"
        . "  display: none !important;\n"
        . "}",
        'css_narrow_p01_rule'
    );

    $cssPatched = rtrim($cssPatched, "\n") . "\n" . rd13r3_css_round3_block();

    // --- 3. JS: full replacement with the round-3 file. ---
    $jsPatched = rd13r3_new_js();

    // --- Postchecks on patched content (before any write). ---
    $postchecks = [
        'twig_new_version_count_2' => substr_count($checkoutPatched, RD13R3_VERSION_NEW) === 2,
        'twig_old_version_gone' => strpos($checkoutPatched, '?v=' . RD13R3_VERSION_OLD) === false,
        'twig_trusted_click_preserved' => strpos($checkoutPatched, "click.bsSt2b1DeferredConfirm") !== false,
        'css_round3_marker' => strpos($cssPatched, RD13R3_CSS_MARKER) !== false,
        'css_summary_unhidden' => strpos(
            $cssPatched,
            "#checkout-checkout.bs-co #checkout-confirm .bs-confirm-deferred-summary,\n"
            . "#checkout-checkout.bs-co #checkout-confirm .bs-confirm-method-summary {"
        ) === false,
        'css_mobile_collapse_rules_kept' => strpos($cssPatched, '.bs-co-summary:not(.is-open) #checkout-confirm .bs-confirm-deferred-summary') !== false,
        'js_round3_marker' => strpos($jsPatched, RD13R3_JS_MARKER) !== false,
        'js_view_builder_present' => strpos($jsPatched, 'function buildSummaryView') !== false,
        'js_old_stub_removed' => strpos($jsPatched, 'renderFreeShippingStub') === false,
        'js_stub_tag_kept' => strpos($jsPatched, 'RD13-STUB') !== false,
        'js_no_endpoint_added' => strpos($jsPatched, '$.ajax') === false && strpos($jsPatched, 'fetch(') === false,
        'js_cta_not_moved' => strpos($jsPatched, 'bs-button-confirm-deferred') === false,
    ];

    foreach ($postchecks as $label => $ok) {
        rd13r3_out('postcheck_' . $label, $ok ? 'ok' : 'FAIL');
        if (!$ok) {
            rd13r3_fail('postcheck_failed:' . $label);
        }
    }

    if (in_array('--dry-run', $argv, true)) {
        rd13r3_out('write', 'skipped_dry_run');
        rd13r3_out('done', 'dry-run');
        return;
    }

    // --- Backup + write. ---
    $backupRoot = rd13r3_path($root, '_patch_backups/' . RD13R3_PATCH_ID . '-' . date('Ymd-His'));
    $backups = [];

    foreach ([RD13R3_CHECKOUT, RD13R3_CSS, RD13R3_JS] as $relative) {
        $backups[$relative] = rd13r3_backup($root, $backupRoot, $relative);
    }

    try {
        rd13r3_atomic_write($checkoutPath, $checkoutPatched, RD13R3_CHECKOUT);
        rd13r3_atomic_write($cssPath, $cssPatched, RD13R3_CSS);
        rd13r3_atomic_write($jsPath, $jsPatched, RD13R3_JS);
    } catch (Throwable $error) {
        rd13r3_restore($backups, $root);
        rd13r3_out('rollback', 'restored_from_backups');
        throw $error;
    }

    foreach ([RD13R3_CHECKOUT => $checkoutPatched, RD13R3_CSS => $cssPatched, RD13R3_JS => $jsPatched] as $relative => $expected) {
        $actual = file_get_contents(rd13r3_path($root, $relative));

        if ($actual !== $expected) {
            rd13r3_restore($backups, $root);
            rd13r3_out('rollback', 'restored_from_backups');
            rd13r3_fail('readback_mismatch:' . $relative);
        }

        rd13r3_out('written_sha256', $relative . ':' . hash('sha256', $expected));
    }

    rd13r3_out('changed_files', '3');
    rd13r3_out('done', 'ok');
}

rd13r3_main($argv);
