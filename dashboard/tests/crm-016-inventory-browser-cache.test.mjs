import assert from 'node:assert/strict';
import fs from 'node:fs';

const html = fs.readFileSync(new URL('../booster-dashboard.html', import.meta.url), 'utf8');
const start = html.indexOf('const INVENTORY_SNAPSHOT_BROWSER_TTL_MS');
const end = html.indexOf('const crmIntegrityState', start);
assert.ok(start >= 0 && end > start);
const code = html.slice(start, end);

let now = 1000;
let calls = 0;
const make = call => new Function('call', 'Date', code + '\nreturn {loadInventorySnapshot_,invalidateInventorySnapshotBrowserCache_};')(
  call, { now: () => now });
const ready = make(async () => { calls++; return { ok:true, source_status:'ready', skus:[{ sku:'TEST' }] }; });
const first = await ready.loadInventorySnapshot_();
now += 5000;
assert.equal(await ready.loadInventorySnapshot_(), first);
assert.equal(calls, 1, 'quick tab change reuses the verified snapshot');
ready.invalidateInventorySnapshotBrowserCache_();
await ready.loadInventorySnapshot_();
assert.equal(calls, 2, 'a mutation or manual refresh invalidates the snapshot');
now += 60001;
await ready.loadInventorySnapshot_();
assert.equal(calls, 3, 'snapshot expires after one minute');

let unavailableCalls = 0;
const unavailable = make(async () => { unavailableCalls++; return { ok:true, source_status:'unavailable', skus:[] }; });
await unavailable.loadInventorySnapshot_();
await unavailable.loadInventorySnapshot_();
assert.equal(unavailableCalls, 2, 'unverified stock never enters the browser cache');

for (const marker of ['async function callPost(payload)', 'async function call3dpPost(payload)', 'function hardRefresh()', 'function configure3dpAccess()']) {
  const from = html.indexOf(marker);
  assert.ok(from >= 0);
  const next = html.indexOf('\n}', from);
  assert.match(html.slice(from, next + 2), /invalidateInventorySnapshotBrowserCache_\(\)/, marker);
}
console.log('CRM-016 browser inventory cache and invalidation OK');
