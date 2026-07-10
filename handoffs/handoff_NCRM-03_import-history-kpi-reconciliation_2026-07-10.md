# Codex Handoff — NCRM-03: Import history from Sheets + KPI reconciliation

Date: 2026-07-10 | Parent: NCRM-01 (Done), NCRM-02 (Done)

## 1. Task ID
NCRM-03 — Імпорт історії зі Sheets + звірка KPI. Notion: `Not started` → moving to `In progress`. Blocker `NCRM-01` closed (schema v1 migrated local+remote); `NCRM-02` also closed (repository layer + Next.js skeleton reads Supabase).

## 2. Context
- Schema v1 (Stage 1–4) is fully migrated in `ncrm/supabase/migrations/0001_stage1_core.sql` … `0005_grants_for_app_read.sql` (28 tables, 12 views, 11 functions; verified local + remote per `diagnostics/NCRM-01_supabase-project-sql-migrations_report_20260705.md`). No product/order/purchase/stock data has been imported yet — only confirmed lookup seeds and the minimal `MBX`/`MBX-XL` product identities.
- `ncrm/lib/repositories/*` (NCRM-02) already reads from Supabase via Repository Pattern; import must go through the DB directly (SQL/service-role), not through these read-only repos.
- Source of truth for legacy formulas: `plans/crm-financial-model_2026-06-26.md` (§B, §C, §F, §K — landed cost, mgmt cost ×1.06, FIFO COGS, revenue/profit formulas). Source of truth for target structure: `plans/crm-schema-v1_2026-06-26.md` (Stage 1–4 table defs, §1.3 `products` from РРЦ/Товари, §1.6–1.7 `purchases`/`purchase_lots`, §3.5 `consumables`, §3.6 `writeoffs`).
- Live source spreadsheet: Google Sheet **"Booster Shop CRM — облік товарів"** (owner's Drive, tabs include `Продажі`, `Закупки`, `Списання`, `РРЦ`/`Товари`, `Розхідники`, `Налаштування`, `Курси`). This is the **live production CRM** the shop runs on daily — it must keep working unmodified; this task only reads from it once, it does not write back.
- `app_config.cost_start_date = 2026-04-01` (per schema v1 §1.1) — this is the confirmed start of warehouse cost accounting; historical rows before this date are out of scope unless the owner says otherwise.
- Snapshot KPI anchors pulled from the CRM's `Дашборд` tab on 2026-07-10 14:25 (for orientation only — **these numbers move daily and must NOT be used as the final reconciliation target**; a fresh snapshot must be taken at the exact moment of import):
  - Вкладено в товар / ПРРО: 192 433,90 грн · Управлінське вкладення в товар: 203 979,93 грн
  - Вартість залишків / ПРРО (склад): 49 074,03 грн · Управлінська вартість залишків (склад): 52 018,47 грн
  - Продажі/прибуток за попередній повний місяць (червень): 40 324,75 грн / 9 697,83 грн

## 3. Goal
One-time, deterministic import of the confirmed history (~300–400 rows across sales, purchases, writeoffs, RRC/products, consumables) into Supabase per schema v1, then a reconciliation report proving computed KPIs match the live CRM's anchors within kopiyka tolerance. DoD is literally: "числа збігаються з поточними в межах копійок."

## 4. What to change (scope)
- **Owner step first (blocking, before Codex writes the import script):** export the five source tabs (`Продажі`, `Закупки`, `Списання`, `РРЦ`+`Товари`, `Розхідники`) from the live sheet to CSV and commit them as a frozen snapshot under `ncrm/import/raw/2026-07-XX/` (exact date = agreed cutover date). Codex should not attempt to read the live Google Sheet directly — work only from this frozen, versioned snapshot, so the import is reproducible and testable.
- `ncrm/scripts/import-history/` — one-time import scripts (Node/TS, using the same `@supabase/supabase-js` client pattern as `ncrm/lib/supabase/client.ts`), split by table group, with a `--dry-run` flag that prints planned inserts/row counts without writing:
  - `products` + `product_prices` from `РРЦ`/`Товари` (brand/category/game/language mapped to the existing lookup tables from NCRM-01 — verify which lookup values already exist vs. need adding; do not invent brand/category values not present in the source data)
  - `purchases` + `purchase_lots` from `Закупки` — **flag as open decision, verify against actual exported columns:** the legacy sheet appears to store fees already allocated at the lot level (no separate order-level header), so Codex must confirm from the real CSV whether to synthesize one `purchases` row per `purchase_lots` row (1:1) or group by `order_ref`, and document the choice in the PR. Canonical lot statuses + `legacy_status` mapping per schema v1 §1.7 table (`На складі в Японії/Виграно → ordered`, `Частково продано → selling`, etc.).
  - `writeoffs` + `writeoff_items` from `Списання`, mapped to the 7 existing writeoff types (confirm no new types needed)
  - `consumables` from `Розхідники` (`unit_cost`, `initial_stock`, `initial_in_transit`, `activation_date`, `is_packaging`)
  - `sales` + `sale_items` from `Продажі`, with `prro_unit`/`mgmt_unit` snapshotted from the imported lots (not recomputed live), `cost_method`/`cost_audit` preserved for traceability
- **Currency conversion:** the legacy sheet has no historical rate table (per `crm-financial-model_2026-06-26.md` §C2) — old JPY lots only have the rate at data-entry time baked into already-converted UAH totals. Import these as-is into `*_uah` fields; where the original `*_amount`/`*_currency`/`*_rate` split cannot be reconstructed, leave those fields NULL and add a note `imported_legacy_no_rate_history` rather than fabricating a rate.
- **Traceability tag:** every imported row gets a `note`/`legacy_status`-style marker (e.g. `imported_batch = 'ncrm03_2026-07-XX'`) so the batch can be identified and reversed cleanly.
- Reconciliation script/query set comparing computed values against the frozen anchor snapshot (see §7) — output as a diff table, not just pass/fail.
- Diagnostics report at `diagnostics/NCRM-03_import-history-kpi-reconciliation_report_<date>.md` documenting row counts imported per table, any rows skipped/flagged, and the final reconciliation diff.

## 5. What NOT to touch
- `ncrm/supabase/migrations/0001`–`0005` — schema is closed from NCRM-01/02; if the real export reveals a genuine schema gap, stop and flag it as a new ticket rather than editing these files.
- The **live production Apps Script CRM** (the Google Sheet itself, its Apps Script code, `Внести_*` forms) — read-only, one-time export only, never write back.
- Live site/OpenCart, `patches/`, `dashboard/` (the current live dashboard/CRM the shop uses daily), other `handoffs/`.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, Product schema — unrelated codebase, not touched by this task.
- Do not add `refunds` or `expenses` data — out of scope for NCRM-03 (Stage 2 `refunds` / Stage 4 `expenses` are separate, not listed in this task's Notion description).

## 6. Likely files/areas
`ncrm/import/raw/` (new, owner-provided CSV snapshot), `ncrm/scripts/import-history/` (new), `diagnostics/NCRM-03_*` (new report). No changes expected in `ncrm/app/`, `ncrm/lib/repositories/*`, or `ncrm/supabase/migrations/*` — Codex should verify against actual project files before assuming otherwise.

## 7. Acceptance criteria
- [ ] Row counts imported match the row counts in the frozen CSV snapshot exactly (reported per table: products, product_prices, purchases, purchase_lots, writeoffs, writeoff_items, consumables, sales, sale_items)
- [ ] No orphan foreign keys (every `purchase_lots.product_id`, `sale_items.product_id`, `sale_items.sale_id`, etc. resolves)
- [ ] Reconciliation report shows, against a **freshly re-pulled** anchor snapshot taken at the actual cutover moment (not the 2026-07-10 example numbers above):
  - warehouse PRRO cost (`Вартість залишків / ПРРО (склад)`) — diff ≤ 0.01 грн
  - warehouse mgmt cost (`Управлінська вартість залишків (склад)`) — diff ≤ 0.01 грн
  - asset cost (non-warehouse: ordered + in_transit lots) — diff ≤ 0.01 грн
  - at least one fully-closed month's revenue and net profit (via `v_pnl_monthly`/`v_sales_report` equivalents) — diff ≤ 0.01 грн
- [ ] `npx supabase db reset` (local) still applies 0001–0005 cleanly with the import scripts layered on top via a separate seed/import step (not baked into migrations)
- [ ] Import is re-runnable in dry-run mode without side effects; a documented rollback query removes exactly the tagged batch

## 8. QA / smoke test (owner)
Not checkout/payment/site-schema — `bs-checkout-smoke`/`bs-merchant-schema-qa` not applicable. This **does** touch financial/CRM data, so name the risk explicitly and verify manually before closing:
- [ ] Row counts in diagnostics report match owner's expectation (~300–400 total)
- [ ] Reconciliation diff table reviewed line-by-line by owner against the live CRM `Дашборд` tab at the same moment
- [ ] Spot-check 3–5 individual sales/purchase lots against the source sheet by `order_no`/`lot_code`
- [ ] Confirm live production CRM (sheet + Apps Script) is untouched and still the system of record until the owner explicitly decides to cut over
- [ ] `git status` before commit — no `.env.local`/keys staged

## 9. Rollback note
All imported rows carry `imported_batch = 'ncrm03_<date>'` (or equivalent note marker). Rollback = `DELETE ... WHERE imported_batch = 'ncrm03_<date>'` in dependency order (sale_items → sales, purchase_lots → purchases, writeoff_items → writeoffs, product_prices → products, consumables), documented as an explicit script, not ad hoc. Does not touch NCRM-01 lookup seeds or `MBX`/`MBX-XL` product rows. No impact on the live production CRM sheet or the live site.

## 10. Recommended status after execution
**`In progress`** until the owner confirms the reconciliation diff is within kopiyka tolerance against a live re-pull (literal Notion DoD). Then → `Done`.
