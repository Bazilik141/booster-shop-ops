# Report — UX-003 stage 3: category filter, panel above the grid (variant C) (runner 7)

Date: 2026-10-04 · Executor: Claude Code · Reviewer: Claude (chat), pending
Patch: `patches/UX-003_category-filter-C_20261004.php` · marker `UX-003-FILTER` · ds.css `?v=ux003filter-20261004`
Handoff: `handoffs/handoff_UX-003_category-filter-C_claude-code_20261004.md` (+ INDEX). Build: `evidence/build/cat/`:

- `make_candidate.py` — anchored, count-checked edits that turn the runner-6 `category.twig` into the candidate;
- `category.twig` — the candidate;
- `filter_markup.twig`, `section.css`, `body.php`.

## Blocker check (handoff §2, review «before hiding #button-filter»)

- The filter template exists, unencoded: `extension/opencart/catalog/view/template/module/filter.twig`. The fresh pull also contains its controller and language files.
- Its inline script applies the filter on checkbox `change` after 250 ms (`applyTimer = setTimeout(applyFilters, 250)`), so `#button-filter` is redundant. It stays in the DOM, hidden.

## Scope

As handoff §4. Implementation details the handoff left open:

- **Side column → panel.** `{{ column_right }}` renders inside the panel, with its wrapper swapped by `|replace`. The wrapper is `<aside id="column-right" class="col-3 d-none d-md-block">`, and `stylesheet.css` hides `#column-right` below 992 px (`display:none !important`, the old mobile drawer). `column_right.twig` itself is not edited, because product and information pages use it. The runner SHA-guards it read-only, since `|replace` depends on its exact aside line. If the category layout's right column holds modules other than the filter, they also appear in the panel.
- **Removed** from `category.twig`, with their inline CSS:
  - the desktop toolbar (`.bs-cat-header__toolbar`, `.bs-segmented`);
  - the mobile subcategory grid (`.bs-subcat-tabs`, UI-FIX-20260903 T9);
  - the mobile action row with `#mobileFilterToggle` and its drawer JS.

  The `.bs-cat-header__toolbar` rules in ds.css go too; no other template uses the class.
- **Sort:** one select replaces the two old ones; same options, same `onchange`. The category page never had `#input-sort`, the id the handoff names.
- **Grid 4 columns at ≥992:** stock `common.js` rewrites `#product-list` to `row-cols-lg-3` on every load (its list/grid localStorage switch), so the template's `row-cols-lg-4` never survives. A scoped CSS width (25 %) for that class at ≥992 does it, without `!important`. Cards keep their width: the old 3 columns sat beside a 3/12 side column.
- **Spacing:** below 992 px the header card gets a 14 px bottom margin, which the removed action row used to provide.
- **Groups:** only the first is open at ≤575; all are open from 576. The design breakpoint is <576.
- **Review N3 (done here):** the inline `:root` no longer redefines `--bs-sh-sm`. The load-more button keeps its old shadow as a literal. The sticky header shadow on category pages measures as the DS value again: `rgba(0,0,0,0.08) 0 1px 3px, rgba(0,0,0,0.05) 0 1px 2px`, was `rgba(17,24,39,0.03) 0 1px 0`.

## Finding — needs an owner decision (not changed)

**The UX-004 active-filter chips never render on production, before or after this runner.**

- `catalog/controller/product/category.php` (live pull) sets `$data['active_filters'] = [];` and never fills it. The 2026-05-30 redesign handoff asked for it to be built from `$_GET['filter']`; that was never done.
- The handoff says to move the chips, not rewrite them, so the block moved unchanged; it renders nothing live. The fixture fills `active_filters`, which is why the screenshots show chips.
- The panel foot therefore offers «Скинути» and «Згорнути» only. «Скинути» unchecks everything and lets the module apply.
- Options:
  - (a) accept: no chips;
  - (b) a controller task that fills `active_filters`, which is PHP in a risky zone;
  - (c) chips rendered client-side from the checked boxes.

## Files touched (SHA-256)

| File | Before (runner 6 output) | After |
|---|---|---|
| `catalog/view/template/product/category.twig` | `ac4f9d52fd000e10b3d9873b7fbe8387545fd324e472b9b0635fe6118ddb0497` | `505404c282376a50fa7268088c5fa6fc6f588e17c61be62a112dc26ef2e4243d` |
| `extension/opencart/catalog/view/template/module/filter.twig` | `bfa1648f6cfce814ba4b2c737099a632b44e9db19dc5675d3a4941eac87cdc54` | `96ea7f7458683e175b33684a97faf6942f1b1b5b8e5ef3a75e023fd86475fa34` |
| `catalog/view/stylesheet/boostershop-ds.css` | `b921a1ebb9d4356bdc54d676c29da36a4af8e9b8db0449f8061ad7ef27ad2bcd` | `bb40278e58261f78aa40477588ff8d038311668089be63166eecec2793622207` |
| `catalog/view/template/common/header.twig` | `f820b6d37ccd5c66376a00166234293c0d01eaa11791afc2563566f20675d505` | `0d96a9d56808550f1089a870d5a0f999cb194cc985bafa203916d9d5188e9bb4` |
| `catalog/view/template/common/column_right.twig` (read-only guard) | `0ff9302db636de24ddfb6baedf427d6a9f90854bb07ed7f2217ebbda114fa2b3` | not written |

