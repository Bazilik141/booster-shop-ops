# Codex Report — NCRM-04: inventory ledger foundation

Date: 2026-07-12

## Scope

Implemented one additive local migration, `0006_inventory_ledger_foundation.sql`.
The five existing migrations are unchanged. No cloud Supabase project, Apps
Script CRM, Google Sheet, OpenCart code, UI/repositories, NCRM-03 importer, or
KPI/reporting views were changed.

Implementation choices documented for review:

- Corrections use a standalone signed ledger. Positive `qty_delta` rows are
  explicit FIFO cost layers; negative rows are chronological consumption. No
  synthetic `purchase_lots` are created, so the existing positive-quantity and
  real-`purchase_id` invariants remain intact.
- `opening_balance` remains a cost layer but is excluded from the operating
  variance amount in `v_inventory_adjustment_pnl`. The only other currently
  approved kind is `operating_correction`; additional taxonomy is marked
  `TODO(NCRM-04/OWNER)`.
- Shared fees are recorded per lot and per fee type. The allocation function
  writes its rounded, residual-balanced result to both the audit table and the
  existing per-lot cost fields, so `v_purchase_lot_costs` remains the single
  cost input. The deferred constraint allows one transaction to create all
  rows, then requires one method and an exact total equal to the source fee.
- Warehouse valuation uses only `in_stock`/`selling`/`sold` purchase layers and
  signed adjustment layers. `ordered`/`in_transit` add to asset value only;
  `cancelled` is excluded. The valuation function does not let an earlier
  shortage consume a later receipt retroactively.

## Files touched

```
ncrm/supabase/migrations/0006_inventory_ledger_foundation.sql
diagnostics/NCRM-04_inventory-ledger-foundation_report_20260712.md
```

## Implemented objects

- `products.weight_g`, `products.is_outlet` (default `false`; no backfill).
- `inventory_adjustments`, `inventory_adjustment_items`, and
  `v_inventory_adjustment_pnl`.
- `purchase_lot_fee_allocations`, deferred exact-total validation, and
  `fn_allocate_purchase_shared_fee(...)` for `weight`, `value`, and `manual`.
- `v_inventory_cost_layers`, `v_inventory_consumptions`,
  `fn_inventory_fifo_layers(...)`, and `v_inventory_fifo_valuation`.
- Widened COGS state to `pending` / `provisional` / `estimated` / `actual`.
  New non-actual sale items default to `pending`; FIFO fallback now writes
  `estimated`; `v_data_quality` exposes estimated rows.
- Updated the existing `fn_fix_new_sale_item` and `fn_fix_actual_sale_items`
  trigger functions so a `pending` item is costed when its sale is actual. This
  preserves the NCRM-03 Mystery `provisional` exclusion and fixes the old
  `0003` assumption that only the former default `actual` needed FIFO.

## Local verification

| Check | Result |
|---|---|
| `git diff --check` | passed |
| `0001`…`0005` unchanged | passed (`immutable_0001_0005=yes`) |
| `npm run build` in `ncrm/` | passed — Next.js compile and TypeScript succeeded |
| `npx supabase db reset` | passed — applied `0001`…`0006`, exit code 0 |
| `npx supabase db diff --local` | passed — `No schema changes found` |
| rollback-only SQL smoke | passed — `ncrm04_smoke=ok`, then `ROLLBACK` |

No cloud command was run.

## Executed local SQL smoke test

One transaction inserted two products, a two-lot purchase, an actual sale, one
positive and one negative correction, a pending sale, and a FIFO-shortage sale.
It asserted all of the following, then rolled itself back:

1. The signed adjustment gave the expected FIFO warehouse quantity and dated
   management variance; the pre-existing fixed sale snapshot was unchanged.
2. Weight, value, and manual allocation each summed to the source fee exactly.
   `SET CONSTRAINTS ALL IMMEDIATE` also executed the deferred invariant.
3. A non-actual sale remained `pending`; the shortage sale became `estimated`
   and appeared as `sale_cogs_estimated` in `v_data_quality`.
4. `is_outlet` defaulted to `false`; `purchase_lot_statuses` still contained
   exactly its six pre-existing codes.

Key output:

```text
BEGIN
DO
SET CONSTRAINTS
ncrm04_smoke=ok
ROLLBACK
```

## Rollback

Local-only rollback: remove
`ncrm/supabase/migrations/0006_inventory_ledger_foundation.sql`, then run
`cd ncrm && npx supabase db reset`.

If this migration is later applied to cloud Supabase, do not use that local
rollback against production. Prepare a dedicated reverse-DDL migration that
drops the NCRM-04 objects and restores `sale_items_cost_state_chk` to
`('provisional', 'actual')` only after confirming no consumer depends on the
new ledger objects.

## Risks / follow-up boundary

- This changes FIFO inputs and COGS state only for future local schema writes;
  it does not alter existing fixed sale snapshots during migration.
- NCRM-03 is intentionally not re-imported or reconciled here. Its signed
  corrections and the legacy-average versus FIFO comparison are a separate
  follow-up after this foundation has passed the local smoke cases.
- Mystery fulfillment, returns, KPI/dashboard rewrites, and live deployment
  remain NCRM-05/06/07 or owner-driven work.
