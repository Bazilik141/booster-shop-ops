# CODEX_WORKFLOW.md — Booster Shop Implementation Protocol

Purpose: define how the owner, Claude, and Codex exchange bounded work through
the Booster repository. `AGENTS.md` remains the canonical authority and safety
contract; `ROADMAP_SOP.md` remains canonical for task-status governance.

## Current automatic review — owner decisions 2026-10-14 / 2026-10-19

Follow [the canonical review policy](docs/BOOSTER_AUTOMATIC_PEER_REVIEW_20261014.md).
Its non-GSS efficiency procedure applies to ongoing and new tasks. Use coherent
required stages; close complete expected host receipts mechanically and assess
deviations/ambiguity as specified there. No repeated owner go is needed for routine
assigned preparation. Owner scope, privacy, Git/status/deployment and QA authority
remain. GSS retains its prior shared and specific controls, including
[its optimization rules](docs/GSS_OPTIMIZATION_RULES_20261014.md).

## Roles in this workflow

- **Claude** writes handoffs and plans, reviews Codex results from the local
  clone, and owns Booster Notion task-property/status writes by default. Claude
  never commits, pushes, or deploys. Claude never invokes SSH/SCP/SFTP or key
  utilities and never reads the owner's SSH files or private key.
- **Codex** implements approved changes, creates patches and diagnostics, and
  owns required `ROADMAP_FLOW` edits in authorized roadmap-affecting work.
  Codex has no server access, never invokes SSH/SCP/SFTP or key utilities, never
  reads the owner's SSH files or private key, and never deploys.
- **Claude Code** follows the same SSH/key restrictions as Claude and Codex.
- **Owner** approves scope, is the only production deployment gate, runs server
  commands and patches, and performs final manual QA. Only the owner uses the
  owner-held SSH key.

An agent may use SSH/SCP only if the owner gives an exact, task-specific
exception in the active task. The exception must name the operation and target;
it never grants access to the owner's private key. Do not infer an exception
from an approved patch, a host receipt, or this workflow.

Codex may commit or push only after a direct, explicit owner request in the
active task for the exact scope. This is one-time authority and creates no
standing permission. Otherwise, complete implementation, required checks, automatic
independent review/scoped repairs and a concise diff summary; stop before Git writes.

## Work exchange

1. Claude writes a bounded handoff in `handoffs/`.
2. The assigned executor reads the handoff, current evidence and project rules; records the
   dependency/impact set and material unknowns in the current task report/ledger.
3. The assigned executor implements only the approved scope.
4. The assigned executor places self-contained runners in `patches/` and required reports in
   `diagnostics/`.
5. The executor supplies the patch and bounded target, receipt and rollback
   facts. Subscription Claude CLI composes the owner-run command packet; Codex checks it,
   including when Claude Code authored the patch.
6. Codex sends the coherent ready stage with both patch and packet SHA-256,
   context and real-versus-fake test boundaries to actual subscription Claude;
   records the verdict, resolves scoped findings and closes unchanged identities
   mechanically under the shared policy.
7. The owner applies the released patch and performs QA. Codex records the host
   receipt; a complete expected non-GSS result needs no separate model call.
   Deviations, ambiguity and explicit owner/GSS gates follow the shared policy.
8. Claude updates Notion task status when closure is authorized; Codex updates
   `ROADMAP_FLOW` only when that action is part of its authorized scope.

The repository is the shared implementation bus. `bs-autosync.ps1` may update
the local clone when safe; it must not compete with active Git writes.

## Output locations

- Patch runner:
  `patches/<TASK-ID>_<slug>_<YYYYMMDD>.php`
- Diagnostic:
  `diagnostics/<TASK-ID>_<slug>_report_<YYYYMMDD>.md`
- The repository patch is the upload source. Do not create a second copy in
  Downloads; record the exact local path and SHA-256 in the task report.

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

1. fail clearly when a target expected to exist is missing, and when a new-file
   target unexpectedly exists;
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
- files touched and the dependency/impact set;
- real native/generated versus fake test boundaries where relevant;
- dry-run or focused local-check result;
- `php -l` result when PHP is involved;
- idempotency evidence;
- rollback path;
- owner-run admission and command-packet facts (or the reviewed packet link
  after subscription Claude CLI composes it);
- post-deploy QA checklist;
- side effects and unresolved risks.

