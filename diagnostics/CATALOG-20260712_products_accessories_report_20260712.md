# Codex Report — PRODUCT-CATALOG-20260712

Date: 2026-07-12

## Scope

Prepared one self-contained OpenCart PHP patch for 8 cards from
`booster_shop_product_cards_full_v2_2026-07-12.json`:

- 5 Mystery / product cards;
- 3 accessories: `ACC-007-360`, `ACC-008`, `ACC-009`.

The patch forces `quantity=0` for every target and resolves the language-4 stock
status named `Закінчився`. Existing images are preserved; newly created products
receive an empty image value for owner-managed image insertion.

## Tables touched

- `DB_PREFIX.product`
- `DB_PREFIX.product_description` for `language_id=4`
- `DB_PREFIX.product_to_category`
- `DB_PREFIX.product_to_store`
- `DB_PREFIX.product_attribute` for `language_id=4`

`seo_url` and image files are intentionally not changed.

## Physical fields

For newly created cards, the patch clones the declared source product row at
runtime, including weight classes and weight/dimensions, then replaces only the
product-specific fields. Quantity, stock status, product status, price, model,
manufacturer, description, categories, and attributes are overwritten by the
payload. This avoids inventing measurements for generic accessories and custom
Mystery packaging.

## Validation

- JSON payload: 8 unique models.
- Descriptions: raw HTML, FAQ blocks present, no script/iframe/event-handler
  content detected, FAQ IDs unique.
- Categories and manufacturers are checked by both ID and name on the host.
- Attribute IDs are checked by ID and language-4 name on the host.
- Clone sources are checked by product ID and expected model before any write.
- PHP syntax: `php -l` passed locally.
- Live DB dry-run: not available locally; owner must run the patch with
  `--dry-run` on the host before applying.

## Backup and rollback

Before the transaction the patch writes:

`_patch_backups/PRODUCT-CATALOG-20260712_products-accessories-<timestamp>/`

with `db-prechange.json`, `payload.json`, and `rollback.sql`.

## Run command

```bash
cd ~/public_html || exit
php CATALOG-20260712_products_accessories.php --dry-run
```

If the dry-run is clean:

```bash
cd ~/public_html || exit
php CATALOG-20260712_products_accessories.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA

- Confirm terminal output contains `done=ok`.
- Open all 8 cards and verify names, prices, descriptions, attributes, and
  `Закінчився` status.
- Add the owner-supplied images to the new cards.
- Confirm the three accessory cards appear under category 70.
- Confirm the two Standard Mystery cards no longer carry the old mixed-product
  model names.
