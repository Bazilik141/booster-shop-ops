# PAY-001-SMOKE — Unified final QA gate for credit purchasing (mono + PUMB)

Roadmap: PAY-001-SMOKE · Risky zone: checkout / payment / order status
Built from the `bs-checkout-smoke` skill's mandatory 11-step base, extended to
cover two credit providers, the shared intermediate confirmation page (PAY-003),
and the consolidated order-status set (PAY-002 revision §8a).

Status: **Not started — blocked.** This is not a test to run now. It is the
single, final regression gate before "Покупка в кредит" (both mono and PUMB)
goes live to real customers without owner supervision. Do not run it partially
and call it done; every prerequisite below must be met first, because the whole
point of this task is to stop testing the two providers in isolation and instead
prove the finished, shared flow works end to end.

## 1. Why this exists (instead of closing PAY-001 with a full re-run now)

Owner decision, 2026-07-27: PAY-001 (mono) closes as `Done` today on the strength
of a real production order that completed the full flow. The dashboard's
previously open item — "full 15-step smoke not re-run after Phase 2d fixes" —
is deliberately **not** re-run in isolation. Instead it is folded into this task
and re-run once, after PUMB and the shared UI exist, so the owner tests the
*actual* shared product once instead of the mono half of it twice.

## 2. What we are waiting for (blockers)

None of these exist yet. This task cannot start until all four are true:

1. **PAY-002** — `extension/pumb_credit` deployed at least to the test callback
   route (Variant A, `...callbackTest`), with a working create → client action →
   shipment-confirm → refund cycle against the bank's test contour.
2. **PAY-003** — the shared "waiting for client confirmation" page is live
   between checkout submission and checkout success, for both providers.
3. **Order-status consolidation** (PAY-002 revision §8a) — the renamed, reduced
   status set is deployed and both extensions write to it; NCRM order-sync
   (`ncrm/supabase/functions/order-sync/index.ts`, NCRM-14) recognizes both
   `credit_mono_3/4/5` and `credit_pumb_3/4/5` payment types without falling
   back to `acquiring`.
4. **PAY-001-UI** modal shows both provider cards as real, working options (not
   one active + one "скоро" placeholder).

## 3. What must be implemented before the test can run — checklist

- [ ] `extension/pumb_credit`: OAuth2 client with token caching, transaction
      table, callback routes (prod + test, Basic auth, IP-allowlisted once bank
      supplies source IPs), `GET /sf-credits/{id}` poll fallback.
- [ ] PUMB shipment hook: order status → "Відправлено" triggers
      `PATCH /sf-credits/{id}` (`method=UPDATE`), same pattern as mono's
      `confirmApplication()`.
- [ ] PAY-003 intermediate page: reads transaction state from both tables, shown
      after checkout submit, redirects to shared checkout success once a
      terminal-success state is reached; recovers correctly if the customer
      closes the tab and returns via order history / direct link.
- [ ] Order-status rename + consolidation deployed (see PAY-002 revision §8a) —
      both extensions write the same shared label set, not provider-prefixed
      duplicates.
- [ ] NCRM-14 deployed (`credit_pumb_3/4/5` payment types + `discount_total`
      fix) and verified against a real PUMB test order, not just code review.
- [ ] `bs-checkout-smoke` base 11-step regression passes on the current
      checkout build (non-credit paths), independent of credit work, as a
      sanity floor before layering credit-specific stages on top.

## 4. Staged test plan

Sandbox/staging notes: mono sandbox (`test_store_with_confirm`); PUMB test
contour (bank-issued OAuth2 credentials, once available); Hutko sandbox;
Checkbox sandbox. **No real payments, no real credit applications.** Owner or a
staging tester runs every step; Claude/Codex never execute real payments or
approve real credit.

### Stage 0 — Base regression (generic, not credit-specific)

The `bs-checkout-smoke` mandatory 11 steps, run once as a floor before the
credit-specific stages. If any of the 11 fails, stop — fix it outside this task
before continuing.

| # | Test | Steps | Expected | Actual | Pass/Fail |
|---|---|---|---|---|---|
| 1 | Register → Checkout | New user signup, add item to cart, proceed to checkout | Reaches checkout with cart intact | | |
| 2 | Auto First15 | New user's first order | `First15` auto-applies | | |
| 3 | Manual First15 reuse | Same user attempts reuse | Reuse blocked | | |
| 4 | Invalid coupon | Enter invalid code | Clear error, checkout not broken, no duplicate errors | | |
| 5 | Nova Poshta data | City + branch selection | Required-field validation works; fallback if API unavailable | | |
| 6 | Order button visibility | Fill/unfill required fields | `Оформити` enabled only when valid | | |
| 7 | Hutko payment return/session | Return from provider | Session alive, order status correct (paid/failed/pending) | | |
| 8 | Success redirect | Complete a non-credit order | Lands on success URL with correct `order_id` | | |
| 9 | Clean JSON responses | Inspect AJAX endpoints | Valid JSON only, no PHP warnings/notices/HTML | | |
| 10 | Email delivery | Complete an order | Confirmation reaches test inbox, links work | | |
| 11 | Checkbox / fiscalization | If payment/status touched | Receipt visible in sandbox/staging | | |

### Stage 1 — Mono credit path (full re-run, not incremental)

