# CRM main-code one-time maintenance audit

Date: 2026-09-13

## Scope

Read-only audit of `crm/apps-script/Code.gs` for historical one-time repairs,
setup wrappers, and task diagnostics that should not remain in the production
main file. No Apps Script source, dashboard, Sheet, trigger, or deployment was
changed.

This is an implementation inventory for a later, separately reviewed cleanup.
It is not authorization to remove a function merely because its name contains
`repair`, `setup`, or `Once`.

## Source confidence

- The last byte-verified full source export is CRM V173, dated 2026-09-11.
- The owner subsequently reported CRM-013 publication/runtime results, but no
  new complete `.gs` or `.txt` export was supplied. Therefore this audit proves
  the local mirror and documented execution evidence, not byte identity with
  the current bound Apps Script project.
- Before any deletion is pasted live, obtain a fresh complete `.gs`/`.txt`
  export and compare it with the exact cleanup candidate. A version label or
  telemetry output is not a source-identity check.

## Confirmed spent code — remove in the cleanup candidate

| Local area | Why it is spent | Reachability / dependent cleanup | Required evidence |
| --- | --- | --- | --- |
| `CRM_STOCK_COUNTING_REPAIR_MARKER_` and the `crmStockCounting...` block, `Code.gs:3975-4163` | Dated repair for the 2026-09-01 WRT/HWAK/MSYM correction. The final owner repeat was idempotent: zero remaining formula/write-off/substitution mutations, 95/95 balances verified, clean integrity. | The helpers are called only by `diagnoseCrmStockCounting20260901()` and `repairCrmStockCounting20260901()`. No dashboard action, API route, or documented trigger references them. Remove the complete block and `tests/stock-counting-repair.test.mjs`. | Existing live completion record in `SOURCE_STATE.md`; re-run ordinary integrity after the later cleanup deployment. |
| `crm011BackfillPaymentDates_()` and `setupCrm011FinanceColumns()`, `Code.gs:9803-9844` | Structural CRM-011 setup completed on 2026-09-09. The sole payment-date backfill wrote zero cells; only the fiscal-receipt header was appended. | `crm011BackfillPaymentDates_()` is called only by this setup wrapper. Keep the permanent header readers and normal payment-date stamping. Update the focused finance test so it no longer asserts the setup entry point. | Existing owner setup and clean before/after integrity evidence. |
| `setupCrm012RevenueRecognition()`, `Code.gs:9846-9865` | The owner ran it successfully: `Дата отримання` was appended as column 35 and both integrity checks were clean. | Delete only the setup wrapper. Keep `crm012SalesRecognitionColumns_`, `crm012RecognitionDateForSalesRow_`, and received-date stamping: they are production behaviour. | Existing CRM-012 completion transcript. |
| `setupCrm012PurchaseSupplierForm()`, `Code.gs:9867-9883` | Supplier form setup was reported complete in CRM-012. | Delete only the setup wrapper. Keep normal supplier validation and purchase paths. | Existing CRM-012 completion transcript. |
| `crm012ZenRecordHistoricalTopupUahForOwner()`, `Code.gs:9985-10004` | Fixed-target historical `ZEN-HIST-008` UAH recording completed; follow-up verification returned `historical_topup_uah_missing: 0`. | No production operation calls this public fixed-target writer. Keep dynamic missing-UAH calculation and ordinary ZenMarket top-up/correction paths. | Existing owner result and post-repair Zen verification. |
| `crm012ZenSheetFormMislabelScanForOwner()` and `crm012RepairLot0181AfterOwnerApproval()`, `Code.gs:10085-10118` | The scan was reviewed; the exact `LOT-0181 / 1158736408` correction completed and the Zen balance returned to JPY -3,685. | No dashboard/API/trigger route calls either function. Keep generic Zen ledger/index helpers and ordinary supplier-controlled purchase sync. | Existing scan, repair, and post-repair verification output. |
| `crm0123dpNameDivergenceReportForOwner()`, `Code.gs:10120-10138` | It completed read-only with `flagged: 0`, `placeholders: 0`, `divergent: 0`; CRM-013 relied on that accepted outcome. | Remove the task-only report and its dedicated assertion in `tests/crm-012-integrity-recognition.test.mjs`. Do not alter 3D catalogue/RRP production sync. | Existing compact owner result. |

## Candidates requiring a decision or a small refactor first

