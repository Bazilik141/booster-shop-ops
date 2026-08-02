import assert from "node:assert/strict";
import test from "node:test";
import { calculateBatchCost, settingsFromRange } from "../lib/calculator.mjs";

const settings = {
  printer_power_kw: 0.17,
  electricity_price_uah_per_kwh: 4.32,
  amortization_uah_per_hour: 12,
};

test("divides batch totals before applying the final spool formula", () => {
  const result = calculateBatchCost({
    quantity: 36,
    total_weight_g: 180,
    total_print_time_h: 18,
    spool_weight_g: 1000,
    spool_price_uah: 800,
  }, settings);

  assert.equal(result.per_unit.weight_g, 5);
  assert.equal(result.per_unit.time_hours, 0.5);
  assert.equal(result.costs.material_uah, 4);
  assert.equal(result.costs.electricity_uah, 0.3672);
  assert.equal(result.costs.amortization_uah, 6);
  assert.equal(result.costs.base_uah, 10.3672);
});

test("rejects zero or missing batch inputs", () => {
  assert.throws(() => calculateBatchCost({
    quantity: 0, total_weight_g: 1, total_print_time_h: 1, spool_weight_g: 1, spool_price_uah: 1,
  }, settings), /Batch quantity/);
});

test("reads the three API-sourced settings cells", () => {
  assert.deepEqual(settingsFromRange([
    ["Глобальні константи 3D-друку", "", ""],
    ["Потужність принтера, кВт", 0.17, "кВт"],
    ["Ціна електроенергії, грн/кВт·год", 4.32, "грн/кВт·год"],
    ["Амортизація принтера, грн/год", 12, "грн/год"],
  ]), settings);
});
