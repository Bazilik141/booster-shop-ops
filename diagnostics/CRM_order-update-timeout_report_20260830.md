# CRM order update timeout — V157 candidate analysis

Date: 2026-08-30

## Scope

Diagnose repeated `Failed to fetch` / six-minute timeouts while updating
`OC-FOP-0337`, and the new cold Overview delay. Prepare a local source fix and a
bounded live-state probe. No deployment or live workbook write was performed.

## Runtime evidence

- V154 `doPost` executions ended at `360.309 s` and `360.779 s` with the Apps
  Script timeout status.
- V155 still ran beyond `202 s`.
- V156 still ran beyond `289 s`.
- During the V156 POST, most parallel `doGet` calls completed in `2–6 s`, while
  the first cold Overview `doGet` took `95.08 s`.

Therefore the save timeout and cold Overview are related by resource pressure,
but they are two separate request paths. V155 and V156 did not prove a single
slow function and their fixes were insufficient.

## Confirmed unbounded paths

### Component update POST

The component save path could still:

1. build the full component catalog and call the remote 3D-P API even when the
   submitted component was an ordinary CRM SKU or consumable;
2. expand `Списання` or `Використання_компонентів` inside the Web App request;
3. run the full CRM integrity suite before and after that expansion while the
   global `doPost` lock was held;
4. force synchronous spreadsheet recalculation with explicit `flush()` calls;
5. write five component formulas through five separate service calls;
6. invoke 3D synchronization even for an order without a 3D SKU, which appended
   a hidden `skipped_no_3dp_sku` journal row and could re-enter capacity work.

Any of those can amplify sheet recalculation. The expansion/integrity path is
especially unsafe inside an interactive request because it has no request-time
bound.

### Cold Overview GET

The critical Overview bootstrap called the full `apiSummary_()` asset valuation.
That calculation scans purchases, sales, write-offs, migrations, stock, and
catalog data before the first cards can render. The `95.08 s` cold GET matches
that architecture.

## V157 local fix

- Resolve only submitted component IDs. Call 3D-P only for an actual `3dp:` ID.
- Interactive component writes may use only already-prepared rows. If capacity
  is exhausted, return `CRM_CAPACITY_REQUIRED` immediately; the existing nightly
  capacity job owns structural expansion and integrity checks.
- Batch the component write-off formulas and remove explicit `flush()` calls
  from the dashboard order-update handler.
- Skip the entire 3D sync/journal path when the order has no 3D SKU.
- Keep full current-cost projection deferred to nightly maintenance.
- Return per-phase `timings_ms` and log every phase, so a remaining live delay
  has an exact boundary instead of another guess.
- Replace the critical Overview summary with a bounded fast summary. Load the
  full asset valuation separately after visible secondary data and cache it for
  ten minutes.
- Build Overview's active-order preview without scanning the 3D/component
  marketing ledgers.

## Partial-write risk

Apps Script does not roll back sheet writes when a request times out. V154–V156
may therefore have appended a component ledger row and/or write-off even though
the browser showed `Failed to fetch`. Do not submit `OC-FOP-0337` again until the
read-only probe reports its exact component state.

Probe: `diagnostics/CRM_OCFOP0337_component_state_probe_20260830.gs`.

## Live read-only probe result

Owner-run at 2026-08-30 12:29 Kyiv:

- `OC-FOP-0337`: one sale row (`Продажі!313`), TTN `20451523505268`;
- component ledger rows: `0`;
- linked write-off rows: `0`;
- duplicate groups: `0`;
- `Використання_компонентів`: 944 prepared free rows;
- `Списання`: row grid `316/316`, first empty row `317`, prepared free
  rows `0`.

This proves the previous timeouts did not partially append this order's
component records. It also confirms the immediate blocking precondition:
`Списання` had no writable prepared row. The next safe action is the existing
owner-run `maintainCrmRowCapacity()` maintenance function, followed by the same
read-only capacity probe. Do not resubmit the order before that check succeeds.

The owner then ran `maintainCrmRowCapacity()` at 12:36 Kyiv. It reached the
Apps Script six-minute ceiling at 12:42 with `Exceeded maximum execution time`.
That function calls the full `apiIntegrityCheck_()` before expanding any sheet,
so the maintenance design itself is not a usable recovery path at the current
workbook size. Manual row insertion is also unsafe because dependent formula
ranges may keep the old grid boundary.

The bounded recovery file
`diagnostics/CRM_expand_sales_writeoffs_300_20260830.gs` therefore adds exactly
300 rows to `Продажі` and `Списання`, copies only row structure, and refreshes
the canonical formula scopes with a persistent checkpoint. Re-running the same
function resumes formula work and cannot append another 300 rows.

That first recovery design was also rejected by live runtime evidence. Owner
runs at 14:06 and 14:19 both reached `360 s`; `Списання` remained unchanged.
The failure occurs before that sheet because V1 processes `Продажі` first and
uses one `copyTo(PASTE_FORMULA)` operation across its complete 300-row by
32-column destination, forcing another large recalculation. The workbook itself
remained responsive: later `keepWarm`, `onOpen`, and a V156 `doPost` completed.

