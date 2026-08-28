import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const html = fs.readFileSync(path.resolve(here, '../booster-dashboard.html'), 'utf8');
const script = [...html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/gi)].map((match) => match[1]).find((source) => source.includes('function loadUpdates'));
assert.ok(script, 'dashboard script is present');

function functionSource(name) {
  const match = new RegExp('(?:async\\s+)?function\\s+' + name + '\\(').exec(script);
  if (!match) throw new Error('Missing function: ' + name);
  const start = match.index;
  const open = script.indexOf('{', start);
  let depth = 0;
  for (let index = open; index < script.length; index += 1) {
    if (script[index] === '{') depth += 1;
    if (script[index] === '}' && --depth === 0) return script.slice(start, index + 1);
  }
  throw new Error('Unclosed function: ' + name);
}

const storage = new Map();
const updatesContent = { innerHTML: '<div id="inventoryMigrationDetails">stale</div>' };
const calls = [];
const context = vm.createContext({
  JSON, Math, Date, String, RegExp,
  localStorage: { getItem: (key) => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value), removeItem: (key) => storage.delete(key) },
  document: { getElementById: (id) => id === 'updatesContent' ? updatesContent : null },
  loaded: {}, spin: () => 'loading',
  loadAccounting: async () => { calls.push('accounting'); },
  renderCrmMaintenanceCommands: () => calls.push('maintenance'),
  renderCrmIntegrityTile: () => calls.push('integrity'),
  loadTestOrderCleanup: () => calls.push('test-cleanup')
});
vm.runInContext([
  "const EXPENSE_PENDING_KEY = 'booster_expense_pending_v1';",
  functionSource('pendingExpenseRequestId_'),
  functionSource('clearPendingExpense_'),
  functionSource('loadUpdates'),
  functionSource('loadSettings'),
  'globalThis.__test={pendingExpenseRequestId_,clearPendingExpense_,loadUpdates,loadSettings};'
].join('\n'), context);

await context.__test.loadUpdates();
assert.equal(updatesContent.innerHTML, 'loading', 'updates refresh replaces stale DOM before reloading');
assert.deepEqual(calls, ['accounting'], 'updates always rebuilds its source data even when old controls exist');
assert.equal(context.loaded.accounting, true);

context.__test.loadSettings();
assert.deepEqual(calls.slice(1), ['maintenance', 'integrity', 'test-cleanup'], 'settings restores every section including the saved cleanup report');

const payload = { action: 'add_expense', amount: '100' };
const firstId = context.__test.pendingExpenseRequestId_(payload);
assert.equal(context.__test.pendingExpenseRequestId_(payload), firstId, 'a lost-response retry reuses the same expense request id');
const changedId = context.__test.pendingExpenseRequestId_({ ...payload, amount: '101' });
assert.notEqual(changedId, firstId, 'changed expense data receives a new request id');
context.__test.clearPendingExpense_(changedId);
assert.equal(storage.has('booster_expense_pending_v1'), false, 'successful expense save clears its pending request');

assert.match(html, /class="mobile-brandbar"[\s\S]*showPageByName\('settings'\)/, 'mobile header exposes settings below the sidebar breakpoint');
console.log('Dashboard settings workflow tests passed');
