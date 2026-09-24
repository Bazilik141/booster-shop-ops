# Claude Code Report — CAT-004 / SD-7: «Rare Pack» badge on the listing tile

Date: 2026-09-18 · Executor: Claude Code (Opus, medium) · Handoff:
`handoffs/handoff_CAT-004-SD-7_rare-pack-listing-badge_20260918.md`

**Ред. 2, 2026-09-18** — the runner was regenerated on two owner instructions: the
cache-bust now follows `AGENTS.md` patch convention 8 (read the token, replace it
wholesale, never append), and the no-mbstring fallback now lowercases instead of passing
the value through unchanged. Everything else in this report was re-verified against the
regenerated file, not carried over.

## Blocking pre-check — attribute identity

| check | source | result |
|---|---|---|
| `attribute_id = 27` is `Тип товару` for `language_id = 4` | `ocp5_attribute_description` in `boosters_ocart49.sql.gz` (2026-09-12) | **confirmed** — `(27,4,'Тип товару')` |
| the attribute is shared, not line-specific | `ocp5_product_attribute` | confirmed — 10 rows, all accessory values (`Протектор`, `Топлоадер`, `Альбом для колекційних карток`, …); no `Rare Pack` row yet |
| `Характеристики` group id | `ocp5_attribute_group_description` | `7` |
| `config_language_id` is populated at runtime | `catalog/controller/startup/language.php:44` in `backup-9.7.2026_20-35-02_boosters.tar.gz` | confirmed — `$this->config->set('config_language_id', …)` |

One expected mismatch, not a blocker: in that 2026-09-12 dump `ocp5_attribute` still
places attribute 27 in group `9` (`Характеристики аксесуарів`). The owner moved it to
group `7` on 2026-09-18, after the dump was taken. §8.7 of the handoff is the check
that confirms the move on production.

## Scope

1:1 with the handoff. Two deliberate implementation choices are recorded below;
neither changes behaviour the handoff specified.

**1 · Constants live at the top of the class, not the top of the block.** PHP has no
block-scoped constants, so `private const BS_CAT004_RARE_ATTRIBUTE_ID = 27` and
`BS_CAT004_RARE_ATTRIBUTE_VALUE = 'rare pack'` are class constants carrying the canon
comment; the lookup block points at them in one line. Grep-discoverable, zero per-call
cost, and the only literal-free reading of "named constants".

**2 · The cache-bust reads the live token and replaces it wholesale** — `AGENTS.md` patch
convention 8. The reference is located by its path prefix
`catalog/view/stylesheet/boostershop-ds.css?v=`, whatever token is there is read and
shape-validated against `^[A-Za-z0-9._-]{1,64}$`, and the whole token becomes
`cat004-sd7-20260918`. Nothing is hardcoded about the previous value and nothing is
appended.

Consequently **`header.twig` is not part of the idempotence marker set.** The three
content markers decide — `thumb.php`, `thumb.twig`, the CSS block. A repeat run that finds
all three exits `already_applied` and does not touch the token, which is correct: this
patch's CSS is already live, so there is nothing to bust, and a token another patch has
since written stays intact. Verified under **Failure paths**.

## Files

```
patches/CAT-004-SD-7_rare-pack-listing-badge_20260918.php      — the runner (new)
diagnostics/CAT-004-SD-7_rare-pack-listing-badge_report_20260918.md  — this report
```

Targets the runner edits, all confirmed LF and anchored at count 1:

```
catalog/controller/product/thumb.php          2 anchors: class head, RD-04f tail
catalog/view/template/product/thumb.twig      2 anchors: state-flag set group, badge-tl block
catalog/view/stylesheet/boostershop-ds.css    1 anchor: /* /UI-FIX-20260903-TILES */
catalog/view/template/common/header.twig      1 anchor: boostershop-ds.css?v= (the second
                                              occurrence of the filename, :259, is inside a
                                              Twig comment and carries no ?v=)
```

Live sources used: `thumb.php` / `thumb.twig` — owner drops in the repo root, 2026-09-18;
`boostershop-ds.css` / `header.twig` — `booster-debug-CAT-004.tar.gz`, 2026-09-16.

## Local verification

Run against copies of the four live files in a sandbox, with the real Twig 3.18.0 taken
from the production backup and the same environment options `system/library/template/twig.php`
uses (`autoescape: false`, `debug: true`).

### Applied run

```
header_cache_bust_from=uifix-tiles-20260904
header_cache_bust_to=cat004-sd7-20260918
backup=…/_patch_backups/CAT-004-SD-7_rare-pack-listing-badge_20260918_122742
changed=catalog/controller/product/thumb.php
changed=catalog/view/template/product/thumb.twig
changed=catalog/view/stylesheet/boostershop-ds.css
changed=catalog/view/template/common/header.twig
php_l_thumb.php=ok
done=ok
self_delete=ok
```

The diff adds lines only, except the single header cache-bust line (acceptance §7.6).

### Rendered markup, pristine template vs patched template, same data

