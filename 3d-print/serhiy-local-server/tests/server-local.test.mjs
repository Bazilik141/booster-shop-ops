import assert from "node:assert/strict";
import fs from "node:fs";
import http from "node:http";
import { once } from "node:events";
import { spawn } from "node:child_process";
import path from "node:path";
import test from "node:test";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const appRoot = path.resolve(here, "..");
const serhiyToken = "local-test-identity";

function respond(response, payload, status = 200) { response.writeHead(status, { "Content-Type": "application/json; charset=utf-8" });response.end(JSON.stringify(payload)); }
async function readJson(request) { const chunks = [];for await (const chunk of request) chunks.push(chunk);return JSON.parse(Buffer.concat(chunks).toString("utf8")); }

async function startFakeApi() {
  const state = {
    settings: [0.17, 4.32, 12, 0.08],
    draft: { quantity: "", total_weight_g: "", total_print_time_h: "", spool_weight_g: "", spool_price_uah: "" },
    sku: { SKU: "FIG-TEST-001", "Назва виробу": "Тестова фігурка", "Час друку за од., год": "", "Вага виробу за од., г": "", "Вага котушки, г": "", "Ціна котушки, грн": "", "Фурнітура (ціна-довідка), грн/шт": "", "РРЦ фактична, грн": 100, "Ціна під викуп, грн": 30, "Посилання на модель": "https://example.invalid/model", "API_статус_запису": "Активний", availability: { "Наявно зараз, шт": 3 } },
    payout: { row_number: 2, "Період (РРРР-ММ)": "2026-08", "Нараховано Сергію за період, грн": 12.5, "Дата фактичної виплати": "2026-08-23", Статус: "Виплачено", "Згода Сергія із сумою (Київ, роль)": "", "Кошти надійшли Сергію (Київ, роль)": "" },
    journal: [{ row_number: 2, "Час (Київ)": "2026-08-23 12:00", Роль: "serhiy", Параметр: "Потужність принтера, кВт", Було: 0.16, Стало: 0.17, SKU: "" }],
    requests: [], manufactureIds: new Set(), failNext: null,
  };
  const server = http.createServer(async (request, response) => {
    const url = new URL(request.url, "http://127.0.0.1"),method = request.method,body = method === "POST" ? await readJson(request) : null;
    const action = method === "POST" ? body.action : url.searchParams.get("action"),token = method === "POST" ? body.token : url.searchParams.get("token");
    state.requests.push({ method, action, token, body, params: Object.fromEntries(url.searchParams) });
    if (token !== serhiyToken) return respond(response, { ok: false, code: "UNAUTHORIZED", error: "Bad test identity." }, 401);
    if (state.failNext?.action === action) { const failure = state.failNext;state.failNext = null;return respond(response, { ok: false, code: failure.code, error: failure.error }, 403); }

    if (method === "GET" && action === "3dp_bootstrap") return respond(response, { ok: true, action, overview: { summary: { sku_count: 1, available: 3, printed: 4, defects: 1, accrued_serhiy_current_month: 12.5 } }, skus: { rows: [{ ...state.sku, availability: { ...state.sku.availability } }] }, settings: { values: state.settings.map((value) => [value]) }, analytics: { values: [["SKU", "Назва", "Собівартість Сергія, грн"], [state.sku.SKU, state.sku["Назва виробу"], 11.2]] } });
    if (method === "GET" && action === "3dp_information_bootstrap") return respond(response, { ok: true, action, sales: { rows: [{ row_number: 2, Дата: "2026-08-23", SKU: state.sku.SKU, Кількість: 1 }] }, plyushky: { rows: [{ row_number: 2, Дата: "2026-08-23", SKU: state.sku.SKU, "Видано як бонус, шт": 1 }] }, payouts: { rows: [{ ...state.payout }] }, fixtures: { rows: [{ row_number: 2, "Назва фурнітури": "Карабін", "Ціна, грн/шт": 4 }] } });
    if (method === "GET" && action === "3dp_print_log") return respond(response, { ok: true, rows: [{ row_number: 7, Дата: "2026-08-02", SKU: state.sku.SKU, "Надруковано, шт": 4, "Час друку факт, год": 2, "Брак, шт": 1 }] });
    if (method === "GET" && action === "3dp_get_range") { assert.equal(url.searchParams.get("sheet"), "Налаштування");assert.equal(url.searchParams.get("range"), "B2:B5");return respond(response, { ok: true, values: state.settings.map((value) => [value]) }); }
    if (method === "GET" && action === "3dp_get_row") return respond(response, { ok: true, row: { ...state.sku } });
    if (method === "GET" && action === "3dp_batch_draft") return respond(response, { ok: true, action, sku: state.sku.SKU, found: Boolean(state.draft.quantity), values: { ...state.draft } });
    if (method === "GET" && action === "3dp_settings_journal") return respond(response, { ok: true, action, rows: state.journal, count: state.journal.length });

    if (method === "POST" && action === "3dp_batch_draft_save") { assert.deepEqual(body.expected_current, state.draft);state.draft = { ...body.values };return respond(response, { ok: true, action, sku: state.sku.SKU, row: 2, values: { ...state.draft } }); }
    if (method === "POST" && action === "3dp_write") {
      if (body.sheet === "Налаштування") { const index = Number(body.sku_or_row) - 2;assert.equal(body.column, "B");assert.equal(String(state.settings[index]), String(body.expected_current));state.settings[index] = Number(body.value);return respond(response, { ok: true, action, cell: `B${body.sku_or_row}`, old_value: body.expected_current, new_value: state.settings[index] }); }
      const map = { G: "Час друку за од., год", H: "Вага виробу за од., г", I: "Вага котушки, г", J: "Ціна котушки, грн", N: "Фурнітура (ціна-довідка), грн/шт", Q: "РРЦ фактична, грн", R: "Ціна під викуп, грн", S: "Посилання на модель" };
      assert.equal(body.expected_current, state.sku[map[body.column]]);state.sku[map[body.column]] = body.value;return respond(response, { ok: true, action, cell: `${body.column}2`, new_value: body.value });
    }
    if (method === "POST" && action === "3dp_adjust_stock") { assert.equal(Object.hasOwn(body, "delta"), false);assert.equal(body.expected_current, state.sku.availability["Наявно зараз, шт"]);const old = body.expected_current;state.sku.availability["Наявно зараз, шт"] = body.new_value;return respond(response, { ok: true, action, sku: body.sku, old_value: old, new_value: body.new_value, delta: body.new_value - old }); }
    if (method === "POST" && action === "3dp_manufacture_batch") { assert.equal(body.printed_by, "Сергій");const duplicate = state.manufactureIds.has(body.request_id);state.manufactureIds.add(body.request_id);return respond(response, { ok: true, action, row: 8, request_id: body.request_id, already_applied: duplicate }); }
    if (method === "POST" && action === "3dp_payout_acknowledge") { const header = acknowledgementHeaders[body.acknowledgement];assert.equal(state.payout[header], "");state.payout[header] = `2026-08-23 13:00 · serhiy`;return respond(response, { ok: true, action, row: 2, period: body.expected_period, acknowledgement: body.acknowledgement, new_value: state.payout[header] }); }
    if (method === "POST" && action === "3dp_payout_acknowledgement_correct") { const header = acknowledgementHeaders[body.acknowledgement];assert.equal(body.expected_current, state.payout[header]);state.payout[header] = `2026-08-23 13:05 · serhiy`;return respond(response, { ok: true, action, row: 2, period: body.expected_period, acknowledgement: body.acknowledgement, new_value: state.payout[header] }); }
    if (method === "POST" && action === "3dp_nomenclature_draft_create") { assert.equal(body.values.B, "Новий виріб");assert.equal(body.values.D, "Панно");return respond(response, { ok: true, action, row: 3, sku: "DRAFT-20260823-001", status: "Чернетка", sku_suggestion: { prefix: "FIG", category_digits: "400", category_label: "Пласка настінна форма" } }); }
    if (method === "POST" && action === "3dp_print_log_update") return respond(response, { ok: true, action, row: body.row, changes: 1 });
    return respond(response, { ok: false, code: "UNEXPECTED_TEST_ACTION", error: `${method} ${action}` }, 400);
  });
  server.listen(0, "127.0.0.1");await once(server, "listening");
  return { state, url: `http://127.0.0.1:${server.address().port}`, async close() { server.close();await once(server, "close"); } };
}

