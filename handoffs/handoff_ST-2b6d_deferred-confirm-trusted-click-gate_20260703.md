# Codex Handoff — ST-2b.6 / closing ST-2b.4 gap: trusted-click gate on deferred «Оформити замовлення» button

Date: 2026-07-03. Parent: ST-2b.4 (original spec, 2026-06-14 — Phase 1 of that handoff called for this gate but it never shipped) / discovered missing during ST-2b.6 Phase 0a. **Independent of `handoff_ST-2b6c_hutko-autoselect-fix_20260703.md`** — separate patch, separate backup/rollback unit, can deploy in the same window but do not merge into one diff. **HIGH-RISK** (checkout / payment / order creation) → `bs-checkout-smoke` mandatory, full 11-step.

## 1. Task ID

Add the `event.isTrusted` + genuine-activation gate to the deferred confirm button (`#checkout-confirm [data-bs-deferred-confirm]` / `button#bs-button-confirm-deferred`) that ST-2b.4's original Phase 1 spec called for, but which ST-2b.6 Phase 0a confirmed is **not present** in the live code today.

## 2. Context

- ST-2b.4 (2026-06-14, §4 Phase 1) specified: "Gate the deferred-button action so it runs only on a genuine user pointer click of «Оформити»... require `event.isTrusted` and pointer-type activation; ignore activation that originates from payment/shipping radio interaction or stray Enter."
- ST-2b.6 Phase 0a (2026-07-03) re-grepped and instrumented the live checkout and found this gate was never implemented — the deferred handler fires on any click reaching the button, trusted or not, with no check.
- Separately, Phase 0a captured one case where a genuine (`isTrusted: true`) click on the real button created an order ~2 seconds after a page reload, which the owner stated they had not intentionally made. **This gate will not necessarily explain or fix that specific case** — the click was trusted, so a trust check alone doesn't change its outcome. That mystery (accidental click landing on the button right after a fast page transition) is a separate, still-open question if it recurs. This handoff closes the documented ST-2b.4 gap on its own merits — defense against synthetic/programmatic triggers and stray keyboard activation from elsewhere — not as a fix for the reload-click case.
- `bsCheckoutLoadConfirmAndSubmit(trigger, event)` already accepts and threads through an `event` parameter (added during ST-2b.6 Phase 0a/0b instrumentation), so the plumbing to inspect the triggering event is already in place — this reduces the size of the change needed here.

## 3. Goal

The deferred «Оформити замовлення» button only proceeds to `confirm.confirm` on a genuine user activation: a real mouse/touch click on the button itself, or a real keyboard activation (Enter/Space) while the button itself is properly focused. It must **not** proceed on: programmatic/synthetic dispatch (`isTrusted: false`), or an activation event that didn't originate from the button itself (e.g. a stray Enter fired while focus was on a payment/shipping radio, if such an event could ever reach this handler through bubbling or delegation).

**Accessibility constraint — important:** keyboard-only users must still be able to place an order by tabbing to the button and pressing Enter/Space while it is genuinely focused. Do not require a mouse/pointer specifically — require *trusted and originating from the button itself*, not "pointer-only."

## 4. What to change

