# Main CRM Apps Script — repository mirror state

**This folder is a MIRROR of the live bound Apps Script project of the main Booster CRM
spreadsheet. It is evidence, not a deployment target.** Editing `Code.gs` here changes nothing on
the live system. Deployment is always: owner pastes into the live script editor and publishes a
new Web App version.

| Field | Value |
|---|---|
| Mirror file | `crm/apps-script/Code.gs` — refreshed from owner-supplied complete `Code.gs` text received 2026-08-23 16:16 Kyiv (supplied SHA-256 `2D8EE2F178EA7C58266D04F586FA45328600340DC2B0618454B14786CD3EAA08`). The source itself has no trustworthy version label, so no version is inferred from this refresh. The local mirror then received the scoped pending `sync_3dp_catalog_rrp` change. |
| Baseline pulled from live | 2026-08-23, 16:16 Kyiv (owner-pasted complete current bound source) |
| **Mirror content deployed to live** | The source was freshly supplied by the owner on 2026-08-23. This is source evidence only: the local pending `sync_3dp_catalog_rrp` change is not published. |
| Deployed Web App version number | Last historical record in this file is CRM V140 at 2026-08-21 11:03 Kyiv. The version corresponding to the 2026-08-23 supplied source was not labelled in the pasted code and is therefore unverified. |
| Live-verified after deploy | Post-V131 (2026-08-18 14:52) owner reported `QA - ok`; `integrity_check`: `clean:true`, `problems:[]`, checked `Товари`, `РРЦ`, `Розхідники`, `Майстер_Товарів`, `Налаштування`; `rrp_mismatch_3dp.compared:3`, `skipped_missing_crm_rrp:0`, `deferred:null`, `elapsed_ms:55853`. This proves the schema/formula relationships covered by `integrity_check`, not unrelated FIFO/warehouse flows or a byte match with the mirror. Post-V130 (2026-08-18 14:36) `integrity_check`: `clean:true`, `problems:[]`, checked `Товари`, `РРЦ`, `Розхідники`, `Майстер_Товарів`, `Налаштування`; `rrp_mismatch_3dp.compared:3`, `skipped_missing_crm_rrp:0`, `deferred:null`, `elapsed_ms:12184`. Owner reported dashboard QA done for the recent-purchases fix. Post-V129 (2026-08-17 22:41) `integrity_check`: `clean:true`, `problems:[]`, `rrp_mismatch_3dp.compared:3`, `skipped_missing_crm_rrp:0`, `deferred:null`, `elapsed_ms:9698`. This proves schema/formula relationships covered by `integrity_check`, not the expected-stock rule: a direct formula read immediately after still found the legacy `Склад!Q3:Q201` rule without `Замовлено`. FIFO migration flows are outside this check and remain unproven live. Post-V128 (18:36) `integrity_check`: `clean:true`, `problems:[]`, `rrp_mismatch_3dp.compared:3`, `skipped_missing_crm_rrp:0`, `deferred:null`, `elapsed_ms:12564`. Post-V118 (23:18) `integrity_check`: `clean:true`, `problems:[]`, `rrp_mismatch_3dp.compared:3`, `skipped_missing_crm_rrp:0`, `deferred:null`, `elapsed_ms:7672`. |

> ⚠ **2026-08-12, 16:45–16:46 — V106 migration succeeded; repeat exposed ARRAYFORMULA spill detection.**
> First setup: three schemas added, one fixture target backfilled, and two verified literal blockers
> cleared. Repeat: `expense_formula_blockers_cleared=52`, `already_applied=false`. A bounded live read
> of `Витрати!A3:M8`, `A34:M39`, and `A52:M57` proved L3/M3 still hold the intended ARRAYFORMULA
> anchors and all inspected lower L/M cells have effective values but no user-entered values. The
> local post-V106 correction treats a healthy spill as a no-op and temporarily removes the anchors
> only when a real `#REF!` requires blocker discovery. The full local regression suite passes.

> ✅ **2026-08-12, 16:55 — V107 setup is idempotent.**
> The owner-run result returned all schema/formula/backfill counters at zero and
> `already_applied=true`. The owner clarified that V107 had been published at 16:54, so dashboard
> API calls and the setup function now use the corrected source. The CRM deploy/setup gate is closed.
| Local syntax check | Node VM parse of `Code.gs` OK + CRM Apps Script suite 21/21 passed 2026-08-21 (MKT-TG-008). The deployed source has not been run through this new test. |
| Previous repo copy | `Booster Shop CRM - Apps_Script_код 29.07.2026.csv` (2026-07-29, pre-V87/V89) — superseded, keep for history only |

