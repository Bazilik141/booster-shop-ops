# Diagnostic — GA4 "Playful Sparkle – Enhanced Measurement": purchase not tracked

Date: 2026-09-05
Surface: Claude (chat) — audit only. No patch, no deploy, no Notion write.
No roadmap row exists for this defect yet (owner to authorize ID).

## Evidence source

All statements below are read from the owner-provided cPanel backup
`backup-9.3.2026_21-30-35_boosters.tar.gz` (site state 2026-09-03 21:30 EEST)
and its DB dump `mysql/boosters_ocart49.sql` (prefix `ocp5_`).
Not verified against production HTML — see "Owner-side evidence still required".

Platform: OpenCart `VERSION = 4.1.0.3`, `config_theme = basic`.
Module: `ps_enhanced_measurement` v1.0.9, `analytics_ps_enhanced_measurement_status = 1`,
implementation `gtag`, tag `G-283QW89TX8`, forced currency `UAH`, `item_price_tax = 1`,
`tracking_delay = 800`. All 42 module events are present and enabled in `ocp5_event`.
So the module is installed and configured correctly. The defect is not configuration.

## How the module works (the mechanism that breaks)

The module does not use a data layer emitted by controllers. It injects its
`<script>` snippets by **literal `str_replace` against the live `.twig` source**
(`catalog/model/analytics/ps_enhanced_measurement.php` → `replace*Before()`,
applied in `catalog/controller/.../ps_enhanced_measurement.php::replaceViews()`).
Every anchor string is a fragment of the **stock OpenCart 4 template**.
Any Booster template that was rewritten by our own waves (ST-2b, ST-2c, RD-13.1,
CHECKOUT-00x, PAY-00x, UI-FIX) silently loses its injection point: no PHP error,
no log line, the event simply never reaches gtag.

## Root cause A — `purchase` is structurally impossible (P0)

Two independent breaks, either one alone is fatal.

1. **Wrong template is rendered.** `catalog/controller/checkout/success.php`
   (Booster-customized, R-11 / ST-2b.2 / CHECKOUT-007A / PAY-003) ends with
   `$this->response->setOutput($this->load->view('checkout/success', $data));`.
   Stock OC 4.1 renders `common/success`. The module registers its purchase
   injector on the trigger `catalog/view/common/success/before`
   (`ocp5_event` id 93). That trigger never fires on the order-success page.
2. **No other purchase path exists.** The catalog-side controller event
   (`catalog/controller/checkout/success/before`, id 92) does run — it builds
   `$this->session->data['ps_purchase']` and stores the `_ga` client id — but the
   only consumer of `ps_purchase` is the view injector from (1). The admin-side
   Measurement Protocol sender (`eventAdminControllerSaleOrderAddHistoryAfter`)
   sends **only** lead events (`working_lead`, `close_convert_lead`,
   `close_unconvert_lead`, `disqualify_lead`) and refunds — never `purchase` —
   and its three `*_lead_status` settings are absent from `ocp5_setting`, so it
   is inert as well.

`catalog/view/template/checkout/success.twig` does still contain the literal
`{{ text_message }}` the injector looks for. That is a coincidence: the anchor is
present but the event that would use it is bound to a different route.

Consequence: GA4 has received **zero `purchase` events** from this site for as
long as the customized success controller has been live. Revenue, transactions,
ecommerce funnel completion and any Ads purchase conversion are all empty by
construction, not under-reported.

Secondary, still relevant after A is fixed: the payload built by event id 92 is
read from the **live cart** (`model_checkout_cart->getProducts()`), not from the
order. It also returns early when `session.data['order_id']` is unset. Both are
hostile to our real return paths — Hutko / PUMB / monobank returns, the PAY-003
`credit_order_id` recovery link, and any return in a browser that lost the
session cookie. A fix that only restores the template hook will still lose
purchases on bank-return flows.

## Root cause B — funnel stages killed by rewritten templates

Anchor presence checked mechanically against the live templates in the backup.

