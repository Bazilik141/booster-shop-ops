# PAY-003 — shared credit waiting/recovery implementation

Date: 2026-08-31; owner decision and bank-coordination note: 2026-09-01
Executor: Codex. Local implementation and synthetic verification only.

## Delivery gate

Implementation was deployed by the owner on 2026-09-01 at 05:36:09 UTC and is
NOT yet bank-certified. The owner approved the client-confirmed transition.
No Notion/dashboard status, commit or push performed.

The original section-10 brief says to leave the waiting page after client
confirmation. The unified smoke plan instead mentions terminal success. The
candidate implements the former: PUMB WAITING_STORE_CONFIRM or FUNDED; Mono
IN_PROCESS/WAITING_FOR_STORE_CONFIRM or SUCCESS/ACTIVE or SUCCESS/DONE. This
avoids keeping a customer waiting until physical dispatch. The destination is
the shared "Order accepted" page, not a claim that funding has happened. The
owner explicitly approved this reconciliation on 2026-09-01.

## Bank coordination update — 2026-09-01

PUMB replied that they believe they can highlight/advance certain statuses; if
the question concerns PROD, their colleagues may not be able to advise, but the
current contact can help with particular statuses. This is treated only as an
offer to coordinate controlled TEST-state progression. It is not PROD support,
production approval or proof of any runtime state. Before the bank cycle, the
owner should confirm that the request is specifically for TEST and agree on the
exact statuses and time window.

## Owner deployment evidence — 2026-09-01

The owner ran the PAY-003 runner in `~/public_html`. All eleven reported
`after_sha256` values match the locally generated manifest. Hosting reported
`php_l=ok`, `assertions=ok`, `database_touched=no`, `done=ok`,
`self_delete=ok`, followed by `cache cleared`.

Server backup:
`/home2/boosters/public_html/_patch_backups/PAY-003_credit-wait-recovery_20260831-20260901-053609-74612f`.

This proves successful file installation and syntax/runner assertions. It does
not prove live storefront behavior, a bank TEST lifecycle or PROD readiness.

### Live QA incident — order 341

The first UI-originated TEST attempt created OpenCart order 341 and NCRM-14
successfully classified `credit_pumb_4`, but checkout displayed a misleading
CAPTCHA error. A read-only diagnostic found order status 15, no PUMB transaction
rows and `bank_application_present=false`; no bank call was made by that
diagnostic.

Root cause: the deferred checkout contract searches the rendered provider output
for the canonical `#button-confirm`. The PAY-003 PUMB confirmation data still
rendered `#pay002-pumb-confirm-button`, so checkout treated the missing canonical
button as a CAPTCHA failure before the provider POST or PAY-003 redirect could
run. The redirect itself is not the cause. Mono already renders
`#button-confirm`.

Prepared hotfix runner:
`patches/PAY-003_pumb-confirm-button-contract_20260901.php`. It changes only the
PUMB confirmation `button_id`, creates a backup, lints PHP, verifies exact
before/after hashes and is idempotent. Local isolated apply and repeat passed.

After that hotfix, the same session successfully created TEST application
`cap_id 19040764` for order 341 and reached the PAY-003 waiting page. The
read-only diagnostic reported `WAITING_CLIENT`, `is_test=1`, but
`requested_term=3` while the checkout UI and persisted order payment code showed
`credit_pumb_4`. This fails the selected-term acceptance criterion but still
allows the application to exercise callbacks and PAY-003 state transitions.

Term root cause: a refreshed checkout preserves the canonical order payment code
but may clear the separate `pay002_pumb_credit_term` session hint. The PUMB
controller used that hint/default 3 to build its confirm URL. Prepared
`patches/PAY-003_pumb-canonical-term_20260901.php` to derive the term from the
persisted order payment code instead, both when rendering and when validating
the create request. It changes one controller, performs no DB write, and passed
PHP lint, 80 synthetic PAY-003 checks, isolated apply and idempotence.

## Source and root cause

Owner archive: `booster-debug-pay003-20260831-200446.tar.gz`.
SHA256: `375e7b5b968e1c5578e5897ebfd3be2c406b9fef632cd8cdd49a780cae7ec357`.
Paths and member types were checked before extraction to `work/pay003/source`.

The archive's PUMB controller hash matches the deployed amount correction:
`422d126854263405112bfe2a7267b178cdaf12aaf381dbcb8f97a1840e01ac45`.

- Both provider index/confirm paths immediately used checkout success after
  creation, before client confirmation. This is the canonical redirect defect.
- Success clears checkout/cart session data. It cannot double as an awaiting
  confirmation page with safe historical-order recovery.
