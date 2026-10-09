/* ============================================================================
 * RD-13.1 · Task B — editable receiver (ПІБ + телефон), AUTHORIZED checkout
 * ----------------------------------------------------------------------------
 * Owner decision: B + B — edited name AND phone apply to THE CURRENT ORDER ONLY.
 *   • Do NOT update the customer account (telephone stays as-is in the profile).
 *   • Do NOT edit/overwrite the saved address book (name is per-order only).
 *
 * INTEGRATION (Codex):
 *   1) In  catalog/view/javascript/checkout-reskin.js  REPLACE the existing
 *      function ensureReceiverRecap() {...}  with the version below (drop-in —
 *      same name, same call site in sync()).
 *   2) Implement window.bsCheckoutSaveReceiver(data) against a server endpoint
 *      that stores the 4 fields as an ORDER-ONLY override (contract at bottom).
 *
 * No CSS is required: the fields reuse .form-control + .bs-co-recipient-field--*
 * which are already styled by boostershop-ds.css. Frontend only — this file is a
 * reference, not something to deploy as-is.
 * ==========================================================================*/

/* -------- 1. Drop-in replacement for ensureReceiverRecap() ---------------- */
function ensureReceiverRecap() {
  // Guest keeps its own editable form-register card; only build for authorized.
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
            '<input type="text" id="bs-co-recv-firstname" class="form-control" placeholder="Ім\'я" autocomplete="given-name">' +
          '</div>' +
          '<div class="col mb-3 required bs-co-recipient-field--last">' +
            '<label class="form-label" for="bs-co-recv-lastname">Прізвище отримувача</label>' +
            '<input type="text" id="bs-co-recv-lastname" class="form-control" placeholder="Прізвище" autocomplete="family-name">' +
          '</div>' +
          '<div class="col mb-3 bs-co-recipient-field--middle">' +
            '<label class="form-label" for="bs-co-recv-middlename">По батькові</label>' +
            '<input type="text" id="bs-co-recv-middlename" class="form-control" placeholder="Необов\'язково" autocomplete="additional-name">' +
          '</div>' +
          '<div class="col mb-3 required bs-co-recipient-field--phone">' +
            '<label class="form-label" for="bs-co-recv-telephone">Телефон</label>' +
            '<input type="tel" id="bs-co-recv-telephone" class="form-control" placeholder="Телефон" autocomplete="tel" inputmode="tel">' +
          '</div>' +
        '</div>' +
        '<div class="bs-co-recap-sub" data-co-receiver-address></div>' +
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

  // -- prefill once (never clobber what the customer is typing) --------------
  if (recap.dataset.coRecvBuilt !== '1' && first && last && phone) {
    recap.dataset.coRecvBuilt = '1';

    var source = select.dataset.coNameCache || '';
    if (!source) {
      source = text((select.options[select.selectedIndex] || select.options[0]).textContent).split(',')[0];
    }
    var nameParts = text(source).split(' ').filter(Boolean);
    first.value = nameParts.shift() || '';
    last.value = nameParts.join(' ');
    phone.value = text(root.getAttribute('data-bs-customer-phone'));

    var pushEdit = function () {
      var f = text(first.value);
      var l = text(last.value);
      var m = text(middle ? middle.value : '');
      var p = text(phone.value);

      // Feed the existing summary pipeline (collapsed one-liner updates itself).
      select.dataset.coNameCache = [f, l].filter(Boolean).join(' ');
      root.setAttribute('data-bs-customer-phone', p);

      // Persist as an order-only override once the required fields are present.
      if (f && l && p && typeof window.bsCheckoutSaveReceiver === 'function') {
        window.bsCheckoutSaveReceiver({ firstname: f, lastname: l, middlename: m, telephone: p });
      }
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

  // -- refresh the read-only address sub-line every sync ---------------------
  var addressLine = recap.querySelector('[data-co-receiver-address]');
  var selectNow = savedAddressSelect();
  var fullOption = selectNow && selectNow.selectedOptions[0] ? selectNow.selectedOptions[0].dataset.coFullText : '';
  setText(addressLine, fullOption || selectedSavedAddressText());
}

/* -------- 2. Save hook (Codex wires the endpoint) ------------------------- */
/* Place anywhere inside the checkout-reskin.js IIFE (or on window). Leaving the
 * stub in prevents silent breakage if the endpoint isn't wired yet. */
if (typeof window.bsCheckoutSaveReceiver !== 'function') {
  window.bsCheckoutSaveReceiver = function (data) {
    // TODO(Codex): POST { firstname, lastname, middlename, telephone } to an
    // ORDER-ONLY override endpoint. Must NOT update account or address book.
    if (window.console && console.warn) {
      console.warn('[RD-13.1] bsCheckoutSaveReceiver not wired yet', data);
    }
  };
}

/* ============================================================================
 * BACKEND CONTRACT for window.bsCheckoutSaveReceiver(data)  — B + B
 * ----------------------------------------------------------------------------
 * data = { firstname, lastname, middlename, telephone }  (all trimmed strings)
 *
 * Behaviour required:
 *   • Store these on the ORDER / checkout session only (per-order override).
 *   • The deferred confirm (checkout/confirm.confirm) MUST pick them up so the
 *     created order uses the edited recipient name + phone.
 *   • DO NOT call account edit — profile telephone stays unchanged.
 *   • DO NOT edit the selected saved address — address book stays unchanged.
 *
 * Suggested response: { "success": 1 }  or  { "error": { ...field: msg } }.
 * On error, surface the message near the field (ids: #bs-co-recv-firstname etc.)
 * — mirror the guest surfaceRegisterErrors() pattern if convenient.
 *
 * Note: name/phone edits do NOT affect shipping quotes, so no method reset is
 * needed after saving (unlike the address autosave path).
 * ==========================================================================*/
