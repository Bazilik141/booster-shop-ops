# Codex Handoff — ST-2: Migration to stock OC4 checkout (Path B)

Date: 2026-06-12. Parent: plan_R-13.5+ (v2), ST-0 report accepted. Owner-approved UX principle: **minimum clicks** — logged-in user with profile gets contacts + default address + shipping method prefilled; only payment choice + «Оформити замовлення» remain. Stepwise stock checkout hidden behind autosave (no per-block "Продовжити" buttons).
Deliver as **3 sequential patches (2a/2b/2c)**, each with own `_patch_backups/`, pre-check anchors, php -l, rollback. Verify every anchor against LIVE (fresh backup: `(свіжий) backup-6.12.2026_11-25-41_boosters.tar.gz` + ST-1 known deltas).

## 1. Task ID
ST-2 — Stock checkout becomes the live checkout: NP structured address, saved addresses, real shipping cost, ports from SimpleCheckout, cutover.

## 2. Context (verified)
- Live checkout = SimpleCheckout via core-file injection: `system/library/url.php:62-66` rewrites link generation `checkout/checkout` → module route. Direct `?route=checkout/checkout` renders STOCK checkout today → full pre-cutover testing possible.
- Stock checkout styled (r11 series), NP events active and fixed (r135): form injects via `</legend>` str_replace into shipping_address; `shipping_existing=novaposhta` radio; searchCity/searchWarehouse/searchStreet AJAX against synced tables (52 159 warehouses after ST-1).
- Hutko: `payment_hutko_shipping_include = 0` → amount = order total − shipping, products normalized (`buildRequest`). Checkbox fiscalization lives in Hutko cabinet. NOTHING to change in Hutko.
- Pinta NP getQuote currently returns `cost => 0` (old crutch) + text with API estimate (r135).
- ST-0 report: port list = coupon/First15, single agree flow, phone requirement, GA4 begin_checkout dedupe, comment field; session mapping risks around `shipping_address.address_id` and `agree`.

## 3. Goal
One checkout (stock route), zero kostyli: structured NP address with autocomplete, saved addresses first-class, real shipping cost in totals (Hutko excl. shipping), promo/First15/oferta/GA4 preserved, minimum clicks UX.

---

## 4. What to change

### Patch 2a — Address core + UX (no cutover, stock route only)
1. **NP structured address as the primary flow** in stock checkout shipping_address (guest: register flow): Місто autocomplete (searchCity, min 2 chars) → тип (відділення / поштомат / адресна) → Відділення/Поштомат autocomplete (searchWarehouse by city ref + type) / адресна: вулиця autocomplete (searchStreet, fallback вільний ввід) + будинок + кв. Use module endpoints directly in our templates; reduce dependence on `</legend>` str_replace injection where practical (move markup into our shipping_address/register twigs calling the same endpoints; events may remain for compatibility).
2. **Saved addresses:** logged-in default = saved address cards/select preselected (last used / default), «Додати нову адресу» opens NP form inline. Save format: `city` = NP city description, `address_1` = «Відділення №X: ...» / «Поштомат №X: ...» / street+house+flat, zone from area (existing format — CRM/admin/TTN compatible). Legacy free-text addresses remain selectable; case-insensitive city match (DB collation _ci) for NP validation.
3. **Autosave UX:** AJAX-save blocks on change/blur (stock save endpoints already exist); hide intermediate buttons; for logged-in prefilled profile the only required actions = payment select + confirm. Hide redundant stock fields (postcode, address_2, company, country/zone selects) server-side; country fixed 220, zone auto from NP area.
4. **Comment field** port (session['comment'], stock-supported).
5. Remove K2 (poshtomat-via-comment instruction) — structured selection replaces it. No fallback (owner goal: no kostyli).

### Patch 2b — Business logic ports
6. **Coupon/promo UI** on stock checkout confirm panel (and re-enable on cart if trivial): apply/remove AJAX against stock coupon extension; port First15 duplicate-use guard + `welcome_coupon_*` session cleanup from SimpleCheckout controller (`prepareSimpleCheckoutCouponTotal`, `applySimpleCheckoutCouponCode` — port logic, not code verbatim).
7. **Single agree flow:** one public-offer confirmation; guest = checkbox (register agree), logged-in = informational text (current behavior), stock `session['agree']` is the single source; confirm validation intact. Remove dual-checkbox ambiguity.
8. **Phone:** required for all checkouts (NP needs it); enforce in stock register/validation explicitly, not via config assumption. Port mask/length validation from SimpleCheckout.
9. **GA4:** rely on enabled `ps_enhanced_measurement` stock-route events; do NOT port SimpleCheckout custom begin_checkout payload; QA must confirm exactly one begin_checkout fires.

