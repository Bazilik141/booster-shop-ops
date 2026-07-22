# Codex Report — ORDER-STATUS-001: preorder order status

Date: 2026-07-21

## Scope
Owner approved adding one selectable OpenCart admin order status: `Передзамовлення`.

The patch does not automatically assign this status to orders containing a preorder product. Automatic classification is a separate checkout/order-write change and is intentionally excluded.

## Files touched
```text
patches/ORDER-STATUS-001_preorder_order_status_20260721.php — DB runner
```

## Dry-run result
```text
Fresh source checked: backup-7.19.2026_09-58-50_boosters.tar.gz
DB prefix: ocp5_
Active language in backup: 4 (Українська)
order_status schema: order_status_id, language_id, name
Existing order statuses: no Передзамовлення row
```

## php -l result
```text
No syntax errors detected in patches/ORDER-STATUS-001_preorder_order_status_20260721.php
Runtime gate: the patch runs php -l on itself before any database write.
```

## Idempotency
If the active Ukrainian language already has exactly one `Передзамовлення` row, the runner prints `already_applied=yes` and self-deletes. Duplicate rows or a different language layout stop the patch without changes.

## Rollback
Backup at: `_patch_backups/ORDER-STATUS-001_preorder_order_status_20260721-<timestamp>/`

`rollback.sql` deletes only the newly-created row by its exact status ID, language ID, and name. Do not use it after orders have been assigned this status.

## Run command (owner)
```bash
cd ~/public_html || exit
php ORDER-STATUS-001_preorder_order_status_20260721.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA checklist
- [ ] Output contains `php_l=ok` and `done=ok`.
- [ ] Admin order-status selector contains one `Передзамовлення` entry.
- [ ] Select the status on a disposable test order, save it, and confirm its order-history entry.
- [ ] Existing statuses and an existing order retain their current values.

## Side effects / risks
- Database only: one row in `ocp5_order_status`; no existing row, product stock state, checkout behavior, payment behavior, or order is modified.
- Clearing the OpenCart status cache is included in the owner command so the new value appears immediately in admin.
