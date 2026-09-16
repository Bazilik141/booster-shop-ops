# Patch Handoff — CAT-004: variant selector on the product page

Date: 2026-09-16 · Notion: `3dc6bf20-bdb4-8164-b001-db2191f9f7e6` · supersedes `3D-P-011` (closed 2026-09-15)
**Executor: Codex · model=Sol · effort=xhigh** — the change is fully specified below and the files are
identified, which would normally argue for `Terra/medium-high`. `Sol/xhigh` is proposed anyway because
this lands on the product page of a production shop with no staging, and because the SEO/schema gate
classified the surrounding work **High risk**. The patch itself must stay away from canonical and
JSON-LD (see §5), and getting that boundary wrong is the expensive failure. **Owner decides.**

> Owner approved the High-risk gate on 2026-09-15. Approval covers writing and deploying this patch
> under the evidence and rollback plan in `plans/3D-P-011_seo-schema-gate_20260912.md`. It does **not**
> cover anything outside §4.

## 1. Task ID

`CAT-004`

## 2. Context

OpenCart 4.1.0.3 ships a native master/variant data model: `ocp5_product` carries `master_id`,
`variant` and `override`. The backend half works — verified on this store, where the owner created
`TEST SKU 2` as a variant of `TEST SKU` with its own `model`, price and quantity.

The storefront half does not exist. Read from the **live** files the owner exported from production on
2026-09-12 (both are in the repo root as `product.php` and `product.twig`; move or ignore them, they are
snapshots, not tracked source):

| Fact | Evidence |
|---|---|
| Controller resolves `master_id`, then loads the master's options | `catalog/controller/product/product.php:442-448` |
| An option that **defines** the current variant is excluded from the page entirely | same file, `:451` — `!isset($product_info['override']['variant'][$option['product_option_id']])` |
| No sibling-variant list is built anywhere in the controller | whole file — searched, absent |
| Canonical is per current product | `:33` `addLink($product_url, 'canonical')` |
| `share` feeds JSON-LD `@id` / `url` / `offers.url` | `:515` and `product.twig:945-969` |
| Options block renders stock Bootstrap radios; value price shows as `(prefix price)` | `product.twig:193-255` |
| Add-to-cart posts a fixed hidden `product_id` | `product.twig:383` |
| JSON-LD `sku` from `model`, price from `special ?: price` | `product.twig:964`, `:971` |

Consequence: a variant page today shows **no** switcher at all — not a broken one. That is why the
owner's admin-side test produced two unlinked pages.

The first production family will be nine Pokémon ex Start Decks (`plans/CAT-004_pkm-ex-start-decks_identifier-canon-draft_20260915.md`),
one characteristic (deck type), nine values. The 3D-print families will later use two characteristics
(size × colour), so the contract below must not hardcode one group.

## 3. Goal

On any product that belongs to a master/variant family, the product page renders a selector whose
values are ordinary links to the sibling variants' own URLs, styled as the design system's selection
chips. Choosing a value navigates. Nothing about price, stock, images, cart, canonical or structured
data changes mechanism — those are already correct per page because each variant is a real product.

Non-goal, explicitly: no in-place swapping, no `pushState`, no AJAX re-read of price or gallery.

## 4. What to change

One work package, one patch file.

### 4.1 Model — a sibling lookup

Add a read-only method that, given a master product id, returns the family: the master itself plus
every product whose `master_id` equals it, with `product_id`, `model`, `variant` (decoded), `price`,
`special` where the existing model already resolves one, `quantity`, `stock_status_id`, `image`,
`status`, `date_available`.

The executor must verify against the live `catalog/model/catalog/product.php` how `getProduct()` already
decodes the `variant` column (the 2026-08-06 diagnostic recorded it at `:122`) and reuse that decoding
rather than writing a second JSON path. Do not invent an API: confirm the actual query builder, price
and special resolution used by the neighbouring methods and follow them.

Exclude from the family: rows with `status = 0`, and rows outside their `date_available` window if the
neighbouring catalogue methods already apply that filter — match existing behaviour, do not invent a
new visibility rule.

### 4.2 Controller — build `variant_groups`

In `catalog/controller/product/product.php`, after the existing options loop, build a new template
variable. Shape, exactly:

```
$data['variant_groups'] = [
  [
    'option_id'   => int,
    'name'        => string,      // option name, e.g. "Тип колоди"
    'show_price'  => bool,        // computed, see the price rule
    'values'      => [
      [
        'label'      => string,   // option value name
        'href'       => string|'',// '' when no sibling exists
        'available'  => bool,
        'is_current' => bool,
        'price'      => string|null, // formatted, only when show_price
        'thumb'      => string|null, // resized option-value image, or null
      ], ...
    ],
  ], ...
];
```

