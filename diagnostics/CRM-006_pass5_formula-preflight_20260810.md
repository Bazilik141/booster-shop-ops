# Codex Report — CRM-006 pass 5 formula repair

Date: 2026-08-10

## Authority and recovery point

The owner authorized `CRM-006-5` and supplied the fresh recovery copy:
`Booster Shop CRM — облік товарів – 10 серпня, 21:41 (копія)`.

The owner-provided pre-change dashboard run reported only these formula-literal
problems, with `clean = false` and `rrp_mismatch_3dp.compared = 1`:

- `Товари → Поточна ціна продажу`: rows `38-39`;
- `Розхідники → Надійшло через витрати`: rows `7-15, 17`;
- `Розхідники → Їде через витрати`: rows `6, 8, 10-15, 17`;
- `Розхідники → Використано в продажах`: rows `10-11, 13-15, 17-23`.

## Applied live spreadsheet repair

All writes were made in one Google Sheets batch and read back immediately.

- `Товари!J38:J39`: restored the SKU-keyed `РРЦ` lookup formula. The column is
  J (`Поточна ціна продажу`), not K. Effective values are now 90.00 UAH for
  `YGO-JP-BDOM-BST` and 2,700.00 UAH for `YGO-JP-BDOM-BBX`, matching `РРЦ`.
- `Розхідники!F7:F15,F17`: restored the per-item `SUMIFS` formula for receipts
  with status `На складі`.
- `Розхідники!G6,G8,G10:G15,G17`: restored the per-item `SUMIFS` formula for
  receipts with status `Їде`.
- `Розхідники!H15`: restored the automatic `Наліпка Mystery Box` sales-audit
  formula. It now reports 8 existing uses instead of the stale literal 0.

The restored formulas also corrected dependent current values, including:

- `Стікер лого+QR`: 0 received and 300 in transit;
- `Блайнд-пакет для картки`: 121 received;
- `Брошки TCG енергії`: 0 received and 18 in transit;
- `Наліпка One Piece`: 0 received and 50 in transit.

## Manual-history integrity policy prepared locally

The remaining `Використано в продажах` literals are not broken formulas.
They are verified manual historical uses without matching sale/write-off
references. For example, `Брошки TCG енергії = 2` has no source record, and
`Фоторамка Pokémon = 1` is a documented blogger write-off.

To preserve those numbers, the local `crm/apps-script/Code.gs` V100 candidate
adds a narrow exception list only for these 11 names when checking
`Розхідники → Використано в продажах`:

`Аніме-брелок поліестер`, `Брошки TCG енергії`, `Фоторамка One Piece`,
`Фоторамка Pokémon`, `Наліпка One Piece`, `Нашивка`, `Фігурка краба`,
`Піни One Piece`, `Фігурка Pokémon`, `FUR-BR-COLOR-MIX`, and `FUR-BR-CARB`.

No broad column exception is used: a new or changed manual literal outside
this exact list still fails the integrity check. The local automated integrity
test passed, including all 11 approved values and one non-exempt negative case.

## Live deployment and final integrity evidence

The owner published the local candidate as CRM Web App **V101** at 22:06 Kyiv
on 2026-08-10. The owner then ran the live dashboard `integrity_check`:

```json
{
  "ok": true,
  "action": "integrity_check",
  "problems": [],
  "coverage": {
    "rrp_mismatch_3dp": {
      "compared": 1,
      "skipped_missing_crm_rrp": 0,
      "deferred": null
    }
  },
  "clean": true,
  "elapsed_ms": 5617
}
```

This is live evidence that every prior `formula_column_literal` finding was
either restored as a real formula or retained only under its narrow,
documented manual-history exception. No new integrity problem code appeared.

`node --check Code.gs` cannot load the `.gs` extension in this Node runtime;
the passing integrity test parses and executes the relevant source inside its
local Apps Script mock instead.
