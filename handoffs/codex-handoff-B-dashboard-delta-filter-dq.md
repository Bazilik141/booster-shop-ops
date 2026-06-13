# Codex Handoff B — Dashboard HTML: delta M/M + filter + data_quality section

File: `booster-dashboard.html` (Booster Shop folder)
All changes are client-side HTML/JS only. Do NOT modify Apps Script or API unless noted.

---

## Change 1: Delta місяць до місяця (B)

### API extension required (Apps Script side)

In `apiSummary_()` function, add reading of previous month row from `Звіт_Продажів`
in the Automation spreadsheet (same way 'Поточний місяць' row is read).
Add to the returned JSON object:
```json
"sales_prev_month": {
  "revenue": 43242.50,
  "profit": 15267.21,
  "margin_pct": 35.3,
  "orders": 42
}
```
If previous month row is not found, return `null` for `sales_prev_month`.

### Dashboard HTML change

In `loadOverview()`, after reading `sm = d.sales_current_month || {}`, add:
```javascript
const sp = d.sales_prev_month || null;

function delta(current, prev) {
  if (!prev || !current || prev === 0) return '';
  const pct = ((current - prev) / Math.abs(prev) * 100).toFixed(1);
  const sign = pct > 0 ? '+' : '';
  const cls = pct > 0 ? 'var(--green)' : 'var(--red)';
  return `<span style="font-size:11px;color:${cls};margin-left:4px">${sign}${pct}%</span>`;
}
```

Modify the "Виручка місяць" card sub-line:
```javascript
// BEFORE:
sub: `${sm.orders||0} замовлень · прибуток ${fmt(sm.profit)}`

// AFTER:
sub: `${sm.orders||0} замовлень · прибуток ${fmt(sm.profit)}${delta(sm.revenue, sp?.revenue)}`
```

Add a new card after "Виручка місяць" (or update its sub):
No new card needed. Delta appears inline as a colored percentage.

If `sales_prev_month` is null, `delta()` returns '' — no visual change.

---

## Change 2: Фільтр на сторінках Склад і Товари (C)

### Склад page filter

Above `<div id="stockTable">`, add:
```html
<div style="margin-bottom:14px">
  <input id="stockFilter" type="text" placeholder="🔍 Фільтр по SKU або назві..."
    style="width:100%;padding:8px 12px;background:var(--surface2);border:1px solid var(--border);
           border-radius:8px;color:var(--text);font-size:13px;outline:none"/>
</div>
```

In JS, after `loadStock()` renders the table, attach filter:
```javascript
document.getElementById('stockFilter').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#stockTable table tbody tr').forEach(tr => {
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});
```

Reset filter on page reload:
```javascript
// At the top of loadStock():
const filterEl = document.getElementById('stockFilter');
if (filterEl) filterEl.value = '';
```

### Товари (SKUs) page filter

Same pattern. Add above `<div id="skuTable">`:
```html
<div style="margin-bottom:14px">
  <input id="skuFilter" type="text" placeholder="🔍 Фільтр по SKU, назві або бренду..."
    style="width:100%;padding:8px 12px;background:var(--surface2);border:1px solid var(--border);
           border-radius:8px;color:var(--text);font-size:13px;outline:none"/>
</div>
```

In `loadSkus()`, after rendering table:
```javascript
document.getElementById('skuFilter').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#skuTable table tbody tr').forEach(tr => {
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});

// Reset on load:
const skuFilterEl = document.getElementById('skuFilter');
if (skuFilterEl) skuFilterEl.value = '';
```

---

## Change 3: Стан даних — окрема секція на Огляді (D)

### Current state

Right now `dq` (data_quality from summary API) shows only one card:
`{ label:'Продажі без SKU', value: dq.sales_without_sku }`.

### What to add

Below the cards grid on the Огляд page, add a collapsible "Стан даних" section.
It should be collapsed by default if no issues, expanded if issues detected.

