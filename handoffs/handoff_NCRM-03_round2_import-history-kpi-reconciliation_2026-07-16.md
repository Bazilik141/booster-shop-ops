# Codex Handoff — NCRM-03 round 2: Import history from Sheets + KPI reconciliation

Date: 2026-07-16 | Parent: NCRM-01 (Done), NCRM-02 (Done) | Round 1 report: `diagnostics/NCRM-03_import-history-kpi-reconciliation_report_20260710.md`

## 1. Task ID
NCRM-03 — round 2, after owner review of the round 1 execution report. Notion: `In progress`, Current Owner `Codex (round 2)`.

## 2. Context
Round 1 (2026-07-10 snapshot) imported cleanly and matched 4/7 KPI anchors exactly or within tolerance (asset PRRO/mgmt, June revenue), but 3 anchors failed by real amounts (~8,000 грн, not rounding): warehouse PRRO, warehouse mgmt, stock+in-transit mgmt. Root causes were isolated to three named issues (see round 1 report §"Proven blockers"). Owner decisions taken 2026-07-16:
1. **Snapshot:** use the new `ncrm/import/raw/2026-07-16/` export (real Sheets tab names, more rows: 208 sales / 98 purchases / 149 writeoffs / 65 RRC / 65 stock / 14 consumables) instead of `2026-07-10/`.
2. **Six negative `Списання` rows** (`WRT-0051`, `WRT-0052`, `WRT-0094`–`WRT-0097`, net −29 units) are **out of scope for NCRM-03**. A new ticket, **NCRM-13 · Signed inventory adjustment model**, was opened in Notion for the schema work this needs. Do not net them, do not invent a workaround schema change here — exclude them from this import and report them explicitly (SKU, qty, WRT id) as deferred to NCRM-13.
3. **Mystery-box SKUs** (`PKM-JP-MIX-MBX`, `OP-JP-MIX-MBX`) with sales but no purchase lots in the snapshot: add the recipe/consumption rule now, in this round, per `plans/crm-schema-v1_2026-06-26.md` §3.1–3.4 (`mystery_box_types`, `mystery_contents`) so these SKUs stop producing negative stock.

## 3. Goal
Re-run the deterministic import against the 2026-07-16 snapshot with the three decisions applied, and get the reconciliation as close to kopiyka-tolerance as possible — with the negative-writeoff-driven residual explicitly quantified and attributed to NCRM-13, not hidden.

