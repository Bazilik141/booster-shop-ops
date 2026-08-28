# Codex Report — CRM-RRP: visible site-price reconciliation, round 2

Date: 2026-08-28

## Scope

WP6 embeds the unchanged 95-row CRM RRP map, applies it only to products currently `status = 1`, skips the permanent Outlet promotion, and recalculates the target set from production at runtime. It does not create products or write descriptions, inventory/status fields, or unnamed special rows.

Round 2 fetches all active discount rows using the storefront model's time window. Named disable rules match their IDs across all active rows; the generic inversion guard considers only `special = 1` and `quantity = 1`. A named row outside its active window logs `special_already_disabled:<SKU>` and still permits the base-price update.

## Files touched

`patches/CRM-RRP_site-price-reconcile_20260828.php`

## Local checks

`php -l` passed. Static build verified 95 CRM RRP entries and four unchanged named special rules. A unit-style contract test proves: a quantity-1 displayed special skips the SKU once without queueing an update; a bulk tier does not trigger the guard; an inactive named row logs `special_already_disabled`; and the >24 update ceiling is present. The runner has SHA-256 integrity checking, `--dry-run`, `already_applied=yes`, transaction rollback and `before.json`/`restore.sql` written before changes. No live database query was run locally.

## Owner run and QA

```bash
php CRM-RRP_site-price-reconcile_20260828.php --dry-run
php CRM-RRP_site-price-reconcile_20260828.php
```

- [ ] Review every dry-run `plan` and `skip` line before applying.
- [ ] Dry run reports `visible_products=66`, then review the reported plan and disable counts before any apply.
- [ ] Confirm the four named pages show one price after the special expiry.
- [ ] Spot-check OP-JP-OP15-BBX = 5400, PKM-JP-ABYE-BBX = 4900, and Outlet still 90 crossed-out / 80 current.

## Risk

This changes buyer-visible prices. The patch aborts if it queues more than 24 rows, and skips any unlisted active storefront special whose price is at or above the proposed base price.
