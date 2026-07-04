# Codex Report — CHECKOUT-001 Phase 1: stock-checkout guest account creation

Date: 2026-07-04

## Scope

Implemented the Phase 1 handoff against the fresh owner-supplied bundles
`booster-debug-CHECKOUT-001-phase1.tar.gz` and
`booster-debug-CHECKOUT-001-register-mail-20260704.tar.gz`.

The patch:

- adds an unchecked guest-only opt-in beside the checkout agreement area;
- persists the preference without attaching it to `#form-register` autosave;
- performs account creation as a separate AJAX pre-step after the existing
  ST-2b6d trusted-click gate and before the existing `confirm.confirm` call;
- returns the same browser response for unchecked, existing-email, and newly
  created cases;
- treats account pre-step validation and database failures as fail-open: logs
  the failure, clears the opt-in, and continues as guest;
- treats transport failures as fail-open in the browser: records the diagnostic,
  clears the visible opt-in, and continues checkout;
- creates the customer through the existing customer model, creates the saved
  address through the existing address model, logs in the new account, and links
  the pending checkout session to it;
- generates a normal one-time `password` token and switches the corresponding
  mail event to a dedicated account-created template;
- suppresses the standard customer registration email only when `addCustomer()`
  receives the CHECKOUT-001-scoped event argument; normal registration and the
  configured admin new-account alert remain unchanged;
- keeps newsletter at `0`.

Not touched: SimpleCheckout, `system/library/url.php`, `confirm.php`,
`confirm.twig`, customer model, Hutko/payment implementation, fiscalization,
CRM, order status, database schema, SEO files, dashboard, or roadmap status.

## Files touched

```text
patches/CHECKOUT-001_guest-account-creation_20260704.php
```

Runtime targets:

```text
catalog/view/template/checkout/checkout.twig
catalog/view/template/checkout/payment_method.twig
catalog/controller/checkout/payment_method.php
catalog/controller/mail/forgotten.php
catalog/controller/mail/register.php
catalog/view/template/mail/account_created.twig   — new
```

## Fresh-source preflight

Exact SHA256 gates were captured from the 2026-07-04 live bundle:

```text
checkout.twig        767f205f4bec6eb0d0e44e42c4d9ebd5f35e522dd9b86bb50ea18ec513260637
payment_method.twig  ee517e2778c1ea91d5519fbd782713108ab286e3fcd2eb41660223f984c2d6dc
payment_method.php   2cfc357418b0b140b8f41051730cf8d27694339feb6b53848f77201a6d00beeb
mail/forgotten.php   09f1d5e9ad50b43022fd06ed3213ec4b201bdbec676ed82c15630205022cf9e5
mail/register.php    fd8908861a420828ce305023dfc58c351f7a18b2f8060d1f1ee25827de4a3548
```

The patch fails before writing if any source hash or exact anchor differs.

Verified before implementation:

- ST-2b6d `nativeEvent.isTrusted === true` gate is present once;
- the delegated trusted-click handler remains unchanged;
- the `#form-register` autosave selector is present once;
- the existing checkout has one actual `.load()` call to
  `checkout/confirm.confirm`;
- `confirm.php` remains read-only.

## Dry-run result

First clean replay against a fresh extraction:

```text
already_applied=no
changed_files=6
php_lint_payment_controller=exit=0
php_lint_mail_controller=exit=0
php_lint_register_mail_controller=exit=0
done=ok
```

Focused output checks:

```text
checkout.twig JS syntax=ok
payment_method.twig JS syntax=ok
trusted gate count=1
autosave selector count=1
runtime_smoke=ok
runtime_fail_open=ok
mail_event_smoke=ok
checkout001_standard_registration_customer_email=0
checkout001_set_password_customer_email=1
ordinary_registration_path=unchanged
account_prestep_failure_mode=fail_open:log_and_continue_checkout
```

The runtime mock covered:

- unchecked opt-in → zero customer writes;
- existing email → zero customer writes, same success JSON, guest stays
  `customer_id=0`;
- new email → one customer, one address, one token, newsletter `0`, login and
  checkout-session linkage;
- repeat pre-confirm call → no duplicate.
- simulated database exception → rollback, generic log entry, cleared opt-in,
  success JSON, and guest checkout continuation.
- CHECKOUT-001 account creation → zero standard registration emails and exactly
  one dedicated set-password email;
- ordinary account registration still enters the standard registration-mail
  path.

## php -l result

```text
No syntax errors detected in CHECKOUT-001_guest-account-creation_20260704.php
No syntax errors detected in catalog/controller/checkout/payment_method.php
No syntax errors detected in catalog/controller/mail/forgotten.php
No syntax errors detected in catalog/controller/mail/register.php
```

