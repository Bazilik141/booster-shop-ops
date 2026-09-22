# Handoff — UI-PCARD-D, fix round 2: catalog product tile "без коробки"

Date: 2026-09-19
Executor: Claude Code (same executor as round 1)
Supersedes: `CLAUDE CODE - картка товару D без коробки_20260919.md` (round 1)
Review that produced this: `diagnostics/UI-PCARD-D_no-box-card_review_20260919.md`

---

## 0. Read this first — what changed since round 1

**Round 1 was never deployed.** Verified on production 2026-09-19: `.bs-pcard`
still computes `background: rgb(255,255,255)`, `border-top-width: 1px`,
`border-radius: 10px`; the live stylesheet token is
`boostershop-ds.css?v=cat004-variant-20260918`; no `UI-PCARD-D` marker is
present. **Anchor on the original live CSS, not on round-1 output.**

**Replace `patches/UI-PCARD-D_no-box-card_20260919.php` in place** with the
round-2 content. One patch file for this task, not two.

Round 1's structure, conventions and safety handling were correct and are not
being re-litigated. Four rules change; everything else in that patch stands.

### The reference prototype is now available

`D - картка товару - раунд 2.html` was produced in Claude Design (claude.ai),
which is why it was never in the repository. The owner has now supplied it.
Section `/* ── D · без коробки ── */` is the approved design. Its rules:

```css
.vD .card  { display:flex; flex-direction:column; gap:10px; height:100%;
             transition:transform .18s ease }
.vD .card:hover { transform:translateY(-3px) }
.vD .media { position:relative; aspect-ratio:331/240 }
.vD .media img { position:absolute; inset:0; width:100%; height:100%;
                 object-fit:contain }
.vD .body  { display:flex; flex-direction:column; gap:6px;
             padding:0 2px 2px; flex:1 }
.vD .tt    { flex:1 }
.vD .rule  { height:1px; background:#D7DADF }
```

Note the prototype's markup order: `.media` → `.rule` → `.body`, i.e. the
divider is a **sibling** between them, so the card's 10px gap sits on both
sides of it. Our `::before` implementation puts it inside `.body`, which is
why the spacing has to be rebalanced (F4 below).

### Root cause of the white columns — confirmed, and it validates the approach

Measured on `/catalog/bustery-pokemon`, all 15 catalog thumbnails: every one is
a **250×250 transparent PNG/WebP** with the product occupying roughly 120×245
(a few are 220×220). There is no white baked into the image files.

What reads as "two white columns" is `.bs-pcard__media img { background:
var(--bs-bg) }` — a `#F7F7F5` rounded plate painted behind the transparent
image, inside a `#FFFFFF` card. Removing that background is the fix, and round
1 already does it correctly. There is no risk of white rectangles floating on
the page canvas after the change.

---

## 1. Task ID

`UI-PCARD-D` — unchanged.

## 2. Fixes required

### F1 · Image slot proportion — `331/240`, not `1/1` — BLOCKING

Round 1 set `.bs-pcard__media { aspect-ratio: 1/1 }` on the grounds that the
live CSS "measures" 1/1. **The live `aspect-ratio: 1/1` declaration is dead
code.** `thumb.twig` renders `<img … width="240" height="240">`; the `height`
attribute is a presentational hint that applies because no CSS rule sets
`height`. With `width: 100%` and `height: 240px` both definite, `aspect-ratio`
is ignored by the browser.

Measured on production (Chrome, 2026-09-19):

| viewport | tile width | `.bs-pcard__media` height | `img` box |
|---|---|---|---|
| 1280px | 311.8 | 264 (240 + 12px padding ×2) | 285.8 × 240 |
| 390px  | 366   | 264 | 342 × 240 |

The slot is **fixed 240px tall at every width** — ratio varies, which is where
the handoff's original "≈331×240" figure came from (it is the 1440px case).

Shipping `1/1` was measured, on production with the round-1 CSS injected:

| viewport | tile height now | with `1/1` | delta | category page |
|---|---|---|---|---|
| 1280px | 415.2 | 478.0 | **+62.8px (+15%)** | 3588 → 3903 (+315px) |
| 390px  | 414.4 | 531.4 | **+117px (+28%)** | 8663 → 10418 (+1755px) |

At 390px the catalog is one column, so the slot becomes a 366×366 square
holding a ~180px-wide product: the empty side canvas the task exists to remove
gets *larger* in absolute terms.

**Required:**

```css
.bs-pcard__media { position: relative; aspect-ratio: 331/240; }
```

Owner decision, 2026-09-19: match the prototype. At 390px this gives 265px
(today 264 — effectively identical); at 1280px 226px vs 240px today, i.e. the
product renders ~6% smaller. Accepted.

