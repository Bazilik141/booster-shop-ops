# Codex Report — LEGAL-002 v2: archive information schema compatibility

Date: 2026-07-24

## Scope

Follow-up only for the safe server stop of v1: `ocp5_information.bottom` is absent. V2 inserts only the supported optional `bottom`, `sort_order`, and `status` columns, while retaining the same embedded HTML, SHA-256 checks, transaction, backups, and SEO-route checks. No other scope changes.

## Files touched

```
patches/LEGAL-002_offer_mono_pumb_archive_v2_20260724.php
diagnostics/LEGAL-002_offer_mono_pumb_archive_v2_report_20260724.md
```

## Dry-run result

V1 stopped before opening a DB transaction with `Unexpected schema: ocp5_information.bottom is missing`; no offer or archive data was written. V2 dynamically uses only columns that exist in `information` and optional metadata columns that exist in `information_description`.

## php -l result

Pending local check after v2 generation.

## Idempotency

Repeat success returns `already_applied=yes` after checking both document SHA-256 values and the archive route.

## Rollback

V2 keeps transaction rollback and JSON backups in `_patch_backups/LEGAL-002_offer_mono_pumb_archive_v2_20260724-<timestamp>/db/`.

## Run command (owner)

```bash
cd ~/public_html || exit
php LEGAL-002_offer_mono_pumb_archive_v2_20260724.php
```

## Post-deploy QA checklist

- [ ] `done=ok` and `created_archive_information_id` appear.
- [ ] Both offer URLs return 200 and cross-link correctly.
- [ ] Archive is absent from menu/footer.

## Side effects / risks

Same approved DB scope as v1. The failed v1 backup directory may remain empty; it contains no DB changes.
