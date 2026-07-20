# Note — NCRM-10: discount_total always 0 in the new CRM

Date: 2026-07-19 | Author: Claude (flagged during CHECKOUT-002 review, confirmed against real data) | Status: backlog, not yet scoped as a handoff

## What's wrong

Every `sales` row created by the OpenCart → Supabase pipeline has `discount_total = 0.00`, regardless of the real order's discount. Confirmed against real production rows (`sales_rows.json`, pulled 2026-07-19 ~19:17): all four rows present (`OC-FOP-0256`–`OC-FOP-0259`) show `"discount_total":"0.00"`.

## Root cause

- `system/library/booster_crm_sync.php` (`NcrmOrderSync::buildPayload()`) already computes the real discount from `order_total` rows and sends it as a top-level numeric field: `'discount_total' => $discount`.
- `ncrm/supabase/functions/order-sync/index.ts` never reads `payload.discount_total`. Instead it computes `discount_total: discountTotal(validated.totals)`, where `discountTotal()` sums a `totals` array of `{code, value}` objects — a field the PHP bridge does not send at all. `payload.totals` is therefore always `undefined` → `validated.totals = []` → `discountTotal([]) = 0`, every time.
- Codex's round-4 report (`diagnostics/NCRM-10_round4_fix-order-id-type-check_report_20260719.md`) flagged this explicitly as an out-of-scope observation while fixing the order_id/unit_price validation bugs; it was correctly left untouched in that round.

## Why it matters

Financial totals in the new CRM are silently wrong for every order with a real discount (coupon, voucher, reward, or manual discount). Nothing rejects or errors — the row inserts cleanly with a wrong number, so this won't surface as a sync failure, only as bad reporting.

## Suggested scope for a future round

- Either: have `index.ts` read `payload.discount_total` directly (matching what PHP already computes), or have PHP send the `totals` array shape `index.ts` expects — pick one source of truth, don't compute the same number two different ways in two languages.
- Small, single-purpose fix, same pattern as round 4 — `ncrm/supabase/functions/order-sync/index.ts` only, most likely.
- Acceptance check: a real order with a known non-zero discount produces a `sales.discount_total` matching the OpenCart order total's discount/coupon/voucher/reward line(s).
- Retroactive correction of the four already-inserted rows with wrong (zero) discount is a separate decision — not assumed here.

## Not yet done

No handoff written, no task ID assigned. Owner said "запиши в задачі, зробимо" (2026-07-19) — logged here so it isn't lost; promote to a proper Codex handoff (and a Notion roadmap entry, once Notion is connected) when scheduled.
