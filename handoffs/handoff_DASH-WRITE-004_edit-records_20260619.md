# DASH-WRITE-004 — Edit & View Recent Records in Accounting Tab

Date: 2026-06-19
Status: ready for Codex

---

## 1. Task ID

DASH-WRITE-004

---

## 2. Context

Dashboard file: `dashboard/booster-dashboard.html` (~1975 lines, verified syntax-clean).

Apps Script web app: deployed at the URL stored in `const API` (line ~489 of the dashboard).
Mirror: `Apps_Script_код` sheet in CRM spreadsheet.

Existing write API (DASH-WRITE-002, already deployed):
- `doPost` routes `add_sale`, `add_purchase`, `add_writeoff`
- Mirror locations: `doPost` at A751, `apiAddSale_` at A804, `apiAddPurchase_` at A845, `apiAddWriteOff_` at A874

Existing read API (already deployed):
- `action=orders` — returns Продажі rows
- `action=summary` — aggregate only, no row-level data

The accounting tab (`#page-accounting`, rendered by `loadAccounting()` at line ~1820) currently shows only write forms. There is no way to view or edit past records from the dashboard.

---

## 3. Goal

**Part A — Apps Script:** Add three new GET endpoints to `doGet`:

| action | returns |
|---|---|
| `recent_sales` | last N rows from Продажі |
| `recent_purchases` | last N rows from Закупки |
| `recent_writeoffs` | last N rows from Списання |

Accept optional param `limit` (default 20, max 50).

Each row must return enough fields to display and pre-fill an edit form:
- Продажі: `row_index`, `date`, `channel`, `order_id`, `sku`, `qty`, `price`, `note`
- Закупки: `row_index`, `date`, `order_ref`, `supplier`, `sku`, `qty`, `cost_prro`, `cost_mgmt`, `status`, `note`
- Списання: `row_index`, `date`, `sku`, `qty`, `reason`, `note`

`row_index` = 1-based row number in the sheet (used for targeted update).

**Part B — Apps Script:** Add three new POST actions to `doPost`:

| action | writes |
|---|---|
| `update_sale` | overwrites specific fields in a Продажі row |
| `update_purchase` | overwrites specific fields in a Закупки row |
| `update_writeoff` | overwrites specific fields in a Списання row |

Payload must include `row_index` + fields to update. Only the listed fields are written; other columns are left untouched.

**Part C — dashboard/booster-dashboard.html:** In the accounting tab, add a "Recent records" section below the write forms. Three sub-tabs (Продажі / Закупки / Списання). Each shows a compact table of the last 20 records. Clicking a row opens an inline edit form pre-filled with that row's data. Saving calls `update_sale` / `update_purchase` / `update_writeoff` via `callPost`.

---

## 4. What to change

### Apps Script (CRM spreadsheet mirror: `Apps_Script_код`)

**A. `doGet(e)` routing — add after existing action checks:**

```
if (action === 'recent_sales')     return apiRecentSales_(params);
if (action === 'recent_purchases') return apiRecentPurchases_(params);
if (action === 'recent_writeoffs') return apiRecentWriteoffs_(params);
```

**B. New read functions** (add after `apiAddWriteOff_`):

`apiRecentSales_(params)`:
- Read sheet `Продажі`
- Take last `limit` rows (skip header row 1)
- Return `{ ok: true, rows: [...] }` where each row has:
  `row_index`, `date` (ISO string), `channel`, `order_id`, `sku`, `qty`, `price`, `note`
- Column mapping: verify actual headers in `Продажі` before hardcoding indices

`apiRecentPurchases_(params)`:
- Read sheet `Закупки`
- Return `{ ok: true, rows: [...] }` where each row has:
  `row_index`, `date`, `order_ref`, `supplier`, `sku`, `qty`, `cost_prro`, `cost_mgmt`, `status`, `note`

