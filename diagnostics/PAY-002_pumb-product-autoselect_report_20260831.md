# PAY-002 PUMB product-modal autoselect hotfix — local report

Date: 2026-08-31
Scope: checkout payment selection only
Database: not touched
Deployment: owner-reported success at 2026-08-31T07:53:04+00:00; no agent deployment

Owner QA correction: this hotfix opened the installment drawer but still selected
Mono for a PUMB modal request. Syntax/hash assertions were insufficient to prove
the selected bank. The metadata remains required; the missing provider-code
selection is addressed by `PAY-002_pumb-provider-selection_20260831.php` and
`PAY-002_pumb-provider-selection_report_20260831.md`.

## Evidence and root cause

Fresh source: `C:\Users\14bez\Downloads\booster-debug-pay002-autoselect-20260831.tar.gz`

- Archive SHA-256: `A585D4670C8F34E468D6E30425348B41BF553CDDD3C1DC9DE2DDE5E72530E2CE`
- Included files: `catalog/controller/checkout/checkout.php`, `catalog/controller/checkout/payment_method.php`, and `catalog/view/template/checkout/payment_method.twig`.
- The checkout controller retained `pay002_pumb_credit_term`, but no PUMB modal-origin session flag.
- The payment-method controller returned the selected PUMB term but no `pay002_from_modal` metadata.
- The checkout Twig carried the modal-origin flag only through the Mono branch. Its automatic save logic only selects an installment option with `fromModal` set.

## Patch

`patches/PAY-002_pumb-product-autoselect_20260831.php` makes three file-only changes:

1. Stores `pay002_pumb_credit_from_modal=1` for a valid PUMB product-modal hand-off and clears it for Mono, direct, malformed, or ambiguous entry.
2. Exposes that session value as `pay002_from_modal` in the PUMB payment-method group.
3. Preserves the flag while the unified credit option is assembled. The intended PUMB auto-selection did not pass owner QA: the unified option still retained Mono's code. See the correction above.

The runner has exact pre-image and post-image SHA-256 guards, one-count anchors, backup before write, PHP lint with restore-on-failure, a marker for idempotence, no database operations, and self-deletion after a successful run.

## Local validation

- `php -l patches/PAY-002_pumb-product-autoselect_20260831.php` passed.
- Clean fixture run against the three fresh archive files passed both generated PHP lint checks and all generated-content assertions.
- Post-image SHA-256 values matched:
  - `checkout.php`: `96ef34ad3c0c2b3c980d6ffe54953da3f66e2cd1320f9f549305f5c3f98289c9`
- `payment_method.php`: `0497216e9267f92c385ea43b43e09fc0a9531ff5df71a6e7d954d6f816c9b79c`
- `payment_method.twig`: `fb57eef71fe3141d4b7d2713ecc2c465ab371476786f491399a4d195764d0f3b`
- The JavaScript extracted from the patched Twig block passed `node --check`.
- After re-uploading the local runner to the already-patched fixture, its repeat run returned `already_applied=yes`.
- The first production attempt restored all three files after its Twig post-image SHA check failed. The runner was corrected to preserve the source Twig line endings instead of using the runner platform's `PHP_EOL`; the corrected clean fixture run above now produces the Linux-compatible Twig hash.

## Production gate and owner QA

The patch is deliberately hash-gated to this archive. If production differs, it exits before a write; obtain a fresh three-file archive rather than changing the expected hashes.

After the owner runs it from `~/public_html` and clears OpenCart cache:

1. On a product page choose PUMB 4 or 5 payments, select **Додати й оформити**, and complete the normal delivery prerequisites.
2. Confirm checkout auto-selects **Сплатити частинами**, opens PUMB, and highlights the exact selected PUMB term.
3. Repeat once for Mono to confirm no regression.
4. Enter checkout directly and confirm no installment method is auto-selected.
5. Complete the normal checkout smoke without creating a real bank order unless separately authorized.