| # | Test | Steps | Expected | Actual | Pass/Fail |
|---|---|---|---|---|---|
| 1.1 | Provider card visible | Open credit modal on a qualifying product | Mono card shown, selectable, correct 3/4/5 terms | | |
| 1.2 | Sandbox phone `...1` | Complete mono flow | `order/create` 201 → callback ~5s → PAY-003 shows waiting state → resolves to funded | | |
| 1.3 | Sandbox phone `...2` | Complete mono flow, no callback | PAY-003 polls `/api/order/state`, picks up state without callback | | |
| 1.4 | Sandbox phone `...3` | Complete mono flow | FAIL surfaces clearly on PAY-003, checkout not broken, order lands in consolidated "Відхилено" status | | |
| 1.5 | Sandbox phone `...4` → admin confirm | Admin confirms after "shipment" | Status → "Оформлено" (consolidated), first payment charged at that moment | | |
| 1.6 | Invalid callback signature | Send tampered callback | Rejected (401/403), not treated as valid | | |
| 1.7 | Duplicate `store_order_id` | Repeat create | No duplicate order/transaction row | | |
| 1.8 | Amount below 500 UAH | Attempt checkout | Method not offered; backend blocks directly if bypassed | | |

### Stage 2 — PUMB credit path (mirrors Stage 1, different mechanics)

| # | Test | Steps | Expected | Actual | Pass/Fail |
|---|---|---|---|---|---|
| 2.1 | Provider card visible | Open credit modal on a qualifying product | PUMB card shown, selectable, correct 3/4/5 terms | | |
| 2.2 | Create application | Complete PUMB flow with bank test credentials | `POST /sf-credits` 201 → `cap_id` stored → PAY-003 shows waiting state | | |
| 2.3 | Callback happy path | Bank sends `WAITING_CLIENT` → `WAITING_STORE_CONFIRM` → `FUNDED` | Each callback answered `{"success":true,"error":null}` HTTP 200; PAY-003 updates live | | |
| 2.4 | Callback missed / poll fallback | Simulate a dropped callback | `GET /sf-credits/{id}` fallback picks up the real state (hybrid scheme working) | | |
| 2.5 | Rejected application | Bank test scenario for `REJECTED`/`NO_LIMIT`/`FAIL` | Surfaces clearly on PAY-003, order lands in consolidated "Відхилено" status | | |
| 2.6 | Shipment confirm | Change order status to "Відправлено" | `PATCH /sf-credits/{id}` `method=UPDATE` fires, `409` (if still `WAITING_CLIENT`) handled as "not yet", not as a hard failure | | |
| 2.7 | Refund | Trigger a cancel/refund on a funded test order | `POST /sf-credits` with `refund:true` succeeds using stored `agreement_number` | | |
| 2.8 | Unauthenticated / wrong-IP callback attempt | Send callback without valid Basic auth or from a non-allowlisted IP | Rejected before it can change any order state | | |
| 2.9 | Amount below 500 UAH or above the bank's confirmed max | Attempt checkout | Method not offered; backend blocks directly if bypassed | | |

### Stage 3 — Shared UI and status consolidation

| # | Test | Steps | Expected | Actual | Pass/Fail |
|---|---|---|---|---|---|
| 3.1 | Admin order-status dropdown | Open Admin → Orders → status list | Shows the consolidated shared set only — no `ПЧ mono —` / `ПЧ PUMB —` duplicates | | |
| 3.2 | PAY-003 tab-close recovery | Close the waiting-confirmation tab mid-flow, return via order history | Page reconstructs the correct current state, does not lose or duplicate the transaction | | |
| 3.3 | Shared checkout success | Complete both a mono and a PUMB test order | Both land on the same success page/template, correct order id each time | | |
| 3.4 | NCRM payment-type mapping | Inspect the resulting NCRM rows | `credit_mono_3/4/5` and `credit_pumb_3/4/5` both resolve correctly, no `acquiring` fallback, `discount_total` correct | | |

### Stage 4 — Regression on existing payment methods

| # | Test | Steps | Expected | Actual | Pass/Fail |
|---|---|---|---|---|---|
| 4.1 | Hutko | Complete a card order | Unaffected by PUMB extension / status rename | | |
| 4.2 | COD | Complete a cash-on-delivery order | Unaffected | | |
| 4.3 | IBAN | Complete an IBAN-transfer order | Unaffected | | |

## 5. Final summary (fill on run)

- Pass count / total:
- Blockers found:
- Recommended status: Ready to enable / Not ready — see blockers
- Sandbox/staging notes:
- Owner sign-off:

## 6. Hard rules (inherited from `bs-checkout-smoke`, extended)

- Owner or a staging tester runs every stage; Claude/Codex never execute a real
  payment or approve a real credit application.
- No real customer PII is used in test runs; bank test/sandbox identities only.
- If PUMB's `guarantee_letter` payload appears in test output (Stage 2), it
  carries real-shaped tax ID / identity-document fields even in a test run —
  do not log it, do not paste it into chat or a diagnostic file.
- If any step cannot be run because a prerequisite is still missing, mark it
  `n/a` with the specific missing prerequisite — do not skip silently.
- This task closes only when every stage passes or is explicitly waived by the
  owner with a stated reason.
