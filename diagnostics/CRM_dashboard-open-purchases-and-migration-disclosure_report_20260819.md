# CRM dashboard open purchases and migration disclosure — local fix report

Date: 2026-08-19

## Live evidence

Read-only inspection of CRM spreadsheet `1PvlSlg3UoPw8Fbj98lHL-VGLB0HP8hgKUxsXPW1GkRg`,
tab `Закупки` (sheet id `58135100`, grid `309 × 20`), bounded to `A2:T309`:

- `yskh284` is `LOT-0097`, row 92, `PKM-JP-MZERO-BLR`, blank track/date, status `Замовлено`.
- `yskh289` is `LOT-0122`, row 113, `OP-JP-OP16-BST`, blank track/date, status `Замовлено`.
- There are 28 eligible open lots: 5 untracked `Замовлено` and 23 tracked `В дорозі`.
- `LX328130128JP` has six eligible lots: `LOT-0093` at row 88 and `LOT-0123` through
  `LOT-0127` at rows 114–118.

## Root cause

The purchases tab called `recent_purchases` with `limit:20`. Its old global slice happened before
the dashboard split the result into untracked lots and tracked parcels. Older open lots therefore
disappeared even though no UI rule limited either section to five rows.

## Change

- The dashboard requests `include_all_open:true` for the purchases tab.
- `apiRecentPurchasesForUpdate_()` returns every eligible open lot for that explicit mode.
  Its ordinary recent mode retains complete non-empty tracked parcels instead of rendering a partial
  shipment.
- `Внутрішня міграція товару` is now a native collapsed `<details>` block. Its FIFO context is
  fetched only when the owner expands it; the closed heading is keyboard-accessible and no CSS
  override or `!important` was added.

## Local verification

- `node crm/apps-script/tests/recent-purchases.test.mjs`
  - retains a six-lot tracked parcel when its oldest sibling is beyond the ordinary recent window;
  - returns every open lot, including an older untracked lot, in `include_all_open` mode.
- `node crm/apps-script/tests/purchase-batch-limit.test.mjs`
- Node VM parse of `crm/apps-script/Code.gs`.
- `node dashboard/tests/dashboard-contract.test.mjs`.
- `git diff --check`.

## Deployment and QA gate

The Apps Script portion is local only and requires a new CRM Web App version after V131.
The canonical dashboard file is local; refresh the file after saving it.

Success criteria:

- `yskh284` and `yskh289` appear under untracked purchases;
- `LX328130128JP` displays `6 лот.` including `PKM-JP-MSYM-BBX`;
- the migration block is closed on entry, opens by mouse or keyboard, and only then loads its FIFO
  selectors.

Visual browser QA against the local `file://` page could not run because the environment blocked
that URL. Static layout review confirms the new summary uses the existing responsive write-grid and
has no fixed-width or breakpoint-specific override; final visual QA remains owner-gated.
