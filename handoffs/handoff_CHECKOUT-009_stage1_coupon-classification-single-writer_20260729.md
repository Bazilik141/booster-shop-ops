# Codex Handoff — CHECKOUT-009 Stage 1: coupon-event classification + single address writer + asset key

Date: 2026-07-29 | Parent: CHECKOUT-009 audit round
Codex config: model=Sol · effort=xhigh

Predecessor artifacts (read before acting, do not re-derive):

- `handoffs/handoff_CHECKOUT-009_shipping-selection-not-registered_20260729.md` — audit-round handoff and the mandatory feature-preservation double check
- `plans/CHECKOUT-009_checkout-architecture-map_20260729.md`
- `plans/CHECKOUT-009_checkout-behaviour-register_20260729.md` — 40 rows, verbatim marker before-image
- `plans/CHECKOUT-009_checkout-state-consolidation-options_20260729.md` — Option C definition
- `diagnostics/CHECKOUT-009_shipping-selection-not-registered_report_20260729.md`
- `diagnostics/CHECKOUT-009_audit_review_20260729.md` — Claude review, conditions 1–4

## Owner decision

**Option C, Stage 1 is authorized for implementation.** Stage 2 is not
authorized and must not be started or partially anticipated beyond keeping
Stage 1's public names/contracts compatible with the Stage 2 target design.

Standing constraints unchanged: no rollback of the ST-2c rounds, no
symptom-only override, no override stacked on an existing override, no
deployment by Codex. Prepare the runner, the checks and a diff summary. Do not
commit or push — this handoff grants no commit/push authority.

## Phase 0 — evidence gate (blocking, before any code is written)

Do not write the patch until these are satisfied. If the owner has not yet
supplied an item, request it and stop.

1. **Authenticated (logged-in) HAR** for the checkout, per the capture list in
   the architecture map §"HAR capture", plus the guest HAR. No order placed.
   Rationale: the logged-in root cause is currently inferred, not reproduced
   (review condition 1), and Stage 1 fixes that path on that inference.
2. **Schema-safe theme override export** — run the read-only command in the
   architecture map §"Schema-safe theme override export". If any checkout Twig
   route is overridden in the database, the file-level mapping is not
   authoritative and you must re-establish which source actually executes
   before patching.
3. ~~First15 auto-apply proof~~ — **satisfied and answered, see Amendment 1.**
   Codex reported on 2026-07-29 that `coupon.summary` itself performs the
   auto-apply (`catalog/controller/checkout/coupon.php:6` →
   `catalog/model/checkout/booster_coupon.php:83` → `applyCouponCode()` writing
   `session.coupon` at line 48), and that `checkout-reskin.js:389` currently
   uses that summary response to requote. This correctly triggered the
   handoff's stop condition. The resolved design is below; it replaces
   scope item 2's unconditional wording.

## Scope — the six corrections (Option C Stage 1)

1. Carry the actual coupon action (`summary` / `apply` / `remove`) from the
   coupon client into the renderer and the state notification.
2. Treat `summary` as a **query by default** — no revision advance, no
   shipping/payment invalidation, no requote — with the single honest exception
   defined in Amendment 1: a summary call that actually auto-applied First15
   reports `mutated: true` and triggers exactly one requote, sequenced after any
   in-flight address commit.
3. Treat only successful `apply` / `remove` as totals mutations that invoke the
   existing coupon requote/resave path (that path itself is correct — preserve
   it).
4. Replace Pinta's 250 ms quote timer and the broad `ajaxSuccess` URL-substring
   address-success matcher with **one explicit address-save-completed callback**
   into the coordinator. Both the stock save path and the Pinta injected form
   must call that one callback.
5. Guest `register.save` success runs one named address transaction; its First15
   summary is a non-mutating query and cannot invalidate that transaction.
6. Publish both checkout scripts under a **new immutable query key**.

Expose these entry points with Stage 2's names and contract so Stage 1 is a
strict subset of the target design, not a temporary API:

