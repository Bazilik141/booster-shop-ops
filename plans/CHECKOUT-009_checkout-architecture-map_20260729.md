# CHECKOUT-009 Checkout Architecture Map

Date: 2026-07-29  
Codex config: model=Sol · effort=xhigh

## Outcome

The current production failure is a checkout-state ordering defect, not a Nova
Poshta quote-calculation failure and not two unrelated guest/logged-in bugs.

`checkout/coupon.summary` is a read-only bootstrap query, but
`catalog/view/javascript/checkout-reskin.js:351-392` reports every coupon
response as a coupon mutation. That unconditionally calls
`checkout-state.js:251-255`, which invokes `couponChanged()` at
`checkout-state.js:239-249`. The mutation path increments the global revision,
clears display/payment readiness and starts a recovery quote with
`autoSelect:false`.

If the initial or address-triggered shipping auto-save belongs to the previous
revision, `shipping_method.twig:53-55` discards its successful response. The
recovery quote cannot restore an empty selection because
`shipping_method.twig:150-168` saves only when auto-select is allowed or a
current code already exists. The result is the visible NP address with:

```text
#input-shipping-code        = ""
#input-shipping-display-text = ""
#input-payment-code         = ""
```

Every downstream readiness gate then correctly reports “delivery not ready”
from incorrect/empty client state.

The owner-directed audit boundary is respected: this document maps the live
system and root cause; it does not implement a correction, deploy, change DB
state, commit or push.

## Evidence and confidence

### Current evidence

- Post-deployment live archive:
  `CHECKOUT-009-live-evidence-20260729-155027.tar.gz`
- SHA-256:
  `2990f6ab406786b8eae4d104da98aa3411a39555d35cd58ed540674549fffc79`
- Archive capture:
  `2026-07-29T12:50:27Z`, `/home2/boosters/public_html`, PHP `8.0.30`
- Archive path safety: 287 entries, zero unsafe traversal entries
- Production guest reproduction in a new anonymous browser session:
  one ₴700 product, test-only nonpersonal contact data, Kharkiv region/city,
  Nova Poshta warehouse 1; no CAPTCHA and no order
- Production asset inventory:
  `checkout-state.js?v=r135-cart-refresh-20260725` and
  `checkout-reskin.js?v=r135-cart-refresh-20260725`
- DB event/settings snapshot and `_patch_backups` directory listing
- Repository history used only to explain active markers

### Explicit gaps

1. `theme_prepare=failed`: the evidence collector selected a nonexistent
   `theme` column from the theme table. The absence of DB Twig overrides is not
   established.
2. No authenticated session/HAR was supplied. The logged-in path is supported
   by owner screenshots/description and the live source path, but not by an
   independently captured request timeline.
3. The browser tool exposed rendered DOM and loaded asset URLs, not response
   bodies for all checkout XHRs. The exact network sequence should be frozen in
   owner HAR evidence before an implementation is approved.
4. No final order was created. CRM, Telegram, payment transport and success
   side effects are source/event dependencies, not production smoke proof.

## 1. Component inventory

Paths and line numbers refer to active files in the supplied live archive.

