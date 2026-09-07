# Codex Report — TECH-015 WP3a: GA4 shipping and payment steps

Date: 2026-09-07

## Outcome

WP3a is ready for owner deployment. The fresh 2026-09-07 source confirms that
`checkout/shipping_method.twig` still has the vendor success anchor, so shipping
requires no code patch. The payment runner adds one guarded consumer of the
vendor-prepared `ps_add_payment_info` response payload to the custom payment
success handler.

Do not enable either admin toggle before the payment runner succeeds. After it
succeeds, enable both `Track Add Shipping Info` and `Track Add Payment Info` in
the module, then run production QA.

No vendor file, payment method, order state, database, CSS, Notion record or
roadmap status is changed.

Owner-approved deployment order for WP3 is: **WP3a → WP3c → WP3b**. WP3b stays
last and isolated because it is the only package that touches the catalogue buy
button.

## Files delivered

```text
patches/TECH-015_ga4-add-payment-info_wp3a_20260907.php
diagnostics/TECH-015_ga4-add-payment-info_wp3a_report_20260907.md
```

Live target of the runner:
`catalog/view/template/checkout/payment_method.twig`.

## Verified source contract

- Fresh source SHA-256 gate:
  `efee1a60c2cecc7547787646690cc00fc01a428f9a2e9a64d9f08d464db6d396`.
- Generated source SHA-256:
  `974d73f62b98b4a562db1478abcea040cd47b7270675e368173a83b3e43bf7c6`.
- Exact vendor response key verified in the installed controller:
  `ps_add_payment_info`.
- The emitter runs after the `json.error` return and before the template records
  the local payment choice.
- The module toggle guard and response-key guard make a missing/disabled payload
  a no-op.
- Shipping anchor count is one; no shipping patch is justified.

## Local verification

Clean fixture application:

```text
changed_file=catalog/view/template/checkout/payment_method.twig
after_sha256=974d73f62b98b4a562db1478abcea040cd47b7270675e368173a83b3e43bf7c6
database_touched=no
done=ok
self_delete=ok
```

Additional gates:

```text
php_lint=ok
twig_embedded_js_parse=ok
wp3a_error_guard_position=ok
repeat_run=already_applied=yes
drift_run_exit=1
drift_error=Source SHA-256 mismatch: catalog/view/template/checkout/payment_method.twig
drift_backup_dirs=0
```

The drift fixture proves that a source mismatch stops before backup or write.

## Production execution evidence

Deployed by the owner on production at `2026-09-07T18:32:45+00:00`:

```text
cwd=/home2/boosters/public_html
time=2026-09-07T18:32:45+00:00
backup=/home2/boosters/public_html/_patch_backups/TECH-015_ga4-add-payment-info_wp3a_20260907-20260907-183245-27322e
backup_file=/home2/boosters/public_html/_patch_backups/TECH-015_ga4-add-payment-info_wp3a_20260907-20260907-183245-27322e/original/catalog/view/template/checkout/payment_method.twig
php_lint=not_applicable target=catalog/view/template/checkout/payment_method.twig
changed_file=catalog/view/template/checkout/payment_method.twig
after_sha256=974d73f62b98b4a562db1478abcea040cd47b7270675e368173a83b3e43bf7c6
database_touched=no
done=ok
self_delete=ok
cache cleared
```

Runner execution and cache cleanup succeeded. Runtime GA4 and checkout QA remain
owner-gated below.

## Rollback

The runner backs up original and generated files under:

```text
_patch_backups/TECH-015_ga4-add-payment-info_wp3a_20260907-<timestamp>-<suffix>/
```

Restore the original target, then clear OpenCart cache:

```bash
cp _patch_backups/TECH-015_ga4-add-payment-info_wp3a_20260907-*/original/catalog/view/template/checkout/payment_method.twig catalog/view/template/checkout/payment_method.twig
php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

Use the exact directory printed by the production runner, not an unresolved
wildcard, when rolling back.

## Owner run command

Upload the runner to `~/public_html`, then:

```bash
cd ~/public_html || exit
php TECH-015_ga4-add-payment-info_wp3a_20260907.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA

- [x] Runner output ends with `done=ok` and `self_delete=ok`; recorded above.
- [ ] Enable both module toggles only after the runner succeeds.
- [ ] Save/submit shipping once: one GA4 batch contains
  `en=add_shipping_info`, correct UAH value and cart items.
- [x] Save/submit payment once: one GA4 batch contains
  `en=add_payment_info`, correct UAH value and cart items.
- [ ] Inspect the GA4 request payload itself; do not rely on a narrow Network
  text filter because GA4 may batch several events into one request.
- [ ] No duplicated shipping/payment event and no new console error.
- [ ] Full `bs-checkout-smoke` passes.
- [ ] All Tier 1 URLs from `AGENTS.md` pass.

Rollback immediately for any checkout regression, missing payment selection,
duplicate event or new console error.

After all WP3a checks pass, the next package is WP3c, not WP3b.

## Production runtime evidence — payment

Owner DevTools evidence confirms `add_payment_info` on 2026-09-07:

```text
tid=G-283QW89TX8
en=add_payment_info
cu=UAH
epn.value=1000
ep.payment_type=extension_heading_title (pinta_nova_poshta_cod)
pr1=id110 ... pr1000 ... qt1
```

The product metadata includes the vendor-built affiliation, brand, category and
checkout-list fields, confirming that the installed WP3a consumer emitted the
vendor payload. Shipping runtime evidence is still pending; the screenshot used
a narrow `add_shipping_info` Network list filter and showed no matching row,
which is not sufficient because GA4 can batch the event inside a `collect`
request.
