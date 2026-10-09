# Visual Design Result — UI-CD — Component B (homepage category tiles)

Date: 2026-09-03 | From: Claude Design | For: Codex
Pairs with: `handoffs/CODEX - UI-CD_final_A-credit_C-subcat_20260903.md` (A + C). This document completes that set — B was withdrawn there and redesigned separately.
Continuation context: `uploads/CLAUDE - B tiles continuation_20260903.md`
Reference prototype (rendered against the real saved homepage at 390px and 1100px): `B - плитки категорій ФІНАЛ.html` — the `FINAL_CSS` constant in its inline script is the shipping CSS, copy it from there.

**Scope: `.bs-catcards` only.** Do NOT touch `.bs-subtiles` («Інші TCG», «Аксесуари») — owner decision, they stay exactly as on production. The legacy duplicate logo block `.category-tiles` under the tiles IS removed (see §5).

---

## 1. What changes and why

The two main category tiles (Pokémon TCG, One Piece Card Game) become **one full-bleed illustration each**, in a 1:1 square, with a single white CTA «Дивитись усе» in the top-left corner. Nothing else: no category name in text, no description, no product count, no product photo, no colored fill, no border.

The owner supplied new key-art illustrations that already contain the category lettering. The old direction (D1, editorial text rows with a product photo) is superseded by owner decision on 2026-09-03 — the artwork is now the design.

**The category name is intentionally not repeated as text** — it is baked into the illustration. It survives for machines via `alt` and `aria-label` (§4).

## 2. Assets — owner must supply these before deploy

Source files (square, 1256×1256, RGB):
- `One Piece Card Game logo tiles catygory.png`
- `Pokemon trading Card Game logo tiles catygory 2.png`

Ship as:

| File | Size | Format |
|---|---|---|
| `category-tile-onepiece-1080.webp` | 1080×1080 | WebP q80 |
| `category-tile-onepiece-540.webp` | 540×540 | WebP q80 |
| `category-tile-pokemon-1080.webp` | 1080×1080 | WebP q80 |
| `category-tile-pokemon-540.webp` | 540×540 | WebP q80 |

Rationale for sizes: desktop column at 1100px container ≈ 530px wide → 1080 covers 2× DPR; mobile tile at 390px ≈ 168px wide → 540 covers 3× DPR. Target ≤160 KB per 1080 file; if a file exceeds that, drop to q72 rather than shrinking dimensions. Keep the PNG originals in the repo as masters, do not serve them.

No cropping, no recolor, no overlay baked into the files — the scrim is CSS.

## 3. DOM

Replace the contents of the existing `.bs-catcards` container (keep the container element and its position in the page, change its class):

```html
<div class="bs-cattiles">
  <a class="bs-cattile" href="/catalog/Pokemon" aria-label="Pokémon TCG — дивитись усе">
    <picture>
      <source type="image/webp"
              srcset="/image/catalog/tiles/category-tile-pokemon-540.webp 540w,
                      /image/catalog/tiles/category-tile-pokemon-1080.webp 1080w"
              sizes="(min-width:900px) 530px, 50vw">
      <img src="/image/catalog/tiles/category-tile-pokemon-1080.webp"
           alt="Pokémon Trading Card Game" width="1080" height="1080"
           loading="lazy" decoding="async">
    </picture>
    <span class="bs-cattile__scrim" aria-hidden="true"></span>
    <span class="bs-cattile__cta">Дивитись усе</span>
  </a>
  <!-- same block for One Piece → href="/catalog/One-Piece" -->
</div>
```

Order stays as on production. `href` values are the existing ones — read them from the current `.bs-catcard` markup, do not retype them.

## 4. SEO / a11y — non-negotiable

- The homepage `<h1>` and all surrounding SEO text stay untouched. This block never contained the `<h1>`; do not move or delete anything around it.
- The category description text currently inside `.bs-catcard__desc` is **removed from the tile**. If that text is indexable content the owner wants to keep on the homepage, it must be preserved elsewhere on the page — **Codex: flag this before deploy if the descriptions are unique SEO copy.** The design assumes they are decorative duplicates of the category pages' own text.
- Each tile carries both `alt` (the product-name form: «Pokémon Trading Card Game» / «One Piece Card Game») and `aria-label` on the link (name + «дивитись усе»). Without them the tile is an unlabelled image link.
- `.bs-cattile__scrim` is `aria-hidden` and `pointer-events:none`.
- Contrast: white 11.5px/700 text sits on a `rgba(10,12,16,.66)→0` gradient over artwork whose top-left region is mid-dark in both illustrations. Verified readable on both. If a future illustration has a light top-left corner, the scrim opacity must go up for that tile — do not solve it per-tile with a different CTA color.
- Touch target: the whole tile is the link (~168×168 mobile), well above 44px.

