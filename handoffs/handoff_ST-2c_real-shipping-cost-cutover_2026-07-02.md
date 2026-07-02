# Codex Handoff — ST-2c: Real shipping cost in totals + checkout cutover

Date: 2026-07-02
Author: Claude (strategic assistant)
Roadmap: ST-2c (High, Not started, Stage: Queued). Parent: R-13.5 (In progress). Blocks: ST-6.

---

## 1. Task ID

**ST-2c — Переключення всіх клієнтів на новий чекаут + реальна вартість доставки в totals.**

## 2. Context

R-13.5 (Nova Poshta module fix) and the ST-0…ST-2b.5 series migrated checkout logic from SimpleCheckout to a patched stock OC4 checkout, in parallel, without cutting over live traffic. ST-2b.1–ST-2b.5 (defer draft orders, success-page fixes, coupon/First15/agree/GA4 parity) are all **Done**. ST-3.5/3.6/3.7 (admin TTN button, COD payment control, UK localization) are also **Done** but are a separate branch of work (admin-side, no checkout dependency).

ST-2c is the last gate before cutover. Two things are bundled here because they must ship together (shipping cost = 0 today is what currently keeps Hutko amounts correct; changing one without the other breaks payment math):

1. **Real shipping cost in order totals.** Today `getQuote` in the patched Pinta NP model returns `cost => 0` unconditionally (the "Hutko crutch" from R-13.5) — Hutko charges cart total only, shipping is never in totals. Owner decision (2026-06-12, confirmed in R-13.5 and `handoff_ST-2_stock-checkout-migration_2026-06-12.md` §10): remove the `cost => 0` crutch, use the real API price (`getDocumentPrice`) in totals, **and** keep `payment_hutko_shipping_include = 0` (already set) so Hutko amount = order total − shipping. This is a from-both-ends math change: totals gain a shipping line, Hutko must keep subtracting it.
2. **Cutover `system/library/url.php`.** SimpleCheckout patches core `url.php:62-66` (rewrite that redirects `checkout/checkout` link generation to the SimpleCheckout module route). Cutover = remove ~5 lines so the link points at the stock checkout Codex has been building. Rollback = restore those lines. SimpleCheckout stays installed (not deleted) as the rollback path until ST-6.

Free-shipping threshold: R-13.5 required an admin-editable setting `shipping_pinta_nova_poshta_free_from` (default 1500 UAH) instead of a hardcoded value. Verify whether this setting was already added by an earlier patch (r135/ST-1) — if not, it must be added here since real cost display needs the threshold logic (`cart total ≥ threshold → cost = 0, title «За наш кошт»`).

## 3. Goal

1. Order totals show the real Nova Poshta shipping cost (from `getDocumentPrice`), not a hardcoded 0.
2. Cart total ≥ free-shipping threshold → shipping cost = 0 in totals, method title "За наш кошт". Threshold is read from an admin-editable setting (`shipping_pinta_nova_poshta_free_from`, default 1500), not hardcoded.
3. Hutko charged amount = order total − shipping (via existing `payment_hutko_shipping_include = 0` — verify this still holds once totals include a real shipping line, do not change Hutko logic itself).
4. `system/library/url.php` cutover: checkout link generation points to stock checkout for all customers (guest + logged-in). SimpleCheckout module stays installed/disabled, not deleted.
5. No regression in any of the ST-2b.1–2b.5 guarantees (single `confirm.confirm` caller / zero draft orders, coupon/First15, GA4 dedupe, success-page fiscal text, admin TTN flow).

## 4. What to change

**Phase 0 — Diagnostics (read-only, live), do first:**
- Confirm current live state: is `cost => 0` still hardcoded in `catalog/model/shipping/pinta_nova_poshta.php::getQuote`? Confirm exact line/patch marker.
- Confirm whether `shipping_pinta_nova_poshta_free_from` setting already exists in `ocp5_setting` / admin form (may have shipped with r135/ST-1 — do not assume, check).
- Confirm `payment_hutko_shipping_include` current value and where it's read in the Hutko payment controller (read-only; do not modify Hutko `buildRequest`/amount/sign/api).
- Confirm current content of `system/library/url.php:62-66` (SimpleCheckout rewrite) matches the R-13.5/ST-0 audit description before touching it.
- Confirm CRM/Apps Script order-sync payload shape for shipping fields (read via `action=orders&status=active&limit=1`, not by editing the sheet) — flag if adding a real shipping line changes the payload shape the CRM expects.

