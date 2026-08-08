# AGENTS.md — Booster Shop ops rules (Claude + Codex)
# Canonical location: booster-shop-ops/AGENTS.md
# If you find another AGENTS.md elsewhere, ignore it — this file wins.

## Project
OpenCart e-commerce: boostershop.website (MTG, Pokemon, One Piece, Yu-Gi-Oh).
Stack: OpenCart (Twig/PHP), custom checkout + NP integration, Google Apps Script CRM, Google Sheets.

## Core authority and writer rules

- Claude never commits or pushes. Claude Code never commits, pushes, or deploys.
- **Patch authorship is shared (2026-08-05, owner decision).** Patches in
  `patches/` may be authored by **Codex or Claude Code**. The owner assigns the
  executor per task. Two agents must never author patches for the same task in
  the same round — that is a parallel-writer violation.
- **Executor recommendation is mandatory.** Before or while preparing a handoff,
  Claude states a recommended executor, model and thinking depth (see
  "Executor, model and effort recommendation"). The owner decides; Claude then
  writes the handoff addressed to the chosen executor. Claude does not assign
  the executor itself.
- Codex may commit or push only after a direct, explicit owner request in the
  active task and only for the exact approved scope. This grants no standing
  permission. Otherwise Codex prepares changes, checks, and a concise diff
  summary only.
- The owner is the only production deployment gate and performs final manual
  QA.
- Claude is the sole default writer of Booster Notion task properties and
  statuses. Neither Codex nor Claude Code changes Notion properties or statuses.
- The assigned patch executor owns `ROADMAP_FLOW` changes required by an
  authorized roadmap-affecting implementation. Exceptions require explicit owner
  reassignment; agents must not compete as parallel status writers.
- **New-task mirroring (2026-08-06, owner decision).** Creating a task in the
  Notion roadmap is not an "implementation", so under the previous wording a new
  row had no defined path into the dashboard mirror and silently never appeared.
  Discovered when four tasks created on 2026-08-06 were missing from the
  dashboard. Therefore: **whoever creates a Notion roadmap row also creates its
  `ROADMAP_FLOW` row in the same session** — in practice Claude (chat).
  This grant is narrow. Claude (chat) writes dashboard rows **only for tasks it
  just created**. Status and progress updates on pre-existing rows remain the
  executor's, unchanged. If one session needs both a creation and a status change
  to an existing row, do the creation and hand off the status change rather than
  becoming a second writer.
- `scripts/auto_review.py` is the canonical implementation behind
  `bs-review.ps1` / `bsreview`. The repository-root `auto_review.py` is a legacy
  duplicate and must not be invoked.
- Use `bsreview --dry-run` for a read-only automated review. A normal
  `bsreview` may save a diagnostic and post a Notion comment, but it must never
  change a Notion property or status.
- Notion search is ranked content search. Prefer a known page ID and direct
  fetch; otherwise search by title or distinctive keywords and verify the
  returned page's Roadmap ID. Do not claim that an exact ID can never match.

