import http from "node:http";
import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import "../shared/print-time.js";
import { calculateBatchCost, settingsFromRange } from "./lib/calculator.mjs";

const here = path.dirname(fileURLToPath(import.meta.url));
const publicDir = path.join(here, "public");
const sharedPrintTimeScript = path.resolve(here, "../shared/print-time.js");
const printTime = globalThis.BoosterPrintTime;
if (!printTime) throw new Error("Shared print-time parser did not load.");
const apiUrl = requiredEnv("BOOSTER_3DP_URL");
const serhiyToken = requiredEnv("BOOSTER_3DP_SERHIY_TOKEN");
const port = requestedPort(process.env.PORT || "3107");
const BATCH_DRAFT_KEYS = Object.freeze([
  "quantity",
  "total_weight_g",
  "total_print_time_h",
  "spool_weight_g",
  "spool_price_uah",
]);
const SETTINGS_ROWS = Object.freeze({
  2: "printer_power_kw",
  3: "electricity_price_uah_per_kwh",
  4: "amortization_uah_per_hour",
  5: "planned_defect_fraction",
});
const PRODUCT_FIELDS = Object.freeze({ Q: true, R: true, S: true });
const DRAFT_FIELDS = Object.freeze(["B", "C", "D", "E", "F", "G", "H", "I", "J", "L", "M", "N", "Q", "R", "S"]);
const REQUEST_ID_PATTERN = /^[A-Za-z0-9_-]{8,80}$/;

const MIME = Object.freeze({
  ".css": "text/css; charset=utf-8",
  ".html": "text/html; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".json": "application/json; charset=utf-8",
});

function requiredEnv(name) {
  const value = String(process.env[name] || "").trim();
  if (!value) throw new Error(`Не задано ${name}. Запусти файл «Запустити.bat» ще раз.`);
  return value;
}

function requestedPort(value) {
  const parsed = Number(value);
  if (!Number.isInteger(parsed) || parsed < 0 || parsed > 65535) throw new Error("Невірний номер локального порту.");
  return parsed;
}

function fail(message, status = 400, code = "LOCAL_VALIDATION") {
  const error = new Error(message);
  error.status = status;
  error.code = code;
  return error;
}

function apiError(payload, fallback) {
  const code = payload?.code || "API_ERROR";
  const tokenHelp = code === "UNAUTHORIZED" ? " Запусти «Змінити токен.bat» і введи новий токен." : "";
  const error = new Error(`${payload?.error || fallback}${tokenHelp}`);
  error.status = 502;
  error.code = code;
  return error;
}

async function call3dpGet(action, params = {}) {
  const url = new URL(apiUrl);
  url.searchParams.set("action", action);
  url.searchParams.set("token", serhiyToken);
  Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, String(value)));
  const response = await fetch(url, { headers: { Accept: "application/json" } });
  const payload = await parseJson(response);
  if (!response.ok || !payload?.ok) throw apiError(payload, `3D-P GET ${action} failed.`);
  return payload;
}

async function call3dpPost(body) {
  const response = await fetch(apiUrl, {
    method: "POST",
    headers: { "Content-Type": "text/plain;charset=utf-8", Accept: "application/json" },
    body: JSON.stringify({ ...body, token: serhiyToken }),
  });
  const payload = await parseJson(response);
  if (!response.ok || !payload?.ok) throw apiError(payload, `3D-P POST ${body.action} failed.`);
  return payload;
}

async function parseJson(response) {
  try {
    return await response.json();
  } catch {
    throw fail("3D-P API returned invalid JSON.", 502, "API_INVALID_JSON");
  }
}

async function getSettings() {
  const payload = await call3dpGet("3dp_get_range", { sheet: "Налаштування", range: "B2:B5" });
  return settingsFromRange(payload.values);
}

function cleanSku(value) {
  const sku = String(value || "").trim();
  if (!sku) throw fail("Оберіть SKU.");
  if (sku.length > 120) throw fail("SKU is too long.");
  return sku;
}

function finitePositive(value, label) {
  const parsed = Number(value);
  if (!Number.isFinite(parsed) || parsed <= 0) throw fail(`${label} має бути додатним числом.`);
  return parsed;
}

function optionalNonNegative(value, label) {
  if (value === "" || value === null || typeof value === "undefined") return 0;
  const parsed = Number(value);
  if (!Number.isFinite(parsed) || parsed < 0) throw fail(`${label} має бути числом не менше нуля.`);
  return parsed;
}

function wholeNonNegative(value, label) {
  const parsed = Number(value);
  if (!Number.isInteger(parsed) || parsed < 0) throw fail(`${label} має бути цілим числом не менше нуля.`);
  return parsed;
}

