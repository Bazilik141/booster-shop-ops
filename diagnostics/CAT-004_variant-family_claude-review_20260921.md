# Claude Review — CAT-004 variant family patch

Date: 2026-09-21 · Reviewer: Claude (chat)
Patch: `patches/CAT-004_variant-family_20260919.php`
Executor report: `diagnostics/CAT-004_variant-family_report_20260919.md`
Handoff: `handoffs/handoff_CAT-004_variant-family-extension_20260919.md`

**Verdict: approved to run.** Independently reproduced, not accepted on the
report's word. Nothing was deployed, committed or uploaded.

## 1 · How this was re-verified

Independent run in the cloud container, not a re-read of the report.

- Target tree assembled from the owner's `booster-debug-CAT-004.tar.gz` catalog
  export plus the two admin files from the repository root (`product.php` =
  `Opencart\Admin\Controller\Catalog\Product`, `product_form.twig`).
- **MariaDB 10.11.14** — the production major version, not MySQL 8.4 as in the
  executor's own run. `ocp5_product`, `ocp5_product_description`,
  `ocp5_product_discount`, `ocp5_product_to_store`, `ocp5_stock_status` and
  `ocp5_setting` created from the CREATE TABLE statements in the owner's
  `boosters_ocart49.sql.gz`, with the three live stock statuses
  (5 `Закінчився`, 7 `В наявності`, 8 `Передзамовлення`) plus a synthetic
  `Pre-Order` to exercise the English branch.
- The **patched catalog model class was loaded for real** (namespaced, with a
  stub `Engine\Model`/`Registry`) and its SQL executed against that database;
  the patched controller block was extracted verbatim from the written file and
  executed with a registry-backed `$this`. `set_error_handler` on `E_ALL`
  turned every notice, warning and deprecation into a visible failure.
- The patched **admin model** was executed the same way for upsert, clip,
  delete and autocomplete.

### Anchors

All twelve anchors occur exactly once in the live files, checked before the run:
`// Customer Group`, `autocomplete(): void`, the save tail, the delete loop, the
`#tab-attribute` nav item, the `{% if not master_id %}` option pane, the
`// Category` JS block, the `Get Products` docblock, `// Subscriptions`, the
`<div id="product">` / `<form id="form-product">` pair, `/* /UI-FIX-20260903-TILES */`
and the stylesheet href prefix.

### Bounded diff

`+57/-0`, `+56/-0`, `+35/-0`, `+82/-0`, `+33/-0`, `+38/-0`, `+1/-1` — identical
to the executor's table. The only removed line in the whole patch is the header
cache-bust.

### Carried-across blocks

Byte-identical to the **deployed 2026-09-18 rework**, not the 2026-09-16
original: Twig 2 260 bytes, CSS 3 517 bytes, matched by exact string equality
against that patch's heredocs. The data contract the Twig reads
(`group.name`, `group.show_price`, `value.label|href|state|is_current|price|thumb`)
is fully supplied by the new controller block.

### Behaviour — every case run against the real database

