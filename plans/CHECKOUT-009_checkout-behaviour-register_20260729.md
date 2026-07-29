# CHECKOUT-009 Checkout Behaviour Register

Date: 2026-07-29  
Codex config: model=Sol · effort=xhigh

## Scope and evidence boundary

This register freezes the observable checkout behaviour and the compensating
mechanisms present in the owner-supplied post-deployment archive
`CHECKOUT-009-live-evidence-20260729-155027.tar.gz`.

- Archive SHA-256:
  `2990f6ab406786b8eae4d104da98aa3411a39555d35cd58ed540674549fffc79`
- Capture: `2026-07-29T12:50:27Z`, `/home2/boosters/public_html`,
  PHP `8.0.30`.
- The owner confirmed the archive was taken after
  `ST-2c_minicart_shipping_threshold_alignment_20260729` was deployed.
- Active source was distinguished from `.bak` and `.before-*` files. Backup
  artifacts are not treated as executable behaviour.
- Phase 0 now includes sanitized HAR 1.2 captures with response bodies and no
  order-write request:
  - guest: `boostershop.website.har`, SHA-256
    `32b95d3fc7ab84c91ade1e8701bfedd09e7379f3d1836a7d6f35891ca9d6b6b1`;
  - logged-in: `boostershop.website1.har`, SHA-256
    `1149f69c7b2d5c443060c0b3dc3974db28bc56b7daa83b105925ae09f7678079`.
- The logged-in HAR confirms the failure ordering
  `shipping_method.quote → coupon.summary → shipping_method.save → recovery
  quote`; the guest HAR confirms
  `register.save → coupon.summary → shipping quote(s)`.
- Complete-archive verification covered all `286/286` extracted files. The only
  live `totalsChanged(` caller passes `'coupon', json.summary_html || ''` from
  `checkout-reskin.js`; Stage 1 replaces it with the typed `promoResult()` path.
- HAR route verification found one captured address-save target, guest
  `checkout/register.save`, with `route=` present; neither HAR contains another
  `checkout/shipping_address.*` sub-action. Live Twig constructors for
  `shipping_address.address`, `shipping_address.save`, and `register.save` all
  use `index.php?route=...`.
- Schema-safe export
  `CHECKOUT-009-theme-overrides-20260729-165825.tsv`, SHA-256
  `882828fa2a0824dc99d2400bcca4c89c26aa21fcb0cd3bef58d7daf64c3b76a2`,
  contains the header only: zero checkout Twig DB overrides.

## Three-sweep method

1. **Marker sweep:** active live checkout, account-address, Nova Poshta,
   credit, order-side-effect and CRM dependency files were searched for the
   marker families below.
2. **Behaviour sweep:** guest and logged-in customer actions were walked from
   identity and address entry through shipping, payment, confirm and success.
3. **History sweep:** repository patches and diagnostics were used only to
   explain why a live mechanism exists. Current behaviour and file ownership
   come from the live archive.

No active mechanism in this register remains `UNKNOWN-PURPOSE`. The theme
override and authenticated HAR gaps affect confidence in deployment topology
and event ordering; they do not create an unexplained mechanism.

## Verbatim marker inventory — before-image

The identifiers below are copied verbatim from active source comments or
marker constants. Repeated occurrences are intentionally represented once with
all relevant active locations. This list is the marker-drop baseline for a
future implementation round.

