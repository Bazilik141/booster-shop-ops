# Codex Handoff — ST-2b.6 Phase 0b: silent payment-method reset to Hutko + old/new checkout desync (diagnostics only)

Date: 2026-07-03. Parent: ST-2b.6 (this is round 2 of Phase 0 — builds on `handoff_ST-2b6_hutko-phantom-order-tab-restore_20260703.md` and its diagnostics report, does not replace them). **HIGH-RISK** (checkout / payment / Hutko / fiscalization) → `bs-checkout-smoke` mandatory before any Phase 1 fix. **Diagnostic-first**, no behavior change.

**Priority note:** this angle is likely more serious than the original tab-restore framing. Owner recalls real customer orders (last few weeks) where the customer visibly selected «Оплата при доставці» (COD) but the admin recorded Hutko as payment method with no payment actually processed. If confirmed as the same root cause, this is not just a test-order nuisance — it risks live orders being mis-tagged as paid-via-Hutko when they weren't. Treat evidence-gathering here as urgent, but still do not ship a fix without evidence — same diagnostic-first discipline as Phase 0a.

## 1. Task ID

ST-2b.6, Phase 0 round 2 ("0b") — determine whether/where `paymentCode` gets silently set to `hutko.hutko` without a direct user click on that radio, and whether the confirm-panel summary can show stale/cached payment+shipping text that diverges from what's actually selected on screen — specifically around navigation between the old (SimpleCheckout) and new (stock) checkout.

## 2. Context — what Phase 0a (round 1) established, and new owner observations since

**From Phase 0a (already executed, see its report):**
- Confirmed live: no `event.isTrusted`/pointer-activation gate exists on the deferred-confirm button, contrary to what the original ST-2b.6 handoff assumed was already live from ST-2b.4.
- One clean repro captured a **genuine, trusted click** on the real «Оформити замовлення» button (`button#bs-button-confirm-deferred`) → `confirm.confirm` → order created successfully, ~2 seconds after a page `reload`. Owner subsequently stated they had **not** intentionally clicked confirm for that order. This is an open contradiction: the browser reports a real trusted click on the real button, but the owner is confident they didn't press it. Possible explanation (unconfirmed): a click intended for content on the *previous* page state landed on the confirm button because it rendered in the same screen position immediately after the fast reload/navigation — i.e., a click-through/timing issue, not event forgery. Not verified either way yet.
- A separate order was created (owner confirms) *before* any click on their part at all — the clearest phantom-order case so far — but it happened before diagnostic logging was active, so there is no captured trigger for it.

**New since then (owner-reported, this round):**
- Owner recalls: in the past few weeks, a small number of real customer orders show the customer's chosen payment method as COD/post-payment in their own recollection or in what should have been selected, but the admin panel recorded Hutko as the payment method, with **no actual Hutko payment processed**. Owner attributes this to "our own crutch" — a known default/auto-selection of Hutko as payment method (confirmed present in diagnostics: `paymentCode: "hutko.hutko"` already set at `checkout:init` time, before any interaction, in multiple captured sessions).
- Owner navigated old checkout (SimpleCheckout module, `route=extension/...simple_checkout`) → selected a shipping/payment combination there → followed a link to the new (stock) checkout → observed the new checkout's confirm-panel text (`Доставка: ...` / `Оплата: ...`) showing **different** values than what was visibly selected, until the owner clicked the radios again on the new page, after which the panel text corrected itself. Screenshot evidence: old checkout showed «доставка у поштомат» + «Оплата карткою через Hutko» selected; new checkout's confirm panel initially read «Доставка: Нова пошта: доставка за адресою» / «Оплата: Оплата при доставці (накладений платіж)» — neither matching the old checkout's selection.
- When the owner ran the Phase 0a diagnostic read command (`window.bsSt2b6ReadDiagnostics()`) while on the **old** checkout page, it threw `TypeError: ... is not a function`. This confirms the Phase 0a instrumentation only exists on `catalog/view/template/checkout/checkout.twig` (the new checkout) — there is **zero observability** on the old (SimpleCheckout) checkout. If the actual reset/desync happens while the customer is still on the old checkout, or exactly at the handoff between old and new, Phase 0a's instrumentation cannot see it.

**Working hypothesis (unconfirmed):** the confirm-panel summary and/or the actual submitted payment code can, under some sequence involving the old→new checkout transition, reflect a stale/cached state (possibly related to the ST-2b.3 client-side cached-summary mechanism, `bsCheckoutCachedSummaryHtml`/`bsCheckoutInitialSummaryHtml`) rather than the live selected radio, and/or some code path resets the payment selection back to the Hutko default. This could explain both the historical live-customer mispayment reports and the test phantom orders. **Do not assume this is confirmed — it is the lead to chase, not a diagnosis.**

## 3. Goal

Capture direct evidence (or documented absence after repeated attempts) of:
1. `paymentCode` changing to `hutko.hutko` through a path other than a direct user click on that radio — with a stack trace identifying what triggered it.
2. Whether the confirm-panel summary text can diverge from the live-selected shipping/payment radios, particularly across an old-checkout → new-checkout transition.
3. Where in the live code (new checkout, and old SimpleCheckout module if accessible) a default/fallback assignment to `hutko` as payment code exists.

No behavior change in this phase.

## 4. What to change (Phase 0b — diagnostics only)

