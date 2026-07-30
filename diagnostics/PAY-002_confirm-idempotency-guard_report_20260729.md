# PAY-002 — PUMB confirm idempotency guard report

Date: 2026-07-29  
Codex config: model=Sol · effort=xhigh

## Outcome

Prepared a host-run PHP patch that makes the PUMB `confirm()` create flow idempotent for the same OpenCart order and `is_test` mode. The payment method remains disabled; this does not call the bank and does not enable checkout.

## Live-source evidence

- Input archive: `C:\Users\14bez\Downloads\booster-debug-pay002b.tar.gz`
- SHA-256: `972559EDF90B476639AA41D6D19CFEB7CD52BE8A2AA0B85D9740A64D27FC1712`
- Archive contents: only `extension/pumb_credit/catalog/controller/payment/pumb_credit.php`
- The current controller still creates a bank application directly on each `confirm()` call and has no prior transaction reservation.

## Implementation

- Before the outbound `POST /sf-credits`, the controller writes a `CREATING` reservation into the existing `pumb_credit_transaction` table.
- The existing unique `store_order_is_test(store_order_id, is_test)` index is used as the cross-request serialization boundary. A per-request random reservation token determines the single request allowed to call the bank.
- A duplicate request returns the stored state and does not issue another bank POST.
- Promotion of a successful reservation explicitly updates cap_id in the existing unique (store_order_id, is_test) row, so polling, callbacks, and admin lifecycle actions retain the real bank application identity.
- A rejected/invalid create response becomes `CREATE_FAILED`; a later confirm reports manual review required and never retries automatically.
- The reservation and lookup are partitioned by `is_test`, so test and production transactions cannot block each other.

## Host preflight and rollback

The runner stops without changing source unless it confirms:

- exact pre-guard controller anchors;
- the transaction table and required columns;
- a unique `store_order_is_test(store_order_id, is_test)` index.

It backs up the controller under `_patch_backups/`, runs `php -l`, restores the source if lint or marker creation fails, then self-deletes after success. It makes no schema or data migration, so no SQL rollback is required.

## Local verification

- `php -l patches/PAY-002_confirm-idempotency-guard_20260729.php`
- A source-transform fixture applies the runner changes to the supplied live controller, verifies exactly two reservation expressions and the resulting `cap_id=VALUES(cap_id)` promotion clause, then runs `php -l` on the assembled controller.

## Remaining external gates

- PUMB must remain disabled.
- Bank test credentials, callback behavior/IPs, successful test-contour create, callback/polling behavior, and live payment QA are not verified by this patch.
- The bank's unresolved application-TTL discrepancy remains outside this guard's scope.

## Owner deployment

Upload only the PHP runner to `~/public_html`, run it once, and clear OpenCart cache only after `done=ok`.
