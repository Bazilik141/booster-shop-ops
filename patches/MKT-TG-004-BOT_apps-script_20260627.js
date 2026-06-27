/**
 * MKT-TG-004-BOT — Telegram candidate review additions.
 *
 * Integration points in the existing Apps Script source:
 *
 * 1) In handleTelegramUpdate_(), the existing /orders line becomes:
 *    if (text.indexOf('/orders') === 0) { tgCommandOrders_(chatId); return; }
 *    if (text.indexOf('/pick_news') === 0) { tgCommandNews_(chatId); return; }
 *    if (text.indexOf('/delete_news') === 0) { tgCleanNews_(chatId); return; }
 *
 * 2) In handleTelegramCallback_(), before the existing order_sel_ branch:
 *    if (data === 'news_list') { tgAnswerCallback_(callbackId, ''); tgCommandNews_(chatId, messageId); return; }
 *    if (data.indexOf('news_pick_') === 0) { tgAnswerCallback_(callbackId, ''); tgShowNewsPost_(chatId, data.substring('news_pick_'.length)); return; }
 *    if (data === 'news_clean') { tgAnswerCallback_(callbackId, 'Чищу...'); tgCleanNews_(chatId, messageId); return; }
 *    if (data.indexOf('news_done_') === 0) {
 *      const newsId = data.substring('news_done_'.length);
 *      const newsCandidate = crmGetNewsCandidates_(36500).filter(function(item) { return item.id === newsId; })[0];
 *      if (!newsCandidate) { tgAnswerCallback_(callbackId, 'Не знайдено'); return; }
 *      _getCrmSs().getSheetByName('Новини_кандидати').getRange(newsCandidate.rowIndex, 10).setValue('posted');
 *      tgAnswerCallback_(callbackId, 'Позначено');
 *      return;
 *    }
 *
 * 3) tgShowMainMenu_() keyboard:
 *    [
 *      [{ text: 'Активні замовлення', callback_data: 'orders_list' }],
 *      [{ text: 'Новини', callback_data: 'news_list' }],
 *      [{ text: 'Очистити новини (>3д)', callback_data: 'news_clean' }]
 *    ]
 */

function setupNewsSheet_() {
  const ss = _getCrmSs();
  const name = 'Новини_кандидати';
  const headers = ['id', 'created_at', 'game', 'title', 'post_text', 'source_url', 'image1', 'image2', 'image3', 'status', 'guid'];
  let sheet = ss.getSheetByName(name);

  if (sheet) {
    const actual = sheet.getRange(1, 1, 1, headers.length).getDisplayValues()[0].map(function(value) {
      return String(value || '').trim();
    });
    if (actual.join('\u001f') !== headers.join('\u001f')) {
      throw new Error('Unexpected header in ' + name + '!A1:K1');
    }
    return { ok: true, created: false, sheet: name };
  }

  sheet = ss.insertSheet(name);
  sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
  sheet.setFrozenRows(1);
  return { ok: true, created: true, sheet: name };
}

function crmGetNewsCandidates_(maxDays) {
  const ss = _getCrmSs();
  const sheet = ss.getSheetByName('Новини_кандидати');
  if (!sheet) throw new Error('Не знайдено вкладку Новини_кандидати. Запустіть setupNewsSheet_().');

  const headers = ['id', 'created_at', 'game', 'title', 'post_text', 'source_url', 'image1', 'image2', 'image3', 'status', 'guid'];
  const actual = sheet.getRange(1, 1, 1, headers.length).getDisplayValues()[0].map(function(value) {
    return String(value || '').trim();
  });
  if (actual.join('\u001f') !== headers.join('\u001f')) {
    throw new Error('Unexpected header in Новини_кандидати!A1:K1');
  }

  const lastRow = sheet.getLastRow();
  if (lastRow < 2) return [];

  const days = Math.max(1, Math.min(Number(maxDays == null ? 3 : maxDays) || 3, 36500));
  const cutoff = new Date().getTime() - days * 86400000;
  const candidates = [];

  sheet.getRange(2, 1, lastRow - 1, 11).getValues().forEach(function(row, index) {
    if (String(row[9] || '').trim().toLowerCase() !== 'new') return;
    const createdSort = dateSortValue_(row[1]);
    if (!createdSort || createdSort < cutoff) return;

    const id = String(row[0] || '').trim();
    if (!id) return;

    candidates.push({
      rowIndex: index + 2,
      id: id,
      game: String(row[2] || '').trim(),
      title: String(row[3] || '').trim(),
      post_text: String(row[4] || ''),
      source_url: String(row[5] || '').trim(),
      urls: row.slice(6, 9).map(function(value) {
        return String(value || '').trim();
      }).filter(Boolean),
      createdSort: createdSort
    });
  });

  candidates.sort(function(a, b) {
    return b.createdSort - a.createdSort || b.rowIndex - a.rowIndex;
  });

  return candidates.slice(0, 20).map(function(candidate) {
    delete candidate.createdSort;
    return candidate;
  });
}

