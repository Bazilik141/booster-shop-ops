import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const html = fs.readFileSync(new URL('../booster-dashboard.html', import.meta.url), 'utf8');
const inline = [...html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/gi)].map(match => match[1]).filter(Boolean).join('\n');

test('CRM-011 dashboard inline script compiles', () => {
  assert.doesNotThrow(() => new Function(inline));
});

test('finance is lazy-routed and explains cashflow sources honestly', () => {
  assert.match(html, /id="page-finance"/);
  assert.match(inline, /finance: loadFinance/);
  assert.match(inline, /Якщо імпорт поповнень ще не виконано/);
  assert.match(inline, /financeValue_\(value,type\)/);
  assert.doesNotMatch(inline, /loaded\.finance\s*=\s*true/);
});

test('follow-up UI has preorder, managed alerts, fiscal blocker and sortable orders', () => {
  assert.match(html, /id="preordersPreview"/);
  assert.match(html, /id="page-alerts"/);
  assert.match(inline, /set_alert_status/);
  assert.match(inline, /editFiscalReceipt/);
  assert.match(inline, /tableSort/);
});

test('orders use AND filters, client column and a single colspan constant', () => {
  assert.match(inline, /const ORDER_SUMMARY_COLUMN_COUNT = 12/);
  assert.match(inline, /function filterOrderRows_/);
  assert.match(inline, /&&\(!f\.channel/);
  assert.match(html, /<th>Клієнт<\/th>/);
  assert.doesNotMatch(html, /colspan="11"/);
});

test('performance guards dedupe GETs and debounce search', () => {
  assert.match(inline, /const inflightGets = new Map/);
  assert.match(inline, /inflight\.has\(key\)/);
  assert.match(inline, /id==='ordersSearch'\?180:0/);
  assert.match(inline, /dashboardPerf\.lastFinanceMs/);
});

test('stock, clients and roadmap contracts are present', () => {
  assert.match(inline, /function renderSlowStock_/);
  assert.match(inline, /vip|VIP/);
  assert.match(inline, /function apiClientOrders_|client_orders/);
  assert.match(inline, /match\(\/\^\(\.\*\)-\\d\+\$\//);
  assert.match(inline, /roadmapFilteredTasks_/);
});
