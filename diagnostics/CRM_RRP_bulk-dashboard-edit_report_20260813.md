# Codex Report — CRM: bulk RRP editing in the dashboard

Date: 2026-08-13

## Scope

Implemented the owner-requested batch editing of ordinary-product RRP from the CRM dashboard's `Товари` page. 3D products remain editable only from the 3D page.

## Files touched

```text
crm/apps-script/Code.gs                         — protected batch RRP API and SKU classification flag
dashboard/booster-dashboard.html                — rightmost `Нова РРЦ, грн` column and batch apply UI
crm/apps-script/tests/catalog-sku-create.test.mjs — local API coverage for the new route
dashboard/tests/dashboard-contract.test.mjs     — dashboard contract coverage
```

## Behaviour and safeguards

- The dashboard collects changed RRP values and sends one `update_rrp_batch` request only after owner confirmation.
- Each request includes the value seen when the list was loaded. The server rejects the whole batch if a target RRP changed in the meantime, so stale browser data cannot overwrite a newer value.
- The server writes only `РРЦ!E:G` (RRP, update date, audit note). It preflights `Товари!J` and `РРЦ!H` formula cells, and protects formula use in `РРЦ!F:G`. A one-off owner calculation in editable `РРЦ!E` is intentionally replaced by the requested fixed RRP.
- A failed verification restores the original `E:G` values for every planned row.
- 3D is blocked in both UI and API: by the established 3D SKU grammar and by `3D` in the product set or format. The UI shows `У вкладці 3D` instead of an input.
- A successful batch refreshes the product list and invokes the existing read-only CRM integrity check.

## Local verification

```text
node crm/apps-script/tests/catalog-sku-create.test.mjs
CRM catalog SKU create tests passed

node crm/apps-script/tests/integrity-check.test.mjs
CRM integrity-check tests passed

node crm/apps-script/tests/test-order-purge.test.mjs
{"ok":true,"order":"MAN-FOP-0006","surfaces":11,"marker_scan":true,"formula_preservation":true,"remote_gifts":true}

node dashboard/tests/dashboard-contract.test.mjs
Dashboard syntax and contract tests passed

git diff --check -- crm/apps-script/Code.gs dashboard/booster-dashboard.html
exit 0
```

## Deployment state

Not published or deployed. The owner previously reported CRM Apps Script version 115 as published before this change; this local implementation requires a new CRM Apps Script publication/version and the updated local dashboard file.

## Post-publish QA

- [ ] Open the local dashboard and refresh `Товари`.
- [ ] Change one ordinary SKU's RRP, apply it, and confirm the new RRP, date, and audit note in `РРЦ!E:G`.
- [ ] Confirm a 3D SKU shows `У вкладці 3D` and cannot be submitted by the CRM RRP route.
- [ ] Run the dashboard CRM integrity check and preserve the bounded output.
- [ ] Report the published CRM version and integrity result for the source-state record.

## Risks / rollback

The operation changes real RRP values after publication; the confirmation dialog and stale-value preflight reduce accidental writes but do not replace owner review of the selected numbers. To undo a completed batch, enter the previous values in the same UI and apply them as a new batch. No sheet structure or formula was changed by this implementation.
