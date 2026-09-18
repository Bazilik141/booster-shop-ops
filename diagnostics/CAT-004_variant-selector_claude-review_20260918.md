# Claude Review — CAT-004 variant selector patch

Date: 2026-09-18
Patch reviewed: `patches/CAT-004_variant-selector_20260916.php`
Codex report: `diagnostics/CAT-004_variant-selector_report_20260916.md`
Handoff: `handoffs/handoff_CAT-004_variant-selector_20260916.md`

**Verdict: Return for changes.** Two blockers and one high-severity defect. Nothing
else in the patch needs rework; the construction, anchoring and rollback design are
sound and independently reproduced below.

Two of the three defects originate in the handoff, not in the executor's work. That is
stated per finding.

---

## How this review was verified

Not read-only inspection. The owner's production export `booster-debug-CAT-004.tar.gz`
was extracted twice: once as a reference copy, once as a run target. The patch was then
executed against the run target with PHP 8.4 (production is 8.0.30; nothing in the patch
is version-sensitive), and the two trees diffed. The controller's insert block was
additionally extracted and executed against synthetic families with stubbed OpenCart
services, to observe `variant_groups` directly rather than reason about it.

Fixtures used, all against one option (`product_option_id` 236, values 34/35/36):

| # | Family | Open page |
|---|---|---|
| A | master 75 + children 182, 183 — all in stock, equal price | master |
| B | same, child 182 priced differently | child 182 |
| C | master 75 + 182 in stock, 183 `quantity = 0` at a different price | master |
| D | master not visible, children 182 + 183 only | child 182 |
| E | master 75 + one child 182, three option values | master |

---

## Blockers

### B1 · An out-of-stock sibling becomes a dead end

The controller does what handoff §4.2 rule 3 asks: `href` is set for an out-of-stock
sibling, empty only when no sibling exists. The template then discards it:

```twig
{% if value.available and value.href %}   ← <a>
{% else %}                                ← <span class="is-off">, href dropped
```

Fixture C confirms it: value 36 returns `available=false, href=SET`, and renders as a
non-link. A sold-out variant page becomes unreachable from every sibling in the family.

This matters beyond navigation. `plans/CAT-004_op-rare-packs_identifier-canon_20260916.md`
§6 keeps a sold-out member's page alive precisely so its accumulated search weight is not
lost — which requires the sibling pages to keep linking to it. This patch removes exactly
those links.

