# Codex Report — PAY-002 WP3: PUMB checkout card UI hotfix

Date: 2026-08-30

## Outcome

Prepared a file-only production runner that merges Mono and PUMB into one `Сплатити частинами` checkout row, removes the stale PUMB `СКОРО БУДЕ` card when real PUMB terms are available, and prevents a separate blocked Mono fallback row beside an active PUMB row.

The production host has no Node.js. The rejected production parse dependency was replaced with a deterministic SHA-256 gate derived from the current post-WP1/WP2 production Twig supplied by the owner.

No upload, deployment, database write, commit, or push was performed by Codex.

## Case selection and production evidence

Case B applies. The owner's production probes returned:

```text
NO NODE FOUND
```

No Node binary was found in the runner candidates, cPanel/CloudLinux paths, `PATH`, `/opt`, or `/usr/local`.

The owner then supplied:

```text
booster-debug-files.tar.gz
└── catalog/view/template/checkout/payment_method.twig
```

This is the current production file after the successful WP1 -> WP2 deployment, not the pre-deploy 2026-08-28 backup.

## Deterministic SHA gate

Hashes calculated from the supplied production file:

```text
BEFORE_SHA256=0d18197b0d58780725cd979ed3d465c198f054cddcb6cc1ee629dffde214e9b2
AFTER_SHA256=7fde759aa8ca95e627f9b3c579d0f1ca178a8df85b862fe3c7469a35a9b2006e
```

Runner behavior:

1. Before any backup or write, it hashes the live Twig and requires an exact `BEFORE_SHA256` match. A mismatch prints the expected and actual full hashes and exits 1 without writing.
2. After writing the transformed Twig, it re-reads and hashes the file and requires an exact `AFTER_SHA256` match. A mismatch restores the backup and exits 1.
3. Only after the SHA gate and structural assertions succeed does it write the marker, print `done=ok`, and self-delete.

Successful gate output:

```text
sha_gate=ok before=0d18197b after=7fde759a
twig_assert=ok
```

The runner contains no `exec()` call, so the former exec-status sentinel concern is no longer applicable.

## Local JavaScript parse and round-2 identity

The generated `<script>` block was extracted from the exact `AFTER_SHA256` Twig and checked locally:

```text
script_extract=ok count=1
node --check reviewed-round2-output.js
node_check_exit=0
```

The SHA-gated runner was then run independently against a fresh extraction of the owner-supplied live file. Its generated Twig was compared with the saved output from the runner revision reviewed by Claude in round 2:

```text
generated_sha256=7FDE759AA8CA95E627F9B3C579D0F1CA178A8DF85B862FE3C7469A35A9B2006E
reviewed_round2_sha256=7FDE759AA8CA95E627F9B3C579D0F1CA178A8DF85B862FE3C7469A35A9B2006E
byte_identical_to_round2=True
```

Therefore the four reviewed content transformations and the blocked-row guard produce byte-identical Twig; only the production safety gate changed.

## Scope

The runner changes only:

```text
catalog/view/template/checkout/payment_method.twig
```

It does not change controllers, payment credentials, preview/public gates, `confirm()`, `mono_chast`, CSS, `.htaccess`, `checkout.twig`, or the database.

Files for review:

```text
patches/PAY-002_pumb-checkout-card-ui-hotfix_20260830.php
diagnostics/PAY-002_pumb-checkout-card-ui-hotfix_report_20260830.md
```

## Generated behavior QA

```text
mono_only_single_credit_row=ok
mono_only_has_pumb_soon=ok
both_providers_single_credit_row=ok
both_provider_cards_no_stale_soon=ok
pumb_only_single_credit_row=ok
pumb_only_clean_drawer=ok
pumb_term_selection_preserved=ok
provider_order_keeps_mono_default=ok
reverse_order_pumb_selection_preserved=ok
active_pumb_suppresses_blocked_mono_row=ok
blocked_mono_row_retained_without_active_credit=ok
no_gate_reason_no_blocked_row=ok
provider_local_card_lookup=ok
provider_local_total_lookup=ok
provider_local_monthly_update=ok
provider_local_remaining_update=ok
single_embedded_script=ok
embedded_twig_js_syntax=ok
```

## Positive fixture result

The final SHA-gated runner was executed against a fresh copy of the owner-supplied live Twig:

```text
sha_gate=ok before=0d18197b after=7fde759a
twig_assert=ok
changed=catalog/view/template/checkout/payment_method.twig
database_touched=no
done=ok
self_delete=ok
runner_exit=0
```

PHP syntax:

```text
No syntax errors detected in patches\PAY-002_pumb-checkout-card-ui-hotfix_20260830.php
```

