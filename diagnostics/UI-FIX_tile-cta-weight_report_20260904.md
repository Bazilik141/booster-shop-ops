# Claude Code Report — UI-FIX: homepage tile CTA weight and size

Date: 2026-09-04
Patch: `patches/UI-FIX_tile-cta-weight_20260904.php`
Target: `.bs-cattile__cta` ("Дивитись усе") on the two homepage category tiles.
Status: **not run anywhere** — owner review first.

## Before / after

Current values read from the **deployed** stylesheet
(`https://boostershop.website/catalog/view/stylesheet/boostershop-ds.css`,
fetched 2026-09-04), not from patch history:

| Rule | Property | Before | After |
|---|---|---|---|
| base | `font-size` | 11.5px | **12.5px** |
| base | `font-weight` | **700** | **800** |
| base | `left` / `top` | 11px / 10px | unchanged |
| `@media (min-width:900px)` | `font-size` | 13.5px | **15px** |
| `@media (min-width:900px)` | `font-weight` | (inherits 700) | (inherits 800) |
| `@media (min-width:900px)` | `left` / `top` | 20px / 18px | unchanged |

Nothing else changes: colour, `letter-spacing`, the scrim heights, the tile
aspect ratios and every other declaration are byte-identical. The patch asserts
this — it counts `left:11px;top:10px` and `left:20px;top:18px` before and after
and fails if either count moves, and does the same for the scrim, both
aspect-ratio rules and the CTA colour line.

## On the weight — the requested range was already exhausted

The request asked for "a bolder weight (600-700 range)". **The deployed weight
is already 700**, the top of that range, so there is no bolder step inside it.
The only real increase is **800**.

That is safe here: the homepage loads
`family=Manrope:wght@400;500;600;700;800`, and `document.fonts.check('800 12px
Manrope')` returns `true` on production — a real 800 face, not a browser-
synthesised faux bold.

If 800 is more than wanted, the alternative is to leave the weight at 700 and
take only the size bump. Say so and it is a one-value edit.

## On the size — measured, and there is no overflow risk

The element is plain absolutely-positioned text. Computed style on production:
no background, no border, no padding, no border-radius — **there is no pill,
badge or arrow on this element**, so the only failure mode is the text running
past the tile edge. (The arrow lived on the old `.bs-catcard__more`
"Переглянути →", which patch 3 removed.)

Measured on the live page:

| Viewport | Tile width | Values | Text width | Clear to tile edge | Lines |
|---|---|---|---|---|---|
| 375px | 169px | 11.5px/700 (now) | 78.8px | 78.8px | 1 |
| 375px | 169px | **12.5px/800 (new)** | 86.9px | **70.6px** | 1 |
| 1920px | 636px | 13.5px/700 (now) | 92.5px | 523.5px | 1 |
| 1920px | 636px | **15px/800 (new)** | 104.3px | **511.7px** | 1 |

375px is the narrowest case in the requested range and still leaves ~70px of
clear space — the label could grow another 80% before touching the edge.
Nothing wraps at any width between 375px and 1920px, and the label stays inside
the scrim (36% of tile height on mobile, 34% on desktop).

Larger steps were measured too and also fit (13px/800 → 90.4px at 375px; 15.5px
/800 → 107.8px at 1920px), so 12.5/15 is a deliberately modest pick, not a
ceiling.

## Verification

- Dry run and real run executed against a copy of the **deployed** stylesheet,
  not a reconstruction: `verified=position_and_geometry_unchanged`,
  163524 → 163969 bytes.
- Diff is two declarations plus two comments; `left`/`top` byte-identical in
  both rules.
- Repeat run → `already_applied=yes`, self-deletes.
- Failure path restores the file from `_patch_backups/`.
- `php -l` clean; `scripts/check-php-host-compat.php` clean.
- Visual check on the live page at 800px: label is clearly heavier and slightly
  larger, in the same position.

## Blocking for this patch — the stylesheet is served stale

Found while verifying, and it applies to this patch as much as to the last one.

`catalog/view/template/common/header.twig:58` links the stylesheet as:

```
catalog/view/stylesheet/boostershop-ds.css?v=tech013-wp2-20260806
```

That token has not changed since 2026-08-06, while patches 2, 3 and the 16:9
patch have all edited this file since. Browsers holding a cached copy keep
serving the old CSS. Measured on production 2026-09-04 from inside the page:

```
fetch('...boostershop-ds.css?v=tech013-wp2-20260806')  → 162243 bytes, no aspect-ratio:16/9
fetch('...boostershop-ds.css?bust=<now>', {cache:'reload'}) → 162656 bytes, has aspect-ratio:16/9
```

The consequence is visible right now, not hypothetical: the 16:9 markup **is**
live (the browser loads `category-tile-pokemon-wide-800.webp`, and the
derivatives are on the server at the right sizes — 800×450 / 70 982 B and
1280×720 / 132 592 B, close to the estimates in that patch's report), but the
CSS that makes the tile 16:9 is not applied for a cached browser. Computed
`aspect-ratio` on `.bs-cattile` is still `1 / 1`, so the wide artwork is being
cropped top and bottom inside a square box.

**This patch will be equally invisible to those visitors.** Bumping the `?v=`
token in `header.twig` is a one-line change; it is deliberately not in this
patch, which was scoped to weight and size only. It needs its own small patch —
say the word and it takes a few minutes.

## Post-deploy QA

- [ ] Hard-reload the homepage (Ctrl+F5) before judging anything — otherwise you
      are looking at the cached stylesheet.
- [ ] Desktop: "Дивитись усе" is visibly heavier and slightly larger, in the
      same top-left position on both tiles.
- [ ] ~375–390px: label on one line, comfortably clear of the tile's right edge.
- [ ] The label still sits inside the dark scrim at both breakpoints.
- [ ] Nothing else on the tiles moved.
