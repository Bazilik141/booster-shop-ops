# Claude (chat) — pre-deploy review: UI-PCARD-D

Date: 2026-09-19
Patch: `patches/UI-PCARD-D_no-box-card_20260919.php`
Executor report: `diagnostics/UI-PCARD-D_no-box-card_report_20260919.md` (Claude Code)
Handoff: `CLAUDE CODE - картка товару D без коробки_20260919.md`

**Verdict: Deploy OK; non-blocking findings (R1–R5).**

## Independently verified here (not taken from the executor report)

- `php -l` on a copy: no syntax errors. No 8.1+ syntax anywhere — safe on production PHP 8.0.30.
- All five CSS anchors match **exactly once** in the live stylesheet
  (`booster-debug-CAT-004.tar.gz` → `catalog/view/stylesheet/boostershop-ds.css`);
  marker `UI-PCARD-D` absent → first run applies, repeat run exits `already_applied`.
- `catalog/view/stylesheet/boostershop-ds.css?v=` occurs exactly once in `header.twig`
  (current token `uifix-tiles-20260904`). Token is read and shape-validated at runtime,
  never hardcoded — AGENTS.md convention 8 satisfied.
- Every token the patch references exists: `--bs-s2` 8px, `--bs-s3` 12px,
  `--bs-line` `#E5E7EB` (= acceptance §7.4), `--bs-blue` `#1E3A8A`. No new colors/radii.
- `.bs-pcard { height: 100%; }` (RD-04 block, line 489) is outside every anchor and stays —
  handoff §4's `height:100%` requirement remains met.
- Conventions: C1 ✓ · C2 ✓ · C3 ✓ (backup written and byte-verified before any write) ·
  C5 ✓ (content marker in the CSS, deliberately not the shared cache token) · C6 n/a (no DB) ·
  C7 ✓ · C8 ✓. **C4 correctly n/a** — the patch writes no `.php`, so there is no output file to
  lint; write-then-readback comparison plus restore-on-throw covers the same failure mode.
- Safety scan: no secrets, no DB ops, no unbounded loops/globs, no `!important`, no `setTimeout`,
  no magic pixels. The two new `position:absolute` rules are required by handoff §4 and explained
  in the patch header (AGENTS.md UI/CSS discipline §7 signature scan — explained, not silent).
- **Schema gate is moot.** The patch writes no `.twig`/`.php` touching the tile, and `thumb.twig`
  carries no microdata or JSON-LD at all. Tile DOM is byte-identical by construction; handoff §8's
  "blocking Rich Results" condition cannot trigger. Acceptance §7.2 is satisfied a priori.

## Findings

### R1 · the `:focus-visible` ring will be clipped (acceptance §7.5) — non-blocking

`.bs-pcard__title` keeps `overflow: hidden` — it is the `-webkit-line-clamp: 2` box — and
`.bs-pcard` keeps `overflow: hidden` too. The new ring is declared on the inner `<a>` with
`outline-offset: 2px`, so it paints *outside* that link's border box and is clipped by the title's
own overflow: at the top and left on a one-line title, and on effectively all sides once the title
wraps to two lines and fills the 40px clamp box.

The executor's evidence was a computed-style read, which returns the declared `outline` whether or
not a single pixel of it is painted. AGENTS.md UI/CSS discipline §5 explicitly excludes that form
of proof for interactive states. So §7.5 is **unverified, and likely failing as written**.

Not a reason to hold the patch — it changes nothing else and is reversible. If owner QA confirms
the ring is invisible or cropped, the follow-up fix is one rule: put the ring on the title box
(`.bs-pcard__title:focus-within`) or use an inset `box-shadow` instead of an offset outline.

### R2 · `flex: 1` on the title moves the price; it does not fix button alignment — non-blocking

`.bs-pcard__cta { margin-top: auto; }` and `.bs-pcard__body { flex: 1 1 auto; }` (RD-04, lines
490–491) already pinned the buy button to the bottom of an equal-height tile before this patch.
The handoff's §4 premise — "без цього кнопки в ряду зигзагом" — was not checked against the live
CSS and appears to be wrong; the executor's 0px `getBoundingClientRect()` measurement would have
read 0px without the change as well.

What `flex: 1` on `.bs-pcard__title` actually does is let the title box absorb the free space, so
the **price row detaches from the title and settles directly above the button**. That is what §4
asked for ("ціна і кнопка на спільну базову лінію"), but it is a deliberate relocation of the
price, not a repair. Owner design call at QA.

### R3 · tiles grow ~36px taller — non-blocking

`aspect-ratio: 1/1` moves from the `img` onto `.bs-pcard__media` and the media's `padding: 12px`
is removed, so the image slot goes from (tile width − 24px) to (tile width); `gap: var(--bs-s3)`
adds a further 12px between slot and body. Worth a look at 390px for above-the-fold density.

### R4 · divider spacing is asymmetric — cosmetic

