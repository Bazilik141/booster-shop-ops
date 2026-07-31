# Codex Handoff — NCRM-16 round2: mount-time payment-type default + Monobazar fee auto-calc

Date: 2026-07-27 | Parent: handoff_NCRM-16_monobazar-postpay-fee_20260726.md
Codex config: model=<Sol/Terra/Luna> · effort=medium

> LOW-RISK: extends the already-reviewed round1 diff (new reference migration + client default in `sale-form.tsx`). No new migration, no changes to `addSale`, order-sync, or checkout. Not the live storefront checkout/payment path — see Risks.

## 1. Task ID
NCRM-16 round2 — two fixes/additions on top of round1, both scoped to the internal CRM new-sale form:
1. **Blocker fix**: payment-type default for channel Monobazar must apply on initial form load, not only on channel `onChange`.
2. **New in this round (owner-approved scope addition)**: auto-calculate the per-line "Комісія, грн" (`paymentFee`) field as 2.9% of line revenue when the order's payment type is `postpay_monobazar`, while keeping the field manually editable and never overwriting a manual edit.

## 2. Context
Round1 review (diagnostics/NCRM-16_monobazar_postpay_fee_report_20260727.md, reviewed 2026-07-27) confirmed migration `0016_ncrm16_monobazar_postpay_fee.sql` is correct and idempotent, and the `sale-form.tsx` diff correctly matches channel/payment-type by `code`, not id. One defect found in review:

`channelId`/`paymentTypeId` state is initialized as `references.channels[0]?.id` / `references.paymentTypes[0]?.id` (both lists sorted by `name_uk` ascending). The Monobazar-default logic (`updateChannel`) only runs inside the channel `<select>` `onChange` handler — it never runs against the initial state. If `channels[0]` happens to already be Monobazar (current seed: "Monobazar" sorts before Cyrillic names), the form mounts with Monobazar + whatever `paymentTypes[0]` is (currently "Еквайринг", i.e. wrong), and the default never fires unless the owner manually toggles the channel dropdown away and back. This is a logic bug independent of today's sort order — fix the root cause (resolve the default from whatever the initial `channelId` is), not the symptom.

Separately, the owner has asked to add automatic 2.9% fee calculation now, in this same task, rather than as a follow-up. `payment_types.fee_pct_config_key` exists in the schema (`0002_stage2_sales.sql`) but currently has zero consumers anywhere in `ncrm/` — confirmed by repo-wide grep during review. `sale_items.payment_fee` is a manual per-line input today (`app/orders/new/actions.ts`, `lib/repositories/sales.repo.ts`) and feeds `v_sale_item_financials.net_profit` (`0002_stage2_sales.sql:202-209`), where `revenue = qty * unit_price - discount_alloc` (`0002_stage2_sales.sql:194`). That `revenue` expression is the correct base for a percentage fee — it is already how "line value net of discount" is defined elsewhere in this schema.

On migration numbering: `0016` correctly follows `0015` in the repo. `0014` is intentionally reserved for NCRM-14 (ПУМБ payment types, `handoffs/handoff_NCRM-14_order-sync-pumb-payment-types_20260726.md`), which is planned but not yet executed — confirmed in `dashboard/booster-dashboard.html` (2026-07-26 entry). This is expected sequencing, not a defect. Do not create, fill, or renumber `0014` as part of NCRM-16.

## 3. Goal
1. The Monobazar → `postpay_monobazar` payment-type default applies correctly on first render, for any seed/sort order — not only after a channel change.
2. When the order's payment type is `postpay_monobazar`, each sale line's `paymentFee` auto-fills as `round((qty * unitPrice - discountAlloc) * feePct, 2)`, where `feePct` is read live from `app_config` (via `payment_types.fee_pct_config_key`), not hardcoded as `0.029` in the frontend.
3. Auto-calculated fee remains a suggested default: once the owner manually edits a line's "Комісія, грн" field, that line's value must never be silently overwritten again (by qty/price/discount edits or by payment-type changes).
4. No behavior change for the four existing payment types (`fop_control`, `cod_personal`, `bank_details`, `acquiring`) — their `fee_pct_config_key` values are not seeded in `app_config` yet (known, out-of-scope gap per round1 handoff), so `feePct` for them must resolve to `null` and trigger no auto-calc.

