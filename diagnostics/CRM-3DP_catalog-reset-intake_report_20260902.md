# CRM / 3D catalogue reset — source and contract audit

Date: 2026-09-02
Executor: Codex. Scope: owner-authorized preparation, read-only audit, and import planning.

## 2026-09-04 refresh after CRM V164

- Latest commit reviewed: `55e93766659aeb1b422e9c871bcd20157b058f55`,
  `CRM-FORMULA-COVERAGE-001 repair ranges and cache`.
- The supplied V164 export and repository `crm/apps-script/Code.gs` are exactly
  equal after normalization: 9,616 lines, SHA-256
  `2cc7bb7fcfd6c69bfff20d41378e11e33643871a4f5390483eefb3f9f9361344`.
- The commit expands formula references dynamically and repairs dashboard cache
  invalidation. It does not add, delete, or modify the 3D catalogue records,
  FIFO ledgers, sale sync, or the 3D-P workbook contract.
- Bounded current CRM reads confirm live formula coverage through Продажі 752,
  Закупки 309, Списання 636, Витрати 218 and Товари/Склад 220. The narrow
  dashboard cards also reference the expanded 309/220 bounds.
- Current live cleanup targets are unchanged: seven CRM product rows (70, 75,
  82, 93, 94, 95, 97) and four BR-CHARM-100 component rows (3, 4, 47, 60).
  The component read omitted parent order identifiers and customer data.
- Relevant V164 tests pass: row capacity, monthly/preorder cache, current-cost
  menu, temporary preflight syntax/read-only guards, and the 3D contract probe.
- The import/reset implementation must use the current dynamic capacity helpers
  and preserve the repaired formula ranges. No old fixed row-201/290/433 bounds
  may be restored or copied into new formulas.
- The available Serhiy credential still cannot inspect Аналітика below row 17;
  the current API returns `RANGE_NOT_ALLOWED`. Owner-run preflight remains the
  gate for that range, internal journals and the exact live `integrity_check`.

## 2026-09-04 owner preflight — gate closed

The owner ran the temporary read-only wrapper in both bound Apps Script projects.
Both logs are complete and internally numbered: 3DP 51/51 and CRM 40/40, with
the expected project/spreadsheet IDs, `ok=true`, no wrapper errors and matching
`CATALOG_PREFLIGHT_DONE` markers. Parsed evidence is stored locally as:

- `work/3dp-catalog-reset-20260902/3dp-preflight-20260904.json`
- `work/3dp-catalog-reset-20260902/crm-preflight-20260904.json`

The CRM `integrity_check` is clean with `problems=[]`; 3D RRP coverage compared
all seven current CRM 3D SKUs, skipped none, and was not deferred. This is the
required pre-change baseline. Formula contracts match the V164 bounded live
reads, including formula-backed Товари B/J, РРЦ A:D/H and Розхідники C/F:J.

The exact current test footprint is now complete:

| Workbook area | Populated business keys | 3D/test-key rows | Treatment |
|---|---:|---:|---|
| 3DP Номенклатура | 8 | 8 | Reset/reconcile against the 72-row manifest |
| 3DP Друк-лог | 12 | 12 | Clear test business effects after backup |
| 3DP Продажі | 0 | 0 | Preserve formula scaffolding |
| 3DP Маркетингові_плюшки | 4 | 4 | Clear test business effects after backup |
| 3DP Виплати | 1 | 0 | Clear the test period after backup |
| 3DP Наявність | 6 | 6 | Rebuild formula-only projections for all 72 SKUs |
| 3DP _Чернетки_партій | 9 | 6 recognized by canonical prefix | Clear all current draft business rows; all current 3D work is owner-declared test |
| 3DP _Коригування_наявності | 6 | 6 | Clear test stock effects after backup |
| 3DP _Журнал_налаштувань_3DP | 13 keyed rows | 13 | Preserve as audit/history; do not use as stock or import input |
| 3DP _Аудит_API | 129 | n/a | Preserve as immutable provenance |
| CRM Товари | 102 total | 7 at rows 70,75,82,93,94,95,97 | Reconcile in place where SKU remains; preserve formula columns |
| CRM Використання_компонентів | 65 total | 4 at rows 3,4,47,60 | Remove only test 3D components, then recompute affected parent-line cost |
| CRM Продажі / Закупки / Списання / 3D_облік_замовлень | existing non-3D rows | 0 direct 3D rows | Preserve |

