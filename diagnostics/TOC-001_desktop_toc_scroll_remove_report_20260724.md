# Codex Report — TOC-001: remove desktop TOC inner scroll

Date: 2026-07-24

## Scope

Remove only `max-height: calc(100vh - 80px)` and `overflow-y: auto` from the active desktop `.bs-cp-page .bs-cp-toc` block in `catalog/view/stylesheet/boostershop-ds.css`. Sticky positioning and responsive/mobile TOC rules remain unchanged.

## Files touched

```
patches/TOC-001_desktop_toc_scroll_remove_20260724.php
diagnostics/TOC-001_desktop_toc_scroll_remove_report_20260724.md
```

## Dry-run result

Owner confirmed the active stylesheet path. The patch requires exactly one desktop TOC block containing both anchors and fails before writing if the source differs.

## php -l result

Pending local validation.

## Idempotency

On repeat, the patch prints `already_applied=yes` when the desktop selector remains but neither inner-scroll declaration exists.

## Rollback

Restore `_patch_backups/TOC-001_desktop_toc_scroll_remove_20260724-<timestamp>/catalog/view/stylesheet/boostershop-ds.css.bak` to `catalog/view/stylesheet/boostershop-ds.css`.

## Run command (owner)

```bash
cd ~/public_html || exit
php TOC-001_desktop_toc_scroll_remove_20260724.php
```

## Post-deploy QA checklist

- [ ] Desktop: TOC has no internal scrollbar at 1280px+.
- [ ] Desktop: TOC remains sticky while the article page scrolls.
- [ ] Mobile/tablet: TOC retains its existing responsive layout.
- [ ] Hover/focus/current-section states remain visible.

## Side effects / risks

Shared stylesheet, low-to-medium visual scope. The patch removes no selector, padding, color, sticky rule, or mobile media query.
