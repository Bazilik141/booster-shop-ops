# CRM-005 — first live integrity baseline

> **Disposable — owner instruction 2026-08-09.** This file exists only to hold the pre-repair
> baseline while `CRM-006` runs. Once the repair is finished and a clean post-repair run is recorded
> in the `CRM-006` diagnostic, delete this file from the repository. It carries no long-term value
> and is not referenced by any rule.

Date: 2026-08-09 · Deployed: main-CRM Apps Script **V97**, published 14:44 Kyiv
Run: 14:45 Kyiv · `elapsed_ms` **5750** · Author: Claude (chat)

**Provenance:** transcribed from the owner's dashboard screenshots, not from a captured API payload.
Treat row lists as legible-but-unverified until the raw JSON is saved. The counts and codes below are
what the tile displayed.

## Result

`clean: false` · **150 problems total** — 26 returned, 124 suppressed by the per-code cap of 10.

| Code | Sheet | Rows shown | Returned | Suppressed |
|---|---|---|---|---|
| `formula_column_literal` | `Товари` — Коротка назва | 38-39, 49-67, 71-76 | 1 | — |
| `formula_column_literal` | `Товари` — Поточна ціна продажу | 38-39 | 1 | — |
| `formula_column_literal` | `Розхідники` — Надійшло через витрати | 7-15, 17 | 1 | — |
| `formula_column_literal` | `Розхідники` — Їде через витрати | 6, 8, 10-15, 17 | 1 | — |
| `formula_column_literal` | `Розхідники` — Використано в продажах | 10-11, 13-15, 17-23 | 1 | — |
| `price_without_sku` | `РРЦ` | 3-69, 71-75 | 1 | — |
| `master_row_inactive` | `Майстер_Товарів` | 2-11 | 10 | 62 |
| `active_sku_without_rrp` | `Товари` | 3-12 | 10 | 62 |

Coverage: `rrp_mismatch_3dp` — compared `0`, skipped for missing SKU-keyed CRM RRP `1`.

## 150 is not 150 independent defects

> ⚠️ **Corrected 2026-08-09 by `diagnostics/CRM-006_bounded-live-diagnosis_report_20260809.md`.**
> The cascade reading below is right; the named cause is **wrong**. The spill blocker is
> **`РРЦ!A76:D76`** — four literals duplicating `Товари!A76/B76/D76/G76` — and the `#REF!` errors on
> `A3:D3` say so themselves ("the array result would overwrite data in row 76"). Rows `71:75` have
> **blank** `A:D` *because* the spill is blocked; they are a symptom, not the cause, and clearing
> them would have changed nothing.
>
> This wrong mechanism originated in the CRM-005 report and was repeated by me here and in the
> CRM-006 task body. Anyone reading the paragraph below should take the *shape* of the argument and
> ignore the row numbers.

`crmIntegrityText_` deliberately maps spreadsheet error values to the empty string:

```js
/^#(?:REF|N\/A|VALUE|NAME|DIV\/0|NUM|ERROR)!?$/i.test(text) ? '' : text
```

So while the spill is broken, **every** `РРЦ` SKU cell reads as blank to the check. Two consequences
follow mechanically from that single root cause:

1. `price_without_sku` on `РРЦ` rows `3-69` — those rows have prices and no readable SKU because the
   spill that should supply the SKU is in `#REF!`. Rows `71-75` are the original manual defect.
2. `active_sku_without_rrp` for ~72 SKUs — no SKU-keyed CRM RRP exists **anywhere** while the spill
   is broken, so every active product reports it.

Expected effect of repairing rows 71–75: the spill restores, and the great majority of both codes
should disappear in a single move. That is the test of this reading — run the check again immediately
after the repair and compare.

## What is genuinely unexplained

- **`master_row_inactive` for ~72 SKUs.** This is *not* downstream of the `РРЦ` spill — it compares
  `Товари.Активний товар` against `Майстер_Товарів.Активний`. Either it is the real
  three-catalogue trap at scale (rows present, `Активний` blank — the most-forgotten step named in
  the CRM-005 handoff), or `Майстер_Товарів.Активний` is itself formula-driven and currently broken
  or emitting a value the check does not recognise as true (`так` / `true` / `yes` / `1`).
  **Undetermined from here.** One bounded live read of that column decides it.
- **`formula_column_literal` in `Товари` and `Розхідники`.** Plausibly the real "a value was pasted
  over a formula" failure, which is exactly what the check exists to catch. But those specific rows
  may also be legitimately manual by design. Needs the same bounded look before anyone touches them.

## Do not repair yet — and never in bulk

The single highest risk right now is someone deciding that 150 rows need 150 edits. Mass-editing
these columns is precisely how a formula column becomes a literal, which would manufacture the very
defect the check reports.

Repair sequence, once the root causes are established:

1. Fix `РРЦ` rows 71–75 only. Re-run the check. Record the before/after pair.
2. Re-read the remaining problem list — it should be a small fraction of 150.
3. Diagnose `master_row_inactive` and `formula_column_literal` on the reduced list.
4. Each repair is its own bounded change under `OPS-CRMINTEGRITY`: check before, check after, new
   problem codes treated as defects of that change.

## Performance — N7 now answered

`elapsed_ms` = **5750**. The click-to-run decision was the right one: auto-running on load would have
added ~5.8 s to every Огляд open. Batching the `Майстер_Товарів` seed reads into one range read
remains the obvious optimisation if the check is later wanted more often. Not urgent.

## Follow-ups

- Owner: save the raw `integrity_check` JSON so this baseline rests on a payload rather than a
  screenshot transcription.
- Owner: fresh `Code.gs` export from V97 so the repository mirror and `crm/apps-script/SOURCE_STATE.md`
  can be refreshed.
- New task needed: bounded diagnosis of `master_row_inactive` and `formula_column_literal`, and the
  staged `РРЦ` repair. Not part of CRM-005, which was scoped to build the check and explicitly
  forbids repairing rows 71–75 inside it.
