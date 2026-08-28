# Codex Report — 3D-P-CARDCONTENT: seven kit cards

Date: 2026-08-28

## Scope

WP5 creates exactly the seven disabled `FIG-*-300` kit cards. Static payload validation found seven products, 23 FAQ items total and 13 attributes per product; no attribute definition is created.

## Files touched

`patches/3D-P-CARDCONTENT_create-kit-cards-7_20260828.php`

## Local checks

Production preflight found a duplicate `Матеріал` label. The runner now resolves this one name exclusively to canonical 3D attribute ID 51 and fails if that row is absent; no attribute-definition row is created or changed. `php -l` passed. Runtime guards validate all attributes against existing definitions, category rows, SEO collisions, payload SHA-256 and the product-126 structural template. A partial pre-existing batch aborts; a complete rerun reports `already_applied=yes`.

## Owner run and QA

```bash
php 3D-P-CARDCONTENT_create-kit-cards-7_20260828.php --dry-run
php 3D-P-CARDCONTENT_create-kit-cards-7_20260828.php
```

- [ ] Seven products exist, all disabled with quantity 0 and price 1.0000.
- [ ] Each has 13 attributes, a unique SEO URL, and entity-encoded description storage.

## Rollback

`restore.sql` deletes exactly the generated rows, using IDs returned during the run.
