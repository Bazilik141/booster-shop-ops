# Codex Report — CRM-002: reserve stock and calculate cost for new orders

Date: 2026-07-04

## Scope

Fixed the remaining `OC-FOP-0203` issue after confirming that
`На складі UA` was already handled exactly like `На складі`.

The actual blocker was the sale-state gate: unpaid orders with status `Нове`
were deliberately excluded from FIFO cost and stock consumption.

## Evidence

- `Закупки!Q62` = `На складі UA`; `LOT-0067` has 1 unit at
  1271.31 грн PRRO / 1347.59 грн management cost.
- `Apps_Script_код` already includes `На складі UA` in FIFO, auto-status,
  and current-cost status maps.
- `Склад!E45` already counted the lot as purchased.
- `Продажі!W191:X191` = `Не оплачено / Нове`.
- `isActualSaleForCost_()` returned false for that state.
- `Склад!F:F` also counted only paid/shipped/received sales.

## Files touched

```text
patches/CRM-002_new-order-reservation-fix_20260704.js
diagnostics/CRM-002_new-order-reservation_report_20260704.md
Google Sheet: Apps_Script_код!A1466
Google Sheet: Склад!F3:F201
Google Sheet: Продажі!L191:M191,AD191:AF191
```

## Implemented

1. `isActualSaleForCost_()` now treats `Нове` and `В обробці` as committed
   inventory states, alongside `Відправлено`, `Отримано`, or paid orders.
2. `Склад!F3:F201` now reserves/deducts stock for `Нове` and `В обробці`.
3. `Скасовано`, `Повернення`, and `Передзамовлення` remain excluded.
4. Existing order `OC-FOP-0203` was backfilled from `LOT-0067`:
   - PRRO cost: 1271.31 грн;
   - management cost: 1348.76 грн, including 1.17 грн logo sticker;
   - gross profit: 828.69 грн;
   - net profit: 740.74 грн.

## Dry-run / live result

```text
Склад formulas changed: 199
OC-FOP-0203 FIFO source: LOT-0067
Склад!F45 sold/reserved: 0 -> 1
Склад!H45 remaining: 1 -> 0
Продажі!V191 net profit: 2089.50 -> 740.74
```

## Syntax check

```text
node --check CRM-002_new-order-reservation-fix_20260704.js
exit_code=0
```

## Idempotency

The formula replacement was bounded to `Склад!F3:F201`; a repeat replacement
finds no old formula fragments. Re-running the Apps Script logic does not
recalculate rows already marked with a fixed FIFO method.

## Rollback

1. Restore `Apps_Script_код!A1466` to:

   `return payment === 'Оплачено' || status === 'Отримано' || status === 'Відправлено';`

2. Remove `Нове` and `В обробці` conditions from `Склад!F3:F201`.
3. Restore `Продажі!L191:M191` to blank and `AD191:AF191` to the prior
   deferred-cost values if the order must no longer reserve stock.

No purchase rows, payment status, order status, or customer fields changed.

## Deployment boundary

The live Google Sheet formulas and `OC-FOP-0203` are corrected now.
`Apps_Script_код` is still a source mirror. For future new orders, the owner
must replace the bound Apps Script `Code.gs` with the patch and deploy a new
Web App version.

## Post-deploy QA checklist

- [x] `OC-FOP-0203` net profit = 740.74 грн.
- [x] `PKM-JP-SVEX-BLR` remaining stock = 0.
- [x] `На складі UA` lot supplies FIFO cost.
- [ ] Deploy the updated bound Apps Script.
- [ ] Create one controlled `Нове / Не оплачено` order and verify immediate
  FIFO cost plus stock reservation.
- [ ] Cancel that test order and verify stock returns.

## Side effects / risks

Active unpaid orders now reserve inventory and receive FIFO cost immediately.
Cancellation and return statuses restore formula stock because they remain
explicitly excluded.
