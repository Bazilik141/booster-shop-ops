import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import vm from "node:vm";
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

const validatorSource = dashboard.match(/function threeDpSkuTypeError\(sku,type\) \{[^\n]+\}/)?.[0];
assert.ok(validatorSource, "threeDpSkuTypeError must remain a standalone validator");
const validatorContext = vm.createContext({ String, RegExp });
vm.runInContext(`${validatorSource}\nglobalThis.validate = threeDpSkuTypeError;`, validatorContext);
assert.equal(validatorContext.validate("ACC-3D-DITTO-410", "Функціональний аксесуар"), "");
assert.equal(validatorContext.validate("ACC-3D-OP-500", "Функціональний аксесуар"), "");
assert.equal(validatorContext.validate("BR-CHARM-001", "Брелок"), "");
assert.match(validatorContext.validate("ACC-3D-410", "Функціональний аксесуар"), /ACC-3D-PKM-130/);
assert.match(validatorContext.validate("ACC-3D-DITTO-410", "Брелок"), /Префікс SKU/);

console.log("3dp-sync-journal dashboard static tests passed");