No unrecognized hidden 3DP sheets were found. The payout acknowledgement sheet
does not yet exist. Audit history is deliberately not business stock and remains.

`Аналітика!A18:N100` is not available for calculator growth: row 67 is the last
used row, 47 rows in 18:67 are occupied, and eleven merged ranges overlap that
area. The captured snapshot hash is
`73f7c92490d58668c718f66511d9652b2deba2bc4ba89c548d9375622c63cc42`.
Therefore the 59-row active-SKU calculator must move to a dedicated sheet; an
in-place extension below row 17 is rejected because it would overwrite content.

The temporary preflight source and its local test/instructions were deleted from
the repository workspace after the evidence was accepted. The owner should also
delete the temporary `CatalogResetPreflight.gs` files from both live Apps Script
projects if that step was not already completed. No Web App publication was
needed for the read-only execution.

## Outcome and current gate

The local implementation candidate is complete and **ready for owner review and
owner-run preview**. It is not deployed and no live mutation has run. The source contains 74 products;
after the owner's two exclusions, import 72 products: 59 active and 13 inactive.
Nami L has an explicit owner override of RRP 750 / buyout 500 UAH. There are no
remaining active-product price questions. Actual manufactured batch FIFO is the
approved cost basis; source single/batch costs remain planning estimates.
The candidate moves active-SKU analytics to `Аналітика_SKU`, provisions all
availability formulas, coordinates activity state between CRM and 3D-P, and
introduces immutable manufactured-batch FIFO. Serhiy consumables are frozen once
inside the batch cost. CRM sale sync atomically consumes the oldest costed layers
and freezes the returned unit cost in both accounting systems.

No live cells, product statuses, Notion properties, or deployments were changed.
No backup has been created yet. No cleanup or import has run. The one-time apply
wrappers create Drive copies before their first write and restore local snapshots
if their verification fails.

Review artifacts:

- `plans/3dp-catalog-reset-20260902/import-manifest.json`: 72 import records,
  original A:Q values, exact source coordinates, per-record fingerprints, both
  cost scenarios, active mapping, proposed SKU keys, dimensions, model links,
  test-print and Mystery Box flags, two explicit exclusions and the owner price
  override. `apply_ready=false` is deliberate.
- `plans/3dp-catalog-reset-20260902/import-review.md`: owner-reviewable row table.
- `plans/3dp-catalog-reset-20260902/fifo-contract.md`: grounded FIFO design,
  integration defects, precision/retry requirements and acceptance examples.
- `scripts/3dp-catalog-reset/CatalogResetPreflight.gs`: complete temporary
  read-only owner-run diagnostic, with Ukrainian instructions and local tests.
- `work/3dp-catalog-reset-20260902/`: bounded read evidence and contract probe.
  Some CRM evidence contains internal record identifiers; do not publish or stage
  that folder with implementation files.

## Owner decisions preserved

1. All existing 3D products and related business records in CRM and 3D-P are tests.
2. Import all products from the five product tabs. Do not import the consumables tab.
3. Source `Так` in the can-print column means active; `Ні`, `ні`, and `Не бажано`
   mean inactive. Inactive products must remain available to the existing owner
   activate/restore control. They are canonical products, not unassigned drafts.
