import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const repo = path.resolve(here, '..');
const dashboard = fs.readFileSync(path.join(repo, 'dashboard', 'booster-dashboard.html'), 'utf8');
const api = fs.readFileSync(path.join(repo, '3d-print', 'apps-script-3dp-api', 'Code.gs'), 'utf8');
const script = dashboard.match(/<script>([\s\S]*)<\/script>/i)?.[1];

test('3D-P dashboard inline script compiles and every information render uses the defined attention renderer', () => {
  assert.ok(script, 'dashboard inline script is present');
  new Function(script);
  assert.equal(script.includes('renderThreeDpAttention('), false, 'obsolete undefined renderer reference must be absent');
  assert.equal((script.match(/function threeDpAttention\(/g) || []).length, 1, 'attention renderer must have one definition');
  assert.match(script, /function renderThreeDpInformation\(\)\s*\{\s*const records=threeDpState\.skus\.map\(threeDpInfoRecord\);threeDpAttention\(records\);/);
  for (const caller of ['async function load3dPrint()', 'async function saveThreeDpProduct()', 'async function saveThreeDpStock()']) {
    assert.equal(script.includes(caller), true, `normal action path exists: ${caller}`);
  }
  assert.equal((script.match(/renderThreeDpAll\(\);/g) || []).length >= 2, true, 'post-save paths re-render all three zones through the corrected renderer');
});

test('stock-adjustment ledger uses the exact API response header for Delta', () => {
  assert.match(api, /STOCK_ADJUSTMENT_HEADERS_3DP[\s\S]*?'Зміна наявності, шт'/);
  assert.match(api, /return \{ action: '3dp_stock_adjustments', rows: rows/);
  assert.match(script, /threeDpEsc\(x\['Зміна наявності, шт'\]\)/);
  assert.equal(script.includes("threeDpEsc(x.Delta)"), false, 'nonexistent Delta response key must be absent');
});
test('every product mutation refreshes API-backed state and all 3D-P zones', () => {
  const product = script.match(/async function saveThreeDpProduct\(\)[\s\S]*?\n}\nasync function toggleThreeDpArchive/)[0];
  const archive = script.match(/async function toggleThreeDpArchive\(\)[\s\S]*?\n}\s*function renderThreeDpStock/)[0];
  const stock = script.match(/async function saveThreeDpStock\(\)[\s\S]*?\n}\n/)[0];

  assert.equal((product.match(/await reloadThreeDpData\(\);/g) || []).length, 2, 'new and existing SKU saves must reload all API data');
  assert.equal((product.match(/renderThreeDpAll\(\);/g) || []).length, 2, 'new and existing SKU saves must re-render every zone');
  assert.match(archive, /await reloadThreeDpData\(\);[\s\S]*?renderThreeDpAll\(\);/);
  assert.match(stock, /await reloadThreeDpData\(\);await loadThreeDpLedger\(row\.SKU\);[\s\S]*?renderThreeDpAll\(\);/);
});