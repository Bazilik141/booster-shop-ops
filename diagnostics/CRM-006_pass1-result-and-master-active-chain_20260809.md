# CRM-006 — pass 1 result, and the `Майстер_Товарів!Активний` chain

Date: 2026-08-09 · Author: Claude (chat) · Evidence: owner screenshots + source read of V97

## Pass 1: succeeded, gate passed

Named Sheets versions exist (`Резерв відкат` 13:45, `Фікс 76К2` 15:33), so the rollback point is real.

| | Before (14:45) | After pass 1 |
|---|---|---|
| Total problems | **150** | **78** |
| Returned / suppressed | 26 / 124 | 16 / 62 |
| `price_without_sku` | 1 entry covering `РРЦ` rows 3-69, 71-75 | **gone** |
| `active_sku_without_rrp` | 72 | **gone** |
| `formula_column_literal` | 5 | 5 (unchanged, expected) |
| `master_row_inactive` | 72 | 72 (unchanged, expected) |
| `rrp_mismatch_3dp` | — (compared 0) | **1 — `РРЦ` row 75, `ACC-3D-DITTO-410`: CRM 90 vs 3D-P 100** |

**The cascade reading is confirmed.** Clearing four cells removed 72 problems. The two codes that were
predicted to be downstream of the `#REF!` both vanished; the two predicted to be independent both
survived unchanged. That is the outcome that would have falsified the reading had it been wrong.

**The §5 gate passed.** `rrp_mismatch_3dp` fires for `ACC-3D-DITTO-410` at row 75 with exactly the
expected `90` vs `100`, which proves row 75's manual price is keyed to the right product.

Key alignment verified against the sheet, not assumed — each row's spilled SKU matches the SKU named
in its own note: row 71 `PKM-JP-MBX-XL`, row 75 `ACC-3D-DITTO-410`, row 76 `PKM-EN-PBLK-BLR-SLP`.
`E76` survived the clear (`550`), as required.

Pass 2 (`РРЦ!E75` `90` → `100`) is therefore unblocked.

## The dashboard symptom: one cell explains all three

Owner report: stock, SKU list and potential profit do not populate on the dashboard.

**Not caused by the repair.** Potential profit read `₴0` in the 11:49 screenshot, before anything was
touched. This is pre-existing.

**Root cause: `Майстер_Товарів!P2`** — the same wrong VLOOKUP index already found in
`diagnostics/CRM-006_bounded-live-diagnosis_report_20260809.md` (`VLOOKUP(...;13;FALSE)` while
`Активний товар` is column 12). Traced through V97 source:

`apiSkuList_` reads `Майстер_Товарів` from the automation workbook and hard-filters on that column:

```js
const active = String(apiObjVal_(row, ['Активний', 'Active']) || '').trim().toLowerCase();
if (['так', 'true', 'yes', '1'].indexOf(active) === -1) return;
```

Because `Активний` holds the `Посилання на товар` value (blank or a URL) rather than `так`, **every
one of the 72 SKUs is skipped**. Three consequences follow mechanically:

1. `sku_list` returns almost nothing → the SKU/Товари views are empty.
2. `apiStockAlerts_` applies the identical filter → stock views are empty.
3. `apiSummary_` builds its price map **from `apiSkuList_`**, not from the `РРЦ` sheet directly:

```js
const skuList = apiSkuList_({}, salesRows);
const rrcBySku = {};
(skuList.skus || []).forEach(function(item) { … if (sku && rrc > 0) rrcBySku[sku] = rrc; });
```

so `rrcBySku` is empty, every lot evaluates `rrc = 0`, and the profit guard never fires:

```js
warehouseCostTotal += lotCost;                    // no guard  → ₴84 077 displays
const rrc = apiNum_(rrcBySku[sku]);
if (rrc > 0) {                                    // never true → potential profit stays exactly 0
  warehouseProfitTotal += lotProfit;
  assetProfitTotal    += lotProfit;
}
```

**This is the decisive evidence:** warehouse cost and warehouse profit are produced by the *same loop
over the same lots*, and only the profit half sits behind `rrc > 0`. The dashboard showing a real
cost (`₴84 077`) beside a profit of exactly `₴0` is the precise signature of an empty `rrcBySku`.

### Consequence for the earlier open question

I previously asked: if `Майстер_Товарів.Активний` has been wrong for a long time, why has anything
downstream been usable? **Answer: it has not been.** The owner's report is that symptom surfacing.
The blast-radius concern is therefore inverted — the column is already broken, and correcting the
index restores function rather than changing working behaviour.

That does not remove the need for care. Fixing `P2` will make ~72 SKUs appear in lists that are
currently empty and will turn potential profit from `0` into a real figure. That is the intended
state, but it is a visible change and needs its own before/after pair.

