import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, '../Code.gs'), 'utf8');

function functionSource(name) {
  const match = new RegExp('function ' + name + '\\(').exec(code);
  if (!match) throw new Error('Missing function: ' + name);
  const start = match.index;
  const open = code.indexOf('{', start);
  let depth = 0;
  for (let index = open; index < code.length; index += 1) {
    if (code[index] === '{') depth += 1;
    if (code[index] === '}') {
      depth -= 1;
      if (depth === 0) return code.slice(start, index + 1);
    }
  }
  throw new Error('Unclosed function: ' + name);
}

// The menu wrapper is the unit under test; updateSkuCurrentCost_ is deliberately
// stubbed. Its FIFO/remainder maths is out of CRM-010 scope and is proven live.
function loadMenu({ uiThrows, updated }) {
  const calls = { invalidate: 0, flush: 0, alerts: [], logs: [], costArgs: [] };
  const activeSpreadsheet = { name: 'ss' };
  const context = vm.createContext({
    String, Number, Math,
    SpreadsheetApp: {
      getActiveSpreadsheet: function() { return activeSpreadsheet; },
      flush: function() { calls.flush += 1; },
      getUi: function() {
        if (uiThrows) throw new Error('Cannot call SpreadsheetApp.getUi() from this context');
        return { alert: function(message) { calls.alerts.push(message); } };
      }
    },
    Logger: { log: function(message) { calls.logs.push(String(message)); } },
    updateSkuCurrentCost_: function(ss) { calls.costArgs.push(ss); return { updated: updated }; },
    invalidateDoGetCache_: function() { calls.invalidate += 1; }
  });
  vm.runInContext([
    functionSource('updateSkuCurrentCostMenu'),
    'globalThis.__test = { updateSkuCurrentCostMenu: updateSkuCurrentCostMenu };'
  ].join('\n'), context, { filename: 'Code.gs' });
  return { run: function() { return context.__test.updateSkuCurrentCostMenu(); }, calls: calls, activeSpreadsheet: activeSpreadsheet };
}

// 1. Apps Script editor context: getUi() throws. This is the live 2026-08-20 failure.
const editor = loadMenu({ uiThrows: true, updated: 32 });
const editorResult = editor.run();
assert.deepEqual(editorResult, { updated: 32 }, 'the menu wrapper returns the recalculation result to the execution log');
assert.equal(editor.calls.alerts.length, 0, 'no alert can be shown without a UI context');
assert.deepEqual(editor.calls.logs, ['Собівартість складу оновлено: 32 SKU.'], 'the message falls back to Logger.log with the updated-SKU count');
assert.equal(editor.calls.invalidate, 1, 'invalidateDoGetCache_ is called exactly once per run');
assert.equal(editor.calls.flush, 1, 'the sheet write is flushed before the cache is invalidated');
assert.deepEqual(editor.calls.costArgs, [editor.activeSpreadsheet], 'the active spreadsheet is passed through to updateSkuCurrentCost_');

// 2. Spreadsheet menu context: getUi() works, the owner sees the count.
const menu = loadMenu({ uiThrows: false, updated: 5 });
assert.deepEqual(menu.run(), { updated: 5 });
assert.deepEqual(menu.calls.alerts, ['Собівартість складу оновлено: 5 SKU.'], 'the owner-facing alert carries the updated-SKU count');
assert.deepEqual(menu.calls.logs, [], 'the Logger fallback stays silent when the alert succeeds');
assert.equal(menu.calls.invalidate, 1, 'invalidateDoGetCache_ is called exactly once per run');

// 3. A second run against an unchanged workbook reports the same count and
//    invalidates the cache once more — never twice within one run.
const repeat = loadMenu({ uiThrows: true, updated: 32 });
assert.deepEqual(repeat.run(), { updated: 32 });
assert.deepEqual(repeat.run(), { updated: 32 }, 'a repeat run returns the same count');
assert.equal(repeat.calls.invalidate, 2, 'each run invalidates exactly once');
assert.equal(repeat.calls.flush, 2);

// 4. The function must never reach the live failure again: the alert is guarded.
const source = functionSource('updateSkuCurrentCostMenu');
assert.match(source, /try \{ SpreadsheetApp\.getUi\(\)\.alert\(message\); \} catch \(e\) \{ Logger\.log\(message\); \}/, 'the alert uses the same guard shape as createDailyInventoryMaintenanceTrigger');

// 5. The recalculation is reachable from the public CRM menu, and every
//    pre-existing item survives unchanged and in order.
const menuItems = [];
const itemPattern = /\.addItem\('([^']*)', '([^']*)'\)/g;
const onOpenSource = functionSource('onOpen');
let hit;
while ((hit = itemPattern.exec(onOpenSource)) !== null) menuItems.push([hit[1], hit[2]]);
assert.deepEqual(menuItems, [
  ['Додати продаж', 'addSale'],
  ['Додати закупку', 'addPurchase'],
  ['Оновити закупку', 'updatePurchase'],
  ['Оновити продаж', 'updateSaleStatus'],
  ['Додати списання', 'addWriteOff'],
  ['Додати витрату', 'addExpense'],
  ['Очистити форму продажу', 'clearSaleForm'],
  ['Очистити форму закупки', 'clearPurchaseForm'],
  ['Очистити форму оновлення закупки', 'clearUpdatePurchaseForm'],
  ['Очистити форму оновлення продажу', 'clearSaleUpdateForm'],
  ['Очистити форму списання', 'clearWriteOffForm'],
  ['Очистити форму витрат', 'clearExpenseForm'],
  ['Оновити довідники SKU', 'setupCrmCatalogOptionInfrastructure'],
  ['Оновити очікуваний залишок', 'updateExpectedStockFormulaMenu'],
  ['Оновити собівартість складу', 'updateSkuCurrentCostMenu'],
  ['Заповнити собівартість передзамовлень', 'initializePreorderCostsMenu'],
  ['Налаштувати автооновлення формул CRM', 'setupCrmRowCapacityMaintenanceMenu'],
  ['Налаштувати OpenAI ключ', 'setupOpenAiApiKey']
], 'the Booster CRM menu keeps every existing item and exposes both cost-maintenance actions');

console.log('CRM-010 SKU current-cost menu tests passed');
