# Booster Shop — Codex Working Instructions (v3, 2026-06-27)

My project is Booster Shop, an ecommerce store on OpenCart 4.
Reply to me in Ukrainian. You may use English internally for reasoning, planning,
code search, technical terms, code comments, and service logic when more precise.
The final response to me must be Ukrainian, short, and pragmatic.

## Canonical local context

- **Repo (local):** `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\`
- **GitHub:** `https://github.com/Bazilik141/booster-shop-ops` (branch: `master`)
- **Dashboard (live):** `C:\Users\14bez\Downloads\Booster Shop\booster-dashboard.html`
- **Dashboard (git copy):** `dashboard/booster-dashboard.html` inside the repo

Retired paths — do NOT use:
- `E:\Personal Files\...`
- `E:\Program Files\...`

New deliverables → appropriate subfolder in the canonical repo above.

## 0. Roles & access (read first)

- **Codex** — technical: file analysis, patch creation, terminal commands, reports.
  Drops files to repo. Commits/pushes **only when owner explicitly asks**.
- **Claude** — audits, diagnosis, handoffs, post-patch review, git diff/read.
  Updates Notion status. Does NOT deploy.
- **Owner** — approves in chat, uploads + runs patch on server. The only prod gate.

**No server access for anyone but owner.** No SSH, no FTP, no DB connection.
If a needed file is missing, give owner a tar command (§4). Do not guess OpenCart structure.

Canonical ops docs (read these before work):
- `AGENTS.md` — roles, paths, repo structure, patch conventions
- `CODEX_WORKFLOW.md` — exchange protocol Claude ↔ Codex
- `ROADMAP_SOP.md` — Notion sync, status vocab, task lifecycle, autosync safety
- `context-index.md` — fast map `ID → handoff file → Notion page_id` (no status here)

## 1. Default format for code fixes

Create ONE self-contained PHP patch file per task.
No scattered supporting files, no unnecessary folders.
Follow patch conventions in §2.

After creating the patch, give ONE terminal command block:
```
cd ~/public_html || exit
php patch-name.php
```

Add cache-clearing only when the task truly needs it:
```
cd ~/public_html || exit
php patch-name.php
rm -rf system/storage/cache/*
rm -rf system/storage/modification/*
```

## 2. Patch file rule

- **Source-controlled patch:** `patches/<TASK-ID>_<slug>_<YYYYMMDD>.php` (in the repo)
- **Uploadable copy (identical):** drop to repo as well — owner picks it from `patches/`
- Owner uploads the patch to `~/public_html` and runs it.

Final response must show the local path and one terminal command block.

## 3. Mandatory requirements for every patch

1. **File exists check** — fail with clear error if target not found; never blind-edit
2. **Anchor pre-check** — fail if anchor count != expected; no blind edits
3. **Backup** to `_patch_backups/<patch>-<ts>/` before any write
4. **`php -l` gate** — restore-on-fail; no silent failures
5. **Idempotent marker** — `already_applied=yes` on repeat run
6. **DB changes** — only with explicit owner approval + rollback SQL in patch header + warning
7. **Self-delete** after success: `@unlink(__FILE__);`

After creating a patch, show only:
- What it does (1-2 sentences / brief bullets)
- Local path: `patches/<filename>`
- Patch check: syntax ok or problem
- Command block (§1)

If there is no `done=ok` or an error after owner runs it → owner sends terminal output → debug.

## 4. Current files before work (no server access)

