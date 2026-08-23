# 3D-P Apps Script source state

Last source export: 2026-08-22 17:35 (Europe/Kyiv), Apps Script V25.

Evidence received: `Версія 25, 22 серп. 2026 р., 1735.txt` supplied by the
owner on 2026-08-22 as the current live bound source. The WP1c 3D-P candidate
is compared directly against it; its only differences are the scoped WP1c
draft/status/validator changes. The prior V23 export remains historical
evidence.

This records the source baseline only. It is not proof that a later local edit
has been published as a Web App version.

## Publications recorded before the V25 baseline

| Version | Date (Kyiv) | Contents | Evidence |
|---|---|---|---|
| V25 | 2026-08-22 17:35 | 3D-P-007 **WP1b** — Serhiy write rights on `Номенклатура` `Q`/`R`/`S` with validation and a shared change journal, Serhiy stock corrections with the actor recorded in the ledger, and `Виплати` two-way acknowledgement with Kyiv timestamp, role and append-only correction history. | Owner-reported publication. `preview3dpWp1bSchema()` then `setup3dpWp1bSchema()` executed 2026-08-22 17:44, completed without error; owner confirmed the two acknowledgement columns are present in the live `Виплати` sheet. Owner QA passed. |
| (WP1 rev 2) | 2026-08-16 | Role-based read projections limited to order/customer identity, `SERHIY_FULL_ECONOMICS_VISIBLE_3DP = true`, `Налаштування!B2:B5` grant with an append-only journal. | Owner QA passed; `integrity_check` clean, `elapsed_ms: 5891`. Live settings confirmed in the UI: power `0.11` kW, electricity `4.32` UAH/kWh, amortisation `12` UAH/h, planned defect `0.08`. |

`Code.gs` in this folder is now the local WP1c candidate, not the live V25
baseline. The V25 export above is the preserved source evidence for a future
comparison or rollback.

⚠ **WP1b introduced a live schema migration**, unlike WP1. Rolling back the code
does not remove `Виплати` acknowledgement columns `G1:H1`. They are idempotent,
historical rows stay blank, and they must never be deleted.