- `AddressCommitted`
- promo result `{kind: "summary"|"apply"|"remove", mutated: boolean}`
- `DeliverySelection.requote(reason, selectionPolicy)`

Expected files (confirm against the live source before writing):

- `catalog/view/javascript/checkout-reskin.js`
- `catalog/view/template/checkout/checkout.twig`
- `extension/PintaNovaPoshtaCod/catalog/view/template/shipping/js_checkout_shipping_address_form.twig`
- `catalog/view/javascript/checkout-state.js` — only if the typed notification
  API belongs there rather than in the reskin routing
- `catalog/controller/checkout/coupon.php` — **narrow exemption, see below**

**Narrow controller exemption (owner-approved, 2026-07-29):** Stage 1 permits a
narrow change to `catalog/controller/checkout/coupon.php` solely to return the
boolean `mutated` field. This response-contract addition is exempt from the
no-controller / server-response-reshaping stop condition. The model and discount
timing remain unchanged.

Beyond that one field, no controller, model, database, or payment-transport
change is expected. If the evidence shows another is required, stop and report
before writing it.

## Amendment 1 (2026-07-29) — First15 auto-apply inside `coupon.summary`

### Decision

**Keep the auto-apply where it is for Stage 1. Make it honest instead of
silent.** `coupon.summary` reports what it actually did, and the client reacts
to that report — not to the endpoint's name.

Rejected for Stage 1: moving the auto-apply to a server-side transition ahead of
the bootstrap quote. That is the architecturally cleaner end state, but it
changes when a real discount is granted, touches a controller and a model in
money-affecting code, and is outside the client-only Stage 1 scope. It is
recorded as a Stage 2 / follow-up item, not abandoned.

This decision needs no new concept: the promo result contract already required
by this handoff — `{kind: "summary"|"apply"|"remove", mutated: boolean}` — is
exactly the carrier for it.

### Rules

1. **Server** — `coupon.summary` returns `mutated: true` only when that call
   actually changed the coupon/session state (the First15 auto-apply case), and
   `mutated: false` otherwise. The flag reflects a real state change, not the
   presence of a discount: a summary call made when the coupon is already in
   session is `mutated: false`.
2. **Idempotency (prove it)** — a second `coupon.summary` in the same session
   after a successful auto-apply must return `mutated: false`. Demonstrate that
   no summary → requote → summary cycle can repeat. A repeating `mutated: true`
   is a fail, not a retry.
3. **Client** — `mutated: false` behaves exactly as scope item 2 specified: pure
   query, no revision advance, no payment-state clear, no display-text reset, no
   requote. `mutated: true` performs **exactly one** requote through
   `DeliverySelection.requote(reason, selectionPolicy)`.
4. **Causality — this is the part that must not reintroduce CHECKOUT-009.** A
   `mutated: true` requote must be **sequenced after** any in-flight address
   transaction commits. It must never abort, invalidate or outrank an
   `AddressCommitted` in progress. Concretely: if an address transaction is
   in flight, the requote is queued behind its commit; it does not advance the
   revision underneath it. Guest `register.save` is the exact case where both
   happen together — that ordering is the whole reason this task exists.
5. **No committed delivery, nothing to requote.** If no delivery selection is
   committed yet (typical first render, before any address), `mutated: true`
   updates the totals/free-shipping projection only. There is no quote to
   invalidate, so no requote is issued.
6. **Apply / remove unchanged** — still exactly one requote+resave each.

### Additional verification required by this amendment

- Deterministic ordering test extended: guest `register.save` where the
  summary response is `mutated: true` **and** the shipping save is in flight,
  in both response orders, proving the address transaction survives and exactly
  one requote runs afterwards.
- Idempotency test: repeated `coupon.summary` calls after auto-apply, proving a
  single `mutated: true` and no requote loop.
- Threshold test with an auto-applied First15 discount in both directions
  around ₴2000, on a committed delivery selection.

