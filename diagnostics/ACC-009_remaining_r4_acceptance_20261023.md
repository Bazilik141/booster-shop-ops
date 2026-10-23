# ACC-009 r4 independent review acceptance

Date: 2026-10-23
Status: CODE_COMPOSITION_REVIEW_OK; owner execution/QA pending.

Actual subscription Claude Opus5-5/high request
37c449d4-32c4-4a9a-8bfb-4a24137332fc accepted all three exact r4 pairs.
Main author read the complete response. Mandatory findings none. All244 declared
inputs and the review packet, HEAD/index/tracked state unchanged at closure.
No model tools/MCP/hooks, no paid overage; original full provider result retained.

Actual response:
diagnostics/ACC-009_remaining_review_evidence_20261023/remaining-code-ready-r4-response.md
SHA256 8f89a5502d9de22d49c8477f798b25fb2fdc0358952109ff0b7f28577445c2ae.
Original result SHA256 77bc1a19d324a23fe72ae4ead949d99174543e6b50ddae68c03c6d5c124d044f.

## Exact executable pairs

All paths below are relative to the canonical repository root.

WP3b runner patches/ACC-009-WP3b_profile-admin-source_HELD_r4_20261023.php
SHA256 0854ffe33ac9239349d95967cca264a378c429a86b2a988f0d028dcc53957166.
Packet diagnostics/ACC-009-WP3b_owner-PS_HELD_r4_20261023.ps1
SHA256 63b6601269f2c459c1febed893ec565fc3cb1262a548f7a79473c236bcbabaf2.

N2 runner patches/ACC-009-N2_unified-name-policy_HELD_r4_20261023.php
SHA256 8c81393cd73c43a8ec70ae98deab0cf1c107fa3dc6cfd0d63ef58f158c8aff89.
Packet diagnostics/ACC-009-N2_owner-PS_HELD_r4_20261023.ps1
SHA256 4aa1b0251a34ba60bff81d95880af1a35d4c40713c1cdb8c7397b59f10172d76.

WP3c runner patches/ACC-009-WP3c_profile-metadata_HELD_r4_20261023.php
SHA256 4f6b5a180caeb5e72f37eb3df14d7bbc36c401372632f9f98dfe369db460878c.
Packet diagnostics/ACC-009-WP3c_owner-PS_HELD_r4_20261023.ps1
SHA256 1e4b4ddb1f154b908d9e64bbebf8d4fec9906aafb175effa06fd222a7494728c.

Actual release gate:
diagnostics/ACC-009_remaining_repair_r4_evidence_20261023/review-release.json
SHA256 a716307b30be91488cd2258fc7e4502adbf4aad4d2230663b366c2ed81a7b5d6.
Schema acc009.remaining-review-release.v1, per-part runner_sha256/packet_sha256.
All six pure extracted release-gate checks pass (three exact accepted, three
altered packet hashes refused); owner packet entry/transport never ran locally.

Preparation NOT_REVIEWED/HELD text and filenames are retained frozen snapshots;
this actual verdict and release supersede those labels for only the exact bytes.
Do not rebuild/rename them. The r3 accepted files remain immutable history but
are superseded for owner use by r4 after the r3 dry-run STOP. Leave the staged r3
runner untouched; removal is a separate later owner action.

## Evidence, limits and optional findings

See ACC-009_remaining_r4_inventory-order_report_20261023.md for exact stop ordering,
repair, fixtures and unknown current live inventory. Source payloads/spec bytes,
before/after states9/18/0 and backup/rollback rules are exactly r3. Detailed target
states remain in ACC-009_remaining_r3_repairs_report_20261023.md and r4 release-facts.
No host source write occurred in the supplied r3 dry-run path; no Apply was given.
No rollback is needed for that STOP. Unknown current host list is not silently
accepted: new r4 strict preflight independently rechecks it and all later gates.

Reviewer optional O-r4-1 is mechanically strengthened after review: byte-level
reverse substitutions recover all three original r3 runner and packet bytes.
O-r4-4 actual r4 release path is checked in release-path-closure.json, preserving
the frozen old close_review.py legacy-path field. O-r4-5 continuation points only
to r4. O-r4-2 mismatch detail and O-r4-3 extra fixtures are optional future work,
not changes to the accepted binaries. Retained r3 optional suggestions remain
in the complete response. Before WP3c warn that manual changes to the three mapped
Custom Fields can cause strict profile/admin errors; graceful fallback is
recommended but was not made a release blocker. Do not silently add it now.

## Next owner gate

Owner WP3b r4 Upload, Verify, Preflight separately; send complete output before
Apply. Successful Preflight expires after30 minutes; re-admit with a fresh dry-run
if necessary, without automatic loops. A STOP needs targeted assessment. If the
extension gate still stops, collect only the bounded technical inventory through
a separately reviewed owner collector; no guessed pins or repeat Apply/rollback.

N2 only after successful WP3b application and focused QA. WP3c only after separate
explicit owner go plus fresh consistency-attested full DB backup held by owner.
No server/key tools, production or business API call was performed by an agent.
No Git commit/push, canonical Notion/dashboard change, SimpleCheckout retirement,
auth reset, mandatory Lock or broader task authority follows from this acceptance.
