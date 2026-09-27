import assert from 'node:assert/strict';
import fs from 'node:fs';
import { createHash } from 'node:crypto';

const source = fs.readFileSync(new URL('../../alerts-apps-script/Code.gs', import.meta.url), 'utf8');
const tracking = source.match(/function alertTrackingKey_\(issue\) \{[\s\S]*?(?=\nfunction setManagedAlertStatus_)/)?.[0];
assert.ok(tracking);
let today = '2026-09-24';
const values = { BOOSTER_ALERTS_TOKEN:'private' };
const properties = {
  getProperties: () => ({ ...values }),
  setProperties: entries => Object.assign(values, entries),
  deleteProperty: key => { delete values[key]; }
};
const helpers = new Function('PropertiesService','Utilities','Session','hashText_', tracking + '\nreturn { key: alertTrackingKey_, dates: alertFirstSeenDates_ };')(
  { getScriptProperties: () => properties },
  { formatDate: () => today },
  { getScriptTimeZone: () => 'Europe/Kyiv' },
  value => createHash('sha256').update(value).digest('hex')
);
const issue = { kind:'stock_negative', sku:'FIG-ONIX-500', title:'Мінусовий залишок', details:'-1' };
const key = helpers.key(issue);
assert.equal(helpers.dates([issue])[key], '2026-09-24');
assert.equal(values.BOOSTER_ALERTS_TOKEN, 'private', 'other script properties are untouched');
today = '2026-09-25';
assert.equal(helpers.dates([{ ...issue, details:'-2' }])[key], '2026-09-24', 'balance changes do not reset the incident date');
helpers.dates([]);
assert.equal(values[key], undefined, 'resolved alert is removed from tracking');
assert.equal(helpers.dates([issue])[key], '2026-09-25', 'a recurrence starts a new incident');
console.log('CRM-016 alert first-seen lifecycle passed');
