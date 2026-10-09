# Global rules

Apply to every UI task unless a spec for that area says otherwise.

## Implementation
- One stylesheet: `boostershop-ds.css`, loaded after Bootstrap. All new classes prefixed `bs-`. Do not duplicate tokens or component CSS into templates; no inline hex.
- `<body class="bs …">` scopes base typography.
- Recreate mockups in Twig; do not ship prototype HTML/JSX.
- Product card is rendered by the `product/thumb` controller/template; edit it there, not in `category.twig`.
- Keep existing JS hooks, IDs, `name`/`value` attributes and module markup that other code depends on. Each spec lists its hooks.
- Remove obsolete markup from templates instead of hiding it with `display:none` or scripts (unless a spec says to keep an element in the DOM, e.g. `#button-filter`).
- Inline SVG icons with `aria-hidden="true"`. No emoji in UI, no Font Awesome in new work. Hardcode `width`/`height` on SVGs so they survive missing CSS.
- No new colors, fonts or off-grid spacing without a design decision. Contrast ≥ 4.5:1 for text.

## Interaction and layout
- Tap targets ≥ 44×44 on mobile (steppers, close buttons, clear ×, chips via `::before` extension).
- Mobile-first. Check 360/390, 768, 1024, 1440. Below 992 the content container is fluid with the header's side paddings (UX-003 grid decision).
- Header is sticky on all pages including checkout. Overlays (burger, search, mini-cart, toast, modals) must sit above it.
- Mini-cart opens only on click of the header cart button. Add-to-cart shows a toast, never auto-opens the drawer.
- Horizontal scroll and truncated names for subcategory navigation on mobile category pages are rejected (C3 grid instead). Exception: the filter row (UX-003 C) scrolls subcategory chips at 768/390.
- Sealed is the default state: no badge on catalog tiles. Badges only for exceptions (discount, low pull, preorder, out of stock, Rare Pack). The light green «В наявності» badge is a deliberate exception (owner 2026-10-09).
- Out of stock: dimmed photo, single status, button "Повідомити про наявність". Preorder: amber badge + ETA line + blue "Передзамовити".
- Purchase controls use `--bs-buy` / `--bs-buy-hover`, never `--bs-green`.
- Logo: place a file from `assets/logo/`; never rebuild it in a font or CSS.
- Disabled purchase CTA: grey (`--bs-line-2` fill, `--bs-ink-2` text, `--bs-line` border), never semi-transparent green.

## Content
- Approved Ukrainian copy is used verbatim. Admin-driven values (free-shipping threshold, etc.) are never hardcoded in copy.
- Reviews: no fake ratings. If a product has 0 reviews, link to external reviews (Telegram / OLX).
- Payment-partner copy, names and logos follow bank materials exactly; if in doubt, stop and ask the owner.

## Do-not-touch zones (unless the task is explicitly about them)
- Checkout, payment (Hutko, `hutko.response`), fiscalization (Checkbox), order submit, totals, shipping calculation, coupon/First15 logic. Reskins are markup + CSS only.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, meta robots, filter URLs and params.
- Product schema / JSON-LD, Merchant feed, GTIN, reviews markup. Do not add FAQPage JSON-LD without a separate ticket.
- GA4 / purchase scripts.
- Vendor module files (e.g. `extension/ps_live_search/**`); override with CSS / init only.

## Delivery
- One task = one patch file in `patches/`, idempotent, with backup (`backups/<date>-<task>/`) and a stated rollback trigger.
- Executor does not commit, push or deploy; the owner runs the patch on production.
- Before patching, compare evidence files with fresh production state.
