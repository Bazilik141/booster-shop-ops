# Хендоф — сітка 576–991: fluid-контейнер

Дата: 2026-10-04
Executor: Claude Code · model=Sonnet · thinking=medium-high — *власник призначив Claude Code явно; одна CSS-зміна, але глобальна — потрібна перевірка багатьох сторінок.*

---

## Claude review 2026-10-04 — binding corrections

Where a correction below conflicts with the Ukrainian text, the correction wins. Cross-cutting rules are in the INDEX handoff and are not repeated here.

- **Scope:** owner-approved extension of UX-003 (Claude Design stage 3, 2026-10-04).
- **Global change:** this alters `.container` on every page. `common/header.twig` has its own inline `header .container` rule. Screenshot every page in §8 before and after the change.

---


## 1. Task ID
`UX-003` (етап 3, тема «Сітка 576–767», варіант **B · fluid до 991**)

## 2. Context
Bootstrap `.container` має max-width 540 px на 576–767 і 720 px на 768–991, а шапка — на всю ширину з полями 10 / 32 px. Через це контент вужчий за шапку (скрін 760). Демонстрація: `UX-003 UX-005 UX-009 - етап 3.html`, тема «Сітка 576–767», ширини 700 / 900 (`GridDemo` у `ux-b-stage3.jsx`).

## 3. Goal
На ширинах <992 контент займає всю ширину з тими самими бічними полями, що й шапка.

## 4. What to change
У `boostershop-ds.css`:
- `@media (max-width:991.98px)` — `.container` (основний контент сторінок) `max-width:none`.
- Бічні поля: <769 — 10 px (як `.bs-header` на моб.), 769–991 — 32 px (як шапка desktop). **Точні значення полів шапки виконавець бере з `.bs-header`** у поточному CSS.
- ≥992 — без змін.
- Не переписувати Bootstrap; лише оверрайд у DS-файлі. Якщо `.container` використовується всередині компонентів (модалки, мінікошик), обмежити селектор основними обгортками сторінки — виконавець знаходить їх у шаблонах.

## 5. Do not touch
- `sitemap.xml`, `robots.txt`, редиректи, canonical, `.htaccess`
- Чекаут, оплата, фіскалізація (сторінки checkout лише візуально отримують ширшу сітку — розмітку форм не чіпати)
- Сітка ≥992 і `.container-fluid`
- Bootstrap-файли
- Merchant-фід, schema

## 6. Likely files / areas
- `catalog/view/stylesheet/boostershop-ds.css`
- Довідково: `common/header.twig`, `common/footer.twig`, шаблони сторінок з `<div class="container">`

## 7. Acceptance criteria
1. На 700 і 900 px ліве й праве поле контенту збігаються з полями логотипа/кошика в шапці (±1 px).
2. На 390 і ≥992 вигляд не змінився (порівняти скріни до/після).
3. Немає горизонтальної прокрутки на 576–991 на жодній сторінці зі списку QA.

## 8. QA / smoke test
- Ширини 600, 700, 800, 900, 990: головна, категорія, товар, пошук, кошик, checkout (без відправки), інформаційна сторінка, 404, success/failure.
- Каруселі/слайдери й таблиці — без переповнення.

## 9. Rollback note
Бекап робить сам раннер у `_patch_backups/<PATCH-ID>-<timestamp>/` (конвенції AGENTS.md); він має містити `boostershop-ds.css`. Відкат — залити бекап, скинути кеш теми.
Тригер: горизонтальна прокрутка, зламаний слайдер чи таблиця на планшеті.

## 10. Recommended status after execution
`In progress` (до QA власника) → `Done` (пише Claude chat) після пп. 1–3.

---

**Доставка:** один файл у `patches/`. Власник завантажує в `~/public_html` і запускає `php <patch>.php`. Виконавець нічого не комітить, не пушить і не деплоїть.
