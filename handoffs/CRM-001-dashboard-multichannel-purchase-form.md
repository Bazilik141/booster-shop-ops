# CRM-001 — Dashboard HTML: Multi-channel purchase form

**Date:** 2026-06-22
**Task ID:** CRM-001
**Depends on:** CRM-002 (Apps Script must be deployed first)
**Executor:** Codex
**Risk:** Low — UI-only changes in booster-dashboard.html, no OpenCart, no SEO

---

## Goal

Adapt the purchase form in booster-dashboard.html to support multiple sourcing channels.
Remove ZenMarket-specific labels and logic from the UI.
Update statuses to the new universal set.

---

## Context

File: dashboard/booster-dashboard.html
Current purchase form (search for: "Внести закупку"):
  - "ZenMarket Order №" — required input
  - "ZenMarket URL" — url input
  - "Доставка / комісії по Японії, JPY" — hardcoded JPY
  - PURCHASE_STATUSES = ['В дорозі','На складі','Частково продано','Продано','Скасовано','На складі в Японії','Виграно']
  - Default status: "Виграно"
  - submitPurchase() validates: if (!order_ref || ...) → blocks submit

Batch-update panel (search for: "batchUkraineDeliveryJpy"):
  - Shows all purchase lots for JPY fee update
  - Must be filtered to zenmarket_jp lots only

---

## Required changes

### 1. PURCHASE_STATUSES — replace (line ~1920)

Old:
  const PURCHASE_STATUSES = ['В дорозі','На складі','Частково продано','Продано','Скасовано','На складі в Японії','Виграно'];

New:
  const PURCHASE_STATUSES = ['Замовлено','В дорозі','На складі UA','Скасовано'];

### 2. Purchase form HTML — add supplier_channel as first field (line ~1963)

Add as the FIRST row in the form, before "Референс / № замовлення":

  <div class="form-row">
    <label>Постачальник / Канал *</label>
    <select id="purSupplierChannel" onchange="onPurChannelChange()">
      <option value="zenmarket_jp">ZenMarket (Японія)</option>
      <option value="supplier_ua">Постачальник UA</option>
      <option value="other">Інший канал</option>
    </select>
  </div>

### 3. Rename and update existing form fields

  "ZenMarket Order №" label → "Референс / № замовлення"
    - Remove required asterisk for non-ZenMarket channels (controlled by onPurChannelChange)
    - Placeholder: "Номер замовлення або лишити порожнім"
  
  "ZenMarket URL" label → "URL замовлення"
    - Keep type="url", keep optional

  "Доставка / комісії по Японії, JPY" → wrap in div id="purJapanFeesRow"
    - Hidden by default for non-JP channels
    - Label: "Комісії / доставка, JPY"

  Add new row (visible for all channels, after JPY row):
  <div class="form-row" id="purUaDeliveryRow">
    <label>Доставка UA, грн</label>
    <input type="number" min="0" step="0.01" id="purUaDelivery">
  </div>

### 4. onPurChannelChange() — new JS function

Add to script section:

  function onPurChannelChange() {
    const ch = document.getElementById('purSupplierChannel').value;
    const isJP = ch === 'zenmarket_jp';
    document.getElementById('purJapanFeesRow').style.display = isJP ? '' : 'none';
    // ref label hint
    const refLabel = document.querySelector('label[for-pur-ref]');
    if (refLabel) refLabel.textContent = isJP
      ? 'Референс / № замовлення *'
      : 'Референс / № замовлення (авто, якщо порожньо)';
  }

Call onPurChannelChange() once during accounting page init to set correct initial state.

### 5. submitPurchase() — update (line ~2147)

Current validation:
  const order_ref = editValue('purOrderRef').trim();
  if (!order_ref || ...) { ... return; }

New validation:
  const supplier_channel = editValue('purSupplierChannel') || 'zenmarket_jp';
  const order_ref = editValue('purOrderRef').trim();
  if (supplier_channel === 'zenmarket_jp' && !order_ref) {
    formMsg('purMsg', '⚠ Вкажи ZenMarket Order №', 'error');
    return;
  }

