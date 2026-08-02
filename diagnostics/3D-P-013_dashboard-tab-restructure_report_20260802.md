# Codex Report — 3D-P-013 dashboard tab restructure

Date: 2026-08-02

## Outcome

`dashboard/booster-dashboard.html` now keeps one sidebar item, **3D-друк**,
but splits its work surface into three internal zones:

- **Калькулятор** — owner API access/settings, per-SKU raw batch drafts and
  guarded per-unit calculation save;
- **Вироби** — creation/editing of the confirmed SKU fields, archive/restore,
  and reasoned stock adjustment with latest ledger entries;
- **Інформація** — SKU table with search, filters, grouping, sorting and local
  column visibility, attention list, 3D-only analytics, plus sales, payouts and
  marketing journal tables.

The dashboard remains an API client only. It neither reads a Sheet directly nor
uses the main CRM token.

## Confirmed local implementation

- The calculator calls `3dp_batch_draft`/`3dp_batch_draft_save` before its
  guarded `Номенклатура!G:J` writes and performs a fresh `3dp_get_row`.
- Settings writes target only existing owner-only `Налаштування!B2:B4` controls.
- Archive/restore and stock correction use the deployed Addendum #2 dedicated
  actions; stock reads the bounded ledger after an adjustment.
- The information table includes active and archived SKU rows, the confirmed
  margin classification grid, a recommended-RRP **pending** column, and
  owner-local column preferences.
- The 3D-order percentage remains a deliberate placeholder pending the shared
  detection contract in 3D-P-010.

## Hard boundary: no invented durable SKU fields

The verified local API fixture defines `Номенклатура` through its existing
cost/fixture fields and has no durable model-link, manual RRP, or Track-2
buyout-price column. The current live API surface also exposes no named action
for those data. Consequently, this change does **not** write a model link,
manual RRP, or buyout price into a guessed `Номенклатура` column or a journal.
The UI may show read-only, source-derived reference values, but a durable CRUD
path needs an owner-approved Sheet/API destination first.

Recommended-RRP generation was expressly out of scope pending a confirmed
target-margin/interested-sales formula and is visibly marked pending.

## Local verification

Passed:

```text
Dashboard inline JavaScript parsed with new Function(...): PASS
git diff --check: PASS
```

The repository's in-app browser refused the local `file://` dashboard URL by
its security policy. No workaround was attempted. Therefore this is not a
visual runtime or live-API claim.

## Owner QA / live gate

1. Open the dashboard from its normal local HTTP route; enter only the separate
   3D-P `/exec` URL and owner token in dashboard-local storage.
2. Check calculator, products and information at desktop, tablet and mobile
   widths; verify long SKU/name text and hover/focus/active tab states.
3. Use one deliberate test SKU to verify draft save → switch SKU → reselect →
   raw draft reload; then confirm one guarded per-unit save with fresh read.
4. Archive and restore the same test SKU; make one deliberate stock adjustment
   with a reason and inspect the visible ledger/audit result.
5. Before enabling editable model/RRP/buyout controls, provide the exact
   approved destination fields and the intended write/audit semantics.

## Recovery

This is one dashboard file. Revert the `3D-P-013` dashboard change before any
local dashboard publication if owner QA fails. No deployment, Apps Script
change, direct Sheet mutation, or CRM write happened in this task.