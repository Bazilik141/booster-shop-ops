# Codex Handoff — CAT-004 continuation: product-info column reflow

Date: 2026-09-23 | Parent: CAT-004
Executor: Claude Code · model=Sonnet · effort/thinking=high — multi-file
(twig + CSS), multi-breakpoint layout change with a conditional (pre-order)
row; needs live-file discovery (repo does not mirror current production
CSS/twig) and real visual verification at 3 breakpoints, not just token
values. Owner decides; record any override here without re-arguing it.

## Context

On the CAT-004 test product, the right-hand info column
(`.bs-product__info`) is now taller than the left-hand gallery column,
pushing the description tabs down. Owner asked for a reflow of two
components in that column. **Both classes are shared and render on every
product page**, not only variant-family ones — this is a sitewide layout
change, not scoped to the test SKU.

1. `.bs-trust-strip` (three items: "Гарантія оригінальності", "Швидка
   відправка" / "Привеземо під замовлення", "Telegram підтримка") — each item
   currently stacks its icon above its text.
2. `.bs-pp-meta` — currently a vertical stack of full-width rows: Виробник, В
   наявності, and — pre-order only — a Доставка/ETA row ("<em>орієнтовно</em>
   3–4 тижні"), added by `UI-FIX_mobile-desktop-polish_20260903.php`.

## Goal

Reduce the info column's height:

- **Desktop:** `.bs-trust-strip__item` → icon to the left of its text, one
  row per item (not icon-over-text). `.bs-pp-meta` → two columns: row 1 = В
  наявності (left) + Виробник (right); row 2, pre-order only = Доставка/ETA
  (left), right cell empty.
- **Mobile:** `.bs-trust-strip__item` only — compact it so the icon sits on
  the same line as the first line of text, instead of a taller stacked block.
  `.bs-pp-meta` unchanged on mobile (stays stacked, full-width rows).

## What to change

- `catalog/view/stylesheet/boostershop-ds.css`:
  - `.bs-trust-strip__item` — icon/text arrangement from stacked to inline
    (icon left of text) at desktop widths; a lighter version at mobile widths
    (icon inline with the first text line only, not a full rebuild).
  - `.bs-pp-meta` — 2-column grid at desktop widths only. Assign each
    `.bs-pp-meta__row` to its target column/row **explicitly**, via modifier
    classes added in the twig (see below) — not `:nth-child`, which breaks
    silently if a row is added or removed later (e.g. the conditional ETA
    row).
- `catalog/view/template/product/product.twig` — add modifier classes to the
  three `.bs-pp-meta__row` blocks: e.g. `bs-pp-meta__row--stock`,
  `bs-pp-meta__row--manufacturer`, `bs-pp-meta__row--eta` (on the existing
  `{% if _is_preorder %}` row).
- `catalog/view/template/common/header.twig` — cache-bust bump (convention 8).

**Root cause / exact current rules not verified locally.** Confirm against
the newest cPanel backup or a fresh live export before patching (same gap as
the companion handoff — the repo does not carry current production CSS).
Also confirm whether `.bs-trust-strip` is used **only** on the product
template or is a shared component rendered elsewhere too —
`cat002_5c_mobile_visual_breadcrumb_20260630.php` already sets a
`#content > .bs-trust-strip { border-bottom: 0 !important; }` rule at
`max-width: 640px`, which may be the same instance. If shared, this patch's
blast radius is larger than the product page and needs QA wherever else it
renders.

## Do not touch

- Trust-strip icon SVGs, copy text, and link targets — layout only.
- `.bs-pp-meta__label` / `.bs-pp-meta__val` / `.bs-pp-meta__link` content, or
  the stock/manufacturer values themselves — arrangement only, not data.
- `_is_preorder` logic or the ETA copy ("орієнтовно 3–4 тижні") — reposition
  the existing row, don't rewrite its condition or text.
- `.bs-price-block`, `.bs-variant*` — covered by the separate
  `CAT-004_variant-cosmetics_20260923` handoff; do not bundle the two.
- `.bs-pcard*` (listing cards) — product detail page only.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout,
  payment, fiscalization, Merchant feed, schema/JSON-LD.

## Likely files / areas

- `catalog/view/stylesheet/boostershop-ds.css` (exact rules to confirm live)
- `catalog/view/template/product/product.twig` (`.bs-pp-meta` block,
  `.bs-trust-strip` block)
- `catalog/view/template/common/header.twig` (cache-bust)
- Verify whether `.bs-trust-strip` also appears in another shared template
  (footer/category) before assuming product-page-only scope.

## Acceptance criteria

- [ ] Desktop (≥1024px): trust-strip items show icon-left-of-text, one visual
      row per item, still 3 items in the strip.
- [ ] Desktop, in-stock non-preorder product: one `.bs-pp-meta` row — В
      наявності (left), Виробник (right).
- [ ] Desktop, pre-order product: a second row appears below with the ETA
      text on the left; right cell of that row has no orphaned content.
- [ ] Mobile (390px): trust-strip icon sits inline with the first text line;
      `.bs-pp-meta` rows remain stacked/full-width, unchanged from today.
- [ ] Tablet (~768px) checked — no awkward half-state between the mobile and
      desktop rules.
- [ ] Info column height on the test product moves closer to the gallery
      column's height (measure before/after; need not be exactly equal).
- [ ] No horizontal scroll at 390px; trust-strip and meta rows keep the
      ≥44px tap-target convention already used on this page.

## QA checklist (owner runs after deploy)

- [ ] Test colour-family product, desktop: confirm trust-strip row layout and
      the meta 2-column layout.
- [ ] Same product, 390px: confirm trust-strip compaction; confirm meta rows
      are unchanged (still stacked).
- [ ] A pre-order product (any SKU with the "передзамовлення" ETA row):
      confirm the ETA row lands on its own second row, left side, at desktop
      width.
- [ ] Any other page that might render `.bs-trust-strip` (per the open
      verification above) — spot-check for unintended layout change.
- [ ] Description tabs position — confirm the columns are closer to level.

## Rollback note

Runner backs up the touched files to
`_patch_backups/CAT-004_info-column-reflow_<ts>/` before writing (convention
C3). Restore from that directory and clear the OpenCart template/cache. No
database involved. If `.bs-trust-strip` turns out to be shared beyond the
product page and the reflow regresses something else, the same rollback path
applies — no separate DB or data concern.

## Recommended status after execution

CAT-004 stays **In progress** — same as the companion patch; no Notion/
dashboard write for this alone.