## Local paths (owner's machine)
- **Repo (local):** `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\` ← primary working folder
- **GitHub:** `https://github.com/Bazilik141/booster-shop-ops` (branch: master)
- **Dashboard (single canonical file, 2026-07-28):** `dashboard/booster-dashboard.html` inside the repo — edit THIS file directly, commit as usual. The former standalone copy outside the repo is retired; do not recreate it.
- **Dashboard URL:** `file:///C:/Users/14bez/Downloads/Booster%20Shop/booster-shop-ops/dashboard/booster-dashboard.html`

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
dashboard/    canonical booster-dashboard.html (single file, edited directly)
templates/    handoff + report templates
```

## Environment
- **Terminal (Claude Code CLI)** — installed. Claude may use it for read-only
  `git diff`/`status`/`log` and shell diagnostics. Claude must never run
  `git commit` or `git push`; it prepares a complete owner-run command block.
- **VS Code (Claude Code extension)** — installed. Use for: viewing/editing repo files, inspecting diffs.

## Roles & boundaries
| Agent | Does | Does NOT |
|-------|------|----------|
| **Claude** (chat/Cowork) | audit, SEO/UX strategy, handoffs, executor recommendation, post-patch review, git diff, Notion status, prepares ready-to-paste commit/push command | write patches, server access, deploy, git commit/push |
| **Codex** | patches (`patches/`), reports (`diagnostics/`), authorized `ROADMAP_FLOW` changes | server access, deploy, Notion properties/status, commit/push without exact active-task approval |
| **Claude Code** (repo agent) | patches (`patches/`), reports (`diagnostics/`), local verification, authorized `ROADMAP_FLOW` changes | server access, deploy, Notion properties/status, commit, push |
| **Owner** | approves scope, assigns the executor, normally runs prepared Git commands, uploads and runs server patches, performs final QA | — |

Only one patch author per task per round. If the owner reassigns mid-task, the
previous executor stops before the new one starts.

## Flow
```
Claude executor recommendation → Owner assigns executor → Claude handoff
→ Codex OR Claude Code patch → drop to C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\patches\
→ Claude review (git diff) → Owner deploy (php patch.php in ~/public_html) → Owner QA
```

## Source of truth
- **Notion roadmap** — canonical task status and priorities.
- **Dashboard `ROADMAP_FLOW`** — mirror of Notion task status.
- **This repo** — implementation history, diffs, patch files
- **Owner cPanel backup drop** — live source for diagnosis (no server access)
- **Roadmap governance** (status, synchronization, Definition of Done, and
  writer roles) — `ROADMAP_SOP.md`.
- Claude writes Booster Notion task properties/status by default. Codex does
  not write Notion properties/status and updates `ROADMAP_FLOW` only when an
  authorized roadmap-affecting implementation requires it.

## Commit / push policy
- Apply the authority rules above. Without exact active-task commit/push
  authorization, show a concise diff summary and prepare one complete owner-run
  PowerShell block: enter the exact repo root, create `.autosync-pause`, stage
  only approved files, validate the staged set, commit/push, and remove the
  sentinel.
- For risky tasks (checkout, payment, schema, DB, `.htaccess`), propose a branch
  and pull request when appropriate.
- Commit message format: `Codex: <TASK-ID> <short description>`
- Do NOT include in the command: `.bak`, `.tar.gz`, `.zip`, `.log`, DB dumps, secrets/tokens.

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

## Apps Script mirrors (OPS-CODEMIRROR, owner decision 2026-08-08)
Both Apps Script projects are mirrored in the repository so an executor reads real code instead
of guessing a deployed version. This rule exists because three consecutive `3D-P-010` attempts
were planned against an assumed script version.

- **Main CRM:** `crm/apps-script/Code.gs` — state recorded in `crm/apps-script/SOURCE_STATE.md`
- **3D-P:** `3d-print/apps-script-3dp-api/Code.gs`

Rules:
1. Any task that reads, plans against, or patches either script **checks the pull date in
   `SOURCE_STATE.md` first**. If the mirror is older than the change being planned, request a
   fresh owner export before writing a handoff.
2. Whoever changes a live script refreshes the mirror **in the same session**, including the pull
   date and the deployed version.
3. Source is not deployment. Editing a script does not update the published Web App; never infer
   a deployed version number from source alone.
4. A mirror must never contain tokens. Both projects keep secrets in Script Properties; if a token
   appears in an export, stop and tell the owner rather than committing it.

## Risky zones — extra care + rollback + smoke test required
checkout · payment · Hutko · Checkbox · fiscalization · Nova Poshta · order status ·
Merchant feed · schema/JSON-LD · SEO (sitemap/robots/canonical/.htaccess) · CRM · DB

## Executor, model and effort recommendation

Every handoff carries an executor line immediately after its date:

`Executor: <Codex|Claude Code> · model=<...> · effort/thinking=<...>` — plus one
sentence of justification.

Claude proposes; the owner decides. If the owner overrides the recommendation,
Claude records the override in the handoff without arguing it again.

### Which executor

| Signal | Prefer |
|---|---|
| Task needs live-file discovery across an unfamiliar tree, or heavy local verification (build, test, image processing, measurement) | Claude Code |
| Task is a well-bounded patch against files already identified in the handoff | either — pick by remaining weekly quota |
| Task is long-running, multi-round, and mostly mechanical once specified | Codex |
| The other executor already worked this task this round | keep the same executor — never swap mid-round |

Weekly quota is a legitimate tie-breaker. State it explicitly when it is the
deciding factor, so the choice stays auditable.

### Codex model + effort

| Task type | Model | Effort |
|---|---|---|
| Risky-zone, multi-file, or architecturally ambiguous work | Sol | xhigh |
| Typical feature, bug fix, or tests | Terra | medium; high when multi-step |
| Mechanical copy, formatting, or small CSS/text change | Luna | low |

Use `ultra` only when the task clearly splits into independent parallel work;
it is not the default.

Source: OpenAI GPT-5.6 model guide, July 2026.

### Claude Code model + thinking depth

| Task type | Model | Thinking |
|---|---|---|
| Risky-zone, multi-file, or architecturally ambiguous work | Opus | high |
| Typical feature, bug fix, or tests | Sonnet | medium; high when multi-step |
| Mechanical copy, formatting, or small CSS/text change | Haiku | low |

Do not run risky-zone work on a small model. A weak model on a risky zone is
how unrelated code gets overwritten — this has already happened once on CRM and
3D-table work (owner report, 2026-08-05).

## Token and context efficiency
- For CRM and Google Sheets work, use the Apps Script API or narrow bounded ranges first.
- Do not export or read an entire workbook, large sheet, or session log when a targeted read suffices.
- A full export is allowed only when targeted reads cannot safely complete the task — tell the owner first.
- Default verification budget: one syntax check + one smoke-test pass per scoped change.
- Before structural edits to Apps Script source, read and preserve the complete
  affected function block.
- **3D-Print Sheet (once `3D-P-008` ships):** use the dedicated `BOOSTER_3DP_TOKEN` Apps Script API for any
  read or write against the 3D-P workbook (`3d-print/3D-P_nomenclature-tracker_*.xlsx` /
  `docs.google.com/spreadsheets/d/1yp15H3YJGkqI4Rx89G4QZHkD9m67gnWh58TsTTi-jjo`) — `3dp_get_row`,
  `3dp_get_range`, `3dp_overview`/`3dp_skus`/`3dp_sales`/`3dp_plyushky`/`3dp_payouts` for reads,
  `3dp_write`/`3dp_append_row` for scoped writes (manual-input cells only, always logged to `_Аудит_API`).
  Do not use a Drive full-document read (`read_file_content` or equivalent) on this workbook except for a
  one-off human-readable audit — it burns tokens re-reading the whole Легенда/Аналітика prose for what a
  narrow API call answers directly, and it cannot write. Until `3D-P-008` ships, a narrow Drive read (specific
  known cells, not the whole doc) is an acceptable fallback — never write to the Sheet by any means other than
  the API once it exists.

## OpenCart SEO URL rules
- Format: `Pokemon-boosters-Set-Name`, `YuGiOh-boosters-Set-Name` (human-readable)
- Box/display → use `booster-box` in URL; single packs → `boosters`
- SKU/article goes ONLY into the SKU field, never into SEO URL

## Owner sync helpers
`bspush` / `bsmain` / `bsreview` — PowerShell commit/push helpers
