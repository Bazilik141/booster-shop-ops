import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const source = fs.readFileSync(path.resolve(here, '../../alerts-apps-script/Code.gs'), 'utf8');
const idA = 'a'.repeat(64), idB = 'b'.repeat(64), idMissing = 'c'.repeat(64);
const rows = [[idA, 'active', new Date('2026-09-20'), 'old signature']];
let writes = 0, deletions = 0, lockHeld = false;
const sheet = {
  getLastRow: () => rows.length + 1,
  getRange: (row, col, height, width) => ({
    getValues: () => rows.slice(row - 2, row - 2 + height).map(value => value.slice(col - 1, col - 1 + width)),
    setValues: values => { writes++; assert.equal(row, 2); assert.equal(col, 1); assert.equal(width, 4); rows.splice(0, rows.length, ...values); }
  })
};
const context = vm.createContext({
  SpreadsheetApp: { getActive: () => ({ getSheetByName: () => sheet }) },
  LockService: { getScriptLock: () => ({ waitLock: () => { lockHeld = true; }, releaseLock: () => { lockHeld = false; } }) },
  PropertiesService: { getScriptProperties: () => ({ deleteProperty: () => { deletions++; } }) }
});
vm.runInContext(source, context);
context.collectManagedIssueCandidates_ = () => [{ id:idA, signature:'issue A' }, { id:idB, signature:'issue B' }];

assert.throws(() => context.setManagedAlertStatusBatch_([idA, idMissing]), /ALERT_NOT_CURRENT/);
assert.equal(writes, 0, 'a missing alert must prevent every write');
assert.equal(lockHeld, false);
const result = context.setManagedAlertStatusBatch_([idA, idB, idA]);
assert.equal(result.changed, 2);
assert.equal(writes, 1, 'batch is saved with one sheet write');
assert.equal(deletions, 1);
assert.equal(rows.length, 2);
assert.deepEqual(rows.map(row => [row[0], row[1], row[3]]), [[idA, 'dismissed', 'issue A'], [idB, 'dismissed', 'issue B']]);
assert.equal(lockHeld, false);
console.log('CRM-016 alert batch: invalid selection rejected, two alerts saved in one write');
