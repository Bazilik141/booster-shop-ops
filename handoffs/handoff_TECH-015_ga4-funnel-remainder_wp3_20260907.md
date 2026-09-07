# Handoff — TECH-015 WP3: the rest of the GA4 funnel

Date: 2026-09-07
Executor: Codex · model=Sol/xhigh · effort=high — owner assignment carried over
from WP1/WP2; never swap executor mid-task.
Justification: catalogue add-to-cart and the checkout payment step are risky
zones on a production-direct deploy, and WP3b touches the buy button itself.

Four work packages. **One patch file each.** WP3-0 is a read-only verification
that must complete and be reported before any patch is written.

---

## 1. Task ID

`TECH-015 — GA4 ecommerce + conversion tracking`. WP1 (`purchase`) and WP2
(`begin_checkout`) are deployed and reviewed. WP3 closes the remaining funnel
gaps found in the 2026-09-05 audit that were never turned into work packages.
Do not open a new roadmap ID.

Prior artifacts to read first:
`diagnostics/GA4-EM_purchase-and-funnel-audit_report_20260905.md`,
`diagnostics/TECH-015_ga4-begin-checkout_wp2_review_20260907.md`.

## 2. Context

Read the WP2 review before anything else. Its lesson governs this handoff.

The vendor module `ps_enhanced_measurement` injects by literal `str_replace`
against stock OpenCart 4 twig. Every Booster template rewrite that removed an
anchor killed an event silently. WP2 proved the correct repair shape, and it is
**not** restoring the vendor's anchor:

> the Booster template renders the vendor's already-prepared `$args` values
> directly, inside our own guard. The vendor keeps building the payload; we own
> where and how it is emitted.

Use that shape everywhere in WP3. Do not restore vendor anchors, and do not
duplicate the vendor's payload logic.

**The inventory below is from the 2026-09-03 backup and is not current.** The
UI-FIX wave of 2026-09-03/04 modified `common/header.twig`,
`product/category.twig` and `product/product.twig` after that snapshot. WP2
regressed on production precisely because a handoff assumed a file state it had
not verified. WP3-0 exists to stop that repeating.

Audit state as of 2026-09-03, to be re-verified, not trusted:

| Event | State | Where the gap is |
|---|---|---|
| `add_payment_info` | off by setting | `checkout/payment_method.twig` anchor `if (json['success']) {` absent |
| `add_shipping_info` | off by setting | `checkout/shipping_method.twig` anchor present |
| `add_to_cart` from catalogue tiles | dead | `product/thumb.twig` buy-button anchor absent |
| `select_item` from tiles | **works** via the product-name link anchor, which is present — do not "fix" it |
| `remove_from_cart` in header mini-cart | dead | `common/cart.twig` remove-button anchor absent |
| `add_to_cart` / `remove_from_cart` elsewhere | work | product page, cart page |

## 3. Goal

Every GA4 funnel step the owner wants is emitted, from the vendor's payloads,
without vendor click interception and without touching vendor files.

## 4. What to change

### WP3-0 — verification pass, read-only, no patch

Blocks everything else.

1. Ask the owner for a **fresh cPanel backup**. The newest in the repository is
   `backup-9.3.2026_21-30-35_boosters.tar.gz` and it predates the UI-FIX
   deploys plus TECH-015 WP1/WP2. Do not derive anchor state from it.
2. From that fresh backup, produce the current-state inventory: for each of
   `common/header.twig`, `product/thumb.twig`, `product/category.twig`,
   `product/search.twig`, `product/special.twig`, `product/product.twig`,
   `common/cart.twig`, `checkout/cart.twig`, `checkout/cart_list.twig`,
   `checkout/payment_method.twig`, `checkout/shipping_method.twig`, list which
   of the vendor's `replace*Before()` anchors are present and which are gone.
   The anchor list is in the vendor model
   `extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php`.
   Note the version gates: this install is OpenCart `4.1.0.3`, so the
   `>= 4.1.0.0` branch applies for `product/thumb.twig` and
   `account/wishlist_list.twig`, and the `<= 4.1.0.0` branch does **not** apply
   for `checkout/cart_list.twig`.
