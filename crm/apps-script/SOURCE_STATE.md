# Main CRM Apps Script — repository mirror state

**This folder is a MIRROR of the live bound Apps Script project of the main Booster CRM
spreadsheet. It is evidence, not a deployment target.** Editing `Code.gs` here changes nothing on
the live system. Deployment is always: owner pastes into the live script editor and publishes a
new Web App version.

| Field | Value |
|---|---|
| Mirror file | `crm/apps-script/Code.gs` |
| Baseline pulled from live | 2026-08-08, 11:41 Kyiv (owner export `CodeJS - CRM.txt`) |
| **Mirror content deployed to live** | **2026-08-08, owner-reported** — owner pasted this exact file into the bound CRM project and published a new Web App version |
| Deployed Web App version number | **V95, exported 2026-08-08 19:31 Kyiv.** Owner export re-verified **2026-08-09: identical to this mirror apart from CRLF line endings.** Supersedes V92 (2026-08-08 15:23) and V89 (2026-08-04) |
| Live-verified after deploy | **Yes.** `3D-P-014` owner QA on 2026-08-08 produced four correct journal outcomes through the live Web App (`apiAddSale_` create, `apiUpdateSale_` create-on-update, `apiUpdateSale_` noop, `skipped_no_3dp_sku`), and `3D-P-022` was proven by `ACC-3D-DITTO-410` syncing end to end |
| Local syntax check | `node --check` passed 2026-08-08 |
| Previous repo copy | `Booster Shop CRM - Apps_Script_код 29.07.2026.csv` (2026-07-29, pre-V87/V89) — superseded, keep for history only |

## Mirror status

**Local pending changes: 3D-P-010 WP4 and 3D-P-023.** The V92 mirror remains the high-confidence live baseline.
This local `Code.gs` adds one call from `updateSaleStatus()` to the existing
`sync3dpPackagingCost_(sales, order, rows, 'updateSaleStatus')` wrapper after cache invalidation
and before the form is cleared. It also formats existing date-valued journal timestamps as Kyiv text
in `apiSyncJournal_`, without changing the underlying Sheet cell type. It is not deployment proof:
after the owner publishes a new CRM Web App version, export `Code.gs` again and record that version above.

Timeline that ties the prior baseline to the deployment: **V92 published 15:23**, first QA journal
entry **15:47** — so every 2026-08-08 QA result in `3D-P-014` and `3D-P-022` was produced by V92.

One caveat before the next task treats the replacement deployment as proof: byte-identity will need
a post-deploy export. The V92 identity itself was inferred from the owner pasting the prior file
wholesale, so it is high-confidence rather than independently proven.

## Companion mirror — 3D-P Apps Script

| Field | Value |
|---|---|
| Mirror file | `3d-print/apps-script-3dp-api/Code.gs` |
| Last verified byte-identical to live | **2026-08-09** — owner export identical apart from CRLF |
| Deployed Web App version | **V10, exported 2026-08-08 21:53 Kyiv.** Supersedes V7 (2026-08-03 20:55) |

> ✅ **2026-08-09 — both mirrors re-verified against fresh owner exports. Byte identity restored.**
> `diff` against the owner's exports (CRM V95, 3D-P V10) is empty apart from CRLF line endings, so
> both mirrors are trustworthy again.
>
> Confirmed present in the exported live source, not inferred:
>
> - 3D-P V10 contains `onEdit`, `3dp_setup_3dp024` and the `Налаштування!$B$5` defect-rate
>   reference — i.e. `3D-P-015`, both fixes and `3D-P-024` are all live;
> - CRM V95 contains `skipped_sku_not_in_nomenclature` and `CRM_3DP_SALES_FROZEN_HEADERS_` — i.e.
>   the frozen `T:W` schema gate and the FIX1 journal outcome are live.
>
> Corroborating runtime evidence: `diagnostics/3D-P-015_live-migration_20260808_205617.json`, and
> the owner's live confirmation that `1:39` normalises to `1.65`.
>
> Note the export timestamps: CRM V95 is 19:31 and 3D-P V10 is 21:53 on 2026-08-08. `3D-P-024`
> landed only on the 3D-P side, which is why the CRM export is the earlier of the two and is still
> current.

The 3D-P script was unchanged between V7 and 2026-08-08; `3D-P-014` and `3D-P-022` landed entirely
on the CRM side. Everything after that is the `3D-P-015` / `3D-P-024` family described above.

## Rule (OPS-CODEMIRROR)

1. Any task that reads, plans against, or patches either Apps Script project **checks the pull
   date in this file first**. If the mirror is older than the change being planned, ask the owner
   for a fresh export before writing a handoff.
2. Whoever changes a live script is responsible for refreshing the mirror **in the same session**,
   together with the pull date and the deployed version above.
3. Never assume a deployed version number from source alone. Source and deployment are separate:
   editing the script does not update the published Web App.
4. The mirror must never contain tokens. Both projects keep secrets in Script Properties only;
   if a token ever appears in an export, stop and tell the owner instead of committing it.

## Companion mirror

The 3D-P project mirror is `3d-print/apps-script-3dp-api/Code.gs`. Verified **2026-08-08**: the
owner's live export is byte-identical to the repository copy apart from CRLF line endings, so the
3D-P side is in sync and the corrected 3D-P-010 source is genuinely live.

## Verified anchors in this pull (2026-08-08)

Facts read directly from the mirrored source, replacing earlier inference:

- `sync3dpSales_` exists and is reached only through the compatibility wrapper
  `sync3dpPackagingCost_`, which is called from exactly **two** places: `apiAddSale_` and
  `apiUpdateSale_`. Both are Web App (`doPost`) paths.
- `updateSaleStatus()` (and its alias `updatePaymentStatus()`) contains **no 3D-P call of any
  kind**. Finding 9 of `diagnostics/3D-P_live-schema-audit_20260803.md` is now proven from source
  rather than inferred from execution logs.
- Product sale costing is **FIFO over `Закупки` lots**: `getFifoCostBatches_` sorts batches
  ascending by delivery date (`batches.sort((a, b) => a.sort - b.sort || a.row - b.row)`), so the
  **oldest** lot is consumed first.
- Consumables (`Розхідники`) work differently and have **no lot model at all**:
  `getAutoConsumableInfo_` reads a single row per consumable — name, unit cost, initial qty, plus
  replenishment qty — and returns one current `unitCost`. There is no price history and no batch
  selection for consumables.
- `Списання` (write-offs) already exists with per-order consumable attribution via
  `getAutoConsumableUnitCost_`.
