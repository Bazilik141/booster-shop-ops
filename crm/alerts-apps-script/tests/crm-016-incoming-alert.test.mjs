import assert from 'node:assert/strict';
import fs from 'node:fs';

const source = fs.readFileSync(new URL('../Code.gs', import.meta.url), 'utf8');
const start = source.indexOf('function collectStockQueueIssues_()');
const end = source.indexOf('function collectManagedIssueCandidates_()', start);
assert.ok(start >= 0 && end > start);

const rowsByRange = {
  'A9:G14': [['Артикул', 'Назва', 'Термін', '30д', 'Залишок', 'Очікується', 'Гранична закупка'],
    ['OP-JP-EB03-BST', 'EB03', 'Висока', '4', '0', '8', '₴1 000']],
  'I18:O23': [['OP-JP-OP07-BST', 'OP07', 'Середня', '2', '1', '0', '₴500']],
  'A18:G23': [],
};
const sheet = { getRange: range => ({ getDisplayValues: () => rowsByRange[range] }) };
const collect = new Function('SpreadsheetApp', 'normalizeText_', 'hashText_', 'trimText_',
  source.slice(start, end) + '\nreturn collectStockQueueIssues_;')(
  { getActive: () => ({ getSheetByName: () => sheet }) },
  value => String(value || '').trim().toLowerCase(),
  value => value,
  value => value,
);
const issues = collect();
assert.equal(issues.length, 2);
assert.match(issues[0].details, /закуплено, ще не на складі UA: 8 шт/);
assert.match(issues[1].details, /закуплено, ще не на складі UA: 0 шт/);
for (const issue of issues) {
  assert.doesNotMatch(issue.details, /гранична закупка|після резерву|₴/);
  assert.match(issue.text, /закуплено, ще не на складі UA/);
}

const blockStart = source.indexOf('function formatStockBlock_(');
const blockEnd = source.indexOf('function compactLine_(', blockStart);
assert.ok(blockStart >= 0 && blockEnd > blockStart);
const format = new Function(source.slice(blockStart, blockEnd) + '\nreturn formatStockBlock_;')();
const telegram = format('Докупити', [rowsByRange['A9:G14'][1]], 3);
assert.match(telegram, /закуплено, ще не на складі UA 8/);
assert.doesNotMatch(telegram, /гранична закупка|₴/);
console.log('CRM-016 incoming quantity and purchase-cap omission OK');
