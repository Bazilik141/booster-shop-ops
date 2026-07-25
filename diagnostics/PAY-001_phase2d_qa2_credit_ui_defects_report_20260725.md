# Codex Report — PAY-001: Phase 2d QA2 credit UI defects

Date: 2026-07-25

## Scope

Implemented the two defects authorized by:

```text
handoffs/handoff_PAY-001_phase2d-qa2-defects_20260725.md
```

Changes are confined to the stock checkout Mono UI:

1. show the current receiver phone in the Mono drawer and update it while the field is edited;
2. preserve the 3/4/5 term selected in the product modal when checkout flattens the Mono options.

Nova Poshta/R-13.5, DB/settings, `checkout.php`, `payment_method.php`, `confirm.php`, Mono API/lifecycle, SimpleCheckout, Hutko/COD/IBAN, NCRM, and order-write logic are outside this patch and unchanged.

## Files touched

The host runner changes:

```text
catalog/view/template/checkout/payment_method.twig
```

Deliverables:

```text
patches/PAY-001_phase2d_qa2_credit_ui_defects_20260725.php
diagnostics/PAY-001_phase2d_qa2_credit_ui_defects_report_20260725.md
```

## Implementation

Phone display:

- reads `#bs-co-recv-telephone` first;
- falls back to `#input-telephone`, then the checkout root's `data-bs-receiver-telephone`;
- keeps the visible value unchanged and does not normalize/mutate it;
- synchronizes every `[data-pay001-phone]` on receiver-phone `input` and `change`.

Credit term:

- keeps Mono options numerically sorted 3/4/5;
- resolves the initial option from the server-provided `group.pay001_preferred`;
- uses that option's actual code instead of always using `monoOptions[0]`;
- leaves manual checkout term switching and server write-boundary validation unchanged.

## Source gate

Fresh live input:

```text
catalog/view/template/checkout/payment_method.twig
old SHA256: 8D3010310F3B2EFEF48F58A7F7716ACD75FFB4B629C762FFB2ED67A1813279F9
new SHA256: D62FD2269D892F7C735CD815F21AB9CF949864D389775C0DF42CB44509D753E5
```

The patch refuses to write if the live source hash or any of five exact anchors differs.

## Dry-run result

Executed against a clean copy from `booster-debug-PAY001-phase2d-qa2.tar.gz`:

```text
cwd=...\pay001_phase2d_qa2_patch_20260725\dryrun
backup=...\_patch_backups\PAY-001_phase2d_qa2_credit_ui_defects_20260725-20260725-104955
changed_file=catalog/view/template/checkout/payment_method.twig
php_l=ok file=PAY-001_phase2d_qa2_credit_ui_defects_20260725.php
done=ok
```

Post-run target hash:

```text
D62FD2269D892F7C735CD815F21AB9CF949864D389775C0DF42CB44509D753E5
```

## Syntax and focused behavior checks

```text
php -l patches/PAY-001_phase2d_qa2_credit_ui_defects_20260725.php: OK
embedded_js_parse=ok
preferred_terms=3,4,5
phone_selector_order=receiver,standard,root-data
live_phone_sync=ok
```

These checks prove the generated source and focused selection logic. Browser/session behavior still requires owner QA.

## Idempotency

Re-uploading and rerunning the patch against the patched target returns:

```text
already_applied=yes
```

The uploaded runner self-deletes after either `done=ok` or `already_applied=yes`.

## Rollback

Automatic backup:

```text
_patch_backups/PAY-001_phase2d_qa2_credit_ui_defects_20260725-<timestamp>/
```

Restore:

```bash
cp "_patch_backups/PAY-001_phase2d_qa2_credit_ui_defects_20260725-<timestamp>/catalog/view/template/checkout/payment_method.twig" \
   "catalog/view/template/checkout/payment_method.twig"
```

Then clear OpenCart cache/template files with the same safe cache command used after deployment.

No SQL rollback is required.

## Run command (owner)

```bash
cd ~/public_html || exit
php PAY-001_phase2d_qa2_credit_ui_defects_20260725.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

Expected terminal tail:

```text
changed_file=catalog/view/template/checkout/payment_method.twig
php_l=ok file=PAY-001_phase2d_qa2_credit_ui_defects_20260725.php
done=ok
cache cleared
```

## Post-deploy QA checklist

- [ ] Receiver phone shown in the Mono drawer exactly matches the visible receiver phone field.
- [ ] Editing that field updates the open Mono drawer without F5.
- [ ] Product page 3 payments → checkout shows and saves 3.
- [ ] Product page 4 payments → checkout shows and saves 4.
- [ ] Product page 5 payments → checkout shows and saves 5.
- [ ] Manual switching between 3/4/5 inside checkout still saves the clicked option.
- [ ] Direct checkout without a modal choice retains the existing default behavior.
- [ ] Credit threshold/preorder gates still work.
- [ ] Coupon apply/remove still refreshes totals and the credit gate.
- [ ] Hutko, COD, IBAN, SimpleCheckout isolation, and explicit-click order creation are unchanged.
- [ ] Complete the full 15-step plan in `diagnostics/PAY-001_phase2c_checkout_smoke_plan_20260725.md`.

Keep `payment_mono_chast_status=0` outside the controlled QA window.

## Side effects / risks

- One Twig file only; no server-side payment or order data changes.
- Checkout/payment remains a high-risk browser flow, so local parsing does not replace live session QA.
- The patch requires OpenCart template/cache clearing.
- PAY-001 remains In progress until the full combined smoke run passes and Monobank completes its integration-side testing.
