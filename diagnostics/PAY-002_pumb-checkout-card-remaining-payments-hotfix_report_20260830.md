# Codex Report — PAY-002 WP4: PUMB remaining-payments display

Date: 2026-08-30

## Outcome

Prepared a file-only hotfix that shows the provider-specific number of payments remaining in the merged installment drawer:

- PUMB selected term 3/4/5 -> 3/4/5 payments remaining;
- Mono selected term 3/4/5 -> existing 2/3/4 payments remaining.

The change affects both the initial drawer render and subsequent term-button clicks. It does not alter the selected PUMB term sent to the backend or bank.

No upload, deployment, database write, commit, or push was performed by Codex.

## Production observation

The owner successfully deployed PAY-002 WP3 and reported:

```text
sha_gate=ok before=0d18197b after=7fde759a
twig_assert=ok
database_touched=no
done=ok
self_delete=ok
cache cleared
```

Browser QA confirmed the duplicate PUMB row and stale `СКОРО БУДЕ` card were removed. It also exposed a separate display defect: selected PUMB term 5 showed `Платежів до завершення: 4`.

## Product semantics

Official PUMB customer information states that the customer pays nothing on the purchase date and makes the first payment one month later. The PUMB instruction also presents the selected term as the number of equal monthly payments.

Sources:

- https://www.pumb.ua/credit
- https://apim.pumb.ua/api/assets/pumb/9ed630ce-708b-4aa7-9a31-97eecac7da43/instrukciya-obminu-informaciyeyu-u-osobistomu-kabineti-servisu-sch.pdf

Therefore, at checkout all selected PUMB payments remain outstanding. The existing Mono behavior is intentionally preserved.

## Root cause

WP3 introduced a shared `providerCard()` renderer but retained the Mono-specific formula for both providers:

```javascript
Math.max(count - 1, 0)
```

The click handler repeated the same shared formula. This affected display only; `data-pay001-code` and the PUMB term passed to `savePayment()` remained correct.

## Scope

The runner changes only:

```text
catalog/view/template/checkout/payment_method.twig
```

It adds provider-specific remaining-payment calculations in two places:

1. initial provider-card render;
2. installment-term click handler.

It does not change controllers, API payloads, `confirm()`, payment gates, credentials, database rows, CSS, `.htaccess`, `checkout.twig`, or Mono behavior.

Files for review:

```text
patches/PAY-002_pumb-checkout-card-remaining-payments-hotfix_20260830.php
diagnostics/PAY-002_pumb-checkout-card-remaining-payments-hotfix_report_20260830.md
```

## Deterministic SHA gate

The current production Twig is the successful WP3 output reported by the owner. Its exact source was reconstructed through WP1 -> WP2 -> WP3 and matched the production-reported hash:

```text
BEFORE_SHA256=7fde759aa8ca95e627f9b3c579d0f1ca178a8df85b862fe3c7469a35a9b2006e
AFTER_SHA256=ea4b41df3577f656ecc57dc02661246ba65e3f6d3deb63dc593517b05850b56b
```

The runner refuses any different live source before backup/write and restores the original if the generated hash differs.

## Positive fixture result

The final runner was executed against the exact WP3 output:

```text
sha_gate=ok before=7fde759a after=ea4b41df
twig_assert=ok
changed=catalog/view/template/checkout/payment_method.twig
database_touched=no
done=ok
self_delete=ok
```

The runner-generated Twig and independently built candidate were byte-identical:

```text
generated=EA4B41DF3577F656ECC57DC02661246BA65E3F6D3DEB63DC593517B05850B56B
candidate=EA4B41DF3577F656ECC57DC02661246BA65E3F6D3DEB63DC593517B05850B56B
byte_identical=True
```

## Generated behavior QA

```text
pumb_initial_remaining_3=ok
mono_initial_remaining_2=ok
pumb_initial_remaining_4=ok
mono_initial_remaining_3=ok
pumb_initial_remaining_5=ok
mono_initial_remaining_4=ok
pumb_click_remaining_3=ok
mono_click_remaining_2=ok
pumb_click_remaining_4=ok
mono_click_remaining_3=ok
pumb_click_remaining_5=ok
mono_click_remaining_4=ok
single_embedded_script=ok
embedded_twig_js_syntax=ok
node_check_exit=0
```

## PHP syntax

```text
No syntax errors detected in patches\PAY-002_pumb-checkout-card-remaining-payments-hotfix_20260830.php
```

The runner is compatible with production PHP 8.0 and has no Node dependency on production.

## Negative test — pre-write mismatch

```text
ERROR: live source SHA256 mismatch expected=7fde759aa8ca95e627f9b3c579d0f1ca178a8df85b862fe3c7469a35a9b2006e actual=eb08ce61faf86f51df6566cbe792d0aa3df010a699fc1b9dd9e812acd574de45; write skipped
runner_exit=1
write_skipped=True
backup_root_exists=False
marker_exists=False
runner_retained=True
```

## Negative test — post-write mismatch

```text
ERROR: source restored: ERROR: generated SHA256 mismatch expected=0000000000000000000000000000000000000000000000000000000000000000 actual=ea4b41df3577f656ecc57dc02661246ba65e3f6d3deb63dc593517b05850b56b
runner_exit=1
pre=7FDE759AA8CA95E627F9B3C579D0F1CA178A8DF85B862FE3C7469A35A9B2006E
post=7FDE759AA8CA95E627F9B3C579D0F1CA178A8DF85B862FE3C7469A35A9B2006E
restored=True
marker_exists=False
runner_retained=True
```

## Idempotency

Re-uploading after success returns:

```text
already_applied=yes
```

## Rollback

The runner prints a backup path under:

```text
_patch_backups/PAY-002_pumb-checkout-card-remaining-payments-hotfix_20260830-<timestamp>/
```

Restore only its `catalog/view/template/checkout/payment_method.twig` and clear caches. Do not restore a WP1, WP2, or WP3 backup for this narrow rollback.

## Owner run command after review approval

```bash
cd ~/public_html || exit
php PAY-002_pumb-checkout-card-remaining-payments-hotfix_20260830.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

Expected gates:

```text
sha_gate=ok before=7fde759a after=ea4b41df
twig_assert=ok
database_touched=no
done=ok
self_delete=ok
cache cleared
```

## Post-deploy owner QA

1. Open the tokenized checkout and select PUMB 3, 4, and 5. `Платежів до завершення` must show 3, 4, and 5 respectively.
2. Select Mono 3, 4, and 5. The value must remain 2, 3, and 4 respectively.
3. Switch repeatedly between banks and terms; only the clicked provider card should update.
4. Confirm there remains exactly one outer `Сплатити частинами` row, two provider cards, and no stale `СКОРО БУДЕ` label while PUMB is available.
5. Confirm the selected PUMB payment code/term still persists through checkout. Do not submit a real bank application unless separately authorized.
6. Check desktop, tablet-width, and mobile-width layouts and the OpenCart error log.

## Risk

Risk is limited to two displayed remaining-payment values in checkout Twig. The exact-source SHA gate intentionally refuses to run if production changed after WP3. Bank/API behavior remains unchanged and owner-gated.

## Claude review request

Verify that:

1. PUMB uses `count`, while Mono retains `Math.max(count - 1, 0)` in both initial render and click updates.
2. The three exact anchors occur once against the WP3 source.
3. BEFORE/AFTER hashes match the documented fixture evidence.
4. Restore-on-failure, marker, idempotency, self-delete, and PHP 8.0 compatibility remain intact.
5. No controller, API, DB, CSS, gate, or Mono behavior is changed.
