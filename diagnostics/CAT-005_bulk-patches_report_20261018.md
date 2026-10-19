# CAT-005 — WP1 offer + archive, WP2 Bulk cards: patch report

Date: 2026-10-19
Executor: Claude Code · model=Opus 5.5 · effort/thinking=not exposed to the session
Handoff: `handoffs/handoff_CAT-005_bulk-cards-and-offer_claude-code_20261018.md` · Audit: `diagnostics/CAT-005_bulk-audit_20261018.md`
Owner decisions applied (2026-10-19): D1 `subtract=1`; D2 new attributes `sort_order` 4 / 5; D3 schema and feed untouched, cards stay `status=0`; D4 quantity display — owner checks.

## Files

| File | Purpose |
|---|---|
| `patches/CAT-005_offer-bulk-and-archive_20261018.php` | WP1, deploy first |
| `patches/CAT-005_bulk-cards_20261018.php` | WP2, deploy second |

Both are generated from templates and the verbatim inputs, so the embedded content cannot drift from them. WP1 embeds `offer_html_20261018.html` as gzip+base64 behind a SHA-256 guard. WP2 embeds each FINAL description as a nowdoc with a SHA-256 guard over the raw FINAL bytes and over the stored (escaped) form.

## What each patch writes

**WP1** — a clone of the LEGAL-003 pattern. It changes only the archive references: ids 6/7/8 are all checked, and ids ≤ 8 are protected.
- Gate: live id=3/lang 4 description SHA-256 = `9e22347c…fb71` and title «Публічна оферта». If the gate fails, the patch writes nothing, exits 1 and keeps the file.
- New archive. In `information`: `sort_order 0`, `status 1`. In `information_description`: title and meta_title «Публічна оферта — архів 06.10.2026», empty meta fields, description = banner + the live body read at run time. Plus `information_to_store` store 0 and `seo_url` `publichna-oferta-arhiv-2026-10-06`. The patch refuses if the keyword or the title already exists.
- id=3 `description` := the 18.10 body. Title and meta fields are preserved and verified.
- Embedded-body gates: 21 × `<h2>`, the Bulk `<h3>`, clauses 2.20 / 5.6 / 6.19 / 6.25 / 13.13 / 13.14, date 18.10.2026, and each of the 4 archive `href`s exactly once, including `…arhiv-2026-10-06`.

**WP2** — INSERT-only; nothing existing is updated or deleted.
- Preconditions:
  - language 4, attribute group 7 and stock status 5 exist;
  - manufacturers 11/12 exist with exact names;
  - categories 59→64 and 60→68 exist with exact names and parents;
  - attributes 12/13/17/18/20/21/24 are in group 7 with exact names;
  - none of the 3 SKUs (as `model` or `sku`), the 3 SEO keywords or the 2 new attribute names exist.
  - Any partial state is refused.
- 2 attributes in group 7: «Кількість карток у наборі» (sort 4) and «Склад за рідкістю» (sort 5). Their ids come from `insert_id`.
- Each of the 3 products gets:
  - `model = sku = SKU`, price 250 / 250 / 150, manufacturer 11 / 12 / 11;
  - `quantity 0`, `status 0`, `subtract 1`, `stock_status_id 5`, `shipping 1`, `minimum 1`, `tax_class_id 0`, `weight_class_id 2`, `length_class_id 1`;
  - weight and dimensions 0, `image ''`, `date_available` = run date;
  - description fields escaped exactly as OpenCart admin stores them (`htmlspecialchars(ENT_COMPAT)`), `meta_keyword ''`;
  - store 0; categories {59, 64} / {60, 68} / {59, 64}; 9 attributes; 1 SEO URL;
  - no image, layout, discount or option rows.

## Deviations from the handoff

| Item | Handoff | Implemented | Why |
|---|---|---|---|
| `subtract` | 0 (Mystery Mix mirror) | 1 | owner decision D1 |
| `image` | not specified | `''` | No live product has NULL; OpenCart admin saves `''` when no image is set. |
| Re-run after deploy | `already_applied` | WP2 checks identity only: SKU→product, keyword→product, attributes in group 7. | The owner edits status, stock, photos and weight after deploy; a full-field re-check would then fail a legitimate re-run. |
| Rollback scope (WP2) | created ids | Also deletes rows the owner adds later in any existing `product_*` child table for those ids (images, discounts…). Attributes are deleted only if no product still uses them. | A rollback after photos are uploaded must not leave orphan rows. |