```html
<!-- Add after #summaryCards div in the HTML template, rendered by JS -->
<div id="dataQualitySection" class="section" style="margin-top:18px;display:none">
  <div class="section-header" style="cursor:pointer" onclick="toggleDQ()">
    <span class="section-title" id="dqTitle">Стан даних</span>
    <span id="dqToggle" style="color:var(--muted);font-size:12px">▼</span>
  </div>
  <div id="dqBody" style="display:none"></div>
</div>
```

In `loadOverview()`, after building cards, add:
```javascript
// Data quality section
const issues = [];
if ((dq.sales_without_sku||0) > 0) issues.push(`⚠ Продажі без SKU: ${dq.sales_without_sku}`);
if (!dq.source_ok) issues.push(`⚠ Перевір джерела продажів`);
// Add more checks here as API grows

const dqEl = document.getElementById('dataQualitySection');
const dqBody = document.getElementById('dqBody');
const dqTitle = document.getElementById('dqTitle');

dqEl.style.display = 'block';

if (issues.length === 0) {
  dqTitle.innerHTML = 'Стан даних <span style="color:var(--green);font-size:11px;margin-left:6px">✅ OK</span>';
  dqBody.innerHTML = '<div style="color:var(--muted);font-size:13px;padding:4px 0">Проблем не знайдено</div>';
  // Collapsed by default when all OK
} else {
  dqTitle.innerHTML = `Стан даних <span class="badge b-red" style="margin-left:8px">${issues.length}</span>`;
  dqBody.innerHTML = issues.map(i => `<div style="color:var(--yellow);font-size:13px;padding:3px 0">${i}</div>`).join('');
  // Expanded by default when there are issues
  dqBody.style.display = 'block';
  document.getElementById('dqToggle').textContent = '▲';
}
```

Add toggle function:
```javascript
function toggleDQ() {
  const body = document.getElementById('dqBody');
  const toggle = document.getElementById('dqToggle');
  const visible = body.style.display !== 'none';
  body.style.display = visible ? 'none' : 'block';
  toggle.textContent = visible ? '▼' : '▲';
}
```

Also remove the standalone "Продажі без SKU" card from the cards array (to avoid duplication).
Instead, data_quality info lives only in the collapsible section.

---

## What NOT to change

- Do NOT modify `call()`, `CONFIG`, TOKEN, or any existing API call logic
- Do NOT modify `loadOrders()`, `hardRefresh()`, sidebar nav, or CSS variables
- Do NOT add new API actions (except `sales_prev_month` to summary in Change 1)

---

## Acceptance criteria

- [ ] Огляд: Виручка місяць card shows "+X%" or "-X%" delta in green/red next to the value
- [ ] Delta is absent (no broken UI) when `sales_prev_month` is null in API response
- [ ] Склад page: filter input appears above table, filters rows in real-time by SKU or name
- [ ] Товари page: same filter behavior
- [ ] Огляд: "Стан даних" section appears below cards
- [ ] Section is collapsed + green "✅ OK" when no issues
- [ ] Section is expanded + red badge when issues present
- [ ] "Продажі без SKU" card removed from the main cards row (now in DQ section only)
- [ ] No console errors on page load

---

## QA checklist (owner)

- [ ] Open dashboard on localhost:8080, check Огляд loads without errors
- [ ] Confirm delta appears on Виручка місяць card
- [ ] Go to Склад, type "MZERO" in filter — only MZERO row visible
- [ ] Go to Товари, type "outlaw" — only Outlaw rows visible
- [ ] Check Стан даних section collapses/expands on click
- [ ] hard-refresh (оновити дані) — filter resets, DQ section recalculates

---

## Risks

- **Filter input persistence**: Currently filter resets on `hardRefresh` (intentional).
  If user wants sticky filter across refreshes, add `localStorage` for filter value — but only if requested.
- **`sales_prev_month` null**: If Apps Script change for Change 1 is not deployed yet,
  dashboard will show no delta (graceful degradation). Ship HTML change first, API second.
- **DQ section visibility**: If `apiSummary_` does not return `data_quality` object,
  the section should still render with "Стан даних — немає даних" and not throw an error.
  Guard with `const dq = d.data_quality || {}`.
