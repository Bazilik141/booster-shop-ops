# Codex Handoff — NCRM-09a: Auth-фундамент (owner/admin)

Date: 2026-07-17 | Parent: NCRM-09 ("Write-форми + FIFO-COGS"), split into 09a (this task) / 09b (продаж+закупка форми) / 09c (списання/РРЦ/повернення/mystery box). Blocker NCRM-08 is CLOSED Done (2026-07-17), NCRM-07b is Done (2026-07-15). See `plans/NCRM-09_write-forms-auth-split_20260717.md` for the full split rationale.

## 1. Task ID
NCRM-09a — Auth-фундамент: логін, сесія, route-gate для ролей `owner`/`admin`, seed owner-акаунта, `created_by` wiring в уже існуючі repo-мутації. Notion parent card NCRM-09 status: `Not started` → `In progress` (flipped this session).

## 2. Context
- Repo-layer convention (`ncrm/README.md`, NCRM-02/08): UI (`ncrm/app/*`) never queries Supabase directly — only `ncrm/lib/repositories/*.repo.ts` may call the Supabase client, returning typed domain objects (`ncrm/lib/domain/*.ts`).
- `ncrm/lib/supabase/client.ts` currently exposes: `createSupabaseServerClient({useServiceRole})` (no session persistence, used server-side), `createSupabaseBrowserAppClient()` (anon key, browser, exists but **unused anywhere today**), and `createRepositoryClient()` (= service role, what every `*.repo.ts` uses). None of these are cookie/session-aware — there is no way today for the app to know who is logged in.
- **No auth exists anywhere in `ncrm/` today.** No `middleware.ts`, no login route, no session handling. All five read screens (NCRM-08) are open to anyone who can reach `npm run dev` — acceptable only because the app is local-only and never deployed (explicitly flagged in NCRM-08 §8, still true).
- Migration `0010` (NCRM-07b, Done) already created the schema this task builds on: `public.staff` (`id references auth.users(id)`, `role in ('owner','admin','user_plus','user')`), `public.staff_permission_overrides`, and a `created_by uuid references auth.users(id)` column on `sales`, `purchases`, `writeoffs`, `mystery_fulfillments`. **No `auth.users` row and no `staff` row exist yet** — the owner seed was deliberately skipped in NCRM-07b pending real auth (dashboard warn note, 2026-07-15).
- **Discovery made while scoping this task (verify before relying on it):** `ncrm/lib/repositories/sales.repo.ts` (`addSale`, `updateSaleStatus`), `purchases.repo.ts` (`addPurchase`), `writeoffs.repo.ts` (`addWriteoff`), and `products.repo.ts` (`updateRrc`) already contain working insert/update logic against the current schema — written ahead of any UI or auth, currently called by nothing. None of them set `created_by`. This task does not build write-form UI (that's 09b/09c) but **does** need to extend these four functions to accept and persist the acting user's id, since that's the one piece only auth can provide. Verify the current signatures against actual file contents before changing them — do not assume the shape described here is exhaustive.
- FIFO/COGS calculation itself is **not in scope and already works**: triggers `fn_fix_sale_cogs`/`fn_fix_new_sale_item`/`fn_fifo_cost_for_product` (migration `0006`) fire automatically on `sale_items` insert. Nothing here touches that logic.
- Access-control decision already made and owner-approved (`plans/NCRM-financial-model-v2_technical-contract_20260711.md §10`, `plans/NCRM-07b_...md §6`): enforcement lives in the **application layer** (Next.js), not as Postgres RLS-per-role policies — RLS stays deny-by-default as defense-in-depth only. Do not add per-role RLS policies in this task.
- Scope explicitly narrowed by owner (2026-07-17, this session): this task covers **`owner` and `admin` roles only**. `user_plus`/`user` exact permissions are still undefined ("owner will assign later" — NCRM-07b §5.1) and are out of scope here. Treat any authenticated user without a `staff` row, or with role `user_plus`/`user`, as **blocked** (not silently granted access) — do not build partial permission logic for roles that have no defined ruleset yet.

## 3. Goal
Give the app a real login (Supabase Auth, email/password — already decided in NCRM-07b §5.3), a session-aware way to know who is acting, and a route gate that only lets `owner`/`admin` staff reach the app. Wire the one missing piece (`created_by`) into the four existing repo mutation functions so future write-form work (09b/09c) inherits a working session from day one instead of bolting it on per form.

## 4. What to change (scope)
- `ncrm/lib/supabase/client.ts` — add a new cookie/session-aware client factory using `@supabase/ssr`'s `createServerClient` (already a dependency, version `0.12.0` — verify API surface against that exact version before writing code) for use in `middleware.ts` and server components/actions that need to read the current session. This must be a **separate function** from `createRepositoryClient()` — the existing service-role client must keep being the only thing that touches business tables (`ncrm/README.md` rule), the new session client is for auth/identity only (reading `auth.uid()`/`staff` role), never for querying `sales`/`purchases`/etc. directly.
- New `ncrm/lib/auth/` module (naming at Codex's discretion, e.g. `session.ts`) — a helper like `getCurrentStaff()` that returns `{ id, role } | null` by combining the session client's current user with a `staff` table lookup (through the repository layer's existing pattern, or a small dedicated read — verify the least-surprising way to do this against how `_utils.ts`/`repositoryError` are used elsewhere). Must NOT special-case `owner` by hardcoding an email/id — role comes from the `staff.role` column, matching NCRM-07b's decision ("if role === 'owner' return true" was a **code-level** shortcut for permission checks, not a hardcoded identity check).
- New `ncrm/middleware.ts` — gate all routes except `/login` (and Next.js static/internal paths). Unauthenticated → redirect to `/login`. Authenticated but no `staff` row, or `staff.role` not in `('owner','admin')` → block with a clear "access pending" state (a simple page/message is enough, not a full UX), not a silent pass-through and not a raw 500.
- New `ncrm/app/login/page.tsx` (+ any supporting client component) — email/password sign-in via `createSupabaseBrowserAppClient()` (already exists, unused today). On success, redirect to `/`.
- `ncrm/app/layout.tsx` — add sign-out control and (when logged in) show `staff.display_name`/role in the header nav, alongside the existing five links from NCRM-08. Keep styling at the existing tech-demo level — not a design task.
- `ncrm/lib/repositories/sales.repo.ts` (`addSale`), `purchases.repo.ts` (`addPurchase`), `writeoffs.repo.ts` (`addWriteoff`) — extend each to accept the acting user's id (e.g. an added `createdBy` field on the existing payload type, or an extra parameter — Codex's call, but must be explicit, not inferred silently inside the repo function from some ambient global) and persist it into the `created_by` column added in `0010`. `products.repo.ts` (`updateRrc`) — **no `created_by` change**, `product_prices` has no such column (confirmed absent from `0010`); leave as-is.
- `ncrm/lib/domain/sales.ts`/`purchases.ts`/`writeoffs.ts` — extend the relevant payload types to carry the new field, matching whatever approach was chosen above.
- New `ncrm/scripts/seed-owner.mjs` (one-off, run manually and locally only, never automatically) — reads `OWNER_EMAIL`/`OWNER_PASSWORD` from `.env.local` (new, gitignored vars — do not add these as `NEXT_PUBLIC_*`), uses the Supabase Auth **admin API** (`service_role` key, `supabase.auth.admin.createUser`) to create the `auth.users` row, then inserts the matching `public.staff` row with `role = 'owner'`. Must error clearly and exit non-zero if the env vars are missing. No credentials hardcoded anywhere in the diff.
- `ncrm/.env.example` — document `OWNER_EMAIL`/`OWNER_PASSWORD` as script-only inputs (comment that they're for `seed-owner.mjs`, not read by the Next.js app itself).

## 5. What NOT to touch
- No write-form UI for sale/purchase/writeoff/RRC/return/mystery box — that is NCRM-09b/09c, explicitly deferred by the owner-approved split (`plans/NCRM-09_write-forms-auth-split_20260717.md`).
- No new/changed repo function for refunds or mystery-box fulfillment (`fn_reserve_mystery_fulfillment`/`fn_commit_mystery_fulfillment`) — those don't have a repo wrapper yet and are NCRM-09c territory.
- No Postgres RLS-per-role policies. RLS stays deny-by-default exactly as `0010` left it — enforcement is application-layer only (see Context).
- No `user_plus`/`user` permission logic, no `staff_permission_overrides` reads/writes — undefined ruleset, out of scope for this task.
- `ncrm/supabase/migrations/0001`–`0010` — no edits. This task should not need a new migration at all (the schema it needs already exists from `0010`). If Codex concludes a migration is genuinely required, **stop and flag it in the report** rather than adding one silently — mirror the NCRM-08 convention.
- `ncrm/scripts/import-history/*`, `ncrm/import/*` — untouched, separate territory (NCRM-03).
- Live Apps Script CRM, Google Sheet, OpenCart — separate codebase, never written to by this task.
- Standard protected zones (required minimum, not technically present in `ncrm/`, confirm no accidental cross-touch): `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, Product schema.
- Do not commit `.env.local` or any real email/password/token — verify `.gitignore` already covers it (per `ncrm/README.md`) before assuming.

## 6. Likely files/areas
- `ncrm/lib/supabase/client.ts` — new session-aware client factory
- New `ncrm/lib/auth/session.ts` (or similar) — `getCurrentStaff()` helper
- New `ncrm/middleware.ts`
- New `ncrm/app/login/page.tsx`
- `ncrm/app/layout.tsx` — sign-out + current-user display
- `ncrm/lib/repositories/sales.repo.ts`, `purchases.repo.ts`, `writeoffs.repo.ts` — `created_by` wiring
- `ncrm/lib/domain/sales.ts`, `purchases.ts`, `writeoffs.ts` — payload type extensions
- New `ncrm/scripts/seed-owner.mjs`
- `ncrm/.env.example` — document script-only vars
- `diagnostics/NCRM-09a_auth-foundation_report_20260717.md` (new, Codex's own report)
- No changes expected in `ncrm/supabase/migrations/*` — verify against actual project files before assuming otherwise.

## 7. Acceptance criteria
- [ ] `cd ncrm && npm run build` succeeds, 0 errors
- [ ] `git diff` on `ncrm/supabase/migrations/*` is empty (or: Codex stopped and flagged why one was needed, per §5)
- [ ] Unauthenticated visit to `/`, `/orders`, `/stock`, `/sku`, `/customers` redirects to `/login`
- [ ] Sign-in with an `owner`- or `admin`-role staff account reaches all five existing read screens with no regression from NCRM-08
- [ ] An authenticated user with no `staff` row, or with role `user_plus`/`user`, is blocked with a clear message — not a crash, not a silent bypass
- [ ] `addSale`/`addPurchase`/`addWriteoff` persist a non-null `created_by` equal to the current session's `auth.users.id` when called with a real session (Codex documents in the report how this was verified for 09a alone, e.g. a temporary test call/script, since no write-form UI exists yet)
- [ ] `node scripts/seed-owner.mjs` (run locally with `OWNER_EMAIL`/`OWNER_PASSWORD` set) creates exactly one `auth.users` + matching `staff` (`role='owner'`) row, and fails clearly with the env vars unset
- [ ] No file under `ncrm/app/` imports the service-role repository client (`createRepositoryClient`) for session/role checks — auth/session logic goes through the new session-aware client only; business data queries stay in `lib/repositories/*` exclusively (grep-verifiable, same rule as NCRM-08 §7)
- [ ] No Postgres RLS policy added anywhere (`git diff` on migrations confirms this along with the empty-migration check above)
- [ ] Report explicitly states: which Supabase client variant backs session/auth (must not be the service-role client), confirms `user_plus`/`user`/`staff_permission_overrides` logic was NOT implemented (out of scope), and confirms the exact shape chosen for passing `createdBy` into the three repo functions

## 8. QA / smoke test (owner)
Not checkout/payment/site-schema — `bs-checkout-smoke`/`bs-merchant-schema-qa` not applicable (separate local Next.js/Supabase app, nothing deployed to the live site, `bs-seo-risk-gate` not applicable). Real risk to name directly: this is the first time the app has any real identity/session layer, guarding the same full financial data (revenue, margin, COGS, RRC) NCRM-08 already exposed with no auth at all. A broken or bypassable gate here is worse than no gate, because it will look protected while not being protected — same failure mode NCRM-07b flagged for RLS `USING (true)`. **Do not deploy this anywhere network-reachable** (Vercel, a public URL, a shared machine) regardless of how this task turns out — that remains a separate owner decision, unchanged from NCRM-08 §8.

- [ ] `cd ncrm && npx supabase start && npx supabase db reset`, then `npm run dev` — app loads with no runtime Supabase error
- [ ] Add `OWNER_EMAIL`/`OWNER_PASSWORD` to local `.env.local` (never commit), run `node scripts/seed-owner.mjs`, confirm exactly one `auth.users` + `staff` row created (`role='owner'`)
- [ ] Log in at `/login` as the seeded owner — confirm access to `/`, `/orders`, `/stock`, `/sku`, `/customers`
- [ ] Log out — confirm redirect to `/login`, and that typing `/orders` directly in the URL bar also redirects (not just the nav link hidden)
- [ ] Manually create a second `auth.users` row (Supabase Studio) with no matching `staff` row — log in as that user, confirm blocked with a clear message
- [ ] Manually add a `staff` row with `role='user'` for a third test user — log in, confirm still blocked (owner/admin only, this task)
- [ ] `git status` before commit — confirm `.env.local`, `OWNER_EMAIL`, `OWNER_PASSWORD`, and any real key/password are not staged
- [ ] Confirm still local-only (`npm run dev`), nothing deployed publicly

## 9. Rollback note
App/auth-layer only — no schema/migration change expected, no live-system writes. Rollback = revert `ncrm/middleware.ts`, `ncrm/app/login/`, the new `ncrm/lib/auth/` module, the `created_by` parameter additions in the three repo files (and their domain type extensions), the layout sign-out addition, and `ncrm/scripts/seed-owner.mjs` via `git`. If an owner user was already seeded, delete it manually in Supabase Studio → Authentication (local emulator only — no cloud Supabase involved, confirmed in Context). No downstream dependents yet: NCRM-09b/09c write-form work has not started and is deliberately sequenced after this task's QA (`plans/NCRM-09_write-forms-auth-split_20260717.md §5`).

## 10. Recommended status after execution
Stays `In progress` (on the parent NCRM-09 Notion card) until: (a) Claude independently reviews the diff (`bs-codex-review`), (b) owner runs the full §8 smoke test locally — seed, login, logout, blocked-user case, blocked-role case, (c) owner confirms the `created_by` verification method documented in the report is convincing even without a real write-form UI yet. Only then does Claude write the NCRM-09b (продаж+закупка forms) handoff — do not start 09b/09c before this task is owner-confirmed, per the split rationale in `plans/NCRM-09_write-forms-auth-split_20260717.md §8` (Risks).
