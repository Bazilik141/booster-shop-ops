import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const dashboard = fs.readFileSync(path.resolve(here, "../booster-dashboard.html"), "utf8");

assert.match(dashboard, /id="threeDpSyncJournal"/);
assert.match(dashboard, /onclick="refreshThreeDpSyncJournal\(\)"/);
assert.match(dashboard, /function renderThreeDpSyncJournal\(\)/);
assert.match(dashboard, /call\('sync_journal',\{limit:20\}\)/);
assert.match(dashboard, /\['created','updated','noop'\]/);
assert.match(dashboard, /success\?'chip':'issue-tag'/);
assert.match(dashboard, /threeDpEsc\(row\.detail\)/);
assert.match(dashboard, /threeDpEsc\(row\.order_id\)/);
assert.match(dashboard, /threeDpEsc\(row\.timestamp_kyiv\)/);
assert.match(dashboard, /function migrateThreeDpLegacyModels\(\)/);
assert.match(dashboard, /threeDpLegacyModelLinks/);
assert.match(dashboard, /row && row\['РРЦ фактична, грн'\]/);
assert.match(dashboard, /row && row\['Ціна під викуп, грн'\]/);
assert.match(dashboard, /row && row\['Посилання на модель'\]/);
assert.match(dashboard, /Клас маржі до split/);
assert.match(dashboard, /\(РРЦ − собівартість Сергія\) ÷ РРЦ, до поділу 50\/50/);
assert.match(dashboard, /\['Q',rrp,'РРЦ фактична, грн'\]/);
assert.match(dashboard, /\['R',buyout,'Ціна під викуп, грн'\]/);
assert.match(dashboard, /\['S',model,'Посилання на модель'\]/);
assert.match(dashboard, /action:'3dp_nomenclature_owner_create'/);
assert.doesNotMatch(dashboard, /action:'3dp_append_row',sheet:'Номенклатура'/,
  "the dashboard must not bypass the atomic owner SKU creation action");
assert.match(dashboard, /Собівартість не вводиться тут: її розрахує калькулятор після першої партії/);
assert.match(dashboard, /function threeDpCrmSkuSnapshot\(sku\)/);
assert.match(dashboard, /action:'sync_3dp_catalog_rrp'/);
assert.match(dashboard, /expected_rrp:expectedRrp/);
assert.match(dashboard, /Синхронізувати артикул \/ РРЦ з CRM/);
assert.match(dashboard, /перейменовує точний history-free CRM SKU з тією самою назвою/);
assert.match(dashboard, /previous_sku:previous\.sku,sku:row\.SKU/);
assert.match(dashboard, /\['defect_rate',5,'Планований брак, частка','частка \(0\.1 = 10%\)'\]/);
assert.equal((dashboard.match(/range:'A1:C5'/g) || []).length, 1);
assert.doesNotMatch(dashboard, /range:'A1:C4'/);
assert.match(dashboard, /baseCost=material\+electricity\+amortization,cost=baseCost\*\(1\+defectRate\)/);
assert.match(dashboard, /Плановий брак/);
assert.match(dashboard, /<script src="\.\.\/3d-print\/shared\/print-time\.js"><\/script>/);
assert.match(dashboard, /function threeDpPrintTime\(value\)/);
assert.match(dashboard, /Сумарний час партії, год<input type="text" inputmode="decimal"/);
assert.match(dashboard, /Можна: 1:30, 1 год 30 хв або 1,5/);
assert.match(dashboard, /threeDpPrintTimeDisplay\(metrics\.time\)/);
assert.match(dashboard, /threeDpPrintTimeWarning\(metrics\.time\)/);
assert.match(dashboard, /f==='total_print_time_h'\?\(printTime\.ok && !printTime\.blank\?printTime\.hours:NaN\)/);
assert.doesNotMatch(dashboard, /function threeDpLatest\(/);
assert.doesNotMatch(dashboard, /function saveThreeDpModel\(/);

const validatorSource = dashboard.match(/function threeDpSkuTypeError\(sku,type\) \{[^\n]+\}/)?.[0];
assert.ok(validatorSource, "threeDpSkuTypeError must remain a standalone validator");
assert.match(validatorSource, /\^\(BR\|FIG\|ACC-3D\)-\[A-Z0-9\]\{2,5\}-\\d\{3\}\(\?:-\[A-Z0-9\]\{1,5\}\)\*\$/,
  "dashboard validator must use the rev. 9 suffix grammar shared with the 3D-P API");
assert.match(dashboard, /const sku=String\(\(threeDpInput\('threeDpProductSku'\) \|\| \{\}\)\.value \|\| ''\)\.trim\(\)\.toUpperCase\(\),invalid=threeDpSkuTypeError\(sku,type\);/,
  "create flow must normalize a lowercase suffix before validation");
const validatorContext = vm.createContext({ String, RegExp });
vm.runInContext(`${validatorSource}\nglobalThis.validate = threeDpSkuTypeError;`, validatorContext);
[
  ["ACC-3D-PKM-130", "Функціональний аксесуар", true],
  ["ACC-3D-DITTO-410", "Функціональний аксесуар", true],
  ["BR-CHARM-100", "Брелок", true],
  ["FIG-CHARM-001", "Фігурка", true],
  ["ACC-3D-ONIX-110-21", "Функціональний аксесуар", true],
  ["ACC-3D-ONIX-110-21-BLK", "Функціональний аксесуар", true],
  ["FIG-ONIX-500-15-WHT", "Фігурка", true],
  ["ACC-3D-410", "Функціональний аксесуар", false],
  ["ACC-3D-ONIX-110-", "Функціональний аксесуар", false],
  ["ACC-3D-ONIX-110-TOOLONG", "Функціональний аксесуар", false],
  ["ACC-001", "Функціональний аксесуар", false],
  ["PKM-JP-EXSD-STD-GRS", "Функціональний аксесуар", false],
].forEach(([sku, type, accepted]) => {
  assert.equal(validatorContext.validate(sku, type) === "", accepted, `dashboard validation: ${sku}`);
});
assert.match(validatorContext.validate("ACC-3D-410", "Функціональний аксесуар"), /ACC-3D-ONIX-110-21-BLK/);
assert.match(validatorContext.validate("ACC-3D-DITTO-410", "Брелок"), /Префікс SKU/);

console.log("3dp-sync-journal dashboard static tests passed");