- The provider poll routes were session-order-only, without a shared historical
  ownership/environment contract or cross-session PUMB frequency gate.

## Implemented scope

- Shared `checkout/credit`, `.state`, `.complete` controller/model/template.
- Existing create routes hand off to it; page refresh/recovery never creates,
  cancels, ships or refunds a bank application.
- Existing failed/ambiguous Mono transactions now go to review instead of
  allowing another create POST for that same transaction/order. This is not a
  new general-purpose concurrent-create reservation system for Mono.
- Allowlisted human-readable waiting, confirmation, failure, timeout, returned
  and manual-review states. Unknown states never mean success.
- Logged-in order-owner recovery, store isolation and session-only guest access
  (24-hour maximum, up to 20 remembered orders). No bearer token or bank ID in
  URLs. A guessed order number is insufficient. Expired guest sessions require
  account access or store support; no new email/token recovery system is added.
- A credit-status link in the authenticated account order-detail page.
- Checkout/cart detach uses a captured cart fingerprint; changed carts and new
  checkout sessions are not cleared by recovery. Creation-in-progress detaches
  only after a real application is available. Historical success also preserves
  a new order ID, coupon and pending account message.
- GET state is DB-only. CSRF-protected POST can invoke the existing provider
  state API. A cache-directory file lock and timestamp cap bank fallback to one
  call per application/environment per 30 seconds across tabs and sessions.
  Failures consume the interval; a throttled read does not extend it.
- Poll persistence uses compare-and-swap so an intervening callback wins.
  Existing status handlers are reused; test PUMB still skips order-status sync.
  Mono poll events are recorded; original environment metadata survives both
  provider callback persistence paths. PUMB amount methods are byte-identical.
- Environment fingerprint includes endpoint and merchant identity, plus PUMB
  mode/OAuth endpoint. Legacy rows without environment provenance can display
  stored callback states, but cannot initiate uncertain bank fallback.
- UI pauses polling in background tabs and stops after 24 automatic checks;
  manual status checks remain possible. No POST-create retry on network error.
- Dedicated scoped CSS; no shared theme rules were edited.

Out of scope: PAY-005, status consolidation, NCRM mapping changes, public flags,
credential changes, bank approval, customer retries with a new order, shipment,
cancellation/refund UI, schema changes and production cutover.

## Files and runner

Runner: `patches/PAY-003_credit-wait-recovery_20260831.php`.
Runner SHA256: `8cdf589c460ede1b9135760ac96ed40fb5f8af6051b8e295f627ccc5e911cc98`.

Changes five existing files:

- `extension/mono_chast/catalog/controller/payment/mono_chast.php`
- `extension/pumb_credit/catalog/controller/payment/pumb_credit.php`
- `catalog/controller/checkout/success.php`
- `catalog/controller/account/order.php`
- `catalog/view/template/account/order_info.twig`

Adds six files:

- `catalog/model/checkout/credit.php`
- `catalog/controller/checkout/credit.php`
- `catalog/view/template/checkout/credit.twig`
- `catalog/view/template/checkout/credit_confirm.twig`
- `catalog/view/javascript/pay003-credit.js`
- `catalog/view/stylesheet/pay003-credit.css`

Build inputs are in `scripts/pay003/`, builder `scripts/build-pay003.mjs`.
Exact before/after hashes and anchors: `work/pay003/manifest.json`.
Additional source guards cover checkout confirm, account order model and the
existing success template. Partial/drifted installs fail closed.

## Local validation

Local PHP 8.3.30, PHP-8.0-compatible syntax; hosting PHP 8.0 execution still
requires owner deployment. No real database, OAuth or bank request was used.

```text
php -n scripts/tests/pay003.test.php
checks=80 result=ok real_bank_calls=0 real_db_calls=0

node scripts/tests/pay003-client.test.mjs
checks=14 result=ok network=synthetic timers=virtual

php scripts/tests/pay003-preview.php
twig_arrayloader_compile=ok templates=3

node --check work/pay003/confirmation-rendered.js
passed

node scripts/tests/verify-pay003-runner.mjs
PASS apply/after hashes, backup hashes, self-delete and repeat
PASS exact explicit rollback
PASS source drift and pre-existing new-file conflict refusal
PASS candidate lint failure before target writes
PASS injected partial-write fault restores originals/removes newly added files
PASS 80 tests against actual runner-generated PHP
```

Runner tests use isolated copies under `work/pay003/runner-*`; source runner
is never executed in the repository root. The tested rollback preserves copies
of generated files under its backup, so removed new files are recoverable.

