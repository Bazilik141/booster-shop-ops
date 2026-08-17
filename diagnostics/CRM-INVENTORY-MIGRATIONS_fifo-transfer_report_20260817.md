# Codex Report — CRM-INVENTORY-MIGRATIONS: FIFO internal SKU transfers

Date: 2026-08-17

## Scope

Implemented the two owner-approved dashboard operations:

- one selected box SKU → a selected existing single-pack SKU, with a manually entered integer number of packs;
- a selected single-pack SKU → the existing `PKM-JP-OUTL-BST` Outlet Mix SKU, one pack for one pack.

The implementation does not edit historic rows in `Закупки` or create a misleading new cash purchase. It creates an append-only `Міграції_Складу` ledger which records the source FIFO lot, source and target quantities, carried PRRO/management cost, unit target cost, operation ID, request ID, and creation time.

## Files touched

```text
crm/apps-script/Code.gs                               — API, ledger, FIFO/current-cost/summary integration
dashboard/booster-dashboard.html                      — two dashboard migration forms
crm/apps-script/tests/inventory-migration.test.mjs    — focused FIFO/idempotency regression
dashboard/tests/dashboard-contract.test.mjs            — UI/API contract assertions
```

## Accounting behaviour

- Source quantity is consumed from the current FIFO sequence, including prior sales, write-offs, and prior migrations.
- The target receives a virtual FIFO lot for every consumed source-lot slice. Its total cost equals the moved source cost; for box conversion that total is divided by the manually entered pack count.
- `Склад!H` is safely wrapped only for the two participating SKU rows to add inbound and subtract outbound ledger movements while retaining the prior formula as the base expression.
- Current warehouse cost, FIFO sale cost, and summary warehouse/asset value read the new virtual lots and outgoing movements. The transferred value is therefore not counted twice.
- The API checks the source quantity submitted by the form against a freshly calculated FIFO balance, uses a per-submit request ID, and rolls back newly written ledger rows/formulas/current-cost cells if its post-write verification fails.

## Local verification

```text
node crm/apps-script/tests/inventory-migration.test.mjs
Inventory migration FIFO and idempotency tests passed

All 16 existing crm/apps-script/tests/*.test.mjs passed
Dashboard syntax and contract tests passed
Code.gs parse ok
git diff --check passed
```

The focused test covers box → 36 packs, packs → Outlet Mix, carried cost, resulting FIFO batches, source/target balances, formula wrapping, and a repeated request that appends no duplicate ledger row.

## Idempotency

The dashboard creates one request ID per submit attempt and retains it on a retry. Repeating that same ID and payload returns `already_applied: true`; it does not add rows or move inventory again.

## Rollback

During a failed action, the API clears only its just-appended ledger rows, restores only formulas it had changed, restores the captured current-cost cells, recalculates, and invalidates the dashboard cache.

After a successful business migration, the ledger is intentionally append-only. Do not delete or rewrite its rows to correct a business mistake: stop and review the operation ID plus later sales before making a compensating inventory correction. A general user-facing reversal flow is deliberately out of this scope because a target SKU may already have been sold.

## Owner publication and QA gate

No live Sheet or Apps Script project was changed. The local mirror must be pasted into the bound Apps Script project and published as a new Web App version by the owner.

After publication:

1. Hard-refresh the dashboard and open `Облік`.
2. Confirm both forms load only the expected box/pack SKUs and show `PKM-JP-OUTL-BST` as the fixed Outlet target.
3. In a safe test or controlled real operation, transfer one box into a known pack count. Confirm one `Міграції_Складу` operation, the source/target `Склад!H` deltas, and identical total carried cost.
4. Transfer a known number of packs into Outlet Mix. Confirm the same checks and that a subsequent test sale reads the target FIFO cost.
5. Run the dashboard CRM integrity check. The first transfer for a SKU also returns bounded before/after integrity results because it wraps that SKU's `Склад!H` formula.

## Side effects and risks

- The formula wrapper is intentionally narrow, but its first use changes `Склад!H` for the source and target SKU. The action stops if either cell is not a formula.
- SKU routing is format-based: boxes need `box`, `бокс`, or `display` in `Товари!G`; packs need `booster`, `бустер`, `pack`, or `пак`. A nonstandard format will be rejected rather than guessed.
- Completed historic purchases retain their original purchase status. The migration ledger, not a rewritten purchase row, is the audit source for an internal conversion.