The runner remains compatible with production PHP 8.0 and contains no PHP 8.1+ constructs.

## Negative test — pre-write mismatch

A comment was added to a copy of the supplied live Twig before running the patch:

```text
ERROR: live source SHA256 mismatch expected=0d18197b0d58780725cd979ed3d465c198f054cddcb6cc1ee629dffde214e9b2 actual=2961cff7e91e52e1dc4b4ca74e93e16217f20ea9a8cf152495bf74b6060ae8e6; write skipped
runner_exit=1
write_skipped=True
backup_root_exists=False
marker_exists=False
runner_retained=True
```

The file hash was unchanged before and after the attempt.

## Negative test — post-write mismatch

`AFTER_SHA256` was deliberately replaced with zeros in an isolated runner copy:

```text
ERROR: source restored: ERROR: generated SHA256 mismatch expected=0000000000000000000000000000000000000000000000000000000000000000 actual=7fde759aa8ca95e627f9b3c579d0f1ca178a8df85b862fe3c7469a35a9b2006e
runner_exit=1
pre=0D18197B0D58780725CD979ED3D465C198F054CDDCB6CC1EE629DFFDE214E9B2
post=0D18197B0D58780725CD979ED3D465C198F054CDDCB6CC1EE629DFFDE214E9B2
restored=True
marker_exists=False
runner_retained=True
```

## Idempotency

Re-uploading the unchanged runner after a successful fixture run returns:

```text
already_applied=yes
```

## Rollback

The runner backs up the target to:

```text
_patch_backups/PAY-002_pumb-checkout-card-ui-hotfix_20260830-<timestamp>/catalog/view/template/checkout/payment_method.twig
```

Restore only that WP3 backup and clear caches. Do not restore a WP1 or WP2 backup, because that would remove previously deployed PAY-002 changes.

## Owner run command after review approval

Keep PUMB disabled until the runner succeeds and caches are cleared. Upload only the WP3 runner to `~/public_html`, then run:

```bash
cd ~/public_html || exit
php PAY-002_pumb-checkout-card-ui-hotfix_20260830.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

Expected output gates:

```text
sha_gate=ok before=0d18197b after=7fde759a
twig_assert=ok
database_touched=no
done=ok
self_delete=ok
cache cleared
```

Any `live source SHA256 mismatch` is a hard stop. Do not bypass it or upload a different source file; return the full error for re-verification.

## Post-deploy owner QA

1. Leave PUMB disabled and verify ordinary checkout still shows one Mono installment row with the unchanged PUMB `СКОРО БУДЕ` card.
2. Enable PUMB only for the bounded preview test and open checkout through a fresh valid preview-token URL.
3. Confirm there is exactly one outer `Сплатити частинами` radio row.
4. Confirm the drawer contains Mono and PUMB cards and no `СКОРО БУДЕ` label.
5. Select 3, 4, and 5 payments in each provider; only the clicked provider's monthly/remaining values should change.
6. Complete a bounded PUMB-term checkout test and confirm the saved payment code belongs to PUMB. Do not submit a real bank application unless separately authorized.
7. Run non-credit payment smoke for Hutko, COD, and IBAN because `flattenPaymentMethods()` handles every payment row.
8. Verify desktop, tablet-width, and mobile-width layouts.
9. Reload a clean URL without the preview token and confirm PUMB is unavailable unless the public switch is intentionally enabled.
10. Check the OpenCart error log for new PHP/Twig errors.
11. If the old drawer survives `Ctrl+F5`, clear template/modification caches from the OpenCart admin before treating the hotfix as failed.

Keep PUMB disabled if any acceptance item fails.

## Side effects and risk

Risk is bounded to checkout payment-method rendering. Existing provider classes and markup structure are retained; no CSS override was added. The production runner has no Node or external-parser dependency. Its deliberate tradeoff is exact-source coupling: any legitimate live Twig change after the supplied archive causes a safe pre-write refusal and requires new hashes and another review. Live browser behavior and bank integration remain owner-gated.

## Claude repeat-review request

Verify these points:

1. The embedded `BEFORE_SHA256` equals the current owner-supplied production Twig.
2. The embedded `AFTER_SHA256` equals the locally parsed generated Twig and the saved round-2 generated output.
3. Pre-write mismatch occurs before backup/write and prints expected plus actual hashes.
4. Post-write mismatch reaches the restore catch and cannot create the marker.
5. The four content transformations and blocked-row guard are unchanged and generate byte-identical round-2 output.
6. No `exec()`, Node requirement, DB write, controller change, or PHP 8.1+ syntax remains.
