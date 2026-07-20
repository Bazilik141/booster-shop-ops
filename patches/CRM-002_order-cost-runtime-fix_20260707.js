function onOpen() {
SpreadsheetApp.getUi()
.createMenu('Booster CRM')
.addItem('Додати продаж', 'addSale')
.addItem('Додати закупку', 'addPurchase')
.addItem('Оновити закупку', 'updatePurchase')
.addItem('Оновити продаж', 'updateSaleStatus')

.addItem('Додати списання', 'addWriteOff')
.addItem('Додати витрату', 'addExpense')
.addSeparator()
.addItem('Очистити форму продажу', 'clearSaleForm')
.addItem('Очистити форму закупки', 'clearPurchaseForm')
.addItem('Очистити форму оновлення закупки', 'clearUpdatePurchaseForm')
.addItem('Очистити форму оновлення продажу', 'clearSaleUpdateForm')
.addItem('Очистити форму списання', 'clearWriteOffForm')
.addItem('Очистити форму витрат', 'clearExpenseForm')
.addToUi();
}

function addSale() {
resetMemoForMutation_(); const ss = SpreadsheetApp.getActive();
const formSheet = ss.getSheetByName('Внести_продаж');
const sales = ss.getSheetByName('Продажі');
const form = readFormRange_(formSheet, 'A4:B20');
const itemRows = formSheet.getRange('A21:E30').getValues();
const items = itemRows
.filter(row => row[0] && num_(row[1]) > 0)
.map(row => ({ sku: parseSku_(row[0]), qty: num_(row[1]), price: num_(row[2]), note: row[4] || '' }));

const mysteryQty = items
.filter(item => isMysteryBoxSale_(item.sku, ''))
.reduce((sum, item) => sum + item.qty, 0);

const componentRows = formSheet.getRange('A36:C45').getValues();
const mysteryComponents = componentRows
.filter(row => row[0] && num_(row[1]) > 0)
.map(row => ({ sku: parseSku_(row[0]), qty: num_(row[1]), note: row[2] || '' }));

if (!items.length) {
SpreadsheetApp.getUi().alert('Додай хоча б один SKU у таблицю позицій.');
return;
}

if (mysteryQty === 0 && mysteryComponents.length) {
SpreadsheetApp.getUi().alert('Компоненти містері бокса заповнюються тільки тоді, коли в продажі є SKU містері бокса.');
return;
}

if (mysteryQty > 0) {
const expectedQty = mysteryQty * 5;
const componentQty = mysteryComponents.reduce((sum, item) => sum + item.qty, 0);
if (!mysteryComponents.length || Math.abs(componentQty - expectedQty) > 0.0001) {
SpreadsheetApp.getUi().alert('Для містері бокса потрібно списати ' + expectedQty + ' бустерів. Зараз вказано: ' + componentQty + '.');
return;
}
}

const source = form['Джерело'] || 'Вручну';
const paymentType = form['Тип оплати'] || 'За реквізитами';
const packagingType = form['Паковання'] || '';
const operation = form['Номер замовлення / операції (опц.)'] || generateOperationNumber(source, paymentType);
const gross = items.reduce((sum, item) => sum + item.qty * item.price, 0);
const checkDiscount = Math.min(num_(form['Знижка на чек']), gross);
const packaging = num_(form['Вартість паковання, грн']);
const shopDelivery = num_(form['Доставка за рахунок магазину']);
if (packagingType && packaging <= 0 && String(packagingType).trim() !== 'Інше') {
SpreadsheetApp.getUi().alert('Для обраного паковання не підтягнулась вартість. Якщо це Інше, сума 0 грн дозволена.');
return;
}
const orderNote = [form['Примітка'], packagingType ? 'Паковання: ' + packagingType : ''].filter(Boolean).join('; ');
const firstRow = nextEmptyRow_(sales, 1, 3, 501);
const costRunState = {};
items.forEach(function(item, index) {
const row = firstRow + index;
const weight = gross ? (item.qty * item.price) / gross : 0;
const lineDiscount = round2_(checkDiscount * weight);
const linePackaging = round2_(packaging * weight);
const lineDelivery = round2_(shopDelivery * weight);
const note = [orderNote, item.note].filter(Boolean).join('; ');

sales.getRange(row, 1, 1, 6).setValues([[operation, source, form['Дата продажу'], form['Телефон клієнта'], form['ПІБ клієнта'], item.sku]]);
sales.getRange(row, 8, 1, 3).setValues([[item.qty, item.price, lineDiscount]]);
sales.getRange(row, 16).setValue(linePackaging);
sales.getRange(row, 20).setValue(lineDelivery);
sales.getRange(row, 23, 1, 6).setValues([[form['Статус оплати'], form['Статус замовлення'], form['Пошта'], form['ТТН'], note, paymentType]]);
sales.getRange(row, 29).setValue(packagingType); fixSaleCostForRow_(ss, row, costRunState, { clearPending: true });
});

if (mysteryComponents.length) {
addMysteryBoxWriteOffs_(ss, mysteryComponents, form['Дата продажу'], operation);
}

updateSkuCurrentCost_(ss); invalidateDoGetCache_(); clearInputForm('Внести_продаж');
SpreadsheetApp.getUi().alert('Продаж додано: ' + operation + ' / позицій: ' + items.length);
}

function addPurchase() {
resetMemoForMutation_(); const ss = SpreadsheetApp.getActive();
const formSheet = ss.getSheetByName('Внести_закупку');
const purchases = ss.getSheetByName('Закупки');
const form = readFormRange_(formSheet, 'A4:B9');
const order = String(form['ZenMarket Order №'] || '').trim(); const orderUrl = String(form['ZenMarket URL'] || '').trim();
const totalCost = num_(form['Загальна вартість лоту, грн']);
const japanFeesJpy = num_(form['Доставка / комісії по Японії, єни (JPY)']);
const japanFees = japanFeesJpy > 0 ? round2_(japanFeesJpy / getCurrencyRate_('JPY')) : 0; const status = form['Статус'] || 'Виграно';
const items = formSheet.getRange('A12:E14').getValues()
.filter(row => row[0] && num_(row[1]) > 0)
.map(row => ({ sku: parseSku_(row[0]), qty: num_(row[1]), cost: num_(row[2]), manualCost: row[3], note: row[4] || '' }));

if (!order || isBlank_(form['Загальна вартість лоту, грн']) || !items.length) { SpreadsheetApp.getUi().alert('Заповни ZenMarket Order №, загальну вартість лоту і хоча б один SKU з кількістю.'); return; }

const totalQty = items.reduce((sum, item) => sum + item.qty, 0);
if (totalQty <= 0) { SpreadsheetApp.getUi().alert('Кількість по позиціях має бути більше нуля.'); return; }
const lineCostTotal = round2_(items.reduce((sum, item) => sum + item.cost, 0));
if (items.some(function(item) { return item.cost <= 0; }) || Math.abs(lineCostTotal - totalCost) > 0.05) {
SpreadsheetApp.getUi().alert('Перевір C12:C14: сума вартості рядків має дорівнювати загальній вартості лоту. Зараз: ' + lineCostTotal + ' грн / лот: ' + totalCost + ' грн.');
return;
}

const firstRow = nextEmptyRow_(purchases, 1, 3, 301);
const lotIds = generateLotIds_(items.length);
let allocatedJapanFees = 0;
const hasManualLineCosts = items.some(function(item) { return !isBlank_(item.manualCost); });

items.forEach(function(item, index) {
const row = firstRow + index;
const lineCost = round2_(item.cost);
const lineJapanFees = japanFees > 0 ? (index === items.length - 1 ? round2_(japanFees - allocatedJapanFees) : round2_(japanFees * item.qty / totalQty)) : '';
if (japanFees > 0) allocatedJapanFees = round2_(allocatedJapanFees + lineJapanFees);
const costNote = hasManualLineCosts ? 'Вартість рядків: авто/ручне коригування з форми' : (items.length > 1 ? 'Вартість лоту розподілена пропорційно кількості' : '');
const note = [form['Примітка'], item.note, costNote, items.length > 1 && japanFees > 0 ? 'JP доставка/комісії в JPY конвертовані в грн і розподілені пропорційно кількості' : ''].filter(Boolean).join('; ');

purchases.getRange(row, 1, 1, 5).setValues([[lotIds[index], order, '', '', item.sku]]);
purchases.getRange(row, 8, 1, 4).setValues([[item.qty, lineCost, lineJapanFees, '']]);
purchases.getRange(row, 17, 1, 3).setValues([[status, note, orderUrl]]);
});

invalidateDoGetCache_(); clearInputForm('Внести_закупку'); SpreadsheetApp.getUi().alert('Закупку додано: ' + order + ' / позицій: ' + items.length);
}


function updatePurchase() {
resetMemoForMutation_(); const ss = SpreadsheetApp.getActive();
const formSheet = ss.getSheetByName('Оновити_закупку');
const form = readForm_('Оновити_закупку');
const purchases = ss.getSheetByName('Закупки');
const lotRows = formSheet.getRange('B4:C11').getValues();
const selectedLots = lotRows.map(function(row) {
const parts = String(row[0] || '').split('|').map(function(part) { return part.trim(); });
if (!parts[0]) return null;
const lot = /^LOT-[0-9]+$/i.test(parts[0])
? { lotId: parts[0], order: parts[1] || '', sku: parts[2] || '' }
: { lotId: '', order: parts[0], sku: parseSku_(parts[1] || '') };
lot.japanJpy = row[1];
return lot;
}).filter(Boolean);
const manualOrder = String(form['ZenMarket Order № / список'] || form['ZenMarket Order №'] || '').trim();
const manualSku = parseSku_(form['SKU (опц.)'] || '');
if (!selectedLots.length && !manualOrder) {
SpreadsheetApp.getUi().alert('Обери хоча б один лот у B4:B11 або введи ZenMarket Order №.');
return;
}

const regularFields = [['Трек-номер', 3], ['Дата доставки в Україну', 4], ['Статус', 17]];
const sharedJpyFields = [['Доставка в Україну, JPY', 11]];
const hasJapanLotFees = selectedLots.some(function(lot) { return !isBlank_(lot.japanJpy); });
const hasAnyUpdate = regularFields.some(item => !isBlank_(form[item[0]]))
|| sharedJpyFields.some(item => !isBlank_(form[item[0]]))
|| hasJapanLotFees
|| !isBlank_(form['Примітка']);

if (!hasAnyUpdate) {
SpreadsheetApp.getUi().alert('Заповни хоча б одне поле для оновлення.');
return;
}

const lastRow = purchases.getLastRow();
const data = purchases.getRange(3, 1, Math.max(lastRow - 2, 1), 18).getValues();
const matchingRows = [];
const seenRows = {};
const manualOrders = manualOrder.split(/[\n,;]+/).map(function(item) { return item.trim(); }).filter(Boolean);

data.forEach(function(values, index) {
const row = index + 3;
const rowLot = String(values[0] || '').trim();
const rowOrder = String(values[1] || '').trim();
const rowOrderBase = rowOrder.replace(/\s*\(.+\)\s*$/, '');
const rowSku = String(values[4] || '').trim();
let selectedLot = null;
const selectionMatch = selectedLots.some(function(lot) {
const lotMatch = lot.lotId && rowLot === lot.lotId;
const orderMatch = lot.order && (rowOrder === lot.order || rowOrderBase === lot.order);
const skuMatch = !lot.sku || rowSku === lot.sku;
if (lotMatch || (orderMatch && skuMatch)) { selectedLot = lot; return true; }
return false;
});
const manualMatch = manualOrders.some(function(item) { return rowOrder === item || rowOrderBase === item; }) && (!manualSku || rowSku === manualSku);
if ((selectionMatch || manualMatch) && !seenRows[row]) {
seenRows[row] = true;
matchingRows.push({ row: row, values: values, lot: selectedLot });
}
});

if (!matchingRows.length) {
SpreadsheetApp.getUi().alert('Не знайшов закупки за вибраними лотами.');
return;
}

regularFields.forEach(function(item) {
const field = item[0];
const column = item[1];
if (!isBlank_(form[field])) matchingRows.forEach(function(match) { purchases.getRange(match.row, column).setValue(form[field]); });
});

const jpyRate = getCurrencyRate_('JPY');
matchingRows.forEach(function(match) {
if (match.lot && !isBlank_(match.lot.japanJpy)) purchases.getRange(match.row, 10).setValue(round2_(num_(match.lot.japanJpy) / jpyRate));
});
sharedJpyFields.forEach(function(item) {
const field = item[0];
const column = item[1];
if (isBlank_(form[field])) return;
const totalUah = round2_(num_(form[field]) / jpyRate);
const allocations = matchingRows.length > 1 ? allocateAmount_(totalUah, matchingRows.map(match => num_(match.values[8]))) : [totalUah];
matchingRows.forEach(function(match, index) { purchases.getRange(match.row, column).setValue(allocations[index]); });
});

if (!isBlank_(form['Примітка'])) {
matchingRows.forEach(function(match) { appendCellText_(purchases.getRange(match.row, 18), form['Примітка']); });
}

invalidateDoGetCache_(); clearInputForm('Оновити_закупку');
SpreadsheetApp.getUi().alert('Закупку оновлено. Рядків: ' + matchingRows.length + '. Курс JPY за 1 грн: ' + jpyRate);
}
function addWriteOff() {
resetMemoForMutation_(); const ss = SpreadsheetApp.getActive();
const formSheet = ss.getSheetByName('Внести_списання');
const form = readForm_('Внести_списання');
const writeOffs = ss.getSheetByName('Списання');
const batchValues = formSheet.getRange('A13:D22').getValues();
const lines = [];
const hasBatchLines = batchValues.some(function(row) { return !!row[0] || !!row[1] || !!row[3]; });

if (hasBatchLines) {
for (let i = 0; i < batchValues.length; i++) {
const rowValues = batchValues[i];
const rawSku = rowValues[0];
const rawQty = rowValues[1];
const lineNote = rowValues[3] || '';
const sku = parseSku_(rawSku);
const qty = (rawQty === '' || rawQty === null) ? (sku ? 1 : 0) : num_(rawQty);
if (!sku && (qty > 0 || lineNote)) {
SpreadsheetApp.getUi().alert('У рядку ' + (13 + i) + ' вкажи SKU або очисти кількість/примітку.');
return;
}
if (sku) {
if (qty <= 0) {
SpreadsheetApp.getUi().alert('У рядку ' + (13 + i) + ' кількість має бути більше 0.');
return;
}
lines.push({ sku: sku, qty: qty, note: lineNote });
}
}
} else {
const sku = parseSku_(form['SKU']);
const qty = num_(form['Кількість']);
if (!sku || qty <= 0) {
SpreadsheetApp.getUi().alert('Заповни SKU і кількість або внеси рядки в таблицю списань.');
return;
}
lines.push({ sku: sku, qty: qty, note: '' });
}

if (!form['Дата'] || !form['Тип списання']) {
SpreadsheetApp.getUi().alert('Заповни дату і тип списання.');
return;
}

const expectedQty = num_(form['Очікувана кількість (опційно)']);
if (expectedQty > 0) {
const actualQty = lines.reduce(function(total, line) { return total + line.qty; }, 0);
if (Math.abs(actualQty - expectedQty) > 0.000001) {
SpreadsheetApp.getUi().alert('Сума кількості в рядках: ' + actualQty + '. Очікувана кількість: ' + expectedQty + '.');
return;
}
}

const row = nextEmptyRow_(writeOffs, 1, 3, 201);
if (row + lines.length - 1 > 201) {
SpreadsheetApp.getUi().alert('Недостатньо вільних рядків у вкладці Списання.');
return;
}
const startNumber = nextIdNumber_('Списання', 1, 'WRT');
const ids = lines.map(function(line, index) { return 'WRT-' + String(startNumber + index).padStart(4, '0'); });
writeOffs.getRange(row, 1, lines.length, 4).setValues(lines.map(function(line, index) {
return [ids[index], form['Дата'], form['Тип списання'], line.sku];
}));
writeOffs.getRange(row, 6, lines.length, 1).setValues(lines.map(function(line) { return [line.qty]; }));
writeOffs.getRange(row, 11, lines.length, 2).setValues(lines.map(function(line) {
const noteParts = [];
if (form['Примітка']) noteParts.push(form['Примітка']);
if (line.note) noteParts.push(line.note);
return [form['Причина'], noteParts.join('; ')];
}));
updateSkuCurrentCost_(ss); invalidateDoGetCache_(); clearInputForm('Внести_списання');
SpreadsheetApp.getUi().alert('Списання додано. Рядків: ' + lines.length + '. ID: ' + ids[0] + (ids.length > 1 ? '–' + ids[ids.length - 1] : '')); 
}

function addExpense() {
resetMemoForMutation_(); const ss = SpreadsheetApp.getActive();
const form = readForm_('Внести_витрату');
const expenses = ss.getSheetByName('Витрати');
const category = form['Категорія'];
const consumableType = form['Тип розхідника'] || ''; 
const consumableQty = num_(form['Кількість розхідників']);
const consumableStatus = form['Статус розхідників'] || ''; 
const amount = num_(form['Сума']);
const isConsumable = !!consumableType || consumableQty > 0 || !!consumableStatus;
const unitCost = consumableQty > 0 ? round2_(amount / consumableQty) : num_(form['Собівартість 1 шт']);

if ((category === 'Пакування' || isConsumable) && (!consumableType || consumableQty <= 0 || !consumableStatus)) {
SpreadsheetApp.getUi().alert('Для розхідника заповни тип, кількість і статус розхідників.');
return;
}

const row = nextEmptyRow_(expenses, 1, 3, 201);
expenses.getRange(row, 1, 1, 11).setValues([[
form['Дата'],
category,
form['Опис'],
amount,
form['Пов’язано з продажем?'],
form['Номер замовлення / операції'],
form['Примітка'],
isConsumable ? consumableType : '',
isConsumable ? consumableQty : '',
isConsumable ? consumableStatus : '',
isConsumable ? unitCost : ''
]]);
invalidateDoGetCache_(); clearInputForm('Внести_витрату');
SpreadsheetApp.getUi().alert('Витрату додано.');
}
function generateOperationNumber(source, paymentType) {
const sourcePrefixes = {};
sourcePrefixes['Telegram'] = 'TG';
sourcePrefixes['OLX'] = 'OLX';
sourcePrefixes['Monobazar'] = 'MBZ';
sourcePrefixes['OpenCart'] = 'OC';
sourcePrefixes['Вручну'] = 'MAN';
sourcePrefixes['Інше'] = 'MAN';
const sourcePrefix = sourcePrefixes[source] || 'MAN';
const accountPrefix = paymentType === 'Післяплата фіз' ? 'PHYS' : 'FOP';
return nextId_('Продажі', 1, sourcePrefix + '-' + accountPrefix);
}

function generateLotId() {
return generateLotIds_(1)[0];
}

function generateLotIds_(count) {
const startNumber = nextIdNumber_('Закупки', 1, 'LOT');
const ids = [];
for (let i = 0; i < count; i++) ids.push('LOT-' + String(startNumber + i).padStart(4, '0'));
return ids;
}

function generateWriteOffId() {
return nextId_('Списання', 1, 'WRT');
}

function clearSaleForm() {
clearInputForm('Внести_продаж');
}

function clearPurchaseForm() {
clearInputForm('Внести_закупку');
}

function clearUpdatePurchaseForm() {
clearInputForm('Оновити_закупку');
}

function clearWriteOffForm() {
clearInputForm('Внести_списання');
}

function clearExpenseForm() {
clearInputForm('Внести_витрату');
}

function clearInputForm(sheetName) {
const ss = SpreadsheetApp.getActive();
const sheet = ss.getSheetByName(sheetName);
const ranges = {};
ranges['Внести_продаж'] = ['B4:B16', 'E10', 'A21:B30', 'E21:E30', 'A36:C45'];
ranges['Внести_закупку'] = ['B4:B9', 'A12:B14', 'D12:E14'];
ranges['Оновити_закупку'] = ['B4:B17', 'C4:C11'];
ranges['Внести_списання'] = ['B4:B10', 'A13:B22', 'D13:D22'];
ranges['Внести_витрату'] = ['B4:B13'];
sheet.getRangeList(ranges[sheetName]).clearContent();
if (sheetName === 'Внести_продаж') { sheet.getRange('B5').setValue(new Date()); restoreSaleFormulas_(sheet); }
if (sheetName === 'Внести_закупку') sheet.getRange('B6').setValue('Виграно');
if (sheetName === 'Внести_списання') sheet.getRange('B4').setValue(new Date());
if (sheetName === 'Внести_витрату') {
sheet.getRange('B4').setValue(new Date());
sheet.getRange('B8').setValue('Ні');
sheet.getRange('B14').setFormula('=IFERROR(IF($B$12>0;$B$7/$B$12;IF($B$11="";"";VLOOKUP($B$11;\'Розхідники\'!$A$3:$C$50;3;FALSE)));"")');
}
}

function readForm_(sheetName) {
const sheet = SpreadsheetApp.getActive().getSheetByName(sheetName);
return readFormRange_(sheet, 'A4:B40');
}

function readFormRange_(sheet, rangeA1) {
const values = sheet.getRange(rangeA1).getValues();
const result = {};
values.forEach(function(row) {
if (row[0]) result[row[0]] = row[1];
});
return result;
}

function nextEmptyRow_(sheet, column, startRow, maxRow) {
const values = sheet.getRange(startRow, column, maxRow - startRow + 1, 1).getValues();
for (let i = 0; i < values.length; i++) {
if (!values[i][0]) return startRow + i;
}
return sheet.getLastRow() + 1;
}

function nextId_(sheetName, column, prefix) {
return prefix + '-' + String(nextIdNumber_(sheetName, column, prefix)).padStart(4, '0');
}

function nextIdNumber_(sheetName, column, prefix) {
const sheet = SpreadsheetApp.getActive().getSheetByName(sheetName);
const values = sheet.getRange(3, column, Math.max(sheet.getLastRow() - 2, 1), 1).getValues().flat();
const re = new RegExp('^' + prefix + '-([0-9]+)$');
let max = 0;
values.forEach(function(value) {
const match = String(value || '').match(re);
if (match) max = Math.max(max, Number(match[1]));
});
return max + 1;
}

