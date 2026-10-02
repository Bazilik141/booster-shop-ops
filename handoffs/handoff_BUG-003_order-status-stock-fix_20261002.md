# Handoff — BUG-003: phantom stock after order-status change

Date: 2026-10-02 | Parent: — | Notion: `3ed6bf20-bdb4-81b9-9428-c72fb23a7aba`
Executor: Claude Code · model=Opus · thinking=high — owner-assigned 2026-10-02. Opus/high
is required: risky zone (order status, stock, DB settings) and the fix runs through the
same `addHistory()` that checkout confirmation uses.

Evidence and root-cause proof: `diagnostics/BUG-003_phantom-stock_diagnostic_20261002.md`.
Do not re-derive it. Read it once before starting.

## 1. Task ID

BUG-003. Three work packages, three patch files, run in order WP1 → WP2 → WP3.
One executor, one round.

## 2. Context

`catalog/model/checkout/order.php` → `addHistory()` builds the stock-holding status set
as `(array)config_processing_status + (array)config_complete_status`. PHP `+` is a key
union, so with live values (processing `["1","7","3","13","12"]`, complete
`["5","12","10","14"]`) the complete list is discarded. Result: an order moving into
«Отримано» (5) is treated as leaving the holding set → its quantity is restocked and
coupon/reward `unconfirm()` runs. Every delivered order on the store has re-added its
units. The owner sets «Отримано» manually in admin.

Same bug makes 10 «Помилка» and 14 «Протерміновано» behave as non-holding. The owner
decided (2026-10-02) that failed/expired orders must return stock, so 10 and 14 are to
leave the complete list. That settings change and the code fix are **one atomic
package**: fixing the code alone turns 10/14 into holding statuses and creates a new
regression (failed payments keep stock).

## 3. Goal

- Status transitions deduct and restock stock exactly once, using the true union of
  processing and complete statuses.
- «Отримано» never restocks. «Помилка» / «Протерміновано» / «Скасовано» return stock.
- Owner gets a read-only per-product estimate of how much stock past deliveries
  inflated, to reconcile against the physical warehouse.

## 4. What to change

### WP1 — `patches/BUG-003_order-status-stock-fix_20261002.php` (code + one setting row, atomic)

1. In `catalog/model/checkout/order.php`, replace every occurrence of exactly
   `(array)$this->config->get('config_processing_status') + (array)$this->config->get('config_complete_status')`
   with
   `array_merge((array)$this->config->get('config_processing_status'), (array)$this->config->get('config_complete_status'))`.
   The 24.09 backup has **5** occurrences (lines 800, 833 ×2, 926 ×2). Anchor pre-check
   must require exactly 5; any other count → abort without writing.
2. Before writing the patch, grep the **whole live tree** for the same `... + (array)$this->config->get(` 
   pattern on status lists, including the renamed admin directory (the backup has no
   `admin/controller/sale/order.php`; locate the real admin dir) and every `extension/**`
   PHP file. Report every hit in the diagnostic. Fix only hits inside
   `catalog/model/checkout/order.php`; any other hit is reported, not patched, and the
   owner decides.
3. Update the setting row `config_complete_status` (store_id 0, code `config`) from
   `["5","12","10","14"]` to `["5","12"]`. Pre-check the current value equals exactly
   `["5","12","10","14"]`; otherwise abort before any write. `config_processing_status`
   is not touched. The DB prefix (`ocp5_`) must be read from `config.php`, not assumed.
4. Write order inside the runner: back up file + record old setting value → write the
   setting → write the PHP file → `php -l` → on any failure restore both. Rollback SQL
   with the exact old value goes in the patch header and into the backup folder.
5. Owner approval for the DB change: given in chat 2026-10-02 («Згоден … внести зміни по
   статусам за твоєю пропозицією, після діагностики багу»). The owner must still take a
   MySQL backup before running (AGENTS.md → Backup and rollback).

### WP2 — `patches/BUG-003_cancelled-order-status_20261002.php` (one INSERT)

Add order status «Скасовано» for the single active language (verify `language_id` from
the live DB; 4 in the 09-12 dump). Follow `patches/ORDER-STATUS-001_preorder_order_status_20260721.php`
as the pattern (idempotent by name, rollback SQL by exact id + name). Do **not** add it
to either status list. No other change.

### WP3 — `patches/BUG-003_stock-inflation-report_20261002.php` (read-only diagnostic)

Read-only. No writes to the DB or to any site file. For every product with
`subtract = 1`, replay `ocp5_order_history` per order (ordered by `date_added`,
`order_history_id`; previous status starts at 0) under two rules:

- buggy rule: holding set = `config_processing_status` only (what ran in production);
- correct rule: holding set = processing ∪ `["5","12"]` (post-WP1 intent).

Per transition, apply the `addHistory()` semantics: not-holding → holding deducts the
line quantity, holding → not-holding restocks it. Include `master_id` variants exactly
as the code does. Per product output: product_id, model/SKU, current `quantity`,
buggy net delta, correct net delta, `inflation = buggy − correct`. Also list deleted-order
gaps it cannot see (order_id gaps) as a caveat.

Output: a CSV written **outside** `public_html` (e.g. `../bs-reports/`), path printed to
stdout, plus a stdout summary (count of products with inflation > 0, top 20). No
customer names, emails, phones or addresses in any output. State in the report header
that manual stock edits by the owner are invisible to the replay, so the number is an
estimate to compare with the physical count, never a value to write back.