- In the `click.bsSt2b1DeferredConfirm` handler (or wherever the deferred button's activation is bound), before calling `bsCheckoutLoadConfirmAndSubmit`, add a guard:
  - Reject (do nothing, no `confirm.confirm` call) if `event.isTrusted !== true`.
  - Reject if the event's `target`/`currentTarget` is not the deferred button itself (guards against delegation/bubbling from an unrelated control).
  - Allow both mouse/touch clicks and keyboard Enter/Space **when genuinely targeting the button** (do not restrict to `pointerType` only — that would break keyboard accessibility).
- Keep the existing double-submit / in-flight guard (from ST-2b.1) exactly as is — this gate is additive, not a replacement.
- Reuse the existing `event` parameter already threaded through `bsCheckoutLoadConfirmAndSubmit(trigger, event)` from the Phase 0a/0b instrumentation rather than re-plumbing it.
- Do not add any new debounce/timing-window logic (e.g. "ignore clicks in the first N ms after page load") — that is a different, separate hardening idea for the reload-click mystery and is explicitly **out of scope** here unless the owner asks for it as its own task.

## 5. Do not touch

- `handoff_ST-2b6c_hutko-autoselect-fix_20260703.md`'s changes — keep this a separate patch/backup/rollback unit even if deployed in the same window.
- Order-creation logic inside `confirm.confirm` itself, Hutko `buildRequest`/amount/fiscalization, coupon/First15 totals.
- ST-2a.4 void-status net (keep as fallback).
- ST-2b.2/2b.3 success-page and summary-capture logic.
- CRM payload shape, NP model, DB schema, order_status config.
- `sitemap.xml`, `robots.txt`, canonical, redirects, `.htaccess`, Merchant feed, schema/JSON-LD.
- Do not weaken or remove the Phase 0/0b diagnostic logging — keep it in place so the gate's effect is observable in the same logs, at least until the owner confirms it's no longer needed.

## 6. Likely files / areas (verify against LIVE; clear template cache after)

- `catalog/view/template/checkout/checkout.twig` — `click.bsSt2b1DeferredConfirm` handler, `bsCheckoutLoadConfirmAndSubmit` entry point. Same file as ST-2b6c but a distinct, non-overlapping section of it (the click-gating point, not the payment-reset/auto-select logic) — Codex should confirm no line-range overlap with the other patch before applying, and apply independently.
- Codex must verify current line numbers against live (Phase 0a/0b's own edits already changed this file once).

## 7. Acceptance criteria (measurable)

1. Programmatic/synthetic dispatch of a click on the deferred button (e.g. via DevTools console `document.querySelector('[data-bs-deferred-confirm]').dispatchEvent(new Event('click'))` or jQuery `.trigger('click')`) does **not** call `confirm.confirm` and does **not** create an order.
2. A genuine mouse click on the button still works exactly as before: `confirm.confirm` fires once, order created once, proceeds to payment.
3. A genuine keyboard activation (Tab to the button, confirm it's focused, press Enter or Space) still places the order — accessibility preserved, verified manually.
4. An Enter keypress while focus is on a payment or shipping radio (not the button) does not trigger the deferred confirm action.
5. No change to the zero-draft-order guarantee elsewhere (switching payment/shipping methods, address changes) — re-verify `oc_order` count stays flat outside of an explicit, genuine place-order activation.
6. No interference with ST-2b6c's fix if both are deployed together — verify independently.

## 8. QA / smoke test

`bs-checkout-smoke`, full 11-step, sandbox/staging only. Additional targeted checks:
1. Attempt a programmatic/synthetic click dispatch on the deferred button via DevTools console → confirm no order created, no `confirm.confirm` network call, no `isAuto`/synthetic entry in the existing diagnostic log showing a successful proceed.
2. Place a real test order via mouse click → confirm works, exactly one order.
3. Place a real test order via keyboard only (Tab + Enter/Space, no mouse) → confirm works, exactly one order — this is an accessibility regression check, do not skip it.
4. Rapid double-click on the button → confirm still only one order (existing double-submit guard unaffected).

## 9. Rollback

Backup the edited file to `_patch_backups/ST-2b6d-*` before write (separate backup id from ST-2b6c); restore + clear OpenCart `template/`+`cache.*` on any regression, in particular if keyboard-only order placement breaks.

## 10. Recommended status after execution

Once QA passes (including the keyboard-accessibility check), this closes the gap left open since ST-2b.4 (2026-06-14) — update that task's history/notes to reflect the gate is now actually live, not just planned. Independent of ST-2b6c's status; both should be verified separately before ST-2c cutover proceeds, since ST-2c's premise (per its own handoff) depends on the zero-draft-order guarantee holding, and this gate is part of that guarantee's intended design. Update `booster-dashboard.html` ROADMAP_FLOW and Notion in the same session per `ROADMAP_SOP.md §3-4` once owner QA confirms.