function restoreSaleFormulas_(sheet) {
const priceFormulas = [];
const sumFormulas = [];
for (let row = 21; row <= 30; row++) {
const priceFormula = '=IF($A' + row + '="";"";IFERROR(VLOOKUP(TRIM(LEFT($A' + row + ';FIND("|";$A' + row + '&"|")-1));\'РРЦ\'!$A$3:$E$300;5;FALSE);""))';
priceFormulas.push([priceFormula]);
sumFormulas.push(['=IF(OR($A' + row + '="";$B' + row + '="";$C' + row + '="");"";$B' + row + '*$C' + row + ')']);
}
sheet.getRange('C21:C30').setFormulas(priceFormulas);
sheet.getRange('D21:D30').setFormulas(sumFormulas);
sheet.getRange('B17').setFormula('=SUM($D$21:$D$30)'); sheet.getRange('B18').setFormula('=IF($B$8="";"";IF($B$8="Післяплата фіз";\'Налаштування\'!$B$10+MAX($B$17-$B$9;0)*\'Налаштування\'!$B$9;IF($B$8="Еквайринг";MAX($B$17-$B$9;0)*\'Налаштування\'!$B$11;IF($B$8="Контроль оплати ФОП";MAX(MAX($B$17-$B$9;0)*\'Налаштування\'!$B$12;\'Налаштування\'!$B$13);0))))'); sheet.getRange('B19').setFormula('=IF($B$10="";0;IF($B$10="Інше";$E$10;IFERROR(VLOOKUP($B$10;\'Розхідники\'!$A$3:$C$50;3;FALSE);0)))'); sheet.getRange('B20').setValue('Кількість');
sheet.getRange('E35').setFormula('=SUMPRODUCT(IFERROR(REGEXMATCH($A$21:$A$30;"-MBX");FALSE)*N($B$21:$B$30))*5');
sheet.getRange('E36').setFormula('=SUM($B$36:$B$45)');
sheet.getRange('E37').setFormula('=IF(SUMPRODUCT(IFERROR(REGEXMATCH($A$21:$A$30;"-MBX");FALSE)*($B$21:$B$30=""))>0;"Вкажи к-сть бокса";IF($E$35=0;"Не потрібно";IF($E$36=$E$35;"OK";"Перевірити")))');
}
function parseSku_(value) {
const text = String(value || '').trim();
if (!text) return ''; 
const pipeIndex = text.indexOf(' | ');
return pipeIndex === -1 ? text : text.substring(0, pipeIndex).trim();
}
function getCurrencyRate_(currency) {
const sheet = SpreadsheetApp.getActive().getSheetByName('Курси');
if (!sheet) return currency === 'JPY' ? 3.5 : 1;
const values = sheet.getRange(4, 1, Math.max(sheet.getLastRow() - 3, 1), 2).getValues();
for (let i = 0; i < values.length; i++) {
if (String(values[i][0] || '').trim() === currency) {
const rate = num_(values[i][1]);
if (rate > 0) return rate;
}
}
return currency === 'JPY' ? 3.5 : 1;
}

function allocateAmount_(amount, basisValues) {
const cleanBasis = basisValues.map(value => Math.max(num_(value), 0));
const basisTotal = cleanBasis.reduce((sum, value) => sum + value, 0);
const fallbackBasis = cleanBasis.map(() => 1);
const activeBasis = basisTotal > 0 ? cleanBasis : fallbackBasis;
const activeTotal = basisTotal > 0 ? basisTotal : fallbackBasis.length;
let allocated = 0;
return activeBasis.map(function(value, index) {
if (index === activeBasis.length - 1) return round2_(amount - allocated);
const part = round2_(amount * value / activeTotal);
allocated = round2_(allocated + part);
return part;
});
}

function appendCellText_(cell, text) {
const current = String(cell.getValue() || '').trim();
const next = current ? current + '; ' + text : text;
cell.setValue(next);
}

function isBlank_(value) {
return value === '' || value == null;
}

function num_(value) {
if (value === '' || value == null) return 0;
if (typeof value === 'string') return Number(value.replace(/\s/g, '').replace(',', '.')) || 0;
return Number(value) || 0;
}

function round2_(value) {
return Math.round((Number(value) + Number.EPSILON) * 100) / 100;
}




function addMysteryBoxWriteOffs_(ss, components, saleDate, operation) {
const writeOffs = ss.getSheetByName('Списання');
const firstRow = nextEmptyRow_(writeOffs, 1, 3, 201);
const startNumber = nextIdNumber_('Списання', 1, 'WRT');
components.forEach(function(component, index) {
const row = firstRow + index;
const writeOffId = 'WRT-' + String(startNumber + index).padStart(4, '0');
const note = ['Продаж ' + operation, component.note].filter(Boolean).join('; ');
writeOffs.getRange(row, 1, 1, 4).setValues([[writeOffId, saleDate, 'Інше', component.sku]]);
writeOffs.getRange(row, 6).setValue(component.qty);
writeOffs.getRange(row, 11, 1, 2).setValues([['для формування містері бокса', note]]);
});
}


function updateSaleStatus() {
resetMemoForMutation_(); const ss = SpreadsheetApp.getActive();
const form = readForm_('Оновити_продаж');
const sales = ss.getSheetByName('Продажі');
const selectedOrder = form['ТТН / замовлення'] || form['Номер замовлення / операції'];
const order = resolveSaleUpdateOrder_(ss, selectedOrder);
if (!order) { SpreadsheetApp.getUi().alert('Вибери ТТН/замовлення для оновлення.'); return; }
const data = sales.getRange(3, 1, Math.max(sales.getLastRow() - 2, 1), 29).getValues();
const matches = [];
data.forEach(function(values, index) {
if (String(values[0] || '').trim() === order) matches.push({ row: index + 3, values: values });
});
if (!matches.length) { SpreadsheetApp.getUi().alert('Не знайшов продажі з номером: ' + order); return; }
const current = matches[0].values;
const newPaymentStatus = form['Новий статус оплати'];
const newOrderStatus = form['Новий статус замовлення'];
const ttn = form['ТТН'];
const packagingType = form['Паковання'];
const paymentChanged = !isBlank_(newPaymentStatus) && String(newPaymentStatus).trim() !== String(current[22] || '').trim();
const orderChanged = !isBlank_(newOrderStatus) && String(newOrderStatus).trim() !== String(current[23] || '').trim();
const packagingChanged = !isBlank_(packagingType) && String(packagingType).trim() !== String(current[28] || '').trim();
const packaging = packagingChanged ? getPackagingCost_(packagingType, form['Якщо Інше, грн']) : null;
const shopDelivery = isBlank_(form['Доставка за рахунок магазину']) ? null : num_(form['Доставка за рахунок магазину']);
const note = form['Примітка'];
const hasUpdate = paymentChanged || orderChanged || !isBlank_(ttn) || packagingChanged || shopDelivery !== null || !isBlank_(note);
if (!hasUpdate) { SpreadsheetApp.getUi().alert('Нічого не змінено: поточні значення вже підтягнуті у форму.'); return; }
if (packagingChanged && packaging <= 0 && String(packagingType).trim() !== 'Інше') { SpreadsheetApp.getUi().alert('Для обраного паковання не підтягнулась вартість. Якщо це Інше, сума 0 грн дозволена.'); return; }
const rows = matches.map(function(match) { return match.row; });
const weights = orderRowWeights_(sales, rows);
const packagingAllocations = packagingChanged ? allocateAmount_(packaging, weights) : [];
const deliveryAllocations = shopDelivery === null ? [] : allocateAmount_(shopDelivery, weights);
const costRunState = {}; rows.forEach(function(row, index) {
if (paymentChanged) sales.getRange(row, 23).setValue(newPaymentStatus);
if (orderChanged) sales.getRange(row, 24).setValue(newOrderStatus);
if (!isBlank_(ttn)) sales.getRange(row, 26).setValue(ttn);
if (packagingChanged) { sales.getRange(row, 16).setValue(packagingAllocations[index]); sales.getRange(row, 29).setValue(packagingType); }
if (shopDelivery !== null) sales.getRange(row, 20).setValue(deliveryAllocations[index]);
if (!isBlank_(note)) appendCellText_(sales.getRange(row, 27), note); fixSaleCostForRow_(ss, row, costRunState, { clearPending: false });
});
invalidateDoGetCache_(); clearSaleUpdateForm();
SpreadsheetApp.getUi().alert('Продаж оновлено: ' + order + ' / рядків: ' + rows.length);
}

function updatePaymentStatus() { updateSaleStatus(); }

function clearSaleUpdateForm() {
const sheet = SpreadsheetApp.getActive().getSheetByName('Оновити_продаж');
sheet.getRangeList(['B4', 'B11:B15']).clearContent();
restoreSaleUpdateFormulas_(sheet);
}

function clearPaymentForm() { clearSaleUpdateForm(); }
function resolveSaleUpdateOrder_(ss, selectedValue) {
const selected = String(selectedValue || '').trim();
if (!selected) return '';
const formSheet = ss.getSheetByName('Оновити_продаж');
if (formSheet) {
const rows = formSheet.getRange('D4:E120').getValues();
for (let i = 0; i < rows.length; i++) {
if (String(rows[i][0] || '').trim() === selected) return String(rows[i][1] || '').trim();
}
}
return parseOrder_(selected);
}

function resolvePaymentOrder_(ss, selectedValue) {
return resolveSaleUpdateOrder_(ss, selectedValue);
}

function parseOrder_(value) {
const text = String(value || '').trim();
if (!text) return '';
const pipeIndex = text.indexOf(' | ');
return pipeIndex === -1 ? text : text.substring(0, pipeIndex).trim();
}




function restoreSaleUpdateFormulas_(sheet) {
if (!sheet) return;
sheet.getRange('B8').setFormula(`=IF($B$4="";"";$B$5)`);
sheet.getRange('B10').setFormula(`=IF($B$4="";"";$B$9)`);
sheet.getRange('B12').setFormula(`=IF($B$4="";"";IFERROR(INDEX('Продажі'!$AC$3:$AC$511;MATCH(INDEX($E$4:$E$120;MATCH($B$4;$D$4:$D$120;0));'Продажі'!$A$3:$A$511;0));""))`);
}

function onEdit(e) {
const range = e && e.range;
if (!range) return;
const sheet = range.getSheet();
if (sheet.getName() === 'Оновити_продаж' && range.getA1Notation() === 'B4') restoreSaleUpdateFormulas_(sheet);
}






















const BOOSTER_CRM_SPREADSHEET_ID = '1PvlSlg3UoPw8Fbj98lHL-VGLB0HP8hgKUxsXPW1GkRg';
const BOOSTER_CRM_TOKEN = 'bscrm_6dcae8d8b90f4c1ea5be1f0a5d2a1b7e';
const AUTOMATION_SPREADSHEET_ID = '1YUGdtxHQJee6vY8MdwRsrUxudJCMtnghOGPVJXwO5ik';

// API/read performance helpers
var _memo = createMemo_();

function createMemo_() {
return { crmSs: null, autoSs: null, salesRows: null, salesRowEntries: null, autoConsumableStateByOrder: null, costAuditColumnsEnsured: false, cacheVersion: null, doGetCacheVersion: null };
}

function resetMemo_() {
_memo = createMemo_();
}

function resetMemoForMutation_() {
if (typeof _memo === 'undefined' || !_memo) resetMemo_();
_memo.salesRows = null; _memo.salesRowEntries = null; _memo.autoConsumableStateByOrder = null; _memo.costAuditColumnsEnsured = false; _memo.doGetCacheVersion = null;
}


function _getCrmSs() {
if (!_memo.crmSs) _memo.crmSs = SpreadsheetApp.openById(BOOSTER_CRM_SPREADSHEET_ID);
return _memo.crmSs;
}

function _getAutoSs() {
if (!_memo.autoSs) _memo.autoSs = SpreadsheetApp.openById(AUTOMATION_SPREADSHEET_ID);
return _memo.autoSs;
}

function _getCrmSalesRowEntries() {
if (!_memo.salesRowEntries) { const ss = _getCrmSs(); const sales = ss.getSheetByName('Продажі'); if (!sales) throw new Error('Не знайдено вкладку Продажі.'); const lastRow = Math.max(sales.getLastRow(), 3); const raw = sales.getRange(3, 1, lastRow - 2, 32).getValues(); _memo.salesRowEntries = raw.map(function(row, index) { return { rowNumber: index + 3, values: row }; }); }
return _memo.salesRowEntries;
}
function _getCrmSalesRows() {
if (!_memo.salesRows) _memo.salesRows = _getCrmSalesRowEntries().map(function(entry) { return entry.values; }).filter(function(row) { return isActualSaleForCost_(row); });
return _memo.salesRows;
}



function keepWarm() { _getCrmSs(); try { _getAutoSs(); } catch (e) { /* non-fatal */ } }

const CACHEABLE_ACTIONS = { sku_list: 300, stock_alerts: 120, summary: 90, channel_stats: 120, monthly_summary: 300 };

function apiDoGetCacheVersion_() {
if (!_memo.doGetCacheVersion) _memo.doGetCacheVersion = String(PropertiesService.getScriptProperties().getProperty('CRM_DOGET_CACHE_VERSION') || '1');
return _memo.doGetCacheVersion;
}

function invalidateDoGetCache_() {
const version = String(new Date().getTime()); PropertiesService.getScriptProperties().setProperty('CRM_DOGET_CACHE_VERSION', version); if (typeof _memo !== 'undefined' && _memo) _memo.doGetCacheVersion = version;
}

function apiDoGetCacheKey_(action, params) { const version = apiDoGetCacheVersion_(); if (action === 'sku_list') return 'bscrm_v2_' + version + '_' + action + '_' + String(params.sort || '').toLowerCase() + '_' + String(params.limit || ''); return 'bscrm_v2_' + version + '_' + action; }

function handleApiAction_(action, params) {
if (action === 'summary') return apiSummary_();
if (action === 'orders') return apiOrders_(params);
if (action === 'stock_alerts') return apiStockAlerts_();
if (action === 'sku_list') return apiSkuList_(params);
if (action === 'consumables') return apiConsumables_(params);
if (action === 'channel_stats') return apiChannelStats_(params);
if (action === 'monthly_summary') return apiMonthlySummary_(params);
if (action === 'ltv_report') return apiLtvReport_(params);
if (action === 'recent_sales') return apiRecentSales_(params);
if (action === 'recent_purchases') return apiRecentPurchases_(params);

return { ok: false, error: 'unknown action: ' + action };
}

function doGet(e) {
resetMemo_();
const params = e && e.parameter ? e.parameter : {};
const action = String(params.action || '').trim();
if (!action) return boosterCrmJson_({ ok: true, service: 'Booster CRM API' });
const token = String(params.token || '');
const expectedToken = getBoosterCrmToken_();
if (!expectedToken || token !== expectedToken) return boosterCrmJson_({ ok: false, error: 'bad token' });
try {
const ttl = CACHEABLE_ACTIONS[action];
if (ttl) {
const cache = CacheService.getScriptCache(); const cacheKey = apiDoGetCacheKey_(action, params);
const hit = cache.get(cacheKey); if (hit) return boosterCrmJson_(JSON.parse(hit));
const result = handleApiAction_(action, params);
try { cache.put(cacheKey, JSON.stringify(result), ttl); } catch (cacheErr) { Logger.log('doGet cache write failed: ' + cacheErr); }
return boosterCrmJson_(result);
}
return boosterCrmJson_(handleApiAction_(action, params));
} catch (err) { return boosterCrmJson_({ ok: false, error: String(err && err.message ? err.message : err) }); }
}


function doPost(e) {
resetMemo_(); let isTelegramUpdate = false; try {
const raw = e && e.postData && e.postData.contents ? e.postData.contents : '{}';
const payload = JSON.parse(raw);
isTelegramUpdate = !!(payload.message || payload.callback_query);
if (isTelegramUpdate) {
Logger.log('Telegram webhook update: type=' + (payload.callback_query ? 'callback_query' : 'message') + ', chat_id=' + tgIncomingChatId_(payload) + ', text=' + tgIncomingText_(payload));
try {
handleTelegramUpdate_(payload);
} catch (tgErr) {
Logger.log('Telegram webhook error: ' + String(tgErr && tgErr.message ? tgErr.message : tgErr));
throw tgErr;
}
return HtmlService.createHtmlOutput('ok');
}

const expectedToken = getBoosterCrmToken_();
if (!expectedToken || expectedToken === 'CHANGE_ME' || payload.token !== expectedToken) {
return boosterCrmJson_({ ok: false, error: 'bad token' });
}
const ss = _getCrmSs();
const lock = LockService.getScriptLock();
if (!lock.tryLock(30000)) return boosterCrmJson_({ ok: false, error: 'crm busy, retry later' });
try {
const action = String(payload.action || '').trim().toLowerCase();
if (action === 'add_sale') return boosterCrmJson_(apiAddSale_(ss, payload));
if (action === 'add_purchase') return boosterCrmJson_(apiAddPurchase_(ss, payload));
if (action === 'add_writeoff') return boosterCrmJson_(apiAddWriteOff_(ss, payload));
if (action === 'update_sale') return boosterCrmJson_(apiUpdateSale_(ss, payload));
if (action === 'update_purchase') return boosterCrmJson_(apiUpdatePurchase_(ss, payload));
if (action === 'add_news_candidate') return boosterCrmJson_(apiAddNewsCandidate_(ss, payload));

const result = upsertOpenCartOrder_(ss, payload);
return boosterCrmJson_({ ok: true, result: result });
} finally { lock.releaseLock(); }
} catch (err) {
Logger.log('doPost error: ' + String(err && err.message ? err.message : err));
if (isTelegramUpdate) throw err;
return boosterCrmJson_({ ok: false, error: String(err && err.message ? err.message : err) });
}
}
function getBoosterCrmToken_() {
return PropertiesService.getScriptProperties().getProperty('BOOSTER_CRM_TOKEN') || BOOSTER_CRM_TOKEN;
}

function boosterCrmJson_(data) {
return ContentService.createTextOutput(JSON.stringify(data)).setMimeType(ContentService.MimeType.JSON);
}

function apiNormalizeDateValue_(value, fieldName) {
const text = String(value || '').trim();
if (!text) return '';
const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text); const date = match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : new Date(text);
if (isNaN(date.getTime()) || (match && (date.getFullYear() !== Number(match[1]) || date.getMonth() !== Number(match[2]) - 1 || date.getDate() !== Number(match[3])))) throw new Error(fieldName + ' invalid');
return date;
}

