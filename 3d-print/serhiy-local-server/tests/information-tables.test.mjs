import assert from "node:assert/strict";
import test from "node:test";
import { informationPageSize, isPrintTimeHeader, matrixToObjects, paginateRecords, prepareInformationTable } from "../public/information-tables.js";
import { collectAttentionSignals, reconcileHiddenSignalIds, splitAttention } from "../public/attention-signals.js";

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

test("pagination uses fifteen records, clamps invalid pages, and reports the visible range", () => {
  const records = Array.from({ length: 47 }, (_, index) => index + 1);
  assert.equal(informationPageSize, 15);
  assert.deepEqual(paginateRecords(records, 4), { records: [46, 47], page: 4, pageCount: 4, total: 47, from: 46, to: 47 });
  assert.equal(paginateRecords(records, 9).page, 4);
  assert.equal(paginateRecords(records, 0).page, 1);
  assert.equal(paginateRecords(records, "х").page, 1);
  assert.deepEqual(paginateRecords([], 2), { records: [], page: 1, pageCount: 1, total: 0, from: 0, to: 0 });
});

test("information table filters and sorts before paginating", () => {
  const manyRows = Array.from({ length: 40 }, (_, index) => ({ SKU: `FIG-${String(index + 1).padStart(3, "0")}`, "Кількість": index + 1 }));
  const manySkus = manyRows.map((row) => ({ SKU: row.SKU, "Тип": "Фігурка" }));
  const view = prepareInformationTable(manyRows, manySkus, { type: "Фігурка", sort: "Кількість", direction: "desc", page: 2 });
  assert.equal(view.total, 40);
  assert.equal(view.page, 2);
  assert.deepEqual(view.records.map(({ flat }) => flat["Кількість"]), Array.from({ length: 15 }, (_, index) => 25 - index));
  assert.equal(view.allRecords[0].flat["Кількість"], 40);
});

test("attention signals use stable IDs, hide independently, and clean up absent signals", () => {
  const signals = collectAttentionSignals({
    skus: [
      { SKU: "FIG-001", availability: { "Наявно зараз, шт": -2 }, "Час друку за од., год": 0 },
      { SKU: "BR-002", availability: { "Наявно зараз, шт": 2 }, "Час друку за од., год": 1 },
    ],
    printLog: [
      { SKU: "FIG-001", Дата: "2026-09-01", "Брак, шт": 1 },
      { SKU: "FIG-001", Дата: "2026-09-16", "Брак, шт": 2 },
      { SKU: "FIG-001", Дата: "2026-10-01", "Брак, шт": 1 },
    ],
  });
  assert.deepEqual(signals.map((signal) => signal.id), ["attention:negative-stock:FIG-001", "attention:no-print-time:FIG-001", "attention:defect:FIG-001:2026-09", "attention:defect:FIG-001:2026-10"]);
  assert.equal(signals.find((signal) => signal.id === "attention:defect:FIG-001:2026-09").defects, 3);
  const split = splitAttention(signals, ["attention:negative-stock:FIG-001"]);
  assert.equal(split.active.length, 3);
  assert.deepEqual(split.hidden.map((signal) => signal.id), ["attention:negative-stock:FIG-001"]);
  assert.deepEqual(reconcileHiddenSignalIds(["attention:negative-stock:FIG-001"], signals.filter((signal) => signal.kind !== "negative-stock")), []);
  assert.equal(splitAttention([signals[0]], []).active.length, 1);
});
