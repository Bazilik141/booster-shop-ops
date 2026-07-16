# NCRM-03 — import history / KPI reconciliation, round 2

Date: 2026-07-16  
Status: `In progress` — local import, reconciliation, rollback, and replay passed; owner review of the frozen-source exceptions is still required.  
Batch: `ncrm03_20260716`  
Scope: local Docker Supabase only. No cloud project, legacy Sheet, Apps Script, production CRM, migrations, or commits were changed.

## Outcome

The 2026-07-16 frozen export imports deterministically. Legacy Mystery component writeoffs are now connected to their sale items without creating stock alerts, and the warehouse management-cost gap decreased from 6,807.78 UAH to 255.54 UAH.

The approved Mystery reconstruction itself changes the audit/COGS linkage, not physical component quantity. Its direct warehouse effect is therefore exactly **0.00 UAH**. The 6,552.24 UAH decrease comes from a separate, documented import correction: four historical lots had a warehouse/selling status but no received date, so their known historical availability was made effective from the agreed `cost_start_date` of 2026-04-01.

## Source and import boundary

Frozen input directory: `ncrm/import/raw/2026-07-16/`

| Source tab | Raw rows | Imported / handling |
|---|---:|---|
| `Продажі` | 208 | 123 sales, 192 sale items; rows before 2026-04-01, blank SKU, and non-positive quantity are reported separately |
| `Закупки` | 98 | 94 purchases + 94 one-to-one lots; four zero-quantity lots excluded |
| `Списання` | 149 | 142 positive source item rows preserved; one zero row excluded; six negative rows deferred to NCRM-13 |
| `РРЦ` | 65 | 65 products and price rows |
| `Склад` | 65 | reconciliation anchor only; it is not copied as a synthetic adjustment |
| `Розхідники` | 14 | 14 consumables |

The source positive writeoff *item* count remains 142 in the database. Header count is intentionally not a 1:1 import:

| Writeoff representation | Count |
|---|---:|
| Ordinary source writeoff headers | 80 |
| Source-linked Mystery groups | 14 |
| Canonical MBOX headers | 15 |
| Canonical writeoff headers | 95 |
| Canonical writeoff items | 142 |

The approved `62 → 14` rule is preserved as **14 source-linked business groups**. The database has 15 MBOX headers because `OC-FOP-0200` contains two separate legacy Mystery `sale_item` rows; the NCRM-05 contract requires each MBOX header to be linked to a specific fulfillment / sale item. Collapsing those two sale items into one header would weaken that audit link. This is an intentional architectural exception to the handoff's generic “source header counts exactly” acceptance criterion, not an unreported discrepancy.

## Mystery reconstruction

| Result | Count |
|---|---:|
| Linked source component writeoff rows | 62 |
| Component quantities preserved | 80 packs |
| Exact source groups | 13 |
| Approximation groups | 1 (`OC-FOP-0219`) |
| Exact box units | 14 |
| Approximate box units | 2 |
| Generated canonical MBOX documents | 15 |
| Unlinked legacy Mystery sale units left provisional | 8 |
| Legacy Mystery negative stock alerts | 0 |

`OC-FOP-0219` is explicitly marked `legacy_import=approximation` in `mystery_fulfillments.note`: its ten source packs are known only as an aggregate for two sold boxes, not as two independently observed five-pack compositions.

Four source-linked legacy boxes contain both Pokémon and One Piece packs: `MBZ-PHYS-0001`, `OLX-PHYS-0011`, `OLX-PHYS-0012`, and `OLX-PHYS-0013`. They are imported under a narrow trusted historical exception. They do not change the approved future operational rule: new Mystery fulfillment remains same-game, JP-only, and Outlet-excluded.

Nine MBOX-like/inventory source writeoffs have no direct sale reference. They remain ordinary writeoffs and are intentionally outside `mystery_contents`.

### COGS confidence

The component facts are exact for 14 box units and approximate for two. They are **not** marked `actual` COGS:

- Several contributing purchase lots have no received date, so an auditable historical FIFO layer cannot be proven.
- The legacy sales export itself stores a 450 UAH fallback in these rows, not independently auditable FIFO COGS.
- Per the approved financial contract, a fallback-derived value cannot be labelled `actual`.

Therefore the 15 reconstructed sale items / 16 units retain the source cost snapshot as `cost_state='estimated'`, with the source WRT references and exact/approximation flag in their audit text. Their management estimate differs from flat 450 UAH by +17.00 UAH in total.

The eight unlinked legacy Mystery units remain `provisional` at 450 UAH. For the four actual June sale items this intentionally reduces legacy snapshot COGS by 8.80 UAH. It explains the +8.77 UAH legacy-vs-canonical June contribution-margin difference; the remaining 0.03 UAH is a source-row rounding difference in `MAN-FOP-0001`, not a revenue, fee, or COGS omission.

## KPI reconciliation

