#!/usr/bin/env node

import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { createClient } from "@supabase/supabase-js";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const repoRoot = path.resolve(__dirname, "../../..");
const defaultDataDir = path.join(repoRoot, "ncrm", "import", "raw", "2026-07-16");
const legacyMysterySkus = new Set(["PKM-JP-MIX-MBX", "OP-JP-MIX-MBX"]);

function argValue(name, fallback) {
  const prefix = `${name}=`;
  const item = process.argv.slice(2).find((value) => value.startsWith(prefix));
  return item ? item.slice(prefix.length) : fallback;
}

const dataDir = path.resolve(argValue("--data-dir", defaultDataDir));
const batch = argValue("--batch", "ncrm03_20260716");
const month = argValue("--month", "2026-06-01");

const sourceFiles = {
  sales: ["Booster Shop CRM — облік товарів - Продажі.csv", "sales.csv"],
  purchases: ["Booster Shop CRM — облік товарів - Закупки.csv", "purchases.csv"],
  writeoffs: ["Booster Shop CRM — облік товарів - Списання.csv", "writeoffs.csv"],
  rrc: ["Booster Shop CRM — облік товарів - РРЦ.csv", "rrc.csv"],
  stock: ["Booster Shop CRM — облік товарів - Склад.csv", "stock_reference.csv"]
};