| Component | Ownership | Reads | Writes / clears |
|---|---|---|---|
| `catalog/controller/checkout/checkout.php` | Checkout page composition; credit UI parameters; receiver fallback | config, customer/session, cart/product credit context | Twig data only |
| `catalog/view/template/checkout/checkout.twig` | Page shell, guest autosave bridges, readiness/confirm UI, cached summary/deferred confirm | hidden shipping/payment mirrors, offer checkbox, credit gate, register responses | DOM mirrors, register autosave requests, confirm UI, coordinator calls |
| `catalog/view/javascript/checkout-state.js` | Current client orchestration and revision ownership | hidden shipping/payment inputs, coordinator callbacks | revision, clears readiness mirrors, quote/payment/totals calls |
| `catalog/view/javascript/checkout-reskin.js` | UI enhancement, promo client, delivery/payment/summary presentation, saved NP hydration | DOM, coupon responses, hidden mirrors, server-rendered summary | promo requests, summary cache, UI state; currently mis-signals every coupon response |
| `catalog/controller/checkout/register.php` | Guest customer/payment/shipping session writer | posted identity/address/NP fields, customer-group rules, previous fingerprints | `session.customer`, payment/shipping addresses; clears methods only when fingerprints change; returns `checkout_state` |
| `catalog/view/template/checkout/register.twig` | Stock guest form and legacy stock handlers | controller data | guest form requests; native account mode disabled |
| `catalog/controller/checkout/shipping_address.php` | Logged-in/new structured shipping-address writer and saved-address selector | account addresses, NP refs, selected address id | account address when authorized; `session.shipping_address`; clears shipping/payment methods |
| `catalog/view/template/checkout/shipping_address.twig` | Saved/new address UI and stock requests | saved addresses | `shipping_address.save` / `.address` requests |
| `extension/PintaNovaPoshtaCod/catalog/controller/payment/pinta_nova_poshta_cod.php` | Injects NP form/JS/CSS; legacy after-hook now no-op | route/view response, module status | no address write in `alterRedirectShippingAddressSave()` (`ACC-002F`) |
| `extension/PintaNovaPoshtaCod/catalog/view/template/shipping/js_checkout_shipping_address_form.twig` | NP type/cascade form and logged-in form submit | structured NP form values, `Chain` | `shipping_address.save`; also starts a duplicate delayed shipping reload |
| `extension/PintaNovaPoshtaCod/catalog/controller/shipping/pinta_nova_poshta.php` | NP lookups/form fragments | NP API/module config | JSON/form data |
| `extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php` | Quote calculation and display-only tariff contract | shipping address, cart, coupon/payable total, ₴2,000 rule | quote with zero payable cost plus display tariff metadata |
| `catalog/controller/checkout/shipping_method.php` | Canonical shipping quote/save session boundary | customer/payment/shipping session, shipping model quotes | `session.shipping_methods`; on save `session.shipping_method`; clears payment; returns summary |
| `catalog/view/template/checkout/shipping_method.twig` | Shipping quote renderer and client save adapter | quote JSON, hidden current code, revision | hidden shipping mirrors; `shipping_method.quote`/`.save`; coordinator callbacks |
| `catalog/controller/checkout/payment_method.php` | Payment-method availability/save, offer/account/comment actions | server shipping readiness, cart/credit context, config | `session.payment_methods`, `session.payment_method`, comment/agreement/account state |
| `catalog/view/template/checkout/payment_method.twig` | Basic payment choices, credit preview/drawer, payment save, comment UI | hidden shipping/payment mirrors, method response, credit gates | `payment_method.getMethods`/`.save`/`.comment`; payment mirrors |
| `extension/mono_chast/catalog/model/payment/mono_chast.php` | Mono availability and SimpleCheckout isolation | config/cart/payment context | method option only for stock checkout |
| `extension/mono_chast/catalog/controller/payment/mono_chast.php` | Mono confirmation/transport entry | selected Mono option/order context | bank/transaction path after final confirmation |
| `extension/pumb_credit/...` | Disabled PUMB skeleton/preview | disabled/no safe status row in supplied settings | none in active checkout |
| `catalog/controller/checkout/coupon.php` | Read-only summary and coupon apply/remove JSON routes | session coupon, cart totals, First15 state | coupon session only for apply/remove; summary is a query |
| `catalog/model/checkout/booster_coupon.php` | Coupon and durable First15 helper | customer custom fields/order-usage state | consumes qualifying First15 flag only in its defined lifecycle |
| `catalog/controller/checkout/confirm.php` | Read-only summary render plus final server validation/order preparation path | customer/address/shipping/payment/agreement/CAPTCHA/session/cart | order data only through the trusted final path; receiver override projection |
| `catalog/view/template/checkout/confirm.twig` | Server totals and selected payment controller fragment | prepared order totals/data | payment controller UI |
| `catalog/controller/checkout/success.php` and `success.twig` | One-shot success messages, receiver cleanup, display tariff | order/session flags | consumes one-shot flags |
| common cart JS/templates | Mini-cart fragments and mutation signal | cart response | cart quantity/remove requests; emits real cart-change event |
| `catalog/model/checkout/order.php` | Order persistence/history plus queued side-effect handoff | final order data/status | order tables; queues Telegram/CRM after appropriate boundary |
| `system/library/booster_async_queue.php` | Deferred external side effects | queued payload | queue files/jobs |
| `extension/telegram_notify/.../telegram.php` | Telegram notification consumer | order after-history event | external notification asynchronously |
| `system/library/booster_crm_sync.php` | CRM sync worker | order/customer data | external CRM sync asynchronously |
| `system/library/session/{file,db,redis}.php` | Session persistence backends | configured session id/backend | checkout session lifetime |
| OpenCart modification/template caches | Compiled route/template runtime | file/DB override source | cached generated PHP/template output |
| DB `event` rows | Event wiring for analytics, CAPTCHA, mail, Telegram, Pinta | route triggers | invokes registered handlers |
| DB theme table | Possible Twig source override | route/code | can supersede file Twig; currently unverified due schema mismatch |

