# Codex Report — 3D-P-007-WP1c: existing 3D SKU CRM RRP synchronization

Date: 2026-08-23

## Scope

The owner reported that a 3D SKU was successfully created in 3D-P, Analytics,
and CRM, but a later manual CRM sync failed with `SKU already exists with
different CRM fields or RRP`. The cause was the dashboard always calling
`add_sku`, whose intentional duplicate guard rejects an existing SKU when its
RRP differs.

Implemented a separate, owner-dashboard API action,
`sync_3dp_catalog_rrp`, for an already-existing canonical 3D SKU. It changes
only the manual RRP cells in CRM `РРЦ` (E:G): value, Kyiv Spreadsheet timestamp,
and an audit note. It does not create a row and does not alter CRM `Товари`,
product title, buyout price, stock, or any formula.

The dashboard now first fetches the current CRM SKU state. If absent, it keeps
the existing owner-confirmed create path. If present, it confirms and calls the
new RRP-only action. The label and success text explicitly state that CRM title
is not synchronized.

The generic `update_rrp_batch` restriction for canonical 3D SKUs remains in
place. The 3D-P Apps Script requires no change or publication for this package.

## Fresh-source evidence

- The owner supplied a fresh main-CRM source export on 2026-08-23 16:16 Kyiv.
- Supplied source SHA-256:
  `2D8EE2F178EA7C58266D04F586FA45328600340DC2B0618454B14786CD3EAA08`.
- The export has no trustworthy source-version label; no deployed version was
  inferred. `crm/apps-script/SOURCE_STATE.md` records this state.

## Guardrails in the CRM action

- Requires a canonical 3D packaging SKU and existing rows in both `Товари` and
  `РРЦ`.
- Requires the CRM row to still be classified as 3D.
- Verifies `Товари!J` and `РРЦ!H` remain formulas and rejects formulas in the
  manual `РРЦ!F:G` columns.
- Uses the dashboard-read current RRP as `expected_rrp`; a concurrent CRM change
  fails closed and asks the owner to refresh rather than overwriting it.
- Verifies the saved RRP after write, restores `РРЦ!E:G` on an in-action
  verification failure, and invalidates the read cache on success.
- A repeat request for the already-current RRP returns `already_applied: true`
  without a write.

## Files delivered

```text
patches/3D-P-007-WP1c_crm-rrp-sync_20260823.js
  Complete replacement copy for the main CRM Apps Script Code.gs.
  SHA-256: 03CEABADCBA259015AB531296586F22B1C98CE271DE55E246731B2C16ACDDCB7

patches/3D-P-007-WP1c_crm-rrp-sync-dashboard_20260823.html
  Complete replacement copy for dashboard/booster-dashboard.html.
  SHA-256: FFAEFC39C1ED9F8C39FEC87CCD0F73A0998EA8E69A7908044A635FEA8E8A261F
```

Local mirror changes are limited to the main CRM source/test/source-state and
dashboard source/tests for this behavior. No commit, push, Apps Script
publication, or live Sheet write was performed.

## Local verification

```text
node crm/apps-script/tests/catalog-sku-create.test.mjs
CRM catalog SKU create tests passed

node crm/apps-script/tests/integrity-check.test.mjs
CRM integrity-check tests passed

node crm/apps-script/tests/3dp-sync-journal.test.mjs
3dp-sync-journal tests passed

node dashboard/tests/3dp-sync-journal-static.test.mjs
3dp-sync-journal dashboard static tests passed

node dashboard/tests/dashboard-contract.test.mjs
Dashboard syntax and contract tests passed
```

The catalog test covers: normal 3D SKU creation; RRP-only synchronization;
preservation of product data and dynamic formula; no-op repeat; stale-RRP
rejection; and rejection of a non-3D SKU. Static dashboard tests confirm the
new action, expected-RRP guard, and owner-facing wording are present.

## Deployment order (owner)

1. In the **main CRM** Apps Script project, make a versioned backup, replace
   `Code.gs` with `3D-P-007-WP1c_crm-rrp-sync_20260823.js`, save, and deploy a
   new Web App version using the existing deployment settings.
2. Replace the local/dashboard-served `booster-dashboard.html` with
   `3D-P-007-WP1c_crm-rrp-sync-dashboard_20260823.html` using the normal
   dashboard release path.

CRM first is safe: before the dashboard copy is released, the old button can
still fail safely through `add_sku`; it cannot call the new mutation early.
Dashboard first is not safe because it would call an action absent from the old
CRM deployment.

## Post-deploy QA checklist

- [ ] In dashboard CRM diagnostics, run the read-only CRM integrity check before
  testing; record its bounded result.
- [ ] Use an existing canonical 3D SKU that has a known CRM RRP. Change only
  its 3D-P actual RRP, save the 3D-P draft, then click `Синхронізувати РРЦ /
  додати CRM` and accept the old-to-new RRP confirmation.
- [ ] Confirm the dashboard reports `CRM РРЦ оновлено` and not a duplicate-SKU
  error.
- [ ] Confirm CRM `РРЦ` has the new RRP, timestamp, and source note; confirm
  `Товари` title and formula cells are unchanged.
- [ ] Re-run the read-only CRM integrity check. No newly reported problem code
  is acceptable.
- [ ] Repeat sync without changing RRP; expect the already-current/no-op message.

## Rollback

- Main CRM: redeploy the immediately preceding Apps Script version, or restore
  the owner backup made before replacing `Code.gs`.
- Dashboard: restore the immediately preceding `booster-dashboard.html` from
  the normal dashboard release backup/history.
- If a successful RRP change itself must be undone, the owner should set the
  prior manual RRP through the approved CRM owner workflow and retain the audit
  note; this package deliberately does not provide a bulk or silent rollback
  writer.

## Risks

- This is a CRM mutation once deployed. The stale-value guard narrows the risk
  to a confirmed current value, but it cannot replace owner QA against the
  actual Apps Script deployment.
- This package intentionally does not synchronize buyout price from 3D-P into
  CRM; that price is not part of the CRM product/RRP update contract.
