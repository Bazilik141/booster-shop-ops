# Codex Report — 3D-P-007 WP1c: draft status + SKU validator

Date: 2026-08-22

## Outcome

Two paste-ready Apps Script replacements are ready locally. Together they make a
new Serhiy product a non-sellable `Чернетка` with a generated `DRAFT-…` key;
only an owner can replace that temporary key with a canonical article and move
the row to `Активний`. No publication, live Sheet write, Git commit, push,
dashboard change, CRM structural change, `Продажі` column change, or token
change was made.

The 3D-P patch is byte-identical to the updated local 3D-P `Code.gs`:
`D10A0C0647EC82288D13AA99EC8E4919497239B7228329254C96A237385FE081`.

The CRM patch is byte-identical to the updated local CRM `Code.gs`:
`9E39510BCE3E4DAF5DDEDF3125E3A26A13D07E7C6A6FE7130A6E9AEB0FFFC907`.

## Source baseline verification

- 3D-P is based on the owner-proven V25/WP1b local bytes. The owner's V25 source
  export is present locally and the owner-read comparison harness uses it.
- The handoff calls the CRM baseline V122, but
  `crm/apps-script/SOURCE_STATE.md` records a newer owner-reported CRM V140.
  The CRM patch therefore uses the current CRM mirror, not the obsolete V122
  file. Its only functional CRM diff is the approved line in
  `apiOrderComponentCatalog_`: `API_статус_запису` must equal `Активний`.
- After this report was first prepared, the owner supplied fresh live exports
  V140 (`Версія 140, 21 серп. 2026 р., 1103.csv`) and V25 (`Версія 25, 22
  серп. 2026 р., 1735.txt`). Normalized comparison proves the CRM patch differs
  from V140 by exactly the approved line 7508. The 3D-P patch differs from V25
  only by the scoped WP1c implementation (344 inserted / 26 replaced lines).
  No rebase was needed.

## Delivered files

```text
patches/3D-P-007-WP1c_crm-active-status-sync-filter_20260822.js
patches/3D-P-007-WP1c_draft-status-and-sku-validator_20260822.js
diagnostics/3D-P-007-WP1c_draft-status-and-sku-validator_report_20260822.md
```

Local source and regression-harness changes are confined to:

```text
crm/apps-script/Code.gs
crm/apps-script/tests/3dp-sync-journal.test.mjs
3d-print/apps-script-3dp-api/Code.gs
3d-print/apps-script-3dp-api/tests/role-read-projections.test.mjs
```

## Implemented behaviour

### Draft creation and assignment

- `3dp_nomenclature_draft_create` is Serhiy-only. It creates one
  `Номенклатура` row with a generated `DRAFT-<32 uppercase hex>` temporary key,
  API status `Чернетка`, an API audit row, and a history entry. The `DRAFT-`
  shape cannot match the CRM 3D trigger `^(?:BR|FIG|ACC-3D)-…`.
- The creator accepts the specified product data. `B` name and `D` type are
  required; `C:F`, `G:J`, `L:N`, and `Q:S` are supported. Existing validation
  remains in force for Q/R/S. After creation, Serhiy still edits only the
  pre-existing manual fields; he cannot write either API-status column.
- `B:F` is now in the Serhiy nomenclature baseline projection because the new
  creator writes it. This is required to preserve the fail-closed rule that no
  Serhiy-writable field becomes unreadable if the full-economics switch changes.
  No customer/order fields or `Продажі` projection changed.
- `3dp_nomenclature_assign_sku` is owner-only. It requires a `Чернетка` row and
  an explicit article matching `^(BR|FIG|ACC-3D)-[A-Z0-9]{2,5}-\d{3}$`, rejects a
  duplicate Nomenclature article, and atomically changes the temporary key and
  status to `Активний` with history/audit records.
- Assignment refuses any draft having a matching 3D `Друк-лог` or `Продажі`
  row. Such a rename is a key migration and remains out of scope.
- Type-based output is only a deterministic `{prefix, category_digits,
  category_label}` suggestion. It contains no mnemonic and cannot assign an
  article automatically.
- Generic `3dp_write` is blocked for `Номенклатура!A`; there is no bypass around
  the owner assignment validator. A draft cannot be archived/restored as a way
  to become active; it must be assigned first.

### Complete status-branch enumeration

