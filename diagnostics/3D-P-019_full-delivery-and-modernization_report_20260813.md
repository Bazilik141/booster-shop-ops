# 3D-P-019 full delivery and CRM/dashboard modernization report

Date: 2026-08-13

Executor: Codex · model=Sol · effort=xhigh

## Purpose and status

This document consolidates the final state, decisions, implementation path, incidents, repairs, live
evidence, and remaining gates from the 3D-P-019 dialogue. It is the operational overview for future
work. Earlier reports remain the primary evidence for their individual rounds but no longer need to
be read in sequence to understand the current system.

Current production baseline, owner-reported:

- main CRM Apps Script Web App: **V112**, published 2026-08-13 15:41 Kyiv;
- 3D-P Apps Script Web App: **V20**, published 2026-08-13 15:42 Kyiv;
- canonical dashboard: `dashboard/booster-dashboard.html` in this repository;
- post-V112/V20 integrity: `clean=true`, `problems=[]`, three completed 3D-P RRP comparisons,
  `deferred=null`, `elapsed_ms=5502`;
- all V112/V20 data migrations and exact repairs described below completed and repeated
  idempotently.

The repository now contains one additional **local, not-deployed V113/V21 candidate**. It fixes the
last two QA findings from this dialogue:

1. active 3D products with positive 3D-P stock are available in the order-update
   `Додати компонент` selector and can be recorded as gifts/components;
2. the 3D Information → Sales table shows `YYYY-MM-DD`, not `00:00:00` after the date.

No commit, push, Apps Script deployment, or live Sheet write was performed by Codex.

## Final business and accounting rules

### Fixture ownership and stock

- Fixture category is `Фурнітура`.
- `Розхідники!O` is `Платник`, restricted to `власник` or `Сергій`.
- Blank payer on an owner-created fixture defaults to `власник` with a visible alert.
- Fixture consumption is append-only in `Використання_фурнітури`; stock formulas derive from that
  ledger. Historical rows are corrected with compensating entries, never silent deletion.
- A fixture line is tied to one exact 3D CRM sale row. Up to ten fixture lines can be added in one
  order update, so an order may contain multiple 3D products and different fixture payers.
- Zero stock warns but does not block the fixture write, preserving the approved F6 behavior.

### 3D sale mode

The owner controls every 3D order line manually:

- checked `Продаж` = normal 50/50 model;
- unchecked = `Маркетинг` at the frozen Booster Shop buyout price;
- price never switches the mode automatically.

For `Продаж`:

- Serhiy receives production cost;
- Serhiy-paid fixture cost is reimbursed;
- remaining margin after both fixture buckets and packaging is split by the frozen Serhiy share;
- owner-paid fixtures are part of Booster Shop management COGS.

For `Маркетинг`:

- Serhiy receives the frozen buyout price plus Serhiy-paid fixtures;
- Booster Shop management COGS and Marketing include buyout plus both fixture payer buckets;
- the derived expense marker is excluded from direct-order expense recalculation, preventing double
  subtraction.

Current CRM PRRO COGS intentionally remains zero for the 3D financial projection. This must be
revisited during NCRM migration because the tax model is income-based, not profit-based.

### General components and gifts

- Up to ten component rows may be added during an order update.
- Blank target = marketing gift distributed across all order rows by the existing order weights.
- Exact target = fulfillment/Mystery Box COGS only for that row, excluded from Marketing.
- Customer payment is never changed by component, gift, or fixture writers.
- CRM catalogue SKU writes use `Списання`; consumables use the component ledger; both are projected
  into management cost without being counted twice.
- In the V113/V21 candidate, 3D-P SKU are sourced from `3dp_skus`, never from stale CRM stock. Their
  management cost is `Ціна під викуп Booster Shop`.
- A selected 3D product is first appended idempotently to 3D-P `Маркетингові_плюшки`; the existing
  3D availability formula reduces stock. The CRM component ledger is written afterwards. A repeated
  stable request does not create a second remote gift or local component entry.
- A 3D component with a blank target becomes order Marketing. A 3D component with an exact target
  becomes that row's fulfillment COGS, while the remote gift journal still records the physical
  inventory issue.

## Durable data model delivered

### Main CRM

- `Використання_фурнітури`: append-only fixture use/correction ledger with frozen price, payer,
  order reference, CRM target row, and target SKU.