| Marker, verbatim | Active live location(s) |
|---|---|
| `RD-13` | `catalog/view/javascript/checkout-reskin.js:1,1545`; `catalog/view/template/checkout/checkout.twig:2` |
| `RD-13.1A` | `catalog/view/template/checkout/checkout.twig:661,1461` |
| `RD-13.1B` | `catalog/controller/checkout/receiver.php:5`; `catalog/controller/checkout/confirm.php:114,207`; `catalog/controller/checkout/success.php:65`; `catalog/view/javascript/checkout-reskin.js:832,1060` |
| `RD-13.1C` | `catalog/controller/checkout/confirm.php:395` |
| `RD-13.1G` | `catalog/view/javascript/checkout-reskin.js:147` |
| `RD-13.1H` | `extension/ps_google_recaptcha/catalog/view/template/captcha/ps_google_recaptcha.twig:47`; referenced by `checkout-reskin.js:1877` |
| `RD-13.1I` | `catalog/view/javascript/checkout-reskin.js:1876` |
| `RD-13.1J` | `catalog/view/template/checkout/checkout.twig:1045` |
| `CHECKOUT-001` | `catalog/controller/checkout/register.php:52,242`; `catalog/controller/checkout/payment_method.php:37,43,551,587,629,728,735`; `catalog/controller/checkout/confirm.php:73`; `catalog/view/template/checkout/register.twig:6,474`; `catalog/view/template/checkout/payment_method.twig:20,28,30`; `catalog/view/template/checkout/checkout.twig:302,885,1011` |
| `CHECKOUT-002_ASYNC_ORDER_SIDE_EFFECTS_20260719` | `catalog/model/checkout/order.php:986`; `extension/telegram_notify/catalog/controller/event/telegram.php:8,66`; `system/library/booster_async_queue.php:2,7`; `system/library/booster_crm_sync.php:302,622` |
| `CHECKOUT-003` | `extension/PintaNovaPoshtaCod/catalog/view/template/shipping/js_checkout_shipping_address_form.twig:366`; `catalog/view/template/checkout/checkout.twig:1424` |
| `CHECKOUT-004` | `catalog/controller/checkout/coupon.php:4`; `catalog/controller/checkout/confirm.php:20`; `catalog/controller/checkout/register.php:699`; `catalog/model/checkout/booster_coupon.php:4`; `catalog/view/template/checkout/checkout.twig:453,1539` |
| `CHECKOUT-005` | `catalog/controller/checkout/payment_method.php:635,761` |
| `CHECKOUT-006` | `catalog/controller/checkout/payment_method.php:718`; `catalog/controller/checkout/success.php:22`; `catalog/model/checkout/booster_coupon.php:54` |
| `CHECKOUT-007` | `catalog/controller/checkout/coupon.php:10`; `catalog/controller/checkout/payment_method.php:665`; `catalog/model/checkout/booster_coupon.php:53`; `catalog/view/template/checkout/success.twig:22` |
| `CHECKOUT-007A` | `catalog/controller/checkout/success.php:27` |
| `ST-2c` | `catalog/view/javascript/checkout-reskin.js:355,1258`; related live shipping/free-shipping comments |
| `ST-2c.3` | `catalog/view/javascript/checkout-state.js:1` |
| `ST-2c.3b` | `catalog/controller/checkout/payment_method.php:324`; `catalog/view/template/checkout/payment_method.twig:215`; `catalog/view/javascript/checkout-state.js:263` |
| `ST-2c.4` | `catalog/controller/checkout/shipping_method.php:142`; `catalog/view/javascript/checkout-state.js:181`; `catalog/view/javascript/checkout-reskin.js:428` |
| `ST-2c.5` | `catalog/controller/checkout/shipping_method.php:134`; `catalog/controller/checkout/success.php:101`; `catalog/view/javascript/checkout-state.js:58`; `catalog/view/javascript/checkout-reskin.js:1277`; `extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php:191` |
| `ST-2C-COUPON-SHIPPING-20260728` | `catalog/controller/checkout/coupon.php:59`; `catalog/view/javascript/checkout-state.js:237`; `catalog/view/template/checkout/shipping_method.twig:154`; `extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php:230` |
| `ST-2C-MINICART-SHIPPING-20260728` | `catalog/view/javascript/checkout-state.js:150` |
| `ST-2C-MINICART-THRESHOLD-ALIGNMENT-20260729` | `catalog/view/javascript/checkout-reskin.js:1264`; `extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php:239` |
| `ACC-002` | `catalog/controller/account/address.php:134,586`; `catalog/view/template/account/address_form.twig:292,552`; `catalog/view/javascript/checkout-reskin.js:1625` |
| `ACC-002A` | `catalog/controller/account/address.php:307`; `catalog/view/javascript/checkout-reskin.js:1677` |
| `ACC-002B` | `catalog/controller/checkout/checkout.php:100`; `catalog/view/javascript/checkout-reskin.js:1060` |
| `ACC-002C` | `catalog/view/javascript/checkout-reskin.js:1351` |
| `ACC-002D` | `catalog/controller/checkout/shipping_address.php:83`; `catalog/view/javascript/checkout-reskin.js:597` |
| `ACC-002E` | `catalog/controller/checkout/shipping_address.php:292`; `catalog/view/javascript/checkout-reskin.js:1775` |
| `ACC-002F` | `extension/PintaNovaPoshtaCod/catalog/controller/payment/pinta_nova_poshta_cod.php:97` |
| `PAY-001` | `catalog/controller/checkout/payment_method.php:505`; payment and confirm controllers/templates |
| `PAY-001-SIMPLE-CHECKOUT-ISOLATION` | `extension/mono_chast/catalog/model/payment/mono_chast.php:6` |
| `PAY-001-PHASE2-CREDIT-UI-20260721` | `catalog/controller/checkout/checkout.php:39` |
| `PAY-001-PHASE2C-D1-OC4-PAYMENT-METHOD-20260725` | `extension/mono_chast/catalog/controller/payment/mono_chast.php:51` |
| `PAY-001-PHASE2C-D2-COUPON-TOTALS-20260725` | `catalog/view/javascript/checkout-reskin.js:390` |
| `PAY-001-PHASE2C-D3-CREDIT-TERM-20260725` | `catalog/controller/checkout/checkout.php:43` |
| `PAY-001-PHASE2C-D4-UNAVAILABLE-CREDIT-ROW-20260725` | `catalog/view/template/checkout/payment_method.twig:283` |
| `PAY-001-PHASE2C-CART-CONTRACT-GATES-20260725` | `catalog/view/template/checkout/checkout.twig:892` |
| `PAY-001-CREDIT-PREVIEW-20260726` | `catalog/view/template/checkout/payment_method.twig:34` |
| `PAY-001-CREDIT-PREVIEW-CARD-20260726` | `catalog/view/template/checkout/payment_method.twig:197` |
| `PAY-002` | `extension/pumb_credit/admin/view/template/payment/pumb_credit.twig:3` |
| `NCRM-10` | `catalog/model/checkout/order.php:1018,1022`; `system/library/booster_crm_sync.php:488` |
| `NCRM-10_ORDER_SYNC_HOOK_20260718` | `catalog/model/checkout/order.php:1000`; `system/library/booster_crm_sync.php:455` |
| `NCRM-10_ROUND3_SYNC_TIMEOUT_20260719` | `system/library/booster_crm_sync.php:682` |
| `NCRM-10_ROUND5_DEFER_AFTER_RESPONSE_20260719` | `catalog/model/checkout/order.php:1001` |

