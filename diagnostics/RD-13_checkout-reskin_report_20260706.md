# Codex Report — RD-13: stock checkout visual reskin

Date: 2026-07-06

## Scope

Implemented the owner-approved visual-only part of the FINAL RD-13 handoff:

- four-card stock checkout shell: Отримувач, Доставка, Оплата, Замовлення;
- desktop sticky order summary;
- mobile collapsible receiver, delivery, and order details;
- guest login nudge;
- responsive form/radio/summary styling;
- presentation-only movement of comment, guest oferta, and account opt-in into
  the order card;
- final CTA copy: `Підтвердити замовлення →`.

Deliberately deferred to a separate task:

- stock coupon/First15 endpoint and promo UI;
- free-shipping threshold, pricing rule, and progress UI.

No DB, controller, model, SimpleCheckout, payment, Hutko, Checkbox,
fiscalization, shipping calculation, order-status, or success-page changes.

The active URL generator still routes normal checkout links to SimpleCheckout.
This patch prepares the stock checkout for later cutover and can be inspected
directly with an active cart session at the stock checkout route.

## Files touched

```text
patches/RD-13_checkout-reskin_20260706.php
```

The hosting patch changes:

```text
catalog/view/template/checkout/checkout.twig
catalog/view/stylesheet/boostershop-ds.css
catalog/view/javascript/checkout-reskin.js
```

## Dry-run result

Validated from a clean extraction of
`backup-7.6.2026_12-14-21_boosters.tar.gz`:

```text
source checkout.twig sha256=18a1b139a86a106ee653d37197664e4bb72d6faa4d0ee4127252554a818dff96
source boostershop-ds.css sha256=f9e0c5da032d86374054713b8fdaa1c30c2edc43fb1269b91a39f8717de1991d
precheck_trusted_click_preserved=ok
precheck_guest_oferta_preserved=ok
precheck_account_opt_in_preserved=ok
precheck_persistent_loader_preserved=ok
precheck_coupon_endpoint_not_added=ok
precheck_simplecheckout_not_added=ok
changed_files=3
done=ok
```

Local JavaScript validation:

```text
node --check catalog/view/javascript/checkout-reskin.js
exit=0
```

## php -l result

```text
No syntax errors detected in RD-13_checkout-reskin_20260706.php
```

No PHP storefront files are changed.

## Idempotency

Re-uploading and re-running after a successful application returns:

```text
already_applied=yes
changed_files=0
done=ok
```

## Rollback

Backup is created before writes at:

```text
_patch_backups/RD-13_checkout-reskin_20260706-<timestamp>/
```

Restore:

```bash
cp _patch_backups/RD-13_checkout-reskin_20260706-<timestamp>/catalog/view/template/checkout/checkout.twig catalog/view/template/checkout/checkout.twig
cp _patch_backups/RD-13_checkout-reskin_20260706-<timestamp>/catalog/view/stylesheet/boostershop-ds.css catalog/view/stylesheet/boostershop-ds.css
rm -f catalog/view/javascript/checkout-reskin.js
```

Then clear OpenCart cache.

## Run command (owner)

```bash
cd ~/public_html || exit
php RD-13_checkout-reskin_20260706.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA checklist

- [ ] Open the stock route with a non-empty cart and confirm four visual cards.
- [ ] Desktop: Замовлення is sticky and all receiver/delivery fields remain usable.
- [ ] Mobile: receiver/delivery collapse only when required data is present.
- [ ] Mobile: order-item details collapse; comment/oferta/CTA remain available.
- [ ] Guest: login nudge appears; authenticated checkout does not show it.
- [ ] Guest: captcha and newsletter retain current behavior.
- [ ] Guest: account opt-in ON creates an account; OFF does not.
- [ ] Guest oferta is visible/required; authenticated oferta remains absent.
- [ ] NP area → city → warehouse/courier cascade retains values and autosaves.
- [ ] Payment selection alone does not grow `oc_order`.
- [ ] Trusted final CTA creates exactly one order.
- [ ] Card / Google Pay / Apple Pay completes sandbox payment.
- [ ] COD and IBAN each create one order with the expected status/instructions.
- [ ] Hutko payload and Checkbox fiscalization remain unchanged.
- [ ] Persistent loader remains visible through the full submit sequence.
- [ ] No promo or free-shipping-progress UI is expected in this patch.

## Side effects / risks

- High-risk page, but the implementation is limited to markup, namespaced CSS,
  and presentation-only DOM organization.
- The patch does not change the currently routed SimpleCheckout page.
- Full-cycle owner smoke remains mandatory before stock-checkout cutover.
- `rd13-checkout.jsx` was unavailable; the complete FINAL handoff was used as
  the design specification.
