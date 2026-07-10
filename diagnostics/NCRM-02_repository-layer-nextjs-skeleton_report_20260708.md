# Codex Report — NCRM-02: Repository layer + Next.js skeleton

Date: 2026-07-08

## Scope
Handoff scope implemented inside `ncrm/`: Next.js App Router skeleton, typed
Supabase client entrypoint, clean domain types, repository layer, root read-demo
page, `.env.example`, README instructions, and npm lockfile.

`ncrm/supabase/migrations/*` from NCRM-01 were not edited.

## Files touched
```
ncrm/.env.example
ncrm/.gitignore
ncrm/README.md
ncrm/app/globals.css
ncrm/app/layout.tsx
ncrm/app/page.tsx
ncrm/lib/domain/*
ncrm/lib/repositories/*
ncrm/lib/supabase/client.ts
ncrm/next-env.d.ts
ncrm/next.config.ts
ncrm/package-lock.json
ncrm/package.json
ncrm/supabase/migrations/0005_grants_for_app_read.sql
ncrm/tsconfig.json
diagnostics/NCRM-02_repository-layer-nextjs-skeleton_report_20260708.md
```

## Implementation notes
- `lib/supabase/client.ts` is the only Supabase client initializer.
- UI/root route imports `analytics.repo.ts`; it does not call Supabase directly.
- Repository methods implemented:
  - `sales.repo.ts`: `listSales()`, `getSale()`, `addSale()`, `updateSaleStatus()`
  - `purchases.repo.ts`: `listPurchaseLots()`, `addPurchase()`, `batchUpdateStatus()`
  - `writeoffs.repo.ts`: `listWriteoffs()`, `getWriteoff()`, `addWriteoff()`
  - `products.repo.ts`: `listProducts()`, `listSku()`, `updateRrc()`
  - `analytics.repo.ts`: `getSummary()`, `getStock()`, `getSkuMetrics()`
- `analytics.repo.ts` reads NCRM-01 views/tables:
  `v_sales_report`, `v_pnl_monthly`, `v_stock_alerts`, `v_top_skus`, `products`.
- `0005_grants_for_app_read.sql` grants PostgREST table/view/function access to
  `service_role` only. It intentionally does not grant table/view access to
  public `anon`.
- Package versions were checked from npm on 2026-07-08:
  Next `16.2.10`, React `19.2.7`, Supabase JS `2.110.1`,
  Supabase SSR `0.12.0`.
- `postcss` is overridden to `8.5.16` because `next@16.2.10` pins vulnerable
  `postcss@8.4.31`; build was re-run after the override.

## Verification

### npm install
```
added/changed packages successfully
found 0 vulnerabilities after postcss override
```

### npm audit
```
npm audit --omit=dev --json
metadata.vulnerabilities.total = 0
```

### Next.js build
```
npm run build
✓ Compiled successfully
Finished TypeScript
Route (app)
┌ ƒ /
└ ○ /_not-found
```

### Repository boundary
```
rg ".from(" ncrm -g "*.ts" -g "*.tsx"
```

All `.from(...)` calls are under:
```
ncrm/lib/repositories/*
```

No page/component file calls Supabase directly.

### Migration safety
```
git diff -- ncrm/supabase/migrations
```

No diff. NCRM-01 SQL migrations were not touched.

## Emulator smoke test
Initial Codex attempt to start local Supabase:
```
npx supabase start
```

Result:
```
failed to inspect service ... open //./pipe/dockerDesktopLinuxEngine:
The system cannot find the file specified.
Docker Desktop is a prerequisite for local development.
```

Follow-up owner run on 2026-07-08:
- Docker/Supabase emulator was running.
- `.env.local` was corrected to use local `ANON_KEY` and `SERVICE_ROLE_KEY`
  from `npx supabase status -o env` instead of the newer publishable/secret
  key labels shown in the pretty status screen.
- Local PostgREST initially returned:
  `permission denied for view v_sales_report` / `permission denied for table products`.
