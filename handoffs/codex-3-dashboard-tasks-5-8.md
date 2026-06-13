# Codex Handoff #3 — Dashboard tasks 5–8 (API + HTML)

File: `booster-dashboard.html`
**Prerequisite:** Handoff #1 deployed (channel_stats, monthly_summary, prev_month in summary).

---

## Task 5: Ендпойнт `channel_stats` + канальні бари

**What:** Visual channel breakdown on the Огляд page.
Horizontal percentage bars showing each channel's share of revenue + absolute values.

**Source:** `action=channel_stats` (new endpoint from Handoff #1).

**Where to add:** New section on Огляд page, below the existing summary cards.
Add to HTML:
```html
<!-- In page-overview, after #summaryCards -->
<div class="section" id="channelSection" style="display:none">
  <div class="section-header">
    <span class="section-title">Продажі по каналах</span>
    <span id="channelPeriodLabel" style="font-size:11px;color:var(--muted)">поточний місяць</span>
  </div>
  <div id="channelBars"></div>
</div>
```

**In `loadOverview()`**, after summary cards, add:
```javascript
// Channel stats
try {
  const ch = await call('channel_stats');
  const channels = ch.channels || [];
  if (channels.length) {
    const maxRev = Math.max(...channels.map(c => c.revenue || 0));
    document.getElementById('channelBars').innerHTML = channels.map(c => {
      const pct = maxRev > 0 ? Math.round((c.revenue / maxRev) * 100) : 0;
      const sharePct = channels.reduce((s, x) => s + (x.revenue || 0), 0);
      const share = sharePct > 0 ? Math.round((c.revenue / sharePct) * 100) : 0;
      return `
        <div style="margin-bottom:12px">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:12px">
            <span style="font-weight:500">${c.name}</span>
            <span style="color:var(--muted)">${fmt(c.revenue)} · ${c.orders} зам. · ${fmtP(c.margin_pct)}</span>
          </div>
          <div style="background:var(--surface2);border-radius:4px;height:6px;overflow:hidden">
            <div style="background:var(--accent);height:100%;width:${pct}%;transition:width .4s;border-radius:4px"></div>
          </div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">${share}% від виручки</div>
        </div>`;
    }).join('');
    document.getElementById('channelSection').style.display = 'block';
  }
} catch(e) {
  // Graceful: section stays hidden if endpoint not available yet
}
```

---

## Task 6: Delta місяць до місяця

**What:** Show percentage change vs previous month on the "Виручка місяць" and "Прибуток місяць" cards.

**Source:** `d.sales_prev_month` from extended `summary` response (Handoff #1).

**In `loadOverview()`**, add helper:
```javascript
function deltaTag(current, prev) {
  if (prev == null || !prev || !current) return '';
  const pct = ((current - prev) / Math.abs(prev) * 100);
  const sign = pct >= 0 ? '+' : '';
  const color = pct >= 0 ? 'var(--green)' : 'var(--red)';
  return `<span style="font-size:11px;color:${color};font-weight:600;margin-left:6px">${sign}${pct.toFixed(1)}%</span>`;
}
```

Modify the cards array entries for month data:
```javascript
// BEFORE:
{ label:'Виручка місяць', value:fmt(sm.revenue), sub:`${sm.orders||0} замовлень · прибуток ${fmt(sm.profit)}` }

// AFTER:
{
  label: 'Виручка місяць',
  value: fmt(sm.revenue) + deltaTag(sm.revenue, sp?.revenue),
  sub: `${sm.orders||0} замовлень · прибуток ${fmt(sm.profit)}${deltaTag(sm.profit, sp?.profit)}`
}
```

Where `sp = d.sales_prev_month || null`.

If `sp` is null, `deltaTag()` returns '' — no visual change, no error.

---

## Task 7: Ендпойнт `monthly_summary` + трендовий графік

**What:** Revenue trend chart for last 6 months on a new "Тренд" section on Огляд.
Simple SVG bar chart or CSS-based bars (no external libraries needed).

**Source:** `action=monthly_summary&months=6` (new endpoint from Handoff #1).

**Add to HTML** (in page-overview, below channel section):
```html
<div class="section" id="trendSection" style="display:none">
  <div class="section-header">
    <span class="section-title">Виручка — 6 місяців</span>
  </div>
  <div id="trendChart" style="display:flex;align-items:flex-end;gap:8px;height:80px;padding:4px 0"></div>
  <div id="trendLabels" style="display:flex;gap:8px;margin-top:6px"></div>
</div>
```

**In `loadOverview()`**:
```javascript
// Monthly trend
try {
  const ms = await call('monthly_summary', { months: 6 });
  const months = ms.months || [];
  if (months.length) {
    const maxRev = Math.max(...months.map(m => m.revenue || 0));
    document.getElementById('trendChart').innerHTML = months.map(m => {
      const h = maxRev > 0 ? Math.round((m.revenue / maxRev) * 68) : 4;
      const isCurrentMonth = months.indexOf(m) === months.length - 1;
      const color = isCurrentMonth ? 'var(--accent)' : 'var(--surface2)';
      const border = isCurrentMonth ? '2px solid var(--accent)' : '1px solid var(--border)';
      return `<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
        <span style="font-size:9px;color:var(--muted)">${fmt(m.revenue)}</span>
        <div style="width:100%;height:${h}px;background:${color};border:${border};border-radius:3px;
                    transition:height .3s"></div>
      </div>`;
    }).join('');
    document.getElementById('trendLabels').innerHTML = months.map(m =>
      `<div style="flex:1;text-align:center;font-size:10px;color:var(--muted)">${m.label || m.month}</div>`
    ).join('');
    document.getElementById('trendSection').style.display = 'block';
  }
} catch(e) {
  // Graceful: section stays hidden
}
```

Current month bar highlighted in accent color. Past months in surface2.

---

## Task 8: Ендпойнт `ltv_report` + Топ-10 клієнтів

**What:** New page or section showing Top-10 clients by lifetime value.
Add as a new nav item in the sidebar, or as a section on Огляд (user preference TBD).

**Recommendation:** Add as a 5th sidebar nav item ("Клієнти") with its own page.
This keeps the Огляд uncluttered.

**Add to HTML sidebar:**
```html
<!-- After the last existing nav-item -->
<div class="nav-item" onclick="showPage('clients', event)">
  <span class="nav-icon">👥</span>
  <span>Клієнти</span>
</div>
```

**Add page section:**
```html
<!-- After page-skus div -->
<div id="page-clients" class="page">
  <div class="header">
    <div class="page-title">Клієнти</div>
    <div class="page-sub">Топ-10 за сумою покупок</div>
  </div>
  <div class="section">
    <div id="clientsTable"><div class="loading"><div class="spinner"></div><br>Завантаження...</div></div>
  </div>
</div>
```

**Add load function:**
```javascript
async function loadClients() {
  clearError();
  try {
    const d = await call('ltv_report', { limit: 10 });
    const clients = d.clients || [];
    if (!clients.length) {
      document.getElementById('clientsTable').innerHTML = '<div style="color:var(--muted);padding:20px">Немає даних</div>';
      return;
    }
    document.getElementById('clientsTable').innerHTML = `<div class="tbl-wrap"><table>
      <tr><th>#</th><th>Клієнт</th><th>Замовлень</th><th>Товарів</th><th>Загальна сума</th></tr>
      ${clients.map((c, i) => `<tr>
        <td style="color:var(--muted)">${i + 1}</td>
        <td class="mono" style="font-size:12px">${c.display || '—'}</td>
        <td>${c.orders}</td>
        <td>${c.units || '—'}</td>
        <td><b>${fmt(c.ltv)}</b></td>
      </tr>`).join('')}
    </table></div>`;
  } catch(e) {
    showError(e.message);
    document.getElementById('clientsTable').innerHTML = '';
  }
}
```

**Update nav/loader map:**
```javascript
// Update titles object:
const titles = { overview: 'Огляд', stock: 'Склад', orders: 'Замовлення', skus: 'Товари', clients: 'Клієнти' };

// Update loaders map:
const loaders = { overview: loadOverview, stock: loadStock, orders: loadOrders, skus: loadSkus, clients: loadClients };

// Update hardRefresh to clear clientsTable:
['summaryCards','pendingPreview','alertsPreview','stockTable','ordersTable','skuTable','clientsTable']
```

---

## Acceptance criteria

- [ ] Огляд: channel bars section visible with 4 channels (OpenCart, OLX, Telegram, Monobazar)
- [ ] Bars proportional to revenue, total sum of all bars = 100%
- [ ] Section hidden (no error) if `channel_stats` not available yet
- [ ] Виручка місяць card shows "+X%" or "-X%" delta in color
- [ ] Delta absent (no error) when `sales_prev_month` is null
- [ ] Trend chart visible with 6 month bars, current month in accent color
- [ ] Month labels visible below bars
- [ ] Клієнти nav item visible in sidebar
- [ ] Clients page loads Top-10 table with masked phone numbers
- [ ] Hard refresh resets all sections

## QA checklist (owner)

- [ ] Channel bars: verify OpenCart % matches CRM channel breakdown
- [ ] Delta: if current month < prev month, delta shows negative in red
- [ ] Trend chart: highest bar = max revenue month, current month = accent
- [ ] Clients page: verify top client matches what you know from CRM
- [ ] All sections load without console errors

## Risks

- **Tasks 5, 7, 8 depend on Handoff #1**: If endpoints are not deployed, sections stay hidden
  (graceful degradation via try/catch). Deploy Handoff #1 first, then test.
- **Task 8 — phones in API**: The `ltv_report` must mask phones server-side.
  Verify the API response before deploying the clients page. Never show full phone numbers.
- **Task 6 — card value with HTML**: `card-value` div currently renders plain text via `textContent`
  in some implementations. If the delta tag (HTML span) doesn't render, switch to `innerHTML`.
  Check how `summaryCards` builds card HTML — it likely uses template literals, so innerHTML is fine.
