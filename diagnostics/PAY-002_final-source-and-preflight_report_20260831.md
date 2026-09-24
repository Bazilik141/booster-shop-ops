# PAY-002 — fresh-source review, bank PDFs, and read-only preflight

Date: 2026-08-31
Scope: continue the owner's diagnostic intake and identify readiness for the
final integration test. No production code/settings changes or bank operations.

## Result

Do not enable public PUMB checkout. The fresh source reproduces an amount
mismatch for order-level discounts. A separate, narrowly approved correction
must reconcile invoice goods and requested credit with the actual payable total.
This report does not implement that correction.

The requested source/PDF inputs are now present. The remaining immediate input
is a safe report of current settings and transaction-table constraints, not
another code archive or a database/customer-data dump.

## Source evidence

Archive: `booster-debug-pay002-final-20260831-164048.tar.gz`

SHA256: `88969e89093c84e3f800a762054012e431ca7f98d7616bab9f9a353d34b2dc10`

Inspected archive entry types/paths before extracting to the isolated
`work/pay002-final-audit/source/` directory. No links or path traversal observed.
Old `success.php.before-*` backup files were present but not treated as active
controllers. The source contains no current DB settings or index inventory.

Verified against owner-deployed after hashes:

| Current file | SHA256 / result |
|---|---|
| `catalog/controller/checkout/cart.php` | `9d0bd9b2ffe271ce774dac2797783c80c15091ff0291a0e53d5794cf069e84d5` — canonical-stock match |
| `catalog/controller/checkout/checkout.php` | `dbf4b8b98c5be25286c7e44254484bb78830179092c2340c50f3a81b2949c3a2` — canonical-stock match |
| `catalog/view/template/checkout/cart_list.twig` | `0c264b8676e8d7e8f97e59cab9d02b204005d1b1cbf4d3c8659a1df1d160fbec` — canonical-stock match |
| `catalog/view/template/checkout/payment_method.twig` | `efee1a60c2cecc7547787646690cc00fc01a428f9a2e9a64d9f08d464db6d396` — provider-selection match |
| `catalog/controller/checkout/shipping_method.php` | `eb14c74de452aa3aa074780fa046704084f50f44d730fe389e9fe79a7d2639e9` — NP timing/cache controller match |
| `extension/pumb_credit/catalog/controller/payment/pumb_credit.php` | `e526b2f07f9ae3950c777357a5b30636219eaf0650ffe466025bdab0e36e9e11` |
| `extension/pumb_credit/admin/controller/payment/pumb_credit.php` | `cb27b2d0c8102774066926e6cd5426dd705c5406e1d6cc10e76693601c5a7e1d` |

The NP extension model was not in this narrow archive. Its earlier deployment
and owner speed acceptance are recorded separately, not re-proven here.

## Confirmed amount defect

Location: `extension/pumb_credit/catalog/controller/payment/pumb_credit.php`,
`createPayload()`, approximately lines 193–199.

- `confirm()` validates the stored `order.total` against the configured bounds.
- `createPayload()` ignores that total. It selects order-product name/quantity/
  price and independently sums rounded unit price times quantity.
- That independently computed sum becomes both invoice total and credit amount.
- Checkout `pay002CheckoutPayable()` uses the totals engine and coupon helper;
  `checkout/confirm.php` stores totals separately from the unchanged product
  data (lines 226–232 and 297–314). These are different amount sources.
- The fresh `BoosterCoupon` helper does not prohibit coupons for PUMB.

Local fixture invokes the actual archived private payload method via reflection,
with a DB double returning one synthetic product at 1000 UAH:

| Stored order total | Bank payload amount | Result |
|---|---|---|
| 1000 | 1000 | equal |
| 850 | 1000 | discount mismatch reproduced |
| 1050 | 1000 | generic extra-total mismatch reproduced |

The 1050 case is an arithmetic edge case, not a claim that current NP shipping
adds 50 UAH to the order. No real order or application was created.

Required correction must preserve selected term, canonical stored order total,
invoice sum consistency and cent-level arithmetic. Merely replacing the credit
amount leaves invoice goods inconsistent. Coupons, multiple units, rounding,
bounds and failure-before-bank behavior require explicit tests. Do not silently
disable coupons or change other payment providers as a workaround.

## Other readiness findings

- The term is passed into `createPayload()` and locally remains 4. This is not
  end-to-end bank proof of a customer-created order.
- Existing-create responses and a reservation mechanism are present. Their
  correctness depends on live unique indexes; source presence is not proof of
  concurrent-create safety. The diagnostic collects index metadata only.
- The PUMB controller's `index()` redirects non-error create responses to
  checkout success; this is not the specified PAY-003 wait/recovery experience.
  A successful create is not evidence of client signing or funding.
- `pay002PumbGate()` exists but `pay002_credit_gate` is not exported for the
  unavailable-provider explanation; PAY-005 remains a release requirement.
