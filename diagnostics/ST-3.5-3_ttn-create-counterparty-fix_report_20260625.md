# Codex Report — ST-3.5-3: TTN create counterparty fix

Date: 2026-06-25

## Scope
Handoff scope: fix fatal crash when submitting the Pinta Nova Poshta admin waybill form for a non-PrivatePerson sender.

Implemented: one admin-only fix in `internet_document.php`. No checkout, payment, Hutko, Checkbox, totals, DB, CRM, address format, or frontend files changed.

## Files touched
```
patches/ST-3.5-3_ttn-create-counterparty-fix_20260625.php — host patch runner
```

Host file changed by the runner:
```
extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php
```

## Root cause
`prepareSender()` and `prepareRecipient()` call `$PintaCounterparty->saveContactPerson(...)` in the non-PrivatePerson branch, but `$PintaCounterparty` is not instantiated inside those methods.

`prepareSender()` also passes `$phone` to `saveContactPerson(...)`, but `$phone` is missing from the method signature and from the caller.

## Fix
- Adds `$formdata['sender_phone']` to the `prepareSender(...)` call.
- Adds `$phone` to the `prepareSender(...)` signature.
- Instantiates `\Opencart\System\Library\Pintanovaposhta\PintaCounterparty()` inside `prepareSender()`.
- Instantiates the same inside `prepareRecipient()`.
- Leaves PrivatePerson logic unchanged.

## Dry-run result
Dry-run was executed against extracted files from:
```
C:\Users\14bez\Downloads\Booster Shop\backup-6.23.2026_11-21-43_boosters.tar.gz
```

Key first-run lines:
```
old_sender_call_count=1
old_sender_signature_count=1
old_recipient_signature_count=1
new_sender_call_count=0
new_sender_signature_count=0
new_recipient_signature_count=0
php_lint=No syntax errors detected in .../extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php
changed_file=extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php
already_applied=no
done=ok
```

## php -l result
Patch runner:
```
No syntax errors detected in ST-3.5-3_ttn-create-counterparty-fix_20260625.php
```

Target file during dry-run:
```
No syntax errors detected in extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php
```

## Idempotency
Second dry-run after applying the same patch returned:
```
old_sender_call_count=0
old_sender_signature_count=0
old_recipient_signature_count=0
new_sender_call_count=1
new_sender_signature_count=1
new_recipient_signature_count=1
already_applied=yes
done=ok
```

## Rollback
The patch backs up the changed file before writing:
```
_patch_backups/st3.5-3-ttn-create-20260625-YYYYMMDD-HHMMSS/extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php
```

To restore:
```
cp _patch_backups/<backup-dir>/extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php
```

## Run command (owner)
```bash
cd ~/public_html || exit
php ST-3.5-3_ttn-create-counterparty-fix_20260625.php
```

## Post-deploy QA checklist
- [ ] Submit one test waybill from admin order #192 or another Warehouse + FOP sender + COD test order.
- [ ] No `Undefined variable $PintaCounterparty`, no undefined `$phone`, no `saveContactPerson() on null`.
- [ ] TTN is created and `int_doc_number` appears.
- [ ] Admin order card shows TTN block with print links.
- [ ] Sender contact person phone is correct.
- [ ] Cancel/delete the real test TTN in Nova Poshta if it is only for QA.
- [ ] No new PHP errors in `storage/logs`.

## Side effects / risks
Low frontend risk: admin-only create flow. The QA submit creates a real Nova Poshta waybill, so owner should test with one controlled order and cancel it afterward if needed.
