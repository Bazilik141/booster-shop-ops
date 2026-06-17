# DASH-WRITE-001 — Apps Script Audit

Date: 2026-06-17
Source: CRM spreadsheet `Apps_Script_код` mirror tab
Scope: audit only, no code changes

## Summary

- `doPost(e)` is reserved for Telegram webhook updates and OpenCart order-sync.
- `doPost(e)` does **not** route `add_sale`, `add_writeoff`, or `add_procurement`.
- `addSale()`, `addPurchase()`, and `addWriteOff()` already exist, but all three are zero-argument UI functions that read data from Google Sheets form tabs, not from `e.postData`.
- `sku_list` already exists on `doGet` and returns a payload with `skus: [...]`; each entry includes at least `sku` and `name` plus extra fields.

## Evidence

### A. `doPost(e)`

Location: `Apps_Script_код!A751:A790`

Signature:

```js
function doPost(e) {
```

First lines:

```js
function doPost(e) {
resetMemo_(); let isTelegramUpdate = false; try {
const raw = e && e.postData && e.postData.contents ? e.postData.contents : '{}';
const payload = JSON.parse(raw);
isTelegramUpdate = !!(payload.message || payload.callback_query);
if (isTelegramUpdate) {
  ...
  return HtmlService.createHtmlOutput('ok');
}

const expectedToken = getBoosterCrmToken_();
if (!expectedToken || expectedToken === 'CHANGE_ME' || payload.token !== expectedToken) {
  return boosterCrmJson_({ ok: false, error: 'bad token' });
}
const ss = _getCrmSs();
const lock = LockService.getScriptLock(); if (!lock.tryLock(30000)) return boosterCrmJson_({ ok: false, error: 'crm busy, retry later' });
try { const result = upsertOpenCartOrder_(ss, payload); return boosterCrmJson_({ ok: true, result: result }); } finally { lock.releaseLock(); }
```

Conclusion:

- No action switch exists inside `doPost(e)`.
- No `add_sale`, `add_writeoff`, or `add_procurement` routing exists.
- Current non-Telegram `doPost(e)` path is single-purpose: `upsertOpenCartOrder_(ss, payload)`.

### B. `addSale()`

Location: `Apps_Script_код!A30:A55`

Signature:

```js
function addSale() {
```

First lines:

```js
function addSale() {
resetMemoForMutation_(); const ss = SpreadsheetApp.getActive();
const formSheet = ss.getSheetByName('Внести_продаж');
const sales = ss.getSheetByName('Продажі');
const form = readFormRange_(formSheet, 'A4:B20');
const itemRows = formSheet.getRange('A21:E30').getValues();
const items = itemRows
  .filter(row => row[0] && num_(row[1]) > 0)
  .map(row => ({ sku: parseSku_(row[0]), qty: num_(row[1]), price: num_(row[2]), note: row[4] || '' }));
...
if (!items.length) {
  SpreadsheetApp.getUi().alert('Додай хоча б один SKU у таблицю позицій.');
  return;
}
```

Conclusion:

- Signature is zero-arg.
- Data source is the sheet form tab `Внести_продаж`.
- It is designed for menu/UI invocation, not API POST payloads.
- Validation exists in-sheet: at minimum requires one SKU row; there is also special validation for mystery box component rows.

### C. `addPurchase()`

Location: `Apps_Script_код!A107:A132`

Signature:

```js
function addPurchase() {
```

First lines:

```js
function addPurchase() {
resetMemoForMutation_(); const ss = SpreadsheetApp.getActive();
const formSheet = ss.getSheetByName('Внести_закупку');
const purchases = ss.getSheetByName('Закупки');
const form = readFormRange_(formSheet, 'A4:B9');
const order = String(form['ZenMarket Order №'] || '').trim(); const orderUrl = String(form['ZenMarket URL'] || '').trim();
const totalCost = num_(form['Загальна вартість лоту, грн']);
...
const items = formSheet.getRange('A12:E14').getValues()
  .filter(row => row[0] && num_(row[1]) > 0)
  .map(row => ({ sku: parseSku_(row[0]), qty: num_(row[1]), cost: num_(row[2]), manualCost: row[3], note: row[4] || '' }));
...
if (!order || isBlank_(form['Загальна вартість лоту, грн']) || !items.length) { SpreadsheetApp.getUi().alert('Заповни ZenMarket Order №, загальну вартість лоту і хоча б один SKU з кількістю.'); return; }
```

