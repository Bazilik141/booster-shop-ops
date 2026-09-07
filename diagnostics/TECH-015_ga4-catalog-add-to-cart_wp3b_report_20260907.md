# Codex Report — TECH-015 WP3b: GA4 catalogue tile add-to-cart

Date: 2026-09-07

## Outcome

WP3b is ready for owner deployment only after WP3a and WP3c pass production QA.
It is intentionally last and isolated because it is the only WP3 package that
touches the catalogue buy button. The runner
adds one globally idempotent, delegated submit observer to the existing Booster
tile form. It calls the vendor's prepared dataset without preventing, delaying
or redispatching the cart submit.

Directly addable products emit `add_to_cart`. Products with options retain the
current redirect-to-product behaviour and emit the vendor-intended
`select_item`; out-of-stock tiles still have no form. The existing preorder,
form action, hidden inputs, stock handling, classes and cart logic are unchanged.

## Files delivered

```text
patches/TECH-015_ga4-catalog-add-to-cart_wp3b_20260907.php
diagnostics/TECH-015_ga4-catalog-add-to-cart_wp3b_report_20260907.md
```

Live target of the runner: `catalog/view/template/product/thumb.twig`.

## Verified source contract

- Fresh source SHA-256 gate:
  `0375e269381f19e85573e3702d81dd118bf0b8b4e562ea60f9c9a30d3ccea807`.
- Generated source SHA-256:
  `ee483c07b40bdd58a2b23e75793b9850aef61ed4efd9df8bf0944f7b538f9c5a`.
- Category, search and special dataset injection anchors are present.
- The listener uses one `window.bsTech015CatalogGa4Bound` flag and one
  namespaced delegated submit handler, so repeated thumb partials do not bind
  duplicate handlers.
- The exact `ga4_data[<event>_<product_id>]` key must exist before calling the
  vendor API; a disabled/missing module is a no-op.
- No `data-ps-track-event`, `data-ps-track-id`, `preventDefault`, `setTimeout`
  or synthetic click was added.

Known deferred cleanup, explicitly not a reason to reissue this patch: the
inline listener block is rendered once per product tile. The global binding flag
keeps runtime behaviour correct, but a later page-weight task should move the
shared listener to one page-level asset or render location.

## Local verification

Clean fixture application:

```text
changed_file=catalog/view/template/product/thumb.twig
after_sha256=ee483c07b40bdd58a2b23e75793b9850aef61ed4efd9df8bf0944f7b538f9c5a
database_touched=no
done=ok
self_delete=ok
```

Listener and runner gates:

```text
php_lint=ok
twig_embedded_js_parse=ok
wp3_semantic_smoke=ok
wp3b_binding_count=1
wp3b_calls=[["add_to_cart","85"],["select_item","86"]]
missing_dataset_calls=0
repeat_run=already_applied=yes
drift_run_exit=1
drift_error=Source SHA-256 mismatch: catalog/view/template/product/thumb.twig
drift_backup_dirs=0
```

The drift fixture proves that a source mismatch stops before backup or write.
The semantic smoke executes the listener twice, verifies only one binding, then
checks direct-add, option-product and missing-dataset branches.

## Production execution evidence

Deployed by the owner on production at `2026-09-07T19:24:35+00:00`:

```text
cwd=/home2/boosters/public_html
time=2026-09-07T19:24:35+00:00
backup=/home2/boosters/public_html/_patch_backups/TECH-015_ga4-catalog-add-to-cart_wp3b_20260907-20260907-192435-f5bebe
backup_file=/home2/boosters/public_html/_patch_backups/TECH-015_ga4-catalog-add-to-cart_wp3b_20260907-20260907-192435-f5bebe/original/catalog/view/template/product/thumb.twig
php_lint=not_applicable target=catalog/view/template/product/thumb.twig
changed_file=catalog/view/template/product/thumb.twig
after_sha256=ee483c07b40bdd58a2b23e75793b9850aef61ed4efd9df8bf0944f7b538f9c5a
database_touched=no
done=ok
self_delete=ok
cache cleared
```

## Rollback

Backup directory:

```text
_patch_backups/TECH-015_ga4-catalog-add-to-cart_wp3b_20260907-<timestamp>-<suffix>/
```

Restore the exact printed backup's original
`catalog/view/template/product/thumb.twig`, then clear OpenCart cache.

## Owner run command

Upload the runner to `~/public_html`, then:

```bash
cd ~/public_html || exit
php TECH-015_ga4-catalog-add-to-cart_wp3b_20260907.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA

- [x] Runner output ends with `done=ok` and `self_delete=ok`; recorded above.
- [x] One tile click adds exactly one line/unit and sends one
  `en=add_to_cart` with correct id, price, quantity and currency.
- [x] Second click adds one more unit and sends exactly one more event.
- [x] Adding a second product preserves exact line count and total.
- [x] An option-bearing tile redirects/selects as before and does not mutate the
  cart prematurely.
- [x] Preorder and out-of-stock tiles behave exactly as before.
- [x] No new console error; `view-source:` contains one marker per rendered
  thumb but runtime has one delegated binding.
- [x] Inspect the GA4 request payload itself; do not rely on a narrow Network
  text filter because GA4 may batch several events into one request.
- [x] Full `bs-checkout-smoke` passes — owner-confirmed.
- [x] All Tier 1 URLs from `AGENTS.md` pass — owner-confirmed.

Rollback immediately for any wrong line, quantity, total, duplicate event,
non-working button or new console error.

Cart correctness is the primary acceptance gate. Event presence is checked only
after line count, quantities and totals are confirmed unchanged.

## Production QA result

Owner confirmed all WP3b cart-first scenarios, GA4 event checks, console check,
Tier 1 URLs and the full checkout smoke as passing on 2026-09-07. No rollback
trigger was observed.
