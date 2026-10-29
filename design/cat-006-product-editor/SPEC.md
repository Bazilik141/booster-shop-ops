# CAT-006 editor — design spec for the executor

Approved 2026-10-28 (layout v0.1 + colours D3); round 2 applied 2026-10-29 (`handoffs/handoff_CAT-006_claude-design-round2_20261029.md`, review `diagnostics/CAT-006_design-package_review_20261028.md`).
Values: `TOKENS-D3.md`. Exact pixels and copy: `preview/*.html`.
Data behaviour (what is written where, specials vs tiers, CRM source, partial-save path): `plans/CAT-006_product-admin-editor_requirements_20261028.md`. This spec does not override it.

## 1. Scope and integration

- Desktop only. Designed at 1440 px, checked at 1920 px (`screens/*@1920.png`); below ~1280 the tables scroll horizontally inside their frame, nothing else adapts.
- Bootstrap 5.3.3 + plain JS inside the stock OpenCart 4 admin shell. The header and left menu in the snapshots are a placeholder: **do not restyle the stock admin header, left menu or any stock page.**
- D3 applies to the editor content area only. Wrap every editor page in one root element (e.g. `<div class="bse" data-bs-theme="dark">`) that declares the `--bse-*` tokens, `color-scheme: dark`, `background: var(--bse-shell)`, Manrope. All editor CSS is scoped under `.bse`. Bootstrap variables used inside (`--bs-body-bg`, `--bs-border-color`, `--bs-primary`…) are re-pointed to `--bse-*` inside `.bse` only.
- Bootstrap pieces rendered outside the root: tooltips and popovers are created with `container` = the editor root element. Modal and offcanvas backdrops are appended to `<body>`, so their colours are set on a selector that does not depend on `.bse` ancestry (e.g. `body.bse-page .modal-backdrop`, `body.bse-page .offcanvas-backdrop`, where `bse-page` is added to `<body>` only on editor routes) using the literal backdrop values from `TOKENS-D3.md`.
- Copy is Ukrainian and approved: everything shown in the previews **and** every «…»-quoted string in this spec (toasts, inline-edit hints, «Без змін», «Повернути прибрані (N)», «Оберіть фото», dialog texts, empty states) is used verbatim. Sample product data is not copy.
- Prices are shown as `4500 грн`, quantities as `11 шт`, dates as `дд.мм.рррр` (uk-UA).
- Every interactive element is a real `<button>`, `<a href>`, `<input>`, `<select>`, `<label>`. Icon-only buttons carry `aria-label`. Visible focus ring on all controls (`TOKENS-D3.md`).

Hover states (all scoped to `.bse`)
- Table row: background `--bse-hover-row`.
- Editable value in a list cell: border `--bse-hover-line`, background `--bse-hover` (signals «click to edit»).
- Primary button: background `--bse-accent-hover`. Secondary / outline button, menu item, pager button: background `--bse-bg`. Icon-only ghost button: background `--bse-hover`. Link: underline.
- Disabled controls never change on hover.

### Stock fields the editor does not show

Saving from the editor (whole product or any partial save) **never clears, resets or rewrites** these fields; they keep their current values. They remain editable only in the stock form (hidden fallback). Source: stock `product_form.twig` of OpenCart 4.1.0.3 (backup 07.09.2026).

| Stock tab | Fields not shown in the editor |
|---|---|
| Загальне | meta keywords (`meta_keyword`), tags (`tag`) |
| Дані | product codes other than the one shown as «Артикул» (`product_code[]`: UPC, EAN, JAN, ISBN, MPN …), location, tax class, date available, requires shipping (`shipping`), sort order, reward points (`points`) |
| Зв’язки | stores (`product_store[]`), downloads (`product_download[]`) |
| Опції | all product options (`product_option[]`) |
| Підписка | all subscription plans (`product_subscription[]`) |
| Знижки | `priority` and `type` of existing discount rows (shown rows keep them; new rows get the stock defaults) |
| Бонусні бали | `product_reward[]` |
| SEO | SEO URLs for other stores / languages (`product_seo_url[store][language]`; the editor shows the default store, Ukrainian) |
| Дизайн | layout override (`product_layout[]`) |
| Native variants | `master_id`, `variant[]`, `override[...]` |

