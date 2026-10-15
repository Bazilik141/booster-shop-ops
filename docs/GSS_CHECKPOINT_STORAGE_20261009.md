# Current GSS checkpoint storage

Date: 2026-10-09. Authority: direct owner decision GSS-D084.
Applies to GSS continuity bookkeeping only. Codex is the sole writer; actual
Claude remains the independent reviewer. PC remains the current host.

## Current paths

- Start/resume: `checkpoints/GSS/CURRENT.md`.
- New dated progress snapshots, previous-pointer copies and JSON receipts:
  `checkpoints/GSS/`.
- Existing history: exact-byte mirrors under `checkpoints/GSS/history/`, with
  source-to-copy SHA256 entries in `checkpoints/GSS/history-index_20261009.json`.
- Writer: `work/save-gss-progress-checkpoint-v2_20261009.mjs`, invoked with a
  new immutable public state JSON under `work/`.
- Legacy discovery: `handoffs/GSS_foundation_RESUME_CURRENT.md` forwards to
  the new current pointer. Do not invoke the old progress writer after this
  routing change.

This amendment supersedes only the storage/writer locations in
`docs/WORK_INTERRUPTION_RECOVERY.md` and discovery references in the D083 rules
and handoff. Its save cadence, interruption reconciliation, no-replay rules and
authority boundaries continue unchanged. Root AGENTS links this amendment.
All relative artifact paths in snapshots resolve from the repository root.

## Preserve history and meaning

Historical checkpoints and receipts remain intact at their original paths.
Their copies contain identical bytes, including original links and timestamps;
they are archival mirrors, not new events or new acceptance. The accepted
read51 result remains anchored to its original checkpoint and receipt. Preserve
the exact pre-change root AGENTS and legacy pointer versions in the history
index before changing the mutable routing. Old packet identities can be
resolved through explicitly recorded historical copies; never overwrite an
old manifest, body, dispatch or result to make it match current files.

`handoffs/` continues to hold genuine task handoffs and retained pinned
historical checkpoint originals. New progress bookkeeping belongs here rather
than adding further checkpoint files directly under `handoffs/`.

The current pointer becomes authoritative only after its dated snapshot and
receipt are saved, fsynced and read back. The writer checks the prior pointer
bytes before replacing it. Interrupted publication leaves dated evidence and
the last intact pointer; inspect it before retrying with a new state. This is
local recovery bookkeeping, not an off-device backup or a zero-loss guarantee.
The helper writes public files only, performs no native/database action and
does not authenticate owner decisions or supply peer acceptance.

## Current continuation and migration limit

Keep this conversation active; the owner intends to continue from the laptop
in this same chat. The old NEXT_CHAT handoff does not require a new conversation.
Read CURRENT, its snapshot, the accepted result, current rules and retained
operation evidence before dependent work. No automated repeat of read51, CR11,
D081 or D082 is authorized. Their grants remain consumed.

D083's final package SOURCE/procedure review has no verdict because the actual
Claude r4 request hit a subscription limit. Do not replay that dispatch or
fabricate its run/result. The old package prototype expects the old mutable
pointer layout; its earlier dry checks are historical evidence, not validation
of this new layout. Before creating the real public manifest/ZIP, prepare an
additive package helper and a new review packet covering this storage change,
historical path aliases, new pointer/receipt chain and preserved prior findings.
Obtain actual independent acceptance and validate the current public closure.
The public ZIP, private backup, laptop inventory/restore and host cutover remain
pending. This amendment does not weaken those gates.
