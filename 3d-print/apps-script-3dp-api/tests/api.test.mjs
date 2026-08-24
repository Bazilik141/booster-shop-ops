import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, "../Code.gs"), "utf8");

new vm.Script(code, { filename: "Code.gs" });

assert.match(code, /case '3dp_test_order_cleanup':/);
assert.match(code, /function testOrderCleanupAction3dp_\(/);
assert.match(code, /CLEAN TEST ORDER ' \+ order/);
assert.match(code, /manualSalesColumns/);
assert.match(code, /appendAudit3dp_\(spreadsheet, actor, 'CLEANUP_TEST_ORDER'/);
assert.match(code, /function preview3dpApiSetup\(/, "owner kept this setup preview");
assert.match(code, /function setup3dpApi\(/, "active baseline setup remains available");
assert.match(code, /oldStatus === API_3DP\.draftStatus \? API_3DP\.activeStatus : oldStatus/,
  "draft assignment activates while active article editing preserves status");
assert.match(code, /blocking_locations/,
  "article editing checks every stored SKU-key location");
assert.match(code, /actorPrefixed: true/,
  "actor-prefixed batch-draft keys participate in the history guard");
assert.match(code, /headerRow: 3, storedOnly: true/,
  "manual Analytics keys block editing while formula mirrors can follow the row");

[
  "3dp_setup_3dp010", "3dp_setup_3dp015", "3dp_setup_3dp024",
  "3dp_setup_order_line_accounting", "3dp_setup_addendum2",
  "function setup3dp010(", "function setup3dp015(", "function setup3dp024(",
  "function setup3dpOrderLineAccounting(", "function setup3dpSalesProfitShareBackfill(",
  "function preview3dpApiAddendum2(", "function repair3dpAvailabilityFormulas(",
].forEach((needle) => assert.equal(code.includes(needle), false, needle + " is archived, not deployed"));

await import("./role-read-projections.test.mjs");

console.log(JSON.stringify({ ok: true, active_cleanup_route: true, archived_setup_routes_removed: 5, preview3dpApiSetup_retained: true, wp2b_article_edit_cases: true }));
