# NCRM-18 — Migrate roadmap tracking into NCRM: scoping notes

Date: 2026-07-28 | Status: scoping only, no Codex handoff yet
Blocked by: NCRM-11, NCRM-12, NCRM-13, NCRM-14, NCRM-15, NCRM-16, NCRM-17 (owner decision, 2026-07-27 — this stays a planning document until the backlog clears)

## 1. Why this is more than "move the Notion data"

Today status lives in Notion (canonical) and gets manually mirrored into `dashboard/booster-dashboard.html`. Every agent session risks drift — this is exactly what NCRM-19 is patching with authorization/process fixes, and what this session's audit found in concrete form (NCRM-07b triplicated, NCRM-11 stale title, NCRM-10's Mono gap sitting undetected in a "Done" card).

If the roadmap table lived inside NCRM's own Postgres instead, Claude and Codex would write status with the same repository/SQL access they already use for `sales`/`purchases`/etc. — there would be nothing to mirror, because there would only be one database. That is the actual prize here, not just relocating data. It's worth being explicit about that trade so the owner is deciding on the real payoff, not just "same thing, different app."

## 2. What NCRM already has to build on

Confirmed by reading the current codebase (not assumed):

- **Auth**: `lib/auth/session.ts` — owner/admin roles, session read via `getStaffByUserId`. Working today, used by existing write forms.
- **Role foundation**: `public.staff` + `public.staff_permission_overrides` (migration `0010`, NCRM-07b) — schema exists, deny-by-default RLS, no per-role UI enforcement built yet (that was explicitly deferred to whoever consumes it first).
- **Repository Pattern**: `lib/repositories/*.repo.ts` — UI never queries Supabase directly. A `roadmap.repo.ts` would follow the same shape as `sales.repo.ts`/`reference.repo.ts`.
- **Existing screens**: `app/orders`, `app/purchases`, `app/sku`, `app/stock`, `app/customers`, `app/writeoffs` — all read/write screens follow one visual and code pattern already. A roadmap screen would be the same shape again, not a new paradigm.

None of this is a green field. The unresolved part is entirely product scope, not architecture.

## 3. Open product questions (need owner answers before a real plan/handoff)

**Feature depth** — pick a target, since building Notion-equivalent from scratch is a materially bigger job than a status table:

- *Minimal*: one table (Roadmap ID, Name, Status, Priority, Blocker, Owner Decision, Last Updated), filterable/sortable, no comments, no kanban, no page content per task.
- *Notion-equivalent*: kanban board, per-task freeform content/comments, history, search — effectively rebuilding what Notion already gives for free.
- *Something in between*: minimal table + freeform notes field per task, no kanban/comments.

**Migration approach**:

- Hard cutover (one day, Notion becomes read-only archive) vs. parallel run for N weeks (both updated, reconciled, then cutover) — mirrors the same trade-off already made for the CRM data migration itself (`plans/crm-new-platform-architecture_2026-06-26.md` §1: "Паралельно зі Sheets + разовий імпорт історії, потім cutover").
- Does Notion get fully retired, or kept as a permanent archive of history/handoff links (given handoffs/diagnostics/plans stay as Markdown files regardless — only the status *tracking* moves)?

**Access outside chat**:

- Notion has a phone app; NCRM today is `npm run dev` on localhost (see NCRM-17). Roadmap-in-NCRM is only checkable from the same place the rest of the CRM lives — worth confirming that's acceptable, especially if the owner ever wants to glance at task status away from the desktop.

**AUTO-\* and other non-ROADMAP_SOP series**:

- This session found the AUTO-\* series lives in the same Notion database but is *not* governed by `ROADMAP_SOP.md` at all (no page-ID registry, no writer rules, separate id collisions). Does NCRM-18 scope cover migrating that series too, or only the ST/NCRM/PAY/MKT-TG track `ROADMAP_SOP.md` already governs? Recommend the latter — folding in an already-messy, ungoverned series expands this task considerably and should be its own decision, not inherited by default.

## 4. Suggested next step (when the backlog clears)

Once NCRM-11–17 are actually closed, come back to the four questions in §3 as an `AskUserQuestion` pass, then write a proper schema-first plan doc (same shape as `plans/crm-new-platform-architecture_2026-06-26.md`) before any Codex handoff — per this task's own Notion card instruction, do not assume scope from this document alone.

## 5. Explicitly not decided here

- Table/column design for a `roadmap_tasks` schema.
- Whether this becomes a new NCRM migration number or a separate schema.
- Any UI mockup or screen layout.
