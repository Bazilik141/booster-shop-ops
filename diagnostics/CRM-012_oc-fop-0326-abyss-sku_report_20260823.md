# CRM-012 — OC-FOP-0326 canonical Abyss Eye SKU repair

Date: 2026-08-23

## Outcome

The owner confirmed the guarded recovery was run successfully from the current Apps Script
source. The temporary `OC-FOP-0326` and earlier `OC-FOP-0320` recoveries were then removed,
and the owner reported publication as CRM V144 on 2026-08-23 at 20:11 Kyiv. This report is
retained as the audit record. No independent post-publication source export or live-row read
was performed in this cleanup session.

## Live diagnosis

- `Продажі!293:296` contains the four lines of `OC-FOP-0326`.
- Only `Продажі!F294` is invalid: it contains `PKM-JP-ABYSS-BST`; its formula in `G294` is
  `IFERROR(INDEX(... MATCH(F294; Товари!A3:A201 ...)); "")`, so it returns blank.
- `Товари!49`, `РРЦ!49`, `Склад!49`, and `Майстер_Товарів!48` all contain the canonical
  SKU `PKM-JP-ABYE-BST` with the name `Pokémon — Abyss Eye — JP — Booster`.
- The invalid SKU also caused `Продажі!L294:M294` to be recorded as `0`; these are FIFO-derived
  inputs and must not be hand-edited.

## Historical implementation

- `crm/apps-script/Code.gs`
  - Added the public CRM menu entry `Виправити SKU OC-FOP-0326 (Abyss Eye)`.
  - Added `repairOCFOP0326AbyssSku()`: validates the canonical catalogue row and one exact
    sale row, changes only `F294` from the alias to the canonical SKU, verifies the formula name,
    forces the existing FIFO recalculation for that line, refreshes warehouse costs, invalidates
    dashboard cache, and logs a bounded result.
  - A repeat is idempotent when the SKU and both cost inputs are already correct.
- `crm/apps-script/tests/repair-oc-fop-0326-abyss-sku.test.mjs`
  - Covers success, repeat, wrong-target stop, missing-catalogue stop, FIFO options, cache
    invalidation, and public-menu availability.

## Historical owner gate

The mirror was refreshed from owner-supplied live source on 2026-08-23 16:16 Kyiv, but a
local source edit is not a deployment. The owner must paste the reviewed hunk into the current
bound Apps Script source, save, publish a named Web App version, and run the public menu action.
The owner subsequently confirmed those steps were completed.

## Cleanup

- Removed the public menu entry `Виправити SKU OC-FOP-0326 (Abyss Eye)`.
- Removed `repairOCFOP0326AbyssSku()`.
- Removed its one-off test file.
- Removed `repairOCFOP0320MysteryBoxCost()` and its private
  `repairMysteryBoxOrderComponentCost_()` helper; neither had remaining runtime callers.
- Removed `crm/apps-script/tests/mystery-box-order-components-repair.test.mjs`, which covered
  only the deleted `OC-FOP-0320` temporary helper.
- Retained this diagnostic because it documents the exact defect, safe repair scope, and
  verification requirements.

## V144 publication and local verification

- Owner-reported Web App publication: CRM V144, 2026-08-23 20:11 Kyiv.
- Local validation after cleanup: Node VM parse of `Code.gs` passed;
  `order-items.test.mjs` and `catalog-sku-create.test.mjs` passed; `git diff --check` passed.
- Searches found zero references to either `OC-FOP-0326` or `OC-FOP-0320` recovery functions
  in `Code.gs` and the Apps Script test directory.
- Cleanup only removes dormant menu actions/helpers/tests. It does not rerun FIFO, change
  sales rows, or modify order totals.
- A fresh Apps Script source export is still required to prove a byte-for-byte match between
  V144 and the repository mirror. No post-release dashboard or CRM integrity QA was reported.

## Post-run acceptance

- `Продажі!F294` is `PKM-JP-ABYE-BST` and `G294` is nonblank.
- `L294:M294` are recalculated by FIFO, not manually entered.
- The order expansion shows the Abyss Eye name and updated order totals after dashboard refresh.
- Run the bounded CRM integrity check after the recovery; any new code is a blocker.
