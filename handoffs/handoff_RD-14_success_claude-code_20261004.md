# Хендоф — RD-14: сторінка «Замовлення прийнято»

Дата: 2026-10-04
Executor: Claude Code · model=Opus · thinking=high — *власник призначив Claude Code явно; Opus, бо шаблон лежить у зоні checkout (GA4-скрипт, гілки за способом оплати, хуки CHECKOUT-008).*

---

## Claude review 2026-10-04 — binding corrections

Where a correction below conflicts with the Ukrainian text, the correction wins. Cross-cutting rules are in the INDEX handoff and are not repeated here.

- **Must survive unchanged:**
  - the `{% if ga4_purchase_payload %}` script block after both branches (TECH-015 WP1);
  - `[data-checkout008-copy-requisites]`, `[data-checkout008-copy-status]` and the inline copy script;
  - the conditions `order_data.show_first15_offer`, `order_data.is_iban_bank_transfer`, `order_data.is_hutko`, `order_data.is_cod`, `is_logged and history_url`;
  - the requisite values.
  - The controller is not touched.
- **New copy goes in the template.** «Що далі», «Фіскальний чек», «Без зайвих дзвінків», «Показати товари (N)» / «Сховати товари» are written in the template, not the language file. The patch stays template + CSS + the `header.twig` cache-bust.
- **Collapse:**
  - The markup renders open.
  - A small vanilla-JS block collapses it below 1024 px via `matchMedia` and keeps `aria-expanded` in sync. No jQuery dependency: `common.js` is deferred.
- **No payment-status logic.** A failed Hutko payment can still land here (`CHECKOUT-012`); do not add any logic about it.
- **§8, owner decision 2026-10-04:** the full real-order set as written, including a real Hutko payment (refunded afterwards) and a new registration for First15.
  - In addition, render all states locally with fixtures before delivery.
  - Optional evidence for `CHECKOUT-012`: one Hutko attempt cancelled on the Hutko page. The owner notes which page the browser lands on. Nothing in this package changes because of it.

---


## 1. Task ID
`RD-14`

## 2. Context
`catalog/view/template/checkout/success.twig` уже має `bs-cp-page` і власні блоки (hero, First15, IBAN-реквізити, товари, футер-повідомлення), але: емодзі як іконки, біле на `--bs-green` (3.29:1), «Переглянути замовлення» — зелена кнопка, блоки на 1440 розтягуються на весь контейнер, реквізити — суцільний текст.
Дизайн-джерело (погоджено власником): `RD-14 RD-15 - фінал.html` (компоненти — `rd14-final.jsx`, стилі — `rd14-shared.css` + `<style>` у файлі). Стани a–e у перемикачі «Стан». Варіант First15 — **A · рамка**.
Деплой прямо в продакшн, staging немає.

## 3. Goal
Сторінка успіху в напрямку «Кроки»: одна колонка 620 px по центру, SVG замість емодзі, реквізити IBAN окремою карткою, згортання списку товарів на <1024, акцентна плашка First15. Дані, суми, логіка гілок і аналітика не змінюються.

## 4. What to change
Тільки розмітка `success.twig` + CSS у `boostershop-ds.css` (секція `#checkout-success` / `.bs-cp-page`).

**Порядок блоків:** hero → First15 (лише якщо зараз показується) → реквізити (лише IBAN-гілка) → картка «Що далі» → «Ваше замовлення» → дії → футер.

- **Hero:** по центру; коло 56 px, фон `--bs-buy`, біла SVG-галочка (stroke 3). H1 і підзаголовок — поточний текст; 🎴 → SVG `cards`, `aria-hidden`.
- **First15 (варіант A):** фон `--bs-green-soft`, рамка `2px solid --bs-buy`, радіус `--bs-r-lg`; іконка-ярлик у колі `--bs-buy`. «Дякуємо за реєстрацію!» — 13 px, `--bs-buy`, 700. Основний рядок — 17 px, 800; «15%» обгорнути в `<mark>` без фону, колір `--bs-buy`, 1.25em. Текст дослівний.
- **Реквізити (IBAN):** окрема картка під hero. Заголовок «Реквізити для оплати» + SVG банку. Рядки `dl`: підпис / значення (≥768 — дві колонки 160 px / решта). Рядок IBAN — фон `--bs-blue-soft`, значення 15 px, 800, `--bs-blue`. Одна кнопка «Скопіювати реквізити» (secondary). Хуки `data-checkout008-copy-requisites` і `data-checkout008-copy-status` — **на тих самих елементах, логіку не змінювати**.
- **«Що далі»:** картка з двома кроками (іконка в колі `--bs-blue-soft` 36 px + підзаголовок + текст):
  - «Фіскальний чек» — поточне речення фіскального чека для гілки (тексти без змін);
  - «Без зайвих дзвінків» — поточне речення «Ми не телефонуємо…», іконка — **смартфон** (не трубка); 🙂 → SVG.
  Підзаголовки «Що далі», «Фіскальний чек», «Без зайвих дзвінків» — нова копія, погоджено.
