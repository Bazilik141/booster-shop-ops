# Codex Handoff #4 — Dashboard tasks 9–10 (API + HTML)

File: `booster-dashboard.html`
**Prerequisite:** Handoff #1 deployed (sku_list sort/limit params, repeat_rate in monthly_summary).

Task 10 (Repeat purchases %) is marked HIGH complexity — deploy separately after task 9 is verified.

---

## Task 9: Топ-5 SKU по чистому прибутку

**What:** A "Top 5 SKU" section on the Огляд page showing best-performing products by net profit (30 days).

**Source:** Extended `sku_list` with `sort=profit&limit=5` params (from Handoff #1, C5).

**Add to HTML** (in page-overview, after trend section):
```html
<div class="section" id="topSkusSection" style="display:none">
  <div class="section-header">
    <span class="section-title">Топ-5 SKU — прибуток за 30 днів</span>
  </div>
  <div id="topSkusList"></div>
</div>
```

**In `loadOverview()`**:
```javascript
// Top-5 SKUs by profit
try {
  const ts = await call('sku_list', { sort: 'profit', limit: 5 });
  const skus = ts.skus || [];
  if (skus.length) {
    const maxProfit = Math.max(...skus.map(s => s.profit_30d || 0));
    document.getElementById('topSkusList').innerHTML = skus.map((s, i) => {
      const barW = maxProfit > 0 ? Math.round(((s.profit_30d||0) / maxProfit) * 100) : 0;
      return `
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
          <span style="color:var(--muted);font-size:12px;width:16px">${i+1}</span>
          <div style="flex:1;min-width:0">
            <div style="font-size:12px;font-weight:500;white-space:nowrap;overflow:hidden;
                        text-overflow:ellipsis">${skuShort(s.name)}</div>
            <div style="background:var(--surface2);border-radius:3px;height:4px;margin-top:4px">
              <div style="background:var(--green);height:100%;width:${barW}%;border-radius:3px"></div>
            </div>
          </div>
          <div style="text-align:right;flex-shrink:0">
            <div style="font-size:13px;font-weight:700;color:var(--green)">${fmt(s.profit_30d)}</div>
            <div style="font-size:10px;color:var(--muted)">${s.sold_30d||0} шт</div>
          </div>
        </div>`;
    }).join('');
    document.getElementById('topSkusSection').style.display = 'block';
  }
} catch(e) {
  // Graceful: section hidden if endpoint not available or sort not supported yet
}
```

**What `profit_30d` should be in the API response:**
`profit_30d = sold_30d × (price_crm - current_cost_prro)`

If `profit_30d` is not yet added by Handoff #1 to sku_list, this section will be hidden.
Add a console.warn in catch to diagnose: `console.warn('top_skus: ', e.message)`.

---

## Task 10: Повторні покупки %

**What:** Show repeat purchase rate on the Огляд page.
"X% клієнтів зробили більше 1 покупки" — simple stat card or inline in the Огляд summary area.

This is HIGH complexity because it requires reliable customer identification across orders.

**Source:** `repeat_rate_pct` field in `monthly_summary` response (from Handoff #1, C3).

**Prerequisite (Apps Script side — verify before HTML):**

`repeat_rate_pct` is calculated as:
```
unique_customers_with_2+_orders / total_unique_customers × 100
```
Where "customer" = phone number (or name if phone absent).
Calculated across ALL TIME, not just current month.

This calculation may not be reliable if:
- Customer identification column is inconsistent (different number formats, empty values)
- Same customer uses different phones

Codex must log how many customers were identified and flag if > 20% have empty identifiers.
If data quality is insufficient, return `repeat_rate_pct: null` and the dashboard will skip the card.

**HTML implementation (add card to Огляд):**

In `loadOverview()`, after fetching monthly_summary for the trend chart (task 7),
read `repeat_rate_pct` from the same response:

```javascript
// Add inside the monthly_summary try block (reuse the same call from task 7)
const repeatRate = ms.repeat_rate_pct;
if (repeatRate != null) {
  // Inject a card into summaryCards or add a dedicated element
  // Option A: add as a metric card (simplest)
  const repeatCard = document.createElement('div');
  repeatCard.className = 'card' + (repeatRate >= 20 ? ' c-green' : '');
  repeatCard.innerHTML = `
    <div class="card-label">Повторні покупки</div>
    <div class="card-value">${fmtP(repeatRate)}</div>
    <div class="card-sub">клієнтів з 2+ замовленнями</div>`;

  // Insert after the existing cards (append to cards grid)
  document.getElementById('summaryCards').appendChild(repeatCard);
}
```

**Alternative (if summaryCards is rebuilt as innerHTML each time):**
Add `repeat_rate_pct` to the cards array:
```javascript
...(repeatRate != null ? [{
  label: 'Повторні покупки',
  value: fmtP(repeatRate),
  sub: 'клієнтів з 2+ замовленнями',
  cls: repeatRate >= 20 ? 'c-green' : ''
}] : [])
```

**Important:** Both options require that `monthly_summary` is called before or during `loadOverview()`.
If task 7 is implemented, reuse the same `ms` object — do NOT make a second API call.

---

## Execution order

1. Deploy Handoff #1 (Apps Script — all API changes)
2. Test all 7 endpoints manually with curl or browser
3. Deploy Handoff #2 (tasks 1–4) — independent, low risk
4. Deploy Handoff #3 (tasks 5–8) — depends on Handoff #1
5. Deploy Handoff #4 task 9 (Top-5 SKU) — verify `profit_30d` in sku_list response first
6. Deploy Handoff #4 task 10 (Repeat purchases) — verify data quality first, then enable

---

## Acceptance criteria

**Task 9:**
- [ ] Топ-5 section visible on Огляд with 5 SKU rows
- [ ] Bars proportional to profit_30d
- [ ] Section hidden if `profit_30d` not in sku_list response (graceful)
- [ ] SKU names truncated properly if too long

**Task 10:**
- [ ] Card "Повторні покупки X%" visible on Огляд
- [ ] Card absent (no error) if `repeat_rate_pct` is null
- [ ] Value makes business sense (> 0%, < 100%)
- [ ] No second API call for monthly_summary (reuses task 7 data)

## QA checklist (owner)

**Task 9:**
- [ ] Verify Top-5 makes sense: compare with CRM Звіт_Продажів
- [ ] SKU with most sold units should be near top (unless low margin)
- [ ] Zero-sales SKUs should NOT appear

**Task 10:**
- [ ] Check Apps Script logs: how many customers identified? How many have empty phones?
- [ ] If > 20% identifiers are empty → do not display (API returns null)
- [ ] Rate of 5–30% is plausible for this type of store
- [ ] If rate shows 0% — investigate customer identification logic in Apps Script

## Risks

- **Task 9 — profit_30d field**: If Handoff #1 doesn't add `profit_30d` to sku_list items,
  the section won't render. Alternative: calculate client-side as `sold_30d × (price_crm - margin_pct × price_crm / 100)`
  — but this is approximate. Better to have it server-side where exact cost data is available.

- **Task 10 — data quality**: Repeat purchase rate depends entirely on customer identification.
  If Продажі doesn't have a reliable phone/name column with consistent formatting,
  the metric will be wrong. Codex must inspect the column first and report data quality before
  implementing. Do not display a meaningless metric.

- **Monthly_summary reuse**: Tasks 7 and 10 both use `monthly_summary` response.
  If they're in different try blocks, the second call is a duplicate. Refactor to call once,
  store in `cache.monthly`, and reuse. Add `cache.monthly` to `hardRefresh()` reset list.

- **Cards grid overflow**: Adding 2+ new cards (tasks 1, 3, 10) to summaryCards may cause
  the grid to overflow or wrap awkwardly on smaller screens. Review after all cards are added —
  may need to increase `minmax()` value or reduce font sizes.
