# NCRM-09e — Mystery Box fulfillment

Date: 2026-07-18  
Scope: local NCRM UI only; no migration, deploy, import, return/refund flow, or sale-form change.

## Delivered

- Added `/mystery` queue for `needs_assembly` and `reserved` fulfillments and a per-sale-item assembly page.
- Uses `v_mystery_eligible_components` directly for the selectable component list; the UI does not recreate eligibility or stock rules.
- Reserve calls `fn_reserve_mystery_fulfillment` with the selected component payload. The RPC remains the source of truth for required count, availability and state checks; its error is returned to the operator.
- After a successful reserve, a narrow server-side update fills `mystery_fulfillments.created_by` only when it is null, using the authenticated staff ID.
- Commit calls `fn_commit_mystery_fulfillment`; it is enabled only for order status `shipped`. The database creates the MBOX writeoff and refreshes actual Mystery COGS atomically.
- Release calls `fn_release_mystery_fulfillment`; it is enabled only for `cancelled` or `refund`. The interface states that status-change automation normally releases the reservation.
- No UI or repository call to `fn_reverse_mystery_fulfillment` was added.

## Files touched

- `ncrm/app/mystery/actions.ts`
- `ncrm/app/mystery/page.tsx`
- `ncrm/app/mystery/[saleItemId]/page.tsx`
- `ncrm/app/mystery/[saleItemId]/mystery-form.tsx`
- `ncrm/lib/domain/mystery.ts`
- `ncrm/lib/repositories/mystery.repo.ts`
- `ncrm/app/layout.tsx` and domain/repository barrel exports

## Verification

- `npm run build` — passed (Next.js 16.2.10; `/mystery` and `/mystery/[saleItemId]` generated as dynamic routes).
- `git diff --check` — passed.
- Diff under `ncrm/supabase/migrations` — empty.
- Search confirmed no `fn_reverse_mystery_fulfillment` call and no direct browser Supabase-client import in the new Mystery routes.

## Risk and rollback

The UI invokes real transactional fulfillment RPCs. A successful commit creates irreversible accounting records according to the database contract; this ticket deliberately provides no reverse path. Rollback of local code is `git restore` of the listed files; do not repair committed business data through this UI.

## Manual QA

```bash
cd ncrm || exit
npm run dev
```

1. Create a Mystery Box sale through the NCRM-09b sale flow and confirm it appears in `/mystery` as `needs_assembly`.
2. Enter an incorrect component total and confirm the RPC error is visible; then reserve an eligible, available total equal to expected pack count times sale quantity.
3. Confirm the reserved page cannot commit before order status is `shipped`; after `shipped`, commit and verify the MBOX writeoff, contents and non-provisional COGS in the database views.
4. In a separate reserved example, change the order to `cancelled` or `refund` and confirm the trigger/RPC releases the reservation. No arbitrary pre-shipment manual release is available.
