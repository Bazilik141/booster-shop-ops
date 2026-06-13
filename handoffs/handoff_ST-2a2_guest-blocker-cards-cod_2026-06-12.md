# Codex Handoff — ST-2a.2: guest checkout blocker + method cards CSS + COD rename

Date: 2026-06-12. Parent: ST-2 Path B, after st2a1 + st2a3 (index) executed. Stock route only, clients on SimpleCheckout until 2c.

## 1. Task ID
ST-2a.2 — five items: B1 guest blocker (diagnose-first), B2 method-cards layout, B3 Pinta COD rename, B4 poshtomat save fail (diagnose-first), B5 TTN button diagnostics (read-only).

## 2. Context
st2a1 executed 2026-06-12. Logged-in flow works end-to-end (saved address → auto shipping → payment radios → totals). Guest flow stalls. Index st2a3 applied (`city_ref`), EXPLAIN ref — leave as is, no further index work in this patch.

---

## B1 — Guest: shipping methods never load (BLOCKER)

### Exact reproduction (owner QA, 2026-06-12)
1. Anonymous browser → add product → `index.php?route=checkout/checkout`.
2. Default «Оформити замовлення без реєстрації» active. Fill E-Mail (`#input-email`).
   NOTE: telephone field is NOT visible on the page (see suspect S2).
3. NP block: Ім'я/Прізвище отримувача typed; Область «Дніпропетровська» — picked from dropdown; Місто «Дніпро» — picked from dropdown; Тип «Відділення»; «Відділення №88: вул. Савкіна, 2» — picked from dropdown. All via dropdown (data-ref set).
4. Result: «Спосіб доставки» panel forever shows «Після адреси покажемо доступну доставку». No error anywhere. «Підтвердження замовлення» idle.

### Diagnostic order (do 1→3 BEFORE coding the fix)
1. **Does `checkout/register.save` fire at all?** (Network tab / access log while reproducing.)
   - If NO: client-side `registerIsComplete()` returns false — log which condition fails (firstOk/lastOk/emailOk/phoneOk/passwordOk/agreeOk + bsCheckoutNpIsComplete). Likely candidates: agree element state; phone element queried as `#input-telephone` but absent.
   - If YES: capture the **full JSON response** — the current JS shows nothing on error; the answer is in `json.error`.
2. **Suspect S1 — reCAPTCHA**: events `captcha_ps_google_recaptcha` (ids 120/124) are bound to `catalog/view/checkout/register/before`. Check whether `register.save` validates captcha server-side; programmatic AJAX submit has no captcha token → silent `error.captcha`. If confirmed: integrate invisible-captcha token into the autosave submit OR (owner decision, report first) exempt captcha for checkout register route.
3. **Suspect S2 — telephone**: telephone input not rendered for guest (config_telephone_display?), but `register.save` may still require it (`config_telephone_required`). If confirmed: render telephone field in register form (owner wants phone ALWAYS required for NP — see plan §ST-2 п.8 / 2b F-item: phone mandatory). Add the field visibly, required, with `autocomplete="tel"`.
4. **Suspect S3 — shipping_zone_id**: empty if client-side area→zone text-match failed. Check actual POST payload. If flaky: resolve zone server-side (Pinta model maps NP area→zone via `getByZoneId`/area table) instead of client text-match.

### Fix requirements
- Root-cause fix per diagnostics above.
- **Error surfacing (mandatory regardless of root cause):** every `json.error` key from register.save must become visible: field errors on visible inputs as usual; errors for HIDDEN inputs (shipping_*, zone, captcha, telephone-if-hidden) → plain text in `[data-bs-register-status]` line, e.g. «Не вдалося зберегти: …». Never silent.
- After successful save: existing flow (load shipping methods, autoSelect) is already wired — do not duplicate.

### AC (B1)
1. Guest per reproduction above completes order to admin (any payment, no live charge).
2. Any register.save error is visible on screen (test by submitting with broken email server-side if needed).
3. Logged-in flow unchanged.

---

## B2 — Method cards render outside panel (visual)

### Exact reproduction
Logged-in stock checkout (or guest after B1 fix): «Спосіб доставки» / «Спосіб оплати» panels — radio cards render as a NARROW (~120-150px) vertical column at the far RIGHT EDGE, visually outside/overflowing the panel border (desktop ~1900px). Panel body itself stays empty; status text («Доставку обрано.») renders inside panel correctly. Screenshots from owner QA confirm both panels affected.

### Direction
Inspect live computed styles on `#bs-shipping-methods .form-check`. Suspected interaction: r11b rule `#checkout-checkout .bs-checkout-panel-choice .form-check` (`position:relative; padding:12px 14px 12px 42px`) is fine, so look for float/width/absolute inherited from `.bs-checkout-panel-choice` column rules or `form-check` Bootstrap + `:has()` selector fallout. Fix in checkout.twig CSS (ST-2a1 style block), candidates:
```
#checkout-checkout .bs-checkout-inline-methods { display:grid; grid-template-columns: 1fr; gap:10px; width:100%; }
#checkout-checkout .bs-checkout-inline-methods .form-check { width:100%; margin:0; float:none; }
```
…but confirm the actual culprit via devtools first; do not stack blind overrides.

