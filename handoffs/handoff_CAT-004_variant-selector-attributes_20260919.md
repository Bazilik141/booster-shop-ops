> **СКАСОВАНО 2026-09-19.** Ця редакція зберігала належність до родини в атрибутах товару, а
> вони рендеряться покупцю в таблиці характеристик. Чинний документ:
> `handoff_CAT-004_variant-family-extension_20260919.md`. Не виконувати.

# Patch Handoff — CAT-004: variant selector on independent products

Date: 2026-09-19 · Notion: `3dc6bf20-bdb4-8164-b001-db2191f9f7e6` · supersedes
`handoffs/handoff_CAT-004_variant-selector_20260916.md` and
`handoffs/addendum_CAT-004_variant-selector-rework_20260918.md`

**Executor: Claude Code.** New patch file, not a regeneration:
`patches/CAT-004_variant-selector-attributes_20260919.php`.

## 1. Why the model changed

Owner decision 2026-09-19 after a live test of the deployed patch. Native OpenCart
master/variant is **out**. `AGENTS.md` → "Variant products — canonical rules" was rewritten
the same day and is the authority; read it before anything else.

The short version, because it explains every design choice below:

- The master is a template and holds no combination of its own. It therefore cannot appear
  in its own family's selector unless the code *guesses* which combination is left over. The
  previous patch did exactly that, at this author's instruction, and the owner's test showed
  what the guess costs: disable one member and the master silently falls out of the row.
- A variant inherits every field from its master until that field's override switch is on.
  One forgotten switch overwrites a live product's price or stock. Production, no staging.
- Option rows carry price / points / weight modifiers belonging to a different feature.

So: a family is now a set of **ordinary independent products**. No `master_id`, no product
options, no inheritance. Membership is declared in three attributes.

## 2. Owner inputs required before any code

Three attributes, created by the owner in the admin, all in a customer-neutral group
(`Характеристики`, the same group `Тип товару` sits in — the group name is rendered as a
header row on the product page, so it must not say "accessories" or similar).

| attribute | role | value example |
|---|---|---|
| `Родина варіантів` | the family key — identical on every member | `PKM-JP-EXSD-STD` |
| `Характеристика варіанта` | the row label above the chips — identical on every member | `Тип колоди` |
| `Значення варіанта` | the chip label — unique per member | `Трава` |

**Blocking pre-check, same discipline as SD-7.** Before writing code, confirm each id
against `ocp5_attribute_description` for `language_id = 4` and report the three
`attribute_id` → name pairs you found. A mismatch stops the task; do not guess an id.

Chip order is the product's own `sort_order` field, then `product_id`. No fourth attribute.

## 3. What is on production right now, and what to do with it

`patches/CAT-004_variant-selector_20260916.php` was applied on 2026-09-18. It must be rolled
back **before** this patch runs — its Twig insert sits exactly where this patch's Twig anchor
used to be, so this patch would abort on `anchor_count_invalid` otherwise.

Rollback is the owner's step, stated in §8: restore the five files from that run's
`_patch_backups/CAT-004_variant-selector_<timestamp>/` directory. That backup was taken after
`CAT-004-SD-7` had already been applied, so the Rare Pack badge and its cache-bust survive the
rollback untouched. Verify that in the report rather than assuming it.

**Reused as they are.** The Twig block and the CSS block from that patch are model-agnostic:
they render `variant_groups` and know nothing about where it came from. Carry both across
byte-identically, including the specificity fixes and the `is-active is-off` rule added on
2026-09-18, and including their comments. Only the data source changes.

**Dropped from the contract.** `variant_groups[].option_id` — nothing reads it. `values[].thumb`
stays in the shape as `null`: the Twig guards it with `{% if value.thumb %}`, and picture chips
from each sibling's own image are a later decision, not this patch.

## 4. What to build

### 4.1 · Model — `catalog/model/catalog/product.php`

One read-only method that takes a family key and returns its visible members. Same anchor as
before (before the `Get Products` docblock), same construction rules as `getProduct()` at
`:117`: the `product_to_store` / `product` / `product_description` join shape, the store,
language, status and `date_available` filters, and `$this->statement['discount']` /
`['special']` reused rather than re-written.

Shape of the query, not to be copied blindly — verify every alias against the live file:

- driven by `ocp5_product_attribute` on the family attribute, `language_id` = the catalogue
  language, `text` = the key;
- joined to the visible product the same way `getProduct()` does, so a disabled,
  out-of-store or future-dated member drops out by the standard rule;
- left-joined once more to `ocp5_product_attribute` on the **value** attribute to bring back
  the chip label;
- returns per row: `product_id`, `price`, `special`, `discount`, `quantity`,
  `stock_status_id`, `date_available`, `tax_class_id`, `sort_order`, and the chip label;
- `ORDER BY p.sort_order ASC, p.product_id ASC`;
- `price` resolved as `discount ?: price`, exactly as `getProduct()` does.

**The family key is customer-invisible but it is still free text entered in the admin.** It
goes through `$this->db->escape()`. Everything else in the query is an integer cast. No
exceptions.

### 4.2 · Controller — `catalog/controller/product/product.php`

Anchor unchanged: before `// Subscriptions`. The block no longer depends on `$master_id` or
`$product_options`, so delete every trace of the master-combination inference, the
`$variant_options` filter and the value map — they have no meaning now.

1. Read the current product's three attribute values in one query (`attribute_id IN (…)`).
2. No family key → `$data['variant_groups'] = []` and stop. This is the regression guard for
   every ordinary product in the catalogue.