4. Source batch capacity is not inventory. Opening stock is zero after test cleanup.
5. Sale: production cost plus 50% of net profit goes to Serhiy. Marketing:
   the agreed buyout price goes to Serhiy; no profit-sharing calculation applies.
6. Serhiy purchases his consumables and includes them once in production cost.
   Owner purchases continue through the existing CRM consumable ledger.
7. Order: contract audit, concrete cleanup/import preview, backup, apply, verification.
8. Exclude draft Брелоки rows 3 and 13: the ChBS and One Piece three-piece sets.
   Nami L / FIG-NAMI-201 uses RRP 750 and buyout 500 UAH; original source cells
   remain unchanged in the captured evidence.
9. Operational cost is FIFO from actual manufactured batches. D/N estimates do
   not determine stock cost. A sale spanning batches uses their aggregate cost.

## Current source proof

The fresh owner exports match the mirrors after UTF-8 BOM removal and CRLF/LF
normalization. The credential-literal scan reported no candidate secret lines.

| Source | Lines | Normalized SHA-256 |
|---|---:|---|
| CRM V164 baseline export | 9616 | `2cc7bb7fcfd6c69bfff20d41378e11e33643871a4f5390483eefb3f9f9361344` |
| 3D V31 export and `3d-print/apps-script-3dp-api/Code.gs` | 3748 | `fdf22bfb2b6c659f6e727be85447049826cb5355278c4adc61cb417c633bedf9` |

This is source identity, not independent proof of published deployment versions.
Authenticated 3D API reads succeeded using the configured Serhiy identity.
Owner 3D and CRM API credentials were absent from process and user environment.

Both dashboard working trees contain pre-existing changes. They were read, not
modified. Future fixes must preserve these changes and use their current content
as the comparison baseline; do not restore them from HEAD.

## Source catalogue

Source spreadsheet: `1gQLHxS-EGxIOwX3k8UhU-1HFRzpgFrlDTZaSelp4Tu4`.
The linked gid is one of five product tabs, not the whole import scope.

| Tab | Products | Active | Inactive |
|---|---:|---:|---:|
| Брелоки (after two owner exclusions) | 17 | 14 | 3 |
| Підставки для карток | 14 | 9 | 5 |
| Фігурки | 21 | 21 | 0 |
| Пластини | 7 | 6 | 1 |
| Аксесуари шо можна юзать | 13 | 9 | 4 |
| Total imported | 72 | 59 | 13 |

Reads used bounded A:AC source rectangles ending at rows 70/40/60/30/40;
returned populated data ended at rows 21/16/23/9/15. No full workbook export.

The manifest has 52 keys found in the canonical naming document and 20 new key
proposals. Neither category is an external-catalogue collision check. Existing
documented variants are preserved, including `FIG-PKBL-600`, `BR-DITTO-400`,
`ACC-3D-PKM-700/710/711/712`, and the `PKBL-400/401` bowls. `FIG-ZORO-410`
follows the explicit assigned-SKU table and owner-confirmed product references;
the same naming file also has an inconsistent `FIG-ZORO-400` physical-data row.
Resolve this source discrepancy in the naming record before publication.

Source names are retained for matching; this intake does not rename website cards.
Material, color, and missing dimensions are not inferred. The Gengar dimensions
`208:240*2` remain unchanged with a review flag. Nintendo-case dimensions remain
the original unresolved text. Time serials are multiplied by 24 to obtain hours.

### Resolved questions and remaining source blanks

The owner excluded the One Piece set and ChBS set, supplied Nami L prices,
and selected actual manufactured batch FIFO. No choice between D and N remains:
both are planning inputs, not an opening valuation. The source has no spool
price/weight assumptions; inventing those to recreate a desired K is unacceptable.

Six inactive products lack RRP, and six lack buyout. They must be imported
with genuinely missing prices and prevented from sale/marketing until completed,
not assigned artificial zero or placeholder values.

## Concrete cleanup inventory

