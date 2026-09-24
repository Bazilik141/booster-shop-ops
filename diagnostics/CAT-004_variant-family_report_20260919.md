# Claude Code Report — CAT-004: variant families, own table + own admin tab

Date: 2026-09-19 · Patch: `patches/CAT-004_variant-family_20260919.php`
Handoff: `handoffs/handoff_CAT-004_variant-family-extension_20260919.md`
Canon: `AGENTS.md` → "Variant products — canonical rules" (2026-09-19)

**Not deployed. Nothing committed, pushed or uploaded.**

## 1 · Read this before you run anything

Two things in the handoff's §5 sequence need a decision first.

**The admin folder on this installation is `adminEvhenii`, not `admin`.** Verified against the
owner's 2026-09-07 cPanel backup: `public_html` contains `adminEvhenii/`, `catalog/`, `system/`,
`extension/` and the backup directories — there is no `public_html/admin`. The handoff's line
references all match that tree exactly (tab strip at `product_form.twig:24-36`, `#tab-links` at
`:477`, the attribute block at `product.php:938-955`, `$json['product_id']` at `:1274`/`:1277`,
the delete loop at `:1323-1325`), so the handoff was written against these files and only the
directory name was generic. The patch targets `adminEvhenii/…`. Nothing else changes.

**Rolling back the selector patch also rolls back `UI-PCARD-D_no-box-card_20260919.php`, if that
one is live.** `UI-PCARD-D` (dated today) rewrites `.bs-pcard*` rules **in place** inside
`catalog/view/stylesheet/boostershop-ds.css` and rewrites the header cache-bust token — both files
are inside the selector patch's backup set, so restoring that backup silently reverts it. Check
before restoring:

```bash
grep -c "UI-PCARD-D" catalog/view/stylesheet/boostershop-ds.css
```

`0` — nothing to worry about, follow §5 as written. `1` or more — restore the five files as
planned, then **re-upload and re-run `UI-PCARD-D_no-box-card_20260919.php`** (it self-deleted after
its own run). Order against this patch does not matter: both read the cache-bust token instead of
anchoring on its value, and whichever runs last owns it.

**The Rare Pack badge does survive the rollback**, for a reason that is checkable rather than
assumed: the selector patch's file list is the five catalog files only, so `thumb.twig` (the badge
markup) and `thumb.php` (the `bs_is_rare` flag) are outside its backup and cannot be touched by
restoring it. Its CSS block was appended after the same terminal marker `SD-7` used, and `SD-7` was
already live when the selector patch ran — which is why that patch reported
`header_cache_bust_from=cat004-sd7-20260918`. After restoring, one grep confirms it:

```bash
grep -c "CAT-004 SD-7 Rare Pack listing badge" catalog/view/stylesheet/boostershop-ds.css
```

If that returns `0`, stop and say so — do not run this patch on top.

## 2 · What the patch does

| Part | File | Change |
|---|---|---|
| table | — | `CREATE TABLE IF NOT EXISTS <prefix>product_variant_family` |
| admin model | `adminEvhenii/model/catalog/variant_family.php` | **new file**, 110 lines: get / set / delete / distinct-key list |
| admin controller | `adminEvhenii/controller/catalog/product.php` | `form()` loads the row · `save()` writes or deletes it · `delete()` removes it · new `familyKey()` autocomplete endpoint |
| admin form | `adminEvhenii/view/template/catalog/product_form.twig` | tab «Родина варіантів» after `#tab-attribute`, four fields, autocomplete JS |
| catalog model | `catalog/model/catalog/product.php` | `getVariantFamilyMembership()` + `getVariantFamilyMembers()` |
| catalog controller | `catalog/controller/product/product.php` | `variant_groups`, one group, one axis |
| catalog template | `catalog/view/template/product/product.twig` | selector block, carried across |
| stylesheet | `catalog/view/stylesheet/boostershop-ds.css` | selector CSS, carried across |
| header | `catalog/view/template/common/header.twig` | cache-bust → `cat004-family-20260919` |

Four decisions worth naming, because they are mine and not in the handoff:

- **Method names differ from the superseded patch** (`getVariantFamilyMembers`, not
  `getVariantFamily`). A partial rollback that left the old method behind would otherwise be a
  fatal redeclare. The runner refuses to start in that state anyway (§3), so this is the second
  of two independent guards.
- **`save()` keys on the field being present in the POST**, not on its value. An emptied field
  deletes the row, as the handoff requires; a caller that never sends the field cannot silently
  wipe an existing family.
