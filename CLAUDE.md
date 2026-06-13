# Booster Shop — Context for Claude & Codex

## Project
OpenCart-based e-commerce site for trading card games (MTG, Pokemon, One Piece, Yu-Gi-Oh).
URL: boostershop.website

## Repo structure
- `handoffs/` — task briefs and acceptance criteria from Claude
- `patches/` — PHP/JS/CSS patches, self-contained, run from ~/public_html
- `plans/` — roadmaps, audits, content plans
- `dashboard/` — local HTML dashboard (CRM view, no server-side)
- `diagnostics/` — read-only diagnostic outputs

## Source of truth
- **Notion roadmap** — task status, priorities, sprint planning
- **This repo** — implementation history, diffs, patch files
- **Hosting server** — live site code (OpenCart + custom modules)

## Stack
- OpenCart (twig templates, PHP controllers/models)
- Custom modules: checkout, Nova Poshta integration, stock management
- Google Apps Script API — CRM/orders backend
- Google Sheets — manual CRM (orders, stock, costs)

## Codex instructions
1. Before starting: `git status` — must be clean
2. Read the relevant handoff file in `handoffs/`; treat it as the scope boundary
3. Place output patch in `patches/` (naming: `TASK-ID_description_YYYYMMDD.php`) AND an identical uploadable copy in `C:\Users\14bez\Downloads`
4. Do NOT commit or push unless the owner explicitly asks; show `git diff` / a concise summary and wait for review
5. Do NOT modify files outside this repo structure
6. Do NOT commit .bak, .tar.gz, .zip, desktop.ini
7. No server access (no SSH/FTP): owner uploads + runs every patch; for current files use the newest backup or an archive command

## Working agreements (v2 — 2026-06-13)
- **Roles:** Claude = audit / diagnosis / handoffs / post-patch review (browser + backups; NO server, NO GitHub-network). Codex = implementation (patches), commits only when asked. Owner = uploads & runs patches on host, pushes commits.
- **Flow:** Claude handoff → Codex patch (`patches/` + Downloads) → Claude review → owner deploys (`php patch.php` in `~/public_html`) → owner QA.
- **Deploy is manual (owner).** Neither Claude nor Codex has server access. Live files come from the owner's cPanel backup drop in the Booster Shop folder (use the freshest; live > older backups).
- **Commits:** owner-initiated; no auto-commit/push. Risky changes → propose branch + PR, still wait for go.
- **Sync:** owner runs `bs-autosync.ps1` (auto-pull) + `bspush`/`bsmain`/`bsreview` PowerShell helpers. No GitHub connector required.
- **Source of truth:** Notion roadmap (status/priorities). Google Sheets roadmap = ARCHIVED. This repo = implementation history / review evidence.
- **Patch hygiene:** self-contained; backup before edit; `php -l` on modified PHP; anchor pre-check (no blind edits); idempotent marker; self-delete on success; DB only with explicit warning + rollback.
- **Risky zones (extra care + rollback + smoke):** checkout, payment, Hutko, Checkbox, fiscalization, Nova Poshta, order status, Merchant feed, schema, SEO, CRM, sitemap/robots/canonical/.htaccess.

## Key contacts
- Owner: Raccoon (14bezlikiy14@gmail.com)
- Claude handles: strategy, SEO, UX/UI, handoff writing, post-Codex review
- Codex handles: PHP/JS/CSS implementation from handoff specs
