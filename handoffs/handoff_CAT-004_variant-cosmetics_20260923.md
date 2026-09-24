# Codex Handoff — CAT-004 continuation: variant selector cosmetics

Date: 2026-09-23 | Parent: CAT-004
Executor: Claude Code · model=Sonnet · effort/thinking=medium — needs live-file
discovery (local repo does not mirror current production
`boostershop-ds.css`/`product.twig`; anchors must be confirmed against the
newest cPanel backup or a fresh live export) plus a real breakpoint check, not
a purely mechanical edit a scripted round-trip handles well. Owner decides;
record any override here without re-arguing it.

## Context

Owner built a live test of the CAT-004 variant-family selector (`.bs-variant`
block, colour axis, two test SKUs) and confirmed switching works. Two cosmetic
issues surfaced on both mobile and desktop:

1. Extra vertical gap between `.bs-price-block` and the `.bs-variant` block.
2. The variant label repeats the selected value as text
   (`<span class="bs-variant__current">Жовтий</span>` next to "Колір"), which
   is redundant — the active chip (`.bs-variant__chip.is-active`) already
   shows the selection visually.

## Goal

Tighten the price→selector spacing to the page's existing vertical rhythm, and
remove the redundant current-value text next to the selector label — without
touching the selector's function, chip states, or the family data behind it.

## What to change

- `catalog/view/template/product/product.twig` — inside the
  `.bs-variant__label` block, remove the
  `<span class="bs-variant__current">{{ ... }}</span>` node entirely. Keep
  the axis-label text ("Колір") only.
- `catalog/view/stylesheet/boostershop-ds.css` — locate the rule producing the
  gap above `.bs-variant` (top margin/padding on `.bs-variant`, or bottom
  margin on `.bs-price-block`).
  **Root cause not verified locally** — the repo's copy of this file is
  stale/absent (confirmed 2026-09-22: `catalog/view/stylesheet/boostershop-ds.css`
  does not exist under the repo root; only patch-authored fragments do).
  Confirm the live rule from the newest cPanel backup or a fresh export before
  writing the patch (AGENTS.md "UI/CSS patch discipline" #1). Also check
  override history first (#2): `CAT-004_variant-family_20260919.php` and
  `CAT-004_variant-selector_20260916.php` both already touch `.bs-variant*` —
  read what spacing they set before adding a new rule on top of it.
- Match the new gap to the spacing already used between other stacked elements
  in `.bs-product__info` (e.g. between `.bs-pp-meta` and `.bs-price-block`)
  rather than picking an arbitrary new value.

## Do not touch

- `.bs-variant__chip`, `.bs-variant__row`, `.bs-variant__text`,
  `.bs-variant__value` — active/hover/focus states and chip markup are already
  reviewed and live; this patch is spacing + one label span only.
- Product/family data, `getVariantFamilyMembers()`, the admin "Родина
  варіантів" tab, or the variant table.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout,
  payment, fiscalization, Merchant feed, schema/JSON-LD.
- Any `.bs-pcard*` (listing-card) rule — product detail page only.

## Likely files / areas

- `catalog/view/template/product/product.twig` (`.bs-variant__label` block —
  confirmed present in current live markup)
- `catalog/view/stylesheet/boostershop-ds.css` (exact rule to confirm live)
- `catalog/view/template/common/header.twig` — cache-bust bump (convention 8:
  read the token, never hardcode it)

## Acceptance criteria

- [ ] On a colour-family product page, at desktop (≥1024px), tablet (~768px)
      and mobile (390px), the gap between the price and the "Колір" selector
      matches the page's normal row spacing — not larger than it.
- [ ] `.bs-variant__label` shows only the axis label; no repeated value text.
- [ ] The active chip (`is-active`) still visually indicates the selection —
      no functional regression to the selector.
- [ ] An ordinary (non-family) product page is unaffected — no `.bs-variant`
      block renders, and no spacing side effect leaks onto `.bs-price-block`
      sitewide.
- [ ] Canonical and Product JSON-LD unchanged (template/CSS-only patch).

## QA checklist (owner runs after deploy)

- [ ] Open the test colour-family product at desktop, tablet, and 390px width
      — confirm the tightened gap and the removed duplicate text.
- [ ] Switch colour chips — confirm active-state highlight is still the only
      selection indicator.
- [ ] Open an ordinary product — confirm no visual change.

## Rollback note

Runner backs up the two touched files to
`_patch_backups/CAT-004_variant-cosmetics_<ts>/` before writing (convention
C3). Restore both files from that directory and clear the OpenCart
template/cache. No database involved.

## Recommended status after execution

CAT-004 stays **In progress** in Notion — this is a cosmetic sub-item of the
still-open owner QA pass, not closure. No Notion/dashboard write needed for
this patch alone.
