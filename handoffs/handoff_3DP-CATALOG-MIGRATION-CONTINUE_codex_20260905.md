# 3DP-CATALOG — continuation checkpoint for a new Codex dialogue

Date: 2026-09-05

Executor: Codex · model=gpt-5.6-sol · effort=xhigh

Use Sol with xhigh effort because this is a risky CRM/Google Sheets migration across two Apps Script systems, OpenCart catalogue identities, dashboards, and immutable FIFO accounting. Ultra is unnecessary because the next phase must have one writer and sequential gates.

## Objective

Continue the approved 3D catalogue migration after Claude supplies the canonical 72-row OpenCart name/article decision file. Correct the already-imported CRM catalogue where necessary, migrate the 3D-P workbook, then verify CRM, 3D-P, both dashboards, and manufactured-batch FIFO as one contract.

The owner will attach this file and Claude's completed file:

`handoffs/handoff_3DP-CATALOG-CANONICAL-DECISIONS_claude-to-codex_20260905.md`

Do not continue without reading both documents and the current repository state.

## Current live state

### Main CRM: catalogue migration is already present

The owner ran `catalogMigrationCrmApplyV2`. It wrote the 72-row target catalogue but returned `apply_failed_backup_preserved` because the final global integrity check compared CRM prices to the still-unmigrated 3D-P catalogue. Automatic rollback was not attempted.

The later read-only `catalogMigrationCrmPostApplyCheckV2` proved the actual CRM result is internally correct:

- `ok=true`;
- exact target SKU set: true;
- exact target rows: true;
- formula columns ready: true;
- exact RRP notes: true;
- related cleanup complete: true;
- only expected temporary 3D-P RRP drift: true;
- 72 products: 59 active, 13 inactive.

The CRM currently contains the proposed migration articles and mostly draft-oriented names. Treat them as data to correct after the canonical audit, not as canonical truth.

Current CRM target articles:

```text
ACC-3D-CHARZ-800
ACC-3D-DITTO-410
ACC-3D-DITTO-420
ACC-3D-DITTO-430
ACC-3D-LUFFY-500
ACC-3D-OP-500
ACC-3D-OP-600
ACC-3D-OPFRT-500
ACC-3D-PKBL-400
ACC-3D-PKBL-401
ACC-3D-PKBL-800
ACC-3D-PKBL-810
ACC-3D-PKM-110
ACC-3D-PKM-120
ACC-3D-PKM-130
ACC-3D-PKM-150
ACC-3D-PKM-200
ACC-3D-PKM-201
ACC-3D-PKM-202
ACC-3D-PKM-300
ACC-3D-PKM-600
ACC-3D-PKM-610
ACC-3D-PKM-700
ACC-3D-PKM-710
ACC-3D-PKM-711
ACC-3D-PKM-712
ACC-3D-PKM-800
BR-BULB-100
BR-CHARM-100
BR-CHARM-200
BR-DITTO-400
BR-MEW-100
BR-OP-100
BR-OPFRT-100
BR-OPMUS-100
BR-OPSHP-100
BR-OPSKL-100
BR-OPSTR-100
BR-PIKA-100
BR-PKBL-200
BR-SQUIR-100
BR-UMBRE-100
BR-UMBRE-110
BR-UMBRE-120
FIG-CHARZ-200
FIG-GENG-300
FIG-GEOD-500
FIG-GEOD-501
FIG-GEOD-510
FIG-GEOD-511
FIG-HAUNT-200
FIG-HAUNT-210
FIG-JIGGL-300
FIG-LUFFY-400
FIG-LUFFY-410
FIG-LUFFY-411
FIG-LUFFY-500
FIG-MAGIK-300
FIG-MEW-100
FIG-MEW-300
FIG-NAMI-200
FIG-NAMI-201
FIG-ONIX-200
FIG-ONIX-500
FIG-ONIX-501
FIG-OP-400
FIG-OPSKL-600
FIG-PIKA-300
FIG-PKBL-600
FIG-SQUIR-300
FIG-UMBRE-300
FIG-ZORO-410
```