function apiAddSale_(ss, payload) { try { resetMemoForMutation_(); const sales = ss.getSheetByName('Продажі'); if (!sales) throw new Error('sales sheet missing'); const date = apiNormalizeDateValue_(payload.date, 'date'); if (!date) throw new Error('date required'); const rawItems = Array.isArray(payload.items) ? payload.items.slice(0, 10) : []; if (!rawItems.length) throw new Error('items required'); const items = rawItems.map(function(item) { const sku = parseSku_(item && item.sku); const qty = num_(item && item.qty); const price = num_(item && item.price); if (!sku) throw new Error('sku required'); if (qty <= 0) throw new Error('qty must be > 0'); if (price < 0) throw new Error('price must be >= 0'); return { sku: sku, qty: qty, price: price, note: String(item.note || '').trim() }; }); const source = String(payload.channel || payload.source || 'Вручну').trim() || 'Вручну'; const paymentType = String(payload.payment_type || 'За реквізитами').trim() || 'За реквізитами'; const packagingType = String(payload.packaging_type || '').trim(); const operation = String(payload.order_id || '').trim() || generateOperationNumber(source, paymentType); const gross = items.reduce(function(sum, item) { return sum + item.qty * item.price; }, 0); const discount = Math.min(Math.max(0, num_(payload.discount)), gross); const customPackaging = Object.prototype.hasOwnProperty.call(payload, 'custom_packaging_cost') ? payload.custom_packaging_cost : ''; const packaging = packagingType ? getPackagingCost_(packagingType, customPackaging) : 0; const shopDelivery = Math.max(0, num_(payload.shop_delivery)); const baseNote = [String(payload.note || '').trim(), packagingType ? 'Паковання: ' + packagingType : ''].filter(Boolean).join('; '); const rawComponents = Array.isArray(payload.mystery_components) ? payload.mystery_components.slice(0, 10) : []; const components = rawComponents.map(function(item) { const sku = parseSku_(item && item.sku); const qty = num_(item && item.qty); if (!sku) throw new Error('mystery component sku required'); if (qty <= 0) throw new Error('mystery component qty must be > 0'); return { sku: sku, qty: qty, note: String(item.note || '').trim() }; }); const mysteryQty = items.filter(function(item) { return isMysteryBoxSale_(item.sku, ''); }).reduce(function(sum, item) { return sum + item.qty; }, 0); if (!mysteryQty && components.length) throw new Error('mystery components require an MBX sale'); if (mysteryQty) { const componentQty = components.reduce(function(sum, item) { return sum + item.qty; }, 0); if (!components.length || Math.abs(componentQty - mysteryQty * 5) > 0.0001) throw new Error('mystery components must total ' + (mysteryQty * 5)); } const firstRow = nextEmptyRow_(sales, 1, 3, 501); if (firstRow + items.length - 1 > 501) throw new Error('not enough rows in sales sheet'); const costRunState = {}; items.forEach(function(item, index) { const row = firstRow + index; const weight = gross ? item.qty * item.price / gross : 0; const note = [baseNote, item.note].filter(Boolean).join('; '); sales.getRange(row, 1, 1, 6).setValues([[operation, source, date, String(payload.customer_phone || '').trim(), String(payload.customer_name || '').trim(), item.sku]]); sales.getRange(row, 8, 1, 3).setValues([[item.qty, item.price, round2_(discount * weight)]]); sales.getRange(row, 16).setValue(round2_(packaging * weight)); sales.getRange(row, 20).setValue(round2_(shopDelivery * weight)); sales.getRange(row, 23, 1, 6).setValues([[String(payload.payment_status || '').trim(), String(payload.order_status || '').trim(), String(payload.post || '').trim(), String(payload.ttn || '').trim(), note, paymentType]]); sales.getRange(row, 29).setValue(packagingType); fixSaleCostForRow_(ss, row, costRunState, { clearPending: true }); }); if (components.length) { addMysteryBoxWriteOffs_(ss, components, date, operation); SpreadsheetApp.flush(); recalculateMysteryBoxOrderCost_(ss, operation); } updateSkuCurrentCost_(ss); invalidateDoGetCache_(); return { ok: true, rows_added: items.length, order_id: operation }; } catch (err) { return { ok: false, error: String(err && err.message ? err.message : err) }; } } function apiAddPurchase_(ss, payload) { try { resetMemoForMutation_(); const purchases = ss.getSheetByName('Закупки'); if (!purchases) throw new Error('purchases sheet missing'); const supplierChannel = String(payload.supplier_channel || 'zenmarket_jp').trim() || 'zenmarket_jp'; const isZenmarket = supplierChannel === 'zenmarket_jp' || supplierChannel === 'ZenMarket'; const rawOrder = String(payload.order_ref || '').trim(); const order = rawOrder || (isZenmarket ? '' : ('AUTO-' + supplierChannel.replace(/[^A-Za-z0-9]+/g, '-').toUpperCase() + '-' + Utilities.formatDate(new Date(), Session.getScriptTimeZone(), 'yyyyMMdd-HHmmss') + '-' + Math.floor(Math.random() * 1000))); if (!order) throw new Error('order_ref required for zenmarket_jp'); if (!Object.prototype.hasOwnProperty.call(payload, 'total_cost')) throw new Error('total_cost required'); const totalCost = num_(payload.total_cost); if (totalCost < 0) throw new Error('total_cost must be >= 0'); const rawItems = Array.isArray(payload.items) ? payload.items.slice(0, 3) : []; if (!rawItems.length) throw new Error('items required'); const items = rawItems.map(function(item) { const sku = parseSku_(item && item.sku); const qty = num_(item && item.qty); const hasManual = item && item.manual_cost !== null && item.manual_cost !== '' && item.manual_cost !== undefined; const manualCost = hasManual ? num_(item.manual_cost) : null; if (!sku) throw new Error('sku required'); if (qty <= 0) throw new Error('qty must be > 0'); if (hasManual && manualCost < 0) throw new Error('manual_cost must be >= 0'); return { sku: sku, qty: qty, manualCost: manualCost, note: String(item.note || '').trim() }; }); const manualTotal = items.reduce(function(sum, item) { return sum + (item.manualCost === null ? 0 : item.manualCost); }, 0); const autoQty = items.reduce(function(sum, item) { return sum + (item.manualCost === null ? item.qty : 0); }, 0); if (manualTotal > totalCost + 0.05) throw new Error('manual costs exceed total_cost'); if (!autoQty && Math.abs(manualTotal - totalCost) > 0.05) throw new Error('manual costs must equal total_cost'); let allocatedCost = round2_(manualTotal); const autoItems = items.filter(function(item) { return item.manualCost === null; }); items.forEach(function(item) { if (item.manualCost !== null) item.cost = round2_(item.manualCost); else { const isLast = autoItems[autoItems.length - 1] === item; item.cost = isLast ? round2_(totalCost - allocatedCost) : round2_((totalCost - manualTotal) * item.qty / autoQty); allocatedCost = round2_(allocatedCost + item.cost); } if (item.cost < 0) throw new Error('line cost must be >= 0'); }); const lineTotal = round2_(items.reduce(function(sum, item) { return sum + item.cost; }, 0)); if (Math.abs(lineTotal - totalCost) > 0.05) throw new Error('line costs do not equal total_cost'); const japanFeesJpy = isZenmarket ? Math.max(0, num_(payload.japan_fees_jpy)) : 0; const japanFees = japanFeesJpy ? round2_(japanFeesJpy / getCurrencyRate_('JPY')) : 0; const ukraineDelivery = Math.max(0, num_(payload.ukraine_delivery_uah)); const totalQty = items.reduce(function(sum, item) { return sum + item.qty; }, 0); const firstRow = nextEmptyRow_(purchases, 1, 3, 301); if (firstRow + items.length - 1 > 301) throw new Error('not enough rows in purchases sheet'); const lotIds = generateLotIds_(items.length); let allocatedFees = 0; let allocatedUkraine = 0; const hasManual = items.some(function(item) { return item.manualCost !== null; }); items.forEach(function(item, index) { const row = firstRow + index; const lineFees = japanFees ? (index === items.length - 1 ? round2_(japanFees - allocatedFees) : round2_(japanFees * item.qty / totalQty)) : ''; const lineUkraine = ukraineDelivery ? (index === items.length - 1 ? round2_(ukraineDelivery - allocatedUkraine) : round2_(ukraineDelivery * item.qty / totalQty)) : ''; if (japanFees) allocatedFees = round2_(allocatedFees + lineFees); if (ukraineDelivery) allocatedUkraine = round2_(allocatedUkraine + lineUkraine); const note = [String(payload.note || '').trim(), item.note, hasManual ? 'Вартість рядків: авто/ручне коригування з форми' : (items.length > 1 ? 'Вартість лоту розподілена пропорційно кількості' : ''), items.length > 1 && japanFees ? 'JP доставка/комісії в JPY конвертовані в грн і розподілені пропорційно кількості' : ''].filter(Boolean).join('; '); purchases.getRange(row, 1, 1, 5).setValues([[lotIds[index], order, '', '', item.sku]]); purchases.getRange(row, 8, 1, 4).setValues([[item.qty, item.cost, lineFees, lineUkraine]]); purchases.getRange(row, 17, 1, 3).setValues([[String(payload.status || 'Замовлено').trim(), note, String(payload.order_url || '').trim()]]); purchases.getRange(row, 20).setValue(supplierChannel); }); invalidateDoGetCache_(); return { ok: true, rows_added: items.length, lot_ids: lotIds }; } catch (err) { return { ok: false, error: String(err && err.message ? err.message : err) }; } } function apiAddWriteOff_(ss, payload) { try { resetMemoForMutation_(); const writeOffs = ss.getSheetByName('Списання'); if (!writeOffs) throw new Error('writeoff sheet missing'); const date = apiNormalizeDateValue_(payload.date, 'date'); if (!date) throw new Error('date required'); const type = String(payload.writeoff_type || payload.type || '').trim(); const reason = String(payload.reason || '').trim(); if (!type) throw new Error('writeoff_type required'); if (!reason) throw new Error('reason required'); const rawItems = Array.isArray(payload.items) ? payload.items.slice(0, 10) : []; if (!rawItems.length) throw new Error('items required'); const items = rawItems.map(function(item) { const sku = parseSku_(item && item.sku); const qty = num_(item && item.qty); if (!sku) throw new Error('sku required'); if (qty <= 0) throw new Error('qty must be > 0'); return { sku: sku, qty: qty, note: String(item.note || '').trim() }; }); if (Object.prototype.hasOwnProperty.call(payload, 'expected_qty') && String(payload.expected_qty) !== '') { const expected = num_(payload.expected_qty); const actual = items.reduce(function(sum, item) { return sum + item.qty; }, 0); if (expected > 0 && Math.abs(actual - expected) > 0.000001) throw new Error('actual quantity ' + actual + ' does not match expected ' + expected); } const row = nextEmptyRow_(writeOffs, 1, 3, 201); if (row + items.length - 1 > 201) throw new Error('not enough rows in writeoff sheet'); const startNumber = nextIdNumber_('Списання', 1, 'WRT'); const ids = items.map(function(item, index) { return 'WRT-' + String(startNumber + index).padStart(4, '0'); }); writeOffs.getRange(row, 1, items.length, 4).setValues(items.map(function(item, index) { return [ids[index], date, type, item.sku]; })); writeOffs.getRange(row, 6, items.length, 1).setValues(items.map(function(item) { return [item.qty]; })); writeOffs.getRange(row, 11, items.length, 2).setValues(items.map(function(item) { return [reason, [String(payload.note || '').trim(), item.note].filter(Boolean).join('; ')]; })); SpreadsheetApp.flush(); recalculateMysteryBoxOrdersFromNote_(ss, String(payload.note || '').trim()); updateSkuCurrentCost_(ss); invalidateDoGetCache_(); return { ok: true, rows_added: items.length, ids: ids }; } catch (err) { return { ok: false, error: String(err && err.message ? err.message : err) }; } } function apiRecentTable_(sheet, requiredHeader) { if (!sheet) return { headerRow: 0, headers: [], rows: [] }; const lastRow = sheet.getLastRow(); const lastCol = Math.min(sheet.getLastColumn(), 50); if (lastRow < 1 || lastCol < 1) return { headerRow: 0, headers: [], rows: [] }; const values = sheet.getRange(1, 1, lastRow, lastCol).getValues(); const wanted = apiNormalizeHeader_(requiredHeader); let headerIndex = -1; for (let i = 0; i < Math.min(values.length, 20); i++) { if (values[i].map(apiNormalizeHeader_).indexOf(wanted) !== -1) { headerIndex = i; break; } } if (headerIndex === -1) throw new Error('header not found: ' + requiredHeader); return { headerRow: headerIndex + 1, headers: values[headerIndex], rows: values.slice(headerIndex + 1) }; } function apiRecentCol_(headers, name) { const index = headers.map(apiNormalizeHeader_).indexOf(apiNormalizeHeader_(name)); if (index === -1) throw new Error('column not found: ' + name); return index; } function apiRecentLimit_(params) { return Math.max(1, Math.min(Math.floor(apiNum_(params && params.limit) || 20), 50)); } function apiRecentSales_(params) { const table = apiRecentTable_(_getCrmSs().getSheetByName('Продажі'), 'Номер замовлення / операції'); if (!table.headerRow) return { ok: true, rows: [] }; const c = { order: apiRecentCol_(table.headers, 'Номер замовлення / операції'), date: apiRecentCol_(table.headers, 'Дата продажу'), amount: apiRecentCol_(table.headers, 'Сума продажу'), packagingCost: apiRecentCol_(table.headers, 'Пакування'), shopDelivery: apiRecentCol_(table.headers, 'Доставка за рахунок магазину'), paymentStatus: apiRecentCol_(table.headers, 'Статус оплати'), orderStatus: apiRecentCol_(table.headers, 'Статус замовлення'), ttn: apiRecentCol_(table.headers, 'ТТН'), post: apiRecentCol_(table.headers, 'Пошта'), note: apiRecentCol_(table.headers, 'Примітка'), paymentType: apiRecentCol_(table.headers, 'Тип оплати'), packagingType: apiRecentCol_(table.headers, 'Паковання') }; const rows = []; let current = null; for (let i = table.rows.length - 1; i >= 0; i--) { const row = table.rows[i]; const order = String(row[c.order] || '').trim(); if (!order) { current = null; continue; } if (!current || current.order_id !== order) { current = { row_index: table.headerRow + 1 + i, order_id: order, date: row[c.date] ? apiDate_(row[c.date]) : '', payment_status: row[c.paymentStatus] || '', payment_type: row[c.paymentType] || '', order_status: row[c.orderStatus] || '', ttn: row[c.ttn] || '', post: row[c.post] || '', packaging_type: row[c.packagingType] || '', amount: 0, packaging_cost: 0, shop_delivery: 0, note: row[c.note] || '' }; rows.push(current); } current.row_index = table.headerRow + 1 + i; current.amount += apiNum_(row[c.amount]); current.packaging_cost += apiNum_(row[c.packagingCost]); current.shop_delivery += apiNum_(row[c.shopDelivery]); } const result = rows.map(function(item) { item.amount = round2_(item.amount); item.packaging_cost = round2_(item.packaging_cost); item.shop_delivery = round2_(item.shop_delivery); return item; }).filter(function(item) { return ['Скасовано', 'Повернення'].indexOf(String(item.payment_status)) === -1 && ['Скасовано', 'Повернення'].indexOf(String(item.order_status)) === -1 && (String(item.payment_status) !== 'Оплачено' || String(item.order_status) !== 'Отримано'); }).sort(function(a, b) { return b.row_index - a.row_index; }).slice(0, apiRecentLimit_(params)); return { ok: true, rows: result }; } function apiRecentPurchases_(params) { const table = apiRecentTable_(_getCrmSs().getSheetByName('Закупки'), 'ID партії'); if (!table.headerRow) return { ok: true, rows: [] }; const c = { lot: apiRecentCol_(table.headers, 'ID партії'), order: apiRecentCol_(table.headers, 'ZenMarket Order №'), track: apiRecentCol_(table.headers, 'Трек-номер'), date: apiRecentCol_(table.headers, 'Дата доставки в Україну'), sku: apiRecentCol_(table.headers, 'SKU'), qty: apiRecentCol_(table.headers, 'Кількість одиниць'), japanFee: apiRecentCol_(table.headers, 'Доставка / комісії по Японії, грн'), status: apiRecentCol_(table.headers, 'Статус'), note: apiRecentCol_(table.headers, 'Примітка') }; const terminal = { 'На складі UA': true, 'На складі': true, 'Продано': true, 'Частково продано': true, 'Скасовано': true }; const rows = []; const jpyRate = getCurrencyRate_('JPY'); for (let i = 0; i < table.rows.length; i++) { const row = table.rows[i]; const lotId = String(row[c.lot] || '').trim(); const status = String(row[c.status] || '').trim(); if (!lotId || row[c.date] || terminal[status]) continue; rows.push({ row_index: table.headerRow + 1 + i, lot_id: lotId, order_ref: row[c.order] || '', track_number: row[c.track] || '', date: '', sku: row[c.sku] || '', qty: apiNum_(row[c.qty]), japan_fee_jpy: round2_(apiNum_(row[c.japanFee]) * jpyRate), status: status, note: row[c.note] || '' }); } rows.sort(function(a, b) { const an = Number((String(a.order_ref || '').match(/\d+/) || [0])[0]); const bn = Number((String(b.order_ref || '').match(/\d+/) || [0])[0]); return an - bn || String(a.order_ref || '').localeCompare(String(b.order_ref || '')); }); return { ok: true, rows: rows.slice(0, apiRecentLimit_(params)) }; } function apiUpdateSale_(ss, payload) { try { resetMemoForMutation_(); const sales = ss.getSheetByName('Продажі'); if (!sales) throw new Error('sales sheet missing'); const rowIndex = Math.floor(apiNum_(payload.row_index)); if (rowIndex < 3 || rowIndex > sales.getLastRow()) throw new Error('invalid row_index'); const current = sales.getRange(rowIndex, 1, 1, 29).getValues()[0]; const order = String(current[0] || '').trim(); if (!order) throw new Error('sale row is empty'); const rows = [rowIndex]; for (let row = rowIndex - 1; row >= 3; row--) { if (String(sales.getRange(row, 1).getValue() || '').trim() !== order) break; rows.unshift(row); } for (let row = rowIndex + 1; row <= sales.getLastRow(); row++) { if (String(sales.getRange(row, 1).getValue() || '').trim() !== order) break; rows.push(row); } const paymentStatus = String(payload.payment_status || '').trim(); const orderStatus = String(payload.order_status || '').trim(); const ttn = String(payload.ttn || '').trim(); const packagingType = String(payload.packaging_type || '').trim(); const note = String(payload.note || '').trim(); const paymentChanged = paymentStatus && paymentStatus !== String(current[22] || '').trim(); const orderChanged = orderStatus && orderStatus !== String(current[23] || '').trim(); const ttnChanged = Object.prototype.hasOwnProperty.call(payload, 'ttn') && ttn !== String(current[25] || '').trim(); const packagingChanged = packagingType && packagingType !== String(current[28] || '').trim(); const hasCustomPackaging = Object.prototype.hasOwnProperty.call(payload, 'custom_packaging_cost') && String(payload.custom_packaging_cost) !== ''; const packaging = packagingChanged || hasCustomPackaging ? getPackagingCost_(packagingType, payload.custom_packaging_cost) : null; const hasDelivery = Object.prototype.hasOwnProperty.call(payload, 'shop_delivery') && String(payload.shop_delivery) !== ''; const shopDelivery = hasDelivery ? Math.max(0, apiNum_(payload.shop_delivery)) : null; if (!paymentChanged && !orderChanged && !ttnChanged && packaging === null && shopDelivery === null && !note) { throw new Error('nothing changed'); } const weights = orderRowWeights_(sales, rows); const packagingAllocations = packaging === null ? [] : allocateAmount_(packaging, weights); const deliveryAllocations = shopDelivery === null ? [] : allocateAmount_(shopDelivery, weights); const costRunState = {}; rows.forEach(function(row, index) { if (paymentChanged) sales.getRange(row, 23).setValue(paymentStatus); if (orderChanged) sales.getRange(row, 24).setValue(orderStatus); if (ttnChanged) sales.getRange(row, 26).setValue(ttn); if (packaging !== null) { sales.getRange(row, 16).setValue(packagingAllocations[index]); sales.getRange(row, 29).setValue(packagingType); } if (shopDelivery !== null) sales.getRange(row, 20).setValue(deliveryAllocations[index]); if (note) appendCellText_(sales.getRange(row, 27), note); fixSaleCostForRow_(ss, row, costRunState, { clearPending: false }); }); invalidateDoGetCache_(); return { ok: true, row_index: rowIndex, order_id: order, rows_updated: rows.length }; } catch (err) { return { ok: false, error: String(err && err.message ? err.message : err) }; } } function apiUpdatePurchase_(ss, payload) { try { resetMemoForMutation_(); const purchases = ss.getSheetByName('Закупки'); if (!purchases) throw new Error('purchases sheet missing'); const rawLots = Array.isArray(payload.lots) ? payload.lots : []; if (!rawLots.length) throw new Error('lots required'); if (rawLots.length > 5) throw new Error('maximum 5 lots'); const lots = {}; rawLots.forEach(function(item) { const lotId = String(item && item.lot_id || '').trim(); if (!/^LOT-[0-9]+$/i.test(lotId)) throw new Error('invalid lot_id'); if (lots[lotId]) throw new Error('duplicate lot_id'); lots[lotId] = item; }); const data = purchases.getRange(3, 1, Math.max(purchases.getLastRow() - 2, 1), 18).getValues(); const matches = []; data.forEach(function(values, index) { const lotId = String(values[0] || '').trim(); if (lots[lotId]) matches.push({ row: index + 3, values: values, lot: lots[lotId] }); }); if (matches.length !== rawLots.length) throw new Error('one or more lots not found'); const hasTrack = Object.prototype.hasOwnProperty.call(payload, 'track_number'); const hasDate = Object.prototype.hasOwnProperty.call(payload, 'date') && String(payload.date || '').trim(); const hasStatus = Object.prototype.hasOwnProperty.call(payload, 'status') && String(payload.status || '').trim(); const hasUkraine = Object.prototype.hasOwnProperty.call(payload, 'ukraine_delivery_jpy') && String(payload.ukraine_delivery_jpy) !== ''; const note = String(payload.note || '').trim(); const hasJapan = matches.some(function(match) { return Object.prototype.hasOwnProperty.call(match.lot, 'japan_fee_jpy') && String(match.lot.japan_fee_jpy) !== ''; }); if (!hasTrack && !hasDate && !hasStatus && !hasUkraine && !note && !hasJapan) throw new Error('nothing changed'); const jpyRate = getCurrencyRate_('JPY'); let ukraineAllocations = []; if (hasUkraine) { const totalUah = round2_(Math.max(0, apiNum_(payload.ukraine_delivery_jpy)) / jpyRate); ukraineAllocations = matches.length > 1 ? allocateAmount_(totalUah, matches.map(function(match) { return apiNum_(match.values[8]); })) : [totalUah]; } matches.forEach(function(match, index) { if (hasTrack) purchases.getRange(match.row, 3).setValue(String(payload.track_number || '').trim()); if (hasDate) purchases.getRange(match.row, 4).setValue(apiNormalizeDateValue_(payload.date, 'date')); if (hasStatus) purchases.getRange(match.row, 17).setValue(String(payload.status).trim()); if (Object.prototype.hasOwnProperty.call(match.lot, 'japan_fee_jpy') && String(match.lot.japan_fee_jpy) !== '') purchases.getRange(match.row, 10).setValue(round2_(Math.max(0, apiNum_(match.lot.japan_fee_jpy)) / jpyRate)); if (hasUkraine) purchases.getRange(match.row, 11).setValue(ukraineAllocations[index]); if (note) appendCellText_(purchases.getRange(match.row, 18), note); }); invalidateDoGetCache_(); return { ok: true, rows_updated: matches.length, lot_ids: Object.keys(lots) }; } catch (err) { return { ok: false, error: String(err && err.message ? err.message : err) }; } }
function recalculateMysteryBoxOrdersFromNote_(ss, note) { note = String(note || '').trim(); if (!note) return []; const sales = ss.getSheetByName('Продажі'); if (!sales) return []; const values = sales.getRange(3, 1, Math.max(sales.getLastRow() - 2, 1), 6).getValues(); const orders = {}; values.forEach(function(row) { const order = String(row[0] || '').trim(); const escaped = order.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); if (order && isMysteryBoxSale_(row[5], '') && new RegExp('(^|\\s|[;,:])' + escaped + '($|\\s|[;,.])').test(note)) orders[order] = true; }); Object.keys(orders).forEach(function(order) { recalculateMysteryBoxOrderCost_(ss, order); }); return Object.keys(orders); } function recalculateMysteryBoxOrderCost_(ss, order) { order = String(order || '').trim(); if (!order) return null; const sales = ss.getSheetByName('Продажі'); const writeOffs = ss.getSheetByName('Списання'); if (!sales || !writeOffs) return null; ensureSaleCostAuditColumns_(sales); const saleValues = sales.getRange(3, 1, Math.max(sales.getLastRow() - 2, 1), 32).getValues(); const mysteryRows = []; saleValues.forEach(function(values, index) { if (String(values[0] || '').trim() === order && isMysteryBoxSale_(values[5], values[6])) mysteryRows.push({ row: index + 3, values: values }); }); if (!mysteryRows.length) return null; const writeOffValues = writeOffs.getRange(3, 1, Math.max(writeOffs.getLastRow() - 2, 1), 12).getValues(); let prroComponents = 0; let mgmtComponents = 0; const componentAudit = []; writeOffValues.forEach(function(values) { const note = String(values[11] || ''); if (note.indexOf(order) === -1) return; const qty = num_(values[5]); const prro = num_(values[8]); const mgmt = num_(values[9]); if (qty <= 0 || (!prro && !mgmt)) return; prroComponents += prro; mgmtComponents += mgmt || prro; componentAudit.push(String(values[3] || '') + '=' + round2_(mgmt || prro)); }); if (prroComponents <= 0 && mgmtComponents <= 0) return null; const qty = mysteryRows.reduce(function(sum, item) { return sum + num_(item.values[7]); }, 0); if (qty <= 0) return null; const first = mysteryRows[0]; const saleDate = first.values[2]; const sticker = getAutoConsumableUnitCost_(ss, 'Стікер лого+QR', 'any', order, first.row, saleDate); const blind = getAutoConsumableUnitCost_(ss, 'Блайнд-пакет для картки', 'mystery', order, first.row, saleDate); const mysteryLabel = getAutoConsumableUnitCost_(ss, 'Наліпка Mystery Box', 'mystery', order, first.row, saleDate); const directExpense = getDirectOrderExpense_(ss, order); const prroUnit = round2_(prroComponents / qty); const mgmtUnit = round2_((mgmtComponents + sticker + blind + mysteryLabel + directExpense) / qty); const audit = trimCostAudit_('components: ' + componentAudit.join(', ') + '; consumables=' + round2_(sticker + blind + mysteryLabel) + '; direct expenses=' + round2_(directExpense)); mysteryRows.forEach(function(item) { sales.getRange(item.row, 12, 1, 2).setValues([[prroUnit, mgmtUnit]]); sales.getRange(item.row, 30, 1, 3).setValues([['MBX фактична комплектація', audit, new Date()]]); }); return { order_id: order, rows_updated: mysteryRows.length, prro_unit: prroUnit, mgmt_unit: mgmtUnit }; } function getDirectOrderExpense_(ss, order) { const expenses = ss.getSheetByName('Витрати'); if (!expenses) return 0; const values = expenses.getRange(3, 1, Math.max(expenses.getLastRow() - 2, 1), 6).getValues(); return round2_(values.reduce(function(sum, row) { const linked = String(row[4] || '').trim().toLowerCase(); const orderRef = String(row[5] || '').trim(); return linked === 'так' && orderRef === order ? sum + num_(row[3]) : sum; }, 0)); }













































































































































































































































































































