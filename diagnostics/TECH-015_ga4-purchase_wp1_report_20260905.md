# Codex Report — TECH-015 WP1: GA4 purchase on order success

Date: 2026-09-06

Post-deploy QA updated: 2026-09-07

## Scope

Implemented WP1 only, as required by the handoff. The hosting runner changes the
Booster-owned checkout success controller and Twig template. It does not touch
the vendor analytics extension, payment code, order lifecycle/status code, the
database, Notion, or `ROADMAP_FLOW`.

Source was verified against repository backup
`backup-9.3.2026_21-30-35_boosters.tar.gz`:

- `catalog/controller/checkout/success.php` SHA-256 before patch:
  `de242821206a69f1f26c4e8f64848cf5bdceeaa21f4ce1d9a009048971306ee9`
- `catalog/view/template/checkout/success.twig` SHA-256 before patch:
  `7f8b516d65877f3ce6f8f986307cf112c34c3175733aab3a2cc1b72b1cb85392`

The runner refuses any other source hashes. This is deliberate because the
success page is a production-direct risky zone.

## Implementation

The controller builds the GA4 payload only after `canShowSuccessOrder()` has
authorized and loaded the order. It reuses the same loaded order products and
totals; it never reads the cleared cart and performs no additional query.

Payload rules:

- `transaction_id`: resolved OpenCart order id.
- `currency`: order currency code.
- `value`: order grand total minus tax and shipping, matching the vendor
  module's purchase rule. Coupon/reward discounts already present in the order
  total remain reflected in `value`.
- `tax`: sum of `tax` total rows.
- `shipping`: sum of `shipping` and `pinta_nova_poshta` total rows.
- every money value is converted with the order's `currency_value` and emitted
  as a JSON number.
- `coupon`: extracted from the order coupon total title when its canonical
  trailing `(CODE)` is present; otherwise the field is omitted.
- `items`: order `product_id`, decoded product name, unit price excluding tax,
  and quantity.

The server stores `bs_ga4_purchase_emitted_order_id` in the session only after
JSON encoding succeeds. The payload is therefore absent on F5 for the same
order. It is always absent on PAY-003 historical recovery and whenever no order
was display-authorized.

The handoff requested the script after `{{ text_message }}`. In the verified
live Twig, that token exists only inside the no-order fallback branch, where a
purchase payload can never exist. The snippet is therefore placed immediately
after both success/fallback branches and remains guarded by
`ga4_purchase_payload`. This is the smallest placement that can satisfy the
runtime acceptance criteria.

## Files touched

```text
patches/TECH-015_ga4-purchase_wp1_20260906.php
diagnostics/TECH-015_ga4-purchase_wp1_report_20260905.md
```

Runtime targets written by the patch:

```text
catalog/controller/checkout/success.php
catalog/view/template/checkout/success.twig
```

## Local fixture result

The uploaded-runner path was exercised against clean copies extracted from the
named backup.

```text
php_lint=ok file=.../generated/catalog/controller/checkout/success.php
php_lint=ok file=.../catalog/controller/checkout/success.php
changed_file=catalog/controller/checkout/success.php
changed_file=catalog/view/template/checkout/success.twig
database_touched=no
done=ok
self_delete=ok
```

After-patch fixture hashes:

```text
catalog/controller/checkout/success.php = 90bea312550bdd42b37129273e7a5aa8a3b80fc4ccc11a70e8ed6d053077737b
catalog/view/template/checkout/success.twig = 4b61912437fcb79520425d8520453deb1ff1663bd86443ef045aef7971833252
```

Negative drift check passed: changing one byte in the source Twig produced
`ERROR: Source SHA-256 mismatch` before backup or write.

## PHP checks

```text
No syntax errors detected in patches/TECH-015_ga4-purchase_wp1_20260906.php
No syntax errors detected in generated catalog/controller/checkout/success.php
PHP 8.0 host compatibility scan: OK
```

## Production execution evidence — owner, 2026-09-06

The owner ran the patch from `~/public_html`. The runner completed successfully
at `2026-09-06T14:53:23+00:00` and reported:

```text
backup=/home2/boosters/public_html/_patch_backups/TECH-015_ga4-purchase_wp1_20260906-20260906-145323-08ebbd
php_lint=ok file=.../generated/catalog/controller/checkout/success.php
php_lint=ok file=.../catalog/controller/checkout/success.php
after_sha256 catalog/controller/checkout/success.php = 90bea312550bdd42b37129273e7a5aa8a3b80fc4ccc11a70e8ed6d053077737b
after_sha256 catalog/view/template/checkout/success.twig = 4b61912437fcb79520425d8520453deb1ff1663bd86443ef045aef7971833252
database_touched=no
done=ok
self_delete=ok
cache cleared
```

