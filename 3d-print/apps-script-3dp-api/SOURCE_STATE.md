# 3D-P Apps Script source state

## CRM-015 local candidate — NOT PUBLISHED (2026-09-22)

The owner supplied the complete published V34 export
`Версія 34, 16 вер. 2026 р., 1726.txt` (3,787 split lines; normalized SHA-256
`ca72e8c6f09489fac47beb99a74161e3997e6897a4615a59746d8eaa165e11f6`).
Before CRM-015, the repository mirror contained an already-present local
batch-draft quantity-keying follow-up. An unrelated local change that bypassed
required sale fields when the order-line schema was absent has been reverted to
the V34 behavior and covered by a focused regression test. CRM-015 preserves
the quantity-keying follow-up; V34 therefore
is an evidence baseline, not a byte-identical mirror claim.

The local candidate adds an atomic manufactured-batch FIFO marketing writeoff,
row-local deterministic formulas for `Продажі!C/I/J/K/L/S`, owner-only
fingerprint-gated formula repair, reconciliation/reversal support for the new
allocation source, and the temporary `CRM-015.html` preview/apply tool for the
three affected rows. The repair action now requires each target's exact date,
SKU and order number; missing or duplicate matches block application. None of
this is published. The repair route/action and
temporary HTML are task-scoped: after successful owner QA both must be removed
from the bound project and local mirror, then the clean 3D-P source republished.

For owner paste, use `work/CRM-015_3dp_Code_from_V34.gs`: it is V34 plus only
the CRM-015 change (113 added / 1 removed line). The mirror `Code.gs` also
contains the preserved, unpublished batch-draft quantity-keying change and is
not the deployment input for this task.

After the owner verifies the three-row formula repair, replace bound `Code.gs`
with `work/CRM-015_3dp_Code_final.gs` and republish the existing deployment.
That final candidate is identical to the temporary candidate except that the
repair route and its two task-only helpers have been removed.

## Deployed — V34 (2026-09-16 17:26, owner-reported)

Owner statement 2026-09-21: `Версія 34, 16 вер. 2026 р., 17:26` is published and
carries the 3D-P-027 revision 9 SKU grammar correctly. The section below, which
described that grammar as a pending local candidate, is therefore **superseded**
and kept only as history.

Two caveats, recorded rather than smoothed over:

- V33 and V34 were published without a source-state entry at the time, so what
  else travelled with them is not recorded here. The repository `Code.gs` was
  last written 2026-09-16 09:18, eight hours before that publication, and cannot
  by itself prove the deployed content.
- This caveat predates the complete V34 export supplied for CRM-015 above. The
  export verifies the source text; published deployment identity remains
  owner-reported until live Web App QA.

## Superseded — local candidate addition, 3D-P-027 (2026-09-16)

The repository Code.gs now accepts the owner-approved revision 9 SKU grammar:
the existing BR|FIG|ACC-3D base plus zero or more uppercase/digit suffix
segments of one to five characters. The same validation remains in the owner
dashboard; both copies use the identical expression and are covered by the
same accept/reject matrix. This local mirror has not been pasted into the bound
3D-P Apps Script project or published as a Web App version. The pre-existing
local V32 candidate changes below remain separate and are preserved.

## Local candidate after V32 — pending publication (2026-09-06)

The repository `Code.gs` is now ahead of the owner-reported V32 deployment by
one bounded-read fix. The owner `3dp_bootstrap` keeps its audited
`Аналітика_SKU!A1:N100` maximum but reads that fixed internal projection
directly, instead of routing it through the 500-cell guard for caller-controlled
`3dp_get_range`. The external range guard remains unchanged and a focused test
proves that `A1:N40` still returns `RANGE_TOO_LARGE` through the public action
while the owner bootstrap succeeds. This candidate is local only; the live Web
App will keep returning the reported error until the owner publishes a new
Apps Script version.

## Deployed catalogue/FIFO source — V32 (2026-09-06)

The owner copied the repository `Code.gs` and `CatalogFifo.gs` into the bound
3D-P project and published `3D-P API — FIFO reversal, reconciliation and repair`
as V32 at 20:17 Kyiv. The deployed source adds `Аналітика_SKU`, immutable actual
manufactured-batch FIFO, atomic CRM sale/gift allocation, activity/price guards,
the approved `ACC-3D-8__` category, and guarded one-time migration support. The
V31 export below remains the last byte-verified owner-supplied source identity;
V32 publication identity is owner-reported runtime/deployment evidence.

