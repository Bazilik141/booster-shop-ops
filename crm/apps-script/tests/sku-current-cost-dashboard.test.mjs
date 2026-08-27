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

function sheet(rows) {
  return {
    getLastRow: function() { return rows.length + 2; },
    getRange: function() { return { getValues: function() { return rows; } }; }
  };
}

const currentStock = [
  ['ACTIVE-001', '', '', '', '', '', '', '', 420, 515.25],
  ['LAST-LOT-001', '', '', '', '', '', '', '', '', ''],
  ['NO-COST-001', '', '', '', '', '', '', '', '', '']
];
const purchases = [
  ['LOT-1', '', '', new Date('2026-08-01'), 'ACTIVE-001', '', '', 2, '', '', '', 800, 400, '', 1000, 500, 'На складі'],
  ['LOT-2', '', '', new Date('2026-07-01'), 'LAST-LOT-001', '', '', 2, '', '', '', 600, 300, '', 700, 350, 'Продано'],
  ['LOT-3', '', '', new Date('2026-08-02'), 'LAST-LOT-001', '', '', 3, '', '', '', 1800, 600, '', 2100, 700, 'Частково продано'],
  ['LOT-4', '', '', new Date('2026-08-03'), 'LAST-LOT-001', '', '', 1, '', '', '', 900, 900, '', 1000, 1000, 'В дорозі'],
  ['LOT-5', '', '', new Date('2026-08-04'), 'NO-COST-001', '', '', 1, '', '', '', '', '', '', '', '', 'Продано']
];
const spreadsheet = { getSheetByName: function(name) { return name === 'Склад' ? sheet(currentStock) : (name === 'Закупки' ? sheet(purchases) : null); } };
const context = vm.createContext({
  String, Number, Math, Date, Object,
  _getCrmSs: function() { return spreadsheet; },
  num_: function(value) { const number = Number(value); return Number.isFinite(number) ? number : 0; },
  round2_: function(value) { return Math.round((Number(value) + Number.EPSILON) * 100) / 100; },
  dateSortValue_: function(value) { return value instanceof Date ? value.getTime() : 0; }
});
vm.runInContext(functionSource('apiSkuCurrentCostMetrics_') + '\nglobalThis.metrics = apiSkuCurrentCostMetrics_();', context, { filename: 'Code.gs' });
const metrics = JSON.parse(JSON.stringify(context.metrics));

assert.deepEqual(metrics['ACTIVE-001'], { cost: 515.25, source: 'warehouse_fifo' }, 'active inventory uses the stored current FIFO cost from Склад J');
assert.deepEqual(metrics['LAST-LOT-001'], { cost: 700, source: 'last_fifo_lot' }, 'without a warehouse cost, the most recent eligible CRM lot is used instead of an all-time average');
assert.equal(metrics['NO-COST-001'], undefined, 'a SKU with no calculable cost remains absent so the dashboard can show a dash');
assert.match(functionSource('apiSkuList_'), /current_cost: currentCostMetric \? currentCostMetric\.cost : null/, 'sku_list returns the current cost as a dedicated nullable field');

console.log('SKU current-cost dashboard tests passed');
