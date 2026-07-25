# Codex Report — PAY-001: Phase 2 architecture readiness

Date: 2026-07-21

## Scope
Reviewed `handoff_PAY-001_RESET_checkout-architecture-correction_20260721.md` first, then the historical PAY-001 handoff and the Phase 2 UI specification. This report records the required architecture decision before a Phase 2 patch; no runtime files, settings, database rows, or deployed patches were changed.

## Architecture decision
Phase 2 must use a dedicated `checkout/checkout` credit entry, not `extension/mono_chast/.../getMethods()`.

- Keep the deployed SimpleCheckout isolation intact: its `getMethods()` continues to return `[]`.
- Expose the credit option only from the stock `checkout/payment_method` controller, after the normal address/shipping validation, and only while `payment_mono_chast_status=1`, UAH is active, the cart total is at least 500 UAH, and the Mono configuration is complete.
- The controller must add a virtual `mono_chast.mono_chast_{3|4|5}` option to the stock-checkout payment map. This preserves the existing order-write boundary and lets the already-installed Mono controller handle its own `confirm()` route after order creation.
- Product-page selection carries the allowed 3/4/5 value to `checkout/checkout`; the stock checkout validates it again before saving the virtual method. No client-provided part count is trusted at the write boundary.

This keeps the visible credit flow inside the checkout that ST-2c will cut over to, while avoiding any re-exposure in live SimpleCheckout.

## Files inspected
```
backup-7.19.2026_09-58-50_boosters.tar.gz
booster-debug-ST2c4-guest-shipping.tar.gz
booster-debug-ST2c5-display-only-shipping.tar.gz
handoffs/handoff_PAY-001_RESET_checkout-architecture-correction_20260721.md
handoffs/handoff_PAY-001_monobank-chastyny-integration_20260718.md
handoffs/CODEX - PAY-001-credit-flow.md
patches/PAY-001_monobank_chastyny_sandbox_20260719.php
patches/PAY-001_simple_checkout_isolation_20260721.php
```

## Dry-run result
```
fresh full backup: backup-7.19.2026_09-58-50_boosters.tar.gz
latest checkout evidence: booster-debug-ST2c5-display-only-shipping.tar.gz
SimpleCheckout isolation: deployed; getMethods() returns []
stock checkout payment save: validates selected code from current server-side payment map
PUMB asset search: no PUMB logo found in backup or repo
```

## php -l result
No patch was created; no PHP source was changed or linted in this readiness pass.

## Idempotency
Not applicable: this is a read-only architecture report.

## Rollback
Not applicable: no files or data were changed.

## PUMB input received after this review
The owner supplied the official PUMB package `Логотипи по Сплачуйте частинами та ПУМБ.zip` on 2026-07-21. It contains original PUMB and `Сплачуйте частинами` assets, including SVG variants; Phase 2 can embed a selected supplied SVG as a site payment asset rather than inventing a logo.

Business rule confirmed by the owner: both Mono and PUMB offers are limited to 3, 4, or 5 payments. The PUMB product must use its approved exact name `Сплачуйте частинами` and `ПУМБ` uppercase. The later PUMB integration must independently verify its API/merchant flow before treating its bank states as identical to Mono states.

## Post-deploy QA checklist (for the future Phase 2 patch)
- [ ] With `payment_mono_chast_status=0`, neither checkout exposes the Mono credit option.
- [ ] With the method temporarily enabled for sandbox, the option appears only in `checkout/checkout`, never in SimpleCheckout.
- [ ] Product selection 3/4/5 matches the value persisted by the stock checkout and the Mono API request.
- [ ] The existing Hutko, COD, and IBAN flows pass the checkout smoke test.
- [ ] Owner completes sandbox QA, then restores `payment_mono_chast_status=0` and keeps SimpleCheckout isolation deployed.
- [ ] PUMB remains an inactive `Скоро буде` offer until its merchant/API integration is separately approved.

## Side effects / risks
- `checkout/checkout.twig`, `payment_method.php`, and the product template are active, shared checkout/product surfaces; a Phase 2 patch must use current anchors from a fresh post-implementation backup and preserve all later ST-2c/CHECKOUT changes.
- The supplied full backup predates the deployed Mono extension; it is valid for the existing product/CSS shape, while the newer checkout debug archives are the current source evidence for the checkout files.
- The current repo has unrelated untracked MKT-TG-008 files. They were not read, changed, or included in this PAY-001 work.
