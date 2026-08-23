# Codex Report — 3D-P-007 WP1c: owner quick-create completion

Date: 2026-08-23

## Outcome

The dashboard's `+ Новий SKU` owner route no longer bypasses the WP1c
draft/activation model. It now creates an active canonical SKU and its
`Аналітика` calculator row atomically before attempting the existing CRM
sync. A missing Analytics profit share can no longer leave this route in the
state observed for `BR-BULB-100` / `BR-MEW-100`.

The owner does **not** enter Serhiy's production cost when creating the SKU.
The action leaves `Номенклатура!G:J` empty and preserves formula column `K`.
The batch calculator fills G:J from an actual print batch; then K calculates
the production cost.

## Root cause

`dashboard/booster-dashboard.html` called generic
`3dp_append_row` directly for `Номенклатура`, wrote a canonical SKU and made
it active, then read it before the CRM call. The generic append action never
provisioned `Аналітика!A4:N17`, so `3dp_get_row` correctly threw:

```
Serhiy profit share is not configured in Analytics for SKU <SKU>.
```

The exception happened before the dashboard's CRM `try` block. Therefore the
3D-P row was left behind while automatic CRM creation was skipped, and the
manual `Перевірити / додати у CRM` button became the only recovery path.

The previous analytics hotfix covered only the separate
`Чернетка → owner assigns SKU` route. This package covers the older dashboard
owner quick-create route as well and makes generic `Номенклатура` append fail
closed.

## Files delivered

```
patches/3D-P-007-WP1c_owner-quick-create-3dp-api_20260823.js
    Complete replacement for the bound 3D-P Apps Script Code.gs.
    SHA-256: 820189181382906599D82FF03C0F44173B09873F1E9FD4305B1CC4AE77B99D34

patches/3D-P-007-WP1c_owner-quick-create-dashboard_20260823.html
    Complete replacement for dashboard/booster-dashboard.html.
    SHA-256: 8589CCC800F9556D1203C3D3ADFCB0F61495635637E677B68850BA2F04097F3B
```

The repository sources were updated in the matching locations, along with
their focused tests. No main-CRM Apps Script, `Продажі` columns, Sheet schema,
or Serhiy identity/read-projection boundary was changed.

## Behaviour after deployment

1. Serhiy still creates only a `Чернетка`; it remains non-sellable and does
   not consume an Analytics calculator row.
2. Owner assignment of a draft still provisions Analytics as in the preceding
   hotfix.
3. The owner dashboard shortcut uses new owner-only action
   `3dp_nomenclature_owner_create`. It validates canonical SKU/type and RRP,
   writes the active row and history, synchronizes the derived calculator
   block, gives a new SKU a 50% share, and records one audit event.
4. Only after that action succeeds does the dashboard call the pre-existing
   main CRM `add_sku` endpoint.
5. A genuine CRM failure remains an honest partial result: 3D-P and Analytics
   stay created, and the existing manual CRM retry button is available. This
   is unavoidable because the two systems cannot be rolled back as one
   transaction.

The generic `3dp_append_row` action now rejects `Номенклатура` with
`SPECIALIZED_ACTION_REQUIRED`. Thus a cached/old dashboard cannot create a
new broken active SKU after the API is deployed.

## Required owner deployment order

1. Make a named Google Sheets version/history checkpoint.
2. In the bound 3D-P Apps Script project, replace all of `Code.gs` with
   `3D-P-007-WP1c_owner-quick-create-3dp-api_20260823.js`, save, and deploy a
   new Web App version.
3. In the Apps Script function picker, run
   `repair3dpActiveNomenclatureAnalytics` once. It repairs already-active
   legacy rows such as `BR-BULB-100` and `BR-MEW-100` by rebuilding only the
   derived `Аналітика!A4:N17` calculator block and defaulting a missing share
   to 50%.
4. Bring the updated dashboard source into the normal dashboard release path:
   replace `dashboard/booster-dashboard.html` with the delivered complete
   file, review, then commit/push/deploy only under owner authority.

Deploy the 3D-P API before the dashboard. Between those two steps an old
dashboard fails safely instead of creating another inconsistent SKU.

## Owner QA

- [ ] The repair result is `ok: true`; `initialized_skus` lists any active
      legacy SKU that lacked a share.
- [ ] In `Аналітика`, `BR-BULB-100` and `BR-MEW-100` appear once each with
      `% прибутку Сергію = 50%`; the other calculator cells remain formulas.
- [ ] Reload each SKU in the dashboard: no missing-profit-share error.
- [ ] Create one normal new SKU from `Вироби`: the success path reports
      3D-P, Analytics, and CRM creation without pressing the retry button.
- [ ] Before a batch is entered, the new SKU shows cost as `—` and the UI
      says that cost comes from the calculator.
- [ ] Select it in `Калькулятор`, enter real batch data, and save. G:J receive
      inputs and formula K becomes the production cost; no manual K entry is
      required.
- [ ] Confirm a Serhiy draft remains absent from both active views and the
      Analytics calculator until owner SKU assignment.

## Local verification

```
node 3d-print/apps-script-3dp-api/tests/role-read-projections.test.mjs
PASS — owner quick-create route, owner-only guard, SKU/type validation,
       fail-closed generic append, atomic Analytics provisioning, no G:K
       production-cost write, and rollback on Analytics schema failure.

node dashboard/tests/3dp-sync-journal-static.test.mjs
PASS — dashboard calls the owner action, contains no direct Nomenclature
       generic append, and explains calculator-derived cost.

node dashboard/tests/dashboard-contract.test.mjs
PASS — inline dashboard JavaScript syntax and existing dashboard contract.

new Function(Code.gs)
PASS — Apps Script source syntax accepted.

git diff --check -- <scoped files>
PASS
```

## Source-state caveat

`3d-print/apps-script-3dp-api/SOURCE_STATE.md` records the last owner export
as V25 (2026-08-22 17:35), while the reported production failure was observed
after V26. The exact failing error and dashboard bypass were reproduced from
the local source; the delivered complete `Code.gs` includes the preceding
analytics-provisioning hotfix and this owner-route fix. It is a replacement,
not a sequential patch to apply after an unknown live edit.

## Authority record

No commit, push, publication, deployment, or live Sheet write was performed
by Codex.
