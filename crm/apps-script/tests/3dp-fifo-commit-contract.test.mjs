import assert from "node:assert/strict";
import fs from "node:fs";

const code = fs.readFileSync(new URL("../Code.gs", import.meta.url), "utf8");

function block(name, nextName) {
  const start = code.indexOf(`function ${name}(`);
  const end = nextName ? code.indexOf(`function ${nextName}(`, start + 1) : code.length;
  assert.ok(start >= 0 && end > start, `${name} block must exist`);
  return code.slice(start, end);
}

const frozenInputs = block("crm3dpFrozenSaleInputs_", "crm3dpFixtureFrozenForLine_");
assert.doesNotMatch(frozenInputs, /productionCost === null/,
  "catalogue K must not gate a sale once actual manufactured-batch FIFO is active");

const syncV2 = block("sync3dpSalesV2_", "sync3dpPackagingCost_");
assert.match(syncV2, /action: '3dp_crm_sale_commit'/);
assert.match(syncV2, /operationId = 'crm_sale:' \+ order \+ ':' \+ entry\.row/);
assert.match(syncV2, /frozen\.production_cost = crm3dpNumber_\(committed\.unit_cost_uah\)/);
assert.doesNotMatch(syncV2, /crm3dpEnsureStock_\(/,
  "V2 must not create a second stock adjustment after the atomic FIFO sale commit");
assert.ok(syncV2.indexOf("3dp_crm_sale_commit") < syncV2.indexOf("crm3dpAccountingSnapshot_"),
  "CRM accounting must use the persisted 3D-P FIFO result");

console.log(JSON.stringify({ ok: true, atomic_fifo_sale_commit: true, legacy_double_decrement_removed: true }));