Conclusion:

- Procurement function already exists.
- Actual function name is `addPurchase()`, not `addProcurement()`.
- Signature is zero-arg.
- Data source is the sheet form tab `Внести_закупку`.
- Validation exists for order number, total lot cost, at least one line item, positive quantity, and line-cost sum matching lot total.

### D. `addWriteOff()`

Location: `Apps_Script_код!A245:A270`

Signature:

```js
function addWriteOff() {
```

First lines:

```js
function addWriteOff() {
resetMemoForMutation_(); const ss = SpreadsheetApp.getActive();
const formSheet = ss.getSheetByName('Внести_списання');
const form = readForm_('Внести_списання');
const writeOffs = ss.getSheetByName('Списання');
const batchValues = formSheet.getRange('A13:D22').getValues();
const lines = [];
const hasBatchLines = batchValues.some(function(row) { return !!row[0] || !!row[1] || !!row[3]; });
...
SpreadsheetApp.getUi().alert('У рядку ' + (13 + i) + ' вкажи SKU або очисти кількість/примітку.');
```

Conclusion:

- Signature is zero-arg.
- Data source is the sheet form tab `Внести_списання`.
- It supports multi-line write-off input from the form sheet.
- Validation exists for row completeness and positive quantity.

### E. Menu wiring

Location: `Apps_Script_код!A10:A27`

Evidence:

```js
function onOpen() {
SpreadsheetApp.getUi()
  .createMenu('Booster CRM')
  .addItem('Додати продаж', 'addSale')
  .addItem('Додати закупку', 'addPurchase')
  ...
  .addItem('Додати списання', 'addWriteOff')
```

Conclusion:

- These write functions are currently wired as Spreadsheet UI menu actions.
- They are not exposed through `doPost(e)` today.

### F. `sku_list`

Routing:

- `Apps_Script_код!A721`: `if (action === 'sku_list') return apiSkuList_(params);`

Implementation:

Location: `Apps_Script_код!A1448:A1460`

```js
function apiSkuList_(params, salesRows) { params = params || {};
const ss = _getAutoSs();
const objects = apiSheetObjects_(ss.getSheetByName('Майстер_Товарів'), ['SKU']); const metrics = apiSkuProfitMetrics_(salesRows); const stockMetrics = apiSkuStockMetrics_(); const rrcMetrics = apiSkuRrcMetrics_();
const skus = [];
objects.rows.forEach(function(row) {
const sku = apiObjVal_(row, ['SKU', 'Артикул']);
if (!sku) return;
...
const skuName = row['Назва'] || row['Назва товару'] || row['Повна назва на сайті'] || '';
...
skus.push({ sku: sku, name: skuName, full_name: row['Повна назва на сайті'] || skuName, ... });
});
...
return { ok: true, count: resultSkus.length, skus: resultSkus };
}
```

Conclusion:

- `action=sku_list` is already available on GET.
- Response shape is not just `{sku, name}`; it returns `{ ok, count, skus }`.
- Each item in `skus` contains at least `sku` and `name`, plus many extra fields.

## Answer to Phase 1 questions

- `addSale()` signature: `function addSale()`
- `addWriteOff()` signature: `function addWriteOff()`
- Procurement function exists and is named `addPurchase()`
- `doPost` routes write actions: no
- Existing `doPost` purpose: Telegram webhook + OpenCart order-sync only
- `sku_list` ready for dashboard dropdown: yes, and richer than required

## Recommendation for Phase 2

- Do not overload the current `doPost(e)` order-sync branch directly.
- Add explicit action routing for write endpoints while preserving current token check and OpenCart path.
- Map dashboard UI payloads to dedicated wrappers such as `apiAddSale_(payload)`, `apiAddPurchase_(payload)`, and `apiAddWriteOff_(payload)` instead of trying to call the current zero-arg menu functions as-is.
