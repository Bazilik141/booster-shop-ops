# Codex Report — CRM-SKU-OP16: correct OP-16 code for yskh288

Date: 2026-07-08

## Scope
Corrected the One Piece JP `yskh288` lot SKUs from temporary `KTOK` naming to the official OP code.

## Evidence
Official One Piece Card Game products/boosters list shows:

- `ブースターパック 決戦の刻〖OP-16〗`
- release date `2026.05.30`
- MSRP `220円(税込)`

Source: `https://www.onepiece-cardgame.com/products/?page=1&subcategory=boosters`

## Files / ranges touched
```
Google Sheet: Booster Shop CRM — облік товарів
Товари!A65:O66
РРЦ!E65:G66
Закупки!E94:R94
Налаштування!AD37
```

No code files, server files, Apps Script source, OpenCart files, database, orders, sales, or writeoffs were changed.

## Correction
Before:

- `OP-JP-KTOK-BBX`
- `OP-JP-KTOK-BST`
- set: `Kessen no Toki`

After:

- `OP-JP-OP16-BBX`
- `OP-JP-OP16-BST`
- set: `OP-16`

Japanese set name `決戦の刻` and romanized `Kessen no Toki` are preserved in full names and notes.

## Verification
Read-back after write confirmed:

- `Товари!A65:O66` shows `OP-JP-OP16-BBX` / `OP-JP-OP16-BST`.
- `РРЦ!A65:H66` shows RRP unchanged: 6900 грн / 280 грн.
- `Закупки!A94:T94` shows `LOT-0099` on `OP-JP-OP16-BST`; total/cost unchanged.
- `Налаштування!AD37` shows `OP-16`.
- `SKU_вибір` sees `OP-JP-OP16-BST`.
- `Склад` sees `OP-JP-OP16-BST`; stock remains `0` while purchase status is `Замовлено`.

## Rollback
Manual rollback if needed:

1. Restore `Товари!A65:F66` and notes to previous `KTOK` values.
2. Restore `Закупки!E94` to `OP-JP-KTOK-BST`.
3. Restore `Налаштування!AD37` to `Kessen no Toki`.

## Side effects / risks
Low risk. Only bounded CRM spreadsheet cells were edited. Cost values were not changed.
