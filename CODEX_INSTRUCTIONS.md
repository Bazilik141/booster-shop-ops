# Booster Shop — Codex Working Instructions (v3, 2026-06-27)

This is a legacy quick-reference. `AGENTS.md` and `CODEX_WORKFLOW.md` are
canonical when they differ; in particular, do not use the old FTP, public-root
upload or hard-coded cache paths below.

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

**Only the owner accesses production.** Agents never run SSH/SCP/SFTP or key
utilities and never read the owner's SSH files. Direct DB clients and DB tunnels
over SSH are prohibited. Follow `CODEX_WORKFLOW.md` for owner-run deployment and
narrow fresh pulls; do not guess live OpenCart paths.

Canonical ops docs (read these before work):
- `AGENTS.md` — roles, paths, repo structure, patch conventions
- `CODEX_WORKFLOW.md` — exchange protocol Claude ↔ Codex
- `ROADMAP_SOP.md` — Notion sync, status vocab, task lifecycle, autosync safety
- `context-index.md` — fast map `ID → handoff file → Notion page_id` (no status here)

## 1. Default format for code fixes

Create ONE self-contained PHP patch file per task.
No scattered supporting files, no unnecessary folders.
Follow patch conventions in §2.

After creating a patch, use the exact facts required by `CODEX_WORKFLOW.md` to
compose the owner-run PowerShell blocks yourself: private staging target,
SHA-256, PHP/lint preflight, working directory, receipt, rollback states and
task checks. Locally validate the packet; when the canonical policy routes the
task to review, actual Claude reviews its final hash with the patch hash in one
independent review. Keep upload, verification and
run as separate owner actions; required cache cleanup may follow the run with `&&`. Add cache
cleanup only when the patch requires it. The owner-confirmed live `DIR_STORAGE`
is recorded in `AGENTS.md`; check the owning application's runtime `DIR_CACHE`
with the same-shell lookup in `CODEX_WORKFLOW.md` before any future cleanup.
Never use `system/storage/...`
or clear a guessed directory. Clearing `modification/` also requires the
OpenCart Modifications refresh before its generated copies are available again.

## 2. Patch file rule

- **Source-controlled patch:** `patches/<TASK-ID>_<slug>_<YYYYMMDD>.php` (in the repo)
- **Upload source:** the same repository file; do not create a duplicate in Downloads.
- Owner uploads by SCP to the verified private staging path and runs from `~/public_html`.

Final executor response must show the local path, patch SHA-256, per-target
rollback facts, receipt contract, locally checked executor-prepared owner-run
packet and its SHA-256. When review is required by the canonical policy, actual
Claude reviews both exact hashes together before owner execution; otherwise
record the applicable no-review route without implying a Claude verdict.

## 3. Mandatory requirements for every patch

1. **Target state check** — fail if an existing-file target is missing or a new-file target already exists; never blind-edit
2. **Anchor pre-check** — fail if anchor count != expected; no blind edits
3. **Backup** to `_patch_backups/<patch>-<ts>/` before any write
4. **`php -l` gate** — restore-on-fail; no silent failures
5. **Idempotent marker** — `already_applied=yes` on repeat run
6. **DB changes** — only with explicit owner approval + rollback SQL in patch header + warning
7. **Self-delete** after success: `@unlink(__FILE__);`

After creating a patch, show:
- What it does (1-2 sentences / brief bullets)
- Local path: `patches/<filename>`
- Patch and executor-prepared command-packet SHA-256 values; add the actual
  Claude verdict after both exact hashes have been reviewed together when the
  canonical policy requires review
- Expected receipt and exact per-target backup/rollback states
- Patch check: syntax ok or problem
- Exact owner-run PowerShell upload, hash/lint, run and post-run blocks;
  record local packet checks and its hash before the integrated review when
  required by the canonical policy

If there is no `done=ok` or an error after owner runs it → owner sends terminal output → debug.

## 4. Current files before work (agents have no server access)

