# Codex Report — PAY-001: Phase 2c cart contract and credit gates

Date: 2026-07-25

## Scope

Implemented the Phase 2c handoff against the owner-collected current live snapshot `booster-debug-PAY001-phase2c-current.tar.gz`, with `CODEX - PAY-001-ADDENDUM-2.md` taking precedence:

- both modal actions use the stock `checkout/cart.add` endpoint with the selected quantity and options;
- `Додати й оформити` redirects only after a successful cart add; `Продовжити покупки` adds and closes without redirect; `×` remains no-op;
- product credit UI stays visible and reacts to selected quantity, threshold, and factual stock;
- stock checkout calculates the Mono gate from final payable after coupon/discount and rejects factual stock `< 1`, with preorder taking priority;
- chips remain in canonical `3 → 4 → 5` order, use correct Ukrainian grammar, and the checkout subtitle is removed;
- coupon/cart total refreshes re-fetch payment methods and update the soft blocker.
- warning colors use the existing `--bs-warning-bg`, `--bs-warning-fg`, and `--bs-warning-line` design tokens;
- bank cards are not faded merely because another bank/term is selected; the PUMB `СКОРО БУДЕ` card keeps its current status and dashed border but no longer loses its brand colors through `opacity`.

The optional same-SKU quantity already present in cart is not included in the product-page estimate. The addendum explicitly marks this as a non-blocker; the product page uses `unit price × selected quantity`, while checkout remains authoritative.

No DB/settings changes. No changes to SimpleCheckout isolation, order-write, Mono API controller, Hutko, COD, IBAN, shipping, or URL routing.

## Files touched

```text
patches/PAY-001_phase2c_cart_contract_gates_20260725.php

catalog/controller/product/product.php
catalog/view/template/product/product.twig
catalog/controller/checkout/payment_method.php
catalog/view/template/checkout/payment_method.twig
catalog/view/template/checkout/checkout.twig
catalog/view/javascript/checkout-state.js
catalog/view/stylesheet/boostershop-ds.css
catalog/view/template/common/header.twig
```

The patch performs exact source SHA256 checks plus one-count anchors before writing all eight files.

The first production attempt safely stopped before backup/write with a SHA mismatch in `catalog/view/template/common/header.twig`. A three-way comparison showed that the other seven targets still matched the 2026-07-24 backup exactly; only TOC-003 had changed the stylesheet cache-buster from `pay001-ui-20260721` to `toc003-20260724`. The rebased target preserves that drift as `toc003-pay001-phase2c-20260725`.

## Root causes fixed

- Product modal action previously only assigned the checkout URL and never called `checkout/cart.add`.
- Product visibility was gated by one-unit price and factual stock, so low-priced and preorder states could not be communicated.
- `pay001MonoChastEnabled()` used raw cart total before coupon totals and did not independently gate factual stock zero.
- `usort()` moved the selected term to the first chip position.
- Checkout totals refresh did not re-fetch payment methods after coupon changes.

## Dry-run result

```text
backup=...\_patch_backups\PAY-001_phase2c_cart_contract_gates_20260725-20260725-041203
changed_file=catalog/controller/product/product.php
changed_file=catalog/view/template/product/product.twig
changed_file=catalog/controller/checkout/payment_method.php
changed_file=catalog/view/template/checkout/payment_method.twig
changed_file=catalog/view/template/checkout/checkout.twig
changed_file=catalog/view/javascript/checkout-state.js
changed_file=catalog/view/stylesheet/boostershop-ds.css
changed_file=catalog/view/template/common/header.twig
php_l=ok
done=ok
post_apply_hashes=ok
```

Static contract checks passed for both modal actions, cart payload, product gate, coupon-aware final total, factual-stock gate, canonical chip order, subtitle removal, grammar helper, warnings, confirm blocker, and reactive payment-method refresh.

The coupon-aware totals call was verified against the exact engine/model files from the same backup:

- `Loader::model()` registers every model method as a closure on `Opencart\System\Engine\Proxy`;
- `Proxy::__get()` returns that closure by reference;
- `catalog/model/checkout/cart.php::getTotals(array &$totals, array &$taxes, int &$total)` requires reference parameters;
- the existing live `catalog/controller/checkout/confirm.php` already uses the identical `($this->model_checkout_cart->getTotals)(...)` form after `prepareCouponTotal()`;
- a local runtime harness using the extracted real `Proxy` returned `proxy_reference=ok total=875 coupon=-125 tax=20`, proving reference mutations propagate through this invocation form.

This validates the PHP/OpenCart call mechanism. The actual configured coupon plus credit behavior still requires the owner-side checkout QA because local source-copy validation has no production session, DB, or enabled total extensions.

Embedded Twig JavaScript parse check:

```text
twig_js=ok file=.../product/product.twig
twig_js=ok file=.../checkout/payment_method.twig
twig_js=ok file=.../checkout/checkout.twig
```

## php -l result

```text
No syntax errors detected in PAY-001_phase2c_cart_contract_gates_20260725.php
No syntax errors detected in catalog/controller/product/product.php
No syntax errors detected in catalog/controller/checkout/payment_method.php
```

`node --check catalog/view/javascript/checkout-state.js` also passed.

## Idempotency

Re-uploading and re-running against the already-patched dry-run tree returns:

```text
already_applied=yes
```

The runner self-deletes after success or an already-applied result.

## Rollback

The patch prints the exact backup directory:

```text
_patch_backups/PAY-001_phase2c_cart_contract_gates_20260725-<timestamp>/
```

Restore all eight files from the matching relative paths in that directory, then clear OpenCart cache. On any PHP lint failure, the runner restores them automatically.

## Run command (owner)

```bash
cd ~/public_html || exit
php PAY-001_phase2c_cart_contract_gates_20260725.php
```

## Post-deploy QA checklist

- [ ] Status `payment_mono_chast_status` stays unchanged until controlled QA.
- [ ] In-stock product above threshold: choose 5 payments, click `Додати й оформити`; selected quantity is added, stock checkout opens, credit and 5 are selected.
- [ ] Repeat with required product options; validation error does not redirect.
- [ ] `Продовжити покупки` adds the same quantity/options, refreshes the cart badge, closes the modal, and does not redirect.
- [ ] `×` closes without adding.
- [ ] Low-price product: credit card is muted at quantity 1; raising quantity above the configured threshold activates it without reload.
- [ ] Factual stock `0`: credit UI remains visible but disabled with availability hint.
- [ ] Checkout containing any factual-stock-zero product does not receive a Mono option in `payment_method.getMethods`; if credit was selected, the preorder warning is shown and confirm is disabled.
- [ ] Eligible checkout: chips remain `3, 4, 5`; labels are `3 платежі`, `4 платежі`, `5 платежів`.
- [ ] Apply a coupon that lowers final payable below the dynamic threshold: Mono is removed/rejected, warning appears, confirm is disabled.
- [ ] Remove the coupon or add enough value: Mono becomes available again and the prior credit intent is re-saved.
- [ ] Switching to Hutko, COD, or IBAN clears the blocker and confirms normally.
- [ ] Guest and authorized checkout smoke: shipping, NP address, coupon, oferta, deferred confirm, Hutko/COD/IBAN.
- [ ] SimpleCheckout retains its existing isolated behavior.
- [ ] Visual QA at 375px, tablet, and desktop; modal actions stack primary then secondary on mobile.
- [ ] PUMB card retains full brand color when monobank or one of its terms is active; only active selection styling changes, not the opacity of other bank cards.

## Side effects / risks

- Checkout/payment is a high-risk area; production QA is mandatory before enabling Mono broadly.
- The shared design-system file is changed only inside the existing PAY-001 component block. The existing `!important` declarations on the black primary button are retained; no new global override or global selector was added.
- Client-side product eligibility is guidance only. Server-side checkout gate is authoritative.
- No live Mono request was made during local/source-copy validation.
