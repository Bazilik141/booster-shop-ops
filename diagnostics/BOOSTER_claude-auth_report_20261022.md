# Booster native subscription Claude authentication investigation

Date: 2026-10-22. Executor: Codex. Scope: existing native Claude subscription
review transport on this Windows user; ACC-009 C2 preparation remains intact.
No deployment, SSH/key/service, account change, CLI update or paid API authority.

## Evidence and bounded comparison

The owner reports repeated daily login interruptions. The attempted owner command
used `C:\Users\14bez.local\bin\claude.exe`, missing the separator before `.local`;
PowerShell did not start that executable. This typo is separate from the real
expired-OAuth401 received by the C2 review process.

Native standalone CLI2.1.303, SHA256
1787c60619bad78543c67e4d9d8a868869c35a904f2a85eb9ea46c4808af196b.
Default configuration selected; no provider, OAuth-token or custom-config
environment override names detected. No credentials contents read by the agent.
Public help states safe mode retains normal authentication; bare mode skips
OAuth and is not used. No change of billing/provider/account/config directory.

Failed C2 final request0fda91b7-c2e8-4e44-a523-0317cf216d83 ran through
the checked task adapter inside workspace-write execution. Native status reported
saved subscription, but the actual model request failed with expired OAuth401
before substantive review. Saved credentials file metadata had modification time
2026-10-21T19:25:15.879186Z. Workspace execution has read access, but no write
grant to the user's default `.claude` profile. A saved-login status is not proof
that native automatic refresh can complete under this execution boundary.

New C2 requeste00b1d8d-92de-4423-9ca8-7b6548980952 runs the same native binary,
adapter, exact C2 runner and owner packet, with a fresh immutable request identity
and normal owner-account execution outside the workspace filesystem sandbox.
This is not Windows administrator elevation. Approval review accepted this
specific checked subscription review call. Tools/MCP/hooks are disabled; no
API-key source/fallback or SSH/server action. The owner did not complete a new
login between these two requests. Only Claude process metadata was checked;
there was no concurrent Claude process at the pre-dispatch observation.

During the second request, default credentials modification metadata changed to
2026-10-22T06:04:32.216977Z without an owner login. Contents were not inspected.
The request is still running at this entry; file modification alone does not
establish a successful model result or the exact internal refresh failure cause.
Do not claim every historical interruption has the same cause.

## Intended correction and verification boundary

Use the checked subscription exchange adapter with normal owner-account profile
write access for actual model reviews. Keep read-only status discovery separate.
Never infer a required interactive login from a workspace-sandbox401 alone;
first assess native profile/refresh access. Preserve the failed identity and use
a fresh identity for any authorized request. Do not replay consumed requests,
probe authentication with throwaway paid/model calls, read or edit tokens, create
a token refresher, copy auth stores, disable model-side review restrictions or
enable unrestricted Claude tools.

Canonical instruction updates await completion of the current frozen-input C2
review. This report is outside that review's declared inputs and grants no new
agent access. The current review can establish present recovery, not permanent
authentication or a test after the next natural credential expiry.

Primary references inspected:

- [Claude authentication](https://code.claude.com/docs/en/authentication): saved
  login reuse and re-login when automatic refresh cannot complete.
- [Official CLI reference](https://code.claude.com/docs/en/cli-reference), alongside
  exact installed binary help: safe mode and restricted review flags.
- [Official issue81937](https://github.com/anthropics/claude-code/issues/81937):
  similar headless/interactive mismatch on a different OS/version; corroboration
  only, not proof of this machine's cause or a reason for credential deletion.

## Result and routing correction

Requeste00b1d8d-92de-4423-9ca8-7b6548980952 completed with an actual Opus5.5/high
answer: C2 REVIEW_OK, no mandatory findings. Tools/MCP absent, overage false,
no declared input drift; HEAD/index unchanged. Original401 request retained.
Acceptance is diagnostics/ACC-009_C2-release_evidence_20261022/release-accepted.json.
The successful needed review, together with native profile metadata advancing
without owner login, establishes present recovery under the changed execution
boundary. No throwaway model probe or credential-content inspection was used.
The precise internal denied refresh operation was not captured; profile write
access is the relevant implementation constraint, not a proved complete history
of all owner login failures.

Canonical routing updates are now applied to guarded hunks in AGENTS.md,
docs/BOOSTER_LOCAL_ACCESS.md and the shared automatic review policy. They require
only the checked subscription exchange to run with normal user profile access,
through scoped approved execution; global sandbox and model-side tools stay
restricted. Interactive login is requested only after proper-context refresh
failure/absence/revocation. No executable/helper, installed CLI, owner profile
or token was directly edited by Codex. The frozen C2 review retains its original
instruction identities; this separate auth-routing delta is being independently
reviewed and does not change its runner/owner-packet or receipt contracts.

## Final routing-review gate: weekly quota, not authentication

Separate final auth-routing requestf8e0e74b-4a4c-4aa8-aaab-ff7d17855afa was
dispatched through the same approved normal-user subscription route. It failed
before substantive evaluation with seven_day quota rejected, isUsingOverage=false,
and a reset timestamp1792695600 (2026-10-22T19:00:00Z;22:00 Europe/Kyiv).
This is not an expired-login result and does not justify another login. No paid
fallback, model downgrade or automatic retry. Original result/run/closure retained
in diagnostics/BOOSTER_claude-auth_evidence_20261022. The request is consumed.

Auth routing documentation is NOT_REVIEWED pending quota availability and a fresh
request identity. Current native authentication recovery is observed; do not
claim independently approved routing or permanent reliability. The added pending-
review notice is bookkeeping after the failed review, not a changed executable.
On resumption, build a fresh bounded review of the current doc delta, preserve
R1 and its quota outcome, and do not replay the consumed builder/request.
No scheduled future execution is configured. C2's earlier REVIEW_OK still binds
the exact unchanged runner/owner packet; the auth-document delta does not alter
its source, owner SSH transport, host admission or receipt contract.
