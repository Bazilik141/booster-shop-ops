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
| Deployed Web App version number | **UNKNOWN since the 2026-08-08 evening republish — owner action required.** V92 (2026-08-08 15:23 Kyiv) is superseded; the owner republished this file during `3D-P-015`. Previous known were V92 → V89 (2026-08-04) |
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
| Last verified byte-identical to live | 2026-08-08 (pre-`3D-P-015` state) — owner export identical apart from CRLF |
| Deployed Web App version | **UNKNOWN — owner action required.** V7 (2026-08-03 20:55) is superseded |

> ⚠ **2026-08-08 evening — both projects were republished and the version numbers were not
> recorded.** During `3D-P-015`, `3D-P-015-FIX1`, `3D-P-015-FIX2` and `3D-P-024` the owner pasted
> this repository's `3d-print/apps-script-3dp-api/Code.gs` into the bound 3D-P project and published
> a new Web App version **at least twice**, and republished `crm/apps-script/Code.gs` once. The
> deployments are proven to have taken effect by live behaviour, not by an export:
>
> - `3dp_setup_3dp015` executed against the live workbook — evidence
>   `diagnostics/3D-P-015_live-migration_20260808_205617.json`;
> - `3dp_setup_3dp024` executed and the `onEdit` normaliser was confirmed live (`1:39` → `1.65`);
> - `Налаштування!B5` defect rate created without overwriting the owner's `B2 = 0.15`.
>
> **Still required from the owner before any task plans against these mirrors** (rule
> OPS-CODEMIRROR, step 2): the published Web App version number for each project, and a fresh
> export of both live `Code.gs` files to re-establish byte identity. Until then, treat the deployed
> version as unknown and do **not** state a version number anywhere.

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