CRM spreadsheet: `1PvlSlg3UoPw8Fbj98lHL-VGLB0HP8hgKUxsXPW1GkRg`.
3D spreadsheet: `1yp15H3YJGkqI4Rx89G4QZHkD9m67gnWh58TsTTi-jjo`.

| Target | Observed test records | Planned treatment |
|---|---|---|
| CRM Товари | rows 70, 75, 82, 93, 94, 95, 97; seven 3D SKU keys | Clear only these products' manual inputs, keeping formula scaffolding; rebuild their keyed RRP values as part of import |
| CRM РРЦ | formula projections of those product rows | Preserve A:D and H; clear only corresponding manual E:G during reset |
| CRM Склад / automation master | derived catalogue projections | Recompute from source; never replace derived cells with manual product copies |
| CRM Використання_компонентів | rows 3, 4, 47, 60; one BR-CHARM-100 gift each | Remove only these component records and their projections; reallocate parent-order component cost |
| 3D Номенклатура | rows 2:9; eight products | Reset business inputs and recreate imported products with formula-safe scaffolding |
| 3D Друк-лог | rows 2:13; 12 records including archived | Remove test effect from totals; retain an external rollback snapshot and an audit record |
| 3D Маркетингові_плюшки | rows 3:6; four records | Remove with corresponding CRM component projections |
| 3D Виплати | row 2; one period | Reset test period inputs and acknowledgements, preserving formulas |
| 3D stock adjustment ledger | visible records at rows 2, 3, 6, 7, 8, 9 | Reset test stock effects through an approved audited migration |
| 3D Наявність | six existing formula rows at 2:7 | Preserve/rebuild formula definitions for all imported products; do not write stock literals |
| 3D Аналітика | eight populated calculator records at 4:11 | Rebuild only after capacity migration and preserve required 50% shares |

The four CRM gift components have total management cost 80 UAH (20 each), with
zero PRRO component cost. Their parent orders can contain real non-3D items.
The existing `test_order_cleanup` API deletes whole selected orders and is
therefore unsuitable for this scope. Preserve every parent sale row and its
non-3D components; use exact component IDs plus fresh snapshots for deletion.

Bounded key-column reads found no direct 3D rows in CRM sales, purchases,
write-offs, 3D order accounting, warehouse migrations, or fixture target SKU
columns. The 3D sales API returned zero rows. This is not proof that hidden
draft/acknowledgement/audit journals contain no records. The final preflight must
enumerate relevant internal journals through an authorized API extension before
promising a complete reset. Keep `_Аудит_API` as provenance, not business stock.

## Contract findings requiring correction

