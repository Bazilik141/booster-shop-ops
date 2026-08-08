# Codex Report — 3D-P-010 WP4: `updateSaleStatus()` hook

Date: 2026-08-08

## Scope

Implemented exactly one CRM call site: the in-Sheet `Оновити_продаж` path now calls the existing
`sync3dpPackagingCost_(sales, order, rows, 'updateSaleStatus')` wrapper after CRM writes and cache
invalidation, before the form clears and its existing success alert appears. The fourth argument
labels the 3D-P-014 journal source; it does not alter sync semantics. No sync helper, matching, stock, fixture,
price, schema, 3D-P workbook, or dashboard code was changed.

## Files touched

```
crm/apps-script/Code.gs
crm/apps-script/SOURCE_STATE.md
tests/3d-p-010-crm-packaging-pull.test.mjs
patches/3D-P-010-WP4_updatesalestatus-hook_20260808.js
diagnostics/3D-P-010-WP4_updatesalestatus-hook_report_20260808.md
```

## Local validation

```text
CRM syntax via Node stdin: passed
tests/3d-p-010-crm-packaging-pull.test.mjs: 14/14 passed
crm/apps-script/tests/3dp-sync-journal.test.mjs: passed
dashboard/tests/3dp-sync-journal-static.test.mjs: passed
git diff --check: passed
```

The new menu-path tests prove: final CRM packaging writes precede the sync; a canonical
`ACC-3D-DITTO-410` creates one 3D-P sale; an existing row is reused; an order with no 3D SKU makes
no HTTP call; a 3D-P outage does not block the CRM write or alert; and a dashboard sync followed by
a menu update makes no duplicate sale row or stock decrement.

## Deployment status

Prepared locally only. `crm/apps-script/SOURCE_STATE.md` records V92 as the current live baseline
and this WP4 call as pending. The owner must paste the one-line block, publish a new CRM Web App
version, and re-export the deployed source; the executor did not deploy or write to either Sheet.

## Rollback

Restore this exact line in `updateSaleStatus()`:

```javascript
invalidateDoGetCache_(); clearSaleUpdateForm();
```

Then publish a new Web App version. No migration or cleanup is required.

## Owner QA

- [ ] Create named versions of the CRM and 3D-P workbooks.
- [ ] Apply [3D-P-010-WP4_updatesalestatus-hook_20260808.js](../patches/3D-P-010-WP4_updatesalestatus-hook_20260808.js) and publish a new CRM Web App version.
- [ ] On the first `Оновити_продаж` run, accept any Google external-request authorization prompt.
- [ ] Update an order with a CRM-and-3D-P-valid SKU; verify one `Продажі` row, one reason-keyed stock ledger entry, and one 3D-P-014 journal result.
- [ ] Repeat unchanged: the form must stop at its existing “Нічого не змінено” guard.
- [ ] Change only packaging: verify `Продажі!G` changes with no second stock decrement.
- [ ] Re-export the deployed CRM source and record the new Web App version.
