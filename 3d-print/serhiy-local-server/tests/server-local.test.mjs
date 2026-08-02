import assert from "node:assert/strict";
import http from "node:http";
import { once } from "node:events";
import { spawn } from "node:child_process";
import path from "node:path";
import test from "node:test";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const appRoot = path.resolve(here, "..");
const serhiyToken = "serhiy-test-token";

function respond(response, payload, status = 200) {
  response.writeHead(status, { "Content-Type": "application/json; charset=utf-8" });
  response.end(JSON.stringify(payload));
}

async function readJson(request) {
  const chunks = [];
  for await (const chunk of request) chunks.push(chunk);
  return JSON.parse(Buffer.concat(chunks).toString("utf8"));
}

async function startFakeApi() {
  const state = {
    draft: {
      quantity: "",
      total_weight_g: "",
      total_print_time_h: "",
      spool_weight_g: "",
      spool_price_uah: "",
    },
    sku: {
      SKU: "FIG-TEST-001",
      "Назва виробу": "Тестова фігурка",
      "Час друку за од., год": "",
      "Вага виробу за од., г": "",
      "Вага котушки, г": "",
      "Ціна котушки, грн": "",
      "Фурнітура (ланцюжок/карабін), грн/шт": "",
    },
    requests: [],
  };
  const server = http.createServer(async (request, response) => {
    const url = new URL(request.url, "http://127.0.0.1");
    const method = request.method;
    const body = method === "POST" ? await readJson(request) : null;
    const action = method === "POST" ? body.action : url.searchParams.get("action");
    const token = method === "POST" ? body.token : url.searchParams.get("token");
    state.requests.push({ method, action, token, body, params: Object.fromEntries(url.searchParams) });
    if (token !== serhiyToken) return respond(response, { ok: false, code: "UNAUTHORIZED", error: "Bad test token." }, 401);

    if (method === "GET") {
      if (action === "3dp_overview") return respond(response, { ok: true, summary: { sku_count: 1, available: 3, printed: 4, defects: 1, accrued_serhiy_current_month: 12.5 } });
      if (action === "3dp_skus") return respond(response, { ok: true, rows: [{ ...state.sku, Статус: "Активний", availability: { "Наявно зараз, шт": 3 } }] });
      if (action === "3dp_fixtures") return respond(response, { ok: true, rows: [{ "Назва фурнітури": "Карабін", "Ціна, грн/шт": 4 }] });
      if (action === "3dp_print_log") return respond(response, { ok: true, rows: [{ row_number: 7, Дата: "2026-08-02", SKU: state.sku.SKU, "Надруковано, шт": 4, "Час друку факт, год": 2, "Брак, шт": 0 }] });
      if (action === "3dp_payouts") return respond(response, { ok: true, rows: [{ "Період (РРРР-ММ)": "2026-08", "Нараховано Сергію, грн": 12.5, Статус: "До виплати" }] });
      if (action === "3dp_get_range" && url.searchParams.get("sheet") === "Налаштування") {
        return respond(response, { ok: true, values: [["Глобальні константи 3D-друку", "", ""], ["Потужність принтера, кВт", 0.17, "кВт"], ["Ціна електроенергії, грн/кВт·год", 4.32, "грн/кВт·год"], ["Амортизація принтера, грн/год", 12, "грн/год"]] });
      }
      if (action === "3dp_get_range" && url.searchParams.get("sheet") === "Легенда") {
        return respond(response, { ok: true, values: [["Відомі відкриті питання"], ["Тестове питання"]] });
      }
      if (action === "3dp_get_row") return respond(response, { ok: true, row: { ...state.sku } });
      if (action === "3dp_batch_draft") return respond(response, { ok: true, action, sku: state.sku.SKU, found: Boolean(state.draft.quantity), values: { ...state.draft } });
    }

    if (method === "POST") {
      if (action === "3dp_batch_draft_save") {
        assert.deepEqual(body.expected_current, state.draft);
        state.draft = { ...body.values };
        return respond(response, { ok: true, action, sku: state.sku.SKU, row: 2, values: { ...state.draft } });
      }
      if (action === "3dp_write") {
        const byColumn = {
          G: "Час друку за од., год",
          H: "Вага виробу за од., г",
          I: "Вага котушки, г",
          J: "Ціна котушки, грн",
          N: "Фурнітура (ланцюжок/карабін), грн/шт",
        };
        assert.equal(body.expected_current, state.sku[byColumn[body.column]]);
        state.sku[byColumn[body.column]] = body.value;
        return respond(response, { ok: true, action, cell: `${body.column}2`, new_value: body.value });
      }
      if (action === "3dp_append_row") return respond(response, { ok: true, action, row: 8 });
      if (action === "3dp_print_log_update") return respond(response, { ok: true, action, row: body.row, changes: 1 });
    }

    return respond(response, { ok: false, code: "UNEXPECTED_TEST_ACTION", error: `${method} ${action}` }, 400);
  });
  server.listen(0, "127.0.0.1");
  await once(server, "listening");
  return {
    state,
    url: `http://127.0.0.1:${server.address().port}/exec`,
    async close() { server.close(); await once(server, "close"); },
  };
}

