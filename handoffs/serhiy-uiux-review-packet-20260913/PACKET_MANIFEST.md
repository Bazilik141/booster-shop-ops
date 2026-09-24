# Serhiy UI/UX review packet manifest

Generated locally: 2026-09-13.

## Files

| File | SHA-256 | Purpose |
|---|---|---|
| `CURRENT_BUILD/Booster-3DP-Оновлення_20260913.exe` | `FA75E0E9AA9E9C34BFB08F5FD20C0AFB01F145F6F1F0743FA6347FFF8EFFF97F` | Existing current updater; no rebuild was performed. |
| `serhiy-local-server-source_20260913.zip` | `A8992E0676E80294F90A1DE05143A7050F7AECE3686C7A226F31CBFC59F2F5A5` | Current reviewable source snapshot. |

`SOURCE_SNAPSHOT/` contains the same reviewed source in extracted form. The ZIP contains 29 files and no `.env` entry. It excludes Node runtime, `node_modules`, distribution archives, and live credentials.

## Local evidence

- `node --check` passed for `server.mjs`, `public/app.js`, `public/information-tables.js`, and `tests/ui-fixture.mjs`.
- `npm test` passed: 22/22 tests.
- The first sandboxed test run was blocked by Windows `spawn EPERM`; the same unchanged test suite passed when rerun in the permitted local execution context. This is an execution-environment restriction, not a product test failure.

## Boundaries

This packet is a local code snapshot and an existing updater, not evidence of Apps Script publication, a live Sheet write, an installation, or production UI QA.
