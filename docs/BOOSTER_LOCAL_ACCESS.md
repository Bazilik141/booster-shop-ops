# Booster local access: reuse before setup

Owner direction: 2026-10-20. Applies to new and ongoing Booster chats on this
Windows PC. This is access routing, not a new deployment or roadmap authority.

## Start here in every new chat

1. Read `diagnostics/BOOSTER_access-current.json`. It contains sanitized technical
   readiness only, not credentials. Read its limits and pending action.
2. Reuse the existing user configuration. A new chat, working directory, review
   request or closed terminal is not a reason to create keys or run Claude login.
3. For ordinary non-GSS Claude exchanges, use
   `python scripts/booster_claude_access.py status` and its resolved native path.
   This read-only helper does not start a model request, login, SSH or key tools.
   Do not treat a missing PATH command as a missing installation or missing login.
4. Keep preparing authorized local work while owner-only SSH setup is pending.
   Do not ask the owner the same setup questions from several chats. The access
   maintainer owns the shared readiness record; other tasks may record their own
   bounded observations without becoming concurrent writers of the shared file.

Root AGENTS.md links this file near its beginning because its complete body can
exceed the default instruction-discovery size budget. Read this referenced file;
do not rely on the last page of a long instruction file being auto-injected.

## Claude Code subscription on this PC

Observed 2026-10-20: native `C:\Users\14bez\.local\bin\claude.exe`, version 2.1.303,
reports saved `claude.ai` subscription authentication in the default user config.
The command was not discoverable in the current Codex process PATH. The older
VS Code-bundled 2.1.300 executable is not the preferred entry for new tasks.

The helper prefers the installed standalone native executable and falls back to
an installed native Anthropic VS Code executable only if standalone is absent.
It records public binary path/version/SHA for the caller. It never installs or
updates software, reads credential files, edits accounts or performs login/logout.

Saved authentication belongs to the Windows user's default Claude CLI config,
not to each task. Do not set per-task `CLAUDE_CONFIG_DIR`, copy credential files,
invent a token refresher or change accounts to make a review run. A custom config
context requires assessment, not silently switching stores. Do not claim this
also establishes authentication for Claude chat, Desktop, cloud or another PC.

Use the helper's child-only environment filtering; do not change the owner's
environment. Keep subscription-only/no-API-source admission, restricted review
tools/MCP/hooks, overage refusal and actual request/result identity controls.
`--no-session-persistence` is not a demand to log in again for each review.
The helper is discovery/admission only, not a replacement for a reviewed exchange
adapter, request ledger or exact patch/command review.

### Native OAuth refresh and the execution boundary - correction 2026-10-22

Review status: present recovery is confirmed by the completed C2 review. The
separate final review of this routing documentation returned a weekly-limit
refusal before substantive evaluation. This delta is NOT_REVIEWED; retain the
pending gate and do not describe it as independently approved.

Read-only status discovery may run inside the workspace sandbox. Actual native
subscription review exchanges need normal owner-account access to Claude's own
profile so its built-in authentication can refresh and persist normally. Run only
the checked task-specific exchange adapter through an approved execution call
outside the workspace filesystem sandbox (`sandbox_permissions=require_escalated`
where supported). This means normal user rights, not Windows administrator rights.
Do not change global sandbox settings, broaden filesystem roots or install a
credential/token refresher. Approval is scoped to the exact checked exchange;
never use this route for arbitrary shell commands, tools or an unreviewed launcher.

Before that call, verify the installed CLI identity, adapter code/dependency pins,
subscription/no-API-source admission and complete declared review input set. Keep
Claude tools/MCP/hooks disabled, provider overrides filtered, custom auth stores
forbidden and paid overage refused. The agent never opens/prints/edits credential
contents; only native Claude performs its own built-in authentication. No SSH/key,
server, deployment, external business-data or GSS permission follows.

