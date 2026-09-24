# PAY-002 cart stock hint correction — local report

> Superseded after owner QA failure. The fresh archive proves the remaining
> input bug: `stock` is numeric quantity, while `stock_status` expresses
> availability for the requested quantity. See
> `PAY-002_cart-canonical-stock-and-checkout-timing_report_20260831.md`.
> Earlier synthetic-fixture success below did not establish production correctness.

Date: 2026-08-31  
Scope: correct the previously deployed cart hint only  
Database: not touched  
Deployment: not performed

## Root cause

The prior cart runner treated `minimum_status` as a checkout-blocking state.
The live result proves that field does not have that meaning in this cart flow:
an above-stock quantity showed a minimum-quantity message, and the state stayed
blocked after reducing to the available quantity. The stock guard in
`checkout/checkout.php` remains the canonical checkout policy.

## Correction

`patches/PAY-002_cart-stock-hint-correction_20260831.php` touches only:

- `catalog/controller/checkout/cart.php`
- `catalog/view/template/checkout/cart_list.twig`

It removes the incorrect minimum flag. It always shows a specific warning when
the selected quantity exceeds the available stock. The normal cart checkout
link remains available when `config_stock_checkout` permits checkout, matching
the observed mini-cart behavior. Only if that same native configuration blocks
checkout does the cart show a disabled non-link CTA.

No payment, product, price, stock quantity, cart mutation, database, CSS,
timer, or checkout-controller behavior changes. No `!important` or new style
override is introduced.

## Source and runner guards

The correction accepts only the exact server post-image reported after the
previous runner:

| File | Required SHA-256 before correction |
|---|---|
| `checkout/cart.php` | `4901f911fe6ecd59ab764725b393842f477517480cfa1954529d00b1a0839a7b` |
| `checkout/cart_list.twig` | `97802adf60179d1e631d3119611b48c7b772f8ed3279005b99be92e8294e67ac` |

It also checks every replacement anchor once before backup or write, creates a
timestamped verified backup, lints PHP after write, restores both files on any
post-write failure, logs all resulting SHA-256 values, and self-deletes only
after success.

## Local validation

```text
php -l runner: passed
synthetic fixture: backup, generated PHP lint, post-state assertions, and self-delete passed
repeat fixture run: already_applied=yes
source-drift fixture: rejected before write, runner retained
```

The original source archives are not present in the local workspace at this
time, so an extracted end-to-end fixture could not be re-run. The exact
server-side pre-image hashes and one-count anchors are therefore mandatory
gates; any source drift stops before backup or write. Browser and live checkout
QA remain owner-gated.

## Expected QA

- Quantity above stock: the cart says that the selected quantity exceeds the
  available stock; it must never cite a minimum quantity.
- With checkout still allowed by the native configuration: **Продовжити** stays
  a normal link, matching the existing mini-cart route.
- Reduce to an available quantity and update the cart: the stock warning
  disappears and the normal CTA remains.
- If native `config_stock_checkout` is disabled for an out-of-stock cart, the
  warning remains and the cart CTA is non-navigable.

## Rollback

Restore both files from the printed `_patch_backups/PAY-002_cart-stock-hint-correction_20260831-.../`
directory, then clear the OpenCart template cache. No database rollback is required.
