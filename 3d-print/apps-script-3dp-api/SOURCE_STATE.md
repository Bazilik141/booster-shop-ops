# 3D-P Apps Script source state

Last source export: 2026-08-13 20:17 (Europe/Kyiv), Apps Script V23.

Evidence received: `Версія 23, 13 серп. 2026 р., 2017.txt` supplied by the
owner on 2026-08-16. Its normalized source content matches `Code.gs`; the raw
file differs only by line-ending representation.

This records the source baseline only. It is not proof that a later local edit
has been published as a Web App version.

## Publications after the V23 baseline

| Version | Date (Kyiv) | Contents | Evidence |
|---|---|---|---|
| V25 | 2026-08-22 17:35 | 3D-P-007 **WP1b** — Serhiy write rights on `Номенклатура` `Q`/`R`/`S` with validation and a shared change journal, Serhiy stock corrections with the actor recorded in the ledger, and `Виплати` two-way acknowledgement with Kyiv timestamp, role and append-only correction history. | Owner-reported publication. `preview3dpWp1bSchema()` then `setup3dpWp1bSchema()` executed 2026-08-22 17:44, completed without error; owner confirmed the two acknowledgement columns are present in the live `Виплати` sheet. Owner QA passed. |
| (WP1 rev 2) | 2026-08-16 | Role-based read projections limited to order/customer identity, `SERHIY_FULL_ECONOMICS_VISIBLE_3DP = true`, `Налаштування!B2:B5` grant with an append-only journal. | Owner QA passed; `integrity_check` clean, `elapsed_ms: 5891`. Live settings confirmed in the UI: power `0.11` kW, electricity `4.32` UAH/kWh, amortisation `12` UAH/h, planned defect `0.08`. |

⚠ **No source export has been supplied for V25.** `Code.gs` in this folder is the
local candidate that the owner pasted, so provenance is "the owner published these
exact bytes", not a fresh byte-for-byte export comparison. Request an export before
any task that plans against the live source.

⚠ **WP1b introduced a live schema migration**, unlike WP1. Rolling back the code
does not remove `Виплати` acknowledgement columns `G1:H1`. They are idempotent,
historical rows stay blank, and they must never be deleted.
