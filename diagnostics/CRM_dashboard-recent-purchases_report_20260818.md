# Codex Report — CRM dashboard recent purchases

Date: 2026-08-18

## Scope

Fix the CRM dashboard recent-purchases selection so the 20-row limit keeps the
newest eligible purchase lots by CRM append row. No live Sheet values, formulas,
Apps Script deployment, dashboard publication, Notion properties, or roadmap
state were changed.

## Live evidence

Read-only inspection of Booster Shop CRM — облік товарів, Закупки!A1:T309:

- LOT-0140 is row 135, PKM-JP-INFX-BBX, supplier reference 1156931401, blank
  Ukraine-delivery date, status Замовлено.
- There are 22 eligible open lots.
- The previous implementation sorted by numeric supplier reference ascending,
  then returned only the first 20. LOT-0140 ranked 22nd and was omitted.

## Files touched

    crm/apps-script/Code.gs
    crm/apps-script/tests/recent-purchases.test.mjs
    diagnostics/CRM_dashboard-recent-purchases_report_20260818.md

## Change

recent_purchases now dispatches to apiRecentPurchasesForUpdate_. The helper
retains the existing open-lot filters, sorts by row_index descending, then
applies the existing 20-lot limit. The older lots therefore leave the list
before a newly appended OLX or other non-ZenMarket reference does.

## Local verification

    node crm/apps-script/tests/recent-purchases.test.mjs
    Recent purchases return the newest open lots first

    Get-Content crm/apps-script/Code.gs -Raw | node --input-type=module ...
    Code.gs parse passed

    git diff --check
    passed

The focused test covers 22 eligible open lots including LOT-0140 with the
large OLX reference, and confirms that delivered and stocked lots remain
excluded.

## Deployment and rollback

Owner-reported CRM Web App V130 was published on 2026-08-18 at 14:36 Kyiv,
and the owner reported dashboard QA done. The post-deploy `integrity_check`
returned `clean:true`, `problems:[]`, and `elapsed_ms:12184`.

This repository file remains an Apps Script mirror; the publication report is
not a fresh byte-for-byte export comparison.

Rollback before or after publication: restore the previous Code.gs revision,
publish a new Web App version, then hard-refresh the dashboard. No spreadsheet
data rollback is needed because this change is read-only.

## Post-deploy QA checklist

- [x] CRM Web App V130 published.
- [x] Owner reported dashboard QA done.
- [x] Post-deploy integrity check is clean.

## Side effects / risks

Low. The limit remains 20; only its ordering changes from supplier-reference
order to CRM insertion order. The Web App publication gate and owner QA remain
required.

## Follow-up after V131 — tracked parcel completeness (not deployed)

Read-only live evidence on 2026-08-18 found six open `Закупки` rows with
`LX328130128JP`: `LOT-0093` at row 88 and `LOT-0123` through `LOT-0127` at
rows 114–118. The dashboard showed only the latter five because the API selected
the newest twenty open lots before client-side parcel grouping.

The local `apiRecentPurchasesForUpdate_()` follow-up retains the twenty newest
rows and adds every open sibling that has the same non-empty track number as one
of those rows. This prevents partial tracked parcels without merging unrelated
lots whose track numbers are blank. The focused regression test now covers the
six-lot parcel where the oldest sibling falls outside the initial twenty.

This follow-up is not in CRM V131. It requires a new Apps Script Web App
publication and a dashboard refresh; success is `LX328130128JP` displaying
`6 лот.` including `PKM-JP-MSYM-BBX`.
