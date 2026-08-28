import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const here=path.dirname(fileURLToPath(import.meta.url));
const html=fs.readFileSync(path.resolve(here,"../booster-dashboard.html"),"utf8");
const apiCode=fs.readFileSync(path.resolve(here,"../../3d-print/apps-script-3dp-api/Code.gs"),"utf8");
const inline=[...html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/gi)].map((match)=>match[1]).filter((source)=>source.trim());
inline.forEach((source,index)=>new vm.Script(source,{filename:"booster-dashboard.inline-"+index+".js"}));
const apiContext=vm.createContext({});
vm.runInContext(apiCode,apiContext,{filename:"Code.gs"});
const apiArticleSuggestions=JSON.parse(vm.runInContext("JSON.stringify(NOMENCLATURE_DRAFT_SUGGESTIONS_3DP)",apiContext));
const dashboardArticleSource=html.match(/const THREE_DP_ARTICLE_CATEGORIES = Object\.freeze\(\[[\s\S]*?\n\]\);/)?.[0];
assert.ok(dashboardArticleSource,'dashboard article category list is present');
const dashboardArticleExpression=dashboardArticleSource.replace(/^const THREE_DP_ARTICLE_CATEGORIES = /,'').replace(/;$/,'');
const dashboardArticleCategories=JSON.parse(vm.runInNewContext('JSON.stringify('+dashboardArticleExpression+')'));
assert.deepEqual(dashboardArticleCategories.map((category)=>category[0]),Object.keys(apiArticleSuggestions),'dashboard categories stay aligned with the API mapping');
dashboardArticleCategories.forEach(([name,prefix,digits])=>{
  assert.equal(prefix,apiArticleSuggestions[name].prefix,`${name} keeps the API prefix in its dropdown label`);
  assert.equal(digits,apiArticleSuggestions[name].category_digits,`${name} keeps the API category digits in its dropdown label`);
});
assert.match(html,/call3dp\('3dp_bootstrap'/);
assert.doesNotMatch(html,/call3dp\('3dp_information_bootstrap'/);
assert.match(html,/loadThreeDpInformationSection\('sales'\)/);
assert.match(html,/actions=\{sales:'3dp_sales',payouts:'3dp_payouts',plyushky:'3dp_plyushky'\}/);
assert.match(html,/three-dp-info-block/);
assert.match(html,/Завантаження чернетки…/);
assert.match(html,/three-dp-draft-status loading/);
assert.doesNotMatch(html,/Promise\.all\(\[call3dp\('3dp_overview'/);
assert.match(html,/action:'add_sku'/);
assert.match(html,/catalog_kind:isAccessory\?'accessory':'tcg'/,'new SKU creation distinguishes TCG goods from accessories');
assert.match(html,/Мова \(за наявності\)/,'accessory creation does not require a language');
assert.match(html,/Лінійка \/ серія \(за наявності\)/,'accessory creation does not require a set');
assert.match(html,/action:'update_rrp_batch'/);
assert.match(html,/Пакетна зміна РРЦ/);
assert.match(html,/id="applyRrpChangesButton"/);
assert.match(html,/Нова РРЦ, грн/);
assert.match(html,/Поточна собівартість/,'the SKU table exposes the current FIFO cost column');
assert.match(html,/r\.current_cost != null \? fmt\(r\.current_cost\) : '—'/,'an SKU without any calculable cost renders a dash');
assert.match(html,/У вкладці 3D/);
assert.match(html,/Частковий результат: 3D-P рядок/);
assert.match(html,/Синхронізувати артикул \/ РРЦ з CRM/);
assert.match(html,/action:'sync_3dp_catalog_rrp'/);
assert.match(html,/previous_sku:oldSku,sku:result\.sku/,
  'an active 3D-P article rename immediately requests the matching CRM catalogue rename');
assert.match(html,/threeDpCrmRenameCandidates_\(crmRows,row\['Назва виробу'\],row\.SKU\)/,
  'the sync button reconciles an already-split SKU by exact 3D product name');
assert.match(html,/previous_sku:previous\.sku,sku:row\.SKU/,
  'the reconciliation path renames the existing CRM row instead of creating a duplicate');
assert.match(html,/function threeDpDraftRows_\(\)[\s\S]*?threeDpStatus\(row\)==='Чернетка'[\s\S]*?Number\(b\.row_number\|\|0\)-Number\(a\.row_number\|\|0\)/,
  'draft queue includes every draft newest first');
assert.match(html,/Механіка \/ категорія<select id="threeDpArticleType-/,
  'mechanic selection uses the requested native dropdown');
assert.match(html,/category\[0\]\+' · '\+category\[1\]\+'-…-'\+category\[2\]/,
  'every dropdown option keeps its complete prefix and category digits');
assert.doesNotMatch(html,/threeDpArticleCategories-|threeDpArticleType-[^\n]*type="search"/,
  'the temporary searchable datalist control is gone');
assert.match(html,/Вставити новий артикул<input id="threeDpArticleSku-/,
  'the owner pastes the complete article into one unrestricted field');
assert.doesNotMatch(html,/Mnemonic, 2–5 символів|previewThreeDpArticle|threeDpArticleMnemonic|maxlength="5"/,
  'the mnemonic generator, truncation, and preview helper are removed');
assert.match(html,/sku=String\(skuInput&&skuInput\.value\|\|''\)\.trim\(\)/,
  'the complete pasted article is read directly from the owner input');
assert.doesNotMatch(html,/skuInput[^;]*toUpperCase\(|threeDpArticleSku-[^\n]*maxlength=/,
  'the dashboard does not rewrite, uppercase, or truncate the pasted article');
assert.match(html,/action:'3dp_nomenclature_assign_sku',draft_sku:oldSku,expected_draft_sku:oldSku,sku:sku/,
  'draft assignment and active rename reuse the specialized action with the current key repeated explicitly');
assert.match(html,/catch\(e\)\{threeDpUi\.product\.msg=\{text:e\.message,kind:'error'\}/,
  'the dashboard shows the API history-blocking message without replacing it');
assert.match(html,/function threeDpActiveSkus\(\) \{ return threeDpState\.skus\.filter\(function\(r\) \{ return threeDpStatus\(r\) === 'Активний'; \}\); \}/,
  'drafts never enter active calculator controls');
assert.match(html,/Фурнітура 3D-друку, до 10/);
assert.match(html,/Додати фурнітуру/);
assert.match(html,/Кожен рядок фурнітури прив’язується до конкретного 3D SKU/);
assert.match(html,/Компоненти \/ маркетингові подарунки, до 10/);
assert.match(html,/Додати компонент/);
assert.match(html,/Прив’язка до рядка \(необов’язково\)/);
assert.match(html,/target_row:Number\(editValue\(id\+'Target'\)\)\|\|0/);
assert.match(html,/Порожня прив’язка = подарунок на все замовлення й «Маркетинг»/);
assert.match(html,/Оплата клієнтом не змінюється/);
assert.match(html,/order_component_catalog/);
assert.match(html,/Список включає CRM-SKU, розхідники й наявні 3D-вироби/);
assert.match(html,/function loadOrderComponentCatalog_\(\)/,'the component catalogue has a shared background loader');
assert.match(html,/async function openEditRow[\s\S]*const catalogLoad=loadOrderComponentCatalog_\(\);[\s\S]*await call\('order_edit_context'/,'order fields render from their own request while the catalogue loads separately');
assert.doesNotMatch(html,/Promise\.all\(requests\)/,'the editor no longer blocks on the remote component catalogue');
assert.match(html,/addEditComponentButton[\s\S]*componentCatalogLoaded/,'component controls stay disabled until their catalogue is ready');
assert.match(html,/if\(key==='Дата'\)return threeDpEsc\(String\(value\)\.slice\(0,10\)\)/,"3D Sales renders a date without the midnight timestamp");
assert.match(html,/result\.retry_action==='retry_3dp_sync'/,"the sync-only recovery button appears only for a remote frozen-row sync failure");
assert.match(html,/той самий ID допише лише незавершене/,"a partial component writer tells the owner to resume the idempotent order update");
assert.match(html,/qualified:'true',limit:500/);
assert.match(html,/clientSortState = \{ field: 'spend_60d', dir: 'desc' \}/);
assert.match(html,/loadThreeDpStockOverlay_/);
assert.match(html,/loadAccountingThreeDpStockOverlay_/);
assert.match(html,/call3dp\('3dp_skus'\)/);
assert.match(html,/Значення CRM не підставляються як начебто актуальні/);
assert.match(html,/id="saveSaleButton"/);
assert.match(html,/button\.disabled=true;button\.textContent='Зберігаю…'/);
assert.match(html,/\.writeoff-line \.line-item-grid \{ grid-template-columns:repeat\(2,minmax\(160px,1fr\)\); \}/,'write-off line details use a separate row below the full-width SKU picker');
assert.match(html,/div\.className = 'line-item' \+ \(kind === 'writeoff' \? ' writeoff-line' : ''\);/,'the write-off-only layout is applied without changing sales or mystery lines');
assert.match(html,/Облік 3D-рядків/);
assert.match(html,/Галочка завжди під твоїм контролем/);
assert.match(html,/зніми галочку для Маркетингу/);
assert.match(html,/components:components,fixtures:fixtures,three_dp_lines:threeDpLines/);
assert.match(html,/id="saveEditRowButton"/);
assert.match(html,/accountingState\.editSaving=true/);
assert.match(html,/action:'3dp_manufacture_batch'/);
assert.match(html,/Виготовити партію/);
assert.match(html,/action:'add_consumable_purchase'/);
assert.match(html,/action:'update_consumable_purchase'/);
assert.match(html,/Закупки розхідників і фурнітури/);
assert.match(html,/Внутрішня міграція товару/);
assert.match(html,/<details class="write-section wide migration-details" id="inventoryMigrationDetails" ontoggle="toggleInventoryMigrationWorkspace\(this\)">/,'inventory migration is a collapsed native disclosure by default');
assert.match(html,/function toggleInventoryMigrationWorkspace\(details\)/,'opening the migration disclosure loads its FIFO context on demand');
assert.doesNotMatch(html,/writeoffAddItem\(\);\s*loadInventoryMigrationContext\(\);/,'migration FIFO context is not fetched while the disclosure remains closed');
assert.match(html,/Бокс → поштучні паки/);
assert.match(html,/Поштучні паки → Outlet Mix/);
assert.match(html,/action:'inventory_migration'/);
assert.match(html,/call\('inventory_migration_context'\)/);
assert.match(html,/Історичні «Закупки» не редагуються/);
assert.match(html,/requestIds: \{ box: '', outlet: '' \}/,'migration retries retain one request id per form');
assert.match(html,/const PURCHASE_BATCH_LIMIT = 10;/,'purchase batch selection limit is ten lots');
assert.match(html,/selectedPurchaseLots\)\.length >= PURCHASE_BATCH_LIMIT/,'purchase selection enforces the shared batch limit');
assert.match(html,/rows\.length > PURCHASE_BATCH_LIMIT/,'purchase submission enforces the shared batch limit');
assert.match(html,/call\(cfg\.action, \{ limit: 20, include_all_open:'true', kind:cfg\.kind \|\| '' \}\)/,'the purchases tab explicitly requests every open purchase while sales pass their order kind');
assert.match(html,/showRecTab\(\\'regular\\',this\)">Звичайні замовлення</,'accounting separates regular orders');
assert.match(html,/showRecTab\(\\'preorders\\',this\)">Передзамовлення</,'accounting separates preorders');
assert.match(html,/preorder_reserved/,'stock UI exposes preorder reservations');
assert.match(html,/preorder_deficit/,'stock UI exposes preorder deficit without a negative available value');
assert.match(html,/Відновити тільки 3D-P після помилки/);
assert.match(html,/action:'retry_3dp_sync'/);
assert.match(html,/retry_3dp_sync'[\s\S]*clearPendingOrderEdit_\(accountingState\.editRequestId\)/);
assert.match(html,/Компоненти й фурнітура повторно не записувались/);
assert.match(html,/ORDER_EDIT_PENDING_KEY/);
assert.match(html,/pendingOrderEditRequestId_/);
assert.match(html,/showPageByName\('settings'\)/,'settings is reachable from the logo gear');
assert.match(html,/showPage\('updates'\)/,'updates and migration has a dedicated navigation item');
assert.match(html,/Налаштування/,'settings page is present');
assert.match(html,/Оновлення та міграція/,'updates and migration page is present');
assert.match(html,/action:'crm_maintenance'/,'settings commands use the CRM maintenance API bridge');
assert.equal((html.match(/when:'Коли /g)||[]).length,5,'every CRM maintenance command explains when the owner should use it');
assert.equal((html.match(/result:'/g)||[]).length,5,'every CRM maintenance command explains the expected outcome');
assert.match(html,/Якщо потрібний товар уже видно — натискати не треба/,'catalog guidance says when no action is needed');
assert.match(html,/Регулярно натискати цю кнопку не потрібно/,'formula automation guidance is explicit about one-off use');
assert.doesNotMatch(html,/showPage\('testcleanup'\)/,'test cleanup no longer has a sidebar page');
assert.match(html,/action:'add_expense'/,'accounting exposes the expense entry API');
assert.match(html,/action: 'test_order_cleanup', confirm: 'CLEAN TEST ORDERS'/);
assert.match(html,/тестове замовлення/);
assert.match(html,/Копіювати звіт/);
assert.match(html,/TEST_ORDER_CLEANUP_REPORT_KEY/);
assert.match(html,/window\.confirm\(message\)/);
assert.match(html,/mtd\.current = ms\.month_to_date/,'month card uses the monthly-summary source');
assert.match(html,/mtd\.previous = ms\.previous_month_to_date/,'month comparison uses the monthly-summary source');
const overviewStart=html.indexOf('async function loadOverview()');
const overviewSource=html.slice(overviewStart,html.indexOf('// ════════════ STOCK ════════════',overviewStart));
assert.match(overviewSource,/call\('overview_bootstrap'\)/,'overview loads critical data through one shared Apps Script bootstrap');
assert.match(overviewSource,/p_bootstrap\.then\(\(\) => call\('overview_secondary'\)\)/,
  'secondary overview data starts only after the critical response');
assert.doesNotMatch(overviewSource,/call\('(summary|orders|channel_stats|monthly_summary|sku_list|stock_alerts)'/,
  'overview no longer starts seven competing Apps Script requests');
assert.doesNotMatch(overviewSource,/status:'all', limit:500/,'overview does not fetch 500 orders to render an active-order preview');
assert.match(html,/function isPreorderOrder\(order\)[\s\S]*Передзамовлення/,'the overview has an explicit preorder classifier');
assert.match(html,/const overviewActiveOrders = activeOrders\.filter\(r => !isPreorderOrder\(r\)\)/,'the overview summary excludes preorders');
assert.match(html,/filter\(r => isActiveOrder\(r\) && !isPreorderOrder\(r\)\)/,'the active-orders overview preview excludes preorders');
assert.match(html,/!isPreorderOrder\(r\) && r\.order_status === 'Відправлено'/,'the attention tile explicitly excludes preorders');
assert.doesNotMatch(html,/Передзам\.: \$\{preorderOrders\.length\}/,'the overview no longer renders a preorder breakdown');
assert.match(html,/\['Нове', 'В обробці', 'Відправлено', 'Передзамовлення'\]/,'paid preorders remain visible in the dashboard active-order filter');
assert.match(html,/threeDpHours/);
assert.match(html,/Очікуємо відповідь CRM…/);
assert.match(html,/THREE_DP_SALES_COLUMNS/);
assert.match(html,/Колонки продажів/);
assert.match(html,/% прибутку Сергію/);
assert.match(html,/UA \+ замовлено \+ в дорозі \+ JP \+ виграно/,'asset scope describes confirmed ordered stock');
assert.match(html,/action:'3dp_payout_create'/);
assert.match(html,/action:'3dp_payout_mark_paid'/);
assert.match(html,/Позначити виплачено/);
assert.match(html,/threeDpUi\.info\.cost===option\[0\]\?' selected':''/);
assert.doesNotMatch(html,/id="mysteryItems"/);
console.log("Dashboard syntax and contract tests passed");
