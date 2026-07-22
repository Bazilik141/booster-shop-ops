# Codex Report — PAY-001: credit selection, cart contract, threshold and preorder guard

Date: 2026-07-22

## Context

The deployed follow-up successfully sends the product modal to stock `checkout/checkout` and preserves the chosen 3/4/5 part count. It does not add the viewed product to the cart. That is expected from the current UI: its modal only navigates with `mono_chast_parts`; it never invokes the normal OpenCart cart-add flow.

The next change is checkout/payment-risky. Do not patch until the owner resolves the decisions below and supplies fresh current files. No database change is requested or expected.

## Confirmed observations

1. Selecting a credit term redirects to stock checkout with the term selected, but leaves the cart unchanged.
2. The term chips can display `5, 3, 4`: the current payment controller reorders the source list so the preferred term is first. UI order must instead be fixed as `3, 4, 5`; only the active state may change.
3. The current generic checkout card contains the unwanted copy `Розстрочка від підключених банків`.
4. The current product gate uses the unit product price. It therefore cannot show an actionable credit flow for a low-price item even when current-cart total plus requested quantity reaches the minimum.
5. The existing quantity-zero product-page guard is presentation-only. The checkout payment controller must independently hide/reject Mono for an order that contains any product with factual stock quantity `0` (preorder/out of stock).

## Proposed contract for approval

### Product-modal action

- `Обрати` must use the **existing normal cart-add endpoint and payload**, including the currently selected quantity and every product option. Redirect only after that endpoint confirms success.
- Existing cart lines must retain normal OpenCart semantics: the selected quantity is added to the cart; required options, stock maximums, errors and same-product line merging remain owned by the normal cart flow.
- `Продовжити покупки` is a secondary button beside `Обрати`; it closes the modal and does not add an item or persist a credit term.
- Recommended label for the primary action: `Додати в кошик і оформити` because it has both side effects. Keep `Обрати` only if the owner prefers the shorter copy.

### Eligibility display

- Show the provider information for every sellable (`quantity > 0`) product while sandbox Mono is enabled.
- Compute the product-page prospective threshold as: current eligible cart merchandise total + displayed product's current unit price × quantity currently selected on the page. Use the same currency and price basis as the final server gate.
- Below the threshold, visually mute the provider block and disable the credit button with a hint such as `Оплата частинами доступна від <minimum> грн. Додайте ще <remaining> грн.`
- Once the prospective total meets the threshold, activate the button without a full page reload.
- If the existing cart includes a zero-stock/preorder item, keep the product credit button disabled and explain that credit is unavailable while the cart contains preorder items.

### Final server-side checkout gate

- Treat browser UI only as a hint. Before returning the virtual Mono payment method, recalculate the real current cart total and independently inspect every cart product's actual stock quantity.
- If any product is quantity `0`, omit/reject the virtual Mono method even if total is high enough. SimpleCheckout isolation remains unchanged.
- Preserve the existing minimum-total gate. Owner must explicitly decide whether coupon/discount effects are included in that minimum; the product preview and backend must then use the same definition.

## Files to collect from the current server

The deployed files changed today are the required source of truth. Upload a fresh archive containing exactly:

```bash
cd ~/public_html || exit
tar -czf booster-debug-PAY001-credit-cart-contract.tar.gz \
  catalog/controller/product/product.php \
  catalog/view/template/product/product.twig \
  catalog/controller/checkout/cart.php \
  catalog/controller/checkout/checkout.php \
  catalog/controller/checkout/payment_method.php \
  catalog/view/template/checkout/payment_method.twig \
  catalog/view/javascript/checkout-state.js \
  catalog/view/javascript/checkout-reskin.js \
  system/library/cart/cart.php \
  extension/mono_chast/catalog/controller/payment/mono_chast.php
```

Download and provide `booster-debug-PAY001-credit-cart-contract.tar.gz`. Do not include `config.php`, database dumps, logs with customer data, or credentials.

## Owner decisions required

1. Confirm primary modal action: standard cart-add of the selected product/quantity/options, then redirect; no replacement of existing cart lines.
2. Confirm secondary action: `Продовжити покупки` only closes the modal and leaves cart/session unchanged.
3. Confirm the minimum uses merchandise total after or before discounts/coupons. Shipping must not be included unless explicitly requested.
4. Confirm whether any zero-stock/preorder item in the existing cart disables Mono for the whole order. Recommended: yes.
5. Confirm treatment of a requested quantity above available stock: recommended normal OpenCart cart-add failure, no redirect.
6. Confirm scope is only the product page and stock checkout, not category/search product cards.
7. Confirm primary button wording: `Додати в кошик і оформити` (recommended) or retain `Обрати`.

## Implementation boundaries after approval

Expected files: product controller/template, current cart-add integration in product template, and stock `payment_method` controller/template. No DB schema/settings, `system/library/url.php`, Mono API payload, order-write boundary, Hutko/COD/IBAN, NCRM or SimpleCheckout isolation should change.

## Acceptance criteria

- A product with selected quantity/required options is added normally before redirect; stock checkout opens with the chosen 3/4/5 term selected.
- Term chips always render `3 платежі`, `4 платежі`, `5 платежів`; the active term never changes their order.
- Direct stock-checkout selection shows the same fixed-order chips, and the generic card does not show the removed caption.
- A low-price in-stock product displays a muted eligibility hint below the threshold and becomes actionable when cart plus selected quantity reaches it.
- A product with factual quantity `0` has no actionable credit flow; any cart containing such a product exposes no Mono method in stock checkout.
- Normal cart add errors, Hutko, COD, IBAN and legacy SimpleCheckout remain intact.

## Risks

High-risk checkout/payment surface. The cart must be changed only through its existing endpoint and validation path; do not create a client-side-only cart state. The preorder guard must be server-side and based on current product stock, not a label or a browser value. Local/static validation is not sandbox, callback, API or live order proof.
