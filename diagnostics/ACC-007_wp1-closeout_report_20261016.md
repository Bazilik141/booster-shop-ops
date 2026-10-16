# ACC-007 WP1 — owner-accepted closeout

Date: 2026-10-16

## Outcome and authority

WP1 ACC-007 is deployed and accepted by the owner. The owner explicitly requested commit, push and the WP1 update on 2026-10-16. This is one-time authority to publish ACC-007 and close its canonical Notion task and dashboard mirror; it does not assign other packages or establish standing Git/status permission.

This closeout preserves the three reviewed runners byte-for-byte. Earlier reports, reviews and evidence are historical snapshots: their pending gates and intermediate comment behavior are superseded by the later evidence summarized here, not rewritten.

## Accepted production behavior

- Account order list/detail/history use the approved customer status presentation, recorded change date/time under the verified Kyiv wall-time basis, deterministic history ordering, consistent visibility/counts and SQL pagination.
- Draft status 15 is hidden; status 19 does not imply completion/receipt; missing definitions and dates have neutral, truthful fallback behavior.
- The owner's later comment decision supersedes the original blanket ban: show an escaped, nonempty comment only for notify=1. It replaces the generic status explanation. Hidden/unnotified/empty comments retain the generic explanation. notify=1 records notification intent, not proof of delivered email.
- These are source-only changes: no DB write, administrative status rename, email, payment or stock-flow change.

## Frozen implementation and independent review

| Runner | SHA-256 |
| --- | --- |
| `patches/ACC-007_order-status-presentation_20261014.php` | `73d292554bf3f49cca5c13c10551a6b1124390ad527ce71f41012d8ef1fd2221` |
| `patches/ACC-007_notified-history-comments_20261014.php` | `f089e31d3b482c107b2f6a719bf1466ab29b224eb79cfcc76947bb242207e9eb` |
| `patches/ACC-007_history-comment-priority_20261014.php` | `0af604ca61123655c3cc54c054cf9f5d3a82fc8b51b00ac312a742fdeff93de6` |

Original implementation reports and actual independent reviews accompany these runners. The initial presentation review's caller-evidence condition was closed by its accepted addendum; the notified-comment and comment-priority changes each received Deploy OK. Their recorded historical transport is preserved honestly, including the earlier API reviews; the new closeout review uses the approved subscription CLI only. No historical review is replayed or represented as a new review of code.

## Production and acceptance evidence

The owner supplied the initial presentation dry-run/apply output on PHP 8.0.30: read-only DB preflight, seven changed targets, lint success and cache completion. Exact initial backup: `_patch_backups/ACC-007_order-status-presentation_20261014-20261014T115816Z-bd7d108c`.

The owner subsequently ran the two comment follow-ups and confirmed the final priority behavior works. The final acceptance is recorded in `ACC-007_history-comment-priority_evidence_20261014/owner_host_qa_20261014.json`. Exact final follow-up apply output and backup directory were not supplied; do not invent them. The owner controls production and must retain the actual per-run backup directories. No Codex production access or independent observation of every manual scenario is claimed.

Local fixture tests and their limits remain in the three original reports/evidence directories; source hashes establish unchanged artifact identity, not fresh production testing. No application code changes or redeploy are part of this closeout, so production QA is not rerun merely to publish its accepted artifacts.

## Publication and synchronization

Only ACC-007 runners, reports, independent reviews, bounded technical evidence, this closeout and the ACC-007 dashboard row are included. Publication is prepared from the current remote master in a detached task worktree; the owner's original branch, index and unrelated dirty changes remain intact. The only dashboard row changed is ACC-007; other tasks retain the remote baseline in the commit and the owner's existing changes in the original checkout.

Canonical target: Notion ACC-007 `Done`, dashboard `done`, last updated 2026-10-16. Planned exact properties and the dashboard row are reviewed before synchronization. The completion record does not start ACC-008/ACC-009, close ACC-006/GA4, change RD-25/RD-27 or implement mobile hero corners.

Rollback remains per runner, using that runner's exact owner-held backup and its documented guarded command, followed by Twig cache clearing. Undoing a Git commit is not production rollback. No DB recovery is required for these read-only presentation patches.