| Priority | Finding and evidence | Required correction |
|---|---|---|
| P1 | `ANALYTICS_CALCULATOR_3DP` has A4:N17, 14 rows. `syncActiveNomenclatureAnalytics3dp_` raises `ANALYTICS_CAPACITY_EXCEEDED` when more are active. The batch needs 59. | Migrate the bounded analytics table safely, including snapshots, share lookup, read projections, and consumers. Inspect content below row 17 first; merely increasing a constant risks overwriting other analytics content. |
| P1 | Live `Наявність!A8:G15` has neither values nor formulas. `FIG-LUFFY-410` and `FIG-123-500` are active but have no availability row. Owner quick-create initializes K and Analytics, not availability. | Provision availability formulas with every canonical SKU, including later activation. Verify all 72 SKU keys appear exactly once. |
| P1 | `toggleThreeDpArchive` calls only 3D archive/restore. The API changes O/P only. CRM `Товари!L` is unaffected; `threeDpCrmPayload` hardcodes `active:true`. | Make the existing owner control coordinate both statuses with stale-write checks, recovery, and visible partial-failure handling. |
| P1 | Live K formulas omit N. Both calculators exclude fixture reference cost. N is explicitly `Фурнітура (ціна-довідка)`. | Add an explicit Serhiy consumable-cost input and include it once in production cost. Do not repurpose a reference-only field silently or double-add it through the CRM Serhiy-payer ledger. |
| P1 | `normalizeNomenclatureOwnerCreateValues3dp_` requires Q>0 and always creates active products; CRM `apiAddSku_` also requires RRP>0. | Support inactive canonical import with missing prices; enforce positive necessary prices when activating or attempting sale/marketing. |
| P1 | Actual manufactured batch costs are not frozen: live Друк-лог G2:G15 uses quantity × current nomenclature K. CRM reads K for each sync; a replay fixture changes CRM unit cost from the remote sale's 86.6667 to current K=120. | Implement immutable batch valuation and FIFO allocations, atomic per-project stock events, replay/reversal rules and matching CRM/3D accounting. Preserve existing formula columns; see fifo-contract.md. |
| P2 | Source D/N estimates have no explicit structured planning destination; K is protected and formula-derived. | Preserve both source estimates as planning metadata. They must not seed stock, manufactured lots or frozen sale costs. |
| P2 | Nomenclature has no structured dimension, source-printability, or Mystery Box eligibility field. Current CRM gift catalogue lists any active stocked 3D item. | Preserve characteristics in explicit metadata and connect fields to the intended UI/filter behavior; do not imply that copying prose to notes implements eligibility. |
| P2 | API and Serhiy draft catalogue contain 17 detailed categories and omit the approved `ACC-3D-8__` case/container category. | Add the category coherently in API and both selectors while writing broad type `Функціональний аксесуар` to D. |

The active-status source is technical O (`Активний`/`Архів`/`Чернетка`), not
legacy business status F. The four broad D types must remain separate from the
detailed mechanics/categories. Current live 3D validation metadata is not exposed
by the existing bounded API; actual validation checks remain an explicit gate.

## Owner consumables and the two payout scenarios

Observed CRM fixtures: `FUR-BR-COLOR-MIX` and `FUR-BR-CARB`, both payer `власник`,
in `Розхідники!22:23`. Their stock formula subtracts `Використання_фурнітури`
using both name and payer. These catalogue rows and purchases are not 3D product
rows and must be preserved. No consumables were imported from the draft workbook.

The current order-line path resolves fixture identity by name+payer, ties usage
to a specific 3D sale line, freezes its unit cost, and includes owner fixture cost
in the margin calculation. A synthetic source-code check passes:

- Sale revenue 200, production cost 80, owner fixtures 10, packaging 10:
  Serhiy accrual = 80 + 50% * (200 - 80 - 10 - 10) = 130.
- Marketing buyout 100, owner fixtures 10: Serhiy accrual = 100,
  management cost = 110. Profit sharing is not used.
- If Serhiy's consumables are already in the 80 production cost, the separate
  Serhiy fixture amount must be zero; otherwise reimbursement is duplicated.

Additional purchase-path gap: the two live fixture arrival formulas stop at
`Витрати!199`, and their unit costs C22/C23 are literals. For existing catalogue
items, `apiAddConsumablePurchase_` updates incoming G formulas but does not refresh
arrival F or price C; `apiUpdateConsumablePurchase_` only changes the expense
status. There are no consumable purchases in inspected rows 200:218 today, so
this is a reproducible future-boundary risk, not evidence of currently lost
receipts. The owner-purchase path needs a focused receipt/price test across that
boundary before the linked system is declared correct.

## Validation performed

- Fresh V164/V31 exports equal the baselines used for the candidate.
- Owner preflight passed 3DP 51/51 and CRM 40/40; CRM integrity was clean.
- Apps Script syntax passes for both complete source files, `CatalogFifo.gs`, and
  both generated one-time migration wrappers.
- FIFO unit tests pass: oldest-layer allocation, mixed-layer aggregate cost,
  insufficient-stock refusal, immutable actual batch cost, defects, and single
  inclusion of Serhiy consumables.
