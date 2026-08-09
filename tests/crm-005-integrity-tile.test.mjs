import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const dashboard = fs.readFileSync(path.resolve(here, '../dashboard/booster-dashboard.html'), 'utf8');

function functionSource(name) {
  const match = new RegExp('(?:async )?function ' + name + '\\(').exec(dashboard);
  if (!match) throw new Error('Missing dashboard function: ' + name);
  const start = match.index;
  const open = dashboard.indexOf('{', start);
  let depth = 0;
  for (let index = open; index < dashboard.length; index += 1) {
    if (dashboard[index] === '{') depth += 1;
    if (dashboard[index] === '}') {
      depth -= 1;
      if (depth === 0) return dashboard.slice(start, index + 1);
    }
  }
  throw new Error('Unclosed dashboard function: ' + name);
}

class Element {
  constructor(id = '') { this.id = id; this.className = ''; this.innerHTML = ''; this.style = {}; this.children = []; this.parent = null; this.textContent = ''; this.value = ''; }
  appendChild(child) { child.parent = this; this.children.push(child); if (child.id) this.registry.set(child.id, child); return child; }
  remove() { if (this.parent) this.parent.children = this.parent.children.filter((child) => child !== this); if (this.id) this.registry.delete(this.id); }
  setAttribute() {}
  select() {}
}

const registry = new Map();
const cards = new Element('summaryCards'); cards.registry = registry; registry.set(cards.id, cards);
const detail = new Element('crmIntegrityDetail'); detail.registry = registry; registry.set(detail.id, detail);
function makeElement(id) { const element = new Element(id); element.registry = registry; return element; }
const body = makeElement('body');
let fallbackCopied = false;
const document = { getElementById: (id) => registry.get(id) || null, createElement: () => makeElement(), body, execCommand: () => { fallbackCopied = true; return true; } };
const timers = [];
const window = { setTimeout: (callback, delay) => { timers.push({ callback, delay }); return timers.length; } };

let nextResult = { ok: true, clean: true, problems: [], elapsed_ms: 47 };
let calls = 0;
let clipboardText = '';
let clipboardFails = false;
const navigator = { clipboard: { writeText: async (value) => { if (clipboardFails) throw new Error('clipboard unavailable'); clipboardText = value; } } };
const source = [
  dashboard.match(/const crmIntegrityState = [^;]+;/)[0],
  dashboard.match(/const CRM_INTEGRITY_COPY_CONFIRM_MS = [^;]+;/)[0],
  functionSource('crmIntegrityLocalTime'),
  functionSource('crmIntegrityElapsed'),
  functionSource('crmIntegrityCopyResult'),
  functionSource('crmIntegrityRender'),
  functionSource('renderCrmIntegrityTile'),
  functionSource('runCrmIntegrityCheck'),
  'globalThis.tileTest = { crmIntegrityState, crmIntegrityCopyResult, crmIntegrityRender, renderCrmIntegrityTile, runCrmIntegrityCheck };',
].join('\n');
const context = vm.createContext({
  Number, String, Object, Array, Date, Error, document, navigator, window,
  spin: () => '<div class="spinner"></div>',
  threeDpEsc: (value) => String(value == null ? '' : value).replace(/[&<>"']/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[char]),
  call: async () => { calls += 1; if (nextResult instanceof Error) throw nextResult; return nextResult; },
});
vm.runInContext(source, context, { filename: 'dashboard/booster-dashboard.html' });

context.tileTest.renderCrmIntegrityTile();
assert.equal(calls, 0, 'tile does not request integrity_check on page load');
assert.equal(cards.children.length, 1);
assert.match(cards.children[0].innerHTML, /Перевірити/);

await context.tileTest.runCrmIntegrityCheck();
assert.equal(calls, 1);
assert.equal(cards.children.length, 1);
assert.match(cards.children[0].className, /c-green/);
assert.match(cards.children[0].innerHTML, /47 мс/);
assert.equal(detail.style.display, 'block');
assert.match(detail.innerHTML, /Скопіювати результат/);

const copiedCleanButton = { textContent: 'Скопіювати результат' };
await context.tileTest.crmIntegrityCopyResult(copiedCleanButton);
assert.equal(clipboardText, JSON.stringify(nextResult, null, 2));
assert.equal(copiedCleanButton.textContent, 'Скопійовано ✓');
assert.equal(timers.at(-1).delay, 2000);
timers.at(-1).callback();
assert.equal(copiedCleanButton.textContent, 'Скопіювати результат');

nextResult = { ok: true, clean: false, elapsed_ms: 83, problems: [{ sheet: 'РРЦ', rows: '71-75', code: 'price_without_sku', detail: 'SKU missing' }], truncated: {}, coverage: {} };
await context.tileTest.runCrmIntegrityCheck();
assert.equal(cards.children.length, 1);
assert.match(cards.children[0].innerHTML, /1 проблем\(и\)/);
assert.equal(detail.style.display, 'block');
assert.match(detail.innerHTML, /price_without_sku/);
assert.match(detail.innerHTML, /Скопіювати результат/);

const copiedProblemButton = { textContent: 'Скопіювати результат' };
await context.tileTest.crmIntegrityCopyResult(copiedProblemButton);
assert.match(clipboardText, /price_without_sku/);
assert.match(clipboardText, /elapsed_ms/);

context.navigator.clipboard = null;
const fallbackButton = { textContent: 'Скопіювати результат' };
await context.tileTest.crmIntegrityCopyResult(fallbackButton);
assert.equal(fallbackCopied, true, 'fallback uses document.execCommand in non-secure contexts');

context.navigator.clipboard = { writeText: async () => { throw new Error('clipboard unavailable'); } };
const failedCopyButton = { textContent: 'Скопіювати результат' };
await context.tileTest.crmIntegrityCopyResult(failedCopyButton);
assert.equal(failedCopyButton.textContent, 'Не вдалося скопіювати');
clipboardFails = false;

context.tileTest.renderCrmIntegrityTile();
assert.equal(cards.children.length, 1, 'refresh recreates one tile without discarding the result');
assert.match(cards.children[0].innerHTML, /1 проблем\(и\)/);

nextResult = new Error('offline');
await context.tileTest.runCrmIntegrityCheck();
assert.match(cards.children[0].innerHTML, /Перевірка недоступна: offline/);
assert.equal(detail.style.display, 'none');

assert.match(dashboard, /renderCrmIntegrityTile\(\);\s*crmIntegrityRender\(crmIntegrityState\.result\);\s*\/\/ Channel stats/);
assert.equal((dashboard.match(/call\('integrity_check'\)/g) || []).length, 1, 'integrity_check is only called by the click handler');
assert.match(dashboard, /#crmIntegrityCard\s*\{\s*order:\s*999;\s*\}/, 'grid order keeps the integrity tile last');

console.log('CRM-005 integrity tile tests passed');