## Mirror status

> ✅ **2026-08-20 — LIVE↔MIRROR BYTE COMPARISON PASSED. The first one since V98; the "not byte-verified" caveat that has been carried in this file since then is now discharged.**
> Evidence: the owner exported the live bound `Код.gs` (Apps Script editor, select-all copy) as
> `Версія 133, 19 серп. 2026 р., 1224` — 493 567 bytes raw, CRLF, no trailing newline; md5 of the raw upload
> `1c0c247fcccd3268dd0642e02d6e4be9`. Normalised to LF it is **485 638 bytes, 7 930 lines, 454 top-level
> `function` declarations**. `diff` against the mirror (485 907 / 7 935 / 454) returns exactly three hunks and
> nothing else: the `onOpen` `.addItem` line, the `updateSkuCurrentCostMenu` body, and the trailing newline.
> All three are `CRM-010`, which is deliberately not live yet.
> **Therefore:** the live bound source equals the mirror minus `CRM-010`. The parcel-completeness follow-up,
> the inventory migrations, the catalog-option infrastructure, the 10-lot purchase editor, the `Виграно`
> expected-stock rule and the `OC-FOP-0320` repair are **all live**. There is no other undeployed work in the mirror.
> `CRM-010` section 9 option **(a)** is now proven rather than assumed: the owner may paste the whole mirror file.
> Scope of the proof: **source only**. It says nothing about which Web App version is published — bound-source
> edits do not publish. The last owner-reported publication remains V131 (2026-08-18 14:52); the live source is
> labelled V133 (2026-08-19 12:24).
> ⚠ **Trap in the repository root:** the file `Версія 133, 19 серп. 2026 р., 1224.csv`
> (422 717 bytes, md5 `dac28f9058efa80021fd97e7501d2f32`) is **mislabelled**. Its content is an older export —
> 382 functions, ~1 190 lines short of the mirror, missing the inventory migrations, catalog-option
> infrastructure, 10-lot purchase editor, `Виграно` rule and `OC-FOP-0320` repair. It is roughly V122, not V133.
> Do not use it for a byte comparison. Rename or remove it; the real V133 export is the one described above.

> ⚠ **2026-08-20 — CRM-011 local candidate: bounded FIFO drift diagnostic + one-order repair, not yet pasted or run live.**
> The owner reassigned CRM-011 to Codex after `LOT-0113` was corrected from 4 to 5 units. Bounded live reads found one matching sale: `OC-FOP-0324` / `PKM-EN-Q2-MTIN-SAL` at `Продажі!289`; it still has frozen `689.88 / 731.27` while `LOT-0113` and `Склад!I:J` now show `551.90 / 585.01`. The local candidate adds a read-only `diagnoseCrm011FifoCostDrift()` (capped at 50 rows) plus preview/apply wrappers that resolve the exact order+SKU at run time and write only `Продажі!L:M,AD:AF` when FIFO differs. 3D-P, Mystery Box, non-actual and invalid-quantity rows are rejected. The repair is idempotent: a matching repeat returns `rows_written=0` and does not invalidate cache. Local Node parse passed; CRM suite 20/20 passed. Live paste, preview, owner-named workbook copy, apply, read-back and `integrity_check` remain required.

