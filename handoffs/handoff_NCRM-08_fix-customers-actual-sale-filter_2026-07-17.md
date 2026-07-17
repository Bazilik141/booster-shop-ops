# Codex Handoff — NCRM-08 fix: customers screen must exclude non-actual sales

Date: 2026-07-17 | Parent: NCRM-08 (In progress — read screens, this is a follow-up fix before closing to Done).

## 1. Task ID
NCRM-08 follow-up (not a new roadmap ID) — small scoped fix found during owner QA on real imported data.

## 2. Context
- Owner ran the NCRM-03 historical import locally and QA'd all 5 NCRM-08 screens against real data. Confirmed on screen: customer "Максим Тимощук" (`500877783`) has order `OC-FOP-0238` with status `Скасовано`/`Скасовано` (cancelled, cancelled payment), yet the `/customers` screen still counts it into his order count and `lifetimeRevenue` (shown: 4 orders, LTV 3 563 ₴).
- `ncrm/lib/repositories/customers.repo.ts`'s `getCustomers()` aggregates the raw `sales` table with no status filter at all — every sale counts, regardless of `order_status`/`payment_status`.
- This is inconsistent with the only other customer-shaped object in the schema, `v_repeat_customers` (`0004`), which filters `where public.fn_is_actual_sale(s.id)`. That SQL function's exact rule (`0002`):
  ```
  (payment_status.code = 'paid' OR order_status.code IN ('shipped', 'received'))
  AND order_status.code NOT IN ('cancelled', 'refund')
  AND payment_status.code NOT IN ('cancelled', 'refund')
  ```
- Note this is **not** simply "exclude unpaid orders" — a shipped-but-not-yet-marked-paid COD order (e.g. `OC-FOP-0239`, status `Відправлено`/`Не оплачено`, also visible on screen for the same customer) correctly still counts as an actual sale under this rule (`os.code = 'shipped'` satisfies the first OR-clause), matching real-world post-pay delivery. Only cancelled/refunded orders should be excluded.
- `ncrm/lib/repositories/orders.repo.ts`'s `getOrders()` deliberately does **not** apply this filter — an orders list should show every order including cancelled ones. Do not add this filter there; this fix is customers-only.

## 3. Goal
`getCustomers()` in `ncrm/lib/repositories/customers.repo.ts` must only aggregate sales that satisfy `fn_is_actual_sale`'s rule into order count / first-last-order dates / `lifetimeRevenue`. A customer whose only orders are cancelled/refunded should not appear at all (order_count would be 0); a customer with a mix should have the cancelled/refunded ones excluded from the count and revenue, but this does not change which orders show up on `/orders` (unaffected, out of scope here).

## 4. What to change (scope)
- `ncrm/lib/repositories/customers.repo.ts`, `getCustomers()`: extend the existing `sales` select to also embed `order_statuses(code)` and `payment_statuses(code)` (same embedding pattern already used in `ncrm/lib/repositories/orders.repo.ts`'s `ORDER_SELECT`).
- In the aggregation loop, before counting a sale row into `orderCount`/`firstOrderAt`/`lastOrderAt`/`lifetimeRevenue`, evaluate the same boolean rule as `fn_is_actual_sale` (quoted in Context above) against the row's joined `order_statuses.code`/`payment_statuses.code`. Skip the row entirely (do not count it, but also don't let it override an existing customer's normalized-phone bucket) if the rule is false.
- If a customer's every sale fails the rule, they should not appear in the result at all (not a zero-order row) — matches `v_repeat_customers`'s exclusion behavior for non-actual sales, extended here to one-time customers too (per the original NCRM-08 handoff's requirement to include one-time buyers, but only real ones).
- Implement the rule as a small local helper function (e.g. `isActualSaleRow(orderStatusCode, paymentStatusCode)`) mirroring the SQL exactly, with a one-line comment pointing at `fn_is_actual_sale` (`0002_stage2_sales.sql`) as the source of truth, so the two don't silently drift apart later.

## 5. What NOT to touch
- `ncrm/lib/repositories/orders.repo.ts` / `/orders` screen — must keep showing every order regardless of status, this fix does not apply there.
- `ncrm/supabase/migrations/*` — no schema change, `fn_is_actual_sale` already exists; this is a repo-layer read-side fix only.
- Any other NCRM-08 screen (`/`, `/stock`, `/sku`) — unaffected, do not touch.
- No write/mutation path — still read-only, same as the rest of NCRM-08.

## 6. Likely files/areas
- `ncrm/lib/repositories/customers.repo.ts` (only expected change)
- Possibly `ncrm/lib/domain/customers.ts` only if a new field is needed to expose excluded-order count for transparency (optional, Codex's call — not required by acceptance criteria below)

## 7. Acceptance criteria
- [ ] `cd ncrm && npm run build` succeeds, 0 errors
- [ ] Максим Тимощук (`500877783`) on `/customers`: order count and LTV no longer include `OC-FOP-0238` (`Скасовано`/`Скасовано`); still includes `OC-FOP-0239` (`Відправлено`/`Не оплачено`, since shipped satisfies the rule) and his other actual orders
- [ ] A customer whose only order(s) are all cancelled/refunded does not appear in `/customers` at all
- [ ] `/orders` output is byte-for-byte unchanged by this fix (no filter added there)
- [ ] No new Supabase client import outside `ncrm/lib/repositories/*`, no mutation calls (same boundary checks as the original NCRM-08 report)

## 8. QA / smoke test (owner)
Not checkout/payment/schema in the live-site sense — local read-only CRM tool, no live system touched.
- [ ] Reload `/customers`, confirm Максим Тимощук's numbers dropped by exactly the cancelled order's amount (750 ₴) and his order count is 3, not 4 (verify against the actual remaining order list, numbers here are the owner's visual read, not authoritative)
- [ ] Spot-check one other customer with no cancelled orders — numbers must stay identical to before the fix
- [ ] `git status` before commit — no `.env.local`/keys staged

## 9. Rollback note
Single-file repo-layer change, read-only. Rollback = revert `customers.repo.ts` to the pre-fix version via `git`. No schema, no data, no other screen affected.

## 10. Recommended status after execution
NCRM-08 stays `In progress` until this fix lands and the owner confirms the QA checklist above. Then NCRM-08 → `Done`. NCRM-09 (write forms + auth foundation) handoff follows once this closes.