function requiredText(value, label, maxLength = 500) {
  const text = String(value || "").trim();
  if (!text) throw fail(`${label} обов’язкове.`);
  if (text.length > maxLength) throw fail(`${label} задовге.`);
  return text;
}

function decimalPrintTime(value, label) {
  const parsed = printTime.parse(value);
  if (!parsed.ok || parsed.blank || !(parsed.hours > 0)) {
    throw fail(`${label}: ${parsed.error || "вкажіть значення більше нуля."}`);
  }
  return parsed.hours;
}

function normalizedForApi(value) {
  return Number(Number(value).toFixed(8));
}

function sameValue(left, right) {
  const a = left === null || typeof left === "undefined" || left === "" ? "" : String(left);
  const b = right === null || typeof right === "undefined" || right === "" ? "" : String(right);
  return a === b;
}

function batchInput(body) {
  return {
    quantity: finitePositive(body.quantity, "Кількість у партії"),
    total_weight_g: finitePositive(body.total_weight_g, "Сумарна вага"),
    total_print_time_h: decimalPrintTime(body.total_print_time_h, "Сумарний час"),
    spool_weight_g: finitePositive(body.spool_weight_g, "Вага котушки"),
    spool_price_uah: finitePositive(body.spool_price_uah, "Ціна котушки"),
  };
}


function draftValues(payload) {
  const source = payload?.values || {};
  return Object.fromEntries(BATCH_DRAFT_KEYS.map((key) => [key, source[key] ?? ""]));
}

async function readSku(sku) {
  const payload = await call3dpGet("3dp_get_row", { sheet: "Номенклатура", sku });
  return payload.row;
}

async function readBatchDraft(sku) {
  const payload = await call3dpGet("3dp_batch_draft", { sku });
  return { ...payload, values: draftValues(payload) };
}

async function saveBatch(body) {
  const sku = cleanSku(body.sku);
  const input = batchInput(body);
  const [settings, row, currentDraft] = await Promise.all([getSettings(), readSku(sku), readBatchDraft(sku)]);
  const calculation = calculateBatchCost(input, settings);
  const rawDraft = await call3dpPost({
    action: "3dp_batch_draft_save",
    sku,
    values: input,
    expected_current: currentDraft.values,
  });
  const writes = [
    ["G", row["Час друку за од., год"], normalizedForApi(calculation.per_unit.time_hours)],
    ["H", row["Вага виробу за од., г"], normalizedForApi(calculation.per_unit.weight_g)],
    ["I", row["Вага котушки, г"], normalizedForApi(calculation.spool_weight_g)],
    ["J", row["Ціна котушки, грн"], normalizedForApi(calculation.spool_price_uah)],
  ];
  const applied = [];
  for (const [column, expectedCurrent, value] of writes) {
    if (sameValue(expectedCurrent, value)) continue;
    const result = await call3dpPost({
      action: "3dp_write",
      sheet: "Номенклатура",
      sku_or_row: sku,
      column,
      value,
      expected_current: expectedCurrent ?? "",
    });
    applied.push(result.cell);
  }
  return {
    sku,
    calculation,
    draft: draftValues(rawDraft),
    draft_already_current: Boolean(rawDraft.already_applied),
    cells_updated: applied,
    already_current: Boolean(rawDraft.already_applied) && applied.length === 0,
  };
}

async function saveFixture(body) {
  const sku = cleanSku(body.sku);
  const fixtureName = String(body.fixture_name || "").trim();
  const [row, information] = await Promise.all([readSku(sku), call3dpGet("3dp_information_bootstrap")]);
  const fixtures = information.fixtures || { rows: [] };
  let value = "";
  if (fixtureName) {
    const fixture = fixtures.rows.find((item) => String(item["Назва фурнітури"] || "").trim() === fixtureName);
    if (!fixture) throw fail("Обрана фурнітура відсутня у довіднику.");
    value = finitePositive(fixture["Ціна, грн/шт"], "Ціна фурнітури");
  }
  const expectedCurrent = row["Фурнітура (ціна-довідка), грн/шт"] ?? "";
  if (sameValue(expectedCurrent, value)) return { sku, price_uah: value, already_current: true };
  const result = await call3dpPost({
    action: "3dp_write",
    sheet: "Номенклатура",
    sku_or_row: sku,
    column: "N",
    value,
    expected_current: expectedCurrent,
  });
  return { sku, price_uah: value, cell: result.cell };
}

async function saveSetting(body) {
  const row = Number(body.row);
  if (!Number.isInteger(row) || !SETTINGS_ROWS[row]) throw fail("Дозволені лише Налаштування!B2:B5.");
  return call3dpPost({
    action: "3dp_write",
    sheet: "Налаштування",
    sku_or_row: row,
    column: "B",
    value: body.value,
    expected_current: body.expected_current ?? "",
  });
}

