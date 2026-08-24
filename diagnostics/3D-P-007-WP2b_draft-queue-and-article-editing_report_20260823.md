# Codex Report — 3D-P-007 WP2b: draft queue and article editing

Date: 2026-08-24

## Outcome

Implemented the WP2b handoff on the original two implementation surfaces, then applied the owner's QA scope expansion for main-CRM catalogue reconciliation:

- the V29-based `3d-print/apps-script-3dp-api/Code.gs` mirror is now the complete owner-paste candidate;
- `dashboard/booster-dashboard.html` was edited in place and its `ROADMAP_TASKS` block was not touched.
- the V148-based `crm/apps-script/Code.gs` mirror now has a pending atomic, history-free 3D catalogue SKU rename path.

No commit, push, Apps Script publication, deployment, live CRM/Sheet data write, or Notion write occurred.

## Files touched

```text
3d-print/apps-script-3dp-api/Code.gs
3d-print/apps-script-3dp-api/tests/api.test.mjs
3d-print/apps-script-3dp-api/tests/role-read-projections.test.mjs
dashboard/booster-dashboard.html
dashboard/tests/dashboard-contract.test.mjs
dashboard/tests/3dp-sync-journal-static.test.mjs
crm/apps-script/Code.gs
crm/apps-script/SOURCE_STATE.md
crm/apps-script/tests/catalog-sku-create.test.mjs
diagnostics/3D-P-007-WP2b_draft-queue-and-article-editing_report_20260823.md
```

## Apps Script implementation

`assignNomenclatureSkuAction3dp_` remains owner-only and now accepts exactly two starting statuses:

- `Чернетка`: assign the canonical article and transition to `Активний`;
- `Активний`: rename the article and leave the status unchanged.

All other statuses return `SKU_STATUS_NOT_EDITABLE`. `expected_draft_sku` remains accepted and is repeated by the dashboard, but in the current implementation it is compared with the same request key used to locate the row. It is therefore a redundant consistency check, not an independent optimistic lock. The row lookup itself still fails closed if the old key no longer exists.

Before a key change, `nomenclatureKeyHistory3dp_` checks these SKU-key locations:

- `Друк-лог`;
- `Продажі`;
- `_Чернетки_партій`, including `role::SKU` storage keys;
- `_Коригування_наявності`;
- `Маркетингові_плюшки`;
- `Аналітика`, where only a manually stored SKU is history and a formula mirror is not;
- `Наявність`, where only a manually stored SKU is history and a formula mirror is not.

Any stored match returns `SKU_HISTORY_EXISTS`, naming the blocking sheet and row. The code refuses migration; it does not rewrite historical keys.

After a successful rename, `syncActiveNomenclatureAnalytics3dp_` runs. Tests confirm that the formula-backed Analytics row follows the new article and the old article does not remain as an orphan. One `API_історія_змін` line records the actor and article change; one `_Аудит_API` row records old and new article/status values. A forced audit failure test confirms rollback of SKU, status, history, and the Analytics calculator snapshot.

The existing asymmetry remains intentionally unchanged: `assignNomenclatureSkuAction3dp_` still does not call `assertNomenclatureSkuMatchesType3dp_`, while owner quick-create does. Tightening that rule was explicitly out of WP2b scope because existing drafts could become invalid.

## Live read-only evidence: `Наявність!A`

Target spreadsheet metadata resolved the exact visible sheet `Наявність` (`sheetId=2085799752`). A bounded formula read of `Наявність!A1:A4` returned:

```text
A1  SKU
A2  ='Номенклатура'!A2
A3  ='Номенклатура'!A3
A4  ='Номенклатура'!A4
```

Therefore `Наявність!A` is a formula mirror of `Номенклатура!A`; a valid rename follows automatically. The guard still fails closed if a future/manual row stores the SKU as a literal.

## Dashboard implementation

The `Вироби` zone now contains:

- a draft queue sourced from the existing `3dp_bootstrap?include_archived=true` SKU payload;
- every `Чернетка` row, sorted by descending `row_number`, with its `DRAFT-` key, name, type, and non-empty Serhiy-supplied values;
- a shared article editor for queued drafts and selected active products;
- a native mechanic/category dropdown backed by `NOMENCLATURE_DRAFT_SUGGESTIONS_3DP`; every option displays the full category, prefix, and three category digits;
- one unrestricted `Вставити новий артикул` field that accepts the complete owner-pasted article without uppercasing, generating, or truncating it;
- no mnemonic generator, prefix/digit preview, or mnemonic confirmation checkbox; the existing final article-change confirmation dialog remains;
- direct display of the API error message, so `SKU_HISTORY_EXISTS` identifies the blocking location.

The editor reuses `call3dpPost`, the existing separate 3D-P credential store, and `3dp_nomenclature_assign_sku`. No API client or token store was added. Drafts are excluded from active calculator/select controls.

For an active SKU, article editing now preflights the exact old CRM SKU and a clean bounded CRM integrity result. After the 3D-P rename succeeds, the dashboard calls the main CRM action with both old and new SKU. The CRM action changes only the manual key in `Товари!A`, verifies that the aligned `РРЦ!A` and `Склад!A` formula projections follow, synchronizes the existing RRP row, and rolls the SKU/RRP back on any verification failure. It refuses duplicate targets and stored key history in sales, purchases, write-offs, inventory migrations, 3D order accounting, component usage, or fixture usage.

The sync button also repairs the owner-observed already-split state: if the new 3D-P SKU is missing from CRM, it searches for exactly one 3D CRM SKU with the same normalized product name and offers to rename that existing row. It creates a new CRM row only when no rename candidate exists.