| GA4 event | Setting | Anchor status | Verdict |
|---|---|---|---|
| `page_view` / base gtag | on | `</head>` in `common/header.twig` present | works |
| `view_item` | 1 | `<button type="submit" id="button-cart"` present | works |
| `view_item_list` | 1 | `{% if products %}` present in category/search/special/manufacturer_info | works |
| `select_item` | 1 | `<a href="{{ href }}">` in `product/thumb.twig` present | partial — name link only |
| `add_to_cart` | 1 | product page `if (json['success']) {` present; tile button anchor **absent** | partial — product page only, listing tiles lost |
| `remove_from_cart` | 1 | `cart_list.twig` `{{ edit }}` / `{{ product.remove }}` present; mini-cart button anchor absent | works on cart page, lost in header mini-cart |
| `view_cart` | 1 | `<div id="shopping-cart">{{ list }}</div>` present | works |
| `search` | 1 | search template hook present | works |
| `sign_up` | 1 | `checkout/register.twig` `if (json['success']) {` present | works |
| `begin_checkout` | 1 | `<h1>{{ heading_title }}</h1>` **absent** — `checkout.twig` line 11 is hardcoded `<h1>Оформити замовлення</h1>` | **dead** |
| `add_payment_info` | **0** | n/a | off by configuration |
| `add_shipping_info` | **0** | n/a | off by configuration |
| `purchase` | 1 | see Root cause A | **dead** |
| `login`, `add_to_wishlist`, `view_promotion`, `select_promotion`, `file_download`, `generate_lead`, `qualify_lead` | 0 | n/a | off by configuration |

`<body>` is also missing from `common/header.twig`, but that anchor only carries
the GTM `<noscript>` and this install runs `gtag`, so it is harmless today. It
becomes a break if the implementation is ever switched to GTM.

Also note: the `</head>` injection block in the module's model already contains a
Booster comment (`// ST-2a.10: keep analytics from breaking storefront flows`).
The vendor module has been patched in place before — a marketplace reinstall or
version upgrade will silently drop that guard.

## Why the funnel looks "wrong" rather than "empty"

`view_item` and `add_to_cart` fire, `begin_checkout` and `purchase` never do. Any
GA4 funnel or Ads optimization built on this data shows traffic collapsing between
cart and checkout and a 0% conversion rate. That shape is an artefact of the
instrumentation, not of customer behaviour, and it must not be used as evidence
for UX or pricing decisions until the events are restored.

## Owner-side evidence still required

The backup proves the code path. These four items close the loop and cannot be
read from the repository:

1. Confirm `G-283QW89TX8` is the GA4 property/stream the owner actually reads,
   and whether `purchase` is registered there as a key event.
2. Confirm no patch touching `catalog/controller/checkout/success.php`,
   `catalog/view/template/checkout/*`, `catalog/view/template/common/header.twig`
   or the module was deployed after 2026-09-03 21:30.
3. GA4 → Reports → Engagement → Events, last 28 days: the observed counts for
   `view_item`, `add_to_cart`, `begin_checkout`, `purchase`. Expected if this
   diagnosis is right: first two non-zero, last two exactly zero.
4. One live test order (COD, no payment) with GA4 DebugView open, plus
   `view-source:` of the resulting `index.php?route=checkout/success` page. The
   page source must contain no `ps_dataLayer.pushEventData('purchase'` string.

Item 4 is the only destructive-ish step (it creates a real order row) and is the
owner's call.

## Fix directions (not authorized, not designed)

Recorded so the handoff has a starting point. Each needs its own scoping.

- **Do not** re-point the module at `common/success` by editing the vendor
  extension. The next module update overwrites it.
- Prefer a Booster-owned server-rendered purchase snippet on the customized
  success page, built from the **order** (`model_checkout_order`), not the cart,
  keyed on the order id the success controller already resolves
  (`bs_success_order_id`, Hutko return cookie, PAY-003 `credit_order_id`), with a
  once-per-order guard so an F5 does not double-count.
- Consider Measurement Protocol from the order-status writer as the durable path
  for bank-return flows, using the `_ga` client id the module already persists.
  This requires the GA4 API secret already stored in module settings — do not
  print or copy that value into any patch, report or chat message.
- `begin_checkout` needs its own anchor decision: either restore a stable hook in
  `checkout/checkout.twig` or emit the event from Booster code.
- Decide deliberately whether `add_payment_info` / `add_shipping_info` stay off.

