# Patch Handoff — CAT-004 / SD-7: «Rare Pack» badge on the listing tile

Date: 2026-09-18 · Notion: `3dc6bf20-bdb4-8164-b001-db2191f9f7e6` (subtask `SD-7`) · sibling work
package: `handoffs/addendum_CAT-004_variant-selector-rework_20260918.md`

**Executor: Claude Code · effort medium.** Four files, one new badge, one flex change, one data lookup.
Fully specified below. Independent of the variant selector: that lives on the product page, this lives
in the listing tile. The two can run in either order or in parallel.

## 1. Task ID

`CAT-004` → `SD-7`

## 2. Context

`Rare Pack` is a Booster Shop product line: a single sealed pack from an old set, bought individually,
sold scarce and dearer. Its canon is `plans/CAT-004_op-rare-packs_identifier-canon_20260916.md`. Four
SKUs exist today — `OP-JP-EB01-RPK`, `OP-JP-OP01-RPK`, `OP-JP-OP05-RPK`, `OP-JP-OP06-RPK` — and the
line will grow.

The line needs to be recognisable in category, subcategory and search listings. Owner decision
2026-09-18, in his words: the badge takes the place of the pre-order badge when there is no pre-order
badge, and sits under it when there is.

### What was read from live source on 2026-09-18 — do not re-derive it, but do re-verify line numbers

`catalog/view/template/product/thumb.twig` — the real tile. It is `.bs-pcard`; the older
`body.bs .product-thumb .bs-pcard-badge--*` system in the stylesheet is **not** used by this template.

- Left corner `.bs-pcard__badge-tl` currently holds exactly one badge:
  `{% if bs_is_pre %} … {% elseif bs_is_out %} … {% endif %}`. That `if/elseif` is what becomes a
  column.
- The discount badge is a **different corner**: `.bs-pcard__badge-tr`, class `.bs-badge--discount`,
  ink `#111827` on white text. Red in the tile is the sale *price*, not a badge.
- `catalog/view/stylesheet/boostershop-ds.css:125` — base `.bs-badge`: 10.5px, weight 700,
  letter-spacing .04em, padding 3px 7px, radius `var(--bs-r-sm)`, **uppercase**. So the label is
  written in normal case in the template and rendered uppercase by CSS.
- `.bs-badge--preorder` (`#fef3c7` / `#92400e` / border `#f59e0b`) and `.bs-badge--out`
  (`#EEF0F2` / `#6B7280` / border `#E5E7EB`) are light pills with borders. The new badge is the only
  solid dark one in that corner. That is deliberate.
- `catalog/controller/product/thumb.php` — the `BoosterShop RD-04f state normalization 20260601`
  block builds `bs_state` and `bs_eta`, and already queries the database directly, with a `static`
  cache, to resolve a stock-status name. The new lookup follows that same shape.

## 3. Goal

A product carrying the `Тип товару` attribute with the value `Rare Pack` shows a `Rare Pack`
badge in the left corner of its listing tile, below the pre-order or out-of-stock badge when one is
present and in its place when none is. Every other product's tile is byte-identical to today.

## 4. What to change

### 4.1 · Where the flag comes from — `catalog/controller/product/thumb.php`

Owner decision 2026-09-18: **a product attribute**, not the `-RPK` article suffix and not a separate
category.

| | value |
|---|---|
| attribute name | `Тип товару` |
| `attribute_id` | `27` |
| attribute group | `Характеристики` |
| matching value | `Rare Pack` |

**Owner-stated, verify before writing code.** Confirm `attribute_id = 27` really is `Тип товару` in
`ocp5_attribute_description` for `language_id = 4`. Chats on this project have invented attributes
that do not exist; do not be the next one. If it does not match, stop and report — do not guess
another id.

`Тип товару` is an **existing, shared** attribute already carrying other values on other products; it
was not created for this line. Two consequences:

1. Matching on the value rather than on presence is not a stylistic choice here, it is the only
   correct behaviour — presence would badge every product that has this attribute at all.
2. `Тип товару` was moved into the group `Характеристики` by the owner on 2026-09-18, specifically
   because `product.twig` renders the attribute **group name** as a header row above its rows
   (`<tr><td colspan="2">{{ attribute_group.name }}</td></tr>`, around `:503`). The group name is
   customer-visible on the product page. Do not move this attribute between groups.

