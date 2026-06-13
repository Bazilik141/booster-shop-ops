# Codex Handoff — ST-2a.8c: dropdown click-select leaves register autosave blocked by stuck `bsSaving` (CONFIRMED root cause)

Date: 2026-06-13. Parent: ST-2 Path B, supersedes ST-2a.8b. Diagnosed live by Claude (Claude-in-Chrome, real dropdown click reproduction). Client JS only.

## 1. Task ID
ST-2a.8c — guest checkout: picking NP area/city/warehouse by MOUSE CLICK fills the fields but does not load shipping; pressing ENTER does. ST-2a.8 (ref gate) + ST-2a.8b (`input.trigger('change')`) did NOT fix it.

## 2. Root cause — CONFIRMED via live reproduction
Reproduced a real `.pinta-dropdown li` mouse click on the warehouse field and instrumented `#form-register`:
- After the real click: visible `data-ref` set, hidden `shipping_novaposhta_warehouse_ref` set, `window.bsCheckoutNpIsComplete()` = **true**.
- `registerIsComplete()` conditions all true: firstname/lastname (synced from NP), email, phone all OK.
- **Yet `checkout/register.save` does NOT fire** (network hook: 0 calls) → shipping never loads.
- The moment I reset `$('#form-register').data('bsSaving', false).data('bsLastSaved','')` and call `window.bsCheckoutNpFieldChanged(warehouseEl)`, `register.save` fires and returns `{success}` and shipping loads.
- Calling `window.bsCheckoutConfirmNpDropdown(el, ref)` directly (what the click handler calls) ALSO fires the save when flags are clean.

Conclusion: the real click-select path leaves `#form-register` `data('bsSaving') === true` stuck, so `triggerRegisterAutosave` (checkout.twig ~650) returns early at `if (form.data('bsSaving') || form.data('bsLastSaved') === signature) return;` and never submits. ENTER works because the native form submit bypasses `triggerRegisterAutosave` entirely.

Why `bsSaving` sticks: `triggerRegisterAutosave` sets `form.data('bsSaving', true)` BEFORE `form.trigger('submit')`. `bsSaving` is only reset in the `ajaxComplete` handler for `register.save` (checkout.twig ~786). In the click path the triggered submit does not produce a `register.save` AJAX (so `ajaxComplete` never runs) → `bsSaving` stays true forever → every later autosave is skipped. ST-2a.8b's added `input.trigger('change')` makes it worse: it fires a SECOND `bsCheckoutNpFieldChanged`; the first `triggerRegisterAutosave` sets `bsSaving=true` (and its submit is swallowed), the second and all subsequent calls hit the stuck flag and skip.

## 3. Goal
A mouse-only dropdown selection (area→city→warehouse) with contacts filled must fire `register.save` exactly once and load shipping/payment — no ENTER needed. The `bsSaving` flag must never stick. ENTER path and logged-in flow must keep working.

## 4. What to change (client JS)
Pick the minimal robust fix; verify live.
1. **Fix the `bsSaving` lifecycle so it cannot stick** (checkout.twig): set `bsSaving=true` only when the actual `register.save` AJAX starts (its `beforeSend`/`ajaxSend`), and always reset it in `ajaxComplete`/`ajaxError` for `register.save`. If `bsSaving` must remain set in `triggerRegisterAutosave` before `form.trigger('submit')`, add a watchdog that clears it (e.g. `setTimeout(()=>form.data('bsSaving',false), 3000)`) so a swallowed submit can't permanently block autosave. Also reset `bsSaving=false` whenever `bsCheckoutNpFieldChanged` detects a genuinely new signature.
2. **Make the dropdown click reliably trigger ONE autosave** (Pinta `js_checkout_shipping_address_form.twig` click handler): after `bsCheckoutConfirmNpDropdown(input[0], ref)`, ensure a single autosave runs with clean flags. Reconsider ST-2a.8b's `input.trigger('change')` — replace the double-trigger with one deterministic path (e.g. clear `bsSaving` then let `bsCheckoutNpFieldChanged` schedule the save once).
3. Confirm why `form.trigger('submit')` from `triggerRegisterAutosave` does not always produce the `register.save` AJAX (where is that AJAX bound? stock checkout JS vs Pinta) — so the autosave reliably reaches it on the click path, same as ENTER does.

Keep ST-2a.7/2a.8/2a.10. Do not touch server, register.save server logic, captcha, Hutko/Checkbox, NP model, DB, POST field names.

## 5. Do not touch
Server controllers, `register.save` business logic, captcha, GA4 (ST-2a.10), Hutko/Checkbox, NP model, DB, POST field names, SimpleCheckout, url.php.

## 6. Likely files
`catalog/view/template/checkout/checkout.twig` — `triggerRegisterAutosave` (~650), `bsCheckoutNpFieldChanged` (~674), `ajaxComplete`/`ajaxSuccess` for register.save (~786/795), `bsSaving` lifecycle. `extension/PintaNovaPoshtaCod/catalog/view/template/shipping/js_checkout_shipping_address_form.twig` — `.pinta-dropdown li` click handler (the ST-2a.8b `input.trigger('change')`). Verify vs live; clear template cache after (twig).

## 7. Acceptance criteria
1. Mouse-only: area→city→warehouse via dropdown clicks + email/phone → `register.save` fires exactly once → shipping + payment load WITHOUT pressing ENTER.
2. Poshtomat: same.
3. Rapid re-selection / changing warehouse twice still saves each time (no stuck `bsSaving`).
4. ENTER path still works.
5. Logged-in saved-address flow unchanged.
6. No duplicate `register.save` calls per selection.

## 8. QA / smoke
bs-checkout-smoke. Guest відділення + поштомат + адресна, mouse-only. Watch Network: one register.save per completed selection, shipping_method.quote follows, order reaches admin. Re-select to confirm no stuck flag.

## 9. Rollback
Backup edited twig(s) to `_patch_backups/st2a8c-*`; restore + clear template cache. Pre-cutover, clients unaffected.

## 10. Status after
Guest blocker fully closed (ST-2a.7 captcha + 2a.8 ref-gate + 2a.10 gtag + 2a.8c autosave flag). Re-verify mouse-only guest order e2e. Update Notion R-13.5.

---
Evidence (Claude live): real warehouse click → npComplete true, registerIsComplete true, register.save fired = 0; after `data('bsSaving',false)` + `bsCheckoutNpFieldChanged()` → register.save fired = 1, `{success}`, shipping rendered.
