# Codex Handoff — ST-2c: real NP shipping cost in totals (threshold 2000 грн) + site-wide free-shipping text sweep

Date: 2026-07-18. Parent: ST-2c (supersedes the shipping-cost half of `handoff_ST-2c_real-shipping-cost-cutover_2026-07-02.md`). **HIGH-RISK zone** (Part A: checkout totals + payment amount) → `bs-checkout-smoke` mandatory before Done on Part A. Part B (content) is low technical risk but customer-facing copy — no `bs-seo-risk-gate` trigger (no sitemap/robots/canonical/redirects/schema touched), but wording needs owner/Claude review before publish.

> Live-file task: repo has no local copy of the checkout/shipping/content files (owner-server-only). Codex must anchor against LIVE files.

**Scope note (owner-confirmed 2026-07-18):** this handoff covers only the shipping-cost logic and the matching content sweep. The `url.php` cutover (redirecting all customers to the new checkout) stays a separate, later step — same as CHECKOUT-004 was kept separate from ST-2c cutover. Do not touch `system/library/url.php` here.

**Owner-confirmed rule (2026-07-18, final — not an assumption):** free-shipping eligibility is evaluated on the cart subtotal **before** any coupon/First15 discount, and applies to **all** Nova Poshta delivery modes (відділення, поштомат, адресна/кур'єр). This matches how the existing RD13-STUB progress bar already reads the subtotal from the DOM.

## 1. Task ID

ST-2c (Part A + Part B) — **A:** replace the hardcoded `cost => 0` Nova Poshta shipping crutch with the real API price in order totals, with an admin-editable free-shipping threshold defaulting to **2000 грн** (not the earlier-discussed 1500), and wire the existing RD13-STUB free-shipping progress bar in the new checkout to that real value instead of its hardcoded stub constant. **B:** find every place on the live site that states a free-shipping threshold in text and update it to match (2000 грн), after owner/Claude review of the exact findings — do not mass-replace blind.

## 2. Context — confirmed in repo

- `handoff_ST-2_stock-checkout-migration_2026-06-12.md` §10/§14 and `handoff_ST-2c_real-shipping-cost-cutover_2026-07-02.md` already specify the mechanism: `getQuote` in the Pinta NP shipping model hardcodes `cost => 0` (the "Hutko crutch" — today shipping is never in totals, so Hutko charges cart total only). Real fix: cost = `getDocumentPrice` (UAH), converted to store currency; if cart subtotal ≥ threshold → cost = 0, title «За наш кошт»; else real cost, plain title. Threshold must be a persistent admin setting (`shipping_pinta_nova_poshta_free_from` or equivalent — confirm exact key against live code, do not assume it matches the name in the 2026-07-02 handoff verbatim), not hardcoded — **default value 2000, not the 1500 referenced in older docs.**
- `plans/RD-13_followup_stock-coupon-free-shipping_20260706.md` (2026-07-06) confirmed at the time: "No durable free-shipping threshold setting exists" and explicitly said "The UI progress bar must not ship before the real order-total rule exists." That condition is what this handoff satisfies.
- The new-checkout UI already has a **stub** for this, tagged `RD13-STUB` in `catalog/view/javascript/checkout-reskin.js` (`renderFreeShippingStub()`): reads the real subtotal from the DOM, but `FREE_SHIP_THRESHOLD = 2000` is a hardcoded JS constant with a comment "delete this constant and read the real config value once the free-shipping backend ships." That moment is now — Part A must remove the `RD13-STUB` tag on this block and wire it to the real config value (via the same coupon/summary-style endpoint pattern already established, or confirm/totals response — Codex to decide the lowest-risk wiring against live code, but must not call `checkout/confirm.confirm` to do it, same constraint as every other totals-refresh in this checkout).
- No ST-2c patch has been executed yet (verified — nothing in `patches/`/`diagnostics/` for ST-2c or shipping cost). This is greenfield within the already-scoped design.
- Since 2026-07-02 the checkout has changed substantially (ACC-002 A–F, RD-13.1A–J, CHECKOUT-004–007A) — Codex must re-run Phase 0 diagnostics fresh against current live files, not assume anything from the older handoff still matches line-for-line.
- Do not confuse this with `CHECKOUT-002` (unrelated: checkout submit latency/loader) or the `url.php` cutover (unrelated: traffic routing, separate step).

## 3. Goal

**Part A:** Order totals show the real Nova Poshta shipping cost. Cart subtotal ≥ 2000 грн (pre-discount, per assumption above) → shipping = 0, "За наш кошт", for all NP delivery modes. Hutko charged amount still equals order total − shipping. The free-shipping progress bar in the new checkout reads the same real threshold/subtotal, no more hardcoded stub.

**Part B:** Every customer-facing page that currently states a free-shipping threshold shows 2000 грн, consistently, with no stray old-amount text left anywhere Codex can find.

## 4. What to change

### Part A — real shipping cost + threshold

**Phase 0 (read-only diagnostics, do first):**
1. Confirm current live state of `cost => 0` in the Pinta NP shipping model `getQuote` (exact file/line).
2. Confirm whether a free-shipping threshold admin setting already exists anywhere (may have partially shipped since 2026-07-02 — check, do not assume either way); if yes, confirm its current value and whether it's already 2000 or still something else.
3. Confirm current `payment_hutko_shipping_include` value and where Hutko's controller reads it (read-only — do not modify Hutko `buildRequest`/amount/sign/api).
4. Confirm the current `RD13-STUB` free-shipping block in `checkout-reskin.js` still matches what's described above (re-grep live, do not assume the June/July snapshot is current).
5. Confirm CRM/Apps Script order-sync payload shape for shipping fields (`action=orders&status=active&limit=1`, read-only) — flag if a real shipping line changes the payload shape.

**Phase 1 (implementation, only after Phase 0):**
1. Replace hardcoded `cost => 0` with real `getDocumentPrice` cost; add/confirm the admin-editable threshold setting, default **2000**; cart subtotal ≥ threshold → cost = 0, title «За наш кошт»; below threshold → real cost, plain title (no "≈" prefix — it's now the real charged amount).
2. Verify Hutko amount = total − shipping still nets out correctly with a non-zero shipping line (read-only check; if netting breaks, stop and report — do not patch Hutko itself without separate approval).
3. Remove the `RD13-STUB` free-shipping tag and hardcoded `2000` JS constant in `checkout-reskin.js`; wire `renderFreeShippingStub()` (or its replacement) to the real threshold + real subtotal from the server, refreshed the same safe way other totals are refreshed in this checkout (no `confirm.confirm` call).
4. Confirm the threshold change is reflected consistently everywhere it's computed client-side (cart-page shipping estimate, if any, per §6).

### Part B — site-wide free-shipping text sweep

**Phase 0 (discovery only — report back, do not edit yet):**
1. Grep all template/PHP/JS/Twig files for existing free-shipping-threshold text mentions (likely phrasing: «безкоштовна доставка від», «безкоштовна доставка при замовленні від», similar).
2. Separately check **database-stored content** — OpenCart product/category/information page bodies are typically stored in `oc_information_description`, `oc_category_description`, `oc_product_description` (and similar), not template files. A file-only grep will miss CMS-edited content. Check these tables (read-only query) for the same phrasing.
3. Check footer, header banners, any promo module content, and FAQ/information pages specifically (most likely locations for this claim).
4. For every match found, report: exact page/file/DB row, the exact current text (verbatim, with the old amount), and the surrounding sentence — **do not just report the bare number**, since a stray "1500" or similar could be an unrelated price/SKU/date and must not be swept up by accident.
5. Deliver this as a findings list in the report. **Stop here — do not edit content yet.**

**Phase 1 (edit — only after owner/Claude approves the Phase 0 findings list):**
1. Update only the confirmed free-shipping-threshold mentions to the new amount (2000 грн), preserving each page's existing sentence structure/tone — do not rewrite surrounding copy, do not add claims not already there (no fake guarantees, no invented specifics, per house content rules).
2. If a mention is baked into an image/banner graphic (not text), it cannot be patched here — list it separately in the report as a design follow-up, do not attempt to edit an image.
3. Re-grep after edit to confirm no old-amount free-shipping mention remains anywhere found in Phase 0.

## 5. Do not touch

- `system/library/url.php` / the checkout cutover itself — explicitly separate, later step.
- Hutko `buildRequest`/amount/sign/api/redirect logic itself (read-only verification only).
- Checkbox fiscalization — read-only, no changes.
- The single `confirm.confirm` caller guarantee (ST-2b.1/ST-2b.4/ST-2b6e/ST-2b6d) — no new caller, no auto-confirm path.
- Coupon/First15 logic (CHECKOUT-004–007A) — Part A must not change how/when coupons apply; if the free-shipping subtotal calculation needs to read cart data, read it, do not modify coupon application order.
- Any text/number that is not a free-shipping-threshold mention, even if it contains "1500" or similar — Part B is scoped to that one specific claim, not a general copy audit.
- `sitemap.xml`, `robots.txt`, canonical, redirects, `.htaccess`, hreflang, Merchant feed, Product schema/JSON-LD.
- DB schema — no migrations without separate approval + rollback SQL in the patch header.
- Admin TTN button / ST-3.5–3.7 flow — unrelated.
- CRM/Apps Script order-sync payload — if the shipping field shape changes beyond value (not just 0 → real number), that's a **needs owner check**, not a silent change.

## 6. Likely files / areas (verify against LIVE — do not assume from older handoffs)

- Pinta NP shipping model (`getQuote`, `getDocumentPrice`) and its admin controller/twig/language files (threshold setting).
- `catalog/view/javascript/checkout-reskin.js` (`RD13-STUB` free-shipping block).
- Hutko payment controller (read-only check of `payment_hutko_shipping_include`).
- `catalog/model/total/shipping.php` or equivalent totals calculation.
- CRM/Apps Script order-sync payload builder (read-only check).
- For Part B: `oc_information_description`, `oc_category_description`, `oc_product_description` (or live equivalents), footer/header template partials, any promo/banner module template.

## 7. Acceptance criteria (measurable)

**Part A:**
1. Guest checkout, cart subtotal (pre-discount) < 2000: order totals show a non-zero shipping line matching the real NP tariff (±20% cross-check against novaposhta.ua) for the selected point, for every delivery mode (відділення/поштомат/адресна).
2. Cart subtotal ≥ 2000: shipping = ₴0, title "За наш кошт", for every delivery mode; changing the admin threshold value moves the boundary without a code change.
3. Hutko payment amount = order total − shipping in both cases (cross-checked against the order's totals breakdown).
4. New-checkout free-shipping progress bar shows the real threshold (2000) and real subtotal, no `RD13-STUB` tag remaining, no hardcoded JS constant.
5. Zero draft/void orders created during the full guest + logged-in checkout flows (ST-2b.1/2b.4 guarantee still holds).
6. CRM readback (`action=orders&status=active&limit=1`) shows the new order with the expected shipping field shape; any shape change beyond value is explicitly flagged, not silently shipped.

**Part B:**
7. Phase 0 report lists every found free-shipping-threshold mention with exact text and location, before any edit.
8. After Phase 1 (owner-approved edits only): every confirmed mention shows 2000 грн; re-grep confirms no old-amount mention remains among the found locations.
9. No unrelated numeric text (prices, SKUs, dates) was touched.

## 8. QA / smoke test

Part A: full `bs-checkout-smoke`, plus matrix {guest, logged-in} × {відділення, поштомат, адресна} × {subtotal <2000, ≥2000} × {with First15, without} — verify shipping line, order total, and Hutko amount agree in every cell, and that the free-shipping rule uses the confirmed subtotal-vs-discounted-total basis. Verify CRM readback shape.
Part B: visual check of every edited page live, confirm text renders correctly (no broken layout from length change), confirm no old amount remains anywhere in the Phase 0 findings list.

## 9. Rollback note

Part A: back up every changed file to `_patch_backups/ST-2c_real-shipping-cost-content-sweep_20260718-<timestamp>/`; restore + clear `cache.*`/`template/*` via `DIR_CACHE`. Reverting Part A returns shipping to the 0-crutch state (current safe fallback) — Hutko math is unaffected by rollback since it already assumes 0 today.
Part B: back up every edited content row/file the same way; DB content edits should be restorable from the same backup (store the original text value per row, not just a file diff, since these are DB writes, not file writes).

## 10. Recommended status after execution

Part A → `На перевірці` → owner QA (`bs-checkout-smoke`, both COD and Hutko, all three NP modes, both sides of the 2000 threshold) → `Готово`. Part B Phase 0 → owner/Claude reviews findings list → approve → Phase 1 edits → visual QA → `Готово`. Neither part implies or unblocks the `url.php` cutover (ST-2c subtask 2) — that stays a separate, explicitly owner-gated step.
