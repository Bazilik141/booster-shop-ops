# Claude Code Report — CRM-010: `updateSkuCurrentCostMenu` UI guard + Booster CRM menu entry

Date: 2026-08-20
Executor: Claude Code (owner-assigned, recorded in the handoff without re-argument)
Handoff: `handoffs/handoff_CRM-010_sku-cost-menu-ui-guard_20260820.md`
Sibling package: `CRM-011` — same file, **separate** work package, separate paste, separate QA. Not started in this round.

> This task produces **no PHP patch and no server deploy**. It edits the repository mirror of the live
> bound Apps Script project. Deployment is the owner pasting source into the live script editor.
> The template's `patches/`, `php -l` and `_patch_backups/` sections are therefore marked N/A with the
> reason, not filled with invented output.

## Scope

1:1 with handoff section 4. Three defects, all fixed, nothing else touched:

| # | Defect | Fix |
|---|---|---|
| 1 | `updateSkuCurrentCostMenu` called `SpreadsheetApp.getUi().alert(...)` unguarded → threw `Cannot call SpreadsheetApp.getUi() from this context` on every editor run | alert wrapped in `try { ... } catch (e) { Logger.log(message); }`, the same shape as `createDailyInventoryMaintenanceTrigger` (Code.gs:4678) |
| 2 | `onOpen` had no menu entry, so the editor was the only invocation route — exactly the context that always threw | `.addItem('Оновити собівартість складу', 'updateSkuCurrentCostMenu')` added immediately after the expected-stock item |
| 3 | The function never called `invalidateDoGetCache_()`, so the dashboard served cached figures for up to 300 s (`sku_list` TTL) after a manual recalculation | `SpreadsheetApp.flush()` then `invalidateDoGetCache_()` before the message |

Plus, as specified: the result of `updateSkuCurrentCost_(ss)` is captured, the message carries the
updated-SKU count (`Собівартість складу оновлено: <N> SKU.`), and the result is returned so the editor
execution log shows it.

**Deviations: none.**

### Anchor verification

Handoff line numbers were marked "likely, not confirmed". Both were verified against the actual file
before any edit, and both were correct. Each anchor matched **exactly once**:

```
anchor A  .addItem('Оновити очікуваний залишок', 'updateExpectedStockFormulaMenu')   count=1  (line 20)
anchor B  function updateSkuCurrentCostMenu() { ... }                                count=1  (lines 4681-4685)
pre-check "updateSkuCurrentCostMenu'" as a menu target                               count=0  (not already applied)
```

The edit script asserted `count === 1` for each anchor and aborted on any other value; it also
re-checked for CRLF after the write.

### Respected exclusions (handoff section 5)

- `updateSkuCurrentCost_` — read only, **not modified**. Its FIFO/remainder maths is untouched.
- `updateExpectedStockFormulaMenu` — same unguarded `getUi()` pattern, deliberately left alone.
- The uncommitted parcel-completeness follow-up already in the working tree (`apiRecentPurchasesForUpdate_`,
  `include_all_open`, `recent-purchases.test.mjs`) — **not reverted, not re-edited, not re-scoped.** It
  appears in `git diff` because it was already there; it is not part of CRM-010.
- All `CRM-011` territory: `Продажі!L:M`, `calculateFifoSaleCost_`, `fixSaleCostForRow_`, `OC-FOP-0324`.
- Protected zones: none touched (no SEO surface, no checkout/payment/fiscalisation, no schema, no feed).

## Files touched

```
crm/apps-script/Code.gs                              — 2 hunks, +7 -2 lines (CRM-010 only)
crm/apps-script/tests/sku-current-cost-menu.test.mjs — new, 105 lines
crm/apps-script/SOURCE_STATE.md                      — mirror-state record (AGENTS.md → Apps Script mirrors, rule 2)
diagnostics/CRM-010_sku-cost-menu-ui-guard_report_20260820.md — this file
```

No file was created in `patches/` and no copy was placed in the owner's Downloads folder —
`CODEX_WORKFLOW.md` → Output locations forbids an upload copy when the task produces no server patch.

