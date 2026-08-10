# Codex Report — CRM-008: OP-16 starter decks and `yskh293` purchase

Date: 2026-08-10

## Status

**WP1 and WP2 are complete and final-integrity-verified in the live main CRM.**
No Apps Script source, Notion property/status, or `ROADMAP_FLOW` entry was
changed.

## Scope and rollback

The owner-supplied recovery copy `10 серпня, 15:01 До 008` remains the
full-workbook rollback point for CRM-008.

The live scope was limited to:

- the `Starter Deck` settings and five starter-deck catalogue/RRP rows;
- the corresponding five `Майстер_Товарів` automation rows;
- six `Закупки` manual-input records for lot `yskh293`.

No manual value was written into a formula field. `Розхідники`, `Склад`,
`Продажі`, and the Apps Script project were not edited.

## Fresh preflight

- Main CRM: `Booster Shop CRM — облік товарів`
  (`1PvlSlg3UoPw8Fbj98lHL-VGLB0HP8hgKUxsXPW1GkRg`), Kyiv timezone.
- `Курси!B5` was `3.5000` for JPY.
- Before WP2, `yskh293` was absent from `Закупки`; the first empty purchase
  row was 126 and the highest existing `LOT-` sequence was 130.
- `Закупки!126:131` already held its normal lookup and cost formulas. Its
  product and status validations respectively pointed to `Товари` and
  `Налаштування!Z4:Z12`, including strict value `Замовлено`.

## WP1 — settings, catalogue, RRP, and automation master

- Added `Starter Deck` / `STD` at `Налаштування!J15:K15`.
- Added `ST-32`…`ST-36` / `ST32`…`ST36` at
  `Налаштування!AD40:AE44`.
- Extended only the existing product dropdown validations:
  `Товари!F3:F201` now reads `Налаштування!AD4:AD44`; `Товари!G3:G201`
  now reads `Налаштування!J4:J15`.
- `Товари!77:81` contains exactly one active `Starter Deck` row for each:
  `OP-JP-ST32-STD`, `OP-JP-ST33-STD`, `OP-JP-ST34-STD`,
  `OP-JP-ST35-STD`, and `OP-JP-ST36-STD`.
- Only manual fields `A`, `C:G`, `K:L`, and `N` were populated. The normal
  formulas in `B77:B81` and `J77:J81` remained formulas.
- Each matching `РРЦ!77:81` row has RRP 700.00, date 2026-08-10, and an
  initial-RRP note. The formula-derived SKU columns were not edited.
- `Майстер_Товарів!75:79` contains exactly one active row per SKU: format
  `Starter Deck`, CRM price 700, active `Так`.

## WP2 — purchase records for `yskh293`

An atomic, bounded direct spreadsheet write created these six records in
`Закупки!126:131`. The current V99 source rules were used only to calculate
the next sequence and JPY allocation; the Apps Script source itself was not
changed.

| Row | Lot ID | SKU | Goods cost, UAH | Japan fee, UAH |
| --- | --- | --- | ---: | ---: |
| 126 | `LOT-0131` | `OP-JP-OP16-BBX` | 3,000.00 | 38.10 |
| 127 | `LOT-0132` | `OP-JP-ST32-STD` | 251.40 | 38.10 |
| 128 | `LOT-0133` | `OP-JP-ST33-STD` | 251.40 | 38.09 |
| 129 | `LOT-0134` | `OP-JP-ST35-STD` | 251.40 | 38.10 |
| 130 | `LOT-0135` | `OP-JP-ST36-STD` | 251.40 | 38.10 |
| 131 | `LOT-0136` | `OP-JP-ST34-STD` | 251.40 | 38.09 |

Read-back returned exactly these six rows for `yskh293`, with goods total
4,257.00 UAH and Japan-fee total 228.58 UAH. The 0.01 UAH difference from
the nominal 228.57 is the current V99 three-line round-to-2-decimals behavior:
each of the two three-line entries allocates 38.10 / 38.10 / 38.09 UAH.

Every row has status `Замовлено`, no Ukraine delivery cost, no delivery date,
the supplied ZenMarket URL, and the specified allocation note. Lookup formulas
in `F:G` returned the correct starter-deck names/formats; cost formulas in
`L:P` remained formulas and calculated 3,038.10 UAH for the box and
289.50/289.49 UAH for the starter decks.

## Stock read-back and acceptance caveat

For each of the five new SKUs, `Склад!77:81` shows one purchased unit in
`Закуплено всього` and zero in the actual-stock column. This confirms the
purchase records are linked.

`Очікується / Японія` and `Очікується після резерву` remain zero. The current
existing stock formula counts `В дорозі`, `На складі в Японії`, and `Виграно`,
but does not count required status `Замовлено`. No formula was changed and no
manual stock adjustment was made, because changing either would exceed CRM-008
scope. Therefore the handoff phrase that stock should reflect `Замовлено` is
not satisfiable under the current formula semantics; this is an acceptance
caveat, not a failed purchase write.

## CRM integrity evidence

### Before — V99, owner run at 2026-08-10 19:59 Kyiv

`formula_column_literal` only:

- `Товари → Поточна ціна продажу`: rows `38-39`;
- `Розхідники → Надійшло через витрати`: rows `7-15, 17`;
- `Розхідники → Їде через витрати`: rows `6, 8, 10-15, 17`;
- `Розхідники → Використано в продажах`: rows `10-11, 13-15, 17-23`.

Coverage: `rrp_mismatch_3dp.compared = 1`, skipped = 0, deferred = null.
`clean = false`; elapsed 6362 ms.

### After WP1 — owner-provided dashboard run

The same four `formula_column_literal` findings, sheets, and row ranges were
returned. No new problem code or row range appeared. Coverage remained
`compared = 1`, skipped = 0, deferred = null; `clean = false`; elapsed 6666 ms.

### After WP2 — owner dashboard run

The final owner run returned exactly the same four
`formula_column_literal` findings, sheets, and row ranges as the V99 baseline
and the post-WP1 run. No new problem code or row range appeared.

Coverage remained `rrp_mismatch_3dp.compared = 1`, skipped = 0, deferred =
null. `clean = false`; elapsed 7739 ms.

**Result:** CRM-008 introduced no integrity defect. All remaining findings
belong to the pre-existing CRM-006 formula-literal backlog.

## Bounded manual rollback, if required

Do not use this while the task is progressing. The recovery copy above is the
primary rollback. If a narrow reverse is approved, clear only the written
manual fields in `Закупки!126:131` (`A:B`, `E`, `H:J`, `Q:T`) rather than
deleting formula-bearing rows; then reverse the WP1 manual/RRP/settings cells
and validation endpoints. Re-run the integrity check after any rollback.