## Gates

- SHA guard ×4, plus the read-only guard on `column_right.twig`; marker.
- `category.twig`:
  - byte-identical before the toolbar, and from the JSON-LD block to EOF (JSON-LD, FAQ, load-more scripts);
  - probes present once each;
  - removed classes absent;
  - no side `{{ column_right }}`.
- `filter.twig`:
  - the script is byte-identical;
  - `name="filter[]"`, the values and the ids are unchanged;
  - `#button-filter` is present.
- Hazard scan (category diff, filter markup); Twig parse gate (category, filter, header); CSS balance: passed.
- Repeat run `already_applied=yes`; self-delete yes. `php -l` and 8.0 compatibility scan: OK. The scanner had flagged a `? … : false` ternary as a false-positive type; the line was rewritten. Log: `evidence/logs/UX-003_category-filter-C_20261004.run1.log`.
- Runner-added text has no leading-dot decimals and no `{#`. The untouched inline-style lines carried over in `category.twig` still contain stock `.4s` / `.18s` / `.65` values; they are live today and unchanged.

## Behaviour checks (`evidence/cat/`, before = runner 6 output)

- **1440:** no side column. One row: chips in a segment, «Фільтр», sort at 220 px. 4 product columns (before: 3 plus the column).
- **768:** one row; the chips scroll; «Фільтр» with its label; sort at 200 px; no duplicate.
- **390:** one row; the chips scroll; «Фільтр» and sort are 44×44. The sort tap target is the real `<select>`, `aria-label` «Сортування».
- **No horizontal scroll** at any of the three widths.
- **Panel:**
  - `aria-expanded` toggles;
  - groups collapse one by one (390: first open, second closed);
  - the badge counts the checked boxes (`filter=12` → 1, `12,21` → 2);
  - «Згорнути» closes the panel and returns focus to «Фільтр».
- **Variants:** with no subcategories the tools stay on the right. With no filter module there is no «Фільтр».
- **Same URL before and after:**
  - checking «Бустер» gives `route=product/category&language=uk-ua&path=59&filter=12`;
  - unchecking one of two gives `filter=12`;
  - «Скинути» gives the URL without `filter`;
  - sort on the 390 overlay navigates.
- **SEO:** the `<head>` of `?filter=12` and of `?filter=12,21&sort=p.price&order=ASC` is byte-identical before and after (ds.css token and host normalised; `evidence/cat/*_head_*.txt`). Not changed by this runner:
  - the category controller (canonical = clean category URL; `noindex,follow` with filter/sort/order/limit);
  - the filter module PHP;
  - `header.twig` beyond its token.

## bs-seo-risk-gate

| Item | Result |
|---|---|
| Risk | Medium |
| Affected assets | Every category page type (template), and internal linking: the separate desktop and mobile subcategory navs become one nav with the same hrefs |
| Not affected | canonical, meta robots, URL parameters, sitemap, robots, redirects, JSON-LD, pagination `<noscript>`, Merchant feed |
| Safest next action | Deploy last in the chain, then a live view-source before/after on two filtered URLs |
| Owner approval beyond normal deploy QA | No |
| Related smoke | None (no schema, feed or checkout) |

## Rollback

```bash
B=$(ls -d _patch_backups/UX-003_category-filter-C_20261004-* | tail -1); (cd "$B" && find . -type f) | while read f; do cp "$B/$f" "$f"; done
```

Then refresh the theme cache and press Ctrl+F5.

## Owner QA (production)

Before running: save view-source of `/catalog/Pokemon?filter=<id>` and of a second filtered URL with `&sort=p.price&order=ASC` (canonical, `<meta name="robots">`). Then run `php UX-003_category-filter-C_20261004.php` in `~/public_html`, refresh the theme cache and press Ctrl+F5.

1. Pokémon category at 1440: there is no side column. One row holds the subcategories, «Фільтр» and the sort. 4 columns.
2. «Фільтр» opens and closes the panel. Each group collapses on its own; the counters show the number checked.
3. Ticking a box applies the filter as before: the same URL in Network or the address bar. Untick a box; press «Скинути». Run pagination and sort with an active filter.
4. 768 and 390: the row does not wrap, and the subcategories scroll. On 390 both buttons are square. There is no second filter button.
5. A category without subcategories, and one without filters: there is no «Фільтр» when the module is absent.
6. View-source after: canonical and robots match the saved copies.
7. The console shows no new errors.
