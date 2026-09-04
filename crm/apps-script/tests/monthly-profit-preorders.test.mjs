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

const context = vm.createContext({ String });
vm.runInContext(functionSource('crmOrderMatchesStatus_') + '\nglobalThis.matches = crmOrderMatchesStatus_;', context, { filename: 'Code.gs' });

const active = (order) => context.matches(order, 'active');
assert.equal(active({ order_status: 'Передзамовлення', payment_status: 'Оплачено' }), true, 'paid preorder remains visible as an active order');
assert.equal(active({ order_status: 'Передзамовлення', payment_status: 'Не оплачено' }), true, 'unpaid preorder remains visible as an active order');
assert.equal(active({ order_status: 'Нове', payment_status: 'Оплачено' }), true, 'a paid new order remains visible as active');
assert.equal(active({ order_status: 'В обробці', payment_status: 'Оплачено' }), true, 'a paid processing order remains visible as active');
assert.equal(active({ order_status: 'Відправлено', payment_status: 'Не оплачено' }), true);
assert.equal(active({ order_status: 'Отримано', payment_status: 'Оплачено' }), false, 'completed orders remain outside the active list');
assert.equal(active({ order_status: 'Скасовано', payment_status: 'Не оплачено' }), false, 'cancellations remain outside the active list');

const monthly = functionSource('apiMonthlySummary_');
assert.match(monthly, /month_to_date:\s*apiAggregateSalesRows_\(rows, currentStart, currentEnd\)/, 'month card uses the same cost-confirmed source as the graph');
assert.match(monthly, /previous_month_to_date:\s*apiAggregateSalesRows_\(rows, previousStart, previousEnd\)/, 'comparison period uses the same source');
assert.doesNotMatch(monthly, /setValue|setValues|appendRow|deleteRow/, 'monthly summary remains read-only');
assert.match(code, /action === 'monthly_summary'\) return 'bscrm_v2_' \+ version \+ '_' \+ action \+ '_v3'/, 'the new monthly payload uses a fresh server cache key after publication');
assert.match(code, /action === 'overview_assets'\) return 'bscrm_v2_' \+ version \+ '_' \+ action \+ '_v1'/, 'asset tiles use the cache-version key, so a purchase invalidation takes effect immediately');
assert.doesNotMatch(code, /action === 'overview_assets'\) return 'bscrm_overview_assets_v1'/, 'asset tiles must not retain the fixed cache key');
assert.match(code, /const cacheKey = 'crm_orders_v4_'/, 'the order list uses a fresh server cache key after publication');

const assetCacheContext = vm.createContext({
  String,
  _memo: {},
  PropertiesService: { getScriptProperties: () => ({ getProperty: () => 'purchase-change-1' }) },
});
vm.runInContext(functionSource('apiDoGetCacheVersion_') + '\n' + functionSource('apiDoGetCacheKey_') + '\nglobalThis.assetCacheKey = apiDoGetCacheKey_;', assetCacheContext, { filename: 'Code.gs' });
assert.equal(assetCacheContext.assetCacheKey('overview_assets', {}), 'bscrm_v2_purchase-change-1_overview_assets_v1', 'overview asset cache key changes after invalidateDoGetCache_ advances the version');

console.log('Monthly profit and preorder visibility tests passed');
