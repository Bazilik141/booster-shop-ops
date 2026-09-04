# Claude Code Report — UI-FIX: mobile/desktop polish batch

Date: 2026-09-03
Handoff: `handoffs/handoff_UI-FIX_codex-handoff_20260902.md`
Executor: Claude Code (owner override 2026-09-03, switched from Codex)
Component D spec: `D - термін доставки передзамовлення v2 ФІНАЛ.html` (owner, 2026-09-03)

## Scope

Handoff Tasks 1, 2, 3a, 3b, 4, 5, 6, 7, 8, 9, 10 implemented. Task 11 is
absorbed into Task 10 by the owner's simplified Component D v2 and needs no
separate work.

Four deviations from the handoff, all forced by what production actually
contains:

| # | Handoff assumed | Reality | Consequence |
|---|---|---|---|
| 1 | Task 5 is a stale-discount or cache problem, direction unclear | A `product_discount` row with `quantity = 1` on two products; `product.price` was correct all along | Fix is a DB delete, not a price edit or a cache flush |
| 2 | Task 3a edits a template | The guarantee page body is admin rich text in `information_description` | Task 3a became a DB change (owner approved option A) |
| 3 | Task 8's legacy `.category-tiles` is template markup and a visible duplicate row | It is HTML module `opencart.html.5` placed on the Home layout, and already computes to `display:none` at every width | Removal is a DB change and is invisible; the gain is a duplicate crawler link pair and dead markup, not a visual fix |
| 4 | Task 8 deletes the tile description copy (SEO flag raised) | Owner decision 2026-09-03 | Copy is reformatted as a 4-question homepage FAQ instead of being deleted or CSS-hidden |
| 5 | Component B's DOM block sets `loading="lazy"` on both tiles | The first tile is above the fold on both mobile and desktop | Pokémon tile ships `loading="eager" fetchpriority="high"`, One Piece stays `loading="lazy"` — shortens LCP on the tile that is actually the LCP candidate. Disclosed here rather than left for a reviewer to spot (round-1 finding F5) |

## Task 5 — what was actually wrong

The handoff called the root cause unconfirmed and asked not to guess. It is now
confirmed, and it is none of the three options the handoff listed.

**Evidence, production, 2026-09-03**

A full catalogue sweep — every one of the 99 rows in `merchant-feed.tsv`
compared against its own product page — found exactly two mismatches:

```
MISMATCH PKM-JP-INFX-BBX feed=6000.00 page=5700.00  product_id 148
MISMATCH PKM-JP-MDEX-BBX feed=4900.00 page=4500.00  product_id 115
```

The other 97 agree. On both products the product page shows a single price with
no struck-through original, so it is not rendering a special: a control product
with a live special (`Pokemon-booster-box-Mega-Brave`) correctly renders
`₴4900.00` struck plus `₴4700.00`, proving the special branch works.

`product.price` is **6000.00 and 4900.00** — confirmed by three independent
paths that all agree: category listing, site search, and `merchant-feed.tsv`.
A fresh-SQL probe (`?limit=2`, `?limit=37`, `?sort=p.price`) returns the same
values, which rules out the `product.*` listing cache in
`ocartdata/storage/cache/`.

The 2026-08-28 dump carries the matching row for product 115:

```
product_discount_id 1163, product_id 115, quantity 1, special 0,
price 4500.0000, type F, date_start 0000-00-00, date_end 0000-00-00
```

`quantity = 1` is the defect. In OpenCart the "Discount" tab means "from N
units" and N must be ≥ 2; a `quantity = 1` row is malformed input. Empty dates
mean *always active* in this build's catalog SQL
(`date_start = '0000-00-00' OR date_start < NOW()`), which is why the owner
could not switch it off from the admin. The product page and the cart consume
it; listings, search and the feed do not.

**The cart charges the discounted price** — owner-verified 2026-09-03: Inferno X
adds to cart at 5700. Both boxes were therefore selling 300 ₴ and 400 ₴ under
the intended price. This is why the price patch ships first and separately.

