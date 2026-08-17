# CRM-CATALOG-OPTIONS — reference-list capacity gap diagnostic

Date: 2026-08-17

## Scope

Diagnose why the new-SKU workflow rejected `YGO-JP-BETB-BST` with
`catalog option list is full: AD4:AD44`, and define a durable correction.

No Apps Script, dashboard, Google Sheet, trigger, deployment, or CRM row was
changed in this investigation.

## Evidence

- The fresh owner-pasted Apps Script export is identical to
  `crm/apps-script/Code.gs` after end-of-line normalization. No secret values
  were inspected or recorded.
- The live CRM setting list `Налаштування!AD4:AD44` contains 41 non-blank set
  values. `BEYOND THE BRAVE` is absent; `YGO-JP-BETB-BST` is absent from
  `Товари`.
- The other three catalog option lists are also at their code-defined limits:
  `D4:D10` (brands), `G4:G8` (languages), and `J4:J15` (formats). The cells
  below those limits through row 60 are blank, but the code and validation
  rules do not use them.
- `Товари` validation rules point to the same closed ranges. In particular,
  the strict Set rule is `='Налаштування'!$AD$4:$AD$44`.
- The dashboard sends `allow_new_options:true` and explicitly promises that a
  new brand, language, set, or format is added in the same operation.

## Root cause

Yesterday's capacity feature manages append-only transaction/catalog *rows*.
`CRM_ROW_CAPACITY_CONFIG_` covers append-only ledgers, and the special catalog
path expands only `Товари`, `РРЦ`, and `Склад` together. It never manages the
four reference-list columns in `Налаштування`.

`apiAddSku_()` correctly calls `crmNextAppendRow_(ss, 'Товари', 1)`, but then
passes fixed A1 ranges into `apiCatalogOptionPlan_()`:

- brand: `D4:D10`
- language: `G4:G8`
- set: `AD4:AD44`
- format: `J4:J15`

That helper deliberately rejects a new value when no blank cell exists in the
given range. The request therefore failed before any product/RRP write. It did
not corrupt the catalog, but it also could never create a 42nd set.

## Did the feature have to work here?

There are two answers:

- The row-capacity implementation fulfilled its stated scope: it grows data
  rows and preserves their formula/validation structure. It would have handled
  this request if `BEYOND THE BRAVE` had already been in the 41-item set list
  and the `Товари`/`РРЦ`/`Склад` catalog rows needed expansion.
- The new-SKU product flow should have handled it. The dashboard promises that
  a new brand, language, set, or format is added in the same operation and
  gives no capacity limit. The server's fixed limit violates that promise.

The missing requirement was independent capacity for controlled reference
lists, including their validation source ranges. Calling both mechanisms
"adding rows" hid that distinction.

## Test gap

`row-capacity.test.mjs` tests transaction rows and the shared catalog rows; it
does not create `Налаштування` or exercise a reference list.

`catalog-sku-create.test.mjs` creates a 3D SKU whose four options already
exist. It does not test a new option at list capacity, the closed `Товари`
validation ranges, or a failed SKU creation after an option plan is made.

## Recommended durable correction

Keep the user-facing SKU flow. Do not manually add one value to `AD45`, delete
an old set, or hard-code a larger endpoint.

1. Introduce one `CRM_CATALOG_OPTION_CONFIG_` registry for brand, language,
   set, and format. Each entry owns its Settings column, first data row and the
   `Товари` column that validates against it.
2. Replace all scattered closed A1 literals with helpers derived from that
   registry: catalog-options read, option planning, and product validation.
3. Before writing a new option, ensure capacity in that option column. Use the
   blank cells already available through Settings row 60; when the Settings
   grid is exhausted, append a bounded batch of rows at the bottom and copy
   only the list-cell structure. Never insert within the existing settings
   layout or repurpose existing options.
4. Rebuild the four `Товари` validation rules over the current Settings grid
   range whenever Settings grows. This ensures both current catalog rows and
   future rows copied by `crmEnsureCatalogCapacity_()` accept the new values.
   Blank source cells remain harmless in a range-based dropdown.
5. Extend `apiIntegrityCheck_()` to cover the four reference lists and their
   validation-source ranges. At minimum it must detect duplicate option text,
   a missing configured list column, and a `Товари` validation range that no
   longer matches the managed Settings capacity.
6. Keep the existing ScriptLock and rollback behavior. If SKU projection or
   RRP verification fails, remove the newly proposed option value too; harmless
   preallocated blank Settings rows may remain.

This makes a new set, brand, language, or format work through the existing
dashboard API indefinitely, with no owner-side list maintenance and no fixed
"next limit" to rediscover.

## Required regression coverage

- A catalog row expansion with all options already present.
- A 42nd set while `AD4:AD44` is full: capacity grows, validation includes the
  new row, and the SKU/RRP is created once.
- The equivalent full-list cases for brand, language, and format.
- Retry of the same SKU/new option is idempotent; two requests cannot create a
  duplicate option while the ScriptLock is held.
- A later SKU validation/projection failure leaves no new option value or RRP
  data behind.
- Integrity check detects intentionally narrowed/drifted validation ranges.

## Owner gate and risk

This is a CRM schema/Apps Script change. It requires a reviewed source patch,
local tests, owner publication of a new Web App version, a before/after bounded
CRM integrity check, and owner dashboard QA. No deployment or production
mutation is authorized by this diagnostic.
