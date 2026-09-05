# Handoff — TECH-015: GA4 `purchase` and `begin_checkout` are never emitted

Date: 2026-09-05
Executor: Codex · model=Sol/xhigh · effort=high — owner assignment, recorded not proposed.
Justification: the change lands on the live order-success page and the live
checkout page, both risky zones, with no staging. Files are already identified
below, so this is a bounded but high-consequence patch, not a discovery task.

Two work packages. **One patch file each — never bundle.** WP1 ships and is
verified before WP2 starts.

---

## 1. Task ID

`TECH-015 — GA4 ecommerce + conversion tracking` (Notion, In progress, High).
This handoff resolves the regression recorded in that task's 2026-08-04 note and
names its cause. Do not open a new roadmap ID.

## 2. Context

Full evidence: `diagnostics/GA4-EM_purchase-and-funnel-audit_report_20260905.md`.
Read it before starting. Summary of what matters for the patch:

The GA4 extension `ps_enhanced_measurement` (Playful Sparkle v1.0.9) injects its
event snippets by literal `str_replace` against stock OpenCart 4 `.twig` source.
Two of our own rewrites removed the anchors it needs:

- **`purchase`** — the injector is bound to the trigger
  `catalog/view/common/success/before` (`ocp5_event` id 93). Our customized
  `catalog/controller/checkout/success.php` renders `checkout/success`, not
  `common/success`, so that trigger never fires on the order-success page.
  Confirmed on production 2026-09-05: the rendered success page contains no
  `pushEventData('purchase'` string. GA4 for 2026-08-08…09-04: key events 0,
  revenue 0, against 12 169 events — zero purchases have ever reached GA4 since
  the success page was customized.
- **`begin_checkout`** — the injector matches `<h1>{{ heading_title }}</h1>`;
  `catalog/view/template/checkout/checkout.twig` line 11 is a hardcoded
  `<h1>Оформити замовлення</h1>`. Confirmed on production 2026-09-05: no
  `pushEventData('begin_checkout'` in the rendered checkout page.

Module configuration is correct and is **not** the fix: status on, Track Purchase
on, Track Begin Checkout on, tag `G-283QW89TX8`, implementation `gtag`. All 42
module events are registered and enabled in `ocp5_event`.

What already works and must keep working: the module's `</head>` block renders on
every page and defines the global `ps_dataLayer` helper, including
`pushEventData(eventName, data)` which gates on `ga4_tracking[eventName]`
(`purchase:true`, `begin_checkout:true` confirmed in the live page source) and
calls `window.gtag('event', name, data.ecommerce)`. **Reuse that transport.**
Do not add a second gtag loader and do not build a parallel data layer.

There is no server-side fallback to lean on: the module's admin Measurement
Protocol handler sends only lead and refund events, never `purchase`.

## 3. Goal

GA4 receives one correct `purchase` event per completed order, and one
`begin_checkout` event per checkout entry, without editing the vendor extension.

## 4. What to change

### WP1 — `purchase` on the order-success page (P0)

Emit the event from Booster-owned code on the success page, built from the
**order**, not from the cart.

`catalog/controller/checkout/success.php` already resolves the order for display:
it computes `$order_id` from `session.data['order_id']`, then from
`bs_success_order_id`, then from the signed `bs_hutko_return` cookie, validates it
through `canShowSuccessOrder()`, and loads `$order_info`, `getProducts()` and
`getTotals()`. Build the GA4 payload from exactly that already-loaded data — do
not re-query, and do not read the cart. `$this->cart` is cleared in this same
method, and the bank-return paths arrive with no cart at all; that is precisely
why the vendor implementation cannot work here.

Payload shape (GA4 `purchase`, matching what the module emits elsewhere):

- `transaction_id` — the resolved `$order_id`
- `currency` — `$order_info['currency_code']`
- `value` — order revenue, and every money field formatted with
  `$order_info['currency_value']` so a non-UAH order is not mis-stated. The
  executor decides `value` = items subtotal vs order total and **states the
  choice in the report**; keep tax and shipping in their own fields either way.
- `tax`, `shipping` — from the `getTotals()` rows already collected
  (`$order_totals`, codes `tax`, `shipping`, and note `pinta_nova_poshta` is a
  shipping-total code on this install)