> ✅ **2026-08-20 — CRM-010 DEPLOYED AND LIVE-VERIFIED as V134 (09:08 Kyiv). Owner QA passed.**
> Two anchors in `crm/apps-script/Code.gs`, both verified against the actual file before editing
> (the handoff line numbers were marked "likely, not confirmed" and both happened to be correct):
> `onOpen` gains `.addItem('Оновити собівартість складу', 'updateSkuCurrentCostMenu')` immediately after the
> expected-stock item (mirror line 21); `updateSkuCurrentCostMenu` (mirror 4682–4690) now captures the
> `updateSkuCurrentCost_(ss)` result, calls `SpreadsheetApp.flush()` then `invalidateDoGetCache_()`, builds
> `Собівартість складу оновлено: <N> SKU.`, guards the alert with the same
> `try { ... } catch (e) { Logger.log(message); }` shape as `createDailyInventoryMaintenanceTrigger`, and
> returns the result so the editor execution log shows it. `updateSkuCurrentCost_` itself is unchanged.
> Mirror after the edit: **7 935 lines, 485 907 bytes, 454 top-level `function` declarations**, LF preserved
> (was 7 930 / 485 639 / 454; +5 lines, +268 bytes — both figures counted with `grep -c ''`). New test `crm/apps-script/tests/sku-current-cost-menu.test.mjs`.
> Local evidence: Node VM parse of `Code.gs` OK; CRM suite 19/19 pass; 3D-P suite 2/2 pass; negative
> control — the new test run against the pre-fix `Code.gs` from `HEAD` throws the exact live error
> `Cannot call SpreadsheetApp.getUi() from this context`, reproducing the 08:04 Kyiv owner failure.
> **Live QA, owner-run 2026-08-20 09:15:59–09:16:03 Kyiv.** Spreadsheet menu: the new item
> `Booster CRM → Оновити собівартість складу` is present and works. Apps Script editor run of
> `updateSkuCurrentCostMenu`: `Виконання почато` → `updateSkuCurrentCost_: updated 32 SKUs` →
> `Собівартість складу оновлено: 32 SKU.` → `Виконання завершено`. No exception. The message reached
> `Logger.log` rather than an alert, which is exactly the guard behaving as designed in a UI-less context,
> and the SKU count matches the 08:04 pre-fix run. The 2026-08-20 08:04 defect is closed. Diagnostic: `diagnostics/CRM-010_sku-cost-menu-ui-guard_report_20260820.md`.
> Handoff: `handoffs/handoff_CRM-010_sku-cost-menu-ui-guard_20260820.md`.
> ⚠ **Live-vs-mirror question is still open** (`CRM-010` section 9). The 2026-08-20 owner paste was
> compared on ten structural markers only — no byte comparison exists. Option **(b)** — fresh owner
> export, rebase the two anchors onto it, then paste — is the safe default and is what this executor
> states applies. Do not paste the whole mirror over the live script until that is resolved.

> ✅ **2026-08-20 — CLOSED. Former live defect: `updateSkuCurrentCostMenu` could not be run by the owner at all.**
> **Fixed live in V134 (2026-08-20 09:08), owner QA passed — see the CRM-010 block above.** Original defect text follows for history.
> It calls `SpreadsheetApp.getUi().alert(...)` unguarded (mirror ~4681–4685) and has **no entry in
> `onOpen`** (~line 20), so its only invocation route is the Apps Script editor — exactly where that
> call throws `Cannot call SpreadsheetApp.getUi() from this context`. Owner run at 08:04 Kyiv:
> `updateSkuCurrentCost_: updated 32 SKUs` was logged (the final statement of `updateSkuCurrentCost_`),
> so the `Склад!I:J` write completed and only the alert failed. The function also never calls
> `invalidateDoGetCache_()`, so dashboard figures stay cached for up to 300 s after a manual run.
> Handoff: `handoffs/handoff_CRM-010_sku-cost-menu-ui-guard_20260820.md`.
> Sibling `updateExpectedStockFormulaMenu` carries the same unguarded pattern but has a working menu
> entry, so it does not fail in practice; deliberately left out of scope.

> ✅ **2026-08-20, ~08:10 Kyiv — owner supplied the current live bound source in chat; the parcel-completeness follow-up IS present live. Not a byte comparison.**
> Provenance: the owner pasted the script into the Cowork conversation as "актуальний скрипт" while
> reporting the `updateSkuCurrentCostMenu` failure. It was a chat paste, **not a file export**, so no
> byte-for-byte diff against `crm/apps-script/Code.gs` was possible or performed.
> Ten structural markers were compared and all ten are present in both the paste and the mirror:
> `include_all_open` (mirror line 2573), `apiRetry3dpOrderSync_` (last function in both),
> `apiUpdatePurchaseBatch10_` / `maximum 10 lots`, `repairOCFOP0320MysteryBoxCost`,
> `CRM_INVENTORY_MIGRATION_OUTLET_SKU_`, `CRM_PACKAGING_TYPES_`, `setup3dpOrderLineAccountingCRM`,
> `expectedStockFormula_` including `Виграно`, the `onOpen` menu item list, and the
> `updateSkuCurrentCostMenu` body. Mirror at the time of comparison: 7 930 lines, 485 639 bytes,
> 454 top-level `function` declarations.
> **What this proves:** the live bound *source* contains the parcel-completeness follow-up, so the
> "not deployed" warning immediately below is superseded as a statement about source.
> **What this does NOT prove:** byte identity between live and mirror, and it says nothing about the
> published **Web App version** — bound-source edits do not publish. Whether a Web App version was
> published carrying that follow-up is still unconfirmed; the last owner-reported version remains
> **V131 (2026-08-18 14:52)**.
> **Still required for byte verification:** a fresh owner file export of the live script.
> Note: `crm/apps-script/Code.gs` was still uncommitted in the local clone at this time.

