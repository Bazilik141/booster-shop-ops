# Claude Code Report — UI-PCARD-D: catalog product tile, "без коробки"

Date: 2026-09-19

Handoff: `handoffs/CLAUDE CODE - картка товару D без коробки_20260919.md`
Patch: `patches/UI-PCARD-D_no-box-card_20260919.php` — rationale and exact
before/after CSS are in the patch's own header comment and inline edit
comments; not repeated here.

## Scope vs handoff — deviations

1. **Reference prototype missing.** `D - картка товару - раунд 2.html` is not
   in the repository (checked repo-wide and the Downloads root). Implemented
   directly from the handoff's written §4 spec instead, which is precise
   enough to fully determine every rule (all values are either existing
   tokens or explicit px numbers). Flagging so the owner can compare the
   deployed result against their own copy of the prototype during QA.
2. **Aspect ratio: 1/1, not 331/240.** §4 said "≈331/240, measure and keep."
   The live rule (`.bs-pcard__media img` / `.bs-pcard__img-placeholder`) is
   `aspect-ratio: 1/1`. Kept at the measured value, per the handoff's own
   instruction to measure rather than trust the guess.
3. **`.bs-pcard__media`'s `padding: 12px` removed**, not just left alone. Once
   the image switches to `position:absolute; inset:0` (as §4 requires), the
   containing block for that child is the parent's padding edge — so the
   padding stops creating any visual inset and becomes dead CSS. Removed as a
   direct, provable consequence of the inset:0 change, not a separate style
   decision. Net effect: product photos now render edge-to-edge in the slot
   (no matting), which reads as more "без коробки," not less.
4. **Badges: verified, not changed.** `.bs-pcard__badge-tl/-tr` are already
   `position:absolute` inside `.bs-pcard__media` (which stays
   `position:relative` before and after this patch) — they were never
   positioned relative to the white card. §4's "Бейджі" concern doesn't apply
   to this codebase; no fix was needed. One visible side effect of point 3:
   badges now sit ~18px from the image's own visible edge instead of ~6px
   (previously offset by the media padding). Still fully inside the slot,
   still clear of the product — satisfies acceptance §7.6 as-is.