- `coupon` — order coupon if present, else omit
- `items[]` — from the already-loaded order products: `item_id` = `product_id`
  (matches the module's `item_id` setting), `item_name`, `price`, `quantity`

Render it in `catalog/view/template/checkout/success.twig` as a single
`<script>ps_dataLayer.pushEventData('purchase', {...});</script>`, placed after
the existing `{{ text_message }}` (line 153), guarded so it renders only when the
payload exists.

Three guards, all mandatory:

1. **Once per order.** A reload of the success page must not emit a second
   `purchase`. The controller already keeps `bs_success_order_id` /
   `bs_success_order_at` in the session; add an explicit
   "GA4 purchase already emitted for this order id" session marker and check it.
   Session-only dedup does not survive a lost session — accept that for now and
   record it as a known limit; do not add a DB table in this patch.
2. **Never on the PAY-003 recovery path.** When `$pay003_recovery` is true the
   page is re-rendering a historical order from a `credit_order_id` link. Emit
   nothing.
3. **Never without a resolved, display-authorized order.** If
   `canShowSuccessOrder()` did not produce an order, emit nothing.

### WP2 — `begin_checkout` on the checkout page

Only after WP1 is deployed and verified. Two routes; the executor evaluates both
against the live files and **recommends one, owner decides**:

- **Option A (restore the anchor).** Put `<h1>{{ heading_title }}</h1>` back in
  `checkout.twig` and let the module's own, already-registered handler
  (`ocp5_event` id 91) inject the event. Cheapest and self-maintaining. **But**
  the uk-ua language value is `Оформлення замовлення` while the page currently
  shows `Оформити замовлення`, and `heading_title` also feeds
  `$this->document->setTitle()` — so this is a visible copy and page-title change
  and needs owner sign-off on the wording, not just on the code.
- **Option B (emit it ourselves).** Same pattern as WP1: Booster-owned snippet
  calling `ps_dataLayer.pushEventData('begin_checkout', …)` built from the cart
  contents at checkout entry. No copy change; more code to maintain.

Do not implement both.

## 5. Do not touch

- Any file under `extension/ps_enhanced_measurement/` — vendor code, overwritten
  by the next module update. The existing `// ST-2a.10` edit in its model is a
  known liability; do not add to it.
- Payment: `extension/hutko/`, PUMB, monobank, `catalog/controller/checkout/credit.php`,
  `catalog/model/checkout/credit.php`, any callback or payment-status writer.
- Order lifecycle: order creation, order status writes, `addHistory`, CRM sync.
- The cart-clearing and session-teardown block in `success.php`, and the
  `canShowSuccessOrder()` / Hutko-return-cookie authorization logic — read from
  them, do not restructure them.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`.
- Merchant feed, Product schema, JSON-LD.
- Notion and `ROADMAP_TASKS` in `dashboard/booster-dashboard.html` — Claude (chat)
  is the writer for this task.

## 6. Likely files / areas

Likely, not confirmed — the executor must verify against the actual project files
and the newest backup before editing.

```
catalog/controller/checkout/success.php          — WP1, build payload from loaded order data
catalog/view/template/checkout/success.twig      — WP1, render snippet after {{ text_message }} (line 153)
catalog/view/template/checkout/checkout.twig     — WP2, line 11 <h1>
extension/ukrainian/catalog/language/uk-ua/checkout/checkout.php — WP2 Option A only, heading_title
```

Reference state: `backup-9.3.2026_21-30-35_boosters.tar.gz` (2026-09-03 21:30).
The UI-FIX wave of 2026-09-03/04 touched `common/header.twig`,
`product/category.twig` and `product/product.twig` after that snapshot; it did
not touch any file in this handoff.

## 7. Acceptance criteria

WP1:

- `view-source:` of `index.php?route=checkout/success` after a real order contains
  `pushEventData('purchase'` **exactly once**.
- GA4 DebugView (or Realtime) shows one `purchase` whose `transaction_id` equals
  the OpenCart order id, `currency` equals the order currency, and `value`
  matches the order per the rule the executor stated.
- Reloading that success page (F5) produces **no** second `purchase` event and no
  second `pushEventData('purchase'` in the source.
- Opening a PAY-003 `?credit_order_id=` recovery link produces no `purchase`.
- A guest order and a logged-in order both emit.
- Browser console on the success page: no new errors.

WP2:

- `view-source:` of the checkout page contains `pushEventData('begin_checkout'`.
- Option A only: the visible `<h1>` and the browser tab title are exactly what the
  owner approved.

## 8. QA / smoke test

This touches the live purchase flow on a production-direct deploy with no
staging. Run `bs-checkout-smoke` in full before the owner calls it done — the
success page is the last step of the payment path and a fatal there strands a
paying customer on a blank page after money has moved.

Minimum owner-run set, on production:

- one COD order end to end, checked per §7, then cancelled in admin;
- one Hutko return, confirming the success page still renders and the order is
  still shown (this is the path that historically depends on `bs_success_order_id`
  and the return cookie);
- confirm nothing changed for a customer who reaches the success page with an
  expired session — the page must degrade to its current behaviour, not error.

Do not mark GA4 numbers as proof on the day of deploy: GA4 standard reports lag,
DebugView/Realtime is the same-day evidence.

## 9. Rollback note

Standard patch backup: `_patch_backups/<patch>-<ts>/` must contain the pre-patch
copies of every file the patch writes. To revert WP1:

```
cp _patch_backups/<patch>-<ts>/catalog/controller/checkout/success.php  ~/public_html/catalog/controller/checkout/success.php
cp _patch_backups/<patch>-<ts>/catalog/view/template/checkout/success.twig ~/public_html/catalog/view/template/checkout/success.twig
```

Rollback trigger: any error on the success page, an order that does not render,
or a duplicated `purchase`. Reverting costs only the GA4 event — it does not
affect orders, payments or fiscalization, so revert first and diagnose after.

## 10. Delivery and recommended status after execution

Delivery path: patch file into `patches/`, owner uploads it to `~/public_html`
and runs `php <patch>.php`. The executor never commits, pushes, uploads or
deploys. Patch files must be PHP 8.0-safe — the host CLI has no 8.1+ binary, and
`never` / `enum` / `readonly` in a patch die before any guard runs.

Report into `diagnostics/TECH-015_<slug>_report_20260905.md` using
`templates/codex-report-template.md`, and state in it the `value` rule chosen for
the payload and the WP2 option recommended.

Recommended status after WP1+WP2 are deployed and owner QA passes: TECH-015 stays
`In progress` until the owner confirms a real `purchase` with correct revenue in
GA4; only the owner authorizes closure, and Claude (chat) performs the Notion and
dashboard write.
