# Хендоф — UX-009: живий пошук

Дата: 2026-10-04
Executor: Claude Code · model=Sonnet · thinking=medium-high — *власник призначив Claude Code явно; CSS-оверрайди + невелика правка init у header.twig, модуль не змінюється.*

---

## Claude review 2026-10-04 — binding corrections

Where a correction below conflicts with the Ukrainian text, the correction wins. Cross-cutting rules are in the INDEX handoff and are not repeated here.

- **No-results text:** insert the query into «Нічого не знайдено за «…»» with `textContent`, never as HTML.
- **Error handler:**
  - Ignore jQuery `abort`.
  - Render the error only when it belongs to the current input value, so a late error never overwrites newer results.
- **Loading spinner:** hide the module's Font Awesome `<i>` inside `.ps-live-search-item-loading` with CSS, and draw the spinner on the container.
- **Chain:** build on top of package 3's output; both edit `header.twig`.

---


## 1. Task ID
`UX-009` (живий пошук; частина пакета B)

## 2. Context
Аудит: пункти 2, 3, 9. Макети (погоджено): `UX-003 UX-005 UX-009 - макети.html`, екран «Живий пошук», стани «фокус», «завантаження», «результати», «нічого», «помилка» (`ux-b-search.jsx` → `LiveList`, `MobileOverlay`; стилі `ux-b.css`, префікс `.ub-ls`).
Модуль `ps_live_search` стилізується лише vendor CSS на змінних Bootstrap; init і `$.ajax` — у `header.twig` (лише `success`, без `error`).

## 3. Goal
Компактний список підказок (більше товарів на екрані), одне повідомлення «нічого не знайдено», видимий стан помилки й завантаження без Font Awesome.

## 4. What to change
1. **Рядок товару (пункт 2), лише CSS у `boostershop-ds.css`:** сховати `.description` у списку; grid `56px | 1fr`, gap 12; мініатюра 56×56, радіус 8, рамка `--bs-line`; назва 14 px, 600, `-webkit-line-clamp:2`; ціна під назвою, 15 px, 800; зі знижкою — `.price-new` `--bs-danger` + закреслена `.price-old` 13 px `--bs-ink-4`. Мін. висота рядка 68 px. Заголовки груп (Товари/Категорії) — 11.5 px uppercase `--bs-ink-3`. Категорії — рядки 44 px зі стрілкою.
2. **«Усі результати»:** кнопка-рядок 44 px з рамкою, текст `--bs-blue`, стрілка **вправо** (SVG) замість ▾.
3. **Нічого не знайдено (пункт 3):** коли немає жодних результатів — одне повідомлення по центру: іконка лупи в колі 44 px, «Нічого не знайдено за «{запит}»» (15 px, 700), під ним «Перевірте написання або спробуйте коротший запит.» (13.5 px, `--bs-ink-3`). Три окремі «Нічого не знайдено» і «Усі результати» — сховати. Реалізація — CSS `:has()` або JS у init поверх рендера модуля; **ps_live_search.js не змінювати**.
4. **Завантаження (пункт 9):** спінер — CSS-коло 24 px (`border 2.5px --bs-line`, `border-top-color --bs-blue`), замість іконки Font Awesome.
5. **Помилка (пункт 9):** у виклику `$.ajax` в init додати `error` і тайм-аут ~8 с → у список: іконка в колі `--bs-warning-bg`/`--bs-warning-line`, «Не вдалося завантажити підказки», кнопка secondary «Шукати на сторінці результатів →», яка відправляє форму пошуку (наявний action + hidden route/language). `role="alert"`.
6. Мобільний оверлей (≤768): список на всю ширину під полем, без рамки й тіні; desktop — випадний список під полем, радіус `--bs-r-lg`, тінь `0 12px 32px rgba(17,24,39,.16)`.

Тексти погоджено власником 03.10.2026.

## 5. Do not touch
- `sitemap.xml`, `robots.txt`, редиректи, canonical, `.htaccess`
- Чекаут, оплата, фіскалізація
- Файли модуля `extension/ps_live_search/**` (JS, PHP, twig, vendor CSS)
- Сторінка `product/search` — окремий пакет
- Хуки: `#ps-live-search-input`, `#ps-live-search`, `.ps-live-search-container`, `data-live-search-target`, form action + hidden route/language, `#bs-msearch`, `[data-bs-search-clear]`
- Merchant-фід, schema

## 6. Likely files / areas
- `catalog/view/stylesheet/boostershop-ds.css` — нова секція «live search»
- `catalog/view/template/common/header.twig` — лише init/`$.ajax` (evidence: `live-snapshots/20261004_rd-ux-batch-live2/catalog/view/template/common/header.twig`)
- Довідка про розмітку модуля: `live-snapshots/20261004_rd-ux-batch-live2/extension/ps_live_search/catalog/view/stylesheet/ps_live_search.css` — **класи звірити з реальним рендером** (DevTools на продакшені)

## 7. Acceptance criteria
1. 390: запит «pokemon» — у першому екрані оверлею видно ≥4 товари; опису немає; назва ≤2 рядків.
2. Запит «zzz» — одне повідомлення, «Усі результати» не видно.
3. DevTools → Network → Offline (або блок запиту): за ≤8 с з’являється «Не вдалося завантажити підказки»; кнопка відкриває `product/search&search=…`.
4. Спінер видно при повільному мережевому профілі; Font Awesome для нього не потрібен.
5. Клік по товару/категорії/«Усі результати» веде туди ж, що й до патча.
6. Консоль без нових помилок.

## 8. QA / smoke test
- 390 / 768 / 1440: порожнє поле у фокусі, «pok», «pokemon», «zzz», офлайн.
- Товар зі знижкою в результатах — дві ціни.
- Клавіатура: Tab по пунктах, Esc закриває (як до патча).

## 9. Rollback note
Бекап робить сам раннер у `_patch_backups/<PATCH-ID>-<timestamp>/` (конвенції AGENTS.md); він має містити `header.twig` і `boostershop-ds.css`. Відкат — залити бекап, скинути кеш теми.
Тригер: підказки не з’являються, клік по підказці не працює, JS-помилка в init.

## 10. Recommended status after execution
`In progress` (до QA власника) → `Done` (пише Claude chat) після пп. 1–5.

---

**Доставка:** один файл у `patches/`. Виконувати **після** пакета UX-003/005 «шапка і бургер» (спільний `header.twig`). Власник завантажує в `~/public_html` і запускає `php <patch>.php`. Виконавець нічого не комітить, не пушить і не деплоїть.