## 5. Do not touch

- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`
- checkout controllers and Twig, payment extensions (Hutko, mono_chast, pumb_credit),
  Checkbox / fiscalization, Merchant feed, schema / JSON-LD
- Nova Poshta module (`extension/PintaNovaPoshtaCod/**`)
- `boosterCrmSync()` / `ncrmOrderSync()` / Telegram call inside `addHistory()` — keep
  byte-identical
- every other line of `addHistory()`, `editOrder()`, `deleteOrder()`
- `config_processing_status`, any other setting row, any existing order, order history
  or product quantity — WP1–WP3 never change stock values
- coupon models (`booster_coupon.php`, totals extensions)
- no `ROADMAP_FLOW` or Notion writes (Claude chat is the writer)

## 6. Likely files / areas (verify against the live tree)

- `catalog/model/checkout/order.php` — confirmed in the 24.09 backup
  (`homedir/public_html/...`, mtime 2026-07-19, 47 948 bytes). Live may differ since; the
  anchor count check is the guard.
- `ocp5_setting` row `config_complete_status`.
- `ocp5_order_status` (WP2).
- `ocp5_order_history`, `ocp5_order_product`, `ocp5_product` (WP3, read-only).
- Unknown: renamed admin directory path — the executor must locate it.
- The owner's local shell sandbox for Claude chat is down; Claude Code works natively
  and may extract what it needs from `backup-9.24.2026_16-35-03_boosters.tar.gz` into a
  temp folder outside the repo (never into the repo). Do not read `.env.review`,
  `scripts/.env`, `client_secret.json`.

## 7. Acceptance criteria

- [ ] WP1 runner output: `occurrences_replaced=5`, `config_complete_status_old=["5","12","10","14"]`,
      `config_complete_status_new=["5","12"]`, `php_lint=ok`, `done=ok`; second run prints
      `already_applied=yes` and writes nothing.
- [ ] After WP1, `grep -c "config_processing_status') + (array)" catalog/model/checkout/order.php` = 0
      and `grep -c "array_merge((array)\$this->config->get('config_processing_status')"` = 5.
- [ ] `SELECT value FROM ocp5_setting WHERE \`key\`='config_complete_status'` returns `["5","12"]`.
- [ ] WP2: exactly one new row «Скасовано» in `ocp5_order_status`; it appears in the admin
      order-status dropdown; neither status list contains its id.
- [ ] WP3: CSV exists outside `public_html`, contains no personal data, and product 73's
      row shows buggy-rule restocks for orders #374, #396, #399 (≥ 42 units total).
- [ ] Diagnostic report `diagnostics/BUG-003_order-status-stock-fix_report_20261002.md`
      (template `templates/codex-report-template.md`) lists: the tree-wide grep hits, live
      file hash before/after, rollback paths.

## 8. QA / smoke test (owner, production, after WP1 + WP2)

Risky zone: order status + stock. Run `bs-checkout-smoke` after WP1 (addHistory is on the
checkout confirm path). Then this focused stock test on one cheap `subtract = 1` product
with spare stock, «Сповістити клієнта» off:

1. Note the product quantity Q.
2. Place a test order for 1 unit with a non-card method (IBAN / післяплата). Admin shows
   «В обробці». Quantity = Q − 1.
3. Set «Доставляється». Quantity stays Q − 1.
4. Set «Отримано». **Quantity stays Q − 1** (this was the bug).
5. Set «Скасовано». Quantity = Q.
6. Delete the test order. Quantity stays Q. Remove the test order from CRM/NCRM the usual way.
7. Next real «Отримано» on any order: that product's quantity does not change.

Rollback trigger: any step where the quantity moves differently, or checkout smoke fails.

## 9. Rollback note

- WP1: restore `order.php` from `_patch_backups/BUG-003_order-status-stock-fix_20261002-<ts>/`
  and run the header rollback SQL
  `UPDATE ocp5_setting SET value='["5","12","10","14"]' WHERE store_id=0 AND code='config' AND \`key\`='config_complete_status';`
  (prefix as read from `config.php`). Both together — never one without the other.
- WP2: rollback SQL deletes the status row by exact id + name; do not run once any order
  carries it.
- WP3: read-only, nothing to roll back; delete the CSV when done.
- Stock values corrected by the owner after WP3 are manual and outside this rollback.

## 10. Recommended status after execution

Notion stays `In progress` after the patches are delivered. `Done` only after: Claude
review of the three patches, owner ran WP1–WP2, `bs-checkout-smoke` passed, steps 1–7
above passed, and the owner decided on the WP3 stock reconciliation (DoD for order-flow
tasks, `ROADMAP_SOP.md` §6).

## Known residuals (out of scope, recorded only)

- Past `unconfirm()` calls removed `coupon_history` rows for orders that reached
  «Отримано». First15 is unaffected (its usage check reads `order_total`,
  `booster_coupon.php::hasCouponOrderUsage`); stock coupon `uses_total` / `uses_customer`
  limits may undercount. Not restored by this task.
- «Повернено» (12) and «Повернення коштів» (13) remain holding statuses: a returned
  parcel does not restock automatically. Owner decision pending; not part of WP1.
