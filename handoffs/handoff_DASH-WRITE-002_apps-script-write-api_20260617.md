# Codex Handoff — DASH-WRITE-002: Apps Script Write API для дашборду

Date: 2026-06-17 | Depends on: DASH-WRITE-001 (аудит завершено) | Tool: Codex

---

## 1. Task ID
`DASH-WRITE-002`

---

## 2. Context

Аудит DASH-WRITE-001 підтвердив:
- `doPost(e)` зараз обробляє лише Telegram webhook та OpenCart order-sync через `upsertOpenCartOrder_()`. Action-routing відсутній.
- `addSale()`, `addPurchase()`, `addWriteOff()` існують, але є zero-arg UI-функціями що читають дані з форм-вкладок Sheets (`Внести_продаж`, `Внести_закупку`, `Внести_списання`). Їх не можна викликати напряму з HTTP.
- `sku_list` GET-ендпоінт працює, повертає `{ ok, count, skus: [{sku, name, ...}] }`.

Потрібно: додати в `doPost(e)` action-routing для трьох нових дій + три API-враппери що приймають payload і записують у Sheets напряму (не через форм-вкладки).

---

## 3. Goal

Розширити `doPost(e)` так, щоб дашборд міг викликати:
- `action: 'add_sale'` → запис у вкладку `Продажі`
- `action: 'add_purchase'` → запис у вкладку `Закупки`  
- `action: 'add_writeoff'` → запис у вкладку `Списання`

Після цього дашборд зможе відображати форми і зберігати записи через ті ж кнопки, що зараз у меню Sheets.

---

## 4. What to change

> **Всі зміни — лише в Apps Script (Code.gs або відповідний .gs файл). Без змін у Sheets структурі.**

### 4a. Розширити `doPost(e)` — додати action switch після перевірки токена

**Знайти в `doPost(e)` блок (після перевірки токена, перед `upsertOpenCartOrder_`):**
```js
const ss = _getCrmSs();
const lock = LockService.getScriptLock();
if (!lock.tryLock(30000)) return boosterCrmJson_({ ok: false, error: 'crm busy, retry later' });
try { const result = upsertOpenCartOrder_(ss, payload); return boosterCrmJson_({ ok: true, result: result }); } finally { lock.releaseLock(); }
```

**Замінити на:**
```js
const ss = _getCrmSs();
const lock = LockService.getScriptLock();
if (!lock.tryLock(30000)) return boosterCrmJson_({ ok: false, error: 'crm busy, retry later' });
try {
  const action = payload.action || '';
  if (action === 'add_sale')      return boosterCrmJson_(apiAddSale_(ss, payload));
  if (action === 'add_purchase')  return boosterCrmJson_(apiAddPurchase_(ss, payload));
  if (action === 'add_writeoff')  return boosterCrmJson_(apiAddWriteOff_(ss, payload));
  // fallback: OpenCart order sync (existing path)
  const result = upsertOpenCartOrder_(ss, payload);
  return boosterCrmJson_({ ok: true, result: result });
} finally { lock.releaseLock(); }
```

---

### 4b. Нова функція `apiAddSale_(ss, payload)`

Записує один або кілька рядків у вкладку `Продажі`.

**Payload schema (від дашборду):**
```js
{
  action: 'add_sale',
  token: '...',
  date: 'YYYY-MM-DD',        // рядок дати, обов'язково
  channel: 'OLX',            // Сайт / OLX / Instagram / Інше
  order_id: 'MANUAL-001',    // опціонально
  items: [
    { sku: 'PKM-JP-MBRV-BST', qty: 2, price: 350 },
    { sku: 'PKM-EN-SV07-BST', qty: 1, price: 290 }
  ]
}
```

**Валідація (повернути `{ ok: false, error: '...' }` якщо):**
- `payload.date` відсутній або не парситься
- `payload.items` порожній або відсутній
- будь-який item: `sku` відсутній, `qty <= 0`, `price < 0`

**Логіка запису:**
- Знайти вкладку `Продажі` через `ss.getSheetByName('Продажі')`
- Визначити структуру колонок так само як це робить `addSale()` (подивитись на реальний заголовок вкладки)
- Для кожного item додати рядок з: date, order_id (або auto-generate `DASH-{timestamp}`), channel, sku, qty, price
- **Не викликати `addSale()` напряму** — лише записати в Sheets через `appendRow` або `getRange(...).setValues(...)`
- Після запису всіх рядків — викликати `updateSkuCurrentCost_(ss)` (вже існує)

**Повернути:**
```js
{ ok: true, rows_added: 2, order_id: 'DASH-1718600000000' }
```

---

### 4c. Нова функція `apiAddPurchase_(ss, payload)`

Записує новий лот у вкладку `Закупки`.

**Payload schema:**
```js
{
  action: 'add_purchase',
  token: '...',
  order_ref: 'ZM-12345',         // ZenMarket Order № або інший ref, обов'язково
  date: 'YYYY-MM-DD',            // дата доставки, опціонально
  supplier: 'ZenMarket',         // постачальник, опціонально
  items: [
    { sku: 'PKM-JP-MBRV-BST', qty: 30, cost_prro: 180, cost_mgmt: 210 }
  ]
}
```

**Валідація:**
- `order_ref` обов'язковий
- `items` не порожній
- кожен item: `sku`, `qty > 0`, `cost_prro >= 0`

