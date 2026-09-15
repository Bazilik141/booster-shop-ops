import assert from "node:assert/strict";
import test from "node:test";
import { createOperationRunner, refreshUntilFresh } from "../public/operation-state.js";

function deferred() {
  let resolve, reject;
  const promise = new Promise((yes, no) => { resolve = yes;reject = no; });
  return { promise, resolve, reject };
}

test("pending state is immediate, duplicate submit is ignored and lock lasts through refresh", async () => {
  const write = deferred(), refresh = deferred(), runner = createOperationRunner();
  const states = [], result = { row: 9 };
  let calls = 0;
  const pending = runner.run({ execute: () => { calls++;return write.promise; }, refresh: () => refresh.promise, onState: (phase) => states.push(phase) });
  assert.equal(runner.busy, true);
  assert.deepEqual(states, ["pending"]);
  assert.equal(await runner.run({ execute: () => { calls++; }, onState: () => {} }), false);
  assert.equal(calls, 1);
  write.resolve(result);
  await Promise.resolve();await Promise.resolve();
  assert.deepEqual(states, ["pending", "confirmed"]);
  assert.equal(runner.busy, true);
  refresh.resolve();
  assert.equal(await pending, true);
  assert.deepEqual(states, ["pending", "confirmed", "success", "idle"]);
  assert.equal(runner.busy, false);
});

test("write rejection preserves input lifecycle, reports error and releases controls", async () => {
  const runner = createOperationRunner(), states = [];
  let confirmed = false, refreshed = false;
  const error = new Error("STALE_WRITE");
  const accepted = await runner.run({ execute: async () => { throw error; }, onConfirmed: () => { confirmed = true; }, refresh: () => { refreshed = true; }, onState: (phase, detail) => { states.push(phase);if (phase === "error") assert.equal(detail.error, error); } });
  assert.equal(accepted, false);
  assert.equal(confirmed, false);
  assert.equal(refreshed, false);
  assert.deepEqual(states, ["pending", "error", "idle"]);
  assert.equal(runner.busy, false);
});

test("refresh failure keeps confirmed write distinct from a failed write", async () => {
  const runner = createOperationRunner(), states = [], result = { sku: "DRAFT-TEST" };
  let confirmed = null;
  assert.equal(await runner.run({ execute: async () => result, onConfirmed: (value) => { confirmed = value; }, refresh: async () => { throw new Error("read failed"); }, onState: (phase, detail) => { states.push(phase);if (phase === "refresh-error") assert.equal(detail.result, result); } }), true);
  assert.equal(confirmed, result);
  assert.deepEqual(states, ["pending", "confirmed", "refresh-error", "idle"]);
  assert.equal(runner.busy, false);
});

test("post-confirmation UI failure does not misreport the external write", async () => {
  const states = [], runner = createOperationRunner();
  await runner.run({ execute: async () => ({ ok: true }), onConfirmed: () => { throw new Error("UI reset failed"); }, onState: (phase) => states.push(phase) });
  assert.deepEqual(states, ["pending", "confirmed", "refresh-error", "idle"]);
  assert.equal(runner.busy, false);
});

test("success rendering failure still reports an accepted write", async () => {
  const states = [], runner = createOperationRunner();
  assert.equal(await runner.run({ execute: async () => ({ ok: true }), onState: (phase) => { states.push(phase);if (phase === "success") throw new Error("render failed"); } }), true);
  assert.deepEqual(states, ["pending", "confirmed", "success", "refresh-error", "idle"]);
  assert.equal(runner.busy, false);
});

test("formula-backed stock refresh retries reads without repeating the write", async () => {
  let reads = 0, pauses = 0;
  const fresh = await refreshUntilFresh({
    refresh: async () => { reads += 1; },
    isFresh: () => reads >= 3,
    delays: [0, 10, 20, 30],
    pause: async () => { pauses += 1; },
  });
  assert.equal(fresh, true);
  assert.equal(reads, 3);
  assert.equal(pauses, 2);
});

test("formula-backed stock refresh stops after its bounded read budget", async () => {
  let reads = 0;
  const fresh = await refreshUntilFresh({
    refresh: async () => { reads += 1; },
    isFresh: () => false,
    delays: [0, 10, 20],
    pause: async () => {},
  });
  assert.equal(fresh, false);
  assert.equal(reads, 3);
});
