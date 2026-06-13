# CODEX_WORKFLOW.md — Booster Shop ops automation protocol

Purpose: Codex and Claude exchange work through THIS repo. Owner only approves
(yes / no / edits in chat) and runs patches on the server. Minimize round-trips.

## Roles & boundaries
- **Claude** — writes handoffs (`handoffs/`) and plans (`plans/`); reviews Codex
  patches from the local clone. NO server access, NO GitHub-network access
  (cannot push/pull/deploy; reads only the local working tree + dropped backups).
- **Codex** — implements patches (`patches/`) + execution reports (`diagnostics/`);
  pushes to GitHub; also drops a patch copy in `C:\Users\14bez\Downloads` for owner
  upload convenience. **No server access** (confirmed 2026-06-13: no SSH/FTP) — does
  NOT pull live source and does NOT deploy.
- **Owner** — approves in chat; runs `php <patch>.php` on the server (the only prod gate).

The repo is the shared bus. Owner runs `bs-autosync.ps1` so the local clone
auto-pulls; Claude then reads Codex output without manual git.

## Branch policy
- Default: commit patches + reports **directly to `master`** (no PR). Claude reviews
  the file before deploy — that review is the real gate, so a PR adds little.
- Big / high-risk tasks (checkout cutover, DB schema, url.php): use a branch + PR.

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
Notion roadmap = status & priorities. This repo = history + review evidence.
After a patch lands + QA passes, update the Notion task.
