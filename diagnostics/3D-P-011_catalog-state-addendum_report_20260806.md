# 3D-P-011 — Addendum: live catalog and data-model state

**Date:** 2026-08-06
**Author:** Claude (chat) — read-only diagnostic, no patch, no write to any live system
**Relation:** extends `diagnostics/3D-P-011_native-variant-feasibility_report_20260806.md`
(same day). That report answered "does the storefront render variants?" (no).
This one answers "what does the catalog actually contain today, and what does that
imply for scope?"

**Source:** `backup-8.5.2026_10-49-27_boosters.tar.gz` (newest owner-provided cPanel
backup). Extracted to a temporary path only, never unpacked into the repository, per
`AGENTS.md`. Files read:

- `mysql/boosters_ocart49.sql` (85 MB dump — parsed for specific tables only)
- `homedir/public_html/catalog/controller/product/product.php`
- `homedir/public_html/catalog/view/template/product/product.twig`

## Findings

### F1 — Database prefix is `ocp5_`, not `oc_`

Verified from `CREATE TABLE` names in the dump. `meta/dbprefix` in the cPanel backup
contains `0` — that is a cPanel flag, **not** the OpenCart table prefix. Any patch or
SQL must use `ocp5_`.

### F2 — Single storefront language

`ocp5_language` has exactly one row: `language_id = 4`, `uk-ua`. No multi-language
duplication to handle for a variant selector.

### F3 — Native master/variant columns exist but have never been used

`ocp5_product` carries OpenCart 4.1 core columns `master_id`, `variant`, `override`.
Across all **60** product rows: **0** rows with `master_id != 0`, **0** rows with a
non-empty `variant` payload. The admin "Додати варіант" feature has never been
exercised on this store.

### F4 — The product-options code path is dead in production

`ocp5_product_option` has **0** rows for all 60 products. `ocp5_option` contains only
OpenCart's stock demo option definitions (including `option_id 11` = "Size", type
`select`) with no product ever attached.

Consequence: the entire `{% if options %}` block in `product.twig:183-260` has never
rendered on this live store. It is untested markup in this custom theme, not proven UI.
Any plan that says "reuse the existing options block" must budget for first-time
styling/QA of that block, not treat it as working.

### F5 — No active database template override for the product route

`ocp5_theme` is empty (`AUTO_INCREMENT=2`, no rows). A file-level edit to
`catalog/view/template/product/product.twig` will take effect after a normal cache
refresh; the "Twig change invisible because of a DB theme override" failure mode
described in the project contract does **not** currently apply. Re-verify against the
newest backup at implementation time — this is a mutable admin-side state.

### F6 — There is no 3D-print category and no shippable 3D product

`ocp5_category_description` holds 11 categories, all TCG or accessories
(Pokémon, One Piece, Yu-Gi-Oh!, MTG, Інші TCG, Набори, Аксесуари, …). No 3D-print
category exists.

The only 3D-adjacent catalog row is:

| field | value |
|---|---|
| `product_id` | 118 |
| name | `3д СКЮ` |
| `model` | `FIG-CHARM-001` |
| `price` | 50.0000 |
| `quantity` | 5 |
| `image` | `catalog/profile-pic.png` (default placeholder) |
| `ocp5_product_to_category` | **no rows** |
| `ocp5_seo_url` | **no rows** |
| `ocp5_product_image` | **no rows** |

This is a placeholder/test row, not a listing. **3D-P-011 therefore has no real
production page to modify today.** The task's real precondition is an owner decision
about whether the 3D-print line is sold on `boostershop.website` at all, and under
which category.

### F7 — Structured data and canonical are per-URL, hand-written

- Canonical: `catalog/controller/product/product.php:33` —
  `$this->document->addLink($product_url, 'canonical')`.
- Product JSON-LD: `product.twig:909-987`, hand-written, with `@id`, `url` and
  `offers.url` all bound to `share`, `sku` bound to `model`, price from
  `special ? special : price`, availability derived from the `stock` string.

Consequence: any variant model that produces a **second URL** yields two fully
independent `Product` entities, each self-canonical, each a separate Merchant/feed
item. That is a schema/SEO risky-zone decision (`AGENTS.md`) and must pass
`bs-seo-risk-gate` + `bs-merchant-schema-qa` before implementation, not after.

Side note (not this task's scope): the template already emits an `aggregateRating`
block gated on `{% if review_status and rating %}`. Flagged only because a
second variant URL would duplicate it.

### F8 — Add-to-cart is a fixed-product_id jQuery post

`product.twig:349` posts a hidden fixed `product_id`; submit handlers at
`product.twig:821-853` (main form) and `:1221-1225` (sticky ATC) both POST
`#form-product` to `index.php?route=checkout/cart.add`. Nothing re-reads price, stock
or images when any input changes. A variant selector that swaps in place must add that
refresh logic; a selector that navigates to the sibling URL does not.

## Not answered by this addendum

1. Whether the order → CRM sync keys on product `model`/SKU per order line, and whether
   it carries OpenCart option text. This decides whether an option-based size model
   silently breaks per-size CRM stock. Needs a bounded read of the Apps Script order
   sync (`doPost` path).
2. Per-size business data for the trigger case (sizes, price per size, photos per size,
   stock/print-time per size, SKU per size in the 3D-P nomenclature tracker).
3. Owner decisions: variant model, catalog placement, Merchant-feed inclusion, priority.
4. Duplicate `3D-P-011` / `3D-P-012` rows in `context-index.md` and the collision note in
   `ROADMAP_SOP.md` are still unreconciled (flagged 2026-08-06). A handoff should not be
   written against an unreconciled ID.