### Patch 2c — Real shipping cost + CRM payload + cutover
10. **getQuote real cost:** remove `cost => 0` crutch: cost = API price (UAH→currency convert), `tax_class_id` from config; cart total ≥ `shipping_pinta_nova_poshta_free_from` → cost = 0, title «За наш кошт». Title for paid: «Нова пошта: …» (ціна показується стандартним рядком методу/totals — без «≈»). Keep r135 helpers; declared value (`Cost` = cart total) unchanged.
11. **Hutko verification (no code change expected):** test order: order_total includes shipping row; Hutko amount = total − shipping; products normalized; Checkbox чек = сума без доставки. If any deviation → STOP, report.
12. **CRM payload fields:** locate site-side order-sync sender (order.addHistory event area / existing sync code) and add `items_total`, `shipping_amount`, `order_total` fields to payload (additive — do not rename/remove existing fields). Apps Script side is handled separately; payload must stay backward-compatible.
13. **Cutover:** remove injected block `system/library/url.php:62-66` (exact anchor: `// Simple Checkout module` … `// Simple Checkout module`). Core file — backup mandatory. Do NOT touch SimpleCheckout module files or its admin status (stays installed+enabled as instant rollback: re-insert block). Low-traffic window; note: mid-checkout sessions on module route finish there harmlessly.
14. Success page: verify «hide zero NP totals» customization doesn't hide the free-shipping (0 ₴ «За наш кошт») row incorrectly; adjust display text if needed.

## 5. Do not touch
- Hutko extension code, Checkbox/fiscalization, payment methods config.
- SimpleCheckout files/status (rollback path until ST-6).
- r135 + ST-1 local patches in PintaNovaPoshtaCod (except the targeted getQuote cost change in 2c).
- NP events (65/66/67) — keep enabled.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, Merchant feed, schema.
- DB schema; CRM existing payload fields (additive only).
- Order address format (`address_1` human-readable) — CRM/TTN compatibility.
- Auto-register (ST-4) — NOT in this patch.

## 6. Likely files (verify on live)
`catalog/controller/checkout/{checkout,register,shipping_address,payment_address,shipping_method,payment_method,confirm}.php`, same-name twigs; `extension/ukrainian/.../checkout/*.php` (labels back to meaningful texts); `extension/PintaNovaPoshtaCod/catalog/...` (form twigs, model getQuote in 2c); `system/library/url.php` (2c cutover only); order-sync sender location (2c); `catalog/view/stylesheet/boostershop-ds.css` (additive section).

## 7. Acceptance criteria
1. (2a, stock route direct) Guest: місто типується → дропдаун; відділення/поштомат/адресна обираються зі списків; замовлення доходить до confirm без вільного вводу адреси. Поля postcode/company/zone не видимі.
2. (2a) Logged-in with saved address: opens checkout → contacts+address+shipping prefilled → only payment + confirm needed (2 interactions). New address can be added inline and becomes saved.
3. (2b) Coupon applies/removes on checkout; First15 not reusable by same account; guest sees agree-checkbox, logged-in sees text, confirm blocked without agree (guest).
4. (2b) Order without phone impossible; GA4 begin_checkout fires exactly once (live QA).
5. (2c) Order <1500: totals show real NP shipping; order total includes it; Hutko payment page amount = total − shipping; Checkbox чек без доставки. Order ≥1500: «За наш кошт», shipping 0, totals consistent.
6. (2c) CRM payload contains items_total/shipping_amount/order_total; existing CRM rows unaffected (readback via Apps Script `action=orders&limit=1`).
7. (2c) After cutover: cart button → stock checkout; SimpleCheckout untouched and reachable only by its direct route; rollback = re-insert url.php block (tested once).
8. Each patch: diff confined to declared files; php -l clean; no PHP errors in log.

## 8. QA / smoke
Full **bs-checkout-smoke** after 2c + matrix: {гість, залогінений} × {відділення, поштомат, адресна} × {<1500, ≥1500} × {з купоном First15, без} + oferta обидві гілки + Hutko sandbox (НЕ оплачувати на проді) + admin order/TTN + CRM readback + mobile ≤480px + GA4 single-fire. After 2a/2b: reduced smoke on stock route only (clients unaffected until cutover).

## 9. Rollback
- 2a/2b: restore from `_patch_backups/st2a|st2b-*`; clients on SimpleCheckout unaffected pre-cutover.
- 2c: re-insert url.php block (instant, clients back on SimpleCheckout); revert getQuote cost block to r135 state; CRM payload additive → no rollback needed.
- Каскадний відкат можливий per-patch незалежно.

## 10. Recommended status after execution
2a done → ST-2 `In progress` (owner clicks through stock route). 2b done → reduced smoke. 2c done + full smoke + owner manual QA (Hutko amount + Checkbox чек + CRM) → ST-2 `Done`, чекаут live на stock. Далі ST-3 (ЛК адреси), ST-4 (auto-register), ST-5 (design data), ST-6 (вимкнення SimpleCheckout + чистка), ST-7 (повна регресія).
