# Codex Report — NCRM-09c: writeoff and RRC forms

Date: 2026-07-17

## Scope

Implemented only the scoped write forms:

- operational writeoffs with repeatable SKU items;
- manual RRC update accessible from every SKU row.

The form calls the existing `addWriteoff` and `updateRrc` repository functions. No repository, DB function, trigger, migration, auth rule, inventory-adjustment flow, return flow, or Mystery Box flow was changed.

## Files touched

```
ncrm/app/writeoffs/page.tsx                   — writeoff list and entry point
ncrm/app/writeoffs/new/{page,writeoff-form,actions}.tsx — create-writeoff UI/action
ncrm/app/sku/[id]/rrc/{page,rrc-form,actions}.tsx — RRC UI/action
ncrm/app/sku/page.tsx                         — reachable per-SKU RRC link
ncrm/app/layout.tsx                           — writeoff navigation link
```

## Behaviour confirmed from the existing contracts

- The writeoff-type selector offers exactly `Власне відкриття`, `Маркетинг`, and `Інше`. `MBOX` is absent from all new writeoff route source, so this form cannot trigger the Mystery-only DB guard.
- The action gets `createdBy` only through `getCurrentStaff()` and passes it into the existing `addWriteoff` payload.
- Existing inventory/FIFO code already reads `writeoff_items` joined to `writeoffs` as consumed inventory and includes prior writeoffs while calculating FIFO skips. This task only supplies rows to that established path.
- `updateRrc` writes `product_prices` with `source: 'manual'`; existing `v_current_rrc` selects the current manual price. The action revalidates `/sku`, and the client returns there after success.
- `inventory_adjustments` and `inventory_adjustment_items` were not touched.

## Verification result

```
npm run build
✓ Compiled successfully
✓ Running TypeScript
✓ /writeoffs
✓ /writeoffs/new
✓ /sku/[id]/rrc
ƒ Proxy (Middleware)

git diff --check
exit=0

git diff -- ncrm/supabase/migrations
no output

rg 'MBOX' ncrm/app/writeoffs
no output

rg '@/lib/supabase/client' ncrm/app/writeoffs ncrm/app/sku/[id]/rrc
no output
```

The only build warning is Next.js 16's pre-existing deprecation notice for the handoff-required `middleware.ts` naming from NCRM-09a.

## Rollback

No schema or live-system state changed. Revert the files listed above. Any test `writeoffs`, `writeoff_items`, or `product_prices` rows created during local QA must be identified and removed deliberately; do not use `db reset` when imported history is present.

## Owner QA (local only)

```bash
cd ncrm || exit
npm run dev
```

- [ ] As owner/admin, create a test `Інше` writeoff with one SKU; verify its `created_by` equals the logged-in Auth id and it appears at `/writeoffs`.
- [ ] Confirm the writeoff type selector contains no `MBOX` option.
- [ ] Update RRC on a disposable test SKU, return to `/sku`, and confirm the new current value is shown.
- [ ] Log out or use a blocked role and spot-check that direct `/writeoffs/new` and `/sku/<id>/rrc` URLs are gated.
- [ ] Confirm no `.env.local` or real credentials are staged before a commit.

## Side effects / risks

This is local-only financial/inventory data entry. The forms intentionally do not expose Mystery Box, return, or signed inventory-adjustment semantics; those are separate future scopes (NCRM-09d, NCRM-09e, and NCRM-13). Parent NCRM-09 remains `In progress` until review and owner QA.