Before patching, use the **newest cPanel backup** in `C:\Users\14bez\Downloads\Booster Shop\`.
Check filename timestamps — live is always newer than older backups.
Approved patches in `patches/` and Claude handoffs are the technical history.

If a needed file is missing, ask owner to collect it:
```
<owner-run SSH/SCP pull from CODEX_WORKFLOW.md with exact task-specific filenames>
```
Keep archives and manifests outside `public_html`, use unique names, verify the
SHA-256 manifest after download, then delete only this task's archive and
manifest under `~/private/booster-pulls/`; never delete pulled live sources.

## 5. Source of truth

- **Notion roadmap** — task status, priorities (canonical). Claude reads/writes Notion; Codex does NOT touch Notion.
- **Repo** — implementation history: patches, handoffs, diagnostics, diffs
- **`context-index.md`** — fast `ID → handoff → Notion page_id` lookup (no status stored here)
- Dashboard `ROADMAP_FLOW` — mirror of Notion statuses; Codex updates it as the last step of any roadmap-affecting patch (Claude writes this requirement into the handoff).

Roadmap governance: `ROADMAP_SOP.md`.

## 6. Working with Claude (handoff-driven flow)

Typical flow: **Claude handoff → Codex patch and owner-run packet → local packet
checks → actual Claude review of both hashes when required by policy → owner-run
SSH/SCP → owner QA.** Routine bounded work on the existing main CRM Google
Sheets workbook and canonical dashboard skips Claude review by default. Unchanged
hashes and expected receipts close mechanically.

When a Claude handoff is provided:
1. Read the file in `handoffs/` FIRST. Grep `context-index.md` for the task ID to find the right file quickly.
2. Treat the handoff as the scope boundary — do not expand scope unless owner asks.
3. Create/update the patch in `patches/` (§2).
4. Do NOT duplicate Claude's audit — add only: which files change, exact location, minimal safe fix, risks, acceptance criteria, QA checklist.
5. If a handoff contains several tasks (Task A / Task B) — ship as SEPARATE patches/commits.
6. At a ready required stage, initiate the actual subscription Claude review
   under the canonical policy. Do not start extra review rounds for unchanged
   bytes or expected receipts; repair substantive findings as one batch.

## 7. If owner asks for something "for Claude"

Structured brief, no long text:
Context / Problem / What was tried / What did not work / What to check / Risks / Acceptance criteria.

## 8. Risky zones — extra care required

checkout · payment · Hutko · Checkbox · fiscalization · Nova Poshta · order status ·
Merchant feed · schema/JSON-LD · SEO (sitemap/robots/canonical/.htaccess) · CRM · DB

Risky-zone precautions remain in force. The CRM label alone does not require
Claude review for routine bounded work on the existing main CRM Google Sheets
workbook and canonical dashboard; use the exception triggers in
`docs/BOOSTER_AUTOMATIC_PEER_REVIEW_20261014.md`.

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
Command to collect: executor supplies the exact files and purpose for the
owner-run narrow SSH/SCP pull in `CODEX_WORKFLOW.md`, using only named files and
task-unique archive/manifest paths. Codex checks exclusions and scope before the
owner runs it.
```

## 10. Diagnostics report

**Required for:** handoff tasks, risky zones, diagnostic investigations.
**Not required for:** simple cosmetic patches (chat summary is enough unless owner asks).

Naming: `diagnostics/<TASK-ID>_<slug>_report_<YYYYMMDD>.md`
Template: `templates/codex-report-template.md`

Report must contain: scope · files touched · dry-run result · `php -l` ·
idempotency · patch SHA-256 (reviewed when required by policy) · per-target before/after hashes or
`absent` states · exact backup paths or `none` · stdout contract · rollback
facts for the executor-prepared owner-run PowerShell packet · local packet
checks · packet SHA-256 · bind it with the patch hash in the integrated review
when required by policy, and record the applicable no-review route otherwise ·
post-deploy QA checklist.

## 11. Booster Shop CRM & Automation

Routine bounded work on the existing main CRM Google Sheets workbook and canonical
`dashboard/booster-dashboard.html` has no Claude review by default. Preserve the
source/integrity, formula, owner-approval and live-QA requirements; structural,
integration/authentication, accounting/stock, broad or materially ambiguous work
uses the owner-selected review route in the canonical policy.

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
