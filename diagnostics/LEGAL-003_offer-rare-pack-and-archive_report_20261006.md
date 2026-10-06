# Codex Report — LEGAL-003: Offer 06.10.2026 and archive 07.08.2026

Date: 2026-10-06

## Result and scope

WP1 is implemented and locally validated. On 2026-10-06 the owner confirmed successful production deployment and QA ("Все ок. QA ok.") and explicitly authorized commit/push and roadmap/dashboard updates by Codex. Production acceptance is owner-reported; Codex did not deploy or independently inspect host output/browser QA. LEGAL-003 is now `Done` in Notion and `done` in the dashboard mirror.

Owner decision in this chat, 2026-10-06: WP2 is rejected because the owner already corrected the Outlet description and attribute manually. No WP2 file was created; no product or attribute table is accessed by WP1. The handoff and its HTML source were preserved.

The runner changes only id=3/language=4 `description` and creates one new archive in `information`, `information_description`, `information_to_store` and `seo_url`. It preserves the offer title and all meta fields, existing archive bodies/settings, layouts and all other existing rows. The HTML is used verbatim, without DOCX conversion or legal-text changes.

## Files touched

- `patches/LEGAL-003_offer-rare-pack-and-archive_20261006.php` — self-contained production runner.
- `diagnostics/LEGAL-003_offer-rare-pack-and-archive_report_20261006.md` — this report.
- `dashboard/booster-dashboard.html` — only the LEGAL-003 mirror entry, closed after owner QA.
- `context-index.md` — only LEGAL-003 evidence links, without status.
- Existing owner/Claude inputs included in the authorized task commits: `handoffs/handoff_LEGAL-003_offer-rare-pack-and-archive_20261006.md`, `handoffs/offer_html_20261006.html`, `Booster_Shop_Public_Offer_2026-10-06_FINAL.docx`; their contents were not edited. The owner explicitly authorized GitHub publication of the source DOCX in a follow-up on 2026-10-06.
- Local-only test tools and fixtures under `work/legal003/`: build script, integration matrix, selected-table SQL seed, disposable runtime/DB files and execution outputs. These are not deployment or commit inputs.

## Source evidence

- Canonical workspace: `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops`.
- Read `AGENTS.md`, `CODEX_WORKFLOW.md`, the LEGAL-003 handoff and context-index row, the LEGAL-002b runner/report and report template.
- Newest available cPanel backup: `backup-9.24.2026_16-35-03_boosters.tar.gz`. Extracted only the five scoped information/route tables from `mysql/boosters_ocart49.sql` into the scratch seed; no customer, order, credential or unrelated table export.
- All five source tables use InnoDB; the `information` row has exactly `information_id`, `sort_order`, `status`. References 6 and 7 have default-store mappings, archive meta patterns and no layout mappings, as confirmed by the DB test.
- Seeded live id=3/language=4 body SHA-256: `08695acfe3c7e1a1b6f5d09360879d2af3e004384494c41742e1197e420e2cae`.
- Approved HTML: 89,596 bytes, 21 `<h2>` sections, SHA-256 `9e22347c80bd773292832614818ecba68d66b053af37a0e369f54d43e07efb71`.
- Runtime archive is exactly the requested banner followed immediately by the verified live old body, without whitespace insertion or normalization. Expected SHA-256: `8df9d9bae07699976433cd7945f5c8624bf8fb4149fc851780617da7bfc0624c`.
- Backup `catalog/model/catalog/information.php`: `getInformation()` reads the DB directly; `getInformations()` caches the information list. Owner command includes cache cleanup after successful application.

The backup is evidence of 24.09 state, not proof of today's production state. The runner checks the actual schema, engines, archive references and locked source row on the owner's host before writing.

## Safety and deviations from the precedent

- CLI only; checks `config.php` and lints itself before DB mutation.
- PHP 8.0-compatible; `(int) DB_PORT`; prepared result reads use `bind_result()`. No `get_result()` or `fetch_all()` dependency.
- Exact gzip+base64 blob hash and exact archive `href` gates.
- Requires verified column sets, InnoDB and no triggers on scoped tables. Unexpected source/schema/reference state refuses execution and retains the runner.
- Source is locked inside the transaction before reading its hash. No permissive partial-state repair: a new offer without a complete correct archive refuses execution.
- Unlike the precedent, the offer meta description is never updated.
- Unlike the precedent, rollback IDs and executable rollback SQL are saved **before commit**. A backup-write or in-transaction verification failure rolls back DB writes.
- Old-body backup uses strict JSON encoding; no silent invalid-UTF-8 substitution.
- Driver exception details are suppressed to avoid logging credentials/server details.
- Self-deletes only after a successful commit or a verified, unchanged idempotent state; logs `self_delete=ok` or `failed`.
- No template, CSS, JavaScript, SEO policy, noindex or sitemap changes. Archive noindex remains outside the task.

## Local integration result

An isolated local MySQL 8.4.3 instance bound to `127.0.0.1:33173` was seeded with the selected tables from the 24.09 dump. The matrix passed on both PHP 8.3.30 and **PHP 8.0.30**. PHP 8.0.30 was obtained as an isolated portable runtime from the official Windows PHP archive; the existing runtime was not replaced.

