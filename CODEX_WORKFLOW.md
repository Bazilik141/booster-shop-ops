# CODEX_WORKFLOW.md — Booster Shop Implementation Protocol

Purpose: define how the owner, Claude, and Codex exchange bounded work through
the Booster repository. `AGENTS.md` remains the canonical authority and safety
contract; `ROADMAP_SOP.md` remains canonical for task-status governance.

## Current automatic review amendment — owner 2026-10-14

Read [the shared review rule](docs/BOOSTER_AUTOMATIC_PEER_REVIEW_20261014.md)
for every Booster task and [the GSS optimization rule](docs/GSS_OPTIMIZATION_RULES_20261014.md)
for GSS. Codex automatically requests actual Claude at a ready required stage
through the existing subscription CLI, captures the complete verdict and handles
scoped repairs. This replaces manual review-trigger wording, not task assignment,
Git/deployment/status authority or owner QA. GSS-specific authority overrides
legacy general roles here within its assigned scope.

## Roles in this workflow

- **Claude** writes handoffs and plans, reviews Codex results from the local
  clone, and owns Booster Notion task-property/status writes by default. Claude
  never commits, pushes, or deploys.
- **Codex** implements approved changes, creates patches and diagnostics, and
  owns required `ROADMAP_FLOW` edits in authorized roadmap-affecting work.
  Codex has no server access and never deploys.
- **Owner** approves scope, is the only production deployment gate, runs server
  patches, and performs final manual QA.

Codex may commit or push only after a direct, explicit owner request in the
active task for the exact scope. This is one-time authority and creates no
standing permission. Otherwise, complete implementation, required checks, automatic
independent review/scoped repairs and a concise diff summary; stop before Git writes.

## Work exchange

1. Claude writes a bounded handoff in `handoffs/`.
2. Codex reads the handoff, current evidence, and applicable project rules.
3. Codex implements only the approved scope.
4. Codex places self-contained runners in `patches/` and required reports in
   `diagnostics/`.
5. Codex automatically sends the ready stage and necessary context/evidence to
   actual Claude through the subscription CLI, records the complete independent
   verdict and resolves scoped findings. Owner QA remains its separate gate.
6. The owner uploads and runs any production patch and performs QA.
7. Claude updates Notion task status when closure is authorized; Codex updates
   `ROADMAP_FLOW` only when that action is part of its authorized scope.

The repository is the shared implementation bus. `bs-autosync.ps1` may update
the local clone when safe; it must not compete with active Git writes.

## Output locations

- Patch runner:
  `patches/<TASK-ID>_<slug>_<YYYYMMDD>.php`
- Diagnostic:
  `diagnostics/<TASK-ID>_<slug>_report_<YYYYMMDD>.md`
- Owner-upload convenience copy when required:
  `C:\Users\14bez\Downloads\<same patch filename>`

Do not create an upload copy when the task does not produce a server patch.

## Git boundary

- Do not commit or push without the exact authority defined in `AGENTS.md`.
- Before any authorized Git write, inspect the working tree and stop if
  unrelated changes overlap the approved files.
- Stage only approved paths and validate the staged file set.
- Use `.autosync-pause` around commit/push operations.
- Commit message format:
  `Codex: <TASK-ID> <short description>`
- For risky work, use a branch and pull request when appropriate.

## PHP runner requirements

Each server patch must be one self-contained runner executable from
`~/public_html` and must:

1. fail clearly when a target file is missing;
2. verify every edit anchor and safe-fail when its count is unexpected;
3. back up targets to `_patch_backups/<patch>-<timestamp>/` before writing;
4. run `php -l` and restore the backup on failure;
5. be idempotent and report `already_applied=yes` on repeat;
6. include rollback SQL for any explicitly authorized DB mutation;
7. self-delete after success.

### EOL and anchor safety

Preserve each target's existing line-ending style. Never rewrite an entire file
from CRLF to LF or LF to CRLF as an unrelated side effect.

Historical note: `RD-13.1J` normalized `checkout.twig` to LF. Anchors targeting
that file must account for its current LF form. An anchor mismatch must
safe-fail and trigger patch regeneration; it is not permission to edit blindly.

## Diagnostic requirements

Every handoff task, risky-zone change, or diagnostic investigation requires a
report containing:

- scope and deviations;
- files touched;
- dry-run or focused local-check result;
- `php -l` result when PHP is involved;
- idempotency evidence;
- rollback path;
- owner run command;
- post-deploy QA checklist;
- side effects and unresolved risks.

Use `templates/codex-report-template.md`.

## CRM structural-change guard

`OPS-CRMINTEGRITY` in `AGENTS.md` is mandatory for any main-CRM structural change, catalogue row
change, or formula-column edit. Run the dashboard's read-only CRM integrity check before and after,
record its bounded output in the diagnostic, and treat any newly introduced problem code as a defect.
Use `docs/CRM-new-SKU-runbook.md` for new SKU work; do not manually overwrite formula columns.

## Prohibited repository content

Never commit hosting backups, DB dumps, archives, `*.bak`, `*.log`, customer
data, payment identifiers, secrets, tokens, API keys, or credentials.

## Live-source boundary

Agents have no production server access. Diagnose live state from the newest
owner-supplied cPanel backup. If a required file or DB table is absent, stop and
ask the owner for a narrow fresh archive; do not infer the live implementation.

## Owner deployment

1. Owner uploads the reviewed patch to `~/public_html` through FTP or cPanel.
2. Owner runs `php <patch>.php` and checks for the documented success output.
3. Owner performs the handoff's smoke test and manual QA.
4. Deployment evidence is recorded separately from local/source validation.

Codex and Claude never deploy.

## Review automation

The current default is Codex-triggered actual Claude through the verified
subscription CLI; follow docs/BOOSTER_AUTOMATIC_PEER_REVIEW_20261014.md.

`bs-review.ps1` still invokes canonical `scripts/auto_review.py`, a LEGACY
paid-API/Haiku path. Even --dry-run makes an API call. It is not the approved
subscription adapter or a no-cost preflight. Normal mode can save a diagnostic
and post a Notion comment only under separate applicable authority; it never
changes a Notion property/status. Do not invoke the repository-root duplicate.
This policy update does not rewrite that program or install a general launcher.

## Status sources

- Notion roadmap: canonical task status and priority.
- `ROADMAP_FLOW`: dashboard mirror written by Codex only when authorized.
- Repository: implementation history and review evidence.
- `context-index.md`: task-to-evidence index without status.

See `ROADMAP_SOP.md` for lifecycle, synchronization, page IDs, and Definition
of Done.
