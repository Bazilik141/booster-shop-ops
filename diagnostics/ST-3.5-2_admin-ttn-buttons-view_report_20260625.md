# Codex Report — ST-3.5-2: admin TTN button view

Date: 2026-06-25

## Scope
Owner QA after the first ST-3.5 patch showed that the admin order delivery block renders a large Pinta settings form instead of a TTN button/form entry point.

Implemented: one admin-side fix in `getOrdersShippingAddressHtmlSuffix()` so orders without an existing TTN render `components/order_page_buttons.twig` with the create-TTN link, not `pinta_nova_poshta/index.twig`.

No checkout, payment, Hutko, Checkbox, totals, DB, CRM, address-save, or frontend files changed.

## Files touched
```
patches/ST-3.5-2_admin-ttn-buttons-view_20260625.php — host patch runner
```

Host file changed by the runner:
```
extension/PintaNovaPoshtaCod/admin/controller/shipping/pinta_nova_poshta.php
```

## Root cause
The first anchor patch made the Pinta admin suffix visible. The suffix then exposed an existing module bug:

```
if (empty($internet_document) || empty($internet_document['int_doc_number'])) {
    $data['status'] = 'Інформація відсутня';
    return $this->load->view('extension/PintaNovaPoshtaCod/shipping/pinta_nova_poshta/index', $data);
}
```

That returns the module settings page inside `sale/order_info`, causing empty settings fields to spill into the order card.

## Fix
For orders without a TTN number, the runner replaces that early return with:

```
$data['status'] = 'Інформація відсутня';
$data['internet_document'] = null;
$data['pinta_link_create_internet_document'] = $this->url->link(...);
return $this->load->view('extension/PintaNovaPoshtaCod/shipping/pinta_nova_poshta/components/order_page_buttons', $data);
```

Existing TTN/status/print logic stays in place.

## Dry-run result
Dry-run was executed against extracted files from:
```
C:\Users\14bez\Downloads\Booster Shop\backup-6.23.2026_11-21-43_boosters.tar.gz
```

Key first-run lines:
```
bad_index_return_count=1
replace_block_count=1
php_lint=No syntax errors detected in .../extension/PintaNovaPoshtaCod/admin/controller/shipping/pinta_nova_poshta.php
changed_file=extension/PintaNovaPoshtaCod/admin/controller/shipping/pinta_nova_poshta.php
already_applied=no
done=ok
```

## php -l result
Patch runner:
```
No syntax errors detected in ST-3.5-2_admin-ttn-buttons-view_20260625.php
```

Target file during dry-run:
```
No syntax errors detected in extension/PintaNovaPoshtaCod/admin/controller/shipping/pinta_nova_poshta.php
```

## Idempotency
Second dry-run after applying the same patch returned:
```
bad_index_return_count=0
replace_block_count=0
already_applied=yes
done=ok
```

## Rollback
The patch backs up the changed file before writing:
```
_patch_backups/st3.5-2-admin-ttn-buttons-view-20260625-YYYYMMDD-HHMMSS/extension/PintaNovaPoshtaCod/admin/controller/shipping/pinta_nova_poshta.php
```

To restore:
```
cp _patch_backups/<backup-dir>/extension/PintaNovaPoshtaCod/admin/controller/shipping/pinta_nova_poshta.php extension/PintaNovaPoshtaCod/admin/controller/shipping/pinta_nova_poshta.php
```

## Run command (owner)
```bash
cd ~/public_html || exit
php ST-3.5-2_admin-ttn-buttons-view_20260625.php
```

## Post-deploy QA checklist
- [ ] Reload admin order #192.
- [ ] The large blank Pinta settings form no longer appears inside "Адреса доставки".
- [ ] A compact create-TTN button/link appears in the shipping address area.
- [ ] Click it: the NP waybill form opens.
- [ ] Check prefill: recipient, phone, city, branch/warehouse, weight/default package data, declared value / COD amount.
- [ ] Do not submit a real TTN unless owner intentionally tests it.
- [ ] No new PHP errors in `storage/logs`; browser console on order page is clean.

## Side effects / risks
Low frontend risk: the patch changes one admin display helper. It does not change checkout, payment method codes, Hutko/Checkbox, shipping totals, or database data.
