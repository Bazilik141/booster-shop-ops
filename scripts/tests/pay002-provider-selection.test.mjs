// Run against a reconstructed/live payment_method.twig, never against the store.
// Uses the actual Twig JavaScript, with only DOM/AJAX/checkout services stubbed.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { execFileSync } from 'node:child_process';

const twig = readFileSync(process.argv[2], 'utf8');
const match = twig.match(/<script[^>]*>([\s\S]*?)<\/script>/);
assert.ok(match, 'template script exists');
const script = match[1].replace(/{{[\s\S]*?}}/g, 'fixture')
  .replace('})();', 'window.qa = { flattenPaymentMethods, renderPaymentMethods, savePayment };\n})();');
execFileSync(process.execPath, ['--check'], { input: script, encoding: 'utf8' });
const compiled = new vm.Script(script, { filename: 'actual-payment-method.twig.js' });

function harness({ current = '', shipping = true } = {}) {
  const values = { '#input-payment-code': current, '#input-shipping-code': shipping ? 'fixture.delivery' : '' };
  const markup = {};
  const requests = [];
  let response;
  let revision = 1;
  function $(selector) {
    if (selector && selector.qaElement) return selector;
    let html = typeof selector === 'string' && selector.startsWith('<') ? selector : '';
    const element = {
      qaElement: true, length: 0,
      val(value) {
        if (value === undefined) return values[selector] || '';
        for (const key of String(selector).split(', ')) values[key] = value;
        return element;
      },
      html(value) { if (value === undefined) return markup[selector] || ''; markup[selector] = value; return element; },
      attr(name, value) { if (value === undefined) return ''; if (name === 'hidden') html = html.replace('<div ', '<div hidden="hidden" '); return element; },
      prop(name) { return name === 'outerHTML' ? html : element; },
      first() { return element; }, find() { return $('fixture-empty'); },
      text() { return element; }, toggleClass() { return element; },
      removeClass() { return element; }, addClass() { return element; }, on() { return element; }
    };
    return element;
  }
  $.each = (collection, callback) => {
    for (const [key, value] of Object.entries(collection || {})) if (callback(key, value) === false) break;
  };
  $.extend = Object.assign;
  $.ajax = request => {
    requests.push(request);
    if (request.url.includes('getMethods')) request.success(response);
  };
  const window = { bsCheckoutState: {
    currentRevision: () => revision, isCurrent: value => value === revision,
    paymentSaved() {}, paymentMethodsRendered() {}, renderConfirm() {}
  } };
  compiled.runInNewContext({ window, $, document: {}, Intl, alert: message => { throw new Error(message); } });
  return {
    window, values, requests,
    posts: () => requests.filter(request => request.type === 'post'),
    html: () => markup['#bs-payment-methods'] || '',
    load(json) { response = json; window.bsCheckoutLoadPaymentMethods(); },
    complete() { const request = requests.filter(request => request.type === 'post').at(-1); assert.ok(request); request.success({}); },
    nextRevision() { revision++; }
  };
}

function data(provider = '', term = 4, order = ['mono_chast', 'pumb_credit']) {
  const groups = {};
  for (const key of order) {
    const prefix = key === 'pumb_credit' ? 'pay002' : 'pay001';
    groups[key] = {
      code: key, [`${prefix}_credit`]: true, [`${prefix}_from_modal`]: provider === key,
      [`${prefix}_preferred`]: provider === key ? term : 3, [`${prefix}_total`]: 5000,
      option: Object.fromEntries([3, 4, 5].map(count => [`${key}_${count}`, { code: `${key}.${key}_${count}` }]))
    };
  }
  return { payment_methods: groups };
}

function selectedCode(ui) {
  return /name="payment_method"[^>]*value="([^"]+)"[^>]* checked/.exec(ui.html())?.[1] || '';
}
function activeTerms(ui) {
  return [...ui.html().matchAll(/<button[^>]*data-pay001-code="([^"]+)"[^>]*class="is-active"/g)].map(item => item[1]);
}

if (process.argv.includes('--expect-bug')) {
  const ui = harness();
  ui.load(data('pumb_credit', 4));
  assert.equal(ui.posts()[0].data.payment_method, 'mono_chast.mono_chast_3');
  assert.equal(selectedCode(ui), 'mono_chast.mono_chast_3');
  assert.deepEqual(activeTerms(ui), ['mono_chast.mono_chast_3']);
  console.log('baseline_bug_reproduced=PUMB_4_saves_and_highlights_MONO_3');
  process.exit(0);
}

let cases = 0;
for (const provider of ['mono_chast', 'pumb_credit']) {
  for (const term of [3, 4, 5]) {
    for (const order of [['mono_chast', 'pumb_credit'], ['pumb_credit', 'mono_chast'], [provider]]) {
      for (const ready of [true, false]) {
        const ui = harness({ shipping: ready });
        const json = data(provider, term, order);
        const expected = `${provider}.${provider}_${term}`;
        ui.load(json);
        if (!ready) {
          assert.equal(ui.requests.length, 0, 'no request before delivery');
          ui.values['#input-shipping-code'] = 'fixture.delivery';
          ui.load(json);
        }
        assert.equal(ui.posts().length, 1, 'one auto-save');
        assert.equal(ui.posts()[0].data.payment_method, expected, 'actual save request provider and term');
        assert.equal(selectedCode(ui), expected, 'radio value');
        assert.deepEqual(activeTerms(ui), [expected], 'exactly one active term in requested bank');
        ui.complete();
        assert.equal(ui.values['#input-payment-code'], expected, 'saved DOM value');
        ui.nextRevision();
        ui.load(json);
        assert.equal(ui.posts().length, 1, 'refresh after save does not re-save or switch bank');
        assert.deepEqual(activeTerms(ui), [expected]);
        cases++;
      }
    }
  }
}

for (const order of [['mono_chast', 'pumb_credit'], ['pumb_credit', 'mono_chast'], ['mono_chast'], ['pumb_credit'], []]) {
  const ui = harness();
  ui.load(data('', 3, order));
  assert.equal(ui.posts().length, 0, 'direct entry never auto-saves');
  assert.equal(selectedCode(ui), '');
  cases++;
}
for (const missing of ['mono_chast', 'pumb_credit']) {
  const ui = harness();
  ui.load(data(missing, 4, [missing === 'mono_chast' ? 'pumb_credit' : 'mono_chast']));
  assert.equal(ui.posts().length, 0, 'unavailable modal bank must not auto-select another bank');
  cases++;
}
for (const current of ['mono_chast.mono_chast_5', 'pumb_credit.pumb_credit_5']) {
  const ui = harness({ current });
  ui.load(data(current.startsWith('pumb') ? 'mono_chast' : 'pumb_credit', 4));
  assert.equal(ui.posts().length, 0, 'existing explicit selection takes precedence on refresh');
  assert.equal(selectedCode(ui), current);
  assert.deepEqual(activeTerms(ui), [current]);
  cases++;
}
{
  const ui = harness();
  const json = data('pumb_credit', 4);
  ui.load(json);
  ui.complete();
  ui.window.qa.savePayment('mono_chast.mono_chast_5', 'fixture', false);
  ui.complete();
  ui.load(json);
  assert.equal(ui.posts().length, 2, 'manual switch adds one save only');
  assert.equal(selectedCode(ui), 'mono_chast.mono_chast_5', 'manual switch survives modal metadata');
  cases++;
}
console.log(`behavior_cases_passed=${cases}; actual_Twig_JS_parsed=yes; mocked_network_only=yes`);