- `Використання_компонентів`: append-only SKU/consumable/3D component ledger with PRRO and management
  values plus optional CRM target row/SKU.
- `3D_облік_замовлень`: append-only per-line 3D accounting snapshots including mode, revenue,
  production/buyout/fixture values, payout, COGS, Marketing, and stable request ID.
- `Витрати`: derived 3D Marketing rows are auditable but excluded from a second direct-expense
  subtraction.
- Component and fixture writers retain stable dashboard request markers for duplicate-submit
  protection.

### 3D-P workbook

- `Продажі!T:AA` contains the CRM row key and frozen sale-accounting values:
  RRP, fixture total/payer, CRM mode, owner fixture, Serhiy fixture, and buyout.
- `% прибутку Сергію` is frozen into Sales H for new rows; the live historical blank was backfilled.
- `_Коригування_наявності` is the append-only stock adjustment ledger.
- `Друк-лог` records manufactured batches; stock derives from printed minus defects/sales/gifts plus
  controlled adjustments.
- `Маркетингові_плюшки` records physical 3D gifts. The V21 candidate adds an owner-only,
  request-idempotent batch action used by CRM order updates.
- `_Аудит_API` records guarded API mutations. Formula cells remain protected.

## Implementation and deployment chronology

### Phase A — fixture category and payer

- Read-only discovery proved there were exactly two fixture rows and that new payer data had to use
  column O without shifting A:N.
- CRM V102 ran `setup3dp019FixturePayerPhaseA()`:
  `header_added=true`, two categories renamed, two payers backfilled.
- Immediate integrity was clean with one completed remote RRP comparison.
- Later Phase-B setup installed strict `власник` / `Сергій` validation and the blank-owner default.

### Phase B/C — usage, corrections, and honest sync

- The owner approved a dedicated fixture ledger after live discovery proved that Sales audit text,
  normal writeoffs, and manual stock literals were unsafe alternatives.
- CRM V103 / 3D-P V11 deployed the usage path. Phase B migrated two fixture rows, installed the
  three forms, and enforced payer validation.
- Phase C added append-only corrections, frozen V/W refresh, historical preview/clear actions, and
  a distinct `skipped_fixture_allocation` journal outcome.
- Partial frozen writes report the exact field state (`V updated; W failed`, etc.) rather than
  claiming that nothing changed.

### Unified SKU creation and load stabilization

- Root cause: creating a product in 3D-P never created the corresponding operational CRM SKU.
- CRM gained guarded `add_sku`; the general product form and 3D product form use the same action.
- Formula-owned CRM surfaces remain formula-owned; idempotent retry and conflict rejection are
  enforced.
- 3D-P gained bounded bootstrap reads; the dashboard reduced failure-coupled requests, retries one
  safe transient GET failure, and reports cross-API partial success honestly.
- The dashboard gained the owner-only general SKU form and an idempotent CRM repair action for
  older 3D products.

### Per-line accounting migration incidents

- 3D-P V14 setup stopped before migration because the Sales grid physically ended before AA.
- V15 expanded the grid and migrated successfully. Its repeat misread Google-added formula quotes as
  a change.
- V16 canonicalized quoted formulas; repeat returned `already_applied=true`, no changes.
- CRM V106 created the accounting schemas but its repeat mistook ARRAYFORMULA spill results for 52
  literal blockers.
- Bounded evidence proved L3/M3 were healthy formula anchors. CRM V107 changed only spill detection;
  repeat returned every counter at zero and `already_applied=true`.

### Dashboard order workflow and accounting

- Multiple fixture lines, multiple components, manual Sale/Marketing switches, Marketing columns,
  disabled save buttons, elapsed-time feedback, and structured partial results were added.
- A 3D-P-only recovery action repeats only synchronization. It never invokes component or fixture
  writers.
- A later STALE_WRITE race was healed by refreshing the exact remote row and retrying only the
  still-different frozen field once.

### Mystery Box COGS regression

- Ordinary order editing recalculated Mystery Box rows without FIFO inventory and replaced linked
  writeoff COGS with auto-consumables only.
- CRM V108 / 3D-P V17 repaired exactly `OC-FOP-0309`, `OC-FOP-0312`, and `OLX-FOP-0050` from their
  surviving linked writeoffs.
