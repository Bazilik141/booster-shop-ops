# AGENTS.md — Booster Shop ops rules (Claude + Codex)
# Canonical location: booster-shop-ops/AGENTS.md
# If you find another AGENTS.md elsewhere, ignore it — this file wins.

## Project
OpenCart e-commerce: boostershop.website (MTG, Pokemon, One Piece, Yu-Gi-Oh).
Stack: OpenCart (Twig/PHP), custom checkout + NP integration, Google Apps Script CRM, Google Sheets.

## Local paths (owner's machine)
- **Repo (local):** `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\` ← primary working folder
- **GitHub:** `https://github.com/Bazilik141/booster-shop-ops` (branch: master)
- **Dashboard (live):** `C:\Users\14bez\Downloads\Booster Shop\booster-dashboard.html` — edit THIS file directly
- **Dashboard (git copy):** `dashboard/booster-dashboard.html` inside the repo — copy after edits, then commit
- **Dashboard URL:** `file:///C:/Users/14bez/Downloads/Booster%20Shop/booster-dashboard.html`

Old paths retired — do not use:
- `E:\Personal Files\...`
- `E:\Program Files\...`

When Codex drops output files to the local machine, target:
`C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\<subfolder>\<filename>`

## Repo structure
```
handoffs/     task briefs (Claude → Codex scope boundary)
patches/      PHP/JS/CSS runners (Codex output)
plans/        roadmaps, audits, content plans
diagnostics/  post-patch reports (Codex output, risky/handoff tasks only)
dashboard/    git copy of booster-dashboard.html (version history only)
templates/    handoff + report templates
```

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
```
Claude handoff → Codex patch → drop to C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\
→ Claude review (git diff) → Owner deploy (php patch.php in ~/public_html) → Owner QA
```

## Source of truth
- **Notion roadmap** — task status, priorities (статус-канон). Дашборд `ROADMAP_FLOW` — дзеркало.
- **This repo** — implementation history, diffs, patch files
- **Owner cPanel backup drop** — live source for diagnosis (no server access)
- **Roadmap governance** (статус / синхронізація / DoD / ролі) → **`ROADMAP_SOP.md`** (канон).
  - Статус у Notion ставить Claude; Codex Notion НЕ чіпає. Codex оновлює `ROADMAP_FLOW` дашборда як останній крок roadmap-affecting патчу (Claude вписує це в handoff).

## Commit / push policy
- **Do NOT commit or push unless owner explicitly asks.** Show `git diff` summary and wait.
- Якщо owner просить закомітити: спершу `New-Item .autosync-pause` у корені (паузить autosync), після `push` — `Remove-Item .autosync-pause`. Деталі: `ROADMAP_SOP.md §4/§8`.
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
Drop to: `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\patches\<same filename>`

After patch is ready, respond with:
- what it does (1-2 sentences)
- local path to the file
- run command: `php <filename>` in `~/public_html`
- one terminal block with the command

## UI/CSS patch discipline
Applies to any patch touching visual/layout CSS, Twig markup styling, or JS that changes visible behavior.

1. **Name the root cause before patching.** State which existing rule/selector/line currently produces the bug (file + line if known, e.g. `boostershop-ds.css:3476`). If unknown, investigate first — do not guess-and-override.
2. **Check override history first.** Before touching a shared/theme selector (`boostershop-ds.css`, `stylesheet.css`, any DS token), `grep` `patches/` and the live file for prior patches touching that selector. State what you found in the patch description.
3. **`!important` / new override requires justification.** Adding `!important` or stacking a new override on existing CSS is allowed only when the patch description states why editing the source rule directly is unsafe or out of scope. No stated reason → do not add it silently.
4. **No easy justification → offer options, don't default.** If a clean fix (edit source rule, remove dead override, refactor selector) is possible but bigger than scope, present two options to the owner: (a) quick override — 1-line trade-off, (b) proper fix at the source — 1-line trade-off + blast radius. Wait for the owner's choice; do not silently pick the cheaper one.
5. **UI acceptance criteria cover more than token values.** For any DS/layout/component patch, verify at minimum: 3 breakpoints (not one mobile width only), real long-content edge cases, and interactive states (hover/focus/active) — not only computed hex/token values.
6. **Shared CSS files are a soft risky zone.** Edits to `boostershop-ds.css`, `stylesheet.css`, or any DS token file affect multiple pages at once — apply the same override-stacking caution as `Risky zones` below, even when no business logic is touched.
7. **Review must scan for these signatures.** Claude's `git diff` review must explicitly check for `!important`, `setTimeout`, `position:absolute/fixed`, and magic pixel values with no comment. Unexplained hits → send back before commit, do not approve silently.

## Diagnostics report
Required for: handoff tasks, risky zones, diagnostic investigations.
Not required for: simple cosmetic patches (report in chat is enough unless owner asks).
Template: `templates/codex-report-template.md`
Naming: `diagnostics/<TASK-ID>_<slug>_report_<YYYYMMDD>.md`

## Live source (diagnosis input)
Live state comes from owner's **cPanel backup drop**.
- Always use the **newest backup** (check timestamp in filename)
- If a needed file is missing from backup, ask owner to run:
  `tar -czf booster-debug-files.tar.gz path/to/file1 path/to/file2`

## Risky zones — extra care + rollback + smoke test required
checkout · payment · Hutko · Checkbox · fiscalization · Nova Poshta · order status ·
Merchant feed · schema/JSON-LD · SEO (sitemap/robots/canonical/.htaccess) · CRM · DB

## Token and context efficiency
- For CRM and Google Sheets work, use the Apps Script API or narrow bounded ranges first.
- Do not export or read an entire workbook, large sheet, or session log when a targeted read suffices.
- A full export is allowed only when targeted reads cannot safely complete the task — tell the owner first.
- Default verification budget: one syntax check + one smoke-test pass per scoped change.
- Before structural edits to `Apps_Script_код`, read and preserve the complete affected function block.

## OpenCart SEO URL rules
- Format: `Pokemon-boosters-Set-Name`, `YuGiOh-boosters-Set-Name` (human-readable)
- Box/display → use `booster-box` in URL; single packs → `boosters`
- SKU/article goes ONLY into the SKU field, never into SEO URL

## Owner sync helpers
`bspush` / `bsmain` / `bsreview` — PowerShell commit/push helpers
