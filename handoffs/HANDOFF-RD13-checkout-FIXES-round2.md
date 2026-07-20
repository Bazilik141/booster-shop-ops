# RD-13 — Checkout reskin · Round 2 fix list (post-implementation review)

_Reviewed: 06.07.2026 · Source of the bugs: `uploads/RD-13_checkout-reskin_20260706.php`
(Codex's actual patch script, read in full) cross-checked against the approved
mockup (`RD-13 Checkout reskin.html` / `rd13-checkout.jsx`) and the manager's
annotated screenshots of the live result._

**How to read this doc:** every fix below references the *real* selectors,
IDs, and function names from your own patch script — not the mockup's
fictional ones. Apply directly; nothing here requires redesigning anything.
Grouped by: **P0** (real bugs/regressions, fix before next deploy), **P1**
(needs a decision from the manager before you touch code — don't guess),
**P2** (pre-existing, not caused by this patch — flag separately, don't fix
here).

Good news first: the approach of wrapping the stock `register` /
`payment_address` / `shipping_address` / `shipping_method` / `payment_method`
/ `confirm` partials instead of rebuilding them was the right call for a
HIGH-RISK, logic-untouched ticket — that's exactly why the guardrails in
§1 of the original handoff exist. Most of what's below is friction from that
approach (opaque partials don't line up with my 4-card boundaries), not
carelessness. Fixable without breaking the "don't touch payment/shipping
logic" rule.

---

## P0 — real bugs, fix before next deploy

### P0-1. Duplicate shipping/payment recap inside "Замовлення"
**Screenshot:** the big red X — a second, non-interactive "Доставка" +
"Оплата" block appears again below/inside the order summary, repeating what
the live Доставка/Оплата cards above already show.

**Root cause:** `{{ confirm }}` (stock deferred-confirm partial) renders its
own internal recap — your CSS already targets it:
```css
.bs-co-summary:not(.is-open) #checkout-confirm .bs-confirm-deferred-summary,
.bs-co-summary:not(.is-open) #checkout-confirm .bs-confirm-method-summary { display: none; }
```
but this is scoped inside `@media (max-width:900px)` AND `:not(.is-open)` —
so it only hides on mobile while collapsed. On desktop, and on mobile once
expanded, the duplicate recap is visible.

**Fix:** hide both classes unconditionally, all breakpoints/states — replace
the scoped rule with:
```css
#checkout-checkout.bs-co #checkout-confirm .bs-confirm-deferred-summary,
#checkout-checkout.bs-co #checkout-confirm .bs-confirm-method-summary {
  display: none !important;
}
```
Leave the existing `.bs-confirm-deferred > h2` hiding as-is. **Do not**
blanket-hide `.bs-confirm-deferred > p` the same way — that paragraph looks
like the stock "Оберіть спосіб оплати, щоб оформити замовлення" status line
that swaps for the submit button once a method is picked; that's functional
progressive disclosure, not a duplicate — leave its existing mobile-only
hide rule alone.

### P0-2. Mobile: "Замовлення" card sinks to the very bottom of the page
**Screenshot:** the long red arrow spanning from "Отримувач" at the top all
the way down to "Замовлення · 1 товар ₴750.00" at the very bottom.

**Root cause:** in `$newShell`, `.bs-co-col` (Отримувач/Доставка/Оплата)
comes before `.bs-co-aside` (Замовлення) in source order. `.bs-co-aside`
becomes `position: static` on mobile, so it renders where the DOM puts it —
after Оплата, at the page bottom. Per the approved mock, the collapsed
"Замовлення" pill should sit near the top (right after the auth nudge).

**Fix — CSS only, no twig/DOM change.** Add `order` to the two existing
children inside your current mobile breakpoint:
```css
@media (max-width: 900px) {
  .bs-co-grid { grid-template-columns: 1fr; }
  .bs-co-col   { order: 2; }
  .bs-co-aside { order: 1; position: static; }
}
```
**Known, accepted tradeoff:** because `{{ confirm }}` is one opaque partial
(items + totals + promo + comment + agree + CTA all fused — same thing my
original handoff flagged in its "mobile ordering caveat"), you can't split
the "items/totals" half from the "promo/comment/agree/CTA" half without
touching the controller. This fix moves the **whole** card up, bundled. That
is an acceptable compromise, not a bug — confirming so you don't spend time
trying to split it further.

### P0-3. Product name in the order summary renders as a live, underlined blue link
**Screenshot:** "1x Містері бокс One Piece Card Game: Mystery Mix..." in
blue/underlined link style inside the confirm table.

**Fix — restyle only, keep it a real link (don't remove the `href`):**
```css
#checkout-checkout.bs-co #checkout-confirm tbody a {
  color: var(--bs-ink);
  font-weight: 600;
  text-decoration: none;
}
#checkout-checkout.bs-co #checkout-confirm tbody a:hover {
  color: var(--bs-blue);
  text-decoration: underline;
}
```

### P0-4. Item list has no height cap — grows unbounded with cart size
This was an explicit, already-approved requirement (cap ~3 rows, scroll
past that) that isn't in the patch at all — there's no rule targeting
`#checkout-confirm tbody` for height.

**Fix:**
```css
#checkout-checkout.bs-co #checkout-confirm tbody {
  display: block;
  max-height: 268px;
  overflow-y: auto;
}
#checkout-checkout.bs-co #checkout-confirm tbody tr {
  display: table;
  width: 100%;
  table-layout: fixed;
}
```
**Verify after applying:** this is the standard "scrollable tbody" CSS
pattern, but your real table's column count/widths aren't visible to me from
screenshots — check columns stay aligned after this change; if they drift,
match each `tr`'s cell widths to the (hidden) `thead`'s via an explicit
`<colgroup>` or per-cell `width`.

### P0-5. Stock totals labels don't match approved copy
Stock table shows **"Сума"** / **"Разом"**; approved copy is **"Сума
товарів"** / **"До сплати"** (see original handoff §3 copy table). Renaming
in the controller/language file is out of scope, so do it the same way you
already solved this for card summaries — a small text-substitution in
`checkout-reskin.js`:
```js
function relabelTotals() {
  root.querySelectorAll('#checkout-confirm tfoot tr').forEach(function (row) {
    var label = row.querySelector('td:first-child');
    if (!label) return;
    var t = text(label.textContent);
    if (/^Сума$/i.test(t))   setText(label, 'Сума товарів');
    if (/^Разом$/i.test(t))  setText(label, 'До сплати');
  });
}
```
Call `relabelTotals()` inside `sync()`, next to `updateSummaryMeta()`. Pure
presentation text-swap — same risk profile as the rest of `checkout-reskin.js`.

### P0-6. Address/captcha/newsletter/"інша особа" fields render inside "Отримувач" instead of "Доставка"
**Screenshots:** Область/Місто/Тип доставки/Відділення, the captcha, the
newsletter toggle, and the "Отримувач — інша особа" checkbox all appear
stacked inside the **Отримувач** card; the **Доставка** card ends up with
almost nothing of its own.

**Root cause:** stock `payment_address` (which your twig routes into the
Отримувач card via `{% if register or payment_address %}`) is OpenCart's
monolithic address-step partial — it bundles recipient-differs-from-account,
the full address cascade, captcha, and newsletter into one opaque blob,
because stock OpenCart doesn't share my 4-card boundary at all. This is not
fixable by CSS alone since these are the same opaque-partial-boundary problem
as P0-1/P0-2.

**Fix — apply the same DOM-move technique you already use in
`moveSummaryFields()`, one level deeper.** Add a sibling function:
```js
function moveDeliveryFields() {
  var deliveryBody = document.querySelector('[data-co-card="delivery"] .bs-co-card__body');
  if (!deliveryBody) return;

  // TODO confirm real selectors via devtools — these are the stock
  // Nova-Poshta / address-cascade fields currently rendered inside
  // #checkout-payment-address. Likely candidates (verify against DOM):
  //   region/zone select      → #input-zone-id or .bs-np-zone
  //   city input              → #input-city or .bs-np-city
  //   delivery-type select    → .bs-np-delivery-type
  //   branch/postomat input   → #input-shipping-address-2 or .bs-np-branch
  //   captcha wrapper         → .g-recaptcha, or its containing .form-group
  //   newsletter checkbox     → #input-newsletter (label.closest('.form-check'))
  ['[data-np-zone]', '[data-np-city]', '[data-np-delivery-type]', '[data-np-branch]',
   '.g-recaptcha', '#input-newsletter'
  ].forEach(function (sel) {
    var el = document.querySelector(sel);
    if (!el) return;
    var block = el.closest('.mb-2, .form-group, .form-check') || el;
    if (block.parentNode !== deliveryBody) deliveryBody.appendChild(block);
  });
}
```
Call it from `sync()` next to `moveSummaryFields()`. **The bracketed
selectors are placeholders** — I don't have DOM access to the live
`payment_address` partial's real markup, only screenshots. Inspect the real
element IDs/classes in devtools, swap them in, keep the move-pattern as-is.
Leave name/phone/email/"інша особа" toggle where they are (Отримувач is
correct for those).

### P0-7. "Отримувач — інша особа" toggle is unstyled
Your CSS already has a stub selector for it (`.bs-recipient-toggle` in the
`bs-np-address-choice, bs-np-address-panel, bs-recipient-toggle` rule) but it
only sets border-radius/border-color — screenshot shows it still looks like
raw unstyled stock markup (plain checkbox + paragraph, no card treatment).

**Fix — give it the same callout treatment as the rest of the system:**
```css
#checkout-checkout.bs-co .bs-recipient-toggle {
  background: var(--bs-bg);
  border: 1px solid var(--bs-line);
  border-radius: var(--bs-r-sm);
  padding: 13px 14px;
  margin: 4px 0 2px;
}
#checkout-checkout.bs-co .bs-recipient-toggle small,
#checkout-checkout.bs-co .bs-recipient-toggle p {
  color: var(--bs-ink-3);
  font-size: 12px;
  margin: 4px 0 0;
}
```
(If `.bs-recipient-toggle` isn't actually the wrapping element around that
checkbox+helper-text pair in the real DOM, confirm the right selector in
devtools first — same caveat as P0-6.)

### P0-8. Remove `bsSt2b6Log` — confirmed temporary debug tracer, not a fraud gate
**Resolved:** confirmed with product — `bsSt2b6Log` was a temporary tracer
added to catch a specific past bug. Remove it.

**Before deleting, separate two things that may be bundled under the
`ST-2b.6` name in the source:**
1. The **logging/tracer calls** themselves (`bsSt2b6Log(...)`, any
   `console.*` under the `[ST-2b.6 diagnostic]` tag) — **delete these.**
2. Any **functional** behavior the same code path performs beyond writing to
   console (e.g. actually gating/enabling the submit button, deduping
   double-submits, delaying activation until a trust flag is set) — if it
   does anything beyond logging, **keep that part**. Only the
   diagnostic/log output is confirmed temporary — the checkout submit button
   still needs whatever real double-submit protection existed before.

Your own patch's precheck currently asserts this string must stay present:
```php
'trusted_click_preserved' => strpos($checkoutPatched, 'ST-2b.6d: trusted deferred-confirm activation gate.') !== false,
```
If, after inspecting the real code, that whole block turns out to be
**only** the diagnostic tracer with no separate functional gate underneath,
remove this precheck line along with the tracer — don't leave a precheck
asserting the presence of code you just deleted. If there IS a functional
part, keep the precheck (or repoint it at whatever marker now identifies the
surviving functional code) and only strip the logging calls.

**Mandatory:** this touches confirm-button activation — re-run the full
smoke test (original handoff §7) after removal. Specifically confirm the
submit button still can't be double-clicked/double-submitted, and a real
order still completes end-to-end for every payment method.

---

## P1 — decisions received this round

### P1-1. Payment method copy — RESOLVED: keep it, it's correct
Confirmed final: *Картка, Google Pay / Apple Pay* (sub: *Безпечно через
еквайринг*) / *Оплата при отриманні (накладений платіж)* / *За реквізитами
на IBAN*. Implement it against the real methods.

**Preferred fix — rename at the payment-module config level**, if your admin
lets you set a custom title (and description/subtext, if supported) per
method. Zero frontend risk, nothing to keep in sync in JS.

**Fallback — if config-level renaming isn't available before deploy**, a
presentation-only JS remap, same pattern as `relabelTotals()`:
```js
var PAYMENT_LABEL_MAP = [
  { match: /^Оплата (при доставці|при отриманні)\s*\(накладений платіж\)$/i,
    title: 'Оплата при отриманні (накладений платіж)', sub: null },
  { match: /^Оплата карткою через Hutko$/i,
    title: 'Картка, Google Pay / Apple Pay', sub: 'Безпечно через еквайринг' },
  { match: /^Банківський переказ$/i,
    title: 'За реквізитами на IBAN', sub: null },
];

function relabelPaymentMethods() {
  root.querySelectorAll('#checkout-payment-method .form-check-label').forEach(function (label) {
    if (label.dataset.coRelabelled === '1') return;
    var current = text(label.textContent);
    var rule = PAYMENT_LABEL_MAP.find(function (r) { return r.match.test(current); });
    if (!rule) return;
    label.dataset.coRelabelled = '1';
    setText(label, rule.title);
    if (rule.sub) {
      var subNode = document.createElement('span');
      subNode.className = 'bs-co-payment-sub';
      subNode.textContent = rule.sub;
      label.appendChild(subNode);
    }
  });
}
```
```css
.bs-co-payment-sub { display: block; font-size: 12px; font-weight: 400; color: var(--bs-ink-3); margin-top: 2px; }
```
Call `relabelPaymentMethods()` in `sync()`. **Verify the real
`.form-check-label` markup** before wiring in — I'm inferring the selector
from your existing CSS (`.bs-checkout-panel-choice .form-check`), not live
DOM; if the module already renders its own description text under the
title, adjust so you don't duplicate it.

**Still separate, still P2:** the duplicate unlabelled 4th "Оплата при
доставці" entry doesn't match any of the 3 approved methods and won't be
touched by this remap (regex requires the "(накладений платіж)" suffix it's
missing) — it needs the payment-module dedup fix, independent of this work.

### P1-2. Free-shipping progress + FIRST15 promo — RESOLVED: ship a temporary stub now
Backend for both confirmed **not ready**; a follow-up ticket wires the real
config/endpoint, at which point this stub gets removed wholesale. Everything
below is tagged `RD13-STUB` in comments specifically so it's grep-able for
clean removal later — when that ticket lands, `grep -rn RD13-STUB` and
delete every match as one unit.

**A. Free-shipping progress — ship as a REAL (not fake) display.** The
*subtotal* is real (already on the page, read from the DOM); only the
*threshold* is a temporary hardcoded constant. This transforms the existing
shipping row inside `#checkout-confirm tfoot` into the merged
shipping+progress block from the approved mock:
```js
function renderFreeShippingStub() {
  var FREE_SHIP_THRESHOLD = 2000; // RD13-STUB — delete this constant and
                                    // read the real config value once the
                                    // free-shipping backend ships.
  var rows = root.querySelectorAll('#checkout-confirm tfoot tr');
  if (rows.length !== 3) return; // unexpected structure (e.g. a discount
                                   // row already present) — bail safely,
                                   // leave stock totals untouched rather
                                   // than risk corrupting the layout.

  var subtotalRow = rows[0], shippingRow = rows[1];
  if (shippingRow.dataset.rd13Stub === '1') return;

  var subtotalCells = subtotalRow.querySelectorAll('td');
  var subtotal = parseInt(text(subtotalCells[subtotalCells.length - 1].textContent).replace(/\D/g, ''), 10) || 0;
  var remaining = Math.max(0, FREE_SHIP_THRESHOLD - subtotal);
  var pct = Math.min(100, Math.round((subtotal / FREE_SHIP_THRESHOLD) * 100));
  var done = remaining <= 0;

  var shipCells = shippingRow.querySelectorAll('td');
  var shippingLabel = text(shipCells[0].textContent);
  var shippingValue = text(shipCells[shipCells.length - 1].textContent);

  var wrap = document.createElement('div');
  wrap.className = 'bs-co-shipblock rd13-stub' + (done ? ' is-free' : '');
  wrap.innerHTML =
    '<div class="bs-co-shipblock__row"><span>' + shippingLabel + '</span>' +
    '<span class="bs-co-shipblock__price">' + (done ? 'Безкоштовно' : shippingValue) + '</span></div>' +
    '<div class="bs-co-shipblock__msg">' + (done
      ? 'Безкоштовна доставка застосована ✓'
      : 'До безкоштовної доставки лишилось ₴' + remaining) + '</div>' +
    '<div class="bs-co-shipblock__track"><i style="width:' + pct + '%"></i></div>';

  var td = document.createElement('td');
  td.colSpan = shipCells.length;
  shippingRow.innerHTML = '';
  shippingRow.appendChild(td);
  td.appendChild(wrap);
  shippingRow.dataset.rd13Stub = '1';
}
```
Call `renderFreeShippingStub()` in `sync()`, after `relabelTotals()` (order
between the two doesn't actually matter — `relabelTotals()` never matches
the shipping row's label — but keep them adjacent for readability).
```css
/* RD13-STUB — remove this whole block together with renderFreeShippingStub() */
.bs-co-shipblock { padding: 12px 14px; border-radius: var(--bs-r-sm); background: var(--bs-blue-soft); margin: 6px 0; }
.bs-co-shipblock.is-free { background: #EAF7EE; }
.bs-co-shipblock__row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 9px; }
.bs-co-shipblock__row span:first-child { font-size: 13px; font-weight: 600; color: var(--bs-ink-2); }
.bs-co-shipblock__price { font-size: 14.5px; font-weight: 800; color: var(--bs-ink); }
.bs-co-shipblock.is-free .bs-co-shipblock__price { color: var(--bs-green); }
.bs-co-shipblock__msg { font-size: 12px; font-weight: 600; color: var(--bs-blue); margin-bottom: 8px; }
.bs-co-shipblock.is-free .bs-co-shipblock__msg { color: var(--bs-green); }
.bs-co-shipblock__track { height: 5px; background: #fff; border-radius: 999px; overflow: hidden; }
.bs-co-shipblock__track i { display: block; height: 100%; background: var(--bs-blue); border-radius: 999px; }
.bs-co-shipblock.is-free .bs-co-shipblock__track i { background: var(--bs-green); }
```

**B. Promo/coupon (FIRST15) — visual stub only, no fake network call.**
There is no apply endpoint yet, so "Застосувати" must never silently do
nothing or error. Add the input+button per the approved design; on click,
show an inline "coming soon" message instead of submitting anything:
```html
<!-- RD13-STUB: replace with real coupon-apply wiring once the endpoint ships -->
<div class="bs-field">
  <label>Промокод</label>
  <div class="bs-co-promo-input">
    <input class="bs-input" name="rd13_stub_coupon" placeholder="Введіть промокод">
    <button type="button" class="bs-btn bs-btn-secondary" data-co-promo-stub>Застосувати</button>
  </div>
  <div class="bs-co-field-hint" data-co-promo-stub-msg hidden>Промокоди зʼявляться незабаром</div>
</div>
```
```js
// RD13-STUB — delete this handler once real coupon endpoint exists
root.querySelectorAll('[data-co-promo-stub]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var msg = btn.closest('.bs-field').querySelector('[data-co-promo-stub-msg]');
    if (msg) msg.hidden = false;
  });
});
```
```css
.bs-co-promo-input { display: flex; gap: 8px; }
.bs-co-field-hint { margin-top: 6px; font-size: 12px; color: var(--bs-ink-3); }
```
Place this block inside `#bs-co-summary-tail`, before the comment field —
it'll land there automatically since `moveSummaryFields()` already relocates
the tail's contents there; just make sure this markup renders inside
`#checkout-confirm`'s DOM (or append it into `#bs-co-summary-tail` directly
from twig, ahead of where JS moves the comment/agree fields).

**Do not** wire this to submit, reload, or affect totals — it's a static
placeholder until the backend ticket lands. One open flag: this ships
visible-but-inert to real customers until the backend exists — if that's not
desired, the same markup supports hiding it instead (just don't render the
block, skip straight to the comment field) — your call, said here so it's a
deliberate choice rather than an oversight.

### P1-3. Shipping price showing ₴0.00 for "Нова пошта: доставка у відділенні"
Could be a real free-shipping condition legitimately met by that test cart,
or a genuine calculation issue — either way it's shipping-price calculation,
explicitly out of this ticket's scope ("markup/CSS only... shipping-price
calculation... not touched"). Flag to whoever owns that logic; don't
investigate inside this ticket.

---

## P2 — pre-existing, not caused by this patch, separate ticket

- **Duplicate "Оплата при доставці" entry** in the payment method list (two
  identical options) — looks like a pre-existing payment-module config
  issue, unrelated to CSS/markup.
- **Breadcrumb says "Оформлення замовлення", H1 says "Оформити
  замовлення"** — two different words for the same page. Breadcrumb text
  comes from the controller/`breadcrumbs` array (language string), which
  this ticket correctly left untouched (`controller_model_changes: none`).
  Worth a copy-consistency fix, but as its own small ticket, not RD-13.

---

## Reference

| File | What it is |
|---|---|
| `uploads/RD-13_checkout-reskin_20260706.php` | The actual patch script executed — every selector/ID/function name above is taken from here. |
| `handoff/HANDOFF-RD13-checkout.md` | Original approved spec — still the source of truth for copy, layout, and behaviour intent. |
| `RD-13 Checkout reskin.html` / `rd13-checkout.jsx` | Approved mockup — note P1-1 means its payment-method copy is illustrative, not literal. |
