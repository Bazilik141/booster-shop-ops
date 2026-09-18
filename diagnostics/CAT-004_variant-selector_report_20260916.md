# Codex Report — CAT-004: master/variant selector

Date: 2026-09-16

## Review target

**Executor:** Codex · model=Sol · effort=xhigh

Please review the patch only; it has not been uploaded, deployed, committed, or pushed.

Patch: `patches/CAT-004_variant-selector_20260916.php`

## Context and scope

The owner requires an ordinary page-navigation selector for every member of an
OpenCart 4 master/variant family. It must render on both the master product and
children; the value represented by the currently open product must have the
`is-active` class. It must not use AJAX, `pushState`, or swap price/gallery/cart
state in place.

Implemented scope matches `handoffs/handoff_CAT-004_variant-selector_20260916.md` §4:

1. A read-only `getVariantFamily(int $master_id)` model method, using the same
   store/language/status/date-visible query shape, JSON decoding, discount and
   special expressions as `getProduct()`.
2. Controller construction of the specified `variant_groups` shape after the
   existing generic-options loop.
3. Inline Twig markup before, and outside, `<form id="form-product">`.
4. `bs-variant*` CSS in `catalog/view/stylesheet/boostershop-ds.css` and the
   mandatory header cache-bust bump.

No database writes are in the patch. It does not touch canonical, JSON-LD,
`aggregateRating`, the generic `{% if options %}` block, the add-to-cart form or
hidden product ID, category/listing code, feed, sitemap, robots, checkout,
payment, redirects, or product data.

## Source evidence

The owner supplied `booster-debug-CAT-004.tar.gz` from production. It contains
the five target files below. In that export:

- `catalog/model/catalog/product.php:getProduct()` decodes `variant` with
  `json_decode(..., true)` and resolves `discount` / `special` via the model's
  existing SQL expressions.
- `catalog/controller/product/product.php` resolves `master_id`, loads the
  master options, and excludes variant-defining generic options.
- `catalog/view/stylesheet/boostershop-ds.css` is the real CSS path; there was
  no prior `bs-variant` selector in it or in `patches/`.
- `catalog/view/template/common/header.twig` currently serves that CSS as
  `?v=uifix-tiles-20260904`.

The owner also read the live family relation. The master (`product_id=75`) has
empty `variant`; child `product_id=182` has `variant={"236":"34"}`. This is
native OpenCart behaviour: a master has no direct option-value identity.

### Master identity rule added by this patch

When the master has no `variant` JSON, the controller enumerates the cartesian
product of the master option values. If exactly one complete combination is not
claimed by a visible child variant, it assigns that combination to the master
in memory for selector rendering only. It never writes the inferred value to
the database.

This preserves the owner's requirement that the master also has an active
selector chip without guessing. For a valid one-dimensional family, this means
master plus child variants cover every option value once. For a two-dimensional
family, they cover every size × colour combination once. If the family has
multiple unclaimed combinations, the patch intentionally does not invent an
active master choice; this is a catalogue-data QA failure, not a code fallback.

## Files touched on production by the runner

```
catalog/model/catalog/product.php
catalog/controller/product/product.php
catalog/view/template/product/product.twig
catalog/view/stylesheet/boostershop-ds.css
catalog/view/template/common/header.twig
```

The repository receives one new untracked deliverable only:

```
patches/CAT-004_variant-selector_20260916.php
```

## Local validation

The runner was executed against a clean extraction of the supplied five-file
production export. Key output:

```
backup=.../_patch_backups/CAT-004_variant-selector_20260916_135812
changed=catalog/model/catalog/product.php
changed=catalog/controller/product/product.php
changed=catalog/view/template/product/product.twig
changed=catalog/view/stylesheet/boostershop-ds.css
changed=catalog/view/template/common/header.twig
php_l_product.php=ok
php_l_product.php=ok
done=ok
self_delete=ok
```

Additional fixture checks:

- patch self-syntax: `No syntax errors detected in patches/CAT-004_variant-selector_20260916.php`;
- repeat run: `already_applied=yes`, then self-delete;
- forced CSS-cache-bust anchor drift: fails before backup or write with
  `ERROR=anchor_count_invalid:header_css_cache_bust`;
- forced PHP syntax failure after writes: reports `restore=attempted`; all five
  fixture files returned to their original state;
- Product JSON-LD script was byte-identical before/after;
- the controller canonical statement was unchanged;
- the Twig substring from `<form id="form-product">` through EOF was
  byte-identical before/after;
- header differs only by the CSS version query string;
- the added CSS block contains no `!important`.

No live browser, database, deployed theme override, canonical/JSON-LD fetch,
or owner QA has occurred.

## Review requests for Claude

1. Confirm the model query preserves OpenCart 4.1 store, language, visible
   status/date, discount, special and ordering semantics.
2. Check the master-combination inference: it must never make a false
   `is_current` claim when more than one full combination is missing.
3. Verify all `variant_groups` rules: master option order, value→product mapping,
   unavailable `span` with no href, out-of-stock sibling link, current chip,
   formatted tax/currency price, and per-group price suppression.
4. Confirm no forbidden area in handoff §5 is modified by the generated patch.
5. Review the Twig output for valid markup/accessibility and the CSS at desktop,
   tablet, 390px, hover, focus-visible, active, unavailable and long-value
   states. Ensure no later selector can override `bs-variant*` unexpectedly.
6. Confirm production-run safety: five target checks, one anchor each, backups
   before writes, lint + restore, idempotence marker, and self-delete.

## Rollback

The runner makes a timestamped file backup before writes:

```
_patch_backups/CAT-004_variant-selector_<UTC timestamp>/
```

Restore the five files from their matching relative paths in that directory,
then clear OpenCart cache. No database rollback exists because the runner does
not mutate the database.

## Owner deployment and QA gate

Before production run:

1. Download a database backup (the patch does not change it, but CAT-004 is
   inside the high-risk SEO/schema gate).
2. Capture canonical, Product JSON-LD, and sitemap evidence per handoff §8.
3. Ensure the test family has exactly one master combination not represented by
   a visible child variant.

After owner-approved deployment, confirm:

- [ ] ordinary product has no selector and no visual regression;
- [ ] master and child both show selector; each open page marks its own value
      active;
- [ ] a live sibling chip is an SEO URL link; no in-place exchange occurs;
- [ ] unavailable value is a non-link `span.is-off`;
- [ ] equal sibling prices render no `.bs-variant__price`; differing prices do;
- [ ] canonical and Product JSON-LD before/after are unchanged;
- [ ] add-to-cart from a child retains that child product ID;
- [ ] 390px has wrapping chips, >=44px target height, no horizontal scroll.

## Run command

```bash
cd ~/public_html || exit
php CAT-004_variant-selector_20260916.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```