### AC (B2)
Cards full-width inside both panels, desktop + mobile ≤480px; selected card highlight still works.

---

## B3 — Pinta COD rename (display only)

File: `extension/PintaNovaPoshtaCod/catalog/model/payment/pinta_nova_poshta_cod.php`.
Replace display name `'Нова Пошта Накладений платіж'` → `'Оплата при доставці (накладений платіж)'` in BOTH methods (`getMethods` + `getMethod`; the 'Нова Пошта' group label in getMethods → `'Оплата при доставці (накладений платіж)'` as well, або залиш групу — на розсуд по тому, як рендериться список).
**Codes MUST NOT change** (`pinta_nova_poshta_cod`, `pinta_nova_poshta_cod.pinta_nova_poshta_cod`) — TTN logic in `internet_document.php` and order history depend on them.
Owner separately disables stock COD in admin (no code).

### AC (B3)
Payment list shows «Оплата при доставці (накладений платіж)»; admin TTN button still renders on a pinta-COD order; old orders unaffected.

---

## B4 — Poshtomat address fails server-side validation (logged-in, NEW address)

### Exact reproduction (owner QA, 2026-06-12)
Logged-in, stock route, «Додати нову адресу Нової пошти»: Дніпропетровська (dropdown) → Дніпро (dropdown) → Тип «Поштомат» → typed «49489» → dropdown suggested «Поштомат "Нова Пошта" №49489: вул. Савкіна, 6 (Біля під'їзду №19, перед аркою)» → PICKED from dropdown → field turns red, status «Перевірте поля адреси», shipping/payment panels show «Потрібний спосіб доставки!». Note: methods HAD loaded earlier (saved-address preselect) — cascade failure starts at address save.

### Diagnosis direction
Server returns `json.error['novaposhta_warehouse-address']` → `createNovaPoshtaAddress` warehouse lookup failed. Capture the actual POST payload + run lookup manually. Suspects, in order:
1. **Whitespace mismatch**: client `normalizeText()` collapses multiple spaces in the dropdown label; `getByName()` does EXACT `description = '...'` match — if DB description contains double spaces / non-breaking spaces, exact match fails for the cleaned label. Compare raw DB `description` of poshtomat 49489 vs POSTed string byte-by-byte.
2. Quotes/apostrophes in description («"Нова Пошта"», «під'їзду») surviving the round-trip (HTML→input value→POST→SQL escape).
3. type=poshtoma branch reaching the correct validation path (r135 added 'poshtoma' to model in_array sites — verify the path used by `createNovaPoshtaAddress` for logged-in save specifically).
### Fix direction (after diagnosis)
Robust matching: prefer lookup **by `ref`** — the dropdown click already has `data-ref` (li attr); POST it as `shipping_novaposhta_warehouse_ref` hidden field and let server resolve by ref with name-match fallback. This kills the whole class of string-mismatch bugs (applies to city/area/street too if cheap to add now — else note for 2b).
### AC (B4)
Logged-in + guest: poshtomat 49489 picked from dropdown → address saves, NP method quotes load, order completes. Відділення/адресна regression-free.

## B5 — Admin TTN button absent on order #155 (read-only diagnostics, fix goes to ST-3.5)
Owner: no Pinta TTN button/block on admin order page for order #155 (shipping = НП відділення, payment = pinta COD). Event 68 (`admin/view/sale/order_info/after` → `alterOrderAddedBtn`) is active per DB. Diagnose only, report: (a) does the event fire (log/echo probe); (b) what `shipping_code`/`shipping_method` value order #155 actually has in `ocp5_order` (JSON shape in OC 4.0.2.x) vs what `getOrdersShippingAddressHtmlSuffix` expects (`pinta_nova_poshta.warehouse/doors`); (c) where the suffix injects — maybe renders but hidden by admin theme. NO fixes in this patch — findings go to ST-3.5.

## Do not touch
SimpleCheckout, `system/library/url.php`, Hutko/Checkbox, getQuote cost (`cost => 0` stays until 2c), NP events, DB schema/indexes (st2a3 index stays), CRM payload, captcha CONFIG without owner approval (report first per B1.2).

## Likely files
`catalog/view/template/checkout/checkout.twig` (JS/CSS), `extension/PintaNovaPoshtaCod/catalog/view/template/shipping/*_register.twig` (telephone/zone fixes if S2/S3), `catalog/view/template/checkout/register.twig` (only if telephone render gated there), `extension/PintaNovaPoshtaCod/catalog/model/payment/pinta_nova_poshta_cod.php` (B3), possibly `extension/PintaNovaPoshtaCod/catalog/controller/payment/pinta_nova_poshta_cod.php` (S3 server-side zone).

## QA / smoke (owner, stock route)
Guest відділення e2e → admin order; guest with intentionally bad data → error visible; logged-in regression (saved address auto-flow); method cards desktop+mobile; payment list naming; TTN button on test pinta-COD order.

## Rollback
`_patch_backups/st2a2-*` per file; pre-check anchors; php -l on model file; clients unaffected pre-cutover.

## Status after
Owner QA pass → ST-2a closed повністю → 2b (coupon/First15, agree consolidation, GA4 dedupe).
