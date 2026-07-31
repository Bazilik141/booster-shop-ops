# Codex Report — NCRM-16: Monobazar postpay fee

Date: 2026-07-27

## Scope

Implemented the handoff exactly: one idempotent reference-data migration and a client-side payment-type default in the existing new-sale form. OpenCart, order-sync, NCRM-12, FIFO/COGS, auth, and existing migrations were not changed.

## Files touched

```
ncrm/supabase/migrations/0016_ncrm16_monobazar_postpay_fee.sql
ncrm/app/orders/new/sale-form.tsx
diagnostics/NCRM-16_monobazar_postpay_fee_report_20260727.md
```

## Static validation

```
git diff --check
channel lookup uses references.channels code, not a UUID
payment-type lookup uses references.paymentTypes code, not a UUID
SQL uses conflict targets (key, effective_from) and code
```

## Idempotency

The migration upserts the configuration row by `(key, effective_from)` and the payment type by `code`; re-applying it does not create duplicate rows. The form stores the selected IDs in component state and does not write data until the existing sale action is submitted.

## Rollback

```sql
delete from public.payment_types where code = 'postpay_monobazar';
delete from public.app_config where key = 'postpay_monobazar_pct';
```

Revert the `channelId` / `paymentTypeId` state and `updateChannel` helper in `sale-form.tsx` to remove the client default.

## Owner QA

- [ ] Apply migration `0016_ncrm16_monobazar_postpay_fee.sql` through the established Supabase migration flow.
- [ ] Confirm one `payment_types` row: `postpay_monobazar` / `Післяплата monobazar`.
- [ ] Confirm `app_config.postpay_monobazar_pct` equals `0.029`.
- [ ] In `/orders/new`, select Monobazar and confirm the payment type changes automatically.
- [ ] Change the payment type manually, then select a non-Monobazar channel and confirm it remains unchanged.
- [ ] Save one controlled test sale and confirm the selected payment type persists.

## Build limitation

Full `npm run build` is expected to remain blocked by the separate NCRM-11 TypeScript resolution issue in `supabase/functions/currency-rates-fetch/index.ts` (`npm:@supabase/supabase-js@2`). That file is outside this handoff and was not modified.

## Round 2 — mount-time default and fee suggestion

The payment default now resolves from the initial channel before the form mounts, so a first-listed Monobazar channel selects `postpay_monobazar` without a dropdown interaction. `getSaleFormReferences` reads active `payment_types.fee_pct_config_key` values and resolves matching live ratios from `v_current_app_config`; missing configuration resolves to `null`.

For a non-null rate, the form suggests a rounded per-line fee from `qty * unitPrice - discountAlloc`. A direct edit to the fee marks that line as form-locally touched, preventing subsequent quantity, price, discount, channel, or payment-type changes from overwriting it. `feeTouched` is stripped from `itemsJson`; the existing action and repository still receive only the numeric `paymentFee`.

Round 2 files:

```
ncrm/app/orders/new/sale-form.tsx
ncrm/lib/domain/reference.ts
ncrm/lib/repositories/reference.repo.ts
diagnostics/NCRM-16_monobazar_postpay_fee_report_20260727.md
```

Additional owner QA:

- [ ] Fresh `/orders/new` load defaults Monobazar to `Післяплата monobazar` without changing a dropdown.
- [ ] For qty `2`, price `500`, discount `0`, the suggested fee is `29.00`.
- [ ] After a manual fee edit, changing quantity or switching payment types never overwrites that line.
- [ ] A payment type without a current fee ratio leaves all fees manual and unchanged.
