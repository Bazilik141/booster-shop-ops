import assert from "node:assert/strict";
import test from "node:test";
import { isPrintTimeHeader, matrixToObjects, prepareInformationTable } from "../public/information-tables.js";

const skus = [
  { SKU: "BR-002", "Тип": "Брелок" },
  { SKU: "FIG-001", "Тип": "Фігурка" },
  { SKU: "BR-001", "Тип": "Брелок" },
];
const rows = [
  { SKU: "BR-002", "Кількість": 2, "Час друку за од., год": 0.5 },
  { SKU: "FIG-001", "Кількість": 10, "Час друку за од., год": 1.25 },
  { SKU: "BR-001", "Кількість": 3, "Час друку за од., год": 0.75 },
];

test("information tables filter by SKU product type and sort the selected column", () => {
  const view = prepareInformationTable(rows, skus, { type: "Брелок", sort: "Кількість", direction: "desc" });
  assert.deepEqual(view.records.map(({ flat }) => flat.SKU), ["BR-001", "BR-002"]);
  assert.deepEqual(view.types, ["Брелок", "Фігурка"]);
});

test("information tables retain the dropdown data while hiding selected display columns", () => {
  const view = prepareInformationTable(rows, skus, { visible: { "Кількість": false } });
  assert.equal(view.headers.includes("Кількість"), true);
  assert.equal(view.visibleHeaders.includes("Кількість"), false);
  assert.equal(view.records.find(({ flat }) => flat.SKU === "BR-002").flat["Кількість"], 2);
});

test("analytics matrices become sortable row objects without shifting blank headers", () => {
  const objects = matrixToObjects([["SKU", "", "Час друку, год"], ["FIG-001", "ignored", 1.5]]);
  assert.deepEqual(objects, [{ SKU: "FIG-001", "Час друку, год": 1.5 }]);
  assert.equal(isPrintTimeHeader("Час друку, год"), true);
  assert.equal(isPrintTimeHeader("Дата"), false);
});