async function readSettingsJournal() {
  const payload = await call3dpGet("3dp_settings_journal", { limit: 50 });
  return { rows: payload.rows || [], count: Number(payload.count || 0) };
}

async function saveProductField(body) {
  const sku = cleanSku(body.sku);
  const column = String(body.column || "").trim().toUpperCase();
  if (!PRODUCT_FIELDS[column]) throw fail("Дозволені лише поля Q, R або S.");
  return call3dpPost({
    action: "3dp_write",
    sheet: "Номенклатура",
    sku_or_row: sku,
    column,
    value: body.value,
    expected_current: body.expected_current ?? "",
  });
}

async function correctStock(body) {
  return call3dpPost({
    action: "3dp_adjust_stock",
    sku: cleanSku(body.sku),
    new_value: wholeNonNegative(body.new_value, "Фактична кількість"),
    expected_current: wholeNonNegative(body.expected_current, "Поточна кількість"),
    reason: requiredText(body.reason, "Причина", 250),
  });
}

function payoutInput(body) {
  const row = Number(body.row_number);
  const acknowledgement = String(body.acknowledgement || "").trim();
  if (!Number.isInteger(row) || row < 2) throw fail("Невірний рядок виплати.");
  if (!["amount_agreed", "money_received"].includes(acknowledgement)) throw fail("Невірний тип підтвердження.");
  return { row_number: row, expected_period: requiredText(body.expected_period, "Період", 20), acknowledgement };
}

async function acknowledgePayout(body, correction) {
  const payload = { action: correction ? "3dp_payout_acknowledgement_correct" : "3dp_payout_acknowledge", ...payoutInput(body) };
  if (correction) {
    payload.expected_current = body.expected_current ?? "";
    payload.reason = requiredText(body.reason, "Причина виправлення", 250);
  }
  return call3dpPost(payload);
}

async function createDraft(body) {
  const supplied = body?.values && typeof body.values === "object" && !Array.isArray(body.values) ? body.values : {};
  const unknown = Object.keys(supplied).filter((column) => !DRAFT_FIELDS.includes(column));
  if (unknown.length) throw fail(`Недозволені поля чернетки: ${unknown.join(", ")}.`);
  const values = {};
  DRAFT_FIELDS.forEach((column) => {
    if (!Object.prototype.hasOwnProperty.call(supplied, column)) return;
    const value = supplied[column];
    if (value === "" || value === null || typeof value === "undefined") return;
    values[column] = value;
  });
  if (!String(values.B || "").trim() || !String(values.D || "").trim()) throw fail("Для чернетки потрібні назва виробу і тип.");
  return call3dpPost({ action: "3dp_nomenclature_draft_create", values });
}

async function appendPrintLog(body) {
  const sku = cleanSku(body.sku);
  const requestId = String(body.request_id || "").trim();
  if (!REQUEST_ID_PATTERN.test(requestId)) throw fail("Стабільний request_id для цієї спроби відсутній.");
  return call3dpPost({
    action: "3dp_manufacture_batch",
    sku,
    quantity: finitePositive(body.printed_quantity, "Надруковано, шт"),
    defects: wholeNonNegative(body.defects ?? 0, "Брак"),
    total_print_time_h: decimalPrintTime(body.actual_time_hours, "Час друку факт"),
    total_weight_g: optionalNonNegative(body.actual_material_g, "Витрачено матеріалу"),
    printed_by: "Сергій",
    request_id: requestId,
    note: String(body.notes || "").trim().slice(0, 220),
  });
}

async function updateDefect(body) {
  const row = Number(body.row);
  if (!Number.isInteger(row) || row < 2) throw fail("Невірний рядок Друк-логу.");
  const defects = optionalNonNegative(body.defects, "Брак");
  const expected = body.expected_current ?? "";
  return call3dpPost({
    action: "3dp_print_log_update",
    row,
    changes: { E: defects },
    expected_current: { E: expected },
  });
}

async function bootstrap() {
  const [core, information, printLog] = await Promise.all([
    call3dpGet("3dp_bootstrap", { include_archived: "true" }),
    call3dpGet("3dp_information_bootstrap"),
    call3dpGet("3dp_print_log", { include_archived: "false" }),
  ]);
  return {
    overview: core.overview?.summary || {},
    skus: core.skus?.rows || [],
    settings: settingsFromRange(core.settings?.values),
    settings_values: (core.settings?.values || []).map((row) => row?.[0] ?? ""),
    analytics: core.analytics?.values || [],
    fixtures: information.fixtures?.rows || [],
    print_log: printLog.rows || [],
    sales: information.sales?.rows || [],
    payouts: information.payouts?.rows || [],
    plyushky: information.plyushky?.rows || [],
  };
}

