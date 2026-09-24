# Codex Report — PAY-003: PUMB final operational gates

Date: 2026-09-01

## Scope

Implemented the owner-approved final PAY-003 patch:

1. Surface existing and future PUMB `CREATE_FAILED` orders without exposing unrelated checkout drafts.
2. Add a guarded `Скасувати магазином` action to the PUMB order card.
3. Return safe bank-refresh diagnostics: HTTP status, request `X-Flow-Id`, and top-level response field names only.
4. Run code-contract preflight as part of the patch and leave production runtime QA owner-gated.
5. Change the waiting-page label from `Оплата частинами` to `Сплата частинами`.
6. Remove the customer-facing manual refresh button while preserving automatic background checks.

No database mutation, schema change, bank call, shared CSS edit, or production deployment was performed locally.

## Root cause and implementation notes

- OpenCart's default admin order list excludes `order_status_id=0`. The PUMB create-failure path persisted `CREATE_FAILED` but did not leave that hidden draft state. Future failures now use the configured failed order status.
- Existing `CREATE_FAILED` drafts (including order 345) are surfaced by two narrow `EXISTS` clauses in the default admin list and its matching count query. Other status-zero checkout drafts remain hidden.
- The order card previously exposed refresh, shipment, and refund actions but not the already-supported bank cancellation contract. The new button reuses `PATCH /sf-credits/{id}` with `method=CLOSE`, `cancel_reason=CancelLead50`, and `DIGITAL_SF`.
- The old refresh error discarded the request correlation value. The API helper now retains the generated `X-Flow-Id`; raw bank response values remain server-side.
- No CSS override was needed. Existing Bootstrap `flex-wrap` and PAY-003 responsive action rules remain the canonical layout behavior; no `!important`, fixed/absolute positioning, or new magic pixels were added.
- The two `setTimeout` calls in `pay003-credit.js` are pre-existing functional request scheduling/deadline timers. They remain because removing the visible button must not remove automatic polling.

## Files touched

```text
patches/PAY-003_pumb-final-gates_20260901.php
scripts/build-pay003-final-gates.mjs
scripts/tests/pay003-final-gates.test.php
scripts/tests/pay003-final-gates-client.test.mjs
work/pay003-final-gates/candidate/...
```

Runtime targets embedded in the patch:

```text
extension/pumb_credit/catalog/controller/payment/pumb_credit.php
extension/pumb_credit/admin/controller/payment/pumb_credit.php
<detected-admin>/view/template/sale/order_info.twig
<detected-admin>/model/sale/order.php
catalog/view/template/checkout/credit.twig
catalog/view/javascript/pay003-credit.js
```

## Local verification

```text
checks=23 result=ok bank_calls=0 database_writes=0
checks=7 result=ok network=synthetic timers=virtual
PHP lint: runner and all three changed PHP candidates OK
JavaScript syntax: OK
Twig parse: admin order page and customer wait page OK
fixture install: done=ok, self_delete=ok
fixture repeat: already_applied=yes, self_delete=ok
fixture rollback: rollback=ok; all six source hashes restored
source drift: refused before writes with exact SHA256 mismatch
```

Patch SHA256:

```text
8eefd8a436436e98e546c528e73dc7931a1d241b8e26b2ab52631e24306066de
```

## Rollback

The runner creates:

```text
_patch_backups/PAY-003_pumb-final-gates_20260901-<timestamp>-<suffix>/
```

Run `php rollback.php` from the printed backup directory, then clear OpenCart caches. File rollback cannot undo a bank cancellation, shipment confirmation, or refund already explicitly requested by an admin.

## Post-deploy owner QA

- Confirm output contains `done=ok`, `self_delete=ok`, and `final_preflight=code_contract_ok_owner_runtime_qa_required`.
- Open the default order list and verify order 345 is visible; other incomplete checkout drafts must not appear.
- Open a PUMB order in `WAITING_CLIENT` or `WAITING_STORE_CONFIRM`: `Скасувати магазином` is enabled, asks for confirmation, and no request is sent when confirmation is declined.
- Open a terminal PUMB order such as `FUNDED`, `CANCELED_BY_STORE`, or `FAIL`: cancellation is disabled.
- On a refresh error, verify the red message includes an HTTP code and request code, but not the raw bank body.
- Open the customer waiting page at approximately 360 px, 768 px, and desktop width: the header says `Сплата частинами`, the manual refresh button is absent, links remain usable, and automatic status changes still appear.
- Smoke home, category, product, cart, checkout entry, and one non-PUMB payment method.

## Side effects and risks

- A future PUMB create failure will enter the configured failed order status and may therefore participate in existing order-sync automation. This is intentional for owner visibility but should be observed once in production.
- The cancellation button performs a real bank mutation after explicit admin confirmation. Use only when the store intends to cancel the application.
- Local preflight proves file composition, syntax, guards, rollback, and client behavior. It is not production launch approval; runtime QA remains required after the owner deploys.
