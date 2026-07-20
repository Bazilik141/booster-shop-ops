# Codex Report — CATALOG-20260712B

Date: 2026-07-12

## Scope

Extended `CATALOG-20260712_products_accessories.php` with a separate,
attribute-only follow-up for four existing EN Mega Evolution products:

- `PKM-EN-PORD-BBN` → product `103`
- `PKM-EN-PORD-BST` → product `104`
- `PKM-EN-CHRS-BBN` → product `105`
- `PKM-EN-CHRS-BST` → product `106`

The follow-up sets `manufacturer_id=11` after verifying the host row is
`The Pokémon Company`, and replaces only language-4 `product_attribute` rows
with the handoff-approved values. Product descriptions, prices, categories,
store relations, SEO URLs, and physical fields are not changed for these four.

## Safety / validation

- Attribute IDs 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, and 24 are verified by
  ID/name against `attribute_description` on the host.
- Product ID/model and price anchors are verified before any write.
- Existing non-empty, non-matching attributes cause a loud stop; they are not
  silently overwritten.
- Backup now includes product and attribute rows for both the original 8-card
  payload and product IDs 103–106.
- `rollback.sql` restores all captured product/attribute rows.
- The parent 8-card path remains unchanged.

## Tables touched

- `DB_PREFIX.product` — parent 8-card changes plus `manufacturer_id` only for
  product IDs 103–106.
- `DB_PREFIX.product_description`, `product_to_category`, `product_to_store` —
  parent 8-card path only; untouched for product IDs 103–106.
- `DB_PREFIX.product_attribute` — parent 8-card path plus language-4 rows for
  product IDs 103–106.

## Checks

- `php -l` required after update.
- Host `--dry-run` must list the four attribute updates if they are pending.
- Re-run after apply must report `already_applied=yes`.

## Run

```bash
cd ~/public_html || exit
php CATALOG-20260712_products_accessories.php --dry-run
```

After reviewing clean output, run the same patch without `--dry-run` and clear
OpenCart cache in the same shell window.
