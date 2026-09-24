# PAY-002 — PUMB order amount reconciliation

Date: 2026-08-31
Executor: Codex. Owner explicitly approved the narrow amount correction.

## Outcome and scope

Prepared one hosting-ready PHP runner:
`patches/PAY-002_pumb-order-amount_20260831.php`.

Only production target:
`extension/pumb_credit/catalog/controller/payment/pumb_credit.php`.

No deployment, bank request, commit/push, Notion/dashboard status change, DB
schema/data migration, setting change, Mono change, delivery change, UI-layout
change or callback/refund change performed. This fixes amount preparation, not
the remaining launch gates. Existing applications are not repriced or recreated.

## Verified baseline

Owner archive `booster-debug-pay002-final-20260831-164048.tar.gz`, SHA256
`88969e89093c84e3f800a762054012e431ca7f98d7616bab9f9a353d34b2dc10`.

Owner's successful v2 preflight confirms PHP 8.0.30, test endpoints/mode,
public=0, status=1, credentials present, correct POS/partner, terms [3,4,5],
bounds 500–500000, mapped status IDs present, and unique indexes on
(cap_id,is_test) and (store_order_id,is_test). Presence is not bank authentication
or complete runtime idempotency proof.

Target before SHA256 (also confirmed by owner preflight):
`e526b2f07f9ae3950c777357a5b30636219eaf0650ffe466025bdab0e36e9e11`.

Target after SHA256:
`422d126854263405112bfe2a7267b178cdaf12aaf381dbcb8f97a1840e01ac45`.

## Root cause and implementation

Original createPayload(), approximately lines 193–199, recalculated the bank
amount from order_product.price * quantity, ignoring persisted order.total.
Checkout separately stores the totals-engine result including coupons. A 1000
UAH product with a final 850 UAH order consequently generated 1000 UAH credit.
Protocol revision §10 already requires reconciling coupons/other totals into
invoice goods, not just changing the credit_request.amount field.

Changes:

1. Read persisted order.total as the authoritative UAH total; round to cents
   using integer arithmetic. Keep four-decimal OpenCart prices as allocation
   weights. No coupon revalidation or cart recalculation at the bank boundary.
2. Allocate the total proportionally to original product value. Integer largest
   remainders distribute residual cents deterministically; order_product_id
   ordering breaks equal-remainder ties consistently.
3. Preserve names and total item quantities. When one per-unit two-decimal
   amount cannot represent a line, use at most two bank goods entries for that
   product, differing by one cent. Example: 3 units totaling 1000 UAH become
   2 × 333.33 and 1 × 333.34. This is bank-payload representation only, not a
   change to stored shop product lines/prices.
4. Retain zero-priced gifts at zero rather than charging them or dropping them.
   A basket with no positive-priced goods cannot allocate a positive total and
   is refused. Positive-priced items that would round below one cent per unit
   are refused rather than silently becoming free. These rare cases show the
   controlled error and require review; no request is sent to the bank.
5. Validate missing/invalid goods, quantity, negative/nonfinite/malformed money,
   unsupported precision and integer overflow before reservation/OAuth/API.
   Input strings accept normal nonnegative DECIMAL(...,4), not scientific
   notation. Requires a 64-bit PHP build; a narrower build safely refuses.
6. Build the payload only after the existing-transaction fast path but before
   reserving a NEW create attempt. Failure returns a Ukrainian error and leaves
   no new CREATING reservation. Existing/reserved application handling, selected
   term validation, min/max checks, successful persistence and status application
   otherwise remain unchanged.

Invariant: sum(goods.amount * goods.count) == invoice.total_amount ==
credit_request.amount == persisted order total rounded to the payable cent.
Only final serialization converts cents into the bank's UAH numeric format.

## Authoring and tests

- `scripts/pay002-amount-payload.fragment.php`: source fragment for changed
  payload builder and decimal parser; not a standalone executable.
- `scripts/build-pay002-order-amount.mjs`: exact-source, one-anchor-per-edit
  generator; preserves original CRLF; emits candidate, manifest and runner.
- `scripts/tests/pay002-order-amount.test.php`: real controller with synthetic
  DB/cURL doubles. Run with php -n; makes no real DB/bank request.
