# Handoff: CATALOG-20260712B — EN Mega Evolution attributes + manufacturer

## 1. Task ID
`CATALOG-20260712B_en-mega-evolution-attributes`
(Follow-up to `PRODUCT-CATALOG-20260712_products-accessories`, reviewed 2026-07-12, not yet applied.)

## 2. Context
- Owner confirmed: prices on these 4 products are already correct on live, and the product pages currently render the description fine. **Do not touch price or description.**
- Owner confirmed release year for both sets: **2026**.
- These 4 SKUs were created by `patches/PRODUCT-NEW-SKUS_new-product-cards_20260708.php` (2026-07-08). That patch set name/description/tag/meta/categories/SEO URL but never set `manufacturer_id` (left at `0`) and never touched `product_attribute` at all.
- Confirmed against live export `products_structure.json` (owner-provided, exported 2026-07-12T12:56:50Z, source: OpenCart database):

| model | product_id | price (live, correct) | manufacturer_id (live) | attributes (live) | categories (live) |
|---|---|---|---|---|---|
| PKM-EN-PORD-BBN | 103 | 2100.0000 | 0 | `[]` (empty) | 59 Pokémon, 64 Набори |
| PKM-EN-PORD-BST | 104 | 350.0000 | 0 | `[]` (empty) | 59 Pokémon, 61 Бустери Pokémon |
| PKM-EN-CHRS-BBN | 105 | 2100.0000 | 0 | `[]` (empty) | 59 Pokémon, 64 Набори |
| PKM-EN-CHRS-BST | 106 | 350.0000 | 0 | `[]` (empty) | 59 Pokémon, 61 Бустери Pokémon |

- `weight`/`length`/`width`/`height` are all `0` on all 4 products (weight_class_id=1, length_class_id=1). This is a real gap but is **out of scope** for this task — do not fill in invented physical dimensions.
- Reference manufacturer: other Pokémon products in the live export use `manufacturer_id=11` for "The Pokémon Company" (e.g. `PKM-JP-MSYM-BST`). Verify the id/name pair against `oc_manufacturer` before writing, same as the parent patch already does for its own 8 products.

## 3. Goal
For the 4 existing products above (by `product_id`, not by clone/create):
1. Set `product`.`manufacturer_id = 11` (verify name = "The Pokémon Company" on the host first).
2. Insert `product_attribute` rows for `language_id=4` — see table in §4. Currently zero rows exist for these products, so this is a pure insert, not a delete+replace.

Nothing else on these 4 products changes.

## 4. What to change

### Attribute values (language_id = 4)

Attribute IDs below reuse the same IDs already used elsewhere in this repo for booster/box products (`CATALOG-20260712_products_accessories.php` payload and `new-product-pages-draft.md`). **Codex must verify each `attribute_id` ↔ name pair against `oc_attribute_description` (language_id=4) on the host before writing** — same pattern the parent patch already uses (`bs_normalize_payload` checks id+name and fails loudly on mismatch). Do not assume the IDs below are correct without that check.

| ID | Attribute name (UA) | PKM-EN-PORD-BBN (103) | PKM-EN-PORD-BST (104) | PKM-EN-CHRS-BBN (105) | PKM-EN-CHRS-BST (106) |
|---|---|---|---|---|---|
| 12 | Мова | Англійська (English) | Англійська (English) | Англійська (English) | Англійська (English) |
| 13 | Назва сету | Perfect Order | Perfect Order | Chaos Rising | Chaos Rising |
| 14 | Рік випуску | 2026 | 2026 | 2026 | 2026 |
| 15 | Кількість карток у бустері | 10 | 10 | 10 | 10 |
| 16 | Кількість бустерів у боксі | 6 | *(omit — single booster)* | 6 | *(omit — single booster)* |
| 17 | Стан | Новий, нерозпакований (Sealed) | Новий, нерозпакований (Sealed) | Новий, нерозпакований (Sealed) | Новий, нерозпакований (Sealed) |
| 18 | Походження товару | Sealed-партія, без розсипу | Sealed-партія, без розсипу | Sealed-партія, без розсипу | Sealed-партія, без розсипу |
| 19 | Зважування | Без зважування, сортування чи ручного перевідбору | Без зважування, сортування чи ручного перевідбору | Без зважування, сортування чи ручного перевідбору | Без зважування, сортування чи ручного перевідбору |
| 20 | Виробник | The Pokémon Company | The Pokémon Company | The Pokémon Company | The Pokémon Company |
| 21 | Тип пакування | Sealed Booster Bundle | Sealed Booster Pack | Sealed Booster Bundle | Sealed Booster Pack |
| 24 | Додатковий вміст | У кожному з 6 бустерів: 1 Basic Energy + 1 Pokémon TCG Live code card | 1 Basic Energy + 1 Pokémon TCG Live code card | У кожному з 6 бустерів: 1 Basic Energy + 1 Pokémon TCG Live code card | 1 Basic Energy + 1 Pokémon TCG Live code card |

