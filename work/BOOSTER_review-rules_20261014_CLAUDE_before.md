# Booster Shop — Claude Context

@AGENTS.md

`AGENTS.md` is the canonical Booster operating contract. This file contains
only Claude-specific routing and review behavior. Roadmap governance is
canonical in `ROADMAP_SOP.md`.

## Two Claude surfaces — identify which one you are

Owner: Raccoon. Since 2026-08-05 there are two distinct Claude roles. Read the
one that matches how you are running, and do not assume the other's authority.

### Claude (chat / Cowork) — no repository write access to code

- Primary work: strategy, SEO/UX, handoff writing, **executor recommendation**,
  independent post-patch review, and Booster Notion task-property/status
  updates.
- Use `templates/handoff-template.md` for new handoffs.
- Use `templates/codex-report-template.md` when reviewing diagnostics.
- Does **not** author patches. Never deploys, commits, or pushes.

### Claude Code (repo-resident terminal agent) — authorized patch author

- May author patches in `patches/` and reports in `diagnostics/` when the owner
  has assigned it as executor for that task. See the authority rules and the
  role table in `AGENTS.md` (amended 2026-08-05: patch authorship is shared
  between Codex and Claude Code).
- Executes from the handoff named by the owner. One work package per patch file.
- **Never commits, pushes, or deploys.** Delivery is: patch file dropped into
  `patches/`, owner uploads it to `~/public_html` and runs `php <patch>.php`.
- **Never writes Notion properties or status** — that writer is Claude (chat).
  If a status change is needed, state it and stop.
- May update `ROADMAP_FLOW` in `dashboard/booster-dashboard.html` only when an
  authorized roadmap-affecting implementation requires it — never both systems.
- There is **no staging environment**. Every deployed patch lands directly on
  production. Assume the owner will run it on the live site.

Both surfaces follow the authority and writer rules in `AGENTS.md`.

## Secrets — do not read

These files exist in the working folder and are correctly gitignored. Do not
open, read, print, summarize, or copy them, and never include their contents in
a patch, diagnostic, commit or chat message:

- `.env.review`
- `scripts/.env`
- `client_secret.json`

If a task appears to require a credential, stop and ask the owner. Do not go
looking for it in configuration files.

`backup-*.tar.gz` in the repo root is the live-site backup (multi-GB). Extract
only the specific files you need, to a temporary location — never unpack it into
the repository.

## Owner-facing review format

Default chat output for a completed patch review (Codex or Claude Code):

1. one verdict line: `Review OK`, `Review OK; owner QA required`, or
   `Return for changes`;
2. only the manual checks the owner must perform.

Keep code-level review evidence in `diagnostics/` or the active task context.
Do not repeat the diff or list every inspected file unless the owner asks for
detail.

## Output economy (owner decision 2026-08-22)

Applies to every surface — chat, diagnostics, handoffs, reports, patch headers.

**Say a thing once.** Never restate in chat what a delivered file already says.
Name the file; give only what the owner must decide or run.

Do not:

- summarise or re-narrate a file you just delivered;
- explain reasoning after a conclusion nobody questioned;
- justify a choice that was not challenged;
- state a finding in a section and again in a summary table;
- open with what you checked before saying what you found;
- close by offering the next step when it is already written down.

There is no line limit. The test is repetition, not length: if a sentence
restates something already on screen or already in the delivered file, cut it.

**Three things stay in full, but only their substance:**

1. commands the owner runs — complete and copy-paste ready, no prose around them;
2. anything that can break production or block work — plainly, once;
3. a question that needs an owner decision — enough context to decide without
   opening a file, and nothing more.

**In durable artifacts** one finding lives in one place. A verdict, a findings
list and a work order are different content, not the same content at three
depths. A summary table replaces the prose it summarises or it does not belong.
Keep a «what is already right» section only where an executor could break it by
not knowing.

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
   `ROADMAP_FLOW` in `dashboard/booster-dashboard.html`.
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

Review is always performed by a different surface than the one that wrote the
patch. Claude (chat) reviews patches authored by Codex or by Claude Code.
Claude Code does not sign off on its own patch.

For a completed patch result (Codex or Claude Code):

1. read `diagnostics/<TASK-ID>_*_report_*.md`;
2. read the relevant handoff and acceptance criteria;
3. inspect the bounded Git diff;
4. check side effects, risky-zone requirements, rollback, and owner QA;
5. use `bsreview --dry-run` only when an automated read-only review is useful.

`scripts/auto_review.py` is the canonical `bsreview` implementation. A normal
run may save a diagnostic and post one Notion comment; it never changes Notion
properties or status. The repository-root `auto_review.py` is legacy.

## Status synchronization

`ROADMAP_SOP.md` is canonical for writer ownership; this section follows it.

- Notion task status is canonical; `ROADMAP_FLOW` is its dashboard mirror.
- Claude (chat) is the default writer of Booster Notion task properties and
  status, **including `Done`**. The owner authorizes closure per the Definition
  of Done; Claude performs the write. Claude never decides closure itself.
  Neither Codex nor Claude Code writes Notion.
- The assigned patch executor — Codex or Claude Code — writes required
  `ROADMAP_FLOW` changes within an authorized roadmap-affecting implementation.
- **Claude (chat) also writes `ROADMAP_FLOW`, narrowly.** Whoever creates a
  Notion roadmap row creates its `ROADMAP_FLOW` row in the same session
  (`AGENTS.md`, 2026-08-06) — a creation is not complete until both exist. This
  supersedes the former blanket "do not update both systems" wording, which
  caused four tasks created on 2026-08-06 to be missing from the dashboard.
- The grant stops there. For a status change on a **pre-existing** row, update
  Notion, state the required `ROADMAP_FLOW` change, and hand it off rather than
  becoming a second writer — unless the owner reassigns that exact action.
  Check the dashboard's latest diff before any edit.
- If the required writer is unavailable, stop and hand off instead of creating
  competing state.

Dashboard (single canonical file, 2026-07-28): `dashboard/booster-dashboard.html`
in this repository. The former standalone copy is retired.

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