| Local area | Why it cannot be blindly deleted | Safe cleanup condition |
| --- | --- | --- |
| CRM-011 FIFO drift set at `Code.gs:9136-9276` (`diagnoseCrm011FifoCostDrift`, preview/apply wrappers, and their private helpers) | Documentation conflicts: the 2026-08-20 report and `SOURCE_STATE.md` say live preview/apply/read-back remained required; a later Claude review calls the preview/apply wrappers spent. There is no supplied successful live repair transcript for `OC-FOP-0324`. | Owner supplies the actual apply/read-back/integrity output, or explicitly abandons this unexecuted repair. If it remains needed, move it to a standalone temporary CRM task file before deleting it from `Code.gs`. |
| `setupCrm011ZenMarketAccount()` plus seed helpers at `Code.gs:9885-9964` | The account is demonstrably in use, but `crm011ZenRequireSetup_()` currently calls `crm011ZenEnsureSheet_()`, which can write missing headers during ordinary paths. Deleting only the public setup wrapper leaves hidden setup mutation in a live reader. | Split into a pure schema validator for normal use and a one-time setup/seed file. Verify the three existing Zen sheets and headers before the main-code cleanup. |
| `setupCrm004PackagingValidation`, `setup3dp019FixtureUsagePhaseB`, `setupOrderComponentUsage`, `setup3dpOrderLineAccountingCRM` | These look like structural setup writers, but this audit has no compact completion evidence for each exact schema/formula state. Some private helpers are also involved in normal order/accounting checks. | Collect a bounded read-only schema/formula proof for each feature, then delete only the completed public setup path and private helpers that have no remaining production caller. |
| `crm011PaymentDateAuditForOwner()` and `crm011PassBVerificationForOwner()` | Read-only task diagnostics, not writers. Their recorded passes do not prove they have no future operational value. | Owner chooses either to retain them as periodic finance controls or to remove them as completed CRM-011 diagnostics. |

## Explicit keep list

These functions or families must remain in `Code.gs` for the current product.

| Area | Evidence |
| --- | --- |
| `runNewsPruneOnce()` | It is an active Apps Script trigger target. Static caller search cannot see trigger invocation. |
| Formula-capacity maintenance (`runCrmFormulaCoverageRepairBatch`, row-capacity setup) | The batch handler is an active trigger target and supports ongoing formula coverage. |
| `apiTestOrderCleanup_()` and `testOrderCleanup...` | The production dashboard invokes `action: 'test_order_cleanup'`; it is an owner-only operational workflow, not a completed one-off repair. |
| `setupCrmCatalogOptionInfrastructure()` | It is wired into the CRM custom menu as `Оновити довідники SKU`. |
| Inventory migration, FIFO reservation, recognition-date, ZenMarket normal top-up/correction, and 3D/order component runtime paths | These are active application behaviour despite words such as `migration`, `repair`, or `ensure` in individual helper names. |

## Cleanup implementation boundary

1. Use a fresh live source export as the baseline; do not cleanup against the
   stale V173 identity claim alone.
2. Remove only the confirmed-spent rows above in the first patch. Do not fold
   the decision/refactor candidates into the same change.
3. Remove or revise tests that only expose deleted task functions; retain tests
   that validate permanent behaviour.
4. Run local parse, the focused CRM regression suite, `git diff --check`, then
   owner paste/publication and one bounded `integrity_check`.
5. Keep every future temporary repair or task diagnostic in a standalone Apps
   Script file named exactly after its task ID; delete it locally and live once
   its verified run is complete.

## Claude review questions

1. Confirm the confirmed-spent table is complete and that each deletion set has
   no hidden trigger, dashboard, or API route.
2. Resolve the CRM-011 FIFO transcript conflict before any deletion.
3. Review the ZenMarket setup split: ordinary reads must become non-mutating
   before the setup/seed code can leave the main file.
4. Confirm whether the four older structural setup wrappers should become a
   later cleanup wave or remain recovery tools pending schema evidence.

## Risks

- Removing a dynamically triggered function based only on static search can
  silently break background automation.
- Removing an unexecuted repair loses the owner-approved recovery route.
- A partial ZenMarket cleanup could leave a schema writer reachable from normal
  finance/purchase reads.
- The cleanup itself changes code only, but it is CRM-risky and needs the
  standard source-identity, focused-test, owner-publication, and integrity
  gates.

## Claude review response — 2026-09-13

The review is accepted with the following corrections and sequencing changes.

### A1: source-identity blocker remains; cache rollback is not pending

The last complete byte-verified export remains V173, so a fresh complete
`.gs`/`.txt` export is mandatory before any cleanup candidate is prepared.

