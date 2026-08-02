import http from "node:http";
import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { calculateBatchCost, settingsFromRange } from "./lib/calculator.mjs";

const here = path.dirname(fileURLToPath(import.meta.url));
const publicDir = path.join(here, "public");
const apiUrl = requiredEnv("BOOSTER_3DP_URL");
const serhiyToken = requiredEnv("BOOSTER_3DP_SERHIY_TOKEN");
const port = Number(process.env.PORT || 3107);

const MIME = Object.freeze({
  ".css": "text/css; charset=utf-8",
  ".html": "text/html; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".json": "application/json; charset=utf-8",
});

function requiredEnv(name) {
  const value = String(process.env[name] || "").trim();
  if (!value) {
    throw new Error(`${name} is required. Set it locally; never put a real token in a file.`);
  }
  return value;
}

function fail(message, status = 400, code = "LOCAL_VALIDATION") {
  const error = new Error(message);
  error.status = status;
  error.code = code;
  return error;
}

function apiError(payload, fallback) {
  const error = new Error(payload?.error || fallback);
  error.status = 502;
  error.code = payload?.code || "API_ERROR";
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
  const payload = await call3dpGet("3dp_get_range", { sheet: "Налаштування", range: "A1:C4" });
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

function normalizedForApi(value) {
  return Number(Number(value).toFixed(8));
}

function sameValue(left, right) {
  const a = left === null || typeof left === "undefined" || left === "" ? "" : String(left);
  const b = right === null || typeof right === "undefined" || right === "" ? "" : String(right);
  return a === b;
}

async function readSku(sku) {
  const payload = await call3dpGet("3dp_get_row", { sheet: "Номенклатура", sku });
  return payload.row;
}

async function saveBatch(body) {
  const sku = cleanSku(body.sku);
  const input = {
    quantity: finitePositive(body.quantity, "Кількість у партії"),
    total_weight_g: finitePositive(body.total_weight_g, "Сумарна вага"),
    total_time_hours: finitePositive(body.total_time_hours, "Сумарний час"),
    spool_weight_g: finitePositive(body.spool_weight_g, "Вага котушки"),
    spool_price_uah: finitePositive(body.spool_price_uah, "Ціна котушки"),
  };
  const [settings, row] = await Promise.all([getSettings(), readSku(sku)]);
  const calculation = calculateBatchCost(input, settings);
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
  return { sku, calculation, cells_updated: applied, already_current: applied.length === 0 };
}

async function saveFixture(body) {
  const sku = cleanSku(body.sku);
  const fixtureName = String(body.fixture_name || "").trim();
  const [row, fixtures] = await Promise.all([readSku(sku), call3dpGet("3dp_fixtures")]);
  let value = "";
  if (fixtureName) {
    const fixture = fixtures.rows.find((item) => String(item["Назва фурнітури"] || "").trim() === fixtureName);
    if (!fixture) throw fail("Обрана фурнітура відсутня у довіднику.");
    value = finitePositive(fixture["Ціна, грн/шт"], "Ціна фурнітури");
  }
  const expectedCurrent = row["Фурнітура (ланцюжок/карабін), грн/шт"] ?? "";
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

async function appendPrintLog(body) {
  const sku = cleanSku(body.sku);
  const today = new Date().toISOString().slice(0, 10);
  const values = {
    A: String(body.date || today).trim(),
    B: sku,
    C: finitePositive(body.printed_quantity, "Надруковано, шт"),
    D: finitePositive(body.actual_time_hours, "Час друку факт"),
    E: optionalNonNegative(body.defects, "Брак"),
    F: finitePositive(body.actual_material_g, "Витрачено матеріалу"),
    H: String(body.printer || "Сергій").trim().slice(0, 120),
    I: String(body.notes || "").trim().slice(0, 1000),
  };
  if (!/^\d{4}-\d{2}-\d{2}$/.test(values.A)) throw fail("Дата має бути у форматі РРРР-ММ-ДД.");
  return call3dpPost({ action: "3dp_append_row", sheet: "Друк-лог", values });
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
  const [overview, skus, fixtures, settings, printLog] = await Promise.all([
    call3dpGet("3dp_overview"),
    call3dpGet("3dp_skus"),
    call3dpGet("3dp_fixtures"),
    getSettings(),
    call3dpGet("3dp_print_log", { include_archived: "false" }),
  ]);
  return { overview: overview.summary, skus: skus.rows, fixtures: fixtures.rows, settings, print_log: printLog.rows };
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
    if (request.method === "POST" && url.pathname === "/api/calculate") {
      const body = await readBody(request);
      const calculation = calculateBatchCost(body, await getSettings());
      return json(response, 200, { ok: true, calculation });
    }
    if (request.method === "POST" && url.pathname === "/api/save-batch") return json(response, 200, { ok: true, ...(await saveBatch(await readBody(request))) });
    if (request.method === "POST" && url.pathname === "/api/save-fixture") return json(response, 200, { ok: true, ...(await saveFixture(await readBody(request))) });
    if (request.method === "POST" && url.pathname === "/api/print-log") return json(response, 200, { ok: true, ...(await appendPrintLog(await readBody(request))) });
    if (request.method === "POST" && url.pathname === "/api/defect") return json(response, 200, { ok: true, ...(await updateDefect(await readBody(request))) });
    if (request.method === "GET") return serveStatic(response, url.pathname);
    throw fail("Not found.", 404, "NOT_FOUND");
  } catch (error) {
    json(response, Number(error.status) || 500, { ok: false, code: error.code || "LOCAL_SERVER_ERROR", error: error.message || "Unexpected local-server error." });
  }
});

server.listen(port, "127.0.0.1", () => {
  console.log(`3D-P Serhiy server is running at http://127.0.0.1:${port}`);
  console.log("Bound to localhost only. Press Ctrl+C to stop it.");
});