---

## Owner-supplied evidence, 2026-09-05 — diagnosis confirmed

**Property identity (item 1) — resolved.** GA4 account `BoosterShop`, property
`https://boostershop.website/`, property ID `533255247`. The owner could not find
a "counter" matching `G-283QW89TX8` because that string is the **data stream**
measurement ID (Admin → Data streams), not the property ID. Both exports carry
the same property, so the tag in the module and the reports the owner reads are
the same property. No mismatch.

**GA4 export, 2026-08-08 … 2026-09-04 (28 days).** Platform `web`:
97 active users, 84 new, 471 engaged sessions, 12 169 events,
**Основні події (key events) = 0**, **Загальний дохід = 0**.
"Перспективні покупці" and "Потенційні клієнти, які здійснили конверсію" are 0
on every one of the 28 days. This is exactly the shape Root cause A predicts:
heavy event volume, zero conversions, zero revenue.

Two items to keep in view, neither contradicting the diagnosis:
- Audience `Purchasers` shows 1 active user in the window while revenue is 0.
  Unexplained; likely legacy audience membership or a non-`purchase` signal.
  Do not treat it as a working purchase event without its own check.
- 1 session sourced from `pay.hutko.org` in the window — at least one real
  payment return happened and still produced no purchase event.
- Session sources overall: `chatgpt.com` 136 vs `google` 56. Out of scope here,
  but it means the acquisition picture is not what an Ads-only view would show.

**Base tag confirmed live.** The owner's page source shows the module's `</head>`
injection intact, including the Booster `// ST-2a.10` guard and the
`ga4_tracking` flag map, which matches `ocp5_setting` exactly
(`purchase:true`, `begin_checkout:true`, `add_payment_info:false`,
`add_shipping_info:false`). So the base gtag layer works and only the per-event
template injections are at fault — as diagnosed.

**Item 2 (post-backup deploys) — partially answered.** Owner recalls only visual
fixes and the credit menu. `git log` for 2026-09-03…04 confirms the UI-FIX wave
touched `common/header.twig`, `product/category.twig` and `product/product.twig`
after the backup snapshot. `catalog/controller/checkout/success.php` and the
checkout templates were **not** touched, so Root cause A stands unchanged. The
anchor state of those three product/header templates must be re-checked against
a fresh backup before the funnel table above is treated as current.

**Test-instruction correction.** Searching the success page source for the bare
word `purchase` is not a valid check: the string appears on **every** page inside
the `ga4_tracking` config map. The decisive string is `pushEventData('purchase'`.

## Production confirmation, 2026-09-05 — Root cause A proven live

Owner ran a real test order. The rendered order-success page contains **no**
`pushEventData('purchase'` string. Root cause A is no longer an inference from
the backup: it is confirmed on production.

Module configuration is **not** at fault, confirmed from the owner's admin
screenshots of the extension:
- Measurement Implementation `Global Site Tag - gtag.js`, Google Tag ID
  `G-283QW89TX8`, Measurement Protocol API Secret filled, Tracking Delay 800.
- Track Purchase Event **ON**. Also on: add_to_cart, remove_from_cart, search,
  view_item_list, select_item, view_item, view_cart, begin_checkout, sign_up.
  Off: add_payment_info, add_shipping_info, login, add_to_wishlist, promotions,
  file_download, generate_lead, qualify_lead.
- Ads Conversion Tracking disabled, all labels empty. GCM disabled (it requires
  GTM and this install is gtag, so this is correct as-is).
- `Lead Associations` is a required field with no order status selected in any of
  its three rows. This matches the missing `*_lead_status` rows in `ocp5_setting`
  and confirms the admin Measurement Protocol lead sender is inert. It has no
  bearing on `purchase` — that sender never emits `purchase` in any case.

Data stream identity confirmed: stream `BoosterShop`,
`https://boostershop.website`, stream ID `14378966327`, measurement ID
`G-283QW89TX8` — the same tag rendered on the site.

Nothing in the module's settings can restore `purchase`. The fix is code: the
site's own success page must emit the event, or the module must be given the
template hook it expects. Both need a scoped task, an owner-authorized roadmap
row, and an executor handoff.
