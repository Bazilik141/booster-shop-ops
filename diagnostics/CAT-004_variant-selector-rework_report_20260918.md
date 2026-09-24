# Claude Code Report — CAT-004: variant selector, addendum rework

Date: 2026-09-18 · Patch: `patches/CAT-004_variant-selector_20260916.php` (regenerated in place)
Addendum: `handoffs/addendum_CAT-004_variant-selector-rework_20260918.md`
Parent handoff: `handoffs/handoff_CAT-004_variant-selector_20260916.md`
Review that produced the addendum: `diagnostics/CAT-004_variant-selector_claude-review_20260918.md`

**Not deployed. Nothing committed, pushed or uploaded.**

## 1 · What changed

All five addendum changes are implemented, plus the two CSS changes in §1.6. Same filename, same
five targets, same anchors, same backup and restore design.

| § | Change | Where |
|---|---|---|
| 3.1 | `available` (bool) → `state` (`''` \| `'out'` \| `'none'`). A value with a sibling is always an `<a>`; only `'none'` is a `<span>`. `is-off` is styling. | controller + twig + css |
| 3.2 | Buyability mirrors `thumb.php` block `BoosterShop RD-04f state normalization 20260601`. Status **name** resolved per distinct `stock_status_id` behind a `static` cache. `bs_eta` not copied. | controller |
| 3.3 | Master-combination inference wrapped in `if ($master_family_product) { … }`. | controller |
| 3.4 | `aria-current="page"` on the current value; `aria-disabled="true"` + `<span class="visually-hidden">недоступно</span>` on `'none'`; `немає в наявності` on `'out'`. Uses the theme's own `body.bs .visually-hidden` (`boostershop-ds.css:3440`). | twig |
| 3.5 | Restore checks each `copy()`, reads the file back, prints `restore:<path>=ok\|copy_failed\|no_backup\|unverified\|mismatch`, ends `restore=ok` or `restore=incomplete:<paths>`. | runner |
| 3.6 | Token located by the path prefix, shape-validated, echoed old→new, replaced wholesale with `cat004-variant-20260918`. Idempotence keys on the four **content** markers only. | runner |
| 4 | `show_price` accumulates every value whose `state` is not `'none'`. | controller |

### 1.6 · Two CSS changes beyond the addendum's list

Both live inside this patch's own block, neither touches a rule another patch owns, and no
priority flag is used anywhere in the block.

**a) The chip's own colour was being overridden by the theme's link rule.** Measured in a browser
against the real cascade, not inferred: `boostershop-ds.css:94` `.bs a { color: var(--bs-blue) }`
at (0,1,1) outranks `.bs-variant__chip { color: var(--bs-ink-2) }` at (0,1,0), and `:95`
`.bs a:hover { text-decoration: underline }` at (0,2,1) outranks `.bs-variant__chip:hover` at
(0,2,0). On the 2026-09-16 CSS as written, an available chip computed `rgb(30,58,138)` — blue — at
rest and grew an underline on hover, where parent handoff §4.4 specifies `var(--bs-ink-2)`. The
active chip is blue too, so active and non-active chips differed only by border, background and
weight.

```css
a.bs-variant__chip { color: var(--bs-ink-2); }
a.bs-variant__chip:hover { text-decoration: none; }
```

The colour rule deliberately stays at (0,1,1) so `.is-active` and `.is-off` keep winning it.

**b) A sold-out variant's own page had no "you are here" cue — owner decision 2026-09-18.** When
the open product is itself `state='out'`, its chip carries `is-active is-off`, and `.is-off` is
declared after `.is-active` at equal specificity, so the chip rendered fully grey. `aria-current`
was emitted, so a screen reader was correct; a sighted visitor had nothing.

```css
.bs-variant__chip.is-active.is-off { border-color: var(--bs-blue); color: var(--bs-blue); }
a.bs-variant__chip.is-off:not(.is-active):hover { border-color: var(--bs-ink-3); color: var(--bs-ink-3); }
```

The first line restores the blue frame and ink at (0,3,0) while the grey ground, the diagonal
strike and the active ring stay — both signals read at once. The second is the existing
out-of-stock hover rule, narrowed: that affordance belongs to the siblings you can travel to, not
to the page you are already on, and without the `:not()` its (0,3,1) would have dragged the
current chip back to grey on hover.