**Cause is the handoff.** §4.2 rule 3 ("an out-of-stock variant still has a page worth
visiting") and §4.3 ("an unavailable value must not be a link") contradict each other.
Codex implemented §4.3. The contradiction is mine.

**Recommended resolution — owner decides.** Split the two states that are currently fused
into `available`:

- no sibling for that value → `<span>`, no href, `is-off`;
- sibling exists but is not buyable → `<a href>`, `is-off` styling retained.

Then §4.3 gains: a value is a link whenever a sibling exists.

### B2 · Pre-order variants vanish from the selector

`available` is `(int)$sibling['quantity'] > 0`. In this shop a pre-order product carries
`quantity <= 0` and the stock status «Передзамовлення», and is fully purchasable:
`catalog/view/template/product/product.twig:84` derives `_is_preorder` from the stock
status text, and `:334` renders a live submit button «Передзамовити».

So every pre-order sibling is greyed out, unlinked, and — because `show_price` counts only
available values — its price is excluded from the per-group price comparison. Fixture C
demonstrates the second effect: a sibling at a different price, out of stock, produced
`show_price=false` for the whole group. The group silently stops showing prices, then
starts again when that variant is restocked.

The handoff said "quantity not positive" and never mentioned pre-order. Also mine.

**Recommended resolution.** `available` becomes: a sibling exists **and**
(`quantity > 0` **or** the sibling's `stock_status_id` is the pre-order status). The
family row already carries `stock_status_id`; the patch selects it and never uses it. The
owner must supply the pre-order `stock_status_id` value — it is not in the export.

**Not a defect, to avoid confusion with the above:** the model's
`date_available <= NOW()` filter also excludes future-dated products from the family. That
is the standard OpenCart visibility rule, byte-identical to `getProduct()` at
`catalog/model/catalog/product.php:117`, and such a product cannot render its own page
either. Consistent, correct, leave it.

---

## High

### H1 · PHP warnings on every page of a family whose master is not visible

When the master is disabled or out of store but two or more children are visible, the
master-combination inference still runs, resolves one unclaimed combination and assigns it
to `$family_by_product_id[$master_id]` — an entry that does not exist. PHP 8 auto-vivifies
it, so the value map holds a row with a `variant` key and nothing else.

Fixture D output, verbatim:

```
PHPWARN: Undefined array key "quantity"
PHPWARN: Undefined array key "product_id"
PHPWARN: Undefined array key "product_id"
```

Warnings, not fatals, on production PHP 8.0.30 — they reach the error log, and the page
HTML if `display_errors` is on. The chip renders as unavailable, which is the right
outcome by accident.

**Fix:** guard the whole inference block with `if ($master_family_product) { … }`.

---

## Medium

### M1 · Incomplete family shows the master's own value as unavailable

Fixture E: three option values, master plus one child, so two combinations are unclaimed.
The patch correctly declines to guess — and the master's own page then renders its own
value as a greyed, unlinked chip. No active chip anywhere.

This is documented behaviour, not a code defect, but it is precisely the state during
content entry for the nine decks. It belongs in the owner QA gate as a data condition:
create every member of the family before judging a page, and read "no active chip" as a
catalogue-data error rather than a code failure.

### M2 · Chip state is visual only

The active chip has no `aria-current`. An unavailable chip conveys its state through a CSS
diagonal line, a muted colour and `cursor: not-allowed` — none of which reach a screen
reader; the label is read as ordinary text.

**Fix:** `aria-current="page"` on the current value, `aria-disabled="true"` plus a
visually hidden word on unavailable ones.

---

## Low

### L1 · Cascade cannot be fully verified from this export

`boostershop-ds.css` is the fifth of eight stylesheets (`header.twig:58`); four load after
it and were not in the export, so "no later selector can override `bs-variant*`" cannot be
proven here. What is proven: no `bs-variant` rule exists anywhere in the exported
stylesheet, the new block uses no `!important`, and it sits at the very end of the file.
The header's inline `<style>` block contains nothing that reaches these classes. Visual QA
at desktop / 768 / 390 closes the remainder.

### L2 · A failed run leaves the runner in `public_html`

Confirmed: forcing `partial_marker_state` exits 1 and does **not** self-delete. Until it is
removed the file is publicly executable by URL at the site root. After any run that does
not end `done=ok` + `self_delete=ok`, delete it manually before doing anything else.

### L3 · Restore reports "attempted", never "succeeded"

`cat004_restore()` copies with `@copy` and discards the result, then prints
`restore=attempted` unconditionally. A restore that silently failed is indistinguishable
from one that worked, at the moment it matters most. Suggest checking each `copy()` and
printing per-file status.

### L4 · Chips inherit whatever URL the sibling has

`$this->url->link('product/product', 'product_id=' . $id)` is byte-identical to the call
that builds the page's own canonical (`catalog/controller/product/product.php:28`), so a
chip's href always equals that sibling's canonical — the strongest consistency available.
The consequence is that a variant with no `ocp5_seo_url` row emits an `index.php?route=…`
chip. Confirm one `seo_url` row per variant before deploy.

---

## Verified clean

Reproduced independently, not taken from the report.

**Anchors.** All five occur exactly once in the live export: model `Get Products` docblock,
controller `// Subscriptions`, twig `<div id="product">` + `<form id="form-product">`, CSS
terminal `/* /UI-FIX-20260903-TILES */` (last line of file), header
`?v=uifix-tiles-20260904`.

**Run.** Against a clean extraction: exit 0, five files written, both PHP files lint `ok`,
self-delete `ok`. Second run: `already_applied=yes`, exit 0. Partial marker state: hard
fail before any write.

**Diff.** Zero removed lines in all five files, except the one intentional header
cache-bust line. All five JSON-LD blocks byte-identical. The canonical statement untouched.
The twig from `<form id="form-product">` to EOF byte-identical. CSS appended after the
terminal marker, no `!important`. All eleven custom properties the new CSS uses are defined
in that same file.

**Model.** `getVariantFamily()` reuses `$this->statement['discount']` and
`['special']` and the exact join shape of `getProduct()` (`:117`), with the same
`variant` decode and `discount ?: price` resolution. Because the WHERE clause filters on
`p.product_id` / `p.master_id`, the status and date conditions in the LEFT JOIN's ON clause
behave as an inner join — disabled products are excluded, as intended.

**Controller.** Uses the raw `$product_options` from `getOptions($master_id)` (`:448`),
not `$data['options']`. This matters: in `$data['options']` the image is already resized to
a URL, and the patch's `is_file(DIR_IMAGE . …)` guard would silently never find a thumb.
The executor picked the right source.

`variant` keyed by `product_option_id` matches the controller's own
`$product_info['override']['variant'][$option['product_option_id']]` at `:451`.

Price formula `special ?: (discount ?: price)` through `tax->calculate` and
`currency->format` matches how `$data['price']` and `$data['special']` are built
(`:370`, `:376`), so a chip's price and the page's price cannot disagree.

**Price rule.** Fixtures A and B confirm the owner's group rule: equal prices across the
group → no chip shows a price; one differing price → every available chip shows one.

**Regression guard.** A product with no family leaves `variant_groups` empty and the page
unchanged, which is the guard for the existing catalogue.

---

## What the owner decides before this goes further

1. **B1** — does an out-of-stock sibling keep its link? Recommended: yes, `is-off` becomes
   styling only, and a non-link is reserved for values with no sibling.
2. **B2** — the `stock_status_id` of «Передзамовлення», so pre-order variants count as
   available.

With those two answered, B1/B2/H1/M2 are one bounded follow-up patch. Nothing else in the
delivery needs revisiting, and no part of it should be deployed first: B1 and B2 both
change rendered links on live pages, so shipping the current version and fixing after would
publish a link structure that then has to be published again.
