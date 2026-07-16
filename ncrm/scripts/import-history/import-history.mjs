#!/usr/bin/env node

import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { createClient } from "@supabase/supabase-js";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const repoRoot = path.resolve(__dirname, "../../..");
const defaultDataDir = path.join(repoRoot, "ncrm", "import", "raw", "2026-07-10");
const allowedCostMethods = new Set(["FIFO", "FIFO+fallback", "Fallback", "Відкладено"]);

function argValue(name, fallback) {
  const prefix = `${name}=`;
  const item = process.argv.slice(2).find((value) => value.startsWith(prefix));
  return item ? item.slice(prefix.length) : fallback;
}

const applyMode = process.argv.includes("--apply");
const acknowledgeAssumptions = process.argv.includes("--acknowledge-legacy-assumptions");
const cutoverDate = argValue("--cutover-date", "2026-07-10");
const batch = argValue("--batch", "ncrm03_20260710");
const dataDir = path.resolve(argValue("--data-dir", defaultDataDir));
const snapshotTimestamp = `${cutoverDate}T00:00:00.000Z`;

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
    const next = text[i + 1];
    if (quoted) {
      if (char === '"' && next === '"') {
        field += '"';
        i += 1;
      } else if (char === '"') {
        quoted = false;
      } else {
        field += char;
      }
    } else if (char === '"') {
      quoted = true;
    } else if (char === ",") {
      row.push(field);
      field = "";
    } else if (char === "\n") {
      row.push(field);
      if (row.some((value) => clean(value) !== "")) rows.push(row);
      row = [];
      field = "";
    } else if (char !== "\r") {
      field += char;
    }
  }

  row.push(field);
  if (row.some((value) => clean(value) !== "")) rows.push(row);
  return rows;
}

async function readCsv(name, headerRowIndex) {
  const filePath = path.join(dataDir, name);
  const text = await fs.readFile(filePath, "utf8");
  const rows = parseCsv(text.replace(/^\uFEFF/, ""));
  const sourceHeader = rows[headerRowIndex];
  if (!sourceHeader) throw new Error(`Missing CSV header row ${headerRowIndex + 1}: ${name}`);

  const seen = new Map();
  const header = sourceHeader.map((value, index) => {
    const base = clean(value) || `__blank_${index}`;
    const count = (seen.get(base) ?? 0) + 1;
    seen.set(base, count);
    return count === 1 ? base : `${base}__${count}`;
  });

  const records = rows.slice(headerRowIndex + 1).map((values) => {
    const padded = [...values];
    while (padded.length < header.length) padded.push("");
    return Object.fromEntries(header.map((key, index) => [key, padded[index] ?? ""]));
  });

  return { name, filePath, header, records };
}

function parseMoney(value) {
  const normalized = clean(value)
    .replace(/грн/gi, "")
    .replace(/\s/g, "")
    .replace(/,/g, ".")
    .replace(/[^0-9.+-]/g, "");
  if (!normalized) return null;
  const number = Number(normalized);
  return Number.isFinite(number) ? Math.round(number * 100) / 100 : null;
}

function parseDate(value) {
  const normalized = clean(value);
  return /^\d{4}-\d{2}-\d{2}$/.test(normalized) ? normalized : null;
}

function parseQty(value) {
  const number = parseMoney(value);
  return number === null ? null : Math.round(number * 1000) / 1000;
}

function sum(values) {
  return Math.round(values.reduce((total, value) => total + (parseMoney(value) ?? 0), 0) * 100) / 100;
}

function noteWithBatch(...parts) {
  return [`imported_batch=${batch}`, ...parts.map(clean).filter(Boolean)].join("; ");
}

function codeFor(value) {
  const aliases = {
    "Pokémon": "pokemon",
    "One Piece": "one_piece",
    "Yu-Gi-Oh!": "yu_gi_oh",
    MTG: "mtg",
    Generic: "generic",
    "Games 7 Days": "games_7_days",
    "Mystery Box": "mystery_box",
    Accessory: "accessory",
    Blister: "blister",
    Booster: "booster",
    "Booster Box": "booster_box",
    "Booster Bundle": "booster_bundle",
    "Collection Set": "collection_set",
    JP: "jp",
    KR: "kr",
    EN: "en"
  };
  if (aliases[value]) return aliases[value];
  return clean(value)
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/^_|_$/g, "");
}

