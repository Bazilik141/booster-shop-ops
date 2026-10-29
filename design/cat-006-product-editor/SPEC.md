# CAT-006 editor — design spec for the executor

Approved 2026-10-28 (layout v0.1 + colours D3). Values: `TOKENS-D3.md`. Exact pixels and copy: `preview/*.html`.
Data behaviour (what is written where, specials vs tiers, CRM source, partial-save path): `plans/CAT-006_product-admin-editor_requirements_20261028.md`. This spec does not override it.

## 1. Scope and integration

- Desktop only. Designed at 1440 px, checked at 1920 px (`screens/*@1920.png`); below ~1280 the tables scroll horizontally inside their frame, nothing else adapts.
- Bootstrap 5 + plain JS inside the stock OpenCart 4 admin shell. The header and left menu in the snapshots are a placeholder: **do not restyle the stock admin header, left menu or any stock page.**
- D3 applies to the editor content area only. Wrap every editor page in one root element (e.g. `<div class="bse" data-bs-theme="dark">`) that declares the `--bse-*` tokens, `color-scheme: dark`, `background: var(--bse-shell)`, Manrope. All editor CSS is scoped under `.bse`. Bootstrap variables used inside (`--bs-body-bg`, `--bs-border-color`, `--bs-primary`…) are re-pointed to `--bse-*` inside `.bse` only.
- Copy is Ukrainian and approved as shown in the previews; use it verbatim. Sample product data is not copy.
- Prices are shown as `4500 грн`, quantities as `11 шт`.
- Every interactive element is a real `<button>`, `<a href>`, `<input>`, `<select>`, `<label>`. Icon-only buttons carry `aria-label`. Visible focus ring on all controls (`TOKENS-D3.md`).

## 2. Product list (`01`–`06`)

Structure top to bottom: title row (H1 + one-line hint; right: CRM snapshot time, «Оновити з CRM», primary «Новий товар») → optional CRM error banner → search (44 px, max-width 560) → filter row → chips row → table → sticky selection bar.

Search
- Instant, case-insensitive substring match on name **or** model; no submit button; clear (×) button appears when not empty.

