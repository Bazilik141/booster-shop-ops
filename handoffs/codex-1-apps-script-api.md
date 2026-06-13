# Codex Handoff #1 — Apps Script: FIFO fixes + API extension to 7 endpoints

Supersedes: codex-handoff-A-fifo-current-cost.md

All changes are in the Apps Script code attached to the CRM Google Sheet.
Do NOT modify doPost (reserved for order-sync). Do NOT change existing endpoint contracts —
only extend responses with new fields.

---

## Part A: FIFO bug fix — lots without delivery date

### File: Apps Script (CRM sheet)
### Function: `getFifoCostBatches_`

**Current code (problematic):**
```javascript
if (saleSort && batchSort && batchSort > saleSort) return;
```

**Problem:** When `batchSort = 0` (lot has no delivery date in col[3]), the short-circuit
`batchSort && ...` makes the whole condition false, so the lot is included in ALL sales
regardless of date. Risk: future lots with missing dates would incorrectly appear
as cost sources for old sales.

**Fix:**
```javascript
// If lot has no delivery date, include it (treat as arrived at time zero)
// but ONLY if it has status 'На складі' or 'Частково продано' (already filtered by allowed statuses)
// If sale has a date and lot has a date, exclude future lots:
if (saleSort && batchSort && batchSort > saleSort) return;
// No change to logic — add a Logger warning only:
if (!batchSort) {
  Logger.log('FIFO warning: lot ' + row[0] + ' (' + row[7] + ') has no delivery date — included as earliest');
}
```

This is a conservative fix: log and monitor, don't change behavior. The actual data risk
is low (only LOT-0019 affected, 0 sales for that SKU). Revisit if a real collision occurs.

---

## Part B: updateSkuCurrentCost_ — correct weighted average of remaining lots

### Add new function

The Склад sheet columns I (Середня собівартість / ПРРО) and J (Управлінська собівартість)
are calculated by formula as weighted average across ALL lots, including fully-sold ones.
This function replaces stale formula values with real weighted averages of remaining stock.