A workspace-sandbox401 does not establish a broken saved login. Preserve that
failed request and inspect the execution boundary first. Where a needed review
was already authorized, use one fresh request identity under the scoped launch
rule, not an automatic replay loop or a throwaway model probe. If approval is
unavailable, stop and explain that execution boundary; do not call it logout.
Only a real failure to authenticate/refresh in the proper native context, or
verified absence/revocation of saved login, justifies asking for owner login.

Observed recovery: failed C2 request0fda91b7-c2e8-4e44-a523-0317cf216d83 was
followed by requeste00b1d8d-92de-4423-9ca8-7b6548980952 outside that sandbox;
native credentials modification metadata advanced without an owner login.
The final model outcome is recorded in
`diagnostics/BOOSTER_claude-auth_report_20261022.md`; metadata alone is not proof
of model success. Do not claim permanent login or every historical401 explained.

If an interactive login is genuinely needed, prefer a fenced owner command using
`& (Join-Path $env:USERPROFILE '.local/bin/claude.exe') auth login --claudeai`.
This avoids the missing-separator typo before `.local`; never ask for tokens.

### When owner login is actually needed

- Never ask for login solely because a chat is new, PATH lookup fails, the quota
  is exhausted, a provider request is refused, or local execution is sandboxed.
- Read sanitized native status in the canonical default context first. A different
  config, unreadable status, CLI update or non-subscription credential is an
  assessment problem; do not label it logout.
- A verified absence of saved login in the default context, or a real expired/
  revoked-login error that cannot refresh, can require owner login. Record the
  actual evidence and provide ONE owner command for the resolved native binary's
  `auth login --claudeai`. No API/Console fallback, password or token in chat.
- Native status proves stored-login readiness, not future refresh or every model
  request. OAuth can genuinely expire or be revoked. Do not promise permanent
  login, retry a consumed review request, or erase the original failure.
- After owner renewal, use a fresh request identity and retained unchanged code/
  tests. Do not rebuild the task or collect source archives again just for login.

## Owner SSH: persistent setup and separate production permission

The expected client alias is `booster` in the owner's local `.ssh/config`.
Accepted owner receipt on 2026-10-20 confirms local setup completed, the existing
key is encrypted, and the missing config was created. Do not rerun local setup.
Accepted owner evidence on 2026-10-20 now records successful key-only login and
remote echo (`BOOSTER_SSH_OK`), followed by the separately reported `ssh_exit=0`.
Receipt: `diagnostics/BOOSTER_access_evidence_20261020/owner-first-connection-success-receipt.json`.
Reuse this setup across chats. Do not request first-connection setup or renewal
of the accepted initial trust merely because a new task starts.

The owner knowingly granted one initial first-use acceptance without independent
provider verification. That exception is now retired after the successful check;
its record remains `diagnostics/BOOSTER_access_evidence_20261020/owner-first-use-trust-exception_20261020.json`.
No provider-verified host identity or known_hosts persistence is claimed by the
two observations. Later owner packets keep `StrictHostKeyChecking yes`, preserve
known_hosts and refuse missing/changed trust. Any such failure needs targeted
assessment, never key deletion or an automatic renewed first-use exception.
No agent SSH/key access or production authority follows. Current readiness and
bounds are in `diagnostics/BOOSTER_access-current.json`.

Agents never inspect the owner's `.ssh`, private key or passphrase and never run
SSH/SCP/SFTP/key utilities or the owner-only diagnostic, even if called read-only.
Only the owner runs the reviewed local access check. It returns sanitized technical
facts so the owner need not understand or attest key internals from memory.
Unknown results stay unknown. Do not reset or generate keys to replace uncertainty.

The current default key is `~/.ssh/booster_deploy`; standard alternate key names
may be counted as metadata for diagnosis only. Finding another key does not grant
its use. Any expanded path/repair scope needs the applicable review and owner gate.
Do not automatically run the earlier alias writer: its encryption/agent/path
preconditions remain unresolved until evidence or a new reviewed disposition.

Once owner setup and its bounded results are accepted, reuse them across chats.
Do not regenerate a key, rewrite config, reauthorize it on hosting, reinstall SSH,
or repeat first-connection setup without a named relevant change/failure.
When a real patch needs the server, its own admitted upload/hash/runtime checks
still run; local setup readiness is not permission to deploy or proof of host state.