Inside the RD-04f block, after `$data['bs_eta']` is set, add a lookup in the same style as the
stock-status one already there:

- read `ocp5_product_attribute.text` for `product_id` = `$data['product_id']`,
  `attribute_id` = the constant, `language_id` = `config_language_id`;
- `static` cache keyed by `language_id . ':' . product_id`, so a product rendered twice on one page
  (related carousel plus grid) costs one query;
- set `$data['bs_is_rare']` true when the trimmed, lower-cased value equals `rare pack`, using
  `mb_strtolower` where available exactly as the existing block does;
- when `$data['product_id']` is empty or the row is absent, `$data['bs_is_rare']` is false.

Match on the **value**, not on the attribute being present: `Тип товару` is a characteristic that will
carry other values later, so presence alone would badge the wrong products.

Put the `attribute_id` and the matching string in named constants at the top of the block with a
one-line comment pointing at `plans/CAT-004_op-rare-packs_identifier-canon_20260916.md`, so the next
person changing the canon can find them.

**Cost.** One indexed lookup per tile on the primary key of `ocp5_product_attribute`. On a 20-tile
category page that is up to 20 extra queries. Accepted — prefetching in the calling controllers would
mean touching category, search, special, manufacturer, related and the home modules, which is out of
scope.

**Do not change** anything else in the RD-04f block: the stock-status resolution, the pre-order
predicate, `bs_state`, `bs_eta` and its month names all stay exactly as they are. The selector work
package mirrors that predicate and must keep finding it unchanged.

### 4.2 · The corner becomes a column — `catalog/view/stylesheet/boostershop-ds.css`

```css
.bs-badge--rare { background: #4C0519; color: #FDE68A; }

.bs-pcard__badge-tl {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
}
```

Colour approved by the owner 2026-09-18; contrast 12.6:1. Size, weight, letter-spacing, radius,
padding and uppercase are inherited from `.bs-badge` — write no other rule for the new badge.

`.bs-pcard__badge-tl` keeps its existing `position: absolute; top: 18px; left: 18px;` — extend the
rule, do not replace it. `.bs-pcard__badge-tr` is not touched.

No `!important`. Append after the terminal `/* /UI-FIX-20260903-TILES */` marker, which is the last
line of the file, in the same `/* CAT-004 … */` … `/* /CAT-004 … */` comment envelope the selector
patch uses.

### 4.3 · The template — `catalog/view/template/product/thumb.twig`

Replace the `{% if bs_is_pre %} … {% elseif bs_is_out %} … {% endif %}` block with one that renders
the wrapper when any of the three states applies, and puts the badges in it in priority order:

1. `Передзамовлення` or `Немає в наявності` — these two remain mutually exclusive, exactly as today;
2. `Rare Pack`.

`bs_is_rare` is read with a default so a tile rendered from a module that does not pass through
`product/thumb.php` cannot throw.

The label is written `Rare Pack` in the template — CSS uppercases it. Do not write `RARE PACK` in the
markup and do not add an icon, a star, a flame or any other ornament next to it.

### 4.4 · Cache-bust — `catalog/view/template/common/header.twig`

Bump the `boostershop-ds.css?v=…` query string to a new value. Exactly one occurrence exists; the
anchor must be checked for count 1 before writing, like every other anchor.

## 5. Do not touch

- `.bs-pcard__badge-tr`, the discount badge and the discount calculation.
- The price row, the CTA block, the cart form, the hidden inputs, and the `TECH-015-WP3B` GA4 script
  block at the end of `thumb.twig`. That script is bound once globally; leaving it byte-identical is
  the regression guard for analytics.
- `bs_state` and `bs_eta` semantics (§4.1).
- The product page: `product.twig`, `product.php`, the variant selector, canonical, JSON-LD,
  `aggregateRating`.
- `.bs-badge--discount`, `--preorder`, `--out`, `--lowpull`, `--instock` — no existing badge changes
  colour, size or corner.
- The older `body.bs .product-thumb .bs-pcard-badge--*` block. It is not used by this template; leave
  it alone rather than tidying it.
- Category and listing controllers, the Merchant feed, `sitemap.xml`, `robots.txt`, redirects,
  `.htaccess`, checkout, payment, fiscalization.
- Any database write. This patch reads one table and writes none.

## 6. Files and delivery