- Owner applied a local dev grant manually.
- Root route `http://localhost:3000/` rendered real Supabase-backed data:
  product count `2`, stock alert rows `MBX` and `MBX-XL`, and empty sales/P&L
  metrics from the current seeded local database.

Conclusion before `0005`: NCRM-02 read path worked against the local Supabase
emulator, but the manual grant was local state and would be lost after
`npx supabase db reset`.

Follow-up authorized by owner on 2026-07-08:
- Added `0005_grants_for_app_read.sql`.
- Restricted durable grants to `service_role` only.
- Updated the repository Supabase client so server repositories require
  `SUPABASE_SERVICE_ROLE_KEY` instead of silently falling back to anon.

Reset-safe verification after `0005`:
```
npx supabase db reset
Applying migration 0005_grants_for_app_read.sql...
Finished supabase db reset on branch master.
```

Post-reset REST check:
```
anon failed_status=401
service_role status=200 length=217
```

This is the intended security shape for NCRM-02: browser/public `anon` cannot
read CRM tables/views directly; the server-side repository layer can read via
`SUPABASE_SERVICE_ROLE_KEY`.

Schema drift check:
```
npx supabase db diff --local
No schema changes found
```

## php -l result
N/A — this task does not create a PHP host patch.

## Idempotency
N/A — this task adds source files, not a rerunnable host patch.

## Rollback
Before NCRM-02 is committed, rollback is deleting the new Next.js files and
restoring touched tracked files:
```
ncrm/.env.example
ncrm/app/
ncrm/lib/domain/
ncrm/lib/repositories/
ncrm/lib/supabase/
ncrm/next-env.d.ts
ncrm/next.config.ts
ncrm/package-lock.json
ncrm/package.json
ncrm/supabase/migrations/0005_grants_for_app_read.sql
ncrm/tsconfig.json
```

Keep previous NCRM-01 migrations and `ncrm/lib/types/database.ts`.

## Run command (owner)
PowerShell:
```powershell
cd "C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\ncrm"
npx supabase start
npx supabase db reset
Copy-Item .env.example .env.local
npm install
npm run dev
```

Fill `.env.local` from local Supabase status values. Do not paste keys into chat
and do not commit `.env.local`.

Production build check:
```powershell
npm run build
```

## Post-deploy / local QA checklist
- [x] `npm install` succeeds.
- [x] `npm audit --omit=dev` reports `0` vulnerabilities.
- [x] `npm run build` succeeds.
- [x] Five required repo files exist with handoff methods.
- [x] No direct Supabase query calls outside `lib/repositories/*`.
- [x] `ncrm/.env.example` contains names only; `.env.local` not created/committed.
- [x] NCRM-01 migrations `0001`–`0004` untouched.
- [x] Follow-up `0005` migration added with service-role grants only.
- [x] `npx supabase db reset` applies `0005` successfully.
- [x] Post-reset REST check: `service_role` can read `v_sales_report`.
- [x] Post-reset REST check: `anon` remains blocked from direct CRM view reads.
- [x] `npx supabase db diff --local` reports no schema changes.
- [x] Owner starts Docker Desktop and verifies `npx supabase start`.
- [x] Owner fills local `.env.local`, runs `npm run dev`, opens root route, and confirms real Supabase read-demo.
- [ ] Owner optionally runs `npx supabase db reset` to re-confirm NCRM-01 emulator path.

## Side effects / risks
- No OpenCart/site/dashboard/payment/checkout files touched by this task.
- Existing unrelated repo changes are present in the working tree; they were not
  modified by this NCRM-02 implementation.
- `SUPABASE_SERVICE_ROLE_KEY` is optional and must stay server-side/local only.
  `.env.local` is gitignored.
- Owner-confirmed read demo means the functional NCRM-02 DoD is met.
- Reset-safe grants are now represented in `0005_grants_for_app_read.sql`.
- `anon` is intentionally not granted table/view access; browser-side direct
  Supabase reads remain blocked until a later auth/RLS task explicitly designs
  that surface.