async function readBody(request) {
  const chunks = [];
  let size = 0;
  for await (const chunk of request) {
    size += chunk.length;
    if (size > 32 * 1024) throw fail("Request body is too large.", 413, "BODY_TOO_LARGE");
    chunks.push(chunk);
  }
  try {
    return JSON.parse(Buffer.concat(chunks).toString("utf8") || "{}");
  } catch {
    throw fail("Невірний JSON-запит.");
  }
}

function json(response, status, payload) {
  response.writeHead(status, {
    "Content-Type": "application/json; charset=utf-8",
    "Cache-Control": "no-store",
    "X-Content-Type-Options": "nosniff",
  });
  response.end(JSON.stringify(payload));
}

async function serveStatic(response, pathname) {
  if (pathname === "/print-time.js") {
    const content = await fs.readFile(sharedPrintTimeScript);
    response.writeHead(200, {
      "Content-Type": MIME[".js"],
      "Cache-Control": "no-store",
      "X-Content-Type-Options": "nosniff",
    });
    response.end(content);
    return;
  }
  const requested = pathname === "/" ? "/index.html" : pathname;
  const target = path.resolve(publicDir, `.${requested}`);
  if (!target.startsWith(`${publicDir}${path.sep}`)) throw fail("Not found.", 404, "NOT_FOUND");
  const extension = path.extname(target);
  if (!MIME[extension]) throw fail("Not found.", 404, "NOT_FOUND");
  try {
    const content = await fs.readFile(target);
    response.writeHead(200, {
      "Content-Type": MIME[extension],
      "Cache-Control": "no-store",
      "X-Content-Type-Options": "nosniff",
    });
    response.end(content);
  } catch (error) {
    if (error.code === "ENOENT") throw fail("Not found.", 404, "NOT_FOUND");
    throw error;
  }
}

const server = http.createServer(async (request, response) => {
  try {
    const url = new URL(request.url, "http://127.0.0.1");
    if (request.method === "GET" && url.pathname === "/api/bootstrap") return json(response, 200, { ok: true, ...(await bootstrap()) });
    if (request.method === "GET" && url.pathname === "/api/batch-draft") return json(response, 200, { ok: true, ...(await readBatchDraft(cleanSku(url.searchParams.get("sku"))) ) });
    if (request.method === "GET" && url.pathname === "/api/settings-journal") return json(response, 200, { ok: true, ...(await readSettingsJournal()) });
    if (request.method === "POST" && url.pathname === "/api/calculate") {
      const calculation = calculateBatchCost(batchInput(await readBody(request)), await getSettings());
      return json(response, 200, { ok: true, calculation });
    }
    if (request.method === "POST" && url.pathname === "/api/save-batch") return json(response, 200, { ok: true, ...(await saveBatch(await readBody(request))) });
    if (request.method === "POST" && url.pathname === "/api/save-fixture") return json(response, 200, { ok: true, ...(await saveFixture(await readBody(request))) });
    if (request.method === "POST" && url.pathname === "/api/setting") return json(response, 200, { ok: true, ...(await saveSetting(await readBody(request))) });
    if (request.method === "POST" && url.pathname === "/api/product-field") return json(response, 200, { ok: true, ...(await saveProductField(await readBody(request))) });
    if (request.method === "POST" && url.pathname === "/api/stock-correction") return json(response, 200, { ok: true, ...(await correctStock(await readBody(request))) });
    if (request.method === "POST" && url.pathname === "/api/payout-acknowledge") return json(response, 200, { ok: true, ...(await acknowledgePayout(await readBody(request), false)) });
    if (request.method === "POST" && url.pathname === "/api/payout-acknowledgement-correct") return json(response, 200, { ok: true, ...(await acknowledgePayout(await readBody(request), true)) });
    if (request.method === "POST" && url.pathname === "/api/draft") return json(response, 200, { ok: true, ...(await createDraft(await readBody(request))) });
    if (request.method === "POST" && url.pathname === "/api/print-log") return json(response, 200, { ok: true, ...(await appendPrintLog(await readBody(request))) });
    if (request.method === "POST" && url.pathname === "/api/defect") return json(response, 200, { ok: true, ...(await updateDefect(await readBody(request))) });
    if (request.method === "GET") return serveStatic(response, url.pathname);
    throw fail("Not found.", 404, "NOT_FOUND");
  } catch (error) {
    json(response, Number(error.status) || 500, { ok: false, code: error.code || "LOCAL_SERVER_ERROR", error: error.message || "Unexpected local-server error." });
  }
});

server.listen(port, "127.0.0.1", () => {
  const address = server.address();
  console.log(`Сторінка Сергія працює: http://127.0.0.1:${address.port}`);
  console.log("Щоб зупинити її, закрий це вікно.");
});
