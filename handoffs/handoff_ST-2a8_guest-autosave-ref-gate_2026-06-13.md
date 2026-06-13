# Codex Handoff — ST-2a.8: guest checkout NP autosave gate decoupled from canonical refs

Date: 2026-06-13. Parent: ST-2 Path B (stock OC4 checkout, pre-cutover — clients still on SimpleCheckout).
Base: fresh backup `backup-6.13.2026_16-15-57_boosters.tar.gz` (has st2a4/st2a5/st2a7 deltas — patch against THIS, not the 10PM backup).
Diagnosis by Claude: live browser (Claude-in-Chrome) + source read of current `checkout.twig`.

## 1. Task ID
ST-2a.8 — guest (and any session-prefilled) checkout: shipping methods never load because the autosave that persists the shipping address to the session never fires. Client-side JS only. (ST-2a.7 fixed the server captcha gate; this is the remaining, separate cause.)

## 2. Context — ROOT CAUSE (verified)
Backend + rendering are PROVEN working (live):
- Direct `POST index.php?route=checkout/register.save` (guest, empty `g-recaptcha-response`) → `{"success":"...guest account information has been saved!"}` (ST-2a.7 effective).
- `POST index.php?route=checkout/shipping_method.quote` → returns `pinta_nova_poshta` warehouse method.
- On reload of a checkout whose session already has a saved address, the «Спосіб доставки» panel renders the NP method.

The failure is the client-side autosave GATE in `catalog/view/template/checkout/checkout.twig`:
- `fieldRef(sel)` = `normalizeText(field(sel).attr('data-ref'))` (~line 288) — reads the VISIBLE input's `data-ref` attribute.
- `bsCheckoutNpIsComplete()` (~line 462) gates on `fieldRef()` of area/city/warehouse (or doors street).
- `registerIsComplete()` (~line 631) requires `bsCheckoutNpIsComplete()`; `triggerRegisterAutosave()` (~line 650) returns early if not complete → `#form-register` never submits → `register.save` never fires → `session['shipping_address']` never set → shipping methods never load.
- The CANONICAL refs live in hidden POST fields `shipping_novaposhta_{area,city,warehouse,street}_ref`, kept in sync by `setHiddenRef()` (~line 303) and present/valid after session hydration.
- BUT the visible `data-ref` attribute is: (a) set only on a live dropdown pick (`bsCheckoutConfirmNpDropdown`, ~line 383), (b) cleared on any field edit (~lines 313/368), (c) **NOT restored when the form is prefilled from session on page load**. So after reload / prefill, `data-ref` is empty while hidden `*_ref` are valid → `bsCheckoutNpIsComplete()` returns false → autosave never fires → «Спосіб доставки» stuck on "Після адреси покажемо доступну доставку".

Live evidence: with hidden `*_ref` valid but visible `data-ref` empty, `bsCheckoutNpIsComplete()` returned `false` and `[data-bs-register-status]` stayed empty (no autosave attempt); after reload the panel still rendered the saved method from session (backend/render fine).

## 3. Goal
Guest / prefilled checkout reliably persists the NP shipping address and loads shipping methods regardless of how the refs got there (dropdown pick, edit, or session hydration).

## 4. What to change (checkout.twig JS only)
Make NP completeness authoritative on the canonical hidden `*_ref` VALUES, not the volatile visible `data-ref`. Verify exact lines against the current file before editing.
1. Add helper, e.g. `function hiddenRef(id){ var n=refFieldName(id); return n ? normalizeText($('input[name="'+n+'"]').val()) : ''; }`.
2. In `bsCheckoutNpIsComplete()` (and/or `fieldRef`), treat a field as "has ref" if EITHER the visible `data-ref` OR the hidden `*_ref` value is non-empty. Keep the doors `data-manual-ok` path intact.
3. On page init/hydration: if hidden `*_ref` are present, restore the visible `data-ref` attrs from them AND call `triggerRegisterAutosave()` once, so shipping loads without requiring a manual field edit.
4. Keep dropdown-select behavior as-is (it already calls `bsCheckoutNpFieldChanged` → autosave) — no regression; and keep the edit-clears-ref behavior so a genuinely emptied field is correctly incomplete.

Scope = pure client JS in `checkout.twig`. No server, no DB, no field-name/POST-shape changes.

## 5. Do not touch
Server controllers/models, `register.save`, the ST-2a.7 captcha block, st2a4 confirm-gating/void-status, Hutko/Checkbox, NP events/Pinta model, DB, CRM payload, hidden field NAMES / POST shape, SimpleCheckout, `system/library/url.php`, sitemap.xml, robots.txt, canonical, .htaccess.

## 6. Likely files / areas
`catalog/view/template/checkout/checkout.twig` only — functions `fieldRef` (~288), `refFieldName` (~292), `setHiddenRef` (~303), `bsCheckoutNpIsComplete` (~462), `registerIsComplete` (~631), `triggerRegisterAutosave` (~650), plus the init/hydration block. Codex must verify exact line numbers against the current file (fresh backup / live).

## 7. Acceptance criteria (measurable)
1. Fresh guest selection (area→city→warehouse via dropdowns) + email + phone → `checkout/register.save` fires and returns `success` → «Спосіб доставки» shows the NP method → order reaches admin (any payment, no live charge).
2. **RELOAD** of checkout with a session-saved address (visible `data-ref` absent, hidden `*_ref` present) → shipping methods AUTO-load WITHOUT any manual field edit. (Key regression this fix must close.)
3. Clearing/editing the warehouse field → completeness becomes false until re-selected (no false-positive autosave with a stale address).
4. Logged-in flow unchanged (saved address → auto shipping → payment → confirm).
5. POST payload field names unchanged (CRM/admin format intact).

## 8. QA / smoke test
HIGH-RISK checkout → run `bs-checkout-smoke`. Minimum: guest відділення + поштомат + адресна; reload mid-flow (AC2); logged-in regression; confirm order in admin + CRM readback (`action=orders`) unchanged.

## 9. Rollback note
Single file. Backup to `_patch_backups/st2a8-<ts>/catalog/view/template/checkout/checkout.twig`; `php -l` n/a (twig) — validate by loading checkout page (no JS console errors) and AC2. Rollback = restore backup. Pre-cutover, clients unaffected.

## 10. Recommended status after execution
Owner QA pass → guest checkout works end-to-end → guest blocker CLOSED (ST-2a.7 server captcha + ST-2a.8 client gate) → proceed to 2b/2c. Update Notion R-13.5.

---
Related, SEPARATE (do not fold into this patch): cold-session first `cart.add` is slow (~7-8s vs 51ms warm) → perceived add-to-cart hang until cookie/session warmed. Tracked separately (cookie-hang); needs server-side profiling.