«Артикул» maps to one entry of `product_code[]`; which identifier code it uses (e.g. SKU) is an executor diagnosis item. The main photo is the stock `image` field; the other photos are `product_image[]` with `sort_order`.

## 2. Product list (`01`–`06`)

Structure top to bottom: title row (H1 + one-line hint; right: CRM snapshot time, «Оновити з CRM», primary «Новий товар») → optional CRM error banner → search (44 px, max-width 560) → filter row → chips row → optional select-all banner → table → pager → sticky selection bar.

«Новий товар» opens the editor product page in create mode (§4.1).

Search
- Instant, case-insensitive substring match on name **or** model over **all** products (not only the current page); no submit button; clear (×) button when not empty. Requests are debounced (~250 ms); results reset to page 1.

Filters
- Selects: Категорія, Виробник, Статус, Наявність, Акція. Ranges: Ціна, грн (від/до), Кількість (від/до).
- Each active filter shows a removable chip (`--bse-accent-soft` / accent text). «Скинути всі» clears filters (not the search).
- Counter «Знайдено: N» reflects search + filters.
- Search, filters, sort, page size and current page **persist** while the owner opens products and comes back, for the lifetime of the browser tab (`sessionStorage`). Closing the tab or «Скинути всі» resets filters; logging out resets everything.
- Legend at the right of the chips row explains the two CRM markers.

Sorting
- Sortable headers: Назва (default, ascending), Модель, Ціна на сайті, Кількість. Header = button with text + arrow: active column `--bse-ink` text and accent arrow «↑» / «↓»; inactive columns `--bse-ink-3` text and neutral «↕». First click on a column sorts ascending, next click toggles. The header cell carries `aria-sort="ascending|descending|none"`; the button `aria-label` «Сортувати: <колонка>[, зараз за зростанням|спаданням]». Ties fall back to name ascending. Changing the sort returns to page 1.

Paging
- Pager under the table: left «Показано 1–15 з 151»; centre page buttons (previous, 1, 2, …, current ±1, …, last two, next; current page filled accent with `aria-current="page"`); right «На сторінці:» segmented 15 / 25 / 50 (default 15). Changing page size returns to page 1.

Selection
- Header checkbox = **current page only** (`aria-label` «Обрати всі на цій сторінці»).
- When every row of the page is selected and more products match, a banner above the table offers «Обрано всі 15 на цій сторінці. Обрати всі 151 знайдених»; after that it reads «Обрано всі 151 знайдених товарів. Зняти вибір».
- Selection survives paging and sorting; «Обрано: N» counts all selected products, also those on other pages.

Table (columns: checkbox · thumbnail 40 · name + meta · model · site price · quantity · status · special · actions)
- Semantics: `role="table"`, `row`, `columnheader`, `cell` (or a real `<table>`).
- Row min-height 64. Selected rows: `--bse-sel` background.
- Disabled product: name in `--bse-ink-3` + «ВИМКНЕНО» badge in the meta line.
- Out of stock: quantity in `--bse-danger` + caption «немає в наявності»; thumbnail at opacity .45.
- Active special: base price stays the main value; second line «акц. N грн» in `--bse-danger`; special column shows inverse badge «АКЦІЯ ДО dd.mm».
- CRM markers: star after the price when site base price ≠ CRM РРЦ; «CRM» mini-badge after the quantity when site quantity ≠ CRM stock. Tooltip on hover **and** keyboard focus: «У CRM: 4800 грн» / «У CRM: 3 шт».
- Actions: edit (link to the product page), copy (stock copy mechanism; toast «Створено копію «<назва>». Копія вимкнена — змініть у ній назву й модель.»).

Inline editing (price, quantity, status)
- Click the value → 32 px input with 2 px accent border, value preselected; hint under it «Enter — зберегти · Esc — скасувати».
- Enter or Tab → validate integer ≥ 0 → «Збереження…» with spinner → «Збережено» in `--bse-ok` for 2 s. Tab then moves focus to the next editable cell in the row (Shift+Tab: previous). Esc cancels. Clicking outside or opening another cell cancels without saving.
- Error (validation or server): the cell gets a `--bse-danger` border and the line «<reason> · Повторити»; «Повторити» resends the same value (`04`). Validation copy: «Ціна має бути цілим числом», «Кількість має бути цілим числом».
- Status switch saves immediately with the same saving/saved feedback.