Mirror before → after: **7 930 → 7 936 lines**, **485 639 → 485 907 bytes**, **454 → 454** top-level
`function` declarations. Line endings **LF preserved**; no CRLF introduced.

No git write was made. The working tree is left for the owner to commit.

## Bounded diff (CRM-010 hunks only)

```diff
@@ crm/apps-script/Code.gs — onOpen @@
 .addItem('Оновити довідники SKU', 'setupCrmCatalogOptionInfrastructure')
 .addItem('Оновити очікуваний залишок', 'updateExpectedStockFormulaMenu')
+.addItem('Оновити собівартість складу', 'updateSkuCurrentCostMenu')
 .addItem('Налаштувати OpenAI ключ', 'setupOpenAiApiKey')
 .addToUi();

@@ crm/apps-script/Code.gs — updateSkuCurrentCostMenu @@
 function updateSkuCurrentCostMenu() {
 const ss = SpreadsheetApp.getActiveSpreadsheet();
-updateSkuCurrentCost_(ss);
-SpreadsheetApp.getUi().alert('Собівартість складу оновлено.');
+const result = updateSkuCurrentCost_(ss);
+SpreadsheetApp.flush();
+invalidateDoGetCache_();
+const message = 'Собівартість складу оновлено: ' + result.updated + ' SKU.';
+try { SpreadsheetApp.getUi().alert(message); } catch (e) { Logger.log(message); }
+return result;
 }
```

`git diff crm/apps-script/Code.gs` also shows one hunk near line 2571
(`apiRecentPurchasesForUpdate_` / `include_all_open`). **That hunk is pre-existing and uncommitted, not
part of CRM-010.** It was present in the working tree before this session and was left untouched.

## Local check result

Node VM parse gate (the Apps Script equivalent of `php -l` for this file type — `php -l` itself is N/A,
no PHP is involved):

```
parse: OK  lines=7936  bytes=485907  top-level functions=454
CRLF present: false
```

Full CRM Apps Script suite — **19/19 pass** (18 pre-existing, all still green, + 1 new). Baseline before
the edit was 18/18:

```
PASS  tests/3d-p-019-fixture-usage.test.mjs           PASS  tests/open-cart-identity-filter.test.mjs
PASS  tests/3dp-sync-journal.test.mjs                 PASS  tests/order-components.test.mjs
PASS  tests/catalog-sku-create.test.mjs               PASS  tests/order-items.test.mjs
PASS  tests/crm-004-packaging-validation.test.mjs     PASS  tests/purchase-batch-limit.test.mjs
PASS  tests/expected-stock-formula.test.mjs           PASS  tests/qualified-clients.test.mjs
PASS  tests/integrity-check.test.mjs                  PASS  tests/recent-purchases.test.mjs
PASS  tests/inventory-migration.test.mjs              PASS  tests/row-capacity.test.mjs
PASS  tests/monthly-profit-preorders.test.mjs         PASS  tests/sku-current-cost-menu.test.mjs   ← new
PASS  tests/mystery-box-cost-repair.test.mjs          PASS  tests/test-order-purge.test.mjs
PASS  tests/mystery-box-order-components-repair.test.mjs
suite_exit=0
```

3D-P Apps Script suite — untouched by this change, run as a regression check: **2/2 pass**
(`tests/api.test.mjs`, `tests/role-read-projections.test.mjs`).

### Negative control — the test reproduces the live failure

The new test was run against the **pre-fix** `Code.gs` taken from `git show HEAD` into a scratch
directory (a read-only git command; no git write, no working-tree change). It fails with the exact
live error:

```
Error: Cannot call SpreadsheetApp.getUi() from this context
    at Object.updateSkuCurrentCostMenu (Code.gs:4:16)
negative_control_exit=1
```

This is the same exception the owner hit at 08:04 Kyiv, now reproduced locally and closed by the fix.

### What the new test asserts

`crm/apps-script/tests/sku-current-cost-menu.test.mjs` extracts the real `updateSkuCurrentCostMenu` and
`onOpen` bodies from `Code.gs` and runs them in the existing `node:vm` harness with stubbed Apps Script
services (`tests/expected-stock-formula.test.mjs` is the reference shape):

