# Codex Report — CAT-004 variant cosmetics

Date: 2026-09-23

## Scope

Prepared `patches/CAT-004_variant-cosmetics_20260923.php` from the owner's
`CAT-004_ui_sources_20260923.tar.gz`. It removes the repeated selected value
beside the variant axis label and changes the existing `.bs-variant` margin from
`20px 0` to `0 0 20px`. The live stylesheet gives `.bs-product__info` a 13px
row gap; the extra 20px top margin caused the oversized price-to-selector gap.
The active chip markup, hover/focus states, links, family data, and all
non-family products remain untouched. Previous selector/family patch blocks
were checked before editing the existing source rule.

## Files touched

The runner changes only these production files, after exact-anchor checks and
backups: `catalog/view/stylesheet/boostershop-ds.css`,
`catalog/view/template/product/product.twig`, and
`catalog/view/template/common/header.twig` (read-and-replace CSS cache token).
No database or PHP target changes. No commit, push, or deployment occurred.

## Local verification

- `php -l patches/CAT-004_variant-cosmetics_20260923.php`: no syntax errors.
- Fresh-source fixture: `done=ok`, three backups, three changed files,
  `self_delete=ok`.
- Both patch orders (variant cosmetics first and info reflow first) finished
  successfully; final CSS and Twig hashes match across orders.
- Repeat fixture run: `already_applied=yes`.
- Local browser fixture at 1280, 1024, 768, and 390px: 13px price-to-selector
  gap, label contains only `Колір`, active chip retains its state, and no
  horizontal overflow. This is a static fixture check, not production QA.

## Rollback and risk

Restore all three files from
`_patch_backups/CAT-004_variant-cosmetics_20260923-<timestamp>/` and clear the
OpenCart theme cache. Risk is low and limited to variant-family product pages.
The runner fails before any write if source anchors drift or the cache token is
missing or malformed.
The handoff recommends Claude Code; Codex authored this patch after the owner
provided the requested live files and directed this task to continue.

## Owner gate

After Claude review, upload the runner to `~/public_html` and execute it with
the cache-clear command in one terminal block. Check the yellow album and an
ordinary product at desktop, tablet, and mobile widths. Confirm the chip still
navigates and highlights the current variant. Run the usual Tier 1 smoke URLs.

```bash
cd ~/public_html || exit
php CAT-004_variant-cosmetics_20260923.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```