### Observation for Stage 2 (record, do not act on it now)

A read-named endpoint that grants a discount as a side effect is the same class
of defect as CHECKOUT-009 itself. Stage 2 should move the First15 grant to an
explicit server-side transition at checkout bootstrap and reduce
`coupon.summary` to a true query. Note in the report whether anything other
than a real customer page load can reach that endpoint (prefetch, crawler,
repeated XHR), since that would grant eligibility outside a genuine checkout —
observation only, no change in this round.

## Cache-key sequencing (hard requirement)

Production currently serves `?v=r135-cart-refresh-20260725` for both checkout
scripts while the files already contain the 2026-07-28/29 logic. Therefore:

- the new key and the corrected sources are **one deployment unit**;
- **never** bump the key ahead of the fix — customers on a warm cache are
  currently executing pre-2026-07-28 JavaScript, and a bare key bump would push
  the failure to all of them;
- QA must cover both a cold session and a session with the old assets cached;
- state in the report that the 2026-07-28/29 rounds' QA may have run against
  pre-patch JavaScript, so their outcomes are unconfirmed until re-checked on
  the new key.

## What NOT to touch

- Stage 2 work: hidden-input authority migration, snapshot stores, server
  response reshaping — **except** the single additive `mutated` boolean in the
  `coupon.summary` response permitted by the narrow controller exemption above.
  No other field is added, renamed, removed or restructured; no existing field's
  meaning changes.
- `catalog/model/checkout/booster_coupon.php`, the First15 eligibility rules and
  the moment the discount is granted — unchanged in Stage 1. The exemption
  covers reporting what already happened, not changing when it happens.
- The revision coordinator, hidden mirrors, coupon/cart requote-resave path,
  atomic shipping summary, deferred confirm — preserved in this stage.
- The ₴2000 threshold value and the Pinta display-only tariff semantics.
- Credit gates: minimum amount and the pre-order block
  (`handoffs/CODEX - PAY-001-ADDENDUM-2.md` §5) must keep blocking.
- `mono_chast` / `pumb_credit` internals and bank transport; PUMB stays disabled.
- Checkbox/fiscalization, Nova Poshta API/quote formula, CRM, Telegram,
  analytics registration, SimpleCheckout (no re-exposure).
- `alterRedirectShippingAddressSave()` — registered no-op under ACC-002F, leave
  it registered.
- No database changes.

## Third-party file requirement

The Pinta file in scope lives inside a third-party extension. A Pinta update
will overwrite it and silently reintroduce the duplicate-writer condition behind
CHECKOUT-009. Record this explicitly in the patch header **and** update the
corresponding Pinta writer row(s) in the behaviour register so a future upgrade
is checked against it.

## Feature-preservation double check — Stage 3 self-check (mandatory)

Per the audit handoff, before handing back:

1. **Marker drop check** against the verbatim before-image in the behaviour
   register. Every marker that disappears is named with its reason and the
   mechanism that subsumes it. Zero unexplained disappearances.
2. **Line accounting**: every deleted or rewritten block mapped to its register
   row and disposition. The rows this stage replaces are `18`, `19`, `23`,
   `24`, `25`, `40` — for each, name the new mechanism explicitly. The other 34
   rows must be demonstrably preserved.
3. **Behaviour replay**: the trigger of each register row exercised in the
   patched code, guest and logged-in, with recorded evidence.
4. Deterministic ordering test: delayed `coupon.summary` and delayed shipping
   save, in **both** response orders, proving the address transaction survives.
5. `php -l` on changed PHP, `node --check` on changed JS, runner `--dry-run`
   clean, exact anchors/hashes, backups written, idempotent marker, self-delete.

## Acceptance criteria

