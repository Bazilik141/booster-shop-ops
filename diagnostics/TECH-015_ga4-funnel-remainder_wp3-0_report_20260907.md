# Codex Report — TECH-015 WP3-0: current GA4 funnel anchor inventory

Date: 2026-09-07

## Outcome

WP3-0 is complete against the fresh owner-provided cPanel backup
`backup-9.7.2026_20-35-02_boosters.tar.gz` (archive timestamp 2026-09-07
20:35; local size 523,007,595 bytes). The archive is readable and contains the
current post-UI-FIX, post-WP1 and post-WP2 storefront source.

No WP3 package can be dropped:

- WP3a shipping needs no code patch because its vendor anchor is present;
  `Track Add Shipping Info` remains an owner-side toggle.
- WP3a payment does need a patch because the rewritten payment template has no
  vendor success anchor. The vendor response key is verified as
  `ps_add_payment_info`.
- WP3b does need a patch because the catalogue tile buy-button anchor is gone,
  while category/search/special payload injection remains intact.
- WP3c does need a patch because the mini-cart remove-button anchor is gone,
  while the dropdown anchor that injects the mini-cart dataset remains intact.

No production mutation, database read, deploy, Notion write, commit or push was
performed during WP3-0.

## Source and platform gates

- OpenCart version from fresh `public_html/index.php`: `4.1.0.3`.
- Therefore the `>= 4.1.0.0` `product/thumb.twig` branch applies.
- The `<= 4.1.0.0` `checkout/cart_list.twig` branch does not apply; the
  `>= 4.1.0.1` `edit` / `product.remove` anchors apply.
- Vendor model SHA-256:
  `eea58d1e247a16c2cc4bc1e61b8a5028e41dc7ed557e9c652d74382f7984b4b4`.
- Vendor controller SHA-256:
  `7273ac3733c3233cc52c86f07aa2a4714adb575d570e901808c61d3498eff00e`.

Only the minimum named files were extracted under
`work/tech015-wp3-live-20260907-203502/`. The database dump was not extracted or
read because the anchor inventory does not require customer or secret-bearing
data.

## Current anchor inventory

Counts are literal counts in the fresh Twig source. `Present` means the vendor
`str_replace` can run; it does not by itself prove that the relevant module
toggle is enabled.

| Template | Active vendor anchor | Count | State / effect |
|---|---|---:|---|
| `common/header.twig` | `<body>` | 0 | Gone; affects only GTM noscript, not the current gtag implementation. |
| `common/header.twig` | `</head>` | 1 | Present; base `ps_dataLayer` and optional login injection remain possible. |
| `product/thumb.twig` | `<a href="{{ href }}">` | 1 | Present; name-link `select_item` injection still works. |
| `product/thumb.twig` | OC 4.1 cart button with `formaction="{{ cart_add }}"` plus stock tooltip attributes | 0 | Gone; catalogue-tile `add_to_cart` remains dead. |
| `product/thumb.twig` | OC 4.1 wishlist button with `formaction="{{ wishlist_add }}"` plus stock tooltip attributes | 0 | Gone; setting is currently out of WP3 scope. |
| `product/category.twig` | `{% if products %}` | 1 | Present; injects `ps_merge_items` and `view_item_list`. |
| `product/search.twig` | `{% if products %}` | 1 | Present; injects `ps_merge_items`, search and `view_item_list`. |
| `product/special.twig` | `{% if products %}` | 1 | Present; injects `ps_merge_items` and `view_item_list`. |
| `product/product.twig` | `<button type="submit" id="button-cart"` | 2 | Present; product-page item metadata injection remains possible. |
| `product/product.twig` | OC 4.1 wishlist button with `formaction="{{ wishlist_add }}"` | 0 | Gone; setting is currently out of WP3 scope. |
| `product/product.twig` | `if (json['success']) {` | 1 | Present; product-page `add_to_cart` still works. |
| `product/product.twig` | `{{ footer }}` | 1 | Present; product-page dataset injection remains possible. |
| `common/cart.twig` | `<a href="{{ product.href }}">` | 1 | Present for the first exact product link anchor. |
| `common/cart.twig` | stock submit remove button anchor | 0 | Gone; mini-cart `remove_from_cart` remains dead. |
| `common/cart.twig` | `<button type="button" data-bs-toggle="dropdown"` | 1 | Present; mini-cart `ps_merge_items` dataset is injected. |
| `checkout/cart.twig` | `<div id="shopping-cart">{{ list }}</div>` | 1 | Present; `view_cart` injection remains possible. |
| `checkout/cart_list.twig` | `<a href="{{ product.href }}">` | 2 | Present. |
| `checkout/cart_list.twig` | `<input type="text" name="quantity"` | 1 | Present. |
| `checkout/cart_list.twig` | OC 4.1.0.1+ `<button type="submit" formaction="{{ edit }}"` | 1 | Present. |
| `checkout/cart_list.twig` | OC 4.1.0.1+ `<a href="{{ product.remove }}"` | 1 | Present. |
| `checkout/cart_list.twig` | `<table class="table table-bordered">` | 1 | Present; cart-list dataset injection remains possible. |
| `checkout/payment_method.twig` | `if (json['success']) {` | 0 | Gone; vendor cannot emit `add_payment_info`. |
| `checkout/shipping_method.twig` | `if (json['success']) {` | 1 | Present; vendor can emit `add_shipping_info` once enabled. |

