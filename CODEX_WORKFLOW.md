# CODEX_WORKFLOW.md — Booster Shop ops automation protocol

Purpose: Codex and Claude exchange work through THIS repo. Owner only approves
(yes / no / edits in chat) and runs patches on the server. Minimize round-trips.

## Roles & boundaries
- **Claude** — writes handoffs (`handoffs/`) and plans (`plans/`); reviews Codex
  patches from the local clone. NO server access, NO GitHub-network access
  (cannot push/pull/deploy; reads only the local working tree + dropped backups).
- **Codex** — implements patches (`patches/`) + execution reports (`diagnostics/`);
  drops a patch copy in `C:\Users\14bez\Downloads` for owner upload convenience;
  commits/pushes **only when owner explicitly asks**. **No server access** (confirmed
  2026-06-13: no SSH/FTP) — does NOT pull live source and does NOT deploy.
- **Owner** — approves in chat; runs `php <patch>.php` on the server (the only prod gate).

The repo is the shared bus. Owner runs `bs-autosync.ps1` so the local clone
auto-pulls; Claude then reads Codex output without manual git.

## Branch / commit policy
- Codex creates patch files in `patches/` (+ identical uploadable copy in `C:\Users\14bez\Downloads`) and reports in `diagnostics/`.
- **Do NOT commit or push unless the owner explicitly asks.** Show `git diff` / a concise
  summary and wait for Claude review + owner go. The owner performs the commit/push.
- Якщо owner просить закомітити саме Codex: спершу `New-Item .autosync-pause` (паузить autosync), після `push` — `Remove-Item .autosync-pause` (`ROADMAP_SOP.md §4/§8`).
- Risky tasks (checkout, payment, schema, DB, url.php cutover): propose a branch + PR, still wait for go.

## Patch conventions (PHP runner)
Each patch = one self-contained runner in `patches/`, runnable from `~/public_html`:
- anchor pre-check (fail if anchor count != expected — never blind-edit),
- backup to `_patch_backups/<patch>-<ts>/` before write,
- `php -l` gate, restore-on-fail,
- idempotent marker (`already_applied=yes` on repeat run),
- self-delete after success.
- Naming: `patches/<taskid>_<slug>_<YYYYMMDD>.php`
- Report:  `diagnostics/<taskid>_<slug>_report_<YYYYMMDD>.md`
- Commit:  `Codex: <TASK-ID> <short description>`
- EOL / anchors: RD-13.1J нормалізував увесь `checkout.twig` до LF (CRLF→LF при читанні).
  Правило надалі: (а) новий патч НЕ повинен мовчки міняти line endings всього файлу —
  зберігай стиль EOL цілі (як робив ST-2b6e); (б) анкери для файлів, які вже проходили
  через раннери, підбирати з урахуванням можливого LF — при mismatch анкер просто
  впаде (safe-fail), це не пошкодження, але вимагає перегенерації патчу;
  (в) `checkout.twig` станом на 2026-07-13 (після RD-13.1J) — LF.

## Report must contain
scope · files touched · dry-run result · `php -l` · idempotency · rollback ·
run command · post-deploy QA checklist.

## Do NOT commit
hosting backups, DB dumps, `*.tar.gz` `*.zip` `*.bak` `*.log`, customer data,
secrets / tokens / API keys. (Most already in `.gitignore`.)

## Live source (diagnosis input)
Codex has no server access, so live state comes from owner's **cPanel backup drop**
into the `Booster Shop` folder (files + DB dump). Claude reads source/config from the
latest backup. Keep dropping a fresh backup before deep checkout/DB diagnosis.

## Deploy (owner, manual)
1. Owner uploads the patch (`patches/<patch>.php`, or its copy in `Downloads`) to
   `~/public_html` via FTP / cPanel File Manager.
2. Owner runs `php <patch>.php`, watches for `done=ok`, then runs QA.
Codex never deploys.

## Source of truth
Notion roadmap = status & priorities (канон). Дашборд `ROADMAP_FLOW` = дзеркало. This repo = history + review evidence.
Governance / синхронізація / DoD / ролі → **`ROADMAP_SOP.md`**.
**Закриття задачі:** статус у Notion ставить Claude (Codex Notion НЕ чіпає); Codex оновлює `ROADMAP_FLOW` дашборда як останній крок roadmap-affecting патчу. Owner каже, кому закривати — той робить обидва місця.