1. **Editor context** (`getUi()` throws): completes without throwing, returns `{ updated: 32 }`, logs
   `Собівартість складу оновлено: 32 SKU.` via `Logger.log`, shows no alert.
2. **Spreadsheet menu context** (`getUi()` works): alerts `Собівартість складу оновлено: 5 SKU.` and the
   `Logger` fallback stays silent.
3. **`invalidateDoGetCache_` is called exactly once per run** — asserted in both contexts, plus a
   two-run check confirming two runs produce exactly two invalidations, never two within one run.
4. `SpreadsheetApp.flush()` runs once per run, before the cache invalidation.
5. The active spreadsheet is passed through to `updateSkuCurrentCost_`.
6. A source-level assertion that the alert carries the guard shape.
7. The full ordered `Booster CRM` menu list is compared element by element — the new item is present and
   all fifteen pre-existing items are unchanged and in their original order (acceptance criterion 4).

`updateSkuCurrentCost_` is **stubbed**, deliberately: its maths is out of scope and is proven live by the
32-SKU run.

## Idempotency

`already_applied=yes` is a PHP-runner convention and does not apply to a source edit. The equivalents:

- **Re-applying the edit is blocked, not silently repeated.** The edit script asserts each anchor matches
  exactly once; after the fix, anchor B no longer exists, so a second application fails loudly rather
  than double-inserting a menu item.
- **Re-running the function is safe.** Two consecutive runs against an unchanged workbook return the same
  `{ updated: N }` and invalidate the cache once each (test 3). `updateSkuCurrentCost_` recomputes
  `Склад!I:J` from scratch on every call and writes the same values for unchanged inputs.
- The byte-identity half of acceptance criterion 6 (`Склад!I:J` identical between two runs) **cannot be
  proven locally** — no agent has spreadsheet access. It is owner QA step 6.

## Rollback

No `_patch_backups/` directory is involved — nothing is written to the server.

- **Live:** Apps Script editor → project history → restore the version immediately preceding the paste →
  save → reload the spreadsheet. **No data rollback is needed**: this change writes no sheet data beyond
  what `updateSkuCurrentCost_` already wrote at 08:04, before the fix existed.
- **Repository:** revert the two `Code.gs` hunks above, delete
  `crm/apps-script/tests/sku-current-cost-menu.test.mjs`, and restore the `SOURCE_STATE.md` entry.
  The working tree is uncommitted, so `git checkout -- crm/apps-script/Code.gs` would **also discard the
  pre-existing parcel-completeness follow-up** — revert the two hunks by hand instead.
- If a Web App version were published in the same paste (not required here), republish the preceding one.

## Run command (owner)

**There is no `php` command for this task.** No patch file exists. Deployment is a source paste:

1. Resolve the section 9 question below **first**.
2. Extensions → Apps Script → paste the agreed source → save.
3. Reload the CRM spreadsheet (F5) so `onOpen` rebuilds the menu.
4. No Web App publication is required — menu items and editor runs execute the current bound source.

## Post-deploy QA checklist (owner)

- [ ] 1. Section 9 resolved and the paste scope agreed (see below).
- [ ] 2. Source pasted into the live bound script and saved.
- [ ] 3. CRM spreadsheet reloaded (F5).
- [ ] 4. `Booster CRM → Оновити собівартість складу` → alert `Собівартість складу оновлено: <N> SKU.`
- [ ] 5. Extensions → Apps Script → run `updateSkuCurrentCostMenu` from the editor → status `Завершено`,
      **no exception**, log shows `updateSkuCurrentCost_: updated <N> SKUs` and a message line with the
      same `<N>`.
- [ ] 6. `Склад`, row `PKM-EN-Q2-MTIN-SAL`, columns **I** and **J** — values present, and identical after
      both runs above. (This is the live half of the idempotency criterion.)
- [ ] 7. `Booster CRM` menu still lists every previous item, in order, plus the new one.
- [ ] 8. Owner dashboard, hard refresh — warehouse-cost figures match `Склад`. The first load after each
      manual recalculation is cold and slower; that is the cache invalidation working.
- [ ] 9. CRM risky zone: run the read-only dashboard `integrity_check` and paste its bounded output back
      for the record. This change edits no sheet structure and no formula column, so `OPS-CRMINTEGRITY`
      is **not strictly triggered** — the check is recorded as CRM-zone evidence, not as a gate this
      change created.

