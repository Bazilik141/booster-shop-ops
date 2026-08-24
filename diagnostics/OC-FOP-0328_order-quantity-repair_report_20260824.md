# OC-FOP-0328 — OpenCart order quantity repair

Date: 2026-08-24

## Root cause

The fresh owner-supplied archive shows that the admin confirmation flow posts
the edited products to `catalog/controller/api/order.php`.

For an existing order, that controller currently calls only
`editOrderStatusId()`. It does not persist the rebuilt product rows, order
totals, or order header total. Therefore the page reports a successful
confirmation but reloading returns the old quantity.

## Scoped repair

`patches/OC-FOP-0328_order-quantity-repair_20260824.php` makes only this
owner-authorized correction:

- order ID `328`, model `OP-JP-V7PR-BST`: quantity `20` to `12`;
- line total `1600.00` to `960.00`;
- subtotal and grand total `1945.00` to `1305.00`;
- restocks 8 units only if the live status is configured to have deducted
  inventory and the product has `subtract=1`.

The runner checks all expected current values, backs up a bounded DB snapshot,
uses a transaction, reads back the exact resulting values, and self-deletes
only after success. It writes no order-history event and sends no customer mail.

## Validation

- Fresh archive inspected: `booster-debug-oc328-admin.tar.gz`, uploaded
  2026-08-24.
- `catalog/controller/api/order.php`: existing-order branch at lines 657–675
  omits the product/total persistence path.
- `catalog/controller/api/cart.php`: submitted `product[*][quantity]` is the
  value used to rebuild the temporary cart.
- Local PHP syntax check: `php -l` passed.
- The first host run stopped before `schema_preflight=ok`: this MariaDB rejects
  a placeholder in `SHOW TABLES LIKE ?`. No backup directory or database write
  was reached. The runner now uses a validated, escaped table name there.
- The second host run reached `schema_preflight=ok`, then stopped before the
  backup and transaction because this PHP build has no `mysqlnd` support for
  `mysqli_stmt::get_result()`. The runner now reads prepared-statement rows via
  native `result_metadata()` and `bind_result()` instead.
- Owner host run at 2026-08-24 07:55 UTC completed with `done=ok`:
  quantity `20->12`, order total `1945.00->1305.00`, and stock `+8`.
- CRM live read-back after the owner-run, exact temporary FIFO wrapper:
  `Продажі!299` has quantity `12`, sale total `960.00`, no `fallback: 6`, and
  FIFO inventory `LOT-0011: 12 x 62.57/66.32`. Its intentional order-component
  allocation remains `20.35` management cost; aggregate component management
  cost across rows 299–301 is `27.67`.
- The accidental `Інше` packaging was reset to blank with zero packaging cost
  on rows 299–301. Current order status and TTN were preserved.

## Remaining follow-up

The controller defect affects any existing order edited through this screen.
It is deliberately out of this one-order database repair: a generic controller
fix must be separately reviewed for status history, stock transitions, and
order-sync side effects.

The one-off Apps Script wrapper must be removed from the live script after this
verified run. The generic OpenCart controller defect remains a separate task:
it needs review for status history, stock transitions, and order-sync effects.

Owner confirmed the temporary Apps Script file was deleted after verification.
