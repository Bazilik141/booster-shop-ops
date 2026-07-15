# Codex Handoff — NCRM-07b: Enable RLS on public schema + multi-user role foundation

Date: 2026-07-15 | Parent: NCRM-07 (must be Done — owner QA — before this ships to Codex)

## 1. Task ID
NCRM-07b. Notion page_id: not yet registered (new card, 2026-07-15 — see `ROADMAP_SOP.md` §5 once added).

## 2. Context
Supabase Security Advisor flags every public-schema table as RLS-disabled; the project is already cloud-linked (`ncrm/supabase/.temp/project-ref` is populated), so this is a live finding, not hypothetical. Owner decision (2026-07-15): don't ship a bare "enable RLS" single-owner shortcut — lay the multi-user role foundation now, even though today there is exactly one user (owner). Full rationale, options considered, and the confirmed role model: `plans/NCRM-07b_rls-multiuser-role-model_20260715.md`. Canonical NCRM architecture reference updated: `plans/NCRM-financial-model-v2_technical-contract_20260711.md` §10 (addendum).

## 3. Goal
Enable RLS deny-by-default on every public table (zero behavior change — the app uses `service_role` exclusively today, which always bypasses RLS regardless of policies), and add the minimal schema foundation for a future multi-user role model — without building login UI or per-role enforcement logic yet. That enforcement work is NCRM-08/09, once a real screen exists to consume it.

