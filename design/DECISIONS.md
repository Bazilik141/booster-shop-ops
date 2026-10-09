# Approved decisions by area

Each entry: the decision, then the spec (`specs/`) and mockup (`reference/`) that carry full detail. Newer entries supersede older ones for the same element.
"Live" = the patch marker was found in the served CSS/JS on 2026-10-09. Patches live in the repository `patches/` folder.

## Foundation
- **Logo** (owner, 2026-10-09): the designer package is canonical; files in `assets/logo/`, rules in `BRAND.md`. Supersedes `bs-logo-crop.png`. Site header and favicon not yet migrated.
- **Gold** (owner, 2026-10-09): site gold `#C68A00`; `#D4A017` retired. Live since RD-10F (2026-06-11).
- **TECH-045 fonts and contrast** (owner 2026-09-25…29, live): Manrope self-hosted (WP-B); JetBrains Mono and IBM Plex Sans Condensed removed, payment requisites in system monospace (WP-A); contrast and ARIA fixes, "До каталогу" in the empty mini-cart is a secondary button (WP-D); purchase controls `--bs-buy #12883E` / hover `#15803D`, card "Купити" 16/700 one line on mobile, checkout confirm 17px, "Переглянути замовлення" white text (WP-E). Patches: `TECH-045_wpa…wpe_2026092*.php`; handoff `handoffs/handoff_TECH-045_render-blocking-fonts-icons_20260925.md`.
- **Design system base** (2026-05-21): single `boostershop-ds.css`, `bs-*` classes, Manrope, tokens. Phases: category header, empty states, /special order, sticky mobile ATC, transactional emails, Twig templates (header, footer, product card, product page, cart, checkout, account, FAQ, home tiles).
  Spec: `HANDOFF.md`. Mockup: `Booster Shop UX Audit.html`. Several sections are superseded below (FAQ, home tiles, cart, mini-cart, checkout, header, product page, PUMB).
- **Emails:** white bg, ink headings, blue links, one green CTA per email, status changes in blue-soft (not green), no gold. Spec: `HANDOFF.md` §4b.
- **Empty state** `.bs-empty` partial reused in cart, search, category. Spec: `HANDOFF.md` §2.

## Header and navigation
- **Header** (`HANDOFF-header-menu-search.md`): utility strip above header removed. Burger left of logo, opens 380px left panel (same menu as mobile; guest/authorized states). Account and Telegram are blue ghost links; cart is the only green element. Mockup: `Мобільний пошук редизайн.html`.
- **Mobile search:** field expands in place with live results; no redirect to /search on tap. Same spec.
- **UX-003/005 header** (2026-10-04): sticky main row on all pages incl. checkout, shadow after scroll; trust strip on home scrolls. "Каталог" label on burger button ≥1024. Mobile placeholder "Пошук". Single clear × (hide native cancel), 44×44 target. Burger: add "Фігурки та декор" under Pokémon and One Piece; brand = logo image 110px (variant A). Spec: `CLAUDE CODE - UX-003-005_header-burger_20261004.md`. Mockups: `UX-003 UX-005 UX-009 - макети.html`, `… - етап 3.html`.
- **Mobile cart badge** (BUG-004 A, live): `CODEX - BUG-004A_mobile-cart-badge_20261006.md`.

## Search
- **Live search** (UX-009): compact rows 56px thumb, no description, 2-line name, sale price danger + strikethrough; one "nothing found" message; CSS spinner; error state after ~8s with "Шукати на сторінці результатів →"; module files untouched. Spec: `CLAUDE CODE - UX-009_live-search_20261004.md`.
- **Search results page** (UX-009): form inside `<details>` "Змінити пошук" (open when 0 results); H2 visually hidden; compare button removed; DS controls, no Font Awesome; empty result shows "Подивіться розділи". Spec: `CLAUDE CODE - UX-009_search-page_20261004.md`.