3. Fetch the family. Fewer than two members → `[]` and stop; a row of one chip is noise.
4. Build exactly one group:
   - `name` — the current product's `Характеристика варіанта`, empty string if unset;
   - `values` — one per member **that has a chip label**. A member with no
     `Значення варіанта` is dropped: the storefront never invents a label. That is a
     catalogue-data error, and §8 has the check that catches it.
   - `state` — `''` buyable, `'out'` not buyable. Mirror the predicate in
     `catalog/controller/product/thumb.php`, block
     `BoosterShop RD-04f state normalization 20260601`: quantity above zero is buyable;
     otherwise the stock status **name** deciding pre-order (`передзамов` / `preorder` /
     `pre-order`) or a future `date_available` is also buyable. Only the predicate — not the
     `bs_eta` month formatting. Resolve the status name through one cached query per distinct
     `stock_status_id`, as that block does.
   - `href` — `$this->url->link('product/product', 'product_id=' . $id)`, the same call that
     builds the page's own canonical at `:28`, so a chip's link always equals that sibling's
     canonical.
   - `is_current` — the member whose `product_id` is the open product.
   - `price` and `show_price` — unchanged rule: every member with a product feeds the
     comparison, in stock or not; equal across the group → `show_price` false and every
     `price` null; any difference → true and every member priced. Format through the same
     currency/tax path as `$data['price']` (`:370`).
   - `'none'` does not arise from this source — a member either resolves to a visible product
     or is absent from the family — but keep the state field's three-value contract, because
     the Twig branches on it.

Cost: two queries per product page, plus one per distinct stock status. Acceptable; say so in
the report rather than optimising it away.

## 5. Do not touch

- The generic `{% if options %}` block, `<form id="form-product">` and everything after it in
  `product.twig`. With no options on a family member that block renders nothing by itself.
- Canonical, JSON-LD, `aggregateRating`. All five JSON-LD blocks stay byte-identical.
- `catalog/controller/product/thumb.php` and `thumb.twig` — read `thumb.php` for the
  predicate, change neither. `SD-7` is Done and live.
- The Rare Pack badge CSS, `.bs-badge--*`, `.bs-pcard__badge-*`.
- Category and listing controllers, the Merchant feed, `sitemap.xml`, `robots.txt`,
  redirects, `.htaccess`, checkout, payment, fiscalization.
- Any database write, and any product data.
- `AGENTS.md` and the plans — canon aligns code, never the reverse.

## 6. Files and delivery

```
catalog/model/catalog/product.php            (family lookup)
catalog/controller/product/product.php       (variant_groups)
catalog/view/template/product/product.twig   (the 2026-09-18 selector block, carried across)
catalog/view/stylesheet/boostershop-ds.css   (the 2026-09-18 selector CSS, carried across)
catalog/view/template/common/header.twig     (cache-bust)
```

Runner conventions: `AGENTS.md` → "Patch conventions (PHP runner)", all eight points. Rule 8
in particular — the cache-bust token is read by path prefix and replaced wholesale, and
`header.twig` is **not** part of the idempotence marker set.

The executor never commits, pushes, uploads, runs or deploys. Report to `diagnostics/`.

## 7. Acceptance criteria

Proven by fixture, not by inspection.

| case | expected |
|---|---|
| product with no family key | `variant_groups` empty, page byte-identical to pre-patch |
| family key set, only one member has it | `variant_groups` empty |
| three members, all in stock, equal price | three chips, `show_price` false |
| one member priced differently | `show_price` true, all three priced |
| one member out of stock, no pre-order signal | `state='out'`, **still an `<a>` with `href`** |
| one member quantity 0 with a pre-order stock status | `state=''`, ordinary chip, price counted |
| open the out-of-stock member's own page | its chip is `is-active is-off`, `aria-current="page"` |
| a member with no `Значення варіанта` | absent from the row; no warning, no fatal, other chips intact |
| members with equal `sort_order` | deterministic order by `product_id` |
| family key containing a quote | query is safe; no SQL error, no injection |

Plus: no PHP notice, warning or deprecation on any of the above; the diff removes no line from
any of the five files except the one header cache-bust line; the Twig and CSS blocks are
byte-identical to the 2026-09-18 versions; no `!important`.

## 8. Owner steps

Before the patch:

1. Create the three attributes from §2 in the `Характеристики` group and send their ids.
2. Undo the test setup: on the TEST SKUs remove the `master_id` links and the product options,
   so nothing in the catalogue still uses OpenCart variants.
3. Roll back `CAT-004_variant-selector_20260916.php` from its `_patch_backups` directory and
   clear the cache. Confirm the Rare Pack badge is still on the listing afterwards — it should
   be, the badge lives in files that rollback does not touch.

After the patch:

4. Fill the three attributes on a two- or three-member test family and open one of its pages.
5. **Count the chips.** The row must show every member you entered. A missing chip means a
   missing or mistyped attribute on that product — this is the failure mode of the whole model
   and the only one that is silent.
6. Sold-out member: its chip is grey and struck through, and still clicks through to its page.
7. On that member's own page its chip is blue-framed, in rest and under the cursor.
8. Add to cart from one member — the order must carry that member, not a sibling.
9. 390 px: chips wrap, target height ≥ 44 px, no horizontal scroll.
10. An ordinary product with no family attributes looks exactly as before.

## 9. Rollback

Restore the five files from this run's `_patch_backups` directory and clear the cache. Nothing
is written to the database. Clearing the family attribute off the products also removes the
selector without touching code.

On any run that does not end `done=ok` + `self_delete=ok`, delete the runner from
`public_html` by hand before anything else — it is publicly executable by URL until it
self-deletes.

## 10. Recommended status after execution

`CAT-004` stays `In progress` until the owner has run §8. Notion status is written by Claude
(chat).