function upsertOpenCartOrder_(ss, payload) {
const sales = ss.getSheetByName('Продажі');
const orderKey = String(payload.order_key || ('OC-FOP-' + String(payload.order_id || '').padStart(4, '0'))).trim();
if (!orderKey) throw new Error('Missing order key'); if (isIgnoredOpenCartOrder_(ss, payload, orderKey)) return { action: 'ignored_test_order', order: orderKey, rows: 0 };
const products = (payload.products || []).filter(function(product) { return product && (product.sku || product.model); });
if (!products.length) throw new Error('No products in payload');
const orderDate = parseOpenCartDate_(payload.date_added);
const phone = payload.telephone || '';
const fullName = payload.customer_name || [payload.firstname, payload.lastname].filter(Boolean).join(' ');
const paymentType = mapOpenCartPaymentType_(payload);
const paymentStatus = mapOpenCartPaymentStatus_(payload, paymentType);
const orderStatus = mapOpenCartOrderStatus_(payload.order_status || '');
const postal = mapOpenCartPost_(payload.shipping_method_name || payload.shipping_method_code || '');
const ttn = extractOpenCartTtn_(payload);
const note = buildOpenCartNote_(payload);
const existingRows = findSaleRowsByOrder_(sales, orderKey);
if (existingRows.length) {
return { action: 'ignored_existing_order', order: orderKey, rows: existingRows.length };
}











const grossValues = products.map(function(product) { return num_(product.total || (num_(product.quantity) * num_(product.price))); });
const discountTotal = extractOpenCartDiscount_(payload);
const discounts = allocateAmount_(discountTotal, grossValues);
const firstRow = nextEmptyRow_(sales, 1, 3, 501);
const costRunState = {}; products.forEach(function(product, index) {
const row = firstRow + index;
const sku = normalizeOpenCartSku_(product.sku || product.model || '');
const qty = num_(product.quantity);
const price = num_(product.price);
sales.getRange(row, 1, 1, 6).setValues([[orderKey, 'OpenCart', orderDate, phone, fullName, sku]]);
sales.getRange(row, 8, 1, 3).setValues([[qty, price, discounts[index] || 0]]);
sales.getRange(row, 16).setValue(0);
sales.getRange(row, 20).setValue(0);
sales.getRange(row, 23, 1, 3).setValues([[paymentStatus, orderStatus, postal]]); if (ttn) sales.getRange(row, 26).setValue(ttn); const noteCell = sales.getRange(row, 27); if (!String(noteCell.getValue() || '').trim()) noteCell.setValue(note); sales.getRange(row, 28).setValue(paymentType); fixSaleCostForRow_(ss, row, costRunState, { clearPending: true });
});
invalidateDoGetCache_(); return { action: 'inserted', order: orderKey, rows: products.length };
}
function normalizeOpenCartSku_(sku) { const text = String(sku || '').trim(); const aliases = { 'PKM-KR-HWA-BST': 'PKM-KR-HWAK-BST', 'PKM-KR-HWA-BBX': 'PKM-KR-HWAK-BBX' }; return aliases[text] || text; }
function isIgnoredOpenCartOrder_(ss, payload, orderKey) {
const rules = getOpenCartIgnoreRules_(ss);

const phone = normalizePhoneForCompare_(payload.telephone || '');
if (phone && rules.phones[phone]) return true;
const email = normalizeTextForCompare_(payload.email || '');
if (email && rules.emails[email]) return true;
const fullName = normalizeTextForCompare_(payload.customer_name || [payload.firstname, payload.lastname].filter(Boolean).join(' '));
if (fullName && Object.keys(rules.names).some(function(name) { return fullName.indexOf(name) !== -1 || name.indexOf(fullName) !== -1; })) return true;
return false;
}

function getOpenCartIgnoreRules_(ss) {
const rules = { orders: {}, phones: {}, emails: {}, names: {} };
const settings = ss.getSheetByName('Налаштування');
if (!settings) return rules;
const values = settings.getRange('A32:B60').getValues();
values.forEach(function(row) {
const type = normalizeTextForCompare_(row[0]);
const rawValue = String(row[1] || '').trim();
if (!type || !rawValue) return;
if (type === 'order' || type === 'замовлення') rules.orders[rawValue] = true;
if (type === 'phone' || type === 'телефон') {
const phone = normalizePhoneForCompare_(rawValue);
if (phone) rules.phones[phone] = true;
}
if (type === 'email' || type === 'пошта') rules.emails[normalizeTextForCompare_(rawValue)] = true;
if (type === 'name' || type === 'піб' || type === "ім'я") rules.names[normalizeTextForCompare_(rawValue)] = true;
});
return rules;
}

function normalizePhoneForCompare_(value) {
const digits = String(value || '').replace(/\D/g, '');
if (!digits) return '';
return digits.length > 9 ? digits.slice(-9) : digits;
}

function normalizeTextForCompare_(value) {
return String(value || '').toLowerCase().replace(/ё/g, 'е').replace(/\s+/g, ' ').trim();
}

function findSaleRowsByOrder_(sales, orderKey) {
const lastRow = Math.max(sales.getLastRow(), 3);
const values = sales.getRange(3, 1, lastRow - 2, 1).getValues();
const rows = [];
values.forEach(function(row, index) {
if (String(row[0] || '').trim() === orderKey) rows.push(index + 3);
});
return rows;
}

function setCellIfBlank_(cell, value) {
if (value === '' || value == null) return;
if (!String(cell.getValue() || '').trim()) cell.setValue(value);
}

function parseOpenCartDate_(value) {
if (!value) return new Date();
const text = String(value).replace(' ', 'T');
const date = new Date(text);
return isNaN(date.getTime()) ? new Date() : date;
}

function mapOpenCartPaymentType_(payload) {
const text = String([payload.payment_method_name, payload.payment_method_code].filter(Boolean).join(' ')).toLowerCase();
if (text.indexOf('card') !== -1 || text.indexOf('карт') !== -1 || text.indexOf('apple') !== -1 || text.indexOf('google') !== -1 || text.indexOf('pay') !== -1) return 'Еквайринг';
if (text.indexOf('рекв') !== -1 || text.indexOf('bank') !== -1 || text.indexOf('iban') !== -1) return 'За реквізитами';
if (text.indexOf('після') !== -1 || text.indexOf('cod') !== -1 || text.indexOf('налож') !== -1 || text.indexOf('нова пей') !== -1 || text.indexOf('novapay') !== -1) return 'Контроль оплати ФОП';
return 'Еквайринг';
}

function mapOpenCartPaymentStatus_(payload, paymentType) {
const status = String(payload.order_status || '').toLowerCase();
if (status.indexOf('скас') !== -1 || status.indexOf('cancel') !== -1) return 'Скасовано';
if (status.indexOf('повер') !== -1 || status.indexOf('return') !== -1) return 'Повернення';
if (paymentType === 'Еквайринг') return 'Оплачено';
if (status.indexOf('оплач') !== -1 || status.indexOf('paid') !== -1) return 'Оплачено';
return 'Не оплачено';
}

function mapOpenCartOrderStatus_(statusValue) {
const status = String(statusValue || '').toLowerCase();
if (status.indexOf('перед') !== -1 || status.indexOf('pre') !== -1) return 'Передзамовлення';
if (status.indexOf('доставля') !== -1 || status.indexOf('відправ') !== -1 || status.indexOf('shipped') !== -1) return 'Відправлено';
if (status.indexOf('отрим') !== -1 || status.indexOf('доставлено') !== -1 || status.indexOf('complete') !== -1) return 'Отримано';
if (status.indexOf('скас') !== -1 || status.indexOf('cancel') !== -1) return 'Скасовано';
if (status.indexOf('повер') !== -1 || status.indexOf('return') !== -1) return 'Повернення';
if (status.indexOf('оброб') !== -1 || status.indexOf('process') !== -1) return 'В обробці';
return 'Нове';
}

function mapOpenCartPost_(shippingText) {
const text = String(shippingText || '').toLowerCase();
if (text.indexOf('нова') !== -1 || text.indexOf('novaposhta') !== -1 || text.indexOf('novapost') !== -1) return 'НП';
if (text.indexOf('укр') !== -1 || text.indexOf('ukr') !== -1) return 'УП';
if (text.indexOf('meest') !== -1) return 'Meest';
if (text.indexOf('самов') !== -1) return 'Самовивіз';
return 'Інше';
}

function extractOpenCartTtn_(payload) {
const chunks = [payload.tracking, payload.comment];
(payload.histories || []).forEach(function(history) { chunks.push(history.comment); });
for (let i = 0; i < chunks.length; i++) {
const text = String(chunks[i] || '');
const matches = text.match(/(?:\d[\s-]?){10,20}/g) || [];
for (let j = 0; j < matches.length; j++) {
const digits = matches[j].replace(/\D/g, '');
if (digits.length >= 10) return digits;
}
}
return '';
}

function extractOpenCartDiscount_(payload) {
return (payload.totals || []).reduce(function(sum, total) {
const value = num_(total.value);
const code = String(total.code || '').toLowerCase();
if (value < 0 && code !== 'total') return sum + Math.abs(value);
return sum;
}, 0);
}

function buildOpenCartNote_(payload) {
const parts = ['OpenCart #' + (payload.order_id || '')];
if (payload.email) parts.push('Email: ' + payload.email);
if (payload.payment_method_name) parts.push('Оплата: ' + payload.payment_method_name);
if (payload.shipping_method_name) parts.push('Доставка: ' + payload.shipping_method_name);
if (num_(payload.total) >= 1500) parts.push('Доставка за рахунок магазину: довнести фактичну суму після відправки'); if (payload.comment) parts.push('Коментар: ' + String(payload.comment).replace(/\s+/g, ' ').trim());
return parts.filter(Boolean).join('; ');
}






















function fixSaleCostForRow_(ss, row, runState, options) {
runState = runState || {};
options = options || {};
const sales = ss.getSheetByName('Продажі');
ensureSaleCostAuditColumns_(sales);
const values = sales.getRange(row, 1, 1, Math.max(sales.getLastColumn(), 32)).getValues()[0];
const sku = String(values[5] || '').trim();
const qty = num_(values[7]);
if (!sku || qty <= 0) return null;
if (!isActualSaleForCost_(values)) {
if (options.clearPending) sales.getRange(row, 12, 1, 2).clearContent();
sales.getRange(row, 30, 1, 3).setValues([['Відкладено', buildPendingCostAudit_(values), new Date()]]);
return null;
}
const formulas = sales.getRange(row, 12, 1, 2).getFormulas()[0];
const methodCell = String(sales.getRange(row, 30).getValue() || '').trim();
if (!formulas[0] && !formulas[1] && methodCell.indexOf('FIFO') === 0) return null;
const result = calculateFifoSaleCost_(ss, sku, qty, row, values[2], runState);
const autoConsumable = calculateAutoConsumableLineCost_(ss, values, row, runState);
if (autoConsumable.cost > 0) {
result.mgmtUnit = round2_(((result.mgmtUnit * qty) + autoConsumable.cost) / qty);
result.method = result.method + ' + авторозхідники';
result.audit = trimCostAudit_(result.audit + '; auto consumables: ' + autoConsumable.audit);
}
sales.getRange(row, 12, 1, 2).setValues([[result.prroUnit, result.mgmtUnit]]);
sales.getRange(row, 30, 1, 3).setValues([[result.method, result.audit, new Date()]]);
runState[sku] = num_(runState[sku]) + qty;
return result;
}

function calculateAutoConsumableLineCost_(ss, saleValues, currentRow, runState) {
runState = runState || {};
if (!runState.autoConsumablesByOrder) runState.autoConsumablesByOrder = {};
const orderKey = String(saleValues[0] || '').trim();
const saleDate = saleValues[2];
const sku = String(saleValues[5] || '').trim();
const name = String(saleValues[6] || '').trim();
const qty = num_(saleValues[7]);
if (!orderKey || qty <= 0) return { cost: 0, audit: '' };
let used = runState.autoConsumablesByOrder[orderKey];
if (!used) { used = getExistingAutoConsumableAudit_(ss, orderKey, currentRow); runState.autoConsumablesByOrder[orderKey] = used; }
let total = 0;
const audit = [];
if (!used.sticker) { const sticker = getAutoConsumableUnitCost_(ss, 'Стікер лого+QR', 'any', orderKey, currentRow, saleDate); if (sticker > 0) { total += sticker; audit.push('Стікер лого+QR=' + round2_(sticker)); } used.sticker = true; }
if (!used.blind && isMysteryBoxSale_(sku, name)) { const blind = getAutoConsumableUnitCost_(ss, 'Блайнд-пакет для картки', 'mystery', orderKey, currentRow, saleDate); if (blind > 0) { total += blind; audit.push('Блайнд-пакет для картки=' + round2_(blind)); } used.blind = true; } if (!used.mysteryLabel && isMysteryBoxSale_(sku, name)) { const mysteryLabel = getAutoConsumableUnitCost_(ss, 'Наліпка Mystery Box', 'mystery', orderKey, currentRow, saleDate); if (mysteryLabel > 0) { total += mysteryLabel; audit.push('Наліпка Mystery Box=' + round2_(mysteryLabel)); } used.mysteryLabel = true; }
return { cost: round2_(total), audit: audit.join(', ') };
}




function getExistingAutoConsumableAudit_(ss, orderKey, currentRow) {
const sales = ss.getSheetByName('Продажі');
const lastRow = Math.max(sales.getLastRow(), 3);
const values = sales.getRange(3, 1, lastRow - 2, 31).getValues();
const state = { sticker: false, blind: false, mysteryLabel: false };
values.forEach(function(row, index) {
const rowNumber = index + 3;
if (rowNumber >= currentRow) return;
if (String(row[0] || '').trim() !== orderKey) return;
const audit = String(row[30] || '');
if (audit.indexOf('Стікер лого+QR=') !== -1) state.sticker = true;
if (audit.indexOf('Блайнд-пакет для картки=') !== -1) state.blind = true; if (audit.indexOf('Наліпка Mystery Box=') !== -1) state.mysteryLabel = true;
});
return state;
}

function getAutoConsumableUnitCost_(ss, itemName, rule, currentOrderKey, currentRow, saleDate) {
const info = getAutoConsumableInfo_(ss, itemName);
if (!info || info.unitCost <= 0 || info.totalQty <= 0) return 0;
const startSort = getAutoConsumableStartSort_(ss, itemName, info.initialQty);
const saleSort = dateSortValue_(saleDate);
if (startSort && saleSort && saleSort < startSort) return 0;
const usedBefore = countAutoConsumableOrdersBefore_(ss, rule, currentOrderKey, currentRow, startSort, saleSort);
if (info.totalQty - usedBefore <= 0) return 0;
return info.unitCost;
}

function getAutoConsumableInfo_(ss, itemName) {
const sheet = ss.getSheetByName('Розхідники');
if (!sheet) return null;
const lastRow = Math.max(sheet.getLastRow(), 4);
const rows = sheet.getRange(4, 1, lastRow - 3, 9).getValues();
for (let i = 0; i < rows.length; i++) {
if (String(rows[i][0] || '').trim() !== itemName) continue;
return { unitCost: num_(rows[i][2]), initialQty: num_(rows[i][3]), totalQty: num_(rows[i][3]) + num_(rows[i][5]) };
}
return null;
}

function getAutoConsumableStartSort_(ss, itemName, initialQty) {
const activationSort = dateSortValue_(new Date(2026, 5, 2));
let startSort = initialQty > 0 ? activationSort : 0;
const expenses = ss.getSheetByName('Витрати');
if (expenses) {
const lastRow = Math.max(expenses.getLastRow(), 3);
const rows = expenses.getRange(3, 1, lastRow - 2, 10).getValues();
rows.forEach(function(row) {
if (String(row[7] || '').trim() !== itemName) return;
if (!{ 'На складі UA': true, 'На складі': true }[String(row[9] || '').trim()]) return;
const rowSort = dateSortValue_(row[0]);
if (rowSort && (!startSort || rowSort < startSort)) startSort = rowSort;
});
}
if (startSort && startSort < activationSort) return activationSort;
return startSort || activationSort;
}

function countAutoConsumableOrdersBefore_(ss, rule, currentOrderKey, currentRow, startSort, saleSort) {
const entries = _getCrmSalesRowEntries();
const orders = {};
entries.forEach(function(entry) {
const row = entry.values;
const rowNumber = entry.rowNumber;
const orderKey = String(row[0] || '').trim();
if (!orderKey || orderKey === currentOrderKey) return;
if (!isActualSaleForCost_(row)) return;
const rowSort = dateSortValue_(row[2]);
if (startSort && rowSort && rowSort < startSort) return;
if (saleSort && rowSort && (rowSort > saleSort || (rowSort === saleSort && rowNumber >= currentRow))) return;
if (!orders[orderKey]) orders[orderKey] = { hasMystery: false };
if (isMysteryBoxSale_(row[5], row[6])) orders[orderKey].hasMystery = true;
});
let count = 0;
Object.keys(orders).forEach(function(orderKey) {
if (rule === 'any' || orders[orderKey].hasMystery) count++;
});
return count;
}


function isMysteryBoxSale_(sku, name) {
const skuText = String(sku || '').trim().toUpperCase();
const nameText = String(name || '').toLowerCase();
return /-MBX$/.test(skuText) || nameText.indexOf('mystery box') !== -1 || nameText.indexOf('містері') !== -1;
}
function ensureSaleCostAuditColumns_(sales) {
if (_memo && _memo.costAuditColumnsEnsured) return;
if (sales.getMaxColumns() < 32) sales.insertColumnsAfter(sales.getMaxColumns(), 32 - sales.getMaxColumns());
const expected = ['Метод собівартості', 'Аудит собівартості', 'Дата фіксації собівартості'];
const headers = sales.getRange(2, 30, 1, 3).getValues()[0].map(function(value) { return String(value || '').trim(); });
let needsHeader = false; expected.forEach(function(header, index) { if (headers[index] !== header) needsHeader = true; });
if (needsHeader) sales.getRange(2, 30, 1, 3).setValues([expected]); sales.getRange(2, 30, sales.getMaxRows() - 1, 3).clearDataValidations();
if (_memo) _memo.costAuditColumnsEnsured = true;
}
function isActualSaleForCost_(values) {
const payment = String(values[22] || '').trim();
const status = String(values[23] || '').trim();
if (['Скасовано', 'Повернення'].indexOf(payment) !== -1) return false;
if (['Скасовано', 'Повернення', 'Передзамовлення'].indexOf(status) !== -1) return false;
return payment === 'Оплачено' || ['Нове', 'В обробці', 'Відправлено', 'Отримано'].indexOf(status) !== -1;
}

function buildPendingCostAudit_(values) {
return trimCostAudit_('Не зафіксовано: оплата=' + String(values[22] || '') + ', статус=' + String(values[23] || ''));
}

function calculateFifoSaleCost_(ss, sku, qty, currentRow, saleDate, runState) {
const batches = getFifoCostBatches_(ss, sku, saleDate);
const consumedStart = getConsumedQtyBeforeSale_(ss, sku, currentRow, saleDate) + getWriteOffQtyBeforeSale_(ss, sku, saleDate) + num_(runState[sku]);
let consumed = consumedStart;
let needed = qty;
let prroTotal = 0;
let mgmtTotal = 0;
let method = 'FIFO';
const audit = ['before=' + round2_(consumedStart)];
batches.forEach(function(batch) {
if (needed <= 0) return;
const skip = Math.min(consumed, batch.qty);
consumed = round2_(consumed - skip);
const available = round2_(batch.qty - skip);
if (available <= 0) return;
const take = Math.min(needed, available);
prroTotal += take * batch.prroUnit;
mgmtTotal += take * batch.mgmtUnit;
needed = round2_(needed - take);
audit.push(batch.lotId + ': ' + round2_(take) + ' x ' + round2_(batch.prroUnit) + '/' + round2_(batch.mgmtUnit));
});
if (needed > 0) {
const fallback = getCurrentCostFallback_(ss, sku);
prroTotal += needed * fallback.prro;
mgmtTotal += needed * fallback.mgmt;
audit.push('fallback: ' + round2_(needed) + ' x ' + round2_(fallback.prro) + '/' + round2_(fallback.mgmt) + ' (' + fallback.source + ')');
method = batches.length ? 'FIFO + fallback' : 'Fallback';
}
return { prroUnit: round2_(prroTotal / qty), mgmtUnit: round2_(mgmtTotal / qty), method: method, audit: trimCostAudit_(audit.join('; ')) };
}

function getFifoCostBatches_(ss, sku, saleDate) {
const purchases = ss.getSheetByName('Закупки');
if (!purchases) return [];
const saleSort = dateSortValue_(saleDate);
const allowed = { 'На складі UA': true, 'На складі': true, 'Частково продано': true, 'Продано': true };
const lastRow = Math.max(purchases.getLastRow(), 3);
const values = purchases.getRange(3, 1, lastRow - 2, 18).getValues();
const batches = [];
values.forEach(function(row, index) {
if (String(row[4] || '').trim() !== sku) return;
if (!allowed[String(row[16] || '').trim()]) return;
const batchSort = dateSortValue_(row[3]);
if (saleSort && batchSort && batchSort > saleSort) return; if (!batchSort) Logger.log('FIFO warning: lot ' + row[0] + ' (' + row[4] + ') has no delivery date — included as earliest');
const qty = num_(row[7]);
if (qty <= 0) return;
const prroUnit = num_(row[12]) || (qty ? num_(row[11]) / qty : 0);
const mgmtUnit = num_(row[15]) || (qty ? num_(row[14]) / qty : prroUnit);
batches.push({ row: index + 3, lotId: String(row[0] || ('row' + (index + 3))), qty: qty, prroUnit: prroUnit, mgmtUnit: mgmtUnit || prroUnit, sort: batchSort || index + 1 });
});
batches.sort(function(a, b) { return a.sort - b.sort || a.row - b.row; });
return batches;
}

