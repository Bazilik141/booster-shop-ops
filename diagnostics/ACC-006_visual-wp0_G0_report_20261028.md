# ACC-006 visual step — WP0 / G0 report

Date: 2026-10-28
Executor: Claude Code (cloud session). Handoff: `handoffs/handoff_ACC-006_favorites-visual_20261028.md`.
Status: **stopped at G0**. No patch, no packet. Nothing executed against production.

## 1. Evidence base

- Owner pull `ACC-006-visual-source-20261028-r1`: archive SHA-256 `c6851418ccd34e494282c712bbac40c6516f9236a75561b4f0def36c3ca9030e`, manifest `0a2064d259ae18a48e60a7aac821fdda931ad06f08093b6c729bcc89fbce561c`, inventory `ce49a830762b3adcaf53a7e98ace16f4d9d1b70d2f08fe8c5ac80be20c95db2a`. 23/23 files, collected 2026-10-28T15:46:51Z, PHP 8.0.30.
- Collector `diagnostics/ACC-006_wp0_collect_20261028.sh` (`d165e341…`) and packet `diagnostics/ACC-006_wp0_owner-pull_20261028.ps1` (`8257008b…`).
- The archive was extracted outside the repository and is not committed.
- No DB template overrides for any affected route: `oc_theme` count is 0.
- **Live site not reachable from this session.** The environment's network policy denies `boostershop.website`. Section 5 is therefore computed from live CSS plus recorded RD-25 measurements of real products. These are not live screenshots.

## 2. Drift against recorded post-release states

| File | Live = recorded state | Result |
|---|---|---|
| `product/thumb.twig` | ACC-006 restore `after` `72dec6ce…` | no drift |
| `account/wishlist.twig`, `account/wishlist_list.twig`, uk-ua `account/wishlist.php` | ACC-006 restore `after` | no drift |
| `booster-wishlist.js`, `controller/account/wishlist.php`, PS `controller/analytics/ps_enhanced_measurement.php` | ACC-006 GA4 `after` | no drift |
| `controller/common/header.php`, `model/account/wishlist.php`, `controller/product/{thumb,product}.php`, PS model | ACC-006/RD-25 recorded hashes | no drift |
| `common/header.twig`, `boostershop-ds.css` | RD-18 WP4 owner-accepted post state | expected (RD-16…18 after ACC-006); ACC-006 GA4 edit and UI-PCARD-BADGE rule (`ds.css:287`) present |
| `account/account.twig`, `controller/account/account.php` | RD-18 WP1 r3 accepted | expected |
| `account/shell.twig`, `controller/account/shell.php` | RD-16 created hashes | no drift |
| `product/product.twig` | **no recorded state** | **DRIFT, unrecorded.** See below |
| uk-ua `common/header.php`, `product/product.php`, `product/thumb.php` | no baseline recorded | read-only; no wishlist strings rendered from them |

**`product.twig` drift.** The ACC-006 post state `db2de45a…` was reconstructed byte-exactly: RD-25-WP2-FIX `after` plus the restore edit. Live `882eeb6d…` differs in exactly one line, `:1040`, in the JSON-LD:

```
-    "itemCondition": "https://schema.org/NewCondition",
+    "itemCondition": "https://schema.org/{% if '-BULK-' in (model|default('')|upper) %}UsedCondition{% else %}NewCondition{% endif %}",
```

- No patch, report or handoff in the repository records this change.
- It is in the protected schema zone. WP3 will treat `882eeb6d…` as its base and leave the line byte-identical.
- The owner should name the task that made it, so the repository record is complete.

## 3. GA4 anchor inventory

Source: live PS model `replaceCatalogView*Before()`. The search strings are applied by `str_replace` at render time. Live occurrence counts are shown in parentheses. Every anchor marked **keep** stays byte-exact in every WP.

