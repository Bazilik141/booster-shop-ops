import assert from 'node:assert/strict';
import fs from 'node:fs';

const html = fs.readFileSync(new URL('../booster-dashboard.html', import.meta.url), 'utf8');
const between = (from, to) => {
  const start = html.indexOf(from), end = html.indexOf(to, start);
  assert.ok(start >= 0 && end > start, `missing UI anchors: ${from}`);
  return html.slice(start, end);
};

const skus = [
  { SKU: 'FIG-ONIX-500', 'Назва виробу': 'Фігурка Онікс', API_статус_запису: 'Активний' },
  { SKU: 'BR-CHARM-100', 'Назва виробу': 'Брелок Чармандер', API_статус_запису: 'Активний' },
  { SKU: 'FIG-OLD-100', 'Назва виробу': 'Стара фігурка', API_статус_запису: 'Архів' }
];
const state = { skus };
const ui = { calc: { sku: '' }, product: { sku: '' }, info: { search: '' } };
const status = row => row.API_статус_запису;
const sku = id => skus.find(row => row.SKU === id) || null;
const esc = value => String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;');
const pickerFunctions = between('function threeDpSkuPickerRows_(', 'function threeDpSkuPickerChoose_(');
const picker = new Function('threeDpState', 'threeDpUi', 'threeDpStatus', 'threeDpSku', 'threeDpActiveSkus', 'threeDpEsc', pickerFunctions + '\nreturn { rows: threeDpSkuPickerRows_, search: threeDpSkuPickerSearch_ };')(
  state, ui, status, sku, () => skus.filter(row => status(row) === 'Активний'), esc
);
assert.equal(picker.rows('calc').length, 2, 'calculator only offers active SKUs');
assert.equal(picker.rows('product').length, 3, 'product editor includes archive');

const results = { innerHTML: '', hidden: true };
const host = { dataset: { kind: 'calc' }, querySelector: () => results };
const input = { value: 'онікс', closest: () => host, setAttribute(name, value) { this[name] = value; }, select() {} };
picker.search(input, false);
assert.match(results.innerHTML, /FIG-ONIX-500/);
assert.doesNotMatch(results.innerHTML, /BR-CHARM-100/);
assert.equal(results.hidden, false);
input.value = 'BR-CHARM';
picker.search(input, false);
assert.match(results.innerHTML, /BR-CHARM-100/, 'search also matches article');

const replacement = { focused: false, range: null, focus() { this.focused = true; }, setSelectionRange(start, end) { this.range = [start, end]; } };
const original = { selectionStart: 2, selectionEnd: 2, closest: () => ({}) };
const document = { activeElement: original, querySelector: () => replacement };
const render = () => {};
const setInfo = new Function('document', 'threeDpUi', 'renderThreeDpInformation', between('function setThreeDpInfo(', 'function sortThreeDpInfo(') + '\nreturn setThreeDpInfo;')(document, ui, render);
setInfo('search', 'он');
assert.equal(ui.info.search, 'он');
assert.equal(replacement.focused, true, 'search regains focus after table redraw');
assert.deepEqual(replacement.range, [2, 2], 'caret position is preserved');
console.log('CRM-016 3D picker and search focus: OK');