function getConsumedQtyBeforeSale_(ss, sku, currentRow, saleDate) {
const entries = _getCrmSalesRowEntries();
const saleSort = dateSortValue_(saleDate);
const startSort = dateSortValue_(getCostStartDate_(ss));
let total = 0;
entries.forEach(function(entry) {
const row = entry.values;
const rowNumber = entry.rowNumber;
if (rowNumber === currentRow) return;
if (String(row[5] || '').trim() !== sku) return;
if (!isActualSaleForCost_(row)) return;
const rowSort = dateSortValue_(row[2]);
if (startSort && rowSort && rowSort < startSort) return;
if (saleSort && rowSort && (rowSort > saleSort || (rowSort === saleSort && rowNumber >= currentRow))) return;
total += num_(row[7]);
});
return total;
}


function getWriteOffQtyBeforeSale_(ss, sku, saleDate) {
const sheet = ss.getSheetByName('Списання');
if (!sheet) return 0;
const lastRow = Math.max(sheet.getLastRow(), 3);
const values = sheet.getRange(3, 1, lastRow - 2, 6).getValues();
const saleSort = dateSortValue_(saleDate);
let total = 0;
values.forEach(function(row) {
if (String(row[3] || '').trim() !== sku) return;
const rowSort = dateSortValue_(row[1]);
if (saleSort && rowSort && rowSort > saleSort) return;
total += num_(row[5]);
});
return total;
}

function getCurrentCostFallback_(ss, sku) {
const stock = ss.getSheetByName('Склад');
if (stock) {
const values = stock.getRange(3, 1, Math.max(stock.getLastRow() - 2, 1), 10).getValues();
for (let i = 0; i < values.length; i++) {
if (String(values[i][0] || '').trim() === sku) {
const prro = num_(values[i][8]);
const mgmt = num_(values[i][9]) || prro;
if (prro || mgmt) return { prro: prro, mgmt: mgmt, source: 'Склад I:J' };
}
}
}
const products = ss.getSheetByName('Товари');
if (products) {
const values = products.getRange(3, 1, Math.max(products.getLastRow() - 2, 1), 15).getValues();
for (let i = 0; i < values.length; i++) {
if (String(values[i][0] || '').trim() === sku) {
const fixed = num_(values[i][14]);
if (fixed) return { prro: fixed, mgmt: fixed, source: 'Товари O' };
}
}
}
return { prro: 0, mgmt: 0, source: 'немає даних' };
}

function getCostStartDate_(ss) {
const settings = ss.getSheetByName('Налаштування');
return settings ? settings.getRange('B8').getValue() : null;
}

function dateSortValue_(value) {
if (!value) return 0;
if (value instanceof Date && !isNaN(value.getTime())) return value.getTime();
if (typeof value === 'number') return value > 1000000000 ? value : (value - 25569) * 86400000;
const date = new Date(value);
return isNaN(date.getTime()) ? 0 : date.getTime();
}

function trimCostAudit_(text) {
text = String(text || '').trim();
return text.length > 450 ? text.slice(0, 447) + '...' : text;
}

function getPackagingCost_(packagingType, customValue) {
if (!packagingType) return null;
if (packagingType === 'Інше') return num_(customValue);
const sheet = SpreadsheetApp.getActive().getSheetByName('Розхідники');
if (!sheet) return 0;
const values = sheet.getRange(4, 1, Math.max(sheet.getLastRow() - 3, 1), 3).getValues();
for (let i = 0; i < values.length; i++) {
if (String(values[i][0] || '').trim() === packagingType) return num_(values[i][2]);
}
return 0;
}

function orderRowWeights_(sales, rows) {
const values = rows.map(function(row) { return Math.max(num_(sales.getRange(row, 11).getValue()), 0); });
const total = values.reduce(function(sum, value) { return sum + value; }, 0);
return total > 0 ? values : rows.map(function() { return 1; });
}

function isFirstOpenCartHistory_(payload) {
const histories = payload.histories || [];
return histories.length <= 1;
}


function handleTelegramUpdate_(payload) {
if (payload.callback_query) {
handleTelegramCallback_(payload.callback_query);
return;
}
if (!payload.message) return;
const chatId = String(payload.message.chat && payload.message.chat.id ? payload.message.chat.id : '');
const text = String(payload.message.text || '').trim();
if (text.indexOf('/debug_chat') === 0) { tgSendMessage_(chatId, 'debug chat_id=' + chatId + '\nallowed=' + String(PropertiesService.getScriptProperties().getProperty('TELEGRAM_ALLOWED_CHAT_ID') || '').trim()); return; }
if (!tgIsAllowedChat_(chatId)) return;
if (text.indexOf('/orders') === 0) { tgCommandOrders_(chatId); return; }
if (text.indexOf('/digest') === 0) { tgCommandDigest_(chatId); return; }
tgShowMainMenu_(chatId);
}

function tgCommandDigest_(chatId) {
try {
const result = newsDigest();
if (!result || !result.sent) tgSendMessage_(chatId, 'Немає нових новин');
return result;
} catch (err) {
const message = String(err && err.message ? err.message : err);
Logger.log('Telegram digest command failed: chat_id=' + chatId + ', error=' + message);
if (message.indexOf('newsDigest is already running') !== -1) {
tgSendMessage_(chatId, 'Дайджест уже формується. Спробуй за хвилину.');
return { ok: false, skipped: 'already_running' };
}
tgSendMessage_(chatId, 'Не вдалося сформувати дайджест. Спробуй пізніше.');
return { ok: false, error: message };
}
}

function handleTelegramCallback_(callbackQuery) {
const callbackId = String(callbackQuery.id || '');
const data = String(callbackQuery.data || '');
const message = callbackQuery.message || {};
const chatId = String(message.chat && message.chat.id ? message.chat.id : '');
const messageId = message.message_id;
if (!tgIsAllowedChat_(chatId)) { tgAnswerCallback_(callbackId, 'Немає доступу'); return; }
try {
if (data === 'main_menu') { tgAnswerCallback_(callbackId, ''); tgShowMainMenu_(chatId, messageId); return; } if (data === 'orders_list' || data === 'back_orders') { tgAnswerCallback_(callbackId, ''); tgCommandOrders_(chatId, messageId); return; }
if (data.indexOf('order_sel_') === 0) { tgAnswerCallback_(callbackId, ''); tgShowOrderDetails_(chatId, messageId, data.substring('order_sel_'.length)); return; }
if (data.indexOf('upd_delivery_') === 0) { tgAnswerCallback_(callbackId, 'Оновлюю...'); tgCommandUpdate_(chatId, data.substring('upd_delivery_'.length), 'delivery', messageId, ''); return; }
if (data.indexOf('upd_payment_') === 0) { tgAnswerCallback_(callbackId, 'Оновлюю...'); tgCommandUpdate_(chatId, data.substring('upd_payment_'.length), 'payment', messageId, ''); return; }
if (data.indexOf('upd_all_') === 0) { tgAnswerCallback_(callbackId, 'Оновлюю...'); tgCommandUpdate_(chatId, data.substring('upd_all_'.length), 'all', messageId, ''); return; }
if (data.indexOf('news_draft_') === 0) {
tgAnswerCallback_(callbackId, 'Готую чернетку...');
const newsShortId = data.substring('news_draft_'.length);
try {
if (!/^[A-Za-z0-9_-]{8,24}$/.test(newsShortId)) throw new Error('Invalid news draft id');
const newsItem = newsLoadDraftItem_(newsShortId);
if (!newsItem) {
tgSendMessage_(chatId, 'Ця новина вже недоступна. Запусти свіжий дайджест.');
return;
}
const newsDraft = tgDraftPost_(newsItem);
const newsDraftHeader = newsDraft.tag ? '<b>' + tgEscapeHtml_(newsDraft.tag) + '</b>\n\n' : '';
tgSendMessage_(chatId, newsDraftHeader + tgEscapeHtml_(newsDraft.text));
} catch (newsDraftErr) {
Logger.log('News draft callback failed: id=' + newsShortId + ', error=' + String(newsDraftErr && newsDraftErr.message ? newsDraftErr.message : newsDraftErr));
try { tgSendMessage_(chatId, 'Не вдалося створити чернетку. Перевір Apps Script Executions і спробуй ще раз.'); } catch (newsSendErr) { Logger.log('News draft error message failed: ' + String(newsSendErr && newsSendErr.message ? newsSendErr.message : newsSendErr)); }
}
return;
}
tgAnswerCallback_(callbackId, 'Невідома дія');
} catch (err) {
const messageText = String(err && err.message ? err.message : err); Logger.log('Telegram callback error: data=' + data + ', chat_id=' + chatId + ', message_id=' + messageId + ', error=' + messageText);
try { tgAnswerCallback_(callbackId, 'Помилка'); } catch (answerErr) { Logger.log('Telegram callback answer failed: ' + String(answerErr && answerErr.message ? answerErr.message : answerErr)); } try { if (messageId) tgEditMessage_(chatId, messageId, 'Помилка: ' + tgEscapeHtml_(messageText), null); } catch (editErr) { Logger.log('Telegram callback edit failed: ' + String(editErr && editErr.message ? editErr.message : editErr)); } throw err;
}
}

function tgCommandOrders_(chatId, messageId) {
const orders = crmGetOrders_('active', 20);
let text = orders.length ? '<b>Активні замовлення: ' + orders.length + '</b>' : 'Активних замовлень немає';
const keyboard = orders.map(function(order) { return [{ text: tgOrderButtonText_(order), callback_data: 'order_sel_' + order.order_id }]; }); keyboard.push([{ text: 'Назад', callback_data: 'main_menu' }]);
if (messageId) tgEditMessage_(chatId, messageId, text, keyboard); else tgSendMessage_(chatId, text, keyboard);
}

function tgShowOrderDetails_(chatId, messageId, orderId, statusText) {
const order = crmFindOrder_(orderId);
if (!order) { tgEditMessage_(chatId, messageId, 'Замовлення не знайдено: ' + tgEscapeHtml_(orderId), [[{ text: 'Назад', callback_data: 'back_orders' }, { text: 'На головну', callback_data: 'main_menu' }]]); return; }
const prefix = statusText ? '<b>' + tgEscapeHtml_(statusText) + '</b>\n' : ''; const text = prefix + tgOrderButtonText_(order) + '\nОплата: ' + tgEscapeHtml_(order.payment_status || '') + ' — ' + tgEscapeHtml_(order.payment_type || '') + '\nДоставка: ' + tgEscapeHtml_(order.order_status || '');
const keyboard = [[{ text: 'Оновити доставку', callback_data: 'upd_delivery_' + order.order_id }], [{ text: 'Оновити оплату', callback_data: 'upd_payment_' + order.order_id }], [{ text: 'Оновити все', callback_data: 'upd_all_' + order.order_id }], [{ text: 'Назад', callback_data: 'back_orders' }, { text: 'На головну', callback_data: 'main_menu' }]];
tgEditMessage_(chatId, messageId, text, keyboard);
}

function tgCommandUpdate_(chatId, orderId, mode, messageId, callbackId) {
const result = tgUpdateOrderStatus_(orderId, mode);
if (!result.found) { tgAnswerCallback_(callbackId, 'Замовлення не знайдено'); tgEditMessage_(chatId, messageId, 'Замовлення не знайдено: ' + tgEscapeHtml_(orderId), [[{ text: 'Назад', callback_data: 'back_orders' }]]); return; }
const answer = mode === 'delivery' ? 'Ок, Доставлено!' : (mode === 'payment' ? 'Ок, чик-чинь!' : 'Ок, чик-чинь! Доставлено');
if (callbackId) tgAnswerCallback_(callbackId, answer);
clearOrdersCache_(); invalidateDoGetCache_(); tgShowOrderDetails_(chatId, messageId, orderId, answer);
}

function tgUpdateOrderStatus_(orderId, mode) {
const ss = _getCrmSs();
const sales = ss.getSheetByName('Продажі');
const rows = findSaleRowsByOrder_(sales, orderId);
if (!rows.length) return { found: false, rows: 0 };
const costRunState = {};
rows.forEach(function(row) {
if (mode === 'payment' || mode === 'all') sales.getRange(row, 23).setValue('Оплачено');
if (mode === 'delivery' || mode === 'all') sales.getRange(row, 24).setValue('Отримано');
if (typeof fixSaleCostForRow_ === 'function') fixSaleCostForRow_(ss, row, costRunState, { clearPending: true });
});
return { found: true, rows: rows.length };
}

function tgSendMessage_(chatId, text, keyboard) {
const payload = { chat_id: chatId, text: text, parse_mode: 'HTML' };
if (keyboard && keyboard.length) payload.reply_markup = { inline_keyboard: keyboard };
tgBotApi_('sendMessage', payload);
}

function tgEditMessage_(chatId, messageId, text, keyboard) {
const payload = { chat_id: chatId, message_id: messageId, text: text, parse_mode: 'HTML' };
if (keyboard && keyboard.length) payload.reply_markup = { inline_keyboard: keyboard };
tgBotApi_('editMessageText', payload);
}

function tgAnswerCallback_(callbackId, text) {
if (!callbackId) return;
tgBotApi_('answerCallbackQuery', { callback_query_id: callbackId, text: text || '' });
}

function tgBotApi_(method, payload) {
const token = String(PropertiesService.getScriptProperties().getProperty('TELEGRAM_BOT_TOKEN') || '').trim();
if (!token) throw new Error('Missing TELEGRAM_BOT_TOKEN');
const response = UrlFetchApp.fetch('https://api.telegram.org/bot' + token + '/' + method, { method: 'post', contentType: 'application/json', payload: JSON.stringify(payload), muteHttpExceptions: true });
const code = response.getResponseCode();
const body = response.getContentText();
Logger.log('Telegram API ' + method + ' HTTP ' + code + ': ' + body);
let parsed;
try { parsed = JSON.parse(body); } catch (err) { throw new Error('Telegram API non-JSON HTTP ' + code + ': ' + body); }
if (code < 200 || code >= 300 || parsed.ok === false) throw new Error(body);
return parsed;
}


function tgIsAllowedChat_(chatId) {
const allowed = String(PropertiesService.getScriptProperties().getProperty('TELEGRAM_ALLOWED_CHAT_ID') || '').trim();
if (!allowed) { Logger.log('Telegram rejected: TELEGRAM_ALLOWED_CHAT_ID is empty; incoming chat_id=' + String(chatId)); return false; }
if (String(chatId) !== allowed) {
Logger.log('Telegram rejected chat_id=' + String(chatId) + '; allowed=' + allowed);
return false;
}
return true;
}

function tgOrderButtonText_(order) {
const ttn = order.ttn ? onlyDigits_(order.ttn).slice(-4) || 'NTTN' : 'NTTN';
return 'Сума: ' + formatUah_(order.amount) + ' грн | ' + ttn + ' | ' + tgEscapeHtml_(order.post || '');
}