**Логіка:**
- Знайти вкладку `Закупки` через `ss.getSheetByName('Закупки')`
- Визначити структуру колонок з реального заголовка (аудит показав колонки A–M мінімум)
- Для кожного item: додати рядок лоту зі статусом `В дорозі` (нові лоти зазвичай ще не в UA)
- Якщо дата не вказана — залишити порожньою
- **Не викликати `addPurchase()` напряму**

**Повернути:**
```js
{ ok: true, rows_added: 1, lot_ids: ['LOT-XXXX'] }
// lot_id — якщо є авто-нумерація, інакше повернути рядки що додані
```

---

### 4d. Нова функція `apiAddWriteOff_(ss, payload)`

Записує рядки у вкладку `Списання`.

**Payload schema:**
```js
{
  action: 'add_writeoff',
  token: '...',
  date: 'YYYY-MM-DD',
  reason: 'Пошкоджено',          // причина, обов'язково
  items: [
    { sku: 'PKM-JP-MBRV-BST', qty: 1, note: '' }
  ]
}
```

**Валідація:**
- `date` обов'язковий
- `reason` обов'язковий
- `items` не порожній, кожен item: `sku`, `qty > 0`

**Логіка:**
- Знайти вкладку `Списання` через `ss.getSheetByName('Списання')`
- Визначити структуру колонок з реального заголовка
- Додати рядки
- Після запису — викликати `updateSkuCurrentCost_(ss)`

**Повернути:**
```js
{ ok: true, rows_added: 1 }
```

---

### 4e. Важлива примітка щодо структури колонок

Codex **повинен** подивитись на реальні заголовки вкладок `Продажі`, `Закупки`, `Списання` перед тим як писати `appendRow`. Не можна вгадувати індекси колонок — структура мусить відповідати тому, що `addSale()` / `addPurchase()` / `addWriteOff()` пишуть зараз. Найпростіший спосіб: знайти в коді `addSale()` рядок де формується масив для запису і використати ту саму структуру.

---

## 5. Do not touch

- Існуючий Telegram-branch в `doPost(e)` — **не чіпати**
- Існуючий OpenCart order-sync branch (`upsertOpenCartOrder_`) — **не чіпати**
- Нульові функції `addSale()`, `addPurchase()`, `addWriteOff()` — **не чіпати** (меню Sheets має лишитись)
- `doGet(e)` — не чіпати
- Всі GET endpoints — не чіпати
- Структуру вкладок Sheets (колонки, формули) — **не змінювати**
- `sitemap.xml`, `robots.txt`, `.htaccess`, checkout, payment — не стосується

---

## 6. Likely files / areas

- **Apps Script** (CRM Google Sheet `1PvlSlg3UoPw8Fbj98lHL-VGLB0HP8hgKUxsXPW1GkRg` → Extensions → Apps Script)
  - `doPost(e)` — розширити action switch
  - Нові функції: `apiAddSale_`, `apiAddPurchase_`, `apiAddWriteOff_`
  - Посилання на існуючі: `updateSkuCurrentCost_`, `boosterCrmJson_`, `_getCrmSs`

---

## 7. Acceptance criteria

- [ ] POST `{action:'add_sale', token, date, channel, items:[{sku,qty,price}]}` → рядок з'являється у вкладці `Продажі`
- [ ] POST `{action:'add_purchase', token, order_ref, items:[{sku,qty,cost_prro}]}` → рядок у `Закупки` зі статусом `В дорозі`
- [ ] POST `{action:'add_writeoff', token, date, reason, items:[{sku,qty}]}` → рядок у `Списання`
- [ ] Після `add_sale` і `add_writeoff` — `updateSkuCurrentCost_()` виконується без помилок
- [ ] `add_sale` з порожнім `items` → `{ ok: false, error: 'items required' }`
- [ ] Неправильний токен → `{ ok: false, error: 'bad token' }` (існуюча поведінка, не ламається)
- [ ] OpenCart order-sync (без поля `action`) — досі працює (fallback path)
- [ ] Меню Sheets: `addSale()` / `addPurchase()` / `addWriteOff()` — досі доступні і працюють

---

## 8. QA / smoke test

1. Відкрити Apps Script → Deploy → Test deployments або скопіювати Web App URL
2. Надіслати тестовий POST через Postman або `curl`:
   ```json
   {"action":"add_sale","token":"<TOKEN>","date":"2026-06-17","channel":"OLX","items":[{"sku":"PKM-JP-MBRV-BST","qty":1,"price":350}]}
   ```
3. Перевірити що рядок з'явився у вкладці `Продажі` Google Sheets
4. Перевірити `Executions` в Apps Script — немає помилок
5. Надіслати POST без `items` → очікується `{ok:false,error:'...'}`
6. Надіслати OpenCart order-sync payload (без `action`) → `{ok:true,result:{...}}` (без змін)
7. Перевірити що меню Booster CRM → "Додати продаж" досі відкриває форму

---

## 9. Rollback note

- Зберегти копію `doPost(e)` (скопіювати в коментар вгорі або окремий файл `doPost_backup.gs`) перед змінами
- Якщо щось зламалось: повернути попередній `doPost(e)` і видалити три нові функції `apiAdd*_`
- Sheets структура не змінюється → rollback не потрібен для таблиць

---

## 10. Recommended status after execution

Codex повертає звіт з: назвами функцій, кількістю рядків коду, результатом тестового POST.
Після power-user QA (owner перевіряє рядок у Sheets) → статус `DASH-WRITE-002` = Done.
Наступний крок: `DASH-WRITE-003` — UI форми в дашборді (клієнтський JS).
