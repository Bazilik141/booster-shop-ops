# CRM-012 — inventory migration reservation and FIFO repair

Date: 2026-08-20

## Scope and authority

Local source and regression-test repair only after owner-reported CRM V137 publication. No Google Sheet rows were read or written by this repair, no Apps Script source was pasted or published, and no dashboard was deployed. Source editing is not evidence of a deployed Web App version.

## Confirmed code-path defects

1. `getSoldQtyBySkuForLotStatuses_()` counted only completed sales. Active `Передзамовлення` did not reserve stock for the inventory-migration snapshot or source allocation. A transfer of 28 packs therefore appeared as 28 available instead of `28 - 2 = 26` for an existing two-pack preorder.
2. `fixSaleCostForRow_()` correctly deferred preorder cost until inventory existed, but `apiInventoryMigration_()` only refreshed the SKU cost. It had no path to backfill deferred preorder rows after the migration made FIFO stock available.
3. `upsertOpenCartOrder_()` returned `ignored_existing_order` for every previously imported OpenCart order. A later quantity revision in the same order could neither adjust `Продажі` nor trigger recalculation.
4. The migration formula wrapper preserved the previous `Склад!H` calculation and added the migration ledger, but did not guarantee that an active `Передзамовлення` was subtracted from that formula. This explains how a moved quantity could stay visibly fixed at 28 even when two units were already reserved.
5. Owner-created `WRT-0208` proved the same formula also did not subtract `Списання!F` by `Списання!D` SKU. The FIFO/current-cost code did count that write-off, so the workbook showed conflicting warehouse facts: cost logic consumed one pack while `Склад!H` remained 28.

## Implemented contract

- Active valid orders, including `Передзамовлення`, reserve inventory after the existing `Налаштування!B8` policy cutoff. Cancellations and returns do not reserve it.
- Migration stock snapshots include sale, write-off, and prior-migration-only SKUs, so they can represent a negative reserve before the first target lot exists.
- Every migration formula plan now explicitly subtracts `Продажі!X = Передзамовлення` when that reservation is not already present. The same correction is safely applied for the named preorder by its ordinary dashboard save, with before/after CRM integrity checks and formula rollback on failure.
- Every migration formula plan also subtracts `Списання!F` by SKU when not already present. The existing public menu `Booster CRM → Оновити собівартість складу` repairs tracked migration formulas as part of its normal run, including the already-written `WRT-0208`.
- After a box-to-packs or packs-to-outlet migration, active preorders for the target SKU are considered in customer-order FIFO priority. Cost is written only when real eligible FIFO quantity exists; a shortage remains explicitly deferred, never replaced by zero or an invented fallback price.
- The migration transaction snapshots preorder price/cost/audit cells and restores them if a later migration write fails.
- The normal dashboard order-save route also attempts this safe deferred-cost backfill. It provides a controlled recovery path for an already-recorded migration without a private Apps Script helper.
- Repeated OpenCart delivery now updates an existing order only when it is a safe one-to-one OpenCart SKU match. Quantity and relevant metadata changes are synchronized and repriced. SKU-set changes or non-OpenCart rows fail closed for manual reconciliation; no duplicate order rows are created.
- Migration source allocation now observes the same customer reservation rule, so packs already promised to customers cannot be silently moved into outlet stock.

## Regression evidence

`inventory-migration.test.mjs` now creates a two-pack preorder and `WRT-0208` one-pack write-off before a box-to-pack transfer. It proves `36 transferred - 2 preorder - 1 write-off = 33`, then proves a five-pack outlet transfer leaves 28. It also proves that the stock formula contains both reservation and write-off subtraction, and that the preorder receives FIFO cost using method `FIFO (резерв через міграцію)`.

`open-cart-identity-filter.test.mjs` proves that identical repeated delivery is a no-op and that a safe existing-order quantity revision is synchronized rather than ignored.

## Local validation

- Complete `crm/apps-script/tests/*.test.mjs` suite: passed.
- `Code.gs` parsed with Node `new Function(...)`: passed.
- Dashboard inline script parsed with Node `new Function(...)`: passed.
- `git diff --check`: passed.
- Owner export `Версія 137, 20 серп. 2026 р., 1539` compared with local `Code.gs`: only the CRM-012 write-off formula follow-up differs.

## Owner-gated live recovery and QA

After the owner pastes and publishes the source, do not rerun the old migration: its ledger marker correctly keeps it idempotent.

1. In Google Sheets, run `Booster CRM → Оновити собівартість складу`. It is an intentional live write: it repairs formulas for migrated SKU and consumes already-recorded `WRT-0208` without adding a second write-off.
2. Read back `Склад!H` for `PKM-JP-ABYE-BST`. Expected balance is `28 − current CRM quantity of OC-FOP-0268 − 1 from WRT-0208`. If the order is still two packs, it is 25; if the later extra two packs were actually saved in CRM, it is 23. Do not use a guessed quantity.
3. In the normal dashboard editor, open `OC-FOP-0268`, add a harmless note such as `CRM-012 відновлення резерву`, and save. This invokes deferred-preorder cost backfill from the already-recorded migration ledger.
4. Read back `Продажі` price/cost/audit fields for `OC-FOP-0268`; verify FIFO cost is populated rather than a fallback value. Confirm neither the menu run nor the save duplicates a migration or write-off.
5. Only if OpenCart sends a real revised payload, verify that increasing an existing same-SKU order quantity updates the CRM sale row and warehouse reservation once.

## Risk and rollback

This touches CRM stock, FIFO cost, active preorders, and OpenCart synchronization. The new code fail-closes ambiguous existing orders and insufficient FIFO stock. No automatic live repair was performed. Roll back the source change before publication by restoring the prior Apps Script version; after publication, use the owner-controlled Apps Script version history and validate the named order and SKU before broader use.