Local update 2026-09-06: after the owner approved append-only negative return
records, the candidate adds owner-only `3dp_fifo_reverse`. It restores the exact
original batch quantities and frozen cost, appends a negative sale/gift row,
replays the same operation idempotently, and blocks a second reversal. Focused
tests pass 9/9. Owner-only read action `3dp_fifo_reconcile` detects allocation,
batch-counter and business-projection drift. Guarded `3dp_fifo_repair` requires
its fresh fingerprint, changes only repairable batch counters, rechecks the full
ledger, rolls back on failure, and refuses to invent a missing business row.
The CRM source routes `Скасовано`/`Повернення` to reversal and prevents an old
reversed operation from becoming active again; 3D-P independently rejects replay
of a reversed allocation. The owner-run copy rehearsal passed at 19:47 Kyiv:
`live_unchanged=true`, two oldest-first sale layers, idempotent sale and reversal
replay, blocked old-id reactivation, one deliberately damaged batch counter
repaired, final reconciliation clean, and the successful copy trashed. At 20:59
CRM called deployed V32 `3dp_fifo_reconcile` and received `clean=true`, zero
problems, zero batches and zero allocations. The first real manufactured batch
and sale remain bounded operational QA.

`scripts/3dp-catalog-reset/Temporary3dpFifoRehearsal.gs` remains in the repository
as the reusable reviewed copy-rehearsal source; it was removed from the bound
project after the successful run.

## Current source identity — verified 2026-09-02

The owner's fresh `Версія 31, 24 серп. 2026 р., 1531.txt` export is identical to
`Code.gs` after UTF-8 BOM removal and CRLF/LF normalization (3,748 lines;
SHA-256 `fdf22bfb2b6c659f6e727be85447049826cb5355278c4adc61cb417c633bedf9`).
The current mirror baseline is V31, superseding the V29 identity and historical
consumer-divergence notes below. No code was changed or published during this
verification. A bounded authenticated `3dp_skus` / `3dp_get_range` read succeeded;
this does not independently establish the published deployment version.

## Historical source record (V29)

Last source export: 2026-08-23 16:00 (Europe/Kyiv), Apps Script V29.

Evidence received: `Версія 29, 23 серп. 2026 р., 1600.txt` supplied by the owner
on 2026-08-23 as the current live bound source.

**The repository mirror equals this export.** `Code.gs` in this folder and the
V29 export are byte-identical after LF normalisation (MD5
`d2f8256c5e21acf14ec442cf4533fff4`, 3718 lines, verified 2026-08-23). There is no
local candidate ahead of the published version at the time of this record.

Re-confirmed 2026-08-24 against a second independent export of the same version.
That copy carries one extra trailing blank line — a select-all artefact of the
Apps Script editor, not a code change; every other line matches. When comparing,
normalise line endings and ignore a lone trailing newline.

This records the source baseline only. It is not proof that a later local edit
has been published as a Web App version. Re-verify before planning against live
source.

## Publications recorded

| Version | Date (Kyiv) | Contents | Evidence |
|---|---|---|---|
| V29 | 2026-08-23 16:00 | 3D-P-007 **WP1c** — `Чернетка` status as a third value of the nomenclature status column, `DRAFT-` draft identifiers, owner-only canonical article assignment with the strict `^(BR\|FIG\|ACC-3D)-[A-Z0-9]{2,5}-\d{3}$` validator, plus the three same-day follow-ups: atomic owner quick-create with an Analytics row at a 50% share, `SPECIALIZED_ACTION_REQUIRED` on generic `Номенклатура` append, and a bounded catalogue RRP sync action. | Owner-supplied labelled export, byte-identical to the repository mirror. Owner QA passed 2026-08-23. |
| V25 | 2026-08-22 17:35 | 3D-P-007 **WP1b** — Serhiy write rights on `Номенклатура` `Q`/`R`/`S` with validation and a shared change journal, Serhiy stock corrections with the actor recorded in the ledger, and `Виплати` two-way acknowledgement with Kyiv timestamp, role and append-only correction history. | Owner-reported publication. `preview3dpWp1bSchema()` then `setup3dpWp1bSchema()` executed 2026-08-22 17:44, completed without error; owner confirmed the two acknowledgement columns are present in the live `Виплати` sheet. Owner QA passed. Export: `Версія 25, 22 серп. 2026 р., 1735.txt`. |
| (WP1 rev 2) | 2026-08-16 | Role-based read projections limited to order/customer identity, `SERHIY_FULL_ECONOMICS_VISIBLE_3DP = true`, `Налаштування!B2:B5` grant with an append-only journal. | Owner QA passed; `integrity_check` clean, `elapsed_ms: 5891`. Live settings confirmed in the UI: power `0.11` kW, electricity `4.32` UAH/kWh, amortisation `12` UAH/h, planned defect `0.08`. |

⚠ **WP1b introduced a live schema migration**, unlike WP1. Rolling back the code
does not remove `Виплати` acknowledgement columns `G1:H1`. They are idempotent,
historical rows stay blank, and they must never be deleted.

## Known divergence outside this folder

`3d-print/serhiy-local-server/` still speaks the pre-projection contract and has
two calls that fail under the Serhiy token against V29 (`Легенда!A32:A38` and
`Налаштування!A1:C4`). Recorded here because the local server is the only other
consumer of this API. Scoped in
`handoffs/handoff_3D-P-007-WP2_serhiy-local-server-respec_20260823.md`.
