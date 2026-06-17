# Codex Handoff — DASH-WRITE-001: Dashboard Write UI (продажі / закупки / списання)

Date: 2026-06-17 | Priority: High | Tool: Codex

---

## 1. Task ID
`DASH-WRITE-001`

---

## 2. Context

Дашборд (`dashboard/booster-dashboard.html`) зараз лише читає дані через Apps Script GET API.
Власник вносить продажі, закупки та списання вручну у вкладки Google Sheets (Продажі / Закупки / Списання).
Задача: замінити ручний Sheets-ввід на форми прямо в дашборді.

Apps Script вже має функції `addSale()`, `addWriteOff()` та відповідні внутрішні функції.
**Перший крок — Codex аудитує реальний Apps Script код**, щоб підтвердити:
- чи є `doPost` обробник що маршрутизує `action=add_sale` / `action=add_writeoff` / `action=add_procurement`
- або ці функції викликаються лише внутрішньо (з кастомних меню / тригерів)
- яка точна сигнатура (параметри) кожної функції

Без цього аудиту — не можна писати клієнтський UI.

**Важливо:** `doPost` зараз зарезервований для order-sync від OpenCart (це задокументовано в `handoffs/codex-1-apps-script-api.md` рядок 6). Нові write-ендпоінти **не повинні ламати** цей flow.

---

## 3. Goal

**Фаза 1 (цей хендоф):** Codex аудитує Apps Script і повертає звіт.
**Фаза 2 (наступний хендоф після звіту):** на основі аудиту — або додати POST-ендпоінти в Apps Script, або реалізувати UI.

Фінальна мета: у дашборді з'явиться вкладка **"➕ Облік"** з трьома формами:
- 💰 **Продаж** — вибір SKU, кількість, ціна продажу, канал (OLX / Сайт / Instagram / інше)
- 📦 **Закупка** — вибір SKU або новий лот, кількість, ціна закупки / шт, постачальник, дата
- 🗑 **Списання** — вибір SKU, кількість, причина

Після сабміту → рядок записується в Google Sheets через Apps Script API → дашборд оновлює відповідний кеш.

---

## 4. What to change

### Фаза 1 — Apps Script аудит (Codex виконує зараз)

Codex відкриває Apps Script редактор (CRM Google Sheet → Extensions → Apps Script) і перевіряє:

**A. `doPost(e)` — існуючий обробник**
- Знайти функцію `doPost`
- Визначити які `action` вона зараз обробляє
- Чи є там `add_sale`, `add_writeoff`, `add_procurement` — або лише order-sync (`sync_order` / `new_order` / тощо)
- Скопіювати перші 30 рядків `doPost` у звіт

**B. `addSale()` — сигнатура та параметри**
- Знайти функцію `addSale`
- Записати точну сигнатуру: `addSale(param1, param2, ...)`
- Записати звідки вона отримує дані (з `e.postData`, з UI форми Sheets, з параметрів?)
- Чи є валідація обов'язкових полів?
- Скопіювати сигнатуру + перші 20 рядків у звіт

**C. `addWriteOff()` — те саме**
- Знайти функцію, записати сигнатуру, джерело даних, перші 20 рядків

**D. Закупки**
- Знайти функцію що додає рядок у вкладку `Закупки` (може називатись `addProcurement`, `addLot`, `addPurchase`, `addBatch` або інше)
- Якщо нема — зафіксувати це у звіті

**E. SKU list endpoint**
- Підтвердити що `action=sku_list` (GET) повертає список SKU з полем `sku` та `name`
- Це потрібно для dropdown у формах дашборду

---

## 5. Do not touch

- `doPost` логіка order-sync від OpenCart — **не чіпати**, не перейменовувати, не видаляти
- `doGet` — не чіпати
- Вкладки Google Sheets (Продажі, Закупки, Склад, Списання) — **не змінювати структуру колонок**
- `booster-dashboard.html` — **не змінювати** на цьому етапі (лише аудит)
- `sitemap.xml`, `robots.txt`, `.htaccess`, checkout, payment — не стосується задачі
- Existing GET endpoints (`summary`, `orders`, `stock_alerts`, `sku_list`, `channel_stats`) — не чіпати контракти

---

## 6. Likely files / areas

- **Apps Script** (прив'язаний до CRM Google Sheet: `1PvlSlg3UoPw8Fbj98lHL-VGLB0HP8hgKUxsXPW1GkRg`)
  - Функції: `doPost`, `addSale`, `addWriteOff`, + пошук аналога для закупок
  - Може бути один файл `Code.gs` або кілька `.gs` файлів
- **`dashboard/booster-dashboard.html`** — лише читати для розуміння поточного `call()` хелпера та TOKEN/API константи (фаза 2)

---

## 7. Acceptance criteria

**Фаза 1 — звіт Codex містить:**
- [ ] Точну сигнатуру `addSale()` з переліком параметрів
- [ ] Точну сигнатуру `addWriteOff()` з переліком параметрів
- [ ] Назву функції для закупок (або "функції немає — потрібно створити")
- [ ] Відповідь: чи `doPost` вже маршрутизує write actions — так/ні + які саме
- [ ] Перші 30 рядків `doPost`
- [ ] Підтвердження що `sku_list` повертає `{sku, name}` для кожного товару

**Фаза 2 (після звіту) — окремий хендоф:**
- [ ] POST до Apps Script з `{action:'add_sale', sku, qty, price, channel}` → рядок з'являється у вкладці Продажі
- [ ] POST з `{action:'add_writeoff', sku, qty, reason}` → рядок у вкладці Списання
- [ ] POST з `{action:'add_procurement', sku, qty, cost_per_unit, supplier, date}` → рядок у вкладці Закупки
- [ ] Форма в дашборді: SKU dropdown заповнений з `sku_list`
- [ ] Після успішного запису — toast "Збережено ✓" + оновлення кешу відповідної сторінки

---

## 8. QA / smoke test

**Фаза 1 (аудит — не потрібен деплой):**
- Codex відкриває Apps Script → знаходить функції → копіює у звіт
- Якщо Apps Script недоступний напряму — Codex повідомляє owner і чекає

**Фаза 2 (після звіту):**
- Відкрити дашборд → вкладка "➕ Облік"
- Ввести тестовий продаж → сабміт → перевірити що рядок з'явився у Google Sheets
- Відкрити Google Sheets вручну і підтвердити дані
- FIFO / собівартість: після тестового продажу — перевірити що Склад оновився коректно

---

## 9. Rollback note

**Фаза 1** — аудит лише читає, rollback не потрібен.

**Фаза 2:**
- Apps Script: зберегти копію поточного `doPost` перед зміною (скопіювати в коментар або окремий .gs файл)
- Дашборд: `git revert` до попереднього коміту якщо UI ламає читання
- Google Sheets структура: не змінюється → rollback не потрібен для таблиць

---

## 10. Recommended status after execution

**Фаза 1:** Codex повертає звіт → Claude переглядає → готує хендоф Фази 2.
**Фаза 2:** після QA → статус `DASH-WRITE-001` = Done в Notion.

---

## Notes for Codex

- Apps Script файл НЕ є локальним — він живе в Google. Codex має відкрити його через браузер: Google Sheets → Extensions → Apps Script.
- Якщо є кілька `.gs` файлів — перевірити всі на наявність `doPost`, `addSale`, `addWriteOff`.
- Не робити жодних змін в Apps Script до отримання підтвердження від owner після аудиту.
- Цей хендоф = ТІЛЬКИ аудит. Код писати не треба.