Responsive CSS uses an auto-fitting grid, wrapping for long keys/URLs, and explicit focus states. A real three-viewport browser pass could not be completed because the in-app browser security policy blocks local `file://` navigation and forbids an alternate-browser/localhost workaround. This remains part of owner visual QA after `Ctrl+F5`.

## Dashboard bounded evidence

| Measure | Before | After |
|---|---:|---:|
| Lines | 4,372 | 4,433 |
| Bytes | 574,000 | 586,682 |
| `ROADMAP_TASKS` SHA-256 | `717ea460…b2f66d9` | `717ea460…b2f66d9` |

The current dashboard also contains the owner's parallel write-off layout fix (`writeoff-line` plus two CSS rules). It is independent of WP2b and contract-tested. A diff-added-line scan found none of these signatures: `ROADMAP_TASKS`, a new Apps Script `/exec` URL, either 3D-P token property name, `!important`, `setTimeout`, or `position:absolute/fixed`.

## Verification

Passed:

```text
node 3d-print/apps-script-3dp-api/tests/api.test.mjs
node 3d-print/apps-script-3dp-api/tests/role-read-projections.test.mjs
node dashboard/tests/dashboard-contract.test.mjs
node dashboard/tests/3dp-sync-journal-static.test.mjs
all 20 crm/apps-script/tests/*.test.mjs files (sequential)
Node VM syntax parse of both Apps Script Code.gs files
git diff --check
```

The supplied CRM V148 export was reconciled before the pending CRM rename implementation. After newline normalisation it was identical to the mirror at that point: 8395 lines, 513365 bytes, SHA-256 `688f7a6476aea597f76fae7f307bf3a5f3a79b465e33b401098fae57317bea57`. The current mirror contains the additional undeployed rename candidate. All 20 currently present local CRM test files passed. The catalogue suite specifically covers a successful `FIG-LUFFY-411` to `FIG-LUFFY-410` history-free rename, stored sales-history refusal without a write, and rollback when the `Склад!A` formula projection does not follow.

The API test now executes the complete role/read harness and covers:

- draft assignment regression;
- active rename with unchanged status;
- archived/non-editable status refusal (`SKU_STATUS_NOT_EDITABLE`);
- refusal and blocking-sheet message for all seven stored-key locations;
- non-canonical article refusal;
- duplicate article refusal;
- non-owner refusal;
- generic `3dp_write` refusal for `Номенклатура!A`;
- audit-failure rollback;
- Analytics and Availability formula mirrors following a rename.

`node --test tests/3d-p-013-dashboard-ui-regression.test.mjs` hit the known Windows sandbox `spawn EPERM`. Direct execution ran but reported three stale assertions. All three reproduce against `HEAD` before WP2b: the old information-render regex is already absent, the old stock-adjustment return regex is already absent, and baseline `saveThreeDpProduct` already contains three reloads while the test expects two. The WP2b dashboard contract is green; the stale suite was not rewritten in this scope.

## Risks and remaining gates

- SKU is a join key. The implementation deliberately refuses stored history instead of migrating it.
- Article rename still does not enforce the SKU-prefix/type assertion; this is the handoff's documented out-of-scope asymmetry.
- The 3D-P and CRM scripts cannot share one transaction. The dashboard performs 3D-P first and CRM second; if CRM fails after 3D-P succeeds, it reports the exact partial state and the sync button retries reconciliation without creating a duplicate.
- CRM rename is deliberately limited to a history-free, exact-name, aligned catalogue row. Any operational history refuses the rename instead of rewriting historical records.
- Local source is not publication. The owner must create a named Sheet version, paste the complete `Code.gs`, publish a new Web App version, hard-refresh the dashboard, and run the handoff QA checklist.
- After publication, export the labelled Apps Script version so `Code.gs` and `SOURCE_STATE.md` can be reconciled to the deployed version in a separate owner-authorized pass.

## Owner QA

- [ ] Create named Google Sheets versions before changing either Apps Script project.
- [ ] Paste the complete main CRM `Code.gs` candidate first and publish a new CRM Web App version.
- [ ] Paste the complete 3D-P `Code.gs` candidate second and publish a new 3D-P Web App version.
- [ ] `Ctrl+F5` the dashboard and inspect the PC-only `Вироби` zone at the owner's normal desktop width.
- [ ] For the already-split product, select its new 3D-P SKU and click `Синхронізувати артикул / РРЦ з CRM`; confirm the dialog names the existing old CRM SKU and offers a rename, not creation.
- [ ] After accepting, confirm the same CRM catalogue row now has the new SKU in `Товари!A`, the aligned `РРЦ!A` and `Склад!A` formula projections followed it, no duplicate catalogue row appeared, and CRM integrity is clean.
- [ ] Create a throwaway Serhiy draft and confirm every supplied value appears newest-first.
- [ ] Open the mechanic/category dropdown and confirm every option keeps its full prefix and category digits.
- [ ] Paste a complete new article into `Вставити новий артикул`; confirm it is not rewritten or shortened before submission.
- [ ] Rename a designated active SKU with no history; confirm status remains `Активний`, Analytics follows, history/audit name the actor, and CRM expectations are safe.
- [ ] Attempt rename on SKUs with print/sales and another stored-key history location; confirm the API names the blocking sheet.
- [ ] Confirm Serhiy remains forbidden from assigning or renaming articles.
- [ ] Export and hand over the labelled published source.
