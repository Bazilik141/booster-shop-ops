# Patch Handoff — CAT-004: variant families, own table + own admin tab

Date: 2026-09-19 · Notion: `3dc6bf20-bdb4-8164-b001-db2191f9f7e6`

**Executor: Claude Code · effort high.** Supersedes every earlier CAT-004 selector handoff:
`handoff_CAT-004_variant-selector_20260916.md`,
`addendum_CAT-004_variant-selector-rework_20260918.md` and
`handoff_CAT-004_variant-selector-attributes_20260919.md`. Those describe two rejected models;
read them only if you want the history, never as a spec.

Read `AGENTS.md` → "Variant products — canonical rules" first. It was rewritten on 2026-09-19
and it is the authority for everything below.

## 1. The model, and why

A variant family is a set of **ordinary independent products**. No `master_id`, no product
options, no field inheritance. Each product owns its price, stock, images, SEO and article.

Two earlier models were built and rejected on evidence:

- **Native OpenCart master/variant** — deployed 2026-09-18, tested by the owner the same day.
  The master is a template with no combination of its own, so it either misses its own family's
  selector or the code guesses which combination is "left over"; that guess collapses when one
  member is disabled. Worse, the override mechanism silently overwrites a live product's price
  or stock when a switch is left off. Production, no staging.
- **The same model with membership stored in product attributes** — rejected before any code.
  Product attributes are customer-facing and render in the specification table on the product
  page, so service data would have leaked into the shop front and then needed a second patch to
  hide it.

Hence: membership lives in the shop's own table, edited from its own tab on the product form.

**Exactly one axis per family.** Colour, or size, or deck type, or set — never two at once.
Owner decision 2026-09-19: two axes need combination matching, a rule for combinations that do
not exist, and a repeating admin UI, and the 3D line does not need them yet. Code that assumes
one axis is correct here, not a shortcut. If that changes it is a new task.

## 2. Data

One new table. **This is a database change** — `AGENTS.md` → "Patch conventions (PHP runner)"
point 6: the owner has approved it in advance for this patch, and the rollback SQL goes in the
patch header.

```
product_id   int, primary key
family_key   varchar(64), indexed   — groups siblings
axis_label   varchar(64)            — the row label, e.g. «Розмір»
value_label  varchar(64)            — the chip label, e.g. «21 см»
sort_order   int                    — chip order inside the row
```

Name it with `DB_PREFIX`. Match the engine and collation of the live `product` table rather than
assuming; read them, state what you found. Create with `IF NOT EXISTS`; rollback is a single
`DROP TABLE IF EXISTS`.

`sort_order` here is the chip order and is **not** the product's own `sort_order` field, which
drives category listing order. Two different jobs; do not reuse one for the other.

## 3. Admin

### 3.1 · The tab

`admin/view/template/catalog/product_form.twig` — add a tab. The tab strip is at `:25–36` and
the panes follow; `#tab-links` at `:477` is a good structural model for a simple pane. Put the
new tab after `#tab-attribute`.

Four fields: family key, axis label, value label, sort order.

Labels are written as literal Ukrainian text in the template, not through `{{ tab_… }}`
variables. The admin is single-language here and this keeps the patch off the language files;
an undefined language variable renders as empty, which is worse than a hardcoded word.

The **family key field has an autocomplete** over the keys already in use, in the same style as
the manufacturer and category fields on this form (`product.twig:201–261` in the list view is a
clean example of the pattern). This is not polish: a mistyped key is the one way a product
silently drops out of its family, and picking from a list removes most of that risk.

### 3.2 · Reading and writing

`admin/controller/catalog/product.php`:

- `form()` — load the current product's row and pass the four values to the template; empty
  strings and `0` when there is no row. Insert near the other per-product loads, after the
  attribute block at `:938–955`.
- `save()` — persist after the product exists. The add path returns the new id into
  `$json['product_id']` at `:1274`/`:1277`; the edit path has `$post_info['product_id']`. Write
  the row for whichever id applies, and delete the row when the family key arrives empty — an
  emptied field means "this product is no longer in a family", not "keep the old value".
- `delete()` — remove the row for each deleted product, in the loop at `:1323–1325`.

**`copyProduct()` is deliberately not touched.** A copied product starts with no family
membership. Copying a member and silently giving the copy the same value label would put two
identical chips in one row; the owner assigns membership to the copy by hand.

