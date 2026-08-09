# Codex Report — CRM-005: compact integrity tile

Date: 2026-08-09

## Scope

Returned fix **WP3** in `dashboard/booster-dashboard.html`: the full-width integrity section is
replaced by one keyboard-accessible KPI tile on Огляд. No Apps Script route or response contract was
changed by this UI work.

## Behaviour

- Idle tile: `Перевірити` and `read-only · без вивантаження таблиць`.
- The first request happens only on tile click (or Enter/Space); re-entry is blocked while running.
- A clean result is green; a problem result shows its count and reveals the escaped detail table
  immediately below the KPI row; an error stays in the tile and hides the detail area.
- `elapsed_ms` is surfaced in the clean/problem tile subtext with the local `HH:MM` result time.
- Last result is module state, so dashboard data refresh recreates one tile without hiding a known
  red result. A fresh browser load returns to idle, as intended.
- The tile is rendered after the summary-card path, not inside the `monthly_summary` path, so that
  request cannot remove the control.

## Local verification

`tests/crm-005-integrity-tile.test.mjs` passed: no page-load call, clean/error/problem states,
elapsed time, hidden/revealed detail area, and no duplicate or reset after a tile refresh.

No browser session or deployed Web App was exercised.

## Rollback

The dashboard upload is coupled with 3D-P-025 WP1/WP2. Reverting this one file and hard-refreshing
removes both features together; it does not change CRM or 3D-P workbook data.