Do **not** substitute a fixed `height: 240px`, and do not keep `1/1`.

### F2 · Remove `flex: 1` from `.bs-pcard__title` — BLOCKING

Round 1 added `flex: 1` to satisfy acceptance §7.3. That criterion was already
passing, and `flex: 1` actively breaks a different alignment.

`.bs-pcard__cta { margin-top: auto }` and `.bs-pcard__body { flex: 1 1 auto }`
already exist (RD-04 block, `boostershop-ds.css` lines 490–491) and already pin
the buy button to the bottom of an equal-height tile. Measured on a row of
three buyable products: buy-button tops `692.6 / 692.6 / 692.6` — identical,
with and without `flex: 1`. Titles are `-webkit-line-clamp: 2` with
`min-height: 40px`, so a title box is 40px whether the name wraps to one line
or two. The zigzag described in round 1's §4 does not exist in this codebase.

What `flex: 1` does do, measured on a mixed row (1 buyable + 2 out-of-stock, at
1280px):

| | price-row tops | spread |
|---|---|---|
| today | 2316.3 / 2316.3 / 2316.3 | **0.0px** |
| round-1 CSS | 2633.6 / 2643.5 / 2643.5 | **9.9px** |

The title absorbs a different amount of free space in a card whose CTA slot
holds a `Немає в наявності` span rather than a button, so the price rows go out
of line. A regression, introduced to fix a non-problem.

**Required:** `.bs-pcard__title` keeps its existing round-1 edit only for the
focus rule; drop the `flex: 1` declaration entirely.

Out of scope, do not touch: the residual 9.9px difference between a buy button
top and a `Немає в наявності` span top comes from
`.bs-pcard__unavail { padding: 10px 0 2px }`. Leave it.

### F3 · Focus ring must actually be painted — BLOCKING

`.bs-pcard__title` is the `-webkit-line-clamp` box and carries `overflow:
hidden`. Round 1 put the ring on the inner `<a>` with `outline-offset: 2px`,
which paints outside that link's border box — into the clipped region.

Measured at 1280px: `.bs-pcard__title` clip box `x 88.5, y 609.4, w 283.8,
h 40`; link box `x 88.5, y 609.4, w 212.4, h 38.6`. The ring is drawn at
`x 86.5 → 302.9`, `y 607.4 → 650.0`. Left, top and bottom edges fall outside
the clip box. Only the right edge survives.

A `getComputedStyle` read returns the declared `outline` whether or not a
single pixel of it is painted — that is not proof. AGENTS.md UI/CSS discipline
§5 requires interactive states to be verified as rendered.

**Required:**

1. Also remove `overflow: hidden` from `.bs-pcard`. It existed to clip the
   image to the card's `border-radius`, which this patch deletes; the prototype
   card has no overflow either. One less clipping layer.
2. The keyboard focus ring on a product title must be **visible on all four
   sides**. Either implementation is acceptable:
   - `.bs-pcard__title a:focus-visible { outline: 2px solid var(--bs-blue);
     outline-offset: -2px; }` — negative offset paints inside the link box, so
     nothing clips it; or
   - `.bs-pcard__title:has(a:focus-visible) { outline: 2px solid
     var(--bs-blue); outline-offset: 2px; }` — ring on the unclipped title box.
3. Proof is a **screenshot** of a focused one-line title and a focused two-line
   title, at 390px and 1280px. Not a computed-style dump.

### F4 · Text block flush with the image, divider spacing symmetric — BLOCKING

After the container is removed, the image spans the full tile width while
`.bs-pcard__body` keeps `padding: 4px 14px 14px` inherited from the card era —
measured 14px of inset between the image's left edge and the title's. The
prototype has the text flush with the image.

Owner decision, 2026-09-19: match the prototype.

**Required:**

```css
.bs-pcard { gap: var(--bs-s2); }                       /* was var(--bs-s3) */
.bs-pcard__body { padding: 0 2px 2px; display: flex; flex-direction: column;
                  gap: var(--bs-s2); }
.bs-pcard__body::before { content: ""; order: -1; display: block; height: 1px;
                          margin: 0 -2px; background: var(--bs-line); }
```

This also fixes the spacing asymmetry round 1 produced — measured 16px above
the divider (12px card gap + 4px body padding-top) against 8px below. With
padding-top at 0 and both gaps at `--bs-s2`, it is 8px on both sides, on the
4px grid, matching the prototype's symmetric 10/10.

Keep `.bs-pcard__body { flex: 1 1 auto }` and `.bs-pcard__cta
{ margin-top: auto }` from the RD-04 block untouched.

## 3. Resulting target CSS