function inferGame(product) {
  const brand = clean(product["Бренд"]);
  return ["Pokémon", "One Piece", "Yu-Gi-Oh!", "MTG"].includes(brand) ? codeFor(brand) : null;
}

function legacySku(note) {
  const match = clean(note).match(/Старий артикул:\s*([^;]+)/i);
  return match ? clean(match[1]) : null;
}

function normalizeCostMethod(value) {
  const source = clean(value).replace(/\s*\+\s*авторозхідники/gi, "").replace(/\s+/g, " ").trim();
  if (!source) return "Відкладено";
  if (/FIFO\s*\+\s*fallback/i.test(source)) return "FIFO+fallback";
  if (/^FIFO$/i.test(source)) return "FIFO";
  if (/^Fallback$/i.test(source)) return "Fallback";
  if (allowedCostMethods.has(source)) return source;
  return "Відкладено";
}

function mapWriteoffType(sourceType, reason) {
  if (sourceType === "Промо" || sourceType === "Подарунок") return "Маркетинг";
  if (sourceType === "Інше" && /містер/i.test(`${sourceType} ${reason}`)) return "MBOX";
  if (sourceType === "Власне відкриття") return "Власне відкриття";
  return "Інше";
}

function mapLotStatus(sourceStatus) {
  const mapping = {
    "В дорозі": "in_transit",
    Замовлено: "ordered",
    "На складі": "in_stock",
    "На складі UA": "in_stock",
    Продано: "sold",
    "Частково продано": "selling"
  };
  return mapping[sourceStatus] ?? "cancelled";
}

function mapChannel(source) {
  return ["OpenCart", "Telegram", "OLX", "Monobazar"].includes(source) ? source : "Інше";
}

function sourceRegion(row) {
  const supplier = clean(row["Постачальник"]);
  const order = clean(row["ZenMarket Order №"]);
  const note = clean(row["Примітка"]);
  if (supplier === "Temu" || /USD|Temu/i.test(note)) return { code: "usa", warning: "Temu/ USD source mapped to existing USD region usa" };
  if (supplier === "other") return { code: "ukraine", warning: "source supplier=other mapped to ukraine region" };
  if (supplier === "zenmarket_jp" || /yskh|zenmarket/i.test(`${order} ${note}`)) return { code: "japan" };
  return { code: "ukraine", warning: "blank/unknown supplier mapped to ukraine region" };
}

function extractOrderFromNote(note) {
  const match = clean(note).match(/(?:Продаж|замовлення)\s+([A-Z0-9-]+)/i);
  return match ? match[1] : null;
}

function parseDashboardAnchors(rows) {
  const anchors = {};
  for (const row of rows) {
    const pairs = [
      ["Вартість залишків / ПРРО (склад)", "warehouse_prro_uah"],
      ["Управлінська вартість залишків (склад)", "warehouse_mgmt_uah"],
      ["Заморожені гроші в товарі (склад + очікується)", "stock_plus_in_transit_mgmt_uah"],
      ["Продажі за попередній місяць", "previous_full_month_revenue_uah"],
      ["Прибуток за попередній місяць", "previous_full_month_net_profit_uah"]
    ];
    for (const [label, key] of pairs) {
      const index = row.findIndex((cell) => clean(cell).startsWith(label));
      if (index >= 0 && row[index + 1] !== undefined) anchors[key] = parseMoney(row[index + 1]);
    }
  }
  const periodRow = rows.find((row) => clean(row[0]) === "Період");
  if (periodRow?.[1]) anchors.dashboard_period = clean(periodRow[1]);
  return anchors;
}

function warning(report, message) {
  report.warnings.push(message);
}

