import assert from 'node:assert/strict';
import fs from 'node:fs';

const code = fs.readFileSync(new URL('../Code.gs', import.meta.url), 'utf8');

const start = code.indexOf('function sync3dpSalesV2_(');
const end = code.indexOf('// Compatibility name retained', start);
assert.ok(start >= 0 && end > start, 'sync3dpSalesV2_ block must exist');
const source = code.slice(start, end);

assert.match(source, /\['Скасовано', 'Повернення'\]/, 'terminal CRM statuses must route to reversal');
assert.match(source, /action: '3dp_fifo_reverse'/, 'terminal 3D rows must call the specialized reversal action');
assert.match(source, /original_operation_id: originalOperationId/, 'reversal must reference the original sale operation');
assert.match(source, /latest3dpAccountingByRow_/, 'CRM reversal must require its frozen accounting snapshot');
assert.match(source, /qty: -Math\.abs/, 'CRM accounting history must append a negative quantity');
assert.match(source, /result\.reversed\+\+/, 'sync result must expose completed reversals');
assert.match(source, /priorAccounting\.qty\) < 0/, 'a negative accounting snapshot must block reactivation');
assert.match(source, /setValue\('Скасовано'\)/, 'a blocked reactivation must leave the CRM row terminal');

console.log('CRM 3D-P FIFO reversal contract passed');