3. Report the delta against the table in §2. If a package below turns out to be
   unnecessary, say so and drop it rather than patching a working file.

### WP3a — `add_shipping_info` and `add_payment_info`

Two halves, and the split matters:

- **Shipping.** If WP3-0 confirms the `if (json['success']) {` anchor still
  exists in `checkout/shipping_method.twig`, this event needs **no patch** — only
  the admin toggle, which is the owner's action. Say so plainly.
- **Payment.** `checkout/payment_method.twig` was rewritten by PAY-002/PAY-005
  and lost the anchor. Apply the WP2 shape: the module's
  `eventCatalogViewCheckoutPaymentMethodSaveAfter` builds
  `$json['ps_add_payment_info']` on the `checkout/payment_method.save` response.
  Our template's own success handler must read that key and push it through
  `ps_dataLayer.pushEventData('add_payment_info', …)`, guarded so it is a no-op
  when the key is absent. Verify the exact key name in the vendor controller —
  do not assume it from this handoff.

The admin toggles are owner actions. State clearly in the report which toggle
must be switched on, and that the toggles must be flipped **after** the payment
patch is deployed, so the owner never has a period where shipping data arrives
and payment data silently does not.

### WP3b — `add_to_cart` from catalogue tiles (highest risk in WP3)

`product/thumb.twig` renders a stock OpenCart 4.1 AJAX form
(`data-oc-toggle="ajax"`, `formaction="{{ cart_add }}"`) with the Booster
preorder/out-of-stock variants.

**Do not add `data-ps-track-event` / `data-ps-track-id` attributes to the buy
button.** The vendor's `ps-enhanced-measurement.js` binds a delegated click
handler on `[data-ps-track-event]` that calls `e.preventDefault()`, disables the
element, fires the event, then re-enables and re-triggers `click` on a
`setTimeout`. On the catalogue buy button that means intercepting and
re-dispatching the add-to-cart click, next to the Booster preorder logic and the
stock AJAX handler. That is a cart-correctness risk taken for a tracking event,
and it is not acceptable on the buy button.

Instead, emit from a Booster-owned delegated listener that does not interfere:
on submit of the tile form, read the `product_id` hidden input and call the
vendor's own lookup, `ps_dataLayer.onClick('add_to_cart', productId)`, letting
the form submit normally. The dataset it reads
(`ga4_data['add_to_cart_<product_id>']`) is populated by the category/search/
special template injection whose `{% if products %}` anchor is intact — WP3-0
must confirm that is still true, since the payload does not exist otherwise.

Two things the executor must resolve against the real code, not assume:

- products **with options** cannot be added from a tile; the vendor emits
  `select_item` for those instead. Establish what this site's tiles actually do
  for such products before deciding whether the listener must branch.
- confirm the listener cannot double-fire when the same product appears in two
  modules on one page (home tiles, bestseller, featured, latest).

### WP3c — `remove_from_cart` in the header mini-cart

`common/cart.twig` lost the remove-button anchor. Same rule as WP3b: no vendor
click interception on a control that mutates the cart. Use a Booster-owned
listener calling `ps_dataLayer.onClick('remove_from_cart', …)` with the id the
vendor's dataset is keyed by.

WP3-0 must first confirm that the mini-cart dataset is actually populated: the
vendor's `replaceCatalogViewCheckoutCartInfoBefore` has three anchors and only
two were present in the 2026-09-03 state. If the `setData` injection is among
the missing ones, there is no payload to emit and this package changes shape —
report that before patching.

## 5. Do not touch

