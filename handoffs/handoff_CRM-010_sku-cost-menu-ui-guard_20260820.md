# Patch Handoff — CRM-010 (provisional): `updateSkuCurrentCostMenu` UI guard + Booster CRM menu entry

Date: 2026-08-20 | Parent: none
Executor: Claude Code · model=Sonnet · effort=medium-high — **owner decision recorded 2026-08-20, not re-argued.** Claude had recommended Codex/Terra (bounded two-anchor edit plus one focused test against a file already identified here; no live-file discovery needed). The owner assigned Claude Code to this package and to `CRM-011`, which also keeps a single patch author on `crm/apps-script/Code.gs` for the round — the correct outcome under the never-two-authors-on-one-file rule.

> ⚠ **Task ID is provisional and NOT registered.** `ROADMAP_SOP.md` names `CRM-010` as the next free `CRM-` ID, but no live Notion collision query was run and no Notion page was created. Registration requires an explicit owner instruction through `bs-roadmap-write`, including the live-series collision check the SOP demands.

---

## 1. Task ID

`CRM-010` (provisional — see the warning above).

## 2. Context

On 2026-08-20 at 08:04 Kyiv the owner ran `updateSkuCurrentCostMenu` from the Apps Script editor, after correcting `LOT-0113` quantity from 4 to 5 in `Закупки!H108`. The execution log shows, in order: execution started, `updateSkuCurrentCost_: updated 32 SKUs`, then `Exception: Cannot call SpreadsheetApp.getUi() from this context` attributed to `updateSkuCurrentCostMenu`. Because that Logger line is the final statement of `updateSkuCurrentCost_`, the warehouse-cost write to `Склад!I:J` had already completed; only the terminal alert failed.

Two defects in `crm/apps-script/Code.gs`:

1. `updateSkuCurrentCostMenu` (~lines 4681–4685) calls `SpreadsheetApp.getUi().alert(...)` unguarded. Sibling routines in the same file already guard exactly this: `createDailyInventoryMaintenanceTrigger` (~4677) and `updateLotStatuses` (~4645) both use `try { ... } catch (e) { Logger.log(message); }`.
2. `onOpen` (~line 20) has no menu entry for `updateSkuCurrentCostMenu`. The only way to invoke it today is the Apps Script editor — precisely the context where the unguarded alert always throws. The function is therefore unrunnable by the owner by any route.

Secondary defect in the same function: it never calls `invalidateDoGetCache_()`, so the owner dashboard keeps serving cached figures after a manual recalculation for up to the longest cache TTL (`sku_list` = 300 s). The adjacent `updateExpectedStockFormulaMenu` does call it.

## 3. Goal

Make the warehouse-cost recalculation runnable from the `Booster CRM` menu and safe to run without a UI context, and let it invalidate the dashboard cache — without changing anything `updateSkuCurrentCost_` computes.

## 4. What to change

- `crm/apps-script/Code.gs` — `updateSkuCurrentCostMenu`: capture the return value of `updateSkuCurrentCost_(ss)`; `SpreadsheetApp.flush()`; call `invalidateDoGetCache_()`; build a message that includes the updated-SKU count; wrap the alert in `try/catch` with a `Logger.log(message)` fallback, using the same shape as `createDailyInventoryMaintenanceTrigger`; `return` the result so the editor execution log shows it. Owner-facing message text: `Собівартість складу оновлено: <N> SKU.`
- `crm/apps-script/Code.gs` — `onOpen`: add `.addItem('Оновити собівартість складу', 'updateSkuCurrentCostMenu')` immediately after the existing `.addItem('Оновити очікуваний залишок', 'updateExpectedStockFormulaMenu')`. Do not reorder or relabel any existing item.
- `crm/apps-script/tests/` — one new focused test using the existing ES-module + `node:vm` harness (`tests/expected-stock-formula.test.mjs` is the reference shape). It must prove that `updateSkuCurrentCostMenu` completes when `SpreadsheetApp.getUi()` throws, and that `invalidateDoGetCache_` is called exactly once per run.
- `crm/apps-script/SOURCE_STATE.md` — record this change, and the mirror/live discrepancy from section 9, in the same session. Required by `AGENTS.md` → Apps Script mirrors, rule 2.

Line numbers above are from the current mirror and are **likely, not confirmed** — the executor verifies every anchor against the actual file before editing.

## 5. What NOT to touch

