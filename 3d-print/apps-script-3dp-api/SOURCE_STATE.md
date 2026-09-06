# 3D-P Apps Script source state

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
