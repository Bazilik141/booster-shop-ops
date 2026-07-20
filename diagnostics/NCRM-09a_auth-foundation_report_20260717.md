# Codex Report — NCRM-09a: auth foundation

Date: 2026-07-17

## Scope

Implemented the `owner`/`admin` authentication foundation from the NCRM-09a handoff: email/password login, cookie-backed Supabase session identity, route gate, clear access-pending state, header identity/logout, owner seed script, and explicit `createdBy` forwarding into existing sale, purchase, and writeoff repository mutations.

No migration, write-form UI, refund/mystery repository work, per-role RLS policy, `user_plus`/`user` permission logic, or protected live-store area was changed.

## Files touched

```
ncrm/lib/supabase/client.ts                    — session-aware auth client; service-role path unchanged
ncrm/lib/auth/session.ts                       — current staff and allowed-role helpers
ncrm/middleware.ts                             — owner/admin route gate and session-cookie refresh
ncrm/app/login/page.tsx                        — login route
ncrm/app/login/login-form.tsx                  — browser email/password sign-in
ncrm/app/access-pending/page.tsx               — blocked-role/no-staff message
ncrm/app/components/sign-out-button.tsx        — browser sign-out
ncrm/app/layout.tsx                            — authenticated staff identity and logout control
ncrm/lib/domain/{sales,purchases,writeoffs}.ts — required `createdBy` mutation payload field
ncrm/lib/repositories/{sales,purchases,writeoffs}.repo.ts — persist `created_by`
ncrm/scripts/seed-owner.mjs                    — manual, local owner/user seed
ncrm/.env.example                              — OWNER_EMAIL/OWNER_PASSWORD documentation
```

## Verification result

```
npm run build
✓ Compiled successfully
✓ Running TypeScript
✓ Generating static pages
ƒ Proxy (Middleware)

node --check scripts/seed-owner.mjs
exit=0

node scripts/seed-owner.mjs
done=error message=Missing required OWNER_EMAIL in .env.local.

git diff --check
exit=0

git diff -- ncrm/supabase/migrations
no output
```

`npm run build` emits only Next.js 16's deprecation warning for the handoff-required `middleware.ts` naming; it compiles and registers the route gate. No change to `proxy.ts` was made because that would diverge from the scoped handoff.

`created_by` verification is static for 09a because there is still no write-form UI and no safe fixture IDs were provided for a database mutation: each payload now requires `createdBy: string`, and each matching `TablesInsert` maps it directly to `created_by`. The owner QA below must confirm one real test mutation per available repository path before NCRM-09b starts.

## Idempotency

`seed-owner.mjs` first finds an existing Auth user by normalized email and then reads the matching `staff` row. A repeat run leaves an existing `owner` row unchanged and returns `done=ok auth_user=existing staff=existing role=owner`. If an existing staff row has a different role, it fails rather than silently escalating it.

## Rollback

No database schema or live-store state changed. Revert only the files listed above. If an owner account was seeded locally, remove that Auth user in local Supabase Studio; its `staff` row cascades through the foreign key.

## Run commands (owner, local only)

```bash
cd ncrm || exit
npx supabase start && npx supabase db reset
# Add OWNER_EMAIL and OWNER_PASSWORD to .env.local; never commit that file.
node scripts/seed-owner.mjs
npm run dev
```

## Post-QA checklist

- [ ] Log in as seeded `owner`; verify `/`, `/orders`, `/stock`, `/sku`, `/customers` load.
- [ ] Log out, then open `/orders` directly; verify redirect to `/login`.
- [ ] Log in as a user without `staff`, then with `staff.role='user'`; verify `/access-pending` rather than a pass-through or crash.
- [ ] With safe local fixture IDs, call each of `addSale`, `addPurchase`, and `addWriteoff` with the current staff id; verify the inserted parent row's `created_by` equals that Auth id.
- [ ] Confirm `.env.local` and real credentials are absent from `git status` staging.
- [ ] Keep the app local-only; no public deployment is in scope.

## Side effects / risks

This is the first real access gate for NCRM financial data. `public.staff` remains deny-by-default under RLS, so the session client only validates Auth identity while a narrow server-only helper reads the staff role through the existing service-role repository path. The browser receives no service-role key.

`user_plus`, `user`, and `staff_permission_overrides` are intentionally not implemented; each is blocked by the gate until its separate permission definition exists. NCRM-09 stays `In progress` pending the owner smoke test and independent review; NCRM-09b/09c must not start from this diff alone.
