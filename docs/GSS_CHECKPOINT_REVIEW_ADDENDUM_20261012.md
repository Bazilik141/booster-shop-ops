# GSS checkpoint routing clarification for sender/requester review

Date: 2026-10-12 owner timezone. Additive executor documentation; independent
review pending. Existing owner D084 routing is preserved, not a new authority.

Read `docs/GSS_CHECKPOINT_STORAGE_20261009.md` alongside
`docs/WORK_INTERRUPTION_RECOVERY.md`. The storage amendment supersedes only the
older document's handoffs pointer/v1 writer locations. Active recovery uses
`checkpoints/GSS/CURRENT.md` and `work/save-gss-progress-checkpoint-v2_20261009.mjs`.
Cadence, actual-operation reconciliation, no-replay and authority rules remain.
Older documents and pinned bytes are preserved rather than silently rewritten.

For new sender/requester review packets CURRENT is a discovery pointer only.
Resolve and hash-check its immutable dated snapshot and JSON receipt; render
those two files and exclude CURRENT from unchanged input pins. Save progress
before long work and at controllable10minute boundaries. Source/packet inputs
remain frozen; advancing CURRENT is not a changed review input or acceptance.
The actual15 predispatch check observed this once on the actual prepared packet.

The v2 writer's complete source and storage amendment must be rendered before
a new R5/adoption review depends on them. Its wx/fsync/readback dated outputs and
checked prior-pointer replacement are local bookkeeping; they do not establish
atomic filesystem behavior, stable media, off-device backup or adversarial locks.
Source review of the writer is distinct from observing one pointer advance.

Time-specific pending-package statements in the20261009 storage document remain
historical statements; they cannot determine later bundle acceptance. A current
bundle claim must independently follow its exact later review/manifest/receipt.
This clarification makes no new ZIP/private backup/laptop/runtime/cutover claim.
INTAKE15 SOURCE Review OK does not review the full writer source; its B4 remains
carried forward for the next concrete R5/design packet.
