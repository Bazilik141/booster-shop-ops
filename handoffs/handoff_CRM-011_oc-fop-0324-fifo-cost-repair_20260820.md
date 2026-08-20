# Patch Handoff — CRM-011 (provisional): FIFO cost drift after the `LOT-0113` quantity correction

Date: 2026-08-20 | Parent: none | Sibling: `CRM-010` (`handoffs/handoff_CRM-010_sku-cost-menu-ui-guard_20260820.md`)
Executor: Claude Code · model=Opus · effort=high — **owner decision, recorded without re-argument.** Claude had recommended Codex/Terra for `CRM-010`; the owner assigned Claude Code to both. Opus/high is correct for this package regardless: it touches frozen historical accounting in a risky zone (CRM) and needs live-evidence reasoning before any write.

> ⚠ **Task ID is provisional and NOT registered.** `ROADMAP_SOP.md` names `CRM-010` as the next free `CRM-` ID; `CRM-010` is claimed by the sibling handoff, so this one provisionally takes `CRM-011`. No live Notion collision query was run and no Notion page exists for either. Registration needs an explicit owner instruction through `bs-roadmap-write`.

> 🛑 **This package is diagnostic-first. Stage A writes nothing. Do not start Stage B until the owner has approved an exact row list from Stage A output.**

---

## 1. Task ID

`CRM-011` (provisional — see the warning above).

## 2. Context

On 2026-08-20 Codex corrected `Закупки!H108` for `LOT-0113` (SKU `PKM-EN-Q2-MTIN-SAL`, Meest parcel `27072026`, delivered 2026-08-01) from 4 units to 5 — the owner had entered the wrong quantity at purchase. Codex reported the lot's recalculated unit cost as **551.90 грн ПРРО** and **585.01 грн управлінська**, and flagged that `Склад` and the sale `OC-FOP-0324` still hold the pre-correction figures.

`updateSkuCurrentCost_` has since run (owner, 08:04 Kyiv, 32 SKUs updated), so `Склад!I:J` is refreshed. Sale rows are not: `fixSaleCostForRow_` deliberately refuses to recompute a row once its cost is frozen — the guard is `if (!formulas[0] && !formulas[1] && methodCell.indexOf('FIFO') === 0) return null;` (literal values in `Продажі!L:M` plus a method string starting with `FIFO`). There is no existing routine that re-freezes an ordinary already-completed sale. `recalculateMysteryBoxOrderCost_` covers only MBX orders; `repairOCFOP0320MysteryBoxCost` is the precedent for a bounded, owner-run, one-off recovery function.

**The scope is almost certainly wider than `OC-FOP-0324`.** Adding a unit to `LOT-0113` changes the FIFO pool for that SKU from the lot's delivery date onward. `calculateFifoSaleCost_` walks lots in delivery order and falls back to current stock cost when the pool runs short, so any sale of `PKM-EN-Q2-MTIN-SAL` on or after 2026-08-01 may have frozen a cost that the corrected pool no longer produces — including rows that silently used the `fallback` branch. `OC-FOP-0324` is the row the owner noticed, not necessarily the only one. Stage A exists to establish the real list instead of guessing it.

Neither Claude nor Claude Code can read the live workbook. All live evidence in this task comes from an owner-run, read-only Apps Script function whose bounded JSON output the owner pastes back.

## 3. Goal

**Stage A:** produce a bounded, read-only, owner-runnable diagnostic that lists every `Продажі` row whose frozen cost no longer matches what FIFO would now compute for `PKM-EN-Q2-MTIN-SAL`, with before/after figures per row. No writes.

**Stage B (only after owner approval of the row list):** re-freeze exactly the approved rows, with a full before/after snapshot, an audit marker, dry-run support, and idempotency.

## 4. What to change

### Stage A — diagnostic (this round)

- `crm/apps-script/Code.gs` — add one read-only function, named in the style of the existing one-off recovery routines, that:
  - takes an SKU (default `PKM-EN-Q2-MTIN-SAL`) and returns a compact JSON object;
  - selects candidate rows from `Продажі` for that SKU where `isActualSaleForCost_(values)` is true;
  - for each candidate returns: row number, `Номер замовлення / операції`, sale date, qty, current `L` (ПРРО unit) and `M` (mgmt unit), the method string (col 30), the audit string (col 31, already truncated by `trimCostAudit_`), and the values `calculateFifoSaleCost_` produces **now** for the same row;
  - flags each row `would_change: true|false` using the same 0.009 tolerance the codebase uses elsewhere;
  - flags `skip_reason` for rows it must never repair: `is3dpPackagingSku_(sku)` → `3dp_projection`, `isMysteryBoxSale_(sku, name)` → `mystery_box`, non-actual sale → `not_actual`;
  - **writes nothing at all** — no `setValue`, no `setFormula`, no cache invalidation, no `updateSkuCurrentCost_` call;
  - caps output (e.g. 50 rows) and returns a `truncated` counter rather than streaming sheet contents, per `OPS-CRMINTEGRITY`'s bounded-output constraint;
  - `Logger.log(JSON.stringify(result))` so the owner can copy it straight out of the execution log;
  - guards its own `getUi()` usage, or uses none — it must run cleanly from the Apps Script editor. See `CRM-010` for why.
