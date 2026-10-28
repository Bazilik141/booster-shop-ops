# Handoff — ACC-006 visual step: «Обране» page, heart button, naming

Date: 2026-10-28
Executor: Claude Code (cloud session) · model=Opus · thinking=high. Owner assignment 2026-10-28. Opus/high because the card and product templates are shared, high-traffic, carry GA4 injection anchors, and the executor does not share the earlier Codex context: the earlier packages are read from their reports.

## 0. Cloud execution contract (read first)
The executor runs in a cloud session on a clone of the GitHub repository. It has no access to the owner's PC, the cPanel backups or any server. That shapes the workflow:
- **Repository state.** Work from the clone of `master`. If this handoff, `design/acc006-favorites/` or a report named below is missing from the clone, stop and say which file is missing.
- **Live-source input.**
  - The executor lists the exact files it needs (WP0).
  - Claude chat composes the owner-run narrow pull command.
  - The owner runs it and **attaches the resulting archive to the cloud session**.
  - Extract it to a temporary directory outside the repository. Never commit raw live source or archives.
  - If the session cannot receive the archive, stop and tell the owner. Do not reconstruct live files from older reports.
- **Never:** SSH, SCP, server access, owner keys, Notion writes or deployment.
- **Git — owner exception for this task only (2026-10-28).**
  - This replaces the usual «Claude Code never commits or pushes» rule for this task only, because files in a cloud session reach the owner only through Git.
  - **Allowed:** commit and push only to the branch `claude/acc-006-visual`. Push only patch files (`patches/ACC-006-*`), owner packets, reports and sanitized evidence (`diagnostics/ACC-006_*`).
  - **Forbidden:** never push to `master`, never merge, never force-push, never open or merge a PR into `master`.
  - The owner fetches the branch to run the packet locally. Publishing to `master` stays an owner action.
- **Owner-run packet.** The executor prepares the owner-run PowerShell packet (upload, verify, dry-run/apply, cache clear, rollback) from its own verified facts, following `CODEX_WORKFLOW.md` → «Owner deployment». The packet must work in Windows PowerShell 5.1. If you cannot run it under 5.1 in the cloud, say exactly which checks you ran instead.
- **Independent review.** Claude Code does not approve its own patch. When a WP package is ready (patch + packet + report pushed), stop and give the owner the branch name, file paths and both SHA-256 values. Claude chat then performs the integrated CODE/composition review (`bs-patch-review`) of exactly those hashes. A changed hash needs a fresh review.
Notion: ACC-006 https://app.notion.com/p/3f86bf20bdb481cc9e95fb7751143503 (step 3: the visual step after the account rollout).

## 1. Task ID
`ACC-006`, visual step. The backend (restore, 2026-10-13) and GA4 `add_to_wishlist` (2026-10-15) are live and owner-QA-accepted.

## 2. Context
Today the wishlist has:
- a full-width secondary button «В улюблене» under «Купити» on every catalog tile;
- a text button «В улюбленому» in the product page purchase column;
- a heart with a counter in the header;
- the stock account page (`account/wishlist`), not yet on the RD-16 shell.

The owner chose the final look on 2026-10-28 in the Claude Design canvas «ACC-006 · Улюблене»:
- canvas: https://claude.ai/artifact/DdqHSf3Gy7gfTdrEaL5TPA;
- boards copied to `design/acc006-favorites/`; read its README first.

**Owner decisions (2026-10-28):**
1. **Catalog tile:** variant **A** on desktop and mobile — a round 44px heart button at the **bottom-right** of the image slot. It replaces the «В улюблене» button under «Купити».
2. **Product page:**
   - **desktop:** variant **A** — a 48×48 square heart button at the end of the quantity + «У кошик» row;
   - **mobile:** variant **C** — a round 44px heart on the main gallery photo, top-right.
3. **Name:** the block is called **«Обране»** everywhere (it replaces «Улюблене»).
4. **Account page heart:** the heart on «Обране» page cards sits at the **bottom-right** of the photo too, standardized with 1A.
5. **Heart color:**
   - added: filled `--bs-danger` (`#DC2626`);
   - not added: outline in `--bs-ink-2`.
   - The owner has not objected to the red default. The canvas has a heart-color tweak if the owner changes it.
   - Never green.

There is no staging environment. Every deployed patch lands on production.

## 3. Goal
One consistent heart control across tile, product page and account. «Обране» page in the light-A account shell. One name everywhere. Add/remove, guest pending, counters and the GA4 `add_to_wishlist` event behave exactly as they do live.

## 4. What to change

