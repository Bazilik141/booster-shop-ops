# Codex Report — NCRM-08: customers actual-sale filter

Date: 2026-07-17

## Scope

Implemented the owner-QA follow-up in `ncrm/lib/repositories/customers.repo.ts` only. `getCustomers()` now embeds the order/payment status codes and applies a local `isActualSaleRow()` helper before customer aggregation.

The helper mirrors `public.fn_is_actual_sale` from `0002_stage2_sales.sql` exactly:

```text
(payment = paid OR order = shipped/received)
AND order NOT IN (cancelled, refund)
AND payment NOT IN (cancelled, refund)
```

`/orders` and `orders.repo.ts` remain intentionally unfiltered, so cancelled/refunded orders stay visible there. No migration or mutation path was added.

## Files touched

```
ncrm/lib/repositories/customers.repo.ts — actual-sale filter before customer aggregation
diagnostics/NCRM-08_fix-customers-actual-sale-filter_report_20260717.md
```

## Dry-run result

```text
cd ncrm && npm run build
✓ Compiled successfully
✓ Running TypeScript
ƒ /customers
ƒ /orders
ƒ /orders/[id]
```

Read-only local Supabase QA against phone `500877783`:

```text
OC-FOP-0199  received / paid       → actual
OC-FOP-0206  received / paid       → actual
OC-FOP-0238  cancelled / cancelled → excluded
OC-FOP-0239  shipped / unpaid      → actual

actual order count: 3
actual lifetime revenue: 2812.50 UAH
```

This proves the cancelled order is excluded while the shipped COD order is retained under the same rule as the SQL source of truth.

## php -l result

Not applicable: read-only TypeScript/Next.js code. `npm run build` completed TypeScript validation.

## Idempotency

Not applicable: no runner, schema change, or write exists. Each request reads and aggregates current local data only.

## Rollback

Single-file rollback: revert `ncrm/lib/repositories/customers.repo.ts`. No database, import, local Supabase data, cloud system, Apps Script, or OpenCart state changed.

## Run command (owner)

```bash
cd ncrm || exit
npm run dev
```

## Post-deploy QA checklist

- [ ] Reload `/customers`; Максим Тимощук (`500877783`) has 3 actual orders and LTV 2 812,50 ₴, with `OC-FOP-0238` absent from the aggregate.
- [ ] Confirm `OC-FOP-0239` (`Відправлено` / `Не оплачено`) still counts as an actual sale.
- [ ] Confirm a customer whose orders are all cancelled/refunded is absent from `/customers`.
- [ ] Confirm `/orders` still lists cancelled and refunded orders.
- [ ] Before a commit, check `git status`; do not stage `.env.local`, keys, Docker temp files, imports, or unrelated dirty-tree files.

## Side effects / risks

The JavaScript helper intentionally duplicates the SQL predicate because PostgREST cannot call `fn_is_actual_sale` inline in this additive-only read query. Its source-of-truth comment names the migration/function to make later contract changes visible. The app remains local-only and unauthenticated; this follow-up does not alter the NCRM-08/09 auth decision.
