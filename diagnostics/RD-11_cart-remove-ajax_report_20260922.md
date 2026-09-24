# RD-11 cart remove AJAX follow-up — diagnostic and local verification

Date: 2026-09-22

## Scope and evidence

The owner reported that removing the last cart item opens `checkout/cart.remove` and shows a JSON object with `redirect` in the browser. The owner supplied the current production `catalog/view/template/checkout/cart.twig` on 2026-09-22 (local attachment `C:\Users\14bez\Downloads\cart (1).twig`). The previously supplied current `cart_list.twig` contains `<a href="{{ product.remove }}" class="bs-cart-remove">`.

In that `cart.twig`, line 197 delegates removal clicks to `.btn-danger`. RD-11 changed the link's class to `.bs-cart-remove` but left this click selector unchanged. Consequently, the browser follows the JSON endpoint as a normal link. The existing AJAX success path already handles both server responses: `redirect` after the last item and `success` while items remain. The production controller and delivery calculations need no change.

## Prepared patch

`patches/RD-11_cart-remove-ajax_20260922.php`

The runner changes only `catalog/view/template/checkout/cart.twig`: it replaces the one delegated selector `.btn-danger` with `.bs-cart-remove` and adds an idempotency marker beside it. It checks the live `cart_list.twig` link contract before writing. No CSS, controller, database, RD-12, payment, price, or shipping changes are included.

The runner checks exact anchor counts, backs up the Twig file under `_patch_backups/RD-11_cart-remove-ajax_20260922-<timestamp>/`, parses the complete Twig template before and after writing, restores the backup on a post-write failure, and self-deletes after `done=ok` or `already_applied=yes`.

## Local verification

Fixture input: the owner-supplied `cart.twig` and `cart_list.twig`. All runs were local; no server access or production action occurred.

```text
php -l patches/RD-11_cart-remove-ajax_20260922.php
No syntax errors detected

clean fixture:
twig_compile=ok files=1 stage=prewrite
twig_compile=ok files=1 stage=postwrite
changed_file=catalog/view/template/checkout/cart.twig
php_l=ok file=RD-11_cart-remove-ajax_20260922.php
done=ok

repeat with re-uploaded runner:
already_applied=yes

drift fixture with changed old handler:
error=anchor_count name=cart_remove_handler expected=1 actual=0
exit=1; backup_created=no; runner_present=yes

inline JavaScript: node --check exit=0
handler smoke: cart_remove_smoke=ok cases=last_item,remaining_items
```

The focused JavaScript smoke evaluated the actual extracted handler with both response shapes. It confirmed `preventDefault`, the JSON GET request, redirect on last-item removal, and fragment reload for a remaining cart. This is local behavior proof, not production browser QA.

## Owner-run command

Upload only `RD-11_cart-remove-ajax_20260922.php` to `~/public_html` and run:

```bash
cd ~/public_html || exit
php RD-11_cart-remove-ajax_20260922.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

Expected: `done=ok` and `cache cleared`. `already_applied=yes` means the exact marked change was already present. On `error=...`, stop and inspect the full output and fresh live files before changing the runner.

## Rollback and QA gate

The runner prints the exact `backup=` directory. Restore only its `catalog/view/template/checkout/cart.twig`, then clear OpenCart cache. The runner does not touch the separate recommendation removal patch.

- [ ] With two items, remove one: cart page remains open, exactly one item remains, and the header minicart count updates.
- [ ] Remove the final item: browser opens the normal empty-cart page, not the JSON endpoint.
- [ ] Repeat at desktop and mobile widths; verify the quantity controls and checkout link still work.
- [ ] Perform the project Tier 1 smoke URLs after deployment and retain the complete terminal output.

Production deployment and owner QA are unconfirmed. No commit, push, or Notion status change was made.
