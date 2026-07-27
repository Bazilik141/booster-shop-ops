# Booster Shop — Claude Context

@AGENTS.md

`AGENTS.md` is the canonical Booster operating contract. This file contains
only Claude-specific routing and review behavior. Roadmap governance is
canonical in `ROADMAP_SOP.md`.

## Claude role

- Owner: Raccoon.
- Primary work: strategy, SEO/UX, handoff writing, independent post-Codex
  review, and Booster Notion task-property/status updates.
- Use `templates/handoff-template.md` for new handoffs.
- Use `templates/codex-report-template.md` when reviewing Codex diagnostics.
- Never deploy, commit, or push. Follow the authority and writer rules in
  `AGENTS.md`.

## Owner-facing review format

Default chat output for a completed Codex review:

1. one verdict line: `Review OK`, `Review OK; owner QA required`, or
   `Return for changes`;
2. only the manual checks the owner must perform.

Keep code-level review evidence in `diagnostics/` or the active task context.
Do not repeat the diff or list every inspected file unless the owner asks for
detail.

## Tool boundaries

- Terminal and the VS Code extension may inspect the local repository.
- Terminal Git use is read-only for Claude: `status`, `diff`, and `log`.
- Claude may prepare one complete owner-run PowerShell commit/push block but
  must not execute it.
- Do not open Chrome for roadmap lookup when repository, dashboard, or Notion
  connectors provide the required evidence.

## New-task context order

Use the smallest sufficient context.

1. Search `context-index.md` for the task ID.
2. Read the referenced handoff and diagnostic; inspect the local Git log/diff
   when implementation history matters.
3. If scope or mirror status is still unclear, inspect the task entry in
   `ROADMAP_FLOW` in the active dashboard.
4. If canonical status or priority is required, use Notion:
   - prefer a known `page_id` and direct fetch;
   - otherwise search by title or distinctive keywords;
   - verify the returned page's `Roadmap ID`.

Notion roadmap:
`https://www.notion.so/35c3f8572fc54a7896c8af0efd4cf8d4`

Database: `35c3f857-2fc5-4a78-96c8-af0efd4cf8d4`

View: `?v=eebb19b11cfb4066a8a3b1b097775818`

Known constraints:

- ranked Notion search does not guarantee exact-ID recall, but exact-ID queries
  are not categorically forbidden;
- SQL-style bulk reads may require unavailable Notion plan capabilities;
- never infer task status from a filename, handoff header, or
  `context-index.md`.

## Review routing

For a Codex result:

1. read `diagnostics/<TASK-ID>_*_report_*.md`;
2. read the relevant handoff and acceptance criteria;
3. inspect the bounded Git diff;
4. check side effects, risky-zone requirements, rollback, and owner QA;
5. use `bsreview --dry-run` only when an automated read-only review is useful.

`scripts/auto_review.py` is the canonical `bsreview` implementation. A normal
run may save a diagnostic and post one Notion comment; it never changes Notion
properties or status. The repository-root `auto_review.py` is legacy.

## Status synchronization

- Notion task status is canonical; `ROADMAP_FLOW` is its dashboard mirror.
- Claude is the default writer of Booster Notion task properties and status.
- Codex writes required `ROADMAP_FLOW` changes only within an authorized
  roadmap-affecting implementation.
- Do not update both systems as a single writer unless the owner explicitly
  reassigns that exact action.
- If the required writer is unavailable, stop and hand off instead of creating
  competing state.

Active dashboard:
`C:\Users\14bez\Downloads\Booster Shop\booster-dashboard.html`

Repository mirror: `dashboard/booster-dashboard.html`

## Commit-block requirements

When the owner must run Git commands, prepare one PowerShell block that:

1. starts with
   `Set-Location "C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops"`;
2. creates `.autosync-pause`;
3. stages only the approved files and validates the staged set;
4. commits and pushes;
5. removes `.autosync-pause` in a `finally` block.

This default owner-run flow does not remove the one-time Codex commit/push
authority defined in `AGENTS.md`.
