# CAT-004 / RD-11 — cart and product UI follow-up

Date: 2026-09-24

Follow-up correction: `RD-11_cart-preorder-quantity_20260924.php` supersedes the cart label condition in this report. The final condition is inventory below 1 **and** stock status ID 8. See `diagnostics/RD-PP-META_and_ui-corrections_report_20260924.md`.

## Scope and source

- Owner's `BS_cart_product_ui_20260924.tar.gz`, SHA-256 `6D867B3EF98E41FF65C0EFBE727BFB1F0046FF3223E16064936992D2D9077968`.
- The four requested changes are split into two independent runners:
  - `patches/RD-11_cart-preorder-label_20260924.php`
  - `patches/CAT-004_preorder-price-credit-layout_20260924.php`
- Existing pending `CAT-004_variant-cosmetics_20260923.php` and `CAT-004_info-column-reflow_20260923.php` were not edited. No commit, push, deployment, database change, price calculation change, or payment eligibility change occurred.

## Root cause and implementation

1. `cart_list.twig:35` labels every false `product.stock` as `немає в наявності`. The cart rows carry `stock_status_id` through `system/library/cart/cart.php` → `catalog/model/checkout/cart.php` → controller, and cart `hasStock()` already treats ID 8 as preorder. The new template branch prints `передзамовлення` for ID 8; other unavailable items retain their old label. The stock warning banner is unchanged.
2. `product.twig:159` excludes preorder from the quantity discount row. The runner removes only that exclusion. For the owner-supplied EB-03 example, the intended display is ₴230 per unit and ₴200 from 3 units. The controller already passes active `special` and `discounts`, and `product.twig:150` already renders an active special alongside the base price. These rules remain intact; there was no supplied preorder product with a currently active unit special to verify against live data.
3. Both product-page credit JS branches contained the old `Оплата частинами доступна лише для товарів у наявності.` hint; both now use the exact requested `Сплата частинами доступна лише для товарів у наявності.`
4. `boostershop-ds.css:5632-5635` stacks the bank rows by default and gives the second a top border. The runner edits those source rules to two equal grid columns with a vertical divider. A single visible provider spans the row. A scoped rule at 480px and below keeps two cards in one row with smaller logos and wrapping text. Override history: `PAY-001_phase2_credit_ui_20260721.php` introduced the CSS; `PAY-002_pumb-product-page-card_20260831.php` changed the product markup/JS. No new `!important`, `setTimeout`, or absolute/fixed positioning was introduced.

## Local validation

- Both runner files passed `php -l` under local PHP 8.3.30; runner syntax uses PHP 8.0-compatible constructs for the production target.
- Ran all four pending/new runners against copied owner-export files, first with the new runners before the 2026-09-23 CAT-004 pair, then in reverse order. All runs ended `done=ok`.
- Re-running each new runner on the transformed copy returned `already_applied=yes`.
- Confirmed the final Twig retains the base/special price branch, includes `{% if discounts %}`, and has two instances of the corrected hint. Confirmed the cart template retains the ordinary unavailable branch.
- Final mobile CSS selector was made more specific than the later default logo/text rules, so the 24px / 12px mobile values are not overridden in the cascade.
- Browser-based local fixture inspection was not completed: headless Chrome and Edge exited 13, and the browser tool explicitly blocked the local `file://` URL. No bypass was attempted. Visual results at 320/390/768/1280px and live price data therefore remain owner QA gates.

## Rollback and run order

Each runner creates `_patch_backups/<patch>-<timestamp>/` before writing and self-deletes after success. Restore the files from the logged backup paths and clear the OpenCart template cache if rollback is needed. The runners modify only cart Twig or product Twig/CSS/header respectively. The new product runner reads and replaces the CSS cache token without assuming its current value; all four runners worked in either order in the local copied source.

After review, upload all four scripts to `~/public_html` and run each once. Use `DIR_CACHE` from `config.php` for cache cleanup after the last successful runner.

## Owner manual QA

- EB-03: base price ₴230 and discount `від 3 шт — ₴200 за штуку`; cart item says `передзамовлення`.
- Any non-preorder unavailable item still says `немає в наявності` in the cart.
- A preorder item with an active unit special, if configured, shows the crossed base price and active special; quantity tier remains visible when configured.
- Product payment cards: both banks in one row at desktop, tablet, and narrow mobile widths; long names wrap without clipping; one-bank state fills the available width; muted preorder state remains readable; button focus/hover/disabled behavior is unchanged.
- Run the project Tier 1 smoke URLs after deployment, especially product, cart, and checkout entry.
