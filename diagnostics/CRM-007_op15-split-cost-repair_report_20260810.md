# CRM-007 — OP-15 split-cost repair (WP1)

Date: 2026-08-10
Executor: Codex
Status: WP1 data repair and live verification complete; no Notion or dashboard status was changed.

## Scope and authority

The owner explicitly authorized WP1 in the active task. This report covers only:

- `Закупки!I58:K58` for `LOT-0063`;
- `Закупки!H123:K123` for `LOT-0119`;
- appended audit text in `Закупки!R58` and `Закупки!R123`;
- clearing `Продажі!AD265` so the normal FIFO recalculation will not be skipped.

No Apps Script source, Notion property, dashboard source, formula cell, or other CRM row was edited.

## Rollback artefact

Owner-provided rollback copy: `До фіксу 007`, shown in the owner screenshot dated 2026-08-10. The screenshot does not show a clock time; that is an evidence limitation, not an inferred timestamp.

## Integrity check — before (verbatim)

```json
{
  "ok": true,
  "action": "integrity_check",
  "checked": [
    "Товари",
    "РРЦ",
    "Розхідники",
    "Майстер_Товарів"
  ],
  "problems": [
    {
      "sheet": "Товари",
      "rows": "38-39, 49-67, 71-76",
      "code": "formula_column_literal",
      "detail": "Коротка назва contains a literal where a formula is required."
    },
    {
      "sheet": "Товари",
      "rows": "38-39",
      "code": "formula_column_literal",
      "detail": "Поточна ціна продажу contains a literal where a formula is required."
    },
    {
      "sheet": "Розхідники",
      "rows": "7-15, 17",
      "code": "formula_column_literal",
      "detail": "Надійшло через витрати contains a literal where a formula is required."
    },
    {
      "sheet": "Розхідники",
      "rows": "6, 8, 10-15, 17",
      "code": "formula_column_literal",
      "detail": "Їде через витрати contains a literal where a formula is required."
    },
    {
      "sheet": "Розхідники",
      "rows": "10-11, 13-15, 17-23",
      "code": "formula_column_literal",
      "detail": "Використано в продажах contains a literal where a formula is required."
    }
  ],
  "coverage": {
    "rrp_mismatch_3dp": {
      "compared": 1,
      "skipped_missing_crm_rrp": 0,
      "deferred": null
    }
  },
  "clean": false,
  "elapsed_ms": 5851
}
```

The five findings are the documented pre-existing `formula_column_literal` baseline. This check does not cover `Закупки`, `Продажі`, or `Склад`.

## Recorded before-values and rollback data

| Cell(s) | Before live value |
| --- | --- |
| `Закупки!H58:K58` | `1`, `6225.00`, `142.86`, `913.80` |
| `Закупки!L58:P58` | formula results `7281.66`, `7281.66`, `436.90`, `7718.56`, `7718.56` |
| `Закупки!R58` | `JP-комісія: ¥500; доставка Україна ¥3988 розподілена за вартістю лоту.` |
| `Закупки!H123:K123` | `20`, `5187.50`, `119.05`, `761.50` |
| `Закупки!L123:P123` | formula results `6068.05`, `303.40`, `364.08`, `6432.13`, `321.61` |
| `Закупки!R123` | `Роздербан 1 боксу LOT-0063: 20 паків переведено у OP-JP-OP15-BST.` |
| `Продажі!L265:M265` | `7281.66`, `7719.73` |
| `Продажі!AD265:AF265` | `FIFO + авторозхідники`; `before=0; LOT-0063: 1 x 7281.66/7718.56; auto consumables: Стікер лого+QR=1.17`; `10.08.2026` |

Both `Закупки!L:P` ranges were read as formulas before the write. No formula conversion was required.

## Applied live batch

One coherent Google Sheets batch applied these exact mutations:

| Range | Change |
| --- | --- |
| `Закупки!I58:K58` | `3112.50`, `71.43`, `456.90` |
| `Закупки!H123:K123` | `24`, `3112.50`, `71.43`, `456.90` |
| `Закупки!R58` | Existing text retained; appended the dated CRM-007 audit note with `; ` separation. |
| `Закупки!R123` | Existing text retained; appended the dated CRM-007 audit note with `; ` separation. |
| `Продажі!AD265` | Cleared only, as required to permit the normal FIFO calculation path. |

## Live read-back after batch

| Lot | H | I | J | K | L | M | N | O | P |
| --- | ---:| ---:| ---:| ---:| ---:| ---:| ---:| ---:| ---:|
| `LOT-0063` / row 58 | 1 | 3112.50 | 71.43 | 456.90 | 3640.83 | 3640.83 | 218.45 | 3859.28 | 3859.28 |
| `LOT-0119` / row 123 | 24 | 3112.50 | 71.43 | 456.90 | 3640.83 | 151.70 | 218.45 | 3859.28 | 160.80 |

