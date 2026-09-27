import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const html = fs.readFileSync(new URL('../booster-dashboard.html', import.meta.url), 'utf8');
const scripts = [...html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g)];
assert.ok(scripts.length > 0);
scripts.forEach((match, index) => new vm.Script(match[1], { filename: `dashboard-inline-${index + 1}` }));
assert.match(html, /async function loadInventorySnapshot_\(\)[\s\S]*?call\('inventory_snapshot'\)/);

const stock = html.slice(html.indexOf('async function loadStock()'), html.indexOf('// ════════════ CONSUMABLES', html.indexOf('async function loadStock()')));
assert.match(stock, /loadInventorySnapshot_\(\)/);
assert.doesNotMatch(stock, /loadThreeDpStockOverlay_|call3dp\('3dp_skus'/);
assert.match(html, /projected_deficit\?\?'—'/);
assert.match(html, /i === 'мінусовий_залишок' && r\.physical_stock != null && Number\(r\.physical_stock\) >= 0/);
const products = html.slice(html.indexOf('async function loadSkus()'), html.indexOf('async function ', html.indexOf('async function loadSkus()') + 1));
assert.match(products, /loadInventorySnapshot_\(\)/);
const accounting = html.slice(html.indexOf('async function loadAccountingThreeDpStockOverlay_()'), html.indexOf('function skuTcg_', html.indexOf('async function loadAccountingThreeDpStockOverlay_()')));
assert.match(accounting, /loadInventorySnapshot_\(\)/);
assert.match(accounting, /row\.stock=null;row\.three_dp_stock_error=true/);
console.log('CRM-016 dashboard inventory contract and inline syntax OK');
