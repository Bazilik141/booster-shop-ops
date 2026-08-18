# CRM purchase batch limit — local fix report

Date: 2026-08-18

## Outcome

The API handler for `update_purchase` had an independent hard limit of five lots.
It now accepts up to ten lots, matching the existing dashboard selection limit.

## Root cause

- `crm/apps-script/Code.gs` dispatched `update_purchase` to `apiUpdatePurchase_()`.
- That legacy implementation rejected `rawLots.length > 5` with `maximum 5 lots`.
- `dashboard/booster-dashboard.html` already declares `PURCHASE_BATCH_LIMIT = 10` and shows
  “Вибери від 1 до 10 лотів”. The “5 лот.” in a parcel header is the actual lot count in that
  shipment, not a client display limit.

## Change

- The `update_purchase` dispatch now calls `apiUpdatePurchaseBatch10_()`.
- The new helper preserves the prior payload validation, row lookup, field writes, fee allocation,
  cache invalidation, and response shape; only the maximum is raised to ten.
- The dashboard source was intentionally unchanged because its client cap and contract test already
  use ten.

## Local verification

- `node crm/apps-script/tests/purchase-batch-limit.test.mjs`
  - ten lots update successfully;
  - eleven lots return `maximum 10 lots` without mutating the eleventh lot.
- `node crm/apps-script/tests/recent-purchases.test.mjs`
- Node VM parse of `crm/apps-script/Code.gs`.
- `node dashboard/tests/dashboard-contract.test.mjs`
- `git diff --check`.

## Deployment and QA

Owner-reported CRM Web App V131 was published on 2026-08-18 at 14:52 Kyiv with `QA - ok`.
The post-deploy `integrity_check` returned `clean:true`, `problems:[]`, checked `Товари`, `РРЦ`,
`Розхідники`, `Майстер_Товарів`, and `Налаштування`; `rrp_mismatch_3dp.compared:3`,
`skipped_missing_crm_rrp:0`, `deferred:null`, `elapsed_ms:55853`.

This confirms the reported deployment and the integrity-check coverage. It is not a fresh
byte-for-byte Apps Script export comparison.
