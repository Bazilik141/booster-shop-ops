# Report — UX-003: category heading flash (runner 8b)

Date: 2026-10-05 · Executor: Claude Code · Reviewer: Claude (chat), pending
Patch: `patches/UX-003_category-heading-fouc_20261005.php` (SHA-256 `edffc10eb682a001118057a629c0b225defdfadd889860000ea726ba43b2b8f7`) · marker `UX-003-FOUC` · no ds.css change, so no token bump
Handoff: INDEX section «Runner 8 deployed; runner 8b — category heading flash». Build: `evidence/build/fouc/`. Evidence: `evidence/fouc/`.

## Base

The live2 pull plus runners 1, 2, 3, 3b, 4, 5, 6, 7 and 8 from `patches/` were applied in a scratch copy. All nine returned `done=ok`, and every target matched runner 8's reported after-SHA (`evidence/logs/chain_1-8b/`).

## Change

`product/category.twig` only. The first inline `<style>…</style>` block, 10,973 bytes, moves unchanged from after the page markup (old lines 180–636) to directly after `{{ header }}`.

One Twig comment line above it is the idempotency marker. It renders nothing: the rendered page is the same length before and after.

The cascade is unchanged: the block still comes after the `<head>` stylesheets (ds.css, stylesheet.css) and before the second inline block.

| File | Before (runner 8 output) | After |
|---|---|---|
| `catalog/view/template/product/category.twig` | `d4462c7554ecb880100dcfe9e71c71ec6b3ec862add2e4eee8eefe105a17b977` | `c7c658d677a120ab3895e37547de7f782b96b8ff42986db2309b177f1f50d54f` |

Diff: `evidence/fouc/runner8b_diff.txt`. Runner 9 and BUG-004 build on this `category.twig`.

## Gates

Logs: `evidence/logs/UX-003_category-heading-fouc_20261005.*.log`

- **run1:**
  - SHA guard;
  - the block is located by unique start/end anchors and its hash is checked;
  - probes `.bs-heading-full`, `.bs-heading-mobile` and `.bs-cat-header__title h1` are present;
  - the block contains no Twig tags;
  - `template_minus_block=identical` and `block=identical`;
  - the counts of `{% if products %}`, JSON-LD, `bs-load-more-btn` and `{{ footer }}` are unchanged;
  - hazard scan on the marker;
  - Twig parse gate (site Twig, control parse first);
  - backup, `done=ok`, self-delete.
- **Independent proof** (Python, outside the runner): new template minus the marker line and the block equals old template minus the block.
- **Rendered fixture HTML** for 4 category variants (with/without subcategories, filter applied, no filter module): identical before/after apart from the block's position. The fixture host/port is the only other difference.
- **run2:** `already_applied=yes`, no backup, self-delete.
- **neg:** on runner 7's output the runner stops with `sha256_mismatch`, writes nothing and is not deleted.
- **restoretest:** a blocked temp write gives `restore=ok`, and the file is byte-identical to the base.
- `php -l`: OK. No PHP 8.1+ syntax.

## Reproduction: first painted frames

Method (`evidence/build/fouc/harness/`):

- `chunkproxy.mjs` serves the category fixture's HTML in two chunks. Chunk one runs up to the `<div id="product-list"` line, right after the header card. The rest follows 3 s later.
- CSS, JS and fonts are warmed first, so only the HTML is slow, as on a chip tap.
- `fouc.mjs` (headless Chrome, CDP) samples every ~120 ms: the composited frame (`Page.captureScreenshot`) and the H1's computed state.

All samples: `evidence/fouc/fouc_samples.json`. Frames: `evidence/fouc/<before|after>_<width>_fNN_<ms>.png`.

| | Final H1 (page complete) | Frames with H1 before the second chunk | Frames that differ from the final H1 |
|---|---|---|---|
| before 390 | 12 px caption, mobile span only | 21 | **18** (219–3021 ms): 26 px, both spans, «Pokémon Pokémon» |
| before 768 | 24 px, mobile span only | 20 | **17** (217–2944 ms): 26 px, both spans |
| after 390 | 12 px caption, mobile span only | 21 | **0** |
| after 768 | 24 px, mobile span only | 20 | **0** |

After the fix, the H1 never paints at the large size or with both spans visible.

The same frames show one more, unrelated flash, which this runner does not touch. At ≤768 the search placeholder reads «Пошук бустерів…» until the deferred header JS swaps it to «Пошук». It is visible only while the HTML is still loading.

## Item 4: other inline styles (report only, nothing changed)

| Template | Inline `<style>` | Styles markup above it in the first screen? |
|---|---|---|
| `product/category.twig`, second block (old line ~795) | `.bs-faq-*` accordion, `.bs-special-seo`, one ≤767 rule | **No.** The FAQ markup comes from `{{ description }}` in `.category-description`, below the product grid. The block sits above it in source but out of the first screen, so at most an unstyled FAQ flashes far down the page on a very slow load. |
| `product/product.twig` (lines 592–674) | sticky buy bar: `.bs-sticky-atc*`, `.bs-qty*`, `body.bs-sticky-atc-visible` | **No.** The bar markup follows the block (line 675), carries `hidden`, and JS shows it only after scrolling. |
| `product/search.twig` | none | — |
| `common/home.twig` (lines 156–159) | an empty `@media (max-width: 767.98px) {}` | **No.** It styles nothing; it is dead and harmless. |

## Rollback

Do not roll back while runner 9 or BUG-004 is applied.

```bash
cd ~/public_html && B=$(ls -d _patch_backups/UX-003_category-heading-fouc_20261005-* | tail -1) && (cd "$B" && find . -type f) | while read f; do cp "$B/$f" "$f"; done
```

Then refresh the theme cache and press Ctrl+F5.

## Owner QA (production)

Run `php UX-003_category-heading-fouc_20261005.php` in `~/public_html`, refresh the theme cache, then press Ctrl+F5.

1. On the phone: Pokémon category, then tap each subcategory chip and go back. The heading never shows large or doubled («Pokémon Pokémon»); it appears as the small caption straight away.
2. Repeat at a tablet width (~768 px) and at 1440 px: no heading jump.
3. The category page otherwise looks and works as after runner 8: chips, «Фільтр» panel, sort, load more, FAQ.
4. The console shows no new errors.