- Apply changed all three; repeat returned `orders_changed=0`, `already_applied=true`.
- Later order edits now restore linked-writeoff cost and then reapply component overlays.

### Manufacturing and consumable purchases

- 3D-P V18 / CRM V109 added `Виготовити партію`, formula-safe prepared Sales F replacement,
  3D-P-only retry, and consumable/fixture purchase creation/status management.
- The owner-confirmed arrival repair changed exactly 16 `Витрати` rows from `Їде` to `На складі`;
  repeat was idempotent and integrity stayed clean.
- New consumables may be created through the guarded workflow; formula columns are never replaced by
  literals.

### Marketing, print time, duplicate-submit repair

- 3D-P V19 / CRM V110 normalized decimal print-time formats and exposed component management cost in
  Marketing without double subtraction.
- Stable request IDs, disabled buttons, elapsed timers, repeated-note normalization, and honest
  partial reporting stopped click-spam duplication.
- `MAN-FOP-0005` duplicate compensation initially retained one ACC-002 and one owner-paid fixture.
  Subsequent QA changed that state again, so the complete test-order purge below superseded the
  intermediate repair.

### V112/V20 cleanup and final migration evidence

- `MAN-FOP-0005` exact preview found 2 Sales, 6 component, 6 fixture, 2 accounting and 5 writeoff
  records in CRM, plus one remote Sales and two remote stock-adjustment rows.
- Apply cleared 21 CRM and three remote business records. Audit/sync journals and formulas were
  preserved. Repeat returned all-zero counts and `already_applied=true`.
- 3D Sales profit-share backfill changed only row 3 / `ACC-3D-DITTO-410` to `0.5`; repeat was
  idempotent.
- `MAN-FOP-0006` allocation repair changed only row 274 from
  `38.89 / 31.11 / 46.67` to `38.88 / 31.12 / 46.66`, producing exact totals:
  discount 100, packaging 80, delivery 120. Repeat was idempotent.
- Final integrity returned `clean=true`, `problems=[]`, three completed remote RRP comparisons, and
  no deferred coverage.

## Dashboard modernization delivered

- Operational SKU creation in CRM and cross-creation from 3D Products.
- Direct 3D-P stock source for sale and Warehouse views; stale CRM 3D stock is labeled unavailable,
  not presented as truth.
- Exact-cent sale allocations and reduced manual-sale submit latency by deferring the full catalogue
  cost rebuild.
- Sortable qualified Clients list using the owner-approved OR criteria.
- Collapsible/lazy 3D information blocks, clearer calculator loading state, cached bounded draft
  reads, and batch manufacturing.
- Reliable product filters, persistent Sales column picker, and Serhiy profit percentage.
- Payout period creation and mark-paid workflow with owner-only guarded writes. A period must not be
  marked paid before real money transfer.
- Consumable and fixture purchasing/status workflow.
- Order Marketing summary and per-line Marketing display.
- Optional component target and mandatory fixture target.
- Save progress, duplicate-submit protection, structured partial outcomes, and 3D-P-only recovery.
- V113 candidate: 3D products in order-update components plus clean Sales date display.

## Final V113/V21 correction design

### 3D products in `Додати компонент`

Root cause: `order_component_catalog` read only CRM `Склад` and `Розхідники`. The sale form had a
separate 3D-P stock overlay, but the order-update catalog did not.

Correction:

- CRM excludes all 3D-pattern SKU from the local stock branch;
- CRM merges active, positive-stock rows returned by `3dp_skus`;
- dropdown labels identify kind `3D-P`, canonical 3D name, stock, and buyout cost;
- if 3D-P is unavailable, CRM SKU/consumables remain usable and the dashboard explicitly says why
  3D products are absent;
- selection is revalidated against fresh 3D-P stock and buyout data during save;
- 3D-P `3dp_order_gifts_append` validates owner role, stable request ID, active SKU, buyout price,
  integer quantity, and aggregate stock before any write;
- existing request markers are removed before stock validation, so a retry after a completed remote
  write is a true no-op even if stock has since decreased;
- remote journal write happens before the local component ledger. If the remote side completed but
  CRM later failed, the same request resumes without duplicating the remote gift;
- the sync-only recovery button is reserved for a frozen-row sync failure. If any component/fixture
  writer partially completes, the dashboard instead repeats the stable order update so only the
  unfinished writer resumes;