Note on ID 16: its stored label is "Кількість бустерів у боксі" even though these products are "Booster Bundle", not a box. `PKM-JP-MBX-ST`/`PKM-JP-MBX-XL` in the parent patch already reuse this same ID for "Mystery Box" packaging, so this is established site convention, not a new inconsistency — do not create a separate attribute for "бандл" wording.

### Implementation approach
The parent script's product loop (`bs_upsert_description` + `bs_replace_relations`) always rewrites `product_description` (name/description/tag/meta) and replaces category/store relations for every entry it processes. Since description/price/categories for these 4 products must NOT change, **do not add these 4 products as ordinary entries to the existing `products` payload array** — that would force a full description/category/store rewrite through the existing generic path and risks reformatting the (currently working) description HTML.

Preferred approach (verify against actual current code before implementing): add a second, narrower payload section (e.g. `attribute_updates`) with its own validation function mirroring the existing attribute/manufacturer validation logic, and a write path that touches only:
- `UPDATE product SET manufacturer_id=? WHERE product_id=?`
- `INSERT INTO product_attribute (...)` for the 4 products (no delete needed since current rows are empty, but use the same delete-then-insert pattern as `bs_replace_relations` for idempotency safety)

Either extend `patches/CATALOG-20260712_products_accessories.php` with this second section, or ship a small standalone companion patch reusing the same backup/rollback/`php -l`/`--dry-run` scaffolding. Codex should decide based on what's cleaner in the actual file — flagging as "likely area," not a fixed instruction.

## 5. Do not touch
- `description`, `name`, `tag`, `meta_title`, `meta_description`, `meta_keyword`, `seo_url` for product_id 103/104/105/106 — owner confirmed these render correctly as-is.
- `price` for these 4 products (2100 / 350 / 2100 / 350 — owner confirmed correct).
- `product_to_category` / `product_to_store` for these 4 products — already correct per live export.
- `weight` / `length` / `width` / `height` — known gap, separate task, do not invent values.
- The 8 products already defined in `CATALOG-20260712_products_accessories.php` — their behavior must not change.
- Protected zones, unrelated to this task: `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, schema/JSON-LD.

## 6. Likely files / areas
- `patches/CATALOG-20260712_products_accessories.php` (extend) — likely, not confirmed; Codex should verify current file state before editing (it may have changed since this review).
- Alternative: new companion file `patches/CATALOG-20260712B_en-mega-evolution-attributes_20260712.php`.
- Tables: `oc_product` (manufacturer_id only), `oc_product_attribute` (insert only, language_id=4).

## 7. Acceptance criteria
- `oc_product.manufacturer_id = 11` for product_id 103, 104, 105, 106.
- `oc_product_attribute` (language_id=4) contains exactly the rows in the §4 table for each product_id (11 rows for 103/105, 10 rows for 104/106).
- `oc_product_description`, `oc_product`.`price`, `oc_product_to_category`, `oc_product_to_store` for these 4 product_id are byte-identical before/after (diff against pre-change snapshot = empty for these fields).
- The 8 products in the parent patch are unaffected.
- `php -l` passes.
- `--dry-run` lists all 4 models as pending attribute/manufacturer updates.
- Re-running after a successful apply reports `already_applied=yes` (idempotent).

## 8. QA / smoke test
Not a checkout/payment/schema task — standard smoke tests (`bs-checkout-smoke`, `bs-merchant-schema-qa`) do not apply.

Manual checks for owner after apply:
- Open all 4 product pages (SEO URLs from the 2026-07-08 patch) and confirm the description still renders exactly as before (no visible change).
- Confirm the attribute table on each page now shows the 10–11 values from §4.
- Confirm displayed price is unchanged (2100 / 350 / 2100 / 350 грн).
- Confirm manufacturer/brand field (if shown in theme) displays "The Pokémon Company".

## 9. Rollback note
Reuse the parent patch's backup pattern: snapshot `oc_product` and `oc_product_attribute` rows for product_id 103/104/105/106 into `_patch_backups/<patch>-<timestamp>/db-prechange.json` before write, and generate `rollback.sql` that restores `manufacturer_id=0` and deletes the inserted `product_attribute` rows for these 4 products (matching pre-patch empty state).

## 10. Recommended status after execution
`На перевірці` — needs owner manual QA (per §8) and Claude post-Codex review before `Готово`. Do not mark `Готово` on Codex's self-report alone.