This proves that the guarded file mutation, production PHP lint, self-delete,
and cache cleanup completed. Browser emission and session dedup were then
verified by the owner with logged-in order #365 and guest order #366; the exact
transport evidence is recorded in the post-deploy checklist below.

An independent HTTP smoke from the Codex Windows host was attempted after
deployment but produced local TLS credential error `SEC_E_NO_CREDENTIALS`
before any HTTP response. This is not evidence of a storefront failure; the
owner's successful production browser checks are the valid runtime evidence.

## Idempotency

Re-running an uploaded copy against the patched fixture returns:

```text
already_applied=yes
self_delete=ok
```

Runtime event deduplication is session-only and keyed by order id. If the browser
loses the session after the first render, the marker is also lost and a later
authorized bank-return render can emit again. This known limit is accepted by
the handoff; no database table was added.

## Rollback

The runner backs up both originals under:

```text
_patch_backups/TECH-015_ga4-purchase_wp1_20260906-<timestamp>-<random>/original/
```

Restore commands, substituting the actual directory printed by the runner:

```bash
cp _patch_backups/TECH-015_ga4-purchase_wp1_20260906-<timestamp>-<random>/original/catalog/controller/checkout/success.php catalog/controller/checkout/success.php
cp _patch_backups/TECH-015_ga4-purchase_wp1_20260906-<timestamp>-<random>/original/catalog/view/template/checkout/success.twig catalog/view/template/checkout/success.twig
```

Rollback immediately if the success page errors, the order does not render, or
one order emits more than once.

## Run command — executed by owner on 2026-09-06