Owner decision 2026-09-03: 6000 and 4900 are correct; delete the rows. No
`product.price` is written.

## Patches

```
patches/UI-FIX_price-discount-rows_20260903.php      DB   — Task 5
patches/UI-FIX_mobile-desktop-polish_20260903.php    files — T1,2,3b,4,6,7,9,10 + FAQ CSS
patches/UI-FIX_home-category-tiles_20260903.php      files + images — Task 8
patches/UI-FIX_cms-content_20260903.php              DB   — Task 3a, legacy tile row, FAQ markup
```

**Run in that order.** `UI-FIX_cms-content` depends on `UI-FIX_mobile-desktop-polish`
for the OLX icon asset, the FAQ accordion CSS and the content-page link-colour
fix; run alone it leaves broken images and unstyled markup.

### Files and rows touched

```
catalog/view/stylesheet/content-pages.css
catalog/view/stylesheet/booster-typography.css
catalog/view/stylesheet/boostershop-ds.css
catalog/view/template/account/login.twig
catalog/view/template/product/product.twig
catalog/view/template/product/category.twig
catalog/view/template/common/home.twig
image/catalog/reviews/olx-review-icon-120.png          (new)
image/catalog/tiles/category-tile-{pokemon,onepiece}-{540,1080}.webp  (new)

ocp5_product_discount        2 rows deleted
ocp5_information_description information_id 5, 1 row updated
ocp5_layout_module           1 row deleted (layout 1 · opencart.html.5)
ocp5_module                  module_id 9 setting updated
```

`adminEvhenii/` is not touched. No controller or model PHP is touched.

## Root causes named before patching (UI/CSS discipline #1)

**T1 — content page heading rhythm.** `content-pages.css:434-435`, the
`PAY-001-INFO-1-20260726` block, re-declares `.bs-cp-main h2 { margin:44px 0
18px; font-size:22px }` *after* the `@media (max-width:768px)` rule at line 366
that sets `margin-top:28px; font-size:20px`. The mobile values have been dead
since 2026-07-26. Fixed by moving the desktop rhythm into the base rule at line
197 and leaving the PAY-001 block only the flex row it exists for. No
`!important`, no new override, desktop output byte-identical.