Filters
- Selects: Категорія, Виробник, Статус, Наявність, Акція. Ranges: Ціна, грн (від/до), Кількість (від/до).
- Each active filter shows a removable chip (`--bse-accent-soft` / accent text). «Скинути всі» clears filters (not the search).
- Counter «Знайдено: N» reflects search + filters.
- Filters and search **persist** when the owner opens a product and comes back (storage mechanism is the executor's choice; it must survive a page navigation).
- Legend at the right of the chips row explains the two CRM markers.

Table (columns: checkbox · thumbnail 40 · name + meta · model · site price · quantity · status · special · actions)
- Row min-height 64. Selected rows: `--bse-sel` background.
- Disabled product: name in `--bse-ink-3` + «ВИМКНЕНО» badge in the meta line.
- Out of stock: quantity in `--bse-danger` + caption «немає в наявності»; thumbnail at opacity .45.
- Active special: base price stays as the main value; second line «акц. N грн» in `--bse-danger`; special column shows inverse badge «АКЦІЯ ДО dd.mm».
- CRM markers: star after the price when site base price ≠ CRM РРЦ; «CRM» mini-badge after the quantity when site quantity ≠ CRM stock. Tooltip on hover **and** keyboard focus: «У CRM: 4800 грн» / «У CRM: 3 шт».
- Actions: edit (link to the product page), copy (stock copy mechanism; success toast top-right).

Inline editing (price, quantity, status)
- Click the value → 32 px input with 2 px accent border, value preselected; hint under it «Enter — зберегти · Esc — скасувати».
- Enter → validate integer ≥ 0 → «Збереження…» with spinner → «Збережено» in `--bse-ok` for 2 s. Esc or opening another cell cancels.
- Error (validation or server): the cell gets a `--bse-danger` border and the line «<reason> · Повторити»; «Повторити» resends the same value (`04`).
- Status switch saves immediately with the same saving/saved feedback.

CRM refresh (manual only, never on page load)
- In progress (`05`): button disabled with spinner «Оновлюємо…», caption under the snapshot time «Запит триває, це може зайняти до 30 с. Список можна редагувати.»; the list stays usable.
- Success: snapshot time updates; toast «Дані CRM оновлено. Розбіжності: ціна — N, кількість — N.»
- Failure (`06`): banner with `--bse-danger` border, title «Не вдалося оновити дані з CRM», cause + statement that markers show the previous snapshot; buttons «Спробувати ще» and close.

Other states: loading skeleton (`03`, 8 rows, pulse), empty result (`02`, with «Очистити пошук» and «Скинути фільтри»).

Selection bar
- Appears when ≥ 1 row selected; sticky at the bottom of the viewport inside the content area; inverse surface. «Обрано: N · Масові дії:» + four entry buttons (Акційна ціна / Знижка від кількості / Кількість / Статус) that open the side panel on the matching tab + «Зняти вибір». Header checkbox selects all rows matching the current search/filters.

## 3. Bulk-actions side panel (`07`–`10`)

- Offcanvas from the right, 900 px wide, full height; list stays visible behind a `rgba(17,24,39,.28)` backdrop. Header: title, «Обрано N товарів…» line, close (×).
- Tabs: Акційна ціна · Знижка від кількості · Кількість · Статус. Switching tab resets to step 1 and drops manual overrides.
- Stepper: 1 Налаштування — 2 Перевірка — 3 Результат. Done steps show a check.
- Footer buttons per step: step 1 «Скасувати» / «Переглянути зміни →»; step 2 «← Змінити налаштування», status message, «Застосувати до N товарів»; step 3 «Повторити невдалі (N)» (only if errors) / «Готово, до списку».

Акційна ціна (step 1)
- Three radio cards with inline inputs; the selected card has an accent border and `--bse-sel` fill. Focusing an inline input selects its card.
  1. «Мінус [N] грн від основної ціни» → `base − N`.
  2. «Встановити ціну [X] грн для всіх обраних» → `X`.
  3. «Мінус [P] % з округленням вниз до [10 | 50 | 100] грн» → `floor(base × (100 − P) / 100 / step) × step`.
- Live example line under the cards (calculated on one selected product).
- Fields: Початок, Кінець (date), Група покупців (default group preselected). Below: chips with the selected models.

Знижка від кількості (step 1)
- Repeatable rows «від [Q] шт → мінус [N] грн за штуку» with delete; «+ Додати поріг»; checkbox «Діє лише в період» reveals two dates.
- Note (verbatim): tiers are **added** to existing ones; nothing is replaced or deleted (owner decision D2).

Кількість: segmented «Встановити / Додати / Відняти» + value; subtract never goes below 0. Статус: two radio cards (Увімкнути / Вимкнути).

Preview (step 2)
- Table: Товар · Основна · Зараз · Нове значення · Період · Дія. Every new value is an editable input (status: a toggle button). Rows with no change show «Без змін» and are skipped.
- Conflict (special overlaps an existing special, D1): row on `--bse-warn-bg`, explanation under the name, segmented «Замінити / Пропустити» with a `--bse-warn-line` border until chosen. Header pill «Конфліктів: N».
- Warnings that do not block: new special ≥ base price; duplicate tier (same quantity, group and overlapping period — `10`).
- Blocking: an unresolved conflict or an invalid value. «Застосувати» is disabled (grey) and the footer explains why.

Result (step 3, `09`)
- Summary chips: «Застосовано: N» (`--bse-ok-bg` / ok), «Пропущено: N», «Помилка: N» (danger outline).
- Per row: «Застосовано» / «Пропущено» / error text «Помилка: сервер не відповів. Цей товар не змінено.» + «Повторити». Each product is applied atomically; a failed row means that product is unchanged.

## 4. Product page (`11`, `12`)

- Separate page. Sticky header: «← До списку», product name (20/800) + model (mono), status switch + label, «Є незбережені зміни» pill (only when dirty), «Усі зміни збережено» (after save), «На сайті ↗», primary «Зберегти» (spinner while saving; toast «Товар збережено.»).
- Left sub-nav 180 px, sticky: Основне · Дані · Категорії та фільтри · Атрибути · Родина варіантів · Знижки · Фото · SEO. Active item on `--bse-accent-soft`. A small accent dot marks a section that needs attention (non-dictionary attribute value). Below a divider: «Згорнути всі», «Розгорнути всі».
- Every section is a collapsible tile: chevron button at the right of the title (rotated −90° when collapsed, `aria-expanded`). Default: all expanded. Clicking a sub-nav item expands its tile and scrolls to it.
- Leaving with unsaved changes (`12`): dialog «Вийти без збереження?» with «Залишитися», «Вийти без збереження» (danger outline), «Зберегти й вийти» (primary). Also intercept browser navigation (`beforeunload`).

Sections
- Основне: Назва; Опис with segmented «Код HTML / Перегляд» (code view: mono textarea on `--bse-bg`; the editor must not alter pasted markup); Meta-заголовок full width with live counter («… символів · рекомендовано до 60»); Meta-опис with counter. No meta keywords field.
- Дані: row 1–2 grid of three: Модель (hint «За моделлю товар звіряється з CRM»), Артикул, Основна ціна (CRM line with star when different), Кількість (CRM line when different), Мінімальне замовлення, Коли немає в наявності. Row 3: Виробник (1/3) + «Вираховувати зі складу» switch box (2/3) with a one-line hint that changes with the state.
- Категорії та фільтри: two equal columns. Left: category chips + type-ahead add + note that parent categories are not added automatically. Right: filter chips («Група › Значення») + type-ahead add + hint.
- Атрибути: «Тип товару» select in the tile header (Бустер-бокс, Бустер, Стартова колода, Rare Pack, Брелок 3D …). Changing it adds that type's attribute set and shows an info line; existing values are never deleted. Each row: name + field-type caption · typed control · remove (×).
  - Field types: list (select of allowed values), year (select «Залежно від сету», 2030 … 1995), number + unit, yes/no (segmented Так / Ні), free text.
  - Value not in the dictionary: row on `--bse-warn-bg`, read-only value box, message «Значення немає в довіднику. Стандартне: «…».» + «Замінити на стандартне» (one click replaces it and shows the select).
  - Empty attribute: dashed field + «Прибрати порожній».
  - «+ Додати атрибут…»: grouped select with **every attribute in the store** (all attribute groups), not only the type template; already present ones are disabled; choosing a removed one restores it; new rows appear highlighted (`--bse-sel`). «Повернути прибрані (N)» undoes removals before save.
- Родина варіантів: Ключ родини (select of existing families with member count), Підпис осі (shared by the family), Значення цього товару; compact member list (current product highlighted); link «Відкрити всю родину →».
- Знижки: two separate tables. «Акційна ціна · на період, для 1 шт» (Група, Ціна (+ difference), Період, Стан, actions) and «Від кількості · ціна за штуку при замовленні від N шт» (Група, Від, Ціна за шт, Період, Стан, actions). Status badges: «Діє зараз» (accent on accent-soft), «Заплановано» (outline), «Завершено» (line-2 fill, row text secondary), «Не діє на сайті» (warn, row on `--bse-warn-bg`, struck price, info button with the tooltip text from `11`). Legacy rows are displayed as they are; no automatic cleanup.
- Фото: 5-column grid. First image = main: accent border + «ГОЛОВНЕ» badge. Each tile: position number, file name, «Зробити головним» (non-main), remove. Drag-and-drop reorder with a drop indicator. Last tile «Додати фото» opens the picker.
- SEO: «Адреса сторінки (SEO URL)» with fixed prefix `boostershop.website/` and mono slug. On change, show the warning that the old URL stops working without a redirect.

## 5. Variant family view (`13`)

- Sticky header with breadcrumb, title «Родина варіантів <KEY>», unsaved pill, «Зберегти».
- Left: tile with Ключ родини (read-only, hint how to move a product) and Підпис осі (editable); members table: order (drag handle + up/down buttons), thumbnail, name + model (+ «ЦЕЙ ТОВАР» badge), editable value label, price, quantity, status, edit link; add-member search.
- Right (360 px, sticky): «Так виглядає на сайті» — axis label + chips in the current order exactly as the storefront variant selector; disabled members are listed as hidden.
- Saves through the same contract as the per-product family fields (requirements §4.2).

## 6. Image picker modal (`14`, `15`)

- Modal 1080 px wide, max height 872, radius 14, backdrop `rgba(17,24,39,.45)`.
- Search by file name (searches all files; while a query is active the tabs are deselected). Tabs: Нещодавні (newest first) · Цього товару · Усі файли. Caption line explains the current list.
- Drop zone strip (dashed): idle «Перетягніть фото в це вікно або [Вибрати файли] · JPG, PNG, WebP»; drag-over state accent dashed border on accent-soft fill «Відпустіть файли, щоб завантажити їх сюди». The whole modal accepts the drop.
- Upload tiles show progress («Завантаження 64%» + bar) and per-file errors in danger. Finished uploads appear first in Нещодавні and are preselected.
- Grid 5 columns of square thumbnails, name and «дата · розмір». Multi-select: accent border + filled check circle. Images already linked to the product: «УЖЕ В ТОВАРІ» badge, dimmed, not selectable.
- Footer: «Обрано: N», «Зняти вибір», «Скасувати», primary «Додати N фото» (grey «Оберіть фото» when nothing is selected).

## 7. Acceptance (visual)

- [ ] Each screen/state matches its `preview/*.html` at 1440 px: layout, spacing, sizes, colours, copy. Differences only where real data or Bootstrap control rendering forces them.
- [ ] All colours come from `--bse-*` tokens; no hard-coded hex in markup.
- [ ] Stock admin pages, header and left menu look exactly as before (compare before/after screenshots).
- [ ] At 1920 px the content stretches without broken alignment (`screens/*@1920.png`).
- [ ] Keyboard: every control reachable with Tab, visible focus ring, Enter/Esc in inline edit, Esc closes panel/modal/dialog, tooltips open on focus.
- [ ] Every state in the snapshot index can be reproduced in the running editor.
