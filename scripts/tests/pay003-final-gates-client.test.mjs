import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync('work/pay003-final-gates/candidate/catalog/view/javascript/pay003-credit.js', 'utf8');
let now = 0;
let sequence = 0;
const timers = new Map();
const calls = [];
const elements = {
  'pay003-credit': {dataset: {stateUrl: '/state', completeUrl: '/complete', csrf: 'fixture', poll: '1', confirmed: '0'}},
  'pay003-network': {textContent: ''},
  'pay003-title': {textContent: ''},
  'pay003-message': {textContent: ''},
};
const document = {
  hidden: false,
  handlers: {},
  getElementById(id) { return elements[id] || null; },
  addEventListener(name, handler) { this.handlers[name] = handler; },
};
class Clock extends Date { static now() { return now; } }
let response = {status: 200, data: {title: 'Очікування', message: 'Підтвердьте заявку', poll: true, confirmed: false, offline: false, redirect: null}};
const context = {
  document,
  window: {location: {href: 'https://shop.invalid/credit', origin: 'https://shop.invalid', assign() {}}, addEventListener() {}},
  URL,
  URLSearchParams,
  AbortController,
  Date: Clock,
  setTimeout(handler, delay) { timers.set(++sequence, {handler, at: now + delay}); return sequence; },
  clearTimeout(id) { timers.delete(id); },
  fetch: async (url, options) => {
    calls.push({url, ...options});
    return {ok: response.status === 200, status: response.status, json: async () => response.data};
  },
};
vm.runInNewContext(source, context);
const flush = async () => { for (let i = 0; i < 15; i++) await Promise.resolve(); };
const advance = async (milliseconds) => {
  const end = now + milliseconds;
  while (true) {
    const ready = [...timers].filter(([, timer]) => timer.at <= end).sort((a, b) => a[1].at - b[1].at);
    if (!ready.length) break;
    const [id, timer] = ready[0];
    timers.delete(id);
    now = timer.at;
    timer.handler();
    await flush();
  }
  now = end;
  await flush();
};

assert.equal(calls.length, 0, 'no immediate request');
await advance(5000);
assert.equal(calls.length, 1, 'automatic DB-only check remains');
assert.equal(calls[0].method, undefined, 'first check is GET');
await advance(26000);
assert.equal(calls.filter((call) => call.method === 'POST').length, 1, 'bank fallback remains automatic');
response = {status: 503, data: {}};
await advance(30000);
assert.match(elements['pay003-network'].textContent, /поверніться .* пізніше/u, 'error does not refer to removed button');
assert.equal(source.includes('pay003-refresh'), false, 'manual customer refresh control removed from JS');
assert.equal(source.includes('Перевірити статус'), false, 'removed button is not referenced in copy');

console.log('checks=7 result=ok network=synthetic timers=virtual');