Put the SQL in a **new** admin model file rather than inline in the controller — a new file has
no anchors to drift and matches how everything else on this form is loaded. Four methods:
get by product, set, delete, and list distinct keys for the autocomplete.

All four fields are free text from the admin. `family_key` reaches a `WHERE` clause on the
storefront, so it goes through `$this->db->escape()` everywhere, admin and catalog alike;
`sort_order` is an integer cast.

## 4. Storefront

### 4.1 · Model — `catalog/model/catalog/product.php`

One read-only method: given a family key, return its visible members. Same anchor as the earlier
patch (before the `Get Products` docblock), same construction rules as `getProduct()` at `:117`
— the `product_to_store` / `product` join shape, the store, language, status and
`date_available` filters, and `$this->statement['discount']` / `['special']` reused rather than
rewritten. The subqueries reference the `p` alias, so keep it.

Driven by the new table, joined to the visible product so a disabled, out-of-store or
future-dated member drops out by the standard rule. A row whose `value_label` is empty is
excluded — the storefront never invents a chip label. Returns `product_id`, `price`, `special`,
`discount`, `quantity`, `stock_status_id`, `date_available`, `tax_class_id`, `value_label` and
the chip sort. `ORDER BY` the chip sort, then `product_id`. `price` resolved as
`discount ?: price`, as `getProduct()` does.

A second, trivial method returns one product's own row (key, axis label) for the open page.

### 4.2 · Controller — `catalog/controller/product/product.php`

Anchor unchanged: before `// Subscriptions`. The block no longer depends on `$master_id` or
`$product_options`.

1. Read the open product's own row. No family key → `$data['variant_groups'] = []` and stop.
   This is the regression guard for every ordinary product in the catalogue.
2. Fetch the family. Fewer than two members → `[]` and stop; a one-chip row is noise.
3. Build exactly one group: `name` = the open product's axis label (empty string if unset),
   `values` = one per member.
   - `state` — `''` buyable, `'out'` not buyable. Mirror the predicate in
     `catalog/controller/product/thumb.php`, block
     `BoosterShop RD-04f state normalization 20260601`: quantity above zero is buyable;
     otherwise a stock status **name** carrying `передзамов` / `preorder` / `pre-order`, or a
     future `date_available`, is also buyable. Only the predicate — not the `bs_eta` month
     formatting. One cached query per distinct `stock_status_id`, as that block does.
   - `href` — `$this->url->link('product/product', 'product_id=' . $id)`, the same call that
     builds the page's own canonical at `:28`.
   - `is_current`, `price`, `show_price` — unchanged rules. Every member with a visible product
     feeds the price comparison, in stock or not; equal across the group → `show_price` false
     and every `price` null; any difference → true and every member priced. Format through the
     same currency/tax path as `$data['price']` (`:370`).
   - `thumb` — `null`. The Twig guards it; picture chips are a later decision.
   - `'none'` cannot arise from this source, but keep the three-value contract: the Twig
     branches on it.

### 4.3 · Template and CSS — carried across unchanged

`patches/CAT-004_variant-selector_20260916.php` (the version on disk as of 2026-09-19) contains
the finished Twig selector block and the finished CSS block. Both are model-agnostic — they
render `variant_groups` and know nothing about where it came from. **Carry both across byte for
byte**, including their comments, the link-colour specificity fixes and the
`.bs-variant__chip.is-active.is-off` rule. Do not redesign them; they passed owner QA of their
own on 2026-09-18.

## 5. Production state — the owner's steps before this patch

`CAT-004_variant-selector_20260916.php` was applied on 2026-09-18 and must be **rolled back**
first: its Twig insert sits exactly where this patch's Twig anchor used to be, so this patch
would abort on `anchor_count_invalid` otherwise. The owner restores the five files from that
run's `_patch_backups/CAT-004_variant-selector_<timestamp>/` and clears the cache. That backup
was taken after `CAT-004-SD-7` had been applied, so the Rare Pack badge and its cache-bust
survive — verify that in the report rather than assuming it.

The owner also strips the `master_id` links and the product options from the TEST SKUs, so
nothing in the catalogue still uses OpenCart variants.

## 6. Do not touch

- The generic `{% if options %}` block, `<form id="form-product">` and everything after it in
  the catalog `product.twig`. A family member has no options, so that block renders nothing.
- Canonical, JSON-LD, `aggregateRating` — all five JSON-LD blocks stay byte-identical.
- `catalog/controller/product/thumb.php` and `thumb.twig` — read `thumb.php` for the predicate,
  change neither. `SD-7` is Done and live.
