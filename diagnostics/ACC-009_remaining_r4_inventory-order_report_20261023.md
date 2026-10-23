# ACC-009 r4: deterministic extension namespace comparison

Date: 2026-10-23
Executor: Codex, same assigned patch author. Scope: narrow r3 admission repair.

## Observed owner result

WP3b r3 Upload and Verify succeeded. Its dry-run Preflight returned exit1,
extension_namespace_inventory_changed at 2026-10-23T12:08:50.5743761Z.
Receipt: work/acc009-remaining-repair-r3-20261023/owner-receipts/WP3b/Preflight-STOP-279dca6c49f845bc922f12ef698098f3.json.
Runner eb3ad8d992670780e1fc9b6b2489b7ac1bf7a4ffc0ccb53c96b2dacfa1cbedb1;
packet da68ab45a43603ba05edfeafce686d8971933048316c873d5047711cb6fd9504.

The exact runner checks target states and read-only dependencies first; then
ar_metadata reads active events, startup/theme counts and extension_install codes.
This STOP precedes registry/grants checks, the temporary web probe, backups,
source writes, metadata writes, cache cleanup and self-deletion. No business API
call is made by this path. source_changed=null is a conservative generic error
field; no-write classification follows the actual code ordering and --dry-run,
not that field alone. No Apply receipt was supplied. No rollback is indicated.

## Confirmed local defect and unresolved live fact

runner_core.php:92-93 compares SQL ORDER BY code results directly to Python-sorted
extension codes. The23 expected values include PintaNovaPoshtaCod and
SimpleCheckout. SQL collation order need not equal byte order. Native test
simulates case-insensitive SQL order, reversal and rotation of the exact same
codes. The accepted C2 predecessor already used sort($codes,SORT_STRING) before
its strict comparison (lines261-262); the new common gate omitted that sort.

This is a confirmed admission implementation defect. The current host's actual
returned code list/collation has not been collected, so it is not yet established
that this defect is the only cause of the owner's STOP. A new strict preflight
will independently establish whether the actual multiset is unchanged. If it
still stops here, collect only the bounded technical code inventory before any
further repair. Do not guess a new baseline, disable checks or repeat Apply.

## r4 change and exact unchanged scope

All three r4 runners sort a local copy of expected codes and actual rows with
SORT_STRING, then compare using ===. Case, duplicate count and exact spelling
remain significant. Added/removed/renamed/duplicate extensions still STOP.
No DB query, cap, event/startup/theme/registry gate or source payload changes.
All AR_SPEC bytes, target hashes/absent states, source before/after payloads,
business rules and rollback algorithms are exactly r3, reverse-checked.

Packets change only runner name/hash/size and r4 release/receipt paths. They use
fresh paths, so r3 staged runner/receipts are preserved and a failed/stale r3
Preflight cannot authorize r4 Apply. They retain separate Upload/Verify/Preflight/
Apply, strict known-host checks,600s wait, no-replay Apply marker, owner backup
attestation for WP3c and cleanup only after successful source Apply.

Facts: work/acc009-remaining-repair-r4-20261023/release-facts.json.
Full reversible diff: work/acc009-remaining-repair-r4-20261023/exact-diff.patch.
Mechanical comparison: diagnostics/ACC-009_remaining_repair_r4_evidence_20261023/equivalence.json.
Source target counts9/18/0, same r3 before/after identities. Source apply creates
the private per-run backup under /home2/boosters/_patch_backups/ACC-009-<part>-<UTC>-<random>;
dry-run has backup=none. This path is not known until an actual Apply receipt.
Rollback remains manually assessed; no automatic rollback or removal of staged r3.

## Local checks and fidelity

PHP8.0.30 lint all3 candidates. Actual candidate admission function chain with
fictional mysqli/config/web:26 checks per runner, including previous18 and new8.
Same23 codes in three distinct orders accepted; added/removed/duplicate/case
changes refused. The fixture contains no production DB, business values or
provider calls. Native PowerShell5.1 AST and extracted pure receipt functions:
27 checks pass. Owner packet entry and transport were never executed by an agent.

The earlier full r3 source/browser/lifecycle/metadata suites and actual r3 verdict
are reused only for byte-identical unchanged payloads/algorithms/dependencies.
No browser rerun is justified by a DB-inventory comparison change. Frozen217
inputs must be verified again at r4 dispatch/closure. Failed draft fixture trace
was Undefined constant AR_SPEC because fixture extraction removes the embedded
constant; fixed by loading the exact decoded candidate spec in the fixture.
No production candidate was changed for that fixture issue.

## Release and remaining gates

Preparation status NOT_REVIEWED; a separate actual r4 review-release.json is
required before any owner r4 packet entry. Actual r3 REVIEW_OK remains valid for
its exact historical bytes, not an approval of r4. The new integrated review
covers all six r4 hashes and the exact delta with unchanged evidence reuse.
Owner runs WP3b Upload/Verify/Preflight, then sends the complete output before
receiving Apply. N2 follows successful WP3b application/QA. WP3c retains separate
explicit owner go and a fresh consistent owner-held full DB backup. Do not repeat
SSH setup/login, require Lock, disable SimpleCheckout, change roadmap/Git, access
server as an agent, or infer host/provider/UI success from local tests/review.