> ⚠ **Local follow-up after V131 — all open purchases and complete tracked parcels; not deployed.**
> **SUPERSEDED 2026-08-20 as a source statement — see the block above.** The live bound source
> contains this follow-up. The publication status of a Web App version carrying it remains
> unconfirmed. Original text retained below for history.
> Live `Закупки` has six open rows with `LX328130128JP`: `LOT-0093` at row 88 and
> `LOT-0123` through `LOT-0127` at rows 114–118. It also has two older, untracked open lots omitted
> from the dashboard: `LOT-0097` / `yskh284` at row 92 and `LOT-0122` / `yskh289` at row 113.
> The current screen asks for only the newest twenty open rows. The local dashboard now explicitly
> requests `include_all_open=true`; the local helper returns every eligible open lot for that request.
> Its regular recent mode still retains complete non-empty tracked parcels. `crm/apps-script/tests/recent-purchases.test.mjs`
> covers both the six-lot parcel and an older untracked open lot. Owner paste, Web App publication,
> dashboard refresh, and QA remain required; V131 does **not** include this follow-up.

> ✅ **2026-08-18, 14:52 — owner-reported publication of CRM Web App Version 131; QA OK.**
> The owner reported `QA - ok` and supplied `integrity_check`: `ok=true`, `clean=true`,
> `problems=[]`, checked `Товари`, `РРЦ`, `Розхідники`, `Майстер_Товарів`, `Налаштування`;
> `rrp_mismatch_3dp.compared=3`, `skipped_missing_crm_rrp=0`, `deferred=null`,
> `elapsed_ms=55853`.
> This is live publication and integrity evidence. No fresh post-V131 Apps Script export was provided,
> so the exact source scope remains **not byte-verified** against `crm/apps-script/Code.gs`.

> ✅ **2026-08-18, 14:36 — owner-reported publication of CRM Web App Version 130; recent-purchases dashboard QA done.**
> The owner reported V130 and supplied an `integrity_check` result immediately after publication:
> `ok=true`, `clean=true`, `problems=[]`, checked `Товари`, `РРЦ`, `Розхідники`,
> `Майстер_Товарів`, `Налаштування`; `rrp_mismatch_3dp.compared=3`,
> `skipped_missing_crm_rrp=0`, `deferred=null`, `elapsed_ms=12184`.
> This is live publication and integrity evidence. No fresh post-V130 Apps Script export was provided,
> so the exact source scope remains **not byte-verified** against `crm/apps-script/Code.gs`.

> ⚠ **2026-08-17, 22:41 — owner-reported publication of CRM Web App Version 129; source scope is NOT byte-verified.**
> The owner reported the live bound script as "Версія 129, 17 серп. 2026 р., 22:41" and pasted an
> `integrity_check` run immediately after: `clean=true`, `problems=[]`,
> `rrp_mismatch_3dp.compared=3`, `skipped_missing_crm_rrp=0`, `deferred=null`,
> `elapsed_ms=9698`, checked sheets `Товари`, `РРЦ`, `Розхідники`, `Майстер_Товарів`,
> `Налаштування`.
> What this proves: a Web App version 129 exists and the schema/formula/relationship checks covered
> by `integrity_check` pass against the live sheets.
> What this does NOT prove: **which** local changes are in V129, or that the expected-stock formula
> was applied. A direct read after this report found `Склад!Q3` and `Q201` still use the legacy
> `Закупки!3:290` formula with only `В дорозі`, `На складі в Японії`, and `Виграно` — no `Замовлено`.
> Required next: refresh the CRM spreadsheet, use the public menu item `Booster CRM → Оновити
> очікуваний залишок`, then read back `Склад!Q` and the ordered-SKU totals. A fresh owner export is
> still required for byte comparison with `crm/apps-script/Code.gs`.

> ⚠ **2026-08-17, 14:30 — owner reported the initial CRM-CATALOG-OPTIONS candidate deployed; a local follow-up is not deployed.**
> The owner-pasted Apps Script export received at this time matched the pre-edit
> repository mirror after end-of-line normalization; no Web App version is
> inferred from that comparison. The owner then reported deployment; an execution
> screenshot shows the bound-sheet menu as `Head` and contemporaneous `doGet`
> requests as Web App Version 126. That does not prove a publication version for
> the bound-sheet menu. `Code.gs` now contains a local follow-up that replaces
> the menu's blocking completion dialog with a toast and bounds clean validation
> checks to twelve exact source probes. Owner review, paste, and publication
> remain required before that follow-up exists live.