## 4. What to change
One additive migration, `ncrm/supabase/migrations/0010_*.sql` (next free number after `0009` — confirm before writing, don't assume):

- `ALTER TABLE ... ENABLE ROW LEVEL SECURITY` on all base tables created in `0001`–`0008` (36 confirmed via `create table` grep at plan time — re-verify against `\dt public.*` after `db reset`, don't hand-type the list from this doc).
- Add `security_invoker = true` to the 7 views in `0009_reporting_forecast_kpi.sql`: `v_current_rrc`, `v_pnl_monthly`, `v_cost_quality_exposure`, `v_unpriced_inventory`, `v_forecast_margin`, `v_inventory_dashboard_guardrails`, `v_data_quality`. Syntax: `alter view public.<name> set (security_invoker = true);` (Postgres 17 per `supabase/config.toml` — supported since PG15). Without this, these views run with definer privileges and can silently bypass RLS later.
- New table `public.staff`:
  `id uuid primary key references auth.users(id) on delete cascade`,
  `role text not null check (role in ('owner','admin','user_plus','user'))`,
  `display_name text`,
  `created_at timestamptz not null default now()`.
- New table `public.staff_permission_overrides`:
  `id bigint generated always as identity primary key`,
  `staff_id uuid not null references public.staff(id) on delete cascade`,
  `permission_key text not null` (free text, no enum/fixed list — do not invent a permission taxonomy, owner defines these later per role),
  `granted boolean not null default true`,
  `created_at timestamptz not null default now()`,
  `unique (staff_id, permission_key)`.
- Add nullable `created_by uuid references auth.users(id)` to `sales`, `purchases`, `writeoffs`, `mystery_fulfillments` (confirmed at plan time: none of the four currently has an actor/owner column).
- No RLS policies for `anon`/`authenticated` on any table — deny-by-default is the entire point of this task (see Risks).
- No grant changes — `0005`'s `service_role`-only grants stand as-is.
- After the migration: `npx supabase db reset`, then regenerate `lib/types/database.ts` per `ncrm/README.md` (`npx supabase gen types typescript --local --schema public > lib/types/database.ts`).
- Seed one `staff` row for the owner (`role = 'owner'`) against whatever local `auth.users` row exists after `db reset`. If no local `auth.users` row exists, create one via Supabase CLI/auth admin tooling, or state clearly in the report that seeding was skipped and why.

## 5. What NOT to touch
- `ncrm/supabase/migrations/0001`–`0009` — additive only, no edits to prior migrations.
- `0005` grants — do not add `anon`/`authenticated` grants anywhere.
- Any RLS *policy* for `anon`/`authenticated` — this task is deny-by-default only. Real per-role policies are explicitly NCRM-08/09, not this task.
- `lib/repositories/*`, `lib/supabase/client.ts` — no application-code changes; this is schema-only. The `can(staffRole, permissionKey, overrides)` app-layer helper described in the plan doc is an NCRM-08/09 concern, not part of this migration.
- Admin/user exact permission grants — owner said these get decided later; do not invent a permission list or assign default grants.
- Any login/auth UI.
- Cloud push — this and all prior NCRM migrations are local-only (`db reset`). Do not run `supabase db push` or otherwise touch the linked cloud project.
- Standard repo-wide protected zones, listed per handoff convention though none apply here: `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, storefront schema.org markup. This task is entirely inside the `ncrm/` Supabase/Next.js subproject, isolated from the OpenCart storefront — n/a, confirmed by task scope.

## 6. Likely files / areas
- `ncrm/supabase/migrations/0010_*.sql` (new — likely name/number, confirm the next free one)
- `ncrm/lib/types/database.ts` (regenerated by CLI, never hand-edited)

Codex should verify the actual current migration count and table list against project files before writing — do not assume this doc's table list is exhaustive; confirm against `\dt public.*` post-reset.

## 7. Acceptance criteria
- [ ] `npx supabase db reset` completes with no errors.
- [ ] `npx supabase db diff --local` is clean after reset.
- [ ] `relrowsecurity` is `true` for every table from `0001`–`0008` (check via `pg_class`/`\d+`).
- [ ] The 7 views from `0009` show `security_invoker=true` in `reloptions`.
- [ ] `public.staff` and `public.staff_permission_overrides` exist with exactly the columns in §4.
- [ ] `created_by` (nullable uuid) exists on `sales`, `purchases`, `writeoffs`, `mystery_fulfillments`.
- [ ] `npm run build` succeeds unchanged — zero application behavior change.
- [ ] `lib/types/database.ts` regenerated and includes the new tables/columns.

## 8. QA / smoke test (owner runs after Codex delivers)
CRM-data risk — see Risks below.
- [ ] Manual REST call to any public table using `NEXT_PUBLIC_SUPABASE_ANON_KEY` with no session → expect empty/403, not data. This is the actual proof RLS blocks, not just "enabled on paper."
- [ ] `npm run dev`, exercise the existing analytics/repository read paths (whatever NCRM-01..07 already verified) — confirm unchanged, since `service_role` bypasses RLS.
- [ ] Supabase Dashboard → Security Advisor → RLS warnings for public schema are gone.
- [ ] Codex's own report (`diagnostics/NCRM-07b_..._report_<date>.md`) documents exact table count RLS was enabled on, confirms no policy was added anywhere, and states the seed decision for the owner `staff` row.

## 9. Rollback note
Additive-only migration — no data deleted, no destructive DDL, no existing column altered or dropped. Local rollback: omit/revert `0010` and `db reset` replays cleanly from `0001`. If this were ever pushed to the linked cloud project (explicitly out of scope here, see §5): a follow-up migration doing `ALTER TABLE ... DISABLE ROW LEVEL SECURITY`, dropping `staff_permission_overrides`/`staff`, and dropping the four `created_by` columns would fully revert — not needed for this task since no cloud push happens here.

## 10. Recommended status after execution
`In progress → Codex done, owner QA pending` (same convention as NCRM-07's current state) — not `Done` until owner runs the §8 smoke test and confirms Security Advisor is clean. Note: NCRM-07b remains blocked upstream by NCRM-07 itself (owner QA not yet passed on that task) — this handoff is prepared in advance per owner's request and should only actually go to Codex once NCRM-07 closes.

## Risks
Touches the new CRM's core database (Supabase/Postgres) — named plainly: if a permissive policy (e.g. `USING (true)` for `authenticated`) gets added instead of staying deny-by-default, it opens all business data to anyone holding the anon key, which is worse than having no RLS at all because it looks protected and isn't. Deny-by-default plus the anon-key smoke test in §8 is the guard against that. Storefront/SEO risk: none — `ncrm/` is a separate subproject from the OpenCart site; `bs-seo-risk-gate`, `bs-checkout-smoke`, and `bs-merchant-schema-qa` don't apply (no checkout, payment, fiscalization, schema, or Merchant feed involved).
