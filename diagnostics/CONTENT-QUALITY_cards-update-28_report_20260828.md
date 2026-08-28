# Codex Report — CONTENT-QUALITY: 28-card content update, round 3

Date: 2026-08-28

## Scope

WP1 only: 28 existing product descriptions and three confirmed existing attribute names. `PKM-JP-SVEL-SET` is not created here. Round 3 removes the four nonexistent capacity/storage writes; their buyer-facing facts remain in the same patch's description HTML.

## Files touched

`patches/CONTENT-QUALITY_cards-update-28_20260828.php`

## Local checks

- `php -l`: passed.
- Payload SHA-256 was regenerated for the reduced attribute contract.
- PHP 8.1-only `never`, `readonly`, `enum`, and arrow-function syntax: absent.
- Contract test: passed 3/3. It decodes the payload and proves all 28 description records are byte-identical to the canonical round-2 composition, with 52 FAQ items.
- The post-write storage assertion compares each stored description to its exact entity-encoded payload. This replaces an invalid universal `<h2>` requirement: `ACC-007-400` intentionally starts with `<h3>`.
- Runtime dry-run performs preflight only; no production database was contacted locally.

## Attribute contract

- Attribute 43: `Типовий строк виготовлення при відсутності на складі` on products 125–143 (19 rows).
- Attribute 44: `Може трапитись у Mystery Box` on products 125–129 (5 rows).
- Attribute 55: `Сумісність` on product 143 only: `PSA, BGS, SGC, слаби на магніті`.
- Product 142's existing `Сумісність` is untouched. The runner resolves only these three names and verifies their live IDs before any write.

## Idempotency and rollback

A matching rerun prints `already_applied=yes`. Before any write, the runner stores `before.json` and `restore.sql` in `_patch_backups/CONTENT-QUALITY_cards-update-28_20260828-<utc>/`; that SQL restores only the 28 description rows and targeted attribute rows captured before the write.

## Owner run and QA

```bash
php CONTENT-QUALITY_cards-update-28_20260828.php --dry-run
php CONTENT-QUALITY_cards-update-28_20260828.php
```

- [ ] Confirm dry-run prints `existing_descriptions=28`, `faq_items=52`, and all seven `faq_<SKU>` lines.
- [ ] Verify the three live Yu-Gi-Oh pages render formatted content and one FAQ opens/closes.
- [ ] Verify ACC-007-400 has four FAQ items and no visible raw HTML.
- [ ] Retain the generated rollback directory.

## Risk

Live content on three cards; descriptions are intentionally entity-encoded before write.