```
PASS embedded_blob_byte_equality
PASS seed_live_offer_sha256
PASS fresh_apply_archive_exact_metadata_protected_rows_backups_self_delete
PASS idempotent_rerun_no_writes
PASS generated_rollback_restores_all_five_tables
PASS refusal_altered_no_writes_file_kept
PASS refusal_slug_collision_no_writes_file_kept
PASS refusal_title_collision_no_writes_file_kept
PASS refusal_mirror_layout_no_writes_file_kept
PASS refusal_nontransactional_no_writes_file_kept
PASS post_insert_failure_transaction_rollback_file_kept
PASS partial_new_edition_refusal_no_writes
ALL CHECKS PASSED; PHP 8.0.30 / MySQL 8.4.3
```

Fresh-apply assertions compare every existing row in all five selected tables against its initial snapshot, allowing only the intended offer-description change. They verify exactly one new row in each of the four write tables, no new layout, identical source metadata, exact archive banner/body, backup contents and self-deletion. The generated rollback restores all five table-row snapshots exactly (auto-increment counters are not reset).

The forced post-insert failure uses a test-only user with SELECT/INSERT but no UPDATE permission: archive INSERTs execute, the offer UPDATE fails, and every inserted row is rolled back. Failure cases return exit 1, preserve all initial rows and retain the runner.

Local reproduction, while the isolated scratch server is running:

```powershell
Set-Location 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops'
$env:LEGAL003_PHP = (Resolve-Path 'work/legal003/php80/php.exe').Path
python work/legal003/test_patch.py
```

This matrix is DB/runtime evidence, not independent production deployment, HTTP 200 or rendered-layout proof. No UI/CSS rules changed. The owner subsequently confirmed successful production QA on 2026-10-06; no independent browser proof is claimed.

## php -l result

Executed with PHP 8.0.30:

```
No syntax errors detected in patches/LEGAL-003_offer-rare-pack-and-archive_20261006.php
```

## Idempotency

Re-uploading the same runner after a full successful application returns `already_applied=yes`, `done=ok`, `self_delete=ok` and changes no rows. It verifies the archive information settings, description/meta, route, store and absence of layout mappings. Conflicting or incomplete state is refused rather than repaired.

## Rollback

Before production execution, the owner must download a separate OpenCart MySQL database backup from cPanel; file-runner backups are not a whole-database backup.

The runner saves these beneath `_patch_backups/LEGAL-003_offer-rare-pack-and-archive_20261006-<timestamp>-<random>/db/`:

- `live_offer_before.json` — original full id=3/language=4 description row and body SHA.
- `created_ids.json` — actual assigned information and SEO IDs, prepared before commit.
- `rollback.sql` — original body encoded as UTF-8 hex, exact assigned-ID deletes and a transaction.

For a successful application requiring rollback, review the saved IDs and execute `rollback.sql` through an owner-controlled DB client. It restores only the offer body and deletes only the created route/store/description/information rows, in that order. Do not apply it after a later offer revision or unrelated edits to the new archive. After `done=failed`, transaction writes are rolled back; the pre-commit backup files may still exist and must not be mistaken for committed IDs. Clear cache after a production rollback.

## Run command (owner, after review)

1. Download the OpenCart database backup in cPanel.
2. Upload the reviewed PHP runner to `~/public_html`.
3. In the hosting terminal, run this single block:

```bash
cd ~/public_html
php LEGAL-003_offer-rare-pack-and-archive_20261006.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

Expected: `updated_offer=description_only`, the approved offer/archive SHA values, assigned archive IDs, `transaction=committed`, `done=ok`, `self_delete=ok`, then `cache cleared`. On failure, stop and provide the bounded output; do not edit hashes or retry a partial repair.

## Post-deploy QA checklist

- [ ] `/information/publichna-oferta`: 21 sections, edition 06.10.2026, section 2.17 Rare Pack present; page title/meta unchanged.
- [ ] Section 21: all three archive links (07.08, 24.07, 26.05) open with HTTP 200.
- [ ] `/information/publichna-oferta-arhiv-2026-08-07`: requested banner, old body and section 20 edition 07.08.2026; title `Публічна оферта — архів 07.08.2026`.
- [ ] Sections 1.3 and 19: channel and support-bot Telegram links open.
- [ ] Checkout consent link opens the current offer; no order needs to be created.
- [ ] Tier 1 pages from `AGENTS.md`: home, top/nested category, sealed/3D product, payment/delivery information, cart and checkout entry render normally.
- [ ] Offer/archive long-text layout at desktop/tablet/mobile widths and archive-link hover/focus navigation.

No WP2 QA is required. The owner confirmed the QA above and authorized LEGAL-003 closure on 2026-10-06. Codex updated the verified Notion card and dashboard under that explicit current-task instruction. The LEGAL-003 prerequisite is complete; publishing the actual Rare Pack cards remains a separate CAT-004 action and is not claimed here.

## Review and Git boundary

The owner authorized a scoped commit/push on 2026-10-06 after deployment and QA. Commit `2602053` published the runner/report, exact LEGAL-003 dashboard entry and evidence-index row, and unchanged handoff/HTML: six files total. The owner subsequently gave explicit permission to publish `Booster_Shop_Public_Offer_2026-10-06_FINAL.docx` to the same GitHub repository after automatic approval review had required document-specific authorization. Its SHA-256 was rechecked as `1e2fa1ae75e646a3ffb46604abd3828bb03349b8f5e8f3bd2e24991c8aef4197`; the follow-up commit adds that unchanged source and this authorization record. Unrelated working-tree changes and local test runtimes/seeds are excluded. The existing branch is `codex/3dp-payout-approval-status`, with its same-name upstream; no master merge was requested.
