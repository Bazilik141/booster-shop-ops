# Codex Handoff — R-13.5: Nova Poshta module — API cost + address flow fix

Date: 2026-06-12
Author: Claude (strategic assistant)
Backup analyzed: `backup-6.12.2026_08-50-35_boosters.tar.gz` (files + DB dump `mysql/boosters_ocart49.sql`)
Roadmap: R-13.5 (High, Not started). Related: TECH-026 (module replace decision — deferred), RD-11 (cart redesign — blocked by this task), RD-13 (checkout reskin — do NOT start).

---

## 1. Task ID

**R-13.5 — Nova Poshta delivery module audit / warehouse pickup fix.**
Owner intent (2026-06-12): NP module must work correctly — pull shipping cost via NP API and delivery address (city/branch/locker) via NP data, instead of the current stock address form with an injected NP block ("форма з костилями").

## 2. Context (verified from backup, 2026-06-12)

Active module: **Pinta Webware "Nova Poshta" v1.4.0** at `extension/PintaNovaPoshtaCod/` (+ marketplace zip in `ocartdata/storage/marketplace/PintaNovaPoshtaCod.ocmod.zip`).

Verified state (DB dump, prefix `ocp5_`):
- `shipping_pinta_nova_poshta_status = 1` (enabled), `sort_order = 2`
- `use_api_price = 1`, `use_fixed_rates = 0` → cost comes from NP API `InternetDocument/getDocumentPrice` (`catalog/model/shipping/pinta_nova_poshta.php::getDocumentPrice`, declared value = cart total)
- API key: set. Sender: Дніпро, Відділення №88 (вул. Савкіна, 2), `sender_service_from = Warehouse`, sender refs present
- `dropdown_checkout_city = 1`, `dropdown_checkout_shipping = 1`, `dropdown_checkout_warehouse = 1`, `dropdown_checkout_shipping_street = 0`, `use_new_nova_poshta_address = 1`
- Reference tables populated: **areas 24, cities 10 948, warehouses 12 985, streets 143 579**
- **Last directory sync: 2026-04-12** (all `reference_*_last_update_datetime` meta keys) → **~2 months stale; cron is not running.** `cron_key` exists; cron entry point: `extension/PintaNovaPoshtaCod/system/library/pintanovaposhta/cron.php`
- Checkout integration: OC4 events (registered AND active, status=1 in `ocp5_event`): `catalog/controller/checkout/shipping_address/after` → `pinta_nova_poshta_cod.alterShippingAddress`, which `str_replace`-injects NP form + JS + CSS after `</legend>` into the stock response. Same pattern for register page. Templates: `catalog/view/template/shipping/checkout_shipping_address_form*.twig`, `js_checkout_shipping_address_form.twig`.
- Stock checkout templates are unmodified OC4 (`catalog/view/template/checkout/shipping_address.twig` — full international form: country/zone/city/postcode/address_1/address_2/company + custom fields). No `pinta` references inside them — coupling is event-only.
- Cart page (`checkout/cart.list`) shows totals only; shipping appears in totals only after a shipping method is chosen in checkout (`extension/opencart/catalog/model/total/shipping.php` reads `session.shipping_method`).

**Installed module is locally patched vs marketplace zip** (verified by diff against `PintaNovaPoshtaCod.ocmod.zip`). 3 patched files:
1. `catalog/model/shipping/pinta_nova_poshta.php` — **THE Hutko crutch (intentional, KEEP)**: `getQuote` computes API price but returns `cost => 0` + text «За тарифами Нової пошти». Result: shipping is never added to order total → Hutko charges cart total (with discounts) correctly; customer pays NP tariff on receipt. Also null-safe city/warehouse lookup.
2. `catalog/model/module/warehouse.php` — null/empty-name guard in `getByName`.
3. `admin/controller/shipping/pinta_nova_poshta.php` — null-safe `shipping_code` handling in order buttons.
Codex: when updating/re-syncing the module, these local patches MUST survive. Never overwrite from the marketplace zip.

Likely root causes of owner-observed problems:
1. **Stale directories (cron dead since 2026-04-12)** → branches/lockers added/renamed/closed after April are missing or wrong.
2. **Duplicate/confusing form UX**: NP selects injected on top of full stock OC address form; user must fill both NP fields and stock fields (postcode/zone/address) that NP flow doesn't need.
3. Possibly cost not visible/incorrect — must be confirmed in Phase 0 (API call may fail silently → `cost => 0` fallback paths exist in `getQuote`).

## 3. Goal

