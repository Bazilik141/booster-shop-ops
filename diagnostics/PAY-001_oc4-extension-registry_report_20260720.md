# PAY-001 — OC4 extension registry fix

## Root cause

The deployed extension exists in the classic payment registry, so the payment list sees its controller. But the first PAY-001 patch did not create the OC4 package registry record. Admin startup registers extension language paths only from extension_install. Consequently the list loader cannot load the existing language file and returns the raw key mono_chast_heading_title.

## Scope

- Adds or repairs one extension_install row for mono_chast.
- Adds missing extension_path rows for the deployed extension tree.
- Leaves extension source files, existing .pay001-marker, payment settings, transaction tables and public UI untouched.

## Expected result

- Marketplace -> Extensions -> Payment displays Покупка Частинами monobank (sandbox).
- The row remains pale while payment_mono_chast_status is 0. That is expected OpenCart styling for a disabled payment method, not an install error.

## Verification

- Root cause verified against the current OC4 extension/payment controller and startup/extension controller from the newest full backup.
- Patch PHP syntax passed php -l.
- Patch backs up prior extension_install and extension_path state, produces rollback SQL, is idempotent and self-deletes on success.
