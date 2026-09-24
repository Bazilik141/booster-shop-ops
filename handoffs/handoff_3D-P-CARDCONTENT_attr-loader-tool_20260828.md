# Handoff — `bs-attr-load.php`, a reusable 3D-P attribute loader

Date: 2026-08-28
Author: Claude (chat)
Executor: to be assigned by the owner (Codex or Claude Code)
Roadmap: `3D-P-CARDCONTENT` (tooling; no separate Roadmap ID assigned yet)
Canon: `plans/3D-P_sku-naming-convention_20260807.md`, **ред. 8 §3** (attribute schema)

---

## 0. Why a tool instead of another patch

The owner creates 3D product cards by hand in the admin panel and does not want to
commission a bespoke PHP runner for every batch of attributes. Reviewing a new
one-off script per batch is the expensive part, not the run itself — the run is
already a single command in cPanel Terminal.

This is written **once**, reviewed **once**, and every future batch becomes a CSV
next to it. It is a tool that lives in the repo, not a patch that self-deletes.

It is also the correction mechanism for cards the owner has already created by
hand, where attributes are missing or incomplete.

## 1. What it must never do

These are not style preferences. Each one is a defect that has already happened
on this project.

1. **Never create, rename or delete an attribute definition.** It may only attach
   values to attributes that already exist in `ocp5_attribute_description`. The
   2026-08-27 SVEL batch invented **13 attribute names** that did not exist and
   nobody noticed until the live run failed; a tool that can create definitions
   would have created all 13 silently instead.
2. **Never resolve an attribute name into a single id without checking for
   duplicates.** `Матеріал` exists **twice** — `attribute_id` 29 and 51. A
   name→id dictionary silently collapses the pair. On any name matching more
   than one id the tool must abort the entire run and print both ids.
3. **Never invent a value.** No defaults, no interpolation, no "probably". If a
   value is absent from the input it is reported as a gap and skipped.
4. **Never write to a product it cannot resolve by SKU.** Attributes cannot be
   attached to a product that does not exist. Unknown SKU → abort, do not create.
5. **Never touch `name`, `meta_*`, `description`, price, quantity, status or
   `seo_url`.** Attributes only. Assert zero writes outside
   `ocp5_product_attribute`.
6. **Never self-delete.** This is a tool, not a patch. Convention C7 does not
   apply; it lives at `scripts/bs-attr-load.php` in the repo and is uploaded once.

## 2. Three modes

### `--report` (read-only, the default)

Takes no CSV. Walks every product whose SKU matches `^(BR-|FIG-|ACC-3D-)` and
compares its live attribute set against the canonical schema (§4). Writes nothing.

Output per product: SKU, product_id, name, then

- `MISSING` — a canonical attribute the product does not have
- `EMPTY` — attribute present, value blank or whitespace
- `EXTRA` — attribute present that the schema does not assign to this product type
- `WRONG_TAIL` — the 13th attribute is present but is the wrong one for this
  product type (e.g. `Сумісність` on a keychain)

Plus a summary: products at exactly 13 attributes, products with gaps, and a
deduplicated list of every distinct gap, so the owner can see in one screen what
values still have to be supplied.

**This mode runs first, before any CSV is written.** The point is to find out what
is actually missing rather than to assume.

### `--dry-run <file.csv>`

Parses the CSV, resolves every SKU and attribute name, runs all guards in §1, and
prints exactly what would be inserted or updated — including the before value on
every update. Touches nothing.

### `--apply <file.csv>`

Same as dry-run, then writes inside one transaction. Requires the owner's explicit
go-ahead per AGENTS.md C6 — being a reusable tool does not exempt a DB write.

## 3. Input format

```csv
sku,attribute,value
FIG-ZORO-410,Розміри,165×164×40 мм
FIG-ZORO-410,Колір,чорний
FIG-ZORO-410,Може трапитись у Mystery Box,Так
BR-OPSHP-100,Матеріал фурнітури,метал
```

- UTF-8, header row required, comma-separated, values may contain `×` and commas
  when quoted.
- One row per (product, attribute). A repeated pair in the same file is an error,
  not a last-wins.
- `language_id` is not a column — it is fixed at 4 and asserted at startup against
  the live `ocp5_product_description` majority language.

## 4. The canonical schema the report checks against

From canon ред. 8 §3, derived from the 19 live products and confirmed by the
`FIG-*-300` kit cards.

**Twelve mandatory, on every 3D product:**

`Країна виготовлення`, `Спосіб виготовлення`, `Розміри`, `Маса`, `Комплектація`,
`Рухомі елементи`, `Вікове позиціонування`,
`Типовий строк виготовлення при відсутності на складі`,
`Може трапитись у Mystery Box`, `Тип виробу`, `Матеріал`, `Колір`

