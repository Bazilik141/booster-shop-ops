# Claude Code Report — UI-FIX post-deploy: homepage tiles 16:9 on desktop

Date: 2026-09-04
Handoff: `handoffs/handoff_UI-FIX_postdeploy-tile-size-desktop_20260904.md`
Patch: `patches/UI-FIX_home-tiles-wide-desktop_20260904.php`
Follows `UI-FIX_home-category-tiles_20260903.php` (patch 3 of UX-036), deployed
and self-deleted.

## Option (a), option (b), or neither

**Neither.** The handoff's premise — tiles "grow unbounded with the viewport" —
does not hold. Measured on production 2026-09-04:

| Viewport | `.container` | Tile |
|---|---|---|
| 1920px | 1320px | 636×636 |
| 2560px | **1320px** | **636×636** |

Bootstrap's `.container` already caps the row at 1320px, and every sibling block
on the homepage is the same 1296px wide (trust strip, hero, subtiles, product
grid, SEO panel). So:

- **(a)** a `max-width` above ~1200–1400px would be a no-op at 1320px, or, if
  set lower, would make the tiles the only block on the page narrower than
  everything around them;
- **(b)** extra columns make no sense with two categories.

The complaint is proportion, not width: a 636px-tall square is too tall. The
owner's 2026-09-04 decision — desktop moves from 1:1 to 16:9, with new artwork
already uploaded — fixes exactly that. At the same 636px column the tile becomes
636×358, in proportion with the hero above it. **No width cap is added**;
stacking one on top of the aspect change would be a second, redundant
constraint.

## Scope deviation, stated plainly

The handoff's guardrail said "CSS-only fix … do not touch the tile
markup/images". That guardrail predates the owner's 16:9 decision and assumed a
width cap. Serving different artwork above 900px cannot be done from CSS with a
`<picture>` — it needs a media-scoped `<source>`. So the markup does change.

Nothing else does: hrefs, tile order, `alt`, `aria-label`,
`loading`/`fetchpriority`, the existing square `<source>`s and the square `<img>`
fallback are byte-unchanged, and the mobile/tablet two-column 1:1 behaviour is
untouched.

Breakpoint: the existing **900px** the component already uses, rather than a
second one. Portrait tablets (768px) therefore stay square; landscape (≥900px)
gets 16:9.

## The artwork filenames — why the patch discovers them

The owner uploaded the 16:9 files to `image/catalog/tiles/` under names not
recorded anywhere I can read. The directory has no autoindex (403), the four
square files from patch 3 are untouched (byte-identical to what patch 3 wrote),
and 432 probed name permutations returned nothing.

So the patch lists the directory **on the server**, prints every image it finds
with dimensions and ratio, and picks the one non-square file per category whose
name contains a category token (`pokemon` / `onepiece`, `one-piece`, `one piece`,
`pkm`, `op-`) and whose ratio is 1.60–1.90. Anything other than exactly one match
per category aborts with the listing.

That listing prints on every run including `--dry-run`, so **run `--dry-run`
first and read it** — it is the only view anyone in this chain has of what is
actually on disk. If discovery picks wrong, the listing gives the real filenames
and pinning them is a one-line follow-up.

From whatever it finds it writes deterministic derivatives, so the markup never
depends on the owner's filenames:

```
category-tile-pokemon-wide-1600.webp    1600x900
category-tile-pokemon-wide-800.webp      800x450
category-tile-onepiece-wide-1600.webp   1600x900
category-tile-onepiece-wide-800.webp     800x450
```

Sources are centre-cropped to exactly 16:9 before scaling, so a source a few
pixels off (1686×948 is 1.7785, not 1.7778) is never stretched. Originals are
read-only. Budget is patch 3's rule: q80, dropping to q72 over 160 KB.

## Verification

Fixture: the real deployed state, rebuilt by running patch 2 then patch 3 on the
2026-08-28 backup copies, plus two simulated owner uploads with deliberately
awkward names and mixed formats — `Pokemon TCG tile 16x9 FINAL.png` (1686×948)
and `One Piece Card Game tile 169.jpg` (1920×1080).