- `scripts/tests/verify-pay002-order-amount-runner.mjs`: copies into newly created
  isolated fixture directories, never executes the release from patches/.
- Work files: `work/pay002-order-amount/`.

Validation evidence:

```text
Original controller: test fails canonical_amount (1000 -> 850 regression).
Generated controller: PHP lint OK.
Release runner: PHP lint OK.
PASS exact source -> after SHA; backup SHA; self-delete; repeat
PASS source drift refused without write
PASS injected lint fault restores exact original
amount_tests=ok randomized=600 invalid=12 confirm_flow=ok real_bank_calls=0
PASS tests against ACTUAL runner-generated controller
```

Tests cover: unchanged basket, 15% discount, positive total adjustment, multiple
products/units, duplicate names, four-decimal price input, half-cent rounding,
deterministic split, free gift, maximum total, 600 seeded randomized baskets,
12 invalid cases, controlled failure before reservation, successful discounted
confirm, repeat confirm without repricing or additional bank call, existing
CREATING/CREATE_FAILED/FUNDED records, existing bounds/term/preview rejection.
The positive-adjustment case is arithmetic coverage, not a claim that NP's
current display tariff is included in payable totals.

Local PHP is 8.3.30; code uses PHP 8.0 syntax. Actual owner deployment performs
php -l using the hosting PHP binary. No production PHP/bank runtime test occurred
here. The runner test needed permission for local Node child-process spawning;
all executions remained in isolated repository fixtures without network access.

## Runner safety / rollback

Exact target-before hash, one-count anchors, candidate-after hash, backup before
write, hosting php -l, final hash verification, restore on failure, and exact
after-hash idempotence. Self-deletes only after success/already-applied.
No DB connection or data change during deployment. Rollback restores only the
target file from the printed backup directory:
`_patch_backups/PAY-002_pumb-order-amount_20260831-<timestamp>-<suffix>/`.
Do not roll back blindly after bank testing; first assess any created application.

## Owner deployment

Upload the release runner to public_html; run:

```bash
cd ~/public_html || exit
php PAY-002_pumb-order-amount_20260831.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

Return the deployment output. Keep public=0 and test_mode=1; do not change
credentials, callbacks, visibility or other settings for this patch.

## Remaining owner/bank QA

- Confirm done=ok, expected target hash, backup and cache-cleared output.
- Verify product/modal and checkout still retain PUMB's selected term and Mono
  behavior; cart overstock correction and shipping remain unchanged.
- Use a NEW, explicitly coordinated bank-test order, not an existing cap_id.
- Check payable total and term before submitting; verify the bank-received amount
  and NEW_4/customer-selected term from safely redacted evidence.
- Include a discounted basket and a multiple-unit cent-split case in bank QA.
  Local arithmetic/schema-shape tests do not prove that the bank accepts the
  split representation; if rejected, stop and inspect the bank's validation
  response rather than altering amounts to obtain acceptance.
- Complete the agreed bank-assisted lifecycle/refund. No public activation until
  remaining PAY-003/PAY-005/status/CRM/regression/approval gates are reconciled.

The existing generic create-success redirect and test-callback status isolation
remain unchanged and must not be mistaken for completed launch readiness.

## Owner-reported deployment evidence

The owner supplied successful production-host installation output at
2026-08-31T16:49:55+00:00. This updates deployment evidence only; it is not
post-deployment UI/bank acceptance or an authorization to enable public access.

- Candidate and after SHA256 both match
  `422d126854263405112bfe2a7267b178cdaf12aaf381dbcb8f97a1840e01ac45`.
- Hosting php_l=ok; assertions=ok; database_touched=no; done=ok;
  self_delete=ok; cache cleared.
- Changed only `extension/pumb_credit/catalog/controller/payment/pumb_credit.php`.
- Backup:
  `/home2/boosters/public_html/_patch_backups/PAY-002_pumb-order-amount_20260831-20260831-164955-6240d8`.

Next gate: coordinate a bank-assisted stage test using a new UI-created order,
the bank-issued test phone and four payments. Verify an actually applied discount
and the resulting bank amount, then the agreed lifecycle/refund. A later
multiple-unit case must exercise the split-cent representation. No test
application or bank request has been executed by Codex.
