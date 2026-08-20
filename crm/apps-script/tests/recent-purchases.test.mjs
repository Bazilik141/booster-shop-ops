import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, "../Code.gs"), "utf8");
const headers = [
  "ID партії", "ZenMarket Order №", "Трек-номер", "Дата доставки в Україну", "SKU", "Назва товару", "Формат", "Кількість одиниць",
  "Вартість лоту, грн", "Доставка / комісії по Японії, грн", "Доставка UA, грн", "Собівартість закупки партії / ПРРО",
  "Собівартість 1 од. / ПРРО", "Кредитне обслуговування", "Управлінська собівартість партії", "Управлінська собівартість 1 од.",
  "Статус", "Примітка", "ZenMarket URL", "Постачальник"
];

function purchase(lotId, orderRef, sku, trackNumber = "") {
  const row = Array(20).fill("");
  row[0] = lotId;
  row[1] = orderRef;
  row[2] = trackNumber;
  row[4] = sku;
  row[7] = 1;
  row[16] = "Замовлено";
  return row;
}

const parcelTrack = "LX328130128JP";
const parcelOld = purchase("LOT-0093", "yskh275", "PKM-JP-MSYM-BBX", parcelTrack);
const rows = [parcelOld, ...Array.from({ length: 21 }, (_, index) => purchase(
  "LOT-" + String(index + 1).padStart(4, "0"),
  "yskh" + String(279 + index),
  "SKU-" + String(index + 1)
))];
rows.slice(17, 22).forEach((row) => { row[2] = parcelTrack; });
const delivered = purchase("LOT-DELIVERED", "yskh999", "SKU-DELIVERED");
delivered[3] = "2026-08-18";
const stocked = purchase("LOT-STOCKED", "yskh998", "SKU-STOCKED");
stocked[16] = "На складі";
rows.push(delivered, stocked);
rows.push(purchase("LOT-0140", "1156931401", "PKM-JP-INFX-BBX"));
const values = [["Закупки"], headers, ...rows];
const purchases = {
  getLastRow: () => values.length,
  getLastColumn: () => 20,
  getRange: () => ({ getValues: () => values })
};
const crm = { getSheetByName: (name) => name === "Закупки" ? purchases : null };
const context = vm.createContext({ JSON, Math, Number, String, Boolean, Array, Object, RegExp, Date, Error, Set, isFinite, console });
vm.runInContext(code + "\nglobalThis.__test = { apiRecentPurchasesForUpdate_ };", context, { filename: "Code.gs" });
context._getCrmSs = () => crm;
context.getCurrencyRate_ = () => 1;

const result = context.__test.apiRecentPurchasesForUpdate_({ limit: 20 });
assert.equal(result.ok, true);
assert.equal(result.rows.length, 21, "the twenty recent lots retain the older sibling of a selected tracked parcel");
assert.equal(result.rows[0].lot_id, "LOT-0140", "the newest appended open purchase is returned first");
assert.equal(result.rows.some((row) => row.lot_id === "LOT-0140"), true);
assert.equal(result.rows.filter((row) => row.track_number === parcelTrack).length, 6,
  "a selected tracked parcel is returned as a complete six-lot group");
assert.equal(result.rows.some((row) => row.lot_id === "LOT-0093"), true,
  "the older sibling of the tracked parcel is not lost to the global recent limit");
assert.equal(result.rows.some((row) => row.lot_id === "LOT-0001"), false, "the oldest open purchase falls outside the recent limit");
assert.equal(result.rows.some((row) => row.lot_id === "LOT-DELIVERED"), false, "delivered lots stay out of the update list");
assert.equal(result.rows.some((row) => row.lot_id === "LOT-STOCKED"), false, "stocked lots stay out of the update list");

const allOpen = context.__test.apiRecentPurchasesForUpdate_({ limit: 20, include_all_open: "true" });
assert.equal(allOpen.rows.length, 23, "the accounting view can request every open purchase, not just the newest twenty");
assert.equal(allOpen.rows.some((row) => row.lot_id === "LOT-0001"), true, "an older untracked open lot remains visible in the accounting view");
assert.equal(allOpen.rows.some((row) => row.lot_id === "LOT-0093"), true, "the complete tracked parcel remains visible in the accounting view");
console.log("Recent purchases return the newest open lots first");
