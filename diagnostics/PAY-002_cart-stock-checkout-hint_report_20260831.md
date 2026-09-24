# PAY-002 cart stock/minimum checkout hint — local report

Date: 2026-08-31
Scope: cart feedback only
Database: not touched
Deployment: not performed

## Evidence and root cause

Fresh sources:

- `booster-debug-cart-stock-hint-20260831 (1).tar.gz`, SHA-256 `2ee02625cc0a52d701efaa3948d2b707f93d3417aa393e30886566fd68dcda35`.
- `booster-debug-cart-list-20260831.tar.gz`, SHA-256 `a5b2a03c9b02326285b4fc461201dbb19fb12dffb9238350cb29a9372043a46a`.

`checkout/checkout.php` redirects to cart before any payment logic if stock is
insufficient or cart minimums are not met. `checkout/cart.php` already computed
the same stock state and exposed individual minimum errors, but
`checkout/cart_list.twig` only showed a dismissible generic stock alert, kept
the checkout CTA active, and provided no combined explanation of what to do.

## Patch

`patches/PAY-002_cart-stock-checkout-hint_20260831.php` touches only:

- `catalog/controller/checkout/cart.php`
- `catalog/view/template/checkout/cart_list.twig`

The controller exposes two booleans matching the actual checkout blockers:
insufficient stock when stock checkout is disabled, and a product below its
minimum quantity. The template then shows a persistent Ukrainian instruction
above the cart and replaces the checkout link with a non-link CTA until the
customer adjusts or removes the affected item. Once cart quantities are updated,
the existing cart fragment reload recalculates both flags and restores the
normal **Продовжити** link.

No payment, product, price, stock quantity, cart mutation, database, CSS, timer,
or checkout-controller logic changes. The patch adds no `!important` or new
visual override: it reuses the existing Bootstrap alert and checkout button
classes.

## Source and patch guards

| File | Before SHA-256 | After SHA-256 |
|---|---|---|
| `checkout/cart.php` | `ba9384d9390370bd2ac1e329338b223742dff4e618620aa545085529ac06f71b` | `4901f911fe6ecd59ab764725b393842f477517480cfa1954529d00b1a0839a7b` |
| `checkout/cart_list.twig` | `3b7d4f617f36a005dd5c92fb1e72cacca403d3e6b602d018db7565b399f5bc13` | `97802adf60179d1e631d3119611b48c7b772f8ed3279005b99be92e8294e67ac` |

The runner checks both pre-image hashes and one-count anchors before backup or
write, preserves source line endings, creates a timestamped backup, restores
both verified hashes on failure, reports `already_applied=yes` for the exact
post-image, and self-deletes after a successful run.

## Local validation

```text
php -l runner: passed
generated cart.php php -l: passed
post-image SHA checks: passed
generated-content assertions: passed
clean fixture: done=ok, database_touched=no, self_delete=ok
repeat fixture run: already_applied=yes
source-drift fixture: hash_reject_no_write=ok, runner_retained=yes
```

Static review of the generated diff confirms one controller state addition, one
cart hint, and the conditional CTA; the original enabled-cart CTA remains
byte-for-byte in the normal branch. The markup uses existing responsive classes
and introduces no layout CSS. Local validation does not prove a live browser or
checkout flow.

## Rollback

Restore both files from the printed directory:
`_patch_backups/PAY-002_cart-stock-checkout-hint_20260831-<timestamp>-<suffix>/`.
Then clear the OpenCart template cache. No database rollback is required.

## Owner run command

```bash
cd ~/public_html || exit
php PAY-002_cart-stock-checkout-hint_20260831.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA

- With one in-stock product and a valid quantity, the normal green
  **Продовжити** link opens checkout.
- Raise a low-stock product above its available quantity: the cart shows the
  explanatory warning and **Виправте кошик для оформлення** cannot navigate.
- Reduce/remove that position: the warning disappears after the existing cart
  refresh and the normal checkout link returns.
- Verify the same behavior on desktop and mobile, then run the normal checkout
  smoke. Do not create a bank order unless separately authorized.
