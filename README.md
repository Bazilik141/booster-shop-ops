# Booster Shop Ops

Private working repo for Booster Shop operational history: PHP patches, Claude/Codex handoffs, diagnostics, rollback notes, and owner-approved implementation records.

## Rules

- Do not commit hosting backups, secrets, DB dumps, raw customer data, browser profiles, or generated cache.
- Keep hosting-ready PHP patch files self-contained and runnable from `~/public_html`.
- Store current task status in Notion. This repo is history and review evidence, not the roadmap source of truth.
- Google Sheets roadmap remains archival unless the owner explicitly asks for a Sheets update.

## Suggested Layout

- `patches/` - final PHP patches after owner approval.
- `handoffs/` - Claude/Codex handoffs and acceptance criteria.
- `diagnostics/` - read-only diagnostic scripts and captured sanitized outputs.
- `templates/` - reusable Notion/Claude/Codex templates.

## GitHub Setup

After `gh auth login` succeeds, create a private remote:

```bash
gh repo create booster-shop-ops --private --source . --remote origin --push
```
