# Хендоф — UX-009: сторінка результатів пошуку

Дата: 2026-10-04
Executor: Claude Code · model=Sonnet · thinking=medium-high — *власник призначив Claude Code явно; один шаблон + CSS, хуки відомі з evidence.*

---

## Claude review 2026-10-04 — binding corrections

Where a correction below conflicts with the Ukrainian text, the correction wins. Cross-cutting rules are in the INDEX handoff and are not repeated here.

- **Tile URLs:** take the four section URLs from the burger in the fresh `header.twig`, not from this text.
- **Stock JS:** the `<details>` wrapper must not break the stock bindings on `#button-search`, `#input-search`, `#button-list`, `#button-grid`, `#input-sort` and `#input-limit`. Test each one.

---


## 1. Task ID
`UX-009` (сторінка `product/search`; частина пакета B)

## 2. Context
Аудит: пункти 1, 6, 8. Макети (погоджено): `UX-003 UX-005 UX-009 - макети.html`, екран «Сторінка пошуку», стани «є результати», «Змінити пошук розгорнуто», «нічого не знайдено» (`ux-b-search.jsx` → `SearchPage`, стилі `ux-b.css`, префікси `.ub-sp`, `.ub-sform`, `.ub-dc`, `.ub-next`).
Зараз на 390 перший товар — нижче першого екрана через повну стокову форму, кнопку «Порівняння товарів» і сортування.

## 3. Goal
Перший товар видно в першому екрані на 390; форма доступна за кнопкою; елементи під DS; порожній результат має наступні кроки.

## 4. What to change
1. **Форма (пункт 1):** обгорнути наявні поля в `<details class="…">` з `<summary>` «Змінити пошук» (secondary, іконка повзунків + шеврон; моб. — на всю ширину). Поля лишаються в DOM, ID без змін. Коли результатів 0 — `<details open>`.
   Вміст: картка DS; ≥769 — дві колонки (Пошук + «Шукати в описі товарів» | Категорія + «Пошук у підкатегоріях»), кнопка «Пошук» праворуч унизу; моб. — стовпчиком, кнопка на всю ширину.
2. **H1** — поточний («Пошук - {запит}»). **H2 «Результати пошуку»** — візуально прихований (`.visually-hidden`), у розмітці лишається (рішення власника).
3. **Кнопку «Порівняння товарів (0)» — прибрати** з розмітки сторінки пошуку.
4. **Під DS (пункт 6):** «Пошук» — `--bs-blue`, білий текст, h44; список/сітка — inline SVG у сегменті 44×44 (активний — `--bs-blue-soft`/`--bs-blue`), Font Awesome не використовувати; сортування й ліміт — підпис над полем (12.5 px, `--bs-ink-3`), h44; моб. — сітка 2 колонки по 50%, перемикач список/сітка на моб. не показувати (як зараз — звірити).
5. **Порожній результат (пункт 8):** під наявним `bs-empty` (RD-06) — блок «Подивіться розділи»: 4 посилання-плитки (Pokémon TCG, One Piece Card Game, Інші TCG, Аксесуари — ті самі URL, що в бургері) з кольоровою точкою категорії й стрілкою; моб. — 1 колонка, ≥576 — 2, ≥1024 — 4. Нижче — плашка `--bs-blue-soft`: «Не знайшли? Напишіть у Telegram — привеземо під замовлення» + secondary-кнопка «Написати в Telegram» → `https://telegram.me/boostershop_tcg`.

Тексти погоджено власником 03.10.2026.

## 5. Do not touch
- `sitemap.xml`, `robots.txt`, редиректи, canonical, `.htaccess`, meta robots сторінки пошуку
- Чекаут, оплата, фіскалізація
- Картки товарів (RD-04), пагінація, текст «Показано з … по …»
- Контролер `product/search` і параметри URL
- Хуки: `#input-search`, `#input-description`, `#input-category`, `#input-sub-category`, `#button-search`, `#button-list`, `#button-grid`, `#input-sort`, `#input-limit`, JS, що на них підвішений
- Живий пошук і шапка — окремі пакети
- Merchant-фід, schema

## 6. Likely files / areas
- `catalog/view/template/product/search.twig` (evidence: `live-snapshots/20261004_rd-ux-batch-live2/catalog/view/template/product/search.twig`)
- `catalog/view/stylesheet/boostershop-ds.css` — нова секція `#product-search`
- URL 4 розділів — взяти з бургера в `header.twig` (виконавець звіряє)

## 7. Acceptance criteria
1. 390, `product/search&search=pokemon` — верх першої картки товару в межах 844 px висоти.
2. «Змінити пошук» розгортає форму; пошук з неї працює як раніше (опис, категорія, підкатегорії).
3. Кнопки «Порівняння товарів» на сторінці немає.
4. Font Awesome на сторінці не потрібен для перемикача і кнопки.
5. Сортування й ліміт змінюють видачу як раніше.
6. `search=zzz` — форма розгорнута, є 4 розділи (HTTP 200) і кнопка Telegram.
7. Консоль без нових помилок.

## 8. QA / smoke test
- 390 / 768 / 1440: є результати, 0 результатів, пошук з галочкою «в описі», з категорією.
- Перемикач список/сітка зберігає вибір (localStorage, як стоково).
- SEO: сторінка пошуку — перевірити, що meta robots і canonical не змінились (view-source до/після).

## 9. Rollback note
Бекап робить сам раннер у `_patch_backups/<PATCH-ID>-<timestamp>/` (конвенції AGENTS.md); він має містити `search.twig` і `boostershop-ds.css`. Відкат — залити бекап, скинути кеш теми.
Тригер: не працює пошук з форми, сортування/ліміт, перемикач виду.

## 10. Recommended status after execution
`In progress` (до QA власника) → `Done` (пише Claude chat) після пп. 1–6.

---

**Доставка:** один файл у `patches/`. Власник завантажує в `~/public_html` і запускає `php <patch>.php`. Виконавець нічого не комітить, не пушить і не деплоїть.