### CRM related-data cleanup

The verified related state is:

- old test 3D component SKUs removed: true;
- old test 3D sync-journal SKUs removed: true;
- dropped order removed: true;
- unaffected sale row counts preserved: true.

Exact sale row counts:

- `OC-FOP-0317`: 2;
- `OC-FOP-0318`: 5;
- `OC-FOP-0329`: 1;
- `OC-FOP-0339`: 0.

The owner explicitly chose to discard `OC-FOP-0339`. Its components, linked write-off, sync-journal row, fixture rows, accounting rows, and expense rows are now absent. Do not reconstruct it.

### CRM backups and recovery history

The backup created immediately before the successful V2 CRM write is:

`1TOX7qnwydHjpHmhTUP1PtCGI_7B8CdUKoDksxrFPucI`

Preserve it. It is rollback evidence, not an instruction to restore.

An earlier unsafe migration attempt required recovery from:

`1ViDV4gn0pCmPLx1IZ00JqRjK78uAD4OPyfaKz6B9RX0`

That recovery eventually restored `Товари!A3:O220` and `РРЦ!A3:H220`, restored manual short names in `Товари!B52:B60` and `B71:B76`, and returned a clean CRM integrity result before V2. This history explains why broad restore logic must not be reused.

Never run `TemporaryCrmCatalogMigration.gs` or any old recovery function again. Never automatically restore a backup after a post-check failure. Diagnose the exact difference first.

### 3D-P workbook: migration has not been applied

The latest owner-run `catalogMigration3dpPreview` returned `ok=true`, `state=ready`, `target_count=72`.

Current old/test articles:

```text
ACC-3D-DITTO-410
BR-BULB-100
BR-CHARM-100
BR-MEW-100
BR-PIKA-100
FIG-123-500
FIG-LUFFY-410
FIG-LUFFY-500
```

Pre-apply business-state fingerprints:

| Sheet | Populated keys | SHA-256 |
|---|---:|---|
| `Друк-лог` | 12 | `731844c5959587f32d0f153f7743bfd4915f3def731a84c7e25a0043ef41853b` |
| `Продажі` | 0 | `6c01395053b558de9206e7d0bf49a18dac874964a9e34ca9ce2fbb2524b38bbb` |
| `Маркетингові_плюшки` | 4 | `88a569f95f14e8f3de7556c2ec34a9192d68fcd0f73c154a34961319f7c99a44` |
| `Виплати` | 1 | `df5e7d806aa99a009afae228fa5cd5281d93f21eb4b9bc9bd9aa76ab1e9d9b23` |
| `Наявність` | 6 | `e193a2d6d651cd2e0fd901009e6275fbd6f1ccd543b4c21780d2a8dc407d9a5f` |
| `_Чернетки_партій` | 9 | `a34a170d00144ce2013b87af99fec7bac4db9c3c8b6f59aa6e81e642ad81c02e` |
| `_Коригування_наявності` | 6 | `418089a95fd3824453edf9cec09d7177e40da268f9dd2d59a8fd13fe92324f0d` |
| `_Партії_FIFO_3DP` | 0 | n/a |
| `_Розподіл_FIFO_3DP` | 0 | n/a |

The migration preserves `_Аудит_API`, `_Журнал_налаштувань_3DP`, and `Аналітика`.

`catalogMigration3dpRehearsalV2()` exists in the local temporary wrapper and passed local syntax validation, but there is no owner-provided execution result. Do not claim it ran. Confirm that the current Apps Script editor contains the exact repository version before asking the owner to execute it.

### Expected temporary CRM integrity findings

Until the 3D-P catalogue is replaced, CRM correctly reports only these six old-catalogue RRP mismatches:

