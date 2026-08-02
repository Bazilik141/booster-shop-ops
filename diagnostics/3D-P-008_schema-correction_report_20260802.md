# Codex Report — 3D-P-008 schema correction

Date: 2026-08-02

## Outcome

Prepared the local Apps Script addendum for the owner-confirmed final cost model.
It is **not deployed** and did not read or write the live Sheet in this run.

## Implemented source changes

- Removes the legacy `Номенклатура!O` combined-amortization column with an exact-header anchor check.
- Clears the legacy `H:J` plastic, material-weight, and price-per-kg values during that owner-approved migration; no conversion is attempted.
- Renames `G:J` to per-unit print time/product weight/spool weight/spool price.
- Creates visible, blue editable settings in `Налаштування!B2:B4`: `0.17`, `4.32`, `12`.
- Rewrites `K` as material-from-spool + electricity + amortization + fixture price, with blank output while required inputs are missing.
- Renames `Друк-лог!E` to `Брак, шт`; it remains writable through the existing optimistic-lock/history/audit mechanism.
- Creates `Фурнітура_довідник` and adds bounded `3dp_fixtures` reads for calculator dropdowns.
- Removes `O` from both owner and Serhiy write whitelists; fixture assignment remains independently writable in `N`.

## Local evidence

- `Code.gs` syntax: PASS via temporary `.js` copy.
- `tests/api.test.mjs` syntax: PASS.
- `tests/api.test.mjs`: PASS — schema migration/idempotency, retained owner-edited setting, fixture endpoint, formula/non-whitelist/stale guards, defect history, archive/restore, audit.
- `tests/live-positive-audit-smoke.ps1` parser: PASS; it now performs a guarded same-value fixture write, never targets removed `O`.
- `git diff --check`: pending final QA after all documentation changes.

## Owner deployment gate

1. In the existing bound 3D-P Apps Script project, replace `Code.gs`.
2. Run `preview3dpApiSetup()` and verify the planned schema changes.
3. Create a named Google Sheets version; then run `setup3dpApi()` once.
4. Publish a new version of the existing Web App; do not change Script Properties or expose tokens.
5. Run the negative and updated positive smoke tests, then spot-check headers, `Налаштування!B2:B4`, `K`, `Друк-лог!E`, and `_Аудит_API`.

## Remaining limitations

Local mocks are not Google Apps Script runtime proof. The reconciliation diff remains a separate owner-approval gate. `3D-P-006` and `3D-P-007` remain blocked until this deployment and live QA pass.