# Codex Execution Report — TECH-015 WP3: GA4 funnel remainder

Date: 2026-09-07

## Outcome

WP3 was deployed to production in the owner-approved order **WP3a → WP3c →
WP3b**. All three runners completed with `done=ok`, `self_delete=ok`, the
declared generated SHA-256, and `database_touched=no`. OpenCart cache was cleared
after every deployment.

Production QA passed for `add_payment_info`, `add_shipping_info`, mini-cart
`remove_from_cart`, catalogue-tile `add_to_cart`, cart correctness, Tier 1 URLs,
console state and the required full checkout smoke runs.

Codex did not deploy, commit, push, write Notion, or change roadmap status. The
owner performed all production execution and manual QA.

## Production sequence and evidence

| Package | Production time | Target | Generated SHA-256 | Result |
|---|---|---|---|---|
| WP3a | `2026-09-07T18:32:45+00:00` | `catalog/view/template/checkout/payment_method.twig` | `974d73f62b98b4a562db1478abcea040cd47b7270675e368173a83b3e43bf7c6` | Runner, Tier 1, both toggles, both events and full checkout smoke passed. |
| WP3c | `2026-09-07T19:04:08+00:00` | `catalog/view/template/common/cart.twig` | `020b8f2a9dfee24d8c55b386415ef31731d16586a70191cbd8266b54dfd27fec` | Runner, mini-cart correctness, event, console and Tier 1 passed. |
| WP3b | `2026-09-07T19:24:35+00:00` | `catalog/view/template/product/thumb.twig` | `ee483c07b40bdd58a2b23e75793b9850aef61ed4efd9df8bf0944f7b538f9c5a` | Runner, cart-first scenarios, event, console, Tier 1 and full checkout smoke passed. |

## Runtime event evidence

Owner-supplied DevTools evidence established:

- `add_payment_info`: GA4 property `G-283QW89TX8`, `UAH`, value `1000`, product
  id `110`, price `1000`, quantity `1`, and a payment type.
- `add_shipping_info`: the same cart identity/value plus a shipping tier.
- `remove_from_cart`: `UAH`, value `1000`, product id `110`, price `1000`,
  quantity `1`, with the vendor cart-list metadata.
- `add_to_cart`: owner confirmed one event per tile action with correct product,
  price, quantity and currency; repeating the action produced one additional
  unit and one additional event, without a duplicate for either action.

GA4 was inspected at the `collect` request payload level because multiple events
may be batched in one request. Sensitive browser request cookies accidentally
included in one raw evidence paste were not retained in repository reports.

## Cart correctness acceptance

Owner confirmed all required WP3b checks:

- first tile action added exactly one line/unit;
- second action added one more unit without an extra line;
- a second product produced the correct second line and total;
- full-cart quantity changes remained correct;
- option-bearing, preorder and out-of-stock tile behaviour remained unchanged;
- no new console error appeared.

The full `bs-checkout-smoke` passed after WP3a and again after WP3b. Tier 1
passed after every deployed package.

## Production backups

```text
/home2/boosters/public_html/_patch_backups/TECH-015_ga4-add-payment-info_wp3a_20260907-20260907-183245-27322e
/home2/boosters/public_html/_patch_backups/TECH-015_ga4-mini-cart-remove_wp3c_20260907-20260907-190408-1a0dad
/home2/boosters/public_html/_patch_backups/TECH-015_ga4-catalog-add-to-cart_wp3b_20260907-20260907-192435-f5bebe
```

Each backup contains the original target under `original/`. For a rollback,
restore only the exact affected file from the corresponding directory, clear
OpenCart cache, then repeat Tier 1 and the relevant focused smoke test.

## Delivered artifacts

- `diagnostics/TECH-015_ga4-funnel-remainder_wp3-0_report_20260907.md`
- `diagnostics/TECH-015_ga4-add-payment-info_wp3a_report_20260907.md`
- `diagnostics/TECH-015_ga4-mini-cart-remove_wp3c_report_20260907.md`
- `diagnostics/TECH-015_ga4-catalog-add-to-cart_wp3b_report_20260907.md`
- `patches/TECH-015_ga4-add-payment-info_wp3a_20260907.php`
- `patches/TECH-015_ga4-mini-cart-remove_wp3c_20260907.php`
- `patches/TECH-015_ga4-catalog-add-to-cart_wp3b_20260907.php`

The separate Claude pre-deploy review remains at
`diagnostics/TECH-015_ga4-funnel-remainder_wp3_review_20260907.md`.

## Deferred observations — no respin in WP3

1. The WP3b listener block is rendered once per product tile. Its global guard
   prevents duplicate bindings, so behaviour is correct, but the shared listener
   should move to a once-per-page location in a future page-weight task.
2. The pre-existing mini-cart removal handler retains `console.log(json)` on a
   production cart control. Remove it only in a separate cleanup scope.

## Remaining TECH-015 closure gates

WP3 itself is complete. Overall TECH-015 status is not changed here and remains
owner/Claude-gated. The previously recorded non-WP3 closure items remain:

- an authorized PAY-003 recovery render;
- a real Hutko return, including whether an unconfirmed Hutko payment can reach
  `checkout/success` and book revenue for an unpaid order;
- owner verification that GA4 `purchase` is registered as a key event.

Only the owner authorizes closure; Claude (chat) owns Notion and dashboard status
writes.