| Metric | Frozen source | Local canonical | Difference (canonical − source) | Result |
|---|---:|---:|---:|---|
| Warehouse quantity | 461 | 470 | +9 units | attributed below |
| Warehouse PRRO cost | 78,131.06 | 78,371.77 | +240.71 UAH | attributed below |
| Warehouse management cost | 82,818.31 | 83,073.85 | +255.54 UAH | attributed below |
| Warehouse + in-transit management cost | 93,461.48 | 93,717.02 | +255.54 UAH | attributed below |
| Asset PRRO cost (warehouse + ordered/in-transit) | 110,820.08 | 111,061.25 | +241.17 UAH | follows warehouse residual |
| Asset management cost (warehouse + ordered/in-transit) | 117,468.66 | 117,724.76 | +256.10 UAH | follows warehouse residual |
| June revenue | 40,324.75 | 40,324.75 | 0.00 UAH | exact |
| June contribution margin, legacy source | 9,697.83 | 9,706.60 | +8.77 UAH | explained provisional-Mystery policy |
| June contribution margin, policy-adjusted source | 9,706.63 | 9,706.60 | −0.03 UAH | legacy line rounding |

### What closed the former ~8k warehouse gap

| Change / cause | Management warehouse effect |
|---|---:|
| Baseline after raw import with undated warehouse lots effective only on snapshot date | +6,807.78 UAH gap |
| Documented availability boundary for four undated warehouse/selling lots | −6,552.24 UAH gap reduction |
| Approved Mystery `62 → 14 groups` reconstruction | 0.00 UAH direct warehouse effect |
| Residual after round 2 | +255.54 UAH |

The 6,552.24 UAH reduction is 96.25% of the fresh-snapshot baseline gap. It is independent of Mystery reconstruction. Mystery reconstruction prevents virtual bundle alerts and restores content/audit links, but conserves the same 62 component writeoff quantities, so it cannot change warehouse value by design.

## Remaining warehouse residual: fully identified, not hidden

The 9-unit net quantity difference is the combination of two known source-history limits:

1. **NCRM-13 signed corrections, −12 units currently absent from the import.**
   - `WRT-0094`: −11 `PKM-JP-MSYM-BST`
   - `WRT-0095`: −1 `PKM-JP-MDEX-BST`

   These source rows restore stock; they cannot be inserted into `writeoff_items.qty > 0` without the signed inventory-adjustment model. All six negative rows are deferred to NCRM-13: `WRT-0051`, `WRT-0052`, `WRT-0094`–`WRT-0097`, net −29 units / −2,084.32 UAH management value at their source snapshots.

2. **Pre-receipt consumption, +21 units still shown by canonical FIFO.**
   - `OP-JP-OP12-BST`: 20-unit sale dated 2026-05-09 precedes the supplied lot receipt date 2026-05-22.
   - `PKM-JP-BBLT-BST`: one writeoff dated 2026-06-12 precedes the supplied first receipt date 2026-06-15.

   The ledger intentionally does not consume a future cost layer. Backdating a receipt date or creating a synthetic writeoff would make the reconciliation look exact while altering the source fact. Neither was done in NCRM-03.

For four lots with *missing* received date but source status `На складі` / `Частково продано`, the importer records `legacy_effective_availability_from=2026-04-01` in its audit note while preserving `delivery_date = NULL`. This is a bounded legacy availability assumption, not a claimed receipt date, and is the reason the 6,552.24 UAH gap closed without changing a source fact.

## Verification evidence

- `node --check` passed for `import-history.mjs` and `reconcile.mjs`.
- Local `supabase db reset --yes` applied migrations `0001` through `0010` cleanly.
- Dry run: no writes; expected source mappings and exclusions reported.
- Apply: batch `ncrm03_20260716` imported successfully.
- Reconciliation: legacy Mystery stock alerts = 0.
- Referential checks: 0 orphan `mystery_contents`; 0 MBOX headers without fulfillment; 0 committed fulfillments without MBOX header.
- Rollback test initially exposed the immutable-content trigger; rollback now uses transaction-local `session_replication_role = replica` only for its correctly ordered local cleanup.
- Rollback deleted the batch fully (`sales=0`, `purchases=0`, `writeoffs=0` for the batch), retained all four operational Mystery types, and the subsequent replay imported successfully.

## Files changed

- `ncrm/scripts/import-history/import-history.mjs`
- `ncrm/scripts/import-history/reconcile.mjs`
- `ncrm/scripts/import-history/rollback_ncrm03_20260716.sql`
- `diagnostics/NCRM-03_import-history-kpi-reconciliation_round2_report_20260716.md`

## Rollback / rerun

Local rollback only:

```powershell
Get-Content -LiteralPath ncrm/scripts/import-history/rollback_ncrm03_20260716.sql -Raw |
  docker exec -i supabase_db_booster-shop-ncrm psql -U postgres -d postgres -v ON_ERROR_STOP=1
```

Dry run:

```powershell
node ncrm/scripts/import-history/import-history.mjs --data-dir='ncrm/import/raw/2026-07-16' --batch=ncrm03_20260716
```

Apply (local only):

```powershell
node ncrm/scripts/import-history/import-history.mjs --data-dir='ncrm/import/raw/2026-07-16' --batch=ncrm03_20260716 --apply --acknowledge-legacy-assumptions
```

## Owner QA / next decision

1. Confirm that the two pre-receipt source events stay visible as a legacy reconciliation residual rather than backdating a received date or creating a synthetic correction.
2. NCRM-13 should import the six signed inventory corrections using its own adjustment model; do not reroute them through writeoffs.
3. Do not mark NCRM-03 `Done` yet: owner should review this report and the remaining +255.54 UAH warehouse management residual.
