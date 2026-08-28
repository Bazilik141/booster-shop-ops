import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, "../Code.gs"), "utf8");
assert.match(code, /action === 'update_purchase'\) return boosterCrmJson_\(apiUpdatePurchaseBatch10_\(ss, payload\)\)/,
  "the public update_purchase action uses the ten-lot implementation");
assert.match(code, /function apiUpdatePurchaseBatch10_\(ss, payload\)[\s\S]*?rawLots\.length > 10/,
  "the active implementation has the ten-lot guard");

function purchase(lotId) {
  const row = Array(18).fill("");
  row[0] = lotId;
  row[4] = "SKU-" + lotId;
  row[7] = 1;
  row[8] = 100;
  row[16] = "Замовлено";
  return row;
}

const rows = Array.from({ length: 11 }, (_, index) => purchase("LOT-" + String(index + 1).padStart(4, "0")));
const purchases = {
  getLastRow: () => rows.length + 2,
  getRange(row, column, numRows = 1, numColumns = 1) {
    return {
      getValues: () => rows.slice(row - 3, row - 3 + numRows).map((source) => source.slice(column - 1, column - 1 + numColumns)),
      setValue(value) {
        rows[row - 3][column - 1] = value;
        return this;
      }
    };
  }
};
const ss = { getSheetByName: (name) => name === "Закупки" ? purchases : null };
const context = vm.createContext({ JSON, Math, Number, String, Boolean, Array, Object, RegExp, Date, Error, Set, isFinite, console, SpreadsheetApp: { flush() {} } });
vm.runInContext(code + "\nglobalThis.__test = { apiUpdatePurchaseBatch10_ };", context, { filename: "Code.gs" });
context.resetMemoForMutation_ = () => {};
context.invalidateDoGetCache_ = () => {};
context.getCurrencyRate_ = () => 1;
let reconciledSkus = [], currentCostRefreshes = 0;
context.preorderReconcileSkus_ = (inputSs, skus) => {
  reconciledSkus = skus;
  return Object.fromEntries(skus.map((sku) => [sku, { priced: 1 }]));
};
context.updateSkuCurrentCost_ = () => { currentCostRefreshes += 1; };

const firstTen = rows.slice(0, 10).map((row) => ({ lot_id: row[0] }));
const accepted = context.__test.apiUpdatePurchaseBatch10_(ss, { lots: firstTen, status: "В дорозі" });
assert.deepEqual(JSON.parse(JSON.stringify(accepted)), {
  ok: true,
  rows_updated: 10,
  lot_ids: firstTen.map((item) => item.lot_id),
  preorder_costs: {}
});
assert.equal(rows.slice(0, 10).every((row) => row[16] === "В дорозі"), true, "all ten selected lots are updated");
assert.deepEqual(reconciledSkus, [], "in-transit status does not run final FIFO reconciliation");

const landed = context.__test.apiUpdatePurchaseBatch10_(ss, { lots: [firstTen[0]], status: "На складі" });
assert.equal(landed.ok, true);
assert.deepEqual(JSON.parse(JSON.stringify(reconciledSkus)), ["SKU-LOT-0001"], "landed purchase triggers reconciliation for its affected SKU");
assert.equal(currentCostRefreshes, 1);
assert.deepEqual(JSON.parse(JSON.stringify(landed.preorder_costs)), { "SKU-LOT-0001": { priced: 1 } });

const rejected = context.__test.apiUpdatePurchaseBatch10_(ss, {
  lots: rows.map((row) => ({ lot_id: row[0] })),
  status: "На складі"
});
assert.deepEqual(JSON.parse(JSON.stringify(rejected)), { ok: false, error: "maximum 10 lots" });
assert.equal(rows[10][16], "Замовлено", "the eleventh lot is not mutated after a rejected request");
console.log("Purchase batch accepts ten lots and rejects eleven");
