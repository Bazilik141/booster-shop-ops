function sendTelegramMessage_(text) {
  const props = PropertiesService.getScriptProperties();
  const token = props.getProperty('TELEGRAM_BOT_TOKEN');
  const chatId = props.getProperty('TELEGRAM_CHAT_ID');
  if (!token) throw new Error('Missing TELEGRAM_BOT_TOKEN');
  if (!chatId) throw new Error('Missing TELEGRAM_CHAT_ID');

  const res = UrlFetchApp.fetch('https://api.telegram.org/bot' + token + '/sendMessage', {
    method: 'post',
    contentType: 'application/json',
    payload: JSON.stringify({ chat_id: chatId, text: text, disable_web_page_preview: true }),
    muteHttpExceptions: true
  });

  if (res.getResponseCode() < 200 || res.getResponseCode() >= 300) {
    throw new Error('Telegram send failed: ' + res.getResponseCode() + ' ' + res.getContentText());
  }
}

function testTelegramDelivery() {
  sendTelegramMessage_('Booster Shop automation test: Telegram delivery works.');
}

function dailyTroubleAlerts() {
  runTroubleAlerts_({ force: false });
}

function testDailyTroubleAlerts() {
  runTroubleAlerts_({ force: true });
}

function runTroubleAlerts_(options) {
  const issues = managedDataQualityIssues_().filter(function(issue) { return issue.status === 'active'; });

  if (issues.length === 0) {
    if (options.force) sendTelegramMessage_('Booster Shop watchdog test: проблем у Якість_Даних не знайдено.');
    logAutomationAlert_('daily_trouble_alerts', 'ok', 'No issues');
    return;
  }

  const signature = hashText_(issues.map(i => i.signature).join('|'));
  const props = PropertiesService.getScriptProperties();
  if (!options.force && signature === props.getProperty('LAST_DAILY_TROUBLE_SIGNATURE')) {
    logAutomationAlert_('daily_trouble_alerts', 'skipped', 'Same issues as previous alert');
    return;
  }

  const lines = issues.slice(0, 8).map((issue, i) => (i + 1) + '. ' + issue.text);
  let text = '[ALERT] Booster Shop: потрібна увага\n\nЗнайдено проблем: ' + issues.length + '\n\n' + lines.join('\n');
  if (issues.length > 8) text += '\n\nЩе проблем: ' + (issues.length - 8) + '. Дивись вкладку Якість_Даних.';

  sendTelegramMessage_(text);
  props.setProperty('LAST_DAILY_TROUBLE_SIGNATURE', signature);
  logAutomationAlert_('daily_trouble_alerts', 'sent', 'Issues: ' + issues.length);
}

function collectDataQualityIssues_() {
  const sheet = SpreadsheetApp.getActive().getSheetByName('Якість_Даних');
  if (!sheet) return [{ signature: 'missing_sheet_quality', id: hashText_('missing_sheet_quality'), title: 'Вкладка Якість_Даних', count: '1', details: 'Не знайдено вкладку Якість_Даних.', action: 'Перевірити структуру таблиці.', text: 'Не знайдено вкладку Якість_Даних.' }];

  const lastRow = Math.min(sheet.getLastRow(), 500);
  const lastCol = Math.min(sheet.getLastColumn(), 20);
  const range = lastRow && lastCol ? sheet.getRange(1, 1, lastRow, lastCol) : null;
  const values = range ? range.getDisplayValues() : [[]];
  const formulas = range ? range.getFormulas() : [[]];
  const headerRowIndex = findHeaderRow_(values);
  const headers = values[headerRowIndex].map(normalizeText_);

  const checkCol = findColumnLoose_(headers, ['перевірка', 'check']);
  const statusCol = findColumnLoose_(headers, ['статус', 'status']);
  const countCol = findColumnLoose_(headers, ['к-сть', 'кількість', 'count']);
  const detailsCol = findColumnLoose_(headers, ['деталі', 'правило', 'details', 'опис']);
  const actionCol = findColumnLoose_(headers, ['рекомендована дія', 'recommended action', 'action']);

  const issues = [];
  for (let r = headerRowIndex + 1; r < values.length; r++) {
    const row = values[r];
    if (!row.join(' ').trim()) continue;

    const statusNorm = normalizeText_(cell_(row, statusCol));
    const isInfoOnly = ['ok', 'ок', 'готово', 'інфо', 'info', 'тільки читання'].indexOf(statusNorm) >= 0;
    const isProblem = /помил|error|warning|потріб|перевір|review|увага|missing/.test(statusNorm);
    if (!isProblem || isInfoOnly) continue;

    const title = cell_(row, checkCol) || 'Проблема якості даних';
    const count = cell_(row, countCol);
    const details = cell_(row, detailsCol);
    const action = cell_(row, actionCol);

    if (normalizeText_(title).indexOf('мінусовий залишок') >= 0) {
      // Read the SKU-level source unconditionally in collectManagedIssueCandidates_.
      continue;
    }
    const formulaIssues = collectCountifQualityIssues_(countCol >= 0 ? cell_(formulas[r] || [], countCol) : '', title, details, action);
    if (formulaIssues.length) {
      Array.prototype.push.apply(issues, formulaIssues);
      continue;
    }

    let text = title;
    if (count && count !== '0') text += ' (' + count + ')';
    if (details) text += '\n   ' + details;
    if (action) text += '\n   Дія: ' + action;

    const signature = normalizeText_([title, count, details, action].join('|'));
    issues.push({ id: hashText_(signature), signature: signature, title: title, count: count, details: details, action: action, text: trimText_(text, 500) });
  }
  return issues;
}