## SHA-256 source gates for later runners

```text
catalog/view/template/common/header.twig
c9be473c6e89241d7a373f270d7c13d64861d499ca70153b5a680248631abe1c

catalog/view/template/product/thumb.twig
0375e269381f19e85573e3702d81dd118bf0b8b4e562ea60f9c9a30d3ccea807

catalog/view/template/product/category.twig
f757bdbd5bb611e67787d6bbaab943635fdc8bec4f7fd5dfa9ab7586ba62ecea

catalog/view/template/product/search.twig
04d2bad20afe6f1f5f72b774fc5a3fdd45f3b8c997a5784a67c27708c2732cb5

catalog/view/template/product/special.twig
eb7d1aa4860d2df2e4b27907d0db7f9997f0cbaf8e5b37ad3865ac1a0b841e53

catalog/view/template/product/product.twig
7ce002fe14a65b3d0a533eefce4eebdc402d42dd55ef83bbabf44b65dad5d9ae

catalog/view/template/common/cart.twig
9b2a8c326604c64ddfe29b8e289712690474cfc2dea59f1de931947d78aa1fa1

catalog/view/template/checkout/cart.twig
8a5817f0073ffa04f221e6b550471f5a295f30acacc472e7757b6894e7339a52

catalog/view/template/checkout/cart_list.twig
0c264b8676e8d7e8f97e59cab9d02b204005d1b1cbf4d3c8659a1df1d160fbec

catalog/view/template/checkout/payment_method.twig
efee1a60c2cecc7547787646690cc00fc01a428f9a2e9a64d9f08d464db6d396

catalog/view/template/checkout/shipping_method.twig
21c4f1982f357e428f3a6c843c6cd42ebedce0dd2a1307ec1128c6833268f1db
```

## Delta from the 2026-09-03 audit

The UI-FIX wave changed several file contents, but it did not change the
event-relevant verdicts from the handoff table:

| Event / surface | 2026-09-03 verdict | Fresh 2026-09-07 verdict | Delta |
|---|---|---|---|
| `add_shipping_info` | Toggle off; anchor present | Anchor still present | No code delta; toggle-only package confirmed. |
| `add_payment_info` | Toggle off; anchor absent | Anchor still absent | No anchor delta; payment patch still required. |
| Tile `add_to_cart` | Buy-button anchor absent | Anchor still absent; list payload anchors present | No functional delta; WP3b still required. |
| Tile `select_item` | Name-link anchor present | Name-link anchor still present | Still works; do not patch it generally. |
| Mini-cart `remove_from_cart` | Remove anchor absent | Remove anchor still absent; dataset anchor present | No functional delta; WP3c still required. |

