# Handoff — RD-18: account orders (list + detail) in light variant A

Date: 2026-10-26
Executor: Codex · model=Terra · effort=high. The owner assigned Codex. Codex built ACC-007 (customer status presentation, history, pagination) and the RD-16 shell, so the read-only order contracts this restyle must keep are already in its context. Effort is high because the pages present order status and history, which are a risky zone by subject. The work stays read-only.
Notion: RD-18 https://app.notion.com/p/3706bf20bdb4813f895ad7953dfc9c36 (owner scope 2026-10-13). Dashboard: `RD-18`.
Sequence: start only after RD-17 is deployed and owner-QA-passed (`handoffs/handoff_RD-17_account-addresses_20261026.md`). The two tasks share `boostershop-ds.css`, its header cache token and the shell.

## 1. Task ID
`RD-18`.

## 2. Context
- **RD-16** is Done. It delivered the shell (`account/shell.php` + `shell.twig`, explicit per-page opt-in) and the `.bs-account-page` CSS prefix.
- **ACC-007** is Done (owner QA 2026-10-14). The order list and detail are already functionally correct:
  - a customer presentation map keyed by `(language_id, order_status_id)`, in `system/library/acc007_order_status.php`, with labels, groups and visibility;
  - hidden statuses are excluded from rows **and** counts;
  - real SQL pagination;
  - status history with recorded date **and** time, in deterministic order;
  - truthful fallbacks for missing IDs and timestamps;
  - an owner comment is shown only on a notified history row (`notify=1`), where it replaces the generic explanation; otherwise it is hidden.
- RD-18 Notion items 2 and 3 (statuses with change dates, the status audit, credit-order technical statuses) are therefore **already implemented**. RD-18 is a presentation task over them.

Design source:
- `design/account-light-A/`: `A-Orders`, `A-Order`, `A-Mobile-Orders`, `A-Mobile-Order`;
- the README there. Status names, colors and dates on the boards are illustrative; ACC-007 is the source.

There is no staging environment. Every deployed patch lands on production.

## 3. Goal
«Мої замовлення» and the order detail use the RD-16 shell and the approved light-A look:
- one card per order;
- a status timeline in the detail;
- clear item, total and delivery blocks.

Every visibility, ordering, pagination and comment rule from ACC-007 stays exactly as it is live.

## 4. What to change

### WP0 — fresh evidence (stop at G0)
- Request one owner-run narrow pull covering:
  - `catalog/controller/account/order.php`;
  - `catalog/view/template/account/order_list.twig`, `order_info.twig` (verify the names);
  - `system/library/acc007_order_status.php`;
  - `catalog/model/account/order.php`;
  - the Ukrainian `account/order` language file;
  - `shell.php` / `shell.twig`;
  - `boostershop-ds.css` and `common/header.twig`.

  Diff the pull against the ACC-007 and RD-16/RD-17 post-release hashes. Report any drift.
- Check for a theme DB override of the order templates.
- **Report these facts and decide at G0:**
  1. **List item summary.** The board shows the first product name plus «ще N товари» on each card. Verify whether the existing read-only model returns order products per order without a new query pattern.
     - If it needs one extra read per order on the page, state the bounded cost (page size × 1 read).
     - Otherwise drop the line and report.
  2. **«Показати ще».** The board shows a load-more button. Keep ACC-007 server pagination. Style the pager to match, or render «Показати ще» as a plain link to the next page. No AJAX, no infinite scroll, no change to page size or counts.
  3. **Timeline «next expected step».** The board greys out the next expected status («очікується»).
     - Render it only if the ACC-007 map already defines a status sequence per group from which the next step follows without new data.
     - Otherwise show only the recorded history and report.
  4. **«Замовити знову».** Verify whether a live reorder action exists on the order detail.
     - If yes, keep its exact route and behaviour; it is a purchase action, so DS purchase styling applies.
     - If not, do not add one.
  5. **Status badge colors (owner decision D2).** The board colors groups as: in progress gold, in transit blue, completed green, cancelled grey. `design/DECISIONS.md` reserves green for purchase actions, and the RD-16 hub already renders the latest-order badge in blue-soft.
     - Default unless the owner decides otherwise: gold / blue / neutral / grey, no green.
     - Hub, list and detail must use the same mapping.

### WP1 — orders list + order detail (one patch, or two if G0 shows independent deployability)
- **Shell:** opt both views into the RD-16 shell. Active item «Мої замовлення», mobile chip nav. Stop the legacy column-left menu on these pages only, at their callers.
- **List:** one card per visible order, with clear separation.
  - Card content: number, date, total, ACC-007 status badge, item summary (per G0 item 1).
  - Filled primary «Детальніше» button (blue, owner comment on the board).
  - Empty state when there are no visible orders.
  - Pager per G0 item 2.