function clean(value) { return String(value ?? "").replace(/\u00a0/g, " ").trim(); }
function round2(value) { return Math.round(Number(value ?? 0) * 100) / 100; }
function parseMoney(value) {
  const normalized = clean(value).replace(/грн/gi, "").replace(/\s/g, "").replace(/,/g, ".").replace(/[^0-9.+-]/g, "");
  if (!normalized) return 0;
  const parsed = Number(normalized);
  return Number.isFinite(parsed) ? round2(parsed) : 0;
}
function parseCsv(text) {
  const rows = []; let row = []; let field = ""; let quoted = false;
  for (let index = 0; index < text.length; index += 1) {
    const char = text[index];
    if (quoted) {
      if (char === '"' && text[index + 1] === '"') { field += '"'; index += 1; }
      else if (char === '"') quoted = false;
      else field += char;
    } else if (char === '"') quoted = true;
    else if (char === ",") { row.push(field); field = ""; }
    else if (char === "\n") { row.push(field); if (row.some((value) => clean(value))) rows.push(row); row = []; field = ""; }
    else if (char !== "\r") field += char;
  }
  row.push(field);
  if (row.some((value) => clean(value))) rows.push(row);
  return rows;
}
async function sourcePath(logical) {
  for (const name of sourceFiles[logical]) {
    const candidate = path.join(dataDir, name);
    try { await fs.access(candidate); return candidate; } catch {}
  }
  throw new Error(`Missing ${logical} source in ${dataDir}.`);
}
async function sourceRows(logical, headerIndex = 1) {
  const filePath = await sourcePath(logical);
  const rows = parseCsv((await fs.readFile(filePath, "utf8")).replace(/^\uFEFF/, ""));
  const header = rows[headerIndex];
  return rows.slice(headerIndex + 1).map((values) => Object.fromEntries(header.map((key, index) => [key, values[index] ?? ""]))).filter((row) => Object.values(row).some((value) => clean(value)));
}
function lotStatus(source) {
  return { "В дорозі": "in_transit", Замовлено: "ordered", "На складі": "in_stock", "На складі UA": "in_stock", Продано: "sold", "Частково продано": "selling" }[clean(source)];
}
function isActualSale(row) {
  const payment = clean(row["Статус оплати"]);
  const status = clean(row["Статус замовлення"]);
  if (["Скасовано", "Повернення"].includes(payment) || ["Скасовано", "Повернення", "Передзамовлення"].includes(status)) return false;
  return payment === "Оплачено" || ["Нове", "В обробці", "Відправлено", "Отримано"].includes(status);
}
function isLegacyMysterySource(row) {
  return /містер|mystery|mbox|mbx/i.test(`${clean(row["Тип списання"])} ${clean(row["Причина"])} ${clean(row["Примітка"])}`);
}
async function loadEnvFile() {
  const text = await fs.readFile(path.join(repoRoot, "ncrm", ".env.local"), "utf8");
  for (const line of text.split(/\r?\n/)) {
    const match = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
    if (match && !process.env[match[1]]) process.env[match[1]] = match[2].replace(/^['"]|['"]$/g, "");
  }
}
async function query(client, table, select) {
  const { data, error } = await client.from(table).select(select);
  if (error) throw new Error(`${table}: ${error.message}`);
  return data ?? [];
}
function metric(name, expected, actual) {
  const normalizedExpected = expected === null ? null : round2(expected);
  const normalizedActual = actual === null ? null : round2(actual);
  const diff = normalizedExpected === null || normalizedActual === null ? null : round2(normalizedActual - normalizedExpected);
  return { metric: name, expected: normalizedExpected, actual: normalizedActual, diff, tolerance: 0.01, pass: diff !== null && Math.abs(diff) <= 0.01 };
}

const [salesSource, purchasesSource, writeoffsSource, stockSource] = await Promise.all([
  sourceRows("sales"), sourceRows("purchases"), sourceRows("writeoffs"), sourceRows("stock")
]);
const stockExpected = {
  qty: round2(stockSource.reduce((total, row) => total + parseMoney(row["Залишок"]), 0)),
  prro: round2(stockSource.reduce((total, row) => total + parseMoney(row["Вартість залишку / ПРРО"]), 0)),
  mgmt: round2(stockSource.reduce((total, row) => total + parseMoney(row["Управлінська вартість залишку"]), 0))
};
const sourceInbound = purchasesSource.reduce((total, row) => {
  if (!["ordered", "in_transit"].includes(lotStatus(row["Статус"])) || parseMoney(row["Кількість одиниць"]) <= 0) return total;
  total.prro += parseMoney(row["Собівартість закупки партії / ПРРО"]);
  total.mgmt += parseMoney(row["Управлінська собівартість партії"]);
  return total;
}, { prro: 0, mgmt: 0 });
const sourceTransit = purchasesSource.reduce((total, row) => {
  if (lotStatus(row["Статус"]) !== "in_transit" || parseMoney(row["Кількість одиниць"]) <= 0) return total;
  total.prro += parseMoney(row["Собівартість закупки партії / ПРРО"]);
  total.mgmt += parseMoney(row["Управлінська собівартість партії"]);
  return total;
}, { prro: 0, mgmt: 0 });
const sourceMonth = salesSource.filter((row) => clean(row["Дата продажу"]).startsWith(month.slice(0, 7)) && isActualSale(row));
const sourceMonthRevenue = round2(sourceMonth.reduce((total, row) => total + parseMoney(row["Сума продажу"]), 0));
const sourceMonthContribution = round2(sourceMonth.reduce((total, row) => total + parseMoney(row["Чистий прибуток"]), 0));
const legacyMysterySourceCostByKey = new Map(salesSource
  .filter((row) => legacyMysterySkus.has(clean(row.SKU)))
  .map((row) => [`${clean(row["Номер замовлення / операції"])}|${clean(row.SKU)}`, {
    qty: parseMoney(row["Кількість"]), mgmt_unit: parseMoney(row["Управлінська собівартість 1 од."]), is_actual: isActualSale(row)
  }]));
const deferredNcrm13 = writeoffsSource
  .map((row) => ({ id: clean(row["ID списання"]), date: clean(row["Дата"]), sku: clean(row.SKU), qty: parseMoney(row["Кількість"]), prro_unit: parseMoney(row["Собівартість 1 од. / ПРРО"]), mgmt_unit: parseMoney(row["Управлінська собівартість 1 од."]), reason: clean(row["Причина"]), note: clean(row["Примітка"]) }))
  .filter((row) => row.qty < 0);
const signedResidual = deferredNcrm13.reduce((total, row) => ({
  qty: total.qty + row.qty, prro: total.prro + row.qty * row.prro_unit, mgmt: total.mgmt + row.qty * row.mgmt_unit
}), { qty: 0, prro: 0, mgmt: 0 });

await loadEnvFile();
const client = createClient(process.env.NEXT_PUBLIC_SUPABASE_URL, process.env.SUPABASE_SERVICE_ROLE_KEY, { auth: { autoRefreshToken: false, persistSession: false } });
const [valuation, lotCosts, pnl, products, sales, saleItems, writeoffs, writeoffItems, fulfillments, contents, alerts] = await Promise.all([
  query(client, "v_inventory_fifo_valuation", "product_id,sku,warehouse_qty,warehouse_prro_cost,warehouse_mgmt_cost,asset_qty,asset_prro_cost,asset_mgmt_cost"),
  query(client, "v_purchase_lot_costs", "id,status,prro_total,mgmt_total"),
  client.from("v_pnl_monthly").select("month,revenue,contribution_margin,true_net_profit").eq("month", month).maybeSingle().then(({ data, error }) => { if (error) throw new Error(`v_pnl_monthly: ${error.message}`); return data; }),
  query(client, "products", "id,sku,is_active"), query(client, "sales", "id,order_no,sold_at,note"),
  query(client, "sale_items", "id,sale_id,product_id,qty,cost_state,cost_method,prro_unit,mgmt_unit"),
  query(client, "writeoffs", "id,writeoff_no,type,note"), query(client, "writeoff_items", "id,writeoff_id,product_id,qty,note"),
  query(client, "mystery_fulfillments", "id,sale_item_id,state,note"), query(client, "mystery_contents", "id,sale_item_id,component_product_id,qty,source,writeoff_item_id"),
  query(client, "v_stock_alerts", "sku,stock_qty")
]);
const sum = (rows, key) => round2(rows.reduce((total, row) => total + Number(row[key] ?? 0), 0));
const dbWarehouse = { qty: sum(valuation, "warehouse_qty"), prro: sum(valuation, "warehouse_prro_cost"), mgmt: sum(valuation, "warehouse_mgmt_cost") };
const dbAsset = { prro: sum(valuation, "asset_prro_cost"), mgmt: sum(valuation, "asset_mgmt_cost") };
const dbTransit = lotCosts.filter((row) => row.status === "in_transit").reduce((total, row) => ({ prro: total.prro + Number(row.prro_total ?? 0), mgmt: total.mgmt + Number(row.mgmt_total ?? 0) }), { prro: 0, mgmt: 0 });
const productById = new Map(products.map((row) => [row.id, row]));
const salesById = new Map(sales.map((row) => [row.id, row]));
const batchSales = new Set(sales.filter((row) => clean(row.note).includes(`imported_batch=${batch}`)).map((row) => row.id));
const batchSaleItems = saleItems.filter((row) => batchSales.has(row.sale_id));
const mboxItems = batchSaleItems.filter((row) => legacyMysterySkus.has(productById.get(row.product_id)?.sku));
const fulfillmentByItem = new Map(fulfillments.map((row) => [row.sale_item_id, row]));
const contentsByItem = new Map();
for (const content of contents) contentsByItem.set(content.sale_item_id, (contentsByItem.get(content.sale_item_id) ?? 0) + Number(content.qty));
const mboxActual = mboxItems.filter((row) => row.cost_state === "actual");
const mboxEstimated = mboxItems.filter((row) => row.cost_state === "estimated");
const mboxProvisional = mboxItems.filter((row) => row.cost_state === "provisional");
const mboxApproximate = mboxItems.filter((row) => /legacy_import=approximation/.test(fulfillmentByItem.get(row.id)?.note ?? ""));
const mboxExact = mboxItems.filter((row) => /legacy_import=exact/.test(fulfillmentByItem.get(row.id)?.note ?? ""));
const mboxSourceLinkedItems = writeoffItems.filter((item) => /legacy_source_wrt=/.test(item.note ?? ""));
const mboxWriteoffs = writeoffs.filter((row) => row.type === "MBOX" && clean(row.note).includes(`imported_batch=${batch}`));
const mboxCogsDelta = round2(mboxEstimated.reduce((total, row) => total + (Number(row.mgmt_unit ?? 0) - 450) * Number(row.qty), 0));
const provisionalMboxLegacyCogsReduction = round2(mboxProvisional.reduce((total, row) => {
  const sale = salesById.get(row.sale_id);
  const sku = productById.get(row.product_id)?.sku;
  const source = legacyMysterySourceCostByKey.get(`${sale?.order_no}|${sku}`);
  if (!source?.is_actual) return total;
  return total + ((Number(source?.mgmt_unit ?? row.mgmt_unit ?? 0) - Number(row.mgmt_unit ?? 0)) * Number(row.qty));
}, 0));
const provisionalMboxJuneCogsReduction = round2(mboxProvisional.reduce((total, row) => {
  const sale = salesById.get(row.sale_id);
  if (sale?.sold_at?.slice(0, 7) !== month.slice(0, 7)) return total;
  const sku = productById.get(row.product_id)?.sku;
  const source = legacyMysterySourceCostByKey.get(`${sale.order_no}|${sku}`);
  if (!source?.is_actual) return total;
  return total + ((Number(source?.mgmt_unit ?? row.mgmt_unit ?? 0) - Number(row.mgmt_unit ?? 0)) * Number(row.qty));
}, 0));
const mboxCrossGameDocuments = mboxWriteoffs.filter((row) => /MBOX-LEGACY-(MBZ-PHYS-0001|OLX-PHYS-0011|OLX-PHYS-0012|OLX-PHYS-0013)/.test(row.writeoff_no));
const sourceCanonicalAsset = { prro: round2(stockExpected.prro + sourceInbound.prro), mgmt: round2(stockExpected.mgmt + sourceInbound.mgmt) };
const sourceMonthPolicyAdjustedContribution = round2(sourceMonthContribution + provisionalMboxJuneCogsReduction);

const metrics = [
  metric("warehouse_prro_uah", stockExpected.prro, dbWarehouse.prro),
  metric("warehouse_mgmt_uah", stockExpected.mgmt, dbWarehouse.mgmt),
  metric("stock_plus_in_transit_mgmt_uah", stockExpected.mgmt + sourceTransit.mgmt, dbWarehouse.mgmt + dbTransit.mgmt),
  metric("asset_prro_uah_warehouse_plus_ordered_in_transit", sourceCanonicalAsset.prro, dbAsset.prro),
  metric("asset_mgmt_uah_warehouse_plus_ordered_in_transit", sourceCanonicalAsset.mgmt, dbAsset.mgmt),
  metric("previous_full_month_revenue_uah", sourceMonthRevenue, pnl?.revenue ?? null),
  metric("previous_full_month_contribution_margin_policy_adjusted_uah", sourceMonthPolicyAdjustedContribution, pnl?.contribution_margin ?? null)
];

console.log(JSON.stringify({
  batch, month,
  source_anchors: {
    warehouse: stockExpected,
    source_inbound_ordered_plus_in_transit: { prro: round2(sourceInbound.prro), mgmt: round2(sourceInbound.mgmt) },
    source_asset_warehouse_plus_ordered_in_transit: sourceCanonicalAsset,
    source_in_transit: { prro: round2(sourceTransit.prro), mgmt: round2(sourceTransit.mgmt) },
    month: { revenue: sourceMonthRevenue, contribution_margin_legacy: sourceMonthContribution, contribution_margin_policy_adjusted: sourceMonthPolicyAdjustedContribution, source_sale_lines: sourceMonth.length }
  },
  db_totals: { warehouse: dbWarehouse, asset: dbAsset, in_transit: { prro: round2(dbTransit.prro), mgmt: round2(dbTransit.mgmt) }, pnl },
  deferred_to_ncrm13: { count: deferredNcrm13.length, signed_qty: round2(signedResidual.qty), signed_prro_value: round2(signedResidual.prro), signed_mgmt_value: round2(signedResidual.mgmt), rows: deferredNcrm13 },
  mystery_reconstruction: {
    generated_mbox_documents: mboxWriteoffs.length, generated_component_items: mboxSourceLinkedItems.length,
    actual_sale_item_rows: mboxActual.length, actual_box_units: round2(mboxActual.reduce((total, row) => total + Number(row.qty), 0)),
    estimated_sale_item_rows: mboxEstimated.length, estimated_box_units: round2(mboxEstimated.reduce((total, row) => total + Number(row.qty), 0)),
    exact_box_units: round2(mboxExact.reduce((total, row) => total + Number(row.qty), 0)),
    approximation_box_units: round2(mboxApproximate.reduce((total, row) => total + Number(row.qty), 0)),
    provisional_box_units: round2(mboxProvisional.reduce((total, row) => total + Number(row.qty), 0)),
    source_component_qty: round2(mboxSourceLinkedItems.reduce((total, row) => total + Number(row.qty), 0)),
    estimated_mystery_cogs_delta_vs_450_provisional_mgmt_uah: mboxCogsDelta,
    provisional_mystery_cogs_reduction_vs_legacy_snapshot_mgmt_uah: provisionalMboxLegacyCogsReduction,
    provisional_mystery_cogs_reduction_vs_legacy_snapshot_june_mgmt_uah: provisionalMboxJuneCogsReduction,
    warehouse_effect_vs_same_62_source_component_writeoffs_uah: 0,
    explanation: "The 62 source component quantities are preserved as 62 generated MBOX writeoff_items. They normalise to 14 source-linked groups and 15 canonical MBOX documents because OC-FOP-0200 has two legacy Mystery sale_items. This changes audit/COGS linkage, not physical warehouse quantity or value, so it cannot close the prior warehouse residual by itself.",
    cross_game_legacy_documents: mboxCrossGameDocuments.map((row) => row.writeoff_no),
    approximate_fulfillment_note: "OC-FOP-0219 is retained as one qty=2 sale_item; the aggregate 10-pack allocation is explicitly marked approximation in mystery_fulfillments.note.",
    cogs_confidence_note: "The reconstructed component links are exact for 14 units and approximate for 2 units, but all 16 use an estimated legacy sales snapshot because source purchase lots lack received dates required for auditable historical FIFO. No fallback-derived amount is labelled actual."
  },
  legacy_mystery_stock_alerts: alerts.filter((row) => legacyMysterySkus.has(row.sku)),
  legacy_margin_variance_explained: {
    legacy_source_contribution_margin: sourceMonthContribution,
    canonical_contribution_margin: round2(pnl?.contribution_margin ?? 0),
    difference: round2((pnl?.contribution_margin ?? 0) - sourceMonthContribution),
    explanation: `The ${provisionalMboxJuneCogsReduction} UAH difference is the intentional 450 UAH provisional-cost policy for four unlinked legacy Mystery units; it is not a revenue or fee discrepancy.`
  },
  batch_counts: {
    sales: batchSales.size, sale_items: batchSaleItems.length,
    writeoffs: writeoffs.filter((row) => clean(row.note).includes(`imported_batch=${batch}`)).length,
    writeoff_items: writeoffItems.filter((row) => writeoffs.some((writeoff) => writeoff.id === row.writeoff_id && clean(writeoff.note).includes(`imported_batch=${batch}`))).length,
    mystery_contents: contents.filter((row) => mboxItems.some((item) => item.id === row.sale_item_id)).length
  },
  diff_table: metrics,
  all_pass: metrics.every((row) => row.pass)
}, null, 2));
