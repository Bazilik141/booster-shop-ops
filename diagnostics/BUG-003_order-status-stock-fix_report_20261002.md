# Claude Code Report — BUG-003: order-status stock fix (WP1–WP3)

Date: 2026-10-02 · Executor: Claude Code (Opus, high) · Handoff: `handoffs/handoff_BUG-003_order-status-stock-fix_20261002.md`
Live source: `backup-9.24.2026_16-35-03_boosters.tar.gz` (PHP tree + `mysql/boosters_ocart49.sql`), extracted to a scratchpad outside the repo and deleted after testing.

## Scope

1:1 with the handoff. Two additions, both outside the handoff's "do not touch" list:

- All three runners exit with 404 when not run from the CLI, because they sit in the web root until they self-delete.
- WP2 clears `DIR_CACHE/cache.order_status.*`. Admin keeps the status list in the file cache, so without this the dropdown shows the new status only after the cache expires (`acceptance: appears in the admin dropdown`). Admin clears the same cache key itself when it saves a status.

WP1 leaves no marker comment in `order.php`. The handoff wants every other line of `addHistory()` byte-identical, so the 5 replaced expressions serve as the idempotency marker: 0 old + 5 new + setting `["5","12"]` → `already_applied=yes`.

## Verified against the live tree (24.09)

| Check | Handoff | Live | |
|---|---|---|---|
| Anchor occurrences in `catalog/model/checkout/order.php` | 5 (lines 800, 833×2, 926×2) | 5, same lines | match |
| `order.php` size / sha256 | 47 948 B | 47 948 B / `2c1482f3bbde65bcda450b57aab72ce9d7b076dba5d6c5e66a3d71fe436b2744` | match |
| `config_complete_status` | `["5","12","10","14"]` | `["5","12","10","14"]`, setting_id 10975, serialized 1, single row (no `ocp5_store` rows) | match |
| `config_processing_status` | `["1","7","3","13","12"]` | same | match |
| DB prefix | `ocp5_` from config.php | `ocp5_` | match |
| Admin directory | unknown | `adminEvhenii/` (OC 4.1.0.3) | located |
| Active language | 4 | 4 «Українська», only active | match |
| `config_void_status_id` | — | 15 (non-holding; used by `editOrder()` / `deleteOrder()`) | info |

Root cause confirmed as stated in the handoff: `(array)processing + (array)complete` with 5 and 4 elements yields processing only.

## Tree-wide grep: `+ (array)$this->config->get(` on status lists

Scope: every `*.php` under `public_html/` (including `adminEvhenii/` and `extension/**`) and `ocartdata/storage/` (excluding `vendor/`).

| Hit | Action |
|---|---|
| `catalog/model/checkout/order.php:800, 833, 926` (5 occurrences) | patched by WP1 |
| `_patch_backups/{NCRM-10_…043141, NCRM-10_round5_…110403, CHECKOUT-002_…162144}/order.php` | inert backup copies, not loaded; not patched |

No other live hit. The other consumers that union the two lists already use `array_merge`: `catalog/controller/event/statistics.php:71` and `adminEvhenii/controller/report/statistics.php:97,192`.

### Consumers of `config_complete_status` (effect of removing 10 and 14)

Removing 10 and 14 changes no stock behaviour, because the bug already made them non-holding. It does change these complete-only consumers, for future events only:

- `adminEvhenii/model/sale/order.php:579` / `getTotalSales`, `extension/opencart/admin/model/report/sale.php`, `extension/opencart/admin/model/dashboard/map.php`, `adminEvhenii/model/marketing/marketing.php:160`: orders in 10/14 are no longer counted as completed sales.
- `catalog/controller/event/statistics.php`: the running `order_complete` / `order_sale` counters move on transitions into or out of 10/14 after deploy. Existing counter values are not recomputed.
- `catalog/model/account/download.php`: downloadable products are not used by the store; no effect.
- `adminEvhenii/controller/sale/order.php:1060` (`complete_status` flag in the order view): only 5 and 12 now count.

No consumer in coupon models, NCRM or CRM sync reads either list.

## Stock writers and status writers outside `addHistory()`

`addHistory()` is the only code that changes `product.quantity` on order events. Two places change an order's status without going through it. Both are outside the scope of this task and are recorded here only; WP3 accounts for them:

- `catalog/controller/api/order.php:673`: when an admin re-saves an existing order, the API sets `order.order_status_id` directly (posted status, or `config_order_status_id` = 1). It writes no history row and applies no stock logic.
- `extension/hutko/admin/model/payment/hutko.php:33` `addOrderHistory()`: writes history and status directly when an admin creates a payment link (status 0 → new) or makes a refund (→ 13). A refund moves holding → holding, so it has no stock effect under either rule. Payment-link rows (comment `Payment Link Created (Admin)`) are skipped by WP3; the 24.09 dump has none.

## Files touched

