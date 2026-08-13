# CRM + 3D-P — MAN-FOP-0006 test-order purge, then one-off migration archive

Date: 2026-08-13 · Tasks: `3D-P-019` follow-up (purge) + new cleanup work package

**Executor: Codex · model=Sol · effort=xhigh**

Justification: Codex authored every round in both files this week; swapping executors would be a
parallel-writer violation. WP1 permanently clears live financial rows in two systems — a risky zone,
so it does not go on a small model.

**Two rounds, two deployments. Do not merge them.** WP2 archives the very machinery WP1 uses.

---

## 1. Task ID

`3D-P-019` follow-up. WP1 = purge `MAN-FOP-0006`. WP2 = archive spent one-off migrations from both
Apps Script projects.

## 2. Context

Live baseline, owner-reported and accepted as source-identical because the owner pastes these exact
repository files: **CRM V113 (2026-08-13 16:58 Kyiv)**, **3D-P V21 (16:56 Kyiv)**. Post-deploy
`integrity_check`: `clean=true`, `problems=[]`, `rrp_mismatch_3dp.compared=3`, `elapsed_ms=6807`.

Current size: CRM `Code.gs` 6 865 lines / 392 functions; 3D-P `Code.gs` 3 015 lines / 164 functions.
At CRM V102 the file was 4 714 lines, so roughly 2 150 lines landed in five days.

Reviewer findings this handoff is built on, verified by reading the current files:

- The cross-system test-order purge **already exists and is live-proven** on `MAN-FOP-0005`
  (21 CRM records + 3 remote records cleared; repeat returned all-zero and `already_applied=true`).
- **It clears cells in place and never deletes rows.** There is no `deleteRows` anywhere in that
  code path. Row numbering therefore survives, and every row-number constant elsewhere in the file
  (`272/273/274`, `268`) stays valid after a purge.
- The order is pinned by two frozen constants — `CRM_TEST_ORDER_PURGE_` and `TEST_ORDER_PURGE_3DP` —
  plus an allow-list check and the literal confirmation phrase `DELETE MAN-FOP-0005`.
- 25 functions have no reference anywhere in their own file. None of the five `3dp_setup_*` API
  actions is called by the dashboard or by the CRM; the two dashboard hits are historical prose in
  `ROADMAP_TASKS` notes, not calls. Only tests reference them.

### Owner decisions, 2026-08-13

1. **Purge scope:** the whole `MAN-FOP-0006` order, all three lines.
2. **Archive, not delete:** spent one-offs move to a **separate repository file that is never pasted
   into the live project**. Explicitly rejected: leaving them commented out inside the working
   `Code.gs`. The deployed script must get shorter, not equally long with a garland of `//`.
3. **Telegram / news digest subsystem is live and entirely out of scope.** The owner publishes news
   through `/digest` and `/post`. Do not touch, do not "tidy", do not test-remove:
   `newsDigest`, `tgDraftPostAnthropic_`, `tgSetupCommands`, `testTelegramSend`,
   `testNewsEditorialAudit`, `runNewsPruneOnce`, `setupNewsDigestTrigger`, `keepWarm`,
   `createDailyLotStatusTrigger`, or anything they reach.

## 3. Goal

WP1: remove the `MAN-FOP-0006` test order from both systems with no residue and no collateral
damage, reusing the already-proven mechanism rather than writing a second deletion path.

WP2: shrink both live scripts by moving spent one-off migrations and repairs into a non-deployed
archive, with zero behaviour change to everything that remains.

## 4. What to change

### WP1 — `MAN-FOP-0006` purge (round 1)

**Generalise the existing mechanism. Do not write a new one.**

1. Replace the single pinned order in `CRM_TEST_ORDER_PURGE_` and `TEST_ORDER_PURGE_3DP` with a
   small **allow-list** of purgeable test orders. Keep every existing gate: owner role only,
   dry-run first, exact confirmation phrase derived per order (`DELETE MAN-FOP-0006`), snapshots
   before clearing, formulas preserved, audit and sync journals preserved.
2. Rename the entry points away from the `0005` label so the next test order does not need a third
   copy. Keep both a preview and an apply function; keep `LockService`.
3. Extend the plan to `MAN-FOP-0006`'s three lines:
   `ACC-3D-DITTO-410` (3D), `PKM-JP-MBX-XL` and `OP-JP-MBX-ST` (both Mystery Box).

**Three things that must be PROVEN before apply, not assumed** — `MAN-FOP-0005` did not exercise any
of them:

- **FIFO lot restoration.** The two Mystery Box lines consumed `Закупки` lots through
  `getFifoCostBatches_`. Establish by bounded live read whether lot balances are formula-derived
  from `Списання` — in which case clearing the write-offs self-heals them — or literals, in which
  case the purge must restore them explicitly. If they are literals and restoration is not safely
  derivable, **stop and put it to the owner**. Do not guess a lot balance.
