# Actual manufactured batch costing contract

Date: 2026-09-02
State: design and acceptance criteria; NOT an implemented or deployed FIFO engine.
Authority: owner's explicit choice of actual manufactured batch FIFO, with Serhiy
consumables included once in production cost and owner consumables kept separate.

Owner decision 2026-09-06: cancellation/return preserves the original sale or
gift and appends a separate negative business row. It restores the exact original
batch quantities at their frozen costs, must be idempotent, and must reject a
second reversal of the same allocation.

The implementation must expose an owner-only read reconciliation report before
any repair. It compares committed consume/reversal breakdowns with batch
allocated/available counters and the linked positive/negative sale or gift row.
Detected drift is a blocker; repair must be a separate guarded operation rather
than an automatic side effect of reading the report.

Owner decision 2026-09-06: guarded repair may change only batch
`allocated_qty`/`available_qty` counters derived exactly from the immutable
allocation ledger. It requires the fresh reconciliation fingerprint and a
reason, verifies the full ledger after writing, and rolls back on failure. A
missing or inconsistent sale/gift projection is not fabricated and blocks the
repair for scoped recovery.

Owner decision 2026-09-06: a reversed 3D sale cannot be reactivated under its
old operation ID. If it becomes a sale again, CRM must create a new operation
that performs a fresh FIFO allocation against then-available batches.

The CRM implementation routes either payment or order status `Скасовано` /
`Повернення` to the stable reversal operation. A later attempt to reactivate the
same CRM row is forced back to a terminal status and reported as a sync failure;
3D-P also rejects the old allocation independently. A legitimate renewed sale
must therefore be a new CRM operation and cannot silently reuse returned stock.

## Confirmed catalogue decisions

- Import 72 products, 62 active and 10 inactive after the owner status decision of 2026-09-05. Exclude both three-piece keychain
  sets (draft Брелоки rows 3 and 13).
- Nami L / proposed FIG-NAMI-201: RRP 750 UAH, buyout 500 UAH. Preserve the source
  question marks in the evidence and record the owner override separately.
- Draft single-print D and batch-unit N are planning estimates. Neither is an
  opening-stock cost or an existing manufactured batch. Opening stock is zero.
- Ordinary sale pays actual production cost plus 50% of net profit to Serhiy.
  Marketing buyout pays the agreed buyout amount; production FIFO still tracks
  which physical units left inventory but does not replace the agreed buyout.

## Verified current defects

| Path | Current behavior | Consequence |
|---|---|---|
| 3DP `manufactureBatchAction3dp_` | Appends quantities, actual weight and time, but no frozen material price, rates, additional Serhiy costs or total cost | The manufactured lot has no immutable valuation |
| Live Друк-лог G2:G15 | Every cell has a formula. G2 = `C2 * INDEX(Номенклатура K, SKU)` with blank/error guards | Changing K revalues already manufactured batches; actual logged time/weight do not determine G |
| CRM `crm3dpFrozenSaleInputs_` | Reads current nomenclature production cost | New sales do not select actual remaining batches |
| CRM `sync3dpSalesV2_` | Reads current production cost before checking an existing remote sale; uses it in a new accounting snapshot | Retry/recalculation can disagree with the remote sale's unchanged frozen F |
| CRM `crm3dpEnsureStock_` | Sale stock decrement is a separate remote call after sale append | A failure can leave a sale with no stock movement; FIFO needs an idempotent operation journal |
| 3DP print-log edit/archive and stock adjustment | Can change quantities independently; automatic sale permits negative stock | Existing routes must participate in FIFO; a new calculator alone cannot fix inventory accounting |
| Serhiy manufacturing form → local server | Sends actual weight/time, but omits the calculator's spool price/weight and extra consumable cost | The API cannot freeze the actual batch inputs from this payload |

The main CRM FIFO orders delivered purchase lots by date then row, skips consumed
quantities, and allocates across remaining lots (`calculateFifoSaleCost_`,
`getFifoCostBatches_`). Its fallback to current catalogue cost when stock is short
cannot establish actual manufactured cost. Preserve unrelated TCG behavior; the
3D path must expose missing actual stock/cost rather than label a fallback FIFO.

## Required implementation invariants