Use `templates/codex-report-template.md`. Keep current evidence/accepted identities,
delivered/HELD/open work, unknowns and one next action in one existing task report
or recovery ledger; do not create a separate file for each section. Historical
results remain evidence, not competing current pointers or external task status.

## CRM structural-change guard

`OPS-CRMINTEGRITY` in `AGENTS.md` is mandatory for any main-CRM structural change, catalogue row
change, or formula-column edit. Run the dashboard's read-only CRM integrity check before and after,
record its bounded output in the diagnostic, and treat any newly introduced problem code as a defect.
Use `docs/CRM-new-SKU-runbook.md` for new SKU work; do not manually overwrite formula columns.

## Prohibited repository content

Never commit hosting backups, DB dumps, archives, `*.bak`, `*.log`, customer
data, payment identifiers, secrets, tokens, API keys, or credentials.

## Live-source boundary

Agents do not access the production server. Diagnose from the newest
owner-supplied cPanel backup by default. For named missing or time-sensitive
files, the owner may provide a narrow fresh pull using the owner-run SSH/SCP
procedure below; record its file list, UTC time and SHA-256 manifest. Do not
infer live state from a stale backup or collect broad directories, credentials,
customer data or database dumps through SSH.

## Owner deployment (PowerShell + SSH/SCP)

First read `docs/BOOSTER_LOCAL_ACCESS.md` and the sanitized shared access record.
New chats reuse existing local setup. Do not reset keys or repeat Claude login
because a task/terminal is new. SSH setup readiness is separate from the owner's
per-patch deployment permission and host checks. Unknown local facts are gathered
by the reviewed owner-only diagnostic, never by agent `.ssh` access or by asking
the owner to understand key/agent internals. A real missing setup remains a gate;
do not mistake written instructions for a completed setup receipt.

### Authority and connection

- Owner-only production access. Agents never run `ssh`, `scp`, `sftp`, `ssh-add`
  or `ssh-keygen`, never read `~/.ssh` or request the key/passphrase, and never
  access the server. An exact owner exception is task-scoped and does not expose
  the owner's private key.
- The key stays on the owner's PC, is protected by a passphrase, and is used
  only by the owner. Do not store the key, passphrase, host address or port in
  the repository. The owner-provided cPanel account path in `AGENTS.md` and
  exact backup paths in receipts are accepted technical paths (2026-10-19).
- If the existing owner key lacks a passphrase, only the owner changes it in
  PowerShell with `ssh-keygen -p -f "$env:USERPROFILE\.ssh\booster_deploy"`.
  The owner enters the old and new passphrases at the prompts; agents never
  request, enter or inspect them.
- **Owner-controlled work periods (2026-10-20):** for ordinary non-GSS tasks the
  owner may unlock the existing encrypted Booster key in the Windows `ssh-agent`
  before work and explicitly remove only that key after work, using the reviewed
  `scripts/booster_ssh_session.ps1` helper. The owner may prepare/start the existing
  Windows service once; key actions run in the normal owner context. No agent
  performs service/key/SSH operations or reads `.ssh`.
- The agent is Windows-account/service-wide, not a PowerShell/Codex-chat session.
  Closing PowerShell, stopping the service or rebooting is not cleanup proof.
  A loaded key can be used by other same-user processes. Keep on-disk encryption,
  no agent forwarding, no automatic key addition and strict stored-host checking.
  Do not promise a time limit unsupported by the installed Windows agent.
- Work ends only after the helper confirms the Booster identity absent. Removal
  is targeted: never clear all identities, stop other users' work, edit the agent
  registry, delete keys or reset the alias. Inaccessible service is not verified
  cleanup; keep a failure unresolved until an exact removal/absence result.
- The alias below stays compatible with manual passphrase use. A reviewed agent
  packet explicitly overrides `IdentityAgent none` for the built-in Windows agent
  and disables interactive fallback with `BatchMode yes`. Old reviewed packets
  remain valid for manual mode; do not silently edit their frozen bytes.
  GSS transport and its prior controls are unchanged by this ordinary-task mode.
- The current PC's ignored `.claude/settings.local.json` denies Claude Code
  SSH/SCP/key commands and reads of `~/.ssh`. Replicate these deny rules on any
  other owner PC before using Claude Code there; this local file is not synced
  by Git. Codex remains bound by `AGENTS.md` and its own sandbox/approval rules.
