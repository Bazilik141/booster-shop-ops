# Design Brief — RD-14 / RD-15 / UX-003 / UX-005 / UX-009: checkout result pages, header & navigation audit, search

Date: 2026-09-30 | For: Claude Design | Implementation after owner approval: Claude Code | Review: Claude (chat)
Parent series: `plans/RD-redesign-roadmap-plan_2026-05-30.md`

Owner decisions for this batch (2026-09-30):

- **RD-15** failure page: visual only. The Hutko return-route gap (see §5) is split out to `CHECKOUT-012` and is not in this batch.
- **UX-003 / UX-005**: no scope existed. Claude Design first audits the live header and mobile navigation and proposes a ranked change list; the owner approves what goes to mockups and implementation.
- **UX-009**: visual + states only. PS Live Search module code is not changed.
- Delivery in **two packages**: **A** = RD-14 + RD-15 (checkout result pages); **B** = UX-003 + UX-005 + UX-009 (header, menu, search). `TECH-045` is paused so package B is the only writer of `common/header.twig` and the DS stylesheets until B is deployed.

## 1. Workflow

Claude Design → owner review → owner pastes the approved result to Claude (chat) → Claude writes the implementation handoff(s) → Claude Code writes the runners → Claude reviews → owner deploys and runs QA.

Inside Claude Design: Part A first (small, independent). Part B starts with the audit (§6); mockups for B only after the owner approves the audit list.

## 2. Evidence

Give Claude Design the whole folder `live-snapshots/20260930_rd14-rd15-ux003-ux005-ux009/`.

| Path in folder | Source | Notes |
|---|---|---|
| `catalog/view/template/checkout/success.twig` | owner live pull 2026-09-30 (`rd-ux-batch-live-20260930-1533.tar.gz`) | RD-14 target; file dated 2026-09-06 (TECH-015 WP1) |
| `catalog/view/template/checkout/failure.twig` | live pull | RD-15 target; unchanged since 2026-05-29 (R-11b) |
| `catalog/view/template/common/header.twig` | live pull | header, burger panel, mobile search overlay, live-search init + Ukrainian strings; includes TECH-045 WP-A…E |
| `catalog/view/template/product/search.twig` | live pull | search results page (mostly stock OpenCart) |
| `catalog/view/stylesheet/boostershop-ds.css`, `booster-typography.css`, `stylesheet.css` | live pull | DS tokens/components; post TECH-045 WP-E |
| `catalog/view/javascript/patch-mobile-search-menu-redesign.js` | live pull | burger + mobile search behaviour (RD-02, `v=rd010203b-20260531`) |
| `extension/ps_live_search/catalog/view/javascript/ps_live_search.js`, `…/stylesheet/ps_live_search.css` | live pull | vendor dropdown renderer — markup source for §7 |
| `catalog/view/template/common/menu.twig`, `common/cart.twig` | `tech045-live3-20260929.tar.gz` | context only: stock `#menu` navbar; mini-cart (RD-12, done — do not redesign) |
| `_reference-backup-20260924/…` | `backup-9.24.2026_16-35-03_boosters.tar.gz` | read-only: success/failure controllers, uk-ua language strings |

**Owner screenshots needed for Part B** (Claude cannot reach the live site from its workspace): 1440 px — home header, category page header after scrolling; 768 px — header; 390 px — header closed, burger open (top, one category expanded), search open empty, search with results (e.g. «pokemon»), search with no results (e.g. «zzzz»), search results page for «pokemon». Optional for Part A: a recent real success page; the failure page renders directly at `https://boostershop.website/index.php?route=checkout/failure`.

## 3. Constraints for every part

