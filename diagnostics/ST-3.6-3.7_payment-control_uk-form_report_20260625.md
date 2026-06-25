# ST-3.6 + ST-3.7 Combined Patch Report

Date: 2026-06-25
Patch: `ST-3.6-3.7_payment-control_uk-form_20260625.php`
Target: `extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php`

## Scope

- ST-3.6: COD prefill now enables reverse money delivery only for Nova Poshta COD payment methods.
- ST-3.6: non-PrivatePerson senders use `AfterpaymentOnGoodsCost` instead of `BackwardDeliveryData/Money`.
- ST-3.6: PrivatePerson senders keep the existing `BackwardDeliveryData/Money` payload.
- ST-3.7: adds Ukrainian label overlay for the admin TTN creation form.

## Risk

- High: affects Nova Poshta TTN creation payload for COD orders.
- No DB changes.
- No checkout, payment gateway, totals, fiscalization, Hutko, or Checkbox changes.
- Rollback: restore the backed-up `internet_document.php` from `_patch_backups/st3.6-3.7-payment-control-uk-20260625-*`.

## Validation

- Patch syntax: `php -l` OK.
- Dry-run source: `backup-6.23.2026_11-21-43_boosters.tar.gz`.
- Dry-run prerequisite: ST-3.5-3 applied cleanly first.
- Combined patch first run: `done=ok`, target `php -l` OK.
- Combined patch repeat run: `already_applied=yes`, `done=ok`.
- Postchecks confirmed:
  - Ukrainian overlay marker present once.
  - `sender_counterparty_type` property/capture present.
  - old inverted COD prefill condition removed.
  - fixed COD prefill condition present.
  - `AfterpaymentOnGoodsCost` payload marker present.

## Owner QA

- Open admin TTN form for a COD order.
- Confirm form labels are Ukrainian.
- Create one real NP TTN for FOP/business sender + COD.
- Verify NP account shows `Контроль оплати` with the order amount.
- If test-only, cancel the created TTN in the NP account.
