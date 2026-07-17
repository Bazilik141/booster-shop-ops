# NCRM-03 — Round 3: import history and KPI reconciliation

Date: 2026-07-16  
Scope: local Docker Supabase only; frozen CSV snapshot `2026-07-16`; no cloud action, migration, schema change, commit, or live CRM write.

## Outcome

Round 3 is reproducible and the availability rule is now general: it detects a real historical shortage for a SKU on a sale/writeoff date and makes the earliest required warehouse lot available from that movement date. It does not bulk-backdate every lot.

However, NCRM-03 cannot meet the handoff acceptance criterion that only `WRT-0051` and `WRT-0052` remain as a stock residual. After the pre-receipt layers are made available, the source-to-DB quantity difference is exactly **-29 units**: the signed total of all six negative legacy rows, including `WRT-0094`–`WRT-0097`. The four paired rows are correctly documented as reclassifications, but omitting their signed balance effect does not reproduce the frozen source stock.

Status remains **In progress** pending an owner decision on the signed paired corrections. No unapproved `inventory_adjustments` were inserted.

## Scope and files changed

```
ncrm/scripts/import-history/import-history.mjs
ncrm/scripts/import-history/reconcile.mjs
ncrm/scripts/import-history/rollback_ncrm03_20260716_r3.sql
diagnostics/NCRM-03_import-history-kpi-reconciliation_round3_report_20260716.md
```

- New batch: `ncrm03_20260716_r3`; Round-2 evidence remains untouched.
- No migration or constraint changed.
- The importer now assigns deterministic UUIDs from `batch + source reference` to purchase lots, sales, sale items, writeoffs, and writeoff items. This is necessary because the existing FIFO function orders same-day movements by UUID.

## General legacy availability rule

The frozen source date is never changed. For a dated lot where an earlier movement needs the lot, the audit note stores both `source_delivery_date` and `legacy_effective_availability_from`; `purchase_lots.delivery_date` is stored as `NULL` and `purchases.ordered_at` receives the effective date. This is required by the existing immutable FIFO layer-date expression:

```
coalesce(purchase_lots.delivery_date, purchases.ordered_at)
```

It preserves the original source date and makes the effective date explicit and reversible without a schema migration.

| Lot | SKU | Source delivery | Effective from | Trigger |
|---|---|---:|---:|---|
| LOT-0026 | OP-JP-OP12-BST | 2026-05-22 | 2026-05-09 | OC-FOP-0004 |
| LOT-0035 | PKM-JP-MZERO-BST | 2026-05-24 | 2026-05-08 | WRT-0012 |
| LOT-0050 | PKM-JP-MBRV-BST | 2026-06-11 | 2026-05-30 | OC-FOP-0137 |
| LOT-0051 | PKM-JP-BBLT-BST | 2026-06-15 | 2026-06-12 | WRT-0073 |
| LOT-0052 | PKM-JP-MBRV-BST | 2026-06-15 | 2026-06-11 | MAN-FOP-0001 |
| LOT-0058 | PKM-JP-OUTL-BST | 2026-07-05 | 2026-06-24 | OLX-FOP-0043 |
| LOT-0076 | PKM-JP-MBRV-BST | 2026-07-14 | 2026-06-17 | OC-FOP-0185 |

The four pre-existing undated warehouse/selling lots stay at the agreed `2026-04-01` boundary: `LOT-0019`, `LOT-0020`, `LOT-0021`, and `LOT-0070`. The simulation has zero uncovered pre-receipt movements.

## Paired signed corrections: verified evidence and conflict

The importer validates both source row shapes and cross-references before classifying the following rows as `superseded_paired_reclassification`:

| Source negative row | Counterpart | Signed SKU effect |
|---|---|---:|
| WRT-0094 | WRT-0093 | PKM-JP-MSYM-BST -11 |
| WRT-0095 | WRT-0093 | PKM-JP-MDEX-BST -1 |
| WRT-0096 | WRT-0093 | PKM-JP-OUTL-BST -6 |
| WRT-0097 | WRT-0098 | PKM-JP-MBRV-BST -1 |

The two expressly deferred NCRM-13 rows are also preserved separately:

| Row | SKU | Signed qty |
|---|---|---:|
| WRT-0051 | PKM-JP-MBRV-BST | -3 |
| WRT-0052 | PKM-JP-MZERO-BST | -7 |

After all positive historical movements are modelled, frozen source stock vs local DB is:

| SKU | Source qty | DB qty | DB − source | Explained by accepted NCRM-13 only? |
|---|---:|---:|---:|---|
| PKM-JP-MBRV-BST | 30 | 26 | -4 | No: -3 WRT-0051 plus -1 WRT-0097 |
| PKM-JP-MDEX-BST | 5 | 4 | -1 | No: WRT-0095 |
| PKM-JP-MSYM-BST | 17 | 6 | -11 | No: WRT-0094 |
| PKM-JP-MZERO-BST | 36 | 29 | -7 | Yes: WRT-0052 |
| PKM-JP-OUTL-BST | 32 | 26 | -6 | No: WRT-0096 |
| **Total** | **461** | **432** | **-29** | **No** |

