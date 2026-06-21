# AGENTS.md — Booster Shop ops rules (Claude + Codex)
# Canonical location: booster-shop-ops/AGENTS.md
# If you find another AGENTS.md elsewhere, ignore it — this file wins.

## Project
OpenCart e-commerce: boostershop.website (MTG, Pokemon, One Piece, Yu-Gi-Oh).
Stack: OpenCart (Twig/PHP), custom checkout + NP integration, Google Apps Script CRM, Google Sheets.

## Local paths (owner's machine)
- **Repo (local):** `E:\Personal Files\booster-shop-ops\` <- primary working folder
- **GitHub:** `https://github.com/Bazilik141/booster-shop-ops` (branch: master)
- **Dashboard (live):** `E:\Personal Files\booster-dashboard.html` — edit THIS file directly
- **Dashboard (git copy):** `dashboard/booster-dashboard.html` inside the repo — copy after edits, then commit
- **Dashboard URL:** `file:///E:/Personal%20Files/booster-dashboard.html`

Old paths retired — do not use:
- `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\`
- `E:\Program Files\...`

When Codex drops output files to the local machine, target:
`E:\Personal Files\booster-shop-ops\<subfolder>\<filename>`

## Repo structure
## Environment
- **Terminal (Claude Code CLI)** — installed. Use for: git operations, bash scripts, FTP deploy triggers.
- **VS Code (Claude Code extension)** — installed. Use for: viewing/editing repo files, inspecting diffs.

## Roles & boundaries
| Agent | Does | Does NOT |
|-------|------|----------|
| **Claude** | audit, SEO/UX strategy, handoffs, post-patch review, git diff | server access, deploy |
| **Codex** | patches (`patches/`), reports (`diagnostics/`), commit/push only when owner asks | server access, deploy, auto-commit/push |
| **Owner** | approves in chat, uploads + runs patch on server, triggers commits | — |

## Flow
## Source of truth
- **Notion roadmap** — task status, priorities
- **This repo** — implementation history, diffs, patch files
- **Owner cPanel backup drop** — live source for diagnosis (no server access)

## Commit / push policy
- **Do NOT commit or push unless owner explicitly asks.** Show `git diff` summary and wait.
- Risky tasks (checkout, payment, schema, DB, .htaccess): propose branch + PR, still wait.
- Commit message format: `Codex: <TASK-ID> <short description>`
- Do NOT commit: `.bak`, `.tar.gz`, `.zip`, `.log`, DB dumps, secrets/tokens.

## Patch conventions (PHP runner)
Each patch must:
1. **File exists check** — fail with clear error if target file not found; never blind-edit
2. **Anchor pre-check** — fail if anchor count != expected
3. **Backup** to `_patch_backups/<patch>-<ts>/` before write
4. **`php -l` gate** — restore-on-fail; no silent failures
5. **Idempotent marker** — `already_applied=yes` on repeat run
6. **DB changes** — only with explicit owner approval + rollback SQL in patch header
7. **Self-delete** after success

Naming: `patches/<TASK-ID>_<slug>_<YYYYMMDD>.php`
Drop to: `E:\Personal Files\booster-shop-ops\patches\<same filename>`

After patch is ready, respond with:
- what it does (1-2 sentences)
- local path to the file
- run command: `php <filename>` in `~/public_html`
- one terminal block with the command

## Diagnostics report
Required for: handoff tasks, risky zones, diagnostic investigations.
Not required for: simple cosmetic patches (report in chat is enough unless owner asks).
Template: `templates/codex-report-template.md`
Naming: `diagnostics/<TASK-ID>_<slug>_report_<YYYYMMDD>.md`

## Live source (diagnosis input)
Live state comes from owner cPanel backup drop.
- Always use the newest backup (check timestamp in filename)
- If a needed file is missing from backup, ask owner to run:
  `tar -czf booster-debug-files.tar.gz path/to/file1 path/to/file2`

## Risky zones — extra care + rollback + smoke test required
checkout · payment · Hutko · Checkbox · fiscalization · Nova Poshta · order status ·
Merchant feed · schema/JSON-LD · SEO (sitemap/robots/canonical/.htaccess) · CRM · DB

## Token and context efficiency
- For CRM and Google Sheets work, use the Apps Script API or narrow bounded ranges first. Request only the rows, columns, and cell fields needed for the current decision.
- Do not export or read an entire workbook, large sheet, repository, backup tree, or session log when a targeted read can answer the question.
- A full export or broad scan is allowed only when targeted reads cannot safely complete the task. Before starting one, tell the owner why it is required and that it may consume substantial weekly usage.
- Do not repeat unchanged reads or verify an established fact through multiple equivalent tools.
- Default verification budget for a scoped code change: one syntax/static check and one focused smoke-test pass. Add checks only for a new failure or a high-risk acceptance criterion.
- Keep tool responses small with exact ranges, finite search bounds, minimal field masks, and short result limits.
- Before structural edits to `Apps_Script_код`, read and preserve the complete affected function block. Avoid scattered row-index edits that can shift or split functions.
- If recovery requires repeated retries, broad exports, or large diagnostic output, pause and report the cause and expected usage cost before continuing.

## OpenCart SEO URL rules
- Format: `Pokemon-boosters-Set-Name`, `YuGiOh-boosters-Set-Name` (human-readable)
- Box/display → use `booster-box` in URL; single packs → `boosters`
- SKU/article goes ONLY into the SKU field, never into SEO URL

## Owner sync helpers
`bspush` / `bsmain` / `bsreview` — PowerShell commit/push helpers
