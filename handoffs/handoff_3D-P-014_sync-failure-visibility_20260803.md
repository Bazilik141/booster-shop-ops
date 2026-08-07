# Codex Handoff — 3D-P-014: make CRM→3D-P sync failures visible

Date: 2026-08-03 | Parent: 3D-P-000 · related: 3D-P-010, 3D-P-013, 3D-P-008
Codex config: model=Sol · effort=high
Priority: do this **before** any further 3D-P architecture work (owner decision
2026-08-03).

## Why

`3D-P-010`'s CRM hook is deliberately fail-open: a 3D-P outage, schema problem,
or rejected write must never block a real customer order. That part is correct
and stays.

What is wrong is that it fails **silently**. Every failure path calls
`crm3dpLogSkip_()` / `crm3dpLogWarning_()`, which write to `Logger.log` — a
surface the owner never sees and which is not retained usefully. On 2026-08-03
order `OC-FOP-0300` (SKU `FIG-CHARM-001`) did not appear in the 3D-P sheet and
the cause had to be inferred from Apps Script execution durations, because there
was no record anywhere of what the hook actually decided to do.

This is a design defect in the original `3D-P-010` handoff, not a Codex
implementation error.

## Scope

**1. Durable sync journal in the 3D-P workbook.**

New hidden/system tab `_Журнал_синхронізації` (same treatment as
`_Аудит_API`/`_Коригування_наявності`: append-only, never returned wholesale by
a generic read action). Suggested columns — confirm against
`diagnostics/3D-P_live-schema-audit_20260803.md` and existing system-tab
conventions before creating:

`timestamp_kyiv | source | order_id | crm_row | sku | outcome | detail`

- `source`: `apiAddSale_` or `apiUpdateSale_`.
- `outcome`: one of a small closed set — `created`, `updated`, `noop`,
  `skipped_no_3dp_sku`, `skipped_not_configured`, `skipped_schema`,
  `skipped_api_error`, `warning_negative_stock`, `error`.
- `detail`: short human-readable reason. **Never a token, URL with token, or
  customer personal data.**

Every branch of `sync3dpSales_` that currently returns a skip/warning, and every
successful create/update, must write exactly one journal row. A row is written
even when the hook succeeds, so an empty journal is itself diagnostic (it means
the hook never ran).

**2. New bounded read action** `3dp_sync_journal` (owner role only), returning
the most recent N rows (cap consistent with existing read caps). Read-only.

**3. Dashboard surface** in `dashboard/booster-dashboard.html`, 3D-друк tab,
Інформація zone: a compact "Синхронізація з CRM" panel showing the latest
journal entries, with non-`created`/`updated`/`noop` outcomes visually flagged.
Reuse the existing "Потребує уваги" pattern rather than inventing a new one.

**4. Fail-open is preserved exactly.** If the journal write itself fails, that
failure must also not block the CRM order — swallow it after attempting, same as
today. The journal must never become a new way to break order saving.

## What NOT to touch

- The fail-open contract itself — do not convert any skip into a thrown error
  that could reach `apiAddSale_`/`apiUpdateSale_`.
- Main CRM sheet data, packaging calculation, `getPackagingCost_()`, `doPost`.
- The price model — that is `3D-P-015`, deliberately separate.
- Any storefront, checkout, payment, SEO or Merchant surface.

## Acceptance criteria

- [ ] Every outcome branch of `sync3dpSales_` produces exactly one journal row,
      including successes — verified by test, not by inspection.
- [ ] No token, tokenised URL, or customer personal data can reach the journal
      or the dashboard panel — assert this in a test.
- [ ] A deliberate 3D-P outage produces a `skipped_api_error` row **and** the
      CRM order still saves normally.
- [ ] Journal write failure does not propagate to the CRM order flow.
- [ ] `3dp_sync_journal` rejects the Serhiy token.
- [ ] Dashboard panel renders the latest entries and visibly flags non-success
      outcomes.
- [ ] `ROADMAP_FLOW` entry for `3D-P-014` added.

## Owner QA

1. Save one order containing a 3D-P SKU → journal shows `created`.
2. Update that same order → journal shows `updated` or `noop`, no duplicate row.
3. Save an order with no 3D-P SKU → journal shows `skipped_no_3dp_sku`.
4. Temporarily break `BOOSTER_3DP_URL` → order still saves, journal shows
   `skipped_api_error`; restore the property afterwards.
5. Confirm the dashboard panel shows all of the above without a page reload
   workaround.

## Rollback

Journal tab and read action are additive; disabling the panel and the journal
writes returns behaviour to today's. No CRM data is touched.

## Risks

CRM risky zone — the hook sits inside live order creation/update. The single
most important invariant is unchanged: **nothing added here may block an order
save.** Any doubt on that point stops the task.

## Recommended status

`Not started` → `In progress` on pickup → `Done` once owner QA passes.