## Home
- **H1 + Trust Strip:** trust strip V2 (white + 3px gold rule, 4 items, non-interactive); H1 hero "Різкий" gradient. Spec: `design_handoff_h1_truststrip/README.md`. Mockup: `H1 та Trust Strip - варіанти.html`.
- **H1 text lockup, Option A** (2026-06-01): line 1 "Оригінальні бустери та бокси TCG" 800 with "TCG" in `--bs-pokemon`; line 2 "Pokémon · One Piece · та інші ігри" 600 `--bs-ink-3`. Spec: `HANDOFF-h1-category-heading.md`. Mockup: `H1 фінал.html`.
- **Main category tiles, Component B** (2026-09-03): two 1:1 full-bleed key-art illustrations (Pokémon, One Piece), two columns at every breakpoint, one white CTA "Дивитись усе" top-left over a CSS scrim; no text name, description, count or border; `alt` + `aria-label` required; legacy `.category-tiles` row removed. Spec: `CODEX - UI-CD_B-tiles_20260903.md`. Mockup: `B - плитки категорій ФІНАЛ.html` (`FINAL_CSS`). Art: `assets/`.
- **Secondary tiles** "Інші TCG" / "Аксесуари" (CAT-002-5): thin 84px mirror tiles, 3px left accent + 12% tint icon plate; icons 07 "Стопка" and 10 "Картка-блиск". Unchanged by Component B. Spec: `CODEX - CAT-002-5 secondary tiles.md`. Mockups: `Другорядні плитки - варіанти.html`, `Іконки другорядних плиток - варіанти.html`.

## Category
- **Subcategory navigation mobile, C3 "inverted hierarchy"** (2026-09-03): small caption line (name · count), subcategories as a 2-column grid of link cards with counts, no horizontal scroll, no truncation. Spec: `CODEX - UI-CD_final_A-credit_C-subcat_20260903.md`. Mockup: `C - підкатегорії mobile у живому контексті.html`.
- **Filter, variant C** (2026-10-04): no side column; one row = subcategories | "Фільтр" button (counter, chevron) | sort. Panel inside the category header card, collapsible groups (1/2/4 columns), checkboxes `--bs-blue`, chips of selected values + "Скинути"; no "Пошук" button; grid 4 columns ≥1024. Filter URLs and canonical unchanged. Spec: `CLAUDE CODE - UX-003_category-filter-C_20261004.md`. Mockup: `UX-003 UX-005 UX-009 - етап 3.html`.
- **Filter runner 9** (2026-10-06, live; "Показати ще" append fix `UX-003_load-more-fix_20261006.php` also live): apply filters/sort/pagination via fetch + `history.pushState`, AbortController, fallback to full navigation on any error; panel stays open; chips row always last in the card; mobile sticky "Показати N товарів"; loading state; live-region announcements. Spec: `CODEX - UX-003_runner9_filters-no-reload_20261006.md`, `UX-003_runner9_implementation-note.md`. Mockup: `UX-003 runner 9 - фільтри без перезавантаження.html` (+ `ux003-r9.js`).
- **Grid 576–991:** fluid container, side paddings equal to header's. Spec: `CLAUDE CODE - UX-003_grid-fluid-991_20261004.md`.
- **/special:** products before FAQ. Spec: `HANDOFF.md` §3.