- **«Ваше замовлення»:** доставка й оплата — над таблицею (≥768 — у дві колонки). Таблиця: «N×» — окремий приглушений `span`, назва переноситься (`overflow-wrap:anywhere`), ціна `nowrap`, `tabular-nums`; рядки підсумку — підпис ліворуч, сума праворуч, «Всього» 16 px, 800, верхня лінія.
  - **<1024 px — завжди згорнуто:** видно доставку, оплату і рядок «Сума» (= «Всього»). Кнопка «Показати товари (N)» / «Сховати товари», `aria-expanded`, `aria-controls`, шеврон повертається. ≥1024 — таблиця завжди відкрита, кнопки немає. **Без JS таблиця відкрита.**
- **Дії:** «На головну» і «Переглянути замовлення» (лише для залогіненого) — обидві secondary (біла, рамка `--bs-line`). Зелених кнопок на сторінці немає. Моб. — на всю ширину стовпчиком, ≥768 — в ряд по центру, min-width 220 px.
- **Футер:** «Вдалого анпакінгу» (🎁 → SVG) і «Якщо є питання — напишіть у Telegram» — посилання `--bs-blue`, підкреслене, з іконкою Telegram (колір іконки `#229ED9`, текст синій — **не** білий на `#229ED9`).
- **Fallback (без номера замовлення, стан e):** hero «Ваше замовлення прийнято!» + картка з поточним `text_message` **без змін**; під текстом — «На головну» + «напишіть у Telegram» (secondary-кнопки з іконкою Telegram).
- Хлібні крихти — як на решті сайту (таблетки), останній пункт — без посилання.

## 5. Do not touch
- `sitemap.xml`, `robots.txt`, редиректи, canonical, `.htaccess`
- **Чекаут, оплата (Hutko, `hutko.response`), фіскалізація (Checkbox)**, логіка First15, обчислення сум
- GA4 / purchase-скрипт на сторінці — не переносити, не дублювати, не міняти умови виклику
- JS копіювання реквізитів (CHECKOUT-008) — тільки CSS і обгортка
- Реквізити, суми, тексти мовного файлу (крім нових підзаголовків і кнопки згортання, які пишуться в шаблоні/мовному файлі на розсуд виконавця — зафіксувати де)
- Merchant-фід, schema
- `failure.twig` — окремий пакет RD-15

## 6. Likely files / areas
- `catalog/view/template/checkout/success.twig` — evidence: `live-snapshots/20261004_rd-ux-batch-live2/catalog/view/template/checkout/success.twig`
- `catalog/view/stylesheet/boostershop-ds.css` — секція success
- Токени `--bs-buy`, `--bs-green-soft`, `--bs-blue-soft` уже є — нових не додавати
- Виконавець зобов’язаний звірити evidence зі свіжим продакшеном перед патчем.

## 7. Acceptance criteria
1. На сторінці немає емодзі ✓ 🎴 🙂 🎁 ⚠️; всі іконки — inline SVG з `aria-hidden="true"`.
2. Коло hero: білий на `#12883E` (контраст ≥4.5:1).
3. На 1440 контент сторінки ≤620 px завширшки, по центру.
4. IBAN-гілка: реквізити — одразу під hero; клік «Скопіювати реквізити» копіює і показує статус, як до патча.
5. <1024: таблиця товарів схована, видно «Сума»; кнопка розгортає/згортає, `aria-expanded` змінюється. ≥1024: таблиця видима, кнопки немає.
6. First15 показується за тих самих умов, що й до патча.
7. «Переглянути замовлення» — біла secondary, лише для залогіненого.
8. Fallback: текст незмінний, є кнопка Telegram → `https://telegram.me/boostershop_tcg`, `target="_blank" rel="noopener"`.
9. Консоль без нових помилок; GA4 purchase-подія приходить один раз (DebugView або Network).

## 8. QA / smoke test
- **Обов’язково `bs-checkout-smoke`** (повний 11-кроковий план) — шаблон рендериться в кінці checkout.
- Тестові замовлення: гість + COD; залогінений + Hutko (+ First15, якщо доступно); IBAN; 6 товарів з довгими назвами; прямий захід на `checkout/success` без сесії (fallback).
- Ширини 390 / 768 / 1440.

## 9. Rollback note
Бекап робить сам раннер у `_patch_backups/<PATCH-ID>-<timestamp>/` (конвенції AGENTS.md); він має містити `success.twig` і `boostershop-ds.css`.
Відкат: залити бекап, скинути кеш теми, `Ctrl+F5`.
Тригер: не приходить GA4 purchase, не працює копіювання реквізитів, зникла гілка оплати, будь-яка PHP-помилка на success.

## 10. Recommended status after execution
`In progress` (до QA власника) → `Done` (пише Claude chat) після проходження `bs-checkout-smoke` і пп. 4, 5, 9.

---

**Доставка:** один файл у `patches/` (тільки RD-14). Власник завантажує в `~/public_html` і запускає `php <patch>.php`. Виконавець нічого не комітить, не пушить і не деплоїть.
