# Continuous recovery during long work

Date: 2026-10-09. Authority: direct owner clarification in the active GSS chat.
Applies to the executor's ongoing work in this repository, including long GSS
segments. Codex remains the assigned GSS writer; actual Claude reviews. This is
a working procedure, not additional implementation, native or publication authority.

## Owner intent

Prevent losing substantial progress when electricity fails or the PC stops
unexpectedly at ANY point in a long work segment. A checkpoint only at the end
of a session does not meet this requirement. The clarification is effective
for subsequent work; earlier one-time checkpoints remain historical evidence.

## Save continuously

- Save task-owned code and documents to files as they are edited. Keep partial
  work on disk, clearly labelled incomplete/unverified; do not retain an entire
  implementation only in chat, model reasoning or process memory.
- Record a small recovery checkpoint after each meaningful completed substep,
  before a long test/review/external operation, after its result, and before
  handing off or finishing. During longer active work, refresh it at least
  every 10 minutes at the next controllable tool boundary, even if incomplete.
  Do not wait for the whole block to finish. This cadence is executor procedure,
  not an installed background timer or guarantee that a blocked process can
  checkpoint itself. Long commands should yield control while running.
- For a long process, save a dispatch/start record first. Retain bounded safe
  progress/output on disk as available; flush operation journals and reservations
  at their protocol-required boundaries. Save a terminal result only once it
  is observed. Where progress cannot be captured during execution, record that
  limit and treat an interrupted operation as unresolved.
- Store completed steps, work in progress, saved artifact paths, latest check
  and review evidence, pending decisions, launched operations/consumed grants,
  and the exact next bounded action. Reference existing evidence rather than
  duplicating large manifests or private data in every checkpoint.
- Keep dated snapshots; advance the task-owned current pointer only after the
  new checkpoint has been written, fsynced and read back. Use an atomic file
  replacement with an unchanged-old-pointer check. A progress update does not
  replace the last accepted result or grant acceptance/authority.

For current GSS work use `handoffs/GSS_foundation_RESUME_CURRENT.md`. The additive
helper `work/save-gss-progress-checkpoint_20261009.mjs` accepts a small saved
public JSON state file under `work/`, writes a dated checkpoint/receipt, and
advances ONLY that pointer. It executes no native operation and is not a daemon.
The executor must invoke it at the boundaries above. Other task writers keep
their own separate checkpoints; never advance another writer's pointer.
This helper version anchors the accepted read51 result. After a newer accepted
stage, prepare an additive successor with that stage's exact anchor; do not keep
labelling read51 as the latest accepted stage or edit a frozen helper in place.

## Resume after interruption

Read the current task pointer, its dated snapshot, the last accepted result,
current project rules and preserved operation evidence before doing work. Verify
the relevant files and current environment. A dispatch without a verified
terminal result is an interrupted/uncertain operation, not success or proof it
never ran. Inspect its journal/reservation and reconcile in the authorized scope
before proceeding. Never automatically replay a write, a one-shot command or a
consumed grant. Historical container health is not current health after reboot.

Preserve frozen material and unrelated work. Checkpointing does not authorize
editing a pinned instruction/source, restoring containers, status changes,
production writes, new grants, Git publication or a change of reviewer format.
No secrets, private accounts, customer data or credential file contents belong
in a progress checkpoint. Local fsync/readback reduces exposure to interruption;
it does not provide a remote backup, physical-disk-failure protection, or an
absolute promise of zero lost in-flight reasoning/work. The recovery boundary
is the last durable snapshot plus the saved artifacts and operation records.
