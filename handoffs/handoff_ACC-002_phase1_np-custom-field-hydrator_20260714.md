# Codex Handoff — ACC-002 Phase 1: NP address form (account) + custom_field metadata + narrow checkout hydrator

Date: 2026-07-14. Parent: ACC-002 (Phase 0: `diagnostics/ACC-002_account-np-address-phase0_report_20260714.md`). Risk: **MEDIUM-HIGH** (saved addresses feed checkout NP; adds a narrow checkout-side reader) → final QA includes full `bs-checkout-smoke`. SEO surface: none.

**Owner decision (2026-07-14): Option 1 + legacy policy B approved.** Versioned NP metadata in the existing `oc_address.custom_field` JSON, no DB schema change, one narrowly scoped checkout hydrator. Legacy free-text rows stay intact and get an explicit re-pick prompt when used.

> Deploy-order dependency: CHECKOUT-003 touches the same `checkout.twig` address zone. Codex must anchor against LIVE files **after** CHECKOUT-003 is deployed, and this patch must not land before it. `checkout.twig` is LF-only (CODEX_WORKFLOW.md → Patch conventions; preserve each target's EOL).

## 1. Task ID

ACC-002 Phase 1 — account address add/edit form gets the NP selection UX (область/місто/тип доставки/відділення/поштомат/вулиця з довідника), persists structured NP metadata in `custom_field` JSON, and the checkout learns to hydrate a saved address from that metadata instead of text parsing.

## 2. Context (established by Phase 0 — do not re-derive, verify anchors only)

- `oc_address` stock fields only; `custom_field` JSON exists and round-trips through `catalog/model/account/address.php`.
- Checkout NP form uses module directory endpoints `searchArea`, `searchCity`, `searchWarehouse`, `searchStreet` and client-side hidden refs (`shipping_novaposhta_area_ref`, `city_ref`, `warehouse_ref`, `street_ref`).
- `checkout-reskin.js` currently infers saved-address type via `parseAddressText()` heuristics — no structured reader exists.
- Post-registration redirect (`address_required_after_register` → `account/address.form`; save returns to checkout when cart is non-empty) is intact and must stay.

## 3. Goal

New/edited account addresses are NP-structured and round-trip exactly into checkout (city + warehouse/poshtomat/street preselected). Legacy or stale rows degrade to one explicit re-pick path. No DB migration.

## 4. What to change

**A. Metadata format (contract first — print it in the report):**
- Versioned key inside the existing `custom_field` JSON, e.g. `bs_np_v1`: `{ "type": "warehouse|poshtomat|courier", "area_ref": ..., "city_ref": ..., "warehouse_ref": ..., "street_ref": ..., "labels": { "area": ..., "city": ..., "point": ... } }` (exact shape Codex's call; version key mandatory).
- **Hard rule: merge into existing `custom_field` JSON — never overwrite the column wholesale.** Real account custom fields may live there; they must survive add/edit/save untouched. Corrupted/unparseable JSON → treat row as legacy (policy B), do not crash, do not destroy the raw value.

**B. Account form (`account/address` add/edit):**
- Replace free-text city/point inputs with the NP picker flow, reusing the module's existing directory endpoints — do not fork the directory logic; verify the endpoints are callable from the account context (auth/session), and report if they are not.
- Persist: human-readable labels into the stock fields (`city`, `address_1`/`address_2` — keep current relabelled semantics) so admin/emails/legacy consumers still read something sensible, plus `bs_np_v1` metadata into `custom_field`.
- Server-side validation for NP types (refs present and consistent with type); courier type keeps street/house validation. Post-registration redirect lands on this form unchanged.
- Edit of a legacy free-text row: form opens with a visible «переоберіть точку Нової пошти» prompt (policy B), old text shown for reference; saving requires a valid NP selection for NP types.

**C. Checkout hydrator (narrow, read-only — the approved scope extension):**
- When an authorized customer's saved address is selected at checkout: if `bs_np_v1` present → validate refs via the module directory endpoints and preselect area/city/warehouse (or street for courier); if key absent, JSON invalid, or any ref no longer resolves (closed warehouse) → same single fallback: explicit re-pick prompt, no silent text parsing for that row.
- `parseAddressText()` heuristics remain only as display fallback for legacy rows — hydration decisions must come from metadata, never from text, for rows that have `bs_np_v1`.
- Programmatic hydration must NOT trigger the register autosave: respect the CHECKOUT-003 `bsCheckoutNpInitialising` gate (or equivalent explicit guard) around any programmatic `change` triggers. An autosave may fire only after the customer's own interaction.

## 5. Do not touch

- `confirm.php`, order-creation gates (ST-2b6e `index($allow_order_write)`, ST-2b6d trusted-click, RD-13.1J POST+CAPTCHA loader), Hutko, Checkbox/fiscalization, totals/coupon/First15, ST-2a.4 void net.
- CHECKOUT-003 changes (init-autosave gating) — build on top, do not revert or weaken.
- DB schema (`oc_address` and all others) — option 1 explicitly avoids migrations. No bulk rewrites of existing address rows.
- NP module server-side API/credentials/config; guest checkout flow; CRM payload shape.
- `sitemap.xml`, `robots.txt`, canonical, redirects, `.htaccess`, Merchant feed, schema/JSON-LD.

## 6. Likely files / areas (verify against LIVE, post-CHECKOUT-003)

- `catalog/view/template/account/address_form.twig`, `catalog/controller/account/address.php`, `catalog/model/account/address.php` (merge-write of `custom_field`).
- `catalog/view/javascript/checkout-reskin.js` (saved-address selection path), possibly a small `checkout.twig` block.
- NP module directory endpoints (reuse; read-only).
- Codex should verify against actual project files; if the account context cannot call the module endpoints without server-side change, stop and report before widening scope.

## 7. Acceptance criteria (measurable)

1. Account → add address: NP pickers (область/місто/тип/точка) з довідника; free-text неможливий для NP-типів; save produces `custom_field` containing `bs_np_v1` AND preserves any pre-existing custom-field keys (verify on a row that has one, or construct the case in fixture).
2. New checkout session, that saved address selected: city + warehouse/poshtomat preselected from metadata (Network shows directory validation, not text parsing), shipping flow works, one COD order completes.
3. Legacy free-text address selected at checkout: explicit re-pick prompt appears; after re-pick, order completes; the stored legacy row is not modified unless the customer saves.
4. Stale metadata simulation (ref that doesn't resolve): same re-pick prompt, no JS error, no silent wrong point.
5. New registration → redirect → NP form → save → address visible in account and round-trips per (2) — full new-customer path.
6. Address edit/delete still work; guest checkout unchanged; no autosave request fires from hydration alone (Network check on saved-address selection).
7. Diff limited to files in §6; `confirm.php` and order gates byte-identical.

## 8. QA / smoke test

After deploy: §7 end-to-end + full 11-step `bs-checkout-smoke` (authorized with structured saved address: COD + one Hutko sandbox; one guest order). Record max order ID + draft count before/after. Re-run the CHECKOUT-003 QA item «logged-in, touch nothing, place order» to prove the hydrator didn't break it.

## 9. Rollback note

Back up every changed file to `_patch_backups/ACC-002_phase1_<...>-<timestamp>/...`; restore + clear `cache.*` + `template/*` via `DIR_CACHE`. No DB rollback needed (no schema change; new rows simply carry an extra JSON key that stock code ignores). State in the report that already-saved `bs_np_v1` rows are harmless after rollback.

## 10. Recommended status after execution

`На перевірці` → owner QA (§7, real mobile + desktop) + smoke → `Готово`. In the report: final metadata contract, endpoints used, and the legacy/stale fallback path — one paragraph each, for the roadmap card.
