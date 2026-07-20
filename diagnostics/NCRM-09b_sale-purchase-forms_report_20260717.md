# Codex Report — NCRM-09b: sale and purchase write forms

Date: 2026-07-17

## Scope

Implemented the owner/admin-gated UI for a new sale with repeatable line items and a new purchase with repeatable lots, manual amount/rate inputs, and a single-lot or shared-fee multi-lot path.

The existing database triggers remain the only FIFO/COGS implementation. The existing `fn_allocate_purchase_shared_fee` RPC remains the only shared-fee allocation implementation. No allocation formula was added to TypeScript.

No migration, auth/middleware change, writeoff/RRC/refund/mystery work, currency-rate fetch, import change, live CRM/site change, or network deployment was made.

## Files touched

```
ncrm/lib/domain/reference.ts                       — typed reference options
ncrm/lib/repositories/reference.repo.ts            — active lookup lists for both forms
ncrm/lib/domain/purchases.ts                        — shared-fee allocation payload contract
ncrm/lib/domain/index.ts                            — reference-type export
ncrm/lib/repositories/index.ts                      — reference repository export
ncrm/lib/repositories/purchases.repo.ts             — RPC orchestration and compensating rollback
ncrm/app/orders/new/{page,sale-form,actions}.tsx    — new sale form and server action
ncrm/app/purchases/new/{page,purchase-form,actions}.tsx — new purchase form and server action
ncrm/app/orders/page.tsx                            — discoverable “create sale” link
ncrm/app/layout.tsx                                 — purchase entry link
ncrm/app/globals.css                                — minimal form controls/layout styling
```

## Shared-fee allocation flow

- One lot: the entered forwarding, international shipping, and local-delivery UAH values are stored directly on that lot; no allocation row is required by the established DB policy.
- Multiple lots: the form sends a selected `value`, `weight`, or `manual` method to `addPurchase`. The repository first inserts the parent and zero-fee lots, then calls `fn_allocate_purchase_shared_fee` once for every non-zero fee type. The RPC inserts the audit rows and updates canonical lot fee columns.
- Manual mode exposes three UAH inputs per lot. The server maps the entered amounts to the actual newly-created lot IDs; the RPC verifies that every lot is present and every fee total matches its purchase total.
- If lot insertion, allocation, or readback fails, `addPurchase` deletes allocation rows, lots, and the parent purchase in that order. A rollback failure is surfaced in the returned error; the form does not report a partial purchase as successful.

## Verification result

```
npm run build
✓ Compiled successfully
✓ Running TypeScript
✓ /orders/new
✓ /purchases/new
ƒ Proxy (Middleware)

git diff --check
exit=0

git diff -- ncrm/supabase/migrations
no output
```

The only build warning is the pre-existing Next.js 16 deprecation for the handoff-required `middleware.ts` naming from NCRM-09a.

The two new write-form route trees contain no direct import of `@/lib/supabase/client`; they call server actions, which call repository modules. A literal whole-`ncrm/app` grep still finds two pre-existing NCRM-09a authentication UI imports (`app/login/login-form.tsx` and `app/components/sign-out-button.tsx`). NCRM-09b explicitly forbids touching those auth files, so they were not refactored in this task.

## Rollback

No schema change and no live-system write occurred. Revert the files above. If QA has created only disposable local data, the owner may clean those known test rows manually; do not run `db reset` where imported history must be preserved.

## Owner QA (local only)

```bash
cd ncrm || exit
npm run dev
```

- [ ] As owner/admin, open `/orders/new`; create an actual sale with two items and confirm `sales.created_by`, `sale_items`, and non-pending/non-zero COGS where eligible under the existing actual-sale trigger rules.
- [ ] Create a single-lot purchase; confirm its three lot fee fields equal the entered purchase fees and `purchases.created_by` is the logged-in staff id.
- [ ] Create a two-lot purchase with non-zero forwarding and `value` or `weight` allocation; confirm one audit row per lot and that `forwarding_fee_uah` sums to the purchase total.
- [ ] Repeat the two-lot case in `manual` mode with deliberately mismatched inputs; confirm the UI returns an error and no new purchase/lot survives the compensating rollback.
- [ ] Try direct form URLs while logged out or as a blocked role; confirm NCRM-09a redirects/blocks them.
- [ ] Keep the app local-only and ensure no `.env.local` or real credentials are staged.

## Side effects / risks

This is the first NCRM UI path that writes inventory/financial data. The principal risk is a wrong shared-fee call; the implementation delegates all allocation and total validation to the already-migrated DB function and does not recreate its arithmetic in the browser or server action. NCRM-09 remains `In progress`; NCRM-09c must wait for review and the owner QA above.
