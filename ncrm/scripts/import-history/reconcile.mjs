#!/usr/bin/env node

import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { createClient } from "@supabase/supabase-js";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const repoRoot = path.resolve(__dirname, "../../..");
const dataDir = path.join(repoRoot, "ncrm", "import", "raw", "2026-07-10");
const batch = process.argv.find((value) => value.startsWith("--batch="))?.slice(8) ?? "ncrm03_20260710";
const month = process.argv.find((value) => value.startsWith("--month="))?.slice(8) ?? "2026-06-01";

function clean(value) {
  return String(value ?? "").replace(/\u00a0/g, " ").trim();
}

function parseCsv(text) {
  const rows = [];
  let row = [];
  let field = "";
  let quoted = false;
  for (let i = 0; i < text.length; i += 1) {
    const char = text[i];
    if (quoted) {
      if (char === '"' && text[i + 1] === '"') {
        field += '"';
        i += 1;
      } else if (char === '"') quoted = false;
      else field += char;
    } else if (char === '"') quoted = true;
    else if (char === ",") {
      row.push(field);
      field = "";
    } else if (char === "\n") {
      row.push(field);
      if (row.some((value) => clean(value) !== "")) rows.push(row);
      row = [];
      field = "";
    } else if (char !== "\r") field += char;
  }
  row.push(field);
  if (row.some((value) => clean(value) !== "")) rows.push(row);
  return rows;
}

function parseMoney(value) {
  const normalized = clean(value).replace(/грн/gi, "").replace(/\s/g, "").replace(/,/g, ".").replace(/[^0-9.+-]/g, "");
  if (!normalized) return 0;
  const number = Number(normalized);
  return Number.isFinite(number) ? Math.round(number * 100) / 100 : 0;
}

function round2(value) {
  return Math.round(value * 100) / 100;
}

function sourceRows(name, headerIndex) {
  return fs.readFile(path.join(dataDir, name), "utf8").then((text) => {
    const rows = parseCsv(text.replace(/^\uFEFF/, ""));
    const header = rows[headerIndex];
    return rows.slice(headerIndex + 1).filter((row) => row.some((value) => clean(value) !== "")).map((row) => Object.fromEntries(header.map((key, index) => [key, row[index] ?? ""])));
  });
}

function parseAnchors(rows) {
  const anchors = {};
  const mappings = [
    ["Вартість залишків / ПРРО (склад)", "warehouse_prro_uah"],
    ["Управлінська вартість залишків (склад)", "warehouse_mgmt_uah"],
    ["Заморожені гроші в товарі (склад + очікується)", "stock_plus_in_transit_mgmt_uah"],
    ["Продажі за попередній місяць", "previous_full_month_revenue_uah"],
    ["Прибуток за попередній місяць", "previous_full_month_net_profit_uah"]
  ];
  for (const row of rows) for (const [label, key] of mappings) {
    const index = row.findIndex((cell) => clean(cell).startsWith(label));
    if (index >= 0) anchors[key] = parseMoney(row[index + 1]);
  }
  return anchors;
}