5. **No-photo placeholder now renders fully invisible**, not a lightly-toned
   box. Before, `.bs-pcard__img-placeholder` painted `var(--bs-bg)` inside a
   *white* card, so it showed as a pale rectangle. Now the card background is
   gone and the placeholder is `transparent` (per §4's explicit "no
   plates/radius under the image"), so a no-photo product's image slot shows
   literally nothing — just page canvas — until the title/price/button. This
   is a rare path (`bs_no_img`, used only when a product has no photo at
   all); flagging it because it's a real, visible consequence the handoff
   didn't anticipate, not silently deciding it's fine.

Everything else in §4 (container decoration removal, hover/focus-within
lift, divider, title `flex:1`, focus-visible ring, token-only values, 4px
grid step) is implemented as specified.

## Override-history check (AGENTS.md UI/CSS discipline §2)

`grep`'d `patches/` for every selector this patch touches
(`.bs-pcard`, `__media`, `__body`, `__title`, `__img-placeholder`,
`var(--bs-paper)`) before writing the patch:

- `CAT-004_variant-selector_20260916.php` — touches `boostershop-ds.css` but
  only adds `.bs-variant__chip*` (product page selector chips, different
  component, confirmed no shared selector).
- `CAT-004-SD-7_rare-pack-listing-badge_20260918.php` — touches
  `thumb.php`/`thumb.twig`/`boostershop-ds.css`/`header.twig`, but its CSS
  insert is anchored on `/* /UI-FIX-20260903-TILES */` (end of file) and only
  adds `.bs-badge--rare` + turns `.bs-pcard__badge-tl` into a flex column —
  it does not touch `.bs-pcard`, `__media`, `__body`, `__title`, or
  `__img-placeholder`.
- No other patch in the repo references `.bs-pcard*` or `var(--bs-paper)` in
  this component.

Conclusion: the region this patch edits (`.bs-pcard` core rules, verified
against `booster-debug-CAT-004.tar.gz`, 2026-09-16) has not been touched by
any patch since that snapshot, so it is safe to anchor on.

No `!important`, no `setTimeout`, no new `position:absolute/fixed` beyond
what §4 explicitly asks for, no unexplained magic-pixel values (18px badge
offset and 14px body padding are pre-existing, untouched numbers).

## Sandbox dry run

Ran against copies of the two live files (CSS from the 2026-09-16 debug
archive, header.twig from the same) in a local sandbox, not the repo:

```
cwd=...\uipcardd-sandbox
header_cache_bust_from=uifix-tiles-20260904
header_cache_bust_to=ui-pcard-d-20260919
backup=...\_patch_backups\UI-PCARD-D_no-box-card_20260919_123856
changed=catalog/view/stylesheet/boostershop-ds.css
changed=catalog/view/template/common/header.twig
done=ok
self_delete=ok
```

All 5 anchored CSS replacements matched on the first try (no
`anchor_count_invalid`). On production the "from" token will read whatever
CAT-004-SD-7 last wrote (`cat004-sd7-20260918`, expected) — the patch reads
it dynamically and does not assume this value.

## php -l result

```
No syntax errors detected in UI-PCARD-D_no-box-card_20260919.php
```

No `.php` file is written by this patch (CSS + Twig only), so there is no
output file for the convention-4 lint gate to check.

## Idempotency

Re-running the patch against the already-patched sandbox files:

```
already_applied=yes
done=ok
self_delete=ok
```

Confirmed the cache-bust token is untouched on the repeat run (read via the
CSS marker only, per convention 8).

## CSS structural check

Brace count on the full stylesheet: 1175/1175 before, 1178/1178 after
(+3/+3 — exactly the three new standalone rules added: hover/focus-within,
the divider, focus-visible). Balanced.

## Visual verification (static mockup, not the live theme)

No staging exists and the live template/CSS can't run standalone locally, so
I rebuilt the tile in isolation — real `thumb.twig` markup, the patched CSS
rules copied verbatim from the sandbox output, real DS tokens — and drove it
in the browser pane at 375px and 1200px:

- **Button baseline (§7.3):** measured via `getBoundingClientRect()`, not by
  eye. A 1-line title and a 2-line title in the same row: buy-button tops at
  `592.67px` / `592.67px` — 0px difference. A second row (preorder / no-photo
  placeholder / long title) — three buttons, all `1197.33px`.
- **Focus-visible (§7.5):** Tab to the title link → computed
  `outline: 2px solid rgb(30, 58, 138)` (`var(--bs-blue)`), `outline-offset:
  2px`.
- **Hover/focus-within lift (§7.5):** hovering `.bs-pcard` → computed
  `transform: matrix(1,0,0,1,0,-3)` (`translateY(-3px)`), `matches(':hover')
  === true`.
- **Discount / out-of-stock / preorder badges (§7.6):** all render inside the
  image slot, clear of the product artwork, at both widths tested.
- **Single tile in last row (§8):** does not stretch to fill the row.
- Browser console: no errors at either width.

This covers everything in §7 and §8 that depends only on the tile's own
markup and CSS. It does **not** cover: real product photography at true
aspect ratio, the live grid's actual column breakpoints (untouched by this
patch), Rich Results Test, or anything cross-browser — those need the owner's
production pass below.

## Rollback

Backup path is printed by the patch as `backup=...` on run (see AGENTS.md
convention 3, `_patch_backups/UI-PCARD-D_no-box-card_<timestamp>/`).
To restore: copy `catalog/view/stylesheet/boostershop-ds.css` and
`catalog/view/template/common/header.twig` from that directory back over the
live files, then clear the OpenCart theme cache and hard-refresh.

Rollback trigger (per handoff §9): `Product` markup disappears from a tile's
DOM (should be impossible — this patch writes no markup), the catalog grid
breaks on mobile, or the homepage "Рекомендовані товари" block breaks.

## Run command (owner)

```bash
php UI-PCARD-D_no-box-card_20260919.php
```