```javascript
/**
 * Recalculates Sklаd!I and J for each SKU based on remaining lots (FIFO walk).
 * Call after addSale(), addWriteOff(), updateLotStatuses().
 */
function updateSkuCurrentCost_(ss) {
  if (!ss) ss = SpreadsheetApp.getActiveSpreadsheet();

  // 1. Read Закупки: all lots with relevant statuses
  const lotsSheet = ss.getSheetByName('Закупки');
  const lotsData = lotsSheet.getDataRange().getValues();
  // Verify column indices before use — adjust if sheet layout differs:
  const L_ROW_ID   = 0;  // col A: lot ID
  const L_DATE     = 3;  // col D: Дата доставки в Україну
  const L_QTY      = 4;  // col E: Кількість штук
  const L_PRRO     = 5;  // col F: Собівартість ПРРО / шт
  const L_MGMT     = 6;  // col G: Управлінська собівартість / шт
  const L_SKU      = 7;  // col H: SKU
  const L_STATUS   = 12; // col M: Статус
  const ALLOWED = { 'На складі': 1, 'Частково продано': 1, 'Продано': 1 };

  // 2. Read Продажі: aggregate sold qty per SKU (actual orders only)
  //    Reuse the same filter as getSoldQtyBySkuForLotStatuses_
  const soldBySku = getSoldQtyBySkuForUpdateCost_(ss);

  // 3. Read Списання if sheet exists: aggregate write-off qty per SKU
  const writeOffBySku = getWriteOffQtyBySkuForUpdateCost_(ss);

  // 4. Read Склад: find SKU → row mapping
  const skladSheet = ss.getSheetByName('Склад');
  const skladData = skladSheet.getDataRange().getValues();
  const S_SKU    = 0;  // col A: SKU
  const S_PRRO   = 8;  // col I: Середня собівартість / ПРРО  ← WRITE
  const S_MGMT   = 9;  // col J: Управлінська собівартість    ← WRITE

  // 5. Build lot index by SKU
  const lotsBySku = {};
  for (let i = 1; i < lotsData.length; i++) {
    const row = lotsData[i];
    const sku = row[L_SKU];
    const status = row[L_STATUS];
    if (!sku || !ALLOWED[status]) continue;
    if (!lotsBySku[sku]) lotsBySku[sku] = [];
    lotsBySku[sku].push({
      id:     row[L_ROW_ID],
      date:   toSortable_(row[L_DATE]),
      qty:    Number(row[L_QTY]) || 0,
      prro:   Number(row[L_PRRO]) || 0,
      mgmt:   Number(row[L_MGMT]) || 0,
    });
  }

  // Sort each SKU's lots by delivery date ascending (FIFO)
  for (const sku in lotsBySku) {
    lotsBySku[sku].sort((a, b) => a.date - b.date);
  }

  // 6. For each SKU in Склад, FIFO-walk to find remaining lots and weighted avg cost
  const updates = []; // {row: sklаdRowIndex, prro, mgmt}

  for (let i = 1; i < skladData.length; i++) {
    const sku = skladData[i][S_SKU];
    if (!sku) continue;

    const lots = lotsBySku[sku];
    if (!lots || !lots.length) continue;

    const totalConsumed = (soldBySku[sku] || 0) + (writeOffBySku[sku] || 0);

    let remaining = totalConsumed;
    let totalRemainingQty = 0;
    let weightedPrro = 0;
    let weightedMgmt = 0;

    for (const lot of lots) {
      const consumed = Math.min(lot.qty, remaining);
      remaining = Math.max(0, remaining - consumed);
      const inLot = lot.qty - consumed;
      if (inLot > 0) {
        totalRemainingQty += inLot;
        weightedPrro += inLot * lot.prro;
        weightedMgmt += inLot * lot.mgmt;
      }
    }

    if (totalRemainingQty > 0) {
      updates.push({
        row:  i + 1, // 1-indexed sheet row
        prro: weightedPrro / totalRemainingQty,
        mgmt: weightedMgmt / totalRemainingQty,
      });
    }
    // If 0 remaining: leave I:J as-is (formula fallback is better than 0)
  }

  // 7. Batch write (minimize API calls)
  for (const u of updates) {
    skladSheet.getRange(u.row, S_PRRO + 1).setValue(u.prro);
    skladSheet.getRange(u.row, S_MGMT + 1).setValue(u.mgmt);
  }

  Logger.log('updateSkuCurrentCost_: updated ' + updates.length + ' SKUs');
}

// Helper: total sold qty per SKU from Продажі (same filter as lot status update)
function getSoldQtyBySkuForUpdateCost_(ss) {
  // Reuse or inline the logic from getSoldQtyBySkuForLotStatuses_
  // Filter: order status in ['Отримано', 'Відправлено'] AND payment in ['Оплачено', 'Передоплачено']
  // Sum col for qty by SKU
  // Return: { 'PKM-JP-MZERO-BST': 22, ... }
  return getSoldQtyBySkuForLotStatuses_(ss); // reuse existing function if signature matches
}

// Helper: write-off qty per SKU from Списання sheet (if exists)
function getWriteOffQtyBySkuForUpdateCost_(ss) {
  const sheet = ss.getSheetByName('Списання');
  if (!sheet) return {};
  const data = sheet.getDataRange().getValues();
  const result = {};
  for (let i = 1; i < data.length; i++) {
    const sku = data[i][/* SKU col — verify */1];
    const qty = Number(data[i][/* qty col — verify */2]) || 0;
    if (sku) result[sku] = (result[sku] || 0) + qty;
  }
  return result;
}
```

**Helper `toSortable_`:** reuse the existing `toSortable` or `dateSortKey` function already in the codebase.