async function buildModel() {
  const [salesCsv, purchasesCsv, rrcCsv, productsCsv, writeoffsCsv, consumablesCsv, dashboardCsv] = await Promise.all([
    readCsv("sales.csv", 1),
    readCsv("purchases.csv", 1),
    readCsv("rrc.csv", 1),
    readCsv("products.csv", 1),
    readCsv("writeoffs.csv", 1),
    // parseCsv removes the intentionally blank second row in this export;
    // after normalization the real header is row index 1.
    readCsv("consumables.csv", 1),
    readCsv("dashboard_anchor.csv", 1)
  ]);

  const report = { warnings: [], skipped: [], counts: {}, anchors: parseDashboardAnchors(parseCsv(await fs.readFile(dashboardCsv.filePath, "utf8"))) };
  const legacyConfig = [
    { key: "credit_rate_monthly", value_num: 0.03, unit: "ratio", description: noteWithBatch("legacy CRM credit rate"), effective_from: "2026-04-01", is_active: true },
    { key: "credit_months", value_num: 2, unit: "months", description: noteWithBatch("legacy CRM credit months"), effective_from: "2026-04-01", is_active: true },
    { key: "expected_income_haircut", value_num: 1.05, unit: "multiplier", description: noteWithBatch("legacy CRM expected-income haircut"), effective_from: "2026-04-01", is_active: true },
    { key: "acquiring_pct", value_num: 0.015, unit: "ratio", description: noteWithBatch("legacy CRM acquiring rate"), effective_from: "2026-04-01", is_active: true },
    { key: "fop_control_pct", value_num: 0.005, unit: "ratio", description: noteWithBatch("legacy CRM FOP control rate"), effective_from: "2026-04-01", is_active: true },
    { key: "fop_control_min", value_num: 0, unit: "UAH", description: noteWithBatch("legacy CRM FOP control minimum"), effective_from: "2026-04-01", is_active: true },
    { key: "payback_fiz_pct", value_num: 0.02, unit: "ratio", description: noteWithBatch("legacy CRM personal COD rate"), effective_from: "2026-04-01", is_active: true },
    { key: "payback_fiz_fix", value_num: 20, unit: "UAH", description: noteWithBatch("legacy CRM personal COD fixed fee"), effective_from: "2026-04-01", is_active: true },
    { key: "cost_start_date", value_date: "2026-04-01", unit: "date", description: noteWithBatch("legacy CRM warehouse cost start"), effective_from: "2026-04-01", is_active: true },
    { key: "nbu_rate_buffer_pct", value_num: 0.01, unit: "ratio", description: noteWithBatch("legacy CRM NBU rate buffer"), effective_from: "2026-04-01", is_active: true }
  ];
  const productRows = productsCsv.records;
  const productBySku = new Map(productRows.map((row) => [clean(row.SKU), row]));
  const rrcBySku = new Map(rrcCsv.records.map((row) => [clean(row.SKU), row]));

  const lookupRows = { product_brands: [], product_categories: [], games: [], product_languages: [] };
  const lookupSeen = Object.fromEntries(Object.keys(lookupRows).map((key) => [key, new Set()]));
  const products = [];
  for (const row of productRows) {
    const sku = clean(row.SKU);
    const brand = clean(row["Бренд"]);
    const category = clean(row["Формат"]);
    const language = clean(row["Мова"]);
    const game = inferGame(row);
    if (brand && !lookupSeen.product_brands.has(codeFor(brand))) {
      lookupSeen.product_brands.add(codeFor(brand));
      lookupRows.product_brands.push({ code: codeFor(brand), name: brand, is_active: true });
    }
    if (category && !lookupSeen.product_categories.has(codeFor(category))) {
      lookupSeen.product_categories.add(codeFor(category));
      lookupRows.product_categories.push({ code: codeFor(category), name: category, is_active: true });
    }
    if (game && !lookupSeen.games.has(game)) {
      lookupSeen.games.add(game);
      lookupRows.games.push({ code: game, name: brand, is_active: true });
    }
    if (language && !lookupSeen.product_languages.has(codeFor(language))) {
      lookupSeen.product_languages.add(codeFor(language));
      lookupRows.product_languages.push({ code: codeFor(language), name: language, is_active: true });
    }
    products.push({
      sku,
      name: clean(row["Коротка назва"]),
      full_name: clean(row["Повна назва для сайту"]),
      brand_code: brand ? codeFor(brand) : null,
      category_code: category ? codeFor(category) : null,
      game_code: game,
      language_code: language ? codeFor(language) : null,
      gtin: null,
      legacy_sku: legacySku(row["Примітка"]),
      is_active: clean(row["Активний товар"]) !== "Ні"
    });
  }

  const productPrices = [];
  for (const row of rrcCsv.records) {
    const sku = clean(row.SKU);
    const rrc = parseMoney(row["РРЦ, грн"]);
    const effectiveFrom = parseDate(row["Дата оновлення"]) ?? cutoverDate;
    if (rrc === null) {
      report.skipped.push({ table: "product_prices", key: sku, reason: "blank_rrc" });
      continue;
    }
    productPrices.push({
      sku,
      rrc,
      source: "legacy РРЦ import",
      note: noteWithBatch(row["Примітка"], "imported_legacy_no_rate_history"),
      effective_from: effectiveFrom
    });
  }

  const purchases = [];
  for (const row of purchasesCsv.records) {
    const lotCode = clean(row["ID партії"]);
    const qty = parseQty(row["Кількість одиниць"]);
    if (!qty || qty <= 0) {
      report.skipped.push({ table: "purchase_lots", key: lotCode, reason: "non_positive_qty" });
      continue;
    }
    const region = sourceRegion(row);
    if (region.warning) warning(report, `${lotCode}: ${region.warning}`);
    const deliveryDate = parseDate(row["Дата доставки в Україну"]);
    const noteParts = [row["Примітка"], "imported_legacy_no_rate_history"];
    if (!deliveryDate) {
      noteParts.push("legacy_ordered_at_missing; ordered_at uses snapshot date");
      warning(report, `${lotCode}: missing source date, ordered_at=${cutoverDate}`);
    }
    const goods = parseMoney(row["Вартість лоту, грн"]) ?? 0;
    const forwarding = parseMoney(row["Доставка / комісії по Японії, грн"]) ?? 0;
    const local = parseMoney(row["Доставка UA, грн"]) ?? 0;
    const prroUnit = parseMoney(row["Собівартість 1 од. / ПРРО"]);
    const mgmtUnit = parseMoney(row["Управлінська собівартість 1 од."]);
    purchases.push({
      lotCode,
      sourceSku: clean(row.SKU),
      purchase: {
        region_code: region.code,
        supplier_name: clean(row["Постачальник"]) || null,
        order_ref: clean(row["ZenMarket Order №"]) || null,
        order_url: clean(row["ZenMarket URL"]) || `legacy://ncrm03/${lotCode}`,
        ordered_at: deliveryDate ?? cutoverDate,
        goods_total_amount: goods,
        goods_total_currency: "UAH",
        goods_total_rate: 1,
        goods_total_uah: goods,
        forwarding_fee_amount: forwarding,
        forwarding_fee_currency: "UAH",
        forwarding_fee_rate: 1,
        forwarding_fee_uah: forwarding,
        intl_shipping_amount: 0,
        intl_shipping_currency: "UAH",
        intl_shipping_rate: 1,
        intl_shipping_uah: 0,
        local_delivery_amount: local,
        local_delivery_currency: "UAH",
        local_delivery_rate: 1,
        local_delivery_uah: local,
        note: noteWithBatch(...noteParts)
      },
      lot: {
        lot_code: lotCode,
        source_sku: clean(row.SKU),
        qty,
        goods_cost_uah: goods,
        forwarding_fee_uah: forwarding,
        intl_shipping_uah: 0,
        local_delivery_uah: local,
        manual_unit_cost: prroUnit,
        delivery_date: deliveryDate,
        track_number: clean(row["Трек-номер"]) || null,
        status: mapLotStatus(clean(row["Статус"])),
        legacy_status: clean(row["Статус"]),
        note: noteWithBatch(row["Примітка"], `source_mgmt_unit=${mgmtUnit ?? "NULL"}`, "imported_legacy_no_rate_history")
      }
    });
  }

  const salesByOrder = new Map();
  for (const row of salesCsv.records) {
    const orderNo = clean(row["Номер замовлення / операції"]);
    const soldAt = parseDate(row["Дата продажу"]);
    const qty = parseQty(row["Кількість"]);
    if (!soldAt || soldAt < "2026-04-01") {
      report.skipped.push({ table: "sales", key: orderNo, reason: "before_cost_start_date" });
      continue;
    }
    if (!qty || qty <= 0) {
      report.skipped.push({ table: "sale_items", key: orderNo, reason: "non_positive_qty" });
      continue;
    }
    if (!clean(row.SKU)) {
      report.skipped.push({ table: "sale_items", key: orderNo, reason: "blank_sku" });
      continue;
    }
    if (!salesByOrder.has(orderNo)) salesByOrder.set(orderNo, []);
    salesByOrder.get(orderNo).push(row);
  }

  const sales = [];
  const saleItems = [];
  for (const [orderNo, rows] of salesByOrder) {
    const first = rows[0];
    const packagingTypes = [...new Set(rows.map((row) => clean(row["Паковання"])).filter(Boolean))];
    if (packagingTypes.length > 1) warning(report, `${orderNo}: multiple packaging types; packaging_type_id left NULL`);
    const sale = {
      sourceOrderNo: orderNo,
      sale: {
        order_no: orderNo,
        opencart_order_id: null,
        channel_name: mapChannel(clean(first["Джерело"])),
        sold_at: parseDate(first["Дата продажу"]),
        customer_phone: clean(first["Телефон клієнта"]) || null,
        customer_name: clean(first["ПІБ клієнта"]) || null,
        payment_type_name: clean(first["Тип оплати"]),
        payment_status_name: clean(first["Статус оплати"]),
        order_status_name: clean(first["Статус замовлення"]),
        post_method_name: clean(first["Пошта"]) || null,
        ttn: clean(first.TTN) || null,
        discount_total: sum(rows.map((row) => Math.max(parseMoney(row["Знижка"]) ?? 0, 0))),
        packaging_cost: sum(rows.map((row) => row["Пакування"])),
        shop_delivery: sum(rows.map((row) => row["Доставка за рахунок магазину"])),
        packaging_name: packagingTypes.length === 1 ? packagingTypes[0] : null,
        note: noteWithBatch(first["Примітка"], packagingTypes.length > 1 ? `legacy_packaging_types=${packagingTypes.join("|")}` : "")
      },
      items: []
    };
    for (const row of rows) {
      const prroUnit = parseMoney(row["Собівартість 1 од. / ПРРО"]);
      const mgmtUnit = parseMoney(row["Управлінська собівартість 1 од."]);
      const sourceMethod = clean(row["Метод собівартості"]);
      const sourceQty = parseQty(row["Кількість"]);
      const sourceUnitPrice = parseMoney(row["Ціна за одиницю"]) ?? 0;
      const sourceDiscount = parseMoney(row["Знижка"]) ?? 0;
      const normalizedDiscount = Math.max(sourceDiscount, 0);
      const normalizedUnitPrice = sourceDiscount < 0 && sourceQty > 0
        ? Math.round(((sourceQty * sourceUnitPrice) - sourceDiscount) / sourceQty * 100) / 100
        : sourceUnitPrice;
      if (sourceDiscount < 0) warning(report, `${orderNo}: negative legacy discount ${sourceDiscount}; unit price normalized to ${normalizedUnitPrice}`);
      const item = {
        sourceSku: clean(row.SKU),
        qty: sourceQty,
        unit_price: normalizedUnitPrice,
        discount_alloc: normalizedDiscount,
        packaging_alloc: parseMoney(row["Пакування"]) ?? 0,
        shop_delivery_alloc: parseMoney(row["Доставка за рахунок магазину"]) ?? 0,
        prro_unit: prroUnit,
        mgmt_unit: mgmtUnit,
        payment_fee: sum([row["Еквайринг"], row["Нова Пей"], row["Комісія маркетплейсу"]]),
        cost_method: normalizeCostMethod(sourceMethod),
        cost_audit: noteWithBatch(row["Аудит собівартості"], sourceMethod ? `legacy_method=${sourceMethod}` : "legacy_method_blank; snapshot cost preserved"),
        cost_state: "actual",
        cost_fixed_at: snapshotTimestamp,
        note: noteWithBatch(row["Примітка"], sourceDiscount < 0 ? `legacy_negative_discount=${sourceDiscount}` : "")
      };
      sale.items.push(item);
      saleItems.push(item);
    }
    if (sale.items.length) sales.push(sale);
  }

  const writeoffs = [];
  for (const row of writeoffsCsv.records) {
    const writeoffNo = clean(row["ID списання"]);
    const qty = parseQty(row["Кількість"]);
    if (!qty || qty <= 0) {
      report.skipped.push({ table: "writeoff_items", key: writeoffNo, reason: "non_positive_qty" });
      continue;
    }
    const sourceType = clean(row["Тип списання"]);
    const reason = clean(row["Причина"]);
    const mappedType = mapWriteoffType(sourceType, reason);
    if (mappedType !== sourceType) warning(report, `${writeoffNo}: ${sourceType} -> ${mappedType}`);
    writeoffs.push({
      sourceSku: clean(row.SKU),
      sourceMysteryOrder: extractOrderFromNote(row["Примітка"]),
      writeoff: {
        writeoff_no: writeoffNo,
        type: mappedType,
        reason: reason || null,
        expected_qty: null,
        written_off_at: parseDate(row["Дата"]) ?? cutoverDate,
        mystery_sale_id: null,
        note: noteWithBatch(row["Примітка"], `legacy_type=${sourceType}`)
      },
      item: { sourceSku: clean(row.SKU), qty, note: noteWithBatch(row["Примітка"], `legacy_type=${sourceType}`) }
    });
  }

  const consumables = consumablesCsv.records.map((row) => {
    const note = clean(row["Примітка"]);
    const activation = note.match(/\b(20\d{2}-\d{2}-\d{2})\b/)?.[1] ?? null;
    return {
      name: clean(row["Тип розхідника"]),
      category: clean(row["Категорія"]),
      unit_cost: parseMoney(row["Собівартість 1 шт"]) ?? 0,
      initial_stock: parseQty(row["Початково на складі"]) ?? 0,
      initial_in_transit: parseQty(row["Початково їде"]) ?? 0,
      received_via_expenses: 0,
      in_transit_via_expenses: 0,
      activation_date: activation,
      is_packaging: clean(row["Категорія"]) === "Упаковка",
      is_active: true
    };
  });

  report.counts = {
    app_config: legacyConfig.length,
    source_products: productRows.length,
    products: products.length,
    product_prices: productPrices.length,
    source_purchases: purchasesCsv.records.length,
    purchases: purchases.length,
    purchase_lots: purchases.length,
    source_sales: salesCsv.records.length,
    sales: sales.length,
    sale_items: saleItems.length,
    source_writeoffs: writeoffsCsv.records.length,
    writeoffs: writeoffs.length,
    writeoff_items: writeoffs.length,
    consumables: consumables.length
  };

  return { report, lookupRows, legacyConfig, products, productPrices, purchases, sales, writeoffs, consumables };
}

