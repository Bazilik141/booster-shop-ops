# Codex Report — NCRM-07b: RLS and multi-user role foundation

Date: 2026-07-15

## Scope

Implemented the handoff as one additive local Supabase migration. RLS is enabled deny-by-default on every public base table, with no RLS policy for any role. `staff` and `staff_permission_overrides` are schema-only foundations; no login UI, permission matrix, repository enforcement, cloud push, or grant change was made.

## Files touched

```text
ncrm/supabase/migrations/0010_rls_multiuser_role_foundation.sql
ncrm/lib/types/database.ts
diagnostics/NCRM-07b_rls-multiuser-role-foundation_report_20260715.md
dashboard/booster-dashboard.html
../booster-dashboard.html
```

## Local validation

`cd ncrm && npx supabase db reset` completed with exit code 0 and applied migrations `0001` through `0010`.

```text
Applying migration 0008_returns_cost_quality.sql...
Applying migration 0009_reporting_forecast_kpi.sql...
Applying migration 0010_rls_multiuser_role_foundation.sql...
Restarting containers...
Finished supabase db reset on branch master.
```

`cd ncrm && npx supabase db diff --local` completed with exit code 0:

```text
{"diff":"","file":null,"schemas":[],"engine":"pg-delta","dropStatements":[],"message":"Diff complete."}
No schema changes found
```

Database assertions after reset:

```text
public_table_count=38
rls_enabled_count=38
rls_disabled_count=0
all_public_rls_policy_count=0
anon_products_status=401; body_length=186
local_auth_user_count=0
```

All seven required views have `security_invoker=true` in `reloptions`:
`v_current_rrc`, `v_pnl_monthly`, `v_cost_quality_exposure`,
`v_unpriced_inventory`, `v_forecast_margin`,
`v_inventory_dashboard_guardrails`, and `v_data_quality`.

`staff` and `staff_permission_overrides` have exactly the columns specified in the handoff. Nullable `created_by uuid` exists on `sales`, `purchases`, `writeoffs`, and `mystery_fulfillments`.

`npx supabase gen types typescript --local --schema public > lib/types/database.ts` completed successfully. The generated types include both role tables and all four `created_by` fields.

`npm run build` completed successfully after one sandbox-only `EPERM` writing `.next/trace-build`; the retry outside the sandbox compiled, type-checked, and generated both static routes successfully.

## Owner staff seed

No local `auth.users` row exists after `db reset` (`local_auth_user_count=0`). No synthetic user, email, or password was created, so no `staff` owner row was seeded. This is intentional: assigning `owner` to an invented or arbitrary auth user would create misleading security state. Seed the real owner only when the owner creates the intended Supabase Auth account in the later auth/login task.

## Idempotency

The migration is replay-safe through the normal local lifecycle: a fresh `db reset` rebuilds `0001`–`0010` cleanly. It is not designed for a second manual execution against an already-migrated database; migrations are tracked and applied once by Supabase.

## Rollback

Local-only rollback: remove `0010_rls_multiuser_role_foundation.sql` and run:

```bash
cd ncrm && npx supabase db reset
```

No cloud migration was pushed. If a future owner deliberately applies `0010` to cloud, rollback must be a dedicated reverse-DDL migration: disable RLS on the affected tables, drop `staff_permission_overrides` then `staff`, drop the four `created_by` columns, and unset `security_invoker` on the seven views.

## Owner QA checklist

- [ ] Do not run `supabase db push` as part of this task; cloud remains untouched.
- [ ] When cloud rollout is explicitly approved later, make an unauthenticated anon-key REST call to a public table and confirm `401`/`403` or no rows.
- [ ] In Supabase Dashboard Security Advisor, confirm public-table RLS warnings are gone after that separately approved cloud rollout.
- [ ] After the real owner Auth account is created, insert the matching `staff` row with `role='owner'`; do not assign the role to a placeholder account.

## Side effects and risks

RLS now denies direct `anon` and `authenticated` table access until NCRM-08/09 add intentionally scoped policies and application-layer authorization. `service_role` behavior and the current server-side repository architecture remain unchanged. No permissive policy such as `USING (true)` was added.