## 2. State model

### Authoritative and projected state

| State | Current writer(s) | Clearer(s) | Reader(s) | Lifetime / authority |
|---|---|---|---|---|
| `session.customer` | `register.save` or logged-in customer bootstrap | session/logout | quote/payment/confirm controllers | Server-authoritative for current session |
| `session.payment_address` | `register.save` / payment-address controller | address changes/session | shipping/payment/confirm | Server-authoritative |
| `session.shipping_address` with `address_id` and `bs_np_v1` | `register.save`, `shipping_address.save`, `.address` | replacement/address change | shipping quote, confirm, account creation | Server-authoritative |
| Account address row/custom field | account/shipping address model | explicit edit/delete/repair | logged-in bootstrap/hydrator | Durable DB state |
| `session.shipping_methods` | `shipping_method.quote` | address/cart/coupon-relevant invalidation | `shipping_method.save` validates posted code | Server-authoritative quote set for current context |
| `session.shipping_method` | `shipping_method.save` | address/cart/coupon transitions and method save logic | payment methods, confirm, success | Server-authoritative selected delivery |
| `session.payment_methods` | `payment_method.getMethods` | shipping/address changes | payment save | Server-authoritative availability set |
| `session.payment_method` | `payment_method.save` | shipping/address/payment-context changes | confirm/payment controller | Server-authoritative selected payment |
| `session.coupon` | coupon apply/remove or First15 helper | remove/session lifecycle | totals, shipping free-threshold logic, confirm | Server-authoritative |
| guest agreement/account/newsletter/comment fields | dedicated checkout endpoints/session | session/order lifecycle | confirm/account/order | Server-authoritative after save |
| receiver override | receiver endpoint/session | success cleanup | confirm/order projection | One-checkout server state |
| `revision` in `checkout-state.js` | coordinator transition functions | page reload initializes `0` | quote/save/payment/totals stale guards | Client concurrency token, not business authority |
| `totalsRequest` / `totalsDirty` | coordinator | abort/response | totals cache refresh | Client request state |
| `#input-shipping-code` | shipping Twig initial session render; `saveShipping`/`shippingSaved` | address transition | payment gate, confirm gate, quote renderer | Client projection currently treated as readiness authority |
| `#input-shipping-method` | shipping Twig/save | address transition | summary/confirm labels | Client display projection |
| `#input-shipping-display-text` | shipping save response/session render | address/cart/coupon transitions | summary and success presentation | Display-only projection |
| `#input-payment-code` | payment Twig session render/save | shipping/address/payment changes | confirm gate | Client projection currently treated as readiness authority |
| `#input-payment-method` | payment Twig/save | same | summary labels | Client display projection |
| `bsPay001CreditGate` | payment preview renderer | method rerender | confirm gate/hint | Client projection of server/cart-owned eligibility |
| cached summary HTML | shipping save response, confirm query, coupon response bridge | replacement | sidebar/deferred confirm | Client display cache; never order authority |
| `window.bsCheckoutFreeShippingRule` | coupon response | page lifecycle/replacement | summary progress renderer | Client display projection of server rule |
| Pinta form hidden refs/signature | Pinta cascade/sync | type/address changes | `shipping_address.save`, autosave dedupe | Client form state until server validation |
| Pinta/register `bsSaving`, `bsLastSaved`, pending signature | form handlers | completion/change | autosave guards | Client transient request state |
| browser HTTP/cache keyed by script URL | browser/CDN/server cache policy | version key/cache expiry | runtime JS loader | Can keep obsolete state machine across deployment |
| modification/template cache | OpenCart | owner cache clear | PHP/Twig runtime | Can keep stale generated source |