- Test callback/poll updates intentionally skip order-status application;
  `confirm()` still sets initial WAITING_CLIENT. Do not count a stage lifecycle
  as proof of production status/CRM progression, or defeat test isolation.
- The fresh `upsertTransaction()` preserves the earlier `create` section of
  payload across callback/poll updates. This corrects the prior report's generic
  concern that callbacks necessarily overwrite the create evidence.
- Schema/state checks, negative cases, shared statuses, CRM, layout approval and
  controlled production cutover are still separate gates. This is a bounded
  readiness review, not an exhaustive security or bank certification audit.

## Bank PDF review

Read and visually inspected all three pages (2 + 1), using pypdf text extraction
and Poppler renderings. Original PDFs were not edited.

`Інструкція_тестування_API_СЧ.pdf`:

- OAuth test endpoint, EXT_OIC and password/optional refresh-token grant.
- GET status requires Bearer authentication and X-Flow-Id.
- Static fixture IDs: 1 IN_PROGRESS; 2 CLIENT_NOT_FOUND; 3 REJECTED;
  4 OVER_LIMIT; 5 NO_LIMIT; 6 IDENTIFICATION_FAILED; 7 WAITING_CLIENT;
  8 PUSH_TIMEOUT; 9 FAIL_OTP; 10 WAITING_STORE_CONFIRM;
  11 CONFIRM_TIME_EXPIRED; 12 CANCELED_BY_STORE; 13 FUNDED.
- These are static GET fixtures, not evidence of application creation,
  callbacks, customer-term propagation, refund or a new live transaction.

`Статуси заявок, та що вони означають.pdf`:

- Distinguishes client wait, store confirmation, funded and refund-finished.
- PUSH_TIMEOUT describes 60 minutes awaiting the client; do not confuse this
  with the separately bank-confirmed seven-day store/production lifetime.
- Generic retry guidance does not authorize blind POST retries after an
  ambiguous response. Confirm prior application state first.
- Generic physical-handover advice does not supersede the shop's separately
  agreed carrier-handover process. No operational policy was changed here.

## Files added

- `scripts/PAY-002_final-preflight_20260831.php`
- `scripts/tests/pay002-final-preflight.test.php`
- `scripts/tests/fixtures/pay002-preflight/config.php` (synthetic credentials)
- `scripts/tests/fixtures/pay002-preflight/catalog/.gitkeep`
- `scripts/tests/pay002-final-source-audit.test.php`
- This report. Extraction/render intermediates remain under `work/`.

## Preflight safety and output

Persistent, CLI-only diagnostic; intentionally not a self-deleting patch.
Upload a copy and run from the OpenCart web root. It:

- tokenizes literal config definitions without executing config.php;
- connects through mysqli using the local config, suppressing raw exceptions;
- issues only bounded SELECT/SHOW queries through an allowlisted query helper;
- projects secret settings to present/empty inside SQL; never selects their raw
  values, transaction payloads, orders, customer data or agreement identifiers;
- reports recognized endpoint environment, mode/public/status flags, terms,
  bounds, bank POS/partner match, IP-list shape, status-ID existence, schema and
  unique-index columns, plus relevant source hashes;
- treats unexpected endpoint/setting text as redacted, not as text to echo;
- never executes OAuth, bank API, a callback, order creation or mutation SQL;
- does not modify files or clear caches. No rollback needed.

`done=ok` means collection succeeded, NOT that launch or a bank test is approved.
The diagnostic does not authenticate credentials with the bank or prove the
preview gate dynamically. Missing/duplicate settings must be reviewed in context.

## Local validation

PHP executable: local PHP 8.3.30; source written for PHP 8.0 syntax. No claim of
execution under production PHP 8.0 or against a real DB.

```text
php -l scripts/PAY-002_final-preflight_20260831.php
No syntax errors detected
php -n scripts/tests/pay002-final-preflight.test.php
preflight_tests=ok; real_db_calls=0; bank_calls=0
php -n scripts/tests/pay002-final-source-audit.test.php
source_reproduction=complete; bank_calls=0; db_fixture_only=yes
```

Fixture checks cover literal config parsing, dynamic/duplicate/unsafe-prefix
refusal, secret and exception redaction, endpoint classification, malformed
settings, unsupported CIDR notation, bounded queries, duplicate settings and
unique-index column ordering. No production mutation/idempotence test applies
to a read-only diagnostic. No bank/production UI test was performed.

## Owner command and next gate

Upload `scripts/PAY-002_final-preflight_20260831.php` as
`~/public_html/PAY-002_final-preflight_20260831.php`, then run:

```bash
cd ~/public_html || exit
php PAY-002_final-preflight_20260831.php
```

Return its output, not raw settings or credential screenshots. On error return
the phase; do not edit config or retry bank operations. No cache clear required.
Keep public PUMB disabled. Review the preflight result, obtain scoped authority
for the amount correction, verify it locally, and only then arrange the next
controlled bank-assisted test. No commit/push/Notion/dashboard action performed.