### WP0 — fresh evidence (stop at G0)
- **Narrow owner-run pull**: you list the paths; Claude chat composes the command; the owner attaches the archive (see section 0). Cover:
  - `catalog/view/template/product/thumb.twig`, `product/product.twig`;
  - `account/wishlist.twig`, `account/wishlist_list.twig`;
  - `common/header.twig`;
  - `catalog/view/javascript/booster-wishlist.js`;
  - `catalog/controller/account/wishlist.php`;
  - the Ukrainian `account/wishlist` language file;
  - RD-16 `account/shell.php` / `shell.twig` and the hub (`account/account.twig`) language keys that say «Улюблене»;
  - `boostershop-ds.css`;
  - the PS Enhanced Measurement replacement sources that touch `thumb.twig`, `product.twig` and `wishlist_list.twig`.
- **Diff** against the ACC-006, RD-16/17/18, RD-25 and UI-PCARD-BADGE post-release hashes. Report any drift.
- **GA4 anchor inventory.** List every GA4 injection anchor the PS module str_replaces in these templates (`select_item`, `add_to_cart`, wishlist list cart button, and others). Every anchor must survive byte-exact unless you show the module no longer uses it. The wishlist-list cart button was kept exactly for GA4 injection in ACC-006; keep it.
- **Name inventory.** List every customer-visible «Улюблене» / «улюблен…» string: language files, header aria/title, shell nav, hub card, page title/H1, breadcrumb, JS messages and aria-labels.
- **Report at G0:**
  - **Image-slot overlay.** RD-25 bottom-aligns cut images in an 84% × 94% inner box. Show where the bottom-right heart overlaps real product images (tile and «Обране» page) at 390/768/1280 with 3 real products. Propose the inset that keeps the heart readable without moving the RD-25 box.
  - **Mobile product heart.** Whether one button with responsive placement or two buttons (desktop row / mobile gallery) is cleaner. Two buttons must share state and never both be visible or both in the tab order.

### WP1 — naming, notices and «Обране» account page (one patch)
- **Rename** every customer-visible «Улюблене» to «Обране» per the inventory. Use correct Ukrainian case forms:
  - «Додати в обране»;
  - «В обраному»;
  - «Прибрати з обраного»;
  - «Додано в обране»;
  - «Прибрано з обраного».

  The route `account/wishlist`, file names, the DB table and GA4 event names stay unchanged.
- **Notices** (board `Naming`): added → «Додано в обране» with a «Переглянути» link to the page; removed → «Прибрано з обраного»; guest → «Збережено поки що на цьому пристрої» / «Увійдіть, і товар перейде у ваш кабінет.» with «Увійти» and «Зареєструватися».
  - Keep the live live-region, busy and duplicate guards.
  - Use the DS popover style (`bs-sh-pop`), not the green success toast.
  - If live guest behaviour differs from that text, keep the behaviour and report the wording mismatch. Never claim a behaviour that does not exist.
- **«Обране» page** (boards `Favorites`, `Favorites-Mobile`):
  - Opt into the RD-16 shell; active nav item «Обране» with its count.
  - Desktop: grid of white cards. Each card: photo, heart button bottom-right on the photo, name, price (sale and old price as live), and the live state action:
    - «Купити» (green);
    - «Передзамовити» (`--bs-blue-light`);
    - out of stock: disabled grey «Немає в наявності», photo dimmed `.45` and the heart **not** dimmed.
  - Mobile: list rows (80px photo, name, price, heart at the right of the row, action button full width).
  - Empty state per the board, with «До каталогу» (blue, not green).
  - Remove the stock horizontal-scroll table.
  - Keep the live add-to-cart button markup that GA4 injects into.

### WP2 — catalog tile heart (one patch)
- In `product/thumb.twig`:
  - remove the full-width «В улюблене» button;
  - add the round heart button inside the image slot at the bottom-right: 44×44, white, 1px `--bs-line` border, subtle `--bs-sh-sm`;
  - `type="button"`, outside the cart form;
  - `aria-label` and `aria-pressed` toggled by the existing JS.
- The badge corners at the top stay untouched (UI-PCARD-BADGE).
- Out-of-stock tiles keep the dimmed photo, with the heart outside the dimmed layer.
- Preorder, sale and Rare Pack states are unchanged.
- The same markup on every listing that uses `thumb.twig` (category, search, special, home modules). Report any listing that does not use it.

### WP3 — product page heart (one patch)
- **Desktop:** a 48×48 square heart after «У кошик» in the quantity row. Outline when not added; when added, a `--bs-danger` border and fill.
- **Mobile (≤768):** a 44px round heart on the main gallery photo, top-right. The desktop square is hidden on mobile.
- Remove the current text button.
- **Must not change:** the sticky mobile add-to-cart bar, credit/installment buttons, the variant selector, gallery behaviour and the purchase form.

### All WPs
- **CSS:** scoped DS classes, existing tokens only. Follow AGENTS.md «UI/CSS patch discipline».
- **Cache-bust:** convention 8. For the header DS/JS version tokens, ask the owner for the token-only exception at the ready package. H1 was granted for RD-17/18 only.
- **JS changes:** only selectors and texts in `booster-wishlist.js`. The request flow, guards, counter sync and GA4 call stay unchanged.

