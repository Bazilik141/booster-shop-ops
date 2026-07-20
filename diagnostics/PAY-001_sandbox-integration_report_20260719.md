# PAY-001 — sandbox integration report

## Scope

Phase 1 only: a new disabled-by-default OC4 payment extension for monobank Покупка Частинами, its transaction/state storage, six distinct OpenCart statuses, and the NCRM payment-type contract. No public product/checkout UI, Hutko, COD, Checkbox, SEO or production credentials are changed.

## Files and DB changes

- Host patch creates `extension/mono_chast/`, `ocp5_mono_chast_transaction`, six `ПЧ mono` statuses, the payment extension registry entry and disabled `payment_mono_chast_*` settings. It grants only the Administrator group access/modify permission for the new admin route and saves the prior JSON in the patch backup.
- `0013_pay001_mono_payment_types.sql` adds `credit_mono_3`, `credit_mono_4`, `credit_mono_5` and fee keys 2.9%, 4.1%, 5.9% effective 2026-07-19.
- `order-sync/index.ts` recognises the saved OC method codes `mono_chast.mono_chast_3|4|5`; it continues to send NCRM `new` and `unpaid` as required.

## Safety gates

- Patch checks `config.php`, the live Hutko OC4 extension anchor and the exact `ocp5_` prefix before writing.
- It backs up `config.php` and writes `rollback.sql` under `_patch_backups/` before file/DB changes.
- Every generated PHP file is checked by `php -l`; a syntax failure moves the new extension into the backup directory.
- Marker rerun returns `already_applied=yes`; new payment method defaults to disabled.
- API credentials are empty settings, never embedded in the patch or source.

## Dry run and syntax

- Source patch PHP syntax: passed locally (`php -l`).
- Generated extension syntax, database migration and sandbox API checks require owner deployment; no server/database access is available locally.

## Rollback

1. Disable `Покупка Частинами monobank` in Admin.
2. Use the generated `_patch_backups/.../rollback.sql` for settings, registry and status rows.
3. Keep `ocp5_mono_chast_transaction` as an audit trail once any transaction exists; restore the generated backup if file rollback is required.

## Owner sandbox QA

1. Configure only sandbox credentials in Admin; keep payment method disabled for public storefront.
2. Verify create for test phones ending `1`, `2`, `3`, `4`; poll case `2` and confirm only case `4` from the protected admin action after simulated fulfilment.
3. Verify invalid callback signature returns 401; verify a duplicate `store_order_id` is idempotent.
4. Verify `< 500` is rejected, customer total has no merchant-fee uplift, and existing Hutko/COD/Checkbox checkout remains unchanged.
5. Apply the NCRM migration locally before any enabled sandbox checkout is permitted.
