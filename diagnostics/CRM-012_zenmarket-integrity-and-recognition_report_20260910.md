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

### 2026-09-11 follow-up — actual payment type on sale update

- The dashboard sale editor now offers `Фактичний тип оплати`. It changes the
  payment type across every row of the selected order only when the value
  differs from the existing one, so an untouched legacy value is preserved.
- The Apps Script update path accepts only the four canonical CRM payment types.
  The existing `Продажі` formulas then recalculate payment fees and net profit;
  no FIFO cost, order ID, payment date, or historical sale amount is rewritten.
- A historic unknown payment label remains visible but cannot be re-submitted as
  a new value. Choose a canonical actual method, for example `Еквайринг` →
  `Контроль оплати ФОП`, before saving.
- The finance quality line now renders both review-returned counters:
  `recognition_fallback_orders` and `identity_conflict_orders`.

### 2026-09-11 repair-runner correction — LOT-0181

- The Apps Script Run menu cannot pass the explicit confirmation argument to
  `crm012RepairLot0181AfterOwnerApproval()`. The original function therefore
  correctly rejected a menu launch without an argument.
- `crm/apps-script/TEMP_CRM012_LOT0181_repair_20260911.gs` is a temporary,
  public owner-run wrapper. It passes only the fixed confirmation literal,
  delegates all target checks and writes to the existing guarded function, then
  runs `crm011ZenMarketVerificationForOwner()` and returns both outputs.
- Delete that temporary script file from the live Apps Script project and this
  repository after an `ok: true` result. It must not become a standing writer.

### 2026-09-11 report-runner correction — 3D-P name divergence

- The full read-only 3D-P report can exceed Apps Script's log-size cap because
  most rows repeat the same live-card-evidence note. It did not indicate a
  failed scan.
- `crm/apps-script/TEMP_CRM012_3dp_name_divergence_summary_20260911.gs` is a
  temporary, read-only summary runner. It emits at most 20 compact flagged
  rows (mismatch or placeholder), plus counts and omitted-row count; it makes
  no Sheet, CRM, 3D-P, or storefront write.
- Owner run result: `flagged: 0`, `placeholders: 0`, `divergent: 0`.
  No internal CRM/3D-P rename is warranted. This does not replace the separate
  canonical OpenCart mapping gate or verify storefront card titles.

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
tests 59
pass 59
fail 0
```

`git diff --check` passed. The local CRM-012 test covers recognition-date
selection, missing-UAH counting, supplier/force-flag removal, Cash Flow notice,
both rendered recognition signals, canonical payment-type update, and report-only
3D naming.

## Owner-run completion evidence — 2026-09-11

- The owner published CRM V172 and ran `apiIntegrityCheck_()` clean before the
  structural setup: no problems; 66 3D-P RRP rows compared and six skipped for
  missing CRM RRP.
- `setupCrm012RevenueRecognition()` completed with `added: true` and created
  `Продажі!Дата отримання` as column 35. Its integrity checks before and after
  were both clean.
- The owner reported successful completion of the supplier-form setup, the
  mislabel scan, and the fixed historical ZenMarket UAH top-up procedure.
- `LOT-0181 / 1158736408` repair completed: supplier is now `other`; the
  one-time compensation was JPY 27,078.40; Zen balance changed from JPY
  -30,763.40 to JPY -3,685.00. The repair was new, not an idempotent replay.
- Post-repair `crm011ZenMarketVerificationForOwner()` returned `ok: true`,
  `historical_topup_uah_missing: 0`, `ledger_rows: 14`, `indexed_lots: 0`,
  no problems, and clean integrity.
- The compact 3D-P name report returned `flagged: 0`, `placeholders: 0`,
  `divergent: 0`, and `write_performed: false`. No internal CRM/3D-P rename is
  warranted.

## Remaining bounded owner actions

1. Delete the two temporary files from the live Apps Script project:
   `TEMP_CRM012_LOT0181_repair_20260911` and
   `TEMP_CRM012_3dp_name_divergence_summary_20260911`. They are already removed
   locally.
2. At the next genuine payment correction, smoke-test the new dashboard control
   by changing `Еквайринг` to `Контроль оплати ФОП` and confirming that payment
   fees and net profit recalculate. Do not create artificial sales data for this.

## Owner-run order after paste/publish — completed

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
- The live payment-type smoke test remains pending until the next genuine
  correction. A separate OpenCart canonical mapping/live-card-title audit is
  outside CRM-012 and remains separately owner-gated.