1. NP branch/locker/city data on checkout is live and correct (fresh sync + working scheduled re-sync).
2. Shipping cost: keep `cost => 0` in totals (Hutko crutch stays — owner decision 2026-06-12). Display the API-estimated tariff informationally in the shipping method text: «За тарифами Нової Пошти (≈ ₴XX)» when cart < free-shipping threshold; «За наш кошт» when cart ≥ threshold. If API price unavailable → fall back to plain «За тарифами Нової Пошти» (no fake numbers).
3. **Free-shipping threshold must be admin-configurable** (owner decision): new module setting (e.g. `shipping_pinta_nova_poshta_free_from`, default `1500`, UAH) editable in admin → Pinta NP module settings form. No hardcoded 1500 anywhere. RD-11 cart summary will later read the same setting.
4. Address step UX: customer selects Area → City → Branch/Locker (searchable dropdowns); redundant stock address fields are hidden/auto-filled — no double data entry, no "crutches". Door delivery (адресна) stays enabled.
5. Selected delivery point is reliably stored in order data and visible in admin order view.

## 4. What to change

**Phase 0 — Diagnostics (read-only, live):**
- Confirm events fire on live checkout (NP block renders in `#shipping-address`).
- Call AJAX endpoints `extension/PintaNovaPoshtaCod/shipping/pinta_nova_poshta|searchCity` / `searchWarehouse` — confirm live data returns.
- Trigger `getQuote` with a test address; log whether `getDocumentPrice` returns a real cost or hits the `cost => 0` fallback; capture NP API response/errors.
- Check hosting cron jobs for the Pinta cron URL/CLI; confirm why sync stopped after 2026-04-12.
- Verify NP API key validity (any NP API call status).

**Phase 1 — Fixes (only after Phase 0 confirms causes):**
1. Re-run full directory sync (cron.php with `cron_key`), then register a daily hosting cron job. Verify `reference_*_last_update_datetime` advance.
2. Shipping method text logic in patched `getQuote` (keep `cost => 0`!):
   - cart total < threshold → «За тарифами Нової Пошти (≈ ₴XX)» where XX = `getDocumentPrice` result; on API failure → «За тарифами Нової Пошти»;
   - cart total ≥ threshold → «За наш кошт»;
   - threshold from new admin setting `shipping_pinta_nova_poshta_free_from` (default 1500, UAH): add field to module admin settings form (`admin/controller/shipping/pinta_nova_poshta.php` + its twig + language files). Declared value logic (`Cost` = cart total) stays unchanged.
3. Form UX cleanup, smallest possible diff: when NP shipping is active, hide unused stock fields (postcode, address_2, company, zone/country where NP selects replace them) and auto-fill required stock fields from NP selections so OC validation passes. Prefer extending the module's own `js_checkout_shipping_address_form.twig` (it already manages field visibility via `pintaFieldsSelectors`) over editing stock `shipping_address.twig`. Door delivery stays available.
4. Confirm order save: city/branch ref + human-readable address stored in order shipping fields; visible in admin order info (module adds TTN button via event).

**Phase 2 — out of scope here:** module replacement decision = TECH-026 (Owner decision). Only escalate if Phase 0/1 proves a hard module limitation.

## 5. Do not touch

- Payment: Hutko, Checkbox **fiscalization**, COD payment extension logic (`pinta_nova_poshta_cod` payment side) — read-only.
- **The `cost => 0` patch in `getQuote`** — shipping must NEVER be added to order totals / Hutko amount. Removing it breaks discounted-payment amounts (owner-confirmed crutch).
- Local patches in the 3 files listed in Context — do not revert to marketplace-zip versions.
- Coupon / First15 logic and order totals other than shipping.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, hreflang.
- Merchant feed, Product schema / JSON-LD.
- Checkout step structure / `checkout.twig` layout (RD-13 is a separate future task — do it LAST).
- DB schema (no migrations without separate approval + rollback).
- CRM / Apps Script order-sync payloads (order field format consumed by CRM must not change shape — if shipping address format in order changes, flag it: **needs owner check**).
- Stock OC shipping extensions (flat/free/pickup) config.

## 6. Likely files / areas (likely, not confirmed — Codex must verify against actual live code)

- `extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php` (getQuote, getDocumentPrice, isCorrectWarehouseAddress)
- `extension/PintaNovaPoshtaCod/catalog/controller/shipping/pinta_nova_poshta.php` (search* AJAX, getPartOfCheckoutShippingForm, getJs/getCss)
- `extension/PintaNovaPoshtaCod/catalog/view/template/shipping/js_checkout_shipping_address_form.twig` (field visibility / autofill)
- `extension/PintaNovaPoshtaCod/system/library/pintanovaposhta/cron.php`, `pintanovaposhtaapi.php`
- `extension/PintaNovaPoshtaCod/catalog/controller/payment/pinta_nova_poshta_cod.php` (alterShippingAddress injection — read mostly)
- Hosting cron configuration (cPanel)
- DB (read-only checks): `ocp5_pinta_nova_poshta_meta`, `_city`, `_warehouse`, `ocp5_event`, `ocp5_setting`