```
in-stock, no attribute           byte-identical
in-stock, bs_is_rare absent      byte-identical
pre-order, no attribute          byte-identical
out-of-stock, no attribute       byte-identical
discount, no attribute           byte-identical
in-stock, Rare Pack              differs (expected)
pre-order, Rare Pack             differs (expected)
out-of-stock, Rare Pack          differs (expected)
discount, Rare Pack              differs (expected)
TECH-015-WP3B script block byte-identical: yes
```

Acceptance §7.1 and §7.2 hold at the byte level, including the case where `bs_is_rare` is
never passed at all (a module that does not route through `product/thumb.php`).

This is why the inner Twig tags in the new block start at column 0: Twig emits the
whitespace around a tag and swallows only the newline directly after `%}`. Indenting them
would add whitespace to every tile on the site. The template carries that warning inline.

Badge corner as rendered:

```
# in-stock, Rare Pack
<span class="bs-pcard__badge-tl">
        <span class="bs-badge bs-badge--rare">Rare Pack</span>
      </span>

# pre-order, Rare Pack
<span class="bs-pcard__badge-tl">
        <span class="bs-badge bs-badge--preorder">Передзамовлення</span>
        <span class="bs-badge bs-badge--rare">Rare Pack</span>
      </span>

# out-of-stock, Rare Pack
<span class="bs-pcard__badge-tl">
        <span class="bs-badge bs-badge--out">Немає в наявності</span>
        <span class="bs-badge bs-badge--rare">Rare Pack</span>
      </span>
```

### Value matching (acceptance §7, last three rows)

```
"Rare Pack"      -> BADGE      "Протектор"   -> no badge
"RARE PACK"      -> BADGE      "Топлоадер"   -> no badge
"  Rare Pack  "  -> BADGE      ""            -> no badge
"\tRare Pack\n"  -> BADGE      "Rare Packs"  -> no badge
                               "Rare-Pack"   -> no badge
                               "Rare  Pack"  -> no badge
```

`Rare  Pack` with a doubled inner space does not match. Inner whitespace is not collapsed —
only leading and trailing whitespace is trimmed and case is folded, exactly as specified.

**Both mbstring paths agree.** The fallback for a host without mbstring is
`strtolower($bs_rare_value)`, not the raw value:

```
"Rare Pack"       mbstring:BADGE     no-mbstring:BADGE
"RARE PACK"       mbstring:BADGE     no-mbstring:BADGE
"  Rare Pack  "   mbstring:BADGE     no-mbstring:BADGE
"Протектор"       mbstring:no badge  no-mbstring:no badge
"Rare Packs"      mbstring:no badge  no-mbstring:no badge
```

Passing the value through unchanged, as the neighbouring RD-04f stock-status code does,
would have matched only a literal lowercase `rare pack` — not `Rare Pack`, which is what
the owner actually types into the attribute. The value is Latin, so ASCII `strtolower` is
sufficient and no dependency is added.

### Failure paths

| test | result |
|---|---|
| second run on a patched tree | `already_applied=yes`, `done=ok`, `self_delete=ok`, no writes |
| second run after a **later patch** wrote its own token | `already_applied=yes`; `header.twig` md5-identical, `?v=someone-elses-token-20260920` left exactly as found |
| one of the three content markers reverted | `ERROR=partial_marker_state`, exit 1, nothing written |
| an anchor no longer at count 1 | `ERROR=anchor_count_invalid:twig_badge_tl_block`, exit 1, no backup dir created, all four files md5-identical |
| `php -l` fails after the writes | all four files restored, each reported `restore:<file>=ok`, all four md5-identical to their pre-run bytes, runner retained |

### Order-independence with the variant-selector patch

Rule 8 gives order-independence only when **both** patches follow it. Tested against the
selector patch as it currently sits in `patches/`, and against a rule-8 stand-in built from
it in the scratchpad (not a delivered file — SD-7 does not author the sibling patch):

| order | selector patch | result | resulting token |
|---|---|---|---|
| SD-7 → selector | **on disk today** | selector aborts `anchor_count_invalid:header_css_cache_bust` | `cat004-sd7-20260918` |
| selector → SD-7 | on disk today | both `done=ok` | `cat004-sd7-20260918` |
| SD-7 → selector | rule-8 | both `done=ok` | `cat004-variant-20260916` |
| selector → SD-7 | rule-8 | both `done=ok` | `cat004-sd7-20260918` |

**`patches/CAT-004_variant-selector_20260916.php` on disk is still the pre-rule-8 version**
(`$header_old = '…?v=uifix-tiles-20260904'`, and `header.twig` still counted among its
idempotence markers). Until it is regenerated, **it has to run before SD-7.** Run it second
and it aborts — cleanly, before any write, with all five of its files untouched, so nothing
breaks — but it does not apply. Once regenerated per its handoff §3.6, either order works;
rows 3 and 4 prove it.

Whichever patch runs last owns the token. That is correct under rule 8: both CSS blocks live
in the same stylesheet, so any fresh token busts the cache for both. Both blocks land after
the `/* /UI-FIX-20260903-TILES */` marker in their own comment envelopes and do not collide.

