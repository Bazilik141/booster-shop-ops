# Codex Handoff — DASH-WRITE-003: Dashboard Write UI Forms

Date: 2026-06-18 | Depends on: DASH-WRITE-002 (done) | Tool: Codex

---

## 1. Task ID
`DASH-WRITE-003`

---

## 2. Context

DASH-WRITE-002 додав POST-ендпоінти в Apps Script (`add_sale`, `add_purchase`, `add_writeoff`).
Дашборд (`dashboard/booster-dashboard.html`, 1727 рядків) зараз лише читає дані через GET.

Поточна структура дашборду:
- `const API` — L442, URL Apps Script Web App
- `const TOKEN` — L443
- `async function call(action, extra)` — L446–453, лише GET через URLSearchParams
- `const cache = {}` — L456, кеш даних
- `const loaders = {...}` — L1720, map name → loadFn
- `function showPage(name)` — L482–488, перемикає вкладки + викликає loaders
- Nav: overview / stock / consumables / orders / skus / clients / roadmap (L249–269)
- Pages: `<div id="page-NAME" class="page">` (L283–424)

`sku_list` endpoint повертає `{ ok, count, skus: [{sku, name, stock, price_crm, ...}] }`.
Дані кешуються в `cache.skus`.

---

## 3. Goal

Додати вкладку **➕ Облік** з трьома формами:
- 💰 **Продаж** — SKU (searchable select), кількість, ціна, канал, опц. order_id
- 📦 **Закупка** — order_ref, постачальник, дата, SKU(и) з qty + cost_prro
- 🗑 **Списання** — SKU, кількість, причина, дата

Після сабміту → POST до Apps Script → toast "Збережено ✓" або повідомлення про помилку.

---

## 4. What to change

> **Всі зміни лише в `dashboard/booster-dashboard.html`. Apps Script не чіпати.**

### 4a. POST хелпер — додати поруч з `call()` (після L453)

```js
async function callPost(payload) {
  const r = await fetch(API, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ...payload, token: TOKEN }),
    redirect: 'follow'
  });
  if (!r.ok) throw new Error(`HTTP ${r.status}`);
  const d = await r.json();
  if (!d.ok) throw new Error(d.error || 'API error');
  return d;
}
```

### 4b. Nav item — додати після рядка з `showPage('roadmap')` (L267–269)

```html
<div class="nav-item" onclick="showPage('accounting')">
  <span class="nav-icon">➕</span> Облік
</div>
```

### 4c. Page div — додати після `<div id="page-clients"...>` (після L424 закриваючого `</div>`)

```html
<div id="page-accounting" class="page">
  <div class="page-header"><h2>➕ Облік</h2></div>
  <div id="accountingContent">Завантаження...</div>
</div>
```

### 4d. Функція `loadAccounting()`

Додати перед блоком `// ════════════ LOADERS MAP ════════════` (L1719).

**Логіка:**
1. Завантажити `cache.skus` якщо ще немає: `await call('sku_list')`
2. Побудувати `<select>` з SKU опцій: `<option value="sku">name (sku)</option>`
3. Select повинен мати атрибут `size="1"` і `<input>` для пошуку над ним (filter by text)
4. Рендерити три секції в `#accountingContent`: Продаж / Закупка / Списання

