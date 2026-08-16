import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const dashboard = fs.readFileSync(path.resolve(here, '../dashboard/booster-dashboard.html'), 'utf8');
const match = dashboard.match(/function orderEditPayloadSignatureForPackaging_[\s\S]*?function clearPendingOrderEdit_[\s\S]*?\n}/);
assert.ok(match, 'order retry helpers are present');

const store = new Map();
const context = vm.createContext({
  JSON, Math, String,
  localStorage: {
    getItem: key => store.has(key) ? store.get(key) : null,
    setItem: (key, value) => store.set(key, value),
    removeItem: key => store.delete(key)
  }
});
vm.runInContext(`
  const ORDER_EDIT_PENDING_KEY = 'booster_order_edit_pending_v1';
  function canonicalPackagingType(value) {
    const raw=String(value==null?'':value).trim(),key=raw.replace(/[’\`]/g,"'").replace(/[хx]/g,'x').replace(/\\s+/g,' ').toLowerCase();
    const canonical=['',"Мала м'яка 14x12 см","Середня м'яка 16x14 см",'Велика пакет 17x30 см','Конверт Airpock 14x22 см','Інше'];
    return canonical.find(function(item){return String(item).replace(/[’\`]/g,"'").replace(/[хx]/g,'x').replace(/\\s+/g,' ').toLowerCase()===key;})||raw;
  }
  ${match[0]}
  globalThis.__test = { orderEditPayloadSignature_, legacyOrderEditPayloadSignature_, pendingOrderEditRequestId_ };
`, context, { filename: 'dashboard/booster-dashboard.html' });

const payload = {
  row_index: 274, payment_status: 'Не оплачено', order_status: 'В обробці', ttn: '',
  packaging_type: "Середня м'яка 16x14 см", note: 'OpenCart #318',
  components: [{ id: 'sku:PKM-JP-MBRV-BST', qty: 1 }], fixtures: [], three_dp_lines: []
};
assert.equal(context.__test.orderEditPayloadSignature_(payload), context.__test.orderEditPayloadSignature_({ ...payload, packaging_type: "Середня м'яка 16х14 см" }), 'lookalike x variants have one semantic request signature');
store.set('booster_order_edit_pending_v1', JSON.stringify({ order: 'OC-FOP-0318', signature: context.__test.legacyOrderEditPayloadSignature_(payload), request_id: 'dashboard-legacy-request' }));
assert.equal(context.__test.pendingOrderEditRequestId_('OC-FOP-0318', payload), 'dashboard-legacy-request', 'a retry after the dashboard update retains the pre-fix request ID');
console.log('CRM-004 dashboard packaging retry tests passed');