Add new fields to the callPost payload:
  await callPost({
    action: 'add_purchase',
    supplier_channel: supplier_channel,
    order_ref: order_ref,
    total_cost: total_cost,
    japan_fees_jpy: Number(editValue('purJapanFees')) || 0,
    ukraine_delivery_uah: Number(editValue('purUaDelivery')) || 0,
    status: editValue('purStatus'),
    order_url: editValue('purOrderUrl'),
    note: editValue('purNote'),
    items: items
  });

### 6. Default status — update (line ~1967)

Old:
  formSelect('purStatus', PURCHASE_STATUSES, 'Виграно')

New:
  formSelect('purStatus', PURCHASE_STATUSES, 'Замовлено')

### 7. Batch-update panel — filter to ZenMarket lots only

Location: function near "selectedPurchaseLots" / "batchUkraineDeliveryJpy" (~line 2429)

In the function that renders lot checkboxes for the batch panel:
Filter rows so only rows with supplier_channel === 'zenmarket_jp' (or null/undefined for old rows) are shown.

  const zpRows = rows.filter(function(row) {
    return !row.supplier_channel || row.supplier_channel === 'zenmarket_jp';
  });
  // use zpRows instead of rows for rendering the batch lot list

Add a note above the batch panel:
  <div style="font-size:11px;color:var(--muted);margin-bottom:8px">
    Тільки ZenMarket лоти (JP комісії та доставка)
  </div>

### 8. Recent purchases table — add Постачальник column

In the columns definition for purchases (line ~2221):
  columns: [['order_ref','Order Ref'],['sku','SKU'],['qty','К-сть'],['japan_fee_jpy','JP-комісія, JPY'],['supplier_channel','Канал'],['status','Статус']]

---

## Do NOT touch

- Sale form (Внести продаж)
- Write-off form (Внести списання)
- Orders, Stock, SKU, Clients, Roadmap pages
- API config (const API, const TOKEN)
- callPost(), call() functions
- Any OpenCart-related code

---

## Acceptance criteria

- [ ] PURCHASE_STATUSES = ['Замовлено','В дорозі','На складі UA','Скасовано'], default = 'Замовлено'
- [ ] "Постачальник / Канал" select is first field in purchase form
- [ ] When channel = ZenMarket: JPY row visible, ref required, label shows "*"
- [ ] When channel = Постачальник UA: JPY row hidden, ref not required, hint "авто, якщо порожньо"
- [ ] "Доставка UA, грн" field visible for all channels
- [ ] submitPurchase() sends supplier_channel and ukraine_delivery_uah in payload
- [ ] submitPurchase() does NOT block submit when order_ref empty + channel = supplier_ua
- [ ] Batch-update panel shows only zenmarket_jp lots (or old lots with no channel)
- [ ] Recent purchases table has "Канал" column

---

## QA checklist

1. Open Облік → Внести закупку
2. Channel = ZenMarket: JPY field visible, ref field shows "*"
3. Try submit with empty ref + ZenMarket → error shown
4. Switch to Постачальник UA: JPY field hidden, ref hint changes
5. Submit with empty ref + UA channel → submits OK (auto-ref on backend)
6. Submit with "Доставка UA, грн" = 200 → check value in sheet via CRM
7. Check PURCHASE_STATUSES dropdown: 4 values only, default "Замовлено"
8. Open batch-update panel (Облік → Закупки → select lots) → verify no UA lots appear

---

## Rollback note

booster-dashboard.html is a standalone file. Keep a backup copy before applying patch.
Rollback = restore previous file version.

---

## OC4 reminders (N/A — this is dashboard HTML, not OpenCart)

Not applicable.

---

## Roadmap update after completion

CRM-001 → Done in Notion. Owner does QA with live UA supplier purchase entry.
