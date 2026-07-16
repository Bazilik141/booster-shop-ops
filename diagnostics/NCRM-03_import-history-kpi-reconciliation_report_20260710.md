# Codex Report — NCRM-03: Import history from Sheets + KPI reconciliation

Date: 2026-07-10  
Status: `In progress` — the frozen import is reproducible and the local batch applied successfully, but the reconciliation DoD is not met.

## Scope and source snapshot

The owner-provided CSV snapshot is frozen under `ncrm/import/raw/2026-07-10/`:

- `sales.csv`, `purchases.csv`, `rrc.csv`, `products.csv`, `writeoffs.csv`, `consumables.csv`
- reference-only: `dashboard_anchor.csv`, `stock_reference.csv`, `expenses_reference.csv`

`Apps_Script_код.csv` was inspected as read-only reference and was not imported. The live CRM, Apps Script, OpenCart, dashboard source, and migrations `0001`–`0005` were not modified.

## Files touched

```text
ncrm/import/raw/2026-07-10/*.csv
ncrm/scripts/import-history/import-history.mjs
ncrm/scripts/import-history/reconcile.mjs
ncrm/scripts/import-history/rollback_ncrm03_20260710.sql
```

## Import decisions

- `purchases` + `purchase_lots`: deterministic 1:1 mapping because the export contains lot-level costs and no separate order-level allocation.
- Legacy UAH totals are imported as-is with `imported_legacy_no_rate_history`.
- Lot status mapping: `В дорозі → in_transit`, `Замовлено → ordered`, `На складі/На складі UA → in_stock`, `Продано → sold`, `Частково продано → selling`.
- Products and lookup values come only from the frozen source; no invented brands/categories/games/languages.
- Sales before `cost_start_date=2026-04-01` are excluded by the handoff scope. The source negative discount on `OLX-FOP-0027` is normalized into the unit price and documented in the item note.
- Source rows with zero/negative quantity are not inserted into positive-quantity schema tables; every such row is reported.

## Execution evidence

Local reset `npx --yes supabase@latest db reset` completed successfully. The importer then completed:

```text
app_config=10
products=64
product_prices=61
purchases=91
purchase_lots=91
sales=118
sale_items=184
writeoffs=125
writeoff_items=125
consumables=14
```

The batch is `ncrm03_20260710`. Re-running apply removes the tagged batch first, so the import is idempotent at batch level.

## Reconciliation result

| Metric | Source | Local DB | Diff | Result |
|---|---:|---:|---:|---|
| Warehouse PRRO | 49,074.03 | 57,135.70 | +8,061.67 | fail |
| Warehouse management | 52,018.47 | 60,563.53 | +8,545.06 | fail |
| Stock + in-transit management | 71,474.73 | 80,019.78 | +8,545.05 | fail |
| Ordered + in-transit asset PRRO | 53,331.25 | 53,331.25 | 0.00 | pass |
| Ordered + in-transit asset management | 56,531.11 | 56,531.11 | 0.00 | pass |
| June revenue | 40,324.75 | 40,324.75 | 0.00 | pass |
| June contribution/net anchor | 9,697.83 | 9,697.80 | -0.03 | fail |

## Proven blockers

1. `Списання` contains six negative correction rows totalling `-29` units: `WRT-0051`, `WRT-0052`, `WRT-0094`–`WRT-0097`. They describe inventory restoration/transfer, not ordinary writeoffs. Schema v1 enforces `writeoff_items.qty > 0`, so these rows cannot be represented faithfully without a signed inventory-adjustment model or an explicitly approved normalization rule. The resulting quantity gaps are exact: `MSYM -11`, `MDEX -1`, `OUTL -6`, `MZERO -7`.
2. The source `Склад` value is not fully derivable from imported FIFO lots. `PKM-JP-MBRV-BBX` has source quantity `2` and source value `0.00`, while its source purchase lot is `7,803.68` PRRO / `8,271.90` management. `PKM-JP-SPIN-BST` has source quantity `40` valued at `2,527.60`, while FIFO lots produce `3,708.80`. This is a source-side average/stale-cost versus target FIFO mismatch, not a missing CSV row.
3. Two mystery-sale SKUs (`PKM-JP-MIX-MBX`, `OP-JP-MIX-MBX`) have sales but no purchase lots in the snapshot. They create negative target stock alerts while the legacy `Склад` snapshot reports zero stock; the target needs an explicit mystery-box consumption rule or a source lot/adjustment.
4. June revenue reconciles exactly, but the June contribution/net anchor differs by `0.03 UAH`; this remains a rounding/cost-definition discrepancy until the owner accepts the legacy calculation rule.

## Verification

```text
node --check ncrm/scripts/import-history/import-history.mjs  ok
node --check ncrm/scripts/import-history/reconcile.mjs        ok
dry-run                                                     ok; writes=0
apply                                                        ok; batch=ncrm03_20260710
reconcile                                                    executed; all_pass=false
php -l                                                       N/A — no PHP artifact
```

## Rollback

Use `ncrm/scripts/import-history/rollback_ncrm03_20260710.sql`. It deletes tagged rows in dependency order and preserves lookup seeds plus the original `MBX`/`MBX-XL` seed products. No live CRM or remote database write was performed.

## Owner decision required before `Done`

Choose one rule for the next revision: (a) add a signed inventory-adjustment model in a new schema task, (b) approve deterministic netting of the six signed source corrections into positive writeoff history, and (c) define how source `Склад` average/stale costs and mystery-box SKUs should map to target FIFO. Until then the correct status is `In progress`.