## 4. What to change (scope)
- Point the importer at `ncrm/import/raw/2026-07-16/` (files: `... - Продажі.csv`, `... - Закупки.csv`, `... - Списання.csv`, `... - РРЦ.csv`, `... - Склад.csv`, `... - Розхідники.csv`). Column headers are the real Sheets headers this time (not the pre-cleaned round-1 names) — re-verify the column mapping in `import-history.mjs` against these actual headers before assuming round-1 mapping still applies.
- Reuse round 1's decisions where still valid: 1:1 `purchases`↔`purchase_lots` mapping, lot status mapping table, `imported_legacy_no_rate_history` for un-reconstructable currency fields, `cost_start_date=2026-04-01` cutoff, zero/negative-qty source rows reported not inserted.
- **Negative writeoff rows:** detect the same 6 rows (or any new ones in the fresher export) by qty < 0, exclude from `writeoffs`/`writeoff_items` insert, and list them in the diagnostics report under a "Deferred to NCRM-13" section with SKU/qty/date so the residual is traceable.
- **Mystery-box consumption:** implement using the existing `mystery_box_types`/`mystery_contents` tables (already migrated, minimally seeded in NCRM-01 — verify current seed state first). For historical sales of `PKM-JP-MIX-MBX`/`OP-JP-MIX-MBX`, source the component paks from the `Списання` rows already tagged as mystery-box writeoffs (per the legacy auto-writeoff logic — cross-reference `writeoff.mystery_sale_id`-equivalent linkage in the source `Примітка`/order reference) where available; where the source data doesn't allow reconstructing actual contents, use the `provisional_unit_cost` (450/700) from schema v1 §3.2 and flag `cost_state='provisional'` rather than inventing box contents.
- New batch tag `ncrm03_20260716` (do not reuse round 1's `ncrm03_20260710` tag).
- Diagnostics report `diagnostics/NCRM-03_import-history-kpi-reconciliation_round2_report_<date>.md`: same reconciliation table format as round 1, plus the new "Deferred to NCRM-13" section and confirmation that mystery-box stock alerts are no longer negative.

## 5. What NOT to touch
- `ncrm/supabase/migrations/*` (now `0001`–`0010`, includes later NCRM-04–07/07b work) — no new migration in this round; the signed-adjustment schema work belongs to NCRM-13, not here.
- `writeoff_items.qty > 0` constraint — do not relax it to accommodate the 6 negative rows; exclude them instead.
- Round 1's artifacts (`ncrm/import/raw/2026-07-10/`, its batch `ncrm03_20260710`, its report) — leave as historical record, don't delete.
- Live production Apps Script CRM (sheet + forms), live site/OpenCart, `patches/`, `dashboard/`, other `handoffs/`.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, Product schema — unrelated codebase.
- `refunds`/`expenses` data — still out of scope for NCRM-03.

## 6. Likely files/areas
`ncrm/import/raw/2026-07-16/` (owner-provided, already in repo), `ncrm/scripts/import-history/*` (extend existing `import-history.mjs`/`reconcile.mjs`, new rollback SQL for the new batch tag), `diagnostics/NCRM-03_*round2*` (new). Codex should verify against the actual current state of `ncrm/scripts/import-history/` and `ncrm/supabase/migrations/` before assuming round 1's code applies unchanged.

## 7. Acceptance criteria
- [ ] Row counts imported match the 2026-07-16 CSVs exactly, per table, minus the explicitly excluded negative-writeoff rows (counted and reported separately)
- [ ] No orphan foreign keys
- [ ] Reconciliation report against a freshly re-pulled anchor snapshot (not the 2026-07-10 numbers) shows: asset PRRO/mgmt and a closed month's revenue/profit within 0.01 грн (as round 1 already achieved); warehouse PRRO/mgmt and stock+in-transit mgmt diffs **quantified and attributed** — if the only remaining cause is the deferred negative-writeoff residual, the report states the exact expected diff (should be explainable, not just "close")
- [ ] `PKM-JP-MIX-MBX`/`OP-JP-MIX-MBX` no longer generate negative stock alerts
- [ ] Diagnostics report includes the "Deferred to NCRM-13" section listing the 6 excluded rows by ID/SKU/qty
- [ ] Import is re-runnable in dry-run mode; rollback script for `ncrm03_20260716` provided and does not touch `ncrm03_20260710` or NCRM-01 seeds

## 8. QA / smoke test (owner)
Not checkout/payment/site-schema. This is CRM/financial data — verify manually before closing:
- [ ] Reconciliation diff table reviewed against the live CRM `Дашборд` tab at the same moment as the fresh anchor pull
- [ ] Confirm the 6 deferred rows match what's expected (cross-check against `Списання` in the live sheet)
- [ ] Spot-check the two mystery-box SKUs' resulting stock/cost state
- [ ] Confirm live production CRM (sheet + Apps Script) and remote Supabase are untouched (local-only import, as in round 1)
- [ ] `git status` before commit — no `.env.local`/keys staged

## 9. Rollback note
Batch `ncrm03_20260716`, own rollback script (`rollback_ncrm03_20260716.sql`), independent of round 1's `ncrm03_20260710` batch/rollback. Does not touch NCRM-01 lookup seeds, `MBX`/`MBX-XL` product rows, or the round 1 artifacts kept for history.

## 10. Recommended status after execution
**`In progress`** until the owner confirms the reconciliation diff — including the explained NCRM-13-deferred residual — against a live re-pull. Full `Done` for NCRM-03 is expected only after NCRM-13 closes and the residual disappears; otherwise this round can close as `Done (partial, residual tracked in NCRM-13)` if the owner accepts that split.