function tgCommandNews_(chatId, messageId) {
  const candidates = crmGetNewsCandidates_(3);
  const text = candidates.length ? '<b>Підібрані пости: ' + candidates.length + '</b>' : 'Немає підібраних постів';
  const keyboard = candidates.map(function(candidate) {
    const rawTitle = candidate.title || candidate.id;
    const shortTitle = rawTitle.length > 40 ? rawTitle.slice(0, 37) + '...' : rawTitle;
    const label = (candidate.game ? candidate.game + ' · ' : '') + shortTitle;
    return [{ text: label, callback_data: 'news_pick_' + candidate.id }];
  });
  keyboard.push([{ text: 'Назад', callback_data: 'main_menu' }]);

  if (messageId) tgEditMessage_(chatId, messageId, text, keyboard);
  else tgSendMessage_(chatId, text, keyboard);
}

function tgShowNewsPost_(chatId, id) {
  id = String(id || '').trim();
  const candidate = crmGetNewsCandidates_(3).filter(function(item) {
    return item.id === id;
  })[0];

  if (!candidate) {
    tgSendMessage_(chatId, 'Пост не знайдено або він уже не має статусу new.');
    return;
  }
  if (!candidate.post_text) throw new Error('Порожній post_text для ' + id);

  tgSendMessage_(chatId, tgEscapeHtml_(candidate.post_text));

  const linkLines = [
    '<b>Джерело:</b> ' + (candidate.source_url ? tgEscapeHtml_(candidate.source_url) : '—')
  ];
  candidate.urls.forEach(function(url, index) {
    linkLines.push('<b>Зображення ' + (index + 1) + ':</b> ' + tgEscapeHtml_(url));
  });
  tgSendMessage_(chatId, linkLines.join('\n'), [[{
    text: '✓ Опубліковано',
    callback_data: 'news_done_' + candidate.id
  }]]);
}

function tgCleanNews_(chatId, messageId) {
  const ss = _getCrmSs();
  const sheet = ss.getSheetByName('Новини_кандидати');
  if (!sheet) throw new Error('Не знайдено вкладку Новини_кандидати. Запустіть setupNewsSheet_().');

  const headers = ['id', 'created_at', 'game', 'title', 'post_text', 'source_url', 'image1', 'image2', 'image3', 'status', 'guid'];
  const actual = sheet.getRange(1, 1, 1, headers.length).getDisplayValues()[0].map(function(value) {
    return String(value || '').trim();
  });
  if (actual.join('\u001f') !== headers.join('\u001f')) {
    throw new Error('Unexpected header in Новини_кандидати!A1:K1');
  }

  const cutoff = new Date().getTime() - 3 * 86400000;
  const ranges = [];
  const lastRow = sheet.getLastRow();
  if (lastRow >= 2) {
    sheet.getRange(2, 1, lastRow - 1, 10).getValues().forEach(function(row, index) {
      const createdSort = dateSortValue_(row[1]);
      if (String(row[9] || '').trim().toLowerCase() === 'new' && createdSort && createdSort < cutoff) {
        ranges.push('J' + (index + 2));
      }
    });
  }
  if (ranges.length) sheet.getRangeList(ranges).setValue('archived');

  const text = 'Архівовано: ' + ranges.length;
  if (messageId) tgEditMessage_(chatId, messageId, text, null);
  else tgSendMessage_(chatId, text);
}

function tgSetupCommands_() {
  const chatId = String(PropertiesService.getScriptProperties().getProperty('TELEGRAM_ALLOWED_CHAT_ID') || '').trim();
  if (!chatId) throw new Error('Missing TELEGRAM_ALLOWED_CHAT_ID');

  return tgBotApi_('setMyCommands', {
    commands: [
      { command: 'pick_news', description: 'Підібрані пости' },
      { command: 'delete_news', description: 'Очистити старі (>3д)' }
    ],
    scope: { type: 'chat', chat_id: chatId }
  });
}
