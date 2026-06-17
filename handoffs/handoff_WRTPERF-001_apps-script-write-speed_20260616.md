# WRTPERF-001 — Apps Script write performance (addSale / addWriteOff)

**Scope:** Google Apps Script only — no OpenCart files, no HTML changes, no Sheets structure changes.  
**Risk:** Medium — affects cost calculation on every sale. Test with 1-item and 3-item sale after patch.  
**Goal:** Cut `addSale()` wall-clock time by ~50–60% by eliminating redundant sheet reads.

---

## Root cause analysis

### What regressed

New auto-consumable functions (sticker/blind-box tracking) added recently read the entire `Продажі` sheet on **every item** of every sale, bypassing the `_memo.salesRows` cache:

| Function | Reads Продажі | Per item? | Uses memo? |
|---|---|---|---|
| `getConsumedQtyBeforeSale_()` | YES (cols 1–29) | YES | NO — direct `getRange` |
| `getExistingAutoConsumableAudit_()` | YES (cols 1–31) | YES | NO — direct `getRange` |
| `countAutoConsumableOrdersBefore_()` | YES (cols 1–24) | YES | NO — direct `getRange` |
| `getSoldQtyBySkuForLotStatuses_()` inside `updateSkuCurrentCost_()` | YES | once (end) | NO |

**For a 3-item sale:** Продажі is read 9+ times. Before the auto-consumable feature, it was read 1–2 times.

### Additional issue

`ensureSaleCostAuditColumns_(sales)` is called inside `fixSaleCostForRow_()`, which runs **per item**. It calls:
1. `sales.getRange(2, 30, ...)` to read headers — every time
2. `sales.getRange(2, 30, sales.getMaxRows() - 1, 3).clearDataValidations()` — **every time** (expensive Sheets API call)

This should run **once** before the items loop.

### keepWarm gap

`keepWarm()` only calls `_getCrmSs()` — it does NOT open the Automation spreadsheet (`_getAutoSs()`).  
The `summary` endpoint opens `_getAutoSs()` on every cold call → 1–2s cold-start penalty for the second spreadsheet.

---

## Changes — exact code patches

> **File location in Apps Script:** All changes are to the single `.gs` (or Code.gs) file of the Web App.  
> There is NO local file — these edits must be made directly in Google Apps Script editor.

---

### Change 1 — Move `ensureSaleCostAuditColumns_()` out of the per-item loop

**Where:** Function `addSale()`.  
Find the items loop. `ensureSaleCostAuditColumns_` is currently called inside `fixSaleCostForRow_()` which runs per item. We extract it to run once before the loop.

#### 1a. Remove `ensureSaleCostAuditColumns_` call from inside `fixSaleCostForRow_()`

**Find in `fixSaleCostForRow_()` (approx line 200+):**
```js
function fixSaleCostForRow_(ss, sales, rowIndex, skuId, qty, saleDate, runState, costRunState) {
  ensureSaleCostAuditColumns_(sales);
```

**Replace with:**
```js
function fixSaleCostForRow_(ss, sales, rowIndex, skuId, qty, saleDate, runState, costRunState) {
  // ensureSaleCostAuditColumns_ is called once before the items loop (not per item)
```

#### 1b. Add call once before the items loop in `addSale()`

**Find the items loop in `addSale()` — look for something like:**
```js
  for (let i = 0; i < items.length; i++) {
    const item = items[i];
```
or the equivalent `items.forEach(...)`.

**Add immediately BEFORE that loop:**
```js
  ensureSaleCostAuditColumns_(sales);
```

---

### Change 2 — Cache Продажі rows for `getConsumedQtyBeforeSale_()` and `countAutoConsumableOrdersBefore_()`

These two functions read the full Продажі sheet independently. They can safely use `_getCrmSalesRows()` (memo-cached) because:
- `getConsumedQtyBeforeSale_` already filters by `isActualSaleForCost_()` internally — same as memo
- `countAutoConsumableOrdersBefore_` skips `currentOrderKey` rows, so newly-written items of the current sale are excluded automatically

#### 2a. Fix `getConsumedQtyBeforeSale_()`

**Find (approx line 900–920):**
```js
function getConsumedQtyBeforeSale_(sales, skuId, currentRow, saleDate, runState) {
  const lastRow = sales.getLastRow();
  if (lastRow < 3) return runState[skuId] || 0;
  const data = sales.getRange(3, 1, lastRow - 2, 29).getValues();
```

**Replace:**
```js
function getConsumedQtyBeforeSale_(sales, skuId, currentRow, saleDate, runState) {
  const data = _getCrmSalesRows();  // memo-cached, not per-item sheet read
  if (!data || data.length === 0) return runState[skuId] || 0;
```

> **Note:** Adjust the column indices in the rest of the function if `_getCrmSalesRows()` returns columns 1–32 (0-indexed as 0–31) — check that `data[j][COL_INDEX]` references match. `_getCrmSalesRows()` returns `getRange(3, 1, ..., 32).getValues()` so column layout is the same.

#### 2b. Fix `countAutoConsumableOrdersBefore_()`

**Find (approx line 1096+):**
```js
function countAutoConsumableOrdersBefore_(sales, currentOrderKey, saleDate) {
  const lastRow = sales.getLastRow();
  if (lastRow < 3) return 0;
  const data = sales.getRange(3, 1, lastRow - 2, 24).getValues();
```