`L:P` remain formulas on both rows. Conservation holds at the displayed precision:

- `L58 + L123 = 7281.66`;
- `O58 + O123 = 7718.56`.

`Продажі!AD265` is blank. `L265:M265` still display the old cost until the supported order-update path runs; no cost was typed into the sale row.

The `Склад` quantities already reflect the repaired input (`OP-JP-OP15-BST`: purchased 48, sold 4, written off 16, balance 28). Its cost columns still show the old values until the owner runs `updateSkuCurrentCost_` through the spreadsheet UI. `OP-JP-OP15-BBX` likewise still shows old historical unit cost values while its balance remains 0.

## Completed acceptance gates

1. The owner performed a note-only update of `OC-FOP-0314` through the CRM dashboard route, invoking FIFO recalculation.
2. The owner ran `updateSkuCurrentCostMenu()` from the bound Apps Script editor. Its execution log recorded `updateSkuCurrentCost_: updated 32 SKUs` before the expected editor-only `SpreadsheetApp.getUi()` exception. The update runs before the UI alert call, so the post-write exception does not undo the recalculation.
3. Corrected sale and warehouse values were read back from the live workbook.
4. The raw post-write `integrity_check` is recorded below. It has no problem code absent from the before JSON.

WP2 (`ACC-003 → ACC-009`) was not started and requires separate owner authorization.

## Sale recalculation read-back

The owner performed the requested note-only update of `OC-FOP-0314` through the existing CRM dashboard route. The dashboard screenshot shows the resulting management cost as approximately `3860` UAH for the one box.

Independent live CRM read-back confirms the exact persisted values:

| Cell(s) | Live result |
| --- | --- |
| `Продажі!L265` | `3640.83` |
| `Продажі!M265` | `3860.45` |
| `Продажі!AD265` | `FIFO + авторозхідники` |
| `Продажі!AE265` | `before=0; LOT-0063: 1 x 3640.83/3859.28; auto consumables: Стікер лого+QR=1.17` |
| `Продажі!AF265` | refreshed on `10.08.2026` |

This meets the sale-specific acceptance criteria. The cost was produced by the FIFO path; it was not typed into `Продажі`.

## Integrity check — after (verbatim)

```json
{
  "ok": true,
  "action": "integrity_check",
  "checked": [
    "Товари",
    "РРЦ",
    "Розхідники",
    "Майстер_Товарів"
  ],
  "problems": [
    {
      "sheet": "Товари",
      "rows": "38-39, 49-67, 71-76",
      "code": "formula_column_literal",
      "detail": "Коротка назва contains a literal where a formula is required."
    },
    {
      "sheet": "Товари",
      "rows": "38-39",
      "code": "formula_column_literal",
      "detail": "Поточна ціна продажу contains a literal where a formula is required."
    },
    {
      "sheet": "Розхідники",
      "rows": "7-15, 17",
      "code": "formula_column_literal",
      "detail": "Надійшло через витрати contains a literal where a formula is required."
    },
    {
      "sheet": "Розхідники",
      "rows": "6, 8, 10-15, 17",
      "code": "formula_column_literal",
      "detail": "Їде через витрати contains a literal where a formula is required."
    },
    {
      "sheet": "Розхідники",
      "rows": "10-11, 13-15, 17-23",
      "code": "formula_column_literal",
      "detail": "Використано в продажах contains a literal where a formula is required."
    }
  ],
  "coverage": {
    "rrp_mismatch_3dp": {
      "compared": 1,
      "skipped_missing_crm_rrp": 0,
      "deferred": null
    }
  },
  "clean": false,
  "elapsed_ms": 6259
}
```

The post-check is identical to the documented before baseline except for elapsed time. Therefore CRM-007 introduced no new integrity problem code.

## Warehouse refresh read-back

The live `Booster CRM` menu indeed lacks an entry for `updateSkuCurrentCostMenu()`. The owner therefore ran the existing wrapper from the bound Apps Script editor. The wrapper first updated 32 SKUs, then attempted to show a spreadsheet-only alert and raised `Cannot call SpreadsheetApp.getUi() from this context`. This is a post-update UI error, not a failed refresh.

| SKU | Live result after refresh |
| --- | --- |
| `OP-JP-OP15-BST` | purchased 48, sold 4, written off 16, balance 28; average `141.77` PRRO / `150.28` management; potential profit `1392.16` UAH (positive) |
| `OP-JP-OP15-BBX` | purchased 1, sold 1, written off 0, balance 0 |

`OP-JP-OP15-BBX` retains the old displayed unit cost (`7281.66` / `7718.56`) despite a zero balance. It has no remaining-stock valuation or effect on the corrected FIFO sale; the stated acceptance criterion for this SKU is its `1 / 1 / 0` quantity state, which passes. No direct worksheet value was used to overwrite `Склад`.

No Code.gs edit was made in CRM-007. WP2 remains separate and requires a new owner authorization.