1. Manufacturing produces a stable batch ID and a frozen snapshot: SKU, effective
   manufacturing date/time, printed units, defects, good units, actual material
   weight, material unit price, actual time, energy/depreciation rates and Serhiy
   extra consumables for the whole batch. Save the resulting actual total cost.
   Later edits to catalogue/calculator/settings must not revalue this batch.
2. Treat defects as actual production loss. For a partly usable batch, allocate
   its actual total production cost across good units; do not also apply the
   forecast defect percentage. A fully defective batch has zero available units
   and separately recorded loss, never a division by zero or usable FIFO layer.
   This is the proposed implementation rule, not an independently confirmed
   existing policy; expose it in the manufacturing preview before adoption.
3. Allocate oldest available actual batches first, with stable ID/sequence as a
   tie-breaker. Keep exact batch quantities and aggregate cost in the allocation.
   Round monetary totals at defined boundaries; retain enough unit precision that
   dividing and multiplying a mixed-batch average does not lose cents.
4. Sale, marketing gift, write-off, negative stock correction and reservation
   share the same available quantities. A reserved sale must not consume them
   a second time when shipped or marked paid.
5. Cancellation/return reverses the original allocations at their original costs.
   Corrections to quantity/SKU explicitly reverse/reallocate the affected units.
   Retrying the same operation returns the persisted result. Reusing an operation
   ID with a changed payload fails; it does not silently replay a different batch.
6. A sale spanning batches freezes the aggregate cost and the allocation IDs in
   both systems' accounting references. Resynchronizing must reuse that result.
   Never recompute an existing sale's cost from current K or current settings.
7. A consumed batch cannot be silently edited or archived. Use a versioned
   correction with reconciliation, or reject the edit with an actionable reason.
   Positive stock corrections also need an actual cost/provenance; a bare count
   must not manufacture costed inventory. Costless stock is an explicit unresolved
   state and must block final cost posting until reconciled.
8. Serhiy extra consumables enter the batch exactly once. Prevent selecting those
   same costs again through the separate Serhiy-payer fixture reimbursement path.
   Owner purchases remain in CRM's name+payer fixture ledger and margin deduction.
9. Keep formula columns formula-based. Any new frozen amount is stored in new
   declared manual ledger fields; existing report formulas reference those fields.
   Do not overwrite Номенклатура K or Друк-лог G with a literal.
10. One script lock covers each 3D inventory commit. Persist an operation journal
    for partial failures between CRM and 3D; Sheets changes in two projects are
    not globally atomic. Reconciliation must finish missing projections without
    duplicating lots, stock movements, payouts or fixture usage.
11. Activate the new cost model only after an exact reset/import preflight,
    verified backups and consistent empty business ledgers. Historical test
    records must not seed new FIFO lots. Keep the API audit trail as provenance.

## Acceptance examples and gates

| Scenario | Expected result |
|---|---|
| 2 older units cost 160 total, 3 newer units cost 300 total; sell 3 | Consume 2 older + 1 newer. Cost 260, remainder 2 newer units costing 200 |
| Change catalogue K after that sale | Saved sale cost remains 260; remaining lot cost remains 200 |
| Retry manufacturing or sale with unchanged operation ID | Same result; no additional stock or payout effect |
| Retry with changed quantity under the same operation ID | Conflict, no additional write |
| Return the first sale's three units | Restore its two original lot allocations at cost 260 |
| Sell without a sufficient costed batch | No fabricated zero/current-catalogue cost; explicit insufficient-costed-stock result |
| Sale revenue 600, FIFO cost 260, owner fixtures 30, packaging 15 | Serhiy receives 260 + (600 - 260 - 30 - 15) / 2 = 407.50 |
| Marketing 3 units at buyout 150, owner fixtures 30 | Serhiy receives 450; management cost 480; same physical FIFO units are removed |
| Source says max batch size 10 or test print completed | Import creates no manufactured stock |

The next missing evidence is the owner-run read-only preflight in
`scripts/3dp-catalog-reset/CatalogResetPreflight.gs`: actual validations, occupied
analytics rows below A4:N17, internal journal coverage, and the canonical CRM
integrity baseline. Current local credentials provide only Serhiy's 3D view.
Do not infer hidden schema or deploy a partial cost change while these gates are
open. Local source inspection and numerical examples are not live FIFO proof.