- the existing `Маркетингові_плюшки` formula reduces stock, so no second adjustment is written.

### 3D Sales date

Root cause: Apps Script returns a normalized date-time string for a Sheet Date value and the generic
table renderer printed it unchanged.

Correction: only the Sales `Дата` cell renderer displays the first `YYYY-MM-DD` part. Stored Sheet
dates, sorting inputs, monthly calculations, and API values are unchanged.

## Verification

Latest local run after the V113/V21 changes:

```text
3D-P API regression tests passed
3D-P-019 fixture usage tests passed
3D-P-019 Phase A setup tests passed
3D sync journal tests passed
CRM catalog SKU create tests passed
CRM integrity-check tests passed
Mystery Box cost repair tests passed
CRM order component tests passed
CRM order-items API tests passed
Qualified clients tests passed
Dashboard 3D sync static tests passed
Dashboard syntax and contract tests passed
ALL_TEST_FILES_PASSED=12
```

New regression coverage proves:

- stale CRM 3D stock is excluded from the component catalog;
- current 3D-P name, stock, and buyout cost are returned;
- insufficient 3D stock is rejected before write;
- Serhiy token cannot append CRM gifts;
- a repeated request does not duplicate the remote gift;
- the CRM calls the specialized remote gift action before its local ledger writer;
- the dashboard exposes 3D products in the component help/contract;
- Sales dates render without the midnight timestamp.

`git diff --check` is clean. These are local mock/static results, not published Web App or live Sheet
proof.

## Required owner deployment order for the final candidate

No setup or schema migration is required for this round.

1. Publish `3d-print/apps-script-3dp-api/Code.gs` as **3D-P V21**.
2. Publish `crm/apps-script/Code.gs` as **CRM V113**.
3. Hard-refresh the canonical local dashboard (`Ctrl+F5`).
4. Run the same bounded CRM integrity check. Require `problems=[]`, `clean=true`, and
   `rrp_mismatch_3dp.deferred=null`.
5. Open an existing order → `Додати компонент`; search `3D`. Confirm active positive-stock 3D
   products show canonical names, 3D-P stock, and buyout cost.
6. Open 3D Print → Information → Sales. Confirm the date is `YYYY-MM-DD` without `00:00:00`.
7. Do not create a fake gift merely for smoke testing: this path writes permanent append-only
   accounting and reduces 3D stock. Complete the first write QA with the next real 3D gift, then
   verify one new `Маркетингові_плюшки` row, one CRM component row, correct Marketing/target COGS,
   unchanged customer payment, and the expected 3D stock decrease.

Stop if either API reports an unexpected result. Do not deploy CRM V113 before 3D-P V21 because the
CRM candidate depends on the new remote action.

## Rollback and residual risks

- Code rollback: republish CRM V112 and 3D-P V20 as new versions, then hard-refresh the dashboard.
- The V113/V21 candidate adds no migration and performs no automatic data repair.
- 3D gift writes are append-only. Correct a wrong real entry through a separately reviewed
  compensating action; do not delete ledger history manually.
- Intermittent Apps Script 404/busy responses are mitigated with bounded safe retries and stable
  request IDs, but live latency remains subject to Apps Script and Sheet recalculation.
- Full browser behavior and real cross-Web-App execution remain owner QA gates.
- OpenCart storefront product creation remains separate from operational SKU creation.
- NCRM migration must revisit the current PRRO treatment and reproduce all append-only accounting
  semantics before retiring the Sheets CRM.

## Superseded evidence reports retained

- `3D-P-019_fixture-phase-a-discovery_report_20260809.md`
- `3D-P-019_fixture-phase-a-setup_report_20260811.md`
- `3D-P-019_phase-b-live-contract-discovery_report_20260811.md`
- `3D-P-019_fixture-usage-phase-b_report_20260811.md`
- `3D-P-019_phase-c_report_20260811.md`
- `CRM_unified-sku-and-dashboard-performance_report_20260812.md`
- `CRM_3DP_order-update-components-and-lazy-load_report_20260812.md`
- `CRM_dashboard_stabilization_round2_report_20260812.md`
- `CRM_3DP_manufacturing-and-consumable-purchases_report_20260812.md`
- `CRM_3DP_marketing-gifts-and-time-repair_report_20260812.md`
- `CRM_3DP_stock-components-payouts-and-test-cleanup_report_20260813.md`
