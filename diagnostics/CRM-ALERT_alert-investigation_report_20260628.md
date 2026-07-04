# Codex Report — CRM-ALERT: negative stock and MBX writeoff alert

Date: 2026-06-28

## Scope

Investigation and correction of the two CRM alerts:

1. negative stock;
2. mystery box without booster writeoffs.

Updated one sale row in CRM and two data-quality formulas in the automation
workbook.

## Finding 1 — negative stock cause

- SKU: `PKM-JP-MBRV-BST`
- Current stock: `-1`
- Stock formula inputs: purchased `123`, sold `110`, written off `14`
- Cause: `Продажі!176`, order `OC-FOP-0196`, dated `2026-06-28`,
  quantity `1`, payment `Оплачено`, order status `Відправлено`.

The inventory reconciliation on 2026-06-27 left MBRV at zero. The new accepted
sale on 2026-06-28 moved it to `-1`.

Owner confirmed that the shipped pack was another Ninja Spinner, not Mega
Brave, and that the check had a 30 UAH discount.

## Finding 2 — OC-FOP-0187 is a false positive

- `Продажі!168`
- SKU: `OP-JP-MIX-MBX`
- Quantity: `1`
- Payment status: `Скасовано`
- Order status: `Скасовано`
- FIFO status: `Відкладено`
- No writeoff rows reference `OC-FOP-0187`.

No booster writeoff should be added for this cancelled order.

## Root cause of false positive

`Booster Shop — Майстер-дашборд автоматизацій`,
`Якість_Даних!C12:D12` filters MBX rows after 2026-06-01 and excludes only
`Статус замовлення = Повернення`.

It does not exclude:

- `Статус замовлення = Скасовано`;
- cancelled payment status.

Therefore `OC-FOP-0187` is incorrectly counted as an MBX sale requiring five
booster writeoffs.

## Recommended fix

Update both formulas in `Якість_Даних!C12:D12` so the MBX source filter excludes
cancelled rows by both payment and order status while preserving the existing
returned-order exclusion.

## Implemented

### CRM sale

Updated `Продажі!176` for `OC-FOP-0196`:

- SKU: `PKM-JP-MBRV-BST` → `PKM-JP-SPIN-BST`;
- price: `170` → `200`;
- discount: `0` → `30`;
- line total remains `170`;
- FIFO cost: `71.18 / 75.45` from `LOT-0021`;
- FIFO audit for rows 176–177 updated to `before=26` and `before=27`.

Result:

- `PKM-JP-MBRV-BST`: `-1` → `0`;
- `PKM-JP-SPIN-BST`: `5` → `4`.

### Data-quality formula

Updated `Якість_Даних!C12:D12` to exclude rows where either payment status or
order status is `Скасовано`.

Result:

- `OC-FOP-0187` no longer triggers a missing-writeoff warning;
- no writeoff was added for the cancelled order.

## Verification performed

- CRM API: `stock_alerts`, `orders&status=all&limit=500`;
- CRM sheet: `Склад`, `Продажі`, `Списання`;
- automation sheet: `Якість_Даних!A12:E12`.

Final API smoke:

- `negative_alerts = []`;
- `mystery_boxes_without_writeoffs = 0`;
- `negative_stock = 0`;
- `source_ok = true`.

## Rollback

CRM rollback:

- restore `Продажі!F176 = PKM-JP-MBRV-BST`;
- restore `I176 = 170`, `J176 = 0`;
- restore prior MBRV cost and audit values.

Automation rollback:

- restore the prior formulas in `Якість_Даних!C12:D12`.