## Product card (catalog)
- **States** (2026-06-01): discount badge black, no tiers; sale price `#DC2626`; out of stock = dimmed photo, one status, "Повідомити про наявність"; preorder = blue badge + ETA line + blue "Передзамовити"; equal-height cards. Edit `product/thumb`. Spec: `CODEX - product-card-states-preorder.md`. Mockups: `Картки товару - пропозиції.html`, `Колір знижки та ціни - рішення.html`.
- **Card variant D "Без коробки"** (2026-09-19, live, round 2): no background, border, radius or shadow on the tile; image slot transparent, `object-fit: contain`, original aspect kept; caption separated by a hairline; lift 3px on hover/focus. Round 1 fills/passe-partout/shadow rejected ("kills the site's lightness"). The spec header still says "proposal"; the live patch settles it. Spec: `CLAUDE CODE - картка товару D без коробки_20260919.md`. Patch: `UI-PCARD-D_no-box-card_20260919.php`. Mockup: `D - картка товару - раунд 2.html`, section D (variant A in the same file is a reserve, not canon).
- **Badge position** (UI-PCARD-BADGE, 2026-10-06, live): both tile badge corners at `top: 4px` (was 18px), sides 18px. Patch: `UI-PCARD-BADGE_raise-badges_20261006.php`.
- **Rare Pack badge** (CAT-004 SD-7, 2026-09-18, live): left-corner listing badge `.bs-badge--rare`, bg `#4C0519`, text `#FDE68A`, shown when attribute «Тип товару» (id 27) = «Rare Pack»; stacks in a flex column with other left badges. Patch: `CAT-004-SD-7_rare-pack-listing-badge_20260918.php`; identifier canon `plans/CAT-004_op-rare-packs_identifier-canon_20260916.md`.
- **Preorder delivery-term line:** mockup `D - термін доставки передзамовлення v2 ФІНАЛ.html`.

## Product page
- **RD-10 fix** (2026-06-11): breadcrumb as pill chips, active chip in category color; gallery `max-height`; "Виробник:" label and "В наявності: N шт"; bulk discount block from `product_discounts`; 0 reviews → link "Відгуки про нас →"; 3-item trust row with icons, different for in-stock and preorder. Spec: `CODEX - product-page-rd10-fix.md`. Mockup: `Сторінка товару - фінал.html`.
- **RD-PP-META, variant B "status card"** (2026-09-24): info block under the title (reviews button, manufacturer, status card per state). The CSS block in the spec file is the single source. Spec: `CODEX - RD-PP-META_status-card_20260924.html`. Mockup: `RD-PP-meta - статус, виробник, відгуки.html`.
- **Variant family selector** (CAT-004, owner 2026-09-19, live): a family = independent products, one axis only (colour, size, deck type or set); membership in the shop's own table, not in attributes or options. On the product page a `.bs-variant` block: axis label (e.g. «Сет»), then a wrapping row of link chips, each chip = value + price, min-height 46, radius `--bs-r-sm`; current chip `is-active` (blue on blue-soft, `aria-current="page"`); out-of-stock chip `is-off` (diagonal hatch on `--bs-bg`, `--bs-ink-4` text, visually hidden «немає в наявності»), still a link. No "+X" surcharge. Live on Rare Pack pages (e.g. EB-02 Rare Pack). Canonical rules: `AGENTS.md` → "Variant products — canonical rules". Patches: `CAT-004_variant-family_20260919.php`, `CAT-004_variant-cosmetics_20260923.php`. The master/variant selector (`CAT-004_variant-selector_20260916.php`) was deployed, failed the owner's test and was rolled back on 2026-09-19.
- **Sticky add-to-cart on mobile** (≤768): `HANDOFF.md` §4a.
- **FAQ accordion, Variant A "Quiet hairlines"** (2026-06-03, supersedes HANDOFF.md §5.9 Variant B): vanilla JS normalizer parses any AI-written FAQ format into one canonical `.bs-faq` DOM; plain heading, hairline dividers, gold chevron when open, grid-rows animation. Spec: `CODEX - FAQ accordion normalizer.md` + `faq-accordion/bs-faq.css|js`. Mockup: `FAQ редизайн v2.html`.