> ✅ **2026-08-13, 23:18 — CRM V118 owner-reported publication (monthly-profit / preorder parity).**
> `integrity_check` immediately after: `clean=true`, `problems=[]`, three 3D-P RRP comparisons,
> `deferred=null`, `elapsed_ms=7672`. Note what this proves and what it does not: the check validates
> CRM schema, formulas and sheet relationships, so it cannot detect a KPI-parity defect at all — the
> two monthly-profit surfaces having different sale-status rules is exactly the class of problem it is
> blind to. Live parity proof is the owner comparing `Місяць на сьогодні` against the current bar of
> `Чистий прибуток — 6 міс` after a dashboard hard refresh.
> Root cause recorded in `diagnostics/CRM_monthly-profit-card-parity_investigation_report_20260813.md`:
> `apiSummary_()` read the prepared `Звіт_Продажів` row while `apiMonthlySummary_()` aggregated CRM
> rows through `isActualSaleForCost_()`, which rejects `Передзамовлення`. The live delta was 122.61 UAH
> — exactly two paid preorder rows dated 2026-08-07. The ~23 UAH delta in the owner's earlier
> screenshot is **not** explained by this and cannot be reconstructed from current sheet values.

> ✅ **2026-08-13, 22:48 — CRM V117 owner-reported publication (bulk RRP editing on `Товари`).**
> `integrity_check`: `clean=true`, `problems=[]`, `elapsed_ms=8523`. Owner QA OK.

> ⚠ **Superseded note, kept for history — local candidate after CRM V115.**
> `apiMonthlySummary_()` now returns cost-confirmed month-to-date totals for the dashboard card,
> and the active-order filter retains `Передзамовлення`. The canonical local dashboard uses the
> same month-to-date source as the profit graph and shows preorders only as a compact subline of
> the existing active-orders card. Local regression tests pass. This is not live evidence; the
> owner must publish a new CRM Web App version and refresh the dashboard before live QA.

> ✅ **2026-08-13, 20:16–20:17 — CRM V115 / 3D-P V23 owner-reported publication.**
> The universal dashboard test-order cleanup and one-off migration archive were published. The owner
> immediately ran `integrity_check`: `clean=true`, `problems=[]`, three 3D-P RRP comparisons,
> `deferred=null`, `elapsed_ms=5982`. This is schema/integrity evidence only; it does not prove a
> live dashboard cleanup run yet.

> ✅ **2026-08-13, 18:45–18:48 — CRM V114 / 3D-P V22 owner-reported deployment and MAN-FOP-0006 purge.**
> The order-update component catalog now excludes stale CRM stock for 3D SKU and merges active,
> positive-stock products directly from `3dp_skus`, using `Ціна під викуп` as management cost.
> Saving a selected 3D gift calls the new idempotent `3dp_order_gifts_append` action before the local
> component ledger: it validates stock, appends `Маркетингові_плюшки`, lets the existing availability
> formula decrement stock, and safely resumes after a repeated request. The dashboard also renders
> 3D Sales `Дата` as `YYYY-MM-DD` instead of displaying midnight. The MAN-FOP-0006 preview/apply/
> repeat and post-purge integrity check completed successfully. A disposable live 3D gift and
> dashboard stock/lot visual QA remain pending.

> ✅ **2026-08-13 — V112/V20 deployed; migrations succeeded.**
> The dashboard reads 3D stock from the bounded
> `3dp_skus` endpoint and labels unavailable data instead of substituting CRM stock; new sales use
> cent-balanced allocations and skip the full current-cost rebuild in the submit request. Components
> may be left order-level (Marketing distributed across the order) or linked to one exact sale row
> (fulfillment COGS for that row). The candidate also repairs frozen `% прибутку Сергію`, adds
> owner-only payout creation/payment actions, exact preview/apply cleanup for `MAN-FOP-0005`, and an
> exact allocation repair for `MAN-FOP-0006`. The canonical dashboard adds reliable 3D filters,
> Sales column visibility, Serhiy profit percent, stock-load status, and payout controls. All 12 local
> test files passed before deployment. Exact purge, profit-share backfill, and allocation repair then
> succeeded live and were idempotent. Post-change integrity and dashboard manual QA remain open.

> ⚠ **2026-08-12 — post-V110/V19 STALE_WRITE resilience candidate is not deployed.**
> The active 3D-P row is already healed: bounded live cells show CRM row 268 at
> `V=16.03`, `W=власник`, `X=Продаж`, `Y=16.03`, `Z=0`, `AA=20`. The local CRM V111
> candidate handles one `STALE_WRITE` on any frozen V:AA field by refreshing that exact remote sale
> row and retrying only the still-different field once. It retains honest per-field detail and never
> repeats component or fixture writers. It also collapses an exact repeated note before a later save,
> preventing a stale dashboard note from restoring the old repeated packaging text. Local regression
> coverage includes `V updated; W current; X current; Y refreshed and updated`.

