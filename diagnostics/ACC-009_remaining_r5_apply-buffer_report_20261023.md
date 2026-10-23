# ACC-009 r5: packet-only Apply marker buffer repair

Date: 2026-10-23
Executor: Codex, same assigned author. Preparation NOT_REVIEWED pending actual r5
integrated acceptance. No change to PHP runners or production source payloads.

## Owner failure and exact causal reconstruction

WP3b r4 Preflight succeeded at 2026-10-23T13:26:19.6877274Z: exact source pins,
multiset, events, registry/grants and web runtime, probe cleanup OK; no changes.
Owner Apply at 2026-10-23T13:29:38.1291227Z failed exit127 with empty stdout and
stderr `main: line 6: 13: command not found`. Receipt:
work/acc009-remaining-repair-r4-20261023/owner-receipts/WP3b/Apply-STOP-7af22ca5e5fd4d34bdd1a75229ce77f0.json.
The atomic local Apply-attempt.json remains present and must never be deleted.

PowerShell variable names are case-insensitive. Top-level $Bytes holds the runner
length; the Apply marker statement assigned its JSON byte array to $bytes in that
same scope. Invoke-Remote subsequently replaced the shell BYTES literal with
`123 13 10 ...` instead of the length. Bash interpreted13 as a command and stopped
under set -e at line6. This precedes path/hash/runtime checks and every PHP call.
No patch, backup, database, business API, probe or cache mutation was reached.
No rollback is indicated. The local attempt marker is the intended safety record.

This is not inferred merely from missing output: reproduce_r4.ps1 extracted the
actual native packet renderer, substituted the exact saved Apply-attempt bytes
and recovered the whole dispatched script SHA256
70517cfe4b94dedd830fec1cdd70cc9323acd4fefdae7b8c132b41ff147a6f84,
exactly matching the owner failure receipt. No transport was invoked locally.
Safe execution of only the first six assignment lines reproduces exit127 and
the same stderr for all three old packets. No real server state was read.

## Narrow repair and scope

All three r5 owner packets rename only that top-level marker buffer to
$markerBytes, including its two uses. Two other exact replacements create r5
release and receipt paths. Each replacement has occurrence-count1 and reversing
the three replacements recovers the original r4 packet bytes exactly.

The r4 PHP runner filenames, lengths, hashes, specs, all payloads, source target
states9/18/0, admission/backup/rollback/metadata algorithms are untouched.
Source Apply backup remains a private per-run directory under
/home2/boosters/_patch_backups/ACC-009-<part>-<UTC>-<random>; actual path awaits a
successful Apply receipt. New-file target backup=none, prior existing targets
retain exact guarded backup/restore. WP3c keeps separate owner go/full backup.

Use only a newly accepted r5 packet. Keep old marker/receipts and staged runners.
There is no automatic replay or rollback. A new owner-controlled attempt is
permitted only after the reviewed no-execution disposition and fresh r5
Upload/Verify/Preflight evidence. Upload will normally find the same staged r4
runner; no source patch needs regeneration or baseline replacement.

## Interaction map and improved fixture fidelity

Owning path: actual top-level Apply branch -> Get-Prior -> optional metadata
backup authority -> atomic marker JSON serialization -> Invoke-Remote renderer
-> process boundary -> shell variable assignment -> source_gate PHP -> cache.
Previous fixture tests parsed syntax and called receipt validators without
executing top-level marker serialization. Bash -n also accepts `BYTES=123 13`.
These gaps allowed the defect despite earlier independent static review.

test_packet_entry.ps1 now executes the actual AST-extracted top-level try block,
native constants/here-strings, real local runner identity guard, Get-Prior,
Read-Result, marker creation and renderer through its actual UTF8 byte encoding.
Only release authority, prior receipts, metadata backup and remote outcome are
fictional. No production packet entry or SSH/key/transport execution occurs.
It writes only new fixture directories inside its own work folder. Every fake
boundary is explicit; success means composition/entry proof, not host application.

Native PS5.1 results: all12 r5 Upload/Verify/Preflight/Apply combinations pass;
all3 old Apply cases discriminate the corrupted length; all3 actual new consumed
Apply branches refuse before mock transport. Total18 cases. Both source counts
9/18 and metadata count0 exercised, including separate fake backup attestation.
Git Bash: full generated Apply scripts parse for all6 old/new cases; runtime of
only six assignment lines fails old127 and passes new0 in all3 parts.
No real PHP, server path access, SQL, NP call, shipment or cache cleanup in tests.

Fixture preparation issues retained: a first local reproduction omitted the
Utf8 fixture binding; fixed in fixture only. Python-child PS5.1 initially inherited
PSMODULEPATH from PS7 and could not autoload Get-FileHash. Child-only case-insensitive
removal of that environment variable restores native module discovery. No global
module/profile/auth setting changed. Git Bash sandbox startup failed3221225794;
the exact bounded shell-only test ran successfully with normal user execution
outside that filesystem sandbox. No permission rejection, SSH or broader action.

## Review and next gate

One final integrated r5 review covers all three retained exact runner hashes and
all three new packet hashes, causal reconstruction and real entry fixture. Reuse
actual r4 REVIEW_OK only for byte-identical unchanged source/runtime scope.
Do not use r4 acceptance to approve new packet bytes. No separate result-review
call is needed merely to reclassify this fully reconstructed fail-closed outcome.

After actual acceptance: owner r5 WP3b Upload/Verify/Preflight separately and send
full output before Apply. A new failure needs targeted assessment, no repeat Apply.
N2 after successful WP3b and QA; WP3c remains separately gated. No new task, auth
reset/key cleanup, SimpleCheckout retirement, canonical roadmap or Git authority.
