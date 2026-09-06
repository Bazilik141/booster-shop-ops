// Bounded, offline contract probe. No network calls and no spreadsheet writes.
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
const manifest = JSON.parse(read('plans/3dp-catalog-reset-20260902/import-manifest.json'));
const api = read('3d-print/apps-script-3dp-api/Code.gs');
const fifo = read('3d-print/apps-script-3dp-api/CatalogFifo.gs');
const crm = read('crm/apps-script/Code.gs');
const dashboard = read('dashboard/booster-dashboard.html');
const migration3dp = read('scripts/3dp-catalog-reset/Temporary3dpCatalogMigration.gs');
const migrationCrm = read('scripts/3dp-catalog-reset/TemporaryCrmCatalogMigration.gs');
new vm.Script(api, { filename: '3DP Code.gs' });
new vm.Script(crm, { filename: 'CRM Code.gs' });
new vm.Script(migration3dp, { filename: 'Temporary3dpCatalogMigration.gs' });
new vm.Script(migrationCrm, { filename: 'TemporaryCrmCatalogMigration.gs' });
const scripts = [...dashboard.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)].map(m => m[1]).filter(s => s.trim());
scripts.forEach((s, i) => new vm.Script(s, { filename: `dashboard inline ${i}` }));
const context = vm.createContext({});
vm.runInContext(api, context);
const schema = vm.runInContext('({ capacity: ANALYTICS_CALCULATOR_3DP.lastDataRow - ANALYTICS_CALCULATOR_3DP.firstDataRow + 1, createColumns: NOMENCLATURE_OWNER_CREATE_COLUMNS_3DP, formulaColumns: FORMULA_COLUMNS_3DP, categories: Object.keys(NOMENCLATURE_DRAFT_SUGGESTIONS_3DP) })', context);
const errorCode = value => {
  context.input = value;
  try { vm.runInContext("normalizedNomenclaturePriceModelValue3dp_('Q', input)", context); return null; }
  catch (e) { return e.code || e.message; }
};
assert.equal(errorCode('?'), 'NOMENCLATURE_PRICE_INVALID');
assert.equal(errorCode(''), 'NOMENCLATURE_PRICE_INVALID');
const crmContext = vm.createContext({});
vm.runInContext(crm, crmContext);
// Synthetic accounting example: revenue 200, production 80, owner fixtures 10,
// packaging 10. Serhiy materials are already inside 80, so separate ledger=0.
crmContext.entry = { row: 3, values: ['sample', '', '2026-09-02', '', '', 'FIG-TEST-200', '', 1, 200, 0, '', '', '', '', '', 10] };
crmContext.frozen = { production_cost: 80, buyout: 100, profit_share: 0.5 };
crmContext.fixture = { owner_total: 10, owner_per_unit: 10, serhiy_total: 0, serhiy_per_unit: 0 };
const sale = vm.runInContext("crm3dpAccountingSnapshot_(entry, 'sample', frozen, fixture, 'Продаж', 'sample')", crmContext);
const marketing = vm.runInContext("crm3dpAccountingSnapshot_(entry, 'sample', frozen, fixture, 'Маркетинг', 'sample')", crmContext);
assert.equal(sale.serhiy_payout, 130);
assert.equal(marketing.serhiy_payout, 100);
assert.equal(marketing.mgmt_cost, 110);
assert.match(migrationCrm, /salesSnapshots\.forEach\(catalogMigrationCrmRestore_\)/, 'CRM rollback must restore affected sales rows');
assert.match(migrationCrm, /resetOrderComponentCostProjectionBeforeBaseRefresh_[\s\S]+reapplyOrderComponentCostAfterBaseRefresh_/, 'CRM cleanup must reset and reapply component projections');
assert.doesNotMatch(migrationCrm, /Email:|@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/, 'Generated migration must omit customer data');
// The atomic 3D-P commit returns the immutable FIFO unit cost. Exercise the
// actual V2 CRM function with isolated adapters; no Sheets/network operations
// are available here.
crmContext.retryEntry = { row: 3, values: ['sample', '', '2026-09-02', '', '', 'FIG-TEST-200', '', 3, 200, 0, '', '', '', '', '', 15] };
vm.runInContext(`
  crm3dpOrderRows_ = function() { return [retryEntry]; };
  crm3dpConfig_ = function() { return {}; };
  crm3dpSaleRows_ = function() { return []; };
  latest3dpAccountingByRow_ = function() { return {}; };
  crm3dpModeForEntry_ = function() { return 'Продаж'; };
  crm3dpFixtureFrozenForLine_ = function() { return { total_per_unit: 0, owner_per_unit: 0,
    serhiy_per_unit: 0, owner_total: 0, serhiy_total: 0, payer: '' }; };
  crm3dpFrozenSaleInputs_ = function() { return { ok: true, actual_rrp: 200,
    buyout: 150, profit_share: 0.5, fixture_cost: 0, fixture_payer: '',
    owner_fixture_per_unit: 0, serhiy_fixture_per_unit: 0 }; };
  crm3dpFetchJson_ = function() { return { unit_cost_uah: 260 / 3,
    allocation_id: 'ALLOC-TEST', already_applied: true }; };
  append3dpAccountingSnapshot_ = function(ss, snapshot) { retrySnapshot = snapshot; return snapshot; };
  project3dpAccountingToCrm_ = function() {};
  crm3dpAppendJournal_ = function() {};
  var retrySnapshot = null;
  var retryResult = sync3dpSalesV2_({ getParent: function() { return {}; } }, 'sample', [3], 'dashboard', {});
`, crmContext);
assert.equal(crmContext.retryResult.ok, true);
assert.ok(crmContext.retrySnapshot, 'V2 must reach accounting for the existing sale fixture');
const statusMatch = dashboard.match(/async function toggleThreeDpArchive\(\)\s*\{([\s\S]*?)\n\}function/);
assert.ok(statusMatch, 'Archive handler anchor must be verified before drawing conclusions');
const nomenclature = JSON.parse(read('work/3dp-catalog-reset-20260902/live-3dp-nomenclature.json'));
const availability = JSON.parse(read('work/3dp-catalog-reset-20260902/live-3dp-availability.json'));
const availabilityKeys = new Set(availability.values.slice(1).map(r => r[0]).filter(Boolean));
const products = nomenclature.values.slice(1).filter(r => r[0]);
const absentStockKeys = products.filter(r => !availabilityKeys.has(r[0])).map(r => r[0]);
const dedicatedAnalytics = /analyticsSheet:\s*'Аналітика_SKU'/u.test(fifo);
assert.equal(dedicatedAnalytics, true, 'Imported catalogue must use the dedicated dynamic SKU analytics sheet');
assert.match(fifo, /serhiy_consumables_uah/u, 'Actual batch cost must include Serhiy consumables');
assert.match(fifo, /FIFO_RECONCILIATION_REQUIRED/u, 'Direct stock adjustments must be rejected');
const result = {
  mode: 'READ_ONLY_SOURCE_AND_FIXTURE_PROBE',
  syntax: { crm: 'pass', three_dp: 'pass', migration_wrappers: 'pass', dashboard_inline_scripts: scripts.length },
  sample_accounting: { sale_serhiy: sale.serhiy_payout, marketing_serhiy: marketing.serhiy_payout, marketing_management_cost: marketing.mgmt_cost, expected: '130 / 100 / 110; all passed' },
  findings: {
    legacy_analytics_capacity: schema.capacity,
    requested_active: manifest.summary.active,
    dedicated_dynamic_analytics: dedicatedAnalytics,
    pre_migration_stock_projection_missing_skus: absentStockKeys,
    archive_handler_updates_crm: /callPost\(/.test(statusMatch[1]),
    has_case_container_category: schema.categories.some(s => /[Кк]ейс|[Кк]онтейнер|[Кк]оробка/.test(s)),
    quick_create_columns: schema.createColumns,
    rejects_question_mark_price: errorCode('?'),
    rejects_blank_price: errorCode(''),
    actual_batch_cost_includes_serhiy_consumables: /serhiy_consumables_uah/u.test(fifo),
    direct_stock_adjustment_rejected: /FIFO_RECONCILIATION_REQUIRED/u.test(fifo),
    existing_sale_retry_cost: {
      committed_fifo_unit: 260 / 3,
      crm_retry_unit: crmContext.retrySnapshot.production_unit,
      preserves_committed_cost: Math.abs(crmContext.retrySnapshot.production_unit - 260 / 3) < 0.000001,
    },
  },
  limitation: 'Local source and bounded fixtures only. This is not runtime QA, a live integrity_check, or an apply result.',
};
fs.writeFileSync(path.join(root, 'work/3dp-catalog-reset-20260902/contract-probe.json'), JSON.stringify(result, null, 2) + '\n');
console.log(JSON.stringify(result, null, 2));
