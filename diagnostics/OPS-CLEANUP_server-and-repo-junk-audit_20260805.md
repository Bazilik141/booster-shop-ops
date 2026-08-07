# OPS-CLEANUP — Server and repository junk audit

**Date:** 2026-08-05
**Author:** Claude (audit only — no server access, no writes performed)
**Roadmap ID:** not assigned yet (owner to decide whether this becomes a roadmap task)
**Status:** proposal awaiting owner approval — nothing has been deleted

## Evidence source

Single input: owner-provided cPanel backup
`booster-shop-ops/backup-8.5.2026_10-49-27_boosters.tar.gz`
(3.55 GB, 22 794 entries, taken 2026-08-05 10:49).

Method: streamed `tar -tzv` listing only. The archive was **not** extracted
except for `cron/boosters` and `catalog/controller/cron/*` (needed to prove
which scripts are cron-driven). No credential-bearing file was opened.

Confirmed home path: `/home2/boosters` (from `_home2_boosters_*` backup
filenames inside `ocartdata/storage/backup/`).

## 1. Server disk map (top of backup)

| Path | Size | Entries |
|---|---:|---:|
| `homedir/.trash` | 3 068.69 MB | 337 |
| `homedir/public_html` | 230.95 MB | 8 842 |
| `homedir/_disabled_wordpress_2026_04_27` | 104.35 MB | 6 511 |
| `mysql/boosters_ocart49.sql` | 81.30 MB | 1 |
| `homedir/wordpress-backups` | 55.97 MB | 3 |
| `homedir/ocartdata` | 51.45 MB | 5 507 |
| `homedir/mail` | 17.11 MB | 744 |
| `homedir/tmp` | 8.65 MB | 204 |

`.trash` alone is 86 % of the backup weight.

## 2. Junk classified by risk tier

### Tier 1 — safe, no runtime dependency

| ID | Path | Size | Files | Why it is dead |
|---|---|---:|---:|---|
| A | `~/.trash/backup-*.tar.gz` (20 files, 2026-04-23 → 2026-05-30) | 3 059.61 MB | 20 | Old full cPanel backups already superseded by the 2026-08-05 backup. `.trash` is the cPanel File Manager recycle bin — emptying it is a first-class cPanel operation. |
| B | `~/.trash/*` remainder — ~300 debug tarballs, `*.twig.N` numbered copies, spent patch runners | 9.08 MB | 317 | Already deleted by the owner via File Manager; only the recycle bin holds them. |
| F1 | `public_html/{_patch_backups,_booster_patch_backups,booster_patch_backups,_bs_patch_backups,_boostershop_patch_backups,_patch_reports}` older than 2026-07-01 | 5.83 MB | 1 030 | Rollback snapshots for tasks closed in May–June. Five parallel folder names exist because the naming convention changed over time. |
| G | `~/tmp/{analog,webalizer,awstats,pma_template_compiles_boosters}` | 8.64 MB | 202 | cPanel statistics output and phpMyAdmin template cache — both auto-regenerated. |
| I | Stale diagnostic working directories (see §3) | ~5.1 MB | ~440 | Evidence folders for closed tasks; the durable copies live in this repository under `diagnostics/`. |

**Tier 1 total: ≈ 3 088 MB.**

### Tier 2 — safe but owner must confirm the content is not needed

| ID | Path | Size | Files | Note |
|---|---|---:|---:|---|
| C | `~/_disabled_wordpress_2026_04_27/` | 104.35 MB | 6 511 | WordPress disabled 2026-04-27. Sits outside `public_html`, so it is not web-served. |
| D | `~/wordpress-backups/*.tar.gz` (2026-04-23, 2026-04-26) | 55.97 MB | 3 | The archive of C. Recommend downloading locally before removing both, or keeping D and removing C. |

**Tier 2 total: ≈ 160 MB.**

### Tier 3 — regenerable caches (cost: CPU spike + slower first page loads)

| ID | Path | Size | Files | Note |
|---|---|---:|---:|---|
| E | `public_html/image/cache/` | 51.63 MB | 1 158 | OpenCart regenerates thumbnails on demand. |
| H | `ocartdata/storage/cache/` | 8.16 MB | 320 | Regenerated on next request. |

**Tier 3 total: ≈ 60 MB.**

### Tier 4 — security-relevant, small but should not stay in the web root

23 leftover files sit directly in `public_html/` and are therefore reachable
over HTTP by anyone who guesses the filename:

- executable: `PAY-001-INFO-1_preflight_20260726.php`,
  `PAY-001-INFO-1_preflight_state_20260726.php`,
  `rd10_product_page_parity_20260611.php`,
  `rd10_product_page_parity_20260611b.php`,
  `booster_crm_flush.php`, `booster_crm_sync_replay_20260616.php`