## 7. Acceptance criteria (measurable)

1. `searchCity` AJAX returns ≥1 correct match for query "Київ"; `searchWarehouse` for Київ returns current branch list including parcel lockers (Поштомат) — spot-check 2 branches against novaposhta.ua.
2. All `reference_*_last_update_datetime` values in `ocp5_pinta_nova_poshta_meta` ≤ 24h old after fix; cron job visible in hosting scheduler and advances dates next day.
3. Guest checkout, cart < threshold: selecting Дніпро → відділення shows NP method text «За тарифами Нової Пошти (≈ ₴XX)» with a plausible API value (cross-check 1 case against novaposhta.ua calculator, ±20%); **order total and Hutko payment amount do NOT include shipping** (shipping total = ₴0).
3a. Cart ≥ threshold: method text = «За наш кошт»; totals unchanged.
3b. Changing the threshold in admin module settings (e.g. 1500 → 2000) switches the 3/3a boundary without any code edit.
4. Checkout address step shows NO required stock fields that duplicate NP data (no separate empty "Місто"/"Поштовий індекс" the user must re-type); form submits without validation dead-ends (HTTP 200 on `checkout/shipping_address.save`, no `error` JSON).
5. Completed test order in admin → order info shows selected city + branch in shipping address fields; TTN button block renders.
6. Logged-in checkout with saved address still works (no JS errors in console on shipping step).
7. No diff outside `extension/PintaNovaPoshtaCod/` and cron config, unless explicitly justified in the report.

## 8. QA / smoke test

Run **bs-checkout-smoke** (11-step manual checkout/payment smoke) after Phase 1 — mandatory, this task touches the purchase funnel. Minimum inline set:

1. Guest: add product → cart → checkout → NP city/branch select → cost appears → Hutko payment page opens (do NOT pay).
2. Same with parcel locker (поштомат).
3. Same with cart ≥ ₴1500 (free-shipping content promise — verify totals don't contradict site content).
4. Coupon First15 applied → totals correct with shipping row.
5. Logged-in user with saved address.
6. Mobile viewport (≤480px): NP dropdowns usable.
7. Admin: order shows delivery point; CRM sheet receives order via existing sync — verify via Apps Script API `action=orders&status=active&limit=1` readback, not by editing the sheet.
8. Checkbox fiscalization untouched: receipt flow on a test/sandbox order unchanged (**needs owner check** — owner verifies manually).

## 9. Rollback note

- Before any change: file-level backup of `extension/PintaNovaPoshtaCod/` → `_patch_backups/r13.5-np-module-YYYYMMDD_HHMMSS/` (same convention as existing `_patch_backups/`).
- Directory re-sync is data-only and reversible by re-running sync; do NOT truncate `ocp5_pinta_nova_poshta_*` tables manually.
- Settings changes: record exact `ocp5_setting` keys + old values in the report before changing.
- Full fallback: restore module files from `backup-6.12.2026_08-50-35_boosters.tar.gz` (`homedir/public_html/extension/PintaNovaPoshtaCod/`), disable new cron job.
- If checkout breaks: disable the two checkout injection events (`pinta_nova_poshta_view_checkout_shipping_address_after`, `..._register_after`) in admin → stock form returns immediately.

## 10. Recommended status after execution

- Phase 0 report delivered → R-13.5: `In progress`, note findings in roadmap.
- Phase 1 done + smoke passed + owner manual check (payment page, fiscalization, CRM row) → R-13.5: `Done`; unblock RD-11 (cart redesign), which reuses verified shipping logic for its summary shipping row («За тарифами Нової Пошти» / «За наш кошт»).
- If module limitation found → R-13.5: `Blocked`, escalate to TECH-026 (owner decision: paid module).

---

### Owner decisions (2026-06-12) — resolved
1. Declared value = full cart total: **keep**. The Hutko crutch (`cost => 0` in getQuote) identified and confirmed — **must stay**.
2. Door delivery (адресна): **keep enabled**.
3. Free shipping from ₴1500: implement as **admin-editable threshold setting** (default 1500), used for shipping method text now and for RD-11 cart summary row later. Shipping still not charged through Hutko.