The architectural fault is that server readiness and client projections are
not one transaction. The hidden code is emptied synchronously, server saves
asynchronously, and several independent callbacks can advance the revision or
start a second quote between those steps.

## 3. Event and call graph

### Page bootstrap

```mermaid
sequenceDiagram
    participant Page as checkout.twig
    participant Promo as checkout-reskin.js
    participant State as checkout-state.js
    participant Quote as shipping_method.quote
    participant Save as shipping_method.save

    Page->>Promo: enhance/init
    Promo->>Promo: coupon.summary queued
    Page->>State: bootstrap()
    State->>Quote: autoSelect=true, revision=0
    Promo-->>State: totalsChanged("coupon") for summary
    State->>State: couponChanged(): revision=1
    State->>Quote: autoSelect=false, resaveCurrent=true, revision=1
    Quote-->>Save: first quote auto-save, revision=0
    Save-->>State: success response
    State->>State: stale guard rejects revision 0
    Quote-->>State: revision 1 quotes rendered
    Note over State,Quote: current code is empty; autoSelect=false; no save
```

OpenCart `Chain` serializes attached steps, but serialization does not make the
semantic classification correct. The read-only summary still advances
revision and invalidates readiness.

### Guest address completion

1. Customer fills receiver/contact and NP fields.
2. Form sync writes duplicate NP form-associated fields (`RD-13.1A`).
3. `checkout/register.save` validates and writes customer/payment/shipping
   session.
4. It compares address fingerprints. If shipping changed, it clears shipping
   and payment method/session quote sets and returns
   `checkout_state.shipping_changed=true` (`register.php:716-732`).
5. Global `ajaxSuccess` runs:
   - first `bsCheckoutRefreshPromoCouponSummary({quiet:true})`
     (`checkout.twig:1539-1542`);
   - then `bsCheckoutState.addressSaved()` (`1544-1549`).
6. `addressSaved()` increments revision, clears client shipping/payment and
   starts auto-select quote.
7. The summary response is incorrectly treated as a coupon mutation, advances
   revision again and starts non-auto-select recovery.
8. The correct auto-save response is stale; recovery has no current code.
9. Shipping/payment hidden mirrors remain empty and confirm stays disabled.

### Logged-in default/saved-address path

- On first render, `shipping_address.index()` can preselect the default
  account address. The same bootstrap summary/quote ordering applies, so a
  visually complete saved address can have no persisted/mirrored method.
- Page reload repeats the deterministic ordering and does not guarantee
  recovery.
- Choosing a different saved address later calls
  `shipping_address.address`. At that time the initial promo summary has
  settled. The route clears server methods and global `ajaxSuccess` calls
  `addressSaved()` without a new register-triggered summary before it. The
  auto-select/save can therefore finish on the current revision. This explains
  the owner-reported logged-in workaround.