## 4. What to change

**Phase A — mount-time default fix** (`ncrm/app/orders/new/sale-form.tsx`):
- Extract the Monobazar-matching logic from `updateChannel` into a small pure helper, e.g. `resolveDefaultPaymentTypeId(channelId, channels, paymentTypes, fallbackId)`, that returns the `postpay_monobazar` id when the given channel's code is `monobazar`, else `fallbackId`.
- Use that helper both for the `paymentTypeId` `useState` initializer (lazy initializer, evaluated against the initial `channelId`) and inside `updateChannel`, so there is exactly one place that encodes the default rule.

**Phase B — fee auto-calc data (read-only)**:
- `ncrm/lib/domain/reference.ts` — add a `PaymentTypeOption` type (`ReferenceOption & { feePct: number | null }`) and change `SaleFormReferences.paymentTypes` to `PaymentTypeOption[]`.
- `ncrm/lib/repositories/reference.repo.ts` — in `getSaleFormReferences`, when loading `payment_types`, also select `fee_pct_config_key`. For the non-null keys, query `public.v_current_app_config` (already exists, already used by `currency-rates-fetch`) filtered to `key in (...)`, and attach `value_num` as `feePct` to the matching payment type by key; payment types with no key or no current config row get `feePct: null`. No new SQL view or migration needed — `0016` already seeds the one row this task needs.

**Phase C — fee auto-calc in the form** (`ncrm/app/orders/new/sale-form.tsx`):
- Add a per-line dirty flag (e.g. `feeTouched: boolean`, default `false`) to `SaleLine` / `emptyLine()`.
- Setting a line's "Комісія, грн" input directly (existing `onChange` at line ~66) sets that line's `feeTouched = true` in the same update.
- When the order's `paymentTypeId` resolves to a payment type with non-null `feePct`, recompute `paymentFee` for every line where `feeTouched === false` as `round((qty * unitPrice - discountAlloc) * feePct, 2)`, on: line qty/unitPrice/discountAlloc change, and on `paymentTypeId` change (including the Phase A default firing). Use whatever reactive pattern fits the existing component (e.g. a small effect, or inline in `updateLine`/`updateChannel`/the payment-type `onChange`) — do not restructure the component beyond what this requires.
- When `paymentTypeId` has no `feePct` (the four existing types), do not touch `paymentFee` at all — leave current manual-entry behavior exactly as-is.
- `feeTouched` is form-local UI state only; it is not submitted and does not need a new column — confirm `itemsJson` / `createSaleAction` / `addSale` still only consume the existing `paymentFee` number, unchanged.

## 5. Do not touch
- `ncrm/supabase/migrations/0016_ncrm16_monobazar_postpay_fee.sql` — already authored and reviewed in round1; read the seeded value, do not edit or re-author it.
- `ncrm/supabase/migrations/0014*` — reserved for NCRM-14; do not create, fill, or renumber.
- `app/orders/new/actions.ts`, `lib/repositories/sales.repo.ts`, `addSale` — `paymentFee` remains a plain number field on the wire; no schema or server-action change.
- `fop_control_pct` / `payback_fiz_pct` / `acquiring_pct` / `fop_control_min` / `payback_fiz_fix` — still not seeded; do not seed them as a side effect of building the generic `feePct` lookup.
- `order-sync/index.ts`, OpenCart, NCRM-14 (ПУМБ), NCRM-12 (`/orders/[id]`) — unrelated, different files.
- FIFO/COGS logic, auth — unrelated.
- The separate NCRM-11 `currency-rates-fetch` TypeScript build issue — known, out of scope, do not fix as part of this task.

## 6. Likely files / areas
- `ncrm/app/orders/new/sale-form.tsx` (extend round1 diff — mount-time default helper + fee auto-calc UI logic).
- `ncrm/lib/domain/reference.ts` (add `PaymentTypeOption`).
- `ncrm/lib/repositories/reference.repo.ts` (extend `getSaleFormReferences` payment-types query + `v_current_app_config` join).
- Codex should verify against actual project files before writing; confirm `v_current_app_config` column names (`key`, `value_num`) against `0004_stage4_expenses_reports.sql` / `lib/types/database.ts` before use.