Discovery picked both correctly and ignored all four square files:

```
wide_source[pokemon]=Pokemon TCG tile 16x9 FINAL.png 1686x948 ratio=1.778
wide_source[onepiece]=One Piece Card Game tile 169.jpg 1920x1080 ratio=1.778
```

Geometry, measured on the live page with the new CSS injected:

| Viewport | Tile | Ratio | Section height | Scrim | CTA inside scrim |
|---|---|---|---|---|---|
| 1920 | 636×358 | 1.778 | 358 (was 636) | 122px | yes |
| 1320 | 546×307 | 1.778 | 307 | 104px | yes |
| 1000 | 456×257 | 1.778 | 257 | 87px | yes |
| 900 | 336×189 | 1.778 | 189 | 64px | yes |
| **899** | **341×341** | **1.000** | 341 | 123px | yes |
| 768 | 358×358 | 1.000 | 358 | 129px | yes |
| 390 | 176×176 | 1.000 | 176 | 63px | yes |

The 900/899 pair is the point: at and above 900px the tiles are 16:9, one pixel
below they are exactly as deployed today.

Other checks:

- Patch-time assertions: two `media="(min-width:900px)"` sources; the desktop
  `<source>` precedes the square one in each `<picture>` (otherwise the browser
  would never reach it) — printed as
  `verified=desktop_source_precedes_square`; `aspect-ratio:16/9` once and
  `aspect-ratio:1/1` still present; **`max-width` occurrence count in
  `boostershop-ds.css` unchanged**, which is the machine-checkable form of "no
  width cap added"; hrefs, square sources, the LCP hint, `.bs-subtiles` (13
  occurrences) and the `<h1>` all unchanged.
- Line endings preserved per file, tested both ways: LF in → LF out, and with
  the fixture forced to CRLF, CRLF in → CRLF out.
- Repeat run → `already_applied=yes`, self-deletes.
- Failure path restores both files from the backup and deletes any derivative it
  had already written.
- `php -l` clean; `scripts/check-php-host-compat.php` clean.

## Run command (owner, from `~/public_html`)

```bash
php -l UI-FIX_home-tiles-wide-desktop_20260904.php
```

```bash
php UI-FIX_home-tiles-wide-desktop_20260904.php --dry-run
```

Read the `tile_dir_listing` and `wide_source[...]` lines before continuing — they
say which files it will use.

```bash
php UI-FIX_home-tiles-wide-desktop_20260904.php
```

## Post-deploy QA

- [ ] Desktop (~1320px and wider): both tiles are wide 16:9 banners, roughly
      636×358, showing the **new** artwork, not the square art cropped.
- [ ] "Дивитись усе" is legible in the top-left of each tile and sits inside the
      dark scrim.
- [ ] Both tiles link correctly — `/catalog/Pokemon`, `/catalog/One-Piece`.
- [ ] Hover still zooms the artwork ~4% with no overflow.
- [ ] ~390px and tablet portrait (768px): unchanged — two square tiles, old
      artwork.
- [ ] View-source: the desktop `<source>` appears before the square one, and the
      `.webp` derivatives load (DevTools → Network shows `*-wide-1600.webp` on
      desktop, `*-540/1080.webp` on mobile).
- [ ] Homepage LCP not regressed — the Pokémon tile keeps
      `loading="eager" fetchpriority="high"`.

## Risks

- **I have never seen the uploaded files.** No server access, no autoindex, and
  the newest backup predates them. Everything about them in this patch comes
  from what it reads on disk at run time, which is why the dry-run listing
  exists. If either file is not ~16:9, or its name carries no category token,
  the patch aborts and prints the directory rather than guessing.
- Derivative sizes in the local test are tiny only because the simulated sources
  were flat colour. Real artwork will be larger; the q80→q72 rule and the 160 KB
  ceiling are the same ones that already fired correctly on the Pokémon square
  tile in patch 3.
