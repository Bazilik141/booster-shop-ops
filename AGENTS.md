# AGENTS.md — Booster Shop ops rules (Claude + Codex)

## Project
OpenCart e-commerce: boostershop.website (MTG, Pokemon, One Piece, Yu-Gi-Oh).
Stack: OpenCart (Twig/PHP), custom checkout + NP integration, Google Apps Script CRM, Google Sheets.

## Repo structure
```
handoffs/     task briefs (Claude → Codex scope boundary)
patches/      PHP/JS/CSS runners (Codex output)
plans/        roadmaps, audits, content plans
diagnostics/  post-patch reports (Codex output)
dashboard/    local CRM HTML
templates/    handoff + report templates
```

## Roles & boundaries
| Agent | Does | Does NOT |
|-------|------|----------|
| **Claude** | audit, SEO/UX strategy, handoffs, post-patch review | server access, push/pull, deploy |
| **Codex** | patches (`patches/`), reports (`diagnostics/`), git commit when asked | server access, deploy, auto-push without owner go |
| **Owner** | approves in chat, uploads + runs patch on server, pushes commits | — |

## Flow
```
Claude handoff → Codex patch (patches/ + C:\Users\14bez\Downloads copy)
→ Claude review (git diff) → Owner deploy (php patch.php in ~/public_html) → Owner QA
```

## Source of truth
- **Notion roadmap** — task status, priorities
- **This repo** — implementation history, diffs, patch files
- **Owner's cPanel backup drop** — live source for diagnosis (no server access)

## Commit / push policy
- **Do NOT commit or push unless owner explicitly asks.** Show `git diff` summary and wait.
- Risky tasks (checkout, payment, schema, DB, .htaccess): propose branch + PR, still wait.
- Commit message format: `Codex: <TASK-ID> <short description>`
- Do NOT commit: `.bak`, `.tar.gz`, `.zip`, `.log`, DB dumps, secrets/tokens.

## Patch conventions (PHP runner)
Each patch must:
1. anchor pre-check (fail if anchor count ≠ expected — no blind edits)
2. backup to `_patch_backups/<patch>-<ts>/` before write
3. `php -l` gate; restore-on-fail
4. idempotent marker (`already_applied=yes` on repeat run)
5. self-delete after success

Naming: `patches/<TASK-ID>_<slug>_<YYYYMMDD>.php`
Report: `diagnostics/<TASK-ID>_<slug>_report_<YYYYMMDD>.md` (use `templates/codex-report-template.md`)

## Risky zones — extra care + rollback + smoke test required
checkout · payment · Hutko · Checkbox · fiscalization · Nova Poshta · order status ·
Merchant feed · schema/JSON-LD · SEO (sitemap/robots/canonical/.htaccess) · CRM · DB

## OpenCart SEO URL rules
- Format: `Pokemon-boosters-Set-Name`, `YuGiOh-boosters-Set-Name` (human-readable)
- Box/display → use `booster-box` in URL; single packs → `boosters`
- SKU/article goes ONLY into the SKU field, never into SEO URL

## Owner sync helpers
`bs-autosync.ps1` — auto-pull on file change
`bspush` / `bsmain` / `bsreview` — PowerShell commit/push helpers