**T6 — breadcrumb chip overflow.** `.bs-crumb__current` is
`display: inline-flex`. `text-overflow: ellipsis` has no effect on a flex
container, so the `max-width`, `overflow:hidden` and `white-space:nowrap`
already present were doing nothing visible. Measured live at 375px before the
fix: computed `max-width: 232.5px`, text still spilling. Fixed by switching to
`inline-block` plus an explicit 16px line-height (26px box − 2px border − 8px
padding). The last crumb also becomes shrinkable (`flex: 0 1 auto; min-width:0`)
so the pill's own right edge stays on screen instead of running under the scroll
container — no magic pixel width, it adapts to the ancestor crumb length.
`grep patches/` for `bs-crumb__current` and `bs-crumb`: **no prior patch touches
this selector** (UI/CSS discipline #2).

**T9 — subcategory row breaking past 4.** `category.twig` carried both
`:has(> :nth-child(4):last-child)` / `:nth-child(6)` rules and a
`--count-1..6` ladder of `!important` grid overrides. Both key on an exact
subcategory count, which is precisely what broke when Pokémon gained a fourth
subcategory. All of them are deleted and replaced by one fixed 2-column grid
with `> :nth-child(odd):last-child { grid-column: 1 / -1 }`. The
`bs-subcat-tabs__row--count-N` class is also removed from the markup, since
nothing consumes it any more and leaving it invites the same mistake again.

## Verification

### Local, against real data

**Task 5 patch executed for real**, not just read: MySQL 8.4.3 throwaway instance
on port 3399, loaded from `mysql/boosters_ocart49.sql` out of
`backup-8.28.2026_13-26-46_boosters.tar.gz` (178 tables), with product 115/148
prices and the missing 148 row set to the values measured live, plus two decoy
rows that must survive (`quantity = 5, special = 0` and `quantity = 1,
special = 1`).

```
plan=delete discount_id=1177 PKM-JP-INFX-BBX qty=1 special=0 price=5700.0000 (base stays 6000.0000)
plan=delete discount_id=1163 PKM-JP-MDEX-BBX qty=1 special=0 price=4500.0000 (base stays 4900.0000)
deleted_rows=2
base_prices_untouched=yes
```

- both decoys survived; `product.price`, `status`, `quantity`,
  `stock_status_id`, `date_modified` unchanged;
- `restore.sql` re-created both rows with their original
  `product_discount_id`s and the table returned to 46 rows;
- second run printed `already_applied=yes`;
- drift guard: with `product.price` set to 5555 the patch exited 1 with
  `base_price_drift:PKM-JP-INFX-BBX:5555.0000:expected=6000.0000` and deleted
  nothing.

**Task 3a / tile row / FAQ patch executed for real** against the same instance:
1 information row updated, `layout_module` 41 deleted, module 9 setting
extended; module 5 itself verified still present; `restore.sql` replayed clean
and returned all three to their previous state; second run printed
`already_applied=yes`.

**Image pipeline executed for real** against the two production source PNGs
(both confirmed 1254×1254, i.e. already square — pure downscale, no crop):

```
tile=category-tile-pokemon-1080.webp  1080x1080 q72 142864B
tile=category-tile-pokemon-540.webp    540x540  q80  66110B
tile=category-tile-onepiece-1080.webp 1080x1080 q80 150198B
tile=category-tile-onepiece-540.webp   540x540  q80  52310B
```

The spec's "drop to q72 rather than shrinking dimensions if a file exceeds
160 KB" fires on exactly one file, the Pokémon 1080 (183138 B at q80). This is
measured, not assumed. GD with WebP encode is present on production — the site
already writes `.webp` derivatives into `image/cache/` through OpenCart's own
resizer — and the patch still checks and aborts rather than writing a broken
file.

**File patches applied to a copy of the real tree** and diffed; both re-read
every file after writing and abort-and-restore on mismatch.

### Live browser, on production pages

Injected the new CSS into the live pages and measured (read-only; nothing was
written to the site).

| Check | Result |
|---|---|
| T6 · 360px | chip 208px, ellipsis, no row overflow |
| T6 · 375px | chip 223px, whole pill on screen, `scrollWidth == clientWidth` |
| T6 · 1200px | full name, no truncation, height 26px — matches `.bs-crumb__link` |
| T1 · 390px | every `h2` 28px top / 18px bottom / 20px — uniform rhythm |
| T7 · 360px | both buttons one row, 143px each, **48px tall**, gap 12px, 16px above |
| T9 · `/catalog/Pokemon` | 2×2 grid, gold badges, `<h1>` still `H1` at 12px, first product card 326px from top |
| T9 · `/catalog/One-Piece` | 2 + 1 full-width row, blue badges |
| T9 · count-agnostic | cloned to 2, 3, 5, 6, 7 cards — all lay out, odd totals span the last row |
| T8 · 390px | two 176×176 tiles side by side, CTA legible on the scrim |
| T8 · 1280px | two 546×546 tiles, gap 24px |
| T3a | cards render `#111827`, `text-decoration: none` inside `.bs-cp-main` |
| FAQ | 4 items, answers `#1f2937`, muted intro paragraph keeps `#6b7280` |

## `php -l`

```
No syntax errors detected in patches/UI-FIX_price-discount-rows_20260903.php
No syntax errors detected in patches/UI-FIX_mobile-desktop-polish_20260903.php
No syntax errors detected in patches/UI-FIX_home-category-tiles_20260903.php
No syntax errors detected in patches/UI-FIX_cms-content_20260903.php
```

Each patch also lints itself at startup before touching anything.

## Idempotency

All four return `already_applied=yes` and self-delete on a repeat run. The two
file patches key on a marker in the written files plus the presence of the new
assets; the DB patches key on the absence of matching rows / presence of the new
content.

## Rollback

| Patch | Restore |
|---|---|
| price-discount-rows | `_patch_backups/UI-FIX_price-discount-rows_20260903-<ts>/restore.sql` — re-creates both rows with their original ids |
| mobile-desktop-polish | copy the 6 files back from `_patch_backups/UI-FIX_mobile-desktop-polish_20260903-<ts>/`, delete `image/catalog/reviews/` |
| home-category-tiles | copy `home.twig` and `boostershop-ds.css` back from `_patch_backups/UI-FIX_home-category-tiles_20260903-<ts>/`, delete `image/catalog/tiles/` |
| cms-content | `_patch_backups/UI-FIX_cms-content_20260903-<ts>/restore.sql` — full previous value of all three rows |

Both `restore.sql` files are written **before** any write. Both DB patches run
inside a single transaction and roll back on any failed assertion.

## Run commands (owner, from `~/public_html`)

**Pre-flight — the real syntax gate.** No PHP 8.0 binary exists in any sandbox
available to this project, so production's own `php -l` is the only true check
that these files parse on PHP 8.0.30. Upload all four, then run this first in
cPanel Terminal. Every line must say `No syntax errors detected`; if any does
not, stop and do not run that patch.

```bash
for f in UI-FIX_price-discount-rows_20260903.php UI-FIX_mobile-desktop-polish_20260903.php UI-FIX_home-category-tiles_20260903.php UI-FIX_cms-content_20260903.php; do php -l "$f"; done
```

```bash
php UI-FIX_price-discount-rows_20260903.php --dry-run
```

```bash
php UI-FIX_price-discount-rows_20260903.php
```

```bash
php UI-FIX_mobile-desktop-polish_20260903.php
```

```bash
php UI-FIX_home-category-tiles_20260903.php
```

```bash
php UI-FIX_cms-content_20260903.php
```

## Post-deploy QA

- [ ] Inferno X and Mega Dream EX: product page, category listing and cart all
      show 6000 and 4900. Add one to the cart and check the total.
- [ ] `/information/original-garanty` at 390px, tablet and desktop — even
      spacing around every heading; Telegram + OLX cards in place of the old
      text link; Telegram card opens `t.me/boostershop_tcg/23`.
- [ ] `?route=account/login` — Увійти block first, Реєстрація second with the
      new one-line copy. **Perform a real login and a real registration**
      (`ACC-003` token defect lives on this page).
- [ ] Any product page: OLX icon renders as the teal brand mark, not stretched;
      "Відгуки про нас →" scrolls to the tab row and opens Відгуки instead of
      leaving the site.
- [ ] A long-name product page at ~360–390px — breadcrumb chip truncates with
      an ellipsis inside the pill.
- [ ] A preorder product — "Доставка · орієнтовно 3–4 тижні" row appears under
      Статус. An in-stock product — the row is absent. No product card anywhere
      shows a delivery term.
- [ ] Credit modal on a product ≥500 ₴ — both buttons on one row, ≥48px, at
      360px and desktop; **both provider flows still submit** (PAY-002/PAY-003).
- [ ] Homepage — two square tiles, `.webp` served, correct links, `.bs-subtiles`
      unchanged, FAQ accordion at the bottom opens and closes.
- [ ] `/catalog/Pokemon` and `/catalog/One-Piece` at 390px — subcategory cards,
      full names, correct counts; desktop segmented control unchanged.
- [ ] Tier 1 smoke URLs (`AGENTS.md`) — this batch touches shared CSS and a
      shared product-page partial.

## Side effects and risks

**Needs an owner decision at review**

- **FAQ copy is a draft.** Four questions and answers were written to carry the
  keywords the tile text used to hold. Claude adapts them at review, as agreed.
- **Desktop tiles are large.** At a 1280px viewport each tile is 546×546. That
  is what 1:1 at two columns produces and it is what the Component B spec asks
  for, but it is the one thing worth eyeballing before accepting.

**Deliberate deviations from the written spec**

- **Task 7 colours left alone.** The Component A spec lists the secondary
  button as `border:1.5px solid var(--bs-line); color:var(--bs-ink-2)` under a
  heading that says "colors unchanged". Production actually has
  `border: 1px solid #111; color: #111`. Applying the spec's values would be a
  colour change, which both the design brief and the task's own scope line
  ("presentation only — button height, layout, spacing") forbid. Only height,
  layout and spacing changed. Flagging rather than silently picking one.
- **Task 9 breakpoints.** The spec says mobile ≤640px. This component is
  `display:none` above 991.98px, so the card grid is applied across the whole
  ≤991.98px range and only the H1-to-caption demotion is scoped to ≤640px.
  Between 641px and 991px you get the current heading plus the new cards.
- **Count badge default colour.** The spec named colours for Pokémon and One
  Piece only. Other categories (Yu-Gi-Oh!, MTG, accessories) get a neutral
  `ink-3 on line-2` badge rather than inheriting Pokémon gold, which is what the
  old `is-active` rule did.

**Risk notes**

- The ПУМБ card currently renders as "СКОРО БУДЕ" with **no buttons** on the
  product page checked, so the "both providers side by side" acceptance line
  can only be verified on monobank until ПУМБ is switched on there.
- The homepage FAQ markup lives in an admin HTML module. Opening module 9 in the
  admin editor and saving may let CKEditor strip `<details>`. Edit that block by
  patch, not through the editor.
- The old `.bs-catcard*` rules in `boostershop-ds.css` no longer match any
  markup after Task 8. They are left in place rather than widening the diff into
  the neighbouring shared `.bs-subtiles` rules; worth a separate cleanup task.
- Nothing in this batch touches `date_available`, `stock_status_id`, order
  status, checkout, cart logic, payment APIs, sitemap, robots, canonical,
  `.htaccess`, the Merchant feed or schema/JSON-LD.
- `login.twig` changes are a block reorder plus copy. The form element, its
  `action`, and every token field are byte-identical; a patch assertion checks
  the OLX link count and the two-column structure after the swap.

---

# Addendum — round-1 review fixes

Date: 2026-09-03
Source: `handoffs/handoff_UI-FIX_fix-requests-round1_20260903.md` (Claude chat
`bs-patch-review`, findings F1–F5; owner dispositioned all five)

Round 1 only. "Fixes applied" is not "cleared to deploy" — that call belongs to
round 2, in a new session.

## F3 — `never` return type, blocking. Fixed

My mistake, and the local toolchain hid it: the only PHP on this machine is
8.3.30, so `php -l` passed on a construct that PHP 8.0 cannot parse at all. On
production the file would have died with a bare parse error before its own
guard code — including its own `php_lint()` — could run.

All 5 occurrences removed; `): never {` became `) {`, no replacement type:

```
patches/UI-FIX_price-discount-rows_20260903.php     fail()                     1
patches/UI-FIX_cms-content_20260903.php             fail()                     1
patches/UI-FIX_home-category-tiles_20260903.php     fail()                     1
patches/UI-FIX_mobile-desktop-polish_20260903.php   fail()                     1
patches/UI-FIX_mobile-desktop-polish_20260903.php   restore_files_and_fail()   1
```

`grep -n "): never"` across the four files now returns nothing. Behaviour is
unchanged: every one of these functions either throws or calls `exit()`, so no
execution path ever reaches the end of the function, and PHP only enforces a
return type when a function actually falls through.

**Went past the grep.** A single `grep` for one keyword proves nothing about
the other seven ways an 8.1+ construct can enter a file, so I wrote a
tokenizer-based scan (`token_get_all`) covering: `never` / `true` / `false` /
`null` used as types, `enum`, `readonly`, `final const`, first-class callable
`f(...)`, explicit octal `0o`, intersection return types, and the runtime
functions added in 8.1–8.4 (`array_is_list`, `enum_exists`, `json_validate`,
`str_increment`, `mb_str_pad`, `array_find`, …).

The scanner was validated against a canary file containing all eight
constructs — it reports all eight. Against the four patches:

```
scanned: patches/UI-FIX_price-discount-rows_20260903.php
scanned: patches/UI-FIX_mobile-desktop-polish_20260903.php
scanned: patches/UI-FIX_home-category-tiles_20260903.php
scanned: patches/UI-FIX_cms-content_20260903.php

RESULT: no PHP 8.1+ construct found — all four parse-compatible with PHP 8.0
```

This is a static scan on 8.3, not a real 8.0 parse. Production's own `php -l`
remains the gate, and it is now the first step of the run sequence above. The
scanner itself lives in the session scratchpad, not in the repo — this round's
scope was three named fixes plus report edits. It is worth adding to `scripts/`
as a pre-flight check if the owner wants it, since this exact class of mistake
has now landed three times (2026-08-24 twice, and here).

**Also checked while in there:** both DB patches use `mysqli_stmt::get_result()`
and `fetch_all()`, which need mysqlnd. `LEGAL-002_offer_mono_pumb_archive_20260724`
uses the same calls and left a real backup directory on production
(`_patch_backups/LEGAL-002_offer_mono_pumb_archive_20260724-20260724-085457`),
so it executed there — mysqlnd is present. No change needed.

## F1 — silent skip on content drift. Fixed

`patches/UI-FIX_cms-content_20260903.php`, information-row loop. The
`$count === 0` branch no longer `continue`s; it calls `fail()` with the
language id and an explicit "content has drifted, refusing to guess" message,
and a comment above the branch records why a silent skip was wrong.

Verified against real production data (the 2026-08-28 dump loaded into a
throwaway MySQL 8.4.3, 178 tables):

**Row classification adds up — no row falls through uncounted.**

```
rows_for_info5 = 1

php_lint=ok
plan_information_rows=1
information_rows_already_done=0
dry_run=ok
```

`1 planned + 0 already-done = 1 total`.

**The new branch actually fires, and writes nothing.** With the row drifted
(the OLX anchor text edited, simulating a manual admin change) both `--dry-run`
and the real run abort:

```
ERROR=information_row_unexpected_content:language_id=4 - neither the
bs-review-cards marker nor the OLX link anchor was found; content has drifted
from what this patch expects, refusing to guess
exit=1
```

and the other two changes stayed untouched — `layout_row_still_there = 1`,
`faq_written = 0`. The abort is patch-wide, not row-wide, which is the right
conservative behaviour: it never half-applies.

After restoring the row, the full run still completes
(`information_rows_updated=1`, `layout_module_rows_removed=1`,
`faq_installed=yes`), a second run prints `already_applied=yes`, and
`restore.sql` replays clean — `layout_restored=1`, `faq_present=0`,
`cards_present=0`.

## F4 — FAQ copy. Replaced verbatim

The `$items` array in `faq_html()` now holds Claude (chat)'s finalized wording
exactly as supplied; nothing else in the function changed. Read back out of the
database after a real run:

```
Q: Які бустери Pokémon TCG є в наявності?
Q: Що є з One Piece Card Game?
Q: Які ще колекційні карткові ігри у вас є?
Q: Чи продаєте аксесуари для зберігання карток?

link targets: /catalog/Pokemon, /catalog/One-Piece, /catalog/more-tcg, /catalog/acsesuary
```

## F5 — eager/fetchpriority. Disclosed

Added as row 5 of the deviations table at the top of this report. No code
change; the attributes were already intentional.

## F2 — closed by the owner

Task 10 stays as shipped (constant string, product page only, gated on
`_is_preorder`, no DB). Task 11 is moot.

## Re-verification after the edits

```
No syntax errors detected in patches/UI-FIX_price-discount-rows_20260903.php
No syntax errors detected in patches/UI-FIX_mobile-desktop-polish_20260903.php
No syntax errors detected in patches/UI-FIX_home-category-tiles_20260903.php
No syntax errors detected in patches/UI-FIX_cms-content_20260903.php
```

Both file patches were re-applied to a fresh copy of the real tree after the
signature edits and completed with `readback=ok` and `done=ok`, and the tile
patch regenerated all four WebP files at the same sizes and qualities as in
round 1. Nothing was run against production.

---

# Addendum — round-2 review fix

Date: 2026-09-03
Source: `handoffs/handoff_UI-FIX_fix-requests-round2_20260903.md`
Scope: the two DB patches only. The two file patches were cleared in round 2 and
were not touched.

## The finding — `get_result()` / `fetch_all()` are fatal on this host

The round-1 addendum concluded "mysqlnd is present. No change needed." **That
conclusion was wrong, and the reasoning behind it was unsound.** I argued that
`LEGAL-002_offer_mono_pumb_archive_20260724` left a `_patch_backups` directory on
production, therefore it executed, therefore `get_result()` worked. A backup
directory proves a patch *started*, not that it reached any particular line.
LEGAL-002 v1 read its schema through plain `$db->query()` — which needs no
mysqlnd — and stopped at the column guard on line 475, long before its first
`get_result()` on line 408 inside a function called later.

The repo's own diagnostics say the opposite, and say it plainly:

- `LEGAL-002_offer_mono_pumb_archive_v3_report_20260724.md`: "the host PHP build
  has no `mysqlnd`, so `mysqli_stmt::get_result()` is unavailable."
- `LEGAL-002_offer_mono_pumb_archive_v4_report_20260724.md`: "V2 failed before
  transaction due to `get_result()`; diagnostic confirmed a PHP mysqli build
  that also lacks `fetch_all()`."

Both were checked directly this round rather than taken from the handoff.

Why nothing local could have caught it: this is a **host capability**, not a
language version. The code is valid PHP 8.0, `php -l` passes, and every local
run passes because local PHP has mysqlnd. Both patches would have died on their
very first `rows()` call — the `SELECT 1 FROM <table> LIMIT 0` table probe —
which sits before `begin_transaction()` in both files. No data would have been
corrupted; the patch would simply have been dead on arrival with a bare fatal
instead of any of its own messaging.

## The fix

The `get_result()` + `fetch_all()` tail of the shared `rows()` helper is replaced
in **both** patches with `result_metadata()` + `bind_result()`, following
`bs_stmt_rows()` in `patches/LEGAL-002_offer_mono_pumb_archive_v4_20260724.php`
(lines 406-416) — the implementation that ran to completion on this host.

The signature and return shape are unchanged (a list of associative arrays keyed
by column name), so no caller changed. The per-iteration `$copy` inside the
`while` loop is present and commented: `bind_result()` binds by reference, and
appending `$row` directly would yield N identical rows. The existing
`need()` guards around prepare / bind_param / execute are untouched and the new
guards use the same message format (`result_failed`, `result_bind_failed:...`).
`execute()`, the write helper, uses neither call and was not modified.

No query, planning branch, assertion or anything else cleared in round 1 was
changed. The two `rows()` bodies are byte-identical between the two patches.

One deviation from the letter of the request, in service of it: my first pass
named the two calls in the explanatory comment, which made the round-2
verification `grep` report hits on comment text. The comment now says
"mysqlnd-only prepared-statement result helpers" instead, so the grep stays a
clean gate.

## Verification

**1. Forbidden calls — gone.**

```
grep -n "get_result\|fetch_all\|mysqli_fetch_all" \
  patches/UI-FIX_price-discount-rows_20260903.php \
  patches/UI-FIX_cms-content_20260903.php
→ no matches
```

**2. Behaviour-identical to round 1.** Same fixture rebuilt from the same
2026-08-28 dump (178 tables), same row ids (1163, 1177) and same two decoys
(`quantity = 5, special = 0` and `quantity = 1, special = 1`). Dry-run output
captured to a file and diffed against the round-1 output:

```
=== price dry-run ===  IDENTICAL to round 1
=== cms dry-run ===    IDENTICAL to round 1
```

(The only raw difference was CRLF vs LF, since `PHP_EOL` is CRLF on the local
machine; compared with `diff --strip-trailing-cr`.)

Real runs match round 1 line for line:

```
deleted_rows=2                       information_rows_updated=1
base_prices_untouched=yes            layout_module_rows_removed=1
done=ok                              faq_installed=yes
```

Decoy rows 1178 and 1179 survived, `product.price` / `status` / `quantity` /
`stock_status_id` unchanged, table back to 46 rows, both patches print
`already_applied=yes` on a repeat run, and both `restore.sql` files are
identical to round 1's — same ids, same values, same quoting. Rollback replayed
clean: `layout_restored=1`, `faq_present=0`, `cards_present=0`.

The two distinct `survey=` lines in the price patch output (1163 / 115 / 4500 and
1177 / 148 / 5700) are also the direct proof that the per-iteration copy works —
without it both lines would show the same row.

**3. F1 corrupted-row test still reaches its failure through the new read
path.** With the description drifted, both `--dry-run` and the real run abort
with `information_row_unexpected_content:language_id=4`, exit 1, and write
nothing (`layout_row_intact = 1`, `faq_written = 0`).

**4. `php -l`:**

```
No syntax errors detected in patches/UI-FIX_price-discount-rows_20260903.php
No syntax errors detected in patches/UI-FIX_cms-content_20260903.php
```

## Pre-flight checker added to `scripts/`

`scripts/check-php-host-compat.php`, per the round-2 answer to the open
question. It carries **two** lists, because a syntax scan would not have caught
this round's blocker:

- **syntax** — constructs newer than PHP 8.0: `never` / `true` / `false` /
  `null` as types, `enum`, `readonly`, `final const`, first-class callable
  `f(...)`, explicit octal `0o`, intersection return types, and functions added
  in 8.1–8.4;
- **host** — calls this host cannot serve regardless of version:
  `get_result`, `mysqli_stmt_get_result`, `fetch_all`, `mysqli_fetch_all`,
  `mysqli_get_client_stats`, each with the reason and the replacement to use.

It reads tokens rather than raw text, so a name appearing in a comment or a
string is not a false hit — the failure mode I hit myself an hour earlier.
`--self-test` runs a canary containing all ten constructs plus two decoys (one
in a comment, one in a string) and fails loudly if the scanner stops detecting
any of them.

```
php scripts/check-php-host-compat.php --self-test
→ self-test: 10 findings on the canary
→ self-test: OK — every planted construct detected, and the ones inside a
  comment and a string were correctly ignored.

php scripts/check-php-host-compat.php patches/UI-FIX_*_20260903.php
→ OK — nothing newer than PHP 8.0, and no call this host cannot serve.
```

Run against the *pre-fix* `rows()` body it reports exactly this round's blocker:

```
[host] rows(): get_result() — mysqli built without mysqlnd; use result_metadata() + bind_result()
[host] rows(): fetch_all() — mysqli built without mysqlnd; use bind_result() + fetch() in a while loop
```

The checker is a static gate, not a replacement for the real one: `php -l` from
`~/public_html` on production stays the first step of the run sequence above.

Both constraints are now also recorded in project memory as
`project-production-php-80` and `project-production-no-mysqlnd`, the second one
including why the "backup directory exists therefore it ran" inference is
invalid.

Nothing was run against production.