```
patches/BUG-003_order-status-stock-fix_20261002.php      — WP1 code + setting, atomic
patches/BUG-003_cancelled-order-status_20261002.php      — WP2 INSERT «Скасовано»
patches/BUG-003_stock-inflation-report_20261002.php      — WP3 read-only estimate
diagnostics/BUG-003_order-status-stock-fix_report_20261002.md
```

## Local execution (real MySQL 8.4 loaded from the 24.09 dump, PHP 8.3)

```
WP1 run 1: anchor_old_count=5 anchor_new_count=0 occurrences_replaced=5
           config_complete_status_old=["5","12","10","14"] config_complete_status_new=["5","12"]
           target_sha256_after=0324c221cb75cb1aaa04d8b28be5865b295aff3b727139791e03413342c3a800 (48 008 B)
           php_lint=ok done=ok; diff vs original = 3 changed lines (800, 833, 926)
WP1 run 2: already_applied=yes (nothing written)
WP1 setting old / code old ............ applied
WP1 setting new / code old ............ abort, nothing written, no backup dir
WP1 setting old / code new ............ abort "Mixed state", nothing written
WP1 6 anchors ......................... abort, nothing written
WP1 injected failure after both writes  rollback_file=restored rollback_setting=restored (sha + value verified)
WP1 generated rollback.sql + cp ....... restores sha 2c1482f3… and ["5","12","10","14"]
WP2 run 1: order_status_id:24,language_id:4,name:Скасовано (UTF-8 bytes verified) cache files cleared=1 done=ok
WP2 run 2: already_applied=yes
WP3: orders_replayed=134 products_subtract_1=123 products_inflation_gt_0=48 inflation_units_total=641
     product 73: inflation 246, includes 374:7 (orders 396/399 postdate the dump — they appear on live)
     CHECKSUM of product/order/order_history/order_product/setting identical before/after → read-only confirmed
     CSV written to ../bs-reports/, no personal data (ids, model, sku, quantities only)
```

The production `order_status_id` for «Скасовано» comes from AUTO_INCREMENT. The runner prints the actual id.

## php -l / host compatibility

`php -l` OK on all three (local 8.3). `scripts/check-php-host-compat.php`: nothing newer than PHP 8.0 and no mysqlnd-only calls. No `never`, no `get_result()` / `fetch_all()`. `mysqli_report(MYSQLI_REPORT_OFF)` is set explicitly, so error handling is the same on 8.0 and 8.3. **Production gate:** run `php -l` on each file in `~/public_html` before executing it.

## Idempotency

- WP1: `already_applied=yes` when the code and setting are already fixed. A mixed state aborts without writing.
- WP2: `already_applied=yes` by exact name + language.
- WP3: no state. Every run writes a new timestamped CSV.

## Rollback

- WP1: `_patch_backups/BUG-003_order-status-stock-fix_20261002-<ts>/` contains `catalog/model/checkout/order.php`, `setting.before.json`, `order.php.sha256-before` and `rollback.sql` (exact `setting_id`, old value, live prefix). Restore the file **and** run the SQL; never one without the other. The header of the patch file contains the same SQL.
- WP2: `_patch_backups/BUG-003_cancelled-order-status_20261002-<ts>/rollback.sql` deletes by exact id + language + name. Only run it while no order carries the status.
- WP3: nothing to roll back. Delete `~/bs-reports/BUG-003_stock-inflation_*.csv` when reconciliation is done.

## Run command (owner)

Take a MySQL backup first. Then, in `~/public_html`, in this order:

```bash
php -l BUG-003_order-status-stock-fix_20261002.php && php BUG-003_order-status-stock-fix_20261002.php
php -l BUG-003_cancelled-order-status_20261002.php && php BUG-003_cancelled-order-status_20261002.php
php -l BUG-003_stock-inflation-report_20261002.php && php BUG-003_stock-inflation-report_20261002.php
```

## Post-deploy QA

Handoff §8, unchanged: `bs-checkout-smoke`, then stock steps 1–7.

Acceptance grep correction: `grep -c` counts lines, and the 5 occurrences sit on 3 lines. Use these instead:

```bash
grep -o "config_processing_status') + (array)" catalog/model/checkout/order.php | wc -l
grep -o "array_merge((array)\$this->config->get('config_processing_status')" catalog/model/checkout/order.php | wc -l
```

The expected results are `0` and `5`.

## Side effects / risks

- **Double restock on historical «Отримано» orders.** Every order that is already in 5 had its units returned by the bug. After WP1, status 5 holds stock, so moving such an order to any non-holding status returns its units a second time. That includes «Скасовано», «Помилка», «Протерміновано», deleting the order (void 15), and draft (15). Do not change the status of pre-fix delivered orders to a non-holding status, and do not delete them, until the WP3 reconciliation is done. Admin «edit order» voids (5→15, +qty) and re-confirms (15→5, −qty), so the net effect is zero.
- Between the setting write and the file write, the old code with the new list still yields holding = processing. Behaviour in that window is the same as today.
- Report and statistics consumers stop counting 10/14 as completed (see above).
- Known residuals in the handoff (coupon_history rows removed by past `unconfirm()`; 12/13 remain holding) are unchanged.