This proves the four paired negative rows cannot be treated as already absorbed in the stock ledger. They are paired operationally, but their restoring side is absent from the local import because signed inventory corrections are deliberately outside NCRM-03.

## KPI reconciliation

| Metric | Frozen source | Local R3 | Difference |
|---|---:|---:|---:|
| Warehouse quantity | 461 | 432 | -29 units |
| Warehouse PRRO cost | 78,131.06 | 75,836.71 | -2,294.35 UAH |
| Warehouse management cost | 82,818.31 | 80,386.67 | -2,431.64 UAH |
| Asset PRRO cost | 110,820.08 | 108,526.19 | -2,293.89 UAH |
| Asset management cost | 117,468.66 | 115,037.58 | -2,431.08 UAH |
| June revenue | 40,324.75 | 40,324.75 | 0.00 UAH |
| June contribution margin (policy-adjusted) | 9,706.63 | 9,706.60 | -0.03 UAH |

### Effect on the earlier ~8,000 UAH warehouse gap

| Stage | Warehouse management gap vs source | Change from prior stage |
|---|---:|---:|
| Raw Round-2 baseline | +6,807.78 UAH | — |
| Round-2 undated-lot availability rule | +255.54 UAH | -6,552.24 UAH |
| Round-3 dated pre-receipt availability rule | -2,431.64 UAH | -2,687.18 UAH |

Round 2 already proved that Mystery reconstruction has a **0.00 UAH direct warehouse effect**; its role is correct source/audit/COGS linkage. The Round-3 availability rule removes 38 units of false surplus relative to Round 2 (470 → 432), but it exposes the omitted signed corrections. It therefore should not be described as an additional clean “gap closure”: the signed gap crosses zero and reveals the actual NCRM-13 boundary.

## Verification

- Dry run: `dry_run=ok writes=0`.
- Source validation: all four paired rows reference and match `WRT-0093`/`WRT-0098`; only `WRT-0051` and `WRT-0052` remain in the deferred list by the current classification.
- Local reset: migrations `0001`–`0010` applied; no new migration.
- Import batch: 123 sales, 192 sale items, 95 writeoffs, 142 writeoff items, 62 Mystery contents.
- Rollback: batch sales/purchases/writeoffs all returned to zero; four active operational Mystery types remained. The legacy inactive `MBX` / `MBX-XL` seed types also remain by design.
- Replay: exact same warehouse quantity, PRRO, and management value after deterministic UUIDs (all deltas `0.00`).
- No legacy Mystery stock alert; June revenue exact; contribution-margin rounding residual is `-0.03 UAH`.

## Rollback / rerun

```powershell
Get-Content ncrm/scripts/import-history/rollback_ncrm03_20260716_r3.sql -Raw |
  docker exec -i supabase_db_booster-shop-ncrm psql -U postgres -d postgres -v ON_ERROR_STOP=1
```

Then rerun only with explicit acknowledgement:

```powershell
node ncrm/scripts/import-history/import-history.mjs --apply --acknowledge-legacy-assumptions --batch=ncrm03_20260716_r3
```

## Required decision

To satisfy the frozen source stock, amend the classification of `WRT-0094`–`WRT-0097` from “superseded with no balance effect” to **paired signed inventory corrections deferred to NCRM-13**. NCRM-13 can then model them atomically as two reclassifications (three-source-SKU → MZERO, and MBRV ↔ OUTL), alongside `WRT-0051` / `WRT-0052`.

If the four rows must remain ignored, this report is the proof that NCRM-03 must explicitly retain a `-19` unit paired-reclassification residual in addition to the `-10` unit NCRM-13 residual; the stated “only two rows remain” criterion cannot be true.

## Closure decision (owner, 2026-07-16)

NCRM-13's scope reverts to all six negative rows, net **-29 units**, grouped as: (1) three-SKU→MZERO reclassification (`WRT-0093`-`WRT-0096`), (2) `MBRV`↔`OUTL` transfer (`WRT-0097`/`WRT-0098`), (3) two standalone inventory-count corrections (`WRT-0051`, `WRT-0052`). This fully and exactly explains the entire remaining warehouse residual — `-29` units / `-2,431.64` UAH management value / `-2,294.35` UAH PRRO value — with zero unattributed difference.

**NCRM-03 is closed `Done`** on this basis: every KPI discrepancy against the frozen 2026-07-16 source is traced to a named, scoped, already-tracked follow-up (NCRM-13), not to an import defect. Nothing was backdated, netted, or fabricated to force a numeric match. Batch `ncrm03_20260716_r3` is the final import state; local Docker Supabase only, no cloud/live-CRM writes. NCRM-13 will apply its own signed-adjustment model against these six rows and close the residual for good.
