# CRM-016: stock and queue formula repair

Date: 2026-09-27

## Scope and authority

The owner requested a controlled repair of the old `Склад` and
`Черга_Складу` formulas, with checks before and after. No product, sale,
purchase, or 3D-P ledger cells were changed.

## Before

- Owner-run CRM `integrity_check`: `ok=true`, `clean=true`, zero problems;
  five checked tables, 67 3D RRP comparisons, 6 skipped missing CRM RRP,
  elapsed 11,665 ms.
- A fresh bounded read matched all 93 expected SKU/row/formula/value tuples
  from `CRM-016_formula_repair_preflight_20260926.json`. The other 97 stock
  formulas were already canonical. All 190 CRM and automation SKU keys and
  inbound counts matched. The queue spill still used `E+G`.
- All 93 stock formula changes had a calculated value delta of zero.

## Applied

- `Склад!H`: 93 formula cells were normalized to include net internal stock
  migration and the preorder reserve. One Sheets batchUpdate succeeded.
- `Черга_Складу!H30`: the spill formula now uses CRM inbound `Q` with current
  available `E`, avoiding a second subtraction of preorder reserves. One
  Sheets batchUpdate succeeded.

## After readback

- All 93 SKU keys, formula texts, and effective numeric stock values match
  expectations; the stock numeric values did not change.
- All 190 queue rows match `E + Source_CRM_Stock!Q`; formula `H30` matches the
  requested expression. Four rows changed, exactly as predicted:

| SKU | Before | After |
|---|---:|---:|
| `OP-JP-OP07-BST` | 0 | 1 |
| `OP-JP-EB03-BST` | -3 | 3 |
| `OP-JP-OP17-BST` | 0 | 7 |
| `OP-JP-OP13-BST` | 33 | 34 |

The required owner-run **post-change** dashboard `integrity_check` returned
`ok=true`, `clean=true`, zero problems, the same five checked tables, 67 3D RRP
comparisons and 6 missing-CRM-RRP skips (68,167 ms). No new problem code was
reported. The targeted formula/value readback remains the direct check of the
stock and queue calculations; the integrity action checks other CRM tables.

## Rollback

`CRM-016_formula_repair_preflight_20260926.json` contains the exact previous
formula and value for each of the 93 stock cells and the old `H30` formula.
Rollback must first re-read the current SKU/formula in each cell and stop on
drift. Restoring the old queue formula would reintroduce the known double
reserve calculation, so rollback is reserved for a demonstrated regression.

## Remaining verification

- Refresh dashboard stock and Alerts after normal source cache expiry; confirm
  the four queue projections and absence of a false EB-03 negative-stock alert.