Rules:

1. **Source of the value list** is the master's options, already loaded at `:448`. Use that order — do
   not re-sort. Every value of every variant-defining option appears, including ones with no sibling.
2. **Mapping** value → sibling: a sibling's decoded `variant` maps `product_option_id` to
   `product_option_value_id`. Match on that. If two siblings claim the same value, take the first by
   the family's existing sort and do not fail the page.
3. `available` is false when there is no sibling for that value, or the sibling's quantity is not
   positive. `href` is `''` in the first case, and the sibling's normal product link in the second —
   an out-of-stock variant still has a page worth visiting.
4. `is_current` is true for the value the currently rendered product represents.
5. **Price rule, per group, not per value.** If every *available* value in the group resolves to the
   same displayed price, `show_price` is false and every `price` is null. If any differs, `show_price`
   is true and every available value carries its formatted price. Unavailable values never carry a
   price.
6. Build nothing when the product has no `master_id` and no variants — `variant_groups` stays `[]`, and
   the page renders exactly as today. **This is the regression guard for all 60 existing products.**
7. Format prices through the same currency/tax path the page already uses for `$data['price']`.

### 4.3 Template — render the selector

In `catalog/view/template/product/product.twig`, insert a block inside `#product` **before**
`<form id="form-product">`, so it sits between the price block and the quantity/cart row. Guard it with
`{% if variant_groups %}`.

Per group: a label (design-system field label) and a row of chips. Each chip is an `<a href>` when
`available` and `href` are set, and a `<span>` otherwise — an unavailable value must not be a link.

Markup contract, so QA can assert on it:

- wrapper `bs-variant`, one per group
- label `bs-variant__label`, with the current value echoed in `bs-variant__current`
- row `bs-variant__row`
- chip `bs-variant__chip`, plus `is-active` on the current value and `is-off` on unavailable
- price line inside the chip: `bs-variant__price`
- swatch, when `thumb` is present: `bs-variant__thumb`

Do not reuse or modify the `{% if options %}` block. It stays exactly as it is for genuine product
options.

### 4.4 Styles

Add the `bs-variant*` rules to `catalog/view/theme/<theme>/stylesheet/boostershop-ds.css` (confirm the
real path in the live tree). Values are taken from the approved mockup and from tokens already in that
file — do not introduce new colour literals:

| State | Spec |
|---|---|
| available | background `#FFFFFF`, border `1px solid var(--bs-line)`, radius `var(--bs-r-sm)`, min-height 44px (46px under 768px), font 14px/600, colour `var(--bs-ink-2)` |
| hover | border `var(--bs-ink-3)`, background unchanged |
| active | border `var(--bs-blue)`, background `var(--bs-blue-soft)`, colour `var(--bs-blue)`, weight 700, `box-shadow: 0 0 0 2px rgba(30,58,138,.08)` |
| unavailable | background `var(--bs-bg)`, border `var(--bs-line-2)`, colour `var(--bs-ink-4)`, `cursor: not-allowed`, a single diagonal strike via `linear-gradient`; a `thumb` inside is dimmed to `opacity: .35` |
| row | `display: flex; gap: 8px; flex-wrap: wrap` |

Green is reserved for purchase actions — the selector is blue only.

**Mandatory with any change to `boostershop-ds.css`:** bump the `?v=` query on that stylesheet in
`catalog/view/template/common/header.twig` in the same patch. Without it browsers keep the old CSS and
the chips render unstyled for returning visitors.

### 4.5 Sort order of chips

Follow the option value `sort_order` already stored in the master's options. No secondary sorting, no
alphabetisation.

## 5. Do not touch

- **Canonical** — `product.php:33` stays as is. Each variant is self-canonical. This is the gate's
  decision, not an oversight.
- **JSON-LD** — `product.twig:941-1020` unchanged. `sku`, price and availability already resolve
  correctly per variant because each variant is a separate product. No `ProductGroup`, no
  `isVariantOf` in this patch — that waits on TECH-008.
- **`aggregateRating`** — leave its `review_status and rating` gate exactly as it is.
- The `{% if options %}` block, `#form-product`, the hidden `product_id` at `:383`, both submit handlers
  (`:821-853` and the sticky ATC at `:1221-1225`), and the quantity control.
- Category and listing templates. The owner controls what appears in a category through
  `product_to_category` links, not through code: for TCG only the master is linked to the category and
  the whole family to the subcategory; for every other product type one card per family. **No listing
  code is in scope.**
- The Merchant feed, sitemap, `robots.txt`, redirects, `.htaccess`, checkout, payment, fiscalization.
- Any product data. This patch creates, renames and deletes nothing in the catalogue.
- `ocp5_seo_url` rows — the owner creates one per variant by hand in the admin.