| Template | Anchor | Live | Disposition |
|---|---|---|---|
| `product/thumb.twig` | `<a href="{{ href }}">` (title link, `select_item`/`select_promotion`) | 1 | keep |
| `product/thumb.twig` | `cart_add`/`add_to_cart` and `wishlist_add`/`add_to_wishlist` submit buttons | 0 | already unused; tile cart GA4 goes through the TECH-015 `data-tech015-ga4-submit` form observer, which is kept with its script |
| `product/product.twig` | `<button type="submit" id="button-cart"` | 2 (if/else, one rendered) | keep |
| `product/product.twig` | `if (json['success']) {` (`add_to_cart`) | 1 | keep |
| `product/product.twig` | `{{ footer }}` (`view_item`, `view_promotion`) | 1 | keep |
| `product/product.twig` | `wishlist_add`/`add_to_wishlist` formaction | 0 | already unused |
| `account/wishlist.twig` | `<div id="wishlist">{{ list }}</div>` (`view_item_list`) | 1 | keep |
| `account/wishlist_list.twig` | `<a href="{{ product.href }}">` | 2 | keep both |
| `account/wishlist_list.twig` | `<button type="submit" form="form-product-{{ product_row }}" class="btn btn-primary mb-1">{{ button_cart }}</button>` | 1 | keep exactly. Restyle «Купити» only through a scoped parent selector; classes stay `btn btn-primary mb-1` |
| `common/header.twig` | `</head>` | 1 | keep (WP3/WP2 touch only the version token, under the owner token exception) |
| `common/header.twig` | `<body>` | 0 | pre-existing (bare `<body>` absent), not this task |
| `account/account.twig` | `<h2>{{ text_my_account }}</h2>` | 0 | pre-existing since RD-16, not this task |

**`add_to_wishlist` does not use a template anchor.** It goes through the ACC-006 GA4 bridge: `booster-wishlist.js` `trackAddition()` reads `json.ps_add_to_wishlist` from `wishlist.add`. Every heart must therefore keep `data-bs-wishlist-add="{{ product_id }}"` as its trigger. Any number of hearts per page is safe, because the event fires only on `json.added === true` from the server.

## 4. Name inventory — customer-visible «Улюблене» / «улюблен…» / «Закладки»

| Location | Current | Visible where |
|---|---|---|
| `thumb.twig:94` | text «В улюблене», `aria-label` «Додати в улюблене: {{ name }}» | every tile |
| `product.twig:461` | the same pair, with `{{ heading_title }}` | product page |
| `header.twig:334` | `aria-label="Улюблене"` | header heart |
| uk-ua `account/wishlist.php:4` | `heading_title` «Улюблене»: page `<title>`, H1, breadcrumb | «Обране» page |
| `…/wishlist.php:9` | `text_wishlist` «Улюблене (%s)» | JSON `total`, not rendered by the JS |
| `…/wishlist.php:13` | `text_no_results` «В улюбленому поки немає товарів» | empty state |
| `…/wishlist.php:27,29` | «Товар додано в улюблене.» / «Товар видалено з улюбленого.» | notices |
| `…/wishlist.php:28` | `text_saved_guest` (no «улюблен», but replaced by the new guest notice) | guest notice |
| `…/wishlist.php:10-12` | stock `text_login`/`text_success`/`text_remove` («Закладки») | unused by the current flow; rename for consistency |
| uk-ua `account/account.php:34,45,55-57` | `rd16_wishlist` (shell nav + hub card title), `rd16_welcome`, `rd16_saved`, `rd16_favorites_empty`, `rd16_favorites_action` | hub, shell nav |
| uk-ua `account/address.php:46`, `edit.php:32`, `order.php:57` | presumably the same `rd16_wishlist` nav key | shell nav on those pages. **Not pulled; see section 8** |
| uk-ua `common/header.php` | `text_wishlist` «Закладки (%s)» | computed in `header.php:180`, not printed by `header.twig` |
| `booster-wishlist.js` | «Додано», «Збережено», «Увійти»; no «улюблен» | button and notice text |

Not covered by the r1 inventory:
- «Закладк…» strings outside the files already pulled;
- `extension/*/catalog/controller` (home modules that load `product/thumb`).

Both are in the r2 request below.

## 5. Image-slot overlay

**Handoff premise is stale.** RD-25 no longer bottom-aligns cuts in 84%×94%. Since `ACC-006_RD-25_HEADER_MEDIA_CENTERING_20261014` (`ds.css:272-276`), cut images are centred in an 84%×88% box: 8% left, 6% top and 6% bottom. Photos fill the slot (`contain`, centred). The slot is 1:1, and 4:5 at ≤767.98px.