const acknowledgementHeaders = { amount_agreed: "Згода Сергія із сумою (Київ, роль)", money_received: "Кошти надійшли Сергію (Київ, роль)" };
async function startLocalServer(apiUrl) {
  const child = spawn(process.execPath, ["server.mjs"], { cwd: appRoot, env: { ...process.env, BOOSTER_3DP_URL: apiUrl, BOOSTER_3DP_SERHIY_TOKEN: serhiyToken, PORT: "0" }, stdio: ["ignore", "pipe", "pipe"] });
  let output = "",errors = "";child.stdout.on("data", (chunk) => { output += chunk.toString("utf8"); });child.stderr.on("data", (chunk) => { errors += chunk.toString("utf8"); });
  const url = await new Promise((resolve, reject) => { const timer = setTimeout(() => reject(new Error(`Local server did not start. ${output} ${errors}`)), 5000);const inspect = () => { const match = output.match(/http:\/\/127\.0\.0\.1:(\d+)/);if (!match) return;clearTimeout(timer);resolve(`http://127.0.0.1:${match[1]}`); };child.stdout.on("data", inspect);child.once("exit", (code) => { clearTimeout(timer);reject(new Error(`Local server exited early (${code}). ${output} ${errors}`)); });inspect(); });
  return { url, async close() { if (child.exitCode === null) { child.kill();await once(child, "exit"); } } };
}
async function localResponse(url, body) { const response = await fetch(url, body ? { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) } : undefined);return { response, payload: await response.json() }; }
async function localJson(url, body) { const result = await localResponse(url, body);assert.equal(result.response.ok, true, result.payload.error);assert.equal(result.payload.ok, true, result.payload.error);return result.payload; }
async function expectApiError(url, body, code, message) { const result = await localResponse(url, body);assert.equal(result.response.ok, false);assert.equal(result.payload.ok, false);assert.equal(result.payload.code, code);assert.equal(result.payload.error, message); }

