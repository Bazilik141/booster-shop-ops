# RD-PP-META and cart/product UI corrections

Date: 2026-09-24

## Scope and source

- Owner-requested corrections from three screenshots plus the owner-supplied `CODEX - RD-PP-META_status-card_20260924.html` handoff and its `RD-PP-meta - статус, виробник, відгуки.html` design reference.
- Code source: `BS_cart_product_ui_20260924.tar.gz` transformed locally by the four preceding CAT-004/RD-11 runners. The screenshots show those preceding changes, but no fresh post-deployment archive or live QA was provided. Every new runner has exact anchors and stops before writing on source drift.
- New independent runners: `patches/RD-11_cart-preorder-quantity_20260924.php`, `patches/CAT-004_trust-credit-copy_20260924.php`, `patches/RD-PP-META_status-card_20260924.php`.
- No production deployment, commit/push, DB change, Notion/status change, stock policy change, payment eligibility change, price calculation change, schema/SEO change, or checkout payment change.

## Findings and implementation

1. The previous cart Twig condition used stock status ID 8 alone, so a positive-stock product that retained ID 8 displayed `передзамовлення`. `system/library/cart/cart.php` provides the actual product inventory as `$product['stock']`, but `catalog/controller/checkout/cart.php` replaces `stock` with a display boolean. The correction exposes a display-only `bs_preorder_label` when inventory is below 1 **and** status ID is 8. Other unavailable lines keep `немає в наявності`. The cart stock warning and checkout gate are untouched.
2. Product Twig has two JS render branches: the preorder hint had been corrected, but their minimum-total hints still said `Оплата частинами доступна від`. Both now say `Сплата частинами доступна від` and retain the same calculated amounts.
3. The shared `.bs-trust-strip` appears in the old `cat002_5c_mobile_visual_breadcrumb_20260630.php` patch and on the home page. The existing product-only mobile rule aligns all icons with the first line; the preorder truck label wraps to two lines. The correction marks only the product's truck item and vertically centers that item and its text at the mobile breakpoint. The shared/global trust strip and other product items are untouched.
4. The status-card runner replaces the product's old review/manufacturer/status/ETA block with design variant B: review/manufacturer row and exactly three status cards (`in`, `pre`, `out`). It uses existing `_is_preorder`, `_is_out`, `stock`, `manufacturer`, `manufacturers`, and review conditions. Any positive numeric stock, including 1–5, gets the same green state with its exact quantity. If the in-stock quantity is unavailable as a number, the subtitle is omitted. ETA remains the existing `3–4 тижні`; there is no separate delivery row.
5. The review link keeps its original `href`, entire `onclick`, and `bs-pp-reviews__olx` class; its inner icon/text markup follows the design. The existing ratings branch remains. Because `.bs-pp-reviews` already names the ratings row, new pill styles are scoped to the review anchor. The handoff's colors and dimensions are retained. Product column's existing 13px flex gap is included when calculating visible title/head, head/card, and card/price spacing. Old `.bs-pp-meta` CSS is left in place because a complete theme usage scan was not available; the new markup does not use it.

## Local validation

- All three runners passed `php -l` on local PHP 8.3.30; production PHP target is 8.0 and the runner syntax is compatible.
- Applied the three runners to a copy of the transformed owner archive in two orders (status card before and after trust/copy). All ended `done=ok`; re-runs returned `already_applied=yes`. The cart controller passed its post-write `php -l` gate.
- Parsed the resulting `product.twig` with local Twig 3.28.0. Focused rendering checks passed for: in stock with 18, preorder ETA, out of stock, missing manufacturer/review, missing numeric quantity, existing ratings branch, preserved review onclick, and the cart label's three visible states.
- Checked final CSS has balanced braces and that new runner code introduces no `!important`, `setTimeout`, or absolute/fixed positioning.
- Browser visual QA at 320/390/768/1280px and live OpenCart/theme override QA remain outstanding. In the previous round, headless browsers exited 13 and the browser tool blocked the local `file://` fixture. No bypass was attempted.

## Run order, rollback, and owner QA

If the four preceding scripts are already on production, upload and run only the three new scripts. Otherwise run the four preceding scripts first. `RD-11_cart-preorder-quantity_20260924.php` requires the earlier `RD-11_cart-preorder-label_20260924.php`; the trust/copy runner requires `CAT-004_info-column-reflow_20260923.php` and `CAT-004_preorder-price-credit-layout_20260924.php`. The status-card runner requires the info-column reflow. Each new runner backs up its targets under `_patch_backups/<patch>-<timestamp>/` and self-deletes after success. Restore the logged files and clear the OpenCart template cache for rollback.

Owner QA after deployment:

- Cart: EB-03 with zero inventory/status 8 shows `передзамовлення`; Black Bolt with positive inventory does not; a genuinely unavailable non-preorder item still shows `немає в наявності`.
- Product payment hint: below ₴500 uses `Сплата частинами доступна від …`, with unchanged amount; preorder uses `Сплата частинами доступна лише для товарів у наявності.`
- Preorder trust strip: `Привеземо під замовлення` and icon are centered on narrow mobile; other trust items and the home-page strip remain unchanged.
- Product meta: OP-16/OP-14/OP-10 represent the three status cards at desktop/tablet/mobile widths; a quantity of 1–5 remains green with its number; long title/manufacturer text wraps; review tab link and manufacturer link still work; no console errors or change to Product schema availability.
- Run the normal Tier 1 smoke URLs after deployment, especially product, cart, and checkout entry.