- **Detail:**
  - «← Усі замовлення» back link.
  - Header: number, status badge, date.
  - «Статус замовлення» timeline from the ACC-007 history rows: date and time per step, current step highlighted, next step per G0 item 3. Notified-comment rules unchanged.
  - «Товари»: name, quantity × price, line total, from the stored order.
  - «Сума» / «Доставка» / «Разом» from the stored order totals exactly as today. No recalculation.
  - Delivery block: recipient name, phone and address from the stored order snapshot, including a stored patronymic when present. Never today's profile values.
  - Reorder per G0 item 4.
  - «Питання щодо замовлення? Напишіть нам» → `https://t.me/BoosterShop_Support_bot`, `target="_blank" rel="noopener"`.
  - Keep any other existing live action (for example a return link) unless the report shows it is broken, and report it.
- **CSS:** under `.bs-account-page`, existing DS tokens only. Follow AGENTS.md «UI/CSS patch discipline». Cache-bust per convention 8, with H1 (see RD-17 handoff).

## 5. Do not touch
- **Protected zones:** `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment (Hutko, monobank ПЧ, `pumb_credit`), Checkbox/fiscalization, Nova Poshta logic, Merchant feed, schema/JSON-LD.
- **Order status data:**
  - order status values, names and the processing/complete settings;
  - direct-SQL status writers;
  - `catalog/controller/mail/order.php`.
- **ACC-007 logic:** `system/library/acc007_order_status.php` — map, visibility, grouping, history ordering and comment rules. Presentation reads it; it does not change.
- **Order reads:** `catalog/model/account/order.php` and every order query and pagination predicate. No DB writes of any kind.
- **Stored orders:** no rewrite of historical orders or their snapshots.
- **Shared header:** `common/header.twig`, except the DS token per H1.
- **Shell:** `shell.php` / `shell.twig`, except an orders-active state if the existing contract cannot express it (report it).
- **Other pages:** addresses (RD-17), wishlist (ACC-006), login/register (RD-19/20), and no RD-29 work.

## 6. Likely files / areas
Likely, not confirmed. **The executor must verify against the actual pulled files.**
- `catalog/controller/account/order.php` (shell opt-in and presentation data only)
- `catalog/view/template/account/order_list.twig`, `order_info.twig`
- `extension/ukrainian/catalog/language/uk-ua/account/order.php` (new labels only)
- `catalog/view/stylesheet/boostershop-ds.css` and the header cache token

## 7. Acceptance criteria
- **`index.php?route=account/order` and an order detail at 390/768/1280:**
  - layout matches the boards;
  - exactly one nav surface;
  - «Мої замовлення» marked `aria-current="page"`.
- **Synthetic accounts:**
  - no orders → empty state;
  - a hidden-status order → absent from rows and counts, exactly as before;
  - a mixed set across pages → the same rows, the same order and the same counts as before the patch;
  - a credit-order technical status → shown only as its ACC-007 label or hidden;
  - a missing timestamp → the ACC-007 fallback text.
- **Detail:**
  - timeline steps match the ACC-007 history rows one-to-one, with date and time;
  - a notified comment replaces the generic text, and a non-notified comment never appears;
  - totals equal the stored order totals;
  - the delivery name equals the stored snapshot.
- **Badges:** one color mapping across hub, list and detail (D2).
- **AGENTS.md UI checks:**
  - three breakpoints;
  - long content (a long product name, 10+ items);
  - hover, focus and active states;
  - no unexplained `!important`, `setTimeout`, `position:absolute/fixed` or magic pixel values.
- **Platform and hygiene:**
  - PHP 8.0.30-compatible;
  - no mysqlnd-only APIs;
  - no new console errors;
  - hub, edit and address output unchanged except the shared CSS token;
  - Tier 1 smoke URLs return 200.
- **Report contents:**
  - exact patch SHA-256;
  - per-target before/after hashes or `absent`;
  - backup path;
  - stdout contract;
  - rollback facts;
  - the exact locally checked owner-run PowerShell packet and its SHA-256, reviewed together with the patch in one integrated independent Claude review.

## 8. QA / smoke test (owner, after deploy)
1. Hard-refresh. Open «Мої замовлення» on desktop and phone. Check that the menu appears once and that the orders and statuses match what you saw before the patch.
2. Open one delivered order and one in-progress order. Check that the timeline dates and times match the admin history, and that the totals and delivery data match the admin order card.
3. If you have an order with a notified comment, check that the comment shows. A non-notified comment must not show.
4. If there are two pages, go to the second page and back.
5. If reorder exists, press it. The products go to the cart. Do not check out.
6. Check the Tier 1 smoke URLs.

## 9. Rollback note
- Every patch:
  - verifies all targets and its backup before changing anything;
  - backs up to `_patch_backups/<patch>-<ts>/`;
  - records the per-target pre/post SHA-256, or `absent` for created files;
  - on rollback, restores existing files and removes only files it created, then clears the template cache.
- The DS cache token is restored from the header backup.
- No DB change, so no SQL rollback.

## 10. Recommended status after execution (Claude writes Notion)
- RD-18 is In progress from handoff until owner QA.
- Done after owner QA.
- Mirror the RD-18 row on the dashboard.

## Owner decisions
- **H1:** header DS cache-token exception (see the RD-17 handoff).
- **D2:** status badge colors. Default: no green.
- **G0 items 1–4** above.