async function loadEnvFile() {
  try {
    const text = await fs.readFile(path.join(repoRoot, "ncrm", ".env.local"), "utf8");
    for (const line of text.split(/\r?\n/)) {
      const match = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
      if (match && !process.env[match[1]]) process.env[match[1]] = match[2].replace(/^['"]|['"]$/g, "");
    }
  } catch {}
}

function lotStatus(source) {
  return { "В дорозі": "in_transit", Замовлено: "ordered", "На складі": "in_stock", "На складі UA": "in_stock", Продано: "sold", "Частково продано": "selling" }[source];
}

function parseStockRow(row) {
  return {
    sku: clean(row.SKU),
    qty: parseMoney(row["Залишок"]),
    prro: parseMoney(row["Вартість залишку / ПРРО"]),
    mgmt: parseMoney(row["Управлінська вартість залишку"])
  };
}

async function query(client, table, select) {
  const { data, error } = await client.from(table).select(select);
  if (error) throw new Error(`${table}: ${error.message}`);
  return data ?? [];
}

function actualSaleIds(sales, paymentStatuses, orderStatuses) {
  const paymentName = new Map(paymentStatuses.map((row) => [row.id, row.name_uk]));
  const orderName = new Map(orderStatuses.map((row) => [row.id, row.name_uk]));
  return new Set(sales.filter((row) => {
    const payment = paymentName.get(row.payment_status_id) ?? "";
    const status = orderName.get(row.order_status_id) ?? "";
    if (["Скасовано", "Повернення"].includes(payment)) return false;
    if (["Скасовано", "Повернення", "Передзамовлення"].includes(status)) return false;
    return payment === "Оплачено" || ["Нове", "В обробці", "Відправлено", "Отримано"].includes(status);
  }).map((row) => row.id));
}

function warehouseTotals(lots, salesItems, writeoffItems, actualSaleIdSet) {
  const consumed = new Map();
  for (const item of salesItems) {
    if (!actualSaleIdSet.has(item.sale_id)) continue;
    consumed.set(item.product_id, (consumed.get(item.product_id) ?? 0) + Number(item.qty));
  }
  for (const item of writeoffItems) consumed.set(item.product_id, (consumed.get(item.product_id) ?? 0) + Number(item.qty));

  const currentLots = lots.filter((lot) => ["in_stock", "selling", "sold"].includes(lot.status));
  const lotsByProduct = new Map();
  for (const lot of currentLots) {
    if (!lotsByProduct.has(lot.product_id)) lotsByProduct.set(lot.product_id, []);
    lotsByProduct.get(lot.product_id).push(lot);
  }

  let prro = 0;
  let mgmt = 0;
  const byProduct = new Map();
  for (const rows of lotsByProduct.values()) {
    rows.sort((a, b) => `${a.delivery_date ?? ""}|${a.lot_code}`.localeCompare(`${b.delivery_date ?? ""}|${b.lot_code}`));
    let priorQty = 0;
    const totalConsumed = consumed.get(rows[0].product_id) ?? 0;
    let remainingQty = 0;
    let productPrro = 0;
    let productMgmt = 0;
    for (const lot of rows) {
      const qty = Number(lot.qty);
      const used = Math.max(0, Math.min(qty, totalConsumed - priorQty));
      const remaining = qty - used;
      prro += remaining * Number(lot.prro_unit ?? 0);
      mgmt += remaining * Number(lot.mgmt_unit ?? 0);
      remainingQty += remaining;
      productPrro += remaining * Number(lot.prro_unit ?? 0);
      productMgmt += remaining * Number(lot.mgmt_unit ?? 0);
      priorQty += qty;
    }
    byProduct.set(rows[0].product_id, { qty: round2(remainingQty), prro: round2(productPrro), mgmt: round2(productMgmt) });
  }
  return { prro: round2(prro), mgmt: round2(mgmt), byProduct };
}

const [dashboardRows, purchaseRows, stockRows, writeoffSourceRows] = await Promise.all([
  fs.readFile(path.join(dataDir, "dashboard_anchor.csv"), "utf8").then((text) => parseCsv(text.replace(/^\uFEFF/, ""))),
  sourceRows("purchases.csv", 1),
  sourceRows("stock_reference.csv", 1),
  sourceRows("writeoffs.csv", 1)
]);
const anchors = parseAnchors(dashboardRows);
const sourceStock = stockRows.map(parseStockRow).filter((row) => row.sku);
const signedWriteoffCorrections = writeoffSourceRows
  .map((row) => ({ id: clean(row["ID списання"]), sku: clean(row.SKU), qty: parseMoney(row["Кількість"]), reason: clean(row["Причина"]), note: clean(row["Примітка"]) }))
  .filter((row) => row.qty !== null && row.qty < 0);
const sourceAsset = purchaseRows.reduce((total, row) => {
  const status = lotStatus(clean(row["Статус"]));
  if (!["ordered", "in_transit"].includes(status) || parseMoney(row["Кількість одиниць"]) <= 0) return total;
  return { prro: total.prro + parseMoney(row["Собівартість закупки партії / ПРРО"]), mgmt: total.mgmt + parseMoney(row["Управлінська собівартість партії"]) };
}, { prro: 0, mgmt: 0 });

await loadEnvFile();
const url = process.env.NEXT_PUBLIC_SUPABASE_URL;
const key = process.env.SUPABASE_SERVICE_ROLE_KEY;
if (!url || !key) throw new Error("Reconciliation requires NEXT_PUBLIC_SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY.");
const client = createClient(url, key, { auth: { autoRefreshToken: false, persistSession: false } });

const [products, lots, sales, saleItems, writeoffs, writeoffItems, paymentStatuses, orderStatuses, pnl] = await Promise.all([
  query(client, "products", "id,sku"),
  query(client, "v_purchase_lot_costs", "id,lot_code,product_id,qty,status,delivery_date,prro_unit,mgmt_unit,prro_total,mgmt_total"),
  query(client, "sales", "id,payment_status_id,order_status_id,note"),
  query(client, "sale_items", "id,sale_id,product_id,qty"),
  query(client, "writeoffs", "id,note"),
  query(client, "writeoff_items", "id,writeoff_id,product_id,qty"),
  query(client, "payment_statuses", "id,name_uk"),
  query(client, "order_statuses", "id,name_uk"),
  client.from("v_pnl_monthly").select("month,revenue,contribution_margin,true_net_profit").eq("month", month).maybeSingle().then(({ data, error }) => { if (error) throw new Error(`v_pnl_monthly: ${error.message}`); return data; })
]);

const actual = warehouseTotals(lots, saleItems, writeoffItems, actualSaleIds(sales, paymentStatuses, orderStatuses));
const skuByProduct = new Map(products.map((row) => [row.id, row.sku]));
const dbStockBySku = new Map([...actual.byProduct.entries()].map(([productId, value]) => [skuByProduct.get(productId), value]));
const sourceStockBySku = new Map(sourceStock.map((row) => [row.sku, row]));
const stockMismatches = sourceStock
  .map((source) => {
    const db = dbStockBySku.get(source.sku) ?? { qty: 0, prro: 0, mgmt: 0 };
    return {
      sku: source.sku,
      source_qty: source.qty,
      db_qty: db.qty,
      qty_diff: round2(db.qty - source.qty),
      source_prro: source.prro,
      db_prro: db.prro,
      prro_diff: round2(db.prro - source.prro),
      source_mgmt: source.mgmt,
      db_mgmt: db.mgmt,
      mgmt_diff: round2(db.mgmt - source.mgmt)
    };
  })
  .filter((row) => row.qty_diff !== 0 || row.prro_diff !== 0 || row.mgmt_diff !== 0)
  .sort((a, b) => Math.abs(b.mgmt_diff) - Math.abs(a.mgmt_diff));
const asset = lots.filter((lot) => ["ordered", "in_transit"].includes(lot.status)).reduce((total, lot) => ({ prro: total.prro + Number(lot.prro_total ?? 0), mgmt: total.mgmt + Number(lot.mgmt_total ?? 0) }), { prro: 0, mgmt: 0 });
const inTransit = lots.filter((lot) => lot.status === "in_transit").reduce((total, lot) => ({ prro: total.prro + Number(lot.prro_total ?? 0), mgmt: total.mgmt + Number(lot.mgmt_total ?? 0) }), { prro: 0, mgmt: 0 });
const rows = [
  ["warehouse_prro_uah", anchors.warehouse_prro_uah, actual.prro],
  ["warehouse_mgmt_uah", anchors.warehouse_mgmt_uah, actual.mgmt],
  ["stock_plus_in_transit_mgmt_uah", anchors.stock_plus_in_transit_mgmt_uah, actual.mgmt + inTransit.mgmt],
  ["asset_prro_uah_ordered_plus_in_transit", sourceAsset.prro, asset.prro],
  ["asset_mgmt_uah_ordered_plus_in_transit", sourceAsset.mgmt, asset.mgmt],
  ["previous_full_month_revenue_uah", anchors.previous_full_month_revenue_uah, pnl?.revenue ?? null],
  ["previous_full_month_contribution_margin_uah", anchors.previous_full_month_net_profit_uah, pnl?.contribution_margin ?? null]
].map(([metric, expected, actualValue]) => ({ metric, expected, actual: actualValue === null ? null : Math.round(actualValue * 100) / 100, diff: expected === null || actualValue === null ? null : Math.round((actualValue - expected) * 100) / 100, tolerance: 0.01, pass: expected !== null && actualValue !== null && Math.abs(actualValue - expected) <= 0.01 }));

console.log(JSON.stringify({
  batch,
  month,
  anchors,
  source_asset_expected: { prro: round2(sourceAsset.prro), mgmt: round2(sourceAsset.mgmt) },
  source_stock_totals: {
    qty: round2(sourceStock.reduce((total, row) => total + (row.qty ?? 0), 0)),
    prro: round2(sourceStock.reduce((total, row) => total + (row.prro ?? 0), 0)),
    mgmt: round2(sourceStock.reduce((total, row) => total + (row.mgmt ?? 0), 0))
  },
  signed_writeoff_corrections: {
    count: signedWriteoffCorrections.length,
    qty: round2(signedWriteoffCorrections.reduce((total, row) => total + row.qty, 0)),
    rows: signedWriteoffCorrections
  },
  source_stock_mismatches: stockMismatches,
  db_counts: { lots: lots.length, sales: sales.length, sale_items: saleItems.length, writeoffs: writeoffs.length, writeoff_items: writeoffItems.length },
  pnl_row: pnl,
  note: "The live CRM's net-profit anchor is compared to contribution_margin because expenses/refunds are explicitly out of scope for NCRM-03; true_net_profit remains a separate readout.",
  diff_table: rows,
  all_pass: rows.every((row) => row.pass)
}, null, 2));