For clarity, the five edited regions in `catalog/view/stylesheet/boostershop-ds.css`
after round 2. Anchors are the **original** live rules (verified byte-exact
against `booster-debug-CAT-004.tar.gz` and the live CSSOM on 2026-09-19):

```css
/* UI-PCARD-D: container decoration removed — tile sits on the page canvas */
.bs-pcard {
  display: flex; flex-direction: column;
  gap: var(--bs-s2);
  transition: transform .18s ease;
}
.bs-pcard:hover, .bs-pcard:focus-within { transform: translateY(-3px); }

.bs-pcard__media { position: relative; aspect-ratio: 331/240; }
.bs-pcard__media img {
  position: absolute; inset: 0;
  width: 100%; height: 100%; object-fit: contain;
}

.bs-pcard__body { padding: 0 2px 2px; display: flex; flex-direction: column; gap: var(--bs-s2); }
.bs-pcard__body::before {
  content: ""; order: -1;
  display: block; height: 1px; margin: 0 -2px;
  background: var(--bs-line);
}

.bs-pcard__title { /* …all existing declarations unchanged, no flex… */ }
/* focus ring per F3 */

.bs-pcard__img-placeholder {
  position: absolute; inset: 0;
  width: 100%; height: 100%;
  background: transparent;
}
```

`--bs-s2` is `8px`, `--bs-line` is `#E5E7EB`, `--bs-blue` is `#1E3A8A` — all
confirmed present. No new tokens.

## 4. Carried over from round 1 — do not redo, do not undo

- Patch conventions C1, C2, C3, C5, C7, C8 as implemented. C4 remains
  correctly n/a (no `.php` output file); keep the write-then-readback
  verification and restore-on-throw.
- The content marker lives in the CSS, never in the shared cache-bust token.
  Use a **new** token value for round 2.
- `.bs-pcard__media`'s `padding: 12px` stays removed.
- `.bs-pcard__badge-tl/-tr` stay untouched — they are already
  `position: absolute` inside a `position: relative` `.bs-pcard__media`.
- `thumb.php`, `thumb.twig`, badge colors, price row, CTA button, TECH-015 GA4
  script, `.bs-catcards` / `.bs-subtiles`, the `UI-FIX-20260903-TILES` block:
  all untouched.
- Files written: `catalog/view/stylesheet/boostershop-ds.css` and the single
  cache-bust line in `catalog/view/template/common/header.twig`. Nothing else.
  No DB writes.

## 5. Do not touch

Unchanged from round 1 §5.

## 6. Acceptance criteria — round 2

Supersedes round 1 §7 where they conflict.

1. Tile has no background, border, shadow or radius; product sits on `#F7F7F5`.
2. Tile DOM byte-identical to pre-patch (no `.twig`/`.php` markup change).
3. `.bs-pcard__media` computed height at 390px is within ±2px of **265px**, and
   at 1280px within ±2px of **226px**. Tile height grows by no more than 5px at
   either width relative to pre-patch.
4. In a row mixing a buyable product with an out-of-stock one, price-row tops
   are identical (spread ≤ 1px) — i.e. no worse than today's measured 0.0px.
5. Buy-button tops across buyable products in a row are identical (≤ 1px).
6. Keyboard focus ring on a title is visible on all four sides, one-line and
   two-line titles, 390px and 1280px — **screenshot evidence**.
7. Title, price and button left edges align with the image's left edge (≤ 2px).
8. 1px `#E5E7EB` divider spans the full tile width, with equal 8px gaps above
   and below.
9. Hover lifts the tile 3px.
10. Badges stay inside the image slot and clear of the product artwork.
11. `/search`, manufacturer page and the homepage «Рекомендовані товари» block
    render consistently; no new console errors.

## 7. QA / smoke test

- Breakpoints 390 / 768 / 1100 / 1440px on a category mixing pack + box + deck.
- States: discounted, out-of-stock, preorder, and a no-photo product if one
  exists (its slot will be fully blank — accepted, flagged in round 1).
- Last row with a single product — tile must not stretch.
- Empty search result.
- **Rich Results Test is not required for this patch.** No `.twig`/`.php`
  markup is written, and `thumb.twig` carries no microdata or JSON-LD, so tile
  DOM cannot change. Round 1 §8's blocking schema gate does not apply.
- `bs-checkout-smoke` not required.
- SEO risk: low.

## 8. Rollback

Unchanged from round 1 §9: restore both files from the
`_patch_backups/UI-PCARD-D_no-box-card_<ts>/` directory the patch prints as
`backup=`, refresh the OpenCart theme cache, hard-refresh the category page.

## 9. Recommended status after execution

`In review`. Owner QA on production against §6. Criterion 6 (focus ring,
screenshot) and criterion 4 (price alignment) are the two that round 1 failed —
do not close without them.
