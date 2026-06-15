# DASH-PERF-001 — Parallel API fetches in loadOverview()

**File:** `dashboard/booster-dashboard.html`
**Type:** Dashboard JS refactor
**Goal:** Fire all `loadOverview()` API calls simultaneously instead of sequentially. Expected reduction: 3–5× faster overview load.

---

## Context

`loadOverview()` currently makes 7 API calls one after another (sequential `await`s). All calls except `orders` for "pending orders preview" are independent. Total wall-clock time = sum of all call latencies (~5–8s). After this patch, wall-clock time = slowest single call (~1–2s).

Additionally, `monthToDateStats()` fetches 500 orders to compute MtD stats client-side. This call is eliminated by fetching `ordersAll` in the parallel batch and computing MtD inline.

---

## Changes — EXACT diffs

### 1. Replace beginning of `loadOverview()` (lines 704–734)

**Find (exact match):**
```js
async function loadOverview() {
  clearError();
  // Summary cards
  try {
    const d = await call('summary');
    cache.summary = d;
    const s7 = d.sales_7d || {};
    const s7Period = d.sales_7d_period || s7;
    const s7PrevPeriod = d.sales_prev_month_7d_period || null;
    const sm = d.sales_current_month || {};
    const sp = d.sales_prev_month || null;
    const st = d.stock || {};
    const dq = d.data_quality || {};
    const po = d.pending_orders || {};
    let mtd = { current: d.sales_current_month || {}, previous: null };
    let ordersData = cache.orders || { orders: [] };
    let ordersForUnpaidOk = !!cache.orders;
    if (!cache.orders) {
      try {
        ordersData = await call('orders', {status:'active', limit:100});
        cache.orders = ordersData;
        ordersForUnpaidOk = true;
      } catch(e) {
        console.warn('orders unpaid card:', e.message);
      }
    }
    try {
      mtd = await monthToDateStats();
    } catch(e) {
      console.warn('mtd card:', e.message);
    }
```

**Replace with:**
```js
async function loadOverview() {
  clearError();
  // Kick off ALL independent fetches simultaneously (none blocked on prior result)
  const p_summary   = call('summary');
  const p_orders    = cache.orders    ? Promise.resolve(cache.orders)    : call('orders', {status:'active', limit:100});
  const p_ordersAll = cache.ordersAll ? Promise.resolve(cache.ordersAll) : call('orders', {status:'all', limit:500, sort:'date_desc'});
  const p_channel   = call('channel_stats');
  const p_monthly   = cache.monthly   ? Promise.resolve(cache.monthly)   : call('monthly_summary', { months: 6 });
  const p_sku       = call('sku_list', { sort: 'profit', limit: 5 });
  const p_stock     = cache.stock     ? Promise.resolve(cache.stock)     : call('stock_alerts');

  // Summary cards
  try {
    const d = await p_summary;
    cache.summary = d;
    const s7 = d.sales_7d || {};
    const s7Period = d.sales_7d_period || s7;
    const s7PrevPeriod = d.sales_prev_month_7d_period || null;
    const sm = d.sales_current_month || {};
    const sp = d.sales_prev_month || null;
    const st = d.stock || {};
    const dq = d.data_quality || {};
    const po = d.pending_orders || {};
    let mtd = { current: d.sales_current_month || {}, previous: null };
    let ordersData = { orders: [] };
    let ordersForUnpaidOk = false;
    try {
      ordersData = await p_orders;
      cache.orders = ordersData;
      ordersForUnpaidOk = true;
    } catch(e) {
      console.warn('orders unpaid card:', e.message);
    }
    try {
      const allOrders = await p_ordersAll;
      cache.ordersAll = allOrders;
      const bounds = mtdPeriodBounds();
      mtd = {
        current: aggregateOrdersForRange(allOrders.orders || [], bounds.current),
        previous: aggregateOrdersForRange(allOrders.orders || [], bounds.previous)
      };
    } catch(e) {
      console.warn('mtd card:', e.message);
    }
```

---

### 2. Channel stats section (line ~771)

**Find:**
```js
    const ch = await call('channel_stats');
```
**Replace with:**
```js
    const ch = await p_channel;
```

---

### 3. Monthly trend section (line ~799)

**Find:**
```js
    const ms = cache.monthly || await call('monthly_summary', { months: 6 });
    cache.monthly = ms;
```
**Replace with:**
```js
    const ms = await p_monthly;
    cache.monthly = ms;
```

---

### 4. Top-5 SKUs section (line ~858)

**Find:**
```js
    const ts = await call('sku_list', { sort: 'profit', limit: 5 });
```
**Replace with:**
```js
    const ts = await p_sku;
```

---

### 5. Pending orders preview (line ~887)

**Find:**
```js
    const d = cache.orders || await call('orders', {status:'active', limit:6});
    cache.orders = d;
```
**Replace with:**
```js
    const d = cache.orders || await p_orders;
    cache.orders = d;
```

---

### 6. Stock alerts preview (line ~912)

**Find:**
```js
    const d = cache.stock || await call('stock_alerts');
    cache.stock = d;
```
**Replace with:**
```js
    const d = await p_stock;
    cache.stock = d;
```

---

## What does NOT change

- All rendering code stays identical (no DOM changes)
- Error handling try/catch stays intact
- Cache logic (`cache.*`) unchanged
- `getAllOrders()`, `monthToDateStats()`, `aggregateOrdersForRange()` helper functions are untouched (they remain in the file but `loadOverview` no longer calls `monthToDateStats` — it inlines the same logic)

---

## Acceptance criteria

- [ ] Dashboard overview loads with all 7 section simultaneously in flight
- [ ] "Місяць на сьогодні" card shows correct `current` and `previous` data (delta arrows work)
- [ ] "Активні замовл." card correct
- [ ] Channel bars render
- [ ] Monthly trend bars render
- [ ] Top-5 SKUs render
- [ ] Pending orders preview renders
- [ ] Stock alerts preview renders
- [ ] Console: no new errors; "orders unpaid card" / "mtd card" warnings only if API actually fails

---

## QA checklist

- [ ] Open `https://bazilik141.github.io/booster-shop-ops/` in fresh incognito window (empty cache)
- [ ] Open DevTools → Network tab → measure time from first request to last response
- [ ] Verify all 7 API calls show simultaneous start timestamps (not sequential)
- [ ] Click each sidebar tab (Stock, Orders, SKUs, Clients) — no errors
- [ ] Hard-reload (Ctrl+Shift+R) second time — cached sections should use in-memory cache

---

## Deploy

This is a GitHub Pages file. No FTP needed.
After patch: `git add dashboard/booster-dashboard.html && git commit -m "Codex: DASH-PERF-001 parallel loadOverview fetches" && git push origin master`
GitHub Pages auto-deploys within ~1 minute.

---

## Manual step (owner, not Codex)

**Apps Script — add server-side caching for summary, channel_stats, monthly_summary:**

In Google Sheets → Extensions → Apps Script, find `CACHEABLE_ACTIONS` and update:

```js
// Before:
const CACHEABLE_ACTIONS = {
  sku_list: 300,
  stock_alerts: 120
};

// After:
const CACHEABLE_ACTIONS = {
  sku_list:        300,
  stock_alerts:    120,
  summary:          90,   // opens 2nd spreadsheet — slow; safe to cache 90s
  channel_stats:   120,
  monthly_summary: 300
};
```

Then add keepWarm trigger (one-time setup):
Apps Script → Triggers (⏰) → Add trigger → Function: `keepWarm` → Time-driven → Minutes timer → Every 5 minutes