- The owner obtains the server host-key fingerprint from the hosting provider
  through a separate trusted channel. The alias below defaults to strict checking;
  for the first connection only, override it with this command:
  `ssh -o StrictHostKeyChecking=ask booster`. Compare the fingerprint displayed
  by OpenSSH with the provider's value for the same key type and SHA256 format
  before typing `yes`. If they differ, cancel
  and contact hosting. OpenSSH then stores the verified host in `known_hosts`;
  later connections use `StrictHostKeyChecking yes`. Never accept an unknown key
  blindly or bypass host-key checking.
- Owner's local `~/.ssh/config` uses the alias `booster` and private values:

  ```sshconfig
  Host booster
    HostName <hosting server address>
    Port <SSH port>
    User <hosting account>
    IdentityFile ~/.ssh/booster_deploy
    IdentitiesOnly yes
    AddKeysToAgent no
    IdentityAgent none
    StrictHostKeyChecking yes
  ```

- One-time owner setup creates private staging directories outside the web
  root, then compares their resolved path with `~/public_html` before use:
  `~/private/booster-patches/` and `~/private/booster-pulls/`. If the host
  cannot provide a verified location outside the web root, stop and ask the
  owner/host for one; do not upload patches or archives to `public_html`.
  The owner can run this one-time setup block after connecting:

  ```powershell
  ssh booster 'umask 077 && mkdir -p ~/private/booster-patches ~/private/booster-pulls && chmod 700 ~/private/booster-patches ~/private/booster-pulls && cd ~/private/booster-patches && pwd -P && cd ~/public_html && pwd -P'
  ```

  The first printed path must be outside the second. If it is not, stop and
  ask the hosting provider for a private staging path.

### Patch packet and owner commands

Before any deployment, the executor's report supplies these facts to actual
Claude through the installed subscription CLI, which composes the owner-run
PowerShell packet. The owner's 2026-10-19 decision replaces the former
Claude-chat author requirement; no owner relay between chats is required:

1. The patch's exact local path and SHA-256. The review record binds both this
   patch hash and the SHA-256 of the final command packet.
2. Confirmation that the patch runs with `~/public_html` as its working
   directory, even though the patch file is stored in the private staging
   directory. It must not derive target paths from the patch's `__DIR__`.
3. For every target: expected `sha256_before`, backup path from the run receipt,
   expected `sha256_after`, and rollback facts. Use literal `absent` for a file
   that does not exist in a state and `backup=none` for a newly created file.
   Use the exact backup path printed by the receipt; never select a backup by
   glob or timestamp sorting.
4. The expected stdout contract: one line per target with `target=`,
   `sha256_before=`, full `backup=` path or `none`, syntax-gate result, `sha256_after=`,
   `changed_file(s)=`, then `done=ok`. No-op is `already_applied=yes`;
   failure is `error=<reason>` and a non-zero exit code.
5. Whether the patch changes the database or clears cache, and the precise
   permission, backup and rollback requirements for those actions.
6. The task's post-run checks and owner QA checklist.

Subscription Claude CLI composes one complete PowerShell packet from the executor's facts
when deployment is prepared. Codex independently checks this Claude-authored
packet; its author cannot be the sole approver of its own commands. Command
drafting and ready-stage review are separate CLI requests, with Codex's independent
command check recorded between them. The required actual Claude
subscription review covers the patch and packet together at one ready stage.
The review record names the exact patch SHA-256 and command-packet SHA-256.
The packet may read dynamic receipt values as validated data; no one hand-edits
its executable commands after review. The owner runs its separate blocks. A
normal, complete receipt closes mechanically only while both reviewed hashes
remain unchanged. A substantive patch/command, scope, permission or privacy
change, or an unexplained result uses the applicable fresh review in the shared
policy. An older `Review OK` under the public_html-upload model is insufficient:
before using a previously reviewed patch, confirm in the same packet review
that its target paths, backups and created files do not depend on `__DIR__`,
`chdir(__DIR__)` or the old working directory.

The owner-confirmed live `DIR_STORAGE` is recorded in `AGENTS.md`. Before a
later task clears cache, the report includes the current owning application,
`DIR_CACHE` and exact cleanup directory. The owner checks those runtime values
in the same non-interactive SSH shell mode as execution. The task packet
substitutes the PHP executable path resolved during verification and selects
`config.php` for catalog targets or `admin/config.php` for admin targets:

```sh
cd ~/public_html && <verified-php-path> -r 'require $argv[1]; foreach (["DIR_STORAGE", "DIR_CACHE", "DIR_MODIFICATION"] as $name) { echo $name, "=", defined($name) ? constant($name) : "UNDEFINED", PHP_EOL; }' <config.php|admin/config.php>
```

The angle-bracket fields are packet placeholders, not a command to copy as-is.
Print only these constants, never config contents. If a required constant is
`UNDEFINED` or conflicts with task facts, stop. Verify the template-cache
directory under the reported `DIR_CACHE` before clearing it; never guess a
hashed Twig filename. Use the same PHP executable for this lookup, `php -v`,
`php -l` and the runner. Do not assume cPanel Terminal has the same PATH.

Never clear `system/storage/...` or infer the current path from an old backup.

The owner runs each block separately and stops at any mismatch:

1. **Upload:** `scp` the reviewed file from the repository's `patches/` folder
   to `booster:private/booster-patches/`. Never put the patch in `public_html`.
2. **Verify:** compare the local `Get-FileHash -Algorithm SHA256` value with
   remote `sha256sum` case-insensitively; resolve the remote PHP path, then run
   that PHP executable's `-v` and `-l` on the staged file.
   Continue only when the patch hash matches the reviewed hash and PHP is 8.0.x.
3. **Run:** use `cd ~/public_html && <verified-php-path>
   ~/private/booster-patches/<patch>` in the same SSH shell mode.
   Upload, verification and execution are separate owner actions; do not chain
   them into one command. When required, chain cache cleanup to successful
   execution within the run block using `&&`.
4. **Check:** compare the complete stdout with the report, verify the patch
   self-deleted from private staging, perform the task checks and Tier 1 smoke
   URLs, then perform manual QA.
5. **Rollback:** use the task's reviewed rollback block and exact backup path
   from the receipt. Before any rollback write, classify every target against
   the recorded before/after states and verify every backup needed for restore
   against its before-hash: current equals after -> restore the checked backup
   (or delete a created file); current equals before -> skip; anything else ->
   stop the whole rollback, non-zero. `absent` is a valid current, before or
   after state. Thus an `error=` after partial application does not block
   rollback of targets that actually changed.
   Restore existing-file permissions and ownership with its backup. For a
   restored or deleted Twig template or OCMOD source, the reviewed rollback
   block then clears the verified `DIR_CACHE` template-cache directory recorded
   in the task facts, never a guessed filename. The owner refreshes OpenCart
   Modifications in the admin UI as a separate step where applicable, then
   checks that the prior behavior is restored. Do not
   restore an older mtime that can leave a newer compiled template cache active.
   File rollback does not reverse database changes; those need the approved
   rollback SQL.

Do not run database clients or open DB tunnels over SSH: no `mysql`,
`mysqldump`, direct SQL sessions, or database-port forwarding. Direct database
access is outside this SSH workflow. Reviewed PHP runners may read bounded,
allowlisted aggregates and non-secret technical metadata (for example schema
or column existence or a named non-secret setting), never credentials or
customer records. They may change the database only with explicit owner
approval, a separate cPanel DB backup and rollback SQL under the patch rules.

### Narrow file pulls

- The owner may make a named, read-only pull to refresh missing or time-sensitive
  source files. The executor specifies the exact files; never include
  `config.php`, `admin/config.php`, `.env*`, keys, credentials, customer data,
  payment identifiers or database dumps. Exclude `DIR_STORAGE` session, logs,
  upload, download and backup directories. Pull regular files only, not symlinks.
- The executor lists the exact files and purpose. Subscription Claude CLI writes the
  owner-run pull block; Codex checks its file list and privacy exclusions
  before the owner runs it. This applies even when Claude Code is executor.
- Write each archive and manifest to a task-unique filename under
  `~/private/booster-pulls/`, outside the web root. The manifest includes the
  exact file list, each file's SHA-256, UTC collection time and archive SHA-256.
- The owner downloads via `scp`, verifies the archive hash against the manifest,
  and only then deletes this task's exact archive and manifest under
  `~/private/booster-pulls/`. Never delete or modify the pulled live source
  files. No shared temporary names such as `collect.tar.gz`; no wildcard cleanup.
- A narrow fresh pull supplements the newest cPanel backup for those named files;
  it does not authorize live reads by an agent or any DB operation.

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