| case | result |
|---|---|
| no family row | `variant_groups` empty |
| key on one product only | empty |
| three members, equal price | 3 chips, `show_price` false, all prices null |
| one member priced differently | `show_price` true, all three priced |
| member `quantity 0` + status 5 `Закінчився` | `state='out'`, `href` kept |
| member `quantity 0` + status 8 `Передзамовлення` | `state=''`, ordinary chip, price counted |
| current page = the out-of-stock member | `is_current` on it, `state='out'` |
| member with empty `value_label` | absent, others intact |
| equal `sort_order` | ordered by `product_id` |
| family key `O'NIX"X\` | stored and matched verbatim, no SQL error |
| disabled member / future `date_available` | dropped by the standard visibility rule |
| member with a `special` | special wins over price in the comparison |
| `config_customer_price` on, guest | no prices, `show_price` false |

Zero PHP notices, warnings or deprecations in any case. Four queries for a
three-member family with two distinct out-of-stock statuses (membership +
members + one per status).

Admin model: upsert leaves exactly one row per product; over-long free text
clipped to 64 **characters** (correct for `varchar(64)` in utf8mb4, Cyrillic
included); empty key deletes the row; `getFamilyKeys()` returns distinct keys,
honours a prefix filter and survives a quote. A `%` typed into the
autocomplete acts as a LIKE wildcard — harmless, no injection.

### Refusals and recovery

| situation | result |
|---|---|
| superseded selector patch still applied | `previous_patch_still_applied`, exit 1, no write, no backup dir |
| one marker missing | `partial_marker_state`, exit 1, files bit-identical, no backup dir |
| second run | `already_applied=yes`, exit 0, **table not re-created, rows untouched** |
| forced `php -l` failure | all 7 files restored and read back `ok`, created file `removed`, `restore=ok` — verified by md5 |

### Production order

Applied `CAT-004-SD-7` first, then this patch, on a clean export — the live
sequence. Result: `header_cache_bust_from=cat004-sd7-20260918` (exactly the
token production carries after the selector rollback), SD-7 CSS block intact,
`thumb.php` and `thumb.twig` byte-identical, zero removed lines in the
stylesheet.

### PHP 8.0

Production PHP 8.0 could not be linted here either (container has 8.4 only).
Scanned every added line and the new file for 8.1+ syntax — `readonly`, `enum`,
`never`, first-class callable syntax, `str_contains`/`str_starts_with`,
attributes, `??=`: none present. `private const` is 7.1+. The executor's claim
holds.

### Predicate fidelity

The pre-order predicate is a line-for-line mirror of
`thumb.php` → `BoosterShop RD-04f state normalization 20260601`, including the
`Передзамов` byte-comparison fallback for a non-mbstring host, the
`0000-00-00` guard and the `substr(..., 0, 10)` date cut. Only the predicate;
no `bs_eta` formatting. Correct.

## 2 · What the owner must act on

### 2.1 · The report's run sequence is stale — do not restore anything again

Report §1 and §5 tell the owner to roll the selector patch back before running
this one. **That rollback already happened on 2026-09-19** and was verified
(token back to `cat004-sd7-20260918`, `bs-variant__chip` count 0,
`bs-badge--rare` count 1). Restoring that backup a second time would now undo
whatever has landed since. The patch's own `previous_patch_still_applied` guard
already covers the case where it did not happen — it refuses before writing.

### 2.2 · One real question: did `UI-PCARD-D` survive that rollback?

`patches/UI-PCARD-D_no-box-card_20260919.php` rewrites `.bs-pcard*` rules **in
place** inside `boostershop-ds.css` and rewrites the header token to
`ui-pcard-d2-20260919`. The post-rollback verification found the token at
`cat004-sd7-20260918`, which means either UI-PCARD-D was never applied, or it
was applied before the rollback and the rollback silently reverted it.

One check on production settles it:

```bash
grep -c "UI-PCARD-D" catalog/view/stylesheet/boostershop-ds.css
```

Non-zero — nothing to do. Zero, and the owner expected that work to be live —
re-upload and re-run `UI-PCARD-D_no-box-card_20260919.php` (it self-deleted
after its own run). Order against this patch does not matter: both read the
cache-bust token by path prefix instead of anchoring on its value.

### 2.3 · Owner pre-step still owed

The TEST SKUs still carry `master_id` links and product options from the
abandoned OpenCart variant experiment. This patch neither reads nor breaks on
them, but those products still inherit fields from their master, which is the
behaviour the model was abandoned for. Strip them before building a real
family.

## 3 · Known and accepted — not defects

- `axis_label` is stored per product, and the row heading uses **the currently
  open product's** value. Two members with different axis labels give a heading
  that changes depending on which page you are standing on. A data error, not a
  code error; the QA checklist does not cover it.
- Nothing stops two members carrying the same `value_label` — two identical
  chips. Same class of data error.
- The future-`date_available` branch of the pre-order predicate is unreachable
  from this data source, because the model's `date_available <= NOW()` filter
  removes such a product from the family first. The executor states this. It is
  the standard catalogue rule: such a product cannot render its own page either.
- `copyProduct` is untouched, so a copied product starts with no family. This
  is the safe direction — a copy silently joining its source's family would be
  worse.
- Opening the admin "add variant" form (`&master_id=N`) prefills the master's
  family row, because core sets `$product_id = master_id` on that path. Dead
  end only for the abandoned variant feature.
- The new table does not carry `ROW_FORMAT=DYNAMIC`, which `ocp5_product` has.
  Engine and collation are copied; row format is not. No functional effect at
  these column widths.

## 4 · Run

Nothing to restore first. Upload to `~/public_html` and:

```bash
php CAT-004_variant-family_20260919.php
```

Expect `db_table_state=created`, `header_cache_bust_from=<whatever is live>`,
`done=ok`, `self_delete=ok`. On any run that does not end `done=ok` +
`self_delete=ok`, delete the runner from `public_html` by hand first — it is
publicly executable by URL until it self-deletes.

Rollback: restore the seven files from the printed
`_patch_backups/CAT-004_variant-family_<ts>/`, delete
`adminEvhenii/model/catalog/variant_family.php`, clear the cache. The table may
stay; `DROP TABLE IF EXISTS \`ocp5_product_variant_family\`;` removes it.

Then run the owner QA list in the executor report §6.
