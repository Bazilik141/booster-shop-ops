# CRM one-time main-code cleanup — local candidate

Date: 2026-09-13

## Outcome

Prepared, but did not publish, a narrow cleanup candidate for the main CRM
Apps Script. It removes confirmed spent task repairs and their dedicated tests.
No Sheet, Apps Script project, trigger, dashboard, or external record was
written during this work.

The preceding ZenMarket validator is live in CRM V178. Owner runtime proof is
clean integrity, successful unwrapped ZenMarket verification, and a real
Finance KPI of `-¥3 685`.

## Removed from the local candidate

| Family | Boundary |
| --- | --- |
| 2026-09-01 stock-counting repair | Marker, private diagnostics/repair helpers, and public diagnostic/repair wrappers; deleted `tests/stock-counting-repair.test.mjs`. |
| CRM-011 FIFO OC-FOP-0324 repair | Complete isolated block previously at V177 lines 9136–9277, including preview/apply helpers; deleted `tests/crm-011-fifo-cost-repair.test.mjs`. The owner preview established `would_change:false`. |
| CRM-011 payment-date setup | `crm011BackfillPaymentDates_()` and `setupCrm011FinanceColumns()`. Permanent date readers and normal date stamping remain. |
| CRM-012 one-time setup | `setupCrm012RevenueRecognition()` and `setupCrm012PurchaseSupplierForm()`. Permanent recognition, supplier validation, and mutation paths remain. |
| CRM-012 fixed targets | Historical top-up UAH writer, Zen sheet-form scan, LOT-0181 repair wrapper, and 3D name-divergence report. Their completed results are retained as task evidence, not executable production code. |

The stale error message referring to deleted `setupCrm011FinanceColumns()` was
replaced with a generic owner-approved CRM schema recovery message.

## Explicitly retained

- CRM V178's pure Zen schema validator and all ordinary ZenMarket operations.
- `setupCrm011ZenMarketAccount()` and seed helpers: permanent recovery route,
  not spent code. A later task may move this family to its own callable Apps
  Script file.
- All four installed trigger targets: `maintainCrmRowCapacity`,
  `runNightlyInventoryMaintenance`, `keepWarm`, `runNewsPruneOnce`.
- Trigger installers, catalog/menu setup, test-order cleanup, ongoing formula
  capacity maintenance, and ordinary inventory/FIFO/recognition/order flows.

## Local verification

- Apps Script parse: passed.
- `git diff --check`: passed.
- Obsolete-symbol scan across `Code.gs` and dashboard: no matches.
- Full local Node suite: 31 files and 60 tests passed, 0 failed. This includes
  the five directly affected suites:
  `crm-011-finance-dashboard`, `crm-011-r2-pass-b`,
  `crm-011-zenmarket-account`, `crm-012-integrity-recognition`, and
  `dashboard-load-telemetry`.
- Current candidate source identity after the review-comment correction:
  normalised SHA-256
  `60d7b5cbf3ab3504628486792b8d823249daa3ca824d589238dd52c85eff5205`;
  10,229 normalised lines; 575 top-level functions.

## Publication gate

1. Claude reviews the exact local diff, including that setup/seed code was
   retained and no trigger target was removed. The review-comment correction
   and the full test suite must be complete before publication.
2. Owner confirms the list of files in the bound Apps Script project while
   publishing. The three local task files `TEMP_CRM_COST_0355_repair_20260901.gs`,
   `TEMP_CRM_COST_0355_order_repair_V2_20260901.gs`, and
   `CRM-011_followup_data_import_20260908.gs` need a separate lifecycle audit;
   they are not part of this deletion wave.
3. Owner pastes and publishes the reviewed candidate as a new Apps Script
   version.
4. Owner runs `apiIntegrityCheck_()`; it must be clean.
5. Owner runs `crm011ZenMarketVerificationForOwner()`; it must return
   `ok: true`.
6. Owner opens Finance and confirms the real JPY KPI remains visible.
7. Owner exports the published `Code.gs` as `.gs` or `.txt`, normalises UTF-8
   BOM and line endings to LF, and compares its SHA-256 with the candidate hash
   above. A mismatch stops the task for source-drift review.

## Risk and rollback

Risk is low but CRM-scoped: this deletes recovery/diagnostic code. Rollback is
to paste the prior V178 source, which remains the known-good published
baseline. This candidate does not include the future move of Zen setup/seed
into a separate Apps Script file.