The review compared the local plain-bypass cache code to historical V176 gzip
and shard behaviour. The owner subsequently supplied live runtime evidence for
the post-review cache cleanup: `overview_secondary` and `ltv_report` now return
`cache_state: value_too_large`, while small responses return `stored_plain`.
Thus the intended cache direction is already live; no cache revert may be
carried incidentally by this cleanup. The fresh export is still required to
prove exact source identity and to record the actual post-V176 code state.

### Returned inventory entries

| Function | Classification after review | Action in this CRM cleanup |
| --- | --- | --- |
| `tgSetupCommands()` (`Code.gs:6912`) | Unclassified owner-run Telegram command registration; zero static callers does not prove the bot command list is obsolete. | Keep pending a separate Telegram/content decision. Do not bundle it with CRM code deletion. |
| `testNewsEditorialAudit()` (`Code.gs:8570`) | Development-only public test entry point with zero static callers. | Candidate for a separate News/Telegram hygiene wave, after the installed-trigger check. Do not bundle it with CRM data-repair deletion. |
| `setupNewsDigestTrigger()` (`Code.gs:7323`) | Trigger installer; its existence is required to restore the active News trigger if it is removed. | Explicitly keep. |
| `setupCrmRowCapacityTrigger()` (`Code.gs:1029`) | Trigger installer for the active formula-capacity handler. | Explicitly keep. |

### Corrected deletion mechanics

- The isolated CRM-011 FIFO block ends at `Code.gs:9277`, inclusive. A future
  deletion must not leave the final closing brace behind.
- Before deleting `setupCrm011FinanceColumns()`, rewrite
  `crm011RequireColumn_()` so it no longer instructs the owner to run a
  nonexistent function. The replacement must say that the required header is
  absent and needs a separate owner-approved maintenance recovery.
- The ZenMarket dashboard hint and the three older setup error strings are not
  modified in the first deletion wave. Their referenced setup/recovery paths
  remain until the later Zen split or a separately approved structural recovery
  redesign.

### Required gates before code deletion

1. Owner provides the fresh full main-CRM source export; the mirror comparison
   and post-V176 state are recorded in `SOURCE_STATE.md`.
2. Owner reads the bound project's installed Apps Script trigger list once;
   handler names are recorded in `SOURCE_STATE.md`.
3. Owner runs the read-only `previewCrm011OcFop0324Repair()` and retains its
   compact output. `would_change: false` admits the whole isolated FIFO block
   to the first deletion wave; `true` keeps it out and moves it to a separate
   temporary task file if repair is still wanted.
4. The ZenMarket pure-validator/setup split is reviewed and published as its
   own CRM change before `setupCrm011ZenMarketAccount()` and seed code leave the
   main file.

## Gate evidence supplied after review — 2026-09-13

- **A1 closed.** The owner supplied complete V177 source. It is raw Apps Script
  source despite the `.csv` name and is byte-identical to the local mirror
  after normalisation (SHA-256
  `5eaf9171fdfd4be5edf05c2037939e2fa16bd3a41fdcb72b5408604241fb8a5a`, 10,704
  lines). The latest live cache telemetry is the intended direct-bypass state,
  not historical V176 gzip/sharding.
- **Installed-trigger gate closed.** The Apps Script UI lists exactly four
  handlers: `maintainCrmRowCapacity`, `runNightlyInventoryMaintenance`,
  `keepWarm`, and `runNewsPruneOnce`. None is in a removal set.
- **CRM-011 FIFO gate closed.** The owner ran the read-only
  `previewCrm011OcFop0324Repair()`; row 289 returned `would_change:false` and
  `already_applied:true`, with current/recomputed units both `551.90 / 585.01`.
  The complete isolated `Code.gs:9136-9277` block is now admitted to the
  deletion wave.

The separate ZenMarket pure-validator/setup split remains a required preceding
CRM fix before the account setup/seed code is moved into its own standing
recovery file. It is not a spent one-time block and must remain callable.

## Local candidate — ZenMarket pure validator split

Prepared after the V177 comparison; not pasted or published. The candidate
keeps `crm011ZenEnsureSheet_()` as setup-only code and introduces
`crm011ZenValidateSheet_()` for normal paths. `crm011ZenRequireSetup_()` now
validates all three ZenMarket sheets without a schema write. The focused
ZenMarket suite passes 7/7, including a missing-header case that throws without
restoring that header. This candidate must receive its own Claude review and
owner publication/integrity gate before the Zen setup/seed block can be moved
out of the main file as a standing recovery module.