- The Pinta new-address form is less deterministic because its success handler
  also schedules a second quote after 250 ms while the global listener starts
  another address transition.

### Manual shipping-method selection

1. `shipping_method.quote` returns current quote groups.
2. `shipping_method.twig` renders radios.
3. A user change calls `beginShippingSelection()`, increments revision and
   clears payment/display tariff.
4. `shipping_method.save` validates the posted code against
   `session.shipping_methods`, writes `session.shipping_method`, clears payment
   state and returns `summary_html`.
5. On current revision, the Twig sets hidden mirrors and calls
   `shippingSaved()`, which caches totals and loads payment methods.

### Coupon apply/remove

1. Promo client posts `coupon.apply` or `.remove` through `Chain`.
2. Server changes coupon session and returns summary/free-shipping data.
3. Current code invokes the same `totalsChanged('coupon')` used by read-only
   summary.
4. `couponChanged()` preserves a nonempty current code, requotes and asks
   `renderShippingMethods()` to re-save `currentQuote`.
5. This mechanism is valid for actual apply/remove, but invalid for summary.

### Mini-cart quantity/remove

1. Common cart code changes cart state and refreshes fragments.
2. A real mutation emits the cart-update event.
3. `cartChanged()` clears display/payment, retains the selected shipping code,
   requotes with `autoSelect:false,resaveCurrent:true`.
4. If the current quote remains available, it is saved and returns atomic
   summary. A display-only mini-cart refresh after shipping save does not emit
   another cart-change event.

### Payment selection and confirm

1. Payment preview may render three basic options plus muted credit before
   shipping readiness.
2. With nonempty shipping code, `payment_method.getMethods` obtains the
   canonical server set.
3. `payment_method.save` writes the selected method and the hidden payment
   mirror.
4. Deferred confirm renders ready only when shipping, payment, agreement and
   credit gates pass.
5. The trusted click runs account opt-in/receiver/CAPTCHA steps as applicable,
   then the selected payment controller. Read-only summary refreshes must never
   call the order-write confirm action.

## 4. Readiness gates

| Gate | Exact location and condition | Inputs | First-render/stale failure |
|---|---|---|---|
| Shipping quote server prerequisites | `shipping_method.php:53-68`: customer set; payment address if configured; `shipping_address.address_id` if cart ships | Server session/cart | Incomplete guest is correctly rejected |
| Shipping save validation | `shipping_method.php:102-127`: prerequisites plus posted code exists in current `session.shipping_methods` | Server quote set + posted code | Quote set cleared by another transition makes save invalid |
| Shipping save stale guard | `shipping_method.twig:53-55`: current revision must equal request revision | Client revision | Correct server save response can be ignored after read-only summary increments revision |
| Shipping client readiness | `checkout.twig:877-879` and `payment_method.twig:68-69`: `!!$('#input-shipping-code').val()` | Hidden client code | Empty after stale response even if address/visible NP type is complete |
| Auto-select | `shipping_method.twig:150-152`: `options.autoSelect !== false && firstQuote && !current` | Quote, option, hidden current | Recovery from coupon path explicitly has `autoSelect:false` |
| Re-save current | `shipping_method.twig:153-158`: `current && options.resaveCurrent && currentQuote` | Hidden code and fresh quote | Cannot restore a selection after the code is already empty |
| Payment method availability | payment controller server validation; payment Twig additionally returns/blocks without shipping code at `359-361,411-413` | Server shipping session and hidden client code | Client can block request even if server was saved but stale response was rejected |
| Payment client readiness | `checkout.twig:881-882`: nonempty hidden payment code | Hidden client code | Always cleared by address/coupon/cart transition until shipping recovery succeeds |
| Guest agreement | `checkout.twig:887-890`: checkbox absent or checked; server guest confirm also requires agreement | DOM + session | Correctly false only for guest unchecked state |
| Credit minimum/preorder | `checkout.twig:894-917`, payment server option metadata | selected credit option, payable/cart contract | Correct blocks must remain; false shipping readiness currently masks them with the address hint |
| Confirm composite | `checkout.twig:916-917`: shipping && payment && agree && !creditBlocked | Client projections | Returns false from empty shipping/payment mirrors |
| Confirm reason text | `checkout.twig:920-945` | same gates | Shows “fill delivery and choose payment” even while NP address/type looks selected |
| Deferred button | generated at `checkout.twig:947-975`; disabled when composite false | composite gate | Visible disabled confirmation is downstream symptom |
| Server final confirm | `confirm.php`: cart/customer/address/shipping/payment/agreement/CAPTCHA and payment-specific checks | Server session/cart/request | Remains essential; must not be weakened to compensate for client failure |

