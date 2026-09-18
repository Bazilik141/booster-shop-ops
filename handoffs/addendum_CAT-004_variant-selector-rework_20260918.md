# Addendum — CAT-004 variant selector: rework of the 2026-09-16 patch

Date: 2026-09-18 · Notion: `3dc6bf20-bdb4-8164-b001-db2191f9f7e6` · parent handoff:
`handoffs/handoff_CAT-004_variant-selector_20260916.md` · review that produced this:
`diagnostics/CAT-004_variant-selector_claude-review_20260918.md`

**Executor: Claude Code** (owner decision 2026-09-18 — the task moves off Codex). **Not a rewrite.**
`patches/CAT-004_variant-selector_20260916.php` is sound in construction, anchoring, backup and
rollback; it was verified by running it against the owner's production export and diffing both trees.
Regenerate that same file with the five changes below. Same filename, same five targets, same marker
scheme, same idempotence and restore design.

**Nothing in the current patch has been deployed.** Two of the changes alter rendered links, so the
current version must not ship first and get fixed after — that would publish one internal link
structure and then replace it.

## 1. Task ID

`CAT-004`

## 2. Why this addendum exists

The parent handoff contradicted itself. §4.2 rule 3 said an out-of-stock sibling keeps its `href`
because "an out-of-stock variant still has a page worth visiting"; §4.3 said "an unavailable value
must not be a link". The executor implemented §4.3. **The contradiction is the handoff author's, not
the executor's**, and the same applies to the pre-order omission in §4.2 rule 3.

Owner decisions of 2026-09-18 resolve both, and the arrival of `catalog/controller/product/thumb.php`
resolves the second without any further input.

## 3. What to change

### 3.1 · `available` no longer decides linkability — BLOCKER

Today the template drops a perfectly good `href`:

```twig
{% if value.available and value.href %}   → <a>
{% else %}                                → <span class="is-off">, href discarded
```

Reproduced on a fixture: a sibling with `quantity = 0` returns `available=false, href=SET` and renders
as a non-link. A sold-out variant page becomes unreachable from every sibling. That directly defeats
`plans/CAT-004_op-rare-packs_identifier-canon_20260916.md` §6, which keeps a sold-out member's page
alive so its accumulated search weight is not lost — which requires the sibling pages to keep linking
to it.

**Owner decision: the chip stays a link. `is-off` becomes styling only.**

Replace the single `available` boolean with an explicit three-state field, because two different
things were fused into it:

| `state` | meaning | renders as |
|---|---|---|
| `''` | a sibling exists and is buyable | `<a>` |
| `'out'` | a sibling exists, not buyable | `<a class="… is-off">` |
| `'none'` | no sibling for this option value | `<span class="… is-off" aria-disabled="true">` |

`href` keeps its current construction: the sibling's link, `''` only when `state` is `'none'`.

CSS consequence: `cursor: not-allowed` moves off `.bs-variant__chip.is-off` and onto
`span.bs-variant__chip.is-off` only. An `<a class="is-off">` is a real link and must keep the pointer
cursor, the hover affordance and focus-visible.

### 3.2 · Pre-order variants are buyable — BLOCKER

`available` is `(int)$sibling['quantity'] > 0`. In this shop a pre-order product carries
`quantity <= 0` and is fully purchasable: `catalog/view/template/product/product.twig:334` renders a
live submit button «Передзамовити», and the listing tile does the same in
`catalog/view/template/product/thumb.twig`. So every pre-order sibling would render greyed, and its
price would be excluded from the per-group price comparison — a family whose only differing price
belongs to a pre-order member would silently show no prices at all, then start showing them on
restock.

**Do not invent a predicate. Reuse the one that already exists.**
`catalog/controller/product/thumb.php`, block `BoosterShop RD-04f state normalization 20260601`, is
the shop's single definition of this state. Read it and mirror it exactly:

```
preorder signal = the stock status NAME contains 'передзамов' / 'Передзамов' / 'preorder' / 'pre-order'
                  OR date_available is a real future date
is_preorder     = quantity <= 0 AND preorder signal
bs_state        = is_preorder ? 'preorder' : (quantity <= 0 ? 'out' : '')
```

So in the controller: `state` is `'out'` **only** when that mirrored predicate yields `out`. A
pre-order sibling gets `state = ''` and is an ordinary chip.

The family rows already carry `stock_status_id`; the current patch selects it and never uses it. The
status **name** is what the predicate needs, so resolve it the same way `thumb.php` does — one query
per distinct `stock_status_id` behind a `static` cache. There are a handful of statuses, so this adds
at most one or two queries per page.

**Do not copy `thumb.php`'s `bs_eta` month formatting or anything else from that block.** Only the
state predicate.

**Not a defect, do not "fix" it:** the model's `date_available <= NOW()` filter also excludes
future-dated products from the family. That is the standard OpenCart visibility rule, byte-identical
to `getProduct()` at `catalog/model/catalog/product.php:117`, and such a product cannot render its own
page either. It is consistent. Leave it.

### 3.3 · Guard the master-combination inference — HIGH

When the master is disabled or out of store but two or more children are visible, the inference
resolves one unclaimed combination and assigns it to `$family_by_product_id[$master_id]` — an entry
that does not exist. PHP 8 auto-vivifies it, and the value map then holds a row carrying only a
`variant` key. Reproduced against a fixture, verbatim:

```
Undefined array key "quantity"
Undefined array key "product_id"   (×2)
```

Warnings, not fatals, on production PHP 8.0.30 — they reach the error log, and the page HTML if
`display_errors` is on. Every page of that family, every load.

