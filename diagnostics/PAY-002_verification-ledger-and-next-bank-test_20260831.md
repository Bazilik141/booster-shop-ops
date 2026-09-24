# PAY-002 — verification ledger and the next controlled bank test

Date: 2026-08-31
Author: Claude (chat). Read-only reconciliation; nothing was run against
production or the bank.
Trigger: owner reported WP4 deployed 2026-08-31
(`sha_gate=ok before=7fde759a after=ea4b41df`, `done=ok`, `self_delete=ok`,
`cache cleared`) with owner UI QA ok, and asked what is actually proven and what
the next bank test must be.

## Headline

The UI work is finished and the bank contour was proven in August — but **those
two facts have never met**. Every bank application to date was created by a
diagnostic script; no application has ever originated from a customer choosing a
term in the checkout drawer. Owner UI QA proves rendering and payment-code
selection. It proves nothing about the bank.

## What is proven, and by what

| Fact | Evidence | Contour |
|---|---|---|
| Full bank lifecycle: `create 201 → callback → client signs → WAITING_STORE_CONFIRM` (agreement_number written automatically) `→ PATCH goods_shipped 200 → FUNDED → refund 201 → REFUND_FINISHED` | `diagnostics/PAY-002_bank-test-drive_result_20260825.md`, cap_id 19040054, order #332, term 4, 700 UAH, 2026-08-26 | test |
| Inbound callbacks land at every step (194.44.66.21, `pumb_test_cb`, HTTP 200) | same | test |
| The bank receives the term it is sent | guarantee letter for cap_id 19040054 returned product «Сплачуйте частинами NEW_4», term 4 | test |
| Amounts are hryvnia decimal, final state is `FUNDED`, `GET /sf-credits/{id}` needs `X-Flow-Id`, bank retries callbacks ~3× / 10 s | `handoff_PAY-002_session-continuation_20260826.md` §3 | test |
| Production terms are 3/4/5 | Roman Nazarenko, 2026-08-26 | production |
| 7-day application window | closed by the bank, owner 2026-08-28 (`handoff_PAY-002_session-continuation_20260826.md` §10) | production |
| Term 5 enabled on production | bank confirmed, owner 2026-08-28 | production |
| Server rejects a confirm without a valid term; `requested_term` persisted | PAY-004 patch deployed 2026-08-25, round-2 review OK | — |
| Token gate: settings, preview route, server-side predicate in `getMethods()` **and** `confirm()` | WP1 deployed, review round 5 | — |
| PUMB card in the drawer, term selection posts a provider-specific code | WP2 + WP3 deployed, reviews rounds 1–3 of WP3 | — |
| Remaining-payments display per provider | WP4 deployed 2026-08-31, owner QA ok | — |

## What is NOT proven — the gap this creates

1. **No application has ever been created through the customer path.** Every
   `cap_id` so far came from `patches/PAY-002_bank-test-drive_diagnostic_20260824.php`
   or equivalent. The chain drawer → `savePayment(code)` → `confirm()` →
   `requestedTerm()` → `createPayload()` → bank has never executed end to end.
   This is the whole point of WP2/PAY-004 and it is untested.
2. **Term 5 has never been exercised anywhere.** The test contour rejects it
   (400 «Term 5 is not supported»); production accepts it per the bank. The first
   real 5-payment application will therefore be a production one.
3. **Callback handling on a UI-originated order** is unverified — the proven runs
   were script-originated with a manually chosen `store_order_id`.
4. **OpenCart order-status transitions for PUMB** still map onto the
   `ПЧ mono — …` statuses; the §8a consolidation (6 mono → 5 shared) is not done.
5. **`NCRM-14`** — order-sync of PUMB payment types has never run against a real
   PUMB order.
6. **The forged-confirm negative case** — calling the PUMB confirm route from a
   session that never opened the preview URL — has been specified in three
   reviews and never executed on production.
7. **Non-credit regression** after the WP3 rewrite of `flattenPaymentMethods()`
   (Hutko, COD, IBAN) — specified, not run.
8. **Brand layout approval** (contract п. 2.2.8) — not started, no contact named,
   plausibly the longest lead item remaining.

## Next controlled bank test — TEST contour, one order, term 4

Scope it to exactly one application. The single question it answers: *does a term
chosen by a customer in the drawer arrive at the bank as that term?*

**Term 4 on purpose.** The drawer's default preferred term is 3, and 5 cannot be
exercised on the test contour. Only a non-default, stage-supported term can
distinguish "the customer's choice propagated" from "the code silently fell back
to the default". A test at term 3 would prove nothing.

### Preconditions

- Test contour on; API/OAuth still on `*.dts.fuib.com`.
- Production callback credentials still empty — do not fill them.
- Test callback user `pumb_test_cb` with the password rotated 2026-08-26.
- `payment_pumb_credit_public = 0`; PUMB reachable only through the preview token.
- Cart ≥ 500 UAH, a disposable order in the manner of #332.
- Enable `payment_pumb_credit_status` for the window only, and disable it
  immediately afterwards.

### Sequence

1. Open checkout through the preview-token URL; confirm the single «Сплатити
   частинами» row with both provider cards.
2. **Before selecting PUMB**, run the forged-confirm negative case: call the PUMB
   confirm route from a browser session that never opened the token URL. Expect a
   refusal with no bank call — this is item 6 above and it costs nothing to fold
   into the same window.
3. Select PUMB, **term 4**, place the order.
4. Record from the request/response: `credit_request.term`, `credit_request.amount`,
   `invoices[0].total_amount`, `store_order_id`, the returned `cap_id`, `X-Flow-Id`.
