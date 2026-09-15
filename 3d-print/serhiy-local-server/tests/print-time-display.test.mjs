import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const source = fs.readFileSync(path.resolve(here, "../../shared/print-time.js"), "utf8");

test("print times display only as hours and two-digit minutes", () => {
  const context = {};
  vm.createContext(context);
  vm.runInContext(source, context);
  assert.equal(context.BoosterPrintTime.human(0.216666667), "0 год 13 хв");
  assert.equal(context.BoosterPrintTime.human(1.016666667), "1 год 01 хв");
  assert.equal(context.BoosterPrintTime.human(2), "2 год 00 хв");
});
