# Claude Code Report — UI-FIX: tile CTA pill + arrow

Date: 2026-09-04
Patch: `patches/UI-FIX_tile-cta-pill_20260904.php`
Target: `.bs-cattile__cta` ("Дивитись усе") on the two homepage category tiles.
Status: **not run anywhere** — owner review first.

## Deployed state, re-confirmed before writing

Fetched `boostershop-ds.css` from production 2026-09-04. The page now links it as
`?v=uifix-tiles-20260904`, so both previous rounds are live:

| | Deployed now |
|---|---|
| base | `left:11px; top:10px; font-size:12.5px; font-weight:800; color:#fff; letter-spacing:.01em` |
| `@media (min-width:900px)` | `left:20px; top:18px; font-size:15px` |
| background / padding / radius / arrow | none |

The 12.5px/800 and 15px values from the last patch are current, as expected.

## Before / after

| Rule | Property | Before | After |
|---|---|---|---|
| base | `background` | — | **`var(--bs-paper,#fff)`** |
| base | `padding` | — | **`5px 10px`** |
| base | `border-radius` | — | **`var(--bs-r-pill,999px)`** |
| base | `color` | `#fff` | **`var(--bs-ink,#111827)`** |
| base | `left` / `top` | 11px / 10px | unchanged |
| base | `font-size` / `font-weight` | 12.5px / 800 | unchanged |
| ≥900px | `padding` | — | **`6px 12px`** |
| ≥900px | `left` / `top` / `font-size` | 20px / 18px / 15px | unchanged |
| new rule | `::after` | — | **`content:"→"; margin-left:5px`** |

## Two calls I made, both worth a look

**The colour had to change.** The request listed background, radius and padding
only, but the label is `color:#fff` today — white text on a white pill is
invisible. It moves to the DS ink. If a dark pill with white text was the intent
instead, that is the same two values swapped.

**The arrow is a `::after`, not markup.** The alternative was editing the two
`<span class="bs-cattile__cta">` in `home.twig`. The pseudo-element keeps the
change to one file and leaves the `<picture>` markup alone — the smaller and
safer diff, as invited. The link's accessible name is unaffected either way: it
comes from the `aria-label` on the `<a>`, not from this text.

## Where the text ends up

Worth knowing before you look at it. `left`/`top` stay fixed, as instructed, so
the pill's top-left corner sits exactly where the bare text's top-left was — and
the glyphs therefore move inward by the padding:

| | Text shifts |
|---|---|
| mobile | 10px right, 5px down |
| desktop | 12px right, 6px down |

The pill itself has not moved. If the intent was to pin the *text* where it is
rather than the box, subtract the padding from `left`/`top` — two values, say the
word.

## Width budget — measured on production

Tile is 169px wide at a 375px viewport, 636px at 1920px.

| Viewport | | Box width | Clear to tile edge | Lines |
|---|---|---|---|---|
| 375px | before | 86.9px | 70.6px | 1 |
| 375px | **after** | **122.8px** | **34.7px** | 1 |
| 1920px | before | 104.3px | 511.7px | 1 |
| 1920px | **after** | **147.4px** | **468.6px** | 1 |

Nothing wraps or reaches the edge. Roomier paddings were measured too and still
fit — `6px 12px` on mobile leaves 29.7px clear — so `5px 10px` / `6px 12px` is a
comfortable pick, not the maximum.

The pill also stays inside the scrim at both breakpoints: 10 + 31 = 41px against
a 61px scrim on mobile; 18 + 38 = 56px against 122px on desktop.

## Verification

- Dry run and real run against a copy of the **deployed** stylesheet:
  `verified=position_type_and_geometry_unchanged`, 163969 → 164615 bytes.
- Pre-check refuses to run unless the previous round's values
  (`12.5px/800`, `15px`) are present, so this cannot be applied to a stylesheet
  that skipped it.
- Assertions: `left:11px;top:10px` and `left:20px;top:18px` counts unchanged;
  font-size/weight unchanged; both scrim rules, both aspect-ratio rules and the
  `.bs-cattiles` grid unchanged; the old `color:#fff` line gone.
- Diff is two rules plus one new `::after` rule — nothing else in the file.
- Repeat run → `already_applied=yes`, self-deletes.
- Failure path restores the file from `_patch_backups/`.
- `php -l` clean; `scripts/check-php-host-compat.php` clean.
- Rendered live with the proposed values injected: white pill, dark label,
  trailing arrow, same corner — reads as a proper CTA badge.

## Cache note

This edits `boostershop-ds.css` again, so the `?v=uifix-tiles-20260904` token
bumped earlier today is stale for anyone who has cached the file since that bump.
Deliberately not touched here — this patch is scoped to one element and the token
lives in `header.twig`.

Two options, owner's call:

- hard-reload is enough for QA now, and the next visitor-facing CSS change gets a
  fresh token anyway;
- or bump the token again in the same deploy — a copy of
  `UI-FIX_ds-css-cache-bust_20260904.php` with a new value, a few minutes.

Worth noting the pattern: every CSS patch needs a token bump to reach returning
visitors. If this keeps recurring, the durable fix is deriving the token from the
file's mtime in `header.twig` instead of a hardcoded string — a separate task,
not this one.

## Post-deploy QA

- [ ] Hard-reload (Ctrl+F5) first.
- [ ] Desktop: white pill with dark "Дивитись усе →" in the tile's top-left, on
      both tiles, same corner as before.
- [ ] ~375–390px: pill on one line, clear of the tile's right edge.
- [ ] Pill sits inside the dark scrim at both breakpoints.
- [ ] Arrow renders as → and not as a missing glyph.
- [ ] Tile geometry unchanged: 16:9 on desktop, 1:1 below 900px.