- **Tokens** (`boostershop-ds.css :root`, live 2026-09-29): surfaces `--bs-bg #F7F7F5`, `--bs-paper #FFF`, lines `--bs-line #E5E7EB` / `--bs-line-2`; ink `--bs-ink #111827` … `--bs-ink-4`; `--bs-blue #1E3A8A`, `--bs-blue-soft #E8EEFB`; radius `--bs-r-sm 6px` / `--bs-r 10px` / `--bs-r-lg 14px`; shadows `--bs-sh-sm/md/pop`; z-scale `--bs-z-dropdown 100 … --bs-z-toast 500`; danger `--bs-danger`; warning `--bs-warning-bg/fg/line`. Use tokens, not new hex values.
- **Green = purchase actions only** (purchase, cart, checkout, success). White text on green must use `--bs-buy #12883E` / hover `--bs-buy-hover #15803D` (4.55:1 / 5.02:1, TECH-045 WP-E). Never white text on `--bs-green #16A34A` (3.29:1). Secondary/selected states are blue.
- **Font**: Manrope only (self-hosted, TECH-045). Do not reintroduce JetBrains Mono or IBM Plex.
- **Icons**: inline SVG. Font Awesome now loads non-blocking (TECH-045 WP-C), so do not depend on it for anything visible on first paint.
- **Breakpoints**: show 390 / 768 / 1440 for every mockup; mobile-first.
- **Copy**: brand is a curated TCG store selling original sealed product — no pull guarantees, no invented facts. Any new or changed copy is a proposal marked as such for owner approval.

## 4. Part A — RD-14 «Замовлення прийнято» (`checkout/success.twig`)

The page is already on the design system (R-11, 2026-05-29) and has since been extended by ST-2b2/2b3, CHECKOUT-007/007A, CHECKOUT-008, TECH-015 WP1 and TECH-045 WP-E. This is a polish/parity pass, not a rebuild. The original RD-14 notes («прибрати прилипання блоку до breadcrumbs», DS card, next steps/contacts) are partly met already — confirm against the page before redesigning them.

Blocks that must all remain, with their conditions:

1. Hero — check mark + H1 `Замовлення #<id> прийнято` + subtitle; without an order id the H1 is the language `heading_title`.
2. First15 notice (`show_first15_offer`) — «Дякуємо за реєстрацію!» + next-order 15% text.
3. Meta card — «Доставка» (method · display text), «Оплата» (method).
4. IBAN card (`is_iban_bank_transfer`) — fixed requisites text, «Скопіювати реквізити» button, polite live status line. Requisite values are fixed data: style only.
5. Items table — `N× name` + line total; totals rows; last row is the grand total. Long names must wrap cleanly.
6. Actions — «На головну» (secondary) always; «Переглянути замовлення» only for logged-in customers. Decide its colour explicitly (it is currently `.bs-btn-primary` = purchase green).
7. Footer message — one fiscal-receipt sentence chosen by payment type (Hutko / COD / other), the «Ми не телефонуємо…» sentence, Telegram link (`https://telegram.me/boostershop_tcg`), «Вдалого анпакінгу».
8. No-order fallback card — language `text_message` + «На головну».
9. Invisible: the GA4 purchase script. No UI, but it stays on the page.

The page uses emoji as icons (✓ as text, 🎴, 🙂, 🎁). Propose whether to keep them.

`TECH-047` will later add Google Customer Reviews opt-in to this page; its UI is Google's. Do not design it.

Mockups: (a) guest, Nova Poshta COD, 2 items; (b) logged-in, Hutko, with First15 notice; (c) IBAN bank transfer; (d) 6 items with long names; (e) no-order fallback.

## 5. Part A — RD-15 «Оплата не пройшла» (`checkout/failure.twig`)

Current: R-11b card — ⚠️, H1 «Помилка оплати!», language `text_message` (reasons list + link to the contact page), right-aligned «← На головну» outline button.

The page is rarely shown. By code, Hutko `confirm()` sends the customer here only when the session has no order id; `hutko.response` always redirects to `checkout/success`, whatever the payment result (runtime behaviour on a declined payment not verified — that is `CHECKOUT-012`). Design a calm, simple error page that matches Part A. No new flows.

- Available data: `heading_title`, `text_message` (HTML), `continue` (home URL), breadcrumbs.
- Allowed actions: «На головну», a static Telegram link (same URL as §4), the contact link already inside the text.
- **No «Повторити оплату» button.** The cart is cleared before the redirect to the gateway, so a cart/checkout link lands on an empty cart.
- Changing `text_message` wording is optional: a language-file edit, proposed separately for owner approval.

Mockup: one state at 390/768/1440.

## 6. Part B — UX-003 / UX-005 audit: header and navigation

History: header rebuilt in RD-02 (2026-05-31), burger categories CAT-002-5b (2026-06-28), mobile polish UX-036 (2026-09-03), fonts/icons/contrast TECH-045 (2026-09-28/29). No open issue list exists, so start with an audit.

