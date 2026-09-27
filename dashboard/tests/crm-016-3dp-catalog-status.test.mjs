import assert from 'node:assert/strict';
import fs from 'node:fs';

const html = fs.readFileSync(new URL('../booster-dashboard.html', import.meta.url), 'utf8');
function block(from, to) {
  const start = html.indexOf(from), end = html.indexOf(to, start);
  assert.ok(start >= 0 && end > start, `missing dashboard function: ${from}`);
  return html.slice(start, end);
}

const sku = { SKU:'ACC-3D-TCG-600', row_number:72, API_статус_запису:'Активний' };
const actions = [];
const ui = { product:{ sku:sku.SKU, msg:{} } };
const archiveFactory = new Function('threeDpSku','threeDpUi','threeDpStatus','prompt','confirm',
  'renderThreeDpProducts','call','callPost','cache','call3dpPost','call3dp','reloadThreeDpData','renderThreeDpAll',
  block('async function toggleThreeDpArchive()', 'function threeDpStockAdjustmentForActual(') + '\nreturn toggleThreeDpArchive;');
const archive = archiveFactory(
  () => sku, ui, row => row.API_статус_запису, () => '', () => true,
  () => {}, async () => ({ problems:[] }), async () => { throw new Error('SKU not found in CRM: ACC-3D-TCG-600'); },
  { skus:{} }, async payload => { actions.push(payload); }, async () => {}, async () => {}, () => {});
await archive();
assert.deepEqual(actions.map(action => action.action), ['3dp_nomenclature_archive']);
assert.equal(actions[0].row, 72);
assert.equal(actions[0].expected_status, 'Активний');
assert.match(ui.product.msg.text, /лише в 3D-P/);

let unsafeArchiveCalls = 0;
const archiveOnCrmFailure = archiveFactory(
  () => sku, { product:{ sku:sku.SKU, msg:{} } }, row => row.API_статус_запису,
  () => '', () => true, () => {}, async () => ({ problems:[] }),
  async () => { throw new Error('HTTP 500'); }, {}, async () => { unsafeArchiveCalls++; },
  async () => {}, async () => {}, () => {});
await archiveOnCrmFailure();
assert.equal(unsafeArchiveCalls, 0, 'other CRM failures must not archive the 3D SKU');

const committedUi = { product:{ sku:sku.SKU, msg:{} } };
let committedCrmCalls = 0;
const archiveWithLostResponse = archiveFactory(
  () => sku, committedUi, row => row.API_статус_запису, () => '', () => true,
  () => {}, async () => ({ problems:[] }), async () => { committedCrmCalls++;throw new Error('SKU not found in CRM: ACC-3D-TCG-600'); },
  {}, async () => { throw new Error('Missing token.'); },
  async () => ({ row:{...sku, API_статус_запису:'Архів'} }), async () => {}, () => {});
await archiveWithLostResponse();
assert.equal(committedCrmCalls, 1, 'confirmed 3D archive must not attempt CRM rollback');
assert.equal(committedUi.product.msg.kind, 'success');
assert.match(committedUi.product.msg.text, /підтверджено повторним читанням/);

const unchangedUi = { product:{ sku:sku.SKU, msg:{} } };
const crmStatusCalls = [];
const archiveWithFailedPost = archiveFactory(
  () => sku, unchangedUi, row => row.API_статус_запису, () => '', () => true,
  () => {}, async () => ({ problems:[] }), async payload => { crmStatusCalls.push(payload); },
  {}, async () => { throw new Error('Missing token.'); },
  async () => ({ row:{...sku} }), async () => {}, () => {});
await archiveWithFailedPost();
assert.deepEqual(crmStatusCalls.map(x => x.active), [false,true], 'rollback only after old 3D status is read back');
assert.match(unchangedUi.product.msg.text, /статус не змінено/);

const unknownUi = { product:{ sku:sku.SKU, msg:{} } };
const unknownCrmCalls = [];
const archiveWithUnknownState = archiveFactory(
  () => sku, unknownUi, row => row.API_статус_запису, () => '', () => true,
  () => {}, async () => ({ problems:[] }), async payload => { unknownCrmCalls.push(payload); },
  {}, async () => { throw new Error('Missing token.'); },
  async () => { throw new Error('Read failed'); }, async () => {}, () => {});
await archiveWithUnknownState();
assert.equal(unknownCrmCalls.length, 1, 'unknown 3D status must not trigger CRM rollback');
assert.match(unknownUi.product.msg.text, /Не повторюй дію до звірки SKU/);

const crmCalls = [];
const existing = { SKU:'ACC-3D-DITTO-420', API_статус_запису:'Активний', 'Назва виробу':'Ditto', 'Тип':'Функціональний аксесуар' };
const productUi = { product:{ sku:existing.SKU, msg:{} } };
const sync = new Function('threeDpSku','threeDpUi','threeDpInput','threeDpOptionalPrice',
  'renderThreeDpProducts','threeDpCrmCatalogue_','threeDpCrmSkuFrom_','threeDpCrmRenameCandidates_',
  'threeDpCrmPayload','confirm','call','callPost','cache','runCrmIntegrityCheck',
  block('async function syncSelectedThreeDpProductToCrm()', 'async function saveThreeDpProduct()') + '\nreturn syncSelectedThreeDpProductToCrm;')(
  () => existing, productUi, () => ({ value:'' }), () => 450, () => {}, async () => [],
  () => null, () => [], sku => ({ action:'add_sku', sku }), () => true,
  async () => ({ clean:true }), async payload => {
    crmCalls.push(payload);
    if (payload.action === 'add_sku') throw new Error('SKU already exists with different CRM fields or RRP');
    return { ok:true, active:'Так' };
  }, { skus:{} }, () => {});
await sync();
assert.deepEqual(crmCalls.map(call => call.action), ['add_sku','set_3dp_sku_active']);
assert.deepEqual(crmCalls[1], { action:'set_3dp_sku_active', sku:'ACC-3D-DITTO-420',
  active:true, expected_active:'Ні' });
assert.match(productUi.product.msg.text, /активовано в CRM/);
console.log('CRM-016 3D catalogue status recovery: archive readback, rollback guard and inactive CRM activation OK');