## 5. Legacy block to remove

`.category-tiles` — the duplicate logo-button row that currently sits under the tiles and repeats the same two categories. Delete it from the template (not `display:none`). It became pure duplication once the tiles carry the artwork. If it is generated by a module/setting rather than the template, disable it there and note which.

## 6. CSS (tokens only, 4px grid)

```css
.bs-cattiles{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:4px 0 12px}
.bs-cattile{position:relative;display:block;aspect-ratio:1/1;overflow:hidden;
  border-radius:var(--bs-r-lg);text-decoration:none;background:var(--bs-ink)}
.bs-cattile img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;
  transition:transform .5s cubic-bezier(.2,.7,.2,1)}
.bs-cattile__scrim{position:absolute;inset:0 0 auto 0;height:36%;pointer-events:none;
  background:linear-gradient(to bottom,rgba(10,12,16,.66),rgba(10,12,16,0))}
.bs-cattile__cta{position:absolute;left:11px;top:10px;font-size:11.5px;font-weight:700;
  color:#fff;letter-spacing:.01em}
.bs-cattile:hover img{transform:scale(1.04)}
.bs-cattile:focus-visible{outline:2px solid var(--bs-ink);outline-offset:3px}
@media (prefers-reduced-motion:reduce){
  .bs-cattile img{transition:none}
  .bs-cattile:hover img{transform:none}
}
@media (min-width:900px){
  .bs-cattiles{gap:24px;margin:8px 0 16px}
  .bs-cattile__scrim{height:34%}
  .bs-cattile__cta{left:20px;top:18px;font-size:13.5px}
}
```

Notes:
- Two columns at **every** breakpoint — the mobile layout is the pair of squares, not a stack. This was the owner's explicit pick (variant N1-A) over the full-width square.
- `background:var(--bs-ink)` on the link is the pre-load and letterbox color; with `object-fit:cover` on a square asset it should never be visible.
- `aspect-ratio` + explicit `width`/`height` on `<img>` mean zero CLS. Do not add a JS height calculation.
- The brand accent variables (`--bs-pokemon`, `--bs-onepiece`) are no longer used in this block — the color now lives in the artwork. Do not reintroduce them as borders, dots or fills.
- The 14px / 24px gaps are the two off-4px values in the block and match `--bs-r-lg` and the existing homepage grid gaps; keep them.

## 7. Verify after deploy (owner-runnable)

Production, real devices, cache cleared:

1. **390px mobile** — two square tiles side by side under the hero, equal size, gap ~14px, nothing cut off at either screen edge.
2. **360px mobile** — same, tiles ~155px, «Дивитись усе» still on one line and legible.
3. **1100px+ desktop** — two large squares in two columns, gap 24px, CTA 13.5px in the top-left of each; hover slowly zooms the artwork ~4% with no jitter or overflow.
4. Tap each tile → correct category page (Pokémon → `/catalog/Pokemon`, One Piece → `/catalog/One-Piece`).
5. The duplicate logo row under the tiles is **gone**; «Інші TCG» and «Аксесуари» tiles below look **exactly** as before the patch.
6. View-source: homepage `<h1>` present and unchanged; both tiles have `alt` and `aria-label` text.
7. Network tab: each tile loads a `.webp` (not the PNG), ≤160 KB, and the file requested at 390px is the 540 variant.
8. PageSpeed mobile on `/` — LCP no worse than before the patch. The tiles are `loading="lazy"`; if either tile is above the fold on a tall phone and LCP regresses, switch the first tile to `loading="eager"` + `fetchpriority="high"` and re-measure.
9. Reload with reduced motion enabled (iOS: Налаштування → Доступність → Рух) → no zoom on tap/hover.

Rollback: the patch touches one template block, one CSS file and adds four image files — revert the template and CSS, leave the images.
