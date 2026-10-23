# ACC-009 r5 independent review and release-condition closure

Date: 2026-10-23
Status: CODE_COMPOSITION_REVIEW_OK; R1/R2 mechanically closed; owner execution
and QA pending. Codex main author read the complete actual response.

Actual subscription Claude Opus5-5/high request
028ece35-43a3-41e8-8357-257e5e366d41 accepted all three exact pairs, no mandatory
defects. All291 declared inputs, packet and HEAD/index/tracked state unchanged at
closure. No tools/MCP/hooks or paid overage. Original provider result preserved.

Response diagnostics/ACC-009_remaining_review_evidence_20261023/remaining-code-ready-r5-response.md
SHA256 2e2ac31ef40905de773cfb7d0c8baa0aad78abd9aef3bd52832441a4796b9713.
Original result SHA256 b57b19716b131201c8ec25632b7d11839c9a3eedf9ff04cbd28f042158a41ee8.

## Exact release identities

WP3b retained runner patches/ACC-009-WP3b_profile-admin-source_HELD_r4_20261023.php
SHA256 0854ffe33ac9239349d95967cca264a378c429a86b2a988f0d028dcc53957166.
New packet diagnostics/ACC-009-WP3b_owner-PS_HELD_r5_20261023.ps1
SHA256 9397ee99f49b1d113db87c068fbad8dc02ae6152b55d51b56e3c390ab9e4a1c6.

N2 retained runner patches/ACC-009-N2_unified-name-policy_HELD_r4_20261023.php
SHA256 8c81393cd73c43a8ec70ae98deab0cf1c107fa3dc6cfd0d63ef58f158c8aff89.
New packet diagnostics/ACC-009-N2_owner-PS_HELD_r5_20261023.ps1
SHA256 d251819bf6318b8832b31f9098a97b9c4cf451bfa6ee2ab2113ae5b4f21d7ca1.

WP3c retained runner patches/ACC-009-WP3c_profile-metadata_HELD_r4_20261023.php
SHA256 4f6b5a180caeb5e72f37eb3df14d7bbc36c401372632f9f98dfe369db460878c.
New packet diagnostics/ACC-009-WP3c_owner-PS_HELD_r5_20261023.ps1
SHA256 256a8b5cd860dc57fb538edfac3f4c62c94a98a2dcf60c1d90161afe781e0620.

Release diagnostics/ACC-009_remaining_repair_r5_evidence_20261023/review-release.json
SHA256 9703c280d5c3c00419b035b8bf7b63bbb7444ce89d64b3a31b06816553e0f10b.
Preparation HELD/NOT_REVIEWED labels are frozen historical snapshots. Actual
release supersedes those labels only for these six identities. No renaming.

## Required release conditions closed without another model call

R1: actual r5 release created, correct request/schema/six hashes. All six pure
AST-extracted Assert-Release checks pass: three exact accepts, three altered
packet hash refusals. Saved post-release-checks.json and release-path-closure.json
under the r5 evidence directory. Frozen old close_review.py legacy-path field
does not describe the actual packet gate; sidecar checks the real r5 path.

R2: exact retained source ar_replace/ar_source_apply bodies mechanically checked
in all3 runners. WP3b/N2 ar_replace lines433-446 explicitly chmod($temp,$mode)
before rename and fail if chmod fails. ar_source_apply lines447-471 selects0644
for absent targets or preserves fileperms(existing)&0777, passing this mode into
ar_replace. Thus inherited umask077 does not leave newly published static files
at0600. WP3b new3/existing6, N2 new2/existing16. Citation and full function bodies
saved in R2-file-modes-closure.json. No native Unix/LiteSpeed serving claim and no
runner change; this is the precise mechanical source closure the reviewer asked.

## Failure assessment, tests and limits

Exact r4 dispatched script SHA reconstructed from real renderer + preserved
Apply-attempt bytes:70517cfe4b94dedd830fec1cdd70cc9323acd4fefdae7b8c132b41ff147a6f84.
Reviewer independently confirmed no PHP/source/backup/cache write: shell stopped
at initial assignment line6 due to the Bytes/bytes alias. No rollback indicated.
Old r4 WP3b marker is permanently consumed and preserved. Old N2/WP3c packets
share the defect and are superseded; never use them. Retain their immutable bytes.

All3 new packets rename the marker buffer, retaining all other executable logic
except new receipt/release paths. Reverse byte equivalence checks each exact
replacement once. Actual r4 PHP/source/rollback/context/browser acceptance reused
only for byte-identical unaffected scope. Target states and backup/rollback facts
9/18/0 remain in r3 detailed report and r5 release-facts.json. No new site code.

Native entry fixture18 cases, six full Apply Bash syntax checks and six real safe
assignment-prefix executions pass. Old Apply corruption discriminated and new
consumed markers refused before mock transport. shell-checks.json was produced
by test_shell.py outside the Windows filesystem sandbox after Git Bash startup
failed inside it. No server/PHP/provider or real cache execution. Fixture fake
release/receipts/backup/remote response limits are documented in the r5 report.

Reviewer optional collision lint/constants/renderer hardening are recorded for a
future substantive revision, not silently added. Retained profile fallback and
strict Custom Fields mapping warning remain relevant before WP3c. Windows PS5.1
is the canonical owner runtime; no direct PS7 packet execution.

## Next owner gate

Owner runs new r5 WP3b Upload/Verify/Preflight separately, sends complete output
before Apply. Upload normally reports already_present_same_hash for the retained
r4 runner. Fresh r5 receipts and hash binding are mandatory; never copy r4 receipts
or remove its marker. A new assessed owner attempt is allowed after this proven
no-execution failure and fresh admission; no automatic replay or rollback.

After successful WP3b Apply and focused QA: N2. WP3c needs separate explicit owner
go and fresh consistency-attested full DB backup held by owner. Agents never use
SSH/key/server tools, reset authentication, delete staged files, require Lock,
retire SimpleCheckout, or gain Git/canonical status-write authority here.
