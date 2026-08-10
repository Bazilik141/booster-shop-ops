# Codex Report — OLX-FOP-0050 Mystery Mix XL cost correction

Date: 2026-08-10

## Status

**Completed and live-verified.** The `PKM-JP-MBX-XL` line in
`OLX-FOP-0050` now uses its actual seven-booster component cost.

## Root cause

The seven component units already existed as `Списання!WRT-0176:WRT-0181`.
Their total quantity is exactly seven:

- `PKM-JP-INFX-BST` x1;
- `PKM-JP-MZERO-BST` x2;
- `PKM-JP-MBRV-BST` x1;
- `PKM-JP-MSYM-BST` x1;
- `PKM-JP-WFLR-BST` x1;
- `PKM-JP-SPIN-BST` x1.

Only `WRT-0176` referenced `OLX-FOP-0050`; the following five sequential
write-offs referred to `OLX-FOP-0051` through `OLX-FOP-0055`, for which no
sales rows exist. Consequently the Mystery Mix XL sale row had fallback cost
0.00 UAH / 2.09 UAH instead of its component total.

## Live changes

One atomic spreadsheet update changed only manual fields:

- `Списання!K178:L183` now says `для формування містері бокса` and
  `Продаж OLX-FOP-0050` for all six write-off records.
- `Продажі!L267:M267` now records unit PRRO cost 734.48 UAH and unit
  management cost 780.63 UAH.
- `Продажі!AD267:AF267` now records method `MBX фактична комплектація`, the
  six-SKU cost audit, and the correction date.

No SKU, quantity, date, formula, stock count, purchase lot, or consumable
row was changed. The write-offs had already reduced stock; this pass repaired
only their sale linkage and the derived sale-cost fields.

## Read-back

- Every `WRT-0176:WRT-0181` note now points to `Продаж OLX-FOP-0050`.
- The component totals are PRRO 734.48 UAH and management 778.54 UAH.
- The Mystery Box adds its already-recorded 2.09 UAH mystery consumables,
  giving management cost 780.63 UAH. The order-level logo sticker remains
  attached once to the sibling OP-15 row and was not duplicated.
- Existing formulas in `Продажі!N267:O267` recalculated to 734.48 UAH and
  780.63 UAH. Gross profit is 265.52 UAH; net profit is 212.62 UAH.
- Cell formats, existing formulas, validations, and row layout remained
  intact in the bounded API read-back.

## Integrity scope

The CRM integrity check is not required for this correction: it did not alter
`Товари`, `РРЦ`, `Розхідники`, or `Майстер_Товарів`, and it did not add/remove
any row or overwrite a formula column in those tabs.

## Narrow rollback

If the correction must be reversed, restore `Списання!K178:L183` to its
previous values, then restore `Продажі!L267:M267` to 0.00 / 2.09 and
`Продажі!AD267:AF267` to the recorded fallback audit. Do not delete the
write-off rows: their formulas and stock impact pre-date this correction.
