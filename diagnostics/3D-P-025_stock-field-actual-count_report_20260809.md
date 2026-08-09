# Codex Report — 3D-P-025: stock correction uses actual count

Date: 2026-08-09

## Scope

Implemented the dashboard-side actual-count workflow and returned fixes **WP1/WP2**. The deployed
3D-P API contract remains `delta`; no Apps Script or workbook change is required.

## Behaviour

- `Фактична наявність зараз, шт` accepts an actual physical count and previews the derived delta.
- An empty field is invalid; a true `0` remains a valid actual count.
- Immediately before confirmation, the dashboard reads the raw row from `Наявність`, takes
  `Наявно зараз, шт` directly from that row, and calculates the delta from this fresh count.
- An absent row, `ROW_NOT_FOUND`, or a raw row without `Наявно зараз, шт` stops before any POST. It
  cannot be silently parsed as `0`.
- Equal actual/fresh counts return before `3dp_adjust_stock`, so no zero-delta ledger row is made.

`Наявність!G` is never written. The existing `expected_current` guard and append-only ledger remain
the final server-side protections.

## Regression evidence

`tests/3d-p-025-stock-actual-count.test.mjs` uses the real `3dp_get_row` transport shape:

- rendered count `196`, fresh `Наявність` count `195`, actual `97` → one POST with
  `expected_current: 195` and `delta: -98`;
- empty input previews a required-field error and never reads or posts;
- a raw `Номенклатура`-shaped response without availability and `ROW_NOT_FOUND` both make no POST.

The local test passed. No live dashboard or workbook write was performed.

## Owner QA

For a known active 3D-P SKU:

1. Enter an actual count and a reason of at least three characters. The preview uses the currently
   rendered count.
2. Before confirming, independently change or re-read stock if practical. The saved ledger delta
   must be based on the just-read `Наявність!G` count, not the older preview base.
3. Clear the actual-count field: the button must stay disabled. Enter literal `0`: it must become a
   valid correction when stock is nonzero.
4. Re-enter the fresh count: the button must be disabled with `Змін немає`, and no ledger row added.

## Rollback

This dashboard file is one coupled delivery with CRM-005 WP3. Reverting
`dashboard/booster-dashboard.html` and hard-refreshing reverts **both** the 3D-P-025 fixes and the
compact CRM integrity tile; they are not independently reversible in this upload. It does not
reverse an already-created ledger row, so owner QA must happen before accepting a correction.