**Tile sizes** are derived from live CSS and the RD-25-recorded `#product-list` classes (`row-cols-sm-2 row-cols-md-2 row-cols-lg-4`), assuming no left column on the category page. They are not measured live:

| Width | Tile |
|---|---|
| 390 | 176×220 |
| 768 | 364×364 |
| 1280 | 261×261 |

**Overlap** below means the 44px heart's overlap with the product's image box, from the RD-25 recorded real-product fits rescaled to the 88% box:

| Product | Inset | 390 | 768 | 1280 |
|---|---|---|---|---|
| OP-15 booster #143 (tall cut) | 8px | 12×39px corner, 2.6% | none | none |
| OP-17 booster box #123 (cut) | 8px | 22×39px, 3.9% | none | none |
| Outlet Mix #235 (wide cut) | 8px | none | none | none |
| Album 360 white #103 (photo) | 8px | 44×30px, 4.3% | 44×44, 1.5% | 44×44, 2.8% |
| same set | 12px | 3.7% / 5.0% / 0 / 4.8% | photo only 1.5% | photo only 2.8% |

**Proposal: inset `var(--bs-s2)` (8px) from the right and bottom edges of the media slot, at every width.**
- Cut products are touched only at 390, at the pack's bottom-right corner.
- Photos are always touched at their bottom-right corner. Readability then comes from the specified white fill, the 1px `--bs-line` border and `--bs-sh-sm`, not from avoiding the image.
- The RD-25 box is not moved.
- The top badge corners (`top:4px`) are 120+px away at every width.

**Markup consequence.** `.bs-pcard__media` is an `<a tabindex="-1" aria-hidden="true">`, so the heart cannot live inside it: a button inside a link is invalid HTML and `aria-hidden` would hide it from assistive technology. WP2 needs a positioned wrapper (`.bs-pcard__media-wrap`) around the existing `<a>` plus the heart as its sibling. The `.bs-pcard__media` rules stay untouched. The out-of-stock dim (`.bs-pcard--out .bs-pcard__media img`) does not reach the heart. The `position:absolute` on the heart is the one justified absolute rule.

**«Обране» page.**
- Its list uses the stock wishlist resize (`config_image_wishlist_*`, OpenCart default 47×47). It has no RD-25 cut/photo classification.
- A card photo per the board needs a larger image. That is a presentation-only change in `wishlist.php getList()` image sizing, and it is part of decision D1.
- The overlap on that page can only be checked live, logged in. It goes to owner QA, or to a live check once network access exists.

## 6. Mobile product heart: two buttons, CSS-switched

One button cannot sit both in the buy row (inside `.bs-buy-row`, in the purchase column) and on the gallery photo (`.bs-product__main-img`, a different grid column) without DOM moves by script.

**Recommended:** two `type="button"` hearts with the same `data-bs-wishlist-add="{{ product_id }}"`.
- **Desktop:** a 48×48 square after `#button-cart` inside `.bs-buy-row`.
  - It goes after the `{% if _is_preorder %}…{% endif %}` block, so the `id="button-cart"` anchors are untouched.
  - It stays outside the `<form>` submit path through `type="button"`.
- **Mobile:** a 44px round heart as a sibling of the main `<a data-bs-gallery-main>` inside `.bs-product__main-img`, positioned top-right.
  - Magnific Popup is bound only to that `<a>` (`product.twig:997`), so a click on the heart does not open the popup.
  - The RD-25 thumbnail switcher is not affected.
- **Switch:** `display:none` for the hidden variant. That removes it from the tab order and the accessibility tree, so exactly one heart is visible and focusable.
  - Breakpoint: **≤767.98px**, the product layout's own single-column switch (`ds.css:336`).
  - The handoff says ≤768. At 768 the page is still two-column, so the desktop square belongs there. See D3.
- **Shared state:**
  - Requests are already de-duplicated per product ID (`pending[id]`).
  - Visual and `aria-pressed` state must be applied to every `[data-bs-wishlist-add="<id>"]`, not only to the clicked button.
- The sticky ATC bar, credit/installment blocks, the variant selector and the purchase form are not touched.

## 7. Blocking finding: the live JS is add-only

