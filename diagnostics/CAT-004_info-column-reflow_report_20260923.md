# Codex Report — CAT-004 product info column reflow

Date: 2026-09-23

## Scope

Prepared `patches/CAT-004_info-column-reflow_20260923.php` from the owner's
`CAT-004_ui_sources_20260923.tar.gz`. The live CSS makes `.bs-pp-meta` a
vertical row stack and product trust items a column with icons above text.
The runner adds explicit modifier classes to the existing manufacturer, stock,
and conditional ETA rows; a desktop grid places stock left, manufacturer
right, and ETA in the second row on the left. At widths below 1024px, the meta
stack stays as it was. Product trust icons move left of text; at mobile/tablet
widths they align with the first line when text wraps.

## Shared-component check

The owner's `CAT-004_trust-uses_20260923.txt` and exported Twig identify two
renderers: `catalog/view/template/common/home.twig` and
`catalog/view/template/product/product.twig`. The old
`cat002_5c_mobile_visual_breadcrumb_20260630.php` touches
`#content > .bs-trust-strip`. This runner changes only
`.bs-product__info .bs-trust-strip__item` and product meta selectors. It does
not change the homepage markup or its `#content >` CSS rules.

## Files touched

The runner changes only these production files, after exact-anchor checks and
backups: `catalog/view/stylesheet/boostershop-ds.css`,
`catalog/view/template/product/product.twig`, and
`catalog/view/template/common/header.twig` (read-and-replace CSS cache token).
No database or PHP target changes. No commit, push, or deployment occurred.

## Local verification

- `php -l patches/CAT-004_info-column-reflow_20260923.php`: no syntax errors.
- Fresh-source fixture: `done=ok`, three backups, three changed files,
  `self_delete=ok`.
- Both patch orders finished successfully; final CSS and Twig hashes match
  across orders. Repeat run returned `already_applied=yes`.
- Read-only live browser check of the owner-provided OP-07 product confirmed
  the pre-order status and existing `Доставка / орієнтовно 3–4 тижні` row.
- Local browser fixture at 1280, 1024, 768, and 390px: desktop stock and
  manufacturer share row one, ETA occupies row two left; narrower widths stay
  stacked. Product trust items use icon-left text, with the icon aligned to
  the first text line at 768/390px. No horizontal overflow was observed. At
  1024px, the desktop meta rows measured at least 44px high. A deliberately
  long, unbroken manufacturer name wrapped inside the row at 390px; a long
  trust item also stayed within its cell. This is a static fixture check, not
  production QA.

## Rollback and risk

Restore all three files from
`_patch_backups/CAT-004_info-column-reflow_20260923-<timestamp>/` and clear
the OpenCart theme cache. Risk is medium because the shared stylesheet serves
every product page. Home trust rules were deliberately left intact. The runner
fails before any write if source anchors drift or the cache token is missing or
malformed. The handoff recommends Claude Code; Codex authored this patch after
the owner provided the requested live files and directed this task to continue.

## Owner gate

After Claude review, upload the runner to `~/public_html` and execute it with
the cache-clear command in one terminal block. Check the yellow album, OP-07,
an ordinary in-stock product, and the homepage at desktop, tablet, and mobile
widths. Confirm text wrapping, hover/focus, no horizontal scroll, and the
position of the description tabs. Run the usual Tier 1 smoke URLs.

```bash
cd ~/public_html || exit
php CAT-004_info-column-reflow_20260923.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```
