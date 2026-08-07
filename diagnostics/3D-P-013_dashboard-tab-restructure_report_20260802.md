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

## Addendum — owner browser QA findings, 2026-08-02

Owner ran manual QA in a real browser (desktop width) per this report's "Owner QA / live gate" section.

**Working:** SKU archive/restore (Активний → Архів → Активний visibility toggled correctly). Вироби form
renders with Посилання-на-модель/РРЦ/Ціна-під-викуп correctly read-only/placeholder per the "Hard boundary"
section above — no fictitious Sheet write attempted, matches spec. Наявність adjustment ledger works
functionally: a test correction changed `Наявність!G` from `1` → `3` and the append-only history table shows
both the earlier Addendum #2 positive-smoke entry and the new test entry.

**Bug found:** `Помилка: renderThreeDpAttention is not defined` appears (a) on initial load of the Калькулятор
zone before any interaction, and (b) again after saving Вироби fields / recording a stock adjustment. This is
a JS `ReferenceError` — some render/refresh path calls `renderThreeDpAttention()` (presumably the "Потребує
уваги" alerts-block renderer from Zone C) but that function is either never defined or named differently
elsewhere in the file. It does not appear to corrupt data (the stock ledger and archive state both updated
correctly despite the error), but it surfaces as a visible red error banner to the owner on core actions —
needs a fix before this ships to Serhiy or is considered QA-passed.

**Needs a second look, not confirmed as a bug:** the "Останні коригування" (recent adjustments) table shows
`Delta: —` for both history rows, even though the naявність total visibly changed by a nonzero amount each
time (`0→1`, then `1→3`). Either the delta value isn't being echoed back correctly by the ledger read, or the
UI isn't rendering the field that holds it — needs Codex to check whether the ledger read action actually
returns a delta field and whether the table binds to the right key.

**Not yet tested:** tablet/mobile widths, long SKU/name text, hover/focus/active states (owner QA only covered
desktop so far).
## Addendum — local fix for owner QA findings, 2026-08-02

**Root cause.** `renderThreeDpInformation()` called
`renderThreeDpAttention(records)`, but the only renderer definition is
`threeDpAttention(records)`. The call runs during the all-zone refresh used by
initial 3D load, product saves and stock adjustments, which explains every
observed ReferenceError without affecting the already-completed API write.

**Fix.** The call now uses the defined `threeDpAttention(records)` function.
The stock-ledger API response is confirmed to use the exact header
`Зміна наявності, шт`; the UI had incorrectly used a nonexistent `Delta` key
and now binds that returned header directly. A numeric zero remains visibly
`0`, not an em dash.

**Local regression check.** `tests/3d-p-013-dashboard-ui-regression.test.mjs`
compiles the inline dashboard script and verifies the corrected all-zone render
path plus the exact API ledger-header binding. It does not call the live API.

**Remaining owner re-QA.** Reload the normal local dashboard, open all three
zones, save one already-selected SKU field without changing data, and inspect
the existing recent-stock ledger. Then use a separately approved test SKU for
any data-changing adjustment. The prior archive/restore and stock writes are
not repeated by this fix.
## Addendum — post-mutation dashboard refresh, 2026-08-02

**Change.** Creating or saving a SKU in `Вироби` now keeps the bounded
`3dp_get_row` confirmation, then calls `reloadThreeDpData()` and
`renderThreeDpAll()`. The existing archive/restore and stock-adjustment paths
already use the same full reload; the latter also reloads the selected SKU's
ledger. Consequently, all 3D-P zones reflect each successful mutation without
a browser-page reload.

**Local regression.** The dashboard regression test now asserts full
API-backed reload and full-zone render paths for new/existing SKU save,
archive/restore, and stock adjustment. Browser/API runtime behaviour remains
for owner re-QA; no live write was run by this change.