- The old square derivatives stay on disk and are still served below 900px —
  intentional, not leftovers.
- `.bs-cattile__scrim` stays at 34% of the box, so on desktop it is now ~122px
  rather than ~216px. The CTA still sits fully inside it at every width in the
  table above, but it is a visual change worth a glance during QA.

---

# Addendum — quality ladder, and the budget decision that is not mine

Date: 2026-09-04
Trigger: the patch failed safely on production —
`category-tile-onepiece-wide-1600.webp` came out **202 924 B at q72**, over the
163 840 B (160 KB) ceiling inherited from patch 3.

## Changed

`QUALITY_LADDER = [80, 72, 62]` — one more step, as requested. Each lower step is
tried only if the previous is over budget, every attempt is reported on the run
line, and if the last step is still over, the patch prints the whole ladder,
the source, the target size, the ceiling and the overshoot, then stops:

```
tile=category-tile-onepiece-wide-1600.webp 1600x900 q62 ...B  [q80=... q72=... q62=...]
ERROR=tile_over_budget=category-tile-onepiece-wide-1600.webp
  source: <name> <w>x<h>
  ladder: q80=...B  q72=...B  q62=...B
  ceiling: 163840B (160 KB)
  over by: ...B at the lowest step (q62)
```

Both paths were exercised locally: the pass path (ladder stops at the first
step that fits) and the fail path (all three steps reported, both files and any
already-written derivative rolled back).

`MAX_BYTES` is untouched.

## q62 is visually fine — and will still not fit

**Visual.** Measured on the owner's real One Piece artwork (the 1254×1254 master
from patch 3, centre-cropped to 16:9), compared at 1:1 pixels on the busiest
region — ship rigging, wood grain, dark water. q72 → q62 gives a barely
perceptible softening of the finest wood grain and rope; no blocking, no
banding, no ringing. The tile renders at 636 CSS px, so the 1600px image is
downscaled ~2.5× in the browser, which hides even that. q62 is safe for this
kind of illustration.

**Arithmetic.** That is the problem — q62 barely saves anything on this content:

| target | q80 | q72 | q62 |
|---|---|---|---|
| 1600×900 | 174 266 | 133 730 | 117 248 |
| 1400×788 | 145 614 | 109 980 | 96 510 |
| 1280×720 | 126 850 | 98 030 | 84 636 |
| 1200×675 | 116 166 | 89 442 | 77 388 |

q62/q72 = **0.875** — a 12.5% saving. Scaling the real file by its known
q72 value (202 924 / 133 730 = 1.517):

| target | q72 (est.) | q62 (est.) |
|---|---|---|
| **1600×900** | **202 924** (measured) | **~177 900 — still over by ~14 KB** |
| 1400×788 | ~166 900 | ~146 400 |
| **1280×720** | **~148 800 — fits** | ~128 400 |

So the new step will most likely *not* rescue the 1600 variant. The run will
report the real numbers either way.

## The actual recommendation — 1280, not a higher ceiling

The tile is never wider than **636 CSS px** (measured: 1320px Bootstrap
container, two columns, 24px gap). At 2× DPR that needs **1272 px**. The 1600
variant was my own choice in the first version of this patch and is
over-provisioned by 25% linear / 56% area for no visible benefit at any DPR the
layout can reach.

Dropping the wide variant to **1280×720** lands at ~148 KB at q72 — inside the
existing ceiling, with the existing ladder, at full quality for the size it is
actually displayed. It is a one-line change: `WIDE_W`/`WIDE_H` near the top of
the patch (the `srcset` widths and derivative filenames follow from those
constants automatically).

The alternative is raising `MAX_BYTES` for the wide tiles to ~200 KB. That works
too, but it buys pixels the display never uses and makes the desktop LCP image
~35% heavier. **That is the owner's call, not mine — this patch does not raise
the ceiling on its own.**

Not recommended: shrinking below 1280 (loses 2× DPR coverage), or pushing
quality under q62 (the curve is flat there — q60 saves only another 3%).

---

