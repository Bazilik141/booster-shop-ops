import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const dashboard = fs.readFileSync(path.resolve(here, "../booster-dashboard.html"), "utf8");

assert.match(dashboard, /id="threeDpSyncJournal"/);
assert.match(dashboard, /onclick="refreshThreeDpSyncJournal\(\)"/);
assert.match(dashboard, /function renderThreeDpSyncJournal\(\)/);
assert.match(dashboard, /call\('sync_journal',\{limit:20\}\)/);
assert.match(dashboard, /\['created','updated','noop'\]/);
assert.match(dashboard, /success\?'chip':'issue-tag'/);
assert.match(dashboard, /threeDpEsc\(row\.detail\)/);
assert.match(dashboard, /threeDpEsc\(row\.order_id\)/);

console.log("3dp-sync-journal dashboard static tests passed");
