# Codex Report — ST-3.7: NP consignment form Ukrainian localization

Date: 2026-06-25

## Scope
Handoff scope: admin-display-only localization for the Pinta Nova Poshta `create_internet_document` screen.

Implemented: one display-only language overlay in the form controller. No checkout, payment, TTN API, COD, totals, DB, CRM, or frontend files changed.

## Files touched
```
patches/ST-3.7_np-consignment-form-uk-localization_20260625.php — host patch runner
```

Host file changed by the runner:
```
extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php
```

## Diagnosis
`create_internet_document.twig` uses language keys, not hardcoded English labels.

The backup `uk-ua` language file contains the relevant keys, but owner QA showed the form body still renders English. Therefore the patch overlays the Ukrainian values directly after:

```php
$data = $this->load->language('extension/PintaNovaPoshtaCod/shipping/pinta_nova_poshta');
```

This affects only the admin TTN form data array and does not change the form submit logic.

## Dry-run result
Dry-run was executed against extracted files from:
```
C:\Users\14bez\Downloads\Booster Shop\backup-6.23.2026_11-21-43_boosters.tar.gz
```

Key first-run lines:
```
old_language_load_count=1
uk_overlay_marker_count=0
php_lint=No syntax errors detected in .../extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php
changed_file=extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php
already_applied=no
done=ok
```

## php -l result
Patch runner:
```
No syntax errors detected in ST-3.7_np-consignment-form-uk-localization_20260625.php
```

Target file during dry-run:
```
No syntax errors detected in extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php
```

## Idempotency
Second dry-run after applying the same patch returned:
```
old_language_load_count=1
uk_overlay_marker_count=1
already_applied=yes
done=ok
```

## Rollback
The patch backs up the changed file before writing:
```
_patch_backups/st3.7-np-consignment-form-uk-20260625-YYYYMMDD-HHMMSS/extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php
```

To restore:
```
cp _patch_backups/<backup-dir>/extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php
```

## Run command (owner)
```bash
cd ~/public_html || exit
php ST-3.7_np-consignment-form-uk-localization_20260625.php
```

## Post-deploy QA checklist
- [ ] Open the NP waybill form for order #192.
- [ ] Header, tabs, sections, labels, select options, seats table, sender/recipient blocks are Ukrainian.
- [ ] No raw language keys such as `entry_xxx`.
- [ ] Form fields and options are still present and prefilled as before.
- [ ] No new PHP errors in `storage/logs`.

## Side effects / risks
Low. Admin display only. The patch does not change TTN creation payload or submit behavior.