# Addendum 2 — wide tier moved to 1280×720

Date: 2026-09-04. Owner-approved after the measurements in addendum 1.

## Changed

```php
const WIDE_W = 1280;   // was 1600
const WIDE_H = 720;    // was 900
const NARROW_W = 800;  // unchanged
const NARROW_H = 450;  // unchanged
```

`MAX_BYTES` (163 840) and `QUALITY_LADDER` ([80, 72, 62]) are untouched.

**NARROW stays 800×450.** It is already exactly 16:9 and it is the 1× tier: the
tile is never wider than 636 CSS px, so 800 covers DPR 1 with headroom. Scaling
it down alongside the wide tier (to 640×360) would leave none and would add a
non-standard tier for no gain. With the pair 800w/1280w the browser resolves
`sizes="(min-width:900px) 636px"` to 800w at DPR 1 and 1280w at DPR 2 — which is
the whole point of moving to 1280.

Derivative filenames follow the constants, so the markup now reads
`category-tile-<slug>-wide-1280.webp` with no separate edit; verified in the
generated `home.twig`.

## Actual bytes

Local run, real run not dry-run, sources = the owner's own square masters from
patch 3 centre-cropped to 16:9 and rendered at 1920×1080 so the patch performs a
genuine downscale to 1280:

| Derivative | Size | Ladder | Result |
|---|---|---|---|
| `category-tile-pokemon-wide-1280.webp` | **133 978 B** | `q80=133978` | fits at q80 |
| `category-tile-pokemon-wide-800.webp` | **68 606 B** | `q80=68606` | fits at q80 |
| `category-tile-onepiece-wide-1280.webp` | **120 806 B** | `q80=120806` | fits at q80 |
| `category-tile-onepiece-wide-800.webp` | **62 492 B** | `q80=62492` | fits at q80 |

Every file clears the ceiling **at q80** — the ladder never steps down.

## What that means for the real artwork

Those numbers are from proxy sources: the owner's *square* masters, not the
actual 16:9 uploads, which are different and heavier illustrations. Absolute
proxy bytes do not transfer; the size-vs-width ratios do, and the real One Piece
file gives an exact calibration point — production reported
**202 924 B at q72@1600**.

Same-pipeline proxy ratios: 1280/1600 = 0.733 at q72, 0.728 at q80; q80/q72 =
1.303 at 1600.

| Real One Piece file | Estimate | vs 163 840 ceiling |
|---|---|---|
| q80 @ 1600 | ~264 400 B | over (it was — the run fell through to q72) |
| q72 @ 1600 | **202 924 B measured** | over by 39 KB → this is the failure the owner hit |
| q80 @ 1280 | ~192 500 B | over |
| **q72 @ 1280** | **~148 800 B** | **fits, ~15 KB headroom** |
| q62 @ 1280 | ~128 400 B | fits |

So on production the One Piece wide tile should land at **q72, ≈149 KB**, one
ladder step, inside budget. Pokémon needs no estimate: the failing run processed
`pokemon` first and got past it at 1600, so its 1600 derivative was already
≤163 840 B; at 1280 it is ~27% smaller again and will clear at q80.

If reality differs, the run prints the full ladder per file and stops rather
than writing anything — that behaviour is unchanged.

## Re-verification after the change

- `php -l` clean; `scripts/check-php-host-compat.php` clean.
- Dry run lists the four `-wide-1280` / `-wide-800` derivatives and both source
  picks; `verified=desktop_source_precedes_square` still prints.
- Real run: all four written at q80, both files written, `readback=ok`,
  `done=ok`.
- Generated `home.twig` references `category-tile-<slug>-wide-1280.webp` in
  `srcset` with `800w` and `1280w` descriptors — confirmed in the diff.
- Repeat run → `already_applied=yes`, self-deletes.
- The CSS half of the patch is unaffected by the constants; the breakpoint
  geometry table in the main report still stands (tiles 16:9 at ≥900px, 1:1 at
  899px and below).

Not yet run against production — owner review first, then `--dry-run`.