Upload to `~/public_html` first; run from there.

## Post-deploy QA checklist (owner, production — maps to handoff §7/§8)

- [ ] `/one-piece-card-game` (or any category) — tiles have no white
      background/border/shadow; product sits on the `#F7F7F5` page.
- [ ] 3-across row: top edges of "Купити" buttons align even when names wrap
      differently.
- [ ] 1px line visible between image and title on every tile.
- [ ] Hover lifts the tile ~3px; Tab to a product title shows a visible blue
      outline.
- [ ] Discount / out-of-stock / preorder badges stay inside the photo, not
      overlapping it.
- [ ] 390 / 768 / 1100 / 1440px — grid intact, equal tile heights per row.
- [ ] Same check on `/search`, a manufacturer page, and the homepage
      "Рекомендовані товари" block.
- [ ] A no-photo product (if one exists in the catalog) — confirm the blank
      image slot (see deviation 5 above) is acceptable as-is.
- [ ] View source / DevTools on one tile — confirm no markup or `Product`
      schema attribute was removed (expected: byte-identical HTML, since this
      patch changes no `.twig`/`.php` file).
- [ ] ~~Rich Results Test~~ — **not required** (round-2 addendum): `thumb.twig`
      carries no schema/JSON-LD at all, and this patch writes no `.twig`/`.php`,
      so the tile DOM cannot change. Superseded, see addendum below.
- [ ] Keyboard `Tab` to a product title on the deployed page — ring visible on
      all four sides, both a short and a wrapping title (round-2 F3).

## Side effects / risks

- Deviations 3–5 above (media padding removed as dead code; badge inset now
  ~18px not ~6px; no-photo placeholder now fully blank) still stand in round
  2. Deviation 2 (aspect ratio) is superseded — see round-2 addendum below.
- `UI-PCARD-D` task ID: not found in `context-index.md` or
  `dashboard/booster-dashboard.html`, so used as given in the handoff. Not
  checked against Notion (Claude Code doesn't write Notion status; leaving
  that confirmation to Claude chat at status-update time).
- Shared-file risk: `boostershop-ds.css` and `header.twig` are both
  soft-risky-zone files per AGENTS.md §UI/CSS discipline. Override-history
  check above found no conflict with the two most recent patches to touch
  either file.

---

## Round-2 addendum — 2026-09-19, fixes F1–F4

Fix handoff: `handoffs/handoff_UI-PCARD-D_fix-round-2_20260919.md`.
Review that required it: `diagnostics/UI-PCARD-D_no-box-card_review_20260919.md`.
`patches/UI-PCARD-D_no-box-card_20260919.php` was **replaced in place** — round
1 was never deployed (owner confirmed live CSS was still pre-patch), so there
is still exactly one patch file for this task.