- Instrument the payment-code state itself: log every time the value read by `$('#input-payment-code').val()` (or whatever the live equivalent is — verify) changes, whether from a user click on a payment radio or from any script-driven assignment. Capture: old value, new value, stack trace, and whether the change coincides with a click event vs. running with no event on the call stack (i.e., called from page-load/init code, an AJAX callback, etc.).
- Locate and instrument whatever sets the **default** payment selection on page load (the mechanism that results in `paymentCode: "hutko.hutko"` being present at `checkout:init` before any user interaction, confirmed in existing Phase 0a logs). Log where this default gets applied and under what condition (e.g., always, or only when session/cart has some prior state).
- At the moment the confirm panel renders/updates its summary text, log both (a) the text/values the panel is about to display and (b) the live value of the payment/shipping radios at that same instant, so a divergence between the two is directly visible in the log rather than requiring a screenshot comparison.
- Re-grep the live tree (`catalog/view/template/checkout/*.twig`, active extensions including SimpleCheckout) specifically for hardcoded `hutko` assignments used as a default or fallback (not just display labels) — report every match with file + line context, do not assume any of them is "the" cause without evidence.
- Document explicitly whether instrumenting the **old** (SimpleCheckout) checkout template is feasible within this patch's scope — if the reset plausibly happens there, Phase 0a's blind spot needs to close. If adding logging to the SimpleCheckout module is out of scope or too risky for this patch, say so clearly and flag it as a follow-up decision for the owner rather than skipping it silently.
- Do not remove or alter the existing Hutko-default selection behavior yet. Do not change the ST-2b.3 cached-summary mechanism's behavior — only observe it.

## 5. Do not touch

Same restricted list as Phase 0a: Hutko `buildRequest`/amount/sign/api, `payment_hutko_shipping_include`, Checkbox/fiscalization, `getQuote` cost, coupon/First15 totals, ST-2a.4 void-status net, ST-2b.1 deferred-confirm trusted-click-adjacent logic (already known to lack a gate — do not add one yet, that's a Phase 1 decision), ST-2b.2/2b.3 success-page and summary-capture logic (observe, don't rewrite), CRM payload field shape, NP model, DB schema, order_status config, `sitemap.xml`, `robots.txt`, canonical, redirects, `.htaccess`, Merchant feed, schema/JSON-LD. Additionally: do not modify SimpleCheckout module's checkout/order logic — read-only instrumentation only, if added there at all, and only after confirming it's safe to touch a module that's still the live rollback path for ST-2c.

## 6. Likely files / areas (verify against LIVE; clear template cache after)

- `catalog/view/template/checkout/checkout.twig` — payment-code read/write sites, default-selection logic, confirm-panel render/update path, ST-2b.3 cached-summary variables.
- Possibly a separate `payment_method.twig` or similar partial — Phase 0a's report said no `confirm.confirm` caller remains there, but the *default selection* logic may still live there or in a controller — unconfirmed, Codex must verify.
- SimpleCheckout module templates/controllers (path unconfirmed — Codex must locate; likely under `extension/` given the route seen was `route=extension/...simple_checkout`) — for the "is there zero observability here" question and for the hardcoded-`hutko` grep.
- Codex should verify against actual project files; do not assume file paths above are complete or correct.

## 7. Acceptance criteria (measurable)

1. A captured log entry (or clearly documented absence after at least 3 varied repro attempts) showing `paymentCode` becoming `hutko.hutko` via a non-click code path, with a stack trace pinpointing the responsible function.
2. A captured comparison (or documented absence of divergence) showing confirm-panel displayed text vs. live radio state at the same timestamp, across at least one old-checkout → new-checkout transition.
3. A list of every live code location that assigns `hutko` as a default/fallback payment code (file + line), with a plain statement of which one(s), if any, plausibly explain the reset — flagged as "candidate", not "confirmed cause", unless the log evidence directly shows it firing.
4. Explicit statement on whether SimpleCheckout-side instrumentation was added, and if not, why not.
5. No order-creation behavior change from this patch — same before/after `oc_order` count check as Phase 0a.

## 8. QA / smoke test

`bs-checkout-smoke` baseline regression check after deploying (observability-only, so this checks nothing broke from the added logging). Additionally, a targeted manual repro for the owner: on the old checkout, select COD + a specific shipping method, confirm visually selected; follow the link to the new checkout without touching any field; immediately screenshot the radios' visual state and the confirm-panel text, and export `window.bsSt2b6ReadDiagnostics()` (or its Phase 0b equivalent) at that exact moment; repeat with 2-3 different payment/shipping combinations. Preserve logs even when the panel text matches correctly — a clean run is also evidence.

## 9. Rollback

Same pattern as Phase 0a: backup to `_patch_backups/ST-2b6b-*` before write, restore + clear OpenCart `template/`+`cache.*` on any regression. Since this remains instrumentation-only, behavioral rollback risk is low, but treat as a HIGH-RISK deploy window since it touches the same live checkout files as the active guarantee ST-2c depends on.

## 10. Recommended status after execution

Stays `На діагностиці` — this round does not authorize a Phase 1 fix by itself. Once evidence comes back: if a silent Hutko-reset path is confirmed, treat it as higher priority than the original tab-restore framing (real customer orders may be affected, not just test orders) and write a dedicated Phase 1 fix handoff scoped to that specific mechanism. If the old-checkout blind spot turns out to matter, that becomes a separate scoping decision (whether to instrument SimpleCheckout or retire it faster) for the owner, referencing ST-2c/ST-6 sequencing. Update `booster-dashboard.html` ROADMAP_FLOW and Notion status for ST-2b.6 in the same session per `ROADMAP_SOP.md §3-4` once there's a real status change to reflect.