- **Free text is clipped to the 64-character column width in the model.** All four fields are free
  text from the admin and a strict-mode server rejects an over-long value outright. The form
  inputs also carry `maxlength="64"`.
- **The table is created before any file is written.** A storefront whose model queries a
  missing table errors on every product page, so the schema leads; if the create fails, no file
  has been touched.

## 3 · How this was verified

Not inspection. The owner's catalog export `booster-debug-CAT-004.tar.gz` and the two admin files
extracted from the 2026-09-07 cPanel backup were assembled into a run target and a reference copy.
A throwaway MySQL server was started with a fixture database carrying the live `ocp5_product`
table options, twelve products, the three stock statuses and the discount table. The patch was run
against the tree; the patched admin model, the patched catalog model and the patched admin
`save()` / `delete()` blocks — all extracted verbatim from the written files — were then executed
against that real database, and the carried Twig block was rendered with Twig 3 over the
controller's real output.

Two environment differences from production, both test-side only: the fixture server is MySQL
8.4 where production is **MariaDB 10.11.19**, so the session `sql_mode` had to be relaxed for
OpenCart's own `'0000-00-00'` discount-window literals (core code, unchanged by this patch, which
MariaDB accepts as-is); and local PHP is 8.3.30, so **production PHP 8.0 could not be linted**.
The written code was scanned instead: no `enum`, `readonly`, `never`, first-class callables,
`str_contains` or other 8.1+ constructs anywhere in the eight files.

### Run

```
db_product_engine=InnoDB
db_product_collation=utf8mb4_unicode_ci
db_table=ocp5_product_variant_family
db_table_state=created
header_cache_bust_from=uifix-tiles-20260904
header_cache_bust_to=cat004-family-20260919
created=adminEvhenii/model/catalog/variant_family.php
changed=adminEvhenii/controller/catalog/product.php
changed=adminEvhenii/view/template/catalog/product_form.twig
changed=catalog/model/catalog/product.php
changed=catalog/controller/product/product.php
changed=catalog/view/template/product/product.twig
changed=catalog/view/stylesheet/boostershop-ds.css
changed=catalog/view/template/common/header.twig
php_l:… =ok  (all four PHP files)
done=ok
self_delete=ok
```

Engine and collation are read from the live `<prefix>product` table and used verbatim. The table
MySQL actually created:

```sql
CREATE TABLE `ocp5_product_variant_family` (
  `product_id` int NOT NULL,
  `family_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `axis_label` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `value_label` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`product_id`),
  KEY `family_key` (`family_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

### Refusals and recovery

| situation | result |
|---|---|
| superseded selector patch still applied | `ERROR=previous_patch_still_applied:restore_CAT-004_variant-selector_backup_first`, exit 1, no write, no backup directory |
| one marker present, others absent | `ERROR=partial_marker_state`, exit 1, files bit-identical, no backup directory |
| second run | `already_applied=yes`, exit 1→0, files identical, **table not re-created and its rows untouched** — the marker check returns before the database is opened at all |
| post-write `php -l` failure (forced) | all seven files restored and read back `ok`, the new admin model file `removed`, `restore=ok` |

The failed-run recovery was checked by MD5 on all seven files plus the absence of the created file.

### Bounded diff

| File | + | − |
|---|---|---|
| `adminEvhenii/controller/catalog/product.php` | 57 | 0 |
| `adminEvhenii/view/template/catalog/product_form.twig` | 56 | 0 |
| `catalog/model/catalog/product.php` | 35 | 0 |
| `catalog/controller/product/product.php` | 82 | 0 |
| `catalog/view/template/product/product.twig` | 33 | 0 |
| `catalog/view/stylesheet/boostershop-ds.css` | 38 | 0 |
| `catalog/view/template/common/header.twig` | 1 | 1 |
| `adminEvhenii/model/catalog/variant_family.php` | new, 110 | — |

Byte-identical before/after, by MD5 of the extracted region: all five JSON-LD blocks in the catalog
`product.twig`; that file before `<div id="product">` and from `<form id="form-product">` to EOF;
the catalog controller before the insert and from `// Subscriptions` to EOF; the catalog model
before `getProduct()` and from the `Get Products` docblock to EOF. `copy()` in the admin
controller is byte-identical, so a copied product starts with no family. The three admin inserts
landed inside `form()`, inside `save()`'s `if (!$json)` branch and inside `delete()`'s loop;
`familyKey()` sits immediately before `autocomplete()`.

The carried-across blocks are byte-identical to the 2026-09-18 versions — 2 260 bytes of Twig and
3 517 bytes of CSS, spliced from `CAT-004_variant-selector_20260916.php` at build time rather than
retyped, and each occurring exactly once in the patched files. Signature scan over every added
line and the new file: no `!important`, no `setTimeout`, no `position: absolute/fixed`. Twig syntax
parses clean on both templates, before and after.

## 4 · Acceptance criteria (handoff §8)

| case | expected | result |
|---|---|---|
| product with no family row | `variant_groups` empty | pass |
| family key set on one product only | `variant_groups` empty | pass |
| three members, in stock, equal price | three chips in declared sort order, `show_price` false | pass |
| one member priced differently | `show_price` true, all three priced | pass |
| member out of stock, no pre-order signal | `state='out'`, still `<a>` with `href` | pass |
| member quantity 0 with a pre-order stock status | `state=''`, ordinary chip, price counted | pass — «Передзамовлення» and `Pre-Order` |
| the out-of-stock member's own page | chip is `is-active is-off`, `aria-current="page"` | pass |
| a row with an empty `value_label` | that member absent, others intact | pass |
| equal chip sort values | deterministic order by `product_id` | pass |
| family key containing a quote | safe in admin and catalog | pass — stored and matched verbatim for `O'NIX"X\` |
| admin: empty family key on a product that had one | the row is deleted | pass |
| admin: delete a product | its row is gone | pass |
| admin: copy a product | the copy has no row | pass — `copy()` untouched |
| second run of the patch | `already_applied=yes`, table not recreated, no data touched | pass |

No PHP notice, warning or deprecation was raised in any case; the harness converts every one into
a visible failure and all runs came back clean.

Beyond the table: a disabled member and a future-dated member both drop out of the family by the
standard OpenCart visibility rule, the same rule that stops them rendering their own pages; the
admin model clips over-long free text to 64 characters; `setFamily()` is an upsert, so re-saving a
product leaves exactly one row; and the autocomplete returns distinct keys and honours a prefix
filter.

One consequence of reusing `getProduct()`'s visibility rule is worth knowing before you build a
pre-order family: a product whose `date_available` is in the future is excluded from its family
entirely, so it shows no chip and appears in no sibling's row until that date passes. That is the
existing catalogue rule, not a decision of this patch — such a product cannot render its own page
either. A pre-order member that is already on sale carries `quantity <= 0` with the
«Передзамовлення» stock status and a past `date_available`, and that one behaves as an ordinary
chip, which is the case the acceptance table covers.

## 5 · Run and rollback

Restore the superseded patch first (handoff §5, with §1 above), then upload and run:

```bash
php CAT-004_variant-family_20260919.php
```

Rollback: restore the seven files from the `_patch_backups/CAT-004_variant-family_<ts>/` directory
the run prints, delete `adminEvhenii/model/catalog/variant_family.php`, clear the OpenCart cache.
The table may stay — members are ordinary products either way. To remove it as well:

```sql
DROP TABLE IF EXISTS `ocp5_product_variant_family`;
```

On any run that does not end `done=ok` + `self_delete=ok`, the runner stays in `public_html` and is
publicly executable by URL. Delete it manually before doing anything else.

## 6 · Owner QA

Handoff §9, plus the two checks from §1.

- [ ] Before restoring: `grep -c "UI-PCARD-D" catalog/view/stylesheet/boostershop-ds.css`
- [ ] After restoring: `grep -c "CAT-004 SD-7 Rare Pack listing badge" catalog/view/stylesheet/boostershop-ds.css` returns a non-zero count
- [ ] Open any product → the tab «Родина варіантів» exists and is empty
- [ ] Fill a three-member family. After the first member is saved, the key field offers that key
- [ ] Open one member's page and **count the chips** — the row must show every member you entered.
      A missing chip means a mistyped key or an empty value label on that product. This is the one
      failure mode of the whole model and it is silent
- [ ] Sold-out member: grey, struck through, still clicks through to its page
- [ ] On that member's own page its chip is blue-framed, at rest and under the cursor
- [ ] Add to cart from one member — the order carries that member
- [ ] 390 px: chips wrap, target height ≥ 44 px, no horizontal scroll
- [ ] An ordinary product with no family row looks exactly as before, and the Rare Pack badge is
      still on the listing

## 7 · Status

`CAT-004` stays `In progress` until §6 is done. Notion is written by Claude (chat); no
`ROADMAP_FLOW` change is required by this implementation and none was made.