## Payment by installments (PAY-001)
- **Final consolidated spec** (2026-07-22) supersedes `credit-flow` and `UI2-answers`. Bank materials take priority. Spec: `CODEX - PAY-001-FINAL-handoff.md`; addendum `CODEX - PAY-001-ADDENDUM-2.md` (cart, preorder checkout blocker). Mockups: `PAY-001 Купити в кредит.html`, `PAY-001 Чекаут - стан disabled (Оплатити частинами).html`.
- **Credit modal buttons, Component A** (2026-09-03): side by side 50/50 on all breakpoints, `min-height:48px`, wrap allowed, 16px above; primary black `#111`, secondary white outline; same for monobank and PUMB. Spec: `CODEX - UI-CD_final_A-credit_C-subcat_20260903.md`.
- **"Оплата і доставка" page** (PAY-001-INFO, 2026-07-26): approved layout and copy; PUMB logo next to "Сплачуйте частинами"; no monobank round sticker badge. Trust live DOM classes `.bs-cp-wrap/.bs-cp-breadcrumb/.bs-cp-layout/.bs-cp-toc` over the older content-pages package. Spec: `CODEX - PAY-001-INFO-page-redesign.md`. Mockups: `PAY-001 Оплата і доставка - сторінка.html`, `… - редизайн.html`.

## Cart, mini-cart, toast
- **RD-11 cart page** (2026-09-21): cards not tables; grid `1fr 340px`, sticky summary; mobile sticky CTA bar; stepper 44×44; "Модель" column removed; free-shipping line always shown with admin value, progress bar only when remaining ≤30% of threshold, reached state `--bs-green-hover` on `--bs-green-soft`; stock error = warning plate + grey disabled CTA; bestseller module as is. Spec: `CODEX - RD-11_cart-page_20260921.md`.
- **RD-12 mini-cart + toast** (2026-09-21): right drawer 380px (desktop) / bottom sheet (mobile ≤768), opens only manually; desktop toast variant A "Куточка" (300px card top-right under cart, no ×, auto-hide); mobile toast = full-width strip under header, green success / danger error. Spec: `CODEX - RD-12_minicart-toast_20260921.md`. Mockup: `RD-11 RD-12 - кошик, міні-кошик, тост.html`.

## Checkout and result pages
- **RD-13 checkout reskin** (2026-07-05): single page, four cards (Отримувач, Доставка, Оплата, Замовлення), one CTA "Підтвердити замовлення →"; markup + CSS only. Round-2 fixes file is authoritative for the current patch; main spec is the end-state. JSX wins over prose. Specs: `HANDOFF-RD13-checkout.md`, `HANDOFF-RD13-checkout-FIXES-round2.md`, `RD-13.1 Codex handoff.md`. Mockup: `RD-13 Checkout reskin.html` (`rd13-checkout.jsx`).
- **RD-14 success** (2026-10-04): direction "Кроки", one 620px column; SVG instead of emoji; IBAN details card; "Що далі" card; items collapsed <1024; First15 variant A "рамка"; no green buttons on the page; `--bs-buy` for success green. Spec: `CLAUDE CODE - RD-14_success_20261004.md`.
- **RD-15 failure** (2026-10-04): direction "Дві колонки"; 600px card with 4px warning top rule; new owner copy (verbatim in spec); actions "На головну" + Telegram, both secondary. Spec: `CLAUDE CODE - RD-15_failure_20261004.md`. Mockup for both: `RD-14 RD-15 - фінал.html`.
- **Content pages** (May 2026): `.bs-cp-*` layer for Guarantee, About, Delivery, Returns, Offer, success fragments. Spec: `content-pages/README.md` (+ `text-revision-tasks.md`). Mockup: `Контентні сторінки редизайн.html`. Markup partly superseded by live DOM (see PAY-001-INFO).

## Error pages
- **404** "Цю сторінку видалено з колоди": three fanned TCG cards (inline SVG), search, CTAs, category shortcuts; no JS. Spec: `design_handoff_404/` (README, twig, css). Mockup: `404 - Сторінка не знайдена.html`.

## Not canon yet
- **Variant families on 3D-print products** (3D-P-011 trigger: Onix 21/15 cm): the selector itself is live (see Product page); no 3D product has a family configured yet (no selector on the live Onix page, 2026-10-09). The 2026-09-12 master/variant model and the Claude artifact "Селектор варіантів — картка товару" (2026-09-15) are superseded.
- **Logo migration on the site** (header SVG, favicon from the mark): not started.
- **Pending owner decisions** listed under "Known drift" in `TOKENS.md`: page background, preorder badge colour, "in stock" green badge.
