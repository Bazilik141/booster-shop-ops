# Codex Report — LEGAL-002 v4: archive store publication recovery

Date: 2026-07-24

## Scope

Recovery based on live diagnostic facts: `ocp5_information` has only `information_id`, `sort_order`, and `status`; `ocp5_information_to_store` exists. The archive can return 404 when its information row lacks the store `0` mapping. V4 creates or repairs only the archive record, its default-store mapping, and its existing route; it also preserves the previously approved current-offer update.

## Files touched

```
patches/LEGAL-002_offer_mono_pumb_archive_v4_20260724.php
diagnostics/LEGAL-002_offer_mono_pumb_archive_v4_report_20260724.md
```

## Dry-run result

V1 failed before transaction due to absent `bottom`; V2 failed before transaction due to `get_result()`; diagnostic confirmed a PHP mysqli build that also lacks `fetch_all()`. V4 contains neither `get_result()` nor `fetch_all()` and reads results only through `result_metadata()` + `bind_result()`.

## php -l result

Pending local validation after generation.

## Idempotency

Re-run checks both document SHA-256 hashes, archive route, `information.status = 1`, and archive `information_to_store` mapping for store `0`. It returns `already_applied=yes` only when all are true.

## Rollback

JSON snapshots include the prior live offer, archive information row, archive description, archive store mappings, and SEO row. All DB writes are inside a transaction and roll back on failure.

## Run command (owner)

```bash
cd ~/public_html || exit
php LEGAL-002_offer_mono_pumb_archive_v4_20260724.php
```

## Post-deploy QA checklist

- [ ] Output ends `done=ok` and reports `archive_store_mapping=created` if it repaired the live archive.
- [ ] Both offer URLs return HTTP 200.
- [ ] The archive content has revision date 26.05.2026 and the main offer links to it.

## Side effects / risks

Narrow approved DB scope: current offer description plus archive information/description/store mapping/SEO route only. No checkout, menus, robots, sitemap, canonical or payment changes.
