# ST-0 checkout preflight report

Date: 2026-06-12
Scope: read-only diagnostics before migrating Booster Shop checkout from Pinta SimpleCheckout to stock OpenCart 4 checkout.

## Summary

- Current checkout entry remains `checkout/checkout` in cart, but the active SimpleCheckout module rewrites this route to `extension/SimpleCheckout/module/pinta_simple_checkout`.
- SimpleCheckout is heavily customized versus the marketplace package. It is not safe to disable it without explicitly deciding what to preserve.
- Nova Poshta COD/shipping extension already has stock OpenCart checkout hooks for `checkout/register`, `checkout/shipping_address`, and `checkout/shipping_address.save`.
- Stock checkout, cart, success, and failure pages are already locally customized and need to be preserved during ST-2.
- ST-0 made no live writes, no database writes, no setting changes, and no cache clears.

## Exact Cart To Checkout Wiring

Current cart button target:

- `catalog/controller/checkout/cart.php:216`
- `$data['checkout'] = $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'));`

SimpleCheckout route rewrite source:

- `extension/SimpleCheckout/admin/model/module/modificate.php:19`
- If route is `checkout/checkout`, it is rewritten to `extension/SimpleCheckout/module/pinta_simple_checkout`.

SimpleCheckout admin controller toggles that modification:

- `extension/SimpleCheckout/admin/controller/module/pinta_simple_checkout.php:26`
- `Modificate();`
- `extension/SimpleCheckout/admin/controller/module/pinta_simple_checkout.php:28`
- `delModificate();`

Database dump evidence:

- `module_pinta_simple_checkout_status = 1`
- SimpleCheckout module is currently enabled.

Log evidence:

- SSL logs contain many requests to `/?route=extension/SimpleCheckout/module/pinta_simple_checkout`.
- Related AJAX endpoints are active: `payment_method`, `shipping_method`, `cart`, `coupon`.

Live read-only check:

- `https://boostershop.website/index.php?route=checkout/checkout` redirects to cart when the session cart is empty.
- `https://boostershop.website/?route=extension/SimpleCheckout/module/pinta_simple_checkout` also redirects to cart when the session cart is empty.
- Full checkout render with a cart item was not tested because ST-0 is read-only.

## SimpleCheckout Local Customizations To Account For

### Coupon / First15

Current SimpleCheckout has local coupon logic:

- `extension/SimpleCheckout/catalog/controller/module/pinta_simple_checkout.php:45`
- `prepareSimpleCheckoutCouponTotal();`
- `extension/SimpleCheckout/catalog/controller/module/pinta_simple_checkout.php:2839`
- `public function coupon(): void`
- `extension/SimpleCheckout/catalog/controller/module/pinta_simple_checkout.php:3766`
- `applySimpleCheckoutCouponCode(...)`
- First15 duplicate-use guard and welcome coupon session cleanup are implemented locally.

Current SimpleCheckout Twig contains custom coupon UI:

- `extension/SimpleCheckout/catalog/view/template/module/checkout.twig`
- Coupon block, AJAX apply/remove buttons, and `bs-coupon-*` styles.

Stock state:

- Stock coupon extension exists in `extension/opencart/catalog/controller/checkout/coupon.php`.
- Stock cart modules accordion is commented out in `catalog/view/template/checkout/cart_list.twig`, so coupon UI is currently hidden from the cart.

ST-2 decision:

- Do not simply drop coupon behavior.
- Either re-enable stock coupon UI or add a stock-checkout-compatible coupon block.
- Port First15 duplicate-use/session cleanup if this promo is still required.

### GA4 Begin Checkout

Current SimpleCheckout emits a local GA4 `begin_checkout` payload:

- `extension/SimpleCheckout/catalog/controller/module/pinta_simple_checkout.php:391`
- `buildSimpleCheckoutGa4BeginCheckoutPayload()`
- `extension/SimpleCheckout/catalog/view/template/module/checkout.twig:355`

Database dump shows `analytics_ps_enhanced_measurement` events are already enabled for stock checkout routes:

- `checkout/checkout`
- `checkout/payment_method`
- `checkout/shipping_method`
- `checkout/confirm`
- `checkout/success`

ST-2 decision:

- Avoid duplicate `begin_checkout`.
- Prefer the existing stock route analytics event unless live QA proves it does not fire.

### Public Offer / Agreement

Current SimpleCheckout has two agreement concepts:

- Local public offer checkbox `agree_public_offer`.
- Stock-style `agree` checkbox.

Relevant files:

- `extension/SimpleCheckout/catalog/view/template/module/checkout.twig`
- `extension/SimpleCheckout/catalog/controller/module/pinta_simple_checkout.php`
- `catalog/controller/checkout/confirm.php`

Stock confirm validates `session['agree']`.

ST-2 decision:

- Consolidate agreement handling to one source.
- Do not leave two visually similar checkboxes with different session behavior.

### Phone Requirement

SimpleCheckout locally requires telephone more strictly than stock checkout:

- `extension/SimpleCheckout/catalog/controller/module/pinta_simple_checkout.php`
- Validation around telephone length and presence is repeated in multiple branches.

Stock register currently depends on:

- `config_telephone_display`
- `config_telephone_required`

ST-2 decision:

- Confirm whether phone must always be required.
- If yes, enforce it in the stock checkout path, not only through config assumptions.

