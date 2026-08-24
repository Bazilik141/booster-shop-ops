# 3D-P Apps Script source state

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
