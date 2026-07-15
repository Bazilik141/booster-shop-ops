# Codex Handoff — CHECKOUT-004: wire real promo code (coupon/First15) into the new checkout

Date: 2026-07-15. Parent: RD-13 (checkout reskin) / ST-2b.5 (coupon backend). **HIGH-RISK zone** (checkout page, order totals) → `bs-checkout-smoke` mandatory before Done. SEO surface: none → `bs-seo-risk-gate` n/a.

> Live-file task: repo has no local copy of `checkout.twig` / `confirm.twig` / `checkout-reskin.js` (owner-server-only, per CHECKOUT-003 precedent). Codex must anchor against LIVE files.

**Scope note (owner-confirmed):** this task is promo codes only. Switching all customers over to the new checkout is a separate step (ST-2c) and is explicitly NOT part of this task — do not touch the old-checkout redirect / cutover logic.

## 1. Task ID

CHECKOUT-004 — the new checkout (RD-13 reskin) currently shows a **visual-only stub** for the promo code field. Clicking "Застосувати" shows a fake "Промокоди з'являться незабаром" message and submits nothing. A real coupon backend already exists and works on the pre-reskin stock checkout — it was never wired into the RD-13 UI. This task replaces the stub with the real, working flow.

## 2. Context — confirmed in repo

- `ST-2b.5` (2026-06-14, `patches/st2b5a_coupon_first15_stock_checkout_20260614.php`) shipped a real coupon endpoint on the stock checkout: new controller `catalog/controller/checkout/coupon.php` with routes `checkout/coupon.summary`, `checkout/coupon.apply`, `checkout/coupon.remove`; First15 auto-apply on eligible new registrations + reuse guard (blocks reapplying First15 per customer/email with a prior order status>0); totals refresh **without** calling `confirm.confirm` (deferred-confirm-safe, per ST-2b.4/ST-2b.6e constraints).
- `RD-13` reskin (`handoffs/handoff_RD-13_checkout-reskin_2026-07-05.md`, §Promo empty/applied states, lines ~244-263 + ~454-465) originally planned to wire the promo field to this **existing** coupon endpoint.
- `HANDOFF-RD13-checkout-FIXES-round2.md` (2026-07-06/07) walked that back: "Backend... confirmed not ready" and shipped a **stub** instead — input `name="rd13_stub_coupon"`, button `[data-co-promo-stub]`, click handler just reveals a "coming soon" hint, no network call. Everything is tagged `RD13-STUB` for later removal.
- Grep confirms `RD13-STUB` is still present through the latest coupon-touching patch (`patches/RD-13_checkout-reskin-round6_20260708.php`); none of the later RD-13.1A–1J patches (guest/address/CAPTCHA fixes, 2026-07-11–13) touch the promo block.
- ST-2b.5's endpoint predates RD-13 and was owner-QA'd on the stock checkout before the reskin — Codex should confirm it is still live/unmodified before wiring, but it is not "not ready"; it exists.

## 3. Goal

The new checkout's promo code field is fully functional: a customer can apply/remove a real coupon, see the correct discount and updated total, and First15 auto-apply/reuse-block behaves the same as it already does elsewhere. No fake "coming soon" message remains for promo codes. No draft order is created by applying/removing a coupon. The old checkout (SimpleCheckout) and the ST-2c cutover decision are untouched by this task.

## 4. What to change

- Remove the `RD13-STUB` promo/coupon block only (input `rd13_stub_coupon`, `[data-co-promo-stub]` button + its click handler + the "coming soon" hint). **Leave the free-shipping-progress `RD13-STUB` block untouched** — that's a separate, still-unresolved stub outside this task's scope.
- Restore/implement the real promo UI per the original RD-13 design states (`handoff_RD-13_checkout-reskin_2026-07-05.md` lines ~71-72, ~244-263):
  - Empty state: label "Промокод", placeholder "Введіть промокод", button "Застосувати".
  - Applied state: `{CODE} · −{pct}% · Промокод застосовано`, button "Прибрати".
