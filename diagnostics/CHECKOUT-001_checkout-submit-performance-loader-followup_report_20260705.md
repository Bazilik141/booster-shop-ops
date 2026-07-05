# Codex Report — CHECKOUT-001: deferred checkout performance and loader follow-up

Date: 2026-07-05  
Recommended priority: Low  
Recommended action: close current CHECKOUT-001; create a separate optimization/UX task.

## Context

CHECKOUT-001 Phase 1–1.3 implemented optional guest account creation, guest-only
Public Offer consent, duplicate-registration-email protection, fail-open account
pre-step behavior, and persistent checkout progress feedback.

Owner’s production observations after the changes:

- guest with account opt-in: approximately 4 seconds from place-order click to
  checkout success;
- authorized customer: approximately 1.5–2 seconds;
- account and order creation are functionally successful;
- owner wants the loader visual and the text `Оформлюємо замовлення...` revised
  as part of the future optimization task.

This performance/visual follow-up is not required to close the current functional
CHECKOUT-001 task.

## Confirmed current behavior

The current client flow is:

```text
guest + account opt-in:
createAccount → checkout/confirm.confirm → payment confirm → redirect

guest without opt-in or authorized customer:
checkout/confirm.confirm → payment confirm → redirect
```

`checkout/payment_method.createAccount` is already skipped when the opt-in is
unchecked or absent. Therefore the authorized customer’s 1.5–2 second delay is
not caused by guest account creation.

The current Phase 1.3 overlay adds only one browser animation frame before the
sequential request chain. It does not explain a 1.5–2 second server/network wait.

## Why this was not optimized inside CHECKOUT-001

`checkout/confirm.confirm` is not a read-only HTML endpoint in this OpenCart 4
flow. It creates or edits the order as a side effect.

Loading it before the explicit place-order click would make the checkout appear
faster after the click, but would reintroduce the defect previously fixed by
ST-2b.1/ST-2b.4:

- draft orders created when selecting/changing payment;
- abandoned order rows without a genuine place-order action;
- increased risk of duplicate or phantom orders.

Removing the final payment request generically is also unsafe because COD,
bank-transfer and Hutko confirmation handlers do not necessarily have the same
response and redirect contract.

No speculative performance patch should be created without endpoint timings and
fresh payment-handler files.

## Files / routes involved

Primary:

```text
catalog/view/template/checkout/checkout.twig
catalog/controller/checkout/confirm.php
```

Must be identified from a fresh live bundle before implementation:

```text
active COD payment confirm controller/template
active bank-transfer payment confirm controller/template
active Hutko payment confirm controller/template
```

Relevant routes:

```text
checkout/payment_method.createAccount
checkout/confirm.confirm
the selected payment extension's confirm route
checkout/success
```

Database schema changes are not expected. Order-row behavior must be measured
read-only during diagnostics.

## What was already tried

- Phase 1.2 removed the unnecessary `createAccount` request for unchecked or
  missing account opt-in.
- Phase 1.2 changed button status text between request stages.
- Phase 1.3 replaced the disappearing button-only state with a fixed overlay
  that survives replacement of `#checkout-confirm`.
- Phase 1.3 displays the overlay before the first sequential request.

These changes improve feedback but do not reduce execution time of
`confirm.confirm` or the payment extension.

## What did not work / remaining gap

- Changing only button text was not reliable because `#checkout-confirm` is
  replaced by AJAX.
- A persistent overlay makes the wait visible but does not make the checkout
  faster.
- No production Network timing capture is available yet, so the 1.5–2 second
  authorized delay cannot currently be assigned to:
  - server wait in `checkout/confirm.confirm`;
  - payment-confirm server wait;
  - frontend parsing/handler work;
  - hosting/network latency.

## Proposed new task

Claude should create a separate low-priority task with two explicit parts.

### Part A — checkout submit performance

1. Capture at least five clean production or staging timings per flow:
   - authorized customer;
   - guest without account opt-in;
   - guest with account opt-in.
2. Use browser Network with `Preserve log` and record:
   - request start;
   - TTFB;
   - download/processing duration;
   - redirect start;
   - exact selected payment route.
3. Test COD, bank transfer and Hutko separately.
4. Identify whether the dominant delay is `confirm.confirm` or payment confirm.
5. Optimize the dominant endpoint without moving order creation before the
   trusted place-order click.
6. Keep `createAccount` exclusive to guest + checked opt-in.

Suggested performance objective after baseline:

```text
authorized / guest without opt-in:
at least 30% median click-to-navigation reduction;
target ≤ 1.0 second where hosting/payment constraints allow.
```

Do not treat a hidden loader or earlier draft-order creation as a performance
improvement.

### Part B — loader visual and copy

The current overlay and copy are temporary functional feedback, not an approved
final design.

Before implementation, obtain owner approval for:

- overlay versus compact inline status;
- spinner/design-system appearance;
- mobile layout;
- exact primary and secondary copy replacing
  `Оформлюємо замовлення...`;
- whether fast non-account flows should suppress the loader below a short
  threshold to avoid a distracting flash;
- account-specific status shown only for guest + checked opt-in.

The loader must remain outside `#checkout-confirm` so AJAX replacement cannot
remove it. It must also disappear on recoverable errors and must not permit a
second submission.

## Risks

High-risk area: checkout, payment, Hutko, fiscalization and order creation.

Do not:

- preload `checkout/confirm.confirm` before trusted user activation;
- weaken the ST-2b6d trusted-click gate;
- introduce a second `confirm.confirm` caller;
- parallelize account creation with order creation;
- hard-code one payment extension’s behavior for all methods;
- change order status, totals, shipping, fiscal or DB schema logic as part of a
  visual loader change.

Rollback for any future implementation must restore every touched checkout and
payment file from the patch backup and clear OpenCart template/cache files.

## Acceptance criteria for the new task

- [ ] Baseline timings exist for all three customer flows and each active payment method.
- [ ] Authorized and guest-without-opt-in send zero `createAccount` requests.
- [ ] `confirm.confirm` runs exactly once and only after a trusted place-order action.
- [ ] Payment switching/address autosave creates zero draft orders.
- [ ] Double-click creates exactly one order.
- [ ] Guest opt-in still creates exactly one account and one order.
- [ ] COD, bank transfer and Hutko redirect/confirmation behavior remains correct.
- [ ] Measured median click-to-navigation time improves by at least 30%, or the report documents the external constraint preventing it.
- [ ] Owner approves the final loader visual and copy before deployment.
- [ ] Loader is not removed by `#checkout-confirm` replacement and clears on errors.
- [ ] Full `bs-checkout-smoke` matrix passes before production deployment.

## Closure recommendation for CHECKOUT-001

Close the current CHECKOUT-001 after its existing functional QA is recorded:

- optional account checkbox is guest-only;
- guest-only Public Offer consent works;
- authorized checkout has no Public Offer block;
- account and order are each created once;
- set-password email path works without the standard duplicate registration email;
- fail-open behavior and checkout success redirect work.

Track submit latency and loader redesign only in the new low-priority task so
they do not keep the completed functional scope open.
