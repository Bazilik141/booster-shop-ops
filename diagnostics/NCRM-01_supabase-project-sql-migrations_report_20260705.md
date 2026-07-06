# Codex Report — NCRM-01: Supabase project, SQL migrations, and types

Date: 2026-07-05  
Remote verification: 2026-07-06

## Scope

Implemented the complete NCRM-01 handoff:

- initialized `ncrm/supabase/`;
- added ordered Stage 1–4 migrations;
- added confirmed lookup seeds only;
- added generated TypeScript database types;
- added local setup and rollback instructions;
- linked the local project to the owner's empty cloud Supabase project;
- pushed the same four migrations to the remote database and verified migration
  history parity.

No OpenCart, dashboard, patch, or existing CRM files were changed. No customer,
order, purchase, or stock data was imported.

Two schema details implicit in `schema-v1` were made explicit:

- normalized lookup tables for brand/category/game/language and purchase-lot
  statuses;
- minimal confirmed `MBX` and `MBX-XL` product identities, required for the
  approved mystery-box type and RRC seeds. No other products were seeded.

## Files touched

```text
ncrm/.gitignore
ncrm/README.md
ncrm/lib/types/database.ts
ncrm/supabase/.gitignore
ncrm/supabase/config.toml
ncrm/supabase/migrations/0001_stage1_core.sql
ncrm/supabase/migrations/0002_stage2_sales.sql
ncrm/supabase/migrations/0003_stage3_mystery_consumables.sql
ncrm/supabase/migrations/0004_stage4_expenses_reports.sql
diagnostics/NCRM-01_supabase-project-sql-migrations_report_20260705.md
```

## Dry-run result

The four migrations were applied in filename order to a temporary PostgreSQL
17.10 instance. Test data was wrapped in a transaction and rolled back.

```text
0001_stage1_core.sql                  ok
0002_stage2_sales.sql                ok
0003_stage3_mystery_consumables.sql  ok
0004_stage4_expenses_reports.sql     ok

tables=28
views=12
public_functions=11
```

Seed verification:

```text
sale_channels=5
payment_types=4
post_methods=5
order_statuses=7
payment_statuses=4
supplier_regions=4
mystery_box_types=2
currency_rates=1 (UAH)
```

Focused smoke:

```text
credit_servicing_pct=0.06
FIFO prro_unit=100, mgmt_unit=106, method=FIFO
MBX provisional_unit=450
MBX actual_unit=530, method=FIFO
v_pnl_monthly July row=yes
```

## Type generation

`ncrm/lib/types/database.ts` was generated from the applied PostgreSQL schema by
the official Supabase `postgres-meta` TypeScript generator:

```text
bytes=58338
missing_table_types=0
```

Docker Desktop and WSL 2 were subsequently installed. The owner successfully
ran the local Supabase stack, database reset, schema diff, and local type
generation workflow.

## Local Supabase verification

Owner-verified on 2026-07-06:

```text
supabase start: success
supabase db reset: success; migrations 0001–0004 applied
supabase db diff --local: No schema changes found
```

## Remote Phase B verification

The local project was linked to the new cloud Supabase project. The owner ran a
dry-run first, then applied the same four migrations to the empty remote
database:

```text
0001_stage1_core.sql                  applied
0002_stage2_sales.sql                applied
0003_stage3_mystery_consumables.sql  applied
0004_stage4_expenses_reports.sql     applied
```

Final migration-history verification:

```text
Local | Remote
0001  | 0001
0002  | 0002
0003  | 0003
0004  | 0004
```

The CLI emitted a non-blocking warning while caching the optional `pg-delta`
catalog because a temporary certificate file was missing. It occurred after all
four migrations were applied. `supabase migration list` confirmed exact local
and remote history parity.

## php -l result

Not applicable: NCRM-01 contains SQL, TOML, Markdown, and generated TypeScript;
no PHP files are present.

## Idempotency

Migrations are immutable, ordered database migrations. `supabase db reset`
drops/recreates the local database, applies `0001`–`0004` once, and recreates
the same confirmed seed sets. Re-running migration SQL directly against an
already-migrated database is intentionally unsupported.

## Rollback

Local rollback:

```bash
cd ncrm
npx supabase stop --no-backup
```

The remote project currently contains only the new schema and approved
reference seeds. Before real data import, remote rollback is project
re-creation or an explicitly reviewed down migration. Do not run
`db reset --linked`.

## QA checklist

- [x] `npx supabase start` exits successfully.
- [x] `npx supabase db reset` applies `0001`–`0004` without errors.
- [x] `npx supabase db diff --local` returns no schema drift.
- [x] `lib/types/database.ts` contains all 28 public tables.
- [x] No `.env`, project ref, DB password, or API key is tracked in `ncrm/`.
- [x] Remote `db push --dry-run` lists only `0001`–`0004`.
- [x] Remote `db push` applies `0001`–`0004`.
- [x] `supabase migration list` shows exact local/remote parity.

## Side effects / risks

- Local migrations rebuild only the local NCRM database.
- `fn_fix_sale_cogs` freezes FIFO COGS when a sale becomes actual.
- Mystery COGS changes from provisional to actual only after the expected pack
  count is complete.
- Europe/USA forwarder details, mixed Pokémon/One Piece consumable rules, and
  the real consumables inventory remain intentionally unseeded pending owner
  decisions.
- NCRM-01 technical DoD is complete. The task is ready for status `Done` in
  Notion by Claude/owner.