`apiRecentWriteoffs_(params)`:
- Read sheet `Списання`
- Return `{ ok: true, rows: [...] }` where each row has:
  `row_index`, `date`, `sku`, `qty`, `reason`, `note`

**C. `doPost(e)` routing — add inside the action switch after existing write actions:**

```
if (payload.action === 'update_sale')      return boosterCrmJson_(apiUpdateSale_(ss, payload));
if (payload.action === 'update_purchase')  return boosterCrmJson_(apiUpdatePurchase_(ss, payload));
if (payload.action === 'update_writeoff')  return boosterCrmJson_(apiUpdateWriteoff_(ss, payload));
```

**D. New update functions:**

`apiUpdateSale_(ss, payload)`:
- Validate: `row_index` must be a positive integer ≥ 2 (row 1 is header)
- Read row at `row_index` from `Продажі` — verify it's not empty (guard against stale row_index)
- Write only the fields present in payload: `date`, `channel`, `order_id`, `sku`, `qty`, `price`, `note`
- Do NOT rewrite the entire row — use `sheet.getRange(row_index, col).setValue(val)` per field
- Return `{ ok: true, row_index }`

`apiUpdatePurchase_(ss, payload)` — same pattern for `Закупки`
`apiUpdateWriteoff_(ss, payload)` — same pattern for `Списання`

Column indices: Codex must verify actual column order in each sheet before hardcoding. Read row 1 headers to map column names to indices.

---

### dashboard/booster-dashboard.html

**E. `loadAccounting()` function (line ~1820):**

After the existing `write-grid` div is built and `purAddItem()` is called, append a "recent records" section:

```
<div class="records-section">
  <div class="records-tabs">
    <button class="rec-tab active" onclick="showRecTab('sales',this)">Продажі</button>
    <button class="rec-tab" onclick="showRecTab('purchases',this)">Закупки</button>
    <button class="rec-tab" onclick="showRecTab('writeoffs',this)">Списання</button>
  </div>
  <div id="recTabSales"  class="rec-pane active"><div class="loading">...</div></div>
  <div id="recTabPurchases" class="rec-pane hidden"></div>
  <div id="recTabWriteoffs" class="rec-pane hidden"></div>
</div>
```

**F. New JS functions** (add after `submitPurchase`):

`showRecTab(name, btn)`:
- Toggle active class on tab buttons
- Toggle `.hidden` on pane divs
- If the target pane has not been loaded yet, call `loadRecTab(name)`

`loadRecTab(name)`:
- Maps name → action: `sales→recent_sales`, `purchases→recent_purchases`, `writeoffs→recent_writeoffs`
- Calls `call(action, { limit: 20 })`
- Renders a compact table inside the pane div using string concatenation (NO template literals — nested backtick bug risk)
- Each row: `<tr onclick="openEditRow('sales', rowData)">` where rowData is JSON.stringify of the row object embedded as a data attribute or kept in a JS map keyed by row_index

`openEditRow(type, row)`:
- Renders an inline edit form inside `<div id="editRowForm">` appended below the table (or replace previous edit form)
- Pre-fills fields from `row`
- Save button calls `saveEditRow(type)` which reads form fields and calls `callPost({ action: 'update_'+type, row_index: row.row_index, ...fields })`
- On success: reload that tab's data, close edit form, show success message

**G. New CSS** (add to `<style>` block):
```css
.records-section { margin-top: 2rem; }
.records-tabs { display: flex; gap: 8px; margin-bottom: 12px; }
.rec-tab { padding: 6px 16px; border: 1px solid var(--border); background: var(--surface2); color: var(--muted); border-radius: 6px; cursor: pointer; font-size: 13px; }
.rec-tab.active { background: var(--accent); color: #fff; border-color: var(--accent); }
.rec-pane { overflow-x: auto; }
.rec-pane.hidden { display: none; }
.rec-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.rec-table th { color: var(--muted); font-weight: 500; text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--border); }
.rec-table td { padding: 6px 8px; border-bottom: 1px solid var(--border); }
.rec-table tr:hover td { background: var(--surface2); cursor: pointer; }
.edit-row-form { margin-top: 1rem; padding: 1rem; background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; }
.edit-row-form .form-row { margin-bottom: 8px; }
```

