# Codex Handoff #2 — Dashboard tasks 1–4 (client HTML only)

File: `booster-dashboard.html`
**Prerequisite:** Handoff #1 deployed (for task 3 — potential_profit_warehouse in summary).
Tasks 1, 2, 4 are pure client-side and can be deployed independently.

---

## Task 1: Картка «Відправлено і не оплачено»

**What:** A card on the Огляд page showing count of orders with status "Відправлено"
AND payment "Не оплачено". Silent informational counter, NOT a critical alert.

**Source:** Existing `action=orders&status=active` response. No new API call needed.

**Implementation:**

In `loadOverview()`, after fetching orders, add:
```javascript
const unpaids = (d.orders || []).filter(r =>
  r.order_status === 'Відправлено' && r.payment_status === 'Не оплачено'
);
```

Add card to the cards array:
```javascript
{
  label: 'Відправлено · не оплачено',
  value: unpaids.length,
  sub: unpaids.length > 0 ? `На суму ${fmt(unpaids.reduce((s,r) => s + (r.amount||0), 0))}` : 'Все оплачено',
  cls: unpaids.length > 0 ? 'c-yellow' : ''
}
```

Place this card after "Активні замовлення" card.

**Note:** The `orders` data is already fetched for the "Активні замовлення" section.
Re-use `cache.orders` — do not make a second API call.

---

## Task 2: Days coverage у таблиці Складу

**What:** Add a "Днів запасу" column to the Склад page table.
Formula: `Math.round(stock / (sold_30d / 30))` — how many days current stock will last at current sell rate.

**Source:** Existing `stock_alerts` response fields: `r.stock` and `r.sold_30d`. No new API.

**Implementation:**

In `loadStock()`, add column header:
```javascript
// In the <tr> header row, after <th>30д</th>:
<th>Днів запасу</th>
```

In the row rendering, add cell:
```javascript
// After the sold_30d cell:
<td>${dayCoverage(r.stock, r.sold_30d)}</td>
```

Add helper function:
```javascript
function dayCoverage(stock, sold30d) {
  if (!stock || stock <= 0) return '<span style="color:var(--muted)">—</span>';
  if (!sold30d || sold30d <= 0) return '<span style="color:var(--muted)">∞</span>';
  const days = Math.round(stock / (sold30d / 30));
  const cls = days < 7 ? 'var(--red)' : days < 21 ? 'var(--yellow)' : 'var(--green)';
  return `<span style="color:${cls};font-weight:600">${days}</span>`;
}
```

Color thresholds:
- < 7 days → red
- 7–20 days → yellow
- ≥ 21 days → green

---

## Task 3: Картка «Потенційний прибуток складу»

**What:** A card on the Огляд page showing total potential profit from current warehouse stock.

**Source:** `d.potential_profit_warehouse` from `summary` API response.
This requires Handoff #1 to be deployed first (apiSummary_ extension).

**Implementation:**

In `loadOverview()`, after reading `sm = d.sales_current_month || {}`, add:
```javascript
const warehouseProfit = d.potential_profit_warehouse || null;
```

Add card to the cards array:
```javascript
{
  label: 'Потенційний прибуток складу',
  value: fmt(warehouseProfit),
  sub: warehouseProfit ? 'При продажу всього залишку' : 'Немає даних',
  cls: warehouseProfit > 0 ? 'c-accent' : ''
}
```

If `potential_profit_warehouse` is null (Handoff #1 not yet deployed), the card shows "—" gracefully.

---

## Task 4: Фільтр у таблицях

**What:** Add a text filter input above the Склад and Товари tables.
Real-time filtering of table rows by any visible text.

### Склад page

Add filter input HTML above `<div id="stockTable">`:
```html
<div style="margin-bottom:14px">
  <input id="stockFilter" type="text" placeholder="🔍 Фільтр по SKU або назві…"
    style="width:100%;padding:8px 12px;background:var(--surface2);border:1px solid var(--border);
           border-radius:8px;color:var(--text);font-size:13px;outline:none;
           transition:border-color .15s" onfocus="this.style.borderColor='var(--accent)'"
    onblur="this.style.borderColor='var(--border)'"/>
</div>
```

In `loadStock()`, after rendering the table, attach listener:
```javascript
const sf = document.getElementById('stockFilter');
if (sf) {
  sf.value = '';
  sf.oninput = function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#stockTable table tr:not(:first-child)').forEach(tr => {
      tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  };
}
```

### Товари page

Same pattern. Add above `<div id="skuTable">`:
```html
<div style="margin-bottom:14px">
  <input id="skuFilter" type="text" placeholder="🔍 Фільтр по SKU, назві або бренду…"
    style="width:100%;padding:8px 12px;background:var(--surface2);border:1px solid var(--border);
           border-radius:8px;color:var(--text);font-size:13px;outline:none;
           transition:border-color .15s" onfocus="this.style.borderColor='var(--accent)'"
    onblur="this.style.borderColor='var(--border)'"/>
</div>
```

In `loadSkus()`, after rendering:
```javascript
const skf = document.getElementById('skuFilter');
if (skf) {
  skf.value = '';
  skf.oninput = function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#skuTable table tr:not(:first-child)').forEach(tr => {
      tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  };
}
```

Filter resets automatically when the page reloads (function reinitializes the element).

---

## Acceptance criteria

- [ ] Огляд: card "Відправлено · не оплачено" visible with count (0 or more)
- [ ] Card shows correct total sum of unpaid-shipped orders
- [ ] Card is yellow when count > 0, neutral when 0
- [ ] No extra API calls made for task 1 (reuses cache.orders)
- [ ] Склад table: "Днів запасу" column appears with colored numbers
- [ ] Days = ∞ when sold_30d = 0, — when stock = 0
- [ ] Огляд: "Потенційний прибуток складу" card appears with ₴ value
- [ ] Card shows "—" gracefully if API field absent
- [ ] Склад: filter input appears above table, typing filters rows instantly
- [ ] Товари: same filter behavior
- [ ] Filter input has focus highlight (accent border)
- [ ] No console errors

## QA checklist (owner)

- [ ] Check "Відправлено · не оплачено" card count against CRM manually
- [ ] Склад: find SKU with sales — verify Днів запасу = stock / (sold_30d/30), rounded
- [ ] SKU with 0 sold_30d → shows ∞ (not division error)
- [ ] Filter: type "mzero" on Склад → only MegaZero rows visible
- [ ] Filter: clear input → all rows visible again
- [ ] Hard refresh: filter resets to empty

## Risks

- **Task 1 data source**: The `orders` endpoint with `status=active` must include
  Відправлено+Не оплачено orders. If these orders are filtered out by the active status definition,
  the counter will always show 0. Verify by checking CRM for such orders manually.
- **Task 3 dependency**: If Handoff #1 is not deployed, `potential_profit_warehouse` is null.
  Card will show "—" — acceptable fallback.
- **Filter on header row**: The selector `tr:not(:first-child)` assumes single header row.
  If the table has a `<thead>` with `<th>` rows, adjust selector to `tbody tr`.
