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

## Owner-controlled SSH work periods - decision 2026-10-20

The owner explicitly approved unlock-before-work / remove-after-work mode after
being told that Windows-agent access is account/service-wide and available to
other same-user processes. It is not confined to one PowerShell window or chat.
Agent SSH/key/server access remains prohibited even while the owner has loaded
the key. Local command denials and project policy are not an OS isolation boundary.

Use reviewed `scripts/booster_ssh_session.ps1` actions only as the owner:

- `Setup`: one-time preparation of the existing built-in Windows agent service;
  an elevated owner console may be needed. Do not install software or load a key
  in a different/elevated user context.
- `Unlock`: normal owner console, existing named encrypted key and native local
  prompt; confirm the exact Booster identity loaded before using agent packets.
- `Lock`: explicitly remove only that identity and confirm absence. Preserve
  other keys; never use a remove-all operation. Do not claim cleanup while the
  service is inaccessible or merely stopped.
- `Status`: bounded owner-local readiness only; never print other identities,
  key material, comments, account/config contents or the passphrase.

Closing PowerShell or rebooting must not be treated as key removal. Do not use
an unverified lifetime constraint as a substitute for explicit Lock. If work is
interrupted, use Lock/Status as the next owner action, not an automatic new load.

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