function tgEscapeHtml_(value) {
return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function apiSummary_() {
const autoSs = _getAutoSs();
const report = autoSs.getSheetByName('Звіт_Продажів').getRange('A1:H40').getDisplayValues();
const quality = autoSs.getSheetByName('Якість_Даних').getRange('A1:E40').getDisplayValues();
const sales7 = apiFindRow_(report, 'Останні 7 днів');
const month = apiFindRow_(report, 'Поточний місяць');
const prevMonth = apiFindRow_(report, 'Попередній місяць');
// Warehouse profit is calculated from the same FIFO remainder as warehouse cost.
const salesRows = _getCrmSalesRows();
const period7d = apiSevenDayPeriodComparison_(salesRows);
const skuList = apiSkuList_({}, salesRows);
const rrcBySku = {};
(skuList.skus || []).forEach(function(item) {
const sku = String(item.sku || '').trim();
const rrc = apiNum_(item.rrc);
if (sku && rrc > 0) rrcBySku[sku] = rrc;
});

const crmSs = _getCrmSs();
const purchases = crmSs.getSheetByName('Закупки');
if (!purchases) throw new Error('Не знайдено вкладку Закупки.');
const lotLastRow = Math.max(purchases.getLastRow(), 2);
const lotLastCol = Math.min(purchases.getLastColumn(), 50);
const lotData = purchases.getRange(1, 1, lotLastRow, lotLastCol).getValues();
const requiredLotHeaders = ['SKU', 'Кількість одиниць', 'Управлінська собівартість 1 од.', 'Статус'];
let lotHeaderIndex = -1;
for (let i = 0; i < Math.min(lotData.length, 80); i++) {
const normalized = lotData[i].map(apiNormalizeHeader_);
if (requiredLotHeaders.every(function(header) {
return normalized.indexOf(apiNormalizeHeader_(header)) !== -1;
})) {
lotHeaderIndex = i;
break;
}
}
if (lotHeaderIndex === -1) throw new Error('Не знайдено очікувані заголовки вкладки Закупки.');

const lotHeaders = lotData[lotHeaderIndex].map(apiNormalizeHeader_);
function lotColumn_(name) {
return lotHeaders.indexOf(apiNormalizeHeader_(name));
}
const lotSkuCol = lotColumn_('SKU');
const lotQtyCol = lotColumn_('Кількість одиниць');
const lotCostCol = lotColumn_('Управлінська собівартість 1 од.');
const lotStatusCol = lotColumn_('Статус');
const lotDateCol = lotColumn_('Дата доставки в Україну');

const soldBySku = getSoldQtyBySkuForLotStatuses_(crmSs);
const writeOffBySku = getWriteOffQtyBySkuForUpdateCost_(crmSs);
const assetStatuses = { 'Замовлено': true, 'В дорозі': true, 'На складі в Японії': true, 'Виграно': true };
const fifoStatuses = { 'На складі UA': true, 'На складі': true, 'Частково продано': true, 'Продано': true };
const lotsBySku = {};
let nonWarehouseAssetCostTotal = 0;
let warehouseCostTotal = 0;
let warehouseProfitTotal = 0;
let assetProfitTotal = 0;
for (let r = lotHeaderIndex + 1; r < lotData.length; r++) {
const row = lotData[r];
const sku = String(row[lotSkuCol] || '').trim();
const status = String(row[lotStatusCol] || '').trim();
const qty = apiNum_(row[lotQtyCol]);
const cost = apiNum_(row[lotCostCol]);
if (!sku || qty <= 0) continue;

if (assetStatuses[status]) {
nonWarehouseAssetCostTotal += cost * qty;
const rrc = apiNum_(rrcBySku[sku]);
const lotProfit = (rrc - cost * 1.05) * qty;
if (rrc > 0) assetProfitTotal += lotProfit;
continue;
}

if (!fifoStatuses[status]) continue;
if (!lotsBySku[sku]) lotsBySku[sku] = [];
lotsBySku[sku].push({
row: r,
date: lotDateCol >= 0 ? dateSortValue_(row[lotDateCol]) : 0,
qty: qty,
cost: cost,
status: status
});
}


Object.keys(lotsBySku).forEach(function(sku) {
const lots = lotsBySku[sku];
lots.sort(function(a, b) {
return (a.date || 0) - (b.date || 0) || a.row - b.row;
});
let consumedLeft = apiNum_(soldBySku[sku]) + apiNum_(writeOffBySku[sku]);
lots.forEach(function(lot) {
const consumed = Math.min(lot.qty, Math.max(consumedLeft, 0));
consumedLeft = round2_(consumedLeft - consumed);
const qtyRemaining = round2_(lot.qty - consumed);
if (qtyRemaining > 0) {
const lotCost = lot.cost * qtyRemaining;
warehouseCostTotal += lotCost;
const rrc = apiNum_(rrcBySku[sku]);
if (rrc > 0) {
const lotProfit = (rrc - lot.cost * 1.05) * qtyRemaining;
warehouseProfitTotal += lotProfit;
assetProfitTotal += lotProfit;
}
}
});
});
const assetCostTotal = warehouseCostTotal + nonWarehouseAssetCostTotal;
const stockData = apiReadStockAlertsAndCounts_();
const pending = crmGetOrders_('active', 200).reduce(function(acc, order) {
acc.count++;
acc.total_amount += num_(order.amount);
return acc;
}, { count: 0, total_amount: 0 });
const stockCounts = apiCountSkuStock_(skuList.skus || []);
return {
ok: true,
as_of: new Date().toISOString(),
sales_7d: apiSalesSummary_(sales7),
sales_7d_period: period7d.current,
sales_prev_month_7d_period: period7d.previous,
sales_7d_period_label: period7d.label,
sales_prev_month_7d_period_label: period7d.prev_label,
sales_current_month: apiSalesSummary_(month),
sales_prev_month: prevMonth && prevMonth.length ? apiSalesSummary_(prevMonth) : null,
potential_profit_warehouse: round2_(warehouseProfitTotal),
warehouse_cost: round2_(warehouseCostTotal),
asset_cost: round2_(assetCostTotal),
asset_potential_profit: round2_(assetProfitTotal),
stock: {
total_sku: (skuList.skus || []).length,
ok: stockCounts.ok,
low: stockCounts.low,
out: stockCounts.out,
action_buy: stockData.counts.action_buy,
action_watch: stockData.counts.action_watch,
action_no_promote: stockData.counts.action_no_promote
},
data_quality: {
sales_without_sku: apiQualityCount_(quality, 'Продажі без SKU'),
mystery_boxes_without_writeoffs: apiQualityCount_(quality, 'Містері бокси без списання бустерів'),
negative_stock: apiQualityCount_(quality, 'Мінусовий залишок'),
source_ok: apiSourcesOk_(quality)
},
pending_orders: {
count: pending.count,
total_amount: round2_(pending.total_amount)
}
};
}

function apiOrders_(params) {
const status = String(params.status || 'active').trim() || 'active';
const limit = Math.max(1, Math.min(num_(params.limit) || 20, 500));
const orders = crmGetOrders_(status, limit, params);
return { ok: true, filter: status, count: orders.length, orders: orders };
}

function apiStockAlerts_() {
const data = apiReadStockAlertsAndCounts_();
return { ok: true, count: data.alerts.length, alerts: data.alerts };
}

function apiSkuList_(params, salesRows) { params = params || {};
const ss = _getAutoSs();
const objects = apiSheetObjects_(ss.getSheetByName('Майстер_Товарів'), ['SKU']); const metrics = apiSkuProfitMetrics_(salesRows); const stockMetrics = apiSkuStockMetrics_(); const rrcMetrics = apiSkuRrcMetrics_();
const skus = [];
objects.rows.forEach(function(row) {
const sku = apiObjVal_(row, ['SKU', 'Артикул']);
if (!sku) return;
const active = String(apiObjVal_(row, ['Активний', 'Active']) || '').trim().toLowerCase();
if (['так', 'true', 'yes', '1'].indexOf(active) === -1) return;
const issuesText = apiObjVal_(row, ['Якість даних', 'Проблеми', 'Issues']);
const skuName = row['Назва'] || row['Назва товару'] || row['Повна назва на сайті'] || ''; const metric = metrics[sku] || {}; const stockMetric = stockMetrics[sku] || {}; const rrcMetric = rrcMetrics[sku] || {}; const currentRrc = rrcMetric.rrc || apiNum_(apiObjVal_(row, ['Ціна CRM', 'РРЦ', 'Ціна'])); skus.push({ sku: sku, name: skuName, full_name: row['Повна назва на сайті'] || skuName, brand: apiObjVal_(row, ['Бренд', 'Brand']), format: apiObjVal_(row, ['Формат', 'Format']), rrc: currentRrc, price_crm: currentRrc, dynamic_rrc: rrcMetric.dynamic_rrc || 0, current_rrc_margin_pct: rrcMetric.margin_pct, rrc_cost_base_60d: rrcMetric.cost_base_60d || 0, price_opencart: apiNum_(apiObjVal_(row, ['Ціна OpenCart', 'OpenCart ціна', 'Feed price'])), stock: stockMetric.stock != null ? stockMetric.stock : apiNum_(apiObjVal_(row, ['Залишок', 'На складі', 'Stock'])), expected: stockMetric.expected != null ? stockMetric.expected : apiNum_(apiObjVal_(row, ['Очікується', 'В дорозі', 'Expected'])), stock_status: apiObjVal_(row, ['Статус залишку', 'Статус', 'Stock status']), url: apiObjVal_(row, ['URL', 'Посилання', 'Link']), issues: splitTags_(issuesText), sold_30d: metric.sold_30d != null ? metric.sold_30d : (stockMetric.sold_30d || 0), profit_30d: metric.profit_30d || 0, sold_60d: metric.sold_60d != null ? metric.sold_60d : (stockMetric.sold_60d || 0), profit_60d: metric.profit_60d || 0, action: stockMetric.action || '', urgency: stockMetric.urgency || '', max_buy_price: stockMetric.max_buy_price, margin_pct: stockMetric.margin_pct });
});
if (String(params.sort || '').toLowerCase() === 'profit') skus.sort(function(a, b) { return (b.profit_30d || 0) - (a.profit_30d || 0); }); const limit = Math.max(0, Math.min(apiNum_(params.limit) || 0, 500)); const resultSkus = limit ? skus.slice(0, limit) : skus; return { ok: true, count: resultSkus.length, skus: resultSkus };
}
function crmGetOrders_(status, limit, params) {
params = params || {}; const st = String(status || 'active').toLowerCase(); const days = Math.max(0, Math.min(num_(params.days) || 0, 3650)); const sortDir = String(params.sort || 'date_desc').toLowerCase() === 'date_asc' ? 'date_asc' : 'date_desc';
const cleanLimit = Math.max(1, Math.min(num_(limit) || 20, 500));
const cache = CacheService.getScriptCache();
const cacheKey = 'crm_orders_v3_' + crmOrdersCacheVersion_() + '_' + st + '_' + cleanLimit + '_' + days + '_' + sortDir;
const cached = cache.get(cacheKey);
if (cached) return JSON.parse(cached);
const ss = _getCrmSs();
const sales = ss.getSheetByName('Продажі');
const lastRow = Math.max(sales.getLastRow(), 3);
const values = sales.getRange(3, 1, lastRow - 2, 28).getValues();
const map = {};
values.forEach(function(row, index) { const orderId = String(row[0] || '').trim(); if (!orderId) return; if (!map[orderId]) map[orderId] = { order_id: orderId, date: apiDate_(row[2]), source: row[1] || '', payment_status: row[22] || '', order_status: row[23] || '', ttn: row[25] || '', post: row[24] || '', payment_type: row[27] || '', amount: 0, profit: 0, items_count: 0, skus: [], rows: [], sort: dateSortValue_(row[2]) || index };
const order = map[orderId]; order.amount = round2_(order.amount + num_(row[10])); order.profit = round2_(order.profit + num_(row[21])); order.items_count = round2_(order.items_count + num_(row[7]));
const sku = String(row[5] || '').trim(); if (sku && order.skus.indexOf(sku) === -1) order.skus.push(sku); order.rows.push(index + 3); });
let orders = Object.keys(map).map(function(key) { return map[key]; });
orders = orders.filter(function(order) { return crmOrderMatchesStatus_(order, st); }); if (days > 0) { const cutoff = new Date().getTime() - days * 86400000; orders = orders.filter(function(order) { return order.sort && order.sort >= cutoff; }); }
orders.sort(function(a, b) { return sortDir === 'date_asc' ? a.sort - b.sort : b.sort - a.sort; });
const result = orders.slice(0, cleanLimit).map(function(order) { delete order.sort; delete order.rows; return order; });
cache.put(cacheKey, JSON.stringify(result), 30);
return result;
}


function crmFindOrder_(orderId) {
const orders = crmGetOrders_('all', 500);
for (let i = 0; i < orders.length; i++) if (orders[i].order_id === orderId) return orders[i];
return null;
}

function crmOrderMatchesStatus_(order, status) {
const st = String(status || 'active').toLowerCase();
const orderStatus = String(order.order_status || '').trim();
const paymentStatus = String(order.payment_status || '').trim();
const terminal = ['Скасовано', 'Отримано', 'Повернення'].indexOf(orderStatus) !== -1;
if (st === 'all') return true; if (st === 'completed') return orderStatus === 'Отримано';
if (st === 'shipped') return orderStatus === 'Відправлено';
if (st === 'unpaid') return paymentStatus === 'Не оплачено' && !terminal;
return (orderStatus === 'Відправлено' || paymentStatus === 'Не оплачено') && !terminal;
}

function apiReadStockAlertsAndCounts_() {
const ss = _getAutoSs();
const objects = apiSheetObjects_(ss.getSheetByName('Черга_Складу'), ['Артикул', 'Дія', 'Покриття, днів']);
const alerts = [];
const counts = { action_buy: 0, action_watch: 0, action_no_promote: 0 };
objects.rows.forEach(function(row) {
const sku = apiObjVal_(row, ['SKU', 'Артикул']);
const action = apiObjVal_(row, ['Дія', 'Рекомендована дія', 'Рішення', 'Що робити']);
if (!sku || !action) return;
if (action.indexOf('Докупить') !== -1 || action.indexOf('Докупити') !== -1) counts.action_buy++;
if (action.indexOf('Пильнувати') !== -1) counts.action_watch++;
if (action === 'Не просувати') counts.action_no_promote++;
if (action === 'Можна просувати' || action === 'Не просувати') return;
alerts.push({ sku: sku, name: apiObjVal_(row, ['Товар', 'Назва', 'Назва товару']), action: action, urgency: apiObjVal_(row, ['Терміновість', 'Пріоритет', 'Urgency']), stock: apiNum_(apiObjVal_(row, ['Залишок', 'На складі', 'Stock'])), expected: apiNum_(apiObjVal_(row, ['Очікується', 'В дорозі', 'Expected'])), sold_30d: apiNum_(apiObjVal_(row, ['Продано 30 днів', 'Продажі 30д', '30 днів', 'sold_30d'])), price: apiNum_(apiObjVal_(row, ['Ціна продажу', 'Ціна', 'РРЦ', 'Price'])), max_buy_price: apiNum_(apiObjVal_(row, ['Гранична закупка', 'Макс. ціна закупки', 'Макс закупка', 'max_buy_price'])), margin_pct: apiPercent_(apiObjVal_(row, ['Маржа %', 'Маржа', 'margin_pct'])) });
});
const urgencyOrder = { 'Терміново': 1, 'Висока': 2, 'Середня': 3, 'Низька': 4 };
alerts.sort(function(a, b) { return (urgencyOrder[a.urgency] || 9) - (urgencyOrder[b.urgency] || 9); });
return { alerts: alerts, counts: counts };
}

function apiSheetObjects_(sheet, requiredHeaders) {
if (!sheet) return { headers: [], rows: [] };
const maxRows = Math.min(sheet.getLastRow(), 2000); const maxCols = Math.min(sheet.getLastColumn(), 50); if (maxRows < 1 || maxCols < 1) return { headers: [], rows: [] }; const data = sheet.getRange(1, 1, maxRows, maxCols).getDisplayValues();
let headerIndex = -1;
for (let i = 0; i < Math.min(data.length, 80); i++) {
const normalized = data[i].map(apiNormalizeHeader_);
const found = requiredHeaders.every(function(header) { return normalized.indexOf(apiNormalizeHeader_(header)) !== -1; });
if (found) { headerIndex = i; break; }
}
if (headerIndex === -1) return { headers: [], rows: [] };
const headers = data[headerIndex];
const rows = [];
for (let r = headerIndex + 1; r < data.length; r++) {
if (!data[r].some(function(cell) { return String(cell || '').trim(); })) continue;
const obj = {};
headers.forEach(function(header, c) { if (header) obj[header] = data[r][c]; });
rows.push(obj);
}
return { headers: headers, rows: rows };
}

function apiObjVal_(obj, aliases) {
const keys = Object.keys(obj || {});
for (let a = 0; a < aliases.length; a++) {
const want = apiNormalizeHeader_(aliases[a]);
for (let k = 0; k < keys.length; k++) {
const have = apiNormalizeHeader_(keys[k]);
if (have === want || have.indexOf(want) !== -1 || want.indexOf(have) !== -1) return obj[keys[k]];
}
}
return '';
}

function apiFindRow_(rows, label) {
for (let i = 0; i < rows.length; i++) if (String(rows[i][0] || '').trim() === label) return rows[i];
return [];
}

function apiSalesSummary_(row) {
return { orders: apiNum_(row[1]), units: apiNum_(row[2]), revenue: apiNum_(row[3]), profit: apiNum_(row[4]), margin_pct: apiPercent_(row[5]) };
}

function apiQualityCount_(rows, label) {
const row = apiFindRow_(rows, label);
return apiNum_(row[2]);
}

function apiSourcesOk_(rows) {
return rows.filter(function(row) { return String(row[0] || '').indexOf('Підключено') === 0; }).every(function(row) { return String(row[1] || '').trim() === 'ОК'; });
}

function apiCountSkuStock_(skus) {
return skus.reduce(function(acc, sku) { const status = String(sku.stock_status || '').toLowerCase(); if (status === 'ок' || status === 'ok') acc.ok++; else if (status.indexOf('мало') !== -1) acc.low++; else if (status.indexOf('немає') !== -1 || status.indexOf('out') !== -1) acc.out++; return acc; }, { ok: 0, low: 0, out: 0 });
}

function apiDate_(value) {
if (value instanceof Date && !isNaN(value.getTime())) return Utilities.formatDate(value, Session.getScriptTimeZone(), 'yyyy-MM-dd');
if (typeof value === 'number') return Utilities.formatDate(new Date((value - 25569) * 86400000), Session.getScriptTimeZone(), 'yyyy-MM-dd');
return String(value || '').trim();
}

function apiNum_(value) {
if (value === '' || value == null) return 0;
if (typeof value === 'number') return value;
const text = String(value).replace(/\s/g, '').replace(',', '.').replace(/[^0-9.\-]/g, '');
return Number(text) || 0;
}

function apiPercent_(value) {
const n = apiNum_(value);
return String(value || '').indexOf('%') === -1 && n > 0 && n <= 1 ? round2_(n * 100) : n;
}

function apiNormalizeHeader_(value) {
return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
}

function splitTags_(value) {
return String(value || '').split(/[;,\s]+/).map(function(item) { return item.trim(); }).filter(Boolean);
}

function onlyDigits_(value) {
return String(value || '').replace(/\D/g, '');
}

function formatUah_(value) {
const n = round2_(num_(value));
return n % 1 === 0 ? String(n) : n.toFixed(2).replace('.', ',');
}

function testTelegramSend_() {
const props = PropertiesService.getScriptProperties();
const token = String(props.getProperty('TELEGRAM_BOT_TOKEN') || '').trim();
const chatId = String(props.getProperty('TELEGRAM_ALLOWED_CHAT_ID') || '').trim();
if (!token) throw new Error('Missing TELEGRAM_BOT_TOKEN');
if (!chatId) throw new Error('Missing TELEGRAM_ALLOWED_CHAT_ID');
Logger.log('testTelegramSend_: chat_id=' + chatId);
tgBotApi_('sendMessage', { chat_id: chatId, text: 'Booster CRM test: Telegram send works.' });
}

function tgIncomingChatId_(payload) {
if (payload.callback_query && payload.callback_query.message && payload.callback_query.message.chat) return String(payload.callback_query.message.chat.id || '');
if (payload.message && payload.message.chat) return String(payload.message.chat.id || '');
return ''; 
}

function tgIncomingText_(payload) {
if (payload.callback_query) return String(payload.callback_query.data || '');
if (payload.message) return String(payload.message.text || '');
return ''; 
}

function tgShowMainMenu_(chatId, messageId) {
const text = 'Booster CRM';
const keyboard = [[{ text: 'Активні замовлення', callback_data: 'orders_list' }]];
if (messageId) tgEditMessage_(chatId, messageId, text, keyboard); else tgSendMessage_(chatId, text, keyboard);
}

function testTelegramSend() {
return testTelegramSend_();
}

function crmOrdersCacheVersion_() {
if (!_memo.cacheVersion) _memo.cacheVersion = String(PropertiesService.getScriptProperties().getProperty('CRM_ORDERS_CACHE_VERSION') || '1');
return _memo.cacheVersion;
}

function clearOrdersCache_() { const version = String(new Date().getTime()); PropertiesService.getScriptProperties().setProperty('CRM_ORDERS_CACHE_VERSION', version); if (typeof _memo !== 'undefined' && _memo) _memo.cacheVersion = version; }


function updateLotStatuses() {
const ss = _getCrmSs();
const purchases = ss.getSheetByName('Закупки');
if (!purchases) throw new Error('Не знайдено вкладку Закупки.');
const updatable = { 'На складі UA': true, 'На складі': true, 'Частково продано': true };
const allowedForFifo = { 'На складі UA': true, 'На складі': true, 'Частково продано': true, 'Продано': true };
const lastRow = Math.max(purchases.getLastRow(), 3);
const values = purchases.getRange(3, 1, lastRow - 2, 17).getValues();
const soldBySku = getSoldQtyBySkuForLotStatuses_(ss);
const lotsBySku = {};
values.forEach(function(row, index) {
const sku = String(row[4] || '').trim();
const status = String(row[16] || '').trim();
if (!sku || !allowedForFifo[status]) return;
const qty = num_(row[7]);
if (qty <= 0) return;
if (!lotsBySku[sku]) lotsBySku[sku] = [];
lotsBySku[sku].push({ rowNumber: index + 3, qty: qty, status: status, sort: dateSortValue_(row[3]) || 9000000000000 + index });
});
const changes = [];
Object.keys(lotsBySku).forEach(function(sku) {
const lots = lotsBySku[sku].sort(function(a, b) { return a.sort - b.sort || a.rowNumber - b.rowNumber; });
let remainingSold = num_(soldBySku[sku]);
lots.forEach(function(lot) {
const soldFromLot = Math.min(Math.max(remainingSold, 0), lot.qty);
remainingSold = round2_(remainingSold - soldFromLot);
let nextStatus = lot.status === 'На складі UA' ? 'На складі UA' : 'На складі';
if (soldFromLot >= lot.qty) nextStatus = 'Продано';
else if (soldFromLot > 0) nextStatus = 'Частково продано';
if (updatable[lot.status] && lot.status !== nextStatus) changes.push({ row: lot.rowNumber, status: nextStatus });
});
});
changes.forEach(function(change) {
purchases.getRange(change.row, 17).setValue(change.status);
});
updateSkuCurrentCost_(ss); const message = 'Статуси лотів оновлено: ' + changes.length + ' змін.';
try { SpreadsheetApp.getActive().toast(message, 'Booster CRM', 5); } catch (e) { Logger.log(message); }
return { checkedSku: Object.keys(lotsBySku).length, changed: changes.length };
}

function getSoldQtyBySkuForLotStatuses_(ss) {
const sales = ss.getSheetByName('Продажі');
if (!sales) throw new Error('Не знайдено вкладку Продажі.');
const lastRow = Math.max(sales.getLastRow(), 3);
const values = sales.getRange(3, 1, lastRow - 2, 29).getValues();
const soldBySku = {};
values.forEach(function(row) {
if (!isActualSaleForCost_(row)) return;
const sku = String(row[5] || '').trim();
if (!sku) return;
soldBySku[sku] = round2_(num_(soldBySku[sku]) + num_(row[7]));
});
return soldBySku;
}

function runNightlyInventoryMaintenance() {
const lock = LockService.getScriptLock();
if (!lock.tryLock(30000)) throw new Error('Нічне оновлення складу вже виконується.');
try { const result = updateLotStatuses(); clearOrdersCache_(); invalidateDoGetCache_(); Logger.log('Nightly inventory maintenance done: ' + JSON.stringify(result)); return result; }
finally { lock.releaseLock(); }
}

function createDailyInventoryMaintenanceTrigger() {
const handler = 'runNightlyInventoryMaintenance'; let exists = false;
ScriptApp.getProjectTriggers().forEach(function(trigger) { const fn = trigger.getHandlerFunction(); if (fn === handler) exists = true; if (fn === 'updateLotStatuses') ScriptApp.deleteTrigger(trigger); });
if (!exists) ScriptApp.newTrigger(handler).timeBased().everyDays(1).atHour(5).create();
const message = exists ? 'Щоденний тригер склад/FIFO вже існує.' : 'Щоденний тригер склад/FIFO створено.';
try { SpreadsheetApp.getUi().alert(message); } catch (e) { Logger.log(message); }
return message;
}
function createDailyLotStatusTrigger() { return createDailyInventoryMaintenanceTrigger(); }
function updateSkuCurrentCostMenu() {
const ss = SpreadsheetApp.getActiveSpreadsheet();
updateSkuCurrentCost_(ss);
SpreadsheetApp.getUi().alert('Собівартість складу оновлено.');
}

function updateSkuCurrentCost_(ss) {
if (!ss) ss = SpreadsheetApp.getActiveSpreadsheet();
const purchases = ss.getSheetByName('Закупки');
const sklad = ss.getSheetByName('Склад');
if (!purchases) throw new Error('Не знайдено вкладку Закупки.');
if (!sklad) throw new Error('Не знайдено вкладку Склад.');
const allowed = { 'На складі UA': true, 'На складі': true, 'Частково продано': true, 'Продано': true };
const lotLastRow = Math.max(purchases.getLastRow(), 3);
const lotRows = purchases.getRange(3, 1, lotLastRow - 2, 17).getValues();
const soldBySku = getSoldQtyBySkuForLotStatuses_(ss);
const writeOffBySku = getWriteOffQtyBySkuForUpdateCost_(ss);
const lotsBySku = {};
lotRows.forEach(function(row, index) {
const sku = String(row[4] || '').trim();
const status = String(row[16] || '').trim();
if (!sku || !allowed[status]) return;
const qty = num_(row[7]);
if (qty <= 0) return;
if (!lotsBySku[sku]) lotsBySku[sku] = [];
lotsBySku[sku].push({ row: index + 3, date: dateSortValue_(row[3]), qty: qty, prro: num_(row[12]) || (qty ? num_(row[11]) / qty : 0), mgmt: num_(row[15]) || (qty ? num_(row[14]) / qty : 0) });
});
Object.keys(lotsBySku).forEach(function(sku) { lotsBySku[sku].sort(function(a, b) { return (a.date || 0) - (b.date || 0) || a.row - b.row; }); });
const skladLastRow = Math.max(sklad.getLastRow(), 3);
const rowCount = skladLastRow - 2;
if (rowCount <= 0) return { updated: 0 };
const skladRows = sklad.getRange(3, 1, rowCount, 10).getValues();
const costs = sklad.getRange(3, 9, rowCount, 2).getValues();
let updated = 0;
skladRows.forEach(function(row, index) {
const sku = String(row[0] || '').trim();
const lots = sku ? (lotsBySku[sku] || []) : [];
if (!sku || !lots.length) return;
let consumedLeft = num_(soldBySku[sku]) + num_(writeOffBySku[sku]);
let remainQty = 0;
let prroTotal = 0;
let mgmtTotal = 0;
lots.forEach(function(lot) {
const consumed = Math.min(lot.qty, Math.max(consumedLeft, 0));
consumedLeft = round2_(consumedLeft - consumed);
const inLot = round2_(lot.qty - consumed);
if (inLot > 0) {
remainQty += inLot;
prroTotal += inLot * lot.prro;
mgmtTotal += inLot * (lot.mgmt || lot.prro);
}
});
if (remainQty > 0) {
costs[index] = [round2_(prroTotal / remainQty), round2_(mgmtTotal / remainQty)];
updated++;
}
});
if (updated) sklad.getRange(3, 9, rowCount, 2).setValues(costs);
Logger.log('updateSkuCurrentCost_: updated ' + updated + ' SKUs');
return { updated: updated };
}

function getWriteOffQtyBySkuForUpdateCost_(ss) {
const sheet = ss.getSheetByName('Списання');
if (!sheet) return {};
const lastRow = Math.max(sheet.getLastRow(), 3);
const values = sheet.getRange(3, 1, lastRow - 2, 6).getValues();
const result = {};
values.forEach(function(row) {
const sku = String(row[3] || '').trim();
if (!sku) return;
result[sku] = round2_(num_(result[sku]) + num_(row[5]));
});
return result;
}

function apiReadCrmSalesRows_() {
return _getCrmSalesRows();
}





function apiPotentialWarehouseProfit_() {
try {
const autoSs = _getAutoSs();
const dash = autoSs.getSheetByName('Дашборд');
if (dash) {
const found = apiFindMetricValue_(dash.getRange('A1:Z120').getDisplayValues(), 'Потенційний прибуток складу');
if (found != null) return found;
}
} catch (err) { Logger.log('apiPotentialWarehouseProfit_ dashboard fallback: ' + String(err && err.message ? err.message : err)); }
const ss = _getCrmSs();
const sklad = ss.getSheetByName('Склад');
if (!sklad) return 0;
const lastRow = Math.max(sklad.getLastRow(), 3);
const values = sklad.getRange(3, 14, lastRow - 2, 1).getDisplayValues();
const total = values.reduce(function(sum, row) { return sum + apiNum_(row[0]); }, 0);
return round2_(total);
}

function apiFindMetricValue_(rows, label) {
for (let r = 0; r < rows.length; r++) {
for (let c = 0; c < rows[r].length; c++) {
if (String(rows[r][c] || '').trim() !== label) continue;
for (let k = c + 1; k < rows[r].length; k++) {
if (String(rows[r][k] || '').trim() !== '') return apiNum_(rows[r][k]);
}
}
}
return null;
}

function apiSkuProfitMetrics_(salesRows) {
const rows = salesRows || _getCrmSalesRows();
const nowMs = new Date().getTime(); const cutoff30 = nowMs - 30 * 86400000; const cutoff60 = nowMs - 60 * 86400000;
const result = {};
rows.forEach(function(row) {
const sort = dateSortValue_(row[2]);
if (!sort || sort < cutoff60) return;
const sku = String(row[5] || '').trim();
if (!sku) return;
if (!result[sku]) result[sku] = { sold_30d: 0, sold_60d: 0, profit_30d: 0, profit_60d: 0 };
result[sku].sold_60d = round2_(result[sku].sold_60d + num_(row[7])); if (sort >= cutoff30) result[sku].sold_30d = round2_(result[sku].sold_30d + num_(row[7]));
result[sku].profit_60d = round2_(result[sku].profit_60d + num_(row[21])); if (sort >= cutoff30) result[sku].profit_30d = round2_(result[sku].profit_30d + num_(row[21]));
});
return result;
}

function apiChannelStats_(params) {
params = params || {};
const period = String(params.period || 'current_month').trim() === 'all_time' ? 'all_time' : 'current_month';
const rows = apiReadCrmSalesRows_();
const now = new Date();
const monthStart = new Date(now.getFullYear(), now.getMonth(), 1).getTime();
const fixedChannels = ['OpenCart','OLX','Telegram','Monobazar']; const map = {}; fixedChannels.forEach(function(name) { map[name] = { name: name, revenue: 0, profit: 0, orders: {}, units: 0 }; });
rows.forEach(function(row) {
const sort = dateSortValue_(row[2]);
if (period === 'current_month' && (!sort || sort < monthStart)) return;
const name = String(row[1] || 'Інше').trim() || 'Інше';
if (!map[name]) map[name] = { name: name, revenue: 0, profit: 0, orders: {}, units: 0 };
map[name].revenue = round2_(map[name].revenue + num_(row[10]));
map[name].profit = round2_(map[name].profit + num_(row[21]));
map[name].units = round2_(map[name].units + num_(row[7]));
map[name].orders[String(row[0] || '')] = true;
});
const channels = Object.keys(map).map(function(key) { const c = map[key]; return { name: c.name, revenue: round2_(c.revenue), profit: round2_(c.profit), margin_pct: c.revenue ? round2_(c.profit / c.revenue * 100) : 0, orders: Object.keys(c.orders).length, units: c.units }; });
channels.sort(function(a, b) { return b.revenue - a.revenue; });
return { ok: true, period: period, channels: channels };
}

function apiMonthlySummary_(params) {
params = params || {};
const requested = Math.max(1, Math.min(apiNum_(params.months) || 6, 24));
const rows = apiReadCrmSalesRows_();
const now = new Date();
const byMonth = {};
const months = [];
for (let i = requested - 1; i >= 0; i--) {
const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
const key = apiMonthKey_(d);
byMonth[key] = { month: key, label: apiMonthLabel_(d), revenue: 0, profit: 0, orders: 0, units: 0, orderMap: {} };
months.push(byMonth[key]);
}
rows.forEach(function(row) {
const sort = dateSortValue_(row[2]);
if (!sort) return;
const key = apiMonthKey_(new Date(sort));
const item = byMonth[key];
if (!item) return;
item.revenue = round2_(item.revenue + num_(row[10]));
item.profit = round2_(item.profit + num_(row[21]));
item.units = round2_(item.units + num_(row[7]));
item.orderMap[String(row[0] || '')] = true;
});
months.forEach(function(item) {
item.orders = Object.keys(item.orderMap).length;
item.margin_pct = item.revenue ? round2_(item.profit / item.revenue * 100) : 0;
delete item.orderMap;
});
return { ok: true, months: months, repeat_rate_pct: apiRepeatRateFromRows_(rows) };
}

function apiLtvReportLegacy_(params) {
params = params || {};
const limit = Math.max(1, Math.min(apiNum_(params.limit) || 10, 50));
const rows = apiReadCrmSalesRows_();
const clients = {};
rows.forEach(function(row) {
const key = apiCustomerKey_(row);
if (!key) return;
if (!clients[key]) clients[key] = { display: apiCustomerDisplay_(row), orders: {}, units: 0, revenue: 0 };
clients[key].orders[String(row[0] || '')] = true;
clients[key].units = round2_(clients[key].units + num_(row[7]));
clients[key].revenue = round2_(clients[key].revenue + num_(row[10]));
});
const result = Object.keys(clients).map(function(key) { const c = clients[key]; return { identifier: c.display, display: c.display, orders: Object.keys(c.orders).length, units: c.units, revenue: round2_(c.revenue), ltv: round2_(c.revenue) }; });
result.sort(function(a, b) { return b.ltv - a.ltv; });
return { ok: true, limit: limit, clients: result.slice(0, limit) };
}

function apiRepeatRateFromRows_(rows) {
const clients = {};
let totalRows = 0;
let ignoredRows = 0;
rows.forEach(function(row) {
totalRows++;
const key = apiCustomerKey_(row);
if (!key) { ignoredRows++; return; }
if (!clients[key]) clients[key] = {};
clients[key][String(row[0] || '')] = true;
});
const keys = Object.keys(clients);
Logger.log('repeat_rate customers=' + keys.length + ', ignored_rows=' + ignoredRows + '/' + totalRows);
if (!keys.length) return null;
const repeat = keys.filter(function(key) { return Object.keys(clients[key]).length > 1; }).length;
return round2_(repeat / keys.length * 100);
}


function apiCustomerKey_(row) {
const phone = onlyDigits_(row[3]);
if (phone.length >= 7) return 'tel:' + phone;
const name = String(row[4] || '').trim().toLowerCase();
return name ? 'name:' + name : '';
}

function apiCustomerDisplay_(row) {
const phone = onlyDigits_(row[3]);
if (phone.length >= 7) return '+' + phone.slice(0, 5) + '...' + phone.slice(-4);
const name = String(row[4] || '').trim();
if (!name) return '—';
return name.length > 2 ? name.slice(0, Math.max(1, name.length - 2)) + '**' : '**';
}

function apiMonthKey_(date) {
return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
}

function apiMonthLabel_(date) {
const labels = ['Січень','Лютий','Березень','Квітень','Травень','Червень','Липень','Серпень','Вересень','Жовтень','Листопад','Грудень'];
return labels[date.getMonth()] || apiMonthKey_(date);
}

function apiSevenDayPeriodComparison_(salesRows) {
const rows = salesRows || _getCrmSalesRows();
const now = new Date();
const day = now.getDate();
const startDay = Math.floor((day - 1) / 7) * 7 + 1;
const endDay = Math.min(startDay + 6, new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate());
const currentStart = new Date(now.getFullYear(), now.getMonth(), startDay);
const currentEnd = new Date(now.getFullYear(), now.getMonth(), endDay + 1);
const prevMonthDate = new Date(now.getFullYear(), now.getMonth() - 1, 1);
const prevLastDay = new Date(prevMonthDate.getFullYear(), prevMonthDate.getMonth() + 1, 0).getDate();
const prevEndDay = Math.min(endDay, prevLastDay);
const prevStart = new Date(prevMonthDate.getFullYear(), prevMonthDate.getMonth(), startDay);
const prevEnd = new Date(prevMonthDate.getFullYear(), prevMonthDate.getMonth(), prevEndDay + 1);
return { current: apiAggregateSalesRows_(rows, currentStart.getTime(), currentEnd.getTime()), previous: apiAggregateSalesRows_(rows, prevStart.getTime(), prevEnd.getTime()), label: apiPeriodLabel_(currentStart, currentEnd), prev_label: apiPeriodLabel_(prevStart, prevEnd) };
}

function apiAggregateSalesRows_(rows, startMs, endMs) {
const orderMap = {};
const acc = { orders: 0, units: 0, revenue: 0, profit: 0, margin_pct: 0 };
rows.forEach(function(row) {
const sort = dateSortValue_(row[2]);
if (!sort || sort < startMs || sort >= endMs) return;
orderMap[String(row[0] || '')] = true;
acc.units = round2_(acc.units + num_(row[7]));
acc.revenue = round2_(acc.revenue + num_(row[10]));
acc.profit = round2_(acc.profit + num_(row[21]));
});
acc.orders = Object.keys(orderMap).length;
acc.margin_pct = acc.revenue ? round2_(acc.profit / acc.revenue * 100) : 0;
return acc;
}

function apiPeriodLabel_(startDate, endExclusive) {
const endDate = new Date(endExclusive.getTime() - 86400000);
return startDate.getDate() + '-' + endDate.getDate() + ' ' + apiMonthLabel_(startDate).toLowerCase();
}

function apiLtvReport_(params) {
params = params || {};
const limit = Math.max(1, Math.min(apiNum_(params.limit) || 10, 50));
const period = String(params.period || 'all_time').trim() === '60d' ? '60d' : 'all_time';
const cutoff = new Date().getTime() - 60 * 86400000;
const rows = apiReadCrmSalesRows_().filter(function(row) {
if (period !== '60d') return true;
const sort = dateSortValue_(row[2]);
return !!sort && sort >= cutoff;
});
const clients = {};
rows.forEach(function(row) {
const key = apiCustomerKey_(row);
if (!key) return;
if (!clients[key]) clients[key] = { display: apiCustomerDisplay_(row), name: apiCustomerName_(row), channels: {}, orders: {}, units: 0, revenue: 0, profit: 0 };
const channel = String(row[1] || 'Інше').trim() || 'Інше';
const revenue = num_(row[10]); const profit = num_(row[21]);
clients[key].orders[String(row[0] || '')] = true;
clients[key].units = round2_(clients[key].units + num_(row[7]));
clients[key].revenue = round2_(clients[key].revenue + revenue); clients[key].profit = round2_(clients[key].profit + profit);
clients[key].channels[channel] = round2_(num_(clients[key].channels[channel]) + revenue);
if (!clients[key].name || clients[key].name === '—') clients[key].name = apiCustomerName_(row);
});
const result = Object.keys(clients).map(function(key) { const c = clients[key]; return { identifier: c.display, display: c.display, name: c.name || '—', channel: apiTopChannel_(c.channels), orders: Object.keys(c.orders).length, units: c.units, revenue: round2_(c.revenue), profit: round2_(c.profit), ltv: round2_(c.revenue) }; });
result.sort(function(a, b) { return b.ltv - a.ltv; });
return { ok: true, period: period, limit: limit, clients: result.slice(0, limit) };
}

function apiCustomerName_(row) {
return String(row[4] || '').trim() || '—';
}

function apiTopChannel_(channels) {
const keys = Object.keys(channels || {});
if (!keys.length) return '—';
keys.sort(function(a, b) { return num_(channels[b]) - num_(channels[a]); });
return keys[0];
}

function apiSkuStockMetrics_() {
const ss = _getAutoSs();
const objects = apiSheetObjects_(ss.getSheetByName('Черга_Складу'), ['Артикул', 'Дія', 'Покриття, днів']);
const result = {};
objects.rows.forEach(function(row) {
const sku = apiObjVal_(row, ['SKU', 'Артикул']);
if (!sku) return;
result[sku] = { sold_30d: apiNum_(apiObjVal_(row, ['Продано 30д', 'Продано 30 днів', 'Продажі 30д', 'sold_30d'])), sold_60d: apiNum_(apiObjVal_(row, ['Продано 60д', 'Продано 60 днів', 'Продажі 60д', 'sold_60d'])), stock: apiNum_(apiObjVal_(row, ['Залишок', 'На складі', 'Stock'])), expected: apiNum_(apiObjVal_(row, ['Очікується після резерву', 'Очікується', 'В дорозі', 'Expected'])), max_buy_price: apiNum_(apiObjVal_(row, ['Гранична закупка', 'Макс. ціна закупки', 'max_buy_price'])), margin_pct: apiPercent_(apiObjVal_(row, ['Маржа %', 'Маржа', 'margin_pct'])), action: apiObjVal_(row, ['Дія', 'Рекомендована дія', 'Рішення', 'Що робити']), urgency: apiObjVal_(row, ['Терміновість', 'Пріоритет', 'Urgency']) };
});
return result;
}

function apiConsumables_(params) {
params = params || {};
const days = Math.max(1, Math.min(num_(params.days) || 30, 90));
const ss = _getCrmSs();
const sheet = ss.getSheetByName('Розхідники');
if (!sheet) throw new Error('Немає вкладки Розхідники');
const expenseSheet = ss.getSheetByName('Витрати');
const salesSheet = ss.getSheetByName('Продажі');
const lastRow = Math.max(sheet.getLastRow(), 4);
const rows = sheet.getRange(4, 1, lastRow - 3, 10).getValues();
const namesMap = {};
rows.forEach(function(row) { const name = String(row[0] || '').trim(); if (name) namesMap[name] = true; });
const latest = apiConsumableLatestPurchases_(expenseSheet, namesMap);
const used = apiConsumableUsage_(salesSheet, namesMap, days);
const cutoff = apiConsumableCutoff_(days);
const result = [];
rows.forEach(function(row) {
const name = String(row[0] || '').trim();
if (!name) return;
const category = String(row[1] || '').trim();
const fallbackCost = num_(row[2]);
const stock = Math.max(0, num_(row[8]));
const incoming = Math.max(0, num_(row[9]));
const last = latest[name] || null;
const used30 = used[name] || 0;
const daily = used30 / days;
const daysLeft = daily > 0 ? stock / daily : null;
const recentPurchase = !!(last && last.sort && last.sort >= cutoff);
if (!(stock > 0 || incoming > 0 || used30 > 0 || recentPurchase)) return;
result.push({ name: name, category: category, unit_cost: round2_((last && last.unit_cost) ? last.unit_cost : fallbackCost), stock: round2_(stock), incoming: round2_(incoming), used_30d: used30, daily_usage: round2_(daily), days_left: daysLeft == null ? null : round2_(daysLeft), last_purchase_date: last ? apiDate_(last.date) : '', last_purchase_qty: last ? round2_(last.qty) : 0, last_purchase_status: last ? last.status : '' });
});
result.sort(function(a, b) {
const ad = a.days_left == null ? 999999 : a.days_left;
const bd = b.days_left == null ? 999999 : b.days_left;
if (ad !== bd) return ad - bd;
return String(a.name).localeCompare(String(b.name), 'uk');
});
return { ok: true, days: days, count: result.length, consumables: result };
}

function apiConsumableCutoff_(days) {
const now = new Date();
return new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime() - (Math.max(days, 1) - 1) * 86400000;
}

function apiConsumableLatestPurchases_(expenseSheet, namesMap) {
const latest = {};
if (!expenseSheet) return latest;
const lastRow = Math.max(expenseSheet.getLastRow(), 3);
const values = expenseSheet.getRange(3, 1, lastRow - 2, 11).getValues();
values.forEach(function(row) {
const name = String(row[7] || '').trim();
if (!name || !namesMap[name]) return;
const qty = num_(row[8]);
if (qty <= 0) return;
const amount = num_(row[3]);
const unitCost = num_(row[10]) || (amount ? amount / qty : 0);
if (!unitCost) return;
const sort = dateSortValue_(row[0]);
if (!latest[name] || sort >= latest[name].sort) latest[name] = { date: row[0], sort: sort, qty: qty, unit_cost: unitCost, status: String(row[9] || '').trim() };
});
return latest;
}

function apiConsumableUsage_(salesSheet, namesMap, days) {
const usageOrders = {};
if (!salesSheet) return {};
const names = Object.keys(namesMap);
const lastRow = Math.max(salesSheet.getLastRow(), 3);
const values = salesSheet.getRange(3, 1, lastRow - 2, 31).getValues();
const cutoff = apiConsumableCutoff_(days);
values.forEach(function(row) {
const orderId = String(row[0] || '').trim();
if (!orderId) return;
const sort = dateSortValue_(row[2]);
if (!sort || sort < cutoff) return;
if (apiSaleRowIsCancelledForConsumables_(row)) return;
const packaging = String(row[28] || '').trim();
if (packaging && namesMap[packaging]) apiAddConsumableUsage_(usageOrders, packaging, orderId);
const audit = String(row[30] || '');
if (audit) names.forEach(function(name) { if (audit.indexOf(name + '=') !== -1) apiAddConsumableUsage_(usageOrders, name, orderId); });
});
const result = {};
Object.keys(usageOrders).forEach(function(name) { result[name] = Object.keys(usageOrders[name]).length; });
return result;
}

function apiSaleRowIsCancelledForConsumables_(row) {
const payment = String(row[22] || '').trim();
const order = String(row[23] || '').trim();
return ['Скасовано', 'Повернення'].indexOf(payment) !== -1 || ['Скасовано', 'Повернення'].indexOf(order) !== -1;
}

function apiAddConsumableUsage_(map, name, orderId) {
if (!map[name]) map[name] = {};
map[name][orderId] = true;
}

function apiSkuRrcMetrics_() {
const ss = _getCrmSs();
const rrc = apiReadRrcMap_(ss);
const costs = apiSkuCleanCostBase60d_();
const result = {};
Object.keys(rrc).forEach(function(sku) {
const item = rrc[sku] || {};
const cost = costs[sku] || {};
const costBase = cost.units ? cost.cost / cost.units : 0;
const dynamicRrc = item.dynamic_rrc || (costBase ? costBase / 0.7 : 0);
const marginPct = item.rrc && costBase ? (item.rrc - costBase) / item.rrc * 100 : null;
result[sku] = { rrc: round2_(item.rrc || 0), dynamic_rrc: round2_(dynamicRrc || 0), margin_pct: marginPct == null ? null : round2_(marginPct), cost_base_60d: round2_(costBase || 0) };
});
Object.keys(costs).forEach(function(sku) {
if (result[sku]) return;
const costBase = costs[sku].units ? costs[sku].cost / costs[sku].units : 0;
result[sku] = { rrc: 0, dynamic_rrc: round2_(costBase ? costBase / 0.7 : 0), margin_pct: null, cost_base_60d: round2_(costBase || 0) };
});
return result;
}

function apiReadRrcMap_(ss) {
const sheet = ss.getSheetByName('РРЦ');
const result = {};
if (!sheet) return result;
const lastRow = Math.max(sheet.getLastRow(), 3);
const values = sheet.getRange(3, 1, lastRow - 2, Math.min(sheet.getMaxColumns(), 8)).getValues();
values.forEach(function(row) {
const sku = String(row[0] || '').trim();
if (!sku) return;
result[sku] = { rrc: num_(row[4]), dynamic_rrc: num_(row[7]) };
});
return result;
}

function apiSkuCleanCostBase60d_() {
const cutoff = new Date().getTime() - 60 * 86400000;
const result = {};
apiReadCrmSalesRows_().forEach(function(row) {
const sort = dateSortValue_(row[2]);
if (!sort || sort < cutoff) return;
const sku = String(row[5] || '').trim();
if (!sku) return;
const qty = num_(row[7]);
if (qty <= 0) return;
if (!result[sku]) result[sku] = { units: 0, cost: 0 };
result[sku].units = round2_(result[sku].units + qty);
result[sku].cost = round2_(result[sku].cost + num_(row[14]) + num_(row[15]) + num_(row[16]) + num_(row[17]) + num_(row[18]) + num_(row[19]));
});
return result;
}
// retired 2026-07-03, MKT-TG-005 cleanup: tgCommandNews_, tgShowNewsPost_ removed (old /pick_news flow)

function tgSetupCommands() {
const chatId = String(PropertiesService.getScriptProperties().getProperty('TELEGRAM_ALLOWED_CHAT_ID') || '').trim();
if (!chatId) throw new Error('Missing TELEGRAM_ALLOWED_CHAT_ID');

return tgBotApi_('setMyCommands', {
    commands: [
      { command: 'start', description: 'Головне меню' },
      { command: 'digest', description: 'Свіжий дайджест новин' }
    ],
    scope: { type: 'chat', chat_id: chatId }
  });
}

function setupNewsDigestTrigger() {
const handler = 'newsDigest';
const retiredHandler = 'newsDigest_';
let existing = null;
let removedDuplicates = 0;
let removedLegacy = 0;

ScriptApp.getProjectTriggers().forEach(function(trigger) {
const triggerHandler = trigger.getHandlerFunction();
if (triggerHandler === retiredHandler) {
ScriptApp.deleteTrigger(trigger);
removedLegacy++;
return;
}
if (triggerHandler !== handler) return;
if (!existing) {
existing = trigger;
return;
}
ScriptApp.deleteTrigger(trigger);
removedDuplicates++;
});

const created = !existing;
if (created) {
existing = ScriptApp.newTrigger(handler).timeBased().everyDays(1).atHour(10).create();
}

const message = (created
? 'Щоденний тригер дайджесту створено приблизно на 10:00.'
: 'Щоденний тригер дайджесту вже існує.')
  + (removedDuplicates ? ' Видалено дублі: ' + removedDuplicates + '.' : '')
  + (removedLegacy ? ' Видалено старі тригери newsDigest_: ' + removedLegacy + '.' : '');
try { SpreadsheetApp.getUi().alert(message); } catch (e) { Logger.log(message); }
return { ok: true, created: created, removedDuplicates: removedDuplicates, removedLegacy: removedLegacy };
}

function apiAddNewsCandidate_(ss, payload) {
  try {
    const sheet = ss.getSheetByName('Новини_кандидати');
    if (!sheet) throw new Error('Новини_кандидати missing; run setupNewsSheet');
    const guid = String(payload.guid || '').trim();
    if (!guid) throw new Error('guid required');
    const last = sheet.getLastRow();
    if (last >= 2) {
      const guids = sheet.getRange(2, 11, last - 1, 1).getValues();
      for (let i = 0; i < guids.length; i++) {
        if (String(guids[i][0] || '').trim() === guid) return { ok: true, skipped: 'duplicate' };
      }
    }
    const id = 'NEWS-' + Utilities.formatDate(new Date(), Session.getScriptTimeZone(), 'yyyyMMdd-HHmmss') + '-' + Math.floor(Math.random() * 1000);
    sheet.getRange(last + 1, 1, 1, 11).setValues([[
      id, new Date(),
      String(payload.game || '').trim(),
      String(payload.title || '').trim(),
      String(payload.post_text || ''),
      String(payload.source_url || '').trim(),
      String(payload.image1 || '').trim(),
      String(payload.image2 || '').trim(),
      String(payload.image3 || '').trim(),
      'new', guid
    ]]);
    return { ok: true, id: id };
  } catch (err) { return { ok: false, error: String(err && err.message ? err.message : err) }; }
}
// MKT-TG-005: lean RSS -> Telegram digest with on-demand Anthropic drafts.
const NEWS_DIGEST_FRESH_DAYS = 3;
const NEWS_DIGEST_DRAFT_TTL_DAYS = 7;
const NEWS_DIGEST_SEEN_TTL_DAYS = 30;
const NEWS_DIGEST_ITEM_PREFIX = 'MKT_TG_005_ITEM_';
const NEWS_DIGEST_SEEN_PREFIX = 'MKT_TG_005_SEEN_';
const NEWS_DIGEST_ANTHROPIC_MODEL = 'claude-sonnet-4-6'; // 2026-07-03: switched from Haiku — owner-supplied proven prompt/model from the old Make pipeline (invented claims, russianisms, stray non-UA token on Haiku).
const NEWS_DIGEST_ARTICLE_MAX_CHARS = 6000; // 2026-07-03: full-article text for on-demand drafts, capped to keep cost/quality sane.
const NEWS_DIGEST_SOURCES = [
  {
    key: 'pokemon',
    tag: 'Pokémon TCG',
    count: 1,
    url: 'https://news.google.com/rss/search?q=%22pokemon+tcg%22&hl=en&gl=US&ceid=US:en'
  },
  {
    key: 'one_piece',
    tag: 'One Piece CG',
    count: 1,
    url: 'https://news.google.com/rss/search?q=%22one+piece+card+game%22&hl=en&gl=US&ceid=US:en'
  },
  {
    key: 'mtg_ygo',
    tag: 'MTG / YGO',
    count: 1,
    url: 'https://news.google.com/rss/search?q=%28%22magic+the+gathering%22+OR+%22yu-gi-oh%22%29&hl=en&gl=US&ceid=US:en'
  },
  {
    key: 'tcg_market',
    tag: 'TCG Market',
    count: 2,
    url: 'https://news.google.com/rss/search?q=%28TCG+OR+%22trading+card+game%22%29+%28market+OR+industry%29&hl=en&gl=US&ceid=US:en'
  }
];

function newsDigest() {
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(5000)) throw new Error('newsDigest is already running');

  try {
    const properties = PropertiesService.getScriptProperties();
    const chatId = String(properties.getProperty('TELEGRAM_ALLOWED_CHAT_ID') || '').trim();
    if (!chatId) throw new Error('Missing TELEGRAM_ALLOWED_CHAT_ID');

    newsPruneDigestProperties_(properties);

    const now = new Date();
    const nowMs = now.getTime();
    const cutoffMs = nowMs - NEWS_DIGEST_FRESH_DAYS * 86400000;
    const feedResponses = UrlFetchApp.fetchAll(NEWS_DIGEST_SOURCES.map(function(source) {
      return {
        url: source.url,
        method: 'get',
        followRedirects: true,
        muteHttpExceptions: true,
        headers: { 'User-Agent': 'BoosterShop-MKT-TG-005/1.0' }
      };
    }));

    const selected = [];
    const selectedIds = {};
    NEWS_DIGEST_SOURCES.forEach(function(source, sourceIndex) {
      const response = feedResponses[sourceIndex];
      const code = response.getResponseCode();
      if (code < 200 || code >= 300) {
        Logger.log('News RSS failed: source=' + source.key + ', HTTP=' + code);
        return;
      }

      const freshItems = parseRssItems_(response.getContentText()).filter(function(item) {
        const publishedMs = item.pubDate instanceof Date ? item.pubDate.getTime() : NaN;
        if (!isFinite(publishedMs) || publishedMs < cutoffMs || publishedMs > nowMs + 3600000) return false;
        const identity = String(item.guid || item.link || '').trim();
        if (!identity) return false;
        item.newsId = newsDigestShortId_(identity);
        item.sourceKey = source.key;
        item.gameTag = source.tag;
        Logger.log('News RSS date: source=' + source.key + ', pubDate=' + item.pubDate.toISOString() + ', title=' + newsClipText_(item.title, 120));
        return !properties.getProperty(NEWS_DIGEST_SEEN_PREFIX + item.newsId);
      });

      freshItems.sort(function(a, b) {
        return b.pubDate.getTime() - a.pubDate.getTime();
      });

      freshItems.filter(function(item) {
        return !selectedIds[item.newsId];
      }).slice(0, source.count).forEach(function(item) {
        selectedIds[item.newsId] = true;
        item.sourceUrl = resolveGoogleNewsArticleUrl_(item.link);
        item.ogImage = fetchOgImage_(item.sourceUrl);
        selected.push(item);
      });
    });

    if (!selected.length) {
      Logger.log('newsDigest: no fresh unseen items');
      return { ok: true, sent: 0, skipped: 'no_fresh_items' };
    }

    const keyboard = [];
    selected.forEach(function(item, itemIndex) {
      const storedItem = {
        id: item.newsId,
        gameTag: item.gameTag,
        title: newsPlainText_(item.title),
        description: newsPlainText_(item.description),
        sourceUrl: item.sourceUrl || item.link,
        ogImage: item.ogImage || '',
        pubDate: item.pubDate.toISOString(),
        storedAt: nowMs
      };
      properties.setProperty(NEWS_DIGEST_ITEM_PREFIX + item.newsId, JSON.stringify(storedItem));
      keyboard.push([{ text: '✍️ Чернетка ' + (itemIndex + 1) + ' · ' + item.gameTag, callback_data: 'news_draft_' + item.newsId }]);
    });

    tgSendMessage_(chatId, newsBuildDigestMessage_(selected), keyboard);

    selected.forEach(function(item) {
      properties.setProperty(NEWS_DIGEST_SEEN_PREFIX + item.newsId, String(nowMs));
    });

    Logger.log('newsDigest: done, sent=' + selected.length);
    return { ok: true, sent: selected.length };
  } finally {
    lock.releaseLock();
  }
}

