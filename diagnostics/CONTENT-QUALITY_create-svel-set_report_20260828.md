# Codex Report — CONTENT-QUALITY: PKM-JP-SVEL-SET creation, round 3

Date: 2026-08-28

## Scope

WP4 creates the decided, disabled starter set independently from WP1. Its payload uses price 650.0000, weight 400 g and dimensions 220 × 160 × 60 mm; product 146 supplies classes and sibling structural fields only. Round 3 replaces thirteen nonexistent attribute names with eight confirmed existing sealed-catalogue attributes.

## Files touched

`patches/CONTENT-QUALITY_create-svel-set_20260828.php`

## Local checks

`php -l` passed. The runner checks SHA-256, FAQ structure, SEO uniqueness across the table, attributes, categories and the sibling anchor. The round-3 contract test passed 3/3; it verifies exactly these eight payload attributes: IDs 12, 13, 14, 17, 20, 21, 24 and 49. It has `--dry-run`, rollback SQL, self-delete on success and an `already_applied=yes` path.

Before its first write, the runner resolves every payload name and fails closed if any ID has drifted. After insertion, it requires exactly eight attribute rows and verifies every ID/text value. It does not create attribute definitions.

## Owner run and QA

```bash
php CONTENT-QUALITY_create-svel-set_20260828.php --dry-run
php CONTENT-QUALITY_create-svel-set_20260828.php
```

- [ ] Confirm dry-run prints `create_sku=PKM-JP-SVEL-SET` without `attribute_missing`.
- [ ] Product is disabled and appears in categories 59 and 64.
- [ ] Data tab shows 400 g and 220 × 160 × 60 mm.
- [ ] SEO keyword is `Pokemon-Starter-Set-Terastal-Loudbone-ex`.

## Risk

The physical values are owner-decided inputs and are not copied from the sibling.
