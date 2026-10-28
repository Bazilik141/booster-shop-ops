# RD-18 WP1 unadmitted-event diagnostic ready package

Date: 2026-10-27 (Europe/Kyiv). Executor: owner-assigned Codex.
Status: REVIEW_OK_OWNER_DIAGNOSTIC_READY. One standalone metadata capture released; main Verify/Apply remain held.

## Observed stop and authority

Owner Upload succeeded at08:44:46Z, then Verify at08:44:49Z stopped with
`error=unadmitted_target_event` after PHP8.0.30 syntax success. Saved stdout
and state match the reviewed pair: runner
`f2bd1d4312923ba7c1d4cdc46cf83ccf44a631f50a027e87f9e3a6bd9a428221`, packet
`2b2713c0ff86a824dacf224b8105ed779d198893943f1d138e8bccb5a3724b91`.
State has uploaded=true and neither verified/admission nor applyAttempted.

This is an expected fail-closed boundary, not evidence of deployment or a source
defect. The exact reviewed runtime rejects a relevant enabled event before the
target/admission/backup/write branch. Verify does not invoke Apply/cache cleanup.
No production source/DB/cache write; no rollback is indicated. Private staged
runner stays held. Do not retry Verify/Apply, reset state, re-pin or broaden an
allowlist without evidence. Starting PowerShell in System32 does not change
the packet's absolute root/path handling.

Evidence: `RD-18_WP1_review_evidence_20261027/owner-Verify-unadmitted-event_20261027.json`.
The identity of the rejected event is NOT yet known. No assumption of analytics,
theme, payment or a harmless hook is made. No separate actual-result/design
review is needed merely to classify this known STOP under canonical policy.
The new diagnostic itself requires its integrated code/packet ready review.

## Exact standalone diagnostic

PHP: `diagnostics/RD-18-WP1_event-inspect_20261027.php`.
Owner packet: `diagnostics/RD-18-WP1_event-inspect_owner_20261027.ps1`.
Task identity: `RD-18-WP1-event-inspect-r1` (one consumed attempt).
Derived from the accepted RD16 owner-run event inspector/stream transport.
It streams the PHP over owner SSH stdin; no staged PHP file is written.

The current main runner's exact rd16_event_check body is copied verbatim,
SHA256 `48f8b701b3034c40256a2a79e8da08e93e8015edf912ac51a9c8fc4cf6c78d6b`.
It classifies strings only; no registered action, application bootstrap,
customer/order model, integration or provider is invoked.

Owner-only PHP8.0 in fixed verified catalog root loads config locally to open
mysqli with int DB_PORT and utf8mb4. Config/credentials are never exported or
printed. Schema columns checked before fixed metadata SELECT. READ ONLY
transaction, rollback in finally. Fixed SQL reads only enabled event trigger
and action, sorted like the main guard, capped1025 to reject >1024. At most64
relevant pairs. >4096-byte fields STOP; safe technical route strings <=512
characters are returned, other values are hash+byte length only. No business,
customer or order rows, logs, sessions, event action execution or DB changes.

Exactly four routing-mechanism source hashes (not file bodies/base64):
- catalog/controller/startup/event.php
- catalog/model/setting/event.php
- system/engine/event.php
- system/engine/action.php

Fixed regular paths walked to reject symlinks; missing means truthful absent.
Each file <=65536 bytes and total<=131072; only size/hash is returned. These
four files are already pinned dependencies of the main runner; compare fresh
identities after capture before reusing earlier dispatch semantics. Never infer
an unknown action's source path/body or admission from its name alone.

Result: two stdout lines (one JSON + RD18_EVENT_INSPECT_OK), captured UTC/PHP,
counts/relevant classified pairs/core hashes, exact classifier and main pair
identities, writes=none/customer_reads=none/admission=none. Captured events can
change after this observation; it is not main-runner admission.

## Owner packet and local checks

Absolute root permits launch from any local directory. Existing strict-host,
batch-mode/no-forwarding agent transport retained. PHP bytes locally SHA-checked;
exact LF stdin sending is unchanged. One exclusive local attempt file before
dispatch. Raw stdout saved before branching on exit. Exact task/two-line/banner/
no-writes/no-customers/no-admission contract checked. Failure or consumed attempt
means preserve receipts and STOP. Main owner state, staged runner, source,
backup/cache/config and hook registrations are untouched.

Receipt directory: work/rd18-event-20261027/owner-receipts.
Expected owner output: RD18_EVENT_INSPECT_OK, unadmitted_count=N, receipt path.
Return this result; do not run Verify/Apply. A zero count is a current observation,
not automatic permission to resume an earlier failed admission.

Local proof: native PHP8.0.30 syntax; exact diagnostic functions under fake mysqli
with native files,12 cases (accepted/refused/irrelevant rows, redaction, empty
global pattern, schema/transaction/query/row/relevant/field/source caps, absent
file). Query strings checked and rollback observed. Full owner packet AST/
success/nonzero/bad-contract/tampered-source/consumed-attempt preservation test
uses a complete transport replacement BEFORE invocation; no native SSH/SCP.
The first test mock used the wrong script variable scope; fixture globals fixed
that local harness, not the owner packet or production. No real MySQL/SSH/root/
PHP-stdin hosting execution is certified. Existing LF transport is reused from
the reviewed owner-executed RD16 packet; its code is unchanged apart from task
identities/hash/receipt contract. Agent never invokes owner packet or server/key
tools, reads credentials, deploys, changes main code/admission, or writes Notion,
dashboard, Git, DB/business/provider data.

After ready review: release one exact diagnostic command. Strict local receipt
intake then inspect actual rejected pairs and fresh core hashes. If a callback
needs source assessment, request only its verified named sources as one bounded
supplement. Any admission change needs a coherent affected implementation review
and owner-controlled future verification, never a blind allowlist edit.


## Exact ready identities

PHP SHA256 `13c3bfe0b98a2d421caf53a902f503263c67f4b2db2619a3032675183430ae77`; owner packet SHA256 `aeb647be7552c110237799341fb3f3e4236094bc08c7c0bfb9b6f0251af8f310`.


## Actual ready review and release

Actual subscription Claude Opus5-5/high Review OK/no mandatory findings, request1c60ae5e-9184-4535-8e18-e78fb6ac9b5d. Exact PHP/owner hashes above bound. Native adapter verified23declared inputs unchanged at completion. Actual response SHA256 97a98afc2cdb03a6cdc2c3c9cc4ca3732ddfde7febfd946ece319b5bb8017dca. No tools/MCP/hooks/API/paid fallback. Original result/run/response and preclosure report/facts preserved. No executable byte changes after review.

Optional E1-E6 disposed in event-ready-r1-release-closure.json. Current exact main query has the same trigger/action projection, enabled predicate, order and1025cap, with only SQL whitespace variations; exactly one unadmitted_target_event emit site. Strict local intake checks it and all three identity hashes, booleans/integer counts/php/cores. New unchanged-code12/4x2 tests bracketed exact pair before/after. Repeated PS result serialization changed only those metadata outputs after native review completion; source/commands/main state remain exact. Generic io_warning/internal failures remain fail-closed; timing/hash metadata is not admission.

No diagnostic owner receipt/attempt exists before release. Main state remains uploaded-only. Owner may run RD-18-WP1_event-inspect_owner_20261027.ps1 once from any directory. Expected RD18_EVENT_INSPECT_OK/unadmitted_count/receipt path. Send output; never run main Verify/Apply or reset state based on this capture. Callback identity/source admission remain unknown.
