# Codex Report — 3D-P-023: journal Kyiv-time display

Date: 2026-08-08

## Scope

Fixed only the API representation of the CRM 3D-P sync journal timestamp. The underlying
`_Журнал_3DP_синхронізації` value remains a Google Sheets date, preserving native search, sorting,
and value-based filtering. The dashboard already renders `timestamp_kyiv` verbatim, so it needs no
production code change.

## Root cause and fix

The journal write used a Kyiv-formatted text value, but Sheets recognized it as a date. `getValues()`
then returned a Date, whose JSON serialization became UTC ISO. `apiSyncJournal_` now detects only
date values and calls `Utilities.formatDate(..., 'Europe/Kyiv', 'yyyy-MM-dd HH:mm:ss')` before JSON
output. Existing non-date text values remain unchanged.

## Files touched

```
crm/apps-script/Code.gs
crm/apps-script/SOURCE_STATE.md
crm/apps-script/tests/3dp-sync-journal.test.mjs
dashboard/tests/3dp-sync-journal-static.test.mjs
patches/3D-P-023_journal-kyiv-time_20260808.js
diagnostics/3D-P-023_journal-kyiv-time_report_20260808.md
```

## Local validation

```text
CRM syntax via Node stdin: passed
crm/apps-script/tests/3dp-sync-journal.test.mjs: passed
dashboard/tests/3dp-sync-journal-static.test.mjs: passed
tests/3d-p-010-crm-packaging-pull.test.mjs: 14/14 passed
git diff --check: passed
```

The journal test stores a date equivalent to `2026-08-08T12:47:22.000Z` and proves the API returns
`2026-08-08 15:47:22` for the dashboard.

## Deployment status

Prepared locally only. Apply the independent [3D-P-023_journal-kyiv-time_20260808.js](../patches/3D-P-023_journal-kyiv-time_20260808.js) block to the main CRM Apps Script, publish a new Web App
version, then export the deployed source and update `crm/apps-script/SOURCE_STATE.md`.

## Rollback

Restore `timestamp_kyiv: row[0],`, remove `crm3dpJournalTimestampKyiv_`, and publish a new Web App
version. No data rollback is required.

## Owner QA

- [ ] Create a named CRM Apps Script version before publishing.
- [ ] Apply the paste block and publish a new Web App version.
- [ ] Refresh `3D-друк → Інформація → Синхронізація з CRM`.
- [ ] Confirm a known journal record shows the same wall-clock Kyiv time as its Sheet cell.
- [ ] Confirm sorting/searching the underlying timestamp column in Sheets still works.
- [ ] Re-export the deployed CRM source and record the new Web App version.
