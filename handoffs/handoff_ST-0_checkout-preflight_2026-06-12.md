# Codex Handoff — ST-0: Checkout pre-flight (READ-ONLY diagnostics + inventory)

Date: 2026-06-12. Parent: plan_R-13.5+_checkout-address-redesign (v2). Decision: **Path B — migrate to stock OC4 checkout** (approved by owner).
Mode: **strictly read-only on live.** No file writes, no DB writes, no settings changes. Output = report only.

## 1. Task ID
ST-0 — Pre-flight inventory before stock-checkout migration (ST-2).

## 2. Context
Live checkout = Pinta SimpleCheckout v1.5.2 (`extension/SimpleCheckout/`, module status=1), heavily patched locally (first15, ga4/tech015, register-polish — see `.before-*` files in module dir). Stock OC4 checkout (`checkout/checkout`) exists in parallel, styled (r11b), Pinta NP events fixed by r135. Confirmed: clients hit `route=extension/SimpleCheckout/module/pinta_simple_checkout` (access log). `catalog/controller/checkout/cart.php` links `checkout` to `checkout/checkout` — so an unknown redirect/link rewrites the path somewhere.

## 3. Goal
Complete, file-referenced map of everything ST-2 must port or replace, so the migration handoff contains zero unknowns.

## 4. What to do (read-only)
1. **Wiring:** find exactly how cart→SimpleCheckout routing happens (candidates: redirect in stock `checkout/checkout` rendering chain, JS redirect in a twig, .htaccess rule, header/mini-cart link, theme JS). Deliver: file + line.
2. **Kostyl inventory in SimpleCheckout** (`catalog/view/template/module/checkout.twig` 1701 lines, `catalog/controller/module/pinta_simple_checkout.php` 4126 lines): list every local customization vs vanilla 1.5.2 (diff against `ocartdata/storage/marketplace/SimpleCheckout.ocmod.zip`): first15 logic, ga4 events (tech015), oferta texts/branches (guest checkbox vs logged-in text), phone validation/masks, NP free-text address fields (K1), poshtomat-via-comment note (K2), «Мої адреси» note (K4), promo-code field, anything else. For each: what it does + where it must land in stock checkout (or be dropped).
3. **Stock checkout current state on live:** open `index.php?route=checkout/checkout` directly (test session, no order placement): does it render? Do NP events (r135) inject the form? Which blocks work/fail? List gaps.
4. **Stock cart coupon:** confirm coupon/promo entry points currently available on stock cart/checkout (cart_list.twig has modules block commented out) — what to re-enable for promo field parity.
5. **Success/failure flow:** confirm checkout/success + failure pages are shared (already redesigned r11) and SimpleCheckout points to them — i.e. no porting needed there.
6. **Session/data compatibility:** check what SimpleCheckout writes to session/order that stock flow names differently (e.g. comment, custom fields) — list mapping for ST-2.

## 5. Do not touch
EVERYTHING. Read-only. No test orders to live payment. No settings/status flips (SimpleCheckout stays on). No cache clears needed.

## 6. Likely files
`extension/SimpleCheckout/**`, `catalog/controller/checkout/*`, `catalog/view/template/checkout/*`, `extension/ukrainian/catalog/language/uk-ua/checkout/*`, `.htaccess`, `catalog/view/template/common/header.twig`, marketplace zips in `ocartdata/storage/marketplace/`.

## 7. Acceptance criteria
1. Wiring answer: exact file:line of cart→SimpleCheckout switch.
2. Inventory table: customization → source file:line → port target in stock checkout → port/drop decision proposal.
3. Stock checkout gap list from live render test.
4. No writes performed (confirm in report).

## 8. QA / smoke
n/a (read-only). Report reviewed by Claude before ST-2 handoff is written.

## 9. Rollback note
n/a — no changes.

## 10. Recommended status after execution
ST-0 `Done` → Claude writes ST-2 handoff (Path B) from the report.