> ⚠ **2026-08-12 — post-V109/V18 QA correction candidate is not deployed.**
> CRM target is **V110** and 3D-P target is **V19**. Bounded live reads proved that the successful
> `MAN-FOP-0005` update left two `ACC-002` component entries and five owner-paid
> `FUR-BR-COLOR-MIX` fixture units, while the intended state is one of each. The CRM candidate adds
> a dry-run-first, append-only compensating repair, exposes component management cost as Marketing
> without subtracting it from profit twice, stops repeated note concatenation, and preserves a stable
> request ID through timeout/reload retries. The 3D-P candidate repairs duration-formatted decimal
> hours and forces the two print-time columns to decimal number format. All 12 local tests pass; no
> candidate setup, repair, or deployment has been run live.

> ⚠ **2026-08-12 — post-V108/V17 candidate is not deployed.**
> CRM target is **V109** and 3D-P target is **V18**. The candidate fixes the live row-268
> `FORMULA_CELL` failure by allowing only the canonical prepared `Продажі!F` formula to be replaced
> during a controlled append; all other formula cells remain protected. It adds a dashboard
> `Виготовити партію` path backed by append-only `Друк-лог`, a 3D-P-only retry that never repeats
> component/fixture writers, and a full consumable/fixture purchase/status workflow over the existing
> `Витрати` and `Розхідники` model. An exact preview/apply arrival repair covers the 16 verified live
> rows named by the owner. All 12 local CRM/3D-P/dashboard tests pass. No live write or deployment has
> been performed from this mirror.

> ⚠ **2026-08-12 — post-V107/V16 stabilization candidate is not deployed.**
> CRM candidate target is **V108**; 3D-P candidate target is **V17**. The dashboard is the canonical
> local file and is not published separately. Live bounded reads proved that Mystery Box rows for
> `OC-FOP-0309`, `OC-FOP-0312`, and `OLX-FOP-0050` retain only auto-consumable cost while their linked
> writeoffs remain intact. The candidate adds a dry-run plus idempotent repair for exactly those orders,
> prevents later order edits from replacing linked-writeoff cost with the zero-stock FIFO fallback,
> allows order-level non-fixture components, retries only an unexecuted HTTP 404 once, and serves the
> new qualified-clients dataset. The 3D-P candidate reduces a batch-draft read from five cell calls to
> one bounded range call. Local CRM/3D/dashboard regression suites pass; owner deployment, repair run,
> live integrity check, and manual QA remain pending.

> ⚠ **2026-08-12 — local post-V105/V13 candidate is not deployed.**
> The CRM mirror now additionally contains per-order-line 3D Sale/Marketing accounting, multiple
> line-targeted fixtures, line-targeted fulfillment components, a dedicated append-only accounting
> ledger, a Marketing projection/Orders column, and duplicate-submit protection. The 3D-P mirror adds
> the matching frozen X:AA schema and formulas. These changes require new deployments plus
> `setup3dpOrderLineAccounting()` on 3D-P and `setup3dpOrderLineAccountingCRM()` on CRM before live use.

> ⚠ **2026-08-12 — deployment reported, byte identity not re-exported.**
> The owner reported 3D-P Web App **V11** at 09:45 and CRM Web App **V103** at 09:46, then completed
> the Phase-A/B/C setup checks. This establishes runtime deployment evidence. It does **not** make the
> local files independently byte-identical to the published scripts; request a fresh redacted export before
> a later source-sensitive change. The post-deploy integrity check is clean, but 3D-P RRP coverage is
> deferred (`3D-P API is unavailable: JSON`), and the owner observed intermittent dashboard HTTP 404s.

> ✅ **2026-08-12, 09:47–09:49 — V103 `3D-P-019` setup evidence, owner-reported.**
> Phase A returned `already_applied=true`; Phase B migrated `fixture_rows_migrated=2`, enforced payer
> validation, and installed the three listed forms; frozen-history preview was an idempotent no-op. The
> bounded CRM integrity result was `clean=true`, `problems=[]`, with the remote 3D-P RRP comparison
> deferred rather than compared. This proves schema/setup state, not a new sale through the CRM dashboard.

> ✅ **2026-08-11, 09:55 — V102 owner-reported Phase-A execution from the reviewed local mirror.**
> `setup3dp019FixturePayerPhaseA()` completed with `header_added=true`, `category_rows_renamed=2`,
> `payer_rows_backfilled=2`, and `already_applied=false`. The owner reported a clean post-change
> `integrity_check`: no problems, one 3D-P RRP comparison, and `elapsed_ms=5938`. This proves the
> live category/payer migration only; fixture consumption, forms, and Serhiy purchase import remain
> outside Phase A.

