# Main CRM Apps Script — repository mirror state

**This folder is a MIRROR of the live bound Apps Script project of the main Booster CRM
spreadsheet. It is evidence, not a deployment target.** Editing `Code.gs` here changes nothing on
the live system. Deployment is always: owner pastes into the live script editor and publishes a
new Web App version.

| Field | Value |
|---|---|
| Mirror file | `crm/apps-script/Code.gs` |
| Pulled from live | **2026-08-08, 11:41 Kyiv** (owner export `CodeJS - CRM.txt`) |
| Lines / bytes | 4104 / 242470 (LF-normalised from the CRLF export) |
| Deployed Web App version at pull time | **V89 (owner-reported 2026-08-04)** — not independently verifiable from source |
| Local syntax check | `node --check` passed 2026-08-08 |
| Previous repo copy | `Booster Shop CRM - Apps_Script_код 29.07.2026.csv` (2026-07-29, pre-V87/V89) — superseded, keep for history only |

## Local pending change

`Code.gs` now contains prepared local **3D-P-014 rev 2** journal work and the **3D-P-022**
SKU-trigger alignment. This file is not deployment proof: after the owner pastes it into the bound
CRM Apps Script project and publishes a new Web App version, export `Code.gs` again and record the
version above. Do not claim byte-identical CRM mirror status before that refresh.

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