## WP3a verified design inputs

- The exact vendor response key is `ps_add_payment_info`, set by
  `eventCatalogViewCheckoutPaymentMethodSaveAfter()` on the
  `checkout/payment_method.save/after` response.
- The payment template uses a custom success callback with no
  `json['success']` branch. It exits on `json.error`, then commits the selected
  payment method. WP3a must consume `json.ps_add_payment_info` after the error
  guard and before the local success-state updates.
- Shipping retains the vendor anchor at exactly one location. No shipping Twig
  patch is justified.
- Owner must enable both `Track Add Shipping Info` and
  `Track Add Payment Info` only after the payment patch is deployed.

## WP3b verified design inputs

- Every available tile submits the existing `.bs-pcard__form` through the stock
  OpenCart delegated AJAX handler. The form has exact hidden `product_id` and
  `quantity` inputs. Out-of-stock tiles render no form; preorder tiles use the
  same form and action as in-stock tiles.
- The cart controller validates required options. A tile submit for a product
  with required options does not add it; the JSON response redirects to the
  product page. Therefore WP3b must branch using vendor-prepared
  `ps_has_options`: emit `select_item` for an option-bearing non-special item,
  and `add_to_cart` only for a directly addable item. This mirrors the vendor's
  intended event choice without its click interception.
- Category, search and special controllers build
  `ga4_data['add_to_cart_<product_id>']` datasets, including the product minimum
  quantity. Their Twig injection anchors are present.
- Bestseller, featured, latest and special module handlers also inject datasets
  outside `product/thumb.twig`. The listener must be globally idempotent because
  the partial can render repeatedly. One namespaced delegated submit handler,
  protected by a `window` binding flag, means one submitted form causes one
  lookup and cannot double-fire even when the same product appears in several
  modules. Dataset keys remain vendor-owned and keyed by product id.
- No `data-ps-track-event` or `data-ps-track-id` attributes will be added to the
  buy button.

## WP3c verified design inputs

- The mini-cart controller prepares `remove_from_cart_<cart_id>` entries.
- The live template retains the dropdown anchor that injects those entries into
  `ps_dataLayer.ga4_data`.
- The custom `.mini-cart-remove-btn` owns removal through a delegated,
  namespaced click handler. WP3c can call
  `ps_dataLayer.onClick('remove_from_cart', cartId)` from that existing handler
  without preventing, redispatching or otherwise changing the cart mutation.
- The call must first verify that the exact vendor dataset key exists, so a
  disabled/removed module degrades to a silent no-op instead of a console error.

## Next bounded action

Prepare and locally validate three separate hosting runners and reports:

1. WP3a payment template patch; shipping remains read-only.
2. WP3b catalogue tile form tracking patch.
3. WP3c mini-cart removal tracking patch.

Each runner must use the SHA-256 source gate above, back up before writing,
validate a generated copy before the live write, restore on failure, remain PHP
8.0-compatible, report `already_applied=yes` on repeat, and self-delete only
after success.

## Owner deployment decision, 2026-09-07

Production order is **WP3a → WP3c → WP3b**. WP3b is deliberately last and
isolated because it is the only package that touches the catalogue buy button.
After WP3a: Tier 1, enable both shipping/payment toggles, then full checkout
smoke. After WP3c: Tier 1. After WP3b: cart-first functional QA, Tier 1, then
full checkout smoke.

Two cleanups are recorded but explicitly deferred: WP3b renders the guarded
inline listener block per tile, and the existing live mini-cart handler contains
`console.log(json)`. Neither changes the current patch artifacts.
