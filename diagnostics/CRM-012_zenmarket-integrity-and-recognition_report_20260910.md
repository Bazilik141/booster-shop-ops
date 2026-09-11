# CRM-012 — ZenMarket integrity and revenue recognition

Date: 2026-09-10

## Scope and baseline

- V171 is recorded as the byte-verified live baseline in
  `crm/apps-script/SOURCE_STATE.md`. CSV comparisons must be parsed as CSV and
  are lossy for a quoted regex line; future identity checks require `.gs` or
  `.txt`.
- Owner confirmed the pre-WP2 spreadsheet copy: `Booster Shop CRM — облік
  товарів – 10 вересня, 22:42 (копія)`.
- This report covers local source and tests only. No Apps Script paste,
  publication, Sheet write, integrity run, or balance repair was performed.

## Implemented

### WP1–WP2 — UAH top-up gap

- Replaced all four hardcoded missing-UAH counters with
  `crm012ZenMissingTopupUah_()`, which scans only `historical_topup` and
  `manual_topup` rows in `ZenMarket_Рахунок`.
- Added the idempotent, fixed-target owner function
  `crm012ZenRecordHistoricalTopupUahForOwner()`. It verifies exactly one
  `ZEN-HIST-008` of +JPY 70,000, records owner-confirmed UAH 22,000 on that
  ledger entry, and creates exactly one linked cash-flow top-up entry. It never
  derives either amount or changes the JPY rate/balance.
- Cash Flow now renders an explicit notice while the dynamic counter is above
  zero: those top-ups are excluded from cash-out and are not zero.

### WP3 — supplier authority and LOT-0181 guard

- The sheet form now requires explicit `Постачальник` with only
  `zenmarket_jp`, `supplier_ua`, or `other`; it writes that value instead of
  unconditional `zenmarket_jp`.
- `force_zenmarket` / `forceZenmarket` was removed completely. Zen lot sync now
  trusts only the supplier column in every call path.
- `setupCrm012PurchaseSupplierForm()` sets up the form field at
  `Внести_закупку!B10` with validation and before/after integrity gates.
- `crm012ZenSheetFormMislabelScanForOwner()` reports index/supplier conflicts.
  Historic row provenance is unavailable: a prior row labelled
  `zenmarket_jp` cannot safely be classified as an OLX row from current data.
- `crm012RepairLot0181AfterOwnerApproval('CONFIRM_LOT-0181_ONLY')` is bounded
  to `LOT-0181` / `1158736408`: it calculates the compensation from the
  current Zen index, posts an append-only correction, changes supplier to
  `other`, and removes only that index entry. It cannot run without the exact
  owner confirmation token.

### WP4 — recognition month

- `setupCrm012RevenueRecognition()` appends `Продажі!Дата отримання` only when
  absent and returns bounded integrity before/after output.
- Both menu and dashboard sale-update paths stamp the received date exactly
  once when status becomes `Отримано`.
- One recognition-date function now applies the owner rule: regular order →
  sale date; reconciled preorder → later payment/received date; historic
  preorder → cost-finalization date; otherwise sale-date fallback.
- Finance exposes `recognition_fallback_orders`. Cash-in continues to use its
  actual payment-date rule and is not silently reclassified as P&L.
- Updated reads that formerly capped `Продажі` at 31–33 columns now use its
  actual width: sales-entry cache, preorder migration/reconciliation, Mystery
  Box recomputation, test-order scan, consumable audit, stock reservation,
  FIFO diagnostics, and exact-row lookup.

### WP5 — report first

- Added `crm0123dpNameDivergenceReportForOwner()`: a read-only merged report
  of CRM Products, `Майстер_Товарів`, and the 3D-P catalogue. It flags
  placeholders, divergent names, and returns no write action.
- Live-card title evidence is intentionally `null` until a human verifies each
  card. No canonical name is invented and no rename path is enabled in this
  round.

## Local validation

```text
Code.gs syntax passed
node --test crm\apps-script\tests\*.test.mjs
tests 57
pass 57
fail 0
```

`git diff --check` passed. The local CRM-012 test covers recognition-date
selection, missing-UAH counting, supplier/force-flag removal, Cash Flow notice,
and report-only 3D naming.

## Required owner-run order after paste/publish

1. Run `apiIntegrityCheck_()` and retain its bounded output.
2. Run `setupCrm012RevenueRecognition()` and
   `setupCrm012PurchaseSupplierForm()`; retain both before/after outputs.
3. Run `crm012ZenSheetFormMislabelScanForOwner()` and review the output.
4. Run `crm012ZenRecordHistoricalTopupUahForOwner()`; then verify the dynamic
   missing-UAH counter is 0 and Cash Flow notice disappears.
5. Review the scan. Only then run the exact LOT-0181 repair function with its
   confirmation token. Do not repair any additional lot without a new owner
   decision.
6. Run `crm011ZenMarketVerificationForOwner()`, `apiIntegrityCheck_()`, and
   Finance/month summaries for the before/after month comparison.
7. Run `crm0123dpNameDivergenceReportForOwner()`, verify live cards, and
   approve canonical names per SKU before any future rename work.

## Known limits and gates

- The historic cost-finalization date is only a proxy: later FIFO/Mystery Box
  recalculations can move it forward. The new received-date stamp prevents that
  drift for future completions.
- No current-sheet provenance identifies every old non-ZenMarket purchase
  inserted through the old sheet form. The report must not be used to infer
  those suppliers.
- Publication, all live data mutation, both integrity outputs, exact JPY
  compensation, resulting balance, month before/after values, and the complete
  3D divergence table remain owner-gated runtime evidence.
