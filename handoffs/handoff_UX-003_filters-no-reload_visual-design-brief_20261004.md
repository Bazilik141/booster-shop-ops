# Design Brief — UX-003 runner 9: category filters without page reload + active-filter chips

Date: 2026-10-04 | For: Claude Design | Implementation after owner approval: Claude Code (runner 9) | Review: Claude (chat)
Parent: `handoffs/handoff_RD-14-15_UX-003-005-009_INDEX_claude-code_20261004.md` (sections "Deploy log runners 3b–7 and runner 8")

## 1. Why

Live since 2026-10-04 (runner 7): the category page has design **C · фінал** — one row with subcategory pills, a «Фільтр» button (count badge) and the sort control; «Фільтр» opens a panel with collapsible checkbox groups and «Скинути». Applying a filter is still stock OpenCart: **every checkbox tick reloads the whole page** and the panel comes back closed. Active filters are visible only as the number on «Фільтр» (the UX-004 chips never rendered).

Owner decision (2026-10-04): the reload-per-tick flow is unacceptable. Runner 9 will filter **without a page reload** and show **active-filter chips**. Technically the page fetches the same filtered category URL in the background and swaps only the product list, pagination and counts; the address bar updates (shareable link, Back works); on any error it falls back to a normal reload. Server, URLs, canonical and SEO do not change.

## 2. Owner decisions that bind this design

- No horizontal-swipe rows anywhere in this area. Below 992 px the header card is **two rows** (runner 8, being built now): row 1 — subcategory pills wrapping onto as many lines as needed; row 2 — «Фільтр» and sort side by side, equal width, with text labels. Design on top of that layout.
- The list/grid view toggle stays hidden (earlier owner decision). Do not bring it back.
- Mobile-first: design 390 first, then 768, then 1440.

## 3. States to design (390 / 768 / 1440)

1. **Active-filter chips.** Where the chips row sits (relative to the header card, the panel and the grid); chip style; «×» removes one value; «Скинути все» when 2+ values are active; chips wrap, never scroll sideways. Chips must stay visible when the panel is closed.
2. **Selecting several values.** The panel stays open while the customer ticks. Mobile: show how the customer gets back to the products (for example a sticky bottom action «Показати N товарів» that closes the panel and scrolls to the grid). Desktop: show whether the grid updates live while the panel is open and how that is communicated.
3. **Loading.** What the grid looks like while results update (skeleton, dimmed grid, or a small progress indicator). No layout jump; the header card and panel do not move.
4. **No results.** Copy and action for an empty filtered result (e.g. «Нічого не знайдено з цими фільтрами» + «Скинути фільтри»).
5. **Counts.** How the total product count in the header card, the badge on «Фільтр» and the per-group counters change after each tick.
6. **With sort and pagination.** Show one frame with an active filter + changed sort, and the bottom of the grid with pagination / «Показати ще».
7. **Keyboard and screen reader.** Focus order after a tick and after «×» on a chip; what is announced (polite live region with the result count).

Error/fallback (network error → normal page reload) needs no visual unless you propose one.

## 3a. Same round: mobile cart badge (BUG-004 subtask A)

Owner decision 2026-10-06: delivered in the same round as runner 9, as a separate runner file.
- Live state: on phones the header cart button is an icon only and shows no item count, even with items in the cart (owner check 2026-10-06). Your header audit (item 10) flagged the same.
- Design the badge on the mobile cart button (390 and 768 px): position, size, colours (the button itself is the green purchase button), 1–2 digits and «9+» or similar, and the empty-cart state (no badge). Keep the 44 px touch target. The count must also be announced (accessible name «Кошик: N товарів» with correct plurals).
- The swipe-to-close on the open mini-cart already works; do not redesign the drawer.

## 4. Constraints

- Tokens and components from `boostershop-ds.css` (`:root`): surfaces, `--bs-line*`, `--bs-ink*`, `--bs-blue` / `--bs-blue-soft`, radii `--bs-r-sm/--bs-r/--bs-r-lg`. Selected/secondary states are blue.
- **Green is for purchase actions only** (Купити, cart, checkout). Chips, «Фільтр», «Показати N товарів» and «Скинути» are not green.
- Touch targets ≥ 44 px on mobile. Natural Ukrainian copy with correct plurals (1 товар / 2 товари / 5 товарів).
- Use real category, filter and product names from the screenshots; do not invent prices, counts or product data.
- Keep the existing filter groups and checkbox semantics (stock OpenCart filter module); this design changes presentation and flow, not which filters exist.

## 5. Evidence

- Approved design source: `handoffs/design_20261004_rd14-15_ux003-005-009/` → `UX-003 UX-005 UX-009 - етап 3.html` → «Фільтр категорії» → «C · фінал».
- Tokens: `live-snapshots/20261004_rd-ux-batch-live2/catalog/view/stylesheet/boostershop-ds.css` (tokens unchanged by runners 1–7).
- Owner screenshots of the live category page after runner 7 (Pokémon): 1440, 1200, 1000, 900 and 400 px, panel closed; plus panel open and one filter ticked at 390 and 1440 (owner attaches).

## 6. Deliverables

1. One HTML mockup page with the states above at 390 / 768 / 1440, annotated.
2. A short implementation note for Claude Code: states, breakpoints, exact Ukrainian copy (incl. plural forms and aria-labels such as «Прибрати фільтр «Бустер»»), what is announced to screen readers.
3. Files go to `handoffs/design_<YYYYMMDD>_ux003-filters/`. The owner approves before any implementation.