## 5. Guest versus logged-in divergence

| Point | Guest | Logged in | Consequence |
|---|---|---|---|
| Customer/session creation | `register.save` builds guest customer/payment/shipping session | Existing customer/session | Guest must successfully autosave identity and address first |
| Address source | Current form values/NP refs | Default/saved account address or new form | Logged-in can begin visually complete |
| Address success signal | `register.save` callback refreshes coupon summary, then calls coordinator | Saved-address route calls coordinator; new Pinta form also schedules legacy reload | Guest deterministically recreates the harmful ordering |
| Agreement | Required checkbox | Absent and treated ready | Not the root cause of logged-in failure |
| Account opt-in | Optional “save data” pre-confirm path | Not present | Adjacent, not readiness root cause |
| Saved-address workaround | None | Changing to another saved address after bootstrap can start a clean transition | Explains owner observation |
| CAPTCHA | Final guest gate | Normally absent | Not reached while shipping/payment mirrors are empty |
| First15 | May create durable eligibility after opted-in order | Can auto-apply on later eligible checkout | Summary bootstrap must stay non-mutating for both |

## 6. Patch archaeology and accumulated compensation

### Causal lineage

1. `CHECKOUT-004` introduced a read-only promo summary on page initialization
   and after register save, intentionally avoiding the order-write endpoint.
   Its diagnostics explicitly bumped the JS cache key.
2. `ST-2c.2` documented an earlier ordering defect:
   `register.save` immediately refreshed summary while shipping reload/save
   followed; a `busy` guard could drop the later refresh.
3. `ST-2c.3` introduced the revision coordinator. The patch removed the
   coalesced-summary distinction and changed promo rendering to call
   `totalsChanged('coupon')` for every response
   (`patches/ST-2c.3...php:1239-1324`).
4. `ST-2c.4` serialized checkout writes and made shipping save return its
   summary on one server-session boundary. This is sound but does not correct
   misclassified summary events.
5. `ST-2c.5` made NP tariff display-only and added display-text invalidation.
6. `ST-2C-COUPON-SHIPPING-20260728` made a coupon transition requote and
   re-save the current method. The live implementation still treats every
   source `"coupon"` event as a mutation
   (`patches/...validated...php:77-100`).
7. `ST-2C-MINICART-SHIPPING-20260728` copied the preserve/requote/resave pattern
   for real cart mutations.
8. `ST-2C-MINICART-THRESHOLD-ALIGNMENT-20260729` aligned the progress basis but
   changed `checkout-reskin.js` without changing the live query key.

### Duplicated/competing writers

- `checkout-state.addressSaved()` owns quote initiation, but Pinta success also
  calls `bsCheckoutLoadShippingMethods()` after a 250 ms timer.
- Global `ajaxSuccess` uses a URL substring to call `addressSaved()` for the
  same Pinta/stock success.
- `register.save` callback starts promo summary before address transition.
- `checkout-reskin.render()` cannot distinguish summary from apply/remove and
  sends all three to the mutation handler.
- Hidden inputs are written both directly in Twig handlers and again by
  coordinator callbacks; they are also read as authority by multiple gates.

