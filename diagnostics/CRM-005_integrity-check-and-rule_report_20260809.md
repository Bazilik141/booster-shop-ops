# Codex Report — CRM-005: bounded main-CRM integrity check

Date: 2026-08-09

## Scope

Implemented the non-cacheable, read-only `integrity_check` Apps Script action and its governance
rule. The dashboard UI is documented separately in
`diagnostics/CRM-005_compact-integrity-tile_report_20260809.md`.

## Behaviour and returned fixes

The action returns at most ten objects per code for:

- `price_without_sku`
- `missing_master_row`
- `master_row_inactive`
- `active_sku_without_rrp`
- `formula_column_literal`
- `duplicate_sku`
- `rrp_mismatch_3dp` when CRM and 3D-P both have a SKU-keyed price

Transport success and the integrity verdict are separate: every completed check now returns
`ok: true`; `clean` is `true` only when `problems` is empty. Genuine request failures retain the
existing `{ ok: false, error: ... }` contract, so the generic dashboard caller still exposes them.

`elapsed_ms` now measures the entire server-side check and is returned on every response. The first
live invocation must retain this value as the runtime baseline; no batching was introduced in this
delivery.

`OPS-CRMINTEGRITY` now explicitly covers `Майстер_Товарів` as well as `Товари`, `РРЦ`, and
`Розхідники`. The 3D SKU regex includes a one-line link to the canonical naming-convention plan.

The existing `Booster CRM` spreadsheet menu is deliberately unchanged. Its items run operational
writes, while integrity is a longer read-only diagnostic whose bounded result belongs in the
dashboard tile; adding a menu item would duplicate the control without making its output clearer.

## RRP coverage and corrected QA

The checker does not guess a relation between an unkeyed manual `РРЦ` value and a 3D-P SKU. Rows
`71-75` therefore report `price_without_sku` (and active products can report
`active_sku_without_rrp`) rather than a false `rrp_mismatch_3dp`.

Do not use this deployment to repair those rows. In a separately authorised repair, once
`РРЦ!A75` receives a genuine formula-derived SKU key, a local CRM price `100` and 3D-P price `90`
will produce `rrp_mismatch_3dp`. Correcting the keyed CRM price to `100` then clears that mismatch
on the next check.

## Local verification

`crm/apps-script/tests/integrity-check.test.mjs` passed with independent single-defect cases for all
seven codes, including mocked authenticated 3D-P mismatch, skipped missing CRM RRP, and deferred
remote failure. It passes actual clean and dirty API results through the extracted dashboard
`call()` transport, and proves that a genuine `{ ok:false, error:'bad token' }` still throws. It
also asserts that `elapsed_ms` is non-negative. The Apps Script mirror passed syntax validation
through Node stdin.

No Apps Script version, Web App deployment, live response, or live workbook action has occurred.

## Owner deployment and QA

1. Create a named Apps Script version before publishing the reviewed `Code.gs` as a new Web App
   version. Refresh the mirror and `SOURCE_STATE.md` in the same session.
2. Upload the coupled dashboard file once and hard-refresh.
3. From the compact dashboard tile, run the check and retain its bounded output and `elapsed_ms` as
   the pre-change baseline. It should show the current unkeyed `РРЦ` rows `71-75`.
4. After a covered structural or SKU change, run the check before and after; a new problem code is a
   defect of that change, not accepted noise.

## Rollback

Restore the named Apps Script version to remove the API action. The dashboard rollback is coupled:
reverting `dashboard/booster-dashboard.html` also reverts 3D-P-025 WP1/WP2 in this delivery. The
integrity action itself makes no workbook writes, so no data rollback is needed.

## Correction — 2026-08-09 (supersedes the earlier RRP-spill wording)

The earlier report described the manual values in `РРЦ` rows `71:75` as the spill blocker. Bounded
live evidence in `diagnostics/CRM-006_bounded-live-diagnosis_report_20260809.md` proved the exact
blocker is the four literals in `РРЦ!A76:D76`; `71:75` contain the unkeyed RRP/date/note symptom.
The CRM-006 repair must clear contents in `A76:D76` only, allowing the existing ARRAYFORMULA seeds
to repopulate rows `71:76`.
