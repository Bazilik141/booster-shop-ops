# Codex Report — NCRM-05: Mystery fulfillment

Date: 2026-07-13

## Scope

Implemented the handoff as one additive local Supabase migration on top of `0001`–`0006`:

- reservation-aware available stock, one fulfillment per `sale_item_id`, and the `needs_assembly → reserved → committed` / cancellation-release state paths;
- four approved JP Mystery SKU seeds, generic `MBX` / `MBX-XL` archival, automatic eligibility view, and the exclusion-only `mystery_eligibility_override`;
- DB-level MBOX/content gates plus `fn_reserve_mystery_fulfillment(...)` and atomic `fn_commit_mystery_fulfillment(...)` RPCs.

Deliberate boundary: no change to `0001`–`0006`, returns/reversals, KPI views, importer, app/repository layer, cloud Supabase, CRM, Apps Script, or OpenCart.

Architectural decision inherited from NCRM-04: reservations are separate per-component rows. Active rows reduce only `v_inventory_available.available_qty`; physical stock is consumed exactly once through the committed linked MBOX writeoff. This avoids synthetic purchase lots and preserves the existing FIFO audit model.

`products.is_sealed_pack` defaults to `false`. No SKU/category heuristic or speculative backfill was used, so the future catalogue/UI owner must explicitly mark eligible sealed packs. `NULL` in `mystery_eligibility_override` means automatic inclusion; its only allowed explicit value is `excluded`.

## Files touched

```
ncrm/supabase/migrations/0007_mystery_fulfillment.sql
  — additive schema, views, seeds, DB guards, cancellation release trigger, and RPCs
diagnostics/NCRM-05_mystery-fulfillment_report_20260713.md
  — implementation and local verification evidence
```

## Dry-run result

```text
npx supabase db reset
Applying migration 0001_stage1_core.sql...
...
Applying migration 0007_mystery_fulfillment.sql...
Finished supabase db reset on branch master.

npx supabase db diff --local
No schema changes found
```

Focused local smoke tests (temporary local Docker records removed by the final reset):

```text
reserve → cancel:  state=released, reserved_qty=0, available_qty restored to 12
reserve → shipped → commit: state=committed, cost_state=actual,
  prro_unit=500.00, mgmt_unit=500.00, MBOX headers=1, available_after=7
free-form MBOX: rejected by fn_guard_mbox_writeoff
over-reserve (10 with 7 available): rejected with explicit insufficient-stock error
```

## SQL validation

`npx supabase db reset` is the syntax/application gate for this SQL migration and passed twice. PHP is not part of this schema-only task, so `php -l` is not applicable.

## Idempotency

Supabase records the migration and does not rerun it after application. Repeated local `db reset` applied the same migration cleanly twice. The immutable reference/SKU seeds use conflict-safe inserts; legacy archival uses `coalesce(archived_at, now())`.

## Rollback

Local-only rollback:

```bash
rm ncrm/supabase/migrations/0007_mystery_fulfillment.sql
cd ncrm && npx supabase db reset
```

Do not apply this migration to a real cloud project until cutover is approved. If a cloud migration has already been applied, create a dedicated reverse-DDL migration that drops only the NCRM-05 objects and reactivates `MBX` / `MBX-XL`; do not edit migration history.

## Run command (owner)

```bash
cd ncrm || exit
npx supabase db reset
npx supabase db diff --local
```

## Post-deploy QA checklist

- [ ] Mark a known non-Outlet JP sealed component with `is_sealed_pack = true`; confirm it appears only for the matching-game Mystery SKU in `v_mystery_eligible_components`.
- [ ] Reserve one ST and one XL fulfillment; cancel one before shipment and confirm its reservation is released and available quantity is restored.
- [ ] Move one reserved ST and one reserved XL to `shipped`, call `fn_commit_mystery_fulfillment(sale_item_id)`, and confirm exactly one linked MBOX header, writeoff items, contents, auto-consumables, and `cost_state = actual`.
- [ ] Attempt a direct `writeoffs.type = 'MBOX'` insert and a reservation larger than available stock; both must fail without partial rows.
- [ ] Confirm `MBX` and `MBX-XL` are inactive, absent from `v_stock_alerts`, and rejected as new Mystery sale items while historical rows remain queryable.
- [ ] Before any real cutover, confirm the future app flow calls reserve on assembly, commit only on `shipped`, and no frontend/CRM process still creates the archived generic SKUs.

## Side effects / risks

- This changes future Mystery inventory availability and COGS finalization; it is intentionally not a live deployment.
- `v_stock_alerts` was not altered per handoff. It already filters `p.is_active`, which suppresses archived `MBX` / `MBX-XL`; a future KPI/reporting task should explicitly decide virtual-bundle reporting for the new active Mystery SKUs.
- The state `reversed` is represented and guarded but has no implementation path here; NCRM-06 owns returns/COGS reversal.