### Nova Poshta Free Text Address / Poshtomat Comment

SimpleCheckout currently uses free-text Nova Poshta address labels:

- `address_1`: branch or poshtomat free text.
- `address_2`: optional courier address.
- Comment workaround: for poshtomat delivery, user is told to write poshtomat address/number in the comment.

Relevant file:

- `extension/SimpleCheckout/catalog/view/template/module/checkout.twig`

ST-2 decision:

- Prefer the structured Nova Poshta module fields in stock checkout.
- Keep the old comment workaround only as fallback if business explicitly wants it.

## Nova Poshta Stock Checkout Compatibility

Nova Poshta extension includes stock checkout hooks:

- `extension/PintaNovaPoshtaCod/catalog/controller/payment/pinta_nova_poshta_cod.php`
- `alterShippingAddressRegister`
- `alterShippingAddress`
- `alterRedirectShippingAddressSave`

Database dump shows enabled events for:

- `catalog/controller/checkout/shipping_address/after`
- `catalog/controller/checkout/register/after`
- `catalog/controller/checkout/shipping_address.save/after`

Nova Poshta checkout form fields:

- `shipping_existing=novaposhta`
- recipient first/last/middle name
- area
- city
- warehouse / doors / poshtoma type
- warehouse address
- doors street/house/flat

Relevant templates:

- `extension/PintaNovaPoshtaCod/catalog/view/template/checkout/checkout_shipping_address_form.twig`
- `extension/PintaNovaPoshtaCod/catalog/view/template/checkout/js_checkout_shipping_address_form.twig`

ST-2 decision:

- Keep these events enabled.
- Verify after switching that the stock checkout still renders the NP form and saves `shipping_address`.

## Stock Checkout State

Stock checkout controller:

- `catalog/controller/checkout/checkout.php`
- Redirects to cart when cart is empty or stock/minimum checks fail.
- Loads register, payment address, shipping address, shipping method, payment method, and confirm blocks.

Stock checkout template is already locally customized:

- `catalog/view/template/checkout/checkout.twig`
- R-11 layout and JS restyling are present.

Stock cart:

- `catalog/controller/checkout/cart.php`
- `catalog/view/template/checkout/cart_list.twig`
- Cart checkout button still links to `checkout/checkout`.
- Cart coupon/modules block is commented out.

Success page:

- `catalog/controller/checkout/success.php`
- Already customized to preserve order data, clear coupon/reward/welcome sessions, and hide zero Nova Poshta totals.
- `catalog/view/template/checkout/success.twig`
- Already contains payment/delivery summary logic.

Failure page:

- `catalog/controller/checkout/failure.php`
- Breadcrumb points to `checkout/checkout`; after migration it should naturally point to stock checkout.

## Session And Data Mapping

SimpleCheckout currently relies on:

- `session['guest']`
- `session['shipping_address']`
- `session['payment_address']`
- `session['shipping_method']`
- `session['payment_method']`
- `session['comment']`
- `session['agree']`
- `session['coupon']`
- `session['welcome_coupon_*']`
- `session['order_id']`

Stock checkout expects:

- `session['customer']` for registered checkout.
- `session['shipping_address']` with `address_id` when shipping is required.
- `session['payment_address']` if payment address is enabled.
- `session['shipping_method']`.
- `session['payment_method']`.
- `session['agree']`.
- `session['comment']`.
- `session['coupon']` / `session['reward']`.

Main migration risk:

- SimpleCheckout accepts more ad hoc address/comment/session paths.
- Stock confirm is stricter, especially around `shipping_address.address_id` and `session['agree']`.

## Recommended ST-2 Patch Direction

ST-2 should be a separate controlled patch, not part of ST-0.

Minimum safe direction:

1. Backup every changed file before writing.
2. Explicitly warn before any database setting/event change.
3. Disable the SimpleCheckout route rewrite/module path safely.
4. Keep Nova Poshta stock checkout events enabled.
5. Preserve stock checkout R-11 template customizations.
6. Decide and implement coupon/First15 behavior in stock checkout/cart.
7. Decide and implement one agreement/public-offer flow.
8. Decide phone requirement and enforce it in stock checkout if needed.
9. Clear OpenCart cache/template/modification cache after patch execution.
10. Syntax-check every modified PHP file.

## ST-2 Acceptance Checks

- Cart button opens stock checkout route, not SimpleCheckout.
- Stock checkout renders with product in cart.
- Nova Poshta structured address form is visible.
- Warehouse/doors/poshtomat selection saves a valid shipping address.
- Payment method and shipping method save correctly.
- Coupon UI is present if promo codes remain supported.
- First15 is not reusable by the same account if this business rule remains active.
- Agreement/public offer validation blocks confirm when unchecked.
- Phone validation matches business requirement.
- Success page still shows correct order/payment/delivery summary.
- No duplicate GA4 `begin_checkout` event.
- No fatal errors in PHP logs.

## Stop Conditions For ST-2

Stop and inspect before changing code if:

- Generated OCMOD/modification file differs from the inspected source route rewrite.
- Nova Poshta events are missing or disabled on live.
- Stock checkout does not render NP form after SimpleCheckout is disabled.
- Coupon/First15 business rule is unclear.
- A payment/fiscalization hook depends directly on SimpleCheckout-only request fields.