Replacement:
`diagnostics/CRM_expand_sales_writeoffs_300_v2_20260830.gs`. V2 adopts any Sales
rows already inserted by V1, inserts the missing grids without duplication,
writes formulas in batched `setFormulas` / `setFormulasR1C1` calls, updates the
canonical `Склад!G` write-off boundary in one batch, and verifies the first and
last new formula rows plus prepared capacity. It does not call the timed-out V1
function or the full integrity suite.

V2 live diagnosis at 16:09 proved the prior V1 defect more precisely:
`Списання` is now 636 rows, and every V2 range row 337:636 contains the literal
ID `WRT-0226`; `Продажі` is 752 rows and its new range 453:752 has no key data.
V2 stopped before formula writes because its guard detected those literals.
The workbook remained readable and the guard prevented another overwrite.

Recovery V3 is `diagnostics/CRM_recover_copied_writeoff_tail_v3_20260830.gs`.
It requires the exact probed 636/752 grids, classifies rows after the original
316-row write-off boundary, clears only manual fields that exactly match a
historical row, refuses any unique or changed tail record, restores formula
columns in batches, prepares the blank Sales rows, extends `Склад!G`, and
verifies at least 300 prepared rows. It is designed to be idempotent if a batch
is interrupted and repeated.

V3 live recovery completed at 18:36 Kyiv in 13 seconds. It cleared all 320
confirmed cloned write-off rows, restored `Списання!317:636`, updated 199 stock
formulas, and reported 320/438 free rows in `Списання`/`Продажі`. The write-off
recovery is therefore complete.

The same run exposed a separate V3 defect in the Sales preparation step. V3
used row 452 (the pre-insertion grid boundary) as its formula template, while
the V2 diagnosis had already shown that the last structured Sales row was 433.
Owner screenshot evidence then showed rows 434:452 without row structure and
formula errors from row 453 onward. The bounded V4 recovery
`diagnostics/CRM_repair_sales_tail_v4_20260830.gs` therefore validates row 433,
refuses any manual value in `Продажі!A434:AF752`, and repairs only that empty
tail. If its post-write formula check finds any error, it clears that empty tail
again and returns `ok:false`; rows 1:433 are never touched.

The first V4 preflight stopped before writing because Google Sheets exposes the
V3 malformed cells as display `#ERROR!` while `getFormulasR1C1()` returns an
empty string. V4 was tightened to allow only that exact error token inside the
already confirmed empty tail; any other non-formula display value remains a
hard pre-write stop.

The corrected V4 run then proved that `setFormulasR1C1()` is incompatible with
this sheet's formula representation: every formula was present but displayed
`#ERROR!`. Its fallback succeeded and cleared rows 434:752; it reported no
errors after fallback and again left rows 1:433 untouched. V5 therefore avoids
formula text parsing entirely. It uses the spreadsheet's native `autoFill`,
first on one canary row and then in verified 25-row resumable chunks, stopping
before the six-minute runtime ceiling.

V5 completed live at 18:59 Kyiv in 12 seconds. Native autofill prepared all 319
rows `Продажі!434:752`, verified 11 formulas per row, reported zero formula
errors, and confirmed that core rows 1:433 were untouched. Together with the
successful V3 write-off recovery, both requested 300-row capacity additions are
now repaired and usable.

Final pre-retry probe at 19:16 Kyiv confirmed `OC-FOP-0337` still has exactly
one sale row (`Продажі!313`) and zero component-ledger rows, linked write-offs,
or duplicate groups. Prepared capacity is 320 write-off rows and 944 component
ledger rows. This closes the partial-write gate: one retry is safe after V157
publication and dashboard refresh.

The owner then supplied a clean full CRM integrity result from deployed V156:
`problems:[]`, `clean:true`, seven 3D RRP comparisons, zero skipped CRM RRP
rows, and `elapsed_ms:11442`. This closes the required post-recovery structural
integrity check. It does not claim V157 runtime proof because V157 is still a
local candidate.

The permanent V157 candidate now applies the same proven rule to future capacity
growth: formats and validations are copied normally, but only actual formula
columns are extended with native autofill. Literal `PASTE_FORMULA` cloning is
removed. Capacity formula-range updates are batched by contiguous column runs,
and nightly maintenance no longer starts the full integrity suite that already
proved unable to finish within six minutes; it performs bounded first/last-row
formula-structure verification instead. The full dashboard integrity action
remains a separate explicit diagnostic.

## Local verification

Passed after the final V157 changes:

- Node parse: complete `Code.gs` and the temporary probe;
- all 23 CRM Apps Script tests plus the dashboard contract test (24/24);
- `git diff --check` on the scoped files.
- direct comparison against the complete owner-supplied V156 export: 194
  insertions / 36 deletions, limited to the reviewed timeout, capacity, and
  Overview paths.

The regression contracts explicitly reject a component POST that calls the
remote 3D catalog for local IDs, expands sheets, runs the 3D skip-journal path
for a non-3D order, calls `SpreadsheetApp.flush()`, or runs the full current-cost
projection.

## Deployment gate

Source is not deployment. The owner must first run the read-only probe and keep
its JSON output. Only then should the complete `Code.gs` be pasted and published
as a new Web App version. A retry is allowed only after duplicate/partial rows
are ruled out or repaired.

## Risk

Medium: CRM accounting. The patch changes request orchestration and capacity
handling, not the accounting formulas or canonical component records. Runtime
and live workbook proof remain owner-gated.
