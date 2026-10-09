# Brand

Updated 2026-10-09 against the live site (`live-snapshots/20261009_design-canon/`) and the designer's logo package. Where this file and an older spec disagree, this file wins.

## Logo (canonical from 2026-10-09)

Source: designer package `Booster Shop logo.zip` (repository root, gitignored; owner keeps the `.ai` masters). Web-ready copies live in `assets/logo/`.

| File | What | Size |
|---|---|---|
| `logo-unit-booster-shop.svg` / `.png` / `.webp` | Full lockup: wordmark "BOOSTER SHOP" (two lines) left, mark right | viewBox 1220×480; PNG 6100×2400 |
| `logo-mark-color.svg` / `.png` / `.webp` | Mark only: card with lightning bolt and wings, full colour | viewBox 500×440; PNG 2501×2200 |
| `logo-mark-monochrome.svg` / `.png` / `.webp` | Mark only, one colour `#004A9E` | viewBox 500×440; PNG 2501×2200 |
| `logo-mark-favicon.png` | Favicon source, colour mark | 513×512 |

Rules:
- Always place the file. Never re-typeset the wordmark in a font and never redraw the mark. The wordmark is outlined paths; its typeface is not named in the package.
- SVG for the web header and anything scalable; PNG/WebP where SVG is not accepted.
- Small sizes, avatars, favicons, social profile images: the mark, not the full lockup.
- One colour (stamp, engraving, single-ink print): `logo-mark-monochrome`.
- Logo colours (from the SVGs; for reference only, the files carry them): mark blue `#004A9E` → `#2290C9` (gradient), bolt `#FDE634` → `#F58124`, wordmark gold gradient `#FFD373` / `#FFB92C` → `#D18B0A`, dark edge `#8D5E07`. The designer defined no flat logo gold; do not invent one.
- Not in the package, so not defined: clear space, minimum size, a reversed (white-on-dark) version. Ask the owner before placing the logo on a dark or busy background.
- The package `.ico` is not a valid ICO file (wrong header). Build favicons from `logo-mark-favicon.png`; it is 513×512, so pad or crop to a square first.

Live state (2026-10-09), not yet migrated: the header shows the old raster `image/catalog/One Piece/BS Big logo.png` (270×84, displayed 135×42) and the favicon is the full lockup as JPG (`BS Big logo1.jpg`). Replacing both with the package files is a separate task.

## Type
- **All site text: Manrope**, self-hosted since TECH-045 WP-B (2026-09-28), variable weight 200–800. Used weights: 400/500/600/700/800.
- **Accompanying brand lettering: Teko** 500/600/700, UPPERCASE, for banners, covers, stickers and packaging next to the logo. Never in site UI. Google Fonts, OFL. Teko is an approximation of the wordmark style, not the wordmark font.
- **No monospace and no other families on the site.** JetBrains Mono and IBM Plex Sans Condensed were removed in TECH-045 WP-A (2026-09-28); payment requisites use the system monospace stack (`ui-monospace, SFMono-Regular, Menlo, Consolas, monospace`). JetBrains Mono stays allowed in mockups and specs only.
- `booster-typography.css` still declares `'Inter'` on `body`; it is overridden by Manrope and never loads. Do not use Inter.

## Type scale (live)
- H1: `.bs h1` 32/800; category header H1 26 (24 at narrower widths); content-page hero 24–32; success hero 23–28.
- H2 24/700 · H3 18/700 (mockup ramp; verify per page).
- Body text 16, line-height 1.7 (`booster-typography.css`). Product-tile name 14/600.
- Buttons: `.bs-btn` 14.5/700, height 44. Card "Купити" 16/700. Header cart trigger 15/700. Checkout "Підтвердити замовлення" 17.
- Badges 10.5/700, uppercase, +0.04em.

## Palette (live `:root`, full list in `TOKENS.md`)
Surfaces: `--bs-paper #FFFFFF` · `--bs-bg #F7F7F5` (fields, hover, media panels) · `--bs-line #E5E7EB` · `--bs-line-2 #EEF0F2`.
Text: `--bs-ink #111827` headings · `--bs-ink-2 #1F2937` body · `--bs-ink-3 #6B7280` secondary · `--bs-ink-4 #9CA3AF` placeholder.
Brand: `--bs-blue #1E3A8A` (links, active, focus) · `--bs-blue-soft #E8EEFB` · `--bs-gold #C68A00` (site accent) · `--bs-gold-soft #FBF4DC`.
Secondary action / preorder: `--bs-blue-light #3B82F6`, hover `#2563EB`.
Purchase controls: `--bs-buy #12883E`, hover/focus/open `--bs-buy-hover #15803D` (owner 2026-09-29, white text 4.55:1).
Green for non-button success (free-shipping reached, toast strip, text): `--bs-green #16A34A`, `--bs-green-d #15803D`, soft `--bs-green-soft #F3FBF6`.
Category accents (banners, tiles, burger chips only): Pokémon `#C68A00` · One Piece `#1E40AF` · Other TCG `#065F46` · Accessories `#0D9488` · Yu-Gi-Oh `#7C3AED` · MTG `#B45309`.
State: danger / sale price `#DC2626` · warning bg `#FFFBEA` / fg `#92400E` / line `#FCD34D`.
Rare Pack badge: bg `#4C0519`, text `#FDE68A` (CAT-004 SD-7).
Partners (only inside their own blocks): PUMB `#E60C2A` (soft `#FDECEE`) · monobank black `#111`. Telegram icon `#229ED9` (icon only).

Gold decision (owner, 2026-10-09): on the site gold is `#C68A00` (set globally by RD-10F on 2026-06-11). `#D4A017` is retired. Logo gold is whatever the logo files carry.

## Color rules
- Purchase green (`--bs-buy`) = only "Купити / Оформити / Додати в кошик / Підтвердити замовлення" and the header cart trigger. Never for status, headings, secondary links.
- Light blue = only preorder and secondary purchase actions.
- Gold = accent. Not a button colour; not body text on white.
- Category colours never enter header chrome, buttons or forms.
- Error states never use green.
- Discount badge is black (`--bs-ink`), no tiers. Sale price `--bs-danger` + grey strikethrough old price.

## Shape and rhythm (live)
Radii: buttons 8 (live `.bs-btn`) · badges and fields 6 (`--bs-r-sm`) · cards 10 (`--bs-r`) · large blocks and sheets 14 (`--bs-r-lg`) · chips and pills 999 (`--bs-r-pill` is not defined in `:root`; rules use the `999px` fallback).
Shadows: `--bs-sh-sm` cards, `--bs-sh-md` raised, `--bs-sh-pop` only for popovers, mini-cart, toasts, dropdowns.
Spacing: 4px grid (4, 8, 12, 16, 20, 24, 32, 40).
Buttons: height 44 (min tap target 44 on mobile); modal CTA pairs, mini-cart checkout and checkout CTAs 48.

## Tone
Clean, light, "premium without luxury": white surfaces, few shadows, pinpoint accents. Catalog tiles have no card box (UI-PCARD-D). No gradient backgrounds (exception: approved home H1 hero), no emoji in UI (inline SVG, `aria-hidden`), no Font Awesome in new work.
