# Codex Report — LEGAL-002 v3: mysqli without mysqlnd

Date: 2026-07-24

## Scope

Follow-up only for v2 safe stop: the host PHP build has no `mysqlnd`, so `mysqli_stmt::get_result()` is unavailable. V3 replaces result reads with metadata + `bind_result()`. It makes no other logical or schema change.

## Files touched

```
patches/LEGAL-002_offer_mono_pumb_archive_v3_20260724.php
diagnostics/LEGAL-002_offer_mono_pumb_archive_v3_report_20260724.md
```

## Dry-run result

V2 stopped after `seo_route_table=ocp5_seo_url` and before `$db->begin_transaction()`, therefore made no DB changes. V3 contains no `get_result()` call and retains the same runtime table/schema validation, backups, SHA-256 verification and DB transaction.

## php -l result

```
No syntax errors detected in LEGAL-002_offer_mono_pumb_archive_v3_20260724.php
```

## Idempotency

After a successful run, a repeated run returns `already_applied=yes` after document-hash and route checks.

## Rollback

Before the transaction, the patch writes JSON snapshots under `_patch_backups/LEGAL-002_offer_mono_pumb_archive_v3_20260724-<timestamp>/db/`. Any error inside the transaction rolls back all DB writes.

## Run command (owner)

```bash
cd ~/public_html || exit
php LEGAL-002_offer_mono_pumb_archive_v3_20260724.php
```

## Post-deploy QA checklist

- [ ] Output ends with `done=ok`.
- [ ] Both offer URLs return 200 and cross-link correctly.
- [ ] Archive is not added to menu/footer.

## Side effects / risks

Same approved DB scope as v1/v2. No completed DB writes occurred in either failed run.