> ✅ **2026-08-10, 22:06 — V101 owner-reported deployment from the CRM-006-5 local mirror.**
> The source restores the confirmed live formulas and exempts only 11 documented manual historical
> `Розхідники → Використано в продажах` values. The local integrity suite passes; final live
> `integrity_check` evidence is pending.

> ✅ **2026-08-10, 19:59 — V99 owner-deployed from the exact local mirror and runtime-proven.** Its `integrity_check`
> response no longer reports `Товари → Коротка назва` rows `52-60, 71-76`; it retains the separate
> `Товари → Поточна ціна продажу` rows `38-39` and all three existing `Розхідники` findings. This
> proves the intended live behavior without hiding unrelated defects. The owner pasted this exact
> local `Code.gs`, so the mirror-to-V99 identity is established by direct deployment provenance;
> no redundant post-deploy export is requested.

> ✅ **2026-08-09, evening — CRM mirror re-verified against the V98 owner export. No local pending
> changes on the CRM side.** `diff` after CRLF normalisation is empty. V98 adds the read-only
> `order_items` action (`CRM-006-ORDER`); runtime proof is the owner expanding `OC-FOP-0312` on the
> live dashboard and its three lines totalling ₴7 400 / ₴3 840, matching the collapsed row exactly.
>
> ✅ **2026-08-09, 14:52 — CRM mirror re-verified against the V97 owner export. No local pending
> changes on the CRM side.** `diff` after CRLF normalisation is empty; 4515 lines on both sides.
>
> Confirmed present in the exported live source, not inferred: `apiIntegrityCheck_`,
> `CRM_INTEGRITY_3DP_SKU_RE_`, `elapsed_ms`, and `report.clean = report.problems.length === 0` —
> i.e. `CRM-005` **including** the `ok`/`clean` collision fix is genuinely live in V97.
>
> Runtime corroboration, not inference: the owner's 14:45 live run returned a populated problem list
> with `elapsed_ms` 5750 instead of the pre-fix `API error`. Baseline recorded in
> `diagnostics/CRM-005_first-live-baseline_20260809.md`.

### Superseded note (kept for history)

**Local pending changes: 3D-P-010 WP4 and 3D-P-023.** The V92 mirror remains the high-confidence live baseline.
This local `Code.gs` adds one call from `updateSaleStatus()` to the existing
`sync3dpPackagingCost_(sales, order, rows, 'updateSaleStatus')` wrapper after cache invalidation
and before the form is cleared. It also formats existing date-valued journal timestamps as Kyiv text
in `apiSyncJournal_`, without changing the underlying Sheet cell type. It is not deployment proof:
after the owner publishes a new CRM Web App version, export `Code.gs` again and record that version above.

Timeline that ties the prior baseline to the deployment: **V92 published 15:23**, first QA journal
entry **15:47** — so every 2026-08-08 QA result in `3D-P-014` and `3D-P-022` was produced by V92.

One caveat before the next task treats the replacement deployment as proof: byte-identity will need
a post-deploy export. The V92 identity itself was inferred from the owner pasting the prior file
wholesale, so it is high-confidence rather than independently proven.

## Companion mirror — 3D-P Apps Script

| Field | Value |
|---|---|
| Mirror file | `3d-print/apps-script-3dp-api/Code.gs` |
| Last verified byte-identical to live | **2026-08-09** — owner export identical apart from CRLF |
| Deployed Web App version | **V20, owner-reported published 2026-08-13 15:42 Kyiv.** V20 includes the stock/components/payouts/test-cleanup release. V10 remains the last independently exported/byte-compared 3D-P source. |

> ✅ **2026-08-12, 18:07–18:09 — V17/CRM V108 repair gate closed.**
> The owner published both Web Apps, ran the exact Mystery Box preview/apply/repeat, and obtained
> a clean bounded integrity result. This establishes runtime evidence for the prior candidate; it does
> not deploy the newer manufacturing, purchase-lifecycle, or FORMULA_CELL correction in this mirror.

> ✅ **2026-08-12, 16:38 — V16 order-line accounting setup is live and idempotent.**
> The owner-run control result was `ok=true`, `already_applied=true`, `columns_added=0`, and an empty
> changes list. This closes the 3D-P schema/migration gate. It does not yet prove the pending CRM
> deployment, CRM setup, or an end-to-end dashboard order update.