```
catalog/controller/product/thumb.php          (RD-04f block, add the lookup)
catalog/view/template/product/thumb.twig      (badge-tl block)
catalog/view/stylesheet/boostershop-ds.css    (after the terminal /* /UI-FIX-20260903-TILES */ marker)
catalog/view/template/common/header.twig      (CSS cache-bust)
```

One PHP runner in `patches/`, named `CAT-004-SD-7_rare-pack-listing-badge_20260918.php`, built to the
same conventions as `patches/CAT-004_variant-selector_20260916.php`, which is the reference
implementation for this project's runner: every anchor checked for exactly one occurrence before any
write, a timestamped backup of all four files under `_patch_backups/`, `php -l` on the PHP file after
writing, restore on any failure with a **verified** per-file result, an idempotence marker with a hard
fail on a partial marker state, and self-delete on success.

The executor never commits, pushes, uploads, runs or deploys. Report to `diagnostics/` as usual.

## 7. Acceptance criteria

| case | expected |
|---|---|
| product with `Тип товару` = `Rare Pack`, in stock | one badge in the left corner: `RARE PACK` |
| the same product on pre-order | `ПЕРЕДЗАМОВЛЕННЯ` on top, `RARE PACK` under it, 6px apart |
| the same product sold out | `НЕМАЄ В НАЯВНОСТІ` on top, `RARE PACK` under it |
| the same product with a discount | `RARE PACK` left, `−N%` right, unchanged |
| product without the attribute | tile byte-identical to today |
| product with `Тип товару` set to any other value | no badge |
| product with the value in different case or with spaces | badge still shown |

Plus:

1. The rendered tile markup for a product without the attribute is byte-identical before and after.
2. The GA4 script block at the end of `thumb.twig` is byte-identical before and after.
3. `.bs-pcard__badge-tr` and every existing `.bs-badge--*` rule are unchanged.
4. The new CSS contains no `!important`.
5. A second run of the patch reports `already_applied=yes` and self-deletes; a partial marker state
   fails before any write.
6. The diff removes no line from any of the four files except the one header cache-bust line.

## 8. QA / smoke test — owner

Not a checkout, payment or fiscalization change — `bs-checkout-smoke` not required. Not an SEO,
canonical, sitemap or schema change — no gate. Risk is confined to how the tile looks.

1. Before running: set `Тип товару` = `Rare Pack` on each of the four `-RPK` products. **A product
   where this is missed simply has no badge and nothing warns you** — this is the one failure mode of
   an attribute-driven flag, so check all four.
2. Run the patch, clear the OpenCart cache, hard-refresh a category page.
3. A non-Rare-Pack tile looks exactly as before.
4. A Rare Pack tile shows the badge; on a pre-order or sold-out one, both badges stack correctly.
5. Search results and the related-products carousel show it too.
6. **390 px.** Two stacked badges take about 44px of the photo. `ПЕРЕДЗАМОВЛЕННЯ` at the narrowest
   tile width already runs close to the right edge. If it clips or wraps, report it — the fix is to
   reduce the corner offset from 18px to 10px under the existing mobile breakpoint, and it is a
   follow-up, not something to improvise during this patch.
7. Open a Rare Pack product page → tab «Характеристики». The row must read `Тип товару — Rare Pack`
   under the group heading `Характеристики`. Any other heading above it means the attribute is back in
   the wrong group.
8. Open one existing product that already carries `Тип товару` with a different value and confirm its
   tile and its characteristics table are unchanged.

## 9. Rollback

Restore the four files from the timestamped `_patch_backups` directory and clear the cache. Nothing is
written to the database, so there is nothing to migrate back. Clearing the attribute value off the
products also removes the badge without touching code.

Operational note: on any run that does not end `done=ok` + `self_delete=ok`, the runner stays in
`public_html` and is publicly executable by URL. Delete it manually before doing anything else.

## 10. What this badge must never say

From `plans/CAT-004_op-rare-packs_identifier-canon_20260916.md` §2: `Rare Pack` is the shop's own
product line, never a publisher designation, and the rare thing is the **set**, not the pack. The
badge is therefore the two words and nothing else — no «лімітовано», no «гарантовано», no star, no
flame, no tooltip promising contents.

## 11. Recommended status after execution

`In progress` until the owner has run §8 on production. Notion status is written by Claude (chat).
