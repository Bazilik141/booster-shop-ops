# Codex Report — ST-3.5: admin TTN button anchor

Date: 2026-06-24

## Scope
Handoff scope: restore the Pinta Nova Poshta TTN block/button on the OpenCart 4.1.0.3 admin order page and leave form submission to owner QA.

Implemented: one admin-side anchor fix only. No checkout, payment, Hutko, Checkbox, totals, DB, CRM, or frontend files changed.

## Files touched
```
patches/ST-3.5_admin-ttn-button_20260624.php — host patch runner
```

Host file changed by the runner:
```
extension/PintaNovaPoshtaCod/admin/controller/payment/pinta_nova_poshta_cod.php
```

## Phase 0 evidence
Handler location:
```
extension/PintaNovaPoshtaCod/admin/controller/payment/pinta_nova_poshta_cod.php
alterOrderAddedBtn()
```

Suffix location:
```
extension/PintaNovaPoshtaCod/admin/controller/shipping/pinta_nova_poshta.php
getOrdersShippingAddressHtmlSuffix()
```

Current admin template anchor from freshest backup:
```
adminEvhenii/view/template/sale/order_info.twig
<div id="output-shipping-address">
```

Old handler anchor:
```
$search = '<div id="shipping-address-value">';
```

New handler anchor:
```
$search = '<div id="output-shipping-address">';
```

## Dry-run result
Dry-run was executed against extracted files from:
```
C:\Users\14bez\Downloads\Booster Shop\backup-6.23.2026_11-21-43_boosters.tar.gz
```

Key first-run lines:
```
admin_template_anchor_count=1
old_anchor_count=1
new_anchor_count=0
php_lint=No syntax errors detected in .../extension/PintaNovaPoshtaCod/admin/controller/payment/pinta_nova_poshta_cod.php
changed_file=extension/PintaNovaPoshtaCod/admin/controller/payment/pinta_nova_poshta_cod.php
already_applied=no
done=ok
```

## php -l result
Patch runner:
```
No syntax errors detected in ST-3.5_admin-ttn-button_20260624.php
```

Target file during dry-run:
```
No syntax errors detected in extension/PintaNovaPoshtaCod/admin/controller/payment/pinta_nova_poshta_cod.php
```

## Idempotency
Second dry-run after applying the same patch returned:
```
old_anchor_count=0
new_anchor_count=1
already_applied=yes
done=ok
```

## Rollback
The patch backs up the changed file before writing:
```
_patch_backups/st3.5-admin-ttn-20260624-YYYYMMDD-HHMMSS/extension/PintaNovaPoshtaCod/admin/controller/payment/pinta_nova_poshta_cod.php
```

To restore:
```
cp _patch_backups/<backup-dir>/extension/PintaNovaPoshtaCod/admin/controller/payment/pinta_nova_poshta_cod.php extension/PintaNovaPoshtaCod/admin/controller/payment/pinta_nova_poshta_cod.php
```

Emergency fallback: disable the admin event for `admin/view/sale/order_info/after` → `alterOrderAddedBtn`.

## Run command (owner)
```bash
cd ~/public_html || exit
php ST-3.5_admin-ttn-button_20260624.php
```

## Post-deploy QA checklist
- [ ] Admin order #155: TTN block/button is visible after patch.
- [ ] Button opens the Nova Poshta waybill form.
- [ ] Prefill is correct: recipient, phone, city, branch/warehouse, weight/default package data, declared value / COD amount.
- [ ] Do not submit a real TTN during smoke unless owner intentionally tests it.
- [ ] No new PHP errors in `storage/logs`; browser console on order page is clean.

## Side effects / risks
Low frontend risk: the patch changes one admin display-event anchor. It does not change checkout, payment method codes, Hutko/Checkbox, shipping totals, or database data.
