import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('./Temporary3dpFifoRehearsal.gs', import.meta.url), 'utf8');
new vm.Script(source, { filename: 'Temporary3dpFifoRehearsal.gs' });

assert.match(source, /makeCopy\(/, 'rehearsal must operate on a Drive copy');
assert.doesNotMatch(source, /fifo3dp(?:ManufactureBatchAction_|CrmSaleCommitAction_|ReverseAction_|RepairAction_)\(live,/, 'live spreadsheet must never enter a FIFO mutation action');
assert.match(source, /live_unchanged: true/, 'successful output must prove live fingerprint stability');
assert.match(source, /rehearsal_copy_trashed: true/, 'successful rehearsal copy must be trashed');
assert.match(source, /FIFO_REHEARSAL_REACTIVATION_NOT_BLOCKED/, 'rehearsal must require reactivation blocking');
assert.match(source, /expected_fingerprint: dirty\.fingerprint/, 'repair must use the immediately preceding reconciliation fingerprint');
assert.match(source, /reconciliation_clean: true/, 'successful output must require clean reconciliation');

console.log('3D-P FIFO copy-rehearsal contract passed');
