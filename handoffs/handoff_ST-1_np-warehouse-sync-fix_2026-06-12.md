# Codex Handoff — ST-1: NP warehouse sync fix + daily cron

Date: 2026-06-12. Parent: R-13.5 / plan_R-13.5+_checkout-address-redesign (ST-1, approved).
Source of truth: `(свіжий) backup-6.12.2026_11-25-41_boosters.tar.gz`. Verify all anchors against LIVE files before patching.

## 1. Task ID
ST-1 — Fix Nova Poshta warehouse reference sync (rate-limit failure since install) + scheduled daily re-sync.

## 2. Context
`extension/PintaNovaPoshtaCod/catalog/controller/shipping/reference.php::updateWarehouseReference`:
- calls `deleteAll()` BEFORE fetching → wipes table first;
- pages NP API `Address/getWarehouses` (Limit 1000) in a tight loop, no delay, no retry;
- Ukraine has ~35–40k points → NP rate-limits («Занадто багато запитів») around page 13 → loop breaks → table left with exactly 13 000 rows (confirmed in DB dump and admin UI);
- `reference_warehouse_last_update_datetime` is set EVEN ON FAILURE (line ~275) → date looks fresh, data truncated.
Streets reference was successfully re-synced manually 2026-06-12 (100%). Cron wrapper exists: `system/library/pintanovaposhta/cron.php` (CLI: `php cron.php HTTP_CATALOG CRON_KEY`); `cron_key` is in module settings. No hosting cron job currently registered.
Note: r135 patch (2026-06-12) already applied to this module — local patches in model/controllers MUST survive; never restore from marketplace zip.

## 3. Goal
Warehouse reference syncs fully and reliably (incl. поштомати); failed sync never destroys existing data and never fakes the update date; sync runs automatically daily.

## 4. What to change
File: `extension/PintaNovaPoshtaCod/catalog/controller/shipping/reference.php` (method `updateWarehouseReference`; same pattern optionally for city/area — lower priority, secondary):
1. Remove `deleteAll()`-first. Mark-and-sweep: collect all `ref` values received during pagination → `upsertMultiple` each page → ONLY after the final page succeeded, delete rows whose `ref` was not seen (model may need a `deleteNotIn(array $refs)` or batched equivalent — add to `catalog/model/module/warehouse.php`).
2. Pagination hardening: `usleep` 1.5–2 s between pages; on API error matching rate-limit → exponential backoff retry (2/4/8/16/32 s, max 5 attempts per page); on persistent failure → abort WITHOUT sweep, log errors.
3. `reference_warehouse_last_update_datetime` → set ONLY on full success. On failure write `reference_warehouse_last_error` meta (datetime + message) so admin "Database" tab shows truth.
4. Mind PHP max_execution_time: web-route run with ~40 pages × 2 s sleep ≈ 2–3 min minimum — set `set_time_limit(0)` inside the method (CLI cron unaffected; admin-button run must not die mid-way), keep memory low (per-page upsert, no accumulation of row data beyond refs).
5. Hosting cron (cPanel): daily at ~03:30 Kyiv: `php /home2/boosters/public_html/extension/PintaNovaPoshtaCod/system/library/pintanovaposhta/cron.php https://boostershop.website/ CRON_KEY` — verify exact cron.php path and HTTP_CATALOG value against live; do not hardcode token in any report.

## 5. Do not touch
- `getQuote` / shipping cost logic (cost=>0 stays until ST-2), Hutko, Checkbox/fiscalization, checkout templates/controllers.
- r135 local patches in module (model shipping, payment controller, twigs).
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, Merchant feed, schema.
- DB schema (no new tables/columns; meta key-value rows are fine).
- Street reference data (just synced 100%) — sweep logic must not run for streets in this task.

## 6. Likely files (verify on live)
- `extension/PintaNovaPoshtaCod/catalog/controller/shipping/reference.php`
- `extension/PintaNovaPoshtaCod/catalog/model/module/warehouse.php`
- `extension/PintaNovaPoshtaCod/system/library/pintanovaposhta/cron.php` (read; change only if CLI args/route broken)
- Hosting cron config (cPanel)

## 7. Acceptance criteria
1. Manual run (admin Database tab → Відділення refresh): completes without «Занадто багато запитів»; count > 30 000.
2. Поштомати present: `searchWarehouse` for Київ returns rows with poshtomat type; spot-check 2 against novaposhta.ua.
3. Simulated failure (e.g. temporarily wrong API key): table keeps previous rows, last_update_datetime unchanged, last_error meta written.
4. Cron job registered; next-day `reference_warehouse_last_update_datetime` advances automatically.
5. Diff limited to files in §6; r135 changes intact (sha-check the model file before/after).

## 8. QA / smoke
- Checkout (no changes expected, regression only): guest order to відділення — method shows, order completes.
- Admin Database tab shows real counts/dates after sync.
- No PHP errors in log during full sync run.

## 9. Rollback
- `_patch_backups/st1-np-sync-YYYYMMDD_HHMMSS/` with originals of §6 files before write; patch-runner style as r135 (pre-check anchors, php -l, auto-restore on syntax fail).
- Data: sweep only runs on full success, so failed runs cannot lose rows; worst case re-run sync.
- Cron job: remove single cPanel entry.

## 10. Recommended status after execution
ST-1 `Done` after: full sync OK + next-day cron tick verified by owner. Then ST-0 → ST-2 per plan.