> ⚠ **2026-08-12, 16:31–16:32 — V15 migration succeeded; repeat exposed a formula-comparison defect.**
> Preview reported Sales already at 27 columns. The first setup added X:AA and changed Availability
> E:F successfully. The repeat changed only E:F and returned `already_applied=false`. A bounded live
> read proved Google persisted the same formulas with automatically quoted sheet names (`'Продажі'`
> and `'Маркетингові_плюшки'`). The local post-V15 correction treats quoted and unquoted sheet names
> as equivalent. Data and stock formulas are valid; only the idempotency detector was wrong.

> ⚠ **2026-08-12, 16:26 — V14 setup stopped; corrective 3D-P candidate is not deployed.**
> `setup3dpOrderLineAccounting()` failed at the first new-header write with “target range coordinates
> are outside the sheet dimensions.” Root cause: the live Sales sheet grid ends before AA. The local
> correction expands the grid through AA before taking/writing the migration snapshot and removes
> the added columns again if a later setup step fails. A regression test now starts Sales at 26 columns
> and proves expansion to 27 plus idempotent repeat. CRM V105 and its setup were not touched.

> ✅ **2026-08-09 — both mirrors re-verified against fresh owner exports. Byte identity restored.**
> `diff` against the owner's exports (CRM V95, 3D-P V10) is empty apart from CRLF line endings, so
> both mirrors are trustworthy again.
>
> Confirmed present in the exported live source, not inferred:
>
> - 3D-P V10 contains `onEdit`, `3dp_setup_3dp024` and the `Налаштування!$B$5` defect-rate
>   reference — i.e. `3D-P-015`, both fixes and `3D-P-024` are all live;
> - CRM V95 contains `skipped_sku_not_in_nomenclature` and `CRM_3DP_SALES_FROZEN_HEADERS_` — i.e.
>   the frozen `T:W` schema gate and the FIX1 journal outcome are live.
>
> Corroborating runtime evidence: `diagnostics/3D-P-015_live-migration_20260808_205617.json`, and
> the owner's live confirmation that `1:39` normalises to `1.65`.
>
> Note the export timestamps: CRM V95 is 19:31 and 3D-P V10 is 21:53 on 2026-08-08. `3D-P-024`
> landed only on the 3D-P side, which is why the CRM export is the earlier of the two and is still
> current.

The 3D-P script was unchanged between V7 and 2026-08-08; `3D-P-014` and `3D-P-022` landed entirely
on the CRM side. Everything after that is the `3D-P-015` / `3D-P-024` family described above.

## Rule (OPS-CODEMIRROR)

1. Any task that reads, plans against, or patches either Apps Script project **checks the pull
   date in this file first**. If the mirror is older than the change being planned, ask the owner
   for a fresh export before writing a handoff.
2. Whoever changes a live script is responsible for refreshing the mirror **in the same session**,
   together with the pull date and the deployed version above.
3. Never assume a deployed version number from source alone. Source and deployment are separate:
   editing the script does not update the published Web App.
4. The mirror must never contain tokens. Both projects keep secrets in Script Properties only;
   if a token ever appears in an export, stop and tell the owner instead of committing it.

## Companion mirror

The 3D-P project mirror is `3d-print/apps-script-3dp-api/Code.gs`. Verified **2026-08-08**: the
owner's live export is byte-identical to the repository copy apart from CRLF line endings, so the
3D-P side is in sync and the corrected 3D-P-010 source is genuinely live.

## Verified anchors in this pull (2026-08-08)

Facts read directly from the mirrored source, replacing earlier inference:

- `sync3dpSales_` exists and is reached only through the compatibility wrapper
  `sync3dpPackagingCost_`, which is called from exactly **two** places: `apiAddSale_` and
  `apiUpdateSale_`. Both are Web App (`doPost`) paths.
- `updateSaleStatus()` (and its alias `updatePaymentStatus()`) contains **no 3D-P call of any
  kind**. Finding 9 of `diagnostics/3D-P_live-schema-audit_20260803.md` is now proven from source
  rather than inferred from execution logs.
- Product sale costing is **FIFO over `Закупки` lots**: `getFifoCostBatches_` sorts batches
  ascending by delivery date (`batches.sort((a, b) => a.sort - b.sort || a.row - b.row)`), so the
  **oldest** lot is consumed first.
- Consumables (`Розхідники`) work differently and have **no lot model at all**:
  `getAutoConsumableInfo_` reads a single row per consumable — name, unit cost, initial qty, plus
  replenishment qty — and returns one current `unitCost`. There is no price history and no batch
  selection for consumables.
- `Списання` (write-offs) already exists with per-order consumable attribution via
  `getAutoConsumableUnitCost_`.