## 7. Acceptance criteria
- [ ] Fresh page load of `/orders/new` with the current seed (Monobazar sorts first): payment type shows "Післяплата monobazar" immediately, with no channel interaction required.
- [ ] Reordering/reseeding `sale_channels` such that Monobazar is not `channels[0]` still produces the correct default when Monobazar is explicitly selected (regression guard for the original round1 behavior).
- [ ] With payment type = "Післяплата monobazar", entering qty=2, unitPrice=500, discountAlloc=0 on a line auto-fills "Комісія, грн" = 29.00 (2 × 500 × 0.029).
- [ ] Manually overwriting a line's "Комісія, грн" to a different value, then changing that line's qty, does not revert the manual value.
- [ ] Switching payment type away from Monobazar and back does not clobber a manually-edited fee on an already-touched line.
- [ ] Selecting any of the four existing payment types (`fop_control`, `cod_personal`, `bank_details`, `acquiring`) leaves "Комісія, грн" exactly as today — still a plain manual number field, no auto-fill, no console error from a null `feePct`.
- [ ] `2.9%` / `0.029` does not appear as a literal in `sale-form.tsx` or any `.ts`/`.tsx` file — the value is read from `app_config` via `references.paymentTypes[...].feePct`.
- [ ] `git diff` touches only the files in §6 (plus the already-existing `0016` migration and diagnostics report from round1 — no new migration file).

## 8. QA / smoke test (owner)
- [ ] Apply migration `0016` (round1 step, unchanged).
- [ ] Open `/orders/new` fresh (hard refresh, no prior interaction) — confirm channel and payment type both show Monobazar defaults without touching any dropdown.
- [ ] Add a line, set qty/price, confirm "Комісія, грн" auto-fills at 2.9% of qty×price.
- [ ] Manually edit that fee value, change qty again, confirm the manual fee value survives.
- [ ] Switch channel to a non-Monobazar channel, confirm payment type and any already-computed fees do not change automatically.
- [ ] Create one test sale with Monobazar + auto-calculated fee; confirm it saves and the stored `payment_fee` matches what was shown in the form.
- [ ] Spot-check one sale using an existing payment type (e.g. `fop_control`) — confirm "Комісія, грн" behaves exactly as before this change (manual only).

## 9. Rollback note
- Phase A/C (`sale-form.tsx`): revert to the round1 diff (or further to the pre-NCRM-16 version) — pure client-side state/logic, no data written.
- Phase B (`reference.ts`, `reference.repo.ts`): revert the added `feePct` field and the `v_current_app_config` join; `SaleFormReferences.paymentTypes` reverts to plain `ReferenceOption[]`.
- No migration rollback needed beyond the round1 note (`delete from payment_types where code='postpay_monobazar'; delete from app_config where key='postpay_monobazar_pct';`) — this round adds no new database objects.

## 10. Recommended status after execution
NCRM-16 → "Done" after owner QA in §8 passes, same as round1's recommendation — this round closes the gap the round1 review found and adds the fee calc the owner requested, without widening into checkout/order-sync/OpenCart.

## Risks
- Not the live storefront checkout/payment path: this form is the internal CRM manual sale-entry screen, not the OpenCart/Hutko/Checkbox/Nova Poshta purchase flow. `bs-checkout-smoke` (11-step checkout/payment smoke test) does not apply here and is not required for this task.
- Financial-figure risk is display-only until save: the auto-calculated fee is a pre-fill the owner sees and can edit before submitting, not a silent recalculation of already-saved sales. No existing `sale_items` rows are touched by this change.
- Scope-creep guard: the generic `feePct` lookup will "just work" for any future payment type that gets both a `fee_pct_config_key` and a seeded `app_config` row — that is intentional reuse, not scope creep, but Codex must not seed the four missing config keys listed in §5 as a convenience while building this.
