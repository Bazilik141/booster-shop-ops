# PAY-001 — admin error property fix

## Cause

The deployed MonoChast admin controller calls this.error in validate without declaring the property. OpenCart resolves it through the registry and throws Could not call registry key error.

## Change

Adds only protected array error = [] immediately inside the existing class body in extension/mono_chast/admin/controller/payment/mono_chast.php.

## Safety

- No database, registry, setting, payment or storefront changes.
- Exact class anchor check, backup and php -l gate with restore-on-failure.
- Rerun reports already_applied=yes.