Deliverable 1 — audit list, max ~10 items, ranked: issue · where (breakpoint/page) · severity (blocks purchase / friction / cosmetic) · proposed fix. Deliverable 2 — mockups only for the items the owner approves.

Cover: desktop header (burger, logo, search, account, cart trigger); the catalogue entry point on desktop; mobile header; burger panel (account row, orders, «Каталог» accordion, other links); sticky/scroll behaviour.

Facts to check against the screenshots:

- The burger (`.bs-burger`) appears to be the catalogue entry at every width — no desktop hide rule found in `boostershop-ds.css`.
- A stock OpenCart navbar (`common/menu.twig`, `#menu`, Bootstrap `bg-primary`, styled only by `stylesheet.css`) is rendered through `{{ menu }}` right after `<main>`. Whether it shows anything depends on DB category settings — not verified.
- Burger category links are hardcoded URLs. Changing the category set is a content decision, not design.

## 7. Part B — UX-009 search

Vendor mechanics (not changed): `ps_live_search.js` renders into `ul#ps-live-search.ps-live-search-list`:

- subheader «Результати за: …» (`.ps-live-search-subheader`);
- section headers «Товари / Категорії / Виробники / Інформація» (`.ps-live-search-header`);
- product rows `.ps-live-search-item` (thumb, `strong.name`, description, `.prices` with `.price-new` / `.price-old`);
- loading row (`.ps-live-search-item-loading`, Font Awesome spinner);
- «Нічого не знайдено» (`.ps-live-search-item-text`);
- «Усі результати» link (`.ps-live-search-more`).

The init call and the Ukrainian strings are Booster-owned code at the bottom of `header.twig` (input delay 150 ms, min 1 character), so strings may change. **There is no error state**: a failed request leaves the spinner. You may propose one; it would be Booster-owned JS, not module code.

- Mobile: the `bs-msearch` overlay — back button, clear button, scrim (`patch-mobile-search-menu-redesign.js`).
- Results page `product/search.twig`: stock OpenCart advanced-search form (Bootstrap), list/grid toggle, sort/limit, product cards (RD-04 — do not redesign cards), pagination, `bs-empty` no-results state (RD-06).

States to mock up:

- header search: idle, focus, typing;
- dropdown: loading, results (products + categories, a long name, a discounted price), no results, proposed error;
- mobile overlay: empty, with results, no results;
- results page: with results, no results.

## 8. Hooks the design must not remove

`#ps-live-search-input`, `data-live-search-target`, `#ps-live-search`, `.ps-live-search-container`, the search form action and hidden `route`/`language` inputs, `#input-search`, `#button-search`, `#input-category`, `#input-sub-category`, `#input-description`, `#button-list`, `#button-grid`, `#input-sort`, `#input-limit`, `#bs-menu-open`, `#bs-menu`, `[data-bs-menu-close]`, `[data-bs-accordion]`, `.bs-menu__subs`, `#bs-msearch`, `[data-bs-search-close]`, `[data-bs-search-clear]`, `#cart`, `#alert`, `[data-checkout008-copy-requisites]`, `[data-checkout008-copy-status]`.

## 9. Carried into the implementation handoff (not for Claude Design)

- `success.twig`: keep the `ga4_purchase_payload` script block after both branches, the First15 block, the CHECKOUT-008 hooks and the payment-type branches. The controller is not touched. QA: `bs-checkout-smoke` + one real order.
- Twig hazards from the RD-11/RD-12 incident (2026-09-22): no literal `{#` inside inline CSS/JS; numeric literals `0.3`, not `.3`. The runner must parse the candidate Twig before writing. `AGENTS.md` C4 covers `php -l` only.
- `header.twig` renders on every page, checkout included — a Twig error takes the whole storefront down. Package B needs a Twig-parse preflight, restore-on-fail and `bs-checkout-smoke`.
- Any `boostershop-ds.css` change updates its cache-bust reference.
- Re-pull live target files right before each runner is written; runners hash-guard their sources.

## 10. Out of scope

`CHECKOUT-012` (Hutko result handling) · credit waiting pages (`checkout/credit*.twig`, PAY-003) · mini-cart and toast (RD-12, done) · product cards (RD-04) · remaining TECH-045 work · TECH-047.