| Site | Draft behaviour | Test coverage |
|---|---|---|
| `3dp_get_row` | Hidden by default; only explicit `include_drafts=true` reads it for management. | Draft read rejected and explicit read accepted. |
| `3dp_skus` / bootstrap | Default catalogue contains `Активний` only. Explicit legacy `include_archived=true` returns non-active rows, including drafts. | Both result sets asserted. |
| `3dp_overview` | Counts active nomenclature only. | Draft does not increase SKU count. |
| CRM component catalog | Requires literal `Активний`; a draft cannot enter the sync catalog. | CRM mock proves draft omitted, active SKU retained. |
| `3dp_order_gifts_append` | Refuses draft before any gift/stock write. | Actual action returns `SKU_NOT_ACTIVE`. |
| `3dp_manufacture_batch` | Refuses draft before a print-log write. | Actual action returns `SKU_NOT_ACTIVE`. |
| `3dp_batch_draft_save` | Refuses draft before internal batch-draft write. | Actual action returns `SKU_NOT_ACTIVE`. |
| `3dp_adjust_stock` | Refuses draft before availability/ledger write. | Actual action returns `SKU_NOT_ACTIVE`. |
| `3dp_nomenclature_archive/restore` | Refuses a draft transition; assignment is the sole exit path. | Returns `DRAFT_ASSIGNMENT_REQUIRED`. |
| Status readers and setup whitelist | `Активний`, `Архів`, and `Чернетка` are valid; unknown values fail closed for catalogue reads. | Status/read and setup-validation assertions. |
| `Наявність!C:D` formulas | Count print-log data only when the matching Nomenclature row is `Активний`; a draft (or archive/unknown status) returns zero. | Targeted setup and both formulas asserted. |

There are no remaining nomenclature branches shaped as
`=== archivedStatus` or as a two-state active/archive whitelist. The remaining
`archivedStatus` checks apply solely to the independent `Друк-лог` archive
system.

## Local verification

All tests below passed locally. These are static/mocked checks, not live Apps
Script or Sheet proof.

```text
3D-P Code.gs syntax ok
CRM Code.gs syntax ok
Apps Script regression suites passed: 23
```

The 23 suites include all `*.test.mjs` files under both Apps Script projects.
The WP1c 3D-P harness additionally proves all four former archive guards,
default/explicit draft reads, formula migration idempotency, role restrictions,
strict/duplicate/history assignment failures, success assignment, suggestion
shape, and the baseline projection invariant. The CRM harness proves the draft
catalog filter and that `DRAFT-…` does not match the 3D trigger.

`git diff --check` passed. Both delivered patch files hash-identically match
their local source candidates.

## Deployment order — CRM first

1. **CRM first:** the fresh V140 comparison is complete; paste
   `3D-P-007-WP1c_crm-active-status-sync-filter_20260822.js`, publish a new CRM
   Web App version, and smoke-check that a known active 3D SKU remains in the
   component catalog.
2. **3D-P second:** paste
   `3D-P-007-WP1c_draft-status-and-sku-validator_20260822.js`, publish a new
   3D-P Web App version.
3. In the bound 3D-P editor run `preview3dpWp1cStatusSchema()`. If it reports
   only the targeted `Наявність!C:D` change, run
   `setup3dpWp1cStatusSchema()`. Do **not** run the older broad
   `setup3dpApi()` for this package.

CRM first is the safe direction: its stricter filter is inert while no drafts
exist. Publishing 3D-P first would open a window in which a draft could exist
while an old CRM filter still accepted every non-archived SKU.

## Owner QA checklist

- [ ] Confirm the CRM patch diff against fresh V140 remains the single approved
      active-status line before paste.
- [ ] After CRM publication, verify an active 3D component is still selectable;
      a deliberately simulated `Чернетка` must not be selectable.
- [ ] After 3D-P publication, run the WP1c preview, then the targeted setup;
      confirm only `Наявність!C:D` changes and repeat reports
      `already_applied:true`.
- [ ] Create one Serhiy draft. Confirm its key begins `DRAFT-`, O is
      `Чернетка`, and it is absent from the ordinary SKU list, stock corrections,
      batch printing, gifts, and CRM components.
- [ ] Confirm Serhiy cannot write O or assign an article.
- [ ] Confirm invalid/lowercase and duplicate articles are rejected; owner
      assignment with a valid explicit article changes O to `Активний`.
- [ ] Confirm a draft with an injected print/sale history is rejected for
      assignment; do not attempt a manual key rename.

## Rollback and risks

- Keep pre-paste source copies for **both** bound scripts. For CRM, use the
  actual source verified immediately before paste, not the obsolete V122 label.
- **Do not blindly republish 3D-P V25 after any draft was created.** V25 has no
  third status and would interpret `Чернетка` as a normal non-archived row.
  Before code rollback, owner must locate every `Номенклатура` row with
  `O = Чернетка` and either complete assignment under WP1c or change it to
  `Архів` after recording the reason. The targeted availability formulas may
  stay; they already fail closed for non-active rows.
- A failed CRM-first deployment is safe to stop: no drafts can yet be created.
  A 3D-P-first deployment is not safe and must be avoided.
- Apps Script source publication and owner QA remain external gates. This task
  performed neither.