## php -l

```
No syntax errors detected in patches/CAT-004-SD-7_rare-pack-listing-badge_20260918.php
No syntax errors detected in catalog/controller/product/thumb.php   (php_l_thumb.php=ok, in-runner)
```

**Both were run on PHP 8.3.30 — the only interpreter on this machine. Production is
PHP 8.0.** The injected code was reviewed by hand against 8.0: no `match`, enums,
`readonly`, `never`, named arguments, nullsafe operator, first-class callables or
`str_contains`. The newest construct used is `private const` (7.1). The runner's own
feature floor is the same as `CAT-004_variant-selector_20260916.php`, which ran on this
host. `php -l` inside the runner executes against the production binary at deploy time
and will catch anything this reasoning missed.

## Cost

One indexed lookup on `ocp5_product_attribute`'s primary key per tile, `static`-cached per
`language_id:product_id` — a product rendered twice on one page (grid plus related carousel)
costs one query. Up to ~20 extra queries on a 20-tile category page. Accepted in the handoff;
prefetching would mean touching six calling controllers.

## CSS

```css
.bs-badge--rare { background: #4C0519; color: #FDE68A; }
.bs-pcard__badge-tl { display: flex; flex-direction: column; align-items: flex-start; gap: 6px; }
```

- Contrast recomputed independently: **12.55:1** — the handoff's 12.6:1 is correct.
- No `!important`; nothing is overridden. `.bs-pcard__badge-tl` at `:273` keeps
  `position/top/left` — the appended rule only adds properties that rule never set.
- No existing `.bs-badge--*` rule, `.bs-pcard__badge-tr`, or the legacy
  `body.bs .product-thumb .bs-pcard-badge--*` block is touched.
- Size, weight, letter-spacing, radius, padding and `text-transform: uppercase` are
  inherited from `.bs-badge` at `:125`. The label is written `Rare Pack` in the markup and
  rendered `RARE PACK` by CSS — per canon §2 it is two words with no ornament, no icon and
  no tooltip.

## Rollback

Restore the four files from the `_patch_backups/CAT-004-SD-7_rare-pack-listing-badge_<ts>/`
directory the run prints, then clear the OpenCart cache. No database write, so nothing to
migrate back. Clearing the attribute value off the products removes the badge without
touching code.

If a run ends on anything other than `done=ok` + `self_delete=ok`, the runner stays in
`public_html` and is publicly executable by URL — delete it manually before anything else.

## Run command (owner)

Upload to `~/public_html`, set `Тип товару` = `Rare Pack` on all four `-RPK` products
first (§8.1 — a product where this is missed simply has no badge and nothing warns you),
then run. If the variant-selector patch is also being deployed and has **not** been
regenerated under rule 8, run it before this one — see **Order-independence**.

```bash
php CAT-004-SD-7_rare-pack-listing-badge_20260918.php
```

## Post-deploy QA — owner

Not a checkout, payment, fiscalization, SEO, canonical, sitemap or schema change. No gate
skill required. Risk is confined to how the tile looks.

- [ ] `Тип товару` = `Rare Pack` is set on **all four**: `OP-JP-EB01-RPK`, `OP-JP-OP01-RPK`, `OP-JP-OP05-RPK`, `OP-JP-OP06-RPK`
- [ ] clear the OpenCart cache, hard-refresh a category page
- [ ] a non-Rare-Pack tile looks exactly as before
- [ ] a Rare Pack tile shows `RARE PACK`; on a pre-order or sold-out one the two badges stack, 6px apart
- [ ] search results and the related-products carousel show it too
- [ ] **390 px** — two stacked badges take roughly 44px of the photo. If `ПЕРЕДЗАМОВЛЕННЯ` clips or wraps, report it; the fix is to drop the corner offset from 18px to 10px under the existing mobile breakpoint, as a follow-up
- [ ] Rare Pack product page → tab «Характеристики»: the row reads `Тип товару — Rare Pack` under the heading `Характеристики`. Any other heading means the attribute is back in the wrong group
- [ ] one existing product that carries `Тип товару` with a different value: tile and characteristics table unchanged

## Side effects / risks

- **The flag is invisible when absent.** An attribute-driven badge has exactly one failure
  mode: a product the owner forgot to tag renders a normal tile and nothing warns anyone.
  That is the first QA line above, and it stays true for every Rare Pack added later.
- **Per-tile query.** Accepted above, but it is a real new query on every listing page.
- **Run order against the selector patch, until that patch is regenerated.** The copy in
  `patches/` still anchors on the literal `?v=uifix-tiles-20260904`. Run SD-7 first and that
  patch can no longer apply — it aborts safely, but it aborts. Either regenerate it under
  rule 8 first, or run it before SD-7. Detail in **Order-independence** above.
- No database write. No change to `bs_state`, `bs_eta`, the pre-order predicate, the discount
  badge, the price row, the cart form or the TECH-015-WP3B GA4 block.

## Status

`In progress` until the owner has run the QA list on production. Notion status is written
by Claude (chat) — not by this surface.