Review scan per `AGENTS.md` UI/CSS rule 7: the diff contains no `!important`, no `setTimeout`, no
`position: absolute/fixed`, and no uncommented magic pixel value.

## 2 · How this was verified

The owner's export `booster-debug-CAT-004.tar.gz` was extracted twice — one reference copy, one
run target — the patch was executed against the run target and the two trees diffed. The
controller's insert block was then extracted verbatim and executed against synthetic families
with stubbed OpenCart services; the twig block was rendered with real Twig 3 against that
controller output; the produced stylesheet was loaded in a browser and computed styles were read
at 390 / 768 / 1280 px, including real hover and keyboard focus.

Local PHP is 8.3.30 — **production 8.0 could not be linted locally**. The inserted code was
scanned instead: no `enum`, `readonly`, `never`, first-class callables, `new` in initialisers or
8.1+ functions; every function used (`array_key_exists`, `mb_strtolower`, `strcspn`, `strpos`,
`in_array`, `array_filter`, `explode`, `substr`, `trim`, `date`) predates 8.0. The runner's own
construct set is the one `CAT-004/SD-7` already ran on production PHP 8.0 on 2026-09-18.

### Run

```
header_cache_bust_from=uifix-tiles-20260904
header_cache_bust_to=cat004-variant-20260918
backup=…/_patch_backups/CAT-004_variant-selector_20260918_200511
changed=catalog/model/catalog/product.php
changed=catalog/controller/product/product.php
changed=catalog/view/template/product/product.twig
changed=catalog/view/stylesheet/boostershop-ds.css
changed=catalog/view/template/common/header.twig
php_l_product.php=ok
php_l_product.php=ok
done=ok
self_delete=ok
```

Second run: `already_applied=yes`, `done=ok`, `self_delete=ok`, and the tree is byte-identical to
before that run — the token is not touched a second time.

Order independence (§3.6), against a tree where SD-7 already owns the token:

```
header_cache_bust_from=cat004-sd7-20260918
header_cache_bust_to=cat004-variant-20260918
```

SD-7's CSS block survives intact and still sits after this patch's block; both anchor on the same
terminal marker and neither depends on the other's token value.

Partial marker state (one content marker planted): `ERROR=partial_marker_state`, exit 1, no backup
directory created, all five files bit-identical. The runner **stays in place** — see §5.

Restore path (a syntax error planted so the post-write `php -l` gate fails):

```
php_l_product.php=failed
restore:catalog/model/catalog/product.php=ok
restore:catalog/controller/product/product.php=ok
restore:catalog/view/template/product/product.twig=ok
restore:catalog/view/stylesheet/boostershop-ds.css=ok
restore:catalog/view/template/common/header.twig=ok
restore=ok
ERROR=php_l_failed:catalog/model/catalog/product.php
```

All five files verified bit-identical to their pre-run state afterwards.

### Bounded diff

| File | + | − |
|---|---|---|
| `catalog/model/catalog/product.php` | 17 | 0 |
| `catalog/controller/product/product.php` | 184 | 0 |
| `catalog/view/template/product/product.twig` | 33 | 0 |
| `catalog/view/stylesheet/boostershop-ds.css` | 38 | 0 |
| `catalog/view/template/common/header.twig` | 1 | 1 |

Byte-identical before/after, by MD5 of the extracted region: all five JSON-LD blocks in
`product.twig`; everything in that file before `<div id="product">` and from
`<form id="form-product">` to EOF; everything in the controller before the insert and from
`// Subscriptions` to EOF; the model from the `Get Products` docblock to EOF and everything before
`getProduct()`. `addLink($product_url, 'canonical')` at `product.php:33` unchanged.

## 3 · Acceptance criteria (addendum §7)

Every row proven by fixture, and the markup column proven by rendering that fixture's controller
output through Twig 3.

| case | expected | result |
|---|---|---|
| sibling in stock | `state=''`, `<a>`, no `is-off` | pass |
| sibling out of stock, no pre-order signal | `state='out'`, `<a>` with `is-off`, `href` set | pass |
| quantity 0 + «Передзамовлення» | `state=''`, ordinary `<a>`, price counted | pass — also with `Pre-Order` |
| option value with no sibling | `state='none'`, `<span>`, no `href`, `aria-disabled` | pass |
| current value | `aria-current="page"` once per group | pass |
| master not in the visible family, ≥2 children | no notice, warning or deprecation | pass |
| equal prices across all values with a sibling | `show_price` false, every `price` null | pass |
| one differing price among values with a sibling | `show_price` true, every non-`'none'` value priced | pass |
| product with no family | `variant_groups` empty, page unchanged | pass |