---

## 5. Do not touch

- `sitemap.xml`, `robots.txt`, `.htaccess`, redirects, canonical tags
- Checkout, payment, fiscalization (Hutko, Checkbox)
- Merchant feed, Product schema, JSON-LD
- `doPost` Telegram branch and `upsertOpenCartOrder_` — must be preserved byte-for-byte
- `apiAddSale_`, `apiAddPurchase_`, `apiAddWriteOff_` — do not modify, only add new functions
- All existing dashboard sections: Огляд, Склад, Замовлення, Roadmap, Alerts
- `callPost()` helper function — do not modify
- `TOKEN` and `API` constants in dashboard
- `acSkuOptions()`, `purAddItem()`, `purRemoveItem()`, `formMsg()` — do not modify

---

## 6. Likely files / areas

- `Apps_Script_код` sheet (CRM spreadsheet mirror) — `doGet` routing block, after existing action checks; new functions after `apiAddWriteOff_` (currently at ~A874)
- `dashboard/booster-dashboard.html` — `loadAccounting()` function (~L1820), `<style>` block (~L7), end of JS section
- Sheets to read: `Продажі`, `Закупки`, `Списання` — Codex must verify header row column names before mapping

---

## 7. Acceptance criteria

**Apps Script GET:**
- `GET ?action=recent_sales&token=...&limit=5` → `{ ok: true, rows: [...] }` with ≤5 objects, each containing `row_index` as integer ≥ 2
- Same for `recent_purchases`, `recent_writeoffs`
- Empty sheet → `{ ok: true, rows: [] }` (no error)

**Apps Script POST:**
- `POST { action: 'update_sale', row_index: N, price: 150, token: ... }` → `{ ok: true, row_index: N }`
- Corresponding cell in `Продажі` row N is updated; other cells in that row are unchanged
- `row_index: 1` (header row) → `{ ok: false, error: 'invalid row_index' }`
- Missing `row_index` → `{ ok: false, error: '...' }`

**Dashboard:**
- Accounting tab loads → "recent records" section appears below forms
- Default active sub-tab: Продажі, showing table of last ≤20 rows
- Clicking a table row → inline edit form appears below table, pre-filled with row data
- Editing a field and clicking Save → `callPost` fires with correct `action` and `row_index`
- Success → table row updates, form closes, success message shown
- `node --check booster-dashboard.html` (extract JS): no syntax errors

---

## 8. QA / smoke test

1. Open dashboard locally in browser → Облік tab → verify "recent records" section renders
2. All 3 sub-tabs clickable; lazy-load on first click
3. Each table shows real data from API (not placeholder)
4. Click any row → edit form appears with pre-filled values
5. Change one field → Save → check Google Sheet that the cell updated
6. `row_index` of updated row matches the correct sheet row (not off by 1)
7. No JS console errors during normal use
8. `node --check` on extracted JS block: OK
9. Existing write forms (add sale / writeoff / purchase) still work after changes

---

## 9. Rollback note

If dashboard breaks:
```
git checkout HEAD -- dashboard/booster-dashboard.html
```

If Apps Script breaks (recent_ or update_ endpoints):
- Remove only the new routing lines from `doGet` and `doPost`
- Remove only the new `apiRecent*` and `apiUpdate*` functions
- Redeploy previous version
- Existing `apiAddSale_` / `apiAddPurchase_` / `apiAddWriteOff_` are unaffected

---

## 10. Recommended status after execution

- If all acceptance criteria pass: mark DASH-WRITE-004 as Done in roadmap
- If only GET endpoints work but edit UI incomplete: mark as Partial, create follow-up
- Run `node --check` on dashboard JS before committing
- Commit message: `Codex: DASH-WRITE-004 edit records`
