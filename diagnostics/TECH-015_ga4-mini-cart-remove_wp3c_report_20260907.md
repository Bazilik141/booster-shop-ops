# Codex Report — TECH-015 WP3c: GA4 mini-cart removal

Date: 2026-09-07

## Outcome

WP3c is ready for owner deployment after WP3a passes production QA and before
WP3b. The runner
adds one guarded `remove_from_cart` call inside the existing mini-cart removal
AJAX success callback, after the server accepts the removal and before the cart
fragments reload.

The existing click handler, request URL, cart key, redirect, reload, quantity
logic and error handling remain unchanged. No vendor click interception is
introduced.

## Files delivered

```text
patches/TECH-015_ga4-mini-cart-remove_wp3c_20260907.php
diagnostics/TECH-015_ga4-mini-cart-remove_wp3c_report_20260907.md
```

Live target of the runner: `catalog/view/template/common/cart.twig`.

## Verified source contract

- Fresh source SHA-256 gate:
  `9b2a8c326604c64ddfe29b8e289712690474cfc2dea59f1de931947d78aa1fa1`.
- Generated source SHA-256:
  `020b8f2a9dfee24d8c55b386415ef31731d16586a70191cbd8266b54dfd27fec`.
- The mini-cart controller prepares vendor dataset keys as
  `remove_from_cart_<cart_id>`.
- The current dropdown anchor injects that dataset into
  `ps_dataLayer.ga4_data`.
- The runner uses the existing button's `data-key`, verifies the exact dataset
  exists, and then calls `ps_dataLayer.onClick('remove_from_cart', cartId)`.
- No `data-ps-track-event`, click redispatch, delay or cart-controller change is
  introduced.

Known deferred cleanup, explicitly not a reason to reissue this patch: the live
mini-cart handler already contains `console.log(json)`. WP3c leaves that
pre-existing production log untouched; remove it later in a separate cleanup
scope.

## Local verification

Clean fixture application:

```text
changed_file=catalog/view/template/common/cart.twig
after_sha256=020b8f2a9dfee24d8c55b386415ef31731d16586a70191cbd8266b54dfd27fec
database_touched=no
done=ok
self_delete=ok
```

Additional gates:

```text
php_lint=ok
twig_embedded_js_parse=ok
wp3_semantic_smoke=ok
wp3c_remove_success_position=ok
repeat_run=already_applied=yes
drift_run_exit=1
drift_error=Source SHA-256 mismatch: catalog/view/template/common/cart.twig
drift_backup_dirs=0
```

An initial fixture run deliberately failed closed because a shorter success
anchor matched both quantity and removal callbacks. The runner was corrected to
anchor the complete removal request block; the final fixture proves the marker
exists only in the remove callback. No file was written and no backup was made
by that rejected attempt.

## Production execution evidence

Deployed by the owner on production at `2026-09-07T19:04:08+00:00`:

```text
cwd=/home2/boosters/public_html
time=2026-09-07T19:04:08+00:00
backup=/home2/boosters/public_html/_patch_backups/TECH-015_ga4-mini-cart-remove_wp3c_20260907-20260907-190408-1a0dad
backup_file=/home2/boosters/public_html/_patch_backups/TECH-015_ga4-mini-cart-remove_wp3c_20260907-20260907-190408-1a0dad/original/catalog/view/template/common/cart.twig
php_lint=not_applicable target=catalog/view/template/common/cart.twig
changed_file=catalog/view/template/common/cart.twig
after_sha256=020b8f2a9dfee24d8c55b386415ef31731d16586a70191cbd8266b54dfd27fec
database_touched=no
done=ok
self_delete=ok
cache cleared
```

## Rollback

Backup directory:

```text
_patch_backups/TECH-015_ga4-mini-cart-remove_wp3c_20260907-<timestamp>-<suffix>/
```

Restore the exact printed backup's original
`catalog/view/template/common/cart.twig`, then clear OpenCart cache.

## Owner run command

Upload the runner to `~/public_html`, then:

```bash
cd ~/public_html || exit
php TECH-015_ga4-mini-cart-remove_wp3c_20260907.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA

- [x] Runner output ends with `done=ok` and `self_delete=ok`; recorded above.
- [x] Removing one item from the header mini-cart removes exactly that item — owner-confirmed.
- [x] One GA4 batch contains one `en=remove_from_cart` for the removed product
  with correct id, price, quantity and currency.
- [x] Inspect the GA4 request payload itself; do not rely on a narrow Network
  text filter because GA4 may batch several events into one request.
- [x] Cart line count and total are exact after fragment reload — owner-confirmed.
- [x] Quantity changes in the mini-cart and full cart still behave as before — owner-confirmed.
- [x] No duplicate event and no new console error — owner-confirmed.
- [x] All Tier 1 URLs from `AGENTS.md` pass — owner-confirmed.

Rollback immediately for any wrong cart line, total, duplicate event, failed
removal or new console error.

After all WP3c checks pass, deploy WP3b separately and run its cart-first QA.

## Production runtime evidence

Owner DevTools payload confirms the mini-cart event on 2026-09-07:

```text
tid=G-283QW89TX8
en=remove_from_cart
cu=UAH
epn.value=1000
pr1=id110 ... pr1000 ... qt1
item_list_id=cart_products
item_list_name=Cart products
```

The event uses the expected vendor-built cart payload. Functional cart state,
console state and Tier 1 remain separate owner-confirmation gates.