Above the line: 12px tile gap + 4px `.bs-pcard__body` padding-top = 16px. Below it: 8px
(`--bs-s2`). Token-legal and on the 4px grid, but the line will read as belonging to the title
rather than separating two blocks.

### R5 · badge inset relative to the artwork — QA only

`.bs-pcard__badge-tl/-tr { top: 18px; left/right: 18px }` are unchanged while the 12px media
padding that used to inset the image is gone, so a badge now sits 18px inside the image box
instead of 6px. With `object-fit: contain` and vertical photography it should land on empty
canvas, but this was checked against a mockup, not real product images. Acceptance §7.6.

## Scope

- S1 ✓ — only `boostershop-ds.css` (five `.bs-pcard*` rules) and the one `header.twig` cache-bust
  line. Nothing in handoff §5 touched; `.bs-catcards`/`.bs-subtiles`, the product page,
  `thumb.php`/`thumb.twig`, image files, SEO assets and checkout are all untouched.
- S2 ✓ — one work package.
- S3 — no risky zone. `boostershop-ds.css` is the project's *soft* risky zone (shared DS file):
  the edited region is the `.bs-pcard` core block, confirmed untouched by `CAT-004` and
  `CAT-004-SD-7`, the only patches to that file since the snapshot.
- S4 ✓ — root cause named at rule level in the patch header; override-history grep stated.
- S5 n/a — no SEO URLs generated.

## Operational notes

- The two live files were last snapshotted **before** `CAT-004-SD-7` (2026-09-18) ran, which also
  writes `boostershop-ds.css` and `header.twig`. Its CSS is appended at end of file, so the
  anchors above should still hold — and if they have drifted, the patch fails
  `anchor_count_invalid` **before** creating a backup or writing anything. Safe failure.
- C7: the patch deletes itself after a successful run **and** after an `already_applied` exit.
  A repeat run needs the file re-uploaded.
- Rollback: restore both files from the `_patch_backups/UI-PCARD-D_no-box-card_<ts>/` path the
  patch prints as `backup=`, then OpenCart cache refresh + Ctrl+F5. `_patch_backups/` lives inside
  the deployment tree and is never touched by this patch.
- Post-deploy: `bs-deploy-verify` only. `bs-merchant-schema-qa` not required (see schema note
  above); `bs-checkout-smoke` not required.

---

## Round-2 addendum — 2026-09-19, after live measurement

The owner supplied the reference prototype (built in Claude Design, which is
why it was never in the repo). Verification moved from static reading to
measurement on production (`/catalog/bustery-pokemon`, Chrome, round-1 CSS
injected client-side for the "after" figures). Three findings above are
upgraded from "likely" to measured, and one new blocking finding was added.

**Verdict revised: Return for changes.** Fix handoff:
`handoffs/handoff_UI-PCARD-D_fix-round-2_20260919.md`.

- **NEW / blocking — aspect-ratio.** The live `aspect-ratio: 1/1` on
  `.bs-pcard__media img` is dead code: `thumb.twig` emits `height="240"`, a
  presentational hint that applies because no CSS sets `height`; with width and
  height both definite the ratio is ignored. The real slot is a fixed 240px
  tall at every viewport (media box 264px incl. padding, measured at both
  1280px and 390px) — which is exactly the handoff's original "≈331×240"
  (the 1440px case). Round 1's "measured 1/1" read the declaration, not the
  rendering. Shipping it: tile 415.2 → 478.0 at 1280px (+62.8), 414.4 → 531.4
  at 390px (+117), category page +315px desktop / +1755px mobile.
- **R1 confirmed (was: likely).** Title clip box `x 88.5 y 609.4 w 283.8 h 40`
  vs link box `x 88.5 y 609.4 w 212.4 h 38.6`; the `outline-offset: 2px` ring
  paints at `x 86.5–302.9, y 607.4–650.0`. Left, top and bottom edges are
  outside the clip. Only the right edge is painted.
- **R2 upgraded to a regression.** Buy-button tops in a three-buyable row are
  `692.6 / 692.6 / 692.6` with and without `flex: 1` — §7.3 already passed
  (`.bs-pcard__cta { margin-top: auto }`, RD-04 line 491; titles are
  clamp-2 + `min-height: 40px`, so the box is always 40px). In a row mixing a
  buyable and two out-of-stock products, `flex: 1` moves price-row spread from
  **0.0px to 9.9px**.
- **R4 confirmed and quantified.** 16px above the divider, 8px below
  (prototype: symmetric 10/10). Plus a finding round 1 did not raise: the text
  block sits 14px inside the image's edges, because `.bs-pcard__body` keeps its
  card-era `padding: 4px 14px 14px`; the prototype has the text flush with the
  image (`padding: 0 2px 2px`).