CRM refresh (manual only, never on page load)
- In progress (`05`): button disabled with spinner «Оновлюємо…», caption «Запит триває, це може зайняти до 30 с. Список можна редагувати.»; the list stays usable.
- Success: snapshot time updates; toast «Дані CRM оновлено. Розбіжності: ціна — N, кількість — N.»
- Failure (`06`): banner with `--bse-danger` border, title «Не вдалося оновити дані з CRM», cause + statement that markers show the previous snapshot; buttons «Спробувати ще» and close.

Other states: loading skeleton (`03`, 8 rows, pulse, no pager), empty result (`02`, with «Очистити пошук» and «Скинути фільтри», no pager).

Selection bar
- Appears when ≥ 1 product is selected; sticky at the bottom of the viewport inside the content area; inverse surface. «Обрано: N · Масові дії:» + four entry buttons (Акційна ціна / Знижка від кількості / Кількість / Статус) that open the side panel on the matching tab + «Зняти вибір».

## 3. Bulk-actions side panel (`07`–`10`, `18`–`20`)

- Offcanvas from the right, 900 px wide, full height; list stays visible behind the `--bse-backdrop` backdrop. Header: title, «Обрано N товарів. Список ліворуч не змінюється, доки ви не застосуєте дію.», close (×).
- Tabs: Акційна ціна · Знижка від кількості · Кількість · Статус.
- Stepper: 1 Налаштування — 2 Перевірка — 3 Результат. Done steps show a check.
- Footer per step: step 1 «Скасувати» / «Переглянути зміни →»; step 2 «← Змінити налаштування», status message, «Застосувати до N товарів» (tiers: «… рядків»); step 3 «Повторити невдалі (N)» (only if errors) / «Готово, до списку».
- Discarding manual edits: if step 2 has hand-edited values and the owner switches tab, presses Esc or closes the panel, show a confirm dialog «Скинути ручні зміни?» — «Ви змінили значення в N рядках. Якщо продовжити, ці зміни буде втрачено.» — buttons «Залишитися» / «Скинути зміни» (danger outline). Without manual edits no dialog.

Акційна ціна (step 1, `07`)
- Three radio cards with inline inputs; the selected card has an accent border and `--bse-sel` fill. Focusing an inline input selects its card.
  1. «Мінус [N] грн від основної ціни» → `base − N`.
  2. «Встановити ціну [X] грн для всіх обраних» → `X`.
  3. «Мінус [P] % з округленням вниз до [10 | 50 | 100] грн» → `floor(base × (100 − P) / 100 / step) × step`.
- Live example line under the cards. Fields: Початок, Кінець, Група покупців (default group preselected). Below: chips with the selected models.

Знижка від кількості (step 1, `18`)
- Repeatable rows «від [Q] шт → мінус [N] грн за штуку» with delete; «+ Додати поріг»; checkbox «Діє лише в період» reveals Початок / Кінець.
- Note (verbatim in the preview): new tiers are **added**; nothing is replaced or deleted (D2).

Кількість (step 1, `19`): segmented «Встановити / Додати / Відняти» + «Значення … шт»; subtract never goes below 0.
Статус (step 1, `20`): two radio cards «Увімкнути — товари з’являться на сайті» / «Вимкнути — товари зникнуть із сайту».

Preview (step 2, `08`, `10`)
- Table: Товар · Основна · Зараз · Нове значення · Період · Дія. Every new value is editable (status: a toggle button). Unchanged rows show «Без змін» and are skipped.
- Special conflict (overlapping existing special, D1): row on `--bse-warn-bg`, explanation under the name, segmented «Замінити / Пропустити»; until chosen its border is `--bse-warn-strong`. Header pill «Конфліктів: N». Blocks «Застосувати».
- Duplicate tier (same quantity, group and overlapping period, `10`): row on `--bse-warn-bg` with «Увага: Уже є знижка від Q шт (… ) — вийде дубль.» and segmented «Додати / Пропустити», preselected «Додати». Does not block.
- Non-blocking warning: new special ≥ base price.
- Blocking: an unresolved conflict or an invalid value; «Застосувати» is disabled (grey) and the footer says why.