### Call sites — add at end of:
```javascript
function addSale(...) { ...; updateSkuCurrentCost_(ss); }
function addWriteOff(...) { ...; updateSkuCurrentCost_(ss); }
function updateLotStatuses() { ...; updateSkuCurrentCost_(SpreadsheetApp.getActiveSpreadsheet()); }
```

### Menu item:
```javascript
// In onOpen() menu builder:
.addItem('Оновити поточну собівартість', 'updateSkuCurrentCostMenu')

function updateSkuCurrentCostMenu() {
  updateSkuCurrentCost_(SpreadsheetApp.getActiveSpreadsheet());
  SpreadsheetApp.getUi().alert('Собівартість складу оновлено.');
}
```

---

## Part C: API extensions (doGet)

### Summary of endpoint changes

| Endpoint | Status | Change |
|---|---|---|
| `summary` | Modified | + `sales_prev_month`, `potential_profit_warehouse` |
| `orders` | Unchanged | — |
| `stock_alerts` | Unchanged | — |
| `sku_list` | Extended | + optional `sort`, `limit` params |
| `channel_stats` | **New** | Channel revenue/profit breakdown |
| `monthly_summary` | **New** | Last N months trend + repeat rate |
| `ltv_report` | **New** | Top N clients by total spend |

Total: 4 existing → 7 endpoints.

---

### C1: Extend `summary` response

In `apiSummary_()`, after reading 'Поточний місяць' row, also read 'Попередній місяць' row
from `Звіт_Продажів` sheet in the Automation spreadsheet.

Add to returned object:
```json
"sales_prev_month": {
  "revenue":    43242.50,
  "profit":     15267.21,
  "margin_pct": 35.3,
  "orders":     42
}
```
If row not found: `"sales_prev_month": null`.

Also read `Потенційний прибуток зі складу` from the Дашборд sheet (it already exists there)
and add:
```json
"potential_profit_warehouse": 28622.89
```

---

### C2: New endpoint `channel_stats`

**Trigger:** `action=channel_stats` (optional param `period=current_month|all_time`, default `current_month`)

**Source:** Продажі sheet. Group by source/channel column. Filter same as summary (actual orders).

**Response:**
```json
{
  "ok": true,
  "period": "current_month",
  "channels": [
    { "name": "OpenCart", "revenue": 27615.75, "profit": 9200.00, "margin_pct": 33.3, "orders": 45, "units": 120 },
    { "name": "OLX",      "revenue": 54170.00, "profit": 18000.00, "margin_pct": 33.2, "orders": 120, "units": 300 },
    { "name": "Telegram", "revenue": 8580.00,  "profit": 2800.00,  "margin_pct": 32.6, "orders": 15,  "units": 40  },
    { "name": "Monobazar","revenue": 1550.00,  "profit": 500.00,   "margin_pct": 32.3, "orders": 3,   "units": 8   }
  ]
}
```

Channels sorted by revenue descending. If column for channel is not explicit in Продажі,
use the same source column that's already used for `source` field in `orders` endpoint.

---

### C3: New endpoint `monthly_summary`

**Trigger:** `action=monthly_summary` (optional param `months=6`, default 6)

**Source:** Aggregate Продажі by month (calendar month). Last N months including current.
OR read from `Звіт_Продажів` if it already has month-by-month rows.

**Response:**
```json
{
  "ok": true,
  "months": [
    { "month": "2026-01", "label": "Січень", "revenue": 45000, "profit": 15000, "margin_pct": 33.3, "orders": 50 },
    { "month": "2026-02", "label": "Лютий",  "revenue": 38000, "profit": 12000, "margin_pct": 31.6, "orders": 42 },
    { "month": "2026-03", "label": "Березень","revenue": 52000,"profit": 17000, "margin_pct": 32.7, "orders": 58 },
    { "month": "2026-04", "label": "Квітень", "revenue": 61000, "profit": 21000, "margin_pct": 34.4, "orders": 67 },
    { "month": "2026-05", "label": "Травень", "revenue": 43242, "profit": 15267, "margin_pct": 35.3, "orders": 42 },
    { "month": "2026-06", "label": "Червень", "revenue": 8273,  "profit": 1308,  "margin_pct": 15.8, "orders": 7  }
  ],
  "repeat_rate_pct": 12.5
}
```