The handoff and acceptance criteria assume a toggle: «`aria-pressed` toggled by the existing JS», «Tap again: it is removed». **Live behaviour does not support this.**
- `booster-wishlist.js` sends `add` from tile and product buttons and then only changes the button text to «Додано» / «Збережено». It has no `aria-pressed`, no remove path from tiles or the product page, and no initial state.
- No template or controller renders whether a product is already in the wishlist. `wishlist.state` returns only the count.
- `wishlist.remove()` rejects guests (`error_auth`). A guest's pending choice cannot be removed at all.

A heart that shows the true state and removes on a second tap therefore needs changes outside «JS: selectors and texts only» and «wishlist controller contract unchanged». See D1.

## 8. Missing for WP1, supplementary pull r2 (read-only)

Files:
```
extension/ukrainian/catalog/language/uk-ua/account/address.php
extension/ukrainian/catalog/language/uk-ua/account/edit.php
extension/ukrainian/catalog/language/uk-ua/account/order.php
catalog/controller/account/order.php
catalog/view/template/account/order_list.twig
catalog/view/template/product/category.twig
```
- The three language files carry the shell nav label.
- `order.php` and `order_list.twig` are the reference for opting a page into the RD-16 shell in page mode.
- `category.twig` confirms the grid columns and any left column for the tile sizes.

Inventories (paths, line numbers and matched word only, as in r1):
1. `grep -rno -e 'закладк' -e 'Закладк'` over `catalog extension/ukrainian extension/opencart/catalog/view/template`;
2. `grep -rl 'product/thumb'` over `extension/*/catalog/controller`.

For the real-image overlay check, either:
- the owner allows `boostershop.website` in this cloud environment's network settings; I then measure 3 real products at 390/768/1280 with headless Chromium, read-only public pages; or
- the owner sends three category screenshots at those widths.

## 9. Owner decisions needed at G0

- **D1 — heart behaviour.**
  - **A (recommended): a true toggle.** It needs a narrow owner exception:
    1. `header.php` / `header.twig` emit the visible wishlist product IDs once, read-only, on the existing `data-bs-wishlist-config` element;
    2. `booster-wishlist.js` marks hearts on load, toggles add/remove, sets `aria-pressed`/`aria-label`, and syncs all hearts with the same ID. The add request, the guards, the counter sync and `trackAddition()` stay unchanged;
    3. `wishlist.php remove()` also accepts a guest and drops only that session-pending ID: no DB write, still token-checked;
    4. `getList()` image size for the «Обране» card.

    GA4: exactly one event per new add, none on remove. Independent review covers these files.
  - **B: add-only heart within the current limits.** The heart fills after an add on this page view only. After a reload it shows empty even for a saved product, and a second tap does nothing. Handoff QA step 2 «tap again: it is removed» cannot pass.
- **D2 — guest notice wording.** The board's text promises things live code does not do:
  - «Зареєструватися»: the item does **not** move after registration. Only an ordinary login merges, per the ACC-006 restore report.
  - «на цьому пристрої»: the choice is held in the server session for this browser until the session expires.
  - Proposal: «Збережено в цьому браузері до входу» / «Увійдіть, і товар перейде у ваш кабінет.» with «Увійти» only.
  - Keeping «Зареєструватися» requires a registration merge, which is backend work outside this task.
- **D3 — product heart breakpoint.** ≤767.98px, matching the product layout (recommended), or the handoff's ≤768.
- Header version token: the per-package owner token-only exception will be requested at each ready package. Nothing is requested now.

## 10. Smaller corrections to the handoff
- «DS popover style (`bs-sh-pop`)»: no `.bs-sh-pop` class exists. `--bs-sh-pop` is a shadow token (`ds.css:71`), used by `.bs-toast` and the mini-cart.
  - The notice will reuse the token in a scoped `.bs-wishlist-notice` popover.
  - Today the notice is a plain `role=status` container inside `<main>` (`header.twig:652`). Making it float needs one justified `position:fixed`.
- The «Обране» page is currently **not** on the RD-16 shell: `wishlist.twig` still renders `column_left`. Opting in edits only `wishlist.php index()` rendering and `wishlist.twig`.
- Nav count: the shell has no count support. Adding «Обране · N» changes `shell.php`/`shell.twig`, within the handoff's «nav label/count» allowance.