## Changes after review (2026-10-19)

The three non-blocking remarks were applied directly to the patch files. Card copy, offer blob, SHA constants, field values and rollback logic are unchanged: every SHA, blob and nowdoc line is identical to the previous commit.

1. **WP2 ID guards.** The hard-coded `$id <= 55` / `$id <= 213` are gone. Inside the transaction, after the `FOR UPDATE` state reads and before the first insert, the patch reads `MAX(attribute_id)` and `MAX(product_id)` and logs them as `attribute_max_before` / `product_max_before`. Every new `insert_id` must be strictly greater, otherwise it throws, rolls back, exits 1 and keeps the file.
2. **WP2 refuses to run before WP1.** A read-only precondition placed before any write: the `seo_url` row `publichna-oferta-arhiv-2026-10-06` (`key information_id`, store 0, language 4) must exist, otherwise the patch fails with «Run CAT-005_offer-bulk-and-archive_20261018.php first». The live offer hash is not compared, so later offer revisions do not block WP2. The check also runs, and passes, on an already-applied re-run.
3. **Cache note.** Both patches print `note=OpenCart caches information and product lists for about 1 hour; if the page looks stale, wait or clear the data cache` after `done=ok`, in the success and the `already_applied` branches.

## Tests (scratch MariaDB 10.11.14 seeded from the 15.10 catalog extract; PHP 8.3 runtime)

All rows were re-run on the post-review files, except the two marked ¹. Those come from the first run; the review fixes do not touch their code paths. The stored-description SHAs and `status`/`subtract`/`qty` were re-checked.

| Test | Result |
|---|---|
| `php -l` both files | no syntax errors |
| `scripts/check-php-host-compat.php` both files (PHP 8.0 syntax + no-mysqlnd calls) | OK, 0 findings |
| Embedded content | offer SHA `6ec16036…dea` and the three stored-description SHAs (`d87fd016…`, `458c6a16…`, `c584f603…`) are unchanged vs the previous commit and match the rows written in the test |
| WP2 on a DB without WP1 | exit 1, «Run CAT-005_offer-bulk-and-archive_20261018.php first», file kept, DB dump unchanged |
| WP1 fresh apply | `done=ok` + `note=…`, self-deleted. id=3 SHA = `6ec16036…dea`. Archive id 9: banner + body, i.e. the body is byte-identical to the pre-patch id=3. SEO 2371, store 0. |
| WP1 re-run | `already_applied=yes` + `note=…`, DB dump unchanged |
| WP1 precondition: id=3 body altered | exit 1, file kept, DB unchanged |
| WP1 precondition: archive keyword exists | exit 1, file kept, DB unchanged |
| WP1 `rollback.sql` | DB dump identical to pre-patch |
| WP2 fresh apply (after WP1) | `wp1_archive_route=ok`, `attribute_max_before=55`, `product_max_before=213`, `done=ok` + `note=…`, self-deleted; products 214/215/216, attributes 57/58 |
| WP2 field check, all 3 cards ¹ | `status 0`, `qty 0`, `subtract 1`, 9 attributes, 1 keyword, store 0, correct categories and manufacturer. Stored description = `htmlspecialchars(FINAL)`; decoded = FINAL byte-for-byte. Meta 54/125, 53/124, 50/128 (limits 63/155). |
| WP2 storefront attribute order (`a.sort_order, ad.name`) ¹ | Назва сету · Мова · Тип пакування · Додатковий вміст · Кількість карток у наборі · Склад за рідкістю · Стан · Походження товару · Виробник |
| WP2 re-run | `wp1_archive_route=ok`, `already_applied=yes` + `note=…`, DB dump unchanged |
| WP2 re-run after simulated owner edits (enable, qty, image + image row) | `already_applied=yes` |
| WP2 `rollback.sql` after those edits | DB dump identical to pre-WP2 |
| WP2 precondition: a product with one SKU exists | exit 1 (partial/conflicting state), file kept, DB unchanged |
| WP2 precondition: attribute 12 renamed | exit 1, file kept, DB unchanged |

Before/after dump diff (`mysqldump --skip-extended-insert`, AUTO_INCREMENT stripped):
- WP1: one changed row — `information_description` (3, 4), description only. Added rows: `information` 9, `information_description` (9, 4), `information_to_store` (9, 0), `seo_url` 2371.
- WP2: 0 changed or removed rows. Added: `attribute` 2, `attribute_description` 2, `product` 3, `product_description` 3, `product_to_store` 3, `product_to_category` 6, `product_attribute` 27, `seo_url` 3.

