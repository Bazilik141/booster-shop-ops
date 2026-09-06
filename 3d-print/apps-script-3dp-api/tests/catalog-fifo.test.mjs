import assert from "node:assert/strict";
import test from "node:test";
import fs from "node:fs";
import vm from "node:vm";

const code = fs.readFileSync(new URL("../CatalogFifo.gs", import.meta.url), "utf8");
const routerCode = fs.readFileSync(new URL("../Code.gs", import.meta.url), "utf8");
const context = vm.createContext({ console });
vm.runInContext(code, context, { filename: "CatalogFifo.gs" });

function call(expression) {
  return vm.runInContext(expression, context);
}

test("allocates oldest manufactured batches and aggregates exact cost", () => {
  const result = call(`fifo3dpPlanAllocation_([
    {batch_id:'new',row:3,manufactured_at:'2026-09-02T10:00:00+03:00',available_qty:3,unit_cost_uah:100},
    {batch_id:'old',row:2,manufactured_at:'2026-09-01T10:00:00+03:00',available_qty:2,unit_cost_uah:80}
  ],3)`);
  assert.equal(result.total_cost_uah, 260);
  assert.equal(result.unit_cost_uah, 86.666667);
  assert.deepEqual(JSON.parse(JSON.stringify(result.allocations)), [
    { batch_id: "old", row: 2, quantity: 2, cost_uah: 160, unit_cost_uah: 80 },
    { batch_id: "new", row: 3, quantity: 1, cost_uah: 100, unit_cost_uah: 100 },
  ]);
});

test("rejects a sale without sufficient costed stock", () => {
  assert.throws(() => call("fifo3dpPlanAllocation_([{batch_id:'b',row:2,manufactured_at:'2026-09-01',available_qty:1,unit_cost_uah:10}],2)"), /FIFO_INSUFFICIENT_COSTED_STOCK/);
});

test("freezes actual batch inputs and includes Serhiy consumables once", () => {
  const result = call("fifo3dpCalculateBatchCost_({actual_weight_g:100,actual_time_h:4,spool_weight_g:1000,spool_price_uah:800,printer_power_kw:0.17,electricity_uah_kwh:4.32,depreciation_uah_h:12,serhiy_consumables_uah:10})");
  assert.equal(result.material_uah, 80);
  assert.equal(result.electricity_uah, 2.9376);
  assert.equal(result.depreciation_uah, 48);
  assert.equal(result.serhiy_consumables_uah, 10);
  assert.equal(result.total_uah, 140.9376);
});

test("reverses into the exact original batches at original cost", () => {
  const result = call(`fifo3dpPlanReversal_([
    {batch_id:'old',row:2,quantity:2,cost_uah:160},
    {batch_id:'new',row:3,quantity:1,cost_uah:100}
  ],[
    {batch_id:'old',row:2,good_qty:2,allocated_qty:2,available_qty:0},
    {batch_id:'new',row:3,good_qty:3,allocated_qty:3,available_qty:0}
  ])`);
  assert.equal(result.quantity, 3);
  assert.equal(result.total_cost_uah, 260);
  assert.deepEqual(JSON.parse(JSON.stringify(result.restores)), [
    { batch_id: 'old', row: 2, quantity: 2, cost_uah: 160 },
    { batch_id: 'new', row: 3, quantity: 1, cost_uah: 100 },
  ]);
});

test("rejects reversal when original batch state no longer supports it", () => {
  assert.throws(() => call(`fifo3dpPlanReversal_([{batch_id:'old',row:2,quantity:2,cost_uah:160}],
    [{batch_id:'old',row:2,good_qty:2,allocated_qty:1,available_qty:1}])`), /FIFO_REVERSAL_BATCH_STATE_CONFLICT/);
});

test("routes reversal only through the specialized FIFO action", () => {
  assert.match(routerCode, /case '3dp_fifo_reverse':\s*return fifo3dpReverseAction_\(spreadsheet, body, actor\);/);
  assert.match(routerCode, /case '3dp_fifo_reconcile':\s*return fifo3dpReconcileAction_\(spreadsheet, actor\);/);
  assert.match(routerCode, /case '3dp_fifo_repair':\s*return fifo3dpRepairAction_\(spreadsheet, body, actor\);/);
  assert.match(code, /FIFO_ALREADY_REVERSED/);
  assert.match(code, /FIFO_ALLOCATION_REVERSED/);
  assert.match(code, /IDEMPOTENCY_CONFLICT/);
  assert.match(code, /FIFO_ALLOCATION_REVERSED/);
});

test("reconciles net consume and reversal quantities to batch state", () => {
  const result = call(`fifo3dpReconcileLedger_([
    {batch_id:'b1',row:2,good_qty:5,allocated_qty:1,available_qty:4}
  ],[
    {allocation_id:'a1',source_type:'crm_sale',quantity:3,total_cost_uah:30,breakdown_json:'[{"batch_id":"b1","row":2,"quantity":3,"cost_uah":30}]',status:'committed',reversal_of:'',projection_row:2},
    {allocation_id:'r1',source_type:'fifo_reversal',quantity:-2,total_cost_uah:-20,breakdown_json:'[{"batch_id":"b1","row":2,"quantity":-2,"cost_uah":-20}]',status:'committed',reversal_of:'a1',projection_row:3}
  ],function(sourceType,row){return row===2?3:-2;})`);
  assert.equal(result.clean, true);
  assert.equal(result.problem_count, 0);
});

test("reports batch and projection drift during reconciliation", () => {
  const result = call(`fifo3dpReconcileLedger_([
    {batch_id:'b1',row:2,good_qty:5,allocated_qty:0,available_qty:5}
  ],[
    {allocation_id:'a1',source_type:'crm_sale',quantity:2,total_cost_uah:20,breakdown_json:'[{"batch_id":"b1","row":2,"quantity":2,"cost_uah":20}]',status:'committed',reversal_of:'',projection_row:2}
  ],function(){return null;})`);
  assert.equal(result.clean, false);
  assert.deepEqual(JSON.parse(JSON.stringify(result.problems.map(p=>p.code))), [
    'FIFO_PROJECTION_MISSING','FIFO_BATCH_ALLOCATED_MISMATCH','FIFO_BATCH_AVAILABLE_MISMATCH'
  ]);
  assert.deepEqual(JSON.parse(JSON.stringify(result.batch_repairs)), [
    { row: 2, batch_id: 'b1', allocated_qty: 2, available_qty: 3 }
  ]);
});

test("repair action refuses to invent a missing business projection", () => {
  assert.match(code, /const blockers = before\.problems\.filter/);
  assert.match(code, /FIFO_REPAIR_BLOCKED/);
  assert.match(code, /STALE_RECONCILIATION/);
  assert.match(code, /FIFO_BATCH_COUNTERS_REPAIRED/);
});