- `BR-CHARM-100`: CRM 30 vs 3D-P 25;
- `BR-BULB-100`: CRM 30 vs 3D-P 25;
- `BR-MEW-100`: CRM 30 vs 3D-P 2;
- `BR-PIKA-100`: CRM 40 vs 3D-P 25;
- `FIG-LUFFY-500`: CRM 70 vs 3D-P 75;
- `FIG-LUFFY-410`: CRM 300 vs 3D-P 222.

Coverage was `compared=7`, `skipped_missing_crm_rrp=6`, `deferred=null`. Any other problem code is a blocker.

## Fixed catalogue and costing decisions

- Exactly 72 products from the five product tabs; no consumables.
- Exactly 59 active and 13 inactive based on the source `can print` flag.
- Import inactive products, keeping unresolved RRP/buyout as null where applicable.
- Exclude source `Брелоки` rows 3 and 13, the two three-piece keychain sets.
- `FIG-NAMI-201` / Nami L: RRP 750 UAH, buyout 500 UAH.
- Opening stock is zero.
- Draft single/batch cost estimates are planning values only.
- Actual sale/gift cost is allocated immutably from actual manufactured batches in FIFO order.
- Serhiy-paid consumables are included in his manufactured-batch cost.
- Owner-paid consumables continue through the existing CRM consumables/write-off logic.

## Source identity warning

The source mirrors are current baselines plus local, unpublished catalogue/FIFO candidates:

- CRM: `crm/apps-script/Code.gs` and `crm/apps-script/SOURCE_STATE.md`.
- 3D-P: `3d-print/apps-script-3dp-api/Code.gs`, `CatalogFifo.gs`, and `SOURCE_STATE.md`.

Editing or pasting a bound Apps Script source does not publish a Web App. Owner-run editor functions prove bound-source behavior only. Verify source identity before changing a function, and record any later deployed Web App version separately.

The repository has many unrelated modified and untracked files. Inspect `git status --short` first, preserve owner changes, and stage only files assigned to this continuation.

## Required input from Claude

Read and validate Claude's `handoff_3DP-CATALOG-CANONICAL-DECISIONS_claude-to-codex_20260905.md` before editing migration data.

Reject the handoff as incomplete if it lacks any of these:

- exactly 72 source identities;
- exact live OpenCart `product_id`, `name`, `model`, and `sku` for every claimed live match;
- explicit resolution of all 20 previously unverified proposed articles;
- explicit resolution of `FIG-ZORO-410/400`, `FIG-PKBL-600/100`, and `BGC/BGS`;
- collision checks against the full live catalogue;
- explicit nulls and blockers where evidence is missing;
- 72/59/13 count reconciliation and the fixed Nami L prices.

If Claude reports `BLOCKED`, do not improvise a mapping. Ask only for the exact missing owner evidence listed there.

## Continuation sequence