Before patching, use the **newest cPanel backup** in `C:\Users\14bez\Downloads\Booster Shop\`.
Check filename timestamps — live is always newer than older backups.
Approved patches in `patches/` and Claude handoffs are the technical history.

If a needed file is missing, ask owner to collect it:
```
cd ~/public_html || exit
tar -czf booster-debug-files.tar.gz path/to/file1.php path/to/file2.twig
```
Drop to `~/public_html/booster-debug-files.tar.gz`. Owner downloads and sends.

## 5. Source of truth

- **Notion roadmap** — task status, priorities (canonical). Claude reads/writes Notion; Codex does NOT touch Notion.
- **Repo** — implementation history: patches, handoffs, diagnostics, diffs
- **`context-index.md`** — fast `ID → handoff → Notion page_id` lookup (no status stored here)
- Dashboard `ROADMAP_FLOW` — mirror of Notion statuses; Codex updates it as the last step of any roadmap-affecting patch (Claude writes this requirement into the handoff).

Roadmap governance: `ROADMAP_SOP.md`.

## 6. Working with Claude (handoff-driven flow)

Typical flow: **Claude handoff → Codex patch → Claude review → Owner deploy.**

When a Claude handoff is provided:
1. Read the file in `handoffs/` FIRST. Grep `context-index.md` for the task ID to find the right file quickly.
2. Treat the handoff as the scope boundary — do not expand scope unless owner asks.
3. Create/update the patch in `patches/` (§2).
4. Do NOT duplicate Claude's audit — add only: which files change, exact location, minimal safe fix, risks, acceptance criteria, QA checklist.
5. If a handoff contains several tasks (Task A / Task B) — ship as SEPARATE patches/commits.
6. After changes, show `git diff` or concise summary and WAIT for review.

## 7. If owner asks for something "for Claude"

Structured brief, no long text:
Context / Problem / What was tried / What did not work / What to check / Risks / Acceptance criteria.

## 8. Risky zones — extra care required

checkout · payment · Hutko · Checkbox · fiscalization · Nova Poshta · order status ·
Merchant feed · schema/JSON-LD · SEO (sitemap/robots/canonical/.htaccess) · CRM · DB

For any task touching these zones: FIRST explain which files change, whether DB is affected, the risk, and rollback from backup. THEN create the patch (which still makes its own backup).

## 9. When NOT to create a patch immediately

If any of these apply:
- Responsible file is unclear
- Must read Claude handoff first
- Change may affect risky zone (§8)
- Several possible causes need diagnostics
- Missing files/logs/code fragments

Give a short diagnostic plan:
```
Need to check:
1. ...
Required files:
* ...
Command to collect:
cd ~/public_html || exit
tar -czf booster-debug-files.tar.gz ...
```

## 10. Diagnostics report

**Required for:** handoff tasks, risky zones, diagnostic investigations.
**Not required for:** simple cosmetic patches (chat summary is enough unless owner asks).

Naming: `diagnostics/<TASK-ID>_<slug>_report_<YYYYMMDD>.md`
Template: `templates/codex-report-template.md`

Report must contain: scope · files touched · dry-run result · `php -l` · idempotency · rollback · run command · post-deploy QA checklist.

## 11. Booster Shop CRM & Automation

Apps Script API first for read-only checks (not Sheets directly).
GET endpoints: `action=summary`, `action=orders&status=active|all|shipped|unpaid&limit=N`, `action=stock_alerts`, `action=sku_list`.
Call via GET query params — NOT JSON POST (doPost is reserved for order-sync).
Use Sheets directly only for edits, formula/validation inspection, debugging, or data the API doesn't cover.
Auth: BOOSTER_CRM_TOKEN / known secure local context. Never expose token values anywhere.

## 12. GitHub repo workflow

**Main repo:** `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\`
Structure: `handoffs/` · `patches/` · `plans/` · `diagnostics/` · `dashboard/` · `templates/`

**Commit/push policy: do NOT commit or push unless owner explicitly asks.**
Show `git diff` or concise summary and wait for review.

**Autosync safety (ROADMAP_SOP.md §4/§8):** before `git add`/`commit`:
```
New-Item .autosync-pause
```
After `push`:
```
Remove-Item .autosync-pause
```
This pauses `bs-autosync.ps1` and prevents `.git/index` race.

### Commit message format (English)
```
Codex: <TASK-ID> <short description>
```
Examples:
- `Codex: ST-3 restore promo description on product card`
- `Codex: TECH-041 add FAQ schema to product page`
- `Codex: ST-7 simplify Nova Poshta validation`

Keep under 72 chars. Be specific. No generic messages ("update files", "fix issue").
For risky areas include scope in description: checkout, payment, merchant, schema, seo, crm.

### Pull request format (English, when requested)
Title: `Codex: <TASK-ID> <short description>`
Description:
```
Summary
- what changed
Scope
- affected files / modules
Risk
- low / medium / high
Manual QA
- required checks
Rollback
- how to revert
```

### Do NOT commit
`.bak` · `.tar.gz` · `.zip` · `.log` · DB dumps · secrets / tokens / API keys
(most already in `.gitignore`)
