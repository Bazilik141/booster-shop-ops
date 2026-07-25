# Codex Report — TOC-002: fix active content-pages TOC scroll

Date: 2026-07-24

## Scope

The live MHTML snapshot proves that `catalog/view/stylesheet/content-pages.css` is the active source of the desktop TOC scroll. Remove only `max-height: calc(-80px + 100vh)` / equivalent and `overflow-y: auto` from its base `.bs-cp-toc` block. `boostershop-ds.css` and all responsive rules remain untouched.

## Files touched

```
patches/TOC-002_content_pages_desktop_scroll_remove_20260724.php
diagnostics/TOC-002_content_pages_desktop_scroll_remove_report_20260724.md
```

## Dry-run result

The MHTML snapshot identifies the exact active stylesheet and source rule. The patch accepts either valid operand order for the `calc()` expression and requires both anchors in exactly one base TOC block before any write.

## php -l result

Pending local validation.

## Idempotency

Repeat after success returns `already_applied=yes` when no base `.bs-cp-toc` block has both inner-scroll anchors.

## Rollback

Restore `_patch_backups/TOC-002_content_pages_desktop_scroll_remove_20260724-<timestamp>/catalog/view/stylesheet/content-pages.css.bak`.

## Run command (owner)

```bash
cd ~/public_html || exit
php TOC-002_content_pages_desktop_scroll_remove_20260724.php
```

## Post-deploy QA checklist

- [ ] Desktop at 1280px+: no TOC internal scrollbar.
- [ ] Desktop: TOC stays sticky while page content scrolls.
- [ ] Mobile/tablet: existing TOC behavior is unchanged.
- [ ] Hard-refresh the browser because the static CSS asset uses a fixed query version.

## Side effects / risks

One CSS declaration pair only; no DB, checkout, navigation, SEO, or mobile CSS change.