## Owner decision required before any paste — handoff section 9

**Option (b) applies.** Stated explicitly, before any paste instruction, as the round requires.

The live-vs-mirror question is **not** resolved. What exists is the 2026-08-20 ~08:10 Kyiv owner chat
paste, compared on **ten structural markers** — a spot check, explicitly recorded in `SOURCE_STATE.md`
as *"not a byte comparison"*. Ten matching markers in a 485 KB, 454-function file do not establish byte
identity, and `SOURCE_STATE.md` still carries an unresolved contradiction about what V131 contains. The
handoff names **(b) the safe default whenever there is doubt**, and there is doubt.

**Concretely:** do not paste the whole mirror over the live script. Export the current live bound source
to a file and hand it over; this executor rebases the two anchors above onto that exact export — a
two-anchor rebase, small and quick — and only then does the paste happen. If the export turns out to be
byte-identical to the mirror, that is the cheapest possible outcome and (a) becomes true by evidence
rather than by assumption.

**Smaller alternative, if a fresh export is inconvenient:** these two anchors are small enough to paste
by hand. Replace only the body of `updateSkuCurrentCostMenu` and add the one `.addItem` line in `onOpen`,
both shown verbatim in the bounded diff above. That touches nine lines of the live script instead of
7 936 and carries none of the whole-file-overwrite risk. It leaves the byte question open for next time,
so it is a workaround, not a resolution.

## Side effects / risks

- **Cold dashboard cache after each manual run.** `invalidateDoGetCache_()` bumps
  `CRM_DOGET_CACHE_VERSION`, so the first dashboard load after a recalculation is slower. Expected
  behaviour and the entire point of defect 3, not a defect.
- **Execution-time limit.** `updateSkuCurrentCost_` reads `Закупки`, `Продажі`, `Списання` and
  `Міграції_Складу` and writes `Склад!I:J` for every SKU. On a large workbook a menu run can approach the
  Apps Script limit. Pre-existing property of the underlying function; this change adds one `flush()` and
  one Properties write, negligible against that.
- **A now-reachable function is a behaviour change.** The menu item makes a full warehouse-cost
  recalculation available to anyone who can open the CRM spreadsheet. That is the requested outcome, but
  worth stating plainly: before this change the function was effectively unreachable.
- **Parallel-writer risk on `crm/apps-script/Code.gs`.** One executor holds both CRM-010 and CRM-011.
  They are separate work packages, pasted and QA'd in sequence, never as one blob.
- CRM is a risky zone per `AGENTS.md`. No referral to `bs-seo-risk-gate`, `bs-checkout-smoke` or
  `bs-merchant-schema-qa` is required: no SEO surface, no checkout/payment/fiscalisation path, no schema
  and no Merchant feed is touched.

## Not verified here

- Byte identity between the live bound source and `crm/apps-script/Code.gs` — see section 9 above.
- The live published Web App version as of 2026-08-20 (irrelevant to a menu-only change, but still
  unknown).
- Whether the daily `runNightlyInventoryMaintenance` 05:00 trigger is installed. No agent can read Apps
  Script triggers.
- Whether `PKM-EN-Q2-MTIN-SAL` holds remaining stock from a single lot. If it does not, `Склад!I:J` shows
  a FIFO-weighted average rather than the `LOT-0113` unit figures — a reading caveat for QA step 6, not a
  defect of this change.
- The live value of `<N>`. The 08:04 run reported 32 SKUs; the count depends on workbook state at run
  time and is not asserted to be 32 again.

## Status recommendation

`Ready for deploy` — the local suite passes and the deliverables are complete. `Done` only after the
owner completes the QA checklist and reports the `integrity_check` output (ROADMAP_SOP Definition of
Done).

Notion is **not** written by this executor. `CRM-010` is still a **provisional, unregistered** ID: no
Notion collision query was run and no page exists. Registration and any status write go through Claude
(chat) via `bs-roadmap-write` on explicit owner instruction, with the `ROADMAP_FLOW` dashboard mirror
updated in the same pass.