- **Gift ledger coverage.** `Маркетингові_плюшки` and the `3dp_order_gifts_append` request markers
  are newer than the `MAN-FOP-0005` purge plan and may not be in it. Determine whether
  `MAN-FOP-0006` produced a gift record on either side, and cover it if so.
- **Component ledger and accounting snapshot coverage.** Confirm the plan reaches
  `Використання_компонентів`, `Використання_фурнітури`, `3D_облік_замовлень`, `Списання`, `Витрати`
  and the derived 3D Marketing expense rows for this order — and that clearing the derived expense
  marker does not leave a double-subtraction or an orphaned Marketing figure.

The preview must report exact per-table counts for `MAN-FOP-0006` before anything is written.

### WP2 — archive spent one-offs (round 2, only after WP1 is complete and verified)

Create two files that are **never pasted into a live Apps Script project**:

- `crm/apps-script/archive/one-off-migrations_20260813.gs`
- `3d-print/apps-script-3dp-api/archive/one-off-migrations_20260813.gs`

Each gets a header stating: what it is, that it is not deployed, which live version it was extracted
from (CRM V113 / 3D-P V21), and that restoring any function requires a fresh review because the
schema it targeted has since moved.

**CRM — move out (verified present, ~266 lines plus constants):**

| Entry point | Anchor |
|---|---|
| `setup3dp019FixturePayerPhaseA` | line 4267 |
| `preview3dp019HistoricalFixtureFrozenValues` / `clear3dp019HistoricalFixtureFrozenValues` + `plan3dp019HistoricalFixtureFrozenCleanup_` | 1640 |
| `previewMysteryBoxCostRegressionRepair` / `repairMysteryBoxCostRegression` + `mysteryBoxCostRegressionRepairAction_` + `CRM_MYSTERY_COST_REPAIR_ORDERS_` | 2045, 2051 |
| `previewManFop0006AllocationRepair` / `repairManFop0006Allocations` + `manFop0006AllocationRepairAction_` + `CRM_MAN_FOP_0006_ALLOCATION_REPAIR_` | 2137, 2145 |
| `previewConsumableArrivalStatusRepair` / `repairConsumableArrivalStatus` + `consumableArrivalStatusRepairAction_` + `CRM_CONSUMABLE_ARRIVAL_REPAIR_NAMES_` | 4076, 4097 |
| `previewManFop0005UsageDuplicateRepair` / `repairManFop0005UsageDuplicates` + `manFop0005UsageDuplicateRepairAction_` + `CRM_MAN_FOP_0005_USAGE_REPAIR_` | 6407, 6413, 6419 |
| the generalised purge from WP1 — **last**, only once `MAN-FOP-0006` is done | 2164–2236 |

`CRM_MAN_FOP_0006_ALLOCATION_REPAIR_` is moot after WP1: it repairs rows that will have been cleared.

**3D-P — move out (~650 lines of 3 015):**

`setup3dp010`, `setup3dp015`, `setup3dp024`, `setup3dpOrderLineAccounting`,
`setup3dpSalesProfitShareBackfill`, `repair3dpAvailabilityFormulas`, `preview3dp015`,
`preview3dp024`, `preview3dpOrderLineAccounting`, `preview3dpSalesProfitShareBackfill`,
`preview3dpApiAddendum2`, `preview3dpApiSetup`, and their `*Action3dp_` bodies and header helpers
(`setup3dp010Action3dp_`, `setup3dp015Action3dp_`, `setup3dp015NomenclatureHeaders3dp_`,
`setup3dp015SalesHeaders3dp_`, `setup3dp024Action3dp_`, `setup3dp024PrintTimeEntry3dp_`,
`setup3dpOrderLineAccountingAction3dp_`).

Also remove the five now-unreachable API branches: `3dp_setup_3dp010`, `3dp_setup_3dp015`,
`3dp_setup_3dp024`, `3dp_setup_order_line_accounting`, `3dp_setup_addendum2`. This is a deliberate
reduction of the remote API surface — state it plainly in the report.

**Before moving anything, verify it is genuinely unreachable:**

- `crmRowsMatching_`, `snapshotCrmRange_` and `restoreCrmRange_` sit inside the purge block but look
  like general helpers. If any live code path uses them, they **stay** in the working file.
- **Apps Script time-driven triggers call functions by name from project settings, not from source.**
  "No reference in the file" is not proof of death. Before removing any function that could
  plausibly be a trigger target, ask the owner to read out the Triggers panel of both projects.
- Tests referencing archived functions move to a matching archive test location or are removed with
  the reason recorded. Never leave a test importing a function that no longer exists.

**Report only, no action:** `apiLtvReportLegacy_` is unreferenced and is not part of the news
subsystem. List it with a recommendation and let the owner decide separately.

## 5. Do not touch

- The Telegram / news digest subsystem, in full — see owner decision 3. This is the single largest
  trap in this task: those functions look unreferenced and are actively used.