Result (step 3, `09`)
- Summary chips «Застосовано: N», «Пропущено: N», «Помилка: N». Per row «Застосовано» / «Пропущено» / «Помилка: сервер не відповів. Цей товар не змінено.» + «Повторити». Each product is applied atomically.

## 4. Product page (`11`, `12`, `16`, `17`)

- Separate page. Sticky header: «← До списку», title (20/800) + model (mono), status switch + label, «Є незбережені зміни» pill (only when dirty), «Усі зміни збережено» (after save), «На сайті ↗» (edit mode only), primary «Зберегти» (spinner while saving; toast «Товар збережено.»).
- Left sub-nav 180 px, sticky: Основне · Дані · Доставка та зв’язки · Категорії та фільтри · Атрибути · Родина варіантів · Знижки · Фото · SEO. Active item on `--bse-accent-soft`. Accent dot = section needs attention (non-dictionary attribute value). Below a divider: «Згорнути всі», «Розгорнути всі».
- Every section is a collapsible tile (chevron button, rotated −90° when collapsed, `aria-expanded`). Default: all expanded. Clicking a sub-nav item expands its tile and scrolls to it.
- Leaving with unsaved changes (`12`): dialog «Вийти без збереження?» — «У товарі «<назва>» є незбережені зміни. Якщо вийти зараз, їх буде втрачено.» — «Залишитися», «Вийти без збереження» (danger outline), «Зберегти й вийти» (primary). Also `beforeunload`.
- Required fields: Назва товару, Модель, Основна ціна — label suffix «*» in `--bse-danger`, `required` + `aria-required`. Save with an empty required field: field border `--bse-danger`, message under it «Заповніть це поле», focus moves to the first such field, nothing is saved.

### 4.1 Create mode (`16`)

