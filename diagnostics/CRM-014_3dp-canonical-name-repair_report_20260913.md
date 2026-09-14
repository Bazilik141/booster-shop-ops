# CRM-014 3D canonical-name repair — failed live preflight

Date: 2026-09-14

## Outcome

No spreadsheet cell was changed. The temporary CRM-014 repair must not be run
again: its live preview returned zero candidates even though the owner-visible
SKU list still displays generic `3D-друк` names.

The temporary local script and its dedicated test were removed. The separate
`CRM-014` file must also be removed from the bound Apps Script project before
another repair is attempted.

## Live evidence

The owner ran `crm014Preview3dpCanonicalNameRepair()` on 2026-09-14. Its output
was:

```json
{
  "ok": true,
  "action": "crm014_3dp_canonical_name_repair",
  "dry_run": true,
  "candidate_count": 0,
  "unresolved_count": 0,
  "absent_from_products": [],
  "write_performed": false
}
```

Immediately afterward, the dashboard `sku_list` view showed at least these
generic names: `BR-CHARM-100`, `BR-BULB-100`, `BR-SQUIR-100`, `BR-MEW-100`,
`BR-PIKA-100`, and `BR-CHARM-200` all render as `3D-друк`.

`apiSkuList_` reads `Майстер_Товарів`, so the display is direct evidence that
the user-facing problem remains. A zero preview is not proof that names are
correct.

## What is known and unknown

The failed script searched `Товари` plus its corresponding master row and
recognised the ordinary literal `3D-друк` in its local test. Therefore neither
the exact live source value, its Unicode characters, nor the script/runtime
version that produced the zero result has been established. Do not claim a
specific root cause from this output alone.

The known defect is the repair's false-negative gate: it allowed an empty
candidate list to look successful while the observable defect remained.

## Required next repair approach

1. Read only the exact cells for the six visible SKUs from both `Товари` and
   `Майстер_Товарів`, preserving the raw value and Unicode code points.
2. Confirm which source field feeds the displayed `apiSkuList_` name for each
   SKU and why the prior preview filtered it out.
3. Build a new isolated, temporary task script only after that evidence is
   recorded. Its preview must fail when a known visible placeholder yields zero
   candidates.
4. Require a displayed candidate list, owner approval, a bounded apply, reload
   of the SKU page, and a clean integrity check. Never overwrite formula
   projections manually.

## Scope

No production repair is claimed. The canonical OpenCart-backed names prepared
for CRM-014 are not applied and should be revalidated by the next executor
against the actual source cells before reuse.