5. Check the transaction row: `requested_term = 4`, `is_test = 1`, `cap_id` stored,
   state as returned.
6. Sign as the client; confirm the callback arrives and the state moves to
   `WAITING_STORE_CONFIRM` with `agreement_number` written automatically.
7. `PATCH goods_shipped` → expect 200 and `FUNDED`.
8. Read the guarantee letter and confirm the product name carries **NEW_4**, not
   NEW_3. This is the assertion that actually closes PAY-004.
9. Record the OpenCart order status at each step and what NCRM received — this is
   evidence for §8a and NCRM-14, not a pass/fail gate for this test.
10. `refund` → `REFUND_FINISHED`, so no live test deal is left open.
11. Disable `payment_pumb_credit_status`. Then run the non-credit half of
    `bs-checkout-smoke` (Hutko, COD, IBAN) — item 7 above.

### Pass criteria

The test passes only if step 8 shows NEW_4 **and** step 5 shows
`requested_term = 4`. Anything else — a fallback to 3, a rejected confirm, a
missing callback — is a defect in the customer path and returns to the executor.

### Explicitly out of scope for this test

Term 5, the production contour, real money, and the brand layout. Term 5 stays a
known production-first risk until the bank enables it on stage or the owner
accepts the gap in writing.

## After this test, before PUMB goes public

In dependency order, not priority order:

1. `PAY-005` — the "unavailable" credit row (hard gate before
   `payment_pumb_credit_public = 1`).
2. Order-status consolidation §8a.
3. `NCRM-14` verification on the PUMB order produced by the test above.
4. Brand layout approval, contract п. 2.2.8 — start now, in parallel; it does not
   depend on anything above.
5. Production contour cutover: fill production callback credentials, switch
   OAuth/API off `*.dts.fuib.com`, then `PAY-001-SMOKE` as the shared final gate.

## Roadmap

No status change written. `PAY-002` stays `In progress`. `PAY-004` should move to
`Owner QA` only when step 8 above passes — its Definition of Done is a
customer-selected term reaching the bank, which is precisely what has not
happened yet. Say the word and it goes through `bs-roadmap-write` with the
`ROADMAP_TASKS` mirror in the same pass.

---

## Addendum 2026-08-31 — the product page still shows the placeholder

Owner observation, with screenshots: the `СКОРО БУДЕ` placeholder was removed
from the checkout drawer but not from the product page. Confirmed against the
live template (`catalog/view/template/product/product.twig`, backup 2026-08-28 —
untouched by WP1–WP4).

### What is actually there

- The whole credit entry — the «Сплатити частинами» button, the provider rows and
  the modal — renders only under the Twig flag `pay001_mono_chast_visible`,
  supplied by the product controller. It is monobank's flag, not a shared one.
- PUMB appears twice as static markup: the provider row at lines ~338–343
  (`pay001-provider-row--soon`) and the modal card at line ~1149
  (`pay001-modal-provider--soon`). Both carry `<em>СКОРО БУДЕ</em>`.
- The monobank modal card carries the 3/4/5 buttons, the summary and
  «Додати й оформити» (`data-pay001-credit-action="checkout"`), which adds to cart
  and moves to checkout, seeding the session preference the drawer later reads.

Incidental but useful: the screenshots prove `payment_mono_chast_status` is
currently **enabled** on production. That retires the concern raised in WP2
review round 2 about PUMB visibility being coupled to a disabled monobank — the
coupling was removed anyway, and the premise did not hold.

### Why this is a separate work package, not a hotfix

Showing PUMB here needs three things, only one of which is markup:

1. a PUMB visibility flag exposed by `catalog/controller/product/product.php`,
   computed with the same server-side predicate as checkout — status +
   credentials + (`payment_pumb_credit_public` OR the session preview flag);
2. the block-level gate widened from "monobank visible" to "either provider
   visible", or PUMB can never appear here if monobank is ever switched off;
3. a real PUMB card in the modal — term buttons, summary, and its own
   «Додати й оформити» that seeds a PUMB term the way monobank's does.

Item 3 introduces a second writer into the session term preference the checkout
drawer reads. That is the part that needs design, not typing.

Ordinary customers must keep seeing exactly today's `СКОРО БУДЕ` until
`payment_pumb_credit_public = 1`, same as in checkout.

### Sequencing decision

**The bank test is not blocked by this and should run first.**

The test's single question is whether a term chosen by the customer reaches the
bank. The authoritative writer of `pay002_pumb_credit_term` is the checkout
drawer, not the product modal; the modal only pre-seeds a preference. The owner
reaches checkout through the preview-token URL regardless. Delaying the test
postpones the only remaining unknown that can still surprise us, in exchange for
UI work whose shape is already understood.

What the test cannot cover, and what must therefore follow WP5: the entry path
from the product modal, because item 3 above adds a second writer to the same
session key. After WP5 is deployed, re-verify on the same test contour — modal →
term 4 → checkout shows term 4 preselected → the confirm payload carries 4. That
is a short check, not a second full bank cycle, and it can ride on one more
disposable order.

Recommended order:

1. Bank test as specified above — now.
2. WP5 (product page + modal) built and reviewed in parallel.
3. Short entry-path re-verification after WP5 deploys.
4. Then `PAY-005`, §8a, `NCRM-14`, brand approval, production cutover,
   `PAY-001-SMOKE`.

No roadmap ID assigned to WP5 yet — it belongs under `PAY-002` unless the owner
wants it tracked separately.