- `updateSkuCurrentCost_` itself — its FIFO/remainder maths is proven by the 32-SKU run and is out of scope.
- `updateExpectedStockFormulaMenu` — it carries the same unguarded `getUi()` pattern, but it already has a working menu entry, so it never reaches the failure in practice. Different impact, separate decision, not this package.
- The undeployed follow-up already present in the mirror (all-open-purchases / complete tracked parcels, `apiRecentPurchasesForUpdate_`, `include_all_open`). Do not revert, re-edit, or re-scope it.
- Frozen sale-cost columns in `Продажі` (L/M), any `Закупки` or `Склад` values, and FIFO recalculation of already-completed sales — including `OC-FOP-0324`. That is `CRM-011` (`handoffs/handoff_CRM-011_oc-fop-0324-fifo-cost-repair_20260820.md`), a separate work package with its own rollback. Same executor, same file, but pasted and QA'd separately — never as one blob.
- Protected zones, none of which this task touches: `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, schema/JSON-LD.

## 6. Likely files / areas

- `crm/apps-script/Code.gs` — `onOpen` (~20); `updateSkuCurrentCostMenu` (~4681–4685); guard reference patterns (~4645, ~4677); `invalidateDoGetCache_` definition (search by name).
- `crm/apps-script/tests/expected-stock-formula.test.mjs` — harness reference only.
- `crm/apps-script/SOURCE_STATE.md`.

## 7. Acceptance criteria

- [ ] `updateSkuCurrentCostMenu` run from the Apps Script editor finishes with status `Завершено`, no exception; the log contains `updateSkuCurrentCost_: updated <N> SKUs` and a message line carrying the same `<N>`.
- [ ] `Booster CRM → Оновити собівартість складу` in the spreadsheet shows the alert `Собівартість складу оновлено: <N> SKU.`
- [ ] `invalidateDoGetCache_()` is called exactly once per run — asserted by the new test.
- [ ] The `Booster CRM` menu contains the new item, and every pre-existing item is present, unchanged, in the same order.
- [ ] The local Apps Script test suite passes, including the new test.
- [ ] Idempotency: running the fixed function twice against an unchanged workbook returns the same `<N>` and leaves `Склад!I:J` byte-identical between runs.

## 8. QA / smoke test (owner runs)

1. Resolve section 9 **before** pasting anything.
2. Paste the agreed source into the live bound script and save.
3. Reload the CRM spreadsheet (F5) so `onOpen` rebuilds the menu.
4. `Booster CRM → Оновити собівартість складу` → expect the alert with the SKU count.
5. Extensions → Apps Script → run `updateSkuCurrentCostMenu` from the editor → expect completion with no exception and the message in the log.
6. `Склад`, row `PKM-EN-Q2-MTIN-SAL`, columns I and J — values present, and identical after the two runs above.
7. Owner dashboard, hard refresh — warehouse cost figures match `Склад`.
8. CRM risky zone: run the read-only dashboard `integrity_check` and record its bounded output in the diagnostic. This change edits no sheet structure and no formula column, so `OPS-CRMINTEGRITY` is not strictly triggered; the check is recorded as CRM-zone evidence, not as a gate this change created.
9. Web App publication is **not** required for a menu-only change — menu items and editor runs execute the current bound source. Publish and record a new version only if the same paste also carries dashboard-API changes (see section 9).

## 9. Owner decision required before deployment

`crm/apps-script/Code.gs` is modified and uncommitted in the local clone, and `SOURCE_STATE.md` still records the parcel-completeness follow-up as **undeployed after V131**. But the script the owner pasted on 2026-08-20 already contains the `include_all_open` handling from that follow-up. Two spot markers were compared (`updateSkuCurrentCostMenu` body and `include_all_open`); **no byte comparison was performed**. So the mirror's deployment note is stale, contradicted, or both.

Decide before any paste:

- **(a)** The live bound source already matches the mirror → paste the mirror with this fix applied, one save, QA covers this fix only.
- **(b)** The live source differs → the owner supplies a fresh export first, the executor rebases this two-anchor edit onto it, and only then does the owner paste.

**(b) is the safe default whenever there is doubt.** Never paste a whole file over a live script whose current contents have not been verified.

## 10. Rollback note

Live: Apps Script editor → project history → restore the version immediately preceding this paste → save → reload the spreadsheet. The change writes no sheet data beyond what `updateSkuCurrentCost_` already wrote, so there is no data rollback. Repository: revert the two anchors in `crm/apps-script/Code.gs`, delete the new test file, restore the `SOURCE_STATE.md` entry. If a Web App version was published in the same paste, republish the preceding version.

## 11. Recommended status after execution

`In progress` while the executor edits → `Ready for deploy` once the local suite passes → `Done` only after the owner completes section 8 and reports the `integrity_check` output (ROADMAP_SOP Definition of Done). Notion status writes go through Claude via `bs-roadmap-write` on explicit owner instruction, with the `ROADMAP_TASKS` dashboard mirror updated in the same pass.

## Risks

- `invalidateDoGetCache_()` bumps `CRM_DOGET_CACHE_VERSION`, so the first dashboard load after each manual recalculation is cold and slower. Expected behaviour, not a defect.
- `updateSkuCurrentCost_` reads `Закупки`, `Продажі`, `Списання` and `Міграції_Складу` and writes `Склад!I:J` for every SKU. On a large workbook a menu run can approach the Apps Script execution limit. That is a pre-existing property of the underlying function, not of this change.
- CRM is a risky zone per `AGENTS.md`. No referral to `bs-seo-risk-gate`, `bs-checkout-smoke` or `bs-merchant-schema-qa` is required: no SEO surface, no checkout/payment/fiscalization path, and no schema or Merchant feed is touched.
- Parallel-writer risk on `crm/apps-script/Code.gs` — see the executor line.

## Not verified in this handoff

- Whether the daily `runNightlyInventoryMaintenance` trigger (05:00) is actually installed in the live project. Claude has no access to Apps Script triggers.
- Whether the live bound source is byte-identical to `crm/apps-script/Code.gs`.
- The live published Web App version as of 2026-08-20.
- Whether `PKM-EN-Q2-MTIN-SAL` holds remaining stock from a single lot; if it does not, `Склад!I:J` will show a FIFO-weighted average rather than the `LOT-0113` unit figures.