- Any behaviour of code that remains. WP2's diff must be **pure extraction plus deletion**, never a
  rename, reorder, reformat or "while I'm here" improvement.
- Row-number-based constants elsewhere in the CRM file.
- `CRM_3DP_SALES_FROZEN_HEADERS_` and the `T:AA` frozen contract.
- Audit trails and sync journals — `_Аудит_API`, `Журнал_3DP_синхронізації`. The purge preserves
  them by design; that is not negotiable.
- Append-only ledger history for orders other than `MAN-FOP-0006`.
- `dashboard/booster-dashboard.html` — no dashboard change is required by either work package.
- Protected zones, listed for completeness and untouched here: `sitemap.xml`, `robots.txt`,
  redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, schema.

## 6. Likely files / areas

Likely, to be verified by the executor against the actual files — line anchors are from the current
repository copy and will shift as edits land:

- `crm/apps-script/Code.gs` — WP1 lines ~2137–2236; WP2 anchors in the table above
- `3d-print/apps-script-3dp-api/Code.gs` — WP1 lines ~642–680; WP2 lines ~1413–1930 and ~2525–2660
- `crm/apps-script/tests/`, `3d-print/apps-script-3dp-api/tests/`
- new: `crm/apps-script/archive/`, `3d-print/apps-script-3dp-api/archive/`
- new: `diagnostics/CRM-3DP_test-order-purge-and-archive_report_20260813.md`
- `crm/apps-script/SOURCE_STATE.md` — after the owner reports each published version

## 7. Acceptance criteria

**WP1**

1. Preview reports exact per-table counts for `MAN-FOP-0006` across every CRM and 3D-P surface and
   writes nothing.
2. FIFO lot behaviour for the two Mystery Box lines is proven by evidence in the report — stated as
   self-healing or explicitly restored. No assumption.
3. Apply clears exactly the previewed records; no row is deleted; row numbering is unchanged.
4. Formulas, `_Аудит_API` and the sync journal survive.
5. Repeat run returns all-zero counts and `already_applied=true`.
6. A different order id cannot be purged without being added to the allow-list, and the confirmation
   phrase is still mandatory.
7. `integrity_check` after apply: `problems=[]`, `clean=true`, `deferred=null`.

**WP2**

8. Both live files parse; both shrink measurably — state before/after line and function counts.
9. Full local suite passes (the run reported `ALL_TEST_FILES_PASSED=12`; state the new number and
   account for any change).
10. `integrity_check` output is byte-identical to the WP1 post-apply baseline.
11. No commented-out archived code anywhere in either working `Code.gs`.
12. Every archived function exists verbatim in its archive file — diff the extracted text against the
    pre-change source to prove nothing was silently altered on the way out.
13. Trigger panel contents recorded in the report, with the owner as the source.

## 8. QA / smoke test

Owner-run, on production. **There is no staging; both scripts are live.**

**Round 1 — WP1**

1. Named Google Sheets version of both files. Run `integrity_check`; keep the output.
2. Publish 3D-P first, then CRM — CRM depends on the remote purge action.
3. Run the purge **preview**. Read the counts. Sanity-check them against what you remember of the
   order before approving.
4. Run apply. Then run it a second time — must be all-zero and `already_applied=true`.
5. `integrity_check` again: `problems=[]`, `clean=true`.
6. Open the dashboard (`Ctrl+F5`): `MAN-FOP-0006` gone from orders, Marketing and 3D accounting; 3D
   stock for `ACC-3D-DITTO-410` increased by the purged quantity; Mystery Box stock/lots correct.

**Round 2 — WP2**

7. Named versions again. Publish both. `Ctrl+F5`.
8. `integrity_check` — must match the round-1 result exactly.
9. Post one news item through `/digest` → `/post`. This is the explicit regression check for owner
   decision 3.
10. One ordinary order save and one order update, to confirm nothing that remained was disturbed.

## 9. Rollback note

- **WP1 code:** republish the previous version (CRM V113 / 3D-P V21).
- **WP1 data:** the named Sheets versions from QA step 1 are the only recovery point. The purge
  snapshots rows in memory during the run, which protects against a mid-run failure — it is **not** a
  post-hoc undo. Keep the preview output; it is the sole record of what the rows held.
- **WP2:** republish the round-1 versions. Because WP2 is pure extraction, rollback is code-only —
  no data implications.

## 10. Recommended status after execution

`3D-P-019` unchanged by this work. The archive package is new scope and should get its own roadmap
row before WP2 deploys. Recommendation only — the owner authorizes, Claude (chat) performs any Notion
write.

## Delivery

Apps Script, not an OpenCart patch: no `patches/` file, no `php <patch>.php`. The executor edits the
repository files, runs the local suites, and writes the diagnostic. **The owner** pastes each file
into the live bound editor and publishes. The executor never commits, pushes, publishes or deploys,
and does not update the deployed-version line in `SOURCE_STATE.md` until the owner reports the
published version numbers.