**Структура HTML форми продажу:**
```html
<div class="write-section">
  <h3>💰 Додати продаж</h3>
  <div class="write-form" id="saleForm">
    <div class="form-row">
      <label>SKU</label>
      <input type="text" id="saleSkuSearch" placeholder="Пошук SKU або назви..." autocomplete="off">
      <select id="saleSkuSelect" size="5" style="width:100%;max-height:140px"></select>
    </div>
    <div class="form-row">
      <label>Кількість</label>
      <input type="number" id="saleQty" min="1" value="1">
    </div>
    <div class="form-row">
      <label>Ціна продажу (грн)</label>
      <input type="number" id="salePrice" min="0" step="0.01">
    </div>
    <div class="form-row">
      <label>Канал</label>
      <select id="saleChannel">
        <option>Сайт</option>
        <option>OLX</option>
        <option>Instagram</option>
        <option>Інше</option>
      </select>
    </div>
    <div class="form-row">
      <label>Order ID (опц.)</label>
      <input type="text" id="saleOrderId" placeholder="напр. MANUAL-001">
    </div>
    <div class="form-row">
      <label>Дата</label>
      <input type="date" id="saleDate">
    </div>
    <button onclick="submitSale()" class="btn-primary">💾 Зберегти продаж</button>
    <div id="saleMsg" class="form-msg"></div>
  </div>
</div>
```

**Аналогічно** для форми Закупки (id prefix: `purchase`) і Списання (id prefix: `writeoff`).

**Форма закупки — поля:**
- order_ref (text, required)
- supplier (text, default "ZenMarket")
- date (date, optional)
- SKU searchable select
- qty (number)
- cost_prro (number, required)
- cost_mgmt (number, optional — якщо не вказано = cost_prro)
- кнопка "➕ Додати ще SKU" — дублює рядок SKU/qty/cost_prro (масив items)

**Форма списання — поля:**
- SKU searchable select
- qty (number)
- reason (text, required) — причина
- date (date, required)

### 4e. Submit функції

**`submitSale()`:**
```js
async function submitSale() {
  const sku = document.getElementById('saleSkuSelect').value;
  const qty = parseInt(document.getElementById('saleQty').value);
  const price = parseFloat(document.getElementById('salePrice').value);
  const channel = document.getElementById('saleChannel').value;
  const order_id = document.getElementById('saleOrderId').value.trim() || undefined;
  const date = document.getElementById('saleDate').value;
  const msg = document.getElementById('saleMsg');

  if (!sku || !qty || !price || !date) {
    msg.textContent = '⚠ Заповніть всі обов\'язкові поля';
    msg.className = 'form-msg error';
    return;
  }
  msg.textContent = 'Збереження...';
  msg.className = 'form-msg';
  try {
    await callPost({ action: 'add_sale', date, channel, order_id,
      items: [{ sku, qty, price }] });
    msg.textContent = '✅ Збережено';
    msg.className = 'form-msg success';
    // Скинути кеш overview і stock щоб наступне відкриття підтягнуло нові дані
    delete cache.summary;
    delete cache.stock;
    loaded.overview = false;
    loaded.stock = false;
  } catch(e) {
    msg.textContent = '❌ ' + e.message;
    msg.className = 'form-msg error';
  }
}
```

**`submitPurchase()`** — аналогічно, збирає масив `items` з динамічних рядків, payload `action:'add_purchase'`.

**`submitWriteoff()`** — аналогічно, payload `action:'add_writeoff'`.

### 4f. Loaders map — додати `accounting`

```js
const loaders = { ..., accounting: loadAccounting };
```

### 4g. Ініціалізація дати за замовчуванням

В `loadAccounting()` після рендеру форм:
```js
const today = new Date().toISOString().slice(0,10);
document.getElementById('saleDate').value = today;
document.getElementById('writeoffDate').value = today;
```

### 4h. CSS — додати в `<style>` блок

```css
/* Write forms */
.write-section { background: var(--card); border-radius: 10px; padding: 20px; margin-bottom: 20px; }
.write-section h3 { margin: 0 0 14px; font-size: 15px; }
.write-form { display: grid; gap: 10px; max-width: 480px; }
.form-row { display: flex; flex-direction: column; gap: 4px; }
.form-row label { font-size: 12px; color: var(--muted); }
.form-row input, .form-row select { background: var(--bg); border: 1px solid var(--border); border-radius: 6px; padding: 7px 10px; color: var(--fg); font-size: 13px; }
.form-row select[size] { overflow-y: auto; }
.form-msg { font-size: 13px; min-height: 18px; }
.form-msg.error { color: var(--red); }
.form-msg.success { color: var(--green); }
.btn-primary { background: var(--accent); color: #fff; border: none; border-radius: 7px; padding: 9px 18px; font-size: 13px; cursor: pointer; font-weight: 600; width: fit-content; }
.btn-primary:hover { opacity: 0.88; }
.btn-secondary { background: var(--border); color: var(--fg); border: none; border-radius: 7px; padding: 7px 14px; font-size: 12px; cursor: pointer; }
```