**Replace:**
```js
function countAutoConsumableOrdersBefore_(sales, currentOrderKey, saleDate) {
  const data = _getCrmSalesRows();  // memo-cached
  if (!data || data.length === 0) return 0;
```

> **Note:** `_getCrmSalesRows()` returns 32 columns; this function only reads up to col 24. The extra columns are simply unused — safe.

---

### Change 3 — Fix `getExistingAutoConsumableAudit_()` (partial cache)

This function **cannot** use pre-mutation cache because for multi-item sales it needs to see items written in the current `addSale()` call. Keep the direct sheet read — but pre-read it ONCE in `addSale()` and pass as parameter.

#### 3a. Add `_memo.autoConsumableAuditRows` cleared per write cycle

**Find `resetMemoForMutation_()` (approx line 80–90):**
```js
function resetMemoForMutation_() {
  _memo.salesRows = null;
  _memo.cacheVersion = null;
}
```

**Replace:**
```js
function resetMemoForMutation_() {
  _memo.salesRows = null;
  _memo.cacheVersion = null;
  _memo.auditRows = null;       // cleared each write; re-populated lazily in getExistingAutoConsumableAudit_
}
```

#### 3b. Replace direct read in `getExistingAutoConsumableAudit_()`

**Find (approx line 1039):**
```js
function getExistingAutoConsumableAudit_(sales, currentOrderKey, currentRowIndex) {
  const lastRow = sales.getLastRow();
  if (lastRow < 3) return { sticker: false, blind: false };
  const data = sales.getRange(3, 1, lastRow - 2, 31).getValues();
```

**Replace:**
```js
function getExistingAutoConsumableAudit_(sales, currentOrderKey, currentRowIndex) {
  // Lazy-read per addSale() run; NOT pre-mutation (needs rows written earlier in same call)
  if (!_memo.auditRows) {
    const lastRow = sales.getLastRow();
    if (lastRow < 3) return { sticker: false, blind: false };
    _memo.auditRows = sales.getRange(3, 1, lastRow - 2, 31).getValues();
  }
  const data = _memo.auditRows;
```

> This still reads fresh from the sheet on the **first item** of each sale, then caches. For a 3-item sale this reduces 3 full reads → 1 read. The first item's newly-written row won't be in the cache until the next sale's first item (which is correct — `getExistingAutoConsumableAudit_` only needs to see PRIOR items of the same order, not the current one).

> **IMPORTANT:** `resetMemoForMutation_()` clears `_memo.auditRows = null` at the start of `addSale()` (via Change 3a), so the cache is always fresh per sale call, never stale across sales.

---

### Change 4 — Fix `keepWarm()` to warm the Automation spreadsheet

**Find (approx line 702):**
```js
function keepWarm() { _getCrmSs(); }
```

**Replace:**
```js
function keepWarm() {
  _getCrmSs();
  try { _getAutoSs(); } catch(e) { /* non-fatal */ }
}
```

This ensures the `summary` endpoint doesn't pay a cold-start penalty on the second spreadsheet on every dashboard load.

---

## What NOT to change

- `addWriteOff()` — same issue exists there but lower priority; handle in a separate task
- `updateSkuCurrentCost_()` — do NOT touch; runs once at end of `addSale()`, already correct
- `calculateFifoSaleCost_()` — do NOT touch; relies on `runState` for correctness across items
- Any column or formula logic in Продажі, Склад, Закупки — NO structure changes

---

## Acceptance criteria

- [ ] `addSale()` with 1-item sale completes in < 8s (was 12–15s)
- [ ] `addSale()` with 3-item sale completes in < 15s (was 25–35s)
- [ ] Cost audit columns (cols AD–AF) still populated correctly after patch
- [ ] Auto-consumable (sticker/blind-box) costs still applied exactly once per order (not duplicated across items)
- [ ] `ensureSaleCostAuditColumns_()` still adds columns if missing (test on fresh sheet)
- [ ] `keepWarm()` trigger: no new errors in Executions log

---

## QA checklist

- [ ] Open Apps Script → Executions → find a recent `addSale` → note wall-clock time before patch
- [ ] Apply changes
- [ ] Save and Deploy → Update (same Web App URL, new version)
- [ ] Add a test sale (1 item) via dashboard → confirm cost columns populated → measure time in Executions
- [ ] Add a test sale (3 items) via dashboard → confirm each item has individual cost + auto-consumable not doubled
- [ ] Check keepWarm in Executions log → 0 new errors

---

## Risks

| Risk | Severity | Mitigation |
|---|---|---|
| `_getCrmSalesRows()` column count differs from direct read | Medium | Both return `getRange(3,1,...,32)` — verify col indices match |
| `_memo.auditRows` not cleared if `addSale()` fails mid-loop | Low | `resetMemoForMutation_()` is called at START of `addSale()`, so next call is always fresh |
| `ensureSaleCostAuditColumns_` call removed from `fixSaleCostForRow_` but called from addSale — addWriteOff still calls fixSaleCostForRow_ | Low | `addWriteOff()` must also call `ensureSaleCostAuditColumns_(sales)` once before its loop — verify and add if missing |

---

## Commit

After verifying in Apps Script editor (no local file):
```
git commit -m "handoff: WRTPERF-001 Apps Script write speed"
```
No FTP deploy needed (Apps Script is deployed via Web App versioning in Google).
