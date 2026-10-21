# Independent ready review: Booster owner work-period SSH agent mode

## Verdict

**Code/composition: READY for owner release, gated.** The helper and the agent packet are approved as bound below.

**Guide: READY after one mandatory prose substitution (M1).** Codex can close M1 mechanically. No new review call is needed.

No runtime, cleanup or production acceptance is implied.

## Bound hashes

| Artifact | SHA-256 | Status |
|---|---|---|
| `scripts\booster_ssh_session.ps1` | `a631153f2ae8dda0c3b398c3b2ad880024cd129223bee6813b0626d86413ff58` | Approved |
| `diagnostics\ACC-009_C1-H1_owner-agent-PS_20261020.ps1` | `2bfb2f1c31669578a1758141d8f68ad8cc3c618b00abe4866ce807e4a85837d9` | Approved; parent `10aeac12…fe14` plus exactly the two authored edits |
| `patches\ACC-009-C1-H1_courier-notifications-private_20261019.php` | `94b92a139fae5d39645b151aa6a85fa746d806f3fdd9e89d5d5b7125855c04ae` | Unchanged; prior acceptance stands |
| `diagnostics\BOOSTER_ssh-work-period_owner-guide_20261020.md` | `d7aae30d788a7e2bdb02c1ca47c991036e6a03848642797b8312b3172e336a66` | Pre-M1. After M1, record the new hash; the diff must be exactly the M1 line |

## Assessment summary

**`$svc` → `$serviceInfo` repair: correct and complete.**
- The rename removes the case-insensitive alias with `$Script:Svc`.
- `$Script:Svc` is now the only service-state string.
- Setup reads `StartType` only after the absent-service receipt has exited.
- I found no other collisions: `$loaded`/`$Loaded` are separate scopes, and `$Key`/`$KeyGen` are distinct names.
- Native arguments are unchanged.

**Helper control flow.**
- `exit` inside `try` is not caught by `catch`, and the receipt is emitted before exit.
- `$null -eq $before` sits on the left, so an empty agent (`,@()`) is not mistaken for "unknown".
- "Has no identities" is accepted only with exit code 1, empty stderr and zero fingerprints. Anything else is reported as unknown.
- **Setup:** never loads a key, never stops or disables the service, installs nothing, and checks admin before any change.
- **Unlock:** the post-load fingerprint check is authoritative.
  - The pre-load pair check may fall back to `.pub`; the code comments say so honestly.
  - The `-cert.pub` guard prevents a silent second identity.
  - Rollback via `-d $Key` is re-verified, and the receipt degrades to `unknown` rather than claiming clean.
- **Lock:**
  - Removes one identity through `-d <.pub>`, never `-D`.
  - Confirms absence afterwards.
  - Reports changes to other identities as `warn`.
  - A stopped or unreachable service is reported as `removal_unverified`, never as clean.
- **No raw output:**
  - Captured output stays in memory.
  - Receipts carry no fingerprint, path or account data.
  - `catch` does not print exception text.

**Native child scope.**
- `SSH_AUTH_SOCK` and `SSH_ASKPASS` are removed for the child only, and `SSH_ASKPASS_REQUIRE=never` is set.
- Interactive `ssh-add` inherits the console, so the native prompt works and stdin is not redirected.
- Quoting is safe for the fixed paths: the profile path has no spaces, and the repo path is never passed to native tools.

**Packet composition.**
- The resolver fires only for `ssh`/`scp` (with or without `.exe`); `default` runs only when neither matches.
- The resolver pins the built-in `System32\OpenSSH` binaries.
- `IdentitiesOnly=yes`, `BatchMode=yes` and publickey-only authentication mean an unloaded key fails closed. There is no passphrase or key-file fallback.
- Strict host checking stays on; no host-key bypass remains.
- `ForwardAgent=no` and `AddKeysToAgent=no` are set.
- The command-line `IdentityAgent` correctly overrides the alias's `IdentityAgent none`.
- The forward-slash pipe path avoids backslash-escape tokenization.
- `scp` is unused in this release because Upload is not rerun.