**Exactly one thirteenth, chosen by SKU prefix and category digit:**

| Attribute | Assigned to |
|---|---|
| `Матеріал фурнітури` | `BR-` (all) |
| `Сумісність` | `ACC-3D-` with category digit 1, 2, 3 or 7 |
| `Призначення` | `FIG-` (all) and `ACC-3D-` with category digit 4, 5, 6 or 8 |

Total 13. Any other count is a data defect and is reported.

`Виробник` is **not** in the schema — it exists on exactly one product,
`FIG-CHARM-001`, a test record the owner is deleting. If the report still sees it,
report as `EXTRA`; do not propagate it.

## 5. Idempotency and rollback

- A row whose live value already equals the CSV value is counted as `unchanged`,
  not rewritten. A rerun of the same CSV prints `already_applied=yes`.
- Before the first write, dump the affected rows to
  `_patch_backups/bs-attr-load-<utc>/before.json` and `restore.sql`.
- ⚠ `restore.sql` must key rows on **`(product_id, attribute_id, language_id)`**
  and never on a surrogate row id. The WP6 promotion rollback from 2026-08-28 is
  unusable today because it pinned `product_discount_id` values that an admin
  product save renumbered. Admin saves renumber attribute rows the same way.

## 6. Environment constraints

- Production runs **PHP 8.0**. `never`, `readonly`, `enum` and other PHP 8.1-only
  syntax are parse errors on the host. `php -l` before shipping.
- Read DB credentials from the OpenCart `config.php` already present in
  `~/public_html`. Do not embed credentials in the script and do not print them.
- Table prefix must be read from config, not hardcoded, and validated against
  `^[A-Za-z0-9_]*$` before being interpolated into any query.
- The host emits `MYSQL_OPT_RECONNECT is deprecated`. Expected, not an error.

## 7. Run sequence for the first sweep

```bash
php bs-attr-load.php --report > attr-report.txt
```

Owner sends `attr-report.txt` back. Gaps that need a human decision — colour,
Mystery Box eligibility, real measured dimensions — are filled by the owner; the
CSV is assembled from the report plus the canon and Sergiy's printable sheet.
Then:

```bash
php bs-attr-load.php --dry-run bs-attr-3dp-<date>.csv
php bs-attr-load.php --apply   bs-attr-3dp-<date>.csv
```

## 8. Scope of the first sweep

**32 SKUs already live**, per the owner's admin-panel screenshots of 2026-08-28:

- `ACC-3D-PKM-110`, `-120`, `-130`, `-200`, `-300`, `-700`, `-710`
- `ACC-3D-DITTO-420`, `ACC-3D-OP-600`, `ACC-3D-PKBL-800`
- `BR-BULB-100`, `BR-CHARM-100`, `BR-CHARM-200`, `BR-MEW-100`, `BR-PIKA-100`,
  `BR-SQUIR-100`
- `FIG-CHARZ-200`, `FIG-GEOD-511`, `FIG-LUFFY-400`, `FIG-LUFFY-410`,
  `FIG-LUFFY-500`, `FIG-MEW-100`, `FIG-NAMI-200`, `FIG-ONIX-500`, `FIG-PKBL-600`
- `FIG-GENG-300`, `FIG-JIGGL-300`, `FIG-MAGIK-300`, `FIG-MEW-300`,
  `FIG-PIKA-300`, `FIG-SQUIR-300`, `FIG-UMBRE-300`

**Six not yet created** — out of scope for the loader until their cards exist:
`FIG-ZORO-410`, `FIG-LUFFY-411`, `BR-DITTO-400`, `BR-OPSHP-100`,
`ACC-3D-DITTO-430`, `ACC-3D-PKBL-400`.

Of the 32, nineteen (`product_id` 125–143) were machine-created on 2026-08-19 and
carried a full 13 at the 2026-08-28 08:47 snapshot. Seven kit cards were created
2026-08-28 with 13 each. **The five the owner created by hand** —
`FIG-CHARZ-200`, `FIG-NAMI-200`, `ACC-3D-DITTO-420`, `ACC-3D-OP-600`,
`ACC-3D-PKBL-800` — have never been inspected by any tool and are the expected
source of most gaps. `BR-CHARM-200` was created by the 2026-08-28 wave and its
attribute set has not been verified against the 13 either.

⚠ `FIG-CHARM-001` (product_id 118) is a test record the owner is deleting. The
report will list it if it still exists; do not write to it.

## 9. Expected output of `--apply`

```
products_seen=<n>
attributes_inserted=<n>
attributes_updated=<n>
attributes_unchanged=<n>
definitions_created=0
products_created=0
non_attribute_tables_written=0
done=ok
```

The last three are **assertions**, not counters. Any non-zero value means the tool
exceeded its mandate and must roll back.