- readable internals: `st2b6-confirm-callers.txt`,
  `st2b6b-payment-state-matches.txt`, `st2b6c-autoselect-matches.txt`,
  `st2a8*.md/.json`, `st2a9_*.md`, `booster-404-files.txt`,
  `rd131j_patch_backups.txt`
- served-as-plaintext config history: `.htaccess.bak-tech005-20260609`,
  `.htaccess.bak-tech005deep-20260606`, `robots.txt.bak-tech005-20260606`,
  `robots.txt.bak-tech005-20260609b`
- superseded feed artefacts: `merchant-feed.tsv.bak-20260605-174550`,
  `merchant-feed.tsv.bak-20260611-080654`,
  `merchant-feed-preorder-patch-20260605-174550.log`,
  `merchant-feed-rebuild-20260611-080654.log`

`booster_crm_flush.php` and `booster_crm_sync_replay_20260616.php` are the
highest concern: both are CRM-sync entry points left in a public directory.
Their behaviour when hit anonymously was **not** verified in this audit — that
requires reading the files, which is a separate bounded task.

**Tier 4 total: ≈ 0.17 MB. Value here is exposure reduction, not disk.**

## 3. Stale working directories

Home directory:

| Path | Size | Files |
|---|---:|---:|
| `~/rd13_site-snapshot_20260705_121237/` | 2 840.9 KB | 65 |
| `~/tech005_proof_20260522-101026/` | 286.9 KB | 109 |
| `~/_sm_analysis_20260608/` | 231.2 KB | 132 |
| `~/bs_gsc_schema_debug_20260526_150420/` | 127.8 KB | 12 |
| `~/_public_html_cleanup_20260520-213343/` | 38.5 KB | 22 |
| `~/_public_html_cleanup_20260520-213522/` | 30.6 KB | 11 |
| `~/RD-13_checkout-reskin-round6_20260708.php` | 62 KB | 1 |
| `~/bs-first15-error-tail.txt` | 41 KB | 1 |
| `~/merchant-feed-build.php.bak-20260605-174550` | <1 KB | 1 |

Inside `public_html`:

| Path | Size | Files |
|---|---:|---:|
| `booster-seo-crit-001-debug-20260607-080345/` | 640.9 KB | 46 |
| `rd04-verify/` | 417.1 KB | 15 |
| `rd04-preorder-check/` | 414.0 KB | 16 |
| `booster-tech030-031-debug/` | 45.0 KB | 12 |
| `booster-order180-diagnostic-20260616-095116/` | 30.1 KB | 5 |
| `booster-crm-sync-files-20260611-111517/` | 18.6 KB | 7 |
| `booster-crm-sync-debug-20260611-110825/` | 18.0 KB | 7 |

`~/CHECKOUT-009-evidence-20260729-155027/` (31.0 KB, 7 files) is **excluded** —
CHECKOUT-009 is recent work and the evidence may still be needed.

## 4. Must NOT be deleted — verified dependencies

Proven from `cron/boosters` inside the backup:

```
15 4  * * * /bin/bash /home2/boosters/sitemap-regen.sh
*/2  * * * * /usr/local/bin/php /home2/boosters/public_html/system/library/booster_async_queue_worker.php
```

| Path | Reason |
|---|---|
| `~/sitemap-regen.sh` | daily cron 04:15 |
| `public_html/system/library/booster_async_queue_worker.php` | cron every 2 minutes |
| `~/logs/booster-async-order-sync.log` | active cron output target |
| `ocartdata/storage/vendor/` (40.20 MB) | Composer dependencies — required at runtime |
| `ocartdata/storage/marketplace/` (1.75 MB, 22 `.ocmod.zip`) | extension installers; removing them breaks reinstall/uninstall from admin |
| `ocartdata/storage/{session,upload,download,config}/` | runtime directories |
| `ocartdata/storage/backup/` (833.9 KB, 97 files, all pre-2026-07-01) | patch rollback evidence — low weight, keep |
| `public_html/{.htaccess,robots.txt,sitemap-full.xml,sitemap_index.xml,merchant-feed.tsv}` | SEO / Merchant Center risky zone |
| `public_html/adminEvhenii/` (23.61 MB) | custom admin directory |
| `public_html/image/catalog/` (123.24 MB, 137 files) | product imagery |
| `~/mail/` (17.11 MB) | live mailboxes |
| `ocartdata/storage/logs/error.log` (516 948 B, last write 2026-08-04 20:29) | active diagnostic evidence — download before truncating, do not delete |

## 5. Local repository (`booster-shop-ops`) — separate from server

| Item | Size | In git? |
|---|---:|---|
| `ncrm/.next/` | 695 MB | no (ignored) |
| `ncrm/node_modules/` | 359 MB | no (ignored) |
| `patches/` — 162 files | 5.8 MB | yes, all tracked |
| `dashboard.zip`, `testwrite.tmp`, `_writetest.tmp`, `scripts/__pycache__`, `ncrm/tsconfig.tsbuildinfo` | ~70 KB | no (ignored) |