1. Read `AGENTS.md`, both source-state files, this checkpoint, Claude's decision file, the import manifest, payload, FIFO contract, and current relevant diffs.
2. Parse Claude's machine-readable 72-row mapping and compare it by `source_tab + source_row` to `import-manifest.json`. Produce a bounded report of name/article changes and prove uniqueness.
3. Update the canonical migration payload and review artifact from that approved mapping. Preserve source names and provenance separately; do not overwrite the source record.
4. Build a narrow CRM correction operation keyed by the current CRM article/source identity. It may change only approved article/name/manual catalogue fields. Preserve formula columns, row placement, RRP history, statuses, and all unrelated CRM data.
5. Run the CRM correction against a rehearsal copy first. Prove live CRM unchanged, exactly 72 targets, formulas intact, 59/13 status counts, no duplicate articles, and unchanged sale/component/accounting counts.
6. Present the rehearsal output to the owner. A live CRM correction requires a fresh backup, owner-run apply, and a read-only post-check. Do not reuse the already-consumed full-catalogue Apply gate.
7. Regenerate the 3D-P migration payload and temporary wrapper from the same approved canonical mapping. Confirm the bound source matches the repository wrapper.
8. Run `catalogMigration3dpRehearsalV2()` on a copy. It must prove the live workbook is unchanged, exact 72-row catalogue output, expected preservation/cleanup, zero opening inventory, correct validation/formulas, and unchanged protected-sheet fingerprints.
9. Only after the rehearsal passes and the owner supplies its exact output, proceed through the wrapper's guarded backup/apply flow. The owner performs the live Apps Script execution.
10. Run read-only post-checks in 3D-P and CRM. The six temporary RRP mismatches must disappear, while inactive rows with null prices remain explicitly skipped rather than fabricated.
11. Verify both dashboards against the exact contracts: allowed status values, active/inactive behavior, article/name lookups, RRP/buyout fields, manufacture flow, consumable payer logic, sale/gift allocations, retries/reversals, and payout calculations.
12. Prove FIFO with a focused end-to-end scenario using actual manufactured batches. Allocation must be immutable, oldest available batch first, idempotent on retry, reversible without double allocation, and reconcilable to stock. Do not create opening batches from draft estimates.
13. Remove temporary live wrapper files only after final evidence is captured, then refresh both repository mirrors and source-state records in the same session. Web App publication remains an owner gate.

## Stop conditions

Stop before any write if:

- Claude's mapping is incomplete, unapproved, or has a collision;
- current live CRM/3D-P state differs from the recorded fingerprints or exact target sets;
- an article is already referenced by manufactured FIFO batches or immutable allocations under a different identity;
- a formula column would receive a literal;
- the current Apps Script source cannot be matched to the repository source;
- a rehearsal changes the live file or fails to delete its copy safely;
- a new CRM integrity problem appears;
- a dashboard sends a value outside the receiving sheet/API validation contract;
- a backup target is ambiguous.

Do not run the legacy CRM Apply, legacy recovery, or a broad range restore. Do not reconstruct `OC-FOP-0339`. Do not apply the 3D-P migration before the canonical mapping is approved.

## Repository references

- `plans/3dp-catalog-reset-20260902/import-manifest.json`
- `plans/3dp-catalog-reset-20260902/import-review.md`
- `plans/3dp-catalog-reset-20260902/migration-payload.json`
- `plans/3dp-catalog-reset-20260902/fifo-contract.md`
- `plans/3D-P_sku-naming-convention_20260807.md`
- `diagnostics/CRM-3DP_catalog-reset-intake_report_20260902.md`
- `scripts/3dp-catalog-reset/TemporaryCrmCatalogMigrationV2FullStandalone.gs`
- `scripts/3dp-catalog-reset/Temporary3dpCatalogMigration.gs`

`docs/CRM-3DP-catalog-migration-runbook.md` records an earlier workflow and its Apply ordering is stale after the recovery and V2 CRM execution. Use it only as historical context until it is rewritten from this checkpoint.

## Completion evidence

The migration is complete only when one final diagnostic records:

- approved canonical 72-row mapping and OpenCart collision evidence;
- before/after backups and exact guarded operations;
- CRM and 3D-P exact SKU/name/price/status reconciliation;
- clean CRM integrity with complete 3D-P comparison coverage;
- protected business-history fingerprints or explained, approved transformations;
- dashboard contract checks for both owners;
- manufactured-batch FIFO allocation, retry, reversal, and reconciliation evidence;
- published Web App versions or an explicit statement that publication remains pending;
- owner manual QA result.

## Suggested opening instruction for the new dialogue

> Continue the 3D catalogue migration using the attached Codex checkpoint and Claude canonical-decision handoff. First validate both files against the current repository and live read-only state. Do not perform a live write until the relevant rehearsal and backup gate pass. Preserve unrelated working-tree changes and do not guess missing OpenCart identities.