The owner keeps the existing passphrase-protected key; never request its secret
or remove on-disk encryption. New hosts still follow the default provider-check
rule; the retired first-use exception above is not permission for another bypass.

## Owner-controlled SSH access - decisions 2026-10-20 / 2026-10-22

The 2026-10-22 owner decision removes mandatory Lock after each task/work period.
The owner may unlock once and reuse the key across ordinary non-GSS tasks/chats.
Do not request a cleanup receipt as a completion gate. Lock remains an optional
owner action for explicitly ending access early. Windows-agent access remains
account/service-wide and available to other same-user processes; it is not
confined to one PowerShell window or chat.
Agent SSH/key/server access remains prohibited even while the owner has loaded
the key. Local command denials and project policy are not an OS isolation boundary.

Use reviewed `scripts/booster_ssh_session.ps1` actions only as the owner:

- `Setup`: one-time preparation of the existing built-in Windows agent service;
  an elevated owner console may be needed. Do not install software or load a key
  in a different/elevated user context.
- `Unlock`: normal owner console, existing named encrypted key and native local
  prompt; confirm the exact Booster identity loaded before using agent packets.
- `Lock` (optional): explicitly remove only that identity and confirm absence. Preserve
  other keys; never use a remove-all operation. Do not claim cleanup while the
  service is inaccessible or merely stopped.
- `Status`: bounded owner-local readiness only; never print other identities,
  key material, comments, account/config contents or the passphrase.

Lifetime decision confirmed 2026-10-22: the owner explicitly accepts persistent
availability, including across Windows sign-out/reboot, until optional owner
removal. This replaces the initial request for Windows-session-bound access.
No automatic expiry or session-end cleanup is required, installed or verified.
Do not introduce cleanup hooks or request another lifetime confirmation. Existing
loaded-key reports may be reused within their observation limits; do not force
repeated Unlock/Setup per chat. Persistence acceptance is not current key-state
proof; owner packets retain their existing fail-closed readiness checks.

The reviewed helper bytes and historical owner guide are preserved. Their older
"Always run Lock after work" instructions are superseded by this owner decision,
not a new executable implementation. No launcher, scheduled task, logoff hook,
service setting, key or SSH configuration was changed. Any new mechanism needs
local checks, integrated independent review and owner execution before use.
Native-agent persistence reference: [Win32-OpenSSH issue 1487](https://github.com/PowerShell/Win32-OpenSSH/issues/1487).

The alias remains unchanged with `IdentityAgent none` for manual mode. The new
reviewed ACC-009 agent packet is
`diagnostics/ACC-009_C1-H1_owner-agent-PS_20261020.ps1`; it overrides the built-in
agent endpoint and uses noninteractive key authentication, no forwarding and
strict known-host checks. It preserves the previously uploaded runner and saved
state: resume `Verify`; do not upload or rebuild sources just for this change.
The original manual packet stays unchanged as the fallback. Future ordinary
Booster packets should support this owner mode instead of forcing a passphrase
per subprocess. Never relax host checking, expose credentials or grant agents
server access to make it convenient. GSS keeps its accepted frozen transport.

Preparation/review is not an actual Setup/Unlock/Lock result. Consult shared
readiness for the released hashes, pending owner action and bounded receipts.
Complete expected receipts close mechanically; deviations get targeted assessment.

## Boundaries and sources

GSS keeps its accepted exact transport, freeze/no-replay controls and checkpoints.
Do not wire the new helper into a frozen GSS adapter, resume GSS or renew a grant.
No commit/push, external settings publication, Notion/dashboard write or server
operation is granted by this access cleanup.

Official references checked 2026-10-20:

- [OpenAI project instruction discovery](https://learn.chatgpt.com/docs/agent-configuration/agents-md)
- [Claude Code authentication and credential persistence](https://code.claude.com/docs/en/authentication)
- [Claude Code native auth-status command](https://code.claude.com/docs/en/cli-reference)