- The Rare Pack badge CSS, `.bs-badge--*`, `.bs-pcard__badge-*`.
- `copyProduct`, `addVariant`, `editVariant`, `editVariants` and every other master/variant
  method. They stay in core untouched; this patch simply stops using them.
- Category and listing controllers, the Merchant feed, `sitemap.xml`, `robots.txt`, redirects,
  `.htaccess`, checkout, payment, fiscalization.
- Any write to an existing table. The only database write is the new table's own rows.
- `AGENTS.md` and the plans — canon aligns code, never the reverse.

## 7. Delivery

One runner, `patches/CAT-004_variant-family_20260919.php`, to all eight points of
`AGENTS.md` → "Patch conventions (PHP runner)", including rule 8 for the cache-bust and rule 5
for the marker set. Plus, because of the schema change: `CREATE TABLE IF NOT EXISTS` is
idempotent on its own, the rollback `DROP` is stated in the header, and the run reports whether
the table was created or already present.

Files:

```
new: admin/model/catalog/<name>.php               (family row CRUD + key list)
     admin/controller/catalog/product.php         (form / save / delete)
     admin/view/template/catalog/product_form.twig(the tab)
     catalog/model/catalog/product.php            (family lookup)
     catalog/controller/product/product.php       (variant_groups)
     catalog/view/template/product/product.twig   (selector block, carried across)
     catalog/view/stylesheet/boostershop-ds.css   (selector CSS, carried across)
     catalog/view/template/common/header.twig     (cache-bust)
```

**Delivery mechanism, stated so it is a choice and not an accident.** This is a core patch, not
an OpenCart extension. Every customisation in this shop already lives as a core patch with
backups, and adding an extension mechanism for one tab would introduce a second way of doing
things. The cost is that an OpenCart upgrade would need it reapplied — which is already true of
everything else here. If the event-hook route is wanted later it is a contained follow-up, not
a reason to delay this.

The executor never commits, pushes, uploads, runs or deploys. Report to `diagnostics/`.

## 8. Acceptance criteria

Proven by fixture, not inspection.

| case | expected |
|---|---|
| product with no family row | `variant_groups` empty; page byte-identical to pre-patch |
| family key set on one product only | `variant_groups` empty |
| three members, in stock, equal price | three chips in declared sort order, `show_price` false |
| one member priced differently | `show_price` true, all three priced |
| member out of stock, no pre-order signal | `state='out'`, **still an `<a>` with `href`** |
| member quantity 0 with a pre-order stock status | `state=''`, ordinary chip, price counted |
| the out-of-stock member's own page | its chip is `is-active is-off`, `aria-current="page"` |
| a row with an empty `value_label` | that member absent; no warning, no fatal, others intact |
| equal chip sort values | deterministic order by `product_id` |
| family key containing a quote | safe in both admin and catalog; no SQL error, no injection |
| admin: save with an empty family key on a product that had one | the row is deleted |
| admin: delete a product | its row is gone |
| admin: copy a product | the copy has no row |
| second run of the patch | `already_applied=yes`, table not recreated, no data touched |

Plus: no PHP notice, warning or deprecation on any of the above; the diff removes no line from
any patched file except the one header cache-bust line; the carried-across Twig and CSS blocks
are byte-identical to the 2026-09-18 versions; no `!important`.

## 9. Owner QA

1. Open any product → the new tab exists and is empty.
2. Fill a three-member family. The key field offers the existing key once the first member is
   saved.
3. Open one member's page and **count the chips** — the row must show every member you entered.
   A missing chip means a mistyped key or an empty value label on that product. This is the one
   failure mode of the whole model and it is silent.
4. Sold-out member: grey, struck through, still clicks through to its page.
5. On that member's own page its chip is blue-framed, at rest and under the cursor.
6. Add to cart from one member — the order carries that member.
7. 390 px: chips wrap, target height ≥ 44 px, no horizontal scroll.
8. An ordinary product with no family row looks exactly as before, and the Rare Pack badge is
   still on the listing.

## 10. Rollback

Restore the patched files from this run's `_patch_backups` directory, clear the cache, and if
the table is to go as well, run the `DROP TABLE` from the patch header. Products are ordinary
products in both states, so dropping the table removes the selector and changes nothing else.

On any run that does not end `done=ok` + `self_delete=ok`, delete the runner from `public_html`
by hand before anything else.

## 11. Recommended status after execution

`CAT-004` stays `In progress` until the owner has run §9.
