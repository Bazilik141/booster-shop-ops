# Codex Report — 3DP-CATALOG: canonical correction and migration rehearsal

Date: 2026-09-06

## Scope

Processed the continuation checkpoint and Claude's 72-row canonical decision handoff. The artifacts use the approved 62 active / 10 inactive split, 39 name changes, three article changes and three status changes. The owner subsequently applied the guarded CRM correction and 3D-P catalogue migration; no Apps Script Web App publication, OpenCart record or dashboard was changed by Codex.

## Files touched

```text
plans/3dp-catalog-reset-20260902/migration-payload.json
plans/3dp-catalog-reset-20260902/import-review.md
plans/3dp-catalog-reset-20260902/fifo-contract.md
plans/3D-P_sku-naming-convention_20260807.md
handoffs/handoff_3DP-CATALOG-MIGRATION-CONTINUE_codex_20260905.md
crm/apps-script/Code.gs
crm/apps-script/SOURCE_STATE.md
crm/apps-script/tests/integrity-check.test.mjs
scripts/build-3dp-catalog-migration.py
scripts/generate-3dp-catalog-wrappers.py
scripts/generate-3dp-canonical-correction.py
scripts/3dp-catalog-reset/TemporaryCrmCatalogCanonicalCorrection.gs
scripts/3dp-catalog-reset/Temporary3dpCatalogMigration.gs
scripts/3dp-catalog-reset/Temporary3dpFifoRehearsal.gs
scripts/3dp-catalog-reset/Temporary3dpFifoLiveCheck.gs
scripts/3dp-catalog-reset/TemporaryCrm3dpFifoRemoteCheck.gs
scripts/3dp-catalog-reset/fifo-rehearsal-contract.test.mjs
scripts/3dp-catalog-reset/canonical-mapping.test.mjs
```

## Local validation

```text
payload build: ok, records=72, active=62, inactive=10
canonical mapping test: ok, article_changes=3, name_changes=39, status_changes=3
CRM correction wrapper syntax: pass
3D-P migration wrapper syntax: pass
catalogue/FIFO contract probe: pass
manufactured-batch FIFO tests: 3/3 pass
CRM atomic FIFO commit contract: pass
git diff --check: pass
```

`node --test` could not spawn child test processes in the managed Windows environment (`spawn EPERM`). Running both test files directly succeeded. The historical `scripts/3dp-catalog-reset/preflight.test.mjs` path referenced by memory/current handoff is absent from the current repository, so it was not claimed as run.

## Safety and idempotency

- `TemporaryCrmCatalogCanonicalCorrection.gs` locates all 72 rows by the preserved `source=<tab>!<row>` provenance in the import note and verifies the current article, name, status, RRP and formula columns before writing.
- The CRM rehearsal mutates only a Drive copy, verifies related business-sheet fingerprints, proves the live CRM unchanged, trashes a successful copy, and opens a 30-minute apply gate tied to the exact live fingerprint.
- The live CRM apply is owner-run, creates a fresh Drive backup, changes only `Товари` columns A/C/L on the approved rows, and never invokes the legacy full-catalogue Apply or broad restore.
- `Temporary3dpCatalogMigration.gs` contains `catalogMigration3dpRehearsalV2()`, which applies the full migration only to a copy and verifies the live workbook and protected sheets unchanged. For formula-driven `Аналітика`, the protected fingerprint covers formulas and non-formula cells while intentionally ignoring recalculated formula results.
- CRM and 3D-P repeated previews report `already_applied` after the owner-run writes.

## Current gate

Owner-run CRM preview and copy rehearsal passed on 2026-09-06 at 11:27–11:28 Kyiv:

```text
preview: ok=true, state=ready, problems=[], active=59, inactive=13
rehearsal: ok=true, state=rehearsal_passed, live_unchanged=true
rehearsal_copy_trashed=true, changed=42, active=62, inactive=10
live fingerprint: 324b438edb71b444f41c109068ba56b6b54e0f7bdab0a75eb06a10737ee5949b
```

This closed the CRM rehearsal gate. The first Apply attempt at 12:03 was safely rejected as `blocked_fresh_rehearsal_required`; it performed no write. After a fresh rehearsal, the owner-run live CRM correction succeeded on 2026-09-06 at 12:22 Kyiv:

```text
ok=true, state=applied, changed=42, active=62, inactive=10
backup_file_id=1Oa3lCzTIRV7MB9_8RABRLz36r_JiBIcFVV77NI7bWdI
```