Wrap the whole inference block in `if ($master_family_product) { … }`.

### 3.4 · Chip state must reach a screen reader — MEDIUM

The active chip has no `aria-current`. An unavailable chip conveys its state only through a CSS
diagonal line, a muted colour and a cursor.

- `aria-current="page"` on the current value's element.
- `state = 'none'` renders `aria-disabled="true"` plus a visually hidden word. Use the theme's own
  utility — `body.bs .visually-hidden` exists in `catalog/view/stylesheet/boostershop-ds.css:3440`;
  do not invent a second one.
- `state = 'out'` keeps its label plus a visually hidden «немає в наявності», since it is a link a
  keyboard user will land on.

### 3.5 · Make restore verifiable — LOW

`cat004_restore()` copies with `@copy`, discards the result and prints `restore=attempted`
unconditionally. A restore that silently failed is indistinguishable from one that worked, at the
moment it matters most. Check each `copy()` and print per-file status; end with an explicit
`restore=ok` or `restore=incomplete:<paths>`.

## 4. One change to a rule the owner already approved — flag, do not silently keep either way

Parent handoff §4.2 rule 5 computes `show_price` over **available** values only. With §3.1 and §3.2 in
place, "available" no longer means "has a sibling", so that rule has to be restated. Implement it as:

> `show_price` is computed over every value whose `state` is not `'none'` — that is, every value that
> has a real sibling, in stock or not.

Reason: it makes the group's price display stable. Under the old reading, a family stops showing
prices the moment the one differently-priced member sells out, and starts again when it is restocked —
the page changes shape for a reason a customer cannot see. A value with `state = 'none'` still never
carries a price.

If the owner reverses this, the only change is the filter in the `$available_prices` accumulation.

## 5. Do not touch

Everything the parent handoff §5 protects, unchanged, plus:

- The generic `{% if options %}` block, `<form id="form-product">` and everything after it in
  `product.twig`. The current patch leaves that region byte-identical — keep it that way.
- Canonical, JSON-LD, `aggregateRating`. All five JSON-LD blocks are byte-identical before/after in
  the current patch; that must still hold.
- `catalog/view/template/product/thumb.twig` and `catalog/controller/product/thumb.php` — read
  `thumb.php` for the predicate, change neither. The listing badge is `CAT-004` **SD-7**, a separate
  work package with its own handoff.
- Category, listing, feed, sitemap, robots, redirects, checkout, payment, fiscalization.
- Any database write. The patch writes none and must continue to write none.

## 6. Files

The same five, at the same anchors — all verified to occur exactly once in the owner's export:

```
catalog/model/catalog/product.php            (getVariantFamily, before the "Get Products" docblock)
catalog/controller/product/product.php       (before "// Subscriptions")
catalog/view/template/product/product.twig   (inside <div id="product">, before <form id="form-product">)
catalog/view/stylesheet/boostershop-ds.css   (after the terminal /* /UI-FIX-20260903-TILES */ marker)
catalog/view/template/common/header.twig     (CSS cache-bust)
```

Bump the cache-bust to a new value; `cat004-variant-20260916` is already the marker the current patch
writes, and the idempotence check keys on it.

## 7. Acceptance criteria

Proven by fixture, not by inspection. The parent handoff §7 still applies; these replace and add to it.

| case | expected |
|---|---|
| sibling in stock | `state=''`, `<a>`, no `is-off` |
| sibling out of stock, no pre-order signal | `state='out'`, **`<a>`** with `is-off`, `href` set |
| sibling with quantity 0 and stock status «Передзамовлення» | `state=''`, ordinary `<a>`, price counted |
| option value with no sibling | `state='none'`, `<span>`, no `href`, `aria-disabled` |
| current value | `aria-current="page"` present exactly once per group |
| master not in the visible family, ≥2 children | no PHP notice, warning or deprecation emitted |
| equal prices across all values with a sibling | `show_price` false, every `price` null |
| one differing price among values with a sibling | `show_price` true, every non-`'none'` value priced |
| product with no family | `variant_groups` empty, page unchanged |

Plus:

1. `span.bs-variant__chip.is-off` carries `cursor: not-allowed`; `a.bs-variant__chip.is-off` does not.
2. The new CSS block still contains no `!important`.
3. A second run reports `already_applied=yes` and self-deletes; a partial marker state hard-fails
   before any write.
4. The diff removes no line from any of the five files except the one header cache-bust line.

## 8. Owner QA

The parent handoff §8 list stands, with these replacing its link items:

1. Open a child variant whose sibling is sold out. Its chip is grey **and clickable**, and lands on
   that sibling's page.
2. Open a family containing a pre-order member. Its chip is an ordinary chip, not grey, and its price
   participates in the group rule.
3. Tab through the selector: every chip with a sibling is reachable; the current one announces itself.
4. 390 px: chips wrap, target height ≥ 44 px, no horizontal scroll.
5. Canonical and Product JSON-LD before/after unchanged.
6. Add to cart from a child still carries that child's product ID.

## 9. Rollback

Unchanged from the current patch: restore the five files from the timestamped `_patch_backups`
directory and clear the OpenCart cache. No database rollback exists because nothing is written.

Operational note for the owner, learned from testing the current runner: on any run that does **not**
end `done=ok` + `self_delete=ok`, the runner stays in `public_html` and is publicly executable by URL.
Delete it manually before doing anything else.

## 10. Recommended status after execution

`In progress` until the owner has run §8 on production. Notion status is written by Claude (chat).
