# CRM/dashboard order editing and marketing-cost fix — local report

Date: 2026-08-26

## Outcome

Prepared a CRM/dashboard fix for two linked defects. The owner subsequently reported publishing the main Apps Script source and a successful, bounded one-time recovery for `OC-FOP-0329`; this report does not independently verify the live Web App publication.

The owner-provided complete source attachment was compared with `crm/apps-script/Code.gs`; its body is identical, with only one trailing blank line difference.

## Root causes

1. `openEditRow()` used `Promise.all()` for the order context and the component catalogue. The catalogue calls the 3D-P Web App, so a slow remote request blocked the whole editor even when the order data was already available.
2. A FIFO/status refresh could overwrite `Продажі!L:M` while the component audit in `AE` remained. A later component replay then subtracted the stale audit amount and added it back, producing no correction. This explains a row that still displays marketing but has a base-only management cost.

## Local changes

| File | Change |
|---|---|
| `crm/apps-script/Code.gs` | Added reset-before-refresh and replay-after-refresh helpers for order-component projections. Applied the pair to the spreadsheet menu update, dashboard update, OpenCart existing-order update, Telegram status update, and 3D-P retry. |
| `dashboard/booster-dashboard.html` | Renders order fields after `order_edit_context`; the slow component/3D-P catalogue now loads in the background. Component and fixture buttons remain disabled until its result arrives. |
| `crm/apps-script/TEMP_repair_OC_FOP_0329_marketing_cost.gs` | One-time, public recovery wrapper used only for the verified repair below; removed locally after success. |

## Local validation

- All 20 CRM Apps Script test files passed.
- `dashboard/tests/dashboard-contract.test.mjs` passed.
- Node function parse of `crm/apps-script/Code.gs` passed.
- `git diff --check` passed.

The new regression proves this sequence: project components once, remove the prior projection before a base FIFO refresh, then reapply it exactly once. It also proves the result is not double-charged.

## Owner-reported recovery and remaining QA

The owner-reported recovery result at 11:47:37 was:

```json
{"ok":true,"already_applied":false,"order":"OC-FOP-0329","rows":[302],"marketing":1514.46,"management_cost_before":3.26,"management_cost_after":1517.72}
```

1. Delete the temporary recovery wrapper from the bound Apps Script project; it has been removed from this local workspace.
2. Refresh the dashboard and verify OC-FOP-0329 shows management cost `₴1,517.72` (normally rendered as `₴1,518`), marketing `₴1,514.46`, and profit about `₴368.28` / `18.4%`.
3. On a fresh dashboard page, open one ordinary order without adding a component. Its edit fields must appear before the component catalogue finishes; the two component-related add buttons become available when the background load completes.

If any preflight or post-write result differs, stop before retrying and preserve the returned JSON/error text for review.
