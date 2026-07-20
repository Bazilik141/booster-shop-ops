# Codex Report — NCRM-06: Returns and cost quality

Date: 2026-07-14

## Scope

Implemented one local, additive Supabase migration on top of `0001`–`0007`.

- `refund_items` links each return line to both `refunds` and the original `sale_items` row; conditions are restricted to `money_only`, `resellable`, `damaged`, and `mystery_unopened`.
- A deferred quantity constraint blocks total returned quantity above the original sold quantity. Resellable lines snapshot the original frozen sale COGS as a new reversal record and create a FIFO warehouse return layer. Money-only and damaged lines create neither.
- Future Mystery commits snapshot every component cost in `mystery_contents`. `fn_reverse_mystery_fulfillment(...)` creates an unopened-Mystery return, restores each confirmed pack component as an immutable FIFO supply layer, and transitions only the linked committed fulfillment to `reversed`.
- `v_data_quality` keeps its five existing branches and appends return/header/quantity/Mystery-state/restock-consistency checks.

Deliberate boundary: no edit to `0001`–`0007`, no KPI/reporting view redesign (NCRM-07), no importer/app/repository changes, no cloud Supabase, CRM, Apps Script, or OpenCart write.

## Owner-approved decisions

1. Future Mystery commits freeze per-component PRRO and management cost snapshots. A legacy committed Mystery without those snapshots is intentionally blocked from automatic reversal and appears in `v_data_quality`; it is not reconstructed from mutable historical FIFO.
2. An unopened Mystery restoration returns confirmed packs only. Holo and packaging/consumables are not separately restored or reversed because the current schema has no independent stock layer for them.
3. `refunds.restock` remains an independent historical header field. A mismatch with line conditions is a warning in `v_data_quality`, not a migration-time blocker.

## Files touched

```text
ncrm/supabase/migrations/0008_returns_cost_quality.sql
diagnostics/NCRM-06_returns-cost-quality_report_20260714.md
```

## Dry-run result

```text
npx supabase db reset
Applying migration 0001_stage1_core.sql...
...
Applying migration 0008_returns_cost_quality.sql...
Finished supabase db reset on branch master.

npx supabase db diff --local
No schema changes found
```

Focused local Docker smoke test, followed by a final `db reset` cleanup:

```text
money_only: no return layer, zero reversal
resellable: return layer qty=1, PRRO=100, management=100
damaged: no return layer
over-refund: blocked by deferred refund quantity constraint
Mystery commit: component snapshot PRRO=100, management=100
Mystery unopened return: state=reversed, restored_components=1, restored_qty=5
post-return FIFO sale: consumed the returned layer at PRRO=100, management=100
final: smoke=ok, refund_items=4, mystery_return_components=1, return_layers=2
```

## SQL validation

`npx supabase db reset` is the SQL syntax/application gate and passed after the migration's FIFO-view column type was explicitly preserved as `numeric(12,2)`. PHP is not part of this schema-only task, so `php -l` is not applicable.

## Idempotency

Supabase records the migration and does not rerun it after application. Repeated local `db reset` applied `0001`–`0008` cleanly. `fn_reverse_mystery_fulfillment(...)` rejects a second unopened return for the same Mystery sale item; `mystery_return_components` are insert-only and function-gated.

## Rollback

Local-only rollback:

```bash
rm ncrm/supabase/migrations/0008_returns_cost_quality.sql
cd ncrm && npx supabase db reset
```

If this is ever applied to cloud Supabase, use a dedicated reverse-DDL migration for only NCRM-06 objects; do not rewrite migration history or modify finalized sales.

## Run command (owner)

```bash
cd ncrm || exit
npx supabase db reset
npx supabase db diff --local
```

## Post-deploy QA checklist

- [ ] Create a money-only, resellable, and damaged return against fixed-cost sale items; confirm only resellable creates a warehouse layer and COGS reversal.
- [ ] Attempt total return quantity above the original `sale_items.qty`; confirm the transaction is rejected.
- [ ] Commit a new Mystery fulfillment, then use `fn_reverse_mystery_fulfillment(sale_item_id, refund_id)` for an unopened full return; confirm `reversed`, one restored row per `mystery_contents` row, and unchanged `sale_items` COGS.
- [ ] Attempt direct `mystery_unopened`/component inserts and a second reversal; both must fail.
- [ ] Review `v_data_quality` for legacy committed Mystery rows missing snapshots before any real cutover.
- [ ] Explicitly confirm no cloud Supabase, live CRM, Apps Script, or OpenCart system was written.

## Side effects / risks

Returns are financial and inventory events. Incorrect condition selection changes FIFO valuation from the refund date onward. The migration therefore derives normal-resellable reversal values from frozen sale COGS, preserves original sale rows, and safe-fails legacy Mystery reversals with no component snapshot rather than guessing a historical cost.
