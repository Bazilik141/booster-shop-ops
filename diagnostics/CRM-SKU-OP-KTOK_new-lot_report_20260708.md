# Codex Report — CRM-SKU-OP-KTOK: new One Piece JP lot

Date: 2026-07-08

## Scope
Added 2 new One Piece JP SKUs for the Rakuma / ZenMarket lot `yskh288` and recorded the purchase as 24 packs.

## Files / ranges touched
```
Google Sheet: Booster Shop CRM — облік товарів
Товари!A65:O66
РРЦ!E65:G66
Закупки!A94:T94
Налаштування!AD37
```

No local code files, server files, Apps Script source, OpenCart files, database, orders, sales, or writeoffs were changed.

## Added SKUs
- `OP-JP-KTOK-BBX` — One Piece — Kessen no Toki — JP — Booster Box
- `OP-JP-KTOK-BST` — One Piece — Kessen no Toki — JP — Booster

Set label used in CRM: `Kessen no Toki`; Japanese title preserved in notes/full names as `決戦の刻`.

## RRP
- `OP-JP-KTOK-BBX`: 6900 грн
- `OP-JP-KTOK-BST`: 280 грн

## Purchase
Added `Закупки!94`:

- Lot ID: `LOT-0099`
- Order: `yskh288`
- SKU: `OP-JP-KTOK-BST`
- Quantity: `24`
- Lot cost: `1899 грн`
- JP commission: `142,86 грн` (`¥500`)
- Status: `Замовлено`
- Source URL: `https://zenmarket.jp/ua/rakumaproduct.aspx?itemCode=7f917b01773793e46826688f5814fb8d`

## Verification
Read-back after write confirmed:

- `Товари!A65:O66` contains both new SKUs and sale prices pulled from `РРЦ`.
- `РРЦ!A65:H66` shows 6900 грн / 280 грн with date `2026-07-08`.
- `Закупки!A94:T94` shows total lot cost `2041,86 грн`, PRRO cost `85,08 грн/pack`, management cost `90,18 грн/pack`.
- `Налаштування!AD37` contains `Kessen no Toki`.
- `SKU_вибір` sees `OP-JP-KTOK-BST`.
- `Склад` sees the SKU, but remaining stock is `0` while purchase status is `Замовлено`.

## Rollback
Manual rollback if needed:

1. Clear `Товари!A65:O66`.
2. Clear `РРЦ!E65:G66`.
3. Clear `Закупки!A94:T94` user-entered values only, preserving row formulas.
4. Clear `Налаштування!AD37` if the set is not needed.

## Side effects / risks
Low risk. Only bounded CRM spreadsheet cells were edited. The purchase is not counted into stock until the lot status is advanced from `Замовлено` according to the existing CRM logic.