- Opened by «Новий товар». Title «Новий товар», subtitle «Ще не збережено · поля з * обов’язкові». No model line, no «На сайті», no CRM lines. Status defaults to «Вимкнено».
- All fields empty (Кількість 0, Мінімальне замовлення 1). Attributes start empty with «Атрибутів ще немає. Додайте шаблон атрибутів або окремий атрибут нижче.» and the template menu open.
- Родина варіантів shows only «Родину варіантів можна призначити після першого збереження товару.» (dashed box). Знижки shows «Акційних цін ще немає.» / «Порогів ще немає.» with the add buttons. Фото works before the first save (images are attached on save). SEO slug empty with placeholder; no URL-change warning.
- First successful save switches the page to edit mode for the new product (URL changes to the product's edit route).

### 4.2 Sections

- Основне: Назва*; Опис with «Код HTML / Перегляд» (code view: mono textarea on `--bse-bg`, placeholder «Вставте готовий HTML опису»; the editor must not alter pasted markup); Meta-заголовок full width with live counter («N символів · рекомендовано до 60»); Meta-опис with counter. No meta keywords field (preserved, §1).
- Дані: grid of three: Модель* (hint «За моделлю товар звіряється з CRM»), Артикул, Основна ціна* (CRM line with star when different), Кількість (CRM line when different), Мінімальне замовлення, Коли немає в наявності. Next row: Виробник (1/3) + «Вираховувати зі складу» switch box (2/3) with a state-dependent hint.
- Доставка та зв’язки: Вага (number + кг / г), Розміри: довжина × ширина × висота (three numbers + см / мм) — stock weight / length fields and classes; hint «Вага й розміри зберігаються у стандартних полях товару OpenCart.». Divider. Супутні товари: chips (name + model, remove), type-ahead «Додати супутній товар: назва або модель», empty state «Супутніх товарів ще немає.», hint «Зв’язок задається вручну, як у стандартній формі товару.» (stock `product_related[]`).
- Категорії та фільтри: two equal columns. Categories: chips + type-ahead + note about parent categories. Filters: chips «Група › Значення» + type-ahead + hint.
- Атрибути: header button «Додати шаблон атрибутів ▾» (menu: Бустер-бокс, Бустер, Стартова колода, Rare Pack, Брелок 3D, each with its attribute count; footer «Додає рядки шаблону, яких ще немає. Наявні значення не змінюються, вибір шаблону не зберігається.»). Choosing a template only adds missing rows and shows «Шаблон «<назва>»: додано рядків — N. Наявні значення не змінено.» (or «… усі його рядки вже є.»). Nothing about the template is stored.
  - Each row: name + field-type caption · typed control · remove (×). Types: list, year («Залежно від сету», 2030 … 1995), number + unit, yes/no (Так / Ні), free text.
  - Value not in the dictionary: row on `--bse-warn-bg`, read-only value, «Значення немає в довіднику. Стандартне: «…».» + «Замінити на стандартне».
  - Empty attribute: dashed field + «Прибрати порожній».
  - «+ Додати атрибут…»: grouped select with every attribute in the store; present ones disabled; a removed one is restored; new rows highlighted `--bse-sel`. «Повернути прибрані (N)» undoes removals before save.
- Родина варіантів: Ключ родини, Підпис осі (shared), Значення цього товару; compact member list; «Відкрити всю родину →». Create mode: hint only (§4.1).
- Знижки: two tables (Акційна ціна · на період, для 1 шт / Від кількості · ціна за штуку при замовленні від N шт). Badges «Діє зараз», «Заплановано», «Завершено», «Не діє на сайті» (+ tooltip text from `11`). Legacy rows shown as they are.
- Фото: 5-column grid. First image = main (stock `image`): accent border + «ГОЛОВНЕ». Each tile: position number; move ← / → buttons over the image (keyboard reorder; disabled at the ends); file name; «Зробити головним» (non-main); remove. Drag-and-drop reorder with drop indicator. Last tile «Додати фото» opens the picker.
- SEO: «Адреса сторінки (SEO URL)» with prefix `boostershop.website/`. After the slug is edited on an existing product (`17`): warning «Стара адреса вже може бути в Google, у закладках і в посиланнях. Після збереження вона перестане відкриватися, якщо не налаштувати переадресацію (301) на нову.»

## 5. Variant family view (`13`)

- Sticky header with breadcrumb, title «Родина варіантів <KEY>», unsaved pill, «Зберегти».
- Left: Ключ родини (read-only) and Підпис осі; members table with drag handle + up/down buttons, thumbnail, name + model (+ «ЦЕЙ ТОВАР»), editable value label, price, quantity, status, edit link; add-member search.
- Right (360 px, sticky): «Так виглядає на сайті» — axis label + chips in the current order; disabled members listed as hidden.
- Saves through the same contract as the per-product family fields (requirements §4.2).

## 6. Image picker modal (`14`, `15`)

- Modal 1080 px wide, max height 872, radius 14, backdrop `--bse-backdrop-modal`.
- Search by file name over all files (tabs deselected while a query is active). Tabs: Нещодавні · Цього товару · Усі файли (in create mode «Цього товару» is empty).
- Drop zone: idle «Перетягніть фото в це вікно або [Вибрати файли] · JPG, PNG, WebP»; drag-over «Відпустіть файли, щоб завантажити їх сюди». The whole modal accepts the drop.
- Upload tiles: progress «Завантаження N%» + bar; per-file error in danger. Finished uploads appear first in Нещодавні and are preselected.
- Grid 5 columns; multi-select with accent border + check; linked images «УЖЕ В ТОВАРІ», dimmed, not selectable.
- Footer: «Обрано: N», «Зняти вибір», «Скасувати», primary «Додати N фото» (grey «Оберіть фото» when nothing is selected).

## 7. Acceptance (visual)

- [ ] Each screen/state matches its `preview/*.html` at 1440 px: layout, spacing, sizes, colours, copy. Differences only where real data or Bootstrap control rendering forces them.
- [ ] All colours, backdrops and shadows come from `--bse-*` tokens; no hard-coded colour in editor markup or CSS except the backdrop rule of §1, which uses the token values.
- [ ] Stock admin pages, header and left menu look exactly as before (before/after screenshots).
- [ ] At 1920 px the content stretches without broken alignment.
- [ ] Keyboard: every control reachable with Tab, visible focus ring, Enter/Tab/Esc in inline edit, Esc closes panel/modal/dialog (with the discard confirm of §3), tooltips open on focus, photos reorder with ← / → buttons.
- [ ] Every state in the snapshot index can be reproduced in the running editor.
- [ ] Saving from the editor leaves every field in §1 «Stock fields the editor does not show» unchanged (compare DB rows before/after).