Twig 3.28.0 was installed only under `work/pay003/tooling` for compilation and
synthetic rendering. This is NOT evidence of the hosting Twig version and is
not packaged for deployment.

## UI evidence and override review

Browser fixture uses actual candidate Twig/JS/CSS and archived shared styles,
with a synthetic header/footer and synthetic bank-state endpoint. No production
browser session was used. Widths 390, 768 and 1440: no horizontal overflow,
including a 658-character message and long order number; mobile buttons 44px
high. Screenshots: `work/pay003/preview-390.png`, `preview-768.png`,
`preview-1440.png`. Network failure and waiting-to-rejected updates were visible.
Keyboard-focused account link showed a solid visible outline. Button hover changed
from rgb(22,163,74) to rgb(21,128,61). The synthetic confirmed case navigated to
`/complete` and displayed its expected completion heading. Browser viewport was
reset, the test tab closed, and the localhost preview server stopped afterwards.

The first synthetic footer omitted `.bs-footer` and reproduced the legacy
absolute footer from `stylesheet.css:252`. Existing DS overrides at about
`boostershop-ds.css:1108` already resolve it for `footer.bs-footer`; the fixture
was corrected to the theme structure. No production override was added.

Prior success-related patches were inspected: CHECKOUT-006/007/008 and st2b2/3.
The new component does not modify their selectors. No new `!important` or
absolute/fixed positioning. `setTimeout` is limited to documented polling and
request deadlines. Component max-width/spacing and 44px touch height are layout
and accessibility bounds, not business-logic delays.

## Rollback and owner deployment

Upload only the runner to `~/public_html`. It creates
`_patch_backups/PAY-003_credit-wait-recovery_20260831-<timestamp>-<suffix>/`,
including `original/`, `generated/`, `manifest.json` and `rollback.php`.

```bash
cd ~/public_html || exit
php PAY-003_credit-wait-recovery_20260831.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

To roll back, run `php <printed-backup>/rollback.php` and the same cache clear.
Rollback first validates every current target; later owner changes cause refusal.
It restores the five originals and removes only the six added target files.
It does not revert runtime transaction states or application progress.

Deployment writes no DB rows. Normal page runtime uses existing transaction
tables, existing order-status handlers, session data and small poll-lock files
under DIR_CACHE. Those lock files intentionally do not match `cache.*` cleanup.

## Coordinated PUMB TEST evidence — order 341

Owner-run read-only diagnostics and bank support established the following on
2026-09-01 for `OC-341`, transaction id 10 and TEST `cap_id 19040764`:

- the customer checkout created exactly one bank application and initially
  stored `WAITING_CLIENT`;
- bank support reported the intermediate transition to
  `WAITING_STORE_CONFIRM` and later reported `FUNDED`;
- the final read-only database diagnostic at `2026-09-01T07:34:41+00:00`
  independently found `state=FUNDED`, `is_test=1`,
  `agreement_number_present=true` and `bank_application_present=true`;
- OpenCart order status remained id 17 by design: TEST callback processing
  updates the transaction but does not apply production order-status history;
- the application stored `requested_term=3` although the customer selected four
  payments. This application predates the canonical-term correction and is not
  proof of the fix. A fresh coordinated TEST application must still prove
  `requested_term=4` and bank product `NEW_4`;
- the deployed canonical-term correction reported after SHA-256
  `77c0cd2d37854b8822173401983160894f809cea29e31cf80e508e5bfc58b6ab`,
  `php_l=ok`, `assertions=ok`, `database_touched=no` and `done=ok`.

The storefront/account product action labelled `Повернути` is the ordinary
OpenCart merchandise-return route. It is not the PUMB API refund action and must
not be used as bank-test evidence. PUMB TEST refund is separately owner-gated in
the payment extension's admin `Manual lifecycle actions` section.

## Required owner QA / remaining gates

1. Client-confirmed transition approved by owner on 2026-09-01; do not wait for
   physical-shipment funding.
2. Owner deployment, hosting PHP lint and after-hash verification completed;
   Tier-1 page smoke test remains.
3. Private test preview only: product-selected term reaches waiting page, account
   recovery and same-session guest recovery work, wrong account cannot read it.
4. In the coordinated bank window: callback and missing-callback fallback,
   client-confirmed transition, no duplicate create, failure/timeout messages.
5. Recheck Mono and non-credit checkout, coupon/cart preservation, actual theme
   output and account-detail link. Local fixtures are not these live proofs.
6. Keep public=0/test_mode=1 until separate bank, shared-status/NCRM and launch
   gates are fulfilled. Do not call PAY-003 or PAY-002 production-complete yet.
