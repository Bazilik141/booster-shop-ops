# Codex Report — PRODUCT-NEW-SKUS: add OpenCart product cards

Date: 2026-07-08

## Scope
Implemented the handoff from `codex-handoff-new-product-cards.md`: one self-contained PHP patch that creates or updates 6 OpenCart product cards by `model` using the payload from `booster-new-skus-codex-payload.json`.

The patch preserves the current live export shape:

- `model` is the SKU-like identifier.
- `sku` remains `NULL` / empty.
- `quantity = 0`, `status = 1`.
- stock availability is resolved at runtime from `stock_status.name = Передзамовлення` and `language_id = 4`.
- descriptions stay HTML-escaped (`&lt;h2&gt;...`) to match current product descriptions.
- image paths are not set unless a real image file exists.

## Files touched
```
patches/PRODUCT-NEW-SKUS_new-product-cards_20260708.php — main patch
diagnostics/PRODUCT-NEW-SKUS_new-product-cards_report_20260708.md — this report
```

## Tables touched
```
DB_PREFIX.product
DB_PREFIX.product_description
DB_PREFIX.product_to_category
DB_PREFIX.product_to_store
DB_PREFIX.seo_url
```

## Dry-run / preflight result
Local comparison used the provided live export archive `booster-product-cards-for-chatgpt.tar.gz`:

```
Current products exported: 42
Payload products: 6
Existing model conflicts: none
Existing SEO URL conflicts: none
Required category names missing from export: none
Existing SKU values: none
Null/empty SKU count: 42
Payload descriptions are HTML-escaped: yes
Newest local cPanel backup noted: backup-7.6.2026_12-14-21_boosters.tar.gz
```

The patch also supports a server-side no-write check:

```bash
php PRODUCT-NEW-SKUS_new-product-cards_20260708.php --dry-run
```

## php -l result
```
No syntax errors detected in PRODUCT-NEW-SKUS_new-product-cards_20260708.php
```

## Idempotency
Re-running the patch after the products already match the embedded payload returns:

```
already_applied=yes
done=ok
```

The patch checks exact product text, price, stock status, category relations, store relation, and SEO URL before deciding it is already applied.

## Rollback
Before any write, the patch creates:

```
_patch_backups/PRODUCT-NEW-SKUS_new-product-cards_20260708-<timestamp>/db-prechange.json
_patch_backups/PRODUCT-NEW-SKUS_new-product-cards_20260708-<timestamp>/payload.json
_patch_backups/PRODUCT-NEW-SKUS_new-product-cards_20260708-<timestamp>/rollback.sql
```

For newly created products, rollback deletes only the 6 created `product_id` rows and their direct product relations. If any target `model` existed before patch execution, generated `rollback.sql` restores the captured previous rows for that product.

## Run command (owner)
```bash
cd ~/public_html || exit
php PRODUCT-NEW-SKUS_new-product-cards_20260708.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA checklist
- [ ] Terminal output shows `done=ok`.
- [ ] Open each new product by SEO URL and confirm the page returns 200.
- [ ] Confirm product page shows `Передзамовлення`, quantity is not presented as in stock, and Add to Cart behavior is acceptable for preorder.
- [ ] Confirm the products appear in their target categories:
  - Pokémon / Набори
  - Pokémon / Бустери Pokémon
  - One Piece Card Game / Набори та бокси One Piece
  - One Piece Card Game / Бустери One Piece
- [ ] Confirm no broken product image is rendered.

## Side effects / risks
Medium risk because this is a database write to OpenCart catalog tables. Scope is limited to the 6 target `model` values and 6 target SEO keywords, with preflight checks for duplicate models, duplicate SEO URLs, category resolution, and stock status resolution.