### Note on fixing the RRP spill first

Repairing `РРЦ` was necessary but not sufficient, and the order was still right: potential profit
reaches the `РРЦ` values only through `apiSkuList_`. Had `P2` been fixed first, the SKU list would
have populated while every price was still `#REF!`, producing a different and more confusing wrong
answer.

## Pass 2 result — gate closed, 2026-08-09 17:50

Owner set `РРЦ!E75` to `100`. Codex verified the cells directly (`E75` = `100`, `F75` date and `G75`
note preserved, `H75` still a formula) in
`diagnostics/CRM-006_pass2-verification_gate_20260809.md`.

Post-edit `integrity_check`, transcribed from the owner's dashboard screenshot at 17:50
(`elapsed_ms` 7562):

| Code | After pass 1 | After pass 2 |
|---|---|---|
| **Total** | **78** | **77** ✓ as predicted |
| Returned / suppressed | 16 / 62 | 15 / 62 |
| `rrp_mismatch_3dp` | 1 | **gone** ✓ |
| `formula_column_literal` | 5 (rows 38-39, 49-67, 71-76; 38-39; 7-15,17; 6,8,10-15,17; 10-11,13-15,17-23) | 5, **identical row lists** ✓ |
| `master_row_inactive` | 72 | 72 ✓ |

Exactly one code changed and exactly one problem disappeared. No other count moved, so the price edit
had no side effects. **Pass 3 is unblocked.**

`elapsed_ms` rose 5750 → 7562, which is expected: the restored spill means `РРЦ` now returns ~70 keyed
rows instead of near-nothing, so the check has real data to read. Still comfortably inside a
click-to-run control.

## Pass 3 result — the chain is proven, 2026-08-09 18:20

Owner created the named version `CRM-006 PASS 3 — before P2 index fix — 2026-08-09`; Codex changed
only the index `13 → 12` in `Майстер_Товарів!P2`. Report:
`diagnostics/CRM-006_pass3_p2-index_report_20260809.md`.

Verified from the owner's dashboard at 18:20 — **no separate QA round was needed, the screenshot
already contains every requested value.**

| | Before all passes | After pass 2 | After pass 3 |
|---|---|---|---|
| **Total problems** | 150 | 77 | **5** |
| `master_row_inactive` | 72 | 72 | **0** |
| `formula_column_literal` | 5 | 5 | 5 (unchanged) |
| Потенційний прибуток складу | ₴0 · 0.0 % | ₴0 · 0.0 % | **₴25 889 · 22.7 %** |
| Потенційний прибуток активів | ₴0 · 0.0 % | ₴0 · 0.0 % | **₴47 627 · 27.8 %** |
| Потребують уваги | 0 | 0 | **13** (1 urgent) |
| Собівартість складу | ₴84 077 | ₴84 077 | ₴84 077 (correctly unchanged) |

**All three predicted symptoms cleared, and nothing else moved.** `master_row_inactive` went to zero
in one index change, which is the direct confirmation that those 72 entries were one formula defect
rather than 72 catalogue omissions. Warehouse *cost* stayed identical, exactly as predicted — it never
depended on the broken column, and the fact that it did not shift is as much a part of the proof as
the profit appearing.

The stock alert panel now reports **13 SKUs needing purchase, 1 urgent**. That information existed
in the data the whole time and was invisible on the dashboard.

### Plausibility check

Warehouse cost `₴84 077` against potential profit `₴25 889` at a stated 22.7 % is consistent with the
order-level net margins visible in the Замовлення tab (22–56 %). The stated percentages do not equal
`profit / (cost + profit)` — 22.7 % vs 23.5 %, and 27.8 % vs 28.7 % — which is expected given the
`(rrc − cost * 1.05)` overhead factor in the profit formula. Nothing here looks wrong; if the owner
ever wants the exact denominator confirmed, that is where to look.

### Performance is now the one thing worth watching

`elapsed_ms`: 5750 → 7562 → **11191**. The check has grown as each repair restored real data for it
to read. Eleven seconds is still acceptable for a deliberate click, and it retrospectively vindicates
the click-to-run decision — this would have been intolerable on every Огляд load. If it keeps
climbing, batching the ~20 individual `Майстер_Товарів` seed reads into one range read is the obvious
first optimisation. Not urgent.

## Remaining after pass 1

- 72 × `master_row_inactive` — one root cause, `Майстер_Товарів!P2`. Pass 3.
- 5 × `formula_column_literal` in `Товари!B`/`J` and `Розхідники!F:H` — genuine, still unaddressed,
  and **no fill-down is authorised**. A literal can match the formula's visible result while still
  being structurally wrong, so each row needs its own confirmation.
- 1 × `rrp_mismatch_3dp` — clears in pass 2.