async function startLocalServer(apiUrl) {
  const child = spawn(process.execPath, ["server.mjs"], {
    cwd: appRoot,
    env: { ...process.env, BOOSTER_3DP_URL: apiUrl, BOOSTER_3DP_SERHIY_TOKEN: serhiyToken, PORT: "0" },
    stdio: ["ignore", "pipe", "pipe"],
  });
  let output = "";
  let errors = "";
  child.stdout.on("data", (chunk) => { output += chunk.toString("utf8"); });
  child.stderr.on("data", (chunk) => { errors += chunk.toString("utf8"); });
  const url = await new Promise((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error(`Local server did not start. ${output} ${errors}`)), 5000);
    const inspect = () => {
      const match = output.match(/http:\/\/127\.0\.0\.1:(\d+)/);
      if (!match) return;
      clearTimeout(timer);
      resolve(`http://127.0.0.1:${match[1]}`);
    };
    child.stdout.on("data", inspect);
    child.once("exit", (code) => {
      clearTimeout(timer);
      reject(new Error(`Local server exited early (${code}). ${output} ${errors}`));
    });
    inspect();
  });
  return {
    url,
    async close() {
      if (child.exitCode === null) {
        child.kill();
        await once(child, "exit");
      }
    },
  };
}

async function localJson(url, body) {
  const response = await fetch(url, body ? { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) } : undefined);
  const payload = await response.json();
  assert.equal(response.ok, true, payload.error);
  assert.equal(payload.ok, true, payload.error);
  return payload;
}

test("local server consumes only the Serhiy API contract and persists final batch inputs", { timeout: 15000 }, async (context) => {
  const api = await startFakeApi();
  context.after(() => api.close());
  const local = await startLocalServer(api.url);
  context.after(() => local.close());

  const page = await fetch(local.url);
  assert.equal(page.status, 200);
  assert.match(await page.text(), /Чернетка зберігає п’ять введених значень/);

  const bootstrap = await localJson(`${local.url}/api/bootstrap`);
  assert.equal(bootstrap.skus[0].SKU, "FIG-TEST-001");
  assert.equal(bootstrap.payouts[0].Статус, "До виплати");
  assert.deepEqual(bootstrap.open_questions, [["Відомі відкриті питання"], ["Тестове питання"]]);
  assert.ok(api.state.requests.some((item) => item.action === "3dp_payouts"));
  assert.ok(api.state.requests.some((item) => item.action === "3dp_get_range" && item.params.sheet === "Легенда" && item.params.range === "A32:A38"));

  const initialDraft = await localJson(`${local.url}/api/batch-draft?sku=FIG-TEST-001`);
  assert.equal(initialDraft.found, false);
  assert.deepEqual(initialDraft.values, { quantity: "", total_weight_g: "", total_print_time_h: "", spool_weight_g: "", spool_price_uah: "" });

  const saved = await localJson(`${local.url}/api/save-batch`, {
    sku: "FIG-TEST-001",
    quantity: 36,
    total_weight_g: 180,
    total_print_time_h: 18,
    spool_weight_g: 1000,
    spool_price_uah: 800,
  });
  assert.deepEqual(saved.draft, { quantity: 36, total_weight_g: 180, total_print_time_h: 18, spool_weight_g: 1000, spool_price_uah: 800 });
  assert.deepEqual(saved.cells_updated, ["G2", "H2", "I2", "J2"]);
  assert.equal(api.state.sku["Час друку за од., год"], 0.5);
  assert.equal(api.state.sku["Вага виробу за од., г"], 5);
  assert.deepEqual(api.state.draft, saved.draft);

  const appended = await localJson(`${local.url}/api/print-log`, {
    sku: "FIG-TEST-001", date: "2026-08-02", printed_quantity: 2, actual_time_hours: 1, actual_material_g: 10, defects: 0, printer: "Сергій", notes: "test",
  });
  assert.equal(appended.row, 8);
  await localJson(`${local.url}/api/defect`, { row: 7, defects: 1, expected_current: 0 });

  const actions = api.state.requests.map((item) => item.action);
  assert.ok(actions.includes("3dp_batch_draft_save"));
  assert.ok(actions.includes("3dp_append_row"));
  assert.ok(actions.includes("3dp_print_log_update"));
  assert.ok(api.state.requests.every((item) => item.token === serhiyToken));
});