- **Approach validated.** All 15 catalog thumbnails on the sampled category are
  250×250 transparent PNG/WebP, product ≈120×245. The "white columns" are
  `.bs-pcard__media img { background: var(--bs-bg) }` — an `#F7F7F5` plate
  behind a transparent image inside a white card. Removing it is the correct
  fix and cannot leave white rectangles on the canvas.
- **Round 1 is not deployed.** Live `.bs-pcard` computes
  `background: rgb(255,255,255)`, `border-top-width: 1px`,
  `border-radius: 10px`; live token is `cat004-variant-20260918`; no
  `UI-PCARD-D` marker in the served stylesheet. Round 2 anchors on the original
  rules and replaces the round-1 patch file in place.
- **Owner decisions, 2026-09-19:** slot `aspect-ratio: 331/240` (prototype);
  text block flush with the image (`padding: 0 2px 2px`).
- Divider colour: `--bs-line` `#E5E7EB` is the darkest line token in the DS
  (`--bs-line-2` is lighter; no stronger token exists). The prototype used
  `#D7DADF` on a darker canvas. Acceptance §7.4 mandates `#E5E7EB`, so it
  stays; if it proves invisible on `#F7F7F5` in production that is a DS token
  task, not this patch.

---

## Round-2 review — 2026-09-19

**Verdict: Deploy OK.** F1–F4 all implemented as specified. Patch file replaced
in place, still one file for the task; scope unchanged (CSS + one cache-bust
line); new token `ui-pcard-d2-20260919`, marker still the CSS-side
`UI-PCARD-D`. Conventions C1–C3, C5, C7, C8 unchanged from round 1 and still
correct; C4 still correctly n/a. `php -l` clean; no PHP 8.1+ syntax (the seven
matches on a version scan are all the word "never"/"whenever" in comments), so
production PHP 8.0.30 is safe. Only `unlink()` calls are the two C7
`unlink(__FILE__)`.

### Independent verification — round-2 CSS simulated on production

Injected client-side on `/catalog/bustery-pokemon`, measured, then removed. Not
the executor's mockup: the real grid, real thumbnails, real product states.

| check | 1280px | 390px |
|---|---|---|
| `.bs-pcard__media` ratio | 1.37915 (target 331/240 = 1.37917) | 1.37915 |
| `.bs-pcard__media` height | 264 → 226.11 | 264 → 265.38 |
| tile height | 415.19 → 372.30 (**−42.89**) | 414.39 → 410.77 (**−3.62**) |
| category page height | 3588 → 3374 (−214) | 8663 → 8609 (−54) |
| text inset vs image edge | 14px → **2px** | 14px → **2px** |
| divider gap above / below | **8 / 8** | — |
| price-row spread, mixed buyable + out-of-stock row | **0.000px** (round 1: 9.9) | — |
| buy-button spread, three buyable tiles | **0.000px** | — |
| badges inside the image slot | 6/6, inset 18px | — |
| descendants escaping the tile box after `overflow` removal | **0** (0 before) | 0 |

Acceptance §6.3 was written expecting near-parity; the real outcome is that
tiles get **shorter**, not taller — at 1280px by 43px. Better than the bar, but
the catalog will read noticeably more compact than today.

### F3 — why the ring can no longer be clipped

`.bs-pcard` computes `overflow: visible` after the patch, and a walk from
`.bs-pcard__title` up to `<html>` returns **zero** ancestors with any non-visible
overflow. `CSS.supports('selector(:has(a))')` is `true`. The ring is on the
title's own box, which nothing clips. Structural, not a computed-style claim —
this is the condition round 1 failed.

### Non-blocking, owner's eye at QA

- **Ring sits flush with the tile edge.** Body padding is 2px and
  `outline-offset` is 2px, so the ring's left and right edges land exactly on
  the tile boundary (measured: ring left 74.50, tile left 74.50). Not clipped,
  but tight against the neighbouring column. `outline-offset: 1px` if it reads
  badly.
- **Product renders ~6% smaller at desktop** — slot 226px vs 240px today. The
  accepted F1 trade-off; at 390px it is unchanged (265 vs 264).
- **Divider is subtle.** `--bs-line` `#E5E7EB` on `#F7F7F5`. It is the darkest
  line token the DS has. If invisible in production, that is a token task.
- Round-1 deviations 3–5 still stand: media padding removed as dead code,
  badge inset now 18px from the image's own edge (measured inside the slot on
  all six badges), no-photo placeholder fully blank.
- C7: the patch self-deletes after a successful run **and** after an
  `already_applied` exit — a repeat run needs the file re-uploaded.

### Open, unrelated to the patch

`UI-PCARD-D` was not found in `context-index.md` or in `ROADMAP_TASKS` in
`dashboard/booster-dashboard.html`, and has not been checked against Notion.
The task may have no roadmap row at all. Creating one requires the Notion row
and its dashboard mirror in the same session (`AGENTS.md`, 2026-08-06) and an
explicit owner instruction.
