# Codex Report — CRM-SKU-EN-MEGA-EVOLUTION: RRP correction and bundle category note

Date: 2026-07-07

## Scope
Adjusted RRP for two English Pokémon booster pack SKUs and added site-category notes for bundle SKUs.

## Files / ranges touched
```
Google Sheet: Booster Shop CRM — облік товарів
РРЦ!E62:G62
РРЦ!E64:G64
Товари!N61:N64
```

No local code files, server files, orders, stock movements, Apps Script source, database, or OpenCart files were changed.

## Change details
- `PKM-EN-PORD-BST`: RRP changed from 400 грн to 340 грн.
- `PKM-EN-CHRS-BST`: RRP changed from 440 грн to 380 грн.
- `PKM-EN-PORD-BBN`: product note now says to add the bundle on site to categories `Бустер бокси` and `Набори`.
- `PKM-EN-CHRS-BBN`: product note now says to add the bundle on site to categories `Бустер бокси` and `Набори`.
- Pack product notes were also updated so `Товари` does not keep stale old RRP text.

## Verification
Read-back after write confirmed:

- `РРЦ!A61:H64` shows pack RRP values 340 грн and 380 грн.
- `Товари!A61:O64` shows current sale prices pulled into `Товари!J62:J64` correctly.
- Bundle notes in `Товари!N61` and `Товари!N63` contain the category reminder.

## Rollback
Manual rollback if needed:

1. Set `РРЦ!E62` back to `400`.
2. Set `РРЦ!E64` back to `440`.
3. Restore previous notes in `Товари!N61:N64` from spreadsheet version history.

## Side effects / risks
Low risk. Only bounded CRM spreadsheet cells were edited.
