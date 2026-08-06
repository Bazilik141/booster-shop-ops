# TECH-013 — WP2 prep note: the header logo CLS is a CSS problem, not a file-size problem

> ## ⚠ SUPERSEDED IN TWO PLACES — corrected 2026-08-06 by in-browser measurement
>
> 1. **This note named `boostershop-ds.css:834` as the winning rule. That is wrong.**
>    Seven rules match `.bs-header__logo img`. The ones that actually apply are
>    **`:2292`** (`body.bs .bs-header__logo img { height:42px !important; max-width:180px
>    !important; width:auto !important }`) and **`:2375`** (the `max-width:768px` variant,
>    `height:32px !important`). Both carry `!important` *and* higher specificity than
>    `:834`, so `:834`'s 46px and `:839`'s 34px are dead code. This matches the owner's
>    live captures — 42 px at 1411 px wide, 32 px at 390 px — which `:834` could not explain.
>
> 2. **The claim that space is not reserved is also wrong.** Measured directly: the logo
>    box is 135.3×42 *before* the image bytes arrive and 135.3×42 after — the `width`/
>    `height` attributes supply a working aspect-ratio hint (`aspect-ratio: auto 1498/465`).
>    The logo is byte-identical on all three pages, yet home and product show CLS 0.003
>    while category shows 0.25; a shared element cannot explain a category-only shift.
>    The likely culprit is category-only and JS-rendered — the load-more widget
>    (`.bs-btn-load-more` / `.bs-load-more-progress`) or the smart-filter panel.
>
> The WP2 patch still adds an explicit `aspect-ratio` at `:2292`, because after the
> re-export the old 1498×465 attributes no longer describe the file and the reservation
> should not depend on those attributes surviving future edits. But it is **not** expected
> to move desktop category CLS on its own. Everything below is kept for history.

Date: 2026-08-06 · Executor: Claude Code · Status: **RECORDED, NOT ACTED ON**
Raised by the owner after the post-WP1 PSI run. This note exists so the finding is not
lost between WP4 and WP2. **No file was changed for this.**

---

## The owner's instruction

> The header logo needs more than a re-export. Shrinking it from 1498×465 to 270×84 will
> not fix the CLS — the `img-fluid` override kills the aspect-ratio reservation regardless
> of file size. WP2 must also give the header logo an explicit aspect ratio or explicit
> dimensions in CSS. Otherwise we ship a smaller file and keep the layout shift.

Confirmed, with one correction to the mechanism — see below. Desktop category CLS is
**0.253**, of which PSI attributes **0.251** to the header logo and the remainder to
`fa-solid-900.woff2` (addressed by WP4).

## What the live CSS actually does

The markup at `catalog/view/template/common/header.twig:250` is:

```twig
<img src="{{ logo }}" title="{{ name }}" alt="{{ name }}" class="img-fluid" width="1498" height="465"/>
```

The attributes are present (TECH-003 is intact). What overrides them is a **stack of four
rules across two stylesheets**, not `img-fluid` alone:

| Source | Rule | Effect |
|---|---|---|
| `bootstrap.css:583` | `.img-fluid { max-width: 100%; height: auto; }` | height released to auto |
| `boostershop-ds.css:692` | `.bs-header__logo img { height: 36px; width: auto; }` | fixes height, releases width |
| `boostershop-ds.css:749` | `@media (max-width:…) .bs-header__logo img { height: 28px; }` | |
| **`boostershop-ds.css:834`** | **`.bs-header__logo img { height: 46px !important; width: auto !important; }`** | **wins — both axes `!important`** |
| `boostershop-ds.css:839` | `@media (max-width:480px) { … height: 34px !important; }` | |

**Correction to the stated mechanism.** `img-fluid` is not the deciding rule — it is
outranked by the `!important` pair at `boostershop-ds.css:834–836`, which is the actual
override. The conclusion the owner drew is unchanged and correct: the rendered box is
driven entirely by CSS (`height` fixed, `width: auto`), so the `width="1498" height="465"`
attributes do not size the element and a re-export alone cannot fix the shift. But WP2
must edit **`boostershop-ds.css:834`**, and any patch description naming `img-fluid` as the
root cause would be pointing at the wrong line — which AGENTS.md UI/CSS discipline
explicitly forbids.

Per that same discipline: this selector already carries `!important` from a previous
round, so WP2 is **stacking on an existing override** in a shared DS file. That is a soft
risky zone and needs the override history stated in the patch description.

## Why space is not reserved

With `height: 46px` and `width: auto`, the browser needs the intrinsic ratio to compute
width. The UA aspect-ratio hint derived from the HTML attributes should supply it — but
the logo is fetched from `image/catalog/One Piece/BS Big logo.png` as a **bare, unversioned
394 KB PNG that bypasses `image/cache/`** (`catalog/controller/common/header.php:65`), and
on a throttled connection it arrives late. Until it does, `width: auto` against a
`display: inline-flex` parent (`.bs-header__logo`, DS:688) collapses toward zero, and the
whole header row — and therefore the product grid below it — reflows when the image lands.

## What WP2 must do (both, not either)

1. **Reserve the box in CSS.** Add an explicit `aspect-ratio` to `.bs-header__logo img`
   alongside the existing `height`, so width is computed before the image arrives.
2. **Re-export** to the §5D target of **270×84**.

**These two must agree.** The current intrinsic ratio is 1498/465 = **3.2215**; the §5D
export target 270/84 = **3.2143**. They are close but not equal. If CSS declares
`aspect-ratio: 1498/465` while the file ships at 270×84, a sub-pixel mismatch remains.
Recommendation for WP2: pick one ratio and make all three agree — the CSS `aspect-ratio`,
the exported pixel dimensions, and the `width`/`height` attributes in `header.twig:250`
(which still say 1498/465 and should be updated to the new export size).

An exact-ratio alternative worth considering: export at **271×84** (3.2262) or keep
**270×84** and set `aspect-ratio: 270/84` — the latter is simplest and self-consistent.

## Related, also for WP2

- The logo bypasses the OpenCart resizer entirely (`header.php:65` emits
  `config_url . 'image/' . config_logo`). Handoff §4 WP2 asks to route it through
  `image/cache/` "where practical" — note that doing so changes the emitted URL, which
  interacts with the WP3 cache decision below.
- **The logo URL has no `?v=`.** This is the exact unversioned-image case behind the
  handoff §5A rule that WP3 must not put a one-year `immutable` TTL on unversioned paths.
  If WP2 overwrites `BS Big logo.png` in place, returning visitors keep the 394 KB file
  until their cache expires — the logo currently has no `Cache-Control` of its own, but
  this should be re-checked with `curl -sI` at WP2 time.
- `PokemonC.png` (452×176 target) sits in `.bs-catcard__media` with
  `object-fit: contain`, so per §5D its aspect ratio can be preserved safely and the
  declared `168×168` attributes should be left alone.
- **Do not spend effort on `One Piece-Photoroom.png`** — intrinsic 463×111 against a
  452×108 requirement, already correctly sized (§5D).

---

_Recorded only. No production file touched, nothing committed._