## Behaviour and mechanism register

Disposition vocabulary:

- `P` — preserve as-is.
- `R:<mechanism>` — replace with the named mechanism; the customer-visible
  behaviour remains.
- No row is deliberately removed in any option.

| # | Behaviour | Where it lives (file · function · line) | Marker / patch of origin | Why it exists | Trigger to observe it | Disposition | Evidence after change |
|---:|---|---|---|---|---|---|---|
| 1 | Guest identity/contact fields autosave into checkout session | `catalog/view/template/checkout/checkout.twig` · register autosave/`ajaxSend`/`ajaxSuccess` · 661-854, 1502-1554; `catalog/controller/checkout/register.php` · `save()` | `CHECKOUT-001`, `RD-13.1A` | Makes the stock checkout guest-only while maintaining server customer/payment/shipping address state | Enter name, phone and e-mail as guest and blur fields | A `P`; B `P`; C1 `P`, C2 `P` | `register.save` success, no customer/order row unless opt-in/confirm reaches its gate |
| 2 | Guest public-offer agreement is mandatory; it is absent/valid for logged-in checkout | `catalog/view/template/checkout/checkout.twig` · `bsCheckoutHasAgreeReady()` · 885-890; `catalog/controller/checkout/confirm.php` · confirm gate · 73+ | `CHECKOUT-001 Phase 1.3` | Legal acceptance before a guest order | Guest toggles offer checkbox; logged-in page has no checkbox | A `P`; B `P`; C1 `P`, C2 `P` | Button and server confirmation reject guest without agreement and accept logged-in absence |
| 3 | “Save data for next time” performs pre-confirm account creation | `catalog/view/template/checkout/payment_method.twig` · opt-in UI · 20-30; `catalog/controller/checkout/payment_method.php` · account endpoint · 587-762 | `CHECKOUT-001`, `CHECKOUT-005`, `CHECKOUT-007` | Optional account creation without native registration mode | Guest checks the option and proceeds to confirm | A `P`; B `P`; C1 `P`, C2 `P` | Account and structured NP address are created only after the explicit opt-in path |
| 4 | Newsletter toggle is carried with the guest/account data | `catalog/view/template/checkout/register.twig`; register/account save path | `CHECKOUT-001` inherited stock behaviour | Retains explicit marketing consent | Toggle newsletter on/off before confirmation | A `P`; B `P`; C1 `P`, C2 `P` | Saved subscription value matches the toggle; no implicit subscription |
| 5 | Receiver name/phone can differ from account profile for one order | `catalog/controller/checkout/receiver.php`; `confirm.php:114,207`; `checkout-reskin.js:832,1060`; `success.php:65` | `RD-13.1B` | Supports order-only receiver override without mutating the account profile | Logged-in customer edits receiver fields | A `P`; B `P`; C1 `P`, C2 `P` | Order uses override; account profile remains unchanged; override clears after success |
| 6 | Logged-in checkout preselects and can change saved addresses | `catalog/controller/checkout/shipping_address.php` · `index()`/`address()` · 14-42, 449-501; `shipping_address.twig` | OpenCart stock + `ACC-002*` | Reuses persisted addresses and clears stale shipping/payment choices on change | Load with default address, then choose another saved address | A `P`; B `P`; C1 `P`, C2 `P` | Default renders; changed address causes exactly one new delivery-selection transaction |
| 7 | Three Nova Poshta modes: warehouse, parcel locker, courier | `extension/PintaNovaPoshtaCod/.../pinta_nova_poshta` controller/model/templates; checkout reskin type controls | Pinta + `ACC-002D/E/F`, `ST-2c.5` | Supports the three owner-approved delivery forms | Select each mode and complete its required fields | A `P`; B `P`; C1 `P`, C2 `P` | Each mode yields a valid canonical address and quote; stale fields from another mode are absent |
| 8 | NP region → city → point/address cascade and structured refs | `checkout.twig:661-816`; Pinta JS; `shipping_address.php:83+` | `RD-13.1A`, `ACC-002D` | Prevents labels alone from being treated as verified NP entities | Pick region, city and warehouse/locker, or enter courier address | A `P`; B `P`; C1 `P`, C2 `P` | Request carries canonical refs; server rejects incomplete/stale combinations |
| 9 | Saved NP metadata is hydrated, validated and can be explicitly repaired | `catalog/controller/account/address.php:134,307,586`; `address_form.twig:292,552`; `checkout-reskin.js:1625-1800`; `shipping_address.php:292+` | `ACC-002`, `ACC-002A`, `ACC-002D`, `ACC-002E` | Avoids trusting stale saved point labels/refs and avoids silent duplicate addresses | Open a saved NP address, encounter stale ref, use re-pick repair | A `P`; B `P`; C1 `P`, C2 `P` | Valid metadata hydrates; stale metadata blocks; repair targets only the named address |
| 10 | Server quotes shipping only with customer/payment/shipping address session ready | `catalog/controller/checkout/shipping_method.php` · `quote()` · 43-85 | OpenCart stock | Makes quote availability depend on authoritative session prerequisites | Call `shipping_method.quote` before/after address save | A `P`; B `P`; C1 `P`, C2 `P` | Before: address/customer error; after: canonical `shipping_methods` response |
| 11 | First available shipping quote is auto-selected and saved after address readiness | `shipping_method.twig` · `renderShippingMethods()`/`saveShipping()` · 36-92, 94-169 | `ST-2c.3` | Converts quote availability into server-persisted `shipping_method` readiness | Complete or change an address | A `P`; B `R: DeliverySelection transaction`; C1 `P`, C2 `R: DeliverySelection transaction` | Exactly one quote and one save; UI and server session share the same selection revision |
| 12 | Card, COD and IBAN are visible choices; selected payment is server-saved | `payment_method.twig` · preview/render/save · 68-125, 278-431; `payment_method.php` | `ST-2c.3b` + stock payment models | Keeps basic payment methods separate from credit availability | Select each method after shipping readiness | A `P`; B `P`; C1 `P`, C2 `P` | Each method saves once and reaches its existing payment controller without changing eligibility |
| 13 | Monobank credit preview, 3/4/5 terms, minimum-total and preorder blocks | `checkout.php:39-46`; `payment_method.twig:193-330`; `checkout.twig:892-917`; Mono extension | `PAY-001-*` | Shows the method early but keeps legitimate server-owned/cart-owned blocks | Cart below minimum, at minimum, and with preorder item; choose 3/4/5 | A `P`; B `P`; C1 `P`, C2 `P` | Existing minimum and preorder matrices remain identical; shipping readiness no longer falsely blocks a ready address |
| 14 | PUMB is a disabled “coming soon” preview/skeleton | `payment_method.twig:268`; `extension/pumb_credit/...`; DB safe settings has no enabled PUMB row | `PAY-002` | Preserves product visibility without enabling bank transport | Open credit drawer | A `P`; B `P`; C1 `P`, C2 `P` | PUMB cannot be selected or sent to bank transport |
| 15 | Hidden inputs mirror client readiness: shipping code/label/display text and payment code/label | `shipping_method.twig:3-5`; `payment_method.twig:3-4`; `checkout.twig:877-882` | `ST-2c.3/.5` | Gives several legacy renderers a shared client-readable state | Inspect after page load, address change, shipping save and payment save | A `P`; B `R: explicit CheckoutReadiness snapshot`; C1 `P`, C2 `R: explicit CheckoutReadiness snapshot` | B/C2 consumers read one immutable snapshot; compatibility mirrors, if retained, are write-only projections |
| 16 | Revision token rejects stale quote/save/payment/totals responses | `checkout-state.js` · `revision`, `isCurrent()`, transition methods · 13-71, 172-255; `shipping_method.twig:53-55,182-184` | `ST-2c.3` | Prevents an older asynchronous response from overwriting a newer user choice | Rapidly change address/coupon/cart while requests are in flight | A `P`; B `R: transaction id owned by CheckoutState`; C1 `P`, C2 `R: transaction id owned by CheckoutState` | Only the final transaction commits; a non-current active address revision is released before a later requote; rejected responses are traceable in diagnostics |
| 17 | Address transition clears shipping/payment readiness before requoting | `checkout-state.js` · `beginAddressTransition()` · 54-62 | `ST-2c.3`, `ST-2c.5` | Prevents a method for the previous address being confirmed | Change address or NP mode | A `P`; B `R: DeliverySelection invalidation`; C1 `P`, C2 `R: DeliverySelection invalidation` | Old server selection is invalidated once; next selection becomes ready atomically |
| 18 | Coupon client exposes `summary`, `apply`, `remove`, single-flight/Chain serialization and cached summary rendering | `checkout-reskin.js` · `render()`/`request()` · 351-455; `coupon.php`; `booster_coupon.php` | `CHECKOUT-004`, `ST-2c.4` | Keeps promo totals read-only until apply/remove and serializes session writes | Initial page load; apply and remove a coupon | A `R: typed coupon action result`; B `R: TotalsRefresh command/query split`; C1 `R: typed coupon action result`, C2 `R: TotalsRefresh command/query split` | `summary` with `mutated:false` is query-only; an actual First15 auto-apply reports `mutated:true`; apply/remove each publish one totals mutation |
| 19 | **Defective compensator:** every coupon response, including read-only `summary`, calls `totalsChanged('coupon')` | `checkout-reskin.js` · `render()` · 389-392; initial summary at 455 | `PAY-001-PHASE2C-D2-COUPON-TOTALS-20260725` plus `ST-2c.3` | Intended to refresh credit/free-shipping totals, but it misclassifies a query as a mutation | Load checkout with no coupon or run quiet summary | A `R: remove unconditional signal; action-aware signal`; B `R: command/query split`; C1 `R: action-aware signal`, C2 `R: command/query split` | `mutated:false` changes no revision; `mutated:true` waits behind a current address/bootstrap save; a superseded active revision is released and the later mutation requotes exactly once |
| 20 | Real coupon mutation requotes and re-saves the current shipping method | `checkout-state.js` · `couponChanged()` · 237-249; `shipping_method.twig:153-158` | `ST-2C-COUPON-SHIPPING-20260728` | Recomputes free-shipping threshold and display tariff after discount changes | Apply/remove coupon with an already selected method | A `P`; B `R: DeliverySelection.requote(reason=coupon)`; C1 `P`, C2 `R: DeliverySelection.requote(reason=coupon)` | Choice identity remains; a non-current address revision never gates the mutation; fresh quote/save and totals are one transaction |
| 21 | Mini-cart mutation requotes and re-saves the current method | `checkout-state.js` · `cartChanged()` · 150-170; common cart event bridge | `ST-2C-MINICART-SHIPPING-20260728` | Keeps tariff and free-shipping state aligned after quantity/removal | Change quantity or remove item in mini-cart while on checkout | A `P`; B `R: DeliverySelection.requote(reason=cart)`; C1 `P`, C2 `R: DeliverySelection.requote(reason=cart)` | One cart event causes one fresh quote/save; no address save is invoked |
| 22 | Pinta form posts the authoritative stock `shipping_address.save` route | `js_checkout_shipping_address_form.twig` · submit handler · 260-345; `shipping_address.php:272-436` | `ACC-002F` | Makes the structured stock controller the sole server address writer | Complete a logged-in new NP address form | A `P`; B `P`; C1 `P`, C2 `P` | One address write; Pinta after-hook remains a no-op and does not duplicate an address |
| 23 | **Duplicate client writer:** Pinta success schedules `bsCheckoutLoadShippingMethods({autoSelect:true})` after 250 ms | Pinta JS · success handler · 313-335 | pre-coordinator legacy timing compensation | Historically loaded methods after address save, but now competes with the coordinator; this vendor file can be overwritten by a Pinta update | Save a logged-in new NP address | A `R: delete delayed call; explicit address-saved transaction`; B `R: CheckoutState event`; C1 `R: explicit transaction`, C2 `R: CheckoutState event` | Pinta calls the shared address-save-completed callback once; no timer-based second writer; every Pinta update must recheck this row |
| 24 | **Duplicate client writer:** broad global `ajaxSuccess` treats any successful URL containing `checkout/shipping_address` as a new address transition | `checkout.twig` · global `ajaxSuccess` · 1557-1558 | `ST-2c.3` integration bridge | Connects legacy stock/Pinta XHR to coordinator, but duplicates Pinta's own success path | Save or select a shipping address | A `R: route-specific explicit completion callback`; B `R: CheckoutState event adapter`; C1 `R: explicit callback`, C2 `R: CheckoutState event adapter` | Only exact normalized stock address routes call the shared callback; Pinta marks and handles its own response once; no substring matching |
| 25 | Guest `register.save` success queues coupon summary before invoking `addressSaved()` | `checkout.twig` · global `ajaxSuccess` · 1534-1554 | `CHECKOUT-004` + `ST-2c.3` | Refreshes First15 and then reacts to changed address/payment state; current ordering creates the defect | Guest completes contact + NP address | A `R: address transaction first; summary query non-mutating`; B `R: orchestrated command/query flow`; C1 `R: typed ordering`, C2 `R: orchestrated flow` | `AddressCommitted` starts before the summary query; `mutated:true` is deferred until shipping save commit; `mutated:false` remains query-only |
| 26 | Page bootstrap starts payment preview and auto shipping quote when no shipping code exists | `checkout-state.js` · `bootstrap()` · 257-284; Pinta `CHECKOUT-003` init guard | `ST-2c.3`, `ST-2c.3b`, `CHECKOUT-003` | Restores ready saved session/address state without user interaction while suppressing init autosave | Load checkout as guest/logged-in with/without address | A `P`; B `R: CheckoutBootstrap transaction`; C1 `P`, C2 `R: CheckoutBootstrap transaction` | No write for incomplete guest; one quote/save for ready address; reload is deterministic |
| 27 | Cached read-only summary and deferred confirm UI avoid preloading an order-write confirm | `checkout-state.js:73-133,172-193`; `checkout.twig:432-459,947-980`; `confirm.php` read-only action | `CHECKOUT-004`, `ST-2c.4` | Shows totals while preventing phantom/order-write confirmation calls | Load checkout, change shipping/coupon/payment | A `P`; B `R: TotalsSnapshot renderer`; C1 `P`, C2 `R: TotalsSnapshot renderer` | No order row/status-0 draft before trusted final click; totals match server response |
| 28 | Free shipping threshold is ₴2,000 on post-discount payable amount | `coupon.php:59+`; Pinta shipping model `230+`; checkout summary progress in `checkout-reskin.js:1258+` | `ST-2C-COUPON-SHIPPING-20260728`, threshold alignment marker | Aligns coupon and cart mutations to the actual payable threshold | Cross ₴2,000 via coupon apply/remove or quantity change/remove | A `P`; B `P`; C1 `P`, C2 `P` | Matrix around 1,999/2,000 and mutation crossings matches server totals |
| 29 | Pinta tariff is display-only; payable shipping cost is zero | Pinta shipping model `191+`; `shipping_method.php:129-145`; summary renderer | `ST-2c.5` | Shows NP estimate without adding it to OpenCart payable totals | Select paid NP quote below threshold | A `P`; B `P`; C1 `P`, C2 `P` | Estimate is visible; order total/payment amount excludes it |
| 30 | Mini-cart quantity/removal emits a checkout cart-update only for a real mutation | common cart JS/template; `checkout-state.js:150-170` | `ST-2C-MINICART-SHIPPING-20260728` | Avoids requote loops when mini-cart is refreshed only for display | Change quantity/remove vs reload fragments | A `P`; B `P`; C1 `P`, C2 `P` | Mutation: one event; fragment reload after shipping save: zero cart-change events |
| 31 | Order comment is saved independently of payment selection | `payment_method.twig` · comment handlers · 463-484; `payment_method.php` comment route | stock checkout/reskin integration | Preserves customer note through payment refreshes | Enter comment, switch/reload payment methods | A `P`; B `P`; C1 `P`, C2 `P` | Comment survives state transitions and reaches order data |
| 32 | Confirm readiness requires shipping code, payment code, offer (guest) and no selected-credit block | `checkout.twig` · `bsCheckoutCanConfirm()` · 877-917; deferred button at 947-975 | `ST-2b.4`, `CHECKOUT-001`, `PAY-001-*` | Prevents final action before all required selections and legal/credit gates | Exercise every ready/not-ready combination | A `P`; B `R: server-backed CheckoutReadiness`; C1 `P`, C2 `R: server-backed CheckoutReadiness` | UI reason and server rejection agree for every gate combination |
| 33 | Guest reCAPTCHA is the final gate and payload is restored through reskin flow | `confirm.php:395+`; captcha Twig; `checkout.twig:1045+`; `checkout-reskin.js:1876+` | `RD-13.1C/H/I/J` | Stops automated guest confirmation without moving CAPTCHA earlier into address saves | Reach final guest confirmation and solve manually | A `P`; B `P`; C1 `P`, C2 `P` | CAPTCHA is requested only at final confirm; failure creates no order |
| 34 | Trusted final confirmation is the only order-write path | `checkout.twig` confirm orchestration; `confirm.php`; selected payment controller | `CHECKOUT-004` safeguards + payment patches | Avoids phantom orders and duplicate bank/IBAN submissions | Complete all gates, then one deliberate final click | A `P`; B `P`; C1 `P`, C2 `P` | Before click: no order; one click: one order/payment action; idempotency guards remain |
| 35 | Coupon and First15 lifecycle: manual coupon preserved; next-order First15 applies only after qualifying account order | `coupon.php`; `booster_coupon.php`; `payment_method.php:665+`; `register.php:699+` | `CHECKOUT-004/005/006/007` | Prevents First15 affecting the registration order or replacing a manual code | Create opted-in account, complete order, open next checkout | A `P`; B `P`; C1 `P`, C2 `P` | Registration order full price; next eligible order applies once; manual coupon wins |
| 36 | Success page consumes/shows First15 courtesy and delivery estimate flags | `checkout/success.php:22-101`; `checkout/success.twig:22+` | `CHECKOUT-006`, `CHECKOUT-007A`, `ST-2c.5` | Communicates next-order benefit and display-only delivery information once | Complete eligible order and load/reload success | A `P`; B `P`; C1 `P`, C2 `P` | First render shows intended message; one-shot flags clear by design |
| 37 | Telegram order notification is queued after order history | `catalog/model/checkout/order.php:986+`; Telegram event; async queue | `CHECKOUT-002_ASYNC_ORDER_SIDE_EFFECTS_20260719` | Keeps external notification latency/failure off the checkout response | Create an owner-approved test order | A `P`; B `P`; C1 `P`, C2 `P` | Checkout response does not wait; one queued notification follows order history |
| 38 | CRM order sync is deferred and bounded | `order.php:1000+`; `system/library/booster_crm_sync.php:455+` | `NCRM-10_*` | Prevents CRM latency/failure from blocking checkout | Create/update an owner-approved test order | A `P`; B `P`; C1 `P`, C2 `P` | Order succeeds independently; one deferred sync attempt is logged |
| 39 | Analytics events observe checkout views and save actions | DB event snapshot: checkout view/save event rows | `analytics_ps_enhanced_measurement` event registrations | Preserves funnel measurement without owning readiness | Load checkout and save shipping/payment | A `P`; B `P`; C1 `P`, C2 `P` | Existing event names fire once per corresponding action |
| 40 | Checkout scripts use the stale query key `r135-cart-refresh-20260725` despite 2026-07-28/29 JS changes | `checkout.twig:1579-1581`; production asset inventory | R-13.5 comment; later ST-2c runners omitted bump | Browser/CDN cache may execute a pre-patch state machine independently of server files | Load production checkout and inspect script URLs | A `R: new immutable version key`; B `R: consolidated bundle/version`; C1 `R: new key`, C2 `R: consolidated bundle/version` | Runner publishes both scripts as `checkout009-stage1-20260729`; live HAR verification remains owner QA |