The CRM live-write gate is closed. The owner-run post-apply preview passed at 12:23 with `state=already_applied`, `problems=[]`, `active=62`, `inactive=10`. The public integrity wrapper then passed at 12:28 with `ok=true`, `compared=7`, `skipped_missing_crm_rrp=6`, `deferred=null`, and exactly the six expected `rrp_mismatch_3dp` findings: `BR-CHARM-100`, `BR-BULB-100`, `BR-MEW-100`, `BR-PIKA-100`, `FIG-LUFFY-500`, and `FIG-LUFFY-410`. No other problem code appeared. `clean=false` is expected only because 3D-P still has the old catalogue.

The owner-run 3D-P preview passed on 2026-09-06 at 13:01 and again at 13:05 Kyiv: `ok=true`, `state=ready`, exact eight old SKUs, `target_count=72`, all seven protected business-state counts and SHA-256 fingerprints matched the approved pre-apply evidence, and both FIFO journals remained empty.

The 13:05 copy rehearsal stopped at target verification with `state=rehearsal_failed`, `live_unchanged=true`, and `error="Rehearsal target verification failed."`. The failed rehearsal copy was deliberately retained by the wrapper for read-only diagnosis. No live workbook write occurred.

The owner-run read-only diagnostic at 13:16 proved the migrated copy had exact 72 target keys (`missing=[]`, `extra=[]`), the complete expected business reset, 72 availability keys, and empty FIFO journals. Its reported row mismatches were all column `G` cells carrying inherited duration formats. The only protected fingerprint difference was formula-driven `Аналітика`; `_Аудит_API` and `_Журнал_налаштувань_3DP` were byte-equivalent by the bounded fingerprint.

A second owner-run rehearsal at 13:29 again left live unchanged and stopped at target verification. Its 13:33 diagnostic narrowed the remaining issue to seven column `G` cells with the opposite inherited format (plain decimal), while target keys, business reset, and all protected fingerprints passed. The canonical intake already converts source spreadsheet day fractions to decimal hours (`source × 24`); payload values are therefore correct and must not be multiplied again. The migration defect was that clearing contents preserved a mixed set of old row formats.

The wrapper now explicitly normalizes `Номенклатура!G2:G73` to the existing `PRINT_TIME_ENTRY_3DP.numberFormat` (`0.##########`), verifies that format and the numeric decimal-hour value, and includes number formats in rollback snapshots. The protected fingerprint for formula-driven `Аналітика` covers formulas plus non-formula cells while excluding formula results that legitimately recalculate. 3D-P Apply remains blocked until a fresh corrected copy rehearsal passes.

The fresh owner-run rehearsal passed on 2026-09-06 at 13:40 Kyiv: `ok=true`, `state=rehearsal_passed`, `live_unchanged=true`, `rehearsal_copy_trashed=true`, `target_count=72`, `active=62`, `inactive=10`, and `analytics_rows=62`. All reset-state counts matched the target: the six reset business/journal areas were empty, `Наявність` contained 72 keys, and both FIFO journals contained zero keys. This closes the 3D-P rehearsal gate; live Apply remains owner-run and must create its backup before writing.

The owner-run live 3D-P Apply succeeded on 2026-09-06 at 13:45 Kyiv. Its internal post-write preview returned `state=already_applied` with the exact 72 target SKUs and reset business state. Final output was `ok=true`, `already_applied=false`, `count=72`, `active=62`, `inactive=10`, `analytics_rows=62`; Drive backup `1xIMVkT2TAHAlnrRC16AvnApJgh2gQjRZR05eSWZqwJ4` was created before the write.

The owner-run CRM integrity post-check returned at 13:48 Kyiv with `ok=true`, `clean=true`, `problems=[]`, `compared=62`, `skipped_missing_crm_rrp=6`, and `deferred=null`. The six temporary RRP mismatches disappeared and the six intentionally unresolved inactive prices remained explicit skips rather than fabricated values. However, this accounts for only 68 of 72 rows. Four inactive products with prices — `FIG-MAGIK-300`, `ACC-3D-DITTO-420`, `ACC-3D-DITTO-430`, and `ACC-3D-LUFFY-500` — were absent from the default active-only `3dp_skus` response and silently unclassified.