## 5. Do not touch
- **Protected zones:** `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment (Hutko, monobank ПЧ, `pumb_credit`), Checkbox/fiscalization, Nova Poshta, Merchant feed, schema/JSON-LD.
- **Wishlist backend:** `catalog/controller/account/wishlist.php` request/response contract, the stock wishlist model, guest-pending merge, and the `customer_wishlist` table. No DB writes.
- **GA4:** the PS Enhanced Measurement module and its settings, the `add_to_wishlist` payload/consent logic, and every GA4 anchor in the templates.
- **Product card layout:** RD-25 media slot geometry, badge positions, price/sale/preorder rendering, the «Купити» / «Передзамовити» / «Повідомити про наявність» buttons and the add-to-cart toast (RD-12).
- **Account shell and other pages:** the RD-16 shell logic beyond the nav label/count, and the RD-17/RD-18 pages.
- **No RD-29 work:** no dark theme.

## 6. Likely files / areas
Likely, not confirmed. **The executor must verify against the actual pulled files.**
- `catalog/view/template/product/thumb.twig`, `product/product.twig`
- `catalog/view/template/account/wishlist.twig`, `wishlist_list.twig`
- `catalog/view/javascript/booster-wishlist.js` (selectors and texts only)
- the Ukrainian `account/wishlist`, `account/account` and shell language files
- `common/header.twig` (aria/title text and the version tokens only)
- `catalog/view/stylesheet/boostershop-ds.css`

## 7. Acceptance criteria
- **Category page:**
  - at 390/768/1280: each tile shows one round heart at the bottom-right of the image, with no «В улюблене» button and an unchanged tile height elsewhere;
  - the heart never covers a top badge and stays readable over 3 real product images.
- **Product page:**
  - at 1280: the square heart sits after «У кошик»;
  - at 390: the round heart sits on the gallery photo, with exactly one visible heart and one in the tab order;
  - the sticky mobile bar is unchanged.
- **Behaviour** (logged in and guest), identical to before:
  - add, then remove;
  - repeated clicks give one request;
  - header and nav counters match;
  - a guest choice survives login.
- **GA4:**
  - exactly one `add_to_wishlist` per new add from tile, product page or mobile heart, and none on remove or repeat;
  - `add_to_cart` and `select_item` anchors and events still fire as before.
- **«Обране» page:**
  - layout per the boards, with in-stock, sale, preorder, out-of-stock and empty states;
  - the heart is bottom-right on desktop cards;
  - remove updates the list and counts without a reload error.
- **Naming:** no customer-visible «Улюблене» left anywhere listed in the G0 inventory.
- **Hygiene:**
  - AGENTS.md UI checks: three breakpoints, long names, hover/focus/active, and no unexplained `!important`, `setTimeout`, `position:absolute/fixed` (the heart overlay needs one, justified in the report) or magic pixel values;
  - PHP 8.0.30-compatible;
  - no new console errors;
  - Tier 1 smoke URLs return 200.
- **Report contents:** exact patch SHA-256, per-target before/after hashes, backup path, stdout contract, rollback facts and the owner-run packet SHA-256. These are pushed to `claude/acc-006-visual` and reviewed together by Claude chat per WP before the owner applies anything.

## 8. QA / smoke test (owner, after each deploy)
1. Hard-refresh a category on phone and desktop. Check there is one heart per tile, at the bottom-right of the photo, and that it does not hide the product.
2. Tap the heart. It turns red and you see «Додано в обране». The header counter grows by 1. Tap again: it is removed.
3. On a product page: on desktop the square is next to «У кошик»; on the phone the heart is on the photo. Add and remove a product.
4. Open «Обране» in the cabinet. Check the products, the states and the empty state after removing everything.
5. Log out, add a product as a guest, log in. The product appears in «Обране».
6. In GA4 Realtime, check that `add_to_wishlist` arrives once per new add.
7. Check the Tier 1 smoke URLs.

## 9. Rollback note
- Every patch:
  - verifies all targets and its backup before changing anything;
  - backs up to `_patch_backups/<patch>-<ts>/`;
  - records the per-target pre/post SHA-256, or `absent` for created files;
  - on rollback, restores existing files and removes only files it created, then clears the template cache.
- Header tokens are restored from the backup.
- No DB change, so no SQL rollback.
- Rollback order: WP3 → WP2 → WP1.

## 10. Recommended status after execution (Claude writes Notion)
- ACC-006 stays In progress until owner QA of the last WP.
- Then Done; the visual step completes the task.
- Claude chat mirrors the ACC-006 row on the dashboard. The cloud executor does not edit the dashboard.
