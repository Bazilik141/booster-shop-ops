# 3D-P-008 — Pre-write reconciliation diff

Date: 2026-08-01

## Gate

**No live values were written.** This report must be reviewed and explicitly
approved by the owner before any listed business-data change is sent through
`3dp_write`.

Sources compared read-only:

- local: `3d-print/3D-P_nomenclature-tracker_v6_20260731.xlsx`
- live: `https://docs.google.com/spreadsheets/d/1yp15H3YJGkqI4Rx89G4QZHkD9m67gnWh58TsTTi-jjo/edit`
- live metadata title: `3D-P_nomenclature-tracker_v6_20260731`

The local workbook's blue manual-input cells were enumerated with the bundled
spreadsheet runtime. Live values/formulas/font colors were read from bounded
Google Sheets ranges. Formula cells and the human-only Analytics research block
are excluded from reconciliation writes.

## Eligible blue-cell differences

### `Номенклатура!A3`

- live: `FIG-CHARM-001`
- local: `BR-CHARM-001`
- effect: renames the real SKU from the generic figurine prefix to the approved
  keychain prefix; downstream formula tabs follow column A.

### `Номенклатура!F3`

- live: `Перший реальний SKU — дані внесено 2026-07-31, очікує уточнень нижче`
- local: `Реальне фото партії підтверджено власником 31.07 (36 заготовок на друкованому столі). Фактично готового товару під продаж 0 шт: усі 36 заготовок замовлені одразу як викуп під Track 2 (маркетингова плюшка), фурнітура (ланцюжок/карабін) ще не приєднана. Track A (продаж на сайті) для цього SKU наразі без реального стоку — потребує рішення власника, чи буде окремий друк під продаж. Оновлено 31.07 (уточнення власника від Сергія): пластик — PLA (базовий, виробник/підтип не фіксовано, можливий перехід на PETG/TPU); собівартість матеріалу порахована зі слайсер-оцінки партії (1549,98 грн/кг, колонка J) — робоче значення, Сергій виправить за потреби.`
- effect: replaces the preliminary status with the later owner/Serhiy production
  and Track-2 clarification.

### `Номенклатура!H3`

- live: `уточнити у Сергія`
- local: `PLA`
- effect: records the material type confirmed in the later local workbook.

### `Номенклатура!J3`

- live: blank
- local: `1549.98`
- effect before amortization: calculated Serhiy cost changes from `3.00 грн` to
  approximately `6.80 грн` for the SKU.

### `Номенклатура!M3`

- live: `Джерело: скріншоти слайсера + повідомлення власника, 2026-07-31. Партія 36 шт на платформі: 3год43хв друку, філамент 27,79м/88,24г, слайсер-оцінка вартості 136,77 грн (≈3,80 грн/шт). Разовий друк 1 шт: 16 хв, слайсер-оцінка 6,08 грн/шт. Використано дані партії (36 шт) як реалістичний виробничий режим. УВАГА: слайсер-оцінка вартості могла включати не лише чистий пластик (можливо електрику/час) — перед внесенням у колонку J (Ціна матеріалу, грн/кг) уточнити у Сергія, чи це ціна пластику окремо. Тип пластику (PLA/ABS/PETG) також не вказано — уточнити.`
- local: `Джерело: скріншоти слайсера + повідомлення власника, 2026-07-31. Партія 36 шт на платформі: 3год43хв друку, філамент 27,79м/88,24г, слайсер-оцінка вартості 136,77 грн (≈3,80 грн/шт). Разовий друк 1 шт: 16 хв, слайсер-оцінка 6,08 грн/шт. Використано дані партії (36 шт) як реалістичний виробничий режим. УВАГА: слайсер-оцінка вартості могла включати не лише чистий пластик (можливо електрику/час) — перед внесенням у колонку J (Ціна матеріалу, грн/кг) уточнити у Сергія, чи це ціна пластику окремо. Тип пластику (PLA/ABS/PETG) також не вказано — уточнити. | 31.07 оновлення: SKU перейменовано з FIG-CHARM-001 на BR-CHARM-001 (це брелок, не окрема фігурка). Реальне фото партії є (не референс/сток). Відкрите процесне питання від власника: як логувати Track-2 закупівлі, коли собівартість = сума Сергію + фурнітура (ланцюжок/карабін) — куди саме заносити (Маркетингові_плюшки vs окрема стаття витрат). Не вирішено, не заповнювати без рішення власника.`
- effect: preserves the original production evidence and adds the later rename,
  real-photo, and unresolved Track-2 accounting notes.

## Formula consequence (not a direct write)

`Номенклатура!K3` is a formula and is never reconciled through `3dp_write`.
With local `J3=1549.98`, `I3=2.45`, and `N3=3`, the pre-amortization result is
approximately `6.80 грн`; the live result is currently `3.00 грн` because J3 is
blank. After schema setup, K also includes `O3*G3`; O3 starts blank.

## Non-blue drift — informational only

These local/live differences exist but are not blue manual-input cells in the
local workbook, so they are **not approved reconciliation candidates** under the
handoff's rule:

| Cell | Live | Local |
|---|---|---|
| `Номенклатура!A5` | `(призначити SKU)` | `ACC-3D-001` |
| `Номенклатура!M5` | original reference note | original note plus draft-SKU convention note |
| `Номенклатура!A6` | `(призначити SKU)` | `ACC-3D-002` |
| `Номенклатура!M6` | original reference note | original note plus draft-SKU convention note |
| `Номенклатура!A7` | `(призначити SKU)` | `ACC-3D-003` |
| `Номенклатура!M7` | original reference note | original note plus draft-SKU convention note |

They require a separate explicit owner decision if they should also move to the
live Sheet.

## Approval choices

The owner can approve:

1. all five eligible blue-cell differences (`A3,F3,H3,J3,M3`);
2. a named subset;
3. none, with a correction to the intended source of truth.

No API call should apply these values until the deployed API passes the three
negative write tests and the owner gives that explicit approval.
