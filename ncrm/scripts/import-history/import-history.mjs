#!/usr/bin/env node

import fs from "node:fs/promises";
import path from "node:path";
import { createHash } from "node:crypto";
import { fileURLToPath } from "node:url";
import { spawn } from "node:child_process";
import { createClient } from "@supabase/supabase-js";

// NCRM-03 round 3 is intentionally local-only. It imports the frozen 2026-07-16
// Sheets export and uses a trusted local SQL transaction only for legacy Mystery
// reconstruction. It never changes migrations or contacts a cloud project.
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const repoRoot = path.resolve(__dirname, "../../..");
const defaultDataDir = path.join(repoRoot, "ncrm", "import", "raw", "2026-07-16");
const legacyMysterySkus = new Set(["PKM-JP-MIX-MBX", "OP-JP-MIX-MBX"]);
const approximateMysteryOrders = new Set(["OC-FOP-0219"]);
const pairedLegacyReclassifications = [
  { id: "WRT-0094", counterpart: "WRT-0093", sku: "PKM-JP-MSYM-BST", qty: -11, counterpartSku: "PKM-JP-MZERO-BST", counterpartQty: 18 },
  { id: "WRT-0095", counterpart: "WRT-0093", sku: "PKM-JP-MDEX-BST", qty: -1, counterpartSku: "PKM-JP-MZERO-BST", counterpartQty: 18 },
  { id: "WRT-0096", counterpart: "WRT-0093", sku: "PKM-JP-OUTL-BST", qty: -6, counterpartSku: "PKM-JP-MZERO-BST", counterpartQty: 18 },
  { id: "WRT-0097", counterpart: "WRT-0098", sku: "PKM-JP-MBRV-BST", qty: -1, counterpartSku: "PKM-JP-OUTL-BST", counterpartQty: 1 }
];

const sourceFiles = {
  sales: ["Booster Shop CRM — облік товарів - Продажі.csv", "sales.csv"],
  purchases: ["Booster Shop CRM — облік товарів - Закупки.csv", "purchases.csv"],
  writeoffs: ["Booster Shop CRM — облік товарів - Списання.csv", "writeoffs.csv"],
  rrc: ["Booster Shop CRM — облік товарів - РРЦ.csv", "rrc.csv"],
  stock: ["Booster Shop CRM — облік товарів - Склад.csv", "stock_reference.csv"],
  consumables: ["Booster Shop CRM — облік товарів - Розхідники.csv", "consumables.csv"]
};

function argValue(name, fallback) {
  const prefix = `${name}=`;
  const item = process.argv.slice(2).find((value) => value.startsWith(prefix));
  return item ? item.slice(prefix.length) : fallback;
}

const applyMode = process.argv.includes("--apply");
const acknowledgeAssumptions = process.argv.includes("--acknowledge-legacy-assumptions");
const cutoverDate = argValue("--cutover-date", "2026-07-16");
const legacyCostStartDate = "2026-04-01";
const batch = argValue("--batch", "ncrm03_20260716_r3");
const dataDir = path.resolve(argValue("--data-dir", defaultDataDir));
const localDbContainer = argValue("--local-db-container", "supabase_db_booster-shop-ncrm");
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

async function sourcePath(logicalName) {
  for (const name of sourceFiles[logicalName] ?? []) {
    const candidate = path.join(dataDir, name);
    try {
      await fs.access(candidate);
      return candidate;
    } catch {}
  }
  throw new Error(`Missing ${logicalName} source in ${dataDir}. Expected one of: ${(sourceFiles[logicalName] ?? []).join(", ")}`);
}

async function readCsv(logicalName, headerRowIndex) {
  const filePath = await sourcePath(logicalName);
  const rows = parseCsv((await fs.readFile(filePath, "utf8")).replace(/^\uFEFF/, ""));
  const sourceHeader = rows[headerRowIndex];
  if (!sourceHeader) throw new Error(`Missing CSV header row ${headerRowIndex + 1}: ${path.basename(filePath)}`);
  const seen = new Map();
  const header = sourceHeader.map((value, index) => {
    const base = clean(value) || `__blank_${index}`;
    const occurrence = (seen.get(base) ?? 0) + 1;
    seen.set(base, occurrence);
    return occurrence === 1 ? base : `${base}__${occurrence}`;
  });
  const records = rows.slice(headerRowIndex + 1).map((values) => {
    const padded = [...values];
    while (padded.length < header.length) padded.push("");
    return Object.fromEntries(header.map((key, index) => [key, padded[index] ?? ""]));
  });
  return { logicalName, filePath, header, records };
}

function parseMoney(value) {
  const normalized = clean(value)
    .replace(/грн/gi, "")
    .replace(/\s/g, "")
    .replace(/,/g, ".")
    .replace(/[^0-9.+-]/g, "");
  if (!normalized) return null;
  const parsed = Number(normalized);
  return Number.isFinite(parsed) ? Math.round(parsed * 100) / 100 : null;
}

function parseQty(value) {
  const parsed = parseMoney(value);
  return parsed === null ? null : Math.round(parsed * 1000) / 1000;
}

function parseDate(value) {
  const normalized = clean(value);
  return /^\d{4}-\d{2}-\d{2}$/.test(normalized) ? normalized : null;
}

function sum(values) {
  return Math.round(values.reduce((total, value) => total + (parseMoney(value) ?? 0), 0) * 100) / 100;
}

function noteWithBatch(...parts) {
  return [`imported_batch=${batch}`, ...parts.map(clean).filter(Boolean)].join("; ");
}