- [ ] Phase 0 items 1–3 satisfied and documented; item 3 proven at file/function/line.
- [ ] Guest, cart ≥ ₴500, delivery filled manually, each of the three Nova Poshta modes: the confirm button enables with no address re-selection, and an order can be placed.
- [ ] Logged-in with a prefilled saved address: summary gate clears and `Оплатити частинами` is selectable on first render, no re-selection, no reload.
- [ ] `coupon.summary` with `mutated: false` provably performs no revision advance, no payment-state clear, no display-text reset and no requote; `apply`/`remove` still perform exactly one requote+resave each.
- [ ] `coupon.summary` reports `mutated: true` only when it actually changed coupon/session state; a repeat call returns `mutated: false` and no requote loop is possible.
- [ ] A `mutated: true` requote never aborts or outranks an in-flight address commit; proven for guest `register.save` in both response orders.
- [ ] With no committed delivery selection, `mutated: true` updates only the totals/free-shipping projection and issues no requote.
- [ ] Exactly one writer completes the address → shipping transition; the 250 ms timer and the URL-substring matcher are gone.
- [ ] Free-shipping threshold correct in both directions after coupon apply/remove, after mini-cart quantity change and removal, and with an automatically applied First15 discount.
- [ ] Credit gates still block for minimum amount and for a pre-order item in the cart.
- [ ] Analytics still records one address/save observation per real save — not zero.
- [ ] New immutable asset key shipped in the same unit; both cold and warm cache verified.
- [ ] Marker drop check clean; 40/40 register rows accounted; register updated with the Pinta third-party note.
- [ ] No console errors on checkout, guest and logged-in.
- [ ] Diagnostic written to `diagnostics/CHECKOUT-009_stage1_<slug>_report_20260729.md`.
- [ ] No deployment, commit or push performed.

## Owner QA (generated from the register; owner runs after deploy)

- [ ] Clear OpenCart cache/compiled templates per the patch output, then hard-refresh.
- [ ] Repeat once in a browser that had the old checkout cached (warm cache) and once in a clean/incognito session.
- [ ] Guest, incognito: fill delivery for `у відділення`, `поштомат`, `кур'єром` in turn — each enables confirm.
- [ ] Guest: place one real low-value test order with `Оплата при отриманні`; confirm it appears in admin and in CRM.
- [ ] Logged-in with saved address: credit method available on first render; switch between saved addresses back and forth.
- [ ] Cart below the credit minimum → credit stays blocked; cart with a pre-order item → credit stays blocked regardless of amount.
- [ ] Apply and remove a coupon crossing ₴2000; change mini-cart quantity across ₴2000 — delivery text and free-shipping progress stay in sync.
- [ ] Customer eligible for the automatic First15 discount, as a **guest going through registration at checkout**, order value near ₴2000 on both sides — the discount applies, delivery is accepted (confirm button enables), and shipping cost plus free-shipping progress are correct.
- [ ] Same customer, page reloaded after the discount already applied — no repeated discount, no flicker of the delivery selection.
- [ ] Card and IBAN methods selectable and save correctly; order comment, offer checkbox, newsletter and "save data for next time" behave as before.
- [ ] Run `bs-checkout-smoke`.

## Rollback

One unit: the runner backs up every target before writing and restores all on
any anchor/syntax failure. Roll back all changed files together — never only
the query key or only one writer deletion.

## Stop conditions

Stop and ask the owner if: a checkout Twig route is overridden in the database;
the live source no longer matches the audit archive; the correct fix would
require a controller, model, DB or payment-transport change **beyond the
approved additive `mutated` field in `coupon.php`** — in particular any change
to the model, to First15 eligibility, or to when the discount is granted; or
unrelated working-tree changes overlap the
target files.

## Risks

Risky zone: checkout + payment + Nova Poshta. Guest ordering is currently
degraded, so both delay and a rushed patch carry cost. The fix must not weaken
the legitimate credit gates, must not regress ST-2c threshold behaviour, and
must not reintroduce the CHECKOUT-002/ST-2b confirm pre-loading defect.
Deployment and production QA remain the owner's gate.
