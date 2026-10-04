# Report — UX-003 stage 3: page grid fluid from 576 to 991px (runner 6)

Date: 2026-10-04 · Executor: Claude Code · Reviewer: Claude (chat), pending
Patch: `patches/UX-003_grid-fluid-991_20261004.php` · marker `UX-003-GRID` · ds.css `?v=ux003grid-20261004`
Handoff: `handoffs/handoff_UX-003_grid-fluid-991_claude-code_20261004.md` (+ INDEX). Build: `evidence/build/grid/`.

## Scope

All rules apply to `main > .container`, the page wrapper of every template directly inside `<main>`:

| Width | Change |
|---|---|
| 576–991 | `max-width: none`; side gutters 10 px up to 768, 32 px from 769 |
| 576–768 | the wrapper's top-level `.row` also gets `--bs-gutter-x: 20px` |
| <576 and ≥992 | unchanged |

- The gutter values are the header's own: `body.bs .bs-header`, measured at 10 / 32 px content edge.
- Why the extra gutter rule: with Bootstrap's −12 px row margins, R07MOB5's `#content { max-width: 100vw !important }` (≤768) clamped the column on one side. Content then sat 10 px from the left edge and 14 px from the right.
- Bootstrap files are not edited.

Two decisions within the handoff text:

- **<576 is unchanged** (12 px, as before), not moved to 10 px. Acceptance criterion 2 requires 390 to stay pixel-identical. The handoff title is «576–991».
- **Page wrappers with their own rules keep them**:
  - `#checkout-checkout.bs-co` stays 12 px at ≤640;
  - information pages are not `.container` at all (`bs-cp-page` with its own `bs-cp-wrap` gutters of 32 / 20 / 14 px);
  - `#common-home` is already fluid at ≤768.

## Files touched (SHA-256)

| File | Before (runner 5 output) | After |
|---|---|---|
| `catalog/view/stylesheet/boostershop-ds.css` | `e88e4155f14bc63624282908eab4a6f7ddfb3f54fc4e71d38d6cc08338245cc3` | `b921a1ebb9d4356bdc54d676c29da36a4af8e9b8db0449f8061ad7ef27ad2bcd` |
| `catalog/view/template/common/header.twig` | `21b8141efec2278d034b0774b7d30d341f6729f9fcc12da763ac6835b8dc7f19` | `f820b6d37ccd5c66376a00166234293c0d01eaa11791afc2563566f20675d505` |

## Gates

SHA guard ×2, marker, anchor, CSS balance and the Twig parse gate (header.twig) all passed. Repeat run `already_applied=yes`; self-delete yes. `php -l` and 8.0 compatibility scan: OK. Log: `evidence/logs/UX-003_grid-fluid-991_20261004.run1.log`.

## Measurements (`evidence/grid/`, widths 390–1440, before = runner 5 output)

Pages checked: category, product (buy bar), search with and without results, checkout, information, success (6 items), failure. Each value is the inner content edge (left / right, px) against the header's content edge.

| Width | Header | Before | After |
|---|---|---|---|
| 600 | 10 | 42 | **10** |
| 700 | 10 | 92 | **10** |
| 768 | 10 | 36 | **10** |
| 800 | 32 | 52 | **32** |
| 900 | 32 | 102 | **32** |
| 990 | 32 | 147 | **32** |

- Left and right edges match (±0) on every page except the two own-rule cases above.
- 390, 1000 and 1440: screenshots are **pixel-identical** before and after on all 8 pages.
- No horizontal scroll introduced (`scrollWidth` is the same before and after at every width).

## Pre-existing, not changed — needs an owner decision

**At ≈769–905 px the page scrolls horizontally on every page**, before and after this runner. It is identical in the untouched live2 base.

- Cause: the header's action row (Увійти + Telegram + the full cart label «0 товар(ів) - 0.00₴») is wider than the viewport there. Measured `scrollWidth` is 904 px; the product page with the buy bar reaches 926.
- This breaks acceptance criterion 3 for this band.
- It is header markup, outside this package's `.container` scope.
- Options: a header follow-up, for example icon-only ghost links or a short cart label between 769 and ~1000 px; or accept it as is.

## Rollback

Only while runner 7 is not applied:

```bash
B=$(ls -d _patch_backups/UX-003_grid-fluid-991_20261004-* | tail -1); (cd "$B" && find . -type f) | while read f; do cp "$B/$f" "$f"; done
```

Then refresh the theme cache and press Ctrl+F5.

## Owner QA (production)

Run `php UX-003_grid-fluid-991_20261004.php` in `~/public_html`, refresh the theme cache, press Ctrl+F5.

1. At 600, 700, 800, 900 and 990 px check: home, category, product, search, cart, checkout (no submit), an information page, a 404, success and failure. The content edges line up with the logo and the cart in the header. Home, cart and 404 have no local fixture; check them by eye.
2. 390 and ≥992 look as before.
3. Sliders, carousels and tables do not overflow. The known header overflow at ~769–905 is pre-existing; see above.