**Superseded from round 1:** deviation 2 above no longer applies. The
prototype the review measured against is the real design target; round 1's
"measured 1/1" was reading a dead CSS declaration, not the rendered box (see
the patch header's "Aspect ratio" paragraph for the full mechanism). §7.5's
focus ring and §7.3's button alignment from round 1 are also superseded by F2
and F3 below.

### F1 · `aspect-ratio: 331/240`

Implemented exactly as specified in the fix handoff §2/F1. Re-verified in a
fresh mockup built from the round-2 sandbox output: `.bs-pcard__media` at
1280px measured `392.33 × 284.45px`, ratio `1.37924` vs. the target
`331/240 = 1.37917` — matches to 4 decimal places (residual is SVG
sub-pixel rounding in the mockup image, not a CSS error).

### F2 · `flex: 1` removed from `.bs-pcard__title`

Re-verified with the fix handoff's own reproduction case: a row mixing one
buyable product with two out-of-stock ones. Price-row tops measured
`473.453 / 473.453 / 473.469px` — 0.016px spread, at noise level, matching
the ≤1px bar in acceptance §6.4 (this is the exact case round 1 regressed to
9.9px). Buy-button-only row (three buyable tiles, one 2-line title) still
`985.906px` on all three — §6.5 holds without the `flex: 1` round 1 added for
it.

### F3 · Focus ring — screenshot evidence, not computed style

This is the one the review explicitly said a computed-style read cannot
prove. Verified by real keyboard `Tab` navigation (not `element.focus()`,
which did not trigger `:focus-visible` in this browser) to a 1-line title and
to a title that wraps to 2–3 lines depending on viewport, at both 1280px and
375px, with a screenshot taken at each of the 4 combinations:

- 1280px, 1-line title ("One Piece OP-11"): complete rectangular ring, all
  four sides visible, no clipping.
- 1280px, 2-line title (long Pokémon name): same — clean single rectangle
  around both lines, not a stepped/per-fragment outline.
- 375px, 1-line title: ring fully visible.
- 375px, same title now wrapping to 3 lines at the narrower width: ring still
  a clean, fully visible rectangle around all three lines.

Supporting computed state at capture time: `.bs-pcard` `overflow: visible`
(confirms F3's container-level removal took effect), `:has(a)` selector
support confirmed (`CSS.supports('selector(:has(a))') === true`), title box
outline `2px solid rgb(30, 58, 138)` / `outline-offset: 2px`, unclipped
because the ring is on `.bs-pcard__title` itself (`:has()`), not the inner
`<a>` — nothing between the title and the viewport has `overflow: hidden`
after F3.1 removed it from `.bs-pcard`.

The `:has()` implementation (option 2 in the fix handoff, chosen over the
negative-offset option on the `<a>`) was picked because it renders one clean
rectangle regardless of line count — an inline `<a>` that wraps across lines
paints a separate outline fragment per line, which the fix handoff's "visible
on all four sides" bar doesn't rule out but this avoids entirely.
`:has()` is supported in every evergreen browser this store needs to target.

### F4 · Flush text, symmetric divider

Re-verified in the same mockup: title/price/button left edge `26px` from
viewport origin vs. the tile's own left edge (`.bs-pcard` / `.bs-pcard__media`
both) at `24px` — 2px inset, matching acceptance §6.7's `≤ 2px` exactly (the
prototype's own `padding: 0 2px 2px`). Divider spacing: `8px` between the
image's bottom edge and the body's top edge (the container's own
`gap: var(--bs-s2)`), then the divider's own `1px`, then `8px` more to the
title's top (the body's `gap: var(--bs-s2)` between the `::before` and the
title) — symmetric 8/8, matching §6.8.

### Updated sandbox run (round 2, against the pristine pre-patch files)

```
header_cache_bust_from=uifix-tiles-20260904
header_cache_bust_to=ui-pcard-d2-20260919
backup=...\_patch_backups\UI-PCARD-D_no-box-card_20260919_185612
changed=catalog/view/stylesheet/boostershop-ds.css
changed=catalog/view/template/common/header.twig
done=ok
self_delete=ok
```

All 5 anchors (now anchored on the original, never-patched live rules, per
the fix handoff's instruction) matched on the first try. Re-run against the
patched sandbox: `already_applied=yes` — idempotent. Cache token is a new
value (`ui-pcard-d2-20260919`), per the fix handoff's "use a new token" note;
never assumes round 1's token, which was never live. Brace count: 1175/1175
(pristine) → 1178/1178 (patched), balanced.

### Still open / owner-facing

- Deviations 3–5 from round 1 (media padding removed as dead code, badge
  inset ~18px, no-photo placeholder fully blank) are unchanged by round 2 and
  still need owner sign-off at QA.
- All F1–F4 verification above is on the mockup, not production (no server
  access; same limitation as round 1). The fix handoff's acceptance §6
  criteria are owner-run checks on the deployed site — this addendum narrows
  what those checks should already find true, it does not replace them.
- `bs-merchant-schema-qa` / Rich Results Test: not required for this patch
  per fix handoff §7 (superseding round 1's report line above, which named it
  as blocking under the original handoff's §8 wording).

### Updated run command

Unchanged mechanically — same filename, same upload/run flow:

```bash
php UI-PCARD-D_no-box-card_20260919.php
```