test("WP2 local server uses projected bundles and exposes every Serhiy route", { timeout: 20000 }, async (context) => {
  const api = await startFakeApi();context.after(() => api.close());const local = await startLocalServer(api.url);context.after(() => local.close());
  const pageHtml = await (await fetch(local.url)).text(),appJs = fs.readFileSync(path.join(appRoot, "public", "app.js"), "utf8");
  assert.match(pageHtml, /zone-calculator/);assert.match(pageHtml, /zone-products/);assert.match(pageHtml, /zone-information/);assert.equal(pageHtml.includes(["Синхронізація", " з CRM"].join("")), false);assert.match(pageHtml, /фінальний артикул призначає власник/);
  assert.match(appJs, /new_value/);assert.doesNotMatch(appJs, /action:\s*["']3dp_payout_create/);assert.match(appJs, /defect_adjusted_uah/);

  const bootstrap = await localJson(`${local.url}/api/bootstrap`);
  assert.equal(bootstrap.skus[0].SKU, "FIG-TEST-001");assert.equal(bootstrap.settings.planned_defect_fraction, 0.08);assert.equal(bootstrap.sales[0].SKU, "FIG-TEST-001");assert.equal(bootstrap.analytics[0][0], "SKU");
  assert.deepEqual(api.state.requests.slice(0, 3).map((item) => item.action).sort(), ["3dp_bootstrap", "3dp_information_bootstrap", "3dp_print_log"].sort());
  assert.equal(api.state.requests.some((item) => item.params.sheet === ["Ле", "генда"].join("")), false);

  const calculation = await localJson(`${local.url}/api/calculate`, { quantity: 36, total_weight_g: 180, total_print_time_h: "18:00", spool_weight_g: 1000, spool_price_uah: 800 });
  assert.equal(calculation.calculation.costs.defect_adjusted_uah, 11.196576);
  const settingsRead = api.state.requests.find((item) => item.action === "3dp_get_range");assert.equal(settingsRead.params.range, "B2:B5");

  await localJson(`${local.url}/api/settings-journal`);
  await localJson(`${local.url}/api/setting`, { row: 2, value: 0.18, expected_current: 0.17 });
  await localJson(`${local.url}/api/save-fixture`, { sku: "FIG-TEST-001", fixture_name: "Карабін" });
  for (const [column, value, expected] of [["Q", 120, 100], ["R", 35, 30], ["S", "https://example.invalid/new", "https://example.invalid/model"]]) await localJson(`${local.url}/api/product-field`, { sku: "FIG-TEST-001", column, value, expected_current: expected });
  const stock = await localJson(`${local.url}/api/stock-correction`, { sku: "FIG-TEST-001", new_value: 5, expected_current: 3, reason: "Контрольний перерахунок" });assert.equal(stock.new_value, 5);
  const agreed = await localJson(`${local.url}/api/payout-acknowledge`, { row_number: 2, expected_period: "2026-08", acknowledgement: "amount_agreed" });
  await localJson(`${local.url}/api/payout-acknowledgement-correct`, { row_number: 2, expected_period: "2026-08", acknowledgement: "amount_agreed", expected_current: agreed.new_value, reason: "Уточнення мітки" });
  await localJson(`${local.url}/api/payout-acknowledge`, { row_number: 2, expected_period: "2026-08", acknowledgement: "money_received" });
  const draft = await localJson(`${local.url}/api/draft`, { values: { B: "Новий виріб", D: "Панно", M: "тест" } });assert.equal(draft.sku_suggestion.category_digits, "400");

  const requestId = "serhiy_test_batch_0001";
  const manufactured = await localJson(`${local.url}/api/print-log`, { sku: "FIG-TEST-001", printed_quantity: 2, actual_time_hours: "1 год 39 хв", actual_material_g: 10, defects: 0, notes: "test", request_id: requestId });assert.equal(manufactured.already_applied, false);
  const repeat = await localJson(`${local.url}/api/print-log`, { sku: "FIG-TEST-001", printed_quantity: 2, actual_time_hours: "1 год 39 хв", actual_material_g: 10, defects: 0, notes: "test", request_id: requestId });assert.equal(repeat.already_applied, true);
  const manufactureRequests = api.state.requests.filter((item) => item.action === "3dp_manufacture_batch");assert.equal(manufactureRequests.length, 2);assert.equal(manufactureRequests[0].body.request_id, manufactureRequests[1].body.request_id);assert.equal(manufactureRequests[0].body.printed_by, "Сергій");

  const saved = await localJson(`${local.url}/api/save-batch`, { sku: "FIG-TEST-001", quantity: 36, total_weight_g: 180, total_print_time_h: "18:00", spool_weight_g: 1000, spool_price_uah: 800 });assert.deepEqual(saved.cells_updated, ["G2", "H2", "I2", "J2"]);
  assert.ok(api.state.requests.every((item) => item.token === serhiyToken));
  assert.equal(api.state.requests.some((item) => ["3dp_payout_create", "3dp_payout_mark_paid", "3dp_nomenclature_owner_create"].includes(item.action)), false);
});

test("API errors cross the local boundary and token rejection adds the recovery hint", { timeout: 20000 }, async (context) => {
  const api = await startFakeApi();context.after(() => api.close());const local = await startLocalServer(api.url);context.after(() => local.close());
  api.state.failNext = { action: "3dp_settings_journal", code: "UNAUTHORIZED", error: "Invalid token." };await expectApiError(`${local.url}/api/settings-journal`, null, "UNAUTHORIZED", "Invalid token. Запусти «Змінити токен.bat» і введи новий токен.");
  api.state.failNext = { action: "3dp_settings_journal", code: "READ_PROJECTION_FORBIDDEN", error: "Journal is not projected." };await expectApiError(`${local.url}/api/settings-journal`, null, "READ_PROJECTION_FORBIDDEN", "Journal is not projected.");
  api.state.failNext = { action: "3dp_write", code: "RANGE_NOT_PROJECTED", error: "Only B2:B5 is projected." };await expectApiError(`${local.url}/api/setting`, { row: 2, value: 0.2, expected_current: 0.17 }, "RANGE_NOT_PROJECTED", "Only B2:B5 is projected.");
  api.state.failNext = { action: "3dp_write", code: "STALE_WRITE", error: "The cell changed after it was read." };await expectApiError(`${local.url}/api/product-field`, { sku: "FIG-TEST-001", column: "Q", value: 120, expected_current: 100 }, "STALE_WRITE", "The cell changed after it was read.");
  api.state.failNext = { action: "3dp_nomenclature_draft_create", code: "FORBIDDEN", error: "Owner-only action refused." };await expectApiError(`${local.url}/api/draft`, { values: { B: "Новий виріб", D: "Панно" } }, "FORBIDDEN", "Owner-only action refused.");
});