- Wire apply/remove to the existing endpoint from ST-2b.5A: `checkout/coupon.apply` (POST code), `checkout/coupon.remove`, and `checkout/coupon.summary` on page load (so an already-applied coupon, incl. an auto-applied First15, renders correctly on load/refresh).
- After apply/remove, refresh the confirm-panel total from the coupon endpoint's own response — **must not** call `checkout/confirm.confirm` to do this (would create a draft order, per the ST-2b.4/ST-2b.6e invariant).
- Surface the First15 reuse-block error and generic invalid-coupon error inline, matching the existing stock-checkout error text ("Промокод First15 вже був використаний для цього акаунта", etc.).
- Codex should verify the coupon.php endpoint and its response shape against the LIVE server before wiring — do not assume it matches the round-2 mockup 1:1.

## 5. Do not touch

- ST-2c cutover: the redirect/toggle that sends customers to the new checkout, and any "switch everyone over" logic — explicitly out of scope for this task, tracked separately.
- Order-creation gates: ST-2b6e read-only render + explicit `confirm::index($allow_order_write)`, ST-2b6d trusted-click gate, RD-13.1J CAPTCHA POST-payload, ST-2b.1 deferred-confirm flow.
- Hutko `buildRequest`/amount/sign, Checkbox/fiscalization, shipping cost/NP quote logic (ST-2c scope), order statuses.
- The free-shipping-progress `RD13-STUB` block (separate, still pending backend).
- The old checkout (SimpleCheckout extension) — its coupon flow already works; do not modify it.
- `sitemap.xml`, `robots.txt`, canonical, redirects, `.htaccess`, Merchant feed, schema/JSON-LD, DB schema.

## 6. Likely files / areas (verify against LIVE)

- `catalog/controller/checkout/coupon.php` — existing endpoint from ST-2b.5A; confirm live and unmodified.
- `catalog/view/template/checkout/checkout.twig` / `confirm.twig` — RD13-STUB promo markup to remove/replace.
- `catalog/view/javascript/checkout-reskin.js` — stub click handler to remove; real apply/remove/summary AJAX to add.
- Codex should verify against actual project files; re-grep live for every `RD13-STUB` occurrence tagged as promo/coupon before removing.

## 7. Acceptance criteria (measurable)

1. New checkout, valid coupon entered + "Застосувати": total decreases by the coupon amount, UI switches to applied state (`{CODE} · −{pct}%`); `oc_order` row count unchanged (no draft created).
2. "Прибрати": coupon removed, total restored, UI returns to empty state.
3. First15 auto-applies for an eligible new registration and is visible in the new-checkout summary on load (via `coupon.summary`).
4. Reusing First15 on an account/email with a prior order (status>0) → blocked with the existing reuse error; checkout remains usable.
5. Invalid coupon code → single clear inline error, no broken layout.
6. No `confirm.confirm` request fires as a result of apply/remove (Network tab check).
7. On order success, coupon/discount is cleared (no leftover discount on next cart) — reuse existing success-side cleanup, don't re-implement.
8. Grep for `rd13_stub_coupon` / `data-co-promo-stub` on the deployed files returns zero matches; free-shipping stub markup/comments remain untouched.
9. Old checkout (SimpleCheckout) coupon flow unaffected.

## 8. QA / smoke test

Full 11-step `bs-checkout-smoke` on the **new checkout** after deploy, plus: apply/remove coupon (total correct, `oc_order` count steady), First15 auto-apply + reuse-block, invalid coupon, confirm that no `confirm.confirm` call fires from coupon actions. Matrix: {guest, logged-in} × {apply valid, remove, First15 auto, First15 reuse, invalid}. Record max order ID + status-0 draft count before/after.

## 9. Rollback note

Back up every changed file to `_patch_backups/CHECKOUT-004_<...>-<timestamp>/...` before writing; restore those files and clear `cache.*` + `template/*` via `DIR_CACHE` to roll back. This task is independent of ST-2c — reverting it does not affect the cutover decision either way.

## 10. Recommended status after execution

Patch → `На перевірці` → owner QA (incl. `bs-checkout-smoke` + §7 checks) → `Готово`. Does **not** unblock or imply ST-2c — cutover stays a separate, owner-gated step.