async function loadEnvFile() {
  const envPath = path.join(repoRoot, "ncrm", ".env.local");
  try {
    const text = await fs.readFile(envPath, "utf8");
    for (const line of text.split(/\r?\n/)) {
      const match = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
      if (match && !process.env[match[1]]) process.env[match[1]] = match[2].replace(/^['"]|['"]$/g, "");
    }
  } catch {
    // Dry-run does not need an env file; apply mode reports missing variables below.
  }
}

async function supabaseClient() {
  await loadEnvFile();
  const url = process.env.NEXT_PUBLIC_SUPABASE_URL;
  const key = process.env.SUPABASE_SERVICE_ROLE_KEY;
  if (!url || !key) throw new Error("Apply mode requires NEXT_PUBLIC_SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY.");
  return createClient(url, key, { auth: { autoRefreshToken: false, persistSession: false } });
}

async function rowsByCode(client, table) {
  const { data, error } = await client.from(table).select("id,code,name_uk");
  if (error) throw new Error(`${table} lookup failed: ${error.message}`);
  const result = new Map();
  for (const row of data ?? []) {
    if (row.code) result.set(row.code, row);
    if (row.name_uk) result.set(row.name_uk, row);
  }
  return result;
}

async function upsertChunks(client, table, rows, onConflict) {
  for (let i = 0; i < rows.length; i += 100) {
    const chunk = rows.slice(i, i + 100);
    if (!chunk.length) continue;
    const { error } = await client.from(table).upsert(chunk, { onConflict });
    if (error) throw new Error(`${table} upsert failed: ${error.message}`);
  }
}

async function deleteBatch(client) {
  const config = await client.from("app_config").delete().like("description", `%imported_batch=${batch}%`);
  if (config.error) throw new Error(`app_config batch delete failed: ${config.error.message}`);

  const writeoffs = await client.from("writeoffs").select("id").like("note", `%imported_batch=${batch}%`);
  if (writeoffs.error) throw new Error(`writeoffs batch lookup failed: ${writeoffs.error.message}`);
  const writeoffIds = (writeoffs.data ?? []).map((row) => row.id);
  if (writeoffIds.length) {
    const result = await client.from("writeoff_items").delete().in("writeoff_id", writeoffIds);
    if (result.error) throw new Error(`writeoff_items batch delete failed: ${result.error.message}`);
    const deleted = await client.from("writeoffs").delete().in("id", writeoffIds);
    if (deleted.error) throw new Error(`writeoffs batch delete failed: ${deleted.error.message}`);
  }

  const sales = await client.from("sales").select("id").like("note", `%imported_batch=${batch}%`);
  if (sales.error) throw new Error(`sales batch lookup failed: ${sales.error.message}`);
  const saleIds = (sales.data ?? []).map((row) => row.id);
  if (saleIds.length) {
    const result = await client.from("sale_items").delete().in("sale_id", saleIds);
    if (result.error) throw new Error(`sale_items batch delete failed: ${result.error.message}`);
    const deleted = await client.from("sales").delete().in("id", saleIds);
    if (deleted.error) throw new Error(`sales batch delete failed: ${deleted.error.message}`);
  }

  const lots = await client.from("purchase_lots").select("id,purchase_id").like("note", `%imported_batch=${batch}%`);
  if (lots.error) throw new Error(`purchase_lots batch lookup failed: ${lots.error.message}`);
  const lotIds = (lots.data ?? []).map((row) => row.id);
  const purchaseIds = [...new Set((lots.data ?? []).map((row) => row.purchase_id))];
  if (lotIds.length) {
    const result = await client.from("purchase_lots").delete().in("id", lotIds);
    if (result.error) throw new Error(`purchase_lots batch delete failed: ${result.error.message}`);
  }
  if (purchaseIds.length) {
    const result = await client.from("purchases").delete().in("id", purchaseIds);
    if (result.error) throw new Error(`purchases batch delete failed: ${result.error.message}`);
  }

  const prices = await client.from("product_prices").delete().like("note", `%imported_batch=${batch}%`);
  if (prices.error) throw new Error(`product_prices batch delete failed: ${prices.error.message}`);
}

async function applyModel(model) {
  if (!acknowledgeAssumptions) {
    throw new Error("Apply mode requires --acknowledge-legacy-assumptions after reviewing the dry-run warnings.");
  }
  const client = await supabaseClient();
  await deleteBatch(client);

  await upsertChunks(client, "app_config", model.legacyConfig, "key,effective_from");
  for (const [table, rows] of Object.entries(model.lookupRows)) await upsertChunks(client, table, rows, "code");
  const regions = await rowsByCode(client, "supplier_regions");
  const channels = await rowsByCode(client, "sale_channels");
  const paymentTypes = await rowsByCode(client, "payment_types");
  const paymentStatuses = await rowsByCode(client, "payment_statuses");
  const orderStatuses = await rowsByCode(client, "order_statuses");
  const postMethods = await rowsByCode(client, "post_methods");
  for (const row of model.consumables) await upsertChunks(client, "consumables", [row], "name");
  const consumableRows = await client.from("consumables").select("id,name");
  if (consumableRows.error) throw new Error(`consumables lookup failed: ${consumableRows.error.message}`);
  const consumableByName = new Map((consumableRows.data ?? []).map((row) => [row.name, row.id]));

  await upsertChunks(client, "products", model.products, "sku");
  const productRows = await client.from("products").select("id,sku").in("sku", model.products.map((row) => row.sku));
  if (productRows.error) throw new Error(`products lookup failed: ${productRows.error.message}`);
  const productIds = new Map((productRows.data ?? []).map((row) => [row.sku, row.id]));
  const priceRows = model.productPrices.map((row) => ({
    product_id: productIds.get(row.sku),
    rrc: row.rrc,
    source: row.source,
    note: row.note,
    effective_from: row.effective_from
  }));
  if (priceRows.some((row) => !row.product_id)) throw new Error("Product price mapping contains an unknown SKU.");
  await upsertChunks(client, "product_prices", priceRows, "product_id,effective_from");

  const purchaseIdByLot = new Map();
  for (const entry of model.purchases) {
    const purchase = { ...entry.purchase, region_id: regions.get(entry.purchase.region_code)?.id };
    delete purchase.region_code;
    if (!purchase.region_id) throw new Error(`Unknown supplier region for ${entry.lotCode}.`);
    const inserted = await client.from("purchases").insert(purchase).select("id").single();
    if (inserted.error) throw new Error(`purchase ${entry.lotCode} insert failed: ${inserted.error.message}`);
    const lot = {
      ...entry.lot,
      purchase_id: inserted.data.id,
      product_id: productIds.get(entry.lot.source_sku)
    };
    delete lot.source_sku;
    if (!lot.product_id) throw new Error(`Unknown purchase SKU for ${entry.lotCode}.`);
    const lotResult = await client.from("purchase_lots").insert(lot).select("id").single();
    if (lotResult.error) throw new Error(`purchase lot ${entry.lotCode} insert failed: ${lotResult.error.message}`);
    purchaseIdByLot.set(entry.lotCode, inserted.data.id);
  }

  const saleIdByOrder = new Map();
  for (const entry of model.sales) {
    const sale = {
      ...entry.sale,
      channel_id: channels.get(entry.sale.channel_name)?.id,
      payment_type_id: paymentTypes.get(entry.sale.payment_type_name)?.id,
      payment_status_id: paymentStatuses.get(entry.sale.payment_status_name)?.id,
      order_status_id: orderStatuses.get(entry.sale.order_status_name)?.id,
      post_method_id: entry.sale.post_method_name ? postMethods.get(entry.sale.post_method_name)?.id : null,
      packaging_type_id: entry.sale.packaging_name ? consumableByName.get(entry.sale.packaging_name) ?? null : null
    };
    for (const key of ["channel_id", "payment_type_id", "payment_status_id", "order_status_id"]) {
      if (!sale[key]) throw new Error(`Missing lookup ${key} for sale ${entry.sourceOrderNo}.`);
    }
    for (const key of ["channel_name", "payment_type_name", "payment_status_name", "order_status_name", "post_method_name", "packaging_name"]) delete sale[key];
    const inserted = await client.from("sales").insert(sale).select("id").single();
    if (inserted.error) throw new Error(`sale ${entry.sourceOrderNo} insert failed: ${inserted.error.message}`);
    saleIdByOrder.set(entry.sourceOrderNo, inserted.data.id);
    const items = entry.items.map((item) => ({ ...item, sale_id: inserted.data.id, product_id: productIds.get(item.sourceSku) }));
    for (const item of items) delete item.sourceSku;
    if (items.some((item) => !item.product_id)) throw new Error(`Unknown product in sale ${entry.sourceOrderNo}.`);
    await upsertChunks(client, "sale_items", items, "id");
  }

  for (const entry of model.writeoffs) {
    const writeoff = { ...entry.writeoff, mystery_sale_id: entry.sourceMysteryOrder ? saleIdByOrder.get(entry.sourceMysteryOrder) ?? null : null };
    const inserted = await client.from("writeoffs").insert(writeoff).select("id").single();
    if (inserted.error) throw new Error(`writeoff ${writeoff.writeoff_no} insert failed: ${inserted.error.message}`);
    const item = { ...entry.item, writeoff_id: inserted.data.id, product_id: productIds.get(entry.item.sourceSku) };
    delete item.sourceSku;
    if (!item.product_id) throw new Error(`Unknown product in writeoff ${writeoff.writeoff_no}.`);
    const result = await client.from("writeoff_items").insert(item);
    if (result.error) throw new Error(`writeoff item ${writeoff.writeoff_no} insert failed: ${result.error.message}`);
  }

  return { applied: true, batch, counts: model.report.counts };
}

const model = await buildModel();
console.log(JSON.stringify({ mode: applyMode ? "apply" : "dry-run", dataDir, batch, ...model.report }, null, 2));

if (applyMode) {
  const result = await applyModel(model);
  console.log(JSON.stringify(result, null, 2));
} else {
  console.log("dry_run=ok writes=0");
}