- CRM contract tests pass for atomic sale commit, removal of the legacy double
  stock decrement, activity sync, mystery eligibility, formula integrity, and
  3D component accounting.
- Serhiy local-server suite passes 16/16, including five calculator/settings
  cases, category parity, UI operation state, API error handling and two local
  integration cases.
- Draft category/API parity passes with the approved `ACC-3D-8__` category.
- The full historical 3D role regression passes against the recovered immutable
  V29 baseline fixture, including owner and Serhiy projections and API wrapper
  coverage. The baseline was supplied only through the test environment.
- The 2026-09-04 portable Serhiy ZIP was rebuilt with Node v24.19.0, then its
  checksum, extracted launcher path, server entry point and embedded runtime
  version were verified.
- No writes were sent to either workbook, so no post-write verification exists.

## Owner-run application gate

1. Paste the reviewed source files and temporary wrappers into their matching
   Apps Script projects. Run previews only. Exact preflight hashes block the apply
   if any guarded 3D business range, exact CRM component record, or
   sync-journal row drifted. The CRM wrapper snapshots the affected parent sales
   rows, removes the four 20 UAH test projections, and reapplies any remaining
   non-test component cost from the canonical ledger.
2. Apply the 3D migration first. It creates a Drive backup, clears test business
   effects, imports 72 products, creates empty FIFO ledgers, and rebuilds 72
   availability rows plus 59 active analytics rows.
3. Publish the 3D Web App, then apply the CRM migration and publish CRM. During
   this short transition, legacy generic 3D sale appends are rejected before a
   stock mutation; retry synchronization after CRM publication.
4. Run the same CRM integrity check after apply, verify 72 unique SKUs, 59 active
   and 13 inactive, zero opening stock, Nami L 750/500, zero surviving test
   component/sync records, and matching RRP/activity states.
5. Delete both temporary migration files from the live Apps Script projects.

Cancellation/return restock is intentionally a separate explicit reversal path.
The current candidate blocks uncosted manual stock corrections and quantity edits
that conflict with an already committed operation. It does not silently recreate
stock on a CRM status change.

## Files changed in the local candidate

- `crm/apps-script/Code.gs`, `3d-print/apps-script-3dp-api/Code.gs`, and
  `3d-print/apps-script-3dp-api/CatalogFifo.gs` — linked activity, actual-batch
  FIFO, atomic sale/gift consumption, analytics and activation validation.
- `dashboard/booster-dashboard.html` — two-workbook active-state coordination and
  disabled uncosted stock adjustment control.
- `3d-print/serhiy-local-server/` — actual batch/defect/Serhiy-consumable inputs,
  FIFO preview, and the approved 18th detailed category.
- `scripts/build-3dp-catalog-migration.py` and
  `scripts/generate-3dp-catalog-wrappers.py` — deterministic payload and guarded
  owner-run preview/apply wrappers.
- `scripts/3dp-catalog-reset/Temporary3dpCatalogMigration.gs` and
  `TemporaryCrmCatalogMigration.gs` — temporary live-project files; delete after
  verified apply.
- `scripts/3dp-catalog-audit-read.py` — bounded GET-only helper, no credential output.
- `scripts/build-3dp-catalog-intake.py` — guarded source-to-manifest preparation.
- `scripts/check-3dp-catalog-contract.mjs` — offline syntax, accounting, FIFO,
  dashboard and migration-wrapper contract verification.
- `3d-print/serhiy-local-server/dist/Booster-3DP-Serhiy_Node-v24.19.0_20260904.zip`
  and its `.sha256.txt` checksum — verified portable handoff for Serhiy.
- Focused tests and source-state records listed in the repository diff.

Local rollback is a scoped revert of the candidate files. Live rollback uses the
two Drive backup copies created by the wrappers and requires owner action. The
two workbooks are separate transaction boundaries; the process never claims a
cross-workbook atomic commit.