---

## 5. Do not touch

- `doGet`, `call()` GET хелпер — не чіпати
- Всі існуючі вкладки (overview, stock, consumables, orders, skus, clients, roadmap) — не чіпати
- Всі існуючі `load*()` функції — не чіпати
- `const API`, `const TOKEN`, `const cache`, `const loaders` структура — лише розширити, не переписувати
- `sitemap.xml`, `robots.txt`, `.htaccess`, canonical, redirects — не стосується
- Checkout, payment, fiscalization, Merchant feed, schema — не стосується
- Apps Script код — **не чіпати** (DASH-WRITE-002 вже задеплоєно)

---

## 6. Likely files / areas

- **`dashboard/booster-dashboard.html`** — єдиний файл. Зміни в 4 місцях:
  1. Після `call()` (L453) — додати `callPost()`
  2. Nav sidebar (L267–269) — додати nav item
  3. Після page-clients div — додати page-accounting div
  4. Перед loaders map (L1719) — додати `loadAccounting()`, `submitSale()`, `submitPurchase()`, `submitWriteoff()`
  5. В `<style>` — додати CSS класи для форм

---

## 7. Acceptance criteria

- [ ] У навбарі з'явилась вкладка "➕ Облік"
- [ ] При кліку на вкладку — завантажуються три форми (Продаж / Закупка / Списання)
- [ ] SKU select фільтрується по введеному тексту
- [ ] Сабміт продажу з валідними даними → `{ok:true}` від API → "✅ Збережено"
- [ ] Сабміт з порожнім SKU або qty → локальна помилка без запиту до API
- [ ] Сабміт закупки → рядок з'являється у Sheets `Закупки` зі статусом `В дорозі`
- [ ] Сабміт списання → рядок у Sheets `Списання`
- [ ] Після успішного продажу — `cache.summary` і `cache.stock` скинуто (наступне відкриття Overview/Склад підтягне свіжі дані)
- [ ] Існуючі вкладки дашборду — не зламані, дані завантажуються як раніше
- [ ] `node --check` на витягнутому JS — без помилок (Codex перевіряє після змін)

---

## 8. QA / smoke test

1. Відкрити дашборд через локальний сервер (`python -m http.server 8080`)
2. Клікнути "➕ Облік" в навбарі — форми рендеряться
3. В формі Продаж: ввести "PKM" в пошук → список фільтрується. Вибрати SKU → заповнити qty/price/date → Submit
4. Перевірити Google Sheets `Продажі` — новий рядок присутній
5. В формі Списання: заповнити SKU / qty / reason / date → Submit → перевірити `Списання`
6. В формі Закупки: order_ref + один SKU → Submit → перевірити `Закупки`
7. F12 Console — немає JS помилок
8. Перейти на Огляд → Склад → Товари → Замовлення — все завантажується як раніше

---

## 9. Rollback note

- `git revert HEAD` якщо вкладка ламає навігацію або існуючі сторінки
- Apps Script не змінюється — rollback там не потрібен
- Google Sheets: тестові рядки що додались під час QA видалити вручну

---

## 10. Recommended status after execution

Codex повертає звіт з кількістю доданих рядків і результатом `node --check`.
Owner запускає QA smoke test (кроки 1–8 вище) → перевіряє рядки в Sheets → якщо ok → статус `DASH-WRITE-003` = Done.
