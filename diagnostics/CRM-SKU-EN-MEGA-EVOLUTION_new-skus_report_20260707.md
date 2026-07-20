# Codex Report — CRM-SKU-EN-MEGA-EVOLUTION: add English Mega Evolution SKUs

Date: 2026-07-07

## Scope
Added 4 English Pokémon SKUs to Booster Shop CRM and set their RRP values:

- `PKM-EN-PORD-BBN` — Pokémon Perfect Order EN Booster Bundle — 2000 грн
- `PKM-EN-PORD-BST` — Pokémon Perfect Order EN Booster — 400 грн
- `PKM-EN-CHRS-BBN` — Pokémon Chaos Rising EN Booster Bundle — 2200 грн
- `PKM-EN-CHRS-BST` — Pokémon Chaos Rising EN Booster — 440 грн

Also added `Booster Bundle` to the format dictionary and added `Perfect Order` / `Chaos Rising` to the set dictionary.

## Files / ranges touched
```
Google Sheet: Booster Shop CRM — облік товарів
Товари!A61:O64
РРЦ!E61:G64
Налаштування!J12
Налаштування!AD35:AD36
Товари!G3:G220 data validation -> Налаштування!J4:J12
```

No local code files or server files were changed.

## Dry-run / pre-check result
Checked existing CRM rows before write:

- first free product block: `Товари!A61:O64`
- `EN` language already existed in settings
- `Booster Bundle` was missing from format dictionary
- `Perfect Order` and `Chaos Rising` were missing from set dictionary
- no existing `PKM-EN-PORD-*` or `PKM-EN-CHRS-*` SKU rows found

## Syntax / script check
Not applicable — direct Google Sheets CRM update, no PHP/script patch created.

## Idempotency
Not a rerunnable patch. Repeating the same manual update would create duplicates unless SKU existence is checked first.

## Rollback
Manual rollback if needed:

1. Clear `Товари!A61:O64`.
2. Clear `РРЦ!E61:G64`.
3. Clear `Налаштування!J12`, `Налаштування!AD35:AD36` if these dictionary values are not needed.
4. Restore `Товари!G3:G220` validation range to `Налаштування!J4:J11` if `Booster Bundle` should be removed.

## Verification
Read-back after write confirmed:

- `Товари!A61:O64` contains all 4 new SKUs.
- `Товари!J61:J64` shows prices: 2000, 400, 2200, 440 грн.
- `РРЦ!A61:H64` shows the same SKUs and RRP values with date `2026-07-07`.
- `Налаштування!J12` contains `Booster Bundle`.
- `Налаштування!AD35:AD36` contains `Perfect Order`, `Chaos Rising`.
- `SKU_вибір` auto-populated the new SKUs.
- Format dropdown validation now points to `Налаштування!J4:J12`.

## Post-update QA checklist
- [x] Product rows created.
- [x] RRP rows populated.
- [x] Current sale price is pulled into `Товари`.
- [x] SKU picker sees the new SKUs.
- [x] New format is included in dropdown source.

## Side effects / risks
Low risk. Only bounded CRM spreadsheet ranges were changed. No orders, stock movements, Apps Script source, OpenCart files, database, or server files were touched.