After the owner saved the reviewed `include_archived=true` change in the bound CRM editor, `catalogCanonicalCrmIntegrityCheck` ran at 14:46–14:47 Kyiv and returned `ok=true`, `clean=true`, `problems=[]`, `compared=66`, `skipped_missing_crm_rrp=6`, `deferred=null`, `elapsed_ms=13238`. This accounts for all 72 catalogue rows and closes the cross-workbook RRP coverage gate for the bound editor source. The owner then removed `TemporaryCrmCatalogMigration.gs`, `catalogMigrationCrmRehearsalV2`, and `TemporaryCrmCatalogCanonicalCorrection.gs` from the live project and published `CRM Auto V165 — full 3D-P integrity coverage` at 17:38 Kyiv. The subsequent dashboard-triggered V165 check returned `ok=true`, `clean=true`, `problems=[]`, `compared=66`, `skipped_missing_crm_rrp=6`, `deferred=null`, `elapsed_ms=12653`. Full post-publication endpoint coverage is closed.

The owner-run FIFO copy rehearsal passed at 19:47 Kyiv. It returned
`state=rehearsal_passed`, `live_unchanged=true`, `rehearsal_copy_trashed=true`,
used `BR-CHARM-100` across two oldest-first batch layers, proved idempotent sale
and reversal replay, blocked reactivation with the old operation id, repaired
one deliberately damaged batch counter, and finished with clean reconciliation.

The owner published `3D-P API — FIFO reversal, reconciliation and repair` as V32
at 20:17 Kyiv and `CRM Auto V166 — 3D-P FIFO reversal integration` as V166 at
20:47 Kyiv. The post-V166 dashboard integrity check returned `clean=true`,
`problems=[]`, `compared=66`, `skipped_missing_crm_rrp=6`, `deferred=null`, and
`elapsed_ms=12858`. At 20:59 Kyiv the CRM bound-source smoke called the deployed
V32 `3dp_fifo_reconcile` route through the saved production configuration and
received `ok=true`, `clean=true`, zero problems, zero repairable batches, zero
current batches and zero allocations.

## Remaining work after owner evidence

- Remove the two read-only smoke wrappers from the bound source editors; they are
  not part of either deployed version and do not affect live behavior.
- Treat the first real manufactured batch, sale and later reversal as bounded
  operational QA; run reconciliation after that first cycle.
- Rebase the stale broad dashboard/API fixtures separately; they do not negate
  the focused FIFO tests, copy rehearsal or deployed-route smoke recorded here.

## Production status and residual QA

The catalogue migration and broader FIFO publication are complete. V32 implements
immutable manufactured batches, oldest-batch allocation, insufficient-costed-stock
rejection, idempotent manufacture/sale/gift commits, reversal, reconciliation and
fingerprint-gated counter-only repair. V166 routes CRM cancellation/return to the
reversal and prevents an old reversed operation from becoming active again. The
focused tests, copy rehearsal, Web App publications, full 72-row CRM integrity
check and remote deployed-route smoke passed. Production use is allowed; because
opening stock is intentionally zero, a sale must have sufficient manufactured
batch quantity or it will be blocked rather than assigned a fabricated cost.

Current bounded local evidence:

```text
catalog-fifo.test.mjs: 9/9 pass
CRM atomic FIFO commit contract: pass
CRM FIFO cancellation/reactivation contract: pass
CRM integrity archived-catalogue request contract: pass
3D-P FIFO copy-rehearsal static contract: pass
Serhiy calculator: 5/5 pass
Serhiy duplicate-submit/operation state: 5/5 pass
Serhiy settings controls: 2/2 pass
draft type/category contracts: 2/2 pass
```

The broader dashboard/API suite is not currently clean evidence: two API projection tests require an absent V29/V25/V23 baseline export; process-spawning server/verifier tests hit the managed Windows `spawn EPERM` boundary; and three older dashboard tests assert pre-FIFO UI/stock behavior that the current candidate intentionally replaced. These failures are test-fixture/contract drift and environment limitations, not accepted passes. The tests must be rebased to the current FIFO contract before publication.

Both `SOURCE_STATE.md` files now record the owner-reported V32/V166 publications
and the bounded post-publication evidence. No fresh version export/byte comparison
was supplied, so publication identity rests on the owner's deployment screenshots
and runtime results rather than a new exported-source hash.

## Risks

- The source spreadsheet still says inactive for `BR-BULB-100`, `BR-SQUIR-100` and `BR-PIKA-100`; a later import can reverse the owner override until the source is corrected.
- Six inactive products intentionally retain null RRP/buyout.
- OpenCart has non-zero quantities for some 3D products while migrated opening stock is zero; insufficient costed stock must remain an explicit error, never a fabricated FIFO cost.
