import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const source = fs.readFileSync(new URL('../Code.gs', import.meta.url), 'utf8');
const dashboard = fs.readFileSync(new URL('../../../dashboard/booster-dashboard.html', import.meta.url), 'utf8');

function functionSource(name) {
  const start = source.indexOf(`function ${name}`);
  assert.notEqual(start, -1, `${name} declaration is missing`);
  const open = source.indexOf('{', start);
  let depth = 0;
  for (let index = open; index < source.length; index += 1) {
    if (source[index] === '{') depth += 1;
    if (source[index] === '}') { depth -= 1; if (depth === 0) return source.slice(start, index + 1); }
  }
  throw new Error(`${name} declaration is incomplete`);
}

function appsScriptUtilities() {
  const blob = value => {
    const bytes = Buffer.isBuffer(value) ? value : Buffer.from(Array.isArray(value) ? value : String(value), 'utf8');
    return { getBytes: () => Array.from(bytes), getDataAsString: () => bytes.toString('utf8') };
  };
  return { newBlob: value => blob(value) };
}

test('load telemetry is opt-in and leaves non-heavy actions unchanged', () => {
  const telemetry = new Function(`const CRM013_LOAD_TELEMETRY_ACTIONS_={overview_bootstrap:true,orders:true};${functionSource('crm013LoadTelemetry_')} return crm013LoadTelemetry_;`)();
  const original = { ok: true, rows: [] };
  assert.equal(telemetry('integrity_check', original, Date.now(), false), original);
  const measured = telemetry('orders', original, Date.now(), true);
  assert.notEqual(measured, original);
  assert.equal(measured.load_telemetry.cache_hit, true);
  assert.equal(typeof measured.load_telemetry.elapsed_ms, 'number');
  assert.equal(original.load_telemetry, undefined);
  assert.equal(measured.load_telemetry.cache_state, 'hit');
});

test('oversized GET payloads bypass cache without compression or cache writes', () => {
  const cache = new Function('Utilities', 'Logger', `
    const CRM013_CACHE_MAX_VALUE_BYTES_=95000;
    ${functionSource('crm013CacheByteLength_')}
    ${functionSource('crm013EncodeCacheValue_')}
    ${functionSource('crm013WriteCacheValue_')}
    return { encode: crm013EncodeCacheValue_, write: crm013WriteCacheValue_ };
  `)(appsScriptUtilities(), { log: () => {} });
  const value = { ok: true, rows: Array.from({ length: 350 }, (_, index) => ({ sku: `SKU-${index}`, name: 'Повторюваний запис для стискання '.repeat(20) })) };
  const encoded = cache.encode(value);
  const entries = new Map();
  const fakeCache = { get: key => entries.get(key) || null, put: (key, stored) => entries.set(key, stored) };
  assert.equal(encoded.ok, false);
  assert.equal(encoded.cache_state, 'value_too_large');
  assert.equal(cache.write(fakeCache, 'cache-key', value, 120), 'value_too_large');
  assert.equal(entries.size, 0);
});

test('client keeps telemetry bounded and omits request secrets and payloads', () => {
  assert.match(dashboard, /CRM013_LOAD_TELEMETRY_LIMIT = 60/);
  assert.match(dashboard, /crmLoadTelemetryState\.entries\.length>CRM013_LOAD_TELEMETRY_LIMIT/);
  assert.match(dashboard, /await r\.text\(\)/);
  assert.match(dashboard, /response_bytes/);
  assert.match(dashboard, /cache_state/);
  assert.match(dashboard, /entry\.deduped=true/);
  assert.match(dashboard, /load_telemetry/);
  assert.doesNotMatch(dashboard, /crmLoadTelemetryState\.entries.*TOKEN/);
});
