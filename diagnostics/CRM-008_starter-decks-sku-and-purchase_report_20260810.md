# Codex Report — CRM-008: starter-deck SKU and purchase preflight

Date: 2026-08-10

## Status

**Blocked before the first CRM write.** This report records the live preflight only.

## Scope

The handoff requires five `Starter Deck` SKUs, two settings-list additions,
five RRP values, and two three-line purchases for order `yskh293`.

Implemented: **none**. No live spreadsheet cell, Apps Script source, Notion
property, dashboard source, or purchase row was written.

The owner supplied the rollback artefact before the preflight:
`10 серпня, 15:01 До 008`.

## Live preflight evidence

- Main CRM: `Booster Shop CRM — облік товарів`
  (`1PvlSlg3UoPw8Fbj98lHL-VGLB0HP8hgKUxsXPW1GkRg`), Kyiv timezone.
- `Курси!B5` is `3.5000` for JPY, matching the handoff calculation.
- No target SKU exists in `Товари`; `yskh293` has no existing row in `Закупки`.
- The next intended product rows are `Товари!77:81`.
- `Налаштування` has neither `Starter Deck` nor `ST-32`…`ST-36`.
  The live dropdown sources are currently `Налаштування!J4:J14` (formats)
  and `Налаштування!AD4:AD39` (sets), so the authorized CRM-008 settings
  work would also require a bounded validation-range extension.
- `Товари!J77:J81` each retain the normal price formula.
- **`Товари!B77:B81` have no formula** (`userEnteredValue` is empty on every
  target row). Row 70 is also empty in column B; rows 71–76 are the known
  literal block owned by CRM-006 pass 4.

## Stop rule applied

CRM-008 WP1 explicitly forbids repairing `Товари!B` or `Товари!J`. It also
requires stopping if either formula does not reach the new rows. Writing the
five SKUs now would create five new missing short names and a new integrity
defect. Therefore the settings, SKU, RRP, master-catalogue verification, and
both purchases were not started.

The CRM-007 report's 2026-08-10 integrity output is useful historical context,
but it is not substituted for CRM-008's own required before/after dashboard
integrity runs.

## Files touched

```
diagnostics/CRM-008_starter-decks-sku-and-purchase_report_20260810.md — this preflight blocker report
```

## Rollback

No live mutation occurred; rollback is not needed. The owner-provided workbook
copy remains reserved for the resumed CRM-008 write pass.

## Required owner decision

Authorize or complete the separately scoped **CRM-006 pass 4** repair for the
missing `Товари!B` formula coverage. Do not fold that repair into CRM-008.
After CRM-006 provides its own integrity evidence, re-run the CRM-008
preflight, including a fresh CRM-008 dashboard integrity baseline, before any
SKU or purchase write.

## Side effects / risks

- No data changed.
- Resuming CRM-008 without formula coverage would violate OPS-CRMINTEGRITY and
  the task handoff's explicit stop condition.
- No Notion or `ROADMAP_FLOW` status was changed.