## Disposition accounting

| Option | Preserved | Replaced | Deliberately removed | `UNKNOWN-PURPOSE` | Accounted rows |
|---|---:|---:|---:|---:|---:|
| A — targeted corrections | 34 | 6 | 0 | 0 | 40/40 |
| B — consolidated state layer | 25 | 15 | 0 | 0 | 40/40 |
| C Stage 1 — safe correction | 34 | 6 | 0 | 0 | 40/40 |
| C final — staged consolidation | 25 | 15 | 0 | 0 | 40/40 |

Rows replaced by Option A/C Stage 1 are `18`, `19`, `23`, `24`, `25`, and
`40`. Option B/C final replaces rows `11`,
`15`-`21`, `23`-`27`, `32`, and `40`.

## Stage 1 implementation accounting

Implementation runner:
`patches/CHECKOUT-009_stage1_coupon-classification-single-writer_20260729.php`.

| Replaced row | Stage 1 mechanism |
|---:|---|
| 18 | Client publishes `{kind, mutated}`; `coupon.summary` adds only the truthful boolean `mutated`. |
| 19 | `promoResult()` removes the unconditional coupon mutation; `mutated:false` only caches totals; a stale `activeAddressRevision` self-heals before any later mutation requote. |
| 23 | Pinta 250 ms writer is replaced by `bsCheckoutAddressSaveCompleted()` → `AddressCommitted`. Vendor-update risk is recorded in the runner header and this register. |
| 24 | The broad URL-substring listener is replaced by exact normalized stock address routes; the Pinta request is source-tagged to avoid a duplicate callback. |
| 25 | Guest `register.save` starts `AddressCommitted` before requesting the coupon summary; a real First15 mutation queues one post-commit requote. |
| 40 | Both scripts use the immutable key `checkout009-stage1-20260729` in the same deployment unit. |

The other 34 rows remain `P`. No row is deliberately removed. Accounting stays
`34 preserved`, `6 replaced`, `0 removed`, `0 UNKNOWN-PURPOSE`, `40/40`.

### Marker-drop check

Across the five live targets, 27 before-image markers were relevant. Patched
fixture comparison found `27/27` still present, `0` missing, and the new
`CHECKOUT-009-STAGE1-20260729` marker in every target. The review regression
case `stale_address_revision_manual_coupon_requote` also passes.

## Remaining production evidence after local implementation

Phase 0 is satisfied: both HARs contain checkout response bodies, the logged-in
failure ordering is reproduced, and the schema-safe DB export contains zero
checkout Twig overrides. Deployment remains owner-only. Still required after
deployment:

1. Guest warehouse, parcel-locker and courier completion through payment and
   one owner-approved final order.
2. Logged-in default and alternate saved-address replay through payment.
3. Coupon apply/remove and automatic First15 threshold crossings around ₴2,000.
4. Mono minimum/preorder gates, basic payment methods, analytics one-save
   observation, cold/warm-cache asset verification and `bs-checkout-smoke`.

