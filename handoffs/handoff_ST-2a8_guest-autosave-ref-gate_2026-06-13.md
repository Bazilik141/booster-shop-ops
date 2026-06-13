# Codex Handoff — ST-2a.8 (guest autosave gate) + ST-2a.9 (cold-session add-to-cart hang)

Date: 2026-06-13. Parent: ST-2 Path B (stock OC4 checkout, pre-cutover — clients still on SimpleCheckout).
Base: fresh backup `backup-6.13.2026_16-15-57_boosters.tar.gz` (has st2a4/st2a5/st2a7 deltas — patch against THIS, not the 10PM backup).
Diagnosis by Claude: live browser (Claude-in-Chrome) + source read of current `checkout.twig` / `product.twig`.

**This handoff contains TWO independent tasks — ship as SEPARATE commits + patches:**
- **Task A — ST-2a.8** (client JS, `checkout.twig`): guest NP autosave gate.
- **Task B — ST-2a.9** (client JS mitigation + server investigation): cold-session add-to-cart hang.

---

# Task A — ST-2a.8 (guest checkout NP autosave gate decoupled from canonical refs)

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

# Task B — ST-2a.9 (cold-session add-to-cart "hang")

## 1. Task ID
ST-2a.9 — on a brand-new (cold) session, the FIRST add-to-cart appears to hang (button spinner stuck) until the session is "warmed" (e.g., by accepting/declining the cookie banner first). Subsequent clicks are fine. Reported symptom: "first click hangs, F5 fixes, item already in cart."

## 2. Context — measured behaviour (Claude, live via Claude-in-Chrome)
- Reproduced by clearing `OCSESSID` + `policy` cookies → reload product page (cold session, cookie banner shown) → click add-to-cart.
- `POST index.php?route=checkout/cart.add` on a COLD session takes **~7-8 s** (eventually HTTP 200, item IS added); on a WARM session the identical request is **~51 ms**.
- The add-to-cart button is re-enabled only in the `$.ajax` `complete:` callback (`product.twig` ~line 813), so it shows "loading" for the whole slow request → perceived freeze. No JS error; no client handler bug.
- Page is heavy (33 requests, extensions: `ps_enhanced_measurement` GA4 + GTM consent-mode, `ps_live_search`, Microsoft Clarity). The cookie banner JS itself is clean and does NOT intercept the click.
- Classic OpenCart cold-session cost: PHP session-file create + lock contention on the first stateful request, amplified by the heavy page. Accepting the cookie first issues a quick request that creates/warms the session, so the next `cart.add` is fast.

## 3. Goal
First add-to-cart on a cold session no longer appears frozen; ideally also reduce the actual cold-request time. Do NOT regress warm-session behaviour, cart correctness, GA4/Clarity, or the cookie banner.

## 4. What to change
**B1 — client UX safeguard (safe, do now) — `product.twig` add-to-cart `$.ajax`:**
- Add a `timeout` (e.g. 12000 ms) and friendly feedback: while pending show e.g. "Додаємо у кошик…"; on `complete`/timeout always re-enable the button; on timeout/error show "Спробуйте ще раз" and allow retry (do NOT double-add — guard with an in-flight flag).
- Goal: the user never sees a dead frozen button; the slow first request degrades gracefully.

**B2 — server-side latency (INVESTIGATION FIRST, no blind patch) — owner/Codex-with-access:**
- Add temporary timing instrumentation around `checkout/cart.add` and the product-page render to measure where the cold-session seconds go (session lock wait vs module work vs DB). Owner has server access; Codex does not — so this step is owner-run (or Codex only if granted access).
- Candidate fixes to evaluate ONLY after measurement: call `session_write_close()` as early as safe in the page-render path so `cart.add` doesn't wait on the render's session lock; consider a non-file session handler (DB/Redis) to cut lock contention; defer/async heavy analytics work off the request path.
- Per project rule: do not apply server patches blindly without measuring current state first.

## 5. Do not touch
Cookie banner controller/twig (`common/cookie`), GA4/`ps_enhanced_measurement` config, Clarity, GTM consent config, cart add/total logic, `checkout/cart.add` business logic, session cookie name/flags, DB schema. B1 is presentation-only; B2 is measure-then-fix.

## 6. Likely files / areas
B1: `catalog/view/template/product/product.twig` (the `#form-product` submit `$.ajax`, ~lines 805-840). B2: server config / `system/` session handling + whatever the timing log points to (TBD after measurement).

## 7. Acceptance criteria
1. B1: on a cold session, first add-to-cart shows progress text and ALWAYS resolves (success or a retry prompt) within the timeout — never a permanently frozen button. Warm session unchanged. No double-add.
2. B1: item still adds correctly; mini-cart (`#cart`) refreshes on success as before.
3. B2 (after measurement): cold-session `cart.add` time documented; if a safe fix is applied, cold time materially drops (target < ~2 s) with zero regression to cart totals / analytics.

## 8. QA / smoke test
Cold session (clear OCSESSID+policy, reload): first add-to-cart → graceful, item added, mini-cart updates. Warm session: unchanged, fast. Guest + logged-in. Re-run `bs-checkout-smoke` if B2 touches session/checkout.

## 9. Rollback note
B1: single file (`product.twig`), backup to `_patch_backups/st2a9-*`, restore on issue. B2: any server change must have its own backup + documented revert (and is gated on measurement).

## 10. Recommended status after execution
B1 done → perceived hang gone (owner QA cold-session). B2 → separate follow-up once measured. Update Notion after both A + B land.