Local PHP: 8.3.30.

## Idempotency

Second run on the patched dry-run tree:

```text
already_applied=yes
changed_files=0
done=ok
```

An unrecognized partial marker state fails loudly instead of applying over an
incomplete deploy.
The patch also recognizes the previous five-file CHECKOUT-001 version and safely
upgrades only `payment_method.php` and `mail/register.php`:

```text
upgrade_from=CHECKOUT-001 pre-mail-suppression patch
changed_files=2
standard_registration_customer_email=suppressed_for_checkout001_only
admin_registration_alert=unchanged
done=ok
```

## Database behavior

Patch execution itself does not write application data and makes no schema
changes.

At checkout runtime, only a new-email guest who explicitly checks the opt-in can
create rows through existing OpenCart model/event paths:

```text
{DB_PREFIX}customer
{DB_PREFIX}address
{DB_PREFIX}customer_token
{DB_PREFIX}customer_activity / customer_ip (core events/login)
```

Existing email, unchecked opt-in, or repeat request creates no customer.
The exact manual test-account cleanup SQL is included in the patch header and is
restricted to a confirmed test `customer_id`.

The CHECKOUT-001 `addCustomer()` call carries a scoped event argument. The
customer-facing `mail/register.php::index()` handler returns before sending its
standard welcome email only for that argument. Its admin-alert handler and normal
registration calls are unchanged.

## Rollback

Before writing, the patch backs up every existing target to:

```text
_patch_backups/CHECKOUT-001_guest-account-creation_20260704-<timestamp>/
```

Code rollback:

1. restore the five existing files from that backup;
2. remove `catalog/view/template/mail/account_created.twig`;
3. clear OpenCart template/cache files.

Already-created customer accounts are business data and are not removed by code
rollback. For a confirmed test account only, use the guarded SQL in the patch
header after verifying the exact customer ID and DB prefix.

## Run command (owner)

The checkbox microcopy is a factual draft and requires explicit owner approval
before this command is run.

```bash
cd ~/public_html || exit
php CHECKOUT-001_guest-account-creation_20260704.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

Expected terminal end:

```text
done=ok
```

## Post-deploy QA checklist

- [ ] Owner approves the visible consent microcopy before deploy.
- [ ] Test only through direct stock route
  `index.php?route=checkout/checkout` until ST-2c cutover.
- [ ] Checkbox is visible only to a guest and initially unchecked.
- [ ] Newsletter stays independent and unchecked.
- [ ] Unchecked flow completes as the current guest flow with `customer_id=0`.
- [ ] New email + checked opt-in creates exactly one customer and one address.
- [ ] New account is logged in for the current session and the order is linked.
- [ ] Dedicated set-password email arrives; link expires after 10 minutes and
  works once.
- [ ] Exactly one customer-facing email arrives for account creation: the
  dedicated set-password email; no standard registration welcome email.
- [ ] Configured admin new-account alert still arrives, if enabled.
- [ ] Existing email + checked opt-in creates no duplicate, exposes no account
  state, and completes as guest.
- [ ] Simulated account endpoint HTTP 500 / network failure silently clears the
  visible opt-in and still produces exactly one `confirm.confirm` request.
- [ ] Address autosave creates no account and does not reset Hutko/payment.
- [ ] Network shows one pre-confirm account call and exactly one actual
  `confirm.confirm` request per trusted place-order click.
- [ ] Double-click still produces one order.
- [ ] Complete the full 11-step `bs-checkout-smoke`.
- [ ] Owner manually verifies Hutko amount, Checkbox fiscalization, and CRM
  readback.
- [ ] Recheck this feature during ST-2c cutover.

## Side effects / risks

High risk: checkout UI and customer DB creation are connected in one user action.
Account creation is transaction-wrapped and fail-open. Server-side errors are
logged, the opt-in is cleared, and `confirm.confirm` continues as guest.
Transport errors are logged in the existing browser diagnostic and checkout
continues. Core order/confirm failures remain fail-closed.

An ambiguous transport failure can occur after the server already committed the
account but before the browser received the response. Checkout still continues,
but that order can be linked to the newly created account rather than remain a
guest order; the browser cannot safely distinguish response loss from a request
that never reached the server.

Email uniqueness is checked through the existing model, but the database still
has no unique email constraint. PHP session serialization and the existing
double-submit guard protect the normal single-session checkout path; cross-session
database-level race hardening remains outside this task.

Status stays `In progress` until full owner QA passes.