function parseRssItems_(xml) {
  const document = XmlService.parse(String(xml || ''));
  const root = document.getRootElement();
  const channel = root.getChild('channel');
  if (!channel) throw new Error('RSS channel not found');

  return channel.getChildren('item').map(function(item) {
    const title = newsXmlChildText_(item, 'title');
    const link = newsXmlChildText_(item, 'link');
    const pubDateText = newsXmlChildText_(item, 'pubDate');
    const guid = newsXmlChildText_(item, 'guid');
    const description = newsXmlChildText_(item, 'description');
    return {
      title: title,
      link: link,
      pubDate: new Date(pubDateText),
      guid: guid,
      description: description
    };
  }).filter(function(item) {
    return item.title && item.link;
  });
}

function newsXmlChildText_(parent, name) {
  const child = parent.getChild(name);
  return child ? String(child.getText() || '').trim() : '';
}

function fetchOgImage_(url) {
  url = String(url || '').trim();
  if (!/^https?:\/\//i.test(url)) return null;
  if (newsIsGoogleNewsUrl_(url)) {
    Logger.log('og:image skipped: unresolved Google News URL=' + newsClipText_(url, 180));
    return null;
  }

  try {
    const response = UrlFetchApp.fetch(url, {
      method: 'get',
      followRedirects: true,
      muteHttpExceptions: true,
      validateHttpsCertificates: true,
      headers: { 'User-Agent': 'Mozilla/5.0 (compatible; BoosterShop-MKT-TG-005/1.0)' }
    });
    const code = response.getResponseCode();
    if (code < 200 || code >= 300) {
      Logger.log('og:image fetch failed: HTTP=' + code + ', url=' + newsClipText_(url, 180));
      return null;
    }

    const html = response.getContentText();
    const propertyFirst = /<meta\b[^>]*\bproperty\s*=\s*["']og:image["'][^>]*\bcontent\s*=\s*["']([^"']+)["'][^>]*>/i;
    const contentFirst = /<meta\b[^>]*\bcontent\s*=\s*["']([^"']+)["'][^>]*\bproperty\s*=\s*["']og:image["'][^>]*>/i;
    const match = html.match(propertyFirst) || html.match(contentFirst);
    if (!match) return null;

    const imageUrl = newsDecodeHtml_(match[1]).trim();
    return /^https?:\/\//i.test(imageUrl) ? imageUrl : null;
  } catch (err) {
    Logger.log('og:image fetch error: url=' + newsClipText_(url, 180) + ', error=' + String(err && err.message ? err.message : err));
    return null;
  }
}

function resolveGoogleNewsArticleUrl_(url) {
  url = String(url || '').trim();
  if (!newsIsGoogleNewsUrl_(url)) return url;

  try {
    const pageResponse = UrlFetchApp.fetch(url, {
      method: 'get',
      followRedirects: true,
      muteHttpExceptions: true,
      validateHttpsCertificates: true,
      headers: { 'User-Agent': 'Mozilla/5.0 (compatible; BoosterShop-MKT-TG-005/1.0)' }
    });
    if (pageResponse.getResponseCode() < 200 || pageResponse.getResponseCode() >= 300) return url;

    const html = pageResponse.getContentText();
    const idMatch = html.match(/\bdata-n-a-id\s*=\s*["']([^"']+)["']/i);
    const timestampMatch = html.match(/\bdata-n-a-ts\s*=\s*["'](\d+)["']/i);
    const signatureMatch = html.match(/\bdata-n-a-sg\s*=\s*["']([^"']+)["']/i);
    if (!idMatch || !timestampMatch || !signatureMatch) {
      Logger.log('Google News decode metadata missing: url=' + newsClipText_(url, 180));
      return url;
    }

    const decodeRequest = [
      'garturlreq',
      [
        ['X', 'X', ['X', 'X'], null, null, 1, 1, 'US:en', null, 1, null, null, null, null, null, 0, 1],
        'X', 'X', 1, [1, 1, 1], 1, 1, null, 0, 0, null, 0
      ],
      idMatch[1],
      Number(timestampMatch[1]),
      signatureMatch[1]
    ];
    const batchRequest = [[['Fbv4je', JSON.stringify(decodeRequest), null, 'generic']]];
    const decodeResponse = UrlFetchApp.fetch('https://news.google.com/_/DotsSplashUi/data/batchexecute?rpcids=Fbv4je', {
      method: 'post',
      contentType: 'application/x-www-form-urlencoded;charset=utf-8',
      headers: { Referer: 'https://news.google.com/' },
      payload: 'f.req=' + encodeURIComponent(JSON.stringify(batchRequest)),
      muteHttpExceptions: true
    });
    if (decodeResponse.getResponseCode() < 200 || decodeResponse.getResponseCode() >= 300) return url;

    const body = decodeResponse.getContentText();
    const wrapperMatch = body.match(/\["wrb\.fr","Fbv4je","((?:\\.|[^"])*)"/);
    if (!wrapperMatch) {
      Logger.log('Google News decode response missing: url=' + newsClipText_(url, 180));
      return url;
    }

    const innerJson = JSON.parse('"' + wrapperMatch[1] + '"');
    const decoded = JSON.parse(innerJson);
    const decodedUrl = decoded && decoded[0] === 'garturlres' ? String(decoded[1] || '').trim() : '';
    if (!/^https?:\/\//i.test(decodedUrl) || newsIsGoogleNewsUrl_(decodedUrl)) return url;
    return decodedUrl;
  } catch (err) {
    Logger.log('Google News decode error: url=' + newsClipText_(url, 180) + ', error=' + String(err && err.message ? err.message : err));
    return url;
  }
}

function newsIsGoogleNewsUrl_(url) {
  return /^https?:\/\/news\.google\.com\/(?:rss\/)?(?:articles|read)\//i.test(String(url || ''));
}

function newsBuildDigestMessage_(items) {
  const lines = ['<b>Свіжі TCG-новини</b>', ''];
  items.forEach(function(item, index) {
    const title = tgEscapeHtml_(newsClipText_(newsPlainText_(item.title), 180));
    const sourceUrl = newsEscapeHtmlAttribute_(item.sourceUrl || item.link);
    lines.push((index + 1) + '. <b>[' + tgEscapeHtml_(item.gameTag) + ']</b> <a href="' + sourceUrl + '">' + title + '</a>');
    if (item.ogImage) {
      lines.push('<a href="' + newsEscapeHtmlAttribute_(item.ogImage) + '">🖼️ Фото зі статті</a>');
    }
    lines.push('');
  });
  lines.push('Натисни кнопку під потрібним пунктом — AI викликається тільки тоді.');
  return lines.join('\n');
}

function newsLoadDraftItem_(shortId) {
  const properties = PropertiesService.getScriptProperties();
  const key = NEWS_DIGEST_ITEM_PREFIX + String(shortId || '');
  const raw = properties.getProperty(key);
  if (!raw) return null;

  try {
    const item = JSON.parse(raw);
    const storedAt = Number(item.storedAt || 0);
    if (!storedAt || Date.now() - storedAt > NEWS_DIGEST_DRAFT_TTL_DAYS * 86400000) {
      properties.deleteProperty(key);
      return null;
    }
    return item;
  } catch (err) {
    properties.deleteProperty(key);
    return null;
  }
}

function fetchArticleText_(url) {
  url = String(url || '').trim();
  if (!/^https?:\/\//i.test(url) || newsIsGoogleNewsUrl_(url)) return '';

  try {
    const response = UrlFetchApp.fetch(url, {
      method: 'get',
      followRedirects: true,
      muteHttpExceptions: true,
      validateHttpsCertificates: true,
      headers: { 'User-Agent': 'Mozilla/5.0 (compatible; BoosterShop-MKT-TG-005/1.0)' }
    });
    if (response.getResponseCode() < 200 || response.getResponseCode() >= 300) return '';

    let html = response.getContentText();
    html = html
      .replace(/<script[\s\S]*?<\/script>/gi, ' ')
      .replace(/<style[\s\S]*?<\/style>/gi, ' ')
      .replace(/<!--[\s\S]*?-->/g, ' ')
      .replace(/<(nav|header|footer|aside|form)\b[\s\S]*?<\/\1>/gi, ' ');

    const articleMatch = html.match(/<article\b[\s\S]*?<\/article>/i);
    const bodyMatch = html.match(/<body\b[\s\S]*?<\/body>/i);
    const scope = articleMatch ? articleMatch[0] : (bodyMatch ? bodyMatch[0] : html);

    const text = newsPlainText_(scope);
    if (text.length < 200) return '';
    return text.length > NEWS_DIGEST_ARTICLE_MAX_CHARS ? text.slice(0, NEWS_DIGEST_ARTICLE_MAX_CHARS) + '…' : text;
  } catch (err) {
    Logger.log('Article text fetch error: url=' + newsClipText_(url, 180) + ', error=' + String(err && err.message ? err.message : err));
    return '';
  }
}

function tgDraftPost_(item) {
  const apiKey = String(PropertiesService.getScriptProperties().getProperty('ANTHROPIC_API_KEY') || '').trim();
  if (!apiKey) throw new Error('Missing ANTHROPIC_API_KEY');
  if (!item || !item.title) throw new Error('Missing RSS item');

  const systemPrompt = [
    'Ти редактор Telegram-групи магазину колекційних карт Booster Shop. Пиши лише українською, без русизмів.',
    'Якщо текст про одного випадкового користувача Reddit — пиши про явище або феномен, який він ілюструє, не про самого користувача.',
    'Формат: 2-4 абзаци, 60-180 слів залежно від кількості реальних деталей у матеріалі — якщо фактів мало, пиши коротше, не розтягуй порожніми фразами. 2-4 емодзі як акценти в тексті. Без markdown, без реклами магазину, без вигаданих деталей. Не починай з "На Reddit", "Хтось", "Якийсь".',
    'Короткі речення. Максимум 2 речення на абзац.',
    'Не пояснюй явище повністю — зачіпляй думку і залишай читача думати.',
    'Якщо є конкретні деталі (назва карти, ціна, дата) — обов\'язково використовуй їх. Не узагальнюй.',
    'Не пиши загальних істин, які підійшли б під будь-який товар ("колекціонери знають...", "поки ринок не відреагував", "це рідкісний момент"). Кожне речення — конкретний факт з цієї статті, не загальне міркування про ринок чи колекціонування.',
    'Не підміняй суть новини абстрактним висновком про "культурний феномен", "мейнстрим" чи подібне. Спочатку розкажи конкретний привід: подія, дата, товар, цифра. Абстрактна думка може бути останнім реченням, але не змістом усього поста.',
    'Уникай такого стилю:',
    '"Vintage holo — це карти ранніх сетів Pokémon TCG, де голографія наноситься особливим способом. Залежно від малюнку блиску розрізняють кілька типів: Cosmos Holo з хаотичними зірками, Sunburst Holo з променями від центру..."',
    'Це стаття Вікіпедії. Не пост.',
    'Те саме стосується будь-якого товару, не лише карт: не переказуй технічні характеристики (матеріал, конструкція, шари) як список властивостей з магазину. Обери один цікавий факт і подай його як деталь, а не специфікацію.',
    'Приклад якісного поста:',
    'Іноді забуваєш, наскільки вузькими бувають колекції Pokémon-карт 👀',
    'Наприклад, існують колекціонери, які роками збирають лише одного покемона. Цього разу в центрі уваги — Bulbasaur 🌱',
    'Нова 30th Promo вже з\'явилась у китайській версії приблизно за 18€, а японська ще навіть не вийшла. Серед тематичних колекціонерів вже почалися звичні роздуми: брати зараз чи чекати японський реліз? 🤔',
    'І найцікавіше тут навіть не питання ціни. Хтось збирає SAR ✨ Хтось полює на повні сети 📚 А хтось настільки сфокусований на одному покемоні, що нова версія Bulbasaur стає окремою подією для колекції 🌱'
  ].join('\n');
  const articleText = fetchArticleText_(item.sourceUrl);
  const bodyForPrompt = articleText || newsPlainText_(item.description);
  Logger.log('Draft input: id=' + item.id + ', usedFullArticle=' + !!articleText + ', bodyChars=' + bodyForPrompt.length);
  const userMessage = 'Ось стаття з RSS:\nЗаголовок: ' + newsPlainText_(item.title) +
    '\nТекст: ' + bodyForPrompt +
    '\nДжерело: ' + String(item.sourceUrl || '') +
    '\n\nНапиши пост для Telegram-групи українського TCG магазину.' +
    '\nВідповідь СУВОРО у форматі: перший рядок — 2-3 слова українською (тема новини, без крапки, без лапок), другий рядок рівно ===, далі сам пост за правилами вище.';

  const response = UrlFetchApp.fetch('https://api.anthropic.com/v1/messages', {
    method: 'post',
    contentType: 'application/json',
    headers: {
      'x-api-key': apiKey,
      'anthropic-version': '2023-06-01'
    },
    payload: JSON.stringify({
      model: NEWS_DIGEST_ANTHROPIC_MODEL,
      max_tokens: 400,
      temperature: 1,
      system: systemPrompt,
      messages: [{ role: 'user', content: userMessage }]
    }),
    muteHttpExceptions: true
  });

  const code = response.getResponseCode();
  let parsed;
  try {
    parsed = JSON.parse(response.getContentText());
  } catch (err) {
    throw new Error('Anthropic returned non-JSON HTTP ' + code);
  }
  if (code < 200 || code >= 300 || parsed.type === 'error') {
    const errorType = parsed && parsed.error && parsed.error.type ? String(parsed.error.type) : 'api_error';
    throw new Error('Anthropic HTTP ' + code + ' (' + errorType + ')');
  }

  const rawText = (parsed.content || []).filter(function(block) {
    return block && block.type === 'text';
  }).map(function(block) {
    return String(block.text || '');
  }).join('\n').trim();
  if (!rawText) throw new Error('Anthropic returned an empty draft');

  const parts = rawText.split(/\r?\n===\r?\n/);
  if (parts.length >= 2) {
    return { tag: parts[0].trim(), text: parts.slice(1).join('\n===\n').trim() };
  }
  return { tag: '', text: rawText };
}

function newsPruneDigestProperties_(properties) {
  const all = properties.getProperties();
  const nowMs = Date.now();
  Object.keys(all).forEach(function(key) {
    if (key.indexOf(NEWS_DIGEST_ITEM_PREFIX) === 0) {
      try {
        const item = JSON.parse(all[key]);
        if (!item.storedAt || nowMs - Number(item.storedAt) > NEWS_DIGEST_DRAFT_TTL_DAYS * 86400000) {
          properties.deleteProperty(key);
        }
      } catch (err) {
        properties.deleteProperty(key);
      }
    } else if (key.indexOf(NEWS_DIGEST_SEEN_PREFIX) === 0) {
      const seenAt = Number(all[key] || 0);
      if (!seenAt || nowMs - seenAt > NEWS_DIGEST_SEEN_TTL_DAYS * 86400000) {
        properties.deleteProperty(key);
      }
    }
  });
}

function newsDigestShortId_(value) {
  const digest = Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, String(value || ''), Utilities.Charset.UTF_8);
  return Utilities.base64EncodeWebSafe(digest).replace(/=+$/g, '').slice(0, 16);
}

function newsPlainText_(value) {
  return newsDecodeHtml_(String(value || '').replace(/<[^>]*>/g, ' '))
    .replace(/\s+/g, ' ')
    .trim();
}

function newsDecodeHtml_(value) {
  return String(value || '')
    .replace(/&amp;/gi, '&')
    .replace(/&quot;/gi, '"')
    .replace(/&#39;|&apos;/gi, "'")
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>')
    .replace(/&#(\d+);/g, function(match, decimal) {
      const codePoint = Number(decimal);
      return isFinite(codePoint) ? String.fromCodePoint(codePoint) : match;
    })
    .replace(/&#x([0-9a-f]+);/gi, function(match, hex) {
      const codePoint = parseInt(hex, 16);
      return isFinite(codePoint) ? String.fromCodePoint(codePoint) : match;
    });
}

function newsEscapeHtmlAttribute_(value) {
  return tgEscapeHtml_(String(value || ''))
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function newsClipText_(value, maxLength) {
  value = String(value || '');
  maxLength = Math.max(1, Number(maxLength) || 1);
  return value.length > maxLength ? value.slice(0, Math.max(1, maxLength - 1)) + '…' : value;
}