### Timing compensation and magic values

- Pinta `setTimeout(..., 250)` is an undocumented ordering surrogate.
- `checkout.twig` mutation enhancer uses 40 ms at `1568-1571`; this is UI
  enhancement scheduling, not business readiness, but remains a timing
  mechanism to account for.
- Historical ST-2c.2 used a queued zero-delay summary refresh, later removed.
- The ₴2,000 threshold is a business value and is server-owned/documented.
- 3/4/5 credit terms and credit minimum/preorder rules are legitimate
  PAY-001 contracts, not cleanup candidates.

### Dead/legacy logic

- Pinta `alterRedirectShippingAddressSave()` remains registered in DB but is a
  deliberate no-op under `ACC-002F`; preserve until event registration is
  separately retired with owner approval.
- The Pinta delayed client reload is legacy after the coordinator and should
  not coexist with the coordinator in the target design.
- The old cache-bust comment says the assets were bumped after earlier fixes,
  while the actual key still predates 2026-07-28/29 changes.

## 7. Adjacent process dependencies

| Process | Dependency on checkout state | Preservation requirement |
|---|---|---|
| NP quoting | Requires canonical server address and current cart/coupon context | One quote transaction per address/cart/coupon mutation |
| ₴2,000 free shipping | Uses post-discount payable amount; cart/coupon changes must requote | Test both directions around threshold after coupon and mini-cart changes |
| Display-only Pinta tariff | Stored in shipping method metadata but excluded from payable total | Preserve zero payable cost and visible estimate |
| Coupon/First15 | Summary query, apply/remove mutation, durable next-order lifecycle | Summary must not mutate readiness; manual coupon must not be replaced |
| Mini-cart | Real mutations invalidate quote context | Preserve one event per mutation and avoid fragment-refresh loops |
| Basic payments | Depend on server shipping selection and client readiness projection | Card/COD/IBAN selection/save/confirm unchanged |
| Mono credit | Depends on shipping readiness, cart amount, preorder guard and terms | Fix false address block without weakening minimum/preorder gates |
| PUMB | Disabled preview/skeleton | Must remain unselectable and transport-disabled |
| Confirm/order write | Depends on canonical server shipping/payment plus legal/CAPTCHA gates | No summary/bootstrap order writes; one trusted final action |
| Order comment | Session data independent of method rerenders | Preserve through state refreshes |
| Account opt-in/newsletter | Guest pre-confirm/account path | Preserve explicit consent and structured NP handoff |
| Telegram | After-history asynchronous side effect | State consolidation must not move it into checkout response |
| CRM sync | Deferred order-side effect | No checkout-readiness dependency added; failure remains nonblocking |
| Analytics | Registered view/save observations | Avoid duplicate save events that inflate measurements |

## Root cause by path

### Guest

Primary producing line:

```text
catalog/view/javascript/checkout-reskin.js:391
window.bsCheckoutState.totalsChanged('coupon', json.summary_html || '');
```

It is reached for `coupon.summary` because `render()` receives no action/type.
`checkout-state.js:252` then calls `couponChanged()`, whose revision advance
invalidates the address-triggered auto-save. Guest `register.save` explicitly
starts summary before `addressSaved()` at `checkout.twig:1539-1549`, making the
race deterministic in the affected timing.

The wrong visible readiness result is read at:

```text
checkout.twig:877-878 -> !!$('#input-shipping-code').val()
payment_method.twig:68-69 -> same hidden input
```

Those gates are not themselves wrong; their input never recovers.

### Logged in

The same unconditional initial `coupon.summary` is called at
`checkout-reskin.js:455` while `checkout-state.bootstrap()` starts the initial
auto quote at `checkout-state.js:257-281`. A default saved address therefore
can render correctly while its shipping save becomes stale and recovery has
`autoSelect:false`.

Changing to another saved address later starts `addressSaved()` after initial
summary has settled and no register callback launches another summary first,
which explains the reported workaround.