function stableUuid(...parts) {
  const hex = createHash("sha256").update([batch, ...parts].join("\u001f")).digest("hex");
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-5${hex.slice(13, 16)}-8${hex.slice(17, 20)}-${hex.slice(20, 32)}`;
}

function codeFor(value) {
  const aliases = {
    "Pokémon": "pokemon", "One Piece": "one_piece", "Yu-Gi-Oh!": "yu_gi_oh", MTG: "mtg",
    Generic: "generic", "Games 7 Days": "games_7_days", Accessory: "accessory",
    Booster: "booster", "Booster Box": "booster_box", "Booster Bundle": "booster_bundle",
    "Collection Set": "collection_set", Blister: "blister", "Mystery Box": "mystery_box",
    JP: "jp", KR: "kr", EN: "en", DE: "de"
  };
  if (aliases[value]) return aliases[value];
  return clean(value).toLowerCase().replace(/[^a-z0-9]+/g, "_").replace(/^_|_$/g, "");
}

function inferGame(row) {
  const brand = clean(row["Бренд"]);
  return ["Pokémon", "One Piece", "Yu-Gi-Oh!", "MTG"].includes(brand) ? codeFor(brand) : null;
}

function inferLanguage(sku) {
  const match = clean(sku).match(/-(JP|KR|EN|DE)-/i);
  return match ? codeFor(match[1].toUpperCase()) : null;
}

function normalizeCostMethod(value) {
  const source = clean(value).replace(/\s*\+\s*авторозхідники/gi, "").replace(/\s+/g, " ");
  if (/FIFO\s*\+\s*fallback/i.test(source)) return "FIFO+fallback";
  if (/^FIFO$/i.test(source)) return "FIFO";
  if (/^Fallback$/i.test(source)) return "Fallback";
  return "Відкладено";
}

function mapWriteoffType(sourceType) {
  if (sourceType === "Промо" || sourceType === "Подарунок") return "Маркетинг";
  if (sourceType === "Власне відкриття") return "Власне відкриття";
  // MBOX is reserved by NCRM-05 for an atomic fulfillment only. Legacy source
  // rows are either collapsed into that fulfillment or remain ordinary writeoffs.
  return "Інше";
}

function mapLotStatus(sourceStatus) {
  return {
    "В дорозі": "in_transit", Замовлено: "ordered", "На складі": "in_stock",
    "На складі UA": "in_stock", Продано: "sold", "Частково продано": "selling"
  }[clean(sourceStatus)] ?? "cancelled";
}

function mapChannel(value) {
  return ["OpenCart", "Telegram", "OLX", "Monobazar"].includes(clean(value)) ? clean(value) : "Інше";
}

function isActualSale(sale) {
  const payment = clean(sale.payment_status_name);
  const status = clean(sale.order_status_name);
  if (["Скасовано", "Повернення"].includes(payment) || ["Скасовано", "Повернення", "Передзамовлення"].includes(status)) return false;
  return payment === "Оплачено" || ["Нове", "В обробці", "Відправлено", "Отримано"].includes(status);
}

function sourceRegion(row) {
  const supplier = clean(row["Постачальник"]);
  const note = clean(row["Примітка"]);
  const order = clean(row["ZenMarket Order №"]);
  if (supplier === "Temu" || /USD|Temu/i.test(note)) return { code: "usa", warning: "Temu/USD mapped to usa" };
  if (supplier === "other") return { code: "ukraine", warning: "supplier=other mapped to ukraine" };
  if (supplier === "zenmarket_jp" || /yskh|zenmarket/i.test(`${order} ${note}`)) return { code: "japan" };
  return { code: "ukraine", warning: "blank/unknown supplier mapped to ukraine" };
}

function isLegacyMysteryWriteoff(row) {
  return /містер|mystery|mbox|mbx/i.test(`${clean(row["Тип списання"])} ${clean(row["Причина"])} ${clean(row["Примітка"])}`);
}

function mysteryOrderFromNote(note) {
  const match = clean(note).match(/(?:Продаж|(?:Для\s+)?замовлення)\s+([A-Z]+-[A-Z]+-\d+)/i);
  return match ? match[1] : null;
}

function warning(report, message) {
  report.warnings.push(message);
}

function pairedReclassificationRows(writeoffRows) {
  const byId = new Map(writeoffRows.map((row) => [clean(row["ID списання"]), row]));
  return pairedLegacyReclassifications.map((definition) => {
    const row = byId.get(definition.id);
    const counterpart = byId.get(definition.counterpart);
    if (!row || !counterpart) throw new Error(`Legacy paired reclassification ${definition.id}/${definition.counterpart} is incomplete in the frozen source.`);
    const qty = parseQty(row["Кількість"]);
    const counterpartQty = parseQty(counterpart["Кількість"]);
    if (clean(row.SKU) !== definition.sku || qty !== definition.qty || clean(counterpart.SKU) !== definition.counterpartSku || counterpartQty !== definition.counterpartQty) {
      throw new Error(`Legacy paired reclassification ${definition.id}/${definition.counterpart} does not match the approved source shape.`);
    }
    const referenceText = `${clean(row["Причина"])} ${clean(row["Примітка"])} ${clean(counterpart["Причина"])} ${clean(counterpart["Примітка"])}`;
    if (!referenceText.includes(definition.counterpart)) throw new Error(`Legacy paired reclassification ${definition.id} does not reference ${definition.counterpart}.`);
    return {
      id: definition.id, counterpart: definition.counterpart, date: parseDate(row["Дата"]) ?? null,
      sku: definition.sku, qty: definition.qty, note: clean(row["Примітка"]),
      classification: "superseded_paired_reclassification"
    };
  });
}

function applyLegacyAvailabilityRule({ purchases, sales, normalWriteoffs, linkedMysteryGroups, report }) {
  const lotsBySku = new Map();
  for (const entry of purchases) {
    const availability = entry.availability;
    if (!availability || !["in_stock", "selling", "sold"].includes(entry.lot.status)) continue;
    if (!lotsBySku.has(entry.sourceSku)) lotsBySku.set(entry.sourceSku, []);
    lotsBySku.get(entry.sourceSku).push({ entry, remaining: Number(entry.lot.qty), ...availability, triggerRefs: [] });
  }

  const eventsBySku = new Map();
  const addEvent = (sourceSku, date, qty, sourceKind, reference) => {
    if (!sourceSku || !date || !qty || qty <= 0 || legacyMysterySkus.has(sourceSku)) return;
    if (!eventsBySku.has(sourceSku)) eventsBySku.set(sourceSku, []);
    eventsBySku.get(sourceSku).push({ date, qty: Number(qty), sourceKind, reference });
  };
  for (const entry of sales) {
    if (!isActualSale(entry.sale)) continue;
    for (const item of entry.items) addEvent(item.sourceSku, entry.sale.sold_at, item.qty, "sale_item", entry.sourceOrderNo);
  }
  for (const entry of normalWriteoffs) {
    addEvent(entry.item.sourceSku, entry.writeoff.written_off_at, entry.item.qty, "writeoff_item", entry.writeoff.writeoff_no);
  }
  for (const group of linkedMysteryGroups) {
    for (const component of group.components) addEvent(component.sourceSku, component.writtenOffAt, component.qty, "writeoff_item", component.writeoffNo);
  }

  const uncovered = [];
  for (const [sku, events] of eventsBySku) {
    const lots = lotsBySku.get(sku) ?? [];
    for (const event of events.sort((left, right) => left.date.localeCompare(right.date) || left.sourceKind.localeCompare(right.sourceKind) || left.reference.localeCompare(right.reference))) {
      let needed = event.qty;
      const consume = (candidates) => {
        for (const lot of candidates) {
          if (needed <= 0) break;
          if (lot.remaining <= 0) continue;
          const taken = Math.min(needed, lot.remaining);
          lot.remaining -= taken;
          needed -= taken;
        }
      };
      consume(lots
        .filter((lot) => lot.effectiveAvailabilityDate <= event.date)
        .sort((left, right) => left.effectiveAvailabilityDate.localeCompare(right.effectiveAvailabilityDate) || left.entry.lotCode.localeCompare(right.entry.lotCode)));
      if (needed > 0) {
        for (const lot of lots
          .filter((candidate) => candidate.remaining > 0 && candidate.effectiveAvailabilityDate > event.date)
          .sort((left, right) => left.effectiveAvailabilityDate.localeCompare(right.effectiveAvailabilityDate) || left.entry.lotCode.localeCompare(right.entry.lotCode))) {
          if (needed <= 0) break;
          lot.effectiveAvailabilityDate = event.date;
          lot.triggerRefs.push(event.reference);
          consume([lot]);
        }
      }
      if (needed > 0) uncovered.push({ sku, date: event.date, source_kind: event.sourceKind, reference: event.reference, uncovered_qty: needed });
    }
  }

  const adjustments = [];
  for (const lots of lotsBySku.values()) {
    for (const lot of lots) {
      const changed = lot.effectiveAvailabilityDate !== lot.originalAvailabilityDate;
      const legacyUndated = lot.sourceDeliveryDate === null;
      if (!changed && !legacyUndated) continue;
      lot.entry.purchase.ordered_at = lot.effectiveAvailabilityDate;
      if (changed && lot.sourceDeliveryDate !== null) lot.entry.lot.delivery_date = null;
      const reason = changed ? "pre_receipt_consumption" : "warehouse_status_without_delivery_date";
      lot.entry.lot.note = `${lot.entry.lot.note}; legacy_effective_availability_from=${lot.effectiveAvailabilityDate}; source_delivery_date=${lot.sourceDeliveryDate ?? "NULL"}; legacy_availability_reason=${reason}${lot.triggerRefs.length ? `; legacy_availability_triggers=${lot.triggerRefs.join("|")}` : ""}`;
      adjustments.push({
        lot_code: lot.entry.lotCode, sku: lot.entry.sourceSku, source_delivery_date: lot.sourceDeliveryDate,
        original_availability_date: lot.originalAvailabilityDate, effective_availability_date: lot.effectiveAvailabilityDate,
        db_delivery_date: lot.entry.lot.delivery_date, reason, trigger_references: lot.triggerRefs
      });
    }
  }
  report.legacy_effective_availability = adjustments.sort((left, right) => left.lot_code.localeCompare(right.lot_code));
  report.uncovered_pre_receipt_consumptions = uncovered;
}

function baseLegacyConfig() {
  return [
    ["credit_rate_monthly", 0.03, "ratio", "legacy CRM credit rate"],
    ["credit_months", 2, "months", "legacy CRM credit months"],
    ["expected_income_haircut", 1.05, "multiplier", "legacy CRM expected-income haircut"],
    ["acquiring_pct", 0.015, "ratio", "legacy CRM acquiring rate"],
    ["fop_control_pct", 0.005, "ratio", "legacy CRM FOP control rate"],
    ["fop_control_min", 0, "UAH", "legacy CRM FOP control minimum"],
    ["payback_fiz_pct", 0.02, "ratio", "legacy CRM personal COD rate"],
    ["payback_fiz_fix", 20, "UAH", "legacy CRM personal COD fixed fee"],
    ["nbu_rate_buffer_pct", 0.01, "ratio", "legacy CRM NBU rate buffer"]
  ].map(([key, value_num, unit, description]) => ({
    key, value_num, unit, description: noteWithBatch(description), effective_from: "2026-04-01", is_active: true
  })).concat([{
    key: "cost_start_date", value_date: "2026-04-01", unit: "date",
    description: noteWithBatch("legacy CRM warehouse cost start"), effective_from: "2026-04-01", is_active: true
  }]);
}

async function buildModel() {
  const [salesCsv, purchasesCsv, writeoffsCsv, rrcCsv, stockCsv, consumablesCsv] = await Promise.all([
    readCsv("sales", 1), readCsv("purchases", 1), readCsv("writeoffs", 1),
    readCsv("rrc", 1), readCsv("stock", 1), readCsv("consumables", 1)
  ]);
  const report = { warnings: [], skipped: [], counts: {}, source_files: Object.fromEntries(
    [salesCsv, purchasesCsv, writeoffsCsv, rrcCsv, stockCsv, consumablesCsv].map((csv) => [csv.logicalName, path.basename(csv.filePath)])
  ) };

  const lookupRows = { product_brands: [], product_categories: [], games: [], product_languages: [] };
  const seen = Object.fromEntries(Object.keys(lookupRows).map((key) => [key, new Set()]));
  const products = [];
  const productSourceBySku = new Map();
  for (const row of rrcCsv.records) {
    const sku = clean(row.SKU);
    if (!sku) continue;
    const brand = clean(row["Бренд"]);
    const category = clean(row["Формат"]);
    const game = inferGame(row);
    const language = inferLanguage(sku);
    if (brand && !seen.product_brands.has(codeFor(brand))) {
      seen.product_brands.add(codeFor(brand));
      lookupRows.product_brands.push({ code: codeFor(brand), name: brand, is_active: true });
    }
    if (category && !seen.product_categories.has(codeFor(category))) {
      seen.product_categories.add(codeFor(category));
      lookupRows.product_categories.push({ code: codeFor(category), name: category, is_active: true });
    }
    if (game && !seen.games.has(game)) {
      seen.games.add(game);
      lookupRows.games.push({ code: game, name: brand, is_active: true });
    }
    if (language && !seen.product_languages.has(language)) {
      seen.product_languages.add(language);
      lookupRows.product_languages.push({ code: language, name: language.toUpperCase(), is_active: true });
    }
    const product = {
      sku,
      name: clean(row["Назва товару"]) || sku,
      full_name: clean(row["Назва товару"]) || null,
      brand_code: brand ? codeFor(brand) : null,
      category_code: category ? codeFor(category) : null,
      game_code: game,
      language_code: language,
      legacy_sku: null,
      is_active: true,
      // These are deterministic frozen-catalogue classifications, not a runtime
      // SKU heuristic. The source export has no dedicated Outlet boolean.
      is_outlet: sku === "PKM-JP-OUTL-BST",
      is_sealed_pack: category === "Booster"
    };
    products.push(product);
    productSourceBySku.set(sku, product);
  }

  const productPrices = [];
  for (const row of rrcCsv.records) {
    const sku = clean(row.SKU);
    const rrc = parseMoney(row["РРЦ, грн"]);
    if (rrc === null) {
      report.skipped.push({ table: "product_prices", key: sku, reason: "blank_rrc" });
      continue;
    }
    productPrices.push({
      sku, rrc, source: "legacy РРЦ import", effective_from: parseDate(row["Дата оновлення"]) ?? cutoverDate,
      note: noteWithBatch(row["Примітка"], "imported_legacy_no_rate_history")
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
    const status = mapLotStatus(row["Статус"]);
    const legacyWarehouseWithoutReceivedDate = !deliveryDate && ["in_stock", "selling", "sold"].includes(status);
    const effectiveAvailabilityDate = deliveryDate ?? (legacyWarehouseWithoutReceivedDate ? legacyCostStartDate : cutoverDate);
    if (!deliveryDate) warning(report, legacyWarehouseWithoutReceivedDate
      ? `${lotCode}: missing received date; source warehouse status makes ${legacyCostStartDate} the documented legacy availability boundary`
      : `${lotCode}: missing delivery date; ordered_at=${cutoverDate}`);
    const goods = parseMoney(row["Вартість лоту, грн"]) ?? 0;
    const forwarding = parseMoney(row["Доставка / комісії по Японії, грн"]) ?? 0;
    const local = parseMoney(row["Доставка UA, грн"]) ?? 0;
    purchases.push({
      lotCode,
      sourceSku: clean(row.SKU),
      availability: {
        sourceDeliveryDate: deliveryDate,
        originalAvailabilityDate: effectiveAvailabilityDate,
        effectiveAvailabilityDate
      },
      purchase: {
        region_code: region.code, supplier_name: clean(row["Постачальник"]) || null,
        order_ref: clean(row["ZenMarket Order №"]) || null, order_url: clean(row["ZenMarket URL"]) || `legacy://ncrm03/${lotCode}`,
        ordered_at: effectiveAvailabilityDate,
        goods_total_amount: goods, goods_total_currency: "UAH", goods_total_rate: 1, goods_total_uah: goods,
        forwarding_fee_amount: forwarding, forwarding_fee_currency: "UAH", forwarding_fee_rate: 1, forwarding_fee_uah: forwarding,
        intl_shipping_amount: 0, intl_shipping_currency: "UAH", intl_shipping_rate: 1, intl_shipping_uah: 0,
        local_delivery_amount: local, local_delivery_currency: "UAH", local_delivery_rate: 1, local_delivery_uah: local,
        note: noteWithBatch(row["Примітка"], "imported_legacy_no_rate_history")
      },
      lot: {
        lot_code: lotCode, source_sku: clean(row.SKU), qty, goods_cost_uah: goods,
        forwarding_fee_uah: forwarding, intl_shipping_uah: 0, local_delivery_uah: local,
        manual_unit_cost: parseMoney(row["Собівартість 1 од. / ПРРО"]), delivery_date: deliveryDate,
        track_number: clean(row["Трек-номер"]) || null, status,
        legacy_status: clean(row["Статус"]),
        note: noteWithBatch(row["Примітка"], `source_mgmt_unit=${parseMoney(row["Управлінська собівартість 1 од."]) ?? "NULL"}`, "imported_legacy_no_rate_history")
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
  for (const [orderNo, rows] of salesByOrder) {
    const first = rows[0];
    const packagingTypes = [...new Set(rows.map((row) => clean(row["Паковання"])).filter(Boolean))];
    if (packagingTypes.length > 1) warning(report, `${orderNo}: multiple packaging types; packaging_type_id left NULL`);
    const items = rows.map((row) => {
      const sourceSku = clean(row.SKU);
      const sourceQty = parseQty(row["Кількість"]);
      const sourceDiscount = parseMoney(row["Знижка"]) ?? 0;
      const sourceUnitPrice = parseMoney(row["Ціна за одиницю"]) ?? 0;
      const legacyMystery = legacyMysterySkus.has(sourceSku);
      const unitPrice = sourceDiscount < 0 && sourceQty > 0
        ? Math.round((((sourceQty * sourceUnitPrice) - sourceDiscount) / sourceQty) * 100) / 100
        : sourceUnitPrice;
      if (sourceDiscount < 0) warning(report, `${orderNo}: negative legacy discount ${sourceDiscount}; unit price normalized`);
      return {
        sourceSku, qty: sourceQty, unit_price: unitPrice, discount_alloc: Math.max(sourceDiscount, 0),
        packaging_alloc: parseMoney(row["Пакування"]) ?? 0, shop_delivery_alloc: parseMoney(row["Доставка за рахунок магазину"]) ?? 0,
        prro_unit: legacyMystery ? null : parseMoney(row["Собівартість 1 од. / ПРРО"]),
        mgmt_unit: legacyMystery ? null : parseMoney(row["Управлінська собівартість 1 од."]),
        payment_fee: sum([row["Еквайринг"], row["Нова Пей"], row["Комісія маркетплейсу"]]),
        cost_method: legacyMystery ? "Provisional" : normalizeCostMethod(row["Метод собівартості"]),
        cost_state: legacyMystery ? "provisional" : "actual",
        cost_fixed_at: legacyMystery ? null : snapshotTimestamp,
        cost_audit: legacyMystery
          ? noteWithBatch("legacy mystery provisional pending source reconstruction")
          : noteWithBatch(row["Аудит собівартості"], `legacy_method=${clean(row["Метод собівартості"]) || "blank"}`),
        note: noteWithBatch(row["Примітка"], sourceDiscount < 0 ? `legacy_negative_discount=${sourceDiscount}` : ""),
        legacy_source_prro_unit: legacyMystery ? parseMoney(row["Собівартість 1 од. / ПРРО"]) : null,
        legacy_source_mgmt_unit: legacyMystery ? parseMoney(row["Управлінська собівартість 1 од."]) : null
      };
    });
    sales.push({
      sourceOrderNo: orderNo,
      sale: {
        order_no: orderNo, opencart_order_id: null, channel_name: mapChannel(first["Джерело"]), sold_at: parseDate(first["Дата продажу"]),
        customer_phone: clean(first["Телефон клієнта"]) || null, customer_name: clean(first["ПІБ клієнта"]) || null,
        payment_type_name: clean(first["Тип оплати"]), payment_status_name: clean(first["Статус оплати"]),
        order_status_name: clean(first["Статус замовлення"]), post_method_name: clean(first["Пошта"]) || null,
        ttn: clean(first["ТТН"]) || null, discount_total: sum(rows.map((row) => Math.max(parseMoney(row["Знижка"]) ?? 0, 0))),
        packaging_cost: sum(rows.map((row) => row["Пакування"])), shop_delivery: sum(rows.map((row) => row["Доставка за рахунок магазину"])),
        packaging_name: packagingTypes.length === 1 ? packagingTypes[0] : null,
        note: noteWithBatch(first["Примітка"], packagingTypes.length > 1 ? `legacy_packaging_types=${packagingTypes.join("|")}` : "")
      }, items
    });
  }

  const mysteryComponents = new Map();
  const deferredNcrm13 = [];
  const normalWriteoffs = [];
  const supersededPairedCorrections = pairedReclassificationRows(writeoffsCsv.records);
  const supersededPairedIds = new Set(supersededPairedCorrections.map((row) => row.id));
  for (const row of writeoffsCsv.records) {
    const writeoffNo = clean(row["ID списання"]);
    const qty = parseQty(row["Кількість"]);
    if (!qty || qty <= 0) {
      if (qty !== null && qty < 0) {
        if (supersededPairedIds.has(writeoffNo)) {
          report.skipped.push({ table: "writeoff_items", key: writeoffNo, reason: "superseded_paired_reclassification" });
          continue;
        }
        deferredNcrm13.push({
          id: writeoffNo, date: parseDate(row["Дата"]) ?? null, sku: clean(row.SKU), qty,
          reason: clean(row["Причина"]), note: clean(row["Примітка"])
        });
      }
      report.skipped.push({ table: "writeoff_items", key: writeoffNo, reason: "non_positive_qty" });
      continue;
    }
    const mysteryOrder = isLegacyMysteryWriteoff(row) ? mysteryOrderFromNote(row["Примітка"]) : null;
    if (mysteryOrder) {
      if (!mysteryComponents.has(mysteryOrder)) mysteryComponents.set(mysteryOrder, []);
      mysteryComponents.get(mysteryOrder).push({
        writeoffNo, sourceSku: clean(row.SKU), qty, writtenOffAt: parseDate(row["Дата"]) ?? cutoverDate,
        reason: clean(row["Причина"]), note: clean(row["Примітка"])
      });
      continue;
    }
    const sourceType = clean(row["Тип списання"]);
    normalWriteoffs.push({
      sourceSku: clean(row.SKU),
      writeoff: {
        writeoff_no: writeoffNo, type: mapWriteoffType(sourceType), reason: clean(row["Причина"]) || null,
        expected_qty: null, written_off_at: parseDate(row["Дата"]) ?? cutoverDate, mystery_sale_id: null,
        note: noteWithBatch(row["Примітка"], `legacy_type=${sourceType}`, isLegacyMysteryWriteoff(row) ? "legacy_mystery_unlinked_not_reconstructed" : "")
      },
      item: { sourceSku: clean(row.SKU), qty, note: noteWithBatch(row["Примітка"], `legacy_source_writeoff_no=${writeoffNo}`) }
    });
  }

  const salesByNo = new Map(sales.map((entry) => [entry.sourceOrderNo, entry]));
  const linkedMysteryGroups = [];
  for (const [orderNo, components] of mysteryComponents) {
    const sale = salesByNo.get(orderNo);
    if (!sale || !sale.items.some((item) => legacyMysterySkus.has(item.sourceSku))) {
      warning(report, `${orderNo}: Mystery source writeoffs have no eligible legacy Mystery sale; imported as ordinary writeoffs`);
      for (const component of components) normalWriteoffs.push({
        sourceSku: component.sourceSku,
        writeoff: {
          writeoff_no: component.writeoffNo, type: "Інше", reason: component.reason || null, expected_qty: null,
          written_off_at: component.writtenOffAt, mystery_sale_id: null,
          note: noteWithBatch(component.note, "legacy_mystery_unlinked_not_reconstructed")
        },
        item: { sourceSku: component.sourceSku, qty: component.qty, note: noteWithBatch(component.note, `legacy_source_writeoff_no=${component.writeoffNo}`) }
      });
      continue;
    }
    linkedMysteryGroups.push({ orderNo, components, approximation: approximateMysteryOrders.has(orderNo) });
  }

  const consumables = consumablesCsv.records.map((row) => {
    const note = clean(row["Примітка"]);
    return {
      name: clean(row["Тип розхідника"]), category: clean(row["Категорія"]), unit_cost: parseMoney(row["Собівартість 1 шт"]) ?? 0,
      initial_stock: parseQty(row["Початково на складі"]) ?? 0, initial_in_transit: parseQty(row["Початково їде"]) ?? 0,
      received_via_expenses: 0, in_transit_via_expenses: 0,
      activation_date: note.match(/\b(20\d{2}-\d{2}-\d{2})\b/)?.[1] ?? null,
      is_packaging: clean(row["Категорія"]) === "Упаковка", is_active: true
    };
  }).filter((row) => row.name);

  const transformedSourceWriteoffItems = linkedMysteryGroups.flatMap((group) => group.components);
  const mysteryBoxesForGroup = (group) => (salesByNo.get(group.orderNo)?.items ?? [])
    .filter((item) => legacyMysterySkus.has(item.sourceSku))
    .reduce((total, item) => total + Number(item.qty), 0);
  const mysterySaleItemDocuments = linkedMysteryGroups.reduce((total, group) => total + (salesByNo.get(group.orderNo)?.items ?? [])
    .filter((item) => legacyMysterySkus.has(item.sourceSku)).length, 0);
  applyLegacyAvailabilityRule({ purchases, sales, normalWriteoffs, linkedMysteryGroups, report });
  report.deferred_ncrm13 = deferredNcrm13;
  report.superseded_paired_reclassifications = supersededPairedCorrections;
  report.mystery = {
    source_linked_writeoff_rows: transformedSourceWriteoffItems.length,
    source_linked_groups: linkedMysteryGroups.length,
    generated_mbox_documents: mysterySaleItemDocuments,
    exact_groups: linkedMysteryGroups.filter((group) => !group.approximation).length,
    approximation_groups: linkedMysteryGroups.filter((group) => group.approximation).map((group) => group.orderNo),
    exact_boxes: linkedMysteryGroups.filter((group) => !group.approximation).reduce((total, group) => total + mysteryBoxesForGroup(group), 0),
    approximation_boxes: linkedMysteryGroups.filter((group) => group.approximation).reduce((total, group) => total + mysteryBoxesForGroup(group), 0),
    source_unlinked_or_inventory_rows: writeoffsCsv.records.filter((row) => (
      (parseQty(row["Кількість"]) ?? 0) > 0 && isLegacyMysteryWriteoff(row) && !mysteryOrderFromNote(row["Примітка"])
    )).length
  };
  report.counts = {
    app_config: baseLegacyConfig().length, source_products_rrc: rrcCsv.records.length, products: products.length, product_prices: productPrices.length,
    source_purchases: purchasesCsv.records.length, purchases: purchases.length, purchase_lots: purchases.length,
    source_sales: salesCsv.records.length, sales: sales.length, sale_items: sales.reduce((total, entry) => total + entry.items.length, 0),
    source_writeoffs: writeoffsCsv.records.length, source_positive_writeoffs: writeoffsCsv.records.filter((row) => (parseQty(row["Кількість"]) ?? 0) > 0).length,
    source_zero_writeoffs: writeoffsCsv.records.filter((row) => parseQty(row["Кількість"]) === 0).length,
    superseded_paired_reclassifications: supersededPairedCorrections.length, deferred_ncrm13_writeoffs: deferredNcrm13.length, ordinary_writeoffs: normalWriteoffs.length,
    source_mystery_groups: linkedMysteryGroups.length, generated_mbox_writeoffs: mysterySaleItemDocuments,
    writeoffs_after_architectural_transform: normalWriteoffs.length + mysterySaleItemDocuments,
    writeoff_items_after_architectural_transform: normalWriteoffs.length + transformedSourceWriteoffItems.length,
    mystery_contents: transformedSourceWriteoffItems.length, consumables: consumables.length
  };
  return { report, lookupRows, products, productSourceBySku, productPrices, purchases, sales, normalWriteoffs, linkedMysteryGroups, consumables, legacyConfig: baseLegacyConfig() };
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

async function supabaseClient() {
  await loadEnvFile();
  const url = process.env.NEXT_PUBLIC_SUPABASE_URL;
  const key = process.env.SUPABASE_SERVICE_ROLE_KEY;
  if (!url || !key) throw new Error("Apply mode requires NEXT_PUBLIC_SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY.");
  return createClient(url, key, { auth: { autoRefreshToken: false, persistSession: false } });
}

async function queryRows(client, table, select) {
  const { data, error } = await client.from(table).select(select);
  if (error) throw new Error(`${table}: ${error.message}`);
  return data ?? [];
}

async function rowsByCode(client, table) {
  const rows = await queryRows(client, table, "id,code,name_uk");
  const result = new Map();
  for (const row of rows) {
    if (row.code) result.set(row.code, row);
    if (row.name_uk) result.set(row.name_uk, row);
  }
  return result;
}

async function upsertChunks(client, table, rows, onConflict) {
  for (let index = 0; index < rows.length; index += 100) {
    const chunk = rows.slice(index, index + 100);
    if (!chunk.length) continue;
    const { error } = await client.from(table).upsert(chunk, { onConflict });
    if (error) throw new Error(`${table} upsert failed: ${error.message}`);
  }
}

async function assertBatchAbsent(client) {
  const [sales, writeoffs] = await Promise.all([
    client.from("sales").select("id", { count: "exact", head: true }).like("note", `%imported_batch=${batch}%`),
    client.from("writeoffs").select("id", { count: "exact", head: true }).like("note", `%imported_batch=${batch}%`)
  ]);
  if (sales.error || writeoffs.error) throw new Error(`Batch preflight failed: ${sales.error?.message ?? writeoffs.error?.message}`);
  if ((sales.count ?? 0) || (writeoffs.count ?? 0)) {
    throw new Error(`Batch ${batch} already exists. Run rollback_ncrm03_20260716.sql locally before another --apply; dry-run remains safe.`);
  }
}

function sqlLiteral(value) {
  return `'${String(value).replace(/'/g, "''")}'`;
}

function sqlUuid(value) {
  if (!/^[0-9a-f-]{36}$/i.test(String(value))) throw new Error(`Expected UUID, got ${value}`);
  return sqlLiteral(value);
}

function runDockerPsql(sql) {
  return new Promise((resolve, reject) => {
    const child = spawn("docker", ["exec", "-i", localDbContainer, "psql", "-U", "postgres", "-d", "postgres", "-v", "ON_ERROR_STOP=1", "-q"], {
      stdio: ["pipe", "pipe", "pipe"], windowsHide: true
    });
    let stdout = "";
    let stderr = "";
    child.stdout.on("data", (chunk) => { stdout += chunk; });
    child.stderr.on("data", (chunk) => { stderr += chunk; });
    child.on("error", (error) => reject(new Error(`Local Docker SQL launch failed: ${error.message}`)));
    child.on("close", (code) => {
      if (code === 0) resolve(stdout.trim());
      else reject(new Error(`Legacy Mystery SQL failed (container=${localDbContainer}, exit=${code}): ${stderr.trim() || stdout.trim()}`));
    });
    child.stdin.end(sql);
  });
}

function buildLegacyMysterySql(assignments) {
  const blocks = assignments.map((assignment) => {
    const writeoffNo = `MBOX-LEGACY-${assignment.orderNo}-${assignment.saleSku}`;
    const writeoffId = stableUuid("writeoff", writeoffNo);
    const references = assignment.components.map((component) => component.writeoffNo).join(",");
    const reconstruction = assignment.approximation ? "approximation" : "exact";
    const fulfillmentNote = assignment.approximation
      ? `legacy_import=approximation; OC-FOP-0219 aggregate composition applies to two boxes; source_writeoff_refs=${references}`
      : `legacy_import=exact; source_writeoff_refs=${references}`;
    const itemValues = assignment.components.map((component) => `(${sqlUuid(stableUuid("writeoff_item", writeoffNo, component.writeoffNo))}::uuid, ${sqlUuid(component.productId)}::uuid, ${component.qty}, ${sqlLiteral(`legacy_source_wrt=${component.writeoffNo}`)})`).join(",\n  ");
    return `
-- ${assignment.orderNo} / ${assignment.saleSku}: ${reconstruction}
update public.mystery_fulfillments
set note = ${sqlLiteral(fulfillmentNote)}
where id = ${sqlUuid(assignment.fulfillmentId)};

insert into public.inventory_reservations (fulfillment_id, product_id, qty)
values ${assignment.components.map((component) => `(${sqlUuid(assignment.fulfillmentId)}, ${sqlUuid(component.productId)}, ${component.qty})`).join(", ")};

insert into public.mystery_fulfillment_items (fulfillment_id, reservation_id, product_id, qty)
select fulfillment_id, id, product_id, qty
from public.inventory_reservations
where fulfillment_id = ${sqlUuid(assignment.fulfillmentId)};

update public.mystery_fulfillments
set state = 'reserved'
where id = ${sqlUuid(assignment.fulfillmentId)};

select set_config('app.mystery_commit', 'on', true);

insert into public.writeoffs (id, writeoff_no, type, reason, expected_qty, written_off_at, mystery_sale_id, mystery_fulfillment_id, note)
values (
  ${sqlUuid(writeoffId)}, ${sqlLiteral(writeoffNo)}, 'MBOX', 'NCRM-03 legacy Mystery reconstruction', ${assignment.expectedQty}, ${sqlLiteral(assignment.soldAt)},
  ${sqlUuid(assignment.saleId)}, ${sqlUuid(assignment.fulfillmentId)},
  ${sqlLiteral(noteWithBatch(`legacy_source_writeoff_refs=${references}`, `reconstruction=${reconstruction}`))}
);

with components(id, product_id, qty, item_note) as (
  values
  ${itemValues}
)
insert into public.writeoff_items (id, writeoff_id, product_id, qty, note)
select c.id, w.id, c.product_id, c.qty, c.item_note
from public.writeoffs w
join components c on true
where w.writeoff_no = ${sqlLiteral(writeoffNo)};

insert into public.mystery_contents (sale_item_id, component_product_id, qty, source, writeoff_item_id)
select ${sqlUuid(assignment.saleItemId)}, wi.product_id, wi.qty, 'writeoff', wi.id
from public.writeoff_items wi
join public.writeoffs w on w.id = wi.writeoff_id
where w.writeoff_no = ${sqlLiteral(writeoffNo)};

update public.inventory_reservations
set state = 'committed', committed_at = now()
where fulfillment_id = ${sqlUuid(assignment.fulfillmentId)};

update public.mystery_fulfillments
set state = 'committed', committed_at = now()
where id = ${sqlUuid(assignment.fulfillmentId)};

-- The legacy source identifies the components, but several contributing purchase
-- lots lack a received date. Therefore historical FIFO cannot be audited without
-- inventing a layer. Preserve the frozen legacy snapshot as an explicitly
-- estimated cost, never as actual FIFO COGS.
update public.sale_items
set
  prro_unit = ${assignment.sourcePrroUnit},
  mgmt_unit = ${assignment.sourceMgmtUnit},
  cost_method = 'Fallback',
  cost_state = 'estimated',
  cost_audit = ${sqlLiteral(`legacy Mystery contents=${reconstruction}; historical FIFO layer unavailable (source lot received date missing); legacy sales snapshot retained as estimate; source_writeoff_refs=${references}`)},
  cost_fixed_at = null
where id = ${sqlUuid(assignment.saleItemId)};

select set_config('app.mystery_commit', 'off', true);`;
  });
  return `begin;
select set_config('app.mystery_reserve', 'on', true);
${blocks.join("\n")}
commit;\n`;
}

async function legacyMysteryAssignments(client, model, productIds, saleIdByOrder) {
  const sales = await queryRows(client, "sales", "id,order_no,sold_at");
  const saleItems = await queryRows(client, "sale_items", "id,sale_id,product_id,qty");
  const fulfillments = await queryRows(client, "mystery_fulfillments", "id,sale_item_id,state");
  const saleById = new Map(sales.map((row) => [row.id, row]));
  const itemsBySale = new Map();
  for (const item of saleItems) {
    if (!itemsBySale.has(item.sale_id)) itemsBySale.set(item.sale_id, []);
    itemsBySale.get(item.sale_id).push(item);
  }
  const fulfillmentBySaleItem = new Map(fulfillments.map((row) => [row.sale_item_id, row]));
  const productById = new Map([...productIds.entries()].map(([sku, id]) => [id, { sku, ...model.productSourceBySku.get(sku) }]));
  const assignments = [];
  for (const group of model.linkedMysteryGroups) {
    const saleId = saleIdByOrder.get(group.orderNo);
    const sourceSale = saleById.get(saleId);
    const mysteryItems = (itemsBySale.get(saleId) ?? []).filter((item) => legacyMysterySkus.has(productById.get(item.product_id)?.sku));
    if (!sourceSale || !mysteryItems.length) throw new Error(`${group.orderNo}: missing imported legacy Mystery sale item.`);
    const components = group.components.map((component) => {
      const productId = productIds.get(component.sourceSku);
      if (!productId) throw new Error(`${group.orderNo}: unknown Mystery component SKU ${component.sourceSku}.`);
      return { ...component, productId, game_code: productById.get(productId)?.game_code ?? null };
    });
    const total = components.reduce((value, component) => value + component.qty, 0);
    const expected = mysteryItems.reduce((value, item) => value + Number(item.qty) * 5, 0);
    if (total !== expected) throw new Error(`${group.orderNo}: source packs=${total}, expected=${expected}.`);
    const allocations = mysteryItems.length === 1
      ? [{ item: mysteryItems[0], components }]
      : mysteryItems.map((item) => {
        const game = productById.get(item.product_id)?.game_code;
        return { item, components: components.filter((component) => component.game_code === game) };
      });
    const allocatedRows = allocations.flatMap((allocation) => allocation.components.map((component) => component.writeoffNo)).sort();
    if (allocatedRows.join("|") !== components.map((component) => component.writeoffNo).sort().join("|")) {
      throw new Error(`${group.orderNo}: multi-line Mystery components cannot be allocated by source game.`);
    }
    for (const allocation of allocations) {
      const fulfillment = fulfillmentBySaleItem.get(allocation.item.id);
      const expectedQty = Number(allocation.item.qty) * 5;
      const allocatedQty = allocation.components.reduce((value, component) => value + component.qty, 0);
      if (!fulfillment || fulfillment.state !== "needs_assembly") throw new Error(`${group.orderNo}: fulfillment is not ready for legacy reconstruction.`);
      if (allocatedQty !== expectedQty) throw new Error(`${group.orderNo}: allocation packs=${allocatedQty}, expected=${expectedQty}.`);
      assignments.push({
        orderNo: group.orderNo, saleSku: productById.get(allocation.item.product_id)?.sku, saleId,
        saleItemId: allocation.item.id, fulfillmentId: fulfillment.id, soldAt: sourceSale.sold_at,
        expectedQty, components: allocation.components, approximation: group.approximation,
        sourcePrroUnit: model.sales.find((entry) => entry.sourceOrderNo === group.orderNo)?.items.find((item) => item.sourceSku === productById.get(allocation.item.product_id)?.sku)?.legacy_source_prro_unit,
        sourceMgmtUnit: model.sales.find((entry) => entry.sourceOrderNo === group.orderNo)?.items.find((item) => item.sourceSku === productById.get(allocation.item.product_id)?.sku)?.legacy_source_mgmt_unit
      });
      const current = assignments.at(-1);
      if (current.sourcePrroUnit === null || current.sourcePrroUnit === undefined || current.sourceMgmtUnit === null || current.sourceMgmtUnit === undefined) {
        throw new Error(`${group.orderNo}: reconstructed Mystery item has no frozen source cost snapshot.`);
      }
    }
  }
  return assignments;
}

async function applyModel(model) {
  if (!acknowledgeAssumptions) throw new Error("Apply mode requires --acknowledge-legacy-assumptions after reviewing dry-run output.");
  const client = await supabaseClient();
  await assertBatchAbsent(client);

  await upsertChunks(client, "app_config", model.legacyConfig, "key,effective_from");
  for (const [table, rows] of Object.entries(model.lookupRows)) await upsertChunks(client, table, rows, "code");
  await upsertChunks(client, "consumables", model.consumables, "name");
  await upsertChunks(client, "products", model.products, "sku");
  const productRows = await queryRows(client, "products", "id,sku");
  const productIds = new Map(productRows.map((row) => [row.sku, row.id]));
  const priceRows = model.productPrices.map((row) => ({ ...row, product_id: productIds.get(row.sku) })).map((row) => {
    delete row.sku;
    return row;
  });
  if (priceRows.some((row) => !row.product_id)) throw new Error("Product price contains an unknown SKU.");
  await upsertChunks(client, "product_prices", priceRows, "product_id,effective_from");

  // Legacy MIX products are historical only. Their type is necessary to exclude
  // virtual box SKU from stock after import; NCRM-05's future same-game guard is
  // deliberately bypassed only inside the local legacy SQL reconstruction below.
  const legacyTypes = [...legacyMysterySkus].map((sku) => ({
    product_id: productIds.get(sku), expected_pack_count: 5, has_holo: true, holo_cost: 75, provisional_unit_cost: 450
  }));
  if (legacyTypes.some((row) => !row.product_id)) throw new Error("Legacy Mystery SKU missing from RRC product catalogue.");
  await upsertChunks(client, "mystery_box_types", legacyTypes, "product_id");

  const regions = await rowsByCode(client, "supplier_regions");
  for (const entry of model.purchases) {
    const purchase = { id: stableUuid("purchase", entry.lotCode), ...entry.purchase, region_id: regions.get(entry.purchase.region_code)?.id };
    delete purchase.region_code;
    if (!purchase.region_id) throw new Error(`Unknown supplier region for ${entry.lotCode}.`);
    const inserted = await client.from("purchases").insert(purchase).select("id").single();
    if (inserted.error) throw new Error(`purchase ${entry.lotCode}: ${inserted.error.message}`);
    const lot = { id: stableUuid("purchase_lot", entry.lotCode), ...entry.lot, purchase_id: inserted.data.id, product_id: productIds.get(entry.lot.source_sku) };
    delete lot.source_sku;
    if (!lot.product_id) throw new Error(`Unknown purchase SKU for ${entry.lotCode}.`);
    const result = await client.from("purchase_lots").insert(lot);
    if (result.error) throw new Error(`purchase lot ${entry.lotCode}: ${result.error.message}`);
  }

  const [channels, paymentTypes, paymentStatuses, orderStatuses, postMethods, consumableRows] = await Promise.all([
    rowsByCode(client, "sale_channels"), rowsByCode(client, "payment_types"), rowsByCode(client, "payment_statuses"),
    rowsByCode(client, "order_statuses"), rowsByCode(client, "post_methods"), queryRows(client, "consumables", "id,name")
  ]);
  const consumableByName = new Map(consumableRows.map((row) => [row.name, row.id]));
  const saleIdByOrder = new Map();
  for (const entry of model.sales) {
    const sale = {
      id: stableUuid("sale", entry.sourceOrderNo), ...entry.sale, channel_id: channels.get(entry.sale.channel_name)?.id, payment_type_id: paymentTypes.get(entry.sale.payment_type_name)?.id,
      payment_status_id: paymentStatuses.get(entry.sale.payment_status_name)?.id, order_status_id: orderStatuses.get(entry.sale.order_status_name)?.id,
      post_method_id: entry.sale.post_method_name ? postMethods.get(entry.sale.post_method_name)?.id : null,
      packaging_type_id: entry.sale.packaging_name ? consumableByName.get(entry.sale.packaging_name) ?? null : null
    };
    for (const key of ["channel_id", "payment_type_id", "payment_status_id", "order_status_id"]) if (!sale[key]) throw new Error(`Missing ${key} for ${entry.sourceOrderNo}.`);
    for (const key of ["channel_name", "payment_type_name", "payment_status_name", "order_status_name", "post_method_name", "packaging_name"]) delete sale[key];
    const inserted = await client.from("sales").insert(sale).select("id").single();
    if (inserted.error) throw new Error(`sale ${entry.sourceOrderNo}: ${inserted.error.message}`);
    saleIdByOrder.set(entry.sourceOrderNo, inserted.data.id);
    const items = entry.items.map((item, index) => {
      const { sourceSku, legacy_source_prro_unit, legacy_source_mgmt_unit, ...dbItem } = item;
      return { id: stableUuid("sale_item", entry.sourceOrderNo, index, sourceSku), ...dbItem, sale_id: inserted.data.id, product_id: productIds.get(sourceSku) };
    });
    if (items.some((item) => !item.product_id)) throw new Error(`Unknown product in sale ${entry.sourceOrderNo}.`);
    const result = await client.from("sale_items").insert(items);
    if (result.error) throw new Error(`sale items ${entry.sourceOrderNo}: ${result.error.message}`);
  }

  for (const entry of model.normalWriteoffs) {
    const writeoff = { id: stableUuid("writeoff", entry.writeoff.writeoff_no), ...entry.writeoff };
    const inserted = await client.from("writeoffs").insert(writeoff).select("id").single();
    if (inserted.error) throw new Error(`writeoff ${entry.writeoff.writeoff_no}: ${inserted.error.message}`);
    const item = { id: stableUuid("writeoff_item", entry.writeoff.writeoff_no), ...entry.item, writeoff_id: inserted.data.id, product_id: productIds.get(entry.item.sourceSku) };
    delete item.sourceSku;
    if (!item.product_id) throw new Error(`Unknown writeoff SKU for ${entry.writeoff.writeoff_no}.`);
    const result = await client.from("writeoff_items").insert(item);
    if (result.error) throw new Error(`writeoff item ${entry.writeoff.writeoff_no}: ${result.error.message}`);
  }

  const assignments = await legacyMysteryAssignments(client, model, productIds, saleIdByOrder);
  await runDockerPsql(buildLegacyMysterySql(assignments));
  const archived = await client.from("products").update({ is_active: false, archived_at: snapshotTimestamp }).in("sku", [...legacyMysterySkus]);
  if (archived.error) throw new Error(`Archive legacy Mystery SKUs failed: ${archived.error.message}`);

  const [mysteryContents, mysteryWriteoffs] = await Promise.all([
    client.from("mystery_contents").select("id", { count: "exact", head: true }),
    client.from("writeoffs").select("id", { count: "exact", head: true }).like("note", `%imported_batch=${batch}%`).eq("type", "MBOX")
  ]);
  if (mysteryContents.error || mysteryWriteoffs.error) throw new Error(`Post-import Mystery verification failed: ${mysteryContents.error?.message ?? mysteryWriteoffs.error?.message}`);
  return { applied: true, batch, counts: model.report.counts, mystery: { assignments: assignments.length, mbox_documents: mysteryWriteoffs.count ?? 0, contents_total: mysteryContents.count ?? 0 } };
}

const model = await buildModel();
console.log(JSON.stringify({ mode: applyMode ? "apply" : "dry-run", dataDir, batch, ...model.report }, null, 2));
if (applyMode) console.log(JSON.stringify(await applyModel(model), null, 2));
else console.log("dry_run=ok writes=0");