`repeat_rate_pct`: percentage of customers who appear in more than 1 order
(across all time, not filtered by month). See Task 10 notes below.

Months ordered oldest → newest.

---

### C4: New endpoint `ltv_report`

**Trigger:** `action=ltv_report` (optional param `limit=10`, default 10)

**Source:** Продажі sheet. Group by customer identifier (phone number column, or name if phone absent).
Filter: only actual paid/received orders.

**Response:**
```json
{
  "ok": true,
  "limit": 10,
  "clients": [
    { "identifier": "+38050...", "display": "+38050···4321", "orders": 5, "units": 18, "revenue": 5200.00, "ltv": 5200.00 },
    { "identifier": "+38067...", "display": "+38067···8876", "orders": 3, "units": 9,  "revenue": 3100.00, "ltv": 3100.00 }
  ]
}
```

`display`: masked phone — show first 5 and last 4 digits only: `+38050···4321`.
If no phone column exists in Продажі, use name column and mask last 2 chars.
Sorted by `ltv` descending. Limit to top N.

**Important:** Do not expose full phone numbers in API response.

---

### C5: Extend `sku_list` with sort/limit params

Existing `sku_list` endpoint returns all SKUs. Add optional params:
- `sort=profit` → sort by net profit descending (profit = sold_30d * (price - cost))
- `limit=N` → return only top N rows

This covers Task 9 (Top-5 SKU by profit) without a new endpoint.
The dashboard will call: `action=sku_list&sort=profit&limit=5`.

If profit data is not available per SKU in the current sku_list response,
add `profit_30d` field to each SKU object (calculated as `sold_30d * margin_per_unit`).

---

## Acceptance criteria

- [ ] `apiSummary_` returns `sales_prev_month` object (not null) for any month after the first
- [ ] `apiSummary_` returns `potential_profit_warehouse` matching value in Дашборд sheet
- [ ] `action=channel_stats` returns 4 channels with revenue, profit, margin_pct, orders
- [ ] `action=monthly_summary&months=6` returns 6 month objects oldest→newest
- [ ] `action=monthly_summary` includes `repeat_rate_pct` (can be approximate, must be > 0)
- [ ] `action=ltv_report&limit=10` returns ≤10 clients, phones masked
- [ ] `action=sku_list&sort=profit&limit=5` returns 5 SKUs sorted by profit desc
- [ ] All new endpoints protected by TOKEN auth (same as existing)
- [ ] All new endpoints respond in < 5 seconds
- [ ] `updateSkuCurrentCost_()` runs after addSale without error
- [ ] FIFO bug: Logger shows warning for lots without delivery date

## QA checklist (owner)

- [ ] Call `?action=channel_stats&token=...` — verify 4 channels and totals match CRM Дашборд
- [ ] Call `?action=monthly_summary&months=6&token=...` — verify current month matches summary
- [ ] Call `?action=ltv_report&limit=5&token=...` — verify phones are masked
- [ ] After adding a test sale, check Склад!I:J changed for that SKU
- [ ] Menu "Оновити поточну собівартість" runs without error

## Risks

- **Продажі customer column**: if no phone/name column exists for LTV grouping,
  `ltv_report` cannot be built. Codex must inspect Продажі column layout first.
- **Automation sheet access**: if `Звіт_Продажів` is in a separate spreadsheet (Automation),
  Apps Script needs `SpreadsheetApp.openById(AUTOMATION_ID)` — same pattern as existing code.
- **updateSkuCurrentCost_ performance**: for 30 SKUs and 80 lots this should be < 5s.
  If it exceeds quota limits, switch to batch setValues() call on a prepared array.
- **Склад column indices**: verify L_STATUS = col M (index 12) before deploying.
  If layout differs, all cost writes will be to wrong columns.