### Production reproduction

Observed sequence/outcome on 2026-07-29:

1. Anonymous session, cart total ₴700.
2. Checkout loaded with the three basic payment choices and muted credit hint.
3. Test-only contact fields completed.
4. `Харківська` → `Харків` → `Відділення №1: вул. Польова, 67`.
5. After waiting for address/quote processing, the shipping-method group was
   empty.
6. Direct locator reads returned:

```json
{
  "shipCount": 1,
  "shipValue": "",
  "shipTextValue": "",
  "paymentValue": ""
}
```

7. The summary remained at “fill delivery and choose payment”; confirm stayed
   disabled.
8. No CAPTCHA was solved and no order was submitted.

This is reproduction evidence of the production symptom and final state.
Exact request/response ordering still needs HAR capture.

## Cache/runtime split risk

The live archive contains the 2026-07-28/29 logic, but production currently
loads:

```text
catalog/view/javascript/checkout-state.js?v=r135-cart-refresh-20260725
catalog/view/javascript/checkout-reskin.js?v=r135-cart-refresh-20260725
```

`diagnostics/ST-2c_minicart_shipping_requote_review_20260728.md:84-91`
already warned that changing checkout JS without a version bump can leave
browsers on pre-patch code. Therefore two unsafe runtime states are possible:

- fresh response/cache: current source with the confirmed summary-classification
  defect;
- warm browser/CDN cache: an older state machine with unknown subset of later
  fixes.

Any implementation option must ship a new immutable asset key and QA both cold
and warm-cache sessions.

## Evidence collection still required

### Schema-safe theme override export

Run from `~/public_html`. It is read-only and does not print credentials:

```bash
cd ~/public_html || exit 1
OUT="$HOME/CHECKOUT-009-theme-overrides-$(date +%Y%m%d-%H%M%S).tsv"
php -r '
require "config.php";
mysqli_report(MYSQLI_REPORT_OFF);
$db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
if ($db->connect_errno) { fwrite(STDERR, "db_connect=failed\n"); exit(2); }
$db->set_charset("utf8mb4");
$table = DB_PREFIX . "theme";
$cols = [];
$q = $db->query("SHOW COLUMNS FROM `" . $table . "`");
if (!$q) { fwrite(STDERR, "show_columns=failed\n"); exit(3); }
while ($row = $q->fetch_assoc()) { $cols[$row["Field"]] = true; }
foreach (["store_id","route","code"] as $required) {
    if (empty($cols[$required])) { fwrite(STDERR, "missing_column=" . $required . "\n"); exit(4); }
}
$stmt = $db->prepare("SELECT `store_id`,`route`,`code` FROM `" . $table . "` WHERE `route` LIKE ? ORDER BY `store_id`,`route`");
if (!$stmt) { fwrite(STDERR, "theme_prepare=failed\n"); exit(5); }
$like = "checkout/%";
$stmt->bind_param("s", $like);
$stmt->execute();
$stmt->bind_result($store_id, $route, $code);
echo "store_id\troute\tcode_base64\n";
while ($stmt->fetch()) { echo (int)$store_id . "\t" . str_replace(["\t","\r","\n"], " ", $route) . "\t" . base64_encode($code) . "\n"; }
' > "$OUT" &&
printf 'file=%s\n' "$OUT" &&
sha256sum "$OUT"
```

### HAR capture

Capture separate guest and logged-in sessions with “Preserve log” enabled.
Include response bodies for:

```text
checkout/register.save
checkout/shipping_address.save
checkout/shipping_address.address
checkout/shipping_method.quote
checkout/shipping_method.save
checkout/payment_method.getMethods
checkout/payment_method.save
checkout/coupon.summary
checkout/coupon.apply
checkout/coupon.remove
checkout/confirm
```

Do not place a real order for the evidence capture. Stop before CAPTCHA/final
confirmation.

