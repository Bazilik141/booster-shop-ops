// Explicit local-only browser fixture. Never packaged and never calls live APIs.
import http from "node:http";
import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const state = {
  delayMs: 2400, failWrite: false, failRefresh: false, freezeStock: false,
  counters: { saves: 0, manufactures: 0, drafts: 0, bootstrap: 0 },
  stock: { "FIG-TEST-500": 3, "BR-TEST-100": 5 }, drafts: {}, requestIds: new Set(), draftValues: null,
};
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const reply = (res, payload, status = 200) => { res.writeHead(status, { "Content-Type": "application/json", "Cache-Control": "no-store" });res.end(JSON.stringify(payload)); };
const readBody = async (req) => { const chunks = [];for await (const chunk of req) chunks.push(chunk);return JSON.parse(Buffer.concat(chunks).toString() || "{}"); };
function bootstrap() {
  const skus = Object.entries(state.stock).map(([SKU, available]) => ({
    SKU, "Назва виробу": SKU === "FIG-TEST-500" ? "Тестова рухома фігурка з дуже довгою українською назвою для перевірки форми" : "Тестовий брелок",
    "Тип": SKU.startsWith("FIG-") ? "Фігурка" : "Брелок", "API_статус_запису": "Активний", "Час друку за од., год": SKU.startsWith("FIG-") ? 1.016666667 : 0.216666667, "РРЦ фактична, грн": 100, "Ціна під викуп, грн": 30, "Посилання на модель": "",
    availability: { "Наявно зараз, шт": available },
  }));
  return {
    ok: true, overview: { sku_count: 2, available: Object.values(state.stock).reduce((a, b) => a + b, 0), accrued_serhiy_current_month: 0 }, skus,
    settings: { printer_power_kw: .11, electricity_price_uah_per_kwh: 4.32, amortization_uah_per_hour: 12, planned_defect_fraction: .08 },
    settings_values: [.11, 4.32, 12, .08], analytics: [["SKU", "Собівартість", "Час друку, год"], ["FIG-TEST-500", 24.02, 1.016666667], ["BR-TEST-100", 8.5, 0.216666667]],
    fixtures: [], print_log: [{ SKU: "FIG-TEST-500", "Час друку факт, год": 2.5 }], sales: [{ SKU: "FIG-TEST-500", "Кількість": 1 }, { SKU: "BR-TEST-100", "Кількість": 3 }], payouts: [{ "Період (РРРР-ММ)": "2026-09", "Статус": "Очікує" }], plyushky: [{ SKU: "BR-TEST-100", "Видано як бонус, шт": 1 }],
  };
}

const server = http.createServer(async (req, res) => {
  try {
    const url = new URL(req.url, "http://127.0.0.1"), body = req.method === "POST" ? await readBody(req) : null;
    if (url.pathname === "/__qa/state") return reply(res, { ...state, requestIds: [...state.requestIds] });
    if (url.pathname === "/__qa/control" && req.method === "POST") {
      for (const key of ["delayMs", "failWrite", "failRefresh", "freezeStock"]) if (Object.hasOwn(body, key)) state[key] = body[key];
      return reply(res, { ok: true });
    }
    if (url.pathname === "/api/heartbeat" || url.pathname === "/favicon.ico") { res.writeHead(204);res.end();return; }
    if (url.pathname === "/api/bootstrap") {
      state.counters.bootstrap++;
      await sleep(150);
      if (state.failRefresh) { state.failRefresh = false;return reply(res, { ok: false, code: "QA_REFRESH_FAILED", error: "Тест: оновлення екрана не вдалося." }, 502); }
      return reply(res, bootstrap());
    }
    if (url.pathname === "/api/settings-journal") return reply(res, { ok: true, rows: [] });
    if (url.pathname === "/api/batch-draft") {
      const sku = url.searchParams.get("sku");
      await sleep(sku === "FIG-TEST-500" ? 900 : 150);
      return reply(res, { ok: true, sku, found: Boolean(state.drafts[sku]), values: state.drafts[sku] || {} });
    }
    if (req.method === "POST" && url.pathname.startsWith("/api/")) {
      await sleep(state.delayMs);
      if (state.failWrite) { state.failWrite = false;return reply(res, { ok: false, code: "QA_WRITE_FAILED", error: "Тест: запис відхилено, дані форми збережені." }, 409); }
      if (url.pathname === "/api/save-batch") {
        state.counters.saves++;state.drafts[body.sku] = body;
        return reply(res, { ok: true, cells_updated: ["G2", "H2"], already_current: false });
      }
      if (url.pathname === "/api/print-log") {
        const duplicate = state.requestIds.has(body.request_id);
        state.counters.manufactures++;
        if (!duplicate && !state.freezeStock) state.stock[body.sku] += Number(body.printed_quantity);
        state.requestIds.add(body.request_id);
        return reply(res, { ok: true, row: 12, stock_added: duplicate ? 0 : Number(body.printed_quantity), already_applied: duplicate });
      }
      if (url.pathname === "/api/draft") {
        state.counters.drafts++;state.draftValues = body.values;
        return reply(res, { ok: true, sku: "DRAFT-QA-ONLY", row: 20, sku_suggestion: { prefix: "FIG", category_digits: "500" } });
      }
      return reply(res, { ok: true });
    }
    const files = {
      "/": "public/index.html", "/index.html": "public/index.html", "/app.js": "public/app.js", "/styles.css": "public/styles.css",
      "/operation-state.js": "public/operation-state.js", "/settings-controls.js": "public/settings-controls.js", "/calculator.mjs": "lib/calculator.mjs", "/print-time.js": "../shared/print-time.js",
      "/draft-categories.js": "public/draft-categories.js", "/information-tables.js": "public/information-tables.js",
    };
    const relative = files[url.pathname];
    if (!relative) return reply(res, { ok: false, error: "Not found" }, 404);
    const content = await fs.readFile(path.resolve(root, relative));
    const contentType = url.pathname.endsWith(".css") ? "text/css" : url.pathname.endsWith(".js") || url.pathname.endsWith(".mjs") ? "text/javascript" : "text/html";
    res.writeHead(200, { "Content-Type": contentType + "; charset=utf-8", "Cache-Control": "no-store" });res.end(content);
  } catch (error) { reply(res, { ok: false, error: error.message }, 500); }
});
server.listen(Number(process.env.UI_QA_PORT || 3108), "127.0.0.1", () => console.log(`Isolated UI QA: http://127.0.0.1:${server.address().port}/ (fake data only)`));
