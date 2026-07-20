# Codex Report — RD-13: checkout reskin preflight

Date: 2026-07-06

## Scope

Reviewed the FINAL RD-13 handoff and design canvas, checked the local roadmap
mirror, inspected the RD-13 site snapshot, reconstructed the latest known
stock-checkout source by replaying the approved CHECKOUT-001 patches from
2026-07-04/05, and then verified that reconstruction byte-for-byte against the
full cPanel backup `backup-7.6.2026_12-14-21_boosters.tar.gz`.

No production patch was created because the current live source contract is not
yet proven and the handoff's sample Twig cannot safely replace the current
checkout implementation as-is.

## Files touched

```text
diagnostics/RD-13_checkout-reskin-preflight_report_20260706.md
handoffs/handoff_RD-13_checkout-reskin_2026-07-05.md
```

No storefront, controller, database, payment, shipping, Hutko, Checkbox, or
fiscalization files were changed.

## Evidence and findings

- Local roadmap mirror: RD-13 is `active`, last updated 2026-07-06, with owner
  confirmation to proceed despite RD-11/RD-12 remaining listed as blockers.
- Latest full cPanel backup:
  `backup-7.6.2026_12-14-21_boosters.tar.gz` (2026-07-06 12:14:21).
- RD-13 rendered-site snapshot:
  `rd13_site-snapshot_20260705_121237.zip`.
- Latest known stock checkout was reconstructed from
  `booster-debug-CHECKOUT-001-phase1.tar.gz`, the missing register-mail file,
  and the five approved CHECKOUT-001 patches from 2026-07-04/05.
- Reconstructed `catalog/view/template/checkout/checkout.twig`:
  1,330 lines / 51,451 characters.
- Reconstructed source SHA-256:
  - `checkout.twig`:
    `18A1B139A86A106EE653D37197664E4BB72D6FAA4D0EE4127252554A818DFF96`
  - `payment_method.php`:
    `D6A957EE26E374F5022DC0740B0F9C56807E69A7ABFFC23417B8B009EF92057C`
  - `confirm.php`:
    `F299E33949C687B3C9FB204053CACFF2896EE0A1885D82BAC079C53077A3029F`
- Snapshot `boostershop-ds.css` SHA-256:
  `F9E0C5DA032D86374054713B8FDAA1C30C2EDC43FB1269B91A39F8717DE1991D`.
- The reconstructed checkout preserves the critical markers for:
  - ST-2b.6d trusted-click deferred confirm;
  - CHECKOUT-001 guest-only oferta;
  - CHECKOUT-001 guest account opt-in;
  - CHECKOUT-001 persistent submit overlay.
- The current `checkout.php` passes pre-rendered partial HTML only:
  `register`, `payment_address`, `shipping_address`, `shipping_method`,
  `payment_method`, and `confirm`.
- The handoff's sample Twig expects a different direct-data contract:
  `shipping_price`, free-shipping threshold/progress, promo breakdown,
  cart items, payable total, saved address state, and captcha widget.
- The 2026-07-06 backup confirms that stock checkout has no
  `catalog/controller/checkout/coupon.php`,
  `catalog/model/checkout/booster_coupon.php`, or
  `catalog/view/template/checkout/coupon.twig`, even though ST-2b.5 is marked
  Done in the roadmap mirror.
- Coupon/First15 remains implemented in the currently routed SimpleCheckout
  controller/template only. It is not available to the stock checkout targeted
  by RD-13.
- `total_coupon_status` is `0` in the backup database; SimpleCheckout enables
  it at runtime, while stock checkout currently has no equivalent runtime
  preparation.
- No free-shipping threshold setting exists in `ocp5_setting`, and no
  threshold/progress implementation exists in either the stock checkout or
  SimpleCheckout source. The handoff's requirement to read a threshold from
  config therefore needs a new, explicitly approved source of configuration.
- `ocp5_theme` contains no data rows, so no database theme override currently
  masks `catalog/view/template/checkout/checkout.twig`.
- `system/library/url.php` still rewrites `checkout/checkout` to
  `extension/SimpleCheckout/module/pinta_simple_checkout`; RD-13 therefore
  prepares the stock checkout for the later cutover and does not alter the
  currently routed checkout by itself.
- `rd13-checkout.jsx`, named by the handoff as the final design source of truth,
  was not supplied and was not found under `C:\Users\14bez\Downloads`.

## Dry-run result

The known checkout chain replayed successfully in a local isolated tree:

```text
CHECKOUT-001_guest-account-creation_20260704.php: done=ok
CHECKOUT-001_phase1-1_guest-only-oferta_20260705.php: done=ok
CHECKOUT-001_phase1-2_agree-session-hotfix_20260705.php: done=ok
CHECKOUT-001_phase1-2_checkout-ux-loader_20260705.php: done=ok
CHECKOUT-001_phase1-3_guest-oferta-loader-fix_20260705.php: done=ok
```

The full 2026-07-06 backup now proves that the reconstructed baseline matches
the current host backup state.

## php -l result

All PHP controllers modified during the isolated replay passed their existing
patch lint gates. No RD-13 patch exists yet, so no RD-13 `php -l` result is
claimed.

## Idempotency

Not applicable until the live source bundle is collected and the RD-13 patch is
built. The final patch must return `already_applied=yes` on repeat execution.

## Rollback

No storefront changes were made. The final RD-13 patch must back up every
changed file under:

```text
_patch_backups/RD-13_checkout-reskin_20260706-<timestamp>/
```

and restore all changed files automatically if any post-write or syntax gate
fails.

## Required scope decision

The owner must explicitly choose whether RD-13 may depend on a separate
preparatory patch that:

1. restores the already-designed ST-2b.5 stock coupon/First15 endpoint without
   changing SimpleCheckout or payment logic; and
2. adds one durable free-shipping-threshold config setting for the stock
   checkout, including rollback SQL.

Without that approval, RD-13 must omit the promo and free-shipping-progress
blocks and would not satisfy the FINAL handoff.

`rd13-checkout.jsx`, named as the design source of truth, is still absent. If
it cannot be supplied, implementation can use the complete FINAL handoff as
the sole design specification after owner approval.

## Run command (owner)

Blocked until the owner approves or rejects the dependency/config scope above.
No patch command is safe to provide yet.

## Post-deploy QA checklist

- [ ] Card / Google Pay / Apple Pay completes one sandbox order.
- [ ] COD completes one order with the correct status.
- [ ] IBAN completes one order and shows the correct instructions.
- [ ] Hutko request payload is unchanged from the pre-reskin baseline.
- [ ] Checkbox fiscalization still fires exactly once.
- [ ] Shipping price matches the carrier API response.
- [ ] Free-shipping threshold comes from config and works just below/above it.
- [ ] Promo apply/remove refreshes discount and payable totals correctly.
- [ ] Guest captcha still blocks an unsolved submission.
- [ ] Guest account opt-in ON/OFF preserves existing behavior.
- [ ] Authorized saved/manual address switching uses the selected address.
- [ ] Mobile collapse preserves values and never hides missing required data.
- [ ] Server-side validation still blocks invalid submission.
- [ ] Payment selection alone does not create an order.
- [ ] Exactly one trusted final click creates exactly one order.

## Side effects / risks

The main risk is replacing the current partial-driven checkout with a
direct-data Twig mockup and thereby deleting proven checkout safeguards.
Implementation must preserve current IDs, form names, AJAX endpoints, trusted
click ordering, guest-only agreement state, account opt-in, and persistent
loader behavior. No DB schema or DB row change belongs in RD-13.
