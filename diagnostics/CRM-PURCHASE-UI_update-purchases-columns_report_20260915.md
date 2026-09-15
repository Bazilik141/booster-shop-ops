# Codex Report — CRM-PURCHASE-UI: purchase update columns and order sorting

Date: 2026-09-15

## Scope

The dashboard's `Закупки` update tab now displays the CRM product name and the
lot's goods value in UAH. `ORDER REF` is an interactive header that toggles
numeric-natural ascending and descending order. No purchase, formula, or other
CRM data is written by this change.

## Files touched

```
crm/apps-script/Code.gs                         — exposes name and lot value in recent_purchases
dashboard/booster-dashboard.html                — renders two columns and ORDER REF sort toggle
crm/apps-script/tests/recent-purchases.test.mjs — API fields regression coverage
dashboard/tests/purchase-update-columns.test.mjs — UI contract and sorting coverage
dashboard/tests/dashboard-contract.test.mjs     — static contract coverage
```

## Local validation

```
node crm/apps-script/tests/recent-purchases.test.mjs
Recent purchases return the newest open lots first

node dashboard/tests/purchase-update-columns.test.mjs
Purchase update columns and ORDER REF sorting are wired

node --check <extracted dashboard script>
passed

git diff --check -- <scoped files>
passed
```

`node dashboard/tests/dashboard-contract.test.mjs` remains blocked before its
purchase assertions by an existing 3D category-contract mismatch:
the dashboard list lacks `Кейс / контейнер для зберігання`. This task does not
touch that list or its API mapping.

## Publication and QA gate

The dashboard file is local and the Apps Script mirror is not a deployment
target. The owner must paste the reviewed `Code.gs` delta into the bound CRM
project and publish a new Web App version before the dashboard can receive the
two new API fields.

- [ ] Open `Оновлення записів → Закупки` after publication.
- [ ] Confirm `Назва` equals the product name for a known open lot.
- [ ] Confirm `Сума лоту, грн` equals that lot's `Вартість лоту, грн` cell.
- [ ] Click `ORDER REF`; confirm ascending order, then descending order.
- [ ] Confirm the selection checkboxes and the 10-lot batch save flow still work.

## Risk and rollback

Risk is low: read-only response fields and client-side ordering only. Roll back
by restoring the four source/test changes; no spreadsheet data or formulas need
restoration.
