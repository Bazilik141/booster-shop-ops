# Codex Report — CONTENT-QUALITY WP3 preflight diagnostic

Date: 2026-08-28

## Trigger

Production `--dry-run` returned `ERROR=expected_one_row_got_2` before any write.

## Scope

The runner is read-only. It reports bounded matching rows for the exact WP3 singleton preconditions: product model/SKU, SEO keyword, product-126 template, categories, every required attribute name, and attributes 43/44.

## File

`patches/CONTENT-QUALITY_wp3-preflight-diagnostic_20260828.php`

## Result expected

Every `*_count` must be `1`, except `categories_count`, which must be `2`, and `product_sku_count` / `seo_keyword_count`, which may be `0` before creation. A count of `2` identifies the precise duplicate that blocks WP3.

## Safety

No INSERT, UPDATE, DELETE, transaction, backup or cache operation. It self-deletes only after a completed report.
