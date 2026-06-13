# Booster Shop — Context for Claude & Codex

## Project
OpenCart-based e-commerce site for trading card games (MTG, Pokemon, One Piece, Yu-Gi-Oh).
URL: boostershop.com.ua

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
2. Read the relevant handoff file in `handoffs/`
3. Place output patch files in `patches/` with naming: `TASK-ID_description_YYYYMMDD.php`
4. After task: `git add . && git commit -m "Codex: TASK-ID description"`
5. Do NOT modify files outside this repo structure
6. Do NOT commit .bak, .tar.gz, .zip, desktop.ini

## Key contacts
- Owner: Raccoon (14bezlikiy14@gmail.com)
- Claude handles: strategy, SEO, UX/UI, handoff writing, post-Codex review
- Codex handles: PHP/JS/CSS implementation from handoff specs