## 6. Likely files / areas

- `catalog/controller/product/product.php` — new block after the options loop (`:450-477`).
- `catalog/model/catalog/product.php` — new sibling lookup. **Not yet read by Claude**; the executor
  must verify its real contents before writing.
- `catalog/view/template/product/product.twig` — new block before `<form id="form-product">` (`:192`).
- `catalog/view/theme/<theme>/stylesheet/boostershop-ds.css` — `bs-variant*` rules.
- `catalog/view/template/common/header.twig` — CSS cache-bust bump.

Line numbers come from the 2026-09-12 production export and must be re-verified against the tree the
patch runs on.

**Environment constraints, both verified previously on this host:** PHP is 8.0.30 — no `enum`,
`readonly` or `never` anywhere in the patch or the runner, they die at parse time before any guard
runs. The mysqli build has no mysqlnd — if the patch runner touches the database directly,
`get_result()` and `fetch_all()` are fatal; use `result_metadata()` + `bind_result()`.

**Delivery.** One patch file into `patches/`, owner uploads to `~/public_html` and runs
`php <patch>.php`. Seven mandatory patch conventions from `AGENTS.md` apply, including the anchor
pre-check, the `_patch_backups/<patch>-<ts>/` backup before any write, and the `php -l` gate with
restore-on-fail. The executor never commits, pushes or deploys.

## 7. Acceptance criteria

1. `php -l` passes on every touched PHP file; the patch self-deletes per convention C7.
2. A product with **no** variants renders byte-identically to before the patch, except for the CSS
   cache-bust. Prove it with a before/after fetch of one existing product page.
3. On a family member, `variant_groups` renders one row per variant-defining option, with every value
   of that option present.
4. The current value carries `is-active`; a value with a live sibling is an `<a>` whose `href` is that
   sibling's SEO URL; a value with no sibling is a `<span class="... is-off">` with no `href`.
5. Clicking a chip lands on the sibling's own URL, and that page's `<h1>`, price, stock line, gallery
   and `<link rel="canonical">` are the sibling's own.
6. Price rule: with all family prices equal, no `bs-variant__price` node exists anywhere in the group;
   with one price changed in the admin, every available chip in that group has one.
7. `<link rel="canonical">` and the `Product` JSON-LD block are byte-identical before and after the
   patch on both a variant page and a non-variant page, apart from values that differ because it is a
   different product.
8. The add-to-cart form still posts the current page's `product_id`; adding to cart from a variant page
   puts that variant in the cart.
9. At 390px width the chip row wraps, every chip is at least 44px tall, and the page has no horizontal
   scroll.
10. The bounded diff touches only the five files in §6.

## 8. QA / smoke test — owner

Not a checkout or payment change, so `bs-checkout-smoke` is not required. It is inside the High-risk
perimeter of the SEO gate, so the evidence plan in `plans/3D-P-011_seo-schema-gate_20260912.md` §8
applies: capture canonical, JSON-LD and `sitemap.xml` before the patch and again after, and diff them.

Before anything: **take a database dump.** Convention C3 backs up files only.

1. Deploy the patch in a window where you are at the computer.
2. Open any ordinary product. Ctrl+F5. Confirm it looks exactly as before and no empty selector block
   appeared.
3. On `TEST SKU` / `TEST SKU 2`, confirm the chips appear and navigate between the two pages.
4. Add to cart from the variant page and confirm the cart holds the variant, not the master.
5. Check the same on a phone.
6. Then, and only then, build the first real family.

**The admin trap, and it is the one that loses data.** A variant inherits every field from its master
until you switch that field's override on. Saving a variant with an override off overwrites its price,
stock or images with the master's. Working order for each variant: open it, switch on override for
price, quantity and images **first**, enter the values, then save. Re-open and confirm the values held
before moving to the next one.

Each variant also needs its own SEO URL row. Without one the chip points at
`index.php?route=product/product&product_id=N`, which Search Console files as an alternate page and the
feed would inherit.

## 9. Rollback note

Files: restore from `_patch_backups/<patch>-<ts>/` — five files, all text, no migration. The patch
writes nothing to the database, so there is nothing to undo there.

Fastest data-side rollback if a family misbehaves: set the variant products' status to disabled in the
admin. Their pages disappear immediately and no code has to be touched.

Asymmetric risk worth knowing before you revert: reverting the template and CSS is free, but reverting
the controller while variant products stay live returns you to the pre-patch state — nine unlinked
pages — not to a broken one.

## 10. Recommended status after execution

`In progress` until the owner has run §8 steps 1–5 on production. Then `Done`. Notion status is written
by Claude (chat); the executor may update the `ROADMAP_FLOW` row for `CAT-004` within this authorised
implementation.
