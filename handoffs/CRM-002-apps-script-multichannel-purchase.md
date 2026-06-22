# CRM-002 — Apps Script: Multi-channel purchase API

**Date:** 2026-06-22
**Task ID:** CRM-002
**Depends on:** nothing (do first)
**Blocks:** CRM-001
**Executor:** Codex
**Risk:** Medium — touches apiAddPurchase_, apiSummary_, Закупки sheet structure

---

## Goal

Extend the purchase API and CRM sheet to support multiple sourcing channels (ZenMarket JP, UA supplier, other).
Remove hard-coded ZenMarket assumptions from the backend.

---

## Context

Current `apiAddPurchase_` (doPost → add_purchase) accepts:
  order_ref (required), total_cost, japan_fees_jpy, status, order_url, note, items[]

It writes to the Закупки sheet. `order_ref` validation blocks submissions without a ZenMarket number.

`apiSummary_` classifies lots by status string to compute warehouse_cost and asset_cost.
Current classification (as of DASH-001 fix):
  - warehouse (UA): "На складі", "Частково продано", "Продано"
  - non-UA asset: "Виграно", "На складі в Японії", "В дорозі"
  - excluded: "Скасовано"

New statuses (decided by owner): Замовлено · В дорозі · На складі UA · Скасовано

---

## Files / zones

- Apps_Script_код tab in CRM spreadsheet (mirror only — Codex pastes into bound Apps Script)
- Sheet: Закупки
- Functions: apiAddPurchase_, apiSummary_, apiRecentPurchases_ (recent_purchases endpoint)

---

## Required changes

### 1. Закупки sheet — add 2 columns via script

Append to the RIGHT of all existing columns (do NOT insert in the middle — breaks column-index reads).

  Column: "Постачальник"     — text   — values: zenmarket_jp / supplier_ua / other
  Column: "Доставка UA, грн" — number — UA delivery cost in UAH, 0 if none

Add headers exactly as above to row 1 of the Закупки sheet.
Use .appendColumn or find last used column and write headers there.

### 2. apiAddPurchase_(payload) — update

Accept new optional fields from payload:
  - supplier_channel       — string, default "zenmarket_jp" if absent (backward compat)
  - ukraine_delivery_uah   — number, default 0

Auto-generate order_ref when channel is not zenmarket_jp:

  if (!order_ref && supplier_channel !== 'zenmarket_jp') {
    var now = new Date();
    var ymd = Utilities.formatDate(now, 'Europe/Kiev', 'yyyyMMdd');
    order_ref = 'UA-' + ymd + '-' + String(now.getTime()).slice(-4);
  }

Validation: require order_ref ONLY when supplier_channel === 'zenmarket_jp'.
For other channels: order_ref is auto-generated above, so it will always be present.

Write 2 new values at the end of each row pushed to Закупки:
  row.push(supplier_channel);        // column: Постачальник
  row.push(ukraine_delivery_uah);    // column: Доставка UA, грн

### 3. apiSummary_ — update status classification

Replace current status string checks with the mapping below.
BOTH old and new statuses must be supported (backward compat for existing rows in sheet):

  var UA_WAREHOUSE_STATUSES = ['На складі UA', 'На складі', 'Частково продано', 'Продано'];
  var NON_UA_ASSET_STATUSES  = ['Замовлено', 'В дорозі', 'Виграно', 'На складі в Японії'];
  var EXCLUDED_STATUSES      = ['Скасовано'];

Logic:
  - UA_WAREHOUSE_STATUSES → include in FIFO warehouse_cost (units physically in UA)
  - NON_UA_ASSET_STATUSES → include in non-UA portion of asset_cost
  - EXCLUDED_STATUSES or unrecognized → skip both

Do NOT remove "Продано" / "Частково продано" / "Виграно" / "На складі в Японії" from the arrays.
These values exist in current sheet data and must still be classified correctly.

### 4. apiRecentPurchases_ (recent_purchases endpoint)

Add supplier_channel field to each row object in the response.
Read it from the new Постачальник column.
If column is missing or empty, return "zenmarket_jp" as default.

---

## Do NOT touch

- doPost Telegram branch
- upsertOpenCartOrder_ (OpenCart sync)
- apiAddSale_, apiAddWriteOff_
- Any existing column positions in Закупки (append only, never insert)
- addSale(), addPurchase(), addWriteOff() UI menu functions
- sku_list, orders, stock_alerts GET endpoints

---

## Acceptance criteria

- [ ] Закупки sheet has 2 new header columns: Постачальник, Доставка UA, грн
- [ ] POST add_purchase { supplier_channel:"supplier_ua", order_ref:"" } → auto-generated ref UA-YYYYMMDD-XXXX saved to sheet
- [ ] POST add_purchase { supplier_channel:"zenmarket_jp", order_ref:"" } → { ok:false, error:"..." } (unchanged behavior)
- [ ] POST add_purchase with supplier_ua → supplier_channel and ukraine_delivery_uah written to new columns
- [ ] GET action=summary — "На складі UA" rows → warehouse_cost; "Замовлено" rows → non-UA asset
- [ ] GET action=summary — old "На складі" / "Виграно" rows still classified correctly
- [ ] GET action=recent_purchases — each row includes supplier_channel field
- [ ] No regression on summary, orders, stock_alerts, sku_list

---

## QA checklist

1. POST { action:"add_purchase", supplier_channel:"supplier_ua", order_ref:"", total_cost:500, items:[...] }
   → Verify ref appears as UA-20260622-XXXX in Закупки
2. POST { action:"add_purchase", supplier_channel:"zenmarket_jp", order_ref:"", total_cost:500, items:[...] }
   → Expect { ok:false } error
3. POST { action:"add_purchase", supplier_channel:"supplier_ua", ukraine_delivery_uah:150, ... }
   → Verify "Доставка UA, грн" column = 150 in sheet
4. GET action=summary → compare warehouse_cost with a manual count of "На складі UA" + "На складі" rows
5. Manually add a row with status "Замовлено" → GET summary → check non-UA portion increases
6. GET action=recent_purchases → verify supplier_channel field present

---

## Rollback note

If apiSummary_ produces wrong values: revert only the status array definitions to prior strings.
New sheet columns are append-only; safe to keep even on rollback — they don't affect existing reads.

---

## Roadmap update after completion

CRM-002 → Done in Notion. Unblock CRM-001.