function alertHeaderRow_(values, names) {
  for (let r = 0; r < Math.min(values.length, 10); r++) {
    const headers = values[r].map(normalizeText_);
    if (findColumnLoose_(headers, names) >= 0) return r;
  }
  return 0;
}

function alertDisplayNumber_(value) {
  const parsed = Number(String(value || '').replace(/\s/g, '').replace(',', '.'));
  return Number.isFinite(parsed) ? parsed : null;
}

function alertColumnNumber_(letters) {
  return String(letters || '').toUpperCase().split('').reduce(function(total, letter) { return total * 26 + letter.charCodeAt(0) - 64; }, 0);
}

function alertWildcardMatch_(value, criterion) {
  const escaped = String(criterion || '').replace(/[.+^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*').replace(/\?/g, '.');
  return new RegExp('^' + escaped + '$', 'i').test(String(value || ''));
}

function collectCountifQualityIssues_(formula, title, details, action) {
  const match = String(formula || '').match(/COUNTIF\s*\(\s*'?([^'!]+)'?!\$?([A-Z]+)\$?(\d*)\s*:\s*\$?[A-Z]+\$?\d*\s*[;,]\s*"([^"]+)"/i);
  if (!match) return [];
  const source = SpreadsheetApp.getActive().getSheetByName(match[1]);
  if (!source) return [];
  const column = alertColumnNumber_(match[2]), startRow = Math.max(1, Number(match[3] || 1)), lastRow = Math.min(source.getLastRow(), 2000), lastCol = Math.min(source.getLastColumn(), 20);
  if (!column || column > lastCol || lastRow < startRow) return [];
  const values = source.getRange(1, 1, lastRow, lastCol).getDisplayValues(), headerRow = alertHeaderRow_(values, ['sku', 'артикул']), headers = values[headerRow].map(normalizeText_);
  const skuCol = findColumnLoose_(headers, ['sku', 'артикул']), nameCol = findColumnLoose_(headers, ['назва', 'назва товару', 'повна назва на сайті']);
  if (skuCol < 0) return [];
  const result = [];
  for (let r = Math.max(headerRow + 1, startRow - 1); r < values.length; r++) {
    const marker = cell_(values[r], column - 1), sku = cell_(values[r], skuCol);
    if (!sku || !alertWildcardMatch_(marker, match[4])) continue;
    const reason = [details, 'Джерело: ' + match[1] + ' · ознака: ' + marker].filter(Boolean).join(' · '), nextAction = action || 'Перевірити вихідний рядок цього SKU.';
    const signature = normalizeText_(['quality_item', title, sku, marker, reason, nextAction].join('|'));
    result.push({ id:hashText_(signature), signature:signature, kind:'quality_item', title:title, sku:sku, name:cell_(values[r], nameCol), count:'1', details:reason, action:nextAction, text:trimText_(title + ' · ' + sku + '\n   ' + reason + '\n   Дія: ' + nextAction, 500) });
  }
  return result;
}

function collectNegativeStockIssues_(inventorySnapshot) {
  const reconciled = {};
  ((inventorySnapshot && inventorySnapshot.skus) || []).forEach(function(item) { if (item.sku) reconciled[String(item.sku)] = item; });
  const sheet = SpreadsheetApp.getActive().getSheetByName('Майстер_Товарів');
  if (!sheet) return [];
  const lastRow = Math.min(sheet.getLastRow(), 2000), lastCol = Math.min(sheet.getLastColumn(), 20);
  if (!lastRow || !lastCol) return [];
  const values = sheet.getRange(1, 1, lastRow, lastCol).getDisplayValues();
  const headerRow = alertHeaderRow_(values, ['sku', 'артикул']), headers = values[headerRow].map(normalizeText_);
  const skuCol = findColumnLoose_(headers, ['sku', 'артикул']);
  if (skuCol < 0) return [];
  const nameCol = findColumnLoose_(headers, ['назва', 'назва товару', 'повна назва на сайті']);
  const balanceCol = findColumnLoose_(headers, ['залишок', 'баланс crm', 'crm balance']);
  const issueCol = findColumnLoose_(headers, ['проблеми', 'проблема', 'алерти', 'issues', 'issue']);
  const inboundCol = findColumnLoose_(headers, ['очікується', 'в дорозі', 'incoming']);
  const reserveCol = findColumnLoose_(headers, ['резерв', 'зарезервовано', 'reserved']);
  const afterReserveCol = findColumnLoose_(headers, ['очікується після резерву', 'після резерву']);
  const result = [];
  for (let r = headerRow + 1; r < values.length; r++) {
    const row = values[r], sku = cell_(row, skuCol);
    if (!sku) continue;
    // The CRM stock formula has no manufacturing receipts. 3D negatives are
    // evaluated from the reconciled CRM/3D-P snapshot below.
    if (/^(?:BR|FIG|ACC-3D)-[A-Z0-9]/i.test(sku)) continue;
    const verified = reconciled[sku];
    if (verified && ((verified.stock_raw !== null && verified.stock_raw !== undefined && Number(verified.stock_raw) >= 0) || (verified.physical_stock !== null && verified.physical_stock !== undefined && Number(verified.physical_stock) >= 0))) continue;
    const balanceText = cell_(row, balanceCol >= 0 ? balanceCol : 11);
    const issueText = normalizeText_(cell_(row, issueCol >= 0 ? issueCol : 16));
    const balance = alertDisplayNumber_(balanceText);
    if (issueText.indexOf('мінусовий_залишок') < 0 && !(balance !== null && balance < 0)) continue;
    const inbound = cell_(row, inboundCol), reserve = cell_(row, reserveCol), afterReserve = cell_(row, afterReserveCol);
    const facts = ['Баланс CRM: ' + (balanceText || 'нижче нуля')];
    if (inbound) facts.push('закуплено, ще не на складі UA: ' + inbound + ' шт');
    if (reserve) facts.push('резерв: ' + reserve);
    if (afterReserve) facts.push('після резерву: ' + afterReserve);
    const details = facts.join(' · '), action = 'Перевірити продажі, списання, резерви та приходи цього SKU.';
    const signature = normalizeText_(['negative_stock', sku, details, action].join('|'));
    result.push({ id: hashText_(signature), signature: signature, kind: 'stock_negative', title: 'Мінусовий залишок', sku: sku, name: cell_(row, nameCol), count: '1', details: details, action: action, text: trimText_('Мінусовий залишок · ' + sku + '\n   ' + details + '\n   Дія: ' + action, 500) });
  }
  return result;
}

function collectStockQueueIssues_() {
  const sheet = SpreadsheetApp.getActive().getSheetByName('Черга_Складу');
  if (!sheet) return [];
  const groups = [
    { kind:'purchase', title:'Докупити', range:'A9:G14', action:'Перевірити потребу та створити закупку для цього SKU.' },
    { kind:'watch', title:'Пильнувати', range:'I18:O23', action:'Перевірити динаміку та залишок цього SKU.' },
    { kind:'pause_promo', title:'Не просувати', range:'A18:G23', action:'Не запускати додаткове просування, доки залишок не нормалізується.' }
  ];
  const result = [];
  groups.forEach(function(group) {
    sheet.getRange(group.range).getDisplayValues().forEach(function(row) {
      const sku = String(row[0] || '').trim();
      if (!sku || normalizeText_(sku) === 'артикул') return;
      if (/^(?:BR|FIG|ACC-3D)-[A-Z0-9]/i.test(sku)) return;
      const name = String(row[1] || '').trim();
      const incoming = row[5] === '' ? '—' : row[5] + ' шт';
      const details = ['Продажі 30д: ' + (row[3] || '—'), 'залишок: ' + (row[4] || '—'), 'закуплено, ще не на складі UA: ' + incoming].join(' · ');
      const signature = normalizeText_([group.kind, sku, details, group.action].join('|'));
      result.push({ id:hashText_(signature), signature:signature, kind:group.kind, title:group.title, sku:sku, name:name, count:'1', details:details, action:group.action, text:trimText_(group.title + ' · ' + sku + '\n   ' + details + '\n   Дія: ' + group.action, 500) });
    });
  });
  return result;
}

function collectManagedIssueCandidates_() {
  let snapshot = null, loadError = null;
  try { snapshot = crm016AlertInventorySnapshot_(); }
  catch (error) { loadError = error; }
  const stockIssues = snapshot ? collectNegativeStockIssues_(snapshot) : [];
  return collectDataQualityIssues_().concat(stockIssues, collectStockQueueIssues_(), collect3dpInventoryIssues_(snapshot, loadError)).slice(0, ALERT_API_MAX_);
}

function crm016AlertInventorySnapshot_() {
  const props = PropertiesService.getScriptProperties();
  const url = String(props.getProperty('BOOSTER_CRM_URL') || '').trim();
  const token = String(props.getProperty('BOOSTER_CRM_TOKEN') || '');
  if (!/\/exec(?:\?|$)/.test(url) || !token) throw new Error('CRM inventory connection is not configured');
  const response = UrlFetchApp.fetch(url + '?action=inventory_snapshot&token=' + encodeURIComponent(token), { method:'get', muteHttpExceptions:true });
  if (response.getResponseCode() !== 200) throw new Error('CRM inventory HTTP ' + response.getResponseCode());
  let payload;
  try { payload = JSON.parse(response.getContentText()); }
  catch (_) { throw new Error('CRM inventory did not return JSON'); }
  if (!payload || payload.ok !== true || !Array.isArray(payload.skus)) throw new Error('CRM inventory snapshot is invalid');
  return payload;
}

function collect3dpInventoryIssues_(snapshot, loadError) {
  if (!snapshot && !loadError) {
    try { snapshot = crm016AlertInventorySnapshot_(); }
    catch (error) { loadError = error; }
  }
  if (loadError) {
    const detail = String(loadError && loadError.message || loadError).slice(0, 120);
    const signature = '3dp_inventory_source_unavailable';
    return [{ id:hashText_(signature), signature:signature, kind:'3dp_source', title:'3D облік недоступний', count:'1', details:detail, action:'Перевірити підключення CRM та 3D-P.', text:'3D облік недоступний · ' + detail }];
  }
  if (snapshot.source_status !== 'ready') {
    const signature = '3dp_inventory_source_unavailable';
    return [{ id:hashText_(signature), signature:signature, kind:'3dp_source', title:'3D облік недоступний', count:'1', details:'CRM не отримала підтверджений залишок 3D-P.', action:'Перевірити підключення 3D-P і журнал виконань.', text:'3D облік недоступний · CRM не отримала підтверджений залишок 3D-P.' }];
  }
  const issues = [];
  (snapshot.skus || []).forEach(function(row) {
    if (!row.is_3dp) return;
    const sku = String(row.sku || '').trim();
    const name = String(row.name || '').trim();
    if (!sku) return;
    const saleSyncMissing = (row.issues || []).indexOf('3dp_sale_sync_missing') !== -1;
    if ((row.stock_error || row.stock_raw === null || row.stock_raw === undefined) && !saleSyncMissing) {
      const signature = '3dp_inventory_invalid|' + sku;
      issues.push({ id:hashText_(signature), signature:signature, kind:'3dp_inventory_invalid', title:'Не підтверджено залишок 3D', sku:sku, name:name, count:'1', details:'Дані 3D-P відсутні або не збігаються.', action:'Звірити SKU в CRM і 3D-P.', text:'Не підтверджено залишок 3D · ' + sku });
      return;
    }
    if (Number(row.stock_raw) < 0) {
      const signature = '3dp_print_needed|' + sku;
      issues.push({ id:hashText_(signature), signature:signature, kind:'3dp_print_needed', title:'Потрібен 3D-друк', sku:sku, name:name, count:String(Math.abs(Number(row.stock_raw))), details:'Після резерву: ' + row.stock_raw, action:'Надрукувати або перевірити резерв.', text:'Потрібен 3D-друк · ' + sku + ' · дефіцит ' + Math.abs(Number(row.stock_raw)) });
    }
    if (saleSyncMissing) {
      const exception = (snapshot.exceptions || []).filter(function(item) { return item.code === '3dp_sale_sync_missing' && String(item.sku || '').trim() === sku; })[0];
      const sources = exception && Array.isArray(exception.missing_fulfilled_sources) ? exception.missing_fulfilled_sources : [];
      const incident = sources.map(function(item) {
        return [String(item.crm_row || ''), String(item.order || ''), String(item.quantity || '')].join(':');
      }).filter(Boolean).sort().join(',');
      // Dismissal applies to these missing CRM sale rows; a later sale gets a new alert ID.
      const signature = '3dp_sale_sync_missing|' + sku + (incident ? '|' + incident : '');
      const unverified = (row.issues || []).indexOf('3dp_physical_unverified') !== -1;
      issues.push({ id:hashText_(signature), signature:signature, kind:'3dp_sale_sync_missing', title:'Продаж не синхронізовано з 3D', sku:sku, name:name, count:'1', details:unverified?'Продаж CRM не прив’язаний до 3D-списання; фізичний залишок не підтверджено.':'CRM продаж не має відповідного списання у 3D-P.', action:unverified?'Звірити окреме списання з продажем; не повторювати продаж навмання.':'Перевірити журнал синхронізації та FIFO до повтору.', text:'Продаж не синхронізовано з 3D · ' + sku });
    }
  });
  (snapshot.exceptions || []).forEach(function(item) {
    if (item.code !== '3dp_active_missing_from_crm_active_catalog') return;
    const sku = String(item.sku || '').trim();
    const signature = '3dp_catalog_gap|' + sku;
    issues.push({ id:hashText_(signature), signature:signature, kind:'3dp_catalog_gap', title:'3D SKU відсутній в активному CRM каталозі', sku:sku, count:'1', details:'3D-P позначає SKU активним.', action:'Звірити статуси й картку товару.', text:'Розбіжність 3D каталогу · ' + sku });
  });
  return issues;
}

function installDailyTroubleAlertTrigger() {
  ScriptApp.getProjectTriggers().forEach(trigger => {
    if (trigger.getHandlerFunction() === 'dailyTroubleAlerts') ScriptApp.deleteTrigger(trigger);
  });

  ScriptApp.newTrigger('dailyTroubleAlerts').timeBased().everyDays(1).atHour(9).inTimezone('Europe/Kiev').create();
  sendTelegramMessage_('Booster Shop: daily trouble alerts увімкнено. Перевірка щодня близько 09:00, тільки по проблемах.');
}

function logAutomationAlert_(scenario, status, details) {
  let sheet = SpreadsheetApp.getActive().getSheetByName('Лог_Алертів');
  if (!sheet) {
    sheet = SpreadsheetApp.getActive().insertSheet('Лог_Алертів');
    sheet.appendRow(['Дата', 'Сценарій', 'Статус', 'Деталі']);
  }
  sheet.appendRow([new Date(), scenario, status, details]);
}

function findHeaderRow_(values) {
  for (let r = 0; r < Math.min(values.length, 10); r++) {
    if (values[r].map(normalizeText_).some(c => c === 'статус' || c === 'status')) return r;
  }
  return 0;
}

function findColumnLoose_(headers, names) {
  for (let i = 0; i < headers.length; i++) if (names.indexOf(headers[i]) >= 0) return i;
  for (let i = 0; i < headers.length; i++) for (let n = 0; n < names.length; n++) if (headers[i].indexOf(names[n]) >= 0) return i;
  return -1;
}

function cell_(row, index) {
  return index >= 0 ? String(row[index] || '').trim() : '';
}

function normalizeText_(value) {
  return String(value || '').trim().toLowerCase();
}

function trimText_(value, maxLength) {
  value = String(value || '').trim();
  return value.length <= maxLength ? value : value.slice(0, maxLength - 3) + '...';
}

function hashText_(value) {
  return Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, value)
    .map(byte => ('0' + (byte < 0 ? byte + 256 : byte).toString(16)).slice(-2))
    .join('');
}
function testWeeklyOwnerSummary() {
  weeklyOwnerSummary_({ force: true });
}

function weeklyOwnerSummary() {
  weeklyOwnerSummary_({ force: false });
}

function weeklyOwnerSummary_(options) {
  const ss = SpreadsheetApp.getActive();

  const salesRow = findRowInRange_(ss, 'Звіт_Продажів', 'A1:H12', 'Останні 7 днів');
  const channelRows = getRows_(ss, 'Звіт_Каналів', 'A5:D7');

  const issues = typeof managedDataQualityIssues_ === 'function'
    ? managedDataQualityIssues_().filter(function(issue) { return issue.status === 'active'; })
    : [];

  const buyRows = getRows_(ss, 'Черга_Складу', 'A9:G14');
  const noPromoRows = getRows_(ss, 'Черга_Складу', 'A18:G23');
  const watchRows = getRows_(ss, 'Черга_Складу', 'I18:O23');
  const promoRows = getRows_(ss, 'Черга_Складу', 'I9:O14');

  let text = 'Booster Shop: тижневий підсумок\n\n';

  text += 'Продажі за 7 днів\n';
  if (salesRow.length) {
    text += '- Замовлення: ' + salesRow[1] + '\n';
    text += '- Одиниць: ' + salesRow[2] + '\n';
    text += '- Виручка: ' + salesRow[3] + '\n';
    text += '- Чистий прибуток: ' + salesRow[4] + '\n';
    text += '- Маржа: ' + salesRow[5] + '\n';
  } else {
    text += '- Немає даних у Звіт_Продажів.\n';
  }

  text += '\nКанали за 30 днів\n';
  channelRows.forEach(function(row) {
    if (row[0] && row[1]) {
      text += '- ' + row[0] + ': ' + row[1] + ' (' + row[2] + ')\n';
    }
  });

  text += '\nЯкість даних\n';
  if (issues.length) {
    text += '- Є проблеми: ' + issues.length + '\n';
    text += '- Перша: ' + compactLine_(issues[0].text) + '\n';
  } else {
    text += '- Критичних проблем не видно.\n';
  }

  text += '\nСклад і дії за динамікою 30 днів\n';
  text += formatStockBlock_('Докупити першими', buyRows, 3);
  text += formatStockBlock_('Не просувати продажі', noPromoRows, 3);
  text += formatStockBlock_('Пильнувати', watchRows, 2);
  text += formatStockBlock_('Можна просувати', promoRows, 3);

  sendTelegramMessage_(text);

  if (typeof logAutomationAlert_ === 'function') {
    logAutomationAlert_('weekly_owner_summary', 'sent', 'Weekly summary sent');
  }
}

function installWeeklyOwnerSummaryTrigger() {
  ScriptApp.getProjectTriggers().forEach(function(trigger) {
    if (trigger.getHandlerFunction() === 'weeklyOwnerSummary') {
      ScriptApp.deleteTrigger(trigger);
    }
  });

  ScriptApp.newTrigger('weeklyOwnerSummary')
    .timeBased()
    .onWeekDay(ScriptApp.WeekDay.MONDAY)
    .atHour(10)
    .inTimezone('Europe/Kiev')
    .create();

  sendTelegramMessage_('Booster Shop: тижневий підсумок увімкнено. Щопонеділка близько 10:00.');
}

function getRows_(ss, sheetName, rangeA1) {
  const sheet = ss.getSheetByName(sheetName);
  if (!sheet) return [];

  return sheet.getRange(rangeA1).getDisplayValues().filter(function(row) {
    return row.join('').trim() !== '';
  });
}

function findRowInRange_(ss, sheetName, rangeA1, label) {
  const rows = getRows_(ss, sheetName, rangeA1);

  for (let i = 0; i < rows.length; i++) {
    if (rows[i][0] === label) return rows[i];
  }

  return [];
}

function formatStockBlock_(title, rows, limit) {
  const cleanRows = rows.filter(function(row) {
    return row[0] && row[0] !== 'Артикул';
  }).slice(0, limit);

  let text = '\n' + title + '\n';

  if (!cleanRows.length) {
    return text + '- Немає позицій.\n';
  }

  cleanRows.forEach(function(row) {
    text += '- ' + row[0]
      + ': ' + row[3] + ' прод. 30д'
      + ', залишок ' + row[4]
      + ', закуплено, ще не на складі UA ' + row[5]
      + '\n';
  });

  return text;
}

function compactLine_(value) {
  return String(value || '').replace(/\s+/g, ' ').trim().slice(0, 220);
}


const ALERT_CONTROL_SHEET_ = '_Керування_Алертами';
const ALERT_CONTROL_HEADERS_ = ['Alert ID','Статус','Оновлено','Поточний підпис'];
const ALERT_API_MAX_ = 200;

function alertJson_(payload) {
  return ContentService.createTextOutput(JSON.stringify(payload)).setMimeType(ContentService.MimeType.JSON);
}

function alertApiAuthorized_(token) {
  const expected = PropertiesService.getScriptProperties().getProperty('BOOSTER_ALERTS_TOKEN');
  return !!expected && String(token || '') === expected;
}

function alertControlSheet_() {
  const ss = SpreadsheetApp.getActive();
  let sheet = ss.getSheetByName(ALERT_CONTROL_SHEET_);
  if (!sheet) {
    sheet = ss.insertSheet(ALERT_CONTROL_SHEET_);
    sheet.getRange(1, 1, 1, ALERT_CONTROL_HEADERS_.length).setValues([ALERT_CONTROL_HEADERS_]);
  }
  return sheet;
}

function alertStatusMap_() {
  const sheet = SpreadsheetApp.getActive().getSheetByName(ALERT_CONTROL_SHEET_), result = {};
  if (!sheet) return result;
  if (sheet.getLastRow() < 2) return result;
  sheet.getRange(2, 1, sheet.getLastRow() - 1, 2).getDisplayValues().forEach(function(row) {
    if (row[0]) result[String(row[0])] = String(row[1] || 'active') === 'dismissed' ? 'dismissed' : 'active';
  });
  return result;
}

function managedDataQualityIssues_() {
  const statuses = alertStatusMap_();
  const issues = collectManagedIssueCandidates_();
  const dates = alertFirstSeenDates_(issues);
  return issues.map(function(issue) {
    issue.id = issue.id || hashText_(issue.signature);
    issue.status = statuses[issue.id] || 'active';
    issue.first_seen = dates[alertTrackingKey_(issue)] || '';
    return issue;
  });
}

// First observed by this API, not a reconstructed historical incident date.
function alertTrackingKey_(issue) {
  return 'ALERT_FIRST_SEEN_' + hashText_([issue.kind || issue.title || '', issue.sku || '', issue.title || ''].join('|')).slice(0, 24);
}

function alertFirstSeenDates_(issues) {
  const properties = PropertiesService.getScriptProperties();
  const stored = properties.getProperties();
  const today = Utilities.formatDate(new Date(), Session.getScriptTimeZone(), 'yyyy-MM-dd');
  const live = {}, additions = {}, dates = {};
  (issues || []).forEach(function(issue) {
    const key = alertTrackingKey_(issue);
    live[key] = true;
    dates[key] = /^\d{4}-\d{2}-\d{2}$/.test(stored[key] || '') ? stored[key] : today;
    if (!stored[key]) additions[key] = today;
  });
  if (Object.keys(additions).length) properties.setProperties(additions, false);
  Object.keys(stored).filter(function(key) { return key.indexOf('ALERT_FIRST_SEEN_') === 0 && !live[key]; }).forEach(function(key) { properties.deleteProperty(key); });
  return dates;
}

function setManagedAlertStatus_(alertId, status) {
  alertId = String(alertId || '').trim();
  status = status === 'dismissed' ? 'dismissed' : 'active';
  if (!/^[a-f0-9]{64}$/.test(alertId)) throw new Error('INVALID_ALERT_ID');
  const current = collectManagedIssueCandidates_().filter(function(issue) { return (issue.id || hashText_(issue.signature)) === alertId; })[0];
  if (!current) throw new Error('ALERT_NOT_CURRENT');
  const lock = LockService.getScriptLock();
  lock.waitLock(10000);
  try {
    const sheet = alertControlSheet_(), last = sheet.getLastRow();
    const ids = last < 2 ? [] : sheet.getRange(2, 1, last - 1, 1).getDisplayValues().map(function(row) { return String(row[0]); });
    const index = ids.indexOf(alertId), row = index < 0 ? last + 1 : index + 2;
    sheet.getRange(row, 1, 1, 4).setValues([[alertId, status, new Date(), current.signature]]);
    PropertiesService.getScriptProperties().deleteProperty('LAST_DAILY_TROUBLE_SIGNATURE');
    return { ok:true, action:'set_alert_status', alert_id:alertId, status:status };
  } finally {
    lock.releaseLock();
  }
}

function setManagedAlertStatusBatch_(alertIds) {
  if (!Array.isArray(alertIds) || !alertIds.length || alertIds.length > 100) throw new Error('INVALID_ALERT_BATCH');
  const ids = Array.from(new Set(alertIds.map(function(id) { return String(id || '').trim(); })));
  if (ids.some(function(id) { return !/^[a-f0-9]{64}$/.test(id); })) throw new Error('INVALID_ALERT_ID');
  const lock = LockService.getScriptLock();
  lock.waitLock(10000);
  try {
    const current = {};
    collectManagedIssueCandidates_().forEach(function(issue) { current[issue.id || hashText_(issue.signature)] = issue; });
    if (ids.some(function(id) { return !current[id]; })) throw new Error('ALERT_NOT_CURRENT');
    const sheet = alertControlSheet_(), last = sheet.getLastRow();
    const values = last < 2 ? [] : sheet.getRange(2, 1, last - 1, 4).getValues();
    const byId = {};
    values.forEach(function(row, index) { if (row[0]) byId[String(row[0])] = index; });
    const changedAt = new Date();
    ids.forEach(function(id) {
      const next = [id, 'dismissed', changedAt, current[id].signature];
      if (Object.prototype.hasOwnProperty.call(byId, id)) values[byId[id]] = next;
      else { byId[id] = values.length; values.push(next); }
    });
    sheet.getRange(2, 1, values.length, 4).setValues(values);
    PropertiesService.getScriptProperties().deleteProperty('LAST_DAILY_TROUBLE_SIGNATURE');
    return { ok:true, action:'set_alert_status_batch', status:'dismissed', changed:ids.length, alert_ids:ids };
  } finally {
    lock.releaseLock();
  }
}

function doGet(e) {
  try {
    const p = e && e.parameter || {};
    if (!alertApiAuthorized_(p.token)) return alertJson_({ ok:false, error:'UNAUTHORIZED' });
    if (String(p.action || 'alerts') !== 'alerts') return alertJson_({ ok:false, error:'UNKNOWN_ACTION' });
    return alertJson_({ ok:true, action:'alerts', alerts:managedDataQualityIssues_() });
  } catch (error) {
    return alertJson_({ ok:false, error:String(error && error.message || error) });
  }
}

function doPost(e) {
  try {
    const payload = JSON.parse(String(e && e.postData && e.postData.contents || '{}'));
    if (!alertApiAuthorized_(payload.token)) return alertJson_({ ok:false, error:'UNAUTHORIZED' });
    if (payload.action === 'set_alert_status_batch') return alertJson_(setManagedAlertStatusBatch_(payload.alert_ids));
    if (payload.action !== 'set_alert_status') return alertJson_({ ok:false, error:'UNKNOWN_ACTION' });
    return alertJson_(setManagedAlertStatus_(payload.alert_id, payload.status));
  } catch (error) {
    return alertJson_({ ok:false, error:String(error && error.message || error) });
  }
}