```bash
cd ~/public_html || exit
php TECH-015_ga4-purchase_wp1_20260906.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA checklist

- [ ] Run full `bs-checkout-smoke`.
- [x] Logged-in COD order #365 completed; its first success render sent exactly
  one GA4 `collect` request with `en=purchase`.
- [x] Guest COD order #366 completed in a fresh browser session. Its first
  success render sent `en=purchase` with `transaction_id=366`, `currency=UAH`,
  `value=800`, `tax=0`, `shipping=0`, and one product at price 800, quantity 1.
- [x] Test order #365 was moved from `В обробці` to the owner's available
  cleanup status `Повернено`, with customer notification disabled. The module
  source confirms that an order-status change does not itself emit GA4
  `refund`; refund is a separate explicit module action. The configured lead
  status associations were already confirmed empty in the audit.
- [x] Guest test order #366 was also moved to `Повернено`, with customer
  notification disabled (owner screenshot, 2026-09-07).
- [x] Direct GA4 transport parameters verified from the owner's browser:
  `tid=G-283QW89TX8`, `transaction_id=365`, `currency=UAH`, `value=1000`,
  `tax=0`, `shipping=0`, and one product at price 1000, quantity 1. The visible
  Nova Poshta ₴75 tariff was informational and was not part of the order total.
- [x] After clearing Network and reloading the success page, the
  `en=purchase` filter remained empty; no second purchase request was emitted.
- [x] The visible DevTools issue was inspected. It is Chrome's generic CSP
  report for blocked string evaluation and has no source location. Neither the
  TECH-015 runner/generated files nor the extracted GA4 module contain `eval`,
  `new Function`, string `setTimeout`/`setInterval`, or `unsafe-eval`. The GA4
  purchase request completed despite the warning, so no TECH-015 runtime error
  is identified. The CSP must not be weakened with `unsafe-eval`.
- [ ] Open a PAY-003 `credit_order_id` recovery link; verify no purchase snippet.
  Owner opened `credit_order_id=341` on 2026-09-07: the request returned
  `Посилання недоступне.` and the `en=purchase` Network filter remained empty.
  This confirms the access-control rejection emits nothing, but does not yet
  exercise an authorized PAY-003 recovery render, so the acceptance item stays
  open.
- [x] Verify guest and logged-in success flows: guest order #366 and logged-in
  order #365 both emitted the expected purchase payload.
- [x] Guest order #366 was reloaded after clearing Network; the
  `en=purchase` filter remained empty, confirming session dedup on the guest
  path as well.
- [ ] Hutko return deliberately skipped by owner decision on 2026-09-07. Hutko
  is currently live/production, so creating a payment solely for this smoke
  test would move real money. Historical navigation cannot reproduce the
  short-lived signed `bs_hutko_return` cookie. This remains an explicitly
  accepted, unverified residual risk for WP1 review.
- [x] In a fresh incognito session, opening `checkout/success` without an order
  rendered the existing styled fallback (`Ваше замовлення прийняте!`) and the
  `en=purchase` Network filter remained empty (owner screenshot, 2026-09-07).
- [x] Clean up both test COD orders in admin: #365 and #366 are `Повернено`.

Do not use the same-day GA4 standard report as proof; use DebugView/Realtime.

## Claude review handoff

### Context and outcome

TECH-015 WP1 is deployed on production. The primary contract is proven in the
owner's browser: one logged-in COD order and one guest COD order each emitted
one correctly shaped GA4 `purchase`; clearing Network and pressing F5 emitted
no duplicate; an empty incognito success visit emitted nothing and retained the
existing fallback page. Both disposable COD orders were moved to `Повернено`
without notifying the customer.

### Files to review

```text
handoffs/handoff_TECH-015_ga4-purchase-begin-checkout_20260905.md
patches/TECH-015_ga4-purchase_wp1_20260906.php
diagnostics/TECH-015_ga4-purchase_wp1_report_20260905.md
```

The deployed runtime targets are limited to:

```text
catalog/controller/checkout/success.php
catalog/view/template/checkout/success.twig
```

### What to verify

1. Review the runner's hash guards, backup/restore, PHP lint gate,
   idempotency, self-delete, and PHP 8.0 compatibility.
2. Confirm that the payload is built only from the already authorized/loaded
   order and that `value = grand total - tax - shipping` matches the existing
   vendor-module convention.
3. Confirm the Twig placement deviation is necessary: `{{ text_message }}` is
   only in the no-order fallback, so the guarded snippet must sit after both
   branches.
4. Confirm the session marker cannot suppress another order id and that the
   explicit `$pay003_recovery` guard prevents historical recovery emission.
5. Recommend whether the evidence is sufficient for the owner to close WP1 and
   proceed to WP2, given the residual QA gaps below.

### Residual QA gaps and stop conditions

- An authorized PAY-003 recovery render was not reproduced. Opening order #341
  from the current browser correctly returned `Посилання недоступне.` and sent
  no purchase, but this only proves access-control rejection. The code contains
  an explicit `$pay003_recovery` no-emission guard; runtime proof still requires
  an authorized historical credit session/account.
- Hutko return was consciously skipped by the owner because Hutko is in
  production mode. Do not request or run a real payment merely to close this
  test. Accept the residual risk or schedule it for the next genuine Hutko
  return.
- The complete historical `bs-checkout-smoke` matrix was not run as a separate
  suite. The focused success-page checks above passed. Do not mark a full-suite
  pass from this report.
- If Claude considers either runtime gap mandatory, WP2 remains blocked. Do not
  infer WP1 closure from deployment alone.

### Acceptance evidence available

- Production runner: `done=ok`, both runtime hashes matched, PHP lint passed,
  database untouched, self-delete and cache clear completed.
- Logged-in #365: correct measurement id, transaction id, UAH currency, value,
  tax, shipping and item payload; F5 produced no duplicate.
- Guest #366: correct transaction id, UAH currency, value, tax, shipping and
  item payload; F5 produced no duplicate.
- Empty incognito success visit: existing fallback rendered; no purchase.
- No new TECH-015 console/runtime error identified. The generic Chrome CSP
  warning is unrelated and must not be "fixed" by enabling `unsafe-eval`.

## WP2 follow-up — prepared separately

After WP1 production QA, the owner approved Option A and the visible heading
`Оформлення замовлення`. WP2 is prepared as a separate, not-yet-deployed runner:

```text
patches/TECH-015_ga4-begin-checkout_wp2_20260907.php
diagnostics/TECH-015_ga4-begin-checkout_wp2_report_20260907.md
```

## Side effects / risks

- The session marker is written during server rendering, before the browser
  confirms that gtag accepted the event. A client-side block or closed tab can
  therefore lose that event rather than retry it on F5.
- A vendor/module change that removes `ps_dataLayer` would produce a browser
  error; current backup and production audit confirm the transport exists.
- The owner performed the production deployment. Codex did not access hosting.
  No commit, push, Notion write, or roadmap status change was performed.