Not testable here:
- `/information/publichna-oferta-arhiv-2026-10-06` → 200 and the page rendering: there is no OpenCart runtime in the sandbox → owner QA.
- PHP 8.0 runtime itself: only 8.3 is available; the 8.0 compatibility gate above covers syntax and mysqli calls.

## Rollback SQL

Each patch writes `_patch_backups/<patch>-<ts>/db/rollback.sql` with the real prefix and ids before commit. Run it only after `done=ok`, with a fresh DB dump taken first. Shape, as generated in the test run:

```sql
-- WP1
START TRANSACTION;
UPDATE `ocp5_information_description` SET description=CONVERT(0x<pre-patch body hex> USING utf8mb4) WHERE information_id=3 AND language_id=4;
DELETE FROM `ocp5_seo_url` WHERE seo_url_id=<created>;
DELETE FROM `ocp5_information_to_store` WHERE information_id=<created>;
DELETE FROM `ocp5_information_description` WHERE information_id=<created>;
DELETE FROM `ocp5_information` WHERE information_id=<created>;
COMMIT;
-- WP2
START TRANSACTION;
DELETE FROM `ocp5_seo_url` WHERE `key`='product_id' AND `value` IN ('<P1>','<P2>','<P3>');
DELETE FROM `ocp5_<each existing product child table>` WHERE product_id IN (<P1>,<P2>,<P3>);
DELETE FROM `ocp5_product` WHERE product_id IN (<P1>,<P2>,<P3>);
DELETE FROM `ocp5_attribute_description` WHERE attribute_id=<A> AND NOT EXISTS (SELECT 1 FROM `ocp5_product_attribute` pa WHERE pa.attribute_id=<A>);
DELETE FROM `ocp5_attribute` WHERE attribute_id=<A> AND NOT EXISTS (SELECT 1 FROM `ocp5_product_attribute` pa WHERE pa.attribute_id=<A>);
COMMIT;
```

## Owner run order

1. Take a DB dump: cPanel → Backup → Download a MySQL Database Backup.
2. Upload both files to `~/public_html` and run them, WP1 first. Each must end with `done=ok`; on `done=failed` stop and send the output.
3. No template or CSS changed, so no theme-cache refresh is needed. OpenCart caches information and product lists in its data cache, which expires on its own (default 1 h). If the offer or a newly enabled card looks stale, wait or clear that cache. The cache path on the server is not in the audit inputs, so it is unverified.

## Owner QA after deploy

Offer (WP1):
- [ ] `/information/publichna-oferta` shows «Редакція від: 18.10.2026»; 2.20, 5.6, 6.19–6.25 under the «Особливості товарів Bulk / Bulk Mix» subheading, and 13.13–13.14 are visible; the subheading looks like a heading.
- [ ] §21 lists four archive links (06.10, 07.08, 24.07, 26.05); each opens; the 06.10 archive starts with the banner and its §20 says 06.10.2026.
- [ ] The checkout consent link still opens the offer.

Cards (WP2), after photos, weight, dimensions and stock are entered and each card is enabled:
- [ ] `/product/Pokemon-Bulk-155-JP`, `/product/One-Piece-Bulk-155-JP`, `/product/Pokemon-Bulk-Mix-100` open; H1, price, breadcrumb through «Набори» / «Набори та бокси One Piece».
- [ ] Description and FAQ accordion open and close; the characteristics table shows 9 rows including the two new ones.
- [ ] Stock line: «В наявності» (with «N шт на складі» only if Display Stock is on — D4) or «Закінчився» / «Товару немає в наявності».
- [ ] Mobile view; add to cart works (no order needed).

CRM (owner): add `Bulk` to the `Товари` type dropdown before entering the rows. Run the CRM integrity check before and after (OPS-CRMINTEGRITY).

## Side effects / open items

- `subtract=1`: each order now decrements the site quantity for these cards.
- Before enabling the cards, decide on D3. Schema `itemCondition: NewCondition` is hard-coded for every product; the Merchant feed rules are unverified. This is a separate task, by owner decision.
- Nothing else is written. Sitemap, feed, templates, CRM and existing products and attributes are untouched (the dump diff above confirms this for the DB).