- Any file under `extension/ps_enhanced_measurement/`.
- The buy button's form action, method, hidden inputs, preorder
  (`bs_is_pre`) and out-of-stock (`bs_is_out`) branches, or its classes — green
  is reserved for purchase actions and the visual contract is not in scope.
- Cart quantity, stock, price or discount logic anywhere.
- `catalog/controller/checkout/success.php`, `checkout/success.twig`,
  `checkout/checkout.twig` — WP1/WP2 are deployed and reviewed; reopening them
  here would put two changes in one blast radius.
- Payment: Hutko, PUMB, monobank, `checkout/credit.php`, callbacks, order status.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, Merchant feed,
  Product schema.
- Notion and `ROADMAP_TASKS` — Claude (chat) writes those.

## 6. Likely files / areas

Likely, not confirmed. WP3-0 replaces this list with a verified one.

```
catalog/view/template/checkout/payment_method.twig     — WP3a
catalog/view/template/checkout/shipping_method.twig    — WP3a, probably read-only
catalog/view/template/product/thumb.twig               — WP3b
catalog/view/template/common/cart.twig                 — WP3c
```

Admin settings (owner action, not a patch): Track Add Payment Info,
Track Add Shipping Info.

## 7. Acceptance criteria

Per package, measured in the browser on production:

- WP3a: submitting the shipping step produces one GA4 request carrying
  `en=add_shipping_info`; submitting the payment step produces one carrying
  `en=add_payment_info`, each with the cart's currency, value and items. Filter
  Network on the GA4 collect request, not on a narrow string — GA4 batches
  several events into one request, which produced false negatives throughout WP2.
- WP3b: clicking Buy on a catalogue tile adds exactly one line to the cart, the
  cart total is correct, and one `en=add_to_cart` is sent with that product's id,
  price and quantity. Clicking twice adds two units and sends two events. A
  preorder tile and an out-of-stock tile behave exactly as they do today.
- WP3c: removing an item from the header mini-cart removes it, and sends one
  `en=remove_from_cart` for that product.
- No new console errors on catalogue, cart or checkout pages.
- `view-source:` shows one snippet per template, not two.

## 8. QA / smoke test

WP3b changes behaviour on the buy button and WP3a on the checkout payment step.
Run `bs-checkout-smoke` in full after WP3a and WP3b. The Tier 1 URL set from
`AGENTS.md` after every one of the four deploys.

Cart-correctness checks that matter more than the tracking here: add from a tile,
add the same product again, add a second product, change quantity in the cart,
remove from the mini-cart, then confirm the cart totals and line count are exactly
what they were before the patch. A tracking event is never worth a wrong cart.

## 9. Rollback note

Per-package backup under `_patch_backups/<patch>-<ts>/original/`, restore with a
`cp` per file, then clear the OpenCart cache. Rollback triggers: any wrong cart
line or total, a buy button that does not add, a duplicated GA4 event, or any new
console error on the catalogue. Reverting costs only the event — revert first,
diagnose after.

## 10. Delivery and recommended status after execution

Patch file into `patches/`, owner uploads to `~/public_html` and runs
`php <patch>.php`. The executor never commits, pushes, uploads or deploys.
Runners must keep the WP1/WP2 conventions: SHA-256 source gate, backup of
originals, generated-file check before the live write, ordered restore on write
failure, idempotent `already_applied=yes`, self-delete. PHP 8.0-safe.

Report per package into `diagnostics/TECH-015_<slug>_report_20260907.md` using
`templates/codex-report-template.md`. **Record the production runner output in
the report** — WP2's hotfix run was never captured there and the owner had to
supply it from memory.

TECH-015 stays `In progress`. Closure additionally requires the two WP1 runtime
gaps (authorized PAY-003 recovery render; a real Hutko return, and with it
whether an unconfirmed Hutko payment can reach `checkout/success`), plus the
owner's GA4 check that `purchase` is registered as a key event. Only the owner
authorizes closure; Claude (chat) performs the Notion and dashboard write.
