# Codex Handoff A — Apps Script: updateSkuCurrentCost_

## Context

The CRM's `fixSaleCostForRow_` correctly fixes FIFO cost at the moment of sale recording.
Audit column AE in Продажі confirms this is working: `FIFO,before=30; LOT-0057: 6 x 67.01; LOT-0060: 14 x 39.20`.

**Problem**: Склад sheet columns I (Середня собівартість / ПРРО) and J (Управлінська собівартість)
are calculated by formula as a weighted average across ALL lots — including fully-sold ones.
When cheap lots sell out and only expensive lots remain, Склад shows a stale lower average.
This propagates to `Черга_Складу` in the Automation sheet and to `stock_alerts` API,
resulting in incorrect margin % in the dashboard.

---

## Task

Add function `updateSkuCurrentCost_(ss)` to Apps Script (CRM sheet code).
Call it at the end of `addSale()`, `addWriteOff()`, and `updateLotStatuses()`.

---

## Algorithm

For each SKU present in Склад sheet:

1. Get ALL lots for this SKU from Закупки sheet with status in:
   `['На складі', 'Частково продано', 'Продано']`
   Sort by delivery date (Закупки col[3]) ascending — oldest first (FIFO order).
   Lots without delivery date should be treated as oldest (sort key = 0), but log a warning.

2. Calculate `totalConsumed`:
   Sum of all sold units for this SKU (from Продажі, same logic as `getSoldQtyBySkuForLotStatuses_`)
   PLUS sum of all written-off units for this SKU (from Списання sheet if exists, otherwise 0).

3. Walk lots FIFO to find remaining per lot:
   ```
   remaining = totalConsumed
   for each lot (sorted by delivery date asc):
     lotConsumed = min(lot.qty, remaining)
     remaining  = max(0, remaining - lotConsumed)
     lotRemaining = lot.qty - lotConsumed
     if lotRemaining > 0:
       accumulate: totalRemainingQty += lotRemaining
                   weightedPrro += lotRemaining * lot.prroUnitCost
                   weightedMgmt += lotRemaining * lot.mgmtUnitCost
   ```

4. Write results to Склад sheet for this SKU row:
   - If `totalRemainingQty > 0`:
     `col I = weightedPrro / totalRemainingQty`
     `col J = weightedMgmt / totalRemainingQty`
   - If `totalRemainingQty == 0`:
     leave cols I:J unchanged (don't write zeros — formula fallback is better)

---

## Column references (verify against actual sheet before coding)

**Закупки sheet** (from existing `getFifoCostBatches_`):
- col[3]  = Дата доставки в Україну
- col[4]  = Кількість штук (batch qty)
- col[5]  = Собівартість ПРРО / шт
- col[6]  = Управлінська собівартість / шт
- col[7]  = SKU
- col[12] = Статус (На складі / Частково продано / Продано)

**Склад sheet**:
- col A (0) = SKU — used to match rows
- col I (8) = Середня собівартість / ПРРО — **WRITE HERE**
- col J (9) = Управлінська собівартість — **WRITE HERE**

Verify these column indices from the actual sheet before writing. Do not hard-code if the sheet layout differs.

---

## Performance notes

- Cache Закупки and Продажі reads once before the loop (batch `getValues()`).
- Use `setValues()` on a prepared 2D array rather than individual `setValue()` calls.
- Log how many SKUs were updated and how many were skipped (no lots found).

---

## Call sites

Add to end of:
```javascript
function addSale(...) {
  // ... existing code ...
  updateSkuCurrentCost_(ss);
}

function addWriteOff(...) {
  // ... existing code ...
  updateSkuCurrentCost_(ss);
}

function updateLotStatuses() {
  // ... existing code ...
  updateSkuCurrentCost_(SpreadsheetApp.getActiveSpreadsheet());
}
```

Also add a standalone menu item:
```javascript
// In onOpen() or createMenuItems():
ui.createMenu('...').addItem('Оновити поточну собівартість', 'updateSkuCurrentCostMenu');

function updateSkuCurrentCostMenu() {
  updateSkuCurrentCost_(SpreadsheetApp.getActiveSpreadsheet());
  SpreadsheetApp.getUi().alert('Собівартість оновлено.');
}
```

---

## Known secondary bug to fix in getFifoCostBatches_

Current filter:
```javascript
if (saleSort && batchSort && batchSort > saleSort) return;
```

If a lot has no delivery date (`batchSort = 0`, which is falsy), the condition short-circuits
and the lot is included in ALL sales' FIFO regardless of date.

Fix:
```javascript
// Exclude lots with no delivery date from future sales if another lot with date exists
// Simple fix: treat batchSort=0 as "arrived at beginning of time" (already-there stock)
// This is acceptable for current data (only LOT-0019 affected, 0 sales)
// No change needed immediately — low risk, document as known issue
```

→ Leave as-is for now. Revisit if a lot without delivery date causes FIFO errors.

---

## Acceptance criteria

1. After `updateSkuCurrentCost_()` runs, Склад!I and J for PKM-JP-MZERO-BST
   shows weighted average of REMAINING lots only (not all lots).
2. For SKUs with 0 stock, columns I:J are unchanged (not zeroed).
3. Function completes in < 10 seconds for current dataset (~30 SKUs, ~80 lots).
4. `addSale()` logs include "Собівартість оновлено" or similar after the function runs.
5. Menu item "Оновити поточну собівартість" works without errors.

---

## QA checklist (owner to verify manually)

- [ ] Run "Оновити поточну собівартість" from menu
- [ ] Check PKM-JP-MZERO-BST: Склад!I should be ≈ weighted avg of LOT-0032 and LOT-0035 remaining units
- [ ] Check SKU with 0 stock (e.g. PKM-JP-MBRV-BST): Склад!I unchanged
- [ ] Add a test sale, confirm `addSale()` still runs FIFO correctly AND updates Склад cost after
- [ ] Confirm Черга_Складу in Automation sheet picks up the new cost (may need manual refresh of automation)
- [ ] Compare margin % in dashboard stock_alerts before and after — should change for at least one SKU

---

## Risks

- **Automation spreadsheet formulas**: If `Черга_Складу` in the Master Dashboard reads Склад via IMPORTRANGE or formula,
  the updated values will propagate automatically. If it's a hardcoded formula that recalculates independently,
  the fix to Склад!I:J will still be correct — the automation sheet will just need its own formula fixed separately.
  Check this BEFORE deploying: does Черга_Складу have an IMPORTRANGE or its own formula for margin?

- **Row matching**: If Склад sheet has blank rows or headers that don't match Закупки SKU column,
  the row lookup could silently skip rows. Add a log for every SKU not found in Закупки.
