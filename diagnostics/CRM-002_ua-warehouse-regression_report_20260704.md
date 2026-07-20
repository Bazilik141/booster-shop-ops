# Codex Report — CRM-002: regression of `На складі UA`

Date: 2026-07-04

## Scope

Diagnosed the missing cost and stock-chain behavior reported against
`OC-FOP-0203`. Restored `На складі UA` support in the current Apps Script
source for FIFO, lot auto-status, and current warehouse cost calculations.
No purchase, sale, stock, payment, or order-status values were changed.

## Evidence

- `Продажі!A191:AF191`: `OC-FOP-0203`, SKU `PKM-JP-SVEX-BLR`, quantity 1,
  payment `Не оплачено`, order status `Нове`, cost method `Відкладено`.
- `Закупки!A62:T62`: `LOT-0067`, the same SKU, quantity 1, status
  `На складі UA`.
- `Склад!A45:T45`: physical stock formula already recognizes
  `На складі UA`; current stock is 1 and potential revenue is 2100 грн.
- `РРЦ!A45:H45`: RRC for the SKU is 2100 грн.
- The current `Apps_Script_код` source had regressed to legacy-only status
  maps in `getFifoCostBatches_()`, `updateLotStatuses()`, and
  `updateSkuCurrentCost_()`.

## Root cause

The CRM-002 status fix from 2026-06-27 was later overwritten when a newer full
Apps Script source for the Telegram/news work was pasted. The new source
restored old maps containing only `На складі`, `Частково продано`, and
`Продано`.

`OC-FOP-0203` also remains intentionally excluded from sale consumption while
it is `Не оплачено / Нове`. The existing financial rule counts a sale only
when payment is `Оплачено` or order status is `Відправлено`/`Отримано`. This
rule was not changed.

## Files touched

```text
patches/CRM-002_ua-warehouse-regression-hotfix_20260704.js
diagnostics/CRM-002_ua-warehouse-regression_report_20260704.md
Google Sheet: Apps_Script_код!A1508,A2159:A2160,A2181,A2238
```

The JS patch is a complete current `Code.gs` source, based on
`MKT-TG-005_cleanup-and-trigger_20260704.js`, with the five warehouse-status
corrections merged in.

## Implemented

1. Added `На складі UA` to `getFifoCostBatches_()`.
2. Added `На складі UA` to `updateLotStatuses()` input and update maps.
3. Preserved `На складі UA` for an unsold lot instead of converting it to the
   legacy `На складі`.
4. Added `На складі UA` to `updateSkuCurrentCost_()`.
5. Updated and re-read the five exact source-copy cells.

## Dry-run result

```text
Target source rows re-read before write: PASS
Source-copy readback after write: PASS (5/5 exact values)
Order/purchase/stock/RRC evidence: PASS
Bound Apps Script deployment: NOT PERFORMED
```

## Syntax check

```text
node --check CRM-002_ua-warehouse-regression-hotfix_20260704.js
exit_code=0
```

## Idempotency

Reapplying the same five source lines produces the same source. Running
`updateLotStatuses()` repeatedly keeps an unsold `На складі UA` lot unchanged
and only moves it to `Частково продано` or `Продано` when actual sales exist.

## Backup and rollback

Google Drive backup:

`Booster CRM Apps_Script_код backup 2026-07-04 CRM-002 regression`

Spreadsheet ID:

`16Dwoo5jr9doXxg1AoMEHRiq4BA_OBQoPOPvaot4kYkQ`

Rollback:

1. Restore the previous bound Apps Script deployment version.
2. Restore the five source-copy rows from the backup spreadsheet if required.
3. No CRM transaction rows need rollback because no transaction data changed.

## Deployment boundary

`Apps_Script_код` is only the review/source mirror. The bound Apps Script
project and Web App deployment were not changed by Codex.

Owner deployment:

1. Replace the bound Apps Script `Code.gs` with
   `patches/CRM-002_ua-warehouse-regression-hotfix_20260704.js`.
2. Save and deploy a new Web App version.
3. Run `updateLotStatuses()` once.

## Post-deploy QA checklist

- [ ] `updateLotStatuses()` runs without errors.
- [ ] Unsold `LOT-0067` remains `На складі UA`.
- [ ] Mark `OC-FOP-0203` `Оплачено` or `Відправлено` only when factually true.
- [ ] After that factual status update, the row gets FIFO cost from
  `LOT-0067`, and `Склад!H45` changes from 1 to 0.
- [ ] `РРЦ!E45` and sale price remain 2100 грн.
- [ ] `action=summary`, `orders`, `stock_alerts`, and `sku_list` still respond.

## Side effects / risks

Medium CRM risk until owner deploy and one real status-transition smoke test.
The patch does not change the existing rule that a new unpaid order is not yet
counted as an actual sale.