The H1 regression was reproduced against the previous block for comparison: fixture "master
absent, two children" emitted `Undefined array key "quantity"` and `Undefined array key
"product_id"` ×2, and rendered the phantom master as a chip pointing at `product_id=0`. The
guarded block emits none of that and renders `state='none'` with no `href`.

Plus:

1. `span.bs-variant__chip.is-off` → `cursor: not-allowed`; `a.bs-variant__chip.is-off` →
   `cursor: pointer`. Read from `getComputedStyle`, not from the source.
2. No `!important` in the new CSS block (0 occurrences).
3. Second run `already_applied=yes` + self-delete; partial marker state hard-fails before any write.
4. Zero removed lines in all five files except the one header cache-bust line.

Computed styles at 1280 px, one row per rendered state, rest → hover:

| element | cursor | colour | border | ground | strike |
|---|---|---|---|---|---|
| `a.bs-variant__chip` | pointer | ink-2 `rgb(31,41,55)` → ink-2, no underline | line → ink-3 | white | none |
| `a…is-active` | pointer | blue `rgb(30,58,138)` | blue | blue-soft + ring | none |
| `a…is-off` | pointer | ink-4 `rgb(156,163,175)` → ink-3 `rgb(107,114,128)` | line-2 → ink-3 | `--bs-bg` | gradient |
| `a…is-active.is-off` | pointer | blue → **blue** | blue → **blue** | `--bs-bg` + ring | gradient |
| `span…is-off` | **not-allowed** | ink-4 | line-2 | `--bs-bg` | gradient |

Breakpoints, measured on a nine-value family with full-length deck names
(«Стартова колода ex — Джиґґліпаф»), one `out` and one `none`:

| width | chip min-height | wrap | horizontal scroll |
|---|---|---|---|
| 390 | 46 px | 9 lines | none |
| 768 | 47 px | 4 lines | none |
| 1280 | 44 px | 5 lines | none |

Keyboard: the eight anchors are tabbable, the `span` is not, and `Tab` onto a chip produces
`outline: 2px solid rgb(30,58,138)` at `2px` offset via `:focus-visible`.

Query cost of §3.2: one `stock_status` read per distinct `stock_status_id`, cached — two
out-of-stock siblings sharing a status produced one query; an all-in-stock family produced none.

## 4 · Not mine to write

`ROADMAP_FLOW` needs no change for this work — `CAT-004` is already `active` / `lastUpdated:
'2026-09-18'`, and the addendum keeps the task `In progress` until owner QA. The `SD-7` subtask
row went to `status: 'done'` in commit `fec9def` while this rework was in progress; nothing in
the dashboard was touched here.

## 5 · Run and rollback

Upload to `~/public_html`, then:

```bash
php CAT-004_variant-selector_20260916.php
```

Rollback: restore the five files from the `_patch_backups/CAT-004_variant-selector_<ts>/`
directory the run prints, then clear the OpenCart cache. No database rollback exists — the patch
writes none.

On any run that does **not** end `done=ok` + `self_delete=ok`, the runner stays in `public_html`
and is publicly executable by URL. Delete it manually before doing anything else.

## 6 · Owner QA

Parent handoff §8 stands. These replace its link items.

- [ ] Open a child variant whose sibling is sold out. Its chip is grey **and clickable**, and lands
      on that sibling's page.
- [ ] Open that sold-out variant's own page. Its chip keeps a blue frame and blue text over the
      grey strike-through ground.
- [ ] Open a family containing a pre-order member. Its chip is an ordinary chip, not grey, and its
      price participates in the group rule.
- [ ] Tab through the selector: every chip with a sibling is reachable; the current one announces
      itself.
- [ ] 390 px: chips wrap, target height ≥ 44 px, no horizontal scroll.
- [ ] Canonical and Product JSON-LD before/after unchanged.
- [ ] Add to cart from a child still carries that child's product ID.
- [ ] Ctrl+F5 an ordinary non-variant product: unchanged, no empty selector block.

Each variant still needs its own `ocp5_seo_url` row before deploy, or its chip emits an
`index.php?route=…` link.