- `crm/apps-script/tests/` — one focused test on the existing `node:vm` harness (`tests/expected-stock-formula.test.mjs` is the shape) proving the diagnostic writes nothing and correctly classifies a drifted row, an unchanged row, a 3D-P row, and an MBX row.
- `crm/apps-script/SOURCE_STATE.md` — record the addition in the same session (`AGENTS.md` → Apps Script mirrors, rule 2).

### Stage B — repair (do NOT start this round)

Specified here so the owner can see the shape; **blocked until the Stage A row list is approved**:

- a bounded repair function accepting an explicit row list plus `{ dry_run }`, defaulting to dry-run;
- before/after snapshot of `L`, `M`, method and audit for every touched row, returned and logged;
- writes only `Продажі` columns 12, 13, 30, 31, 32 on approved rows, appending an audit marker such as `crm011_refreeze=<date>`;
- idempotent — a second run over the same rows reports `already_applied` and changes nothing;
- calls `invalidateDoGetCache_()` once at the end;
- refuses to run on any row carrying a `skip_reason`.

## 5. What NOT to touch

- **3D-P sale rows** (`is3dpPackagingSku_`). Their `L`/`M` are written by `project3dpAccountingToCrm_` from the `3D_облік_замовлень` ledger. A generic FIFO re-freeze would silently destroy that projection. Hard exclusion in both stages.
- **Mystery Box rows** (`isMysteryBoxSale_`). They are owned by `recalculateMysteryBoxOrderCost_` and its frozen component ledger. Hard exclusion.
- `calculateFifoSaleCost_`, `fixSaleCostForRow_`, `getFifoCostBatches_`, `updateSkuCurrentCost_` — read them, do not modify them. The freeze guard is intentional behaviour, not a bug.
- `Закупки` — `LOT-0113` is already corrected. Do not re-touch `H108` or any lot row.
- `Склад!I:J` — already refreshed by the 08:04 run.
- `Використання_компонентів`, `Використання_фурнітури`, `3D_облік_замовлень`, `Міграції_Складу` — all append-only ledgers, out of scope.
- The `CRM-010` scope (`updateSkuCurrentCostMenu`, `onOpen`). Same file, separate work package, separate rollback.
- Protected zones, none touched here: `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, schema/JSON-LD.

## 6. Likely files / areas

Line numbers are from the current mirror and are **likely, not confirmed** — verify against the actual file.

- `crm/apps-script/Code.gs` — `fixSaleCostForRow_` and its freeze guard; `calculateFifoSaleCost_`; `getFifoCostBatches_`; `getConsumedQtyBeforeSale_`; `isActualSaleForCost_`; `is3dpPackagingSku_`; `isMysteryBoxSale_`; `ensureSaleCostAuditColumns_`; `repairMysteryBoxOrderComponentCost_` / `repairOCFOP0320MysteryBoxCost` (~7400s) as the one-off recovery precedent; `trimCostAudit_`.
- `crm/apps-script/tests/expected-stock-formula.test.mjs`, `tests/mystery-box-order-components-repair.test.mjs` — harness references.
- `crm/apps-script/SOURCE_STATE.md`.

## 7. Acceptance criteria (Stage A)

- [ ] The diagnostic runs from the Apps Script editor to `Завершено`, no exception, and the execution log contains one JSON line.
- [ ] The JSON lists every `PKM-EN-Q2-MTIN-SAL` sale row that `isActualSaleForCost_` accepts, each with `row`, `order`, `date`, `qty`, `current_prro_unit`, `current_mgmt_unit`, `fifo_prro_unit`, `fifo_mgmt_unit`, `would_change`, `method`, `audit`, `skip_reason`.
- [ ] `OC-FOP-0324` appears in the output with `would_change` populated.
- [ ] A byte-level check of the function body confirms zero `setValue` / `setFormula` / `invalidateDoGetCache_` / `updateSkuCurrentCost_` calls.
- [ ] Running it twice leaves `Продажі` and `Склад` unchanged — verified by the owner re-reading two cells before and after (see QA step 4).
- [ ] The new test passes together with the existing Apps Script suite.
- [ ] Output is capped and reports `truncated` when the cap is hit.

## 8. QA / smoke test (owner runs)

1. Resolve the paste question in `CRM-010` section 9 first — the same live-vs-mirror uncertainty applies to this paste.
2. Paste the agreed source into the live bound script and save. No Web App publication is needed for an editor-run diagnostic.
3. Before running: open `Продажі`, note the exact values of `L` and `M` on the `OC-FOP-0324` row, and `Склад` row `PKM-EN-Q2-MTIN-SAL` columns I and J. Write them down.
4. Extensions → Apps Script → run the diagnostic → copy the whole JSON line out of the execution log.
5. Re-read the same four cells from step 3. They must be identical. If anything changed, stop and report — the function is not read-only and Stage B must not proceed.
6. Paste the JSON back into the Cowork chat for review.
7. Risky zone (CRM): run the dashboard read-only `integrity_check` and record its bounded output. This change adds no sheet structure and edits no formula column, so `OPS-CRMINTEGRITY` is not strictly triggered; the check is recorded as CRM-zone evidence.

## 9. Owner decisions required before Stage B

1. **Scope.** Repair only `OC-FOP-0324`, or every drifted row Stage A finds? Repairing one row while leaving others drifted keeps the SKU internally inconsistent.
2. **Accounting.** `Продажі!L` is the ПРРО cost basis and `M` feeds profit, margin, channel stats and the monthly summary. Re-freezing changes **historical** reported profit for the affected months. If any affected sale has already been fiscalised or reported, whether its recorded cost basis may be restated is an owner/accountant decision — Claude is not a financial or tax advisor and states this as a decision, not advice.
3. **Cut-off.** Whether rows dated before the CRM cost start date (`Налаштування!B8`, read by `getCostStartDate_`) are in or out.
4. **Lot status.** Check whether `LOT-0113` should now read `Частково продано` rather than `Продано` after the 4→5 correction. `runNightlyInventoryMaintenance` (05:00) recomputes this automatically **if that trigger is installed** — unverified. Confirm from `Закупки!Q108` rather than assuming.

## 10. Rollback note

**Stage A:** nothing to roll back in the workbook — the function writes nothing. To remove it: restore the previous script version via Apps Script editor → project history, save; in the repository revert the added function, delete the new test, restore the `SOURCE_STATE.md` entry.

**Stage B (when it happens):** before writing, the owner takes a named copy of the workbook (File → Make a copy, name it `До CRM-011 <date>`) — the same precaution used for `CRM-008` (`Rollback-копія: 10 серпня, 15:01 До 008`). Cell-level rollback then uses the before/after snapshot the repair returns: re-write the recorded `L`, `M`, method and audit values for each touched row. Google Sheets version history is the second line of defence.

## 11. Recommended status after execution

`In progress` for Stage A → back to the owner with the JSON → explicit owner approval of the row list → `In progress` for Stage B → `Done` only after the owner confirms the repaired figures and the `integrity_check` output (ROADMAP_SOP Definition of Done). Notion writes go through Claude via `bs-roadmap-write` on explicit owner instruction, with the `ROADMAP_TASKS` dashboard mirror updated in the same pass.

## Risks

- **Widening the blast radius.** A generic "recalculate sale cost" helper would be tempting and dangerous: 3D-P and MBX rows must never go through the FIFO path. The exclusions in section 5 are load-bearing.
- **Cascade.** Re-freezing an earlier sale changes `getConsumedQtyBeforeSale_` for later sales of the same SKU. Stage A must compute each candidate independently, in row order, and the owner must see the whole list before any write.
- **Silent fallback rows.** A row whose audit contains `fallback:` consumed stock the FIFO pool could not supply. After the `LOT-0113` correction some of those may now resolve to a real lot. These are the rows most likely to move, and the audit string is the evidence.
- CRM is a risky zone per `AGENTS.md`. No referral to `bs-seo-risk-gate`, `bs-checkout-smoke` or `bs-merchant-schema-qa`: no SEO surface, no checkout/payment path, no schema or Merchant feed is touched. Fiscalisation is *not* touched in code — but see section 9.2 for the accounting question it raises.
- Parallel-writer risk on `crm/apps-script/Code.gs`: `CRM-010` edits the same file. One executor holds both, and the two work packages are pasted and QA'd in sequence, never as one blob.

## Not verified in this handoff

- Any live value: no figure from `Продажі`, `Закупки` or `Склад` was read. `551.90` / `585.01` are **Codex's reported figures relayed by the owner**, not independently confirmed.
- Which sale rows exist for `PKM-EN-Q2-MTIN-SAL`, how many there are, or whether `OC-FOP-0324` is the only drifted one. That is exactly what Stage A establishes.
- Whether the `runNightlyInventoryMaintenance` 05:00 trigger is installed.
- Whether the live bound source is byte-identical to `crm/apps-script/Code.gs`.