**Phase 1 — Implementation (only after Phase 0 confirms the above):**
1. In `getQuote`: replace hardcoded `cost => 0` with real cost from `getDocumentPrice` (UAH, converted to store currency per existing helper). If cart total ≥ threshold (`shipping_pinta_nova_poshta_free_from`, admin setting, default 1500) → cost = 0, title "За наш кошт". Otherwise real cost, plain title ("Нова пошта: ..." — cost shown via standard method/totals row, no "≈" prefix since it's now the real charged amount, not an estimate). Add the admin setting field if it does not already exist (module settings form + twig + language files) per R-13.5 §4.2.
2. Verify Hutko amount calculation still nets out shipping correctly with a non-zero shipping total (read-only check on Hutko controller logic — do not modify Hutko itself unless the netting is broken by this totals change, in which case flag and stop, do not patch Hutko without separate approval).
3. Cutover `url.php`: remove the SimpleCheckout rewrite (~5 lines) so `checkout/checkout` resolves to stock route for all visitors. Keep SimpleCheckout module files/config untouched and installed (rollback path).
4. Confirm the free-shipping threshold change is reflected consistently: shipping method text, totals, and (if surfaced) any cart-page shipping estimate.

**Phase 2 — out of scope here:** ST-6 (disable SimpleCheckout, remove K4 crutches) — separate task, gated on a stable period post-cutover.

## 5. Do not touch

- Hutko `buildRequest` / amount / `sign` / `api` / redirect target logic itself — only verify it nets shipping correctly with the new real cost; any actual code change to Hutko needs separate approval.
- Checkbox fiscalization — read-only, no changes.
- The single `confirm.confirm` caller guarantee from ST-2b.1/ST-2b.4 (checkout.twig = exactly 1 caller, on click only) — do not reintroduce any auto-confirm path.
- Coupon/First15 logic (ST-2b.5) and GA4 dedupe (ST-2b.5) — no changes beyond what's needed if totals shape changes affect them (flag, don't silently touch).
- CRM / Apps Script order-sync payload — if shipping field format in the order changes shape (not just goes from 0 to a real number), that's a **needs owner check**, not a silent change.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, hreflang.
- Merchant feed, Product schema / JSON-LD.
- DB schema (no migrations without separate approval + rollback).
- SimpleCheckout module files — do not delete or disable (that's ST-6, after a stable period).
- Admin TTN button / ST-3.5-3.7 flow (COD payment control, UK localization) — unrelated, do not touch.

## 6. Likely files / areas (likely, not confirmed — Codex must verify against actual live code)

- `extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php` (`getQuote`, `getDocumentPrice`)
- `extension/PintaNovaPoshtaCod/admin/controller/shipping/pinta_nova_poshta.php` + twig + language files (free-shipping threshold setting, if not already present)
- `system/library/url.php` (lines ~62-66, SimpleCheckout rewrite)
- Hutko payment controller (read-only check of `payment_hutko_shipping_include` usage)
- `catalog/model/total/shipping.php` (totals calculation — confirm how it reads `session.shipping_method` cost)
- CRM/Apps Script order-sync payload builder (read-only check of shipping field shape)

## 7. Acceptance criteria (measurable)

1. Guest checkout, cart total < threshold: order totals show a non-zero shipping line matching (±20%, cross-checked against novaposhta.ua calculator) the real NP tariff for the selected branch/locker.
2. Cart total ≥ threshold: shipping line = ₴0, method title = "За наш кошт"; changing the threshold in admin (e.g. 1500 → 2000) moves the boundary without a code change.
3. Hutko payment amount = order total − shipping in both cases above (cross-check against order totals breakdown).
4. `checkout/checkout` link resolves to stock checkout for a fresh guest session (no SimpleCheckout route) — verify via response URL/HTML, not assumption.
5. SimpleCheckout module remains installed and re-enablable (status toggle in admin still present) — rollback path intact.
6. CRM readback (`action=orders&status=active&limit=1`) shows the new order with expected field shape; if shape changed beyond value, explicitly flagged as **needs owner check** in the report, not silently shipped.
7. Zero draft/void orders created during the full guest + logged-in checkout flows (verify ST-2b.1/2b.4 guarantee still holds).
8. No diff outside the files listed in §6, unless explicitly justified in the report.

## 8. QA / smoke test

Run **bs-checkout-smoke** (11-step manual checkout/payment smoke) — mandatory, this is a HIGH-RISK checkout + payment task. Minimum inline set:

1. Guest: cart < threshold → NP branch → real shipping cost shown → Hutko payment page shows amount = total − shipping (do NOT pay).
2. Same with parcel locker (поштомат).
3. Guest: cart ≥ threshold → shipping = ₴0, "За наш кошт", totals correct.
4. Coupon First15 applied → totals correct with real shipping row + discount.
5. Logged-in user with saved address, same checks.
6. Mobile viewport (≤480px).
7. Confirm zero draft orders across all of the above (admin order list, no stray "Чернетка (системний)" beyond expected).
8. Admin: order shows delivery point + TTN button still works (ST-3.5 regression check).
9. CRM sync: `action=orders&status=active&limit=1` readback shows the new order correctly — do not edit the sheet directly.
10. Checkbox fiscalization: receipt flow unchanged (**needs owner check** — owner verifies manually).

## 9. Rollback note

- Before any change: file-level backup of `extension/PintaNovaPoshtaCod/`, `system/library/url.php`, and the Hutko controller (read state only, but back up in case investigation requires a touch) → `_patch_backups/st2c-real-shipping-cutover-YYYYMMDD_HHMMSS/`.
- `url.php` rollback: restore the ~5 removed lines to re-enable the SimpleCheckout rewrite.
- `getQuote` rollback: restore `cost => 0` if real cost causes Hutko amount mismatches.
- Settings changes: record exact `ocp5_setting` keys + old values in the report before changing.
- If checkout breaks post-cutover: revert `url.php`, confirm SimpleCheckout resumes serving traffic, notify owner immediately (payment-path incident).

## 10. Recommended status after execution

- Phase 0 report delivered → ST-2c: `In progress`, findings noted in Notion + dashboard.
- Phase 1 done + smoke passed + owner manual check (Hutko amount, fiscalization, CRM row, SimpleCheckout still toggleable) → ST-2c: `Done`; unblocks ST-6 (disable SimpleCheckout).
- Update Notion R-13.5 master log entry to reflect ST-2c completion and current state of the whole NP/checkout series (last entry in Notion is from 2026-06-14 — stale relative to ST-3.5–3.7 which are Done as of 2026-06-25).
- Update `booster-dashboard.html` `ROADMAP_FLOW` in the same session (canon: `ROADMAP_SOP.md` §3-4) — both R-13.5 subtasks currently marked `todo` ("Реальна вартість доставки в замовленні", "Увімкнути новий чекаут для всіх") should flip to reflect actual status.
