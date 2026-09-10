import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const source = fs.readFileSync(new URL('../Code.gs', import.meta.url), 'utf8');

test('CRM-011 Apps Script source compiles', () => {
  assert.doesNotThrow(() => new Function(source));
});

test('append-only date columns are guarded and both mutation paths stamp them', () => {
  assert.match(source, /setupCrm011FinanceColumns/);
  assert.match(source, /\['Закупки', 'Дата створення'\], \['Продажі', 'Дата оплати'\], \['Продажі', 'Фіскальний чек'\]/);
  assert.match(source, /CRM-011_SETUP_REQUIRED/);
  assert.match(source, /crm011ApiAddSale_/);
  assert.match(source, /crm011ApiAddPurchase_/);
  assert.match(source, /crm011ApiUpdateSale_/);
  assert.match(source, /payment_dates_backfilled/);
});

test('finance uses canonical profit, cashflow sources, assets, and bounded cache', () => {
  assert.match(source, /finance_report: 180/);
  assert.match(source, /num_\(row\[21\]\)/);
  assert.match(source, /row\[11\]/);
  assert.match(source, /crm011ZenmarketTopups_/);
  assert.match(source, /crm011ExpenseCashflow_/);
  assert.match(source, /outside_stock:/);
  assert.match(source, /missing_values_are_not_zero: true/);
  assert.match(source, /_getCrmSalesRows\(\)/);
});

test('follow-up contracts include fiscal blocker, date audit, games and arrival map', () => {
  assert.match(source, /FISCAL_RECEIPT_REQUIRED/);
  assert.match(source, /payment_date_audit/);
  assert.match(source, /paid_orders_found/);
  assert.match(source, /payment_status_counts/);
  assert.match(source, /scan_rows: 500/);
  assert.match(source, /future_payment_dates_count/);
  assert.match(source, /orders_sample: result\.orders\.slice\(0, 10\)/);
  assert.match(source, /games:/);
  assert.match(source, /arrivals_by_sku/);
});

test('client and order contracts expose identity, segmentation and lazy history', () => {
  assert.match(source, /client_identity_source/);
  assert.match(source, /client_type/);
  assert.match(source, /function apiClientOrders_/);
  assert.match(source, /vip_profit_percentile: 0\.90/);
  assert.match(source, /low_margin_ltv: 5000/);
});

test('JPY fallback is 3.2 in both fallback branches', () => {
  const start = source.indexOf('function getCurrencyRate_(currency)');
  const end = source.indexOf('function allocateAmount_', start);
  const body = source.slice(start, end);
  assert.equal((body.match(/JPY' \? 3\.2/g) || []).length, 2);
  assert.doesNotMatch(body, /3\.5/);
});
