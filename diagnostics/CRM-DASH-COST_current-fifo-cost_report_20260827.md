# Codex Report — CRM-DASH-COST: current FIFO cost in the SKU dashboard

Date: 2026-08-27

## Scope

Implemented the requested read-only column **«Поточна собівартість»** in the dashboard SKU table, immediately after **«Гранична закупка»**.

`sku_list` now reads the management unit cost from `Склад!J`, which is the established current weighted FIFO cost of active inventory. If that cell has no usable cost, the API selects the newest eligible historical lot in `Закупки` (`На складі UA`, `На складі`, `Частково продано`, or `Продано`). If neither source can calculate a value, it returns `null` and the dashboard renders `—`.

No CRM sheet values, formulas, historical sales costs, FIFO calculations, or inventory statuses are changed.

## Files touched

```
crm/apps-script/Code.gs                                      — API field and bounded FIFO-cost read helper
dashboard/booster-dashboard.html                             — SKU-table column rendering
crm/apps-script/tests/sku-current-cost-dashboard.test.mjs    — helper and fallback regression coverage
dashboard/tests/dashboard-contract.test.mjs                  — dashboard-column contract
```

## Local verification

```
20 CRM Apps Script tests passed
Dashboard syntax and contract tests passed
Code.gs parse OK
git diff --check passed
```

The focused regression test proves:

- an in-stock SKU uses its existing `Склад!J` FIFO value;
- a SKU without that value uses the latest eligible lot, not an all-time average;
- an SKU with no calculable cost stays empty for a dashboard `—`.

## Deployment and owner QA gate

The repository Apps Script file is a local mirror, not a live deployment. Its `SOURCE_STATE.md` identifies the current mirror as a post-V148 local candidate, so the owner must first reconcile the intended candidate with the live Apps Script source, then publish a new Web App version.

After publishing:

- [ ] Run **Booster CRM → Оновити собівартість складу** once.
- [ ] Reload dashboard → **Товари** and confirm the new column is after **Гранична закупка**.
- [ ] Check one SKU with stock against `Склад!J`.
- [ ] Check one zero-stock SKU against the last eligible `Закупки` lot.
- [ ] Check one SKU with no purchases shows `—`.

## Risks

Low local-code risk: this is a read-only API extension and UI column. Live correctness still depends on the existing FIFO refresh having completed and on owner publication/QA.