**Policy edits.**
- All four edits are consistent with the owner decision and the helper.
- They keep the agent prohibition and the "not OS isolation" disclosure.
- None promises a key lifetime, and GSS is unchanged.

## Mandatory findings

### M1 — Guide cleanup gap after a non-ok Unlock (prose only, mechanical closure)

**Problem.** The guide allows Lock only "after an error during work following a successful Unlock". It also forbids ad-hoc cleanup. That leaves no instruction for Unlock results where the key may be loaded:
- `warn target_loaded_with_extra_identity` (target loaded = yes)
- `post_load_state_unknown_run_lock` (unknown)
- `unexpected_error_check_status` (unknown)
- no receipt at all, e.g. Ctrl+C or a closed window

In those cases the key could stay loaded account-wide while the owner waits. The helper's own reason literal (`…_run_lock`) also contradicts the guide.

**Closure.** In the "Якщо результат інший" section, replace exactly this sentence:

> Якщо помилка сталася під час роботи після успішного Unlock, дозволена одна спроба звичайного Lock для завершення; при помилці Lock зупинись.

with:

> Якщо Unlock завершився без `status: ok` і показав `target_loaded: yes` або `unknown`, або квитанції не було, або помилка сталася під час роботи після успішного Unlock, виконай одну спробу звичайного Lock; якщо Lock не повернув `status: ok` з `target_loaded: no`, зупинись і надішли обидві квитанції.

**To close:**
1. Apply the substitution as the only change.
2. Record the new guide SHA-256.
3. Confirm the diff is exactly this one sentence.

## Optional notes (record only)

- **O1 — Reason literals.** The helper's reason literals (`…_owner_must_remove`, `…_run_lock`, `…_check_status`) are subordinate to the guide. No code change is needed. If an unexpected identity may remain, that becomes a narrow follow-up; the owner should not remove keys on their own.
- **O2 — Cancelling the prompt.** Ctrl+C during the `ssh-add` prompt may end the helper with no receipt; M1 now covers that case. Entering an empty passphrase is a cleaner cancel.
- **O3 — Fail-safe stops.** A 32-bit PowerShell host, where `System32` is redirected, or an always-admin token with UAC disabled will produce stop receipts. They never produce false success.
- **O4 — Saved-state lookup.** Before the owner runs Verify, Codex should confirm that no state, receipt or stage path is derived from `$PSCommandPath`, `$MyInvocation` or the script's basename. The renamed packet must find the existing Upload state. If the check fails, Verify still fails safe (it stops without mutating anything), but the owner's work period is wasted.
- **O5 — Console prompt shows a path.** The native prompt and `ssh-add` stderr may show a path containing the account name on the local console. That is native output, not the receipt; the owner sends only the JSON.
- **O6 — Applying policy drafts.** Apply each draft only where its old text matches exactly once. Record the resulting hashes. Mechanically check that no other "never add to ssh-agent" sentence remains elsewhere unamended.
- **O7 — Native behaviour still unproven.** Installed-binary behaviour is not yet proven: forward-slash pipe resolution, `-d` targeted removal and `-l` output format. The source basis is not the installed build. With `BatchMode` and strict checks, any failure is fail-closed.

## Owner release (after M1)

1. **Setup** (elevated, once). Expected: `ok`, `running`, `not_checked`, reason `service_ready` or `service_already_ready`.
2. **Unlock** (normal shell). Expected: `ok`, `running`, `yes`.
3. **Verify** with the new packet only. Do not re-upload. The full output is assessed against the parent contract.
4. **Preflight** as a separate later step, after Verify is accepted. It is held to the parent's 26-row dry-run contract.
5. **Lock** after work, or after an error as M1 describes. Expected: `ok`, `running`, `no`. Only this result closes the work period.

## Remaining runtime gates and exclusions

**Remaining runtime gates (unproven):**
- Native Setup, Unlock, Lock and Status receipts.
- Agent-authenticated Verify through the pipe.
- Proof of targeted removal on the installed Windows agent.

**Still closed:** Apply, rollback, cache, commit/push, Notion, dashboard and GSS. The manual packet remains the fallback, to be used only after a confirmed Lock.
