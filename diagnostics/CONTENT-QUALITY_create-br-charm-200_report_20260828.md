# Codex Report — CONTENT-QUALITY: BR-CHARM-200 creation

Date: 2026-08-28

## Scope

WP3 creates the single disabled `BR-CHARM-200` product from its complete approved payload, using product 126 only as the permitted structural template.

## Files touched

`patches/CONTENT-QUALITY_create-br-charm-200_20260828.php`

## Local checks

Production preflight found two `Матеріал` labels: IDs 29 and 51. The runner now selects only ID 51, the canonical 3D attribute, and aborts if it is absent; it never renames, deletes or merges either database row. `php -l` passed. Payload integrity, FAQ structure, existing attribute resolution, category existence and whole-table SEO-keyword collision are runtime guards. A matching prior creation exits `already_applied=yes`.

## Owner run and QA

```bash
php CONTENT-QUALITY_create-br-charm-200_20260828.php --dry-run
php CONTENT-QUALITY_create-br-charm-200_20260828.php
```

- [ ] Product is disabled, quantity 0, price 1.0000, categories 59 and 73.
- [ ] SEO keyword is unique and the description is formatted rather than raw HTML.

## Rollback

The generated `restore.sql` deletes only the recorded new product and its dependent rows.
