# Codex Report — CHECKOUT-001 Phase 1.2: checkout UX loader

Date: 2026-07-05

## Scope

Implemented:

`handoffs/handoff_CHECKOUT-001_phase1-2_checkout-ux-loader_2026-07-05.md`.

The patch:

- skips `checkout/payment_method.createAccount` when the account opt-in is
  unchecked or absent;
- preserves the checked opt-in pre-step and its existing fail-open behavior;
- shows `Перевіряємо дані...` only while the checked pre-step actually runs;
- shows `Створюємо замовлення...` while loading `confirm.confirm`;
- updates the real payment button to `Завершуємо замовлення...` before triggering
  the payment extension handler;
- preserves the trusted-click guard, one `confirm.confirm`, the payment
  extension's own disabled/loading behavior, and the single-order sequence.

Not touched: server controllers, account timing, database/session logic,
`confirm.php`, payment extensions, Hutko, fiscalization, CRM, SimpleCheckout,
`url.php`, register flow, oferta logic, dashboard, or roadmap status.

## Files touched

```text
patches/CHECKOUT-001_phase1-2_checkout-ux-loader_20260705.php
```

Runtime target:

```text
catalog/view/template/checkout/checkout.twig
```

## Source preflight

The current checkout source was reproduced from the owner-confirmed deployed
Phase 1, Phase 1.1 and agree-session hotfix chain:

```text
checkout.twig SHA256
5c8b49203c12588f5e0f3de715791ffe94d45e65934d9b2b4dde68d64dc951bc
```

No separate post-deploy bundle was supplied. The runner therefore uses this exact
SHA gate and fails before writing if the live file has drifted.

The successful live order also confirms that the current payment partial exposes
the expected `#button-confirm` and redirects correctly after its AJAX response.

## Dry-run result

```text
already_applied=no
changed_files=1
unchecked_or_missing_optin=skip_createAccount
checked_optin=preserve_createAccount_then_confirm
status_sequence_checked=Перевіряємо дані -> Створюємо замовлення -> Завершуємо замовлення
status_sequence_unchecked=Створюємо замовлення -> Завершуємо замовлення
done=ok
```

Focused runtime:

```text
loader_runtime_smoke=ok
unchecked_createAccount_requests=0
missing_optin_createAccount_requests=0
checked_createAccount_requests=1
confirm_loads_per_flow=1
real_payment_clicks_per_flow=1
checkout_js_syntax=ok
trusted_gate=ok
autosave_selector=ok
```

The runtime mock also covered a checked pre-step network failure and confirmed
that fail-open still continues through one confirm load and one payment click.

## php -l result

```text
No syntax errors detected in CHECKOUT-001_phase1-2_checkout-ux-loader_20260705.php
```

Local PHP: 8.3.30.

## Idempotency

```text
already_applied=yes
changed_files=0
done=ok
```

## Database behavior

No schema or row changes. Account and order creation timing is unchanged for
checked opt-in. Unchecked/missing opt-in now avoids a no-op HTTP request only.

## Rollback

The patch backs up `checkout.twig` to:

```text
_patch_backups/CHECKOUT-001_phase1-2_checkout-ux-loader_20260705-<timestamp>-<suffix>/
```

Restore that file and clear OpenCart cache/template files.

## Run command

```bash
cd ~/public_html || exit
php CHECKOUT-001_phase1-2_checkout-ux-loader_20260705.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

Expected terminal end:

```text
done=ok
```

## Post-deploy QA checklist

- [ ] Unchecked opt-in: Network contains no `payment_method.createAccount`.
- [ ] Unchecked opt-in: status moves from `Створюємо замовлення...` to
  `Завершуємо замовлення...`, then redirects.
- [ ] Checked opt-in: Network contains one `payment_method.createAccount`.
- [ ] Checked opt-in: status moves through all three stages.
- [ ] Checked pre-step failure still continues fail-open.
- [ ] Both flows contain exactly one `confirm.confirm`.
- [ ] Both flows create exactly one order on a double-click attempt.
- [ ] Guest order/account/email behavior remains unchanged.

## Risks

Low risk: one client-side Twig function only. Main deployment risk is live source
drift; the SHA preflight converts that into a loud no-write failure.

This follow-up does not change CHECKOUT-001 roadmap status.