Two constraints found:

1. `context-index.md` references `handoffs/` 91 times and `diagnostics/` 21
   times, but `patches/` only once. Removing handoffs or diagnostics would
   break task-context lookup; removing patches would not.
2. The working tree currently holds 10 modified and 8 untracked files (active
   3D-P-010 / 013 / 014 / 015 work). Any removal of *tracked* files needs its
   own isolated commit and must not be mixed into that set. Removing *ignored*
   files needs no commit at all.

`ncrm/node_modules` is regenerable via `npm install`, but it is required to run
or build the NCRM Next.js app. Owner asked to keep it if the NCRM task chain
continues — recommendation is therefore: delete `.next` (695 MB, pure build
output), keep `node_modules`.

## 6. Rollback position

- Tier 1 A/B: the 2026-08-05 backup in the repository contains a full copy of
  everything in `.trash`, so the archive itself is the rollback.
- Tier 1 F1/G/I and Tier 4: same — present in the 2026-08-05 backup.
- Tier 2 C/D: present in the 2026-08-05 backup; additionally recommend a local
  download before removal.
- Tier 3 E/H: regenerable by design, no rollback required.

Any server deletion must be run **list-first**: print the matched paths and the
reclaimed size, have the owner read the output, and only then run the removal.

## 7. Web-root script assessment (owner-requested, completed 2026-08-05)

Six PHP files from `public_html/` were extracted from the backup and read. No
secret value is reproduced below.

| File | Auth gate | Writes | Verdict |
|---|---|---|---|
| `booster_crm_sync_replay_20260616.php` | **none** | rewrites `system/library/booster_crm_sync.php` (L304), deletes queued payloads, re-signs and replays the queue to the CRM | **highest severity** |
| `rd10_product_page_parity_20260611.php` | **none** | `file_put_contents` on templates (L56), `UPDATE ... SET code = ?` on a DB table (L662) | high |
| `rd10_product_page_parity_20260611b.php` | **none** | same, L56 / L688 | high |
| `booster_crm_flush.php` | `$_GET['token']` + `hash_equals`, 403 otherwise | deletes queue files, POSTs to Apps Script | medium |
| `PAY-001-INFO-1_preflight_20260726.php` | none | read-only (verified: no UPDATE/INSERT/write) | low |
| `PAY-001-INFO-1_preflight_state_20260726.php` | none | read-only (verified) | low |

Three findings that change the priority of Tier 4:

1. **Three unauthenticated live-mutation scripts sit in the public web root.**
   `booster_crm_sync_replay_20260616.php` and both `rd10_*` runners execute
   their full effect on a plain anonymous GET. No token, no CLI-only gate, no
   IP restriction.
2. **Each of the three ends with `@unlink(__FILE__)`** (replay L344, rd10 L791,
   rd10b L826). Their continued presence is therefore evidence that the last
   execution did not reach the end — they either failed partway or were never
   completed. They are live, runnable, and in an unknown completion state.
3. **`booster_crm_flush.php` is orphaned.** `grep` over
   `system/library/booster_crm_sync.php` and
   `system/library/booster_async_queue_worker.php` returns no reference to it.
   The cron worker uses `system/library/booster_async_queue.php` instead.
   Removing the flush script does not break the CRM sync path.

Secondary exposure: `booster_crm_flush.php` embeds the Apps Script Web App URL
and the flush token as plaintext constants; both `PAY-001-INFO-1_*` scripts
print the resolved DB table prefix and file SHA-256 hashes to any caller.

## 8. Approved scope (owner decision, 2026-08-05)

Approved: **Tier 1** and **Tier 4**.
Deferred: Tier 2 (WordPress) and Tier 3 (caches).
Backup rule: delete every backup in `.trash` (all are 2026-04/2026-05); the
current 2026-08-05 backup is explicitly protected and must not be matched by
any delete pattern.
Execution channel: cPanel Terminal, owner-run, dry-run before every removal.

Expected reclaim: ≈ 3.09 GB, plus removal of three unauthenticated mutation
endpoints from the public web root.

Commands prepared in:

- `diagnostics/OPS-CLEANUP_server-commands_20260805.sh` (cPanel Terminal)
- `diagnostics/OPS-CLEANUP_local-commands_20260805.ps1` (owner's machine)

## 9. Open items for the owner

1. Tier 2 — confirm whether anything is still needed from the disabled
   WordPress install before it is removed (≈ 160 MB still pending).
2. Tier 3 — decide whether the cache purge is worth the first-load slowdown.
3. Decide whether this audit becomes a roadmap task in Notion.
4. `ocartdata/storage/logs/error.log` (516 948 B, last write 2026-08-04 20:29)
   was deliberately left untouched. Download it before any future truncation.
